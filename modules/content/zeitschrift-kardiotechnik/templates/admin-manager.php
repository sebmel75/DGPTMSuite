<?php
/**
 * Template: Zeitschrift Verwaltung
 *
 * @var array $issues Array aller Zeitschrift-Posts
 */

if (!defined('ABSPATH')) {
    exit;
}

$current_user = wp_get_current_user();
?>

<div class="zk-admin-container">
    <div class="zk-admin-header">
        <h1 class="zk-admin-title">Zeitschrift Kardiotechnik - Verwaltung</h1>
        <p class="zk-admin-subtitle">
            Angemeldet als: <strong><?php echo esc_html($current_user->display_name); ?></strong>
        </p>
    </div>

    <div class="zk-admin-actions">
        <a href="<?php echo esc_url(admin_url('post-new.php?post_type=' . ZK_POST_TYPE)); ?>"
           class="zk-btn zk-btn-primary" target="_blank">
            <span class="dashicons dashicons-plus-alt"></span>
            Neue Ausgabe erstellen
        </a>
    </div>

    <div class="zk-admin-table-wrapper">
        <table class="zk-admin-table">
            <thead>
                <tr>
                    <th class="zk-col-cover">Cover</th>
                    <th class="zk-col-ausgabe">Ausgabe</th>
                    <th class="zk-col-articles">Artikel</th>
                    <th class="zk-col-status">Status</th>
                    <th class="zk-col-date">Veröffentlichung</th>
                    <th class="zk-col-actions">Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($issues)) : ?>
                    <tr>
                        <td colspan="6" class="zk-no-issues">Keine Ausgaben vorhanden.</td>
                    </tr>
                <?php else : ?>
                    <?php foreach ($issues as $issue) :
                        $issue_id = $issue->ID;
                        $titelseite = get_field('titelseite', $issue_id);
                        $label = DGPTM_Zeitschrift_Kardiotechnik::format_issue_label($issue_id);
                        $articles = DGPTM_Zeitschrift_Kardiotechnik::get_issue_articles($issue_id);
                        $status = ZK_Admin::get_issue_status($issue_id);
                        $date_input = ZK_Admin::date_to_input($status['date']);
                    ?>
                        <tr class="zk-issue-row" data-issue-id="<?php echo esc_attr($issue_id); ?>">
                            <td class="zk-col-cover">
                                <?php if ($titelseite) : ?>
                                    <img src="<?php echo esc_url($titelseite['sizes']['thumbnail'] ?? $titelseite['url']); ?>"
                                         alt="<?php echo esc_attr($label); ?>"
                                         class="zk-admin-thumbnail" />
                                <?php else : ?>
                                    <div class="zk-admin-placeholder">
                                        <span class="dashicons dashicons-book-alt"></span>
                                    </div>
                                <?php endif; ?>
                            </td>

                            <td class="zk-col-ausgabe">
                                <strong><?php echo esc_html($label); ?></strong>
                                <br>
                                <small><?php echo esc_html($issue->post_title); ?></small>
                            </td>

                            <td class="zk-col-articles">
                                <span class="zk-article-count"><?php echo count($articles); ?></span>
                                <span class="zk-article-label">Artikel</span>
                            </td>

                            <td class="zk-col-status">
                                <span class="zk-status-badge <?php echo esc_attr($status['class']); ?>">
                                    <?php echo esc_html($status['label']); ?>
                                </span>
                            </td>

                            <td class="zk-col-date">
                                <div class="zk-date-controls">
                                    <input type="date"
                                           class="zk-date-input"
                                           value="<?php echo esc_attr($date_input); ?>"
                                           data-issue-id="<?php echo esc_attr($issue_id); ?>" />
                                    <button type="button" class="zk-btn zk-btn-small zk-btn-save-date"
                                            data-issue-id="<?php echo esc_attr($issue_id); ?>">
                                        Speichern
                                    </button>
                                </div>
                            </td>

                            <td class="zk-col-actions">
                                <div class="zk-action-buttons">
                                    <?php if ($status['status'] !== 'online') : ?>
                                        <button type="button" class="zk-btn zk-btn-small zk-btn-publish-now"
                                                data-issue-id="<?php echo esc_attr($issue_id); ?>">
                                            Jetzt veröffentlichen
                                        </button>
                                    <?php endif; ?>

                                    <a href="<?php echo esc_url(get_edit_post_link($issue_id)); ?>"
                                       class="zk-btn zk-btn-small zk-btn-secondary" target="_blank">
                                        Bearbeiten
                                    </a>

                                    <a href="<?php echo esc_url(add_query_arg('ausgabe_id', $issue_id, get_permalink())); ?>"
                                       class="zk-btn zk-btn-small zk-btn-link" target="_blank">
                                        Ansehen
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (post_type_exists('artikel_einreichung')) : ?>
        <div class="zk-admin-section">
            <h2 class="zk-section-title">Akzeptierte Artikel</h2>
            <p class="zk-section-desc">Diese Artikel wurden akzeptiert und können einer Ausgabe zugewiesen werden.</p>

            <div class="zk-accepted-articles" id="zk-accepted-articles">
                <button type="button" class="zk-btn zk-btn-secondary" id="zk-load-accepted">
                    <span class="dashicons dashicons-update"></span>
                    Akzeptierte Artikel laden
                </button>
                <div class="zk-accepted-list"></div>
            </div>
        </div>
    <?php endif; ?>

    <div class="zk-admin-footer">
        <p class="zk-footer-info">
            <span class="dashicons dashicons-info-outline"></span>
            Änderungen am Veröffentlichungsdatum werden sofort wirksam.
        </p>
    </div>
</div>
