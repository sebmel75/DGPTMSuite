<?php
/**
 * Module Settings View
 */

if (!defined('ABSPATH')) {
    exit;
}

$module_id = $_GET['module'] ?? '';
$module_loader = dgptm_suite()->get_module_loader();
$config = $module_loader->get_module_config($module_id);
?>

<div class="wrap">
    <h1><?php _e('Module Settings', 'dgptm-suite'); ?></h1>

    <?php if ($config): ?>
        <h2><?php echo esc_html($config['name']); ?></h2>
        <p><?php echo esc_html($config['description'] ?? ''); ?></p>

        <form method="post" action="options.php">
            <?php
            settings_fields('dgptm_suite_module_' . $module_id);
            do_settings_sections('dgptm_suite_module_' . $module_id);
            submit_button();
            ?>
        </form>
    <?php else: ?>
        <p><?php _e('Please select a module from the dashboard.', 'dgptm-suite'); ?></p>
    <?php endif; ?>
</div>
