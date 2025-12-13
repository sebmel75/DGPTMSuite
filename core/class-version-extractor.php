<?php
/**
 * Version Extractor für DGPTM Module
 * Extrahiert Versionsnummer aus PHP-Dateien
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Version_Extractor {

    /**
     * Extrahiere Version aus PHP-Datei
     *
     * @param string $file_path Pfad zur PHP-Datei
     * @return string|null Versionsnummer oder null
     */
    public static function extract_version($file_path) {
        if (!file_exists($file_path)) {
            return null;
        }

        $content = file_get_contents($file_path);

        // Verschiedene Muster für Version-Extraktion
        $patterns = [
            // Version: 1.2.3
            '/Version:\s*([0-9]+\.[0-9]+(?:\.[0-9]+)?)/i',

            // @version 1.2.3
            '/@version\s+([0-9]+\.[0-9]+(?:\.[0-9]+)?)/i',

            // const VERSION = '1.2.3'
            '/const\s+VERSION\s*=\s*[\'"]([0-9]+\.[0-9]+(?:\.[0-9]+)?)[\'"];/i',

            // define('VERSION', '1.2.3')
            '/define\s*\(\s*[\'"].*VERSION[\'"],\s*[\'"]([0-9]+\.[0-9]+(?:\.[0-9]+)?)[\'"]/',

            // private $version = '1.2.3'
            '/(?:private|public|protected)\s+\$version\s*=\s*[\'"]([0-9]+\.[0-9]+(?:\.[0-9]+)?)[\'"];/i',

            // 'version' => '1.2.3'
            '/[\'"]version[\'"]\s*=>\s*[\'"]([0-9]+\.[0-9]+(?:\.[0-9]+)?)[\'"]/',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $content, $matches)) {
                return $matches[1];
            }
        }

        // Fallback: Suche in den ersten 100 Zeilen nach reiner Versionsnummer
        $lines = explode("\n", $content);
        $first_lines = array_slice($lines, 0, 100);

        foreach ($first_lines as $line) {
            if (preg_match('/([0-9]+\.[0-9]+(?:\.[0-9]+)?)/', $line, $matches)) {
                // Prüfe ob es eine vernünftige Version ist (nicht z.B. PHP 7.4)
                $version = $matches[1];
                $parts = explode('.', $version);

                if (count($parts) >= 2 && intval($parts[0]) < 100) {
                    return $version;
                }
            }
        }

        return null;
    }

    /**
     * Extrahiere Version aus Modul
     *
     * @param string $module_id Modul-ID
     * @return string Versionsnummer oder 'unknown'
     */
    public static function get_module_version($module_id) {
        $module_loader = dgptm_suite()->get_module_loader();
        $module_path = $module_loader->get_module_path($module_id);
        $config = $module_loader->get_module_config($module_id);

        if (!$module_path || !$config) {
            return 'unknown';
        }

        // Hauptdatei des Moduls
        $main_file = $module_path . $config['main_file'];

        // Version aus Hauptdatei extrahieren
        $version = self::extract_version($main_file);

        // Fallback: Version aus module.json
        if ($version === null && isset($config['version'])) {
            $version = $config['version'];
        }

        return $version ?? 'unknown';
    }

    /**
     * Aktualisiere alle Modul-Versionen in module.json
     *
     * @return array Statistik
     */
    public static function update_all_module_versions() {
        $module_loader = dgptm_suite()->get_module_loader();
        $available_modules = $module_loader->get_available_modules();

        $updated = 0;
        $failed = 0;
        $unchanged = 0;

        foreach ($available_modules as $module_id => $module_info) {
            $module_path = $module_info['path'];
            $config = $module_info['config'];
            $main_file = $module_path . $config['main_file'];

            // Version aus Hauptdatei extrahieren
            $extracted_version = self::extract_version($main_file);

            if ($extracted_version === null) {
                $failed++;
                continue;
            }

            // Vergleiche mit module.json Version
            $current_version = $config['version'] ?? null;

            if ($current_version === $extracted_version) {
                $unchanged++;
                continue;
            }

            // Aktualisiere module.json
            $config['version'] = $extracted_version;
            $config_file = $module_path . 'module.json';

            $result = file_put_contents(
                $config_file,
                json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
            );

            if ($result !== false) {
                $updated++;
            } else {
                $failed++;
            }
        }

        return [
            'total' => count($available_modules),
            'updated' => $updated,
            'unchanged' => $unchanged,
            'failed' => $failed,
        ];
    }
}
