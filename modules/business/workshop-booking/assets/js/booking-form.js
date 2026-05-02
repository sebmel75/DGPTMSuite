/**
 * DGPTM Workshop-Booking — Frontend-JS.
 *
 * - Klick auf "Jetzt buchen" oeffnet Dialog
 * - Submit ruft AJAX-Endpoint dgptm_wsb_book
 * - Bei Erfolg: Redirect zur Stripe-Checkout-URL (oder Bestaetigungsseite)
 */
(function ($) {
    'use strict';

    function getDialog() {
        return document.getElementById('dgptm-wsb-booking-dialog');
    }

    function openDialog() {
        var dlg = getDialog();
        if (dlg && typeof dlg.showModal === 'function') {
            dlg.showModal();
        } else if (dlg) {
            dlg.setAttribute('open', 'open');
            dlg.style.display = 'block';
        }
    }

    function closeDialog() {
        var dlg = getDialog();
        if (dlg && typeof dlg.close === 'function') {
            dlg.close();
        } else if (dlg) {
            dlg.removeAttribute('open');
            dlg.style.display = 'none';
        }
    }

    $(document).on('click', '.dgptm-wsb-card-book', function () {
        var $btn    = $(this);
        var $dialog = $(getDialog());
        $dialog.find('input[name="event_id"]').val($btn.data('event-id'));
        $dialog.find('.dgptm-wsb-dialog-event-name').text($btn.data('event-name'));
        $dialog.find('.dgptm-wsb-feedback').text('');
        $dialog.find('input[name="first_name"], input[name="last_name"], input[name="email"]').val('');
        openDialog();
    });

    $(document).on('click', '.dgptm-wsb-cancel', function () {
        closeDialog();
    });

    $(document).on('submit', '#dgptm-wsb-booking-form', function (e) {
        e.preventDefault();
        var $form   = $(this);
        var $submit = $form.find('.dgptm-wsb-submit');
        var $fb     = $form.find('.dgptm-wsb-feedback').text('Wird gesendet…');
        $submit.prop('disabled', true);

        var attendees = [{
            first_name: $form.find('input[name="first_name"]').val(),
            last_name:  $form.find('input[name="last_name"]').val(),
            email:      $form.find('input[name="email"]').val(),
            price_eur:  0
        }];

        $.post(dgptmWsb.ajaxUrl, {
            action:    'dgptm_wsb_book',
            nonce:     dgptmWsb.nonce,
            event_id:  $form.find('input[name="event_id"]').val(),
            attendees: JSON.stringify(attendees)
        }).done(function (resp) {
            if (resp && resp.success && resp.data && resp.data.redirect_url) {
                window.location.href = resp.data.redirect_url;
            } else {
                var msg = (resp && resp.data) ? resp.data : 'unbekannter Fehler';
                $fb.text('Buchung fehlgeschlagen: ' + msg);
                $submit.prop('disabled', false);
            }
        }).fail(function () {
            $fb.text('Netzwerkfehler. Bitte erneut versuchen.');
            $submit.prop('disabled', false);
        });
    });
})(jQuery);
