<?php
/**
 * Survey Frontend Editor - Allows users with umfragen permission to manage surveys from the frontend
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('DGPTM_Survey_Frontend_Editor')) {

    class DGPTM_Survey_Frontend_Editor {

        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {}

        /**
         * Render the frontend editor
         */
        public function render($atts) {
            // Permission already checked in shortcode handler
            ob_start();
            include DGPTM_UMFRAGEN_PATH . 'templates/frontend-editor.php';
            return ob_get_clean();
        }

        /**
         * Get surveys for the current user
         * Admins see all, non-admins see own + shared
         */
        public function get_user_surveys() {
            global $wpdb;
            $table = $wpdb->prefix . 'dgptm_surveys';
            $user_id = get_current_user_id();

            if (current_user_can('manage_options')) {
                return $wpdb->get_results(
                    "SELECT s.*, (SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_survey_responses r WHERE r.survey_id = s.id AND r.status = 'completed') as response_count
                     FROM $table s WHERE s.status != 'archived' ORDER BY s.created_at DESC"
                );
            }

            return $wpdb->get_results($wpdb->prepare(
                "SELECT s.*, (SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_survey_responses r WHERE r.survey_id = s.id AND r.status = 'completed') as response_count
                 FROM $table s WHERE s.status != 'archived' AND (s.created_by = %d OR FIND_IN_SET(%d, s.shared_with) > 0) ORDER BY s.created_at DESC",
                $user_id, $user_id
            ));
        }

        /**
         * Get a single survey (with permission check)
         */
        public function get_survey($survey_id) {
            global $wpdb;
            $survey = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$wpdb->prefix}dgptm_surveys WHERE id = %d",
                $survey_id
            ));

            if (!$survey) {
                return null;
            }

            // Admins can edit any survey
            if (current_user_can('manage_options')) {
                return $survey;
            }

            // Owner can edit
            if ((int) $survey->created_by === get_current_user_id()) {
                return $survey;
            }

            // Shared users can edit
            if (self::is_shared_with($survey, get_current_user_id())) {
                return $survey;
            }

            return null;
        }

        /**
         * Check if a survey is shared with a specific user
         */
        public static function is_shared_with($survey, $user_id) {
            if (empty($survey->shared_with)) {
                return false;
            }
            $shared_ids = array_map('intval', array_filter(explode(',', $survey->shared_with)));
            return in_array((int) $user_id, $shared_ids, true);
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
    }
}
