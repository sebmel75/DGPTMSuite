<?php
/**
 * Update all module.json files with category field
 * Run this once to add category to all existing modules
 */

// WordPress laden
require_once(__DIR__ . '/../../../wp-load.php');

if (!current_user_can('manage_options')) {
    die('Keine Berechtigung');
}

$modules_dir = __DIR__ . '/modules/';
$updated = 0;
$skipped = 0;
$errors = 0;

echo "<h1>Module Category Update</h1>";
echo "<pre>";

// Kategorie-Mapping basierend auf Ordnerstruktur
$category_map = [
    'core-infrastructure' => 'core-infrastructure',
    'business' => 'business',
    'payment' => 'payment',
    'auth' => 'auth',
    'media' => 'media',
    'content' => 'content',
    'acf-tools' => 'acf-tools',
    'utilities' => 'utilities',
];

// Scan alle Kategorien
foreach ($category_map as $folder => $category) {
    $category_path = $modules_dir . $folder . '/';

    if (!is_dir($category_path)) {
        echo "Kategorie-Ordner nicht gefunden: $category_path\n";
        continue;
    }

    echo "\n=== Kategorie: $category ===\n";

    $items = scandir($category_path);

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $module_path = $category_path . $item . '/';

        if (!is_dir($module_path)) {
            continue;
        }

        $config_file = $module_path . 'module.json';

        if (!file_exists($config_file)) {
            echo "  [SKIP] $item - keine module.json\n";
            $skipped++;
            continue;
        }

        // JSON laden
        $json = file_get_contents($config_file);
        $config = json_decode($json, true);

        if (!$config) {
            echo "  [ERROR] $item - ungültige JSON\n";
            $errors++;
            continue;
        }

        // Prüfen ob category bereits gesetzt
        if (isset($config['category'])) {
            echo "  [OK] {$config['id']} - category bereits gesetzt: {$config['category']}\n";
            $skipped++;
            continue;
        }

        // Category hinzufügen
        $config['category'] = $category;

        // JSON speichern mit schöner Formatierung
        $json_output = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if (file_put_contents($config_file, $json_output)) {
            echo "  [UPDATE] {$config['id']} - category hinzugefügt: $category\n";
            $updated++;
        } else {
            echo "  [ERROR] {$config['id']} - konnte nicht schreiben\n";
            $errors++;
        }
    }
}

echo "\n=== Zusammenfassung ===\n";
echo "Aktualisiert: $updated\n";
echo "Übersprungen: $skipped\n";
echo "Fehler: $errors\n";
echo "\nFertig!\n";
echo "</pre>";

echo "<p><a href='" . admin_url('admin.php?page=dgptm-suite-categories') . "'>Zur Kategorien-Verwaltung</a></p>";
