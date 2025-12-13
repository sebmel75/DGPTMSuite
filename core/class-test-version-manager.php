<?php
/**
 * Test Version Manager
 *
 * Verwaltet Test-Versionen von Modulen:
 * - Erstellen von Test-Kopien
 * - Verknüpfung mit Hauptmodul
 * - Automatisches Switching bei Aktivierung
 * - Merge von Test in Hauptversion
 *
 * @package DGPTM_Suite
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Test_Version_Manager {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Prüfe ob Modul eine Testversion ist
     */
    public function is_test_version($module_data) {
        return isset($module_data['test_version']) && $module_data['test_version'] === true;
    }

    /**
     * Prüfe ob Modul eine verknüpfte Testversion hat
     */
    public function has_test_version($module_data) {
        return !empty($module_data['has_test_version']);
    }

    /**
     * Hole ID der Hauptversion (wenn Testversion)
     */
    public function get_main_version_id($module_data) {
        return isset($module_data['main_version_id']) ? $module_data['main_version_id'] : null;
    }

    /**
     * Hole ID der Testversion (wenn Hauptversion)
     */
    public function get_test_version_id($module_data) {
        return isset($module_data['has_test_version']) ? $module_data['has_test_version'] : null;
    }

    /**
     * Erstelle Testversion eines Moduls
     *
     * @param string $module_id ID des Hauptmoduls
     * @return array|WP_Error Ergebnis mit test_id oder Fehler
     */
    public function create_test_version($module_id) {
        $modules_path = DGPTM_SUITE_PATH . 'modules/';

        // Finde Hauptmodul
        $main_module_path = $this->find_module_path($module_id);
        if (!$main_module_path) {
            return new WP_Error('module_not_found', 'Hauptmodul nicht gefunden.');
        }

        // Lade Hauptmodul-Config
        $main_config_file = $main_module_path . '/module.json';
        if (!file_exists($main_config_file)) {
            return new WP_Error('config_not_found', 'module.json nicht gefunden.');
        }

        $main_config = json_decode(file_get_contents($main_config_file), true);
        if (!$main_config) {
            return new WP_Error('config_invalid', 'Ungültige module.json.');
        }

        // Prüfe ob bereits eine Testversion existiert
        if ($this->has_test_version($main_config)) {
            return new WP_Error('test_exists', 'Modul hat bereits eine Testversion.');
        }

        // Erstelle Test-ID
        $test_id = $module_id . '-test';
        $category = $main_config['category'];
        $test_module_path = $modules_path . $category . '/' . $test_id;

        // Prüfe ob Test-Verzeichnis bereits existiert
        if (file_exists($test_module_path)) {
            return new WP_Error('test_dir_exists', 'Test-Verzeichnis existiert bereits.');
        }

        // Kopiere Modul-Verzeichnis rekursiv
        if (!$this->copy_directory($main_module_path, $test_module_path)) {
            return new WP_Error('copy_failed', 'Fehler beim Kopieren des Moduls.');
        }

        // Aktualisiere Test-Config
        $test_config = $main_config;
        $test_config['id'] = $test_id;
        $test_config['name'] = $main_config['name'] . ' (TEST)';
        $test_config['description'] = '[TESTVERSION] ' . $main_config['description'];
        $test_config['test_version'] = true;
        $test_config['main_version_id'] = $module_id;
        $test_config['active'] = false;
        unset($test_config['has_test_version']); // Test kann keine eigene Test haben

        // Speichere Test-Config
        $test_config_file = $test_module_path . '/module.json';
        if (!file_put_contents($test_config_file, json_encode($test_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            // Cleanup bei Fehler
            $this->delete_directory($test_module_path);
            return new WP_Error('config_write_failed', 'Fehler beim Schreiben der Test-Config.');
        }

        // Verknüpfe mit Hauptmodul
        $link_result = $this->link_versions($module_id, $test_id);
        if (is_wp_error($link_result)) {
            // Cleanup bei Fehler
            $this->delete_directory($test_module_path);
            return $link_result;
        }

        DGPTM_Logger::log('Test-Version erstellt: ' . $test_id . ' von ' . $module_id);

        return [
            'success' => true,
            'test_id' => $test_id,
            'test_path' => $test_module_path,
            'main_id' => $module_id
        ];
    }

    /**
     * Verknüpfe Haupt- und Testversion
     */
    public function link_versions($main_id, $test_id) {
        $main_path = $this->find_module_path($main_id);
        $test_path = $this->find_module_path($test_id);

        if (!$main_path || !$test_path) {
            return new WP_Error('module_not_found', 'Modul nicht gefunden.');
        }

        // Aktualisiere Hauptmodul
        $main_config_file = $main_path . '/module.json';
        $main_config = json_decode(file_get_contents($main_config_file), true);
        $main_config['has_test_version'] = $test_id;
        file_put_contents($main_config_file, json_encode($main_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Aktualisiere Testmodul
        $test_config_file = $test_path . '/module.json';
        $test_config = json_decode(file_get_contents($test_config_file), true);
        $test_config['main_version_id'] = $main_id;
        $test_config['test_version'] = true;
        file_put_contents($test_config_file, json_encode($test_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        DGPTM_Logger::log('Versionen verknüpft: ' . $main_id . ' <-> ' . $test_id);

        return ['success' => true];
    }

    /**
     * Trenne Verknüpfung zwischen Haupt- und Testversion
     */
    public function unlink_versions($main_id, $test_id) {
        $main_path = $this->find_module_path($main_id);
        $test_path = $this->find_module_path($test_id);

        if ($main_path && file_exists($main_path . '/module.json')) {
            $main_config = json_decode(file_get_contents($main_path . '/module.json'), true);
            unset($main_config['has_test_version']);
            file_put_contents($main_path . '/module.json', json_encode($main_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        if ($test_path && file_exists($test_path . '/module.json')) {
            $test_config = json_decode(file_get_contents($test_path . '/module.json'), true);
            unset($test_config['main_version_id']);
            $test_config['test_version'] = false;
            file_put_contents($test_path . '/module.json', json_encode($test_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        DGPTM_Logger::log('Versionsverknüpfung getrennt: ' . $main_id . ' <-> ' . $test_id);

        return ['success' => true];
    }

    /**
     * Merge Testversion in Hauptversion
     *
     * @param string $test_id ID der Testversion
     * @return array|WP_Error Ergebnis oder Fehler
     */
    public function merge_test_to_main($test_id) {
        $test_path = $this->find_module_path($test_id);
        if (!$test_path) {
            return new WP_Error('test_not_found', 'Testversion nicht gefunden.');
        }

        // Lade Test-Config
        $test_config = json_decode(file_get_contents($test_path . '/module.json'), true);
        if (!$this->is_test_version($test_config)) {
            return new WP_Error('not_test_version', 'Modul ist keine Testversion.');
        }

        $main_id = $this->get_main_version_id($test_config);
        if (!$main_id) {
            return new WP_Error('main_not_linked', 'Hauptversion nicht verknüpft.');
        }

        $main_path = $this->find_module_path($main_id);
        if (!$main_path) {
            return new WP_Error('main_not_found', 'Hauptversion nicht gefunden.');
        }

        // Speichere Hauptversion-Status
        $settings = get_option('dgptm_suite_settings', []);
        $main_was_active = isset($settings['active_modules'][$main_id]) && $settings['active_modules'][$main_id];

        // Backup der Hauptversion erstellen
        $backup_path = $main_path . '_backup_' . time();
        if (!$this->copy_directory($main_path, $backup_path)) {
            return new WP_Error('backup_failed', 'Fehler beim Erstellen des Backups.');
        }

        // Lösche Hauptversion-Inhalt (außer module.json)
        $main_config = json_decode(file_get_contents($main_path . '/module.json'), true);
        $this->delete_directory_content($main_path, ['module.json']);

        // Kopiere Testversion-Dateien (außer module.json)
        if (!$this->copy_directory_content($test_path, $main_path, ['module.json'])) {
            // Restore bei Fehler
            $this->delete_directory($main_path);
            rename($backup_path, $main_path);
            return new WP_Error('merge_failed', 'Fehler beim Mergen. Backup wiederhergestellt.');
        }

        // Aktualisiere Hauptversion-Config mit neuer Version
        $main_config['version'] = $test_config['version'];
        $main_config['description'] = str_replace('[TESTVERSION] ', '', $test_config['description']);

        // Entferne Test-Verknüpfung
        unset($main_config['has_test_version']);

        file_put_contents($main_path . '/module.json', json_encode($main_config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        // Lösche Testversion
        $this->delete_directory($test_path);

        // Lösche Backup nach erfolgreichem Merge
        $this->delete_directory($backup_path);

        // Reaktiviere Hauptversion falls sie vorher aktiv war
        if ($main_was_active) {
            $settings['active_modules'][$main_id] = true;
            if (isset($settings['active_modules'][$test_id])) {
                unset($settings['active_modules'][$test_id]);
            }
            update_option('dgptm_suite_settings', $settings);
        }

        DGPTM_Logger::log('Test-Version gemerged: ' . $test_id . ' -> ' . $main_id);

        return [
            'success' => true,
            'main_id' => $main_id,
            'message' => 'Testversion erfolgreich in Hauptversion gemerged.'
        ];
    }

    /**
     * Automatisches Switching bei Aktivierung
     *
     * @param string $module_id ID des zu aktivierenden Moduls
     * @param array $module_data Modul-Daten
     * @return array|WP_Error Ergebnis oder Fehler
     */
    public function handle_activation_switch($module_id, $module_data) {
        $settings = get_option('dgptm_suite_settings', []);

        // Fall 1: Testversion wird aktiviert -> Hauptversion deaktivieren
        if ($this->is_test_version($module_data)) {
            $main_id = $this->get_main_version_id($module_data);
            if ($main_id && isset($settings['active_modules'][$main_id]) && $settings['active_modules'][$main_id]) {
                $settings['active_modules'][$main_id] = false;
                update_option('dgptm_suite_settings', $settings);
                DGPTM_Logger::log('Hauptversion deaktiviert: ' . $main_id . ' (Testversion aktiviert: ' . $module_id . ')');
            }
        }

        // Fall 2: Hauptversion wird aktiviert -> Testversion deaktivieren
        if ($this->has_test_version($module_data)) {
            $test_id = $this->get_test_version_id($module_data);
            if ($test_id && isset($settings['active_modules'][$test_id]) && $settings['active_modules'][$test_id]) {
                $settings['active_modules'][$test_id] = false;
                update_option('dgptm_suite_settings', $settings);
                DGPTM_Logger::log('Testversion deaktiviert: ' . $test_id . ' (Hauptversion aktiviert: ' . $module_id . ')');
            }
        }

        return ['success' => true];
    }

    /**
     * Rollback bei fehlgeschlagener Aktivierung
     */
    public function handle_activation_rollback($failed_module_id, $module_data) {
        $settings = get_option('dgptm_suite_settings', []);

        // Wenn Testversion fehlschlägt -> Hauptversion wieder aktivieren
        if ($this->is_test_version($module_data)) {
            $main_id = $this->get_main_version_id($module_data);
            if ($main_id) {
                $settings['active_modules'][$main_id] = true;
                $settings['active_modules'][$failed_module_id] = false;
                update_option('dgptm_suite_settings', $settings);
                DGPTM_Logger::log('Rollback: Hauptversion reaktiviert: ' . $main_id . ' (Test fehlgeschlagen: ' . $failed_module_id . ')');
            }
        }

        return ['success' => true];
    }

    /**
     * Lösche Testversion
     */
    public function delete_test_version($test_id) {
        $test_path = $this->find_module_path($test_id);
        if (!$test_path) {
            return new WP_Error('test_not_found', 'Testversion nicht gefunden.');
        }

        $test_config = json_decode(file_get_contents($test_path . '/module.json'), true);
        if (!$this->is_test_version($test_config)) {
            return new WP_Error('not_test_version', 'Modul ist keine Testversion.');
        }

        $main_id = $this->get_main_version_id($test_config);

        // Trenne Verknüpfung
        if ($main_id) {
            $this->unlink_versions($main_id, $test_id);
        }

        // Deaktiviere Test falls aktiv
        $settings = get_option('dgptm_suite_settings', []);
        if (isset($settings['active_modules'][$test_id])) {
            $settings['active_modules'][$test_id] = false;
            update_option('dgptm_suite_settings', $settings);
        }

        // Lösche Verzeichnis
        $this->delete_directory($test_path);

        DGPTM_Logger::log('Testversion gelöscht: ' . $test_id);

        return ['success' => true, 'message' => 'Testversion gelöscht.'];
    }

    /**
     * Hilfsfunktion: Finde Modul-Pfad
     */
    private function find_module_path($module_id) {
        $modules_path = DGPTM_SUITE_PATH . 'modules/';
        $categories = ['core-infrastructure', 'business', 'payment', 'auth', 'media', 'content', 'acf-tools', 'utilities'];

        foreach ($categories as $category) {
            $path = $modules_path . $category . '/' . $module_id;
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    /**
     * Hilfsfunktion: Kopiere Verzeichnis rekursiv
     */
    private function copy_directory($source, $destination) {
        if (!is_dir($source)) {
            return false;
        }

        if (!is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $dir = opendir($source);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $source_path = $source . '/' . $file;
            $dest_path = $destination . '/' . $file;

            if (is_dir($source_path)) {
                $this->copy_directory($source_path, $dest_path);
            } else {
                copy($source_path, $dest_path);
            }
        }
        closedir($dir);

        return true;
    }

    /**
     * Hilfsfunktion: Kopiere Verzeichnis-Inhalt (mit Ausnahmen)
     */
    private function copy_directory_content($source, $destination, $exclude = []) {
        if (!is_dir($source)) {
            return false;
        }

        $dir = opendir($source);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..' || in_array($file, $exclude)) {
                continue;
            }

            $source_path = $source . '/' . $file;
            $dest_path = $destination . '/' . $file;

            if (is_dir($source_path)) {
                $this->copy_directory($source_path, $dest_path);
            } else {
                copy($source_path, $dest_path);
            }
        }
        closedir($dir);

        return true;
    }

    /**
     * Hilfsfunktion: Lösche Verzeichnis rekursiv
     */
    private function delete_directory($path) {
        if (!is_dir($path)) {
            return false;
        }

        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            $file_path = $path . '/' . $file;
            if (is_dir($file_path)) {
                $this->delete_directory($file_path);
            } else {
                unlink($file_path);
            }
        }

        return rmdir($path);
    }

    /**
     * Hilfsfunktion: Lösche Verzeichnis-Inhalt (mit Ausnahmen)
     */
    private function delete_directory_content($path, $exclude = []) {
        if (!is_dir($path)) {
            return false;
        }

        $files = array_diff(scandir($path), ['.', '..']);
        foreach ($files as $file) {
            if (in_array($file, $exclude)) {
                continue;
            }

            $file_path = $path . '/' . $file;
            if (is_dir($file_path)) {
                $this->delete_directory($file_path);
            } else {
                unlink($file_path);
            }
        }

        return true;
    }
}
