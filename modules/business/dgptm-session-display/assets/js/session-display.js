/**
 * DGPTM Session Display - Frontend JavaScript
 */

(function($) {
    'use strict';

    class DGPTMSessionDisplay {
        constructor(element) {
            this.$element = $(element);
            this.room = this.$element.data('room');
            this.type = this.$element.data('type') || 'current';
            this.refreshInterval = dgptmSessionDisplay.refreshInterval || 60000;
            this.autoRefresh = dgptmSessionDisplay.autoRefresh;
            this.sponsorInterval = 10000;
            this.sponsorIndex = 0;
            this.refreshTimer = null;
            this.sponsorTimer = null;

            this.init();
        }

        init() {
            // Aktuelle Zeit anzeigen
            this.updateCurrentTime();
            setInterval(() => this.updateCurrentTime(), 1000);

            // Auto-Refresh aktivieren
            if (this.autoRefresh) {
                this.startAutoRefresh();
            }

            // Sponsoren-Rotation starten
            this.startSponsorRotation();

            // Fullscreen-Support
            this.setupFullscreen();
        }

        /**
         * Aktuelle Zeit aktualisieren
         */
        updateCurrentTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('de-DE', {
                hour: '2-digit',
                minute: '2-digit'
            });

            $('#dgptm-current-time, #dgptm-overview-time').text(timeString);
        }

        /**
         * Session-Daten aktualisieren
         */
        refreshSession() {
            const self = this;

            $.ajax({
                url: dgptmSessionDisplay.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_get_current_session',
                    nonce: dgptmSessionDisplay.nonce,
                    room_id: this.room,
                    display_type: this.type
                },
                success: function(response) {
                    if (response.success) {
                        self.updateDisplay(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('DGPTM Session Display: Fehler beim Aktualisieren', error);
                }
            });
        }

        /**
         * Display mit neuen Daten aktualisieren
         */
        updateDisplay(data) {
            // Hier würde die DOM-Manipulation für Live-Updates erfolgen
            // Für einen vollständigen Reload:
            location.reload();

            // Oder für dynamisches Update ohne Reload:
            // this.updateSessionContent(data.current_session);
            // this.updateNextSession(data.next_session);
            // $('#dgptm-last-update').text(data.timestamp);
        }

        /**
         * Auto-Refresh starten
         */
        startAutoRefresh() {
            this.refreshTimer = setInterval(() => {
                this.refreshSession();
            }, this.refreshInterval);
        }

        /**
         * Auto-Refresh stoppen
         */
        stopAutoRefresh() {
            if (this.refreshTimer) {
                clearInterval(this.refreshTimer);
                this.refreshTimer = null;
            }
        }

        /**
         * Sponsoren-Rotation starten
         */
        startSponsorRotation() {
            const $sponsors = $('.sponsor-slide');

            if ($sponsors.length <= 1) {
                return;
            }

            this.sponsorTimer = setInterval(() => {
                $sponsors.hide();
                this.sponsorIndex = (this.sponsorIndex + 1) % $sponsors.length;
                $sponsors.eq(this.sponsorIndex).fadeIn(500);
            }, this.sponsorInterval);
        }

        /**
         * Fullscreen-Setup
         */
        setupFullscreen() {
            const self = this;

            // Fullscreen bei Doppelklick
            this.$element.on('dblclick', function() {
                self.toggleFullscreen();
            });

            // ESC-Taste zum Verlassen
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.$element.hasClass('fullscreen')) {
                    self.exitFullscreen();
                }
            });
        }

        /**
         * Fullscreen umschalten
         */
        toggleFullscreen() {
            if (this.$element.hasClass('fullscreen')) {
                this.exitFullscreen();
            } else {
                this.enterFullscreen();
            }
        }

        /**
         * Fullscreen aktivieren
         */
        enterFullscreen() {
            this.$element.addClass('fullscreen');

            // Browser-Fullscreen API verwenden
            const element = this.$element[0];
            if (element.requestFullscreen) {
                element.requestFullscreen();
            } else if (element.mozRequestFullScreen) {
                element.mozRequestFullScreen();
            } else if (element.webkitRequestFullscreen) {
                element.webkitRequestFullscreen();
            } else if (element.msRequestFullscreen) {
                element.msRequestFullscreen();
            }
        }

        /**
         * Fullscreen verlassen
         */
        exitFullscreen() {
            this.$element.removeClass('fullscreen');

            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.msExitFullscreen) {
                document.msExitFullscreen();
            }
        }

        /**
         * Destroy
         */
        destroy() {
            this.stopAutoRefresh();
            if (this.sponsorTimer) {
                clearInterval(this.sponsorTimer);
            }
        }
    }

    /**
     * Overview-Klasse für Raum-Übersichten
     */
    class DGPTMSessionOverview {
        constructor(element) {
            this.$element = $(element);
            this.refreshInterval = dgptmSessionDisplay.refreshInterval || 60000;
            this.autoRefresh = dgptmSessionDisplay.autoRefresh;
            this.refreshTimer = null;

            this.init();
        }

        init() {
            // Aktuelle Zeit anzeigen
            this.updateCurrentTime();
            setInterval(() => this.updateCurrentTime(), 1000);

            // Auto-Refresh aktivieren
            if (this.autoRefresh) {
                this.startAutoRefresh();
            }
        }

        updateCurrentTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('de-DE', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });

            $('#dgptm-overview-update').text(timeString);
        }

        startAutoRefresh() {
            this.refreshTimer = setInterval(() => {
                location.reload();
            }, this.refreshInterval);
        }

        stopAutoRefresh() {
            if (this.refreshTimer) {
                clearInterval(this.refreshTimer);
                this.refreshTimer = null;
            }
        }

        destroy() {
            this.stopAutoRefresh();
        }
    }

    /**
     * jQuery Plugin
     */
    $.fn.dgptmSessionDisplay = function() {
        return this.each(function() {
            if (!$.data(this, 'dgptmSessionDisplay')) {
                $.data(this, 'dgptmSessionDisplay', new DGPTMSessionDisplay(this));
            }
        });
    };

    $.fn.dgptmSessionOverview = function() {
        return this.each(function() {
            if (!$.data(this, 'dgptmSessionOverview')) {
                $.data(this, 'dgptmSessionOverview', new DGPTMSessionOverview(this));
            }
        });
    };

    /**
     * Auto-Initialisierung
     */
    $(document).ready(function() {
        $('.dgptm-session-display').dgptmSessionDisplay();
        $('.dgptm-session-overview').dgptmSessionOverview();
    });

})(jQuery);
