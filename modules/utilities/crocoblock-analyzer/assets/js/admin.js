/**
 * Crocoblock Analyzer - Admin JavaScript
 */

(function($) {
    'use strict';

    var CBA = {
        results: null,

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#cba-run-analysis').on('click', this.runAnalysis.bind(this));
            $('#cba-export-report').on('click', this.exportReport.bind(this));
        },

        runAnalysis: function() {
            var self = this;
            var $btn = $('#cba-run-analysis');
            var $progress = $('#cba-progress');
            var $results = $('#cba-results');

            $btn.prop('disabled', true).text('Analysiere...');
            $progress.show();
            $results.hide();

            // Animate progress bar
            var $fill = $('.cba-progress-fill');
            var progress = 0;
            var progressInterval = setInterval(function() {
                progress += Math.random() * 15;
                if (progress > 90) progress = 90;
                $fill.css('width', progress + '%');
            }, 200);

            $.ajax({
                url: cbaAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cba_run_analysis',
                    nonce: cbaAdmin.nonce
                },
                success: function(response) {
                    clearInterval(progressInterval);
                    $fill.css('width', '100%');

                    if (response.success) {
                        self.results = response.data;
                        setTimeout(function() {
                            self.renderResults(response.data);
                            $progress.hide();
                            $results.show();
                            $('#cba-export-report').prop('disabled', false);
                        }, 500);
                    } else {
                        alert('Fehler: ' + (response.data.message || 'Unbekannter Fehler'));
                        $progress.hide();
                    }
                },
                error: function() {
                    clearInterval(progressInterval);
                    alert('Verbindungsfehler');
                    $progress.hide();
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Analyse starten');
                }
            });
        },

        renderResults: function(data) {
            this.renderJetEngine(data.jetengine);
            this.renderJetElements(data.jetelements);
            this.renderJetSmartFilters(data.jetsmartfilters);
            this.renderJetFormBuilder(data.jetformbuilder);
            this.renderSummary(data.summary);
        },

        renderJetEngine: function(data) {
            var self = this;

            // Status
            var statusHtml = data.active ?
                '<span class="cba-status cba-status-active"><span class="dashicons dashicons-yes"></span> Aktiv</span>' :
                '<span class="cba-status cba-status-inactive"><span class="dashicons dashicons-no"></span> Inaktiv</span>';

            $('#cba-jetengine h2').append(' ' + statusHtml);

            // CPTs
            var cptsHtml = '';
            if (data.cpts && data.cpts.length > 0) {
                data.cpts.forEach(function(cpt) {
                    var metaCount = cpt.meta_fields ? Object.keys(cpt.meta_fields).length : 0;
                    cptsHtml += '<div class="cba-item">';
                    cptsHtml += '<div class="cba-item-main">';
                    cptsHtml += '<div class="cba-item-title">' + self.escapeHtml(cpt.name) + '</div>';
                    cptsHtml += '<div class="cba-item-meta">';
                    cptsHtml += '<span>Slug: ' + self.escapeHtml(cpt.slug) + '</span>';
                    if (metaCount > 0) {
                        cptsHtml += '<span>' + metaCount + ' Meta-Felder</span>';
                    }
                    cptsHtml += '</div>';
                    cptsHtml += '</div>';
                    cptsHtml += '</div>';
                });
            } else {
                cptsHtml = '<div class="cba-empty">Keine JetEngine CPTs gefunden</div>';
            }
            $('#cba-cpts').html(cptsHtml);

            // Taxonomies
            var taxHtml = '';
            if (data.taxonomies && data.taxonomies.length > 0) {
                data.taxonomies.forEach(function(tax) {
                    taxHtml += '<div class="cba-item">';
                    taxHtml += '<div class="cba-item-main">';
                    taxHtml += '<div class="cba-item-title">' + self.escapeHtml(tax.name) + '</div>';
                    taxHtml += '<div class="cba-item-meta">';
                    taxHtml += '<span>Slug: ' + self.escapeHtml(tax.slug) + '</span>';
                    if (tax.post_types && tax.post_types.length > 0) {
                        taxHtml += '<span>Post Types: ' + self.escapeHtml(tax.post_types.join(', ')) + '</span>';
                    }
                    taxHtml += '</div>';
                    taxHtml += '</div>';
                    taxHtml += '</div>';
                });
            } else {
                taxHtml = '<div class="cba-empty">Keine JetEngine Taxonomien gefunden</div>';
            }
            $('#cba-taxonomies').html(taxHtml);

            // Meta Fields
            var metaHtml = '';
            if (data.meta_fields && data.meta_fields.length > 0) {
                data.meta_fields.forEach(function(meta) {
                    metaHtml += '<div class="cba-item">';
                    metaHtml += '<div class="cba-item-main">';
                    metaHtml += '<div class="cba-item-title">' + self.escapeHtml(meta.title) + '</div>';
                    metaHtml += '<div class="cba-item-meta">';
                    if (meta.post_types && meta.post_types.length > 0) {
                        metaHtml += '<span>Für: ' + self.escapeHtml(meta.post_types.join(', ')) + '</span>';
                    }
                    if (meta.fields && meta.fields.length > 0) {
                        metaHtml += '<span>' + meta.fields.length + ' Felder</span>';
                    }
                    metaHtml += '</div>';
                    if (meta.fields && meta.fields.length > 0) {
                        metaHtml += '<details class="cba-pages-toggle"><summary>Felder anzeigen</summary>';
                        metaHtml += '<ul class="cba-pages-list">';
                        meta.fields.forEach(function(field) {
                            metaHtml += '<li><strong>' + self.escapeHtml(field.name) + '</strong> (' + field.type + ')</li>';
                        });
                        metaHtml += '</ul></details>';
                    }
                    metaHtml += '</div>';
                    metaHtml += '</div>';
                });
            } else {
                metaHtml = '<div class="cba-empty">Keine JetEngine Meta Boxes gefunden</div>';
            }
            $('#cba-metafields').html(metaHtml);

            // Relations
            var relHtml = '';
            if (data.relations && data.relations.length > 0) {
                data.relations.forEach(function(rel) {
                    relHtml += '<div class="cba-item">';
                    relHtml += '<div class="cba-item-main">';
                    relHtml += '<div class="cba-item-title">' + self.escapeHtml(rel.name) + '</div>';
                    relHtml += '<div class="cba-item-meta">';
                    relHtml += '<span>' + self.escapeHtml(rel.parent) + ' → ' + self.escapeHtml(rel.child) + '</span>';
                    relHtml += '<span>Typ: ' + self.escapeHtml(rel.type) + '</span>';
                    relHtml += '</div>';
                    relHtml += '</div>';
                    relHtml += '</div>';
                });
            } else {
                relHtml = '<div class="cba-empty">Keine JetEngine Relations gefunden</div>';
            }
            $('#cba-relations').html(relHtml);
        },

        renderJetElements: function(data) {
            var self = this;

            // Status
            var statusHtml = data.active ?
                '<span class="cba-status cba-status-active"><span class="dashicons dashicons-yes"></span> Aktiv</span>' :
                '<span class="cba-status cba-status-inactive"><span class="dashicons dashicons-no"></span> Inaktiv</span>';

            $('#cba-jetelements h2').append(' ' + statusHtml);

            var html = '';

            // Widgets summary
            if (data.widgets && Object.keys(data.widgets).length > 0) {
                html += '<h4>Verwendete Widgets (' + Object.keys(data.widgets).length + ' verschiedene)</h4>';
                html += '<div class="cba-widget-grid">';
                for (var widget in data.widgets) {
                    html += '<div class="cba-widget-item">';
                    html += '<span class="cba-widget-name">' + self.escapeHtml(widget) + '</span>';
                    html += '<span class="cba-widget-count">' + data.widgets[widget] + 'x</span>';
                    html += '</div>';
                }
                html += '</div>';

                // Pages with widgets
                if (data.pages && data.pages.length > 0) {
                    html += '<h4 style="margin-top: 25px;">Seiten mit JetElements (' + data.pages.length + ')</h4>';
                    data.pages.forEach(function(page) {
                        var widgetList = Object.keys(page.widgets).join(', ');
                        html += '<div class="cba-item">';
                        html += '<div class="cba-item-main">';
                        html += '<div class="cba-item-title">' + self.escapeHtml(page.title) + '</div>';
                        html += '<div class="cba-item-meta">';
                        html += '<span>Typ: ' + self.escapeHtml(page.type) + '</span>';
                        html += '<span>Widgets: ' + self.escapeHtml(widgetList) + '</span>';
                        html += '</div>';
                        html += '</div>';
                        html += '<div class="cba-item-actions">';
                        html += '<a href="' + page.edit_url + '" target="_blank">Bearbeiten</a>';
                        html += '</div>';
                        html += '</div>';
                    });
                }
            } else {
                html = '<div class="cba-empty">Keine JetElements Widgets gefunden</div>';
            }

            $('#cba-jetelements-list').html(html);
        },

        renderJetSmartFilters: function(data) {
            var self = this;

            // Status
            var statusHtml = data.active ?
                '<span class="cba-status cba-status-active"><span class="dashicons dashicons-yes"></span> Aktiv</span>' :
                '<span class="cba-status cba-status-inactive"><span class="dashicons dashicons-no"></span> Inaktiv</span>';

            $('#cba-jetsmartfilters h2').append(' ' + statusHtml);

            var html = '';

            if (data.filters && data.filters.length > 0) {
                html += '<h4>Filter (' + data.filters.length + ')</h4>';
                data.filters.forEach(function(filter) {
                    html += '<div class="cba-item">';
                    html += '<div class="cba-item-main">';
                    html += '<div class="cba-item-title">' + self.escapeHtml(filter.title) + '</div>';
                    html += '<div class="cba-item-meta">';
                    html += '<span>Typ: ' + self.escapeHtml(filter.type) + '</span>';
                    html += '<span>Quelle: ' + self.escapeHtml(filter.data_source) + '</span>';
                    if (filter.query_var) {
                        html += '<span>Query: ' + self.escapeHtml(filter.query_var) + '</span>';
                    }
                    html += '</div>';
                    html += '</div>';
                    html += '<div class="cba-item-actions">';
                    html += '<a href="' + filter.edit_url + '" target="_blank">Bearbeiten</a>';
                    html += '</div>';
                    html += '</div>';
                });

                // Pages with filters
                if (data.pages_with_filters && data.pages_with_filters.length > 0) {
                    html += '<h4 style="margin-top: 25px;">Seiten mit Filtern (' + data.pages_with_filters.length + ')</h4>';
                    data.pages_with_filters.forEach(function(page) {
                        html += '<div class="cba-item">';
                        html += '<div class="cba-item-main">';
                        html += '<div class="cba-item-title">' + self.escapeHtml(page.title) + '</div>';
                        html += '<div class="cba-item-meta">';
                        html += '<span>Typ: ' + self.escapeHtml(page.type) + '</span>';
                        html += '</div>';
                        html += '</div>';
                        html += '<div class="cba-item-actions">';
                        html += '<a href="' + page.edit_url + '" target="_blank">Bearbeiten</a>';
                        html += '</div>';
                        html += '</div>';
                    });
                }
            } else {
                html = '<div class="cba-empty">Keine JetSmartFilters gefunden</div>';
            }

            $('#cba-filters-list').html(html);
        },

        renderJetFormBuilder: function(data) {
            var self = this;

            // Status
            var statusHtml = data.active ?
                '<span class="cba-status cba-status-active"><span class="dashicons dashicons-yes"></span> Aktiv</span>' :
                '<span class="cba-status cba-status-inactive"><span class="dashicons dashicons-no"></span> Inaktiv</span>';

            $('#cba-jetformbuilder h2').append(' ' + statusHtml);

            var html = '';

            if (data.forms && data.forms.length > 0) {
                html += '<h4>Formulare (' + data.forms.length + ')</h4>';
                data.forms.forEach(function(form) {
                    html += '<div class="cba-item">';
                    html += '<div class="cba-item-main">';
                    html += '<div class="cba-item-title">' + self.escapeHtml(form.title) + '</div>';
                    html += '<div class="cba-item-meta">';
                    html += '<span>Status: ' + self.escapeHtml(form.status) + '</span>';
                    html += '<span>' + form.field_count + ' Felder</span>';
                    html += '</div>';
                    html += '</div>';
                    html += '<div class="cba-item-actions">';
                    html += '<a href="' + form.edit_url + '" target="_blank">Bearbeiten</a>';
                    html += '</div>';
                    html += '</div>';
                });

                // Pages with forms
                if (data.pages_with_forms && data.pages_with_forms.length > 0) {
                    html += '<h4 style="margin-top: 25px;">Seiten mit Formularen (' + data.pages_with_forms.length + ')</h4>';
                    data.pages_with_forms.forEach(function(page) {
                        html += '<div class="cba-item">';
                        html += '<div class="cba-item-main">';
                        html += '<div class="cba-item-title">' + self.escapeHtml(page.title) + '</div>';
                        html += '<div class="cba-item-meta">';
                        html += '<span>Typ: ' + self.escapeHtml(page.type) + '</span>';
                        html += '</div>';
                        html += '</div>';
                        html += '<div class="cba-item-actions">';
                        html += '<a href="' + page.edit_url + '" target="_blank">Bearbeiten</a>';
                        html += '</div>';
                        html += '</div>';
                    });
                }
            } else {
                html = '<div class="cba-empty">Keine JetFormBuilder Formulare gefunden</div>';
            }

            $('#cba-forms-list').html(html);
        },

        renderSummary: function(summary) {
            var html = '';

            // Stats grid
            html += '<div class="cba-summary-grid">';
            html += this.createStatCard(summary.total_cpts, 'CPTs');
            html += this.createStatCard(summary.total_taxonomies, 'Taxonomien');
            html += this.createStatCard(summary.total_relations, 'Relations');
            html += this.createStatCard(summary.total_jet_widgets, 'Jet Widgets');
            html += this.createStatCard(summary.pages_with_jet_widgets, 'Seiten mit Widgets');
            html += this.createStatCard(summary.total_filters, 'Filter');
            html += this.createStatCard(summary.total_jet_forms, 'JetForms');
            html += '</div>';

            // Migration tasks
            if (summary.migration_tasks && summary.migration_tasks.length > 0) {
                html += '<div class="cba-tasks">';
                html += '<h4>Migrationsaufgaben</h4>';

                summary.migration_tasks.forEach(function(task) {
                    html += '<div class="cba-task cba-priority-' + task.priority + '">';
                    html += '<div class="cba-task-header">';
                    html += '<span class="cba-task-title">' + task.task + '</span>';
                    html += '<span class="cba-task-badge ' + task.priority + '">' + task.priority + '</span>';
                    html += '</div>';
                    html += '<div class="cba-task-details">';
                    html += '<span>Anzahl: ' + task.count + '</span>';
                    if (task.pages) {
                        html += '<span>Betroffene Seiten: ' + task.pages + '</span>';
                    }
                    html += '</div>';
                    html += '<div class="cba-task-replacement">';
                    html += '<strong>Ersatz:</strong> ' + task.replacement;
                    html += '</div>';
                    html += '</div>';
                });

                html += '</div>';
            } else {
                html += '<div class="cba-empty">Keine Crocoblock-Abhängigkeiten gefunden! Migration nicht erforderlich.</div>';
            }

            $('#cba-summary-content').html(html);
        },

        createStatCard: function(number, label) {
            return '<div class="cba-stat-card">' +
                '<div class="cba-stat-number">' + number + '</div>' +
                '<div class="cba-stat-label">' + label + '</div>' +
                '</div>';
        },

        exportReport: function() {
            if (!this.results) {
                alert('Bitte zuerst Analyse durchführen');
                return;
            }

            var self = this;
            var $btn = $('#cba-export-report');

            $btn.prop('disabled', true).text('Exportiere...');

            $.ajax({
                url: cbaAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'cba_export_report',
                    nonce: cbaAdmin.nonce
                },
                success: function(response) {
                    if (response.success) {
                        var dataStr = JSON.stringify(response.data.content, null, 2);
                        var blob = new Blob([dataStr], {type: 'application/json'});
                        var url = URL.createObjectURL(blob);

                        var a = document.createElement('a');
                        a.href = url;
                        a.download = response.data.filename;
                        document.body.appendChild(a);
                        a.click();
                        document.body.removeChild(a);
                        URL.revokeObjectURL(url);
                    } else {
                        alert('Export fehlgeschlagen');
                    }
                },
                error: function() {
                    alert('Verbindungsfehler');
                },
                complete: function() {
                    $btn.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> Report exportieren');
                }
            });
        },

        escapeHtml: function(text) {
            if (!text) return '';
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };

    $(document).ready(function() {
        CBA.init();
    });

})(jQuery);
