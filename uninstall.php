<?php
/**
 * Easy Event – Uninstall
 *
 * Wird von WordPress ausgeführt wenn das Plugin über "Löschen" entfernt wird.
 * Entfernt alle Tabellen und Optionen aus der Datenbank.
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_event_registrations" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_event_groups" );
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}easy_event_events" );

delete_option( 'easy_event_db_version' );
