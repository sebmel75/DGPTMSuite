<?php
/**
 * Debug-Skript für DGPTM Module
 * Temporär zum Debuggen - kann nach Behebung gelöscht werden
 */

// WordPress laden
require_once dirname(__FILE__) . '/../../../wp-load.php';

if (!current_user_can('manage_options')) {
    die('Insufficient permissions');
}

echo "<h1>DGPTM Module Debug</h1>";
echo "<style>body { font-family: monospace; } .error { color: red; } .success { color: green; } .warning { color: orange; } pre { background: #f5f5f5; padding: 10px; }</style>";

// 1. Einstellungen prüfen
echo "<h2>1. Gespeicherte Einstellungen</h2>";
$settings = get_option('dgptm_suite_settings', []);
echo "<pre>";
print_r($settings);
echo "</pre>";

// 2. Aktive Module
echo "<h2>2. Aktive Module (laut Einstellungen)</h2>";
$active_modules = $settings['active_modules'] ?? [];
$active_list = array_keys(array_filter($active_modules));
echo "<pre>";
print_r($active_list);
echo "</pre>";
echo "<p>Anzahl aktiver Module: " . count($active_list) . "</p>";

// 3. Module Loader initialisieren
echo "<h2>3. Module Loader Test</h2>";
$module_loader = dgptm_suite()->get_module_loader();
$available = $module_loader->get_available_modules();
echo "<p>Verfügbare Module: " . count($available) . "</p>";

// 4. Geladene Module
echo "<h2>4. Geladene Module</h2>";
$loaded = $module_loader->get_loaded_modules();
echo "<pre>";
print_r(array_keys($loaded));
echo "</pre>";
echo "<p>Anzahl geladener Module: " . count($loaded) . "</p>";

// 5. Vergleich: Aktiv vs Geladen
echo "<h2>5. Vergleich: Aktiv vs Geladen</h2>";
$not_loaded = array_diff($active_list, array_keys($loaded));
if (!empty($not_loaded)) {
    echo "<p class='error'>Folgende Module sind aktiv, aber NICHT geladen:</p>";
    echo "<pre class='error'>";
    print_r($not_loaded);
    echo "</pre>";
} else {
    echo "<p class='success'>Alle aktiven Module wurden erfolgreich geladen!</p>";
}

// 6. Detaillierte Prüfung jedes aktiven Moduls
echo "<h2>6. Detaillierte Prüfung</h2>";
foreach ($active_list as $module_id) {
    echo "<h3>Modul: $module_id</h3>";

    // Config prüfen
    $config = $module_loader->get_module_config($module_id);
    if (!$config) {
        echo "<p class='error'>✗ Keine module.json gefunden</p>";
        continue;
    }
    echo "<p class='success'>✓ Config gefunden</p>";

    // Pfad prüfen
    $path = $module_loader->get_module_path($module_id);
    if (!$path) {
        echo "<p class='error'>✗ Kein Pfad gefunden</p>";
        continue;
    }
    echo "<p class='success'>✓ Pfad: $path</p>";

    // Hauptdatei prüfen
    $main_file = $config['main_file'] ?? '';
    if (empty($main_file)) {
        echo "<p class='error'>✗ Keine main_file in config</p>";
        continue;
    }

    $main_file_path = $path . $main_file;
    if (!file_exists($main_file_path)) {
        echo "<p class='error'>✗ Hauptdatei nicht gefunden: $main_file_path</p>";
        continue;
    }
    echo "<p class='success'>✓ Hauptdatei existiert: $main_file</p>";

    // PHP Syntax Check
    $output = [];
    $return_var = 0;
    exec("php -l " . escapeshellarg($main_file_path) . " 2>&1", $output, $return_var);
    if ($return_var === 0) {
        echo "<p class='success'>✓ PHP Syntax OK</p>";
    } else {
        echo "<p class='error'>✗ PHP Syntax Error:</p>";
        echo "<pre class='error'>" . implode("\n", $output) . "</pre>";
    }

    // Abhängigkeiten prüfen
    $dep_manager = dgptm_suite()->get_dependency_manager();
    $dep_check = $dep_manager->check_module_dependencies($module_id);
    if ($dep_check['status']) {
        echo "<p class='success'>✓ Alle Abhängigkeiten erfüllt</p>";
    } else {
        echo "<p class='error'>✗ Fehlende Abhängigkeiten:</p>";
        echo "<pre class='error'>";
        print_r($dep_check['messages']);
        echo "</pre>";
    }

    // Geladen?
    if ($module_loader->is_module_loaded($module_id)) {
        echo "<p class='success'>✓ Modul ist geladen</p>";
        $loaded_info = $loaded[$module_id] ?? [];
        if (isset($loaded_info['load_result'])) {
            echo "<p>Load Result:</p>";
            echo "<pre>";
            print_r($loaded_info['load_result']);
            echo "</pre>";
        }
    } else {
        echo "<p class='error'>✗ Modul ist NICHT geladen</p>";
    }

    echo "<hr>";
}

// 7. WordPress Error Log
echo "<h2>7. Letzte PHP Errors (wenn vorhanden)</h2>";
$error_log = ini_get('error_log');
if ($error_log && file_exists($error_log)) {
    echo "<p>Error Log: $error_log</p>";
    $lines = file($error_log);
    $recent = array_slice($lines, -20);
    echo "<pre>";
    echo implode("", $recent);
    echo "</pre>";
} else {
    echo "<p class='warning'>Error log nicht gefunden oder nicht konfiguriert</p>";
}

echo "<h2>Debug abgeschlossen</h2>";
echo "<p><a href='" . admin_url('admin.php?page=dgptm-suite') . "'>Zurück zum Dashboard</a></p>";
