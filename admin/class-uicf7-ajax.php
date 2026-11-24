<?php
/**
 * AJAX endpoints for the admin screens.
 *
 * Every endpoint checks the nonce and the user capability first.
 *
 * @package UniInbox_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * AJAX controller.
 */
class UICF7_Ajax {

	const NONCE = 'uicf7_ajax';

	/**
	 * Register endpoints.
	 *
	 * @return void
	 */
	public static function init() {
		foreach ( array( 'queue', 'star', 'assist', 'test_ai', 'test_alert' ) as $action ) {
			add_action( 'wp_ajax_uicf7_' . $action, array( __CLASS__, $action ) );
		}
	}

	/**
	 * Nonce and capability check.
	 *
	 * @return void
	 */
	private static function guard() {
		if ( ! check_ajax_referer( self::NONCE, 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Your session expired. Reload the page and try again.', 'uniinbox-for-contact-form-7' ) ), 403 );
		}
		if ( ! current_user_can( UICF7_Admin::CAP ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to do that.', 'uniinbox-for-contact-form-7' ) ), 403 );
		}
	}

	/**
	 * Read a sanitized POST value. guard() has verified the nonce.
	 *
	 * @param string $key       Key.
	 * @param bool   $multiline Keep line breaks.
	 * @return string
	 */
	private static function post( $key, $multiline = false ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in guard().
		if ( ! isset( $_POST[ $key ] ) || is_array( $_POST[ $key ] ) ) {
			return '';
		}
		$value = wp_unslash( $_POST[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized on the next line.
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		return $multiline ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
	}

	/**
	 * Analyze the next due message and return its updated cells.
	 *
	 * @return void
	 */
	public static function queue() {
		self::guard();

		if ( ! UICF7_Settings::ai_ready() ) {
			wp_send_json_error( array( 'message' => __( 'AI analysis is off.', 'uniinbox-for-contact-form-7' ) ) );
		}

		$item = null;
		$ids  = UICF7_Repository::next_queue_ids( 1 );
		if ( ! empty( $ids ) ) {
			$id = (int) $ids[0];
			UICF7_Cron::process( $id );
			$row = UICF7_Repository::get( $id );
			if ( $row ) {
				$item = array(
					'id'     => $id,
					'status' => $row['ai_status'],
					'note'   => $row['ai_note'],
					'spam'   => 'spam' === $row['status'],
					'cells'  => array(
						'message'  => UICF7_List_Table::render_message_cell( $row ),
						'category' => UICF7_UI::category_badge( $row['category'] ),
						'urgency'  => UICF7_UI::urgency_badge( $row['urgency'] ),
					),
				);
			}
		}

		$counts = UICF7_Repository::queue_counts();
		wp_send_json_success(
			array(
				'item'    => $item,
				'due'     => $counts['due'],
				'waiting' => $counts['waiting'],
			)
		);
	}

	/**
	 * Toggle the star on a message.
	 *
	 * @return void
	 */
	public static function star() {
		self::guard();
		$id = absint( self::post( 'id' ) );
		if ( ! $id || ! UICF7_Repository::get( $id ) ) {
			wp_send_json_error( array( 'message' => __( 'Message not found.', 'uniinbox-for-contact-form-7' ) ) );
		}
		wp_send_json_success( array( 'starred' => UICF7_Repository::toggle_star( $id ) ) );
	}

	/**
	 * AI writing help for the reply editor.
	 *
	 * @return void
	 */
	public static function assist() {
		self::guard();

		$message = UICF7_Repository::get( absint( self::post( 'id' ) ) );
		if ( ! $message ) {
			wp_send_json_error( array( 'message' => __( 'Message not found.', 'uniinbox-for-contact-form-7' ) ) );
		}
		if ( ! UICF7_Settings::ai_ready() ) {
			wp_send_json_error( array( 'message' => __( 'Turn on AI and add an API key in Settings first.', 'uniinbox-for-contact-form-7' ) ) );
		}
		if ( ! UICF7_Cron::within_daily_cap() ) {
			wp_send_json_error( array( 'message' => __( 'Your daily AI limit has been reached. You can raise it in Settings.', 'uniinbox-for-contact-form-7' ) ) );
		}

		UICF7_Cron::count_call();
		$text = UICF7_AI::assist( $message, self::post( 'text', true ), sanitize_key( self::post( 'mode' ) ) );
		if ( is_wp_error( $text ) ) {
			wp_send_json_error( array( 'message' => $text->get_error_message() ) );
		}
		wp_send_json_success( array( 'html' => UICF7_Mailer::text_to_html( $text ) ) );
	}

	/**
	 * Test an AI provider with the values currently on the form (saved or not).
	 *
	 * @return void
	 */
	public static function test_ai() {
		self::guard();

		$config   = UICF7_Settings::all();
		$provider = sanitize_key( self::post( 'provider' ) );
		if ( ! array_key_exists( $provider, UICF7_Settings::provider_meta() ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a provider first.', 'uniinbox-for-contact-form-7' ) ) );
		}
		$config['provider'] = $provider;

		$model = trim( self::post( 'model' ) );
		if ( UICF7_Settings::valid_model( $model ) ) {
			$config[ 'model_' . $provider ] = $model;
		}
		$key = trim( self::post( 'key' ) );
		if ( '' !== $key ) {
			$config[ 'api_key_' . $provider ] = $key;
		}
		$meta = UICF7_Settings::provider_meta();
		if ( empty( $meta[ $provider ]['builtin'] ) && '' === (string) $config[ 'api_key_' . $provider ] ) {
			wp_send_json_error( array( 'message' => __( 'Paste an API key first.', 'uniinbox-for-contact-form-7' ) ) );
		}

		$result = UICF7_AI::test( $config );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success(
			array(
				/* translators: %s: provider name. */
				'message' => sprintf( __( 'Connected to %s. AI analysis works.', 'uniinbox-for-contact-form-7' ), UICF7_Settings::providers()[ $provider ] ),
			)
		);
	}

	/**
	 * Send a test alert with the values currently on the form.
	 *
	 * @return void
	 */
	public static function test_alert() {
		self::guard();

		$config                     = UICF7_Settings::all();
		$config['notify_email']     = UICF7_Settings::sanitize_email_list( self::post( 'email' ) );
		$config['slack_webhook']    = UICF7_Settings::sanitize_slack( self::post( 'slack' ) );
		$config['telegram_chat_id'] = UICF7_Settings::sanitize_telegram_chat( self::post( 'chat' ) );
		$token                      = UICF7_Settings::sanitize_telegram_token( self::post( 'token' ) );
		if ( '' !== $token ) {
			$config['telegram_token'] = $token;
		}

		$results = UICF7_Notifier::send_test( $config );
		if ( empty( $results ) ) {
			wp_send_json_error( array( 'message' => __( 'Add an email address, a Slack webhook or a Telegram bot first.', 'uniinbox-for-contact-form-7' ) ) );
		}

		$names  = array(
			'email'    => __( 'Email', 'uniinbox-for-contact-form-7' ),
			'slack'    => 'Slack',
			'telegram' => 'Telegram',
		);
		$ok     = array();
		$errors = array();
		foreach ( $results as $channel => $result ) {
			if ( is_wp_error( $result ) ) {
				$errors[] = $result->get_error_message();
			} else {
				$ok[] = $names[ $channel ];
			}
		}
		if ( ! empty( $errors ) ) {
			wp_send_json_error( array( 'message' => implode( ' ', $errors ) ) );
		}
		/* translators: %s: list of channels, e.g. "Email, Slack". */
		wp_send_json_success( array( 'message' => sprintf( __( 'Test alert sent: %s.', 'uniinbox-for-contact-form-7' ), implode( ', ', $ok ) ) ) );
	}
}
