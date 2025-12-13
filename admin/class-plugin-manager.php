<?php
/**
 * Plugin Manager Admin Interface
 * Hauptverwaltungsseite für DGPTM Plugin Suite
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Plugin_Manager {

    private static $instance = null;

    /**
     * Singleton Instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor
     */
    private function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('admin_init', [$this, 'handle_actions']);
        add_action('wp_ajax_dgptm_toggle_module', [$this, 'ajax_toggle_module']);
        add_action('wp_ajax_dgptm_export_module', [$this, 'ajax_export_module']);
        add_action('wp_ajax_dgptm_get_module_info', [$this, 'ajax_get_module_info']);
        add_action('wp_ajax_dgptm_create_module', [$this, 'ajax_create_module']);
        add_action('wp_ajax_dgptm_test_module', [$this, 'ajax_test_module']);
        add_action('wp_ajax_dgptm_checkout_module', [$this, 'ajax_checkout_module']);
        add_action('wp_ajax_dgptm_checkin_module', [$this, 'ajax_checkin_module']);
        add_action('wp_ajax_dgptm_cancel_checkout', [$this, 'ajax_cancel_checkout']);
        add_action('wp_ajax_dgptm_delete_module', [$this, 'ajax_delete_module']);
        add_action('wp_ajax_dgptm_reinit_module', [$this, 'ajax_reinit_module']);
        add_action('admin_post_dgptm_clear_logs', [$this, 'clear_logs']);

        // Module Metadata AJAX-Handler
        add_action('wp_ajax_dgptm_add_flag', [$this, 'ajax_add_flag']);
        add_action('wp_ajax_dgptm_remove_flag', [$this, 'ajax_remove_flag']);
        add_action('wp_ajax_dgptm_set_comment', [$this, 'ajax_set_comment']);
        add_action('wp_ajax_dgptm_switch_version', [$this, 'ajax_switch_version']);
        add_action('wp_ajax_dgptm_link_test_version', [$this, 'ajax_link_test_version']);
        add_action('wp_ajax_dgptm_repair_flags', [$this, 'ajax_repair_flags']);
        add_action('wp_ajax_dgptm_clear_module_error', [$this, 'ajax_clear_module_error']);

        // Test-Version Management AJAX-Handler
        add_action('wp_ajax_dgptm_create_test_version', [$this, 'ajax_create_test_version']);
        add_action('wp_ajax_dgptm_merge_test_version', [$this, 'ajax_merge_test_version']);
        add_action('wp_ajax_dgptm_delete_test_version', [$this, 'ajax_delete_test_version']);
        add_action('wp_ajax_dgptm_unlink_test_version', [$this, 'ajax_unlink_test_version']);

        // Automatische Log-Bereinigung
        add_action('dgptm_suite_cleanup_logs', [$this, 'cleanup_old_logs']);
        $this->schedule_log_cleanup();
    }

    /**
     * Scheduliere stündliche Log-Bereinigung
     */
    private function schedule_log_cleanup() {
        if (!wp_next_scheduled('dgptm_suite_cleanup_logs')) {
            wp_schedule_event(time(), 'hourly', 'dgptm_suite_cleanup_logs');
            dgptm_log("Automatische Log-Bereinigung aktiviert (stündlich)", 'info');
        }
    }

    /**
     * Entferne geplante Log-Bereinigung (beim Deaktivieren)
     */
    public static function unschedule_log_cleanup() {
        $timestamp = wp_next_scheduled('dgptm_suite_cleanup_logs');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'dgptm_suite_cleanup_logs');
            dgptm_log("Automatische Log-Bereinigung deaktiviert", 'info');
        }
    }

    /**
     * Admin-Menü hinzufügen
     */
    public function add_admin_menu() {
        add_menu_page(
            __('DGPTM Plugin Suite', 'dgptm-suite'),
            __('DGPTM Suite', 'dgptm-suite'),
            'manage_options',
            'dgptm-suite',
            [$this, 'render_dashboard'],
            'dashicons-admin-plugins',
            3
        );

        add_submenu_page(
            'dgptm-suite',
            __('Dashboard', 'dgptm-suite'),
            __('Dashboard', 'dgptm-suite'),
            'manage_options',
            'dgptm-suite',
            [$this, 'render_dashboard']
        );

        add_submenu_page(
            'dgptm-suite',
            __('Module Settings', 'dgptm-suite'),
            __('Module Settings', 'dgptm-suite'),
            'manage_options',
            'dgptm-suite-settings',
            [$this, 'render_settings']
        );

        add_submenu_page(
            'dgptm-suite',
            __('Export', 'dgptm-suite'),
            __('Export', 'dgptm-suite'),
            'manage_options',
            'dgptm-suite-export',
            [$this, 'render_export']
        );

        add_submenu_page(
            'dgptm-suite',
            __('Create New Module', 'dgptm-suite'),
            __('Create New Module', 'dgptm-suite'),
            'manage_options',
            'dgptm-suite-new-module',
            [$this, 'render_new_module']
        );

        add_submenu_page(
            'dgptm-suite',
            __('Categories', 'dgptm-suite'),
            __('Categories', 'dgptm-suite'),
            'manage_options',
            'dgptm-suite-categories',
            [$this, 'render_categories']
        );

        add_submenu_page(
            null, // Hidden from menu
            __('Update Module Categories', 'dgptm-suite'),
            __('Update Module Categories', 'dgptm-suite'),
            'manage_options',
            'dgptm-suite-update-categories',
            [$this, 'render_update_categories']
        );

        add_submenu_page(
            'dgptm-suite',
            __('Module Guides', 'dgptm-suite'),
            __('Module Guides', 'dgptm-suite'),
            'manage_options',
            'dgptm-suite-guides',
            [$this, 'render_guides']
        );

        add_submenu_page(
            'dgptm-suite',
            __('System Logs', 'dgptm-suite'),
            __('System Logs', 'dgptm-suite'),
            'manage_options',
            'dgptm-suite-logs',
            [$this, 'render_logs']
        );
    }

    /**
     * Admin-Assets einbinden
     */
    public function enqueue_admin_assets($hook) {
        if (strpos($hook, 'dgptm-suite') === false) {
            return;
        }

        wp_enqueue_style(
            'dgptm-suite-admin',
            DGPTM_SUITE_URL . 'admin/assets/css/admin.css',
            [],
            DGPTM_SUITE_VERSION
        );

        wp_enqueue_script(
            'dgptm-suite-admin',
            DGPTM_SUITE_URL . 'admin/assets/js/admin.js',
            ['jquery'],
            DGPTM_SUITE_VERSION,
            true
        );

        wp_enqueue_script(
            'dgptm-suite-metadata',
            DGPTM_SUITE_URL . 'admin/assets/js/module-metadata.js',
            ['jquery', 'dgptm-suite-admin'],
            DGPTM_SUITE_VERSION,
            true
        );

        wp_localize_script('dgptm-suite-admin', 'dgptmSuite', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dgptm_suite_nonce'),
            'strings' => [
                'activate' => __('Activate', 'dgptm-suite'),
                'deactivate' => __('Deactivate', 'dgptm-suite'),
                'confirm_deactivate' => __('Are you sure you want to deactivate this module?', 'dgptm-suite'),
                'confirm_export' => __('Export this module as standalone plugin?', 'dgptm-suite'),
                'exporting' => __('Exporting...', 'dgptm-suite'),
                'activating' => __('Activating...', 'dgptm-suite'),
                'deactivating' => __('Deactivating...', 'dgptm-suite'),
            ]
        ]);
    }

    /**
     * Actions behandeln
     */
    public function handle_actions() {
        if (!isset($_GET['page']) || strpos($_GET['page'], 'dgptm-suite') === false) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        // Nonce prüfen
        if (isset($_POST['dgptm_action']) && !isset($_POST['_wpnonce'])) {
            return;
        }

        if (isset($_POST['_wpnonce']) && !wp_verify_nonce($_POST['_wpnonce'], 'dgptm_suite_action')) {
            return;
        }

        // Bulk-Actions
        if (isset($_POST['dgptm_bulk_action']) && isset($_POST['modules'])) {
            $this->handle_bulk_action($_POST['dgptm_bulk_action'], $_POST['modules']);
        }
    }

    /**
     * Bulk-Action behandeln
     */
    private function handle_bulk_action($action, $module_ids) {
        switch ($action) {
            case 'activate':
                foreach ($module_ids as $module_id) {
                    $this->activate_module($module_id);
                }
                $this->add_admin_notice(__('Selected modules have been activated.', 'dgptm-suite'), 'success');
                break;

            case 'deactivate':
                foreach ($module_ids as $module_id) {
                    $this->deactivate_module($module_id);
                }
                $this->add_admin_notice(__('Selected modules have been deactivated.', 'dgptm-suite'), 'success');
                break;

            case 'export':
                $zip_generator = new DGPTM_ZIP_Generator();
                $results = $zip_generator->export_multiple_modules($module_ids);
                $this->add_admin_notice(__('Selected modules have been exported.', 'dgptm-suite'), 'success');
                break;
        }
    }

    /**
     * Modul aktivieren (mit Test-Version Switching)
     */
    private function activate_module($module_id) {
        // SCHUTZ: Prüfe ob Modul fehlerhaft ist und Aktivierung blockiert werden soll
        $safe_loader = DGPTM_Safe_Loader::get_instance();
        $failed_activations = $safe_loader->get_failed_activations();

        if (isset($failed_activations[$module_id])) {
            $error_info = $failed_activations[$module_id];
            $error_age = time() - ($error_info['timestamp'] ?? 0);

            // Blockiere Aktivierung wenn Fehler weniger als 1 Stunde alt ist
            // (außer Admin hat explizit "can_retry" erlaubt)
            if ($error_age < 3600 && empty($error_info['can_retry'])) {
                $error_msg = sprintf(
                    __('Modul "%s" kann nicht aktiviert werden, da es beim letzten Versuch einen kritischen Fehler verursacht hat. Fehler: %s. Bitte beheben Sie den Fehler zuerst oder warten Sie 1 Stunde für einen erneuten Versuch.', 'dgptm-suite'),
                    $module_id,
                    $error_info['error']['message'] ?? $error_info['error'] ?? 'Unbekannt'
                );

                $this->add_admin_notice($error_msg, 'error');
                dgptm_log("Aktivierung blockiert: Modul '$module_id' hat bekannten Fehler (Alter: {$error_age}s)", 'warning');
                return false;
            }

            // Wenn älter als 1 Stunde: Erlaube Retry aber warne
            if ($error_age >= 3600) {
                dgptm_log("Aktivierung erlaubt: Fehler von '$module_id' ist älter als 1 Stunde - Retry erlaubt", 'info');
                $this->add_admin_notice(
                    sprintf(
                        __('Warnung: Modul "%s" hatte beim letzten Versuch einen Fehler. Bitte prüfen Sie die Logs nach erfolgreicher Aktivierung.', 'dgptm-suite'),
                        $module_id
                    ),
                    'warning'
                );
                // Lösche alten Fehler-Eintrag für frischen Versuch
                $safe_loader->clear_module_error($module_id);
            }
        }

        $settings = get_option('dgptm_suite_settings', []);

        if (!isset($settings['active_modules'])) {
            $settings['active_modules'] = [];
        }

        // Hole Modul-Daten
        $module_loader = dgptm_suite()->get_module_loader();
        $all_modules = $module_loader->get_available_modules();

        if (!isset($all_modules[$module_id])) {
            dgptm_log("Aktivierung fehlgeschlagen: Modul '$module_id' nicht gefunden", 'error');
            return false;
        }

        $module_data = $all_modules[$module_id]['config'];
        $test_manager = DGPTM_Test_Version_Manager::get_instance();

        // AUTO-SWITCHING: Vor Aktivierung andere Version deaktivieren
        $switch_result = $test_manager->handle_activation_switch($module_id, $module_data);
        if (is_wp_error($switch_result)) {
            dgptm_log("Switching fehlgeschlagen für '$module_id': " . $switch_result->get_error_message(), 'error');
            $this->add_admin_notice($switch_result->get_error_message(), 'error');
            return false;
        }

        // Aktiviere Modul
        $settings['active_modules'][$module_id] = true;
        $result = update_option('dgptm_suite_settings', $settings);

        // Object Cache explizit löschen (falls vorhanden)
        wp_cache_delete('dgptm_suite_settings', 'options');

        // Debug-Logging
        dgptm_log("Aktivierung von '$module_id' - update_option Result: " . ($result ? 'SUCCESS' : 'FAILED'), 'verbose');
        dgptm_log("Aktive Module nach Speicherung: " . json_encode($settings['active_modules']), 'verbose');

        // Verify - sofort wieder auslesen
        $verify = get_option('dgptm_suite_settings', []);
        dgptm_log("Verification - Aktive Module nach Reload: " . json_encode($verify['active_modules'] ?? []), 'verbose');

        // Bei Fehler: Rollback
        if (!$result) {
            $test_manager->handle_activation_rollback($module_id, $module_data);
            dgptm_log("Aktivierung fehlgeschlagen, Rollback durchgeführt für '$module_id'", 'error');
            return false;
        }

        do_action('dgptm_suite_module_activated', $module_id);

        return $result;
    }

    /**
     * Modul deaktivieren
     */
    private function deactivate_module($module_id) {
        // Prüfe ob Modul als kritisch markiert ist (Flag ODER Config)
        $module_loader = dgptm_suite()->get_module_loader();
        $all_modules = $module_loader->get_available_modules();

        if (isset($all_modules[$module_id])) {
            $module_config = $all_modules[$module_id]['config'];
            $metadata_manager = DGPTM_Module_Metadata_File::get_instance();

            // SCHUTZ: Prüfe ob kritisch (Flag ODER Config)
            if ($metadata_manager->is_module_critical($module_id, $module_config)) {
                $module_name = $module_config['name'] ?? $module_id;
                $this->add_admin_notice(
                    sprintf(
                        __('Modul "%s" ist als kritisch markiert und kann nicht deaktiviert werden. Es ist für den Betrieb der Suite unverzichtbar.', 'dgptm-suite'),
                        $module_name
                    ),
                    'error'
                );
                dgptm_log("Versuch kritisches Modul '$module_id' zu deaktivieren - BLOCKIERT (Flag oder Config)", 'critical');
                return false;
            }
        }

        $dependency_manager = dgptm_suite()->get_dependency_manager();
        $can_deactivate = $dependency_manager->can_deactivate_module($module_id);

        if (!$can_deactivate['can_deactivate']) {
            $this->add_admin_notice($can_deactivate['message'], 'error');
            return false;
        }

        $settings = get_option('dgptm_suite_settings', []);

        if (!isset($settings['active_modules'])) {
            $settings['active_modules'] = [];
        }

        $settings['active_modules'][$module_id] = false;
        $result = update_option('dgptm_suite_settings', $settings);

        // Object Cache explizit löschen (falls vorhanden)
        wp_cache_delete('dgptm_suite_settings', 'options');

        dgptm_log("Deaktivierung von '$module_id' - Result: " . ($result ? 'SUCCESS' : 'FAILED'), 'verbose');

        do_action('dgptm_suite_module_deactivated', $module_id);
        return true;
    }

    /**
     * Admin-Notice hinzufügen
     */
    private function add_admin_notice($message, $type = 'info') {
        add_settings_error('dgptm_suite_notices', 'dgptm_suite_notice', $message, $type);
    }

    /**
     * Dashboard rendern
     */
    public function render_dashboard() {
        require_once DGPTM_SUITE_PATH . 'admin/views/dashboard.php';
    }

    /**
     * Einstellungen rendern
     */
    public function render_settings() {
        require_once DGPTM_SUITE_PATH . 'admin/views/module-settings.php';
    }

    /**
     * Export rendern
     */
    public function render_export() {
        require_once DGPTM_SUITE_PATH . 'admin/views/export.php';
    }

    /**
     * Neues Modul rendern
     */
    public function render_new_module() {
        require_once DGPTM_SUITE_PATH . 'admin/views/new-module.php';
    }

    /**
     * Anleitungen rendern
     */
    public function render_guides() {
        require_once DGPTM_SUITE_PATH . 'admin/views/guides.php';
    }

    /**
     * System Logs rendern
     */
    public function render_logs() {
        require_once DGPTM_SUITE_PATH . 'admin/views/logs.php';
    }

    /**
     * AJAX: Modul aktivieren/deaktivieren
     */
    public function ajax_toggle_module() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dgptm-suite')]);
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');
        $activate = filter_var($_POST['activate'] ?? false, FILTER_VALIDATE_BOOLEAN);

        dgptm_log("AJAX: module_id='$module_id', activate=" . ($activate ? 'true' : 'false'), 'verbose');

        if (empty($module_id)) {
            wp_send_json_error(['message' => __('Invalid module ID.', 'dgptm-suite')]);
        }

        if ($activate) {
            $result = $this->activate_module($module_id);
            if ($result !== false) {
                wp_send_json_success([
                    'message' => __('Module activated.', 'dgptm-suite'),
                    'module_id' => $module_id,
                    'activated' => true
                ]);
            } else {
                wp_send_json_error(['message' => __('Failed to activate module.', 'dgptm-suite')]);
            }
        } else {
            $result = $this->deactivate_module($module_id);
            if ($result) {
                wp_send_json_success(['message' => __('Module deactivated.', 'dgptm-suite')]);
            } else {
                wp_send_json_error(['message' => __('Cannot deactivate module.', 'dgptm-suite')]);
            }
        }
    }

    /**
     * AJAX: Modul exportieren
     */
    public function ajax_export_module() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dgptm-suite')]);
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');

        if (empty($module_id)) {
            wp_send_json_error(['message' => __('Invalid module ID.', 'dgptm-suite')]);
        }

        $zip_generator = new DGPTM_ZIP_Generator();
        $result = $zip_generator->export_module_as_plugin($module_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Modul-Informationen abrufen
     */
    public function ajax_get_module_info() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dgptm-suite')]);
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');

        if (empty($module_id)) {
            wp_send_json_error(['message' => __('Invalid module ID.', 'dgptm-suite')]);
        }

        $module_loader = dgptm_suite()->get_module_loader();
        $config = $module_loader->get_module_config($module_id);

        if (!$config) {
            wp_send_json_error(['message' => __('Module not found.', 'dgptm-suite')]);
        }

        $dependency_manager = dgptm_suite()->get_dependency_manager();
        $dep_info = $dependency_manager->get_module_info($module_id);

        // Hole Metadaten
        $metadata_manager = DGPTM_Module_Metadata_File::get_instance();
        $metadata = $metadata_manager->get_module_metadata($module_id);

        wp_send_json_success([
            'config' => $config,
            'dependencies' => $dep_info,
            'metadata' => $metadata,
        ]);
    }

    /**
     * AJAX: Neues Modul erstellen
     */
    public function ajax_create_module() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dgptm-suite')]);
        }

        $config = [
            'id' => sanitize_text_field($_POST['module_id'] ?? ''),
            'name' => sanitize_text_field($_POST['module_name'] ?? ''),
            'description' => sanitize_textarea_field($_POST['module_description'] ?? ''),
            'version' => sanitize_text_field($_POST['module_version'] ?? '1.0.0'),
            'author' => sanitize_text_field($_POST['module_author'] ?? ''),
            'category' => sanitize_text_field($_POST['module_category'] ?? 'utilities'),
            'main_file' => sanitize_file_name($_POST['main_file'] ?? ''),
            'icon' => sanitize_text_field($_POST['icon'] ?? 'dashicons-admin-plugins'),
            'needs_assets' => !empty($_POST['needs_assets']),
            'needs_includes' => !empty($_POST['needs_includes']),
            'dependencies' => array_map('sanitize_text_field', $_POST['dependencies'] ?? []),
            'wp_plugins' => array_map('sanitize_text_field', $_POST['wp_plugins'] ?? []),
        ];

        // Templates
        $templates = array_map('sanitize_text_field', $_POST['templates'] ?? []);

        $generator = DGPTM_Module_Generator::get_instance();
        $result = $generator->create_module($config);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Templates generieren
        if (!empty($templates)) {
            foreach ($templates as $template_type) {
                $generator->generate_template($template_type, $result['path'], $config);
            }
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Modul testen (ohne zu aktivieren)
     */
    public function ajax_test_module() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dgptm-suite')]);
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');

        if (empty($module_id)) {
            wp_send_json_error(['message' => __('Invalid module ID.', 'dgptm-suite')]);
        }

        $module_loader = dgptm_suite()->get_module_loader();
        $module_path = $module_loader->get_module_path($module_id);
        $config = $module_loader->get_module_config($module_id);

        if (!$module_path || !$config) {
            wp_send_json_error(['message' => __('Module not found.', 'dgptm-suite')]);
        }

        $main_file = $module_path . $config['main_file'];

        $safe_loader = DGPTM_Safe_Loader::get_instance();
        $test_result = $safe_loader->test_load_module($module_id, $main_file);

        if ($test_result['success']) {
            wp_send_json_success([
                'message' => __('Module test successful! No errors detected.', 'dgptm-suite'),
                'warnings' => $test_result['warnings'] ?? [],
            ]);
        } else {
            wp_send_json_error([
                'message' => __('Module test failed:', 'dgptm-suite'),
                'error' => $test_result['error'],
                'details' => $test_result,
            ]);
        }
    }

    /**
     * AJAX: Modul auschecken (Checkout für Update)
     */
    public function ajax_checkout_module() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dgptm-suite')]);
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');

        if (empty($module_id)) {
            wp_send_json_error(['message' => __('Invalid module ID.', 'dgptm-suite')]);
        }

        $checkout_manager = DGPTM_Checkout_Manager::get_instance();
        $result = $checkout_manager->checkout_module($module_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success($result);
    }

    /**
     * AJAX: Modul einchecken (Checkin nach Update)
     */
    public function ajax_checkin_module() {
        // Debug-Logging
        dgptm_log('Checkin AJAX: Request received', 'verbose');
        dgptm_log('POST data: ' . print_r($_POST, true), 'verbose');
        dgptm_log('FILES data: ' . print_r($_FILES, true), 'verbose');

        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            dgptm_log('Checkin AJAX: Permission denied', 'warning');
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dgptm-suite')]);
        }

        // Bei FormData können die Werte auch in $_REQUEST sein
        $checkout_id = sanitize_text_field($_REQUEST['checkout_id'] ?? $_POST['checkout_id'] ?? '');
        dgptm_log('Checkin AJAX: checkout_id = ' . $checkout_id, 'verbose');

        if (empty($checkout_id)) {
            dgptm_log('Checkin AJAX: Missing checkout_id', 'error');
            wp_send_json_error(['message' => __('Missing checkout ID.', 'dgptm-suite')]);
        }

        if (empty($_FILES['module_zip'])) {
            dgptm_log('Checkin AJAX: Missing module_zip file', 'error');
            wp_send_json_error(['message' => __('Missing ZIP file.', 'dgptm-suite')]);
        }

        dgptm_log('Checkin AJAX: Starting checkin process', 'info');

        $checkout_manager = DGPTM_Checkout_Manager::get_instance();
        $result = $checkout_manager->checkin_module($checkout_id, $_FILES['module_zip']);

        if (is_wp_error($result)) {
            dgptm_log('Checkin AJAX: Checkin failed - ' . $result->get_error_message(), 'error');
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'details' => $result->get_error_data()
            ]);
        }

        dgptm_log('Checkin AJAX: Checkin successful', 'info');
        wp_send_json_success($result);
    }

    /**
     * AJAX: Cancel Checkout (ohne Upload)
     */
    public function ajax_cancel_checkout() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'dgptm-suite')]);
        }

        $checkout_id = sanitize_text_field($_POST['checkout_id'] ?? '');

        if (empty($checkout_id)) {
            wp_send_json_error(['message' => __('No checkout ID provided', 'dgptm-suite')]);
        }

        $checkout_manager = DGPTM_Checkout_Manager::get_instance();
        $result = $checkout_manager->cancel_checkout($checkout_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Checkout cancelled successfully', 'dgptm-suite'),
            'checkout_id' => $checkout_id
        ]);
    }

    /**
     * AJAX: Delete Module
     */
    public function ajax_delete_module() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions', 'dgptm-suite')]);
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');

        if (empty($module_id)) {
            wp_send_json_error(['message' => __('No module ID provided', 'dgptm-suite')]);
        }

        // Prüfen ob Modul aktiv ist
        $settings = get_option('dgptm_suite_settings', []);
        $active_modules = $settings['active_modules'] ?? [];

        if (isset($active_modules[$module_id]) && $active_modules[$module_id]) {
            wp_send_json_error(['message' => __('Please deactivate the module before deleting', 'dgptm-suite')]);
        }

        // Modul-Pfad ermitteln
        $module_loader = dgptm_suite()->get_module_loader();
        $available_modules = $module_loader->get_available_modules();

        if (!isset($available_modules[$module_id])) {
            wp_send_json_error(['message' => __('Module not found', 'dgptm-suite')]);
        }

        $module_path = $available_modules[$module_id]['path'];

        // Verzeichnis löschen
        if (file_exists($module_path)) {
            $this->delete_directory_recursive($module_path);
        }

        wp_send_json_success([
            'message' => __('Module deleted successfully', 'dgptm-suite'),
            'module_id' => $module_id
        ]);
    }

    /**
     * AJAX: Modul neu initialisieren
     */
    public function ajax_reinit_module() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'dgptm-suite')]);
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');

        if (empty($module_id)) {
            wp_send_json_error(['message' => __('Keine Modul-ID angegeben', 'dgptm-suite')]);
        }

        dgptm_log("AJAX: Neu-Initialisierung angefordert für Modul: $module_id", 'info');

        $module_loader = dgptm_suite()->get_module_loader();
        $result = $module_loader->reinit_module($module_id);

        if (is_wp_error($result)) {
            dgptm_log("AJAX: Neu-Initialisierung fehlgeschlagen: " . $result->get_error_message(), 'error');
            wp_send_json_error([
                'message' => $result->get_error_message(),
                'error_code' => $result->get_error_code()
            ]);
        }

        dgptm_log("AJAX: Neu-Initialisierung erfolgreich für Modul: $module_id", 'info');
        wp_send_json_success($result);
    }

    /**
     * Rekursiv Verzeichnis löschen
     */
    private function delete_directory_recursive($dir) {
        if (!file_exists($dir)) {
            return false;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!$this->delete_directory_recursive($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    /**
     * Debug-Logs leeren
     */
    public function clear_logs() {
        check_admin_referer('dgptm_clear_logs');

        if (!current_user_can('manage_options')) {
            wp_die(__('Insufficient permissions.', 'dgptm-suite'));
        }

        $debug_log_path = WP_CONTENT_DIR . '/debug.log';

        if (file_exists($debug_log_path)) {
            // Backup erstellen
            $backup_path = WP_CONTENT_DIR . '/debug.log.bak';
            copy($debug_log_path, $backup_path);

            // Log leeren
            file_put_contents($debug_log_path, '');

            dgptm_log("debug.log wurde geleert. Backup: $backup_path", 'info');

            wp_redirect(add_query_arg([
                'page' => 'dgptm-suite-logs',
                'message' => 'log_cleared'
            ], admin_url('admin.php')));
        } else {
            wp_redirect(add_query_arg([
                'page' => 'dgptm-suite-logs',
                'message' => 'log_not_found'
            ], admin_url('admin.php')));
        }
        exit;
    }

    /**
     * Alte nicht-kritische Log-Einträge bereinigen
     * Wird automatisch per WordPress Cron ausgeführt
     */
    public function cleanup_old_logs() {
        $debug_log_path = WP_CONTENT_DIR . '/debug.log';

        if (!file_exists($debug_log_path) || !is_readable($debug_log_path) || !is_writable($debug_log_path)) {
            return;
        }

        // Dateigröße prüfen - nur bereinigen wenn > 1 MB (Performance-Optimierung)
        $file_size = filesize($debug_log_path);
        if ($file_size < 1048576) { // 1 MB
            return;
        }

        // Einstellung laden: Alter in Stunden (Standard: 24 Stunden)
        $settings = get_option('dgptm_suite_settings', []);
        $cleanup_age_hours = isset($settings['log_cleanup_age']) ? absint($settings['log_cleanup_age']) : 24;

        // Mindestens 1 Stunde, maximal 168 Stunden (7 Tage)
        $cleanup_age_hours = max(1, min(168, $cleanup_age_hours));

        dgptm_log("Starte automatische Log-Bereinigung (Dateigröße: " . size_format($file_size) . ", Alter: {$cleanup_age_hours}h)", 'info');

        // Zeitlimit erhöhen für große Dateien
        @set_time_limit(120);

        // Log-Datei einlesen
        $lines = @file($debug_log_path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            dgptm_log("Log-Bereinigung fehlgeschlagen - Datei konnte nicht gelesen werden", 'error');
            return;
        }

        $current_time = time();
        $cutoff_time = $current_time - ($cleanup_age_hours * 3600); // Konfigurierbare Stunden in Sekunden
        $kept_lines = [];
        $removed_count = 0;

        foreach ($lines as $line) {
            // Prüfe ob Zeile kritisch ist
            $is_critical = (
                stripos($line, 'KRITISCH') !== false ||
                stripos($line, 'CRITICAL') !== false ||
                stripos($line, 'Fatal error') !== false ||
                stripos($line, 'PHP Fatal') !== false ||
                stripos($line, 'ERROR') !== false
            );

            // Kritische Einträge immer behalten
            if ($is_critical) {
                $kept_lines[] = $line;
                continue;
            }

            // Versuche Zeitstempel zu extrahieren (WordPress Format: [DD-MMM-YYYY HH:MM:SS UTC])
            if (preg_match('/\[(\d{2}-[A-Za-z]{3}-\d{4} \d{2}:\d{2}:\d{2}[^\]]*)\]/', $line, $matches)) {
                $timestamp_str = $matches[1];
                $log_time = strtotime($timestamp_str);

                if ($log_time !== false) {
                    // Behalte nur Einträge jünger als konfiguriertes Alter
                    if ($log_time >= $cutoff_time) {
                        $kept_lines[] = $line;
                    } else {
                        $removed_count++;
                    }
                } else {
                    // Zeitstempel konnte nicht geparst werden - behalten
                    $kept_lines[] = $line;
                }
            } else {
                // Keine Zeitstempel-Zeile (z.B. mehrzeilige Einträge) - behalten
                $kept_lines[] = $line;
            }
        }

        // Nur schreiben wenn tatsächlich Einträge entfernt wurden
        if ($removed_count > 0) {
            // Backup erstellen vor Änderung
            $backup_path = WP_CONTENT_DIR . '/debug.log.auto-cleanup-' . date('Y-m-d-H-i-s') . '.bak';
            @copy($debug_log_path, $backup_path);

            // Bereinigte Log-Datei schreiben
            $result = @file_put_contents($debug_log_path, implode("\n", $kept_lines) . "\n");

            if ($result !== false) {
                $new_size = filesize($debug_log_path);
                dgptm_log(sprintf(
                    "Log-Bereinigung abgeschlossen - %d alte Einträge entfernt, %d Einträge behalten. Größe: %s → %s. Backup: %s",
                    $removed_count,
                    count($kept_lines),
                    size_format($file_size),
                    size_format($new_size),
                    basename($backup_path)
                ), 'info');

                // Alte Backup-Dateien löschen (nur die letzten 3 behalten)
                $this->cleanup_old_backup_files();
            } else {
                dgptm_log("Log-Bereinigung fehlgeschlagen - Datei konnte nicht geschrieben werden", 'error');
            }
        } else {
            dgptm_log("Log-Bereinigung übersprungen - keine alten Einträge gefunden", 'verbose');
        }
    }

    /**
     * Alte Backup-Dateien bereinigen (nur die letzten 3 behalten)
     */
    private function cleanup_old_backup_files() {
        $backup_pattern = WP_CONTENT_DIR . '/debug.log.auto-cleanup-*.bak';
        $backup_files = glob($backup_pattern);

        if (count($backup_files) > 3) {
            // Nach Änderungszeit sortieren (älteste zuerst)
            usort($backup_files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });

            // Älteste Dateien löschen, nur 3 neueste behalten
            $to_delete = array_slice($backup_files, 0, count($backup_files) - 3);
            foreach ($to_delete as $file) {
                @unlink($file);
            }
        }
    }

    // ===========================================
    // MODULE METADATA AJAX-HANDLER
    // ===========================================

    /**
     * AJAX: Flag zu Modul hinzufügen
     */
    public function ajax_add_flag() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'dgptm-suite')]);
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');
        $flag_type = sanitize_text_field($_POST['flag_type'] ?? '');
        $label = sanitize_text_field($_POST['label'] ?? '');

        if (empty($module_id) || empty($flag_type)) {
            wp_send_json_error(['message' => __('Modul-ID und Flag-Typ erforderlich', 'dgptm-suite')]);
        }

        $metadata = DGPTM_Module_Metadata_File::get_instance();
        $result = $metadata->add_flag($module_id, $flag_type, $label);

        if ($result) {
            wp_send_json_success([
                'message' => __('Flag hinzugefügt', 'dgptm-suite'),
                'flags' => $metadata->get_flags($module_id)
            ]);
        } else {
            wp_send_json_error(['message' => __('Flag existiert bereits', 'dgptm-suite')]);
        }
    }

    /**
     * AJAX: Flag von Modul entfernen
     */
    public function ajax_remove_flag() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'dgptm-suite')]);
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');
        $flag_type = sanitize_text_field($_POST['flag_type'] ?? '');

        if (empty($module_id) || empty($flag_type)) {
            wp_send_json_error(['message' => __('Modul-ID und Flag-Typ erforderlich', 'dgptm-suite')]);
        }

        $metadata = DGPTM_Module_Metadata_File::get_instance();
        $metadata->remove_flag($module_id, $flag_type);

        wp_send_json_success([
            'message' => __('Flag entfernt', 'dgptm-suite'),
            'flags' => $metadata->get_flags($module_id)
        ]);
    }

    /**
     * AJAX: Kommentar setzen
     */
    public function ajax_set_comment() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'dgptm-suite')]);
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');
        $comment = sanitize_textarea_field($_POST['comment'] ?? '');

        if (empty($module_id)) {
            wp_send_json_error(['message' => __('Modul-ID erforderlich', 'dgptm-suite')]);
        }

        $metadata = DGPTM_Module_Metadata_File::get_instance();
        $metadata->set_comment($module_id, $comment);

        wp_send_json_success([
            'message' => __('Kommentar gespeichert', 'dgptm-suite'),
            'comment' => $comment
        ]);
    }

    /**
     * AJAX: Zwischen Haupt- und Test-Version wechseln
     */
    public function ajax_switch_version() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'dgptm-suite')]);
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');

        if (empty($module_id)) {
            wp_send_json_error(['message' => __('Modul-ID erforderlich', 'dgptm-suite')]);
        }

        $metadata = DGPTM_Module_Metadata_File::get_instance();
        $result = $metadata->switch_version($module_id);

        if ($result['success']) {
            wp_send_json_success([
                'message' => sprintf(
                    __('Gewechselt zu %s-Version', 'dgptm-suite'),
                    $result['switched_to'] === 'test' ? 'Test' : 'Haupt'
                ),
                'switched_to' => $result['switched_to'],
                'active_module' => $result['active_module'],
                'inactive_module' => $result['inactive_module']
            ]);
        } else {
            wp_send_json_error(['message' => $result['error']]);
        }
    }

    /**
     * AJAX: Test-Version mit Haupt-Version verknüpfen
     */
    public function ajax_link_test_version() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'dgptm-suite')]);
        }

        $main_module_id = sanitize_text_field($_POST['main_module_id'] ?? '');
        $test_module_id = sanitize_text_field($_POST['test_module_id'] ?? '');

        if (empty($main_module_id) || empty($test_module_id)) {
            wp_send_json_error(['message' => __('Beide Modul-IDs erforderlich', 'dgptm-suite')]);
        }

        $metadata = DGPTM_Module_Metadata_File::get_instance();
        $metadata->link_test_version($main_module_id, $test_module_id);

        wp_send_json_success([
            'message' => __('Test-Version verknüpft', 'dgptm-suite')
        ]);
    }

    /**
     * AJAX: Repariere alle Module mit fehlerhaften Flags
     */
    public function ajax_repair_flags() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'dgptm-suite')]);
        }

        $metadata = DGPTM_Module_Metadata_File::get_instance();
        $result = $metadata->repair_all_module_flags();

        wp_send_json_success([
            'message' => $result['message'],
            'repaired' => $result['repaired'],
            'errors' => $result['errors']
        ]);
    }

    /**
     * AJAX: Testversion erstellen
     */
    public function ajax_create_test_version() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'dgptm-suite')]);
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');

        if (empty($module_id)) {
            wp_send_json_error(['message' => __('Modul-ID erforderlich', 'dgptm-suite')]);
        }

        $test_manager = DGPTM_Test_Version_Manager::get_instance();
        $result = $test_manager->create_test_version($module_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Testversion erfolgreich erstellt', 'dgptm-suite'),
            'test_id' => $result['test_id'],
            'main_id' => $result['main_id']
        ]);
    }

    /**
     * AJAX: Testversion in Hauptversion mergen
     */
    public function ajax_merge_test_version() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'dgptm-suite')]);
        }

        $test_id = sanitize_text_field($_POST['test_id'] ?? '');

        if (empty($test_id)) {
            wp_send_json_error(['message' => __('Test-ID erforderlich', 'dgptm-suite')]);
        }

        $test_manager = DGPTM_Test_Version_Manager::get_instance();
        $result = $test_manager->merge_test_to_main($test_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => $result['message'],
            'main_id' => $result['main_id']
        ]);
    }

    /**
     * AJAX: Testversion löschen
     */
    public function ajax_delete_test_version() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'dgptm-suite')]);
        }

        $test_id = sanitize_text_field($_POST['test_id'] ?? '');

        if (empty($test_id)) {
            wp_send_json_error(['message' => __('Test-ID erforderlich', 'dgptm-suite')]);
        }

        $test_manager = DGPTM_Test_Version_Manager::get_instance();
        $result = $test_manager->delete_test_version($test_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => $result['message']
        ]);
    }

    /**
     * AJAX: Verknüpfung zwischen Haupt- und Testversion trennen
     */
    public function ajax_unlink_test_version() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'dgptm-suite')]);
        }

        $main_id = sanitize_text_field($_POST['main_id'] ?? '');
        $test_id = sanitize_text_field($_POST['test_id'] ?? '');

        if (empty($main_id) || empty($test_id)) {
            wp_send_json_error(['message' => __('Beide IDs erforderlich', 'dgptm-suite')]);
        }

        $test_manager = DGPTM_Test_Version_Manager::get_instance();
        $result = $test_manager->unlink_versions($main_id, $test_id);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Verknüpfung getrennt', 'dgptm-suite')
        ]);
    }

    /**
     * ========================================
     * KATEGORIEN-VERWALTUNG
     * ========================================
     */

    /**
     * Kategorien verwalten
     */
    public function render_categories() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        try {
            // Handle category actions
            if (isset($_POST['dgptm_category_action'])) {
                check_admin_referer('dgptm_category_action');
                $this->handle_category_action();
            }

            $categories = $this->get_categories();
            $modules_by_category = $this->get_modules_by_category();

            error_log('DGPTM render_categories: Categories count = ' . count($categories));
            error_log('DGPTM render_categories: modules_by_category count = ' . count($modules_by_category));
            error_log('DGPTM render_categories: modules_by_category = ' . print_r(array_keys($modules_by_category), true));

            // Check if view file exists
            $view_file = DGPTM_SUITE_PATH . 'admin/views/categories.php';
            if (!file_exists($view_file)) {
                wp_die('Categories view file not found: ' . $view_file);
            }

            include $view_file;
        } catch (Exception $e) {
            error_log('DGPTM Categories Error: ' . $e->getMessage());
            wp_die('Error loading categories page: ' . esc_html($e->getMessage()));
        }
    }

    /**
     * Kategorien aus Datenbank und Modulen abrufen
     */
    private function get_categories() {
        // Kategorien aus Optionen laden
        $stored_categories = get_option('dgptm_suite_categories', []);

        // Standard-Kategorien
        $default_categories = [
            'core-infrastructure' => ['name' => 'Core Infrastructure', 'description' => 'Grundlegende Infrastruktur-Module', 'color' => '#e74c3c'],
            'business' => ['name' => 'Business Logic', 'description' => 'Geschäftslogik-Module', 'color' => '#3498db'],
            'payment' => ['name' => 'Payment', 'description' => 'Zahlungs-Integrationen', 'color' => '#2ecc71'],
            'auth' => ['name' => 'Authentication', 'description' => 'Authentifizierungs-Module', 'color' => '#f39c12'],
            'media' => ['name' => 'Media', 'description' => 'Medien-Verwaltung', 'color' => '#9b59b6'],
            'content' => ['name' => 'Content', 'description' => 'Content-Management', 'color' => '#1abc9c'],
            'acf-tools' => ['name' => 'ACF Tools', 'description' => 'Advanced Custom Fields Werkzeuge', 'color' => '#34495e'],
            'utilities' => ['name' => 'Utilities', 'description' => 'Verschiedene Hilfswerkzeuge', 'color' => '#95a5a6'],
            'uncategorized' => ['name' => 'Uncategorized', 'description' => 'Nicht kategorisiert', 'color' => '#7f8c8d'],
        ];

        // Merge mit gespeicherten Kategorien
        $categories = array_merge($default_categories, $stored_categories);

        // Alphabetisch nach Namen sortieren
        uasort($categories, function($a, $b) {
            return strcmp($a['name'], $b['name']);
        });

        return $categories;
    }

    /**
     * Module nach Kategorien gruppieren
     */
    private function get_modules_by_category() {
        global $dgptm_suite;

        // Fallback: Versuche dgptm_suite() Funktion wenn global nicht verfügbar
        if (!isset($dgptm_suite) && function_exists('dgptm_suite')) {
            $dgptm_suite = dgptm_suite();
        }

        // Wenn module_loader verfügbar ist, verwende ihn
        if (isset($dgptm_suite) && isset($dgptm_suite->module_loader)) {
            $modules = $dgptm_suite->module_loader->get_available_modules();
            error_log('DGPTM Categories: Using module_loader - Found ' . count($modules) . ' modules');
        } else {
            // Fallback: Direkter Scan wenn module_loader nicht verfügbar
            error_log('DGPTM Categories: module_loader not available, using direct scan');
            $modules = $this->scan_modules_direct();
        }

        if (empty($modules)) {
            error_log('DGPTM Categories: No modules found!');
            return [];
        }

        $grouped = [];
        foreach ($modules as $module_id => $module_info) {
            $category = $module_info['category'] ?? 'uncategorized';
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = [
                'id' => $module_id,
                'name' => $module_info['config']['name'] ?? $module_id,
                'description' => $module_info['config']['description'] ?? '',
                'path' => $module_info['path'],
            ];
        }

        // Module innerhalb jeder Kategorie alphabetisch sortieren
        foreach ($grouped as $cat_id => $cat_modules) {
            usort($grouped[$cat_id], function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });
        }

        error_log('DGPTM Categories: Grouped into ' . count($grouped) . ' categories: ' . implode(', ', array_keys($grouped)));
        return $grouped;
    }

    /**
     * Direkt Module scannen (Fallback wenn module_loader nicht verfügbar)
     */
    private function scan_modules_direct() {
        $modules_dir = DGPTM_SUITE_PATH . 'modules/';
        $found_modules = [];

        if (!is_dir($modules_dir)) {
            error_log('DGPTM Categories: Modules directory not found: ' . $modules_dir);
            return [];
        }

        // Scan category folders
        $categories = ['core-infrastructure', 'business', 'payment', 'auth', 'media', 'content', 'acf-tools', 'utilities'];

        foreach ($categories as $category) {
            $category_path = $modules_dir . $category . '/';

            if (!is_dir($category_path)) {
                continue;
            }

            $items = @scandir($category_path);
            if ($items === false) {
                continue;
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || substr($item, -4) === '.zip') {
                    continue;
                }

                $module_path = $category_path . $item . '/';
                $config_file = $module_path . 'module.json';

                if (!is_dir($module_path) || !file_exists($config_file)) {
                    continue;
                }

                $config = @json_decode(file_get_contents($config_file), true);

                if ($config && isset($config['id'])) {
                    $found_modules[$config['id']] = [
                        'path' => $module_path,
                        'category' => $config['category'] ?? $category,
                        'config' => $config,
                    ];
                }
            }
        }

        error_log('DGPTM Categories: Direct scan found ' . count($found_modules) . ' modules');
        return $found_modules;
    }

    /**
     * Kategorien-Aktion verarbeiten
     */
    private function handle_category_action() {
        $action = $_POST['category_action'] ?? '';

        switch ($action) {
            case 'add':
                $this->add_category();
                break;
            case 'edit':
                $this->edit_category();
                break;
            case 'delete':
                $this->delete_category();
                break;
            case 'move_module':
                $this->move_module_to_category();
                break;
        }
    }

    /**
     * Kategorie hinzufügen
     */
    private function add_category() {
        $category_id = sanitize_key($_POST['category_id'] ?? '');
        $category_name = sanitize_text_field($_POST['category_name'] ?? '');
        $category_description = sanitize_textarea_field($_POST['category_description'] ?? '');
        $category_color = sanitize_hex_color($_POST['category_color'] ?? '#3498db');

        if (empty($category_id) || empty($category_name)) {
            add_settings_error('dgptm_categories', 'invalid_input', 'Category ID and name are required.');
            return;
        }

        $categories = get_option('dgptm_suite_categories', []);
        $categories[$category_id] = [
            'name' => $category_name,
            'description' => $category_description,
            'color' => $category_color,
        ];

        update_option('dgptm_suite_categories', $categories);
        add_settings_error('dgptm_categories', 'category_added', 'Category added successfully.', 'success');
    }

    /**
     * Kategorie bearbeiten
     */
    private function edit_category() {
        $category_id = sanitize_key($_POST['category_id'] ?? '');
        $category_name = sanitize_text_field($_POST['category_name'] ?? '');
        $category_description = sanitize_textarea_field($_POST['category_description'] ?? '');
        $category_color = sanitize_hex_color($_POST['category_color'] ?? '#3498db');

        if (empty($category_id)) {
            add_settings_error('dgptm_categories', 'invalid_input', 'Category ID is required.');
            return;
        }

        $categories = get_option('dgptm_suite_categories', []);
        if (isset($categories[$category_id])) {
            $categories[$category_id] = [
                'name' => $category_name,
                'description' => $category_description,
                'color' => $category_color,
            ];
            update_option('dgptm_suite_categories', $categories);
            add_settings_error('dgptm_categories', 'category_updated', 'Category updated successfully.', 'success');
        }
    }

    /**
     * Kategorie löschen
     */
    private function delete_category() {
        $category_id = sanitize_key($_POST['category_id'] ?? '');

        if (empty($category_id)) {
            add_settings_error('dgptm_categories', 'invalid_input', 'Category ID is required.');
            return;
        }

        // Prevent deletion of default categories
        $default_categories = ['core-infrastructure', 'business', 'payment', 'auth', 'media', 'content', 'acf-tools', 'utilities'];
        if (in_array($category_id, $default_categories)) {
            add_settings_error('dgptm_categories', 'cannot_delete_default', 'Cannot delete default category.');
            return;
        }

        $categories = get_option('dgptm_suite_categories', []);
        if (isset($categories[$category_id])) {
            unset($categories[$category_id]);
            update_option('dgptm_suite_categories', $categories);

            // Move all modules in this category to 'uncategorized'
            $this->move_category_modules($category_id, 'uncategorized');

            add_settings_error('dgptm_categories', 'category_deleted', 'Category deleted successfully.', 'success');
        }
    }

    /**
     * Modul in andere Kategorie verschieben
     */
    private function move_module_to_category() {
        $module_id = sanitize_key($_POST['module_id'] ?? '');
        $new_category = sanitize_key($_POST['new_category'] ?? '');

        error_log("DGPTM move_module: Attempting to move '$module_id' to '$new_category'");

        if (empty($module_id) || empty($new_category)) {
            add_settings_error('dgptm_categories', 'invalid_input', 'Module ID and category are required.');
            error_log("DGPTM move_module: Failed - missing module_id or category");
            return;
        }

        global $dgptm_suite;

        // Fallback: Versuche dgptm_suite() Funktion wenn global nicht verfügbar
        if (!isset($dgptm_suite) && function_exists('dgptm_suite')) {
            $dgptm_suite = dgptm_suite();
        }

        // Wenn module_loader verfügbar ist, verwende ihn
        if (isset($dgptm_suite) && isset($dgptm_suite->module_loader)) {
            $all_modules = $dgptm_suite->module_loader->get_available_modules();
            $module_info = $all_modules[$module_id] ?? null;
            error_log("DGPTM move_module: Using module_loader, found " . count($all_modules) . " modules");
        } else {
            // Fallback: Direkter Scan
            error_log("DGPTM move_module: module_loader not available, using direct scan");
            $all_modules = $this->scan_modules_direct();
            $module_info = $all_modules[$module_id] ?? null;
        }

        if (!$module_info) {
            add_settings_error('dgptm_categories', 'module_not_found', "Module '$module_id' not found.");
            error_log("DGPTM move_module: Module '$module_id' not found in module list");
            return;
        }

        error_log("DGPTM move_module: Found module at path: " . $module_info['path']);

        // Update module.json
        $config_file = $module_info['path'] . 'module.json';

        error_log("DGPTM move_module: Config file path: $config_file");

        if (!file_exists($config_file)) {
            add_settings_error('dgptm_categories', 'config_not_found', "Module configuration file not found: $config_file");
            error_log("DGPTM move_module: Config file not found: $config_file");
            return;
        }

        $json_content = file_get_contents($config_file);
        error_log("DGPTM move_module: Read JSON content (" . strlen($json_content) . " bytes)");

        $config = json_decode($json_content, true);

        if (!$config) {
            add_settings_error('dgptm_categories', 'json_error', 'Could not parse module.json file.');
            error_log("DGPTM move_module: JSON decode failed");
            return;
        }

        $old_category = $config['category'] ?? 'uncategorized';
        error_log("DGPTM move_module: Old category: $old_category, New category: $new_category");

        $config['category'] = $new_category;

        $json_output = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        error_log("DGPTM move_module: Writing JSON (" . strlen($json_output) . " bytes)");

        $result = file_put_contents($config_file, $json_output);

        if ($result === false) {
            add_settings_error('dgptm_categories', 'write_error', "Could not write to module configuration file: $config_file");
            error_log("DGPTM move_module: file_put_contents failed");
            return;
        }

        error_log("DGPTM move_module: Successfully wrote $result bytes to $config_file");

        $module_name = $config['name'] ?? $module_id;
        $categories = $this->get_categories();
        $old_cat_name = $categories[$old_category]['name'] ?? $old_category;
        $new_cat_name = $categories[$new_category]['name'] ?? $new_category;

        add_settings_error(
            'dgptm_categories',
            'module_moved',
            sprintf('Modul "%s" wurde erfolgreich von "%s" nach "%s" verschoben.', $module_name, $old_cat_name, $new_cat_name),
            'success'
        );

        error_log("DGPTM move_module: Success - Module '$module_name' moved from '$old_cat_name' to '$new_cat_name'");
    }

    /**
     * Alle Module einer Kategorie verschieben
     */
    private function move_category_modules($old_category, $new_category) {
        global $dgptm_suite;

        // Fallback: Versuche dgptm_suite() Funktion wenn global nicht verfügbar
        if (!isset($dgptm_suite) && function_exists('dgptm_suite')) {
            $dgptm_suite = dgptm_suite();
        }

        // Sicherheitscheck
        if (!isset($dgptm_suite) || !isset($dgptm_suite->module_loader)) {
            error_log('DGPTM Categories: Cannot move modules - loader not available');
            return;
        }

        $modules = $dgptm_suite->module_loader->get_available_modules();

        foreach ($modules as $module_id => $module_info) {
            if ($module_info['category'] === $old_category) {
                $config_file = $module_info['path'] . 'module.json';

                if (!file_exists($config_file)) {
                    continue;
                }

                $config = json_decode(file_get_contents($config_file), true);

                if ($config) {
                    $config['category'] = $new_category;
                    file_put_contents($config_file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
            }
        }
    }

    /**
     * Module-Kategorien aktualisieren (einmalig)
     */
    public function render_update_categories() {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have sufficient permissions to access this page.'));
        }

        // Ausführen wenn Button geklickt
        if (isset($_POST['dgptm_update_categories'])) {
            check_admin_referer('dgptm_update_categories');

            $results = $this->update_all_module_categories();

            echo '<div class="wrap">';
            echo '<h1>' . __('Module-Kategorien aktualisieren', 'dgptm-suite') . '</h1>';
            echo '<div class="notice notice-success"><p><strong>Update abgeschlossen!</strong></p></div>';
            echo '<pre>';
            echo "Aktualisiert: {$results['updated']}\n";
            echo "Übersprungen: {$results['skipped']}\n";
            echo "Fehler: {$results['errors']}\n";
            echo "\nDetails:\n" . esc_html($results['log']);
            echo '</pre>';
            echo '<p><a href="' . admin_url('admin.php?page=dgptm-suite-categories') . '" class="button button-primary">Zur Kategorien-Verwaltung</a></p>';
            echo '</div>';
            return;
        }

        // Formular anzeigen
        ?>
        <div class="wrap">
            <h1><?php _e('Module-Kategorien aktualisieren', 'dgptm-suite'); ?></h1>
            <p><?php _e('Dieses Tool fügt allen Modulen das "category" Feld in ihrer module.json hinzu, basierend auf dem Ordner in dem sie sich befinden.', 'dgptm-suite'); ?></p>

            <div class="notice notice-info">
                <p><strong><?php _e('Hinweis:', 'dgptm-suite'); ?></strong> <?php _e('Dieser Schritt ist nur einmal notwendig für bestehende Module. Neue Module sollten das category Feld bereits haben.', 'dgptm-suite'); ?></p>
            </div>

            <form method="post" onsubmit="return confirm('<?php _e('Möchten Sie wirklich alle module.json Dateien aktualisieren?', 'dgptm-suite'); ?>');">
                <?php wp_nonce_field('dgptm_update_categories'); ?>
                <p>
                    <button type="submit" name="dgptm_update_categories" class="button button-primary button-large">
                        <?php _e('Alle Module aktualisieren', 'dgptm-suite'); ?>
                    </button>
                    <a href="<?php echo admin_url('admin.php?page=dgptm-suite-categories'); ?>" class="button">
                        <?php _e('Abbrechen', 'dgptm-suite'); ?>
                    </a>
                </p>
            </form>
        </div>
        <?php
    }

    /**
     * Alle module.json Dateien mit category aktualisieren
     */
    private function update_all_module_categories() {
        $modules_dir = DGPTM_SUITE_PATH . 'modules/';
        $updated = 0;
        $skipped = 0;
        $errors = 0;
        $log = '';

        // Kategorie-Mapping basierend auf Ordnerstruktur
        $category_map = [
            'core-infrastructure' => 'core-infrastructure',
            'business' => 'business',
            'payment' => 'payment',
            'auth' => 'auth',
            'media' => 'media',
            'content' => 'content',
            'acf-tools' => 'acf-tools',
            'utilities' => 'utilities',
        ];

        // Scan alle Kategorien
        foreach ($category_map as $folder => $category) {
            $category_path = $modules_dir . $folder . '/';

            if (!is_dir($category_path)) {
                continue;
            }

            $log .= "\n=== Kategorie: $category ===\n";

            $items = @scandir($category_path);
            if ($items === false) {
                continue;
            }

            foreach ($items as $item) {
                if ($item === '.' || $item === '..' || substr($item, -4) === '.zip') {
                    continue;
                }

                $module_path = $category_path . $item . '/';

                if (!is_dir($module_path)) {
                    continue;
                }

                $config_file = $module_path . 'module.json';

                if (!file_exists($config_file)) {
                    $log .= "  [SKIP] $item - keine module.json\n";
                    $skipped++;
                    continue;
                }

                // JSON laden
                $json = file_get_contents($config_file);
                $config = json_decode($json, true);

                if (!$config) {
                    $log .= "  [ERROR] $item - ungültige JSON\n";
                    $errors++;
                    continue;
                }

                // Prüfen ob category bereits gesetzt
                if (isset($config['category'])) {
                    $log .= "  [OK] {$config['id']} - category bereits gesetzt: {$config['category']}\n";
                    $skipped++;
                    continue;
                }

                // Category hinzufügen
                $config['category'] = $category;

                // JSON speichern mit schöner Formatierung
                $json_output = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

                if (file_put_contents($config_file, $json_output)) {
                    $log .= "  [UPDATE] {$config['id']} - category hinzugefügt: $category\n";
                    $updated++;
                } else {
                    $log .= "  [ERROR] {$config['id']} - konnte nicht schreiben\n";
                    $errors++;
                }
            }
        }

        return [
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
            'log' => $log,
        ];
    }

    /**
     * AJAX: Modul-Fehler löschen
     */
    public function ajax_clear_module_error() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung', 'dgptm-suite')]);
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');

        if (empty($module_id)) {
            wp_send_json_error(['message' => __('Ungültige Modul-ID', 'dgptm-suite')]);
        }

        $safe_loader = DGPTM_Safe_Loader::get_instance();
        $safe_loader->clear_module_error($module_id);

        dgptm_log("Fehler-Eintrag für Modul '$module_id' wurde manuell gelöscht", 'info');

        wp_send_json_success([
            'message' => sprintf(
                __('Fehler-Eintrag für Modul "%s" wurde gelöscht. Sie können jetzt versuchen das Modul erneut zu aktivieren.', 'dgptm-suite'),
                $module_id
            ),
            'module_id' => $module_id
        ]);
    }
}
