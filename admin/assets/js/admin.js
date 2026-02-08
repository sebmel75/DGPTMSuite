/**
 * DGPTM Plugin Suite - Admin JavaScript
 */

(function($) {
    'use strict';

    const dgptmSuiteAdmin = {
        init: function() {
            this.bindEvents();
            this.initSearch();
            this.initCategoryToggle();
            this.initTestVersions();
        },

        bindEvents: function() {
            // Quick Debug-Level
            $(document).on('change', '#dgptm-quick-debug-level', this.setGlobalDebugLevel);

            // Toggle Module
            $(document).on('click', '.dgptm-toggle-module', this.toggleModule);

            // Export Module
            $(document).on('click', '.dgptm-export-module', this.exportModule);

            // Module Info
            $(document).on('click', '.dgptm-module-info', this.showModuleInfo);

            // Reinit Module
            $(document).on('click', '.dgptm-reinit-module', this.reinitModule);

            // Repair Flags
            $(document).on('click', '#dgptm-repair-flags-btn', this.repairFlags);

            // Clear Module Error
            $(document).on('click', '.dgptm-clear-error', this.clearModuleError);

            // Modal Close
            $(document).on('click', '.dgptm-modal-close', this.closeModal);
            $(document).on('click', '.dgptm-modal', function(e) {
                if ($(e.target).hasClass('dgptm-modal')) {
                    dgptmSuiteAdmin.closeModal();
                }
            });

            // Select All
            $(document).on('change', '.dgptm-select-all', this.selectAll);
        },

        initSearch: function() {
            const $searchInput = $('#dgptm-module-search');
            const $categoryFilter = $('#dgptm-category-filter');
            const $statusFilter = $('#dgptm-status-filter');

            function filterModules() {
                const searchTerm = $searchInput.val().toLowerCase();
                const selectedCategory = $categoryFilter.val();
                const selectedStatus = $statusFilter.val();

                $('.dgptm-module-row').each(function() {
                    const $row = $(this);
                    const moduleName = $row.find('strong').text().toLowerCase();
                    const moduleId = $row.data('module-id');
                    const moduleStatus = $row.data('status');
                    const $section = $row.closest('.dgptm-category-section');
                    const moduleCategory = $section.data('category');

                    let show = true;

                    // Search term
                    if (searchTerm && !moduleName.includes(searchTerm) && !moduleId.includes(searchTerm)) {
                        show = false;
                    }

                    // Category filter
                    if (selectedCategory && moduleCategory !== selectedCategory) {
                        show = false;
                    }

                    // Status filter
                    if (selectedStatus) {
                        if (selectedStatus === 'active' && moduleStatus !== 'active') {
                            show = false;
                        }
                        if (selectedStatus === 'inactive' && moduleStatus !== 'inactive') {
                            show = false;
                        }
                    }

                    $row.toggle(show);
                });

                // Hide empty categories
                $('.dgptm-category-section').each(function() {
                    const $section = $(this);
                    const visibleRows = $section.find('.dgptm-module-row:visible').length;
                    $section.toggle(visibleRows > 0);
                });
            }

            $searchInput.on('input', filterModules);
            $categoryFilter.on('change', filterModules);
            $statusFilter.on('change', filterModules);
        },

        initCategoryToggle: function() {
            $(document).on('click', '.dgptm-category-title', function() {
                $(this).closest('.dgptm-category-section').toggleClass('collapsed');
            });
        },

        toggleModule: function(e) {
            e.preventDefault();

            const $button = $(this);
            const moduleId = $button.data('module-id');
            const isActive = $button.data('active') === 1;
            const action = !isActive;

            // Nur bei Deaktivierung Bestätigung fragen
            if (isActive && !confirm(dgptmSuite.strings.confirm_deactivate)) {
                return;
            }

            $button.addClass('dgptm-loading').prop('disabled', true);

            console.log('DGPTM: Toggle module', moduleId, 'activate:', action);

            $.ajax({
                url: dgptmSuite.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_toggle_module',
                    nonce: dgptmSuite.nonce,
                    module_id: moduleId,
                    activate: action
                },
                success: function(response) {
                    console.log('DGPTM: AJAX Response:', response);

                    if (response.success) {
                        console.log('DGPTM: Module toggle erfolgreich!', response.data);

                        // Button aktualisieren
                        $button.data('active', action ? 1 : 0);
                        $button.text(action ? dgptmSuite.strings.deactivate : dgptmSuite.strings.activate);

                        // Status-Badge aktualisieren
                        const $row = $button.closest('tr');
                        const $statusCell = $row.find('td').eq(4);
                        const $statusBadge = $statusCell.find('.dgptm-status-badge').first();

                        if (action) {
                            $statusBadge.removeClass('dgptm-status-inactive').addClass('dgptm-status-active').text('Active');
                            $row.data('status', 'active');
                        } else {
                            $statusBadge.removeClass('dgptm-status-active').addClass('dgptm-status-inactive').text('Inactive');
                            $row.data('status', 'inactive');
                        }

                        // Seite neu laden für vollständige Aktualisierung
                        console.log('DGPTM: Lade Seite neu in 500ms...');
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    } else {
                        console.error('DGPTM: Fehler beim Toggle:', response.data);
                        alert(response.data.message || 'Error');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('DGPTM: AJAX error', textStatus, errorThrown);
                    console.error('DGPTM: Response:', jqXHR.responseText);
                    alert('AJAX error: ' + textStatus);
                },
                complete: function() {
                    $button.removeClass('dgptm-loading').prop('disabled', false);
                }
            });
        },

        exportModule: function(e) {
            e.preventDefault();

            const $button = $(this);
            const moduleId = $button.data('module-id');

            if (!confirm(dgptmSuite.strings.confirm_export)) {
                return;
            }

            $button.addClass('dgptm-loading').prop('disabled', true);

            $.ajax({
                url: dgptmSuite.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_export_module',
                    nonce: dgptmSuite.nonce,
                    module_id: moduleId
                },
                success: function(response) {
                    if (response.success) {
                        // Download starten
                        window.location.href = response.data.download_url;
                        alert('Export successful! Download started.');
                    } else {
                        alert(response.data.message || 'Export failed');
                    }
                },
                error: function() {
                    alert('AJAX error');
                },
                complete: function() {
                    $button.removeClass('dgptm-loading').prop('disabled', false);
                }
            });
        },

        showModuleInfo: function(e) {
            e.preventDefault();

            const $button = $(this);
            const moduleId = $button.data('module-id');

            $button.addClass('dgptm-loading').prop('disabled', true);

            $.ajax({
                url: dgptmSuite.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_get_module_info',
                    nonce: dgptmSuite.nonce,
                    module_id: moduleId
                },
                success: function(response) {
                    if (response.success) {
                        dgptmSuiteAdmin.displayModuleInfo(response.data);
                    } else {
                        alert(response.data.message || 'Error');
                    }
                },
                error: function() {
                    alert('AJAX error');
                },
                complete: function() {
                    $button.removeClass('dgptm-loading').prop('disabled', false);
                }
            });
        },

        reinitModule: function(e) {
            e.preventDefault();

            const $button = $(this);
            const moduleId = $button.data('module-id');

            // Bestätigung vom Benutzer einholen
            if (!confirm('Modul "' + moduleId + '" neu initialisieren?\n\nDies führt folgende Aktionen aus:\n- Aktivierungs-Hooks werden ausgeführt\n- Permalinks werden aktualisiert\n- Custom Post Types und Taxonomien werden neu registriert\n\nFortfahren?')) {
                return;
            }

            $button.addClass('dgptm-loading').prop('disabled', true);
            $button.find('.dashicons').addClass('spin');

            console.log('DGPTM: Reinit module', moduleId);

            $.ajax({
                url: dgptmSuite.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_reinit_module',
                    nonce: dgptmSuite.nonce,
                    module_id: moduleId
                },
                success: function(response) {
                    console.log('DGPTM Reinit Response:', response);

                    if (response.success) {
                        // Erfolgsmeldung anzeigen
                        const message = response.data.message || 'Modul wurde erfolgreich neu initialisiert!';
                        alert('✅ ' + message + '\n\n' +
                              'Details:\n' +
                              '- Aktivierungs-Hook: ' + (response.data.activation_hook_executed ? 'Ja' : 'Nein') + '\n' +
                              '- Permalinks aktualisiert: ' + (response.data.permalinks_flushed ? 'Ja' : 'Nein')
                        );

                        // Seite neu laden
                        console.log('DGPTM: Lade Seite neu in 500ms...');
                        setTimeout(function() {
                            location.reload();
                        }, 500);
                    } else {
                        console.error('DGPTM: Fehler beim Reinit:', response.data);
                        alert('❌ Fehler beim Neu-Initialisieren:\n\n' + (response.data.message || 'Unbekannter Fehler'));
                        $button.removeClass('dgptm-loading').prop('disabled', false);
                        $button.find('.dashicons').removeClass('spin');
                    }
                },
                error: function(jqXHR, textStatus, errorThrown) {
                    console.error('DGPTM: AJAX error', textStatus, errorThrown);
                    console.error('DGPTM: Response:', jqXHR.responseText);
                    alert('❌ AJAX-Fehler beim Neu-Initialisieren:\n\n' + textStatus);
                    $button.removeClass('dgptm-loading').prop('disabled', false);
                    $button.find('.dashicons').removeClass('spin');
                }
            });
        },

        displayModuleInfo: function(data) {
            const config = data.config;
            const deps = data.dependencies;

            let html = '<h2>' + config.name + '</h2>';
            html += '<p>' + (config.description || '') + '</p>';
            html += '<table class="widefat">';
            html += '<tr><th>ID:</th><td>' + config.id + '</td></tr>';
            html += '<tr><th>Version:</th><td>' + config.version + '</td></tr>';
            html += '<tr><th>Author:</th><td>' + config.author + '</td></tr>';
            html += '<tr><th>Category:</th><td>' + config.category + '</td></tr>';

            if (config.dependencies && config.dependencies.length > 0) {
                html += '<tr><th>Dependencies:</th><td>' + config.dependencies.join(', ') + '</td></tr>';
            }

            if (config.wp_dependencies && config.wp_dependencies.plugins && config.wp_dependencies.plugins.length > 0) {
                html += '<tr><th>WP Plugins:</th><td>' + config.wp_dependencies.plugins.join(', ') + '</td></tr>';
            }

            html += '</table>';

            $('#dgptm-module-info-content').html(html);
            $('#dgptm-module-info-modal').fadeIn();
        },

        closeModal: function() {
            $('.dgptm-modal').fadeOut();
        },

        selectAll: function() {
            const $checkbox = $(this);
            const $table = $checkbox.closest('table');
            $table.find('input[name="modules[]"]').prop('checked', $checkbox.prop('checked'));
        },

        repairFlags: function(e) {
            e.preventDefault();
            const $button = $(this);
            const $result = $('#dgptm-repair-result');

            if (!confirm('Module-Flags reparieren?\n\nDies normalisiert alle Flag-Daten in den module.json Dateien und behebt Fehler aus älteren Versionen.')) {
                return;
            }

            $button.prop('disabled', true).text('Repariere...');
            $result.hide();

            $.ajax({
                url: dgptmSuite.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_repair_flags',
                    nonce: dgptmSuite.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $result
                            .removeClass('notice-error')
                            .addClass('notice notice-success')
                            .html('<p><strong>Erfolg:</strong> ' + response.data.message + '</p>')
                            .show();

                        if (response.data.repaired > 0) {
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        }
                    } else {
                        $result
                            .removeClass('notice-success')
                            .addClass('notice notice-error')
                            .html('<p><strong>Fehler:</strong> ' + response.data.message + '</p>')
                            .show();
                    }
                },
                error: function() {
                    $result
                        .removeClass('notice-success')
                        .addClass('notice notice-error')
                        .html('<p><strong>Fehler:</strong> Netzwerkfehler beim Reparieren.</p>')
                        .show();
                },
                complete: function() {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-admin-tools" style="margin-top: 3px;"></span> Module-Flags reparieren');
                }
            });
        },

        clearModuleError: function(e) {
            e.preventDefault();
            const $button = $(this);
            const moduleId = $button.data('module-id');

            if (!confirm('Fehler-Eintrag für Modul "' + moduleId + '" löschen?\n\nDanach können Sie das Modul erneut aktivieren.')) {
                return;
            }

            $button.prop('disabled', true).text('Lösche...');

            $.ajax({
                url: dgptmSuite.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_clear_module_error',
                    module_id: moduleId,
                    nonce: dgptmSuite.nonce
                },
                success: function(response) {
                    if (response.success) {
                        alert(response.data.message);
                        location.reload();
                    } else {
                        alert(response.data.message || 'Fehler beim Löschen');
                        $button.prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> Fehler löschen');
                    }
                },
                error: function() {
                    alert('AJAX-Fehler beim Löschen des Fehler-Eintrags');
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-dismiss"></span> Fehler löschen');
                }
            });
        },

        setGlobalDebugLevel: function() {
            var $select = $(this);
            var level = $select.val();
            var levelColors = {
                verbose: '#9cdcfe',
                info: '#4ec9b0',
                warning: '#dcdcaa',
                error: '#f48771',
                critical: '#d63638'
            };

            $select.prop('disabled', true);

            $.ajax({
                url: dgptmSuite.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_set_global_debug_level',
                    nonce: dgptmSuite.nonce,
                    level: level
                },
                success: function(response) {
                    if (response.success) {
                        var color = levelColors[level] || '#999';
                        $select.css({
                            'border-color': color,
                            'background-color': color + '20'
                        });
                        dgptmSuiteAdmin.showTestFeedback('success', response.data.message);
                    } else {
                        dgptmSuiteAdmin.showTestFeedback('error', response.data.message);
                    }
                },
                error: function() {
                    dgptmSuiteAdmin.showTestFeedback('error', 'AJAX-Fehler beim Setzen des Debug-Levels.');
                },
                complete: function() {
                    $select.prop('disabled', false);
                }
            });
        },

        /* =================================================================
           Test-Version Management
           ================================================================= */

        initTestVersions: function() {
            // Event-Handler für Test-Versionen
            $(document).on('click', '.dgptm-create-test', this.createTestVersion);
            $(document).on('click', '.dgptm-merge-test', this.mergeTestVersion);
            $(document).on('click', '.dgptm-delete-test', this.deleteTestVersion);
        },

        createTestVersion: function(e) {
            e.preventDefault();
            const $button = $(this);
            const moduleId = $button.data('module-id');

            if (!confirm('Testversion für "' + moduleId + '" erstellen?\n\nEs wird eine vollständige Kopie des Moduls erstellt und automatisch verknüpft.')) {
                return;
            }

            // Loading State
            $button.prop('disabled', true).addClass('dgptm-test-loading');

            $.ajax({
                url: dgptmSuite.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_create_test_version',
                    module_id: moduleId,
                    nonce: dgptmSuite.nonce
                },
                success: function(response) {
                    if (response.success) {
                        dgptmSuiteAdmin.showTestFeedback('success', response.data.message);
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        dgptmSuiteAdmin.showTestFeedback('error', response.data.message);
                        $button.prop('disabled', false).removeClass('dgptm-test-loading');
                    }
                },
                error: function() {
                    dgptmSuiteAdmin.showTestFeedback('error', 'AJAX-Fehler beim Erstellen der Testversion.');
                    $button.prop('disabled', false).removeClass('dgptm-test-loading');
                }
            });
        },

        mergeTestVersion: function(e) {
            e.preventDefault();
            const $button = $(this);
            const testId = $button.data('test-id');
            const mainId = $button.data('main-id');

            if (!confirm('Testversion in Hauptversion mergen?\n\n⚠️ WARNUNG:\n- Die Hauptversion "' + mainId + '" wird überschrieben\n- Die Testversion "' + testId + '" wird gelöscht\n- Ein Backup der Hauptversion wird erstellt\n\nFortfahren?')) {
                return;
            }

            // Loading State
            $button.prop('disabled', true).addClass('dgptm-test-loading');

            $.ajax({
                url: dgptmSuite.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_merge_test_version',
                    test_id: testId,
                    nonce: dgptmSuite.nonce
                },
                success: function(response) {
                    if (response.success) {
                        dgptmSuiteAdmin.showTestFeedback('success', response.data.message);
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        dgptmSuiteAdmin.showTestFeedback('error', response.data.message);
                        $button.prop('disabled', false).removeClass('dgptm-test-loading');
                    }
                },
                error: function() {
                    dgptmSuiteAdmin.showTestFeedback('error', 'AJAX-Fehler beim Mergen.');
                    $button.prop('disabled', false).removeClass('dgptm-test-loading');
                }
            });
        },

        deleteTestVersion: function(e) {
            e.preventDefault();
            const $button = $(this);
            const testId = $button.data('test-id');
            const mainId = $button.data('main-id');

            if (!confirm('Testversion "' + testId + '" wirklich löschen?\n\nDie Hauptversion "' + mainId + '" bleibt unverändert.')) {
                return;
            }

            // Loading State
            $button.prop('disabled', true).addClass('dgptm-test-loading');

            $.ajax({
                url: dgptmSuite.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_delete_test_version',
                    test_id: testId,
                    nonce: dgptmSuite.nonce
                },
                success: function(response) {
                    if (response.success) {
                        dgptmSuiteAdmin.showTestFeedback('success', response.data.message);
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        dgptmSuiteAdmin.showTestFeedback('error', response.data.message);
                        $button.prop('disabled', false).removeClass('dgptm-test-loading');
                    }
                },
                error: function() {
                    dgptmSuiteAdmin.showTestFeedback('error', 'AJAX-Fehler beim Löschen.');
                    $button.prop('disabled', false).removeClass('dgptm-test-loading');
                }
            });
        },

        showTestFeedback: function(type, message) {
            const $feedback = $('<div class="dgptm-test-feedback ' + type + '">' + message + '</div>');
            $('body').append($feedback);

            setTimeout(function() {
                $feedback.fadeOut(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    // Init on document ready
    $(document).ready(function() {
        dgptmSuiteAdmin.init();
    });

})(jQuery);
