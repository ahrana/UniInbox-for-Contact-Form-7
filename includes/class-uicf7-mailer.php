<?php
/**
 * Replies and internal notes: HTML email, attachments, CC/BCC, signature.
 *
 * @package UniInbox_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Mailer.
 */
class UICF7_Mailer {

	const MAX_ATTACHMENTS = 5;
	const MAX_BYTES       = 10485760; // 10 MB.

	/**
	 * Tags the reply editor may produce.
	 *
	 * @return array
	 */
	public static function allowed_html() {
		return array(
			'p'          => array(),
			'div'        => array(),
			'br'         => array(),
			'strong'     => array(),
			'b'          => array(),
			'em'         => array(),
			'i'          => array(),
			'u'          => array(),
			'ul'         => array(),
			'ol'         => array(),
			'li'         => array(),
			'blockquote' => array(),
			'a'          => array(
				'href'  => true,
				'title' => true,
			),
		);
	}

	/**
	 * Clean editor HTML.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	public static function clean_html( $html ) {
		$html = wp_kses( (string) $html, self::allowed_html() );
		$html = preg_replace( '#(<br\s*/?>\s*)+$#i', '', trim( $html ) );
		return (string) $html;
	}

	/**
	 * Whether HTML has any visible text.
	 *
	 * @param string $html HTML.
	 * @return bool
	 */
	public static function has_text( $html ) {
		return '' !== trim( str_replace( "\xc2\xa0", ' ', html_entity_decode( wp_strip_all_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ) ) );
	}

	/**
	 * Plain text to safe paragraphs.
	 *
	 * @param string $text Plain text.
	 * @return string
	 */
	public static function text_to_html( $text ) {
		$text = trim( (string) $text );
		return '' === $text ? '' : trim( wpautop( esc_html( $text ) ) );
	}

	/**
	 * HTML to readable plain text (for the text part of emails and for AI).
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function html_to_text( $html ) {
		$text = (string) $html;
		$text = preg_replace_callback(
			'#<a\s[^>]*href=("|\')(.*?)\1[^>]*>(.*?)</a>#is',
			function ( $m ) {
				$label = trim( wp_strip_all_tags( $m[3] ) );
				return ( '' === $label || $label === $m[2] ) ? $m[2] : $label . ' (' . $m[2] . ')';
			},
			$text
		);
		$text = preg_replace( '#<br\s*/?>#i', "\n", $text );
		$text = preg_replace( '#<li[^>]*>#i', '- ', $text );
		$text = preg_replace( '#</(p|div|li|blockquote|ul|ol)>#i', "\n", $text );
		$text = html_entity_decode( wp_strip_all_tags( $text ), ENT_QUOTES, 'UTF-8' );
		$text = preg_replace( "/[ \t]+\n/", "\n", $text );
		$text = preg_replace( "/\n{3,}/", "\n\n", $text );
		return trim( (string) $text );
	}

	/**
	 * Placeholder values for templates.
	 *
	 * @param array $message Message row.
	 * @return array<string,string>
	 */
	public static function placeholders( array $message ) {
		$name  = trim( (string) $message['sender_name'] );
		$parts = preg_split( '/\s+/', $name );
		return array(
			'{name}'       => $name,
			'{first_name}' => '' !== $name ? $parts[0] : '',
			'{email}'      => (string) $message['sender_email'],
			'{subject}'    => (string) $message['subject'],
			'{form}'       => (string) $message['form_title'],
			'{site}'       => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
		);
	}

	/**
	 * Templates rendered for one message, ready for the editor.
	 *
	 * @param array $message Message row.
	 * @return array[] id, name, html.
	 */
	public static function templates_for( array $message ) {
		$values = self::placeholders( $message );
		$out    = array();
		foreach ( UICF7_Settings::templates() as $template ) {
			$text = strtr( $template['body'], $values );
			// "Hi ," when the name is unknown reads badly; tidy it up.
			$text  = preg_replace( '/^(Hi|Hello|Dear)\s+,/m', '$1,', $text );
			$out[] = array(
				'id'   => $template['id'],
				'name' => $template['name'],
				'html' => self::text_to_html( $text ),
			);
		}
		return $out;
	}

	/**
	 * Signature HTML.
	 *
	 * @return string
	 */
	public static function signature_html() {
		$signature = trim( (string) UICF7_Settings::get( 'signature', '' ) );
		if ( '' === $signature ) {
			$signature = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		}
		return nl2br( esc_html( $signature ) );
	}

	/**
	 * Parse a comma separated address list.
	 *
	 * @param string $raw Raw list.
	 * @return string[]|WP_Error
	 */
	public static function parse_emails( $raw ) {
		$raw = trim( (string) $raw );
		if ( '' === $raw ) {
			return array();
		}
		$out = array();
		foreach ( preg_split( '/[,;]/', $raw ) as $email ) {
			$email = trim( $email );
			if ( '' === $email ) {
				continue;
			}
			if ( ! is_email( $email ) ) {
				/* translators: %s: the invalid address. */
				return new WP_Error( 'uicf7_email', sprintf( __( '"%s" is not a valid email address.', 'uniinbox-for-contact-form-7' ), $email ) );
			}
			$out[] = sanitize_email( $email );
		}
		$out = array_values( array_unique( $out ) );
		if ( count( $out ) > 10 ) {
			return new WP_Error( 'uicf7_email', __( 'You can add up to 10 addresses in CC and BCC.', 'uniinbox-for-contact-form-7' ) );
		}
		return $out;
	}

	/**
	 * Check Media Library attachments and return their files.
	 *
	 * @param array $ids Attachment IDs.
	 * @return array|WP_Error Array with 'paths' and 'meta'.
	 */
	public static function resolve_attachments( array $ids ) {
		$ids = array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
		if ( count( $ids ) > self::MAX_ATTACHMENTS ) {
			/* translators: %d: maximum number of files. */
			return new WP_Error( 'uicf7_files', sprintf( __( 'You can attach up to %d files.', 'uniinbox-for-contact-form-7' ), self::MAX_ATTACHMENTS ) );
		}

		$paths = array();
		$meta  = array();
		$bytes = 0;
		foreach ( $ids as $id ) {
			if ( 'attachment' !== get_post_type( $id ) || ! current_user_can( 'edit_post', $id ) ) {
				return new WP_Error( 'uicf7_files', __( 'One of the attachments could not be found.', 'uniinbox-for-contact-form-7' ) );
			}
			$path = get_attached_file( $id );
			if ( ! $path || ! is_readable( $path ) ) {
				return new WP_Error( 'uicf7_files', __( 'One of the attachment files is missing on the server.', 'uniinbox-for-contact-form-7' ) );
			}
			$bytes  += (int) filesize( $path );
			$paths[] = $path;
			$meta[]  = array(
				'id'   => $id,
				'name' => wp_basename( $path ),
				'size' => (int) filesize( $path ),
			);
		}

		if ( $bytes > self::MAX_BYTES ) {
			/* translators: %s: size limit, e.g. "10 MB". */
			return new WP_Error( 'uicf7_files', sprintf( __( 'Attachments are too large. The total limit is %s.', 'uniinbox-for-contact-form-7' ), size_format( self::MAX_BYTES ) ) );
		}

		return array(
			'paths' => $paths,
			'meta'  => $meta,
		);
	}

	/**
	 * "On <date>, <name> wrote:" quote of the original message.
	 *
	 * @param array $message Message row.
	 * @return string
	 */
	private static function quote_html( array $message ) {
		$time = strtotime( $message['created_at'] . ' UTC' );
		$when = $time ? wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $time ) : '';
		$who  = '' !== $message['sender_name'] ? $message['sender_name'] : $message['sender_email'];

		return '<div style="margin-top:28px;padding-top:14px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:13px;">'
			/* translators: 1: date, 2: sender name. */
			. esc_html( sprintf( __( 'On %1$s, %2$s wrote:', 'uniinbox-for-contact-form-7' ), $when, $who ) )
			. '<blockquote style="margin:8px 0 0;padding:0 0 0 12px;border-left:3px solid #d1d5db;">'
			. nl2br( esc_html( $message['body'] ) )
			. '</blockquote></div>';
	}

	/**
	 * Wrap content in a simple, widely compatible email layout.
	 *
	 * @param string $content Inner HTML.
	 * @return string
	 */
	private static function wrap( $content ) {
		$content = str_replace( '<blockquote>', '<blockquote style="margin:12px 0;padding:0 0 0 12px;border-left:3px solid #d1d5db;color:#4b5563;">', $content );
		$content = str_replace( '<p>', '<p style="margin:0 0 14px;">', $content );

		return '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>'
			. '<body style="margin:0;padding:24px 16px;background:#ffffff;">'
			. '<div style="max-width:640px;font-family:-apple-system,BlinkMacSystemFont,\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;font-size:15px;line-height:1.6;color:#1f2937;">'
			. $content
			. '</div></body></html>';
	}

	/**
	 * Send a reply to the person who submitted the form.
	 *
	 * @param array $message Message row.
	 * @param array $args    subject, body (HTML), cc, bcc, attachment_ids, signature, quote.
	 * @return int|WP_Error Reply ID.
	 */
	public static function send( array $message, array $args ) {
		$to = (string) $message['sender_email'];
		if ( ! is_email( $to ) ) {
			return new WP_Error( 'uicf7_to', __( 'This message has no valid sender email address to reply to.', 'uniinbox-for-contact-form-7' ) );
		}

		$body = self::clean_html( isset( $args['body'] ) ? $args['body'] : '' );
		if ( ! self::has_text( $body ) ) {
			return new WP_Error( 'uicf7_empty', __( 'The reply is empty.', 'uniinbox-for-contact-form-7' ) );
		}

		$subject = isset( $args['subject'] ) ? sanitize_text_field( $args['subject'] ) : '';
		$subject = mb_substr( str_replace( array( "\r", "\n" ), ' ', $subject ), 0, 200 );
		if ( '' === $subject ) {
			$subject = '' !== $message['subject'] ? 'Re: ' . $message['subject'] : __( 'Re: your message', 'uniinbox-for-contact-form-7' );
		}

		$cc  = self::parse_emails( isset( $args['cc'] ) ? $args['cc'] : '' );
		$bcc = self::parse_emails( isset( $args['bcc'] ) ? $args['bcc'] : '' );
		if ( is_wp_error( $cc ) ) {
			return $cc;
		}
		if ( is_wp_error( $bcc ) ) {
			return $bcc;
		}

		$files = self::resolve_attachments( isset( $args['attachment_ids'] ) ? (array) $args['attachment_ids'] : array() );
		if ( is_wp_error( $files ) ) {
			return $files;
		}

		$content = $body;
		if ( ! empty( $args['signature'] ) ) {
			$content .= '<div style="margin-top:20px;color:#4b5563;">' . self::signature_html() . '</div>';
		}
		if ( ! empty( $args['quote'] ) ) {
			$content .= self::quote_html( $message );
		}

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );

		$reply_to = (string) UICF7_Settings::get( 'reply_to', '' );
		if ( ! is_email( $reply_to ) ) {
			$user     = wp_get_current_user();
			$reply_to = ( $user && is_email( $user->user_email ) ) ? $user->user_email : '';
		}
		if ( '' !== $reply_to ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}
		foreach ( $cc as $address ) {
			$headers[] = 'Cc: ' . $address;
		}
		foreach ( $bcc as $address ) {
			$headers[] = 'Bcc: ' . $address;
		}

		$alt_body  = self::html_to_text( $content );
		$set_alt   = function ( $phpmailer ) use ( $alt_body ) {
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHPMailer property.
			$phpmailer->AltBody = $alt_body;
		};
		$from_name = (string) UICF7_Settings::get( 'from_name', '' );
		$set_name  = function () use ( $from_name ) {
			return $from_name;
		};

		add_action( 'phpmailer_init', $set_alt );
		if ( '' !== $from_name ) {
			add_filter( 'wp_mail_from_name', $set_name, 99 );
		}

		$sent = wp_mail( $to, $subject, self::wrap( $content ), $headers, $files['paths'] );

		remove_action( 'phpmailer_init', $set_alt );
		remove_filter( 'wp_mail_from_name', $set_name, 99 );

		$reply_id = UICF7_Repository::add_reply(
			array(
				'message_id'  => (int) $message['id'],
				'type'        => 'reply',
				'user_id'     => get_current_user_id(),
				'to_email'    => $to,
				'cc'          => implode( ', ', $cc ),
				'bcc'         => implode( ', ', $bcc ),
				'subject'     => $subject,
				'body'        => $content,
				'attachments' => wp_json_encode( $files['meta'] ),
				'status'      => $sent ? 'sent' : 'failed',
			)
		);

		if ( ! $sent ) {
			return new WP_Error( 'uicf7_mail', __( 'WordPress could not send the email. Check your site email setup (an SMTP plugin usually fixes this).', 'uniinbox-for-contact-form-7' ) );
		}

		UICF7_Repository::update(
			(int) $message['id'],
			array(
				'status'      => 'replied',
				'draft_reply' => '',
				'replied_at'  => UICF7_Repository::now(),
			)
		);

		return $reply_id;
	}

	/**
	 * Add an internal note (never emailed).
	 *
	 * @param array  $message Message row.
	 * @param string $text    Plain text.
	 * @return int|WP_Error Note ID.
	 */
	public static function add_note( array $message, $text ) {
		$text = mb_substr( trim( (string) $text ), 0, 5000 );
		if ( '' === $text ) {
			return new WP_Error( 'uicf7_empty', __( 'The note is empty.', 'uniinbox-for-contact-form-7' ) );
		}
		return UICF7_Repository::add_reply(
			array(
				'message_id' => (int) $message['id'],
				'type'       => 'note',
				'user_id'    => get_current_user_id(),
				'body'       => self::text_to_html( $text ),
				'status'     => 'sent',
			)
		);
	}
}
