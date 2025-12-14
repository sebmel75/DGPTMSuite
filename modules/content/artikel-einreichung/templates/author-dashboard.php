<?php
/**
 * Template: Autoren-Dashboard
 * Shortcode: [artikel_dashboard]
 * Zeigt dem Autor seine eingereichten Artikel und deren Status
 * Unterstützt sowohl eingeloggte Benutzer als auch Token-basierten Zugang
 */

if (!defined('ABSPATH')) exit;

$plugin = DGPTM_Artikel_Einreichung::get_instance();
$user_id = get_current_user_id();

// Check for token-based access
$author_token = $GLOBALS['dgptm_artikel_token'] ?? '';
$token_article_id = $GLOBALS['dgptm_artikel_token_article_id'] ?? false;
$is_token_access = !empty($author_token) && $token_article_id;

// Get articles based on access type
if ($is_token_access) {
    // Token access: Only show the specific article
    $articles = [get_post($token_article_id)];
    $view_id = $token_article_id;
    $view_article = get_post($token_article_id);
} else {
    // Logged-in user: Show all their articles
    $articles = get_posts([
        'post_type' => DGPTM_Artikel_Einreichung::POST_TYPE,
        'author' => $user_id,
        'posts_per_page' => -1,
        'orderby' => 'date',
        'order' => 'DESC'
    ]);

    // Check if viewing single article
    $view_id = isset($_GET['artikel_id']) ? intval($_GET['artikel_id']) : 0;
    $view_article = null;
    if ($view_id) {
        $view_article = get_post($view_id);
        // Security: Only show if user is author
        if (!$view_article || $view_article->post_author != $user_id) {
            $view_article = null;
            $view_id = 0;
        }
    }
}
?>

