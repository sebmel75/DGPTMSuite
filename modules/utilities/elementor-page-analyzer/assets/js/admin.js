/**
 * DGPTM Page Analyzer - Admin JS
 */
(function($) {
    'use strict';

    var Analyzer = {

        init: function() {
            this.loadPages();
            this.bindEvents();
        },

        loadPages: function() {
            var $select = $('#dgptm-analyzer-page');

            $.post(dgptmAnalyzer.ajaxUrl, {
                action: 'dgptm_analyzer_get_pages',
                nonce: dgptmAnalyzer.nonce
            }, function(resp) {
                if (resp.success) {
                    $select.empty().append('<option value="">-- Seite waehlen --</option>');
                    $.each(resp.data.pages, function(i, page) {
                        $select.append(
                            $('<option>').val(page.id).text(
                                page.title + ' (' + page.type + ', ID: ' + page.id + ')'
                            )
                        );
                    });
                    $('#dgptm-analyzer-run').prop('disabled', false);
                } else {
                    $select.empty().append('<option value="">Fehler beim Laden</option>');
                }
            });
        },

        bindEvents: function() {
            var self = this;

            $('#dgptm-analyzer-run').on('click', function() {
                var pageId = $('#dgptm-analyzer-page').val();
                if (!pageId) {
                    alert('Bitte eine Seite auswaehlen');
                    return;
                }
                self.analyze(pageId);
            });

            $('#dgptm-analyzer-copy').on('click', function() {
                var $textarea = $('#dgptm-analyzer-json');
                $textarea.select();
                if (navigator.clipboard) {
                    navigator.clipboard.writeText($textarea.val()).then(function() {
                        alert('Blueprint in Zwischenablage kopiert!');
                    });
                } else {
                    document.execCommand('copy');
                    alert('Blueprint kopiert!');
                }
            });
        },

        analyze: function(pageId) {
            var $btn = $('#dgptm-analyzer-run');
            var $status = $('#dgptm-analyzer-status');
            var $result = $('#dgptm-analyzer-result');

            $btn.prop('disabled', true).text('Analysiere...');
            $status.show();
            $result.hide();
            $('#dgptm-analyzer-status-text').html('<p>Elementor-Daten werden gelesen und analysiert...</p>');

            $.post(dgptmAnalyzer.ajaxUrl, {
                action: 'dgptm_analyzer_analyze',
                nonce: dgptmAnalyzer.nonce,
                page_id: pageId
            }, function(resp) {
                $btn.prop('disabled', false).text('Blueprint erstellen');

                if (resp.success) {
                    var bp = resp.data.blueprint;

                    // Summary
                    var html = '<p><strong>Datei gespeichert:</strong> <code>' + resp.data.filepath + '</code></p>';
                    html += '<ul>';
                    html += '<li>Seite: <strong>' + bp.page.title + '</strong> (ID: ' + bp.page.id + ')</li>';
                    html += '<li>Sections: ' + (bp.sections ? bp.sections.length : 0) + '</li>';
                    html += '<li>Shortcodes: ' + (bp.shortcodes ? bp.shortcodes.length : 0) + '</li>';
                    html += '<li>Bedingte Elemente: ' + (bp.visibility_summary ? bp.visibility_summary.total_conditional_elements : 0) + '</li>';
                    html += '<li>Referenzierte Berechtigungen: ' + (bp.permission_system.fields_referenced ? bp.permission_system.fields_referenced.length : 0) + '</li>';
                    html += '</ul>';

                    if (bp.permission_system.fields_referenced && bp.permission_system.fields_referenced.length > 0) {
                        html += '<p><strong>Berechtigungsfelder:</strong> ' + bp.permission_system.fields_referenced.join(', ') + '</p>';
                    }

                    $('#dgptm-analyzer-summary').html(html);
                    $('#dgptm-analyzer-json').val(JSON.stringify(bp, null, 2));
                    $('#dgptm-analyzer-status-text').html('<p style="color: green;">Analyse erfolgreich abgeschlossen.</p>');
                    $result.show();
                } else {
                    $('#dgptm-analyzer-status-text').html('<p style="color: red;">Fehler: ' + resp.data.message + '</p>');
                }
            }).fail(function() {
                $btn.prop('disabled', false).text('Blueprint erstellen');
                $('#dgptm-analyzer-status-text').html('<p style="color: red;">AJAX-Fehler aufgetreten.</p>');
            });
        }
    };

    $(document).ready(function() {
        Analyzer.init();
    });

})(jQuery);
