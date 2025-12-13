<?php
/**
 * Asset-Abhängigkeits-Scanner für DGPTM Module
 * Scannt alle Module nach CSS, JS und anderen Asset-Abhängigkeiten
 */

// WordPress laden
$wp_load_path = dirname(__FILE__) . '/../../../wp-load.php';
if (file_exists($wp_load_path)) {
    require_once $wp_load_path;
} else {
    die("WordPress konnte nicht geladen werden.\n");
}

if (!defined('ABSPATH')) {
    die('WordPress nicht geladen');
}

echo "DGPTM Asset-Abhängigkeits-Scanner\n";
echo "==================================\n\n";

// Asset-Typen
$asset_patterns = [
    'css' => [
        '/wp_enqueue_style\s*\(\s*[\'"]([^\'"]+)[\'"]/',
        '/\.css[\'"]/',
        '/<link[^>]+href=[\'"]([^\'"]+\.css)[\'"]/',
    ],
    'js' => [
        '/wp_enqueue_script\s*\(\s*[\'"]([^\'"]+)[\'"]/',
        '/\.js[\'"]/',
        '/<script[^>]+src=[\'"]([^\'"]+\.js)[\'"]/',
    ],
    'images' => [
        '/\.(png|jpg|jpeg|gif|svg|webp)[\'"]/',
        '/<img[^>]+src=[\'"]([^\'"]+)[\'"]/',
    ],
    'fonts' => [
        '/\.(woff|woff2|ttf|eot|otf)[\'"]/',
    ],
];

// Ergebnisse
$results = [];
$issues = [];

// Modul-Loader
$module_loader = dgptm_suite()->get_module_loader();
$available_modules = $module_loader->get_available_modules();

echo "Scanne " . count($available_modules) . " Module...\n\n";

foreach ($available_modules as $module_id => $module_info) {
    $module_path = $module_info['path'];
    $config = $module_info['config'];

    echo "Scanne: $module_id ({$config['name']})...\n";

    $module_results = [
        'module_id' => $module_id,
        'name' => $config['name'],
        'path' => $module_path,
        'assets' => [
            'css' => [],
            'js' => [],
            'images' => [],
            'fonts' => [],
        ],
        'missing_assets' => [],
        'directories' => [],
    ];

    // Alle PHP-Dateien im Modul scannen
    $php_files = scan_directory_recursive($module_path, '*.php');

    foreach ($php_files as $php_file) {
        $content = file_get_contents($php_file);

        // CSS Assets finden
        foreach ($asset_patterns['css'] as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[0] as $match) {
                    if (!in_array($match, $module_results['assets']['css'])) {
                        $module_results['assets']['css'][] = $match;
                    }
                }
            }
        }

        // JS Assets finden
        foreach ($asset_patterns['js'] as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[0] as $match) {
                    if (!in_array($match, $module_results['assets']['js'])) {
                        $module_results['assets']['js'][] = $match;
                    }
                }
            }
        }

        // Images finden
        foreach ($asset_patterns['images'] as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[0] as $match) {
                    if (!in_array($match, $module_results['assets']['images'])) {
                        $module_results['assets']['images'][] = $match;
                    }
                }
            }
        }

        // Fonts finden
        foreach ($asset_patterns['fonts'] as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[0] as $match) {
                    if (!in_array($match, $module_results['assets']['fonts'])) {
                        $module_results['assets']['fonts'][] = $match;
                    }
                }
            }
        }
    }

    // Prüfe auf Assets-Verzeichnisse
    $common_asset_dirs = ['assets', 'css', 'js', 'images', 'img', 'fonts', 'dist', 'build'];

    foreach ($common_asset_dirs as $dir_name) {
        $dir_path = $module_path . $dir_name;
        if (is_dir($dir_path)) {
            $module_results['directories'][] = $dir_name;
        }
    }

    // Prüfe ob Asset-Verzeichnisse existieren wenn Assets gefunden wurden
    $total_assets = count($module_results['assets']['css']) +
                   count($module_results['assets']['js']) +
                   count($module_results['assets']['images']) +
                   count($module_results['assets']['fonts']);

    if ($total_assets > 0 && empty($module_results['directories'])) {
        $issues[] = [
            'module_id' => $module_id,
            'type' => 'missing_asset_directory',
            'message' => "Modul verwendet Assets aber hat kein Asset-Verzeichnis",
            'severity' => 'warning',
        ];
    }

    // Prüfe ob enqueue-Funktionen ohne Datei-Referenzen verwendet werden
    $has_enqueue = (count($module_results['assets']['css']) > 0 || count($module_results['assets']['js']) > 0);

    if ($has_enqueue && empty($module_results['directories'])) {
        $issues[] = [
            'module_id' => $module_id,
            'type' => 'enqueue_without_files',
            'message' => "Modul nutzt wp_enqueue aber hat keine Asset-Verzeichnisse",
            'severity' => 'error',
        ];
    }

    // Speichere Ergebnisse
    $results[$module_id] = $module_results;

    // Ausgabe
    echo "  CSS: " . count($module_results['assets']['css']) . "\n";
    echo "  JS: " . count($module_results['assets']['js']) . "\n";
    echo "  Images: " . count($module_results['assets']['images']) . "\n";
    echo "  Fonts: " . count($module_results['assets']['fonts']) . "\n";
    echo "  Verzeichnisse: " . implode(', ', $module_results['directories']) . "\n\n";
}

