<?php
/**
 * Survey Admin - CRUD operations, question builder
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Survey_Admin {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {}

    /**
     * Render admin page based on current view
     */
    public function render() {
        $view = isset($_GET['view']) ? sanitize_text_field($_GET['view']) : 'list';
        $survey_id = isset($_GET['survey_id']) ? absint($_GET['survey_id']) : 0;

        switch ($view) {
            case 'edit':
                include DGPTM_UMFRAGEN_PATH . 'templates/admin-survey-edit.php';
                break;
            case 'results':
                include DGPTM_UMFRAGEN_PATH . 'templates/admin-survey-results.php';
                break;
            default:
                include DGPTM_UMFRAGEN_PATH . 'templates/admin-survey-list.php';
                break;
        }
    }

    // --- Data helpers ---

    /**
     * Get all surveys
     */
    public function get_surveys($status = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_surveys';

        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT s.*, (SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_survey_responses r WHERE r.survey_id = s.id AND r.status = 'completed') as response_count
                 FROM $table s WHERE s.status = %s ORDER BY s.created_at DESC",
                $status
            ));
        }

        return $wpdb->get_results(
            "SELECT s.*, (SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_survey_responses r WHERE r.survey_id = s.id AND r.status = 'completed') as response_count
             FROM $table s WHERE s.status != 'archived' ORDER BY s.created_at DESC"
        );
    }

    /**
     * Get single survey
     */
    public function get_survey($survey_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dgptm_surveys WHERE id = %d",
            $survey_id
        ));
    }

    /**
     * Get questions for a survey
     */
    public function get_questions($survey_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dgptm_survey_questions WHERE survey_id = %d ORDER BY sort_order ASC",
            $survey_id
        ));
    }

    /**
     * Get responses for a survey
     */
    public function get_responses($survey_id, $status = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_survey_responses';

        if ($status) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table WHERE survey_id = %d AND status = %s ORDER BY started_at DESC",
                $survey_id, $status
            ));
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE survey_id = %d ORDER BY started_at DESC",
            $survey_id
        ));
    }

    /**
     * Get answers for a response
     */
    public function get_answers($response_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}dgptm_survey_answers WHERE response_id = %d",
            $response_id
        ));
    }

    /**
     * Get all answers for a question (for aggregation)
     */
    public function get_question_answers($question_id, $survey_id) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.* FROM {$wpdb->prefix}dgptm_survey_answers a
             INNER JOIN {$wpdb->prefix}dgptm_survey_responses r ON a.response_id = r.id
             WHERE a.question_id = %d AND r.survey_id = %d AND r.status = 'completed'",
            $question_id, $survey_id
        ));
    }

    /**
     * Get response count for survey
     */
    public function get_response_count($survey_id, $status = 'completed') {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_survey_responses WHERE survey_id = %d AND status = %s",
            $survey_id, $status
        ));
    }

    // --- AJAX handlers ---

    public function ajax_save_survey() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');
        if (!DGPTM_Umfragen::user_can_manage_surveys()) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_surveys';

        $survey_id = absint($_POST['survey_id'] ?? 0);
        $data = [
            'title'           => sanitize_text_field($_POST['title'] ?? ''),
            'slug'            => sanitize_title($_POST['slug'] ?? ''),
            'description'     => sanitize_textarea_field($_POST['description'] ?? ''),
            'status'          => sanitize_text_field($_POST['status'] ?? 'draft'),
            'access_mode'     => sanitize_text_field($_POST['access_mode'] ?? 'public'),
            'duplicate_check' => sanitize_text_field($_POST['duplicate_check'] ?? 'cookie_ip'),
            'show_progress'   => absint($_POST['show_progress'] ?? 1),
            'allow_save_resume' => absint($_POST['allow_save_resume'] ?? 0),
            'updated_at'      => current_time('mysql'),
        ];

        // Validate required fields
        if (empty($data['title'])) {
            wp_send_json_error(['message' => 'Titel ist erforderlich']);
        }
        if (empty($data['slug'])) {
            $data['slug'] = sanitize_title($data['title']);
        }

        // Validate status
        $valid_statuses = ['draft', 'active', 'closed', 'archived'];
        if (!in_array($data['status'], $valid_statuses, true)) {
            $data['status'] = 'draft';
        }

        // Validate access_mode
        $valid_modes = ['public', 'token', 'logged_in'];
        if (!in_array($data['access_mode'], $valid_modes, true)) {
            $data['access_mode'] = 'public';
        }

        // Validate duplicate_check
        $valid_checks = ['none', 'cookie', 'ip', 'cookie_ip'];
        if (!in_array($data['duplicate_check'], $valid_checks, true)) {
            $data['duplicate_check'] = 'cookie_ip';
        }

        if ($survey_id) {
            // Update
            // Handle status transitions
            $old = $this->get_survey($survey_id);
            if ($old && $data['status'] === 'closed' && $old->status !== 'closed') {
                $data['closed_at'] = current_time('mysql');
            }

            // Generate survey_token for legacy surveys that don't have one
            if ($old && empty($old->survey_token)) {
                $data['survey_token'] = wp_generate_password(32, false);
            }

            $wpdb->update($table, $data, ['id' => $survey_id]);

            if (function_exists('dgptm_log_info')) {
                dgptm_log_info('Umfrage aktualisiert: ' . $data['title'] . ' (ID: ' . $survey_id . ')', 'umfragen');
            }
        } else {
            // Create
            $data['created_by'] = get_current_user_id();
            $data['created_at'] = current_time('mysql');
            $data['results_token'] = wp_generate_password(32, false);
            $data['survey_token'] = wp_generate_password(32, false);

            // Ensure unique slug
            $base_slug = $data['slug'];
            $counter = 1;
            while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE slug = %s", $data['slug']))) {
                $data['slug'] = $base_slug . '-' . $counter;
                $counter++;
            }

            $wpdb->insert($table, $data);
            $survey_id = $wpdb->insert_id;

            if (function_exists('dgptm_log_info')) {
                dgptm_log_info('Umfrage erstellt: ' . $data['title'] . ' (ID: ' . $survey_id . ')', 'umfragen');
            }
        }

        wp_send_json_success([
            'message'   => 'Umfrage gespeichert',
            'survey_id' => $survey_id,
            'slug'      => $data['slug'],
        ]);
    }

    public function ajax_delete_survey() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');
        if (!DGPTM_Umfragen::user_can_manage_surveys()) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        global $wpdb;
        $survey_id = absint($_POST['survey_id'] ?? 0);
        if (!$survey_id) {
            wp_send_json_error(['message' => 'Keine Umfrage-ID']);
        }

        // Archive instead of hard delete
        $wpdb->update(
            $wpdb->prefix . 'dgptm_surveys',
            ['status' => 'archived', 'updated_at' => current_time('mysql')],
            ['id' => $survey_id]
        );

        if (function_exists('dgptm_log_info')) {
            dgptm_log_info('Umfrage archiviert (ID: ' . $survey_id . ')', 'umfragen');
        }

        wp_send_json_success(['message' => 'Umfrage archiviert']);
    }

    public function ajax_save_questions() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');
        if (!DGPTM_Umfragen::user_can_manage_surveys()) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_survey_questions';
        $survey_id = absint($_POST['survey_id'] ?? 0);

        if (!$survey_id) {
            wp_send_json_error(['message' => 'Keine Umfrage-ID']);
        }

        $questions_json = wp_unslash($_POST['questions'] ?? '[]');
        $questions = json_decode($questions_json, true);

        if (!is_array($questions)) {
            wp_send_json_error(['message' => 'Ungueltige Fragen-Daten']);
        }

        // Get existing question IDs
        $existing_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT id FROM $table WHERE survey_id = %d",
            $survey_id
        ));
        $submitted_ids = [];

        foreach ($questions as $index => $q) {
            $q_id = absint($q['id'] ?? 0);
            $q_data = [
                'survey_id'          => $survey_id,
                'sort_order'         => $index + 1,
                'group_label'        => sanitize_text_field($q['group_label'] ?? ''),
                'question_type'      => sanitize_text_field($q['question_type'] ?? 'text'),
                'question_text'      => sanitize_textarea_field($q['question_text'] ?? ''),
                'description'        => sanitize_textarea_field($q['description'] ?? ''),
                'choices'            => isset($q['choices']) ? wp_json_encode($q['choices']) : null,
                'validation_rules'   => isset($q['validation_rules']) ? wp_json_encode($q['validation_rules']) : null,
                'skip_logic'         => isset($q['skip_logic']) ? wp_json_encode($q['skip_logic']) : null,
                'is_required'        => absint($q['is_required'] ?? 0),
                'parent_question_id' => absint($q['parent_question_id'] ?? 0),
                'parent_answer_value' => sanitize_text_field($q['parent_answer_value'] ?? ''),
            ];

            // Validate question type
            $valid_types = ['radio', 'checkbox', 'text', 'textarea', 'number', 'select', 'matrix', 'file'];
            if (!in_array($q_data['question_type'], $valid_types, true)) {
                $q_data['question_type'] = 'text';
            }

            if ($q_id && in_array($q_id, $existing_ids)) {
                // Update
                $wpdb->update($table, $q_data, ['id' => $q_id]);
                $submitted_ids[] = $q_id;
            } else {
                // Insert
                $wpdb->insert($table, $q_data);
                $submitted_ids[] = $wpdb->insert_id;
            }
        }

        // Delete removed questions
        $to_delete = array_diff($existing_ids, $submitted_ids);
        if (!empty($to_delete)) {
            $placeholders = implode(',', array_fill(0, count($to_delete), '%d'));
            $wpdb->query($wpdb->prepare(
                "DELETE FROM $table WHERE id IN ($placeholders)",
                ...$to_delete
            ));
        }

        // Update survey timestamp
        $wpdb->update(
            $wpdb->prefix . 'dgptm_surveys',
            ['updated_at' => current_time('mysql')],
            ['id' => $survey_id]
        );

        if (function_exists('dgptm_log_info')) {
            dgptm_log_info('Fragen gespeichert fuer Umfrage ID: ' . $survey_id . ' (' . count($questions) . ' Fragen)', 'umfragen');
        }

        wp_send_json_success([
            'message'      => count($questions) . ' Fragen gespeichert',
            'question_ids' => $submitted_ids,
        ]);
    }

    public function ajax_reorder_questions() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');
        if (!DGPTM_Umfragen::user_can_manage_surveys()) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_survey_questions';
        $order = $_POST['order'] ?? [];

        if (!is_array($order)) {
            wp_send_json_error(['message' => 'Ungueltige Daten']);
        }

        foreach ($order as $position => $question_id) {
            $wpdb->update($table, ['sort_order' => absint($position) + 1], ['id' => absint($question_id)]);
        }

        wp_send_json_success(['message' => 'Reihenfolge aktualisiert']);
    }

    public function ajax_delete_response() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');
        if (!DGPTM_Umfragen::user_can_manage_surveys()) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        global $wpdb;
        $response_id = absint($_POST['response_id'] ?? 0);
        if (!$response_id) {
            wp_send_json_error(['message' => 'Keine Antwort-ID']);
        }

        // Delete answers first, then response
        $wpdb->delete($wpdb->prefix . 'dgptm_survey_answers', ['response_id' => $response_id]);
        $wpdb->delete($wpdb->prefix . 'dgptm_survey_responses', ['id' => $response_id]);

        wp_send_json_success(['message' => 'Antwort geloescht']);
    }

    public function ajax_duplicate_survey() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');
        if (!DGPTM_Umfragen::user_can_manage_surveys()) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        global $wpdb;
        $survey_id = absint($_POST['survey_id'] ?? 0);
        if (!$survey_id) {
            wp_send_json_error(['message' => 'Keine Umfrage-ID']);
        }

        $survey = $this->get_survey($survey_id);
        if (!$survey) {
            wp_send_json_error(['message' => 'Umfrage nicht gefunden']);
        }

        $now = current_time('mysql');
        $table_surveys = $wpdb->prefix . 'dgptm_surveys';

        // Find unique slug
        $base_slug = $survey->slug . '-kopie';
        $slug = $base_slug;
        $counter = 1;
        while ($wpdb->get_var($wpdb->prepare("SELECT id FROM $table_surveys WHERE slug = %s", $slug))) {
            $slug = $base_slug . '-' . $counter;
            $counter++;
        }

        // Duplicate survey
        $wpdb->insert($table_surveys, [
            'title'             => $survey->title . ' (Kopie)',
            'slug'              => $slug,
            'description'       => $survey->description,
            'status'            => 'draft',
            'access_mode'       => $survey->access_mode,
            'duplicate_check'   => $survey->duplicate_check,
            'results_token'     => wp_generate_password(32, false),
            'survey_token'      => wp_generate_password(32, false),
            'show_progress'     => $survey->show_progress,
            'allow_save_resume' => $survey->allow_save_resume,
            'created_by'        => get_current_user_id(),
            'created_at'        => $now,
            'updated_at'        => $now,
        ]);
        $new_id = $wpdb->insert_id;

        // Duplicate questions
        $questions = $this->get_questions($survey_id);
        $id_map = [];
        foreach ($questions as $q) {
            $old_id = $q->id;
            $wpdb->insert($wpdb->prefix . 'dgptm_survey_questions', [
                'survey_id'          => $new_id,
                'sort_order'         => $q->sort_order,
                'group_label'        => $q->group_label,
                'question_type'      => $q->question_type,
                'question_text'      => $q->question_text,
                'description'        => $q->description,
                'choices'            => $q->choices,
                'validation_rules'   => $q->validation_rules,
                'skip_logic'         => null, // Reset, will remap below
                'is_required'        => $q->is_required,
                'parent_question_id' => 0, // Reset, will remap below
                'parent_answer_value' => $q->parent_answer_value,
            ]);
            $id_map[$old_id] = $wpdb->insert_id;
        }

        // Remap skip logic + parent question IDs
        foreach ($questions as $q) {
            $new_q_id = isset($id_map[$q->id]) ? $id_map[$q->id] : 0;
            if (!$new_q_id) {
                continue;
            }

            $updates = [];

            // Remap skip logic
            if (!empty($q->skip_logic)) {
                $skip = json_decode($q->skip_logic, true);
                if (is_array($skip)) {
                    foreach ($skip as &$rule) {
                        if (isset($rule['goto_question_id']) && isset($id_map[$rule['goto_question_id']])) {
                            $rule['goto_question_id'] = $id_map[$rule['goto_question_id']];
                        }
                    }
                    unset($rule);
                    $updates['skip_logic'] = wp_json_encode($skip);
                }
            }

            // Remap parent_question_id
            if ($q->parent_question_id && isset($id_map[$q->parent_question_id])) {
                $updates['parent_question_id'] = $id_map[$q->parent_question_id];
            }

            if (!empty($updates)) {
                $wpdb->update(
                    $wpdb->prefix . 'dgptm_survey_questions',
                    $updates,
                    ['id' => $new_q_id]
                );
            }
        }

        if (function_exists('dgptm_log_info')) {
            dgptm_log_info('Umfrage dupliziert: ID ' . $survey_id . ' -> ' . $new_id, 'umfragen');
        }

        wp_send_json_success([
            'message'   => 'Umfrage dupliziert',
            'survey_id' => $new_id,
        ]);
    }
}
