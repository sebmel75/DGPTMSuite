<?php
/*
Plugin Name: Timeline Plus – v1.4 CPT + Manager (1.6.7, Button-Link)
Description: 'timeline_entry' + Frontend-Manager. Neu: pro Eintrag Button (Label/URL/Ziel) – Felder _timeline_btn_label, _timeline_btn_url, _timeline_btn_target. Beinhaltet Skalierung, Status, Jahr-Pflicht, Thumbnails etc.
Version: 1.6.7
Author: ChatGPT
License: GPLv2 or later
Text Domain: timeline-plus
*/

if (!defined('ABSPATH')) exit;

define('TPL47_VERSION', '1.6.7');
define('TPL47_URL', plugin_dir_url(__FILE__));
define('TPL47_PATH', plugin_dir_path(__FILE__));

function tpl47_can_manage(){
    if (!is_user_logged_in()) return false;
    $uid = get_current_user_id();
    $toggle = get_user_meta($uid, 'timeline', true);
    $allowed = in_array(strtolower((string)$toggle), array('1','true','yes','on'), true);
    if (!$allowed && current_user_can('manage_options')) $allowed = true;
    return (bool) apply_filters('tpl47_can_manage', $allowed, $uid);
}

// CPT & REST meta
add_action('init', function () {
    register_post_type('timeline_entry', array(
        'labels' => array(
            'name'               => __('Timeline', 'timeline-plus'),
            'singular_name'      => __('Timeline-Eintrag', 'timeline-plus'),
            'add_new'            => __('Neu hinzufügen', 'timeline-plus'),
            'add_new_item'       => __('Timeline-Eintrag hinzufügen', 'timeline-plus'),
            'edit_item'          => __('Timeline-Eintrag bearbeiten', 'timeline-plus'),
            'new_item'           => __('Neuer Timeline-Eintrag', 'timeline-plus'),
            'view_item'          => __('Timeline-Eintrag ansehen', 'timeline-plus'),
            'search_items'       => __('Timeline durchsuchen', 'timeline-plus'),
            'not_found'          => __('Keine Einträge gefunden', 'timeline-plus'),
            'not_found_in_trash' => __('Keine Einträge im Papierkorb', 'timeline-plus'),
        ),
        'public'       => true,
        'has_archive'  => false,
        'show_in_rest' => true,
        'menu_icon'    => 'dashicons-clock',
        'supports'     => array('title','editor','excerpt','thumbnail'),
        'rewrite'      => array('slug' => 'timeline')
    ));

    $rest_true='__return_true';
    register_post_meta('timeline_entry','_tp_year',array('type'=>'string','single'=>true,'show_in_rest'=>true,'sanitize_callback'=>'sanitize_text_field','auth_callback'=>$rest_true));
    register_post_meta('timeline_entry','_tp_ts',array('type'=>'integer','single'=>true,'show_in_rest'=>true,'auth_callback'=>$rest_true));
    register_post_meta('timeline_entry','_dtl_year',array('type'=>'integer','single'=>true,'show_in_rest'=>true,'auth_callback'=>$rest_true));
    register_post_meta('timeline_entry','_timeline_year',array('type'=>'integer','single'=>true,'show_in_rest'=>true,'auth_callback'=>$rest_true));
    register_post_meta('timeline_entry','timeline_kurz',array('type'=>'string','single'=>true,'show_in_rest'=>true,'sanitize_callback'=>'wp_kses_post','auth_callback'=>$rest_true));
    register_post_meta('timeline_entry','_timeline_bild',array('type'=>'integer','single'=>true,'show_in_rest'=>true,'auth_callback'=>$rest_true));
    // Button fields
    register_post_meta('timeline_entry','_timeline_btn_label',array('type'=>'string','single'=>true,'show_in_rest'=>true,'sanitize_callback'=>'sanitize_text_field','auth_callback'=>$rest_true));
    register_post_meta('timeline_entry','_timeline_btn_url',array('type'=>'string','single'=>true,'show_in_rest'=>true,'sanitize_callback'=>'esc_url_raw','auth_callback'=>$rest_true));
    register_post_meta('timeline_entry','_timeline_btn_target',array('type'=>'integer','single'=>true,'show_in_rest'=>true,'auth_callback'=>$rest_true));
});

