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

        $form.on('submit', function(e) {
            e.preventDefault();

            const $submitBtn = $form.find('button[type="submit"]');
            const originalText = $submitBtn.text();

            // Validate required fields
            let valid = true;
            $form.find('[required]').each(function() {
                if (!$(this).val()) {
                    $(this).addClass('error');
                    valid = false;
                } else {
                    $(this).removeClass('error');
                }
            });

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
     */
    function initRevisionForm() {
        const $form = $('#revision-form');
        if (!$form.length) return;

        $form.on('submit', function(e) {
            e.preventDefault();

            const articleId = $form.data('article-id');
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
            formData.append('action', 'dgptm_submit_revision');
            formData.append('nonce', config.nonce);
            formData.append('article_id', articleId);

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
