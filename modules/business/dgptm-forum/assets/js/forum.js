(function($) {
    'use strict';

    /* ==========================================================
     * DGPTM Forum — Klassische Forumnavigation
     * Hauptgruppe → Threads → Thread + Antworten
     * Alle Handler via $(document).on() für Dashboard-Kompatibilität
     * ========================================================== */

    var F = {
        currentAgId: 0,
        currentThreadId: 0,

        ajax: function(action, data, cb) {
            if (typeof data === 'string') {
                data += '&action=dgptm_forum_' + action + '&nonce=' + dgptmForum.nonce;
            } else {
                data = data || {};
                data.action = 'dgptm_forum_' + action;
                data.nonce = dgptmForum.nonce;
            }
            $.post(dgptmForum.ajaxUrl, data).done(function(r) {
                if (cb) cb(r);
            }).fail(function() {
                F.notify('Verbindungsfehler', 'error');
            });
        },

        $content: function() { return $('.dgptm-forum-content'); },

        init: function() {
            if ($('.dgptm-forum-wrap').length) {
                F.loadView('ags');
            }
        },

        loadView: function(view, id) {
            F.$content().html('<div class="dgptm-forum-loading">Wird geladen\u2026</div>');
            if (view === 'threads') F.currentAgId = id;
            if (view === 'thread') F.currentThreadId = id;
            F.ajax('load_view', { view: view, id: id || 0 }, function(r) {
                if (r && r.success) {
                    F.$content().html(r.data.html);
                    F.updateBreadcrumb(r.data.breadcrumb || []);
                } else {
                    F.$content().html('<p style="color:red">' + ((r && r.data && r.data.message) || 'Fehler') + '</p>');
                }
            });
        },

        updateBreadcrumb: function(crumbs) {
            var $bc = $('.dgptm-forum-breadcrumb');
            if (!$bc.length) return;
            var html = '';
            for (var i = 0; i < crumbs.length; i++) {
                if (i > 0) html += ' <span class="sep">\u203A</span> ';
                if (crumbs[i].link) {
                    html += '<a href="#" data-view="' + crumbs[i].view + '" data-id="' + (crumbs[i].id || 0) + '">' + crumbs[i].label + '</a>';
                } else {
                    html += '<span>' + crumbs[i].label + '</span>';
                }
            }
            $bc.html(html);
        },

        notify: function(msg, type) {
            var bg = type === 'error' ? '#fce4ec' : (type === 'success' ? '#e7f5e7' : '#f0f0f0');
            var color = type === 'error' ? '#c62828' : (type === 'success' ? '#2e7d32' : '#333');
            var $n = $('<div style="padding:10px 14px;margin-bottom:12px;border-radius:4px;background:' + bg + ';color:' + color + ';font-size:13px">' + msg + '</div>');
            F.$content().prepend($n);
            setTimeout(function() { $n.fadeOut(300, function() { $n.remove(); }); }, 3000);
        }
    };

    // ===========================================================
    // Navigation: Hauptgruppe → Threads → Thread
    // ===========================================================

    // Klick auf Hauptgruppe → Threads laden
    $(document).on('click', '.dgptm-forum-ag-link', function(e) {
        e.preventDefault();
        F.loadView('threads', $(this).data('ag-id'));
    });

    // Klick auf Thread → Thread-Detail laden
    $(document).on('click', '.dgptm-forum-thread-link', function(e) {
        e.preventDefault();
        F.loadView('thread', $(this).data('thread-id'));
    });

    // Breadcrumb-Navigation
    $(document).on('click', '.dgptm-forum-breadcrumb a', function(e) {
        e.preventDefault();
        F.loadView($(this).data('view'), $(this).data('id'));
    });

    // ===========================================================
    // Thread erstellen
    // ===========================================================

    $(document).on('click', '.dgptm-forum-new-thread-btn', function(e) {
        e.preventDefault();
        var $area = $('#dgptm-forum-compose-thread');
        if ($area.is(':visible')) { $area.slideUp(); return; }
        $area.slideDown();
    });

    $(document).on('click', '.dgptm-forum-cancel-compose', function(e) {
        e.preventDefault();
        $('#dgptm-forum-compose-thread').slideUp();
    });

    $(document).on('submit', '.dgptm-forum-thread-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var fd = new FormData($form[0]);
        fd.append('action', 'dgptm_forum_create_thread');
        fd.append('nonce', dgptmForum.nonce);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true);
        $.ajax({
            url: dgptmForum.ajaxUrl, type: 'POST', data: fd,
            processData: false, contentType: false
        }).done(function(r) {
            if (r && r.success) {
                F.loadView('thread', r.data.thread_id);
                F.notify('Thread erstellt', 'success');
            } else {
                F.notify((r && r.data && r.data.message) || 'Fehler', 'error');
                $btn.prop('disabled', false);
            }
        }).fail(function() { F.notify('Verbindungsfehler', 'error'); $btn.prop('disabled', false); });
    });

    // ===========================================================
    // Antworten
    // ===========================================================

    $(document).on('click', '.dgptm-forum-reply-btn', function(e) {
        e.preventDefault();
        var parentId = $(this).data('parent-id') || 0;
        var depth = parseInt($(this).data('depth') || 0) + 1;
        if (depth > 3) { F.notify('Maximale Verschachtelungstiefe erreicht.', 'error'); return; }
        var threadId = $(this).data('thread-id');
        $('.dgptm-forum-reply-form-inline').remove();
        var indent = Math.min(depth * 25, 75);
        var html = '<div class="dgptm-forum-reply-form-inline" style="margin:8px 0 8px ' + indent + 'px">';
        html += '<form class="dgptm-forum-reply-form">';
        html += '<input type="hidden" name="thread_id" value="' + threadId + '">';
        html += '<input type="hidden" name="parent_id" value="' + parentId + '">';
        html += '<input type="hidden" name="depth" value="' + depth + '">';
        html += '<textarea name="content" rows="3" placeholder="Antwort schreiben\u2026" style="width:100%;margin-bottom:6px;padding:8px;font-size:13px;border:1px solid #ccc;border-radius:4px"></textarea>';
        html += '<input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.docx" style="margin-bottom:6px;font-size:12px">';
        html += '<div><button type="submit" class="dgptm-forum-btn" style="font-size:12px;padding:4px 12px">Antworten</button> ';
        html += '<a href="#" class="dgptm-forum-cancel-reply" style="font-size:12px;color:#666">Abbrechen</a></div>';
        html += '</form></div>';
        $(this).closest('.dgptm-forum-post').after(html);
    });

    $(document).on('click', '.dgptm-forum-cancel-reply', function(e) {
        e.preventDefault();
        $(this).closest('.dgptm-forum-reply-form-inline').remove();
    });

    $(document).on('submit', '.dgptm-forum-reply-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var fd = new FormData($form[0]);
        fd.append('action', 'dgptm_forum_create_reply');
        fd.append('nonce', dgptmForum.nonce);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true);
        $.ajax({
            url: dgptmForum.ajaxUrl, type: 'POST', data: fd,
            processData: false, contentType: false
        }).done(function(r) {
            if (r && r.success) {
                F.loadView('thread', F.currentThreadId);
                F.notify('Antwort gesendet', 'success');
            } else {
                F.notify((r && r.data && r.data.message) || 'Fehler', 'error');
                $btn.prop('disabled', false);
            }
        }).fail(function() { F.notify('Verbindungsfehler', 'error'); $btn.prop('disabled', false); });
    });

    // ===========================================================
    // Bearbeiten / Löschen
    // ===========================================================

    $(document).on('click', '.dgptm-forum-edit-btn', function(e) {
        e.preventDefault();
        var $post = $(this).closest('.dgptm-forum-post');
        var postId = $(this).data('post-id');
        var postType = $(this).data('post-type');
        var $content = $post.find('.post-content');
        $content.html(
            '<textarea style="width:100%;min-height:80px;padding:8px;font-size:13px;border:1px solid #ccc;border-radius:4px">' + $content.html() + '</textarea>' +
            '<div style="margin-top:6px"><button class="dgptm-forum-btn dgptm-forum-save-edit" data-post-id="' + postId + '" data-post-type="' + postType + '" style="font-size:12px;padding:4px 12px">Speichern</button> ' +
            '<a href="#" class="dgptm-forum-cancel-edit" style="font-size:12px;color:#666">Abbrechen</a></div>'
        );
    });

    $(document).on('click', '.dgptm-forum-save-edit', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var content = $btn.closest('.post-content').find('textarea').val();
        F.ajax('edit_post', { post_id: $btn.data('post-id'), post_type: $btn.data('post-type'), content: content }, function(r) {
            if (r && r.success) { F.loadView('thread', F.currentThreadId); F.notify('Gespeichert', 'success'); }
            else { F.notify((r && r.data && r.data.message) || 'Fehler', 'error'); }
        });
    });

    $(document).on('click', '.dgptm-forum-cancel-edit', function(e) {
        e.preventDefault();
        F.loadView('thread', F.currentThreadId);
    });

    $(document).on('click', '.dgptm-forum-delete-btn', function(e) {
        e.preventDefault();
        if (!confirm('Beitrag wirklich l\u00f6schen?')) return;
        var postType = $(this).data('post-type');
        F.ajax('delete_post', { post_id: $(this).data('post-id'), post_type: postType }, function(r) {
            if (r && r.success) {
                if (postType === 'thread') F.loadView('threads', F.currentAgId);
                else F.loadView('thread', F.currentThreadId);
                F.notify('Gel\u00f6scht', 'success');
            } else { F.notify((r && r.data && r.data.message) || 'Fehler', 'error'); }
        });
    });

    // ===========================================================
    // Abonnieren / Moderationstools
    // ===========================================================

    $(document).on('click', '.dgptm-forum-subscribe-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var subscribed = $btn.hasClass('subscribed');
        F.ajax(subscribed ? 'unsubscribe' : 'subscribe', { scope: $btn.data('scope'), scope_id: $btn.data('scope-id') }, function(r) {
            if (r && r.success) { $btn.toggleClass('subscribed'); $btn.text(subscribed ? 'Abonnieren' : 'Abonniert \u2713'); }
        });
    });

    $(document).on('click', '.dgptm-forum-pin-btn', function(e) {
        e.preventDefault();
        F.ajax('admin_toggle_pin', { thread_id: $(this).data('thread-id') }, function(r) {
            if (r && r.success) F.loadView('thread', F.currentThreadId);
        });
    });

    $(document).on('click', '.dgptm-forum-close-btn', function(e) {
        e.preventDefault();
        F.ajax('admin_close_thread', { thread_id: $(this).data('thread-id') }, function(r) {
            if (r && r.success) F.loadView('thread', F.currentThreadId);
        });
    });

    // Mitgliedschaft beantragen
    $(document).on('click', '.dgptm-forum-request-membership', function(e) {
        e.preventDefault();
        var $btn = $(this).prop('disabled', true);
        F.ajax('request_membership', { ag_id: $btn.data('ag-id') }, function(r) {
            if (r && r.success) { $btn.text('Anfrage gesendet').removeClass('dgptm-forum-btn'); }
            else { F.notify((r && r.data && r.data.message) || 'Fehler', 'error'); $btn.prop('disabled', false); }
        });
    });

    // ===========================================================
    // Init + Dashboard Re-Init
    // ===========================================================

    $(document).ready(function() { F.init(); });
    $(document).on('dgptm_tab_loaded', function() { F.init(); });

})(jQuery);
