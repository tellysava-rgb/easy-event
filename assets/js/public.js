/* Easy Event – Public JS */
jQuery(function ($) {

    // ------------------------------------------------------------------
    // Countdown
    // ------------------------------------------------------------------
    var $countdown = $('.easy-event-countdown');

    if ($countdown.length) {
        var presaleISO  = $countdown.data('presale');
        var presaleDate = new Date(presaleISO);

        if (!isNaN(presaleDate.getTime())) {
            function pad(n) { return n < 10 ? '0' + n : n; }

            function tick() {
                var now  = new Date();
                var diff = presaleDate - now;

                if (diff <= 0) {
                    $countdown.text('Vorverkauf hat begonnen – Seite wird neu geladen…');
                    setTimeout(function () { window.location.reload(); }, 1500);
                    return;
                }

                var days    = Math.floor(diff / 86400000);
                var hours   = Math.floor((diff % 86400000) / 3600000);
                var minutes = Math.floor((diff % 3600000)  / 60000);
                var seconds = Math.floor((diff % 60000)    / 1000);

                var parts = [];
                if (days > 0)  parts.push(days + (days === 1 ? ' Tag' : ' Tage'));
                if (hours > 0) parts.push(pad(hours) + ' Std.');
                parts.push(pad(minutes) + ' Min.');
                parts.push(pad(seconds) + ' Sek.');

                $countdown.text(parts.join(' '));
            }

            tick();
            setInterval(tick, 1000);
        }
    }

    // ------------------------------------------------------------------
    // Formular-Validierung
    // ------------------------------------------------------------------
    var $form = $('#ee-registration-form');
    if (!$form.length) return;

    var emailPattern = /^[a-zA-Z0-9._%+\-]+@[a-zA-Z0-9.\-]+\.[a-zA-Z]{2,}$/;

    function showError($field, msg) {
        $field.closest('.easy-event-field').addClass('ee-field-error');
        $field.closest('.easy-event-field').find('.ee-field-msg').text(msg).show();
    }

    function clearError($field) {
        $field.closest('.easy-event-field').removeClass('ee-field-error');
        $field.closest('.easy-event-field').find('.ee-field-msg').text('').hide();
    }

    function validateName() {
        var $f = $('#ee-f-name');
        if (!$f.val().trim()) {
            showError($f, 'Dieses Feld darf nicht leer sein.');
            return false;
        }
        clearError($f);
        return true;
    }

    function validateEmail() {
        var $f  = $('#ee-f-email');
        var val = $f.val().trim();
        if (!val) {
            showError($f, 'Dieses Feld darf nicht leer sein.');
            return false;
        }
        if (!emailPattern.test(val)) {
            showError($f, 'Bitte eine gültige E-Mail-Adresse eingeben (z.B. name@beispiel.ch).');
            return false;
        }
        clearError($f);
        return true;
    }

    function validateGroup() {
        var $f = $('#ee-f-group');
        if (!$f.val()) {
            showError($f, 'Bitte eine Gruppe auswählen.');
            return false;
        }
        clearError($f);
        return true;
    }

    // Fehler beim Verlassen des Feldes
    $('#ee-f-name').on('blur', validateName);
    $('#ee-f-email').on('blur', validateEmail);
    $('#ee-f-group').on('change', validateGroup);

    // Eigene Validierung beim Absenden
    $form.on('submit', function (e) {
        var ok = true;
        if (!validateName())  ok = false;
        if (!validateEmail()) ok = false;
        if (!validateGroup()) ok = false;

        if (!ok) {
            e.preventDefault();
            var $first = $form.find('.ee-field-error').first();
            if ($first.length) {
                $('html, body').animate({ scrollTop: $first.offset().top - 60 }, 300);
            }
        }
        // ok === true → kein preventDefault → Formular wird normal abgeschickt
    });
});
