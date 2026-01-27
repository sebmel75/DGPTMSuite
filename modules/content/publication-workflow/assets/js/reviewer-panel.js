/**
 * Publication Frontend Manager - Reviewer Panel JavaScript
 * Handles review form submission, reviewer management, and file uploads
 */

(function($) {
    'use strict';

    var PFMReviewerPanel = {
        /**
         * Initialize the reviewer panel
         */
        init: function() {
            this.bindEvents();
            this.initFileUploads();
        },

        /**
         * Bind all event handlers
         */
        bindEvents: function() {
            var self = this;

            // Open/close review form
            $(document).on('click', '.open-review-form', function(e) {
                e.preventDefault();
                var postId = $(this).data('post-id');
                var $container = $('#review-form-' + postId);
                var $card = $(this).closest('.publication-card');

                // Close other open forms
                $('.review-form-container').not($container).slideUp();
                $('.review-details-container').slideUp();

                $container.slideToggle();
                $card.toggleClass('form-open');
            });

            // Cancel review form
            $(document).on('click', '.cancel-review', function(e) {
                e.preventDefault();
                var $form = $(this).closest('.pfm-review-form');
                var $container = $form.closest('.review-form-container');
                var $card = $form.closest('.publication-card');

                $container.slideUp();
                $card.removeClass('form-open');
                $form[0].reset();
                $form.find('.file-name').text('');
            });

            // View review details
            $(document).on('click', '.view-review', function(e) {
                e.preventDefault();
                var commentId = $(this).data('comment-id');
                var $container = $('#review-details-' + commentId);

                // Close other details
                $('.review-details-container').not($container).slideUp();
                $('.review-form-container').slideUp();

                $container.slideToggle();
            });

            // Submit review form
            $(document).on('submit', '.pfm-review-form', function(e) {
                e.preventDefault();
                self.submitReview($(this));
            });

            // Add reviewer button
            $(document).on('click', '.add-reviewer-btn', function(e) {
                e.preventDefault();
                var postId = $(this).data('post-id');
                var $form = $('#add-reviewer-form-' + postId);
                $form.slideToggle();
            });

            // Tab switching in add reviewer form
            $(document).on('click', '.form-tabs .tab-btn', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var tab = $btn.data('tab');
                var $panel = $btn.closest('.add-reviewer-panel');

                $panel.find('.tab-btn').removeClass('active');
                $btn.addClass('active');

                $panel.find('.tab-content').removeClass('active');
                $panel.find('.tab-content[id*="' + tab + '"]').addClass('active');
            });

            // Assign existing reviewer
            $(document).on('click', '.assign-existing-reviewer', function(e) {
                e.preventDefault();
                self.assignExistingReviewer($(this));
            });

            // Create and assign new reviewer
            $(document).on('click', '.create-and-assign-reviewer', function(e) {
                e.preventDefault();
                self.createAndAssignReviewer($(this));
            });

            // Remove reviewer assignment
            $(document).on('click', '.remove-reviewer-btn', function(e) {
                e.preventDefault();
                if (confirm(pfm_reviewer_panel.i18n.confirm_remove)) {
                    self.removeReviewer($(this));
                }
            });

            // Create reviewer from management section
            $(document).on('submit', '.create-reviewer-form', function(e) {
                e.preventDefault();
                self.createReviewer($(this));
            });

            // Recommendation card selection visual feedback
            $(document).on('change', '.recommendation-options input[type="radio"]', function() {
                var $option = $(this).closest('.recommendation-option');
                $option.siblings().find('.option-card').removeClass('selected');
                $option.find('.option-card').addClass('selected');
            });

            // SharePoint download
            $(document).on('click', '.pfm-sp-download', function(e) {
                e.preventDefault();
                self.downloadSharePointFile($(this));
            });
        },

        /**
         * Initialize file upload areas with drag-drop
         */
        initFileUploads: function() {
            $(document).on('change', '.file-upload-area input[type="file"]', function() {
                var $input = $(this);
                var $label = $input.siblings('.file-upload-label');
                var fileName = this.files && this.files[0] ? this.files[0].name : '';

                $label.find('.file-name').text(fileName);
                if (fileName) {
                    $label.addClass('has-file');
                } else {
                    $label.removeClass('has-file');
                }
            });

            // Drag and drop
            $(document).on('dragover dragenter', '.file-upload-area', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).addClass('dragover');
            });

            $(document).on('dragleave drop', '.file-upload-area', function(e) {
                e.preventDefault();
                e.stopPropagation();
                $(this).removeClass('dragover');
            });

            $(document).on('drop', '.file-upload-area', function(e) {
                var files = e.originalEvent.dataTransfer.files;
                if (files.length) {
                    var $input = $(this).find('input[type="file"]');
                    $input[0].files = files;
                    $input.trigger('change');
                }
            });
        },

        /**
         * Submit review form
         */
        submitReview: function($form) {
            var self = this;
            var $submitBtn = $form.find('.submit-review');
            var $btnText = $submitBtn.find('.btn-text');
            var $btnLoading = $submitBtn.find('.btn-loading');

            // Validate recommendation
            var recommendation = $form.find('input[name="recommendation"]:checked').val();
            if (!recommendation) {
                self.showNotice($form, pfm_reviewer_panel.i18n.rating_required, 'error');
                return;
            }

            // Prepare form data
            var formData = new FormData($form[0]);
            formData.append('action', 'pfm_submit_review_enhanced');
            formData.append('nonce', pfm_reviewer_panel.nonce);

            // Show loading state
            $submitBtn.prop('disabled', true);
            $btnText.hide();
            $btnLoading.show();

            $.ajax({
                url: pfm_reviewer_panel.ajax_url,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        self.showNotice($form, pfm_reviewer_panel.i18n.submit_success, 'success');

                        // Update card to show completed status
                        var postId = $form.data('post-id');
                        var $card = $form.closest('.publication-card');

                        setTimeout(function() {
                            // Reload to show updated state
                            location.reload();
                        }, 1500);
                    } else {
                        self.showNotice($form, response.data.message || pfm_reviewer_panel.i18n.submit_error, 'error');
                    }
                },
                error: function() {
                    self.showNotice($form, pfm_reviewer_panel.i18n.submit_error, 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false);
                    $btnText.show();
                    $btnLoading.hide();
                }
            });
        },

        /**
         * Assign existing reviewer to publication
         */
        assignExistingReviewer: function($btn) {
            var self = this;
            var postId = $btn.data('post-id');
            var $panel = $btn.closest('.add-reviewer-panel');
            var reviewerId = $panel.find('.existing-reviewer-select').val();
            var deadline = $panel.find('.review-deadline').val();

            if (!reviewerId) {
                alert('Bitte wählen Sie einen Reviewer aus.');
                return;
            }

            $btn.prop('disabled', true);

            $.ajax({
                url: pfm_reviewer_panel.ajax_url,
                type: 'POST',
                data: {
                    action: 'pfm_assign_reviewer_to_publication',
                    nonce: pfm_reviewer_panel.nonce,
                    post_id: postId,
                    reviewer_id: reviewerId,
                    deadline: deadline,
                    send_invitation: true
                },
                success: function(response) {
                    if (response.success) {
                        location.reload();
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('Ein Fehler ist aufgetreten.');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Create new reviewer and assign to publication
         */
        createAndAssignReviewer: function($btn) {
            var self = this;
            var postId = $btn.data('post-id');
            var $panel = $btn.closest('.add-reviewer-panel');
            var email = $panel.find('.new-reviewer-email').val();
            var name = $panel.find('.new-reviewer-name').val();
            var sendInvitation = $panel.find('.send-invitation').is(':checked');
            var deadline = $panel.find('.review-deadline').val();

            if (!email || !name) {
                alert('Bitte füllen Sie alle Pflichtfelder aus.');
                return;
            }

            $btn.prop('disabled', true);

            // First create the reviewer
            $.ajax({
                url: pfm_reviewer_panel.ajax_url,
                type: 'POST',
                data: {
                    action: 'pfm_add_reviewer',
                    nonce: pfm_reviewer_panel.nonce,
                    email: email,
                    name: name,
                    send_welcome: sendInvitation
                },
                success: function(response) {
                    if (response.success) {
                        // Now assign to publication
                        $.ajax({
                            url: pfm_reviewer_panel.ajax_url,
                            type: 'POST',
                            data: {
                                action: 'pfm_assign_reviewer_to_publication',
                                nonce: pfm_reviewer_panel.nonce,
                                post_id: postId,
                                reviewer_id: response.data.user_id,
                                deadline: deadline,
                                send_invitation: sendInvitation
                            },
                            success: function(assignResponse) {
                                if (assignResponse.success) {
                                    location.reload();
                                } else {
                                    alert(assignResponse.data.message);
                                }
                            },
                            error: function() {
                                alert('Fehler beim Zuweisen des Reviewers.');
                            },
                            complete: function() {
                                $btn.prop('disabled', false);
                            }
                        });
                    } else {
                        alert(response.data.message);
                        $btn.prop('disabled', false);
                    }
                },
                error: function() {
                    alert('Ein Fehler ist aufgetreten.');
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Remove reviewer from publication
         */
        removeReviewer: function($btn) {
            var postId = $btn.data('post-id');
            var reviewerId = $btn.data('reviewer-id');

            $btn.prop('disabled', true);

            $.ajax({
                url: pfm_reviewer_panel.ajax_url,
                type: 'POST',
                data: {
                    action: 'pfm_remove_reviewer_assignment',
                    nonce: pfm_reviewer_panel.nonce,
                    post_id: postId,
                    reviewer_id: reviewerId
                },
                success: function(response) {
                    if (response.success) {
                        $btn.closest('.reviewer-item').fadeOut(function() {
                            $(this).remove();
                        });
                    } else {
                        alert(response.data.message);
                    }
                },
                error: function() {
                    alert('Ein Fehler ist aufgetreten.');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Create reviewer from management section
         */
        createReviewer: function($form) {
            var self = this;
            var email = $form.find('input[name="email"]').val();
            var name = $form.find('input[name="display_name"]').val();
            var sendWelcome = $form.find('input[name="send_welcome"]').is(':checked');
            var $submitBtn = $form.find('button[type="submit"]');

            $submitBtn.prop('disabled', true);

            $.ajax({
                url: pfm_reviewer_panel.ajax_url,
                type: 'POST',
                data: {
                    action: 'pfm_add_reviewer',
                    nonce: pfm_reviewer_panel.nonce,
                    email: email,
                    name: name,
                    send_welcome: sendWelcome
                },
                success: function(response) {
                    if (response.success) {
                        self.showNotice($form, pfm_reviewer_panel.i18n.reviewer_added, 'success');
                        $form[0].reset();

                        // Reload after short delay
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        self.showNotice($form, response.data.message, 'error');
                    }
                },
                error: function() {
                    self.showNotice($form, 'Ein Fehler ist aufgetreten.', 'error');
                },
                complete: function() {
                    $submitBtn.prop('disabled', false);
                }
            });
        },

        /**
         * Download file from SharePoint
         */
        downloadSharePointFile: function($btn) {
            var versionId = $btn.data('version-id');

            $btn.prop('disabled', true);

            $.ajax({
                url: pfm_reviewer_panel.ajax_url,
                type: 'POST',
                data: {
                    action: 'pfm_get_sp_download_url',
                    nonce: pfm_reviewer_panel.nonce,
                    version_id: versionId
                },
                success: function(response) {
                    if (response.success && response.data.url) {
                        window.open(response.data.url, '_blank');
                    } else {
                        alert(response.data.message || 'Download nicht verfügbar.');
                    }
                },
                error: function() {
                    alert('Fehler beim Abrufen der Download-URL.');
                },
                complete: function() {
                    $btn.prop('disabled', false);
                }
            });
        },

        /**
         * Show notice message
         */
        showNotice: function($context, message, type) {
            var $notice = $('<div class="pfm-notice pfm-notice-' + type + '">' + message + '</div>');

            // Remove existing notices
            $context.find('.pfm-notice').remove();

            // Insert notice
            if ($context.is('form')) {
                $context.prepend($notice);
            } else {
                $context.before($notice);
            }

            // Auto-hide success notices
            if (type === 'success') {
                setTimeout(function() {
                    $notice.fadeOut(function() {
                        $(this).remove();
                    });
                }, 5000);
            }
        }
    };

    // Initialize when document is ready
    $(document).ready(function() {
        PFMReviewerPanel.init();
    });

})(jQuery);
