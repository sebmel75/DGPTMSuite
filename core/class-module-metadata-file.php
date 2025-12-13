<?php
/**
 * Module Metadata Manager (File-Based) für DGPTM Plugin Suite
 * Speichert Metadaten direkt in module.json statt in wp_options
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Module_Metadata_File {

    private static $instance = null;
    private $modules_base_path;
    private $categories_cache = null;
    private $migrated = false;

    /**
     * Singleton Instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->modules_base_path = DGPTM_SUITE_PATH . 'modules/';

        // Auto-Migration beim ersten Zugriff (nur einmal pro Request)
        add_action('init', [$this, 'migrate_from_options'], 5);
    }

    /**
     * Hole Kategorien-Definitionen
     */
    public function get_categories() {
        if ($this->categories_cache !== null) {
            return $this->categories_cache;
        }

        $categories_file = DGPTM_SUITE_PATH . 'categories.json';

        if (!file_exists($categories_file)) {
            $this->categories_cache = ['categories' => [], 'flags' => []];
            return $this->categories_cache;
        }

        $data = @json_decode(file_get_contents($categories_file), true);
        $this->categories_cache = $data ?: ['categories' => [], 'flags' => []];

        return $this->categories_cache;
    }

    /**
     * Hole alle verfügbaren Flags
     */
    public function get_available_flags() {
        $categories = $this->get_categories();
        return $categories['flags'] ?? [];
    }

    /**
     * Finde module.json Pfad für Modul-ID
     */
    private function find_module_json_path($module_id) {
        // Suche rekursiv in allen Unterverzeichnissen
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->modules_base_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'module.json') {
                $config = @json_decode(file_get_contents($file->getPathname()), true);
                if ($config && isset($config['id']) && $config['id'] === $module_id) {
                    return $file->getPathname();
                }
            }
        }

        return null;
    }

    /**
     * Lade Modul-Konfiguration
     */
    public function load_module_config($module_id) {
        $path = $this->find_module_json_path($module_id);

        if (!$path || !file_exists($path)) {
            return null;
        }

        $config = @json_decode(file_get_contents($path), true);
        return $config ?: null;
    }

    /**
     * Speichere Modul-Konfiguration
     */
    public function save_module_config($module_id, $config) {
        $path = $this->find_module_json_path($module_id);

        if (!$path) {
            DGPTM_Logger::error("Metadata: module.json für Modul '{$module_id}' nicht gefunden");
            return false;
        }

        // Pretty print JSON für bessere Lesbarkeit
        $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            DGPTM_Logger::error("Metadata: JSON-Kodierung für Modul '{$module_id}' fehlgeschlagen");
            return false;
        }

        $result = file_put_contents($path, $json);

        if ($result === false) {
            DGPTM_Logger::error("Metadata: Schreiben der module.json für '{$module_id}' fehlgeschlagen");
            return false;
        }

        do_action('dgptm_suite_module_config_updated', $module_id, $config);

        return true;
    }

    /**
     * Hole Metadaten für ein Modul
     */
    public function get_module_metadata($module_id) {
        $config = $this->load_module_config($module_id);

        if (!$config) {
            return [
                'flags' => [],
                'comment' => '',
                'test_version_link' => '',
                'main_version_link' => ''
            ];
        }

        // Konvertiere Flag-IDs zu vollständigen Flag-Objekten
        $flag_ids = $config['flags'] ?? [];
        $flags = $this->get_flag_objects($flag_ids);

        // Extrahiere Kommentar-Text (kann String oder Object sein)
        $comment_raw = $config['comment'] ?? '';
        $comment_text = '';

        if (is_array($comment_raw) && isset($comment_raw['text'])) {
            // Kommentar ist ein Objekt mit text, timestamp, user_id
            $comment_text = $comment_raw['text'];
        } elseif (is_string($comment_raw)) {
            // Kommentar ist bereits ein String
            $comment_text = $comment_raw;
        }

        return [
            'flags' => $flags,
            'comment' => $comment_text,
            'test_version_link' => $config['test_version_link'] ?? '',
            'main_version_link' => $config['main_version_link'] ?? '',
            'test_module_id' => $config['test_version_link'] ?? '',
            'main_module_id' => $config['main_version_link'] ?? ''
        ];
    }

    /**
     * Konvertiere Flag-IDs zu vollständigen Flag-Objekten mit Labels
     */
    private function get_flag_objects($flag_ids) {
        if (empty($flag_ids) || !is_array($flag_ids)) {
            return [];
        }

        // Normalisiere Flag-Array (behandle alte Formate)
        $flag_ids = $this->normalize_flag_array($flag_ids);

        $categories = $this->get_categories();
        $available_flags = $categories['flags'] ?? [];
        $flag_objects = [];

        foreach ($flag_ids as $flag_id) {
            // Skip leere oder ungültige Einträge
            if (empty($flag_id) || !is_string($flag_id)) {
                continue;
            }

            if (isset($available_flags[$flag_id])) {
                $flag_objects[] = [
                    'type' => $flag_id,
                    'label' => $available_flags[$flag_id]['label'] ?? $flag_id,
                    'color' => $available_flags[$flag_id]['color'] ?? '#646970',
                    'icon' => $available_flags[$flag_id]['icon'] ?? 'dashicons-flag'
                ];
            } else {
                // Fallback für unbekannte Flags
                $flag_objects[] = [
                    'type' => $flag_id,
                    'label' => ucfirst(str_replace(['-', '_'], ' ', $flag_id)),
                    'color' => '#646970',
                    'icon' => 'dashicons-flag'
                ];
            }
        }

        return $flag_objects;
    }

    /**
     * Normalisiere Flag-Array - konvertiert alte Formate
     * Alte Formate: ['flag1' => 'Label1', 'flag2' => 'Label2'] oder gemischte Arrays
     * Neues Format: ['flag1', 'flag2']
     */
    private function normalize_flag_array($flags) {
        if (!is_array($flags)) {
            return [];
        }

        $normalized = [];

        foreach ($flags as $key => $value) {
            // Wenn Value ein String ist und Key numerisch, dann ist es bereits normalisiert
            if (is_numeric($key) && is_string($value)) {
                $normalized[] = $value;
            }
            // Wenn Key ein String ist (altes assoziatives Format)
            elseif (is_string($key)) {
                $normalized[] = $key;
            }
            // Wenn Value ein String ist (Fallback)
            elseif (is_string($value)) {
                $normalized[] = $value;
            }
        }

        return array_unique(array_values($normalized));
    }

    /**
     * Hole CSS-Klasse für Flag-Badge
     */
    public function get_flag_badge_class($flag_type) {
        $classes = [
            'testing' => 'dgptm-flag-testing',
            'deprecated' => 'dgptm-flag-deprecated',
            'important' => 'dgptm-flag-important',
            'development' => 'dgptm-flag-dev',
            'production' => 'dgptm-flag-production',
            'beta' => 'dgptm-flag-beta',
        ];

        return $classes[$flag_type] ?? 'dgptm-flag-default';
    }

    /**
     * Füge Flag hinzu
     */
    public function add_flag($module_id, $flag, $label = '') {
        $config = $this->load_module_config($module_id);

        if (!$config) {
            DGPTM_Logger::warning("Metadata: Modul '{$module_id}' nicht gefunden - Flag kann nicht gesetzt werden");
            return false;
        }

        if (!isset($config['flags'])) {
            $config['flags'] = [];
        }

        if (!in_array($flag, $config['flags'])) {
            $config['flags'][] = $flag;
            return $this->save_module_config($module_id, $config);
        }

        return true;
    }

    /**
     * Entferne Flag
     */
    public function remove_flag($module_id, $flag) {
        $config = $this->load_module_config($module_id);

        if (!$config) {
            return false;
        }

        if (isset($config['flags'])) {
            $config['flags'] = array_values(array_diff($config['flags'], [$flag]));
            return $this->save_module_config($module_id, $config);
        }

        return true;
    }

    /**
     * Hole alle Flags eines Moduls (als vollständige Objekte)
     */
    public function get_flags($module_id) {
        $metadata = $this->get_module_metadata($module_id);
        return $metadata['flags'];
    }

    /**
     * Hole alle Flag-IDs eines Moduls (nur die IDs, kein Objekt)
     */
    public function get_flag_ids($module_id) {
        $config = $this->load_module_config($module_id);
        if (!$config) {
            return [];
        }
        return $config['flags'] ?? [];
    }

    /**
     * Setze Kommentar
     */
    public function set_comment($module_id, $comment) {
        $config = $this->load_module_config($module_id);

        if (!$config) {
            return false;
        }

        $config['comment'] = $comment;
        return $this->save_module_config($module_id, $config);
    }

    /**
     * Hole Kommentar
     */
    public function get_comment($module_id) {
        $metadata = $this->get_module_metadata($module_id);
        return $metadata['comment'];
    }

    /**
     * Verknüpfe Test-Version
     */
    public function link_test_version($main_module_id, $test_module_id) {
        // Setze Link beim Haupt-Modul
        $main_config = $this->load_module_config($main_module_id);
        if ($main_config) {
            $main_config['test_version_link'] = $test_module_id;
            $this->save_module_config($main_module_id, $main_config);
        }

        // Setze Link beim Test-Modul (umgekehrt)
        $test_config = $this->load_module_config($test_module_id);
        if ($test_config) {
            $test_config['main_version_link'] = $main_module_id;
            $this->save_module_config($test_module_id, $test_config);
        }

        return true;
    }

    /**
     * Entferne Test-Version-Link
     */
    public function unlink_test_version($module_id) {
        $config = $this->load_module_config($module_id);

        if (!$config) {
            return false;
        }

        // Wenn dies ein Haupt-Modul ist
        if (isset($config['test_version_link']) && !empty($config['test_version_link'])) {
            $test_id = $config['test_version_link'];

            // Entferne Link beim Test-Modul
            $test_config = $this->load_module_config($test_id);
            if ($test_config) {
                unset($test_config['main_version_link']);
                $this->save_module_config($test_id, $test_config);
            }

            // Entferne Link beim Haupt-Modul
            unset($config['test_version_link']);
            $this->save_module_config($module_id, $config);
        }

        // Wenn dies ein Test-Modul ist
        if (isset($config['main_version_link']) && !empty($config['main_version_link'])) {
            $main_id = $config['main_version_link'];

            // Entferne Link beim Haupt-Modul
            $main_config = $this->load_module_config($main_id);
            if ($main_config) {
                unset($main_config['test_version_link']);
                $this->save_module_config($main_id, $main_config);
            }

            // Entferne Link beim Test-Modul
            unset($config['main_version_link']);
            $this->save_module_config($module_id, $config);
        }

        return true;
    }

    /**
     * Wechsle zwischen Haupt- und Test-Version
     */
    public function switch_version($module_id) {
        $config = $this->load_module_config($module_id);

        if (!$config) {
            return false;
        }

        $settings = get_option('dgptm_suite_settings', []);

        // Ist dies ein Haupt-Modul mit Test-Version?
        if (isset($config['test_version_link']) && !empty($config['test_version_link'])) {
            $test_id = $config['test_version_link'];

            // Deaktiviere Haupt-Modul, aktiviere Test-Modul
            $settings['active_modules'][$module_id] = false;
            $settings['active_modules'][$test_id] = true;

            update_option('dgptm_suite_settings', $settings);
            return $test_id;
        }

        // Ist dies ein Test-Modul mit Haupt-Version?
        if (isset($config['main_version_link']) && !empty($config['main_version_link'])) {
            $main_id = $config['main_version_link'];

            // Deaktiviere Test-Modul, aktiviere Haupt-Modul
            $settings['active_modules'][$module_id] = false;
            $settings['active_modules'][$main_id] = true;

            update_option('dgptm_suite_settings', $settings);
            return $main_id;
        }

        return false;
    }

    /**
     * Prüfe ob Modul ein bestimmtes Flag hat
     */
    public function has_flag($module_id, $flag) {
        $flag_ids = $this->get_flag_ids($module_id);
        return in_array($flag, $flag_ids);
    }

    /**
     * Synchronisiere critical-Flag mit module.json config
     * Wenn module.json critical: true hat, wird automatisch "important" Flag gesetzt
     *
     * @param string $module_id
     * @param array $config
     */
    public function sync_critical_flag($module_id, $config) {
        if (isset($config['critical']) && $config['critical']) {
            // Module ist in config als critical markiert
            // Setze automatisch "important" Flag wenn noch nicht vorhanden
            if (!$this->has_flag($module_id, 'important')) {
                $this->add_flag($module_id, 'important');
            }
        }
    }

    /**
     * Prüfe ob Modul kritisch ist (Flag oder Config)
     *
     * @param string $module_id
     * @param array $config Optional - wenn nicht angegeben wird config geladen
     * @return bool
     */
    public function is_module_critical($module_id, $config = null) {
        // Prüfe ob "important" Flag gesetzt ist
        if ($this->has_flag($module_id, 'important')) {
            return true;
        }

        // Prüfe ob in config critical: true gesetzt ist
        if ($config === null) {
            $config = $this->load_module_config($module_id);
        }

        if ($config && isset($config['critical']) && $config['critical']) {
            return true;
        }

        return false;
    }

    /**
     * Migriere Metadaten von wp_options nach module.json
     */
    public function migrate_from_options() {
        // Nur einmal pro Request
        if ($this->migrated) {
            return;
        }

        $this->migrated = true;

        // Prüfe ob bereits migriert
        $migrated_flag = get_option('dgptm_suite_metadata_migrated', false);
        if ($migrated_flag) {
            return;
        }

        DGPTM_Logger::info('Metadata-Migration: Starte Migration von wp_options nach module.json');

        // Hole alte Metadaten aus wp_options
        $old_metadata = get_option('dgptm_suite_module_metadata', []);

        if (empty($old_metadata)) {
            update_option('dgptm_suite_metadata_migrated', true);
            DGPTM_Logger::info('Metadata-Migration: Keine Metadaten zum Migrieren gefunden');
            return;
        }

        $migrated_count = 0;

        foreach ($old_metadata as $module_id => $data) {
            $config = $this->load_module_config($module_id);

            if (!$config) {
                DGPTM_Logger::warning("Metadata-Migration: Modul '{$module_id}' nicht gefunden - überspringe");
                continue;
            }

            $updated = false;

            // Migriere Flags mit Normalisierung
            if (isset($data['flags']) && is_array($data['flags']) && !empty($data['flags'])) {
                $flags = $this->normalize_flag_array($data['flags']);

                if (!empty($flags)) {
                    $config['flags'] = $flags;
                    $updated = true;
                }
            }

            // Migriere Kommentar
            if (isset($data['comment']) && !empty($data['comment'])) {
                $config['comment'] = $data['comment'];
                $updated = true;
            }

            // Migriere Test-Version-Link
            if (isset($data['test_module_id']) && !empty($data['test_module_id'])) {
                $config['test_version_link'] = $data['test_module_id'];
                $updated = true;
            }

            if (isset($data['main_module_id']) && !empty($data['main_module_id'])) {
                $config['main_version_link'] = $data['main_module_id'];
                $updated = true;
            }

            if ($updated) {
                $this->save_module_config($module_id, $config);
                $migrated_count++;
                DGPTM_Logger::verbose("Metadata-Migration: Metadaten für '{$module_id}' migriert");
            }
        }

        // Setze Migrations-Flag
        update_option('dgptm_suite_metadata_migrated', true);

        DGPTM_Logger::info("Metadata-Migration: Abgeschlossen - {$migrated_count} Module migriert");
    }

    /**
     * Repariere alle Module mit fehlerhaften Flag-Daten
     * ÖFFENTLICHE METHODE für manuellen Aufruf
     */
    public function repair_all_module_flags() {
        $repaired_count = 0;
        $error_count = 0;

        // Durchsuche alle Module
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->modules_base_path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getFilename() === 'module.json') {
                $config = @json_decode(file_get_contents($file->getPathname()), true);

                if (!$config || !isset($config['id'])) {
                    continue;
                }

                $module_id = $config['id'];
                $needs_repair = false;

                // Prüfe und repariere Flags
                if (isset($config['flags']) && is_array($config['flags'])) {
                    $original_flags = $config['flags'];
                    $normalized_flags = $this->normalize_flag_array($config['flags']);

                    // Vergleiche - wenn unterschiedlich, repariere
                    if ($original_flags !== $normalized_flags) {
                        $config['flags'] = $normalized_flags;
                        $needs_repair = true;
                        DGPTM_Logger::info("Flag-Reparatur: Modul '{$module_id}' - Flags normalisiert");
                    }
                }

                if ($needs_repair) {
                    $result = $this->save_module_config($module_id, $config);
                    if ($result) {
                        $repaired_count++;
                    } else {
                        $error_count++;
                        DGPTM_Logger::error("Flag-Reparatur: Konnte '{$module_id}' nicht speichern");
                    }
                }
            }
        }

        $message = "Flag-Reparatur abgeschlossen: {$repaired_count} Module repariert";
        if ($error_count > 0) {
            $message .= ", {$error_count} Fehler";
        }

        DGPTM_Logger::info($message);

        return [
            'repaired' => $repaired_count,
            'errors' => $error_count,
            'message' => $message
        ];
    }
}
