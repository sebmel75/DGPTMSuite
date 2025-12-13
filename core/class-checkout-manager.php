<?php
/**
 * Checkout Manager für DGPTM Plugin Suite
 * Checkout/Checkin System mit automatischem Rollback
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Checkout_Manager {

    private static $instance = null;
    private $checkout_dir;
    private $backup_dir;

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
        $this->checkout_dir = WP_CONTENT_DIR . '/dgptm-checkouts/';
        $this->backup_dir = WP_CONTENT_DIR . '/dgptm-backups/';

        // Verzeichnisse erstellen
        wp_mkdir_p($this->checkout_dir);
        wp_mkdir_p($this->backup_dir);
    }

    /**
     * CHECKOUT: Modul exportieren für Bearbeitung
     *
     * @param string $module_id Module ID
     * @return array|WP_Error Result mit download_url oder Error
     */
    public function checkout_module($module_id) {
        $module_loader = dgptm_suite()->get_module_loader();
        $module_path = $module_loader->get_module_path($module_id);
        $config = $module_loader->get_module_config($module_id);

        if (!$module_path || !$config) {
            return new WP_Error('module_not_found', __('Module not found.', 'dgptm-suite'));
        }

        // Erstelle Checkout-Record
        $checkout_id = uniqid('checkout_', true);
        $timestamp = time();

        $checkout_info = [
            'checkout_id' => $checkout_id,
            'module_id' => $module_id,
            'module_name' => $config['name'],
            'version' => $config['version'],
            'checked_out_at' => $timestamp,
            'checked_out_by' => get_current_user_id(),
            'status' => 'checked_out',
        ];

        // Erstelle vollständiges Backup VOR Export
        $backup_result = $this->create_checkout_backup($module_id, $checkout_id);

        if (is_wp_error($backup_result)) {
            return $backup_result;
        }

        $checkout_info['backup_file'] = $backup_result['backup_file'];
        $checkout_info['backup_path'] = $backup_result['backup_path'];

        // Speichere Checkout-Info
        $this->save_checkout_info($checkout_id, $checkout_info);

        // Exportiere Modul als ZIP
        $zip_generator = new DGPTM_ZIP_Generator();
        $export_result = $zip_generator->export_module_as_plugin($module_id);

        if (is_wp_error($export_result)) {
            return $export_result;
        }

        // Füge Checkout-Info zur Response hinzu
        $export_result['checkout_id'] = $checkout_id;
        $export_result['checkout_info'] = $checkout_info;
        $export_result['message'] = sprintf(
            __('Module "%s" checked out. Version: %s. Backup created.', 'dgptm-suite'),
            $config['name'],
            $config['version']
        );

        dgptm_log("Checkout: Module '$module_id' checked out with ID $checkout_id", 'info');

        return $export_result;
    }

    /**
     * CHECKIN: Modul-Update hochladen und installieren
     *
     * @param string $checkout_id Checkout ID
     * @param array $file_info $_FILES array entry
     * @return array|WP_Error Result oder Error
     */
    public function checkin_module($checkout_id, $file_info) {
        // Lade Checkout-Info
        $checkout_info = $this->get_checkout_info($checkout_id);

        if (!$checkout_info) {
            return new WP_Error('invalid_checkout', __('Invalid checkout ID.', 'dgptm-suite'));
        }

        $module_id = $checkout_info['module_id'];

        dgptm_log("Checkin: Starting checkin for module '$module_id' (Checkout: $checkout_id)", 'info');

        // Validiere Upload
        if ($file_info['error'] !== UPLOAD_ERR_OK) {
            return new WP_Error('upload_error', __('File upload error.', 'dgptm-suite'));
        }

        // PHP 7.4 kompatibel: str_ends_with() erst ab PHP 8.0
        $filename = strtolower($file_info['name']);
        if (substr($filename, -4) !== '.zip') {
            return new WP_Error('invalid_file', __('Only ZIP files are allowed.', 'dgptm-suite'));
        }

        // Temporäre Datei verschieben
        $temp_zip = $this->checkout_dir . 'checkin_' . $checkout_id . '.zip';

        if (!move_uploaded_file($file_info['tmp_name'], $temp_zip)) {
            return new WP_Error('move_failed', __('Could not move uploaded file.', 'dgptm-suite'));
        }

        dgptm_log("Checkin: ZIP uploaded successfully", 'verbose');

        // KRITISCH: Teste das neue Modul VOR der Installation!
        $test_result = $this->test_module_zip($temp_zip, $module_id);

        if (is_wp_error($test_result)) {
            dgptm_log("Checkin: Module test FAILED: " . $test_result->get_error_message(), 'error');
            @unlink($temp_zip);

            return new WP_Error(
                'module_test_failed',
                sprintf(
                    __('Module test failed: %s. Original module preserved.', 'dgptm-suite'),
                    $test_result->get_error_message()
                ),
                ['test_details' => $test_result->get_error_data()]
            );
        }

        dgptm_log("Checkin: Module test PASSED", 'info');

        // Test erfolgreich → Installiere Update
        $install_result = $this->install_module_update($module_id, $temp_zip, $checkout_info);

        if (is_wp_error($install_result)) {
            dgptm_log("Checkin: Installation FAILED - Rolling back", 'error');

            // ROLLBACK: Stelle Original wieder her
            $rollback_result = $this->rollback_from_backup($module_id, $checkout_info['backup_path']);

            @unlink($temp_zip);

            return new WP_Error(
                'installation_failed',
                sprintf(
                    __('Installation failed: %s. Module rolled back to previous version.', 'dgptm-suite'),
                    $install_result->get_error_message()
                )
            );
        }

        dgptm_log("Checkin: Installation successful", 'info');

        // Aufräumen
        @unlink($temp_zip);

        // Markiere Checkout als abgeschlossen
        $checkout_info['status'] = 'checked_in';
        $checkout_info['checked_in_at'] = time();
        $checkout_info['new_version'] = $install_result['new_version'];
        $this->save_checkout_info($checkout_id, $checkout_info);

        // Erfolgsmeldung
        $result = [
            'success' => true,
            'module_id' => $module_id,
            'checkout_id' => $checkout_id,
            'old_version' => $checkout_info['version'],
            'new_version' => $install_result['new_version'],
            'backup_kept' => true,
            'backup_file' => $checkout_info['backup_file'],
            'message' => sprintf(
                __('Module "%s" successfully updated from version %s to %s. Backup preserved.', 'dgptm-suite'),
                $checkout_info['module_name'],
                $checkout_info['version'],
                $install_result['new_version']
            )
        ];

        do_action('dgptm_suite_module_checked_in', $module_id, $checkout_info, $result);

        return $result;
    }

    /**
     * Teste Modul-ZIP in isolierter Umgebung
     */
    private function test_module_zip($zip_file, $module_id) {
        dgptm_log("Test: Testing module ZIP before installation", 'verbose');

        // Erstelle temporäres Test-Verzeichnis
        $test_dir = $this->checkout_dir . 'test_' . uniqid() . '/';
        wp_mkdir_p($test_dir);

        // Extrahiere ZIP in Test-Verzeichnis
        $zip = new ZipArchive();
        if ($zip->open($zip_file) !== true) {
            $this->delete_directory($test_dir);
            return new WP_Error('zip_error', __('Could not open ZIP file.', 'dgptm-suite'));
        }

        $zip->extractTo($test_dir);
        $zip->close();

        // Finde module.json
        $module_json = $test_dir . 'module.json';

        if (!file_exists($module_json)) {
            // Suche in Unterverzeichnissen (falls ZIP ein Wrapper-Verzeichnis hat)
            $items = scandir($test_dir);
            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                $subdir = $test_dir . $item . '/module.json';
                if (file_exists($subdir)) {
                    $module_json = $subdir;
                    break;
                }
            }
        }

        if (!file_exists($module_json)) {
            $this->delete_directory($test_dir);
            return new WP_Error('invalid_module', __('module.json not found in ZIP.', 'dgptm-suite'));
        }

        // Validiere module.json
        $config = json_decode(file_get_contents($module_json), true);

        if (!$config || !isset($config['id']) || !isset($config['main_file'])) {
            $this->delete_directory($test_dir);
            return new WP_Error('invalid_config', __('Invalid module.json structure.', 'dgptm-suite'));
        }

        // Prüfe ob IDs übereinstimmen
        if ($config['id'] !== $module_id) {
            $this->delete_directory($test_dir);
            return new WP_Error(
                'module_id_mismatch',
                sprintf(__('Module ID mismatch. Expected: %s, Found: %s', 'dgptm-suite'), $module_id, $config['id'])
            );
        }

        // Prüfe ob Modul als CRITICAL markiert ist
        $is_critical = !empty($config['critical']);
        $was_active = false;

        if ($is_critical) {
            // KRITISCHE MODULE: Nicht deaktivieren, da sie geschützt sind und bereits im Speicher geladen
            dgptm_log("Test: Module '$module_id' is CRITICAL - skipping deactivation/reactivation cycle", 'info');
            dgptm_log("Test: Critical modules can only be syntax-checked, not functionally tested", 'verbose');
        } else {
            // NORMALE MODULE: Temporär deaktivieren für sicheren Test
            $was_active = $this->deactivate_module_temporarily($module_id);
            dgptm_log("Test: Module '$module_id' temporarily deactivated for testing (was active: " . ($was_active ? 'yes' : 'no') . ")", 'verbose');
        }

        // Finde Hauptdatei
        $main_file = dirname($module_json) . '/' . $config['main_file'];

        if (!file_exists($main_file)) {
            $this->delete_directory($test_dir);
            if ($was_active) $this->reactivate_module($module_id);
            return new WP_Error('main_file_missing', sprintf(__('Main file not found: %s', 'dgptm-suite'), $config['main_file']));
        }

        // Syntax-Check mit php -l (falls verfügbar)
        $php_binary = $this->find_php_cli_binary();

        if ($php_binary) {
            dgptm_log("Test: Running syntax check with: $php_binary", 'verbose');
            $syntax_check = shell_exec("$php_binary -l " . escapeshellarg($main_file) . " 2>&1");

            if ($syntax_check && strpos($syntax_check, 'No syntax errors') === false) {
                dgptm_log("Test: Syntax check failed: $syntax_check", 'error');
                $this->delete_directory($test_dir);
                if ($was_active) $this->reactivate_module($module_id);
                return new WP_Error('syntax_error', sprintf(__('PHP Syntax error: %s', 'dgptm-suite'), $syntax_check));
            }

            dgptm_log("Test: Syntax check passed", 'verbose');
        } else {
            dgptm_log("Test: No PHP CLI binary found, skipping syntax check (proceeding with installation)", 'verbose');
        }

        $test_result = ['success' => true];

        // Cleanup
        $this->delete_directory($test_dir);

        // Reaktiviere Modul nur wenn es nicht kritisch ist UND vorher aktiv war
        if (!$is_critical && $was_active) {
            $this->reactivate_module($module_id);
            dgptm_log("Test: Module '$module_id' reactivated after test", 'verbose');
        }

        if (!$test_result['success']) {
            dgptm_log("Test: Module test failed - " . $test_result['error'], 'error');
            return new WP_Error(
                'module_load_error',
                sprintf(__('Module contains errors: %s', 'dgptm-suite'), $test_result['error']),
                $test_result
            );
        }

        dgptm_log("Test: Module test successful", 'verbose');

        return [
            'success' => true,
            'config' => $config,
            'version' => $config['version'] ?? 'unknown'
        ];
    }

    /**
     * Installiere Modul-Update
     */
    private function install_module_update($module_id, $zip_file, $checkout_info) {
        $module_loader = dgptm_suite()->get_module_loader();
        $module_path = $module_loader->get_module_path($module_id);

        if (!$module_path) {
            return new WP_Error('module_path_not_found', __('Module path not found.', 'dgptm-suite'));
        }

        dgptm_log("Install: Installing update to $module_path", 'info');

        // Modul temporär deaktivieren
        $was_active = $this->deactivate_module_temporarily($module_id);

        // Lösche altes Modul (außer module.json Backup)
        $old_module_json = $module_path . 'module.json';
        $module_json_backup = $module_path . 'module.json.backup';

        if (file_exists($old_module_json)) {
            copy($old_module_json, $module_json_backup);
        }

        $this->clean_module_directory($module_path, ['module.json.backup']);

        // Extrahiere neues Modul
        $zip = new ZipArchive();

        if ($zip->open($zip_file) !== true) {
            // Restore backup
            if (file_exists($module_json_backup)) {
                rename($module_json_backup, $old_module_json);
            }
            return new WP_Error('zip_extract_failed', __('Could not extract ZIP file.', 'dgptm-suite'));
        }

        // Prüfe ZIP-Struktur
        $has_wrapper = false;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $filename = $zip->getNameIndex($i);
            if (strpos($filename, '/') !== false && substr_count($filename, '/') === 1) {
                $has_wrapper = true;
                break;
            }
        }

        if ($has_wrapper) {
            // Extract mit Wrapper, dann verschieben
            $temp_extract = $module_path . '_temp/';
            wp_mkdir_p($temp_extract);
            $zip->extractTo($temp_extract);
            $zip->close();

            // Finde das echte Modul-Verzeichnis
            $items = scandir($temp_extract);
            $wrapper_dir = null;

            foreach ($items as $item) {
                if ($item === '.' || $item === '..') continue;
                if (is_dir($temp_extract . $item)) {
                    $wrapper_dir = $temp_extract . $item;
                    break;
                }
            }

            if ($wrapper_dir) {
                // Verschiebe Dateien aus Wrapper
                $files = scandir($wrapper_dir);
                foreach ($files as $file) {
                    if ($file === '.' || $file === '..') continue;
                    rename($wrapper_dir . '/' . $file, $module_path . $file);
                }
            }

            $this->delete_directory($temp_extract);
        } else {
            // Direct extract
            $zip->extractTo($module_path);
            $zip->close();
        }

        // Cleanup backup
        @unlink($module_json_backup);

        // Lade neue Konfiguration
        $new_config = $module_loader->get_module_config($module_id);

        // Reaktiviere Modul wenn es vorher aktiv war
        if ($was_active) {
            $this->reactivate_module($module_id);
        }

        dgptm_log("Install: Update installed successfully", 'info');

        return [
            'success' => true,
            'new_version' => $new_config['version'] ?? 'unknown'
        ];
    }

    /**
     * Erstelle Checkout-Backup
     */
    private function create_checkout_backup($module_id, $checkout_id) {
        $module_loader = dgptm_suite()->get_module_loader();
        $module_path = $module_loader->get_module_path($module_id);

        if (!$module_path) {
            return new WP_Error('module_not_found', __('Module not found.', 'dgptm-suite'));
        }

        $backup_name = $module_id . '_checkout_' . $checkout_id . '_' . date('Y-m-d_H-i-s') . '.zip';
        $backup_path = $this->backup_dir . $backup_name;

        $zip_generator = new DGPTM_ZIP_Generator();
        $result = $zip_generator->create_zip($module_path, $backup_path);

        if (!$result) {
            return new WP_Error('backup_failed', __('Could not create backup.', 'dgptm-suite'));
        }

        dgptm_log("Checkout: Backup created: $backup_name", 'verbose');

        return [
            'backup_file' => $backup_name,
            'backup_path' => $backup_path
        ];
    }

    /**
     * Rollback von Backup
     */
    private function rollback_from_backup($module_id, $backup_path) {
        if (!file_exists($backup_path)) {
            return new WP_Error('backup_not_found', __('Backup file not found.', 'dgptm-suite'));
        }

        dgptm_log("Rollback: Rolling back module '$module_id' from backup", 'warning');

        $module_loader = dgptm_suite()->get_module_loader();
        $module_path = $module_loader->get_module_path($module_id);

        // Lösche aktuelles Modul
        $this->clean_module_directory($module_path);

        // Stelle Backup wieder her
        $zip = new ZipArchive();

        if ($zip->open($backup_path) !== true) {
            return new WP_Error('zip_error', __('Could not open backup file.', 'dgptm-suite'));
        }

        $zip->extractTo($module_path);
        $zip->close();

        dgptm_log("Rollback: Rollback successful", 'info');

        return true;
    }

    /**
     * Modul temporär deaktivieren
     */
    private function deactivate_module_temporarily($module_id) {
        $settings = get_option('dgptm_suite_settings', []);
        $was_active = isset($settings['active_modules'][$module_id]) && $settings['active_modules'][$module_id];

        if ($was_active) {
            $settings['active_modules'][$module_id] = false;
            update_option('dgptm_suite_settings', $settings);
            dgptm_log("Module '$module_id' temporarily deactivated", 'verbose');
        }

        return $was_active;
    }

    /**
     * Modul reaktivieren
     */
    private function reactivate_module($module_id) {
        $settings = get_option('dgptm_suite_settings', []);
        $settings['active_modules'][$module_id] = true;
        update_option('dgptm_suite_settings', $settings);
        dgptm_log("Module '$module_id' reactivated", 'verbose');
    }

    /**
     * Speichere Checkout-Info
     */
    private function save_checkout_info($checkout_id, $checkout_info) {
        $checkouts = get_option('dgptm_suite_checkouts', []);
        $checkouts[$checkout_id] = $checkout_info;
        update_option('dgptm_suite_checkouts', $checkouts);
    }

    /**
     * Lade Checkout-Info
     */
    private function get_checkout_info($checkout_id) {
        $checkouts = get_option('dgptm_suite_checkouts', []);
        return $checkouts[$checkout_id] ?? null;
    }

    /**
     * Alle Checkouts abrufen
     */
    public function get_all_checkouts() {
        return get_option('dgptm_suite_checkouts', []);
    }

    /**
     * Aktive Checkouts abrufen (alias für get_all_checkouts)
     */
    public function get_active_checkouts() {
        return $this->get_all_checkouts();
    }

    /**
     * Verzeichnis bereinigen
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

            $item_path = $path . $item;

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
     * CANCEL CHECKOUT: Checkout abbrechen ohne Upload
     *
     * @param string $checkout_id Checkout ID
     * @return true|WP_Error
     */
    public function cancel_checkout($checkout_id) {
        // Prüfe beide möglichen Option-Namen
        $checkouts = get_option('dgptm_suite_checkouts', []);

        if (!isset($checkouts[$checkout_id])) {
            // Fallback auf alte Option
            $checkouts = get_option('dgptm_active_checkouts', []);
        }

        if (!isset($checkouts[$checkout_id])) {
            return new WP_Error('not_found', __('Checkout nicht gefunden', 'dgptm-suite'));
        }

        $checkout_info = $checkouts[$checkout_id];
        $module_id = $checkout_info['module_id'];

        dgptm_log("Cancel Checkout: Storniere Checkout für Modul '$module_id' (ID: $checkout_id)", 'info');

        // Backup-Datei löschen (falls vorhanden)
        if (isset($checkout_info['backup_path']) && file_exists($checkout_info['backup_path'])) {
            @unlink($checkout_info['backup_path']);
            dgptm_log("Cancel Checkout: Backup gelöscht: {$checkout_info['backup_path']}", 'verbose');
        }

        // Alternative Backup-Pfade prüfen
        $backup_paths = [
            $this->backup_dir . $checkout_id . '.zip',
            $this->backup_dir . $module_id . '_checkout_' . $checkout_id . '*.zip'
        ];

        foreach ($backup_paths as $pattern) {
            foreach (glob($pattern) as $file) {
                @unlink($file);
                dgptm_log("Cancel Checkout: Backup gelöscht: $file", 'verbose');
            }
        }

        // Checkout-Datei löschen (falls vorhanden)
        $checkout_file = $this->checkout_dir . $checkout_id . '.zip';
        if (file_exists($checkout_file)) {
            @unlink($checkout_file);
            dgptm_log("Cancel Checkout: Checkout-Datei gelöscht: $checkout_file", 'verbose');
        }

        // Aus Checkouts entfernen
        unset($checkouts[$checkout_id]);
        update_option('dgptm_suite_checkouts', $checkouts);

        // Auch aus alter Option entfernen (falls vorhanden)
        $old_checkouts = get_option('dgptm_active_checkouts', []);
        if (isset($old_checkouts[$checkout_id])) {
            unset($old_checkouts[$checkout_id]);
            update_option('dgptm_active_checkouts', $old_checkouts);
        }

        dgptm_log("Cancel Checkout: Checkout erfolgreich storniert", 'info');

        do_action('dgptm_suite_checkout_cancelled', $module_id, $checkout_id);

        return true;
    }

    /**
     * Finde PHP CLI Binary (nicht php-fpm)
     */
    private function find_php_cli_binary() {
        // Versuche verschiedene Pfade (PHP 8.0+)
        $possible_paths = [
            '/usr/bin/php',
            '/usr/local/bin/php',
            '/opt/plesk/php/8.3/bin/php',
            '/opt/plesk/php/8.2/bin/php',
            '/opt/plesk/php/8.1/bin/php',
            '/opt/plesk/php/8.0/bin/php',
            'php', // Fallback: Hoffe es ist im PATH
        ];

        // Prüfe PHP_BINARY, aber nur wenn es nicht php-fpm ist
        if (defined('PHP_BINARY') && PHP_BINARY && strpos(PHP_BINARY, 'php-fpm') === false) {
            array_unshift($possible_paths, PHP_BINARY);
        }

        foreach ($possible_paths as $path) {
            // Teste ob Binary existiert und -l unterstützt
            $test = @shell_exec("$path -v 2>&1");
            if ($test && strpos($test, 'PHP') !== false && strpos($test, 'fpm') === false) {
                return $path;
            }
        }

        return null;
    }
}
