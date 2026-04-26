/**
 * DGPTM Umfragen - Frontend Editor JavaScript
 * Survey management, question builder from the frontend
 */
(function($) {
    'use strict';

    var FEEditor = {

        _bound: false,

        init: function() {
            // Only init if editor elements exist on page
            if (!$('.dgptm-fe-editor-wrap, .dgptm-fe-survey-list, .dgptm-fe-editor').length) return;

            // Bind document-level event handlers only once
            if (!this._bound) {
                this._bound = true;
                this.bindSurveyForm();
                this.bindQuestionBuilder();
                this.bindSharing();
                this.bindSurveyList();
                this.bindCopyLinks();
            }

            // These can be called multiple times safely
            this.initSortable();
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

        // --- Survey List (archive) ---
        bindSurveyList: function() {
            var self = this;

            $(document).on('click', '.dgptm-fe-archive-survey', function() {
                if (!confirm(dgptmSurveyEditor.strings.confirmArchive)) {
                    return;
                }

                var $btn = $(this);
                var surveyId = $btn.data('id');
                var $card = $btn.closest('.dgptm-fe-survey-card');

                $btn.prop('disabled', true);

                $.post(dgptmSurveyEditor.ajaxUrl, {
                    action: 'dgptm_survey_delete',
                    nonce: dgptmSurveyEditor.nonce,
                    survey_id: surveyId
                }, function(resp) {
                    if (resp.success) {
                        $card.fadeOut(300, function() { $(this).remove(); });
                        self.notify(resp.data.message);
                    } else {
                        self.notify(resp.data.message, 'error');
                        $btn.prop('disabled', false);
                    }
                }).fail(function() {
                    self.notify(dgptmSurveyEditor.strings.error, 'error');
                    $btn.prop('disabled', false);
                });
            });

            // Freigeben (Status auf 'active' setzen)
            $(document).on('click', '.dgptm-fe-publish-survey', function() {
                if (!confirm('Umfrage freigeben und veroeffentlichen?')) return;

                var $btn = $(this);
                var surveyId = $btn.data('id');
                $btn.prop('disabled', true).text('...');

                $.post(dgptmSurveyEditor.ajaxUrl, {
                    action: 'dgptm_survey_save',
                    nonce: dgptmSurveyEditor.nonce,
                    survey_id: surveyId,
                    status: 'active',
                    _only_status: '1'
                }, function(resp) {
                    if (resp.success) {
                        self.notify('Umfrage freigegeben.');
                        // Badge aktualisieren
                        var $card = $btn.closest('.dgptm-fe-survey-card');
                        $card.find('.dgptm-fe-badge').removeClass('dgptm-fe-badge-draft dgptm-fe-badge-closed').addClass('dgptm-fe-badge-active').text('Aktiv');
                        $btn.remove();
                    } else {
                        self.notify(resp.data.message || 'Fehler', 'error');
                        $btn.prop('disabled', false).text('Freigeben');
                    }
                });
            });

            // Endgueltig loeschen
            $(document).on('click', '.dgptm-fe-delete-survey', function() {
                var title = $(this).data('title') || 'diese Umfrage';
                if (!confirm('Umfrage "' + title + '" und alle Antworten endgueltig loeschen?')) return;

                var $btn = $(this);
                var surveyId = $btn.data('id');
                var $card = $btn.closest('.dgptm-fe-survey-card');
                $btn.prop('disabled', true).text('...');

                $.post(dgptmSurveyEditor.ajaxUrl, {
                    action: 'dgptm_survey_delete',
                    nonce: dgptmSurveyEditor.nonce,
                    survey_id: surveyId,
                    permanent: '1'
                }, function(resp) {
                    if (resp.success) {
                        $card.fadeOut(300, function() { $(this).remove(); });
                        self.notify('Umfrage geloescht.');
                    } else {
                        self.notify(resp.data.message || 'Fehler', 'error');
                        $btn.prop('disabled', false).text('Loeschen');
                    }
                });
            });
        },

        // --- Survey Form ---
        bindSurveyForm: function() {
            var self = this;

            $(document).on('submit', '#dgptm-fe-survey-form', function(e) {
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
                    allow_post_edit: $form.find('[name="allow_post_edit"]').is(':checked') ? 1 : 0,
                    completion_text: $form.find('[name="completion_text"]').val() || '',
                    shared_with: $form.find('[name="shared_with"]').val() || '',
                    end_date: $form.find('[name="end_date"]').val() || '',
                    expired_message: $form.find('[name="expired_message"]').val() || ''
                };

                $.post(dgptmSurveyEditor.ajaxUrl, data, function(resp) {
                    if (resp.success) {
                        self.notify(resp.data.message);
                        if (!data.survey_id || data.survey_id === '0') {
                            // Redirect to edit this new survey
                            var url = new URL(window.location.href.split('#')[0]);
                            url.searchParams.set('survey_action', 'edit');
                            url.searchParams.set('survey_id', resp.data.survey_id);
                            window.location.href = url.toString() + '#tab-umfragen';
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

        // --- Sharing ---
        bindSharing: function() {
            var self = this;
            var searchTimer = null;

            // Search users with debounce
            $(document).on('input', '#dgptm-fe-share-search', function() {
                var term = $.trim($(this).val());
                var $results = $('#dgptm-fe-share-results');

                if (searchTimer) clearTimeout(searchTimer);

                if (term.length < 2) {
                    $results.hide().empty();
                    return;
                }

                searchTimer = setTimeout(function() {
                    $.post(dgptmSurveyEditor.ajaxUrl, {
                        action: 'dgptm_survey_search_users',
                        nonce: dgptmSurveyEditor.nonce,
                        search: term
                    }, function(resp) {
                        $results.empty();
                        if (resp.success && resp.data.users.length > 0) {
                            var currentIds = ($('#dgptm-fe-shared-with').val() || '').split(',').filter(Boolean);
                            $.each(resp.data.users, function(i, u) {
                                if (currentIds.indexOf(String(u.id)) !== -1) return;
                                var $item = $('<div class="dgptm-fe-share-result-item">')
                                    .attr('data-user-id', u.id)
                                    .attr('data-display-name', u.display_name)
                                    .attr('data-email', u.user_email);
                                $item.append($('<strong>').text(u.display_name));
                                $item.append(' ');
                                $item.append($('<small>').text('(' + u.user_email + ')'));
                                $results.append($item);
                            });
                            $results.show();
                        } else {
                            $results.hide();
                        }
                    }).fail(function() {
                        $results.hide();
                    });
                }, 300);
            });

            // Click search result to add user
            $(document).on('click', '.dgptm-fe-share-result-item', function() {
                var userId = $(this).attr('data-user-id');
                var name = $(this).attr('data-display-name');
                var email = $(this).attr('data-email');

                var $hidden = $('#dgptm-fe-shared-with');
                var ids = $hidden.val() ? $hidden.val().split(',').filter(Boolean) : [];
                if (ids.indexOf(userId) === -1) {
                    ids.push(userId);
                    $hidden.val(ids.join(','));
                }

                var $badge = $('<span class="dgptm-fe-shared-user">').attr('data-user-id', userId);
                $badge.append(document.createTextNode(name + ' (' + email + ')'));
                $badge.append(' ');
                $badge.append($('<button type="button" class="dgptm-fe-remove-shared-user">').text('\u00d7'));
                $('#dgptm-fe-shared-users-list').append($badge).append(' ');

                $('#dgptm-fe-share-search').val('');
                $('#dgptm-fe-share-results').hide().empty();

                self.notify('Benutzer hinzugefuegt');
            });

            // Remove shared user
            $(document).on('click', '.dgptm-fe-remove-shared-user', function() {
                var $badge = $(this).closest('.dgptm-fe-shared-user');
                var userId = $badge.attr('data-user-id');

                var $hidden = $('#dgptm-fe-shared-with');
                var ids = $hidden.val() ? $hidden.val().split(',').filter(Boolean) : [];
                ids = ids.filter(function(id) { return id !== String(userId); });
                $hidden.val(ids.join(','));

                $badge.remove();
                self.notify('Benutzer entfernt');
            });

            // Close results on click outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.dgptm-fe-share-search-wrap').length) {
                    $('#dgptm-fe-share-results').hide();
                }
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
                $item.find('.dgptm-fe-other-section').toggle(choiceTypes.indexOf(type) !== -1);
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
            $(document).on('click', '#dgptm-fe-add-question', function() {
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
                    '<label class="dgptm-fe-choice-text-toggle" title="Textfeld bei Auswahl" style="display:inline-flex;align-items:center;gap:3px;font-size:11px;cursor:pointer;margin:0 4px;">' +
                    '<input type="checkbox" class="dgptm-fe-choice-has-text">' +
                    '<small>Text</small>' +
                    '</label>' +
                    '<label class="dgptm-fe-choice-number-toggle" title="Zahlenfeld bei Auswahl" style="display:inline-flex;align-items:center;gap:3px;font-size:11px;cursor:pointer;margin:0 4px;">' +
                    '<input type="checkbox" class="dgptm-fe-choice-has-number">' +
                    '<small>Zahl</small>' +
                    '</label>' +
                    '<input type="text" class="dgptm-fe-choice-number-label" value="Anzahl:" placeholder="Label..." style="width:80px;font-size:11px;padding:2px 4px;border:1px solid #ccc;border-radius:3px;display:none;">' +
                    '<button type="button" class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-danger dgptm-fe-remove-choice">&times;</button>' +
                    '</div>'
                );
                $list.find('.dgptm-fe-choice-input').last().focus();
            });

            // Zahlenfeld-Label ein-/ausblenden wenn Checkbox getoggelt wird
            $(document).on('change', '.dgptm-fe-choice-has-number', function() {
                $(this).closest('.dgptm-fe-choice-item').find('.dgptm-fe-choice-number-label').toggle(this.checked);
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
            $(document).on('click', '#dgptm-fe-save-questions', function() {
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
                // Validation muss VOR choices deklariert werden
                var validation = {};

                var q = {
                    id: $item.attr('data-question-id') || 0,
                    question_type: type,
                    question_text: $item.find('.dgptm-fe-q-text').val(),
                    description: $item.find('.dgptm-fe-q-description').val(),
                    group_label: $item.find('.dgptm-fe-q-group').val(),
                    is_required: $item.find('.dgptm-fe-q-required').is(':checked') ? 1 : 0,
                    is_privacy_sensitive: $item.find('.dgptm-fe-q-privacy').is(':checked') ? 1 : 0,
                    choices: null,
                    validation_rules: null,
                    skip_logic: null,
                    parent_question_id: 0,
                    parent_answer_value: ''
                };

                // Choices + per-choice text fields
                if (type === 'radio' || type === 'checkbox' || type === 'select') {
                    var choices = [];
                    var choicesWithText = [];
                    $item.find('.dgptm-fe-choice-item').each(function() {
                        var val = $.trim($(this).find('.dgptm-fe-choice-input').val());
                        if (val) {
                            choices.push(val);
                            if ($(this).find('.dgptm-fe-choice-has-text').is(':checked')) {
                                choicesWithText.push(val);
                            }
                        }
                    });
                    var choicesWithNumber = [];
                    $item.find('.dgptm-fe-choice-item').each(function() {
                        var val = $.trim($(this).find('.dgptm-fe-choice-input').val());
                        if (val && $(this).find('.dgptm-fe-choice-has-number').is(':checked')) {
                            var label = $.trim($(this).find('.dgptm-fe-choice-number-label').val()) || 'Anzahl:';
                            choicesWithNumber.push({ choice: val, label: label });
                        }
                    });

                    if (choices.length > 0) q.choices = choices;
                    if (choicesWithText.length > 0) validation.choices_with_text = choicesWithText;
                    if (choicesWithNumber.length > 0) validation.choices_with_number = choicesWithNumber;
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

                // Sonstiges-Option + Freitextfeld
                if ($item.find('.dgptm-fe-q-allow-other').is(':checked')) {
                    validation.allow_other = true;
                }
                if ($item.find('.dgptm-fe-q-free-text').is(':checked')) {
                    validation.allow_free_text = true;
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

    // === Duplizieren ===
    $(document).on('click', '.dgptm-fe-duplicate-survey', function() {
        var id = $(this).data('id');
        if (!confirm('Umfrage duplizieren?')) return;
        var $btn = $(this);
        $btn.prop('disabled', true).text('...');
        $.post(dgptmSurveyEditor.ajaxUrl, {
            action: 'dgptm_survey_duplicate',
            nonce: dgptmSurveyEditor.nonce,
            survey_id: id
        }, function(res) {
            $btn.prop('disabled', false).text('Duplizieren');
            if (res.success) {
                alert('Umfrage dupliziert: ' + (res.data.title || 'OK'));
                location.reload();
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Fehler');
            }
        });
    });

    // === Antworten anzeigen/loeschen ===
    $(document).on('click', '.dgptm-fe-show-responses', function() {
        var $btn = $(this);
        var surveyId = $btn.data('id');
        var $card = $btn.closest('.dgptm-fe-survey-card');
        var $existing = $card.find('.dgptm-fe-responses-panel');

        // Toggle: schon offen → schliessen
        if ($existing.length) {
            $existing.slideToggle(200);
            return;
        }

        $btn.prop('disabled', true).text('Lade...');

        $.post(dgptmSurveyEditor.ajaxUrl, {
            action: 'dgptm_survey_get_responses',
            nonce: dgptmSurveyEditor.nonce,
            survey_id: surveyId
        }, function(res) {
            $btn.prop('disabled', false).text('Antworten (' + $btn.data('count') + ')');
            if (!res.success) {
                alert(res.data && res.data.message ? res.data.message : 'Fehler');
                return;
            }

            var responses = res.data.responses || [];
            var html = '<div class="dgptm-fe-responses-panel" style="margin-top:10px;border-top:1px solid #eee;padding-top:10px;">';
            html += '<h4 style="font-size:13px;margin:0 0 8px;">Antworten (' + responses.length + ')</h4>';

            if (responses.length === 0) {
                html += '<p style="color:#888;font-size:12px;">Keine Antworten.</p>';
            } else {
                html += '<table style="width:100%;font-size:12px;border-collapse:collapse;">';
                html += '<tr style="border-bottom:2px solid #eee;"><th style="text-align:left;padding:4px 8px;">ID</th><th style="text-align:left;padding:4px 8px;">Teilnehmer</th><th style="text-align:left;padding:4px 8px;">Datum</th><th style="text-align:left;padding:4px 8px;">Status</th><th style="padding:4px;"></th></tr>';
                responses.forEach(function(r) {
                    var statusLabel = r.status === 'completed' ? '<span style="color:#46b450;">Abgeschlossen</span>' : '<span style="color:#f0ad4e;">' + r.status + '</span>';
                    html += '<tr data-response-id="' + r.id + '" style="border-bottom:1px solid #f0f0f0;">';
                    html += '<td style="padding:4px 8px;">#' + r.id + '</td>';
                    html += '<td style="padding:4px 8px;">' + (r.name || '<em style="color:#999;">Anonym</em>') + '</td>';
                    html += '<td style="padding:4px 8px;">' + (r.completed_at || r.started_at || '-') + '</td>';
                    html += '<td style="padding:4px 8px;">' + statusLabel + '</td>';
                    html += '<td style="padding:4px;"><button class="dgptm-fe-btn dgptm-fe-btn-small dgptm-fe-btn-danger dgptm-fe-delete-response" data-id="' + r.id + '" data-survey-id="' + surveyId + '">Loeschen</button></td>';
                    html += '</tr>';
                });
                html += '</table>';
            }
            html += '</div>';
            $card.append(html);
        });
    });

    // Einzelne Antwort loeschen
    $(document).on('click', '.dgptm-fe-delete-response', function() {
        var $btn = $(this);
        var responseId = $btn.data('id');
        var surveyId = $btn.data('survey-id');
        if (!confirm('Antwort #' + responseId + ' unwiderruflich loeschen?')) return;

        $btn.prop('disabled', true).text('...');
        $.post(dgptmSurveyEditor.ajaxUrl, {
            action: 'dgptm_survey_delete_response',
            nonce: dgptmSurveyEditor.nonce,
            response_id: responseId,
            survey_id: surveyId
        }, function(res) {
            if (res.success) {
                $btn.closest('tr').fadeOut(200, function() { $(this).remove(); });
            } else {
                alert(res.data && res.data.message ? res.data.message : 'Fehler');
                $btn.prop('disabled', false).text('Loeschen');
            }
        });
    });

    // Init on page load
    $(document).ready(function() {
        FEEditor.init();
    });

    // Re-init when loaded via AJAX dashboard tab
    $(document).on('dgptm_tab_loaded dgptm:ftab-switched', function() {
        setTimeout(function() { FEEditor.init(); }, 50);
    });

})(jQuery);
