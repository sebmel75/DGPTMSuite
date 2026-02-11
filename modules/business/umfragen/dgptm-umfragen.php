<?php
/**
 * Plugin Name: DGPTM Umfragen
 * Description: Generisches Umfrage-Framework mit erweiterten Fragetypen, Skip-Logic, Verschachtelung und Ergebnis-Dashboard
 * Version: 1.1.0
 * Author: Sebastian Melzer
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('DGPTM_UMFRAGEN_VERSION')) {
    define('DGPTM_UMFRAGEN_VERSION', '1.1.0');
}
if (!defined('DGPTM_UMFRAGEN_PATH')) {
    define('DGPTM_UMFRAGEN_PATH', plugin_dir_path(__FILE__));
}
if (!defined('DGPTM_UMFRAGEN_URL')) {
    define('DGPTM_UMFRAGEN_URL', plugin_dir_url(__FILE__));
}

if (!class_exists('DGPTM_Umfragen')) {

    class DGPTM_Umfragen {

        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->load_dependencies();
            $this->init_hooks();
        }

        private function load_dependencies() {
            require_once DGPTM_UMFRAGEN_PATH . 'includes/class-survey-installer.php';
            require_once DGPTM_UMFRAGEN_PATH . 'includes/class-survey-seeder.php';

            if (is_admin()) {
                require_once DGPTM_UMFRAGEN_PATH . 'includes/class-survey-admin.php';
            }

            require_once DGPTM_UMFRAGEN_PATH . 'includes/class-survey-frontend.php';
            require_once DGPTM_UMFRAGEN_PATH . 'includes/class-survey-exporter.php';
            require_once DGPTM_UMFRAGEN_PATH . 'includes/class-survey-frontend-editor.php';
        }

        private function init_hooks() {
            // Database
            register_activation_hook(__FILE__, ['DGPTM_Survey_Installer', 'install']);
            add_action('admin_init', ['DGPTM_Survey_Installer', 'maybe_upgrade']);

            // Ensure tables exist on module load (suite doesn't trigger activation hooks)
            add_action('init', [$this, 'ensure_tables'], 1);

            // Admin menu
            add_action('admin_menu', [$this, 'register_admin_menu']);

            // Shortcodes
            add_action('init', [$this, 'register_shortcodes']);

            // Rewrite rules for public results
            add_action('init', [$this, 'add_rewrite_rules']);
            add_filter('query_vars', [$this, 'add_query_vars']);
            add_action('template_redirect', [$this, 'handle_results_page']);

            // Admin AJAX
            add_action('wp_ajax_dgptm_survey_save', [$this, 'ajax_save_survey']);
            add_action('wp_ajax_dgptm_survey_delete', [$this, 'ajax_delete_survey']);
            add_action('wp_ajax_dgptm_survey_save_questions', [$this, 'ajax_save_questions']);
            add_action('wp_ajax_dgptm_survey_reorder', [$this, 'ajax_reorder_questions']);
            add_action('wp_ajax_dgptm_survey_seed_ecls', [$this, 'ajax_seed_ecls']);
            add_action('wp_ajax_dgptm_survey_export_csv', [$this, 'ajax_export_csv']);
            add_action('wp_ajax_dgptm_survey_export_pdf', [$this, 'ajax_export_pdf']);
            add_action('wp_ajax_dgptm_survey_delete_response', [$this, 'ajax_delete_response']);
            add_action('wp_ajax_dgptm_survey_duplicate', [$this, 'ajax_duplicate_survey']);

            // Public AJAX (nopriv for public surveys)
            add_action('wp_ajax_dgptm_survey_submit', [$this, 'ajax_submit_survey']);
            add_action('wp_ajax_nopriv_dgptm_survey_submit', [$this, 'ajax_submit_survey']);
            add_action('wp_ajax_dgptm_survey_save_progress', [$this, 'ajax_save_progress']);
            add_action('wp_ajax_nopriv_dgptm_survey_save_progress', [$this, 'ajax_save_progress']);
            add_action('wp_ajax_dgptm_survey_upload_file', [$this, 'ajax_upload_file']);
            add_action('wp_ajax_nopriv_dgptm_survey_upload_file', [$this, 'ajax_upload_file']);

            // Admin assets
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

            // File cleanup cron
            add_action('dgptm_survey_cleanup_files', [$this, 'cleanup_old_files']);
            if (!wp_next_scheduled('dgptm_survey_cleanup_files')) {
                wp_schedule_event(time(), 'daily', 'dgptm_survey_cleanup_files');
            }
        }

        // --- Permission helper ---

        /**
         * Check if a user can manage surveys (admin OR ACF umfragen=true)
         */
        public static function user_can_manage_surveys($user_id = null) {
            if (!$user_id) {
                $user_id = get_current_user_id();
            }
            if (!$user_id) {
                return false;
            }
            if (current_user_can('manage_options')) {
                return true;
            }
            if (function_exists('get_field')) {
                return (bool) get_field('umfragen', 'user_' . $user_id);
            }
            return (bool) get_user_meta($user_id, 'umfragen', true);
        }

        /**
         * Ensure DB tables exist (suite loads modules without activation hook)
         */
        public function ensure_tables() {
            if (get_option('dgptm_umfragen_db_version') !== DGPTM_UMFRAGEN_VERSION) {
                DGPTM_Survey_Installer::install();
            }
        }

        public function register_admin_menu() {
            add_submenu_page(
                'dgptm-suite',
                'Umfragen',
                'Umfragen',
                'manage_options',
                'dgptm-umfragen',
                [$this, 'render_admin_page']
            );
        }

        public function render_admin_page() {
            $admin = DGPTM_Survey_Admin::get_instance();
            $admin->render();
        }

        public function register_shortcodes() {
            add_shortcode('dgptm_umfrage', [$this, 'shortcode_survey']);
            add_shortcode('umfrageberechtigung', [$this, 'shortcode_berechtigung']);
            add_shortcode('dgptm_umfrage_editor', [$this, 'shortcode_editor']);
        }

        public function shortcode_survey($atts) {
            $frontend = DGPTM_Survey_Frontend::get_instance();
            return $frontend->render_shortcode($atts);
        }

        /**
         * [umfrageberechtigung] - Returns "1" if user has umfragen permission, "0" otherwise
         */
        public function shortcode_berechtigung($atts) {
            return self::user_can_manage_surveys() ? '1' : '0';
        }

        /**
         * [dgptm_umfrage_editor] - Renders frontend survey editor
         */
        public function shortcode_editor($atts) {
            if (!is_user_logged_in()) {
                return '<p class="dgptm-survey-error">Bitte melden Sie sich an.</p>';
            }
            if (!self::user_can_manage_surveys()) {
                return '<p class="dgptm-survey-error">Kein Zugriff.</p>';
            }

            $this->enqueue_frontend_editor_assets();
            $editor = DGPTM_Survey_Frontend_Editor::get_instance();
            return $editor->render($atts);
        }

        // --- Rewrite rules for public results ---

        public function add_rewrite_rules() {
            add_rewrite_rule(
                '^umfrage-ergebnisse/([a-zA-Z0-9]+)/?$',
                'index.php?dgptm_survey_results=$matches[1]',
                'top'
            );
        }

        public function add_query_vars($vars) {
            $vars[] = 'dgptm_survey_results';
            return $vars;
        }

        public function handle_results_page() {
            $token = get_query_var('dgptm_survey_results');
            if (!$token) {
                return;
            }

            global $wpdb;
            $table = $wpdb->prefix . 'dgptm_surveys';
            $survey = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table WHERE results_token = %s AND status != 'archived'",
                sanitize_text_field($token)
            ));

            if (!$survey) {
                wp_die('Umfrage nicht gefunden.', 'Fehler', ['response' => 404]);
            }

            $this->enqueue_frontend_assets_direct();
            include DGPTM_UMFRAGEN_PATH . 'templates/public-results.php';
            exit;
        }

        // --- Admin AJAX handlers (delegate to admin/frontend classes) ---

        public function ajax_save_survey() {
            DGPTM_Survey_Admin::get_instance()->ajax_save_survey();
        }

        public function ajax_delete_survey() {
            DGPTM_Survey_Admin::get_instance()->ajax_delete_survey();
        }

        public function ajax_save_questions() {
            DGPTM_Survey_Admin::get_instance()->ajax_save_questions();
        }

        public function ajax_reorder_questions() {
            DGPTM_Survey_Admin::get_instance()->ajax_reorder_questions();
        }

        public function ajax_seed_ecls() {
            check_ajax_referer('dgptm_suite_nonce', 'nonce');
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }
            $result = DGPTM_Survey_Seeder::seed_ecls_zentren();
            if ($result) {
                if (function_exists('dgptm_log_info')) {
                    dgptm_log_info('ECLS-Zentren Umfrage angelegt', 'umfragen');
                }
                wp_send_json_success(['message' => 'ECLS-Zentren Umfrage wurde angelegt', 'survey_id' => $result]);
            } else {
                wp_send_json_error(['message' => 'Umfrage existiert bereits oder Fehler beim Anlegen']);
            }
        }

        public function ajax_export_csv() {
            DGPTM_Survey_Exporter::get_instance()->export_csv();
        }

        public function ajax_export_pdf() {
            DGPTM_Survey_Exporter::get_instance()->export_pdf();
        }

        public function ajax_delete_response() {
            DGPTM_Survey_Admin::get_instance()->ajax_delete_response();
        }

        public function ajax_duplicate_survey() {
            DGPTM_Survey_Admin::get_instance()->ajax_duplicate_survey();
        }

        // Public AJAX
        public function ajax_submit_survey() {
            DGPTM_Survey_Frontend::get_instance()->ajax_submit_survey();
        }

        public function ajax_save_progress() {
            DGPTM_Survey_Frontend::get_instance()->ajax_save_progress();
        }

        public function ajax_upload_file() {
            DGPTM_Survey_Frontend::get_instance()->ajax_upload_file();
        }

        // --- Assets ---

        public function enqueue_admin_assets($hook) {
            if (strpos($hook, 'dgptm-umfragen') === false) {
                return;
            }

            wp_enqueue_style(
                'dgptm-umfragen-admin',
                DGPTM_UMFRAGEN_URL . 'assets/css/admin.css',
                [],
                DGPTM_UMFRAGEN_VERSION
            );

            wp_enqueue_script('jquery-ui-sortable');
            wp_enqueue_script(
                'dgptm-umfragen-admin',
                DGPTM_UMFRAGEN_URL . 'assets/js/admin.js',
                ['jquery', 'jquery-ui-sortable'],
                DGPTM_UMFRAGEN_VERSION,
                true
            );

            wp_localize_script('dgptm-umfragen-admin', 'dgptmUmfragen', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('dgptm_suite_nonce'),
                'strings' => [
                    'confirmDelete'   => 'Wirklich loeschen?',
                    'confirmArchive'  => 'Umfrage wirklich archivieren?',
                    'saved'           => 'Gespeichert',
                    'error'           => 'Fehler beim Speichern',
                    'questionAdded'   => 'Frage hinzugefuegt',
                    'questionRemoved' => 'Frage entfernt',
                    'noQuestions'     => 'Bitte mindestens eine Frage hinzufuegen.',
                    'uploadError'     => 'Fehler beim Hochladen',
                ]
            ]);
        }

        /**
         * Enqueue frontend assets (called from shortcode)
         */
        public function enqueue_frontend_assets() {
            wp_enqueue_style(
                'dgptm-umfragen-frontend',
                DGPTM_UMFRAGEN_URL . 'assets/css/frontend.css',
                [],
                DGPTM_UMFRAGEN_VERSION
            );

            wp_enqueue_script(
                'dgptm-umfragen-frontend',
                DGPTM_UMFRAGEN_URL . 'assets/js/frontend.js',
                ['jquery'],
                DGPTM_UMFRAGEN_VERSION,
                true
            );

            wp_localize_script('dgptm-umfragen-frontend', 'dgptmSurvey', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('dgptm_suite_nonce'),
                'strings' => [
                    'required'       => 'Dieses Feld ist erforderlich.',
                    'invalidEmail'   => 'Bitte geben Sie eine gueltige E-Mail-Adresse ein.',
                    'invalidNumber'  => 'Bitte geben Sie eine gueltige Zahl ein.',
                    'minValue'       => 'Mindestwert: ',
                    'maxValue'       => 'Maximalwert: ',
                    'submitting'     => 'Wird gesendet...',
                    'submitted'      => 'Vielen Dank fuer Ihre Teilnahme!',
                    'alreadyDone'    => 'Sie haben diese Umfrage bereits ausgefuellt.',
                    'error'          => 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.',
                    'fileTooBig'     => 'Datei ist zu gross. Maximal 5 MB.',
                    'fileTypeError'  => 'Dateityp nicht erlaubt. Erlaubt: PDF, JPG, PNG.',
                    'progressSaved'  => 'Fortschritt gespeichert.',
                ]
            ]);
        }

        /**
         * Direct enqueue for template_redirect pages (public results)
         */
        private function enqueue_frontend_assets_direct() {
            wp_enqueue_style(
                'dgptm-umfragen-frontend',
                DGPTM_UMFRAGEN_URL . 'assets/css/frontend.css',
                [],
                DGPTM_UMFRAGEN_VERSION
            );
        }

        /**
         * Enqueue frontend editor assets (called from shortcode)
         */
        public function enqueue_frontend_editor_assets() {
            wp_enqueue_script('jquery-ui-sortable');

            wp_enqueue_style(
                'dgptm-umfragen-fe-editor',
                DGPTM_UMFRAGEN_URL . 'assets/css/frontend-editor.css',
                [],
                DGPTM_UMFRAGEN_VERSION
            );

            wp_enqueue_script(
                'dgptm-umfragen-fe-editor',
                DGPTM_UMFRAGEN_URL . 'assets/js/frontend-editor.js',
                ['jquery', 'jquery-ui-sortable'],
                DGPTM_UMFRAGEN_VERSION,
                true
            );

            wp_localize_script('dgptm-umfragen-fe-editor', 'dgptmSurveyEditor', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('dgptm_suite_nonce'),
                'strings' => [
                    'confirmDelete'   => 'Wirklich loeschen?',
                    'confirmArchive'  => 'Umfrage wirklich archivieren?',
                    'saved'           => 'Gespeichert',
                    'error'           => 'Fehler beim Speichern',
                    'questionAdded'   => 'Frage hinzugefuegt',
                    'questionRemoved' => 'Frage entfernt',
                    'noQuestions'     => 'Bitte mindestens eine Frage hinzufuegen.',
                    'linkCopied'     => 'Link kopiert!',
                ]
            ]);
        }

        /**
         * Get survey URL (page-based with token parameter)
         */
        public static function get_survey_url($survey) {
            $token = is_object($survey) ? $survey->survey_token : $survey;
            if (!$token) {
                return '';
            }
            $settings = get_option('dgptm_module_settings_umfragen', []);
            $page_id = !empty($settings['survey_page_id']) ? absint($settings['survey_page_id']) : 0;
            if ($page_id && get_post($page_id)) {
                return add_query_arg('survey', $token, get_permalink($page_id));
            }
            return add_query_arg('survey', $token, home_url('/'));
        }

        /**
         * Cleanup uploaded files older than configured days
         */
        public function cleanup_old_files() {
            $days = 90;
            $settings = get_option('dgptm_module_settings_umfragen', []);
            if (!empty($settings['file_cleanup_days'])) {
                $days = absint($settings['file_cleanup_days']);
            }

            global $wpdb;
            $table = $wpdb->prefix . 'dgptm_survey_answers';
            $cutoff = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));

            $answers = $wpdb->get_results($wpdb->prepare(
                "SELECT a.file_ids FROM $table a
                 INNER JOIN {$wpdb->prefix}dgptm_survey_responses r ON a.response_id = r.id
                 WHERE r.completed_at < %s AND a.file_ids IS NOT NULL AND a.file_ids != ''",
                $cutoff
            ));

            foreach ($answers as $answer) {
                $file_ids = json_decode($answer->file_ids, true);
                if (is_array($file_ids)) {
                    foreach ($file_ids as $id) {
                        wp_delete_attachment(absint($id), true);
                    }
                }
            }

            if (function_exists('dgptm_log_info')) {
                dgptm_log_info('Datei-Cleanup durchgefuehrt (aelter als ' . $days . ' Tage)', 'umfragen');
            }
        }
    }
}

// Prevent double initialization
if (!isset($GLOBALS['dgptm_umfragen_initialized'])) {
    $GLOBALS['dgptm_umfragen_initialized'] = true;
    DGPTM_Umfragen::get_instance();
}
