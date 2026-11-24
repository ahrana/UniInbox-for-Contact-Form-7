<?php
/**
 * Plugin bootstrap.
 *
 * @package UniInbox_For_CF7
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main plugin class.
 */
final class UICF7_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var UICF7_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get (and create) the instance.
	 *
	 * @return UICF7_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Wire everything up.
	 */
	private function __construct() {
		UICF7_Install::maybe_upgrade();

		UICF7_Capture::init();
		UICF7_Privacy::init();

		add_action( 'uicf7_analyze_message', array( 'UICF7_Cron', 'analyze' ), 10, 2 );
		add_action( 'uicf7_queue', array( 'UICF7_Cron', 'sweep' ) );
		add_action( 'uicf7_daily', array( 'UICF7_Cron', 'daily' ) );
		add_action( 'uicf7_weekly_digest', array( 'UICF7_Cron', 'weekly_digest' ) );

		if ( is_admin() ) {
			require_once UICF7_PATH . 'admin/class-uicf7-ui.php';
			require_once UICF7_PATH . 'admin/class-uicf7-list-table.php';
			require_once UICF7_PATH . 'admin/class-uicf7-ajax.php';
			require_once UICF7_PATH . 'admin/class-uicf7-admin.php';
			UICF7_Admin::init();
		}
	}
}
