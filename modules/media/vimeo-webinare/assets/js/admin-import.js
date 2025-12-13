/**
 * Vimeo Webinar Batch Import JavaScript
 */

(function($) {
    'use strict';

    const VimeoImport = {
        connected: false,
        selectedFolder: null,

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            $('#test-connection').on('click', this.testConnection.bind(this));
            $('#load-folders').on('click', this.loadFolders.bind(this));
            $('#start-import').on('click', this.startImport.bind(this));

            // Enable/disable auto-punkte input
            $('#auto-punkte').on('change', function() {
                $('#default-punkte').prop('disabled', $(this).is(':checked'));
            });

            // Enable import button when folder is selected
            $('#folder-select').on('change', function() {
                const selected = $(this).val();
                $('#start-import').prop('disabled', !selected);
                VimeoImport.selectedFolder = selected;
            });
        },

        testConnection: function() {
            const token = $('#vimeo-api-token').val().trim();

            if (!token) {
                this.showStatus('Bitte API Token eingeben', 'error');
                return;
            }

            const $button = $('#test-connection');
            const $status = $('#connection-status');

            $button.prop('disabled', true).text('Teste...');
            $status.hide();

            $.ajax({
                url: vwImportData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vw_test_vimeo_connection',
                    nonce: vwImportData.nonce,
                    token: token
                },
                success: (response) => {
                    if (response.success) {
                        this.connected = true;
                        this.showStatus('✓ Verbindung erfolgreich! Benutzer: ' + response.data.user, 'success');
                        $('#load-folders').prop('disabled', false);
                    } else {
                        this.connected = false;
                        this.showStatus('✗ Fehler: ' + response.data.message, 'error');
                    }
                },
                error: () => {
                    this.connected = false;
                    this.showStatus('✗ Verbindungsfehler', 'error');
                },
                complete: () => {
                    $button.prop('disabled', false).text('Verbindung testen');
                }
            });
        },

        showStatus: function(message, type) {
            const $status = $('#connection-status');
            $status.removeClass('success error')
                   .addClass(type)
                   .html(message)
                   .show();
        },

        loadFolders: function() {
            if (!this.connected) {
                alert('Bitte zuerst API-Verbindung testen');
                return;
            }

            const $button = $('#load-folders');
            const $folderList = $('#folder-list');
            const $folderSelect = $('#folder-select');

            $button.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> Lade...');
            $folderSelect.empty();

            $.ajax({
                url: vwImportData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vw_get_vimeo_folders',
                    nonce: vwImportData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        const folders = response.data.folders;

                        if (folders.length === 0) {
                            $folderSelect.append('<option>Keine Ordner gefunden</option>');
                        } else {
                            $folderSelect.append('<option value="">-- Bitte wählen --</option>');

                            folders.forEach(folder => {
                                const name = folder.name || 'Unbenannt';
                                const uri = folder.uri;
                                const videos = folder.metadata?.connections?.videos?.total || 0;

                                $folderSelect.append(
                                    `<option value="${uri}">${name} (${videos} Videos)</option>`
                                );
                            });
                        }

                        $folderList.show();
                    } else {
                        alert('Fehler beim Laden der Ordner: ' + response.data.message);
                    }
                },
                error: () => {
                    alert('Verbindungsfehler beim Laden der Ordner');
                },
                complete: () => {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-category"></span> Ordner laden');
                }
            });
        },

        startImport: function() {
            if (!this.selectedFolder) {
                alert('Bitte Ordner auswählen');
                return;
            }

            if (!confirm('Import starten? Alle Videos aus diesem Ordner werden als Webinar-Entwürfe importiert.')) {
                return;
            }

            const category = $('#import-category').val().trim();
            const autoPunkte = $('#auto-punkte').is(':checked');
            const defaultPunkte = parseInt($('#default-punkte').val()) || 1;

            const $button = $('#start-import');
            const $progress = $('#import-progress');
            const $results = $('#import-results');

            $button.prop('disabled', true);
            $progress.show();
            $results.hide().empty();

            this.updateProgress(0, 'Starte Import...');

            $.ajax({
                url: vwImportData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vw_import_folder_videos',
                    nonce: vwImportData.nonce,
                    folder_uri: this.selectedFolder,
                    category: category,
                    auto_punkte: autoPunkte,
                    default_punkte: defaultPunkte
                },
                success: (response) => {
                    if (response.success) {
                        const data = response.data;
                        this.updateProgress(100, 'Import abgeschlossen!');
                        this.showResults(data);
                    } else {
                        this.updateProgress(0, 'Import fehlgeschlagen');
                        alert('Fehler: ' + response.data.message);
                    }
                },
                error: () => {
                    this.updateProgress(0, 'Verbindungsfehler');
                    alert('Verbindungsfehler beim Import');
                },
                complete: () => {
                    $button.prop('disabled', false);
                }
            });
        },

        updateProgress: function(percent, text) {
            $('.progress-fill').css('width', percent + '%');
            $('.progress-text').text(text);
        },

        showResults: function(data) {
            const $results = $('#import-results');
            let html = '';

            html += '<h3>Import-Ergebnis</h3>';
            html += `<p><strong>${data.total} Videos gefunden</strong></p>`;

            if (data.imported.length > 0) {
                html += '<div class="success-section">';
                html += `<h4 style="color: #155724;">✓ Erfolgreich importiert (${data.imported.length}):</h4>`;
                html += '<ul class="success-list">';
                data.imported.forEach(name => {
                    html += `<li>${this.escapeHtml(name)}</li>`;
                });
                html += '</ul>';
                html += '</div>';
            }

            if (data.skipped.length > 0) {
                html += '<div class="skip-section">';
                html += `<h4 style="color: #856404;">⊗ Übersprungen (${data.skipped.length}):</h4>`;
                html += '<ul class="skip-list">';
                data.skipped.forEach(name => {
                    html += `<li>${this.escapeHtml(name)}</li>`;
                });
                html += '</ul>';
                html += '</div>';
            }

            if (data.errors.length > 0) {
                html += '<div class="error-section">';
                html += `<h4 style="color: #721c24;">✗ Fehler (${data.errors.length}):</h4>`;
                html += '<ul class="error-list">';
                data.errors.forEach(error => {
                    html += `<li>${this.escapeHtml(error)}</li>`;
                });
                html += '</ul>';
                html += '</div>';
            }

            html += '<p style="margin-top: 20px;">';
            html += '<a href="edit.php?post_type=vimeo_webinar&post_status=draft" class="button button-primary">';
            html += 'Zu den importierten Webinaren →';
            html += '</a>';
            html += '</p>';

            $results.html(html).show();
        },

        escapeHtml: function(text) {
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        VimeoImport.init();
    });

})(jQuery);
