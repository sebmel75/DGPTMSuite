<?php
/**
 * Dashboard View
 */

if (!defined('ABSPATH')) {
    exit;
}

$module_loader = dgptm_suite()->get_module_loader();
$dependency_manager = dgptm_suite()->get_dependency_manager();
$available_modules = $module_loader->get_available_modules();
$loaded_modules = $module_loader->get_loaded_modules();
$settings = get_option('dgptm_suite_settings', []);
$active_modules = $settings['active_modules'] ?? [];

// Test Version Manager
$test_manager = DGPTM_Test_Version_Manager::get_instance();

// Checkout Manager nur laden wenn die Klasse existiert
$active_checkouts = [];
if (class_exists('DGPTM_Checkout_Manager')) {
    $checkout_manager = DGPTM_Checkout_Manager::get_instance();
    $active_checkouts = $checkout_manager->get_active_checkouts();
}

// Kategorien aus Datenbank und Standard-Kategorien laden
$stored_categories = get_option('dgptm_suite_categories', []);

// Standard-Kategorien
$default_categories = [
    'core-infrastructure' => ['name' => __('Core Infrastructure', 'dgptm-suite'), 'description' => '', 'color' => '#e74c3c'],
    'business' => ['name' => __('Business Logic', 'dgptm-suite'), 'description' => '', 'color' => '#3498db'],
    'payment' => ['name' => __('Payment', 'dgptm-suite'), 'description' => '', 'color' => '#2ecc71'],
    'auth' => ['name' => __('Authentication', 'dgptm-suite'), 'description' => '', 'color' => '#f39c12'],
    'media' => ['name' => __('Media', 'dgptm-suite'), 'description' => '', 'color' => '#9b59b6'],
    'content' => ['name' => __('Content Management', 'dgptm-suite'), 'description' => '', 'color' => '#1abc9c'],
    'acf-tools' => ['name' => __('ACF Tools', 'dgptm-suite'), 'description' => '', 'color' => '#34495e'],
    'utilities' => ['name' => __('Utilities', 'dgptm-suite'), 'description' => '', 'color' => '#95a5a6'],
    'uncategorized' => ['name' => __('Uncategorized', 'dgptm-suite'), 'description' => '', 'color' => '#7f8c8d'],
];

// Merge mit gespeicherten Kategorien
$categories = array_merge($default_categories, $stored_categories);

// Alphabetisch nach Namen sortieren
uasort($categories, function($a, $b) {
    return strcmp($a['name'], $b['name']);
});

$modules_by_category = [];
foreach ($available_modules as $module_id => $module_info) {
    $category = $module_info['category'] ?? 'uncategorized';
    if (!isset($modules_by_category[$category])) {
        $modules_by_category[$category] = [];
    }
    $modules_by_category[$category][$module_id] = $module_info;
}

// Module innerhalb jeder Kategorie alphabetisch sortieren
foreach ($modules_by_category as $cat_id => $modules) {
    uasort($modules_by_category[$cat_id], function($a, $b) {
        $name_a = $a['config']['name'] ?? '';
        $name_b = $b['config']['name'] ?? '';
        return strcmp($name_a, $name_b);
    });
}

// Statistiken
$total_modules = count($available_modules);
$active_count = count(array_filter($active_modules));
$loaded_count = count($loaded_modules);
$checkout_count = count($active_checkouts);

// Aktuelles Debug-Level
$current_debug_level = isset($settings['logging']['global_level']) ? $settings['logging']['global_level'] : 'warning';

// Prüfen welche Module ausgecheckt sind
$checked_out_modules = [];
foreach ($active_checkouts as $checkout_info) {
    $checked_out_modules[] = $checkout_info['module_id'];
}
?>