// Admin Metabox (optional)
add_action('add_meta_boxes', function () {
    add_meta_box('tpl47_box', __('Timeline-Daten', 'timeline-plus'), function ($post) {
        $date  = get_post_meta($post->ID, '_tp_year', true);
        $year  = get_post_meta($post->ID, '_dtl_year', true);
        $short = get_post_meta($post->ID, 'timeline_kurz', true);
        $bild  = get_post_meta($post->ID, '_timeline_bild', true);
        $blabel= get_post_meta($post->ID, '_timeline_btn_label', true);
        $burl  = get_post_meta($post->ID, '_timeline_btn_url', true);
        $bnew  = get_post_meta($post->ID, '_timeline_btn_target', true) ? 1 : 0;
        wp_nonce_field('tpl47_meta', 'tpl47_meta_nonce'); ?>
        <p><label><strong><?php _e('Datum (YYYY-MM-DD)', 'timeline-plus'); ?></strong></label><br/>
           <input type="date" name="tpl47_date" value="<?php echo esc_attr($date); ?>"></p>
        <p><label><strong><?php _e('Jahr (Zahl, Pflicht)', 'timeline-plus'); ?></strong></label><br/>
           <input type="number" name="tpl47_year" value="<?php echo esc_attr($year); ?>" min="0" step="1" required></p>
        <p><label><strong><?php _e('Kurzbeschreibung', 'timeline-plus'); ?></strong></label><br/>
           <input type="text" name="tpl47_short" value="<?php echo esc_attr($short); ?>" style="width:100%"></p>
        <p><label><strong><?php _e('Bild (Attachment ID) _timeline_bild', 'timeline-plus'); ?></strong></label><br/>
           <input type="number" name="tpl47_bild" value="<?php echo esc_attr($bild); ?>"></p>
        <hr/>
        <p><label><strong><?php _e('Button-Text', 'timeline-plus'); ?></strong></label><br/>
           <input type="text" name="tpl47_btn_label" value="<?php echo esc_attr($blabel); ?>" style="width:100%"></p>
        <p><label><strong><?php _e('Button-URL', 'timeline-plus'); ?></strong></label><br/>
           <input type="url" name="tpl47_btn_url" value="<?php echo esc_attr($burl); ?>" style="width:100%"></p>
        <p><label><input type="checkbox" name="tpl47_btn_target" value="1" <?php checked($bnew,1); ?>> <?php _e('In neuem Tab öffnen', 'timeline-plus'); ?></label></p>
    <?php }, 'timeline_entry', 'side');
});
add_action('save_post_timeline_entry', function ($post_id) {
    if (!isset($_POST['tpl47_meta_nonce']) || !wp_verify_nonce($_POST['tpl47_meta_nonce'], 'tpl47_meta')) return;
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $date = isset($_POST['tpl47_date']) ? sanitize_text_field($_POST['tpl47_date']) : '';
    if ($date!==''){
        update_post_meta($post_id, '_tp_year', $date);
        $ts = strtotime($date.' 00:00:00'); if ($ts) update_post_meta($post_id, '_tp_ts', $ts);
    }
    if (isset($_POST['tpl47_year']) && $_POST['tpl47_year']!=='') {
        $y = intval($_POST['tpl47_year']);
        update_post_meta($post_id, '_dtl_year', $y);
        update_post_meta($post_id, '_timeline_year', $y);
    }
    if (isset($_POST['tpl47_short'])) update_post_meta($post_id, 'timeline_kurz', wp_kses_post($_POST['tpl47_short']));
    if (isset($_POST['tpl47_bild'])) update_post_meta($post_id, '_timeline_bild', intval($_POST['tpl47_bild']));

    // Button
    update_post_meta($post_id, '_timeline_btn_label', isset($_POST['tpl47_btn_label']) ? sanitize_text_field($_POST['tpl47_btn_label']) : '');
    update_post_meta($post_id, '_timeline_btn_url',   isset($_POST['tpl47_btn_url']) ? esc_url_raw($_POST['tpl47_btn_url']) : '');
    update_post_meta($post_id, '_timeline_btn_target', !empty($_POST['tpl47_btn_target']) ? 1 : 0);
});

