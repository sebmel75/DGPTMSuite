<?php
/**
 * [newsanzahl anzahl="5"]
 * Gibt "true"/"false" zurück, wenn mindestens die angegebene Anzahl an gültigen News vorhanden ist.
 */
function cnp_newsanzahl_shortcode($atts){
    $atts = shortcode_atts(array('anzahl' => 1), $atts, 'newsanzahl');
    $today = date('Y-m-d');
    $args = array(
        'post_type'      => 'newsbereich',
        'post_status'    => 'publish',
        'posts_per_page' => (int)$atts['anzahl'],
        'fields'         => 'ids',
        'no_found_rows'  => false,
        'meta_query'     => array(
            'relation' => 'AND',
            array(
                'key'     => '_cnp_publish_date',
                'value'   => $today,
                'compare' => '<=',
                'type'    => 'DATE',
            ),
            array(
                'relation' => 'OR',
                array(
                    'key'     => '_cnp_display_until',
                    'value'   => '',
                    'compare' => '=',
                ),
                array(
                    'key'     => '_cnp_display_until',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                )
            )
        )
    );
    $q = new WP_Query($args);
    $found = $q->found_posts;
    wp_reset_postdata();
    return ($found >= (int)$atts['anzahl']) ? 'true' : 'false';
}
add_shortcode('newsanzahl','cnp_newsanzahl_shortcode');


/**
 * [news-valid-list categories="slug1,slug2" textonly="false" link="modal|url|none"
 *                  enddate="dd.mm.yyyy" group="false" thumb_size="thumbnail"]
 */
function cnp_news_valid_list_shortcode($atts){
    $atts = shortcode_atts(
        array(
            'categories' => '',
            'textonly'   => 'false',
            'link'       => 'none',
            'enddate'    => '',
            'group'      => 'false',
            'thumb_size' => 'thumbnail'
        ),
        $atts, 'news-valid-list'
    );
    $today = date('Y-m-d');
    $enddate_ymd = '';
    if (!empty($atts['enddate'])) {
        $enddate_ymd = cnp_convert_date_or_fallback_today($atts['enddate']);
    }

    $meta_query = array(
        'relation' => 'AND',
        array(
            'key'     => '_cnp_publish_date',
            'value'   => $today,
            'compare' => '<=',
            'type'    => 'DATE',
        ),
        array(
            'relation' => 'OR',
            array(
                'key'     => '_cnp_display_until',
                'value'   => '',
                'compare' => '=',
            ),
            array(
                'key'     => '_cnp_display_until',
                'value'   => $today,
                'compare' => '>=',
                'type'    => 'DATE',
            )
        )
    );
    if (!empty($enddate_ymd) && $enddate_ymd !== $today) {
        $meta_query[] = array(
            'key'     => '_cnp_publish_date',
            'value'   => $enddate_ymd,
            'compare' => '<=',
            'type'    => 'DATE',
        );
    }

    $tax_query = array();
    if (!empty($atts['categories'])) {
        $cat_slugs = array_map('trim', explode(',', $atts['categories']));
        $tax_query[] = array(
            'taxonomy' => 'category',
            'field'    => 'slug',
            'terms'    => $cat_slugs,
            'operator' => 'IN',
        );
    }

    $args = array(
        'post_type'      => 'newsbereich',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
        'no_found_rows'  => true,
        'meta_query'     => $meta_query,
    );
    if (!empty($tax_query)) {
        $args['tax_query'] = $tax_query;
    }

    $q = new WP_Query($args);
    if (!$q->have_posts()) {
        return '<p>Keine gültigen News gefunden.</p>';
    }

    $textonly  = (strtolower($atts['textonly']) === 'true');
    $link_mode = strtolower($atts['link']);
    $group     = (strtolower($atts['group']) === 'true');
    $thumb_size= trim($atts['thumb_size']);

    $all_posts = $q->posts;
    wp_reset_postdata();

    if (!function_exists('cnp_format_news_title_for_link')) {
        function cnp_format_news_title_for_link($pid, $title, $post_content, $link_mode) {
            $plainTitle = wp_strip_all_tags($title);
            if ($link_mode === 'modal') {
                $mid = 'cnp-modal-' . $pid;
                $ret = '<a href="#" class="cnp-open-modal" data-target="#' . $mid . '">' . esc_html($plainTitle) . '</a>';
                $ret .= '
                <div id="' . $mid . '" class="cnp-modal-overlay">
                    <div class="cnp-modal-content">
                        <button class="cnp-close-modal" data-close="#' . $mid . '">&times;</button>
                        <h2>' . esc_html($plainTitle) . '</h2>
                        <div class="cnp-modal-body">' . apply_filters('the_content', $post_content) . '</div>
                    </div>
                </div>';
                return $ret;
            } elseif ($link_mode === 'url') {
                $meta_url = get_post_meta($pid, '_cnp_news_url', true);
                if (!empty($meta_url)) {
                    return '<a href="' . esc_url($meta_url) . '" target="_blank" rel="noopener">' . esc_html($plainTitle) . '</a>';
                }
                return esc_html($plainTitle);
            } else {
                return esc_html($plainTitle);
            }
        }
    }

    $output = '';

    if (!$group) {
        $output .= '<table class="cnp-valid-news-table">';
        foreach ($all_posts as $pObj) {
            $pid = $pObj->ID;
            $title_out = cnp_format_news_title_for_link($pid, get_the_title($pObj), $pObj->post_content, $link_mode);
            $img_html = '';
            if (!$textonly && has_post_thumbnail($pid)) {
                $img_html = get_the_post_thumbnail($pid, $thumb_size);
            }
            $output .= '<tr>';
            $output .= '<td class="cnp-valid-thumb">' . ($img_html ?: '') . '</td>';
            $output .= '<td class="cnp-valid-title">' . $title_out . '</td>';
            $output .= '</tr>';
        }
        $output .= '</table>';
    } else {
        $cat_map = array();
        $all_cat_names = array();
        foreach ($all_posts as $pObj) {
            $cidArr = wp_get_post_terms($pObj->ID, 'category');
            if (empty($cidArr)) {
                $cat_map[0][] = $pObj;
                $all_cat_names[0] = 'Allgemein (keine)';
            } else {
                foreach ($cidArr as $cTerm) {
                    $cID = $cTerm->term_id;
                    $cat_map[$cID][] = $pObj;
                    $all_cat_names[$cID] = $cTerm->name;
                }
            }
        }
        $sorted_cIDs = array_keys($cat_map);
        usort($sorted_cIDs, function($a, $b) use($all_cat_names){
            $A = isset($all_cat_names[$a]) ? $all_cat_names[$a] : '';
            $B = isset($all_cat_names[$b]) ? $all_cat_names[$b] : '';
            return strcasecmp($A, $B);
        });
        foreach ($sorted_cIDs as $cid) {
            $catName = isset($all_cat_names[$cid]) ? $all_cat_names[$cid] : '(unbekannt)';
            $posts_in_cat = $cat_map[$cid];
            $output .= '<h3>' . esc_html($catName) . '</h3>';
            $output .= '<table class="cnp-valid-news-table">';
            foreach ($posts_in_cat as $pObj) {
                $pxID = $pObj->ID;
                $title_out = cnp_format_news_title_for_link($pxID, get_the_title($pObj), $pObj->post_content, $link_mode);
                $img_html = '';
                if (!$textonly && has_post_thumbnail($pxID)) {
                    $img_html = get_the_post_thumbnail($pxID, $thumb_size);
                }
                $output .= '<tr>';
                $output .= '<td class="cnp-valid-thumb">' . ($img_html ?: '') . '</td>';
                $output .= '<td class="cnp-valid-title">' . $title_out . '</td>';
                $output .= '</tr>';
            }
            $output .= '</table>';
        }
    }
    return $output;
}
add_shortcode('news-valid-list','cnp_news_valid_list_shortcode');


/**
 * Shortcode: [news]
 */
