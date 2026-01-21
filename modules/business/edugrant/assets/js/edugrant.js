/**
 * EduGrant Manager - Frontend JavaScript
 */

(function($) {
    'use strict';

    // Initialize on document ready
    $(document).ready(function() {
        initDocumentForm();
        initApplicationForm();
        initEventSelect();
        initCostCalculation();
        initFilePreview();
    });

    /**
     * Initialize document submission form (eduid parameter)
     */
    function initDocumentForm() {
        var $form = $('#edugrant-document-form');
        if (!$form.length) return;

        var eduid = $form.data('eduid');
        var code = $form.data('code');

        if (eduid) {
            loadGrantDetails(eduid);
        }

        // Form submission
        $form.on('submit', function(e) {
            e.preventDefault();
            submitDocuments($form);
        });
    }

    /**
     * Load grant details via AJAX
     */
    function loadGrantDetails(eduid) {
        var $container = $('#grant-info-container');

        $.ajax({
            url: dgptmEdugrant.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_edugrant_get_grant_details',
                nonce: dgptmEdugrant.nonce,
                eduid: eduid
            },
            success: function(response) {
                if (response.success && response.data.grant) {
                    displayGrantInfo($container, response.data.grant);
                } else {
                    $container.html('<div class="edugrant-error">' + (response.data.message || 'Fehler beim Laden') + '</div>');
                }
            },
            error: function() {
                $container.html('<div class="edugrant-error">Verbindungsfehler</div>');
            }
        });
    }

    /**
     * Display grant information
     * API Field Names (EduGrant module): Name=Bezeichnung, Veranstaltung=lookup, Status, MaxSupport, Nummer
     */
    function displayGrantInfo($container, grant) {
        var eventName = grant.Veranstaltung ? (grant.Veranstaltung.name || grant.Veranstaltung) : 'N/A';
        // MaxSupport is calculated from event's Maximum_Promotion
        var maxFunding = grant.MaxSupport || grant.Maximum_Promotion || 'Keine Angabe';
        // External_Event comes from the linked event
        var isExternal = grant.External_Event || (grant.Veranstaltung && grant.Veranstaltung.External_Event) || false;
        isExternal = (isExternal === true || isExternal === 'true');

        var html = '<div class="grant-info-grid">';
        html += '<div class="grant-info-item"><label>EduGrant-Nummer</label><span>' + (grant.Nummer || grant.Name || 'N/A') + '</span></div>';
        html += '<div class="grant-info-item"><label>Veranstaltung</label><span>' + escapeHtml(eventName) + '</span></div>';
        html += '<div class="grant-info-item"><label>Status</label><span>' + (grant.Status || 'N/A') + '</span></div>';
        html += '<div class="grant-info-item"><label>Max. Förderung</label><span>' + formatCurrency(maxFunding) + '</span></div>';
        html += '<div class="grant-info-item"><label>Art</label><span class="event-type-badge ' + (isExternal ? 'external' : 'internal') + '">' + (isExternal ? 'Extern' : 'Intern') + '</span></div>';
        html += '</div>';

        $container.html(html);

        // Show/hide Teilnahmebescheinigung fieldset based on event type
        if (isExternal) {
            $('#fieldset-teilnahmebescheinigung').show();
            $('#doc-teilnahme').show();
            // Make Teilnahmebescheinigung required for external events
            $('#teilnahmebescheinigung').prop('required', true);
        } else {
            $('#fieldset-teilnahmebescheinigung').hide();
            $('#doc-teilnahme').hide();
            $('#teilnahmebescheinigung').prop('required', false);
        }
    }

    // Global state
    var currentEventIsExternal = false;

    /**
     * Initialize application form (new application)
     */
    function initApplicationForm() {
        var $form = $('#edugrant-application-form');
        if (!$form.length) return;

        var eventId = $form.data('event-id');

        if (eventId) {
            loadEventDetails(eventId);
        }

        // Form submission
        $form.on('submit', function(e) {
            e.preventDefault();
            submitApplication($form);
        });
    }

    /**
     * Load event details via AJAX
     */
    function loadEventDetails(eventId) {
        var $container = $('#event-info-container');
        var $ticketContainer = $('#ticket-status-container');

        $.ajax({
            url: dgptmEdugrant.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_edugrant_get_event_details',
                nonce: dgptmEdugrant.nonce,
                event_id: eventId
            },
            success: function(response) {
                if (response.success && response.data.event) {
                    displayEventInfo($container, response.data.event);

                    // Check if external or internal (API field: External_Event)
                    var isExternal = response.data.event.External_Event || false;
                    isExternal = (isExternal === true || isExternal === 'true');
                    currentEventIsExternal = isExternal;

                    // For internal events: check ticket
                    if (!isExternal) {
                        checkTicketEligibility(eventId, $ticketContainer);
                    }
                } else {
                    $container.html('<div class="edugrant-error">' + (response.data.message || 'Fehler beim Laden') + '</div>');
                }
            },
            error: function() {
                $container.html('<div class="edugrant-error">Verbindungsfehler</div>');
            }
        });
    }

    /**
     * Check ticket eligibility for internal events
     */
    function checkTicketEligibility(eventId, $container) {
        $container.show();

        $.ajax({
            url: dgptmEdugrant.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_edugrant_check_ticket',
                nonce: dgptmEdugrant.nonce,
                event_id: eventId
            },
            success: function(response) {
                if (response.success) {
                    if (response.data.eligible) {
                        // Ticket found - show success
                        var html = '<div class="edugrant-notice" style="background: #d4edda; border-color: #c3e6cb; color: #155724;">';
                        html += '<span class="dashicons dashicons-yes-alt"></span> ';
                        html += response.data.message;
                        html += '</div>';
                        $container.html(html);
                    } else {
                        // No ticket - show error and disable form
                        var html = '<div class="edugrant-error">';
                        html += '<span class="dashicons dashicons-warning"></span> ';
                        html += response.data.message;
                        html += '</div>';
                        $container.html(html);

                        // Disable form submission
                        $('#edugrant-application-form').find('.edugrant-submit-btn').prop('disabled', true);
                        $('#edugrant-application-form').find('input, select').prop('disabled', true);
                    }
                } else {
                    $container.html('<div class="edugrant-error">' + (response.data.message || 'Fehler bei der Ticket-Prüfung') + '</div>');
                }
            },
            error: function() {
                $container.html('<div class="edugrant-error">Verbindungsfehler bei Ticket-Prüfung</div>');
            }
        });
    }

    /**
     * Display event information
     * API Field Names (DGFK_Events module): Name, From_Date, To_Date, Maximum_Promotion, External_Event, Location (lookup)
     */
    function displayEventInfo($container, event) {
        var eventName = event.Name || 'N/A';
        var location = (event.Location && event.Location.name) || event.City || 'Ort nicht angegeben';
        var startDate = event.From_Date ? formatDate(event.From_Date) : 'N/A';
        var endDate = event.To_Date ? formatDate(event.To_Date) : '';
        var maxFunding = event.Maximum_Promotion || 'Keine Angabe';
        var isExternal = event.External_Event || false;
        isExternal = (isExternal === true || isExternal === 'true');

        var dateStr = startDate;
        if (endDate && endDate !== startDate) {
            dateStr += ' - ' + endDate;
        }

        var eventTypeLabel = isExternal ? 'Externe Veranstaltung' : 'Interne Veranstaltung';
        var eventTypeClass = isExternal ? 'external' : 'internal';

        var html = '<div class="grant-info-grid">';
        html += '<div class="grant-info-item"><label>Veranstaltung</label><span>' + escapeHtml(eventName) + '</span></div>';
        html += '<div class="grant-info-item"><label>Datum</label><span>' + dateStr + '</span></div>';
        html += '<div class="grant-info-item"><label>Ort</label><span>' + escapeHtml(location) + '</span></div>';
        html += '<div class="grant-info-item"><label>Max. Förderung</label><span>' + formatCurrency(maxFunding) + '</span></div>';
        html += '<div class="grant-info-item"><label>Veranstaltungsart</label><span class="event-type-badge ' + eventTypeClass + '">' + eventTypeLabel + '</span></div>';
        html += '</div>';

        if (!isExternal) {
            html += '<div class="edugrant-notice" style="margin-top: 15px;">';
            html += '<span class="dashicons dashicons-tickets-alt"></span> ';
            html += 'Für diese interne Veranstaltung ist ein gültiges Ticket erforderlich.';
            html += '</div>';
        }

        $container.html(html);
    }

    /**
     * Initialize event selection (when no event_id is provided)
     */
    function initEventSelect() {
        var $container = $('#event-select-container');
        if (!$container.length) return;

        $.ajax({
            url: dgptmEdugrant.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_edugrant_get_events',
                nonce: dgptmEdugrant.nonce
            },
            success: function(response) {
                if (response.success && response.data.events) {
                    displayEventSelect($container, response.data.events);
                } else {
                    $container.html('<div class="edugrant-error">' + (response.data.message || 'Keine Events verfügbar') + '</div>');
                }
            },
            error: function() {
                $container.html('<div class="edugrant-error">Verbindungsfehler</div>');
            }
        });
    }

    /**
     * Display event selection
     */
    function displayEventSelect($container, events) {
        if (!events.length) {
            $container.html('<div class="edugrant-notice">Aktuell sind keine Veranstaltungen mit EduGrant-Budget verfügbar.</div>');
            return;
        }

        var html = '<select id="event-selector" class="event-selector">';
        html += '<option value="">-- Bitte wählen --</option>';

        events.forEach(function(event) {
            if (event.can_apply && event.has_spots) {
                // API field: Name = Veranstaltungsbezeichnung, From_Date = Von
                var name = event.Name || 'Veranstaltung';
                var date = event.From_Date ? formatDate(event.From_Date) : '';
                html += '<option value="' + event.id + '">' + escapeHtml(name) + ' (' + date + ')</option>';
            }
        });

        html += '</select>';
        html += '<button type="button" id="select-event-btn" class="button" style="margin-left: 10px;">Auswählen</button>';

        $container.html(html);

        // Handle event selection
        $('#select-event-btn').on('click', function() {
            var eventId = $('#event-selector').val();
            if (eventId) {
                window.location.href = window.location.pathname + '?event_id=' + eventId;
            } else {
                alert('Bitte wählen Sie eine Veranstaltung aus.');
            }
        });
    }

    /**
     * Initialize cost calculation
     */
    function initCostCalculation() {
        var $form = $('#edugrant-document-form');
        if (!$form.length) return;

        var $unterkunft = $('#unterkunft');
        var $fahrtkosten = $('#fahrtkosten');
        var $kilometer = $('#kilometer');
        var $hinrueck = $('#hin_rueck');
        var $teilnahme = $('#teilnahmegebuehren');
        var $total = $('#total-amount');

        function calculateTotal() {
            var unterkunft = parseFloat($unterkunft.val()) || 0;
            var fahrtkosten = parseFloat($fahrtkosten.val()) || 0;
            var kilometer = parseInt($kilometer.val()) || 0;
            var teilnahme = parseFloat($teilnahme.val()) || 0;

            var kmCost = kilometer * 0.20;
            if ($hinrueck.is(':checked')) {
                kmCost *= 2;
            }

            var total = unterkunft + fahrtkosten + kmCost + teilnahme;
            $total.text(formatCurrency(total));
        }

        $unterkunft.on('input', calculateTotal);
        $fahrtkosten.on('input', calculateTotal);
        $kilometer.on('input', calculateTotal);
        $hinrueck.on('change', calculateTotal);
        $teilnahme.on('input', calculateTotal);
    }

    /**
     * Initialize file preview
     */
    function initFilePreview() {
        var $fileInput = $('#belege');
        var $preview = $('#file-preview');

        if (!$fileInput.length) return;

        $fileInput.on('change', function() {
            $preview.empty();

            var files = this.files;
            for (var i = 0; i < files.length; i++) {
                var file = files[i];
                var html = '<div class="file-preview-item">';
                html += '<span class="dashicons dashicons-media-default"></span>';
                html += '<span>' + escapeHtml(file.name) + '</span>';
                html += '<span>(' + formatFileSize(file.size) + ')</span>';
                html += '</div>';
                $preview.append(html);
            }
        });
    }

    /**
     * Submit documents
     */
    function submitDocuments($form) {
        var $submitBtn = $form.find('.edugrant-submit-btn');
        var $message = $('#edugrant-message');

        if (!confirm(dgptmEdugrant.i18n.confirmSubmit)) {
            return;
        }

        $submitBtn.prop('disabled', true).text('Wird eingereicht...');

        var formData = new FormData($form[0]);
        formData.append('action', 'dgptm_edugrant_submit_documents');
        formData.append('nonce', dgptmEdugrant.nonce);
        formData.append('eduid', $form.data('eduid'));

        $.ajax({
            url: dgptmEdugrant.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $message.removeClass('error').addClass('success').text(response.data.message).show();
                    $form.hide();
                } else {
                    $message.removeClass('success').addClass('error').text(response.data.message).show();
                    $submitBtn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Abrechnung einreichen');
                }
            },
            error: function() {
                $message.removeClass('success').addClass('error').text(dgptmEdugrant.i18n.error).show();
                $submitBtn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> Abrechnung einreichen');
            }
        });
    }

    /**
     * Submit application
     */
    function submitApplication($form) {
        var $submitBtn = $form.find('.edugrant-submit-btn');
        var $message = $('#edugrant-message');

        if (!$('#terms_accepted').is(':checked')) {
            alert('Bitte akzeptieren Sie die Bedingungen.');
            return;
        }

        if (!confirm(dgptmEdugrant.i18n.confirmSubmit)) {
            return;
        }

        $submitBtn.prop('disabled', true).text('Wird eingereicht...');

        $.ajax({
            url: dgptmEdugrant.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_edugrant_submit',
                nonce: dgptmEdugrant.nonce,
                event_id: $form.data('event-id')
            },
            success: function(response) {
                if (response.success) {
                    $message.removeClass('error').addClass('success').html(
                        response.data.message + '<br><strong>EduGrant-Nummer: ' + response.data.edugrant_number + '</strong>'
                    ).show();
                    $form.hide();
                } else {
                    $message.removeClass('success').addClass('error').text(response.data.message).show();
                    $submitBtn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> EduGrant beantragen');
                }
            },
            error: function() {
                $message.removeClass('success').addClass('error').text(dgptmEdugrant.i18n.error).show();
                $submitBtn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> EduGrant beantragen');
            }
        });
    }

    /**
     * Helper: Format currency
     */
    function formatCurrency(value) {
        if (typeof value === 'string' && isNaN(parseFloat(value))) {
            return value;
        }
        var num = parseFloat(value) || 0;
        return num.toLocaleString('de-DE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' \u20AC';
    }

    /**
     * Helper: Format date
     */
    function formatDate(dateStr) {
        if (!dateStr) return '';
        var date = new Date(dateStr);
        return date.toLocaleDateString('de-DE');
    }

    /**
     * Helper: Format file size
     */
    function formatFileSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    /**
     * Helper: Escape HTML
     */
    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

})(jQuery);
