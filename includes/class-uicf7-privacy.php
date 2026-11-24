<?php
/**
 * Privacy: policy text, personal data exporter and eraser.
 *
 * @package UniInbox_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Privacy integration.
 */
class UICF7_Privacy {

	const PER_PAGE = 50;

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	/**
	 * Suggested privacy policy text.
	 *
	 * @return void
	 */
	public static function policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$text  = '<h2>' . esc_html__( 'Contact form messages (UniInbox)', 'uniinbox-for-contact-form-7' ) . '</h2>';
		$text .= '<p>' . esc_html__( 'When you submit a Contact Form 7 form, a copy of your message (the fields you filled in, the form name and the time) is stored in this website\'s database so the site team can read and answer it. Replies the team sends you, and internal notes about your message, are stored with it.', 'uniinbox-for-contact-form-7' ) . '</p>';
		$text .= '<p>' . esc_html__( 'If the site owner enabled AI analysis, the text of your message may be sent to an AI provider they chose (for example Google, Groq, OpenRouter, Cerebras, Mistral, OpenAI or Anthropic) to classify it, summarize it and draft a reply. Email addresses and phone numbers can be removed from that text before it is sent. If alerts are enabled, a short summary may be sent to the team by email, Slack or Telegram.', 'uniinbox-for-contact-form-7' ) . '</p>';
		$text .= '<p>' . esc_html__( 'Messages are kept until the site team deletes them or the retention period set by the site owner ends. You can ask for your messages to be exported or erased.', 'uniinbox-for-contact-form-7' ) . '</p>';

		wp_add_privacy_policy_content( 'UniInbox for Contact Form 7', wp_kses_post( $text ) );
	}

	/**
	 * Register the exporter.
	 *
	 * @param array $exporters Existing exporters.
	 * @return array
	 */
	public static function register_exporter( $exporters ) {
		$exporters['uniinbox-for-contact-form-7'] = array(
			'exporter_friendly_name' => __( 'UniInbox messages', 'uniinbox-for-contact-form-7' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $exporters;
	}

	/**
	 * Register the eraser.
	 *
	 * @param array $erasers Existing erasers.
	 * @return array
	 */
	public static function register_eraser( $erasers ) {
		$erasers['uniinbox-for-contact-form-7'] = array(
			'eraser_friendly_name' => __( 'UniInbox messages', 'uniinbox-for-contact-form-7' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $erasers;
	}

	/**
	 * Export messages sent from an email address, with the replies sent back.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number.
	 * @return array
	 */
	public static function export( $email, $page = 1 ) {
		$page   = max( 1, (int) $page );
		$rows   = UICF7_Repository::by_email( sanitize_email( $email ), self::PER_PAGE, ( $page - 1 ) * self::PER_PAGE );
		$export = array();

		foreach ( $rows as $row ) {
			$data = array(
				array(
					'name'  => __( 'Form', 'uniinbox-for-contact-form-7' ),
					'value' => $row['form_title'],
				),
				array(
					'name'  => __( 'Received (UTC)', 'uniinbox-for-contact-form-7' ),
					'value' => $row['created_at'],
				),
				array(
					'name'  => __( 'Name', 'uniinbox-for-contact-form-7' ),
					'value' => $row['sender_name'],
				),
				array(
					'name'  => __( 'Email', 'uniinbox-for-contact-form-7' ),
					'value' => $row['sender_email'],
				),
				array(
					'name'  => __( 'Message', 'uniinbox-for-contact-form-7' ),
					'value' => $row['body'],
				),
			);

			foreach ( UICF7_Repository::replies_for( (int) $row['id'] ) as $reply ) {
				if ( 'reply' !== $reply['type'] ) {
					continue;
				}
				$data[] = array(
					/* translators: %s: date and time (UTC). */
					'name'  => sprintf( __( 'Reply sent %s (UTC)', 'uniinbox-for-contact-form-7' ), $reply['created_at'] ),
					'value' => UICF7_Mailer::html_to_text( $reply['body'] ),
				);
			}

			$export[] = array(
				'group_id'    => 'uniinbox-messages',
				'group_label' => __( 'Contact form messages', 'uniinbox-for-contact-form-7' ),
				'item_id'     => 'uniinbox-message-' . (int) $row['id'],
				'data'        => $data,
			);
		}

		return array(
			'data' => $export,
			'done' => count( $rows ) < self::PER_PAGE,
		);
	}

	/**
	 * Erase messages (and their replies and notes) from an email address.
	 *
	 * @param string $email Email address.
	 * @param int    $page  Page number (unused, everything is erased in one pass).
	 * @return array
	 */
	public static function erase( $email, $page = 1 ) {
		unset( $page );
		$removed = UICF7_Repository::delete_by_email( sanitize_email( $email ) );

		return array(
			'items_removed'  => $removed > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
