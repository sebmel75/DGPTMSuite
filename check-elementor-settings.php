<?php
/**
 * Elementor Settings Checker und Fixer
 */

// WordPress laden
define('WP_USE_THEMES', false);
require_once(__DIR__ . '/../wp-load.php');

// Nur für Admins
if (!current_user_can('manage_options')) {
    die('Keine Berechtigung');
}

echo "=== ELEMENTOR EINSTELLUNGEN ÜBERPRÜFUNG ===" . PHP_EOL . PHP_EOL;

// Aktuelle Einstellungen abrufen
$css_print_method = get_option('elementor_css_print_method', 'external');
$safe_mode = get_option('elementor_safe_mode', []);
$maintenance_mode = get_option('elementor_maintenance_mode_mode', 'off');
$disable_color_schemes = get_option('elementor_disable_color_schemes', '');
$disable_typography_schemes = get_option('elementor_disable_typography_schemes', '');
$editor_v2 = get_option('elementor_experiment-editor_v2', '');

echo "📊 Aktuelle Einstellungen:" . PHP_EOL;
echo "-------------------------" . PHP_EOL;
echo "CSS Print Method: " . $css_print_method . PHP_EOL;
echo "Safe Mode: " . (empty($safe_mode) ? 'Aus ✅' : 'An ⚠️ - ' . json_encode($safe_mode)) . PHP_EOL;
echo "Maintenance Mode: " . $maintenance_mode . PHP_EOL;
echo "Editor V2: " . ($editor_v2 ?: 'Standard') . PHP_EOL;
echo PHP_EOL;

// Prüfe ob CSS Print Method problematisch ist
if ($css_print_method === 'external') {
    echo "⚠️ PROBLEM GEFUNDEN: CSS Print Method ist auf 'external'" . PHP_EOL;
    echo "   Dies kann Probleme verursachen, wenn CSS-Dateien nicht geschrieben werden können." . PHP_EOL;
    echo PHP_EOL;

    // Korrektur anwenden
    echo "🔧 KORREKTUR: Setze CSS Print Method auf 'internal'..." . PHP_EOL;
    update_option('elementor_css_print_method', 'internal');
    echo "✅ CSS Print Method wurde auf 'internal' gesetzt!" . PHP_EOL;
    echo PHP_EOL;
}

// Prüfe Safe Mode
if (!empty($safe_mode)) {
    echo "⚠️ WARNUNG: Safe Mode ist aktiviert!" . PHP_EOL;
    echo "   Grund: " . json_encode($safe_mode) . PHP_EOL;
    echo "   Dies sollte nur temporär aktiviert sein." . PHP_EOL;
    echo PHP_EOL;
}

// Prüfe CSS-Verzeichnis
$upload_dir = wp_upload_dir();
$css_dir = $upload_dir['basedir'] . '/elementor/css/';

echo "📁 CSS-Verzeichnis Prüfung:" . PHP_EOL;
echo "-------------------------" . PHP_EOL;
echo "Pfad: " . $css_dir . PHP_EOL;

if (!file_exists($css_dir)) {
    echo "⚠️ Verzeichnis existiert nicht! Erstelle es..." . PHP_EOL;
    if (wp_mkdir_p($css_dir)) {
        echo "✅ Verzeichnis erstellt!" . PHP_EOL;
    } else {
        echo "❌ Konnte Verzeichnis nicht erstellen!" . PHP_EOL;
    }
} else {
    echo "✅ Verzeichnis existiert" . PHP_EOL;

    // Prüfe Schreibrechte
    if (is_writable($css_dir)) {
        echo "✅ Verzeichnis ist beschreibbar" . PHP_EOL;
    } else {
        echo "❌ Verzeichnis ist NICHT beschreibbar! Bitte Berechtigungen prüfen." . PHP_EOL;
    }

    // Zähle CSS-Dateien
    $css_files = glob($css_dir . '*.css');
    echo "📝 Anzahl CSS-Dateien: " . count($css_files) . PHP_EOL;
}

echo PHP_EOL;
echo "=== EMPFEHLUNGEN ===" . PHP_EOL;
echo "1. ✅ CSS Print Method wurde auf 'internal' gesetzt (kein externes CSS mehr nötig)" . PHP_EOL;
echo "2. 🔄 Führen Sie jetzt 'Clear Cache' und 'CSS Regenerate' im Admin-Tool aus" . PHP_EOL;
echo "3. 🧪 Testen Sie dann eine Seite im Elementor Editor" . PHP_EOL;
echo PHP_EOL;

// Cache löschen
if (class_exists('\Elementor\Plugin')) {
    echo "🗑️ Lösche Elementor Cache..." . PHP_EOL;
    \Elementor\Plugin::$instance->files_manager->clear_cache();
    echo "✅ Cache gelöscht!" . PHP_EOL;
}

echo PHP_EOL;
echo "=== FERTIG ===" . PHP_EOL;
