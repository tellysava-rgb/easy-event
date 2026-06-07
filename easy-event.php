<?php
/**
 * Plugin Name: Easy Event
 * Description: Einfaches Event-Anmeldesystem mit Vorverkauf, Gruppen und Ticketkontingenten.
 * Version: 1.1.0
 * Author: Easy Event
 * Text Domain: easy-event
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'EASY_EVENT_VERSION', '1.1.0' );
define( 'EASY_EVENT_PATH', plugin_dir_path( __FILE__ ) );
define( 'EASY_EVENT_URL', plugin_dir_url( __FILE__ ) );

require_once EASY_EVENT_PATH . 'includes/class-database.php';
require_once EASY_EVENT_PATH . 'includes/class-email.php';
require_once EASY_EVENT_PATH . 'includes/class-updater.php';
require_once EASY_EVENT_PATH . 'admin/class-admin.php';
require_once EASY_EVENT_PATH . 'public/class-shortcode.php';

register_activation_hook( __FILE__, array( 'Easy_Event_Database', 'create_tables' ) );

add_action( 'plugins_loaded', 'easy_event_init' );

function easy_event_init() {
    Easy_Event_Database::maybe_upgrade();

    // GitHub-Updates: «dein-github-user» durch deinen GitHub-Benutzernamen ersetzen
    new Easy_Event_Updater( __FILE__, 'tellysava-rgb', 'easy-event' );

    if ( is_admin() ) {
        Easy_Event_Admin::init();
    } else {
        Easy_Event_Shortcode::init();
    }
}
