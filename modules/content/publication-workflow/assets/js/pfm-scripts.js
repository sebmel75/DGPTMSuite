/**
 * Publication Frontend Manager - Frontend Scripts
 * Interaktive Features und UX-Verbesserungen
 */

(function($) {
    'use strict';

    $(document).ready(function() {

        // ============================================
        // FILE VERSION MANAGEMENT
        // ============================================

        // Set Current Version Button
        $('.set-current').on('click', function() {
            var postId = $(this).data('post-id');
            var version = $(this).data('version');

            if (confirm('Diese Version als aktuelle Version setzen?')) {
                $.ajax({
                    url: pfm_ajax.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'pfm_set_current_version',
                        nonce: pfm_ajax.nonce,
                        post_id: postId,
                        version: version
                    },
                    success: function(response) {
                        if (response.success) {
                            location.reload();
                        } else {
                            alert('Fehler: ' + (response.data || 'Unbekannter Fehler'));
                        }
                    },
                    error: function() {
                        alert('AJAX-Fehler. Bitte versuchen Sie es erneut.');
                    }
                });
            }
        });

        // ============================================
        // REVIEW CRITERIA - VISUAL FEEDBACK
        // ============================================

        $('.pfm-rating-buttons input[type="radio"]').on('change', function() {
            var $container = $(this).closest('.pfm-rating-buttons');
            $container.find('label').removeClass('selected');
            $(this).closest('label').addClass('selected');

            // Visual feedback
            var value = $(this).val();
            var $label = $(this).closest('label');

            if (value >= 4) {
                $label.css('background-color', '#27ae60');
            } else if (value >= 3) {
                $label.css('background-color', '#f39c12');
            } else {
                $label.css('background-color', '#e74c3c');
            }
        });

        // ============================================
        // DECISION FORM - VALIDATION
        // ============================================

        $('.pfm-decision-form').on('submit', function(e) {
            var decision = $('#decision').val();
            var comments = $('#decision_comments').val().trim();

            if (!decision) {
                e.preventDefault();
                alert('Bitte wählen Sie eine Entscheidung aus.');
                return false;
            }

            if (!comments) {
                e.preventDefault();
                alert('Bitte geben Sie ein Feedback für den Autor an.');
                return false;
            }

            if (decision === 'rejected') {
                if (!confirm('Sind Sie sicher, dass Sie diese Publikation ablehnen möchten?')) {
                    e.preventDefault();
                    return false;
                }
            }

            return true;
        });

        // ============================================
        // FILE UPLOAD - DRAG & DROP
        // ============================================

        if (window.File && window.FileList && window.FileReader) {
            var $fileInputs = $('input[type="file"]');

            $fileInputs.each(function() {
                var $input = $(this);
                var $wrapper = $('<div class="pfm-file-drop-zone"></div>');
                var $label = $('<p>Datei hierher ziehen oder klicken zum Auswählen</p>');

                $input.wrap($wrapper);
                $input.before($label);

                var $dropZone = $input.parent();

                $dropZone.on('dragover', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).addClass('drag-over');
                });

                $dropZone.on('dragleave', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).removeClass('drag-over');
                });

                $dropZone.on('drop', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    $(this).removeClass('drag-over');

                    var files = e.originalEvent.dataTransfer.files;
                    if (files.length > 0) {
                        $input[0].files = files;
                        updateFileLabel($input, files[0].name);
                    }
                });

                $input.on('change', function() {
                    if (this.files && this.files[0]) {
                        updateFileLabel($input, this.files[0].name);
                    }
                });
            });

            function updateFileLabel($input, filename) {
                $input.prev('p').html('<strong>Ausgewählt:</strong> ' + filename);
            }
        }

        // ============================================
        // CONFIRMATION DIALOGS
        // ============================================

        $('.pfm-confirm-action').on('click', function(e) {
            var message = $(this).data('confirm') || 'Sind Sie sicher?';
            if (!confirm(message)) {
                e.preventDefault();
                return false;
            }
        });

        // ============================================
        // COLLAPSIBLE SECTIONS
        // ============================================

        $('.pfm-collapsible-header').on('click', function() {
            $(this).next('.pfm-collapsible-content').slideToggle();
            $(this).toggleClass('expanded');
        });

        // ============================================
        // TOOLTIPS
        // ============================================

        $('[data-tooltip]').each(function() {
            var $elem = $(this);
            var tooltip = $elem.data('tooltip');

            $elem.on('mouseenter', function() {
                var $tooltip = $('<div class="pfm-tooltip">' + tooltip + '</div>');
                $('body').append($tooltip);

                var offset = $elem.offset();
                $tooltip.css({
                    top: offset.top - $tooltip.outerHeight() - 10,
                    left: offset.left + ($elem.outerWidth() / 2) - ($tooltip.outerWidth() / 2)
                });
            });

            $elem.on('mouseleave', function() {
                $('.pfm-tooltip').remove();
            });
        });

        // ============================================
        // AUTO-SAVE DRAFT (für längere Formulare)
        // ============================================

        var autoSaveTimer;
        var $autoSaveForms = $('.pfm-autosave-form');

        $autoSaveForms.find('input, textarea, select').on('change input', function() {
            clearTimeout(autoSaveTimer);
            autoSaveTimer = setTimeout(function() {
                saveFormDraft();
            }, 5000); // 5 Sekunden nach letzter Änderung
        });

        function saveFormDraft() {
            var $form = $('.pfm-autosave-form').first();
            if ($form.length === 0) return;

            var formData = $form.serialize();
            localStorage.setItem('pfm_form_draft_' + $form.data('post-id'), formData);
            showAutoSaveNotification();
        }

        function showAutoSaveNotification() {
            var $notification = $('<div class="pfm-autosave-notification">Entwurf gespeichert</div>');
            $('body').append($notification);
            setTimeout(function() {
                $notification.fadeOut(function() {
                    $(this).remove();
                });
            }, 2000);
        }

        // Restore draft on page load
        $autoSaveForms.each(function() {
            var $form = $(this);
            var postId = $form.data('post-id');
            var draft = localStorage.getItem('pfm_form_draft_' + postId);

            if (draft && confirm('Möchten Sie Ihren gespeicherten Entwurf wiederherstellen?')) {
                // Parse and restore form data
                var params = new URLSearchParams(draft);
                params.forEach(function(value, key) {
                    var $field = $form.find('[name="' + key + '"]');
                    if ($field.length) {
                        if ($field.is(':checkbox') || $field.is(':radio')) {
                            $field.filter('[value="' + value + '"]').prop('checked', true);
                        } else {
                            $field.val(value);
                        }
                    }
                });
            }
        });

        // ============================================
        // CHARACTER COUNTER
        // ============================================

        $('textarea[maxlength]').each(function() {
            var $textarea = $(this);
            var maxLength = $textarea.attr('maxlength');
            var $counter = $('<div class="pfm-char-counter"><span class="current">0</span> / ' + maxLength + '</div>');

            $textarea.after($counter);

            $textarea.on('input', function() {
                var currentLength = $(this).val().length;
                $counter.find('.current').text(currentLength);

                if (currentLength >= maxLength * 0.9) {
                    $counter.addClass('warning');
                } else {
                    $counter.removeClass('warning');
                }
            });

            $textarea.trigger('input');
        });

        // ============================================
        // SMOOTH SCROLLING TO SECTIONS
        // ============================================

        $('a[href^="#"]').on('click', function(e) {
            var target = $(this.getAttribute('href'));
            if (target.length) {
                e.preventDefault();
                $('html, body').stop().animate({
                    scrollTop: target.offset().top - 100
                }, 600);
            }
        });

        // ============================================
        // PRINT BUTTON
        // ============================================

        $('.pfm-print-button').on('click', function(e) {
            e.preventDefault();
            window.print();
        });

        // ============================================
        // EXPORT BUTTON
        // ============================================

        $('.pfm-export-button').on('click', function(e) {
            e.preventDefault();
            var exportType = $(this).data('export-type') || 'submissions';
            window.location.href = pfm_ajax.admin_url + 'admin-post.php?action=pfm_export_csv&type=' + exportType;
        });

        // ============================================
        // ANALYTICS CHART ANIMATIONS
        // ============================================

        if ($('.pfm-bar-chart').length) {
            $(window).on('scroll', function() {
                $('.pfm-bar-chart .bar-fill').each(function() {
                    var $bar = $(this);
                    var elementTop = $bar.offset().top;
                    var viewportBottom = $(window).scrollTop() + $(window).height();

                    if (viewportBottom > elementTop && !$bar.hasClass('animated')) {
                        $bar.addClass('animated');
                    }
                });
            }).trigger('scroll');
        }

        // ============================================
        // METRIC CARDS ANIMATION
        // ============================================

        if ($('.metric-card').length) {
            $(window).on('scroll', function() {
                $('.metric-card').each(function(index) {
                    var $card = $(this);
                    var elementTop = $card.offset().top;
                    var viewportBottom = $(window).scrollTop() + $(window).height();

                    if (viewportBottom > elementTop && !$card.hasClass('animated')) {
                        setTimeout(function() {
                            $card.addClass('animated').css({
                                animation: 'fadeInUp 0.5s ease forwards'
                            });
                        }, index * 100);
                    }
                });
            }).trigger('scroll');
        }

        // ============================================
        // REVIEW SCORE CALCULATION (Live Preview)
        // ============================================

        $('.pfm-review-criteria input[type="radio"]').on('change', function() {
            calculateOverallScore();
        });

        function calculateOverallScore() {
            var totalScore = 0;
            var totalWeight = 0;
            var criteria = {
                'methodology': 25,
                'relevance': 20,
                'clarity': 15,
                'literature': 15,
                'results': 15,
                'presentation': 10
            };

            $.each(criteria, function(key, weight) {
                var value = $('input[name="pfm_scores[' + key + ']"]:checked').val();
                if (value) {
                    totalScore += parseFloat(value) * weight;
                    totalWeight += weight;
                }
            });

            if (totalWeight > 0) {
                var overallScore = (totalScore / totalWeight).toFixed(2);
                $('#overall-score-preview').text(overallScore + ' / 5.0');
            }
        }

        // Add score preview element if it doesn't exist
        if ($('.pfm-review-criteria').length && !$('#overall-score-preview').length) {
            $('.pfm-review-criteria').append(
                '<div class="score-preview">' +
                '<strong>Vorschau Gesamtbewertung:</strong> ' +
                '<span id="overall-score-preview">-</span>' +
                '</div>'
            );
        }

        // ============================================
        // LOADING INDICATORS
        // ============================================

        $('form').on('submit', function() {
            var $submitButton = $(this).find('[type="submit"]');
            $submitButton.prop('disabled', true);
            $submitButton.append(' <span class="spinner is-active" style="float:none;margin:0 0 0 8px;"></span>');
        });

    });

})(jQuery);
