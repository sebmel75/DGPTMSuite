/**
 * DGPTM Vorstandsliste v2.0 - Frontend JavaScript
 */
(function($) {
    'use strict';

    var VL = {
        config: null,
        currentPeriodeId: null,
        positionCounter: 0,

        init: function() {
            this.config = window.dgptmVorstandsliste || {};
            this.bindEvents();
        },

        bindEvents: function() {
            var self = this;

            // Accordion Toggle
            $(document).on('click', '.dgptm-vl-header', function(e) {
                if ($(e.target).closest('.dgptm-vl-btn-icon').length) return;
                var $item = $(this).closest('.dgptm-vl-item');
                $item.toggleClass('expanded');
            });

            // Vita Modal
            $(document).on('click', '.dgptm-vl-person.has-vita', function() {
                var personId = $(this).data('person-id');
                if (personId) self.openVitaModal(personId);
            });

            // Photo Lightbox
            $(document).on('click', '.dgptm-vl-foto-btn', function(e) {
                e.stopPropagation();
                var foto = $(this).data('foto');
                var caption = $(this).data('caption');
                self.openLightbox(foto, caption);
            });

            // Modal Close
            $(document).on('click', '.dgptm-vl-modal-close, .dgptm-vl-modal-overlay, .dgptm-vl-modal-cancel', function() {
                self.closeModal($(this).closest('.dgptm-vl-modal'));
            });

            // ESC to close
            $(document).on('keydown', function(e) {
                if (e.key === 'Escape') {
                    $('.dgptm-vl-modal:visible').each(function() {
                        self.closeModal($(this));
                    });
                }
            });

            // Edit Mode
            if (this.config.canEdit) {
                // Add new periode
                $(document).on('click', '#dgptm-add-periode', function() {
                    self.openPeriodeModal(0);
                });

                // Edit periode
                $(document).on('click', '.dgptm-vl-edit-periode', function(e) {
                    e.stopPropagation();
                    var periodeId = $(this).closest('.dgptm-vl-item').data('periode-id');
                    self.openPeriodeModal(periodeId);
                });

                // Save periode
                $(document).on('click', '#dgptm-save-periode', function() {
                    self.savePeriode();
                });

                // Delete periode
                $(document).on('click', '#dgptm-delete-periode', function() {
                    if (confirm('Amtsperiode wirklich löschen?')) {
                        self.deletePeriode();
                    }
                });

                // Add position row
                $(document).on('click', '#dgptm-add-position', function() {
                    self.addPositionRow();
                });

                // Remove position row
                $(document).on('click', '.dgptm-vl-position-remove', function() {
                    $(this).closest('.dgptm-vl-position-edit-row').remove();
                });

                // Person search
                $(document).on('input', '.dgptm-vl-person-search', function() {
                    var $wrap = $(this).closest('.dgptm-vl-person-search-wrap');
                    var search = $(this).val();
                    self.searchPersons($wrap, search);
                });

                $(document).on('focus', '.dgptm-vl-person-search', function() {
                    var $wrap = $(this).closest('.dgptm-vl-person-search-wrap');
                    if ($(this).val().length >= 2) {
                        self.searchPersons($wrap, $(this).val());
                    }
                });

                $(document).on('click', '.dgptm-vl-person-search-item', function() {
                    var $wrap = $(this).closest('.dgptm-vl-person-search-wrap');
                    var id = $(this).data('id');
                    var name = $(this).data('name');
                    var isNew = $(this).hasClass('create-new');

                    if (isNew) {
                        self.createPerson($wrap, name);
                    } else {
                        self.selectPerson($wrap, id, name);
                    }
                });

                $(document).on('click', '.dgptm-vl-person-selected-remove', function() {
                    var $wrap = $(this).closest('.dgptm-vl-person-search-wrap');
                    self.clearPersonSelection($wrap);
                });

                // Toggle ausgeschieden
                $(document).on('change', '.dgptm-vl-ausgeschieden-check', function() {
                    var $row = $(this).closest('.dgptm-vl-position-edit-row');
                    var $datumGroup = $row.find('.dgptm-vl-ausgeschieden-datum-group');
                    if ($(this).is(':checked')) {
                        $datumGroup.show();
                    } else {
                        $datumGroup.hide();
                    }
                });

                // Edit person from card view
                $(document).on('click', '.dgptm-av-edit-person', function(e) {
                    e.stopPropagation();
                    var personId = $(this).data('person-id');
                    if (personId) self.openPersonEditModal(personId);
                });

                // Person edit modal
                $(document).on('click', '#dgptm-save-person', function() {
                    self.savePerson();
                });

                // Close search results on outside click
                $(document).on('click', function(e) {
                    if (!$(e.target).closest('.dgptm-vl-person-search-wrap').length) {
                        $('.dgptm-vl-person-search-results').removeClass('active');
                    }
                });
            }
        },

        // ==================== VITA MODAL ====================
        openVitaModal: function(personId) {
            var self = this;
            var $modal = $('#dgptm-vl-vita-modal');

            $modal.find('.dgptm-vl-vita-photo').html('');
            $modal.find('.dgptm-vl-vita-name').text('Laden...');
            $modal.find('.dgptm-vl-vita-content').html('<div class="dgptm-vl-loading"><div class="dgptm-vl-spinner"></div>Laden...</div>');

            this.openModal($modal);

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_get_vita',
                    nonce: this.config.nonce,
                    person_id: personId
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        if (data.foto) {
                            $modal.find('.dgptm-vl-vita-photo').html('<img src="' + data.foto + '" alt="">');
                        }
                        $modal.find('.dgptm-vl-vita-name').text(data.name);
                        $modal.find('.dgptm-vl-vita-content').html(data.vita || '<em>Keine Vita vorhanden.</em>');
                    } else {
                        $modal.find('.dgptm-vl-vita-content').html('<p>Fehler beim Laden.</p>');
                    }
                }
            });
        },

        // ==================== LIGHTBOX ====================
        openLightbox: function(foto, caption) {
            var $modal = $('#dgptm-vl-foto-modal');
            $modal.find('.dgptm-vl-lightbox-img').attr('src', foto);
            $modal.find('.dgptm-vl-lightbox-caption').text(caption || '');
            this.openModal($modal);
        },

        // ==================== PERIODE EDIT ====================
        openPeriodeModal: function(periodeId) {
            var self = this;
            var $modal = $('#dgptm-vl-periode-modal');
            this.currentPeriodeId = periodeId;

            // Reset form
            $('#dgptm-vl-periode-form')[0].reset();
            $('#dgptm-vl-positionen-list').empty();
            this.positionCounter = 0;

            // Show/hide delete button
            if (periodeId) {
                $('#dgptm-delete-periode').show();
                $modal.find('.dgptm-vl-modal-title').text('Amtsperiode bearbeiten');
            } else {
                $('#dgptm-delete-periode').hide();
                $modal.find('.dgptm-vl-modal-title').text('Neue Amtsperiode');
            }

            this.openModal($modal);

            // Load data
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_get_periode',
                    nonce: this.config.nonce,
                    periode_id: periodeId
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        $('#periode_id').val(data.id);
                        $('#periode_start').val(data.start);
                        $('#periode_ende').val(data.ende);
                        $('#periode_notiz').val(data.notiz);

                        // Positionen
                        if (data.positionen && data.positionen.length > 0) {
                            data.positionen.forEach(function(pos) {
                                self.addPositionRow(pos);
                            });
                        }
                    }
                }
            });
        },

        addPositionRow: function(data) {
            var self = this;
            data = data || {};
            var index = this.positionCounter++;

            var html = '<div class="dgptm-vl-position-edit-row" data-index="' + index + '">';
            html += '<button type="button" class="dgptm-vl-position-remove">&times;</button>';

            // Position Type
            html += '<div class="dgptm-vl-form-group">';
            html += '<label>Position</label>';
            html += '<select name="pos_typ_' + index + '" class="dgptm-vl-pos-typ">';
            $.each(this.config.positionen, function(key, label) {
                var selected = (data.typ === key) ? 'selected' : '';
                html += '<option value="' + key + '" ' + selected + '>' + label + '</option>';
            });
            html += '</select>';
            html += '</div>';

            // Person Search
            html += '<div class="dgptm-vl-form-group">';
            html += '<label>Person</label>';
            html += '<div class="dgptm-vl-person-search-wrap">';
            if (data.person_id) {
                html += '<div class="dgptm-vl-person-selected">';
                html += '<span class="dgptm-vl-person-selected-name">' + data.person_name + '</span>';
                html += '<button type="button" class="dgptm-vl-person-selected-remove">&times;</button>';
                html += '<input type="hidden" class="dgptm-vl-person-id" value="' + data.person_id + '">';
                html += '</div>';
                html += '<input type="text" class="dgptm-vl-person-search" placeholder="Person suchen..." style="display:none;">';
            } else {
                html += '<input type="text" class="dgptm-vl-person-search" placeholder="Person suchen...">';
                html += '<input type="hidden" class="dgptm-vl-person-id" value="">';
            }
            html += '<div class="dgptm-vl-person-search-results"></div>';
            html += '</div>';
            html += '</div>';

            // Ausgeschieden
            var checkedAus = data.ausgeschieden ? 'checked' : '';
            var displayDatum = data.ausgeschieden ? '' : 'display:none;';
            html += '<div class="dgptm-vl-form-group">';
            html += '<label>&nbsp;</label>';
            html += '<div class="dgptm-vl-checkbox-row">';
            html += '<input type="checkbox" class="dgptm-vl-ausgeschieden-check" ' + checkedAus + '>';
            html += '<label>Ausgeschieden</label>';
            html += '</div>';
            html += '</div>';

            // Datum
            var ausDate = data.ausgeschieden_datum || '';
            if (ausDate && ausDate.match(/^\d{4}-\d{2}/)) {
                ausDate = ausDate.substr(5, 2) + '/' + ausDate.substr(0, 4);
            }
            html += '<div class="dgptm-vl-form-group dgptm-vl-ausgeschieden-datum-group" style="' + displayDatum + '">';
            html += '<label>Datum</label>';
            html += '<input type="text" class="dgptm-vl-ausgeschieden-datum" placeholder="MM/YYYY" value="' + ausDate + '">';
            html += '</div>';

            // Notiz
            html += '<div class="dgptm-vl-form-group" style="flex:2;">';
            html += '<label>Notiz</label>';
            html += '<input type="text" class="dgptm-vl-pos-notiz" value="' + (data.notiz || '') + '">';
            html += '</div>';

            html += '</div>';

            $('#dgptm-vl-positionen-list').append(html);
        },

        searchPersons: function($wrap, search) {
            var self = this;
            var $results = $wrap.find('.dgptm-vl-person-search-results');

            if (search.length < 2) {
                $results.removeClass('active').empty();
                return;
            }

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_search_persons',
                    nonce: this.config.nonce,
                    search: search
                },
                success: function(response) {
                    if (response.success) {
                        var html = '';
                        response.data.forEach(function(p) {
                            html += '<div class="dgptm-vl-person-search-item" data-id="' + p.id + '" data-name="' + self.escapeHtml(p.name) + '">' + self.escapeHtml(p.name) + '</div>';
                        });
                        html += '<div class="dgptm-vl-person-search-item create-new" data-name="' + self.escapeHtml(search) + '">+ "' + self.escapeHtml(search) + '" neu anlegen</div>';
                        $results.html(html).addClass('active');
                    }
                }
            });
        },

        createPerson: function($wrap, name) {
            var self = this;
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_create_person',
                    nonce: this.config.nonce,
                    name: name
                },
                success: function(response) {
                    if (response.success) {
                        self.selectPerson($wrap, response.data.id, response.data.name);
                    }
                }
            });
        },

        selectPerson: function($wrap, id, name) {
            $wrap.find('.dgptm-vl-person-search').hide().val('');
            $wrap.find('.dgptm-vl-person-id').val(id);
            $wrap.find('.dgptm-vl-person-search-results').removeClass('active');

            var html = '<div class="dgptm-vl-person-selected">';
            html += '<span class="dgptm-vl-person-selected-name">' + this.escapeHtml(name) + '</span>';
            html += '<button type="button" class="dgptm-vl-person-selected-remove">&times;</button>';
            html += '</div>';

            $wrap.find('.dgptm-vl-person-selected').remove();
            $wrap.prepend(html);
        },

        clearPersonSelection: function($wrap) {
            $wrap.find('.dgptm-vl-person-selected').remove();
            $wrap.find('.dgptm-vl-person-id').val('');
            $wrap.find('.dgptm-vl-person-search').show().focus();
        },

        savePeriode: function() {
            var self = this;
            var positionen = [];

            $('.dgptm-vl-position-edit-row').each(function() {
                var $row = $(this);
                positionen.push({
                    typ: $row.find('.dgptm-vl-pos-typ').val(),
                    person_id: $row.find('.dgptm-vl-person-id').val(),
                    ausgeschieden: $row.find('.dgptm-vl-ausgeschieden-check').is(':checked'),
                    ausgeschieden_datum: $row.find('.dgptm-vl-ausgeschieden-datum').val(),
                    notiz: $row.find('.dgptm-vl-pos-notiz').val()
                });
            });

            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_save_periode',
                    nonce: this.config.nonce,
                    periode_id: this.currentPeriodeId,
                    start: $('#periode_start').val(),
                    ende: $('#periode_ende').val(),
                    notiz: $('#periode_notiz').val(),
                    positionen: JSON.stringify(positionen)
                },
                success: function(response) {
                    if (response.success) {
                        var $existing = $('.dgptm-vl-item[data-periode-id="' + response.data.periode_id + '"]');
                        if ($existing.length) {
                            $existing.replaceWith(response.data.html);
                        } else {
                            $('.dgptm-vl-accordion').prepend(response.data.html);
                            $('.dgptm-vl-empty').remove();
                        }
                        self.closeModal($('#dgptm-vl-periode-modal'));
                    } else {
                        alert('Fehler: ' + (response.data.message || 'Unbekannt'));
                    }
                }
            });
        },

        deletePeriode: function() {
            var self = this;
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_delete_periode',
                    nonce: this.config.nonce,
                    periode_id: this.currentPeriodeId
                },
                success: function(response) {
                    if (response.success) {
                        $('.dgptm-vl-item[data-periode-id="' + self.currentPeriodeId + '"]').remove();
                        self.closeModal($('#dgptm-vl-periode-modal'));
                    }
                }
            });
        },

        // ==================== PERSON EDIT ====================
        openPersonEditModal: function(personId) {
            var self = this;
            var $modal = $('#dgptm-vl-person-modal');

            // Reset form
            $('#dgptm-vl-person-form')[0].reset();
            $('#person_edit_id').val(personId);

            this.openModal($modal);

            // Load person data
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_get_person',
                    nonce: this.config.nonce,
                    person_id: personId
                },
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        $('#person_edit_id').val(data.id);
                        $('#person_name').val(data.name);
                        $('#person_titel').val(data.titel || '');
                        $('#person_klinik').val(data.klinik || '');
                        $('#person_email').val(data.email || '');
                        $('#person_linkedin').val(data.linkedin || '');
                        $('#person_vita').val(data.vita || '');
                    }
                }
            });
        },

        savePerson: function() {
            var self = this;
            $.ajax({
                url: this.config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_save_person',
                    nonce: this.config.nonce,
                    person_id: $('#person_edit_id').val(),
                    name: $('#person_name').val(),
                    titel: $('#person_titel').val(),
                    klinik: $('#person_klinik').val(),
                    email: $('#person_email').val(),
                    linkedin: $('#person_linkedin').val(),
                    vita: $('#person_vita').val()
                },
                success: function(response) {
                    if (response.success) {
                        self.closeModal($('#dgptm-vl-person-modal'));
                        // Reload page to show updated social icons
                        location.reload();
                    } else {
                        alert('Fehler: ' + (response.data.message || 'Unbekannt'));
                    }
                }
            });
        },

        // ==================== MODAL HELPERS ====================
        openModal: function($modal) {
            $modal.fadeIn(200);
            $('body').css('overflow', 'hidden');
        },

        closeModal: function($modal) {
            $modal.fadeOut(200, function() {
                // Check after fadeOut completes
                if (!$('.dgptm-vl-modal:visible').length) {
                    $('body').css('overflow', '');
                }
            });
        },

        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(text));
            return div.innerHTML;
        }
    };

    $(document).ready(function() {
        VL.init();
    });

})(jQuery);
