/**
 * DGPTM Filters JavaScript
 */
(function($) {
    'use strict';

    var filterTimeout = null;
    var currentRequest = null;

    function initFilters() {
        $('.dgptm-filter-wrapper').each(function() {
            var $filter = $(this);
            var $form = $filter.find('.dgptm-filter-form');
            var postType = $filter.data('post-type');
            var targetId = $filter.data('target');
            var $target = targetId ? $('#' + targetId) : $filter.next('.dgptm-filter-results-wrapper');

            if (!$target.length) {
                $target = $('.dgptm-filter-results-wrapper').first();
            }

            // Input change with debounce
            $form.on('input', 'input[type="text"]', function() {
                clearTimeout(filterTimeout);
                filterTimeout = setTimeout(function() {
                    performFilter($filter, $target);
                }, 400);
            });

            // Select change
            $form.on('change', 'select, input[type="checkbox"], input[type="radio"]', function() {
                performFilter($filter, $target);
            });

            // Reset
            $form.on('reset', function(e) {
                e.preventDefault();
                $form.find('input[type="text"]').val('');
                $form.find('select').val('');
                $form.find('input[type="checkbox"]').prop('checked', false);
                $form.find('input[type="radio"]').prop('checked', false);
                $form.find('.dgptm-filter-button').removeClass('is-active');
                performFilter($filter, $target);
            });

            // Button filter clicks
            $form.on('click', '.dgptm-filter-button', function(e) {
                e.preventDefault();
                var $btn = $(this);
                var isMultiple = $btn.closest('.dgptm-filter-buttons').data('multiple');

                if (isMultiple) {
                    $btn.toggleClass('is-active');
                } else {
                    $btn.siblings().removeClass('is-active');
                    $btn.addClass('is-active');
                }

                performFilter($filter, $target);
            });
        });

        // Initialize results containers
        $('.dgptm-filter-results-wrapper').each(function() {
            var $results = $(this);
            initPagination($results);
        });
    }

    function performFilter($filter, $target) {
        var $form = $filter.find('.dgptm-filter-form');
        var $loading = $filter.find('.dgptm-filter-loading');
        var $count = $filter.find('.dgptm-filter-count');
        var $container = $target.find('.dgptm-filter-results-container');

        var postType = $filter.data('post-type') || $target.data('post-type') || 'post';
        var perPage = $target.data('per-page') || 12;
        var orderby = $target.data('orderby') || 'date';
        var order = $target.data('order') || 'DESC';

        // Collect filter data
        var data = {
            action: 'dgptm_filter_posts',
            nonce: dgptmFilters.nonce,
            post_type: postType,
            per_page: perPage,
            orderby: orderby,
            order: order,
            page: 1,
            taxonomies: {},
            meta: {},
            search: ''
        };

        // Search
        data.search = $form.find('input[name="search"]').val() || '';

        // Taxonomy selects
        $form.find('select[name^="taxonomy_"]').each(function() {
            var $select = $(this);
            var taxonomy = $select.attr('name').replace('taxonomy_', '');
            var value = $select.val();
            if (value) {
                data.taxonomies[taxonomy] = [parseInt(value)];
            }
        });

        // Taxonomy checkboxes
        $form.find('.dgptm-filter-taxonomy').each(function() {
            var $field = $(this);
            var taxonomy = $field.data('taxonomy');
            var values = [];

            $field.find('input[type="checkbox"]:checked').each(function() {
                values.push(parseInt($(this).val()));
            });

            if (values.length) {
                data.taxonomies[taxonomy] = values;
            }
        });

        // Button filters
        $form.find('.dgptm-filter-buttons').each(function() {
            var $buttons = $(this);
            var taxonomy = $buttons.data('taxonomy');
            var metaKey = $buttons.data('meta-key');
            var values = [];

            $buttons.find('.dgptm-filter-button.is-active').each(function() {
                values.push($(this).data('value'));
            });

            if (values.length) {
                if (taxonomy) {
                    data.taxonomies[taxonomy] = values;
                } else if (metaKey) {
                    data.meta[metaKey] = values;
                }
            }
        });

        // Cancel previous request
        if (currentRequest) {
            currentRequest.abort();
        }

        // Show loading
        $loading.show();
        $count.hide();
        $container.addClass('is-loading');

        // Perform request
        currentRequest = $.ajax({
            url: dgptmFilters.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                $loading.hide();
                $container.removeClass('is-loading');

                if (response.success) {
                    $container.html(response.data.html);

                    // Update count
                    var countText = dgptmFilters.strings.showingResults.replace('%d', response.data.total);
                    $count.text(countText).show();

                    // Update pagination
                    updatePagination($target, response.data);

                    // Scroll to results if filtering changed
                    if ($target.length && $target.offset()) {
                        var scrollTop = $target.offset().top - 100;
                        if ($(window).scrollTop() > scrollTop) {
                            $('html, body').animate({ scrollTop: scrollTop }, 300);
                        }
                    }
                }
            },
            error: function(xhr, status) {
                $loading.hide();
                $container.removeClass('is-loading');

                if (status !== 'abort') {
                    console.error('Filter error:', status);
                }
            }
        });
    }

    function initPagination($results) {
        var $pagination = $results.find('.dgptm-filter-pagination');
        if (!$pagination.length) return;

        $pagination.on('click', '.dgptm-filter-prev', function() {
            if ($(this).prop('disabled')) return;
            var currentPage = parseInt($pagination.data('current-page'));
            loadPage($results, currentPage - 1);
        });

        $pagination.on('click', '.dgptm-filter-next', function() {
            if ($(this).prop('disabled')) return;
            var currentPage = parseInt($pagination.data('current-page'));
            loadPage($results, currentPage + 1);
        });
    }

    function loadPage($results, page) {
        var $container = $results.find('.dgptm-filter-results-container');
        var $pagination = $results.find('.dgptm-filter-pagination');

        var postType = $results.data('post-type') || 'post';
        var perPage = $results.data('per-page') || 12;
        var orderby = $results.data('orderby') || 'date';
        var order = $results.data('order') || 'DESC';

        // Get filter data from connected filter
        var $filter = $('.dgptm-filter-wrapper[data-target="' + $results.attr('id') + '"]');
        var taxonomies = {};
        var meta = {};
        var search = '';

        if ($filter.length) {
            var $form = $filter.find('.dgptm-filter-form');
            search = $form.find('input[name="search"]').val() || '';

            $form.find('select[name^="taxonomy_"]').each(function() {
                var taxonomy = $(this).attr('name').replace('taxonomy_', '');
                var value = $(this).val();
                if (value) {
                    taxonomies[taxonomy] = [parseInt(value)];
                }
            });
        }

        var data = {
            action: 'dgptm_filter_posts',
            nonce: dgptmFilters.nonce,
            post_type: postType,
            per_page: perPage,
            orderby: orderby,
            order: order,
            page: page,
            taxonomies: taxonomies,
            meta: meta,
            search: search
        };

        $container.addClass('is-loading');

        $.ajax({
            url: dgptmFilters.ajaxUrl,
            type: 'POST',
            data: data,
            success: function(response) {
                $container.removeClass('is-loading');

                if (response.success) {
                    $container.html(response.data.html);
                    updatePagination($results, response.data);

                    // Scroll to results
                    $('html, body').animate({
                        scrollTop: $results.offset().top - 100
                    }, 300);
                }
            },
            error: function() {
                $container.removeClass('is-loading');
            }
        });
    }

    function updatePagination($results, data) {
        var $pagination = $results.find('.dgptm-filter-pagination');
        if (!$pagination.length) return;

        var currentPage = data.current_page;
        var totalPages = data.pages;

        $pagination.data('current-page', currentPage);
        $pagination.data('total-pages', totalPages);

        $pagination.find('.current').text(currentPage);
        $pagination.find('.total').text(totalPages);

        $pagination.find('.dgptm-filter-prev').prop('disabled', currentPage <= 1);
        $pagination.find('.dgptm-filter-next').prop('disabled', currentPage >= totalPages);

        // Show/hide pagination
        if (totalPages <= 1) {
            $pagination.hide();
        } else {
            $pagination.show();
        }
    }

    // Initialize on document ready
    $(document).ready(function() {
        initFilters();
    });

    // Re-initialize on Elementor frontend init
    $(window).on('elementor/frontend/init', function() {
        if (typeof elementorFrontend !== 'undefined') {
            elementorFrontend.hooks.addAction('frontend/element_ready/dgptm-filter.default', function($scope) {
                initFilters();
            });
        }
    });

})(jQuery);
