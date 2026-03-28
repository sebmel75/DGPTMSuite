<?php
/**
 * Publication Frontend Manager - Reviewer Upload Shortcode
 * Provides [pfm_reviewer_upload] shortcode for token-based file uploads
 */

if (!defined('ABSPATH')) {
    exit;
}

class PFM_Reviewer_Shortcode {

    /**
     * @var PFM_Upload_Token
     */
    private $token_manager;

    /**
     * @var PFM_SharePoint_Uploader
     */
    private $uploader;

    /**
     * Constructor - registers shortcode and hooks
     */
    public function __construct() {
        $this->token_manager = new PFM_Upload_Token();
        $this->uploader = new PFM_SharePoint_Uploader();

        // Register shortcode
        add_shortcode('pfm_reviewer_upload', array($this, 'render'));

        // AJAX handlers
        add_action('wp_ajax_pfm_reviewer_upload', array($this, 'handle_upload'));
        add_action('wp_ajax_nopriv_pfm_reviewer_upload', array($this, 'handle_upload'));

        add_action('wp_ajax_pfm_reviewer_download', array($this, 'handle_download'));
        add_action('wp_ajax_nopriv_pfm_reviewer_download', array($this, 'handle_download'));

        // Enqueue assets
        add_action('wp_enqueue_scripts', array($this, 'maybe_enqueue_assets'));
    }

