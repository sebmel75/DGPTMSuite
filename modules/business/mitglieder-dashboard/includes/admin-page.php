<?php if (!defined('ABSPATH')) exit; $all_tabs = $tabs->get_all(); ?>
<div class="wrap">
    <h1>Dashboard Tabs verwalten</h1>

    <div id="dgptm-dash-msg"></div>

    <table class="widefat" id="dgptm-tab-table">
        <thead>
            <tr>
                <th style="width:30px">#</th>
                <th style="width:140px">ID</th>
                <th style="width:160px">Label</th>
                <th style="width:120px">Parent</th>
                <th style="width:160px">Berechtigung</th>
                <th style="width:50px">Aktiv</th>
                <th>Inhalt (HTML / Shortcodes)</th>
                <th style="width:60px"></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($all_tabs as $i => $t) : ?>
            <tr data-id="<?php echo esc_attr($t['id']); ?>">
                <td><?php echo $i + 1; ?></td>
                <td><code><?php echo esc_html($t['id']); ?></code></td>
                <td><input type="text" class="dt-label" value="<?php echo esc_attr($t['label']); ?>" style="width:100%"></td>
                <td><input type="text" class="dt-parent" value="<?php echo esc_attr($t['parent']); ?>" style="width:100%" placeholder="leer = Top-Level"></td>
                <td><input type="text" class="dt-perm" value="<?php echo esc_attr($t['permission']); ?>" style="width:100%" placeholder="always"></td>
                <td style="text-align:center"><input type="checkbox" class="dt-active" <?php checked($t['active']); ?>></td>
                <td><textarea class="dt-content" rows="3" style="width:100%;font-family:monospace;font-size:11px;"><?php echo esc_textarea($t['content']); ?></textarea></td>
                <td><button type="button" class="button button-small dt-delete">X</button></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h3 style="margin-top:20px">Neuen Tab</h3>
    <div style="display:flex;gap:8px;align-items:flex-end;margin-bottom:16px;">
        <input type="text" id="new-id" placeholder="tab-id" style="width:140px">
        <input type="text" id="new-label" placeholder="Label" style="width:160px">
        <input type="text" id="new-parent" placeholder="Parent (leer=Top)" style="width:140px">
        <button type="button" class="button" id="dt-add">+ Hinzufuegen</button>
    </div>

    <p>
        <button type="button" class="button button-primary" id="dt-save">Alle Tabs speichern</button>
        <button type="button" class="button" id="dt-reset" style="margin-left:8px">Auf Standard zuruecksetzen</button>
    </p>

    <p class="description">
        <strong>Berechtigung:</strong> <code>always</code> | <code>admin</code> | <code>acf:feldname</code> | <code>role:rolle1,rolle2</code><br>
        <strong>Inhalt:</strong> HTML und Shortcodes wie <code>[zoho_api_data_ajax field="Vorname"]</code> werden verarbeitet.
    </p>
</div>
