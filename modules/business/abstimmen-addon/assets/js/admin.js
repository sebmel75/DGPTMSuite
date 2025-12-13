/**
 * DGPTM Abstimmen-Addon - Admin JavaScript
 * Version: 4.0.0
 */

(function($) {
    'use strict';

    /**
     * Poll Management
     */
    const PollManagement = {
        init: function() {
            this.bindEvents();
            this.loadActivePolls();
        },

        bindEvents: function() {
            // Toggle poll status
            $(document).on('change', '.pollToggleSwitch', this.handlePollToggle);

            // Load poll details
            $(document).on('click', '.loadDetailsBtn', this.handleLoadDetails);

            // Beamer toggle
            $(document).on('change', '.beamerAllSwitch', this.handleBeamerToggle);

            // New poll form
            $('#newPollBtn').on('click', this.showNewPollForm);
            $('#createPollForm').on('submit', this.handleCreatePoll);

            // Settings modal
            $('#dgptm_openSettings').on('click', this.openSettingsModal);
            $('#dgptm_closeSettings').on('click', this.closeSettingsModal);

            // Close modal on backdrop click
            $('#dgptm_settingsBackdrop').on('click', function(e) {
                if (e.target === this) {
                    PollManagement.closeSettingsModal();
                }
            });
        },

        handlePollToggle: function(e) {
            const $checkbox = $(this);
            const pollId = $checkbox.data('id');
            const isActive = $checkbox.is(':checked');

            $.ajax({
                url: dgptm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dgptm_toggle_poll',
                    poll_id: pollId,
                    status: isActive ? 'active' : 'archived',
                    nonce: dgptm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        PollManagement.showNotice('Umfrage-Status aktualisiert', 'success');
                    } else {
                        PollManagement.showNotice('Fehler beim Aktualisieren', 'error');
                        $checkbox.prop('checked', !isActive);
                    }
                },
                error: function() {
                    PollManagement.showNotice('AJAX-Fehler', 'error');
                    $checkbox.prop('checked', !isActive);
                }
            });
        },

        handleLoadDetails: function(e) {
            e.preventDefault();
            const $btn = $(this);
            const pollId = $btn.data('id');
            const $detailsRow = $btn.closest('tr').next('.poll-details');
            const $detailsContent = $('#pollDetails_' + pollId);

            // Toggle visibility
            if ($detailsRow.is(':visible')) {
                $detailsRow.hide();
                $btn.text('Details');
                return;
            }

            // Load if not loaded yet
            if ($detailsContent.data('loaded') === '0') {
                $detailsContent.html('<p>Lade Details...</p>');

                $.ajax({
                    url: dgptm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'dgptm_get_poll_details',
                        poll_id: pollId,
                        nonce: dgptm_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $detailsContent.html(response.data.html);
                            $detailsContent.data('loaded', '1');
                            $detailsRow.show();
                            $btn.text('Schließen');

                            // Initialize QR codes if present
                            PollManagement.initQRCodes(pollId);
                        } else {
                            $detailsContent.html('<p>Fehler beim Laden</p>');
                        }
                    },
                    error: function() {
                        $detailsContent.html('<p>AJAX-Fehler</p>');
                    }
                });
            } else {
                $detailsRow.show();
                $btn.text('Schließen');
            }
        },

        handleBeamerToggle: function(e) {
            const $checkbox = $(this);
            const pollId = $checkbox.data('pid');
            const isOn = $checkbox.is(':checked');

            $.ajax({
                url: dgptm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dgptm_set_beamer_mode',
                    mode: isOn ? 'results_all' : 'auto',
                    poll_id: isOn ? pollId : 0,
                    nonce: dgptm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Uncheck other beamer toggles
                        if (isOn) {
                            $('.beamerAllSwitch').not($checkbox).prop('checked', false);
                        }
                        PollManagement.showNotice('Beamer-Modus aktualisiert', 'success');
                    } else {
                        PollManagement.showNotice('Fehler', 'error');
                        $checkbox.prop('checked', !isOn);
                    }
                },
                error: function() {
                    PollManagement.showNotice('AJAX-Fehler', 'error');
                    $checkbox.prop('checked', !isOn);
                }
            });
        },

        showNewPollForm: function(e) {
            e.preventDefault();
            $('#newPollForm').slideToggle();
        },

        handleCreatePoll: function(e) {
            e.preventDefault();
            const $form = $(this);
            const formData = new FormData(this);
            formData.append('action', 'dgptm_create_poll');
            formData.append('nonce', dgptm_ajax.nonce);

            $.ajax({
                url: dgptm_ajax.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        PollManagement.showNotice('Umfrage erstellt', 'success');
                        location.reload();
                    } else {
                        PollManagement.showNotice(response.data.message || 'Fehler', 'error');
                    }
                },
                error: function() {
                    PollManagement.showNotice('AJAX-Fehler', 'error');
                }
            });
        },

        openSettingsModal: function(e) {
            e.preventDefault();
            const $modal = $('#dgptm_settingsBackdrop');
            const $content = $('#dgptm_settingsContent');

            $modal.css('display', 'flex');

            // Load settings if not loaded
            if ($content.find('form').length === 0) {
                $.ajax({
                    url: dgptm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'dgptm_get_settings',
                        nonce: dgptm_ajax.nonce
                    },
                    success: function(response) {
                        if (response.success) {
                            $content.html(response.data.html);
                        } else {
                            $content.html('<p>Fehler beim Laden der Einstellungen</p>');
                        }
                    },
                    error: function() {
                        $content.html('<p>AJAX-Fehler</p>');
                    }
                });
            }
        },

        closeSettingsModal: function() {
            $('#dgptm_settingsBackdrop').hide();
        },

        initQRCodes: function(pollId) {
            if (typeof window.dgptmDrawQR === 'function') {
                window.dgptmDrawQR(pollId);
            }
        },

        loadActivePolls: function() {
            // Auto-expand active poll details on page load
            const $activeRow = $('.poll-header[data-active="1"]').first();
            if ($activeRow.length) {
                $activeRow.find('.loadDetailsBtn').trigger('click');
            }
        },

        showNotice: function(message, type) {
            const $notice = $('<div>')
                .addClass('notice notice-' + type + ' is-dismissible')
                .html('<p>' + message + '</p>')
                .hide();

            $('.wrap').prepend($notice);
            $notice.slideDown();

            setTimeout(function() {
                $notice.slideUp(function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };

    /**
     * Zoom Management
     */
    const ZoomManagement = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Zoom pull registrants
            $(document).on('click', '#dgptm_zoomPullBtn', this.handlePullRegistrants);

            // Zoom sync actions
            $(document).on('click', '#dgptm_zoomSyncRegister', this.handleSyncRegister);
            $(document).on('click', '#dgptm_zoomSyncCancel', this.handleSyncCancel);

            // Zoom log
            $(document).on('click', '#dgptm_zoomLogClear', this.handleLogClear);
            $(document).on('click', '#dgptm_zoomLogDownload', this.handleLogDownload);
        },

        handlePullRegistrants: function(e) {
            e.preventDefault();
            const $btn = $(this);
            const originalText = $btn.text();

            $btn.prop('disabled', true).text('Laden...');

            $.ajax({
                url: dgptm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dgptm_zoom_pull_registrants',
                    nonce: dgptm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $('#dgptm_zoomRegistrantsList').html(response.data.html);
                        PollManagement.showNotice('Registrierungen geladen', 'success');
                    } else {
                        PollManagement.showNotice(response.data.message || 'Fehler', 'error');
                    }
                },
                error: function() {
                    PollManagement.showNotice('AJAX-Fehler', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        handleSyncRegister: function(e) {
            e.preventDefault();
            if (!confirm('Alle fehlenden Benutzer bei Zoom registrieren?')) return;

            ZoomManagement.syncAction('register_missing', $(this));
        },

        handleSyncCancel: function(e) {
            e.preventDefault();
            if (!confirm('Alle überschüssigen Zoom-Registrierungen stornieren?')) return;

            ZoomManagement.syncAction('cancel_extras', $(this));
        },

        syncAction: function(action, $btn) {
            const originalText = $btn.text();
            $btn.prop('disabled', true).text('Verarbeite...');

            $.ajax({
                url: dgptm_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'dgptm_zoom_sync',
                    do: action,
                    nonce: dgptm_ajax.nonce
                },
                success: function(response) {
                    if (response.success) {
                        PollManagement.showNotice(response.data.message || 'Synchronisiert', 'success');
                        // Reload registrants list
                        $('#dgptm_zoomPullBtn').trigger('click');
                    } else {
                        PollManagement.showNotice(response.data.message || 'Fehler', 'error');
                    }
                },
                error: function() {
                    PollManagement.showNotice('AJAX-Fehler', 'error');
                },
                complete: function() {
                    $btn.prop('disabled', false).text(originalText);
                }
            });
        },

        handleLogClear: function(e) {
            e.preventDefault();
            if (!confirm('Wirklich alle Log-Einträge löschen?')) return;

            window.location.href = $(this).attr('href');
        },

        handleLogDownload: function(e) {
            // Allow default link behavior (download)
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        if (typeof dgptm_ajax !== 'undefined') {
            PollManagement.init();
            ZoomManagement.init();
        }
    });

})(jQuery);
