/* Vimeo Stream Manager Multi - Frontend JavaScript v3.0.0 */

(function($) {
    'use strict';
    
    const VSMFrontend = {
        
        // Eigenschaften
        currentDay: null,
        streamsData: {},
        settings: {},
        players: {},
        mainStreamId: null,
        activeStreamId: null, // Mobile: Stream mit aktivem Ton
        showButtons: true,
        fixedTag: '',
        gridColumns: 2,
        debug: false, // Debug-Mode
        
        /**
         * Initialisierung
         */
        init: function() {
            // Debug-Mode aus URL-Parameter
            const urlParams = new URLSearchParams(window.location.search);
            this.debug = urlParams.get('vsm_debug') === '1';
            
            if (this.debug) {
                console.log('VSM Debug Mode aktiviert');
            }
            
            if (typeof vsmData === 'undefined') {
                console.error('VSM: Daten nicht geladen');
                return;
            }
            
            this.streamsData = vsmData.streams || {};
            this.settings = vsmData.settings || {};
            
            if (this.debug) {
                console.log('VSM Streams:', this.streamsData);
                console.log('VSM Settings:', this.settings);
                console.log('User Agent:', navigator.userAgent);
                console.log('Is Mobile:', this.isMobile());
            }
            
            const $container = $('.vsm-container');
            this.showButtons = $container.data('show-buttons') !== 'false';
            this.fixedTag = $container.data('fixed-tag') || '';
            this.gridColumns = parseInt($container.data('columns')) || this.settings.grid_columns || 2;
            
            this.applyCustomHeights();
            this.setupGrid();
            this.renderDayButtons();
            
            if (this.fixedTag && this.streamsData[this.fixedTag]) {
                this.selectDay(this.fixedTag);
            } else {
                this.selectFirstDay();
            }
            
            this.bindEvents();
        },
        
        /**
         * Grid-Layout einrichten
         */
        setupGrid: function() {
            $('.vsm-top-streams').attr('data-columns', this.gridColumns);
        },
        
        /**
         * Benutzerdefinierte Höhen anwenden
         */
        applyCustomHeights: function() {
            const topHeight = this.settings.top_stream_height || 250;
            const bottomHeight = this.settings.bottom_stream_height || 500;
            
            document.documentElement.style.setProperty('--vsm-top-height', topHeight + 'px');
            document.documentElement.style.setProperty('--vsm-bottom-height', bottomHeight + 'px');
        },
        
        /**
         * Tag-Buttons rendern
         */
        renderDayButtons: function() {
            if (!this.showButtons) return;
            
            const $selector = $('.vsm-day-selector');
            $selector.empty();
            
            if (Object.keys(this.streamsData).length === 0) {
                $selector.html('<p class="vsm-no-streams">Keine Streams verfügbar</p>');
                return;
            }
            
            // Sortierte Tage
            const sortedDays = Object.keys(this.streamsData).sort();
            
            sortedDays.forEach(day => {
                const $button = $('<button>')
                    .addClass('vsm-day-button')
                    .attr('data-day', day)
                    .text(day);
                
                $selector.append($button);
            });
        },
        
        /**
         * Ersten Tag auswählen
         */
        selectFirstDay: function() {
            const days = Object.keys(this.streamsData).sort();
            
            if (days.length > 0) {
                this.selectDay(days[0]);
            } else {
                this.showNoStreams();
            }
        },
        
        /**
         * Tag auswählen
         */
        selectDay: function(day) {
            this.currentDay = day;
            
            if (this.showButtons) {
                $('.vsm-day-button').removeClass('active');
                $(`.vsm-day-button[data-day="${day}"]`).addClass('active');
            }
            
            this.loadStreams(day);
        },
        
        /**
         * Streams laden und anzeigen
         */
        loadStreams: function(day) {
            const streams = this.streamsData[day];
            
            if (!streams) {
                this.showNoStreams();
                return;
            }
            
            // Alle Players entfernen
            this.destroyAllPlayers();
            
            // Streams sammeln
            const streamList = [];
            for (let i = 1; i <= 5; i++) {
                if (streams['stream_' + i]) {
                    streamList.push({
                        id: streams['stream_' + i],
                        caption: streams['caption_' + i] || ''
                    });
                }
            }
            
            if (streamList.length === 0) {
                this.showNoStreams();
                return;
            }
            
            // Layout aufbauen
            this.buildStreamLayout(streamList);
        },
        
        /**
         * Stream-Layout aufbauen
         */
        buildStreamLayout: function(streams) {
            const maxTopStreams = this.settings.max_top_streams || 4;
            const $topContainer = $('.vsm-top-streams');
            const $mainStream = $('.vsm-main-stream');
            const isMobile = this.isMobile();
            
            // Container leeren
            $topContainer.empty();
            
            if (isMobile) {
                // Mobile: Alle Streams gleichberechtigt
                // Hauptstream verstecken
                $mainStream.hide();
                
                // Alle Streams in den Top-Container
                streams.forEach((stream, index) => {
                    const $streamDiv = this.createSmallStream(stream, index);
                    // Auf Mobile sind alle Streams gleich
                    $streamDiv.addClass('vsm-mobile-stream');
                    $topContainer.append($streamDiv);
                });
                
                // Player für alle Streams initialisieren - ALLE stumm
                streams.forEach((stream, index) => {
                    this.initializePlayer('small-' + index, stream.id, true); // Alle stumm
                });
                
            } else {
                // Desktop: Original-Verhalten
                $mainStream.show();
                
                // Hauptstream bestimmen (letzter Stream)
                const mainStream = streams[streams.length - 1];
                const topStreams = streams.slice(0, Math.min(streams.length - 1, maxTopStreams));
                
                // Obere Streams erstellen
                topStreams.forEach((stream, index) => {
                    const $streamDiv = this.createSmallStream(stream, index);
                    $topContainer.append($streamDiv);
                });
                
                // Hauptstream laden
                this.loadMainStream(mainStream);
                
                // Player für obere Streams initialisieren
                topStreams.forEach((stream, index) => {
                    this.initializePlayer('small-' + index, stream.id, true); // true = muted
                });
            }
        },
        
        /**
         * Kleinen Stream erstellen
         */
        createSmallStream: function(stream, index) {
            const $div = $('<div>')
                .addClass('vsm-stream vsm-small-stream')
                .attr('data-stream-id', stream.id)
                .attr('data-stream-index', index)
                .attr('data-stream-type', 'small');
            
            // Video-Container
            const $videoContainer = $('<div>')
                .addClass('vsm-video-container')
                .attr('id', 'vsm-player-small-' + index);
            
            // Mute-Indikator
            const $muteIndicator = $('<div>')
                .addClass('vsm-mute-indicator')
                .html('🔇');
            
            // Switch-Indikator
            const $switchIndicator = $('<div>')
                .addClass('vsm-switch-indicator')
                .text('→ Zum Hauptstream');
            
            // Beschriftung
            if (stream.caption) {
                const $caption = $('<div>')
                    .addClass('vsm-stream-caption')
                    .text(stream.caption);
                $div.addClass('has-caption');
                $div.append($caption);
            }
            
            $div.append($videoContainer);
            $div.append($muteIndicator);
            $div.append($switchIndicator);

            return $div;
        },
        
        /**
         * Hauptstream laden
         */
        loadMainStream: function(stream) {
            const $mainStream = $('.vsm-main-stream');
            
            // Container leeren
            $mainStream.find('.vsm-video-container, .vsm-sound-indicator').remove();
            $mainStream.find('.vsm-placeholder').hide();
            
            // Video-Container
            const $videoContainer = $('<div>')
                .addClass('vsm-video-container')
                .attr('id', 'vsm-player-main');
            
            // Sound-Indikator
            const $soundIndicator = $('<div>')
                .addClass('vsm-sound-indicator')
                .html('🔊');
            
            // Beschriftung
            const $caption = $mainStream.find('.vsm-stream-caption');
            if (stream.caption) {
                $caption.text(stream.caption);
                $mainStream.addClass('has-caption');
            } else {
                $caption.text('');
                $mainStream.removeClass('has-caption');
            }
            
            $mainStream.attr('data-stream-id', stream.id);
            $mainStream.append($videoContainer);
            $mainStream.append($soundIndicator);

            // Player initialisieren
            this.mainStreamId = stream.id;
            this.initializePlayer('main', stream.id, false); // false = nicht muted
        },
        
        /**
         * Mobile Detection
         */
        isMobile: function() {
            return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
        },
        
        /**
         * Vimeo Player initialisieren (Events/Livestreams und normale Videos)
         */
        initializePlayer: function(playerId, videoId, muted) {
            const elementId = 'vsm-player-' + playerId;
            const $element = $('#' + elementId);
            
            if ($element.length === 0) {
                console.error('VSM: Element nicht gefunden:', elementId);
                return;
            }
            
            // Loading-Status
            $element.parent().addClass('vsm-loading');
            
            // Mobile Detection
            const isMobile = this.isMobile();
            
            // Prüfen ob es ein Event/Livestream ist
            const isEvent = videoId.includes('event/');
            let iframeUrl;
            
            if (isEvent) {
                // Vimeo Event/Livestream - Optimiert für bessere Kompatibilität
                const eventId = videoId.replace('event/', '').trim();
                
                // Basis-URL für Events
                iframeUrl = `https://vimeo.com/event/${eventId}/embed`;
                
                // Parameter-Object für Events
                const params = new URLSearchParams();
                
                if (!muted) {
                    // Hauptstream - nur auf Desktop autoplay mit Ton
                    if (!isMobile) {
                        params.append('autoplay', '1');
                    }
                    params.append('muted', '0');
                } else {
                    // Obere Streams - immer muted
                    params.append('muted', '1');
                    // Auf Mobile kein Autoplay für Events
                    if (!isMobile) {
                        params.append('autoplay', '1');
                    }
                }
                
                // Weitere Parameter für bessere Performance
                params.append('autopause', '0');
                params.append('playsinline', '1');
                
                // Parameter anhängen wenn vorhanden
                const paramString = params.toString();
                if (paramString) {
                    iframeUrl += '?' + paramString;
                }
                
            } else {
                // Normales Vimeo Video
                const params = new URLSearchParams();
                
                if (muted) {
                    // Background-Modus für stumme Videos
                    params.append('background', '1');
                    params.append('autoplay', '1');
                    params.append('loop', '1');
                    params.append('muted', '1');
                    params.append('playsinline', '1');
                } else {
                    // Hauptstream
                    params.append('muted', isMobile ? '1' : '0'); // Mobile immer muted starten
                    params.append('autoplay', '1');
                    params.append('playsinline', '1');
                    params.append('autopause', '0');
                }
                
                iframeUrl = `https://player.vimeo.com/video/${videoId}?${params.toString()}`;
            }
            
            // Eindeutige ID für iframe
            const iframeId = 'vsm-iframe-' + playerId + '-' + Date.now();
            
            // Iframe erstellen mit besseren Attributen
            const iframe = document.createElement('iframe');
            iframe.id = iframeId;
            iframe.src = iframeUrl;
            iframe.frameBorder = '0';
            iframe.allow = 'autoplay; fullscreen; picture-in-picture; encrypted-media; gyroscope; accelerometer';
            iframe.allowFullscreen = true;
            iframe.setAttribute('playsinline', ''); // Wichtig für iOS
            iframe.setAttribute('webkit-playsinline', ''); // Wichtig für ältere iOS
            iframe.style.position = 'absolute';
            iframe.style.top = '0';
            iframe.style.left = '0';
            iframe.style.width = '100%';
            iframe.style.height = '100%';
            
            // Load-Event für besseres Loading-Handling
            let loadTimeout;
            
            iframe.onload = () => {
                clearTimeout(loadTimeout);
                $element.parent().removeClass('vsm-loading');
                console.log('VSM: Player loaded:', playerId, isEvent ? 'Event' : 'Video');
            };
            
            iframe.onerror = () => {
                clearTimeout(loadTimeout);
                $element.parent().removeClass('vsm-loading');
                console.error('VSM: Loading error for player:', playerId);
                // Fallback anzeigen
                $element.html('<div class="vsm-error">Stream konnte nicht geladen werden</div>');
            };
            
            // Timeout-Fallback falls onload nicht feuert
            loadTimeout = setTimeout(() => {
                $element.parent().removeClass('vsm-loading');
                console.log('VSM: Loading timeout reached, removing spinner');
            }, 5000);
            
            // Container leeren und iframe einfügen
            $element.empty();
            $element[0].appendChild(iframe);
            
            // Player-Info speichern
            this.players[playerId] = { 
                type: isEvent ? 'event' : 'video', 
                element: $element[0],
                iframe: iframe,
                videoId: videoId,
                muted: muted,
                isMobile: isMobile
            };
            
            // Mobile-spezifische Anpassungen
            if (isMobile && !muted) {
                // Hinweis für Mobile User beim Hauptstream
                this.showMobileHint($element.parent());
            }
        },
        
        /**
         * Mobile-Hinweis anzeigen
         */
        showMobileHint: function($container) {
            // Entferne alten Hinweis falls vorhanden
            $container.find('.vsm-mobile-hint').remove();
            
            const hint = $('<div class="vsm-mobile-hint">▶ Tippen zum Abspielen</div>');
            $container.append(hint);
            
            // Hinweis nach 5 Sekunden ausblenden
            setTimeout(() => {
                hint.fadeOut(500, function() {
                    $(this).remove();
                });
            }, 5000);
        },
        
        /**
         * Streams tauschen
         */
        switchStreams: function($clickedStream) {
            // Auf Mobile: Direkte Aktion ohne Check
            const isMobile = this.isMobile();
            
            if (!isMobile && !this.settings.auto_switch_sound) return;
            
            const clickedId = $clickedStream.attr('data-stream-id');
            const clickedIndex = $clickedStream.attr('data-stream-index');
            const mainId = this.mainStreamId;
            
            if (!clickedId || !mainId || clickedId === mainId) return;
            
            // Animation
            $clickedStream.addClass('vsm-switching');
            $('.vsm-main-stream').addClass('vsm-switching');
            
            // Captions merken
            const clickedCaption = $clickedStream.find('.vsm-stream-caption').text();
            const mainCaption = $('.vsm-main-stream .vsm-stream-caption').text();
            
            // Players zerstören
            this.destroyPlayer('small-' + clickedIndex);
            this.destroyPlayer('main');
            
            // IDs tauschen
            $clickedStream.attr('data-stream-id', mainId);
            $('.vsm-main-stream').attr('data-stream-id', clickedId);
            this.mainStreamId = clickedId;
            
            // Captions tauschen
            $clickedStream.find('.vsm-stream-caption').text(mainCaption);
            if (mainCaption) {
                $clickedStream.addClass('has-caption');
            } else {
                $clickedStream.removeClass('has-caption');
            }
            
            $('.vsm-main-stream .vsm-stream-caption').text(clickedCaption);
            if (clickedCaption) {
                $('.vsm-main-stream').addClass('has-caption');
            } else {
                $('.vsm-main-stream').removeClass('has-caption');
            }
            
            // Neue Players initialisieren (kürzere Verzögerung auf Mobile)
            const delay = isMobile ? 100 : 300;
            
            setTimeout(() => {
                // Neuer kleiner Stream (vorher Hauptstream)
                this.initializePlayer('small-' + clickedIndex, mainId, true);
                
                // Neuer Hauptstream (vorher kleiner Stream)
                this.initializePlayer('main', clickedId, false);
                
                // Animation entfernen
                $clickedStream.removeClass('vsm-switching');
                $('.vsm-main-stream').removeClass('vsm-switching');
            }, delay);
        },
        
        /**
         * Player zerstören
         */
        destroyPlayer: function(playerId) {
            if (this.players[playerId]) {
                const player = this.players[playerId];
                // Iframe entfernen
                if (player.element) {
                    $(player.element).empty();
                }
                delete this.players[playerId];
            }
        },
        
        /**
         * Alle Players zerstören
         */
        destroyAllPlayers: function() {
            Object.keys(this.players).forEach(playerId => {
                this.destroyPlayer(playerId);
            });
        },
        
        /**
         * Keine Streams anzeigen
         */
        showNoStreams: function() {
            this.destroyAllPlayers();
            $('.vsm-top-streams').html('<div class="vsm-no-streams">Keine Streams verfügbar</div>');
            $('.vsm-main-stream .vsm-placeholder').show();
            $('.vsm-main-stream .vsm-video-container, .vsm-main-stream .vsm-sound-indicator').remove();
        },
        
        /**
         * Events binden
         */
        bindEvents: function() {
            const self = this;
            
            // Tag-Auswahl
            $(document).on('click', '.vsm-day-button', function() {
                const day = $(this).data('day');
                self.selectDay(day);
            });
            
            // Desktop: Stream-Wechsel (Click-to-Switch)
            if (!this.isMobile()) {
                $(document).on('click', '.vsm-small-stream', function() {
                    self.switchStreams($(this));
                });
            }
            
            // Fullscreen Button (nur Mobile)
            if (this.isMobile()) {
                $(document).on('click', '.vsm-fullscreen-button', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    self.toggleFullscreen();
                });

                // Close-Button für manuellen Fullscreen
                $(document).on('click', '.vsm-fullscreen-close', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    self.exitFullscreen();
                });

                // Fullscreen Change Events
                document.addEventListener('fullscreenchange', function() {
                    const isFullscreen = !!document.fullscreenElement;
                    if (!isFullscreen) {
                        $('.vsm-container').removeClass('vsm-fullscreen-active');
                    }
                });

                document.addEventListener('webkitfullscreenchange', function() {
                    const isFullscreen = !!document.webkitFullscreenElement;
                    if (!isFullscreen) {
                        $('.vsm-container').removeClass('vsm-fullscreen-active');
                    }
                });

                document.addEventListener('mozfullscreenchange', function() {
                    const isFullscreen = !!document.mozFullScreenElement;
                    if (!isFullscreen) {
                        $('.vsm-container').removeClass('vsm-fullscreen-active');
                    }
                });
            }

            // Mobile: Click für Sound-Aktivierung
            if (this.isMobile()) {
                $(document).on('click', '.vsm-small-stream, .vsm-mobile-stream', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const $clickedStream = $(this);
                    const streamId = $clickedStream.attr('data-stream-id');
                    const streamIndex = $clickedStream.attr('data-stream-index');
                    const playerId = 'small-' + streamIndex;
                    
                    // Alle anderen Streams stummschalten
                    $('.vsm-stream').not($clickedStream).each(function() {
                        const $otherStream = $(this);
                        const otherIndex = $otherStream.attr('data-stream-index');
                        const otherPlayerId = 'small-' + otherIndex;
                        
                        if (self.players[otherPlayerId] && self.players[otherPlayerId].iframe) {
                            // Stream stummschalten
                            const otherIframe = self.players[otherPlayerId].iframe;
                            otherIframe.contentWindow.postMessage('{"method":"setMuted","value":true}', '*');
                            
                            // Visuelles Feedback
                            $otherStream.removeClass('active-sound');
                            $otherStream.find('.vsm-sound-indicator').remove();
                            if (!$otherStream.find('.vsm-mute-indicator').length) {
                                $otherStream.append('<div class="vsm-mute-indicator">🔇</div>');
                            }
                        }
                    });
                    
                    // Angeklickten Stream aktivieren
                    if (self.players[playerId] && self.players[playerId].iframe) {
                        const iframe = self.players[playerId].iframe;
                        
                        // Sound aktivieren
                        iframe.contentWindow.postMessage('{"method":"setMuted","value":false}', '*');
                        iframe.contentWindow.postMessage('{"method":"play"}', '*');
                        
                        // Visuelles Feedback
                        $clickedStream.addClass('active-sound');
                        $clickedStream.find('.vsm-mute-indicator').remove();
                        if (!$clickedStream.find('.vsm-sound-indicator').length) {
                            $clickedStream.append('<div class="vsm-sound-indicator">🔊</div>');
                        }
                        
                        // Speichere aktiven Stream
                        self.activeStreamId = streamId;
                    }
                });
                
                // Orientation Change für Landscape-Vollbild
                window.addEventListener('orientationchange', function() {
                    setTimeout(() => {
                        self.handleOrientationChange();
                    }, 100);
                });
                
                // Alternative für Geräte ohne orientationchange Event
                let lastOrientation = window.orientation;
                $(window).on('resize', function() {
                    if (window.orientation !== lastOrientation) {
                        lastOrientation = window.orientation;
                        self.handleOrientationChange();
                    }
                });
            }
            
            // Window Resize (nur Desktop)
            if (!this.isMobile()) {
                let resizeTimer;
                $(window).on('resize', function() {
                    clearTimeout(resizeTimer);
                    resizeTimer = setTimeout(() => {
                        self.applyCustomHeights();
                    }, 250);
                });
            }
        },
        
        /**
         * Orientation Change Handler
         */
        handleOrientationChange: function() {
            const isLandscape = window.orientation === 90 || window.orientation === -90;
            const $container = $('.vsm-container');
            const $activeStream = $('.vsm-stream.active-sound');

            if (this.debug) {
                console.log('Orientation:', isLandscape ? 'Landscape' : 'Portrait');
                console.log('Active Stream:', $activeStream.length);
            }

            if (isLandscape && $activeStream.length > 0) {
                // Landscape: Aktiven Stream zum Vollbild
                $container.addClass('vsm-landscape-mode');
                $('body').css('overflow', 'hidden');
            } else {
                // Portrait: Normal
                $container.removeClass('vsm-landscape-mode');
                $('body').css('overflow', '');
            }
        },

        /**
         * Toggle Fullscreen für Hauptstream
         */
        toggleFullscreen: function() {
            const $bottomStream = $('.vsm-bottom-stream')[0];
            const $container = $('.vsm-container');

            if (!$bottomStream) {
                console.error('VSM: Bottom stream nicht gefunden');
                return;
            }

            // Prüfe ob bereits im Fullscreen
            const isFullscreen = document.fullscreenElement ||
                                document.webkitFullscreenElement ||
                                document.mozFullScreenElement ||
                                $bottomStream.classList.contains('vsm-manual-fullscreen');

            if (isFullscreen) {
                // Exit Fullscreen
                if (document.exitFullscreen) {
                    document.exitFullscreen();
                } else if (document.webkitExitFullscreen) {
                    document.webkitExitFullscreen();
                } else if (document.mozCancelFullScreen) {
                    document.mozCancelFullScreen();
                } else {
                    // Manueller Fallback
                    $($bottomStream).removeClass('vsm-manual-fullscreen');
                    $('body').removeClass('vsm-fullscreen-active');
                }

                $container.removeClass('vsm-fullscreen-active');

                if (this.debug) {
                    console.log('Fullscreen beendet');
                }
            } else {
                // Enter Fullscreen
                if ($bottomStream.requestFullscreen) {
                    $bottomStream.requestFullscreen();
                } else if ($bottomStream.webkitRequestFullscreen) {
                    $bottomStream.webkitRequestFullscreen();
                } else if ($bottomStream.mozRequestFullScreen) {
                    $bottomStream.mozRequestFullScreen();
                } else {
                    // Manueller Fallback
                    $($bottomStream).addClass('vsm-manual-fullscreen');
                    $('body').addClass('vsm-fullscreen-active');
                }

                $container.addClass('vsm-fullscreen-active');

                if (this.debug) {
                    console.log('Fullscreen aktiviert');
                }
            }
        },

        /**
         * Exit Fullscreen (für Close-Button)
         */
        exitFullscreen: function() {
            const $bottomStream = $('.vsm-bottom-stream');

            if (document.exitFullscreen) {
                document.exitFullscreen();
            } else if (document.webkitExitFullscreen) {
                document.webkitExitFullscreen();
            } else if (document.mozCancelFullScreen) {
                document.mozCancelFullScreen();
            } else {
                // Manueller Fallback
                $bottomStream.removeClass('vsm-manual-fullscreen');
                $('body').removeClass('vsm-fullscreen-active');
            }

            $('.vsm-container').removeClass('vsm-fullscreen-active');

            if (this.debug) {
                console.log('Fullscreen beendet via Close-Button');
            }
        }
    };
    
    // Initialisierung beim DOM Ready
    $(document).ready(function() {
        VSMFrontend.init();
    });
    
    // Globaler Zugriff für Debugging
    window.VSMFrontend = VSMFrontend;
    
})(jQuery);