function cnp_single_news_shortcode($atts) {
    $atts = shortcode_atts(
        array(
            'fields'       => '',
            'field'        => 'title',
            'count'        => 20,
            'offset'       => 0,
            'position'     => 1,
            'link'         => '',
            'category'     => '',
            'id'           => 0,
            'order'        => 'date',
            'imagesize'    => 'cnp_news_thumbnail',
            'noposts_text' => 'Keine News gefunden.',
        ),
        $atts,
        'news'
    );

    $fields_to_show = !empty($atts['fields']) ? array_map('trim', explode(',', $atts['fields'])) : array($atts['field']);

    if (!empty($atts['id'])) {
        $the_post = get_post((int)$atts['id']);
        if (!$the_post || $the_post->post_type !== 'newsbereich') {
            return '<p>News mit ID ' . intval($atts['id']) . ' nicht gefunden.</p>';
        }
    } else {
        $args = array(
            'post_type'      => 'newsbereich',
            'posts_per_page' => (int)$atts['count'],
            'offset'         => (int)$atts['offset'],
            'no_found_rows'  => true,
        );
        if (strtolower($atts['order']) === 'random') {
            $args['orderby'] = 'rand';
        } else {
            $args['orderby'] = 'date';
            $args['order']   = 'DESC';
        }
        if (!empty($atts['category'])) {
            $cat_slugs = array_map('trim', explode(',', $atts['category']));
            $args['tax_query'] = array(
                array(
                    'taxonomy' => 'category',
                    'field'    => 'slug',
                    'terms'    => $cat_slugs,
                    'operator' => 'IN',
                )
            );
        }
        $query = new WP_Query($args);
        if (!$query->have_posts()) {
            return '<p>' . esc_html($atts['noposts_text']) . '</p>';
        }
        $pos_idx = max(0, (int)$atts['position'] - 1);
        $posts_arr = $query->posts;
        if (!isset($posts_arr[$pos_idx])) {
            return sprintf('<p>Es gibt nicht genügend News, um Position %d anzuzeigen.</p>', $atts['position']);
        }
        $the_post = $posts_arr[$pos_idx];
        wp_reset_postdata();
    }

    $output_fields = array();
    foreach ($fields_to_show as $fld) {
        $fld_output = '';
        switch ($fld) {
            case 'title':
                $fld_output = wp_strip_all_tags($the_post->post_title);
                break;
            case 'excerpt':
                $fld_output = !empty($the_post->post_excerpt) ? wp_strip_all_tags($the_post->post_excerpt) : 'Kein Auszug vorhanden.';
                break;
            case 'content':
                $fld_output = apply_filters('the_content', $the_post->post_content);
                break;
            case 'author':
                $fld_output = get_the_author_meta('display_name', $the_post->post_author);
                break;
            case 'image':
                $fld_output = has_post_thumbnail($the_post->ID) ? get_the_post_thumbnail($the_post->ID, $atts['imagesize']) : 'Kein Bild vorhanden.';
                break;
            case 'publish_date':
                $pd = get_post_meta($the_post->ID, '_cnp_publish_date', true);
                if ($pd) {
                    $ts = strtotime($pd);
                    $fld_output = $ts ? date_i18n('d.m.Y', $ts) : esc_html($pd);
                } else {
                    $fld_output = 'Kein Veröffentlichungsdatum.';
                }
                break;
            case 'display_until':
                $du = get_post_meta($the_post->ID, '_cnp_display_until', true);
                if ($du) {
                    $ts = strtotime($du);
                    $fld_output = $ts ? date_i18n('d.m.Y', $ts) : esc_html($du);
                } else {
                    $fld_output = 'Kein Enddatum.';
                }
                break;
            case 'validity':
                $pd = get_post_meta($the_post->ID, '_cnp_publish_date', true);
                $du = get_post_meta($the_post->ID, '_cnp_display_until', true);
                $pd_str = $pd ? date_i18n('d.m.Y', strtotime($pd)) : '';
                $du_str = $du ? date_i18n('d.m.Y', strtotime($du)) : '';
                if ($pd_str && $du_str) {
                    $fld_output = 'Gültig von ' . $pd_str . ' bis ' . $du_str;
                } elseif ($pd_str) {
                    $fld_output = 'Gültig ab ' . $pd_str;
                } else {
                    $fld_output = 'Keine Gültigkeit.';
                }
                break;
            case 'permalink':
                $fld_output = get_permalink($the_post->ID);
                break;
            default:
                $fld_output = sprintf('Unbekanntes Feld "%s".', esc_html($fld));
                break;
        }
        $output_fields[] = $fld_output;
    }

    $link_type = trim($atts['link']);
    if (!empty($link_type) && !empty($output_fields)) {
        $meta_url = get_post_meta($the_post->ID, '_cnp_news_url', true);
        $base_content = $output_fields[0];
        if ($link_type === 'url') {
            if (!empty($meta_url)) {
                $output_fields[0] = '<a href="' . esc_url($meta_url) . '" target="_blank" rel="noopener">' . $base_content . '</a>';
            }
        } elseif ($link_type === 'modal') {
            $mid = 'cnp-modal-' . $the_post->ID;
            $title_plain = wp_strip_all_tags($the_post->post_title);
            $link_out = '<a href="#" class="cnp-open-modal" data-target="#' . esc_attr($mid) . '">' . $base_content . '</a>';
            $modal_html = '
            <div id="' . esc_attr($mid) . '" class="cnp-modal-overlay">
                <div class="cnp-modal-content">
                    <button class="cnp-close-modal" data-close="#' . esc_attr($mid) . '">&times;</button>
                    <h2>' . esc_html($title_plain) . '</h2>
                    <div class="cnp-modal-body">' . apply_filters('the_content', $the_post->post_content) . '</div>
                </div>
            </div>';
            $output_fields[0] = $link_out . $modal_html;
        } elseif ($link_type === 'permalink') {
            $perma = get_permalink($the_post->ID);
            $output_fields[0] = '<a href="' . esc_url($perma) . '" >' . $base_content . '</a>';
        } else {
            if (filter_var($link_type, FILTER_VALIDATE_URL)) {
                $output_fields[0] = '<a href="' . esc_url($link_type) . '" target="_blank" rel="noopener">' . $base_content . '</a>';
            }
        }
    }

    $final = '<div class="cnp-news-fields">';
    foreach ($output_fields as $i => $f) {
        $final .= '<div class="cnp-news-field cnp-news-field--' . $i . '">' . $f . '</div>';
    }
    $final .= '</div>';
    return $final;
}
add_shortcode('news','cnp_single_news_shortcode');


/**
 * Shortcode: [news-cat-available category="slug"]
 */
function cnp_news_cat_available_shortcode($atts){
    $atts = shortcode_atts(
        array(
            'category' => '',
        ),
        $atts,
        'news-cat-available'
    );
    if (empty($atts['category'])) {
        return 'false';
    }
    $today = date('Y-m-d');
    $args = array(
        'post_type'      => 'newsbereich',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'tax_query'      => array(
            array(
                'taxonomy' => 'category',
                'field'    => 'slug',
                'terms'    => $atts['category'],
            ),
        ),
        'meta_query' => array(
            'relation' => 'AND',
            array(
                'key'     => '_cnp_publish_date',
                'value'   => $today,
                'compare' => '<=',
                'type'    => 'DATE',
            ),
            array(
                'relation' => 'OR',
                array(
                    'key'     => '_cnp_display_until',
                    'compare' => 'NOT EXISTS',
                ),
                array(
                    'key'     => '_cnp_display_until',
                    'value'   => $today,
                    'compare' => '>=',
                    'type'    => 'DATE',
                ),
            ),
        ),
    );
    $q = new WP_Query($args);
    $found = count($q->posts);
    wp_reset_postdata();
    return ($found > 0) ? 'true' : 'false';
}
add_shortcode('news-cat-available','cnp_news_cat_available_shortcode');


/**
 * Shortcode: [news-modal]
 * VOLLSTÄNDIG AKTUALISIERT: Verwendet jetzt DGPTM-Styles mit vollständigem Modal-Design
 * 
 * Attribute:
 * - category: Kategorie-Slug
 * - type: "news" oder "event" (bestimmt das Grid-Layout)
 * - count / posts_per_page: Anzahl der Beiträge (Standard: 20)
 * - layout: "list" oder "grid" (Standard: list)
 * - sort_field: publish_date, event_start, display_until
 * - sort_dir: ASC oder DESC (Standard: DESC)
 */
