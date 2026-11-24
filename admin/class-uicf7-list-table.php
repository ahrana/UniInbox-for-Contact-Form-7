<?php
/**
 * Inbox list table.
 *
 * @package UniInbox_For_CF7
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Messages list table.
 */
class UICF7_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'uicf7_message',
				'plural'   => 'uicf7_messages',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Current filters read from the query string. Filters only change what is
	 * listed and never write data, so no nonce is needed here.
	 *
	 * @return array<string,string>
	 */
	public static function filters() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only list filters.
		return array(
			'status'   => isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '',
			'category' => isset( $_GET['category'] ) ? sanitize_key( wp_unslash( $_GET['category'] ) ) : '',
			'urgency'  => isset( $_GET['urgency'] ) ? sanitize_key( wp_unslash( $_GET['urgency'] ) ) : '',
			'search'   => isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '',
			'orderby'  => isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '',
			'order'    => isset( $_GET['order'] ) ? sanitize_key( wp_unslash( $_GET['order'] ) ) : '',
		);
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Base URL of the inbox page.
	 *
	 * @return string
	 */
	public static function base_url() {
		return admin_url( 'admin.php?page=uicf7-inbox' );
	}

	/**
	 * URL of one message.
	 *
	 * @param int $id Message ID.
	 * @return string
	 */
	public static function message_url( $id ) {
		return add_query_arg( 'view', (int) $id, self::base_url() );
	}

	/**
	 * Columns.
	 *
	 * @return array<string,string>
	 */
	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'star'       => '<span class="screen-reader-text">' . esc_html__( 'Starred', 'uniinbox-for-contact-form-7' ) . '</span>',
			'sender'     => __( 'From', 'uniinbox-for-contact-form-7' ),
			'message'    => __( 'Message', 'uniinbox-for-contact-form-7' ),
			'category'   => __( 'Category', 'uniinbox-for-contact-form-7' ),
			'urgency'    => __( 'Priority', 'uniinbox-for-contact-form-7' ),
			'created_at' => __( 'Received', 'uniinbox-for-contact-form-7' ),
		);
	}

	/**
	 * Primary column (holds the row actions).
	 *
	 * @return string
	 */
	protected function get_default_primary_column_name() {
		return 'sender';
	}

	/**
	 * Sortable columns.
	 *
	 * @return array<string,array>
	 */
	protected function get_sortable_columns() {
		return array(
			'sender'     => array( 'sender_name', false ),
			'category'   => array( 'category', false ),
			'urgency'    => array( 'urgency', false ),
			'created_at' => array( 'created_at', true ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array<string,string>
	 */
	protected function get_bulk_actions() {
		$actions = array();
		if ( UICF7_Settings::ai_ready() ) {
			$actions['analyze'] = __( 'Analyze with AI', 'uniinbox-for-contact-form-7' );
		}
		return $actions + array(
			'mark_read'   => __( 'Mark as read', 'uniinbox-for-contact-form-7' ),
			'mark_unread' => __( 'Mark as unread', 'uniinbox-for-contact-form-7' ),
			'star'        => __( 'Star', 'uniinbox-for-contact-form-7' ),
			'unstar'      => __( 'Remove star', 'uniinbox-for-contact-form-7' ),
			'mark_spam'   => __( 'Mark as spam', 'uniinbox-for-contact-form-7' ),
			'not_spam'    => __( 'Not spam', 'uniinbox-for-contact-form-7' ),
			'delete'      => __( 'Delete permanently', 'uniinbox-for-contact-form-7' ),
		);
	}

	/**
	 * Status views.
	 *
	 * @return array<string,string>
	 */
	protected function get_views() {
		$counts  = UICF7_Repository::status_counts();
		$current = self::filters()['status'];

		$items = array(
			''        => array( __( 'All', 'uniinbox-for-contact-form-7' ), $counts['unread'] + $counts['read'] + $counts['replied'] ),
			'unread'  => array( __( 'Unread', 'uniinbox-for-contact-form-7' ), $counts['unread'] ),
			'starred' => array( __( 'Starred', 'uniinbox-for-contact-form-7' ), $counts['starred'] ),
			'replied' => array( __( 'Replied', 'uniinbox-for-contact-form-7' ), $counts['replied'] ),
			'spam'    => array( __( 'Spam', 'uniinbox-for-contact-form-7' ), $counts['spam'] ),
		);

		$views = array();
		foreach ( $items as $status => $item ) {
			$url              = '' === $status ? self::base_url() : add_query_arg( 'status', $status, self::base_url() );
			$views[ $status ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">%4$s</span></a>',
				esc_url( $url ),
				$current === $status ? ' class="current" aria-current="page"' : '',
				esc_html( $item[0] ),
				esc_html( number_format_i18n( $item[1] ) )
			);
		}
		return $views;
	}

	/**
	 * Category and priority filters.
	 *
	 * @param string $which top|bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$filters = self::filters();

		echo '<div class="alignleft actions uicf7-filters">';

		echo '<label class="screen-reader-text" for="uicf7-filter-category">' . esc_html__( 'Filter by category', 'uniinbox-for-contact-form-7' ) . '</label>';
		echo '<select name="category" id="uicf7-filter-category">';
		echo '<option value="">' . esc_html__( 'All categories', 'uniinbox-for-contact-form-7' ) . '</option>';
		foreach ( UICF7_Settings::categories() as $slug => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $slug ), selected( $filters['category'], $slug, false ), esc_html( $label ) );
		}
		echo '</select>';

		echo '<label class="screen-reader-text" for="uicf7-filter-urgency">' . esc_html__( 'Filter by priority', 'uniinbox-for-contact-form-7' ) . '</label>';
		echo '<select name="urgency" id="uicf7-filter-urgency">';
		echo '<option value="">' . esc_html__( 'All priorities', 'uniinbox-for-contact-form-7' ) . '</option>';
		foreach ( UICF7_Settings::urgencies() as $slug => $label ) {
			printf( '<option value="%1$s"%2$s>%3$s</option>', esc_attr( $slug ), selected( $filters['urgency'], $slug, false ), esc_html( $label ) );
		}
		echo '</select>';

		submit_button( __( 'Filter', 'uniinbox-for-contact-form-7' ), '', 'filter_action', false );
		echo '</div>';
	}

	/**
	 * Load rows.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'sender' );

		$filters  = self::filters();
		$per_page = 20;
		$args     = array(
			'status'   => $filters['status'],
			'category' => $filters['category'],
			'urgency'  => $filters['urgency'],
			'search'   => $filters['search'],
			'orderby'  => $filters['orderby'],
			'order'    => $filters['order'],
			'per_page' => $per_page,
			'page'     => $this->get_pagenum(),
		);

		$this->items = UICF7_Repository::query( $args );
		$total       = UICF7_Repository::count( $args );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}

	/**
	 * Empty state.
	 *
	 * @return void
	 */
	public function no_items() {
		echo '<div class="uicf7-empty">' . wp_kses( UICF7_UI::icon( 'email-alt' ), UICF7_UI::allowed_html() ) . '<strong>' . esc_html__( 'No messages here yet', 'uniinbox-for-contact-form-7' ) . '</strong><span>' . esc_html__( 'New Contact Form 7 submissions will appear here automatically.', 'uniinbox-for-contact-form-7' ) . '</span></div>';
	}

	/**
	 * Row with an ID for live updates.
	 *
	 * @param array $item Row.
	 * @return void
	 */
	public function single_row( $item ) {
		printf(
			'<tr class="uicf7-row%1$s" data-id="%2$d">',
			esc_attr( 'unread' === $item['status'] ? ' is-unread' : '' ),
			(int) $item['id']
		);
		$this->single_row_columns( $item );
		echo '</tr>';
	}

	/**
	 * Checkbox column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_cb( $item ) {
		return sprintf(
			'<label class="screen-reader-text" for="uicf7-cb-%1$d">%2$s</label><input type="checkbox" id="uicf7-cb-%1$d" name="message[]" value="%1$d" />',
			(int) $item['id'],
			esc_html__( 'Select message', 'uniinbox-for-contact-form-7' )
		);
	}

	/**
	 * Star column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_star( $item ) {
		$on = ! empty( $item['starred'] );
		return sprintf(
			'<button type="button" class="uicf7-star%1$s" data-id="%2$d" aria-pressed="%3$s" aria-label="%4$s">%5$s</button>',
			$on ? ' is-on' : '',
			(int) $item['id'],
			$on ? 'true' : 'false',
			esc_attr__( 'Star message', 'uniinbox-for-contact-form-7' ),
			UICF7_UI::icon( $on ? 'star-filled' : 'star-empty' )
		);
	}

	/**
	 * Sender column with row actions.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_sender( $item ) {
		$id   = (int) $item['id'];
		$name = '' !== $item['sender_name'] ? $item['sender_name'] : ( '' !== $item['sender_email'] ? $item['sender_email'] : __( '(no name)', 'uniinbox-for-contact-form-7' ) );
		$view = self::message_url( $id );

		$out  = '<div class="uicf7-sender">' . UICF7_UI::avatar( $item['sender_name'], $item['sender_email'], 'sm' );
		$out .= '<div class="uicf7-sender__text"><a class="uicf7-sender__name row-title" href="' . esc_url( $view ) . '">' . esc_html( $name ) . '</a>';
		if ( '' !== $item['sender_email'] && $item['sender_email'] !== $name ) {
			$out .= '<span class="uicf7-sender__email">' . esc_html( $item['sender_email'] ) . '</span>';
		}
		$out .= '</div></div>';

		$actions = array(
			'view' => sprintf( '<a href="%s">%s</a>', esc_url( $view ), esc_html__( 'Open', 'uniinbox-for-contact-form-7' ) ),
		);
		if ( 'spam' === $item['status'] ) {
			$actions['unspam'] = self::row_link( $id, 'not_spam', __( 'Not spam', 'uniinbox-for-contact-form-7' ) );
		} else {
			$actions['toggle'] = 'unread' === $item['status']
				? self::row_link( $id, 'read', __( 'Mark read', 'uniinbox-for-contact-form-7' ) )
				: self::row_link( $id, 'unread', __( 'Mark unread', 'uniinbox-for-contact-form-7' ) );
			$actions['spam']   = self::row_link( $id, 'spam', __( 'Spam', 'uniinbox-for-contact-form-7' ) );
		}
		$actions['delete'] = self::row_link( $id, 'delete', __( 'Delete', 'uniinbox-for-contact-form-7' ), true );

		return $out . $this->row_actions( $actions );
	}

	/**
	 * Nonce-protected row action link.
	 *
	 * @param int    $id      Message ID.
	 * @param string $task    Action keyword.
	 * @param string $label   Link text.
	 * @param bool   $confirm Ask for confirmation first.
	 * @return string
	 */
	public static function row_link( $id, $task, $label, $confirm = false ) {
		$url = wp_nonce_url(
			add_query_arg(
				array(
					'action'  => 'uicf7_message_action',
					'do'      => $task,
					'message' => (int) $id,
				),
				admin_url( 'admin-post.php' )
			),
			'uicf7_message_' . (int) $id
		);
		return sprintf(
			'<a href="%1$s"%2$s>%3$s</a>',
			esc_url( $url ),
			$confirm ? ' class="uicf7-confirm submitdelete" data-confirm="' . esc_attr__( 'Delete this message permanently?', 'uniinbox-for-contact-form-7' ) . '"' : '',
			esc_html( $label )
		);
	}

	/**
	 * Message cell (also used for live updates).
	 *
	 * @param array $item Row.
	 * @return string
	 */
	public static function render_message_cell( array $item ) {
		$subject = trim( (string) $item['subject'] );
		$summary = '' !== trim( (string) $item['summary'] ) ? $item['summary'] : wp_trim_words( UICF7_UI::message_text( $item ), 22, '…' );

		$out = '<a class="uicf7-snippet" href="' . esc_url( self::message_url( (int) $item['id'] ) ) . '">';
		if ( '' !== $subject ) {
			$out .= '<span class="uicf7-snippet__subject">' . esc_html( $subject ) . '</span>';
		}
		$out .= '<span class="uicf7-snippet__text">' . esc_html( $summary ) . '</span></a>';
		$out .= UICF7_UI::ai_chip( $item );
		return $out;
	}

	/**
	 * Message column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_message( $item ) {
		return self::render_message_cell( $item );
	}

	/**
	 * Category column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_category( $item ) {
		return UICF7_UI::category_badge( $item['category'] );
	}

	/**
	 * Priority column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_urgency( $item ) {
		return UICF7_UI::urgency_badge( $item['urgency'] );
	}

	/**
	 * Received column.
	 *
	 * @param array $item Row.
	 * @return string
	 */
	protected function column_created_at( $item ) {
		return '<span class="uicf7-muted">' . UICF7_UI::time_ago( $item['created_at'] ) . '</span>';
	}

	/**
	 * Fallback column renderer.
	 *
	 * @param array  $item        Row.
	 * @param string $column_name Column.
	 * @return string
	 */
	protected function column_default( $item, $column_name ) {
		return isset( $item[ $column_name ] ) ? esc_html( (string) $item[ $column_name ] ) : '';
	}
}
