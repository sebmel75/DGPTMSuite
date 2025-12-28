<?php
/**
 * Template: Zeitschrift Grid-Übersicht
 *
 * @var array $issues Array von Zeitschrift-Posts
 * @var array $atts Shortcode-Attribute
 */

if (!defined('ABSPATH')) {
    exit;
}

$spalten = intval($atts['spalten']);
?>

<div class="zk-grid-container" data-columns="<?php echo esc_attr($spalten); ?>">
    <?php if (empty($issues)) : ?>
        <p class="zk-no-issues">Keine Ausgaben gefunden.</p>
    <?php else : ?>
        <div class="zk-grid">
            <?php foreach ($issues as $issue) :
                $issue_id = $issue->ID;
                $titelseite = get_field('titelseite', $issue_id);
                $jahr = get_field('jahr', $issue_id);
                $ausgabe = get_field('ausgabe', $issue_id);
                $label = DGPTM_Zeitschrift_Kardiotechnik::format_issue_label($issue_id);
                $articles = DGPTM_Zeitschrift_Kardiotechnik::get_issue_articles($issue_id);
                $detail_url = get_permalink($issue_id);
                if (!$detail_url || $detail_url === false) {
                    $detail_url = home_url('/?p=' . $issue_id);
                }
            ?>
                <div class="zk-card" data-issue-id="<?php echo esc_attr($issue_id); ?>">
                    <a href="<?php echo esc_url($detail_url); ?>" class="zk-card-link">
                        <div class="zk-card-thumbnail">
                            <?php if ($titelseite) : ?>
                                <img src="<?php echo esc_url($titelseite['sizes']['medium_large'] ?? $titelseite['url']); ?>"
                                     alt="<?php echo esc_attr($label); ?>"
                                     loading="lazy" />
                            <?php else : ?>
                                <div class="zk-card-placeholder">
                                    <span class="dashicons dashicons-book-alt"></span>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="zk-card-info">
                            <span class="zk-card-label"><?php echo esc_html($label); ?></span>
                        </div>
                    </a>

                    <!-- Mobile: Artikel-Liste direkt anzeigen -->
                    <div class="zk-card-articles-mobile">
                        <?php if (!empty($articles)) : ?>
                            <ul class="zk-articles-list">
                                <?php foreach ($articles as $key => $article) :
                                    $pub = $article['publication'];
                                    $authors = ZK_Shortcodes::get_authors_string($pub);
                                ?>
                                    <li class="zk-article-item">
                                        <span class="zk-article-title"><?php echo esc_html($pub->post_title); ?></span>
                                        <?php if ($authors) : ?>
                                            <span class="zk-article-authors"><?php echo esc_html($authors); ?></span>
                                        <?php endif; ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </div>

                    <!-- Desktop: Hover-Popup -->
                    <div class="zk-card-popup">
                        <div class="zk-popup-header">
                            <strong><?php echo esc_html($issue->post_title); ?></strong>
                        </div>
                        <?php if (!empty($articles)) : ?>
                            <div class="zk-popup-articles">
                                <?php foreach ($articles as $key => $article) :
                                    $pub = $article['publication'];
                                    $authors = ZK_Shortcodes::get_authors_string($pub);
                                    $abstract = get_field('abstract-deutsch', $pub->ID);
                                    if (empty($abstract)) {
                                        $abstract = get_field('abstract', $pub->ID);
                                    }
                                ?>
                                    <div class="zk-popup-article">
                                        <div class="zk-popup-article-header">
                                            <span class="zk-popup-title"><?php echo esc_html($pub->post_title); ?></span>
                                            <?php if ($authors) : ?>
                                                <span class="zk-popup-authors"><?php echo esc_html($authors); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($abstract) : ?>
                                            <div class="zk-popup-abstract">
                                                <?php echo wp_kses_post($abstract); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <p class="zk-popup-empty">Keine Artikel</p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