function cnp_news_modal_shortcode($atts){
    $atts = shortcode_atts(
        array(
            'category'       => '',
            'type'           => '',
            'count'          => 20,
            'posts_per_page' => 0,
            'title_length'   => 0,
            'layout'         => 'list',
            'show_pubdate'   => 'false',
            'format'         => '<h6><strong>{title}</strong></h6>',
            'sort_field'     => '',
            'sort_dir'       => 'DESC',
        ),
        $atts,
        'news-modal'
    );

    // posts_per_page als Alias für count
    if ((int)$atts['posts_per_page'] > 0) {
        $atts['count'] = (int)$atts['posts_per_page'];
    }

    $today = date('Y-m-d');
    $meta_query = array(
        'relation' => 'AND',
        array(
            'relation' => 'OR',
            array(
                'key'     => '_cnp_publish_date',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => '_cnp_publish_date',
                'value'   => $today,
                'compare' => '<=',
                'type'    => 'DATE',
            ),
        ),
        array(
            'relation' => 'OR',
            array(
                'key'     => '_cnp_display_until',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'     => '_cnp_display_until',
                'value'   => '',
                'compare' => '=',
            ),
            array(
                'key'     => '_cnp_display_until',
                'value'   => $today,
                'compare' => '>=',
                'type'    => 'DATE',
            ),
        ),
    );

    $sort_field = trim($atts['sort_field']);
    $sort_dir   = strtoupper(trim($atts['sort_dir']));
    if (!in_array($sort_dir, array('ASC','DESC'))) {
        $sort_dir = 'DESC';
    }

    $args = array(
        'post_type'      => 'newsbereich',
        'post_status'    => 'publish',
        'posts_per_page' => (int)$atts['count'],
        'no_found_rows'  => true,
        'meta_query'     => $meta_query,
    );
    
    if (!empty($atts['category'])) {
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'category',
                'field'    => 'slug',
                'terms'    => $atts['category'],
            )
        );
    }
    
    if (!empty($sort_field)) {
        $args['meta_key']   = '_cnp_' . $sort_field;
        $args['orderby']    = 'meta_value';
        $args['order']      = $sort_dir;
        $args['meta_type']  = 'DATE';
    } else {
        $args['orderby'] = 'date';
        $args['order']   = $sort_dir;
    }

    $q = new WP_Query($args);
    if (!$q->have_posts()) {
        return '<p>Keine (gültigen) News vorhanden.</p>';
    }

    $title_len = (int)$atts['title_length'];
    $show_date = (strtolower($atts['show_pubdate']) === 'true');

    // CSS nur einmal pro Seite ausgeben
    static $modal_css_printed = false;
    if (!$modal_css_printed) {
        cnp_output_dgptm_styles();
        $modal_css_printed = true;
    }

    ob_start();

    // Grid-Layout mit DGPTM-Styles
    if ($atts['layout'] === 'grid') {
        $type = isset($atts['type']) ? trim(strtolower($atts['type'])) : '';
        if ($type === 'event') { 
            $is_events = true; 
        } elseif ($type === 'news') { 
            $is_events = false; 
        } else { 
            $is_events = (trim(strtolower($atts['category'])) === 'events'); 
        }
        
        echo '<div class="dgptm__container' . (($type === 'news') ? ' dgptm__container--news-doublewide' : '') . '">';

        while ($q->have_posts()) {
            $q->the_post();
            $pid = get_the_ID();
            $title_raw = get_the_title();
            $content_raw = get_the_content();
            $plain_title = wp_strip_all_tags($title_raw);

            if($title_len > 0 && mb_strlen($plain_title) > $title_len){
                $plain_title = mb_substr($plain_title, 0, $title_len).'...';
            }

            $mid = 'cnp-modal-'.$pid;

            // Event-spezifische Felder
            $es = get_post_meta($pid, '_cnp_event_start', true);
            $du = get_post_meta($pid, '_cnp_display_until', true);
            $loc = get_post_meta($pid, '_cnp_event_location', true) ?: '';
            
            $tags_str = ''; 
            $the_tags = wp_get_post_terms($pid, 'post_tag'); 
            if (!is_wp_error($the_tags) && !empty($the_tags)) {
                $tag_names = array_map(function($t){ return $t->name; }, $the_tags);
                $tags_str = implode(', ', $tag_names);
            }
            
            $es_str = $es ? date_i18n('d.m.Y', strtotime($es)) : '';
            $du_str = $du ? date_i18n('d.m.Y', strtotime($du)) : '';
            $date_display = $es_str;
            if ($du_str) {
                $date_display = $es_str . ' – ' . $du_str;
            }

            // URL und Edugrant für Modal-Footer
            $url = get_post_meta($pid, '_cnp_news_url', true);
            $edugrant_val = get_post_meta($pid, '_cnp_edugrant', true);

            ?>
            <div class="<?php echo $is_events ? 'dgptm__event-item' : 'dgptm__news-item'; ?>">
                <div class="<?php echo $is_events ? 'dgptm__event-item-inner' : 'dgptm__news-item-inner'; ?>">
                    <?php if ($is_events): ?>
                        <div class="dgptm__event-item-date">
                            <?php echo esc_html($date_display); ?>
                        </div>
                        <div class="dgptm__event-item-tags">
                            <?php echo esc_html($tags_str); ?>
                        </div>
                        <div class="dgptm__event-item-body">
                            <?php echo esc_html($plain_title); ?>
                        </div>
                        <div class="dgptm__event-item-place">
                            <?php echo esc_html($loc); ?>
                        </div>
                        <div class="dgptm__event-item-button">
                            <a href="#" class="cnp-open-modal" data-target="#<?php echo esc_attr($mid); ?>">mehr …</a>
                        </div>
                    <?php else: ?>
                        <div class="dgptm__news-item-headline">
                            <?php echo esc_html($plain_title); ?>
                        </div>
                        <?php
                        $excerpt_text = get_the_excerpt();
                        if (!$excerpt_text) {
                            $excerpt_text = wp_trim_words(strip_tags($content_raw), 30, '…');
                        }
                        ?>
                        <div class="dgptm__news-item-body">
                            <?php echo esc_html($excerpt_text); ?>
                        </div>
                        <?php $pdf_url = get_post_meta($pid, '_cnp_pdf_url', true); ?>
                        <?php if (!empty($pdf_url)): ?>
                            <div class="dgptm__news-item-pdfbutton">
                                <a href="<?php echo esc_url($pdf_url); ?>" target="_blank" rel="noopener" download>PDF</a>
                            </div>
                        <?php endif; ?>
                        <div class="dgptm__news-item-button">
                            <a href="#" class="cnp-open-modal" data-target="#<?php echo esc_attr($mid); ?>">mehr …</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- DGPTM Modal-Inhalt (vollständiges Design aus Vorlage) -->
            <div id="<?php echo esc_attr($mid); ?>" class="dgptm__popup">
                <div class="dgptm__popup-bg"></div>
                <div class="dgptm__popup-content">
                    <div class="dgptm__popup-close">X</div>
                    
                    <?php if ($is_events): ?>
                        <!-- Event-Header mit Datum und Ort -->
                        <div class="dgptm__popup-header">
                            <div class="dgptm__popup-date">
                                <?php echo esc_html($date_display); ?>
                            </div>
                            <?php if (!empty($loc)): ?>
                            <div class="dgptm__popup-place">
                                <?php echo esc_html($loc); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="dgptm__popup-inner">
                        <div class="dgptm__popup-headline">
                            <?php echo esc_html($title_raw); ?>
                        </div>
                        
                        <?php if (has_post_thumbnail($pid)): ?>
                        <div class="dgptm__popup-image">
                            <div class="image-as-background">
                                <?php echo get_the_post_thumbnail($pid, 'large'); ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="dgptm__popup-text">
                            <?php echo apply_filters('the_content', $content_raw); ?>
                        </div>
                        
                        <?php if (!empty($url)): ?>
                        <div class="dgptm__popup-link">
                            <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">
                                Seite aufrufen ...
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="dgptm__popup-footer">
                        <div class="dgptm__popup-footer-text">
                            Diesen Beitrag teilen
                        </div>
                        <div class="dgptm__popup-footer-links">
                            <ul>
                                <li>
                                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(get_permalink($pid)); ?>" target="_blank" rel="noopener" title="Auf Facebook teilen">
                                        <svg class="e-font-icon-svg e-fab-facebook" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M504 256C504 119 393 8 256 8S8 119 8 256c0 123.78 90.69 226.38 209.25 245V327.69h-63V256h63v-54.64c0-62.15 37-96.48 93.67-96.48 27.14 0 55.52 4.84 55.52 4.84v61h-31.28c-30.8 0-40.41 19.12-40.41 38.73V256h68.78l-11 71.69h-57.78V501C413.31 482.38 504 379.78 504 256z"></path></svg>
                                    </a>
                                </li>
                                <li>
                                    <a href="https://x.com/intent/tweet?url=<?php echo urlencode(get_permalink($pid)); ?>&text=<?php echo urlencode($title_raw); ?>" target="_blank" rel="noopener" title="Auf X teilen">
                                        <svg class="e-font-icon-svg e-fab-x-twitter" viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M389.2 48h70.6L305.6 224.2 487 464H345L233.7 318.6 106.5 464H35.8L200.7 275.5 26.8 48H172.4L272.9 180.9 389.2 48zM364.4 421.8h39.1L151.1 88h-42L364.4 421.8z"></path></svg>
                                    </a>
                                </li>
                                <li>
                                    <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode(get_permalink($pid)); ?>&title=<?php echo urlencode($title_raw); ?>" target="_blank" rel="noopener" title="Auf LinkedIn teilen">
                                        <svg class="e-font-icon-svg e-fab-linkedin" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg"><path d="M416 32H31.9C14.3 32 0 46.5 0 64.3v383.4C0 465.5 14.3 480 31.9 480H416c17.6 0 32-14.5 32-32.3V64.3c0-17.8-14.4-32.3-32-32.3zM135.4 416H69V202.2h66.5V416zm-33.2-243c-21.3 0-38.5-17.3-38.5-38.5S80.9 96 102.2 96c21.2 0 38.5 17.3 38.5 38.5 0 21.3-17.2 38.5-38.5 38.5zm282.1 243h-66.4V312c0-24.8-.5-56.7-34.5-56.7-34.6 0-39.9 27-39.9 54.9V416h-66.4V202.2h63.7v29.2h.9c8.9-16.8 30.6-34.5 62.9-34.5 67.2 0 79.7 44.3 79.7 101.9V416z"></path></svg>
                                    </a>
                                </li>
                                <li>
                                    <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($title_raw . ' ' . get_permalink($pid)); ?>" target="_blank" rel="noopener" title="Per WhatsApp teilen">
                                        <svg class="e-font-icon-svg e-fab-whatsapp" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"></path></svg>
                                    </a>
                                </li>
                                <li>
                                    <a href="https://www.instagram.com/" target="_blank" rel="noopener" title="Auf Instagram folgen" class="cnp-share-instagram" data-url="<?php echo esc_attr(get_permalink($pid)); ?>">
                                        <svg class="e-font-icon-svg e-fab-instagram" viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg"><path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z"></path></svg>
                                    </a>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }
        wp_reset_postdata();
        echo '</div><!-- .dgptm__container -->';

    } else {
        // Listenansicht
        ?>
        <ul class="cnp-news-modal-listview">
        <?php
        $found_all = $q->post_count;
        $counter   = 0;
        while($q->have_posts()){
            $q->the_post();
            $pid = get_the_ID();
            $title_raw = get_the_title();
            $content_raw = get_the_content();

            $pd = get_post_meta($pid, '_cnp_publish_date', true);
            $es = get_post_meta($pid, '_cnp_event_start', true);
            $du = get_post_meta($pid, '_cnp_display_until', true);
            $loc = get_post_meta($pid, '_cnp_event_location', true);
            $url = get_post_meta($pid, '_cnp_news_url', true);

            $pd_str = $pd ? date_i18n('d.m.Y', strtotime($pd)) : '';
            $es_str = $es ? date_i18n('d.m.Y', strtotime($es)) : '';
            $du_str = $du ? date_i18n('d.m.Y', strtotime($du)) : '';
            $loc_str= $loc ?: '';
            $url_str= $url ?: '';

            $url_button = '';
            if(!empty($url_str)){
                $url_button = '<a href="'.esc_url($url_str).'" class="button" target="_blank">Seite aufrufen</a>';
            }
            $edugrant_val = get_post_meta($pid, '_cnp_edugrant', true);
            $edugrant_button = '';
            if($edugrant_val == 1){
                $edugrant_button = '<a href="https://perfusiologie.de/veranstaltungen/educational-grant-der-dgptm/educational-grant-antrag/" class="button" target="_blank">Jetzt Educational Grant beantragen</a>';
            }
            $link_bar = '';
            if($url_button || $edugrant_button){
                $link_bar = '<div class="cnp-modal-linkbar" style="text-align: center;">';
                if($url_button){
                    $link_bar .= $url_button;
                }
                if($url_button && $edugrant_button){
                    $link_bar .= ' &bull; ';
                }
                if($edugrant_button){
                    $link_bar .= $edugrant_button;
                }
                $link_bar .= '</div>';
            }

            $plain_title = wp_strip_all_tags($title_raw);
            if($title_len > 0 && mb_strlen($plain_title) > $title_len){
                $plain_title = mb_substr($plain_title, 0, $title_len).'...';
            }

            $mid = 'cnp-modal-'.$pid;

            $date_add = '';
            if($show_date && !empty($pd_str)){
                $date_add = ' &bull; Veröffentlicht am '.$pd_str;
            }
            $link_text = $plain_title.$date_add;
            ?>
            <li>
              <a href="#" class="cnp-open-modal" data-target="#<?php echo esc_attr($mid); ?>">
                <?php
                if (!empty($atts['format'])) {
                    $item_output = $atts['format'];
                    $item_output = str_replace('{title}',          $plain_title, $item_output);
                    $item_output = str_replace('{event_start}',    $es_str,      $item_output);
                    $item_output = str_replace('{display_until}',  $du_str,      $item_output);
                    $item_output = str_replace('{event_location}', $loc_str,     $item_output);
                    echo $item_output;
                } else {
                    echo esc_html($link_text);
                }
                ?>
              </a>
            </li>
            <?php
            $counter++;
            if($counter < $found_all){
                echo '<hr/>';
            }
            ?>
            <div id="<?php echo esc_attr($mid); ?>" class="cnp-modal-overlay">
              <div class="cnp-modal-content">
                <button class="cnp-close-modal" data-close="#<?php echo esc_attr($mid); ?>">&times;</button>
                <h2><?php echo esc_html($title_raw); ?></h2>
                <div class="cnp-modal-body">
                  <?php echo apply_filters('the_content', $content_raw); ?>
                  <?php if($link_bar){ echo $link_bar; } ?>
                </div>
                <div class="cnp-modal-share">
                  <div class="cnp-modal-share-text">Diesen Beitrag teilen</div>
                  <div class="cnp-modal-share-links">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo urlencode(get_permalink($pid)); ?>" target="_blank" rel="noopener" title="Auf Facebook teilen">
                      <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M504 256C504 119 393 8 256 8S8 119 8 256c0 123.78 90.69 226.38 209.25 245V327.69h-63V256h63v-54.64c0-62.15 37-96.48 93.67-96.48 27.14 0 55.52 4.84 55.52 4.84v61h-31.28c-30.8 0-40.41 19.12-40.41 38.73V256h68.78l-11 71.69h-57.78V501C413.31 482.38 504 379.78 504 256z"></path></svg>
                    </a>
                    <a href="https://x.com/intent/tweet?url=<?php echo urlencode(get_permalink($pid)); ?>&text=<?php echo urlencode($title_raw); ?>" target="_blank" rel="noopener" title="Auf X teilen">
                      <svg viewBox="0 0 512 512" xmlns="http://www.w3.org/2000/svg"><path d="M389.2 48h70.6L305.6 224.2 487 464H345L233.7 318.6 106.5 464H35.8L200.7 275.5 26.8 48H172.4L272.9 180.9 389.2 48zM364.4 421.8h39.1L151.1 88h-42L364.4 421.8z"></path></svg>
                    </a>
                    <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?php echo urlencode(get_permalink($pid)); ?>&title=<?php echo urlencode($title_raw); ?>" target="_blank" rel="noopener" title="Auf LinkedIn teilen">
                      <svg viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg"><path d="M416 32H31.9C14.3 32 0 46.5 0 64.3v383.4C0 465.5 14.3 480 31.9 480H416c17.6 0 32-14.5 32-32.3V64.3c0-17.8-14.4-32.3-32-32.3zM135.4 416H69V202.2h66.5V416zm-33.2-243c-21.3 0-38.5-17.3-38.5-38.5S80.9 96 102.2 96c21.2 0 38.5 17.3 38.5 38.5 0 21.3-17.2 38.5-38.5 38.5zm282.1 243h-66.4V312c0-24.8-.5-56.7-34.5-56.7-34.6 0-39.9 27-39.9 54.9V416h-66.4V202.2h63.7v29.2h.9c8.9-16.8 30.6-34.5 62.9-34.5 67.2 0 79.7 44.3 79.7 101.9V416z"></path></svg>
                    </a>
                    <a href="https://api.whatsapp.com/send?text=<?php echo urlencode($title_raw . ' ' . get_permalink($pid)); ?>" target="_blank" rel="noopener" title="Per WhatsApp teilen">
                      <svg viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg"><path d="M380.9 97.1C339 55.1 283.2 32 223.9 32c-122.4 0-222 99.6-222 222 0 39.1 10.2 77.3 29.6 111L0 480l117.7-30.9c32.4 17.7 68.9 27 106.1 27h.1c122.3 0 224.1-99.6 224.1-222 0-59.3-25.2-115-67.1-157zm-157 341.6c-33.2 0-65.7-8.9-94-25.7l-6.7-4-69.8 18.3L72 359.2l-4.4-7c-18.5-29.4-28.2-63.3-28.2-98.2 0-101.7 82.8-184.5 184.6-184.5 49.3 0 95.6 19.2 130.4 54.1 34.8 34.9 56.2 81.2 56.1 130.5 0 101.8-84.9 184.6-186.6 184.6zm101.2-138.2c-5.5-2.8-32.8-16.2-37.9-18-5.1-1.9-8.8-2.8-12.5 2.8-3.7 5.6-14.3 18-17.6 21.8-3.2 3.7-6.5 4.2-12 1.4-32.6-16.3-54-29.1-75.5-66-5.7-9.8 5.7-9.1 16.3-30.3 1.8-3.7.9-6.9-.5-9.7-1.4-2.8-12.5-30.1-17.1-41.2-4.5-10.8-9.1-9.3-12.5-9.5-3.2-.2-6.9-.2-10.6-.2-3.7 0-9.7 1.4-14.8 6.9-5.1 5.6-19.4 19-19.4 46.3 0 27.3 19.9 53.7 22.6 57.4 2.8 3.7 39.1 59.7 94.8 83.8 35.2 15.2 49 16.5 66.6 13.9 10.7-1.6 32.8-13.4 37.4-26.4 4.6-13 4.6-24.1 3.2-26.4-1.3-2.5-5-3.9-10.5-6.6z"></path></svg>
                    </a>
                    <a href="https://www.instagram.com/" target="_blank" rel="noopener" title="Auf Instagram folgen">
                      <svg viewBox="0 0 448 512" xmlns="http://www.w3.org/2000/svg"><path d="M224.1 141c-63.6 0-114.9 51.3-114.9 114.9s51.3 114.9 114.9 114.9S339 319.5 339 255.9 287.7 141 224.1 141zm0 189.6c-41.1 0-74.7-33.5-74.7-74.7s33.5-74.7 74.7-74.7 74.7 33.5 74.7 74.7-33.6 74.7-74.7 74.7zm146.4-194.3c0 14.9-12 26.8-26.8 26.8-14.9 0-26.8-12-26.8-26.8s12-26.8 26.8-26.8 26.8 12 26.8 26.8zm76.1 27.2c-1.7-35.9-9.9-67.7-36.2-93.9-26.2-26.2-58-34.4-93.9-36.2-37-2.1-147.9-2.1-184.9 0-35.8 1.7-67.6 9.9-93.9 36.1s-34.4 58-36.2 93.9c-2.1 37-2.1 147.9 0 184.9 1.7 35.9 9.9 67.7 36.2 93.9s58 34.4 93.9 36.2c37 2.1 147.9 2.1 184.9 0 35.9-1.7 67.7-9.9 93.9-36.2 26.2-26.2 34.4-58 36.2-93.9 2.1-37 2.1-147.8 0-184.8zM398.8 388c-7.8 19.6-22.9 34.7-42.6 42.6-29.5 11.7-99.5 9-132.1 9s-102.7 2.6-132.1-9c-19.6-7.8-34.7-22.9-42.6-42.6-11.7-29.5-9-99.5-9-132.1s-2.6-102.7 9-132.1c7.8-19.6 22.9-34.7 42.6-42.6 29.5-11.7 99.5-9 132.1-9s102.7-2.6 132.1 9c19.6 7.8 34.7 22.9 42.6 42.6 11.7 29.5 9 99.5 9 132.1s2.7 102.7-9 132.1z"></path></svg>
                    </a>
                  </div>
                </div>
              </div>
            </div>
            <?php
        }
        wp_reset_postdata();
        ?>
        </ul>
        <?php
    }

    return ob_get_clean();
}
add_shortcode('news-modal','cnp_news_modal_shortcode');


