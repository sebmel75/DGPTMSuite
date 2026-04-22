(function ($) {
    'use strict';

    var config = window.dgptmWsbEvl || {};

    /* Toggle Kommentarpanel */
    $(document).on('click', '.dgptm-wsb-evl-comments-toggle', function () {
        $(this).next('.dgptm-wsb-evl-comments-panel').slideToggle(200);
        $(this).toggleClass('open');
    });

    /* Kommentar hinzufuegen */
    $(document).on('click', '.dgptm-wsb-evl-comment-submit', function () {
        var $block  = $(this).closest('.dgptm-wsb-evl-comments-block');
        var section = $block.data('section');
        var $input  = $block.find('.dgptm-wsb-evl-comment-input');
        var text    = $.trim($input.val());

        if (!text) { $input.focus(); return; }

        var $btn = $(this);
        $btn.prop('disabled', true).text('Wird gesendet...');

        $.post(config.ajaxUrl, {
            action:  'dgptm_wsb_evl_comment',
            nonce:   config.nonce,
            section: section,
            comment: text
        }, function (res) {
            if (res.success) {
                $block.find('.dgptm-wsb-evl-comments-list').append(res.data.html);
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

    /* Enter = Senden, Shift+Enter = Zeilenumbruch */
    $(document).on('keydown', '.dgptm-wsb-evl-comment-input', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            $(this).closest('.dgptm-wsb-evl-comment-form')
                   .find('.dgptm-wsb-evl-comment-submit').trigger('click');
        }
    });

    /* Kommentar loeschen */
    $(document).on('click', '.dgptm-wsb-evl-comment-delete', function () {
        if (!confirm('Kommentar wirklich loeschen?')) return;

        var $comment = $(this).closest('.dgptm-wsb-evl-comment');
        var $block   = $(this).closest('.dgptm-wsb-evl-comments-block');
        var commentId = $(this).data('id');

        $.post(config.ajaxUrl, {
            action:     'dgptm_wsb_evl_delete_comment',
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
        var count = $block.find('.dgptm-wsb-evl-comment').length;
        $block.find('.dgptm-wsb-evl-comments-count').text(count);
    }

    /* Als eingearbeitet markieren (Admin) */
    $(document).on('click', '.dgptm-wsb-evl-comment-mark-read', function () {
        var $btn = $(this);
        var commentId = $btn.data('id');
        var $comment  = $btn.closest('.dgptm-wsb-evl-comment');

        $btn.prop('disabled', true).text('...');

        $.post(config.ajaxUrl, {
            action:      'dgptm_wsb_evl_mark_read',
            nonce:       config.nonce,
            comment_ids: JSON.stringify([commentId])
        }, function (res) {
            if (res.success) {
                $comment.addClass('dgptm-wsb-evl-comment-read');
                $btn.replaceWith('<span class="dgptm-wsb-evl-badge-eingearbeitet">eingearbeitet</span>');
                $comment.find('.dgptm-wsb-evl-comment-delete').remove();
            } else {
                alert(res.data || 'Fehler.');
                $btn.prop('disabled', false).html('&#10003; eingearbeitet');
            }
        });
    });

    /* Freigabe erteilen */
    $(document).on('click', '#dgptm-wsb-evl-approve, #dgptm-wsb-evl-approve-footer', function () {
        if (!confirm('Moechten Sie diese Entscheidungsvorlage freigeben? Sie koennen die Freigabe spaeter wieder zurueckziehen.')) return;

        var $btn = $(this);
        $btn.prop('disabled', true).text('Wird gespeichert...');

        $.post(config.ajaxUrl, {
            action: 'dgptm_wsb_evl_approve',
            nonce:  config.nonce
        }, function (res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data || 'Fehler bei der Freigabe.');
                $btn.prop('disabled', false).text('Entscheidungsvorlage freigeben');
            }
        }).fail(function () {
            alert('Verbindungsfehler.');
            $btn.prop('disabled', false).text('Entscheidungsvorlage freigeben');
        });
    });

    /* Freigabe zurueckziehen */
    $(document).on('click', '#dgptm-wsb-evl-revoke', function () {
        if (!confirm('Freigabe wirklich zurueckziehen?')) return;

        $.post(config.ajaxUrl, {
            action: 'dgptm_wsb_evl_revoke',
            nonce:  config.nonce
        }, function (res) {
            if (res.success) {
                location.reload();
            } else {
                alert(res.data || 'Fehler.');
            }
        });
    });

})(jQuery);
