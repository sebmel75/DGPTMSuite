/**
 * DGPTM Mitglieder Dashboard v1.1
 * Tab switching, sub-tabs, AJAX lazy loading, CRM refresh, deep linking
 */
(function($) {
    'use strict';

    var Dashboard = {

        activeTab: null,
        loadedTabs: {},
        loading: false,

        init: function() {
            var $d = $('.dgptm-dashboard');
            if (!$d.length) return;

            this.activeTab = $d.data('default-tab');
            this.loadedTabs[this.activeTab] = true;

            this.bindTabClicks($d);
            this.bindMobileSelect($d);
            this.bindSubTabs($d);
            this.bindCRMRefresh($d);
            this.handleDeepLink($d);

            // Auto-load first visible sub-tab
            this.autoLoadSubTabs($d);
        },

        // === Main Tab Navigation ===

        bindTabClicks: function($d) {
            var self = this;
            $d.on('click', '.dgptm-tab-nav__item', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var id = $(this).data('tab-id');
                if (id && id !== self.activeTab) self.switchTab($d, id);
                return false;
            });
        },

        bindMobileSelect: function($d) {
            var self = this;
            $d.on('change', '#dgptm-mobile-tab-select', function() {
                var id = $(this).val();
                if (id && id !== self.activeTab) self.switchTab($d, id);
            });
        },

        switchTab: function($d, tabId) {
            if (this.loading) return;
            this.activeTab = tabId;

            // Update nav
            $d.find('.dgptm-tab-nav__item').removeClass('dgptm-tab-nav__item--active').attr('aria-selected', 'false');
            $d.find('.dgptm-tab-nav__item[data-tab-id="' + tabId + '"]').addClass('dgptm-tab-nav__item--active').attr('aria-selected', 'true');
            $d.find('#dgptm-mobile-tab-select').val(tabId);

            // Switch panels
            $d.find('.dgptm-tab-panel').hide().removeClass('dgptm-tab-panel--active');
            var $panel = $d.find('#dgptm-panel-' + tabId);
            $panel.show().addClass('dgptm-tab-panel--active');

            // Lazy load
            if (!this.loadedTabs[tabId]) {
                this.loadTabContent($d, tabId);
            }

            // URL hash
            if (history.replaceState) {
                history.replaceState(null, null, '#tab-' + tabId);
            }

            // Scroll
            var offset = $d.offset().top - 80;
            if ($(window).scrollTop() > offset) {
                $('html, body').animate({ scrollTop: offset }, 150);
            }
        },

        loadTabContent: function($d, tabId) {
            var self = this;
            var $panel = $d.find('#dgptm-panel-' + tabId);

            this.loading = true;
            $panel.html('<div class="dgptm-tab-loading"><div class="dgptm-spinner"></div><span>' + dgptmDashboard.strings.loading + '</span></div>');

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
                    self.executeScripts($panel);
                    self.autoLoadSubTabs($d);
                    $(document).trigger('dgptm_tab_loaded', [tabId, $panel]);
                } else {
                    $panel.html('<div class="dgptm-tab-error">' + (resp.data.message || dgptmDashboard.strings.error) + '</div>');
                }
            }).fail(function() {
                self.loading = false;
                $panel.html('<div class="dgptm-tab-error">' + dgptmDashboard.strings.error + '</div>');
            });
        },

        // === Sub-Tab Navigation ===

        bindSubTabs: function($d) {
            var self = this;

            $d.on('click', '.dgptm-folder-tab', function(e) {
                e.preventDefault();
                var $tab = $(this);
                var subtabId = $tab.data('subtab');
                var $folder = $tab.closest('.dgptm-folder-tabs');

                // Switch active folder tab
                $folder.find('.dgptm-folder-tab').removeClass('dgptm-folder-tab--active');
                $tab.addClass('dgptm-folder-tab--active');

                // Switch panels
                $folder.find('.dgptm-subtab-panel').removeClass('dgptm-subtab-panel--active').hide();
                var $panel = $folder.find('[data-subtab-panel="' + subtabId + '"]');
                $panel.addClass('dgptm-subtab-panel--active').show();

                // Lazy load sub-tab
                if ($panel.data('subtab-loaded') !== true) {
                    self.loadSubTabContent($panel);
                }

                return false;
            });
        },

        autoLoadSubTabs: function($d) {
            // Load the first active sub-tab in each visible folder
            $d.find('.dgptm-tab-panel--active .dgptm-folder-tabs').each(function() {
                var $activePanel = $(this).find('.dgptm-subtab-panel--active');
                if ($activePanel.length && $activePanel.data('subtab-loaded') !== true) {
                    Dashboard.loadSubTabContent($activePanel);
                }
            });
        },

        loadSubTabContent: function($panel) {
            var action = $panel.data('subtab-action');
            if (!action) return;

            $panel.html('<div class="dgptm-tab-loading"><div class="dgptm-spinner"></div><span>' + dgptmDashboard.strings.loading + '</span></div>');

            $.post(dgptmDashboard.ajaxUrl, {
                action: 'dgptm_dashboard_load_subtab',
                nonce: dgptmDashboard.nonce,
                subtab_id: action
            }, function(resp) {
                if (resp.success) {
                    $panel.html(resp.data.html);
                    $panel.data('subtab-loaded', true);
                    Dashboard.executeScripts($panel);
                } else {
                    $panel.html('<div class="dgptm-tab-error">' + (resp.data.message || dgptmDashboard.strings.error) + '</div>');
                }
            }).fail(function() {
                $panel.html('<div class="dgptm-tab-error">' + dgptmDashboard.strings.error + '</div>');
            });
        },

        // === CRM Refresh ===

        bindCRMRefresh: function($d) {
            var self = this;
            $d.on('click', '#dgptm-crm-refresh', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).find('.dashicons').addClass('dgptm-rotating');

                $.post(dgptmDashboard.ajaxUrl, {
                    action: 'dgptm_dashboard_refresh_crm',
                    nonce: dgptmDashboard.nonce
                }, function(resp) {
                    $btn.prop('disabled', false).find('.dashicons').removeClass('dgptm-rotating');
                    if (resp.success) {
                        self.showToast(dgptmDashboard.strings.refreshed, 'success');
                        // Reload profil tab
                        self.loadedTabs = {};
                        self.loadTabContent($('.dgptm-dashboard'), 'profil');
                    } else {
                        self.showToast(resp.data.message || dgptmDashboard.strings.error, 'error');
                    }
                }).fail(function() {
                    $btn.prop('disabled', false).find('.dashicons').removeClass('dgptm-rotating');
                    self.showToast(dgptmDashboard.strings.error, 'error');
                });
            });
        },

        // === Utilities ===

        executeScripts: function($container) {
            $container.find('script').each(function() {
                var s = document.createElement('script');
                if (this.src) { s.src = this.src; } else { s.textContent = this.textContent; }
                document.body.appendChild(s);
                $(this).remove();
            });
        },

        handleDeepLink: function($d) {
            var hash = window.location.hash;
            if (hash && hash.indexOf('#tab-') === 0) {
                var tabId = hash.replace('#tab-', '');
                if ($d.find('.dgptm-tab-nav__item[data-tab-id="' + tabId + '"]').length) {
                    this.switchTab($d, tabId);
                }
            }
        },

        showToast: function(msg, type) {
            $('.dgptm-toast').remove();
            var $t = $('<div class="dgptm-toast dgptm-toast--' + (type || 'success') + '">').text(msg).appendTo('body');
            setTimeout(function() { $t.fadeOut(300, function() { $(this).remove(); }); }, 3000);
        }
    };

    $(document).ready(function() { Dashboard.init(); });

})(jQuery);
