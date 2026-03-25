(function($){
    'use strict';

    // ============================================
    // Content-Bereich nach CRUD-Operation neu laden
    // ============================================
    function refreshNewsList() {
        var $area = $('#cnp-news-content-area');
        if (!$area.length) return;
        $area.css('opacity', '0.5');
        $.post(cnpNews.ajaxUrl, {
            action: 'cnp_news_load_list',
            nonce: cnpNews.nonce
        }).done(function(r) {
            if (r.success) {
                $area.html(r.data.html).css('opacity', '1');
            } else {
                $area.css('opacity', '1');
                alert(r.data.message || 'Fehler');
            }
        }).fail(function() {
            $area.css('opacity', '1');
            alert('Verbindungsfehler');
        });
    }

    // ============================================
    // Modals
    // ============================================
    $(document).on('click', '#cnp-open-create-modal', function(e) {
        e.preventDefault();
        $('#cnp-modal-create').css('display', 'flex').hide().fadeIn(200);
    });

    $(document).on('click', '.cnp-open-edit-modal', function(e) {
        e.preventDefault();
        var pid = $(this).data('postid');
        $('#cnp-modal-edit-' + pid).css('display', 'flex').hide().fadeIn(200);
    });

    $(document).on('click', '.cnp-close-modal', function(e) {
        e.preventDefault();
        var target = $(this).data('close');
        $(target).fadeOut(200);
    });

    $(document).on('click', '.cnp-modal-overlay', function(e) {
        if ($(e.target).hasClass('cnp-modal-overlay')) {
            $(this).fadeOut(200);
        }
    });

    // ============================================
    // Toggle Publish
    // ============================================
    $(document).on('change', '.cnp-toggle-publish', function() {
        var $cb = $(this);
        var postId = $cb.data('postid');
        $.post(cnpNews.ajaxUrl, {
            action: 'cnp_news_toggle_publish',
            nonce: cnpNews.nonce,
            post_id: postId
        }).done(function(r) {
            if (r.success) {
                $cb.closest('tr').find('.cnp-status-label').text(r.data.status_label);
            } else {
                alert(r.data.message || 'Fehler');
                $cb.prop('checked', !$cb.prop('checked'));
            }
        }).fail(function() {
            alert('Verbindungsfehler');
            $cb.prop('checked', !$cb.prop('checked'));
        });
    });

    // ============================================
    // Delete
    // ============================================
    $(document).on('click', '.cnp-delete-btn', function(e) {
        e.preventDefault();
        if (!confirm('Eintrag wirklich löschen?')) return;
        var $btn = $(this).prop('disabled', true);
        $.post(cnpNews.ajaxUrl, {
            action: 'cnp_news_delete',
            nonce: cnpNews.nonce,
            post_id: $btn.data('postid')
        }).done(function(r) {
            if (r.success) {
                refreshNewsList();
            } else {
                alert(r.data.message || 'Fehler');
                $btn.prop('disabled', false);
            }
        }).fail(function() {
            alert('Verbindungsfehler');
            $btn.prop('disabled', false);
        });
    });

    // ============================================
    // Create / Edit via FormData (File-Upload)
    // ============================================
    $(document).on('submit', '.cnp-ajax-form', function(e) {
        e.preventDefault();
        var $form = $(this);
        var fd = new FormData($form[0]);
        fd.append('action', $form.data('action'));
        fd.append('nonce', cnpNews.nonce);

        var isCreate = ($form.data('action') === 'cnp_news_create');
        var $btn = $form.find('button[type="submit"]').prop('disabled', true);
        var origText = $btn.text();
        $btn.text('Wird gespeichert\u2026');

        $.ajax({
            url: cnpNews.ajaxUrl,
            type: 'POST',
            data: fd,
            processData: false,
            contentType: false
        }).done(function(r) {
            if (r.success) {
                $form.closest('.cnp-modal-overlay').fadeOut(200);
                refreshNewsList();
                if (isCreate) $form[0].reset();
            } else {
                alert(r.data.message || 'Fehler');
            }
            $btn.prop('disabled', false).text(origText);
        }).fail(function() {
            alert('Verbindungsfehler');
            $btn.prop('disabled', false).text(origText);
        });
    });

    // ============================================
    // Typ-Switch (News / Veranstaltung)
    // ============================================
    $(document).on('change', '#cnp-type-switch-create', function() {
        var isEvent = this.checked;
        $('#cnp-type-label-create').text(isEvent ? 'Veranstaltung' : 'News');
        $('#cnp-event-fields-create').toggle(isEvent);
        $('#cnp_display_until_label_create').text(
            isEvent ? 'Veranstaltungsende (dd.mm.yyyy):' : 'Anzeigen bis (dd.mm.yyyy):'
        );
    });

    $(document).on('change', '.cnp-type-switch-edit', function() {
        var isEvent = this.checked;
        var id = this.id.replace('cnp-type-switch-edit-', '');
        $('#cnp-type-label-edit-' + id).text(isEvent ? 'Veranstaltung' : 'News');
        $('#cnp-event-fields-edit-' + id).toggle(isEvent);
        $('#cnp-event-fields-edit-' + id + '-2').toggle(isEvent);
        $('#cnp_display_until_label_edit-' + id).text(
            isEvent ? 'Veranstaltungsende (dd.mm.yyyy):' : 'Anzeigen bis (dd.mm.yyyy):'
        );
    });

})(jQuery);
