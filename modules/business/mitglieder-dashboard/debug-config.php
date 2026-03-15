<?php
/**
 * Debug: Check config without WordPress
 * DELETE AFTER USE
 */
header('Content-Type: text/plain; charset=utf-8');
echo "=== Dashboard Debug ===\n\n";

// Find wp-config.php
$dir = __DIR__;
for ($i = 0; $i < 10; $i++) {
    $dir = dirname($dir);
    if (file_exists($dir . '/wp-config.php')) {
        break;
    }
}

$wpconfig = $dir . '/wp-config.php';
if (!file_exists($wpconfig)) {
    echo "wp-config.php not found\n";
    exit;
}

// Extract DB credentials
$content = file_get_contents($wpconfig);
preg_match("/define\s*\(\s*'DB_NAME'\s*,\s*'([^']+)'/", $content, $m);
$db_name = $m[1] ?? '';
preg_match("/define\s*\(\s*'DB_USER'\s*,\s*'([^']+)'/", $content, $m);
$db_user = $m[1] ?? '';
preg_match("/define\s*\(\s*'DB_PASSWORD'\s*,\s*'([^']+)'/", $content, $m);
$db_pass = $m[1] ?? '';
preg_match("/define\s*\(\s*'DB_HOST'\s*,\s*'([^']+)'/", $content, $m);
$db_host = $m[1] ?? 'localhost';
preg_match("/table_prefix\s*=\s*'([^']+)'/", $content, $m);
$prefix = $m[1] ?? 'wp_';

echo "DB: {$db_name} @ {$db_host}\n\n";

try {
    $pdo = new PDO("mysql:host={$db_host};dbname={$db_name};charset=utf8mb4", $db_user, $db_pass);
    $stmt = $pdo->prepare("SELECT option_value FROM {$prefix}options WHERE option_name = 'dgptm_dashboard_config' LIMIT 1");
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo "dgptm_dashboard_config NOT FOUND in wp_options\n";
    } else {
        $config = unserialize($row['option_value']);
        if (!$config) {
            $config = json_decode($row['option_value'], true);
        }

        if (empty($config['tabs'])) {
            echo "NO TABS!\n";
            echo "Raw (first 500): " . substr($row['option_value'], 0, 500) . "\n";
        } else {
            foreach ($config['tabs'] as $tab) {
                $html_val = $tab['content_html'] ?? null;
                $html_status = $html_val === null ? 'NOT_SET' : ($html_val === '' ? 'EMPTY_STRING' : 'HAS_CONTENT(' . strlen($html_val) . ')');
                echo sprintf("%-25s parent=%-15s html=%s\n",
                    $tab['id'],
                    $tab['parent_tab'] ?? 'NOT_SET',
                    $html_status
                );
            }
        }
    }
} catch (Exception $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}

echo "\n=== Template Files ===\n";
foreach (glob(__DIR__ . '/templates/tabs/*.php') as $f) {
    echo basename($f) . "\n";
}

echo "\nDELETE THIS FILE!\n";
