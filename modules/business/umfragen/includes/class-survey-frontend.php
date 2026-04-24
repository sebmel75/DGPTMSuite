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
        $survey = null;

        if (!empty($atts['slug'])) {
            $survey = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE slug = %s",
                sanitize_text_field($atts['slug'])
            ));
        } elseif (!empty($atts['id'])) {
            $survey = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE id = %d",
                absint($atts['id'])
            ));
        }

        // Token-based lookup from GET parameter
        if (!$survey && isset($_GET['survey'])) {
            $token = sanitize_text_field($_GET['survey']);
            if ($token) {
                $survey = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM $table WHERE survey_token = %s",
                    $token
                ));
            }
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

        // Duplicate check mit Post-Edit-Verzweigung
        if ($this->has_already_responded($survey)) {
            $own = $this->get_own_completed_response($survey);

            // Edit-Modus: wenn Umfrage aktiv, allow_post_edit gesetzt und User bewusst auf "Bearbeiten" geklickt
            if ($own && $survey->status === 'active' && !empty($survey->allow_post_edit) && !empty($_GET['dgptm_edit'])) {
                $resume_data = $this->build_resume_data_from_response($own);
                $edit_mode = true;
                // Frontend-Form unten durchlaufen lassen
            } elseif ($own && $survey->status === 'active' && !empty($survey->allow_post_edit)) {
                // Edit-Prompt: Bestaetigungsseite mit "Antworten bearbeiten"-Button
                return $this->render_edit_prompt($survey, $own);
            } else {
                return '<div class="dgptm-survey-already-done"><p>Sie haben diese Umfrage bereits ausgefuellt. Vielen Dank fuer Ihre Teilnahme!</p></div>';
            }
        } else {
            // Check for resume token (in_progress response)
            $resume_data = $this->get_resume_data($survey);
            $edit_mode = false;
        }

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

    /**
     * Find the current user's own completed response for this survey.
     * Returns the response row or null.
     */
    private function get_own_completed_response($survey) {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_survey_responses';

        // Logged-in: Zuerst nach user_id suchen
        if (is_user_logged_in()) {
            $user_id = get_current_user_id();
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE survey_id = %d AND user_id = %d AND status = 'completed' ORDER BY id DESC LIMIT 1",
                $survey->id, $user_id
            ));
            if ($row) return $row;
        }

        // Cookie-basiert
        $cookie_name = 'dgptm_survey_' . $survey->id;
        $cookie = isset($_COOKIE[$cookie_name]) ? sanitize_text_field($_COOKIE[$cookie_name]) : '';
        if ($cookie) {
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE survey_id = %d AND respondent_cookie = %s AND status = 'completed' ORDER BY id DESC LIMIT 1",
                $survey->id, $cookie
            ));
            if ($row) return $row;
        }

        // IP-basiert (nur wenn duplicate_check dafuer konfiguriert ist)
        if (in_array($survey->duplicate_check, ['ip', 'cookie_ip'], true)) {
            $ip = $this->get_client_ip();
            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE survey_id = %d AND respondent_ip = %s AND status = 'completed' ORDER BY id DESC LIMIT 1",
                $survey->id, $ip
            ));
            if ($row) return $row;
        }

        return null;
    }

    /**
     * Build resume-style prefill data from a completed response.
     */
    private function build_resume_data_from_response($response) {
        global $wpdb;
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
            'answers'     => $data,
        ];
    }

    /**
     * Render the two-step "Antworten bearbeiten"-Prompt for returning respondents.
     */
    private function render_edit_prompt($survey, $response) {
        $completed_human = $response->completed_at
            ? wp_date('d.m.Y H:i', strtotime($response->completed_at))
            : '';
        $edited_human = !empty($response->last_edited_at)
            ? wp_date('d.m.Y H:i', strtotime($response->last_edited_at))
            : '';

        // Edit-Link: dieselbe URL plus ?dgptm_edit=1
        $edit_url = add_query_arg('dgptm_edit', '1');

        $html  = '<div class="dgptm-survey-edit-prompt">';
        $html .= '<p><strong>Sie haben diese Umfrage bereits ausgefuellt'
            . ($completed_human ? ' am ' . esc_html($completed_human) : '')
            . '.</strong></p>';
        if ($edited_human) {
            $html .= '<p class="dgptm-survey-edit-prompt-meta">Zuletzt geaendert am ' . esc_html($edited_human) . '.</p>';
        }
        $html .= '<p>Sie koennen Ihre Antworten bis zur Schliessung der Umfrage nachtraeglich bearbeiten.</p>';
        $html .= '<p><a href="' . esc_url($edit_url) . '" class="dgptm-btn dgptm-btn-primary">Antworten bearbeiten</a></p>';
        $html .= '</div>';
        return $html;
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

        // Duplicate check — mit Edit-Bypass
        $posted_response_id = absint($_POST['response_id'] ?? 0);
        $is_editing_own = false;
        if ($posted_response_id && !empty($survey->allow_post_edit)) {
            $posted_response = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}dgptm_survey_responses WHERE id = %d AND survey_id = %d",
                $posted_response_id, $survey_id
            ));
            if ($posted_response && $this->response_belongs_to_current_user($survey, $posted_response)) {
                $is_editing_own = true;
            }
        }

        if (!$is_editing_own && $this->has_already_responded($survey)) {
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

            // Check if this question is skipped via skip-logic or nesting
            if ($this->is_question_skipped($q, $questions, $answers_raw)) {
                continue;
            }
            if ($this->is_question_nested_hidden($q, $questions, $answers_raw)) {
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

        // Check for existing response (from resume oder edit)
        $response_id = absint($_POST['response_id'] ?? 0);
        $now = current_time('mysql');
        $is_edit_submit = false;

        // Ownership-Check: response_id wird nur akzeptiert, wenn die Response
        // dem aktuellen Nutzer gehoert (via user_id oder cookie).
        if ($response_id) {
            $existing_row = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}dgptm_survey_responses WHERE id = %d AND survey_id = %d",
                $response_id, $survey_id
            ));
            if (!$existing_row || !$this->response_belongs_to_current_user($survey, $existing_row)) {
                // Fremde oder nicht-existente Response-ID — ignorieren, neue Response anlegen
                $response_id = 0;
            }
        }

        if ($response_id) {
            // $existing_row wurde oben bereits geladen, Status daraus nehmen
            $existing_status = $existing_row->status;

            $update_data = [
                'respondent_ip'    => $ip,
                'respondent_name'  => sanitize_text_field($_POST['respondent_name'] ?? ''),
                'respondent_email' => sanitize_email($_POST['respondent_email'] ?? ''),
            ];

            if ($existing_status === 'completed') {
                // Edit-Submit: completed_at unveraendert lassen, last_edited_at setzen
                $update_data['last_edited_at'] = $now;
                $is_edit_submit = true;
            } else {
                // Resume-Submit (in_progress -> completed)
                $update_data['status']       = 'completed';
                $update_data['completed_at'] = $now;
            }

            $wpdb->update($wpdb->prefix . 'dgptm_survey_responses', $update_data, ['id' => $response_id]);
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
            if ($is_edit_submit) {
                dgptm_log_info('Umfrage-Antwort bearbeitet: Survey=' . $survey_id . ', Response=' . $response_id, 'umfragen');
            } else {
                dgptm_log_info('Umfrage-Antwort eingegangen: Survey=' . $survey_id . ', Response=' . $response_id, 'umfragen');
            }
        }

        /**
         * Fires after a survey has been completed and all answers saved.
         *
         * @param int    $survey_id   The survey ID.
         * @param int    $response_id The response ID.
         * @param object $survey      The survey object.
         */
        do_action('dgptm_survey_completed', $survey_id, $response_id, $survey);

        wp_send_json_success([
            'message'     => $is_edit_submit ? 'Ihre Aenderungen wurden gespeichert.' : 'Vielen Dank fuer Ihre Teilnahme!',
            'response_id' => $response_id,
            'edited'      => $is_edit_submit,
        ]);
    }

    /**
     * Check if a response belongs to the current requesting user (via user_id or cookie).
     */
    private function response_belongs_to_current_user($survey, $response) {
        if (is_user_logged_in() && (int)$response->user_id === get_current_user_id()) {
            return true;
        }
        $cookie_name = 'dgptm_survey_' . $survey->id;
        $cookie = isset($_COOKIE[$cookie_name]) ? sanitize_text_field($_COOKIE[$cookie_name]) : '';
        if ($cookie && $cookie === $response->respondent_cookie) {
            return true;
        }
        return false;
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
     * Check if a question is hidden due to nesting (parent chain)
     */
    private function is_question_nested_hidden($question, $all_questions, $answers) {
        if (empty($question->parent_question_id)) {
            return false;
        }

        // Build lookup map
        $q_map = [];
        foreach ($all_questions as $q) {
            $q_map[$q->id] = $q;
        }

        // Walk parent chain (max 10 levels to prevent infinite loops)
        $current = $question;
        for ($i = 0; $i < 10; $i++) {
            if (empty($current->parent_question_id)) {
                return false; // Reached top level, not hidden
            }

            $parent_id = $current->parent_question_id;
            if (!isset($q_map[$parent_id])) {
                return true; // Parent not found, hide
            }

            $parent = $q_map[$parent_id];
            $parent_answer = isset($answers[$parent_id]) ? $answers[$parent_id] : '';
            if (is_array($parent_answer)) {
                $parent_answer = implode(',', $parent_answer);
            }

            // Check if parent answer matches expected value
            if ($parent_answer !== $current->parent_answer_value) {
                return true; // Parent answer doesn't match, hide this question
            }

            // Check if parent itself is nested-hidden
            $current = $parent;
        }

        return false;
    }

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
