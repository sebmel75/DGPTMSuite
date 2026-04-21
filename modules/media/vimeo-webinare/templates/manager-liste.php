<?php
/**
 * Template: Manager-Liste + Inline-Editor-Slot
 * Variablen: $webinars (array), $stats (array), $nonce (string)
 */
if (!defined('ABSPATH')) exit;
?>
<div class="dgptm-vw dgptm-vw-mgr" data-nonce="<?php echo esc_attr($nonce); ?>">

    <div class="dgptm-vw-mgr-toolbar">
        <label class="dgptm-vw-search">
            <span class="dashicons dashicons-search" aria-hidden="true"></span>
            <input type="text" class="dgptm-vw-mgr-search-input" placeholder="Webinare durchsuchen..." />
        </label>
        <button type="button" class="dgptm-btn--primary dgptm-vw-create-new">
            <span class="dashicons dashicons-plus-alt" aria-hidden="true"></span>
            Neues Webinar
        </button>
    </div>

    <!-- Editor-Slot für Create (oberhalb der Tabelle) -->
    <div class="dgptm-vw-editor-slot dgptm-vw-editor-create" hidden></div>

    <table class="dgptm-vw-mgr-table">
        <thead>
            <tr>
                <th>Titel</th>
                <th>Datum</th>
                <th>Vimeo-ID</th>
                <th>EBCP</th>
                <th>Erforderlich</th>
                <th>Abgeschlossen</th>
                <th class="dgptm-vw-col-actions">Aktionen</th>
            </tr>
        </thead>
        <tbody class="dgptm-vw-mgr-tbody">
            <?php foreach ($webinars as $w):
                $s = $stats[$w['id']] ?? ['completed' => 0, 'in_progress' => 0, 'total_views' => 0];
                include plugin_dir_path(__FILE__) . 'manager-row.php';
            endforeach; ?>
        </tbody>
    </table>

    <?php if (empty($webinars)): ?>
        <p class="dgptm-vw-empty">Keine Webinare vorhanden.</p>
    <?php endif; ?>

    <template id="dgptm-vw-form-template"><?php include plugin_dir_path(__FILE__) . 'manager-form.php'; ?></template>

</div>

<div class="dgptm-vw-toast-layer" aria-live="polite"></div>
