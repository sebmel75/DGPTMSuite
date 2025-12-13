<?php
/**
 * Plugin Name: DGPTM - Publikation Frontend Manager (Enhanced)
 * Description: Professionelles Publikations-Management-System mit Review-Workflow, Editorial Decision Interface, Analytics und mehr
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
 * Internationalisierung
 */
add_action('plugins_loaded', function () {
    load_plugin_textdomain(PFM_TD, false, dirname(plugin_basename(__FILE__)) . '/languages');
});

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

/**
 * Enqueue Styles und Scripts
 */
add_action('wp_enqueue_scripts', 'pfm_enqueue_assets');
function pfm_enqueue_assets() {
    wp_enqueue_style('pfm-styles', PFM_URL . 'assets/css/pfm-styles.css', array(), PFM_VERSION);
    wp_enqueue_style('dashicons');

    wp_enqueue_script('pfm-scripts', PFM_URL . 'assets/js/pfm-scripts.js', array('jquery'), PFM_VERSION, true);

    wp_localize_script('pfm-scripts', 'pfm_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'admin_url' => admin_url(),
        'nonce' => wp_create_nonce('pfm_ajax_nonce'),
    ));
}

/**
 * Admin Styles und Scripts
 * NUR auf relevanten Seiten laden (nicht auf allen Admin-Seiten)
 */
add_action('admin_enqueue_scripts', 'pfm_admin_enqueue_assets');
function pfm_admin_enqueue_assets($hook) {
    $screen = get_current_screen();

    // Nur auf Publikations-Seiten laden
    $is_publication_screen = false;

    if ($screen && $screen->post_type === 'publikation') {
        $is_publication_screen = true;
    }

    if (strpos($hook, 'publikation') !== false || strpos($hook, 'pfm') !== false) {
        $is_publication_screen = true;
    }

    if (!$is_publication_screen) {
        return;
    }

    wp_enqueue_style('pfm-admin-styles', PFM_URL . 'assets/css/pfm-styles.css', array(), PFM_VERSION);
}

/* -------------------------------------------------------------------------
 * Benutzerprofil-Checkboxen (Editor in Chief / Redaktion / Reviewer)
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

/* =========================================================================
 * NEUE SHORTCODES - ENHANCED VERSION
 * ========================================================================= */

/**
 * Shortcode: Enhanced Publikations-Einzelansicht mit allen neuen Features
 * [publikation_view_enhanced id="123"]
 */
add_shortcode('publikation_view_enhanced', 'pfm_shortcode_view_enhanced');
function pfm_shortcode_view_enhanced($atts) {
    $a = shortcode_atts(array('id' => 0), $atts);

    $post_id = intval($a['id']);
    if($post_id <= 0 && isset($_GET['pfm_id'])) {
        $post_id = intval($_GET['pfm_id']);
    }

    if(!is_user_logged_in()) {
        return '<p style="color:red;">'.__('Bitte logge Dich ein.', PFM_TD).'</p>';
    }

    $post = get_post($post_id);
    if(!$post || $post->post_type !== 'publikation') {
        return '<p style="color:red;">'.__('Publikation nicht gefunden.', PFM_TD).'</p>';
    }

    ob_start();

    echo '<div class="pfm-publikation-enhanced">';

    // Workflow Timeline
    echo PFM_Workflow_Tracker::render_timeline($post_id);

    // Basic Information
    echo '<div class="publication-info">';
    echo '<h2>'.esc_html($post->post_title).'</h2>';

    $autoren = get_post_meta($post_id, 'autoren', true);
    $doi = get_post_meta($post_id, 'doi', true);
    $status = get_post_meta($post_id, 'review_status', true);

    echo '<p><strong>'.__('Autoren:', PFM_TD).'</strong> '.esc_html($autoren).'</p>';
    echo '<p><strong>DOI:</strong> '.($doi ? esc_html($doi) : '<em>'.__('Noch nicht vergeben', PFM_TD).'</em>').'</p>';
    echo '<p><strong>'.__('Status:', PFM_TD).'</strong> '. PFM_Workflow_Tracker::render_status_badge($status).'</p>';

    $abstract = get_post_meta($post_id, 'pfm_abstract', true);
    if($abstract) {
        echo '<div class="abstract"><h4>'.__('Abstract', PFM_TD).'</h4>';
        echo '<p>'.wp_kses_post(nl2br($abstract)).'</p></div>';
    }

    echo '</div>';

    // File Versions
    echo PFM_File_Manager::render_version_history($post_id);
    echo PFM_File_Manager::render_supplementary_files($post_id);

    // Für Autoren: Upload neue Version
    if($post->post_author == get_current_user_id() && $status === 'revision_needed') {
        echo PFM_File_Manager::render_upload_form($post_id);
    }

    // Für Reviewer: COI Declaration und Review-Form
    if(pfm_user_is_reviewer()) {
        $assigned = get_post_meta($post_id, 'pfm_assigned_reviewers', true);
        if(is_array($assigned) && in_array(get_current_user_id(), $assigned)) {
            echo PFM_Conflict_Interest::render_declaration_form($post_id);
            echo '<hr>';
            echo pfm_render_enhanced_review_form($post_id);
        }
    }

    // Review-Übersicht
    echo '<h3>'.__('Reviews', PFM_TD).'</h3>';
    echo PFM_Review_Criteria::render_aggregated_scores($post_id);
    echo pfm_render_reviews_list($post_id);

    // Für Redaktion: Editorial Decision Interface
    if(pfm_user_is_editor_in_chief() || pfm_user_is_redaktion()) {
        echo '<hr>';
        echo PFM_Editorial_Decision::render_decision_interface($post_id);
        echo PFM_Conflict_Interest::render_coi_status($post_id);
        echo PFM_Editorial_Decision::render_decisions_history($post_id);
        echo PFM_Workflow_Tracker::render_history_table($post_id);
    }

    echo '</div>';

    return ob_get_clean();
}

