/**
 * DGPTM AJAX Search JavaScript
 */
(function($) {
    'use strict';

    var searchTimeout = null;
    var currentRequest = null;

    function initSearch() {
        $('.dgptm-search-wrapper').each(function() {
            var $wrapper = $(this);
            var $form = $wrapper.find('.dgptm-search-form');
            var $input = $wrapper.find('.dgptm-search-input');
            var $results = $wrapper.find('.dgptm-search-results');
            var $icon = $wrapper.find('.dgptm-search-icon');
            var $spinner = $wrapper.find('.dgptm-search-spinner');

            // Get settings from data attributes
            var postTypes = ($form.data('post-types') || 'post,page').split(',');
            var limit = parseInt($form.data('limit')) || 8;
            var showExcerpt = $form.data('show-excerpt') !== 'false';
            var showThumbnail = $form.data('show-thumbnail') !== 'false';
            var showTypeBadge = $form.data('show-type-badge') !== 'false';

            // Input event with debounce
            $input.on('input', function() {
                var query = $(this).val().trim();

                // Clear previous timeout
                if (searchTimeout) {
                    clearTimeout(searchTimeout);
                }

                // Hide results if query too short
                if (query.length < dgptmSearch.minChars) {
                    hideResults($results);
                    return;
                }

                // Debounce search
                searchTimeout = setTimeout(function() {
                    performSearch(query, postTypes, limit, showExcerpt, showThumbnail, showTypeBadge, $wrapper);
                }, 300);
            });

            // Form submit - go to search page
            $form.on('submit', function(e) {
                e.preventDefault();
                var query = $input.val().trim();
                if (query.length >= dgptmSearch.minChars) {
                    window.location.href = '/?s=' + encodeURIComponent(query);
                }
            });

            // Close results on click outside
            $(document).on('click', function(e) {
                if (!$wrapper.is(e.target) && $wrapper.has(e.target).length === 0) {
                    hideResults($results);
                }
            });

            // Keyboard navigation
            $input.on('keydown', function(e) {
                var $items = $results.find('.dgptm-search-result-item');
                var $focused = $items.filter('.is-focused');
                var index = $items.index($focused);

                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (index < $items.length - 1) {
                        $items.removeClass('is-focused');
                        $items.eq(index + 1).addClass('is-focused');
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (index > 0) {
                        $items.removeClass('is-focused');
                        $items.eq(index - 1).addClass('is-focused');
                    }
                } else if (e.key === 'Enter') {
                    if ($focused.length) {
                        e.preventDefault();
                        window.location.href = $focused.attr('href');
                    }
                } else if (e.key === 'Escape') {
                    hideResults($results);
                    $input.blur();
                }
            });

            // Focus input on wrapper click
            $wrapper.on('click', function(e) {
                if ($(e.target).is($wrapper)) {
                    $input.focus();
                }
            });
        });
    }

    function performSearch(query, postTypes, limit, showExcerpt, showThumbnail, showTypeBadge, $wrapper) {
        var $results = $wrapper.find('.dgptm-search-results');
        var $icon = $wrapper.find('.dgptm-search-icon');
        var $spinner = $wrapper.find('.dgptm-search-spinner');

        // Cancel previous request
        if (currentRequest) {
            currentRequest.abort();
        }

        // Show loading
        $icon.hide();
        $spinner.show();
        showResults($results);
        $results.html('<div class="dgptm-search-loading">' + dgptmSearch.strings.searching + '</div>');

        // Perform AJAX request
        currentRequest = $.ajax({
            url: dgptmSearch.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_ajax_search',
                nonce: dgptmSearch.nonce,
                query: query,
                post_types: postTypes,
                limit: limit,
                show_excerpt: showExcerpt,
                show_thumbnail: showThumbnail
            },
            success: function(response) {
                $icon.show();
                $spinner.hide();

                if (response.success && response.data.results.length > 0) {
                    renderResults(response.data, showExcerpt, showThumbnail, showTypeBadge, query, $results);
                } else {
                    renderNoResults($results);
                }
            },
            error: function(xhr, status, error) {
                $icon.show();
                $spinner.hide();

                if (status !== 'abort') {
                    $results.html('<div class="dgptm-search-no-results">' + dgptmSearch.strings.error + '</div>');
                }
            }
        });
    }

    function renderResults(data, showExcerpt, showThumbnail, showTypeBadge, query, $results) {
        var html = '';

        data.results.forEach(function(result) {
            html += '<a href="' + escapeHtml(result.url) + '" class="dgptm-search-result-item">';

            if (showThumbnail && result.thumbnail) {
                html += '<div class="dgptm-search-result-thumbnail">';
                html += '<img src="' + escapeHtml(result.thumbnail) + '" alt="">';
                html += '</div>';
            }

            html += '<div class="dgptm-search-result-content">';
            html += '<h4 class="dgptm-search-result-title">' + highlightText(result.title, query) + '</h4>';

            if (showExcerpt && result.excerpt) {
                html += '<p class="dgptm-search-result-excerpt">' + highlightText(result.excerpt, query) + '</p>';
            }

            if (showTypeBadge) {
                html += '<div class="dgptm-search-result-meta">';
                html += '<span class="dgptm-search-type-badge">' + escapeHtml(result.post_type_label) + '</span>';
                html += '</div>';
            }

            html += '</div>';
            html += '</a>';
        });

        // View all link if more results
        if (data.total > data.results.length) {
            html += '<a href="' + escapeHtml(data.search_url) + '" class="dgptm-search-view-all">';
            html += dgptmSearch.strings.viewAll + ' (' + data.total + ')';
            html += '</a>';
        }

        $results.html(html);
    }

    function renderNoResults($results) {
        var html = '<div class="dgptm-search-no-results">';
        html += '<div class="dgptm-search-no-results-icon">🔍</div>';
        html += '<p>' + dgptmSearch.strings.noResults + '</p>';
        html += '</div>';
        $results.html(html);
    }

    function showResults($results) {
        $results.slideDown(150);
    }

    function hideResults($results) {
        $results.slideUp(150);
    }

    function highlightText(text, query) {
        if (!query || !text) return escapeHtml(text);

        var escaped = escapeHtml(text);
        var regex = new RegExp('(' + escapeRegex(query) + ')', 'gi');
        return escaped.replace(regex, '<span class="dgptm-search-highlight">$1</span>');
    }

    function escapeHtml(text) {
        if (!text) return '';
        var div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    function escapeRegex(string) {
        return string.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    }

    // Initialize on document ready
    $(document).ready(function() {
        initSearch();
    });

    // Re-initialize on Elementor frontend init
    $(window).on('elementor/frontend/init', function() {
        if (typeof elementorFrontend !== 'undefined') {
            elementorFrontend.hooks.addAction('frontend/element_ready/dgptm-ajax-search.default', function($scope) {
                initSearch();
            });
        }
    });

})(jQuery);
