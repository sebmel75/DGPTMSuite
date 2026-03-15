/**
 * DGPTM Mitglieder Dashboard - Frontend JavaScript
 * Tab switching, AJAX lazy loading, CRM refresh, deep linking
 */
(function($) {
    'use strict';

    var Dashboard = {

        activeTab: null,
        loadedTabs: {},
        loading: false,

        init: function() {
            var $dashboard = $('.dgptm-dashboard');
            if (!$dashboard.length) return;

            this.activeTab = $dashboard.data('default-tab');
            this.loadedTabs[this.activeTab] = true;

            this.bindTabClicks($dashboard);
            this.bindMobileSelect($dashboard);
            this.bindCRMRefresh($dashboard);
            this.handleDeepLink($dashboard);
        },

        bindTabClicks: function($dashboard) {
            var self = this;

            $dashboard.on('click', '.dgptm-tab-nav__item', function(e) {
                e.preventDefault();
                var tabId = $(this).data('tab-id');
                if (tabId && tabId !== self.activeTab) {
                    self.switchTab($dashboard, tabId);
                }
            });
        },

        bindMobileSelect: function($dashboard) {
            var self = this;

            $dashboard.on('change', '#dgptm-mobile-tab-select', function() {
                var tabId = $(this).val();
                if (tabId && tabId !== self.activeTab) {
                    self.switchTab($dashboard, tabId);
                }
            });
        },

        bindCRMRefresh: function($dashboard) {
            var self = this;

            $dashboard.on('click', '#dgptm-crm-refresh', function() {
                var $btn = $(this);
                $btn.prop('disabled', true);
                $btn.find('.dashicons').addClass('dgptm-rotating');

                $.post(dgptmDashboard.ajaxUrl, {
                    action: 'dgptm_dashboard_refresh_crm',
                    nonce: dgptmDashboard.nonce
                }, function(resp) {
                    $btn.prop('disabled', false);
                    $btn.find('.dashicons').removeClass('dgptm-rotating');

                    if (resp.success) {
                        self.showToast(dgptmDashboard.strings.refreshed, 'success');
                        // Reload current tab to reflect new CRM data
                        self.loadedTabs = {};
                        self.loadedTabs[self.activeTab] = false;
                        self.loadTabContent($dashboard, self.activeTab, true);
                    } else {
                        self.showToast(resp.data.message || dgptmDashboard.strings.error, 'error');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false);
                    $btn.find('.dashicons').removeClass('dgptm-rotating');
                    self.showToast(dgptmDashboard.strings.error, 'error');
                });
            });
        },

        switchTab: function($dashboard, tabId) {
            if (this.loading) return;

            this.activeTab = tabId;

            // Update tab nav active state
            $dashboard.find('.dgptm-tab-nav__item').removeClass('dgptm-tab-nav__item--active')
                .attr('aria-selected', 'false');
            $dashboard.find('.dgptm-tab-nav__item[data-tab-id="' + tabId + '"]')
                .addClass('dgptm-tab-nav__item--active')
                .attr('aria-selected', 'true');

            // Update mobile select
            $dashboard.find('#dgptm-mobile-tab-select').val(tabId);

            // Show/hide panels
            $dashboard.find('.dgptm-tab-panel').hide().removeClass('dgptm-tab-panel--active');
            var $panel = $dashboard.find('#dgptm-panel-' + tabId);
            $panel.show().addClass('dgptm-tab-panel--active');

            // Lazy load if not loaded
            if (!this.loadedTabs[tabId]) {
                this.loadTabContent($dashboard, tabId, false);
            }

            // Update URL hash
            if (history.replaceState) {
                history.replaceState(null, null, '#tab-' + tabId);
            }

            // Scroll to top of dashboard
            var offset = $dashboard.offset().top - 80;
            if ($(window).scrollTop() > offset) {
                $('html, body').animate({ scrollTop: offset }, 150);
            }
        },

        loadTabContent: function($dashboard, tabId, forceReload) {
            var self = this;
            var $panel = $dashboard.find('#dgptm-panel-' + tabId);

            if ($panel.data('loaded') === true && !forceReload) {
                return;
            }

            this.loading = true;

            // Show spinner
            $panel.html(
                '<div class="dgptm-tab-loading">' +
                '<div class="dgptm-spinner"></div>' +
                '<span>' + dgptmDashboard.strings.loading + '</span>' +
                '</div>'
            );

            $.post(dgptmDashboard.ajaxUrl, {
                action: 'dgptm_dashboard_load_tab',
                nonce: dgptmDashboard.nonce,
                tab_id: tabId
            }, function(resp) {
                self.loading = false;

                if (resp.success) {
                    $panel.html(resp.data.html);
                    $panel.data('loaded', true);
                    self.loadedTabs[tabId] = true;

                    // Execute any inline scripts in the loaded content
                    self.executeScripts($panel);

                    // Trigger custom event for other modules
                    $(document).trigger('dgptm_tab_loaded', [tabId, $panel]);
                } else {
                    $panel.html(
                        '<div class="dgptm-tab-error">' +
                        '<span class="dashicons dashicons-warning"></span> ' +
                        (resp.data.message || dgptmDashboard.strings.error) +
                        '</div>'
                    );
                }
            }).fail(function() {
                self.loading = false;
                $panel.html(
                    '<div class="dgptm-tab-error">' +
                    '<span class="dashicons dashicons-warning"></span> ' +
                    dgptmDashboard.strings.error +
                    '</div>'
                );
            });
        },

        executeScripts: function($container) {
            $container.find('script').each(function() {
                var script = document.createElement('script');
                if (this.src) {
                    script.src = this.src;
                } else {
                    script.textContent = this.textContent;
                }
                document.body.appendChild(script);
                $(this).remove();
            });
        },

        handleDeepLink: function($dashboard) {
            var hash = window.location.hash;
            if (hash && hash.indexOf('#tab-') === 0) {
                var tabId = hash.replace('#tab-', '');
                var $tab = $dashboard.find('.dgptm-tab-nav__item[data-tab-id="' + tabId + '"]');
                if ($tab.length) {
                    this.switchTab($dashboard, tabId);
                }
            }
        },

        showToast: function(message, type) {
            var $existing = $('.dgptm-toast');
            if ($existing.length) $existing.remove();

            var $toast = $('<div class="dgptm-toast dgptm-toast--' + (type || 'success') + '">')
                .text(message)
                .appendTo('body');

            setTimeout(function() {
                $toast.fadeOut(300, function() { $(this).remove(); });
            }, 3000);
        }
    };

    // Rotating animation for refresh button
    var style = document.createElement('style');
    style.textContent = '.dgptm-rotating { animation: dgptm-spin 0.7s linear infinite; }';
    document.head.appendChild(style);

    $(document).ready(function() {
        Dashboard.init();
    });

})(jQuery);
