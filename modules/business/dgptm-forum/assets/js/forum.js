(function($) {
    'use strict';

    /* ==========================================================
     * DGPTM Forum — Dashboard-kompatible SPA-Navigation
     * Alle Handler via $(document).on() für AJAX-Tab-Support
     * ========================================================== */

    var F = {
        view: 'ags',
        navStack: [],
        currentAgId: 0,
        currentTopicId: 0,
        currentThreadId: 0,

        // --- AJAX Helper ---
        ajax: function(action, data, cb) {
            data = data || {};
            data.action = 'dgptm_forum_' + action;
            data.nonce = dgptmForum.nonce;
            $.post(dgptmForum.ajaxUrl, data).done(function(r) {
                if (cb) cb(r);
            }).fail(function() {
                F.notify('Verbindungsfehler', 'error');
            });
        },

        // --- Content area ---
        $content: function() { return $('.dgptm-forum-content'); },
        $adminContent: function() { return $('.dgptm-forum-admin-content'); },

        // --- Init ---
        init: function() {
            if ($('.dgptm-forum-wrap').length) {
                F.loadView('ags');
            }
        },

        initAdmin: function() {
            if ($('.dgptm-forum-admin-wrap').length) {
                F.loadAdminTab('ags');
            }
        },

        // ===========================================================
        // Forum View — SPA Navigation
        // ===========================================================

        loadView: function(view, id) {
            F.view = view;
            F.$content().html('<div class="dgptm-forum-loading">Wird geladen\u2026</div>');
            F.ajax('load_view', { view: view, id: id || 0 }, function(r) {
                if (r.success) {
                    F.$content().html(r.data.html);
                    F.updateBreadcrumb(r.data.breadcrumb || []);
                } else {
                    F.$content().html('<p style="color:red">' + (r.data.message || 'Fehler') + '</p>');
                }
            });
        },

        updateBreadcrumb: function(crumbs) {
            var $bc = $('.dgptm-forum-breadcrumb');
            if (!$bc.length) return;
            var html = '';
            for (var i = 0; i < crumbs.length; i++) {
                if (i > 0) html += '<span class="sep">&rsaquo;</span>';
                if (crumbs[i].link) {
                    html += '<a href="#" data-view="' + crumbs[i].view + '" data-id="' + (crumbs[i].id || 0) + '">' + crumbs[i].label + '</a>';
                } else {
                    html += '<span>' + crumbs[i].label + '</span>';
                }
            }
            $bc.html(html);
        },

        notify: function(msg, type) {
            var cls = type === 'error' ? 'color:red' : (type === 'success' ? 'color:green' : 'color:#333');
            var $n = $('<div class="dgptm-forum-notify" style="padding:8px 12px;margin-bottom:10px;border-radius:4px;' + cls + '">' + msg + '</div>');
            F.$content().prepend($n);
            setTimeout(function() { $n.fadeOut(300, function() { $n.remove(); }); }, 3000);
        },

        // ===========================================================
        // Admin Panel
        // ===========================================================

        loadAdminTab: function(tab) {
            F.$adminContent().html('<div class="dgptm-forum-loading">Wird geladen\u2026</div>');
            F.ajax('admin_load_tab', { tab: tab }, function(r) {
                if (r.success) {
                    F.$adminContent().html(r.data.html);
                } else {
                    F.$adminContent().html('<p style="color:red">' + (r.data.message || 'Fehler') + '</p>');
                }
            });
        }
    };

    // ===========================================================
    // Event Handlers — $(document).on() for Dashboard compat
    // ===========================================================

    // --- Forum Navigation ---
    $(document).on('click', '.dgptm-forum-ag-link', function(e) {
        e.preventDefault();
        F.loadView('topics', $(this).data('ag-id'));
    });
    $(document).on('click', '.dgptm-forum-topic-link', function(e) {
        e.preventDefault();
        F.loadView('threads', $(this).data('topic-id'));
    });
    $(document).on('click', '.dgptm-forum-thread-link', function(e) {
        e.preventDefault();
        F.loadView('thread', $(this).data('thread-id'));
    });
    $(document).on('click', '.dgptm-forum-breadcrumb a', function(e) {
        e.preventDefault();
        F.loadView($(this).data('view'), $(this).data('id'));
    });

    // --- Create Thread ---
    $(document).on('submit', '.dgptm-forum-thread-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var fd = new FormData($form[0]);
        fd.append('action', 'dgptm_forum_create_thread');
        fd.append('nonce', dgptmForum.nonce);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true);
        $.ajax({
            url: dgptmForum.ajaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false
        }).done(function(r) {
            if (r.success) {
                F.loadView('thread', r.data.thread_id);
                F.notify('Thread erstellt', 'success');
            } else {
                F.notify(r.data.message || 'Fehler', 'error');
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            F.notify('Verbindungsfehler', 'error');
            $btn.prop('disabled', false);
        });
    });

    // --- Create Reply ---
    $(document).on('submit', '.dgptm-forum-reply-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var fd = new FormData($form[0]);
        fd.append('action', 'dgptm_forum_create_reply');
        fd.append('nonce', dgptmForum.nonce);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true);
        $.ajax({
            url: dgptmForum.ajaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false
        }).done(function(r) {
            if (r.success) {
                F.loadView('thread', F.currentThreadId);
                F.notify('Antwort gesendet', 'success');
            } else {
                F.notify(r.data.message || 'Fehler', 'error');
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            F.notify('Verbindungsfehler', 'error');
            $btn.prop('disabled', false);
        });
    });

    // --- Reply Button (show inline form) ---
    $(document).on('click', '.dgptm-forum-reply-btn', function(e) {
        e.preventDefault();
        var parentId = $(this).data('parent-id') || 0;
        var depth = parseInt($(this).data('depth') || 0) + 1;
        if (depth > 3) {
            F.notify('Maximale Verschachtelungstiefe erreicht.', 'warning');
            return;
        }
        var threadId = $(this).data('thread-id');
        // Remove any existing open reply forms
        $('.dgptm-forum-reply-form-inline').remove();
        var html = '<div class="dgptm-forum-reply-form-inline" style="margin:10px 0 10px ' + (depth * 30) + 'px">';
        html += '<form class="dgptm-forum-reply-form">';
        html += '<input type="hidden" name="thread_id" value="' + threadId + '">';
        html += '<input type="hidden" name="parent_id" value="' + parentId + '">';
        html += '<input type="hidden" name="depth" value="' + depth + '">';
        html += '<textarea name="content" rows="4" placeholder="Antwort schreiben\u2026" style="width:100%;margin-bottom:8px"></textarea>';
        html += '<input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.docx" style="margin-bottom:8px">';
        html += '<div><button type="submit" class="dgptm-forum-btn">Antworten</button> ';
        html += '<a href="#" class="dgptm-forum-cancel-reply">Abbrechen</a></div>';
        html += '</form></div>';
        $(this).closest('.dgptm-forum-post').after(html);
    });

    $(document).on('click', '.dgptm-forum-cancel-reply', function(e) {
        e.preventDefault();
        $(this).closest('.dgptm-forum-reply-form-inline').remove();
    });

    // --- Subscribe/Unsubscribe ---
    $(document).on('click', '.dgptm-forum-subscribe-btn', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var scope = $btn.data('scope');
        var scopeId = $btn.data('scope-id');
        var subscribed = $btn.hasClass('subscribed');
        var action = subscribed ? 'unsubscribe' : 'subscribe';
        F.ajax(action, { scope: scope, scope_id: scopeId }, function(r) {
            if (r.success) {
                $btn.toggleClass('subscribed');
                $btn.text(subscribed ? 'Abonnieren' : 'Abonniert');
            }
        });
    });

    // --- Edit Post ---
    $(document).on('click', '.dgptm-forum-edit-btn', function(e) {
        e.preventDefault();
        var $post = $(this).closest('.dgptm-forum-post');
        var postId = $(this).data('post-id');
        var postType = $(this).data('post-type');
        var $content = $post.find('.post-content');
        var currentText = $content.text();
        $content.html('<textarea class="dgptm-forum-edit-textarea" style="width:100%;min-height:80px">' + $content.html() + '</textarea>' +
            '<div style="margin-top:8px"><button class="dgptm-forum-btn dgptm-forum-save-edit" data-post-id="' + postId + '" data-post-type="' + postType + '">Speichern</button> ' +
            '<a href="#" class="dgptm-forum-cancel-edit">Abbrechen</a></div>');
    });

    $(document).on('click', '.dgptm-forum-save-edit', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var content = $btn.closest('.post-content').find('textarea').val();
        F.ajax('edit_post', {
            post_id: $btn.data('post-id'),
            post_type: $btn.data('post-type'),
            content: content
        }, function(r) {
            if (r.success) {
                F.loadView('thread', F.currentThreadId);
                F.notify('Gespeichert', 'success');
            } else {
                F.notify(r.data.message || 'Fehler', 'error');
            }
        });
    });

    $(document).on('click', '.dgptm-forum-cancel-edit', function(e) {
        e.preventDefault();
        F.loadView('thread', F.currentThreadId);
    });

    // --- Delete Post ---
    $(document).on('click', '.dgptm-forum-delete-btn', function(e) {
        e.preventDefault();
        if (!confirm('Beitrag wirklich löschen?')) return;
        var postId = $(this).data('post-id');
        var postType = $(this).data('post-type');
        F.ajax('delete_post', { post_id: postId, post_type: postType }, function(r) {
            if (r.success) {
                if (postType === 'thread') {
                    F.loadView('threads', F.currentTopicId);
                } else {
                    F.loadView('thread', F.currentThreadId);
                }
                F.notify('Gelöscht', 'success');
            } else {
                F.notify(r.data.message || 'Fehler', 'error');
            }
        });
    });

    // --- New Thread Button ---
    $(document).on('click', '.dgptm-forum-new-thread-btn', function(e) {
        e.preventDefault();
        var topicId = $(this).data('topic-id');
        var $area = F.$content().find('.dgptm-forum-compose-area');
        if ($area.is(':visible')) { $area.slideUp(); return; }
        $area.html(
            '<form class="dgptm-forum-thread-form">' +
            '<input type="hidden" name="topic_id" value="' + topicId + '">' +
            '<input type="text" name="title" placeholder="Titel des Threads" style="width:100%;margin-bottom:8px;padding:8px">' +
            '<textarea name="content" rows="6" placeholder="Ihr Beitrag\u2026" style="width:100%;margin-bottom:8px;padding:8px"></textarea>' +
            '<input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.docx" style="margin-bottom:8px">' +
            '<div><button type="submit" class="dgptm-forum-btn">Thread erstellen</button></div>' +
            '</form>'
        ).slideDown();
    });

    // ===========================================================
    // Admin Panel Handlers
    // ===========================================================

    // --- Admin Tab Navigation ---
    $(document).on('click', '.dgptm-forum-admin-tabs a', function(e) {
        e.preventDefault();
        $('.dgptm-forum-admin-tabs a').removeClass('active');
        $(this).addClass('active');
        F.loadAdminTab($(this).data('tab'));
    });

    // --- Admin: Save AG ---
    $(document).on('submit', '.dgptm-forum-admin-ag-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true);
        F.ajax('admin_save_ag', $form.serialize(), function(r) {
            if (r.success) {
                F.loadAdminTab('ags');
                F.notify(r.data.message || 'Gespeichert', 'success');
            } else {
                F.notify(r.data.message || 'Fehler', 'error');
                $btn.prop('disabled', false);
            }
        });
    });

    // --- Admin: Delete AG ---
    $(document).on('click', '.dgptm-forum-admin-delete-ag', function(e) {
        e.preventDefault();
        if (!confirm('AG wirklich löschen? Alle Mitgliedschaften werden entfernt.')) return;
        F.ajax('admin_delete_ag', { ag_id: $(this).data('ag-id') }, function(r) {
            if (r.success) { F.loadAdminTab('ags'); }
            else { F.notify(r.data.message || 'Fehler', 'error'); }
        });
    });

    // --- Admin: User Search ---
    var searchTimer;
    $(document).on('input', '.dgptm-forum-user-search', function() {
        var $input = $(this);
        var term = $input.val();
        var $results = $input.closest('.dgptm-forum-user-search-wrap').find('.dgptm-forum-user-results');
        clearTimeout(searchTimer);
        if (term.length < 2) { $results.hide(); return; }
        searchTimer = setTimeout(function() {
            F.ajax('admin_search_users', { term: term }, function(r) {
                if (r.success && r.data.users.length) {
                    var html = '';
                    r.data.users.forEach(function(u) {
                        html += '<div class="user-item" data-user-id="' + u.id + '">' + u.name + ' (' + u.email + ')</div>';
                    });
                    $results.html(html).show();
                } else {
                    $results.html('<div class="user-item">Keine Ergebnisse</div>').show();
                }
            });
        }, 300);
    });

    // --- Admin: Select User from Search ---
    $(document).on('click', '.dgptm-forum-user-results .user-item', function() {
        var userId = $(this).data('user-id');
        if (!userId) return;
        var $wrap = $(this).closest('.dgptm-forum-user-search-wrap');
        var context = $wrap.data('context'); // 'ag-member', 'forum-admin', 'topic-access'
        var targetId = $wrap.data('target-id');

        if (context === 'ag-member') {
            F.ajax('admin_add_member', { ag_id: targetId, user_id: userId }, function(r) {
                if (r.success) { F.loadAdminTab('ags'); F.notify('Mitglied hinzugefügt', 'success'); }
                else { F.notify(r.data.message || 'Fehler', 'error'); }
            });
        } else if (context === 'forum-admin') {
            F.ajax('admin_set_forum_admin', { user_id: userId, is_admin: 1 }, function(r) {
                if (r.success) { F.loadAdminTab('admins'); F.notify('Forum-Admin hinzugefügt', 'success'); }
                else { F.notify(r.data.message || 'Fehler', 'error'); }
            });
        } else if (context === 'topic-access') {
            F.ajax('admin_grant_access', { topic_id: targetId, user_id: userId }, function(r) {
                if (r.success) { F.loadAdminTab('topics'); F.notify('Zugriff gewährt', 'success'); }
                else { F.notify(r.data.message || 'Fehler', 'error'); }
            });
        }
        $wrap.find('.dgptm-forum-user-results').hide();
        $wrap.find('.dgptm-forum-user-search').val('');
    });

    // --- Admin: Remove Member ---
    $(document).on('click', '.dgptm-forum-admin-remove-member', function(e) {
        e.preventDefault();
        var agId = $(this).data('ag-id');
        var userId = $(this).data('user-id');
        F.ajax('admin_remove_member', { ag_id: agId, user_id: userId }, function(r) {
            if (r.success) { F.loadAdminTab('ags'); }
            else { F.notify(r.data.message || 'Fehler', 'error'); }
        });
    });

    // --- Admin: Remove Forum Admin ---
    $(document).on('click', '.dgptm-forum-admin-remove-admin', function(e) {
        e.preventDefault();
        var userId = $(this).data('user-id');
        F.ajax('admin_set_forum_admin', { user_id: userId, is_admin: 0 }, function(r) {
            if (r.success) { F.loadAdminTab('admins'); }
            else { F.notify(r.data.message || 'Fehler', 'error'); }
        });
    });

    // --- Admin: Save Topic ---
    $(document).on('submit', '.dgptm-forum-admin-topic-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var $btn = $form.find('button[type="submit"]').prop('disabled', true);
        F.ajax('admin_save_topic', $form.serialize(), function(r) {
            if (r.success) {
                F.loadAdminTab('topics');
                F.notify(r.data.message || 'Gespeichert', 'success');
            } else {
                F.notify(r.data.message || 'Fehler', 'error');
                $btn.prop('disabled', false);
            }
        });
    });

    // --- Admin: Pin/Close Thread ---
    $(document).on('click', '.dgptm-forum-admin-pin', function(e) {
        e.preventDefault();
        F.ajax('admin_toggle_pin', { thread_id: $(this).data('thread-id') }, function(r) {
            if (r.success) { F.loadAdminTab('moderation'); }
        });
    });
    $(document).on('click', '.dgptm-forum-admin-close', function(e) {
        e.preventDefault();
        F.ajax('admin_close_thread', { thread_id: $(this).data('thread-id') }, function(r) {
            if (r.success) { F.loadAdminTab('moderation'); }
        });
    });

    // ===========================================================
    // Init + Dashboard Re-Init
    // ===========================================================

    $(document).ready(function() {
        F.init();
        F.initAdmin();
    });

    $(document).on('dgptm_tab_loaded', function() {
        F.init();
        F.initAdmin();
    });

})(jQuery);