/**
 * Gibt die DGPTM-Styles aus (vollständig aus der Vorlage style.css)
 */
function cnp_output_dgptm_styles() {
    ?>
    <style>
    /* DGPTM Custom Fonts */
    @font-face {
        font-family: 'Titillium Web';
        font-style: normal;
        font-weight: 300;
        font-display: auto;
        src: url('https://perfusiologie.de/wp-content/uploads/2024/07/TitilliumWeb-Light.woff2') format('woff2'), 
             url('https://perfusiologie.de/wp-content/uploads/2024/07/TitilliumWeb-Light.woff') format('woff'), 
             url('https://perfusiologie.de/wp-content/uploads/2024/07/TitilliumWeb-Light.ttf') format('truetype');
    }
    @font-face {
        font-family: 'Titillium Web';
        font-style: normal;
        font-weight: bold;
        font-display: auto;
        src: url('https://perfusiologie.de/wp-content/uploads/2024/07/TitilliumWeb-Bold.woff2') format('woff2'), 
             url('https://perfusiologie.de/wp-content/uploads/2024/07/TitilliumWeb-Bold.woff') format('woff'), 
             url('https://perfusiologie.de/wp-content/uploads/2024/07/TitilliumWeb-Bold.ttf') format('truetype');
    }
    @font-face {
        font-family: 'Titillium Web';
        font-style: normal;
        font-weight: normal;
        font-display: auto;
        src: url('https://perfusiologie.de/wp-content/uploads/2024/07/TitilliumWeb-Regular.woff2') format('woff2'), 
             url('https://perfusiologie.de/wp-content/uploads/2024/07/TitilliumWeb-Regular.woff') format('woff'), 
             url('https://perfusiologie.de/wp-content/uploads/2024/07/TitilliumWeb-Regular.ttf') format('truetype');
    }
    @font-face {
        font-family: 'Titillium Web';
        font-style: normal;
        font-weight: 600;
        font-display: auto;
        src: url('https://perfusiologie.de/wp-content/uploads/2024/07/TitilliumWeb-SemiBold.woff2') format('woff2'), 
             url('https://perfusiologie.de/wp-content/uploads/2024/07/TitilliumWeb-SemiBold.woff') format('woff'), 
             url('https://perfusiologie.de/wp-content/uploads/2024/07/TitilliumWeb-SemiBold.ttf') format('truetype');
    }

    /* Standard-Modal für Listenansicht */
    .cnp-modal-overlay {
        display: none;
        position: fixed;
        z-index: 9999;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.65);
        backdrop-filter: blur(3px);
        overflow-y: auto;
    }
    .cnp-modal-overlay.active {
        display: block;
        opacity: 1;
    }
    .cnp-modal-content {
        background: #fff;
        margin: 5% auto;
        max-width: 600px;
        max-height: 80vh;
        overflow-y: auto;
        padding: 20px 20px 40px;
        position: relative;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    .cnp-close-modal {
        position: absolute;
        top: 10px;
        right: 10px;
        background: none;
        border: none;
        font-size: 1.5em;
        line-height: 1;
        cursor: pointer;
        color: #555;
        z-index: 10;
    }
    .cnp-close-modal:hover {
        color: #000;
    }
    .cnp-modal-body {
        margin-top: 10px;
        line-height: 1.5;
    }

    /* Share-Buttons für Listenansicht */
    .cnp-modal-share {
        margin-top: 20px;
        padding-top: 15px;
        border-top: 1px solid #eee;
    }
    .cnp-modal-share-text {
        font-weight: 600;
        margin-bottom: 10px;
        color: #333;
    }
    .cnp-modal-share-links {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .cnp-modal-share-links a {
        display: flex;
        align-items: center;
        justify-content: center;
        width: 40px;
        height: 40px;
        background-color: #2492BA;
        border-radius: 50%;
        transition: background-color 0.3s ease;
    }
    .cnp-modal-share-links a:hover {
        background-color: #005792;
    }
    .cnp-modal-share-links a svg {
        width: 18px;
        height: 18px;
        fill: #fff;
    }

    /* DGPTM Container und Grid */
    *, ::after, ::before {
        box-sizing: border-box;
    }

    .dgptm__container {
        max-width: 1140px;
        margin-left: auto;
        margin-right: auto;
        display: flex;
        flex-wrap: wrap;
        margin-right: -10px;
        margin-left: -10px;
    }

    .dgptm__news-item, 
    .dgptm__event-item {
        flex: 0 0 33.333333%;
        max-width: 33.333333%;
        position: relative;
        width: 100%;
        padding-right: 10px;
        padding-left: 10px;
        margin-bottom: 20px;
    }

    .dgptm__news-item-inner,
    .dgptm__event-item-inner {
        background-color: rgb(36, 146, 186);
        position: relative;
        padding-bottom: 120px;
        padding-top: 40px;
        padding-left: 40px;
        padding-right: 40px;
        color: #fff;
        height: 400px;
        overflow: hidden;
    }

    .dgptm__news-item-inner:hover,
    .dgptm__event-item-inner:hover {
        background-color: rgb(0, 87, 146);
    }

    .dgptm__news-item-inner:before {
        background-image: url("https://perfusiologie.de/wp-content/uploads/2024/08/dgfptm-icon-news-white.svg");
        background-position: 20px 20px;
        background-repeat: no-repeat;
        background-size: 60% auto;
        content: "";
        display: block;
        height: 420px;
        width: 100%;
        left: 0px;
        mix-blend-mode: normal;
        opacity: 0.05;
        position: absolute;
        top: 0px;
    }

    .dgptm__event-item-inner:before {
        background-image: url("https://perfusiologie.de/wp-content/uploads/2024/08/dgfptm-icon-event-white.svg");
        background-position: 20px 20px;
        background-repeat: no-repeat;
        background-size: 60% auto;
        content: "";
        display: block;
        height: 420px;
        width: 100%;
        left: 0px;
        mix-blend-mode: normal;
        opacity: 0.05;
        position: absolute;
        top: 0px;
    }

    .dgptm__news-item-headline {
        font-family: "Titillium Web", sans-serif;
        font-size: 24px;
        font-weight: 700;
        margin-bottom: 20px;
        height: 90px;
        line-height: 24px;
        position: relative;
        z-index: 1;
    }

    .dgptm__news-item-body {
        font-family: "Titillium Web", sans-serif;
        font-size: 20px;
        font-weight: 300;
        height: 150px;
        line-height: 30px;
        position: relative;
        z-index: 1;
    }

    .dgptm__news-item-button,
    .dgptm__event-item-button {
        position: absolute;
        right: 10px;
        bottom: 40px;
        z-index: 2;
    }

    .dgptm__news-item-button a,
    .dgptm__event-item-button a {
        font-family: "Titillium Web", sans-serif;
        font-size: 20px;
        font-weight: 700;
        line-height: 20px;
        height: 44px;
        padding-bottom: 6px;
        padding-left: 24px;
        padding-right: 24px;
        padding-top: 6px;
        background-color: #fff;
        color: rgb(36, 146, 186);
        border-bottom-left-radius: 20px;
        border-top-left-radius: 20px;
        text-decoration: none;
        display: inline-block;
    }

    .dgptm__news-item-button a:hover,
    .dgptm__event-item-button a:hover {
        background-color: #333;
        color: #fff;
    }

    .dgptm__event-item-date {
        font-family: "Titillium Web", sans-serif;
        background-color: #005792;
        padding-top: 20px;
        padding-bottom: 10px;
        padding-left: 40px;
        padding-right: 40px;
        margin-left: -40px;
        margin-right: -40px;
        font-size: 20px;
        font-weight: 300;
        margin-bottom: 20px;
        position: relative;
        z-index: 1;
    }

    .dgptm__event-item-tags {
        font-family: "Titillium Web", sans-serif;
        font-size: 20px;
        font-weight: 300;
        padding-bottom: 20px;
        margin-bottom: 20px;
        border-bottom: 1px solid #fff;
        position: relative;
        z-index: 1;
    }

    .dgptm__event-item-body {
        font-family: "Titillium Web", sans-serif;
        font-size: 20px;
        font-weight: 700;
        line-height: 30px;
        position: relative;
        z-index: 1;
    }

    .dgptm__event-item-place {
        font-family: "Titillium Web", sans-serif;
        font-size: 20px;
        font-weight: 300;
        position: absolute;
        left: 10px;
        bottom: 20px;
        padding-left: 40px;
        width: calc(100% - 140px);
        z-index: 2;
    }

    /* PDF-Button (spiegelverkehrt, links) */
    .dgptm__news-item-pdfbutton {
        position: absolute;
        left: 10px;
        bottom: 40px;
        z-index: 2;
    }

    .dgptm__news-item-pdfbutton a {
        font-family: "Titillium Web", sans-serif;
        font-size: 20px;
        font-weight: 700;
        line-height: 20px;
        height: 44px;
        padding: 6px 24px;
        background-color: #fff;
        color: rgb(36, 146, 186);
        border-bottom-right-radius: 20px;
        border-top-right-radius: 20px;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
    }

    .dgptm__news-item-pdfbutton a:hover {
        background-color: #333;
        color: #fff;
    }

    /* News-Double-Wide für type="news" */
    .dgptm__container--news-doublewide .dgptm__news-item {
        flex: 1 1 50% !important;
        min-width: 300px !important;
        max-width: 100%;
        width: auto !important;
    }

    .dgptm__container--news-doublewide .dgptm__news-item-headline {
        line-height: 24px;
        height: auto !important;
        max-height: 72px;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 3;
        -webkit-box-orient: vertical;
    }

    .dgptm__container--news-doublewide .dgptm__news-item-body {
        font-size: 16px !important;
        line-height: 24px !important;
        height: auto !important;
        max-height: 144px;
        overflow: hidden;
        display: -webkit-box;
        -webkit-line-clamp: 6;
        -webkit-box-orient: vertical;
        word-break: break-word;
        hyphens: auto;
        margin-bottom: 8px;
    }

    /* ======= DGPTM POPUP STYLES (vollständig aus Vorlage) ======= */
    .dgptm__popup {
        position: fixed;
        width: 100%;
        height: 100%;
        top: 0px;
        left: 0px;
        z-index: 10000;
        display: none;
    }
    .dgptm__popup .dgptm__popup-bg {
        position: absolute;
        left: 0px;
        top: 0px;
        background-color: rgba(0,0,0,0.4);
        width: 100%;
        height: 100%;
        z-index: 101;
    }
    .dgptm__popup .dgptm__popup-content {
        position: absolute;
        z-index: 102;
        max-width: 680px;
        width: 100%;
        background-color: #fff;
        top: 50%;
        left: 50%;
        transform: translate(-50%,-50%);
        max-height: 90vh;
        overflow-y: auto;
    }
    .dgptm__popup-close {
        position: absolute;
        font-family: "Titillium Web", sans-serif;
        font-size: 20px;
        font-weight: 700;
        line-height: 20px;
        padding-bottom: 10px;
        padding-left: 24px;
        padding-right: 24px;
        padding-top: 10px;
        background-color: #BD1722;
        color: #fff;
        border-bottom-left-radius: 20px;
        border-top-left-radius: 20px;
        text-decoration: none;
        right: 0px;
        top: 30px;
        z-index: 110;
        cursor: pointer;
    }
    .dgptm__popup-close:hover {
        background-color: #333;
        color: #fff;
    }
    .dgptm__popup-inner {
        padding: 40px;
        padding-bottom: 100px;
        position: relative;
    }
    .dgptm__popup-headline {
        padding-right: 40px;
        font-family: "Titillium Web", sans-serif;
        font-size: 24px;
        font-weight: 700;
        line-height: 30px;
        margin-bottom: 40px;
    }
    .dgptm__popup-image {
        position: relative;
        width: 100%;
        height: 338px;
        overflow: hidden;
        margin-bottom: 20px;
    }
    .image-as-background {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
    }
    .image-as-background img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        font-family: 'object-fit: cover;';
    }
    .dgptm__popup-text {
        font-family: "Titillium Web", sans-serif;
        font-size: 20px;
        font-weight: 300;
        line-height: 30px;
    }
    .dgptm__popup-link {
        position: absolute;
        right: 0px;
        bottom: 40px;
    }
    .dgptm__popup-link a {
        font-family: "Titillium Web", sans-serif;
        font-size: 20px;
        font-weight: 700;
        line-height: 20px;
        padding-bottom: 6px;
        padding-left: 24px;
        padding-right: 24px;
        padding-top: 6px;
        background-color: #005792;
        color: #fff;
        border-bottom-left-radius: 20px;
        border-top-left-radius: 20px;
        text-decoration: none;
    }
    .dgptm__popup-link a:hover {
        background-color: #333;
        color: #fff;
    }
    .dgptm__popup-footer {
        padding: 40px;
        background-color: #2492BA;
        color: #fff;
        position: relative;
    }
    .dgptm__popup-footer:before {
        content: '';
        display: block;
        position: absolute;
        left: 0px;
        bottom: 0px;
        background-image: url("https://perfusiologie.de/wp-content/uploads/2024/08/dgfptm-icon-news-white.svg");
        background-position: center center;
        background-repeat: no-repeat;
        background-size: 40% auto;
        height: 165px;
        width: 100%;
        opacity: 0.05;
    }
    .dgptm__popup-footer-text {
        font-family: "Titillium Web", sans-serif;
        font-size: 20px;
        font-weight: 700;
        line-height: 20px;
        margin-bottom: 20px;
    }
    .dgptm__popup-footer-links ul {
        padding: 0px;
        margin: 0px;
        list-style: none;
    }
    .dgptm__popup-footer-links ul li {
        display: inline-block;
        margin-right: 10px;
    }
    .dgptm__popup-footer-links ul li a {
        display: block;
        width: 45px;
        height: 45px;
        background-color: #005792;
        border-radius: 50%;
        position: relative;
    }
    .dgptm__popup-footer-links ul li a:hover {
        background-color: #333;
    }
    .dgptm__popup-footer-links ul li a svg {
        width: 17px;
        position: absolute;
        left: 50%;
        top: 50%;
        transform: translate(-50%,-50%);
        fill: #fff;
    }
    .dgptm__popup-header {
        background-color: #2492BA;
        color: #fff;
        padding-top: 20px;
        padding-bottom: 20px;
        padding-left: 40px;
        padding-right: 100px;
        font-family: "Titillium Web", sans-serif;
        font-size: 20px;
        font-weight: 300;
        line-height: 20px;
    }
    .dgptm__popup-date {
        margin-bottom: 10px;
        padding-bottom: 10px;
        border-bottom: 1px solid #fff;
    }
    .dgptm__popup-place {
        color: #fff;
    }

    /* Responsive */
    @media (max-width: 1139.98px) {
        .dgptm__news-item, 
        .dgptm__event-item {
            flex: 0 0 50%;
            max-width: 50%;
        }
        .dgptm__container--news-doublewide .dgptm__news-item {
            flex: 1 1 100% !important;
            min-width: 0 !important;
            width: 100% !important;
        }
    }

    @media (max-width: 767.98px) {
        .dgptm__news-item, 
        .dgptm__event-item {
            flex: 0 0 100%;
            max-width: 100%;
        }
        .dgptm__popup .dgptm__popup-content {
            max-width: 95%;
            margin: 20px auto;
        }
        .dgptm__popup-inner {
            padding: 20px;
            padding-bottom: 80px;
        }
    }
    </style>
    <?php
}


