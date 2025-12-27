<?php
/**
 * Template: Zeitschrift Einzelansicht
 *
 * @var WP_Post $issue Das Zeitschrift-Post-Objekt
 * @var array $articles Array der Artikel
 */

if (!defined('ABSPATH')) {
    exit;
}

$issue_id = $issue->ID;
$titelseite = get_field('titelseite', $issue_id);
$jahr = get_field('jahr', $issue_id);
$ausgabe = get_field('ausgabe', $issue_id);
$doi = get_field('doi', $issue_id);
$pdf = get_field('pdf', $issue_id);
$label = DGPTM_Zeitschrift_Kardiotechnik::format_issue_label($issue_id);

// Artikel nach Typ gruppieren
$special_articles = [];
$regular_articles = [];

foreach ($articles as $key => $article) {
    if (in_array($article['type'], ['editorial', 'journalclub', 'tutorial'])) {
        $special_articles[$key] = $article;
    } else {
        $regular_articles[$key] = $article;
    }
}
?>

<div class="zk-detail-container">
    <div class="zk-detail-header">
        <div class="zk-detail-cover">
            <?php if ($titelseite) : ?>
                <img src="<?php echo esc_url($titelseite['sizes']['large'] ?? $titelseite['url']); ?>"
                     alt="Kardiotechnik <?php echo esc_attr($label); ?>" />
            <?php else : ?>
                <div class="zk-detail-placeholder">
                    <span class="dashicons dashicons-book-alt"></span>
                </div>
            <?php endif; ?>
        </div>

        <div class="zk-detail-info">
            <h1 class="zk-detail-title">Kardiotechnik <?php echo esc_html($label); ?></h1>

            <div class="zk-detail-meta">
                <?php if ($jahr) : ?>
                    <div class="zk-meta-item">
                        <span class="zk-meta-label">Jahrgang:</span>
                        <span class="zk-meta-value"><?php echo esc_html($jahr); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($ausgabe) : ?>
                    <div class="zk-meta-item">
                        <span class="zk-meta-label">Ausgabe:</span>
                        <span class="zk-meta-value"><?php echo esc_html($ausgabe); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($doi) : ?>
                    <div class="zk-meta-item">
                        <span class="zk-meta-label">DOI:</span>
                        <span class="zk-meta-value">
                            <a href="https://doi.org/<?php echo esc_attr($doi); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html($doi); ?>
                            </a>
                        </span>
                    </div>
                <?php endif; ?>
            </div>

            <?php if ($pdf) : ?>
                <div class="zk-detail-actions">
                    <a href="<?php echo esc_url($pdf['url']); ?>" class="zk-btn zk-btn-primary" target="_blank" rel="noopener">
                        <span class="dashicons dashicons-download"></span>
                        Gesamtausgabe herunterladen (PDF)
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="zk-detail-content">
        <?php if (!empty($special_articles)) : ?>
            <div class="zk-articles-section">
                <h2 class="zk-section-title">Rubriken</h2>
                <div class="zk-articles-list">
                    <?php foreach ($special_articles as $key => $article) :
                        $pub = $article['publication'];
                        $pub_data = DGPTM_Zeitschrift_Kardiotechnik::get_publication_display_data($pub);
                        if (!$pub_data) continue;
                    ?>
                        <div class="zk-article-card zk-article-<?php echo esc_attr($article['type']); ?>">
                            <div class="zk-article-type">
                                <?php echo esc_html(ZK_Shortcodes::get_article_type_label($article['type'])); ?>
                            </div>
                            <h3 class="zk-article-title"><?php echo esc_html($pub_data['title']); ?></h3>

                            <?php if ($pub_data['authors'] || $pub_data['main_author']) : ?>
                                <div class="zk-article-authors">
                                    <?php echo esc_html($pub_data['authors'] ?: $pub_data['main_author']); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($article['supplement']) : ?>
                                <div class="zk-article-supplement">
                                    <?php echo esc_html($article['supplement']); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($pub_data['abstract_de']) : ?>
                                <div class="zk-article-abstract">
                                    <?php echo wp_kses_post(wp_trim_words($pub_data['abstract_de'], 50)); ?>
                                </div>
                            <?php endif; ?>

                            <div class="zk-article-actions">
                                <?php if ($pub_data['pdf_volltext']) : ?>
                                    <a href="<?php echo esc_url($pub_data['pdf_volltext']['url']); ?>"
                                       class="zk-btn zk-btn-small" target="_blank" rel="noopener">
                                        Volltext (PDF)
                                    </a>
                                <?php endif; ?>

                                <?php if ($pub_data['pdf_abstract']) : ?>
                                    <a href="<?php echo esc_url($pub_data['pdf_abstract']['url']); ?>"
                                       class="zk-btn zk-btn-small zk-btn-secondary" target="_blank" rel="noopener">
                                        Abstract (PDF)
                                    </a>
                                <?php endif; ?>

                                <?php if ($pub_data['doi']) : ?>
                                    <a href="https://doi.org/<?php echo esc_attr($pub_data['doi']); ?>"
                                       class="zk-btn zk-btn-small zk-btn-link" target="_blank" rel="noopener">
                                        DOI
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($regular_articles)) : ?>
            <div class="zk-articles-section">
                <h2 class="zk-section-title">Fachartikel</h2>
                <div class="zk-articles-list">
                    <?php foreach ($regular_articles as $key => $article) :
                        $pub = $article['publication'];
                        $pub_data = DGPTM_Zeitschrift_Kardiotechnik::get_publication_display_data($pub);
                        if (!$pub_data) continue;
                    ?>
                        <div class="zk-article-card">
                            <h3 class="zk-article-title"><?php echo esc_html($pub_data['title']); ?></h3>

                            <?php if ($pub_data['authors'] || $pub_data['main_author']) : ?>
                                <div class="zk-article-authors">
                                    <?php echo esc_html($pub_data['authors'] ?: $pub_data['main_author']); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($article['supplement']) : ?>
                                <div class="zk-article-supplement">
                                    <?php echo esc_html($article['supplement']); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($pub_data['abstract_de']) : ?>
                                <div class="zk-article-abstract">
                                    <?php echo wp_kses_post(wp_trim_words($pub_data['abstract_de'], 50)); ?>
                                </div>
                            <?php endif; ?>

                            <?php if ($pub_data['keywords_de']) : ?>
                                <div class="zk-article-keywords">
                                    <strong>Keywords:</strong> <?php echo esc_html($pub_data['keywords_de']); ?>
                                </div>
                            <?php endif; ?>

                            <div class="zk-article-actions">
                                <?php if ($pub_data['pdf_volltext']) : ?>
                                    <a href="<?php echo esc_url($pub_data['pdf_volltext']['url']); ?>"
                                       class="zk-btn zk-btn-small" target="_blank" rel="noopener">
                                        Volltext (PDF)
                                    </a>
                                <?php endif; ?>

                                <?php if ($pub_data['pdf_abstract']) : ?>
                                    <a href="<?php echo esc_url($pub_data['pdf_abstract']['url']); ?>"
                                       class="zk-btn zk-btn-small zk-btn-secondary" target="_blank" rel="noopener">
                                        Abstract (PDF)
                                    </a>
                                <?php endif; ?>

                                <?php if ($pub_data['doi']) : ?>
                                    <a href="https://doi.org/<?php echo esc_attr($pub_data['doi']); ?>"
                                       class="zk-btn zk-btn-small zk-btn-link" target="_blank" rel="noopener">
                                        DOI
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($articles)) : ?>
            <p class="zk-no-articles">Keine Artikel in dieser Ausgabe.</p>
        <?php endif; ?>
    </div>

    <div class="zk-detail-footer">
        <a href="javascript:history.back()" class="zk-btn zk-btn-secondary">
            &larr; Zurück zur Übersicht
        </a>
    </div>
</div>
