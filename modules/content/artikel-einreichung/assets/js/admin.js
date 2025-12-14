/**
 * Admin JavaScript - Artikel-Einreichung
 * Nur für Administrator-Einstellungen
 */

(function($) {
    'use strict';

    const config = window.dgptmArtikel || {};

    function init() {
        initReviewerManagement();
    }

    /**
     * Reviewer List Management
     */
    function initReviewerManagement() {
        const $container = $('#reviewer-management');
        if (!$container.length) return;

        const $searchInput = $('#reviewer-search');
        const $results = $('#reviewer-search-results');
        const $list = $('#reviewer-list');
        let searchTimeout = null;

        // Search users
        $searchInput.on('input', function() {
            const query = $(this).val();

            clearTimeout(searchTimeout);

            if (query.length < 2) {
                $results.removeClass('active').empty();
                return;
            }

            searchTimeout = setTimeout(function() {
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'dgptm_search_users',
                        nonce: config.nonce,
                        query: query
                    },
                    success: function(response) {
                        if (response.success && response.data.users.length) {
                            $results.empty();
                            response.data.users.forEach(function(user) {
                                $results.append(`
                                    <div class="user-search-item" data-user-id="${user.ID}">
                                        <img src="${user.avatar}" alt="" class="avatar">
                                        <div>
                                            <div class="name">${user.display_name}</div>
                                            <div class="email">${user.email}</div>
                                        </div>
                                    </div>
                                `);
                            });
                            $results.addClass('active');
                        } else {
                            $results.removeClass('active').empty();
                        }
                    }
                });
            }, 300);
        });

        // Add user to list
        $results.on('click', '.user-search-item', function() {
            const userId = $(this).data('user-id');
            const userName = $(this).find('.name').text();
            const userEmail = $(this).find('.email').text();
            const userAvatar = $(this).find('.avatar').attr('src');

            // Check if already in list
            if ($list.find(`[data-user-id="${userId}"]`).length) {
                alert('Dieser Benutzer ist bereits in der Liste.');
                return;
            }

            $list.append(`
                <div class="reviewer-list-item" data-user-id="${userId}">
                    <div class="user-info">
                        <img src="${userAvatar}" alt="" class="avatar">
                        <div>
                            <div class="name">${userName}</div>
                            <div class="email">${userEmail}</div>
                        </div>
                    </div>
                    <span class="remove-btn dashicons dashicons-no-alt" title="Entfernen"></span>
                </div>
            `);

            $searchInput.val('');
            $results.removeClass('active').empty();

            saveReviewerList();
        });

        // Remove user from list
        $list.on('click', '.remove-btn', function() {
            $(this).closest('.reviewer-list-item').remove();
            saveReviewerList();
        });

        // Hide results on outside click
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.user-search-box').length) {
                $results.removeClass('active');
            }
        });

        function saveReviewerList() {
            const userIds = [];
            $list.find('.reviewer-list-item').each(function() {
                userIds.push($(this).data('user-id'));
            });

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dgptm_save_reviewer_list',
                    nonce: config.nonce,
                    reviewer_ids: userIds
                },
                success: function(response) {
                    if (response.success) {
                        // Show saved indicator
                        $('#save-indicator').text('Gespeichert').fadeIn().delay(2000).fadeOut();
                    }
                }
            });
        }
    }

    $(document).ready(init);

})(jQuery);
