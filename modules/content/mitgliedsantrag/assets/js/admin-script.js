/**
 * DGPTM Mitgliedsantrag - Admin JavaScript
 * Handles webhook testing
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        console.log('DGPTM Mitgliedsantrag Admin Script loaded');
        console.log('Found test buttons:', $('.test-webhook-btn').length);
        console.log('Admin data:', dgptmMitgliedsantragAdmin);

        // Webhook test buttons (both simple and full)
        $('.test-webhook-btn').on('click', function(e) {
            e.preventDefault();
            console.log('Test button clicked');

            const $btn = $(this);
            const $result = $('#webhook-test-result');
            const originalText = $btn.text();
            const testType = $btn.data('test-type');

            // Disable ALL test buttons
            $('.test-webhook-btn').prop('disabled', true);

            const loadingText = testType === 'full' ?
                '⏳ Sende vollständige Testdaten...' :
                '⏳ Sende Verbindungstest...';

            $btn.text(loadingText);
            $result.html('<p style="color: #666;">' + loadingText + '</p>');

            console.log('Sending AJAX request:', {
                url: dgptmMitgliedsantragAdmin.ajaxUrl,
                test_type: testType
            });

            $.ajax({
                url: dgptmMitgliedsantragAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_test_webhook',
                    nonce: dgptmMitgliedsantragAdmin.nonce,
                    test_type: testType
                },
                success: function(response) {
                    console.log('AJAX Success:', response);
                    // Re-enable all buttons
                    $('.test-webhook-btn').prop('disabled', false);
                    $btn.text(originalText);

                    if (response.success) {
                        const testTypeLabel = response.data.test_type === 'full' ?
                            '<span style="background: #2271b1; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px;">VOLLSTÄNDIGER TEST</span>' :
                            '<span style="background: #666; color: white; padding: 2px 8px; border-radius: 3px; font-size: 11px;">VERBINDUNGSTEST</span>';

                        let html = '<div class="notice notice-success inline" style="margin: 0; padding: 15px;">';
                        html += '<p><strong>✅ ' + response.data.message + '</strong> ' + testTypeLabel + '</p>';
                        html += '<p><strong>HTTP Code:</strong> ' + response.data.http_code + '</p>';

                        if (response.data.response_body) {
                            html += '<p><strong>Server Response:</strong></p>';
                            html += '<pre style="background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto; max-height: 200px;">' +
                                    escapeHtml(response.data.response_body.substring(0, 1000)) +
                                    (response.data.response_body.length > 1000 ? '\n\n... (gekürzt)' : '') +
                                    '</pre>';
                        }

                        html += '<details style="margin-top: 15px;"><summary style="cursor: pointer; font-weight: bold;">📦 Gesendete Test-Daten anzeigen</summary>';
                        html += '<pre style="background: #f0f0f0; padding: 15px; border-radius: 3px; overflow-x: auto; margin-top: 10px; max-height: 400px; border: 1px solid #ddd;">' +
                                JSON.stringify(response.data.sent_payload, null, 2) +
                                '</pre>';

                        if (response.data.test_type === 'full') {
                            html += '<p style="margin-top: 10px; color: #135e96;"><strong>💡 Hinweis:</strong> Dies sind alle Felder, die ein echtes Formular sendet. Verwenden Sie diese Struktur für Ihre Webhook-Integration.</p>';
                        }

                        html += '</details>';
                        html += '</div>';

                        $result.html(html);
                    } else {
                        let html = '<div class="notice notice-error inline" style="margin: 0; padding: 10px;">';
                        html += '<p><strong>❌ ' + response.data.message + '</strong></p>';
                        html += '</div>';
                        $result.html(html);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {xhr: xhr, status: status, error: error});
                    console.error('Response text:', xhr.responseText);

                    // Re-enable all buttons
                    $('.test-webhook-btn').prop('disabled', false);
                    $btn.text(originalText);

                    let html = '<div class="notice notice-error inline" style="margin: 0; padding: 10px;">';
                    html += '<p><strong>❌ AJAX-Fehler:</strong> ' + error + '</p>';
                    html += '<p><strong>Status:</strong> ' + status + '</p>';
                    html += '<p><strong>HTTP Code:</strong> ' + xhr.status + '</p>';
                    if (xhr.responseText) {
                        html += '<p><strong>Server Response:</strong></p>';
                        html += '<pre style="background: #f5f5f5; padding: 10px; border-radius: 3px; overflow-x: auto;">' +
                                escapeHtml(xhr.responseText.substring(0, 500)) +
                                '</pre>';
                    }
                    html += '</div>';
                    $result.html(html);
                }
            });
        });
    });

    function escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, function(m) { return map[m]; });
    }

})(jQuery);