/**
 * AUTOMATISCHE MENÜ-EINTRÄGE FÜR 'menue_event' UND 'menue_news'
 */
add_action('save_post_newsbereich', 'cnp_menue_auto_update', 20, 3);
add_action('trash_post', 'cnp_menue_auto_remove_on_trash');
add_action('untrash_post', 'cnp_menue_auto_recheck_on_untrash');
add_action('transition_post_status', 'cnp_menue_auto_remove_on_status_change', 10, 3);

function cnp_menue_auto_update($post_id, $post, $update) {
    if (wp_is_post_revision($post_id)) {
        return;
    }
    if ($post->post_type !== 'newsbereich') {
        return;
    }
    $has_event_cat = has_term('menue_event', 'category', $post_id);
    $has_news_cat  = has_term('menue_news', 'category', $post_id);

    if ($post->post_status !== 'publish') {
        cnp_menue_remove_item($post_id);
        return;
    }
    if (!$has_event_cat && !$has_news_cat) {
        cnp_menue_remove_item($post_id);
        return;
    }
    if ($has_event_cat) {
        cnp_menue_add_item($post_id, $post->post_title, 'Veranstaltungen');
    }
    if ($has_news_cat) {
        cnp_menue_add_item($post_id, $post->post_title, 'Neuigkeiten');
    }
}

