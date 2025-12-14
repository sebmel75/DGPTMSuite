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

                <!-- Status-specific Messages -->
                <?php if ($status === DGPTM_Artikel_Einreichung::STATUS_ACCEPTED): ?>
                <div class="dgptm-artikel-notice notice-success" style="margin-top: 20px;">
                    <p><strong>Herzlichen Glückwunsch!</strong> Ihr Artikel wurde zur Veröffentlichung angenommen.</p>
                </div>
                <?php elseif ($status === DGPTM_Artikel_Einreichung::STATUS_REJECTED): ?>
                <div class="dgptm-artikel-notice notice-error" style="margin-top: 20px;">
                    <p>Ihr Artikel wurde leider nicht zur Veröffentlichung angenommen. Bitte lesen Sie die Rückmeldung der Redaktion unten.</p>
                </div>
                <?php elseif ($status === DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED): ?>
                <div class="dgptm-artikel-notice notice-info" style="margin-top: 20px;">
                    <p><strong>Revision eingereicht!</strong> Ihre überarbeitete Version wurde erfolgreich übermittelt. Die Redaktion wird diese prüfen.</p>
                </div>
                <?php elseif ($status === DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW): ?>
                <div class="dgptm-artikel-notice notice-info" style="margin-top: 20px;">
                    <p>Ihr Artikel wird derzeit begutachtet. Wir informieren Sie, sobald es Neuigkeiten gibt.</p>
                </div>
                <?php endif; ?>

                <!-- Decision Letter (if available) -->
                <?php if ($decision_letter && in_array($status, [
                    DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED,
                    DGPTM_Artikel_Einreichung::STATUS_ACCEPTED,
                    DGPTM_Artikel_Einreichung::STATUS_REJECTED
                ])): ?>
                <div class="review-section" style="margin-top: 30px; background: <?php
                    if ($status === DGPTM_Artikel_Einreichung::STATUS_ACCEPTED) echo '#c6f6d5';
                    elseif ($status === DGPTM_Artikel_Einreichung::STATUS_REJECTED) echo '#fed7d7';
                    else echo '#fef3c7';
                ?>;">
                    <h4 style="margin-top: 0;">
                        <?php
                        if ($status === DGPTM_Artikel_Einreichung::STATUS_ACCEPTED) echo 'Begründung der Annahme';
                        elseif ($status === DGPTM_Artikel_Einreichung::STATUS_REJECTED) echo 'Begründung der Ablehnung';
                        else echo 'Rückmeldung der Redaktion - Überarbeitung erforderlich';
                        ?>
                    </h4>
                    <div style="white-space: pre-wrap; line-height: 1.6; background: rgba(255,255,255,0.5); padding: 15px; border-radius: 6px;">
                        <?php echo esc_html($decision_letter); ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Revision Form (if revision required) -->
                <?php if ($status === DGPTM_Artikel_Einreichung::STATUS_REVISION_REQUIRED): ?>
                <div class="review-section" style="margin-top: 30px; background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border: 2px solid #d69e2e; border-radius: 8px;">
                    <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 15px;">
                        <span style="font-size: 28px;">&#9998;</span>
                        <h4 style="margin: 0; color: #92400e;">Revision einreichen</h4>
                    </div>
                    <p style="color: #78350f;">
                        Die Gutachter haben eine Überarbeitung Ihres Artikels empfohlen. Bitte laden Sie Ihr überarbeitetes Manuskript hoch
                        und beantworten Sie die Kommentare der Reviewer Punkt für Punkt.
                    </p>

                    <form id="revision-form"
                          data-article-id="<?php echo esc_attr($view_id); ?>"
                          data-use-token="<?php echo $is_token_access ? '1' : '0'; ?>"
                          data-author-token="<?php echo esc_attr($author_token); ?>"
                          enctype="multipart/form-data"
                          style="background: #fff; padding: 20px; border-radius: 6px; margin-top: 15px;">

                        <div class="form-row">
                            <label style="font-weight: 600; color: #1a365d;">Revidiertes Manuskript <span class="required">*</span></label>
                            <p class="description" style="margin-bottom: 10px;">Laden Sie Ihr überarbeitetes Manuskript hoch (PDF, Word). Änderungen sollten markiert sein.</p>
                            <div class="file-upload-area" style="background: #f7fafc;">
                                <input type="file" name="revision_manuskript" accept=".pdf,.doc,.docx" required>
                                <div class="upload-icon">&#128196;</div>
                                <div class="upload-text">Klicken oder Datei hierher ziehen</div>
                                <div class="upload-hint">PDF, DOC oder DOCX (max. 20MB)</div>
                            </div>
                            <div class="file-preview-container"></div>
                        </div>

                        <div class="form-row" style="margin-top: 20px;">
                            <label for="revision_response" style="font-weight: 600; color: #1a365d;">Response to Reviewers <span class="required">*</span></label>
                            <p class="description" style="margin-bottom: 10px;">
                                Beschreiben Sie die vorgenommenen Änderungen und beantworten Sie jeden Kommentar der Reviewer.
                                Nummerieren Sie Ihre Antworten entsprechend den Reviewer-Kommentaren.
                            </p>
                            <textarea name="revision_response" id="revision_response" rows="10" required
                                      style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 6px; font-family: inherit;"
                                      placeholder="Beispiel:

Reviewer 1, Kommentar 1:
[Antwort und Beschreibung der Änderung]

Reviewer 1, Kommentar 2:
[Antwort und Beschreibung der Änderung]

Reviewer 2, Kommentar 1:
[Antwort und Beschreibung der Änderung]
..."></textarea>
                        </div>

                        <div style="margin-top: 20px; padding-top: 15px; border-top: 1px solid #e2e8f0;">
                            <button type="submit" class="btn btn-primary" style="font-size: 16px; padding: 14px 28px;">
                                &#10003; Revision einreichen
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <!-- Show previous revision if submitted -->
                <?php if ($status === DGPTM_Artikel_Einreichung::STATUS_REVISION_SUBMITTED): ?>
                <?php $revision_response = get_field('revision_response', $view_id); ?>
                <?php if ($revision_response): ?>
                <div class="review-section" style="margin-top: 30px; background: #e0f2fe;">
                    <h4 style="margin-top: 0; color: #0369a1;">Ihre eingereichte Revision</h4>
                    <p><strong>Response to Reviewers:</strong></p>
                    <div style="white-space: pre-wrap; line-height: 1.6; background: #fff; padding: 15px; border-radius: 6px; font-size: 14px;">
                        <?php echo esc_html($revision_response); ?>
                    </div>
                </div>
                <?php endif; ?>
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
