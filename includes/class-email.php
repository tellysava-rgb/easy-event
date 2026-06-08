<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Easy_Event_Email {

    /**
     * Send confirmation email to the participant.
     */
    public static function send_confirmation( $registration, $event, $group ) {
        if ( empty( $event->sender_email ) || empty( $event->confirmation_subject ) ) {
            error_log( 'Easy Event: send_confirmation() übersprungen – Absender-E-Mail oder Betreff fehlt (Event-ID ' . ( $event->id ?? '?' ) . ').' );
            return false;
        }

        $to      = $registration->email;
        $subject = self::replace_placeholders( $event->confirmation_subject, $registration, $event, $group );
        $body    = self::replace_placeholders( $event->confirmation_text, $registration, $event, $group );
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . str_replace( array( "\r", "\n" ), '', $event->sender_name ) . ' <' . $event->sender_email . '>',
        );

        return wp_mail( $to, $subject, $body, $headers );
    }

    /**
     * Send notification email to the event admin.
     */
    public static function send_admin_notification( $registration, $event, $group ) {
        if ( empty( $event->admin_email ) ) return false;

        $gruppe_info = $group
            ? 'Gruppe ' . $group->group_number . ( ! empty( $group->description ) ? ' – ' . $group->description : '' )
            : '–';

        $to      = $event->admin_email;
        $subject = 'Neue Anmeldung: ' . $event->title;
        $body    = 'Neue Anmeldung für: ' . $event->title . "\n\n";
        $body   .= 'Name:            ' . $registration->name    . "\n";
        $body   .= 'E-Mail:          ' . $registration->email   . "\n";
        $body   .= 'Gruppe:          ' . $gruppe_info           . "\n";
        $body   .= 'Anzahl Tickets:  ' . $registration->tickets . "\n";
        $body   .= 'Anmeldedatum:    ' . date_i18n( 'd.m.Y H:i', strtotime( $registration->created_at ) ) . "\n";

        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
        if ( ! empty( $event->sender_email ) ) {
            $headers[] = 'From: ' . str_replace( array( "\r", "\n" ), '', $event->sender_name ) . ' <' . $event->sender_email . '>';
        }

        return wp_mail( $to, $subject, $body, $headers );
    }

    /**
     * Send a test email with example values.
     */
    public static function send_test_email( $to, $event ) {
        $fake_reg = (object) array(
            'name'       => 'Max Mustermann',
            'email'      => $to,
            'tickets'    => 2,
            'created_at' => current_time( 'mysql' ),
        );
        $fake_group = (object) array(
            'group_number' => 1,
            'description'  => 'Beispielgruppe',
        );

        $subject = '[TEST] ' . self::replace_placeholders( $event->confirmation_subject ?: 'Test-E-Mail: ' . $event->title, $fake_reg, $event, $fake_group );
        $body    = "DIES IST EINE TEST-E-MAIL (mit Beispielwerten)\n";
        $body   .= str_repeat( '-', 40 ) . "\n\n";
        $body   .= self::replace_placeholders( $event->confirmation_text ?: '(kein Bestätigungstext definiert)', $fake_reg, $event, $fake_group );

        $headers = array( 'Content-Type: text/plain; charset=UTF-8' );
        if ( ! empty( $event->sender_email ) ) {
            $headers[] = 'From: ' . str_replace( array( "\r", "\n" ), '', $event->sender_name ) . ' <' . $event->sender_email . '>';
        }

        return wp_mail( $to, $subject, $body, $headers );
    }

    /**
     * Replace all placeholders in a text string.
     *
     * Available placeholders:
     *   {name}, {email}, {tickets},
     *   {gruppe_nr}, {gruppe_beschreibung},
     *   {event_titel}, {event_datum}
     */
    private static function replace_placeholders( $text, $registration, $event, $group ) {
        $event_date = ! empty( $event->event_date )
            ? date_i18n( 'd.m.Y', strtotime( $event->event_date ) )
            : '';

        $map = array(
            '{name}'                => $registration->name    ?? '',
            '{email}'               => $registration->email   ?? '',
            '{tickets}'             => $registration->tickets ?? '',
            '{gruppe_nr}'           => $group ? ( $group->group_number ?? '' ) : '',
            '{gruppe_beschreibung}' => $group ? ( $group->description  ?? '' ) : '',
            '{event_titel}'         => $event->title          ?? '',
            '{event_datum}'         => $event_date,
        );

        return str_replace( array_keys( $map ), array_values( $map ), $text );
    }
}
