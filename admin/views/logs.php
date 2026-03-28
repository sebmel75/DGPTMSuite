<?php
/**
 * System Logs View
 * Zeigt DGPTM Suite Logs aus Datenbank und WordPress debug.log
 *
 * @version 2.0.0 - Hybrid-Logging mit DB-Support
 */

if (!defined('ABSPATH')) {
    exit;
}

// Logging-Einstellungen speichern
if (isset($_POST['dgptm_save_logging_settings']) && check_admin_referer('dgptm_logging_settings')) {
    $settings = get_option('dgptm_suite_settings', []);

    // Legacy-Settings
    $settings['enable_logging'] = isset($_POST['enable_logging']);
    $settings['enable_verbose_logging'] = isset($_POST['enable_verbose_logging']);

    // Log-Cleanup-Alter speichern
    if (isset($_POST['log_cleanup_age'])) {
        $cleanup_age = absint($_POST['log_cleanup_age']);
        $settings['log_cleanup_age'] = max(1, min(168, $cleanup_age));
    }

    // Neue Logging-Einstellungen
    if (!isset($settings['logging'])) {
        $settings['logging'] = [];
    }

    $settings['logging']['global_level'] = isset($_POST['global_level']) ? sanitize_text_field($_POST['global_level']) : 'warning';
    $settings['logging']['db_enabled'] = isset($_POST['db_enabled']);
    $settings['logging']['file_enabled'] = isset($_POST['file_enabled']);
    $settings['logging']['max_db_entries'] = isset($_POST['max_db_entries']) ? absint($_POST['max_db_entries']) : 100000;
    $settings['logging']['max_debug_log_lines'] = isset($_POST['max_debug_log_lines']) ? max(100, min(10000, absint($_POST['max_debug_log_lines']))) : 1000;

    update_option('dgptm_suite_settings', $settings);

    echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__('Logging-Einstellungen gespeichert.', 'dgptm-suite') . '</p></div>';
}

// Modul Debug-Level speichern (AJAX)
if (isset($_POST['dgptm_save_module_level']) && check_admin_referer('dgptm_logging_settings')) {
    $module_id = sanitize_text_field($_POST['module_id']);
    $level = sanitize_text_field($_POST['module_level']);
    dgptm_set_module_log_level($module_id, $level);
    echo '<div class="notice notice-success is-dismissible"><p>' . sprintf(__('Debug-Level für %s gespeichert.', 'dgptm-suite'), esc_html($module_id)) . '</p></div>';
}

// Aktuelle Einstellungen laden
$settings = get_option('dgptm_suite_settings', []);
$enable_logging = isset($settings['enable_logging']) ? (bool)$settings['enable_logging'] : false;
$enable_verbose_logging = isset($settings['enable_verbose_logging']) ? (bool)$settings['enable_verbose_logging'] : false;
$log_cleanup_age = isset($settings['log_cleanup_age']) ? absint($settings['log_cleanup_age']) : 24;

// Neue Logging-Settings
$global_level = isset($settings['logging']['global_level']) ? $settings['logging']['global_level'] : 'warning';
$db_enabled = isset($settings['logging']['db_enabled']) ? (bool)$settings['logging']['db_enabled'] : true;
$file_enabled = isset($settings['logging']['file_enabled']) ? (bool)$settings['logging']['file_enabled'] : true;
$max_db_entries = isset($settings['logging']['max_db_entries']) ? (int)$settings['logging']['max_db_entries'] : 100000;
$max_debug_log_lines = isset($settings['logging']['max_debug_log_lines']) ? (int)$settings['logging']['max_debug_log_lines'] : 1000;

// Log-Quelle bestimmen (db oder file)
$source = isset($_GET['source']) ? sanitize_text_field($_GET['source']) : 'db';

// Filter-Parameter
$filter_level = isset($_GET['level']) ? sanitize_text_field($_GET['level']) : '';
$filter_module = isset($_GET['module']) ? sanitize_text_field($_GET['module']) : '';
$filter_search = isset($_GET['search']) ? sanitize_text_field($_GET['search']) : '';
$lines = isset($_GET['lines']) ? absint($_GET['lines']) : 100;
$lines = max(50, min(500, $lines));
$paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;

