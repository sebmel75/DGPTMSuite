/**
 * Bürgenbestätigung JavaScript
 * Mitgliedsantrag Modul
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        var $buttons   = $('.dgptm-vg-btn');
        var $resultBox = $('.dgptm-vg-result');
        var $bemerkung = $('#dgptm-buerge-bemerkung');
        var isProcessing = false;

        $buttons.on('click', function(e) {
            e.preventDefault();
            if (isProcessing) return;

            var action = $(this).data('action');
            var confirmMsg = action === 'confirm'
                ? dgptmBuerge.strings.confirm_confirm
                : dgptmBuerge.strings.confirm_reject;

            if (!confirm(confirmMsg)) {
                return;
            }

            isProcessing = true;
            $buttons.prop('disabled', true);

            $resultBox
                .removeClass('success error')
                .html('<div class="dgptm-vg-loading"><div class="dgptm-vg-spinner"></div><span>' + dgptmBuerge.strings.processing + '</span></div>')
                .show();

            $.ajax({
                url: dgptmBuerge.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_buerge_entscheidung',
                    nonce: dgptmBuerge.nonce,
                    antragsteller_token: dgptmBuerge.antragstellerToken,
                    slot: dgptmBuerge.slot,
                    entscheidung: action,
                    bemerkung: $bemerkung.val()
                },
                success: function(response) {
                    if (response.success) {
                        $resultBox
                            .removeClass('error')
                            .addClass('success')
                            .html('<p>' + response.data.message + '</p>');
                        $('.dgptm-vg-buttons, .dgptm-vg-kommentar').hide();
                        setTimeout(function() { window.location.reload(); }, 3000);
                    } else {
                        showError((response.data && response.data.message) || dgptmBuerge.strings.error);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', status, error);
                    showError(dgptmBuerge.strings.error);
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
