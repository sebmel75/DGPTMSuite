jQuery(document).ready(function($) {
    'use strict';

    const API = {
        getPages: function() {
            return $.ajax({
                url: elementorAiExport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'elementor_ai_get_pages',
                    nonce: elementorAiExport.nonce
                }
            });
        },

        exportPage: function(pageId, format) {
            return $.ajax({
                url: elementorAiExport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'elementor_ai_export_page',
                    nonce: elementorAiExport.nonce,
                    page_id: pageId,
                    format: format
                }
            });
        },

        importPage: function(pageId, content) {
            return $.ajax({
                url: elementorAiExport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'elementor_ai_import_page',
                    nonce: elementorAiExport.nonce,
                    page_id: pageId,
                    content: content
                }
            });
        },

        importStaging: function(pageId, content) {
            return $.ajax({
                url: elementorAiExport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'elementor_ai_import_staging',
                    nonce: elementorAiExport.nonce,
                    page_id: pageId,
                    content: content
                }
            });
        },

        getStagingPages: function() {
            return $.ajax({
                url: elementorAiExport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'elementor_ai_get_staging_pages',
                    nonce: elementorAiExport.nonce
                }
            });
        },

        deleteStaging: function(stagingId) {
            return $.ajax({
                url: elementorAiExport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'elementor_ai_delete_staging',
                    nonce: elementorAiExport.nonce,
                    staging_id: stagingId
                }
            });
        },

        applyStaging: function(stagingId) {
            return $.ajax({
                url: elementorAiExport.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'elementor_ai_apply_staging',
                    nonce: elementorAiExport.nonce,
                    staging_id: stagingId
                }
            });
        }
    };

    // Load pages
    function loadPages() {
        API.getPages().done(function(response) {
            if (response.success) {
                const pages = response.data.pages;

                const options = ['<option value="">-- Seite auswählen --</option>'];
                pages.forEach(function(page) {
                    options.push(
                        '<option value="' + page.id + '">' +
                        page.title + ' (' + page.type + ') - ' + page.modified +
                        '</option>'
                    );
                });

                $('#page-select, #import-page-select').html(options.join(''));
            } else {
                alert('Fehler beim Laden der Seiten: ' + (response.data.message || 'Unbekannter Fehler'));
            }
        }).fail(function() {
            alert('Netzwerkfehler beim Laden der Seiten');
        });
    }

    // Load staging pages
    function loadStagingPages() {
        API.getStagingPages().done(function(response) {
            if (response.success) {
                const stagingPages = response.data.staging_pages;
                const $list = $('#staging-list');

                if (stagingPages.length === 0) {
                    $list.html('<p class="description">Keine Staging-Seiten vorhanden.</p>');
                    return;
                }

                let html = '<table class="wp-list-table widefat fixed striped">';
                html += '<thead><tr>';
                html += '<th>Staging-Seite</th>';
                html += '<th>Original-Seite</th>';
                html += '<th>Erstellt</th>';
                html += '<th>Aktionen</th>';
                html += '</tr></thead><tbody>';

                stagingPages.forEach(function(page) {
                    html += '<tr data-staging-id="' + page.id + '">';
                    html += '<td><strong>' + page.title + '</strong></td>';
                    html += '<td>' + page.original_title + '</td>';
                    html += '<td>' + page.created + '</td>';
                    html += '<td>';
                    html += '<a href="' + page.url + '" target="_blank" class="button button-small">Ansehen</a> ';
                    html += '<a href="' + page.edit_url + '" target="_blank" class="button button-small">Bearbeiten</a> ';
                    html += '<button class="button button-small button-primary apply-staging" data-staging-id="' + page.id + '">Übernehmen</button> ';
                    html += '<button class="button button-small delete-staging" data-staging-id="' + page.id + '">Löschen</button>';
                    html += '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
                $list.html(html);
            }
        });
    }

    // Enable/disable export button
    $('#page-select').on('change', function() {
        $('#export-btn').prop('disabled', !$(this).val());
    });

    // Enable/disable import button
    $('#import-page-select, #import-content').on('change keyup', function() {
        const hasPage = $('#import-page-select').val();
        const hasContent = $('#import-content').val().trim();
        $('#import-btn').prop('disabled', !hasPage || !hasContent);
    });

    // Update import mode UI
    $('input[name="import-mode"]').on('change', function() {
        const mode = $(this).val();
        const $btnText = $('#import-btn-text');
        const $warning = $('#import-warning-text');

        if (mode === 'staging') {
            $btnText.text('Als Staging importieren');
            $warning.html('<strong>Hinweis:</strong> Eine Staging-Seite wird als Entwurf erstellt. Sie können sie testen, bevor Sie die Änderungen auf die Original-Seite übertragen.');
        } else {
            $btnText.text('Direkt überschreiben');
            $warning.html('<strong>Achtung:</strong> Der Import überschreibt die aktuelle Seite sofort. Ein automatisches Backup wird erstellt.');
        }
    });

    // Export page
    $('#export-btn').on('click', function() {
        const pageId = $('#page-select').val();
        const format = $('#format-select').val();

        if (!pageId) {
            alert('Bitte wählen Sie eine Seite aus');
            return;
        }

        const $btn = $(this);
        $btn.addClass('loading').prop('disabled', true);
        $('#export-result').hide();

        API.exportPage(pageId, format).done(function(response) {
            if (response.success) {
                const data = response.data;

                // Show result
                $('#export-content').val(data.content);
                $('#export-result').fadeIn();

                // Store for download
                $('#export-result').data('export', data);

                // Scroll to result
                $('html, body').animate({
                    scrollTop: $('#export-result').offset().top - 100
                }, 500);
            } else {
                alert('Fehler beim Export: ' + (response.data.message || 'Unbekannter Fehler'));
            }
        }).fail(function() {
            alert('Netzwerkfehler beim Export');
        }).always(function() {
            $btn.removeClass('loading').prop('disabled', false);
        });
    });

    // Copy to clipboard
    $('#copy-btn').on('click', function() {
        const $content = $('#export-content');
        $content.select();

        try {
            document.execCommand('copy');

            const $btn = $(this);
            const originalText = $btn.html();
            $btn.html('<span class="dashicons dashicons-yes"></span> Kopiert!');

            setTimeout(function() {
                $btn.html(originalText);
            }, 2000);
        } catch (err) {
            alert('Kopieren fehlgeschlagen. Bitte manuell kopieren.');
        }
    });

    // Download file
    $('#download-btn').on('click', function() {
        const exportData = $('#export-result').data('export');

        if (!exportData) {
            alert('Keine Export-Daten vorhanden');
            return;
        }

        const blob = new Blob([exportData.content], { type: 'text/plain' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = exportData.filename;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    });

    // Import page
    $('#import-btn').on('click', function() {
        const pageId = $('#import-page-select').val();
        const content = $('#import-content').val().trim();
        const mode = $('input[name="import-mode"]:checked').val();

        if (!pageId || !content) {
            alert('Bitte füllen Sie alle Felder aus');
            return;
        }

        // Validate JSON
        try {
            JSON.parse(content);
        } catch (e) {
            alert('Ungültiges JSON-Format. Bitte überprüfen Sie den Inhalt.');
            return;
        }

        // Confirm for direct mode
        if (mode === 'direct') {
            if (!confirm('Möchten Sie die Seite wirklich überschreiben? Ein Backup wird automatisch erstellt.')) {
                return;
            }
        }

        const $btn = $(this);
        $btn.addClass('loading').prop('disabled', true);
        $('#import-result').hide();

        const apiCall = mode === 'staging' ? API.importStaging(pageId, content) : API.importPage(pageId, content);

        apiCall.done(function(response) {
            const $result = $('#import-result');

            if (response.success) {
                let message = '';

                if (mode === 'staging') {
                    message = '<strong>Erfolg!</strong> Staging-Seite wurde erstellt. ' +
                              '<a href="' + response.data.staging_url + '" target="_blank">Ansehen</a> | ' +
                              '<a href="' + response.data.edit_url + '" target="_blank">Bearbeiten</a>';

                    // Reload staging list
                    loadStagingPages();
                } else {
                    message = '<strong>Erfolg!</strong> Die Seite wurde erfolgreich aktualisiert. ' +
                              '<a href="' + getEditUrl(pageId) + '" target="_blank">In Elementor bearbeiten</a>';
                }

                $result.removeClass('error').addClass('success').html(message).fadeIn();

                // Clear content
                $('#import-content').val('');
                $('#import-btn').prop('disabled', true);
            } else {
                $result.removeClass('success').addClass('error')
                    .html('<strong>Fehler!</strong> ' + (response.data.message || 'Unbekannter Fehler'))
                    .fadeIn();
            }
        }).fail(function() {
            $('#import-result').removeClass('success').addClass('error')
                .html('<strong>Netzwerkfehler!</strong> Der Import konnte nicht durchgeführt werden.')
                .fadeIn();
        }).always(function() {
            $btn.removeClass('loading').prop('disabled', false);
        });
    });

    // Apply staging to original
    $(document).on('click', '.apply-staging', function() {
        const stagingId = $(this).data('staging-id');
        const $row = $(this).closest('tr');

        if (!confirm('Möchten Sie die Änderungen wirklich auf die Original-Seite übertragen? Ein Backup wird erstellt.')) {
            return;
        }

        const $btn = $(this);
        $btn.addClass('loading').prop('disabled', true);

        API.applyStaging(stagingId).done(function(response) {
            if (response.success) {
                alert('Änderungen wurden erfolgreich übernommen!');
                $row.fadeOut(function() {
                    $(this).remove();
                    // Check if table is empty
                    if ($('#staging-list tbody tr').length === 0) {
                        loadStagingPages();
                    }
                });
            } else {
                alert('Fehler: ' + (response.data.message || 'Unbekannter Fehler'));
                $btn.removeClass('loading').prop('disabled', false);
            }
        }).fail(function() {
            alert('Netzwerkfehler beim Übernehmen der Änderungen');
            $btn.removeClass('loading').prop('disabled', false);
        });
    });

    // Delete staging page
    $(document).on('click', '.delete-staging', function() {
        const stagingId = $(this).data('staging-id');
        const $row = $(this).closest('tr');

        if (!confirm('Möchten Sie die Staging-Seite wirklich löschen?')) {
            return;
        }

        const $btn = $(this);
        $btn.addClass('loading').prop('disabled', true);

        API.deleteStaging(stagingId).done(function(response) {
            if (response.success) {
                $row.fadeOut(function() {
                    $(this).remove();
                    // Check if table is empty
                    if ($('#staging-list tbody tr').length === 0) {
                        loadStagingPages();
                    }
                });
            } else {
                alert('Fehler: ' + (response.data.message || 'Unbekannter Fehler'));
                $btn.removeClass('loading').prop('disabled', false);
            }
        }).fail(function() {
            alert('Netzwerkfehler beim Löschen');
            $btn.removeClass('loading').prop('disabled', false);
        });
    });

    // Helper: Get Elementor edit URL
    function getEditUrl(pageId) {
        return window.location.origin + '/wp-admin/post.php?post=' + pageId + '&action=elementor';
    }

    // Tab Navigation
    $('.nav-tab').on('click', function(e) {
        e.preventDefault();
        const tab = $(this).data('tab');

        // Update tabs
        $('.nav-tab').removeClass('nav-tab-active');
        $(this).addClass('nav-tab-active');

        // Update content
        $('.tab-content').hide();
        $('#tab-' + tab).show();

        // Load staging pages when switching to staging tab
        if (tab === 'staging') {
            loadStagingPages();
        }

        // Load pages for auto tab
        if (tab === 'auto') {
            loadAutoPages();
        }
    });

    // Load pages for auto redesign
    function loadAutoPages() {
        API.getPages().done(function(response) {
            if (response.success) {
                const pages = response.data.pages;

                const options = ['<option value="">-- Seite auswählen --</option>'];
                pages.forEach(function(page) {
                    options.push(
                        '<option value="' + page.id + '">' +
                        page.title + ' (' + page.type + ') - ' + page.modified +
                        '</option>'
                    );
                });

                $('#auto-page-select').html(options.join(''));
            }
        });
    }

    // Enable/disable auto redesign button
    $('#auto-page-select, #redesign-prompt').on('change keyup', function() {
        const hasPage = $('#auto-page-select').val();
        const hasPrompt = $('#redesign-prompt').val().trim();
        $('#auto-redesign-btn').prop('disabled', !hasPage || !hasPrompt);
    });

    // Automatic Redesign
    $('#auto-redesign-btn').on('click', function() {
        const pageId = $('#auto-page-select').val();
        const prompt = $('#redesign-prompt').val().trim();
        const duplicate = $('#auto-duplicate').is(':checked');

        if (!pageId || !prompt) {
            alert('Bitte wählen Sie eine Seite aus und geben Sie Anweisungen ein');
            return;
        }

        if (!confirm('Möchten Sie die Seite jetzt mit Claude umgestalten lassen?\n\nDies kann 1-3 Minuten dauern (wegen Rate Limits) und kostet ca. $0.05-0.30.')) {
            return;
        }

        const $btn = $(this);
        $btn.prop('disabled', true);
        $('#auto-result').hide();
        $('#auto-progress').show();
        $('.progress-text').text('Seite wird an Claude gesendet...');

        // Animate progress bar (slower for longer expected duration)
        let progress = 0;
        const progressInterval = setInterval(function() {
            progress += 0.5; // Slower progress
            if (progress > 90) progress = 90; // Stop at 90% until done
            $('.progress-fill').css('width', progress + '%');

            // Update text based on progress
            if (progress > 30 && progress < 60) {
                $('.progress-text').text('Claude analysiert Ihre Seite...');
            } else if (progress >= 60) {
                $('.progress-text').text('Änderungen werden angewendet...');
            }
        }, 1000);

        $.ajax({
            url: elementorAiExport.ajaxUrl,
            type: 'POST',
            timeout: 300000, // 5 minutes timeout (wegen Rate Limit Retries)
            data: {
                action: 'elementor_ai_redesign_auto',
                nonce: elementorAiExport.nonce,
                page_id: pageId,
                prompt: prompt,
                duplicate: duplicate
            },
            success: function(response) {
                clearInterval(progressInterval);
                $('.progress-fill').css('width', '100%');

                setTimeout(function() {
                    $('#auto-progress').hide();
                    $('.progress-fill').css('width', '0%');

                    const $result = $('#auto-result');

                    if (response.success) {
                        $result.removeClass('error').addClass('success')
                            .html(
                                '<h3>✅ Umgestaltung erfolgreich!</h3>' +
                                '<p>' + response.data.message + '</p>' +
                                '<div class="auto-result-actions">' +
                                '<a href="' + response.data.staging_url + '" target="_blank" class="button button-large">Staging-Seite ansehen</a> ' +
                                '<a href="' + response.data.edit_url + '" target="_blank" class="button button-large">In Elementor bearbeiten</a>' +
                                '</div>' +
                                '<p class="description">Die umgestaltete Seite wurde als Staging-Version erstellt. ' +
                                'Prüfen Sie sie und übernehmen Sie die Änderungen im Tab "Staging-Verwaltung".</p>'
                            )
                            .fadeIn();

                        // Clear form
                        $('#redesign-prompt').val('');
                        $('#auto-duplicate').prop('checked', false);
                        $('#auto-redesign-btn').prop('disabled', true);

                        // Reload staging pages
                        loadStagingPages();
                    } else {
                        // Formatiere Fehlermeldung mit Zeilenumbrüchen
                        var errorMessage = response.data.message || 'Unbekannter Fehler';

                        // Ersetze alle \n\n mit <br><br> und einzelne \n mit <br>
                        var formattedMessage = errorMessage
                            .replace(/\\n\\n/g, '<br><br>')  // Doppelte Zeilenumbrüche
                            .replace(/\\n/g, '<br>')         // Einzelne Zeilenumbrüche
                            .replace(/\n\n/g, '<br><br>')    // Falls echte Zeilenumbrüche
                            .replace(/\n/g, '<br>');         // Falls echte Zeilenumbrüche

                        $result.removeClass('success').addClass('error')
                            .html('<h3>⚠️ Hinweis</h3><div style="text-align: left;">' + formattedMessage + '</div>')
                            .fadeIn();
                    }
                }, 500);
            },
            error: function(xhr, status, error) {
                clearInterval(progressInterval);
                $('#auto-progress').hide();
                $('.progress-fill').css('width', '0%');

                var errorMsg = '<h3>❌ Netzwerkfehler</h3>';
                if (status === 'timeout') {
                    errorMsg += '<p>Die Anfrage hat zu lange gedauert. Das kann bei Rate Limits passieren.</p>' +
                               '<p><strong>Lösung:</strong> Warten Sie 60 Sekunden und versuchen Sie es erneut.</p>';
                } else {
                    errorMsg += '<p>Die Verbindung zur Claude API ist fehlgeschlagen.</p>' +
                               '<p>Fehler: ' + error + '</p>';
                }

                $('#auto-result').removeClass('success').addClass('error')
                    .html(errorMsg)
                    .fadeIn();
            },
            complete: function() {
                $btn.prop('disabled', false);
            }
        });
    });

    // Initialize
    loadPages();
    loadStagingPages();
});
