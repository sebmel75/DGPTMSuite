/**
 * Dashboard Admin - Tab config, drag-and-drop, settings save
 */
(function($) {
    'use strict';

    var DashboardAdmin = {

        init: function() {
            this.bindAdminTabs();
            this.bindTabExpand();
            this.bindDragDrop();
            this.bindAddTab();
            this.bindSave();
            this.bindReset();
        },

        bindAdminTabs: function() {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                var tab = $(this).data('admin-tab');
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                $('.dgptm-admin-section').hide();
                $('[data-admin-panel="' + tab + '"]').show();
            });
        },

        bindTabExpand: function() {
            $(document).on('click', '.dgptm-tab-expand', function() {
                var $details = $(this).closest('.dgptm-tab-config-item').find('.dgptm-tab-config-details');
                $details.slideToggle(200);
                $(this).text($details.is(':visible') ? 'Zuklappen' : 'Details');
            });
        },

        bindDragDrop: function() {
            var $list = $('#dgptm-tab-list');
            var dragItem = null;

            $list.on('dragstart', '.dgptm-drag-handle', function(e) {
                dragItem = $(this).closest('.dgptm-tab-config-item')[0];
                dragItem.classList.add('dragging');
                e.originalEvent.dataTransfer.effectAllowed = 'move';
            });

            // Make the handle draggable
            $list.find('.dgptm-drag-handle').attr('draggable', 'true');

            $list.on('dragover', '.dgptm-tab-config-item', function(e) {
                e.preventDefault();
                e.originalEvent.dataTransfer.dropEffect = 'move';
                $(this).addClass('drag-over');
            });

            $list.on('dragleave', '.dgptm-tab-config-item', function() {
                $(this).removeClass('drag-over');
            });

            $list.on('drop', '.dgptm-tab-config-item', function(e) {
                e.preventDefault();
                $(this).removeClass('drag-over');
                if (dragItem && dragItem !== this) {
                    var $target = $(this);
                    var $drag = $(dragItem);
                    if ($drag.index() < $target.index()) {
                        $target.after($drag);
                    } else {
                        $target.before($drag);
                    }
                }
            });

            $list.on('dragend', '.dgptm-tab-config-item', function() {
                if (dragItem) {
                    dragItem.classList.remove('dragging');
                    dragItem = null;
                }
                $list.find('.drag-over').removeClass('drag-over');
            });
        },

        getEditorContent: function(tabId) {
            // Read directly from textarea (reliable, works in hidden panels)
            var $item = $('#dgptm-tab-list .dgptm-tab-config-item[data-tab-id="' + tabId + '"]');
            var $textarea = $item.find('.dgptm-tab-content-html');
            return $textarea.length ? $textarea.val() : null;
        },

        collectConfig: function() {
            var tabs = [];

            $('#dgptm-tab-list .dgptm-tab-config-item').each(function(i) {
                var $item = $(this);
                var roles = $.trim($item.find('.dgptm-tab-roles').val() || '');

                tabs.push({
                    id: $item.data('tab-id'),
                    label: $item.find('.dgptm-tab-label').val(),
                    icon: $item.find('.dgptm-tab-icon').val(),
                    parent_tab: $item.find('.dgptm-tab-parent').val() || '',
                    permission_type: $item.find('.dgptm-tab-permission-type').val(),
                    permission_field: $item.find('.dgptm-tab-permission-field').val(),
                    permission_roles: roles ? roles.split(',').map(function(r) { return $.trim(r); }) : [],
                    datetime_start: $item.find('.dgptm-tab-datetime-start').val(),
                    datetime_end: $item.find('.dgptm-tab-datetime-end').val(),
                    active: $item.find('.dgptm-tab-active').is(':checked'),
                    order: (i + 1) * 10,
                    content_html: self.getEditorContent($item.data('tab-id'))
                });
            });

            var settings = {
                cache_ttl: parseInt($('#dgptm-cache-ttl').val(), 10) || 900,
                primary_color: $('#dgptm-primary-color').val(),
                accent_color: $('#dgptm-accent-color').val(),
                default_tab: $('#dgptm-default-tab').val(),
                mobile_dropdown: $('#dgptm-mobile-dropdown').is(':checked')
            };

            return { tabs: tabs, settings: settings };
        },

        bindSave: function() {
            var self = this;

            $('#dgptm-save-tabs, #dgptm-save-settings').on('click', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Speichern...');

                var config = self.collectConfig();

                var originalText = $btn.text();

                $.ajax({
                    url: dgptmDashboardAdmin.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'dgptm_dashboard_save_config',
                        nonce: dgptmDashboardAdmin.nonce,
                        config: JSON.stringify(config)
                    },
                    timeout: 30000
                }).done(function(resp) {
                    if (resp.success) {
                        self.notify(resp.data.message, 'success');
                    } else {
                        self.notify(resp.data ? resp.data.message : 'Fehler beim Speichern', 'error');
                    }
                }).fail(function(xhr, status, error) {
                    self.notify('Speichern fehlgeschlagen: ' + (error || status), 'error');
                }).always(function() {
                    $btn.prop('disabled', false).text(originalText);
                });
            });
        },

        bindAddTab: function() {
            var self = this;

            $('#dgptm-add-tab').on('click', function() {
                var id = $.trim($('#dgptm-new-tab-id').val()).toLowerCase().replace(/[^a-z0-9\-]/g, '');
                var label = $.trim($('#dgptm-new-tab-label').val());
                var parent = $('#dgptm-new-tab-parent').val() || '';

                if (!id || !label) {
                    alert('Bitte ID und Label eingeben.');
                    return;
                }

                // Check for duplicate ID
                if ($('#dgptm-tab-list .dgptm-tab-config-item[data-tab-id="' + id + '"]').length) {
                    alert('Ein Tab mit dieser ID existiert bereits.');
                    return;
                }

                // Build new tab HTML (same structure as existing items)
                var html = '<div class="dgptm-tab-config-item" data-tab-id="' + id + '">' +
                    '<div class="dgptm-tab-config-header">' +
                    '<span class="dgptm-drag-handle dashicons dashicons-move" draggable="true"></span>' +
                    '<span class="dashicons dashicons-admin-page"></span>' +
                    '<strong class="dgptm-tab-config-label">' + $('<span>').text(label).html() + '</strong>' +
                    '<code class="dgptm-tab-config-id">' + id + '</code>' +
                    '<label class="dgptm-tab-config-toggle"><input type="checkbox" class="dgptm-tab-active" checked> Aktiv</label>' +
                    '<button type="button" class="button button-small dgptm-tab-expand">Details</button>' +
                    '<button type="button" class="button button-small dgptm-tab-delete" style="color:#dc2626;margin-left:4px;">Loeschen</button>' +
                    '</div>' +
                    '<div class="dgptm-tab-config-details" style="display:none;">' +
                    '<table class="form-table">' +
                    '<tr><th>Label</th><td><input type="text" class="dgptm-tab-label regular-text" value="' + $('<span>').text(label).html() + '"></td></tr>' +
                    '<tr><th>Icon (Dashicons)</th><td><input type="text" class="dgptm-tab-icon regular-text" value="dashicons-admin-page" placeholder="dashicons-admin-page"></td></tr>' +
                    '<tr><th>Uebergeordneter Tab</th><td><select class="dgptm-tab-parent"><option value="">-- Kein (Top-Level) --</option></select><p class="description">Wird als Unter-Tab angezeigt. Nach Speichern neu laden fuer aktualisierte Liste.</p></td></tr>' +
                    '<tr><th>Berechtigungstyp</th><td><select class="dgptm-tab-permission-type"><option value="always">Immer sichtbar</option><option value="acf_field">ACF-Feld</option><option value="role">WordPress-Rolle</option><option value="admin">Nur Admins</option></select></td></tr>' +
                    '<tr class="dgptm-field-row-acf"><th>ACF-Feld</th><td><select class="dgptm-tab-permission-field"><option value="">-- Keine --</option></select></td></tr>' +
                    '<tr class="dgptm-field-row-role"><th>Rollen</th><td><input type="text" class="dgptm-tab-roles regular-text" value="" placeholder="administrator,editor"></td></tr>' +
                    '<tr><th>Zeitfenster Start</th><td><input type="datetime-local" class="dgptm-tab-datetime-start" value=""></td></tr>' +
                    '<tr><th>Zeitfenster Ende</th><td><input type="datetime-local" class="dgptm-tab-datetime-end" value=""></td></tr>' +
                    '</table></div></div>';

                var $newItem = $(html);
                // Set parent value
                $newItem.find('.dgptm-tab-parent').val(parent);
                $('#dgptm-tab-list').append($newItem);

                // Clear form
                $('#dgptm-new-tab-id').val('');
                $('#dgptm-new-tab-label').val('');
                $('#dgptm-new-tab-parent').val('');

                self.notify('Tab "' + label + '" erstellt. Bitte speichern und Template-Datei anlegen.', 'success');
            });

            // Delete tab
            $(document).on('click', '.dgptm-tab-delete', function() {
                var $item = $(this).closest('.dgptm-tab-config-item');
                var id = $item.data('tab-id');
                if (!confirm('Tab "' + id + '" wirklich loeschen?')) return;
                $item.slideUp(200, function() { $(this).remove(); });
            });
        },

        bindReset: function() {
            var self = this;

            $('#dgptm-reset-tabs').on('click', function() {
                if (!confirm('Alle Tab-Einstellungen auf Standard zuruecksetzen?')) return;
                // Save default config by posting empty config triggers server-side reset
                $.post(dgptmDashboardAdmin.ajaxUrl, {
                    action: 'dgptm_dashboard_save_config',
                    nonce: dgptmDashboardAdmin.nonce,
                    config: JSON.stringify({ _reset: true })
                }, function() {
                    location.reload();
                });
            });
        },

        notify: function(message, type) {
            var $notice = $('<div class="notice notice-' + (type === 'error' ? 'error' : 'success') + ' is-dismissible"><p>' + message + '</p></div>');
            $('.dgptm-dashboard-admin h1').after($notice);
            setTimeout(function() { $notice.fadeOut(300, function() { $(this).remove(); }); }, 3000);
        }
    };

    $(document).ready(function() {
        DashboardAdmin.init();
    });

})(jQuery);
