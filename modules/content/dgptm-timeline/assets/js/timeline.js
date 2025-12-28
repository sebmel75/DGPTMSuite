/**
 * DGPTM Timeline JavaScript
 */
(function($) {
    'use strict';

    // Animate entries on scroll
    function initTimelineAnimation() {
        var $entries = $('.dgptm-timeline-entry');

        if (!$entries.length) {
            return;
        }

        // Check if IntersectionObserver is supported
        if ('IntersectionObserver' in window) {
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        $(entry.target).addClass('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.2
            });

            $entries.each(function() {
                observer.observe(this);
            });
        } else {
            // Fallback: show all entries immediately
            $entries.addClass('is-visible');
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        initTimelineAnimation();
    });

    // Re-initialize on Elementor frontend init (for live preview)
    $(window).on('elementor/frontend/init', function() {
        if (typeof elementorFrontend !== 'undefined') {
            elementorFrontend.hooks.addAction('frontend/element_ready/dgptm-timeline.default', function($scope) {
                initTimelineAnimation();
            });
        }
    });

})(jQuery);
