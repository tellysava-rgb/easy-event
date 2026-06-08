<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
    <h1>Anmeldungen</h1>

    <?php if ( isset( $_GET['deleted'] ) ) : ?>
        <div class="notice notice-success is-dismissible"><p>Anmeldung wurde gelöscht. Die Plätze sind wieder frei.</p></div>
    <?php endif; ?>

    <!-- Filter -->
    <form method="get" action="" style="margin: 16px 0;">
        <input type="hidden" name="page" value="easy-event-registrations">
        <select name="event_id" id="ee-event-filter" style="height:32px">
            <option value="">— Alle Events —</option>
            <?php foreach ( $events as $ev ) : ?>
                <option value="<?php echo (int) $ev->id; ?>" <?php selected( $event_id, $ev->id ); ?>>
                    <?php echo esc_html( $ev->title ); ?>
                    (<?php echo esc_html( date_i18n( 'd.m.Y', strtotime( $ev->event_date ) ) ); ?>)
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ( ! $event_id ) : ?>
            <input type="submit" class="button" value="Filtern">
        <?php endif; ?>
    </form>

    <?php if ( $event_id ) : ?>
        <?php
        $export_url = wp_nonce_url(
            admin_url( 'admin.php?page=easy-event&action=export_csv&event_id=' . $event_id ),
            'easy_event_export_csv'
        );
        ?>
        <p>
            <a href="<?php echo esc_url( $export_url ); ?>" class="button">
                ↓ CSV exportieren
            </a>
        </p>
    <?php endif; ?>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Name</th>
                <th>E-Mail</th>
                <th>Event</th>
                <th>Gruppe</th>
                <th style="width:70px">Tickets</th>
                <th style="width:140px">Anmeldedatum</th>
                <th style="width:80px">Aktion</th>
            </tr>
        </thead>
        <tbody>
            <?php if ( empty( $registrations ) ) : ?>
                <tr>
                    <td colspan="7">Keine Anmeldungen vorhanden.</td>
                </tr>
            <?php else : ?>
                <?php
                // Build events map to avoid N+1 queries
                $events_map = array();
                foreach ( $events as $ev ) {
                    $events_map[ $ev->id ] = $ev;
                }
                ?>
                <?php foreach ( $registrations as $reg ) : ?>
                    <?php
                    $ev_obj     = $events_map[ $reg->event_id ] ?? null;
                    $delete_url = wp_nonce_url(
                        admin_url( 'admin.php?page=easy-event-registrations&action=delete_registration&id=' . $reg->id . '&event_id=' . $reg->event_id ),
                        'easy_event_delete_registration_' . $reg->id
                    );
                    ?>
                    <tr>
                        <td><?php echo esc_html( $reg->name ); ?></td>
                        <td><?php echo esc_html( $reg->email ); ?></td>
                        <td><?php echo esc_html( $ev_obj ? $ev_obj->title : '–' ); ?></td>
                        <td>
                            <?php if ( ! empty( $reg->group_number ) ) : ?>
                                Gruppe <?php echo (int) $reg->group_number; ?>
                                <?php if ( ! empty( $reg->description ) ) : ?>
                                    &ndash; <?php echo esc_html( $reg->description ); ?>
                                <?php endif; ?>
                            <?php else : ?>
                                &ndash;
                            <?php endif; ?>
                        </td>
                        <td><?php echo (int) $reg->tickets; ?></td>
                        <td><?php echo esc_html( date_i18n( 'd.m.Y H:i', strtotime( $reg->created_at ) ) ); ?></td>
                        <td>
                            <a href="<?php echo esc_url( $delete_url ); ?>"
                               onclick="return confirm('Anmeldung von «<?php echo esc_js( $reg->name ); ?>» wirklich löschen?');"
                               style="color:#b32d2e">Löschen</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <?php if ( $total > 0 ) : ?>
        <p style="color:#666">
            <?php echo (int) $total; ?> Anmeldung(en) gefunden.
        </p>
    <?php endif; ?>

    <?php
    // Paginierung
    $total_pages = $per_page > 0 ? ceil( $total / $per_page ) : 1;
    if ( $total_pages > 1 ) :
        $base_url = admin_url( 'admin.php?page=easy-event-registrations' );
        if ( $event_id ) $base_url = add_query_arg( 'event_id', $event_id, $base_url );
        echo paginate_links( array(
            'base'      => add_query_arg( 'paged', '%#%', $base_url ),
            'format'    => '',
            'current'   => $paged,
            'total'     => $total_pages,
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
        ) );
    endif;
    ?>
</div>
