<?php
/**
 * ZIP Generator für DGPTM Plugin Suite
 * Exportiert Module als eigenständige WordPress-Plugins
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_ZIP_Generator {

    /**
     * Modul als eigenständiges Plugin exportieren
     */
    public function export_module_as_plugin($module_id) {
        $module_loader = dgptm_suite()->get_module_loader();
        $module_path = $module_loader->get_module_path($module_id);
        $module_config = $module_loader->get_module_config($module_id);

        if (!$module_path || !$module_config) {
            return new WP_Error('module_not_found', __('Module not found.', 'dgptm-suite'));
        }

        // Export-Verzeichnis erstellen
        $export_dir = DGPTM_SUITE_PATH . 'exports/';

        if (!is_dir($export_dir)) {
            wp_mkdir_p($export_dir);
        }

        // Temporäres Verzeichnis für Plugin-Struktur
        $temp_dir = $export_dir . 'temp_' . $module_id . '_' . time() . '/';
        wp_mkdir_p($temp_dir);

        // Plugin-Struktur erstellen
        $plugin_dir = $temp_dir . $module_id . '/';
        wp_mkdir_p($plugin_dir);

        // Alle Dateien kopieren
        $this->copy_directory($module_path, $plugin_dir);

        // README.txt generieren
        $this->generate_readme($plugin_dir, $module_config);

        // Hauptdatei anpassen (Plugin-Header hinzufügen)
        $this->add_plugin_header($plugin_dir, $module_config);

        // Abhängigkeiten einbetten (falls vorhanden)
        $this->embed_dependencies($plugin_dir, $module_id, $module_config);

        // ZIP erstellen
        $zip_name = $module_id . '-' . $module_config['version'] . '.zip';
        $zip_path = $export_dir . $zip_name;

        $result = $this->create_zip($temp_dir, $zip_path);

        // Temp-Verzeichnis löschen
        $this->delete_directory($temp_dir);

        if ($result) {
            return [
                'success' => true,
                'file' => $zip_name,
                'path' => $zip_path,
                'size' => filesize($zip_path),
                'download_url' => DGPTM_SUITE_URL . 'exports/' . $zip_name,
            ];
        }

        return new WP_Error('zip_creation_failed', __('ZIP file creation failed.', 'dgptm-suite'));
    }

    /**
     * Plugin-Header zur Hauptdatei hinzufügen
     */
    private function add_plugin_header($plugin_dir, $config) {
        $main_file = $config['main_file'] ?? '';

        if (empty($main_file)) {
            return;
        }

        $main_file_path = $plugin_dir . $main_file;

        if (!file_exists($main_file_path)) {
            return;
        }

        $content = file_get_contents($main_file_path);

        // Prüfen, ob bereits Plugin-Header vorhanden
        if (strpos($content, 'Plugin Name:') !== false) {
            return; // Header bereits vorhanden
        }

        // Plugin-Header generieren
        $header = $this->generate_plugin_header($config);

        // Header am Anfang einfügen (nach <?php)
        $content = preg_replace('/^<\?php\s*/i', "<?php\n" . $header . "\n", $content, 1);

        file_put_contents($main_file_path, $content);
    }

    /**
     * Plugin-Header generieren
     */
    private function generate_plugin_header($config) {
        $header = "/**\n";
        $header .= " * Plugin Name: " . ($config['name'] ?? 'DGPTM Module') . "\n";

        if (!empty($config['description'])) {
            $header .= " * Description: " . $config['description'] . "\n";
        }

        $header .= " * Version: " . ($config['version'] ?? '1.0.0') . "\n";
        $header .= " * Author: " . ($config['author'] ?? 'Sebastian Melzer') . "\n";
        $header .= " * Text Domain: " . ($config['id'] ?? 'dgptm-module') . "\n";

        if (!empty($config['requires_php'])) {
            $header .= " * Requires PHP: " . $config['requires_php'] . "\n";
        }

        if (!empty($config['requires_wp'])) {
            $header .= " * Requires at least: " . $config['requires_wp'] . "\n";
        }

        $header .= " */\n";

        return $header;
    }

    /**
     * README.txt generieren
     */
    private function generate_readme($plugin_dir, $config) {
        $readme = "=== " . ($config['name'] ?? 'DGPTM Module') . " ===\n";
        $readme .= "Contributors: sebastianmelzer\n";
        $readme .= "Tags: dgptm\n";
        $readme .= "Requires at least: " . ($config['requires_wp'] ?? '5.8') . "\n";
        $readme .= "Tested up to: 6.7\n";
        $readme .= "Requires PHP: " . ($config['requires_php'] ?? '7.4') . "\n";
        $readme .= "Stable tag: " . ($config['version'] ?? '1.0.0') . "\n";
        $readme .= "License: GPLv2 or later\n";
        $readme .= "License URI: https://www.gnu.org/licenses/gpl-2.0.html\n\n";

        $readme .= ($config['description'] ?? '') . "\n\n";

        $readme .= "== Description ==\n\n";
        $readme .= ($config['description'] ?? '') . "\n\n";

        // Abhängigkeiten auflisten
        if (!empty($config['dependencies']) || !empty($config['wp_dependencies']['plugins'])) {
            $readme .= "== Requirements ==\n\n";

            if (!empty($config['wp_dependencies']['plugins'])) {
                $readme .= "This plugin requires the following WordPress plugins to be installed and active:\n\n";
                foreach ($config['wp_dependencies']['plugins'] as $plugin) {
                    $readme .= "* " . ucfirst(str_replace('-', ' ', $plugin)) . "\n";
                }
                $readme .= "\n";
            }

            if (!empty($config['dependencies'])) {
                $readme .= "This plugin requires the following DGPTM modules:\n\n";
                foreach ($config['dependencies'] as $dep) {
                    $readme .= "* " . ucfirst(str_replace('-', ' ', $dep)) . "\n";
                }
                $readme .= "\n";
            }
        }

        $readme .= "== Installation ==\n\n";
        $readme .= "1. Upload the plugin files to the `/wp-content/plugins/" . $config['id'] . "` directory, or install the plugin through the WordPress plugins screen directly.\n";
        $readme .= "2. Activate the plugin through the 'Plugins' screen in WordPress\n";

        if (!empty($config['wp_dependencies']['plugins']) || !empty($config['dependencies'])) {
            $readme .= "3. Make sure all required plugins/modules are installed and active\n";
        }

        $readme .= "\n== Changelog ==\n\n";
        $readme .= "= " . ($config['version'] ?? '1.0.0') . " =\n";
        $readme .= "* Initial release (exported from DGPTM Plugin Suite)\n";

        file_put_contents($plugin_dir . 'README.txt', $readme);
    }

    /**
     * Abhängigkeiten einbetten (Libraries wie FPDF, Code128)
     */
    private function embed_dependencies($plugin_dir, $module_id, $config) {
        $dependency_manager = dgptm_suite()->get_dependency_manager();
        $module_info = $dependency_manager->get_module_info($module_id);

        if (empty($module_info['libraries'])) {
            return;
        }

        $lib_dir = $plugin_dir . 'libraries/';
        wp_mkdir_p($lib_dir);

        foreach ($module_info['libraries'] as $library) {
            $source_lib = DGPTM_SUITE_PATH . 'libraries/' . $library . '/';

            if (is_dir($source_lib)) {
                $dest_lib = $lib_dir . $library . '/';
                $this->copy_directory($source_lib, $dest_lib);
            }
        }
    }

    /**
     * ZIP-Datei erstellen
     */
    public function create_zip($source, $destination) {
        if (!extension_loaded('zip') || !class_exists('ZipArchive')) {
            return false;
        }

        $zip = new ZipArchive();

        if ($zip->open($destination, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return false;
        }

        $source = str_replace('\\', '/', realpath($source));

        if (is_dir($source)) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $item_path = str_replace('\\', '/', $item->getPathname());
                $local_path = str_replace($source . '/', '', $item_path);

                if ($item->isDir()) {
                    $zip->addEmptyDir($local_path);
                } else {
                    $zip->addFile($item_path, $local_path);
                }
            }
        } elseif (is_file($source)) {
            $zip->addFile($source, basename($source));
        }

        return $zip->close();
    }

    /**
     * Verzeichnis rekursiv kopieren
     */
    private function copy_directory($source, $destination) {
        if (!is_dir($destination)) {
            wp_mkdir_p($destination);
        }

        $items = scandir($source);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $source_path = $source . '/' . $item;
            $dest_path = $destination . '/' . $item;

            if (is_dir($source_path)) {
                $this->copy_directory($source_path, $dest_path);
            } else {
                copy($source_path, $dest_path);
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
     * Mehrere Module gleichzeitig exportieren
     */
    public function export_multiple_modules($module_ids) {
        $results = [];

        foreach ($module_ids as $module_id) {
            $result = $this->export_module_as_plugin($module_id);
            $results[$module_id] = $result;
        }

        return $results;
    }
}
