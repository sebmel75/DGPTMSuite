/**
 * DGPTM Mitgliedsbeitrag Frontend
 */
(function($) {
    'use strict';

    function init() {
        loadStatus();
        bindButtons();
    }

    function loadStatus() {
        $.post(dgptmMB.ajaxUrl, {
            action: 'dgptm_mb_get_status',
            nonce: dgptmMB.nonce
        }, function(res) {
            $('#dgptm-mb-stats-loading').hide();
            if (!res.success) {
                $('#dgptm-mb-stats-content').html('<p class="dgptm-mb-error">' + esc(res.data?.message || 'Fehler') + '</p>').show();
                return;
            }
            renderStats(res.data);
        });
    }

    function renderStats(data) {
        var $c = $('#dgptm-mb-stats-content');
        var html = '';
        var s = data.stats || {};

        // KPIs
        html += '<div class="dgptm-mb-kpis">';
        html += kpi('Aktive Mitglieder', s.total_active || 0, 'blue');
        if (s.billing_status) {
            var bs = s.billing_status;
            html += kpi('Abgerechnet ' + bs.current_year, bs.billed_current || 0, 'green');
            html += kpi('Ausstehend', bs.pending || 0, bs.pending > 0 ? 'red' : 'green');
        }
        html += '</div>';

        // Nach Typ
        if (s.by_type && Object.keys(s.by_type).length) {
            html += '<table class="dgptm-mb-table"><thead><tr><th>Mitgliedstyp</th><th class="num">Anzahl</th></tr></thead><tbody>';
            var types = Object.keys(s.by_type).sort(function(a, b) { return s.by_type[b] - s.by_type[a]; });
            types.forEach(function(t) {
                html += '<tr><td>' + esc(t || 'Ohne Typ') + '</td><td class="num">' + s.by_type[t] + '</td></tr>';
            });
            html += '</tbody></table>';
        }

        // Beitragslauf
        if (s.billing_status) {
            var bs = s.billing_status;
            html += '<table class="dgptm-mb-table" style="margin-top:12px;"><thead><tr><th>Beitragslauf ' + bs.current_year + '</th><th class="num">Anzahl</th></tr></thead><tbody>';
            html += '<tr><td>Abgerechnet ' + bs.current_year + '</td><td class="num">' + bs.billed_current + '</td></tr>';
            html += '<tr><td>Frueheres Jahr</td><td class="num">' + bs.billed_previous + '</td></tr>';
            html += '<tr><td>Nie abgerechnet</td><td class="num">' + bs.never_billed + '</td></tr>';
            html += '<tr style="background:#fef3cd;"><td><strong>Ausstehend</strong></td><td class="num"><strong>' + bs.pending + '</strong></td></tr>';
            html += '</tbody></table>';
        }

        // Letzter Lauf
        if (data.last_run && data.last_run.timestamp) {
            html += '<p style="font-size:0.8em;color:#6b7280;margin-top:12px;">Letzter Lauf: ' + esc(data.last_run.timestamp) + ' (Jahr ' + (data.last_run.year || '-') + ')</p>';
        }

        if (!data.config_set) {
            html += '<p class="dgptm-mb-error" style="margin-top:12px;">Konfiguration nicht gesetzt. Bitte im Admin-Bereich die config.json importieren.</p>';
        }

        $c.html(html).show();
    }

    function bindButtons() {
        // Dry-Run
        $('#dgptm-mb-dry-run').on('click', function() {
            runBilling(true);
        });

        // Live-Run
        $('#dgptm-mb-live-run').on('click', function() {
            if (!confirm('LIVE-Abrechnungslauf starten? Es werden echte Rechnungen erstellt!')) return;
            runBilling(false);
        });

        // Zahlungen verarbeiten
        $('#dgptm-mb-process-payments').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Verarbeite...');
            ajaxAction('dgptm_mb_process_payments', { dry_run: true }, function(res) {
                $btn.prop('disabled', false).text('Zahlungen verarbeiten (Dry-Run)');
                showResults(res.data || res);
            });
        });

        // Mandate synchronisieren
        $('#dgptm-mb-sync-mandates').on('click', function() {
            var $btn = $(this);
            $btn.prop('disabled', true).text('Synchronisiere...');
            ajaxAction('dgptm_mb_sync_mandates', { dry_run: true }, function(res) {
                $btn.prop('disabled', false).text('Mandate synchronisieren (Dry-Run)');
                showResults(res.data || res);
            });
        });
    }

    function runBilling(dryRun) {
        var year = $('#dgptm-mb-year').val();
        var contacts = $('#dgptm-mb-contacts').val().trim();
        var send = $('#dgptm-mb-send').is(':checked');

        var $btn = dryRun ? $('#dgptm-mb-dry-run') : $('#dgptm-mb-live-run');
        $btn.prop('disabled', true).text(dryRun ? 'Simuliere...' : 'Verarbeite...');

        var data = {
            year: year,
            dry_run: dryRun ? 1 : 0,
            send_invoices: send ? 1 : 0,
        };
        if (contacts) {
            data.contact_ids = contacts.split(',').map(function(s) { return s.trim(); });
        }

        ajaxAction('dgptm_mb_start_billing', data, function(res) {
            $btn.prop('disabled', false).text(dryRun ? 'Dry-Run (Simulation)' : 'Live-Run (Abrechnen)');
            showResults(res.data || res);
            if (!dryRun) loadStatus(); // Stats aktualisieren
        });
    }

    function ajaxAction(action, extraData, callback) {
        var data = $.extend({ action: action, nonce: dgptmMB.nonce }, extraData);
        $.post(dgptmMB.ajaxUrl, data, function(res) {
            if (res.success) {
                callback(res);
            } else {
                showResults({ error: res.data?.message || 'Fehler' });
            }
        }).fail(function() {
            showResults({ error: 'Netzwerkfehler' });
        });
    }

    function showResults(data) {
        var $r = $('#dgptm-mb-results');
        var $c = $('#dgptm-mb-results-content');
        var html = '';

        if (data.error) {
            html = '<p class="dgptm-mb-error">' + esc(data.error) + '</p>';
        } else if (data.summary) {
            var s = data.summary;
            html += '<div class="dgptm-mb-kpis">';
            html += kpi('Gesamt', s.total || 0, 'blue');
            html += kpi('Verarbeitet', s.processed || 0, 'green');
            html += kpi('Uebersprungen', s.skipped || 0, 'blue');
            html += kpi('Fehler', s.errors || 0, s.errors > 0 ? 'red' : 'green');
            html += '</div>';

            if (s.by_variant && Object.keys(s.by_variant).length) {
                html += '<table class="dgptm-mb-table"><thead><tr><th>Variante</th><th class="num">Anzahl</th></tr></thead><tbody>';
                Object.keys(s.by_variant).forEach(function(v) {
                    html += '<tr><td>' + esc(v) + '</td><td class="num">' + s.by_variant[v] + '</td></tr>';
                });
                html += '</tbody></table>';
            }
        }

        // Detail-Log
        if (data.members && data.members.length) {
            html += '<details style="margin-top:12px;"><summary>Details (' + data.members.length + ' Mitglieder)</summary>';
            html += '<div class="dgptm-mb-log">';
            data.members.forEach(function(m) {
                var icon = m.status === 'success' ? '✓' : (m.status === 'error' ? '✗' : '–');
                html += icon + ' ' + esc(m.name || '') + ' [' + esc(m.variant || m.status || '') + '] ' + esc(m.message || '') + '\n';
            });
            html += '</div></details>';
        }

        if (data.details && data.details.length) {
            html += '<div class="dgptm-mb-log" style="margin-top:12px;">' + JSON.stringify(data.details, null, 2) + '</div>';
        }

        $c.html(html);
        $r.show();
    }

    function kpi(label, value, color) {
        return '<div class="dgptm-mb-kpi dgptm-mb-kpi-' + color + '">' +
               '<div class="dgptm-mb-kpi-label">' + label + '</div>' +
               '<div class="dgptm-mb-kpi-value">' + value + '</div></div>';
    }

    function esc(str) {
        if (str == null) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(String(str)));
        return div.innerHTML;
    }

    $(init);

})(jQuery);
