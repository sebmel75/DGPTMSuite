/**
 * DGPTM Vimeo Webinare - Frontend Script
 * Version: 1.3.1 - Robustes Time-based tracking
 */

(function($) {
    'use strict';

    console.log('VW Script: Loaded');

    // ============================================================
    // Cookie Helper Functions
    // ============================================================

    function setCookie(name, value, days) {
        var d = new Date();
        d.setTime(d.getTime() + (days * 24 * 60 * 60 * 1000));
        document.cookie = name + '=' + encodeURIComponent(value) + '; expires=' + d.toUTCString() + '; path=/; SameSite=Lax';
    }

    function getCookie(name) {
        var nameEQ = name + '=';
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i].trim();
            if (c.indexOf(nameEQ) === 0) {
                return decodeURIComponent(c.substring(nameEQ.length));
            }
        }
        return null;
    }

    function deleteCookie(name) {
        document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    }

    function getAllWebinarCookies() {
        var cookies = {};
        var ca = document.cookie.split(';');
        for (var i = 0; i < ca.length; i++) {
            var c = ca[i].trim();
            if (c.indexOf('vw_webinar_') === 0) {
                var parts = c.split('=');
                var name = parts[0];
                var webinarId = name.replace('vw_webinar_', '');
                try {
                    cookies[webinarId] = JSON.parse(decodeURIComponent(parts[1]));
                } catch (e) {
                    console.warn('VW: Could not parse cookie for webinar', webinarId);
                }
            }
        }
        return cookies;
    }

    // ============================================================
    // Helper Functions
    // ============================================================

    function showLoading() {
        $('.vw-loading-overlay').fadeIn(200);
    }

    function hideLoading() {
        $('.vw-loading-overlay').fadeOut(200);
    }

    function showNotification(message, type) {
        type = type || 'info';
        var $notification = $('<div class="vw-notification vw-notification-' + type + '">' + message + '</div>');
        $('body').append($notification);
        $notification.fadeIn(300);
        setTimeout(function() {
            $notification.fadeOut(300, function() {
                $(this).remove();
            });
        }, 4000);
    }

    function formatTime(seconds) {
        var mins = Math.floor(seconds / 60);
        var secs = Math.floor(seconds % 60);
        return mins.toString().padStart(2, '0') + ':' + secs.toString().padStart(2, '0');
    }

    // ============================================================
    // Check if vwData is available
    // ============================================================

    if (typeof vwData === 'undefined') {
        console.error('VW Script: vwData not defined! Script localization failed.');
        return;
    }

    console.log('VW Script: vwData available', {
        ajaxUrl: vwData.ajaxUrl,
        userId: vwData.userId,
        isLoggedIn: vwData.isLoggedIn
    });

    // ============================================================
    // Transfer Cookie Progress on Login
    // ============================================================

    if (vwData.isLoggedIn) {
        var cookieProgress = getAllWebinarCookies();
        var cookieCount = Object.keys(cookieProgress).length;
        
        if (cookieCount > 0) {
            console.log('VW: Found', cookieCount, 'cookie(s) to transfer');
            
            $.ajax({
                url: vwData.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vw_transfer_cookie_progress',
                    nonce: vwData.nonce,
                    cookie_data: cookieProgress
                },
                success: function(response) {
                    if (response.success && response.data.transferred && response.data.transferred.length > 0) {
                        console.log('VW: Transferred progress for webinars:', response.data.transferred);
                        response.data.transferred.forEach(function(webinarId) {
                            deleteCookie('vw_webinar_' + webinarId);
                        });
                        showNotification(response.data.message, 'success');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('VW: Cookie transfer failed:', error);
                }
            });
        }
    }

    // ============================================================
    // Vimeo Player Initialization
    // ============================================================

    function initializeVimeoPlayer() {
        var $container = $('.vw-player-container');
        
        if ($container.length === 0) {
            console.log('VW: No player container found');
            return;
        }

        // Mark as initialized to prevent double init
        if ($container.data('vw-initialized')) {
            console.log('VW: Already initialized');
            return;
        }
        $container.data('vw-initialized', true);

        console.log('VW: Player container found');

        var webinarId = parseInt($container.data('webinar-id'), 10);
        var completionRequired = parseFloat($container.data('completion')) || 90;
        var initialWatchedTime = parseFloat($container.data('watched-time')) || 0;
        var isLoggedIn = $container.data('user-logged-in') === true || 
                         $container.data('user-logged-in') === 'true' ||
                         $container.data('user-logged-in') === 1;

        console.log('VW Config:', {
            webinarId: webinarId,
            completionRequired: completionRequired,
            initialWatchedTime: initialWatchedTime,
            isLoggedIn: isLoggedIn
        });

        var $iframe = $container.find('.vw-vimeo-player iframe');
        
        if ($iframe.length === 0) {
            console.error('VW: No iframe found');
            return;
        }

        console.log('VW: iframe found, src:', $iframe.attr('src'));

        // Check if Vimeo is available
        if (typeof Vimeo === 'undefined' || typeof Vimeo.Player === 'undefined') {
            console.error('VW: Vimeo Player API not loaded!');
            $container.data('vw-initialized', false); // Allow retry
            console.log('VW: Retrying in 500ms...');
            setTimeout(initializeVimeoPlayer, 500);
            return;
        }

        console.log('VW: Vimeo API available, creating player...');

        var player;
        try {
            player = new Vimeo.Player($iframe[0]);
            console.log('VW: Vimeo Player created successfully');
        } catch (e) {
            console.error('VW: Failed to create Vimeo Player:', e);
            return;
        }

        // State variables
        var duration = 0;
        var watchedTime = initialWatchedTime;
        var sessionWatchedTime = 0;
        var isPlaying = false;
        var trackingInterval = null;
        var hasCompleted = $container.find('.vw-completed-banner').length > 0;
        var completionTriggered = false;

        console.log('VW State:', {
            watchedTime: watchedTime,
            hasCompleted: hasCompleted
        });

        // Update UI function
        function updateUI() {
            if (duration <= 0) return;
            
            var progress = Math.min(100, (watchedTime / duration) * 100);
            
            $container.find('.vw-progress-fill').css('width', progress + '%');
            $container.find('.vw-progress-value').text(progress.toFixed(1));
            $container.find('.vw-watched-time-display').text(formatTime(watchedTime));
        }

        // Save progress function
        function saveProgress() {
            if (sessionWatchedTime <= 0) return;
            
            var toSave = sessionWatchedTime;
            sessionWatchedTime = 0; // Reset immediately
            
            console.log('VW: Saving progress - session:', toSave, 'total:', watchedTime, 'duration:', duration);

            if (isLoggedIn) {
                $.ajax({
                    url: vwData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'vw_track_progress',
                        nonce: vwData.nonce,
                        webinar_id: webinarId,
                        watched_time: toSave,
                        duration: duration
                    },
                    success: function(response) {
                        console.log('VW: Save response:', response);
                        if (response.success && response.data) {
                            // Update UI with server values
                            if (response.data.progress !== undefined) {
                                $container.find('.vw-progress-fill').css('width', response.data.progress + '%');
                                $container.find('.vw-progress-value').text(parseFloat(response.data.progress).toFixed(1));
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error('VW: Save failed:', error);
                        // Restore session time on error
                        sessionWatchedTime += toSave;
                    }
                });
            } else {
                // Save to cookie
                var progress = duration > 0 ? (watchedTime / duration) * 100 : 0;
                var cookieData = {
                    watched_time: watchedTime,
                    progress: progress,
                    last_updated: new Date().toISOString()
                };
                setCookie('vw_webinar_' + webinarId, JSON.stringify(cookieData), 30);
                console.log('VW: Saved to cookie');
                
                // Still send duration to server for caching
                $.ajax({
                    url: vwData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'vw_track_progress',
                        webinar_id: webinarId,
                        duration: duration
                    }
                });
            }
        }

        // Complete webinar function
        function completeWebinar() {
            if (completionTriggered || hasCompleted) return;
            
            completionTriggered = true;
            console.log('VW: Completing webinar...');
            
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
                    console.log('VW: Complete response:', response);

                    if (response.success) {
                        hasCompleted = true;
                        window.certificateUrl = response.data.certificate_url;
                        
                        // Show modal
                        var $modal = $('#vw-completion-modal');
                        if ($modal.length) {
                            $modal.fadeIn();
                        } else {
                            showNotification('🎉 Glückwunsch! Webinar abgeschlossen!', 'success');
                            setTimeout(function() {
                                location.reload();
                            }, 2000);
                        }
                    } else {
                        completionTriggered = false;
                        showNotification(response.data.message || 'Fehler beim Abschließen', 'error');
                    }
                },
                error: function(xhr, status, error) {
                    hideLoading();
                    completionTriggered = false;
                    console.error('VW: Complete failed:', error);
                    showNotification('Netzwerkfehler beim Abschließen', 'error');
                }
            });
        }

        // Start tracking
        function startTracking() {
            if (trackingInterval) {
                console.log('VW: Tracking already running');
                return;
            }
            
            console.log('VW: Starting tracking');
            
            trackingInterval = setInterval(function() {
                if (!isPlaying || hasCompleted || completionTriggered) return;
                
                watchedTime += 1;
                sessionWatchedTime += 1;
                
                updateUI();
                
                // Save every 5 seconds
                if (sessionWatchedTime >= 5) {
                    saveProgress();
                }
                
                // Check completion
                if (duration > 0 && isLoggedIn && !completionTriggered) {
                    var progress = (watchedTime / duration) * 100;
                    
                    if (progress >= completionRequired) {
                        console.log('VW: Completion threshold reached!', progress.toFixed(2), '%');
                        stopTracking();
                        saveProgress();
                        completeWebinar();
                    }
                }
            }, 1000);
        }

        // Stop tracking
        function stopTracking() {
            if (trackingInterval) {
                console.log('VW: Stopping tracking');
                clearInterval(trackingInterval);
                trackingInterval = null;
                
                // Save remaining progress
                if (sessionWatchedTime > 0) {
                    saveProgress();
                }
            }
        }

        // Player event handlers
        player.getDuration().then(function(d) {
            duration = d;
            console.log('VW: Video duration:', duration, 'seconds');
            updateUI();
            
            // Initial save to cache duration
            if (duration > 0 && !hasCompleted) {
                $.ajax({
                    url: vwData.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'vw_track_progress',
                        nonce: vwData.nonce,
                        webinar_id: webinarId,
                        watched_time: 0,
                        duration: duration
                    }
                });
            }
        }).catch(function(error) {
            console.error('VW: Error getting duration:', error);
        });

        player.on('play', function() {
            console.log('VW: Play event');
            isPlaying = true;
            startTracking();
        });

        player.on('pause', function() {
            console.log('VW: Pause event');
            isPlaying = false;
            stopTracking();
        });

        player.on('ended', function() {
            console.log('VW: Ended event');
            isPlaying = false;
            stopTracking();
        });

        player.on('error', function(error) {
            console.error('VW: Player error:', error);
        });

        // Detect seek (skip forward detection)
        var lastPosition = 0;
        player.on('timeupdate', function(data) {
            var newPosition = data.seconds;
            
            // If jumped forward more than 2 seconds, don't count it
            if (newPosition > lastPosition + 2) {
                console.log('VW: Forward seek detected, not counting:', (newPosition - lastPosition).toFixed(1), 'seconds');
            }
            
            lastPosition = newPosition;
        });

        console.log('VW: Player initialization complete');
    }

    // ============================================================
    // Modal Handlers
    // ============================================================

    $(document).on('click', '.vw-close-modal', function() {
        $(this).closest('.vw-modal').fadeOut();
        location.reload();
    });

    $(document).on('click', '.vw-download-cert', function() {
        if (window.certificateUrl) {
            window.open(window.certificateUrl, '_blank');
        }
        $(this).closest('.vw-modal').fadeOut();
        location.reload();
    });

    // ============================================================
    // Generate Certificate Button
    // ============================================================

    $(document).on('click', '.vw-generate-certificate', function() {
        var webinarId = $(this).data('webinar-id');
        var $button = $(this);

        $button.prop('disabled', true).text('Generiere...');
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
                    window.open(response.data.pdf_url, '_blank');
                    showNotification('Zertifikat erstellt!', 'success');
                } else {
                    showNotification(response.data.message || 'Fehler beim Generieren', 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotification('Netzwerkfehler', 'error');
            },
            complete: function() {
                $button.prop('disabled', false).text('📄 Zertifikat herunterladen');
            }
        });
    });

    // ============================================================
    // Webinar Liste - Search & Filter
    // ============================================================

    $('.vw-search-input').on('input', function() {
        var searchTerm = $(this).val().toLowerCase();

        $('.vw-webinar-card').each(function() {
            var title = $(this).data('title') || '';
            $(this).toggle(title.indexOf(searchTerm) > -1);
        });
    });

    $('.vw-status-filter').on('change', function() {
        var selectedStatus = $(this).val();

        $('.vw-webinar-card').each(function() {
            var status = $(this).data('status');
            $(this).toggle(selectedStatus === 'all' || status === selectedStatus);
        });
    });

    // ============================================================
    // Webinar Manager - Frontend CRUD
    // ============================================================

    // Tab switching
    $(document).on('click', '.vw-tab-btn', function() {
        var tab = $(this).data('tab');

        $('.vw-tab-btn').removeClass('active');
        $(this).addClass('active');

        $('.vw-tab-content').removeClass('active');
        $('.vw-tab-' + tab).addClass('active');
    });

    // Manager search
    $(document).on('input', '.vw-manager-search-input', function() {
        var searchTerm = $(this).val().toLowerCase();

        $('#vw-manager-tbody tr').each(function() {
            var title = $(this).data('title') || '';
            $(this).toggle(title.indexOf(searchTerm) > -1);
        });
    });

    // Create new webinar button
    $(document).on('click', '#vw-create-new', function(e) {
        e.preventDefault();
        console.log('VW Manager: Create new clicked');

        // Reset form
        $('#vw-webinar-form')[0].reset();
        $('#vw-post-id').val('');
        $('#vw-modal-title').text('Webinar erstellen');

        // Show modal
        $('#vw-modal').fadeIn();
    });

    // Edit webinar button
    $(document).on('click', '.vw-edit', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var webinarId = $(this).data('id');
        console.log('VW Manager: Edit clicked for webinar', webinarId);

        showLoading();

        // Get webinar data via WordPress REST API or from table row
        var $row = $(this).closest('tr');
        var title = $row.find('td:first strong').text();
        var vimeoId = $row.find('td:eq(1)').text();
        var points = $row.find('td:eq(2)').text();
        var completion = $row.find('td:eq(3)').text().replace('%', '');

        // Fill form
        $('#vw-post-id').val(webinarId);
        $('#vw-title').val(title);
        $('#vw-vimeo-id').val(vimeoId);
        $('#vw-points').val(points);
        $('#vw-completion').val(completion);
        $('#vw-modal-title').text('Webinar bearbeiten');

        hideLoading();

        // Show modal
        $('#vw-modal').fadeIn();
    });

    // View stats button
    $(document).on('click', '.vw-view-stats', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var webinarId = $(this).data('id');
        console.log('VW Manager: Stats clicked for webinar', webinarId);

        showLoading();

        $.ajax({
            url: vwData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'vw_manager_stats',
                nonce: vwData.nonce,
                webinar_id: webinarId
            },
            success: function(response) {
                hideLoading();

                if (response.success) {
                    $('#vw-stats-modal-body').html(response.data.html);
                    $('#vw-stats-modal').fadeIn();
                } else {
                    showNotification(response.data.message || 'Fehler beim Laden', 'error');
                }
            },
            error: function() {
                hideLoading();
                showNotification('Netzwerkfehler', 'error');
            }
        });
    });

    // Delete webinar button
    $(document).on('click', '.vw-delete', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var webinarId = $(this).data('id');
        var $row = $(this).closest('tr');
        var title = $row.find('td:first strong').text();

        if (!confirm('Möchten Sie das Webinar "' + title + '" wirklich löschen?')) {
            return;
        }

        console.log('VW Manager: Delete clicked for webinar', webinarId);

        showLoading();

        $.ajax({
            url: vwData.ajaxUrl,
            type: 'POST',
            data: {
                action: 'vw_manager_delete',
                nonce: vwData.nonce,
                webinar_id: webinarId
            },
            success: function(response) {
                hideLoading();

                if (response.success) {
                    $row.fadeOut(300, function() {
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

    // Close modal
    $(document).on('click', '.vw-modal-close', function() {
        $(this).closest('.vw-modal').fadeOut();
    });

    // Close modal on background click
    $(document).on('click', '.vw-modal', function(e) {
        if ($(e.target).hasClass('vw-modal')) {
            $(this).fadeOut();
        }
    });

    // Submit webinar form (create/update)
    $(document).on('submit', '#vw-webinar-form', function(e) {
        e.preventDefault();

        var postId = $('#vw-post-id').val();
        var action = postId ? 'vw_manager_update' : 'vw_manager_create';

        console.log('VW Manager: Submitting form, action:', action);

        var formData = {
            action: action,
            nonce: vwData.nonce,
            post_id: postId,
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
                    showNotification(response.data.message || 'Gespeichert!', 'success');
                    $('#vw-modal').fadeOut();

                    // Reload page to show updated data
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

    // ============================================================
    // Initialize on Document Ready
    // ============================================================

    $(document).ready(function() {
        console.log('VW: Document ready');
        
        // Small delay to ensure Vimeo API is loaded
        setTimeout(function() {
            initializeVimeoPlayer();
        }, 100);
    });

    // Also try on window load as fallback
    $(window).on('load', function() {
        console.log('VW: Window loaded');
        
        // If player wasn't initialized yet, try again
        if ($('.vw-player-container').length > 0 && !$('.vw-player-container').data('vw-initialized')) {
            console.log('VW: Retrying initialization on window load');
            initializeVimeoPlayer();
        }
    });

})(jQuery);