    /**
     * Conditionally enqueue assets only when shortcode is present
     */
    public function maybe_enqueue_assets() {
        global $post;

        if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'pfm_reviewer_upload')) {
            $this->enqueue_assets();
        }
    }

    /**
     * Enqueue CSS and JavaScript
     */
    public function enqueue_assets() {
        wp_enqueue_style(
            'pfm-reviewer-upload',
            PFM_URL . 'assets/css/reviewer-upload.css',
            array(),
            PFM_VERSION
        );

        wp_enqueue_script(
            'pfm-reviewer-upload',
            PFM_URL . 'assets/js/reviewer-upload.js',
            array('jquery'),
            PFM_VERSION,
            true
        );

        wp_localize_script('pfm-reviewer-upload', 'pfm_reviewer', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pfm_reviewer_nonce'),
            'max_file_size' => 20 * 1024 * 1024, // 20 MB
            'allowed_types' => array('application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'),
            'i18n' => array(
                'uploading' => __('Wird hochgeladen...', PFM_TD),
                'upload_success' => __('Upload erfolgreich!', PFM_TD),
                'upload_error' => __('Upload fehlgeschlagen.', PFM_TD),
                'file_too_large' => __('Datei ist zu groß. Maximum: 20 MB', PFM_TD),
                'invalid_type' => __('Ungültiger Dateityp. Erlaubt: PDF, DOC, DOCX', PFM_TD),
                'required_fields' => __('Bitte füllen Sie alle Pflichtfelder aus.', PFM_TD),
                'confirm_submit' => __('Möchten Sie das Gutachten jetzt absenden?', PFM_TD),
            ),
        ));
    }

    /**
     * Render the shortcode
     *
     * @param array $atts Shortcode attributes
     * @return string HTML output
     */
    public function render($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Gutachten-Upload für Zeitschrift Perfusion', PFM_TD),
        ), $atts);

        // Get token from URL
        $token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

        if (empty($token)) {
            return $this->render_error(__('Kein Upload-Token angegeben.', PFM_TD), 'missing');
        }

        // Validate token
        $token_data = $this->token_manager->validate($token);

        if (is_wp_error($token_data)) {
            $error_data = $token_data->get_error_data();
            $code = isset($error_data['code']) ? $error_data['code'] : 'invalid';
            return $this->render_error($token_data->get_error_message(), $code);
        }

        // Get publication data
        $publication = $token_data['publication'];
        $publication_id = $publication->ID;

        // Get additional publication info
        $autoren = get_post_meta($publication_id, 'autoren', true);
        $abstract = get_post_meta($publication_id, 'pfm_abstract', true);
        $submission_date = get_the_date('d.m.Y', $publication);

        // Get current manuscript version
        $current_version = $this->get_current_manuscript($publication_id);

        // Load template
        ob_start();
        include PFM_PATH . 'templates/reviewer-upload-form.php';
        return ob_get_clean();
    }

    /**
     * Get the current manuscript for download
     *
     * @param int $publication_id
     * @return array|null
     */
    private function get_current_manuscript($publication_id) {
        // First try SharePoint version
        $sp_version = $this->uploader->get_current_version($publication_id, PFM_SharePoint_Uploader::TYPE_EINREICHUNG);
        if ($sp_version) {
            return array(
                'source' => 'sharepoint',
                'id' => $sp_version['id'],
                'filename' => $sp_version['original_filename'],
                'version' => $sp_version['version_number'],
                'uploaded_at' => $sp_version['uploaded_at'],
            );
        }

        // Fall back to local attachment
        $attachment_id = get_post_meta($publication_id, 'pfm_manuscript_attachment_id', true);
        if ($attachment_id) {
            $file_path = get_attached_file($attachment_id);
            return array(
                'source' => 'local',
                'id' => $attachment_id,
                'filename' => basename($file_path),
                'version' => 1,
                'uploaded_at' => get_the_date('Y-m-d H:i:s', $attachment_id),
            );
        }

        return null;
    }

    /**
     * Render error message
     *
     * @param string $message Error message
     * @param string $code    Error code
     * @return string HTML
     */
    private function render_error($message, $code = 'error') {
        $icons = array(
            'missing' => 'dashicons-warning',
            'invalid' => 'dashicons-no',
            'not_found' => 'dashicons-search',
            'expired' => 'dashicons-clock',
            'used' => 'dashicons-yes-alt',
        );
        $icon = isset($icons[$code]) ? $icons[$code] : 'dashicons-warning';

        ob_start();
        ?>
        <div class="pfm-reviewer-error pfm-reviewer-error-<?php echo esc_attr($code); ?>">
            <div class="error-icon">
                <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
            </div>
            <div class="error-content">
                <h3><?php _e('Upload nicht möglich', PFM_TD); ?></h3>
                <p><?php echo esc_html($message); ?></p>
                <?php if ($code === 'expired'): ?>
                    <p class="error-hint"><?php _e('Bitte kontaktieren Sie die Redaktion für einen neuen Upload-Link.', PFM_TD); ?></p>
                <?php elseif ($code === 'used'): ?>
                    <p class="error-hint"><?php _e('Sie haben bereits ein Gutachten eingereicht. Bei Fragen kontaktieren Sie bitte die Redaktion.', PFM_TD); ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Handle AJAX upload request
     */
    public function handle_upload() {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'pfm_reviewer_nonce')) {
            wp_send_json_error(array('message' => __('Sicherheitsüberprüfung fehlgeschlagen.', PFM_TD)));
        }

        // Get and validate token
        $token = sanitize_text_field($_POST['token'] ?? '');
        $token_data = $this->token_manager->validate($token);

        if (is_wp_error($token_data)) {
            wp_send_json_error(array('message' => $token_data->get_error_message()));
        }

        // Check for file
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $error_messages = array(
                UPLOAD_ERR_INI_SIZE => __('Datei überschreitet Server-Limit.', PFM_TD),
                UPLOAD_ERR_FORM_SIZE => __('Datei überschreitet Formular-Limit.', PFM_TD),
                UPLOAD_ERR_PARTIAL => __('Datei nur teilweise hochgeladen.', PFM_TD),
                UPLOAD_ERR_NO_FILE => __('Keine Datei hochgeladen.', PFM_TD),
            );
            $error_code = $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE;
            $message = $error_messages[$error_code] ?? __('Upload-Fehler.', PFM_TD);
            wp_send_json_error(array('message' => $message));
        }

        // Validate file type
        $allowed_types = array(
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        );
        $file_type = wp_check_filetype($_FILES['file']['name']);
        $mime_type = mime_content_type($_FILES['file']['tmp_name']);

        if (!in_array($mime_type, $allowed_types, true)) {
            wp_send_json_error(array('message' => __('Ungültiger Dateityp. Erlaubt: PDF, DOC, DOCX', PFM_TD)));
        }

        // Validate file size (20 MB max)
        $max_size = 20 * 1024 * 1024;
        if ($_FILES['file']['size'] > $max_size) {
            wp_send_json_error(array('message' => __('Datei ist zu groß. Maximum: 20 MB', PFM_TD)));
        }

        // Get form data
        $reviewer_name = sanitize_text_field($_POST['reviewer_name'] ?? '');
        $reviewer_email = sanitize_email($_POST['reviewer_email'] ?? '');
        $notes = sanitize_textarea_field($_POST['notes'] ?? '');

        // Validate required fields
        if (empty($reviewer_name) || empty($reviewer_email)) {
            wp_send_json_error(array('message' => __('Name und E-Mail sind Pflichtfelder.', PFM_TD)));
        }

        // Determine version type based on token type
        $version_type_map = array(
            PFM_Upload_Token::TYPE_GUTACHTEN => PFM_SharePoint_Uploader::TYPE_GUTACHTEN,
            PFM_Upload_Token::TYPE_REVISION => PFM_SharePoint_Uploader::TYPE_REVISION,
            PFM_Upload_Token::TYPE_AUTOR => PFM_SharePoint_Uploader::TYPE_REVISION, // Authors submit revisions
        );
        $version_type = $version_type_map[$token_data['token_type']] ?? PFM_SharePoint_Uploader::TYPE_GUTACHTEN;

        // Check if SharePoint is configured
        $use_sharepoint = get_post_meta($token_data['publication_id'], '_pfm_sharepoint_enabled', true);
        $use_sharepoint = $use_sharepoint === '' ? $this->uploader->is_available() : (bool) $use_sharepoint;

        if ($use_sharepoint && $this->uploader->is_available()) {
            // Upload to SharePoint
            $result = $this->uploader->upload_publication_file(
                $token_data['publication_id'],
                $_FILES['file'],
                $version_type,
                array(
                    'notes' => $notes,
                    'reviewer_name' => $reviewer_name,
                    'token_id' => $token_data['id'],
                    'uploaded_by' => 0, // External user
                )
            );

            if (is_wp_error($result)) {
                wp_send_json_error(array('message' => $result->get_error_message()));
            }
        } else {
            // Fall back to WordPress media library
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $upload = wp_handle_upload($_FILES['file'], array('test_form' => false));

            if (isset($upload['error'])) {
                wp_send_json_error(array('message' => $upload['error']));
            }

            // Create attachment
            $attachment = array(
                'post_mime_type' => $upload['type'],
                'post_title' => sanitize_file_name($_FILES['file']['name']),
                'post_content' => '',
                'post_status' => 'inherit',
            );

            $attachment_id = wp_insert_attachment($attachment, $upload['file'], $token_data['publication_id']);

            if (is_wp_error($attachment_id)) {
                wp_send_json_error(array('message' => $attachment_id->get_error_message()));
            }

            // Add to file versions
            PFM_File_Manager::add_file_version(
                $token_data['publication_id'],
                $attachment_id,
                $version_type,
                sprintf(__('Hochgeladen von %s via Upload-Link. %s', PFM_TD), $reviewer_name, $notes)
            );

            $result = array(
                'id' => $attachment_id,
                'filename' => $_FILES['file']['name'],
            );
        }

        // Mark token as used
        $this->token_manager->mark_used($token);

        // Update publication status if it's a review
        if ($token_data['token_type'] === PFM_Upload_Token::TYPE_GUTACHTEN) {
            // Optionally update status
            $current_status = get_post_meta($token_data['publication_id'], 'review_status', true);
            if ($current_status === 'under_review') {
                // Could trigger status change or notification here
            }
        }

        // Send notification to editors
        $this->send_upload_notification($token_data, $reviewer_name, $reviewer_email, $notes);

        wp_send_json_success(array(
            'message' => __('Vielen Dank! Ihr Gutachten wurde erfolgreich hochgeladen.', PFM_TD),
            'result' => $result,
        ));
    }

    /**
     * Handle download request
     */
    public function handle_download() {
        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'pfm_reviewer_nonce')) {
            wp_die(__('Sicherheitsüberprüfung fehlgeschlagen.', PFM_TD));
        }

        // Get and validate token
        $token = sanitize_text_field($_GET['token'] ?? '');
        $token_data = $this->token_manager->validate($token);

        if (is_wp_error($token_data)) {
            wp_die($token_data->get_error_message());
        }

        $publication_id = $token_data['publication_id'];
        $source = sanitize_text_field($_GET['source'] ?? 'local');
        $version_id = intval($_GET['version_id'] ?? 0);

        if ($source === 'sharepoint' && $version_id > 0) {
            // Get SharePoint download URL
            $download_url = $this->uploader->get_version_download_url($version_id);

            if (is_wp_error($download_url)) {
                wp_die($download_url->get_error_message());
            }

            wp_redirect($download_url);
            exit;
        } else {
            // Local attachment
            $attachment_id = get_post_meta($publication_id, 'pfm_manuscript_attachment_id', true);

            if (!$attachment_id) {
                wp_die(__('Keine Datei gefunden.', PFM_TD));
            }

            $file_path = get_attached_file($attachment_id);

            if (!file_exists($file_path)) {
                wp_die(__('Datei nicht gefunden.', PFM_TD));
            }

            // Serve file
            $filename = basename($file_path);
            $mime_type = mime_content_type($file_path);

            header('Content-Type: ' . $mime_type);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($file_path));

            readfile($file_path);
            exit;
        }
    }

    /**
     * Send notification email about upload
     *
     * @param array  $token_data
     * @param string $reviewer_name
     * @param string $reviewer_email
     * @param string $notes
     */
    private function send_upload_notification($token_data, $reviewer_name, $reviewer_email, $notes) {
        $publication = get_post($token_data['publication_id']);

        // Get editors
        $editors = pfm_get_editors();

        if (empty($editors)) {
            return;
        }

        $type_labels = array(
            PFM_Upload_Token::TYPE_GUTACHTEN => __('Gutachten', PFM_TD),
            PFM_Upload_Token::TYPE_REVISION => __('Revision', PFM_TD),
            PFM_Upload_Token::TYPE_AUTOR => __('Autoren-Upload', PFM_TD),
        );
        $type_label = $type_labels[$token_data['token_type']] ?? $token_data['token_type'];

        $subject = sprintf(
            __('%s eingereicht: %s', PFM_TD),
            $type_label,
            $publication->post_title
        );

        $message = sprintf(
            __("Ein neues %s wurde für folgende Publikation eingereicht:\n\nTitel: %s\nEingereicht von: %s (%s)\n\nKommentar:\n%s\n\nZur Publikation: %s", PFM_TD),
            $type_label,
            $publication->post_title,
            $reviewer_name,
            $reviewer_email,
            $notes ?: __('(kein Kommentar)', PFM_TD),
            add_query_arg('pfm_id', $token_data['publication_id'], get_permalink($token_data['publication_id']))
        );

        foreach ($editors as $editor) {
            wp_mail($editor->user_email, $subject, $message);
        }
    }
}
