/**
 * DGPTM Vimeo Webinare - Frontend Script
 * Version: 1.2.0 - Time-based tracking (anti-skip)
 */

(function($) {
    'use strict';

    // ============================================================
    // Vimeo Player & Time-Based Progress Tracking
    // ============================================================

    console.log('VW Script loaded!');
    console.log('VW Container found:', $('.vw-player-container').length);

    if ($('.vw-player-container').length > 0) {
        const container = $('.vw-player-container');
        const webinarId = container.data('webinar-id');
        const completionRequired = container.data('completion');
        const isLoggedIn = container.data('user-logged-in') === 'true';
        const playerElement = document.querySelector('.vw-vimeo-player iframe');

        console.log('VW Init:', {
            webinarId: webinarId,
            completionRequired: completionRequired,
            isLoggedIn: isLoggedIn,
            playerElement: playerElement !== null,
            vimeoSDK: typeof Vimeo !== 'undefined',
            vwData: typeof vwData !== 'undefined' ? vwData : 'NOT DEFINED'
        });

        if (!playerElement) {
            console.error('VW: Player element not found!');
            return;
        }

        if (typeof Vimeo === 'undefined') {
            console.error('VW: Vimeo SDK not loaded!');
            console.log('VW: Trying to load Vimeo SDK...');
            // Try to load Vimeo SDK dynamically
            const script = document.createElement('script');
            script.src = 'https://player.vimeo.com/api/player.js';
            script.onload = function() {
                console.log('VW: Vimeo SDK loaded dynamically, reloading page...');
                location.reload();
            };
            document.head.appendChild(script);
            return;
        }

        if (playerElement && typeof Vimeo !== 'undefined') {
            console.log('VW: Creating Vimeo Player...');
            const player = new Vimeo.Player(playerElement);

            let duration = 0;
            let watchedTime = parseFloat(container.data('watched-time')) || 0; // Aus DB/Cookie
            let sessionWatchedTime = 0; // Nur diese Session
            let lastPosition = 0;
            let isPlaying = false;
            let trackingInterval = null;
            let hasCompleted = container.find('.vw-completed-banner').length > 0;

            // Get video duration
            player.getDuration().then(function(d) {
                duration = d;
                console.log('VW: Video duration loaded:', duration);

                // Speichere Dauer beim ersten Mal
                if (isLoggedIn) {
                    saveProgress(sessionWatchedTime, duration);
                }
            }).catch(function(error) {
                console.error('VW: Error getting duration:', error);
            });

            // Play Event
            player.on('play', function() {
                console.log('VW: Play event - starting tracking');
                isPlaying = true;
                startTracking();
            });

            // Pause Event
            player.on('pause', function() {
                isPlaying = false;
                stopTracking();
            });

            // Ended Event
            player.on('ended', function() {
                isPlaying = false;
                stopTracking();
            });

            // Seeked Event (Vorspulen erkannt)
            player.on('seeked', function(data) {
                const newPosition = data.seconds;

                // Wenn vorwärts gespult wurde, zählt das NICHT als angesehene Zeit
                if (newPosition > lastPosition + 1) {
                    console.log('Forward seek detected - no time added');
                }

                lastPosition = newPosition;
            });

            // Time Update (für UI-Updates, nicht für Tracking!)
            player.on('timeupdate', function(data) {
                lastPosition = data.seconds;
            });

            // Tracking starten
            function startTracking() {
                if (trackingInterval) return; // Bereits aktiv

                trackingInterval = setInterval(function() {
                    if (isPlaying && !hasCompleted) {
                        // Jede Sekunde wird als angesehen gezählt
                        sessionWatchedTime += 1;
                        watchedTime += 1;

                        // UI aktualisieren
                        updateUI();

                        // Alle 10 Sekunden speichern
                        if (sessionWatchedTime % 10 === 0) {
                            saveProgress(sessionWatchedTime, duration);
                        }

                        // Prüfe Completion - WICHTIG: Nur wenn eingeloggt und noch nicht completed
                        if (duration > 0 && isLoggedIn && !hasCompleted) {
                            const progress = (watchedTime / duration) * 100;

                            console.log('VW Progress Check:', {
                                watched: watchedTime,
                                duration: duration,
                                progress: progress.toFixed(2),
                                required: completionRequired,
                                willComplete: progress >= completionRequired
                            });

                            if (progress >= completionRequired) {
                                console.log('VW: COMPLETION TRIGGERED!');
                                completeWebinar(webinarId);
                            }
                        }
                    }
                }, 1000); // Jede Sekunde
            }

            // Tracking stoppen
            function stopTracking() {
                if (trackingInterval) {
                    clearInterval(trackingInterval);
                    trackingInterval = null;

                    // Beim Stoppen speichern
                    if (sessionWatchedTime > 0) {
                        saveProgress(sessionWatchedTime, duration);
                    }
                }
            }

            // UI aktualisieren
            function updateUI() {
                if (duration > 0) {
                    const progress = Math.min(100, (watchedTime / duration) * 100);

                    // Fortschrittsbalken
                    container.find('.vw-progress-fill').css('width', progress + '%');
                    container.find('.vw-progress-value').text(progress.toFixed(1));

                    // Angesehene Zeit (MM:SS)
                    const minutes = Math.floor(watchedTime / 60);
                    const seconds = Math.floor(watchedTime % 60);
                    const timeStr = minutes.toString().padStart(2, '0') + ':' + seconds.toString().padStart(2, '0');
                    container.find('.vw-watched-time-display').text(timeStr);
                }
            }

            // Save Progress via AJAX
            function saveProgress(watched, dur) {
                if (!isLoggedIn) {
                    // Nicht eingeloggt - in Cookie speichern
                    saveToCookie(watched, dur);
                    return;
                }

                $.ajax({
                    url: vwData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'vw_track_progress',
                        nonce: vwData.nonce,
                        webinar_id: webinarId,
                        watched_time: watched,
                        duration: dur
                    },
                    success: function(response) {
                        if (response.success) {
                            // Update UI mit Server-Daten
                            if (response.data.progress !== undefined) {
                                const serverProgress = response.data.progress;
                                container.find('.vw-progress-fill').css('width', serverProgress + '%');
                                container.find('.vw-progress-value').text(serverProgress.toFixed(1));
                            }

                            // Zeige Login-Hinweis für nicht eingeloggte
                            if (!response.data.logged_in && response.data.message) {
                                showNotification(response.data.message, 'info');
                            }
                        }
                    }
                });

                // Reset session counter nach Speicherung
                sessionWatchedTime = 0;
            }

            // Cookie speichern (für nicht eingeloggte)
            function saveToCookie(watched, dur) {
                const progress = dur > 0 ? (watched / dur) * 100 : 0;
                const cookieData = {
                    watched_time: watched,
                    progress: progress
                };

                const cookieName = 'vw_webinar_' + webinarId;
                const cookieValue = JSON.stringify(cookieData);
                const expiryDays = 30;
                const d = new Date();
                d.setTime(d.getTime() + (expiryDays * 24 * 60 * 60 * 1000));

                document.cookie = cookieName + '=' + encodeURIComponent(cookieValue) +
                    '; expires=' + d.toUTCString() + '; path=/';
            }

            // Complete Webinar
            function completeWebinar(webinarId) {
                console.log('VW: completeWebinar called for webinar ID:', webinarId);
                hasCompleted = true;
                stopTracking();

                showLoading();

                $.ajax({
                    url: vwData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'vw_complete_webinar',
                        nonce: vwData.nonce,
                        webinar_id: webinarId
                    },
                    success: function(response) {
                        hideLoading();
                        console.log('VW: AJAX response:', response);

                        if (response.success) {
                            showNotification('Glückwunsch! Sie haben das Webinar erfolgreich abgeschlossen! 🎉', 'success');

                            // Reload page to show completion
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        } else {
                            showNotification(response.data.message || 'Fehler beim Abschließen', 'error');
                        }
                    },
                    error: function(xhr, status, error) {
                        hideLoading();
                        console.error('VW: AJAX error:', {xhr: xhr, status: status, error: error});
                        showNotification('Netzwerkfehler: ' + error, 'error');
                    }
                });
            }

            // Make completeWebinar globally accessible for testing
            window.vwCompleteWebinar = completeWebinar;

            // Cleanup beim Verlassen der Seite
            $(window).on('beforeunload', function() {
                if (sessionWatchedTime > 0) {
                    // Synchron speichern vor Page Unload
                    saveProgress(sessionWatchedTime, duration);
                }
            });
        }
    }

    // ============================================================
    // Certificate Generation
    // ============================================================

    $(document).on('click', '.vw-generate-certificate', function(e) {
        e.preventDefault();
        const webinarId = $(this).data('webinar-id');

        showLoading();

        $.ajax({
            url: vwData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'vw_generate_certificate',
                nonce: vwData.nonce,
                webinar_id: webinarId
            },
            success: function(response) {
                hideLoading();

                if (response.success) {
                    // Download PDF
                    window.open(response.data.pdf_url, '_blank');
                    showNotification('Zertifikat wird heruntergeladen...', 'success');
                } else {
                    showNotification(response.data.message || 'Fehler beim Generieren', 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotification('Netzwerkfehler', 'error');
            }
        });
    });

    // ============================================================
    // Webinar Liste - Search & Filter
    // ============================================================

    $('.vw-search-input').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();

        $('.vw-webinar-card').each(function() {
            const title = $(this).data('title');

            if (title.indexOf(searchTerm) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    $('.vw-status-filter').on('change', function() {
        const selectedStatus = $(this).val();

        $('.vw-webinar-card').each(function() {
            const status = $(this).data('status');

            if (selectedStatus === 'all' || status === selectedStatus) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // ============================================================
    // Frontend Manager - CRUD Operations
    // ============================================================

    // Create New Button
    $('#vw-create-new').on('click', function() {
        $('#vw-modal-title').text('Neues Webinar erstellen');
        $('#vw-webinar-form')[0].reset();
        $('#vw-post-id').val('');
        $('#vw-modal').fadeIn();
    });

    // Edit Button
    $(document).on('click', '.vw-edit', function() {
        const row = $(this).closest('tr');
        const postId = row.data('id');

        // Get webinar data from DOM
        const title = row.find('td:eq(0)').text().trim();
        const vimeoId = row.find('td:eq(1)').text().trim();
        const points = row.find('td:eq(2)').text().trim();
        const completion = row.find('td:eq(3)').text().replace('%', '').trim();

        $('#vw-modal-title').text('Webinar bearbeiten');
        $('#vw-post-id').val(postId);
        $('#vw-title').val(title);
        $('#vw-vimeo-id').val(vimeoId);
        $('#vw-points').val(points);
        $('#vw-completion').val(completion);
        $('#vw-modal').fadeIn();
    });

    // Delete Button
    $(document).on('click', '.vw-delete', function() {
        if (!confirm('Möchten Sie dieses Webinar wirklich löschen?')) {
            return;
        }

        const postId = $(this).data('id');
        const row = $(this).closest('tr');

        showLoading();

        $.ajax({
            url: vwData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'vw_manager_delete',
                nonce: vwData.nonce,
                post_id: postId
            },
            success: function(response) {
                hideLoading();

                if (response.success) {
                    row.fadeOut(300, function() {
                        $(this).remove();
                    });
                    showNotification('Webinar gelöscht', 'success');
                } else {
                    showNotification(response.data.message || 'Fehler beim Löschen', 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotification('Netzwerkfehler', 'error');
            }
        });
    });

    // Submit Form (Create/Update)
    $('#vw-webinar-form').on('submit', function(e) {
        e.preventDefault();

        const formData = {
            action: $('#vw-post-id').val() ? 'vw_manager_update' : 'vw_manager_create',
            nonce: vwData.nonce,
            post_id: $('#vw-post-id').val(),
            title: $('#vw-title').val(),
            description: $('#vw-description').val(),
            vimeo_id: $('#vw-vimeo-id').val(),
            completion_percentage: $('#vw-completion').val(),
            points: $('#vw-points').val(),
            vnr: $('#vw-vnr').val()
        };

        showLoading();

        $.ajax({
            url: vwData.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                hideLoading();

                if (response.success) {
                    showNotification(response.data.message, 'success');
                    $('#vw-modal').fadeOut();

                    // Reload page to show changes
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    showNotification(response.data.message || 'Fehler beim Speichern', 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotification('Netzwerkfehler', 'error');
            }
        });
    });

    // View Stats Button
    $(document).on('click', '.vw-view-stats', function() {
        const postId = $(this).data('id');

        showLoading();

        $.ajax({
            url: vwData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'vw_manager_stats',
                nonce: vwData.nonce,
                webinar_id: postId
            },
            success: function(response) {
                hideLoading();

                if (response.success) {
                    const stats = response.data;
                    let html = '<div class="vw-stats-details">';
                    html += '<p><strong>Abgeschlossen:</strong> ' + stats.completed + '</p>';
                    html += '<p><strong>In Bearbeitung:</strong> ' + stats.in_progress + '</p>';
                    html += '<p><strong>Gesamt Ansichten:</strong> ' + stats.total_views + '</p>';
                    html += '</div>';

                    $('#vw-stats-modal-body').html(html);
                    $('#vw-stats-modal').fadeIn();
                }
            },
            error: function() {
                hideLoading();
                showNotification('Netzwerkfehler', 'error');
            }
        });
    });

    // Close Modal
    $('.vw-modal-close').on('click', function() {
        $(this).closest('.vw-modal').fadeOut();
    });

    // Close modal on outside click
    $('.vw-modal').on('click', function(e) {
        if ($(e.target).hasClass('vw-modal')) {
            $(this).fadeOut();
        }
    });

    // Manager Tabs
    $('.vw-tab-btn').on('click', function() {
        const tab = $(this).data('tab');

        $('.vw-tab-btn').removeClass('active');
        $(this).addClass('active');

        $('.vw-tab-content').removeClass('active');
        $('.vw-tab-' + tab).addClass('active');
    });

    // Manager Search
    $('.vw-manager-search-input').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();

        $('#vw-manager-tbody tr').each(function() {
            const title = $(this).data('title');

            if (title.indexOf(searchTerm) > -1) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // ============================================================
    // Helper Functions
    // ============================================================

    function showLoading() {
        $('.vw-loading-overlay').fadeIn();
    }

    function hideLoading() {
        $('.vw-loading-overlay').fadeOut();
    }

    function showNotification(message, type) {
        const notification = $('<div class="vw-notification vw-notification-' + type + '">' + message + '</div>');

        $('body').append(notification);

        notification.fadeIn(300);

        setTimeout(function() {
            notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 4000);
    }

})(jQuery);