// Zusammenfassung
echo "\n==================================\n";
echo "ZUSAMMENFASSUNG\n";
echo "==================================\n\n";

$modules_with_assets = 0;
$modules_without_assets = 0;
$total_css = 0;
$total_js = 0;
$total_images = 0;
$total_fonts = 0;

foreach ($results as $module_id => $data) {
    $has_assets = !empty($data['directories']) ||
                  !empty($data['assets']['css']) ||
                  !empty($data['assets']['js']) ||
                  !empty($data['assets']['images']) ||
                  !empty($data['assets']['fonts']);

    if ($has_assets) {
        $modules_with_assets++;
        $total_css += count($data['assets']['css']);
        $total_js += count($data['assets']['js']);
        $total_images += count($data['assets']['images']);
        $total_fonts += count($data['assets']['fonts']);
    } else {
        $modules_without_assets++;
    }
}

echo "Module mit Assets: $modules_with_assets\n";
echo "Module ohne Assets: $modules_without_assets\n\n";

echo "Gesamt CSS-Referenzen: $total_css\n";
echo "Gesamt JS-Referenzen: $total_js\n";
echo "Gesamt Bild-Referenzen: $total_images\n";
echo "Gesamt Font-Referenzen: $total_fonts\n\n";

// Probleme ausgeben
if (!empty($issues)) {
    echo "==================================\n";
    echo "GEFUNDENE PROBLEME\n";
    echo "==================================\n\n";

    foreach ($issues as $issue) {
        $severity_symbol = $issue['severity'] === 'error' ? '✗' : '⚠';
        echo "$severity_symbol [{$issue['module_id']}] {$issue['message']}\n";
    }

    echo "\n";
}

// Module mit Assets detailliert auflisten
echo "==================================\n";
echo "MODULE MIT ASSETS (DETAILLIERT)\n";
echo "==================================\n\n";

foreach ($results as $module_id => $data) {
    $has_assets = !empty($data['directories']) ||
                  !empty($data['assets']['css']) ||
                  !empty($data['assets']['js']) ||
                  !empty($data['assets']['images']) ||
                  !empty($data['assets']['fonts']);

    if ($has_assets) {
        echo "## {$data['name']} ($module_id)\n";
        echo "Pfad: {$data['path']}\n";

        if (!empty($data['directories'])) {
            echo "Asset-Verzeichnisse: " . implode(', ', $data['directories']) . "\n";
        }

        if (!empty($data['assets']['css'])) {
            echo "CSS-Dateien:\n";
            foreach (array_slice($data['assets']['css'], 0, 5) as $asset) {
                echo "  - $asset\n";
            }
            if (count($data['assets']['css']) > 5) {
                echo "  ... und " . (count($data['assets']['css']) - 5) . " weitere\n";
            }
        }

        if (!empty($data['assets']['js'])) {
            echo "JS-Dateien:\n";
            foreach (array_slice($data['assets']['js'], 0, 5) as $asset) {
                echo "  - $asset\n";
            }
            if (count($data['assets']['js']) > 5) {
                echo "  ... und " . (count($data['assets']['js']) - 5) . " weitere\n";
            }
        }

        echo "\n";
    }
}

// JSON-Export
$export_file = DGPTM_SUITE_PATH . 'asset-scan-results.json';
file_put_contents($export_file, json_encode($results, JSON_PRETTY_PRINT));
echo "Vollständige Ergebnisse gespeichert in: $export_file\n";

// Rekursive Verzeichnis-Scan-Funktion
function scan_directory_recursive($dir, $pattern) {
    $files = [];

    if (!is_dir($dir)) {
        return $files;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $file) {
        if ($file->isFile() && fnmatch($pattern, $file->getFilename())) {
            $files[] = $file->getPathname();
        }
    }

    return $files;
}
