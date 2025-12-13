<?php
/**
 * Plugin Name: DGPTM - Publikation Frontend Manager (Medical Enhanced)
 * Description: Professionelles medizinisches Publikations-Management-System mit Review-Workflow, Editorial Decision Interface, Analytics, ACF-Integration für Kardiotechnik
 * Version: 3.0.0
 * Author: Sebastian Melzer
 * Text Domain: publikation-frontend-manager
 */

// Verhindert Direktzugriff
if (!defined('ABSPATH')) {
    exit;
}

define('PFM_VERSION', '3.0.0');
define('PFM_TD', 'publikation-frontend-manager');
define('PFM_PATH', plugin_dir_path(__FILE__));
define('PFM_URL', plugin_dir_url(__FILE__));

/**
 * Include erforderliche Klassen
 */
require_once PFM_PATH . 'includes/class-pfm-review-criteria.php';
require_once PFM_PATH . 'includes/class-pfm-workflow-tracker.php';
require_once PFM_PATH . 'includes/class-pfm-email-templates.php';
require_once PFM_PATH . 'includes/class-pfm-editorial-decision.php';
require_once PFM_PATH . 'includes/class-pfm-file-manager.php';
require_once PFM_PATH . 'includes/class-pfm-conflict-interest.php';
require_once PFM_PATH . 'includes/class-pfm-analytics.php';
require_once PFM_PATH . 'includes/class-pfm-reminders.php';
require_once PFM_PATH . 'includes/class-pfm-medical-fields.php';
require_once PFM_PATH . 'includes/class-pfm-dashboard.php';

/**
 * Enqueue Styles und Scripts
 */
add_action('wp_enqueue_scripts', 'pfm_enqueue_assets');
function pfm_enqueue_assets() {
    wp_enqueue_style('pfm-styles', PFM_URL . 'assets/css/pfm-styles.css', array(), PFM_VERSION);
    wp_enqueue_style('pfm-medical-styles', PFM_URL . 'assets/css/pfm-medical-styles.css', array('pfm-styles'), PFM_VERSION);
    wp_enqueue_style('dashicons');

    wp_enqueue_script('pfm-scripts', PFM_URL . 'assets/js/pfm-scripts.js', array('jquery'), PFM_VERSION, true);

    wp_localize_script('pfm-scripts', 'pfm_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'admin_url' => admin_url(),
        'nonce' => wp_create_nonce('pfm_ajax_nonce'),
        'dashboard_nonce' => wp_create_nonce('pfm_dashboard_nonce'),
    ));
}

/**
 * Admin Styles und Scripts
 * NUR auf relevanten Seiten laden (nicht auf allen Admin-Seiten)
 */
add_action('admin_enqueue_scripts', 'pfm_admin_enqueue_assets');
function pfm_admin_enqueue_assets($hook) {
    // Nur laden auf:
    // 1. Post-Edit-Seiten (post.php, post-new.php)
    // 2. Publikations-spezifischen Seiten

    $screen = get_current_screen();

    // Prüfe ob wir auf einer Publikations-Seite sind
    $is_publication_screen = false;

    if ($screen && $screen->post_type === 'publikation') {
        $is_publication_screen = true;
    }

    // Oder auf Publikations-Admin-Seiten
    if (strpos($hook, 'publikation') !== false || strpos($hook, 'pfm') !== false) {
        $is_publication_screen = true;
    }

    // Nur CSS laden wenn auf relevanter Seite
    if (!$is_publication_screen) {
        return;
    }

    wp_enqueue_style('pfm-admin-styles', PFM_URL . 'assets/css/pfm-styles.css', array(), PFM_VERSION);
    wp_enqueue_style('pfm-admin-medical-styles', PFM_URL . 'assets/css/pfm-medical-styles.css', array('pfm-admin-styles'), PFM_VERSION);
}

/**
 * Internationalisierung
 */
add_action('plugins_loaded', function () {
    load_plugin_textdomain(PFM_TD, false, dirname(plugin_basename(__FILE__)) . '/languages');
});

/**
 * Register Custom Post Type: Publikation
 */
add_action('init', 'pfm_register_post_type');
function pfm_register_post_type() {
    $labels = array(
        'name' => __('Publikationen', PFM_TD),
        'singular_name' => __('Publikation', PFM_TD),
        'add_new' => __('Neue hinzufügen', PFM_TD),
        'add_new_item' => __('Neue Publikation hinzufügen', PFM_TD),
        'edit_item' => __('Publikation bearbeiten', PFM_TD),
        'new_item' => __('Neue Publikation', PFM_TD),
        'view_item' => __('Publikation ansehen', PFM_TD),
        'search_items' => __('Publikationen suchen', PFM_TD),
        'not_found' => __('Keine Publikationen gefunden', PFM_TD),
        'not_found_in_trash' => __('Keine Publikationen im Papierkorb', PFM_TD),
        'menu_name' => __('Publikationen', PFM_TD),
    );

    $args = array(
        'labels' => $labels,
        'public' => true,
        'has_archive' => true,
        'publicly_queryable' => true,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => true,
        'show_in_rest' => true,
        'menu_icon' => 'dashicons-media-document',
        'supports' => array('title', 'editor', 'author', 'thumbnail', 'excerpt', 'comments', 'custom-fields'),
        'capability_type' => 'post',
        'rewrite' => array('slug' => 'publikation'),
        'menu_position' => 5,
    );

    register_post_type('publikation', $args);
}

/* -------------------------------------------------------------------------
 * 1) Benutzerprofil-Checkboxen (Editor in Chief / Redaktion / Reviewer)
 *    - Speichern in User-Meta
 * ------------------------------------------------------------------------- */
add_action('show_user_profile', 'pfm_show_extra_user_fields');
add_action('edit_user_profile', 'pfm_show_extra_user_fields');
function pfm_show_extra_user_fields($user) {
    if (!current_user_can('edit_users')) {
        return;
    }

    $is_editor_in_chief = get_user_meta($user->ID, 'pfm_is_editor_in_chief', true) === '1';
    $is_redaktion       = get_user_meta($user->ID, 'pfm_is_redaktion', true) === '1';
    $is_reviewer        = get_user_meta($user->ID, 'pfm_is_reviewer', true) === '1';
    ?>
    <h3><?php esc_html_e('Publikations-Workflow (Frontend Manager)', PFM_TD); ?></h3>
    <table class="form-table">
        <tr>
            <th><label for="pfm_is_editor_in_chief"><?php _e('Editor in Chief', PFM_TD); ?></label></th>
            <td>
                <input type="checkbox" name="pfm_is_editor_in_chief" id="pfm_is_editor_in_chief" value="1" <?php checked($is_editor_in_chief); ?> />
                <span class="description"><?php _e('Hat volle Rechte im Publikations-Workflow (Front-End).', PFM_TD); ?></span>
            </td>
        </tr>
        <tr>
            <th><label for="pfm_is_redaktion"><?php _e('Redaktion', PFM_TD); ?></label></th>
            <td>
                <input type="checkbox" name="pfm_is_redaktion" id="pfm_is_redaktion" value="1" <?php checked($is_redaktion); ?> />
                <span class="description"><?php _e('Redaktionsrechte (Front-End).', PFM_TD); ?></span>
            </td>
        </tr>
        <tr>
            <th><label for="pfm_is_reviewer"><?php _e('Reviewer', PFM_TD); ?></label></th>
            <td>
                <input type="checkbox" name="pfm_is_reviewer" id="pfm_is_reviewer" value="1" <?php checked($is_reviewer); ?> />
                <span class="description"><?php _e('Reviewer-Rechte (Front-End).', PFM_TD); ?></span>
            </td>
        </tr>
    </table>
    <?php
}
add_action('personal_options_update', 'pfm_save_extra_user_fields');
add_action('edit_user_profile_update', 'pfm_save_extra_user_fields');
function pfm_save_extra_user_fields($user_id) {
    if (!current_user_can('edit_users')) return;
    update_user_meta($user_id, 'pfm_is_editor_in_chief', isset($_POST['pfm_is_editor_in_chief']) ? '1' : '0');
    update_user_meta($user_id, 'pfm_is_redaktion',       isset($_POST['pfm_is_redaktion']) ? '1' : '0');
    update_user_meta($user_id, 'pfm_is_reviewer',        isset($_POST['pfm_is_reviewer']) ? '1' : '0');
}

/** Hilfsfunktionen Rollen */
function pfm_user_is_editor_in_chief($user_id = null) {
    if(!$user_id) { $user_id = get_current_user_id(); }
    return get_user_meta($user_id, 'pfm_is_editor_in_chief', true) === '1';
}
function pfm_user_is_redaktion($user_id = null) {
    if(!$user_id) { $user_id = get_current_user_id(); }
    return get_user_meta($user_id, 'pfm_is_redaktion', true) === '1';
}
function pfm_user_is_reviewer($user_id = null) {
    if(!$user_id) { $user_id = get_current_user_id(); }
    return get_user_meta($user_id, 'pfm_is_reviewer', true) === '1';
}

/** Reviewer-Liste */
function pfm_get_all_reviewers() {
    $args = array(
        'meta_key'   => 'pfm_is_reviewer',
        'meta_value' => '1',
        'number'     => 500,
        'fields'     => array('ID', 'display_name', 'user_email'),
    );
    return get_users($args);
}

/* -------------------------------------------------------------------------
 * 2) Shortcode: Liste aller Publikationen (unverändert)
 * ------------------------------------------------------------------------- */
