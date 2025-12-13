/**
 * Module Metadata JavaScript
 * Handles Flags, Comments, and Version Switching
 */

(function($) {
    'use strict';

    let currentModuleId = '';
    let allModules = [];

    // Init
    $(document).ready(function() {
        // Sammle alle Module für Dropdown
        $('.dgptm-module-row').each(function() {
            const $row = $(this);
            allModules.push({
                id: $row.data('module-id'),
                name: $row.find('td:eq(0) strong').text()
            });
        });

        // Module-Info Button Click
        $(document).on('click', '.dgptm-module-info-btn', function(e) {
            e.preventDefault();
            const moduleId = $(this).data('module-id');
            const moduleName = $(this).closest('tr').find('td:eq(0) strong').text();
            openMetadataModal(moduleId, moduleName);
        });

        // Modal Close
        $('.dgptm-modal-close, .dgptm-modal-overlay').on('click', function() {
            $('#dgptm-metadata-modal').fadeOut(200);
        });

        // Add Flag
        $('#dgptm-add-flag-btn').on('click', function() {
            const flagType = $('#dgptm-flag-type').val();
            const label = $('#dgptm-flag-label').val();

            if (!flagType) {
                alert('Bitte wählen Sie einen Flag-Typ aus.');
                return;
            }

            addFlag(currentModuleId, flagType, label);
        });

        // Remove Flag (delegated)
        $(document).on('click', '.dgptm-flag-remove', function(e) {
            e.stopPropagation();
            const flagType = $(this).data('flag-type');
            removeFlag(currentModuleId, flagType);
        });

        // Save Comment
        $('#dgptm-save-comment-btn').on('click', function() {
            const comment = $('#dgptm-module-comment').val();
            saveComment(currentModuleId, comment);
        });

        // Switch Version
        $('#dgptm-switch-version-btn').on('click', function() {
            switchVersion(currentModuleId);
        });

        // Link Test Version
        $('#dgptm-link-version-btn').on('click', function() {
            const testModuleId = $('#dgptm-test-module-select').val();
            if (!testModuleId) {
                alert('Bitte wählen Sie ein Modul aus.');
                return;
            }
            linkTestVersion(currentModuleId, testModuleId);
        });
    });

    /**
     * Open Metadata Modal
     */
    function openMetadataModal(moduleId, moduleName) {
        currentModuleId = moduleId;

        $('#dgptm-metadata-module-name').text(moduleName);
        $('#dgptm-metadata-module-id').text(moduleId);

        // Load current data
        loadModuleMetadata(moduleId);

        // Show modal
        $('#dgptm-metadata-modal').fadeIn(200);
    }

    /**
     * Load Module Metadata via AJAX
     */
    function loadModuleMetadata(moduleId) {
        $.ajax({
            url: dgptmSuite.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_get_module_info',
                nonce: dgptmSuite.nonce,
                module_id: moduleId
            },
            success: function(response) {
                if (response.success && response.data.metadata) {
                    const metadata = response.data.metadata;

                    // Display Flags
                    displayFlags(metadata.flags || []);

                    // Display Comment
                    $('#dgptm-module-comment').val(metadata.comment || '');

                    // Display Version Status
                    displayVersionStatus(metadata);

                    // Populate Test Module Dropdown
                    populateTestModuleDropdown(moduleId, metadata);
                }
            }
        });
    }

    /**
     * Display Flags
     */
    function displayFlags(flags) {
        const $container = $('#dgptm-current-flags');
        $container.empty();

        if (flags.length === 0) {
            $container.html('<p class="dgptm-no-flags">Keine Flags gesetzt</p>');
            return;
        }

        flags.forEach(function(flag) {
            const badgeClass = getFlagBadgeClass(flag.type);
            const $flag = $(`
                <span class="dgptm-flag ${badgeClass}">
                    ${flag.label}
                    <button type="button" class="dgptm-flag-remove" data-flag-type="${flag.type}">×</button>
                </span>
            `);
            $container.append($flag);
        });
    }

    /**
     * Get Flag Badge Class
     */
    function getFlagBadgeClass(flagType) {
        const classes = {
            'critical': 'dgptm-flag-critical',
            'important': 'dgptm-flag-important',
            'testing': 'dgptm-flag-testing',
            'production': 'dgptm-flag-production',
            'development': 'dgptm-flag-dev',
            'deprecated': 'dgptm-flag-deprecated',
            'custom': 'dgptm-flag-custom'
        };
        return classes[flagType] || 'dgptm-flag-default';
    }

    /**
     * Display Version Status
     */
    function displayVersionStatus(metadata) {
        const $status = $('#dgptm-version-status');
        const $switchUI = $('#dgptm-switch-version-ui');
        const $linkUI = $('#dgptm-link-version-ui');

        // Has Test Version?
        if (metadata.test_module_id) {
            $status.html(`
                <p><strong>Haupt-Version</strong> <span class="dgptm-version-badge active-main">MAIN</span></p>
                <p>Test-Version verknüpft mit: <code>${metadata.test_module_id}</code></p>
            `);
            $switchUI.show();
            $('#dgptm-switch-version-text').text('Zu Test-Version wechseln');
            $linkUI.hide();
        }
        // Is Test Module?
        else if (metadata.main_module_id) {
            $status.html(`
                <p><strong>Test-Version</strong> <span class="dgptm-version-badge active-test">TEST</span></p>
                <p>Haupt-Version: <code>${metadata.main_module_id}</code></p>
            `);
            $switchUI.show();
            $('#dgptm-switch-version-text').text('Zu Haupt-Version wechseln');
            $linkUI.hide();
        }
        // No Version Link
        else {
            $status.html('<p>Keine Test-Version verknüpft</p>');
            $switchUI.hide();
            $linkUI.show();
        }
    }

    /**
     * Populate Test Module Dropdown
     */
    function populateTestModuleDropdown(currentModuleId, metadata) {
        const $select = $('#dgptm-test-module-select');
        $select.empty().append('<option value="">Modul auswählen...</option>');

        allModules.forEach(function(module) {
            if (module.id !== currentModuleId) {
                $select.append(`<option value="${module.id}">${module.name} (${module.id})</option>`);
            }
        });
    }

    /**
     * Add Flag
     */
    function addFlag(moduleId, flagType, label) {
        $.ajax({
            url: dgptmSuite.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_add_flag',
                nonce: dgptmSuite.nonce,
                module_id: moduleId,
                flag_type: flagType,
                label: label
            },
            success: function(response) {
                if (response.success) {
                    displayFlags(response.data.flags);
                    $('#dgptm-flag-type').val('');
                    $('#dgptm-flag-label').val('');
                    showNotice('Flag hinzugefügt');
                } else {
                    alert('Fehler: ' + response.data.message);
                }
            }
        });
    }

    /**
     * Remove Flag
     */
    function removeFlag(moduleId, flagType) {
        $.ajax({
            url: dgptmSuite.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_remove_flag',
                nonce: dgptmSuite.nonce,
                module_id: moduleId,
                flag_type: flagType
            },
            success: function(response) {
                if (response.success) {
                    displayFlags(response.data.flags);
                    showNotice('Flag entfernt');
                }
            }
        });
    }

    /**
     * Save Comment
     */
    function saveComment(moduleId, comment) {
        $.ajax({
            url: dgptmSuite.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_set_comment',
                nonce: dgptmSuite.nonce,
                module_id: moduleId,
                comment: comment
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Kommentar gespeichert');
                }
            }
        });
    }

    /**
     * Switch Version
     */
    function switchVersion(moduleId) {
        if (!confirm('Möchten Sie wirklich die Version wechseln? Die Seite wird neu geladen.')) {
            return;
        }

        $.ajax({
            url: dgptmSuite.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_switch_version',
                nonce: dgptmSuite.nonce,
                module_id: moduleId
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message);
                    location.reload();
                } else {
                    alert('Fehler: ' + response.data.message);
                }
            }
        });
    }

    /**
     * Link Test Version
     */
    function linkTestVersion(mainModuleId, testModuleId) {
        $.ajax({
            url: dgptmSuite.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_link_test_version',
                nonce: dgptmSuite.nonce,
                main_module_id: mainModuleId,
                test_module_id: testModuleId
            },
            success: function(response) {
                if (response.success) {
                    showNotice('Test-Version verknüpft');
                    loadModuleMetadata(mainModuleId); // Reload
                }
            }
        });
    }

    /**
     * Show Notice
     */
    function showNotice(message) {
        // Simple notification (könnte später mit WP-Admin-Notices ersetzt werden)
        const $notice = $('<div class="dgptm-inline-notice">' + message + '</div>');
        $notice.css({
            position: 'fixed',
            top: '32px',
            right: '20px',
            background: '#46b450',
            color: '#fff',
            padding: '12px 20px',
            borderRadius: '4px',
            zIndex: 999999,
            boxShadow: '0 2px 10px rgba(0,0,0,0.2)'
        });
        $('body').append($notice);
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 2000);
    }

})(jQuery);
