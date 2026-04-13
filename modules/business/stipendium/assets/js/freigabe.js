(function ($) {
    'use strict';

    var config = window.dgptmFreigabe || {};

    /* ──────────────────────────────────────────────
     * Countdown
     * ────────────────────────────────────────────── */

    function initCountdown() {
        var $el = $('#dgptm-freigabe-countdown');
        if (!$el.length) return;

        var deadline = new Date($el.data('deadline')).getTime();

        function update() {
            var now  = Date.now();
            var diff = deadline - now;

            if (diff <= 0) {
                $el.find('.countdown-value').text('Frist abgelaufen');
                $el.addClass('expired');
                return;
            }

            var days    = Math.floor(diff / 86400000);
            var hours   = Math.floor((diff % 86400000) / 3600000);
            var minutes = Math.floor((diff % 3600000) / 60000);

            var text = '';
            if (days > 0) text += days + (days === 1 ? ' Tag' : ' Tage') + ', ';
            text += hours + (hours === 1 ? ' Stunde' : ' Stunden') + ', ';
            text += minutes + (minutes === 1 ? ' Minute' : ' Minuten');

            $el.find('.countdown-value').text(text);

            if (days <= 1) {
                $el.addClass('urgent');
            } else if (days <= 3) {
                $el.addClass('warning');
            }
        }

        update();
        setInterval(update, 60000); // jede Minute aktualisieren
    }

    /* ──────────────────────────────────────────────
     * Kommentare: Toggle (standardmaessig zugeklappt)
     * ────────────────────────────────────────────── */

    $(document).on('click', '.dgptm-freigabe-comments-toggle', function () {
        var $panel = $(this).next('.dgptm-freigabe-comments-panel');
        $panel.slideToggle(200);
        $(this).toggleClass('open');
    });

    /* ──────────────────────────────────────────────
     * Kommentare: Hinzufuegen
     * ────────────────────────────────────────────── */

    $(document).on('click', '.dgptm-freigabe-comment-submit', function () {
        var $block   = $(this).closest('.dgptm-freigabe-comments-block');
        var section  = $block.data('section');
        var $input   = $block.find('.dgptm-freigabe-comment-input');
        var text     = $.trim($input.val());

        if (!text) {
            $input.focus();
            return;
        }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Wird gesendet...');

        $.post(config.ajaxUrl, {
            action:  'dgptm_freigabe_comment',
            nonce:   config.nonce,
            section: section,
            comment: text
        }, function (res) {
            if (res.success) {
                $block.find('.dgptm-freigabe-comments-list').append(res.data.html);
                $input.val('');
                updateCommentCount($block);
            } else {
                alert(res.data || 'Fehler beim Speichern.');
            }
        }).fail(function () {
            alert('Verbindungsfehler. Bitte erneut versuchen.');
        }).always(function () {
            $btn.prop('disabled', false).text('Kommentar senden');
        });
    });

    // Enter-Taste in Textarea: Shift+Enter fuer Umbruch, Enter zum Senden
    $(document).on('keydown', '.dgptm-freigabe-comment-input', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            $(this).closest('.dgptm-freigabe-comment-form')
                   .find('.dgptm-freigabe-comment-submit').trigger('click');
        }
    });

    /* ──────────────────────────────────────────────
     * Kommentare: Loeschen
     * ────────────────────────────────────────────── */

    $(document).on('click', '.dgptm-freigabe-comment-delete', function () {
        if (!confirm('Kommentar wirklich loeschen?')) return;

        var $comment = $(this).closest('.dgptm-freigabe-comment');
        var $block   = $(this).closest('.dgptm-freigabe-comments-block');
        var commentId = $(this).data('id');

        $.post(config.ajaxUrl, {
            action:     'dgptm_freigabe_delete_comment',
            nonce:      config.nonce,
            comment_id: commentId
        }, function (res) {
            if (res.success) {
                $comment.slideUp(200, function () {
                    $(this).remove();
                    updateCommentCount($block);
                });
            } else {
                alert(res.data || 'Fehler beim Loeschen.');
            }
        });
    });

    function updateCommentCount($block) {
        var count = $block.find('.dgptm-freigabe-comment').length;
        $block.find('.dgptm-freigabe-comments-count').text(count);
    }

    /* ──────────────────────────────────────────────
     * Kommentare: Als eingelesen markieren (nur Admin)
     * ────────────────────────────────────────────── */

    $(document).on('click', '.dgptm-freigabe-comment-mark-read', function () {
        var $btn = $(this);
        var commentId = $btn.data('id');
        var $comment = $btn.closest('.dgptm-freigabe-comment');

        $btn.prop('disabled', true).text('...');

        $.post(config.ajaxUrl, {
            action:      'dgptm_freigabe_mark_read',
            nonce:       config.nonce,
            comment_ids: JSON.stringify([commentId])
        }, function (res) {
            if (res.success) {
                $comment.addClass('dgptm-freigabe-comment-read');
                $btn.replaceWith('<span class="dgptm-badge-eingelesen">eingearbeitet</span>');
                // Loeschen-Button entfernen
                $comment.find('.dgptm-freigabe-comment-delete').remove();
            } else {
                alert(res.data || 'Fehler.');
                $btn.prop('disabled', false).html('&#10003; eingearbeitet');
            }
        });
    });

    /* ──────────────────────────────────────────────
     * Freigabe: Erteilen
     * ────────────────────────────────────────────── */

    $(document).on('click', '#dgptm-freigabe-approve, #dgptm-freigabe-approve-footer', function () {
        if (!confirm('Moechten Sie dieses Konzept freigeben? Sie koennen die Freigabe spaeter wieder zurueckziehen.')) return;

        var $btn = $(this);
        $btn.prop('disabled', true).text('Wird gespeichert...');

        $.post(config.ajaxUrl, {
            action: 'dgptm_freigabe_approve',
            nonce:  config.nonce
        }, function (res) {
            if (res.success) {
                // Seite neu laden um Zustand korrekt darzustellen
                location.reload();
            } else {
                alert(res.data || 'Fehler bei der Freigabe.');
                $btn.prop('disabled', false).text('Dokument freigeben');
            }
        }).fail(function () {
            alert('Verbindungsfehler.');
            $btn.prop('disabled', false).text('Dokument freigeben');
        });
    });

    /* ──────────────────────────────────────────────
     * Freigabe: Zurueckziehen
     * ────────────────────────────────────────────── */

    $(document).on('click', '#dgptm-freigabe-revoke', function () {
        if (!confirm('Freigabe wirklich zurueckziehen?')) return;

        $.post(config.ajaxUrl, {
            action: 'dgptm_freigabe_revoke',
            nonce:  config.nonce
        }, function (res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data || 'Fehler.');
            }
        });
    });

    /* ──────────────────────────────────────────────
     * Init
     * ────────────────────────────────────────────── */

    $(document).ready(function () {
        initCountdown();
    });

})(jQuery);
