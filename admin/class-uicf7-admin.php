<?php
/**
 * Admin screens and form handlers.
 *
 * Every state-changing request checks the user capability and a nonce.
 *
 * @package UniInbox_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin controller.
 */
class UICF7_Admin {

	const CAP = 'manage_options';

	/**
	 * Screen hook suffixes of this plugin's pages.
	 *
	 * @var string[]
	 */
	private static $screens = array();

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_post_uicf7_message_action', array( __CLASS__, 'handle_message_action' ) );
		add_action( 'admin_post_uicf7_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_uicf7_analyze_unprocessed', array( __CLASS__, 'handle_analyze_unprocessed' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( UICF7_FILE ), array( __CLASS__, 'action_links' ) );
		add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
		UICF7_Ajax::init();
	}

	/**
	 * Menu with an unread counter.
	 *
	 * @return void
	 */
	public static function register_menu() {
		$unread = current_user_can( self::CAP ) ? UICF7_Repository::unread_count() : 0;
		$bubble = $unread > 0 ? ' <span class="awaiting-mod count-' . (int) $unread . '"><span class="pending-count">' . esc_html( number_format_i18n( $unread ) ) . '</span></span>' : '';

		$inbox = add_menu_page(
			__( 'UniInbox', 'uniinbox-for-contact-form-7' ),
			esc_html__( 'UniInbox', 'uniinbox-for-contact-form-7' ) . $bubble,
			self::CAP,
			'uicf7-inbox',
			array( __CLASS__, 'render_inbox' ),
			'dashicons-email-alt',
			26
		);

		add_submenu_page( 'uicf7-inbox', __( 'Inbox', 'uniinbox-for-contact-form-7' ), __( 'Inbox', 'uniinbox-for-contact-form-7' ), self::CAP, 'uicf7-inbox', array( __CLASS__, 'render_inbox' ) );

		$settings = add_submenu_page( 'uicf7-inbox', __( 'UniInbox Settings', 'uniinbox-for-contact-form-7' ), __( 'Settings', 'uniinbox-for-contact-form-7' ), self::CAP, 'uicf7-settings', array( __CLASS__, 'render_settings' ) );

		self::$screens = array_filter( array( $inbox, $settings ) );

		if ( $inbox ) {
			add_action( 'load-' . $inbox, array( __CLASS__, 'process_bulk' ) );
		}
	}

