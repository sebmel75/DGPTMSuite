<?php
/**
 * Einmalige OPcache-Flush-Datei
 * Aufrufen: https://perfusiologie.de/wp-content/plugins/dgptm-plugin-suite/modules/business/mitglieder-dashboard/flush-cache.php
 * NACH BENUTZUNG LOESCHEN!
 */

header('Content-Type: text/plain; charset=utf-8');

echo "=== DGPTM Dashboard Cache Flush ===\n\n";

// OPcache
if (function_exists('opcache_get_status')) {
    $status = opcache_get_status(false);
    echo "OPcache enabled: " . ($status['opcache_enabled'] ? 'JA' : 'NEIN') . "\n";
    echo "Cached scripts: " . ($status['opcache_statistics']['num_cached_scripts'] ?? '?') . "\n";

    if (function_exists('opcache_reset')) {
        opcache_reset();
        echo "OPcache RESET: OK\n";
    } else {
        echo "opcache_reset() nicht verfuegbar\n";
    }
} else {
    echo "OPcache nicht verfuegbar\n";
}

echo "\n";

// Check if dashboard files exist
$base = __DIR__ . '/';
$files = [
    'dgptm-mitglieder-dashboard.php',
    'includes/class-dashboard-renderer.php',
    'includes/class-dashboard-config.php',
    'templates/frontend-dashboard.php',
    'templates/tabs/tab-profil.php',
    'assets/css/dashboard.css',
    'assets/js/dashboard.js',
];

echo "=== Dashboard Dateien ===\n";
foreach ($files as $f) {
    $path = $base . $f;
    if (file_exists($path)) {
        $mtime = date('Y-m-d H:i:s', filemtime($path));
        $size = filesize($path);
        echo "OK  {$f} ({$size} bytes, {$mtime})\n";
    } else {
        echo "FEHLT  {$f}\n";
    }
}

echo "\n";

// Show version from main file
$main = file_get_contents($base . 'dgptm-mitglieder-dashboard.php');
if (preg_match("/DGPTM_DASHBOARD_VERSION.*?'([^']+)'/", $main, $m)) {
    echo "Dashboard Version: " . $m[1] . "\n";
}

// Show first line of CSS to check content
$css = file_get_contents($base . 'assets/css/dashboard.css');
echo "CSS Start: " . substr($css, 0, 60) . "\n";
echo "CSS Groesse: " . strlen($css) . " bytes\n";

// Check tab-profil for folder-tabs
$profil = file_get_contents($base . 'templates/tabs/tab-profil.php');
echo "tab-profil hat folder-tabs: " . (strpos($profil, 'dgptm-folder-tabs') !== false ? 'JA' : 'NEIN') . "\n";
echo "tab-profil hat subtab-nav (alt): " . (strpos($profil, 'dgptm-subtab-nav') !== false ? 'JA' : 'NEIN') . "\n";

echo "\nFertig. DIESE DATEI NACH BENUTZUNG LOESCHEN!\n";
