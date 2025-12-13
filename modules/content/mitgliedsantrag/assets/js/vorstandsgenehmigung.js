/**
 * Vorstandsgenehmigung JavaScript
 * Mitgliedsantrag Modul
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var $container = $('.dgptm-vorstandsgenehmigung-container');
        var $buttons = $('.dgptm-vg-btn');
        var $resultBox = $('.dgptm-vg-result');
        var $bemerkung = $('#dgptm-vg-bemerkung');
        var isProcessing = false;

        // Button Click Handler
        $buttons.on('click', function(e) {
            e.preventDefault();

            if (isProcessing) return;

            var $btn = $(this);
            var action = $btn.data('action');

            // Bestaetigung
            var confirmMsg = action === 'approve'
                ? dgptmVorstand.strings.confirm_approve
                : dgptmVorstand.strings.confirm_reject;

            if (!confirm(confirmMsg)) {
                return;
            }

            // Processing starten
            isProcessing = true;
            $buttons.prop('disabled', true);

            // Loading anzeigen
            $resultBox
                .removeClass('success error')
                .html('<div class="dgptm-vg-loading"><div class="dgptm-vg-spinner"></div><span>' + dgptmVorstand.strings.processing + '</span></div>')
                .show();

            // AJAX Request
            $.ajax({
                url: dgptmVorstand.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_vorstand_entscheidung',
                    nonce: dgptmVorstand.nonce,
                    antragsteller_id: dgptmVorstand.antragstellerId,
                    vorstand_id: dgptmVorstand.vorstandId,
                    entscheidung: action,
                    bemerkung: $bemerkung.val()
                },
                success: function(response) {
                    if (response.success) {
                        $resultBox
                            .removeClass('error')
                            .addClass('success')
                            .html('<p>' + response.data.message + '</p>');

                        // Buttons ausblenden
                        $('.dgptm-vg-buttons').hide();
                        $('.dgptm-vg-kommentar').hide();

                        // Erfolgreiche Abstimmung - Seite nach 3 Sekunden neu laden
                        setTimeout(function() {
                            window.location.reload();
                        }, 3000);
                    } else {
                        showError(response.data.message || dgptmVorstand.strings.error);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    showError(dgptmVorstand.strings.error);
                },
                complete: function() {
                    isProcessing = false;
                }
            });
        });

        function showError(message) {
            $buttons.prop('disabled', false);
            $resultBox
                .removeClass('success')
                .addClass('error')
                .html('<p>' + message + '</p>')
                .show();
        }
    });

})(jQuery);
