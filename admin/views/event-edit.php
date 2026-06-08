<?php if ( ! defined( 'ABSPATH' ) ) exit;

// Feldzugriffe auf $event sicher kapseln – für PHP 8.x, wo null->property einen Error wirft
$v = (object) array(
    'id'                         => $event ? (int)    $event->id                                         : 0,
    'title'                      => $event ?          $event->title                                      : '',
    'event_date'                 => $event ?          $event->event_date                                 : '',
    'description'                => $event ?        ( $event->description                ?? '' )         : '',
    'has_groups'                 => $event ? (int)    $event->has_groups                                 : 1,
    'has_presale'                => $event ? (int)    $event->has_presale                                : 1,
    'allow_duplicate_email'      => $event ? (int)    $event->allow_duplicate_email                      : 1,
    'registration_deadline_date' => $event ?        ( $event->registration_deadline_date ?? '' )         : '',
    'registration_deadline_time' => $event ?   substr( $event->registration_deadline_time ?? '', 0, 5 )  : '',
    'presale_date'               => $event ?        ( $event->presale_date               ?? '' )         : '',
    'presale_time'               => $event ?   substr( $event->presale_time              ?? '', 0, 5 )   : '',
    'presale_message'            => $event ?        ( $event->presale_message            ?? '' )         : '',
    'sold_out_message'           => $event ?        ( $event->sold_out_message           ?? '' )         : '',
    'admin_email'                => $event ?        ( $event->admin_email                ?? '' )         : '',
    'sender_name'                => $event ?        ( $event->sender_name                ?? '' )         : '',
    'sender_email'               => $event ?        ( $event->sender_email               ?? '' )         : '',
    'confirmation_subject'       => $event ?        ( $event->confirmation_subject       ?? '' )         : '',
    'confirmation_text'          => $event ?        ( $event->confirmation_text          ?? '' )         : '',
);
?>
<div class="wrap">
    <h1><?php echo $event ? 'Event bearbeiten' : 'Neuen Event erstellen'; ?></h1>

    <?php if ( isset( $_GET['saved'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>Event wurde erfolgreich gespeichert.</p></div>
    <?php endif; ?>

    <?php if ( ! empty( $ee_error ) ) : ?>
        <div class="notice notice-error is-dismissible">
            <p><strong>Fehler beim Speichern:</strong> <?php echo esc_html( $ee_error ); ?></p>
        </div>
    <?php endif; ?>

    <?php if ( isset( $_GET['ee_warnings'] ) ) :
        $event_id_for_warn = absint( $_GET['id'] ?? 0 );
        $warnings = get_transient( 'ee_admin_warnings_' . $event_id_for_warn );
        delete_transient( 'ee_admin_warnings_' . $event_id_for_warn );
        if ( ! empty( $warnings ) ) :
    ?>
        <div class="notice notice-warning is-dismissible">
            <p><strong>Event gespeichert – aber mit folgenden Hinweisen:</strong></p>
            <ul style="list-style:disc; margin-left:20px">
                <?php foreach ( $warnings as $w ) : ?>
                    <li><?php echo esc_html( $w ); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; endif; ?>

    <form method="post" action="" id="easy-event-form" novalidate>
        <?php wp_nonce_field( 'easy_event_save_event' ); ?>
        <input type="hidden" name="event_id" value="<?php echo $v->id; ?>">
<input type="hidden" name="easy_event_save_event" value="1">

        <div id="ee-ajax-notice" class="notice is-dismissible" style="display:none; margin:0 0 16px"></div>

        <!-- Tab-Navigation -->
        <div id="ee-tabs">
            <ul class="ee-tab-nav">
                <li><a href="#ee-tab-details" class="ee-tab-link active">① Event-Details</a></li>
                <li id="ee-tab-nav-groups"  <?php if ( ! $v->has_groups  ) echo 'style="display:none"'; ?>><a href="#ee-tab-groups"  class="ee-tab-link">② Gruppen</a></li>
                <li id="ee-tab-nav-presale" <?php if ( ! $v->has_presale ) echo 'style="display:none"'; ?>><a href="#ee-tab-presale" class="ee-tab-link">③ Vorverkauf</a></li>
                <li><a href="#ee-tab-email"   class="ee-tab-link">④ E-Mail</a></li>
            </ul>

            <!-- ============================================================
                 Tab 1: Event-Details
                 ============================================================ -->
            <div id="ee-tab-details" class="ee-tab-panel">
                <table class="form-table ee-compact" role="presentation">
                    <tr>
                        <th scope="row"><label for="ee-event-date">Event-Datum <span class="required">*</span></label></th>
                        <td>
                            <input type="date" id="ee-event-date" name="event_date"
                                   required value="<?php echo esc_attr( $v->event_date ); ?>">
                            <span class="ee-field-hint"></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ee-title">Titel <span class="required">*</span></label></th>
                        <td>
                            <input type="text" id="ee-title" name="title" class="regular-text"
                                   required value="<?php echo esc_attr( $v->title ); ?>">
                            <span class="ee-field-hint"></span>
                            <p class="description">Platzhalter: <?php echo Easy_Event_Admin::ee_ph('{event_datum}'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="easy_event_description">Beschreibung</label></th>
                        <td>
                            <?php
                            wp_editor(
                                $v->description,
                                'easy_event_description',
                                array(
                                    'textarea_name' => 'description',
                                    'textarea_rows' => 6,
                                    'media_buttons' => false,
                                    'teeny'         => true,
                                )
                            );
                            ?>
                            <p class="description">Platzhalter: <?php echo Easy_Event_Admin::ee_ph('{event_datum}'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Gruppen</th>
                        <td>
                            <label>
                                <input type="checkbox" name="has_groups" id="ee-has-groups" value="1"
                                    <?php checked( $v->has_groups ); ?>>
                                Dieses Event hat Gruppen
                            </label>
                            <p class="description">Aktiviert den Gruppen-Tab und die Gruppenauswahl und Anzahlkontingent pro Gruppe im Anmeldeformular.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Vorverkauf</th>
                        <td>
                            <label>
                                <input type="checkbox" name="has_presale" id="ee-has-presale" value="1"
                                    <?php checked( $v->has_presale ); ?>>
                                Dieses Event hat einen Vorverkaufsstart
                            </label>
                            <p class="description">Aktiviert den Vorverkauf-Tab. Ohne Vorverkauf ist das Formular sofort offen.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Mehrfachanmeldung</th>
                        <td>
                            <label>
                                <input type="checkbox" name="allow_duplicate_email" value="1"
                                    <?php checked( $v->allow_duplicate_email ); ?>>
                                Dieselbe E-Mail-Adresse darf mehrfach für dieses Event angemeldet werden
                            </label>
                            <p class="description">
                                Deaktiviert: Jede E-Mail-Adresse kann pro Event nur <strong>einmal</strong> verwendet werden.
                                Schützt auch vor versehentlichem Doppelabsenden des Formulars.
                            </p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ee-deadline-date">Anmeldeschluss</label></th>
                        <td>
                            <input type="date" id="ee-deadline-date" name="registration_deadline_date"
                                   value="<?php echo esc_attr( $v->registration_deadline_date ); ?>">
                            <input type="time" id="ee-deadline-time" name="registration_deadline_time"
                                   value="<?php echo esc_attr( $v->registration_deadline_time ); ?>">
                            <p class="description">Optional. Nach diesem Zeitpunkt ist keine Anmeldung mehr möglich. Uhrzeit leer = 23:59.</p>
                        </td>
                    </tr>
                </table>
                <div class="ee-tab-buttons">
                    <button type="button" class="button button-primary ee-tab-next" data-next-tab="ee-tab-groups">Weiter: Gruppen →</button>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=easy-event' ) ); ?>" class="button">Abbrechen</a>
                </div>
            </div>

            <!-- ============================================================
                 Tab 2: Gruppen
                 ============================================================ -->
            <div id="ee-tab-groups" class="ee-tab-panel" style="display:none">
                <div id="ee-groups-error" class="notice notice-error" style="display:none; margin:12px 0 0">
                    <p></p>
                </div>
                <table class="wp-list-table widefat fixed" id="ee-groups-table">
                    <thead>
                        <tr>
                            <th style="width:90px">Gruppe Nr. <span class="required">*</span></th>
                            <th>Beschreibung</th>
                            <th style="width:150px">Max. Teilnehmer <span class="required">*</span></th>
                            <th style="width:100px">Aktion</th>
                        </tr>
                    </thead>
                    <tbody id="ee-groups-body">
                        <?php foreach ( $groups as $idx => $group ) : ?>
                            <tr class="ee-group-row">
                                <td>
                                    <input type="hidden" name="groups[<?php echo $idx; ?>][id]" value="<?php echo (int) $group->id; ?>">
                                    <input type="number" name="groups[<?php echo $idx; ?>][group_number]"
                                           value="<?php echo (int) $group->group_number; ?>"
                                           min="1" class="small-text" required>
                                </td>
                                <td><input type="text" name="groups[<?php echo $idx; ?>][description]"
                                           value="<?php echo esc_attr( $group->description ?? '' ); ?>"
                                           class="regular-text" style="width:100%"></td>
                                <td><input type="number" name="groups[<?php echo $idx; ?>][max_tickets]"
                                           value="<?php echo (int) $group->max_tickets; ?>"
                                           min="1" class="small-text" required></td>
                                <td><button type="button" class="button ee-remove-group">Entfernen</button></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p>
                    <button type="button" id="ee-add-group" class="button button-secondary">
                        + Gruppe hinzufügen
                    </button>
                </p>
                <div class="ee-tab-buttons">
                    <button type="button" class="button ee-tab-prev">← Zurück</button>
                    <button type="button" class="button button-primary ee-tab-next" data-next-tab="ee-tab-presale">Weiter: Vorverkauf →</button>
                </div>
            </div>

            <!-- ============================================================
                 Tab 3: Vorverkauf
                 ============================================================ -->
            <div id="ee-tab-presale" class="ee-tab-panel" style="display:none">
                <table class="form-table ee-compact" role="presentation">
                    <tr>
                        <th scope="row"><label for="ee-presale-date">Datum Vorverkauf <span class="required">*</span></label></th>
                        <td>
                            <input type="date" id="ee-presale-date" name="presale_date"
                                   required value="<?php echo esc_attr( $v->presale_date ); ?>">
                            <span class="ee-field-hint"></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ee-presale-time">Uhrzeit Vorverkauf <span class="required">*</span></label></th>
                        <td>
                            <input type="time" id="ee-presale-time" name="presale_time"
                                   required value="<?php echo esc_attr( $v->presale_time ); ?>">
                            <span class="ee-field-hint"></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ee-presale-msg">Nachricht vor Vorverkauf</label></th>
                        <td>
                            <textarea id="ee-presale-msg" name="presale_message" class="large-text" rows="3"><?php echo esc_textarea( $v->presale_message ?: 'Der Vorverkauf startet am {datum} um {uhrzeit}' ); ?></textarea>
                            <p class="description">Platzhalter: <?php echo Easy_Event_Admin::ee_ph('{datum}'); ?> <?php echo Easy_Event_Admin::ee_ph('{uhrzeit}'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ee-soldout-msg">Nachricht wenn ausverkauft</label></th>
                        <td>
                            <textarea id="ee-soldout-msg" name="sold_out_message" class="large-text" rows="3"><?php echo esc_textarea( $v->sold_out_message ?: 'Leider sind alle Tickets ausverkauft. Schade, vielleicht beim nächsten Mal.' ); ?></textarea>
                        </td>
                    </tr>
                </table>
                <div class="ee-tab-buttons">
                    <button type="button" class="button ee-tab-prev">← Zurück</button>
                    <button type="button" class="button button-primary ee-tab-next" data-next-tab="ee-tab-email">Weiter: E-Mail →</button>
                </div>
            </div>

            <!-- ============================================================
                 Tab 4: E-Mail
                 ============================================================ -->
            <div id="ee-tab-email" class="ee-tab-panel" style="display:none">
                <table class="form-table ee-compact" role="presentation">
                    <tr>
                        <th scope="row"><label for="ee-admin-email">Admin E-Mail (Empfänger)</label></th>
                        <td>
                            <input type="email" id="ee-admin-email" name="admin_email" class="regular-text"
                                   pattern="[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}"
                                   title="Bitte eine gültige E-Mail-Adresse eingeben (z.B. name@beispiel.ch)"
                                   value="<?php echo esc_attr( $v->admin_email ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ee-sender-name">Absender Name</label></th>
                        <td>
                            <input type="text" id="ee-sender-name" name="sender_name" class="regular-text"
                                   value="<?php echo esc_attr( $v->sender_name ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ee-sender-email">Absender E-Mail</label></th>
                        <td>
                            <input type="email" id="ee-sender-email" name="sender_email" class="regular-text"
                                   pattern="[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}"
                                   title="Bitte eine gültige E-Mail-Adresse eingeben (z.B. name@beispiel.ch)"
                                   value="<?php echo esc_attr( $v->sender_email ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ee-conf-subject">Betreff Bestätigungs-E-Mail</label></th>
                        <td>
                            <input type="text" id="ee-conf-subject" name="confirmation_subject" class="regular-text"
                                   value="<?php echo esc_attr( $v->confirmation_subject ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ee-conf-text">Text Bestätigungs-E-Mail</label></th>
                        <td>
                            <?php
                            $default_conf_text = "Hallo {name}\n\nDu hast dich gerade für {event_titel} angemeldet\n\nSuper bist du dabei.\nWir sehen uns.";
                            ?>
                            <textarea id="ee-conf-text" name="confirmation_text" class="large-text" rows="8"><?php echo esc_textarea( $v->confirmation_text ?: $default_conf_text ); ?></textarea>
                            <p class="description">
                                Platzhalter:
                                <?php
                                foreach ( ['{name}','{email}','{personen}','{gruppe_nr}','{gruppe_beschreibung}','{event_titel}','{event_datum}'] as $ph ) {
                                    echo Easy_Event_Admin::ee_ph( $ph ) . ' ';
                                }
                                ?>
                            </p>
                        </td>
                    </tr>
                </table>

                <?php if ( $event ) : ?>
                <hr style="margin:20px 0">
                <h3 style="margin-top:0">Test-E-Mail senden</h3>
                <p class="description">Sendet eine Test-E-Mail mit Beispielwerten. Die Adresse wird nicht gespeichert.</p>
                <div class="ee-test-email-row">
                    <input type="email" id="ee-test-email" placeholder="test@beispiel.ch" class="regular-text"
                           pattern="[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}"
                           title="Bitte eine gültige E-Mail-Adresse eingeben (z.B. name@beispiel.ch)">
                    <button type="button" id="ee-send-test" class="button button-secondary">Senden</button>
                    <span id="ee-test-result"></span>
                </div>
                <?php else : ?>
                <p class="description" style="margin-top:16px; color:#888">Speichere den Event zuerst, um eine Test-E-Mail senden zu können.</p>
                <?php endif; ?>

                <div class="ee-tab-buttons">
                    <button type="button" class="button ee-tab-prev">← Zurück</button>
                    <input type="submit" class="button-primary" value="Event speichern">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=easy-event' ) ); ?>" class="button">Abbrechen</a>
                </div>
            </div>

        </div><!-- #ee-tabs -->
    </form>
</div>

<?php if ( $event ) : ?>
    <div class="wrap" style="margin-top:0">
        <p>
            Shortcode für diese Seite:
            <code style="font-size:14px; padding:4px 10px; background:#f0f0f0">
                [easy_event id="<?php echo $v->id; ?>"]
            </code>
        </p>
    </div>
<?php endif; ?>
