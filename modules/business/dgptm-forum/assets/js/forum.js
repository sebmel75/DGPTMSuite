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
            var $w = $('.dgptm-forum-wrap');
            if (!$w.length) return;
            var deepThread = parseInt($w.data('deep-thread') || 0);
            if (deepThread > 0) {
                F.loadView('thread', deepThread);
            } else {
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

    // Klick auf Thread-Vorschau in Hauptgruppen-Karte → direkt Thread öffnen
    $(document).on('click', '.dgptm-forum-thread-preview', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Verhindert Klick auf AG-Karte
        F.loadView('thread', $(this).data('thread-id'));
    });

    // Breadcrumb + Zurück-Navigation
    $(document).on('click', '.dgptm-forum-breadcrumb a, .dgptm-forum-back-btn', function(e) {
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
                // Feature 3: Show mention warnings
                if (r.data.warnings && r.data.warnings.length) {
                    for (var i = 0; i < r.data.warnings.length; i++) {
                        F.notify(r.data.warnings[i], 'info');
                    }
                }
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
        // Bei max Tiefe: flach unter dem Thread antworten
        if (depth > 3) { depth = 3; }
        var threadId = $(this).data('thread-id');
        $('.dgptm-forum-reply-form-inline').remove();
        var indent = Math.min(depth * 20, 60);
        var html = '<div class="dgptm-forum-reply-form-inline" style="margin:4px 0 4px ' + indent + 'px">';
        html += '<form class="dgptm-forum-reply-form" style="background:#f8f9fa;padding:8px 10px;border-radius:4px;border:1px solid #eee">';
        html += '<input type="hidden" name="thread_id" value="' + threadId + '">';
        html += '<input type="hidden" name="parent_id" value="' + parentId + '">';
        html += '<input type="hidden" name="depth" value="' + depth + '">';
        html += '<textarea name="content" rows="2" placeholder="Antwort\u2026" style="width:100%;margin-bottom:4px;padding:6px;font-size:12px;border:1px solid #ddd;border-radius:3px"></textarea>';
        html += '<div style="display:flex;justify-content:space-between;align-items:center">';
        html += '<input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.docx" style="font-size:10px">';
        html += '<div><button type="submit" class="dgptm-forum-btn dgptm-forum-btn-sm">Senden</button> ';
        html += '<a href="#" class="dgptm-forum-cancel-reply" style="font-size:10px;color:#999">Abbrechen</a></div></div>';
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
                // Feature 3: Show mention warnings
                if (r.data.warnings && r.data.warnings.length) {
                    for (var i = 0; i < r.data.warnings.length; i++) {
                        F.notify(r.data.warnings[i], 'info');
                    }
                }
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
            if (r && r.success) {
                $btn.toggleClass('subscribed');
                if (subscribed) {
                    $btn.html('\uD83D\uDD15 Abonnieren').css('color', '#999');
                } else {
                    $btn.html('\uD83D\uDD14 Abonniert').css('color', '#0073aa');
                    F.notify('E-Mail-Benachrichtigung aktiviert', 'success');
                }
            }
        });
    });

    $(document).on('click', '.dgptm-forum-toggle-pin-btn', function(e) {
        e.preventDefault();
        F.ajax('admin_toggle_pin', { thread_id: $(this).data('thread-id') }, function(r) {
            if (r && r.success) F.loadView('thread', F.currentThreadId);
        });
    });

    $(document).on('click', '.dgptm-forum-close-thread-btn', function(e) {
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
    // Feature 4: Blacklist Toggle (Frontend)
    // ===========================================================

    $(document).on('click', '.dgptm-forum-toggle-blacklist', function(e) {
        e.preventDefault();
        var action = $(this).data('action'); // 'enable' or 'disable'
        F.ajax('toggle_blacklist', { blacklist_action: action }, function(r) {
            if (r && r.success) {
                F.notify(r.data.message, 'success');
                // Reload ags view to update the toggle link
                F.loadView('ags');
            } else {
                F.notify((r && r.data && r.data.message) || 'Fehler', 'error');
            }
        });
    });

    // ===========================================================
    // @Mention Autocomplete
    // ===========================================================

    var mentionTimer, $mentionDropdown = null;

    $(document).on('input', '.dgptm-forum-thread-form textarea, .dgptm-forum-reply-form textarea', function() {
        var $ta = $(this);
        var val = $ta.val();
        var pos = $ta[0].selectionStart;
        // Text vor dem Cursor
        var before = val.substring(0, pos);
        // Letztes @ finden
        var atIdx = before.lastIndexOf('@');
        if (atIdx === -1) { removeMentionDropdown(); return; }
        var query = before.substring(atIdx + 1);
        // Kein Leerzeichen vor @ (außer am Anfang) → nur echte Mentions
        if (atIdx > 0 && before[atIdx - 1] !== ' ' && before[atIdx - 1] !== '\n') { removeMentionDropdown(); return; }
        // Mind. 3 Zeichen nach @
        if (query.length < 3) { removeMentionDropdown(); return; }
        // Kein Zeilenumbruch in der Query
        if (query.indexOf('\n') !== -1) { removeMentionDropdown(); return; }

        clearTimeout(mentionTimer);
        mentionTimer = setTimeout(function() {
            F.ajax('search_mentions', { term: query }, function(r) {
                if (r && r.success && r.data.users && r.data.users.length) {
                    showMentionDropdown($ta, r.data.users, atIdx);
                } else {
                    removeMentionDropdown();
                }
            });
        }, 250);
    });

    function showMentionDropdown($ta, users, atIdx) {
        removeMentionDropdown();
        var html = '<div class="dgptm-forum-mention-dropdown" style="position:absolute;background:#fff;border:1px solid #ddd;border-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,.12);z-index:200;max-height:150px;overflow-y:auto;font-size:12px">';
        for (var i = 0; i < users.length; i++) {
            var u = users[i];
            var cls = u.blacklisted ? 'color:#c00' : 'color:#1d2327';
            var suffix = u.blacklisted ? ' <span style="font-size:9px;color:#c00">(wird nicht benachrichtigt)</span>' : '';
            html += '<div class="dgptm-forum-mention-item" data-name="' + u.name + '" data-at-idx="' + atIdx + '" style="padding:5px 10px;cursor:pointer;' + cls + '" onmouseover="this.style.background=\'#f0f6fc\'" onmouseout="this.style.background=\'\'">' + u.name + suffix + '</div>';
        }
        html += '</div>';
        $mentionDropdown = $(html);
        // Position unter der Textarea
        var offset = $ta.offset();
        $mentionDropdown.css({ top: offset.top + $ta.outerHeight() + 2, left: offset.left, width: Math.min($ta.outerWidth(), 300) });
        $('body').append($mentionDropdown);
    }

    function removeMentionDropdown() {
        if ($mentionDropdown) { $mentionDropdown.remove(); $mentionDropdown = null; }
    }

    $(document).on('click', '.dgptm-forum-mention-item', function() {
        var name = $(this).data('name');
        var atIdx = parseInt($(this).data('at-idx'));
        // Finde die aktive Textarea
        var $ta = $('textarea:focus');
        if (!$ta.length) $ta = $('.dgptm-forum-thread-form textarea, .dgptm-forum-reply-form textarea').last();
        if (!$ta.length) { removeMentionDropdown(); return; }
        var val = $ta.val();
        var pos = $ta[0].selectionStart;
        // Text vor @ + Name + Leerzeichen + Text nach Cursor
        var newVal = val.substring(0, atIdx) + '@' + name + ' ' + val.substring(pos);
        $ta.val(newVal);
        var newPos = atIdx + name.length + 2;
        $ta[0].setSelectionRange(newPos, newPos);
        $ta.focus();
        removeMentionDropdown();
    });

    // Dropdown schließen bei Klick außerhalb
    $(document).on('click', function(e) {
        if ($mentionDropdown && !$(e.target).closest('.dgptm-forum-mention-dropdown').length) {
            removeMentionDropdown();
        }
    });

    // Dropdown schließen bei Escape
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape') removeMentionDropdown();
    });

    // ===========================================================
    // Init + Dashboard Re-Init
    // ===========================================================

    $(document).ready(function() { F.init(); });
    $(document).on('dgptm_tab_loaded', function() { F.init(); });

})(jQuery);
