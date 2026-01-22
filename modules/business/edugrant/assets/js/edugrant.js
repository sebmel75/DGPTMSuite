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
        initGuestForm();
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
        var $form = $('#edugrant-application-form');
        var loggedInAttr = $form.data('logged-in');
        var isLoggedIn = loggedInAttr === 1 || loggedInAttr === '1';

        console.log('loadEventDetails - logged-in attribute:', loggedInAttr, 'type:', typeof loggedInAttr, 'isLoggedIn:', isLoggedIn);

        $.ajax({
            url: dgptmEdugrant.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_edugrant_get_event_details',
                nonce: dgptmEdugrant.nonce,
                event_id: eventId
            },
            success: function(response) {
                console.log('loadEventDetails AJAX response:', response);
                console.log('response.data:', response.data);
                console.log('response.data.event:', response.data ? response.data.event : 'no data');

                if (response.success && response.data.event) {
                    displayEventInfo($container, response.data.event);

                    // Check if external or internal (API field: External_Event)
                    var isExternal = response.data.event.External_Event || false;
                    isExternal = (isExternal === true || isExternal === 'true');
                    currentEventIsExternal = isExternal;

                    // For internal events: check ticket (only for logged-in users)
                    // Guests handle ticket check through email verification flow
                    console.log('Ticket check decision - isExternal:', isExternal, 'isLoggedIn:', isLoggedIn, 'will check:', !isExternal && isLoggedIn);
                    if (!isExternal && isLoggedIn) {
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
        // Debug logging
        console.log('displayEventInfo received:', event);
        console.log('Event keys:', Object.keys(event || {}));
        console.log('From_Date:', event.From_Date);
        console.log('City:', event.City);
        console.log('External_Event:', event.External_Event);
        console.log('Maximum_Promotion:', event.Maximum_Promotion);

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
     * Display event selection as card list
     */
    function displayEventSelect($container, events) {
        console.log('displayEventSelect called with', events.length, 'events');

        if (!events.length) {
            $container.html('<div class="edugrant-notice"><span class="dashicons dashicons-info"></span> Aktuell sind keine Veranstaltungen mit EduGrant-Budget verfügbar.</div>');
            return;
        }

        // Filter events where application deadline hasn't passed
        var displayEvents = events.filter(function(event) {
            return event.can_apply; // Show even if no spots (limit reached)
        });

        console.log('Events to display:', displayEvents.length);

        if (!displayEvents.length) {
            $container.html('<div class="edugrant-notice"><span class="dashicons dashicons-info"></span> Aktuell sind keine Veranstaltungen mit offener Antragsfrist verfügbar.</div>');
            return;
        }

        var html = '<div class="edugrant-events-grid">';

        displayEvents.forEach(function(event) {
            // API fields from Modules.json
            var name = event.Name || 'Veranstaltung';
            var startDate = event.From_Date ? formatDate(event.From_Date) : '';
            var endDate = event.To_Date ? formatDate(event.To_Date) : '';
            var location = (event.Location && event.Location.name) || event.City || '';
            var maxFunding = event.Maximum_Promotion || '';
            var spotsAvailable = event.spots_available || 0;
            var hasSpots = event.has_spots !== false && spotsAvailable > 0;
            var isExternal = event.External_Event === true || event.External_Event === 'true';

            var dateStr = startDate;
            if (endDate && endDate !== startDate) {
                dateStr += ' - ' + endDate;
            }

            // Card classes - add disabled class if no spots
            var cardClass = 'edugrant-event-card';
            if (hasSpots) {
                cardClass += ' selectable';
            } else {
                cardClass += ' disabled';
            }

            html += '<div class="' + cardClass + '" data-event-id="' + event.id + '">';
            html += '<div class="event-header">';
            html += '<h4 class="event-title">' + escapeHtml(name);
            if (!hasSpots) {
                html += ' <span class="limit-reached">(Limit erreicht)</span>';
            }
            html += '</h4>';
            html += '<span class="event-type-badge ' + (isExternal ? 'external' : 'internal') + '">' + (isExternal ? 'Extern' : 'Intern') + '</span>';
            html += '</div>';

            html += '<div class="event-details">';
            if (dateStr) {
                html += '<div class="event-detail"><span class="dashicons dashicons-calendar-alt"></span> ' + dateStr + '</div>';
            }
            if (location) {
                html += '<div class="event-detail"><span class="dashicons dashicons-location"></span> ' + escapeHtml(location) + '</div>';
            }
            if (maxFunding) {
                html += '<div class="event-detail highlight"><span class="dashicons dashicons-awards"></span> Max. Förderung: <strong>' + formatCurrency(maxFunding) + '</strong></div>';
            }
            if (spotsAvailable < 999) {
                var spotsText = hasSpots ? 'Noch ' + spotsAvailable + ' Plätze' : 'Ausgebucht';
                var spotsClass = hasSpots ? '' : ' warning';
                html += '<div class="event-detail' + spotsClass + '"><span class="dashicons dashicons-groups"></span> ' + spotsText + '</div>';
            }
            html += '</div>';

            html += '<div class="event-actions">';
            if (hasSpots) {
                html += '<a href="' + window.location.pathname + '?event_id=' + event.id + '" class="button edugrant-apply-btn">';
                html += '<span class="dashicons dashicons-yes"></span> Auswählen</a>';
            } else {
                html += '<span class="edugrant-unavailable"><span class="dashicons dashicons-no"></span> Ausgebucht</span>';
            }
            html += '</div>';

            html += '</div>';
        });

        html += '</div>';

        $container.html(html);

        // Make selectable cards clickable
        $container.find('.edugrant-event-card.selectable').on('click', function(e) {
            if (!$(e.target).is('a, button')) {
                var eventId = $(this).data('event-id');
                window.location.href = window.location.pathname + '?event_id=' + eventId;
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
     * Initialize guest form functionality (non-logged-in users)
     */
    function initGuestForm() {
        var $form = $('#edugrant-application-form');
        if (!$form.length) return;

        var isLoggedIn = $form.data('logged-in') === 1 || $form.data('logged-in') === '1';

        // If user is logged in, skip guest functionality
        if (isLoggedIn) return;

        var $emailStep = $('#guest-step-email');
        var $contactStep = $('#guest-step-contact');
        var $berechtigungStep = $('#guest-step-berechtigung');
        var $checkEmailBtn = $('#check-email-btn');
        var $emailInput = $('#guest_email');
        var $emailResult = $('#email-check-result');
        var $termsSection = $form.find('.edugrant-terms');
        var $submitBtn = $('#submit-application-btn');

        // Hide terms and submit initially until email is checked
        $termsSection.hide();
        $submitBtn.hide();

        // Show "sonstiges" text field when selected
        $('#guest_berechtigung').on('change', function() {
            if ($(this).val() === 'sonstiges') {
                $('#guest_berechtigung_sonstiges_row').show();
                $('#guest_berechtigung_text').prop('required', true);
            } else {
                $('#guest_berechtigung_sonstiges_row').hide();
                $('#guest_berechtigung_text').prop('required', false);
            }
        });

        // Email check button click
        $checkEmailBtn.on('click', function() {
            var email = $emailInput.val().trim();

            if (!email || !isValidEmail(email)) {
                $emailResult.html('<div class="edugrant-error"><span class="dashicons dashicons-warning"></span> Bitte geben Sie eine gültige E-Mail-Adresse ein.</div>').show();
                return;
            }

            checkGuestEmail(email);
        });

        // Enter key on email field
        $emailInput.on('keypress', function(e) {
            if (e.which === 13) {
                e.preventDefault();
                $checkEmailBtn.click();
            }
        });

        /**
         * Check guest email via AJAX
         */
        function checkGuestEmail(email) {
            var eventId = $form.data('event-id');

            $checkEmailBtn.prop('disabled', true).text('Prüfe...');
            $emailResult.html('<div class="loading-indicator"><span class="dashicons dashicons-update spin"></span> E-Mail wird geprüft...</div>').show();

            $.ajax({
                url: dgptmEdugrant.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_edugrant_check_guest_email',
                    nonce: dgptmEdugrant.nonce,
                    email: email,
                    event_id: eventId
                },
                success: function(response) {
                    $checkEmailBtn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> E-Mail prüfen');

                    if (response.success) {
                        handleEmailCheckResult(response.data);
                    } else {
                        $emailResult.html('<div class="edugrant-error"><span class="dashicons dashicons-warning"></span> ' + (response.data.message || 'Fehler bei der Prüfung') + '</div>');
                    }
                },
                error: function() {
                    $checkEmailBtn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> E-Mail prüfen');
                    $emailResult.html('<div class="edugrant-error"><span class="dashicons dashicons-warning"></span> Verbindungsfehler</div>');
                }
            });
        }

        /**
         * Handle email check result
         */
        function handleEmailCheckResult(data) {
            console.log('Email check result:', data);

            // Store contact info in hidden fields
            $('#guest_contact_id').val(data.contact_id || '');
            $('#guest_contact_found').val(data.contact_found ? '1' : '0');
            $('#guest_has_ticket').val(data.has_ticket ? '1' : '0');
            $('#guest_is_member').val(data.is_member ? '1' : '0');

            // Show result message
            var resultClass = data.can_apply ? 'edugrant-notice' : 'edugrant-error';
            var icon = data.contact_found ? (data.can_apply ? 'yes-alt' : 'warning') : 'info';

            if (data.contact_found && data.can_apply) {
                resultClass = 'edugrant-notice';
                resultClass += ' style="background: #d4edda; border-color: #c3e6cb; color: #155724;"';
            }

            var html = '<div class="' + resultClass + '">';
            html += '<span class="dashicons dashicons-' + icon + '"></span> ';
            html += data.message;
            html += '</div>';

            $emailResult.html(html);

            // Lock email field
            $emailInput.prop('readonly', true);
            $checkEmailBtn.hide();

            // Add "change email" button
            if (!$('#change-email-btn').length) {
                $checkEmailBtn.after('<button type="button" id="change-email-btn" class="button">E-Mail ändern</button>');
                $('#change-email-btn').on('click', function() {
                    resetGuestForm();
                });
            }

            if (!data.can_apply) {
                // Cannot apply - show error and stop
                $contactStep.hide();
                $berechtigungStep.hide();
                $termsSection.hide();
                $submitBtn.hide();
                return;
            }

            // Show/hide contact data fields
            if (data.needs_contact_data) {
                $contactStep.show();
                $contactStep.find('input').prop('required', true);
            } else {
                $contactStep.hide();
                $contactStep.find('input').prop('required', false);
            }

            // Show/hide eligibility proof fields
            if (data.needs_eligibility_proof) {
                $berechtigungStep.show();
                $('#guest_berechtigung').prop('required', true);
                $('#guest_nachweis').prop('required', true);
            } else {
                $berechtigungStep.hide();
                $('#guest_berechtigung').prop('required', false);
                $('#guest_nachweis').prop('required', false);
            }

            // Show terms and submit button
            $termsSection.show();
            $submitBtn.show();
        }

        /**
         * Reset guest form to email step
         */
        function resetGuestForm() {
            $emailInput.prop('readonly', false).val('');
            $checkEmailBtn.show();
            $('#change-email-btn').remove();
            $emailResult.hide();
            $contactStep.hide();
            $berechtigungStep.hide();
            $termsSection.hide();
            $submitBtn.hide();

            // Clear hidden fields
            $('#guest_contact_id').val('');
            $('#guest_contact_found').val('0');
            $('#guest_has_ticket').val('0');
            $('#guest_is_member').val('0');
        }

        /**
         * Validate email format
         */
        function isValidEmail(email) {
            var re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
        }
    }

    /**
     * Submit application (supports both logged-in and guest users)
     */
    function submitApplication($form) {
        var $submitBtn = $form.find('.edugrant-submit-btn');
        var $message = $('#edugrant-message');

        // Check all required checkboxes
        if (!$('#terms_accepted').is(':checked')) {
            alert('Bitte akzeptieren Sie die Bedingungen.');
            return;
        }

        // Check new checkboxes if they exist
        if ($('#travel_policy_accepted').length && !$('#travel_policy_accepted').is(':checked')) {
            alert('Bitte akzeptieren Sie die Reisekostenrichtlinie.');
            return;
        }

        if ($('#privacy_accepted').length && !$('#privacy_accepted').is(':checked')) {
            alert('Bitte stimmen Sie der Datenschutzerklärung zu.');
            return;
        }

        if (!confirm(dgptmEdugrant.i18n.confirmSubmit)) {
            return;
        }

        $submitBtn.prop('disabled', true).text('Wird eingereicht...');

        // Determine if this is a guest submission
        var isLoggedIn = $form.data('logged-in') === 1 || $form.data('logged-in') === '1';
        var isGuest = !isLoggedIn;

        // Build form data
        var formData;
        var ajaxAction;

        if (isGuest) {
            // Guest submission - use FormData for file upload support
            formData = new FormData($form[0]);
            formData.append('action', 'dgptm_edugrant_submit');
            formData.append('nonce', dgptmEdugrant.nonce);
            formData.append('event_id', $form.data('event-id'));

            $.ajax({
                url: dgptmEdugrant.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: handleSubmitResponse,
                error: handleSubmitError
            });
        } else {
            // Logged-in user submission
            $.ajax({
                url: dgptmEdugrant.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_edugrant_submit',
                    nonce: dgptmEdugrant.nonce,
                    event_id: $form.data('event-id')
                },
                success: handleSubmitResponse,
                error: handleSubmitError
            });
        }

        function handleSubmitResponse(response) {
            if (response.success) {
                var successHtml = response.data.message;
                if (response.data.edugrant_number) {
                    successHtml += '<br><strong>EduGrant-Nummer: ' + response.data.edugrant_number + '</strong>';
                }
                $message.removeClass('error').addClass('success').html(successHtml).show();
                $form.hide();
            } else {
                $message.removeClass('success').addClass('error').text(response.data.message).show();
                $submitBtn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> EduGrant beantragen');
            }
        }

        function handleSubmitError() {
            $message.removeClass('success').addClass('error').text(dgptmEdugrant.i18n.error).show();
            $submitBtn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> EduGrant beantragen');
        }
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
