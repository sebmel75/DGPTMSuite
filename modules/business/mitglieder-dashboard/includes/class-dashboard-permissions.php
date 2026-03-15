<?php
/**
 * Dashboard Permissions - ACF field checks, role checks, datetime windows
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Dashboard_Permissions {

    private $config;
    private $permission_cache = [];

    public function __construct(DGPTM_Dashboard_Config $config) {
        $this->config = $config;
    }

    /**
     * Get all tabs visible to a user
     */
    public function get_visible_tabs($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $tabs = $this->config->get_tabs();
        $visible = [];

        foreach ($tabs as $tab) {
            if (empty($tab['active'])) {
                continue;
            }
            if ($this->user_can_see_tab($user_id, $tab)) {
                $visible[] = $tab;
            }
        }

        return $visible;
    }

    /**
     * Check if user can see a specific tab
     */
    public function user_can_see_tab($user_id, $tab) {
        if (empty($tab['active'])) {
            return false;
        }

        // Datetime window check
        if (!$this->check_datetime_window($tab)) {
            return false;
        }

        $type = $tab['permission_type'] ?? 'always';

        switch ($type) {
            case 'always':
                return true;

            case 'acf_field':
                $field = $tab['permission_field'] ?? '';
                return $field ? $this->check_acf_permission($user_id, $field) : true;

            case 'role':
                $roles = $tab['permission_roles'] ?? [];
                return !empty($roles) ? $this->check_role_permission($user_id, $roles) : true;

            case 'admin':
                return user_can($user_id, 'manage_options');

            case 'custom_callback':
                return $this->check_custom_permission($user_id, $tab);

            default:
                return true;
        }
    }

    /**
     * Check ACF True/False field on user profile
     */
    public function check_acf_permission($user_id, $field_name) {
        $cache_key = $user_id . '_' . $field_name;

        if (isset($this->permission_cache[$cache_key])) {
            return $this->permission_cache[$cache_key];
        }

        // Admins always pass
        if (user_can($user_id, 'manage_options')) {
            $this->permission_cache[$cache_key] = true;
            return true;
        }

        $result = false;

        if (function_exists('get_field')) {
            $val = get_field($field_name, 'user_' . $user_id);
            $result = !empty($val);
        } else {
            $val = get_user_meta($user_id, $field_name, true);
            $result = !empty($val);
        }

        $this->permission_cache[$cache_key] = $result;
        return $result;
    }

    /**
     * Check WordPress user roles
     */
    public function check_role_permission($user_id, $allowed_roles) {
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        foreach ($allowed_roles as $role) {
            if (in_array($role, (array) $user->roles, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check datetime visibility window
     */
    public function check_datetime_window($tab) {
        $start = $tab['datetime_start'] ?? '';
        $end   = $tab['datetime_end'] ?? '';

        if (empty($start) && empty($end)) {
            return true;
        }

        $now = current_time('timestamp');

        if (!empty($start) && $now < strtotime($start)) {
            return false;
        }

        if (!empty($end) && $now > strtotime($end)) {
            return false;
        }

        return true;
    }

    /**
     * Custom permission checks (herzzentrum assigned users etc.)
     */
    private function check_custom_permission($user_id, $tab) {
        if ($tab['id'] === 'herzzentrum') {
            return $this->is_herzzentrum_assigned($user_id);
        }

        // Admins always pass custom checks
        return user_can($user_id, 'manage_options');
    }

    /**
     * Check if user has assigned heart center
     */
    private function is_herzzentrum_assigned($user_id) {
        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        // Check user meta for assigned heart center
        if (function_exists('get_field')) {
            $assigned = get_field('zugewiesenes_herzzentrum', 'user_' . $user_id);
            if (!empty($assigned)) {
                return true;
            }
            $edit_all = get_field('alle_herzzentren_bearbeiten', 'user_' . $user_id);
            if (!empty($edit_all)) {
                return true;
            }
        }

        $meta = get_user_meta($user_id, 'zugewiesenes_herzzentrum', true);
        return !empty($meta);
    }

    /**
     * Batch-load all permissions for a user (for efficiency)
     */
    public function preload_permissions($user_id) {
        if (!function_exists('acf_get_fields')) {
            return;
        }

        $fields = acf_get_fields('group_6792060047841');
        if (!is_array($fields)) {
            return;
        }

        foreach ($fields as $field) {
            if ($field['type'] !== 'true_false') {
                continue;
            }
            $cache_key = $user_id . '_' . $field['name'];
            if (!isset($this->permission_cache[$cache_key])) {
                $val = get_field($field['name'], 'user_' . $user_id);
                $this->permission_cache[$cache_key] = !empty($val);
            }
        }
    }
}
