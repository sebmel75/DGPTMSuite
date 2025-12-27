<?php
/**
 * Template: Zeitschrift Einzelansicht
 *
 * Layout: Links Deckblatt, Rechts Artikelliste
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
    <div class="zk-detail-layout">
        <!-- Linke Spalte: Deckblatt -->
        <div class="zk-detail-left">
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

            <div class="zk-detail-meta">
                <h2 class="zk-detail-title">Kardiotechnik <?php echo esc_html($label); ?></h2>

                <?php if ($doi) : ?>
                    <div class="zk-meta-doi">
                        <a href="https://doi.org/<?php echo esc_attr($doi); ?>" target="_blank" rel="noopener">
                            DOI: <?php echo esc_html($doi); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($pdf) : ?>
                    <div class="zk-detail-download">
                        <a href="<?php echo esc_url($pdf['url']); ?>" class="zk-btn zk-btn-primary" target="_blank" rel="noopener">
                            <span class="dashicons dashicons-download"></span>
                            Gesamtausgabe (PDF)
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Rechte Spalte: Artikelliste -->
        <div class="zk-detail-right">
            <h3 class="zk-articles-heading">Inhaltsverzeichnis</h3>

            <?php if (!empty($special_articles)) : ?>
                <div class="zk-articles-group">
                    <?php foreach ($special_articles as $key => $article) :
                        $pub = $article['publication'];
                        $pub_data = DGPTM_Zeitschrift_Kardiotechnik::get_publication_display_data($pub);
                        if (!$pub_data) continue;
                        $article_url = home_url('/?p=' . $pub_data['id']);
                    ?>
                        <div class="zk-article-row zk-article-<?php echo esc_attr($article['type']); ?>">
                            <a href="<?php echo esc_url($article_url); ?>" class="zk-article-link">
                                <span class="zk-article-type-badge">
                                    <?php echo esc_html(ZK_Shortcodes::get_article_type_label($article['type'])); ?>
                                </span>
                                <span class="zk-article-title"><?php echo esc_html($pub_data['title']); ?></span>
                                <?php if ($pub_data['authors'] || $pub_data['main_author']) : ?>
                                    <span class="zk-article-authors">
                                        <?php echo esc_html($pub_data['authors'] ?: $pub_data['main_author']); ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($regular_articles)) : ?>
                <div class="zk-articles-group">
                    <h4 class="zk-group-title">Fachartikel</h4>
                    <?php foreach ($regular_articles as $key => $article) :
                        $pub = $article['publication'];
                        $pub_data = DGPTM_Zeitschrift_Kardiotechnik::get_publication_display_data($pub);
                        if (!$pub_data) continue;
                        $article_url = home_url('/?p=' . $pub_data['id']);
                    ?>
                        <div class="zk-article-row">
                            <a href="<?php echo esc_url($article_url); ?>" class="zk-article-link">
                                <span class="zk-article-title"><?php echo esc_html($pub_data['title']); ?></span>
                                <?php if ($pub_data['authors'] || $pub_data['main_author']) : ?>
                                    <span class="zk-article-authors">
                                        <?php echo esc_html($pub_data['authors'] ?: $pub_data['main_author']); ?>
                                    </span>
                                <?php endif; ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($articles)) : ?>
                <p class="zk-no-articles">Keine Artikel in dieser Ausgabe.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