/**
 * Render Enhanced Review Form mit Kriterien
 */
function pfm_render_enhanced_review_form($post_id) {
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
    <div class="pfm-enhanced-review-form">
        <h3><?php _e('Review einreichen', PFM_TD); ?></h3>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
            <?php wp_nonce_field('pfm_submit_enhanced_review', 'pfm_enhanced_review_nonce'); ?>
            <input type="hidden" name="action" value="pfm_submit_enhanced_review">
            <input type="hidden" name="pfm_post_id" value="<?php echo esc_attr($post_id); ?>">

            <?php PFM_Review_Criteria::render_criteria_form(); ?>

            <h4><?php _e('Empfehlung', PFM_TD); ?></h4>
            <p>
                <select name="pfm_recommendation" required style="width:100%;max-width:400px;">
                    <option value=""><?php _e('-- Bitte wählen --', PFM_TD); ?></option>
                    <option value="accept"><?php _e('Accept', PFM_TD); ?></option>
                    <option value="minor"><?php _e('Minor Revision', PFM_TD); ?></option>
                    <option value="major"><?php _e('Major Revision', PFM_TD); ?></option>
                    <option value="reject"><?php _e('Reject', PFM_TD); ?></option>
                </select>
            </p>

            <p>
                <label><strong><?php _e('Kommentare an Autor:innen', PFM_TD); ?></strong></label><br>
                <textarea name="pfm_comments_to_author" rows="8" style="width:100%;" required></textarea>
            </p>

            <p>
                <label><strong><?php _e('Vertrauliche Kommentare an Redaktion', PFM_TD); ?></strong></label><br>
                <textarea name="pfm_confidential_to_editor" rows="6" style="width:100%;"></textarea>
            </p>

            <p>
                <label><?php _e('Datei anhängen (optional)', PFM_TD); ?><br>
                <input type="file" name="pfm_review_file">
                </label>
            </p>

            <p class="submit">
                <button type="submit" class="button button-primary button-large">
                    <?php _e('Review absenden', PFM_TD); ?>
                </button>
            </p>
        </form>
    </div>
    <?php
    return ob_get_clean();
}

/**
 * Render Reviews List
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
        return '<p>'.__('Keine Reviews vorhanden.', PFM_TD).'</p>';
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
            $output .= '<div class="review-confidential"><strong>'.__('Vertraulich (Redaktion):', PFM_TD).'</strong><br>';
            $output .= wp_kses_post(nl2br($confidential)).'</div>';
        }

        $output .= '</div>';
    }

    $output .= '</div>';

    return $output;
}

/**
 * Shortcode: Analytics Dashboard
 * [publikation_analytics]
 */
add_shortcode('publikation_analytics', 'pfm_shortcode_analytics');
function pfm_shortcode_analytics() {
    if(!pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion()) {
        return '<p>'.__('Keine Berechtigung.', PFM_TD).'</p>';
    }

    return PFM_Analytics::render_dashboard();
}

/**
 * Shortcode: Upcoming Deadlines Widget
 * [publikation_deadlines]
 */
add_shortcode('publikation_deadlines', 'pfm_shortcode_deadlines');
function pfm_shortcode_deadlines() {
    if(!is_user_logged_in()) {
        return '';
    }

    return PFM_Reminders::render_upcoming_deadlines_widget();
}

