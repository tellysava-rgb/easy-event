<?php if ( ! defined( 'ABSPATH' ) ) exit;

// Feldfehler aus Server-Validierung aufbereiten (key => message)
$field_errors = array();
if ( ! empty( $errors ) ) {
    foreach ( $errors as $err ) {
        if ( strpos( $err, 'Namen' ) !== false || strpos( $err, 'Name' ) !== false ) {
            $field_errors['name'] = $err;
        } elseif ( strpos( $err, 'E-Mail' ) !== false || strpos( $err, 'Email' ) !== false ) {
            $field_errors['email'] = $err;
        } elseif ( strpos( $err, 'Gruppe' ) !== false ) {
            $field_errors['group_id'] = $err;
        } else {
            $field_errors['general'][] = $err;
        }
    }
}
?>

<?php if ( ! empty( $field_errors['general'] ) ) : ?>
    <div class="easy-event-notice easy-event-error">
        <ul>
            <?php foreach ( $field_errors['general'] as $err ) : ?>
                <li><?php echo esc_html( $err ); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form class="easy-event-form" method="post" action="" id="ee-registration-form">
    <?php wp_nonce_field( 'easy_event_register', 'easy_event_nonce' ); ?>
    <input type="hidden" name="event_id" value="<?php echo (int) $event_id; ?>">
    <input type="hidden" name="easy_event_register" value="1">
    <input type="hidden" name="ee_current_url" value="<?php echo esc_url( get_permalink() ?: ( home_url( $_SERVER['REQUEST_URI'] ) ) ); ?>">
    <input type="hidden" name="ee_submit_token" value="<?php echo esc_attr( $submit_token ); ?>">

    <!-- Honeypot: für Menschen unsichtbar, Bots füllen es aus -->
    <div class="ee-honeypot" aria-hidden="true">
        <label for="ee-website">Website</label>
        <input type="text" id="ee-website" name="ee_website" value="" tabindex="-1" autocomplete="off">
    </div>

    <div class="easy-event-field<?php echo isset( $field_errors['name'] ) ? ' ee-field-error' : ''; ?>">
        <label for="ee-f-name">Name <span class="required">*</span></label>
        <input type="text" id="ee-f-name" name="name" required
               value="<?php echo esc_attr( $form_data['name'] ?? '' ); ?>">
        <?php if ( isset( $field_errors['name'] ) ) : ?>
            <span class="ee-field-msg"><?php echo esc_html( $field_errors['name'] ); ?></span>
        <?php else : ?>
            <span class="ee-field-msg" style="display:none"></span>
        <?php endif; ?>
    </div>

    <div class="easy-event-field<?php echo isset( $field_errors['email'] ) ? ' ee-field-error' : ''; ?>">
        <label for="ee-f-email">E-Mail <span class="required">*</span></label>
        <input type="email" id="ee-f-email" name="email" required
               pattern="[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}"
               value="<?php echo esc_attr( $form_data['email'] ?? '' ); ?>">
        <?php if ( isset( $field_errors['email'] ) ) : ?>
            <span class="ee-field-msg"><?php echo esc_html( $field_errors['email'] ); ?></span>
        <?php else : ?>
            <span class="ee-field-msg" style="display:none"></span>
        <?php endif; ?>
    </div>

    <?php if ( $event->has_groups ) : ?>
    <div class="easy-event-field<?php echo isset( $field_errors['group_id'] ) ? ' ee-field-error' : ''; ?>">
        <label for="ee-f-group">Gruppe <span class="required">*</span></label>
        <select id="ee-f-group" name="group_id" required>
            <option value="">— Gruppe auswählen —</option>
            <?php foreach ( $groups as $group ) : ?>
                <?php
                $sel      = selected( $form_data['group_id'] ?? '', $group->id, false );
                $disabled = ( $group->remaining === 0 ) ? ' disabled' : '';

                if ( $group->remaining === 0 ) {
                    $label = 'Ausverkauft – Gruppe ' . $group->group_number
                           . ( $group->start_time ? ' – ' . $group->start_time : '' )
                           . ( $group->leader     ? ' – ' . $group->leader     : '' );
                } elseif ( $group->remaining <= 15 ) {
                    $label = 'Noch ' . $group->remaining . ' Ticket(s) verfügbar'
                           . ' – Gruppe ' . $group->group_number
                           . ( $group->start_time ? ' – ' . $group->start_time : '' )
                           . ( $group->leader     ? ' – ' . $group->leader     : '' );
                } else {
                    $label = 'Gruppe ' . $group->group_number
                           . ( $group->start_time ? ' – ' . $group->start_time : '' )
                           . ( $group->leader     ? ' – ' . $group->leader     : '' );
                }
                ?>
                <option value="<?php echo (int) $group->id; ?>"<?php echo $sel . $disabled; ?>>
                    <?php echo esc_html( $label ); ?>
                </option>
            <?php endforeach; ?>
        </select>
        <?php if ( isset( $field_errors['group_id'] ) ) : ?>
            <span class="ee-field-msg"><?php echo esc_html( $field_errors['group_id'] ); ?></span>
        <?php else : ?>
            <span class="ee-field-msg" style="display:none"></span>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="easy-event-field">
        <label for="ee-f-tickets">Anzahl Tickets <span class="required">*</span></label>
        <select id="ee-f-tickets" name="tickets">
            <?php
            $sel_tickets = absint( $form_data['tickets'] ?? 1 );
            for ( $i = 1; $i <= 15; $i++ ) :
            ?>
                <option value="<?php echo $i; ?>" <?php selected( $sel_tickets, $i ); ?>>
                    <?php echo $i; ?>
                </option>
            <?php endfor; ?>
        </select>
        <!-- Tickets ist ein Select 1-15, kann nie leer sein -->
    </div>

    <div class="easy-event-submit">
        <button type="submit" class="easy-event-btn">
            Jetzt anmelden
        </button>
    </div>
</form>
