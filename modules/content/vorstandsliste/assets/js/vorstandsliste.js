/**
 * DGPTM Vorstandsliste - Frontend JavaScript
 */
(function($) {
    'use strict';

    var VitaModal = {
        $modal: null,

        init: function() {
            this.$modal = $('#dgptm-vita-modal');
            if (!this.$modal.length) return;

            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // Person mit Vita anklicken
            $(document).on('click', '.dgptm-has-vita', function(e) {
                e.preventDefault();
                var personId = $(this).data('person-id');
                if (personId) {
                    self.load(personId);
                }
            });

            // Modal schliessen
            this.$modal.on('click', '.dgptm-vita-modal-close', function() {
                self.close();
            });

            // Ausserhalb klicken schliesst Modal
            this.$modal.on('click', function(e) {
                if ($(e.target).is('.dgptm-vita-modal')) {
                    self.close();
                }
            });

            // ESC schliesst Modal
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.$modal.is(':visible')) {
                    self.close();
                }
            });
        },

        load: function(personId) {
            var self = this;

            // Loading-Zustand
            this.$modal.find('.dgptm-vita-modal-photo').html('');
            this.$modal.find('.dgptm-vita-modal-name').text('Lädt...');
            this.$modal.find('.dgptm-vita-modal-body').html('<p>Vita wird geladen...</p>');
            this.open();

            // AJAX Request
            $.ajax({
                url: dgptmVorstandsliste.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_get_vita',
                    nonce: dgptmVorstandsliste.nonce,
                    person_id: personId
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;

                        // Foto
                        if (data.foto) {
                            self.$modal.find('.dgptm-vita-modal-photo').html(
                                '<img src="' + data.foto + '" alt="' + data.name + '">'
                            );
                        } else {
                            self.$modal.find('.dgptm-vita-modal-photo').html('');
                        }

                        // Name
                        self.$modal.find('.dgptm-vita-modal-name').text(data.name);

                        // Vita
                        if (data.vita) {
                            self.$modal.find('.dgptm-vita-modal-body').html(data.vita);
                        } else {
                            self.$modal.find('.dgptm-vita-modal-body').html('<p><em>Keine Vita verfügbar.</em></p>');
                        }
                    } else {
                        self.$modal.find('.dgptm-vita-modal-name').text('Fehler');
                        self.$modal.find('.dgptm-vita-modal-body').html('<p>' + response.data.message + '</p>');
                    }
                },
                error: function() {
                    self.$modal.find('.dgptm-vita-modal-name').text('Fehler');
                    self.$modal.find('.dgptm-vita-modal-body').html('<p>Fehler beim Laden der Vita.</p>');
                }
            });
        },

        open: function() {
            this.$modal.fadeIn(200);
            $('body').css('overflow', 'hidden');
        },

        close: function() {
            this.$modal.fadeOut(200);
            $('body').css('overflow', '');
        }
    };

    var FotoLightbox = {
        $lightbox: null,

        init: function() {
            this.$lightbox = $('#dgptm-foto-lightbox');
            if (!this.$lightbox.length) return;

            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // Foto in Tabelle anklicken
            $(document).on('click', '.dgptm-periode-foto', function(e) {
                e.preventDefault();
                var fullUrl = $(this).data('full');
                var caption = $(this).data('caption');
                self.open(fullUrl, caption);
            });

            // Foto-Button in Card anklicken
            $(document).on('click', '.dgptm-foto-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var fullUrl = $(this).data('full');
                var caption = $(this).data('caption');
                self.open(fullUrl, caption);
            });

            // Lightbox schliessen
            this.$lightbox.on('click', '.dgptm-foto-lightbox-close', function() {
                self.close();
            });

            // Ausserhalb klicken schliesst Lightbox
            this.$lightbox.on('click', function(e) {
                if ($(e.target).is('.dgptm-foto-lightbox') || $(e.target).is('.dgptm-foto-lightbox-content')) {
                    self.close();
                }
            });

            // ESC schliesst Lightbox
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape' && self.$lightbox.is(':visible')) {
                    self.close();
                }
            });
        },

        open: function(imageUrl, caption) {
            this.$lightbox.find('.dgptm-foto-lightbox-img').attr('src', imageUrl);
            this.$lightbox.find('.dgptm-foto-lightbox-caption').text(caption || '');
            this.$lightbox.fadeIn(200);
            $('body').css('overflow', 'hidden');
        },

        close: function() {
            this.$lightbox.fadeOut(200);
            $('body').css('overflow', '');
        }
    };

    var CardToggle = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Toggle-Button klicken
            $(document).on('click', '.dgptm-toggle-btn', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var $card = $(this).closest('.dgptm-vorstand-card');
                $card.toggleClass('collapsed');
            });

            // Header klicken (ausser auf Buttons)
            $(document).on('click', '.dgptm-card-header', function(e) {
                if (!$(e.target).closest('button, a').length) {
                    var $card = $(this).closest('.dgptm-vorstand-card');
                    $card.toggleClass('collapsed');
                }
            });
        }
    };

    var ResponsiveTable = {
        init: function() {
            // Data-Label fuer Mobile-Ansicht setzen
            var labels = [];
            $('.dgptm-vorstandsliste-table th').each(function() {
                labels.push($(this).text());
            });

            $('.dgptm-vorstandsliste-table tbody tr').each(function() {
                $(this).find('td').each(function(index) {
                    if (labels[index]) {
                        $(this).attr('data-label', labels[index]);
                    }
                });
            });
        }
    };

    // Initialisierung
    $(document).ready(function() {
        VitaModal.init();
        FotoLightbox.init();
        CardToggle.init();
        ResponsiveTable.init();
    });

})(jQuery);
