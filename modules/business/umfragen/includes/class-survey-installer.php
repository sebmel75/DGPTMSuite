<?php
/**
 * Survey Installer - Database table creation and upgrades
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Survey_Installer {

    public static function install() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_surveys   = $wpdb->prefix . 'dgptm_surveys';
        $table_questions = $wpdb->prefix . 'dgptm_survey_questions';
        $table_responses = $wpdb->prefix . 'dgptm_survey_responses';
        $table_answers   = $wpdb->prefix . 'dgptm_survey_answers';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $sql_surveys = "CREATE TABLE $table_surveys (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            description TEXT,
            status VARCHAR(20) NOT NULL DEFAULT 'draft',
            access_mode VARCHAR(20) NOT NULL DEFAULT 'public',
            duplicate_check VARCHAR(20) NOT NULL DEFAULT 'cookie_ip',
            results_token VARCHAR(64) DEFAULT NULL,
            survey_token VARCHAR(64) DEFAULT NULL,
            show_progress TINYINT(1) NOT NULL DEFAULT 1,
            allow_save_resume TINYINT(1) NOT NULL DEFAULT 0,
            completion_text TEXT DEFAULT NULL,
            shared_with TEXT DEFAULT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            closed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY slug (slug),
            KEY status (status),
            KEY results_token (results_token),
            KEY survey_token (survey_token)
        ) $charset_collate;";

        $sql_questions = "CREATE TABLE $table_questions (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            survey_id BIGINT(20) UNSIGNED NOT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            group_label VARCHAR(255) NOT NULL DEFAULT '',
            question_type VARCHAR(30) NOT NULL,
            question_text TEXT NOT NULL,
            description TEXT,
            choices TEXT,
            validation_rules TEXT,
            skip_logic TEXT,
            is_required TINYINT(1) NOT NULL DEFAULT 0,
            parent_question_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            parent_answer_value VARCHAR(255) NOT NULL DEFAULT '',
            PRIMARY KEY (id),
            KEY survey_id (survey_id),
            KEY sort_order (sort_order)
        ) $charset_collate;";

        $sql_responses = "CREATE TABLE $table_responses (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            survey_id BIGINT(20) UNSIGNED NOT NULL,
            respondent_cookie VARCHAR(64) NOT NULL DEFAULT '',
            respondent_ip VARCHAR(45) NOT NULL DEFAULT '',
            respondent_name VARCHAR(255) NOT NULL DEFAULT '',
            respondent_email VARCHAR(255) NOT NULL DEFAULT '',
            user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'in_progress',
            resume_token VARCHAR(64) DEFAULT NULL,
            started_at DATETIME NOT NULL,
            completed_at DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            KEY survey_id (survey_id),
            KEY respondent_cookie (respondent_cookie),
            KEY resume_token (resume_token)
        ) $charset_collate;";

        $sql_answers = "CREATE TABLE $table_answers (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            response_id BIGINT(20) UNSIGNED NOT NULL,
            question_id BIGINT(20) UNSIGNED NOT NULL,
            answer_value TEXT,
            file_ids TEXT,
            PRIMARY KEY (id),
            KEY response_id (response_id),
            KEY question_id (question_id)
        ) $charset_collate;";

        dbDelta($sql_surveys);
        dbDelta($sql_questions);
        dbDelta($sql_responses);
        dbDelta($sql_answers);

        // ALTER TABLE fallback for columns dbDelta may not add
        self::ensure_columns();

        update_option('dgptm_umfragen_db_version', DGPTM_UMFRAGEN_VERSION);

        if (function_exists('dgptm_log_info')) {
            dgptm_log_info('Datenbanktabellen erstellt/aktualisiert (v' . DGPTM_UMFRAGEN_VERSION . ')', 'umfragen');
        }
    }

    public static function maybe_upgrade() {
        $current = get_option('dgptm_umfragen_db_version', '0');
        if (version_compare($current, DGPTM_UMFRAGEN_VERSION, '>=')) {
            return;
        }

        // Re-run install to pick up schema changes via dbDelta
        self::install();
    }

    /**
     * Fallback: ensure new columns exist via ALTER TABLE
     * (dbDelta sometimes fails to add columns to existing tables)
     */
    private static function ensure_columns() {
        global $wpdb;

        $has = function ($table, $col) use ($wpdb) {
            $rows = $wpdb->get_results("SHOW COLUMNS FROM $table", ARRAY_A);
            if (!$rows) {
                return false;
            }
            foreach ($rows as $r) {
                if ($r['Field'] === $col) {
                    return true;
                }
            }
            return false;
        };

        $surveys   = $wpdb->prefix . 'dgptm_surveys';
        $questions = $wpdb->prefix . 'dgptm_survey_questions';

        if (!$has($surveys, 'completion_text')) {
            $wpdb->query("ALTER TABLE $surveys ADD COLUMN completion_text TEXT DEFAULT NULL");
        }

        if (!$has($surveys, 'shared_with')) {
            $wpdb->query("ALTER TABLE $surveys ADD COLUMN shared_with TEXT DEFAULT NULL");
        }

        if (!$has($surveys, 'survey_token')) {
            $wpdb->query("ALTER TABLE $surveys ADD COLUMN survey_token VARCHAR(64) DEFAULT NULL");
            $wpdb->query("ALTER TABLE $surveys ADD KEY survey_token (survey_token)");
        }

        if (!$has($questions, 'parent_question_id')) {
            $wpdb->query("ALTER TABLE $questions ADD COLUMN parent_question_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0");
        }

        if (!$has($questions, 'parent_answer_value')) {
            $wpdb->query("ALTER TABLE $questions ADD COLUMN parent_answer_value VARCHAR(255) NOT NULL DEFAULT ''");
        }

        // Backfill: generate survey_token for existing surveys that don't have one
        $missing = $wpdb->get_col("SELECT id FROM $surveys WHERE survey_token IS NULL OR survey_token = ''");
        foreach ($missing as $sid) {
            $wpdb->update($surveys, ['survey_token' => wp_generate_password(32, false)], ['id' => $sid]);
        }
    }
}
