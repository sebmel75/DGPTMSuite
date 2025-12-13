<?php
/**
 * Plugin Name: DGPTM - Custom News Plugin
 * Description: Verwaltung und Anzeige von News und Veranstaltungen mit dynamischen Berechtigungen (usermeta) und Shortcodes.
 * Version: 3.1
 * Author: Sebastian Melzer
 * Text Domain: custom-news-plugin
 */

if (!defined('ABSPATH')) {
    exit; // Kein direkter Zugriff
}

/* ----------------------------------------------------------
 * 0) HILFSFUNKTIONEN
 * ---------------------------------------------------------- */

/**
 * map_meta_cap-Callback:
 * Hier werden sowohl unsere CPT-spezifischen "Meta Caps" (z.B. edit_newsbereich)
 * als auch die WP-Standard-Caps (z.B. edit_post, delete_post, publish_post)
 * auf Usermeta-basierte Prüflogik gemappt, **wenn** das Post Type = 'newsbereich' ist.
 * WordPress-Rollen werden NICHT mehr berücksichtigt.
 */
function cnp_map_meta_cap($required_caps, $cap, $user_id, $args) {

    // 1) Wenn der Benutzer nicht eingeloggt ist => verweigern
    if ($user_id === 0) {
        return array('do_not_allow');
    }

    // 2) Metadaten des Benutzers abfragen
    $can_write      = (bool) get_user_meta($user_id, 'news_schreiben', true);
    $can_manage_all = (bool) get_user_meta($user_id, 'news_alle', true);

    // 3) Herausfinden, ob es sich um einen Beitrag vom CPT "newsbereich" handelt.
    $post_id = !empty($args[0]) ? (int)$args[0] : 0;
    $is_news_cpt = false;
    $post_owner_is_current_user = false;
    if ($post_id) {
        $post_obj = get_post($post_id);
        if ($post_obj && $post_obj->post_type === 'newsbereich') {
            $is_news_cpt = true;
            if ((int) $post_obj->post_author === (int) $user_id) {
                $post_owner_is_current_user = true;
            }
        }
    }

    // Kurze Helferfunktion
    $allow_or_not = function($is_own) use($can_write, $can_manage_all) {
        if ($can_manage_all) {
            return array('exist');
        } elseif ($can_write && $is_own) {
            return array('exist');
        }
        return array('do_not_allow');
    };

    // ----------------------------------------------------------------
    // A) Standard-WP-Caps abfangen (edit_post, delete_post, publish_post, read_post)
    //    Nur wenn post_type='newsbereich'
    // ----------------------------------------------------------------
    if ($is_news_cpt) {
        switch ($cap) {
            case 'edit_post':
            case 'delete_post':
            case 'publish_post':
            case 'read_post':
                return $allow_or_not($post_owner_is_current_user);
        }
    }

    // ----------------------------------------------------------------
    // B) CPT-spezifische Caps (z.B. edit_newsbereich, edit_newsbereiche, etc.)
    // ----------------------------------------------------------------
    if (strpos($cap, 'newsbereich') !== false) {
        switch ($cap) {
            /* Einzelne */
            case 'edit_newsbereich':
            case 'read_newsbereich':
            case 'delete_newsbereich':
                return $allow_or_not($post_owner_is_current_user);

            /* Mehrere */
            case 'edit_newsbereiche':
            case 'delete_newsbereiche':
            case 'publish_newsbereiche':
                if (!$post_id) {
                    // Bulk => nur news_alle
                    return $can_manage_all ? array('exist') : array('do_not_allow');
                } else {
                    // Konkrete ID => checke own
                    return $allow_or_not($post_owner_is_current_user);
                }

            case 'read_private_newsbereiche':
                if (!$post_id) {
                    return $can_manage_all ? array('exist') : array('do_not_allow');
                } else {
                    return $allow_or_not($post_owner_is_current_user);
                }

            case 'edit_others_newsbereiche':
            case 'delete_others_newsbereiche':
                return $can_manage_all ? array('exist') : array('do_not_allow');

            case 'edit_private_newsbereiche':
            case 'delete_private_newsbereiche':
            case 'edit_published_newsbereiche':
            case 'delete_published_newsbereiche':
                if (!$post_id) {
                    return $can_manage_all ? array('exist') : array('do_not_allow');
                } else {
                    return $allow_or_not($post_owner_is_current_user);
                }
        }
        // Fallback
        return array('do_not_allow');
    }

    // ----------------------------------------------------------------
    // C) Andere Caps => unsere Funktion ändert nichts
    // ----------------------------------------------------------------
    return $required_caps;
}
add_filter('map_meta_cap', 'cnp_map_meta_cap', 10, 4);


