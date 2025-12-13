<?php
/**
 * Standalone Guide Generator (ohne WordPress-Abhängigkeit)
 * Generiert alle Modul-Anleitungen als JSON-Dateien
 */

echo "DGPTM Guide Generator (Standalone)\n";
echo "===================================\n\n";

// Guides-Verzeichnis
$guides_dir = __DIR__ . '/guides/';
if (!file_exists($guides_dir)) {
    mkdir($guides_dir, 0755, true);
    echo "✓ Guides-Verzeichnis erstellt\n";
}

// Lade existierende Dokumentation aus JSON
$doc_file = dirname(__DIR__) . '/DGPTM-Module-Dokumentation.json';
$existing_guides = [];

if (file_exists($doc_file)) {
    $json = file_get_contents($doc_file);
    $existing_guides = json_decode($json, true);
    if ($existing_guides) {
        echo "✓ Existierende Dokumentation geladen: " . count($existing_guides) . " Module\n\n";
    } else {
        echo "! JSON-Dekodierung fehlgeschlagen\n\n";
        $existing_guides = [];
    }
} else {
    echo "! Keine existierende Dokumentation gefunden\n\n";
}

// Zeitstempel für Metadaten
$current_time = date('Y-m-d H:i:s');

// Füge Zeitstempel zu existierenden Guides hinzu
foreach ($existing_guides as $module_id => &$guide_data) {
    $guide_data['module_id'] = $module_id;
    if (!isset($guide_data['generated_at'])) {
        $guide_data['generated_at'] = $current_time;
    }
    $guide_data['last_updated'] = $current_time;
}
unset($guide_data);

// Import aller neuen Module-Guides
require_once __DIR__ . '/import-all-guides.php';

echo "\n===================================\n";
echo "✓ Alle Anleitungen erfolgreich generiert!\n";
echo "Verfügbar unter: guides/\n\n";
echo "Sie können die Anleitungen jetzt über das\n";
echo "WordPress-Dashboard unter 'DGPTM Suite → Module Guides' ansehen.\n";
