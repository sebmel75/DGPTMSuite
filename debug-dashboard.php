<?php
/**
 * Dashboard Config Debug - im Plugin-Suite Root
 * URL: /wp-content/plugins/dgptm-plugin-suite/debug-dashboard.php
 * DELETE AFTER USE
 */
header('Content-Type: text/plain; charset=utf-8');
echo "=== Dashboard Config Debug ===\n\n";

// WordPress bootstrap
$wp_load = dirname(__FILE__) . '/../../../wp-load.php';
if (!file_exists($wp_load)) {
    echo "wp-load.php not found at: {$wp_load}\n";
    exit;
}
require_once $wp_load;

if (!is_user_logged_in() || !current_user_can('manage_options')) {
    echo "Login als Admin erforderlich.\n";
    exit;
}

$config = get_option('dgptm_dashboard_config', []);

if (empty($config['tabs'])) {
    echo "KEINE TABS in Config!\n";
    echo "Option vorhanden: " . (get_option('dgptm_dashboard_config', '__NOPE__') !== '__NOPE__' ? 'JA' : 'NEIN') . "\n";
} else {
    echo "Tabs: " . count($config['tabs']) . "\n\n";
    foreach ($config['tabs'] as $tab) {
        $html_val = $tab['content_html'] ?? null;
        if ($html_val === null) $html_status = 'NOT_SET';
        elseif ($html_val === '') $html_status = 'EMPTY_STRING';
        else $html_status = 'CONTENT(' . strlen($html_val) . ')';

        echo sprintf("%-25s active=%-5s parent=%-15s html=%s\n",
            $tab['id'],
            !empty($tab['active']) ? 'YES' : 'NO',
            $tab['parent_tab'] ?? 'NOT_SET',
            $html_status
        );
    }
}

echo "\n=== Template Files ===\n";
$tpl_dir = dirname(__FILE__) . '/modules/business/mitglieder-dashboard/templates/tabs/';
if (is_dir($tpl_dir)) {
    foreach (glob($tpl_dir . '*.php') as $f) {
        echo basename($f) . " (" . filesize($f) . " bytes)\n";
    }
} else {
    echo "Template dir not found: {$tpl_dir}\n";
}

echo "\nDELETE THIS FILE!\n";
