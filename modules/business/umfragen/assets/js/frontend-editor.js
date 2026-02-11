/**
 * DGPTM Umfragen - Frontend Editor JavaScript
 * Survey management, question builder from the frontend
 */
(function($) {
    'use strict';

    var FEEditor = {

        init: function() {
            this.bindSurveyForm();
            this.bindQuestionBuilder();
            this.initSortable();
            this.bindCopyLinks();
            this.populateParentDropdowns();
        },

        notify: function(message, type) {
            var $notice = $('<div class="dgptm-fe-notice">').addClass(type || 'success').text(message);
            $('body').append($notice);
            setTimeout(function() {
                $notice.fadeOut(300, function() { $(this).remove(); });
            }, 3000);
        },

        // --- Copy links ---
        bindCopyLinks: function() {
            var self = this;
            $(document).on('click', '.dgptm-fe-copy-link', function() {
                var url = $(this).data('url');
                if (url && navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(function() {
                        self.notify(dgptmSurveyEditor.strings.linkCopied || 'Link kopiert!');
                    });
                }
            });
        },

        // --- Survey Form ---
        bindSurveyForm: function() {
            var self = this;

            $('#dgptm-fe-survey-form').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var $btn = $form.find('.dgptm-fe-btn-primary');
                $btn.prop('disabled', true).text('Speichern...');

                var data = {
                    action: 'dgptm_survey_save',
                    nonce: dgptmSurveyEditor.nonce,
                    survey_id: $form.find('[name="survey_id"]').val(),
                    title: $form.find('[name="title"]').val(),
                    slug: $form.find('[name="slug"]').val(),
                    description: $form.find('[name="description"]').val(),
                    status: $form.find('[name="status"]').val(),
                    access_mode: $form.find('[name="access_mode"]').val(),
                    duplicate_check: $form.find('[name="duplicate_check"]').val(),
                    show_progress: $form.find('[name="show_progress"]').is(':checked') ? 1 : 0,
                    allow_save_resume: $form.find('[name="allow_save_resume"]').is(':checked') ? 1 : 0,
                    completion_text: $form.find('[name="completion_text"]').val() || ''
                };

                $.post(dgptmSurveyEditor.ajaxUrl, data, function(resp) {
                    if (resp.success) {
                        self.notify(resp.data.message);
                        if (!data.survey_id || data.survey_id === '0') {
                            // Redirect to edit this new survey
                            var url = new URL(window.location.href);
                            url.searchParams.set('survey_action', 'edit');
                            url.searchParams.set('survey_id', resp.data.survey_id);
                            window.location.href = url.toString();
                        } else {
                            $btn.prop('disabled', false).text('Umfrage speichern');
                            if (resp.data.slug) {
                                $form.find('[name="slug"]').val(resp.data.slug);
                            }
                        }
                    } else {
                        self.notify(resp.data.message, 'error');
                        $btn.prop('disabled', false).text('Umfrage speichern');
                    }
                }).fail(function() {
                    self.notify(dgptmSurveyEditor.strings.error, 'error');
                    $btn.prop('disabled', false).text('Umfrage speichern');
                });
            });
        },

        // --- Question Builder ---
        bindQuestionBuilder: function() {
            var self = this;

            // Toggle question body
            $(document).on('click', '.dgptm-fe-toggle-q, .dgptm-fe-question-header', function(e) {
                if ($(e.target).closest('.dgptm-fe-remove-q, .dgptm-fe-toggle-q').length && !$(e.target).closest('.dgptm-fe-toggle-q').length) {
                    return;
                }
                var $item = $(this).closest('.dgptm-fe-question-item');
                $item.toggleClass('dgptm-fe-open');
                $item.find('.dgptm-fe-question-body').slideToggle(200);
            });

            // Type change
            $(document).on('change', '.dgptm-fe-q-type', function() {
                var type = $(this).val();
                var $item = $(this).closest('.dgptm-fe-question-item');
                var choiceTypes = ['radio', 'checkbox', 'select'];

                $item.find('.dgptm-fe-choices-section').toggle(choiceTypes.indexOf(type) !== -1);
                $item.find('.dgptm-fe-exclusive-section').toggle(type === 'checkbox');
                $item.find('.dgptm-fe-matrix-section').toggle(type === 'matrix');
                $item.find('.dgptm-fe-number-section').toggle(type === 'number');
                $item.find('.dgptm-fe-text-section').toggle(type === 'text');

                var label = $(this).find('option:selected').text();
                $item.find('.dgptm-fe-type-badge').text(label);
            });

            // Update title preview
            $(document).on('input', '.dgptm-fe-q-text', function() {
                var text = $(this).val();
                var preview = text.length > 60 ? text.substring(0, 60) + '...' : text;
                $(this).closest('.dgptm-fe-question-item').find('.dgptm-fe-question-preview').text(preview || 'Neue Frage');
            });

            // Add question
            $('#dgptm-fe-add-question').on('click', function() {
                var type = $('#dgptm-fe-new-type').val();
                var template = $('#tmpl-dgptm-fe-question').html();
                var count = $('#dgptm-fe-questions-list .dgptm-fe-question-item').length + 1;
                var typeLabel = $('#dgptm-fe-new-type option:selected').text();

                template = template.replace('{{number}}', count).replace('{{typeLabel}}', typeLabel);

                var $newItem = $(template);
                $newItem.find('.dgptm-fe-q-type').val(type).trigger('change');
                $('#dgptm-fe-questions-list').append($newItem);

                $newItem.addClass('dgptm-fe-open');
                $newItem.find('.dgptm-fe-question-body').show();

                self.updateQuestionNumbers();
                self.populateParentDropdowns();
                self.notify(dgptmSurveyEditor.strings.questionAdded);

                $('html, body').animate({ scrollTop: $newItem.offset().top - 100 }, 300);
            });

            // Remove question
            $(document).on('click', '.dgptm-fe-remove-q', function(e) {
                e.stopPropagation();
                if (!confirm(dgptmSurveyEditor.strings.confirmDelete)) return;
                $(this).closest('.dgptm-fe-question-item').slideUp(200, function() {
                    $(this).remove();
                    self.updateQuestionNumbers();
                    self.populateParentDropdowns();
                });
                self.notify(dgptmSurveyEditor.strings.questionRemoved);
            });

            // Choices
            $(document).on('click', '.dgptm-fe-add-choice', function() {
                var $list = $(this).siblings('.dgptm-fe-choices-list');
                $list.append(
                    '<div class="dgptm-fe-choice-item">' +
                    '<input type="text" class="dgptm-fe-choice-input" value="" placeholder="Option...">' +
                    '<button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-danger dgptm-fe-remove-choice">&times;</button>' +
                    '</div>'
                );
                $list.find('.dgptm-fe-choice-input').last().focus();
            });

            $(document).on('click', '.dgptm-fe-remove-choice', function() {
                $(this).closest('.dgptm-fe-choice-item').remove();
            });

            // Matrix
            $(document).on('click', '.dgptm-fe-add-matrix-row', function() {
                $(this).siblings('.dgptm-fe-matrix-rows-list').append(
                    '<div class="dgptm-fe-choice-item">' +
                    '<input type="text" class="dgptm-fe-matrix-row-input" value="" placeholder="Zeile...">' +
                    '<button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-danger dgptm-fe-remove-matrix-row">&times;</button>' +
                    '</div>'
                );
            });
            $(document).on('click', '.dgptm-fe-remove-matrix-row', function() {
                $(this).closest('.dgptm-fe-choice-item').remove();
            });
            $(document).on('click', '.dgptm-fe-add-matrix-col', function() {
                $(this).siblings('.dgptm-fe-matrix-cols-list').append(
                    '<div class="dgptm-fe-choice-item">' +
                    '<input type="text" class="dgptm-fe-matrix-col-input" value="" placeholder="Spalte...">' +
                    '<button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-danger dgptm-fe-remove-matrix-col">&times;</button>' +
                    '</div>'
                );
            });
            $(document).on('click', '.dgptm-fe-remove-matrix-col', function() {
                $(this).closest('.dgptm-fe-choice-item').remove();
            });

            // Skip logic
            $(document).on('click', '.dgptm-fe-add-skip', function() {
                var $rules = $(this).siblings('.dgptm-fe-skip-rules');
                $rules.append(
                    '<div class="dgptm-fe-skip-rule">' +
                    'Wenn = <input type="text" class="dgptm-fe-skip-value" value="" placeholder="Wert" style="width:100px;">' +
                    ' &rarr; Frage ' +
                    '<select class="dgptm-fe-skip-goto"><option value="">--</option></select>' +
                    ' <button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-danger dgptm-fe-remove-skip">&times;</button>' +
                    '</div>'
                );
                self.populateSkipTargets($rules.find('.dgptm-fe-skip-goto').last());
            });

            $(document).on('click', '.dgptm-fe-remove-skip', function() {
                $(this).closest('.dgptm-fe-skip-rule').remove();
            });

            // Save questions
            $('#dgptm-fe-save-questions').on('click', function() {
                var $btn = $(this);
                var questions = self.collectQuestions();

                if (questions.length === 0) {
                    self.notify(dgptmSurveyEditor.strings.noQuestions, 'error');
                    return;
                }

                $btn.prop('disabled', true).text('Speichern...');

                var surveyId = $('input[name="survey_id"]').val();
                $.post(dgptmSurveyEditor.ajaxUrl, {
                    action: 'dgptm_survey_save_questions',
                    nonce: dgptmSurveyEditor.nonce,
                    survey_id: surveyId,
                    questions: JSON.stringify(questions)
                }, function(resp) {
                    if (resp.success) {
                        self.notify(resp.data.message);
                        if (resp.data.question_ids) {
                            $('#dgptm-fe-questions-list .dgptm-fe-question-item').each(function(i) {
                                if (resp.data.question_ids[i]) {
                                    $(this).attr('data-question-id', resp.data.question_ids[i]);
                                }
                            });
                        }
                        self.populateParentDropdowns();
                    } else {
                        self.notify(resp.data.message, 'error');
                    }
                    $btn.prop('disabled', false).text('Alle Fragen speichern');
                }).fail(function() {
                    self.notify(dgptmSurveyEditor.strings.error, 'error');
                    $btn.prop('disabled', false).text('Alle Fragen speichern');
                });
            });
        },

        // --- Sortable ---
        initSortable: function() {
            var self = this;
            if ($('#dgptm-fe-questions-list').length) {
                $('#dgptm-fe-questions-list').sortable({
                    handle: '.dgptm-fe-drag-handle',
                    placeholder: 'ui-sortable-placeholder',
                    tolerance: 'pointer',
                    update: function() {
                        self.updateQuestionNumbers();
                        self.populateParentDropdowns();
                    }
                });
            }
        },

        // --- Helpers ---

        updateQuestionNumbers: function() {
            $('#dgptm-fe-questions-list .dgptm-fe-question-item').each(function(i) {
                $(this).find('.dgptm-fe-question-number').text((i + 1) + '.');
            });
            $('.dgptm-fe-question-count').text('(' + $('#dgptm-fe-questions-list .dgptm-fe-question-item').length + ')');
        },

        populateParentDropdowns: function() {
            var questions = [];
            $('#dgptm-fe-questions-list .dgptm-fe-question-item').each(function(i) {
                questions.push({
                    id: $(this).attr('data-question-id'),
                    text: (i + 1) + '. ' + ($(this).find('.dgptm-fe-q-text').val() || 'Frage ' + (i + 1)).substring(0, 40)
                });
            });

            $('#dgptm-fe-questions-list .dgptm-fe-q-parent').each(function() {
                var $select = $(this);
                var currentVal = $select.val();
                var myId = $select.closest('.dgptm-fe-question-item').attr('data-question-id');

                $select.find('option:not(:first)').remove();
                $.each(questions, function(idx, q) {
                    if (q.id !== myId) {
                        $select.append($('<option>').val(q.id).text(q.text));
                    }
                });

                if (currentVal) {
                    $select.val(currentVal);
                }
            });
        },

        populateSkipTargets: function($select) {
            $('#dgptm-fe-questions-list .dgptm-fe-question-item').each(function(i) {
                var id = $(this).attr('data-question-id');
                var text = $(this).find('.dgptm-fe-q-text').val() || 'Frage ' + (i + 1);
                text = (i + 1) + '. ' + (text.length > 40 ? text.substring(0, 40) + '...' : text);
                $select.append($('<option>').val(id).text(text));
            });
        },

        collectQuestions: function() {
            var questions = [];

            $('#dgptm-fe-questions-list .dgptm-fe-question-item').each(function() {
                var $item = $(this);
                var type = $item.find('.dgptm-fe-q-type').val();
                var q = {
                    id: $item.attr('data-question-id') || 0,
                    question_type: type,
                    question_text: $item.find('.dgptm-fe-q-text').val(),
                    description: $item.find('.dgptm-fe-q-description').val(),
                    group_label: $item.find('.dgptm-fe-q-group').val(),
                    is_required: $item.find('.dgptm-fe-q-required').is(':checked') ? 1 : 0,
                    choices: null,
                    validation_rules: null,
                    skip_logic: null,
                    parent_question_id: 0,
                    parent_answer_value: ''
                };

                // Choices
                if (type === 'radio' || type === 'checkbox' || type === 'select') {
                    var choices = [];
                    $item.find('.dgptm-fe-choice-input').each(function() {
                        var val = $.trim($(this).val());
                        if (val) choices.push(val);
                    });
                    if (choices.length > 0) q.choices = choices;
                }

                // Matrix
                if (type === 'matrix') {
                    var rows = [], cols = [];
                    $item.find('.dgptm-fe-matrix-row-input').each(function() {
                        var val = $.trim($(this).val());
                        if (val) rows.push(val);
                    });
                    $item.find('.dgptm-fe-matrix-col-input').each(function() {
                        var val = $.trim($(this).val());
                        if (val) cols.push(val);
                    });
                    if (rows.length > 0 || cols.length > 0) {
                        q.choices = { rows: rows, columns: cols };
                    }
                }

                // Validation
                var validation = {};
                if (q.is_required) validation.required = true;

                if (type === 'number') {
                    var min = $item.find('.dgptm-fe-q-min').val();
                    var max = $item.find('.dgptm-fe-q-max').val();
                    if (min !== '') validation.min = parseFloat(min);
                    if (max !== '') validation.max = parseFloat(max);
                }

                if (type === 'text') {
                    var pattern = $item.find('.dgptm-fe-q-pattern').val();
                    if (pattern) validation.pattern = pattern;
                }

                if (type === 'checkbox') {
                    var exclusive = $.trim($item.find('.dgptm-fe-q-exclusive').val());
                    if (exclusive) validation.exclusive_option = exclusive;
                }

                if (Object.keys(validation).length > 0) {
                    q.validation_rules = validation;
                }

                // Skip logic
                var skipRules = [];
                $item.find('.dgptm-fe-skip-rule').each(function() {
                    var val = $.trim($(this).find('.dgptm-fe-skip-value').val());
                    var goto_id = $(this).find('.dgptm-fe-skip-goto').val();
                    if (val && goto_id) {
                        skipRules.push({ if_value: val, goto_question_id: parseInt(goto_id, 10) });
                    }
                });
                if (skipRules.length > 0) {
                    q.skip_logic = skipRules;
                }

                // Nesting
                var parentId = $item.find('.dgptm-fe-q-parent').val();
                var parentValue = $.trim($item.find('.dgptm-fe-q-parent-value').val());
                if (parentId) {
                    q.parent_question_id = parseInt(parentId, 10) || 0;
                    q.parent_answer_value = parentValue;
                }

                questions.push(q);
            });

            return questions;
        }
    };

    $(document).ready(function() {
        FEEditor.init();
    });

})(jQuery);
