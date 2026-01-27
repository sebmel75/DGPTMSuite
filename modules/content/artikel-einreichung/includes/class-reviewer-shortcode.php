<?php
/**
 * DGPTM Artikel-Einreichung - Reviewer Upload Shortcode
 * Provides [dgptm_reviewer_upload] shortcode for token-based uploads
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Artikel_Reviewer_Shortcode {

    /**
     * Initialize
     */
    public function __construct() {
        add_shortcode('dgptm_reviewer_upload', array($this, 'render_shortcode'));
        add_shortcode('dgptm_is_assigned_reviewer', array($this, 'render_is_assigned_reviewer'));

        // AJAX handlers
        add_action('wp_ajax_dgptm_reviewer_upload', array($this, 'ajax_handle_upload'));
        add_action('wp_ajax_nopriv_dgptm_reviewer_upload', array($this, 'ajax_handle_upload'));
        add_action('wp_ajax_dgptm_reviewer_download', array($this, 'ajax_handle_download'));
        add_action('wp_ajax_nopriv_dgptm_reviewer_download', array($this, 'ajax_handle_download'));

        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
    }

    /**
     * Conditionally enqueue assets
     */
    public function maybe_enqueue_assets() {
        global $post;

        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'dgptm_reviewer_upload')) {
            $this->enqueue_assets();
        }

        // Also enqueue if token parameter is present
        if (isset($_GET['token'])) {
            $this->enqueue_assets();
        }
    }

    /**
     * Enqueue CSS and JavaScript
     */
    public function enqueue_assets() {
        wp_enqueue_style('dashicons');
        wp_enqueue_style(
            'dgptm-reviewer-upload',
            DGPTM_ARTIKEL_URL . 'assets/css/reviewer-upload.css',
            array(),
            defined('DGPTM_ARTIKEL_VERSION') ? DGPTM_ARTIKEL_VERSION : '1.0.0'
        );

        wp_enqueue_script(
            'dgptm-reviewer-upload',
            DGPTM_ARTIKEL_URL . 'assets/js/reviewer-upload.js',
            array('jquery'),
            defined('DGPTM_ARTIKEL_VERSION') ? DGPTM_ARTIKEL_VERSION : '1.0.0',
            true
        );

        wp_localize_script('dgptm-reviewer-upload', 'dgptm_reviewer', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dgptm_reviewer_nonce'),
            'i18n' => array(
                'uploading' => __('Wird hochgeladen...', 'dgptm-artikel-einreichung'),
                'success' => __('Datei erfolgreich hochgeladen!', 'dgptm-artikel-einreichung'),
                'error' => __('Fehler beim Hochladen.', 'dgptm-artikel-einreichung'),
                'file_required' => __('Bitte wählen Sie eine Datei aus.', 'dgptm-artikel-einreichung'),
                'name_required' => __('Bitte geben Sie Ihren Namen an.', 'dgptm-artikel-einreichung'),
            ),
        ));
    }

    /**
     * Render the shortcode
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Gutachten-Upload', 'dgptm-artikel-einreichung'),
        ), $atts);

        // Get token from URL
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        if (empty($token)) {
            return $this->render_error(__('Kein Upload-Token angegeben. Bitte verwenden Sie den Link aus Ihrer E-Mail.', 'dgptm-artikel-einreichung'));
        }

        // Validate token
        $token_manager = new DGPTM_Artikel_Upload_Token();
        $token_data = $token_manager->validate($token);

        if (is_wp_error($token_data)) {
            return $this->render_error($token_data->get_error_message());
        }

        // Get article data
        $artikel_id = $token_data['artikel_id'];
        $artikel = get_post($artikel_id);

        if (!$artikel) {
            return $this->render_error(__('Der zugehörige Artikel wurde nicht gefunden.', 'dgptm-artikel-einreichung'));
        }

        // Get article metadata
        $autoren = get_field('hauptautorin', $artikel_id);
        $abstract = get_field('abstract-deutsch', $artikel_id) ?: get_field('abstract', $artikel_id);
        $submission_date = get_field('submitted_at', $artikel_id);

        // Get current manuscript version for download
        $uploader = new DGPTM_Artikel_SharePoint_Uploader();
        $current_version = $uploader->get_current_version($artikel_id, DGPTM_Artikel_SharePoint_Uploader::TYPE_EINREICHUNG);

        // If no SharePoint version, try to get attachment
        if (!$current_version) {
            $manuscript_id = get_field('manuskript', $artikel_id);
            if ($manuscript_id) {
                $current_version = array(
                    'id' => $manuscript_id,
                    'source' => 'attachment',
                    'filename' => basename(get_attached_file($manuscript_id)),
                    'version' => 1,
                );
            }
        } else {
            $current_version['source'] = 'sharepoint';
        }

        // Render template
        ob_start();
        include DGPTM_ARTIKEL_PATH . 'templates/reviewer-upload-form.php';
        return ob_get_clean();
    }

    /**
     * Render error message
     */
    private function render_error($message) {
        return '<div class="dgptm-reviewer-error">
            <div class="error-icon"><span class="dashicons dashicons-warning"></span></div>
            <div class="error-message">' . esc_html($message) . '</div>
        </div>';
    }

    /**
     * Shortcode: Check if current user is assigned as reviewer
     * Returns "1" if user has pending review assignments, "0" otherwise
     */
    public function render_is_assigned_reviewer($atts) {
        if (!is_user_logged_in()) {
            return '0';
        }

        $user_id = get_current_user_id();

        // Check if user is marked as reviewer in artikel-einreichung system
        $reviewers = get_option(DGPTM_Artikel_Einreichung::OPT_REVIEWERS, array());
        $is_reviewer = false;

        foreach ($reviewers as $reviewer) {
            if (isset($reviewer['user_id']) && $reviewer['user_id'] == $user_id) {
                $is_reviewer = true;
                break;
            }
        }

        if (!$is_reviewer) {
            return '0';
        }

        // Check if user has any article assignments
        $args = array(
            'post_type' => DGPTM_Artikel_Einreichung::POST_TYPE,
            'posts_per_page' => 1,
            'post_status' => 'any',
            'fields' => 'ids',
            'meta_query' => array(
                array(
                    'key' => 'assigned_reviewers',
                    'value' => serialize(strval($user_id)),
                    'compare' => 'LIKE',
                ),
            ),
        );

        $query = new WP_Query($args);

        return $query->have_posts() ? '1' : '0';
    }

    /**
     * AJAX: Handle file upload
     */
    public function ajax_handle_upload() {
        // Verify nonce
        if (!check_ajax_referer('dgptm_reviewer_nonce', 'nonce', false)) {
            wp_send_json_error(array('message' => __('Sicherheitsprüfung fehlgeschlagen.', 'dgptm-artikel-einreichung')));
        }

        $token = sanitize_text_field($_POST['token'] ?? '');

        // Validate token
        $token_manager = new DGPTM_Artikel_Upload_Token();
        $token_data = $token_manager->validate($token);

        if (is_wp_error($token_data)) {
            wp_send_json_error(array('message' => $token_data->get_error_message()));
        }

        // Check for file
        if (empty($_FILES['file']['name'])) {
            wp_send_json_error(array('message' => __('Keine Datei hochgeladen.', 'dgptm-artikel-einreichung')));
        }

        // Validate file type
        $allowed_types = array('pdf', 'doc', 'docx');
        $file_ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

        if (!in_array($file_ext, $allowed_types)) {
            wp_send_json_error(array('message' => __('Nur PDF und Word-Dokumente sind erlaubt.', 'dgptm-artikel-einreichung')));
        }

        // Validate file size (20MB max)
        if ($_FILES['file']['size'] > 20 * 1024 * 1024) {
            wp_send_json_error(array('message' => __('Die Datei ist zu groß (max. 20 MB).', 'dgptm-artikel-einreichung')));
        }

        $artikel_id = $token_data['artikel_id'];
        $reviewer_name = sanitize_text_field($_POST['reviewer_name'] ?? $token_data['reviewer_name'] ?? '');
        $reviewer_email = sanitize_email($_POST['reviewer_email'] ?? $token_data['reviewer_email'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        // Determine version type based on token type
        $version_type_map = array(
            DGPTM_Artikel_Upload_Token::TYPE_GUTACHTEN => DGPTM_Artikel_SharePoint_Uploader::TYPE_GUTACHTEN,
            DGPTM_Artikel_Upload_Token::TYPE_REVISION => DGPTM_Artikel_SharePoint_Uploader::TYPE_REVISION,
            DGPTM_Artikel_Upload_Token::TYPE_AUTOR => DGPTM_Artikel_SharePoint_Uploader::TYPE_REVISION,
        );
        $version_type = $version_type_map[$token_data['token_type']] ?? DGPTM_Artikel_SharePoint_Uploader::TYPE_GUTACHTEN;

        // Try SharePoint upload first
        $uploader = new DGPTM_Artikel_SharePoint_Uploader();

        if ($uploader->is_available()) {
            $result = $uploader->upload_artikel_file(
                $artikel_id,
                $_FILES['file'],
                $version_type,
                array(
                    'token_id' => $token_data['id'],
                    'reviewer_name' => $reviewer_name,
                    'notes' => $notes,
                )
            );

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }
        } else {
            // Fallback to local WordPress upload
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $attachment_id = media_handle_upload('file', $artikel_id);

            if (is_wp_error($attachment_id)) {
                wp_send_json_error(array('message' => $attachment_id->get_error_message()));
            }

            // Store metadata
            update_post_meta($attachment_id, '_dgptm_reviewer_name', $reviewer_name);
            update_post_meta($attachment_id, '_dgptm_reviewer_email', $reviewer_email);
            update_post_meta($attachment_id, '_dgptm_upload_notes', $notes);
            update_post_meta($attachment_id, '_dgptm_token_id', $token_data['id']);
            update_post_meta($attachment_id, '_dgptm_upload_type', $token_data['token_type']);

            // Add to article's review attachments
            $reviews = get_post_meta($artikel_id, '_dgptm_review_attachments', true) ?: array();
            $reviews[] = array(
                'attachment_id' => $attachment_id,
                'type' => $token_data['token_type'],
                'reviewer_name' => $reviewer_name,
                'uploaded_at' => current_time('mysql'),
            );
            update_post_meta($artikel_id, '_dgptm_review_attachments', $reviews);
        }

        // Mark token as used
        $token_manager->mark_used($token);

        // Update article status if it's a review
        if ($token_data['token_type'] === DGPTM_Artikel_Upload_Token::TYPE_GUTACHTEN) {
            $current_status = get_field('artikel_status', $artikel_id);
            if ($current_status === DGPTM_Artikel_Einreichung::STATUS_UNDER_REVIEW) {
                // Status remains under review until editor makes decision
            }
        }

        // Send notification email to editors
        $this->send_upload_notification($artikel_id, $token_data, $reviewer_name);

        wp_send_json_success(array(
            'message' => __('Vielen Dank! Ihre Datei wurde erfolgreich hochgeladen.', 'dgptm-artikel-einreichung'),
        ));
    }

    /**
     * Send notification email to editors
     */
    private function send_upload_notification($artikel_id, $token_data, $reviewer_name) {
        $artikel = get_post($artikel_id);
        $type_label = DGPTM_Artikel_Upload_Token::get_type_label($token_data['token_type']);

        $subject = sprintf(
            __('[%s] Neue %s eingegangen', 'dgptm-artikel-einreichung'),
            get_bloginfo('name'),
            $type_label
        );

        $message = sprintf(
            __("Eine neue %s wurde hochgeladen.\n\nArtikel: %s\nVon: %s\n\nBitte prüfen Sie den Upload im Redaktionssystem.", 'dgptm-artikel-einreichung'),
            $type_label,
            $artikel->post_title,
            $reviewer_name
        );

        // Get editor emails from settings
        $settings = get_option(DGPTM_Artikel_Einreichung::OPT_SETTINGS, array());
        $editor_emails = isset($settings['notification_emails']) ? $settings['notification_emails'] : get_option('admin_email');

        wp_mail($editor_emails, $subject, $message);
    }

    /**
     * AJAX: Handle file download
     */
    public function ajax_handle_download() {
        $token = sanitize_text_field($_GET['token'] ?? '');
        $source = sanitize_text_field($_GET['source'] ?? '');
        $version_id = intval($_GET['version_id'] ?? 0);

        // Validate token
        $token_manager = new DGPTM_Artikel_Upload_Token();
        $token_data = $token_manager->validate($token);

        if (is_wp_error($token_data)) {
            wp_die($token_data->get_error_message());
        }

        if ($source === 'sharepoint' && $version_id) {
            $uploader = new DGPTM_Artikel_SharePoint_Uploader();
            $download_url = $uploader->get_version_download_url($version_id);

            if (is_wp_error($download_url)) {
                wp_die($download_url->get_error_message());
            }

            wp_redirect($download_url);
            exit;
        } elseif ($source === 'attachment' && $version_id) {
            $file_path = get_attached_file($version_id);

            if (!$file_path || !file_exists($file_path)) {
                wp_die(__('Datei nicht gefunden.', 'dgptm-artikel-einreichung'));
            }

            $filename = basename($file_path);
            $mime_type = mime_content_type($file_path);

            header('Content-Type: ' . $mime_type);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($file_path));

            readfile($file_path);
            exit;
        }

        wp_die(__('Ungültige Download-Anfrage.', 'dgptm-artikel-einreichung'));
    }
}
