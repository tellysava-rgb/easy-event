<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Easy_Event_Shortcode {

    public static function init() {
        add_shortcode( 'easy_event', array( __CLASS__, 'render' ) );
        add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'init', array( __CLASS__, 'handle_form_submission' ) );
    }

    // ------------------------------------------------------------------
    // Assets
    // ------------------------------------------------------------------

    public static function enqueue_assets() {
        wp_enqueue_style(
            'easy-event-public',
            EASY_EVENT_URL . 'assets/css/public.css',
            array(), EASY_EVENT_VERSION
        );
        wp_enqueue_script(
            'easy-event-public',
            EASY_EVENT_URL . 'assets/js/public.js',
            array( 'jquery' ), EASY_EVENT_VERSION, true
        );
    }

    // ------------------------------------------------------------------
    // Form submission (runs on init, before any output)
    // ------------------------------------------------------------------

    public static function handle_form_submission() {
        if ( ! isset( $_POST['easy_event_register'] ) ) return;
        if ( ! isset( $_POST['easy_event_nonce'] ) || ! wp_verify_nonce( $_POST['easy_event_nonce'], 'easy_event_register' ) ) {
            return;
        }

        // Honeypot: Bots füllen versteckte Felder aus – bei Treffer stumm als Erfolg melden
        if ( ! empty( $_POST['ee_website'] ) ) {
            $current_url = ! empty( $_POST['ee_current_url'] )
                ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['ee_current_url'] ) ), home_url( '/' ) )
                : home_url( $_SERVER['REQUEST_URI'] );
            $success_key = 'ee_ok_' . md5( uniqid( '', true ) );
            set_transient( $success_key, 1, 1800 );
            wp_safe_redirect( add_query_arg( 'ee_msg', $success_key, $current_url ) );
            exit;
        }

        // Submit-Token prüfen (Schutz gegen Doppelabsenden)
        $submit_token = sanitize_key( $_POST['ee_submit_token'] ?? '' );
        if ( ! $submit_token || ! get_transient( 'ee_form_token_' . $submit_token ) ) {
            // Formular wurde bereits abgeschickt oder Token ist abgelaufen
            $current_url = ! empty( $_POST['ee_current_url'] )
                ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['ee_current_url'] ) ), home_url( '/' ) )
                : home_url( $_SERVER['REQUEST_URI'] );
            $transient_key = 'ee_err_' . md5( uniqid( '', true ) );
            set_transient( $transient_key, array(
                'errors' => array( 'Das Formular wurde bereits abgeschickt oder ist abgelaufen. Bitte lade die Seite neu und versuche es erneut.' ),
                'data'   => array(),
            ), 1800 );
            wp_safe_redirect( add_query_arg( 'ee_error', $transient_key, $current_url ) );
            exit;
        }
        // Token einmalig verbrauchen
        delete_transient( 'ee_form_token_' . $submit_token );

        $event_id = absint( $_POST['event_id']  ?? 0 );
        $group_id = absint( $_POST['group_id']  ?? 0 );
        $name     = sanitize_text_field( $_POST['name']    ?? '' );
        $email    = sanitize_email(      $_POST['email']   ?? '' );
        $tickets  = max( 1, min( 15, absint( $_POST['tickets'] ?? 1 ) ) );

        $errors = array();

        if ( empty( $name ) )
            $errors[] = 'Name: Dieses Feld darf nicht leer sein.';
        if ( empty( trim( $_POST['email'] ?? '' ) ) )
            $errors[] = 'E-Mail: Dieses Feld darf nicht leer sein.';
        elseif ( ! is_email( $email ) )
            $errors[] = 'E-Mail: Bitte eine gültige E-Mail-Adresse eingeben (z.B. name@beispiel.ch).';

        // Event laden – wird für alle weiteren Prüfungen benötigt
        if ( $event_id ) {
            $event = Easy_Event_Database::get_event( $event_id );
            // Gruppe nur prüfen wenn Event Gruppen hat
            if ( $event && $event->has_groups && ! $group_id )
                $errors[] = 'Gruppe: Bitte eine Gruppe auswählen.';
            if ( ! $event ) {
                $errors[] = 'Event nicht gefunden.';
            } else {
                $now = current_datetime()->getTimestamp();

                // Vorverkaufsstart nur prüfen wenn aktiviert
                if ( $event->has_presale ) {
                    $presale_ts = strtotime( $event->presale_date . ' ' . $event->presale_time );
                    if ( $presale_ts === false ) {
                        $errors[] = 'Ungültiges Vorverkaufsdatum. Bitte den Admin kontaktieren.';
                    } elseif ( $now < $presale_ts ) {
                        $errors[] = 'Der Vorverkauf hat noch nicht begonnen.';
                    }
                }

                // Anmeldeschluss prüfen
                if ( ! empty( $event->registration_deadline_date ) ) {
                    $time_part   = ! empty( $event->registration_deadline_time ) ? $event->registration_deadline_time : '23:59';
                    $deadline_ts = strtotime( $event->registration_deadline_date . ' ' . $time_part );
                    if ( $deadline_ts !== false && $now > $deadline_ts ) {
                        $errors[] = 'Die Anmeldefrist ist abgelaufen.';
                    }
                }
            }
        } else {
            $errors[] = 'Ungültige Anfrage.';
        }

        // Check ticket availability (nur bei Gruppen-Events)
        if ( empty( $errors ) && $group_id ) {
            $remaining = Easy_Event_Database::get_group_remaining_tickets( $group_id );
            if ( $tickets > $remaining ) {
                if ( $remaining === 0 ) {
                    $errors[] = 'Diese Gruppe ist leider ausverkauft.';
                } else {
                    $errors[] = 'Es sind nur noch ' . $remaining . ' Ticket(s) für diese Gruppe verfügbar.';
                }
            }
        }

        // Rückgabe-URL aus dem Formular lesen – auf eigene Domain beschränken
        $current_url = ! empty( $_POST['ee_current_url'] )
            ? wp_validate_redirect( esc_url_raw( wp_unslash( $_POST['ee_current_url'] ) ), home_url( '/' ) )
            : home_url( $_SERVER['REQUEST_URI'] );

        if ( ! empty( $errors ) ) {
            $transient_key = 'ee_err_' . md5( uniqid( '', true ) );
            set_transient( $transient_key, array( 'errors' => $errors, 'data' => $_POST ), 1800 );
            wp_safe_redirect( add_query_arg( 'ee_error', $transient_key, $current_url ) );
            exit;
        }

        // Save registration (atomar mit Row-Lock)
        $result = Easy_Event_Database::save_registration( array(
            'event_id' => $event_id,
            'group_id' => $group_id,
            'name'     => $name,
            'email'    => $email,
            'tickets'  => $tickets,
        ) );

        // Fehler aus der Transaktion (z.B. Überverkauf durch Race Condition)
        if ( is_wp_error( $result ) ) {
            $transient_key = 'ee_err_' . md5( uniqid( '', true ) );
            set_transient( $transient_key, array( 'errors' => array( $result->get_error_message() ), 'data' => $_POST ), 1800 );
            wp_safe_redirect( add_query_arg( 'ee_error', $transient_key, $current_url ) );
            exit;
        }

        // Send emails
        $registration = Easy_Event_Database::get_registration( $result );
        $event        = Easy_Event_Database::get_event( $event_id );
        $group        = Easy_Event_Database::get_group( $group_id );

        if ( $registration && $event && $group ) {
            Easy_Event_Email::send_confirmation( $registration, $event, $group );
            Easy_Event_Email::send_admin_notification( $registration, $event, $group );
        }

        // Erfolg: Transient statt URL-Parameter
        $success_key = 'ee_ok_' . md5( uniqid( '', true ) );
        set_transient( $success_key, $event_id, 1800 );
        wp_safe_redirect( add_query_arg( 'ee_msg', $success_key, remove_query_arg( 'ee_error', $current_url ) ) );
        exit;
    }

    // ------------------------------------------------------------------
    // Shortcode renderer
    // ------------------------------------------------------------------

    public static function render( $atts ) {
        $atts     = shortcode_atts( array( 'id' => 0 ), $atts, 'easy_event' );
        $event_id = absint( $atts['id'] );

        if ( ! $event_id ) {
            return '<p class="easy-event-notice easy-event-error">Kein Event angegeben. Beispiel: <code>[easy_event id="1"]</code></p>';
        }

        $event = Easy_Event_Database::get_event( $event_id );
        if ( ! $event ) {
            return '<p class="easy-event-notice easy-event-error">Event nicht gefunden.</p>';
        }

        ob_start();

        // Zeiten in lokaler WP-Zeitzone gespeichert → current_time('timestamp') konsistent
        $presale_ts = ( $event->has_presale && ! empty( $event->presale_date ) )
            ? (int) strtotime( $event->presale_date . ' ' . $event->presale_time )
            : 0;
        $now = current_datetime()->getTimestamp();

        echo '<div class="easy-event-wrap" data-event-id="' . (int) $event_id . '">';

        // Platzhalter {event_datum} in Titel und Beschreibung ersetzen
        $event_date_ts   = ! empty( $event->event_date ) ? strtotime( $event->event_date ) : false;
        $event_datum_fmt = $event_date_ts ? date_i18n( 'd.m.Y', $event_date_ts ) : '';
        $display_title   = str_replace( '{event_datum}', $event_datum_fmt, $event->title );
        $display_desc    = str_replace( '{event_datum}', $event_datum_fmt, $event->description ?? '' );

        // Header: title + description
        echo '<div class="easy-event-header">';
        echo '<h2 class="easy-event-title">' . esc_html( $display_title ) . '</h2>';
        if ( ! empty( $display_desc ) ) {
            echo '<div class="easy-event-description">' . wp_kses_post( $display_desc ) . '</div>';
        }
        echo '</div>';

        // Anmeldeschluss prüfen
        $deadline_passed = false;
        if ( ! empty( $event->registration_deadline_date ) ) {
            $dl_time     = ! empty( $event->registration_deadline_time ) ? $event->registration_deadline_time : '23:59';
            $deadline_ts = strtotime( $event->registration_deadline_date . ' ' . $dl_time );
            if ( $deadline_ts !== false && $now > $deadline_ts ) {
                $deadline_passed = true;
            }
        }

        // Vorverkaufsstart prüfen (nur wenn aktiviert)
        $presale_pending = false;
        if ( $event->has_presale && $presale_ts > 0 && $now < $presale_ts ) {
            $presale_pending = true;
        }

        if ( $presale_pending ) {
            // ---- Vorverkauf noch nicht gestartet ----
            $presale_label = date_i18n( 'd.m.Y', $presale_ts ) . ' um ' . date_i18n( 'H:i', $presale_ts ) . ' Uhr';

            echo '<div class="easy-event-presale">';
            if ( ! empty( $event->presale_message ) ) {
                $presale_datum   = date_i18n( 'd.m.Y', $presale_ts );
                $presale_uhrzeit = date_i18n( 'H:i', $presale_ts );
                $msg = str_replace(
                    array( '{datum}', '{uhrzeit}' ),
                    array( $presale_datum, $presale_uhrzeit ),
                    $event->presale_message
                );
                echo '<p>' . esc_html( $msg ) . '</p>';
            } else {
                echo '<p>Vorverkauf startet am <strong>' . esc_html( $presale_label ) . '</strong>.</p>';
            }
            $iso = date( 'Y-m-d', strtotime( $event->presale_date ) ) . 'T' . substr( $event->presale_time, 0, 5 ) . ':00';
            echo '<p class="easy-event-countdown" data-presale="' . esc_attr( $iso ) . '"></p>';
            echo '</div>';

        } elseif ( $deadline_passed ) {
            // ---- Anmeldeschluss überschritten ----
            echo '<div class="easy-event-notice easy-event-error"><p>Die Anmeldefrist für dieses Event ist abgelaufen.</p></div>';

        } else {
            // ---- Registration form or sold out ----
            $groups = $event->has_groups ? Easy_Event_Database::get_groups_with_availability( $event_id ) : array();
            if ( $event->has_groups && Easy_Event_Database::is_event_sold_out( $event_id ) ) {
                $msg = ! empty( $event->sold_out_message )
                    ? $event->sold_out_message
                    : 'Die Veranstaltung ist ausverkauft.';
                echo '<div class="easy-event-notice easy-event-sold-out"><p>' . esc_html( $msg ) . '</p></div>';
            } else {

                // Erfolgsmeldung: aus Transient lesen (kein URL-Parameter mehr)
                $show_success = false;
                if ( ! empty( $_GET['ee_msg'] ) ) {
                    $msg_key = sanitize_key( $_GET['ee_msg'] );
                    if ( get_transient( $msg_key ) ) {
                        delete_transient( $msg_key );
                        $show_success = true;
                    }
                }
                if ( $show_success ) {
                    echo '<div class="easy-event-notice easy-event-success">';
                    echo '<p>Deine Anmeldung wurde erfolgreich registriert.</p>';
                    echo '</div>';
                }

                // Retrieve form errors / repopulated data from transient
                $errors    = array();
                $form_data = array();
                if ( ! empty( $_GET['ee_error'] ) ) {
                    $t_key    = sanitize_key( $_GET['ee_error'] );
                    $t_data   = get_transient( $t_key );
                    if ( $t_data ) {
                        $errors    = $t_data['errors'];
                        $form_data = $t_data['data'];
                        delete_transient( $t_key );
                    }
                }

                // URL-Parameter nach Anzeige aus der Adresszeile entfernen (kein Reload-Problem)
                if ( $show_success || ! empty( $_GET['ee_error'] ) ) {
                    echo '<script>
                    if (window.history && window.history.replaceState) {
                        var u = new URL(window.location.href);
                        u.searchParams.delete("ee_msg");
                        u.searchParams.delete("ee_error");
                        window.history.replaceState({}, document.title, u.toString());
                    }
                    </script>';
                }

                // Submit-Token generieren (Schutz gegen Doppelabsenden)
                $submit_token = md5( uniqid( '', true ) );
                set_transient( 'ee_form_token_' . $submit_token, $event_id, 600 );

                include EASY_EVENT_PATH . 'public/views/form.php';
            }
        }

        echo '</div>'; // .easy-event-wrap

        return ob_get_clean();
    }
}
