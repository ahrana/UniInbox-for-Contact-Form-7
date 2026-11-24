<?php
/**
 * Background work: the AI analysis queue, retention clean-up and the weekly digest.
 *
 * New messages are queued as "pending". They are processed by a single cron
 * event a few seconds after submission, by a 15-minute sweep, and live from
 * the inbox screen. A claim/lock makes sure each message is analyzed once.
 *
 * @package UniInbox_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Cron callbacks and the analysis engine.
 */
class UICF7_Cron {

	const MAX_RETRIES = 3;

	/**
	 * Single event scheduled right after a submission.
	 *
	 * @param int $id      Message ID.
	 * @param int $attempt Unused; kept for events scheduled by older versions.
	 * @return void
	 */
	public static function analyze( $id, $attempt = 0 ) {
		unset( $attempt );
		self::process( absint( $id ) );
	}

	/**
	 * 15-minute sweep: picks up anything still waiting (retries, missed events).
	 *
	 * @return void
	 */
	public static function sweep() {
		if ( ! UICF7_Settings::ai_ready() ) {
			return;
		}
		$started = time();
		foreach ( UICF7_Repository::next_queue_ids( 5 ) as $id ) {
			if ( time() - $started > 45 ) {
				break;
			}
			self::process( $id );
		}
	}

	/**
	 * Claim and analyze one queued message.
	 *
	 * @param int $id Message ID.
	 * @return string done|busy|pending|failed|off
	 */
	public static function process( $id ) {
		if ( ! UICF7_Repository::claim( $id ) ) {
			return 'busy';
		}
		if ( true === self::analyze_now( $id, true ) ) {
			return 'done';
		}
		$row = UICF7_Repository::get( $id );
		return $row ? (string) $row['ai_status'] : 'failed';
	}

	/**
	 * Analyze a message now.
	 *
	 * @param int  $id     Message ID.
	 * @param bool $queued True when run by the queue (retries allowed, alerts sent).
	 * @return true|WP_Error
	 */
	public static function analyze_now( $id, $queued = false ) {
		$message = UICF7_Repository::get( $id );
		if ( ! $message ) {
			return new WP_Error( 'uicf7_missing', __( 'Message not found.', 'uniinbox-for-contact-form-7' ) );
		}

		if ( ! UICF7_Settings::ai_ready() ) {
			UICF7_Repository::update(
				$id,
				array(
					'ai_status'    => 'off',
					'ai_locked_at' => null,
				)
			);
			return new WP_Error( 'uicf7_ai_off', __( 'AI analysis is switched off or no API key is saved.', 'uniinbox-for-contact-form-7' ) );
		}

		if ( ! self::within_daily_cap() ) {
			if ( $queued ) {
				UICF7_Repository::update(
					$id,
					array(
						'ai_status'    => 'pending',
						'ai_next_at'   => gmdate( 'Y-m-d 00:05:00', time() + DAY_IN_SECONDS ),
						'ai_locked_at' => null,
						'ai_note'      => __( 'Daily AI limit reached. Analysis continues tomorrow.', 'uniinbox-for-contact-form-7' ),
					)
				);
			}
			return new WP_Error( 'uicf7_cap', __( 'Your daily AI limit has been reached. You can raise it in Settings.', 'uniinbox-for-contact-form-7' ) );
		}

		self::count_call();
		$result = UICF7_AI::analyze( $message );

		if ( is_wp_error( $result ) ) {
			$attempts = (int) $message['ai_attempts'];
			if ( $queued && 'uicf7_rate' === $result->get_error_code() && $attempts < self::MAX_RETRIES ) {
				UICF7_Repository::update(
					$id,
					array(
						'ai_status'    => 'pending',
						'ai_attempts'  => $attempts + 1,
						'ai_next_at'   => UICF7_Repository::now( 120 * ( $attempts + 1 ) ),
						'ai_locked_at' => null,
						'ai_note'      => __( 'The AI provider limit was reached. Trying again in a few minutes.', 'uniinbox-for-contact-form-7' ),
					)
				);
				return $result;
			}

			UICF7_Repository::update(
				$id,
				array(
					'ai_status'    => 'failed',
					'ai_note'      => mb_substr( $result->get_error_message(), 0, 255 ),
					'ai_next_at'   => null,
					'ai_locked_at' => null,
				)
			);
			return $result;
		}

		$update = array(
			'category'     => $result['category'],
			'urgency'      => $result['urgency'],
			'summary'      => $result['summary'],
			'extracted'    => wp_json_encode( $result['extracted'] ),
			'ai_status'    => 'done',
			'ai_note'      => '',
			'ai_provider'  => (string) UICF7_Settings::get( 'provider' ),
			'ai_attempts'  => 0,
			'ai_next_at'   => null,
			'ai_locked_at' => null,
		);

		// Never overwrite a draft someone already wrote or saved.
		if ( '' === trim( (string) $message['draft_reply'] ) && 'replied' !== $message['status'] ) {
			$update['draft_reply'] = UICF7_Mailer::text_to_html( $result['draft_reply'] );
		}

		// Messages the AI calls spam move to the Spam view. They are not deleted.
		if ( 'spam' === $result['category'] && 'unread' === $message['status'] ) {
			$update['status'] = 'spam';
		}

		UICF7_Repository::update( $id, $update );

		// Alert once, and only for fresh messages (not when old mail is analyzed in bulk).
		$fresh = strtotime( $message['created_at'] . ' UTC' ) > time() - DAY_IN_SECONDS;
		if ( $queued && $fresh && empty( $message['notified_at'] ) ) {
			$row = UICF7_Repository::get( $id );
			if ( $row && UICF7_Notifier::maybe_notify( $row ) ) {
				UICF7_Repository::update( $id, array( 'notified_at' => UICF7_Repository::now() ) );
			}
		}

		return true;
	}

