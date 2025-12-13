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

            // NEU v1.1.0: Vollbild und Hintergrundbilder
            this.fullscreenAuto = this.$element.data('fullscreen-auto') === 1;
            this.bgInterval = this.$element.data('bg-interval') || 30000;
            this.bgIndex = 0;
            this.bgTimer = null;

            // NEU v1.1.0: Debug-Zeit und Debug-Datum
            this.debugEnabled = this.$element.data('debug-enabled') === 1;
            this.debugTime = this.$element.data('debug-time') || '09:00';
            this.debugDate = this.$element.data('debug-date') || '';

            // NEU v1.1.0: Session-Rotation
            this.hasMultipleCurrent = this.$element.data('has-multiple-current') === 1;
            this.sessionRotationInterval = this.$element.data('session-rotation-interval') || 15000;
            this.sessionRotationTimer = null;
            this.currentSessionIndex = 0;

            // NEU v1.1.2: Pausen-Rotation
            this.pauseRotationTimer = null;

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

            // NEU v1.1.0: Auto-Vollbild aktivieren
            if (this.fullscreenAuto) {
                this.autoEnterFullscreen();
            }

            // NEU v1.1.0: Hintergrundbilder-Rotation starten
            this.startBackgroundRotation();

            // NEU v1.1.0: F11-Hinweis nach 3 Sekunden ausblenden
            setTimeout(() => {
                $('#dgptm-fullscreen-hint').fadeOut(1000);
            }, 3000);

            // NEU v1.1.0: Session-Rotation starten
            if (this.hasMultipleCurrent) {
                this.startSessionRotation();
            }

            // NEU v1.1.1: Vorträge-Rotation starten
            this.startTalksRotation();

            // NEU v1.1.2: Pausen-Rotation starten (Session-Ankündigung + Sponsoren)
            this.startPauseRotation();
        }

        /**
         * Aktuelle Zeit aktualisieren
         * NEU v1.1.0: Debug-Zeit und Debug-Datum-Override
         */
        updateCurrentTime() {
            let timeString;
            let dateString = '';

            if (this.debugEnabled) {
                // Debug-Modus: Feste Zeit anzeigen
                if (this.debugTime) {
                    timeString = this.debugTime;
                } else {
                    const now = new Date();
                    timeString = now.toLocaleTimeString('de-DE', {
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                }

                // Debug-Datum anzeigen (falls gesetzt)
                if (this.debugDate) {
                    const debugDateObj = new Date(this.debugDate);
                    dateString = ' | ' + debugDateObj.toLocaleDateString('de-DE', {
                        weekday: 'short',
                        day: '2-digit',
                        month: '2-digit'
                    });
                }

                timeString += dateString + ' (DEBUG)';
            } else {
                // Normale Zeit
                const now = new Date();
                timeString = now.toLocaleTimeString('de-DE', {
                    hour: '2-digit',
                    minute: '2-digit'
                });
            }

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
         * NEU v1.1.0: Auto-Vollbild aktivieren
         */
        autoEnterFullscreen() {
            const self = this;

            // Warte kurz, dann aktiviere Vollbild
            setTimeout(() => {
                $('body').addClass('dgptm-fullscreen-active');

                // Optional: Browser-Fullscreen API nutzen (benötigt User-Interaktion)
                // Zeige Hinweis, dass F11 gedrückt werden kann
                $('#dgptm-fullscreen-hint').show();
            }, 500);

            // F11-Tastenkombination erkennen
            $(document).on('keydown.fullscreen', function(e) {
                if (e.key === 'F11') {
                    e.preventDefault();
                    if ($('body').hasClass('dgptm-fullscreen-active')) {
                        $('body').removeClass('dgptm-fullscreen-active');
                    } else {
                        $('body').addClass('dgptm-fullscreen-active');
                    }
                }
            });
        }

        /**
         * NEU v1.1.0: Hintergrundbilder-Rotation
         */
        startBackgroundRotation() {
            const $bgImages = $('#dgptm-bg-gallery .bg-image');

            if ($bgImages.length <= 1) {
                return; // Keine Rotation nötig
            }

            this.bgTimer = setInterval(() => {
                // Aktuelles Bild ausblenden
                $bgImages.eq(this.bgIndex).removeClass('active');

                // Nächsten Index berechnen
                this.bgIndex = (this.bgIndex + 1) % $bgImages.length;

                // Nächstes Bild einblenden
                $bgImages.eq(this.bgIndex).addClass('active');
            }, this.bgInterval);
        }

        /**
         * NEU v1.1.0: Session-Rotation (mehrere parallele Sessions)
         */
        startSessionRotation() {
            const $sessions = $('#dgptm-current-sessions .current-session');

            if ($sessions.length <= 1) {
                return; // Keine Rotation nötig
            }

            console.log('DGPTM Session Display: Starte Rotation von ' + $sessions.length + ' Sessions');

            this.sessionRotationTimer = setInterval(() => {
                // Aktuelle Session ausblenden
                $sessions.eq(this.currentSessionIndex).removeClass('active');

                // Nächsten Index berechnen
                this.currentSessionIndex = (this.currentSessionIndex + 1) % $sessions.length;

                // Nächste Session einblenden
                $sessions.eq(this.currentSessionIndex).addClass('active');

                console.log('DGPTM Session Display: Zeige Session ' + (this.currentSessionIndex + 1) + '/' + $sessions.length);
            }, this.sessionRotationInterval);
        }

        /**
         * NEU v1.1.1: Vorträge-Rotation starten
         * Rotiert durch die Vorträge innerhalb jeder laufenden Session
         */
        startTalksRotation() {
            const self = this;

            // Für jede aktive Session mit Vorträgen
            $('.current-session[data-has-talks="1"]').each(function() {
                const $session = $(this);
                const $talksContainer = $session.find('.talks-rotation-container');
                const $talks = $talksContainer.find('.talk-slide');
                const talksCount = parseInt($session.data('talks-count')) || 0;

                if (talksCount <= 1) {
                    return; // Nur rotieren wenn mehrere Vorträge
                }

                console.log('DGPTM Session Display: Starte Vorträge-Rotation (' + talksCount + ' Vorträge)');

                let currentTalkIndex = 0;

                // Rotation starten
                const rotationTimer = setInterval(() => {
                    // Aktuellen Vortrag ausblenden
                    $talks.eq(currentTalkIndex).removeClass('active');

                    // Nächsten Index berechnen
                    currentTalkIndex = (currentTalkIndex + 1) % talksCount;

                    // Nächsten Vortrag einblenden
                    $talks.eq(currentTalkIndex).addClass('active');

                    console.log('DGPTM Session Display: Zeige Vortrag ' + (currentTalkIndex + 1) + '/' + talksCount);
                }, self.sessionRotationInterval);

                // Timer speichern für späteres Cleanup
                $session.data('talks-rotation-timer', rotationTimer);
            });
        }

        /**
         * NEU v1.1.2: Pausen-Rotation starten (Session-Ankündigung + Sponsoren)
         */
        startPauseRotation() {
            const self = this;
            const $pauseMode = $('.pause-mode');

            if ($pauseMode.length === 0) {
                return; // Keine Pause aktiv
            }

            const hasSponsors = $pauseMode.data('has-sponsors') === 1;
            const sponsorsCount = parseInt($pauseMode.data('sponsors-count')) || 0;

            if (!hasSponsors || sponsorsCount === 0) {
                console.log('DGPTM Session Display: Pause-Modus aktiv, aber keine Sponsoren - keine Rotation');
                return; // Keine Sponsoren, keine Rotation nötig
            }

            const $pauseSlides = $pauseMode.find('.pause-slide');
            const totalSlides = $pauseSlides.length;

            if (totalSlides <= 1) {
                console.log('DGPTM Session Display: Nur ein Slide - keine Rotation nötig');
                return;
            }

            console.log('DGPTM Session Display: Starte Pausen-Rotation (' + totalSlides + ' Slides: 1 Session-Ankündigung + ' + sponsorsCount + ' Sponsoren)');

            let currentSlideIndex = 0;

            // Rotation durch alle Slides (Session-Ankündigung + je ein Sponsor pro Slide)
            const pauseRotationInterval = 12000; // 12 Sekunden pro Slide
            this.pauseRotationTimer = setInterval(() => {
                // Aktuellen Slide ausblenden
                $pauseSlides.eq(currentSlideIndex).removeClass('active');

                // Nächsten Index berechnen
                currentSlideIndex = (currentSlideIndex + 1) % totalSlides;

                // Nächsten Slide einblenden
                $pauseSlides.eq(currentSlideIndex).addClass('active');

                const slideType = currentSlideIndex === 0 ? 'Session-Ankündigung' : 'Sponsor ' + currentSlideIndex;
                console.log('DGPTM Session Display: Zeige ' + slideType + ' (Slide ' + (currentSlideIndex + 1) + '/' + totalSlides + ')');
            }, pauseRotationInterval);
        }

        /**
         * Destroy
         */
        destroy() {
            this.stopAutoRefresh();
            if (this.sponsorTimer) {
                clearInterval(this.sponsorTimer);
            }
            if (this.bgTimer) {
                clearInterval(this.bgTimer);
            }
            if (this.sessionRotationTimer) {
                clearInterval(this.sessionRotationTimer);
            }

            // NEU v1.1.1: Vorträge-Rotation Timer clearen
            $('.current-session[data-has-talks="1"]').each(function() {
                const timer = $(this).data('talks-rotation-timer');
                if (timer) {
                    clearInterval(timer);
                }
            });

            // NEU v1.1.2: Pausen-Rotation Timer clearen
            if (this.pauseRotationTimer) {
                clearInterval(this.pauseRotationTimer);
            }

            $(document).off('keydown.fullscreen');
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
