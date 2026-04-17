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

        // Schnell-Status-Update (nur Status aendern, kein volles Formular)
        if (!empty($_POST['_only_status']) && $survey_id) {
            $new_status = sanitize_text_field($_POST['status'] ?? '');
            $valid = ['draft', 'active', 'closed'];
            if (!in_array($new_status, $valid, true)) {
                wp_send_json_error(['message' => 'Ungueltiger Status.']);
            }
            $update = ['status' => $new_status, 'updated_at' => current_time('mysql')];
            if ($new_status === 'active') {
                $update['published_at'] = current_time('mysql');
            }
            $wpdb->update($table, $update, ['id' => $survey_id]);
            wp_send_json_success(['message' => 'Status aktualisiert.', 'survey_id' => $survey_id]);
        }

        $data = [
            'title'           => sanitize_text_field($_POST['title'] ?? ''),
            'slug'            => sanitize_title($_POST['slug'] ?? ''),
            'description'     => sanitize_textarea_field($_POST['description'] ?? ''),
            'status'          => sanitize_text_field($_POST['status'] ?? 'draft'),
            'access_mode'     => sanitize_text_field($_POST['access_mode'] ?? 'public'),
            'duplicate_check' => sanitize_text_field($_POST['duplicate_check'] ?? 'cookie_ip'),
            'show_progress'   => absint($_POST['show_progress'] ?? 1),
            'allow_save_resume' => absint($_POST['allow_save_resume'] ?? 0),
            'completion_text' => wp_kses_post(wp_unslash($_POST['completion_text'] ?? '')),
            'shared_with'    => self::sanitize_shared_with($_POST['shared_with'] ?? ''),
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

        $old_shared_with = '';

        if ($survey_id) {
            // Update
            // Handle status transitions
            $old = $this->get_survey($survey_id);
            $old_shared_with = $old ? ($old->shared_with ?? '') : '';
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

        // Sync umfragen permissions for shared users
        $this->sync_shared_permissions($old_shared_with, $data['shared_with']);

        // If survey was archived via status change, revoke for all shared users
        if ($data['status'] === 'archived' && !empty($data['shared_with'])) {
            $archived_shared = array_filter(array_map('absint', explode(',', $data['shared_with'])));
            foreach ($archived_shared as $shared_uid) {
                $this->maybe_revoke_survey_permission($shared_uid);
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

        // Ownership check for non-admins
        if (!current_user_can('manage_options')) {
            $survey = $this->get_survey($survey_id);
            if (!$survey) {
                wp_send_json_error(['message' => 'Umfrage nicht gefunden']);
            }
            $uid = get_current_user_id();
            if ((int) $survey->created_by !== $uid && !DGPTM_Survey_Frontend_Editor::is_shared_with($survey, $uid)) {
                wp_send_json_error(['message' => 'Keine Berechtigung fuer diese Umfrage']);
            }
        }

        // Get shared users before archiving (for permission revocation)
        $archive_survey = $this->get_survey($survey_id);
        $archive_shared_with = ($archive_survey && !empty($archive_survey->shared_with)) ? $archive_survey->shared_with : '';

        $permanent = !empty($_POST['permanent']);

        if ($permanent) {
            // Endgueltig loeschen: Umfrage + Fragen + Antworten
            $t_surveys   = $wpdb->prefix . 'dgptm_surveys';
            $t_questions = $wpdb->prefix . 'dgptm_survey_questions';
            $t_responses = $wpdb->prefix . 'dgptm_survey_responses';
            $t_answers   = $wpdb->prefix . 'dgptm_survey_answers';

            // Antworten zu Responses dieser Umfrage loeschen
            $response_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT id FROM $t_responses WHERE survey_id = %d", $survey_id
            ));
            if (!empty($response_ids)) {
                $placeholders = implode(',', array_fill(0, count($response_ids), '%d'));
                $wpdb->query($wpdb->prepare(
                    "DELETE FROM $t_answers WHERE response_id IN ($placeholders)", ...$response_ids
                ));
            }

            $wpdb->delete($t_responses, ['survey_id' => $survey_id]);
            $wpdb->delete($t_questions, ['survey_id' => $survey_id]);
            $wpdb->delete($t_surveys, ['id' => $survey_id]);

            if (function_exists('dgptm_log_info')) {
                dgptm_log_info('Umfrage endgueltig geloescht (ID: ' . $survey_id . ')', 'umfragen');
            }

            wp_send_json_success(['message' => 'Umfrage und alle Daten geloescht.']);
        }

        // Archive (Soft-Delete)
        $wpdb->update(
            $wpdb->prefix . 'dgptm_surveys',
            ['status' => 'archived', 'updated_at' => current_time('mysql')],
            ['id' => $survey_id]
        );

        // Revoke auto-granted permissions for shared users if needed
        if ($archive_shared_with) {
            $shared_ids = array_filter(array_map('absint', explode(',', $archive_shared_with)));
            foreach ($shared_ids as $shared_uid) {
                $this->maybe_revoke_survey_permission($shared_uid);
            }
        }

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
                'is_privacy_sensitive' => absint($q['is_privacy_sensitive'] ?? 0),
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
            'completion_text'   => $survey->completion_text,
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
                'is_privacy_sensitive' => $q->is_privacy_sensitive,
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

    /**
     * AJAX: Search users with umfragen permission
     */
    public function ajax_search_users() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');
        if (!DGPTM_Umfragen::user_can_manage_surveys()) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        $search = sanitize_text_field($_POST['search'] ?? '');
        if (strlen($search) < 2) {
            wp_send_json_success(['users' => []]);
        }

        $wp_users = get_users([
            'search'         => '*' . $search . '*',
            'search_columns' => ['user_login', 'user_email', 'display_name'],
            'number'         => 10,
        ]);

        $results = [];
        $current_user_id = get_current_user_id();
        foreach ($wp_users as $u) {
            // Skip self
            if ($u->ID === $current_user_id) {
                continue;
            }
            // Only include users who can manage surveys
            if (!DGPTM_Umfragen::user_can_manage_surveys($u->ID)) {
                continue;
            }
            $results[] = [
                'id'           => $u->ID,
                'display_name' => $u->display_name,
                'user_email'   => $u->user_email,
            ];
        }

        wp_send_json_success(['users' => $results]);
    }

    /**
     * Sanitize shared_with: comma-separated user IDs
     */
    public static function sanitize_shared_with($value) {
        if (empty($value)) {
            return '';
        }
        $ids = array_map('absint', array_filter(explode(',', $value)));
        $ids = array_unique(array_filter($ids));
        return implode(',', $ids);
    }

    // --- Shared permission sync ---

    /**
     * Sync umfragen permission when shared_with changes
     */
    private function sync_shared_permissions($old_shared_str, $new_shared_str) {
        $old_ids = $old_shared_str ? array_filter(array_map('absint', explode(',', $old_shared_str))) : [];
        $new_ids = $new_shared_str ? array_filter(array_map('absint', explode(',', $new_shared_str))) : [];

        // Newly added users -> grant permission
        foreach (array_diff($new_ids, $old_ids) as $uid) {
            $this->grant_survey_permission($uid);
        }

        // Removed users -> maybe revoke
        foreach (array_diff($old_ids, $new_ids) as $uid) {
            $this->maybe_revoke_survey_permission($uid);
        }
    }

    /**
     * Auto-grant umfragen permission for a shared user.
     * Skips users who already have manual (ACF) permission.
     */
    private function grant_survey_permission($user_id) {
        $has_permission = (bool) get_user_meta($user_id, 'umfragen', true);
        $auto_granted = (bool) get_user_meta($user_id, '_umfragen_auto_granted', true);

        // Already has permission and it was NOT auto-granted -> manual, don't touch
        if ($has_permission && !$auto_granted) {
            return;
        }

        update_user_meta($user_id, 'umfragen', '1');
        update_user_meta($user_id, '_umfragen_auto_granted', '1');
    }

    /**
     * Revoke auto-granted permission if user has no more active shared surveys.
     * Never revokes manually granted permissions.
     */
    private function maybe_revoke_survey_permission($user_id) {
        // Only revoke if it was auto-granted
        if (!get_user_meta($user_id, '_umfragen_auto_granted', true)) {
            return;
        }

        // Still has other active shared surveys -> keep
        if ($this->user_has_active_shared_surveys($user_id)) {
            return;
        }

        delete_user_meta($user_id, 'umfragen');
        delete_user_meta($user_id, '_umfragen_auto_granted', '1');
    }

    /**
     * Check if user has any active (non-archived) shared surveys
     */
    private function user_has_active_shared_surveys($user_id) {
        global $wpdb;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_surveys
             WHERE status != 'archived' AND FIND_IN_SET(%d, shared_with) > 0",
            $user_id
        )) > 0;
    }

    /**
     * Aggregate answers for a single question into per-choice counts, numbers, and texts.
     * Handles the "Choice|||Text|||Number" storage format from radio/checkbox with choices_with_text/number.
     *
     * @return array<string, array{count:int, numbers:float[], texts:string[]}>
     */
    public static function aggregate_choice_answers($question, $q_answers) {
        $result = [];

        $init_choice = function ($label) use (&$result) {
            if (!isset($result[$label])) {
                $result[$label] = ['count' => 0, 'numbers' => [], 'texts' => []];
            }
        };

        $parse_value = function ($raw) {
            $parts = explode('|||', $raw, 3);
            return [
                'base'   => $parts[0] ?? '',
                'text'   => $parts[1] ?? '',
                'number' => $parts[2] ?? '',
            ];
        };

        $choices = $question->choices ? json_decode($question->choices, true) : [];
        if (is_array($choices)) {
            foreach ($choices as $c) {
                $init_choice($c);
            }
        }

        foreach ($q_answers as $a) {
            $raw = $a->answer_value;

            if ($question->question_type === 'checkbox') {
                $vals = json_decode($raw, true);
                if (!is_array($vals)) continue;
            } else {
                $vals = [$raw];
            }

            foreach ($vals as $val) {
                $parsed = $parse_value($val);
                $label = $parsed['base'];
                if ($label === '') continue;
                $init_choice($label);
                $result[$label]['count']++;
                if ($parsed['number'] !== '' && is_numeric($parsed['number'])) {
                    $result[$label]['numbers'][] = floatval($parsed['number']);
                }
                if ($parsed['text'] !== '') {
                    $result[$label]['texts'][] = $parsed['text'];
                }
            }
        }

        return $result;
    }

    /**
     * Render aggregated results for a single question.
     * Used by both admin-survey-results.php and public-results.php.
     * Echoes HTML directly.
     */
    public static function render_question_result($q, $q_answers) {
        $choices = $q->choices ? json_decode($q->choices, true) : [];

        switch ($q->question_type) {
            case 'radio':
            case 'select':
            case 'checkbox':
                $per_choice = self::aggregate_choice_answers($q, $q_answers);
                $total = count($q_answers);
                $max_count = 1;
                foreach ($per_choice as $data) {
                    if ($data['count'] > $max_count) $max_count = $data['count'];
                }
                echo '<div class="dgptm-bar-chart">';
                foreach ($per_choice as $label => $data) {
                    $display_label = $label === '__other__' ? 'Sonstiges' : $label;
                    $pct = $max_count > 0 ? round(($data['count'] / $max_count) * 100) : 0;
                    $pct_total = $total > 0 ? round(($data['count'] / $total) * 100) : 0;
                    echo '<div class="dgptm-bar-row">';
                    echo '<span class="dgptm-bar-label" title="' . esc_attr($display_label) . '">' . esc_html($display_label) . '</span>';
                    echo '<div class="dgptm-bar-track"><div class="dgptm-bar-fill" style="width:' . esc_attr($pct) . '%"></div></div>';
                    echo '<span class="dgptm-bar-count">' . esc_html($data['count']) . ' (' . esc_html($pct_total) . '%)</span>';
                    echo '</div>';

                    if (!empty($data['numbers'])) {
                        $nums = $data['numbers'];
                        sort($nums);
                        $n = count($nums);
                        $sum = array_sum($nums);
                        $avg = $sum / $n;
                        $median_idx = (int) floor($n / 2);
                        $median = $n % 2 === 0 ? ($nums[$median_idx - 1] + $nums[$median_idx]) / 2 : $nums[$median_idx];
                        echo '<div class="dgptm-choice-stats">';
                        echo 'n=' . esc_html($n);
                        echo '  Median=' . esc_html(number_format_i18n($median, 1));
                        echo '  &Oslash;=' . esc_html(number_format_i18n($avg, 1));
                        echo '  Min&ndash;Max: ' . esc_html(number_format_i18n(min($nums))) . '&ndash;' . esc_html(number_format_i18n(max($nums)));
                        echo '  &Sigma;=' . esc_html(number_format_i18n($sum));
                        echo '</div>';
                    }

                    if (!empty($data['texts'])) {
                        echo '<details class="dgptm-choice-details">';
                        echo '<summary>' . esc_html(count($data['texts'])) . ' Freitext-Eintrag/Eintr&auml;ge</summary>';
                        echo '<ul class="dgptm-choice-texts">';
                        foreach ($data['texts'] as $t) {
                            echo '<li>' . esc_html($t) . '</li>';
                        }
                        echo '</ul>';
                        echo '</details>';
                    }

                    if (!empty($data['numbers']) && count($data['numbers']) > 0) {
                        $nums_display = $data['numbers'];
                        sort($nums_display);
                        echo '<details class="dgptm-choice-details">';
                        echo '<summary>Zahlen-Einzelwerte anzeigen</summary>';
                        echo '<p class="dgptm-choice-numbers">' . esc_html(implode(', ', array_map('number_format_i18n', $nums_display))) . '</p>';
                        echo '</details>';
                    }
                }
                echo '</div>';
                break;

            case 'number':
                $values = [];
                foreach ($q_answers as $a) {
                    if (is_numeric($a->answer_value)) $values[] = floatval($a->answer_value);
                }
                if (!empty($values)) {
                    sort($values);
                    $sum = array_sum($values);
                    $cnt = count($values);
                    $avg = $sum / $cnt;
                    $median_idx = (int) floor($cnt / 2);
                    $median = $cnt % 2 === 0 ? ($values[$median_idx - 1] + $values[$median_idx]) / 2 : $values[$median_idx];
                    echo '<div class="dgptm-number-stats">';
                    printf('<div class="dgptm-number-stat"><strong>%s</strong><span>Minimum</span></div>', esc_html(number_format_i18n(min($values))));
                    printf('<div class="dgptm-number-stat"><strong>%s</strong><span>Maximum</span></div>', esc_html(number_format_i18n(max($values))));
                    printf('<div class="dgptm-number-stat"><strong>%s</strong><span>Durchschnitt</span></div>', esc_html(number_format_i18n($avg, 1)));
                    printf('<div class="dgptm-number-stat"><strong>%s</strong><span>Median</span></div>', esc_html(number_format_i18n($median, 1)));
                    printf('<div class="dgptm-number-stat"><strong>%s</strong><span>Summe</span></div>', esc_html(number_format_i18n($sum)));
                    echo '</div>';
                } else {
                    echo '<p style="color:#999;">Keine numerischen Antworten.</p>';
                }
                break;

            case 'matrix':
                $rows = isset($choices['rows']) ? $choices['rows'] : [];
                $cols = isset($choices['columns']) ? $choices['columns'] : [];
                $matrix_counts = [];
                foreach ($rows as $r) {
                    $rk = sanitize_title($r);
                    foreach ($cols as $c) $matrix_counts[$rk][$c] = 0;
                }
                foreach ($q_answers as $a) {
                    $vals = json_decode($a->answer_value, true);
                    if (!is_array($vals)) continue;
                    foreach ($vals as $rk => $cv) {
                        if (isset($matrix_counts[$rk][$cv])) $matrix_counts[$rk][$cv]++;
                    }
                }
                $matrix_max = 1;
                foreach ($matrix_counts as $rc) foreach ($rc as $c) if ($c > $matrix_max) $matrix_max = $c;
                echo '<div class="dgptm-matrix-result"><table><thead><tr><th></th>';
                foreach ($cols as $c) echo '<th>' . esc_html($c) . '</th>';
                echo '</tr></thead><tbody>';
                foreach ($rows as $r) {
                    $rk = sanitize_title($r);
                    echo '<tr><th>' . esc_html($r) . '</th>';
                    foreach ($cols as $c) {
                        $v = $matrix_counts[$rk][$c] ?? 0;
                        $intensity = $matrix_max > 0 ? ($v / $matrix_max) * 0.4 : 0;
                        echo '<td class="dgptm-matrix-cell" style="background:rgba(34,113,177,' . $intensity . ')">' . esc_html($v) . '</td>';
                    }
                    echo '</tr>';
                }
                echo '</tbody></table></div>';
                break;

            case 'text':
            case 'textarea':
                echo '<div class="dgptm-text-responses">';
                if (empty($q_answers)) {
                    echo '<p style="color:#999;">Keine Antworten.</p>';
                } else {
                    foreach ($q_answers as $a) {
                        echo '<div class="dgptm-text-response-item">' . esc_html($a->answer_value) . '</div>';
                    }
                }
                echo '</div>';
                break;

            case 'file':
                $file_count = 0;
                foreach ($q_answers as $a) {
                    if ($a->file_ids) {
                        $fids = json_decode($a->file_ids, true);
                        if (is_array($fids)) $file_count += count($fids);
                    }
                }
                echo '<p>' . esc_html($file_count) . ' Datei(en) hochgeladen.</p>';
                break;
        }
    }
}