// DB-Statistiken holen
$db_stats = [];
$db_available = false;
if (class_exists('DGPTM_Logger_Installer') && DGPTM_Logger_Installer::table_exists()) {
    $db_available = true;
    $db_stats = DGPTM_Logger_Installer::get_stats();
}

// Module mit Logs holen
$logged_modules = DGPTM_Logger::get_logged_modules();

// Alle registrierten Module für Debug-Level Einstellungen
$all_modules = [];
$module_loader = dgptm_suite()->get_module_loader();
if ($module_loader && method_exists($module_loader, 'get_all_modules')) {
    $all_modules = $module_loader->get_all_modules();
}

// Logs basierend auf Quelle laden
$log_content = '';
$log_lines = [];
$total_entries = 0;
$total_pages = 1;

if ($source === 'db' && $db_available) {
    // Aus Datenbank laden
    $filters = [
        'per_page' => $lines,
        'page' => $paged,
        'order' => 'DESC'
    ];

    if (!empty($filter_level)) {
        $filters['level'] = $filter_level;
    }

    if (!empty($filter_module)) {
        $filters['module_id'] = $filter_module;
    }

    if (!empty($filter_search)) {
        $filters['search'] = $filter_search;
    }

    $result = DGPTM_Logger::query_logs($filters);
    $log_entries = $result['logs'];
    $total_entries = $result['total'];
    $total_pages = $result['total_pages'];

} else {
    // Aus File laden (Legacy)
    $debug_log_path = WP_CONTENT_DIR . '/debug.log';

    if (file_exists($debug_log_path) && is_readable($debug_log_path)) {
        $all_lines = file($debug_log_path, FILE_IGNORE_NEW_LINES);
        $all_lines = array_slice($all_lines, -2000); // Max 2000 Zeilen

        // Filter anwenden
        foreach ($all_lines as $line) {
            $include = true;

            // Level-Filter
            if (!empty($filter_level)) {
                switch ($filter_level) {
                    case 'critical':
                        $include = (stripos($line, 'KRITISCH') !== false || stripos($line, 'CRITICAL') !== false);
                        break;
                    case 'error':
                        $include = (stripos($line, 'ERROR') !== false || stripos($line, 'Fatal error') !== false);
                        break;
                    case 'warning':
                        $include = (stripos($line, 'WARNING') !== false || stripos($line, 'Warning:') !== false);
                        break;
                    case 'info':
                        $include = (stripos($line, 'DGPTM Suite:') !== false && stripos($line, 'WARNING') === false && stripos($line, 'ERROR') === false);
                        break;
                    case 'verbose':
                        $include = (stripos($line, '[VERBOSE]') !== false);
                        break;
                }
            }

            // Modul-Filter
            if ($include && !empty($filter_module)) {
                $include = (stripos($line, '[' . $filter_module . ']') !== false);
            }

            // Such-Filter
            if ($include && !empty($filter_search)) {
                $include = (stripos($line, $filter_search) !== false);
            }

            if ($include) {
                $log_lines[] = $line;
            }
        }

        $total_entries = count($log_lines);
        $log_lines = array_slice(array_reverse($log_lines), ($paged - 1) * $lines, $lines);
        $total_pages = ceil($total_entries / $lines);
    }
}

// Level-Labels
$level_labels = [
    '' => __('Alle Level', 'dgptm-suite'),
    'critical' => __('Kritisch', 'dgptm-suite'),
    'error' => __('Fehler', 'dgptm-suite'),
    'warning' => __('Warnungen', 'dgptm-suite'),
    'info' => __('Info', 'dgptm-suite'),
    'verbose' => __('Verbose', 'dgptm-suite')
];

$level_icons = [
    'critical' => '🔴',
    'error' => '🟠',
    'warning' => '🟡',
    'info' => '🔵',
    'verbose' => '🔍'
];
?>