	/**
	 * Register the settings option with its sanitizer.
	 *
	 * @return void
	 */
	public static function register_settings() {
		register_setting(
			'uicf7_settings_group',
			UICF7_Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( 'UICF7_Settings', 'sanitize' ),
				'default'           => UICF7_Settings::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Plugins-screen shortcuts.
	 *
	 * @param array $links Existing links.
	 * @return array
	 */
	public static function action_links( $links ) {
		return array_merge(
			array(
				'<a href="' . esc_url( admin_url( 'admin.php?page=uicf7-inbox' ) ) . '">' . esc_html__( 'Inbox', 'uniinbox-for-contact-form-7' ) . '</a>',
				'<a href="' . esc_url( admin_url( 'admin.php?page=uicf7-settings' ) ) . '">' . esc_html__( 'Settings', 'uniinbox-for-contact-form-7' ) . '</a>',
			),
			$links
		);
	}

	/**
	 * Body class for this plugin's screens (scopes the styles).
	 *
	 * @param string $classes Classes.
	 * @return string
	 */
	public static function body_class( $classes ) {
		return self::on_our_screen() ? $classes . ' uicf7-screen' : $classes;
	}

	/**
	 * Whether the current screen belongs to this plugin.
	 *
	 * @return bool
	 */
	private static function on_our_screen() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		return $screen && in_array( $screen->id, self::$screens, true );
	}

	/**
	 * Version string for an asset (changes whenever the file changes).
	 *
	 * @param string $rel Path relative to the plugin folder.
	 * @return string
	 */
	private static function asset_version( $rel ) {
		$file = UICF7_PATH . $rel;
		return file_exists( $file ) ? UICF7_VERSION . '.' . filemtime( $file ) : UICF7_VERSION;
	}

	/**
	 * Load CSS and JS on this plugin's screens only.
	 *
	 * @param string $hook Current screen hook suffix.
	 * @return void
	 */
	public static function enqueue( $hook ) {
		if ( ! in_array( $hook, self::$screens, true ) ) {
			return;
		}

		wp_enqueue_style( 'uicf7-admin', UICF7_URL . 'assets/css/admin.css', array( 'dashicons' ), self::asset_version( 'assets/css/admin.css' ) );
		wp_enqueue_script( 'uicf7-common', UICF7_URL . 'assets/js/common.js', array(), self::asset_version( 'assets/js/common.js' ), true );
		wp_localize_script(
			'uicf7-common',
			'uicf7Admin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( UICF7_Ajax::NONCE ),
				'i18n'    => array(
					'error'         => __( 'Something went wrong. Please try again.', 'uniinbox-for-contact-form-7' ),
					'confirmDelete' => __( 'Delete this message permanently?', 'uniinbox-for-contact-form-7' ),
					'confirmBulk'   => __( 'Delete the selected messages permanently?', 'uniinbox-for-contact-form-7' ),
				),
			)
		);

		if ( false !== strpos( $hook, 'uicf7-settings' ) ) {
			wp_enqueue_script( 'uicf7-settings', UICF7_URL . 'assets/js/settings.js', array( 'uicf7-common' ), self::asset_version( 'assets/js/settings.js' ), true );
			wp_localize_script(
				'uicf7-settings',
				'uicf7Settings',
				array(
					'i18n' => array(
						'testing'  => __( 'Testing…', 'uniinbox-for-contact-form-7' ),
						'unsaved'  => __( 'You have unsaved changes.', 'uniinbox-for-contact-form-7' ),
						'maxItems' => __( 'You have reached the maximum number of items.', 'uniinbox-for-contact-form-7' ),
						'removeIt' => __( 'Remove this item?', 'uniinbox-for-contact-form-7' ),
					),
				)
			);
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which message is open.
		$view    = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0;
		$message = $view ? UICF7_Repository::get( $view ) : null;

		if ( $message ) {
			wp_enqueue_media();
			wp_enqueue_script( 'uicf7-message', UICF7_URL . 'assets/js/message.js', array( 'uicf7-common' ), self::asset_version( 'assets/js/message.js' ), true );
			wp_localize_script(
				'uicf7-message',
				'uicf7Message',
				array(
					'id'             => (int) $message['id'],
					'templates'      => UICF7_Mailer::templates_for( $message ),
					'maxAttachments' => UICF7_Mailer::MAX_ATTACHMENTS,
					'i18n'           => array(
						'body'        => __( 'Reply message', 'uniinbox-for-contact-form-7' ),
						'writing'     => __( 'AI is writing…', 'uniinbox-for-contact-form-7' ),
						'attachTitle' => __( 'Attach files', 'uniinbox-for-contact-form-7' ),
						'attachBtn'   => __( 'Attach', 'uniinbox-for-contact-form-7' ),
						'maxFiles'    => __( 'You can attach up to 5 files.', 'uniinbox-for-contact-form-7' ),
						'remove'      => __( 'Remove', 'uniinbox-for-contact-form-7' ),
						'badLink'     => __( 'Enter a web address starting with https:// or an email address.', 'uniinbox-for-contact-form-7' ),
						'empty'       => __( 'Write a reply first.', 'uniinbox-for-contact-form-7' ),
						'placeholder' => __( 'Write your reply…', 'uniinbox-for-contact-form-7' ),
						'sendConfirm' => __( 'Send this reply by email now?', 'uniinbox-for-contact-form-7' ),
						'leave'       => __( 'Your reply has not been sent. Leave anyway?', 'uniinbox-for-contact-form-7' ),
					),
				)
			);
			return;
		}

		wp_enqueue_script( 'uicf7-inbox', UICF7_URL . 'assets/js/inbox.js', array( 'uicf7-common' ), self::asset_version( 'assets/js/inbox.js' ), true );
		wp_localize_script(
			'uicf7-inbox',
			'uicf7Inbox',
			array(
				'aiReady' => UICF7_Settings::ai_ready(),
				'i18n'    => array(
					/* translators: %d: number of messages. */
					'analyzing' => __( 'Analyzing new messages with AI… %d left', 'uniinbox-for-contact-form-7' ),
					/* translators: %d: number of messages. */
					'waiting'   => __( '%d messages are waiting for the AI provider limit to reset. They will be analyzed automatically.', 'uniinbox-for-contact-form-7' ),
					'done'      => __( 'All messages analyzed.', 'uniinbox-for-contact-form-7' ),
				),
			)
		);
	}

	/**
	 * Store a one-time notice for the current user.
	 *
	 * @param string $type success|error|warning|info.
	 * @param string $text Plain text message.
	 * @return void
	 */
	public static function flash( $type, $text ) {
		set_transient(
			'uicf7_flash_' . get_current_user_id(),
			array(
				'type' => $type,
				'text' => $text,
			),
			60
		);
	}

	/**
	 * Flash notices and the missing-dependency warning.
	 *
	 * @return void
	 */
	public static function notices() {
		if ( ! self::on_our_screen() ) {
			return;
		}

		$key   = 'uicf7_flash_' . get_current_user_id();
		$flash = get_transient( $key );
		if ( is_array( $flash ) && isset( $flash['type'], $flash['text'] ) ) {
			delete_transient( $key );
			$type = in_array( $flash['type'], array( 'success', 'error', 'warning', 'info' ), true ) ? $flash['type'] : 'info';
			printf( '<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>', esc_attr( $type ), esc_html( $flash['text'] ) );
		}

		if ( ! defined( 'WPCF7_VERSION' ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Contact Form 7 is not active. UniInbox cannot receive new messages until it is installed and activated.', 'uniinbox-for-contact-form-7' ) . '</p></div>';
		}
	}

	/**
	 * Read a sanitized scalar from the request. Callers verify the nonce first.
	 *
	 * @param string $key Request key.
	 * @return string
	 */
	private static function req( $key ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.NonceVerification.Missing -- every caller runs check_admin_referer() first.
		return isset( $_REQUEST[ $key ] ) && ! is_array( $_REQUEST[ $key ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) : '';
	}

	/**
	 * Stop unless the current user may manage the inbox.
	 *
	 * @return void
	 */
	private static function require_cap() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'uniinbox-for-contact-form-7' ), '', array( 'response' => 403 ) );
		}
	}

	/**
	 * Bulk actions from the list table (runs on load-{inbox screen}).
	 *
	 * @return void
	 */
	public static function process_bulk() {
		$allowed = array( 'analyze', 'mark_read', 'mark_unread', 'star', 'unstar', 'mark_spam', 'not_spam', 'delete' );

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- the nonce is verified below once a bulk action is detected.
		$action = '';
		foreach ( array( 'action', 'action2' ) as $field ) {
			if ( isset( $_REQUEST[ $field ] ) && ! is_array( $_REQUEST[ $field ] ) ) {
				$value = sanitize_key( wp_unslash( $_REQUEST[ $field ] ) );
				if ( in_array( $value, $allowed, true ) ) {
					$action = $value;
					break;
				}
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( '' === $action ) {
			return;
		}

		self::require_cap();
		check_admin_referer( 'bulk-uicf7_messages' );

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- verified above.
		$raw = isset( $_REQUEST['message'] ) ? (array) wp_unslash( $_REQUEST['message'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cast to integers next.
		$ids = array_values( array_filter( array_map( 'absint', $raw ) ) );

		if ( empty( $ids ) ) {
			self::flash( 'warning', __( 'No messages were selected.', 'uniinbox-for-contact-form-7' ) );
		} else {
			switch ( $action ) {
				case 'analyze':
					$count = UICF7_Repository::mark_pending( $ids );
					/* translators: %d: number of messages. */
					self::flash( 'success', sprintf( _n( '%d message queued for AI analysis.', '%d messages queued for AI analysis.', $count, 'uniinbox-for-contact-form-7' ), $count ) );
					break;
				case 'star':
				case 'unstar':
					UICF7_Repository::set_starred( $ids, 'star' === $action );
					break;
				case 'mark_read':
					UICF7_Repository::set_status( $ids, 'read' );
					break;
				case 'mark_unread':
				case 'not_spam':
					UICF7_Repository::set_status( $ids, 'unread' );
					break;
				case 'mark_spam':
					UICF7_Repository::set_status( $ids, 'spam' );
					break;
				default:
					$count = UICF7_Repository::delete( $ids );
					/* translators: %d: number of messages. */
					self::flash( 'success', sprintf( _n( '%d message deleted.', '%d messages deleted.', $count, 'uniinbox-for-contact-form-7' ), $count ) );
			}
		}

		$keep = array();
		foreach ( array( 'status', 'category', 'urgency' ) as $field ) {
			$value = sanitize_key( self::req( $field ) );
			if ( '' !== $value ) {
				$keep[ $field ] = $value;
			}
		}
		if ( '' !== self::req( 's' ) ) {
			$keep['s'] = self::req( 's' );
		}

		wp_safe_redirect( add_query_arg( $keep, UICF7_List_Table::base_url() ) );
		exit;
	}

	/**
	 * Queue every unprocessed message (admin-post).
	 *
	 * @return void
	 */
	public static function handle_analyze_unprocessed() {
		self::require_cap();
		check_admin_referer( 'uicf7_analyze_unprocessed' );

		if ( ! UICF7_Settings::ai_ready() ) {
			self::flash( 'error', __( 'Turn on AI and add an API key in Settings first.', 'uniinbox-for-contact-form-7' ) );
		} else {
			$count = UICF7_Repository::mark_pending( UICF7_Repository::unprocessed_ids( 500 ) );
			/* translators: %d: number of messages. */
			self::flash( 'success', sprintf( _n( '%d message queued for AI analysis.', '%d messages queued for AI analysis.', $count, 'uniinbox-for-contact-form-7' ), $count ) );
		}
		wp_safe_redirect( UICF7_List_Table::base_url() );
		exit;
	}

	/**
	 * Single-message actions (admin-post).
	 *
	 * @return void
	 */
	public static function handle_message_action() {
		self::require_cap();

		$id = absint( self::req( 'message' ) );
		check_admin_referer( 'uicf7_message_' . $id );

		$message = $id ? UICF7_Repository::get( $id ) : null;
		if ( ! $message ) {
			self::flash( 'error', __( 'Message not found.', 'uniinbox-for-contact-form-7' ) );
			wp_safe_redirect( UICF7_List_Table::base_url() );
			exit;
		}

		$task     = sanitize_key( self::req( 'do' ) );
		$back_to  = UICF7_List_Table::message_url( $id );
		$redirect = $back_to;

		switch ( $task ) {
			case 'read':
			case 'unread':
				UICF7_Repository::set_status( array( $id ), $task );
				$redirect = UICF7_List_Table::base_url();
				break;

			case 'spam':
				UICF7_Repository::set_status( array( $id ), 'spam' );
				self::flash( 'success', __( 'Moved to spam.', 'uniinbox-for-contact-form-7' ) );
				$redirect = UICF7_List_Table::base_url();
				break;

			case 'not_spam':
				UICF7_Repository::set_status( array( $id ), 'unread' );
				self::flash( 'success', __( 'Moved back to the inbox.', 'uniinbox-for-contact-form-7' ) );
				break;

			case 'delete':
				UICF7_Repository::delete( array( $id ) );
				self::flash( 'success', __( 'Message deleted.', 'uniinbox-for-contact-form-7' ) );
				$redirect = UICF7_List_Table::base_url();
				break;

			case 'save_draft':
				UICF7_Repository::update( $id, array( 'draft_reply' => self::posted_reply() ) );
				self::flash( 'success', __( 'Draft saved.', 'uniinbox-for-contact-form-7' ) );
				break;

			case 'send_reply':
				$redirect = self::send_reply( $message );
				break;

			case 'add_note':
				// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by check_admin_referer() above.
				$note   = isset( $_POST['note_text'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note_text'] ) ) : '';
				$result = UICF7_Mailer::add_note( $message, $note );
				if ( is_wp_error( $result ) ) {
					self::flash( 'error', $result->get_error_message() );
				} else {
					self::flash( 'success', __( 'Note added.', 'uniinbox-for-contact-form-7' ) );
				}
				$redirect = $back_to . '#uicf7-thread-end';
				break;

			case 'reanalyze':
				$result = UICF7_Cron::analyze_now( $id, false );
				if ( is_wp_error( $result ) ) {
					self::flash( 'error', $result->get_error_message() );
				} else {
					self::flash( 'success', __( 'Message analyzed with AI.', 'uniinbox-for-contact-form-7' ) );
				}
				break;

			default:
				self::flash( 'error', __( 'Unknown action.', 'uniinbox-for-contact-form-7' ) );
		}

		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * The reply HTML posted from the composer, cleaned.
	 *
	 * @return string
	 */
	private static function posted_reply() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified by check_admin_referer() in the caller.
		$html = isset( $_POST['reply_html'] ) ? wp_kses( wp_unslash( $_POST['reply_html'] ), UICF7_Mailer::allowed_html() ) : '';
		return UICF7_Mailer::clean_html( mb_substr( $html, 0, 50000 ) );
	}

	/**
	 * Send the reply composed on the message screen.
	 *
	 * @param array $message Message row.
	 * @return string Redirect URL.
	 */
	private static function send_reply( array $message ) {
		$id   = (int) $message['id'];
		$html = self::posted_reply();
		$ids  = array_filter( array_map( 'absint', explode( ',', self::req( 'attachment_ids' ) ) ) );

		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified by check_admin_referer() in the caller.
		$result = UICF7_Mailer::send(
			$message,
			array(
				'subject'        => self::req( 'reply_subject' ),
				'body'           => $html,
				'cc'             => self::req( 'reply_cc' ),
				'bcc'            => self::req( 'reply_bcc' ),
				'attachment_ids' => $ids,
				'signature'      => ! empty( $_POST['use_signature'] ),
				'quote'          => ! empty( $_POST['quote_original'] ),
			)
		);
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( is_wp_error( $result ) ) {
			// Keep what was typed so nothing is lost.
			UICF7_Repository::update( $id, array( 'draft_reply' => $html ) );
			self::flash( 'error', $result->get_error_message() );
			return UICF7_List_Table::message_url( $id ) . '#uicf7-composer';
		}

		self::flash( 'success', __( 'Reply sent.', 'uniinbox-for-contact-form-7' ) );
		return UICF7_List_Table::message_url( $id ) . '#uicf7-thread-end';
	}

	/**
	 * CSV export (admin-post).
	 *
	 * @return void
	 */
	public static function handle_export() {
		self::require_cap();
		check_admin_referer( 'uicf7_export' );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="uniinbox-export-' . gmdate( 'Ymd' ) . '.csv"' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming to the output buffer, not the filesystem.
		$out = fopen( 'php://output', 'w' );
		if ( ! $out ) {
			exit;
		}

		$categories = UICF7_Settings::categories();
		fputcsv( $out, array( 'ID', 'Received (UTC)', 'Form', 'Name', 'Email', 'Subject', 'Category', 'Priority', 'Status', 'Starred', 'Summary', 'Message' ) );

		foreach ( UICF7_Repository::for_export() as $row ) {
			fputcsv(
				$out,
				array_map(
					array( __CLASS__, 'csv_cell' ),
					array(
						$row['id'],
						$row['created_at'],
						$row['form_title'],
						$row['sender_name'],
						$row['sender_email'],
						$row['subject'],
						isset( $categories[ $row['category'] ] ) ? $categories[ $row['category'] ] : $row['category'],
						$row['urgency'],
						$row['status'],
						$row['starred'] ? 'yes' : '',
						$row['summary'],
						$row['body'],
					)
				)
			);
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- see above.
		fclose( $out );
		exit;
	}

	/**
	 * Neutralize spreadsheet formula injection in a CSV cell.
	 *
	 * @param mixed $value Cell value.
	 * @return string
	 */
	public static function csv_cell( $value ) {
		$value = (string) $value;
		if ( '' !== $value && in_array( $value[0], array( '=', '+', '-', '@', "\t", "\r" ), true ) ) {
			return "'" . $value;
		}
		return $value;
	}

	/**
	 * Inbox screen: list or single message.
	 *
	 * @return void
	 */
	public static function render_inbox() {
		self::require_cap();

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only: which message to display.
		$view = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0;

		if ( $view ) {
			$message = UICF7_Repository::get( $view );
			if ( $message ) {
				if ( 'unread' === $message['status'] ) {
					UICF7_Repository::set_status( array( $view ), 'read' );
					$message['status'] = 'read';
				}
				$replies   = UICF7_Repository::replies_for( $view );
				$neighbors = UICF7_Repository::neighbors( $message );
				include UICF7_PATH . 'admin/views/message.php';
				return;
			}
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Message not found.', 'uniinbox-for-contact-form-7' ) . '</p></div>';
		}

		$table = new UICF7_List_Table();
		$table->prepare_items();
		$stats       = UICF7_Repository::dashboard_stats();
		$unprocessed = UICF7_Settings::ai_ready() ? UICF7_Repository::count_unprocessed() : 0;
		$queue       = UICF7_Repository::queue_counts();
		include UICF7_PATH . 'admin/views/inbox.php';
	}

	/**
	 * Settings screen.
	 *
	 * @return void
	 */
	public static function render_settings() {
		self::require_cap();

		$settings = UICF7_Settings::all();
		$usage    = UICF7_Cron::usage_today();
		$forms    = get_posts(
			array(
				'post_type'      => 'wpcf7_contact_form',
				'post_status'    => 'publish',
				'posts_per_page' => 100,
				'orderby'        => 'title',
				'order'          => 'ASC',
			)
		);

		include UICF7_PATH . 'admin/views/settings.php';
	}
}
