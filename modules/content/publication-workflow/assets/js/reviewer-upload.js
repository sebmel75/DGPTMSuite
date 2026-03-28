/**
 * Reviewer Upload Form JavaScript
 * Publication Frontend Manager
 */

(function($) {
    'use strict';

    var PFM_Reviewer = {
        /**
         * Initialize
         */
        init: function() {
            this.form = $('#pfm-reviewer-upload-form');
            this.fileInput = this.form.find('input[type="file"]');
            this.fileWrapper = this.form.find('.file-upload-wrapper');
            this.submitButton = this.form.find('.pfm-submit-button');
            this.successMessage = $('.pfm-reviewer-success');

            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            var self = this;

            // File input change
            this.fileInput.on('change', function(e) {
                self.handleFileSelect(e.target.files);
            });

            // Drag and drop
            this.fileWrapper
                .on('dragover dragenter', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('dragover');
                })
                .on('dragleave dragend drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).removeClass('dragover');
                })
                .on('drop', function(e) {
                    var files = e.originalEvent.dataTransfer.files;
                    self.fileInput[0].files = files;
                    self.handleFileSelect(files);
                });

            // Form submit
            this.form.on('submit', function(e) {
                e.preventDefault();
                self.handleSubmit();
            });

            // Token management events (admin)
            this.bindTokenManagement();
        },

        /**
         * Handle file selection
         */
        handleFileSelect: function(files) {
            if (!files || files.length === 0) {
                return;
            }

            var file = files[0];
            var config = window.pfm_reviewer || {};
            var maxSize = config.max_file_size || (20 * 1024 * 1024);
            var allowedTypes = config.allowed_types || [
                'application/pdf',
                'application/msword',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];

            // Check file size
            if (file.size > maxSize) {
                this.showError(config.i18n.file_too_large || 'File is too large. Maximum: 20 MB');
                this.fileInput.val('');
                return;
            }

            // Check file type
            if (allowedTypes.indexOf(file.type) === -1) {
                this.showError(config.i18n.invalid_type || 'Invalid file type. Allowed: PDF, DOC, DOCX');
                this.fileInput.val('');
                return;
            }

            // Update UI
            this.fileWrapper.addClass('has-file');
            this.fileWrapper.find('.file-name').text(file.name);
        },

        /**
         * Handle form submission
         */
        handleSubmit: function() {
            var self = this;
            var config = window.pfm_reviewer || {};

            // Validate form
            if (!this.validateForm()) {
                return;
            }

            // Confirm submission
            if (!confirm(config.i18n.confirm_submit || 'Submit the review now?')) {
                return;
            }

            // Show loading state
            this.setLoading(true);

            // Create form data
            var formData = new FormData(this.form[0]);

            // Upload via AJAX
            $.ajax({
                url: config.ajax_url || ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener('progress', function(e) {
                        if (e.lengthComputable) {
                            var percent = Math.round((e.loaded / e.total) * 100);
                            self.updateProgress(percent);
                        }
                    }, false);
                    return xhr;
                },
                success: function(response) {
                    self.setLoading(false);

                    if (response.success) {
                        self.showSuccess(response.data.message);
                    } else {
                        self.showError(response.data.message || config.i18n.upload_error);
                    }
                },
                error: function(xhr, status, error) {
                    self.setLoading(false);
                    self.showError(config.i18n.upload_error || 'Upload failed. Please try again.');
                    console.error('Upload error:', error);
                }
            });
        },

        /**
         * Validate form
         */
        validateForm: function() {
            var config = window.pfm_reviewer || {};
            var isValid = true;

            // Check required fields
            this.form.find('[required]').each(function() {
                if (!$(this).val()) {
                    isValid = false;
                    $(this).addClass('error');
                } else {
                    $(this).removeClass('error');
                }
            });

            if (!isValid) {
                this.showError(config.i18n.required_fields || 'Please fill in all required fields.');
            }

            return isValid;
        },

        /**
         * Set loading state
         */
        setLoading: function(loading) {
            if (loading) {
                this.submitButton.prop('disabled', true);
                this.submitButton.find('.button-text').hide();
                this.submitButton.find('.button-loading').show();
            } else {
                this.submitButton.prop('disabled', false);
                this.submitButton.find('.button-text').show();
                this.submitButton.find('.button-loading').hide();
            }
        },

        /**
         * Update progress indicator
         */
        updateProgress: function(percent) {
            // Could add a progress bar here
            // console.log('Upload progress:', percent + '%');
        },

        /**
         * Show success message
         */
        showSuccess: function(message) {
            this.form.hide();
            this.successMessage.show();
        },

        /**
         * Show error message
         */
        showError: function(message) {
            // Remove existing error
            this.form.find('.form-error').remove();

            // Add error message
            var errorHtml = '<div class="form-error" style="color: #d63638; background: #fef7f7; padding: 12px; border-radius: 4px; margin-bottom: 20px;">' +
                '<span class="dashicons dashicons-warning" style="margin-right: 8px;"></span>' +
                message +
                '</div>';

            this.form.prepend(errorHtml);

            // Scroll to error
            $('html, body').animate({
                scrollTop: this.form.offset().top - 50
            }, 300);

            // Remove after delay
            setTimeout(function() {
                $('.form-error').fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);
        },

        /**
         * Bind token management events (admin interface)
         */
        bindTokenManagement: function() {
            var self = this;

            // Generate token form
            $(document).on('submit', '.pfm-generate-token-form', function(e) {
                e.preventDefault();
                self.generateToken($(this));
            });

            // Copy token URL
            $(document).on('click', '.pfm-copy-token', function(e) {
                e.preventDefault();
                var url = $(this).data('url');
                self.copyToClipboard(url);
                $(this).find('.dashicons').removeClass('dashicons-admin-page').addClass('dashicons-yes');
                var btn = $(this);
                setTimeout(function() {
                    btn.find('.dashicons').removeClass('dashicons-yes').addClass('dashicons-admin-page');
                }, 2000);
            });

            // Revoke token
            $(document).on('click', '.pfm-revoke-token', function(e) {
                e.preventDefault();
                if (!confirm('Token wirklich widerrufen?')) return;
                self.revokeToken($(this).data('token-id'), $(this).closest('tr'));
            });

            // Delete token
            $(document).on('click', '.pfm-delete-token', function(e) {
                e.preventDefault();
                if (!confirm('Token wirklich löschen?')) return;
                self.deleteToken($(this).data('token-id'), $(this).closest('tr'));
            });

            // SharePoint download
            $(document).on('click', '.pfm-sp-download', function(e) {
                e.preventDefault();
                self.downloadSharePointVersion($(this).data('version-id'));
            });
        },

        /**
         * Generate new token via AJAX
         */
        generateToken: function($form) {
            var publicationId = $form.data('publication-id');
            var tokenType = $form.find('[name="token_type"]').val();
            var reviewerName = $form.find('[name="reviewer_name"]').val();
            var reviewerEmail = $form.find('[name="reviewer_email"]').val();

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'pfm_generate_token',
                    publication_id: publicationId,
                    token_type: tokenType,
                    reviewer_name: reviewerName,
                    reviewer_email: reviewerEmail,
                    nonce: window.pfm_ajax ? pfm_ajax.nonce : ''
                },
                success: function(response) {
                    if (response.success) {
                        // Reload to show new token
                        location.reload();
                    } else {
                        alert(response.data.message || 'Error generating token');
                    }
                },
                error: function() {
                    alert('Error generating token');
                }
            });
        },

        /**
         * Revoke token via AJAX
         */
        revokeToken: function(tokenId, $row) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'pfm_revoke_token',
                    token_id: tokenId,
                    nonce: window.pfm_ajax ? pfm_ajax.nonce : ''
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || 'Error revoking token');
                    }
                }
            });
        },

        /**
         * Delete token via AJAX
         */
        deleteToken: function(tokenId, $row) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'pfm_delete_token',
                    token_id: tokenId,
                    nonce: window.pfm_ajax ? pfm_ajax.nonce : ''
                },
                success: function(response) {
                    if (response.success) {
                        $row.fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message || 'Error deleting token');
                    }
                }
            });
        },

        /**
         * Download SharePoint version
         */
        downloadSharePointVersion: function(versionId) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'pfm_get_sp_download_url',
                    version_id: versionId,
                    nonce: window.pfm_ajax ? pfm_ajax.nonce : ''
                },
                success: function(response) {
                    if (response.success && response.data.url) {
                        window.open(response.data.url, '_blank');
                    } else {
                        alert(response.data.message || 'Error getting download URL');
                    }
                }
            });
        },

        /**
         * Copy text to clipboard
         */
        copyToClipboard: function(text) {
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text);
            } else {
                // Fallback
                var $temp = $('<input>');
                $('body').append($temp);
                $temp.val(text).select();
                document.execCommand('copy');
                $temp.remove();
            }
        }
    };

    // Initialize on document ready
    $(document).ready(function() {
        PFM_Reviewer.init();
    });

})(jQuery);
