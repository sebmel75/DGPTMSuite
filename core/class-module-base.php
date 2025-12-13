<?php
/**
 * Basis-Klasse für alle DGPTM-Module
 */

if (!defined('ABSPATH')) {
    exit;
}

abstract class DGPTM_Module_Base {

    protected $module_id;
    protected $module_name;
    protected $module_version;
    protected $module_path;
    protected $module_url;
    protected $module_config;

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->load_config();
        $this->init();
    }

    /**
     * Modul-Konfiguration laden
     */
    private function load_config() {
        $config_file = $this->module_path . '/module.json';

        if (file_exists($config_file)) {
            $config = json_decode(file_get_contents($config_file), true);

            if ($config) {
                $this->module_id = $config['id'] ?? '';
                $this->module_name = $config['name'] ?? '';
                $this->module_version = $config['version'] ?? '1.0.0';
                $this->module_config = $config;
            }
        }
    }

    /**
     * Initialisierung (muss von Kindklasse implementiert werden)
     */
    abstract protected function init();

    /**
     * Modul-ID abrufen
     */
    public function get_id() {
        return $this->module_id;
    }

    /**
     * Modul-Name abrufen
     */
    public function get_name() {
        return $this->module_name;
    }

    /**
     * Modul-Version abrufen
     */
    public function get_version() {
        return $this->module_version;
    }

    /**
     * Modul-Pfad abrufen
     */
    public function get_path() {
        return $this->module_path;
    }

    /**
     * Modul-URL abrufen
     */
    public function get_url() {
        return $this->module_url;
    }

    /**
     * Modul-Konfiguration abrufen
     */
    public function get_config() {
        return $this->module_config;
    }

    /**
     * Abhängigkeiten abrufen
     */
    public function get_dependencies() {
        return $this->module_config['dependencies'] ?? [];
    }

    /**
     * Optionale Abhängigkeiten abrufen
     */
    public function get_optional_dependencies() {
        return $this->module_config['optional_dependencies'] ?? [];
    }

    /**
     * WordPress-Plugin-Abhängigkeiten abrufen
     */
    public function get_wp_dependencies() {
        return $this->module_config['wp_dependencies']['plugins'] ?? [];
    }

    /**
     * Prüfen, ob Modul exportierbar ist
     */
    public function can_export() {
        return $this->module_config['can_export'] ?? true;
    }

    /**
     * Kategorie abrufen
     */
    public function get_category() {
        return $this->module_config['category'] ?? 'utilities';
    }

    /**
     * Icon abrufen
     */
    public function get_icon() {
        return $this->module_config['icon'] ?? 'dashicons-admin-plugins';
    }

    /**
     * Beschreibung abrufen
     */
    public function get_description() {
        return $this->module_config['description'] ?? '';
    }

    /**
     * Autor abrufen
     */
    public function get_author() {
        return $this->module_config['author'] ?? 'Sebastian Melzer';
    }

    /**
     * Hauptdatei abrufen
     */
    public function get_main_file() {
        return $this->module_config['main_file'] ?? '';
    }

    /**
     * Prüfen, ob alle Abhängigkeiten erfüllt sind
     */
    public function check_dependencies() {
        $dependency_manager = dgptm_suite()->get_dependency_manager();
        return $dependency_manager->check_module_dependencies($this->module_id);
    }

    /**
     * Modul-Assets einbinden (überschreibbar)
     */
    public function enqueue_assets() {
        // Kann von Kindklasse überschrieben werden
    }

    /**
     * Admin-Assets einbinden (überschreibbar)
     */
    public function enqueue_admin_assets() {
        // Kann von Kindklasse überschrieben werden
    }
}
