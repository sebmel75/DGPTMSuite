<?php
/**
 * Shortcode: [list_creation_edit_modal]
 */

if ( ! function_exists('cnp_convert_date_or_fallback_today') ) {
    function cnp_convert_date_or_fallback_today($input) {
        $input = trim($input);
        if ( empty($input) ) {
            return date('Y-m-d');
        }
        $d = DateTime::createFromFormat('d.m.Y', $input);
        if ($d && $d->format('d.m.Y') === $input) {
            return $d->format('Y-m-d');
        }
        $d2 = DateTime::createFromFormat('Y-m-d', $input);
        if ($d2 && $d2->format('Y-m-d') === $input) {
            return $input;
        }
        return date('Y-m-d');
    }
}

if ( ! function_exists('cnp_ensure_category_allgemein') ) {
    function cnp_ensure_category_allgemein(&$cat_selected) {
        if ( ! is_array($cat_selected) ) {
            $cat_selected = array();
        }
        if ( empty($cat_selected) ) {
            $default_cat = get_term_by('slug', 'allgemein', 'category');
            if ( !$default_cat ) {
                $new_term = wp_insert_term('Allgemein', 'category', array('slug' => 'allgemein'));
                if ( ! is_wp_error($new_term) ) {
                    $cat_selected[] = (int)$new_term['term_id'];
                }
            } else {
                $cat_selected[] = (int)$default_cat->term_id;
            }
        }
    }
}

if ( ! function_exists('cnp_get_req') ) {
    function cnp_get_req($key, $default = '') {
        return isset($_REQUEST[$key]) ? sanitize_text_field($_REQUEST[$key]) : $default;
    }
}

// Neue Funktion: Kategorien rekursiv als Checkboxes anzeigen (nach Hierarchie)
if ( ! function_exists('cnp_category_checklist') ) {
    function cnp_category_checklist($cats, $parent = 0, $selected = array()) {
        foreach ($cats as $cat) {
            if ($cat->parent == $parent) {
                echo '<label style="margin-left:'.($parent ? '20px' : '0px').'; display:block;">';
                echo '<input type="checkbox" name="cnp_news_category[]" value="'.intval($cat->term_id).'" '.(in_array($cat->term_id, $selected) ? 'checked' : '').'> ';
                echo esc_html($cat->name);
                echo '</label>';
                cnp_category_checklist($cats, $cat->term_id, $selected);
            }
        }
    }
}

