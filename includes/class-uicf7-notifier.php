<?php
/**
 * Routing: email, Slack and Telegram alerts for important messages.
 *
 * Alerts contain the AI summary and a link to the inbox, not the full message.
 *
 * @package UniInbox_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Notifier.
 */
class UICF7_Notifier {

	/**
	 * Send alerts for an analyzed message when it matches the routing rules.
	 *
	 * @param array $message Row from the messages table.
	 * @return bool Whether the message matched the rules (and alerts were sent).
	 */
	public static function maybe_notify( array $message ) {
		if ( ! self::should_notify( $message ) ) {
			return false;
		}
		self::dispatch( self::compose( $message ), UICF7_Settings::all() );
		return true;
	}

	/**
	 * Send a test alert on every configured channel.
	 *
	 * @param array|null $config Settings to use (unsaved values allowed).
	 * @return array<string,true|WP_Error> Channel => result.
	 */
	public static function send_test( $config = null ) {
		$text = array(
			'subject' => __( 'UniInbox test alert', 'uniinbox-for-contact-form-7' ),
			'body'    => __( 'This is a test alert from UniInbox for Contact Form 7. Your notification channel works.', 'uniinbox-for-contact-form-7' ),
		);
		return self::dispatch( $text, is_array( $config ) ? $config : UICF7_Settings::all() );
	}

	/**
	 * Check routing rules.
	 *
	 * @param array $message Message row.
	 * @return bool
	 */
	private static function should_notify( array $message ) {
		if ( empty( $message['category'] ) || 'spam' === $message['category'] || empty( $message['urgency'] ) ) {
			return false;
		}

		$rank = array(
			'low'    => 1,
			'medium' => 2,
			'high'   => 3,
		);
		$min  = (string) UICF7_Settings::get( 'notify_min_urgency', 'high' );
		if ( ! isset( $rank[ $message['urgency'] ], $rank[ $min ] ) || $rank[ $message['urgency'] ] < $rank[ $min ] ) {
			return false;
		}

		return in_array( $message['category'], UICF7_Settings::alert_categories(), true );
	}

	/**
	 * Build the alert text.
	 *
	 * @param array $message Message row.
	 * @return array{subject:string,body:string}
	 */
	private static function compose( array $message ) {
		$categories = UICF7_Settings::categories();
		$urgencies  = UICF7_Settings::urgencies();

		$category = isset( $categories[ $message['category'] ] ) ? $categories[ $message['category'] ] : $message['category'];
		$urgency  = isset( $urgencies[ $message['urgency'] ] ) ? $urgencies[ $message['urgency'] ] : $message['urgency'];

		$sender = trim( $message['sender_name'] );
		if ( '' === $sender ) {
			$sender = '' !== $message['sender_email'] ? $message['sender_email'] : __( 'Unknown sender', 'uniinbox-for-contact-form-7' );
		}

		$link = add_query_arg(
			array(
				'page' => 'uicf7-inbox',
				'view' => (int) $message['id'],
			),
			admin_url( 'admin.php' )
		);

		$subject = sprintf(
			/* translators: 1: urgency, 2: category, 3: sender name. */
			__( '%1$s priority %2$s from %3$s', 'uniinbox-for-contact-form-7' ),
			$urgency,
			$category,
			$sender
		);

		$lines = array(
			$subject,
			'',
			$message['summary'],
			'',
			/* translators: %s: form name. */
			sprintf( __( 'Form: %s', 'uniinbox-for-contact-form-7' ), $message['form_title'] ),
			/* translators: %s: URL of the message in the admin. */
			sprintf( __( 'Open in inbox: %s', 'uniinbox-for-contact-form-7' ), $link ),
		);

		return array(
			'subject' => $subject,
			'body'    => implode( "\n", $lines ),
		);
	}

	/**
	 * Deliver to all configured channels.
	 *
	 * @param array{subject:string,body:string} $text   Alert text.
	 * @param array                             $config Settings.
	 * @return array<string,true|WP_Error>
	 */
	private static function dispatch( array $text, array $config ) {
		$results = array();

		$emails = array_filter( array_map( 'trim', explode( ',', (string) $config['notify_email'] ) ), 'is_email' );
		if ( ! empty( $emails ) ) {
			$site             = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
			$subject          = str_replace( array( "\r", "\n" ), ' ', wp_strip_all_tags( '[' . $site . '] ' . $text['subject'] ) );
			$sent             = wp_mail( $emails, $subject, wp_strip_all_tags( $text['body'] ) );
			$results['email'] = $sent ? true : new WP_Error( 'uicf7_mail', __( 'The email could not be sent.', 'uniinbox-for-contact-form-7' ) );
		}

		$webhook = (string) $config['slack_webhook'];
		if ( '' !== $webhook ) {
			$results['slack'] = self::slack( $webhook, $text['body'] );
		}

		$token = (string) $config['telegram_token'];
		$chat  = (string) $config['telegram_chat_id'];
		if ( '' !== $token && '' !== $chat ) {
			$results['telegram'] = self::telegram( $token, $chat, $text['body'] );
		}

		return $results;
	}

	/**
	 * Post to a Slack incoming webhook.
	 *
	 * @param string $webhook Webhook URL (validated on save).
	 * @param string $text    Plain text.
	 * @return true|WP_Error
	 */
	private static function slack( $webhook, $text ) {
		// Slack treats &, < and > specially; escape them so message content cannot inject mentions or links.
		$text = str_replace( array( '&', '<', '>' ), array( '&amp;', '&lt;', '&gt;' ), $text );

		$response = wp_safe_remote_post(
			$webhook,
			array(
				'timeout' => 10,
				'headers' => array( 'Content-Type' => 'application/json' ),
				'body'    => wp_json_encode( array( 'text' => $text ) ),
			)
		);
		return self::check( $response, 'Slack' );
	}

	/**
	 * Send a Telegram message through the Bot API.
	 *
	 * @param string $token Bot token (validated on save).
	 * @param string $chat  Chat ID or @channel.
	 * @param string $text  Plain text.
	 * @return true|WP_Error
	 */
	private static function telegram( $token, $chat, $text ) {
		$response = wp_safe_remote_post(
			'https://api.telegram.org/bot' . $token . '/sendMessage',
			array(
				'timeout' => 10,
				'body'    => array(
					'chat_id'                  => $chat,
					'text'                     => mb_substr( $text, 0, 4000 ),
					'disable_web_page_preview' => 'true',
				),
			)
		);
		return self::check( $response, 'Telegram' );
	}

	/**
	 * Turn an HTTP response into true or a WP_Error.
	 *
	 * @param array|WP_Error $response Response.
	 * @param string         $service  Service name.
	 * @return true|WP_Error
	 */
	private static function check( $response, $service ) {
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'uicf7_http', $service . ': ' . $response->get_error_message() );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			/* translators: 1: service name, 2: HTTP status code. */
			return new WP_Error( 'uicf7_http', sprintf( __( '%1$s returned HTTP %2$d.', 'uniinbox-for-contact-form-7' ), $service, $code ) );
		}
		return true;
	}
}
