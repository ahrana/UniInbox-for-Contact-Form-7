<?php
/**
 * Data access layer for messages and replies.
 *
 * Every query uses $wpdb->prepare() with whitelisted column names. Table
 * names are built from $wpdb->prefix and are never user-controlled.
 *
 * @package UniInbox_For_CF7
 */

// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- custom tables, trusted table names, all values are prepared.

defined( 'ABSPATH' ) || exit;

/**
 * Repository for inbox messages and their replies/notes.
 */
class UICF7_Repository {

	const LOCK_SECONDS = 300;

	/**
	 * Messages table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'uicf7_messages';
	}

	/**
	 * Replies table name.
	 *
	 * @return string
	 */
	public static function replies_table() {
		global $wpdb;
		return $wpdb->prefix . 'uicf7_replies';
	}

	/**
	 * Message column => format. Doubles as the write whitelist.
	 *
	 * @return array<string,string>
	 */
	private static function columns() {
		return array(
			'form_id'      => '%d',
			'form_title'   => '%s',
			'sender_name'  => '%s',
			'sender_email' => '%s',
			'subject'      => '%s',
			'body'         => '%s',
			'field_data'   => '%s',
			'status'       => '%s',
			'starred'      => '%d',
			'category'     => '%s',
			'urgency'      => '%s',
			'summary'      => '%s',
			'extracted'    => '%s',
			'draft_reply'  => '%s',
			'ai_status'    => '%s',
			'ai_note'      => '%s',
			'ai_provider'  => '%s',
			'ai_attempts'  => '%d',
			'ai_next_at'   => '%s',
			'ai_locked_at' => '%s',
			'notified_at'  => '%s',
			'replied_at'   => '%s',
			'created_at'   => '%s',
		);
	}

	/**
	 * Reply column => format.
	 *
	 * @return array<string,string>
	 */
	private static function reply_columns() {
		return array(
			'message_id'  => '%d',
			'type'        => '%s',
			'user_id'     => '%d',
			'to_email'    => '%s',
			'cc'          => '%s',
			'bcc'         => '%s',
			'subject'     => '%s',
			'body'        => '%s',
			'attachments' => '%s',
			'status'      => '%s',
			'created_at'  => '%s',
		);
	}

	/**
	 * Keep only whitelisted columns and build the format list.
	 *
	 * @param array $data    Raw data.
	 * @param array $columns Column => format whitelist.
	 * @return array{0:array,1:array}
	 */
	private static function filter_data( array $data, array $columns ) {
		$clean   = array();
		$formats = array();
		foreach ( $data as $key => $value ) {
			if ( isset( $columns[ $key ] ) ) {
				$clean[ $key ] = $value;
				$formats[]     = $columns[ $key ];
			}
		}
		return array( $clean, $formats );
	}

	/**
	 * Current UTC time in MySQL format.
	 *
	 * @param int $offset Seconds to add.
	 * @return string
	 */
	public static function now( $offset = 0 ) {
		return gmdate( 'Y-m-d H:i:s', time() + (int) $offset );
	}