<div class="wrap dgptm-suite-dashboard">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>

    <?php settings_errors('dgptm_suite_notices'); ?>

    <!-- Active Checkouts Warning -->
    <?php if (!empty($active_checkouts)): ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('Active Module Checkouts:', 'dgptm-suite'); ?></strong>
                <?php printf(_n('%d module is checked out for editing.', '%d modules are checked out for editing.', $checkout_count, 'dgptm-suite'), $checkout_count); ?>
                <?php foreach ($active_checkouts as $checkout_id => $checkout_info): ?>
                    <br>
                    • <strong><?php echo esc_html($checkout_info['module_id']); ?></strong>
                    (<?php echo esc_html(human_time_diff($checkout_info['checked_out_at'], time())); ?> ago)
                    <button type="button" class="button button-small dgptm-checkin-show" data-checkout-id="<?php echo esc_attr($checkout_id); ?>" data-module-id="<?php echo esc_attr($checkout_info['module_id']); ?>">
                        <?php _e('Upload Update', 'dgptm-suite'); ?>
                    </button>
                <?php endforeach; ?>
            </p>
        </div>
    <?php endif; ?>

    <!-- Statistiken -->
    <div class="dgptm-stats">
        <div class="dgptm-stat-box">
            <div class="dgptm-stat-number"><?php echo $total_modules; ?></div>
            <div class="dgptm-stat-label"><?php _e('Total Modules', 'dgptm-suite'); ?></div>
        </div>
        <div class="dgptm-stat-box">
            <div class="dgptm-stat-number"><?php echo $active_count; ?></div>
            <div class="dgptm-stat-label"><?php _e('Active Modules', 'dgptm-suite'); ?></div>
        </div>
        <div class="dgptm-stat-box">
            <div class="dgptm-stat-number"><?php echo $loaded_count; ?></div>
            <div class="dgptm-stat-label"><?php _e('Loaded Modules', 'dgptm-suite'); ?></div>
        </div>
        <div class="dgptm-stat-box">
            <div class="dgptm-stat-number"><?php echo count($categories); ?></div>
            <div class="dgptm-stat-label"><?php _e('Categories', 'dgptm-suite'); ?></div>
        </div>
        <div class="dgptm-stat-box dgptm-stat-debug-level">
            <?php
            $level_colors = [
                'verbose'  => '#9cdcfe',
                'info'     => '#4ec9b0',
                'warning'  => '#dcdcaa',
                'error'    => '#f48771',
                'critical' => '#d63638'
            ];
            $level_color = isset($level_colors[$current_debug_level]) ? $level_colors[$current_debug_level] : '#999';
            ?>
            <div class="dgptm-stat-number" style="font-size: 14px; line-height: 1.4;">
                <select id="dgptm-quick-debug-level" style="font-size: 14px; font-weight: bold; border-color: <?php echo esc_attr($level_color); ?>; background-color: <?php echo esc_attr($level_color); ?>20; padding: 4px 8px;">
                    <option value="verbose" <?php selected($current_debug_level, 'verbose'); ?>>Verbose</option>
                    <option value="info" <?php selected($current_debug_level, 'info'); ?>>Info</option>
                    <option value="warning" <?php selected($current_debug_level, 'warning'); ?>>Warning</option>
                    <option value="error" <?php selected($current_debug_level, 'error'); ?>>Error</option>
                    <option value="critical" <?php selected($current_debug_level, 'critical'); ?>>Critical</option>
                </select>
            </div>
            <div class="dgptm-stat-label">
                <?php _e('Debug-Level', 'dgptm-suite'); ?>
                <a href="<?php echo admin_url('admin.php?page=dgptm-suite-logs&tab=modules'); ?>" style="font-size: 11px; margin-left: 4px;"><?php _e('Pro Modul', 'dgptm-suite'); ?></a>
            </div>
        </div>
    </div>

    <!-- Wartungs-Tools -->
    <div class="dgptm-maintenance-tools" style="margin: 20px 0; padding: 15px; background: #f9f9f9; border-left: 4px solid #2271b1; border-radius: 4px;">
        <h3 style="margin-top: 0;"><?php _e('Wartungs-Tools', 'dgptm-suite'); ?></h3>
        <button type="button" id="dgptm-repair-flags-btn" class="button button-secondary">
            <span class="dashicons dashicons-admin-tools" style="margin-top: 3px;"></span>
            <?php _e('Module-Flags reparieren', 'dgptm-suite'); ?>
        </button>
        <p class="description">
            <?php _e('Repariert fehlerhafte Flag-Daten aus älteren Versionen. Führen Sie dies aus, wenn Module Fehler bei der Flag-Anzeige haben.', 'dgptm-suite'); ?>
        </p>
        <div id="dgptm-repair-result" style="margin-top: 10px; display: none;"></div>
    </div>

    <!-- Suchfeld -->
    <div class="dgptm-search-box">
        <input type="text" id="dgptm-module-search" placeholder="<?php esc_attr_e('Search modules...', 'dgptm-suite'); ?>" />
        <select id="dgptm-category-filter">
            <option value=""><?php _e('All Categories', 'dgptm-suite'); ?></option>
            <?php foreach ($categories as $cat_id => $cat_data): ?>
                <option value="<?php echo esc_attr($cat_id); ?>"><?php echo esc_html($cat_data['name'] ?? $cat_id); ?></option>
            <?php endforeach; ?>
        </select>
        <select id="dgptm-status-filter">
            <option value=""><?php _e('All Modules', 'dgptm-suite'); ?></option>
            <option value="active"><?php _e('Active Only', 'dgptm-suite'); ?></option>
            <option value="inactive"><?php _e('Inactive Only', 'dgptm-suite'); ?></option>
        </select>
    </div>

    <!-- Bulk Actions -->
    <form method="post" action="">
        <?php wp_nonce_field('dgptm_suite_action'); ?>

        <div class="tablenav top">
            <div class="alignleft actions bulkactions">
                <select name="dgptm_bulk_action">
                    <option value=""><?php _e('Bulk Actions', 'dgptm-suite'); ?></option>
                    <option value="activate"><?php _e('Activate', 'dgptm-suite'); ?></option>
                    <option value="deactivate"><?php _e('Deactivate', 'dgptm-suite'); ?></option>
                    <option value="export"><?php _e('Export', 'dgptm-suite'); ?></option>
                </select>
                <input type="submit" class="button action" value="<?php esc_attr_e('Apply', 'dgptm-suite'); ?>" />
            </div>
        </div>

        <!-- Module nach Kategorien -->
        <?php foreach ($categories as $cat_id => $cat_data): ?>
            <?php if (isset($modules_by_category[$cat_id]) && !empty($modules_by_category[$cat_id])): ?>
                <div class="dgptm-category-section" data-category="<?php echo esc_attr($cat_id); ?>">
                    <h2 class="dgptm-category-title" style="border-left: 4px solid <?php echo esc_attr($cat_data['color'] ?? '#3498db'); ?>; padding-left: 12px;">
                        <span class="dashicons dashicons-arrow-down-alt2"></span>
                        <?php echo esc_html($cat_data['name'] ?? $cat_id); ?>
                        <span class="dgptm-category-count">(<?php echo count($modules_by_category[$cat_id]); ?>)</span>
                        <?php if (!empty($cat_data['description'])): ?>
                            <small style="color: #666; font-weight: normal; margin-left: 10px;"><?php echo esc_html($cat_data['description']); ?></small>
                        <?php endif; ?>
                    </h2>

                    <table class="wp-list-table widefat fixed striped dgptm-modules-table">
                        <thead>
                            <tr>
                                <td class="check-column">
                                    <input type="checkbox" class="dgptm-select-all" />
                                </td>
                                <th><?php _e('Module', 'dgptm-suite'); ?></th>
                                <th><?php _e('Description', 'dgptm-suite'); ?></th>
                                <th><?php _e('Version', 'dgptm-suite'); ?></th>
                                <th><?php _e('Dependencies', 'dgptm-suite'); ?></th>
                                <th><?php _e('Status', 'dgptm-suite'); ?></th>
                                <th><?php _e('Actions', 'dgptm-suite'); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($modules_by_category[$cat_id] as $module_id => $module_info): ?>
                                <?php
                                $config = $module_info['config'];
                                $is_active = isset($active_modules[$module_id]) && $active_modules[$module_id];
                                $is_loaded = isset($loaded_modules[$module_id]);
                                $dep_check = $dependency_manager->check_module_dependencies($module_id);
                                $can_deactivate = $dependency_manager->can_deactivate_module($module_id);
                                $is_checked_out = in_array($module_id, $checked_out_modules);

                                // WICHTIG: Version aus Hauptdatei extrahieren statt aus module.json
                                $version = DGPTM_Version_Extractor::get_module_version($module_id);

                                // Metadata laden
                                $metadata_manager = DGPTM_Module_Metadata_File::get_instance();
                                $metadata = $metadata_manager->get_module_metadata($module_id);
                                $flags = $metadata['flags'] ?? [];
                                $comment = $metadata['comment'] ?? '';

                                // Sync critical flag from config
                                $metadata_manager->sync_critical_flag($module_id, $config);

                                // Prüfe ob kritisch (Flag oder Config)
                                $is_critical = $metadata_manager->is_module_critical($module_id, $config);

                                // Prüfe ob Modul fehlerhaft ist
                                $safe_loader = DGPTM_Safe_Loader::get_instance();
                                $failed_activations = $safe_loader->get_failed_activations();
                                $has_error = isset($failed_activations[$module_id]);
                                $error_info = $has_error ? $failed_activations[$module_id] : null;
                                $error_age = $has_error ? (time() - ($error_info['timestamp'] ?? 0)) : 0;
                                $error_is_recent = $has_error && $error_age < 3600; // Weniger als 1 Stunde alt

                                // Test-Version Informationen
                                $is_test_version = $test_manager->is_test_version($config);
                                $has_test_version = $test_manager->has_test_version($config);
                                $main_version_id = $test_manager->get_main_version_id($config);
                                $test_version_id = $test_manager->get_test_version_id($config);

                                // CSS-Klassen für Gruppierung
                                $row_classes = ['dgptm-module-row'];
                                if ($is_test_version) {
                                    $row_classes[] = 'dgptm-test-version';
                                    $row_classes[] = 'dgptm-linked-to-' . sanitize_html_class($main_version_id);
                                } elseif ($has_test_version) {
                                    $row_classes[] = 'dgptm-has-test';
                                    $row_classes[] = 'dgptm-main-of-' . sanitize_html_class($test_version_id);
                                }
                                ?>
                                <tr class="<?php echo implode(' ', $row_classes); ?>" data-module-id="<?php echo esc_attr($module_id); ?>" data-status="<?php echo $is_active ? 'active' : 'inactive'; ?>"<?php if ($is_test_version): ?> data-main-version="<?php echo esc_attr($main_version_id); ?>"<?php endif; ?><?php if ($has_test_version): ?> data-test-version="<?php echo esc_attr($test_version_id); ?>"<?php endif; ?>>
                                    <th class="check-column">
                                        <input type="checkbox" name="modules[]" value="<?php echo esc_attr($module_id); ?>" />
                                    </th>
                                    <td>
                                        <strong><?php echo esc_html($config['name']); ?></strong>

                                        <!-- Version Badge -->
                                        <?php if ($is_test_version): ?>
                                            <span class="dgptm-version-badge dgptm-version-badge-test" title="<?php esc_attr_e('Testversion', 'dgptm-suite'); ?>">TEST</span>
                                        <?php elseif ($has_test_version): ?>
                                            <span class="dgptm-version-badge dgptm-version-badge-main" title="<?php esc_attr_e('Hauptversion (hat Testversion)', 'dgptm-suite'); ?>">HAUPT</span>
                                        <?php endif; ?>

                                        <!-- Flags anzeigen -->
                                        <?php if (!empty($flags)): ?>
                                            <div class="dgptm-module-flags" style="margin: 5px 0;">
                                                <?php foreach ($flags as $flag): ?>
                                                    <?php
                                                    $flag_class = $metadata_manager->get_flag_badge_class($flag['type']);
                                                    ?>
                                                    <span class="dgptm-flag-mini <?php echo esc_attr($flag_class); ?>" title="<?php echo esc_attr($flag['label']); ?>">
                                                        <?php echo esc_html($flag['label']); ?>
                                                    </span>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Kommentar aufklappbar anzeigen -->
                                        <?php if (!empty($comment)): ?>
                                            <details class="dgptm-module-comment-details" style="margin: 5px 0;">
                                                <summary style="cursor: pointer; color: #2271b1;">
                                                    <span class="dashicons dashicons-admin-comments" style="font-size: 14px;"></span>
                                                    <?php _e('Kommentar', 'dgptm-suite'); ?>
                                                </summary>
                                                <div style="padding: 5px 0 0 20px; color: #646970; font-size: 13px;">
                                                    <?php echo nl2br(esc_html($comment)); ?>
                                                </div>
                                            </details>
                                        <?php endif; ?>

                                        <div class="row-actions">
                                            <span class="dgptm-module-id"><?php echo esc_html($module_id); ?></span>
                                            <?php if (!empty($config['category'])): ?>
                                                | <span class="dgptm-module-category-display">
                                                    <?php _e('Kategorie:', 'dgptm-suite'); ?>
                                                    <code><?php echo esc_html($config['category']); ?></code>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td><?php echo esc_html($config['description'] ?? ''); ?></td>
                                    <td><?php echo esc_html($version); ?></td>
                                    <td>
                                        <?php if (!empty($config['dependencies'])): ?>
                                            <details>
                                                <summary><?php echo count($config['dependencies']); ?> <?php _e('dependencies', 'dgptm-suite'); ?></summary>
                                                <ul>
                                                    <?php foreach ($config['dependencies'] as $dep): ?>
                                                        <li><?php echo esc_html($dep); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </details>
                                        <?php else: ?>
                                            <span class="dgptm-no-deps"><?php _e('None', 'dgptm-suite'); ?></span>
                                        <?php endif; ?>

                                        <?php if (!empty($config['wp_dependencies']['plugins'])): ?>
                                            <details>
                                                <summary><?php echo count($config['wp_dependencies']['plugins']); ?> WP <?php _e('plugins', 'dgptm-suite'); ?></summary>
                                                <ul>
                                                    <?php foreach ($config['wp_dependencies']['plugins'] as $plugin): ?>
                                                        <li><?php echo esc_html($plugin); ?></li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            </details>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_critical): ?>
                                            <span class="dgptm-status-badge dgptm-status-critical" style="background: #d63638; color: white; font-weight: bold;" title="<?php _e('Critical module - cannot be deactivated (Flag or Config)', 'dgptm-suite'); ?>">
                                                <span class="dashicons dashicons-shield" style="font-size: 12px; width: 12px; height: 12px;"></span>
                                                <?php _e('CRITICAL', 'dgptm-suite'); ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($is_active): ?>
                                            <span class="dgptm-status-badge dgptm-status-active"><?php _e('Active', 'dgptm-suite'); ?></span>
                                        <?php else: ?>
                                            <span class="dgptm-status-badge dgptm-status-inactive"><?php _e('Inactive', 'dgptm-suite'); ?></span>
                                        <?php endif; ?>

                                        <?php if ($is_loaded): ?>
                                            <span class="dgptm-status-badge dgptm-status-loaded"><?php _e('Loaded', 'dgptm-suite'); ?></span>
                                        <?php endif; ?>

                                        <?php if ($is_checked_out): ?>
                                            <span class="dgptm-status-badge dgptm-status-checkout" style="background: #d63638; color: white;">
                                                <span class="dashicons dashicons-lock" style="font-size: 12px; width: 12px; height: 12px;"></span>
                                                <?php _e('Checked Out', 'dgptm-suite'); ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if (!$dep_check['status']): ?>
                                            <span class="dgptm-status-badge dgptm-status-error" title="<?php echo esc_attr(implode(', ', $dep_check['messages'])); ?>">
                                                <?php _e('Missing Deps', 'dgptm-suite'); ?>
                                            </span>
                                        <?php endif; ?>

                                        <?php if ($has_error): ?>
                                            <?php
                                            $error_message = $error_info['error']['message'] ?? $error_info['error'] ?? 'Unbekannter Fehler';
                                            $error_time_ago = human_time_diff($error_info['timestamp'], time());
                                            $error_tooltip = sprintf(
                                                __('FEHLER vor %s: %s', 'dgptm-suite'),
                                                $error_time_ago,
                                                $error_message
                                            );
                                            ?>
                                            <span class="dgptm-status-badge dgptm-status-error" style="background: #d63638; color: white; font-weight: bold;" title="<?php echo esc_attr($error_tooltip); ?>">
                                                <span class="dashicons dashicons-warning" style="font-size: 12px; width: 12px; height: 12px;"></span>
                                                <?php echo $error_is_recent ? __('FEHLER (blockiert)', 'dgptm-suite') : __('FEHLER (alt)', 'dgptm-suite'); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button type="button"
                                                class="button button-small dgptm-module-info-btn"
                                                data-module-id="<?php echo esc_attr($module_id); ?>"
                                                title="<?php esc_attr_e('Module Information', 'dgptm-suite'); ?>">
                                            <span class="dashicons dashicons-info"></span>
                                        </button>
                                        <button type="button"
                                                class="button dgptm-toggle-module"
                                                data-module-id="<?php echo esc_attr($module_id); ?>"
                                                data-active="<?php echo $is_active ? '1' : '0'; ?>"
                                                <?php echo $is_critical ? 'disabled title="' . esc_attr__('Critical module cannot be deactivated', 'dgptm-suite') . '"' : ''; ?>
                                                <?php echo (!$is_active && !$dep_check['status']) ? 'disabled title="' . esc_attr(implode(', ', $dep_check['messages'])) . '"' : ''; ?>
                                                <?php echo ($is_active && !$is_critical && !$can_deactivate['can_deactivate']) ? 'disabled title="' . esc_attr($can_deactivate['message']) . '"' : ''; ?>
                                                <?php echo (!$is_active && $error_is_recent) ? 'disabled title="' . esc_attr__('Module hat kürzlich einen Fehler verursacht. Bitte Fehler beheben oder 1 Stunde warten.', 'dgptm-suite') . '"' : ''; ?>>
                                            <?php echo $is_active ? __('Deactivate', 'dgptm-suite') : __('Activate', 'dgptm-suite'); ?>
                                        </button>

                                        <?php if ($is_checked_out): ?>
                                            <button type="button" class="button button-primary dgptm-checkin-show" data-checkout-id="<?php echo esc_attr(array_search($module_id, $checked_out_modules)); ?>" data-module-id="<?php echo esc_attr($module_id); ?>">
                                                <span class="dashicons dashicons-upload"></span>
                                                <?php _e('Upload Update', 'dgptm-suite'); ?>
                                            </button>
                                            <button type="button" class="button dgptm-cancel-checkout" data-checkout-id="<?php echo esc_attr(array_search($module_id, $checked_out_modules)); ?>" data-module-id="<?php echo esc_attr($module_id); ?>">
                                                <span class="dashicons dashicons-no"></span>
                                                <?php _e('Cancel Checkout', 'dgptm-suite'); ?>
                                            </button>
                                        <?php else: ?>
                                            <button type="button" class="button dgptm-checkout-module" data-module-id="<?php echo esc_attr($module_id); ?>">
                                                <span class="dashicons dashicons-download"></span>
                                                <?php _e('Checkout', 'dgptm-suite'); ?>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($is_active): ?>
                                            <button type="button" class="button button-secondary dgptm-reinit-module" data-module-id="<?php echo esc_attr($module_id); ?>" title="<?php esc_attr_e('Modul neu initialisieren (Aktivierungs-Hooks und Permalinks)', 'dgptm-suite'); ?>">
                                                <span class="dashicons dashicons-update"></span>
                                                <?php _e('Reinit', 'dgptm-suite'); ?>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($has_error): ?>
                                            <button type="button" class="button button-secondary dgptm-clear-error" data-module-id="<?php echo esc_attr($module_id); ?>" title="<?php esc_attr_e('Fehler-Eintrag löschen und erneuten Aktivierungsversuch erlauben', 'dgptm-suite'); ?>">
                                                <span class="dashicons dashicons-dismiss"></span>
                                                <?php _e('Fehler löschen', 'dgptm-suite'); ?>
                                            </button>
                                        <?php endif; ?>

                                        <button type="button" class="button dgptm-module-info" data-module-id="<?php echo esc_attr($module_id); ?>">
                                            <span class="dashicons dashicons-info"></span>
                                        </button>

                                        <button type="button" class="button dgptm-delete-module" data-module-id="<?php echo esc_attr($module_id); ?>" data-category="<?php echo esc_attr($cat_id); ?>" <?php echo $is_active ? 'disabled' : ''; ?> title="<?php esc_attr_e('Delete Module', 'dgptm-suite'); ?>">
                                            <span class="dashicons dashicons-trash"></span>
                                        </button>

                                        <!-- Test-Version Management Buttons -->
                                        <?php if (!$is_test_version && !$has_test_version): ?>
                                            <!-- Hauptmodul ohne Testversion: "Testversion erstellen" -->
                                            <button type="button" class="button button-secondary dgptm-create-test" data-module-id="<?php echo esc_attr($module_id); ?>" title="<?php esc_attr_e('Testversion erstellen', 'dgptm-suite'); ?>">
                                                <span class="dashicons dashicons-admin-page"></span>
                                                <?php _e('Test erstellen', 'dgptm-suite'); ?>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($is_test_version): ?>
                                            <!-- Testversion: "Merge" und "Löschen" -->
                                            <button type="button" class="button button-primary dgptm-merge-test" data-test-id="<?php echo esc_attr($module_id); ?>" data-main-id="<?php echo esc_attr($main_version_id); ?>" title="<?php esc_attr_e('In Hauptversion mergen', 'dgptm-suite'); ?>">
                                                <span class="dashicons dashicons-upload"></span>
                                                <?php _e('Merge', 'dgptm-suite'); ?>
                                            </button>
                                            <button type="button" class="button dgptm-delete-test" data-test-id="<?php echo esc_attr($module_id); ?>" data-main-id="<?php echo esc_attr($main_version_id); ?>" title="<?php esc_attr_e('Testversion löschen', 'dgptm-suite'); ?>">
                                                <span class="dashicons dashicons-no-alt"></span>
                                                <?php _e('Test löschen', 'dgptm-suite'); ?>
                                            </button>
                                        <?php endif; ?>

                                        <?php if ($has_test_version): ?>
                                            <!-- Hauptversion mit Testversion: Link zur Testversion anzeigen -->
                                            <span class="dgptm-test-link" style="color: #ffa726; font-size: 12px; display: inline-block; margin-left: 5px;">
                                                <span class="dashicons dashicons-arrow-down-alt" style="font-size: 14px;"></span>
                                                <?php printf(__('Test: %s', 'dgptm-suite'), esc_html($test_version_id)); ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </form>
