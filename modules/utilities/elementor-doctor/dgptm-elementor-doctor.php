<?php
/**
 * Plugin Name: DGPTM - Elementor Doctor
 * Description: Scannt und repariert fehlerhafte Elementor-Seiten mit sicherer Stapelverarbeitung
 * Version: 1.0.0
 * Author: Sebastian Melzer
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent class redeclaration
if (!class_exists('DGPTM_Elementor_Doctor')) {
    class DGPTM_Elementor_Doctor {
        private static $instance = null;
        private $plugin_path;
        private $plugin_url;
        private $scanner;
        private $repair;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->plugin_path = plugin_dir_path(__FILE__);
            $this->plugin_url = plugin_dir_url(__FILE__);

            // Load dependencies
            $this->load_dependencies();

            // Initialize hooks
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

            // AJAX handlers
            add_action('wp_ajax_elementor_doctor_scan_page', [$this, 'ajax_scan_page']);
            add_action('wp_ajax_elementor_doctor_scan_all', [$this, 'ajax_scan_all']);
            add_action('wp_ajax_elementor_doctor_repair_page', [$this, 'ajax_repair_page']);
            add_action('wp_ajax_elementor_doctor_get_backup', [$this, 'ajax_get_backup']);
            add_action('wp_ajax_elementor_doctor_get_all_backups', [$this, 'ajax_get_all_backups']);
            add_action('wp_ajax_elementor_doctor_restore_backup', [$this, 'ajax_restore_backup']);
        }

        private function load_dependencies() {
            require_once $this->plugin_path . 'includes/class-elementor-scanner.php';
            require_once $this->plugin_path . 'includes/class-elementor-repair.php';

            $this->scanner = new DGPTM_Elementor_Scanner();
            $this->repair = new DGPTM_Elementor_Repair();
        }

        public function add_admin_menu() {
            add_submenu_page(
                'tools.php',
                'Elementor Doctor',
                'Elementor Doctor',
                'manage_options',
                'elementor-doctor',
                [$this, 'render_admin_page']
            );
        }

        public function render_admin_page() {
            if (!current_user_can('manage_options')) {
                wp_die(__('Keine Berechtigung für diese Seite.'));
            }

            include $this->plugin_path . 'views/admin-page.php';
        }

        public function enqueue_admin_assets($hook) {
            if ($hook !== 'tools_page_elementor-doctor') {
                return;
            }

            wp_enqueue_style(
                'elementor-doctor-admin',
                $this->plugin_url . 'assets/css/admin.css',
                [],
                '1.0.0'
            );

            wp_enqueue_script(
                'elementor-doctor-admin',
                $this->plugin_url . 'assets/js/admin.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('elementor-doctor-admin', 'elementorDoctorData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('elementor_doctor_nonce'),
                'strings' => [
                    'scanning' => 'Scanne Seite...',
                    'repairing' => 'Repariere Seite...',
                    'success' => 'Erfolgreich',
                    'error' => 'Fehler',
                    'confirmRepair' => 'Möchtest du diese Seite wirklich reparieren? Ein Backup wird automatisch erstellt.',
                    'confirmBatchRepair' => 'Möchtest du alle fehlerhaften Seiten reparieren? Backups werden automatisch erstellt.'
                ]
            ]);
        }

        // AJAX: Scan single page
        public function ajax_scan_page() {
            check_ajax_referer('elementor_doctor_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $post_id = intval($_POST['post_id'] ?? 0);
            if (!$post_id) {
                wp_send_json_error(['message' => 'Ungültige Post-ID']);
            }

            $results = $this->scanner->scan_page($post_id);

            wp_send_json_success([
                'post_id' => $post_id,
                'results' => $results
            ]);
        }

        // AJAX: Scan all pages
        public function ajax_scan_all() {
            check_ajax_referer('elementor_doctor_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $page = intval($_POST['page'] ?? 1);
            $per_page = 10; // Process in batches

            $results = $this->scanner->scan_all_pages($page, $per_page);

            wp_send_json_success($results);
        }

        // AJAX: Repair page
        public function ajax_repair_page() {
            check_ajax_referer('elementor_doctor_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $post_id = intval($_POST['post_id'] ?? 0);
            if (!$post_id) {
                wp_send_json_error(['message' => 'Ungültige Post-ID']);
            }

            // Create backup first
            $backup_id = $this->repair->create_backup($post_id);
            if (is_wp_error($backup_id)) {
                wp_send_json_error(['message' => 'Backup fehlgeschlagen: ' . $backup_id->get_error_message()]);
            }

            // Repair page
            $result = $this->repair->repair_page($post_id);

            if (is_wp_error($result)) {
                wp_send_json_error([
                    'message' => $result->get_error_message(),
                    'backup_id' => $backup_id
                ]);
            }

            wp_send_json_success([
                'message' => 'Seite erfolgreich repariert',
                'backup_id' => $backup_id,
                'repairs' => $result
            ]);
        }

        // AJAX: Get backup list for a specific post
        public function ajax_get_backup() {
            check_ajax_referer('elementor_doctor_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $post_id = intval($_POST['post_id'] ?? 0);
            if (!$post_id) {
                wp_send_json_error(['message' => 'Ungültige Post-ID']);
            }

            $backups = $this->repair->get_backups($post_id);

            wp_send_json_success(['backups' => $backups]);
        }

        // AJAX: Get all backups
        public function ajax_get_all_backups() {
            check_ajax_referer('elementor_doctor_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $backups = $this->repair->get_all_backups();

            wp_send_json_success(['backups' => $backups]);
        }

        // AJAX: Restore backup
        public function ajax_restore_backup() {
            check_ajax_referer('elementor_doctor_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $backup_id = intval($_POST['backup_id'] ?? 0);
            if (!$backup_id) {
                wp_send_json_error(['message' => 'Ungültige Backup-ID']);
            }

            $result = $this->repair->restore_backup($backup_id);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success(['message' => 'Backup erfolgreich wiederhergestellt']);
        }
    }
}

// Prevent double initialization
if (!isset($GLOBALS['dgptm_elementor_doctor_initialized'])) {
    $GLOBALS['dgptm_elementor_doctor_initialized'] = true;
    DGPTM_Elementor_Doctor::get_instance();
}
