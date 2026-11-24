<?php
/**
 * Plugin Name:       UniInbox for Contact Form 7
 * Plugin URI:        https://wordpress.org/plugins/uniinbox-for-contact-form-7/
 * Description:       A smart inbox for Contact Form 7. Stores every submission, sorts it with AI (free plans supported), and lets you reply with rich text, templates and attachments.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Requires Plugins:  contact-form-7
 * Author:            Anowar Hossain Rana
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       uniinbox-for-contact-form-7
 *
 * @package UniInbox_For_CF7
 */

defined( 'ABSPATH' ) || exit;

define( 'UICF7_VERSION', '1.0.0' );
define( 'UICF7_FILE', __FILE__ );
define( 'UICF7_PATH', plugin_dir_path( __FILE__ ) );
define( 'UICF7_URL', plugin_dir_url( __FILE__ ) );
define( 'UICF7_DB_VERSION', '2' );

require_once UICF7_PATH . 'includes/class-uicf7-settings.php';
require_once UICF7_PATH . 'includes/class-uicf7-repository.php';
require_once UICF7_PATH . 'includes/class-uicf7-install.php';
require_once UICF7_PATH . 'includes/class-uicf7-mailer.php';
require_once UICF7_PATH . 'includes/class-uicf7-capture.php';
require_once UICF7_PATH . 'includes/class-uicf7-ai.php';
require_once UICF7_PATH . 'includes/class-uicf7-notifier.php';
require_once UICF7_PATH . 'includes/class-uicf7-cron.php';
require_once UICF7_PATH . 'includes/class-uicf7-privacy.php';
require_once UICF7_PATH . 'includes/class-uicf7-plugin.php';

add_filter( 'cron_schedules', array( 'UICF7_Install', 'cron_schedules' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected -- adds a 900-second (15-minute) interval.

register_activation_hook( __FILE__, array( 'UICF7_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'UICF7_Install', 'deactivate' ) );

add_action( 'plugins_loaded', array( 'UICF7_Plugin', 'instance' ) );
