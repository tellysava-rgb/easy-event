<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Easy_Event_Admin {

    /**
     * Wird gesetzt wenn process_save_event() einen Fehler zurückgibt.
     * Kein Redirect – die View liest diese Properties direkt aus.
     */
    private static $form_error    = null;  // string|null
    private static $form_warnings = array();
    private static $form_event    = null;  // stdClass|null  – befülltes $event-Objekt
    private static $form_groups   = array();

    public static function ee_ph( $tag ) {
        return '<code class="ee-placeholder" data-value="' . esc_attr( $tag ) . '" title="Klicken zum Einfügen">' . esc_html( $tag ) . '</code>';
    }

    public static function init() {
        add_action( 'admin_menu',            array( __CLASS__, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'admin_init',            array( __CLASS__, 'handle_form_submissions' ) );
        add_action( 'wp_ajax_ee_send_test_email', array( __CLASS__, 'ajax_send_test_email' ) );
        add_action( 'wp_ajax_ee_save_event',     array( __CLASS__, 'ajax_save_event' ) );
    }

    // ------------------------------------------------------------------
    // Menus
    // ------------------------------------------------------------------

    public static function register_menus() {
        add_menu_page(
            'Easy Event',
            'Easy Event',
            'manage_options',
            'easy-event',
            array( __CLASS__, 'page_events' ),
            'dashicons-tickets-alt',
            30
        );
        add_submenu_page(
            'easy-event', 'Events', 'Events',
            'manage_options', 'easy-event',
            array( __CLASS__, 'page_events' )
        );
        add_submenu_page(
            'easy-event', 'Anmeldungen', 'Anmeldungen',
            'manage_options', 'easy-event-registrations',
            array( __CLASS__, 'page_registrations' )
        );
    }

    // ------------------------------------------------------------------
    // Assets
    // ------------------------------------------------------------------

    public static function enqueue_assets( $hook ) {
        if ( strpos( $hook, 'easy-event' ) === false ) return;
        wp_enqueue_style(
            'easy-event-admin',
            EASY_EVENT_URL . 'assets/css/admin.css',
            array(), EASY_EVENT_VERSION
        );
        wp_enqueue_script(
            'easy-event-admin',
            EASY_EVENT_URL . 'assets/js/admin.js',
            array( 'jquery' ), EASY_EVENT_VERSION, true
        );
        wp_localize_script( 'easy-event-admin', 'eeAdmin', array(
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'ee_send_test_email' ),
        ) );
    }

    // ------------------------------------------------------------------
    // Form handling (runs on admin_init before output)
    // ------------------------------------------------------------------

    public static function handle_form_submissions() {
        if ( ! current_user_can( 'manage_options' ) ) return;

        // --- Save event ---
        if ( isset( $_POST['easy_event_save_event'] ) && ! wp_doing_ajax() ) {
            check_admin_referer( 'easy_event_save_event' );

            $result = self::process_save_event();

            if ( is_wp_error( $result ) ) {
                // KEIN Redirect – Fehler + POST-Daten in statischen Properties merken.
                // page_events() wird danach normal aufgerufen und zeigt das Formular
                // inklusive Fehlermeldung und allen eingegebenen Werten.
                self::$form_error  = $result->get_error_message();
                self::$form_event  = self::build_event_from_post();
                // Arrays in Objekte konvertieren, damit die View $group->id etc. nutzen kann
                self::$form_groups = array_map( function( $g ) { return (object) $g; }, self::build_groups_from_post() );
                return;
            }

            // Erfolg → einziger Redirect erlaubt
            $event_id = $result['event_id'];
            $redirect = admin_url( 'admin.php?page=easy-event&action=edit&id=' . $event_id . '&saved=1' );
            $redirect = wp_nonce_url( $redirect, 'easy_event_edit_' . $event_id );
            if ( ! empty( $result['warnings'] ) ) {
                set_transient( 'ee_admin_warnings_' . $event_id, $result['warnings'], 120 );
                $redirect = add_query_arg( 'ee_warnings', '1', $redirect );
            }
            wp_safe_redirect( $redirect );
            exit;
        }

        // --- Delete event ---
        if (
            isset( $_GET['action'] ) && $_GET['action'] === 'delete_event' &&
            isset( $_GET['id'] )
        ) {
            check_admin_referer( 'easy_event_delete_event_' . (int) $_GET['id'] );
            Easy_Event_Database::delete_event( (int) $_GET['id'] );
            wp_safe_redirect( admin_url( 'admin.php?page=easy-event&deleted=1' ) );
            exit;
        }

        // --- Delete registration ---
        if (
            isset( $_GET['action'] ) && $_GET['action'] === 'delete_registration' &&
            isset( $_GET['id'] )
        ) {
            check_admin_referer( 'easy_event_delete_registration_' . (int) $_GET['id'] );
            Easy_Event_Database::delete_registration( (int) $_GET['id'] );
            $back = admin_url( 'admin.php?page=easy-event-registrations&deleted=1' );
            if ( ! empty( $_GET['event_id'] ) ) {
                $back .= '&event_id=' . absint( $_GET['event_id'] );
            }
            wp_safe_redirect( $back );
            exit;
        }

        // --- CSV export ---
        if (
            isset( $_GET['action'] ) && $_GET['action'] === 'export_csv' &&
            isset( $_GET['event_id'] )
        ) {
            check_admin_referer( 'easy_event_export_csv' );
            self::export_csv( (int) $_GET['event_id'] );
            exit;
        }
    }

    // ------------------------------------------------------------------
    // Private helpers
    // ------------------------------------------------------------------

    /**
     * Verarbeitet das Speicher-Formular.
     *
     * @return array|WP_Error  array('event_id'=>int,'warnings'=>array) bei Erfolg,
     *                         WP_Error bei Validierungs- oder Datenbankfehler.
     */
    private static function process_save_event() {
        global $wpdb;

        // Whitelist: nur bekannte Felder aus $_POST übernehmen
        $data = array(
            'id'                          => absint( $_POST['event_id']                   ?? 0 ),
            'title'                       => $_POST['title']                              ?? '',
            'description'                 => $_POST['description']                        ?? '',
            'event_date'                  => $_POST['event_date']                         ?? '',
            'has_groups'                  => ! empty( $_POST['has_groups'] )              ? 1 : 0,
            'has_presale'                 => ! empty( $_POST['has_presale'] )             ? 1 : 0,
            'registration_deadline_date'  => $_POST['registration_deadline_date']         ?? '',
            'registration_deadline_time'  => $_POST['registration_deadline_time']         ?? '',
            'presale_date'                => $_POST['presale_date']                       ?? '',
            'presale_time'                => $_POST['presale_time']                       ?? '',
            'presale_message'             => $_POST['presale_message']                    ?? '',
            'sold_out_message'            => $_POST['sold_out_message']                   ?? '',
            'admin_email'                 => $_POST['admin_email']                        ?? '',
            'sender_name'                 => $_POST['sender_name']                        ?? '',
            'sender_email'                => $_POST['sender_email']                       ?? '',
            'confirmation_subject'        => $_POST['confirmation_subject']               ?? '',
            'confirmation_text'           => $_POST['confirmation_text']                  ?? '',
            'allow_duplicate_email'       => $_POST['allow_duplicate_email']              ?? '',
        );

        $warnings = array();

        // Pflichtfelder serverseitig prüfen (Fallback falls JS deaktiviert)
        $required_errors = array();
        if ( empty( trim( $data['title'] ) ) )
            $required_errors[] = 'Tab «Event-Details»: Titel ist erforderlich.';
        if ( empty( $data['event_date'] ) )
            $required_errors[] = 'Tab «Event-Details»: Event-Datum ist erforderlich.';
        if ( $data['has_presale'] ) {
            if ( empty( $data['presale_date'] ) )
                $required_errors[] = 'Tab «Vorverkauf»: Datum Vorverkauf ist erforderlich.';
            if ( empty( $data['presale_time'] ) )
                $required_errors[] = 'Tab «Vorverkauf»: Uhrzeit Vorverkauf ist erforderlich.';
        }

        if ( ! empty( $required_errors ) ) {
            return new WP_Error( 'validation', implode( ' / ', $required_errors ) );
        }

        // E-Mail-Felder validieren: warnen wenn ausgefüllt aber ungültig
        if ( ! empty( $data['admin_email'] ) && ! is_email( $data['admin_email'] ) ) {
            $warnings[] = 'Admin-E-Mail «' . esc_html( $data['admin_email'] ) . '» ist ungültig und wurde nicht gespeichert.';
        }
        if ( ! empty( $data['sender_email'] ) && ! is_email( $data['sender_email'] ) ) {
            $warnings[] = 'Absender-E-Mail «' . esc_html( $data['sender_email'] ) . '» ist ungültig und wurde nicht gespeichert.';
        }

        // Gruppen aus POST aufbereiten
        $groups = self::build_groups_from_post();

        // max_tickets < bereits verkaufte Tickets prüfen
        foreach ( $groups as $g ) {
            if ( $g['id'] ) {
                $sold = Easy_Event_Database::get_group_sold_tickets( $g['id'] );
                if ( $g['max_tickets'] < $sold ) {
                    $warnings[] = 'Gruppe ' . $g['group_number'] . ': Max. Tickets (' . $g['max_tickets'] . ') ist kleiner als bereits verkaufte Tickets (' . $sold . '). Der Wert wurde trotzdem gespeichert.';
                }
            }
        }

        // Alles in einer Transaktion speichern
        $wpdb->query( 'START TRANSACTION' );

        $event_id = Easy_Event_Database::save_event( $data );

        if ( is_wp_error( $event_id ) ) {
            $wpdb->query( 'ROLLBACK' );
            return $event_id;  // WP_Error zurückgeben – kein Redirect
        }

        $skipped = Easy_Event_Database::save_groups( $event_id, (array) $groups );
        foreach ( $skipped as $gnum ) {
            $warnings[] = 'Gruppe ' . $gnum . ' wurde nicht entfernt – es sind noch Anmeldungen vorhanden.';
        }

        $wpdb->query( 'COMMIT' );

        return array( 'event_id' => $event_id, 'warnings' => $warnings );
    }

    /**
     * Baut ein stdClass-Event-Objekt aus den aktuellen $_POST-Daten.
     * Wird nur bei Fehler aufgerufen, damit das Formular befüllt bleibt.
     */
    private static function build_event_from_post() {
        return (object) array(
            'id'                         => absint( $_POST['event_id']                  ?? 0 ),
            'title'                      => sanitize_text_field( $_POST['title']                    ?? '' ),
            'description'                => wp_kses_post( $_POST['description']              ?? '' ),
            'event_date'                 => sanitize_text_field( $_POST['event_date']               ?? '' ),
            'has_groups'                 => ! empty( $_POST['has_groups'] )              ? 1 : 0,
            'has_presale'                => ! empty( $_POST['has_presale'] )             ? 1 : 0,
            'registration_deadline_date' => sanitize_text_field( $_POST['registration_deadline_date'] ?? '' ),
            'registration_deadline_time' => sanitize_text_field( $_POST['registration_deadline_time'] ?? '' ),
            'presale_date'               => sanitize_text_field( $_POST['presale_date']             ?? '' ),
            'presale_time'               => sanitize_text_field( $_POST['presale_time']             ?? '' ),
            'presale_message'            => sanitize_textarea_field( $_POST['presale_message']          ?? '' ),
            'sold_out_message'           => sanitize_textarea_field( $_POST['sold_out_message']         ?? '' ),
            'admin_email'                => sanitize_email( $_POST['admin_email']               ?? '' ),
            'sender_name'                => sanitize_text_field( $_POST['sender_name']              ?? '' ),
            'sender_email'               => sanitize_email( $_POST['sender_email']              ?? '' ),
            'confirmation_subject'       => sanitize_text_field( $_POST['confirmation_subject']     ?? '' ),
            'confirmation_text'          => sanitize_textarea_field( $_POST['confirmation_text']        ?? '' ),
            'allow_duplicate_email'      => isset( $_POST['allow_duplicate_email'] )    ? 1 : 0,
        );
    }

    /**
     * Baut ein Array von Arrays aus den aktuellen $_POST-Gruppenfeldern.
     * Kompatibel mit save_groups(); für die View werden die Einträge in page_events() in Objekte konvertiert.
     */
    private static function build_groups_from_post() {
        $groups = array();
        if ( ! empty( $_POST['groups'] ) && is_array( $_POST['groups'] ) ) {
            foreach ( array_values( $_POST['groups'] ) as $g ) {
                if ( empty( $g['group_number'] ) ) continue;
                // Als Array (für save_groups) UND als stdClass-Properties (für die View)
                $groups[] = array(
                    'id'           => absint( $g['id']           ?? 0 ),
                    'group_number' => absint( $g['group_number'] ),
                    'description'  => sanitize_text_field( $g['description'] ?? '' ),
                    'max_tickets'  => absint( $g['max_tickets']  ?? 10 ),
                );
            }
        }
        return $groups;
    }

    private static function export_csv( $event_id ) {
        $event         = Easy_Event_Database::get_event( $event_id );
        $registrations = Easy_Event_Database::get_registrations( $event_id );
        $filename      = 'anmeldungen-' . sanitize_title( $event ? $event->title : 'event' ) . '-' . date_i18n( 'Y-m-d' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $out = fopen( 'php://output', 'w' );
        // UTF-8 BOM for Excel
        fputs( $out, "\xEF\xBB\xBF" );
        fputcsv( $out, array( 'Name', 'E-Mail', 'Gruppe Nr.', 'Beschreibung', 'Tickets', 'Anmeldedatum' ), ';' );

        foreach ( $registrations as $r ) {
            fputcsv( $out, array(
                $r->name,
                $r->email,
                $r->group_number ?? '',
                $r->description  ?? '',
                $r->tickets,
                date_i18n( 'd.m.Y H:i', strtotime( $r->created_at ) ),
            ), ';' );
        }
        fclose( $out );
    }

    // ------------------------------------------------------------------
    // Page callbacks
    // ------------------------------------------------------------------

    public static function page_events() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Keine Berechtigung.' ) );
        }

        // Nach einem fehlgeschlagenen Speichern: statische Properties sind gesetzt.
        // Die View bekommt $event und $groups direkt aus dem POST – kein Redirect nötig.
        if ( self::$form_error !== null ) {
            $event    = self::$form_event;
            $groups   = self::$form_groups;
            $ee_error = self::$form_error;
            include EASY_EVENT_PATH . 'admin/views/event-edit.php';
            return;
        }

        $action = sanitize_key( $_GET['action'] ?? 'list' );

        if ( in_array( $action, array( 'edit', 'new' ), true ) ) {
            $event_id = absint( $_GET['id'] ?? 0 );
            // Nonce-Prüfung beim Bearbeiten eines bestehenden Events
            if ( $event_id && isset( $_GET['_wpnonce'] ) ) {
                if ( ! wp_verify_nonce( $_GET['_wpnonce'], 'easy_event_edit_' . $event_id ) ) {
                    wp_die( 'Ungültige Anfrage.' );
                }
            }
            $event    = $event_id ? Easy_Event_Database::get_event( $event_id ) : null;
            $groups   = $event_id ? Easy_Event_Database::get_groups( $event_id ) : array();
            $ee_error = null;
            include EASY_EVENT_PATH . 'admin/views/event-edit.php';
        } else {
            $per_page = 50;
            $paged    = max( 1, absint( $_GET['paged'] ?? 1 ) );
            $total    = Easy_Event_Database::count_events();
            $events   = Easy_Event_Database::get_events( $per_page, $paged );
            include EASY_EVENT_PATH . 'admin/views/events-list.php';
        }
    }

    public static function page_registrations() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'Keine Berechtigung.' ) );
        }
        $per_page      = 50;
        $event_id      = absint( $_GET['event_id'] ?? 0 );
        $paged         = max( 1, absint( $_GET['paged'] ?? 1 ) );
        $total         = Easy_Event_Database::count_registrations_total( $event_id ?: null );
        $events        = Easy_Event_Database::get_events();
        $registrations = Easy_Event_Database::get_registrations( $event_id ?: null, $per_page, $paged );
        include EASY_EVENT_PATH . 'admin/views/registrations.php';
    }

    // ------------------------------------------------------------------
    // AJAX: Event speichern (für den «Weiter»-Button ohne Page-Reload)
    // ------------------------------------------------------------------

    public static function ajax_save_event() {
        check_ajax_referer( 'easy_event_save_event', '_wpnonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
        }

        $result = self::process_save_event();

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        wp_send_json_success( array(
            'event_id' => $result['event_id'],
            'warnings' => $result['warnings'],
        ) );
    }

    // ------------------------------------------------------------------
    // AJAX: Test-E-Mail direkt aus dem Event-Formular senden
    // ------------------------------------------------------------------

    public static function ajax_send_test_email() {
        check_ajax_referer( 'ee_send_test_email', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( 'Keine Berechtigung.' );
        }

        $to       = sanitize_email( $_POST['test_email'] ?? '' );
        $event_id = absint( $_POST['event_id'] ?? 0 );

        if ( ! is_email( $to ) ) {
            wp_send_json_error( 'Ungültige E-Mail-Adresse.' );
        }
        if ( ! $event_id ) {
            wp_send_json_error( 'Event-ID fehlt. Bitte zuerst speichern.' );
        }

        $event = Easy_Event_Database::get_event( $event_id );
        if ( ! $event ) {
            wp_send_json_error( 'Event nicht gefunden.' );
        }

        $sent = Easy_Event_Email::send_test_email( $to, $event );
        if ( $sent ) {
            wp_send_json_success( 'Test-E-Mail erfolgreich gesendet an ' . $to . '.' );
        } else {
            wp_send_json_error( 'E-Mail konnte nicht gesendet werden.' );
        }
    }
}