// Assets
add_action('wp_enqueue_scripts', function () {
    wp_register_style('tpl47-css', TPL47_URL . 'assets/manager.css', array(), TPL47_VERSION);
    wp_register_script('tpl47-js', TPL47_URL . 'assets/manager.js', array('jquery'), TPL47_VERSION, true);
    wp_localize_script('tpl47-js', 'TPL47', array(
        'ajax'  => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('tpl47_ajax'),
        'i18n'  => array(
            'close' => __('Schließen','timeline-plus'),
            'deleteConfirm' => __('Eintrag wirklich löschen?','timeline-plus'),
            'removeImageConfirm' => __('Bild-Zuweisung wirklich entfernen? (Datei bleibt in der Mediathek)', 'timeline-plus'),
            'resizeConfirm' => __('Eine skalierte Kopie wird erstellt und zugewiesen. Original bleibt erhalten. Fortfahren?', 'timeline-plus'),
            'errorLoad' => __('Fehler beim Laden.','timeline-plus'),
            'errorSave' => __('Speichern fehlgeschlagen.','timeline-plus'),
            'errorDelete' => __('Löschen fehlgeschlagen.','timeline-plus'),
        ),
    ));
});

// Frontend-Manager (Form + Scaling wie 1.6.6)
function tpl47_render_manager(){
    if (!tpl47_can_manage()) return '<div class="tpl47-noaccess">'.esc_html__('','timeline-plus').'</div>';
    wp_enqueue_style('tpl47-css'); wp_enqueue_script('tpl47-js'); wp_enqueue_media();
    $nonce = wp_create_nonce('tpl47_ajax');
    ob_start(); ?>
    <div class="tpl47-manager">
      <button class="tpl47-open button button-primary" type="button" data-nonce="<?php echo esc_attr($nonce); ?>"><?php esc_html_e('Timeline bearbeiten','timeline-plus'); ?></button>

      <div class="tpl47-modal" style="display:none">
        <div class="tpl47-modal-inner">
          <button class="tpl47-close" type="button" aria-label="<?php esc_attr_e('Schließen','timeline-plus'); ?>">&times;</button>
          <h3><?php esc_html_e('Timeline bearbeiten','timeline-plus'); ?></h3>

          <div class="tpl47-grid">
            <div class="tpl47-form">
              <form class="tpl47-editor">
                <input type="hidden" name="post_id" value="">
                <p><label><?php esc_html_e('Titel','timeline-plus'); ?></label><input type="text" name="title" required></p>
                <p><label><?php esc_html_e('Datum (YYYY-MM-DD)','timeline-plus'); ?></label><input type="date" name="date" required></p>
                <p><label><?php esc_html_e('Jahr (Zahl – Pflicht)','timeline-plus'); ?></label><input type="number" name="yearn" min="0" step="1" required></p>
                <p><label><?php esc_html_e('Status','timeline-plus'); ?></label>
                   <select name="status">
                     <option value="publish"><?php esc_html_e('Veröffentlicht','timeline-plus'); ?></option>
                     <option value="draft"><?php esc_html_e('Entwurf','timeline-plus'); ?></option>
                   </select>
                </p>
                <p><label><?php esc_html_e('Kurztext (HTML erlaubt)','timeline-plus'); ?></label><textarea name="short" rows="3"></textarea></p>
                <p><label><?php esc_html_e('Volltext','timeline-plus'); ?></label><textarea name="content" rows="8"></textarea></p>
                <fieldset class="tpl47-button">
                  <legend><?php esc_html_e('Schaltfläche (optional)','timeline-plus'); ?></legend>
                  <p><label><?php esc_html_e('Button-Text','timeline-plus'); ?></label><input type="text" name="btn_label"></p>
                  <p><label><?php esc_html_e('Button-URL','timeline-plus'); ?></label><input type="url" name="btn_url" placeholder="https://..."></p>
                  <p><label><input type="checkbox" name="btn_target" value="1"> <?php esc_html_e('In neuem Tab öffnen','timeline-plus'); ?></label></p>
                </fieldset>
                <div class="tpl47-image">
                  <label><?php esc_html_e('Bild (Thumbnail-Vorschau, entfernbar & skalierbar)','timeline-plus'); ?></label>
                  <div class="tpl47-image-row">
                    <img class="tpl47-preview" src="" alt="" style="display:none" />
                    <button type="button" class="button button-secondary tpl47-remove-image" style="display:none"><?php esc_html_e('Bild entfernen','timeline-plus'); ?></button>
                  </div>
                  <div class="tpl47-scale">
                    <div class="tpl47-scale-row">
                      <input type="number" name="img_max_w" min="0" placeholder="<?php esc_attr_e('Max Breite (px)','timeline-plus'); ?>">
                      <input type="number" name="img_max_h" min="0" placeholder="<?php esc_attr_e('Max Höhe (px)','timeline-plus'); ?>">
                      <button type="button" class="button tpl47-resize-existing"><?php esc_html_e('Vorhandenes Bild skalieren (Kopie)','timeline-plus'); ?></button>
                    </div>
                    <small><?php esc_html_e('Skaliert proportional (Seitenverhältnis bleibt erhalten). Es wird nie vergrößert, nur verkleinert. Beim Upload werden Werte (falls angegeben) direkt angewendet.','timeline-plus'); ?></small>
                  </div>
                  <input type="file" name="image" accept="image/*">
                </div>
                <p class="tpl47-actions">
                  <button type="submit" class="button button-primary"><?php esc_html_e('Speichern','timeline-plus'); ?></button>
                  <button type="button" class="button tpl47-cancel"><?php esc_html_e('Abbrechen','timeline-plus'); ?></button>
                </p>
              </form>
            </div>
            <div class="tpl47-list">
              <h4><?php esc_html_e('Einträge','timeline-plus'); ?></h4>
              <ul class="tpl47-items"></ul>
            </div>
          </div>
        </div>
      </div>
    </div>
    <?php return ob_get_clean();
}
add_shortcode('timeline_manager', 'tpl47_render_manager');
add_shortcode('timeline-manager', 'tpl47_render_manager');

