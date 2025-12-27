<?php
/**
 * Template: Zeitschrift Einzelansicht
 *
 * Layout: Links Deckblatt, Rechts Info + Artikelliste
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

// Artikel nach Typ sortieren
$sorted_articles = [
    'editorial' => [],
    'journalclub' => [],
    'tutorial' => [],
    'artikel' => []
];

foreach ($articles as $key => $article) {
    $type = $article['type'];
    if (!isset($sorted_articles[$type])) {
        $sorted_articles['artikel'][] = $article;
    } else {
        $sorted_articles[$type][] = $article;
    }
}
?>

<div class="zk-single">
    <div class="zk-single-layout">
        <!-- Linke Spalte: Deckblatt -->
        <div class="zk-single-cover">
            <?php if ($titelseite) : ?>
                <img src="<?php echo esc_url($titelseite['sizes']['large'] ?? $titelseite['url']); ?>"
                     alt="Kardiotechnik <?php echo esc_attr($label); ?>" />
            <?php else : ?>
                <div class="zk-single-placeholder">
                    <span class="dashicons dashicons-book-alt"></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Rechte Spalte: Info + Artikel -->
        <div class="zk-single-content">
            <!-- Header mit Ausgabe-Info -->
            <div class="zk-single-header">
                <h1 class="zk-single-title">Kardiotechnik</h1>
                <div class="zk-single-issue">Ausgabe <?php echo esc_html($label); ?></div>

                <?php if ($doi) : ?>
                    <div class="zk-single-doi">
                        <a href="https://doi.org/<?php echo esc_attr($doi); ?>" target="_blank" rel="noopener">
                            <span class="zk-doi-icon">DOI</span>
                            <?php echo esc_html($doi); ?>
                        </a>
                    </div>
                <?php endif; ?>

                <?php if ($pdf) : ?>
                    <a href="<?php echo esc_url($pdf['url']); ?>" class="zk-download-btn" target="_blank" rel="noopener">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                            <polyline points="7 10 12 15 17 10"/>
                            <line x1="12" y1="15" x2="12" y2="3"/>
                        </svg>
                        Gesamtausgabe herunterladen
                    </a>
                <?php endif; ?>
            </div>

            <!-- Artikelliste -->
            <div class="zk-single-articles">
                <?php foreach ($sorted_articles as $type => $type_articles) : ?>
                    <?php if (!empty($type_articles)) : ?>
                        <div class="zk-article-section" data-type="<?php echo esc_attr($type); ?>">
                            <h3 class="zk-section-label"><?php echo esc_html(ZK_Shortcodes::get_article_type_label($type)); ?></h3>

                            <?php foreach ($type_articles as $article) :
                                $pub = $article['publication'];
                                $pub_data = DGPTM_Zeitschrift_Kardiotechnik::get_publication_display_data($pub);
                                if (!$pub_data) continue;
                                $article_url = home_url('/?p=' . $pub_data['id']);
                                $authors = $pub_data['authors'] ?: $pub_data['main_author'];
                            ?>
                                <a href="<?php echo esc_url($article_url); ?>" class="zk-article-item">
                                    <div class="zk-article-info">
                                        <span class="zk-article-title"><?php echo esc_html($pub_data['title']); ?></span>
                                        <?php if ($authors) : ?>
                                            <span class="zk-article-authors"><?php echo esc_html($authors); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <svg class="zk-article-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <polyline points="9 18 15 12 9 6"/>
                                    </svg>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if (empty($articles)) : ?>
                    <p class="zk-no-articles">Keine Artikel in dieser Ausgabe.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