	/**
	 * Clean a list of IDs.
	 *
	 * @param array $ids IDs.
	 * @return int[]
	 */
	private static function clean_ids( array $ids ) {
		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/**
	 * Insert a message.
	 *
	 * @param array $data Column => value.
	 * @return int Inserted ID, 0 on failure.
	 */
	public static function insert( array $data ) {
		global $wpdb;
		if ( empty( $data['created_at'] ) ) {
			$data['created_at'] = self::now();
		}
		list( $clean, $formats ) = self::filter_data( $data, self::columns() );
		return $wpdb->insert( self::table(), $clean, $formats ) ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Update a message.
	 *
	 * @param int   $id   Message ID.
	 * @param array $data Column => value.
	 * @return bool
	 */
	public static function update( $id, array $data ) {
		global $wpdb;
		list( $clean, $formats ) = self::filter_data( $data, self::columns() );
		if ( empty( $clean ) ) {
			return false;
		}
		return false !== $wpdb->update( self::table(), $clean, array( 'id' => absint( $id ) ), $formats, array( '%d' ) );
	}

	/**
	 * Fetch one message.
	 *
	 * @param int $id Message ID.
	 * @return array|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = esc_sql( self::table() );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ), ARRAY_A );
		return $row ? $row : null;
	}

	/**
	 * Delete messages (and their replies and notes).
	 *
	 * @param int[] $ids IDs.
	 * @return int Messages deleted.
	 */
	public static function delete( array $ids ) {
		global $wpdb;
		$ids          = self::clean_ids( $ids );
		$placeholders = implode( ',', array_fill( 0, max( 1, count( $ids ) ), '%d' ) );
		if ( empty( $ids ) ) {
			return 0;
		}
		$table   = esc_sql( self::table() );
		$replies = esc_sql( self::replies_table() );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$replies} WHERE message_id IN ({$placeholders})", $ids ) );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids ) );
	}

	/**
	 * Set the status of several messages.
	 *
	 * @param int[]  $ids    IDs.
	 * @param string $status unread|read|replied|spam.
	 * @return int Rows changed.
	 */
	public static function set_status( array $ids, $status ) {
		global $wpdb;
		if ( ! in_array( $status, array( 'unread', 'read', 'replied', 'spam' ), true ) ) {
			return 0;
		}
		$ids          = self::clean_ids( $ids );
		$placeholders = implode( ',', array_fill( 0, max( 1, count( $ids ) ), '%d' ) );
		if ( empty( $ids ) ) {
			return 0;
		}
		$table = esc_sql( self::table() );
		return (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET status = %s WHERE id IN ({$placeholders})", array_merge( array( $status ), $ids ) ) );
	}

	/**
	 * Star or unstar several messages.
	 *
	 * @param int[] $ids     IDs.
	 * @param bool  $starred New value.
	 * @return int Rows changed.
	 */
	public static function set_starred( array $ids, $starred ) {
		global $wpdb;
		$ids          = self::clean_ids( $ids );
		$placeholders = implode( ',', array_fill( 0, max( 1, count( $ids ) ), '%d' ) );
		if ( empty( $ids ) ) {
			return 0;
		}
		$table = esc_sql( self::table() );
		return (int) $wpdb->query( $wpdb->prepare( "UPDATE {$table} SET starred = %d WHERE id IN ({$placeholders})", array_merge( array( $starred ? 1 : 0 ), $ids ) ) );
	}

	/**
	 * Flip the star of one message.
	 *
	 * @param int $id Message ID.
	 * @return int New value (0 or 1).
	 */
	public static function toggle_star( $id ) {
		global $wpdb;
		$table = esc_sql( self::table() );
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET starred = 1 - starred WHERE id = %d", absint( $id ) ) );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT starred FROM {$table} WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Build a WHERE clause from filter arguments.
	 *
	 * @param array $args   Filters: status, category, urgency, search, form_id.
	 * @param array $values Collected placeholder values (by reference).
	 * @return string
	 */
	private static function where( array $args, array &$values ) {
		global $wpdb;
		$where  = array( '1=1' );
		$status = isset( $args['status'] ) ? (string) $args['status'] : '';

		if ( in_array( $status, array( 'unread', 'read', 'replied', 'spam' ), true ) ) {
			$where[]  = 'status = %s';
			$values[] = $status;
		} elseif ( 'starred' === $status ) {
			$where[]  = 'starred = 1 AND status <> %s';
			$values[] = 'spam';
		} else {
			$where[]  = 'status <> %s';
			$values[] = 'spam';
		}

		if ( ! empty( $args['category'] ) ) {
			$where[]  = 'category = %s';
			$values[] = sanitize_key( $args['category'] );
		}

		if ( ! empty( $args['urgency'] ) && array_key_exists( $args['urgency'], UICF7_Settings::urgencies() ) ) {
			$where[]  = 'urgency = %s';
			$values[] = $args['urgency'];
		}

		if ( ! empty( $args['form_id'] ) ) {
			$where[]  = 'form_id = %d';
			$values[] = absint( $args['form_id'] );
		}

		if ( isset( $args['search'] ) && '' !== $args['search'] ) {
			$like    = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where[] = '(sender_name LIKE %s OR sender_email LIKE %s OR subject LIKE %s OR summary LIKE %s OR body LIKE %s)';
			array_push( $values, $like, $like, $like, $like, $like );
		}

		return 'WHERE ' . implode( ' AND ', $where );
	}


	/**
	 * Query messages.
	 *
	 * @param array $args Filters plus orderby, order, per_page, page.
	 * @return array[]
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$table  = esc_sql( self::table() );
		$values = array();
		$where  = self::where( $args, $values );

		$orderby_ok = array( 'created_at', 'sender_name', 'category', 'urgency', 'status' );
		$orderby    = ( isset( $args['orderby'] ) && in_array( $args['orderby'], $orderby_ok, true ) ) ? $args['orderby'] : 'created_at';
		$order      = ( isset( $args['order'] ) && 'asc' === strtolower( $args['order'] ) ) ? 'ASC' : 'DESC';
		$per_page   = isset( $args['per_page'] ) ? max( 1, min( 200, (int) $args['per_page'] ) ) : 20;
		$page       = isset( $args['page'] ) ? max( 1, (int) $args['page'] ) : 1;

		$values[] = $per_page;
		$values[] = ( $page - 1 ) * $per_page;

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d", $values ), ARRAY_A ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is built by where() from fixed SQL fragments; every value goes through prepare(). $orderby/$order are whitelisted.
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count messages matching filters.
	 *
	 * @param array $args Filters.
	 * @return int
	 */
	public static function count( array $args = array() ) {
		global $wpdb;
		$table  = esc_sql( self::table() );
		$values = array();
		$where  = self::where( $args, $values );
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} {$where}", $values ) ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where is built by where() from fixed SQL fragments; every value goes through prepare().
	}

	/**
	 * Counts per view for the list table.
	 *
	 * @return array<string,int>
	 */
	public static function status_counts() {
		global $wpdb;
		$table  = esc_sql( self::table() );
		$rows   = $wpdb->get_results( "SELECT status, COUNT(*) AS total FROM {$table} GROUP BY status", ARRAY_A );
		$counts = array(
			'unread'  => 0,
			'read'    => 0,
			'replied' => 0,
			'spam'    => 0,
		);
		foreach ( (array) $rows as $row ) {
			if ( isset( $counts[ $row['status'] ] ) ) {
				$counts[ $row['status'] ] = (int) $row['total'];
			}
		}
		$counts['starred'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE starred = 1 AND status <> 'spam'" );
		return $counts;
	}

	/**
	 * Unread count for the menu bubble.
	 *
	 * @return int
	 */
	public static function unread_count() {
		global $wpdb;
		$table = esc_sql( self::table() );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'unread'" );
	}

	/**
	 * Numbers for the inbox stat cards.
	 *
	 * @return array<string,int>
	 */
	public static function dashboard_stats() {
		global $wpdb;
		$table  = esc_sql( self::table() );
		$cutoff = self::now( -7 * DAY_IN_SECONDS );
		$row    = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) AS unread,
					SUM(CASE WHEN urgency = 'high' AND status IN ('unread','read') THEN 1 ELSE 0 END) AS high_waiting,
					SUM(CASE WHEN created_at >= %s AND status <> 'spam' THEN 1 ELSE 0 END) AS last7,
					SUM(CASE WHEN replied_at IS NOT NULL AND replied_at >= %s THEN 1 ELSE 0 END) AS replied7
				FROM {$table}",
				$cutoff,
				$cutoff
			),
			ARRAY_A
		);
		$out    = array();
		foreach ( array( 'unread', 'high_waiting', 'last7', 'replied7' ) as $key ) {
			$out[ $key ] = isset( $row[ $key ] ) ? (int) $row[ $key ] : 0;
		}
		return $out;
	}

