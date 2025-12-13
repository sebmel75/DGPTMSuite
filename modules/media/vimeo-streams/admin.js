/* Vimeo Stream Manager Multi - Admin JavaScript */

(function($) {
    'use strict';
    
    const VSMAdmin = {
        
        currentEditDay: null,
        
        /**
         * Initialisierung
         */
        init: function() {
            this.bindEvents();
            this.initTabs();
        },
        
        /**
         * Tab-Navigation
         */
        initTabs: function() {
            $('.nav-tab').on('click', function(e) {
                e.preventDefault();
                const target = $(this).attr('href').substring(1);
                
                // Tabs wechseln
                $('.nav-tab').removeClass('nav-tab-active');
                $(this).addClass('nav-tab-active');
                
                // Content wechseln
                $('.tab-content').removeClass('active');
                $('#' + target).addClass('active');
            });
        },
        
        /**
         * Events binden
         */
        bindEvents: function() {
            const self = this;
            
            // Tag hinzufügen
            $('#vsm-add-day').on('click', function() {
                self.addDay();
            });
            
            // Enter-Taste im Input
            $('#vsm-new-day-name').on('keypress', function(e) {
                if (e.which === 13) {
                    e.preventDefault();
                    self.addDay();
                }
            });
            
            // Tag bearbeiten
            $(document).on('click', '.vsm-edit-day', function() {
                const day = $(this).closest('.vsm-day-card').data('day');
                self.editDay(day);
            });
            
            // Tag löschen
            $(document).on('click', '.vsm-delete-day', function() {
                const day = $(this).closest('.vsm-day-card').data('day');
                self.deleteDay(day);
            });
            
            // Modal schließen
            $('.vsm-modal-close').on('click', function() {
                self.closeModal();
            });
            
            // Modal-Hintergrund klicken
            $('.vsm-modal').on('click', function(e) {
                if ($(e.target).hasClass('vsm-modal')) {
                    self.closeModal();
                }
            });
            
            // Streams speichern
            $('#vsm-save-streams').on('click', function() {
                self.saveStreams();
            });
            
            // Einstellungen speichern
            $('#vsm-settings-form').on('submit', function(e) {
                e.preventDefault();
                self.saveSettings();
            });
        },
        
        /**
         * Tag hinzufügen
         */
        addDay: function() {
            const dayName = $('#vsm-new-day-name').val().trim();
            
            if (!dayName) {
                alert('Bitte geben Sie einen Tag-Namen ein.');
                $('#vsm-new-day-name').focus();
                return;
            }
            
            // Prüfen ob Tag bereits existiert
            if ($(`.vsm-day-card[data-day="${dayName}"]`).length > 0) {
                alert('Dieser Tag existiert bereits.');
                return;
            }
            
            // Tag hinzufügen und direkt bearbeiten
            this.currentEditDay = dayName;
            this.openModal(dayName);
            
            // Input leeren
            $('#vsm-new-day-name').val('');
        },
        
        /**
         * Tag bearbeiten
         */
        editDay: function(day) {
            this.currentEditDay = day;
            
            // Streams für diesen Tag laden
            $.ajax({
                url: vsmAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vsm_get_day_streams',
                    nonce: vsmAdmin.nonce,
                    day: day
                },
                success: (response) => {
                    if (response.success) {
                        this.openModal(day, response.data);
                    }
                },
                error: () => {
                    alert('Fehler beim Laden der Streams');
                }
            });
        },
        
        /**
         * Modal öffnen
         */
        openModal: function(day, streams = {}) {
            $('#vsm-edit-day-name').text(day);
            
            // Felder leeren oder füllen
            for (let i = 1; i <= 5; i++) {
                $('#stream_' + i).val(streams['stream_' + i] || '');
                $('#caption_' + i).val(streams['caption_' + i] || '');
            }
            
            $('#vsm-stream-editor').fadeIn(200);
        },
        
        /**
         * Modal schließen
         */
        closeModal: function() {
            $('#vsm-stream-editor').fadeOut(200);
            this.currentEditDay = null;
        },
        
        /**
         * Streams speichern
         */
        saveStreams: function() {
            if (!this.currentEditDay) return;
            
            const $button = $('#vsm-save-streams');
            const originalText = $button.text();
            
            // Button deaktivieren
            $button.prop('disabled', true)
                   .html('<span class="vsm-loading-spinner"></span> Speichern...');
            
            // Daten sammeln
            const data = {
                action: 'vsm_save_streams',
                nonce: vsmAdmin.nonce,
                day: this.currentEditDay
            };
            
            for (let i = 1; i <= 5; i++) {
                data['stream_' + i] = $('#stream_' + i).val();
                data['caption_' + i] = $('#caption_' + i).val();
            }
            
            // Speichern
            $.ajax({
                url: vsmAdmin.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        this.showMessage('Streams erfolgreich gespeichert!', 'success');
                        this.closeModal();
                        this.reloadDaysList();
                    } else {
                        alert('Fehler beim Speichern');
                    }
                },
                error: () => {
                    alert('Fehler beim Speichern der Streams');
                },
                complete: () => {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Tag löschen
         */
        deleteDay: function(day) {
            if (!confirm(`Möchten Sie den Tag "${day}" wirklich löschen?`)) {
                return;
            }
            
            const $card = $(`.vsm-day-card[data-day="${day}"]`);
            $card.css('opacity', '0.5');
            
            $.ajax({
                url: vsmAdmin.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'vsm_delete_day',
                    nonce: vsmAdmin.nonce,
                    day: day
                },
                success: (response) => {
                    if (response.success) {
                        $card.fadeOut(300, function() {
                            $(this).remove();
                            
                            // Prüfen ob noch Tage vorhanden
                            if ($('.vsm-day-card').length === 0) {
                                $('.vsm-days-grid').html('<p class="vsm-no-days">Noch keine Tage angelegt.</p>');
                            }
                        });
                        
                        this.showMessage('Tag erfolgreich gelöscht', 'success');
                    } else {
                        alert('Fehler beim Löschen');
                        $card.css('opacity', '1');
                    }
                },
                error: () => {
                    alert('Fehler beim Löschen des Tages');
                    $card.css('opacity', '1');
                }
            });
        },
        
        /**
         * Einstellungen speichern
         */
        saveSettings: function() {
            const $form = $('#vsm-settings-form');
            const $button = $form.find('button[type="submit"]');
            const originalText = $button.text();
            const $message = $('.vsm-settings-message');
            
            // Button deaktivieren
            $button.prop('disabled', true)
                   .html('<span class="vsm-loading-spinner"></span> Speichern...');
            
            // Daten sammeln
            const data = {
                action: 'vsm_save_settings',
                nonce: vsmAdmin.nonce
            };
            
            // Checkboxen
            $form.find('input[type="checkbox"]').each(function() {
                data[$(this).attr('name')] = $(this).is(':checked') ? 1 : 0;
            });
            
            // Text- und Number-Felder
            $form.find('input[type="text"], input[type="number"], textarea, select').each(function() {
                data[$(this).attr('name')] = $(this).val();
            });
            
            // Speichern
            $.ajax({
                url: vsmAdmin.ajaxUrl,
                type: 'POST',
                data: data,
                success: (response) => {
                    if (response.success) {
                        $message.removeClass('error')
                                .addClass('success')
                                .text('Einstellungen erfolgreich gespeichert!')
                                .fadeIn(300);
                        
                        // Animation
                        $form.addClass('vsm-success-flash');
                        setTimeout(() => {
                            $form.removeClass('vsm-success-flash');
                        }, 1000);
                        
                        // Nachricht ausblenden
                        setTimeout(() => {
                            $message.fadeOut(300);
                        }, 3000);
                    } else {
                        $message.removeClass('success')
                                .addClass('error')
                                .text('Fehler beim Speichern der Einstellungen')
                                .fadeIn(300);
                    }
                },
                error: () => {
                    $message.removeClass('success')
                            .addClass('error')
                            .text('Netzwerkfehler beim Speichern')
                            .fadeIn(300);
                },
                complete: () => {
                    $button.prop('disabled', false).text(originalText);
                }
            });
        },
        
        /**
         * Tagesliste neu laden
         */
        reloadDaysList: function() {
            location.reload(); // Einfachste Lösung für jetzt
        },
        
        /**
         * Nachricht anzeigen
         */
        showMessage: function(message, type = 'success') {
            // Temporäre Nachricht oben anzeigen
            const $notice = $('<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>');
            $('.vsm-admin-wrap').prepend($notice);
            
            setTimeout(() => {
                $notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 3000);
        }
    };
    
    // Initialisierung
    $(document).ready(function() {
        VSMAdmin.init();
    });
    
})(jQuery);