</div>

<!-- Modal für Modul-Informationen -->
<div id="dgptm-module-info-modal" class="dgptm-modal" style="display: none;">
    <div class="dgptm-modal-content">
        <span class="dgptm-modal-close">&times;</span>
        <div id="dgptm-module-info-content"></div>
    </div>
</div>

<!-- Checkin Modal -->
<div id="dgptm-checkin-modal" style="display: none;">
    <div class="dgptm-modal-overlay"></div>
    <div class="dgptm-modal-content-large">
        <h2><?php _e('Upload Updated Module', 'dgptm-suite'); ?></h2>
        <p class="description">
            <?php _e('Upload the updated ZIP file for this module. The system will automatically test it before installation and rollback if any errors occur.', 'dgptm-suite'); ?>
        </p>

        <form id="dgptm-checkin-form" enctype="multipart/form-data">
            <input type="hidden" id="dgptm-checkin-checkout-id" name="checkout_id" value="">

            <table class="form-table">
                <tr>
                    <th><?php _e('Module ID', 'dgptm-suite'); ?></th>
                    <td><code id="dgptm-checkin-module-id"></code></td>
                </tr>
                <tr>
                    <th><?php _e('Checkout ID', 'dgptm-suite'); ?></th>
                    <td><code id="dgptm-checkin-checkout-id-display"></code></td>
                </tr>
                <tr>
                    <th><label for="dgptm-module-zip-file"><?php _e('Updated Module ZIP', 'dgptm-suite'); ?></label></th>
                    <td>
                        <input type="file" id="dgptm-module-zip-file" name="module_zip" accept=".zip" required>
                        <p class="description"><?php _e('Select the updated module ZIP file', 'dgptm-suite'); ?></p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">
                    <?php _e('Upload and Test', 'dgptm-suite'); ?>
                </button>
                <button type="button" class="button dgptm-modal-close-checkin">
                    <?php _e('Cancel', 'dgptm-suite'); ?>
                </button>
            </p>
        </form>

        <div id="dgptm-checkin-progress" style="display: none;">
            <p><strong><?php _e('Processing update...', 'dgptm-suite'); ?></strong></p>
            <div class="dgptm-progress-bar">
                <div class="dgptm-progress-fill"></div>
            </div>
            <p class="dgptm-progress-status"></p>
        </div>

        <div id="dgptm-checkin-result" style="display: none;">
            <div class="dgptm-result-message"></div>
            <button class="button dgptm-modal-close-checkin"><?php _e('Close', 'dgptm-suite'); ?></button>
        </div>
    </div>
