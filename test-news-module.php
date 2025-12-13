<?php
/**
 * Test Script für News-Management Modul
 */

// WordPress laden
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Sicherheit
if (!current_user_can('manage_options')) {
    die('Access denied');
}

echo "<h1>News-Management Modul Test</h1>";

// Module Loader
$module_loader = dgptm_suite()->get_module_loader();
$all_modules = $module_loader->get_all_modules();

echo "<h2>Alle Module:</h2>";
echo "<pre>";
foreach ($all_modules as $id => $info) {
    echo "$id\n";
}
echo "</pre>";

// News-Management spezifisch
echo "<h2>News-Management Modul:</h2>";
if (isset($all_modules['news-management'])) {
    echo "<pre>";
    print_r($all_modules['news-management']);
    echo "</pre>";
} else {
    echo "<p style='color: red;'><strong>News-Management Modul NICHT gefunden!</strong></p>";
}

// Versuche zu laden
echo "<h2>Test-Laden:</h2>";
$safe_loader = DGPTM_Safe_Loader::get_instance();
$module_path = DGPTM_SUITE_PATH . 'modules/content/news-management/newsedit.php';

echo "Pfad: $module_path<br>";
echo "Existiert: " . (file_exists($module_path) ? 'JA' : 'NEIN') . "<br>";

if (file_exists($module_path)) {
    $test_result = $safe_loader->test_load_module('news-management', $module_path);
    echo "<h3>Test-Ergebnis:</h3>";
    echo "<pre>";
    print_r($test_result);
    echo "</pre>";
}

// Aktuelle Einstellungen
echo "<h2>Aktuelle Einstellungen:</h2>";
$settings = get_option('dgptm_suite_settings', []);
echo "<pre>";
print_r($settings['active_modules'] ?? []);
echo "</pre>";
