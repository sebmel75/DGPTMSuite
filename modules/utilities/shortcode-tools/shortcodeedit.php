<?php
/**
 * Shortcode [news-edit-form]
 *
 * Zeigt das Bearbeitungsformular für EINE News (ID via ?cnp_edit_news=123).
 * Titel und Inhalt werden im WYSIWYG-Feld bearbeitet.
 * Benötigt die Fähigkeit 'edit_newsbereich' für das jeweilige Post.
 */

if (!defined('ABSPATH')) {
    exit;
}
function cnp_news_edit_form_shortcode() {
    // 1) Prüfen, ob wir eine cnp_edit_news GET-Variable haben
    if (!isset($_GET['cnp_edit_news'])) {
        // Ohne ID kein Formular
        return '';
    }
    // 2) Welcher Beitrag?
    $edit_id = (int)$_GET['cnp_edit_news'];

    // 3) Prüfen, ob der User diesen Beitrag bearbeiten darf
    if (!current_user_can('edit_newsbereich', $edit_id)) {
        return '<p>Keine Berechtigung, diese News zu bearbeiten.</p>';
    }

    // 4) Post holen
    $post = get_post($edit_id);
    if (!$post || $post->post_type !== 'newsbereich') {
        return '<p>Ungültige News-ID.</p>';
    }

    // Puffer starten
    ob_start();

    // 5) Formular absenden?
    if (isset($_POST['cnp_update_news'])) {
        // Sicherheitscheck
        if (!isset($_POST['cnp_nonce']) || !wp_verify_nonce($_POST['cnp_nonce'], 'cnp_update_news_' . $edit_id)) {
            echo '<div class="notice notice-error"><p>Ungültige Sicherheitsprüfung.</p></div>';
        } else {
            // Daten übernehmen
            $title_raw      = isset($_POST['cnp_news_title']) ? wp_kses_post($_POST['cnp_news_title']) : '';
            $content        = isset($_POST['cnp_news_content']) ? wp_kses_post($_POST['cnp_news_content']) : '';
            $publish_date   = isset($_POST['cnp_publish_date']) ? cnp_convert_date_or_fallback_today($_POST['cnp_publish_date']) : date('Y-m-d');
            $display_until  = isset($_POST['cnp_display_until']) ? cnp_convert_date_or_fallback_today($_POST['cnp_display_until']) : '';
            $cnp_news_url   = isset($_POST['cnp_news_url']) ? esc_url_raw($_POST['cnp_news_url']) : '';
            $cat_selected   = isset($_POST['cnp_news_category']) ? array_map('intval', $_POST['cnp_news_category']) : array();

            // Neue Kategorie?
            if (!empty($_POST['cnp_new_category'])) {
                $new_cat_name = sanitize_text_field($_POST['cnp_new_category']);
                $existing_cat = get_term_by('name', $new_cat_name, 'category');
                if (!$existing_cat) {
                    $new_term = wp_insert_term($new_cat_name, 'category');
                    if (!is_wp_error($new_term)) {
                        $cat_selected[] = (int)$new_term['term_id'];
                    }
                } else {
                    $cat_selected[] = (int)$existing_cat->term_id;
                }
            }

            // Falls Du sicherstellen willst, dass mindestens "allgemein" gesetzt ist
            cnp_ensure_category_allgemein($cat_selected);

            // Prüfen, ob Titel und Inhalt gefüllt
            if (empty($title_raw) || empty($content)) {
                echo '<div class="notice notice-error"><p>Bitte Titel und Inhalt ausfüllen.</p></div>';
            } else {
                // 6) Beitrag aktualisieren
                $updated_post = array(
                    'ID'           => $edit_id,
                    'post_title'   => $title_raw,
                    'post_content' => $content,
                    'meta_input'   => array('_cnp_publish_date' => $publish_date),
                    'post_category'=> $cat_selected,
                );
                // Anzeigen bis
                if (!empty($display_until)) {
                    $updated_post['meta_input']['_cnp_display_until'] = $display_until;
                } else {
                    delete_post_meta($edit_id, '_cnp_display_until');
                }

                // Beitragsbild?
                if (!empty($_FILES['cnp_featured_image']['name'])) {
                    require_once(ABSPATH . 'wp-admin/includes/file.php');
                    require_once(ABSPATH . 'wp-admin/includes/media.php');
                    require_once(ABSPATH . 'wp-admin/includes/image.php');
                    $attachment_id = media_handle_upload('cnp_featured_image', $edit_id);
                    if (!is_wp_error($attachment_id)) {
                        set_post_thumbnail($edit_id, $attachment_id);
                    }
                }

                // Aktualisieren
                $res = wp_update_post($updated_post, true);
                if (is_wp_error($res)) {
                    echo '<div class="notice notice-error"><p>Fehler beim Aktualisieren: ' . esc_html($res->get_error_message()) . '</p></div>';
                } else {
                    // Externe URL aktualisieren
                    update_post_meta($edit_id, '_cnp_news_url', $cnp_news_url);

                    // Erfolg => Zurück (Modus schließen)
                    $redirect_url = remove_query_arg('cnp_edit_news');
                    wp_safe_redirect($redirect_url);
                    exit;
                }
            }
        }
    }

    // 7) Formular ausgeben (Vorbefüllen)
    $title_raw      = $post->post_title;
    $content        = $post->post_content;
    $pd             = get_post_meta($edit_id, '_cnp_publish_date', true);
    $du             = get_post_meta($edit_id, '_cnp_display_until', true);
    $cnp_news_url   = get_post_meta($edit_id, '_cnp_news_url', true);
    $cats_selected  = wp_get_post_terms($edit_id, 'category', array('fields'=>'ids'));

    ?>
    <div class="cnp-edit-form">
        <h2>News bearbeiten (ID: <?php echo intval($edit_id); ?>)</h2>
        <form method="post" enctype="multipart/form-data">

            <label>Titel* (WYSIWYG):</label><br>
            <?php
            $title_editor_settings = array(
                'textarea_name' => 'cnp_news_title',
                'textarea_rows' => 2,
                'media_buttons' => true,
            );
            wp_editor($title_raw, 'cnp_news_title_editor_' . $edit_id, $title_editor_settings);
            ?>
            <br><br>

            <label>Inhalt*:</label><br>
            <?php
            $content_editor_settings = array(
                'textarea_name' => 'cnp_news_content',
                'textarea_rows' => 6,
                'media_buttons' => true,
            );
            wp_editor($content, 'cnp_news_content_editor_' . $edit_id, $content_editor_settings);
            ?>
            <br>

            <label>Veröffentlichungsdatum (dd.mm.yyyy):</label><br>
            <input type="text" name="cnp_publish_date" style="width:100%;"
                   placeholder="z.B. 31.12.2025"
                   value="<?php echo (!empty($pd)) ? date_i18n('d.m.Y', strtotime($pd)) : ''; ?>">
            <br><br>

            <label>Anzeigen bis (optional, dd.mm.yyyy):</label><br>
            <input type="text" name="cnp_display_until" style="width:100%;"
                   placeholder="z.B. 31.01.2026"
                   value="<?php echo (!empty($du)) ? date_i18n('d.m.Y', strtotime($du)) : ''; ?>">
            <br><br>

            <label>Kategorien:</label><br>
            <select name="cnp_news_category[]" multiple style="width:100%;">
                <?php
                $all_cats = get_categories(array('taxonomy'=>'category','hide_empty'=>false));
                foreach ($all_cats as $c) {
                    $sel = in_array($c->term_id, $cats_selected) ? 'selected' : '';
                    echo '<option value="' . intval($c->term_id) . '" ' . $sel . '>' . esc_html($c->name) . '</option>';
                }
                ?>
            </select>
            <br><br>

            <label>Neue Kategorie (optional):</label><br>
            <input type="text" name="cnp_new_category" style="width:100%;"
                   placeholder="Name...">
            <br><br>

            <label>Beitragsbild (optional):</label><br>
            <input type="file" name="cnp_featured_image" accept="image/*">
            <br><br>

            <label>Externe URL (optional):</label><br>
            <input type="url" name="cnp_news_url" style="width:100%;"
                   value="<?php echo esc_attr($cnp_news_url); ?>">
            <br><br>

            <?php wp_nonce_field('cnp_update_news_' . $edit_id, 'cnp_nonce'); ?>
            <button type="submit" name="cnp_update_news" class="button button-primary">Aktualisieren</button>
            <a href="<?php echo esc_url(remove_query_arg('cnp_edit_news')); ?>" class="button">Abbrechen</a>
        </form>
    </div>
    <?php

    return ob_get_clean();
}
add_shortcode('news-edit-form', 'cnp_news_edit_form_shortcode');


