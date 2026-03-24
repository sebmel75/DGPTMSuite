/**
 * DGPTM Finanzen Frontend
 *
 * Modular tab-based finance dashboard with chunk-processing billing,
 * payment management, invoice handling, reports, and treasurer tools.
 *
 * Expects dgptmFin global (via wp_localize_script):
 *   dgptmFin.ajaxUrl, dgptmFin.nonce, dgptmFin.role, dgptmFin.tabs
 */
(function($) {
    'use strict';

    var DgptmFin = {
        state: { activeTab: null, billingSession: null },

        // ----------------------------------------------------------------
        // Core
        // ----------------------------------------------------------------

        init: function() {
            var self = this;
            $('.dgptm-fin-tab').on('click', function() {
                self.switchTab($(this).data('tab'));
            });
            if (dgptmFin.tabs && dgptmFin.tabs.length) {
                this.switchTab(dgptmFin.tabs[0]);
            }
        },

        switchTab: function(name) {
            if (!name) return;
            this.state.activeTab = name;
            $('.dgptm-fin-tab').removeClass('active');
            $('.dgptm-fin-tab[data-tab="' + name + '"]').addClass('active');
            $('.dgptm-fin-panel').hide();
            $('#dgptm-fin-panel-' + name).show();
            if (this.tabs[name] && typeof this.tabs[name].load === 'function' && !this.tabs[name]._loaded) {
                this.tabs[name].load();
                this.tabs[name]._loaded = true;
            }
        },

        ajax: function(action, data) {
            data = data || {};
            data.action = action;
            data.nonce = dgptmFin.nonce;
            return $.post(dgptmFin.ajaxUrl, data);
        },

        // ----------------------------------------------------------------
        // Utilities
        // ----------------------------------------------------------------

        feur: function(amount) {
            if (amount == null) return '0,00 EUR';
            return parseFloat(amount).toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' EUR';
        },
        kpi: function(label, value, color) {
            return '<div class="dgptm-fin-kpi dgptm-fin-kpi-' + color + '">' +
                '<div class="dgptm-fin-kpi-value">' + this.esc(String(value)) + '</div>' +
                '<div class="dgptm-fin-kpi-label">' + this.esc(label) + '</div></div>';
        },
        esc: function(s) {
            if (s == null) return '';
            var d = document.createElement('div');
            d.textContent = String(s);
            return d.innerHTML;
        },
        renderTable: function(headers, rows) {
            var self = this;
            var html = '<table class="dgptm-fin-table"><thead><tr>';
            headers.forEach(function(h) {
                var cls = h.cls ? ' class="' + h.cls + '"' : '';
                html += '<th' + cls + '>' + self.esc(h.label || h) + '</th>';
            });
            html += '</tr></thead><tbody>';
            rows.forEach(function(row) {
                html += '<tr>';
                row.forEach(function(cell, i) {
                    var cls = (headers[i] && headers[i].cls) ? ' class="' + headers[i].cls + '"' : '';
                    html += '<td' + cls + '>' + cell + '</td>';
                });
                html += '</tr>';
            });
            html += '</tbody></table>';
            return html;
        },
        showError: function($el, msg) {
            $el.html('<p class="dgptm-fin-error">' + this.esc(msg || 'Unbekannter Fehler') + '</p>');
        },
        showLoading: function($el) {
            $el.html('<div class="dgptm-fin-loading">Laden...</div>');
        },
        errMsg: function(res) {
            return (res && res.data && res.data.message) || 'Fehler';
        },

        // ----------------------------------------------------------------
        // Tab Controllers
        // ----------------------------------------------------------------
        tabs: {}
    };

    // == 1. Dashboard =====================================================
    DgptmFin.tabs.dashboard = {
        _loaded: false,
        load: function() {
            var $p = $('#dgptm-fin-panel-dashboard');
            DgptmFin.showLoading($p);
            DgptmFin.ajax('dgptm_fin_get_dashboard').done(function(res) {
                if (!res || !res.success) { DgptmFin.showError($p, DgptmFin.errMsg(res)); return; }
                var d = res.data, html = '<div class="dgptm-fin-kpis">';
                html += DgptmFin.kpi('Aktive Mitglieder', d.active_members || 0, 'blue');
                html += DgptmFin.kpi('Abgerechnet aktuelles Jahr', DgptmFin.feur(d.billed_current_year), 'green');
                html += DgptmFin.kpi('Ausstehend', d.pending || 0, (d.pending || 0) > 0 ? 'red' : 'green');
                html += DgptmFin.kpi('Offene Rechnungen', d.open_invoices || 0, (d.open_invoices || 0) > 0 ? 'red' : 'green');
                html += '</div>';
                if (d.last_billing_run) {
                    var lr = d.last_billing_run;
                    html += '<div class="dgptm-fin-section"><h4>Letzter Abrechnungslauf</h4>';
                    html += '<table class="dgptm-fin-table"><tbody>';
                    html += '<tr><td>Datum</td><td>' + DgptmFin.esc(lr.date || '-') + '</td></tr>';
                    html += '<tr><td>Jahr</td><td>' + DgptmFin.esc(lr.year || '-') + '</td></tr>';
                    html += '<tr><td>Typ</td><td>' + (lr.dry_run ? 'Simulation' : 'Live') + '</td></tr>';
                    html += '<tr><td>Verarbeitet</td><td>' + (lr.processed || 0) + ' / ' + (lr.total || 0) + '</td></tr>';
                    html += '<tr><td>Fehler</td><td>' + (lr.errors || 0) + '</td></tr>';
                    html += '</tbody></table></div>';
                }
                if (d.timestamp) html += '<p class="dgptm-fin-note">Stand: ' + DgptmFin.esc(d.timestamp) + '</p>';
                $p.html(html);
            }).fail(function() { DgptmFin.showError($p, 'Netzwerkfehler'); });
        }
    };

    // == 2. Billing (chunk-processing) ====================================
    DgptmFin.tabs.billing = {
        _loaded: false,
        load: function() {
            var self = this;
            this.bindEvents();
            DgptmFin.ajax('dgptm_fin_get_billing_status').done(function(res) {
                if (res && res.success && res.data && res.data.session_id) {
                    DgptmFin.state.billingSession = res.data.session_id;
                    self.showProgress(res.data.processed || 0, res.data.total || 1);
                    self.processNextChunk();
                }
            });
        },
        bindEvents: function() {
            var self = this;
            $('#dgptm-fin-billing-dry').off('click').on('click', function() { self.startBilling(true); });
            $('#dgptm-fin-billing-live').off('click').on('click', function() {
                if (!confirm('LIVE-Abrechnungslauf starten? Es werden echte Rechnungen in Zoho Books erstellt!')) return;
                self.startBilling(false);
            });
            $('#dgptm-fin-billing-cancel').off('click').on('click', function() { self.cancel(); });
        },
        startBilling: function(dryRun) {
            var self = this;
            var contacts = $('#dgptm-fin-billing-contacts').val().trim();
            var data = {
                year: $('#dgptm-fin-billing-year').val(),
                dry_run: dryRun ? 1 : 0,
                send_invoices: $('#dgptm-fin-billing-send').is(':checked') ? 1 : 0
            };
            if (contacts) data.contact_ids = contacts;

            var $btn = dryRun ? $('#dgptm-fin-billing-dry') : $('#dgptm-fin-billing-live');
            var label = dryRun ? 'Dry-Run (Simulation)' : 'Live-Run (Abrechnen)';
            $btn.prop('disabled', true).text(dryRun ? 'Simuliere...' : 'Verarbeite...');
            $('#dgptm-fin-billing-results').empty();

            DgptmFin.ajax('dgptm_fin_start_billing', data).done(function(res) {
                $btn.prop('disabled', false).text(label);
                if (!res || !res.success) {
                    DgptmFin.showError($('#dgptm-fin-billing-results'), DgptmFin.errMsg(res));
                    return;
                }
                DgptmFin.state.billingSession = res.data.session_id;
                self.showProgress(0, res.data.total || 1);
                $('#dgptm-fin-billing-cancel').show();
                self.processNextChunk();
            }).fail(function() {
                $btn.prop('disabled', false).text(label);
                DgptmFin.showError($('#dgptm-fin-billing-results'), 'Netzwerkfehler');
            });
        },
        processNextChunk: function() {
            var self = this;
            if (!DgptmFin.state.billingSession) return;

            DgptmFin.ajax('dgptm_fin_process_chunk', {
                session_id: DgptmFin.state.billingSession
            }).done(function(res) {
                if (!res || !res.success) {
                    DgptmFin.showError($('#dgptm-fin-billing-results'), DgptmFin.errMsg(res));
                    self.resetUI();
                    return;
                }
                var d = res.data;
                self.showProgress(d.processed || 0, d.total || 1);
                if (d.done) { self.finalize(); } else { self.processNextChunk(); }
            }).fail(function() {
                DgptmFin.showError($('#dgptm-fin-billing-results'), 'Netzwerkfehler');
                self.resetUI();
            });
        },
        showProgress: function(processed, total) {
            var pct = total > 0 ? Math.round(processed / total * 100) : 0;
            var $bar = $('#dgptm-fin-billing-progress');
            if (!$bar.length) {
                $('#dgptm-fin-billing-results').before(
                    '<div id="dgptm-fin-billing-progress" class="dgptm-fin-progress"><div class="dgptm-fin-progress-bar"></div><div class="dgptm-fin-progress-text"></div></div>'
                );
                $bar = $('#dgptm-fin-billing-progress');
            }
            $bar.show().find('.dgptm-fin-progress-bar').css('width', pct + '%');
            $bar.find('.dgptm-fin-progress-text').text(processed + ' / ' + total + ' (' + pct + '%)');
        },
        finalize: function() {
            var self = this;
            DgptmFin.ajax('dgptm_fin_finalize_billing', {
                session_id: DgptmFin.state.billingSession
            }).done(function(res) {
                self.resetUI();
                if (!res || !res.success) {
                    DgptmFin.showError($('#dgptm-fin-billing-results'), DgptmFin.errMsg(res));
                    return;
                }
                self.renderResults(res.data);
                // Refresh dashboard on next visit
                if (DgptmFin.tabs.dashboard) DgptmFin.tabs.dashboard._loaded = false;
            }).fail(function() {
                self.resetUI();
                DgptmFin.showError($('#dgptm-fin-billing-results'), 'Netzwerkfehler');
            });
        },
        renderResults: function(data) {
            var $r = $('#dgptm-fin-billing-results'), s = data.summary || {}, html = '';
            html += '<div class="dgptm-fin-kpis">';
            html += DgptmFin.kpi('Gesamt', s.total || 0, 'blue');
            html += DgptmFin.kpi('Verarbeitet', s.processed || 0, 'green');
            html += DgptmFin.kpi('Uebersprungen', s.skipped || 0, 'blue');
            html += DgptmFin.kpi('Fehler', s.errors || 0, (s.errors || 0) > 0 ? 'red' : 'green');
            html += '</div>';
            if (s.by_variant && Object.keys(s.by_variant).length) {
                var rows = [];
                Object.keys(s.by_variant).forEach(function(v) { rows.push([DgptmFin.esc(v), s.by_variant[v]]); });
                html += '<div class="dgptm-fin-section"><h4>Nach Variante</h4>';
                html += DgptmFin.renderTable([{ label: 'Variante' }, { label: 'Anzahl', cls: 'num' }], rows);
                html += '</div>';
            }
            if (data.members && data.members.length) {
                html += '<details class="dgptm-fin-details"><summary>Details (' + data.members.length + ' Mitglieder)</summary>';
                html += '<table class="dgptm-fin-table"><thead><tr><th>Name</th><th>Variante</th><th>Status</th><th>Nachricht</th></tr></thead><tbody>';
                data.members.forEach(function(m) {
                    var cls = m.status === 'success' ? 'dgptm-fin-row-ok' : (m.status === 'error' ? 'dgptm-fin-row-err' : '');
                    html += '<tr class="' + cls + '"><td>' + DgptmFin.esc(m.name || '') + '</td><td>' + DgptmFin.esc(m.variant || '') + '</td><td>' + DgptmFin.esc(m.status || '') + '</td><td>' + DgptmFin.esc(m.message || '') + '</td></tr>';
                });
                html += '</tbody></table></details>';
            }
            $r.html(html);
        },
        cancel: function() {
            var self = this;
            if (!DgptmFin.state.billingSession) return;
            DgptmFin.ajax('dgptm_fin_cancel_billing', { session_id: DgptmFin.state.billingSession }).always(function() {
                self.resetUI();
                $('#dgptm-fin-billing-results').html('<p class="dgptm-fin-info">Abrechnungslauf abgebrochen.</p>');
            });
        },
        resetUI: function() {
            DgptmFin.state.billingSession = null;
            $('#dgptm-fin-billing-progress').hide();
            $('#dgptm-fin-billing-cancel').hide();
            $('#dgptm-fin-billing-dry, #dgptm-fin-billing-live').prop('disabled', false);
        }
    };

    // == 3. Members =======================================================
    DgptmFin.tabs.members = {
        _loaded: false,
        load: function() { this.bindEvents(); },
        bindEvents: function() {
            var self = this;
            $('#dgptm-fin-members-load').off('click').on('click', function() { self.loadMembers(); });
            $('#dgptm-fin-members-select-all').off('change').on('change', function() {
                $('.dgptm-fin-member-check').prop('checked', $(this).is(':checked'));
            });
            $('#dgptm-fin-members-to-billing').off('click').on('click', function() { self.selectForBilling(); });
        },
        loadMembers: function() {
            var $p = $('#dgptm-fin-members-table');
            DgptmFin.showLoading($p);
            DgptmFin.ajax('dgptm_fin_get_members', {
                year: $('#dgptm-fin-members-year').val(),
                filter: $('#dgptm-fin-members-filter').val()
            }).done(function(res) {
                if (!res || !res.success) { DgptmFin.showError($p, DgptmFin.errMsg(res)); return; }
                var members = res.data.members || [];
                if (!members.length) { $p.html('<p class="dgptm-fin-info">Keine Mitglieder gefunden.</p>'); return; }
                var html = '<table class="dgptm-fin-table"><thead><tr>';
                html += '<th><input type="checkbox" id="dgptm-fin-members-select-all"></th>';
                html += '<th>Name</th><th>Typ</th><th>Status</th><th>Letzter Beitrag</th></tr></thead><tbody>';
                members.forEach(function(m) {
                    html += '<tr><td><input type="checkbox" class="dgptm-fin-member-check" value="' + DgptmFin.esc(m.contact_id) + '"></td>';
                    html += '<td>' + DgptmFin.esc(m.name || '') + '</td><td>' + DgptmFin.esc(m.type || '') + '</td>';
                    html += '<td>' + DgptmFin.esc(m.status || '') + '</td><td>' + DgptmFin.esc(m.last_billing || '-') + '</td></tr>';
                });
                html += '</tbody></table><p class="dgptm-fin-note">' + members.length + ' Mitglieder geladen</p>';
                $p.html(html);
                $('#dgptm-fin-members-select-all').on('change', function() {
                    $('.dgptm-fin-member-check').prop('checked', $(this).is(':checked'));
                });
            }).fail(function() { DgptmFin.showError($p, 'Netzwerkfehler'); });
        },
        selectForBilling: function() {
            var ids = [];
            $('.dgptm-fin-member-check:checked').each(function() { ids.push($(this).val()); });
            if (!ids.length) { alert('Bitte mindestens ein Mitglied auswaehlen.'); return; }
            $('#dgptm-fin-billing-contacts').val(ids.join(','));
            DgptmFin.switchTab('billing');
        }
    };

    // == 4. Results (billing history) =====================================
    DgptmFin.tabs.results = {
        _loaded: false,
        load: function() {
            var $p = $('#dgptm-fin-panel-results');
            DgptmFin.showLoading($p);
            DgptmFin.ajax('dgptm_fin_get_results').done(function(res) {
                if (!res || !res.success) { DgptmFin.showError($p, DgptmFin.errMsg(res)); return; }
                var runs = res.data.runs || [];
                if (!runs.length) { $p.html('<p class="dgptm-fin-info">Keine bisherigen Abrechnungslaeufe vorhanden.</p>'); return; }
                var html = '<table class="dgptm-fin-table dgptm-fin-results-table"><thead><tr>';
                html += '<th>Datum</th><th>Jahr</th><th>Typ</th><th class="num">Gesamt</th><th class="num">Erfolg</th><th class="num">Fehler</th></tr></thead><tbody>';
                runs.forEach(function(r, idx) {
                    html += '<tr class="dgptm-fin-results-row" data-idx="' + idx + '" style="cursor:pointer;">';
                    html += '<td>' + DgptmFin.esc(r.date || '') + '</td><td>' + DgptmFin.esc(r.year || '') + '</td>';
                    html += '<td>' + (r.dry_run ? 'Simulation' : 'Live') + '</td>';
                    html += '<td class="num">' + (r.total || 0) + '</td><td class="num">' + (r.success || 0) + '</td><td class="num">' + (r.failed || 0) + '</td></tr>';
                    html += '<tr class="dgptm-fin-results-detail" data-idx="' + idx + '" style="display:none;"><td colspan="6">';
                    if (r.details && r.details.length) {
                        html += '<table class="dgptm-fin-table"><thead><tr><th>Name</th><th>Status</th><th>Nachricht</th></tr></thead><tbody>';
                        r.details.forEach(function(d) {
                            html += '<tr><td>' + DgptmFin.esc(d.name || '') + '</td><td>' + DgptmFin.esc(d.status || '') + '</td><td>' + DgptmFin.esc(d.message || '') + '</td></tr>';
                        });
                        html += '</tbody></table>';
                    } else { html += '<p class="dgptm-fin-note">Keine Details verfuegbar.</p>'; }
                    html += '</td></tr>';
                });
                html += '</tbody></table>';
                $p.html(html);
                $p.find('.dgptm-fin-results-row').on('click', function() {
                    var idx = $(this).data('idx');
                    $p.find('.dgptm-fin-results-detail[data-idx="' + idx + '"]').toggle();
                });
            }).fail(function() { DgptmFin.showError($p, 'Netzwerkfehler'); });
        }
    };

    // == 5. Payments ======================================================
    DgptmFin.tabs.payments = {
        _loaded: false,
        load: function() { this.bindEvents(); },
        bindEvents: function() {
            var self = this;
            $('#dgptm-fin-process-payments').off('click').on('click', function() { self.processPayments(); });
            $('#dgptm-fin-sync-mandates').off('click').on('click', function() { self.syncMandates(); });
        },
        processPayments: function() {
            var $btn = $('#dgptm-fin-process-payments'), $r = $('#dgptm-fin-payments-results');
            $btn.prop('disabled', true).text('Verarbeite...');
            $r.empty();
            DgptmFin.ajax('dgptm_fin_process_payments', { dry_run: 1 }).done(function(res) {
                $btn.prop('disabled', false).text('Zahlungen verarbeiten (Dry-Run)');
                if (!res || !res.success) { DgptmFin.showError($r, DgptmFin.errMsg(res)); return; }
                var d = res.data, html = '';
                if (d.summary) {
                    var s = d.summary;
                    html += '<div class="dgptm-fin-kpis">';
                    html += DgptmFin.kpi('Gesamt', s.total || 0, 'blue');
                    html += DgptmFin.kpi('Verarbeitet', s.processed || 0, 'green');
                    html += DgptmFin.kpi('Fehler', s.errors || 0, (s.errors || 0) > 0 ? 'red' : 'green');
                    html += '</div>';
                }
                if (d.details && d.details.length) {
                    html += '<table class="dgptm-fin-table"><thead><tr><th>Rechnung</th><th class="num">Betrag</th><th>Status</th><th>Nachricht</th></tr></thead><tbody>';
                    d.details.forEach(function(item) {
                        html += '<tr><td>' + DgptmFin.esc(item.invoice || '') + '</td><td class="num">' + DgptmFin.feur(item.amount) + '</td><td>' + DgptmFin.esc(item.status || '') + '</td><td>' + DgptmFin.esc(item.message || '') + '</td></tr>';
                    });
                    html += '</tbody></table>';
                }
                $r.html(html);
            }).fail(function() { $btn.prop('disabled', false).text('Zahlungen verarbeiten (Dry-Run)'); DgptmFin.showError($r, 'Netzwerkfehler'); });
        },
        syncMandates: function() {
            var $btn = $('#dgptm-fin-sync-mandates'), $r = $('#dgptm-fin-payments-results');
            $btn.prop('disabled', true).text('Synchronisiere...');
            $r.empty();
            DgptmFin.ajax('dgptm_fin_sync_mandates', { dry_run: 1 }).done(function(res) {
                $btn.prop('disabled', false).text('Mandate synchronisieren (Dry-Run)');
                if (!res || !res.success) { DgptmFin.showError($r, DgptmFin.errMsg(res)); return; }
                var d = res.data, html = '';
                if (d.summary) {
                    var s = d.summary;
                    html += '<div class="dgptm-fin-kpis">';
                    html += DgptmFin.kpi('Gesamt', s.total || 0, 'blue');
                    html += DgptmFin.kpi('Aktualisiert', s.updated || 0, 'green');
                    html += DgptmFin.kpi('Neu', s.created || 0, 'blue');
                    html += DgptmFin.kpi('Fehler', s.errors || 0, (s.errors || 0) > 0 ? 'red' : 'green');
                    html += '</div>';
                }
                if (d.details && d.details.length) {
                    html += '<table class="dgptm-fin-table"><thead><tr><th>Mitglied</th><th>Mandat</th><th>Status</th><th>Nachricht</th></tr></thead><tbody>';
                    d.details.forEach(function(item) {
                        html += '<tr><td>' + DgptmFin.esc(item.name || '') + '</td><td>' + DgptmFin.esc(item.mandate || '') + '</td><td>' + DgptmFin.esc(item.status || '') + '</td><td>' + DgptmFin.esc(item.message || '') + '</td></tr>';
                    });
                    html += '</tbody></table>';
                }
                $r.html(html);
            }).fail(function() { $btn.prop('disabled', false).text('Mandate synchronisieren (Dry-Run)'); DgptmFin.showError($r, 'Netzwerkfehler'); });
        }
    };

    // == 6. Invoices ======================================================
    DgptmFin.tabs.invoices = {
        _loaded: false,
        load: function() { this.loadInvoices(); },
        loadInvoices: function() {
            var self = this, $p = $('#dgptm-fin-panel-invoices');
            DgptmFin.showLoading($p);
            DgptmFin.ajax('dgptm_fin_get_invoices').done(function(res) {
                if (!res || !res.success) { DgptmFin.showError($p, DgptmFin.errMsg(res)); return; }
                var invoices = res.data.invoices || [];
                if (!invoices.length) { $p.html('<p class="dgptm-fin-info">Keine Rechnungen vorhanden.</p>'); return; }
                var html = '<table class="dgptm-fin-table dgptm-fin-invoices-table"><thead><tr>';
                html += '<th>Rechnung#</th><th>Kunde</th><th class="num">Betrag</th><th>Status</th><th>GC Status</th><th>Kredit</th><th>Aktionen</th></tr></thead><tbody>';
                invoices.forEach(function(inv) {
                    html += '<tr data-invoice-id="' + DgptmFin.esc(inv.invoice_id || '') + '">';
                    html += '<td>' + DgptmFin.esc(inv.invoice_number || '') + '</td>';
                    html += '<td>' + DgptmFin.esc(inv.customer_name || '') + '</td>';
                    html += '<td class="num">' + DgptmFin.feur(inv.total) + '</td>';
                    html += '<td>' + DgptmFin.esc(inv.status || '') + '</td>';
                    html += '<td>' + DgptmFin.esc(inv.gc_status || '-') + '</td>';
                    html += '<td>' + DgptmFin.feur(inv.credit_applied) + '</td>';
                    html += '<td>' + self.renderActions(inv) + '</td></tr>';
                });
                html += '</tbody></table>';
                $p.html(html);
                self.bindActions($p);
            }).fail(function() { DgptmFin.showError($p, 'Netzwerkfehler'); });
        },
        renderActions: function(inv) {
            var btns = '', id = DgptmFin.esc(inv.invoice_id || '');
            var st = (inv.status || '').toLowerCase(), gc = (inv.gc_status || '').toLowerCase();
            if (st !== 'paid' && inv.has_mandate)
                btns += '<button class="dgptm-fin-action-btn" data-action="collect" data-id="' + id + '">Einziehen</button> ';
            if (st !== 'paid' && st !== 'void')
                btns += '<button class="dgptm-fin-action-btn" data-action="credit" data-id="' + id + '">Kredit</button> ';
            if (gc === 'paid' || gc === 'confirmed')
                btns += '<button class="dgptm-fin-action-btn" data-action="chargeback" data-id="' + id + '">Chargeback</button> ';
            if (!inv.sent)
                btns += '<button class="dgptm-fin-action-btn" data-action="send" data-id="' + id + '">Senden</button>';
            return btns || '-';
        },
        bindActions: function($c) {
            var self = this;
            $c.find('.dgptm-fin-action-btn').off('click').on('click', function(e) {
                e.stopPropagation();
                var $btn = $(this), actionType = $btn.data('action'), invoiceId = $btn.data('id');
                if (actionType === 'collect' && !confirm('Lastschrift fuer diese Rechnung einziehen?')) return;
                if (actionType === 'chargeback' && !confirm('Chargeback fuer diese Rechnung durchfuehren?')) return;
                $btn.prop('disabled', true).text('...');
                DgptmFin.ajax('dgptm_fin_invoice_action', { invoice_id: invoiceId, action_type: actionType }).done(function(res) {
                    if (res && res.success) { self._loaded = false; self.loadInvoices(); }
                    else { alert(DgptmFin.errMsg(res)); $btn.prop('disabled', false).text($btn.data('action')); }
                }).fail(function() { alert('Netzwerkfehler'); $btn.prop('disabled', false).text($btn.data('action')); });
            });
        }
    };

    // == 7. Reports (ported from finanzbericht.js) ========================
    DgptmFin.tabs.reports = {
        _loaded: false, _report: null, _year: null,
        load: function() {
            this._year = new Date().getFullYear();
            this.bindEvents();
            $('#dgptm-fin-report-tabs .dgptm-fin-report-tab:first').trigger('click');
        },
        bindEvents: function() {
            var self = this;
            $('#dgptm-fin-report-tabs').off('click', '.dgptm-fin-report-tab').on('click', '.dgptm-fin-report-tab', function() {
                $('#dgptm-fin-report-tabs .dgptm-fin-report-tab').removeClass('active');
                $(this).addClass('active');
                self._report = $(this).data('report');
                self.updateYearSelect();
                self.loadReport();
            });
            $('#dgptm-fin-report-year').off('change').on('change', function() { self._year = parseInt($(this).val()); self.loadReport(); });
            $('#dgptm-fin-report-reload').off('click').on('click', function() {
                if (self._report === 'mitgliederzahl') self.refreshCache(); else self.loadReport();
            });
        },
        updateYearSelect: function() {
            var sel = $('#dgptm-fin-report-year'), current = new Date().getFullYear();
            sel.empty();
            for (var y = current; y >= 2023; y--) sel.append($('<option>').val(y).text(y));
            this._year = current;
        },
        loadReport: function() {
            if (!this._report) return;
            var self = this, $content = $('#dgptm-fin-report-content'), $source = $('#dgptm-fin-report-source');
            DgptmFin.showLoading($content);
            $source.text('');
            DgptmFin.ajax('dgptm_fin_get_report', { report: this._report, year: this._year }).done(function(res) {
                if (!res || !res.success) { DgptmFin.showError($content, DgptmFin.errMsg(res)); return; }
                var data = res.data;
                if (data.years && data.years.length) {
                    var sel = $('#dgptm-fin-report-year'), cur = self._year;
                    sel.empty();
                    data.years.sort(function(a, b) { return b - a; });
                    data.years.forEach(function(y) { sel.append($('<option>').val(y).text(y).prop('selected', y === cur)); });
                }
                if (data.source === 'static') $source.text('Historische Daten').removeClass('live').addClass('static');
                else if (data.source === 'live') $source.text('Live (Zoho Books)').removeClass('static').addClass('live');
                else $source.text('');
                if (data.error) { DgptmFin.showError($content, data.error); return; }
                if (self._report === 'mitgliederzahl') self.renderMemberStats($content, data);
                else self.renderReport($content, data);
            }).fail(function() { DgptmFin.showError($content, 'Netzwerkfehler'); });
        },
        renderReport: function($el, data) {
            var html = '<div class="dgptm-fin-report-header"><h3>' + DgptmFin.esc(data.title || '') + '</h3>';
            if (data.period) html += '<p class="dgptm-fin-period">' + DgptmFin.esc(data.period) + '</p>';
            if (data.location) html += '<p class="dgptm-fin-location">' + DgptmFin.esc(data.location) + '</p>';
            html += '</div>';
            html += '<div class="dgptm-fin-kpis">';
            html += DgptmFin.kpi('Einnahmen', DgptmFin.feur(data.income ? data.income.total : 0), 'green');
            html += DgptmFin.kpi('Ausgaben', DgptmFin.feur(data.expenses ? data.expenses.total : 0), 'red');
            var net = data.net_result || 0;
            html += DgptmFin.kpi('Ergebnis', DgptmFin.feur(net), net >= 0 ? 'green' : 'red');
            html += '</div>';
            if (data.income && data.income.categories) {
                html += '<div class="dgptm-fin-section"><h4>Einnahmen nach Kategorie</h4>' + this.categoryTable(data.income.categories, data.income.total) + '</div>';
            }
            if (data.expenses && data.expenses.categories) {
                html += '<div class="dgptm-fin-section"><h4>Ausgaben nach Kategorie</h4>' + this.categoryTable(data.expenses.categories, data.expenses.total) + '</div>';
            }
            if (data.expenses && data.expenses.note) html += '<p class="dgptm-fin-note">' + DgptmFin.esc(data.expenses.note) + '</p>';
            if (data.expenses && data.expenses.items && data.expenses.items.length) {
                html += '<div class="dgptm-fin-section"><h4>Ausgaben-Positionen</h4>' + this.itemsTable(data.expenses.items) + '</div>';
            }
            $el.html(html);
        },
        categoryTable: function(cats, total) {
            var html = '<table class="dgptm-fin-table"><thead><tr><th>Kategorie</th><th class="num">Anzahl</th><th class="num">Betrag</th></tr></thead><tbody>';
            Object.keys(cats).forEach(function(key) {
                var c = cats[key];
                html += '<tr><td>' + DgptmFin.esc(key) + '</td><td class="num">' + (c.count || '-') + '</td><td class="num">' + DgptmFin.feur(c.total) + '</td></tr>';
            });
            html += '</tbody><tfoot><tr><td><strong>Gesamt</strong></td><td></td><td class="num"><strong>' + DgptmFin.feur(total) + '</strong></td></tr></tfoot></table>';
            return html;
        },
        itemsTable: function(items) {
            items.sort(function(a, b) { return b.total - a.total; });
            var html = '<table class="dgptm-fin-table"><thead><tr><th>Datum</th><th>Lieferant</th><th class="num">Betrag</th></tr></thead><tbody>';
            items.forEach(function(item) {
                html += '<tr><td>' + DgptmFin.esc(item.date || '') + '</td><td>' + DgptmFin.esc(item.vendor || item.customer || '') + '</td><td class="num">' + DgptmFin.feur(item.total) + '</td></tr>';
            });
            html += '</tbody></table>';
            return html;
        },
        renderMemberStats: function($el, data) {
            var html = '<div class="dgptm-fin-report-header"><h3>' + DgptmFin.esc(data.title || 'Mitgliederzahlen') + '</h3></div>';
            if (data.error) { $el.html(html + '<p class="dgptm-fin-error">' + DgptmFin.esc(data.error) + '</p>'); return; }
            html += '<div class="dgptm-fin-kpis">';
            html += DgptmFin.kpi('Aktive Mitglieder', data.total_active || 0, 'green');
            if (data.billing_status) {
                var bs = data.billing_status;
                html += DgptmFin.kpi('Beitrag ' + bs.current_year + ' abgerechnet', bs.billed_current || 0, 'green');
                html += DgptmFin.kpi('Noch ausstehend', bs.pending || 0, (bs.pending || 0) > 0 ? 'red' : 'green');
            }
            html += '</div>';
            if (data.by_type && Object.keys(data.by_type).length) {
                html += '<div class="dgptm-fin-section"><h4>Nach Mitgliedstyp</h4>';
                html += '<table class="dgptm-fin-table"><thead><tr><th>Typ</th><th class="num">Anzahl</th></tr></thead><tbody>';
                Object.keys(data.by_type).sort(function(a, b) { return data.by_type[b] - data.by_type[a]; }).forEach(function(t) {
                    html += '<tr><td>' + DgptmFin.esc(t || 'Ohne Typ') + '</td><td class="num">' + data.by_type[t] + '</td></tr>';
                });
                html += '</tbody></table></div>';
            }
            if (data.billing_status) {
                var bs = data.billing_status;
                html += '<div class="dgptm-fin-section"><h4>Beitragslauf ' + bs.current_year + '</h4>';
                html += '<table class="dgptm-fin-table"><thead><tr><th>Status</th><th class="num">Anzahl</th></tr></thead><tbody>';
                html += '<tr><td>Beitrag ' + bs.current_year + ' abgerechnet</td><td class="num">' + bs.billed_current + '</td></tr>';
                html += '<tr><td>Frueheres Jahr abgerechnet</td><td class="num">' + bs.billed_previous + '</td></tr>';
                html += '<tr><td>Noch nie abgerechnet</td><td class="num">' + bs.never_billed + '</td></tr>';
                html += '<tr class="dgptm-fin-highlight"><td><strong>Ausstehend fuer ' + bs.current_year + '</strong></td><td class="num"><strong>' + bs.pending + '</strong></td></tr>';
                html += '</tbody></table></div>';
            }
            if (data.timestamp) html += '<p class="dgptm-fin-note">Stand: ' + DgptmFin.esc(data.timestamp) + (data.source === 'cache' ? ' (Cache)' : ' (Live)') + '</p>';
            $el.html(html);
        },
        refreshCache: function() {
            var $c = $('#dgptm-fin-report-content');
            DgptmFin.showLoading($c);
            DgptmFin.ajax('dgptm_fin_refresh_cache').done(function(res) {
                if (res && res.success) DgptmFin.tabs.reports.renderMemberStats($c, res.data);
                else DgptmFin.showError($c, DgptmFin.errMsg(res));
            }).fail(function() { DgptmFin.showError($c, 'Netzwerkfehler'); });
        }
    };

    // == 8. Treasurer =====================================================
    DgptmFin.tabs.treasurer = {
        _loaded: false,
        load: function() { this.loadData(); },
        loadData: function() {
            var self = this, $p = $('#dgptm-fin-panel-treasurer');
            DgptmFin.showLoading($p);
            DgptmFin.ajax('dgptm_fin_treasurer_crud', { action_type: 'get' }).done(function(res) {
                if (!res || !res.success) { DgptmFin.showError($p, DgptmFin.errMsg(res)); return; }
                self.renderSections($p, res.data);
            }).fail(function() { DgptmFin.showError($p, 'Netzwerkfehler'); });
        },
        renderSections: function($p, data) {
            var self = this, html = '';
            var sections = [
                { key: 'expenses', title: 'Ausgaben / Spesen' },
                { key: 'edu_grants', title: 'Fortbildungszuschuesse' },
                { key: 'bills', title: 'Offene Rechnungen' }
            ];
            sections.forEach(function(sec) {
                var items = data[sec.key] || [];
                html += '<div class="dgptm-fin-section"><h4>' + DgptmFin.esc(sec.title) + ' (' + items.length + ')</h4>';
                if (!items.length) {
                    html += '<p class="dgptm-fin-info">Keine Eintraege vorhanden.</p>';
                } else {
                    html += '<table class="dgptm-fin-table"><thead><tr><th>Datum</th><th>Beschreibung</th><th>Empfaenger</th><th class="num">Betrag</th><th>Status</th><th>Aktion</th></tr></thead><tbody>';
                    items.forEach(function(item) {
                        html += '<tr><td>' + DgptmFin.esc(item.date || '') + '</td><td>' + DgptmFin.esc(item.description || '') + '</td>';
                        html += '<td>' + DgptmFin.esc(item.recipient || '') + '</td><td class="num">' + DgptmFin.feur(item.amount) + '</td>';
                        html += '<td>' + DgptmFin.esc(item.status || '') + '</td><td>';
                        if (item.status !== 'transferred') {
                            html += '<button class="dgptm-fin-action-btn dgptm-fin-treasurer-transfer" data-id="' + DgptmFin.esc(item.id || '') + '" data-section="' + sec.key + '">Ueberwiesen</button>';
                        } else { html += '<span class="dgptm-fin-transferred">Erledigt</span>'; }
                        html += '</td></tr>';
                    });
                    html += '</tbody></table>';
                }
                html += '</div>';
            });
            $p.html(html);
            $p.find('.dgptm-fin-treasurer-transfer').on('click', function() {
                var $btn = $(this);
                self.markTransferred($btn.data('id'), $btn.data('section'), $btn);
            });
        },
        markTransferred: function(id, section, $btn) {
            var self = this;
            $btn.prop('disabled', true).text('...');
            DgptmFin.ajax('dgptm_fin_treasurer_crud', { action_type: 'mark_transferred', item_id: id, section: section }).done(function(res) {
                if (res && res.success) { self._loaded = false; self.loadData(); }
                else { alert(DgptmFin.errMsg(res)); $btn.prop('disabled', false).text('Ueberwiesen'); }
            }).fail(function() { alert('Netzwerkfehler'); $btn.prop('disabled', false).text('Ueberwiesen'); });
        }
    };

    // == 9. Settings ======================================================
    DgptmFin.tabs.settings = {
        _loaded: false,
        load: function() {
            var self = this;
            if (self._loaded) return;
            self._loaded = true;
            self.loadStatus();
            self.loadHistory();
            self.bindActions();
        },
        loadStatus: function() {
            var $s = $('#dgptm-fin-settings-status');
            DgptmFin.ajax('dgptm_fin_get_dashboard').done(function(res) {
                if (!res || !res.success) { DgptmFin.showError($s, DgptmFin.errMsg(res)); return; }
                var d = res.data, html = '';
                if (d.config_valid) {
                    html += '<p style="color:#00a32a;"><strong>&#10003; Konfiguration aktiv</strong></p>';
                    html += '<table class="dgptm-fin-table"><tbody>';
                    if (d.config) {
                        html += '<tr><td>Zoho Org-ID</td><td>' + DgptmFin.esc(d.config.zoho_org_id || '-') + '</td></tr>';
                        html += '<tr><td>GoCardless</td><td>' + (d.config.gocardless_configured ? 'Konfiguriert' : 'Nicht konfiguriert') + '</td></tr>';
                    }
                    html += '</tbody></table>';
                    if (d.membership_types && Object.keys(d.membership_types).length) {
                        html += '<h4>Mitgliedstypen und Beitraege</h4>';
                        html += '<table class="dgptm-fin-table"><thead><tr><th>Typ</th><th class="num">Beitrag</th><th>Item-Code</th></tr></thead><tbody>';
                        var mt = d.membership_types;
                        Object.keys(mt).forEach(function(key) {
                            html += '<tr><td>' + DgptmFin.esc(key) + '</td><td class="num">' + DgptmFin.feur(mt[key].amount || 0) + '</td><td>' + DgptmFin.esc(mt[key].item_code || '-') + '</td></tr>';
                        });
                        html += '</tbody></table>';
                    }
                } else {
                    html += '<p style="color:orange;"><strong>&#9888; Keine gueltige Konfiguration.</strong> Bitte config.json unten importieren.</p>';
                }
                html += '<p style="margin-top:8px;">Rolle: <strong>' + DgptmFin.esc(dgptmFin.role || '-') + '</strong> | Tabs: ' + DgptmFin.esc((dgptmFin.tabs || []).join(', ')) + '</p>';
                $s.html(html);
            }).fail(function() { DgptmFin.showError($s, 'Netzwerkfehler'); });
        },
        loadHistory: function() {
            var $t = $('#dgptm-fin-billing-history-table');
            DgptmFin.ajax('dgptm_fin_get_results').done(function(res) {
                if (!res || !res.success || !res.data || !res.data.length) { $t.html(''); return; }
                var rows = res.data, html = '<table class="dgptm-fin-table"><thead><tr><th>Datum</th><th>Jahr</th><th>Dry-Run</th><th>Total</th><th>Erfolg</th><th>Fehler</th><th class="num">Betrag</th></tr></thead><tbody>';
                rows.forEach(function(r) {
                    html += '<tr><td>' + DgptmFin.esc(r.timestamp || '-') + '</td><td>' + DgptmFin.esc(r.year || '-') + '</td>';
                    html += '<td>' + (r.dry_run ? 'Ja' : 'Nein') + '</td><td>' + (r.total || 0) + '</td>';
                    html += '<td>' + (r.success || 0) + '</td><td>' + (r.errors || 0) + '</td>';
                    html += '<td class="num">' + DgptmFin.feur(r.amount || 0) + '</td></tr>';
                });
                html += '</tbody></table>';
                $t.html(html);
            });
        },
        bindActions: function() {
            var self = this;

            // Config speichern (JSON-Paste oder Datei)
            $('#dgptm-fin-save-config').on('click', function() {
                var $btn = $(this), fileInput = $('#dgptm-fin-config-file')[0];
                $btn.prop('disabled', true).text('Speichere...');

                var processJson = function(json) {
                    DgptmFin.ajax('dgptm_fin_save_config', { config: json }).done(function(res) {
                        if (res && res.success) {
                            alert('Konfiguration gespeichert.');
                            self._loaded = false;
                            self.loadStatus();
                        } else { alert(DgptmFin.errMsg(res)); }
                    }).fail(function() { alert('Netzwerkfehler'); }).always(function() {
                        $btn.prop('disabled', false).text('Konfiguration speichern');
                    });
                };

                if (fileInput.files && fileInput.files.length) {
                    var reader = new FileReader();
                    reader.onload = function(e) { processJson(e.target.result); };
                    reader.readAsText(fileInput.files[0]);
                } else {
                    var json = $('#dgptm-fin-config-json').val().trim();
                    if (!json) { alert('Bitte JSON eingeben oder Datei auswaehlen.'); $btn.prop('disabled', false).text('Konfiguration speichern'); return; }
                    processJson(json);
                }
            });

            // Billing-Results importieren
            $('#dgptm-fin-import-results').on('click', function() {
                var $btn = $(this), fileInput = $('#dgptm-fin-results-files')[0];
                if (!fileInput.files || !fileInput.files.length) { alert('Bitte Dateien auswaehlen.'); return; }
                $btn.prop('disabled', true).text('Importiere...');
                var done = 0, total = fileInput.files.length, errors = 0;

                Array.from(fileInput.files).forEach(function(file) {
                    var reader = new FileReader();
                    reader.onload = function(e) {
                        try {
                            var data = JSON.parse(e.target.result);
                            DgptmFin.ajax('dgptm_fin_import_historical', { data: JSON.stringify(data), filename: file.name, type: 'billing_results' }).always(function() {
                                done++;
                                if (done >= total) {
                                    alert(done + ' Datei(en) verarbeitet.');
                                    self.loadHistory();
                                    $btn.prop('disabled', false).text('Ergebnisse importieren');
                                }
                            });
                        } catch(ex) { errors++; done++; }
                    };
                    reader.readAsText(file);
                });
            });

            // Historische Daten importieren
            $('#dgptm-fin-import-historical').on('click', function() {
                var $btn = $(this), json = $('#dgptm-fin-historical-json').val().trim();
                if (!json) { alert('Bitte JSON eingeben.'); return; }
                $btn.prop('disabled', true).text('Importiere...');
                DgptmFin.ajax('dgptm_fin_import_historical', { data: json }).done(function(res) {
                    if (res && res.success) { alert('Import erfolgreich: ' + (res.data.count || 0) + ' Datensaetze.'); }
                    else { alert(DgptmFin.errMsg(res)); }
                }).fail(function() { alert('Netzwerkfehler'); }).always(function() {
                    $btn.prop('disabled', false).text('Historische Daten importieren');
                });
            });
        }
    };

    // == Initialize ========================================================
    $(function() { DgptmFin.init(); });

})(jQuery);
