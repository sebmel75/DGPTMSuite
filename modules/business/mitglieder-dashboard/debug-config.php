<?php
/**
 * Debug: Show saved config for profil tab
 * URL: /wp-content/plugins/dgptm-plugin-suite/modules/business/mitglieder-dashboard/debug-config.php
 * DELETE AFTER USE
 */
require_once dirname(__FILE__) . '/../../../dgptm-master.php';

header('Content-Type: text/plain; charset=utf-8');

$config = get_option('dgptm_dashboard_config', []);

echo "=== Saved Config ===\n\n";

if (empty($config['tabs'])) {
    echo "NO TABS in saved config!\n";
} else {
    foreach ($config['tabs'] as $tab) {
        $has_html = isset($tab['content_html']);
        $html_len = strlen($tab['content_html'] ?? '');
        $has_parent = isset($tab['parent_tab']);
        echo sprintf("%-25s parent=%-15s content_html=%s (%d chars)\n",
            $tab['id'],
            $tab['parent_tab'] ?? 'NOT SET',
            $has_html ? ($html_len > 0 ? 'YES' : 'EMPTY') : 'NOT SET',
            $html_len
        );
    }
}

echo "\n=== Template Files ===\n";
$dir = __DIR__ . '/templates/tabs/';
foreach (glob($dir . '*.php') as $f) {
    echo basename($f) . " (" . filesize($f) . " bytes)\n";
}

echo "\n=== Profil Tab Detail ===\n";
foreach ($config['tabs'] as $tab) {
    if ($tab['id'] === 'profil') {
        echo "content_html value: " . var_export($tab['content_html'] ?? 'NOT SET', true) . "\n";
        echo "!empty check: " . (!empty($tab['content_html'] ?? '') ? 'TRUE (uses html)' : 'FALSE (uses template)') . "\n";
        $tpl = __DIR__ . '/templates/tabs/tab-profil.php';
        echo "Template exists: " . (file_exists($tpl) ? 'YES' : 'NO') . "\n";
    }
}

echo "\nDELETE THIS FILE!\n";
