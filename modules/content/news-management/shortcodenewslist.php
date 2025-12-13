/**
 * Shortcode [news-list]
 *
 * Zeigt eine Tabelle aller Einträge des Custom Post Types "newsbereich"
 * (z. B. publish + future). Jeder Eintrag hat:
 *  - Vorschaubild (cnp_news_thumbnail)
 *  - Nur-Text-Titel
 *  - Veröffentlichung / Anzeigen-bis
 *  - Buttons "Bearbeiten" / "Löschen"
 *
 * Benötigt:
 *  1) current_user_can('edit_newsbereiche'), um die Liste zu sehen
 *  2) current_user_can('edit_others_newsbereiche'), um fremde Einträge anzuzeigen
 *  3) current_user_can('delete_newsbereich', $post_id), um Einträge löschen zu dürfen
 */
function cnp_news_list_shortcode() {
    // 1) Berechtigungscheck
    if (!current_user_can('edit_newsbereiche')) {
        return __('Keine Berechtigung, News zu sehen.', 'custom-news-plugin');
    }

    ob_start();
    ?>
    <div class="cnp-news-list">
        <h2>Liste aller News</h2>
        <?php
        // 2) Query-Einstellungen
        $args = array(
            'post_type'      => 'newsbereich',
            // Falls du "future" mit anzeigen willst:
            'post_status'    => array('publish', 'future'),
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        );

        // Nur eigene News, wenn User nicht "edit_others_newsbereiche" kann
        if (!current_user_can('edit_others_newsbereiche')) {
            $args['author'] = get_current_user_id();
        }

        // 3) Posts holen
        $query = new WP_Query($args);

        if (!$query->have_posts()) {
            echo '<p>Keine News gefunden.</p>';
        } else {
            // Tabelle
            echo '<table class="widefat fixed striped">';
            echo '<thead><tr>
                  <th>Bild</th>
                  <th>Titel (nur Text)</th>
                  <th>Veröffentlicht am</th>
                  <th>Anzeigen bis</th>
                  <th>Status</th>
                  <th>Aktionen</th>
                  </tr></thead><tbody>';

            while ($query->have_posts()) {
                $query->the_post();
                $news_id = get_the_ID();

                // a) Metadaten holen (z. B. publish_date, display_until)
                $publish_date = get_post_meta($news_id, '_cnp_publish_date', true);
                $display_until = get_post_meta($news_id, '_cnp_display_until', true);

                // b) Datumsformatieren
                $pd_str = '';
                if ($publish_date) {
                    $time = strtotime($publish_date);
                    $pd_str = $time ? date_i18n('d.m.Y', $time) : esc_html($publish_date);
                }
                $du_str = '';
                if ($display_until) {
                    $time2 = strtotime($display_until);
                    $du_str = $time2 ? date_i18n('d.m.Y', $time2) : esc_html($display_until);
                }

                // c) Post-Status
                $status_obj = get_post_status_object(get_post_status($news_id));
                $status_label = $status_obj ? $status_obj->label : 'Unbekannt';

                // d) Titel => Nur-Text
                $plain_title = wp_strip_all_tags(get_the_title());

                // e) Thumbnail
                $thumb_html = (has_post_thumbnail($news_id)) 
                    ? get_the_post_thumbnail($news_id, 'cnp_news_thumbnail') 
                    : 'Kein Bild';

                // f) Nonce für das Löschen
                $delete_nonce = wp_create_nonce('cnp_delete_news_' . $news_id);

                echo '<tr>';
                echo '<td>' . $thumb_html . '</td>';
                echo '<td>' . esc_html($plain_title) . '</td>';
                echo '<td>' . (!empty($pd_str) ? $pd_str : '-') . '</td>';
                echo '<td>' . (!empty($du_str) ? $du_str : '-') . '</td>';
                echo '<td>' . esc_html($status_label) . '</td>';
                echo '<td>
                        <a href="' . esc_url(add_query_arg('cnp_edit_news', $news_id)) . '" 
                           class="button">Bearbeiten</a>
                        <a href="' . esc_url(add_query_arg(array('cnp_delete_news' => $news_id, 'cnp_nonce' => $delete_nonce))) . '"
                           class="button button-secondary"
                           onclick="return confirm(\'Wirklich löschen?\');">Löschen</a>
                      </td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            wp_reset_postdata();
        }
        ?>
    </div>

    <?php
    // 4) Lösch-Aktion abfangen
    if (isset($_GET['cnp_delete_news'])) {
        $del_id = (int) $_GET['cnp_delete_news'];
        // Sicherheitsprüfung
        if (!isset($_GET['cnp_nonce']) || !wp_verify_nonce($_GET['cnp_nonce'], 'cnp_delete_news_' . $del_id)) {
            echo '<div class="notice notice-error"><p>Ungültige Sicherheitsprüfung fürs Löschen.</p></div>';
        } else {
            // Berechtigung zum Löschen dieses Beitrags?
            if (current_user_can('delete_newsbereich', $del_id)) {
                wp_delete_post($del_id, true);  // Hard delete
                // Nach dem Löschen => Reload ohne Parameter
                wp_safe_redirect(remove_query_arg(array('cnp_delete_news','cnp_nonce')));
                exit;
            } else {
                echo '<div class="notice notice-error"><p>Keine Berechtigung zum Löschen.</p></div>';
            }
        }
    }

    return ob_get_clean();
}
add_shortcode('news-list', 'cnp_news_list_shortcode');
