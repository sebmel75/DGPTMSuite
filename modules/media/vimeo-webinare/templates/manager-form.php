<?php
/**
 * Template: Inline-Formular für Create/Edit eines Webinars.
 *
 * Variablen: $w (optional: bestehendes Webinar für Edit, sonst leer)
 */
if (!defined('ABSPATH')) exit;

$w = $w ?? [
    'id' => 0, 'title' => '', 'description' => '', 'vimeo_id' => '',
    'completion_percentage' => 90, 'ebcp_points' => 1, 'vnr' => '',
];
$is_edit = $w['id'] > 0;
?>
<form class="dgptm-vw-form dgptm-card">
    <h3><?php echo $is_edit ? 'Webinar bearbeiten' : 'Neues Webinar'; ?></h3>
    <input type="hidden" name="post_id" value="<?php echo esc_attr($w['id']); ?>" />

    <div class="dgptm-vw-form-row">
        <label class="dgptm-vw-form-field dgptm-vw-form-field-full">
            <span>Titel <em>*</em></span>
            <input type="text" name="title" required value="<?php echo esc_attr($w['title']); ?>" />
        </label>
    </div>

    <div class="dgptm-vw-form-row">
        <label class="dgptm-vw-form-field dgptm-vw-form-field-full">
            <span>Beschreibung</span>
            <textarea name="description" rows="3"><?php echo esc_textarea($w['description']); ?></textarea>
        </label>
    </div>

    <div class="dgptm-vw-form-row dgptm-vw-form-row-split">
        <label class="dgptm-vw-form-field">
            <span>Vimeo-ID <em>*</em></span>
            <input type="text" name="vimeo_id" required value="<?php echo esc_attr($w['vimeo_id']); ?>" />
            <small>Nur die Zahlen-ID</small>
        </label>
        <label class="dgptm-vw-form-field">
            <span>Erforderlich % <em>*</em></span>
            <input type="number" name="completion_percentage" min="1" max="100" value="<?php echo esc_attr($w['completion_percentage']); ?>" required />
        </label>
    </div>

    <div class="dgptm-vw-form-row dgptm-vw-form-row-split">
        <label class="dgptm-vw-form-field">
            <span>EBCP-Punkte <em>*</em></span>
            <input type="number" name="points" step="0.5" min="0" value="<?php echo esc_attr($w['ebcp_points']); ?>" required />
        </label>
        <label class="dgptm-vw-form-field">
            <span>VNR</span>
            <input type="text" name="vnr" value="<?php echo esc_attr($w['vnr']); ?>" />
        </label>
    </div>

    <div class="dgptm-vw-form-actions">
        <button type="button" class="dgptm-btn--ghost dgptm-vw-form-cancel">Abbrechen</button>
        <button type="submit" class="dgptm-btn--primary dgptm-vw-form-save">Speichern</button>
    </div>
</form>
