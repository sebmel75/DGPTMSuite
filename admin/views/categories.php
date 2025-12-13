<?php
/**
 * Kategorien-Verwaltung
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<div class="wrap">
    <h1><?php _e('Kategorien-Verwaltung', 'dgptm-suite'); ?></h1>
    <p><?php _e('Verwalten Sie Modul-Kategorien und verschieben Sie Module zwischen Kategorien.', 'dgptm-suite'); ?></p>

    <?php
    // Check if modules need category update
    global $dgptm_suite;

    // Fallback: Versuche dgptm_suite() Funktion wenn global nicht verfügbar
    if (!isset($dgptm_suite) && function_exists('dgptm_suite')) {
        $dgptm_suite = dgptm_suite();
    }

    $modules_need_update = false;
    if (isset($dgptm_suite) && isset($dgptm_suite->module_loader)) {
        $all_modules = $dgptm_suite->module_loader->get_available_modules();
        foreach ($all_modules as $module) {
            // Check if module.json has category field
            if (!isset($module['config']['category'])) {
                $modules_need_update = true;
                break;
            }
        }
    }

    if ($modules_need_update): ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php _e('Hinweis:', 'dgptm-suite'); ?></strong>
                <?php _e('Einige Module haben noch kein "category" Feld. Bitte führen Sie das Update aus, um Module verschieben zu können.', 'dgptm-suite'); ?>
                <a href="<?php echo admin_url('admin.php?page=dgptm-suite-update-categories'); ?>" class="button button-primary" style="margin-left: 10px;">
                    <?php _e('Module-Kategorien jetzt aktualisieren', 'dgptm-suite'); ?>
                </a>
            </p>
        </div>
    <?php endif; ?>

    <?php settings_errors('dgptm_categories'); ?>

    <div class="dgptm-categories-container" style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 20px;">

        <!-- Linke Spalte: Kategorienliste -->
        <div class="dgptm-categories-list">
            <h2><?php _e('Kategorien', 'dgptm-suite'); ?></h2>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th style="width: 30px;"></th>
                        <th><?php _e('Name', 'dgptm-suite'); ?></th>
                        <th><?php _e('Beschreibung', 'dgptm-suite'); ?></th>
                        <th><?php _e('Module', 'dgptm-suite'); ?></th>
                        <th><?php _e('Aktionen', 'dgptm-suite'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat_id => $cat_data): ?>
                        <?php
                        $module_count = count($modules_by_category[$cat_id] ?? []);
                        $is_default = in_array($cat_id, ['core-infrastructure', 'business', 'payment', 'auth', 'media', 'content', 'acf-tools', 'utilities']);
                        ?>
                        <tr data-category-id="<?php echo esc_attr($cat_id); ?>">
                            <td style="background-color: <?php echo esc_attr($cat_data['color'] ?? '#3498db'); ?>; width: 30px;"></td>
                            <td><strong><?php echo esc_html($cat_data['name'] ?? $cat_id); ?></strong></td>
                            <td><?php echo esc_html($cat_data['description'] ?? ''); ?></td>
                            <td><?php echo $module_count; ?> Module</td>
                            <td>
                                <button class="button button-small dgptm-edit-category" data-category-id="<?php echo esc_attr($cat_id); ?>">
                                    <?php _e('Bearbeiten', 'dgptm-suite'); ?>
                                </button>
                                <?php if (!$is_default): ?>
                                    <button class="button button-small button-link-delete dgptm-delete-category" data-category-id="<?php echo esc_attr($cat_id); ?>">
                                        <?php _e('Löschen', 'dgptm-suite'); ?>
                                    </button>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <br>
            <button class="button button-primary" id="dgptm-show-add-category-form"><?php _e('Neue Kategorie erstellen', 'dgptm-suite'); ?></button>
        </div>

        <!-- Rechte Spalte: Modulen nach Kategorien -->
        <div class="dgptm-modules-by-category">
            <h2><?php _e('Module verschieben', 'dgptm-suite'); ?></h2>
            <p><strong><?php _e('Anleitung:', 'dgptm-suite'); ?></strong> <?php _e('Wählen Sie bei einem Modul die gewünschte Zielkategorie aus dem Dropdown-Menü und klicken Sie auf "Verschieben".', 'dgptm-suite'); ?></p>

            <?php if (empty($modules_by_category)): ?>
                <div class="notice notice-info">
                    <p><?php _e('Keine Module gefunden. Bitte überprüfen Sie, ob Module vorhanden sind.', 'dgptm-suite'); ?></p>
                </div>
            <?php else:
                $total_modules = 0;
                foreach ($modules_by_category as $modules) {
                    $total_modules += count($modules);
                }
            ?>
                <p style="color: #666; font-size: 13px;">
                    <?php printf(__('Insgesamt %d Module gefunden', 'dgptm-suite'), $total_modules); ?>
                </p>
            <?php endif; ?>

            <?php foreach ($modules_by_category as $cat_id => $modules): ?>
                <?php if (empty($modules)) continue; ?>
                <div class="dgptm-category-modules" style="margin-bottom: 20px; border: 1px solid #ddd; padding: 15px; border-radius: 4px;">
                    <h3 style="margin-top: 0; color: <?php echo esc_attr($categories[$cat_id]['color'] ?? '#3498db'); ?>;">
                        <?php echo esc_html($categories[$cat_id]['name'] ?? $cat_id); ?>
                    </h3>

                    <table class="widefat">
                        <tbody>
                            <?php foreach ($modules as $module): ?>
                                <tr>
                                    <td style="width: 60%;">
                                        <strong><?php echo esc_html($module['name']); ?></strong><br>
                                        <small style="color: #666;"><?php echo esc_html($module['description']); ?></small>
                                    </td>
                                    <td>
                                        <select class="dgptm-module-category-select" data-module-id="<?php echo esc_attr($module['id']); ?>" style="width: 100%;">
                                            <?php foreach ($categories as $opt_cat_id => $opt_cat_data): ?>
                                                <option value="<?php echo esc_attr($opt_cat_id); ?>" <?php selected($opt_cat_id, $cat_id); ?>>
                                                    <?php echo esc_html($opt_cat_data['name'] ?? $opt_cat_id); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td style="width: 100px;">
                                        <button class="button button-small dgptm-move-module" data-module-id="<?php echo esc_attr($module['id']); ?>">
                                            <?php _e('Verschieben', 'dgptm-suite'); ?>
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Modal: Kategorie hinzufügen/bearbeiten -->
<div id="dgptm-category-modal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
    <div style="background-color: #fff; margin: 10% auto; padding: 20px; border-radius: 8px; max-width: 500px;">
        <h2 id="dgptm-modal-title"><?php _e('Kategorie hinzufügen', 'dgptm-suite'); ?></h2>

        <form method="post" id="dgptm-category-form">
            <?php wp_nonce_field('dgptm_category_action'); ?>
            <input type="hidden" name="dgptm_category_action" value="1">
            <input type="hidden" name="category_action" id="dgptm-category-action" value="add">

            <table class="form-table">
                <tr>
                    <th><label for="category_id"><?php _e('Kategorie-ID', 'dgptm-suite'); ?> *</label></th>
                    <td>
                        <input type="text" id="category_id" name="category_id" class="regular-text" required>
                        <p class="description"><?php _e('Eindeutiger Bezeichner (nur Kleinbuchstaben und Bindestriche)', 'dgptm-suite'); ?></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="category_name"><?php _e('Name', 'dgptm-suite'); ?> *</label></th>
                    <td>
                        <input type="text" id="category_name" name="category_name" class="regular-text" required>
                    </td>
                </tr>
                <tr>
                    <th><label for="category_description"><?php _e('Beschreibung', 'dgptm-suite'); ?></label></th>
                    <td>
                        <textarea id="category_description" name="category_description" class="large-text" rows="3"></textarea>
                    </td>
                </tr>
                <tr>
                    <th><label for="category_color"><?php _e('Farbe', 'dgptm-suite'); ?></label></th>
                    <td>
                        <input type="color" id="category_color" name="category_color" value="#3498db">
                    </td>
                </tr>
            </table>

            <p>
                <button type="submit" class="button button-primary"><?php _e('Speichern', 'dgptm-suite'); ?></button>
                <button type="button" class="button" id="dgptm-close-modal"><?php _e('Abbrechen', 'dgptm-suite'); ?></button>
            </p>
        </form>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Kategorie hinzufügen Modal anzeigen
    $('#dgptm-show-add-category-form').on('click', function() {
        $('#dgptm-modal-title').text('<?php _e('Kategorie hinzufügen', 'dgptm-suite'); ?>');
        $('#dgptm-category-action').val('add');
        $('#dgptm-category-form')[0].reset();
        $('#category_id').prop('readonly', false);
        $('#dgptm-category-modal').fadeIn();
    });

    // Kategorie bearbeiten
    $('.dgptm-edit-category').on('click', function() {
        var catId = $(this).data('category-id');
        var row = $(this).closest('tr');

        $('#dgptm-modal-title').text('<?php _e('Kategorie bearbeiten', 'dgptm-suite'); ?>');
        $('#dgptm-category-action').val('edit');
        $('#category_id').val(catId).prop('readonly', true);
        $('#category_name').val(row.find('td:nth-child(2)').text().trim());
        $('#category_description').val(row.find('td:nth-child(3)').text().trim());
        $('#category_color').val(row.find('td:first').css('background-color'));

        $('#dgptm-category-modal').fadeIn();
    });

    // Modal schließen
    $('#dgptm-close-modal').on('click', function() {
        $('#dgptm-category-modal').fadeOut();
    });

    // Kategorie löschen
    $('.dgptm-delete-category').on('click', function() {
        if (!confirm('<?php _e('Sind Sie sicher, dass Sie diese Kategorie löschen möchten? Alle Module werden in "Uncategorized" verschoben.', 'dgptm-suite'); ?>')) {
            return;
        }

        var catId = $(this).data('category-id');
        var form = $('<form method="post">' +
            '<?php echo wp_nonce_field('dgptm_category_action', '_wpnonce', true, false); ?>' +
            '<input type="hidden" name="dgptm_category_action" value="1">' +
            '<input type="hidden" name="category_action" value="delete">' +
            '<input type="hidden" name="category_id" value="' + catId + '">' +
            '</form>');

        $('body').append(form);
        form.submit();
    });

    // Modul verschieben
    $('.dgptm-move-module').on('click', function() {
        var moduleId = $(this).data('module-id');
        var newCategory = $(this).closest('tr').find('.dgptm-module-category-select').val();

        if (!confirm('<?php _e('Möchten Sie dieses Modul wirklich verschieben?', 'dgptm-suite'); ?>')) {
            return;
        }

        var form = $('<form method="post">' +
            '<?php echo wp_nonce_field('dgptm_category_action', '_wpnonce', true, false); ?>' +
            '<input type="hidden" name="dgptm_category_action" value="1">' +
            '<input type="hidden" name="category_action" value="move_module">' +
            '<input type="hidden" name="module_id" value="' + moduleId + '">' +
            '<input type="hidden" name="new_category" value="' + newCategory + '">' +
            '</form>');

        $('body').append(form);
        form.submit();
    });
});
</script>

<style>
.dgptm-category-modules table td {
    padding: 8px;
    vertical-align: middle;
}
.dgptm-category-modules h3 {
    border-bottom: 2px solid currentColor;
    padding-bottom: 8px;
}
.dgptm-modules-by-category {
    background: #f9f9f9;
    padding: 15px;
    border-radius: 4px;
}
.dgptm-module-category-select {
    min-width: 200px;
}
.dgptm-move-module {
    white-space: nowrap;
}
.dgptm-category-modules {
    background: white;
}
</style>
