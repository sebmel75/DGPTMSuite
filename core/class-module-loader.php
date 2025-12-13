<?php
/**
 * Module Loader für DGPTM Plugin Suite
 * Lädt aktive Module dynamisch
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Module_Loader {

    private $dependency_manager;
    private $loaded_modules = [];
    private $module_paths = [];

    private $safe_loader = null;

    /**
     * Konstruktor
     */
    public function __construct($dependency_manager) {
        $this->dependency_manager = $dependency_manager;
        $this->safe_loader = DGPTM_Safe_Loader::get_instance();
        $this->scan_modules();
    }

    /**
     * Alle verfügbaren Module scannen
     * Unterstützt beide Strukturen:
     * - Neue: modules/{module-id}/module.json
     * - Alte: modules/{category}/{module-id}/module.json
     */
    private function scan_modules() {
        $modules_dir = DGPTM_SUITE_PATH . 'modules/';

        if (!is_dir($modules_dir)) {
            DGPTM_Logger::error('Module Loader: Modules-Verzeichnis nicht gefunden: ' . $modules_dir);
            return;
        }

        DGPTM_Logger::verbose('Module Loader: Starte Module-Scan in ' . $modules_dir);

        // Scan direkt im modules/ Verzeichnis (neue Struktur)
        $this->scan_directory($modules_dir, null);

        // Scan in Unterverzeichnissen (alte Struktur mit Kategorien als Ordner)
        $items = @scandir($modules_dir);
        if ($items === false) {
            DGPTM_Logger::error('Module Loader: Kann Modules-Verzeichnis nicht scannen');
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $item_path = $modules_dir . $item . '/';

            // Wenn es ein Verzeichnis ist UND keine module.json hat, ist es vermutlich ein Kategorie-Ordner
            if (@is_dir($item_path) && !file_exists($item_path . 'module.json')) {
                DGPTM_Logger::verbose("Module Loader: Scanne Kategorie-Ordner: $item");
                // Scan dieses Verzeichnis als Kategorie (alte Struktur)
                $this->scan_directory($item_path, $item);
            }
        }

        DGPTM_Logger::verbose('Module Loader: Scan abgeschlossen - ' . count($this->module_paths) . ' Module gefunden');
    }

    /**
     * Verzeichnis nach Modulen durchsuchen
     *
     * @param string $directory Verzeichnis zum Durchsuchen
     * @param string|null $fallback_category Kategorie als Fallback (für alte Struktur)
     */
    private function scan_directory($directory, $fallback_category = null) {
        if (!is_dir($directory)) {
            return;
        }

        $items = @scandir($directory);

        if ($items === false) {
            DGPTM_Logger::error('Module Loader: Kann Verzeichnis nicht scannen: ' . $directory);
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            // Skip ZIP files and hidden files
            if (substr($item, -4) === '.zip' || substr($item, 0, 1) === '.') {
                continue;
            }

            $item_path = $directory . $item . '/';

            // Prüfen ob Verzeichnis (mit Fehlerbehandlung für open_basedir)
            if (!@is_dir($item_path)) {
                continue;
            }

            $config_file = $item_path . 'module.json';

            if (file_exists($config_file)) {
                $config = @json_decode(file_get_contents($config_file), true);

                if ($config && isset($config['id'])) {
                    // Kategorie aus module.json verwenden, oder Fallback
                    $category = $config['category'] ?? $fallback_category ?? 'uncategorized';

                    $this->module_paths[$config['id']] = [
                        'path' => $item_path,
                        'category' => $category,
                        'config' => $config,
                    ];

                    DGPTM_Logger::verbose('Module Loader: Modul gefunden "' . $config['id'] . '" in Kategorie "' . $category . '"');
                }
            }
        }
    }

    /**
     * Aktive Module laden
     */
    public function load_modules() {
        // WICHTIG: Keine Cache-Version verwenden!
        // get_option kann von Object Cache gecached werden
        $settings = get_option('dgptm_suite_settings', [], false);
        $active_modules = $settings['active_modules'] ?? [];

        DGPTM_Logger::verbose("Module Loader: load_modules() gestartet - Context: " . (is_admin() ? 'ADMIN' : 'FRONTEND'));
        DGPTM_Logger::verbose("Module Loader: Request URI: " . ($_SERVER['REQUEST_URI'] ?? 'N/A'));
        DGPTM_Logger::verbose("Module Loader: Aktive Module: " . json_encode($active_modules));

        // Auto-Reaktivierung kritischer Module
        $settings_changed = false;
        foreach ($this->module_paths as $module_id => $module_info) {
            $config = $module_info['config'];
            if (!empty($config['critical'])) {
                // Kritisches Modul muss immer aktiv sein
                if (empty($active_modules[$module_id])) {
                    dgptm_log("KRITISCHES MODUL '$module_id' war deaktiviert - AUTO-REAKTIVIERUNG", 'critical');
                    $active_modules[$module_id] = true;
                    $settings_changed = true;
                }
            }
        }

        // Speichere Änderungen wenn kritische Module reaktiviert wurden
        if ($settings_changed) {
            $settings['active_modules'] = $active_modules;
            update_option('dgptm_suite_settings', $settings);
            wp_cache_delete('dgptm_suite_settings', 'options');
            dgptm_log("Einstellungen aktualisiert - kritische Module reaktiviert", 'info');
        }

        // CLEANUP: Entferne Module aus der Datenbank, die nicht mehr im Filesystem existieren
        $cleanup_result = $this->cleanup_missing_modules($active_modules);
        if ($cleanup_result['cleaned']) {
            $active_modules = $cleanup_result['active_modules'];
            $settings['active_modules'] = $active_modules;
            update_option('dgptm_suite_settings', $settings);
            wp_cache_delete('dgptm_suite_settings', 'options');

            // Admin-Notice hinzufügen (nur im Admin-Bereich)
            if (is_admin() && !empty($cleanup_result['removed_modules'])) {
                $this->add_cleanup_notice($cleanup_result['removed_modules']);
            }
        }

        // Nur die Module filtern, die auf true gesetzt sind
        $active_module_ids = array_keys(array_filter($active_modules));
        dgptm_log("Zu ladende Module: " . json_encode($active_module_ids), 'verbose');

        // Module nach Abhängigkeiten sortieren
        $sorted_modules = $this->sort_modules_by_dependencies($active_module_ids);
        dgptm_log("Sortierte Module: " . json_encode($sorted_modules), 'verbose');

        foreach ($sorted_modules as $module_id) {
            dgptm_log("Versuche Modul zu laden: $module_id", 'verbose');
            $this->load_module($module_id);
        }

        dgptm_log(count($this->loaded_modules) . " Module erfolgreich geladen", 'info');

        // Action nach Laden aller Module
        do_action('dgptm_suite_modules_loaded', $this->loaded_modules);
    }

    /**
     * Einzelnes Modul laden
     */
    public function load_module($module_id) {
        // Prüfen, ob bereits geladen
        if (isset($this->loaded_modules[$module_id])) {
            return true;
        }

        // DEBUG: Prüfen, ob Modul temporär deaktiviert ist (für Debugging)
        $debug_disabled = get_option('dgptm_debug_disabled_modules', []);
        if (in_array($module_id, $debug_disabled)) {
            DGPTM_Logger::info("Module Loader: Modul '$module_id' temporär deaktiviert für Debugging");
            return false;
        }

        // Prüfen, ob Modul existiert
        if (!isset($this->module_paths[$module_id])) {
            DGPTM_Logger::error("Module Loader: Modul '$module_id' nicht gefunden");
            return false;
        }

        $module_info = $this->module_paths[$module_id];
        $module_path = $module_info['path'];
        $config = $module_info['config'];

        // Abhängigkeiten prüfen
        $dep_check = $this->dependency_manager->check_module_dependencies($module_id);

        if (!$dep_check['status']) {
            DGPTM_Logger::error("Module Loader: Kann Modul '$module_id' nicht laden. Fehlende Abhängigkeiten: " . implode(', ', $dep_check['messages']));
            return false;
        }

        // Hauptdatei laden
        $main_file = $config['main_file'] ?? '';

        if (empty($main_file)) {
            DGPTM_Logger::error("Module Loader: Keine Hauptdatei für Modul '$module_id' definiert");
            return false;
        }

        $main_file_path = $module_path . $main_file;

        if (!file_exists($main_file_path)) {
            DGPTM_Logger::error("Module Loader: Hauptdatei nicht gefunden für Modul '$module_id': $main_file_path");
            return false;
        }

        // Modul sicher laden mit Fehlerabfang
        $load_result = $this->safe_loader->safe_load_module($module_id, $main_file_path);

        if (!$load_result['success']) {
            DGPTM_Logger::error("Module Loader: Laden von Modul '$module_id' fehlgeschlagen: " . $load_result['error']);

            // AUTOMATISCH DEAKTIVIEREN
            $this->auto_deactivate_module($module_id, $load_result['error']);

            // Admin-Notice für den aktuellen User
            if (is_admin()) {
                add_action('admin_notices', function() use ($module_id, $load_result) {
                    $module_name = $load_result['config']['name'] ?? $module_id;
                    ?>
                    <div class="notice notice-error is-dismissible">
                        <p><strong><?php _e('DGPTM Suite - Kritischer Fehler in Modul', 'dgptm-suite'); ?></strong></p>
                        <p><?php printf(__('Modul "%s" wurde automatisch deaktiviert aufgrund eines kritischen Fehlers:', 'dgptm-suite'), esc_html($module_name)); ?></p>
                        <p><code><?php echo esc_html($load_result['error']); ?></code></p>
                        <?php if (isset($load_result['file']) && isset($load_result['line'])): ?>
                            <p><small><?php echo esc_html($load_result['file']); ?>:<?php echo esc_html($load_result['line']); ?></small></p>
                        <?php endif; ?>
                        <p><em><?php _e('Das Modul bleibt deaktiviert bis der Fehler behoben wurde.', 'dgptm-suite'); ?></em></p>
                    </div>
                    <?php
                });
            }

            return false;
        }

        // Warnungen loggen wenn vorhanden
        if (!empty($load_result['warnings'])) {
            foreach ($load_result['warnings'] as $warning) {
                DGPTM_Logger::warning("Module Loader: Warnung in Modul '$module_id': " . $warning['message']);
            }
        }

        // Modul als geladen markieren
        $this->loaded_modules[$module_id] = [
            'path' => $module_path,
            'config' => $config,
            'loaded_at' => time(),
            'load_result' => $load_result,
        ];

        // Action nach Laden des Moduls
        do_action('dgptm_suite_module_loaded', $module_id, $config);

        return true;
    }

    /**
     * Module nach Abhängigkeiten sortieren (topologische Sortierung)
     */
    private function sort_modules_by_dependencies($module_ids) {
        $sorted = [];
        $visited = [];
        $temp = [];

        $visit = function($module_id) use (&$visit, &$sorted, &$visited, &$temp, $module_ids) {
            // Zirkuläre Abhängigkeiten vermeiden
            if (isset($temp[$module_id])) {
                DGPTM_Logger::warning("Module Loader: Zirkuläre Abhängigkeit erkannt für Modul '$module_id'");
                return;
            }

            if (isset($visited[$module_id])) {
                return;
            }

            $temp[$module_id] = true;

            // Abhängigkeiten zuerst besuchen
            $deps = $this->dependency_manager->get_all_dependencies($module_id);

            foreach ($deps as $dep) {
                if (in_array($dep, $module_ids)) {
                    $visit($dep);
                }
            }

            unset($temp[$module_id]);
            $visited[$module_id] = true;
            $sorted[] = $module_id;
        };

        foreach ($module_ids as $module_id) {
            $visit($module_id);
        }

        return $sorted;
    }

    /**
     * Geladene Module abrufen
     */
    public function get_loaded_modules() {
        return $this->loaded_modules;
    }

    /**
     * Alle verfügbaren Module abrufen
     */
    public function get_available_modules() {
        return $this->module_paths;
    }

    /**
     * Modul-Pfad abrufen
     */
    public function get_module_path($module_id) {
        return $this->module_paths[$module_id]['path'] ?? null;
    }

    /**
     * Modul-Konfiguration abrufen
     */
    public function get_module_config($module_id) {
        return $this->module_paths[$module_id]['config'] ?? null;
    }

    /**
     * Modul automatisch deaktivieren bei Fehler
     */
    private function auto_deactivate_module($module_id, $error_message) {
        // Einstellungen laden
        $settings = get_option('dgptm_suite_settings', []);
        $active_modules = $settings['active_modules'] ?? [];

        // Modul deaktivieren
        if (isset($active_modules[$module_id])) {
            $active_modules[$module_id] = false;
            $settings['active_modules'] = $active_modules;
            update_option('dgptm_suite_settings', $settings);
            wp_cache_delete('dgptm_suite_settings', 'options');

            // Log schreiben
            dgptm_log("AUTO-DEAKTIVIERT: Modul '$module_id' wegen Fehler: " . $error_message, 'error');

            // Fehler in separates Log schreiben für spätere Analyse
            $error_log = get_option('dgptm_suite_module_errors', []);
            $error_log[$module_id] = [
                'error' => $error_message,
                'timestamp' => current_time('mysql'),
                'auto_deactivated' => true
            ];
            update_option('dgptm_suite_module_errors', $error_log);
        }
    }

    /**
     * Prüfen, ob Modul geladen ist
     */
    public function is_module_loaded($module_id) {
        return isset($this->loaded_modules[$module_id]);
    }

    /**
     * Modul neu initialisieren (wie bei regulärem Plugin)
     * Führt Aktivierungs-Hooks aus und initialisiert Permalinks neu
     *
     * @param string $module_id Die Modul-ID
     * @return array|WP_Error Erfolgsmeldung oder Fehler
     */
    public function reinit_module($module_id) {
        dgptm_log("Starte Neu-Initialisierung für Modul: $module_id", 'info');

        // Prüfen, ob Modul existiert
        if (!isset($this->module_paths[$module_id])) {
            $error = "Modul '$module_id' nicht gefunden.";
            dgptm_log($error, 'error');
            return new WP_Error('module_not_found', $error);
        }

        $module_info = $this->module_paths[$module_id];
        $module_path = $module_info['path'];
        $config = $module_info['config'];

        // Prüfen ob Modul aktiv ist
        $settings = get_option('dgptm_suite_settings', []);
        $active_modules = $settings['active_modules'] ?? [];

        if (empty($active_modules[$module_id])) {
            $error = "Modul '$module_id' ist nicht aktiv. Bitte zuerst aktivieren.";
            dgptm_log($error, 'error');
            return new WP_Error('module_not_active', $error);
        }

        $main_file = $config['main_file'] ?? '';
        if (empty($main_file)) {
            $error = "Keine Hauptdatei für Modul '$module_id' definiert.";
            dgptm_log($error, 'error');
            return new WP_Error('no_main_file', $error);
        }

        $main_file_path = $module_path . $main_file;
        if (!file_exists($main_file_path)) {
            $error = "Hauptdatei nicht gefunden: $main_file_path";
            dgptm_log($error, 'error');
            return new WP_Error('main_file_not_found', $error);
        }

        // Modul-Hook-Name für Aktivierung (basierend auf WordPress Plugin-Konvention)
        // Format: plugin_basename wird simuliert
        $plugin_basename = 'dgptm-suite-modules/' . $module_id . '/' . $main_file;

        dgptm_log("Simuliere Plugin-Aktivierung für: $plugin_basename", 'verbose');

        // 1. Führe register_activation_hook aus wenn vorhanden
        // Suche nach register_activation_hook im Code
        $file_content = file_get_contents($main_file_path);

        // Prüfe ob es eine dedizierte Aktivierungsfunktion gibt
        $has_activation_hook = false;
        if (preg_match('/register_activation_hook\s*\(\s*__FILE__\s*,\s*[\'"]([^\'"]+)[\'"]\s*\)/', $file_content, $matches)) {
            $activation_function = $matches[1];
            dgptm_log("Gefundene Aktivierungsfunktion: $activation_function", 'verbose');

            // Lade Modul-Datei falls noch nicht geladen
            if (!$this->is_module_loaded($module_id)) {
                require_once $main_file_path;
            }

            // Führe Aktivierungsfunktion aus
            if (function_exists($activation_function)) {
                dgptm_log("Führe Aktivierungsfunktion aus: $activation_function()", 'info');
                try {
                    call_user_func($activation_function);
                    $has_activation_hook = true;
                    dgptm_log("Aktivierungsfunktion erfolgreich ausgeführt", 'info');
                } catch (Exception $e) {
                    dgptm_log("Fehler bei Aktivierungsfunktion: " . $e->getMessage(), 'error');
                }
            } else {
                dgptm_log("Aktivierungsfunktion '$activation_function' nicht gefunden", 'warning');
            }
        }

        // 2. Führe do_action für Plugin-Aktivierung aus
        dgptm_log("Führe do_action('activate_plugin') aus", 'verbose');
        do_action('activate_plugin', $plugin_basename);

        // 3. Führe modulspezifische Aktivierungshooks aus
        $module_name = $config['name'] ?? $module_id;
        dgptm_log("Führe Modul-spezifische Aktivierungshooks aus", 'verbose');
        do_action('dgptm_suite_module_reinit', $module_id, $config);
        do_action("dgptm_suite_module_reinit_{$module_id}", $config);

        // 4. Permalinks neu initialisieren
        dgptm_log("Initialisiere Permalinks neu...", 'info');

        // Lösche Rewrite-Regeln Cache
        delete_option('rewrite_rules');

        // Führe flush_rewrite_rules aus (nicht-soft, um sicherzustellen dass Regeln neu geschrieben werden)
        flush_rewrite_rules(false);

        dgptm_log("Permalinks erfolgreich neu initialisiert", 'info');

        // 5. Führe weitere WordPress-Initialisierungen aus
        // Oft registrieren Plugins Custom Post Types, Taxonomien, etc. in 'init' Hook
        // Diese müssen neu initialisiert werden
        dgptm_log("Triggere init-Hooks neu...", 'verbose');

        // Führe modulspezifische Init-Funktion aus falls vorhanden
        if (preg_match('/add_action\s*\(\s*[\'"]init[\'"]\s*,\s*\[?\s*[\'"]?(\$this|[a-zA-Z_][a-zA-Z0-9_]*)[\'"]?\s*,\s*[\'"]([^\'"]+)[\'"]/', $file_content, $matches)) {
            dgptm_log("Gefundene Init-Hook Registrierung", 'verbose');
        }

        // 6. Triggere einen WordPress-Init-Zyklus für das Modul
        // Dies stellt sicher dass alle register_post_type, register_taxonomy etc. neu ausgeführt werden
        do_action('dgptm_suite_module_init_cycle', $module_id);

        $success_message = sprintf(
            'Modul "%s" wurde erfolgreich neu initialisiert. Aktivierungs-Hooks wurden ausgeführt und Permalinks wurden aktualisiert.',
            $module_name
        );

        dgptm_log($success_message, 'info');

        return [
            'success' => true,
            'message' => $success_message,
            'module_id' => $module_id,
            'module_name' => $module_name,
            'activation_hook_executed' => $has_activation_hook,
            'permalinks_flushed' => true,
        ];
    }

    /**
     * Bereinige Module aus der Datenbank, die nicht mehr im Filesystem existieren
     *
     * @param array $active_modules Array der aktiven Module aus der Datenbank
     * @return array ['cleaned' => bool, 'removed_modules' => array, 'active_modules' => array]
     */
    private function cleanup_missing_modules($active_modules) {
        $available_module_ids = array_keys($this->module_paths);
        $database_module_ids = array_keys($active_modules);

        // Finde Module die in der DB sind, aber nicht im Filesystem
        $missing_modules = array_diff($database_module_ids, $available_module_ids);

        if (empty($missing_modules)) {
            return [
                'cleaned' => false,
                'removed_modules' => [],
                'active_modules' => $active_modules
            ];
        }

        dgptm_log("Module Loader: CLEANUP - Folgende Module existieren nicht mehr im Filesystem: " . implode(', ', $missing_modules), 'warning');

        $removed_count = 0;
        foreach ($missing_modules as $module_id) {
            dgptm_log("Module Loader: CLEANUP - Entferne Modul '$module_id' aus der Datenbank", 'info');
            unset($active_modules[$module_id]);
            $removed_count++;
        }

        dgptm_log("Module Loader: CLEANUP - $removed_count Module aus der Datenbank entfernt", 'info');

        return [
            'cleaned' => true,
            'removed_modules' => array_values($missing_modules),
            'active_modules' => $active_modules
        ];
    }

    /**
     * Füge Admin-Notice für bereinigte Module hinzu
     *
     * @param array $removed_modules Array der entfernten Modul-IDs
     */
    private function add_cleanup_notice($removed_modules) {
        // Verwende WordPress Transient für Admin-Notices
        $notice_data = [
            'type' => 'warning',
            'message' => sprintf(
                _n(
                    'DGPTM Suite: Das Modul <strong>%s</strong> wurde aus der Datenbank entfernt, da es nicht mehr im Filesystem vorhanden ist.',
                    'DGPTM Suite: Die folgenden Module wurden aus der Datenbank entfernt, da sie nicht mehr im Filesystem vorhanden sind: <strong>%s</strong>',
                    count($removed_modules),
                    'dgptm-suite'
                ),
                implode(', ', $removed_modules)
            ),
            'dismissible' => true
        ];

        // Speichere Notice als Transient (24 Stunden)
        set_transient('dgptm_cleanup_notice', $notice_data, DAY_IN_SECONDS);

        // Hook für Admin-Notices registrieren
        add_action('admin_notices', function() {
            $notice = get_transient('dgptm_cleanup_notice');
            if ($notice) {
                $class = 'notice notice-' . $notice['type'];
                if ($notice['dismissible']) {
                    $class .= ' is-dismissible';
                }

                printf(
                    '<div class="%1$s"><p>%2$s</p></div>',
                    esc_attr($class),
                    $notice['message']
                );

                // Lösche Transient nach Anzeige
                delete_transient('dgptm_cleanup_notice');
            }
        });
    }
}
