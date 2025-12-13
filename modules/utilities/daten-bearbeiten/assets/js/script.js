jQuery(document).ready(function($) {
    'use strict';

    const $form = $('#dgptm-daten-form');
    const $loadingMessage = $('#loading-message');
    const $successMessage = $('#success-message');
    const $submitBtn = $('#submit-btn');
    const $formMessages = $('#form-messages');

    let clinicsData = [];
    let selectedEmployer = {
        name: '',
        id: ''
    };

    /**
     * Load member data on page load
     */
    function loadMemberData() {
        console.log('Loading member data...');
        console.log('AJAX URL:', dgptmDatenBearbeiten.ajaxUrl);
        console.log('Nonce:', dgptmDatenBearbeiten.nonce);

        $.ajax({
            url: dgptmDatenBearbeiten.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_load_member_data',
                nonce: dgptmDatenBearbeiten.nonce
            },
            success: function(response) {
                console.log('Load response:', response);

                if (response.success && response.data) {
                    populateForm(response.data.data);
                    $loadingMessage.fadeOut(300, function() {
                        $form.fadeIn(300);
                    });
                } else {
                    var errorMsg = 'Fehler beim Laden der Daten';
                    if (response.data && response.data.message) {
                        errorMsg = response.data.message;
                    }
                    console.error('Error message:', errorMsg);
                    showErrorInPlace(errorMsg);
                    $loadingMessage.hide();
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Load error:', {
                    status: status,
                    error: error,
                    responseText: xhr.responseText
                });
                showErrorInPlace('Fehler beim Laden der Daten: ' + error);
                $loadingMessage.hide();
            }
        });
    }

    /**
     * Show error message in place of loading message
     */
    function showErrorInPlace(message) {
        $loadingMessage.html(
            '<div class="error-box">' +
            '<h3>Fehler</h3>' +
            '<p>' + message + '</p>' +
            '<p><small>Bitte kontaktieren Sie die Geschäftsstelle, wenn dieser Fehler weiterhin auftritt.</small></p>' +
            '</div>'
        );
    }

    /**
     * Search for accounts
     */
    function searchAccounts(searchTerm) {
        console.log('Searching accounts for:', searchTerm);

        if (!searchTerm || searchTerm.length < 2) {
            showError('Bitte geben Sie mindestens 2 Zeichen ein');
            return;
        }

        // Show loading indicator
        $('#employer_results').html('<div style="padding: 15px; text-align: center;">Suche läuft...</div>');
        $('#employer_results_row').show();

        $.ajax({
            url: dgptmDatenBearbeiten.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_load_accounts',
                nonce: dgptmDatenBearbeiten.nonce,
                search: searchTerm
            },
            success: function(response) {
                console.log('Search results:', response);
                if (response.success && response.data.accounts) {
                    displaySearchResults(response.data.accounts);
                } else {
                    $('#employer_results').html('<div style="padding: 15px; text-align: center; color: #666;">Keine Ergebnisse gefunden</div>');
                }
            },
            error: function(xhr, status, error) {
                console.error('Search failed:', error);
                $('#employer_results').html('<div style="padding: 15px; text-align: center; color: #d63638;">Fehler bei der Suche</div>');
            }
        });
    }

    /**
     * Display search results
     */
    function displaySearchResults(accounts) {
        const $results = $('#employer_results');
        $results.empty();

        if (accounts.length === 0) {
            $results.html('<div style="padding: 15px; text-align: center; color: #666;">Keine Ergebnisse gefunden</div>');
            return;
        }

        accounts.forEach(function(account) {
            const $item = $('<div class="employer-result-item" data-id="' + account.id + '" data-name="' + account.name + '" style="padding: 12px; border-bottom: 1px solid #eee; cursor: pointer; transition: background 0.2s;">' +
                '<strong>' + account.name + '</strong>' +
                (account.industry ? '<br><small style="color: #666;">' + account.industry + '</small>' : '') +
                '</div>');

            $item.on('mouseenter', function() {
                $(this).css('background', '#f0f0f0');
            }).on('mouseleave', function() {
                $(this).css('background', 'white');
            }).on('click', function() {
                selectEmployer(account.id, account.name);
            });

            $results.append($item);
        });
    }

    /**
     * Select an employer
     */
    function selectEmployer(id, name) {
        selectedEmployer.id = id;
        selectedEmployer.name = name;

        // Update hidden fields
        $('#employer').val(name);
        $('#employer_id').val(id);

        // Update display
        $('#employer_current_name').text(name);

        // Hide search section
        $('#employer_search_section').hide();
        $('#employer_current_row').show();

        // Clear search
        $('#employer_search').val('');
        $('#employer_results_row').hide();

        // Check if service provider
        checkIfServiceProvider(name);

        console.log('Selected employer:', name, '(ID:', id + ')');
    }

    /**
     * Load clinics from Zoho CRM
     */
    function loadClinics() {
        console.log('Loading clinics...');

        $.ajax({
            url: dgptmDatenBearbeiten.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_load_clinics',
                nonce: dgptmDatenBearbeiten.nonce
            },
            success: function(response) {
                console.log('Clinics loaded:', response);
                if (response.success && response.data.clinics) {
                    clinicsData = response.data.clinics;
                    populateClinicsDropdown();
                }
            },
            error: function(xhr, status, error) {
                console.error('Failed to load clinics:', error);
            }
        });
    }


    /**
     * Populate clinics dropdown
     */
    function populateClinicsDropdown() {
        const $temporaryWorkSelect = $('#temporary_work');
        $temporaryWorkSelect.empty();
        $temporaryWorkSelect.append('<option value="">Keine Ausleihe</option>');

        clinicsData.forEach(function(clinic) {
            $temporaryWorkSelect.append('<option value="' + clinic.name + '" data-id="' + clinic.id + '">' + clinic.name + '</option>');
        });
    }

    /**
     * Populate form with data
     */
    function populateForm(data) {
        console.log('Populating form with data:', data);

        // Text fields
        $('#ansprache').val(data.ansprache || 'Liebe(r)');
        $('#akad_titel').val(data.akad_titel || '');
        $('#titel_nach_name').val(data.titel_nach_name || '');

        // Read-only name field
        const fullName = (data.vorname || '') + ' ' + (data.nachname || '');
        $('#name').val(fullName.trim());

        // Email addresses
        $('#mail1').val(data.mail1 || '');
        $('#mail2').val(data.mail2 || '');
        $('#mail3').val(data.mail3 || '');

        // Address
        $('#strasse').val(data.strasse || '');
        $('#adresszusatz').val(data.adresszusatz || '');
        $('#plz').val(data.plz || '');
        $('#ort').val(data.ort || '');
        $('#land').val(data.land || 'Deutschland');

        // Phone numbers
        $('#telefon').val(data.telefon || '');
        $('#mobil').val(data.mobil || '');
        $('#diensttelefon').val(data.diensttelefon || '');

        // Employer - prioritize lookup, fallback to free text
        console.log('Employer data:', data.employer, 'ID:', data.employer_id, 'Free:', data.employer_free);

        let displayEmployer = '';

        if (data.employer) {
            // Lookup employer exists
            displayEmployer = data.employer;
            selectedEmployer.name = data.employer;
            selectedEmployer.id = data.employer_id || '';
            $('#employer').val(data.employer);
            $('#employer_id').val(data.employer_id || '');
        } else if (data.employer_free) {
            // Fallback to free text employer
            displayEmployer = data.employer_free;
            selectedEmployer.name = data.employer_free;
            selectedEmployer.id = '';
            $('#employer').val(data.employer_free);
            $('#employer_id').val('');
        }

        if (displayEmployer) {
            $('#employer_current_name').text(displayEmployer);
            console.log('Set employer display to:', displayEmployer);
            checkIfServiceProvider(displayEmployer);
        } else {
            console.log('No employer data, setting to "Nicht zugeordnet"');
            $('#employer_current_name').text('Nicht zugeordnet');
        }

        // Temporary work
        if (data.temporary_work) {
            $('#temporary_work').val(data.temporary_work);
        }

        // Journal preferences (checkboxes)
        $('#journal_post').prop('checked', data.journal_post === 'true' || data.journal_post === true);
        $('#journal_mail').prop('checked', data.journal_mail === 'true' || data.journal_mail === true);

        // DGPTM Account Hinweis anzeigen
        displayDgptmAccountNotice(data.dgptm_mail, data.hat_postfach);
    }

    /**
     * Display DGPTM Account notice if DGPTMMail ends with @dgptm.de
     */
    function displayDgptmAccountNotice(dgptmMail, hatPostfach) {
        const $notice = $('#dgptm-account-notice');
        const $text = $('#dgptm-account-text');

        // Prüfen ob DGPTMMail vorhanden und auf @dgptm.de endet
        if (!dgptmMail || !dgptmMail.toLowerCase().endsWith('@dgptm.de')) {
            $notice.hide();
            return;
        }

        let noticeText = '';

        // Boolean-Prüfung für hatPostfach
        const hasPostfach = (hatPostfach === true || hatPostfach === 'true' || hatPostfach === 1 || hatPostfach === '1');

        if (hasPostfach) {
            // Mit aktivem Postfach
            noticeText = 'Sie verfügen über einen DGPTM-Account auf <a href="https://office.com" target="_blank">https://office.com</a> mit der Adresse <strong>' + dgptmMail + '</strong> mit einem aktiven Postfach auf <a href="https://outlook.office.com" target="_blank">https://outlook.office.com</a>.';
        } else {
            // Ohne Postfach
            noticeText = 'Sie verfügen über einen DGPTM-Account auf <a href="https://office.com" target="_blank">office.com</a> mit der Adresse <strong>' + dgptmMail + '</strong>.';
        }

        $text.html(noticeText);
        $notice.show();

        console.log('DGPTM Account notice displayed:', dgptmMail, 'hatPostfach:', hasPostfach);
    }

    /**
     * Check if employer is a service provider (WKK or LifeSystems)
     */
    function checkIfServiceProvider(employerName) {
        const serviceProviders = ['WKK', 'LifeSystems'];
        const isServiceProvider = serviceProviders.some(function(provider) {
            return employerName && employerName.includes(provider);
        });

        if (isServiceProvider) {
            $('#temporary_work_row').show();
        } else {
            $('#temporary_work_row').hide();
            $('#temporary_work').val('');
        }
    }

    /**
     * Handle "Change" button click
     */
    $('#employer_change_btn').on('click', function() {
        $('#employer_current_row').hide();
        $('#employer_search_section').show();
        $('#employer_search').focus();
    });

    /**
     * Handle search button click
     */
    $('#employer_search_btn').on('click', function() {
        const searchTerm = $('#employer_search').val().trim();
        searchAccounts(searchTerm);
    });

    /**
     * Handle Enter key in search field
     */
    $('#employer_search').on('keypress', function(e) {
        if (e.which === 13) {
            e.preventDefault();
            const searchTerm = $(this).val().trim();
            searchAccounts(searchTerm);
        }
    });

    /**
     * Handle "not in list" checkbox
     */
    $('#employer_not_in_list').on('change', function() {
        if ($(this).is(':checked')) {
            $('#employer_manual_row').show();
            $('#employer_results_row').hide();
        } else {
            $('#employer_manual_row').hide();
            $('#employer_manual').val('');
        }
    });

    /**
     * Handle bank change request toggle
     */
    $('#bank_change_requested').on('change', function() {
        if ($(this).is(':checked')) {
            $('#bank_change_section').slideDown();
            // Felder mit Mitgliedsdaten vorausfüllen
            var fullName = $('#name').val();
            if (fullName) {
                var nameParts = fullName.split(' ');
                $('#bank_vorname').val(nameParts[0] || '');
                $('#bank_nachname').val(nameParts.slice(1).join(' ') || '');
            }
        } else {
            $('#bank_change_section').slideUp();
            // Felder leeren
            $('#bank_vorname').val('');
            $('#bank_nachname').val('');
            $('#bank_iban').val('');
        }
    });

    /**
     * IBAN validation and formatting
     */
    $('#bank_iban').on('input', function() {
        var iban = $(this).val().toUpperCase().replace(/\s/g, '');
        // Format IBAN with spaces every 4 characters
        var formatted = iban.match(/.{1,4}/g);
        if (formatted) {
            $(this).val(formatted.join(' '));
        }
    });

    /**
     * Validate IBAN format (basic check)
     */
    function validateIBAN(iban) {
        // Remove spaces
        iban = iban.replace(/\s/g, '');
        // Basic format check: 2 letters, 2 digits, then alphanumeric
        var ibanRegex = /^[A-Z]{2}[0-9]{2}[A-Z0-9]{9,30}$/;
        return ibanRegex.test(iban);
    }

    /**
     * Handle form submission
     */
    $form.on('submit', function(e) {
        e.preventDefault();

        console.log('Form submitted');

        // Validate bank change if requested
        if ($('#bank_change_requested').is(':checked')) {
            var bankVorname = $('#bank_vorname').val().trim();
            var bankNachname = $('#bank_nachname').val().trim();
            var bankIBAN = $('#bank_iban').val().trim();

            if (!bankVorname || !bankNachname || !bankIBAN) {
                showError('Bitte füllen Sie alle Bankdaten-Felder aus.');
                return;
            }

            if (!validateIBAN(bankIBAN)) {
                showError('Bitte geben Sie eine gültige IBAN ein.');
                return;
            }
        }

        // Disable submit button
        $submitBtn.prop('disabled', true).text(dgptmDatenBearbeiten.strings.saving);
        $formMessages.empty();

        // Determine employer value and whether it's manual
        let employerValue = '';
        let employerId = '';
        let isManualEmployer = false;

        if ($('#employer_not_in_list').is(':checked')) {
            // Manual entry
            employerValue = $('#employer_manual').val();
            isManualEmployer = true;
        } else {
            // From selection or hidden field
            employerValue = $('#employer').val();
            employerId = $('#employer_id').val();
            isManualEmployer = false;
        }

        // Prepare form data
        const formData = {
            action: 'dgptm_update_member_data',
            nonce: dgptmDatenBearbeiten.nonce,

            ansprache: $('#ansprache').val(),
            akad_titel: $('#akad_titel').val(),
            titel_nach_name: $('#titel_nach_name').val(),

            mail1: $('#mail1').val(),
            mail2: $('#mail2').val(),
            mail3: $('#mail3').val(),

            strasse: $('#strasse').val(),
            adresszusatz: $('#adresszusatz').val(),
            plz: $('#plz').val(),
            ort: $('#ort').val(),
            land: $('#land').val(),

            telefon: $('#telefon').val(),
            mobil: $('#mobil').val(),
            diensttelefon: $('#diensttelefon').val(),

            employer: employerValue,
            employer_id: employerId,
            is_manual_employer: isManualEmployer ? 'true' : 'false',
            temporary_work: $('#temporary_work').val(),

            journal_post: $('#journal_post').is(':checked') ? 'true' : 'false',
            journal_mail: $('#journal_mail').is(':checked') ? 'true' : 'false',

            // Bank change data
            bank_change_requested: $('#bank_change_requested').is(':checked') ? 'true' : 'false',
            bank_vorname: $('#bank_vorname').val(),
            bank_nachname: $('#bank_nachname').val(),
            bank_iban: $('#bank_iban').val().replace(/\s/g, '') // Remove spaces from IBAN
        };

        console.log('Sending data:', formData);

        $.ajax({
            url: dgptmDatenBearbeiten.ajaxUrl,
            type: 'POST',
            data: formData,
            success: function(response) {
                console.log('Update response:', response);

                if (response.success) {
                    // Show success message
                    $form.fadeOut(300, function() {
                        $successMessage.fadeIn(300);
                    });

                    // Close Elementor accordion before redirect
                    var $accordion = $('#datenakkordeon');
                    if ($accordion.length) {
                        console.log('Closing Elementor accordion');
                        // Find and click the active accordion title to close it
                        $accordion.find('.elementor-tab-title.elementor-active').trigger('click');
                    }

                    // Redirect after 2 seconds
                    setTimeout(function() {
                        window.location.href = dgptmDatenBearbeiten.redirectUrl;
                    }, 2000);
                } else {
                    showError(response.data.message || dgptmDatenBearbeiten.strings.error);
                    $submitBtn.prop('disabled', false).text('Daten ändern');
                }
            },
            error: function(xhr, status, error) {
                console.error('Update error:', error);
                showError(dgptmDatenBearbeiten.strings.error + ': ' + error);
                $submitBtn.prop('disabled', false).text('Daten ändern');
            }
        });
    });

    /**
     * Show error message
     */
    function showError(message) {
        $formMessages.html('<div class="error-message">' + message + '</div>').fadeIn();
    }

    // Load data on page load
    loadClinics();
    loadMemberData();

    // ============================================
    // Student Status Form Functionality
    // ============================================

    const $studentModal = $('#dgptm-student-modal');
    const $studentStatusDisplay = $('#dgptm-student-status-display');
    const $studentUploadForm = $('#dgptm-student-upload-form');
    const $uploadResponse = $('#dgptm-upload-response');

    /**
     * Load student status from CRM
     */
    function loadStudentStatus() {
        if (!$studentStatusDisplay.length) {
            return; // Not on student status page
        }

        console.log('Loading student status...');

        $.ajax({
            url: dgptmDatenBearbeiten.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_load_student_status',
                nonce: dgptmDatenBearbeiten.nonce
            },
            success: function(response) {
                console.log('Student status response:', response);

                if (response.success && response.data) {
                    displayStudentStatus(response.data.data);
                } else {
                    // Bei Fehler: Display ausblenden statt Fehlermeldung anzeigen
                    $studentStatusDisplay.hide();
                    console.log('Error loading status - hiding display:', response.data.message);
                }
            },
            error: function(xhr, status, error) {
                // Bei Netzwerkfehler: Display ausblenden statt Fehlermeldung anzeigen
                console.error('Failed to load student status:', error);
                $studentStatusDisplay.hide();
            }
        });
    }

    /**
     * Display student status
     */
    function displayStudentStatus(data) {
        // Prüfe ob Student_Status = true ist
        const statusDisplay = data.student_status_display || 'inactive';

        // WICHTIG: Nur anzeigen, wenn Student_Status aktiv oder in Prüfung ist
        // Bei 'inactive' ausblenden, auch wenn alte Bescheinigung/Jahr vorhanden ist
        if (statusDisplay === 'inactive') {
            // Student_Status = false - Display-Bereich komplett ausblenden
            $studentStatusDisplay.hide();
            console.log('Student status is INACTIVE - hiding status display (Student_Status = false)');
            return;
        }

        // Status ist aktiv oder in Prüfung - Display-Bereich anzeigen
        $studentStatusDisplay.show();
        console.log('Student status is ACTIVE or IN_REVIEW - showing status display');

        let statusHtml = '<div class="status-content">';

        // Check if we're in Q4 (last quarter)
        const now = new Date();
        const currentMonth = now.getMonth() + 1; // 1-12
        const currentYear = now.getFullYear();
        const isLastQuarter = (currentMonth >= 10 && currentMonth <= 12); // October, November, December

        // Check if status is about to expire (valid_through equals current year and we're in Q4)
        const validYear = parseInt(data.valid_through);
        const isExpiringSoon = (validYear === currentYear && isLastQuarter);

        // Student Status - use statusDisplay from above (already declared)
        if (statusDisplay === 'active') {
            const statusClass = isExpiringSoon ? 'status-active status-expiring' : 'status-active';
            statusHtml += '<div class="status-item ' + statusClass + '">';
            statusHtml += '<strong>Studierendenstatus:</strong> <span class="status-badge active' + (isExpiringSoon ? ' expiring' : '') + '">Aktiv' + (isExpiringSoon ? ' (läuft ab)' : '') + '</span>';
            statusHtml += '</div>';
        } else if (statusDisplay === 'in_review') {
            statusHtml += '<div class="status-item status-in-review">';
            statusHtml += '<strong>Studierendenstatus:</strong> <span class="status-badge in-review">In Prüfung</span>';
            statusHtml += '</div>';
        } else {
            statusHtml += '<div class="status-item status-inactive">';
            statusHtml += '<strong>Studierendenstatus:</strong> <span class="status-badge inactive">Nicht aktiv</span>';
            statusHtml += '</div>';
        }

        // Valid Through - with red highlighting in Q4
        statusHtml += '<div class="status-item' + (isExpiringSoon ? ' status-expiring' : '') + '">';
        statusHtml += '<strong>Gültig bis Beitragsjahr:</strong> <span class="' + (isExpiringSoon ? 'text-expiring' : '') + '">' + data.valid_through + '</span>';
        statusHtml += '</div>';

        // Certificate Status
        if (data.has_certificate) {
            statusHtml += '<div class="status-item">';
            statusHtml += '<strong>Studienbescheinigung:</strong> <span class="status-badge uploaded">Hochgeladen</span>';
            statusHtml += '</div>';
        } else {
            statusHtml += '<div class="status-item">';
            statusHtml += '<strong>Studienbescheinigung:</strong> <span class="status-badge not-uploaded">Nicht vorhanden</span>';
            statusHtml += '</div>';
        }

        // Informational text about deadline
        statusHtml += '<div class="status-info-text">';
        statusHtml += '<p><small><strong>Hinweis:</strong> Stichtag ist satzungsgemäß der 1.3. des laufenden Jahres. Studienbescheinigungen, die bis dahin nicht eingereicht wurden, werden nicht auf den Jahresbeitrag angerechnet. Davon ausgenommen sind Neumitglieder.</small></p>';
        statusHtml += '</div>';

        statusHtml += '</div>';

        $studentStatusDisplay.html(statusHtml);
    }

    /**
     * Open student modal
     */
    $(document).on('click', '#dgptm-student-open-modal', function() {
        $studentModal.fadeIn(300);
        $uploadResponse.empty();
        $studentUploadForm[0].reset();
        initializeYearInput(); // Setze min/max basierend auf aktuellem Quartal
    });

    /**
     * Close student modal
     */
    $(document).on('click', '.dgptm-close-modal', function() {
        $studentModal.fadeOut(300);
    });

    // Close modal when clicking outside
    $(document).on('click', function(e) {
        if ($(e.target).is('#dgptm-student-modal')) {
            $studentModal.fadeOut(300);
        }
    });

    /**
     * Initialize year input with dynamic min/max based on quarter
     */
    function initializeYearInput() {
        const $yearInput = $('#dgptm-valid-year');
        if (!$yearInput.length) {
            return;
        }

        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = now.getMonth() + 1; // 1-12
        const isQ4 = (currentMonth >= 10); // Oktober, November, Dezember

        let minYear, maxYear, placeholder;

        if (isQ4) {
            // Q4: Nur Folgejahr oder später (max. 2 Jahre im Voraus)
            minYear = currentYear + 1;
            maxYear = currentYear + 3;
            placeholder = 'z.B. ' + minYear;
        } else {
            // Q1-Q3: Aktuelles Jahr bis max. 2 Jahre im Voraus
            minYear = currentYear;
            maxYear = currentYear + 2;
            placeholder = 'z.B. ' + currentYear;
        }

        $yearInput.attr('min', minYear);
        $yearInput.attr('max', maxYear);
        $yearInput.attr('placeholder', placeholder);

        console.log('Year input initialized. Q4:', isQ4, 'Range:', minYear, '-', maxYear);
    }

    /**
     * Handle student certificate upload
     */
    $studentUploadForm.on('submit', function(e) {
        e.preventDefault();

        console.log('Uploading student certificate...');

        const year = parseInt($('#dgptm-valid-year').val());
        const fileInput = document.getElementById('dgptm-certificate-file');
        const file = fileInput.files[0];

        if (!year || !file) {
            showUploadMessage('Bitte füllen Sie alle Felder aus.', 'error');
            return;
        }

        // Validate year with Q4 logic
        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = now.getMonth() + 1;
        const isQ4 = (currentMonth >= 10);

        let minYear, maxYear;

        if (isQ4) {
            minYear = currentYear + 1;
            maxYear = currentYear + 3;

            if (year < minYear) {
                showUploadMessage('Ab dem 4. Quartal (Oktober-Dezember) können nur Bescheinigungen für das Folgejahr oder später eingereicht werden. Frühestes Jahr: ' + minYear, 'error');
                return;
            }
            if (year > maxYear) {
                showUploadMessage('Das Jahr darf maximal 2 Jahre nach dem Folgejahr liegen. Spätestes Jahr: ' + maxYear, 'error');
                return;
            }
        } else {
            minYear = currentYear;
            maxYear = currentYear + 2;

            if (year < minYear) {
                showUploadMessage('Das Jahr darf nicht in der Vergangenheit liegen. Frühestes Jahr: ' + minYear, 'error');
                return;
            }
            if (year > maxYear) {
                showUploadMessage('Das Jahr darf maximal 2 Jahre in der Zukunft liegen. Spätestes Jahr: ' + maxYear, 'error');
                return;
            }
        }

        // Validate file size (2MB)
        if (file.size > 2 * 1024 * 1024) {
            showUploadMessage('Die Datei darf maximal 2MB groß sein.', 'error');
            return;
        }

        // Disable submit button
        const $submitBtn = $('#dgptm-upload-btn');
        $submitBtn.prop('disabled', true).text('Wird hochgeladen...');
        showUploadMessage('Datei wird hochgeladen...', 'info');

        // Prepare form data
        const formData = new FormData();
        formData.append('action', 'dgptm_upload_student_certificate');
        formData.append('nonce', dgptmDatenBearbeiten.nonce);
        formData.append('year', year);
        formData.append('certificate_file', file);

        // Upload via AJAX
        $.ajax({
            url: dgptmDatenBearbeiten.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                console.log('Upload response:', response);

                if (response.success) {
                    showUploadMessage(response.data.message || 'Erfolgreich hochgeladen!', 'success');

                    // Reset form
                    $studentUploadForm[0].reset();

                    // Reload status
                    setTimeout(function() {
                        loadStudentStatus();
                        $studentModal.fadeOut(300);
                    }, 2000);
                } else {
                    showUploadMessage(response.data.message || 'Fehler beim Hochladen', 'error');
                }

                $submitBtn.prop('disabled', false).text('Hochladen & Absenden');
            },
            error: function(xhr, status, error) {
                console.error('Upload error:', error);
                showUploadMessage('Fehler beim Hochladen: ' + error, 'error');
                $submitBtn.prop('disabled', false).text('Hochladen & Absenden');
            }
        });
    });

    /**
     * Show upload message
     */
    function showUploadMessage(message, type) {
        let className = 'upload-message';
        if (type === 'error') className += ' error';
        if (type === 'success') className += ' success';
        if (type === 'info') className += ' info';

        $uploadResponse.html('<div class="' + className + '">' + message + '</div>').fadeIn();
    }

    // Load student status on page load (if element exists)
    loadStudentStatus();

    // ============================================
    // Student Status Banner - Open Accordion
    // ============================================

    /**
     * Open Elementor accordion when banner button is clicked
     */
    $(document).on('click', '#dgptm-open-studistatus-accordion', function() {
        console.log('Opening student status accordion...');

        // Try multiple selectors to find the accordion
        let $accordionTitle = null;
        let $scrollTarget = null;
        let isNewElementor = false;

        // Method 1: Direct search for element with CSS ID
        let $target = $('#studistatusakkordeon');
        if ($target.length > 0) {
            console.log('Found element by ID: studistatusakkordeon');
            console.log('Element classes:', $target.attr('class'));
            console.log('Element HTML tag:', $target.prop('tagName'));
            $scrollTarget = $target;

            // Check if this is the new Elementor structure (HTML5 details/summary)
            if ($target.is('details') || $target.hasClass('e-n-accordion-item')) {
                console.log('Detected new Elementor accordion (HTML5 details/summary)');
                isNewElementor = true;
                $accordionTitle = $target.find('summary').first();

                if ($accordionTitle.length === 0 && $target.is('summary')) {
                    console.log('ID is on summary itself');
                    $accordionTitle = $target;
                    $scrollTarget = $target.closest('details');
                }
            } else {
                // Old Elementor structure
                console.log('Detected old Elementor accordion structure');
                // Look for accordion title inside
                $accordionTitle = $target.find('.elementor-tab-title').first();
                console.log('Found tab titles inside:', $target.find('.elementor-tab-title').length);

                if ($accordionTitle.length === 0) {
                    // Maybe the ID is on the accordion item itself
                    if ($target.hasClass('elementor-tab-title')) {
                        console.log('ID is on tab-title itself');
                        $accordionTitle = $target;
                    } else if ($target.hasClass('elementor-accordion-item')) {
                        console.log('ID is on accordion-item');
                        $accordionTitle = $target.find('.elementor-tab-title').first();
                    } else {
                        // Try to find parent accordion widget and search inside
                        console.log('Searching for parent accordion widget...');
                        const $parentAccordion = $target.closest('.elementor-widget-accordion');
                        if ($parentAccordion.length > 0) {
                            console.log('Found parent accordion widget');
                            $accordionTitle = $parentAccordion.find('.elementor-tab-title').first();
                        }
                    }
                }
            }
        }

        // Method 2: Search in widget with settings ID
        if ($accordionTitle === null || $accordionTitle.length === 0) {
            console.log('Trying alternate search methods...');
            // Try finding widget container with ID and then finding accordion inside
            const $widget = $('.elementor-element[id="studistatusakkordeon"]');
            if ($widget.length > 0) {
                console.log('Found widget container with element class');
                $scrollTarget = $widget;
                $accordionTitle = $widget.find('.elementor-tab-title').first();
            }
        }

        // Method 3: Search by data-id attribute
        if ($accordionTitle === null || $accordionTitle.length === 0) {
            const $dataId = $('[data-id="studistatusakkordeon"]');
            if ($dataId.length > 0) {
                console.log('Found by data-id attribute');
                $scrollTarget = $dataId;
                $accordionTitle = $dataId.find('.elementor-tab-title').first();
            }
        }

        // Method 4: Search by accordion title text content
        if ($accordionTitle === null || $accordionTitle.length === 0) {
            console.log('Trying to find by title text: Bescheinigung_Studierendenstatus');
            $('.elementor-tab-title').each(function() {
                const titleText = $(this).text().trim();
                console.log('Checking title:', titleText);
                if (titleText.includes('Bescheinigung_Studierendenstatus') ||
                    titleText.includes('Studierendenstatus') ||
                    titleText.includes('Bescheinigung')) {
                    console.log('Found matching title by text!');
                    $accordionTitle = $(this);
                    $scrollTarget = $(this).closest('.elementor-widget-accordion');
                    return false; // break loop
                }
            });
        }

        if ($accordionTitle === null || $accordionTitle.length === 0) {
            console.error('Could not find accordion with ID "studistatusakkordeon"');
            console.log('Please ensure the Elementor accordion or accordion item has CSS ID: studistatusakkordeon');
            console.log('Alternatively, ensure an accordion title contains "Bescheinigung_Studierendenstatus"');
            return;
        }

        console.log('Found accordion title, scrolling and opening...');
        console.log('Title text:', $accordionTitle.text());

        // Scroll to accordion
        if ($scrollTarget && $scrollTarget.length > 0) {
            $('html, body').animate({
                scrollTop: $scrollTarget.offset().top - 100
            }, 500);
        }

        // Open accordion based on Elementor version
        if (isNewElementor) {
            console.log('Using new Elementor method (details/summary)');
            const detailsElement = $scrollTarget.is('details') ? $scrollTarget[0] : $scrollTarget.closest('details')[0];

            if (detailsElement) {
                // Check if already open
                if (!detailsElement.open) {
                    setTimeout(function() {
                        // Open the details element
                        detailsElement.open = true;
                        console.log('Accordion opened (new Elementor)');
                    }, 600);
                } else {
                    console.log('Accordion already open (new Elementor)');
                }
            }
        } else {
            console.log('Using old Elementor method (click)');
            // Check if accordion is already open
            if (!$accordionTitle.hasClass('elementor-active')) {
                // Click to open after scroll
                setTimeout(function() {
                    $accordionTitle.trigger('click');
                    console.log('Accordion opened (old Elementor)');
                }, 600);
            } else {
                console.log('Accordion already open (old Elementor)');
            }
        }
    });
});
