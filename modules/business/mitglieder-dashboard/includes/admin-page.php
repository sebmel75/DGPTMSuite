<?php if (!defined('ABSPATH')) exit; $all_tabs = $tabs->get_all();

$acf_fields = [
    'news_schreiben', 'news_alle', 'stellenanzeigen_anlegen', 'testbereich',
    'eventtracker', 'quizz_verwalten', 'delegate', 'timeline',
    'mitgliederversammlung', 'checkliste', 'webinar', 'checkliste_erstellen',
    'editor_in_chief', 'redaktion_perfusiologie', 'zeitschriftmanager', 'umfragen',
];

// Collect top-level tab IDs for parent dropdown
$top_ids = [];
foreach ($all_tabs as $t) {
    if (empty($t['parent'])) $top_ids[] = $t;
}
?>
<div class="wrap dgptm-dash-admin">
    <h1>Dashboard-Einstellungen</h1>
    <p class="description">Verwalte die Tabs des Mitglieder-Dashboards. Inhalte koennen HTML und Shortcodes enthalten.</p>

    <div id="dgptm-dash-msg"></div>

    <div id="dgptm-tab-list">
        <?php foreach ($all_tabs as $i => $t) : ?>
        <div class="dgptm-adm-tab" data-id="<?php echo esc_attr($t['id']); ?>">
            <div class="dgptm-adm-header">
                <span class="dgptm-adm-num"><?php echo $i + 1; ?>.</span>
                <strong class="dgptm-adm-title"><?php echo esc_html($t['label']); ?></strong>
                <code class="dgptm-adm-id"><?php echo esc_html($t['id']); ?></code>
                <?php if (!empty($t['parent'])) : ?>
                    <span class="dgptm-adm-parent-badge">Unter-Tab von: <?php echo esc_html($t['parent']); ?></span>
                <?php endif; ?>
                <label class="dgptm-adm-active-toggle">
                    <input type="checkbox" class="dt-active" <?php checked($t['active']); ?>>
                    Aktiv
                </label>
                <button type="button" class="button button-small dgptm-adm-toggle">Details</button>
                <button type="button" class="button button-small dgptm-adm-delete" style="color:#dc2626;">Loeschen</button>
            </div>
            <div class="dgptm-adm-body" style="display:none;">
                <table class="form-table">
                    <tr>
                        <th>Label</th>
                        <td><input type="text" class="dt-label regular-text" value="<?php echo esc_attr($t['label']); ?>"></td>
                    </tr>
                    <tr>
                        <th>Uebergeordneter Tab</th>
                        <td>
                            <select class="dt-parent">
                                <option value="">-- Kein (Top-Level) --</option>
                                <?php foreach ($top_ids as $p) :
                                    if ($p['id'] === $t['id']) continue;
                                ?>
                                    <option value="<?php echo esc_attr($p['id']); ?>" <?php selected($t['parent'], $p['id']); ?>>
                                        <?php echo esc_html($p['label']); ?> (<?php echo esc_html($p['id']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Wird als Folder-Unter-Tab unter dem gewaehlten Tab angezeigt.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Berechtigung</th>
                        <td>
                            <select class="dt-perm-type" style="margin-bottom:6px;">
                                <option value="always" <?php selected(strpos($t['permission'] ?? '', 'acf:') === false && strpos($t['permission'] ?? '', 'role:') === false && ($t['permission'] ?? '') !== 'admin'); ?>>Immer sichtbar</option>
                                <option value="admin" <?php selected($t['permission'] ?? '', 'admin'); ?>>Nur Admins</option>
                                <option value="acf" <?php selected(strpos($t['permission'] ?? '', 'acf:'), 0); ?>>ACF-Feld</option>
                                <option value="role" <?php selected(strpos($t['permission'] ?? '', 'role:'), 0); ?>>WordPress-Rolle</option>
                            </select>
                            <div class="dt-perm-acf" style="<?php echo strpos($t['permission'] ?? '', 'acf:') === 0 ? '' : 'display:none;'; ?>margin-top:4px;">
                                <select class="dt-perm-acf-field">
                                    <?php
                                    $current_acf = str_replace('acf:', '', $t['permission'] ?? '');
                                    foreach ($acf_fields as $f) : ?>
                                        <option value="<?php echo esc_attr($f); ?>" <?php selected($current_acf, $f); ?>><?php echo esc_html($f); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="dt-perm-role" style="<?php echo strpos($t['permission'] ?? '', 'role:') === 0 ? '' : 'display:none;'; ?>margin-top:4px;">
                                <input type="text" class="dt-perm-role-val regular-text" value="<?php echo esc_attr(str_replace('role:', '', $t['permission'] ?? '')); ?>" placeholder="administrator,mitglied,editor">
                                <p class="description">Kommagetrennte Rollennamen</p>
                            </div>
                            <input type="hidden" class="dt-perm" value="<?php echo esc_attr($t['permission'] ?? 'always'); ?>">
                        </td>
                    </tr>
                    <tr>
                        <th>Inhalt (HTML / Shortcodes)</th>
                        <td>
                            <textarea class="dt-content large-text code" rows="12" style="font-family:Consolas,monospace;font-size:12px;"><?php echo esc_textarea($t['content']); ?></textarea>
                            <p class="description">HTML und WordPress-Shortcodes. Beispiel: <code>[zoho_api_data_ajax field="Vorname"]</code></p>
                        </td>
                    </tr>
                </table>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="dgptm-adm-section" style="margin-top:24px;">
        <h2>Neuen Tab erstellen</h2>
        <table class="form-table">
            <tr>
                <th>ID</th>
                <td><input type="text" id="new-id" class="regular-text" placeholder="mein-neuer-tab" style="max-width:200px;">
                    <p class="description">Eindeutig, Kleinbuchstaben, keine Leerzeichen</p></td>
            </tr>
            <tr>
                <th>Label</th>
                <td><input type="text" id="new-label" class="regular-text" placeholder="Mein neuer Tab"></td>
            </tr>
            <tr>
                <th>Uebergeordneter Tab</th>
                <td>
                    <select id="new-parent">
                        <option value="">-- Kein (Top-Level) --</option>
                        <?php foreach ($top_ids as $p) : ?>
                            <option value="<?php echo esc_attr($p['id']); ?>"><?php echo esc_html($p['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <p><button type="button" class="button button-secondary" id="dt-add">+ Tab erstellen</button></p>
    </div>

    <hr>

    <p class="submit">
        <button type="button" class="button button-primary button-hero" id="dt-save">Alle Tabs speichern</button>
        <button type="button" class="button" id="dt-reset" style="margin-left:12px;">Auf Standard zuruecksetzen</button>
    </p>
</div>
