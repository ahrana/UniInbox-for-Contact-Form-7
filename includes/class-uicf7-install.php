<?php
/**
 * Activation, deactivation and upgrades.
 *
 * @package UniInbox_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Installer.
 */
class UICF7_Install {

	/**
	 * Add a 15-minute cron interval for the analysis queue.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public static function cron_schedules( $schedules ) {
		if ( ! isset( $schedules['uicf7_quarter_hour'] ) ) {
			$schedules['uicf7_quarter_hour'] = array(
				'interval' => 900,
				'display'  => 'Every 15 minutes (UniInbox)',
			);
		}
		return $schedules;
	}

	/**
	 * Plugin activation.
	 *
	 * @return void
	 */
	public static function activate() {
		self::create_tables();
		if ( false === get_option( UICF7_Settings::OPTION, false ) ) {
			add_option( UICF7_Settings::OPTION, UICF7_Settings::defaults(), '', false );
		}
		self::schedule_events();
	}

	/**
	 * Plugin deactivation: stop background jobs. Data is kept.
	 *
	 * @return void
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'uicf7_daily' );
		wp_clear_scheduled_hook( 'uicf7_weekly_digest' );
		wp_clear_scheduled_hook( 'uicf7_queue' );
		wp_unschedule_hook( 'uicf7_analyze_message' );
	}

	/**
	 * Run the table upgrade when the stored DB version is old.
	 *
	 * @return void
	 */
	public static function maybe_upgrade() {
		if ( get_option( 'uicf7_db_version' ) !== UICF7_DB_VERSION ) {
			self::create_tables();
		}
		self::schedule_events();
	}

	/**
	 * Create or update the plugin tables.
	 *
	 * @return void
	 */
	public static function create_tables() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$messages = UICF7_Repository::table();
		$replies  = UICF7_Repository::replies_table();
		$charset  = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$messages} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  form_id bigint(20) unsigned NOT NULL DEFAULT 0,
  form_title varchar(255) NOT NULL DEFAULT '',
  sender_name varchar(190) NOT NULL DEFAULT '',
  sender_email varchar(190) NOT NULL DEFAULT '',
  subject varchar(255) NOT NULL DEFAULT '',
  body longtext NOT NULL,
  field_data longtext NULL,
  status varchar(20) NOT NULL DEFAULT 'unread',
  starred tinyint(1) unsigned NOT NULL DEFAULT 0,
  category varchar(30) NOT NULL DEFAULT '',
  urgency varchar(10) NOT NULL DEFAULT '',
  summary text NULL,
  extracted longtext NULL,
  draft_reply longtext NULL,
  ai_status varchar(20) NOT NULL DEFAULT 'off',
  ai_note varchar(255) NOT NULL DEFAULT '',
  ai_provider varchar(20) NOT NULL DEFAULT '',
  ai_attempts smallint(5) unsigned NOT NULL DEFAULT 0,
  ai_next_at datetime NULL DEFAULT NULL,
  ai_locked_at datetime NULL DEFAULT NULL,
  notified_at datetime NULL DEFAULT NULL,
  replied_at datetime NULL DEFAULT NULL,
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY status_created (status,created_at),
  KEY ai_queue (ai_status,ai_next_at),
  KEY category (category),
  KEY urgency (urgency),
  KEY sender_email (sender_email),
  KEY created_at (created_at)
) {$charset};"
		);

		dbDelta(
			"CREATE TABLE {$replies} (
  id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  message_id bigint(20) unsigned NOT NULL,
  type varchar(10) NOT NULL DEFAULT 'reply',
  user_id bigint(20) unsigned NOT NULL DEFAULT 0,
  to_email varchar(190) NOT NULL DEFAULT '',
  cc text NULL,
  bcc text NULL,
  subject varchar(255) NOT NULL DEFAULT '',
  body longtext NOT NULL,
  attachments text NULL,
  status varchar(10) NOT NULL DEFAULT 'sent',
  created_at datetime NOT NULL,
  PRIMARY KEY  (id),
  KEY message_id (message_id)
) {$charset};"
		);

		update_option( 'uicf7_db_version', UICF7_DB_VERSION, false );
	}

	/**
	 * Make sure recurring jobs are scheduled.
	 *
	 * @return void
	 */
	public static function schedule_events() {
		if ( ! wp_next_scheduled( 'uicf7_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'uicf7_daily' );
		}
		if ( ! wp_next_scheduled( 'uicf7_weekly_digest' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'uicf7_weekly_digest' );
		}
		if ( ! wp_next_scheduled( 'uicf7_queue' ) ) {
			wp_schedule_event( time() + 15 * MINUTE_IN_SECONDS, 'uicf7_quarter_hour', 'uicf7_queue' );
		}
	}
}