// Helpers
function tpl47_thumb_for_post($post_id){
    $aid = intval(get_post_meta($post_id, '_timeline_bild', true));
    if (!$aid) $aid = get_post_thumbnail_id($post_id);
    if ($aid){
        $u = wp_get_attachment_image_url($aid, 'thumbnail');
        return $u ? $u : '';
    }
    return '';
}
function tpl47_scale_image_file($file, $max_w=0, $max_h=0){
    if (!file_exists($file)) return new WP_Error('not_found','File not found');
    if (!$max_w && !$max_h) return $file;
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $editor = wp_get_image_editor($file);
    if (is_wp_error($editor)) return $editor;
    $size = $editor->get_size();
    $W = isset($size['width'])?intval($size['width']):0;
    $H = isset($size['height'])?intval($size['height']):0;
    if (!$W || !$H) return new WP_Error('bad_size','Invalid image size');
    $factors = array();
    if ($max_w) $factors[] = $max_w / $W;
    if ($max_h) $factors[] = $max_h / $H;
    $factor = $factors ? min($factors) : 1;
    if ($factor >= 1) return $file; // not enlarging
    $new_w = max(1, intval(floor($W * $factor)));
    $new_h = max(1, intval(floor($H * $factor)));
    $editor->resize($new_w, $new_h, false);
    $info = pathinfo($file);
    $dest = $info['dirname'] . '/' . $info['filename'] . '-scaled-' . $new_w . 'x' . $new_h . '.' . $info['extension'];
    $saved = $editor->save($dest);
    if (is_wp_error($saved)) return $saved;
    return $saved['path'];
}
function tpl47_attach_file($file_path, $parent_post_id=0){
    $filetype = wp_check_filetype(basename($file_path), null);
    $attachment = array(
        'post_mime_type' => $filetype['type'],
        'post_title'     => sanitize_file_name(basename($file_path)),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );
    $attach_id = wp_insert_attachment($attachment, $file_path, $parent_post_id);
    require_once ABSPATH . 'wp-admin/includes/image.php';
    $attach_data = wp_generate_attachment_metadata($attach_id, $file_path);
    wp_update_attachment_metadata($attach_id, $attach_data);
    return $attach_id;
}