add_shortcode('publikation_list_frontend', 'pfm_shortcode_list');
function pfm_shortcode_list($atts) {
    $a = shortcode_atts(array('count' => -1), $atts);

    $args = array(
        'post_type'      => 'publikation',
        'posts_per_page' => intval($a['count']),
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
    $query = new WP_Query($args);

    ob_start();

    if(!$query->have_posts()) {
        echo '<p>'.__('Keine Publikationen gefunden.', PFM_TD).'</p>';
    } else {
        echo '<ul class="pfm-publikation-list">';
        while($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();
            $title = get_the_title();
            echo '<li>';
            echo '<a href="'.esc_url(add_query_arg(array('pfm_id' => $post_id), get_permalink())).'">'.esc_html($title).'</a>';
            echo '</li>';
        }
        echo '</ul>';
        wp_reset_postdata();
    }

    return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * 3) Shortcode: Einzelansicht + Edit (erweitert, rückwärtskompatibel)
 * ------------------------------------------------------------------------- */
add_shortcode('publikation_edit_frontend', 'pfm_shortcode_edit_frontend');
function pfm_shortcode_edit_frontend($atts) {
    $a = shortcode_atts(array('id' => 0), $atts);

    $post_id = intval($a['id']);
    if($post_id <= 0 && isset($_GET['pfm_id'])) {
        $post_id = intval($_GET['pfm_id']);
    }

    if(!is_user_logged_in()) {
        return '<p style="color:red;">'.__('Bitte logge Dich ein, um auf diese Publikation zuzugreifen.', PFM_TD).'</p>';
    }

    $post = get_post($post_id);
    if(!$post || $post->post_type !== 'publikation') {
        return '<p style="color:red;">'.__('Publikation wurde nicht gefunden.', PFM_TD).'</p>';
    }

    $can_edit = pfm_user_is_editor_in_chief() || pfm_user_is_redaktion();

    // Metadaten (bestehende Keys weiterverwenden)
    $autoren      = get_post_meta($post_id, 'autoren', true);
    $doi          = get_post_meta($post_id, 'doi', true);
    $review_st    = get_post_meta($post_id, 'review_status', true);
    $abstract     = get_post_meta($post_id, 'pfm_abstract', true);
    $keywords     = get_post_meta($post_id, 'pfm_keywords', true);
    $license_url  = get_post_meta($post_id, 'pfm_license_url', true);
    $volume       = get_post_meta($post_id, 'pfm_volume', true);
    $issue        = get_post_meta($post_id, 'pfm_issue', true);
    $pub_year     = get_post_meta($post_id, 'pfm_pub_year', true);
    $first_page   = get_post_meta($post_id, 'pfm_first_page', true);
    $last_page    = get_post_meta($post_id, 'pfm_last_page', true);
    $article_id   = get_post_meta($post_id, 'pfm_article_number', true);
    $manuscript   = intval(get_post_meta($post_id, 'pfm_manuscript_attachment_id', true));
    $rev_round    = intval(get_post_meta($post_id, 'pfm_revision_round', true));
    if($rev_round < 0) $rev_round = 0;

    // Zugewiesene Reviewer
    $assigned_reviewers = (array) get_post_meta($post_id, 'pfm_assigned_reviewers', true);
    if (!is_array($assigned_reviewers)) { $assigned_reviewers = array(); }

    ob_start();
    echo '<div class="pfm-publikation-frontend">';
    echo '<h3>'.esc_html($post->post_title).'</h3>';

    echo '<p><strong>'.__('Autoren:', PFM_TD).'</strong> '.esc_html($autoren).'</p>';
    echo '<p><strong>DOI:</strong> '.($doi ? esc_html($doi) : '<em>'.__('(noch nicht vergeben)', PFM_TD).'</em>').'</p>';
    echo '<p><strong>'.__('Review-Status:', PFM_TD).'</strong> '.esc_html($review_st).'</p>';

    // Zusatzinfos
    if($abstract)  echo '<p><strong>'.__('Abstract:', PFM_TD).'</strong><br>'.wp_kses_post(nl2br($abstract)).'</p>';
    if($keywords)  echo '<p><strong>'.__('Keywords:', PFM_TD).'</strong> '.esc_html($keywords).'</p>';
    if($license_url) echo '<p><strong>'.__('Lizenz:', PFM_TD).'</strong> <a href="'.esc_url($license_url).'" target="_blank" rel="noopener">'.esc_html($license_url).'</a></p>';

    // Manuskript
    if($manuscript) {
        $url = wp_get_attachment_url($manuscript);
        echo '<p><strong>'.__('Manuskript:', PFM_TD).'</strong> <a href="'.esc_url($url).'" target="_blank" rel="noopener">'.basename($url).'</a></p>';
    }

    if($can_edit) {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('pfm_save_publikation', 'pfm_nonce'); ?>
            <input type="hidden" name="action" value="pfm_save_publikation">
            <input type="hidden" name="pfm_post_id" value="<?php echo esc_attr($post_id); ?>">

            <p>
                <label for="pfm_autoren"><?php _e('Autoren (frei, z.B. "Nachname, Vorname; ...")', PFM_TD); ?></label><br>
                <input type="text" id="pfm_autoren" name="pfm_autoren" value="<?php echo esc_attr($autoren); ?>" style="width:100%;">
            </p>
            <p>
                <label for="pfm_doi"><?php _e('DOI', PFM_TD); ?></label><br>
                <input type="text" id="pfm_doi" name="pfm_doi" value="<?php echo esc_attr($doi); ?>" style="width:100%;">
            </p>
            <p>
                <label for="pfm_review_status"><?php _e('Review-Status', PFM_TD); ?></label><br>
                <select id="pfm_review_status" name="pfm_review_status">
                    <option value="submitted"          <?php selected($review_st, 'submitted'); ?>><?php _e('Eingereicht', PFM_TD); ?></option>
                    <option value="under_review"       <?php selected($review_st, 'under_review'); ?>><?php _e('Im Review', PFM_TD); ?></option>
                    <option value="revision_needed"    <?php selected($review_st, 'revision_needed'); ?>><?php _e('Nachbesserung erforderlich', PFM_TD); ?></option>
                    <option value="accepted"           <?php selected($review_st, 'accepted'); ?>><?php _e('Final freigegeben', PFM_TD); ?></option>
                    <option value="rejected"           <?php selected($review_st, 'rejected'); ?>><?php _e('Abgelehnt', PFM_TD); ?></option>
                    <option value="published"          <?php selected($review_st, 'published'); ?>><?php _e('Veröffentlicht', PFM_TD); ?></option>
                </select>
            </p>

            <fieldset>
                <legend><?php _e('Artikel-Metadaten', PFM_TD); ?></legend>
                <p>
                    <label for="pfm_abstract"><?php _e('Abstract', PFM_TD); ?></label><br>
                    <textarea id="pfm_abstract" name="pfm_abstract" rows="5" style="width:100%;"><?php echo esc_textarea($abstract); ?></textarea>
                </p>
                <p>
                    <label for="pfm_keywords"><?php _e('Keywords (kommagetrennt)', PFM_TD); ?></label><br>
                    <input type="text" id="pfm_keywords" name="pfm_keywords" value="<?php echo esc_attr($keywords); ?>" style="width:100%;">
                </p>
                <div style="display:flex;gap:10px;flex-wrap:wrap">
                    <p><label><?php _e('Volume', PFM_TD); ?><br><input type="text" name="pfm_volume" value="<?php echo esc_attr($volume); ?>" style="width:120px;"></label></p>
                    <p><label><?php _e('Issue', PFM_TD); ?><br><input type="text" name="pfm_issue" value="<?php echo esc_attr($issue); ?>" style="width:120px;"></label></p>
                    <p><label><?php _e('Publikationsjahr', PFM_TD); ?><br><input type="text" name="pfm_pub_year" value="<?php echo esc_attr($pub_year); ?>" style="width:120px;"></label></p>
                    <p><label><?php _e('Erste Seite', PFM_TD); ?><br><input type="text" name="pfm_first_page" value="<?php echo esc_attr($first_page); ?>" style="width:120px;"></label></p>
                    <p><label><?php _e('Letzte Seite', PFM_TD); ?><br><input type="text" name="pfm_last_page" value="<?php echo esc_attr($last_page); ?>" style="width:120px;"></label></p>
                    <p><label><?php _e('Artikelnummer/ID', PFM_TD); ?><br><input type="text" name="pfm_article_number" value="<?php echo esc_attr($article_id); ?>" style="width:160px;"></label></p>
                </div>
                <p>
                    <label for="pfm_license_url"><?php _e('Lizenz-URL (z.B. CC BY 4.0)', PFM_TD); ?></label><br>
                    <input type="url" id="pfm_license_url" name="pfm_license_url" value="<?php echo esc_attr($license_url); ?>" style="width:100%;">
                </p>
            </fieldset>

            <p><input type="submit" class="button button-primary" value="<?php esc_attr_e('Speichern', PFM_TD); ?>"></p>
        </form>

        <?php if(pfm_user_is_editor_in_chief()): ?>
            <hr>
            <h4><?php _e('Redaktion: Reviewer zuweisen', PFM_TD); ?></h4>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('pfm_assign_reviewers', 'pfm_assign_reviewers_nonce'); ?>
                <input type="hidden" name="action" value="pfm_assign_reviewers">
                <input type="hidden" name="pfm_post_id" value="<?php echo esc_attr($post_id); ?>">
                <p>
                    <label><?php _e('Reviewer', PFM_TD); ?></label><br>
                    <select name="pfm_reviewer_ids[]" multiple style="width:100%;max-width:600px;height:120px;">
                        <?php
                        $revUsers = pfm_get_all_reviewers();
                        foreach($revUsers as $u){
                            printf(
                                '<option value="%d" %s>%s (%s)</option>',
                                $u->ID,
                                in_array($u->ID, $assigned_reviewers, true) ? 'selected' : '',
                                esc_html($u->display_name),
                                esc_html($u->user_email)
                            );
                        }
                        ?>
                    </select>
                </p>
                <p>
                    <label><?php _e('Review-Deadline (YYYY-MM-DD)', PFM_TD); ?></label><br>
                    <input type="date" name="pfm_review_deadline" value="<?php echo esc_attr(get_post_meta($post_id,'pfm_review_deadline',true)); ?>">
                </p>
                <p><input type="submit" class="button" value="<?php esc_attr_e('Zuweisen & Benachrichtigen', PFM_TD); ?>"></p>
            </form>

            <hr>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('pfm_assign_doi', 'pfm_assign_doi_nonce'); ?>
                <input type="hidden" name="action" value="pfm_assign_doi_frontend">
                <input type="hidden" name="pfm_post_id" value="<?php echo esc_attr($post_id); ?>">
                <p><input type="submit" class="button button-secondary" value="<?php esc_attr_e('Zufällige DOI vergeben', PFM_TD); ?>"></p>
            </form>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('<?php echo esc_js(__('Crossref-Deposit jetzt ausführen?', PFM_TD)); ?>')">
                <?php wp_nonce_field('pfm_crossref_deposit', 'pfm_crossref_deposit_nonce'); ?>
                <input type="hidden" name="action" value="pfm_crossref_deposit">
                <input type="hidden" name="pfm_post_id" value="<?php echo esc_attr($post_id); ?>">
                <p><input type="submit" class="button button-secondary" value="<?php esc_attr_e('Crossref Deposit ausführen', PFM_TD); ?>"></p>
            </form>
        <?php endif; ?>

        <?php
    } else {
        echo '<p style="color:gray;">'.__('Du hast keine Berechtigung, diese Publikation zu bearbeiten.', PFM_TD).'</p>';
    }

    // Reviews anzeigen (zusammengefasst)
    echo '<hr><h4>'.__('Reviews', PFM_TD).'</h4>';
    $reviews = get_comments(array(
        'post_id' => $post_id,
        'status'  => 'approve',
        'type'    => 'pfm_review',
        'orderby' => 'comment_date_gmt',
        'order'   => 'ASC',
    ));
    if($reviews){
        echo '<ul class="pfm-reviews">';
        foreach($reviews as $rev){
            $rec = get_comment_meta($rev->comment_ID, 'pfm_recommendation', true);
            $to_author = get_comment_meta($rev->comment_ID, 'pfm_comments_to_author', true);
            $conf      = get_comment_meta($rev->comment_ID, 'pfm_confidential_to_editor', true);
            $is_conf   = ($conf !== '');
            echo '<li>';
            echo '<p><strong>'.esc_html(get_comment_author($rev)).'</strong> — '.esc_html($rec).'</p>';
            if($to_author){
                echo '<div><em>'.__('Für Autor:innen', PFM_TD).':</em><br>'.wp_kses_post(nl2br($to_author)).'</div>';
            }
            if($is_conf && (pfm_user_is_editor_in_chief() || pfm_user_is_redaktion())){
                echo '<div style="margin-top:6px;color:#a00;"><em>'.__('Vertraulich (nur Redaktion)', PFM_TD).':</em><br>'.wp_kses_post(nl2br($conf)).'</div>';
            }
            echo '</li>';
        }
        echo '</ul>';
    } else {
        echo '<p>'.__('Keine Reviews vorhanden.', PFM_TD).'</p>';
    }

    echo '</div>'; // .pfm-publikation-frontend
    return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * 4) admin_post: Speichern der Publikation (bestehend + erweitert)
 * ------------------------------------------------------------------------- */
add_action('admin_post_pfm_save_publikation', 'pfm_handle_save_publikation');
function pfm_handle_save_publikation() {
    if(!is_user_logged_in()) wp_die(__('Bitte einloggen.', PFM_TD));

    // Rechte prüfen: EIC oder Redaktion
    if(!current_user_can('edit_users')) {
        if(!pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion()) {
            wp_die(__('Keine Rechte zum Bearbeiten dieser Publikation.', PFM_TD));
        }
    }
    check_admin_referer('pfm_save_publikation', 'pfm_nonce');

    $post_id = isset($_POST['pfm_post_id']) ? intval($_POST['pfm_post_id']) : 0;
    $post = get_post($post_id);
    if(!$post || $post->post_type !== 'publikation') {
        wp_die(__('Ungültige Publikation.', PFM_TD));
    }

    // Meta-Daten aktualisieren
    $autoren   = sanitize_text_field($_POST['pfm_autoren'] ?? '');
    $doi       = sanitize_text_field($_POST['pfm_doi'] ?? '');
    $review_st = sanitize_text_field($_POST['pfm_review_status'] ?? 'submitted');

    update_post_meta($post_id, 'autoren', $autoren);
    update_post_meta($post_id, 'doi', $doi);
    update_post_meta($post_id, 'review_status', $review_st);

    // Erweiterte Felder
    update_post_meta($post_id, 'pfm_abstract', wp_kses_post($_POST['pfm_abstract'] ?? ''));
    update_post_meta($post_id, 'pfm_keywords', sanitize_text_field($_POST['pfm_keywords'] ?? ''));
    update_post_meta($post_id, 'pfm_volume', sanitize_text_field($_POST['pfm_volume'] ?? ''));
    update_post_meta($post_id, 'pfm_issue', sanitize_text_field($_POST['pfm_issue'] ?? ''));
    update_post_meta($post_id, 'pfm_pub_year', sanitize_text_field($_POST['pfm_pub_year'] ?? ''));
    update_post_meta($post_id, 'pfm_first_page', sanitize_text_field($_POST['pfm_first_page'] ?? ''));
    update_post_meta($post_id, 'pfm_last_page', sanitize_text_field($_POST['pfm_last_page'] ?? ''));
    update_post_meta($post_id, 'pfm_article_number', sanitize_text_field($_POST['pfm_article_number'] ?? ''));
    update_post_meta($post_id, 'pfm_license_url', esc_url_raw($_POST['pfm_license_url'] ?? ''));

    $redirect = add_query_arg(array('pfm_id' => $post_id, 'updated' => 'true'), get_permalink($post_id));
    wp_safe_redirect($redirect);
    exit;
}

/* -------------------------------------------------------------------------
 * 5) admin_post: DOI zuweisen (nur EIC)
 * ------------------------------------------------------------------------- */
add_action('admin_post_pfm_assign_doi_frontend', 'pfm_handle_assign_doi_frontend');
function pfm_handle_assign_doi_frontend() {
    if(!is_user_logged_in() || !pfm_user_is_editor_in_chief()) {
        wp_die(__('Keine Berechtigung.', PFM_TD));
    }
    check_admin_referer('pfm_assign_doi', 'pfm_assign_doi_nonce');

    $post_id = isset($_POST['pfm_post_id']) ? intval($_POST['pfm_post_id']) : 0;
    $post = get_post($post_id);
    if(!$post || $post->post_type !== 'publikation') {
        wp_die(__('Ungültige Publikation.', PFM_TD));
    }

    $settings = pfm_get_settings();
    $prefix = !empty($settings['doi_prefix']) ? $settings['doi_prefix'] : '10.1234';
    // Opaque Suffix: 8 Zeichen alphanumerisch (keine Metadaten!)
    $suffix = strtolower(wp_generate_password(8, false, false));
    $doi = $prefix . '/' . $suffix;

    update_post_meta($post_id, 'doi', $doi);

    $redirect = add_query_arg(array('pfm_id' => $post_id, 'doi_assigned' => 'true'), get_permalink($post_id));
    wp_safe_redirect($redirect);
    exit;
}

/* =========================================================================
 * NEU: Einreichung, Dashboard, Review, Revisionsrunden, Crossref-Einstellungen
 * ========================================================================= */

/** Optionen/Einstellungen registrieren */
function pfm_get_settings() {
    $defaults = array(
        'doi_prefix'         => '',
        'crossref_user'      => '',
        'crossref_password'  => '',
        'depositor_name'     => get_bloginfo('name'),
        'depositor_email'    => get_bloginfo('admin_email'),
        'registrant'         => get_bloginfo('name'),
        'publisher'          => get_bloginfo('name'),
        'journal_title'      => get_bloginfo('name'),
        'issn_print'         => '',
        'issn_electronic'    => '',
        'license_url_default'=> 'https://creativecommons.org/licenses/by/4.0/',
        'use_sandbox'        => '1',
        'use_v2_sync'        => '0',
        'notify_endpoint'    => '',
    );
    $opt = get_option('pfm_crossref_settings', array());
    return wp_parse_args($opt, $defaults);
}

add_action('admin_menu', function () {
    add_options_page(
        __('Publikation Frontend Manager', PFM_TD),
        __('Publikation Frontend Manager', PFM_TD),
        'manage_options',
        'pfm-settings',
        'pfm_render_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('pfm_settings_group', 'pfm_crossref_settings', 'pfm_sanitize_settings');

    add_settings_section('pfm_crossref_main', __('Crossref & Journal', PFM_TD), '__return_false', 'pfm-settings');

    $fields = array(
        'doi_prefix'        => __('DOI-Prefix (z. B. 10.xxxx)', PFM_TD),
        'crossref_user'     => __('Crossref Benutzername (login_id)', PFM_TD),
        'crossref_password' => __('Crossref Passwort (login_passwd)', PFM_TD),
        'depositor_name'    => __('Depositor Name (im XML)', PFM_TD),
        'depositor_email'   => __('Depositor E-Mail (Empfang Logs)', PFM_TD),
        'registrant'        => __('Registrant (Verantwortliche Organisation)', PFM_TD),
        'publisher'         => __('Publisher', PFM_TD),
        'journal_title'     => __('Journal-Titel (full_title)', PFM_TD),
        'issn_print'        => __('ISSN (Print)', PFM_TD),
        'issn_electronic'   => __('ISSN (Online)', PFM_TD),
        'license_url_default'=>__('Standard Lizenz-URL (z. B. CC BY 4.0)', PFM_TD),
        'use_sandbox'       => __('Crossref Sandbox verwenden (Test)', PFM_TD),
        'use_v2_sync'       => __('(Optional) Sync v2 API verwenden (falls freigeschaltet)', PFM_TD),
        'notify_endpoint'   => __('(Optional) Callback Endpunkt-Name', PFM_TD),
    );

    foreach ($fields as $key => $label) {
        add_settings_field("pfm_{$key}", $label, 'pfm_render_field', 'pfm-settings', 'pfm_crossref_main', array('key'=>$key));
    }
});
function pfm_sanitize_settings($input) {
    $out = array();
    $map_text = array('doi_prefix','crossref_user','depositor_name','depositor_email','registrant','publisher','journal_title','issn_print','issn_electronic','license_url_default','notify_endpoint');
    foreach($map_text as $k){ $out[$k] = sanitize_text_field($input[$k] ?? ''); }
    $out['crossref_password'] = $input['crossref_password'] ?? ''; // bewusst nicht gefiltert (Passwort kann Sonderzeichen enthalten), aber esc_attr bei Ausgabe!
    $out['use_sandbox'] = !empty($input['use_sandbox']) ? '1' : '0';
    $out['use_v2_sync'] = !empty($input['use_v2_sync']) ? '1' : '0';
    return $out;
}
function pfm_render_field($args) {
    $key = $args['key'];
    $opt = pfm_get_settings();
    $val = $opt[$key] ?? '';
    if(in_array($key, array('use_sandbox','use_v2_sync'), true)) {
        echo '<label><input type="checkbox" name="pfm_crossref_settings['.esc_attr($key).']" value="1" '.checked($val,'1',false).'> '.__('Aktivieren', PFM_TD).'</label>';
        return;
    }
    $type = ($key==='crossref_password') ? 'password' : 'text';
    printf('<input type="%s" name="pfm_crossref_settings[%s]" value="%s" class="regular-text">', esc_attr($type), esc_attr($key), esc_attr($val));
}
function pfm_render_settings_page() {
    ?>
    <div class="wrap">
        <h1><?php _e('Publikation Frontend Manager – Einstellungen', PFM_TD); ?></h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('pfm_settings_group');
            do_settings_sections('pfm-settings');
            submit_button();
            ?>
        </form>
        <p style="max-width:900px;">
            <?php _e('Hinweis: Für Crossref-Deposits benötigen Sie gültige Mitglieds-Zugangsdaten. Der Sandbox-Schalter nutzt test.crossref.org; für Live-Registrierungen deaktivieren.', PFM_TD); ?>
        </p>
    </div>
    <?php
}

/* -------------------------------------------------------------------------
 * Shortcode: Einreichung (Autoren laden Manuskript hoch)
 * ------------------------------------------------------------------------- */
add_shortcode('publikation_submit', 'pfm_shortcode_submit');
function pfm_shortcode_submit() {
    if(!is_user_logged_in()){
        return '<p>'.__('Bitte einloggen, um eine Einreichung zu erstellen.', PFM_TD).'</p>';
    }
    ob_start();
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
        <?php wp_nonce_field('pfm_submit', 'pfm_submit_nonce'); ?>
        <input type="hidden" name="action" value="pfm_submit">
        <p><label><?php _e('Titel', PFM_TD); ?><br><input type="text" name="pfm_title" required style="width:100%"></label></p>
        <p><label><?php _e('Abstract', PFM_TD); ?><br><textarea name="pfm_abstract" rows="6" style="width:100%"></textarea></label></p>
        <p><label><?php _e('Keywords (kommagetrennt)', PFM_TD); ?><br><input type="text" name="pfm_keywords" style="width:100%"></label></p>
        <p><label><?php _e('Autor:innen (frei; z. B. "Nachname, Vorname [ORCID]; ...")', PFM_TD); ?><br><input type="text" name="pfm_authors_string" style="width:100%"></label></p>
        <p><label><?php _e('Manuskript (PDF)', PFM_TD); ?><br><input type="file" name="pfm_manuscript" accept="application/pdf" required></label></p>
        <p><label><?php _e('Zusätzliche Dateien (optional, mehrere)', PFM_TD); ?><br><input type="file" name="pfm_supp[]" multiple></label></p>
        <p style="display:flex;gap:10px;flex-wrap:wrap">
            <label><?php _e('Volume', PFM_TD); ?><br><input type="text" name="pfm_volume" style="width:140px"></label>
            <label><?php _e('Issue', PFM_TD); ?><br><input type="text" name="pfm_issue" style="width:140px"></label>
            <label><?php _e('Publikationsjahr', PFM_TD); ?><br><input type="text" name="pfm_pub_year" style="width:140px"></label>
        </p>
        <p><label><?php _e('Lizenz-URL (z. B. CC BY 4.0)', PFM_TD); ?><br><input type="url" name="pfm_license_url" placeholder="https://creativecommons.org/licenses/by/4.0/"></label></p>
        <p><button type="submit" class="button button-primary"><?php _e('Einreichen', PFM_TD); ?></button></p>
    </form>
    <?php
    return ob_get_clean();
}

add_action('admin_post_pfm_submit', 'pfm_handle_submit');
function pfm_handle_submit() {
    if(!is_user_logged_in()) wp_die(__('Bitte einloggen.', PFM_TD));
    check_admin_referer('pfm_submit', 'pfm_submit_nonce');

    $title       = sanitize_text_field($_POST['pfm_title'] ?? '');
    if(!$title) wp_die(__('Titel fehlt.', PFM_TD));

    $abstract    = wp_kses_post($_POST['pfm_abstract'] ?? '');
    $keywords    = sanitize_text_field($_POST['pfm_keywords'] ?? '');
    $authors_str = sanitize_text_field($_POST['pfm_authors_string'] ?? '');
    $license     = esc_url_raw($_POST['pfm_license_url'] ?? '');
    $volume      = sanitize_text_field($_POST['pfm_volume'] ?? '');
    $issue       = sanitize_text_field($_POST['pfm_issue'] ?? '');
    $pub_year    = sanitize_text_field($_POST['pfm_pub_year'] ?? '');

    // Beitrag anlegen
    $post_id = wp_insert_post(array(
        'post_type'   => 'publikation',
        'post_title'  => $title,
        'post_content'=> '',
        'post_status' => 'draft',
        'post_author' => get_current_user_id(),
    ), true);
    if(is_wp_error($post_id)) wp_die($post_id->get_error_message());

    // Metadaten
    update_post_meta($post_id, 'pfm_abstract', $abstract);
    update_post_meta($post_id, 'pfm_keywords', $keywords);
    update_post_meta($post_id, 'autoren', $authors_str);
    update_post_meta($post_id, 'pfm_license_url', $license);
    update_post_meta($post_id, 'pfm_volume', $volume);
    update_post_meta($post_id, 'pfm_issue', $issue);
    update_post_meta($post_id, 'pfm_pub_year', $pub_year);
    update_post_meta($post_id, 'review_status', 'submitted');
    update_post_meta($post_id, 'pfm_revision_round', 0);

    // Uploads
    if(!empty($_FILES['pfm_manuscript']['name'])) {
        $manuscript_id = pfm_handle_upload($_FILES['pfm_manuscript'], $post_id, true);
        if($manuscript_id) update_post_meta($post_id, 'pfm_manuscript_attachment_id', $manuscript_id);
    }
    if(!empty($_FILES['pfm_supp']['name'][0])) {
        $ids = array();
        foreach($_FILES['pfm_supp']['name'] as $i => $name){
            $file = array(
                'name'     => $_FILES['pfm_supp']['name'][$i],
                'type'     => $_FILES['pfm_supp']['type'][$i],
                'tmp_name' => $_FILES['pfm_supp']['tmp_name'][$i],
                'error'    => $_FILES['pfm_supp']['error'][$i],
                'size'     => $_FILES['pfm_supp']['size'][$i],
            );
            $aid = pfm_handle_upload($file, $post_id, false);
            if($aid) $ids[] = $aid;
        }
        if($ids) update_post_meta($post_id, 'pfm_supplementary_ids', $ids);
    }

    // Info an Redaktion
    pfm_notify_editors(__('Neue Einreichung', PFM_TD), sprintf(__('Neue Einreichung: "%s" (#%d)', PFM_TD), $title, $post_id));

    $redirect = add_query_arg(array('pfm_id' => $post_id, 'submitted' => 'true'), get_permalink($post_id));
    wp_safe_redirect($redirect);
    exit;
}

function pfm_handle_upload($file, $post_id, $pdf_only = false) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $overrides = array('test_form' => false);
    if($pdf_only){
        $overrides['mimes'] = array('pdf' => 'application/pdf');
    }
    $movefile = wp_handle_upload($file, $overrides);
    if($movefile && !isset($movefile['error'])) {
        $attachment = array(
            'guid'           => $movefile['url'],
            'post_mime_type' => $movefile['type'],
            'post_title'     => sanitize_file_name(basename($movefile['file'])),
            'post_content'   => '',
            'post_status'    => 'inherit'
        );
        $attach_id = wp_insert_attachment($attachment, $movefile['file'], $post_id);
        if(!is_wp_error($attach_id)){
            $attach_data = wp_generate_attachment_metadata($attach_id, $movefile['file']);
            wp_update_attachment_metadata($attach_id, $attach_data);
            return $attach_id;
        }
    }
    return 0;
}

/* -------------------------------------------------------------------------
 * Shortcode: Dashboard (Enhanced with AJAX filtering and source verification)
 * ------------------------------------------------------------------------- */
add_shortcode('publikation_dashboard', 'pfm_shortcode_dashboard');
function pfm_shortcode_dashboard() {
    if(!is_user_logged_in()) return '<p>'.__('Bitte einloggen.', PFM_TD).'</p>';

    // Use the enhanced dashboard from PFM_Dashboard class
    return PFM_Dashboard::render_enhanced_dashboard();
}

/* -------------------------------------------------------------------------
 * Shortcode: Dashboard (Legacy version - simple list)
 * Use [publikation_dashboard_simple] for the old simple table view
 * ------------------------------------------------------------------------- */
add_shortcode('publikation_dashboard_simple', 'pfm_shortcode_dashboard_simple');
function pfm_shortcode_dashboard_simple() {
    if(!is_user_logged_in()) return '<p>'.__('Bitte einloggen.', PFM_TD).'</p>';

    $uid = get_current_user_id();
    $is_eic = pfm_user_is_editor_in_chief();
    $is_ed  = pfm_user_is_redaktion();
    $is_rev = pfm_user_is_reviewer();

    $args_owner = array(
        'post_type'      => 'publikation',
        'posts_per_page' => 50,
        'author'         => $uid,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
    $args_all = array(
        'post_type'      => 'publikation',
        'posts_per_page' => 50,
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    ob_start();
    echo '<div class="pfm-dashboard">';

    // Autor: eigene Einreichungen
    if(!$is_eic && !$is_ed && !$is_rev){
        $q = new WP_Query($args_owner);
        echo '<h3>'.__('Meine Einreichungen', PFM_TD).'</h3>';
        pfm_render_post_list($q);
    } else {
        // Redaktion: alle
        if($is_eic || $is_ed){
            $q = new WP_Query($args_all);
            echo '<h3>'.__('Alle Einreichungen', PFM_TD).'</h3>';
            pfm_render_post_list($q);
        }
        // Reviewer: zugewiesene
        if($is_rev){
            echo '<h3>'.__('Mir zugewiesene Reviews', PFM_TD).'</h3>';
            $ids = pfm_get_assigned_posts_for_reviewer($uid);
            if($ids){
                $q = new WP_Query(array(
                    'post_type' => 'publikation',
                    'post__in'  => $ids,
                    'posts_per_page' => 50
                ));
                pfm_render_post_list($q);
            } else {
                echo '<p>'.__('Keine zugewiesenen Einreichungen.', PFM_TD).'</p>';
            }
        }
    }

    echo '</div>';
    return ob_get_clean();
}
function pfm_render_post_list($query) {
    if(!$query->have_posts()){
        echo '<p>'.__('Keine Einträge gefunden.', PFM_TD).'</p>';
        return;
    }
    echo '<table class="widefat striped"><thead><tr>';
    echo '<th>'.__('Titel', PFM_TD).'</th>';
    echo '<th>'.__('Status', PFM_TD).'</th>';
    echo '<th>'.__('DOI', PFM_TD).'</th>';
    echo '<th>'.__('Aktion', PFM_TD).'</th>';
    echo '</tr></thead><tbody>';
    while($query->have_posts()){
        $query->the_post();
        $pid = get_the_ID();
        $st  = get_post_meta($pid, 'review_status', true);
        $doi = get_post_meta($pid, 'doi', true);
        $url = add_query_arg('pfm_id', $pid, get_permalink());
        echo '<tr>';
        echo '<td>'.esc_html(get_the_title()).'</td>';
        echo '<td>'.esc_html($st).'</td>';
        echo '<td>'.($doi ? esc_html($doi) : '&ndash;').'</td>';
        echo '<td><a class="button" href="'.esc_url($url).'">'.__('Öffnen', PFM_TD).'</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    wp_reset_postdata();
}
function pfm_get_assigned_posts_for_reviewer($user_id) {
    global $wpdb;
    $meta_key = 'pfm_assigned_reviewers';
    $q = $wpdb->prepare("
        SELECT post_id FROM {$wpdb->postmeta}
        WHERE meta_key = %s AND meta_value LIKE %s
    ", $meta_key, '%' . $wpdb->esc_like((string)$user_id) . '%');
    $rows = $wpdb->get_col($q);
    return array_map('intval', $rows);
}

/* -------------------------------------------------------------------------
 * Reviewer-Zuweisung
 * ------------------------------------------------------------------------- */
add_action('admin_post_pfm_assign_reviewers', 'pfm_handle_assign_reviewers');
function pfm_handle_assign_reviewers() {
    if(!is_user_logged_in() || (!pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion())){
        wp_die(__('Keine Berechtigung.', PFM_TD));
    }
    check_admin_referer('pfm_assign_reviewers', 'pfm_assign_reviewers_nonce');

    $post_id = intval($_POST['pfm_post_id'] ?? 0);
    $post = get_post($post_id);
    if(!$post || $post->post_type !== 'publikation') wp_die(__('Ungültig.', PFM_TD));

    $ids = array_map('intval', $_POST['pfm_reviewer_ids'] ?? array());
    update_post_meta($post_id, 'pfm_assigned_reviewers', $ids);
    $deadline = sanitize_text_field($_POST['pfm_review_deadline'] ?? '');
    update_post_meta($post_id, 'pfm_review_deadline', $deadline);

    // Status auf "under_review"
    update_post_meta($post_id, 'review_status', 'under_review');

    // Benachrichtigung an Reviewer
    if($ids){
        foreach($ids as $rid){
            $u = get_user_by('id', $rid);
            if($u){
                $subj = sprintf(__('Review-Zuweisung: %s', PFM_TD), get_the_title($post_id));
                $msg  = sprintf(__('Sie wurden als Reviewer zugewiesen: %s', PFM_TD), get_permalink($post_id));
                wp_mail($u->user_email, $subj, $msg);
            }
        }
    }

    $redirect = add_query_arg(array('pfm_id' => $post_id, 'assigned' => 'true'), get_permalink($post_id));
    wp_safe_redirect($redirect); exit;
}

/* -------------------------------------------------------------------------
 * Shortcode: Reviewmaske (für zugewiesene Reviewer)
 * ------------------------------------------------------------------------- */
add_shortcode('publikation_review', 'pfm_shortcode_review');
function pfm_shortcode_review($atts){
    $a = shortcode_atts(array('id'=>0), $atts);
    $post_id = intval($a['id'] ?: ($_GET['pfm_id'] ?? 0));
    if(!$post_id) return '<p>'.__('Ungültige ID.', PFM_TD).'</p>';
    if(!is_user_logged_in()) return '<p>'.__('Bitte einloggen.', PFM_TD).'</p>';

    $uid = get_current_user_id();
    $assigned = (array) get_post_meta($post_id, 'pfm_assigned_reviewers', true);
    if(!in_array($uid, $assigned, true) && !pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion()){
        return '<p>'.__('Keine Berechtigung.', PFM_TD).'</p>';
    }

    ob_start();
    ?>
    <h3><?php echo esc_html(get_the_title($post_id)); ?></h3>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
        <?php wp_nonce_field('pfm_submit_review', 'pfm_submit_review_nonce'); ?>
        <input type="hidden" name="action" value="pfm_submit_review">
        <input type="hidden" name="pfm_post_id" value="<?php echo esc_attr($post_id); ?>">
        <p>
            <label><?php _e('Empfehlung', PFM_TD); ?></label><br>
            <select name="pfm_recommendation" required>
                <option value="accept"><?php _e('Accept', PFM_TD); ?></option>
                <option value="minor"><?php _e('Minor Revision', PFM_TD); ?></option>
                <option value="major"><?php _e('Major Revision', PFM_TD); ?></option>
                <option value="reject"><?php _e('Reject', PFM_TD); ?></option>
            </select>
        </p>
        <p>
            <label><?php _e('Kommentare an Autor:innen', PFM_TD); ?></label><br>
            <textarea name="pfm_comments_to_author" rows="6" style="width:100%"></textarea>
        </p>
        <p>
            <label><?php _e('Vertrauliche Kommentare an Redaktion', PFM_TD); ?></label><br>
            <textarea name="pfm_confidential_to_editor" rows="6" style="width:100%"></textarea>
        </p>
        <p><label><?php _e('Datei anhängen (optional)', PFM_TD); ?><br><input type="file" name="pfm_review_file"></label></p>
        <p><button type="submit" class="button button-primary"><?php _e('Review absenden', PFM_TD); ?></button></p>
    </form>
    <?php
    return ob_get_clean();
}
add_action('admin_post_pfm_submit_review', 'pfm_handle_submit_review');
function pfm_handle_submit_review() {
    if(!is_user_logged_in()) wp_die(__('Bitte einloggen.', PFM_TD));
    check_admin_referer('pfm_submit_review', 'pfm_submit_review_nonce');

    $post_id = intval($_POST['pfm_post_id'] ?? 0);
    $post = get_post($post_id);
    if(!$post || $post->post_type!=='publikation') wp_die(__('Ungültig.', PFM_TD));

    $uid  = get_current_user_id();
    $assigned = (array) get_post_meta($post_id, 'pfm_assigned_reviewers', true);
    if(!in_array($uid, $assigned, true) && !pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion()){
        wp_die(__('Keine Berechtigung.', PFM_TD));
    }

    $rec = sanitize_text_field($_POST['pfm_recommendation'] ?? '');
    $cta = wp_kses_post($_POST['pfm_comments_to_author'] ?? '');
    $cte = wp_kses_post($_POST['pfm_confidential_to_editor'] ?? '');

    $comment_id = wp_insert_comment(array(
        'comment_post_ID' => $post_id,
        'comment_author'  => wp_get_current_user()->display_name,
        'comment_author_email' => wp_get_current_user()->user_email,
        'comment_content' => '(Review)',
        'user_id'         => $uid,
        'comment_approved'=> 1,
        'comment_type'    => 'pfm_review',
    ));
    if($comment_id){
        update_comment_meta($comment_id, 'pfm_recommendation', $rec);
        update_comment_meta($comment_id, 'pfm_comments_to_author', $cta);
        if($cte!=='') update_comment_meta($comment_id, 'pfm_confidential_to_editor', $cte);

        // optional Datei
        if(!empty($_FILES['pfm_review_file']['name'])){
            $aid = pfm_handle_upload($_FILES['pfm_review_file'], $post_id, false);
            if($aid) update_comment_meta($comment_id, 'pfm_review_attachment_id', $aid);
        }
        // Info an Redaktion
        pfm_notify_editors(__('Neues Review eingegangen', PFM_TD), sprintf(__('Ein Review zu "%s" wurde abgegeben.', PFM_TD), get_the_title($post_id)));
    }

    $redirect = add_query_arg(array('pfm_id'=>$post_id,'reviewed'=>'true'), get_permalink($post_id));
    wp_safe_redirect($redirect); exit;
}

/* -------------------------------------------------------------------------
 * Revisionsrunde (Autoren laden überarbeitete Datei hoch)
 * ------------------------------------------------------------------------- */
add_shortcode('publikation_revision_upload', function($atts){
    $a = shortcode_atts(array('id'=>0), $atts);
    $post_id = intval($a['id'] ?: ($_GET['pfm_id'] ?? 0));
    if(!$post_id) return '<p>'.__('Ungültige ID.', PFM_TD).'</p>';
    if(!is_user_logged_in()) return '<p>'.__('Bitte einloggen.', PFM_TD).'</p>';
    $post = get_post($post_id);
    if(!$post || $post->post_type!=='publikation') return '<p>'.__('Ungültig.', PFM_TD).'</p>';
    if(intval($post->post_author)!==get_current_user_id()) return '<p>'.__('Nur Autor:in darf Revision hochladen.', PFM_TD).'</p>';

    ob_start(); ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
        <?php wp_nonce_field('pfm_submit_revision', 'pfm_submit_revision_nonce'); ?>
        <input type="hidden" name="action" value="pfm_submit_revision">
        <input type="hidden" name="pfm_post_id" value="<?php echo esc_attr($post_id); ?>">
        <p><label><?php _e('Überarbeitete Version (PDF)', PFM_TD); ?><br><input type="file" name="pfm_revision_pdf" accept="application/pdf" required></label></p>
        <p><button type="submit" class="button button-primary"><?php _e('Revision hochladen', PFM_TD); ?></button></p>
    </form>
    <?php return ob_get_clean();
});
add_action('admin_post_pfm_submit_revision', function(){
    if(!is_user_logged_in()) wp_die(__('Bitte einloggen.', PFM_TD));
    check_admin_referer('pfm_submit_revision','pfm_submit_revision_nonce');

    $post_id = intval($_POST['pfm_post_id'] ?? 0);
    $post = get_post($post_id);
    if(!$post || $post->post_type!=='publikation') wp_die(__('Ungültig.', PFM_TD));
    if(intval($post->post_author)!==get_current_user_id()) wp_die(__('Keine Berechtigung.', PFM_TD));

    if(empty($_FILES['pfm_revision_pdf']['name'])) wp_die(__('Datei fehlt.', PFM_TD));
    $aid = pfm_handle_upload($_FILES['pfm_revision_pdf'], $post_id, true);
    if($aid){
        $hist = (array) get_post_meta($post_id, 'pfm_revision_history', true);
        $hist[] = array('time'=>current_time('mysql'),'attachment_id'=>$aid,'user'=>get_current_user_id());
        update_post_meta($post_id, 'pfm_revision_history', $hist);

        // Runde +1
        $r = intval(get_post_meta($post_id, 'pfm_revision_round', true));
        update_post_meta($post_id, 'pfm_revision_round', $r+1);
        // Status wieder auf "under_review"
        update_post_meta($post_id, 'review_status', 'under_review');

        pfm_notify_editors(__('Revision hochgeladen', PFM_TD), sprintf(__('Eine neue Revision zu "%s" wurde hochgeladen.', PFM_TD), get_the_title($post_id)));
    }

    $redirect = add_query_arg(array('pfm_id'=>$post_id,'rev'=>'1'), get_permalink($post_id));
    wp_safe_redirect($redirect); exit;
});

/* -------------------------------------------------------------------------
 * E-Mail-Helfer
 * ------------------------------------------------------------------------- */
function pfm_notify_editors($subject, $message) {
    $users = get_users(array(
        'meta_key'   => 'pfm_is_redaktion',
        'meta_value' => '1',
        'fields'     => array('user_email')
    ));
    $emails = array_map(function($u){ return $u->user_email; }, $users);
    // EIC zusätzlich
    $eics = get_users(array(
        'meta_key'   => 'pfm_is_editor_in_chief',
        'meta_value' => '1',
        'fields'     => array('user_email')
    ));
    $emails = array_merge($emails, array_map(function($u){return $u->user_email;}, $eics));
    $emails = array_unique($emails);

    if($emails){
        foreach($emails as $to){
            wp_mail($to, $subject, $message);
        }
    }
}

/* -------------------------------------------------------------------------
 * Crossref: XML Builder & Deposit
 * ------------------------------------------------------------------------- */

/**
 * Baut ein Crossref-XML für journal_article (Schema 5.3.1/5.4.0 kompatibel)
 * Minimalfelder gemäß Doku: Journal-Titel/ISSN, Artikel-Titel, Publication Year, doi_data (DOI + resource)
 */
function pfm_build_crossref_xml($post_id) {
    $s = pfm_get_settings();
    $post = get_post($post_id);
    $title = get_the_title($post_id);
    $permalink = get_permalink($post_id);

    $doi  = get_post_meta($post_id, 'doi', true);
    $year = get_post_meta($post_id, 'pfm_pub_year', true);
    if(!$year) $year = date('Y');

    $journal_title = $s['journal_title'];
    $issn_p = trim($s['issn_print']);
    $issn_e = trim($s['issn_electronic']);

    $volume = get_post_meta($post_id, 'pfm_volume', true);
    $issue  = get_post_meta($post_id, 'pfm_issue', true);

    $first_page = get_post_meta($post_id, 'pfm_first_page', true);
    $last_page  = get_post_meta($post_id, 'pfm_last_page', true);
    $article_id = get_post_meta($post_id, 'pfm_article_number', true);

    $abstract = get_post_meta($post_id, 'pfm_abstract', true);
    $license  = get_post_meta($post_id, 'pfm_license_url', true);
    if(!$license && $s['license_url_default']) $license = $s['license_url_default'];

    $authors_str = get_post_meta($post_id, 'autoren', true); // Fallback freier String
    // Einfache Zerlegung in Personen (Semikolon-getrennt)
    $authors = array();
    if($authors_str){
        $parts = array_filter(array_map('trim', explode(';', $authors_str)));
        foreach($parts as $p){
            // Optionales [ORCID] am Ende
            $orcid = '';
            if(preg_match('/\[(.*?)\]$/', $p, $m)){
                $orcid = trim($m[1]);
                $p = trim(preg_replace('/\[(.*?)\]$/', '', $p));
            }
            // "Nachname, Vorname"
            $gn = ''; $sn='';
            if(strpos($p, ',') !== false){
                list($sn,$gn) = array_map('trim', explode(',', $p, 2));
            } else {
                $sn = $p;
            }
            $authors[] = array('given'=>$gn,'family'=>$sn,'orcid'=>$orcid);
        }
    }

    // Kopf
    $batch_id  = 'pfm_' . $post_id . '_' . time();
    $timestamp = date('YmdHis');

    // XML zusammenbauen
    $xml  = '<?xml version="1.0" encoding="UTF-8"?>'."\n";
    // Schema-Version 5.3.1 (stabil); 5.4.0 ebenfalls verfügbar
    $xml .= '<doi_batch version="5.3.1" xmlns="http://www.crossref.org/schema/5.3.1" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="http://www.crossref.org/schema/5.3.1 http://www.crossref.org/schemas/crossref5.3.1.xsd">'."\n";
    $xml .= '  <head>'."\n";
    $xml .= '    <doi_batch_id>'.esc_html($batch_id).'</doi_batch_id>'."\n";
    $xml .= '    <timestamp>'.esc_html($timestamp).'</timestamp>'."\n";
    $xml .= '    <depositor>'."\n";
    $xml .= '      <depositor_name>'.esc_html($s['depositor_name']).'</depositor_name>'."\n";
    $xml .= '      <email_address>'.esc_html($s['depositor_email']).'</email_address>'."\n";
    $xml .= '    </depositor>'."\n";
    $xml .= '    <registrant>'.esc_html($s['registrant']).'</registrant>'."\n";
    $xml .= '  </head>'."\n";
    $xml .= '  <body>'."\n";
    $xml .= '    <journal>'."\n";
    $xml .= '      <journal_metadata language="en">'."\n";
    $xml .= '        <full_title>'.esc_html($journal_title).'</full_title>'."\n";
    if($issn_p) $xml .= '        <issn media_type="print">'.esc_html($issn_p).'</issn>'."\n";
    if($issn_e) $xml .= '        <issn media_type="electronic">'.esc_html($issn_e).'</issn>'."\n";
    $xml .= '      </journal_metadata>'."\n";

    if($volume || $issue || $year){
        $xml .= '      <journal_issue>'."\n";
        if($year){
            $xml .= '        <publication_date media_type="online"><year>'.esc_html($year).'</year></publication_date>'."\n";
        }
        if($volume) $xml .= '        <journal_volume><volume>'.esc_html($volume).'</volume></journal_volume>'."\n";
        if($issue)  $xml .= '        <issue>'.esc_html($issue).'</issue>'."\n";
        $xml .= '      </journal_issue>'."\n";
    }

    $xml .= '      <journal_article publication_type="full_text">'."\n";
    $xml .= '        <titles><title>'.esc_html($title).'</title></titles>'."\n";

    if($authors){
        $xml .= '        <contributors>'."\n";
        foreach($authors as $idx => $a){
            $seq = ($idx===0) ? 'first' : 'additional';
            $xml .= '          <person_name sequence="'.$seq.'" contributor_role="author">'."\n";
            if($a['given'])  $xml .= '            <given_name>'.esc_html($a['given']).'</given_name>'."\n";
            if($a['family']) $xml .= '            <surname>'.esc_html($a['family']).'</surname>'."\n";
            if($a['orcid']){
                $orcid = preg_replace('~^https?://orcid.org/~','',$a['orcid']);
                $xml .= '            <ORCID authenticated="false">https://orcid.org/'.esc_html($orcid).'</ORCID>'."\n";
            }
            $xml .= '          </person_name>'."\n";
        }
        $xml .= '        </contributors>'."\n";
    }

    if($year){
        $xml .= '        <publication_date media_type="online"><year>'.esc_html($year).'</year></publication_date>'."\n";
    }

    if($first_page || $last_page){
        $xml .= '        <pages>'."\n";
        if($first_page) $xml .= '          <first_page>'.esc_html($first_page).'</first_page>'."\n";
        if($last_page)  $xml .= '          <last_page>'.esc_html($last_page).'</last_page>'."\n";
        $xml .= '        </pages>'."\n";
    } elseif($article_id){
        $xml .= '        <publisher_item><item_number item_number_type="article-number">'.esc_html($article_id).'</item_number></publisher_item>'."\n";
    }

    if($license){
        $xml .= '        <ai:program xmlns:ai="http://www.crossref.org/AccessIndicators.xsd" name="AccessIndicators">'."\n";
        $xml .= '          <ai:license_ref>'.esc_html($license).'</ai:license_ref>'."\n";
        $xml .= '        </ai:program>'."\n";
    }

    if($abstract){
        $xml .= '        <jats:abstract xmlns:jats="http://www.ncbi.nlm.nih.gov/JATS1">' . esc_html($abstract) . '</jats:abstract>'."\n";
    }

    $xml .= '        <doi_data>'."\n";
    $xml .= '          <doi>'.esc_html($doi).'</doi>'."\n";
    $xml .= '          <resource>'.esc_url($permalink).'</resource>'."\n";
    $xml .= '        </doi_data>'."\n";

    $xml .= '      </journal_article>'."\n";
    $xml .= '    </journal>'."\n";
    $xml .= '  </body>'."\n";
    $xml .= '</doi_batch>'."\n";

    return array('xml'=>$xml, 'batch_id'=>$batch_id);
}

/**
 * Crossref Deposit via HTTPS POST (servlet/deposit) – allgemein verfügbar
 * Optional: v2 Sync (falls freigeschaltet)
 */
add_action('admin_post_pfm_crossref_deposit', 'pfm_handle_crossref_deposit');
function pfm_handle_crossref_deposit() {
    if(!is_user_logged_in() || (!pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion())){
        wp_die(__('Keine Berechtigung.', PFM_TD));
    }
    check_admin_referer('pfm_crossref_deposit','pfm_crossref_deposit_nonce');

    $post_id = intval($_POST['pfm_post_id'] ?? 0);
    $post = get_post($post_id);
    if(!$post || $post->post_type!=='publikation') wp_die(__('Ungültig.', PFM_TD));

    $doi = get_post_meta($post_id, 'doi', true);
    if(!$doi) wp_die(__('Bitte zunächst eine DOI vergeben.', PFM_TD));

    $s = pfm_get_settings();
    $built = pfm_build_crossref_xml($post_id);
    $xml = $built['xml'];
    $batch_id = $built['batch_id'];

    $result = pfm_crossref_deposit_request($xml, $s);
    if(is_wp_error($result)) {
        wp_die(sprintf(__('Deposit fehlgeschlagen: %s', PFM_TD), $result->get_error_message()));
    }

    // Antwort speichern
    update_post_meta($post_id, 'pfm_crossref_last_request', array(
        'time' => current_time('mysql'),
        'batch_id' => $batch_id,
        'response_code' => wp_remote_retrieve_response_code($result),
        'response_body' => wp_remote_retrieve_body($result),
    ));

    // Bei Erfolg: Status ggf. auf accepted/published setzen (manuell steuerbar)
    // Hier: auf "accepted", Veröffentlichung separat
    if(get_post_meta($post_id, 'review_status', true) === 'accepted'){
        // nichts ändern
    } else {
        update_post_meta($post_id, 'review_status', 'accepted');
    }

    $redirect = add_query_arg(array('pfm_id'=>$post_id,'deposit'=>'1'), get_permalink($post_id));
    wp_safe_redirect($redirect); exit;
}

function pfm_crossref_deposit_request($xml, $settings) {
    $use_v2 = ($settings['use_v2_sync'] === '1');
    $sandbox = ($settings['use_sandbox'] === '1');

    if($use_v2){
        $url = $sandbox ? 'https://api.crossref.org/v2/deposits' : 'https://api.crossref.org/v2/deposits';
        // multipart: usr, pwd, operation=doMDUpload, mdFile=@file
        $boundary = 'pfm-'.wp_generate_password(24,false,false);
        $body  = "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"operation\"\r\n\r\ndoMDUpload\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"usr\"\r\n\r\n".$settings['crossref_user']."\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"pwd\"\r\n\r\n".$settings['crossref_password']."\r\n";
        $body .= "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"mdFile\"; filename=\"deposit.xml\"\r\n";
        $body .= "Content-Type: application/xml\r\n\r\n";
        $body .= $xml . "\r\n";
        $body .= "--$boundary--\r\n";

        $args = array(
            'method'  => 'POST',
            'timeout' => 60,
            'headers' => array(
                'Content-Type' => 'multipart/form-data; boundary='.$boundary,
                'User-Agent'   => 'PFM/'.PFM_VERSION.' (WordPress; '.home_url().')'
            ),
            'body'    => $body
        );
        return wp_remote_post($url, $args);
    } else {
        // servlet/deposit: Query-Parameter + multipart nur für fname
        $base = $sandbox ? 'https://test.crossref.org/servlet/deposit' : 'https://doi.crossref.org/servlet/deposit';
        $url = add_query_arg(array(
            'operation'    => 'doMDUpload',
            'login_id'     => rawurlencode($settings['crossref_user']),
            'login_passwd' => rawurlencode($settings['crossref_password'])
        ), $base);

        $boundary = 'pfm-'.wp_generate_password(24,false,false);
        $body  = "--$boundary\r\n";
        $body .= "Content-Disposition: form-data; name=\"fname\"; filename=\"deposit.xml\"\r\n";
        $body .= "Content-Type: application/xml\r\n\r\n";
        $body .= $xml . "\r\n";
        $body .= "--$boundary--\r\n";

        $args = array(
            'method'  => 'POST',
            'timeout' => 60,
            'headers' => array(
                'Content-Type' => 'multipart/form-data; boundary='.$boundary,
                'User-Agent'   => 'PFM/'.PFM_VERSION.' (WordPress; '.home_url().')'
            ),
            'body'    => $body
        );
        return wp_remote_post($url, $args);
    }
}

/**
 * Submission-Log abrufen (Polling) – via submissionDownload
 */
function pfm_crossref_fetch_log($args = array()) {
    $s = pfm_get_settings();
    $sandbox = ($s['use_sandbox']==='1');
    $url = $sandbox ? 'https://test.crossref.org/servlet/submissionDownload' : 'https://doi.crossref.org/servlet/submissionDownload';

    $fields = array(
        'usr'  => $s['crossref_user'],
        'pwd'  => $s['crossref_password'],
        'type' => 'result',
    );
    if(!empty($args['doi_batch_id'])) $fields['doi_batch_id'] = $args['doi_batch_id'];
    if(!empty($args['submission_id'])) $fields['submission_id'] = $args['submission_id'];

    $res = wp_remote_post($url, array(
        'timeout'=> 60,
        'body'   => $fields,
        'headers'=> array('User-Agent'=>'PFM/'.PFM_VERSION.' (WordPress; '.home_url().')')
    ));
    if(is_wp_error($res)) return $res;
    return wp_remote_retrieve_body($res);
}

/* -------------------------------------------------------------------------
 * REST Endpoint: Crossref Callback Receiver (optional)
 * Crossref sendet Header inkl. CROSSREF-RETRIEVE-URL; wir holen das Ergebnis.
 * ------------------------------------------------------------------------- */
add_action('rest_api_init', function(){
    register_rest_route('pfm/v1', '/crossref-callback', array(
        'methods'  => 'POST',
        'permission_callback' => '__return_true',
        'callback' => 'pfm_rest_crossref_callback',
    ));
});
function pfm_rest_crossref_callback(WP_REST_Request $req) {
    $headers = array_change_key_case($req->get_headers());
    $retrieve = isset($headers['crossref-retrieve-url'][0]) ? $headers['crossref-retrieve-url'][0] : '';
    $external = isset($headers['crossref-external-id'][0]) ? $headers['crossref-external-id'][0] : '';
    $internal = isset($headers['crossref-internal-id'][0]) ? $headers['crossref-internal-id'][0] : '';
    if(!$retrieve) return new WP_REST_Response(array('ok'=>false,'msg'=>'no retrieve header'), 400);

    // Abrufen (normalerweise erfordert Auth im Abruf-Link oder Basic Auth via usr/pwd)
    $s = pfm_get_settings();
    $res = wp_remote_get($retrieve, array(
        'timeout'=>60,
        'headers'=> array('User-Agent'=>'PFM/'.PFM_VERSION),
    ));
    $body = is_wp_error($res) ? $res->get_error_message() : wp_remote_retrieve_body($res);

    // Zur einfachen Auswertung speichern (ohne Zuordnung zu Post – die erfolgt manuell/durch batch_id Matching)
    $log = array(
        'time' => current_time('mysql'),
        'retrieve' => $retrieve,
        'external' => $external,
        'internal' => $internal,
        'body' => $body
    );
    // In Option anfügen
    $arr = get_option('pfm_crossref_callback_logs', array());
    $arr[] = $log;
    update_option('pfm_crossref_callback_logs', $arr);

    return array('ok'=>true);
}

/* -------------------------------------------------------------------------
 * Veröffentlichungshilfe: Status auf "published" setzen (optional)
 * ------------------------------------------------------------------------- */
add_action('admin_post_pfm_mark_published', function(){
    if(!is_user_logged_in() || (!pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion())){
        wp_die(__('Keine Berechtigung.', PFM_TD));
    }
    check_admin_referer('pfm_mark_published', 'pfm_mark_published_nonce');
    $post_id = intval($_POST['pfm_post_id'] ?? 0);
    $post = get_post($post_id);
    if(!$post || $post->post_type!=='publikation') wp_die(__('Ungültig.', PFM_TD));
    update_post_meta($post_id, 'review_status', 'published');
    // Optional: WordPress publish:
    wp_update_post(array('ID'=>$post_id,'post_status'=>'publish'));
    wp_safe_redirect(add_query_arg(array('pfm_id'=>$post_id,'pub'=>'1'), get_permalink($post_id))); exit;
});

/* =========================================================================
 * MEDICAL PUBLICATION FEATURES - Enhanced Shortcodes & Handlers
 * ========================================================================= */

/**
 * Shortcode: Medizinische Publikationsansicht mit ACF-Feldern
 * [publikation_medical_view id="123"]
 */
add_shortcode('publikation_medical_view', 'pfm_shortcode_medical_view');
function pfm_shortcode_medical_view($atts) {
    $a = shortcode_atts(array('id' => 0), $atts);

    $post_id = intval($a['id']);
    if($post_id <= 0 && isset($_GET['pfm_id'])) {
        $post_id = intval($_GET['pfm_id']);
    }

    if(!$post_id) {
        return '<p style="color:red;">'.__('Keine Publikations-ID angegeben.', PFM_TD).'</p>';
    }

    $post = get_post($post_id);
    if(!$post || $post->post_type !== 'publikation') {
        return '<p style="color:red;">'.__('Publikation nicht gefunden.', PFM_TD).'</p>';
    }

    ob_start();

    echo '<div class="pfm-medical-publication-view">';

    // Kardiotechnik Header
    echo '<div class="kardiotechnik-header">';
    echo '<h3>'.esc_html($post->post_title).'</h3>';
    $unterueberschrift = PFM_Medical_Fields::get_field_value($post_id, 'unterueberschrift');
    if($unterueberschrift) {
        echo '<p class="kardiotechnik-subtitle">'.esc_html($unterueberschrift).'</p>';
    }
    echo '</div>';

    // Workflow Timeline
    echo PFM_Workflow_Tracker::render_timeline($post_id);

    // Medical Publication Info (ACF-Felder)
    echo PFM_Medical_Fields::render_publication_info($post_id);

    // Abstracts (Deutsch & Englisch)
    echo PFM_Medical_Fields::render_abstracts($post_id);

    // PDF Downloads
    echo PFM_Medical_Fields::render_pdf_downloads($post_id);

    // File Versions & Supplementary
    if(is_user_logged_in() && (pfm_user_is_editor_in_chief() || pfm_user_is_redaktion() || $post->post_author == get_current_user_id())) {
        echo '<h4>'.__('Dateiverwaltung', PFM_TD).'</h4>';
        echo PFM_File_Manager::render_version_history($post_id);
        echo PFM_File_Manager::render_supplementary_files($post_id);
    }

    // Review-Übersicht mit medizinischen Kriterien
    if(is_user_logged_in()) {
        echo '<h3>'.__('Peer Review', PFM_TD).'</h3>';
        echo PFM_Review_Criteria::render_aggregated_scores($post_id);
        echo pfm_render_reviews_list($post_id);
    }

    // Literatur
    echo PFM_Medical_Fields::render_literature($post_id);

    // Zitierformat
    if(get_post_status($post_id) === 'publish') {
        $citation = PFM_Medical_Fields::get_citation_format($post_id);
        echo '<div class="pfm-citation">';
        echo '<h5>'.__('Zitieren Sie diesen Artikel', PFM_TD).'</h5>';
        echo '<div class="citation-text">'.esc_html($citation).'</div>';
        echo '<button class="citation-copy-btn" onclick="navigator.clipboard.writeText(\''.$citation.'\'); alert(\'Zitat kopiert!\');">'.__('Kopieren', PFM_TD).'</button>';
        echo '</div>';
    }

    // Für Redaktion: Edit Form & Decision Interface
    if(pfm_user_is_editor_in_chief() || pfm_user_is_redaktion()) {
        echo '<hr>';
        echo '<h3>'.__('Redaktionsbereich', PFM_TD).'</h3>';
        echo PFM_Medical_Fields::render_medical_edit_form($post_id);
        echo PFM_Editorial_Decision::render_decision_interface($post_id);
        echo PFM_Conflict_Interest::render_coi_status($post_id);
    }

    // Für Reviewer: COI & Review Form
    if(pfm_user_is_reviewer() && is_user_logged_in()) {
        $assigned = get_post_meta($post_id, 'pfm_assigned_reviewers', true);
        if(is_array($assigned) && in_array(get_current_user_id(), $assigned)) {
            echo '<hr>';
            echo '<h3>'.__('Review einreichen', PFM_TD).'</h3>';
            echo PFM_Conflict_Interest::render_declaration_form($post_id);
            echo pfm_render_medical_review_form($post_id);
        }
    }

    echo '</div>';

    return ob_get_clean();
}

/**
 * Medical Review Form mit medizinischen Kriterien
 */
function pfm_render_medical_review_form($post_id) {
    $user_id = get_current_user_id();

    // Prüfe ob bereits reviewed
    $existing_review = get_comments(array(
        'post_id' => $post_id,
        'user_id' => $user_id,
        'type' => 'pfm_review',
        'status' => 'approve',
        'number' => 1,
    ));

    if (!empty($existing_review)) {
        return '<div class="notice notice-info"><p>'.__('Sie haben bereits ein Review für diese Publikation eingereicht.', PFM_TD).'</p></div>';
    }

    ob_start();
    ?>
    <div class="pfm-medical-review-form">
        <div class="medical-criteria-info">
            <h5><?php _e('Medizinische Review-Kriterien', PFM_TD); ?></h5>
            <p><?php _e('Bitte bewerten Sie diese medizinische Publikation anhand der folgenden Kriterien:', PFM_TD); ?></p>
            <ul>
                <li><strong><?php _e('Klinische Relevanz', PFM_TD); ?>:</strong> <?php _e('Bedeutung für Praxis und Patientenversorgung', PFM_TD); ?></li>
                <li><strong><?php _e('Studiendesign & Methodik', PFM_TD); ?>:</strong> <?php _e('Wissenschaftliche Qualität der Studie', PFM_TD); ?></li>
                <li><strong><?php _e('Ethische Standards', PFM_TD); ?>:</strong> <?php _e('Einhaltung ethischer Richtlinien', PFM_TD); ?></li>
                <li><strong><?php _e('Datenqualität', PFM_TD); ?>:</strong> <?php _e('Vollständigkeit und statistische Auswertung', PFM_TD); ?></li>
            </ul>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field('pfm_submit_medical_review', 'pfm_medical_review_nonce'); ?>
            <input type="hidden" name="action" value="pfm_submit_medical_review">
            <input type="hidden" name="pfm_post_id" value="<?php echo esc_attr($post_id); ?>">

            <?php
            // Nutze medizinische Kriterien
            $criteria = PFM_Medical_Fields::get_medical_review_criteria();
            $scale = PFM_Review_Criteria::get_rating_scale();

            echo '<div class="pfm-review-criteria">';
            echo '<h4>' . __('Bewertungskriterien', PFM_TD) . '</h4>';

            foreach ($criteria as $key => $criterion) {
                echo '<div class="pfm-criterion">';
                echo '<label><strong>' . esc_html($criterion['label']) . '</strong></label>';
                echo '<p class="description">' . esc_html($criterion['description']) . '</p>';
                echo '<div class="pfm-rating-buttons">';

                foreach ($scale as $value => $label) {
                    echo '<label class="pfm-rating-option">';
                    echo '<input type="radio" name="pfm_scores[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" required>';
                    echo '<span class="rating-label">' . esc_html($value) . ' - ' . esc_html($label) . '</span>';
                    echo '</label>';
                }

                echo '</div></div>';
            }
            echo '</div>';
            ?>

            <h4><?php _e('Empfehlung', PFM_TD); ?></h4>
            <p>
                <select name="pfm_recommendation" required style="width:100%;max-width:400px;">
                    <option value=""><?php _e('-- Bitte wählen --', PFM_TD); ?></option>
                    <option value="accept"><?php _e('Accept - Publikation ohne Änderungen', PFM_TD); ?></option>
                    <option value="minor"><?php _e('Minor Revision - Kleinere Überarbeitungen', PFM_TD); ?></option>
                    <option value="major"><?php _e('Major Revision - Umfangreiche Überarbeitungen', PFM_TD); ?></option>
                    <option value="reject"><?php _e('Reject - Ablehnung', PFM_TD); ?></option>
                </select>
            </p>

            <p>
                <label><strong><?php _e('Kommentare für die Autoren', PFM_TD); ?></strong></label><br>
                <textarea name="pfm_comments_to_author" rows="10" style="width:100%;" required placeholder="<?php esc_attr_e('Bitte geben Sie hier Ihr detailliertes Feedback für die Autoren ein...', PFM_TD); ?>"></textarea>
            </p>

            <p>
                <label><strong><?php _e('Vertrauliche Kommentare an die Redaktion', PFM_TD); ?></strong></label><br>
                <textarea name="pfm_confidential_to_editor" rows="6" style="width:100%;" placeholder="<?php esc_attr_e('Optional: Vertrauliche Anmerkungen nur für die Redaktion', PFM_TD); ?>"></textarea>
            </p>

            <p>
                <label><?php _e('Annotiertes Manuskript anhängen (optional)', PFM_TD); ?><br>
                <input type="file" name="pfm_review_file" accept=".pdf,.doc,.docx">
                </label>
            </p>

            <p class="submit">
                <button type="submit" class="button button-primary button-large">
                    <span class="dashicons dashicons-yes"></span>
                    <?php _e('Review absenden', PFM_TD); ?>
                </button>
            </p>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Shortcode: Medizinisches Submission Form
 * [publikation_medical_submit]
 */
add_shortcode('publikation_medical_submit', 'pfm_shortcode_medical_submit');
function pfm_shortcode_medical_submit() {
    if(!is_user_logged_in()) {
        return '<p>'.__('Bitte loggen Sie sich ein, um eine Publikation einzureichen.', PFM_TD).'</p>';
    }

    return PFM_Medical_Fields::render_medical_submission_form();
}

/**
 * Handle Medical Submission
 */
add_action('admin_post_pfm_medical_submission', 'pfm_handle_medical_submission');
function pfm_handle_medical_submission() {
    if(!is_user_logged_in()) wp_die(__('Bitte einloggen.', PFM_TD));
    check_admin_referer('pfm_medical_submission', 'pfm_medical_submission_nonce');

    // Basisdaten
    $title = sanitize_text_field($_POST['publication_title']);
    $unterueberschrift = sanitize_text_field($_POST['unterueberschrift'] ?? '');
    $publikationsart = sanitize_text_field($_POST['publikationsart']);
    $kardiotechnikausgabe = sanitize_text_field($_POST['kardiotechnikausgabe'] ?? '');

    // Autoren
    $autoren = sanitize_textarea_field($_POST['autoren']);
    $hauptautorin = sanitize_text_field($_POST['hauptautorin']);

    // Abstracts
    $abstract_deutsch = sanitize_textarea_field($_POST['abstract_deutsch']);
    $keywords_deutsch = sanitize_text_field($_POST['keywords_deutsch']);
    $abstract = sanitize_textarea_field($_POST['abstract']);
    $keywords_englisch = sanitize_text_field($_POST['keywords_englisch']);

    // Literatur
    $literatur = sanitize_textarea_field($_POST['literatur'] ?? '');

    // Post erstellen
    $post_id = wp_insert_post(array(
        'post_type' => 'publikation',
        'post_title' => $title,
        'post_status' => 'draft',
        'post_author' => get_current_user_id(),
    ));

    if(is_wp_error($post_id)) {
        wp_die($post_id->get_error_message());
    }

    // ACF-Felder speichern
    PFM_Medical_Fields::update_field_value($post_id, 'unterueberschrift', $unterueberschrift);
    PFM_Medical_Fields::update_field_value($post_id, 'publikationsart', $publikationsart);
    PFM_Medical_Fields::update_field_value($post_id, 'kardiotechnikausgabe', $kardiotechnikausgabe);
    PFM_Medical_Fields::update_field_value($post_id, 'autoren', $autoren);
    PFM_Medical_Fields::update_field_value($post_id, 'hauptautorin', $hauptautorin);
    PFM_Medical_Fields::update_field_value($post_id, 'abstract-deutsch', $abstract_deutsch);
    PFM_Medical_Fields::update_field_value($post_id, 'keywords-deutsch', $keywords_deutsch);
    PFM_Medical_Fields::update_field_value($post_id, 'abstract', $abstract);
    PFM_Medical_Fields::update_field_value($post_id, 'keywords-englisch', $keywords_englisch);
    PFM_Medical_Fields::update_field_value($post_id, 'literatur', $literatur);

    // Review Status
    update_post_meta($post_id, 'review_status', 'submitted');

    // Manuskript Upload
    if(!empty($_FILES['manuscript_pdf']['name'])) {
        $manuscript_id = pfm_handle_upload($_FILES['manuscript_pdf'], $post_id, true);
        if($manuscript_id) {
            PFM_File_Manager::add_file_version($post_id, $manuscript_id, 'initial', 'Ersteinreichung');
        }
    }

    // Supplementary Materials
    if(!empty($_FILES['supplement_files']['name'][0])) {
        $supp_ids = array();
        foreach($_FILES['supplement_files']['name'] as $i => $name) {
            if(empty($name)) continue;
            $file = array(
                'name' => $_FILES['supplement_files']['name'][$i],
                'type' => $_FILES['supplement_files']['type'][$i],
                'tmp_name' => $_FILES['supplement_files']['tmp_name'][$i],
                'error' => $_FILES['supplement_files']['error'][$i],
                'size' => $_FILES['supplement_files']['size'][$i],
            );
            $aid = pfm_handle_upload($file, $post_id, false);
            if($aid) $supp_ids[] = $aid;
        }
        if($supp_ids) update_post_meta($post_id, 'pfm_supplementary_ids', $supp_ids);
    }

    // E-Mail an Redaktion
    PFM_Email_Templates::send_submission_emails($post_id);

    // Status-Historie
    PFM_Workflow_Tracker::log_status_change($post_id, '', 'submitted');

    $redirect = add_query_arg(array('pfm_id' => $post_id, 'submitted' => 'success'), get_permalink($post_id));
    wp_safe_redirect($redirect);
    exit;
}

/**
 * Handle Medical Review Submission
 */
add_action('admin_post_pfm_submit_medical_review', 'pfm_handle_submit_medical_review');
function pfm_handle_submit_medical_review() {
    if(!is_user_logged_in()) wp_die(__('Bitte einloggen.', PFM_TD));
    check_admin_referer('pfm_submit_medical_review', 'pfm_medical_review_nonce');

    $post_id = intval($_POST['pfm_post_id']);
    $uid = get_current_user_id();

    // Scores sammeln
    $scores = isset($_POST['pfm_scores']) ? $_POST['pfm_scores'] : array();
    foreach($scores as $key => $value) {
        $scores[$key] = intval($value);
    }

    $rec = sanitize_text_field($_POST['pfm_recommendation'] ?? '');
    $cta = wp_kses_post($_POST['pfm_comments_to_author'] ?? '');
    $cte = wp_kses_post($_POST['pfm_confidential_to_editor'] ?? '');

    // Review Comment erstellen
    $comment_id = wp_insert_comment(array(
        'comment_post_ID' => $post_id,
        'comment_author' => wp_get_current_user()->display_name,
        'comment_author_email' => wp_get_current_user()->user_email,
        'comment_content' => '(Medical Review)',
        'user_id' => $uid,
        'comment_approved' => 1,
        'comment_type' => 'pfm_review',
    ));

    if($comment_id) {
        update_comment_meta($comment_id, 'pfm_recommendation', $rec);
        update_comment_meta($comment_id, 'pfm_comments_to_author', $cta);
        if($cte) update_comment_meta($comment_id, 'pfm_confidential_to_editor', $cte);

        // Scores speichern
        update_comment_meta($comment_id, 'pfm_review_scores', $scores);

        // Gewichteten Score berechnen
        $criteria = PFM_Medical_Fields::get_medical_review_criteria();
        $weighted_score = PFM_Review_Criteria::calculate_weighted_score($scores);
        update_comment_meta($comment_id, 'pfm_review_weighted_score', $weighted_score);

        // File Upload
        if(!empty($_FILES['pfm_review_file']['name'])) {
            $aid = pfm_handle_upload($_FILES['pfm_review_file'], $post_id, false);
            if($aid) update_comment_meta($comment_id, 'pfm_review_attachment_id', $aid);
        }

        // Notification
        $data = PFM_Email_Templates::get_email_data($post_id, array(
            'reviewer_name' => wp_get_current_user()->display_name
        ));
        PFM_Email_Templates::send_email(
            get_bloginfo('admin_email'),
            'review_received',
            $data
        );
    }

    $redirect = add_query_arg(array('pfm_id' => $post_id, 'review_submitted' => 'success'), get_permalink($post_id));
    wp_safe_redirect($redirect);
    exit;
}

/**
 * Handle Medical Fields Update
 */
add_action('admin_post_pfm_update_medical_fields', 'pfm_handle_update_medical_fields');
function pfm_handle_update_medical_fields() {
    if(!pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion()) {
        wp_die(__('Keine Berechtigung.', PFM_TD));
    }

    check_admin_referer('pfm_update_medical_fields', 'pfm_medical_nonce');

    $post_id = intval($_POST['post_id']);

    // Update ACF Fields
    PFM_Medical_Fields::update_field_value($post_id, 'publikationsart', sanitize_text_field($_POST['publikationsart'] ?? ''));
    PFM_Medical_Fields::update_field_value($post_id, 'kardiotechnikausgabe', sanitize_text_field($_POST['kardiotechnikausgabe'] ?? ''));
    PFM_Medical_Fields::update_field_value($post_id, 'supplement', sanitize_text_field($_POST['supplement'] ?? ''));
    PFM_Medical_Fields::update_field_value($post_id, 'doi', sanitize_text_field($_POST['doi'] ?? ''));
    PFM_Medical_Fields::update_field_value($post_id, 'volltext_anzeigen', sanitize_text_field($_POST['volltext_anzeigen'] ?? ''));

    $redirect = add_query_arg(array('pfm_id' => $post_id, 'updated' => 'success'), get_permalink($post_id));
    wp_safe_redirect($redirect);
    exit;
}

/**
 * Helper: Render Reviews List
 */
function pfm_render_reviews_list($post_id) {
    $reviews = get_comments(array(
        'post_id' => $post_id,
        'type' => 'pfm_review',
        'status' => 'approve',
        'orderby' => 'comment_date',
        'order' => 'ASC',
    ));

    if(empty($reviews)) {
        return '<p>'.__('Noch keine Reviews eingegangen.', PFM_TD).'</p>';
    }

    $output = '<div class="pfm-reviews-list">';

    foreach($reviews as $review) {
        $rec = get_comment_meta($review->comment_ID, 'pfm_recommendation', true);
        $to_author = get_comment_meta($review->comment_ID, 'pfm_comments_to_author', true);
        $confidential = get_comment_meta($review->comment_ID, 'pfm_confidential_to_editor', true);

        $output .= '<div class="review-item">';
        $output .= '<h5>'.esc_html($review->comment_author).' — '.esc_html(mysql2date('d.m.Y', $review->comment_date)).'</h5>';

        $output .= PFM_Review_Criteria::render_scores_display($review->comment_ID);

        if($to_author) {
            $output .= '<div class="review-comments"><strong>'.__('Kommentare:', PFM_TD).'</strong><br>';
            $output .= wp_kses_post(nl2br($to_author)).'</div>';
        }

        if($confidential && (pfm_user_is_editor_in_chief() || pfm_user_is_redaktion())) {
            $output .= '<div class="review-confidential"><strong>'.__('Vertraulich (nur Redaktion):', PFM_TD).'</strong><br>';
            $output .= wp_kses_post(nl2br($confidential)).'</div>';
        }

        $output .= '</div>';
    }

    $output .= '</div>';

    return $output;
}

/* -------------------------------------------------------------------------
 * AJAX HANDLERS - Dashboard Enhanced
 * ------------------------------------------------------------------------- */

/**
 * AJAX Handler: Load Dashboard Publications
 */
add_action('wp_ajax_pfm_load_dashboard_publications', 'pfm_ajax_load_dashboard_publications');
function pfm_ajax_load_dashboard_publications() {
    check_ajax_referer('pfm_dashboard_nonce', 'nonce');

    $current_user_id = get_current_user_id();
    $is_eic = pfm_user_is_editor_in_chief();
    $is_ed = pfm_user_is_redaktion();
    $is_rev = pfm_user_is_reviewer();

    // Get parameters
    $filter = sanitize_text_field($_POST['filter'] ?? 'all');
    $search = sanitize_text_field($_POST['search'] ?? '');
    $sort = sanitize_text_field($_POST['sort'] ?? 'date_desc');
    $page = intval($_POST['page'] ?? 1);
    $per_page = 20;

    // Build query args
    $args = array(
        'post_type' => 'publikation',
        'posts_per_page' => $per_page,
        'paged' => $page,
    );

    // Filter by status
    if($filter !== 'all') {
        $args['meta_query'] = array(
            array(
                'key' => 'pfm_status',
                'value' => $filter,
                'compare' => '='
            )
        );
    }

    // Search
    if(!empty($search)) {
        $args['s'] = $search;
    }

    // Sort
    switch($sort) {
        case 'date_desc':
            $args['orderby'] = 'date';
            $args['order'] = 'DESC';
            break;
        case 'date_asc':
            $args['orderby'] = 'date';
            $args['order'] = 'ASC';
            break;
        case 'title_asc':
            $args['orderby'] = 'title';
            $args['order'] = 'ASC';
            break;
        case 'title_desc':
            $args['orderby'] = 'title';
            $args['order'] = 'DESC';
            break;
    }

    // Access control
    if(!$is_eic && !$is_ed) {
        if($is_rev) {
            // Reviewer sees only assigned
            $args['meta_query'][] = array(
                'key' => 'pfm_assigned_reviewers',
                'value' => serialize(strval($current_user_id)),
                'compare' => 'LIKE'
            );
        } else {
            // Author sees only own
            $args['author'] = $current_user_id;
        }
    }

    $query = new WP_Query($args);

    $publications = array();

    if($query->have_posts()) {
        while($query->have_posts()) {
            $query->the_post();
            $post_id = get_the_ID();

            $status = get_post_meta($post_id, 'pfm_status', true) ?: 'submitted';
            $pub_type = PFM_Medical_Fields::get_field_value($post_id, 'publikationsart');
            $ausgabe = PFM_Medical_Fields::get_field_value($post_id, 'kardiotechnikausgabe');
            $doi = PFM_Medical_Fields::get_field_value($post_id, 'doi');
            $autoren = PFM_Medical_Fields::get_field_value($post_id, 'autoren');

            // Get review statistics
            $reviews = get_comments(array(
                'post_id' => $post_id,
                'type' => 'pfm_review',
                'status' => 'approve',
                'count' => true
            ));

            $assigned_reviewers = get_post_meta($post_id, 'pfm_assigned_reviewers', true) ?: array();
            $reviewer_count = is_array($assigned_reviewers) ? count($assigned_reviewers) : 0;

            $publications[] = array(
                'id' => $post_id,
                'title' => get_the_title(),
                'excerpt' => get_the_excerpt(),
                'date' => get_the_date('d.m.Y'),
                'status' => $status,
                'status_label' => pfm_get_status_label($status),
                'pub_type' => $pub_type,
                'ausgabe' => $ausgabe,
                'doi' => $doi,
                'autoren' => $autoren,
                'author_name' => get_the_author(),
                'reviews_count' => $reviews,
                'reviewers_count' => $reviewer_count,
                'permalink' => get_permalink($post_id),
                'edit_link' => admin_url('post.php?post=' . $post_id . '&action=edit')
            );
        }
        wp_reset_postdata();
    }

    wp_send_json_success(array(
        'publications' => $publications,
        'total' => $query->found_posts,
        'pages' => $query->max_num_pages,
        'current_page' => $page
    ));
}

/**
 * AJAX Handler: Verify Literature Sources
 */
add_action('wp_ajax_pfm_verify_literature', 'pfm_ajax_verify_literature');
function pfm_ajax_verify_literature() {
    check_ajax_referer('pfm_dashboard_nonce', 'nonce');

    if(!pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion()) {
        wp_send_json_error(array('message' => 'Keine Berechtigung'));
    }

    $post_id = intval($_POST['post_id']);

    // Get literature from ACF field
    $literatur = PFM_Medical_Fields::get_field_value($post_id, 'literatur');

    if(empty($literatur)) {
        wp_send_json_success(array(
            'html' => '<p class="no-literature">Keine Literaturangaben vorhanden.</p>'
        ));
    }

    // Parse references (split by numbered lines)
    $references = pfm_parse_literature($literatur);

    $results = array();

    foreach($references as $index => $reference) {
        $verification = pfm_verify_single_reference($reference);

        $results[] = array(
            'number' => $index + 1,
            'text' => $reference,
            'status' => $verification['status'], // 'valid', 'invalid', 'uncertain'
            'links' => $verification['links'],
            'message' => $verification['message']
        );
    }

    // Build HTML
    $html = '<div class="literature-verification-results">';

    foreach($results as $result) {
        $status_class = 'verification-' . $result['status'];
        $icon = $result['status'] === 'valid' ? '✓' : ($result['status'] === 'invalid' ? '✗' : '?');

        $html .= '<div class="verification-item ' . esc_attr($status_class) . '">';
        $html .= '<div class="verification-header">';
        $html .= '<span class="verification-icon">' . $icon . '</span>';
        $html .= '<span class="verification-number">[' . $result['number'] . ']</span>';
        $html .= '</div>';
        $html .= '<div class="verification-text">' . esc_html($result['text']) . '</div>';

        if(!empty($result['links'])) {
            $html .= '<div class="verification-links">';
            foreach($result['links'] as $link) {
                $html .= '<a href="' . esc_url($link['url']) . '" target="_blank" class="verification-link">';
                $html .= esc_html($link['label']) . '</a>';
            }
            $html .= '</div>';
        }

        if(!empty($result['message'])) {
            $html .= '<div class="verification-message">' . esc_html($result['message']) . '</div>';
        }

        $html .= '</div>';
    }

    $html .= '</div>';

    wp_send_json_success(array('html' => $html));
}

/**
 * Parse Literature into Individual References
 */
function pfm_parse_literature($literatur) {
    // Remove HTML tags
    $text = wp_strip_all_tags($literatur);

    // Split by numbered references (1., 2., etc. or [1], [2], etc.)
    $pattern = '/(?:^|\n)\s*(?:\[?\d+\]?\.?)\s+/m';
    $parts = preg_split($pattern, $text, -1, PREG_SPLIT_NO_EMPTY);

    return array_map('trim', $parts);
}

/**
 * Verify Single Reference Against External Sources
 */
function pfm_verify_single_reference($reference) {
    $result = array(
        'status' => 'uncertain',
        'links' => array(),
        'message' => ''
    );

    // Extract DOI if present
    $doi = pfm_extract_doi($reference);
    if($doi) {
        $doi_valid = pfm_check_doi($doi);
        if($doi_valid) {
            $result['status'] = 'valid';
            $result['links'][] = array(
                'label' => 'DOI',
                'url' => 'https://doi.org/' . $doi
            );
        } else {
            $result['status'] = 'invalid';
            $result['message'] = 'DOI nicht auflösbar';
        }
    }

    // Extract PubMed ID if present
    $pmid = pfm_extract_pmid($reference);
    if($pmid) {
        $result['links'][] = array(
            'label' => 'PubMed',
            'url' => 'https://pubmed.ncbi.nlm.nih.gov/' . $pmid
        );
        if($result['status'] !== 'valid') {
            $result['status'] = 'valid';
        }
    }

    // Generate Google Scholar search link
    $search_query = pfm_generate_scholar_query($reference);
    $result['links'][] = array(
        'label' => 'Google Scholar',
        'url' => 'https://scholar.google.com/scholar?q=' . urlencode($search_query)
    );

    // If we found identifiers, mark as valid
    if(!empty($doi) || !empty($pmid)) {
        if($result['status'] === 'uncertain') {
            $result['status'] = 'valid';
        }
    }

    return $result;
}

/**
 * Extract DOI from Reference
 */
function pfm_extract_doi($text) {
    // Pattern: 10.xxxx/xxxxx
    if(preg_match('/10\.\d{4,}\/[^\s]+/i', $text, $matches)) {
        return trim($matches[0], '.,;');
    }
    return false;
}

/**
 * Extract PubMed ID from Reference
 */
function pfm_extract_pmid($text) {
    // Pattern: PMID: 12345678 or PMID:12345678
    if(preg_match('/PMID:\s*(\d+)/i', $text, $matches)) {
        return $matches[1];
    }
    return false;
}

/**
 * Check if DOI is Valid
 */
function pfm_check_doi($doi) {
    $url = 'https://doi.org/' . $doi;

    $response = wp_remote_head($url, array(
        'timeout' => 5,
        'redirection' => 5
    ));

    if(is_wp_error($response)) {
        return false;
    }

    $code = wp_remote_retrieve_response_code($response);
    return ($code >= 200 && $code < 400);
}

/**
 * Generate Google Scholar Search Query
 */
function pfm_generate_scholar_query($reference) {
    // Extract author and year if possible
    $query = $reference;

    // Remove URLs
    $query = preg_replace('#https?://[^\s]+#', '', $query);

    // Remove DOI
    $query = preg_replace('/10\.\d{4,}\/[^\s]+/i', '', $query);

    // Remove PMID
    $query = preg_replace('/PMID:\s*\d+/i', '', $query);

    // Take first 100 chars for search
    $query = substr($query, 0, 100);

    return trim($query);
}

/**
 * Helper: Get Status Label
 */
function pfm_get_status_label($status) {
    $labels = array(
        'submitted' => 'Eingereicht',
        'under_review' => 'Im Review',
        'revision_needed' => 'Nachbesserung',
        'accepted' => 'Akzeptiert',
        'rejected' => 'Abgelehnt',
        'published' => 'Veröffentlicht'
    );

    return $labels[$status] ?? $status;
}

/* -------------------------------------------------------------------------
 * Ende Plugin - Medical Enhanced Version 3.0.0
 * ------------------------------------------------------------------------- */
