<?php
/**
 * Template: Reviewer-Dashboard
 * Shortcode: [artikel_review]
 * Zeigt dem Reviewer die ihm zugewiesenen Artikel zur Begutachtung
 */

if (!defined('ABSPATH')) exit;

$plugin = DGPTM_Artikel_Einreichung::get_instance();
$user_id = get_current_user_id();

// Get articles assigned to this reviewer
$articles_r1 = get_posts([
    'post_type' => DGPTM_Artikel_Einreichung::POST_TYPE,
    'meta_query' => [
        [
            'key' => 'reviewer_1',
            'value' => $user_id,
            'compare' => '='
        ]
    ],
    'posts_per_page' => -1
]);

$articles_r2 = get_posts([
    'post_type' => DGPTM_Artikel_Einreichung::POST_TYPE,
    'meta_query' => [
        [
            'key' => 'reviewer_2',
            'value' => $user_id,
            'compare' => '='
        ]
    ],
    'posts_per_page' => -1
]);

$articles = array_merge($articles_r1, $articles_r2);
$articles = array_unique($articles, SORT_REGULAR);

// Check if viewing single article for review
$review_id = isset($_GET['review_artikel_id']) ? intval($_GET['review_artikel_id']) : 0;
$review_article = null;
$reviewer_slot = 0;

if ($review_id) {
    $review_article = get_post($review_id);
    if ($review_article && $plugin->is_reviewer_for_article($review_id, $user_id)) {
        $reviewer_slot = $plugin->get_reviewer_number($review_id, $user_id);
    } else {
        $review_article = null;
        $review_id = 0;
    }
}
?>