/* =========================================================================
 * AJAX HANDLERS
 * ========================================================================= */

/**
 * AJAX: Set Current File Version
 */
add_action('wp_ajax_pfm_set_current_version', 'pfm_ajax_set_current_version');
function pfm_ajax_set_current_version() {
    check_ajax_referer('pfm_ajax_nonce', 'nonce');

    $post_id = intval($_POST['post_id']);
    $version = intval($_POST['version']);

    if(!pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion()) {
        wp_send_json_error(__('Keine Berechtigung', PFM_TD));
    }

    update_post_meta($post_id, 'pfm_current_version', $version);

    wp_send_json_success(__('Version aktualisiert', PFM_TD));
}

/* =========================================================================
 * ADMIN POST HANDLERS
 * ========================================================================= */

/**
 * Handle Enhanced Review Submission
 */
add_action('admin_post_pfm_submit_enhanced_review', 'pfm_handle_enhanced_review');
function pfm_handle_enhanced_review() {
    if(!is_user_logged_in()) wp_die(__('Bitte einloggen.', PFM_TD));
    check_admin_referer('pfm_submit_enhanced_review', 'pfm_enhanced_review_nonce');

    $post_id = intval($_POST['pfm_post_id']);
    $post = get_post($post_id);
    if(!$post || $post->post_type !== 'publikation') wp_die(__('Ungültig.', PFM_TD));

    $uid = get_current_user_id();
    $assigned = get_post_meta($post_id, 'pfm_assigned_reviewers', true);

    if(!in_array($uid, (array)$assigned) && !pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion()) {
        wp_die(__('Keine Berechtigung.', PFM_TD));
    }

    // Scores
    $scores = isset($_POST['pfm_scores']) ? $_POST['pfm_scores'] : array();
    foreach($scores as $key => $value) {
        $scores[$key] = intval($value);
    }

    $rec = sanitize_text_field($_POST['pfm_recommendation'] ?? '');
    $cta = wp_kses_post($_POST['pfm_comments_to_author'] ?? '');
    $cte = wp_kses_post($_POST['pfm_confidential_to_editor'] ?? '');

    // Create review comment
    $comment_id = wp_insert_comment(array(
        'comment_post_ID' => $post_id,
        'comment_author' => wp_get_current_user()->display_name,
        'comment_author_email' => wp_get_current_user()->user_email,
        'comment_content' => '(Enhanced Review)',
        'user_id' => $uid,
        'comment_approved' => 1,
        'comment_type' => 'pfm_review',
    ));

    if($comment_id) {
        update_comment_meta($comment_id, 'pfm_recommendation', $rec);
        update_comment_meta($comment_id, 'pfm_comments_to_author', $cta);
        if($cte) update_comment_meta($comment_id, 'pfm_confidential_to_editor', $cte);

        // Save scores
        PFM_Review_Criteria::save_review_scores($comment_id, $scores);

        // File upload
        if(!empty($_FILES['pfm_review_file']['name'])) {
            $aid = pfm_handle_upload($_FILES['pfm_review_file'], $post_id, false);
            if($aid) update_comment_meta($comment_id, 'pfm_review_attachment_id', $aid);
        }

        // Notify editors
        PFM_Email_Templates::send_email(
            pfm_get_editors()[0]->user_email ?? get_bloginfo('admin_email'),
            'review_received',
            PFM_Email_Templates::get_email_data($post_id, array('reviewer_name' => wp_get_current_user()->display_name))
        );
    }

    $redirect = add_query_arg(array('pfm_id' => $post_id, 'reviewed' => 'true'), get_permalink($post_id));
    wp_safe_redirect($redirect);
    exit;
}

/**
 * Handle Editorial Decision
 */
add_action('admin_post_pfm_make_decision', 'pfm_handle_make_decision');
function pfm_handle_make_decision() {
    if(!is_user_logged_in() || (!pfm_user_is_editor_in_chief() && !pfm_user_is_redaktion())) {
        wp_die(__('Keine Berechtigung.', PFM_TD));
    }
    check_admin_referer('pfm_make_decision', 'pfm_decision_nonce');

    $post_id = intval($_POST['pfm_post_id']);
    $decision = sanitize_text_field($_POST['decision']);
    $comments = wp_kses_post($_POST['decision_comments']);
    $send_email = isset($_POST['send_email']);

    PFM_Editorial_Decision::save_decision($post_id, $decision, $comments, $send_email);

    $redirect = add_query_arg(array('pfm_id' => $post_id, 'decision' => 'saved'), get_permalink($post_id));
    wp_safe_redirect($redirect);
    exit;
}

