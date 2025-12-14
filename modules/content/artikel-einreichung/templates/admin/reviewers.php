<?php
/**
 * Admin Template: Reviewer-Verwaltung
 * Verwalten der Reviewer-Liste
 */

if (!defined('ABSPATH')) exit;

$plugin = DGPTM_Artikel_Einreichung::get_instance();

// Current reviewers
$current_reviewer_ids = get_option(DGPTM_Artikel_Einreichung::OPT_REVIEWERS, []);
$current_reviewers = [];
if (!empty($current_reviewer_ids)) {
    $current_reviewers = get_users([
        'include' => $current_reviewer_ids,
        'orderby' => 'display_name'
    ]);
}
?>

<div class="wrap dgptm-artikel-admin">
    <h1>Reviewer-Verwaltung</h1>

    <p class="description">
        Verwalten Sie hier die Liste der verfügbaren Reviewer für Artikel-Einreichungen.
        Nur Benutzer in dieser Liste können als Reviewer für Artikel zugewiesen werden.
    </p>

    <div class="dgptm-admin-columns">
        <!-- Current Reviewers -->
        <div class="dgptm-admin-column">
            <div class="dgptm-admin-box">
                <h2>Aktuelle Reviewer (<?php echo count($current_reviewers); ?>)</h2>

                <?php if (empty($current_reviewers)): ?>
                    <p class="no-items">Noch keine Reviewer hinzugefügt.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>E-Mail</th>
                                <th style="width: 100px;">Aktion</th>
                            </tr>
                        </thead>
                        <tbody id="reviewer-list">
                            <?php foreach ($current_reviewers as $reviewer): ?>
                            <tr data-user-id="<?php echo $reviewer->ID; ?>">
                                <td>
                                    <?php echo get_avatar($reviewer->ID, 32); ?>
                                    <strong><?php echo esc_html($reviewer->display_name); ?></strong>
                                </td>
                                <td><?php echo esc_html($reviewer->user_email); ?></td>
                                <td>
                                    <button type="button" class="button button-small remove-reviewer" data-user-id="<?php echo $reviewer->ID; ?>">
                                        Entfernen
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Add Reviewer -->
        <div class="dgptm-admin-column">
            <div class="dgptm-admin-box">
                <h2>Reviewer hinzufügen</h2>

                <p>
                    <label for="user-search">Benutzer suchen:</label>
                    <input type="text" id="user-search" class="regular-text" placeholder="Name oder E-Mail eingeben...">
                </p>

                <div id="search-results" style="display: none;">
                    <h4>Suchergebnisse</h4>
                    <div id="search-results-list"></div>
                </div>

                <hr>

                <h3>Alle Benutzer</h3>
                <p class="description">Wählen Sie einen Benutzer aus der Liste aus.</p>

                <?php
                $all_users = get_users([
                    'orderby' => 'display_name',
                    'number' => 100
                ]);
                ?>

                <select id="add-reviewer-select" class="regular-text">
                    <option value="">-- Benutzer wählen --</option>
                    <?php foreach ($all_users as $user):
                        if (in_array($user->ID, $current_reviewer_ids)) continue;
                    ?>
                        <option value="<?php echo $user->ID; ?>">
                            <?php echo esc_html($user->display_name); ?> (<?php echo esc_html($user->user_email); ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="button" id="add-reviewer-btn" class="button button-primary">Hinzufügen</button>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var reviewerIds = <?php echo json_encode($current_reviewer_ids); ?>;

    // Save reviewer list
    function saveReviewerList() {
        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dgptm_save_reviewer_list',
                nonce: '<?php echo wp_create_nonce(DGPTM_Artikel_Einreichung::NONCE_ACTION); ?>',
                reviewer_ids: reviewerIds
            },
            success: function(response) {
                if (!response.success) {
                    alert(response.data.message || 'Fehler beim Speichern');
                }
            }
        });
    }

    // Add reviewer
    $('#add-reviewer-btn').on('click', function() {
        var userId = parseInt($('#add-reviewer-select').val());
        if (!userId) {
            alert('Bitte wählen Sie einen Benutzer aus.');
            return;
        }

        if (reviewerIds.indexOf(userId) !== -1) {
            alert('Dieser Benutzer ist bereits ein Reviewer.');
            return;
        }

        reviewerIds.push(userId);
        saveReviewerList();

        // Reload page to refresh lists
        location.reload();
    });

    // Remove reviewer
    $(document).on('click', '.remove-reviewer', function() {
        var userId = parseInt($(this).data('user-id'));

        if (!confirm('Reviewer wirklich entfernen?')) {
            return;
        }

        reviewerIds = reviewerIds.filter(function(id) {
            return id !== userId;
        });

        saveReviewerList();

        // Remove from table
        $(this).closest('tr').fadeOut(function() {
            $(this).remove();
        });
    });

    // Search users
    var searchTimeout;
    $('#user-search').on('input', function() {
        var search = $(this).val();

        clearTimeout(searchTimeout);

        if (search.length < 2) {
            $('#search-results').hide();
            return;
        }

        searchTimeout = setTimeout(function() {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'dgptm_search_users',
                    nonce: '<?php echo wp_create_nonce(DGPTM_Artikel_Einreichung::NONCE_ACTION); ?>',
                    search: search
                },
                success: function(response) {
                    if (response.success && response.data.users.length > 0) {
                        var html = '<ul class="search-result-list">';
                        response.data.users.forEach(function(user) {
                            var isReviewer = reviewerIds.indexOf(user.id) !== -1;
                            html += '<li>';
                            html += '<strong>' + user.name + '</strong> (' + user.email + ')';
                            if (isReviewer) {
                                html += ' <span class="already-reviewer">(bereits Reviewer)</span>';
                            } else {
                                html += ' <button type="button" class="button button-small add-from-search" data-user-id="' + user.id + '">Hinzufügen</button>';
                            }
                            html += '</li>';
                        });
                        html += '</ul>';

                        $('#search-results-list').html(html);
                        $('#search-results').show();
                    } else {
                        $('#search-results-list').html('<p>Keine Benutzer gefunden.</p>');
                        $('#search-results').show();
                    }
                }
            });
        }, 300);
    });

    // Add from search results
    $(document).on('click', '.add-from-search', function() {
        var userId = parseInt($(this).data('user-id'));

        reviewerIds.push(userId);
        saveReviewerList();

        // Reload page to refresh lists
        location.reload();
    });
});
</script>

<style>
.search-result-list {
    list-style: none;
    padding: 0;
    margin: 0;
}
.search-result-list li {
    padding: 10px;
    border-bottom: 1px solid #ddd;
}
.search-result-list li:last-child {
    border-bottom: none;
}
.already-reviewer {
    color: #666;
    font-style: italic;
}
#reviewer-list td {
    vertical-align: middle;
}
#reviewer-list img {
    vertical-align: middle;
    margin-right: 10px;
    border-radius: 50%;
}
</style>
