<?php
/**
 * Export View
 */

if (!defined('ABSPATH')) {
    exit;
}

$export_dir = DGPTM_SUITE_PATH . 'exports/';
$exports = [];

if (is_dir($export_dir)) {
    $files = scandir($export_dir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || substr($file, -4) !== '.zip') {
            continue;
        }
        $exports[] = [
            'file' => $file,
            'size' => filesize($export_dir . $file),
            'date' => filemtime($export_dir . $file),
            'url' => DGPTM_SUITE_URL . 'exports/' . $file,
        ];
    }
}
?>

<div class="wrap">
    <h1><?php _e('Exported Modules', 'dgptm-suite'); ?></h1>

    <p><?php _e('Download exported module ZIP files for installation on other WordPress sites.', 'dgptm-suite'); ?></p>

    <?php if (empty($exports)): ?>
        <p><?php _e('No exports available yet. Export modules from the dashboard.', 'dgptm-suite'); ?></p>
    <?php else: ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th><?php _e('File', 'dgptm-suite'); ?></th>
                    <th><?php _e('Size', 'dgptm-suite'); ?></th>
                    <th><?php _e('Date', 'dgptm-suite'); ?></th>
                    <th><?php _e('Action', 'dgptm-suite'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($exports as $export): ?>
                    <tr>
                        <td><strong><?php echo esc_html($export['file']); ?></strong></td>
                        <td><?php echo size_format($export['size']); ?></td>
                        <td><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $export['date']); ?></td>
                        <td>
                            <a href="<?php echo esc_url($export['url']); ?>" class="button" download>
                                <span class="dashicons dashicons-download"></span>
                                <?php _e('Download', 'dgptm-suite'); ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