<div class="dgptm-artikel-container">

    <?php if ($review_article): ?>
        <!-- Single Article Review View -->
        <?php
        $status = get_field('artikel_status', $review_id);
        $submission_id = get_field('submission_id', $review_id);
        $review_status = get_field('reviewer_' . $reviewer_slot . '_status', $review_id);
        $existing_comment = get_field('reviewer_' . $reviewer_slot . '_comment', $review_id);
        $existing_recommendation = get_field('reviewer_' . $reviewer_slot . '_recommendation', $review_id);
        ?>

        <a href="<?php echo esc_url(remove_query_arg('review_artikel_id')); ?>" class="btn btn-secondary" style="margin-bottom: 20px;">
            &larr; Zurück zur Übersicht
        </a>

        <div class="article-card">
            <div class="article-card-header">
                <span class="submission-id"><?php echo esc_html($submission_id); ?></span>
                <h3><?php echo esc_html($review_article->post_title); ?></h3>

                <?php if ($review_status === 'completed'): ?>
                    <span class="status-badge status-green">Review abgeschlossen</span>
                <?php else: ?>
                    <span class="status-badge status-orange">Review ausstehend</span>
                <?php endif; ?>
            </div>

            <div class="article-card-body">
                <!-- Tabs -->
                <div class="tabs-container">
                    <div class="tabs">
                        <div class="tab active" data-tab="abstract">Abstract</div>
                        <div class="tab" data-tab="manuscript">Manuskript</div>
                        <div class="tab" data-tab="review">Gutachten</div>
                    </div>

                    <!-- Abstract Tab -->
                    <div class="tab-content active" data-tab-content="abstract">
                        <h4>Publikationsart</h4>
                        <p><?php echo esc_html(DGPTM_Artikel_Einreichung::PUBLIKATIONSARTEN[get_field('publikationsart', $review_id)] ?? '-'); ?></p>

                        <?php if ($unter = get_field('unterueberschrift', $review_id)): ?>
                        <h4>Untertitel</h4>
                        <p><?php echo esc_html($unter); ?></p>
                        <?php endif; ?>

                        <h4>Abstract (Deutsch)</h4>
                        <div style="white-space: pre-wrap; background: #f7fafc; padding: 15px; border-radius: 6px;">
                            <?php echo esc_html(get_field('abstract-deutsch', $review_id)); ?>
                        </div>

                        <?php if ($abstract_en = get_field('abstract', $review_id)): ?>
                        <h4 style="margin-top: 20px;">Abstract (English)</h4>
                        <div style="white-space: pre-wrap; background: #f7fafc; padding: 15px; border-radius: 6px;">
                            <?php echo esc_html($abstract_en); ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($keywords_de = get_field('keywords-deutsch', $review_id)): ?>
                        <h4 style="margin-top: 20px;">Schlüsselwörter</h4>
                        <p><?php echo esc_html($keywords_de); ?></p>
                        <?php endif; ?>
                    </div>

                    <!-- Manuscript Tab -->
                    <div class="tab-content" data-tab-content="manuscript">
                        <?php
                        $manuskript = get_field('manuskript', $review_id);
                        $revision = get_field('revision_manuskript', $review_id);
                        ?>

                        <h4>Manuskript</h4>
                        <?php if ($manuskript): ?>
                            <a href="<?php echo esc_url($manuskript['url']); ?>" target="_blank" class="btn btn-primary">
                                Original-Manuskript herunterladen (<?php echo esc_html($manuskript['filename']); ?>)
                            </a>
                        <?php else: ?>
                            <p>Kein Manuskript vorhanden.</p>
                        <?php endif; ?>

                        <?php if ($revision): ?>
                        <h4 style="margin-top: 20px;">Revidiertes Manuskript</h4>
                        <a href="<?php echo esc_url($revision['url']); ?>" target="_blank" class="btn btn-primary">
                            Revision herunterladen (<?php echo esc_html($revision['filename']); ?>)
                        </a>

                        <?php if ($response = get_field('revision_response', $review_id)): ?>
                        <h4 style="margin-top: 20px;">Response to Reviewers</h4>
                        <div style="white-space: pre-wrap; background: #f7fafc; padding: 15px; border-radius: 6px;">
                            <?php echo esc_html($response); ?>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <?php
                        $abbildungen = get_field('abbildungen', $review_id);
                        if ($abbildungen):
                        ?>
                        <h4 style="margin-top: 20px;">Abbildungen</h4>
                        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 10px;">
                            <?php foreach ($abbildungen as $img): ?>
                                <a href="<?php echo esc_url($img['url']); ?>" target="_blank">
                                    <img src="<?php echo esc_url($img['sizes']['thumbnail']); ?>" alt=""
                                         style="width: 100%; border-radius: 4px;">
                                </a>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <?php if ($literatur = get_field('literatur', $review_id)): ?>
                        <h4 style="margin-top: 20px;">Literaturverzeichnis</h4>
                        <div style="white-space: pre-wrap; background: #f7fafc; padding: 15px; border-radius: 6px; font-size: 13px;">
                            <?php echo esc_html($literatur); ?>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- Review Tab -->
                    <div class="tab-content" data-tab-content="review">
                        <?php if ($review_status === 'completed'): ?>
                            <!-- Show completed review -->
                            <div class="dgptm-artikel-notice notice-success">
                                <p>Ihr Gutachten wurde erfolgreich eingereicht. Vielen Dank!</p>
                            </div>

                            <h4>Ihre Empfehlung</h4>
                            <p>
                                <span class="status-badge <?php
                                    $rec_class = [
                                        'accept' => 'status-green',
                                        'minor_revision' => 'status-blue',
                                        'major_revision' => 'status-orange',
                                        'reject' => 'status-red'
                                    ];
                                    echo esc_attr($rec_class[$existing_recommendation] ?? 'status-gray');
                                ?>">
                                    <?php
                                    $rec_labels = [
                                        'accept' => 'Annehmen',
                                        'minor_revision' => 'Kleinere Überarbeitung',
                                        'major_revision' => 'Größere Überarbeitung',
                                        'reject' => 'Ablehnen'
                                    ];
                                    echo esc_html($rec_labels[$existing_recommendation] ?? '-');
                                    ?>
                                </span>
                            </p>

                            <h4>Ihr Gutachten</h4>
                            <div style="white-space: pre-wrap; background: #f7fafc; padding: 15px; border-radius: 6px;">
                                <?php echo esc_html($existing_comment); ?>
                            </div>

                        <?php else: ?>
                            <!-- Review Form -->
                            <form id="review-form" data-article-id="<?php echo esc_attr($review_id); ?>">
                                <div class="dgptm-artikel-notice notice-info">
                                    <p><strong>Hinweis:</strong> Ihre Identität wird dem Autor nicht mitgeteilt (Single-Blind Review).</p>
                                </div>

                                <div class="form-row">
                                    <label>Gutachten <span class="required">*</span></label>
                                    <textarea name="comment" rows="12" required
                                              placeholder="Bitte geben Sie eine detaillierte Bewertung des Artikels ab.

