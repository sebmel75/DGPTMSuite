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

            // Alle Benutzer mit umfragen-Berechtigung sehen alle Umfragen
            return $wpdb->get_results(
                "SELECT s.*, (SELECT COUNT(*) FROM {$wpdb->prefix}dgptm_survey_responses r WHERE r.survey_id = s.id AND r.status = 'completed') as response_count
                 FROM $table s WHERE s.status != 'archived' ORDER BY s.created_at DESC"
            );
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

            // Users with umfragen ACF permission can edit any survey
            if (DGPTM_Umfragen::user_can_manage_surveys()) {
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