function cnp_menue_auto_remove_on_trash($post_id){
    $post = get_post($post_id);
    if ($post && $post->post_type === 'newsbereich') {
        cnp_menue_remove_item($post_id);
    }
}

function cnp_menue_auto_recheck_on_untrash($post_id){
    $post = get_post($post_id);
    if ($post && $post->post_type === 'newsbereich') {
        cnp_menue_auto_update($post_id, $post, true);
    }
}

function cnp_menue_auto_remove_on_status_change($new_status, $old_status, $post){
    if ($post->post_type === 'newsbereich') {
        if ($new_status !== 'publish') {
            cnp_menue_remove_item($post->ID);
        }
    }
}

function cnp_menue_remove_item($post_id){
    $menu_slug = 'mainnavi-c';
    $menu_object = wp_get_nav_menu_object($menu_slug);
    if(!$menu_object){
        return;
    }
    $menu_items = wp_get_nav_menu_items($menu_object->term_id);
    if(!$menu_items){
        return;
    }
    foreach($menu_items as $item){
        if($item->xfn == 'cnp-post-'.$post_id){
            wp_delete_post($item->ID, true);
        }
    }
}

function cnp_menue_add_item($post_id, $post_title, $parent_title){
    $menu_slug = 'mainnavi-c';
    $menu_object = wp_get_nav_menu_object($menu_slug);
    if(!$menu_object){
        return;
    }
    $parent_item_id = 0;
    $menu_items = wp_get_nav_menu_items($menu_object->term_id);
    if(!$menu_items){
        return;
    }
    foreach($menu_items as $item){
        if($item->title == $parent_title && $item->menu_item_parent == 0){
            $parent_item_id = $item->ID;
            break;
        }
    }
    if(!$parent_item_id){
        return;
    }
    $existing_item_id = 0;
    foreach($menu_items as $item){
        if($item->xfn == 'cnp-post-'.$post_id){
            $existing_item_id = $item->ID;
            break;
        }
    }
    $menu_link = '#cnp-modal-'.$post_id;

    $menu_title = wp_strip_all_tags($post_title);
    if(mb_strlen($menu_title) > 50){
        $menu_title = mb_substr($menu_title, 0, 50).'...';
    }

    $menu_data = array(
        'menu-item-object'     => 'custom',
        'menu-item-type'       => 'custom',
        'menu-item-title'      => $menu_title,
        'menu-item-url'        => $menu_link,
        'menu-item-parent-id'  => $parent_item_id,
        'menu-item-status'     => 'publish',
        'menu-item-xfn'        => 'cnp-post-'.$post_id
    );
    if($existing_item_id){
        $menu_data['menu-item-ID'] = $existing_item_id;
    }
    wp_update_nav_menu_item($menu_object->term_id, 0, $menu_data);
}


