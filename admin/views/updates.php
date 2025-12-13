<?php
/**
 * Updates View - Checkout/Checkin System
 */

if (!defined('ABSPATH')) {
    exit;
}

$module_loader = dgptm_suite()->get_module_loader();
$all_modules = $module_loader->get_all_modules();
$checkout_manager = DGPTM_Checkout_Manager::get_instance();
$active_checkouts = $checkout_manager->get_active_checkouts();
?>

<div class="wrap">
    <h1><?php _e('Module Updates (Checkout/Checkin)', 'dgptm-suite'); ?></h1>

    <p class="description">
        <?php _e('Export a module for editing (Checkout), then import the updated version (Checkin) with automatic validation and rollback protection.', 'dgptm-suite'); ?>
    </p>

    <!-- Active Checkouts Section -->
    <?php if (!empty($active_checkouts)): ?>
        <div class="dgptm-checkouts-section" style="margin-top: 20px; margin-bottom: 30px;">
            <h2><?php _e('Active Checkouts', 'dgptm-suite'); ?></h2>
            <div class="notice notice-warning">
                <p><strong><?php _e('The following modules are checked out for editing:', 'dgptm-suite'); ?></strong></p>
            </div>

            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('Module', 'dgptm-suite'); ?></th>
                        <th><?php _e('Version', 'dgptm-suite'); ?></th>
                        <th><?php _e('Checked Out', 'dgptm-suite'); ?></th>
                        <th><?php _e('Checkout ID', 'dgptm-suite'); ?></th>
                        <th><?php _e('Action', 'dgptm-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($active_checkouts as $checkout_id => $checkout_info): ?>
                        <tr>
                            <td>
                                <strong><?php echo esc_html($checkout_info['module_id']); ?></strong>
                            </td>
                            <td><?php echo esc_html($checkout_info['version']); ?></td>
                            <td><?php echo esc_html(human_time_diff($checkout_info['checked_out_at'], time()) . ' ago'); ?></td>
                            <td><code><?php echo esc_html($checkout_id); ?></code></td>
                            <td>
                                <button class="button button-primary dgptm-checkin-show" data-checkout-id="<?php echo esc_attr($checkout_id); ?>" data-module-id="<?php echo esc_attr($checkout_info['module_id']); ?>">
                                    <?php _e('Upload Updated Module', 'dgptm-suite'); ?>
                                </button>
                                <button class="button dgptm-cancel-checkout" data-checkout-id="<?php echo esc_attr($checkout_id); ?>">
                                    <?php _e('Cancel Checkout', 'dgptm-suite'); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <!-- All Modules Section -->
    <h2><?php _e('All Modules', 'dgptm-suite'); ?></h2>

    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th><?php _e('Module', 'dgptm-suite'); ?></th>
                <th><?php _e('Version', 'dgptm-suite'); ?></th>
                <th><?php _e('Category', 'dgptm-suite'); ?></th>
                <th><?php _e('Status', 'dgptm-suite'); ?></th>
                <th><?php _e('Action', 'dgptm-suite'); ?></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($all_modules as $module_id => $config):
                $is_checked_out = false;
                foreach ($active_checkouts as $checkout_info) {
                    if ($checkout_info['module_id'] === $module_id) {
                        $is_checked_out = true;
                        break;
                    }
                }
            ?>
                <tr>
                    <td>
                        <strong><?php echo esc_html($config['name']); ?></strong>
                        <br>
                        <small><code><?php echo esc_html($module_id); ?></code></small>
                    </td>
                    <td><?php echo esc_html($config['version'] ?? '1.0.0'); ?></td>
                    <td><?php echo esc_html($config['category'] ?? 'general'); ?></td>
                    <td>
                        <?php if ($is_checked_out): ?>
                            <span class="dashicons dashicons-lock" style="color: #d63638;"></span>
                            <span style="color: #d63638;"><?php _e('Checked Out', 'dgptm-suite'); ?></span>
                        <?php else: ?>
                            <span class="dashicons dashicons-unlock" style="color: #00a32a;"></span>
                            <span style="color: #00a32a;"><?php _e('Available', 'dgptm-suite'); ?></span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if (!$is_checked_out): ?>
                            <button class="button button-primary dgptm-checkout-module" data-module-id="<?php echo esc_attr($module_id); ?>">
                                <?php _e('Checkout for Update', 'dgptm-suite'); ?>
                            </button>
                        <?php else: ?>
                            <em><?php _e('Already checked out', 'dgptm-suite'); ?></em>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Checkin Modal -->
<div id="dgptm-checkin-modal" style="display: none;">
    <div class="dgptm-modal-overlay"></div>
    <div class="dgptm-modal-content">
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
                <button type="button" class="button dgptm-modal-close">
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
            <button class="button dgptm-modal-close"><?php _e('Close', 'dgptm-suite'); ?></button>
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

.dgptm-modal-content {
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
    animation: progress-animation 2s infinite;
}

@keyframes progress-animation {
    0% { background-position: 0 0; }
    100% { background-position: 40px 40px; }
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
                    alert('<?php _e('Error:', 'dgptm-suite'); ?> ' + response.data.message);
                    $button.prop('disabled', false).text('<?php _e('Checkout for Update', 'dgptm-suite'); ?>');
                }
            },
            error: function() {
                alert('<?php _e('AJAX error occurred', 'dgptm-suite'); ?>');
                $button.prop('disabled', false).text('<?php _e('Checkout for Update', 'dgptm-suite'); ?>');
            }
        });
    });

    // Show Checkin Modal
    $('.dgptm-checkin-show').on('click', function() {
        const checkoutId = $(this).data('checkout-id');
        const moduleId = $(this).data('module-id');

        $('#dgptm-checkin-checkout-id').val(checkoutId);
        $('#dgptm-checkin-checkout-id-display').text(checkoutId);
        $('#dgptm-checkin-module-id').text(moduleId);

        $('#dgptm-checkin-form').show();
        $('#dgptm-checkin-progress').hide();
        $('#dgptm-checkin-result').hide();

        $('#dgptm-checkin-modal').show();
    });

    // Close Modal
    $('.dgptm-modal-close').on('click', function() {
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
                        const percent = (e.loaded / e.total) * 50; // First 50% for upload
                        $('.dgptm-progress-fill').css('width', percent + '%');
                    }
                }, false);
                return xhr;
            },
            success: function(response) {
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
                        $('.dgptm-result-message')
                            .addClass('error')
                            .html('<strong><?php _e('Error!', 'dgptm-suite'); ?></strong><br>' +
                                  response.data.message + '<br>' +
                                  '<small><?php _e('The original module has been preserved.', 'dgptm-suite'); ?></small>');
                    }
                }, 1000);
            },
            error: function() {
                $('#dgptm-checkin-progress').hide();
                $('#dgptm-checkin-result').show();
                $('.dgptm-result-message')
                    .addClass('error')
                    .text('<?php _e('AJAX error occurred', 'dgptm-suite'); ?>');
            }
        });
    });
});
</script>