// AJAX
add_action('wp_ajax_tpl47_list', function(){
    check_ajax_referer('tpl47_ajax', 'nonce');
    if (!tpl47_can_manage()) wp_send_json_error(array('message'=>__('Kein Zugriff.','timeline-plus')),403);
    $posts = get_posts(array('post_type'=>'timeline_entry','post_status'=>array('publish','draft'),'numberposts'=>-1,'orderby'=>'date','order'=>'DESC'));
    $items = array();
    foreach($posts as $p){
        $items[] = array(
            'id'=>$p->ID,
            'title'=>$p->post_title,
            'status'=>$p->post_status,
            'date'=>get_post_meta($p->ID,'_tp_year',true),
            'ts'=>intval(get_post_meta($p->ID,'_tp_ts',true)),
            'yearn'=>get_post_meta($p->ID,'_dtl_year',true),
            'short'=>get_post_meta($p->ID,'timeline_kurz',true),
            'bild'=>intval(get_post_meta($p->ID,'_timeline_bild',true)),
            'thumb'=>tpl47_thumb_for_post($p->ID),
            'btn_label'=>get_post_meta($p->ID,'_timeline_btn_label',true),
            'btn_url'=>get_post_meta($p->ID,'_timeline_btn_url',true),
            'btn_target'=>get_post_meta($p->ID,'_timeline_btn_target',true)?1:0,
        );
    }
    wp_send_json_success(array('items'=>$items));
});

add_action('wp_ajax_tpl47_load', function(){
    check_ajax_referer('tpl47_ajax', 'nonce');
    if (!tpl47_can_manage()) wp_send_json_error(array('message'=>__('Kein Zugriff.','timeline-plus')),403);
    $id = isset($_POST['id'])?intval($_POST['id']):0;
    $p = get_post($id);
    if(!$p || $p->post_type!=='timeline_entry') wp_send_json_error(array('message'=>__('Nicht gefunden.','timeline-plus')),404);
    wp_send_json_success(array(
        'id'=>$p->ID,
        'title'=>$p->post_title,
        'status'=>$p->post_status,
        'content'=>$p->post_content,
        'date'=>get_post_meta($p->ID,'_tp_year',true),
        'ts'=>intval(get_post_meta($p->ID,'_tp_ts',true)),
        'yearn'=>get_post_meta($p->ID,'_dtl_year',true),
        'short'=>get_post_meta($p->ID,'timeline_kurz',true),
        'bild'=>intval(get_post_meta($p->ID,'_timeline_bild',true)),
        'thumb'=>tpl47_thumb_for_post($p->ID),
        'btn_label'=>get_post_meta($p->ID,'_timeline_btn_label',true),
        'btn_url'=>get_post_meta($p->ID,'_timeline_btn_url',true),
        'btn_target'=>get_post_meta($p->ID,'_timeline_btn_target',true)?1:0,
    ));
});

function tpl47_handle_image_upload($filefield, $max_w=0, $max_h=0, $parent_post_id=0){
    if (!isset($_FILES[$filefield]) || empty($_FILES[$filefield]['name'])) return 0;
    require_once ABSPATH . 'wp-admin/includes/file.php';
    $overrides = array('test_form'=>false);
    $file = wp_handle_upload($_FILES[$filefield], $overrides);
    if (isset($file['error'])) return 0;
    $path = $file['file'];
    $scaled = tpl47_scale_image_file($path, intval($max_w), intval($max_h));
    $final_path = (is_wp_error($scaled) || !$scaled) ? $path : $scaled;
    $attach_id = tpl47_attach_file($final_path, $parent_post_id);
    return $attach_id;
}

add_action('wp_ajax_tpl47_save', function(){
    check_ajax_referer('tpl47_ajax', 'nonce');
    if (!tpl47_can_manage()) wp_send_json_error(array('message'=>__('Kein Zugriff.','timeline-plus')),403);

    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $title   = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
    $date    = isset($_POST['date'])  ? sanitize_text_field($_POST['date']) : '';
    $yearN   = isset($_POST['yearn']) ? intval($_POST['yearn']) : 0;
    $short   = isset($_POST['short']) ? wp_kses_post($_POST['short']) : '';
    $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
    $status  = (isset($_POST['status']) && in_array($_POST['status'], array('publish','draft'), true)) ? $_POST['status'] : 'publish';
    $max_w   = isset($_POST['img_max_w']) ? intval($_POST['img_max_w']) : 0;
    $max_h   = isset($_POST['img_max_h']) ? intval($_POST['img_max_h']) : 0;
    $btn_label = isset($_POST['btn_label']) ? sanitize_text_field($_POST['btn_label']) : '';
    $btn_url   = isset($_POST['btn_url']) ? esc_url_raw($_POST['btn_url']) : '';
    $btn_target= !empty($_POST['btn_target']) ? 1 : 0;

    if (!$title || !$date || !$yearN) wp_send_json_error(array('message'=>__('Titel, Datum und Jahr sind erforderlich.','timeline-plus')),400);

    $data = array('post_title'=>$title,'post_content'=>$content,'post_type'=>'timeline_entry','post_status'=>$status);
    if ($post_id>0) { $data['ID']=$post_id; $post_id = wp_update_post($data,true); }
    else { $post_id = wp_insert_post($data,true); }
    if (is_wp_error($post_id)) wp_send_json_error(array('message'=>$post_id->get_error_message()),500);

    update_post_meta($post_id, '_tp_year',  $date);
    $ts = strtotime($date.' 00:00:00'); if ($ts) update_post_meta($post_id, '_tp_ts', $ts);
    update_post_meta($post_id, '_dtl_year', $yearN);
    update_post_meta($post_id, '_timeline_year', $yearN);
    update_post_meta($post_id, 'timeline_kurz', $short);

    // Button
    update_post_meta($post_id, '_timeline_btn_label', $btn_label);
    update_post_meta($post_id, '_timeline_btn_url', $btn_url);
    update_post_meta($post_id, '_timeline_btn_target', $btn_target);

    // Image upload (with optional scale)
    $attach_id = tpl47_handle_image_upload('image', $max_w, $max_h, $post_id);
    if ($attach_id){
        set_post_thumbnail($post_id, $attach_id);
        update_post_meta($post_id, '_timeline_bild', $attach_id);
    }

    wp_send_json_success(array('id'=>$post_id));
});

