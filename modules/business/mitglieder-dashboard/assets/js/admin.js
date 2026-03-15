jQuery(function($) {

    function collect() {
        var tabs = [];
        $('#dgptm-tab-table tbody tr').each(function(i) {
            tabs.push({
                id:         $(this).data('id'),
                label:      $(this).find('.dt-label').val(),
                parent:     $(this).find('.dt-parent').val() || '',
                permission: $(this).find('.dt-perm').val() || 'always',
                active:     $(this).find('.dt-active').is(':checked'),
                order:      (i + 1) * 10,
                content:    $(this).find('.dt-content').val()
            });
        });
        return tabs;
    }

    function msg(text, type) {
        var cls = type === 'error' ? 'notice-error' : 'notice-success';
        $('#dgptm-dash-msg').html('<div class="notice ' + cls + ' is-dismissible"><p>' + text + '</p></div>');
    }

    // Save
    $('#dt-save').on('click', function() {
        var $btn = $(this);
        var tabs = collect();
        $btn.prop('disabled', true).text('Speichern...');

        $.ajax({
            url: dgptmDashAdmin.ajax,
            type: 'POST',
            data: { action: 'dgptm_dash_save', nonce: dgptmDashAdmin.nonce, tabs: JSON.stringify(tabs) },
            timeout: 30000
        }).done(function(r) {
            msg(r.success ? r.data : (r.data || 'Fehler'), r.success ? 'ok' : 'error');
        }).fail(function(x, s, e) {
            msg('AJAX Fehler: ' + (e || s), 'error');
        }).always(function() {
            $btn.prop('disabled', false).text('Alle Tabs speichern');
        });
    });

    // Reset
    $('#dt-reset').on('click', function() {
        if (!confirm('Alle Tabs auf Standard zuruecksetzen?')) return;
        $.post(dgptmDashAdmin.ajax, {
            action: 'dgptm_dash_save',
            nonce: dgptmDashAdmin.nonce,
            tabs: '__RESET__'
        }).done(function() { location.reload(); });
    });

    // Add row
    $('#dt-add').on('click', function() {
        var id = $('#new-id').val().replace(/[^a-z0-9-]/g, '');
        var label = $('#new-label').val();
        if (!id || !label) { alert('ID und Label eingeben'); return; }

        var tr = '<tr data-id="' + id + '">' +
            '<td></td>' +
            '<td><code>' + id + '</code></td>' +
            '<td><input type="text" class="dt-label" value="' + $('<span>').text(label).html() + '" style="width:100%"></td>' +
            '<td><input type="text" class="dt-parent" value="' + ($('#new-parent').val() || '') + '" style="width:100%"></td>' +
            '<td><input type="text" class="dt-perm" value="always" style="width:100%"></td>' +
            '<td style="text-align:center"><input type="checkbox" class="dt-active" checked></td>' +
            '<td><textarea class="dt-content" rows="3" style="width:100%;font-family:monospace;font-size:11px;"></textarea></td>' +
            '<td><button type="button" class="button button-small dt-delete">X</button></td>' +
            '</tr>';
        $('#dgptm-tab-table tbody').append(tr);
        $('#new-id, #new-label, #new-parent').val('');
    });

    // Delete row
    $(document).on('click', '.dt-delete', function() {
        if (confirm('Tab loeschen?')) $(this).closest('tr').remove();
    });
});
