<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Easy_Event_Database {

    const DB_VERSION_OPTION = 'easy_event_db_version';
    const DB_VERSION        = '1.3';

    // ------------------------------------------------------------------
    // Installation & Updates
    // ------------------------------------------------------------------

    /**
     * Läuft bei jedem Seitenaufruf via plugins_loaded.
     * Führt create_tables() + explizite Migrationen aus wenn sich die DB-Version geändert hat.
     */
    public static function maybe_upgrade() {
        if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
            self::create_tables();
            self::run_migrations();
        }
    }

    /**
     * Explizite Spalten-Migrationen als Fallback für dbDelta.
     * dbDelta fügt bei bestehenden Tabellen nicht immer zuverlässig neue Spalten hinzu.
     */
    public static function run_migrations() {
        global $wpdb;

        // v1.1: allow_duplicate_email
        $col = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}easy_event_events LIKE 'allow_duplicate_email'" );
        if ( empty( $col ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}easy_event_events ADD COLUMN allow_duplicate_email tinyint(1) NOT NULL DEFAULT 1" );
        }

        // v1.3: has_groups, has_presale, registration_deadline_date, registration_deadline_time
        $col = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}easy_event_events LIKE 'has_groups'" );
        if ( empty( $col ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}easy_event_events ADD COLUMN has_groups tinyint(1) NOT NULL DEFAULT 1" );
        }
        $col = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}easy_event_events LIKE 'has_presale'" );
        if ( empty( $col ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}easy_event_events ADD COLUMN has_presale tinyint(1) NOT NULL DEFAULT 1" );
        }
        $col = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}easy_event_events LIKE 'registration_deadline_date'" );
        if ( empty( $col ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}easy_event_events ADD COLUMN registration_deadline_date date DEFAULT NULL" );
        }
        $col = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}easy_event_events LIKE 'registration_deadline_time'" );
        if ( empty( $col ) ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}easy_event_events ADD COLUMN registration_deadline_time time DEFAULT NULL" );
        }

        // v1.3: group_id nullable in registrations
        $col = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}easy_event_registrations LIKE 'group_id'" );
        if ( ! empty( $col ) && strpos( $col[0]->Null, 'YES' ) === false ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}easy_event_registrations MODIFY COLUMN group_id bigint(20) UNSIGNED NULL DEFAULT NULL" );
        }

        // v1.3: presale_date / presale_time nullable (vorher NOT NULL)
        $col = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}easy_event_events LIKE 'presale_date'" );
        if ( ! empty( $col ) && strpos( $col[0]->Null, 'YES' ) === false ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}easy_event_events MODIFY COLUMN presale_date date DEFAULT NULL" );
        }
        $col = $wpdb->get_results( "SHOW COLUMNS FROM {$wpdb->prefix}easy_event_events LIKE 'presale_time'" );
        if ( ! empty( $col ) && strpos( $col[0]->Null, 'YES' ) === false ) {
            $wpdb->query( "ALTER TABLE {$wpdb->prefix}easy_event_events MODIFY COLUMN presale_time time DEFAULT NULL" );
        }
    }

    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = array();

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}easy_event_events (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title varchar(255) NOT NULL DEFAULT '',
            description longtext,
            event_date date NOT NULL,
            has_groups tinyint(1) NOT NULL DEFAULT 1,
            has_presale tinyint(1) NOT NULL DEFAULT 1,
            registration_deadline_date date DEFAULT NULL,
            registration_deadline_time time DEFAULT NULL,
            presale_message text,
            presale_date date DEFAULT NULL,
            presale_time time DEFAULT NULL,
            sold_out_message text,
            admin_email varchar(255) DEFAULT '',
            sender_name varchar(255) DEFAULT '',
            sender_email varchar(255) DEFAULT '',
            confirmation_subject varchar(255) DEFAULT '',
            confirmation_text longtext,
            allow_duplicate_email tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}easy_event_groups (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id bigint(20) UNSIGNED NOT NULL,
            group_number int(11) NOT NULL DEFAULT 1,
            start_time varchar(10) NOT NULL DEFAULT '',
            leader varchar(255) DEFAULT '',
            max_tickets int(11) NOT NULL DEFAULT 100,
            PRIMARY KEY (id),
            KEY event_id (event_id)
        ) $charset_collate;";

        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}easy_event_registrations (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            event_id bigint(20) UNSIGNED NOT NULL,
            group_id bigint(20) UNSIGNED NULL DEFAULT NULL,
            name varchar(255) NOT NULL DEFAULT '',
            email varchar(255) NOT NULL DEFAULT '',
            tickets int(11) NOT NULL DEFAULT 1,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY event_id (event_id),
            KEY group_id (group_id)
        ) $charset_collate;";


        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ( $sql as $query ) {
            dbDelta( $query );
        }

        update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

        // Explizite Migrationen immer nach create_tables ausführen
        self::run_migrations();
    }

    // ------------------------------------------------------------------
    // Events
    // ------------------------------------------------------------------

    public static function get_events() {
        global $wpdb;
        return $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}easy_event_events ORDER BY event_date DESC"
        );
    }

    public static function get_event( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}easy_event_events WHERE id = %d",
            absint( $id )
        ) );
    }

    public static function save_event( $data ) {
        global $wpdb;

        $has_presale = ! empty( $data['has_presale'] ) ? 1 : 0;

        $fields = array(
            'title'                      => sanitize_text_field( $data['title'] ?? '' ),
            'description'                => wp_kses_post( $data['description'] ?? '' ),
            'event_date'                 => sanitize_text_field( $data['event_date'] ?? '' ),
            'has_groups'                 => ! empty( $data['has_groups'] ) ? 1 : 0,
            'has_presale'                => $has_presale,
            'registration_deadline_date' => ! empty( $data['registration_deadline_date'] ) ? sanitize_text_field( $data['registration_deadline_date'] ) : null,
            'registration_deadline_time' => ! empty( $data['registration_deadline_time'] ) ? sanitize_text_field( $data['registration_deadline_time'] ) : null,
            'presale_message'            => sanitize_textarea_field( $data['presale_message'] ?? '' ),
            'presale_date'               => $has_presale && ! empty( $data['presale_date'] ) ? sanitize_text_field( $data['presale_date'] ) : null,
            'presale_time'               => $has_presale && ! empty( $data['presale_time'] ) ? sanitize_text_field( $data['presale_time'] ) : null,
            'sold_out_message'           => sanitize_textarea_field( $data['sold_out_message'] ?? '' ),
            'admin_email'                => is_email( $data['admin_email'] ?? '' ) ? sanitize_email( $data['admin_email'] ) : '',
            'sender_name'                => sanitize_text_field( $data['sender_name'] ?? '' ),
            'sender_email'               => is_email( $data['sender_email'] ?? '' ) ? sanitize_email( $data['sender_email'] ) : '',
            'confirmation_subject'       => sanitize_text_field( $data['confirmation_subject'] ?? '' ),
            'confirmation_text'          => sanitize_textarea_field( $data['confirmation_text'] ?? '' ),
            'allow_duplicate_email'      => isset( $data['allow_duplicate_email'] ) ? 1 : 0,
        );
        $formats = array( '%s', '%s', '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d' );

        $id = absint( $data['id'] ?? 0 );
        if ( $id ) {
            $result = $wpdb->update(
                $wpdb->prefix . 'easy_event_events',
                $fields,
                array( 'id' => $id ),
                $formats,
                array( '%d' )
            );
            if ( $result === false ) {
                return new WP_Error( 'db_error', 'Datenbankfehler beim Speichern: ' . $wpdb->last_error );
            }
            return $id;
        } else {
            $result = $wpdb->insert( $wpdb->prefix . 'easy_event_events', $fields, $formats );
            if ( $result === false ) {
                return new WP_Error( 'db_error', 'Datenbankfehler beim Erstellen: ' . $wpdb->last_error );
            }
            return (int) $wpdb->insert_id;
        }
    }

    public static function delete_event( $id ) {
        global $wpdb;
        $id = absint( $id );
        $wpdb->delete( $wpdb->prefix . 'easy_event_registrations', array( 'event_id' => $id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'easy_event_groups',        array( 'event_id' => $id ), array( '%d' ) );
        $wpdb->delete( $wpdb->prefix . 'easy_event_events',        array( 'id'       => $id ), array( '%d' ) );
    }

    // ------------------------------------------------------------------
    // Groups
    // ------------------------------------------------------------------

    public static function get_groups( $event_id ) {
        global $wpdb;
        return $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}easy_event_groups WHERE event_id = %d ORDER BY group_number ASC",
            absint( $event_id )
        ) );
    }

    public static function get_group( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}easy_event_groups WHERE id = %d",
            absint( $id )
        ) );
    }

    public static function save_groups( $event_id, $groups ) {
        global $wpdb;
        $event_id = absint( $event_id );

        // Bestehende Gruppen-IDs holen
        $existing_ids = $wpdb->get_col( $wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}easy_event_groups WHERE event_id = %d",
            $event_id
        ) );

        $submitted_ids = array();

        foreach ( $groups as $g ) {
            if ( empty( $g['group_number'] ) ) continue;

            $data = array(
                'event_id'     => $event_id,
                'group_number' => absint( $g['group_number'] ),
                'start_time'   => sanitize_text_field( $g['start_time'] ?? '' ),
                'leader'       => sanitize_text_field( $g['leader'] ?? '' ),
                'max_tickets'  => absint( $g['max_tickets'] ?? 10 ),
            );
            $formats = array( '%d', '%d', '%s', '%s', '%d' );

            $row_id = absint( $g['id'] ?? 0 );

            if ( $row_id && in_array( $row_id, $existing_ids ) ) {
                // Bestehende Gruppe aktualisieren (ID bleibt erhalten!)
                $wpdb->update(
                    $wpdb->prefix . 'easy_event_groups',
                    $data,
                    array( 'id' => $row_id ),
                    $formats,
                    array( '%d' )
                );
                $submitted_ids[] = $row_id;
            } else {
                // Neue Gruppe einfügen
                $wpdb->insert( $wpdb->prefix . 'easy_event_groups', $data, $formats );
                $submitted_ids[] = (int) $wpdb->insert_id;
            }
        }

        // Gruppen löschen, die im Formular entfernt wurden (keine Anmeldungen vorhanden)
        // Gibt Gruppen-Nummern zurück, die NICHT gelöscht werden konnten (wegen Anmeldungen)
        $skipped = array();
        foreach ( $existing_ids as $eid ) {
            if ( ! in_array( (int) $eid, $submitted_ids ) ) {
                $has_regs = (int) $wpdb->get_var( $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->prefix}easy_event_registrations WHERE group_id = %d",
                    $eid
                ) );
                if ( $has_regs ) {
                    $gnum = (int) $wpdb->get_var( $wpdb->prepare(
                        "SELECT group_number FROM {$wpdb->prefix}easy_event_groups WHERE id = %d",
                        $eid
                    ) );
                    $skipped[] = $gnum;
                } else {
                    $wpdb->delete( $wpdb->prefix . 'easy_event_groups', array( 'id' => $eid ), array( '%d' ) );
                }
            }
        }
        return $skipped;
    }

    /**
     * Gibt die Anzahl bereits verkaufter Tickets für eine Gruppe zurück.
     */
    public static function get_group_sold_tickets( $group_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(tickets), 0) FROM {$wpdb->prefix}easy_event_registrations WHERE group_id = %d",
            absint( $group_id )
        ) );
    }

    public static function get_group_remaining_tickets( $group_id ) {
        global $wpdb;
        $group = self::get_group( $group_id );
        if ( ! $group ) return 0;
        $sold = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COALESCE(SUM(tickets), 0) FROM {$wpdb->prefix}easy_event_registrations WHERE group_id = %d",
            absint( $group_id )
        ) );
        return max( 0, (int) $group->max_tickets - $sold );
    }

    public static function get_groups_with_availability( $event_id ) {
        global $wpdb;
        $groups = self::get_groups( $event_id );
        foreach ( $groups as &$group ) {
            $sold            = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(tickets), 0) FROM {$wpdb->prefix}easy_event_registrations WHERE group_id = %d",
                $group->id
            ) );
            $group->remaining = max( 0, (int) $group->max_tickets - $sold );
        }
        return $groups;
    }

    public static function is_event_sold_out( $event_id ) {
        $groups = self::get_groups_with_availability( $event_id );
        if ( empty( $groups ) ) return false;
        foreach ( $groups as $group ) {
            if ( $group->remaining > 0 ) return false;
        }
        return true;
    }

    // ------------------------------------------------------------------
    // Registrations
    // ------------------------------------------------------------------

    /**
     * Registrierungen abrufen, optional gefiltert und paginiert.
     *
     * @param int|null $event_id  Null = alle Events
     * @param int      $per_page  0 = kein Limit
     * @param int      $page      Aktuelle Seite (1-basiert)
     */
    public static function get_registrations( $event_id = null, $per_page = 0, $page = 1 ) {
        global $wpdb;

        $limit  = '';
        if ( $per_page > 0 ) {
            $offset = max( 0, ( (int) $page - 1 ) * (int) $per_page );
            $limit  = $wpdb->prepare( ' LIMIT %d OFFSET %d', $per_page, $offset );
        }

        if ( $event_id ) {
            return $wpdb->get_results( $wpdb->prepare(
                "SELECT r.*, g.group_number, g.start_time, g.leader
                 FROM {$wpdb->prefix}easy_event_registrations r
                 LEFT JOIN {$wpdb->prefix}easy_event_groups g ON r.group_id = g.id
                 WHERE r.event_id = %d
                 ORDER BY r.created_at DESC" . $limit,
                absint( $event_id )
            ) );
        }
        return $wpdb->get_results(
            "SELECT r.*, g.group_number, g.start_time, g.leader
             FROM {$wpdb->prefix}easy_event_registrations r
             LEFT JOIN {$wpdb->prefix}easy_event_groups g ON r.group_id = g.id
             ORDER BY r.created_at DESC" . $limit
        );
    }

    /**
     * Gesamtanzahl Registrierungen (für Paginierung).
     */
    public static function count_registrations_total( $event_id = null ) {
        global $wpdb;
        if ( $event_id ) {
            return (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}easy_event_registrations WHERE event_id = %d",
                absint( $event_id )
            ) );
        }
        return (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->prefix}easy_event_registrations"
        );
    }

    /**
     * Prüft ob eine E-Mail-Adresse für ein Event bereits registriert ist.
     */
    public static function is_email_registered( $event_id, $email ) {
        global $wpdb;
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}easy_event_registrations WHERE event_id = %d AND email = %s",
            absint( $event_id ),
            sanitize_email( $email )
        ) );
    }

    public static function get_registration( $id ) {
        global $wpdb;
        return $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}easy_event_registrations WHERE id = %d",
            absint( $id )
        ) );
    }

    /**
     * Speichert eine Anmeldung atomar.
     *
     * Verwendet eine MySQL-Transaktion mit SELECT ... FOR UPDATE, damit bei
     * gleichzeitigen Requests keine Überverkäufe entstehen.
     *
     * Rückgabe: Insert-ID bei Erfolg, WP_Error wenn nicht genug Tickets.
     */
    public static function save_registration( $data ) {
        global $wpdb;

        $group_id = ! empty( $data['group_id'] ) ? absint( $data['group_id'] ) : null;
        $tickets  = absint( $data['tickets'] );

        $wpdb->query( 'START TRANSACTION' );

        // Duplikat-E-Mail prüfen (atomar innerhalb der Transaktion)
        $event = self::get_event( absint( $data['event_id'] ) );
        if ( $event && ! $event->allow_duplicate_email ) {
            $already = (bool) $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->prefix}easy_event_registrations WHERE event_id = %d AND email = %s",
                absint( $data['event_id'] ),
                sanitize_email( $data['email'] )
            ) );
            if ( $already ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'duplicate_email', 'Diese E-Mail-Adresse ist für dieses Event bereits registriert.' );
            }
        }

        // Ticket-Limit nur prüfen wenn Gruppen aktiv
        if ( $group_id ) {
            // Gruppe mit Row-Lock lesen – blockiert andere gleichzeitige Transaktionen
            $group = $wpdb->get_row( $wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}easy_event_groups WHERE id = %d FOR UPDATE",
                $group_id
            ) );

            if ( ! $group ) {
                $wpdb->query( 'ROLLBACK' );
                return new WP_Error( 'group_not_found', 'Gruppe nicht gefunden.' );
            }

            $sold = (int) $wpdb->get_var( $wpdb->prepare(
                "SELECT COALESCE(SUM(tickets), 0) FROM {$wpdb->prefix}easy_event_registrations WHERE group_id = %d",
                $group_id
            ) );

            $remaining = max( 0, (int) $group->max_tickets - $sold );

            if ( $tickets > $remaining ) {
                $wpdb->query( 'ROLLBACK' );
                if ( $remaining === 0 ) {
                    return new WP_Error( 'sold_out', 'Diese Gruppe ist leider ausverkauft.' );
                }
                return new WP_Error( 'not_enough_tickets', 'Es sind nur noch ' . $remaining . ' Ticket(s) verfügbar.' );
            }
        }

        $wpdb->insert(
            $wpdb->prefix . 'easy_event_registrations',
            array(
                'event_id' => absint( $data['event_id'] ),
                'group_id' => $group_id,
                'name'     => sanitize_text_field( $data['name'] ),
                'email'    => sanitize_email( $data['email'] ),
                'tickets'  => $tickets,
            ),
            array( '%d', $group_id ? '%d' : 'NULL', '%s', '%s', '%d' )
        );

        $insert_id = (int) $wpdb->insert_id;
        $wpdb->query( 'COMMIT' );

        return $insert_id;
    }

    public static function delete_registration( $id ) {
        global $wpdb;
        $wpdb->delete(
            $wpdb->prefix . 'easy_event_registrations',
            array( 'id' => absint( $id ) ),
            array( '%d' )
        );
    }

    // ------------------------------------------------------------------
    // Stats helpers
    // ------------------------------------------------------------------

    public static function count_groups( $event_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}easy_event_groups WHERE event_id = %d",
            absint( $event_id )
        ) );
    }

    public static function count_registrations( $event_id ) {
        global $wpdb;
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}easy_event_registrations WHERE event_id = %d",
            absint( $event_id )
        ) );
    }
}
