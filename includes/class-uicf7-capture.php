<?php
/**
 * Captures Contact Form 7 submissions into the inbox table.
 *
 * @package UniInbox_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Submission capture.
 */
class UICF7_Capture {

	const MAX_BODY = 20000;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'wpcf7_before_send_mail', array( __CLASS__, 'capture' ), 20, 3 );
	}

	/**
	 * Store a submission. Never interrupts the form: failures are silent.
	 *
	 * @param WPCF7_ContactForm $contact_form Form object.
	 * @param bool              $abort        Whether another plugin aborted sending.
	 * @param WPCF7_Submission  $submission   Submission object.
	 * @return void
	 */
	public static function capture( $contact_form, $abort = false, $submission = null ) {
		if ( $abort || ! is_object( $contact_form ) ) {
			return;
		}

		if ( ! is_object( $submission ) && class_exists( 'WPCF7_Submission' ) ) {
			$submission = WPCF7_Submission::get_instance();
		}
		if ( ! is_object( $submission ) ) {
			return;
		}

		$form_id = (int) $contact_form->id();
		if ( in_array( $form_id, array_map( 'intval', (array) UICF7_Settings::get( 'excluded_forms', array() ) ), true ) ) {
			return;
		}

		$posted = $submission->get_posted_data();
		if ( ! is_array( $posted ) || empty( $posted ) ) {
			return;
		}

		$fields = self::clean_fields( $posted );
		if ( empty( $fields ) ) {
			return;
		}

		$body = self::build_body( $fields );
		$body = (string) mb_substr( $body, 0, self::MAX_BODY );

		$message = array(
			'form_id'      => $form_id,
			'form_title'   => mb_substr( sanitize_text_field( $contact_form->title() ), 0, 255 ),
			'sender_name'  => mb_substr( self::find_name( $fields ), 0, 190 ),
			'sender_email' => mb_substr( self::find_email( $fields ), 0, 190 ),
			'subject'      => mb_substr( self::find_subject( $fields ), 0, 255 ),
			'body'         => $body,
			'field_data'   => wp_json_encode( $fields ),
			'status'       => 'unread',
			'created_at'   => current_time( 'mysql', true ),
		);

		$skip_reason = self::prefilter( $body );

		if ( ! UICF7_Settings::ai_ready() ) {
			$message['ai_status'] = 'off';
		} elseif ( '' !== $skip_reason ) {
			$message['ai_status'] = 'skipped';
			$message['ai_note']   = $skip_reason;
		} elseif ( ! UICF7_Settings::get( 'auto_analyze', 1 ) ) {
			$message['ai_status'] = 'off';
			$message['ai_note']   = __( 'Automatic analysis is off.', 'uniinbox-for-contact-form-7' );
		} else {
			$message['ai_status']   = 'pending';
			$message['ai_provider'] = (string) UICF7_Settings::get( 'provider' );
		}

		$id = UICF7_Repository::insert( $message );

		if ( $id && 'pending' === $message['ai_status'] ) {
			wp_schedule_single_event( time() + 5, 'uicf7_analyze_message', array( $id ) );
			spawn_cron();
		}
	}

	/**
	 * Flatten and sanitize posted data. Internal "_" fields are dropped.
	 *
	 * @param array $posted Posted data from CF7.
	 * @return array<string,string>
	 */
	private static function clean_fields( array $posted ) {
		$fields = array();
		foreach ( $posted as $key => $value ) {
			$key = sanitize_text_field( (string) $key );
			if ( '' === $key || '_' === $key[0] ) {
				continue;
			}
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'strval', array_filter( $value, 'is_scalar' ) ) );
			} elseif ( ! is_scalar( $value ) ) {
				continue;
			}
			$value = trim( sanitize_textarea_field( (string) $value ) );
			if ( '' !== $value ) {
				$fields[ $key ] = $value;
			}
		}
		return $fields;
	}

	/**
	 * Human readable "Label: value" text.
	 *
	 * @param array<string,string> $fields Cleaned fields.
	 * @return string
	 */
	private static function build_body( array $fields ) {
		$lines = array();
		foreach ( $fields as $key => $value ) {
			$label   = ucfirst( trim( str_replace( array( '-', '_' ), ' ', $key ) ) );
			$lines[] = $label . ': ' . $value;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Find a sender email.
	 *
	 * @param array<string,string> $fields Cleaned fields.
	 * @return string
	 */
	private static function find_email( array $fields ) {
		foreach ( $fields as $key => $value ) {
			if ( false !== stripos( $key, 'email' ) && is_email( $value ) ) {
				return sanitize_email( $value );
			}
		}
		foreach ( $fields as $value ) {
			if ( is_email( $value ) ) {
				return sanitize_email( $value );
			}
		}
		return '';
	}

	/**
	 * Find a sender name.
	 *
	 * @param array<string,string> $fields Cleaned fields.
	 * @return string
	 */
	private static function find_name( array $fields ) {
		if ( isset( $fields['your-name'] ) ) {
			return $fields['your-name'];
		}
		foreach ( $fields as $key => $value ) {
			if ( false !== stripos( $key, 'name' ) && false === strpos( $value, "\n" ) ) {
				return $value;
			}
		}
		return '';
	}

	/**
	 * Find a subject line.
	 *
	 * @param array<string,string> $fields Cleaned fields.
	 * @return string
	 */
	private static function find_subject( array $fields ) {
		if ( isset( $fields['your-subject'] ) ) {
			return $fields['your-subject'];
		}
		foreach ( $fields as $key => $value ) {
			if ( false !== stripos( $key, 'subject' ) && false === strpos( $value, "\n" ) ) {
				return $value;
			}
		}
		return '';
	}

	/**
	 * Cheap local checks that avoid spending AI credits on junk.
	 *
	 * @param string $body Message body.
	 * @return string Reason, or empty string when the message should be analyzed.
	 */
	private static function prefilter( $body ) {
		$min = (int) UICF7_Settings::get( 'min_chars', 10 );
		if ( $min > 0 && mb_strlen( $body ) < $min ) {
			return __( 'Skipped: message too short.', 'uniinbox-for-contact-form-7' );
		}

		$max_links = (int) UICF7_Settings::get( 'max_links', 3 );
		if ( $max_links > 0 ) {
			$links = preg_match_all( '#https?://|www\.#i', $body );
			if ( $links > $max_links ) {
				return __( 'Skipped: too many links.', 'uniinbox-for-contact-form-7' );
			}
		}

		return '';
	}
}
