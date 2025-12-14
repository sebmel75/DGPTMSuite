<?php
/**
 * Admin Template: Alle Einreichungen
 * Liste aller Artikel-Einreichungen
 */

if (!defined('ABSPATH')) exit;

$plugin = DGPTM_Artikel_Einreichung::get_instance();
$is_eic = $plugin->is_editor_in_chief();

// Handle single article view
$artikel_id = isset($_GET['artikel_id']) ? intval($_GET['artikel_id']) : 0;

// Filter
$status_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';

// Get articles
$args = [
    'post_type' => DGPTM_Artikel_Einreichung::POST_TYPE,
    'posts_per_page' => 50,
    'orderby' => 'date',
    'order' => 'DESC',
    'paged' => isset($_GET['paged']) ? intval($_GET['paged']) : 1
];

if ($status_filter) {
    $args['meta_query'] = [
        [
            'key' => 'artikel_status',
            'value' => $status_filter,
            'compare' => '='
        ]
    ];
}

$articles_query = new WP_Query($args);
$articles = $articles_query->posts;
?>

<div class="wrap dgptm-artikel-admin">

    <?php if ($artikel_id): ?>
        <?php
        // Single article view
        $article = get_post($artikel_id);
        if (!$article) {
            echo '<div class="notice notice-error"><p>Artikel nicht gefunden.</p></div>';
            return;
        }

        $status = get_field('artikel_status', $artikel_id);
        $submission_id = get_field('submission_id', $artikel_id);
        $reviewers = $plugin->get_reviewers();
        ?>

        <h1>
            <a href="<?php echo admin_url('admin.php?page=dgptm-artikel-list'); ?>" class="page-title-action">&larr; Zurück</a>
            Artikel: <?php echo esc_html($submission_id); ?>
        </h1>

        <div class="dgptm-admin-article-view">
            <div class="dgptm-admin-columns">
                <!-- Article Details -->
                <div class="dgptm-admin-column" style="flex: 2;">
                    <div class="dgptm-admin-box">
                        <h2><?php echo esc_html($article->post_title); ?></h2>

                        <table class="form-table">
                            <tr>
                                <th>Status</th>
                                <td>
                                    <span class="status-badge <?php echo esc_attr($plugin->get_status_class($status)); ?>">
                                        <?php echo esc_html($plugin->get_status_label($status)); ?>
                                    </span>
                                </td>
                            </tr>
                            <tr>
                                <th>Publikationsart</th>
                                <td><?php echo esc_html(DGPTM_Artikel_Einreichung::PUBLIKATIONSARTEN[get_field('publikationsart', $artikel_id)] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Korrespondenzautor</th>
                                <td>
                                    <?php echo esc_html(get_field('hauptautorin', $artikel_id)); ?><br>
                                    <small><?php echo esc_html(get_field('hauptautor_email', $artikel_id)); ?></small>
                                </td>
                            </tr>
                            <tr>
                                <th>Institution</th>
                                <td><?php echo esc_html(get_field('hauptautor_institution', $artikel_id) ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Ko-Autoren</th>
                                <td><?php echo nl2br(esc_html(get_field('autoren', $artikel_id) ?: '-')); ?></td>
                            </tr>
                            <tr>
                                <th>Eingereicht am</th>
                                <td><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime(get_field('submitted_at', $artikel_id)))); ?></td>
                            </tr>
                        </table>

                        <h3>Abstract (Deutsch)</h3>
                        <div class="abstract-box">
                            <?php echo nl2br(esc_html(get_field('abstract-deutsch', $artikel_id))); ?>
                        </div>

                        <?php if ($abstract_en = get_field('abstract', $artikel_id)): ?>
                        <h3>Abstract (English)</h3>
                        <div class="abstract-box">
                            <?php echo nl2br(esc_html($abstract_en)); ?>
                        </div>
                        <?php endif; ?>

                        <h3>Dateien</h3>
                        <ul class="file-list">
                            <?php if ($manuskript = get_field('manuskript', $artikel_id)): ?>
                            <li>
                                <a href="<?php echo esc_url($manuskript['url']); ?>" target="_blank" class="button">
                                    Manuskript: <?php echo esc_html($manuskript['filename']); ?>
                                </a>
                            </li>
                            <?php endif; ?>
                            <?php if ($revision = get_field('revision_manuskript', $artikel_id)): ?>
                            <li>
                                <a href="<?php echo esc_url($revision['url']); ?>" target="_blank" class="button">
                                    Revision: <?php echo esc_html($revision['filename']); ?>
                                </a>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

                <!-- Sidebar -->
                <div class="dgptm-admin-column" style="flex: 1;">
                    <?php if ($is_eic): ?>
                    <!-- Reviewer Assignment -->
                    <div class="dgptm-admin-box">
                        <h2>Reviewer</h2>

                        <?php for ($slot = 1; $slot <= 2; $slot++):
                            $reviewer = get_field('reviewer_' . $slot, $artikel_id);
                            $reviewer_status = get_field('reviewer_' . $slot . '_status', $artikel_id);
                            $reviewer_id = is_object($reviewer) ? $reviewer->ID : (is_array($reviewer) ? ($reviewer['ID'] ?? 0) : intval($reviewer));
                        ?>
                        <div class="reviewer-slot">
                            <h4>Reviewer <?php echo $slot; ?></h4>
                            <select class="reviewer-select" data-slot="<?php echo $slot; ?>" data-article="<?php echo $artikel_id; ?>">
                                <option value="">-- Wählen --</option>
                                <?php foreach ($reviewers as $rev): ?>
                                    <option value="<?php echo $rev->ID; ?>" <?php selected($reviewer_id, $rev->ID); ?>>
                                        <?php echo esc_html($rev->display_name); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($reviewer_status): ?>
                                <span class="reviewer-status status-<?php echo esc_attr($reviewer_status); ?>">
                                    <?php
                                    $status_labels = [
                                        'pending' => 'Ausstehend',
                                        'completed' => 'Abgeschlossen',
                                        'declined' => 'Abgelehnt'
                                    ];
                                    echo esc_html($status_labels[$reviewer_status] ?? $reviewer_status);
                                    ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($reviewer_status === 'completed'): ?>
                                <div class="reviewer-result">
                                    <strong>Empfehlung:</strong>
                                    <?php
                                    $rec = get_field('reviewer_' . $slot . '_recommendation', $artikel_id);
                                    $rec_labels = [
                                        'accept' => 'Annehmen',
                                        'minor_revision' => 'Kleinere Überarbeitung',
                                        'major_revision' => 'Größere Überarbeitung',
                                        'reject' => 'Ablehnen'
                                    ];
                                    echo esc_html($rec_labels[$rec] ?? '-');
                                    ?>
                                    <br>
                                    <a href="#" class="show-review" data-slot="<?php echo $slot; ?>">Gutachten anzeigen</a>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php endfor; ?>
                    </div>

                    <!-- Decision -->
                    <div class="dgptm-admin-box">
                        <h2>Entscheidung</h2>
                        <form id="editor-decision-form" data-article-id="<?php echo $artikel_id; ?>">
                            <p>
                                <label>
                                    <input type="radio" name="decision" value="accept"> Annehmen
                                </label><br>
                                <label>
                                    <input type="radio" name="decision" value="revision"> Revision erforderlich
                                </label><br>
                                <label>
                                    <input type="radio" name="decision" value="reject"> Ablehnen
                                </label>
                            </p>
                            <p>
                                <label for="decision_letter">Decision Letter:</label>
                                <textarea id="decision_letter" name="decision_letter" rows="6" class="large-text"><?php echo esc_textarea(get_field('decision_letter', $artikel_id)); ?></textarea>
                            </p>
                            <p>
                                <button type="submit" class="button button-primary">Entscheidung speichern</button>
                            </p>
                        </form>
                    </div>

                    <!-- Editor Notes -->
                    <div class="dgptm-admin-box">
                        <h2>Interne Notizen</h2>
                        <form id="editor-notes-form" data-article-id="<?php echo $artikel_id; ?>">
                            <textarea name="notes" rows="4" class="large-text"><?php echo esc_textarea(get_field('editor_notes', $artikel_id)); ?></textarea>
                            <p>
                                <button type="submit" class="button">Notizen speichern</button>
                            </p>
                        </form>
                    </div>
                    <?php else: ?>
                    <!-- Redaktion: Read-only view -->
                    <div class="dgptm-admin-box">
                        <h2>Review-Status</h2>
                        <p><em>Die Namen der Reviewer werden aus Gründen der Anonymität nicht angezeigt.</em></p>
                        <?php
                        $r1_status = get_field('reviewer_1_status', $artikel_id);
                        $r2_status = get_field('reviewer_2_status', $artikel_id);
                        ?>
                        <ul>
                            <li>Reviewer 1: <?php echo $r1_status ? esc_html($r1_status) : 'Nicht zugewiesen'; ?></li>
                            <li>Reviewer 2: <?php echo $r2_status ? esc_html($r2_status) : 'Nicht zugewiesen'; ?></li>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Review Modal -->
        <div id="review-modal" class="dgptm-modal" style="display: none;">
            <div class="dgptm-modal-content">
                <span class="dgptm-modal-close">&times;</span>
                <h2>Gutachten Reviewer <span id="modal-reviewer-slot"></span></h2>
                <div id="modal-review-content"></div>
            </div>
        </div>

    <?php else: ?>
        <!-- Article List -->
        <h1>Alle Einreichungen</h1>

        <!-- Filters -->
        <div class="dgptm-admin-filters">
            <form method="get" action="">
                <input type="hidden" name="page" value="dgptm-artikel-list">
                <select name="status">
                    <option value="">Alle Status</option>
                    <option value="<?php echo DGPTM_Artikel_Einreichung::STATUS_SUBMITTED; ?>" <?php selected($status_filter, DGPTM_Artikel_Einreichung::STATUS_SUBMITTED); ?>>Eingereicht</option>
                    <option value="<?php echo DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW; ?>" <?php selected($status_filter, DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW); ?>>Im Review</option>
                    <option value="<?php echo DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED; ?>" <?php selected($status_filter, DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED); ?>>Revision erforderlich</option>
                    <option value="<?php echo DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED; ?>" <?php selected($status_filter, DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED); ?>>Revision eingereicht</option>
                    <option value="<?php echo DGPTM_Artikel_Einreichung::STATUS_ACCEPTED; ?>" <?php selected($status_filter, DGPTM_Artikel_Einreichung::STATUS_ACCEPTED); ?>>Angenommen</option>
                    <option value="<?php echo DGPTM_Artikel_Einreichung::STATUS_REJECTED; ?>" <?php selected($status_filter, DGPTM_Artikel_Einreichung::STATUS_REJECTED); ?>>Abgelehnt</option>
                    <option value="<?php echo DGPTM_Artikel_Einreichung::STATUS_PUBLISHED; ?>" <?php selected($status_filter, DGPTM_Artikel_Einreichung::STATUS_PUBLISHED); ?>>Veröffentlicht</option>
                </select>
                <button type="submit" class="button">Filtern</button>
            </form>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 120px;">ID</th>
                    <th>Titel</th>
                    <th>Autor</th>
                    <th style="width: 130px;">Status</th>
                    <th style="width: 100px;">Eingereicht</th>
                    <th style="width: 100px;">Aktion</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($articles)): ?>
                    <tr>
                        <td colspan="6">Keine Einreichungen gefunden.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($articles as $article):
                        $status = get_field('artikel_status', $article->ID);
                        $submission_id = get_field('submission_id', $article->ID);
                        $submitted_at = get_field('submitted_at', $article->ID);
                        $author_name = get_field('hauptautorin', $article->ID);
                    ?>
                    <tr>
                        <td><?php echo esc_html($submission_id); ?></td>
                        <td>
                            <strong>
                                <a href="<?php echo admin_url('admin.php?page=dgptm-artikel-list&artikel_id=' . $article->ID); ?>">
                                    <?php echo esc_html($article->post_title); ?>
                                </a>
                            </strong>
                            <div class="row-actions">
                                <span class="publikationsart">
                                    <?php echo esc_html(DGPTM_Artikel_Einreichung::PUBLIKATIONSARTEN[get_field('publikationsart', $article->ID)] ?? ''); ?>
                                </span>
                            </div>
                        </td>
                        <td><?php echo esc_html($author_name); ?></td>
                        <td>
                            <span class="status-badge <?php echo esc_attr($plugin->get_status_class($status)); ?>">
                                <?php echo esc_html($plugin->get_status_label($status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($submitted_at ? date_i18n('d.m.Y', strtotime($submitted_at)) : '-'); ?></td>
                        <td>
                            <a href="<?php echo admin_url('admin.php?page=dgptm-artikel-list&artikel_id=' . $article->ID); ?>" class="button button-small">
                                Details
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>

        <?php
        // Pagination
        $total_pages = $articles_query->max_num_pages;
        if ($total_pages > 1) {
            $current_page = max(1, isset($_GET['paged']) ? intval($_GET['paged']) : 1);
            echo '<div class="tablenav bottom"><div class="tablenav-pages">';
            echo paginate_links([
                'base' => add_query_arg('paged', '%#%'),
                'format' => '',
                'current' => $current_page,
                'total' => $total_pages
            ]);
            echo '</div></div>';
        }
        ?>

    <?php endif; ?>

</div>

<script>
jQuery(document).ready(function($) {
    // Reviewer assignment
    $('.reviewer-select').on('change', function() {
        var slot = $(this).data('slot');
        var articleId = $(this).data('article');
        var reviewerId = $(this).val();

        if (!reviewerId) return;

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dgptm_assign_reviewer',
                nonce: '<?php echo wp_create_nonce(DGPTM_Artikel_Einreichung::NONCE_ACTION); ?>',
                article_id: articleId,
                reviewer_id: reviewerId,
                slot: slot
            },
            success: function(response) {
                if (response.success) {
                    alert('Reviewer zugewiesen.');
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler');
                }
            }
        });
    });

    // Editor decision
    $('#editor-decision-form').on('submit', function(e) {
        e.preventDefault();
        var articleId = $(this).data('article-id');
        var decision = $('input[name="decision"]:checked').val();
        var letter = $('#decision_letter').val();

        if (!decision) {
            alert('Bitte wählen Sie eine Entscheidung.');
            return;
        }

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dgptm_editor_decision',
                nonce: '<?php echo wp_create_nonce(DGPTM_Artikel_Einreichung::NONCE_ACTION); ?>',
                article_id: articleId,
                decision: decision,
                decision_letter: letter
            },
            success: function(response) {
                if (response.success) {
                    alert('Entscheidung gespeichert.');
                    location.reload();
                } else {
                    alert(response.data.message || 'Fehler');
                }
            }
        });
    });

    // Editor notes
    $('#editor-notes-form').on('submit', function(e) {
        e.preventDefault();
        var articleId = $(this).data('article-id');
        var notes = $(this).find('textarea').val();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'dgptm_save_editor_notes',
                nonce: '<?php echo wp_create_nonce(DGPTM_Artikel_Einreichung::NONCE_ACTION); ?>',
                article_id: articleId,
                notes: notes
            },
            success: function(response) {
                if (response.success) {
                    alert('Notizen gespeichert.');
                } else {
                    alert(response.data.message || 'Fehler');
                }
            }
        });
    });

    // Show review modal
    $('.show-review').on('click', function(e) {
        e.preventDefault();
        var slot = $(this).data('slot');
        var articleId = <?php echo $artikel_id ?: 0; ?>;

        $('#modal-reviewer-slot').text(slot);
        $('#modal-review-content').html('Wird geladen...');
        $('#review-modal').show();

        // Load review content via AJAX or from hidden fields
        <?php if ($artikel_id): ?>
        var reviews = {
            1: <?php echo json_encode([
                'recommendation' => get_field('reviewer_1_recommendation', $artikel_id),
                'comment' => get_field('reviewer_1_comment', $artikel_id)
            ]); ?>,
            2: <?php echo json_encode([
                'recommendation' => get_field('reviewer_2_recommendation', $artikel_id),
                'comment' => get_field('reviewer_2_comment', $artikel_id)
            ]); ?>
        };

        var recLabels = {
            'accept': 'Annehmen',
            'minor_revision': 'Kleinere Überarbeitung',
            'major_revision': 'Größere Überarbeitung',
            'reject': 'Ablehnen'
        };

        var review = reviews[slot];
        var html = '<p><strong>Empfehlung:</strong> ' + (recLabels[review.recommendation] || '-') + '</p>';
        html += '<p><strong>Gutachten:</strong></p>';
        html += '<div class="review-text">' + (review.comment || '-').replace(/\n/g, '<br>') + '</div>';
        $('#modal-review-content').html(html);
        <?php endif; ?>
    });

    // Close modal
    $('.dgptm-modal-close, #review-modal').on('click', function(e) {
        if (e.target === this) {
            $('#review-modal').hide();
        }
    });
});
</script>
