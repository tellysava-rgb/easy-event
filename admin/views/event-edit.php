<?php if ( ! defined( 'ABSPATH' ) ) exit;

// Helper: klickbarer Platzhalter
function ee_ph( $tag ) {
    return '<code class="ee-placeholder" data-value="' . esc_attr( $tag ) . '" title="Klicken zum Einfügen">' . esc_html( $tag ) . '</code>';
}
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
        <input type="hidden" name="event_id" value="<?php echo $event ? (int) $event->id : 0; ?>">
        <input type="hidden" name="ee_next_tab" id="ee-next-tab" value="">

        <!-- Tab-Navigation -->
        <div id="ee-tabs">
            <ul class="ee-tab-nav">
                <li><a href="#ee-tab-details" class="ee-tab-link active">① Event-Details</a></li>
                <li id="ee-tab-nav-groups"  <?php if ( ! ( isset( $event->has_groups  ) ? $event->has_groups  : 1 ) ) echo 'style="display:none"'; ?>><a href="#ee-tab-groups"  class="ee-tab-link">② Gruppen</a></li>
                <li id="ee-tab-nav-presale" <?php if ( ! ( isset( $event->has_presale ) ? $event->has_presale : 1 ) ) echo 'style="display:none"'; ?>><a href="#ee-tab-presale" class="ee-tab-link">③ Vorverkauf</a></li>
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
                                   required value="<?php echo esc_attr( $event->event_date ?? '' ); ?>">
                            <span class="ee-field-hint"></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ee-title">Titel <span class="required">*</span></label></th>
                        <td>
                            <input type="text" id="ee-title" name="title" class="regular-text"
                                   required value="<?php echo esc_attr( $event->title ?? '' ); ?>">
                            <span class="ee-field-hint"></span>
                            <p class="description">Platzhalter: <?php echo ee_ph('{event_datum}'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="easy_event_description">Beschreibung</label></th>
                        <td>
                            <?php
                            wp_editor(
                                $event->description ?? '',
                                'easy_event_description',
                                array(
                                    'textarea_name' => 'description',
                                    'textarea_rows' => 6,
                                    'media_buttons' => false,
                                    'teeny'         => true,
                                )
                            );
                            ?>
                            <p class="description">Platzhalter: <?php echo ee_ph('{event_datum}'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Gruppen</th>
                        <td>
                            <label>
                                <input type="checkbox" name="has_groups" id="ee-has-groups" value="1"
                                    <?php checked( isset( $event->has_groups ) ? $event->has_groups : 1 ); ?>>
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
                                    <?php checked( isset( $event->has_presale ) ? $event->has_presale : 1 ); ?>>
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
                                    <?php checked( ! isset( $event->allow_duplicate_email ) || $event->allow_duplicate_email ); ?>>
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
                                   value="<?php echo esc_attr( $event->registration_deadline_date ?? '' ); ?>">
                            <input type="time" id="ee-deadline-time" name="registration_deadline_time"
                                   value="<?php echo esc_attr( substr( $event->registration_deadline_time ?? '', 0, 5 ) ); ?>">
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
                            <th style="width:110px">Startzeit</th>
                            <th>Gruppenleiter</th>
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
                                <td><input type="text" name="groups[<?php echo $idx; ?>][start_time]"
                                           value="<?php echo esc_attr( $group->start_time ); ?>"
                                           placeholder="10:00" class="small-text"></td>
                                <td><input type="text" name="groups[<?php echo $idx; ?>][leader]"
                                           value="<?php echo esc_attr( $group->leader ); ?>"
                                           class="regular-text"></td>
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
                                   required value="<?php echo esc_attr( $event->presale_date ?? '' ); ?>">
                            <span class="ee-field-hint"></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ee-presale-time">Uhrzeit Vorverkauf <span class="required">*</span></label></th>
                        <td>
                            <input type="time" id="ee-presale-time" name="presale_time"
                                   required value="<?php echo esc_attr( substr( $event->presale_time ?? '', 0, 5 ) ); ?>">
                            <span class="ee-field-hint"></span>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ee-presale-msg">Nachricht vor Vorverkauf</label></th>
                        <td>
                            <textarea id="ee-presale-msg" name="presale_message" class="large-text" rows="3"><?php echo esc_textarea( $event->presale_message ?? 'Der Vorverkauf startet am {datum} um {uhrzeit}' ); ?></textarea>
                            <p class="description">Platzhalter: <?php echo ee_ph('{datum}'); ?> <?php echo ee_ph('{uhrzeit}'); ?></p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ee-soldout-msg">Nachricht wenn ausverkauft</label></th>
                        <td>
                            <textarea id="ee-soldout-msg" name="sold_out_message" class="large-text" rows="3"><?php echo esc_textarea( $event->sold_out_message ?? 'Leider sind alle Tickets ausverkauft. Schade, vielleicht beim nächsten Mal.' ); ?></textarea>
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
                                   value="<?php echo esc_attr( $event->admin_email ?? '' ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ee-sender-name">Absender Name</label></th>
                        <td>
                            <input type="text" id="ee-sender-name" name="sender_name" class="regular-text"
                                   value="<?php echo esc_attr( $event->sender_name ?? '' ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ee-sender-email">Absender E-Mail</label></th>
                        <td>
                            <input type="email" id="ee-sender-email" name="sender_email" class="regular-text"
                                   pattern="[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}"
                                   title="Bitte eine gültige E-Mail-Adresse eingeben (z.B. name@beispiel.ch)"
                                   value="<?php echo esc_attr( $event->sender_email ?? '' ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ee-conf-subject">Betreff Bestätigungs-E-Mail</label></th>
                        <td>
                            <input type="text" id="ee-conf-subject" name="confirmation_subject" class="regular-text"
                                   value="<?php echo esc_attr( $event->confirmation_subject ?? '' ); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="ee-conf-text">Text Bestätigungs-E-Mail</label></th>
                        <td>
                            <?php
                            $default_conf_text = "Hallo {name}\n\nDu hast dir gerade {tickets} Ticket/s für die Gruppe {gruppe_nr} um {startzeit} für {event_titel} am {event_datum} gekauft.\n\nDu bist in der Gruppe mit {gruppenleiter}\n\nSuper bist du dabei.\nWir sehen uns.";
                            ?>
                            <textarea id="ee-conf-text" name="confirmation_text" class="large-text" rows="8"><?php echo esc_textarea( $event->confirmation_text ?? $default_conf_text ); ?></textarea>
                            <p class="description">
                                Platzhalter:
                                <?php
                                foreach ( ['{name}','{email}','{tickets}','{gruppe_nr}','{startzeit}','{gruppenleiter}','{event_titel}','{event_datum}'] as $ph ) {
                                    echo ee_ph( $ph ) . ' ';
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
                    <input type="submit" name="easy_event_save_event" class="button-primary" value="Event speichern">
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
                [easy_event id="<?php echo (int) $event->id; ?>"]
            </code>
        </p>
    </div>
<?php endif; ?>
