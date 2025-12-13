<?php
if (!defined('ABSPATH')) {
    exit;
}

global $wpdb;
$table_name = $wpdb->prefix . 'kardiotechnik_articles';

// Artikel aktualisieren
if (isset($_POST['update_article']) && check_admin_referer('kta_update_article')) {
    $article_id = intval($_POST['article_id']);
    $wpdb->update(
        $table_name,
        array(
            'title' => sanitize_text_field($_POST['title']),
            'author' => sanitize_text_field($_POST['author']),
            'keywords' => sanitize_textarea_field($_POST['keywords']),
            'abstract' => sanitize_textarea_field($_POST['abstract']),
            'page_start' => intval($_POST['page_start']),
            'page_end' => intval($_POST['page_end'])
        ),
        array('id' => $article_id),
        array('%s', '%s', '%s', '%s', '%d', '%d'),
        array('%d')
    );
    echo '<div class="notice notice-success"><p>Artikel wurde aktualisiert.</p></div>';
}

// Artikel abrufen
$articles = $wpdb->get_results("SELECT * FROM $table_name ORDER BY year DESC, issue DESC LIMIT 100");
$total_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
?>

<div class="wrap">
    <h1>Kardiotechnik Archiv - Artikel verwalten</h1>

    <div class="kta-stats">
        <div class="kta-stat-box">
            <h3><?php echo number_format($total_count); ?></h3>
            <p>Artikel im Archiv</p>
        </div>
    </div>

    <div class="tablenav top">
        <div class="alignleft actions">
            <input type="text" id="kta-search-articles" placeholder="Artikel suchen..." />
            <button class="button" id="kta-search-btn">Suchen</button>
        </div>
    </div>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>ID</th>
                <th>Jahr</th>
                <th>Ausgabe</th>
                <th>Titel</th>
                <th>Autor</th>
                <th>Seiten</th>
                <th>Aktionen</th>
            </tr>
        </thead>
        <tbody id="kta-articles-list">
            <?php foreach ($articles as $article): ?>
            <tr>
                <td><?php echo esc_html($article->id); ?></td>
                <td><?php echo esc_html($article->year); ?></td>
                <td><?php echo esc_html($article->issue); ?></td>
                <td><?php echo esc_html($article->title); ?></td>
                <td><?php echo esc_html($article->author); ?></td>
                <td>
                    <?php
                    if ($article->page_start && $article->page_end) {
                        echo esc_html($article->page_start . '-' . $article->page_end);
                    }
                    ?>
                </td>
                <td>
                    <a href="<?php echo esc_url($article->pdf_url); ?>" target="_blank" class="button button-small">PDF anzeigen</a>
                    <button class="button button-small kta-edit-article" data-id="<?php echo esc_attr($article->id); ?>">Bearbeiten</button>
                    <button class="button button-small button-link-delete kta-delete-article" data-id="<?php echo esc_attr($article->id); ?>">Löschen</button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal für Artikel bearbeiten -->
<div id="kta-edit-modal" style="display:none;">
    <div class="kta-modal-content">
        <span class="kta-modal-close">&times;</span>
        <h2>Artikel bearbeiten</h2>
        <form method="post" id="kta-edit-form">
            <?php wp_nonce_field('kta_update_article'); ?>
            <input type="hidden" name="article_id" id="edit-article-id" />

            <table class="form-table">
                <tr>
                    <th><label for="edit-title">Titel</label></th>
                    <td><input type="text" name="title" id="edit-title" class="regular-text" required /></td>
                </tr>
                <tr>
                    <th><label for="edit-author">Autor(en)</label></th>
                    <td><input type="text" name="author" id="edit-author" class="regular-text" /></td>
                </tr>
                <tr>
                    <th><label for="edit-keywords">Schlagwörter</label></th>
                    <td><input type="text" name="keywords" id="edit-keywords" class="regular-text" placeholder="Komma-getrennt" /></td>
                </tr>
                <tr>
                    <th><label for="edit-abstract">Zusammenfassung</label></th>
                    <td><textarea name="abstract" id="edit-abstract" rows="5" class="large-text"></textarea></td>
                </tr>
                <tr>
                    <th><label for="edit-page-start">Seiten</label></th>
                    <td>
                        <input type="number" name="page_start" id="edit-page-start" style="width:80px" /> bis
                        <input type="number" name="page_end" id="edit-page-end" style="width:80px" />
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" name="update_article" class="button button-primary">Speichern</button>
            </p>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Modal öffnen
    $('.kta-edit-article').on('click', function() {
        var articleId = $(this).data('id');

        $.post(ajaxurl, {
            action: 'kta_get_article',
            article_id: articleId,
            nonce: ktaAdmin.nonce
        }, function(response) {
            if (response.success) {
                var article = response.data;
                $('#edit-article-id').val(article.id);
                $('#edit-title').val(article.title);
                $('#edit-author').val(article.author);
                $('#edit-keywords').val(article.keywords);
                $('#edit-abstract').val(article.abstract);
                $('#edit-page-start').val(article.page_start);
                $('#edit-page-end').val(article.page_end);
                $('#kta-edit-modal').show();
            }
        });
    });

    // Modal schließen
    $('.kta-modal-close').on('click', function() {
        $('#kta-edit-modal').hide();
    });

    // Artikel löschen
    $('.kta-delete-article').on('click', function() {
        if (!confirm('Möchten Sie diesen Artikel wirklich löschen?')) {
            return;
        }

        var articleId = $(this).data('id');
        var $row = $(this).closest('tr');

        $.post(ajaxurl, {
            action: 'kta_delete_article',
            article_id: articleId,
            nonce: ktaAdmin.nonce
        }, function(response) {
            if (response.success) {
                $row.fadeOut(function() {
                    $(this).remove();
                });
            }
        });
    });
});
</script>
