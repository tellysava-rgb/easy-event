<?php
/**
 * Plugin Name: Easy Event
 * Description: Einfaches Event-Anmeldesystem mit Vorverkauf, Gruppen und Ticketkontingenten.
 * Version: 1.2.8
 * Author: Easy Event
 * Text Domain: easy-event
 * Requires at least: 5.8
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'EASY_EVENT_VERSION', '1.2.8' );
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
    load_plugin_textdomain( 'easy-event', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );

    Easy_Event_Database::maybe_upgrade();

    // GitHub-Updates: «dein-github-user» durch deinen GitHub-Benutzernamen ersetzen
    new Easy_Event_Updater( __FILE__, 'tellysava-rgb', 'easy-event' );

    if ( is_admin() ) {
        Easy_Event_Admin::init();
    } else {
        Easy_Event_Shortcode::init();
    }

    // DSGVO: Personenbezogene Daten exportieren und löschen
    add_filter( 'wp_privacy_personal_data_exporters', 'easy_event_register_privacy_exporter' );
    add_filter( 'wp_privacy_personal_data_erasers',   'easy_event_register_privacy_eraser' );
}

function easy_event_register_privacy_exporter( $exporters ) {
    $exporters['easy-event'] = array(
        'exporter_friendly_name' => 'Easy Event',
        'callback'               => 'easy_event_privacy_exporter',
    );
    return $exporters;
}

function easy_event_register_privacy_eraser( $erasers ) {
    $erasers['easy-event'] = array(
        'eraser_friendly_name' => 'Easy Event',
        'callback'             => 'easy_event_privacy_eraser',
    );
    return $erasers;
}

function easy_event_privacy_exporter( $email_address, $page = 1 ) {
    global $wpdb;

    $registrations = $wpdb->get_results( $wpdb->prepare(
        "SELECT r.*, e.title AS event_title, e.event_date, g.group_number, g.description
         FROM {$wpdb->prefix}easy_event_registrations r
         LEFT JOIN {$wpdb->prefix}easy_event_events e ON r.event_id = e.id
         LEFT JOIN {$wpdb->prefix}easy_event_groups g ON r.group_id = g.id
         WHERE r.email = %s",
        sanitize_email( $email_address )
    ) );

    $export_items = array();
    foreach ( $registrations as $reg ) {
        $gruppe = $reg->group_number
            ? 'Gruppe ' . $reg->group_number . ( ! empty( $reg->description ) ? ' – ' . $reg->description : '' )
            : '–';
        $export_items[] = array(
            'group_id'    => 'easy-event-registrations',
            'group_label' => 'Event-Anmeldungen',
            'item_id'     => 'registration-' . $reg->id,
            'data'        => array(
                array( 'name' => 'Name',         'value' => $reg->name ),
                array( 'name' => 'E-Mail',       'value' => $reg->email ),
                array( 'name' => 'Event',        'value' => $reg->event_title ),
                array( 'name' => 'Event-Datum',  'value' => $reg->event_date ),
                array( 'name' => 'Gruppe',       'value' => $gruppe ),
                array( 'name' => 'Tickets',      'value' => $reg->tickets ),
                array( 'name' => 'Anmeldedatum', 'value' => $reg->created_at ),
            ),
        );
    }

    return array( 'data' => $export_items, 'done' => true );
}

function easy_event_privacy_eraser( $email_address, $page = 1 ) {
    global $wpdb;

    $count = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}easy_event_registrations WHERE email = %s",
        sanitize_email( $email_address )
    ) );

    if ( $count > 0 ) {
        $wpdb->delete(
            $wpdb->prefix . 'easy_event_registrations',
            array( 'email' => sanitize_email( $email_address ) ),
            array( '%s' )
        );
    }

    return array(
        'items_removed'  => $count > 0,
        'items_retained' => false,
        'messages'       => $count > 0 ? array( $count . ' Anmeldung(en) wurden gelöscht.' ) : array(),
        'done'           => true,
    );
}