function cnp_list_creation_edit_modal_shortcode() {
    // Nur eingeloggt
    if (!is_user_logged_in()) {
        return '<p>Bitte logge dich ein, um News verwalten zu können.</p>';
    }
    if ( ! current_user_can('edit_newsbereiche') ) {
        return '<p>Keine Berechtigung, um News zu verwalten.</p>';
    }

    $all_cats = get_categories(array('taxonomy' => 'category', 'hide_empty' => false));
    if ( ! is_array($all_cats) ) {
        $all_cats = array();
    }

    ob_start();
    ?>
    <style>
    .cnp-modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.6);
      z-index: 9999;
      overflow-y: auto;
    }
    .cnp-modal-content {
      background: #fff;
      max-width: 700px;
      margin: 100px auto;
      padding: 20px;
      position: relative;
      border-radius: 5px;
      box-shadow: 0 0 10px rgba(0,0,0,0.3);
    }
    .cnp-close-modal {
      position: absolute;
      top: 10px;
      right: 10px;
      background: none;
      border: none;
      font-size: 24px;
      line-height: 24px;
      cursor: pointer;
    }
    .cnp-switch {
      position: relative;
      display: inline-block;
      width: 50px;
      height: 24px;
    }
    .cnp-switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }
    .cnp-slider {
      position: absolute;
      cursor: pointer;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #ccc;
      transition: .4s;
      border-radius: 24px;
    }
    .cnp-slider:before {
      position: absolute;
      content: "";
      height: 18px;
      width: 18px;
      left: 3px;
      bottom: 3px;
      background-color: #fff;
      transition: .4s;
      border-radius: 50%;
    }
    input:checked + .cnp-slider {
      background-color: #2196F3;
    }
    input:checked + .cnp-slider:before {
      transform: translateX(26px);
    }
    .cnp-filter-form {
      margin: 1em 0;
      padding: 10px;
      background: #f9f9f9;
      border: 1px solid #ddd;
    }
    .cnp-filter-form label {
      margin-right: 5px;
    }
    </style>
    <?php

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!isset($_POST['cnp_modal_nonce']) || !wp_verify_nonce($_POST['cnp_modal_nonce'], 'cnp_modal_action')) {
            echo '<div class="notice notice-error"><p>Ungültiger Nonce.</p></div>';
        } else {
            $action = isset($_POST['cnp_action']) ? sanitize_text_field($_POST['cnp_action']) : '';

            // Toggle Publish/Draft
            if ($action === 'toggle_publish') {
                $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
                if ($post_id && current_user_can('edit_post', $post_id)) {
                    $the_post = get_post($post_id);
                    if ($the_post) {
                        if ($the_post->post_status === 'publish') {
                            wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
                            echo '<div class="notice notice-success"><p>Eintrag als Entwurf gespeichert.</p></div>';
                        } else {
                            wp_update_post(array('ID' => $post_id, 'post_status' => 'publish'));
                            echo '<div class="notice notice-success"><p>Eintrag veröffentlicht.</p></div>';
                        }
                    }
                }
            }
            // CREATE
            elseif ($action === 'create') {
                $title = wp_kses_post(isset($_POST['cnp_news_title']) ? $_POST['cnp_news_title'] : '');
                $content = isset($_POST['cnp_news_content']) ? $_POST['cnp_news_content'] : '';
                $pd = trim(isset($_POST['cnp_publish_date']) ? $_POST['cnp_publish_date'] : '');
                $es = trim(isset($_POST['cnp_event_start']) ? $_POST['cnp_event_start'] : '');
                $du = trim(isset($_POST['cnp_display_until']) ? $_POST['cnp_display_until'] : '');
                $loc = sanitize_text_field(isset($_POST['cnp_event_location']) ? $_POST['cnp_event_location'] : '');
                $url = esc_url_raw(isset($_POST['cnp_news_url']) ? $_POST['cnp_news_url'] : '');
                $cats = isset($_POST['cnp_news_category']) ? array_map('intval', $_POST['cnp_news_category']) : array();
                $new_cat = isset($_POST['cnp_new_category']) ? sanitize_text_field($_POST['cnp_new_category']) : '';

                if (!empty($new_cat)) {
                    $term = get_term_by('name', $new_cat, 'category');
                    if (!$term) {
                        $nt = wp_insert_term($new_cat, 'category');
                        if (!is_wp_error($nt)) {
                            $cats[] = (int)$nt['term_id'];
                        }
                    } else {
                        $cats[] = (int)$term->term_id;
                    }
                }

                $edugrant = isset($_POST['cnp_edugrant']) ? 1 : 0;

                if (empty($title) || empty($content)) {
                    echo '<div class="notice notice-error"><p>Titel und Inhalt sind erforderlich (Create).</p></div>';
                } else {
                    $pd_ymd = cnp_convert_date_or_fallback_today($pd);
                    $meta = array('_cnp_publish_date' => $pd_ymd);
                    if (!empty($es)) {
                        $meta['_cnp_event_start'] = cnp_convert_date_or_fallback_today($es);
                    }
                    if (!empty($du)) {
                        $meta['_cnp_display_until'] = cnp_convert_date_or_fallback_today($du);
                    }
                    if (!empty($loc)) {
                        $meta['_cnp_event_location'] = $loc;
                    }
                    $meta['_cnp_edugrant'] = $edugrant;

                    $new_post = array(
                        'post_title'   => $title,
                        'post_content' => $content,
                        'post_status'  => 'publish',
                        'post_type'    => 'newsbereich',
                        'post_author'  => get_current_user_id(),
                        'meta_input'   => $meta,
                        'post_category'=> $cats,
                    );
                    $pid = wp_insert_post($new_post);
                    if (is_wp_error($pid)) {
                        echo '<div class="notice notice-error"><p>Fehler beim Erstellen: ' . esc_html($pid->get_error_message()) . '</p></div>';
                    } else {
                        if (!empty($_FILES['cnp_featured_image']['name'])) {
                            require_once(ABSPATH.'wp-admin/includes/file.php');
                            require_once(ABSPATH.'wp-admin/includes/media.php');
                            require_once(ABSPATH.'wp-admin/includes/image.php');
                            $att_id = media_handle_upload('cnp_featured_image', $pid);
                            if (!is_wp_error($att_id)) {
                                set_post_thumbnail($pid, $att_id);
                            }
                        }
                        update_post_meta($pid, '_cnp_news_url', $url);
                        echo '<div class="notice notice-success"><p>Neuer Eintrag erstellt.</p></div>';
                    }
                }
            }
            // EDIT
            elseif ($action === 'edit') {
                $edit_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
                if (!$edit_id || !current_user_can('edit_post', $edit_id)) {
                    echo '<div class="notice notice-error"><p>Keine Berechtigung oder ungültige ID (Edit).</p></div>';
                } else {
                    $title = wp_kses_post(isset($_POST['cnp_news_title']) ? $_POST['cnp_news_title'] : '');
                    $content = isset($_POST['cnp_news_content']) ? $_POST['cnp_news_content'] : '';
                    $pd = trim(isset($_POST['cnp_publish_date']) ? $_POST['cnp_publish_date'] : '');
                    $es = trim(isset($_POST['cnp_event_start']) ? $_POST['cnp_event_start'] : '');
                    $du = trim(isset($_POST['cnp_display_until']) ? $_POST['cnp_display_until'] : '');
                    $loc = sanitize_text_field(isset($_POST['cnp_event_location']) ? $_POST['cnp_event_location'] : '');
                    $url = esc_url_raw(isset($_POST['cnp_news_url']) ? $_POST['cnp_news_url'] : '');
                    $cats = isset($_POST['cnp_news_category']) ? array_map('intval', $_POST['cnp_news_category']) : array();
                    $new_cat = isset($_POST['cnp_new_category']) ? sanitize_text_field($_POST['cnp_new_category']) : '';

                    if (!empty($new_cat)) {
                        $term = get_term_by('name', $new_cat, 'category');
                        if (!$term) {
                            $nt = wp_insert_term($new_cat, 'category');
                            if (!is_wp_error($nt)) {
                                $cats[] = (int)$nt['term_id'];
                            }
                        } else {
                            $cats[] = (int)$term->term_id;
                        }
                    }
                    cnp_ensure_category_allgemein($cats);
                    $edugrant = isset($_POST['cnp_edugrant']) ? 1 : 0;

                    if (empty($title) || empty($content)) {
                        echo '<div class="notice notice-error"><p>Titel und Inhalt sind erforderlich (Edit).</p></div>';
                    } else {
                        $pd_ymd = cnp_convert_date_or_fallback_today($pd);
                        $meta = array('_cnp_publish_date' => $pd_ymd);
                        if (!empty($es)) {
                            $meta['_cnp_event_start'] = cnp_convert_date_or_fallback_today($es);
                        } else {
                            delete_post_meta($edit_id, '_cnp_event_start');
                        }
                        if (!empty($du)) {
                            $meta['_cnp_display_until'] = cnp_convert_date_or_fallback_today($du);
                        } else {
                            delete_post_meta($edit_id, '_cnp_display_until');
                        }
                        if (!empty($loc)) {
                            $meta['_cnp_event_location'] = $loc;
                        } else {
                            delete_post_meta($edit_id, '_cnp_event_location');
                        }
                        $meta['_cnp_edugrant'] = $edugrant;

                        $upd = array(
                            'ID'           => $edit_id,
                            'post_title'   => $title,
                            'post_content' => $content,
                            'meta_input'   => $meta,
                            'post_category'=> $cats,
                        );
                        if (!empty($_FILES['cnp_featured_image']['name'])) {
                            require_once(ABSPATH.'wp-admin/includes/file.php');
                            require_once(ABSPATH.'wp-admin/includes/media.php');
                            require_once(ABSPATH.'wp-admin/includes/image.php');
                            $att_id = media_handle_upload('cnp_featured_image', $edit_id);
                            if (!is_wp_error($att_id)) {
                                set_post_thumbnail($edit_id, $att_id);
                            }
                        }
                        $res = wp_update_post($upd, true);
                        if (is_wp_error($res)) {
                            echo '<div class="notice notice-error"><p>Fehler beim Aktualisieren: ' . esc_html($res->get_error_message()) . '</p></div>';
                        } else {
                            update_post_meta($edit_id, '_cnp_news_url', $url);
                            echo '<div class="notice notice-success"><p>Eintrag aktualisiert.</p></div>';
                        }
                    }
                }
            }
            // DELETE
            elseif ($action === 'delete') {
                $del_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
                if (!$del_id || !current_user_can('delete_post', $del_id)) {
                    echo '<div class="notice notice-error"><p>Keine Berechtigung oder ungültige ID (Delete).</p></div>';
                } else {
                    wp_delete_post($del_id, true);
                    echo '<div class="notice notice-success"><p>Eintrag gelöscht.</p></div>';
                }
            }
        }
    }

    // WP_Query
    $args = array(
        'post_type'      => 'newsbereich',
        'post_status'    => array('publish', 'draft', 'future'),
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
    if (!current_user_can('edit_others_newsbereiche')) {
        $args['author'] = get_current_user_id();
    }
    $q = new WP_Query($args);

    // Optionales Filter-Formular
    $per_page_options = array(10,20,50,100);
    $per_page = (int) cnp_get_req('cnp_per_page', 10);
    if (!in_array($per_page, $per_page_options)) { $per_page = 10; }
    $orderby = cnp_get_req('cnp_orderby', 'date');
    $order   = strtoupper(cnp_get_req('cnp_order', 'DESC'));
    if (!in_array($orderby, array('date','title'))) { $orderby = 'date'; }
    if (!in_array($order, array('ASC','DESC'))) { $order = 'DESC'; }
    $filter_cat = cnp_get_req('cnp_filter_cat', '');
    $paged = max(1, (int) cnp_get_req('cnp_paged', 1));
    ?>
    <form method="get" class="cnp-filter-form">
      <label>Kategorie:</label>
      <select name="cnp_filter_cat">
        <option value="">(Alle)</option>
        <?php foreach ($all_cats as $c):
                $sel = ($filter_cat === $c->slug) ? 'selected' : ''; ?>
          <option value="<?php echo esc_attr($c->slug); ?>" <?php echo $sel; ?>>
            <?php echo esc_html($c->name); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <label>Sortieren nach:</label>
      <select name="cnp_orderby">
        <option value="date" <?php selected($orderby, 'date'); ?>>Datum</option>
        <option value="title" <?php selected($orderby, 'title'); ?>>Titel</option>
      </select>
      <select name="cnp_order">
        <option value="ASC" <?php selected($order, 'ASC'); ?>>Aufsteigend</option>
        <option value="DESC" <?php selected($order, 'DESC'); ?>>Absteigend</option>
      </select>
      <label>Pro Seite:</label>
      <select name="cnp_per_page">
        <?php foreach ($per_page_options as $ppo): ?>
          <option value="<?php echo intval($ppo); ?>" <?php selected($per_page, $ppo); ?>>
            <?php echo intval($ppo); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit" class="button">Anwenden</button>
    </form>

    <!-- Button für neuen Eintrag -->
    <button type="button" class="button" id="cnp-open-create-modal">+ Neuer Eintrag</button>

    <!-- CREATE MODAL -->
    <div id="cnp-modal-create" class="cnp-modal-overlay">
      <div class="cnp-modal-content">
        <button type="button" class="cnp-close-modal" data-close="#cnp-modal-create">&times;</button>
        <h2>Neuen Eintrag erstellen</h2>
        <!-- Typ-Switch -->
        <div style="margin-bottom:10px;">
          <strong>Typ (News oder Veranstaltung):</strong><br>
          <label class="cnp-switch">
            <input type="checkbox" id="cnp-type-switch-create">
            <span class="cnp-slider"></span>
          </label>
          <span id="cnp-type-label-create">News</span>
        </div>
        <form method="post" enctype="multipart/form-data">
          <?php 
            // Hier => WP-Nonce:
            wp_nonce_field('cnp_modal_action', 'cnp_modal_nonce'); 
          ?>
          <input type="hidden" name="cnp_action" value="create">
          <div style="margin-bottom:10px;">
            <label>Titel*:</label><br>
            <input type="text" name="cnp_news_title" style="width:100%;">
          </div>
          <div style="margin-bottom:10px;">
            <label>Inhalt*:</label><br>
            <?php
            $create_editor_id = 'cnp_create_editor_' . rand(1000, 9999);
            wp_editor('', $create_editor_id, array(
                'textarea_name' => 'cnp_news_content',
                'textarea_rows' => 15,
                'media_buttons' => true,
            ));
            ?>
          </div>
          <div style="margin-bottom:10px;">
            <label>Veröffentlichungsdatum (dd.mm.yyyy):</label><br>
            <input type="text" name="cnp_publish_date" style="width:100%;">
          </div>
          <div style="margin-bottom:10px;">
            <label id="cnp_display_until_label_create">Anzeigen bis (optional, dd.mm.yyyy):</label><br>
            <input type="text" name="cnp_display_until" style="width:100%;">
          </div>
          <!-- Eventfelder (nur sichtbar bei Veranstaltung) -->
          <div id="cnp-event-fields-create" style="display:none;">
            <div style="margin-bottom:10px;">
              <label>Veranstaltungsstart (optional, dd.mm.yyyy):</label><br>
              <input type="text" name="cnp_event_start" style="width:100%;">
            </div>
            <div style="margin-bottom:10px;">
              <label>Ort (optional):</label><br>
              <input type="text" name="cnp_event_location" style="width:100%;">
            </div>
            <div style="margin-bottom:10px;">
              <label>Edugrant verfügbar:</label><br>
              <label class="cnp-switch">
                <input type="checkbox" name="cnp_edugrant" value="1">
                <span class="cnp-slider"></span>
              </label>
            </div>
          </div>
          <div style="margin-bottom:10px;">
            <label>Kategorien:</label><br>
            <?php cnp_category_checklist($all_cats); ?>
          </div>
          <div style="margin-bottom:10px;">
            <label>Neue Kategorie (optional):</label><br>
            <input type="text" name="cnp_new_category" style="width:100%;">
          </div>
          <div style="margin-bottom:10px;">
            <label>Beitragsbild (optional):</label><br>
            <input type="file" name="cnp_featured_image" accept="image/*">
          </div>
          <div style="margin-bottom:10px;">
            <label>Externe URL (optional):</label><br>
            <input type="url" name="cnp_news_url" style="width:100%;" placeholder="https://...">
          </div>
          <button type="submit" class="button button-primary">Erstellen</button>
        </form>
      </div>
    </div>

    <hr style="margin:1em 0;">
    <h2>Liste aller Einträge</h2>
    <?php
    if (!$q->have_posts()) {
        echo '<p>Keine Einträge gefunden.</p>';
    } else {
        echo '<table class="widefat fixed striped">';
        echo '<thead><tr>
               <th>Bild</th>
               <th>Titel</th>
               <th>Datum</th>
               <th>Status</th>
               <th>Publiziert?</th>
               <th>Aktionen</th>
             </tr></thead><tbody>';
        while ($q->have_posts()) {
            $q->the_post();
            $nid = get_the_ID();
            $pd = get_post_meta($nid, '_cnp_publish_date', true);
            $pd_str = $pd ? date_i18n('d.m.Y', strtotime($pd)) : '-';
            $thumb_html = has_post_thumbnail($nid) ? get_the_post_thumbnail($nid, 'cnp_news_thumbnail') : 'Kein Bild';
            $st_obj = get_post_status_object(get_post_status($nid));
            $st_lbl = $st_obj ? $st_obj->label : '-';
            $is_published = (get_post_status($nid) === 'publish');
            ?>
            <tr>
              <td><?php echo $thumb_html; ?></td>
              <td><?php echo esc_html(get_the_title()); ?></td>
              <td><?php echo esc_html($pd_str); ?></td>
              <td><?php echo esc_html($st_lbl); ?></td>
              <td>
                <form method="post" style="display:inline;">
                  <?php wp_nonce_field('cnp_modal_action', 'cnp_modal_nonce'); ?>
                  <input type="hidden" name="cnp_action" value="toggle_publish">
                  <input type="hidden" name="post_id" value="<?php echo intval($nid); ?>">
                  <label class="cnp-switch">
                    <input type="checkbox" <?php if ($is_published) echo 'checked'; ?> onchange="this.form.submit()">
                    <span class="cnp-slider"></span>
                  </label>
                </form>
              </td>
              <td>
                <div style="display: flex; gap: 10px; align-items: center;">
                  <button type="button" class="button cnp-open-edit-modal" data-postid="<?php echo intval($nid); ?>">Bearbeiten</button>
                  <form method="post" onsubmit="return confirm('Löschen?');">
                    <?php wp_nonce_field('cnp_modal_action', 'cnp_modal_nonce'); ?>
                    <input type="hidden" name="cnp_action" value="delete">
                    <input type="hidden" name="post_id" value="<?php echo intval($nid); ?>">
                    <button type="submit" class="button button-secondary">Löschen</button>
                  </form>
                </div>
              </td>
            </tr>
            <?php
        }
        echo '</tbody></table>';
        wp_reset_postdata();
    }

    // EDIT-Modals
    $q->rewind_posts();
    while ($q->have_posts()) {
        $q->the_post();
        $nid = get_the_ID();
        $title_val = get_the_title();
        $cont_val = get_the_content();
        $pd = get_post_meta($nid, '_cnp_publish_date', true);
        $pd_val = $pd ? date_i18n('d.m.Y', strtotime($pd)) : '';
        $es = get_post_meta($nid, '_cnp_event_start', true);
        $es_val = $es ? date_i18n('d.m.Y', strtotime($es)) : '';
        $du = get_post_meta($nid, '_cnp_display_until', true);
        $du_val = $du ? date_i18n('d.m.Y', strtotime($du)) : '';
        $loc_val = get_post_meta($nid, '_cnp_event_location', true);
        $url_val = get_post_meta($nid, '_cnp_news_url', true);
        $cats_sel = wp_get_post_terms($nid, 'category', array('fields' => 'ids'));
        $modal_id = 'cnp-modal-edit-' . $nid;
        $edugrant_val = get_post_meta($nid, '_cnp_edugrant', true);
        // Ermittlung des Typs: Veranstaltung, wenn Event-Daten vorhanden sind
        $is_event = (!empty($es_val) || !empty($loc_val) || $edugrant_val == 1);
        ?>
        <div id="<?php echo esc_attr($modal_id); ?>" class="cnp-modal-overlay">
          <div class="cnp-modal-content">
            <button type="button" class="cnp-close-modal" data-close="#<?php echo esc_attr($modal_id); ?>">&times;</button>
            <h2>Bearbeiten (ID: <?php echo intval($nid); ?>)</h2>
            <!-- Typ-Switch -->
            <div style="margin-bottom:10px;">
              <strong>Typ (News oder Veranstaltung):</strong><br>
              <label class="cnp-switch">
                <input type="checkbox" class="cnp-type-switch-edit" id="cnp-type-switch-edit-<?php echo intval($nid); ?>" <?php echo ($is_event ? 'checked' : ''); ?>>
                <span class="cnp-slider"></span>
              </label>
              <span id="cnp-type-label-edit-<?php echo intval($nid); ?>"><?php echo ($is_event ? 'Veranstaltung' : 'News'); ?></span>
            </div>
            <form method="post" enctype="multipart/form-data">
              <?php wp_nonce_field('cnp_modal_action', 'cnp_modal_nonce'); ?>
              <input type="hidden" name="cnp_action" value="edit">
              <input type="hidden" name="post_id" value="<?php echo intval($nid); ?>">
              <div style="margin-bottom:10px;">
                <label>Titel*:</label><br>
                <input type="text" name="cnp_news_title" style="width:100%;" value="<?php echo esc_attr($title_val); ?>">
              </div>
              <div style="margin-bottom:10px;">
                <label>Inhalt*:</label><br>
                <?php
                $ed_id = 'cnp_edit_' . rand(1000, 9999);
                wp_editor($cont_val, $ed_id, array(
                    'textarea_name' => 'cnp_news_content',
                    'textarea_rows' => 15,
                    'media_buttons' => true,
                ));
                ?>
              </div>
              <div style="margin-bottom:10px;">
                <label>Veröffentlichungsdatum (dd.mm.yyyy):</label><br>
                <input type="text" name="cnp_publish_date" style="width:100%;" value="<?php echo esc_attr($pd_val); ?>">
              </div>
              <!-- Eventfelder in Edit-Modals -->
              <div id="cnp-event-fields-edit-<?php echo intval($nid); ?>" style="display:<?php echo ($is_event ? 'block' : 'none'); ?>;">
                <div style="margin-bottom:10px;">
                  <label>Veranstaltungsstart (optional, dd.mm.yyyy):</label><br>
                  <input type="text" name="cnp_event_start" style="width:100%;" value="<?php echo esc_attr($es_val); ?>">
                </div>
              </div>
              <div style="margin-bottom:10px;">
                <label id="cnp_display_until_label_edit-<?php echo intval($nid); ?>"><?php echo ($is_event ? 'Veranstaltungsende (dd.mm.yyyy):' : 'Anzeigen bis (optional, dd.mm.yyyy):'); ?></label><br>
                <input type="text" name="cnp_display_until" style="width:100%;" value="<?php echo esc_attr($du_val); ?>">
              </div>
              <div id="cnp-event-fields-edit-<?php echo intval($nid); ?>-2" style="display:<?php echo ($is_event ? 'block' : 'none'); ?>;">
                <div style="margin-bottom:10px;">
                  <label>Ort (optional):</label><br>
                  <input type="text" name="cnp_event_location" style="width:100%;" value="<?php echo esc_attr($loc_val); ?>">
                </div>
                <div style="margin-bottom:10px;">
                  <label>Edugrant verfügbar:</label><br>
                  <label class="cnp-switch">
                    <input type="checkbox" name="cnp_edugrant" value="1" <?php echo ($edugrant_val == 1 ? 'checked' : ''); ?>>
                    <span class="cnp-slider"></span>
                  </label>
                </div>
              </div>
              <div style="margin-bottom:10px;">
                <label>Kategorien:</label><br>
                <?php cnp_category_checklist($all_cats, 0, $cats_sel); ?>
              </div>
              <div style="margin-bottom:10px;">
                <label>Neue Kategorie (optional):</label><br>
                <input type="text" name="cnp_new_category" style="width:100%;">
              </div>
              <div style="margin-bottom:10px;">
                <label>Beitragsbild (optional):</label><br>
                <input type="file" name="cnp_featured_image" accept="image/*">
              </div>
              <div style="margin-bottom:10px;">
                <label>Externe URL (optional):</label><br>
                <input type="url" name="cnp_news_url" style="width:100%;" value="<?php echo esc_attr($url_val); ?>">
              </div>
              <button type="submit" class="button button-primary">Speichern</button>
            </form>
          </div>
        </div>
        <?php
    }
    wp_reset_postdata();
    ?>

    <script>
    document.addEventListener('DOMContentLoaded', function(){
        // CREATE Modal
        const createBtn = document.getElementById('cnp-open-create-modal');
        const createModal = document.getElementById('cnp-modal-create');
        if (createModal) { createModal.style.display = 'none'; }
        if (createBtn && createModal) {
            createBtn.addEventListener('click', function(e){
                e.preventDefault();
                createModal.style.display = 'block';
            });
        }

        // Typ-Switch in Create Modal
        const typeSwitchCreate = document.getElementById('cnp-type-switch-create');
        if (typeSwitchCreate) {
            typeSwitchCreate.addEventListener('change', function(){
                var isEvent = this.checked;
                var label = document.getElementById('cnp-type-label-create');
                label.textContent = isEvent ? 'Veranstaltung' : 'News';
                // Eventfelder ein-/ausblenden
                var eventFields = document.getElementById('cnp-event-fields-create');
                if (eventFields) {
                    eventFields.style.display = isEvent ? 'block' : 'none';
                }
                // Label des "Anzeigen bis" Feldes anpassen
                var displayUntilLabel = document.getElementById('cnp_display_until_label_create');
                if (displayUntilLabel) {
                    displayUntilLabel.textContent = isEvent ? 'Veranstaltungsende (dd.mm.yyyy):' : 'Anzeigen bis (optional, dd.mm.yyyy):';
                }
            });
        }

        // EDIT Modals: Typ-Switch
        document.querySelectorAll('.cnp-type-switch-edit').forEach(function(switchEl){
            switchEl.addEventListener('change', function(){
                var isEvent = this.checked;
                // Extrahiere eindeutige ID anhand der Element-ID (Format: cnp-type-switch-edit-{ID})
                var modalId = this.id.replace('cnp-type-switch-edit-', '');
                var label = document.getElementById('cnp-type-label-edit-' + modalId);
                if (label) {
                    label.textContent = isEvent ? 'Veranstaltung' : 'News';
                }
                // Es gibt zwei Container in Edit-Modals: einen für Veranstaltungsstart und einen für Ort/Edugrant
                var eventFields1 = document.getElementById('cnp-event-fields-edit-' + modalId);
                if (eventFields1) {
                    eventFields1.style.display = isEvent ? 'block' : 'none';
                }
                var eventFields2 = document.getElementById('cnp-event-fields-edit-' + modalId + '-2');
                if (eventFields2) {
                    eventFields2.style.display = isEvent ? 'block' : 'none';
                }
                var displayUntilLabel = document.getElementById('cnp_display_until_label_edit-' + modalId);
                if (displayUntilLabel) {
                    displayUntilLabel.textContent = isEvent ? 'Veranstaltungsende (dd.mm.yyyy):' : 'Anzeigen bis (optional, dd.mm.yyyy):';
                }
            });
        });

        // EDIT Modals öffnen
        const editBtns = document.querySelectorAll('.cnp-open-edit-modal');
        editBtns.forEach(function(btn){
            btn.addEventListener('click', function(e){
                e.preventDefault();
                const pid = this.getAttribute('data-postid');
                const modal = document.getElementById('cnp-modal-edit-' + pid);
                if (modal) { modal.style.display = 'block'; }
            });
        });

        // CLOSE Buttons
        const closeBtns = document.querySelectorAll('.cnp-close-modal');
        closeBtns.forEach(function(cb){
            cb.addEventListener('click', function(e){
                e.preventDefault();
                const target = this.getAttribute('data-close');
                const overlay = document.querySelector(target);
                if (overlay) { overlay.style.display = 'none'; }
            });
        });
    });
    </script>
    <?php

    return ob_get_clean();
}
add_shortcode('list_creation_edit_modal', 'cnp_list_creation_edit_modal_shortcode');

// NEUE EINZIGE ÄNDERUNG: Synchronisation des Veröffentlichungsdatums mit dem WordPress-Beitragsdatum
add_filter('wp_insert_post_data', function($data, $postarr) {
    if ( isset($postarr['meta_input']['_cnp_publish_date']) && $data['post_type'] === 'newsbereich' ) {
        $pd = $postarr['meta_input']['_cnp_publish_date'];
        $data['post_date'] = $pd . ' 00:00:00';
        $data['post_date_gmt'] = get_gmt_from_date($data['post_date']);
    }
    return $data;
}, 10, 2);
?>