/**
 * Shortcode: [news-list]
 */
function cnp_news_list_shortcode($atts) {
    if (!current_user_can('edit_newsbereiche')) {
        return '<p>Keine Berechtigung, News zu sehen.</p>';
    }
    $atts = shortcode_atts(
        array(
            'excerpt_length' => 0,
            'show_pubdate'   => 'false',
        ),
        $atts,
        'news-list'
    );
    $valid_limits = array(5,10,20,50);
    $def_limit    = 10;
    $limit = (isset($_GET['cnp_limit']) && in_array((int)$_GET['cnp_limit'], $valid_limits))
        ? (int)$_GET['cnp_limit']
        : $def_limit;
    $valid_orders = array('date','start','end','title');
    $orderby = (isset($_GET['cnp_order']) && in_array(strtolower($_GET['cnp_order']), $valid_orders))
        ? strtolower($_GET['cnp_order'])
        : 'date';
    $dir = 'DESC';
    if (isset($_GET['cnp_dir']) && in_array(strtoupper($_GET['cnp_dir']), array('ASC','DESC'), true)) {
        $dir = strtoupper($_GET['cnp_dir']);
    }
    $args = array(
        'post_type'      => 'newsbereich',
        'post_status'    => array('publish','future'),
        'posts_per_page' => $limit,
        'order'          => $dir,
        'no_found_rows'  => true,
    );
    switch ($orderby) {
        case 'start':
            $args['orderby']   = 'meta_value';
            $args['meta_key']  = '_cnp_event_start';
            $args['meta_type'] = 'DATE';
            break;
        case 'end':
            $args['orderby']   = 'meta_value';
            $args['meta_key']  = '_cnp_display_until';
            $args['meta_type'] = 'DATE';
            break;
        case 'title':
            $args['orderby'] = 'title';
            break;
        case 'date':
        default:
            $args['orderby'] = 'date';
            break;
    }
    if (!current_user_can('edit_others_newsbereiche')) {
        $args['author'] = get_current_user_id();
    }
    $q = new WP_Query($args);
    ob_start();
    $base_url = remove_query_arg(array('cnp_limit','cnp_order','cnp_dir','cnp_delete_news','cnp_nonce','cnp_edit_news'));
    ?>
    <div class="cnp-news-list-compact">
      <h4>News bearbeiten</h4>
      <form method="get" style="margin-bottom:1em;">
         <label>Zeilen pro Seite:</label>
         <select name="cnp_limit">
           <?php
           foreach($valid_limits as $vl){
               echo '<option value="' . intval($vl) . '" ' . selected($limit, $vl, false) . '>' . intval($vl) . '</option>';
           }
           ?>
         </select>
         <label>Sortieren nach:</label>
         <select name="cnp_order">
             <option value="date" <?php selected($orderby, 'date'); ?>>Datum (Beitrag)</option>
             <option value="start" <?php selected($orderby, 'start'); ?>>Start (event_start)</option>
             <option value="end" <?php selected($orderby, 'end'); ?>>Ende (display_until)</option>
             <option value="title" <?php selected($orderby, 'title'); ?>>Titel</option>
         </select>
         <select name="cnp_dir">
             <option value="asc"  <?php selected($dir, 'ASC'); ?>>Aufsteigend</option>
             <option value="desc" <?php selected($dir, 'DESC'); ?>>Absteigend</option>
         </select>
         <button type="submit" class="button">Anzeigen</button>
      </form>
      <?php
      if (!$q->have_posts()){
          echo '<p>Keine News gefunden.</p>';
      } else {
          echo '<table class="widefat fixed striped">';
          echo '<thead><tr>
                  <th>Bild</th>
                  <th>Titel</th>
                  <th>Start</th>
                  <th>Veröffentlicht</th>
                  <th>Ende</th>
                  <th>Ort</th>
                  <th>Aktionen</th>
                </tr></thead><tbody>';
          while($q->have_posts()){
              $q->the_post();
              $nid  = get_the_ID();
              $pd   = get_post_meta($nid, '_cnp_publish_date', true);
              $es   = get_post_meta($nid, '_cnp_event_start', true);
              $du   = get_post_meta($nid, '_cnp_display_until', true);
              $loc  = get_post_meta($nid, '_cnp_event_location', true);
              $pd_str = $pd ? date_i18n('d.m.Y', strtotime($pd)) : '-';
              $es_str = $es ? date_i18n('d.m.Y', strtotime($es)) : '-';
              $du_str = $du ? date_i18n('d.m.Y', strtotime($du)) : '-';
              $thumb_html = has_post_thumbnail($nid) ? get_the_post_thumbnail($nid, 'cnp_news_thumbnail') : 'Kein Bild';
              $title = get_the_title();
              $status_obj = get_post_status_object(get_post_status($nid));
              $status_label = $status_obj ? $status_obj->label : 'Unbekannt';
              $delete_nonce = wp_create_nonce('cnp_delete_news_' . $nid);
              echo '<tr>';
              echo '<td>' . $thumb_html . '</td>';
              echo '<td><strong>' . esc_html($title) . '</strong></td>';
              echo '<td>' . esc_html($es_str) . '</td>';
              echo '<td>' . esc_html($pd_str) . '</td>';
              echo '<td>' . esc_html($du_str) . '</td>';
              echo '<td>' . esc_html($loc ?: '') . '</td>';
              echo '<td>
                      <a href="' . esc_url(add_query_arg('cnp_edit_news', $nid, $base_url)) . '" class="button">Bearbeiten</a>
                      <a href="' . esc_url(add_query_arg(array('cnp_delete_news'=>$nid, 'cnp_nonce'=>$delete_nonce), $base_url)) . '"
                         class="button button-secondary"
                         onclick="return confirm(\'Wirklich löschen?\');">
                         Löschen
                      </a>
                    </td>';
              echo '</tr>';
          }
          wp_reset_postdata();
          echo '</tbody></table>';
      }
      ?>
    </div>
    <?php
    if (isset($_GET['cnp_delete_news'])) {
        $del_id = (int)$_GET['cnp_delete_news'];
        if (!isset($_GET['cnp_nonce']) || !wp_verify_nonce($_GET['cnp_nonce'], 'cnp_delete_news_' . $del_id)) {
            echo '<div class="notice notice-error"><p>Ungültige Sicherheitsprüfung fürs Löschen.</p></div>';
        } else {
            if (current_user_can('delete_newsbereich', $del_id)) {
                wp_delete_post($del_id, true);
                wp_safe_redirect(remove_query_arg(array('cnp_delete_news', 'cnp_nonce')));
                exit;
            } else {
                echo '<div class="notice notice-error"><p>Keine Berechtigung zum Löschen.</p></div>';
            }
        }
    }
    return ob_get_clean();
}
add_shortcode('news-list','cnp_news_list_shortcode');


