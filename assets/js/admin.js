/* Easy Event – Admin JS */
jQuery(function ($) {

    // ------------------------------------------------------------------
    // Tab-Reihenfolge und Hilfsfunktionen
    // ------------------------------------------------------------------

    // Aktive Tab-Reihenfolge dynamisch berechnen (abhängig von Checkboxen)
    function getTabOrder() {
        var order = ['ee-tab-details'];
        if ($('#ee-has-groups').is(':checked'))  order.push('ee-tab-groups');
        if ($('#ee-has-presale').is(':checked')) order.push('ee-tab-presale');
        order.push('ee-tab-email');
        return order;
    }
    function switchToTab(tabId) {
        $('.ee-tab-link').removeClass('active');
        $('.ee-tab-panel').hide();
        $('#' + tabId).show();
        $('a[href="#' + tabId + '"]').addClass('active');
        $('html, body').animate({ scrollTop: 0 }, 200);
    }

    // Manuelle Tab-Klicks (Übersicht)
    $('.ee-tab-link').on('click', function (e) {
        e.preventDefault();
        switchToTab( $(this).attr('href').replace('#', '') );
    });

    // Nach Redirect: Tab aus URL-Parameter öffnen und Parameter aus URL entfernen
    (function () {
        var params = new URLSearchParams(window.location.search);
        var tab = params.get('tab');
        var allTabs = ['ee-tab-details', 'ee-tab-groups', 'ee-tab-presale', 'ee-tab-email'];
        if (tab && allTabs.indexOf(tab) !== -1) {
            switchToTab(tab);
            params.delete('tab');
            var newUrl = window.location.pathname + (params.toString() ? '?' + params.toString() : '');
            if (window.history && window.history.replaceState) {
                window.history.replaceState({}, document.title, newUrl);
            }
        }
    }());

    // ------------------------------------------------------------------
    // Validierung pro Tab
    // ------------------------------------------------------------------

    // Zeigt Fehler direkt am Feld an
    function showFieldError($input, msg) {
        $input.addClass('ee-input-error');
        $input.siblings('.ee-field-hint').text(msg).show();
    }

    // Entfernt Fehler von einem Feld
    function clearFieldError($input) {
        $input.removeClass('ee-input-error');
        $input.siblings('.ee-field-hint').text('').hide();
    }

    // Validiert alle required-Felder eines Tabs, gibt true zurück wenn alles ok
    function validateTab(tabId) {
        var valid = true;

        if (tabId === 'ee-tab-groups') {
            // Gruppen-Tab: eigene Logik
            var groupError = '';
            if ($('#ee-groups-body .ee-group-row').length === 0) {
                groupError = 'Bitte mindestens eine Gruppe hinzufügen.';
                valid = false;
            } else {
                $('#ee-groups-body .ee-group-row').each(function (idx) {
                    var $nr  = $(this).find('input[name*="group_number"]');
                    var $max = $(this).find('input[name*="max_tickets"]');
                    $nr.removeClass('ee-input-error');
                    $max.removeClass('ee-input-error');

                    if (!$.trim($nr.val()) || parseInt($nr.val()) < 1) {
                        $nr.addClass('ee-input-error');
                        groupError = 'Alle Gruppen brauchen eine gültige Gruppe Nr.';
                        valid = false;
                    }
                    if (!$.trim($max.val()) || parseInt($max.val()) < 1) {
                        $max.addClass('ee-input-error');
                        groupError = groupError || 'Alle Gruppen brauchen einen Wert für Max. Teilnehmer.';
                        valid = false;
                    }
                });
            }
            if (!valid) {
                $('#ee-groups-error p').text(groupError);
                $('#ee-groups-error').show();
            } else {
                $('#ee-groups-error').hide();
                $('#ee-groups-body .ee-group-row input').removeClass('ee-input-error');
            }
            return valid;
        }

        // Alle anderen Tabs: required-Felder prüfen
        $('#' + tabId + ' [required]').each(function () {
            if (!$.trim($(this).val())) {
                showFieldError($(this), 'Dieses Feld ist erforderlich.');
                valid = false;
            } else {
                clearFieldError($(this));
            }
        });
        return valid;
    }

    // Fehler eines Tabs zurücksetzen
    function clearTabErrors(tabId) {
        $('#' + tabId + ' [required]').each(function () {
            clearFieldError($(this));
        });
        $('#ee-groups-error').hide();
        $('#ee-groups-body .ee-group-row input').removeClass('ee-input-error');
    }

    // Fehler sofort entfernen wenn Feld ausgefüllt wird
    $(document).on('input change', '[required]', function () {
        if ($.trim($(this).val())) {
            clearFieldError($(this));
        }
    });

    // ------------------------------------------------------------------
    // Weiter / Zurück Navigation
    // ------------------------------------------------------------------

    // Gruppen/Vorverkauf-Checkboxen: Tab-Nav ein-/ausblenden + Weiter-Button-Text aktualisieren
    function updateTabVisibility() {
        var hasGroups  = $('#ee-has-groups').is(':checked');
        var hasPresale = $('#ee-has-presale').is(':checked');
        $('#ee-tab-nav-groups').toggle(hasGroups);
        $('#ee-tab-nav-presale').toggle(hasPresale);

        // Weiter-Button-Text in Tab 1 anpassen
        var order = getTabOrder();
        var nextAfterDetails = order.length > 1 ? order[1] : null;
        var labels = {
            'ee-tab-groups':  'Weiter: Gruppen →',
            'ee-tab-presale': 'Weiter: Vorverkauf →',
            'ee-tab-email':   'Weiter: E-Mail →'
        };
        if (nextAfterDetails && labels[nextAfterDetails]) {
            $('#ee-tab-details .ee-tab-next').text(labels[nextAfterDetails]);
        }

        // Wenn aktuell aktiver Tab ausgeblendet wird → zu Details wechseln
        var activeTab = $('.ee-tab-panel:visible').attr('id');
        if ( (!hasGroups && activeTab === 'ee-tab-groups') ||
             (!hasPresale && activeTab === 'ee-tab-presale') ) {
            switchToTab('ee-tab-details');
        }
    }
    $('#ee-has-groups, #ee-has-presale').on('change', updateTabVisibility);
    updateTabVisibility(); // Initialzustand setzen

    var isEditMode = parseInt($('input[name="event_id"]').val(), 10) > 0;

    $(document).on('click', '.ee-tab-next', function () {
        var currentId = $(this).closest('.ee-tab-panel').attr('id');
        if (!validateTab(currentId)) return;

        clearTabErrors(currentId);
        var order   = getTabOrder();
        var idx     = order.indexOf(currentId);
        var nextTab = idx < order.length - 1 ? order[idx + 1] : null;
        if (!nextTab) return;

        if (!isEditMode) {
            switchToTab(nextTab);
            return;
        }

        var $btn     = $(this);
        var origText = $btn.text();

        $btn.prop('disabled', true).text('Wird gespeichert…');
        $('#ee-ajax-notice').hide();

        if (typeof tinymce !== 'undefined') {
            tinymce.triggerSave();
        }
        var formData = new FormData(document.getElementById('easy-event-form'));
        formData.append('action', 'ee_save_event');

        $.ajax({
            url:         eeAdmin.ajaxUrl,
            type:        'POST',
            data:        formData,
            processData: false,
            contentType: false,
        })
        .done(function (res) {
            if (res.success) {
                if (res.data.event_id) {
                    $('input[name="event_id"]').val(res.data.event_id);
                    isEditMode = true;
                }
                if (res.data.warnings && res.data.warnings.length) {
                    var html = '<strong>Gespeichert – aber mit Hinweisen:</strong><ul>';
                    $.each(res.data.warnings, function (i, w) {
                        html += '<li>' + $('<div>').text(w).html() + '</li>';
                    });
                    html += '</ul>';
                    $('#ee-ajax-notice').removeClass('notice-error').addClass('notice-warning').html(html).show();
                }
                switchToTab(nextTab);
            } else {
                var msg = (res.data && res.data.message)
                    ? $('<div>').text(res.data.message).html()
                    : 'Unbekannter Fehler.';
                $('#ee-ajax-notice')
                    .removeClass('notice-warning').addClass('notice-error')
                    .html('<strong>Fehler beim Speichern:</strong> ' + msg)
                    .show();
            }
        })
        .fail(function () {
            $('#ee-ajax-notice')
                .removeClass('notice-warning').addClass('notice-error')
                .html('<strong>Verbindungsfehler.</strong> Bitte erneut versuchen.')
                .show();
        })
        .always(function () {
            $btn.prop('disabled', false).text(origText);
        });
    });

    $(document).on('click', '.ee-tab-prev', function () {
        var currentId = $(this).closest('.ee-tab-panel').attr('id');
        var order = getTabOrder();
        var idx   = order.indexOf(currentId);
        if (idx > 0) {
            switchToTab(order[idx - 1]);
        }
    });

    // ------------------------------------------------------------------
    // Finale Validierung beim Absenden (Fallback, falls jemand direkt speichert)
    // ------------------------------------------------------------------
    $('#easy-event-form').on('submit', function (e) {
        var firstInvalidTab = null;
        getTabOrder().forEach(function (tabId) {
            if (!validateTab(tabId) && !firstInvalidTab) {
                firstInvalidTab = tabId;
            }
        });
        if (firstInvalidTab) {
            e.preventDefault();
            switchToTab(firstInvalidTab);
        }
    });

    // ------------------------------------------------------------------
    // Gruppen – dynamische Zeilen
    // ------------------------------------------------------------------

    function reindexGroups() {
        $('#ee-groups-body .ee-group-row').each(function (idx) {
            $(this).find('input').each(function () {
                var name = $(this).attr('name');
                if (name) {
                    $(this).attr('name', name.replace(/groups\[\d+\]/, 'groups[' + idx + ']'));
                }
            });
        });
    }

    function newGroupRow() {
        var i = $('#ee-groups-body .ee-group-row').length;
        var row = '<tr class="ee-group-row">' +
            '<td><input type="hidden" name="groups[' + i + '][id]" value="0">' +
            '<input type="number" name="groups[' + i + '][group_number]" value="' + (i + 1) + '" min="1" class="small-text" required></td>' +
            '<td><input type="text" name="groups[' + i + '][description]" class="regular-text" style="width:100%"></td>' +
            '<td><input type="number" name="groups[' + i + '][max_tickets]" value="10" min="1" class="small-text" required></td>' +
            '<td><button type="button" class="button ee-remove-group">Entfernen</button></td>' +
            '</tr>';
        $('#ee-groups-body').append(row);
    }

    if ($('#ee-groups-body .ee-group-row').length === 0) {
        newGroupRow();
    }

    $('#ee-add-group').on('click', function () {
        newGroupRow();
        reindexGroups();
        $('#ee-groups-error').hide();
    });

    $(document).on('click', '.ee-remove-group', function () {
        $(this).closest('tr').remove();
        reindexGroups();
    });

    // ------------------------------------------------------------------
    // Platzhalter klickbar: in zuletzt fokussiertes Feld einfügen
    // ------------------------------------------------------------------
    var lastFocusedField = null;

    $(document).on('focus', 'input[type="text"], input[type="email"], textarea', function () {
        lastFocusedField = this;
    });

    $(document).on('click', '.ee-placeholder', function (e) {
        e.preventDefault();
        var value = $(this).data('value');

        if (typeof tinymce !== 'undefined' && tinymce.activeEditor && !tinymce.activeEditor.isHidden()) {
            tinymce.activeEditor.insertContent(value);
            return;
        }

        if (lastFocusedField) {
            var el    = lastFocusedField;
            var start = el.selectionStart;
            var end   = el.selectionEnd;
            var text  = el.value;
            el.value  = text.substring(0, start) + value + text.substring(end);
            el.selectionStart = el.selectionEnd = start + value.length;
            el.focus();
        } else {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(value);
                var $c = $(this);
                $c.attr('title', 'Kopiert!');
                setTimeout(function () { $c.attr('title', 'Klicken zum Einfügen'); }, 1500);
            }
        }
    });

    // ------------------------------------------------------------------
    // Anmeldungen-Filter: Event-Dropdown auto-submit
    // ------------------------------------------------------------------
    $('#ee-event-filter').on('change', function () {
        $(this).closest('form').submit();
    });

    // ------------------------------------------------------------------
    // Test-E-Mail via AJAX senden
    // ------------------------------------------------------------------
    $('#ee-send-test').on('click', function () {
        var $btn    = $(this);
        var $result = $('#ee-test-result');
        var to      = $('#ee-test-email').val().trim();
        var eventId = $('input[name="event_id"]').val();

        if (!to) {
            $result.text('Bitte eine E-Mail-Adresse eingeben.').css('color', '#d63638');
            return;
        }

        $btn.prop('disabled', true).text('Wird gesendet…');
        $result.text('').css('color', '');

        $.post(eeAdmin.ajaxUrl, {
            action:     'ee_send_test_email',
            nonce:      eeAdmin.nonce,
            test_email: to,
            event_id:   eventId
        })
        .done(function (res) {
            if (res.success) {
                $result.text('✓ ' + res.data).css('color', '#00a32a');
            } else {
                $result.text('✗ ' + res.data).css('color', '#d63638');
            }
        })
        .fail(function () {
            $result.text('✗ Verbindungsfehler.').css('color', '#d63638');
        })
        .always(function () {
            $btn.prop('disabled', false).text('Senden');
        });
    });
});
