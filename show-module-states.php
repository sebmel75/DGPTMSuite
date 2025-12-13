<?php
/**
 * DGPTM Module States Viewer
 * Zeigt den aktuellen Zustand aller Module an
 *
 * AUFRUF: http://ihre-domain.de/wp-content/plugins/dgptm-plugin-suite/show-module-states.php
 */

// WordPress laden
require_once dirname(__FILE__) . '/../../../wp-load.php';

// Sicherheit: Nur für Admins
if (!current_user_can('manage_options')) {
    die('Access denied');
}

// Einstellungen abrufen
$settings = get_option('dgptm_suite_settings', []);
$active_modules = $settings['active_modules'] ?? [];

?>
<!DOCTYPE html>
<html>
<head>
    <title>DGPTM Module States</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            padding: 20px;
            background: #f0f0f1;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        h1 {
            color: #1d2327;
            margin-top: 0;
        }
        .info-box {
            background: #f6f7f7;
            border-left: 4px solid #2271b1;
            padding: 15px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #f6f7f7;
            font-weight: 600;
            color: #1d2327;
        }
        .status-active {
            color: #00a32a;
            font-weight: 600;
        }
        .status-inactive {
            color: #d63638;
        }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-active {
            background: #d5f4e6;
            color: #00a32a;
        }
        .badge-inactive {
            background: #f0e5e5;
            color: #d63638;
        }
        .code-block {
            background: #1d2327;
            color: #f0f0f1;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            margin: 20px 0;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-box {
            background: #f6f7f7;
            padding: 20px;
            border-radius: 4px;
            text-align: center;
        }
        .stat-number {
            font-size: 32px;
            font-weight: 600;
            color: #2271b1;
        }
        .stat-label {
            color: #646970;
            font-size: 14px;
            margin-top: 5px;
        }
        .btn {
            display: inline-block;
            padding: 8px 16px;
            background: #2271b1;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            margin-top: 20px;
        }
        .btn:hover {
            background: #135e96;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>📊 DGPTM Module States</h1>

        <div class="info-box">
            <strong>Datenbank-Speicherort:</strong><br>
            Tabelle: <code>wp_options</code><br>
            Option Name: <code>dgptm_suite_settings</code><br>
            Array Key: <code>active_modules</code>
        </div>

        <?php
        $total = count($active_modules);
        $active_count = count(array_filter($active_modules));
        $inactive_count = $total - $active_count;
        ?>

        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?php echo $total; ?></div>
                <div class="stat-label">Gesamt</div>
            </div>
            <div class="stat-box">
                <div class="stat-number" style="color: #00a32a;"><?php echo $active_count; ?></div>
                <div class="stat-label">Aktiv</div>
            </div>
            <div class="stat-box">
                <div class="stat-number" style="color: #d63638;"><?php echo $inactive_count; ?></div>
                <div class="stat-label">Inaktiv</div>
            </div>
        </div>

        <h2>Module Status Übersicht</h2>

        <table>
            <thead>
                <tr>
                    <th>Modul ID</th>
                    <th>Status</th>
                    <th>Wert in DB</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($active_modules as $module_id => $is_active): ?>
                <tr>
                    <td><code><?php echo esc_html($module_id); ?></code></td>
                    <td>
                        <?php if ($is_active): ?>
                            <span class="badge badge-active">AKTIV</span>
                        <?php else: ?>
                            <span class="badge badge-inactive">INAKTIV</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <code><?php echo $is_active ? 'true' : 'false'; ?></code>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h2>Komplette Einstellungen (JSON)</h2>
        <div class="code-block">
<?php echo htmlspecialchars(json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?>
        </div>

        <h2>SQL Abfrage</h2>
        <div class="code-block">
SELECT option_value
FROM wp_options
WHERE option_name = 'dgptm_suite_settings';
        </div>

        <h2>PHP Code zum Ändern</h2>
        <div class="code-block">
// Modul aktivieren
$settings = get_option('dgptm_suite_settings', []);
$settings['active_modules']['modul-id'] = true;
update_option('dgptm_suite_settings', $settings);

// Modul deaktivieren
$settings = get_option('dgptm_suite_settings', []);
$settings['active_modules']['modul-id'] = false;
update_option('dgptm_suite_settings', $settings);

// Status prüfen
$settings = get_option('dgptm_suite_settings', []);
$is_active = $settings['active_modules']['modul-id'] ?? false;
        </div>

        <a href="<?php echo admin_url('admin.php?page=dgptm-suite'); ?>" class="btn">
            ← Zurück zum Dashboard
        </a>
    </div>
</body>
</html>
