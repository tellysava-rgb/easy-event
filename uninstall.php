<?php
/**
 * Easy Event – Uninstall
 *
 * Wird von WordPress ausgeführt wenn das Plugin über "Löschen" entfernt wird.
 * Entfernt alle Tabellen, Optionen und Transients aus der Datenbank.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_event_registrations" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_event_groups" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_event_events" );

delete_option( 'easy_event_db_version' );

// Alle Plugin-Transients entfernen (ee_* und easy_event_*)
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '_transient_ee_%'
        OR option_name LIKE '_transient_timeout_ee_%'
        OR option_name LIKE '_transient_easy_event_%'
        OR option_name LIKE '_transient_timeout_easy_event_%'"
);
