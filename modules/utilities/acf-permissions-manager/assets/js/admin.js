/**
 * ACF Permissions Manager Admin JavaScript
 */

(function($) {
    'use strict';

    const APM = {
        init: function() {
            this.bindEvents();
            this.updateTotalGranted();
        },

        bindEvents: function() {
            // Tab switching
            $('.nav-tab').on('click', this.switchTab.bind(this));

            // Permission toggle
            $('.permission-toggle').on('change', this.togglePermission.bind(this));

            // View users button
            $('.view-users').on('click', this.viewUsers.bind(this));

            // Modal close
            $('.apm-modal-close, .apm-modal-overlay').on('click', this.closeModal.bind(this));

            // Permission select
            $('#permission-select').on('change', function() {
                $('#load-permission-users').prop('disabled', !$(this).val());
            });

            $('#load-permission-users').on('click', this.loadPermissionUsers.bind(this));

            // Batch operations
            $('#batch-permission').on('change', this.updateBatchButtons.bind(this));
            $('.batch-user-select').on('change', this.updateBatchButtons.bind(this));
            $('#select-all-users').on('click', this.selectAllUsers.bind(this));
            $('#deselect-all-users').on('click', this.deselectAllUsers.bind(this));
            $('#batch-assign-btn').on('click', this.batchAssign.bind(this));
            $('#batch-revoke-all-btn').on('click', this.batchRevokeAll.bind(this));
        },

        switchTab: function(e) {
            e.preventDefault();
            const target = $(e.currentTarget).attr('href');

            $('.nav-tab').removeClass('nav-tab-active');
            $(e.currentTarget).addClass('nav-tab-active');

            $('.apm-tab-content').removeClass('active');
            $(target).addClass('active');
        },

        togglePermission: function(e) {
            const $checkbox = $(e.currentTarget);
            const userId = $checkbox.data('user-id');
            const permission = $checkbox.data('permission');
            const value = $checkbox.is(':checked');

            $checkbox.prop('disabled', true);

            $.ajax({
                url: apmData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'apm_toggle_permission',
                    nonce: apmData.nonce,
                    user_id: userId,
                    permission: permission,
                    value: value
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message, 'success');
                        this.updateUsersCounts();
                        this.updateTotalGranted();
                    } else {
                        this.showNotice(response.data.message, 'error');
                        $checkbox.prop('checked', !value);
                    }
                },
                error: () => {
                    this.showNotice('Verbindungsfehler', 'error');
                    $checkbox.prop('checked', !value);
                },
                complete: () => {
                    $checkbox.prop('disabled', false);
                }
            });
        },

        viewUsers: function(e) {
            const $button = $(e.currentTarget);
            const permission = $button.data('permission');
            const label = $button.data('label');

            this.loadUsersModal(permission, label);
        },

        loadUsersModal: function(permission, label) {
            $('#modal-title').text('Benutzer mit: ' + label);
            $('#modal-users-list').html('<p>Lade...</p>');
            $('#user-list-modal').fadeIn(200);

            $.ajax({
                url: apmData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'apm_get_permission_users',
                    nonce: apmData.nonce,
                    permission: permission
                },
                success: (response) => {
                    if (response.success) {
                        this.displayUsersInModal(response.data.users, permission);
                    } else {
                        $('#modal-users-list').html('<p class="error">' + response.data.message + '</p>');
                    }
                },
                error: () => {
                    $('#modal-users-list').html('<p class="error">Fehler beim Laden</p>');
                }
            });
        },

        displayUsersInModal: function(users, permission) {
            let html = '';

            if (users.length === 0) {
                html = '<p>Keine Benutzer mit dieser Berechtigung.</p>';
            } else {
                html = '<table class="wp-list-table widefat">';
                html += '<thead><tr><th>Benutzer</th><th>E-Mail</th><th>Aktion</th></tr></thead>';
                html += '<tbody>';

                users.forEach(user => {
                    html += '<tr>';
                    html += '<td><strong>' + this.escapeHtml(user.display_name) + '</strong></td>';
                    html += '<td>' + this.escapeHtml(user.user_email) + '</td>';
                    html += '<td>';
                    html += '<button class="button button-small revoke-permission" ';
                    html += 'data-user-id="' + user.ID + '" ';
                    html += 'data-permission="' + permission + '">';
                    html += 'Entziehen</button>';
                    html += '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';

                // Bind revoke buttons
                setTimeout(() => {
                    $('.revoke-permission').on('click', this.revokePermission.bind(this));
                }, 100);
            }

            $('#modal-users-list').html(html);
        },

        revokePermission: function(e) {
            const $button = $(e.currentTarget);
            const userId = $button.data('user-id');
            const permission = $button.data('permission');

            if (!confirm(apmData.strings.confirmRevoke)) {
                return;
            }

            $button.prop('disabled', true).text('...');

            $.ajax({
                url: apmData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'apm_toggle_permission',
                    nonce: apmData.nonce,
                    user_id: userId,
                    permission: permission,
                    value: false
                },
                success: (response) => {
                    if (response.success) {
                        $button.closest('tr').fadeOut(300, function() {
                            $(this).remove();

                            // Check if table is empty
                            if ($('#modal-users-list tbody tr').length === 0) {
                                $('#modal-users-list').html('<p>Keine Benutzer mehr mit dieser Berechtigung.</p>');
                            }
                        });

                        this.showNotice(response.data.message, 'success');
                        this.updateUsersCounts();
                        this.updateTotalGranted();

                        // Update checkbox in users tab
                        $(`.permission-toggle[data-user-id="${userId}"][data-permission="${permission}"]`)
                            .prop('checked', false);
                    } else {
                        this.showNotice(response.data.message, 'error');
                        $button.prop('disabled', false).text('Entziehen');
                    }
                },
                error: () => {
                    this.showNotice('Verbindungsfehler', 'error');
                    $button.prop('disabled', false).text('Entziehen');
                }
            });
        },

        closeModal: function() {
            $('#user-list-modal').fadeOut(200);
        },

        loadPermissionUsers: function() {
            const permission = $('#permission-select').val();

            if (!permission) {
                return;
            }

            const permissionLabel = $('#permission-select option:selected').text();
            const $list = $('#permission-users-list');

            $list.html('<p>Lade...</p>').show();

            $.ajax({
                url: apmData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'apm_get_permission_users',
                    nonce: apmData.nonce,
                    permission: permission
                },
                success: (response) => {
                    if (response.success) {
                        this.displayPermissionUsers(response.data.users, permission, permissionLabel);
                    } else {
                        $list.html('<p class="error">' + response.data.message + '</p>');
                    }
                },
                error: () => {
                    $list.html('<p class="error">Fehler beim Laden</p>');
                }
            });
        },

        displayPermissionUsers: function(users, permission, label) {
            const $list = $('#permission-users-list');
            let html = '<h3>' + label + ' (' + users.length + ' Benutzer)</h3>';

            if (users.length === 0) {
                html += '<p>Keine Benutzer mit dieser Berechtigung.</p>';
            } else {
                html += '<table class="wp-list-table widefat striped">';
                html += '<thead><tr><th>Benutzer</th><th>E-Mail</th><th>Aktion</th></tr></thead>';
                html += '<tbody>';

                users.forEach(user => {
                    html += '<tr>';
                    html += '<td><strong>' + this.escapeHtml(user.display_name) + '</strong></td>';
                    html += '<td>' + this.escapeHtml(user.user_email) + '</td>';
                    html += '<td>';
                    html += '<button class="button button-small revoke-permission-list" ';
                    html += 'data-user-id="' + user.ID + '" ';
                    html += 'data-permission="' + permission + '">';
                    html += 'Entziehen</button>';
                    html += '</td>';
                    html += '</tr>';
                });

                html += '</tbody></table>';
            }

            $list.html(html);

            // Bind revoke buttons
            $('.revoke-permission-list').on('click', this.revokePermissionFromList.bind(this));
        },

        revokePermissionFromList: function(e) {
            const $button = $(e.currentTarget);
            const userId = $button.data('user-id');
            const permission = $button.data('permission');

            if (!confirm(apmData.strings.confirmRevoke)) {
                return;
            }

            $button.prop('disabled', true).text('...');

            $.ajax({
                url: apmData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'apm_toggle_permission',
                    nonce: apmData.nonce,
                    user_id: userId,
                    permission: permission,
                    value: false
                },
                success: (response) => {
                    if (response.success) {
                        $button.closest('tr').fadeOut(300, function() {
                            $(this).remove();
                        });

                        this.showNotice(response.data.message, 'success');
                        this.updateUsersCounts();
                        this.updateTotalGranted();

                        // Update checkbox in users tab
                        $(`.permission-toggle[data-user-id="${userId}"][data-permission="${permission}"]`)
                            .prop('checked', false);
                    } else {
                        this.showNotice(response.data.message, 'error');
                        $button.prop('disabled', false).text('Entziehen');
                    }
                },
                error: () => {
                    this.showNotice('Verbindungsfehler', 'error');
                    $button.prop('disabled', false).text('Entziehen');
                }
            });
        },

        updateUsersCounts: function() {
            $('.users-count').each(function() {
                const $count = $(this);
                const permission = $count.data('permission');

                // Count checkboxes for this permission
                const count = $(`.permission-toggle[data-permission="${permission}"]:checked`).length;
                $count.text(count);
            });
        },

        updateTotalGranted: function() {
            const total = $('.permission-toggle:checked').length;
            $('#total-granted').text(total);
        },

        selectAllUsers: function() {
            $('.batch-user-select').prop('checked', true);
            this.updateBatchButtons();
        },

        deselectAllUsers: function() {
            $('.batch-user-select').prop('checked', false);
            this.updateBatchButtons();
        },

        updateBatchButtons: function() {
            const selectedCount = $('.batch-user-select:checked').length;
            const hasPermission = $('#batch-permission').val() !== '';

            $('.selected-count strong').text(selectedCount);
            $('#batch-assign-btn').prop('disabled', selectedCount === 0 || !hasPermission);
            $('#batch-revoke-all-btn').prop('disabled', !hasPermission);
        },

        batchAssign: function() {
            const userIds = $('.batch-user-select:checked').map(function() {
                return $(this).val();
            }).get();

            const permission = $('#batch-permission').val();
            const permissionLabel = $('#batch-permission option:selected').text();

            if (!confirm(apmData.strings.confirmBatchAssign + '\n\nBerechtigung: ' + permissionLabel + '\nBenutzer: ' + userIds.length)) {
                return;
            }

            const $button = $('#batch-assign-btn');
            $button.prop('disabled', true).html('<span class="spinner is-active" style="float:none;"></span> Wird ausgeführt...');

            $.ajax({
                url: apmData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'apm_batch_assign',
                    nonce: apmData.nonce,
                    user_ids: userIds,
                    permission: permission
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message, 'success');

                        // Update checkboxes in users tab
                        userIds.forEach(userId => {
                            $(`.permission-toggle[data-user-id="${userId}"][data-permission="${permission}"]`)
                                .prop('checked', true);
                        });

                        this.updateUsersCounts();
                        this.updateTotalGranted();

                        // Reset selections
                        $('.batch-user-select').prop('checked', false);
                        this.updateBatchButtons();
                    } else {
                        this.showNotice(response.data.message, 'error');
                    }
                },
                error: () => {
                    this.showNotice('Verbindungsfehler', 'error');
                },
                complete: () => {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Berechtigung erteilen');
                }
            });
        },

        batchRevokeAll: function() {
            const permission = $('#batch-permission').val();
            const permissionLabel = $('#batch-permission option:selected').text();

            if (!confirm(apmData.strings.confirmBatchRevoke + '\n\nBerechtigung: ' + permissionLabel)) {
                return;
            }

            const $button = $('#batch-revoke-all-btn');
            $button.prop('disabled', true).html('<span class="spinner is-active" style="float:none;"></span> Wird ausgeführt...');

            $.ajax({
                url: apmData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'apm_batch_revoke',
                    nonce: apmData.nonce,
                    permission: permission
                },
                success: (response) => {
                    if (response.success) {
                        this.showNotice(response.data.message, 'success');

                        // Update all checkboxes for this permission
                        $(`.permission-toggle[data-permission="${permission}"]`).prop('checked', false);

                        this.updateUsersCounts();
                        this.updateTotalGranted();
                    } else {
                        this.showNotice(response.data.message, 'error');
                    }
                },
                error: () => {
                    this.showNotice('Verbindungsfehler', 'error');
                },
                complete: () => {
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> Allen Benutzern entziehen');
                    this.updateBatchButtons();
                }
            });
        },

        showNotice: function(message, type) {
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.apm-wrap h1').after($notice);

            setTimeout(() => {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
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
        APM.init();
    });

})(jQuery);