/**
 * Konvertiert ein eingegebenes Datum ("dd.mm.yyyy") nach "yyyy-mm-dd".
 * Ist es leer/ungültig => fallback = heute
 */
function cnp_convert_date_or_fallback_today($input) {
    $input = trim($input);
    if (empty($input)) {
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

/**
 * Falls keine Kategorie gewählt wurde => setze "allgemein"
 */
function cnp_ensure_category_allgemein(&$cat_selected) {
    if (!is_array($cat_selected)) {
        $cat_selected = array();
    }
    if (empty($cat_selected)) {
        $default_cat = get_term_by('slug','allgemein','category');
        if (!$default_cat) {
            $new_term = wp_insert_term('Allgemein','category',array('slug'=>'allgemein'));
            if (!is_wp_error($new_term)) {
                $cat_selected[] = (int)$new_term['term_id'];
            }
        } else {
            $cat_selected[] = (int)$default_cat->term_id;
        }
    }
}

/* ----------------------------------------------------------
 * 1) CPT "newsbereich" registrieren
 * ---------------------------------------------------------- */
function cnp_register_newsbereich_post_type() {
    $labels = array(
        'name'               => __('News', 'custom-news-plugin'),
        'singular_name'      => __('News', 'custom-news-plugin'),
        'add_new'            => __('Neue News hinzufügen', 'custom-news-plugin'),
        'add_new_item'       => __('Neue News hinzufügen', 'custom-news-plugin'),
        'edit_item'          => __('News bearbeiten', 'custom-news-plugin'),
        'new_item'           => __('Neue News', 'custom-news-plugin'),
        'view_item'          => __('News anzeigen', 'custom-news-plugin'),
        'search_items'       => __('News suchen', 'custom-news-plugin'),
        'not_found'          => __('Keine News gefunden', 'custom-news-plugin'),
        'not_found_in_trash' => __('Keine News im Papierkorb gefunden', 'custom-news-plugin'),
        'all_items'          => __('Alle News', 'custom-news-plugin'),
        'menu_name'          => __('News', 'custom-news-plugin'),
        'name_admin_bar'     => __('News', 'custom-news-plugin'),
    );

    $capabilities = array(
        'edit_post'             => 'edit_newsbereich',
        'read_post'             => 'read_newsbereich',
        'delete_post'           => 'delete_newsbereich',
        'edit_posts'            => 'edit_newsbereiche',
        'edit_others_posts'     => 'edit_others_newsbereiche',
        'publish_posts'         => 'publish_newsbereiche',
        'read_private_posts'    => 'read_private_newsbereiche',
        'delete_posts'          => 'delete_newsbereiche',
        'delete_private_posts'  => 'delete_private_newsbereiche',
        'delete_published_posts'=> 'delete_published_newsbereiche',
        'delete_others_posts'   => 'delete_others_newsbereiche',
        'edit_private_posts'    => 'edit_private_newsbereiche',
        'edit_published_posts'  => 'edit_published_newsbereiche',
        'create_posts'          => 'edit_newsbereiche',
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
		'publicly_queryable' => false,
        'has_archive'        => true,
        'supports'           => array('title','editor','thumbnail','excerpt'),
        'taxonomies'         => array('category'),
        'capability_type'    => 'newsbereich',
        'capabilities'       => $capabilities,
        'map_meta_cap'       => true,
        'show_in_rest'       => true,
        'rewrite'            => array('slug'=>'news'),
        'menu_icon'          => 'dashicons-admin-post',
    );

    register_post_type('newsbereich', $args);
}
add_action('init', 'cnp_register_newsbereich_post_type');


/* ----------------------------------------------------------
 * 2) AKTIVIERUNG / DEAKTIVIERUNG
 * ---------------------------------------------------------- */
function cnp_activate_plugin() {
    cnp_register_newsbereich_post_type();
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'cnp_activate_plugin');

function cnp_deactivate_plugin() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'cnp_deactivate_plugin');


/* ----------------------------------------------------------
 * 4) THUMBNAILS
 * ---------------------------------------------------------- */
function cnp_setup_thumbnail_support() {
    add_theme_support('post-thumbnails');
    add_image_size('cnp_news_thumbnail',150,150,true);
}
add_action('after_setup_theme','cnp_setup_thumbnail_support');


/* ----------------------------------------------------------
 * Einbinden der Shortcode-Dateien
 * ---------------------------------------------------------- */
require_once plugin_dir_path(__FILE__) . 'includes/news_shortcodes.php';
require_once plugin_dir_path(__FILE__) . 'includes/news_basis_shortcodes.php';



/* ----------------------------------------------------------
 * 11) PDF-Metabox für CPT 'newsbereich'
 * ---------------------------------------------------------- */
function cnp_news_pdf_add_metabox() {
    add_meta_box(
        'cnp_news_pdf_box',
        __('PDF', 'custom-news-plugin'),
        'cnp_news_pdf_metabox_render',
        'newsbereich',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'cnp_news_pdf_add_metabox');

function cnp_news_pdf_metabox_render($post) {
    wp_nonce_field('cnp_news_pdf_nonce', 'cnp_news_pdf_nonce_field');
    $pdf_url = get_post_meta($post->ID, '_cnp_pdf_url', true);
    ?>
    <p>
        <input type="text" id="cnp_pdf_url" name="cnp_pdf_url" value="<?php echo esc_attr($pdf_url); ?>" style="width:100%;" placeholder="<?php esc_attr_e('Kein PDF ausgewählt', 'custom-news-plugin'); ?>" />
    </p>
    <p>
        <button type="button" class="button" id="cnp_pdf_upload_btn"><?php _e('PDF hochladen/auswählen', 'custom-news-plugin'); ?></button>
        <button type="button" class="button" id="cnp_pdf_remove_btn" style="margin-left:6px;"><?php _e('Entfernen', 'custom-news-plugin'); ?></button>
    </p>
    <script>
    (function($){
        $(document).ready(function(){
            var frame;
            $('#cnp_pdf_upload_btn').on('click', function(e){
                e.preventDefault();
                if (frame) { frame.open(); return; }
                frame = wp.media({
                    title: '<?php echo esc_js(__('PDF auswählen', 'custom-news-plugin')); ?>',
                    button: { text: '<?php echo esc_js(__('Übernehmen', 'custom-news-plugin')); ?>' },
                    library: { type: 'application/pdf' },
                    multiple: false
                });
                frame.on('select', function(){
                    var attachment = frame.state().get('selection').first().toJSON();
                    $('#cnp_pdf_url').val(attachment.url);
                });
                frame.open();
            });
            $('#cnp_pdf_remove_btn').on('click', function(e){
                e.preventDefault();
                $('#cnp_pdf_url').val('');
            });
        });
    })(jQuery);
    </script>
    <?php
}

function cnp_news_pdf_save_meta($post_id){
    if (!isset($_POST['cnp_news_pdf_nonce_field']) || !wp_verify_nonce($_POST['cnp_news_pdf_nonce_field'], 'cnp_news_pdf_nonce')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;

    $pdf_url = isset($_POST['cnp_pdf_url']) ? esc_url_raw($_POST['cnp_pdf_url']) : '';
    if (!empty($pdf_url)) {
        update_post_meta($post_id, '_cnp_pdf_url', $pdf_url);
    } else {
        delete_post_meta($post_id, '_cnp_pdf_url');
    }
}
add_action('save_post_newsbereich','cnp_news_pdf_save_meta');

function cnp_admin_enqueue_media_for_news($hook){
    global $post;
    if (($hook === 'post-new.php' || $hook === 'post.php') && isset($post->post_type) && $post->post_type === 'newsbereich') {
        wp_enqueue_media();
    }
}
add_action('admin_enqueue_scripts', 'cnp_admin_enqueue_media_for_news');
/* ----------------------------------------------------------
 * 10) CSS & JS => Modal etc.
 * ---------------------------------------------------------- */
function cnp_enqueue_scripts(){
    if(!is_admin()){
        wp_enqueue_style('cnp-style', plugin_dir_url(__FILE__).'css/style.css', array(), '3.0');

        // Ensure modal overlay is hidden by default and can be shown when active (fallback if no theme CSS is present)
        wp_add_inline_style('cnp-style', '.cnp-modal-overlay{display:none;position:fixed;z-index:9999;top:0;left:0;right:0;bottom:0;background:rgba(0,0,0,.6);align-items:center;justify-content:center;padding:20px;}.cnp-modal-overlay.active{display:flex;}.cnp-modal-content{background:#fff;max-width:900px;width:100%;padding:20px;position:relative;}.cnp-close-modal{position:absolute;top:8px;right:8px;border:0;background:transparent;font-size:28px;line-height:1;cursor:pointer;}');

        wp_enqueue_script('cnp-modal-script', plugin_dir_url(__FILE__).'js/modal.js', array('jquery'), '3.0', true);
    }
}
add_action('wp_enqueue_scripts','cnp_enqueue_scripts');