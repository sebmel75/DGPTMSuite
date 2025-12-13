<?php
/**
 * New Module Upload & Management View
 * - Upload existing plugins
 * - Auto-detect plugin metadata
 * - Manual categorization only
 */

if (!defined('ABSPATH')) {
    exit;
}

$module_loader = dgptm_suite()->get_module_loader();
$available_modules = $module_loader->get_available_modules();
?>

<div class="wrap dgptm-new-module">
    <h1><?php _e('Add New Module', 'dgptm-suite'); ?></h1>

    <div class="dgptm-upload-modes">
        <button type="button" class="button button-large mode-btn active" data-mode="upload">
            <span class="dashicons dashicons-upload"></span>
            <?php _e('Upload Plugin', 'dgptm-suite'); ?>
        </button>
        <button type="button" class="button button-large mode-btn" data-mode="manual">
            <span class="dashicons dashicons-edit"></span>
            <?php _e('Create Manually', 'dgptm-suite'); ?>
        </button>
    </div>

    <!-- Upload Mode -->
    <div id="upload-mode" class="dgptm-mode-panel">
        <div class="dgptm-form-section">
            <h2><?php _e('Upload Plugin ZIP', 'dgptm-suite'); ?></h2>
            <p class="description">
                <?php _e('Upload a WordPress plugin (ZIP file). The system will automatically extract plugin information (name, version, author, description) from the plugin headers.', 'dgptm-suite'); ?>
            </p>

            <form id="dgptm-upload-module-form" enctype="multipart/form-data">
                <?php wp_nonce_field('dgptm_suite_nonce', 'dgptm_nonce'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="plugin_zip"><?php _e('Plugin ZIP File', 'dgptm-suite'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="file" name="plugin_zip" id="plugin_zip" accept=".zip" required />
                            <p class="description"><?php _e('Select a .zip file containing your WordPress plugin', 'dgptm-suite'); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary button-hero" id="upload-analyze-btn">
                        <span class="dashicons dashicons-search"></span>
                        <?php _e('Analyze Plugin', 'dgptm-suite'); ?>
                    </button>
                </p>
            </form>

            <!-- Analysis Result -->
            <div id="plugin-analysis-result" style="display: none;">
                <h3><?php _e('Detected Plugin Information', 'dgptm-suite'); ?></h3>
                <form id="dgptm-finalize-module-form">
                    <?php wp_nonce_field('dgptm_suite_nonce', 'dgptm_nonce_final'); ?>
                    <input type="hidden" name="temp_path" id="temp_path" />
                    <input type="hidden" name="detected_main_file" id="detected_main_file" />

                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Plugin Name', 'dgptm-suite'); ?></th>
                            <td>
                                <strong id="detected_name"></strong>
                                <input type="hidden" name="module_name" id="final_name" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Version', 'dgptm-suite'); ?></th>
                            <td>
                                <strong id="detected_version"></strong>
                                <input type="hidden" name="module_version" id="final_version" />
                                <p class="description"><?php _e('Version is read automatically from plugin file', 'dgptm-suite'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Author', 'dgptm-suite'); ?></th>
                            <td>
                                <strong id="detected_author"></strong>
                                <input type="hidden" name="module_author" id="final_author" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Description', 'dgptm-suite'); ?></th>
                            <td>
                                <p id="detected_description"></p>
                                <input type="hidden" name="module_description" id="final_description" />
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Main File', 'dgptm-suite'); ?></th>
                            <td>
                                <code id="detected_file"></code>
                            </td>
                        </tr>
                        <tr class="required-field">
                            <th scope="row">
                                <label for="module_id_auto"><?php _e('Module ID', 'dgptm-suite'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <input type="text" name="module_id" id="module_id_auto" class="regular-text" required pattern="[a-z0-9-]+" />
                                <p class="description"><?php _e('Unique identifier (lowercase, numbers, hyphens only). Suggested based on plugin name.', 'dgptm-suite'); ?></p>
                            </td>
                        </tr>
                        <tr class="required-field">
                            <th scope="row">
                                <label for="module_category_auto"><?php _e('Category', 'dgptm-suite'); ?> <span class="required">*</span></label>
                            </th>
                            <td>
                                <select name="module_category" id="module_category_auto" required>
                                    <option value=""><?php _e('-- Select Category --', 'dgptm-suite'); ?></option>
                                    <option value="utilities"><?php _e('Utilities', 'dgptm-suite'); ?></option>
                                    <option value="business"><?php _e('Business Logic', 'dgptm-suite'); ?></option>
                                    <option value="content"><?php _e('Content Management', 'dgptm-suite'); ?></option>
                                    <option value="media"><?php _e('Media & Content', 'dgptm-suite'); ?></option>
                                    <option value="acf-tools"><?php _e('ACF Tools', 'dgptm-suite'); ?></option>
                                    <option value="payment"><?php _e('Payment', 'dgptm-suite'); ?></option>
                                    <option value="auth"><?php _e('Authentication', 'dgptm-suite'); ?></option>
                                    <option value="core-infrastructure"><?php _e('Core Infrastructure', 'dgptm-suite'); ?></option>
                                </select>
                                <p class="description"><?php _e('Select the category for this module', 'dgptm-suite'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="icon_auto"><?php _e('Icon', 'dgptm-suite'); ?></label>
                            </th>
                            <td>
                                <input type="text" name="icon" id="icon_auto" value="dashicons-admin-plugins" class="regular-text" />
                                <p class="description">
                                    <?php _e('Dashicons class', 'dgptm-suite'); ?>
                                    <a href="https://developer.wordpress.org/resource/dashicons/" target="_blank"><?php _e('View Dashicons', 'dgptm-suite'); ?></a>
                                </p>
                            </td>
                        </tr>
                    </table>

                    <p class="submit">
                        <button type="submit" class="button button-primary button-hero" id="finalize-module-btn">
                            <span class="dashicons dashicons-yes"></span>
                            <?php _e('Add Module to Suite', 'dgptm-suite'); ?>
                        </button>
                        <button type="button" class="button button-secondary button-large" id="cancel-upload-btn">
                            <span class="dashicons dashicons-no"></span>
                            <?php _e('Cancel', 'dgptm-suite'); ?>
                        </button>
                    </p>
                </form>
            </div>
        </div>
    </div>

    <!-- Manual Mode (Original) -->
    <div id="manual-mode" class="dgptm-mode-panel" style="display: none;">
        <div class="dgptm-form-section">
            <h2><?php _e('Create Module Manually', 'dgptm-suite'); ?></h2>
            <p class="description"><?php _e('Create a new module from scratch with the generator.', 'dgptm-suite'); ?></p>

            <form id="dgptm-create-module-form" class="dgptm-module-form">
                <?php wp_nonce_field('dgptm_suite_nonce', 'dgptm_nonce_manual'); ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="module_id"><?php _e('Module ID', 'dgptm-suite'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="module_id" id="module_id" class="regular-text" required pattern="[a-z0-9-]+" />
                            <p class="description"><?php _e('Unique identifier (lowercase, numbers, hyphens only)', 'dgptm-suite'); ?></p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="module_name"><?php _e('Module Name', 'dgptm-suite'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="module_name" id="module_name" class="regular-text" required />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="module_description"><?php _e('Description', 'dgptm-suite'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <textarea name="module_description" id="module_description" rows="3" class="large-text" required></textarea>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="module_version"><?php _e('Version', 'dgptm-suite'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="module_version" id="module_version" value="1.0.0" class="small-text" required />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="module_author"><?php _e('Author', 'dgptm-suite'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="module_author" id="module_author" value="<?php echo esc_attr(get_bloginfo('name')); ?>" class="regular-text" />
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="module_category"><?php _e('Category', 'dgptm-suite'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <select name="module_category" id="module_category" required>
                                <option value="utilities"><?php _e('Utilities', 'dgptm-suite'); ?></option>
                                <option value="business"><?php _e('Business Logic', 'dgptm-suite'); ?></option>
                                <option value="content"><?php _e('Content Management', 'dgptm-suite'); ?></option>
                                <option value="media"><?php _e('Media & Content', 'dgptm-suite'); ?></option>
                                <option value="acf-tools"><?php _e('ACF Tools', 'dgptm-suite'); ?></option>
                                <option value="payment"><?php _e('Payment', 'dgptm-suite'); ?></option>
                                <option value="auth"><?php _e('Authentication', 'dgptm-suite'); ?></option>
                                <option value="core-infrastructure"><?php _e('Core Infrastructure', 'dgptm-suite'); ?></option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="main_file"><?php _e('Main File', 'dgptm-suite'); ?> <span class="required">*</span></label>
                        </th>
                        <td>
                            <input type="text" name="main_file" id="main_file" class="regular-text" required />
                            <button type="button" class="button" id="auto-generate-filename"><?php _e('Auto-generate', 'dgptm-suite'); ?></button>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">
                            <label for="icon"><?php _e('Icon', 'dgptm-suite'); ?></label>
                        </th>
                        <td>
                            <input type="text" name="icon" id="icon" value="dashicons-admin-plugins" class="regular-text" />
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary button-hero" id="create-module-btn">
                        <span class="dashicons dashicons-plus-alt"></span>
                        <?php _e('Create Module', 'dgptm-suite'); ?>
                    </button>
                </p>
            </form>
        </div>
    </div>

    <div id="dgptm-result" style="display: none;"></div>
</div>

<script>
jQuery(document).ready(function($) {
    // Mode switching
    $('.mode-btn').on('click', function() {
        var mode = $(this).data('mode');
        $('.mode-btn').removeClass('active');
        $(this).addClass('active');
        $('.dgptm-mode-panel').hide();
        $('#' + mode + '-mode').show();
    });

    // Upload and analyze plugin
    $('#dgptm-upload-module-form').on('submit', function(e) {
        e.preventDefault();

        var formData = new FormData(this);
        formData.append('action', 'dgptm_analyze_plugin');
        formData.append('nonce', $('#dgptm_nonce').val());

        var $btn = $('#upload-analyze-btn');
        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php _e('Analyzing...', 'dgptm-suite'); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    // Fill in detected information
                    $('#detected_name').text(response.data.name);
                    $('#detected_version').text(response.data.version);
                    $('#detected_author').text(response.data.author || '<?php _e('Unknown', 'dgptm-suite'); ?>');
                    $('#detected_description').text(response.data.description);
                    $('#detected_file').text(response.data.main_file);

                    $('#final_name').val(response.data.name);
                    $('#final_version').val(response.data.version);
                    $('#final_author').val(response.data.author);
                    $('#final_description').val(response.data.description);
                    $('#temp_path').val(response.data.temp_path);
                    $('#detected_main_file').val(response.data.main_file);

                    // Suggest module ID
                    var suggested_id = response.data.name.toLowerCase()
                        .replace(/[^a-z0-9\s-]/g, '')
                        .replace(/\s+/g, '-');
                    $('#module_id_auto').val(suggested_id);

                    // Show analysis result
                    $('#dgptm-upload-module-form').hide();
                    $('#plugin-analysis-result').show();
                } else {
                    alert('<?php _e('Error:', 'dgptm-suite'); ?> ' + response.data.message);
                }
            },
            error: function() {
                alert('<?php _e('AJAX error occurred.', 'dgptm-suite'); ?>');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-search"></span> <?php _e('Analyze Plugin', 'dgptm-suite'); ?>');
            }
        });
    });

    // Cancel upload
    $('#cancel-upload-btn').on('click', function() {
        if (confirm('<?php _e('Are you sure you want to cancel? The uploaded file will be deleted.', 'dgptm-suite'); ?>')) {
            var temp_path = $('#temp_path').val();

            $.post(ajaxurl, {
                action: 'dgptm_cancel_upload',
                nonce: dgptmSuite.nonce,
                temp_path: temp_path
            });

            // Reset form
            $('#plugin-analysis-result').hide();
            $('#dgptm-upload-module-form').show()[0].reset();
        }
    });

    // Finalize module
    $('#dgptm-finalize-module-form').on('submit', function(e) {
        e.preventDefault();

        var $btn = $('#finalize-module-btn');
        var $result = $('#dgptm-result');

        $btn.prop('disabled', true).html('<span class="dashicons dashicons-update spin"></span> <?php _e('Adding Module...', 'dgptm-suite'); ?>');

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $(this).serialize() + '&action=dgptm_finalize_module&nonce=' + dgptmSuite.nonce,
            success: function(response) {
                if (response.success) {
                    $result.html(
                        '<div class="notice notice-success"><p>' +
                        '<strong><?php _e('Success!', 'dgptm-suite'); ?></strong> ' +
                        response.data.message +
                        '</p><p><a href="<?php echo admin_url('admin.php?page=dgptm-suite'); ?>" class="button button-primary"><?php _e('Go to Dashboard', 'dgptm-suite'); ?></a></p></div>'
                    ).show();

                    $('#plugin-analysis-result').hide();
                    $('#dgptm-upload-module-form').show()[0].reset();
                } else {
                    alert('<?php _e('Error:', 'dgptm-suite'); ?> ' + response.data.message);
                }
            },
            error: function() {
                alert('<?php _e('AJAX error occurred.', 'dgptm-suite'); ?>');
            },
            complete: function() {
                $btn.prop('disabled', false).html('<span class="dashicons dashicons-yes"></span> <?php _e('Add Module to Suite', 'dgptm-suite'); ?>');
            }
        });
    });

    // Manual mode: Auto-generate filename
    $('#auto-generate-filename').on('click', function() {
        var id = $('#module_id').val();
        if (id) {
            $('#main_file').val(id + '.php');
        }
    });

    // Manual mode: Create module
    $('#dgptm-create-module-form').on('submit', function(e) {
        e.preventDefault();

        var $btn = $('#create-module-btn');
        var $result = $('#dgptm-result');

        $btn.prop('disabled', true).addClass('dgptm-loading');
        $result.hide();

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: $(this).serialize() + '&action=dgptm_create_module&nonce=' + dgptmSuite.nonce,
            success: function(response) {
                if (response.success) {
                    $result.html(
                        '<div class="notice notice-success"><p>' +
                        '<strong><?php _e('Success!', 'dgptm-suite'); ?></strong> ' +
                        response.data.message +
                        '</p><p><a href="<?php echo admin_url('admin.php?page=dgptm-suite'); ?>" class="button button-primary"><?php _e('Go to Dashboard', 'dgptm-suite'); ?></a></p></div>'
                    ).show();

                    $('#dgptm-create-module-form')[0].reset();
                } else {
                    $result.html(
                        '<div class="notice notice-error"><p>' +
                        '<strong><?php _e('Error:', 'dgptm-suite'); ?></strong> ' +
                        response.data.message +
                        '</p></div>'
                    ).show();
                }
            },
            error: function() {
                $result.html(
                    '<div class="notice notice-error"><p><?php _e('AJAX error occurred.', 'dgptm-suite'); ?></p></div>'
                ).show();
            },
            complete: function() {
                $btn.prop('disabled', false).removeClass('dgptm-loading');
            }
        });
    });
});
</script>

<style>
.dgptm-upload-modes {
    margin: 20px 0;
    display: flex;
    gap: 10px;
}

.mode-btn {
    padding: 10px 20px !important;
    height: auto !important;
}

.mode-btn.active {
    background: #2271b1;
    color: #fff;
    border-color: #2271b1;
}

.mode-btn .dashicons {
    margin-right: 5px;
}

.dgptm-form-section {
    background: #fff;
    border: 1px solid #ccd0d4;
    padding: 20px;
    margin: 20px 0;
    border-radius: 4px;
}

.required {
    color: #d63638;
}

#plugin-analysis-result {
    border-top: 2px solid #2271b1;
    padding-top: 20px;
    margin-top: 20px;
}

.required-field th {
    background: #fffbcc;
}

.dashicons.spin {
    animation: rotation 1s infinite linear;
}

@keyframes rotation {
    from { transform: rotate(0deg); }
    to { transform: rotate(359deg); }
}
</style>