/**
 * jQuery-Skript für Modal-Fenster im Footer (mit DGPTM-Popup-Support)
 */
function cnp_modal_init_script() {
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Standard-Modal (für Listenansicht)
        $('.cnp-open-modal').on('click', function(e) {
            e.preventDefault();
            var target = $(this).data('target');
            $(target).addClass('active').fadeIn(300);
        });
        $('.cnp-close-modal').on('click', function(e) {
            e.preventDefault();
            var target = $(this).data('close');
            $(target).removeClass('active').fadeOut(300);
        });
        $('a[href^="#cnp-modal-"]').on('click', function(e) {
            e.preventDefault();
            var modalSelector = $(this).attr('href');
            $(modalSelector).addClass('active').fadeIn(300);
        });
        
        // DGPTM-Popup (für Grid-Layout)
        $('.dgptm__popup-close').on('click', function(e) {
            e.preventDefault();
            $(this).closest('.dgptm__popup').fadeOut(300);
        });
        $('.dgptm__popup-bg').on('click', function(e) {
            $(this).closest('.dgptm__popup').fadeOut(300);
        });
    });
    </script>
    <?php
}
add_action('wp_footer', 'cnp_modal_init_script');


/**
 * Lädt Modal-Markup für Menü-Links im Footer
 */
function cnp_load_modal_markup_in_footer() {
    $today = date('Y-m-d');
    $meta_query = array(
         'relation' => 'AND',
         array(
             'relation' => 'OR',
             array(
                 'key' => '_cnp_publish_date',
                 'compare' => 'NOT EXISTS'
             ),
             array(
                 'key' => '_cnp_publish_date',
                 'value' => $today,
                 'compare' => '<=',
                 'type' => 'DATE'
             )
         ),
         array(
             'relation' => 'OR',
             array(
                 'key' => '_cnp_display_until',
                 'compare' => 'NOT EXISTS'
             ),
             array(
                 'key' => '_cnp_display_until',
                 'value' => '',
                 'compare' => '='
             ),
             array(
                 'key' => '_cnp_display_until',
                 'value' => $today,
                 'compare' => '>=',
                 'type' => 'DATE'
             )
         )
    );

    $args = array(
         'post_type' => 'newsbereich',
         'post_status' => 'publish',
         'posts_per_page' => -1,
         'tax_query' => array(
             'relation' => 'OR',
             array(
                 'taxonomy' => 'category',
                 'field' => 'slug',
                 'terms' => 'menue_event'
             ),
             array(
                 'taxonomy' => 'category',
                 'field' => 'slug',
                 'terms' => 'menue_news'
             )
         ),
         'meta_query' => $meta_query,
    );
    $q = new WP_Query($args);
    if (!$q->have_posts()) {
         return;
    }

    while($q->have_posts()){
         $q->the_post();
         $pid = get_the_ID();
         $title_raw = get_the_title();
         $content_raw = get_the_content();

         $url = get_post_meta($pid, '_cnp_news_url', true);
         $url_button = '';
         if(!empty($url)){
              $url_button = '<a href="'.esc_url($url).'" class="button" target="_blank">Seite aufrufen</a>';
         }
         $edugrant_val = get_post_meta($pid, '_cnp_edugrant', true);
         $edugrant_button = '';
         if($edugrant_val == 1){
              $edugrant_button = '<a href="https://perfusiologie.de/veranstaltungen/educational-grant-der-dgptm/educational-grant-antrag/" class="button" target="_blank">Jetzt Educational Grant beantragen</a>';
         }
         $link_bar = '';
         if($url_button || $edugrant_button){
              $link_bar = '<div class="cnp-modal-linkbar" style="text-align: center;">';
              if($url_button){
                  $link_bar .= $url_button;
              }
              if($url_button && $edugrant_button){
                  $link_bar .= ' &bull; ';
              }
              if($edugrant_button){
                  $link_bar .= $edugrant_button;
              }
              $link_bar .= '</div>';
         }

         $mid = 'cnp-modal-'.$pid;
         ?>
         <div id="<?php echo esc_attr($mid); ?>" class="cnp-modal-overlay">
           <div class="cnp-modal-content">
             <button class="cnp-close-modal" data-close="#<?php echo esc_attr($mid); ?>">&times;</button>
             <h2><?php echo esc_html($title_raw); ?></h2>
             <div class="cnp-modal-body">
               <?php echo apply_filters('the_content', $content_raw); ?>
               <?php if($link_bar){ echo $link_bar; } ?>
             </div>
           </div>
         </div>
         <?php
    }
    wp_reset_postdata();
}
add_action('wp_footer', 'cnp_load_modal_markup_in_footer');


/**
 * Shortcode: [events_by_month]
 */
function cnp_events_by_month_shortcode($atts) {
    $atts = shortcode_atts( array(
        'categories'  => '',
        'month_count' => 3,
    ), $atts, 'events_by_month' );

    $start_date = date('Y-m-01');
    if ((int)$atts['month_count'] > 0) {
        $end_date = date('Y-m-t', strtotime("+" . ((int)$atts['month_count'] - 1) . " months", strtotime($start_date)));
    } else {
        $end_date = date('Y-m-d', strtotime("+10 years", strtotime($start_date)));
    }
    $args = array(
        'post_type'      => 'newsbereich',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_key'       => '_cnp_event_start',
        'meta_query'     => array(
            array(
                'key'     => '_cnp_event_start',
                'value'   => array($start_date, $end_date),
                'compare' => 'BETWEEN',
                'type'    => 'DATE',
            ),
        ),
    );
    if (!empty($atts['categories'])) {
        $cat_slugs = array_map('trim', explode(',', $atts['categories']));
        $args['tax_query'] = array(
            array(
                'taxonomy' => 'category',
                'field'    => 'slug',
                'terms'    => $cat_slugs,
                'operator' => 'IN',
            ),
        );
    }

    $query = new WP_Query($args);
    $output = '';
    if (!$query->have_posts()) {
        return '<p>Keine Veranstaltungen gefunden.</p>';
    }
    $events_by_month = array();
    while ($query->have_posts()) {
        $query->the_post();
        $event_date = get_post_meta(get_the_ID(), '_cnp_event_start', true);
        if (!$event_date) {
            continue;
        }
        $year_month = date('Y-m', strtotime($event_date));
        $events_by_month[$year_month][] = get_post();
    }
    wp_reset_postdata();
    ksort($events_by_month);

    foreach ($events_by_month as $year_month => $posts) {
        $timestamp  = strtotime($year_month . '-01');
        $month_name = date_i18n('F Y', $timestamp);
        $output    .= '<h3>' . esc_html($month_name) . '</h3>';
        $output    .= '<ul class="cnp-events-by-month">';
        foreach ($posts as $post) {
            setup_postdata($post);
            $event_date     = get_post_meta($post->ID, '_cnp_event_start', true);
            $formatted_date = $event_date ? date_i18n('d.m.Y', strtotime($event_date)) : '';
            $output .= '<li><strong>' . esc_html(get_the_title($post)) . '</strong> – ' . esc_html($formatted_date) . '</li>';
        }
        wp_reset_postdata();
        $output .= '</ul>';
    }
    return $output;
}
add_shortcode('events_by_month', 'cnp_events_by_month_shortcode');
?>