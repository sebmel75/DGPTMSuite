jQuery(function($) {

    // Admin tab switching (Tabs / Einstellungen)
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        var tab = $(this).data('admin-tab');
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');
        $('.dgptm-admin-section').hide();
        $('[data-admin-panel="' + tab + '"]').show();
    });

    // Toggle details
    $(document).on('click', '.dgptm-tab-expand', function() {
        var $details = $(this).closest('.dgptm-tab-config-item').find('.dgptm-tab-config-details');
        $details.slideToggle(200);
        $(this).text($details.is(':visible') ? 'Zuklappen' : 'Details');
    });

    // Permission type toggle
    $(document).on('change', '.dt-perm-type', function() {
        var val = $(this).val();
        var $item = $(this).closest('.dgptm-tab-config-item');
        $item.find('.dt-row-acf').toggle(val === 'acf_field');
        $item.find('.dt-row-role').toggle(val === 'role');
        $item.find('.dt-row-shortcode').toggle(val === 'shortcode');
    });

    // Update label preview
    $(document).on('input', '.dt-label', function() {
        $(this).closest('.dgptm-tab-config-item').find('.dgptm-tab-config-label').text($(this).val());
    });

    // Build permission string from dropdowns
    function buildPerm($item) {
        var type = $item.find('.dt-perm-type').val();
        if (type === 'admin') return 'admin';
        if (type === 'acf_field') return 'acf:' + $item.find('.dt-perm-acf').val();
        if (type === 'role') return 'role:' + $.trim($item.find('.dt-perm-roles').val());
        if (type === 'shortcode') return 'sc:' + $.trim($item.find('.dt-perm-sc').val());
        return 'always';
    }

    // Collect all tabs
    function collect() {
        var tabs = [];
        $('#dgptm-tab-list .dgptm-tab-config-item').each(function(i) {
            var $t = $(this);
            tabs.push({
                id:         $t.data('tab-id'),
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

    // Save
    $('#dt-save').on('click', function() {
        var $btn = $(this).prop('disabled', true).text('Speichern...');
        $.ajax({
            url: dgptmDashAdmin.ajax,
            type: 'POST',
            data: { action: 'dgptm_dash_save', nonce: dgptmDashAdmin.nonce, tabs: JSON.stringify(collect()) },
            timeout: 30000
        }).done(function(r) {
            msg(r.success ? r.data : (r.data || 'Fehler'), r.success ? 'ok' : 'error');
        }).fail(function(x, s, e) {
            msg('Speichern fehlgeschlagen: ' + (e || s), 'error');
        }).always(function() {
            $btn.prop('disabled', false).text('Alle Tabs speichern');
        });
    });

    // Save settings
    $('#dt-save-settings').on('click', function() {
        var $btn = $(this).prop('disabled', true).text('Speichern...');
        $.ajax({
            url: dgptmDashAdmin.ajax,
            type: 'POST',
            data: {
                action: 'dgptm_dash_save_settings',
                nonce: dgptmDashAdmin.nonce,
                admin_bypass: $('#dt-admin-bypass').is(':checked') ? '1' : '0'
            },
            timeout: 10000
        }).done(function(r) {
            msg(r.success ? r.data : (r.data || 'Fehler'), r.success ? 'ok' : 'error');
        }).fail(function(x, s, e) {
            msg('Fehler: ' + (e || s), 'error');
        }).always(function() {
            $btn.prop('disabled', false).text('Einstellungen speichern');
        });
    });

    // Reset
    $('#dt-reset').on('click', function() {
        if (!confirm('Alle Tabs auf Standard zuruecksetzen? Individuelle Aenderungen gehen verloren.')) return;
        $.post(dgptmDashAdmin.ajax, { action: 'dgptm_dash_save', nonce: dgptmDashAdmin.nonce, tabs: '__RESET__' })
         .done(function() { location.reload(); });
    });

    // Add tab
    $('#dt-add-tab').on('click', function() {
        var id = $.trim($('#new-tab-id').val()).toLowerCase().replace(/[^a-z0-9-]/g, '');
        var label = $.trim($('#new-tab-label').val());
        var parent = $('#new-tab-parent').val() || '';
        if (!id || !label) { alert('ID und Label erforderlich'); return; }
        if ($('#dgptm-tab-list .dgptm-tab-config-item[data-tab-id="' + id + '"]').length) { alert('ID existiert bereits'); return; }

        var parentBadge = parent ? '<span style="font-size:11px;color:#2271b1;background:#e8f0fe;padding:2px 8px;border-radius:3px;">↳ ' + parent + '</span>' : '';

        var html = '<div class="dgptm-tab-config-item" data-tab-id="' + id + '">' +
            '<div class="dgptm-tab-config-header">' +
            '<span class="dashicons dashicons-menu" style="color:#999;"></span>' +
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
            '<tr><th>Uebergeordneter Tab</th><td><input type="text" class="dt-parent regular-text" value="' + parent + '" placeholder="Tab-ID des Eltern-Tabs"></td></tr>' +
            '<tr><th>Berechtigungstyp</th><td><select class="dt-perm-type"><option value="always" selected>Immer sichtbar</option><option value="acf_field">ACF-Feld</option><option value="role">Rolle</option><option value="admin">Nur Admins</option></select></td></tr>' +
            '<tr><th>Inhalt</th><td><textarea class="dt-content large-text code" rows="10" style="font-family:Consolas,monospace;font-size:12px;" placeholder="HTML und Shortcodes eingeben..."></textarea></td></tr>' +
            '</table></div></div>';

        $('#dgptm-tab-list').append(html);
        $('#new-tab-id, #new-tab-label').val('');
        $('#new-tab-parent').val('');
        msg('Tab "' + label + '" erstellt. Bitte Inhalt eingeben und speichern.', 'ok');
    });

    // Delete tab
    $(document).on('click', '.dgptm-tab-delete', function() {
        var $tab = $(this).closest('.dgptm-tab-config-item');
        if (!confirm('Tab "' + $tab.data('tab-id') + '" loeschen?')) return;
        $tab.slideUp(200, function() { $(this).remove(); });
    });
});
