<?php
/**
 * Plugin Name: DGPTM Plugin Suite - Master Controller
 * Plugin URI: https://perfusiologie.de
 * Description: Zentrale Verwaltung aller DGPTM-Module mit individueller Aktivierung, Update-Management und ZIP-Export
 * Version: 3.0.0
 * Author: Sebastian Melzer / DGPTM
 * Author URI: https://dgptm.de
 * License: GPL v2 or later
 * Text Domain: dgptm-suite
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin-Konstanten
define('DGPTM_SUITE_VERSION', '3.0.0');
define('DGPTM_SUITE_FILE', __FILE__);
define('DGPTM_SUITE_PATH', plugin_dir_path(__FILE__));
define('DGPTM_SUITE_URL', plugin_dir_url(__FILE__));
define('DGPTM_SUITE_BASENAME', plugin_basename(__FILE__));

// Core-Klassen laden
require_once DGPTM_SUITE_PATH . 'core/class-logger-installer.php';
require_once DGPTM_SUITE_PATH . 'core/class-logger.php';
require_once DGPTM_SUITE_PATH . 'core/class-dgptm-colors.php';
require_once DGPTM_SUITE_PATH . 'core/class-module-base.php';
require_once DGPTM_SUITE_PATH . 'core/class-dependency-manager.php';
require_once DGPTM_SUITE_PATH . 'core/class-safe-loader.php';
require_once DGPTM_SUITE_PATH . 'core/class-test-version-manager.php';
require_once DGPTM_SUITE_PATH . 'core/class-module-loader.php';
require_once DGPTM_SUITE_PATH . 'core/class-module-metadata.php';
require_once DGPTM_SUITE_PATH . 'core/class-module-metadata-file.php'; // Neue file-basierte Metadata
require_once DGPTM_SUITE_PATH . 'core/class-zip-generator.php';
require_once DGPTM_SUITE_PATH . 'core/class-module-generator.php';
require_once DGPTM_SUITE_PATH . 'core/class-checkout-manager.php';
require_once DGPTM_SUITE_PATH . 'core/class-guide-manager.php';
require_once DGPTM_SUITE_PATH . 'core/class-version-extractor.php';
require_once DGPTM_SUITE_PATH . 'core/class-module-settings-manager.php';
require_once DGPTM_SUITE_PATH . 'core/class-central-settings-registry.php';

// Admin-Klassen laden
if (is_admin()) {
    require_once DGPTM_SUITE_PATH . 'admin/class-plugin-manager.php';
    require_once DGPTM_SUITE_PATH . 'admin/class-module-upload-handler.php';
}

/**
 * Hauptklasse für die DGPTM Plugin Suite
 */
final class DGPTM_Plugin_Suite {

    private static $instance = null;
    private $module_loader = null;
    private $dependency_manager = null;

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
        $this->init();
    }

    /**
     * Initialisierung
     */
    private function init() {
        // Logger initialisieren (für Cleanup-Cron)
        DGPTM_Logger::get_instance();

        // Bei Bedarf Logger-DB erstellen/aktualisieren (nach Plugin-Updates)
        add_action('admin_init', [$this, 'maybe_upgrade_logger_db']);

        // Dependency Manager initialisieren
        $this->dependency_manager = new DGPTM_Dependency_Manager();

        // Module Loader initialisieren
        $this->module_loader = new DGPTM_Module_Loader($this->dependency_manager);

        // Hooks registrieren
        add_action('plugins_loaded', [$this, 'load_textdomain']);

        // Module FRÜHER laden - beim plugins_loaded Hook mit höchster Priorität
        // So können die Module ihre eigenen Hooks richtig registrieren
        add_action('plugins_loaded', [$this, 'load_active_modules'], 1);

        // Admin initialisieren
        if (is_admin()) {
            DGPTM_Plugin_Manager::get_instance();
            // Guide Manager initialisieren für AJAX
            DGPTM_Guide_Manager::get_instance();
        }

        // Activation/Deactivation Hooks
        register_activation_hook(__FILE__, [$this, 'activate']);
        register_deactivation_hook(__FILE__, [$this, 'deactivate']);
    }

    /**
     * Logger-Datenbank bei Bedarf aktualisieren
     */
    public function maybe_upgrade_logger_db() {
        if (DGPTM_Logger_Installer::needs_upgrade()) {
            DGPTM_Logger_Installer::install();
        }
    }

    /**
     * Textdomain laden
     */
    public function load_textdomain() {
        load_plugin_textdomain(
            'dgptm-suite',
            false,
            dirname(DGPTM_SUITE_BASENAME) . '/languages'
        );
    }

    /**
     * Aktive Module laden
     */
    public function load_active_modules() {
        $this->module_loader->load_modules();
    }

    /**
     * Plugin-Aktivierung
     */
    public function activate() {
        // Default-Einstellungen setzen
        if (!get_option('dgptm_suite_settings')) {
            $default_settings = [
                'active_modules' => [
                    // Core-Module standardmäßig aktiv
                    'crm-abruf' => true,
                    'rest-api-extension' => true,
                    'webhook-trigger' => true,
                    'menu-control' => true,
                    'side-restrict' => true,
                ],
                'module_settings' => [],
                'last_update_check' => time(),
                'enable_logging' => false,  // Standard: Nur Warnings/Critical (Info-Logs deaktiviert)
                'enable_verbose_logging' => false,  // Standard: Verbose logging deaktiviert
                'logging' => [
                    'global_level' => 'warning',
                    'db_enabled' => true,
                    'file_enabled' => true,
                    'max_db_entries' => 100000,
                    'module_levels' => []
                ]
            ];
            update_option('dgptm_suite_settings', $default_settings);
        }

        // Logger-Datenbank installieren
        DGPTM_Logger_Installer::install();

        // Flush rewrite rules
        flush_rewrite_rules();
    }

    /**
     * Plugin-Deaktivierung
     */
    public function deactivate() {
        // Rewrite rules löschen
        flush_rewrite_rules();

        // Log-Cleanup Cron Job entfernen (Legacy)
        if (class_exists('DGPTM_Plugin_Manager')) {
            DGPTM_Plugin_Manager::unschedule_log_cleanup();
        }

        // Neuen Logger-Cleanup Cron entfernen
        $timestamp = wp_next_scheduled('dgptm_logs_cleanup');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'dgptm_logs_cleanup');
        }
    }

    /**
     * Dependency Manager abrufen
     */
    public function get_dependency_manager() {
        return $this->dependency_manager;
    }

    /**
     * Module Loader abrufen
     */
    public function get_module_loader() {
        return $this->module_loader;
    }
}

/**
 * Plugin initialisieren
 */
function dgptm_suite() {
    return DGPTM_Plugin_Suite::get_instance();
}

// Plugin starten und globale Variable setzen
$GLOBALS['dgptm_suite'] = dgptm_suite();
