jQuery(function($) {

    // Toggle details
    $(document).on('click', '.dgptm-adm-toggle', function() {
        var $body = $(this).closest('.dgptm-adm-tab').find('.dgptm-adm-body');
        $body.slideToggle(200);
        $(this).text($body.is(':visible') ? 'Zuklappen' : 'Details');
    });

    // Permission type toggle
    $(document).on('change', '.dt-perm-type', function() {
        var val = $(this).val();
        var $row = $(this).closest('td');
        $row.find('.dt-perm-acf').toggle(val === 'acf');
        $row.find('.dt-perm-role').toggle(val === 'role');
        updatePermValue($(this).closest('.dgptm-adm-tab'));
    });
    $(document).on('change', '.dt-perm-acf-field, .dt-perm-role-val', function() {
        updatePermValue($(this).closest('.dgptm-adm-tab'));
    });
    $(document).on('input', '.dt-perm-role-val', function() {
        updatePermValue($(this).closest('.dgptm-adm-tab'));
    });

    function updatePermValue($tab) {
        var type = $tab.find('.dt-perm-type').val();
        var val = 'always';
        if (type === 'admin') val = 'admin';
        if (type === 'acf') val = 'acf:' + $tab.find('.dt-perm-acf-field').val();
        if (type === 'role') val = 'role:' + $.trim($tab.find('.dt-perm-role-val').val());
        $tab.find('.dt-perm').val(val);
    }

    // Update title preview on label change
    $(document).on('input', '.dt-label', function() {
        $(this).closest('.dgptm-adm-tab').find('.dgptm-adm-title').text($(this).val());
    });

    // Collect all tabs
    function collect() {
        var tabs = [];
        $('#dgptm-tab-list .dgptm-adm-tab').each(function(i) {
            var $t = $(this);
            tabs.push({
                id:         $t.data('id'),
                label:      $t.find('.dt-label').val(),
                parent:     $t.find('.dt-parent').val() || '',
                permission: $t.find('.dt-perm').val() || 'always',
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

    // Reset
    $('#dt-reset').on('click', function() {
        if (!confirm('Alle Tabs auf Standard zuruecksetzen? Individuelle Aenderungen gehen verloren.')) return;
        $.post(dgptmDashAdmin.ajax, { action: 'dgptm_dash_save', nonce: dgptmDashAdmin.nonce, tabs: '__RESET__' })
         .done(function() { location.reload(); });
    });

    // Add tab
    $('#dt-add').on('click', function() {
        var id = $.trim($('#new-id').val()).toLowerCase().replace(/[^a-z0-9-]/g, '');
        var label = $.trim($('#new-label').val());
        var parent = $('#new-parent').val() || '';
        if (!id || !label) { alert('ID und Label erforderlich'); return; }
        if ($('#dgptm-tab-list .dgptm-adm-tab[data-id="' + id + '"]').length) { alert('ID existiert bereits'); return; }

        var html = '<div class="dgptm-adm-tab" data-id="' + id + '">' +
            '<div class="dgptm-adm-header">' +
            '<span class="dgptm-adm-num">*</span>' +
            '<strong class="dgptm-adm-title">' + $('<span>').text(label).html() + '</strong>' +
            '<code class="dgptm-adm-id">' + id + '</code>' +
            (parent ? '<span class="dgptm-adm-parent-badge">Unter-Tab von: ' + parent + '</span>' : '') +
            '<label class="dgptm-adm-active-toggle"><input type="checkbox" class="dt-active" checked> Aktiv</label>' +
            '<button type="button" class="button button-small dgptm-adm-toggle">Details</button>' +
            '<button type="button" class="button button-small dgptm-adm-delete" style="color:#dc2626;">Loeschen</button>' +
            '</div>' +
            '<div class="dgptm-adm-body">' +
            '<table class="form-table">' +
            '<tr><th>Label</th><td><input type="text" class="dt-label regular-text" value="' + $('<span>').text(label).html() + '"></td></tr>' +
            '<tr><th>Uebergeordneter Tab</th><td><input type="text" class="dt-parent regular-text" value="' + parent + '"></td></tr>' +
            '<tr><th>Berechtigung</th><td><input type="hidden" class="dt-perm" value="always"><select class="dt-perm-type"><option value="always" selected>Immer sichtbar</option><option value="admin">Nur Admins</option><option value="acf">ACF-Feld</option><option value="role">Rolle</option></select></td></tr>' +
            '<tr><th>Inhalt</th><td><textarea class="dt-content large-text code" rows="12" style="font-family:Consolas,monospace;font-size:12px;"></textarea></td></tr>' +
            '</table></div></div>';

        $('#dgptm-tab-list').append(html);
        $('#new-id, #new-label').val('');
        $('#new-parent').val('');
        msg('Tab "' + label + '" erstellt. Bitte Inhalt eingeben und speichern.', 'ok');
    });

    // Delete
    $(document).on('click', '.dgptm-adm-delete', function() {
        var $tab = $(this).closest('.dgptm-adm-tab');
        var id = $tab.data('id');
        if (!confirm('Tab "' + id + '" wirklich loeschen?')) return;
        $tab.slideUp(200, function() { $(this).remove(); });
    });
});
