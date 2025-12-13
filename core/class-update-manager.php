<?php
/**
 * Update Manager für DGPTM Plugin Suite
 * Verwaltet Updates für einzelne Module
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Update_Manager {

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
        add_action('admin_init', [$this, 'check_updates']);
    }

    /**
     * Updates prüfen (täglich)
     */
    public function check_updates() {
        $settings = get_option('dgptm_suite_settings', []);
        $last_check = $settings['last_update_check'] ?? 0;

        // Nur einmal täglich prüfen
        if (time() - $last_check < DAY_IN_SECONDS) {
            return;
        }

        // Update-Check durchführen
        $this->perform_update_check();

        // Timestamp aktualisieren
        $settings['last_update_check'] = time();
        update_option('dgptm_suite_settings', $settings);
    }

    /**
     * Update-Check durchführen
     */
    private function perform_update_check() {
        // TODO: Implementierung für externe Update-Quelle
        // Könnte GitHub, eigener Server, etc. sein

        /**
         * Beispiel-Implementation:
         * 1. Verfügbare Module abrufen
         * 2. Versions-Informationen von Update-Server abrufen
         * 3. Vergleich mit installierten Versionen
         * 4. Verfügbare Updates in Transient speichern
         */

        do_action('dgptm_suite_update_check_performed');
    }

    /**
     * Verfügbare Updates abrufen
     */
    public function get_available_updates() {
        return get_transient('dgptm_suite_available_updates') ?: [];
    }

    /**
     * Modul aktualisieren
     */
    public function update_module($module_id, $update_data) {
        if (empty($update_data['download_url'])) {
            return new WP_Error('no_download_url', __('No download URL provided.', 'dgptm-suite'));
        }

        // Backup erstellen
        $this->create_module_backup($module_id);

        // Update herunterladen
        $temp_file = $this->download_update($update_data['download_url']);

        if (is_wp_error($temp_file)) {
            return $temp_file;
        }

        // Update installieren
        $result = $this->install_update($module_id, $temp_file);

        // Temp-Datei löschen
        @unlink($temp_file);

        if ($result) {
            do_action('dgptm_suite_module_updated', $module_id, $update_data);
            return true;
        }

        return new WP_Error('update_failed', __('Update installation failed.', 'dgptm-suite'));
    }

    /**
     * Update herunterladen
     */
    private function download_update($url) {
        if (!function_exists('download_url')) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
        }

        $temp_file = download_url($url);

        if (is_wp_error($temp_file)) {
            return $temp_file;
        }

        return $temp_file;
    }

    /**
     * Update installieren
     */
    private function install_update($module_id, $zip_file) {
        if (!class_exists('ZipArchive')) {
            return new WP_Error('no_zip', __('ZipArchive class not available.', 'dgptm-suite'));
        }

        $module_loader = dgptm_suite()->get_module_loader();
        $module_path = $module_loader->get_module_path($module_id);

        if (!$module_path) {
            return new WP_Error('module_not_found', __('Module path not found.', 'dgptm-suite'));
        }

        $zip = new ZipArchive();

        if ($zip->open($zip_file) !== true) {
            return new WP_Error('zip_open_failed', __('Could not open ZIP file.', 'dgptm-suite'));
        }

        // Altes Modul löschen (außer module.json für Settings)
        $this->clean_module_directory($module_path, ['module.json']);

        // Neues Modul extrahieren
        $zip->extractTo($module_path);
        $zip->close();

        return true;
    }

    /**
     * Modul-Verzeichnis bereinigen
     */
    private function clean_module_directory($path, $keep_files = []) {
        if (!is_dir($path)) {
            return;
        }

        $items = scandir($path);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            if (in_array($item, $keep_files)) {
                continue;
            }

            $item_path = $path . '/' . $item;

            if (is_dir($item_path)) {
                $this->delete_directory($item_path);
            } else {
                @unlink($item_path);
            }
        }
    }

    /**
     * Verzeichnis rekursiv löschen
     */
    private function delete_directory($dir) {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . '/' . $item;

            if (is_dir($path)) {
                $this->delete_directory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }

    /**
     * Modul-Backup erstellen
     */
    private function create_module_backup($module_id) {
        $module_loader = dgptm_suite()->get_module_loader();
        $module_path = $module_loader->get_module_path($module_id);

        if (!$module_path) {
            return false;
        }

        $backup_dir = WP_CONTENT_DIR . '/dgptm-backups/';

        if (!is_dir($backup_dir)) {
            wp_mkdir_p($backup_dir);
        }

        $backup_name = $module_id . '_' . date('Y-m-d_H-i-s') . '.zip';
        $backup_path = $backup_dir . $backup_name;

        // ZIP erstellen
        $zip_generator = new DGPTM_ZIP_Generator();
        return $zip_generator->create_zip($module_path, $backup_path);
    }

    /**
     * Rollback durchführen
     */
    public function rollback_module($module_id, $backup_file) {
        if (!file_exists($backup_file)) {
            return new WP_Error('backup_not_found', __('Backup file not found.', 'dgptm-suite'));
        }

        return $this->install_update($module_id, $backup_file);
    }

    /**
     * Verfügbare Backups abrufen
     */
    public function get_available_backups($module_id = null) {
        $backup_dir = WP_CONTENT_DIR . '/dgptm-backups/';

        if (!is_dir($backup_dir)) {
            return [];
        }

        $backups = [];
        $files = scandir($backup_dir);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || !str_ends_with($file, '.zip')) {
                continue;
            }

            if ($module_id && !str_starts_with($file, $module_id . '_')) {
                continue;
            }

            $backups[] = [
                'file' => $file,
                'path' => $backup_dir . $file,
                'size' => filesize($backup_dir . $file),
                'date' => filemtime($backup_dir . $file),
            ];
        }

        // Nach Datum sortieren (neueste zuerst)
        usort($backups, function($a, $b) {
            return $b['date'] - $a['date'];
        });

        return $backups;
    }
}
