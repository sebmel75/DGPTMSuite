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
                                var source = user.source || 'wp';
                                var badgeClass = source === 'crm' ? 'ae-source-crm' : 'ae-source-wp';
                                var badgeLabel = source === 'crm' ? 'CRM' : 'WP';
                                var firstName = user.first_name || '';
                                var lastName = user.last_name || '';
                                var zohoId = user.zoho_id || '';
                                $results.append(
                                    '<div class="user-search-item"' +
                                    ' data-user-id="' + user.ID + '"' +
                                    ' data-email="' + user.email + '"' +
                                    ' data-first-name="' + firstName + '"' +
                                    ' data-last-name="' + lastName + '"' +
                                    ' data-zoho-id="' + zohoId + '"' +
                                    ' data-source="' + source + '">' +
                                    '<img src="' + user.avatar + '" alt="" class="avatar">' +
                                    '<div>' +
                                    '<div class="name">' + user.display_name +
                                    ' <span class="ae-source-badge ' + badgeClass + '">' + badgeLabel + '</span>' +
                                    '</div>' +
                                    '<div class="email">' + user.email + '</div>' +
                                    '</div>' +
                                    '</div>'
                                );
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
            const $item = $(this);
            const userId = $item.data('user-id');
            const userName = $item.find('.name').text().trim();
            const userEmail = $item.data('email') || $item.find('.email').text();
            const userAvatar = $item.find('.avatar').attr('src');
            const firstName = $item.data('first-name') || '';
            const lastName = $item.data('last-name') || '';
            const zohoId = $item.data('zoho-id') || '';
            const source = $item.data('source') || 'wp';

            // Check if already in list
            if ($list.find('[data-user-id="' + userId + '"]').length) {
                alert('Dieser Benutzer ist bereits in der Liste.');
                return;
            }

            $list.append(
                '<div class="reviewer-list-item" data-user-id="' + userId + '"' +
                ' data-email="' + userEmail + '"' +
                ' data-first-name="' + firstName + '"' +
                ' data-last-name="' + lastName + '"' +
                ' data-zoho-id="' + zohoId + '"' +
                ' data-source="' + source + '">' +
                '<div class="user-info">' +
                '<img src="' + userAvatar + '" alt="" class="avatar">' +
                '<div>' +
                '<div class="name">' + userName + '</div>' +
                '<div class="email">' + userEmail + '</div>' +
                '</div>' +
                '</div>' +
                '<span class="remove-btn dashicons dashicons-no-alt" title="Entfernen"></span>' +
                '</div>'
            );

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
            const reviewers = [];
            $list.find('.reviewer-list-item').each(function() {
                const $row = $(this);
                reviewers.push({
                    user_id: $row.data('user-id'),
                    email: $row.data('email') || '',
                    first_name: $row.data('first-name') || '',
                    last_name: $row.data('last-name') || '',
                    zoho_id: $row.data('zoho-id') || '',
                    source: $row.data('source') || 'wp'
                });
            });

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dgptm_save_reviewer_list',
                    nonce: config.nonce,
                    reviewer_ids: reviewers.map(function(r) { return r.user_id; }),
                    reviewers: reviewers
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

    // Reviewer aktiv/inaktiv Toggle
    $(document).on('click', '.btn-toggle-reviewer', function(e) {
        e.preventDefault();
        var $btn = $(this);
        var userId = $btn.data('user-id');

        $.post(config.ajaxUrl || ajaxurl, {
            action: 'dgptm_toggle_reviewer_active',
            nonce: config.nonce,
            user_id: userId
        }, function(res) {
            if (res.success) {
                var $row = $btn.closest('.reviewer-item, tr, .search-result-item');
                if (res.data.active) {
                    $row.removeClass('reviewer-inactive');
                    $btn.text('Deaktivieren');
                } else {
                    $row.addClass('reviewer-inactive');
                    $btn.text('Aktivieren');
                }
            }
        });
    });

    $(document).ready(init);

})(jQuery);
