/**
 * Frontend JavaScript - Artikel-Einreichung
 * Die Perfusiologie - Fachzeitschrift
 */

(function($) {
    'use strict';

    // Configuration from localized script
    const config = window.dgptmArtikel || {};

    /**
     * Initialize all components
     */
    function init() {
        initSubmissionForm();
        initFileUploads();
        initReviewForm();
        initRevisionForm();
        initModals();
        initTabs();
        initEditorDashboard();
    }

    /**
     * Submission Form Handler
     */
    function initSubmissionForm() {
        const $form = $('#artikel-submission-form');
        if (!$form.length) return;

        // ORCID format helper
        $form.find('#hauptautor_orcid').on('input', function() {
            let val = $(this).val().replace(/[^\dX]/gi, '');
            if (val.length > 16) val = val.substring(0, 16);
            // Auto-format with dashes
            let formatted = '';
            for (let i = 0; i < val.length; i++) {
                if (i > 0 && i % 4 === 0) formatted += '-';
                formatted += val[i];
            }
            $(this).val(formatted.toUpperCase());

            // Hide status and register hint when typing
            $('#orcid-status').removeClass('success error loading').hide();
            $('#orcid-register-hint').hide();
        });

        // ORCID Lookup
        $('#orcid-lookup-btn').on('click', function() {
            const $btn = $(this);
            const $input = $('#hauptautor_orcid');
            const $status = $('#orcid-status');
            const $registerHint = $('#orcid-register-hint');
            const orcid = $input.val().trim();

            // Validate format
            if (!orcid || !/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/.test(orcid)) {
                $status.removeClass('success loading').addClass('error')
                       .html('Bitte geben Sie eine gültige ORCID-ID ein (Format: 0000-0000-0000-0000)').show();
                $registerHint.show();
                return;
            }

            // Show loading
            $btn.prop('disabled', true).text('Wird abgerufen...');
            $status.removeClass('success error').addClass('loading')
                   .html('<span class="spinner" style="display: inline-block; width: 16px; height: 16px; border: 2px solid #7dd3fc; border-top-color: #0369a1; border-radius: 50%; animation: spin 0.8s linear infinite; vertical-align: middle; margin-right: 8px;"></span> ORCID-Daten werden abgerufen...').show();
            $registerHint.hide();

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_lookup_orcid',
                    nonce: config.nonce,
                    orcid: orcid
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('Daten abrufen');

                    if (response.success) {
                        const data = response.data.data;

                        // Fill in the form fields
                        if (data.name) {
                            $('#hauptautor').val(data.name);
                        }
                        if (data.email) {
                            $('#hauptautor_email').val(data.email);
                        }
                        if (data.institution) {
                            $('#hauptautor_institution').val(data.institution);
                        }

                        // Show success message
                        let successMsg = '<strong>&#10003; Daten erfolgreich abgerufen:</strong><br>';
                        successMsg += 'Name: ' + (data.name || '<em>nicht öffentlich</em>') + '<br>';
                        successMsg += 'E-Mail: ' + (data.email || '<em>nicht öffentlich</em>') + '<br>';
                        successMsg += 'Institution: ' + (data.institution || '<em>nicht verfügbar</em>');

                        $status.removeClass('error loading').addClass('success').html(successMsg).show();
                        $registerHint.hide();

                        // Highlight filled fields briefly
                        $('#hauptautor, #hauptautor_email, #hauptautor_institution').each(function() {
                            if ($(this).val()) {
                                $(this).css('background-color', '#dcfce7');
                                setTimeout(() => {
                                    $(this).css('background-color', '');
                                }, 2000);
                            }
                        });

                    } else {
                        // Error or partial data
                        $status.removeClass('success loading').addClass('error')
                               .html(response.data.message).show();

                        if (response.data.show_register) {
                            $registerHint.show();
                        }

                        // If partial data available, still fill what we have
                        if (response.data.partial && response.data.data) {
                            const data = response.data.data;
                            if (data.email) $('#hauptautor_email').val(data.email);
                            if (data.institution) $('#hauptautor_institution').val(data.institution);
                        }
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('Daten abrufen');
                    $status.removeClass('success loading').addClass('error')
                           .html('Verbindungsfehler. Bitte versuchen Sie es später erneut.').show();
                }
            });
        });

        // Show register hint when clicking into empty ORCID field
        $('#hauptautor_orcid').on('focus', function() {
            if (!$(this).val()) {
                $('#orcid-register-hint').slideDown(200);
            }
        });

        $form.on('submit', function(e) {
            e.preventDefault();

            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();

            // Combine highlights into hidden field
            const highlight1 = $form.find('#highlight_1').val().trim();
            const highlight2 = $form.find('#highlight_2').val().trim();
            const highlight3 = $form.find('#highlight_3').val().trim();

            if (!highlight1 || !highlight2 || !highlight3) {
                showNotice('Bitte füllen Sie alle drei Highlights aus.', 'error');
                return;
            }

            const highlightsText = '1. ' + highlight1 + '\n2. ' + highlight2 + '\n3. ' + highlight3;
            $form.find('#highlights').val(highlightsText);

            // Validate required fields
            let valid = true;
            $form.find('[required]').each(function() {
                // Skip highlight inputs as we validate them separately
                if ($(this).attr('id') && $(this).attr('id').startsWith('highlight_')) {
                    return;
                }
                if (!$(this).val()) {
                    $(this).addClass('error');
                    valid = false;
                } else {
                    $(this).removeClass('error');
                }
            });

            // Validate ORCID format if provided
            const orcid = $form.find('#hauptautor_orcid').val();
            if (orcid && !/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/.test(orcid)) {
                showNotice('Bitte geben Sie eine gültige ORCID-ID ein (Format: 0000-0000-0000-0000).', 'error');
                $form.find('#hauptautor_orcid').addClass('error');
                return;
            }

            // Check manuscript upload
            const $manuskript = $form.find('input[name="manuskript"]');
            if (!$manuskript[0].files.length) {
                showNotice('Bitte laden Sie das Manuskript hoch.', 'error');
                valid = false;
            }

            if (!valid) {
                showNotice('Bitte füllen Sie alle Pflichtfelder aus.', 'error');
                return;
            }

            // Prepare form data
            const formData = new FormData(this);
            formData.append('action', 'dgptm_submit_artikel');
            formData.append('nonce', config.nonce);

            // Submit
            $submitBtn.prop('disabled', true).html('<span class="spinner"></span> Wird eingereicht...');

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success');
                        $form[0].reset();
                        $('.file-preview').remove();

                        // Show success message with submission ID
                        $form.html(`
                            <div class="submission-success">
                                <div class="success-icon">&#10003;</div>
                                <h3>Vielen Dank für Ihre Einreichung!</h3>
                                <p>Ihre Einreichungs-ID: <strong>${response.data.submission_id}</strong></p>
                                <p>Wir werden Ihren Artikel prüfen und uns in Kürze bei Ihnen melden.</p>
                                <p>Eine Bestätigung wurde an Ihre E-Mail-Adresse gesendet.</p>
                                <a href="" class="btn btn-primary" onclick="location.reload()">Weiteren Artikel einreichen</a>
                            </div>
                        `);
                    } else {
                        showNotice(response.data.message || 'Ein Fehler ist aufgetreten.', 'error');
                        $submitBtn.prop('disabled', false).text(originalText);
                    }
                },
                error: function() {
                    showNotice('Verbindungsfehler. Bitte versuchen Sie es erneut.', 'error');
                    $submitBtn.prop('disabled', false).text(originalText);
                }
            });
        });
    }

    /**
     * File Upload Handler
     */
    function initFileUploads() {
        $('.file-upload-area').each(function() {
            const $area = $(this);
            const $input = $area.find('input[type="file"]');
            const $preview = $area.siblings('.file-preview-container');
            const multiple = $input.attr('multiple') !== undefined;

            // Click to upload
            $area.on('click', function(e) {
                if (!$(e.target).hasClass('remove-file')) {
                    $input.click();
                }
            });

            // Drag and drop
            $area.on('dragover dragenter', function(e) {
                e.preventDefault();
                $area.addClass('dragover');
            });

            $area.on('dragleave drop', function(e) {
                e.preventDefault();
                $area.removeClass('dragover');
            });

            $area.on('drop', function(e) {
                const files = e.originalEvent.dataTransfer.files;
                $input[0].files = files;
                $input.trigger('change');
            });

            // File selection
            $input.on('change', function() {
                const files = this.files;
                $preview.empty();

                if (files.length === 0) return;

                for (let i = 0; i < files.length; i++) {
                    const file = files[i];

                    // Validate file size
                    if (file.size > config.maxFileSize) {
                        showNotice(`Datei "${file.name}" ist zu groß (max. 20MB).`, 'error');
                        continue;
                    }

                    // Validate file type
                    const ext = file.name.split('.').pop().toLowerCase();
                    if (!config.allowedTypes.includes(ext)) {
                        showNotice(`Dateityp "${ext}" ist nicht erlaubt.`, 'error');
                        continue;
                    }

                    // Add preview
                    const $item = $(`
                        <div class="file-preview" data-index="${i}">
                            <span class="file-icon">&#128196;</span>
                            <span class="file-name">${file.name}</span>
                            <span class="file-size">(${formatFileSize(file.size)})</span>
                            <span class="remove-file" title="Entfernen">&times;</span>
                        </div>
                    `);

                    $preview.append($item);
                }
            });

            // Remove file
            $preview.on('click', '.remove-file', function() {
                const $item = $(this).closest('.file-preview');
                $item.remove();
                $input.val('');
            });
        });
    }

    /**
     * Review Form Handler
     */
    function initReviewForm() {
        const $form = $('#review-form');
        if (!$form.length) return;

        // Recommendation selection
        $form.on('click', '.recommendation-option', function() {
            $('.recommendation-option').removeClass('selected');
            $(this).addClass('selected');
            $(this).find('input[type="radio"]').prop('checked', true);
        });

        $form.on('submit', function(e) {
            e.preventDefault();

            const articleId = $form.data('article-id');
            const comment = $form.find('[name="comment"]').val();
            const recommendation = $form.find('[name="recommendation"]:checked').val();

            if (!comment || !recommendation) {
                showNotice('Bitte füllen Sie alle Felder aus.', 'error');
                return;
            }

            const $submitBtn = $form.find('button[type="submit"]');
            $submitBtn.prop('disabled', true).html('<span class="spinner"></span> Wird gespeichert...');

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_submit_review',
                    nonce: config.nonce,
                    article_id: articleId,
                    comment: comment,
                    recommendation: recommendation
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        showNotice(response.data.message || 'Ein Fehler ist aufgetreten.', 'error');
                        $submitBtn.prop('disabled', false).text('Gutachten absenden');
                    }
                },
                error: function() {
                    showNotice('Verbindungsfehler. Bitte versuchen Sie es erneut.', 'error');
                    $submitBtn.prop('disabled', false).text('Gutachten absenden');
                }
            });
        });
    }

    /**
     * Revision Form Handler
     * Supports both logged-in users and token-based access
     */
    function initRevisionForm() {
        const $form = $('#revision-form');
        if (!$form.length) return;

        $form.on('submit', function(e) {
            e.preventDefault();

            const articleId = $form.data('article-id');
            const useToken = $form.data('use-token') === '1' || $form.data('use-token') === 1;
            const authorToken = $form.data('author-token') || '';
            const $file = $form.find('input[name="revision_manuskript"]');
            const response = $form.find('[name="revision_response"]').val();

            if (!$file[0].files.length) {
                showNotice('Bitte laden Sie das revidierte Manuskript hoch.', 'error');
                return;
            }

            if (!response) {
                showNotice('Bitte geben Sie eine Antwort auf die Reviewer-Kommentare an.', 'error');
                return;
            }

            const formData = new FormData(this);

            // Use token-based action for non-logged-in users
            if (useToken && authorToken) {
                formData.append('action', 'dgptm_submit_revision_token');
                formData.append('author_token', authorToken);
            } else {
                formData.append('action', 'dgptm_submit_revision');
                formData.append('article_id', articleId);
            }

            formData.append('nonce', config.nonce);

            const $submitBtn = $form.find('button[type="submit"]');
            $submitBtn.prop('disabled', true).html('<span class="spinner"></span> Wird hochgeladen...');

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success');
                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        showNotice(response.data.message || 'Ein Fehler ist aufgetreten.', 'error');
                        $submitBtn.prop('disabled', false).text('Revision einreichen');
                    }
                },
                error: function() {
                    showNotice('Verbindungsfehler. Bitte versuchen Sie es erneut.', 'error');
                    $submitBtn.prop('disabled', false).text('Revision einreichen');
                }
            });
        });
    }

    /**
     * Editor Dashboard (Frontend)
     */
    function initEditorDashboard() {
        // Text Snippets storage
        let textSnippets = {};

        // Load text snippets
        function loadTextSnippets() {
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_get_text_snippets',
                    nonce: config.nonce
                },
                success: function(response) {
                    if (response.success) {
                        textSnippets = response.data.snippets || {};
                        updateSnippetDropdown();
                    }
                }
            });
        }

        // Update snippet dropdown based on category
        function updateSnippetDropdown(category) {
            const $select = $('#snippet-select');
            $select.html('<option value="">-- Textbaustein wählen --</option>');

            const categoryLabels = {
                'formal': 'Formale Prüfung',
                'review': 'Review-Feedback',
                'decision': 'Entscheidungen',
                'general': 'Allgemein'
            };

            // Group by category
            const byCategory = {};
            Object.values(textSnippets).forEach(function(snippet) {
                const cat = snippet.category || 'general';
                if (!byCategory[cat]) byCategory[cat] = [];
                byCategory[cat].push(snippet);
            });

            // Add options
            Object.keys(byCategory).forEach(function(cat) {
                if (category && category !== cat) return;

                const label = categoryLabels[cat] || cat;
                const $group = $('<optgroup>').attr('label', label);

                byCategory[cat].forEach(function(snippet) {
                    $group.append(
                        $('<option>').val(snippet.id).text(snippet.title)
                    );
                });

                $select.append($group);
            });
        }

        // Category filter change
        $(document).on('change', '#snippet-category', function() {
            updateSnippetDropdown($(this).val());
        });

        // Insert snippet
        $(document).on('click', '#insert-snippet-btn', function() {
            const snippetId = $('#snippet-select').val();
            if (!snippetId || !textSnippets[snippetId]) {
                showNotice('Bitte wählen Sie einen Textbaustein aus.', 'error');
                return;
            }

            const snippet = textSnippets[snippetId];
            const $textarea = $('#email-body');
            const currentText = $textarea.val();
            const cursorPos = $textarea[0].selectionStart;

            // Insert at cursor position
            const newText = currentText.substring(0, cursorPos) +
                           snippet.content +
                           currentText.substring(cursorPos);

            $textarea.val(newText);

            // Move cursor after inserted text
            const newCursorPos = cursorPos + snippet.content.length;
            $textarea[0].setSelectionRange(newCursorPos, newCursorPos);
            $textarea.focus();
        });

        // Email Preview
        $(document).on('click', '.preview-email-btn', function() {
            const articleId = $(this).data('article-id');
            const emailType = $(this).data('email-type') || 'status_update';
            const recipientType = $(this).data('recipient-type') || 'author';
            const reviewerId = $(this).data('reviewer-id') || 0;

            // Load snippets if not loaded
            if (Object.keys(textSnippets).length === 0) {
                loadTextSnippets();
            }

            // Load email preview
            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_preview_email',
                    nonce: config.nonce,
                    article_id: articleId,
                    email_type: emailType,
                    recipient_type: recipientType,
                    reviewer_id: reviewerId
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        $('#email-recipient').text(data.recipient_name + ' <' + data.recipient_email + '>');
                        $('#email-subject').val(data.subject);
                        $('#email-body').val(data.body || 'Sehr geehrte/r ' + data.recipient_name + ',\n\n\n\nMit freundlichen Grüßen,\nDie Redaktion\nDie Perfusiologie');
                        $('#email-article-id').val(articleId);
                        $('#email-type').val(emailType);
                        $('#email-preview-modal').data('recipient-email', data.recipient_email);
                        $('#snippet-category').val('');
                        updateSnippetDropdown();
                        openModal('email-preview-modal');
                    } else {
                        showNotice(response.data.message || 'Fehler beim Laden der E-Mail-Vorschau.', 'error');
                    }
                },
                error: function() {
                    showNotice('Verbindungsfehler.', 'error');
                }
            });
        });

        // Send Email from Preview
        $(document).on('click', '#send-email-btn', function() {
            const $btn = $(this);
            const articleId = $('#email-article-id').val();
            const recipientEmail = $('#email-preview-modal').data('recipient-email');
            const subject = $('#email-subject').val();
            const body = $('#email-body').val();
            const emailType = $('#email-type').val();

            if (!subject || !body) {
                showNotice('Bitte füllen Sie Betreff und Nachricht aus.', 'error');
                return;
            }

            $btn.prop('disabled', true).text('Wird gesendet...');

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_send_custom_email',
                    nonce: config.nonce,
                    article_id: articleId,
                    recipient_email: recipientEmail,
                    subject: subject,
                    body: body,
                    email_type: emailType
                },
                success: function(response) {
                    $btn.prop('disabled', false).text('E-Mail senden');
                    if (response.success) {
                        showNotice(response.data.message, 'success');
                        closeModal();
                    } else {
                        showNotice(response.data.message || 'Fehler beim Senden.', 'error');
                    }
                },
                error: function() {
                    $btn.prop('disabled', false).text('E-Mail senden');
                    showNotice('Verbindungsfehler.', 'error');
                }
            });
        });

        // Assign reviewer
        $(document).on('click', '.assign-reviewer-btn', function() {
            const articleId = $(this).data('article-id');
            const slot = $(this).data('slot');
            openModal('assign-reviewer-modal');
            $('#assign-reviewer-modal').data('article-id', articleId).data('slot', slot);
        });

        $(document).on('click', '#confirm-assign-reviewer', function() {
            const $modal = $('#assign-reviewer-modal');
            const articleId = $modal.data('article-id');
            const slot = $modal.data('slot');
            const reviewerId = $modal.find('select[name="reviewer_id"]').val();

            if (!reviewerId) {
                showNotice('Bitte wählen Sie einen Reviewer aus.', 'error');
                return;
            }

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_assign_reviewer',
                    nonce: config.nonce,
                    article_id: articleId,
                    reviewer_id: reviewerId,
                    slot: slot
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success');
                        closeModal();
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotice(response.data.message || 'Ein Fehler ist aufgetreten.', 'error');
                    }
                }
            });
        });

        // Editor decision
        $(document).on('click', '.decision-btn', function() {
            const articleId = $(this).data('article-id');
            const decision = $(this).data('decision');
            openModal('decision-modal');
            $('#decision-modal').data('article-id', articleId).data('decision', decision);

            // Update modal title based on decision
            const titles = {
                'accept': 'Artikel annehmen',
                'revision': 'Revision anfordern',
                'reject': 'Artikel ablehnen'
            };
            $('#decision-modal .dgptm-modal-header h3').text(titles[decision] || 'Entscheidung');
        });

        $(document).on('click', '#confirm-decision', function() {
            const $modal = $('#decision-modal');
            const articleId = $modal.data('article-id');
            const decision = $modal.data('decision');
            const letter = $modal.find('textarea[name="decision_letter"]').val();

            $.ajax({
                url: config.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'dgptm_editor_decision',
                    nonce: config.nonce,
                    article_id: articleId,
                    decision: decision,
                    decision_letter: letter
                },
                success: function(response) {
                    if (response.success) {
                        showNotice(response.data.message, 'success');
                        closeModal();
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showNotice(response.data.message || 'Ein Fehler ist aufgetreten.', 'error');
                    }
                }
            });
        });
    }

    /**
     * Modal Handlers
     */
    function initModals() {
        // Close modal on overlay click
        $(document).on('click', '.dgptm-modal-overlay', function(e) {
            if ($(e.target).hasClass('dgptm-modal-overlay')) {
                closeModal();
            }
        });

        // Close modal on close button
        $(document).on('click', '.dgptm-modal-close, .modal-cancel', function() {
            closeModal();
        });

        // Close on ESC
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
            }
        });
    }

    function openModal(modalId) {
        $('#' + modalId).addClass('active');
        $('body').css('overflow', 'hidden');
    }

    function closeModal() {
        $('.dgptm-modal-overlay').removeClass('active');
        $('body').css('overflow', '');
    }

    /**
     * Tab Navigation
     */
    function initTabs() {
        $(document).on('click', '.tab', function() {
            const target = $(this).data('tab');
            const $container = $(this).closest('.tabs-container');

            $container.find('.tab').removeClass('active');
            $(this).addClass('active');

            $container.find('.tab-content').removeClass('active');
            $container.find('[data-tab-content="' + target + '"]').addClass('active');
        });
    }

    /**
     * Show Notice
     */
    function showNotice(message, type) {
        const $notice = $(`
            <div class="dgptm-artikel-notice notice-${type}">
                <p>${message}</p>
            </div>
        `);

        // Remove existing notices
        $('.dgptm-artikel-notice').remove();

        // Add new notice at top of container
        $('.dgptm-artikel-container').prepend($notice);

        // Auto-hide after 5 seconds
        setTimeout(function() {
            $notice.fadeOut(300, function() {
                $(this).remove();
            });
        }, 5000);

        // Scroll to notice
        $('html, body').animate({
            scrollTop: $notice.offset().top - 100
        }, 300);
    }

    /**
     * Format file size
     */
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Initialize on document ready
    $(document).ready(init);

})(jQuery);