	/**
	 * AI calls made today and the daily limit.
	 *
	 * @return array{used:int,cap:int}
	 */
	public static function usage_today() {
		$usage = get_option( 'uicf7_ai_usage', array() );
		$used  = ( is_array( $usage ) && isset( $usage['day'], $usage['count'] ) && gmdate( 'Y-m-d' ) === $usage['day'] ) ? (int) $usage['count'] : 0;
		return array(
			'used' => $used,
			'cap'  => (int) UICF7_Settings::get( 'daily_cap', 200 ),
		);
	}

	/**
	 * Whether another AI call is allowed today.
	 *
	 * @return bool
	 */
	public static function within_daily_cap() {
		$usage = self::usage_today();
		return $usage['cap'] < 1 || $usage['used'] < $usage['cap'];
	}

	/**
	 * Count one AI call for today.
	 *
	 * @return void
	 */
	public static function count_call() {
		$usage = self::usage_today();
		update_option(
			'uicf7_ai_usage',
			array(
				'day'   => gmdate( 'Y-m-d' ),
				'count' => $usage['used'] + 1,
			),
			false
		);
	}

	/**
	 * Daily job: apply the retention period.
	 *
	 * @return void
	 */
	public static function daily() {
		$days = (int) UICF7_Settings::get( 'retention_days', 0 );
		if ( $days > 0 ) {
			UICF7_Repository::purge_older_than( $days );
		}
	}

	/**
	 * Weekly job: email a short summary.
	 *
	 * @return void
	 */
	public static function weekly_digest() {
		if ( ! UICF7_Settings::get( 'digest_enabled' ) ) {
			return;
		}

		$to = (string) UICF7_Settings::get( 'digest_email', '' );
		if ( ! is_email( $to ) ) {
			$to = (string) get_option( 'admin_email' );
		}
		if ( ! is_email( $to ) ) {
			return;
		}

		$stats      = UICF7_Repository::digest_stats( 7 );
		$categories = UICF7_Settings::categories();
		$site       = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );

		$lines = array(
			sprintf(
				/* translators: %d: number of messages. */
				_n( '%d new message in the last 7 days.', '%d new messages in the last 7 days.', $stats['total'], 'uniinbox-for-contact-form-7' ),
				$stats['total']
			),
		);
		foreach ( $stats['by_category'] as $row ) {
			if ( '' === $row['category'] ) {
				$label = __( 'Not analyzed', 'uniinbox-for-contact-form-7' );
			} else {
				$label = isset( $categories[ $row['category'] ] ) ? $categories[ $row['category'] ] : $row['category'];
			}
			$lines[] = '- ' . $label . ': ' . (int) $row['total'];
		}
		$lines[] = '';
		/* translators: %d: number of messages. */
		$lines[] = sprintf( __( 'High-priority messages still waiting: %d', 'uniinbox-for-contact-form-7' ), $stats['unanswered_high'] );
		/* translators: %d: number of messages. */
		$lines[] = sprintf( __( 'Unread messages: %d', 'uniinbox-for-contact-form-7' ), $stats['unread'] );
		$lines[] = '';
		$lines[] = admin_url( 'admin.php?page=uicf7-inbox' );

		/* translators: %s: site name. */
		$subject = sprintf( __( '[%s] Your weekly inbox digest', 'uniinbox-for-contact-form-7' ), $site );
		wp_mail( $to, str_replace( array( "\r", "\n" ), ' ', wp_strip_all_tags( $subject ) ), implode( "\n", $lines ) );
	}
}