</div>

<style>
.dgptm-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    z-index: 100000;
}

.dgptm-modal-content-large {
    position: fixed;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 30px;
    border-radius: 4px;
    box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
    z-index: 100001;
    max-width: 600px;
    width: 90%;
    max-height: 90vh;
    overflow-y: auto;
}

.dgptm-progress-bar {
    width: 100%;
    height: 30px;
    background: #f0f0f1;
    border-radius: 3px;
    overflow: hidden;
    margin: 15px 0;
}

.dgptm-progress-fill {
    height: 100%;
    background: #2271b1;
    width: 0%;
    transition: width 0.3s ease;
}

.dgptm-progress-status {
    color: #646970;
    font-style: italic;
}

.dgptm-result-message {
    padding: 15px;
    margin: 15px 0;
    border-radius: 3px;
}

.dgptm-result-message.success {
    background: #d5f4e6;
    border-left: 4px solid #00a32a;
    color: #00a32a;
}

.dgptm-result-message.error {
    background: #f0e5e5;
    border-left: 4px solid #d63638;
    color: #d63638;
}
</style>

<script>
jQuery(document).ready(function($) {
    // Checkout Module
    $('.dgptm-checkout-module').on('click', function() {
        const moduleId = $(this).data('module-id');
        const $button = $(this);

        if (!confirm('<?php _e('Export this module for editing? A backup will be created automatically.', 'dgptm-suite'); ?>')) {
            return;
        }

        $button.prop('disabled', true).text('<?php _e('Exporting...', 'dgptm-suite'); ?>');

        $.ajax({
            url: dgptmSuite.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_checkout_module',
                nonce: dgptmSuite.nonce,
                module_id: moduleId
            },
            success: function(response) {
                console.log('DGPTM Checkout Response:', response);

                if (response.success) {
                    alert('<?php _e('Module checked out successfully! Download will start now.', 'dgptm-suite'); ?>');

                    // Trigger download
                    if (response.data.download_url) {
                        window.location.href = response.data.download_url;
                    }

                    // Reload page to show checkout status
                    setTimeout(function() {
                        location.reload();
                    }, 1000);
                } else {
                    console.error('DGPTM Checkout Error:', response);
                    alert('<?php _e('Error:', 'dgptm-suite'); ?> ' + (response.data && response.data.message ? response.data.message : '<?php _e('Unknown error', 'dgptm-suite'); ?>'));
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> <?php _e('Checkout', 'dgptm-suite'); ?>');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('DGPTM Checkout AJAX Error:', {
                    status: jqXHR.status,
                    statusText: jqXHR.statusText,
                    textStatus: textStatus,
                    errorThrown: errorThrown,
                    responseText: jqXHR.responseText
                });

                let errorMsg = '<?php _e('AJAX error occurred', 'dgptm-suite'); ?>\n\n';

                if (jqXHR.status === 0) {
                    errorMsg += '<?php _e('Network error: No response from server.', 'dgptm-suite'); ?>';
                } else if (jqXHR.status === 500) {
                    errorMsg += '<?php _e('Server error (500). Check server error logs.', 'dgptm-suite'); ?>';
                } else if (jqXHR.status === 403) {
                    errorMsg += '<?php _e('Permission denied (403).', 'dgptm-suite'); ?>';
                } else {
                    errorMsg += 'HTTP ' + jqXHR.status + ': ' + errorThrown;
                }

                // Versuche, detaillierte Fehlermeldung zu extrahieren
                if (jqXHR.responseText) {
                    try {
                        const response = JSON.parse(jqXHR.responseText);
                        if (response && response.data && response.data.message) {
                            errorMsg += '\n\n' + response.data.message;
                        }
                    } catch(e) {
                        // Ignoriere Parse-Fehler
                    }
                }

                alert(errorMsg);
                $button.prop('disabled', false).html('<span class="dashicons dashicons-download"></span> <?php _e('Checkout', 'dgptm-suite'); ?>');
            }
        });
    });

    // Show Checkin Modal
    $('.dgptm-checkin-show').on('click', function() {
        const checkoutId = $(this).data('checkout-id');
        const moduleId = $(this).data('module-id');

        // Find the actual checkout ID from active checkouts
        <?php foreach ($active_checkouts as $checkout_id => $checkout_info): ?>
            if ('<?php echo esc_js($checkout_info['module_id']); ?>' === moduleId) {
                $('#dgptm-checkin-checkout-id').val('<?php echo esc_js($checkout_id); ?>');
                $('#dgptm-checkin-checkout-id-display').text('<?php echo esc_js($checkout_id); ?>');
            }
        <?php endforeach; ?>

        $('#dgptm-checkin-module-id').text(moduleId);

        $('#dgptm-checkin-form').show();
        $('#dgptm-checkin-progress').hide();
        $('#dgptm-checkin-result').hide();

        $('#dgptm-checkin-modal').show();
    });

    // Close Modal
    $('.dgptm-modal-close-checkin').on('click', function() {
        $('#dgptm-checkin-modal').hide();
    });

    // Handle Checkin Form Submit
    $('#dgptm-checkin-form').on('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);
        formData.append('action', 'dgptm_checkin_module');
        formData.append('nonce', dgptmSuite.nonce);

        $('#dgptm-checkin-form').hide();
        $('#dgptm-checkin-progress').show();
        $('.dgptm-progress-fill').css('width', '10%');
        $('.dgptm-progress-status').text('<?php _e('Uploading module...', 'dgptm-suite'); ?>');

        $.ajax({
            url: dgptmSuite.ajaxUrl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            xhr: function() {
                const xhr = new window.XMLHttpRequest();
                xhr.upload.addEventListener('progress', function(e) {
                    if (e.lengthComputable) {
                        const percent = (e.loaded / e.total) * 50;
                        $('.dgptm-progress-fill').css('width', percent + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
                console.log('DGPTM Checkin Response:', response);

                $('.dgptm-progress-fill').css('width', '60%');
                $('.dgptm-progress-status').text('<?php _e('Testing module...', 'dgptm-suite'); ?>');

                setTimeout(function() {
                    $('.dgptm-progress-fill').css('width', '100%');
                    $('#dgptm-checkin-progress').hide();
                    $('#dgptm-checkin-result').show();

                    if (response.success) {
                        $('.dgptm-result-message')
                            .addClass('success')
                            .html('<strong><?php _e('Success!', 'dgptm-suite'); ?></strong><br>' +
                                  '<?php _e('Module updated successfully:', 'dgptm-suite'); ?><br>' +
                                  '<?php _e('Old Version:', 'dgptm-suite'); ?> ' + response.data.old_version + '<br>' +
                                  '<?php _e('New Version:', 'dgptm-suite'); ?> ' + response.data.new_version);

                        setTimeout(function() {
                            location.reload();
                        }, 2000);
                    } else {
                        console.error('DGPTM Checkin Error:', response);
                        $('.dgptm-result-message')
                            .addClass('error')
                            .html('<strong><?php _e('Error!', 'dgptm-suite'); ?></strong><br>' +
                                  (response.data && response.data.message ? response.data.message : '<?php _e('Unknown error', 'dgptm-suite'); ?>') + '<br>' +
                                  '<small><?php _e('The original module has been preserved.', 'dgptm-suite'); ?></small>');
                    }
                }, 1000);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error('DGPTM Checkin AJAX Error:', {
                    status: jqXHR.status,
                    statusText: jqXHR.statusText,
                    textStatus: textStatus,
                    errorThrown: errorThrown,
                    responseText: jqXHR.responseText
                });

                $('#dgptm-checkin-progress').hide();
                $('#dgptm-checkin-result').show();

                let errorMsg = '<?php _e('AJAX error occurred', 'dgptm-suite'); ?>';

                // Detaillierte Fehlermeldung
                if (jqXHR.status === 0) {
                    errorMsg += '<br><small><?php _e('Network error: No response from server. Check if the file is too large or the server connection timed out.', 'dgptm-suite'); ?></small>';
                } else if (jqXHR.status === 413) {
                    errorMsg += '<br><small><?php _e('File too large: The uploaded file exceeds the server limit. Check PHP upload_max_filesize and post_max_size settings.', 'dgptm-suite'); ?></small>';
                } else if (jqXHR.status === 500) {
                    errorMsg += '<br><small><?php _e('Server error: Check server error logs for details.', 'dgptm-suite'); ?></small>';
                } else if (jqXHR.status === 403) {
                    errorMsg += '<br><small><?php _e('Permission denied: Check file permissions and nonce validation.', 'dgptm-suite'); ?></small>';
                } else {
                    errorMsg += '<br><small>HTTP ' + jqXHR.status + ': ' + errorThrown + '</small>';
                }

                // Versuche, Response-Text zu parsen
                if (jqXHR.responseText) {
                    try {
                        const response = JSON.parse(jqXHR.responseText);
                        if (response && response.data && response.data.message) {
                            errorMsg += '<br><br><strong><?php _e('Details:', 'dgptm-suite'); ?></strong><br>' + response.data.message;
                        }
                    } catch(e) {
                        // Zeige ersten Teil der Antwort
                        if (jqXHR.responseText.length > 0) {
                            const preview = jqXHR.responseText.substring(0, 200);
                            errorMsg += '<br><br><details><summary><?php _e('Server Response Preview', 'dgptm-suite'); ?></summary><pre style="max-height:200px;overflow:auto;font-size:11px;">' + preview + (jqXHR.responseText.length > 200 ? '...' : '') + '</pre></details>';
                        }
                    }
                }

                $('.dgptm-result-message')
                    .addClass('error')
                    .html(errorMsg);
            }
        });
    });

    // Cancel Checkout - Checkout-ID Mapping erstellen
    const checkoutMapping = {
        <?php foreach ($active_checkouts as $checkout_id => $checkout_info): ?>
        '<?php echo esc_js($checkout_info['module_id']); ?>': '<?php echo esc_js($checkout_id); ?>',
        <?php endforeach; ?>
    };

    // Cancel Checkout
    $('.dgptm-cancel-checkout').on('click', function() {
        const moduleId = $(this).data('module-id');
        const $button = $(this);

        // Finde echte Checkout-ID aus Mapping
        const actualCheckoutId = checkoutMapping[moduleId];

        if (!actualCheckoutId) {
            alert('<?php _e('Checkout ID not found', 'dgptm-suite'); ?>');
            return;
        }

        if (!confirm('<?php _e('Möchten Sie diesen Checkout wirklich abbrechen? Das Modul wird nicht verändert.', 'dgptm-suite'); ?>')) {
            return;
        }

        $button.prop('disabled', true).text('<?php _e('Cancelling...', 'dgptm-suite'); ?>');

        $.ajax({
            url: dgptmSuite.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_cancel_checkout',
                nonce: dgptmSuite.nonce,
                checkout_id: actualCheckoutId
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php _e('Checkout erfolgreich abgebrochen!', 'dgptm-suite'); ?>');
                    location.reload();
                } else {
                    alert('<?php _e('Fehler:', 'dgptm-suite'); ?> ' + (response.data.message || 'Unknown error'));
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> <?php _e('Cancel Checkout', 'dgptm-suite'); ?>');
                }
            },
            error: function(xhr, status, error) {
                console.error('AJAX Error:', xhr.responseText);
                alert('<?php _e('AJAX-Fehler aufgetreten:', 'dgptm-suite'); ?> ' + error);
                $button.prop('disabled', false).html('<span class="dashicons dashicons-no"></span> <?php _e('Cancel Checkout', 'dgptm-suite'); ?>');
            }
        });
    });

    // Delete Module
    $('.dgptm-delete-module').on('click', function() {
        const moduleId = $(this).data('module-id');
        const category = $(this).data('category');
        const $button = $(this);
        const $row = $button.closest('tr');

        if (!confirm('<?php _e('Are you sure you want to delete this module? This action cannot be undone!', 'dgptm-suite'); ?>')) {
            return;
        }

        $button.prop('disabled', true).text('<?php _e('Deleting...', 'dgptm-suite'); ?>');

        $.ajax({
            url: dgptmSuite.ajaxUrl,
            type: 'POST',
            data: {
                action: 'dgptm_delete_module',
                nonce: dgptmSuite.nonce,
                module_id: moduleId,
                category: category
            },
            success: function(response) {
                if (response.success) {
                    alert('<?php _e('Module deleted successfully!', 'dgptm-suite'); ?>');
                    $row.fadeOut(300, function() {
                        $(this).remove();
                        location.reload();
                    });
                } else {
                    alert('<?php _e('Error:', 'dgptm-suite'); ?> ' + response.data.message);
                    $button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span>');
                }
            },
            error: function() {
                alert('<?php _e('AJAX error occurred', 'dgptm-suite'); ?>');
                $button.prop('disabled', false).html('<span class="dashicons dashicons-trash"></span>');
            }
        });
    });
});
</script>

<?php
// Include Module Metadata Modal
require_once DGPTM_SUITE_PATH . 'admin/views/module-metadata-modal.php';
?>
