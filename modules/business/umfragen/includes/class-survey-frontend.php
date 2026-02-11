<?php
/**
 * Survey Frontend - Shortcode rendering, AJAX submit, duplicate detection
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Survey_Frontend {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Render survey shortcode [dgptm_umfrage id="X"] or [dgptm_umfrage slug="xxx"]
     */
    public function render_shortcode($atts) {
        $atts = shortcode_atts([
            'id'   => 0,
            'slug' => '',
        ], $atts, 'dgptm_umfrage');

        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_surveys';

        if (!empty($atts['slug'])) {
            $survey = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE slug = %s",
                sanitize_text_field($atts['slug'])
            ));
        } else {
            $survey = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                absint($atts['id'])
            ));
        }

        if (!$survey) {
            return '<p class="dgptm-survey-error">Umfrage nicht gefunden.</p>';
        }

        if ($survey->status !== 'active') {
            if ($survey->status === 'closed') {
                return '<div class="dgptm-survey-closed"><p>Diese Umfrage ist geschlossen.</p></div>';
            }
            if ($survey->status === 'draft' && !current_user_can('manage_options')) {
                return '<p class="dgptm-survey-error">Umfrage nicht verfuegbar.</p>';
            }
            // Draft but admin can preview
        }

        // Access check
        if ($survey->access_mode === 'logged_in' && !is_user_logged_in()) {
            return '<div class="dgptm-survey-login-required"><p>Bitte melden Sie sich an, um an dieser Umfrage teilzunehmen.</p></div>';
        }

        // Duplicate check
        if ($this->has_already_responded($survey)) {
            return '<div class="dgptm-survey-already-done"><p>Sie haben diese Umfrage bereits ausgefuellt. Vielen Dank fuer Ihre Teilnahme!</p></div>';
        }

        // Check for resume token
        $resume_data = $this->get_resume_data($survey);

        // Get questions
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dgptm_survey_questions WHERE survey_id = %d ORDER BY sort_order ASC",
            $survey->id
        ));

        if (empty($questions)) {
            return '<p class="dgptm-survey-error">Keine Fragen vorhanden.</p>';
        }

        // Enqueue assets
        DGPTM_Umfragen::get_instance()->enqueue_frontend_assets();

        // Render form
        ob_start();
        include DGPTM_UMFRAGEN_PATH . 'templates/frontend-form.php';
        return ob_get_clean();
    }

    /**
     * Check if user has already responded
     */
    private function has_already_responded($survey) {
        if ($survey->duplicate_check === 'none') {
            return false;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_survey_responses';

        $cookie_name = 'dgptm_survey_' . $survey->id;
        $cookie = isset($_COOKIE[$cookie_name]) ? sanitize_text_field($_COOKIE[$cookie_name]) : '';
        $ip = $this->get_client_ip();

        switch ($survey->duplicate_check) {
            case 'cookie':
                if ($cookie) {
                    return (bool) $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $table WHERE survey_id = %d AND respondent_cookie = %s AND status = 'completed'",
                        $survey->id, $cookie
                    ));
                }
                return false;

            case 'ip':
                return (bool) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table WHERE survey_id = %d AND respondent_ip = %s AND status = 'completed'",
                    $survey->id, $ip
                ));

            case 'cookie_ip':
            default:
                if ($cookie) {
                    $by_cookie = $wpdb->get_var($wpdb->prepare(
                        "SELECT id FROM $table WHERE survey_id = %d AND respondent_cookie = %s AND status = 'completed'",
                        $survey->id, $cookie
                    ));
                    if ($by_cookie) return true;
                }
                return (bool) $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table WHERE survey_id = %d AND respondent_ip = %s AND status = 'completed'",
                    $survey->id, $ip
                ));
        }
    }

    /**
     * Get resume data if available
     */
    private function get_resume_data($survey) {
        if (!$survey->allow_save_resume) {
            return null;
        }

        $cookie_name = 'dgptm_survey_' . $survey->id;
        $cookie = isset($_COOKIE[$cookie_name]) ? sanitize_text_field($_COOKIE[$cookie_name]) : '';

        if (!$cookie) {
            return null;
        }

        global $wpdb;
        $response = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dgptm_survey_responses WHERE survey_id = %d AND respondent_cookie = %s AND status = 'in_progress'",
            $survey->id, $cookie
        ));

        if (!$response) {
            return null;
        }

        $answers = $wpdb->get_results($wpdb->prepare(
            "SELECT question_id, answer_value FROM {$wpdb->prefix}dgptm_survey_answers WHERE response_id = %d",
            $response->id
        ));

        $data = [];
        foreach ($answers as $a) {
            $data[$a->question_id] = $a->answer_value;
        }

        return [
            'response_id' => $response->id,
            'answers'      => $data,
        ];
    }

    // --- AJAX handlers ---

    /**
     * Submit completed survey
     */
    public function ajax_submit_survey() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        global $wpdb;
        $survey_id = absint($_POST['survey_id'] ?? 0);

        if (!$survey_id) {
            wp_send_json_error(['message' => 'Ungueltige Umfrage']);
        }

        $survey = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dgptm_surveys WHERE id = %d AND status = 'active'",
            $survey_id
        ));

        if (!$survey) {
            wp_send_json_error(['message' => 'Umfrage nicht verfuegbar']);
        }

        // Duplicate check
        if ($this->has_already_responded($survey)) {
            wp_send_json_error(['message' => 'Sie haben diese Umfrage bereits ausgefuellt.']);
        }

        // Generate/get cookie
        $cookie_name = 'dgptm_survey_' . $survey->id;
        $cookie = isset($_COOKIE[$cookie_name]) ? sanitize_text_field($_COOKIE[$cookie_name]) : wp_generate_password(32, false);
        $ip = $this->get_client_ip();

        // Get questions for validation
        $questions = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dgptm_survey_questions WHERE survey_id = %d ORDER BY sort_order ASC",
            $survey_id
        ));

        $answers_raw = isset($_POST['answers']) ? $_POST['answers'] : [];
        if (!is_array($answers_raw)) {
            $answers_raw = [];
        }

        // Validate required fields
        foreach ($questions as $q) {
            if (!$q->is_required) {
                continue;
            }

            $val = isset($answers_raw[$q->id]) ? $answers_raw[$q->id] : '';

            // Check if this question is skipped via skip-logic
            if ($this->is_question_skipped($q, $questions, $answers_raw)) {
                continue;
            }

            if ($q->question_type === 'checkbox') {
                if (empty($val) || (is_array($val) && count($val) === 0)) {
                    wp_send_json_error(['message' => 'Bitte beantworten Sie alle Pflichtfragen.', 'question_id' => $q->id]);
                }
            } elseif ($q->question_type === 'matrix') {
                // Matrix validation: at least partially filled
                if (empty($val)) {
                    wp_send_json_error(['message' => 'Bitte beantworten Sie alle Pflichtfragen.', 'question_id' => $q->id]);
                }
            } else {
                $check = is_array($val) ? implode('', $val) : trim($val);
                if ($check === '') {
                    wp_send_json_error(['message' => 'Bitte beantworten Sie alle Pflichtfragen.', 'question_id' => $q->id]);
                }
            }
        }

        // Check for existing in-progress response (from resume)
        $response_id = absint($_POST['response_id'] ?? 0);
        $now = current_time('mysql');

        if ($response_id) {
            // Update existing response
            $wpdb->update(
                $wpdb->prefix . 'dgptm_survey_responses',
                [
                    'status'           => 'completed',
                    'respondent_ip'    => $ip,
                    'respondent_name'  => sanitize_text_field($_POST['respondent_name'] ?? ''),
                    'respondent_email' => sanitize_email($_POST['respondent_email'] ?? ''),
                    'completed_at'     => $now,
                ],
                ['id' => $response_id]
            );
            // Delete old answers before re-inserting
            $wpdb->delete($wpdb->prefix . 'dgptm_survey_answers', ['response_id' => $response_id]);
        } else {
            // Create new response
            $wpdb->insert($wpdb->prefix . 'dgptm_survey_responses', [
                'survey_id'        => $survey_id,
                'respondent_cookie' => $cookie,
                'respondent_ip'    => $ip,
                'respondent_name'  => sanitize_text_field($_POST['respondent_name'] ?? ''),
                'respondent_email' => sanitize_email($_POST['respondent_email'] ?? ''),
                'user_id'          => get_current_user_id(),
                'status'           => 'completed',
                'started_at'       => $now,
                'completed_at'     => $now,
            ]);
            $response_id = $wpdb->insert_id;
        }

        if (!$response_id) {
            wp_send_json_error(['message' => 'Fehler beim Speichern']);
        }

        // Save answers
        foreach ($questions as $q) {
            $val = isset($answers_raw[$q->id]) ? $answers_raw[$q->id] : null;
            if ($val === null || $val === '' || (is_array($val) && empty($val))) {
                continue;
            }

            $answer_value = is_array($val) ? wp_json_encode($val) : sanitize_textarea_field($val);
            $file_ids = null;

            // File uploads are handled separately via AJAX, stored as attachment IDs
            if ($q->question_type === 'file' && is_array($val)) {
                $file_ids = wp_json_encode(array_map('absint', $val));
                $answer_value = null;
            }

            $wpdb->insert($wpdb->prefix . 'dgptm_survey_answers', [
                'response_id'  => $response_id,
                'question_id'  => $q->id,
                'answer_value' => $answer_value,
                'file_ids'     => $file_ids,
            ]);
        }

        // Set cookie (1 year)
        if ($survey->duplicate_check !== 'none') {
            setcookie($cookie_name, $cookie, time() + (365 * 24 * 60 * 60), '/');
        }

        if (function_exists('dgptm_log_info')) {
            dgptm_log_info('Umfrage-Antwort eingegangen: Survey=' . $survey_id . ', Response=' . $response_id, 'umfragen');
        }

        wp_send_json_success([
            'message'     => 'Vielen Dank fuer Ihre Teilnahme!',
            'response_id' => $response_id,
        ]);
    }

    /**
     * Save partial progress
     */
    public function ajax_save_progress() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        global $wpdb;
        $survey_id = absint($_POST['survey_id'] ?? 0);

        if (!$survey_id) {
            wp_send_json_error(['message' => 'Ungueltige Umfrage']);
        }

        $survey = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dgptm_surveys WHERE id = %d",
            $survey_id
        ));

        if (!$survey || !$survey->allow_save_resume) {
            wp_send_json_error(['message' => 'Zwischenspeichern nicht verfuegbar']);
        }

        $cookie_name = 'dgptm_survey_' . $survey->id;
        $cookie = isset($_COOKIE[$cookie_name]) ? sanitize_text_field($_COOKIE[$cookie_name]) : wp_generate_password(32, false);
        $ip = $this->get_client_ip();
        $now = current_time('mysql');

        // Find or create in-progress response
        $response_id = absint($_POST['response_id'] ?? 0);

        if (!$response_id) {
            // Check if we have an existing in-progress response via cookie
            $response_id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}dgptm_survey_responses WHERE survey_id = %d AND respondent_cookie = %s AND status = 'in_progress'",
                $survey_id, $cookie
            ));
        }

        if ($response_id) {
            // Delete old answers for re-saving
            $wpdb->delete($wpdb->prefix . 'dgptm_survey_answers', ['response_id' => $response_id]);
        } else {
            $resume_token = wp_generate_password(32, false);
            $wpdb->insert($wpdb->prefix . 'dgptm_survey_responses', [
                'survey_id'         => $survey_id,
                'respondent_cookie' => $cookie,
                'respondent_ip'     => $ip,
                'user_id'           => get_current_user_id(),
                'status'            => 'in_progress',
                'resume_token'      => $resume_token,
                'started_at'        => $now,
            ]);
            $response_id = $wpdb->insert_id;
        }

        // Save current answers
        $answers_raw = isset($_POST['answers']) ? $_POST['answers'] : [];
        if (is_array($answers_raw)) {
            foreach ($answers_raw as $question_id => $val) {
                if ($val === null || $val === '' || (is_array($val) && empty($val))) {
                    continue;
                }
                $answer_value = is_array($val) ? wp_json_encode($val) : sanitize_textarea_field($val);

                $wpdb->insert($wpdb->prefix . 'dgptm_survey_answers', [
                    'response_id'  => $response_id,
                    'question_id'  => absint($question_id),
                    'answer_value' => $answer_value,
                ]);
            }
        }

        // Set cookie
        setcookie($cookie_name, $cookie, time() + (365 * 24 * 60 * 60), '/');

        wp_send_json_success([
            'message'     => 'Fortschritt gespeichert',
            'response_id' => $response_id,
        ]);
    }

    /**
     * Handle file upload for file-type questions
     */
    public function ajax_upload_file() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (empty($_FILES['file'])) {
            wp_send_json_error(['message' => 'Keine Datei']);
        }

        $file = $_FILES['file'];

        // Check file size (5MB default)
        $max_size = 5 * 1024 * 1024;
        $settings = get_option('dgptm_module_settings_umfragen', []);
        if (!empty($settings['file_upload_max_size'])) {
            $max_size = absint($settings['file_upload_max_size']) * 1024 * 1024;
        }

        if ($file['size'] > $max_size) {
            wp_send_json_error(['message' => 'Datei zu gross']);
        }

        // Allowed MIME types
        $allowed_types = ['image/jpeg', 'image/png', 'application/pdf'];
        if (!in_array($file['type'], $allowed_types, true)) {
            wp_send_json_error(['message' => 'Dateityp nicht erlaubt. Erlaubt: PDF, JPG, PNG.']);
        }

        if (!function_exists('wp_handle_upload')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }
        if (!function_exists('wp_generate_attachment_metadata')) {
            require_once ABSPATH . 'wp-admin/includes/image.php';
        }

        $upload_overrides = [
            'test_form' => false,
            'mimes'     => [
                'jpg|jpeg|jpe' => 'image/jpeg',
                'png'          => 'image/png',
                'pdf'          => 'application/pdf',
            ],
        ];

        $uploaded = wp_handle_upload($file, $upload_overrides);

        if (isset($uploaded['error'])) {
            wp_send_json_error(['message' => $uploaded['error']]);
        }

        // Create WP attachment
        $attachment = [
            'post_mime_type' => $uploaded['type'],
            'post_title'     => sanitize_file_name($file['name']),
            'post_content'   => '',
            'post_status'    => 'inherit',
        ];

        $attach_id = wp_insert_attachment($attachment, $uploaded['file']);
        if (!$attach_id) {
            wp_send_json_error(['message' => 'Fehler beim Erstellen des Anhangs']);
        }

        $metadata = wp_generate_attachment_metadata($attach_id, $uploaded['file']);
        wp_update_attachment_metadata($attach_id, $metadata);

        wp_send_json_success([
            'attachment_id' => $attach_id,
            'filename'      => $file['name'],
            'url'           => $uploaded['url'],
        ]);
    }

    // --- Helpers ---

    /**
     * Check if a question should be skipped based on skip-logic
     */
    private function is_question_skipped($question, $all_questions, $answers) {
        // Check all questions BEFORE this one for skip-logic that jumps PAST this question
        foreach ($all_questions as $q) {
            if ($q->sort_order >= $question->sort_order) {
                break;
            }
            if (empty($q->skip_logic)) {
                continue;
            }
            $skip_rules = json_decode($q->skip_logic, true);
            if (!is_array($skip_rules)) {
                continue;
            }

            $answer = isset($answers[$q->id]) ? $answers[$q->id] : '';
            if (is_array($answer)) {
                $answer = implode(',', $answer);
            }

            foreach ($skip_rules as $rule) {
                if (!isset($rule['if_value']) || !isset($rule['goto_question_id'])) {
                    continue;
                }
                if ($answer === $rule['if_value']) {
                    // Find the goto question's sort_order
                    foreach ($all_questions as $tq) {
                        if ($tq->id == $rule['goto_question_id']) {
                            // If this question is between the skip source and the goto target, it's skipped
                            if ($question->sort_order > $q->sort_order && $question->sort_order < $tq->sort_order) {
                                return true;
                            }
                            break;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * Get client IP address
     */
    private function get_client_ip() {
        $ip = '';
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $ip = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $ip = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return sanitize_text_field($ip);
    }
}
