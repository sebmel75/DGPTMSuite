/**
 * DGPTM Mitgliedsantrag - Frontend JavaScript
 * Handles form validation, multi-step navigation, guarantor verification, and submission
 */

(function($) {
    'use strict';

    let currentStep = 1;
    const totalSteps = 5;
    let guarantor1Valid = false;
    let guarantor2Valid = false;
    let emailValidationTimers = {};

    $(document).ready(function() {
        initializeForm();
        setupNavigation();
        setupGuarantorVerification();
        setupEmailValidation();
        setupAddressValidation();
        setupStudentToggle();
        setupFormSubmission();
    });

    function initializeForm() {
        // Show first step
        showStep(1);

        // Prevent Enter key from submitting form
        $('#dgptm-mitgliedsantrag-form').on('keypress', function(e) {
            if (e.which === 13 && e.target.type !== 'textarea') {
                e.preventDefault();
                return false;
            }
        });

        // Setup membership type change handler
        setupMembershipTypeHandler();

        // Setup qualification handlers
        setupQualificationHandlers();
    }

    /**
     * Bestimmt die Anzahl erforderlicher Bürgen basierend auf Mitgliedsart, Student-Status und Qualifikation
     * @returns {object} { required: number (0-2), optional: boolean, showFields: number (1-2) }
     */
    function getGuarantorRequirements() {
        const mitgliedsart = $('#mitgliedsart').val();
        const istStudent = $('#ist_student').is(':checked');
        const hatQualifikation = $('input[name="hat_qualifikation"]:checked').val();

        // Förderndes Mitglied: keine Bürgen, keine Anzeige
        if (mitgliedsart === 'förderndes') {
            return { required: 0, optional: false, showFields: 0, hideSection: true };
        }

        // Außerordentliches Mitglied: keine Bürgen, keine Anzeige
        if (mitgliedsart === 'außerordentliches') {
            return { required: 0, optional: false, showFields: 0, hideSection: true };
        }

        // Ordentliches Mitglied
        if (mitgliedsart === 'ordentliches') {
            // Mit anerkannter Qualifikation: Bürgen optional als Fallback
            if (hatQualifikation === 'ja') {
                return { required: 0, optional: true, showFields: 2 };
            }

            // Student: 2 Felder anzeigen, aber nur 1 Bürge erforderlich
            if (istStudent) {
                return { required: 1, optional: true, showFields: 2 };
            }

            // Ohne Qualifikation, nicht Student: 2 Bürgen
            if (hatQualifikation === 'nein') {
                return { required: 2, optional: false, showFields: 2 };
            }

            // Falls Qualifikation noch nicht angegeben wurde, erstmal 2 annehmen
            return { required: 2, optional: false, showFields: 2 };
        }

        // Default: keine Bürgen
        return { required: 0, optional: false, showFields: 0 };
    }

    /**
     * Passt die Bürgen-Sektion basierend auf den Anforderungen an
     */
    function updateGuarantorSection() {
        const requirements = getGuarantorRequirements();

        const $guarantor1Section = $('#guarantor1-section');
        const $guarantor2Section = $('#guarantor2-section');
        const $guarantor1Input = $('#buerge1_input');
        const $guarantor2Input = $('#buerge2_input');
        const $introText = $('#guarantor-intro-text');

        // Update progress bar - hide/show guarantor step
        const $guarantorProgressStep = $('.progress-step[data-step="4"]');
        if (requirements.hideSection) {
            $guarantorProgressStep.hide();
        } else {
            $guarantorProgressStep.show();
        }

        // Reset required attribute
        $guarantor1Input.prop('required', false);
        $guarantor2Input.prop('required', false);

        if (requirements.showFields === 0) {
            // Keine Bürgen möglich (förderndes/außerordentliches Mitglied)
            $guarantor1Section.hide();
            $guarantor2Section.hide();

            // Wenn hideSection true ist, komplett verstecken (für förderndes/außerordentliches)
            if (requirements.hideSection) {
                $introText.html('');
            } else {
                // Fallback für andere Fälle
                $introText.html('<div class="info-box-optional"><p><strong>Keine Bürgen erforderlich</strong></p><p>Gemäß Satzung sind keine Bürgen oder Qualifikationsnachweise erforderlich.</p></div>');
            }
        } else if (requirements.showFields === 2 && requirements.optional && requirements.required === 0) {
            // Ordentliche Mitglieder mit Qualifikation: Bürgen optional als Fallback
            $guarantor1Section.show();
            $guarantor2Section.show();
            $guarantor1Input.prop('required', false);
            $guarantor2Input.prop('required', false);
            $introText.html('<div class="info-box-optional"><p><strong>Optionale Angabe von Bürgen</strong></p><p>Um im Fall der Ablehnung Ihres Qualifikationsnachweises trotzdem als Ordentliches Mitglied aufgenommen werden zu können, haben Sie hier die Möglichkeit, potentielle Bürgen anzugeben. Die Angabe ist freiwillig.</p><p class="small-info">Bürgen müssen bereits ordentliches, außerordentliches oder korrespondierendes Mitglied der DGPTM sein.</p></div>');
        } else if (requirements.showFields === 2 && requirements.optional && requirements.required === 1) {
            // Studentische ordentliche Mitglieder: 2 Felder, 1 Pflicht
            $guarantor1Section.show();
            $guarantor2Section.show();
            $guarantor1Input.prop('required', false);
            $guarantor2Input.prop('required', false);
            $introText.html('<div class="info-box"><p>Als studentisches ordentliches Mitglied benötigen Sie einen Bürgen, der bereits ordentliches, außerordentliches oder korrespondierendes Mitglied der DGPTM ist. Bitte geben Sie zwei Mitglieder an, von denen ein Mitglied für Sie bürgen muss.</p></div>');
        } else {
            // 2 Bürgen erforderlich (normale Mitglieder ohne Qualifikation)
            $guarantor1Section.show();
            $guarantor2Section.show();
            $guarantor1Input.prop('required', true);
            $guarantor2Input.prop('required', true);
            $introText.html('<p>Bitte geben Sie <strong>zwei Bürgen</strong> an, die bereits ordentliche, außerordentliche oder korrespondierende Mitglieder der DGPTM sind.</p>');
        }
    }

    /**
     * Handler für Änderungen der Mitgliedsart
     */
    function setupMembershipTypeHandler() {
        $('#mitgliedsart').on('change', function() {
            const mitgliedsart = $(this).val();

            // Reset alle bedingten Sektionen
            $('#qualification-section').hide();

            if (mitgliedsart === 'ordentliches') {
                $('#qualification-section').show();
            }

            // Update guarantor requirements
            updateGuarantorSection();
        });
    }

    /**
     * Handler für Qualifikations-Auswahl
     */
    function setupQualificationHandlers() {
        $('input[name="hat_qualifikation"]').on('change', function() {
            const value = $(this).val();
            const $uploadSection = $('#qualification-upload');

            // Reset required attributes
            $('#qualifikation_typ').prop('required', false);
            $('#qualifikation_nachweis').prop('required', false);

            if (value === 'ja') {
                $uploadSection.show();
                $('#qualifikation_typ').prop('required', true);
                $('#qualifikation_nachweis').prop('required', true);
            } else if (value === 'nein') {
                $uploadSection.hide();
            }

            // Update guarantor requirements
            updateGuarantorSection();
        });
    }

    function setupNavigation() {
        // Next button
        $('.btn-next').on('click', async function() {
            const isValid = await validateStep(currentStep);
            if (isValid) {
                goToStep(currentStep + 1);
            }
        });

        // Previous button
        $('.btn-prev').on('click', function() {
            goToStep(currentStep - 1);
        });
    }

    function goToStep(step) {
        if (step < 1 || step > totalSteps) {
            return;
        }

        // Handle conditional step 3 (student certificate)
        const isStudent = $('#ist_student').is(':checked');

        if (step === 3 && !isStudent) {
            // Skip step 3 if not a student
            if (currentStep < 3) {
                step = 4; // Going forward, skip to step 4
            } else {
                step = 2; // Going backward, go to step 2
            }
        }

        // Handle conditional step 4 (guarantors)
        const requirements = getGuarantorRequirements();

        if (step === 4 && requirements.hideSection) {
            // Skip step 4 if no guarantors needed (förderndes/außerordentliches)
            if (currentStep < 4) {
                step = 5; // Going forward, skip to step 5
            } else {
                // Going backward, check if we need to skip step 3 too
                step = isStudent ? 3 : 2;
            }
        }

        currentStep = step;
        showStep(step);
    }

    function showStep(step) {
        // Hide all steps
        $('.form-step').removeClass('active');

        // Show current step
        $('.form-step[data-step="' + step + '"]').addClass('active');

        // Update progress bar
        $('.progress-step').removeClass('active completed');
        $('.progress-step').each(function() {
            const stepNum = parseInt($(this).data('step'));
            if (stepNum < step) {
                $(this).addClass('completed');
            } else if (stepNum === step) {
                $(this).addClass('active');
            }
        });

        // Scroll to top
        $('html, body').animate({
            scrollTop: $('.dgptm-mitgliedsantrag-wrapper').offset().top - 100
        }, 300);
    }

    async function validateStep(step) {
        let isValid = true;
        const $step = $('.form-step[data-step="' + step + '"]');

        // Clear previous errors
        $step.find('.form-group').removeClass('error');
        $step.find('.error-message').remove();

        switch(step) {
            case 1:
                // Validate basic data
                isValid = validateRequiredFields($step);

                // Additional validation for ordentliches Mitglied qualification
                if (isValid) {
                    const mitgliedsart = $('#mitgliedsart').val();
                    if (mitgliedsart === 'ordentliches') {
                        const hatQualifikation = $('input[name="hat_qualifikation"]:checked').val();

                        if (!hatQualifikation) {
                            showError($('input[name="hat_qualifikation"]').closest('.form-group'),
                                'Bitte geben Sie an, ob Sie eine anerkannte Qualifikation besitzen.');
                            isValid = false;
                        } else if (hatQualifikation === 'ja') {
                            // Check qualification upload
                            if (!$('#qualifikation_typ').val()) {
                                showError($('#qualifikation_typ').closest('.form-group'),
                                    'Bitte wählen Sie die Art Ihrer Qualifikation.');
                                isValid = false;
                            }

                            const qualFileInput = $('#qualifikation_nachweis')[0];
                            if (!qualFileInput.files || qualFileInput.files.length === 0) {
                                showError($('#qualifikation_nachweis').closest('.form-group'),
                                    'Bitte laden Sie einen Nachweis Ihrer Qualifikation hoch.');
                                isValid = false;
                            } else {
                                // Validate file type
                                const file = qualFileInput.files[0];
                                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
                                if (!allowedTypes.includes(file.type)) {
                                    showError($('#qualifikation_nachweis').closest('.form-group'),
                                        'Nur JPG, PNG oder PDF Dateien erlaubt.');
                                    isValid = false;
                                }
                                // Validate file size (5MB max)
                                if (file.size > 5 * 1024 * 1024) {
                                    showError($('#qualifikation_nachweis').closest('.form-group'),
                                        'Datei zu groß (max. 5 MB).');
                                    isValid = false;
                                }
                            }
                        }
                    }
                }
                break;

            case 2:
                // Validate address and emails
                isValid = validateRequiredFields($step);
                if (isValid) {
                    isValid = validateAddressFields($step);
                }

                // WICHTIG: Google Maps Adressvalidierung erzwingen
                if (isValid) {
                    const $nextBtn = $step.find('.btn-next');
                    const originalText = $nextBtn.text();

                    // Show loading indicator for address
                    $nextBtn.prop('disabled', true).text('Adresse wird geprüft...');

                    const addressValidation = await validateAddressWithGoogleSync();

                    if (!addressValidation.valid) {
                        $nextBtn.prop('disabled', false).text(originalText);
                        showError($('#stadt').closest('.form-group'), addressValidation.message);
                        isValid = false;
                    }
                }

                // Check all emails against CRM for existing members
                if (isValid) {
                    const $nextBtn = $step.find('.btn-next');
                    const originalText = $nextBtn.text();

                    $nextBtn.prop('disabled', true).text('E-Mail-Adressen werden geprüft...');

                    const emailCheckResult = await checkEmailsInCRM();

                    $nextBtn.prop('disabled', false).text(originalText);

                    if (!emailCheckResult.valid) {
                        showError($(emailCheckResult.field).closest('.form-group'), emailCheckResult.message);
                        isValid = false;
                    }
                }
                break;

            case 3:
                // Validate student certificate if student
                const isStudent = $('#ist_student').is(':checked');
                if (isStudent) {
                    isValid = validateStudentFields($step);
                }
                break;

            case 4:
                // Validate guarantors
                isValid = validateGuarantors();
                break;

            case 5:
                // Validate Satzung acceptance
                if (!$('#satzung_akzeptiert').is(':checked')) {
                    showError($('#satzung_akzeptiert').closest('.form-group'), 'Bitte bestätigen Sie die Anerkennung der Satzung.');
                    isValid = false;
                }

                // Validate Beitrag acceptance
                if (!$('#beitrag_akzeptiert').is(':checked')) {
                    showError($('#beitrag_akzeptiert').closest('.form-group'), 'Bitte bestätigen Sie die Kenntnisnahme der Beitragspflicht.');
                    isValid = false;
                }

                // Validate DSGVO acceptance
                if (!$('#dsgvo_akzeptiert').is(':checked')) {
                    showError($('#dsgvo_akzeptiert').closest('.form-group'), 'Bitte stimmen Sie der Datenschutzerklärung zu.');
                    isValid = false;
                }
                break;
        }

        return isValid;
    }

    function validateRequiredFields($container) {
        let isValid = true;

        $container.find('input[required], select[required]').each(function() {
            const $field = $(this);
            const $group = $field.closest('.form-group');

            if (!$field.val() || $field.val().trim() === '') {
                showError($group, 'Dieses Feld ist erforderlich.');
                isValid = false;
            }
        });

        return isValid;
    }

    function validateAddressFields($container) {
        let isValid = true;

        // Validate ZIP code format
        const zip = $('#plz').val();
        if (zip && !/^\d{5}$/.test(zip)) {
            showError($('#plz').closest('.form-group'), 'Ungültige Postleitzahl (Format: 12345)');
            isValid = false;
        }

        // Validate that at least email1 is provided
        const email1 = $('#email1').val().trim();
        if (!email1) {
            showError($('#email1').closest('.form-group'), 'Die erste E-Mail-Adresse ist erforderlich');
            isValid = false;
        }

        // Collect all email addresses
        const emails = [];
        const emailFields = ['#email1', '#email2', '#email3'];

        emailFields.forEach(function(fieldId) {
            const email = $(fieldId).val().trim();
            if (email) {
                // Check format
                if (!isValidEmail(email)) {
                    showError($(fieldId).closest('.form-group'), 'Ungültige E-Mail-Adresse');
                    isValid = false;
                } else {
                    // Check for duplicates
                    if (emails.includes(email.toLowerCase())) {
                        showError($(fieldId).closest('.form-group'), 'Diese E-Mail-Adresse wurde bereits angegeben');
                        isValid = false;
                    } else {
                        emails.push(email.toLowerCase());
                    }
                }
            }
        });

        return isValid;
    }

    async function validateAddressWithGoogleSync() {
        const street = $('#strasse').val().trim();
        const zip = $('#plz').val().trim();
        const city = $('#stadt').val().trim();
        const country = $('#land').val() || 'Deutschland';

        if (!street || !zip || !city) {
            return { valid: false, message: 'Bitte füllen Sie alle Adressfelder aus' };
        }

        return new Promise((resolve) => {
            $.ajax({
                url: dgptmMitgliedsantrag.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_validate_address',
                    nonce: dgptmMitgliedsantrag.nonce,
                    street: street,
                    zip: zip,
                    city: city,
                    country: country
                },
                success: function(response) {
                    if (response.success) {
                        resolve({ valid: true });
                    } else {
                        resolve({
                            valid: false,
                            message: response.data.message || 'Adressvalidierung fehlgeschlagen'
                        });
                    }
                },
                error: function() {
                    // If API fails, allow to proceed (graceful degradation)
                    resolve({ valid: true });
                }
            });
        });
    }

    /**
     * Check all three email addresses against CRM for existing members
     * Prevents duplicate member applications
     */
    async function checkEmailsInCRM() {
        const emailFields = [
            { id: '#email1', name: 'E-Mail 1' },
            { id: '#email2', name: 'E-Mail 2' },
            { id: '#email3', name: 'E-Mail 3' }
        ];

        // Check each email that has a value
        for (const emailField of emailFields) {
            const email = $(emailField.id).val().trim();

            if (!email) {
                continue; // Skip empty emails
            }

            // Check this email in CRM
            const result = await checkSingleEmailInCRM(email);

            if (!result.valid) {
                return {
                    valid: false,
                    message: result.message,
                    field: emailField.id
                };
            }
        }

        // All emails are valid
        return { valid: true };
    }

    /**
     * Check a single email address in CRM
     */
    function checkSingleEmailInCRM(email) {
        return new Promise((resolve) => {
            $.ajax({
                url: dgptmMitgliedsantrag.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_check_email_in_crm',
                    nonce: dgptmMitgliedsantrag.nonce,
                    email: email
                },
                success: function(response) {
                    if (response.success) {
                        // Email not found in CRM or not a member - OK
                        resolve({ valid: true });
                    } else {
                        // Email found for existing member - Error
                        resolve({
                            valid: false,
                            message: response.data.message || 'E-Mail-Adresse bei Mitglied gefunden, bitte andere E-Mail-Adresse nutzen.'
                        });
                    }
                },
                error: function(xhr, status, error) {
                    console.error('CRM email check error:', error);
                    // On error, allow to proceed (graceful degradation)
                    resolve({ valid: true });
                }
            });
        });
    }

    function validateStudentFields($container) {
        let isValid = true;

        // Validate study direction
        if (!$('#studienrichtung').val()) {
            showError($('#studienrichtung').closest('.form-group'), 'Bitte geben Sie Ihre Studienrichtung an.');
            isValid = false;
        }

        // Validate certificate upload
        const fileInput = $('#studienbescheinigung')[0];
        if (!fileInput.files || fileInput.files.length === 0) {
            showError($('#studienbescheinigung').closest('.form-group'), 'Bitte laden Sie eine Studienbescheinigung hoch.');
            isValid = false;
        } else {
            // Validate file type
            const file = fileInput.files[0];
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            if (!allowedTypes.includes(file.type)) {
                showError($('#studienbescheinigung').closest('.form-group'), 'Nur JPG, PNG oder PDF Dateien erlaubt.');
                isValid = false;
            }
            // Validate file size (5MB max)
            if (file.size > 5 * 1024 * 1024) {
                showError($('#studienbescheinigung').closest('.form-group'), 'Datei zu groß (max. 5 MB).');
                isValid = false;
            }
        }

        // Validate valid until year
        const validUntil = $('#studienbescheinigung_gueltig_bis').val();
        if (!validUntil || validUntil < 2025 || validUntil > 2030) {
            showError($('#studienbescheinigung_gueltig_bis').closest('.form-group'), 'Bitte geben Sie ein gültiges Jahr an (2025-2030).');
            isValid = false;
        }

        return isValid;
    }

    function validateGuarantors() {
        const requirements = getGuarantorRequirements();
        let isValid = true;

        // Check if any guarantor input was provided
        const buerge1Input = $('#buerge1_input').val();
        const buerge2Input = $('#buerge2_input').val();
        const buerge1Filled = buerge1Input && buerge1Input.trim() !== '';
        const buerge2Filled = buerge2Input && buerge2Input.trim() !== '';
        const hasAnyGuarantorInput = buerge1Filled || buerge2Filled;

        console.log('Validating guarantors - Required:', requirements.required, 'Optional:', requirements.optional, 'Has input:', hasAnyGuarantorInput);

        // Keine Bürgen erforderlich und keine eingegeben - immer valid
        if (requirements.required === 0 && !hasAnyGuarantorInput) {
            console.log('No guarantors required and none provided - valid');
            return true;
        }

        // Wenn Bürgen optional sind (required: 0, optional: true) aber Eingaben gemacht wurden
        // müssen diese verifiziert sein
        if (requirements.required === 0 && requirements.optional && hasAnyGuarantorInput) {
            console.log('Optional guarantors with input - validating filled fields');
            // Prüfe nur die ausgefüllten Felder
            if (buerge1Filled) {
                if (!guarantor1Valid) {
                    showError($('#buerge1_input').closest('.form-group'), 'Wenn Sie einen Bürgen angeben, muss dieser ein verifiziertes Mitglied sein.');
                    console.log('Guarantor 1 invalid');
                    isValid = false;
                }
            }
            if (buerge2Filled) {
                if (!guarantor2Valid) {
                    showError($('#buerge2_input').closest('.form-group'), 'Wenn Sie einen Bürgen angeben, muss dieser ein verifiziertes Mitglied sein.');
                    console.log('Guarantor 2 invalid');
                    isValid = false;
                }
            }

            // If validation passed for optional guarantors, continue to duplicate check
            if (!isValid) {
                return false;
            }
        }
        // Prüfe Bürge 1 (immer erforderlich wenn Bürgen benötigt werden)
        else if (requirements.required >= 1) {
            if (!guarantor1Valid) {
                showError($('#buerge1_input').closest('.form-group'), 'Bürge 1 muss ein verifiziertes Mitglied sein.');
                isValid = false;
            }
        }

        // Prüfe Bürge 2 (nur erforderlich wenn nicht optional)
        if (requirements.required === 2 && !requirements.optional) {
            if (!guarantor2Valid) {
                showError($('#buerge2_input').closest('.form-group'), 'Bürge 2 muss ein verifiziertes Mitglied sein.');
                isValid = false;
            }
        }

        // Bei studentischen Mitgliedern: Mindestens 1 Bürge muss verifiziert sein
        if (requirements.optional && requirements.required === 1) {
            if (!guarantor1Valid && !guarantor2Valid) {
                showError($('#buerge1_input').closest('.form-group'), 'Mindestens ein Bürge muss verifiziert sein.');
                isValid = false;
            }
        }

        // Check if both guarantors are the same person (wenn beide ausgefüllt)
        if (guarantor1Valid && guarantor2Valid) {
            const buerge1Id = $('#buerge1_id').val();
            const buerge2Id = $('#buerge2_id').val();
            const buerge1Email = $('#buerge1_email').val().toLowerCase();
            const buerge2Email = $('#buerge2_email').val().toLowerCase();

            // Check if same CRM contact ID
            if (buerge1Id && buerge2Id && buerge1Id === buerge2Id) {
                showError($('#buerge2_input').closest('.form-group'), 'Bürge 2 muss eine andere Person als Bürge 1 sein.');
                isValid = false;
            }

            // Check if same email address
            if (buerge1Email && buerge2Email && buerge1Email === buerge2Email) {
                showError($('#buerge2_input').closest('.form-group'), 'Bürge 2 muss eine andere Person als Bürge 1 sein (gleiche E-Mail-Adresse).');
                isValid = false;
            }
        }

        return isValid;
    }

    function showError($group, message) {
        $group.addClass('error');
        $group.append('<span class="error-message">' + message + '</span>');
    }

    function setupGuarantorVerification() {
        let verificationTimers = {};

        $('#buerge1_input, #buerge2_input').on('input', function() {
            const $input = $(this);
            const guarantorNum = $input.attr('id').includes('1') ? '1' : '2';
            const value = $input.val().trim();

            // Clear previous timer
            if (verificationTimers[guarantorNum]) {
                clearTimeout(verificationTimers[guarantorNum]);
            }

            // Reset status
            $('.guarantor-status[data-guarantor="' + guarantorNum + '"]').removeClass('verifying verified not-found not-member');
            $('#buerge' + guarantorNum + '_info').removeClass('success error warning').text('');

            if (value.length < 3) {
                return;
            }

            // Check if same as other guarantor (real-time check before verification)
            const otherGuarantorNum = guarantorNum === '1' ? '2' : '1';
            const otherGuarantorValue = $('#buerge' + otherGuarantorNum + '_input').val().trim();

            // Check if entering same text as other guarantor
            if (otherGuarantorValue && value.toLowerCase() === otherGuarantorValue.toLowerCase()) {
                $('.guarantor-status[data-guarantor="' + guarantorNum + '"]').addClass('not-member');
                $('#buerge' + guarantorNum + '_info').addClass('warning').text('⚠ Dies ist die gleiche Eingabe wie bei Bürge ' + otherGuarantorNum + '. Bitte wählen Sie einen anderen Bürgen.');

                if (guarantorNum === '1') {
                    guarantor1Valid = false;
                } else {
                    guarantor2Valid = false;
                }
                return;
            }

            // Set verifying status
            $('.guarantor-status[data-guarantor="' + guarantorNum + '"]').addClass('verifying');

            // Debounce verification
            verificationTimers[guarantorNum] = setTimeout(function() {
                verifyGuarantor(value, guarantorNum);
            }, 800);
        });
    }

    function verifyGuarantor(input, guarantorNum) {
        $.ajax({
            url: dgptmMitgliedsantrag.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_verify_guarantor',
                nonce: dgptmMitgliedsantrag.nonce,
                input: input
            },
            success: function(response) {
                const $status = $('.guarantor-status[data-guarantor="' + guarantorNum + '"]');
                const $info = $('#buerge' + guarantorNum + '_info');

                $status.removeClass('verifying');

                if (response.success && response.data.found) {
                    if (response.data.is_member) {
                        // Check if this is the same person as the other guarantor
                        const otherGuarantorNum = guarantorNum === '1' ? '2' : '1';
                        const otherGuarantorId = $('#buerge' + otherGuarantorNum + '_id').val();
                        const otherGuarantorEmail = $('#buerge' + otherGuarantorNum + '_email').val().toLowerCase();
                        const thisEmail = response.data.contact.email.toLowerCase();

                        if ((otherGuarantorId && otherGuarantorId === response.data.contact.id) ||
                            (otherGuarantorEmail && otherGuarantorEmail === thisEmail)) {
                            // Same person as other guarantor
                            $status.addClass('not-member');
                            $info.addClass('warning').text(
                                '⚠ Dies ist die gleiche Person wie Bürge ' + otherGuarantorNum + '. ' +
                                'Bitte wählen Sie einen anderen Bürgen.'
                            );

                            if (guarantorNum === '1') {
                                guarantor1Valid = false;
                            } else {
                                guarantor2Valid = false;
                            }
                            return;
                        }

                        // Valid member found and different from other guarantor
                        $status.addClass('verified');
                        $info.addClass('success').text(
                            'Verifiziert: ' + response.data.contact.name +
                            ' (' + response.data.contact.membership_type + ')'
                        );

                        // Store contact data
                        $('#buerge' + guarantorNum + '_id').val(response.data.contact.id);
                        $('#buerge' + guarantorNum + '_name').val(response.data.contact.name);
                        $('#buerge' + guarantorNum + '_email').val(response.data.contact.email);

                        // Mark as valid
                        if (guarantorNum === '1') {
                            guarantor1Valid = true;
                        } else {
                            guarantor2Valid = true;
                        }
                    } else {
                        // Found but not a member
                        $status.addClass('not-member');
                        $info.addClass('warning').text(
                            'Person gefunden, aber kein gültiges Mitglied. Mitgliedstyp: ' +
                            (response.data.contact.membership_type || 'Nicht gesetzt')
                        );

                        if (guarantorNum === '1') {
                            guarantor1Valid = false;
                        } else {
                            guarantor2Valid = false;
                        }
                    }
                } else {
                    // Not found
                    $status.addClass('not-found');
                    $info.addClass('error').text('Kein Mitglied mit diesem Namen oder dieser E-Mail-Adresse gefunden.');

                    if (guarantorNum === '1') {
                        guarantor1Valid = false;
                    } else {
                        guarantor2Valid = false;
                    }
                }
            },
            error: function() {
                const $status = $('.guarantor-status[data-guarantor="' + guarantorNum + '"]');
                const $info = $('#buerge' + guarantorNum + '_info');

                $status.removeClass('verifying').addClass('not-found');
                $info.addClass('error').text('Fehler bei der Überprüfung. Bitte versuchen Sie es erneut.');

                if (guarantorNum === '1') {
                    guarantor1Valid = false;
                } else {
                    guarantor2Valid = false;
                }
            }
        });
    }

    function setupEmailValidation() {
        $('input[type="email"]').on('input', function() {
            const $input = $(this);
            const $icon = $input.siblings('label').find('.validation-icon');
            const email = $input.val().trim();
            const fieldName = $input.attr('name');

            // Clear previous timer
            if (emailValidationTimers[fieldName]) {
                clearTimeout(emailValidationTimers[fieldName]);
            }

            // Reset icon
            $icon.removeClass('valid invalid checking');

            if (!email) {
                return;
            }

            // Basic format check
            if (!isValidEmail(email)) {
                $icon.addClass('invalid');
                return;
            }

            // Show checking status
            $icon.addClass('checking');

            // Debounce API validation
            emailValidationTimers[fieldName] = setTimeout(function() {
                validateEmail(email, $icon);
            }, 1000);
        });
    }

    function setupAddressValidation() {
        // Trigger address validation when all address fields are filled
        let validationTimer;

        $('#strasse, #plz, #stadt').on('input', function() {
            clearTimeout(validationTimer);

            const strasse = $('#strasse').val().trim();
            const plz = $('#plz').val().trim();
            const stadt = $('#stadt').val().trim();

            // Only validate if all required fields are filled
            if (strasse && plz && stadt) {
                validationTimer = setTimeout(function() {
                    validateAddressWithAPI(strasse, plz, stadt);
                }, 1500);
            }
        });
    }

    function validateAddressWithAPI(street, zip, city) {
        const country = $('#land').val() || 'Deutschland';

        $.ajax({
            url: dgptmMitgliedsantrag.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_validate_address',
                nonce: dgptmMitgliedsantrag.nonce,
                street: street,
                zip: zip,
                city: city,
                country: country
            },
            success: function(response) {
                const $addressFields = $('#strasse, #plz, #stadt');

                if (response.success) {
                    // Show success indicator
                    $addressFields.each(function() {
                        const $field = $(this);
                        const $group = $field.closest('.form-group');
                        $group.removeClass('error');
                        $group.find('.error-message').remove();
                    });

                    // Show formatted address if available
                    if (response.data.formatted_address) {
                        $('#stadt').attr('title', 'Verifiziert: ' + response.data.formatted_address);
                    }
                } else {
                    // Show error on city field
                    const $group = $('#stadt').closest('.form-group');
                    $group.addClass('error');
                    $group.find('.error-message').remove();
                    $group.append('<span class="error-message">' + response.data.message + '</span>');
                }
            }
        });
    }

    function validateEmail(email, $icon) {
        $.ajax({
            url: dgptmMitgliedsantrag.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_validate_email',
                nonce: dgptmMitgliedsantrag.nonce,
                email: email
            },
            success: function(response) {
                $icon.removeClass('checking');
                if (response.success) {
                    $icon.addClass('valid');
                } else {
                    $icon.addClass('invalid');
                }
            },
            error: function() {
                $icon.removeClass('checking').addClass('invalid');
            }
        });
    }

    function isValidEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }

    function setupStudentToggle() {
        $('#ist_student').on('change', function() {
            const isChecked = $(this).is(':checked');
            const mitgliedsart = $('#mitgliedsart').val();

            if (isChecked) {
                $('#student-fields').show();
                $('#non-student-info').hide();

                // Make student fields required
                $('#studienrichtung').prop('required', true);
                $('#studienbescheinigung').prop('required', true);
                $('#studienbescheinigung_gueltig_bis').prop('required', true);
            } else {
                $('#student-fields').hide();
                $('#non-student-info').show();

                // Remove required from student fields
                $('#studienrichtung').prop('required', false);
                $('#studienbescheinigung').prop('required', false);
                $('#studienbescheinigung_gueltig_bis').prop('required', false);
            }

            // Update guarantor requirements (student ordentliches Mitglied needs only 1 guarantor)
            if (mitgliedsart === 'ordentliches') {
                updateGuarantorSection();
            }
        });

        // Trigger initial state
        $('#ist_student').trigger('change');
    }

    function setupFormSubmission() {
        $('#dgptm-mitgliedsantrag-form').on('submit', async function(e) {
            e.preventDefault();

            console.log('Form submission started');

            // Clear all previous errors
            $('.form-group').removeClass('error');
            $('.error-message').remove();

            // Validate step 5 (async)
            const isValid = await validateStep(5);
            console.log('Step 5 validation result:', isValid);

            if (!isValid) {
                console.log('Validation failed, stopping submission');
                alert('Bitte füllen Sie alle erforderlichen Felder in Schritt 5 aus.');
                return false;
            }

            const $form = $(this);
            const $submitBtn = $('.btn-submit');

            console.log('Disabling submit button and preparing data');

            // Disable submit button
            $submitBtn.prop('disabled', true).text(dgptmMitgliedsantrag.strings.submitting);

            // Add loading state
            $form.addClass('loading');

            // Prepare form data
            const formData = new FormData($form[0]);
            formData.append('action', 'dgptm_submit_application');
            formData.append('nonce', dgptmMitgliedsantrag.nonce);

            // Convert checkbox values - explicitly set as string 'true' or 'false'
            formData.set('ist_student', $('#ist_student').is(':checked') ? 'true' : 'false');
            formData.set('satzung_akzeptiert', $('#satzung_akzeptiert').is(':checked') ? 'true' : 'false');
            formData.set('beitrag_akzeptiert', $('#beitrag_akzeptiert').is(':checked') ? 'true' : 'false');
            formData.set('dsgvo_akzeptiert', $('#dsgvo_akzeptiert').is(':checked') ? 'true' : 'false');

            console.log('Sending AJAX request to:', dgptmMitgliedsantrag.ajaxUrl);

            $.ajax({
                url: dgptmMitgliedsantrag.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('AJAX success response:', response);
                    $form.removeClass('loading');

                    if (response.success) {
                        console.log('Application submitted successfully');
                        // Hide form and show success message
                        $form.fadeOut(300, function() {
                            $('#success-message').fadeIn(300);
                        });
                    } else {
                        console.error('Application submission failed:', response.data);
                        alert(response.data.message || dgptmMitgliedsantrag.strings.error);
                        $submitBtn.prop('disabled', false).text('Antrag absenden');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', {xhr: xhr, status: status, error: error});
                    console.error('Response text:', xhr.responseText);
                    $form.removeClass('loading');

                    // More detailed error message
                    let errorMsg = dgptmMitgliedsantrag.strings.error;
                    if (xhr.responseText) {
                        errorMsg += '\n\nServer response: ' + xhr.responseText.substring(0, 200);
                    }
                    alert(errorMsg);

                    $submitBtn.prop('disabled', false).text('Antrag absenden');
                }
            });

            return false;
        });
    }

})(jQuery);