Berücksichtigen Sie dabei:
- Wissenschaftliche Qualität und Originalität
- Methodik und Datenanalyse
- Struktur und Verständlichkeit
- Relevanz für die Leserschaft
- Vollständigkeit der Literatur
- Qualität der Abbildungen/Tabellen

Formulieren Sie konkrete Verbesserungsvorschläge."></textarea>
                                </div>

                                <div class="form-row">
                                    <label>Empfehlung <span class="required">*</span></label>
                                    <div class="recommendation-options">
                                        <label class="recommendation-option">
                                            <input type="radio" name="recommendation" value="accept">
                                            <div>
                                                <strong>Annehmen</strong>
                                                <div style="font-size: 12px; color: #718096;">Publikation ohne Änderungen</div>
                                            </div>
                                        </label>
                                        <label class="recommendation-option">
                                            <input type="radio" name="recommendation" value="minor_revision">
                                            <div>
                                                <strong>Kleinere Überarbeitung</strong>
                                                <div style="font-size: 12px; color: #718096;">Minor Revision erforderlich</div>
                                            </div>
                                        </label>
                                        <label class="recommendation-option">
                                            <input type="radio" name="recommendation" value="major_revision">
                                            <div>
                                                <strong>Größere Überarbeitung</strong>
                                                <div style="font-size: 12px; color: #718096;">Major Revision erforderlich</div>
                                            </div>
                                        </label>
                                        <label class="recommendation-option">
                                            <input type="radio" name="recommendation" value="reject">
                                            <div>
                                                <strong>Ablehnen</strong>
                                                <div style="font-size: 12px; color: #718096;">Nicht für Publikation geeignet</div>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary">Gutachten absenden</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php else: ?>
        <!-- Review List View -->
        <h2>Meine Review-Aufgaben</h2>

        <?php if (empty($articles)): ?>
            <div class="empty-state">
                <div class="empty-state-icon">&#128203;</div>
                <h3>Keine offenen Reviews</h3>
                <p>Ihnen sind derzeit keine Artikel zur Begutachtung zugewiesen.</p>
            </div>
        <?php else: ?>

            <table class="dgptm-artikel-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Titel</th>
                        <th>Review-Status</th>
                        <th>Eingereicht</th>
                        <th>Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($articles as $article):
                        $submission_id = get_field('submission_id', $article->ID);
                        $submitted_at = get_field('submitted_at', $article->ID);
                        $slot = $plugin->get_reviewer_number($article->ID, $user_id);
                        $review_status = get_field('reviewer_' . $slot . '_status', $article->ID);
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
                            <?php if ($review_status === 'completed'): ?>
                                <span class="status-badge status-green">Abgeschlossen</span>
                            <?php elseif ($review_status === 'pending'): ?>
                                <span class="status-badge status-orange">Ausstehend</span>
                            <?php else: ?>
                                <span class="status-badge status-gray">-</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($submitted_at ? date_i18n('d.m.Y', strtotime($submitted_at)) : '-'); ?></td>
                        <td>
                            <a href="<?php echo esc_url(add_query_arg('review_artikel_id', $article->ID)); ?>" class="btn btn-primary">
                                <?php echo $review_status === 'completed' ? 'Ansehen' : 'Begutachten'; ?>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php endif; ?>

    <?php endif; ?>

</div>