/**
 * Handle File Version Upload
 */
add_action('admin_post_pfm_upload_version', 'pfm_handle_upload_version');
function pfm_handle_upload_version() {
    if(!is_user_logged_in()) wp_die(__('Bitte einloggen.', PFM_TD));
    check_admin_referer('pfm_upload_version', 'pfm_upload_version_nonce');

    $post_id = intval($_POST['pfm_post_id']);
    $version_type = sanitize_text_field($_POST['version_type']);
    $notes = sanitize_textarea_field($_POST['version_notes']);

    if(empty($_FILES['version_file']['name'])) {
        wp_die(__('Keine Datei ausgewählt.', PFM_TD));
    }

    $aid = pfm_handle_upload($_FILES['version_file'], $post_id, true);
    if($aid) {
        PFM_File_Manager::add_file_version($post_id, $aid, $version_type, $notes);
    }

    $redirect = add_query_arg(array('pfm_id' => $post_id, 'version_uploaded' => 'true'), get_permalink($post_id));
    wp_safe_redirect($redirect);
    exit;
}

/**
 * Handle COI Declaration
 */
add_action('admin_post_pfm_save_coi', 'pfm_handle_save_coi');
function pfm_handle_save_coi() {
    if(!is_user_logged_in()) wp_die(__('Bitte einloggen.', PFM_TD));
    check_admin_referer('pfm_save_coi', 'pfm_coi_nonce');

    $post_id = intval($_POST['pfm_post_id']);
    $has_conflict = $_POST['has_conflict'] === '1';
    $details = sanitize_textarea_field($_POST['conflict_details'] ?? '');

    PFM_Conflict_Interest::save_reviewer_declaration(get_current_user_id(), $post_id, $has_conflict, $details);

    $redirect = add_query_arg(array('pfm_id' => $post_id, 'coi_saved' => 'true'), get_permalink($post_id));
    wp_safe_redirect($redirect);
    exit;
}

/**
 * Handle CSV Export
 */
add_action('admin_post_pfm_export_csv', 'pfm_handle_export_csv');
function pfm_handle_export_csv() {
    $type = sanitize_text_field($_GET['type'] ?? 'submissions');
    PFM_Analytics::export_to_csv($type);
}

/**
 * Handle Reviewer Exclusions
 */
add_action('admin_post_pfm_save_exclusions', 'pfm_handle_save_exclusions');
function pfm_handle_save_exclusions() {
    if(!is_user_logged_in()) wp_die(__('Bitte einloggen.', PFM_TD));
    check_admin_referer('pfm_save_exclusions', 'pfm_exclusions_nonce');

    $post_id = intval($_POST['pfm_post_id']);
    $exclusions_text = sanitize_textarea_field($_POST['exclusions']);
    $reason = sanitize_textarea_field($_POST['exclusion_reason']);

    $exclusions = array_filter(array_map('trim', explode("\n", $exclusions_text)));

    update_post_meta($post_id, 'pfm_reviewer_exclusions', $exclusions);
    update_post_meta($post_id, 'pfm_exclusion_reason', $reason);

    $redirect = add_query_arg(array('pfm_id' => $post_id, 'exclusions_saved' => 'true'), get_permalink($post_id));
    wp_safe_redirect($redirect);
    exit;
}

/**
 * Helper: Handle File Upload
 */
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
            'guid' => $movefile['url'],
            'post_mime_type' => $movefile['type'],
            'post_title' => sanitize_file_name(basename($movefile['file'])),
            'post_content' => '',
            'post_status' => 'inherit'
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

/**
 * Settings Helper (aus Original übernommen)
 */
function pfm_get_settings() {
    $defaults = array(
        'doi_prefix' => '',
        'crossref_user' => '',
        'crossref_password' => '',
        'depositor_name' => get_bloginfo('name'),
        'depositor_email' => get_bloginfo('admin_email'),
        'registrant' => get_bloginfo('name'),
        'publisher' => get_bloginfo('name'),
        'journal_title' => get_bloginfo('name'),
        'issn_print' => '',
        'issn_electronic' => '',
        'license_url_default' => 'https://creativecommons.org/licenses/by/4.0/',
        'use_sandbox' => '1',
        'use_v2_sync' => '0',
        'notify_endpoint' => '',
    );

    $opt = get_option('pfm_crossref_settings', array());
    return wp_parse_args($opt, $defaults);
}

/* =========================================================================
 * ENDE - Publication Workflow Enhanced
 * ========================================================================= */
