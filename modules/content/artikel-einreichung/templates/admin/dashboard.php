<?php
/**
 * Admin Template: Dashboard
 * Übersicht für Editor in Chief im WordPress-Backend
 */

if (!defined('ABSPATH')) exit;

$plugin = DGPTM_Artikel_Einreichung::get_instance();

// Get statistics
$all_articles = get_posts([
    'post_type' => DGPTM_Artikel_Einreichung::POST_TYPE,
    'posts_per_page' => -1,
    'post_status' => 'publish'
]);

$stats = [
    'total' => count($all_articles),
    'submitted' => 0,
    'in_review' => 0,
    'revision_required' => 0,
    'revision_submitted' => 0,
    'accepted' => 0,
    'rejected' => 0,
    'published' => 0
];

foreach ($all_articles as $article) {
    $status = get_field('artikel_status', $article->ID);
    switch ($status) {
        case DGPTM_Artikel_Einreichung::STATUS_SUBMITTED:
            $stats['submitted']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW:
            $stats['in_review']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED:
            $stats['revision_required']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED:
            $stats['revision_submitted']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_ACCEPTED:
            $stats['accepted']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_REJECTED:
            $stats['rejected']++;
            break;
        case DGPTM_Artikel_Einreichung::STATUS_PUBLISHED:
            $stats['published']++;
            break;
    }
}

// Recent submissions
$recent = get_posts([
    'post_type' => DGPTM_Artikel_Einreichung::POST_TYPE,
    'posts_per_page' => 10,
    'orderby' => 'date',
    'order' => 'DESC'
]);

// Pending reviews
$pending_action = get_posts([
    'post_type' => DGPTM_Artikel_Einreichung::POST_TYPE,
    'posts_per_page' => -1,
    'meta_query' => [
        'relation' => 'OR',
        [
            'key' => 'artikel_status',
            'value' => DGPTM_Artikel_Einreichung::STATUS_SUBMITTED,
            'compare' => '='
        ],
        [
            'key' => 'artikel_status',
            'value' => DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED,
            'compare' => '='
        ]
    ]
]);
?>

<div class="wrap dgptm-artikel-admin">
    <h1>Die Perfusiologie - Artikel-Dashboard</h1>

    <!-- Statistics Cards -->
    <div class="dgptm-admin-stats">
        <div class="stat-card">
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Gesamt</div>
        </div>
        <div class="stat-card stat-new">
            <div class="stat-number"><?php echo $stats['submitted']; ?></div>
            <div class="stat-label">Neu eingereicht</div>
        </div>
        <div class="stat-card stat-review">
            <div class="stat-number"><?php echo $stats['in_review']; ?></div>
            <div class="stat-label">Im Review</div>
        </div>
        <div class="stat-card stat-revision">
            <div class="stat-number"><?php echo $stats['revision_submitted']; ?></div>
            <div class="stat-label">Revision eingereicht</div>
        </div>
        <div class="stat-card stat-accepted">
            <div class="stat-number"><?php echo $stats['accepted']; ?></div>
            <div class="stat-label">Angenommen</div>
        </div>
        <div class="stat-card stat-published">
            <div class="stat-number"><?php echo $stats['published']; ?></div>
            <div class="stat-label">Veröffentlicht</div>
        </div>
    </div>

    <div class="dgptm-admin-columns">
        <!-- Pending Action -->
        <div class="dgptm-admin-column">
            <div class="dgptm-admin-box">
                <h2>Aktion erforderlich (<?php echo count($pending_action); ?>)</h2>
                <?php if (empty($pending_action)): ?>
                    <p class="no-items">Keine Einreichungen erfordern aktuell eine Aktion.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Titel</th>
                                <th>Status</th>
                                <th>Aktion</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pending_action as $article):
                                $status = get_field('artikel_status', $article->ID);
                                $submission_id = get_field('submission_id', $article->ID);
                            ?>
                            <tr>
                                <td><?php echo esc_html($submission_id); ?></td>
                                <td>
                                    <strong><?php echo esc_html(wp_trim_words($article->post_title, 6)); ?></strong>
                                </td>
                                <td>
                                    <span class="status-badge <?php echo esc_attr($plugin->get_status_class($status)); ?>">
                                        <?php echo esc_html($plugin->get_status_label($status)); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=dgptm-artikel-list&artikel_id=' . $article->ID); ?>" class="button button-primary button-small">
                                        Bearbeiten
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Submissions -->
        <div class="dgptm-admin-column">
            <div class="dgptm-admin-box">
                <h2>Letzte Einreichungen</h2>
                <?php if (empty($recent)): ?>
                    <p class="no-items">Keine Einreichungen vorhanden.</p>
                <?php else: ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Titel</th>
                                <th>Datum</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent as $article):
                                $status = get_field('artikel_status', $article->ID);
                                $submission_id = get_field('submission_id', $article->ID);
                                $submitted_at = get_field('submitted_at', $article->ID);
                            ?>
                            <tr>
                                <td><?php echo esc_html($submission_id); ?></td>
                                <td>
                                    <a href="<?php echo admin_url('admin.php?page=dgptm-artikel-list&artikel_id=' . $article->ID); ?>">
                                        <?php echo esc_html(wp_trim_words($article->post_title, 6)); ?>
                                    </a>
                                </td>
                                <td><?php echo esc_html($submitted_at ? date_i18n('d.m.Y', strtotime($submitted_at)) : '-'); ?></td>
                                <td>
                                    <span class="status-badge <?php echo esc_attr($plugin->get_status_class($status)); ?>">
                                        <?php echo esc_html($plugin->get_status_label($status)); ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Quick Links -->
    <div class="dgptm-admin-box">
        <h2>Schnellzugriff</h2>
        <div class="quick-links">
            <a href="<?php echo admin_url('admin.php?page=dgptm-artikel-list'); ?>" class="button">
                Alle Einreichungen
            </a>
            <a href="<?php echo admin_url('admin.php?page=dgptm-artikel-reviewers'); ?>" class="button">
                Reviewer verwalten
            </a>
            <a href="<?php echo admin_url('admin.php?page=dgptm-artikel-settings'); ?>" class="button">
                Einstellungen
            </a>
        </div>
    </div>
</div>
