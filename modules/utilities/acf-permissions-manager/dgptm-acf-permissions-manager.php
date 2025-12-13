<?php
/**
 * Plugin Name: DGPTM - ACF Permissions Manager
 * Description: Verwaltet und überwacht ACF-Berechtigungen für Benutzer mit Batch-Zuweisung
 * Version: 1.0.0
 * Author: Sebastian Melzer
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent class redeclaration
if (!class_exists('DGPTM_ACF_Permissions_Manager')) {

class DGPTM_ACF_Permissions_Manager {

    private static $instance = null;
    private $plugin_path;
    private $plugin_url;
    private $permissions_group_key = 'group_6792060047841'; // ACF Group Key

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
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

        // AJAX Handlers
        add_action('wp_ajax_apm_get_permission_users', [$this, 'ajax_get_permission_users']);
        add_action('wp_ajax_apm_toggle_permission', [$this, 'ajax_toggle_permission']);
        add_action('wp_ajax_apm_batch_assign', [$this, 'ajax_batch_assign']);
        add_action('wp_ajax_apm_batch_revoke', [$this, 'ajax_batch_revoke']);
        add_action('wp_ajax_apm_export_csv', [$this, 'ajax_export_csv']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_users_page(
            'ACF Berechtigungen',
            'ACF Berechtigungen',
            'manage_options',
            'acf-permissions-manager',
            [$this, 'render_admin_page']
        );
    }

    /**
     * Enqueue admin assets
     */
    public function enqueue_admin_assets($hook) {
        if ($hook !== 'users_page_acf-permissions-manager') {
            return;
        }

        wp_enqueue_style(
            'apm-admin',
            $this->plugin_url . 'assets/css/admin.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'apm-admin',
            $this->plugin_url . 'assets/js/admin.js',
            ['jquery'],
            '1.0.0',
            true
        );

        wp_localize_script('apm-admin', 'apmData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('apm_nonce'),
            'strings' => [
                'confirmRevoke' => 'Berechtigung wirklich entziehen?',
                'confirmBatchAssign' => 'Berechtigung allen ausgewählten Benutzern zuweisen?',
                'confirmBatchRevoke' => 'Berechtigung allen Benutzern entziehen?'
            ]
        ]);
    }

    /**
     * Render admin page
     */
    public function render_admin_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung');
        }

        include $this->plugin_path . 'templates/admin-page.php';
    }

    /**
     * Get all permissions from ACF group dynamically
     */
    public function get_all_permissions() {
        if (!function_exists('acf_get_field_group')) {
            return [];
        }

        $group = acf_get_field_group($this->permissions_group_key);

        if (!$group) {
            return [];
        }

        $fields = acf_get_fields($this->permissions_group_key);
        $permissions = [];

        if ($fields) {
            foreach ($fields as $field) {
                if ($field['type'] === 'true_false') {
                    $permissions[] = [
                        'key' => $field['key'],
                        'name' => $field['name'],
                        'label' => $field['label']
                    ];
                }
            }
        }

        return $permissions;
    }

    /**
     * Get users with specific permission
     */
    public function get_users_with_permission($permission_name) {
        $all_users = get_users([
            'fields' => ['ID', 'display_name', 'user_email', 'user_login']
        ]);

        $users_with_permission = [];

        foreach ($all_users as $user) {
            $has_permission = get_field($permission_name, 'user_' . $user->ID);

            if ($has_permission) {
                $users_with_permission[] = [
                    'ID' => $user->ID,
                    'display_name' => $user->display_name,
                    'user_email' => $user->user_email,
                    'user_login' => $user->user_login
                ];
            }
        }

        return $users_with_permission;
    }

    /**
     * Get all users with their permissions
     */
    public function get_all_users_permissions() {
        $permissions = $this->get_all_permissions();
        $all_users = get_users([
            'fields' => ['ID', 'display_name', 'user_email', 'user_login']
        ]);

        $result = [];

        foreach ($all_users as $user) {
            $user_permissions = [];

            foreach ($permissions as $permission) {
                $has_permission = get_field($permission['name'], 'user_' . $user->ID);
                $user_permissions[$permission['name']] = (bool) $has_permission;
            }

            $result[] = [
                'ID' => $user->ID,
                'display_name' => $user->display_name,
                'user_email' => $user->user_email,
                'user_login' => $user->user_login,
                'permissions' => $user_permissions
            ];
        }

        return $result;
    }

    /**
     * AJAX: Get users for specific permission
     */
    public function ajax_get_permission_users() {
        check_ajax_referer('apm_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        $permission_name = sanitize_text_field($_POST['permission'] ?? '');

        if (empty($permission_name)) {
            wp_send_json_error(['message' => 'Ungültige Berechtigung']);
        }

        $users = $this->get_users_with_permission($permission_name);

        wp_send_json_success([
            'users' => $users,
            'count' => count($users)
        ]);
    }

    /**
     * AJAX: Toggle permission for user
     */
    public function ajax_toggle_permission() {
        check_ajax_referer('apm_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        $user_id = intval($_POST['user_id'] ?? 0);
        $permission_name = sanitize_text_field($_POST['permission'] ?? '');
        $value = filter_var($_POST['value'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$user_id || empty($permission_name)) {
            wp_send_json_error(['message' => 'Ungültige Parameter']);
        }

        // Update ACF field
        update_field($permission_name, $value, 'user_' . $user_id);

        wp_send_json_success([
            'message' => $value ? 'Berechtigung erteilt' : 'Berechtigung entzogen',
            'value' => $value
        ]);
    }

    /**
     * AJAX: Batch assign permission to multiple users
     */
    public function ajax_batch_assign() {
        check_ajax_referer('apm_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        $user_ids = $_POST['user_ids'] ?? [];
        $permission_name = sanitize_text_field($_POST['permission'] ?? '');

        if (empty($user_ids) || empty($permission_name)) {
            wp_send_json_error(['message' => 'Ungültige Parameter']);
        }

        $count = 0;

        foreach ($user_ids as $user_id) {
            $user_id = intval($user_id);
            if ($user_id) {
                update_field($permission_name, true, 'user_' . $user_id);
                $count++;
            }
        }

        wp_send_json_success([
            'message' => "{$count} Benutzer(n) wurde die Berechtigung erteilt",
            'count' => $count
        ]);
    }

    /**
     * AJAX: Batch revoke permission from all users
     */
    public function ajax_batch_revoke() {
        check_ajax_referer('apm_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        $permission_name = sanitize_text_field($_POST['permission'] ?? '');

        if (empty($permission_name)) {
            wp_send_json_error(['message' => 'Ungültige Berechtigung']);
        }

        $users = $this->get_users_with_permission($permission_name);
        $count = 0;

        foreach ($users as $user) {
            update_field($permission_name, false, 'user_' . $user['ID']);
            $count++;
        }

        wp_send_json_success([
            'message' => "{$count} Benutzer(n) wurde die Berechtigung entzogen",
            'count' => $count
        ]);
    }

    /**
     * AJAX: Export permissions as CSV
     */
    public function ajax_export_csv() {
        check_ajax_referer('apm_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung');
        }

        $permissions = $this->get_all_permissions();
        $users_permissions = $this->get_all_users_permissions();

        // Set headers for CSV download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=acf-berechtigungen-' . date('Y-m-d') . '.csv');

        $output = fopen('php://output', 'w');

        // Write BOM for Excel UTF-8 support
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

        // Header row
        $header = ['Benutzer', 'E-Mail', 'Login'];
        foreach ($permissions as $permission) {
            $header[] = $permission['label'];
        }
        fputcsv($output, $header, ';');

        // Data rows
        foreach ($users_permissions as $user_data) {
            $row = [
                $user_data['display_name'],
                $user_data['user_email'],
                $user_data['user_login']
            ];

            foreach ($permissions as $permission) {
                $has_permission = $user_data['permissions'][$permission['name']] ?? false;
                $row[] = $has_permission ? 'Ja' : 'Nein';
            }

            fputcsv($output, $row, ';');
        }

        fclose($output);
        exit;
    }
}

} // End class check

// Prevent double initialization
if (!isset($GLOBALS['dgptm_acf_permissions_manager_initialized'])) {
    $GLOBALS['dgptm_acf_permissions_manager_initialized'] = true;
    DGPTM_ACF_Permissions_Manager::get_instance();
}