	/**
	 * IDs of the next messages due for AI analysis.
	 *
	 * @param int $limit Maximum.
	 * @return int[]
	 */
	public static function next_queue_ids( $limit = 5 ) {
		global $wpdb;
		$table = esc_sql( self::table() );
		$ids   = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$table}
				WHERE ( ai_status = 'pending' AND ( ai_next_at IS NULL OR ai_next_at <= %s ) )
				   OR ( ai_status = 'processing' AND ( ai_locked_at IS NULL OR ai_locked_at < %s ) )
				ORDER BY id ASC LIMIT %d",
				self::now(),
				self::now( -self::LOCK_SECONDS ),
				max( 1, (int) $limit )
			)
		);
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Queue sizes: due now, and waiting in total.
	 *
	 * @return array{due:int,waiting:int}
	 */
	public static function queue_counts() {
		global $wpdb;
		$table = esc_sql( self::table() );
		$row   = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					SUM(CASE WHEN ( ai_status = 'pending' AND ( ai_next_at IS NULL OR ai_next_at <= %s ) )
					          OR ( ai_status = 'processing' AND ( ai_locked_at IS NULL OR ai_locked_at < %s ) ) THEN 1 ELSE 0 END) AS due,
					COUNT(*) AS waiting
				FROM {$table} WHERE ai_status IN ('pending','processing')",
				self::now(),
				self::now( -self::LOCK_SECONDS )
			),
			ARRAY_A
		);
		return array(
			'due'     => isset( $row['due'] ) ? (int) $row['due'] : 0,
			'waiting' => isset( $row['waiting'] ) ? (int) $row['waiting'] : 0,
		);
	}

	/**
	 * Atomically take a queued message so cron and the browser never analyze it twice.
	 *
	 * @param int $id Message ID.
	 * @return bool Whether this request owns the message now.
	 */
	public static function claim( $id ) {
		global $wpdb;
		$table = esc_sql( self::table() );
		$rows  = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET ai_status = 'processing', ai_locked_at = %s
				WHERE id = %d AND ( ai_status = 'pending' OR ( ai_status = 'processing' AND ( ai_locked_at IS NULL OR ai_locked_at < %s ) ) )",
				self::now(),
				absint( $id ),
				self::now( -self::LOCK_SECONDS )
			)
		);
		return 1 === (int) $rows;
	}

	/**
	 * Put messages in the analysis queue.
	 *
	 * @param int[] $ids IDs.
	 * @return int Rows queued.
	 */
	public static function mark_pending( array $ids ) {
		global $wpdb;
		$ids          = self::clean_ids( $ids );
		$placeholders = implode( ',', array_fill( 0, max( 1, count( $ids ) ), '%d' ) );
		if ( empty( $ids ) ) {
			return 0;
		}
		$table = esc_sql( self::table() );
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET ai_status = 'pending', ai_attempts = 0, ai_next_at = NULL, ai_locked_at = NULL, ai_note = ''
				WHERE id IN ({$placeholders}) AND ai_status <> 'processing'",
				$ids
			)
		);
	}

	/**
	 * Messages never analyzed (AI was off) or whose analysis failed.
	 *
	 * @param int $limit Maximum.
	 * @return int[]
	 */
	public static function unprocessed_ids( $limit = 500 ) {
		global $wpdb;
		$table = esc_sql( self::table() );
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE ai_status IN ('off','failed') AND status <> 'spam' ORDER BY id DESC LIMIT %d", absint( $limit ) ) );
		return array_map( 'intval', (array) $ids );
	}

	/**
	 * Count of unprocessed messages.
	 *
	 * @return int
	 */
	public static function count_unprocessed() {
		global $wpdb;
		$table = esc_sql( self::table() );
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE ai_status IN ('off','failed') AND status <> 'spam'" );
	}

	/**
	 * Newer and older neighbours for prev/next navigation.
	 *
	 * @param array $message Current message.
	 * @return array{newer:int,older:int}
	 */
	public static function neighbors( array $message ) {
		global $wpdb;
		$table = esc_sql( self::table() );
		$spam  = 'spam' === $message['status'] ? 1 : 0;
		$id    = (int) $message['id'];
		return array(
			'newer' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id > %d AND ( CASE WHEN status = 'spam' THEN 1 ELSE 0 END ) = %d ORDER BY id ASC LIMIT 1", $id, $spam ) ),
			'older' => (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE id < %d AND ( CASE WHEN status = 'spam' THEN 1 ELSE 0 END ) = %d ORDER BY id DESC LIMIT 1", $id, $spam ) ),
		);
	}

	/**
	 * Add a reply or internal note.
	 *
	 * @param array $data Column => value.
	 * @return int Inserted ID.
	 */
	public static function add_reply( array $data ) {
		global $wpdb;
		if ( empty( $data['created_at'] ) ) {
			$data['created_at'] = self::now();
		}
		list( $clean, $formats ) = self::filter_data( $data, self::reply_columns() );
		return $wpdb->insert( self::replies_table(), $clean, $formats ) ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Replies and notes of one message, oldest first.
	 *
	 * @param int $message_id Message ID.
	 * @return array[]
	 */
	public static function replies_for( $message_id ) {
		global $wpdb;
		$table = esc_sql( self::replies_table() );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE message_id = %d ORDER BY id ASC", absint( $message_id ) ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Messages from one sender (privacy exporter).
	 *
	 * @param string $email  Sender email.
	 * @param int    $limit  Page size.
	 * @param int    $offset Offset.
	 * @return array[]
	 */
	public static function by_email( $email, $limit = 50, $offset = 0 ) {
		global $wpdb;
		$table = esc_sql( self::table() );
		$rows  = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE sender_email = %s ORDER BY id ASC LIMIT %d OFFSET %d", $email, absint( $limit ), absint( $offset ) ),
			ARRAY_A
		);
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Delete everything from one sender (privacy eraser).
	 *
	 * @param string $email Sender email.
	 * @return int Messages deleted.
	 */
	public static function delete_by_email( $email ) {
		global $wpdb;
		$table = esc_sql( self::table() );
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$table} WHERE sender_email = %s", $email ) );
		return self::delete( (array) $ids );
	}

	/**
	 * Delete messages older than N days.
	 *
	 * @param int $days Days to keep.
	 * @return int Messages deleted.
	 */
	public static function purge_older_than( $days ) {
		global $wpdb;
		$days = absint( $days );
		if ( $days < 1 ) {
			return 0;
		}
		$table   = esc_sql( self::table() );
		$replies = esc_sql( self::replies_table() );
		$cutoff  = self::now( -$days * DAY_IN_SECONDS );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$replies} WHERE message_id IN ( SELECT id FROM {$table} WHERE created_at < %s )", $cutoff ) );
		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
	}

	/**
	 * Summary numbers for the weekly digest.
	 *
	 * @param int $days Look-back window.
	 * @return array
	 */
	public static function digest_stats( $days = 7 ) {
		global $wpdb;
		$table  = esc_sql( self::table() );
		$cutoff = self::now( -absint( $days ) * DAY_IN_SECONDS );

		$total  = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s AND status <> 'spam'", $cutoff ) );
		$by_cat = $wpdb->get_results(
			$wpdb->prepare( "SELECT category, COUNT(*) AS total FROM {$table} WHERE created_at >= %s AND status <> 'spam' GROUP BY category", $cutoff ),
			ARRAY_A
		);

		return array(
			'total'           => $total,
			'by_category'     => is_array( $by_cat ) ? $by_cat : array(),
			'unanswered_high' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE urgency = 'high' AND status IN ('unread','read')" ),
			'unread'          => self::unread_count(),
		);
	}

	/**
	 * Rows for CSV export.
	 *
	 * @param int $limit Maximum rows.
	 * @return array[]
	 */
	public static function for_export( $limit = 10000 ) {
		global $wpdb;
		$table = esc_sql( self::table() );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} ORDER BY id DESC LIMIT %d", absint( $limit ) ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}
}