<div class="dgptm-artikel-container">

    <?php if ($view_article): ?>
        <!-- Single Article View -->
        <?php
        $status = get_field('artikel_status', $view_id);
        $submission_id = get_field('submission_id', $view_id);
        $decision_letter = get_field('decision_letter', $view_id);
        ?>

        <?php if (!$is_token_access): ?>
        <a href="<?php echo esc_url(remove_query_arg('artikel_id')); ?>" class="btn btn-secondary" style="margin-bottom: 20px;">
            &larr; Zurück zur Übersicht
        </a>
        <?php endif; ?>

        <div class="article-card">
            <div class="article-card-header">
                <span class="submission-id"><?php echo esc_html($submission_id); ?></span>
                <h3><?php echo esc_html($view_article->post_title); ?></h3>
                <span class="status-badge <?php echo esc_attr($plugin->get_status_class($status)); ?>">
                    <?php echo esc_html($plugin->get_status_label($status)); ?>
                </span>
            </div>

            <div class="article-card-body">
                <!-- Article Info -->
                <div class="article-info-grid" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div>
                        <h4>Artikeldetails</h4>
                        <ul class="info-list">
                            <li>
                                <span class="label">Publikationsart:</span>
                                <span class="value"><?php echo esc_html(DGPTM_Artikel_Einreichung::PUBLIKATIONSARTEN[get_field('publikationsart', $view_id)] ?? '-'); ?></span>
                            </li>
                            <li>
                                <span class="label">Korrespondenzautor:</span>
                                <span class="value"><?php echo esc_html(get_field('hauptautorin', $view_id)); ?></span>
                            </li>
                            <li>
                                <span class="label">Eingereicht am:</span>
                                <span class="value"><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime(get_field('submitted_at', $view_id)))); ?></span>
                            </li>
                            <?php if ($decision_at = get_field('decision_at', $view_id)): ?>
                            <li>
                                <span class="label">Entscheidung am:</span>
                                <span class="value"><?php echo esc_html(date_i18n('d.m.Y', strtotime($decision_at))); ?></span>
                            </li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <div>
                        <h4>Dateien</h4>
                        <ul class="info-list">
                            <?php
                            $manuskript = get_field('manuskript', $view_id);
                            if ($manuskript):
                            ?>
                            <li>
                                <a href="<?php echo esc_url($manuskript['url']); ?>" target="_blank" class="btn btn-secondary">
                                    Manuskript herunterladen
                                </a>
                            </li>
                            <?php endif; ?>

                            <?php
                            $revision = get_field('revision_manuskript', $view_id);
                            if ($revision):
                            ?>
                            <li>
                                <a href="<?php echo esc_url($revision['url']); ?>" target="_blank" class="btn btn-secondary">
                                    Revidiertes Manuskript
                                </a>
                            </li>
                            <?php endif; ?>
                            <li style="margin-top: 10px;">
                                <?php
                                $pdf_url = add_query_arg(['dgptm_artikel_pdf' => 1, 'artikel_id' => $view_id], home_url());
                                if ($is_token_access && $author_token) {
                                    $pdf_url = add_query_arg('autor_token', $author_token, $pdf_url);
                                }
                                ?>
                                <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" class="btn btn-secondary">
                                    Artikel-Übersicht als PDF
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Decision Letter (if available) -->
                <?php if ($decision_letter && in_array($status, [
                    DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED,
                    DGPTM_Artikel_Einreichung::STATUS_ACCEPTED,
                    DGPTM_Artikel_Einreichung::STATUS_REJECTED
                ])): ?>
                <div class="review-section" style="margin-top: 30px;">
                    <h4>Rückmeldung der Redaktion</h4>
                    <div style="white-space: pre-wrap; line-height: 1.6;">
                        <?php echo esc_html($decision_letter); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Revision Form (if revision required) -->
                <?php if ($status === DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED): ?>
                <div class="review-section" style="margin-top: 30px; background: #fef3c7;">
                    <h4>Revision einreichen</h4>
                    <p>Bitte laden Sie Ihr überarbeitetes Manuskript hoch und beantworten Sie die Reviewer-Kommentare.</p>

                    <form id="revision-form"
                          data-article-id="<?php echo esc_attr($view_id); ?>"
                          data-use-token="<?php echo $is_token_access ? '1' : '0'; ?>"
                          data-author-token="<?php echo esc_attr($author_token); ?>"
                          enctype="multipart/form-data">
                        <div class="form-row">
                            <label>Revidiertes Manuskript <span class="required">*</span></label>
                            <div class="file-upload-area">
                                <input type="file" name="revision_manuskript" accept=".pdf,.doc,.docx" required>
                                <div class="upload-icon">&#128196;</div>
                                <div class="upload-text">Klicken oder Datei hierher ziehen</div>
                            </div>
                            <div class="file-preview-container"></div>
                        </div>

                        <div class="form-row">
                            <label for="revision_response">Response to Reviewers <span class="required">*</span></label>
                            <textarea name="revision_response" id="revision_response" rows="8" required
                                      placeholder="Bitte beschreiben Sie die vorgenommenen Änderungen und beantworten Sie die Kommentare der Reviewer punkt für punkt."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary">Revision einreichen</button>
                    </form>
                </div>
                <?php endif; ?>

            </div>
        </div>

    <?php elseif (!$is_token_access): ?>
        <!-- Article List View (only for logged-in users) -->
        <h2>Meine Einreichungen</h2>

        <?php if (empty($articles)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">&#128196;</div>
                <h3>Noch keine Einreichungen</h3>
                <p>Sie haben noch keinen Artikel eingereicht.</p>
                <a href="<?php echo esc_url(home_url('/fachzeitschrift/autorin-werden/')); ?>" class="btn btn-primary">
                    Artikel einreichen
                </a>
            </div>
        <?php else: ?>

            <table class="dgptm-artikel-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Titel</th>
                        <th>Status</th>
                        <th>Eingereicht</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $article):
                        $status = get_field('artikel_status', $article->ID);
                        $submission_id = get_field('submission_id', $article->ID);
                        $submitted_at = get_field('submitted_at', $article->ID);
                    ?>
                    <tr>
                        <td class="submission-id"><?php echo esc_html($submission_id); ?></td>
                        <td>
                            <div class="article-title"><?php echo esc_html($article->post_title); ?></div>
                            <div class="article-meta">
                                <?php echo esc_html(DGPTM_Artikel_Einreichung::PUBLIKATIONSARTEN[get_field('publikationsart', $article->ID)] ?? ''); ?>
                            </div>
                        </td>
                        <td>
                            <span class="status-badge <?php echo esc_attr($plugin->get_status_class($status)); ?>">
                                <?php echo esc_html($plugin->get_status_label($status)); ?>
                            </span>
                        </td>
                        <td><?php echo esc_html($submitted_at ? date_i18n('d.m.Y', strtotime($submitted_at)) : '-'); ?></td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg('artikel_id', $article->ID)); ?>" class="btn btn-secondary">
                                Details
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>

    <?php endif; ?>

</div>
