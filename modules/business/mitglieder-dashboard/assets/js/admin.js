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
                    template: 'tabs/tab-' + $item.data('tab-id') + '.php'
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

                $.post(dgptmDashboardAdmin.ajaxUrl, {
                    action: 'dgptm_dashboard_save_config',
                    nonce: dgptmDashboardAdmin.nonce,
                    config: JSON.stringify(config)
                }, function(resp) {
                    $btn.prop('disabled', false).text($btn.attr('id') === 'dgptm-save-tabs' ? 'Alle Tabs speichern' : 'Einstellungen speichern');
                    if (resp.success) {
                        self.notify(resp.data.message, 'success');
                    } else {
                        self.notify(resp.data.message, 'error');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    self.notify('AJAX-Fehler', 'error');
                });
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
