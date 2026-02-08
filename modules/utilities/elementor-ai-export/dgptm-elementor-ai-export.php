<?php
/**
 * Plugin Name: DGPTM - Elementor AI Export
 * Description: Exportiert Elementor-Seiten in ein Claude-freundliches Format für einfache Bearbeitung und Re-Import
 * Version: 1.0.0
 * Author: Sebastian Melzer
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('DGPTM_Elementor_AI_Export')) {
    class DGPTM_Elementor_AI_Export {
        private static $instance = null;
        private $plugin_path;
        private $plugin_url;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->plugin_path = plugin_dir_path(__FILE__);
            $this->plugin_url = plugin_dir_url(__FILE__);

            // Admin menu
            add_action('admin_menu', [$this, 'add_admin_menu']);

            // AJAX handlers
            add_action('wp_ajax_elementor_ai_export_page', [$this, 'ajax_export_page']);
            add_action('wp_ajax_elementor_ai_import_page', [$this, 'ajax_import_page']);
            add_action('wp_ajax_elementor_ai_import_staging', [$this, 'ajax_import_staging']);
            add_action('wp_ajax_elementor_ai_get_pages', [$this, 'ajax_get_pages']);
            add_action('wp_ajax_elementor_ai_get_staging_pages', [$this, 'ajax_get_staging_pages']);
            add_action('wp_ajax_elementor_ai_delete_staging', [$this, 'ajax_delete_staging']);
            add_action('wp_ajax_elementor_ai_apply_staging', [$this, 'ajax_apply_staging']);
            add_action('wp_ajax_elementor_ai_redesign_auto', [$this, 'ajax_redesign_auto']);
            add_action('wp_ajax_elementor_ai_save_settings', [$this, 'ajax_save_settings']);
            add_action('wp_ajax_elementor_ai_test_api', [$this, 'ajax_test_api']);

            // Enqueue admin assets
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

            // Load converter class
            require_once $this->plugin_path . 'includes/class-elementor-converter.php';
            require_once $this->plugin_path . 'includes/class-claude-api.php';
        }

        public function add_admin_menu() {
            add_submenu_page(
                'dgptm-suite',
                'Elementor AI Export',
                'AI Export',
                'manage_options',
                'elementor-ai-export',
                [$this, 'render_admin_page']
            );

            // Settings page
            add_submenu_page(
                'dgptm-suite',
                'AI Export Einstellungen',
                'AI Export Settings',
                'manage_options',
                'elementor-ai-export-settings',
                [$this, 'render_settings_page']
            );
        }

        public function render_settings_page() {
            require_once $this->plugin_path . 'views/settings-page.php';
        }

        public function enqueue_admin_assets($hook) {
            if ('dgptm-suite_page_elementor-ai-export' !== $hook) {
                return;
            }

            wp_enqueue_style(
                'elementor-ai-export-admin',
                $this->plugin_url . 'assets/css/admin.css',
                [],
                '1.0.0'
            );

            wp_enqueue_script(
                'elementor-ai-export-admin',
                $this->plugin_url . 'assets/js/admin.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('elementor-ai-export-admin', 'elementorAiExport', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('elementor_ai_export_nonce')
            ]);
        }

        public function render_admin_page() {
            require_once $this->plugin_path . 'views/admin-page.php';
        }

        public function ajax_get_pages() {
            check_ajax_referer('elementor_ai_export_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $args = [
                'post_type' => ['page', 'post'],
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => '_elementor_edit_mode',
                        'value' => 'builder',
                        'compare' => '='
                    ]
                ],
                'orderby' => 'post_modified',
                'order' => 'DESC'
            ];

            $query = new WP_Query($args);
            $pages = [];

            foreach ($query->posts as $post) {
                $pages[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'type' => $post->post_type,
                    'modified' => get_the_modified_date('Y-m-d H:i:s', $post->ID),
                    'url' => get_permalink($post->ID)
                ];
            }

            wp_send_json_success(['pages' => $pages]);
        }

        public function ajax_export_page() {
            check_ajax_referer('elementor_ai_export_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $page_id = intval($_POST['page_id']);
            $format = sanitize_text_field($_POST['format'] ?? 'markdown');

            if (!$page_id) {
                wp_send_json_error(['message' => 'Ungültige Seiten-ID']);
            }

            // Check if Elementor is active
            if (!did_action('elementor/loaded')) {
                wp_send_json_error(['message' => 'Elementor ist nicht aktiv']);
            }

            $converter = new DGPTM_Elementor_Converter();
            $result = $converter->export_page($page_id, $format);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success($result);
        }

        public function ajax_import_page() {
            check_ajax_referer('elementor_ai_export_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $page_id = intval($_POST['page_id']);
            $content = wp_unslash($_POST['content']);

            if (!$page_id || !$content) {
                wp_send_json_error(['message' => 'Ungültige Daten']);
            }

            // Check if Elementor is active
            if (!did_action('elementor/loaded')) {
                wp_send_json_error(['message' => 'Elementor ist nicht aktiv']);
            }

            $converter = new DGPTM_Elementor_Converter();
            $result = $converter->import_page($page_id, $content);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success(['message' => 'Import erfolgreich']);
        }

        public function ajax_import_staging() {
            check_ajax_referer('elementor_ai_export_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $page_id = intval($_POST['page_id']);
            $content = wp_unslash($_POST['content']);

            if (!$page_id || !$content) {
                wp_send_json_error(['message' => 'Ungültige Daten']);
            }

            // Check if Elementor is active
            if (!did_action('elementor/loaded')) {
                wp_send_json_error(['message' => 'Elementor ist nicht aktiv']);
            }

            $converter = new DGPTM_Elementor_Converter();
            $result = $converter->import_as_staging($page_id, $content);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success([
                'message' => 'Staging-Seite erfolgreich erstellt',
                'staging_id' => $result['staging_id'],
                'staging_url' => get_permalink($result['staging_id']),
                'edit_url' => admin_url('post.php?post=' . $result['staging_id'] . '&action=elementor')
            ]);
        }

        public function ajax_get_staging_pages() {
            check_ajax_referer('elementor_ai_export_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $args = [
                'post_type' => ['page', 'post'],
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => '_elementor_ai_staging',
                        'compare' => 'EXISTS'
                    ]
                ],
                'orderby' => 'post_modified',
                'order' => 'DESC'
            ];

            $query = new WP_Query($args);
            $staging_pages = [];

            foreach ($query->posts as $post) {
                $original_id = get_post_meta($post->ID, '_elementor_ai_staging_original', true);
                $original_post = get_post($original_id);

                $staging_pages[] = [
                    'id' => $post->ID,
                    'title' => $post->post_title,
                    'original_id' => $original_id,
                    'original_title' => $original_post ? $original_post->post_title : 'Unbekannt',
                    'created' => get_the_date('Y-m-d H:i:s', $post->ID),
                    'url' => get_permalink($post->ID),
                    'edit_url' => admin_url('post.php?post=' . $post->ID . '&action=elementor')
                ];
            }

            wp_send_json_success(['staging_pages' => $staging_pages]);
        }

        public function ajax_delete_staging() {
            check_ajax_referer('elementor_ai_export_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $staging_id = intval($_POST['staging_id']);

            if (!$staging_id) {
                wp_send_json_error(['message' => 'Ungültige Staging-ID']);
            }

            // Verify it's a staging page
            $is_staging = get_post_meta($staging_id, '_elementor_ai_staging', true);
            if (!$is_staging) {
                wp_send_json_error(['message' => 'Keine Staging-Seite']);
            }

            // Delete the page
            $result = wp_delete_post($staging_id, true);

            if (!$result) {
                wp_send_json_error(['message' => 'Fehler beim Löschen']);
            }

            wp_send_json_success(['message' => 'Staging-Seite gelöscht']);
        }

        public function ajax_apply_staging() {
            check_ajax_referer('elementor_ai_export_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $staging_id = intval($_POST['staging_id']);

            if (!$staging_id) {
                wp_send_json_error(['message' => 'Ungültige Staging-ID']);
            }

            $converter = new DGPTM_Elementor_Converter();
            $result = $converter->apply_staging_to_original($staging_id);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success([
                'message' => 'Änderungen erfolgreich übernommen',
                'original_url' => get_permalink($result['original_id'])
            ]);
        }

        public function ajax_redesign_auto() {
            check_ajax_referer('elementor_ai_export_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            // Erhöhe PHP Timeout wegen möglicher Rate Limit Wartezeiten (bis zu 3 Minuten)
            set_time_limit(300); // 5 Minuten
            ini_set('max_execution_time', 300);

            $page_id = intval($_POST['page_id']);
            $prompt = sanitize_textarea_field(wp_unslash($_POST['prompt']));
            $duplicate = isset($_POST['duplicate']) && $_POST['duplicate'] === 'true';

            dgptm_log_verbose("Start: Page ID = {$page_id}, Duplicate = " . ($duplicate ? 'yes' : 'no'), 'elementor-ai-export');

            if (!$page_id || !$prompt) {
                wp_send_json_error(['message' => 'Fehlende Parameter']);
            }

            // Check if Elementor is active
            if (!did_action('elementor/loaded')) {
                wp_send_json_error(['message' => 'Elementor ist nicht aktiv']);
            }

            dgptm_log_verbose("Elementor aktiv, starte Export", 'elementor-ai-export');

            // Export original page
            $converter = new DGPTM_Elementor_Converter();
            $export_data = $converter->export_page($page_id, 'json');

            if (is_wp_error($export_data)) {
                dgptm_log_error("Export fehlgeschlagen: " . $export_data->get_error_message(), 'elementor-ai-export');
                wp_send_json_error(['message' => 'Export fehlgeschlagen: ' . $export_data->get_error_message()]);
            }

            dgptm_log_verbose("Export erfolgreich, rufe Claude API", 'elementor-ai-export');

            // Decode JSON
            $page_data = json_decode($export_data['content'], true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                dgptm_log_error("JSON Decode Error: " . json_last_error_msg(), 'elementor-ai-export');
                wp_send_json_error(['message' => 'JSON Decode Fehler: ' . json_last_error_msg()]);
            }

            // Call Claude API
            $claude = new DGPTM_Claude_API();
            dgptm_log_verbose("Claude API Instanz erstellt, sende Request", 'elementor-ai-export');
            $modified_data = $claude->redesign_page($page_data, $prompt);

            if (is_wp_error($modified_data)) {
                wp_send_json_error(['message' => $modified_data->get_error_message()]);
            }

            // Create staging page or duplicate
            if ($duplicate) {
                // Duplicate the page first
                $original_post = get_post($page_id);
                $new_post = [
                    'post_title' => $original_post->post_title . ' (AI Redesign)',
                    'post_content' => $original_post->post_content,
                    'post_status' => 'draft',
                    'post_type' => $original_post->post_type,
                    'post_author' => get_current_user_id()
                ];
                $new_page_id = wp_insert_post($new_post);

                if (is_wp_error($new_page_id)) {
                    wp_send_json_error(['message' => 'Duplizierung fehlgeschlagen']);
                }

                $target_page_id = $new_page_id;
            } else {
                $target_page_id = $page_id;
            }

            // Import to staging
            $result = $converter->import_as_staging($target_page_id, json_encode($modified_data));

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => 'Import fehlgeschlagen: ' . $result->get_error_message()]);
            }

            wp_send_json_success([
                'message' => 'Seite wurde automatisch umgestaltet!',
                'staging_id' => $result['staging_id'],
                'staging_url' => get_permalink($result['staging_id']),
                'edit_url' => admin_url('post.php?post=' . $result['staging_id'] . '&action=elementor')
            ]);
        }

        public function ajax_save_settings() {
            check_ajax_referer('elementor_ai_export_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $api_key = sanitize_text_field($_POST['api_key'] ?? '');

            update_option('elementor_ai_export_claude_api_key', $api_key);

            wp_send_json_success(['message' => 'Einstellungen gespeichert']);
        }

        public function ajax_test_api() {
            check_ajax_referer('elementor_ai_export_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $claude = new DGPTM_Claude_API();
            $result = $claude->test_connection();

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            // Wenn test_connection ein Array zurückgibt (bei Rate Limit), nutze dessen Message
            if (is_array($result) && isset($result['message'])) {
                wp_send_json_success(['message' => $result['message']]);
            }

            wp_send_json_success(['message' => 'Verbindung erfolgreich! API funktioniert.']);
        }
    }
}

// Prevent double initialization
if (!isset($GLOBALS['dgptm_elementor_ai_export_initialized'])) {
    $GLOBALS['dgptm_elementor_ai_export_initialized'] = true;
    DGPTM_Elementor_AI_Export::get_instance();
}