<div class="wrap dgptm-suite-logs">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <!-- Tab-Navigation -->
    <h2 class="nav-tab-wrapper">
        <a href="?page=dgptm-suite-logs&tab=logs" class="nav-tab <?php echo (!isset($_GET['tab']) || $_GET['tab'] === 'logs') ? 'nav-tab-active' : ''; ?>">
            <?php _e('Log-Anzeige', 'dgptm-suite'); ?>
        </a>
        <a href="?page=dgptm-suite-logs&tab=settings" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'settings') ? 'nav-tab-active' : ''; ?>">
            <?php _e('Einstellungen', 'dgptm-suite'); ?>
        </a>
        <a href="?page=dgptm-suite-logs&tab=modules" class="nav-tab <?php echo (isset($_GET['tab']) && $_GET['tab'] === 'modules') ? 'nav-tab-active' : ''; ?>">
            <?php _e('Modul Debug-Level', 'dgptm-suite'); ?>
        </a>
    </h2>

    <?php
    $current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'logs';

    if ($current_tab === 'settings'):
    ?>
    <!-- Einstellungen Tab -->
    <div class="dgptm-logging-settings" style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
        <h2 style="margin-top: 0;">
            <span class="dashicons dashicons-admin-settings" style="color: #2271b1;"></span>
            <?php _e('Logging-Einstellungen', 'dgptm-suite'); ?>
        </h2>

        <form method="post" action="">
            <?php wp_nonce_field('dgptm_logging_settings'); ?>

            <table class="form-table">
                <tr>
                    <th scope="row"><?php _e('Globales Debug-Level', 'dgptm-suite'); ?></th>
                    <td>
                        <select name="global_level" id="global_level">
                            <option value="critical" <?php selected($global_level, 'critical'); ?>><?php _e('Critical - Nur kritische Fehler', 'dgptm-suite'); ?></option>
                            <option value="error" <?php selected($global_level, 'error'); ?>><?php _e('Error - Fehler und kritisch', 'dgptm-suite'); ?></option>
                            <option value="warning" <?php selected($global_level, 'warning'); ?>><?php _e('Warning - Warnungen und höher (Standard)', 'dgptm-suite'); ?></option>
                            <option value="info" <?php selected($global_level, 'info'); ?>><?php _e('Info - Informationen und höher', 'dgptm-suite'); ?></option>
                            <option value="verbose" <?php selected($global_level, 'verbose'); ?>><?php _e('Verbose - Alles loggen (nur für Debugging!)', 'dgptm-suite'); ?></option>
                        </select>
                        <p class="description"><?php _e('Bestimmt, welche Log-Level global erfasst werden. Module können eigene Level haben.', 'dgptm-suite'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Log-Speicher', 'dgptm-suite'); ?></th>
                    <td>
                        <label style="display: block; margin-bottom: 10px;">
                            <input type="checkbox" name="db_enabled" value="1" <?php checked($db_enabled); ?>>
                            <?php _e('In Datenbank speichern', 'dgptm-suite'); ?>
                            <span class="description">(<?php _e('Strukturierte Logs mit Filter-Optionen', 'dgptm-suite'); ?>)</span>
                        </label>
                        <label style="display: block;">
                            <input type="checkbox" name="file_enabled" value="1" <?php checked($file_enabled); ?>>
                            <?php _e('In debug.log speichern', 'dgptm-suite'); ?>
                            <span class="description">(<?php _e('Standard WordPress Debug-Log', 'dgptm-suite'); ?>)</span>
                        </label>
                        <p class="description" style="margin-top: 10px;">
                            <?php _e('Hinweis: Critical und Error werden IMMER in debug.log geschrieben.', 'dgptm-suite'); ?>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Max. DB-Einträge', 'dgptm-suite'); ?></th>
                    <td>
                        <input type="number" name="max_db_entries" value="<?php echo esc_attr($max_db_entries); ?>" min="1000" max="1000000" style="width: 120px;">
                        <p class="description"><?php _e('Maximale Anzahl von Log-Einträgen in der Datenbank. Ältere werden automatisch gelöscht.', 'dgptm-suite'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Max. debug.log Zeilen', 'dgptm-suite'); ?></th>
                    <td>
                        <input type="number" name="max_debug_log_lines" value="<?php echo esc_attr($max_debug_log_lines); ?>" min="100" max="10000" style="width: 120px;">
                        <p class="description"><?php _e('Maximale Zeilenanzahl in der debug.log. Wird stündlich automatisch getrimmt.', 'dgptm-suite'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><?php _e('Auto-Bereinigung (Stunden)', 'dgptm-suite'); ?></th>
                    <td>
                        <input type="number" name="log_cleanup_age" value="<?php echo esc_attr($log_cleanup_age); ?>" min="1" max="168" style="width: 80px;">
                        <p class="description"><?php _e('Nicht-kritische Logs älter als diese Stundenzahl werden automatisch gelöscht. Critical/Error bleiben erhalten.', 'dgptm-suite'); ?></p>
                    </td>
                </tr>

                <!-- Legacy Settings (versteckt, für Kompatibilität) -->
                <tr style="display: none;">
                    <td colspan="2">
                        <input type="checkbox" name="enable_logging" value="1" <?php checked($enable_logging); ?>>
                        <input type="checkbox" name="enable_verbose_logging" value="1" <?php checked($enable_verbose_logging); ?>>
                    </td>
                </tr>
            </table>

            <?php if ($db_available): ?>
            <div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0;">
                <h4 style="margin-top: 0;"><?php _e('Datenbank-Statistiken', 'dgptm-suite'); ?></h4>
                <ul style="margin: 0;">
                    <li><strong><?php _e('Gesamteinträge:', 'dgptm-suite'); ?></strong> <?php echo number_format($db_stats['total'], 0, ',', '.'); ?></li>
                    <li><strong><?php _e('Tabellengröße:', 'dgptm-suite'); ?></strong> <?php echo esc_html($db_stats['table_size']); ?></li>
                    <li><strong><?php _e('Ältester Eintrag:', 'dgptm-suite'); ?></strong> <?php echo $db_stats['oldest'] ? esc_html($db_stats['oldest']) : '-'; ?></li>
                    <li><strong><?php _e('Neuester Eintrag:', 'dgptm-suite'); ?></strong> <?php echo $db_stats['newest'] ? esc_html($db_stats['newest']) : '-'; ?></li>
                </ul>
                <?php if (!empty($db_stats['by_level'])): ?>
                <p style="margin-bottom: 0; margin-top: 10px;">
                    <strong><?php _e('Nach Level:', 'dgptm-suite'); ?></strong>
                    <?php
                    $level_counts = [];
                    foreach ($db_stats['by_level'] as $level_stat) {
                        $icon = isset($level_icons[$level_stat['level']]) ? $level_icons[$level_stat['level']] : '';
                        $level_counts[] = $icon . ' ' . ucfirst($level_stat['level']) . ': ' . number_format($level_stat['count'], 0, ',', '.');
                    }
                    echo implode(' | ', $level_counts);
                    ?>
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($db_available): ?>
            <div style="background: #fff8e1; border-left: 4px solid #f0ad4e; padding: 15px; margin: 20px 0;">
                <h4 style="margin-top: 0;">Log-Tabelle bereinigen</h4>
                <p style="margin: 0 0 12px; font-size: 13px; color: #666;">
                    Loescht Eintraege aus der Datenbank. Critical/Error-Eintraege bleiben bei der Alters-Bereinigung erhalten.
                </p>
                <div style="display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                    <button type="button" id="dgptm-cleanup-old" class="button" data-mode="old">
                        Aelter als <input type="number" id="dgptm-cleanup-hours" value="48" min="1" max="8760" style="width: 60px; margin: 0 4px;"> Stunden loeschen
                    </button>
                    <button type="button" id="dgptm-cleanup-info" class="button" data-mode="info">
                        Nur Info-Eintraege loeschen
                    </button>
                    <button type="button" id="dgptm-cleanup-all" class="button button-link-delete" data-mode="all">
                        Alle Eintraege loeschen
                    </button>
                </div>
                <p id="dgptm-cleanup-result" style="margin: 10px 0 0; display: none;"></p>
            </div>
            <script>
            jQuery(function($){
                $('[id^="dgptm-cleanup-"]').filter('button').on('click', function(){
                    var mode = $(this).data('mode');
                    var hours = $('#dgptm-cleanup-hours').val();
                    var msg = mode === 'all' ? 'ALLE Log-Eintraege unwiderruflich loeschen?' :
                              mode === 'info' ? 'Alle Info-Eintraege loeschen (Errors/Critical bleiben)?' :
                              'Eintraege aelter als ' + hours + ' Stunden loeschen?';

                    if (!confirm(msg)) return;

                    var $result = $('#dgptm-cleanup-result');
                    $result.html('Bereinige...').show().css('color', '#666');

                    $.post(ajaxurl, {
                        action: 'dgptm_cleanup_logs',
                        nonce: '<?php echo wp_create_nonce('dgptm_cleanup_logs'); ?>',
                        mode: mode,
                        hours: hours
                    }, function(res){
                        if (res.success) {
                            $result.html(res.data.message).css('color', '#46b450');
                            setTimeout(function(){ location.reload(); }, 1500);
                        } else {
                            $result.html(res.data || 'Fehler').css('color', '#dc3232');
                        }
                    });
                });
            });
            </script>
            <?php endif; ?>

            <p class="submit">
                <button type="submit" name="dgptm_save_logging_settings" class="button button-primary">
                    <?php _e('Einstellungen speichern', 'dgptm-suite'); ?>
                </button>
            </p>
        </form>

        <!-- Health-Check API -->
        <?php if (class_exists('DGPTM_Health_Check')): ?>
        <div style="background: #f0f6fc; border-left: 4px solid #2271b1; padding: 15px; margin: 20px 0;">
            <h4 style="margin-top: 0;">Health-Check API</h4>
            <p class="description"><?php _e('REST-Endpoint fuer automatisierte Ueberwachung (z.B. Claude Code, Monitoring).', 'dgptm-suite'); ?></p>
            <p><strong>Endpoint:</strong> <code><?php echo esc_html(home_url('/wp-json/dgptm/v1/health-check')); ?></code></p>
            <p>
                <strong>Token:</strong>
                <input type="text" value="<?php echo esc_attr(DGPTM_Health_Check::get_token()); ?>" readonly style="width:400px;font-family:monospace;font-size:12px;" onclick="this.select();">
                <button type="button" class="button button-small" onclick="navigator.clipboard.writeText(this.previousElementSibling.value);this.textContent='Kopiert!';">Kopieren</button>
            </p>
            <p class="description"><?php _e('Verwende diesen Token als Bearer-Token im Authorization-Header.', 'dgptm-suite'); ?></p>
        </div>
        <?php endif; ?>
    </div>

    <?php elseif ($current_tab === 'modules'): ?>
    <!-- Modul Debug-Level Tab -->
    <div class="dgptm-module-levels" style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
        <h2 style="margin-top: 0;">
            <span class="dashicons dashicons-admin-tools" style="color: #2271b1;"></span>
            <?php _e('Debug-Level pro Modul', 'dgptm-suite'); ?>
        </h2>

        <p class="description"><?php _e('Hier können Sie für einzelne Module ein eigenes Debug-Level festlegen, das vom globalen Level abweicht.', 'dgptm-suite'); ?></p>

        <table class="wp-list-table widefat fixed striped" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th style="width: 200px;"><?php _e('Modul', 'dgptm-suite'); ?></th>
                    <th style="width: 200px;"><?php _e('Debug-Level', 'dgptm-suite'); ?></th>
                    <th><?php _e('Status', 'dgptm-suite'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($all_modules)): ?>
                    <?php foreach ($all_modules as $module): ?>
                        <?php
                        $module_id = $module['id'];
                        $module_level = dgptm_get_module_log_level($module_id);
                        $is_active = isset($settings['active_modules'][$module_id]) && $settings['active_modules'][$module_id];
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($module['name']); ?></strong>
                                <br><code style="font-size: 11px;"><?php echo esc_html($module_id); ?></code>
                            </td>
                            <td>
                                <form method="post" style="display: inline;">
                                    <?php wp_nonce_field('dgptm_logging_settings'); ?>
                                    <input type="hidden" name="module_id" value="<?php echo esc_attr($module_id); ?>">
                                    <select name="module_level" onchange="this.form.submit()" style="width: 150px;">
                                        <option value="global" <?php selected($module_level, 'global'); ?>><?php _e('Global (Standard)', 'dgptm-suite'); ?></option>
                                        <option value="verbose" <?php selected($module_level, 'verbose'); ?>>🔍 Verbose</option>
                                        <option value="info" <?php selected($module_level, 'info'); ?>>🔵 Info</option>
                                        <option value="warning" <?php selected($module_level, 'warning'); ?>>🟡 Warning</option>
                                        <option value="error" <?php selected($module_level, 'error'); ?>>🟠 Error</option>
                                        <option value="critical" <?php selected($module_level, 'critical'); ?>>🔴 Critical</option>
                                    </select>
                                    <input type="hidden" name="dgptm_save_module_level" value="1">
                                </form>
                            </td>
                            <td>
                                <?php if ($is_active): ?>
                                    <span style="color: #00a32a;">● <?php _e('Aktiv', 'dgptm-suite'); ?></span>
                                <?php else: ?>
                                    <span style="color: #999;">○ <?php _e('Inaktiv', 'dgptm-suite'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3"><?php _e('Keine Module gefunden.', 'dgptm-suite'); ?></td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php else: ?>
    <!-- Log-Anzeige Tab -->

    <!-- Filter-Bereich -->
    <div class="dgptm-log-filters" style="background: white; padding: 20px; margin: 20px 0; border: 1px solid #ccd0d4; border-radius: 4px;">
        <form method="get" style="display: flex; gap: 15px; align-items: end; flex-wrap: wrap;">
            <input type="hidden" name="page" value="dgptm-suite-logs">

            <!-- Quelle -->
            <div>
                <label><strong><?php _e('Quelle:', 'dgptm-suite'); ?></strong></label><br>
                <select name="source" style="min-width: 150px;">
                    <option value="db" <?php selected($source, 'db'); ?> <?php disabled(!$db_available); ?>>
                        📊 <?php _e('Datenbank', 'dgptm-suite'); ?>
                        <?php if (!$db_available) echo '(nicht verfügbar)'; ?>
                    </option>
                    <option value="file" <?php selected($source, 'file'); ?>>📄 <?php _e('debug.log', 'dgptm-suite'); ?></option>
                </select>
            </div>

            <!-- Level-Filter -->
            <div>
                <label><strong><?php _e('Level:', 'dgptm-suite'); ?></strong></label><br>
                <select name="level" style="min-width: 150px;">
                    <?php foreach ($level_labels as $value => $label): ?>
                        <option value="<?php echo esc_attr($value); ?>" <?php selected($filter_level, $value); ?>>
                            <?php if (!empty($value) && isset($level_icons[$value])) echo $level_icons[$value] . ' '; ?>
                            <?php echo esc_html($label); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Modul-Filter -->
            <div>
                <label><strong><?php _e('Modul:', 'dgptm-suite'); ?></strong></label><br>
                <select name="module" style="min-width: 180px;">
                    <option value=""><?php _e('Alle Module', 'dgptm-suite'); ?></option>
                    <?php foreach ($logged_modules as $mod): ?>
                        <option value="<?php echo esc_attr($mod); ?>" <?php selected($filter_module, $mod); ?>>
                            <?php echo esc_html($mod); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <!-- Suche -->
            <div>
                <label><strong><?php _e('Suche:', 'dgptm-suite'); ?></strong></label><br>
                <input type="text" name="search" value="<?php echo esc_attr($filter_search); ?>" placeholder="<?php _e('Suchbegriff...', 'dgptm-suite'); ?>" style="min-width: 200px;">
            </div>

            <!-- Anzahl -->
            <div>
                <label><strong><?php _e('Einträge:', 'dgptm-suite'); ?></strong></label><br>
                <select name="lines">
                    <option value="50" <?php selected($lines, 50); ?>>50</option>
                    <option value="100" <?php selected($lines, 100); ?>>100</option>
                    <option value="200" <?php selected($lines, 200); ?>>200</option>
                    <option value="500" <?php selected($lines, 500); ?>>500</option>
                </select>
            </div>

            <button type="submit" class="button button-primary">
                <span class="dashicons dashicons-search" style="margin-top: 4px;"></span>
                <?php _e('Filtern', 'dgptm-suite'); ?>
            </button>

            <a href="<?php echo admin_url('admin.php?page=dgptm-suite-logs'); ?>" class="button">
                <?php _e('Zurücksetzen', 'dgptm-suite'); ?>
            </a>
        </form>
    </div>

    <!-- Statistik-Zeile -->
    <div style="display: flex; justify-content: space-between; align-items: center; margin: 10px 0;">
        <div>
            <strong><?php _e('Gefunden:', 'dgptm-suite'); ?></strong>
            <?php echo number_format($total_entries, 0, ',', '.'); ?> <?php _e('Einträge', 'dgptm-suite'); ?>
            <?php if ($total_pages > 1): ?>
                | <?php _e('Seite', 'dgptm-suite'); ?> <?php echo $paged; ?> <?php _e('von', 'dgptm-suite'); ?> <?php echo $total_pages; ?>
            <?php endif; ?>
        </div>
        <div>
            <strong><?php _e('Quelle:', 'dgptm-suite'); ?></strong>
            <?php echo $source === 'db' ? __('Datenbank', 'dgptm-suite') : __('debug.log', 'dgptm-suite'); ?>
        </div>
    </div>

    <!-- Log-Anzeige -->
    <?php if ($source === 'db' && $db_available && !empty($log_entries)): ?>
        <!-- Datenbank-Logs -->
        <div class="dgptm-log-viewer" style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 12px; max-height: 700px; overflow-y: auto;">
            <?php foreach ($log_entries as $entry): ?>
                <div class="log-entry" style="padding: 8px 0; border-bottom: 1px solid #333;">
                    <span style="color: #808080;">[<?php echo esc_html($entry['timestamp']); ?>]</span>
                    <?php
                    $level_color = [
                        'critical' => '#f48771',
                        'error' => '#f48771',
                        'warning' => '#dcdcaa',
                        'info' => '#4ec9b0',
                        'verbose' => '#9cdcfe'
                    ];
                    $color = isset($level_color[$entry['level']]) ? $level_color[$entry['level']] : '#d4d4d4';
                    $bg = $entry['level'] === 'critical' ? 'background: #5a1d1d; padding: 2px 4px;' : '';
                    ?>
                    <span style="color: <?php echo $color; ?>; font-weight: bold; <?php echo $bg; ?>">
                        [<?php echo strtoupper($entry['level']); ?>]
                    </span>
                    <?php if (!empty($entry['module_id'])): ?>
                        <span style="color: #ce9178;">[<?php echo esc_html($entry['module_id']); ?>]</span>
                    <?php endif; ?>
                    <span style="color: #d4d4d4;"><?php echo esc_html($entry['message']); ?></span>
                    <?php if (!empty($entry['context'])): ?>
                        <details style="margin-top: 5px; margin-left: 20px;">
                            <summary style="cursor: pointer; color: #808080;"><?php _e('Context anzeigen', 'dgptm-suite'); ?></summary>
                            <pre style="color: #9cdcfe; background: #2d2d2d; padding: 10px; margin-top: 5px; border-radius: 3px; overflow-x: auto;"><?php echo esc_html(json_encode($entry['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                        </details>
                    <?php endif; ?>
                    <?php if (!empty($entry['user_id']) || !empty($entry['request_uri'])): ?>
                        <div style="margin-top: 3px; font-size: 10px; color: #666;">
                            <?php if (!empty($entry['user_id'])): ?>
                                User: <?php echo esc_html($entry['user_id']); ?>
                            <?php endif; ?>
                            <?php if (!empty($entry['request_uri'])): ?>
                                | URI: <?php echo esc_html($entry['request_uri']); ?>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>

    <?php elseif ($source === 'file' && !empty($log_lines)): ?>
        <!-- File-Logs (Legacy) -->
        <div class="dgptm-log-viewer" style="background: #1e1e1e; color: #d4d4d4; padding: 20px; border-radius: 4px; font-family: 'Courier New', monospace; font-size: 12px; max-height: 700px; overflow-y: auto;">
            <pre style="margin: 0; white-space: pre-wrap; word-wrap: break-word;"><?php
                $content = implode("\n", $log_lines);

                // Syntax-Highlighting
                $content = preg_replace('/(DGPTM Suite KRITISCH|KRITISCH[ES]*|CRITICAL)/i', '<span style="color: #f48771; font-weight: bold; background: #5a1d1d; padding: 2px 4px;">$0</span>', $content);
                $content = preg_replace('/(DGPTM Suite ERROR|PHP Fatal error|ERROR)/i', '<span style="color: #f48771; font-weight: bold;">$0</span>', $content);
                $content = preg_replace('/(DGPTM Suite WARNING|PHP Warning|Warning:)/i', '<span style="color: #dcdcaa; font-weight: bold;">$0</span>', $content);
                $content = preg_replace('/\[VERBOSE\]/i', '<span style="color: #9cdcfe; font-weight: bold;">$0</span>', $content);
                $content = preg_replace('/DGPTM Suite:/i', '<span style="color: #4ec9b0; font-weight: bold;">$0</span>', $content);
                $content = preg_replace('/\[([a-z0-9_-]+)\]/i', '<span style="color: #ce9178;">[$1]</span>', $content);
                $content = preg_replace('/\[([0-9]{2}-[A-Za-z]{3}-[0-9]{4} [0-9]{2}:[0-9]{2}:[0-9]{2}[^\]]*)\]/', '<span style="color: #808080;">[$1]</span>', $content);

                echo $content;
            ?></pre>
        </div>

    <?php else: ?>
        <div class="notice notice-info" style="margin: 20px 0;">
            <p><?php _e('Keine Log-Einträge gefunden.', 'dgptm-suite'); ?></p>
            <?php if ($source === 'db' && !$db_available): ?>
                <p><?php _e('Die Log-Datenbank ist nicht verfügbar. Bitte aktivieren Sie das Plugin erneut, um die Tabelle zu erstellen.', 'dgptm-suite'); ?></p>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <div class="tablenav bottom" style="margin-top: 20px;">
            <div class="tablenav-pages">
                <?php
                $base_url = add_query_arg([
                    'page' => 'dgptm-suite-logs',
                    'source' => $source,
                    'level' => $filter_level,
                    'module' => $filter_module,
                    'search' => $filter_search,
                    'lines' => $lines
                ], admin_url('admin.php'));

                echo paginate_links([
                    'base' => $base_url . '%_%',
                    'format' => '&paged=%#%',
                    'current' => $paged,
                    'total' => $total_pages,
                    'prev_text' => '&laquo;',
                    'next_text' => '&raquo;'
                ]);
                ?>
            </div>
        </div>
    <?php endif; ?>

    <?php endif; ?>

</div>

<style>
.dgptm-log-viewer::-webkit-scrollbar {
    width: 12px;
}
.dgptm-log-viewer::-webkit-scrollbar-track {
    background: #2d2d2d;
}
.dgptm-log-viewer::-webkit-scrollbar-thumb {
    background: #555;
    border-radius: 6px;
}
.dgptm-log-viewer::-webkit-scrollbar-thumb:hover {
    background: #777;
}
.dgptm-log-viewer .log-entry:last-child {
    border-bottom: none;
}
</style>
