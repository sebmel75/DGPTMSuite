/**
 * DGPTM Umfragen - Frontend JavaScript
 * Form logic, skip-logic, AJAX submit, file uploads
 */
(function($) {
    'use strict';

    var SurveyFrontend = {

        currentSection: 0,
        totalSections: 0,

        init: function() {
            var self = this;

            $('.dgptm-survey-form').each(function() {
                var $form = $(this);
                var $container = $form.closest('.dgptm-survey-container');
                self.totalSections = $container.find('.dgptm-survey-section').length;

                self.bindNavigation($container);
                self.bindSubmit($form, $container);
                self.bindSkipLogic($container);
                self.bindFileUploads($container);
                self.bindSaveProgress($form, $container);

                // Apply skip-logic on load (for pre-filled forms)
                self.evaluateSkipLogic($container);
            });
        },

        // --- Section navigation ---

        bindNavigation: function($container) {
            var self = this;

            $container.on('click', '.dgptm-btn-next', function() {
                if (!self.validateSection($container, self.currentSection)) {
                    return;
                }
                self.goToSection($container, self.currentSection + 1);
            });

            $container.on('click', '.dgptm-btn-prev', function() {
                self.goToSection($container, self.currentSection - 1);
            });
        },

        goToSection: function($container, index) {
            if (index < 0 || index >= this.totalSections) return;

            this.currentSection = index;
            $container.find('.dgptm-survey-section').hide();
            $container.find('.dgptm-survey-section[data-section="' + index + '"]').fadeIn(200);

            // Update progress
            var progress = Math.round(((index + 1) / this.totalSections) * 100);
            $container.find('.dgptm-progress-fill').css('width', progress + '%');
            $container.find('.dgptm-current-section').text(index + 1);

            // Show/hide nav buttons
            $container.find('.dgptm-btn-prev').toggle(index > 0);
            $container.find('.dgptm-btn-next').toggle(index < this.totalSections - 1);
            $container.find('.dgptm-btn-submit').toggle(index === this.totalSections - 1);

            // Scroll to top of form
            $('html, body').animate({
                scrollTop: $container.offset().top - 60
            }, 200);
        },

        // --- Validation ---

        validateSection: function($container, sectionIndex) {
            var valid = true;
            var $section = $container.find('.dgptm-survey-section[data-section="' + sectionIndex + '"]');

            $section.find('.dgptm-question:visible').each(function() {
                var $q = $(this);
                if (!$q.data('required')) return;

                var type = $q.data('question-type');
                var hasValue = false;

                switch (type) {
                    case 'text':
                    case 'textarea':
                    case 'number':
                    case 'select':
                        hasValue = $.trim($q.find('.dgptm-input, select').val()) !== '';
                        break;
                    case 'radio':
                        hasValue = $q.find('input[type="radio"]:checked').length > 0;
                        break;
                    case 'checkbox':
                        hasValue = $q.find('input[type="checkbox"]:checked').length > 0;
                        break;
                    case 'matrix':
                        // Check at least one row has a selection
                        var totalRows = $q.find('.dgptm-matrix-table tbody tr').length;
                        var answeredRows = 0;
                        $q.find('.dgptm-matrix-table tbody tr').each(function() {
                            if ($(this).find('input:checked').length > 0) answeredRows++;
                        });
                        hasValue = answeredRows > 0;
                        break;
                    case 'file':
                        hasValue = $q.find('.dgptm-file-ids').val() !== '' || $q.find('.dgptm-file-input').val() !== '';
                        break;
                }

                if (!hasValue) {
                    valid = false;
                    $q.addClass('dgptm-question-error-state');
                    $q.find('.dgptm-question-error').text(dgptmSurvey.strings.required).show();
                } else {
                    $q.removeClass('dgptm-question-error-state');
                    $q.find('.dgptm-question-error').hide();
                }

                // Additional validation
                if (hasValue && type === 'text') {
                    var $input = $q.find('input');
                    if ($input.attr('type') === 'email') {
                        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test($input.val())) {
                            valid = false;
                            $q.addClass('dgptm-question-error-state');
                            $q.find('.dgptm-question-error').text(dgptmSurvey.strings.invalidEmail).show();
                        }
                    }
                }

                if (hasValue && type === 'number') {
                    var $numInput = $q.find('input[type="number"]');
                    var val = parseFloat($numInput.val());
                    var min = $numInput.attr('min');
                    var max = $numInput.attr('max');
                    if (min !== undefined && val < parseFloat(min)) {
                        valid = false;
                        $q.addClass('dgptm-question-error-state');
                        $q.find('.dgptm-question-error').text(dgptmSurvey.strings.minValue + min).show();
                    }
                    if (max !== undefined && val > parseFloat(max)) {
                        valid = false;
                        $q.addClass('dgptm-question-error-state');
                        $q.find('.dgptm-question-error').text(dgptmSurvey.strings.maxValue + max).show();
                    }
                }
            });

            if (!valid) {
                // Scroll to first error
                var $firstError = $section.find('.dgptm-question-error-state').first();
                if ($firstError.length) {
                    $('html, body').animate({
                        scrollTop: $firstError.offset().top - 80
                    }, 200);
                }
            }

            return valid;
        },

        // Clear errors on input
        bindClearErrors: function() {
            $(document).on('input change', '.dgptm-question input, .dgptm-question textarea, .dgptm-question select', function() {
                var $q = $(this).closest('.dgptm-question');
                $q.removeClass('dgptm-question-error-state');
                $q.find('.dgptm-question-error').hide();
            });
        },

        // --- Skip Logic ---

        bindSkipLogic: function($container) {
            var self = this;

            $container.on('change', 'input[type="radio"], input[type="checkbox"], select', function() {
                self.evaluateSkipLogic($container);
            });
        },

        evaluateSkipLogic: function($container) {
            // Reset all questions to visible first
            $container.find('.dgptm-question').removeClass('dgptm-question-hidden');

            // Process each question with skip-logic
            $container.find('.dgptm-question[data-skip-logic]').each(function() {
                var $q = $(this);
                var rules = $q.data('skip-logic');
                if (!rules || !Array.isArray(rules)) return;

                var type = $q.data('question-type');
                var currentValue = '';

                if (type === 'radio') {
                    currentValue = $q.find('input[type="radio"]:checked').val() || '';
                } else if (type === 'select') {
                    currentValue = $q.find('select').val() || '';
                } else if (type === 'checkbox') {
                    var vals = [];
                    $q.find('input[type="checkbox"]:checked').each(function() {
                        vals.push($(this).val());
                    });
                    currentValue = vals.join(',');
                }

                if (!currentValue) return;

                for (var i = 0; i < rules.length; i++) {
                    var rule = rules[i];
                    if (currentValue === rule.if_value && rule.goto_question_id) {
                        // Hide all questions between this one and the target
                        var thisOrder = $q.index('.dgptm-question');
                        var $target = $container.find('.dgptm-question[data-question-id="' + rule.goto_question_id + '"]');
                        if ($target.length) {
                            var found = false;
                            $container.find('.dgptm-question').each(function() {
                                if (this === $q[0]) {
                                    found = true;
                                    return; // Skip source question
                                }
                                if (found && this !== $target[0]) {
                                    $(this).addClass('dgptm-question-hidden');
                                }
                                if (this === $target[0]) {
                                    found = false;
                                }
                            });
                        }
                        break; // First matching rule wins
                    }
                }
            });
        },

        // --- File Uploads ---

        bindFileUploads: function($container) {
            $container.on('change', '.dgptm-file-input', function() {
                var $input = $(this);
                var $area = $input.closest('.dgptm-file-upload-area');
                var $preview = $area.find('.dgptm-file-preview');
                var $idsInput = $area.find('.dgptm-file-ids');
                var file = this.files[0];

                if (!file) return;

                // Check size (5MB)
                if (file.size > 5 * 1024 * 1024) {
                    alert(dgptmSurvey.strings.fileTooBig);
                    $input.val('');
                    return;
                }

                // Check type
                var allowed = ['application/pdf', 'image/jpeg', 'image/png'];
                if (allowed.indexOf(file.type) === -1) {
                    alert(dgptmSurvey.strings.fileTypeError);
                    $input.val('');
                    return;
                }

                // Upload via AJAX
                var formData = new FormData();
                formData.append('action', 'dgptm_survey_upload_file');
                formData.append('nonce', dgptmSurvey.nonce);
                formData.append('file', file);

                $preview.html('<span>Wird hochgeladen...</span>');

                $.ajax({
                    url: dgptmSurvey.ajaxUrl,
                    type: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(resp) {
                        if (resp.success) {
                            var ids = $idsInput.val() ? JSON.parse($idsInput.val()) : [];
                            ids.push(resp.data.attachment_id);
                            $idsInput.val(JSON.stringify(ids));

                            $preview.html(
                                '<div class="dgptm-file-preview-item">' +
                                '<span>' + $('<span>').text(resp.data.filename).html() + '</span>' +
                                '<span class="dgptm-file-remove" data-id="' + resp.data.attachment_id + '">&times;</span>' +
                                '</div>'
                            );
                        } else {
                            $preview.html('<span style="color:#dc3232">' + $('<span>').text(resp.data.message).html() + '</span>');
                        }
                    },
                    error: function() {
                        $preview.html('<span style="color:#dc3232">' + dgptmSurvey.strings.uploadError + '</span>');
                    }
                });
            });

            // Remove uploaded file
            $container.on('click', '.dgptm-file-remove', function() {
                var removeId = $(this).data('id');
                var $area = $(this).closest('.dgptm-file-upload-area');
                var $idsInput = $area.find('.dgptm-file-ids');

                var ids = $idsInput.val() ? JSON.parse($idsInput.val()) : [];
                ids = ids.filter(function(id) { return id !== removeId; });
                $idsInput.val(ids.length > 0 ? JSON.stringify(ids) : '');

                $(this).closest('.dgptm-file-preview-item').remove();
                $area.find('.dgptm-file-input').val('');
            });
        },

        // --- Save Progress ---

        bindSaveProgress: function($form, $container) {
            $container.on('click', '.dgptm-btn-save', function() {
                var $btn = $(this);
                $btn.prop('disabled', true).text('Speichern...');

                var answers = SurveyFrontend.collectAnswers($container);

                $.post(dgptmSurvey.ajaxUrl, {
                    action: 'dgptm_survey_save_progress',
                    nonce: dgptmSurvey.nonce,
                    survey_id: $container.data('survey-id'),
                    response_id: $container.data('response-id') || 0,
                    answers: answers
                }, function(resp) {
                    if (resp.success) {
                        $container.data('response-id', resp.data.response_id);
                        $form.find('input[name="response_id"]').val(resp.data.response_id);
                        SurveyFrontend.showNotice($container, dgptmSurvey.strings.progressSaved, 'success');
                    } else {
                        SurveyFrontend.showNotice($container, resp.data.message, 'error');
                    }
                    $btn.prop('disabled', false).text('Zwischenspeichern');
                }).fail(function() {
                    SurveyFrontend.showNotice($container, dgptmSurvey.strings.error, 'error');
                    $btn.prop('disabled', false).text('Zwischenspeichern');
                });
            });
        },

        // --- Submit ---

        bindSubmit: function($form, $container) {
            var self = this;

            $form.on('submit', function(e) {
                e.preventDefault();

                // Validate current (last) section
                if (!self.validateSection($container, self.currentSection)) {
                    return;
                }

                var $btn = $container.find('.dgptm-btn-submit');
                $btn.prop('disabled', true).text(dgptmSurvey.strings.submitting);

                var answers = self.collectAnswers($container);

                // Include file IDs in answers
                $container.find('.dgptm-file-ids').each(function() {
                    var $ids = $(this);
                    var val = $ids.val();
                    if (val) {
                        var qId = $ids.closest('.dgptm-question').data('question-id');
                        answers[qId] = JSON.parse(val);
                    }
                });

                $.post(dgptmSurvey.ajaxUrl, {
                    action: 'dgptm_survey_submit',
                    nonce: dgptmSurvey.nonce,
                    survey_id: $container.data('survey-id'),
                    response_id: $container.data('response-id') || 0,
                    answers: answers,
                    respondent_name: $container.find('input[name="respondent_name"]').val() || '',
                    respondent_email: $container.find('input[name="respondent_email"]').val() || ''
                }, function(resp) {
                    if (resp.success) {
                        $form.hide();
                        $container.find('.dgptm-survey-nav').hide();
                        $container.find('.dgptm-progress-bar, .dgptm-progress-text').hide();
                        $container.find('.dgptm-survey-success').fadeIn(300);

                        $('html, body').animate({
                            scrollTop: $container.offset().top - 60
                        }, 200);
                    } else {
                        self.showNotice($container, resp.data.message, 'error');
                        $btn.prop('disabled', false).text('Absenden');

                        // Scroll to specific question if indicated
                        if (resp.data.question_id) {
                            var $errQ = $container.find('.dgptm-question[data-question-id="' + resp.data.question_id + '"]');
                            if ($errQ.length) {
                                $errQ.addClass('dgptm-question-error-state');
                                // Go to the section containing this question
                                var $section = $errQ.closest('.dgptm-survey-section');
                                if ($section.length) {
                                    self.goToSection($container, parseInt($section.data('section'), 10));
                                }
                            }
                        }
                    }
                }).fail(function() {
                    self.showNotice($container, dgptmSurvey.strings.error, 'error');
                    $btn.prop('disabled', false).text('Absenden');
                });
            });
        },

        // --- Helpers ---

        collectAnswers: function($container) {
            var answers = {};

            $container.find('.dgptm-question:visible').each(function() {
                var $q = $(this);
                var qId = $q.data('question-id');
                var type = $q.data('question-type');

                switch (type) {
                    case 'text':
                    case 'textarea':
                    case 'number':
                        answers[qId] = $.trim($q.find('input, textarea').not('[type="hidden"]').val() || '');
                        break;
                    case 'radio':
                        var radioVal = $q.find('input[type="radio"]:checked').val();
                        if (radioVal !== undefined) answers[qId] = radioVal;
                        break;
                    case 'select':
                        var selectVal = $q.find('select').val();
                        if (selectVal) answers[qId] = selectVal;
                        break;
                    case 'checkbox':
                        var cbVals = [];
                        $q.find('input[type="checkbox"]:checked').each(function() {
                            cbVals.push($(this).val());
                        });
                        if (cbVals.length > 0) answers[qId] = cbVals;
                        break;
                    case 'matrix':
                        var matrixVals = {};
                        $q.find('.dgptm-matrix-table tbody tr').each(function() {
                            var $checked = $(this).find('input:checked');
                            if ($checked.length) {
                                var name = $checked.attr('name');
                                // Extract row key from name: answers[qid][row_key]
                                var match = name.match(/\[([^\]]+)\]$/);
                                if (match) {
                                    matrixVals[match[1]] = $checked.val();
                                }
                            }
                        });
                        if (Object.keys(matrixVals).length > 0) answers[qId] = matrixVals;
                        break;
                    case 'file':
                        // File IDs handled separately in submit
                        break;
                }
            });

            return answers;
        },

        showNotice: function($container, message, type) {
            var $existing = $container.find('.dgptm-frontend-notice');
            if ($existing.length) $existing.remove();

            var cls = type === 'error' ? 'color:#dc3232;background:#fef7f7;border:1px solid #dc3232;' : 'color:#46b450;background:#f0faf0;border:1px solid #46b450;';
            var $notice = $('<div class="dgptm-frontend-notice" style="' + cls + 'padding:10px 14px;border-radius:4px;margin-bottom:16px;font-size:14px;">')
                .text(message);

            $container.prepend($notice);
            $('html, body').animate({ scrollTop: $container.offset().top - 60 }, 200);

            setTimeout(function() {
                $notice.fadeOut(300, function() { $(this).remove(); });
            }, 5000);
        }
    };

    $(document).ready(function() {
        SurveyFrontend.init();
        SurveyFrontend.bindClearErrors();
    });

})(jQuery);
