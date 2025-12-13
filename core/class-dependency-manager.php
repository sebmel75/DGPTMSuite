<?php
/**
 * Dependency Manager für DGPTM Plugin Suite
 * Verwaltet Abhängigkeiten zwischen Modulen
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Dependency_Manager {

    private $dependencies = [];
    private $module_registry = [];

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->load_dependency_map();
    }

    /**
     * Dependency Map laden
     */
    private function load_dependency_map() {
        // Abhängigkeiten aus DEPENDENCIES.md
        $this->dependencies = [
            // Core Infrastructure
            'crm-abruf' => [
                'required_by' => ['event-tracker', 'abstimmen-addon', 'quiz-manager', 'fortbildung'],
                'requires' => [],
                'wp_plugins' => [],
            ],
            'rest-api-extension' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => [],
            ],
            'webhook-trigger' => [
                'required_by' => ['event-tracker', 'abstimmen-addon'],
                'requires' => [],
                'wp_plugins' => [],
            ],
            'menu-control' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => [],
            ],
            'side-restrict' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => [],
            ],

            // ACF Tools
            'acf-anzeiger' => [
                'required_by' => ['herzzentren', 'fortbildung'],
                'requires' => [],
                'wp_plugins' => ['advanced-custom-fields'],
            ],
            'acf-toggle' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => ['advanced-custom-fields'],
            ],
            'acf-jetsync' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => ['advanced-custom-fields', 'jetengine'],
            ],

            // Business Modules
            'fortbildung' => [
                'required_by' => [],
                'requires' => ['quiz-manager'],
                'optional_requires' => ['crm-abruf'],
                'wp_plugins' => ['advanced-custom-fields'],
                'libraries' => ['fpdf'],
            ],
            'quiz-manager' => [
                'required_by' => ['fortbildung'],
                'requires' => [],
                'optional_requires' => ['crm-abruf'],
                'wp_plugins' => ['quiz-maker'],
            ],
            'herzzentren' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => ['elementor', 'advanced-custom-fields'],
            ],
            'timeline-manager' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => [],
            ],
            'event-tracker' => [
                'required_by' => [],
                'requires' => ['webhook-trigger'],
                'optional_requires' => ['crm-abruf'],
                'wp_plugins' => [],
            ],
            'abstimmen-addon' => [
                'required_by' => [],
                'requires' => ['webhook-trigger'],
                'optional_requires' => ['crm-abruf'],
                'wp_plugins' => [],
            ],
            'microsoft-gruppen' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => [],
            ],
            'anwesenheitsscanner' => [
                'required_by' => [],
                'requires' => [],
                'libraries' => ['fpdf', 'code128'],
                'wp_plugins' => [],
            ],
            'gehaltsstatistik' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => [],
            ],

            // Payment
            'stripe-formidable' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => ['formidable'],
            ],
            'gocardless' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => ['formidable'],
            ],

            // Auth
            'otp-login' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => [],
            ],

            // Media
            'vimeo-streams' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => [],
            ],
            'wissens-bot' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => [],
            ],

            // Content
            'news-management' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => [],
            ],
            'publication-workflow' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => [],
            ],

            // Utilities
            'kiosk-jahrestagung' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => [],
            ],
            'exif-data' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => [],
            ],
            'blaue-seiten' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => [],
            ],
            'shortcode-tools' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => [],
            ],
            'stellenanzeige' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => [],
            ],
            'conditional-logic' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => [],
            ],
            'installer' => [
                'required_by' => [],
                'requires' => [],
                'wp_plugins' => [],
            ],
            'zoho-role-manager' => [
                'required_by' => [],
                'requires' => ['crm-abruf'],
                'wp_plugins' => [],
            ],
        ];
    }

    /**
     * Modul registrieren
     */
    public function register_module($module_id, $module_instance) {
        $this->module_registry[$module_id] = $module_instance;
    }

    /**
     * Prüfen, ob ein Modul aktiviert ist
     */
    public function is_module_active($module_id) {
        $settings = get_option('dgptm_suite_settings', []);
        $active_modules = $settings['active_modules'] ?? [];
        return isset($active_modules[$module_id]) && $active_modules[$module_id] === true;
    }

    /**
     * Abhängigkeiten eines Moduls prüfen
     */
    public function check_module_dependencies($module_id) {
        if (!isset($this->dependencies[$module_id])) {
            return ['status' => true, 'messages' => []];
        }

        $dep_info = $this->dependencies[$module_id];
        $messages = [];
        $status = true;

        // Modul-Abhängigkeiten prüfen
        if (!empty($dep_info['requires'])) {
            foreach ($dep_info['requires'] as $required_module) {
                if (!$this->is_module_active($required_module)) {
                    $messages[] = sprintf(
                        __('Required module "%s" is not active.', 'dgptm-suite'),
                        $required_module
                    );
                    $status = false;
                }
            }
        }

        // WordPress-Plugin-Abhängigkeiten prüfen
        if (!empty($dep_info['wp_plugins'])) {
            foreach ($dep_info['wp_plugins'] as $plugin) {
                if (!$this->is_wp_plugin_active($plugin)) {
                    $messages[] = sprintf(
                        __('Required WordPress plugin "%s" is not active.', 'dgptm-suite'),
                        $plugin
                    );
                    $status = false;
                }
            }
        }

        return ['status' => $status, 'messages' => $messages];
    }

    /**
     * Prüfen, ob WordPress-Plugin aktiv ist
     */
    private function is_wp_plugin_active($plugin_slug) {
        // Bekannte Plugin-Pfade
        $plugin_paths = [
            'advanced-custom-fields' => 'advanced-custom-fields/acf.php',
            'advanced-custom-fields-pro' => 'advanced-custom-fields-pro/acf.php',
            'elementor' => 'elementor/elementor.php',
            'formidable' => 'formidable/formidable.php',
            'quiz-maker' => 'quiz-maker/quiz-maker.php',
            'jetengine' => 'jet-engine/jet-engine.php',
        ];

        // ACF kann Pro oder Free sein
        if ($plugin_slug === 'advanced-custom-fields') {
            return is_plugin_active('advanced-custom-fields/acf.php') ||
                   is_plugin_active('advanced-custom-fields-pro/acf.php');
        }

        $plugin_path = $plugin_paths[$plugin_slug] ?? $plugin_slug;

        if (!function_exists('is_plugin_active')) {
            include_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active($plugin_path);
    }

    /**
     * Abhängige Module finden (welche Module hängen von diesem ab?)
     */
    public function get_dependent_modules($module_id) {
        $dependents = [];

        foreach ($this->dependencies as $mid => $info) {
            if (in_array($module_id, $info['requires'] ?? [])) {
                if ($this->is_module_active($mid)) {
                    $dependents[] = $mid;
                }
            }
        }

        return $dependents;
    }

    /**
     * Kann Modul deaktiviert werden?
     */
    public function can_deactivate_module($module_id) {
        // Prüfe Critical-Flag
        $metadata = DGPTM_Module_Metadata_File::get_instance();
        $config = $metadata->load_module_config($module_id);

        // Prüfe ob Modul als kritisch markiert ist (Flag oder Config)
        if ($metadata->is_module_critical($module_id, $config)) {
            return [
                'can_deactivate' => false,
                'dependents' => [],
                'is_critical' => true,
                'message' => sprintf(
                    __('Cannot deactivate: "%s" is marked as critical and essential for system operation.', 'dgptm-suite'),
                    $config['name'] ?? $module_id
                )
            ];
        }

        // Prüfe Abhängigkeiten
        $dependents = $this->get_dependent_modules($module_id);

        if (empty($dependents)) {
            return ['can_deactivate' => true, 'dependents' => [], 'is_critical' => false];
        }

        return [
            'can_deactivate' => false,
            'dependents' => $dependents,
            'is_critical' => false,
            'message' => sprintf(
                __('Cannot deactivate: The following modules depend on "%s": %s', 'dgptm-suite'),
                $module_id,
                implode(', ', $dependents)
            )
        ];
    }

    /**
     * Alle Abhängigkeiten für ein Modul abrufen (inkl. transitiver Abhängigkeiten)
     */
    public function get_all_dependencies($module_id, &$collected = []) {
        if (in_array($module_id, $collected)) {
            return $collected; // Zirkularität vermeiden
        }

        if (!isset($this->dependencies[$module_id])) {
            return $collected;
        }

        $dep_info = $this->dependencies[$module_id];

        if (!empty($dep_info['requires'])) {
            foreach ($dep_info['requires'] as $required_module) {
                if (!in_array($required_module, $collected)) {
                    $collected[] = $required_module;
                    // Rekursiv weitere Abhängigkeiten sammeln
                    $this->get_all_dependencies($required_module, $collected);
                }
            }
        }

        return $collected;
    }

    /**
     * Modul-Informationen abrufen
     */
    public function get_module_info($module_id) {
        return $this->dependencies[$module_id] ?? null;
    }
}