add_action('wp_ajax_tpl47_remove_image', function(){
    check_ajax_referer('tpl47_ajax', 'nonce');
    if (!tpl47_can_manage()) wp_send_json_error(array('message'=>__('Kein Zugriff.','timeline-plus')),403);
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    if(!$post_id) wp_send_json_error(array('message'=>__('Ungültige ID.','timeline-plus')),400);
    delete_post_meta($post_id, '_timeline_bild');
    delete_post_thumbnail($post_id);
    wp_send_json_success(array('ok'=>true));
});

add_action('wp_ajax_tpl47_resize_image', function(){
    check_ajax_referer('tpl47_ajax', 'nonce');
    if (!tpl47_can_manage()) wp_send_json_error(array('message'=>__('Kein Zugriff.','timeline-plus')),403);
    $post_id = isset($_POST['post_id']) ? intval($_POST['post_id']) : 0;
    $max_w   = isset($_POST['img_max_w']) ? intval($_POST['img_max_w']) : 0;
    $max_h   = isset($_POST['img_max_h']) ? intval($_POST['img_max_h']) : 0;
    if(!$post_id) wp_send_json_error(array('message'=>__('Ungültige ID.','timeline-plus')),400);
    $aid = intval(get_post_meta($post_id, '_timeline_bild', true));
    if (!$aid) $aid = get_post_thumbnail_id($post_id);
    if (!$aid) wp_send_json_error(array('message'=>__('Kein Bild zugewiesen.','timeline-plus')),400);
    $file = get_attached_file($aid);
    if (!$file || !file_exists($file)) wp_send_json_error(array('message'=>__('Quelldatei nicht gefunden.','timeline-plus')),500);
    $scaled = tpl47_scale_image_file($file, $max_w, $max_h);
    if (is_wp_error($scaled)) wp_send_json_error(array('message'=>$scaled->get_error_message()),500);
    if (!$scaled || !file_exists($scaled)) wp_send_json_error(array('message'=>__('Skalierung fehlgeschlagen oder nicht nötig.','timeline-plus')),500);
    $new_id = tpl47_attach_file($scaled, $post_id);
    set_post_thumbnail($post_id, $new_id);
    update_post_meta($post_id, '_timeline_bild', $new_id);
    $thumb = wp_get_attachment_image_url($new_id, 'thumbnail');
    wp_send_json_success(array('id'=>$new_id, 'thumb'=>$thumb));
});

add_action('wp_ajax_tpl47_delete', function(){
    check_ajax_referer('tpl47_ajax', 'nonce');
    if (!tpl47_can_manage()) wp_send_json_error(array('message'=>__('Kein Zugriff.','timeline-plus')),403);
    $id = isset($_POST['id'])?intval($_POST['id']):0;
    if(!$id) wp_send_json_error(array('message'=>__('Ungültige ID.','timeline-plus')),400);
    $r = wp_delete_post($id, true);
    if(!$r) wp_send_json_error(array('message'=>__('Löschen fehlgeschlagen.','timeline-plus')),500);
    wp_send_json_success(array('id'=>$id));
});
