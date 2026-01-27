/**
 * Reviewer Upload JavaScript
 * DGPTM Artikel-Einreichung
 */

(function($) {
    'use strict';

    var DGPTMReviewerUpload = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.initFileUpload();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // Form submission
            $(document).on('submit', '#dgptm-reviewer-upload-form', function(e) {
                e.preventDefault();
                self.handleSubmit($(this));
            });
        },

        /**
         * Initialize file upload with drag-drop
         */
        initFileUpload: function() {
            var $wrapper = $('.file-upload-wrapper');

            // File input change
            $(document).on('change', '.file-upload-wrapper input[type="file"]', function() {
                var $input = $(this);
                var $parent = $input.closest('.file-upload-wrapper');
                var $label = $parent.find('.file-upload-info');
                var fileName = this.files && this.files[0] ? this.files[0].name : '';

                $label.find('.file-name').text(fileName);

                if (fileName) {
                    $parent.addClass('has-file');
                } else {
                    $parent.removeClass('has-file');
                }
            });

            // Drag events
            $(document).on('dragover dragenter', '.file-upload-wrapper', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });

            $(document).on('dragleave drop', '.file-upload-wrapper', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });

            $(document).on('drop', '.file-upload-wrapper', function(e) {
                var files = e.originalEvent.dataTransfer.files;
                if (files.length) {
                    var $input = $(this).find('input[type="file"]');
                    $input[0].files = files;
                    $input.trigger('change');
                }
            });
        },

        /**
         * Handle form submission
         */
        handleSubmit: function($form) {
            var self = this;
            var $submitBtn = $form.find('.dgptm-submit-button');
            var $btnText = $submitBtn.find('.button-text');
            var $btnLoading = $submitBtn.find('.button-loading');

            // Validate
            var reviewerName = $form.find('input[name="reviewer_name"]').val();
            var fileInput = $form.find('input[name="file"]')[0];

            if (!reviewerName && !$form.find('input[name="reviewer_name"]').prop('readonly')) {
                alert(dgptm_reviewer.i18n.name_required);
                return;
            }

            if (!fileInput.files || !fileInput.files[0]) {
                alert(dgptm_reviewer.i18n.file_required);
                return;
            }

            // Prepare form data
            var formData = new FormData($form[0]);
            formData.append('action', 'dgptm_reviewer_upload');
            formData.append('nonce', dgptm_reviewer.nonce);

            // Show loading state
            $submitBtn.prop('disabled', true);
            $btnText.hide();
            $btnLoading.show();

            $.ajax({
                url: dgptm_reviewer.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.showSuccess($form);
                    } else {
                        self.showError($form, response.data.message || dgptm_reviewer.i18n.error);
                    }
                },
                error: function() {
                    self.showError($form, dgptm_reviewer.i18n.error);
                },
                complete: function() {
                    $submitBtn.prop('disabled', false);
                    $btnText.show();
                    $btnLoading.hide();
                }
            });
        },

        /**
         * Show success message
         */
        showSuccess: function($form) {
            var $container = $form.closest('.dgptm-reviewer-upload');
            var $success = $container.find('.dgptm-reviewer-success');
            var $formSection = $container.find('.dgptm-reviewer-form-section');

            $formSection.slideUp(300, function() {
                $success.slideDown(300);
            });
        },

        /**
         * Show error message
         */
        showError: function($form, message) {
            var $existingError = $form.find('.dgptm-form-error');
            $existingError.remove();

            var $error = $('<div class="dgptm-form-error" style="color:#d63638;padding:10px;margin-bottom:20px;background:#fef7f1;border-radius:4px;">' + message + '</div>');
            $form.prepend($error);

            // Remove after 5 seconds
            setTimeout(function() {
                $error.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        DGPTMReviewerUpload.init();
    });

})(jQuery);
