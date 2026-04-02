/**
 * DGPTM Umfragen - Frontend JavaScript
 * Form logic, skip-logic, nesting, exclusive checkboxes, AJAX submit, file uploads
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
                self.bindNesting($container);
                self.bindExclusiveCheckbox($container);
                self.bindTextInputs($container);
                self.bindFileUploads($container);
                self.bindSaveProgress($form, $container);

                // Apply unified visibility evaluation on load
                self.evaluateVisibility($container);

                // Skip initial section if empty
                if (self.totalSections > 1 && !self.sectionHasVisibleQuestions($container, 0)) {
                    var first = self.findNextVisibleSection($container, 0, 1);
                    if (first !== -1) {
                        self.goToSection($container, first);
                    }
                }
            });
        },

        // --- Section navigation ---

        bindNavigation: function($container) {
            var self = this;

            $container.on('click', '.dgptm-btn-next', function() {
                if (!self.validateSection($container, self.currentSection)) {
                    return;
                }
                var next = self.findNextVisibleSection($container, self.currentSection + 1, 1);
                if (next !== -1) {
                    self.goToSection($container, next);
                }
            });

            $container.on('click', '.dgptm-btn-prev', function() {
                var prev = self.findNextVisibleSection($container, self.currentSection - 1, -1);
                if (prev !== -1) {
                    self.goToSection($container, prev);
                }
            });
        },

        goToSection: function($container, index) {
            if (index < 0 || index >= this.totalSections) return;

            this.currentSection = index;
            $container.find('.dgptm-survey-section').hide();
            $container.find('.dgptm-survey-section[data-section="' + index + '"]').fadeIn(200);

            // Calculate progress based on visible sections
            var visibleSections = [];
            for (var i = 0; i < this.totalSections; i++) {
                if (this.sectionHasVisibleQuestions($container, i)) {
                    visibleSections.push(i);
                }
            }
            var currentPos = visibleSections.indexOf(index);
            var totalVisible = visibleSections.length || 1;
            if (currentPos < 0) currentPos = 0;
            var progress = Math.round(((currentPos + 1) / totalVisible) * 100);
            $container.find('.dgptm-progress-fill').css('width', progress + '%');
            $container.find('.dgptm-current-section').text(currentPos + 1);

            // Show/hide nav buttons based on visible sections
            var hasPrev = this.findNextVisibleSection($container, index - 1, -1) !== -1;
            var hasNext = this.findNextVisibleSection($container, index + 1, 1) !== -1;
            $container.find('.dgptm-btn-prev').toggle(hasPrev);
            $container.find('.dgptm-btn-next').toggle(hasNext);
            $container.find('.dgptm-btn-submit').toggle(!hasNext);

            // Scroll to top of form
            $('html, body').animate({
                scrollTop: $container.offset().top - 60
            }, 200);
        },

        sectionHasVisibleQuestions: function($container, index) {
            var $section = $container.find('.dgptm-survey-section[data-section="' + index + '"]');
            if (!$section.length) return false;
            return $section.find('.dgptm-question').not('.dgptm-question-hidden').not('.dgptm-question-hidden-nested').length > 0;
        },

        findNextVisibleSection: function($container, fromIndex, direction) {
            var i = fromIndex;
            while (i >= 0 && i < this.totalSections) {
                if (this.sectionHasVisibleQuestions($container, i)) {
                    return i;
                }
                i += direction;
            }
            return -1;
        },

        // --- Validation ---

        validateSection: function($container, sectionIndex) {
            var valid = true;
            var $section = $container.find('.dgptm-survey-section[data-section="' + sectionIndex + '"]');

            // Find questions in this section that are not hidden by skip-logic or nesting
            $section.find('.dgptm-question').not('.dgptm-question-hidden').not('.dgptm-question-hidden-nested').each(function() {
                var $q = $(this);
                if (!$q.data('required')) return;

                var type = $q.data('question-type');
                var hasValue = false;

                switch (type) {
                    case 'text':
                    case 'textarea':
                    case 'number':
                        hasValue = $.trim($q.find('input, textarea').not('[type="hidden"]').val() || '') !== '';
                        break;
                    case 'select':
                        hasValue = $.trim($q.find('select').val() || '') !== '';
                        break;
                    case 'radio':
                        hasValue = $q.find('input[type="radio"]:checked').length > 0;
                        break;
                    case 'checkbox':
                        hasValue = $q.find('input[type="checkbox"]:checked').length > 0;
                        break;
                    case 'matrix':
                        var $mWrapper = $q.find('.dgptm-matrix-wrapper');
                        var mType = $mWrapper.data('matrix-type') || 'radio';
                        if (mType === 'number') {
                            $q.find('.dgptm-matrix-table tbody tr').each(function() {
                                $(this).find('input[type="number"]').each(function() {
                                    if ($.trim($(this).val()) !== '') hasValue = true;
                                });
                            });
                        } else {
                            $q.find('.dgptm-matrix-table tbody tr').each(function() {
                                if ($(this).find('input:checked').length > 0) hasValue = true;
                            });
                        }
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

        // --- Unified Visibility Evaluation ---
        // Coordinates skip-logic and nesting so they don't conflict.
        // Order: 1) Reset all → 2) Nesting → 3) Skip-Logic → 4) Cascade hidden parents to children

        evaluateVisibility: function($container) {
            // Step 1: Reset all visibility flags
            $container.find('.dgptm-question').removeClass('dgptm-question-hidden dgptm-question-hidden-nested');

            // Step 2: Evaluate nesting (parent→child visibility)
            this.evaluateNesting($container);

            // Step 3: Evaluate skip logic (answer→goto jumps)
            this.evaluateSkipLogic($container);

            // Step 4: Cascade — if a question is hidden (by skip or nesting),
            // all its children must also be hidden
            for (var pass = 0; pass < 10; pass++) {
                var changed = false;
                $container.find('.dgptm-question[data-parent-id]').each(function() {
                    var $q = $(this);
                    if ($q.hasClass('dgptm-question-hidden') || $q.hasClass('dgptm-question-hidden-nested')) {
                        return; // Already hidden
                    }
                    var parentId = $q.data('parent-id');
                    var $parent = $container.find('.dgptm-question[data-question-id="' + parentId + '"]');
                    if ($parent.length && ($parent.hasClass('dgptm-question-hidden') || $parent.hasClass('dgptm-question-hidden-nested'))) {
                        $q.addClass('dgptm-question-hidden-nested');
                        changed = true;
                    }
                });
                // Also cascade: questions with skip-logic-hidden parents
                $container.find('.dgptm-question[data-parent-id]').each(function() {
                    var $q = $(this);
                    if ($q.hasClass('dgptm-question-hidden') || $q.hasClass('dgptm-question-hidden-nested')) {
                        return;
                    }
                    var parentId = $q.data('parent-id');
                    var $parent = $container.find('.dgptm-question[data-question-id="' + parentId + '"]');
                    if ($parent.length && ($parent.hasClass('dgptm-question-hidden') || $parent.hasClass('dgptm-question-hidden-nested'))) {
                        $q.addClass('dgptm-question-hidden-nested');
                        changed = true;
                    }
                });
                if (!changed) break;
            }
        },

        // --- Skip Logic ---

        bindSkipLogic: function($container) {
            var self = this;

            $container.on('change', 'input[type="radio"], input[type="checkbox"], select', function() {
                self.evaluateVisibility($container);
            });
        },

        evaluateSkipLogic: function($container) {
            // Note: reset is handled by evaluateVisibility()

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
                        var $target = $container.find('.dgptm-question[data-question-id="' + rule.goto_question_id + '"]');
                        if ($target.length) {
                            var found = false;
                            var skippedIds = [];
                            $container.find('.dgptm-question').each(function() {
                                if (this === $q[0]) {
                                    found = true;
                                    return;
                                }
                                if (found && this !== $target[0]) {
                                    $(this).addClass('dgptm-question-hidden');
                                    skippedIds.push(String($(this).data('question-id')));
                                }
                                if (this === $target[0]) {
                                    found = false;
                                }
                            });
                            // Also hide children of skipped questions
                            if (skippedIds.length) {
                                $container.find('.dgptm-question[data-parent-id]').each(function() {
                                    var pid = String($(this).data('parent-id'));
                                    if (skippedIds.indexOf(pid) !== -1) {
                                        $(this).addClass('dgptm-question-hidden');
                                    }
                                });
                            }
                        }
                        break;
                    }
                }
            });
        },

        // --- Nesting (Verschachtelung) ---

        bindNesting: function($container) {
            var self = this;
            $container.on('change input', '.dgptm-question input, .dgptm-question textarea, .dgptm-question select', function() {
                self.evaluateVisibility($container);
            });
        },

        evaluateNesting: function($container) {
            // Note: reset is handled by evaluateVisibility()

            for (var pass = 0; pass < 10; pass++) {
                var changed = false;
                $container.find('.dgptm-question[data-parent-id]').each(function() {
                    var $q = $(this);
                    if ($q.hasClass('dgptm-question-hidden-nested')) return;

                    var parentId = $q.data('parent-id');
                    var parentValue = String($q.data('parent-value') || '');
                    if (!parentId) return;

                    var $parent = $container.find('.dgptm-question[data-question-id="' + parentId + '"]');
                    if (!$parent.length) return;

                    if ($parent.hasClass('dgptm-question-hidden-nested') || $parent.hasClass('dgptm-question-hidden')) {
                        $q.addClass('dgptm-question-hidden-nested');
                        changed = true;
                        return;
                    }

                    var parentType = $parent.data('question-type');
                    var currentValue = '';

                    if (parentType === 'radio') {
                        currentValue = $parent.find('input[type="radio"]:checked').val() || '';
                    } else if (parentType === 'select') {
                        currentValue = $parent.find('select').val() || '';
                    } else if (parentType === 'checkbox') {
                        var vals = [];
                        $parent.find('input[type="checkbox"]:checked').each(function() {
                            vals.push($(this).val());
                        });
                        currentValue = vals.join(',');
                    } else {
                        currentValue = $.trim($parent.find('input, textarea').not('[type="hidden"]').first().val() || '');
                    }

                    if (currentValue !== parentValue) {
                        $q.addClass('dgptm-question-hidden-nested');
                        changed = true;
                    }
                });
                if (!changed) break;
            }
        },

        // --- Exclusive Checkbox ---

        bindExclusiveCheckbox: function($container) {
            $container.on('change', '.dgptm-checkbox-label input[type="checkbox"]', function() {
                var $cb = $(this);
                var $q = $cb.closest('.dgptm-question');
                var isExclusive = $cb.closest('.dgptm-checkbox-label').hasClass('dgptm-checkbox-exclusive');

                if ($cb.is(':checked')) {
                    if (isExclusive) {
                        // Uncheck all non-exclusive options
                        $q.find('.dgptm-checkbox-label:not(.dgptm-checkbox-exclusive) input[type="checkbox"]').prop('checked', false);
                    } else {
                        // Uncheck all exclusive options
                        $q.find('.dgptm-checkbox-exclusive input[type="checkbox"]').prop('checked', false);
                    }
                }
            });
        },

        // --- Text Inputs for Choices / "Sonstiges:" ---

        bindTextInputs: function($container) {
            // Radio: show/hide text + number inputs when selection changes
            $container.on('change', '.dgptm-question input[type="radio"]', function() {
                var $q = $(this).closest('.dgptm-question');
                $q.find('.dgptm-choice-text-input, .dgptm-other-text-input, .dgptm-choice-number-wrap').hide();
                var $label = $(this).closest('.dgptm-radio-label, .dgptm-other-label');
                if ($(this).val() === '__other__') {
                    $label.find('.dgptm-other-text-input').show().focus();
                } else {
                    $label.find('.dgptm-choice-text-input').show().focus();
                    $label.find('.dgptm-choice-number-wrap').show();
                }
            });

            // Checkbox: show/hide text + number inputs when checkbox changes
            $container.on('change', '.dgptm-question input[type="checkbox"]', function() {
                var $label = $(this).closest('label');
                var isChecked = $(this).is(':checked');
                if ($(this).val() === '__other__') {
                    var $ti = $label.find('.dgptm-other-text-input');
                    isChecked ? $ti.show().focus() : $ti.hide();
                } else {
                    var $ti = $label.find('.dgptm-choice-text-input');
                    isChecked ? $ti.show().focus() : $ti.hide();
                    var $ni = $label.find('.dgptm-choice-number-wrap');
                    isChecked ? $ni.show() : $ni.hide();
                }
            });

            // Select: show/hide "Sonstiges" text input
            $container.on('change', '.dgptm-question select', function() {
                var $q = $(this).closest('.dgptm-question');
                var $ti = $q.find('.dgptm-other-text-input');
                $(this).val() === '__other__' ? $ti.show().focus() : $ti.hide();
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

                if (file.size > 5 * 1024 * 1024) {
                    alert(dgptmSurvey.strings.fileTooBig);
                    $input.val('');
                    return;
                }

                var allowed = ['application/pdf', 'image/jpeg', 'image/png'];
                if (allowed.indexOf(file.type) === -1) {
                    alert(dgptmSurvey.strings.fileTypeError);
                    $input.val('');
                    return;
                }

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

                // Collect answers from ALL sections (not just visible)
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

                        if (resp.data.question_id) {
                            var $errQ = $container.find('.dgptm-question[data-question-id="' + resp.data.question_id + '"]');
                            if ($errQ.length) {
                                $errQ.addClass('dgptm-question-error-state');
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

        /**
         * Collect answers from ALL sections, excluding only skip-logic
         * and nesting hidden questions.
         */
        collectAnswers: function($container) {
            var answers = {};

            // Collect from ALL questions, not just :visible
            // This ensures multi-section answers are included
            $container.find('.dgptm-question').not('.dgptm-question-hidden').not('.dgptm-question-hidden-nested').each(function() {
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
                        if (radioVal !== undefined) {
                            var textVal = '';
                            var numberVal = '';
                            var $checkedLabel = $q.find('input[type="radio"]:checked').closest('label');
                            if (radioVal === '__other__') {
                                textVal = $.trim($checkedLabel.find('.dgptm-other-text-input').val() || '');
                            } else {
                                textVal = $.trim($checkedLabel.find('.dgptm-choice-text-input').val() || '');
                                numberVal = $.trim($checkedLabel.find('.dgptm-choice-number-input').val() || '');
                            }
                            var val = radioVal;
                            if (textVal || numberVal) val += '|||' + textVal;
                            if (numberVal) val += '|||' + numberVal;
                            answers[qId] = val;
                        }
                        break;
                    case 'select':
                        var selectVal = $q.find('select').val();
                        if (selectVal) {
                            if (selectVal === '__other__') {
                                var otherText = $.trim($q.find('.dgptm-other-text-input').val() || '');
                                answers[qId] = otherText ? '__other__|||' + otherText : '__other__';
                            } else {
                                answers[qId] = selectVal;
                            }
                        }
                        break;
                    case 'checkbox':
                        var cbVals = [];
                        $q.find('input[type="checkbox"]:checked').each(function() {
                            var cbVal = $(this).val();
                            var textVal = '';
                            var numberVal = '';
                            var $lbl = $(this).closest('label');
                            if (cbVal === '__other__') {
                                textVal = $.trim($lbl.find('.dgptm-other-text-input').val() || '');
                            } else {
                                textVal = $.trim($lbl.find('.dgptm-choice-text-input').val() || '');
                                numberVal = $.trim($lbl.find('.dgptm-choice-number-input').val() || '');
                            }
                            var v = cbVal;
                            if (textVal || numberVal) v += '|||' + textVal;
                            if (numberVal) v += '|||' + numberVal;
                            cbVals.push(v);
                        });
                        if (cbVals.length > 0) answers[qId] = cbVals;
                        break;
                    case 'matrix':
                        var matrixVals = {};
                        var $mw = $q.find('.dgptm-matrix-wrapper');
                        var matrixType = $mw.data('matrix-type') || 'radio';

                        if (matrixType === 'number') {
                            // Number matrix: { row_key: { col_key: number } }
                            $q.find('.dgptm-matrix-table tbody tr').each(function() {
                                $(this).find('input[type="number"]').each(function() {
                                    var name = $(this).attr('name');
                                    var val = $.trim($(this).val());
                                    // Parse name: answers[qId][row_key][col_key]
                                    var matches = name.match(/\[([^\]]+)\]\[([^\]]+)\]$/);
                                    if (matches && val !== '') {
                                        if (!matrixVals[matches[1]]) matrixVals[matches[1]] = {};
                                        matrixVals[matches[1]][matches[2]] = val;
                                    }
                                });
                            });
                        } else {
                            // Radio matrix: { row_key: selected_col }
                            $q.find('.dgptm-matrix-table tbody tr').each(function() {
                                var $checked = $(this).find('input:checked');
                                if ($checked.length) {
                                    var name = $checked.attr('name');
                                    var match = name.match(/\[([^\]]+)\]$/);
                                    if (match) {
                                        matrixVals[match[1]] = $checked.val();
                                    }
                                }
                            });
                        }
                        if (Object.keys(matrixVals).length > 0) answers[qId] = matrixVals;
                        break;
                    case 'file':
                        break;
                }
            });

            return answers;
        },

        showNotice: function($container, message, type) {
            var $existing = $container.find('.dgptm-frontend-notice');
            if ($existing.length) $existing.remove();

            var cls = type === 'error' ? 'dgptm-notice-error' : 'dgptm-notice-success';
            var $notice = $('<div class="dgptm-frontend-notice ' + cls + '">').text(message);

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
