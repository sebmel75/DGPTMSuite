jQuery(function($) {
    'use strict';

    // ─── Admin tab switching ───
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.dgptm-admin-section').hide();
        $('[data-admin-panel="' + $(this).data('admin-tab') + '"]').show();
    });

    // ─── Toggle details ───
    $(document).on('click', '.dgptm-tab-expand', function() {
        var $d = $(this).closest('.dgptm-tab-config-item').find('.dgptm-tab-config-details');
        $d.slideToggle(200);
        $(this).text($d.is(':visible') ? 'Zuklappen' : 'Details');
    });

    // ─── Permission type toggle ───
    $(document).on('change', '.dt-perm-type', function() {
        var v = $(this).val();
        var $i = $(this).closest('.dgptm-tab-config-item');
        $i.find('.dt-row-acf').toggle(v === 'acf_field');
        $i.find('.dt-row-role').toggle(v === 'role');
        $i.find('.dt-row-shortcode').toggle(v === 'shortcode');
    });

    // ─── Label preview ───
    $(document).on('input', '.dt-label', function() {
        $(this).closest('.dgptm-tab-config-item').find('.dgptm-tab-config-label').text($(this).val());
    });

    // ─── Move Up / Down ───
    $(document).on('click', '.dgptm-move-up', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $item = $(this).closest('.dgptm-tab-config-item');
        var $prev = $item.prev('.dgptm-tab-config-item');
        if ($prev.length) $item.insertBefore($prev);
    });

    $(document).on('click', '.dgptm-move-down', function(e) {
        e.preventDefault();
        e.stopPropagation();
        var $item = $(this).closest('.dgptm-tab-config-item');
        var $next = $item.next('.dgptm-tab-config-item');
        if ($next.length) $item.insertAfter($next);
    });

    // ─── Build permission string ───
    function buildPerm($item) {
        var type = $item.find('.dt-perm-type').val();
        if (type === 'admin') return 'admin';
        if (type === 'acf_field') return 'acf:' + $item.find('.dt-perm-acf').val();
        if (type === 'role') return 'role:' + $.trim($item.find('.dt-perm-roles').val());
        if (type === 'shortcode') return 'sc:' + $.trim($item.find('.dt-perm-sc').val());
        return 'always';
    }

    // ─── Collect tabs ───
    function collect() {
        var tabs = [];
        $('#dgptm-tab-list .dgptm-tab-config-item').each(function(i) {
            var $t = $(this);
            tabs.push({
                id:         $t.attr('data-tab-id'),
                label:      $t.find('.dt-label').val(),
                parent:     $t.find('.dt-parent').val() || '',
                permission: buildPerm($t),
                link:       $.trim($t.find('.dt-link').val()) || '',
                active:     $t.find('.dt-active').is(':checked'),
                order:      (i + 1) * 10,
                content:    $t.find('.dt-content').val()
            });
        });
        return tabs;
    }

    function msg(text, type) {
        var cls = type === 'error' ? 'notice-error' : 'notice-success';
        $('#dgptm-dash-msg').html('<div class="notice ' + cls + ' is-dismissible"><p>' + text + '</p></div>');
        $('html, body').animate({ scrollTop: 0 }, 200);
    }

    // ─── Save tabs ───
    $(document).on('click', '#dt-save', function() {
        var $btn = $(this), orig = $btn.text();
        $btn.prop('disabled', true).text('Speichern...');
        $.ajax({
            url: dgptmDashAdmin.ajax, type: 'POST', timeout: 30000,
            data: { action: 'dgptm_dash_save', nonce: dgptmDashAdmin.nonce, tabs: JSON.stringify(collect()) }
        }).done(function(r) {
            msg(r.success ? r.data : (r.data || 'Fehler'), r.success ? 'ok' : 'error');
        }).fail(function(x, s, e) {
            msg('Speichern fehlgeschlagen: ' + (e || s), 'error');
        }).always(function() {
            $btn.prop('disabled', false).text(orig);
        });
    });

    // ─── Save settings ───
    $(document).on('click', '#dt-save-settings', function() {
        var $btn = $(this), orig = $btn.text();
        $btn.prop('disabled', true).text('Speichern...');
        $.ajax({
            url: dgptmDashAdmin.ajax, type: 'POST', timeout: 10000,
            data: {
                action: 'dgptm_dash_save_settings',
                nonce: dgptmDashAdmin.nonce,
                admin_bypass: $('#dt-admin-bypass').is(':checked') ? '1' : '0'
            }
        }).done(function(r) {
            msg(r.success ? r.data : (r.data || 'Fehler'), r.success ? 'ok' : 'error');
        }).fail(function(x, s, e) {
            msg('Fehler: ' + (e || s), 'error');
        }).always(function() {
            $btn.prop('disabled', false).text(orig);
        });
    });

    // ─── Reset ───
    $(document).on('click', '#dt-reset', function() {
        if (!confirm('Alle Tabs auf Standard zuruecksetzen?')) return;
        $.post(dgptmDashAdmin.ajax, { action: 'dgptm_dash_save', nonce: dgptmDashAdmin.nonce, tabs: '__RESET__' })
         .done(function() { location.reload(); });
    });

    // ─── Add tab ───
    $(document).on('click', '#dt-add-tab', function() {
        var id = $.trim($('#new-tab-id').val()).toLowerCase().replace(/[^a-z0-9-]/g, '');
        var label = $.trim($('#new-tab-label').val());
        var parent = $('#new-tab-parent').val() || '';
        if (!id || !label) { alert('ID und Label erforderlich'); return; }
        if ($('#dgptm-tab-list [data-tab-id="' + id + '"]').length) { alert('ID existiert bereits'); return; }

        var parentBadge = parent ? '<span style="font-size:11px;color:#2271b1;background:#e8f0fe;padding:2px 8px;border-radius:3px;">↳ ' + parent + '</span>' : '';
        var childCls = parent ? ' dgptm-tab-child' : '';

        var html = '<div class="dgptm-tab-config-item' + childCls + '" data-tab-id="' + id + '" data-parent="' + parent + '">' +
            '<div class="dgptm-tab-config-header">' +
            '<button type="button" class="button button-small dgptm-move-up" title="Hoch">&#9650;</button>' +
            '<button type="button" class="button button-small dgptm-move-down" title="Runter">&#9660;</button>' +
            '<strong class="dgptm-tab-config-label">' + $('<span>').text(label).html() + '</strong>' +
            '<code class="dgptm-tab-config-id">' + id + '</code>' +
            parentBadge +
            '<label class="dgptm-tab-config-toggle"><input type="checkbox" class="dt-active" checked> Aktiv</label>' +
            '<button type="button" class="button button-small dgptm-tab-expand">Details</button>' +
            '<button type="button" class="button button-small dgptm-tab-delete" style="color:#b32d2e;">Loeschen</button>' +
            '</div>' +
            '<div class="dgptm-tab-config-details">' +
            '<table class="form-table">' +
            '<tr><th>Label</th><td><input type="text" class="dt-label regular-text" value="' + $('<span>').text(label).html() + '"></td></tr>' +
            '<tr><th>Direkter Link</th><td><input type="url" class="dt-link regular-text" value="" placeholder="https://..."></td></tr>' +
            '<tr><th>Uebergeordneter Tab</th><td><input type="text" class="dt-parent regular-text" value="' + parent + '"></td></tr>' +
            '<tr><th>Berechtigungstyp</th><td><select class="dt-perm-type"><option value="always">Immer sichtbar</option><option value="acf_field">ACF-Feld</option><option value="role">Rolle</option><option value="shortcode">Shortcode</option><option value="admin">Nur Admins</option></select></td></tr>' +
            '<tr><th>Inhalt</th><td><textarea class="dt-content large-text code" rows="10" style="font-family:Consolas,monospace;font-size:12px;"></textarea></td></tr>' +
            '</table></div></div>';

        $('#dgptm-tab-list').append(html);
        $('#new-tab-id, #new-tab-label').val('');
        $('#new-tab-parent').val('');
        msg('Tab "' + label + '" erstellt. Inhalt eingeben und speichern.', 'ok');
    });

    // ─── Delete tab ───
    $(document).on('click', '.dgptm-tab-delete', function() {
        var $tab = $(this).closest('.dgptm-tab-config-item');
        if (!confirm('Tab "' + $tab.attr('data-tab-id') + '" loeschen?')) return;
        $tab.slideUp(200, function() { $(this).remove(); });
    });
});
