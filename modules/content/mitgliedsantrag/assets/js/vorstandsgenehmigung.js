/**
 * Vorstandsgenehmigung JavaScript
 * Mitgliedsantrag Modul
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var $buttons   = $('.dgptm-vg-btn');
        var $resultBox = $('.dgptm-vg-result');
        var $bemerkung = $('#dgptm-vg-bemerkung');
        var $vorstand  = $('#dgptm-vg-vorstand');
        var isProcessing = false;

        $buttons.on('click', function(e) {
            e.preventDefault();
            if (isProcessing) return;

            var action     = $(this).data('action');
            var vorstandId = ($vorstand.val() || '').trim();

            if (!vorstandId) {
                showError(dgptmVorstand.strings.select_vorstand);
                $vorstand.focus();
                return;
            }

            var confirmMsg = action === 'approve'
                ? dgptmVorstand.strings.confirm_approve
                : dgptmVorstand.strings.confirm_reject;

            if (!confirm(confirmMsg)) {
                return;
            }

            isProcessing = true;
            $buttons.prop('disabled', true);

            $resultBox
                .removeClass('success error')
                .html('<div class="dgptm-vg-loading"><div class="dgptm-vg-spinner"></div><span>' + dgptmVorstand.strings.processing + '</span></div>')
                .show();

            $.ajax({
                url: dgptmVorstand.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_vorstand_entscheidung',
                    nonce: dgptmVorstand.nonce,
                    antragsteller_token: dgptmVorstand.antragstellerToken,
                    vorstand_id: vorstandId,
                    entscheidung: action,
                    bemerkung: $bemerkung.val(),
                    skip_status_check: dgptmVorstand.skipStatusCheck || 0
                },
                success: function(response) {
                    if (response.success) {
                        $resultBox
                            .removeClass('error')
                            .addClass('success')
                            .html('<p>' + response.data.message + '</p>');
                        $('.dgptm-vg-buttons, .dgptm-vg-kommentar, .dgptm-vg-select').hide();
                        setTimeout(function() { window.location.reload(); }, 3000);
                    } else {
                        showError((response.data && response.data.message) || dgptmVorstand.strings.error);
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
