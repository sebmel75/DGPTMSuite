<?php
/**
 * Module Upload & Management AJAX Handler
 * Handles plugin upload, analysis, finalization, editing, and deletion
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Module_Upload_Handler {

    public function __construct() {
        // Upload & Analysis
        add_action('wp_ajax_dgptm_analyze_plugin', array($this, 'analyze_plugin'));
        add_action('wp_ajax_dgptm_finalize_module', array($this, 'finalize_module'));
        add_action('wp_ajax_dgptm_cancel_upload', array($this, 'cancel_upload'));

        // Module Management
        add_action('wp_ajax_dgptm_edit_module', array($this, 'edit_module'));
        add_action('wp_ajax_dgptm_update_module', array($this, 'update_module'));
        add_action('wp_ajax_dgptm_delete_module', array($this, 'delete_module'));
        add_action('wp_ajax_dgptm_move_module', array($this, 'move_module'));

        // Version reading
        add_action('wp_ajax_dgptm_refresh_version', array($this, 'refresh_version'));
    }

    /**
     * Analyze uploaded plugin ZIP
     */
    public function analyze_plugin() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'dgptm-suite')));
        }

        if (!isset($_FILES['plugin_zip'])) {
            wp_send_json_error(array('message' => __('No file uploaded', 'dgptm-suite')));
        }

        $file = $_FILES['plugin_zip'];

        // Validate file type
        $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($file_ext !== 'zip') {
            wp_send_json_error(array('message' => __('Only ZIP files are allowed', 'dgptm-suite')));
        }

        // Create temp directory
        $temp_dir = WP_CONTENT_DIR . '/dgptm-temp-' . uniqid();
        if (!wp_mkdir_p($temp_dir)) {
            wp_send_json_error(array('message' => __('Could not create temp directory', 'dgptm-suite')));
        }

        // Move uploaded file
        $temp_zip = $temp_dir . '/plugin.zip';
        if (!move_uploaded_file($file['tmp_name'], $temp_zip)) {
            wp_send_json_error(array('message' => __('Could not move uploaded file', 'dgptm-suite')));
        }

        // Extract ZIP
        WP_Filesystem();
        global $wp_filesystem;

        $unzip_result = unzip_file($temp_zip, $temp_dir);
        if (is_wp_error($unzip_result)) {
            $this->cleanup_temp($temp_dir);
            wp_send_json_error(array('message' => __('Could not extract ZIP file', 'dgptm-suite')));
        }

        // Find main plugin file
        $plugin_info = $this->find_plugin_file($temp_dir);

        if (!$plugin_info) {
            $this->cleanup_temp($temp_dir);
            wp_send_json_error(array('message' => __('No valid plugin file found in ZIP', 'dgptm-suite')));
        }

        // Parse plugin headers
        $plugin_data = get_plugin_data($plugin_info['file_path'], false, false);

        wp_send_json_success(array(
            'name' => $plugin_data['Name'] ?: 'Unknown Plugin',
            'version' => $plugin_data['Version'] ?: '1.0.0',
            'author' => $plugin_data['Author'] ?: '',
            'description' => $plugin_data['Description'] ?: '',
            'main_file' => $plugin_info['main_file'],
            'temp_path' => $temp_dir,
            'folder_name' => $plugin_info['folder_name']
        ));
    }

    /**
     * Find main plugin file in extracted directory
     */
    private function find_plugin_file($dir) {
        $files = glob($dir . '/*.php');

        // First, check root directory
        foreach ($files as $file) {
            $data = get_file_data($file, array('Name' => 'Plugin Name'));
            if (!empty($data['Name'])) {
                return array(
                    'file_path' => $file,
                    'main_file' => basename($file),
                    'folder_name' => null
                );
            }
        }

        // Check subdirectories (typical plugin structure)
        $dirs = glob($dir . '/*', GLOB_ONLYDIR);
        foreach ($dirs as $subdir) {
            $files = glob($subdir . '/*.php');
            foreach ($files as $file) {
                $data = get_file_data($file, array('Name' => 'Plugin Name'));
                if (!empty($data['Name'])) {
                    return array(
                        'file_path' => $file,
                        'main_file' => basename($file),
                        'folder_name' => basename($subdir)
                    );
                }
            }
        }

        return false;
    }

    /**
     * Finalize module - move to modules directory and create module.json
     */
    public function finalize_module() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'dgptm-suite')));
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');
        $module_name = sanitize_text_field($_POST['module_name'] ?? '');
        $module_version = sanitize_text_field($_POST['module_version'] ?? '1.0.0');
        $module_author = sanitize_text_field($_POST['module_author'] ?? '');
        $module_description = sanitize_text_field($_POST['module_description'] ?? '');
        $module_category = sanitize_text_field($_POST['module_category'] ?? 'utilities');
        $icon = sanitize_text_field($_POST['icon'] ?? 'dashicons-admin-plugins');
        $main_file = sanitize_text_field($_POST['detected_main_file'] ?? '');
        $temp_path = sanitize_text_field($_POST['temp_path'] ?? '');

        if (empty($module_id) || empty($module_name) || empty($temp_path)) {
            wp_send_json_error(array('message' => __('Missing required fields', 'dgptm-suite')));
        }

        // Validate module ID
        if (!preg_match('/^[a-z0-9-]+$/', $module_id)) {
            wp_send_json_error(array('message' => __('Invalid module ID format', 'dgptm-suite')));
        }

        $modules_dir = WP_PLUGIN_DIR . '/dgptm-plugin-suite/modules';
        $category_dir = $modules_dir . '/' . $module_category;
        $module_dir = $category_dir . '/' . $module_id;

        // Check if module already exists
        if (file_exists($module_dir)) {
            $this->cleanup_temp($temp_path);
            wp_send_json_error(array('message' => __('Module ID already exists', 'dgptm-suite')));
        }

        // Create category directory if needed
        if (!file_exists($category_dir)) {
            wp_mkdir_p($category_dir);
        }

        // Move extracted plugin to module directory
        WP_Filesystem();
        global $wp_filesystem;

        // Find the actual plugin folder in temp
        $temp_contents = glob($temp_path . '/*');
        $source_dir = $temp_path;

        // If there's a subdirectory, use that
        foreach ($temp_contents as $item) {
            if (is_dir($item) && basename($item) !== 'plugin.zip') {
                $source_dir = $item;
                break;
            }
        }

        // Copy directory
        if (!$this->copy_dir($source_dir, $module_dir)) {
            $this->cleanup_temp($temp_path);
            wp_send_json_error(array('message' => __('Could not copy module files', 'dgptm-suite')));
        }

        // Create module.json
        $module_config = array(
            'id' => $module_id,
            'name' => $module_name,
            'description' => $module_description,
            'version' => $module_version,
            'author' => $module_author,
            'main_file' => $main_file,
            'dependencies' => array(),
            'optional_dependencies' => array(),
            'wp_dependencies' => array('plugins' => array()),
            'requires_php' => '7.4',
            'requires_wp' => '5.8',
            'category' => $module_category,
            'icon' => $icon,
            'active' => false,
            'can_export' => true
        );

        file_put_contents(
            $module_dir . '/module.json',
            json_encode($module_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
        );

        // Cleanup temp
        $this->cleanup_temp($temp_path);

        wp_send_json_success(array(
            'message' => sprintf(__('Module "%s" has been successfully added!', 'dgptm-suite'), $module_name),
            'module_id' => $module_id
        ));
    }

    /**
     * Cancel upload - cleanup temp files
     */
    public function cancel_upload() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        $temp_path = sanitize_text_field($_POST['temp_path'] ?? '');

        if ($temp_path && strpos($temp_path, 'dgptm-temp-') !== false) {
            $this->cleanup_temp($temp_path);
        }

        wp_send_json_success(array('message' => __('Upload canceled', 'dgptm-suite')));
    }

    /**
     * Edit module - return module data for editing
     */
    public function edit_module() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'dgptm-suite')));
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');

        $module_file = WP_PLUGIN_DIR . '/dgptm-plugin-suite/modules/' . $category . '/' . $module_id . '/module.json';

        if (!file_exists($module_file)) {
            wp_send_json_error(array('message' => __('Module not found', 'dgptm-suite')));
        }

        $config = json_decode(file_get_contents($module_file), true);

        wp_send_json_success($config);
    }

    /**
     * Update module configuration
     */
    public function update_module() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'dgptm-suite')));
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');
        $current_category = sanitize_text_field($_POST['current_category'] ?? '');
        $new_category = sanitize_text_field($_POST['category'] ?? '');
        $name = sanitize_text_field($_POST['name'] ?? '');
        $description = sanitize_text_field($_POST['description'] ?? '');
        $author = sanitize_text_field($_POST['author'] ?? '');
        $icon = sanitize_text_field($_POST['icon'] ?? 'dashicons-admin-plugins');

        $module_dir = WP_PLUGIN_DIR . '/dgptm-plugin-suite/modules/' . $current_category . '/' . $module_id;
        $module_file = $module_dir . '/module.json';

        if (!file_exists($module_file)) {
            wp_send_json_error(array('message' => __('Module not found', 'dgptm-suite')));
        }

        $config = json_decode(file_get_contents($module_file), true);

        // Update version from main file
        $main_file_path = $module_dir . '/' . $config['main_file'];
        if (file_exists($main_file_path)) {
            $plugin_data = get_plugin_data($main_file_path, false, false);
            $config['version'] = $plugin_data['Version'] ?: $config['version'];
        }

        // Update other fields
        $config['name'] = $name;
        $config['description'] = $description;
        $config['author'] = $author;
        $config['icon'] = $icon;
        $config['category'] = $new_category;

        // Save updated config
        file_put_contents($module_file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Move module if category changed
        if ($current_category !== $new_category) {
            $new_dir = WP_PLUGIN_DIR . '/dgptm-plugin-suite/modules/' . $new_category . '/' . $module_id;

            if (!file_exists(dirname($new_dir))) {
                wp_mkdir_p(dirname($new_dir));
            }

            rename($module_dir, $new_dir);
        }

        wp_send_json_success(array('message' => __('Module updated successfully', 'dgptm-suite')));
    }

    /**
     * Delete module
     */
    public function delete_module() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('Insufficient permissions', 'dgptm-suite')));
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');

        $module_dir = WP_PLUGIN_DIR . '/dgptm-plugin-suite/modules/' . $category . '/' . $module_id;

        if (!file_exists($module_dir)) {
            wp_send_json_error(array('message' => __('Module not found', 'dgptm-suite')));
        }

        // Check if module is active
        $module_file = $module_dir . '/module.json';
        if (file_exists($module_file)) {
            $config = json_decode(file_get_contents($module_file), true);
            if (!empty($config['active'])) {
                wp_send_json_error(array('message' => __('Please deactivate the module before deleting', 'dgptm-suite')));
            }
        }

        // Delete directory
        $this->delete_directory($module_dir);

        wp_send_json_success(array('message' => __('Module deleted successfully', 'dgptm-suite')));
    }

    /**
     * Refresh module version from main file
     */
    public function refresh_version() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');

        $module_dir = WP_PLUGIN_DIR . '/dgptm-plugin-suite/modules/' . $category . '/' . $module_id;
        $module_file = $module_dir . '/module.json';

        if (!file_exists($module_file)) {
            wp_send_json_error(array('message' => __('Module not found', 'dgptm-suite')));
        }

        $config = json_decode(file_get_contents($module_file), true);
        $main_file_path = $module_dir . '/' . $config['main_file'];

        if (file_exists($main_file_path)) {
            $plugin_data = get_plugin_data($main_file_path, false, false);
            $new_version = $plugin_data['Version'] ?: $config['version'];

            $config['version'] = $new_version;
            file_put_contents($module_file, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

            wp_send_json_success(array('version' => $new_version));
        } else {
            wp_send_json_error(array('message' => __('Main file not found', 'dgptm-suite')));
        }
    }

    /**
     * Copy directory recursively
     */
    private function copy_dir($src, $dst) {
        if (!file_exists($dst)) {
            wp_mkdir_p($dst);
        }

        $dir = opendir($src);
        if (!$dir) {
            return false;
        }

        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $src_file = $src . '/' . $file;
            $dst_file = $dst . '/' . $file;

            if (is_dir($src_file)) {
                $this->copy_dir($src_file, $dst_file);
            } else {
                copy($src_file, $dst_file);
            }
        }

        closedir($dir);
        return true;
    }

    /**
     * Delete directory recursively
     */
    private function delete_directory($dir) {
        if (!file_exists($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), array('.', '..'));

        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    /**
     * Cleanup temp directory
     */
    private function cleanup_temp($temp_dir) {
        if (file_exists($temp_dir) && strpos($temp_dir, 'dgptm-temp-') !== false) {
            $this->delete_directory($temp_dir);
        }
    }
}

// Initialize
new DGPTM_Module_Upload_Handler();
