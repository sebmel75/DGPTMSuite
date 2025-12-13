/**
 * Elementor Doctor Admin JavaScript
 */

(function($) {
    'use strict';

    const ElementorDoctor = {
        batchResults: [],
        currentBatchPage: 1,
        totalBatchPages: 0,
        stats: {
            total: 0,
            valid: 0,
            errors: 0,
            warnings: 0
        },

        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Tab switching
            $('.nav-tab').on('click', this.switchTab.bind(this));

            // Single page scan
            $('#scan-single').on('click', this.scanSingle.bind(this));

            // Batch scan
            $('#scan-all').on('click', this.startBatchScan.bind(this));

            // Batch repair
            $('#repair-all').on('click', this.startBatchRepair.bind(this));

            // Backups
            $('#load-all-backups').on('click', this.loadAllBackups.bind(this));
            $('#refresh-backups').on('click', this.loadAllBackups.bind(this));

            // Single page repair (delegated event)
            $(document).on('click', '.repair-single', this.repairSingle.bind(this));

            // View backups (delegated event)
            $(document).on('click', '.view-backups', this.viewBackups.bind(this));

            // Restore backup (delegated event)
            $(document).on('click', '.restore-backup', this.restoreBackup.bind(this));
        },

        switchTab: function(e) {
            e.preventDefault();
            const target = $(e.currentTarget).attr('href');

            $('.nav-tab').removeClass('nav-tab-active');
            $(e.currentTarget).addClass('nav-tab-active');

            $('.elementor-doctor-tab-content').removeClass('active');
            $(target).addClass('active');
        },

        scanSingle: function() {
            const postId = $('#page-selector').val();

            if (!postId) {
                alert('Bitte wähle eine Seite aus.');
                return;
            }

            const $button = $('#scan-single');
            $button.prop('disabled', true).text(elementorDoctorData.strings.scanning);

            $.ajax({
                url: elementorDoctorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'elementor_doctor_scan_page',
                    nonce: elementorDoctorData.nonce,
                    post_id: postId
                },
                success: (response) => {
                    if (response.success) {
                        this.displaySingleResults(response.data);
                    } else {
                        alert('Fehler: ' + response.data.message);
                    }
                },
                error: () => {
                    alert('Verbindungsfehler beim Scannen.');
                },
                complete: () => {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> Scannen');
                }
            });
        },

        displaySingleResults: function(data) {
            const results = data.results;
            const $container = $('#single-scan-results');

            let html = '<div class="result-header' + (!results.is_valid ? ' has-errors' : '') + '">';
            html += '<h3>' + results.post_title + '</h3>';
            html += '<p><a href="' + results.post_url + '" target="_blank">Seite ansehen</a></p>';

            if (results.is_valid) {
                html += '<p style="color: #00a32a;"><strong>✓ Keine Fehler gefunden!</strong></p>';
            } else {
                html += '<p style="color: #d63638;"><strong>⚠ Fehler gefunden!</strong></p>';
            }
            html += '</div>';

            html += '<div class="result-details">';

            // Errors
            if (results.errors.length > 0) {
                html += '<h4 style="color: #d63638;">Fehler (' + results.errors.length + '):</h4>';
                html += '<ul class="error-list">';
                results.errors.forEach(error => {
                    html += '<li>' + this.escapeHtml(error) + '</li>';
                });
                html += '</ul>';
            }

            // Warnings
            if (results.warnings.length > 0) {
                html += '<h4 style="color: #dba617;">Warnungen (' + results.warnings.length + '):</h4>';
                html += '<ul class="warning-list">';
                results.warnings.forEach(warning => {
                    html += '<li>' + this.escapeHtml(warning) + '</li>';
                });
                html += '</ul>';
            }

            // Info
            if (results.info.length > 0) {
                html += '<h4>Informationen:</h4>';
                html += '<ul class="info-list">';
                results.info.forEach(info => {
                    html += '<li>' + this.escapeHtml(info) + '</li>';
                });
                html += '</ul>';
            }

            html += '</div>';

            // Actions
            if (!results.is_valid) {
                html += '<div class="result-actions">';
                html += '<button class="button button-primary repair-single" data-post-id="' + results.post_id + '">';
                html += '<span class="dashicons dashicons-admin-tools"></span> Seite reparieren';
                html += '</button>';
                html += '<p class="description" style="margin-top: 10px;">Ein Backup wird automatisch erstellt.</p>';
                html += '</div>';
            }

            $container.html(html).show();
        },

        repairSingle: function(e) {
            const postId = $(e.currentTarget).data('post-id');

            if (!confirm(elementorDoctorData.strings.confirmRepair)) {
                return;
            }

            const $button = $(e.currentTarget);
            $button.prop('disabled', true).text(elementorDoctorData.strings.repairing);

            $.ajax({
                url: elementorDoctorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'elementor_doctor_repair_page',
                    nonce: elementorDoctorData.nonce,
                    post_id: postId
                },
                success: (response) => {
                    if (response.success) {
                        alert('Reparatur erfolgreich!\n\nDurchgeführte Reparaturen:\n- ' + response.data.repairs.join('\n- '));
                        // Re-scan to show updated results
                        $('#scan-single').click();
                    } else {
                        alert('Reparatur fehlgeschlagen: ' + response.data.message);
                    }
                },
                error: () => {
                    alert('Verbindungsfehler beim Reparieren.');
                },
                complete: () => {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools"></span> Seite reparieren');
                }
            });
        },

        startBatchScan: function() {
            // Reset state
            this.batchResults = [];
            this.currentBatchPage = 1;
            this.totalBatchPages = 0;
            this.stats = { total: 0, valid: 0, errors: 0, warnings: 0 };

            // Show progress
            $('#batch-progress').show();
            $('#batch-stats').show();
            $('#batch-results').empty();
            $('#scan-all').prop('disabled', true);
            $('#repair-all').hide();

            this.updateProgress(0, 'Starte Scan...');
            this.processBatchScan();
        },

        processBatchScan: function() {
            $.ajax({
                url: elementorDoctorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'elementor_doctor_scan_all',
                    nonce: elementorDoctorData.nonce,
                    page: this.currentBatchPage
                },
                success: (response) => {
                    if (response.success) {
                        const data = response.data;
                        this.totalBatchPages = data.total_pages;

                        // Process results
                        data.items.forEach(item => {
                            this.batchResults.push(item);
                            this.stats.total++;

                            if (item.is_valid) {
                                this.stats.valid++;
                            } else if (item.errors.length > 0) {
                                this.stats.errors++;
                            } else if (item.warnings.length > 0) {
                                this.stats.warnings++;
                            }
                        });

                        // Update UI
                        const progress = (this.currentBatchPage / this.totalBatchPages) * 100;
                        this.updateProgress(progress, 'Scanne Seite ' + this.currentBatchPage + ' von ' + this.totalBatchPages + '...');
                        this.updateStats();

                        // Continue or finish
                        if (data.has_more) {
                            this.currentBatchPage++;
                            this.processBatchScan();
                        } else {
                            this.finishBatchScan();
                        }
                    } else {
                        alert('Fehler beim Batch-Scan: ' + response.data.message);
                        this.resetBatchUI();
                    }
                },
                error: () => {
                    alert('Verbindungsfehler beim Batch-Scan.');
                    this.resetBatchUI();
                }
            });
        },

        finishBatchScan: function() {
            this.updateProgress(100, 'Scan abgeschlossen!');
            this.displayBatchResults();

            $('#scan-all').prop('disabled', false);

            if (this.stats.errors > 0) {
                $('#repair-all').show();
            }
        },

        updateProgress: function(percent, status) {
            $('.progress-bar-fill').css('width', percent + '%');
            $('.progress-text').text(Math.round(percent) + '%');
            $('.progress-status').text(status);
        },

        updateStats: function() {
            $('.stat-total .stat-number').text(this.stats.total);
            $('.stat-valid .stat-number').text(this.stats.valid);
            $('.stat-errors .stat-number').text(this.stats.errors);
            $('.stat-warnings .stat-number').text(this.stats.warnings);
        },

        displayBatchResults: function() {
            let html = '<table class="wp-list-table widefat fixed striped">';
            html += '<thead><tr>';
            html += '<th width="40">Status</th>';
            html += '<th>Seite</th>';
            html += '<th width="80">Fehler</th>';
            html += '<th width="80">Warnungen</th>';
            html += '<th width="150">Aktionen</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            this.batchResults.forEach(result => {
                let statusIcon = '';
                let statusClass = '';

                if (result.is_valid) {
                    statusIcon = '✓';
                    statusClass = 'valid';
                } else if (result.errors.length > 0) {
                    statusIcon = '✗';
                    statusClass = 'error';
                } else {
                    statusIcon = '⚠';
                    statusClass = 'warning';
                }

                html += '<tr>';
                html += '<td><span class="status-icon ' + statusClass + '">' + statusIcon + '</span></td>';
                html += '<td><strong>' + this.escapeHtml(result.post_title) + '</strong><br>';
                html += '<a href="' + result.post_url + '" target="_blank">Ansehen</a> | ';
                html += '<a href="post.php?post=' + result.post_id + '&action=elementor">Bearbeiten</a></td>';
                html += '<td>' + result.errors.length + '</td>';
                html += '<td>' + result.warnings.length + '</td>';
                html += '<td>';

                if (!result.is_valid) {
                    html += '<button class="button button-small repair-single" data-post-id="' + result.post_id + '">Reparieren</button>';
                }

                html += '</td>';
                html += '</tr>';

                // Details row
                if (result.errors.length > 0 || result.warnings.length > 0) {
                    html += '<tr class="details-row"><td colspan="5"><div style="padding: 10px; background: #f9f9f9;">';

                    if (result.errors.length > 0) {
                        html += '<strong style="color: #d63638;">Fehler:</strong><ul style="margin: 5px 0;">';
                        result.errors.forEach(error => {
                            html += '<li>' + this.escapeHtml(error) + '</li>';
                        });
                        html += '</ul>';
                    }

                    if (result.warnings.length > 0) {
                        html += '<strong style="color: #dba617;">Warnungen:</strong><ul style="margin: 5px 0;">';
                        result.warnings.forEach(warning => {
                            html += '<li>' + this.escapeHtml(warning) + '</li>';
                        });
                        html += '</ul>';
                    }

                    html += '</div></td></tr>';
                }
            });

            html += '</tbody></table>';

            $('#batch-results').html(html);
        },

        startBatchRepair: function() {
            if (!confirm(elementorDoctorData.strings.confirmBatchRepair)) {
                return;
            }

            const errorPages = this.batchResults.filter(r => !r.is_valid);
            let repaired = 0;
            let failed = 0;

            const repairNext = (index) => {
                if (index >= errorPages.length) {
                    alert('Batch-Reparatur abgeschlossen!\n\nRepariert: ' + repaired + '\nFehlgeschlagen: ' + failed);
                    this.startBatchScan(); // Re-scan
                    return;
                }

                const page = errorPages[index];
                this.updateProgress(
                    (index / errorPages.length) * 100,
                    'Repariere Seite ' + (index + 1) + ' von ' + errorPages.length + '...'
                );

                $.ajax({
                    url: elementorDoctorData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'elementor_doctor_repair_page',
                        nonce: elementorDoctorData.nonce,
                        post_id: page.post_id
                    },
                    success: (response) => {
                        if (response.success) {
                            repaired++;
                        } else {
                            failed++;
                        }
                    },
                    error: () => {
                        failed++;
                    },
                    complete: () => {
                        repairNext(index + 1);
                    }
                });
            };

            $('#repair-all').prop('disabled', true);
            repairNext(0);
        },

        viewBackups: function(e) {
            const postId = $(e.currentTarget).data('post-id');

            $.ajax({
                url: elementorDoctorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'elementor_doctor_get_backup',
                    nonce: elementorDoctorData.nonce,
                    post_id: postId
                },
                success: (response) => {
                    if (response.success) {
                        this.displayBackups(response.data.backups);
                    } else {
                        alert('Fehler beim Laden der Backups: ' + response.data.message);
                    }
                },
                error: () => {
                    alert('Verbindungsfehler beim Laden der Backups.');
                }
            });
        },

        displayBackups: function(backups) {
            let html = '<h3>Verfügbare Backups</h3>';

            if (backups.length === 0) {
                html += '<p>Keine Backups gefunden.</p>';
            } else {
                html += '<table class="wp-list-table widefat">';
                html += '<thead><tr><th>Datum</th><th>Aktionen</th></tr></thead>';
                html += '<tbody>';

                backups.forEach(backup => {
                    html += '<tr>';
                    html += '<td>' + this.escapeHtml(backup.date) + '</td>';
                    html += '<td><button class="button restore-backup" data-backup-id="' + backup.id + '">Wiederherstellen</button></td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
            }

            $('#backup-list').html(html);
        },

        loadAllBackups: function() {
            $('#backup-loading').show();
            $('#backup-list').html('');
            $('#load-all-backups').prop('disabled', true);

            $.ajax({
                url: elementorDoctorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'elementor_doctor_get_all_backups',
                    nonce: elementorDoctorData.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.displayAllBackups(response.data.backups);
                        $('#refresh-backups').show();
                    } else {
                        alert('Fehler beim Laden der Backups: ' + response.data.message);
                        $('#backup-list').html('<p class="description">Fehler beim Laden der Backups.</p>');
                    }
                },
                error: () => {
                    alert('Verbindungsfehler beim Laden der Backups.');
                    $('#backup-list').html('<p class="description">Verbindungsfehler.</p>');
                },
                complete: () => {
                    $('#backup-loading').hide();
                    $('#load-all-backups').prop('disabled', false);
                }
            });
        },

        displayAllBackups: function(backups) {
            let html = '';

            if (backups.length === 0) {
                html = '<div class="notice notice-info inline"><p><strong>Keine Backups gefunden.</strong></p><p>Backups werden automatisch erstellt, wenn du Seiten reparierst.</p></div>';
            } else {
                html = '<div class="notice notice-success inline" style="margin-bottom: 20px;">';
                html += '<p><strong>' + backups.length + ' Backup(s) gefunden</strong></p>';
                html += '</div>';

                html += '<table class="wp-list-table widefat fixed striped">';
                html += '<thead><tr>';
                html += '<th width="30%">Original-Seite</th>';
                html += '<th width="20%">Backup-Datum</th>';
                html += '<th width="15%">Status</th>';
                html += '<th width="20%">Aktionen</th>';
                html += '</tr></thead>';
                html += '<tbody>';

                backups.forEach(backup => {
                    let statusBadge = '';
                    let canRestore = false;

                    if (backup.original_post_status === 'deleted') {
                        statusBadge = '<span style="color: #d63638;">❌ Gelöscht</span>';
                    } else if (backup.original_post_status === 'publish') {
                        statusBadge = '<span style="color: #00a32a;">✓ Veröffentlicht</span>';
                        canRestore = true;
                    } else if (backup.original_post_status === 'draft') {
                        statusBadge = '<span style="color: #dba617;">📝 Entwurf</span>';
                        canRestore = true;
                    } else {
                        statusBadge = '<span style="color: #646970;">' + this.escapeHtml(backup.original_post_status) + '</span>';
                        canRestore = true;
                    }

                    html += '<tr>';
                    html += '<td>';
                    html += '<strong>' + this.escapeHtml(backup.original_post_title || 'Unbekannt') + '</strong><br>';
                    if (backup.original_post_url) {
                        html += '<a href="' + backup.original_post_url + '" target="_blank">Ansehen</a> | ';
                        html += '<a href="' + backup.original_post_edit_url + '">Bearbeiten</a>';
                    } else {
                        html += '<em style="color: #646970;">Seite wurde gelöscht</em>';
                    }
                    html += '</td>';
                    html += '<td>' + this.escapeHtml(backup.backup_date || backup.date) + '</td>';
                    html += '<td>' + statusBadge + '</td>';
                    html += '<td>';

                    if (canRestore) {
                        html += '<button class="button button-primary button-small restore-backup" data-backup-id="' + backup.id + '">';
                        html += '<span class="dashicons dashicons-backup" style="font-size: 16px; width: 16px; height: 16px; vertical-align: middle;"></span> ';
                        html += 'Wiederherstellen';
                        html += '</button>';
                    } else {
                        html += '<em style="color: #646970;">Nicht wiederherstellbar (Seite gelöscht)</em>';
                    }

                    html += '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
            }

            $('#backup-list').html(html);
        },

        restoreBackup: function(e) {
            if (!confirm('Möchtest du dieses Backup wirklich wiederherstellen?')) {
                return;
            }

            const backupId = $(e.currentTarget).data('backup-id');
            const $button = $(e.currentTarget);

            $button.prop('disabled', true).text('Wird wiederhergestellt...');

            $.ajax({
                url: elementorDoctorData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'elementor_doctor_restore_backup',
                    nonce: elementorDoctorData.nonce,
                    backup_id: backupId
                },
                success: (response) => {
                    if (response.success) {
                        alert('Backup erfolgreich wiederhergestellt!');
                        location.reload();
                    } else {
                        alert('Fehler beim Wiederherstellen: ' + response.data.message);
                    }
                },
                error: () => {
                    alert('Verbindungsfehler beim Wiederherstellen.');
                },
                complete: () => {
                    $button.prop('disabled', false).text('Wiederherstellen');
                }
            });
        },

        resetBatchUI: function() {
            $('#scan-all').prop('disabled', false);
            $('#batch-progress').hide();
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
        ElementorDoctor.init();
    });

})(jQuery);
