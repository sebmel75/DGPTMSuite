/**
 * DGPTM Umfragen - Admin JavaScript
 * Question builder, sortable, AJAX operations
 */
(function($) {
    'use strict';

    var UmfragenAdmin = {

        init: function() {
            this.bindSurveyForm();
            this.bindQuestionBuilder();
            this.bindSurveyList();
            this.initSortable();
        },

        // --- Notifications ---

        notify: function(message, type) {
            var $notice = $('<div class="dgptm-notice">').addClass(type || 'success').text(message);
            $('body').append($notice);
            setTimeout(function() {
                $notice.fadeOut(300, function() { $(this).remove(); });
            }, 3000);
        },

        // --- Survey List ---

        bindSurveyList: function() {
            var self = this;

            // Seed ECLS
            $('#dgptm-seed-ecls').on('click', function() {
                var $btn = $(this);
                if (!confirm('ECLS-Zentren Musterumfrage anlegen?')) return;
                $btn.prop('disabled', true).text('Wird angelegt...');

                $.post(dgptmUmfragen.ajaxUrl, {
                    action: 'dgptm_survey_seed_ecls',
                    nonce: dgptmUmfragen.nonce
                }, function(resp) {
                    if (resp.success) {
                        self.notify(resp.data.message);
                        location.reload();
                    } else {
                        self.notify(resp.data.message, 'error');
                        $btn.prop('disabled', false).text('ECLS-Zentren anlegen');
                    }
                }).fail(function() {
                    self.notify(dgptmUmfragen.strings.error, 'error');
                    $btn.prop('disabled', false).text('ECLS-Zentren anlegen');
                });
            });

            // Delete (archive) survey
            $(document).on('click', '.dgptm-delete-survey', function() {
                if (!confirm(dgptmUmfragen.strings.confirmArchive)) return;
                var id = $(this).data('id');
                var $row = $(this).closest('tr');

                $.post(dgptmUmfragen.ajaxUrl, {
                    action: 'dgptm_survey_delete',
                    nonce: dgptmUmfragen.nonce,
                    survey_id: id
                }, function(resp) {
                    if (resp.success) {
                        $row.fadeOut(300, function() { $(this).remove(); });
                        self.notify(resp.data.message);
                    } else {
                        self.notify(resp.data.message, 'error');
                    }
                }).fail(function() {
                    self.notify(dgptmUmfragen.strings.error, 'error');
                });
            });

            // Duplicate survey
            $(document).on('click', '.dgptm-duplicate-survey', function() {
                var id = $(this).data('id');
                var $btn = $(this);
                $btn.prop('disabled', true);

                $.post(dgptmUmfragen.ajaxUrl, {
                    action: 'dgptm_survey_duplicate',
                    nonce: dgptmUmfragen.nonce,
                    survey_id: id
                }, function(resp) {
                    if (resp.success) {
                        self.notify(resp.data.message);
                        location.reload();
                    } else {
                        self.notify(resp.data.message, 'error');
                        $btn.prop('disabled', false);
                    }
                }).fail(function() {
                    self.notify(dgptmUmfragen.strings.error, 'error');
                    $btn.prop('disabled', false);
                });
            });

            // Copy results link
            $(document).on('click', '.dgptm-copy-results-link', function() {
                var token = $(this).data('token');
                var url = location.origin + '/umfrage-ergebnisse/' + token;

                // Check if we're on the edit page and have an explicit link element
                var $linkEl = $('#results-link');
                if ($linkEl.length) {
                    url = $linkEl.text();
                }

                if (navigator.clipboard) {
                    navigator.clipboard.writeText(url).then(function() {
                        self.notify('Link kopiert!');
                    });
                } else {
                    // Fallback
                    var $temp = $('<input>').val(url).appendTo('body');
                    $temp.select();
                    document.execCommand('copy');
                    $temp.remove();
                    self.notify('Link kopiert!');
                }
            });

            // Copy shortcode
            $(document).on('click', '.dgptm-shortcode-copy', function() {
                var text = $(this).text();
                if (navigator.clipboard) {
                    navigator.clipboard.writeText(text).then(function() {
                        self.notify('Shortcode kopiert!');
                    });
                }
            });
        },

        // --- Survey Form ---

        bindSurveyForm: function() {
            var self = this;

            $('#dgptm-survey-form').on('submit', function(e) {
                e.preventDefault();
                var $form = $(this);
                var $btn = $form.find('.button-primary');
                $btn.prop('disabled', true).text('Speichern...');

                var data = {
                    action: 'dgptm_survey_save',
                    nonce: dgptmUmfragen.nonce,
                    survey_id: $form.find('[name="survey_id"]').val(),
                    title: $form.find('[name="title"]').val(),
                    slug: $form.find('[name="slug"]').val(),
                    description: $form.find('[name="description"]').val(),
                    status: $form.find('[name="status"]').val(),
                    access_mode: $form.find('[name="access_mode"]').val(),
                    duplicate_check: $form.find('[name="duplicate_check"]').val(),
                    show_progress: $form.find('[name="show_progress"]').is(':checked') ? 1 : 0,
                    allow_save_resume: $form.find('[name="allow_save_resume"]').is(':checked') ? 1 : 0
                };

                $.post(dgptmUmfragen.ajaxUrl, data, function(resp) {
                    if (resp.success) {
                        self.notify(resp.data.message);
                        // If new survey, redirect to edit page
                        if (!data.survey_id || data.survey_id === '0') {
                            window.location.href = 'admin.php?page=dgptm-umfragen&view=edit&survey_id=' + resp.data.survey_id;
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
                    self.notify(dgptmUmfragen.strings.error, 'error');
                    $btn.prop('disabled', false).text('Umfrage speichern');
                });
            });
        },

        // --- Question Builder ---

        bindQuestionBuilder: function() {
            var self = this;

            // Toggle question body
            $(document).on('click', '.dgptm-toggle-question, .dgptm-question-header', function(e) {
                if ($(e.target).closest('.dgptm-remove-question, .dgptm-toggle-question').length && !$(e.target).closest('.dgptm-toggle-question').length) {
                    return;
                }
                var $item = $(this).closest('.dgptm-question-item');
                $item.toggleClass('dgptm-open');
                $item.find('.dgptm-question-body').slideToggle(200);
            });

            // Type change -> show/hide relevant fields
            $(document).on('change', '.dgptm-q-type', function() {
                var type = $(this).val();
                var $item = $(this).closest('.dgptm-question-item');
                var choiceTypes = ['radio', 'checkbox', 'select'];

                $item.find('.dgptm-choices-row').toggle(choiceTypes.indexOf(type) !== -1);
                $item.find('.dgptm-matrix-row').toggle(type === 'matrix');
                $item.find('.dgptm-number-row').toggle(type === 'number');
                $item.find('.dgptm-text-validation-row').toggle(type === 'text');

                // Update type badge
                var label = $(this).find('option:selected').text();
                $item.find('.dgptm-question-type-badge').text(label);
            });

            // Update title preview on text change
            $(document).on('input', '.dgptm-q-text', function() {
                var text = $(this).val();
                var preview = text.length > 60 ? text.substring(0, 60) + '...' : text;
                $(this).closest('.dgptm-question-item').find('.dgptm-question-title-preview').text(preview || 'Neue Frage');
            });

            // Add question
            $('#dgptm-add-question').on('click', function() {
                var type = $('#dgptm-new-question-type').val();
                var template = $('#tmpl-dgptm-question').html();
                var count = $('#dgptm-questions-list .dgptm-question-item').length + 1;

                // Find the label for the selected type
                var typeLabel = $('#dgptm-new-question-type option:selected').text();
                template = template.replace('{{number}}', count).replace('{{typeLabel}}', typeLabel);

                var $newItem = $(template);
                $newItem.find('.dgptm-q-type').val(type).trigger('change');
                $('#dgptm-questions-list').append($newItem);

                // Open the new question for editing
                $newItem.addClass('dgptm-open');
                $newItem.find('.dgptm-question-body').show();

                self.updateQuestionNumbers();
                self.notify(dgptmUmfragen.strings.questionAdded);

                // Scroll to new question
                $('html, body').animate({
                    scrollTop: $newItem.offset().top - 100
                }, 300);
            });

            // Remove question
            $(document).on('click', '.dgptm-remove-question', function(e) {
                e.stopPropagation();
                if (!confirm(dgptmUmfragen.strings.confirmDelete)) return;
                $(this).closest('.dgptm-question-item').slideUp(200, function() {
                    $(this).remove();
                    self.updateQuestionNumbers();
                });
                self.notify(dgptmUmfragen.strings.questionRemoved);
            });

            // --- Choices ---

            $(document).on('click', '.dgptm-add-choice', function() {
                var $list = $(this).siblings('.dgptm-choices-list');
                $list.append(
                    '<div class="dgptm-choice-item">' +
                    '<input type="text" class="dgptm-choice-input" value="" placeholder="Option...">' +
                    '<button type="button" class="button button-small dgptm-remove-choice">&times;</button>' +
                    '</div>'
                );
                $list.find('.dgptm-choice-input').last().focus();
            });

            $(document).on('click', '.dgptm-remove-choice', function() {
                $(this).closest('.dgptm-choice-item').remove();
            });

            // --- Matrix rows/cols ---

            $(document).on('click', '.dgptm-add-matrix-row', function() {
                var $list = $(this).siblings('.dgptm-matrix-rows-list');
                $list.append(
                    '<div class="dgptm-matrix-item">' +
                    '<input type="text" class="dgptm-matrix-row-input" value="" placeholder="Zeile...">' +
                    '<button type="button" class="button button-small dgptm-remove-matrix-row">&times;</button>' +
                    '</div>'
                );
            });

            $(document).on('click', '.dgptm-remove-matrix-row', function() {
                $(this).closest('.dgptm-matrix-item').remove();
            });

            $(document).on('click', '.dgptm-add-matrix-col', function() {
                var $list = $(this).siblings('.dgptm-matrix-cols-list');
                $list.append(
                    '<div class="dgptm-matrix-item">' +
                    '<input type="text" class="dgptm-matrix-col-input" value="" placeholder="Spalte...">' +
                    '<button type="button" class="button button-small dgptm-remove-matrix-col">&times;</button>' +
                    '</div>'
                );
            });

            $(document).on('click', '.dgptm-remove-matrix-col', function() {
                $(this).closest('.dgptm-matrix-item').remove();
            });

            // --- Skip logic ---

            $(document).on('click', '.dgptm-add-skip', function() {
                var $rules = $(this).siblings('.dgptm-skip-logic-rules');
                $rules.append(
                    '<div class="dgptm-skip-rule">' +
                    'Wenn Antwort = <input type="text" class="dgptm-skip-value" value="" placeholder="Wert">' +
                    ' &rarr; Springe zu Frage ' +
                    '<select class="dgptm-skip-goto"><option value="">-- Waehlen --</option></select>' +
                    ' <button type="button" class="button button-small dgptm-remove-skip">&times;</button>' +
                    '</div>'
                );
                self.populateSkipTargets($rules.find('.dgptm-skip-goto').last());
            });

            $(document).on('click', '.dgptm-remove-skip', function() {
                $(this).closest('.dgptm-skip-rule').remove();
            });

            // --- Save all questions ---

            $('#dgptm-save-questions').on('click', function() {
                var $btn = $(this);
                var questions = self.collectQuestions();

                if (questions.length === 0) {
                    self.notify(dgptmUmfragen.strings.noQuestions, 'error');
                    return;
                }

                $btn.prop('disabled', true).text('Speichern...');

                var surveyId = $('input[name="survey_id"]').val();
                $.post(dgptmUmfragen.ajaxUrl, {
                    action: 'dgptm_survey_save_questions',
                    nonce: dgptmUmfragen.nonce,
                    survey_id: surveyId,
                    questions: JSON.stringify(questions)
                }, function(resp) {
                    if (resp.success) {
                        self.notify(resp.data.message);
                        // Update question IDs from response
                        if (resp.data.question_ids) {
                            $('#dgptm-questions-list .dgptm-question-item').each(function(i) {
                                if (resp.data.question_ids[i]) {
                                    $(this).attr('data-question-id', resp.data.question_ids[i]);
                                }
                            });
                        }
                    } else {
                        self.notify(resp.data.message, 'error');
                    }
                    $btn.prop('disabled', false).text('Alle Fragen speichern');
                }).fail(function() {
                    self.notify(dgptmUmfragen.strings.error, 'error');
                    $btn.prop('disabled', false).text('Alle Fragen speichern');
                });
            });
        },

        // --- Sortable ---

        initSortable: function() {
            var self = this;
            if ($('#dgptm-questions-list').length) {
                $('#dgptm-questions-list').sortable({
                    handle: '.dgptm-drag-handle',
                    placeholder: 'ui-sortable-placeholder',
                    tolerance: 'pointer',
                    update: function() {
                        self.updateQuestionNumbers();
                    }
                });
            }
        },

        // --- Helpers ---

        updateQuestionNumbers: function() {
            $('#dgptm-questions-list .dgptm-question-item').each(function(i) {
                $(this).find('.dgptm-question-number').text((i + 1) + '.');
            });
            $('.dgptm-question-count').text('(' + $('#dgptm-questions-list .dgptm-question-item').length + ')');
        },

        populateSkipTargets: function($select) {
            $('#dgptm-questions-list .dgptm-question-item').each(function(i) {
                var id = $(this).attr('data-question-id');
                var text = $(this).find('.dgptm-q-text').val() || 'Frage ' + (i + 1);
                text = (i + 1) + '. ' + (text.length > 40 ? text.substring(0, 40) + '...' : text);
                $select.append($('<option>').val(id).text(text));
            });
        },

        collectQuestions: function() {
            var questions = [];

            $('#dgptm-questions-list .dgptm-question-item').each(function() {
                var $item = $(this);
                var type = $item.find('.dgptm-q-type').val();
                var q = {
                    id: $item.attr('data-question-id') || 0,
                    question_type: type,
                    question_text: $item.find('.dgptm-q-text').val(),
                    description: $item.find('.dgptm-q-description').val(),
                    group_label: $item.find('.dgptm-q-group').val(),
                    is_required: $item.find('.dgptm-q-required').is(':checked') ? 1 : 0,
                    choices: null,
                    validation_rules: null,
                    skip_logic: null
                };

                // Collect choices for choice-based types
                if (type === 'radio' || type === 'checkbox' || type === 'select') {
                    var choices = [];
                    $item.find('.dgptm-choice-input').each(function() {
                        var val = $.trim($(this).val());
                        if (val) choices.push(val);
                    });
                    if (choices.length > 0) q.choices = choices;
                }

                // Collect matrix data
                if (type === 'matrix') {
                    var rows = [], cols = [];
                    $item.find('.dgptm-matrix-row-input').each(function() {
                        var val = $.trim($(this).val());
                        if (val) rows.push(val);
                    });
                    $item.find('.dgptm-matrix-col-input').each(function() {
                        var val = $.trim($(this).val());
                        if (val) cols.push(val);
                    });
                    if (rows.length > 0 || cols.length > 0) {
                        q.choices = { rows: rows, columns: cols };
                    }
                }

                // Validation rules
                var validation = {};
                if (q.is_required) validation.required = true;

                if (type === 'number') {
                    var min = $item.find('.dgptm-q-min').val();
                    var max = $item.find('.dgptm-q-max').val();
                    if (min !== '') validation.min = parseFloat(min);
                    if (max !== '') validation.max = parseFloat(max);
                }

                if (type === 'text') {
                    var pattern = $item.find('.dgptm-q-pattern').val();
                    if (pattern) validation.pattern = pattern;
                }

                if (Object.keys(validation).length > 0) {
                    q.validation_rules = validation;
                }

                // Skip logic
                var skipRules = [];
                $item.find('.dgptm-skip-rule').each(function() {
                    var val = $.trim($(this).find('.dgptm-skip-value').val());
                    var goto_id = $(this).find('.dgptm-skip-goto').val();
                    if (val && goto_id) {
                        skipRules.push({ if_value: val, goto_question_id: parseInt(goto_id, 10) });
                    }
                });
                if (skipRules.length > 0) {
                    q.skip_logic = skipRules;
                }

                questions.push(q);
            });

            return questions;
        }
    };

    $(document).ready(function() {
        UmfragenAdmin.init();
    });

})(jQuery);
