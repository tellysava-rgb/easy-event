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
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . str_replace( array( "\r", "\n" ), '', $event->sender_name ) . ' <' . $event->sender_email . '>',
        );

        return wp_mail( $to, $subject, nl2br( esc_html( $body ) ), $headers );
    }

    /**
     * Send notification email to the event admin.
     */
    public static function send_admin_notification( $registration, $event, $group ) {
        if ( empty( $event->admin_email ) ) return false;

        $default_text = "Hallo\n\nEs wurde gerade eine neue Anmeldung registriert für {event_titel}.\n\nName: {name}\nEmail: {email}\nAnzahl Tickets: {anzahl_personen}\nGruppe: {gruppe_beschreibung}";
        $body_raw = ! empty( $event->admin_notification_text )
            ? $event->admin_notification_text
            : $default_text;

        $to      = $event->admin_email;
        $subject = 'Neue Anmeldung: ' . $event->title;
        $body    = self::replace_placeholders( $body_raw, $registration, $event, $group );
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . str_replace( array( "\r", "\n" ), '', $event->sender_name ) . ' <' . $event->sender_email . '>',
        );

        return wp_mail( $to, $subject, nl2br( esc_html( $body ) ), $headers );
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

        $headers = array( 'Content-Type: text/html; charset=UTF-8' );
        if ( ! empty( $event->sender_email ) ) {
            $headers[] = 'From: ' . str_replace( array( "\r", "\n" ), '', $event->sender_name ) . ' <' . $event->sender_email . '>';
        }

        return wp_mail( $to, $subject, nl2br( esc_html( $body ) ), $headers );
    }

    /**
     * Replace all placeholders in a text string.
     *
     * Available placeholders:
     *   {name}, {email}, {anzahl_personen},
     *   {gruppe_beschreibung},
     *   {event_titel}, {event_datum}
     */
    private static function replace_placeholders( $text, $registration, $event, $group ) {
        $event_date = ! empty( $event->event_date )
            ? date_i18n( 'd.m.Y', strtotime( $event->event_date ) )
            : '';

        $map = array(
            '{name}'                => $registration->name    ?? '',
            '{email}'               => $registration->email   ?? '',
            '{anzahl_personen}'     => $registration->tickets ?? '',
            '{gruppe_beschreibung}' => $group ? ( $group->description  ?? '' ) : '',
            '{event_titel}'         => $event->title          ?? '',
            '{event_datum}'         => $event_date,
        );

        return str_replace( array_keys( $map ), array_values( $map ), $text );
    }
}
