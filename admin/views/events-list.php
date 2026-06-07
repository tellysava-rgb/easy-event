<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
    <h1 class="wp-heading-inline">Events</h1>
    <a href="<?php echo esc_url( admin_url( 'admin.php?page=easy-event&action=new' ) ); ?>" class="page-title-action">
        Neuen Event erstellen
    </a>

    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>Event wurde erfolgreich gelöscht.</p></div>
    <?php endif; ?>

    <table class="wp-list-table widefat fixed striped" style="margin-top:20px">
        <thead>
            <tr>
                <th>Titel</th>
                <th>Event-Datum</th>
                <th>Vorverkauf ab</th>
                <th style="width:80px">Gruppen</th>
                <th style="width:100px">Anmeldungen</th>
                <th style="width:160px">Shortcode</th>
                <th style="width:140px">Aktionen</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $events ) ) : ?>
                <tr>
                    <td colspan="7">Noch keine Events vorhanden. <a href="<?php echo esc_url( admin_url( 'admin.php?page=easy-event&action=new' ) ); ?>">Ersten Event erstellen</a></td>
                </tr>
            <?php else : ?>
                <?php foreach ( $events as $event ) : ?>
                    <?php
                    $group_count = Easy_Event_Database::count_groups( $event->id );
                    $reg_count   = Easy_Event_Database::count_registrations( $event->id );
                    $edit_url    = wp_nonce_url(
                        admin_url( 'admin.php?page=easy-event&action=edit&id=' . $event->id ),
                        'easy_event_edit_' . $event->id
                    );
                    $delete_url  = wp_nonce_url(
                        admin_url( 'admin.php?page=easy-event&action=delete_event&id=' . $event->id ),
                        'easy_event_delete_event_' . $event->id
                    );
                    ?>
                    <tr>
                        <td>
                            <strong>
                                <a href="<?php echo esc_url( $edit_url ); ?>">
                                    <?php echo esc_html( $event->title ); ?>
                                </a>
                            </strong>
                        </td>
                        <td><?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $event->event_date ) ) ); ?></td>
                        <td>
                            <?php
                            echo esc_html(
                                date_i18n( 'd.m.Y', strtotime( $event->presale_date ) )
                                . ' ' .
                                substr( $event->presale_time, 0, 5 ) . ' Uhr'
                            );
                            ?>
                        </td>
                        <td><?php echo (int) $group_count; ?></td>
                        <td>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=easy-event-registrations&event_id=' . $event->id ) ); ?>">
                                <?php echo (int) $reg_count; ?>
                            </a>
                        </td>
                        <td>
                            <code>[easy_event id="<?php echo (int) $event->id; ?>"]</code>
                        </td>
                        <td>
                            <a href="<?php echo esc_url( $edit_url ); ?>">Bearbeiten</a>
                            &nbsp;|&nbsp;
                            <?php
                            $confirm_msg  = 'Event «' . esc_js( $event->title ) . '» wirklich unwiderruflich löschen?' . "\n\n";
                            $confirm_msg .= 'Folgendes wird vollständig und dauerhaft entfernt:' . "\n";
                            $confirm_msg .= '  • ' . $group_count . ' Gruppe(n)' . "\n";
                            $confirm_msg .= '  • ' . $reg_count   . ' Anmeldung(en)' . "\n";
                            $confirm_msg .= '  • Alle E-Mail- und Vorverkaufseinstellungen' . "\n\n";
                            $confirm_msg .= 'Diese Aktion kann nicht rückgängig gemacht werden.';
                            ?>
                            <a href="<?php echo esc_url( $delete_url ); ?>"
                               onclick="return confirm('<?php echo $confirm_msg; ?>');"
                               style="color:#b32d2e">Löschen</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
