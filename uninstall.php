<?php
/**
 * Uninstall: remove data only if the site owner chose to.
 *
 * @package UniInbox_For_CF7
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove plugin data for the current site.
 *
 * @return void
 */
function uicf7_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'uicf7_settings', array() );
	if ( empty( $settings['delete_on_uninstall'] ) ) {
		return;
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted table names, uninstall only.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}uicf7_replies" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}uicf7_messages" );
	// phpcs:enable

	delete_option( 'uicf7_settings' );
	delete_option( 'uicf7_db_version' );
	delete_option( 'uicf7_ai_usage' );

	wp_clear_scheduled_hook( 'uicf7_daily' );
	wp_clear_scheduled_hook( 'uicf7_weekly_digest' );
	wp_clear_scheduled_hook( 'uicf7_queue' );
	wp_unschedule_hook( 'uicf7_analyze_message' );
}

if ( is_multisite() ) {
	$uicf7_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $uicf7_site_ids as $uicf7_site_id ) {
		switch_to_blog( $uicf7_site_id );
		uicf7_uninstall_site();
		restore_current_blog();
	}
} else {
	uicf7_uninstall_site();
}
