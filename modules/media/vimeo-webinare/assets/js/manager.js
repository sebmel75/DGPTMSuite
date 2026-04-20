/* Manager-Frontend: CRUD, Inline-Editor, Toast, Suche.
   Nutzt jQuery (bereits im WP-Admin vorhanden). */
(function ($) {
    'use strict';

    var FORM_TEMPLATE = null;

    function ajaxCall(action, data, $form) {
        var $root = $('.dgptm-vw-mgr');
        var nonce = $root.data('nonce');
        var ajaxUrl = (window.ajaxurl) ? window.ajaxurl : '/wp-admin/admin-ajax.php';

        if ($form) $form.addClass('is-loading');

        return $.post(ajaxUrl, Object.assign({
            action: action,
            nonce: nonce,
        }, data)).always(function () {
            if ($form) $form.removeClass('is-loading');
        });
    }

    function toast(message, type) {
        type = type || 'success';
        var $t = $('<div class="dgptm-toast dgptm-toast--' + type + '"></div>').text(message);
        $('.dgptm-vw-toast-layer').append($t);
        requestAnimationFrame(function () { $t.addClass('is-visible'); });
        setTimeout(function () {
            $t.removeClass('is-visible');
            setTimeout(function () { $t.remove(); }, 250);
        }, 3000);
    }

    function getFormTemplate() {
        if (FORM_TEMPLATE) return FORM_TEMPLATE;
        var $tpl = $('#dgptm-vw-form-template');
        FORM_TEMPLATE = $tpl.length ? $tpl.html() : '';
        return FORM_TEMPLATE;
    }

    function closeAllEditors() {
        $('.dgptm-vw-editor-slot').empty().attr('hidden', true);
        $('.dgptm-vw-editor-row').remove();
    }

    function confirmDiscardIfDirty() {
        var $open = $('.dgptm-vw-form');
        if ($open.length && $open.data('dirty')) {
            return window.confirm('Ungespeicherte Änderungen verwerfen?');
        }
        return true;
    }

    function populateForm($form, webinar) {
        $form.find('[name="post_id"]').val(webinar.id || 0);
        $form.find('[name="title"]').val(webinar.title || '');
        $form.find('[name="description"]').val(webinar.description || '');
        $form.find('[name="vimeo_id"]').val(webinar.vimeo_id || '');
        $form.find('[name="completion_percentage"]').val(webinar.completion_percentage || 90);
        $form.find('[name="points"]').val(webinar.ebcp_points || 1);
        $form.find('[name="vnr"]').val(webinar.vnr || '');
        $form.find('h3').text(webinar.id ? 'Webinar bearbeiten' : 'Neues Webinar');
        $form.data('dirty', false);
    }

    function markDirty() {
        $(this).closest('.dgptm-vw-form').data('dirty', true);
    }

    function openCreate() {
        if (!confirmDiscardIfDirty()) return;
        closeAllEditors();
        var html = getFormTemplate();
        var $slot = $('.dgptm-vw-editor-create');
        $slot.html(html).removeAttr('hidden');
        var $form = $slot.find('.dgptm-vw-form');
        populateForm($form, {});
        $form.find('[name="title"]').focus();
    }

    function openEdit(id) {
        if (!confirmDiscardIfDirty()) return;
        closeAllEditors();
        var $row = $('.dgptm-vw-mgr-row[data-id="' + id + '"]');
        var cols = $row.find('td').length || 6;
        var $editorRow = $('<tr class="dgptm-vw-editor-row"><td colspan="' + cols + '"></td></tr>');
        $editorRow.find('td').html(getFormTemplate());
        $row.after($editorRow);
        var $form = $editorRow.find('.dgptm-vw-form');

        var seed = {
            id: id,
            title: $row.find('.dgptm-vw-cell-title').text().trim(),
            vimeo_id: $row.find('td').eq(1).text().trim(),
            ebcp_points: parseFloat($row.find('td').eq(2).text().replace(',', '.')) || 1,
            completion_percentage: parseInt($row.find('td').eq(3).text(), 10) || 90,
            description: $row.data('description') || '',
            vnr: $row.data('vnr') || '',
        };
        populateForm($form, seed);
        $form.find('[name="title"]').focus();
    }

    function onSave(e) {
        e.preventDefault();
        var $form = $(this);
        var payload = {};
        $form.serializeArray().forEach(function (f) { payload[f.name] = f.value; });

        ajaxCall('dgptm_vw_save', payload, $form).then(function (resp) {
            if (!resp || !resp.success) {
                toast((resp && resp.data) || 'Speichern fehlgeschlagen', 'error');
                return;
            }
            var id = resp.data.id;
            var rowHtml = resp.data.row;
            var $existing = $('.dgptm-vw-mgr-row[data-id="' + id + '"]');
            if ($existing.length) {
                $existing.replaceWith(rowHtml);
            } else {
                $('.dgptm-vw-mgr-tbody').prepend(rowHtml);
            }
            closeAllEditors();
            toast('Gespeichert');
        }).fail(function () {
            toast('Serverfehler beim Speichern', 'error');
        });
    }

    function onDelete() {
        var id = $(this).data('id');
        if (!window.confirm('Webinar wirklich in den Papierkorb verschieben?')) return;
        ajaxCall('dgptm_vw_delete', { post_id: id }).then(function (resp) {
            if (!resp || !resp.success) {
                toast((resp && resp.data) || 'Löschen fehlgeschlagen', 'error');
                return;
            }
            $('.dgptm-vw-mgr-row[data-id="' + id + '"]').fadeOut(200, function () { $(this).remove(); });
            toast('In Papierkorb verschoben');
        }).fail(function () { toast('Serverfehler', 'error'); });
    }

    function onSearch() {
        var q = $(this).val().trim().toLowerCase();
        $('.dgptm-vw-mgr-row').each(function () {
            var title = $(this).data('title') || '';
            $(this).toggle(title.indexOf(q) !== -1);
        });
    }

    $(function () {
        $(document).on('click', '.dgptm-vw-create-new', openCreate);
        $(document).on('click', '.dgptm-vw-edit', function () { openEdit($(this).data('id')); });
        $(document).on('click', '.dgptm-vw-delete', onDelete);
        $(document).on('click', '.dgptm-vw-form-cancel', function () {
            if (confirmDiscardIfDirty()) closeAllEditors();
        });
        $(document).on('submit', '.dgptm-vw-form', onSave);
        $(document).on('input change', '.dgptm-vw-form input, .dgptm-vw-form textarea', markDirty);
        $(document).on('input', '.dgptm-vw-mgr-search-input', onSearch);
    });
})(jQuery);
