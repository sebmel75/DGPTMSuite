<?php
/**
 * Module Metadata Manager für DGPTM Plugin Suite
 * Verwaltet Flags, Kommentare und Versionsumschaltung für Module
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Module_Metadata {

    private static $instance = null;

    // Option-Name in WordPress
    const OPTION_NAME = 'dgptm_suite_module_metadata';

    // Verfügbare Flag-Typen
    const FLAG_TESTING = 'testing';           // Modul wird getestet
    const FLAG_DEPRECATED = 'deprecated';     // Modul ist veraltet
    const FLAG_IMPORTANT = 'important';       // Wichtiges Modul
    const FLAG_CRITICAL = 'critical';         // Kritisches Modul (höchste Priorität)
    const FLAG_DEV = 'development';           // In Entwicklung
    const FLAG_PRODUCTION = 'production';     // Produktiv im Einsatz
    const FLAG_CUSTOM = 'custom';             // Benutzerdefiniert

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
     * Hole alle Metadaten
     */
    private function get_all_metadata() {
        $metadata = get_option(self::OPTION_NAME, []);

        // Stelle sicher dass es ein Array ist
        if (!is_array($metadata)) {
            $metadata = [];
            update_option(self::OPTION_NAME, $metadata);
        }

        return $metadata;
    }

    /**
     * Speichere alle Metadaten
     */
    private function save_all_metadata($metadata) {
        update_option(self::OPTION_NAME, $metadata);
        wp_cache_delete(self::OPTION_NAME, 'options');
    }

    /**
     * Hole Metadaten für ein Modul
     */
    public function get_module_metadata($module_id) {
        $all_metadata = $this->get_all_metadata();

        // Default-Struktur
        $default = [
            'flags' => [],
            'comment' => '',
            'version_mode' => 'main',  // 'main' oder 'test'
            'test_module_id' => '',    // ID des Test-Moduls
            'main_module_id' => '',    // ID des Haupt-Moduls (wenn dies ein Test-Modul ist)
            'custom_data' => []
        ];

        return isset($all_metadata[$module_id])
            ? array_merge($default, $all_metadata[$module_id])
            : $default;
    }

    /**
     * Setze Metadaten für ein Modul
     */
    public function set_module_metadata($module_id, $metadata) {
        $all_metadata = $this->get_all_metadata();

        // Merge mit existierenden Daten
        if (isset($all_metadata[$module_id])) {
            $all_metadata[$module_id] = array_merge($all_metadata[$module_id], $metadata);
        } else {
            $all_metadata[$module_id] = $metadata;
        }

        $this->save_all_metadata($all_metadata);

        do_action('dgptm_suite_module_metadata_updated', $module_id, $metadata);

        return true;
    }

    // ===========================================
    // FLAGS
    // ===========================================

    /**
     * Füge Flag hinzu
     */
    public function add_flag($module_id, $flag_type, $label = '') {
        $metadata = $this->get_module_metadata($module_id);

        // Flag-Struktur: ['type' => 'testing', 'label' => 'Beta Version', 'added' => timestamp]
        $flag = [
            'type' => $flag_type,
            'label' => !empty($label) ? $label : $this->get_flag_default_label($flag_type),
            'added' => current_time('timestamp'),
            'added_by' => get_current_user_id()
        ];

        // Verhindere Duplikate
        foreach ($metadata['flags'] as $existing_flag) {
            if ($existing_flag['type'] === $flag_type) {
                return false; // Flag existiert bereits
            }
        }

        $metadata['flags'][] = $flag;

        return $this->set_module_metadata($module_id, $metadata);
    }

    /**
     * Entferne Flag
     */
    public function remove_flag($module_id, $flag_type) {
        $metadata = $this->get_module_metadata($module_id);

        $metadata['flags'] = array_values(array_filter($metadata['flags'], function($flag) use ($flag_type) {
            return $flag['type'] !== $flag_type;
        }));

        return $this->set_module_metadata($module_id, $metadata);
    }

    /**
     * Prüfe ob Modul ein bestimmtes Flag hat
     */
    public function has_flag($module_id, $flag_type) {
        $metadata = $this->get_module_metadata($module_id);

        foreach ($metadata['flags'] as $flag) {
            if ($flag['type'] === $flag_type) {
                return true;
            }
        }

        return false;
    }

    /**
     * Hole alle Flags für ein Modul
     */
    public function get_flags($module_id) {
        $metadata = $this->get_module_metadata($module_id);
        return $metadata['flags'];
    }

    /**
     * Standard-Label für Flag-Typen
     */
    private function get_flag_default_label($flag_type) {
        $labels = [
            self::FLAG_TESTING => __('Im Test', 'dgptm-suite'),
            self::FLAG_DEPRECATED => __('Veraltet', 'dgptm-suite'),
            self::FLAG_IMPORTANT => __('Wichtig', 'dgptm-suite'),
            self::FLAG_CRITICAL => __('Kritisch', 'dgptm-suite'),
            self::FLAG_DEV => __('In Entwicklung', 'dgptm-suite'),
            self::FLAG_PRODUCTION => __('Produktiv', 'dgptm-suite'),
            self::FLAG_CUSTOM => __('Benutzerdefiniert', 'dgptm-suite')
        ];

        return isset($labels[$flag_type]) ? $labels[$flag_type] : $flag_type;
    }

    /**
     * Hole Flag-Icon/Badge-Klasse
     */
    public function get_flag_badge_class($flag_type) {
        $classes = [
            self::FLAG_TESTING => 'dgptm-flag-testing',
            self::FLAG_DEPRECATED => 'dgptm-flag-deprecated',
            self::FLAG_IMPORTANT => 'dgptm-flag-important',
            self::FLAG_CRITICAL => 'dgptm-flag-critical',
            self::FLAG_DEV => 'dgptm-flag-dev',
            self::FLAG_PRODUCTION => 'dgptm-flag-production',
            self::FLAG_CUSTOM => 'dgptm-flag-custom'
        ];

        return isset($classes[$flag_type]) ? $classes[$flag_type] : 'dgptm-flag-default';
    }

    // ===========================================
    // KOMMENTARE
    // ===========================================

    /**
     * Setze Kommentar für ein Modul
     */
    public function set_comment($module_id, $comment) {
        $metadata = $this->get_module_metadata($module_id);
        $metadata['comment'] = sanitize_textarea_field($comment);
        $metadata['comment_updated'] = current_time('timestamp');
        $metadata['comment_updated_by'] = get_current_user_id();

        return $this->set_module_metadata($module_id, $metadata);
    }

    /**
     * Hole Kommentar für ein Modul
     */
    public function get_comment($module_id) {
        $metadata = $this->get_module_metadata($module_id);
        return $metadata['comment'];
    }

    // ===========================================
    // VERSIONSUMSCHALTUNG
    // ===========================================

    /**
     * Verknüpfe Haupt- und Test-Modul
     */
    public function link_test_version($main_module_id, $test_module_id) {
        // Setze Test-Modul für Haupt-Modul
        $main_metadata = $this->get_module_metadata($main_module_id);
        $main_metadata['test_module_id'] = $test_module_id;
        $this->set_module_metadata($main_module_id, $main_metadata);

        // Setze Haupt-Modul für Test-Modul
        $test_metadata = $this->get_module_metadata($test_module_id);
        $test_metadata['main_module_id'] = $main_module_id;
        $this->set_module_metadata($test_module_id, $test_metadata);

        return true;
    }

    /**
     * Entferne Verknüpfung
     */
    public function unlink_test_version($module_id) {
        $metadata = $this->get_module_metadata($module_id);

        // Wenn es ein Haupt-Modul ist
        if (!empty($metadata['test_module_id'])) {
            $test_id = $metadata['test_module_id'];

            // Entferne Verknüpfung beim Test-Modul
            $test_metadata = $this->get_module_metadata($test_id);
            $test_metadata['main_module_id'] = '';
            $this->set_module_metadata($test_id, $test_metadata);

            // Entferne Verknüpfung beim Haupt-Modul
            $metadata['test_module_id'] = '';
            $this->set_module_metadata($module_id, $metadata);
        }

        // Wenn es ein Test-Modul ist
        if (!empty($metadata['main_module_id'])) {
            $main_id = $metadata['main_module_id'];

            // Entferne Verknüpfung beim Haupt-Modul
            $main_metadata = $this->get_module_metadata($main_id);
            $main_metadata['test_module_id'] = '';
            $this->set_module_metadata($main_id, $main_metadata);

            // Entferne Verknüpfung beim Test-Modul
            $metadata['main_module_id'] = '';
            $this->set_module_metadata($module_id, $metadata);
        }

        return true;
    }

    /**
     * Wechsle zwischen Haupt- und Test-Version
     */
    public function switch_version($module_id) {
        $metadata = $this->get_module_metadata($module_id);

        // Ist dies ein Haupt-Modul mit Test-Version?
        if (!empty($metadata['test_module_id'])) {
            $test_id = $metadata['test_module_id'];

            // Deaktiviere Haupt-Modul
            $settings = get_option('dgptm_suite_settings', []);
            $settings['active_modules'][$module_id] = false;

            // Aktiviere Test-Modul
            $settings['active_modules'][$test_id] = true;

            update_option('dgptm_suite_settings', $settings);
            wp_cache_delete('dgptm_suite_settings', 'options');

            return [
                'success' => true,
                'switched_to' => 'test',
                'active_module' => $test_id,
                'inactive_module' => $module_id
            ];
        }

        // Ist dies ein Test-Modul mit Haupt-Version?
        if (!empty($metadata['main_module_id'])) {
            $main_id = $metadata['main_module_id'];

            // Deaktiviere Test-Modul
            $settings = get_option('dgptm_suite_settings', []);
            $settings['active_modules'][$module_id] = false;

            // Aktiviere Haupt-Modul
            $settings['active_modules'][$main_id] = true;

            update_option('dgptm_suite_settings', $settings);
            wp_cache_delete('dgptm_suite_settings', 'options');

            return [
                'success' => true,
                'switched_to' => 'main',
                'active_module' => $main_id,
                'inactive_module' => $module_id
            ];
        }

        return [
            'success' => false,
            'error' => __('Keine Test-Version verknüpft', 'dgptm-suite')
        ];
    }

    /**
     * Prüfe ob ein Modul eine Test-Version hat
     */
    public function has_test_version($module_id) {
        $metadata = $this->get_module_metadata($module_id);
        return !empty($metadata['test_module_id']);
    }

    /**
     * Prüfe ob ein Modul ein Test-Modul ist
     */
    public function is_test_module($module_id) {
        $metadata = $this->get_module_metadata($module_id);
        return !empty($metadata['main_module_id']);
    }

    /**
     * Hole aktuell aktive Version (main oder test)
     */
    public function get_active_version($module_id) {
        $settings = get_option('dgptm_suite_settings', []);
        $active_modules = $settings['active_modules'] ?? [];

        $metadata = $this->get_module_metadata($module_id);

        // Wenn dies das Haupt-Modul ist
        if (!empty($metadata['test_module_id'])) {
            $test_id = $metadata['test_module_id'];

            if (isset($active_modules[$test_id]) && $active_modules[$test_id]) {
                return 'test';
            }

            if (isset($active_modules[$module_id]) && $active_modules[$module_id]) {
                return 'main';
            }

            return 'none';
        }

        // Wenn dies ein Test-Modul ist
        if (!empty($metadata['main_module_id'])) {
            $main_id = $metadata['main_module_id'];

            if (isset($active_modules[$module_id]) && $active_modules[$module_id]) {
                return 'test';
            }

            if (isset($active_modules[$main_id]) && $active_modules[$main_id]) {
                return 'main';
            }

            return 'none';
        }

        return 'main'; // Kein Test-Setup
    }

    // ===========================================
    // UTILITY
    // ===========================================

    /**
     * Lösche alle Metadaten für ein Modul
     */
    public function delete_module_metadata($module_id) {
        $all_metadata = $this->get_all_metadata();

        if (isset($all_metadata[$module_id])) {
            unset($all_metadata[$module_id]);
            $this->save_all_metadata($all_metadata);
        }

        return true;
    }

    /**
     * Hole alle verfügbaren Flag-Typen
     */
    public function get_available_flag_types() {
        return [
            self::FLAG_CRITICAL,
            self::FLAG_IMPORTANT,
            self::FLAG_TESTING,
            self::FLAG_PRODUCTION,
            self::FLAG_DEV,
            self::FLAG_DEPRECATED,
            self::FLAG_CUSTOM
        ];
    }

    /**
     * Export Metadaten (für Backup/Migration)
     */
    public function export_metadata() {
        return $this->get_all_metadata();
    }

    /**
     * Import Metadaten (für Backup/Migration)
     */
    public function import_metadata($metadata) {
        if (!is_array($metadata)) {
            return false;
        }

        $this->save_all_metadata($metadata);
        return true;
    }

    /**
     * Prüfe ob Modul kritisch ist (entweder über Flag oder module.json)
     */
    public function is_module_critical($module_id, $config = null) {
        // Prüfe Flag
        if ($this->has_flag($module_id, self::FLAG_CRITICAL)) {
            return true;
        }

        // Prüfe module.json
        if ($config && isset($config['critical']) && $config['critical']) {
            return true;
        }

        return false;
    }

    /**
     * Synchronisiere Critical-Flag mit module.json
     * Wenn module.json critical: true hat, setze automatisch Flag
     */
    public function sync_critical_flag($module_id, $config) {
        if (isset($config['critical']) && $config['critical']) {
            // Module ist in config als critical markiert -> Flag setzen
            if (!$this->has_flag($module_id, self::FLAG_CRITICAL)) {
                $this->add_flag($module_id, self::FLAG_CRITICAL, 'System Critical');
            }
        }
    }
}
