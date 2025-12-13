(function($) {

    // Um Mehrfach-Auslösung zu verhindern, entfernen wir vorab jegliche
    // click-Handler auf .webhook-trigger-btn und fügen unseren EINMALIG hinzu.
    $(document).off('click', '.webhook-trigger-btn');

    $(document).on('click', '.webhook-trigger-btn', function(e) {
        e.preventDefault();

        const $btn       = $(this);
        const url        = $btn.data('url') || '';
        const method     = $btn.data('method') || 'POST';
        const successMsg = $btn.data('success-msg') || 'Erfolg!';
        const errorMsg   = $btn.data('error-msg')   || 'Fehler!';
        const statusId   = $btn.data('status-id')   || '';

        // Ausgabeelement finden
        let $output;
        if (statusId) {
            // Falls user [webhook_status_output id="XYZ"] hat
            $output = $('#' + statusId);
            // Fallback, falls DIV nicht existiert
            if (!$output.length) {
                $output = $btn.next('.webhook-output');
            }
        } else {
            // Keines angegeben -> wir nehmen das automatisch erstellte
            $output = $btn.next('.webhook-output');
        }

        // Sicherstellen, dass es existiert
        if (!$output.length) {
            alert('Kein Ausgabeelement gefunden!');
            return;
        }

        // Erste Meldung
        $output.html('<div class="webhook-message info">Anfrage wird gesendet...</div>');

        // Ajax an admin-ajax.php
        $.ajax({
            url: webhookAjax.ajax_url,
            method: 'POST',
            data: {
                action:       'webhook_trigger',
                url:          url,
                method:       method,
                success_msg:  successMsg,
                error_msg:    errorMsg
            },
            success: function(response) {
                if (response.success) {
                    // Erfolg
                    $output.html(response.data.message);
                } else {
                    // Fehler
                    // (z.B. WP-Error oder HTTP != 2xx)
                    $output.html(response.data.message);
                }
            },
            error: function() {
                $output.html('<div class="webhook-message error">Ajax-Fehler!</div>');
            }
        });
    });

})(jQuery);
