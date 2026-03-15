<?php
if (!defined('ABSPATH')) exit;
$all_tabs = $tabs->get_all();
$acf_fields = [
    '' => '-- Keine --',
    'news_schreiben' => 'news_schreiben', 'news_alle' => 'news_alle',
    'stellenanzeigen_anlegen' => 'stellenanzeigen_anlegen', 'testbereich' => 'testbereich',
    'eventtracker' => 'eventtracker', 'quizz_verwalten' => 'quizz_verwalten',
    'delegate' => 'delegate', 'timeline' => 'timeline',
    'mitgliederversammlung' => 'mitgliederversammlung', 'checkliste' => 'checkliste',
    'webinar' => 'webinar', 'checkliste_erstellen' => 'checkliste_erstellen',
    'editor_in_chief' => 'editor_in_chief', 'redaktion_perfusiologie' => 'redaktion_perfusiologie',
    'zeitschriftmanager' => 'zeitschriftmanager', 'umfragen' => 'umfragen',
];
// Top-level tabs for parent dropdown
$top_tabs = array_filter($all_tabs, function($t) { return empty($t['parent']); });
?>
<div class="wrap dgptm-dashboard-admin">
    <h1>Dashboard-Einstellungen</h1>
    <p class="description">Konfiguriere Tabs, Reihenfolge, Berechtigungen und Darstellung des Mitglieder-Dashboards.</p>

    <div id="dgptm-dash-msg"></div>

    <div class="nav-tab-wrapper">
        <a href="#" class="nav-tab nav-tab-active" data-admin-tab="tabs">Tabs</a>
        <a href="#" class="nav-tab" data-admin-tab="settings">Einstellungen</a>
    </div>

    <!-- Tabs Config -->
    <div class="dgptm-admin-section" data-admin-panel="tabs">
        <h2>Tab-Verwaltung</h2>
        <p class="description">Deaktivierte Tabs werden nicht angezeigt.</p>

        <div id="dgptm-tab-list" class="dgptm-tab-list">
            <?php foreach ($all_tabs as $tab) :
                // Parse permission for display
                $perm = $tab['permission'] ?? 'always';
                $perm_type = 'always';
                $perm_acf = '';
                $perm_roles = '';
                if ($perm === 'admin') $perm_type = 'admin';
                elseif (strpos($perm, 'acf:') === 0) { $perm_type = 'acf_field'; $perm_acf = substr($perm, 4); }
                elseif (strpos($perm, 'role:') === 0) { $perm_type = 'role'; $perm_roles = substr($perm, 5); }
            ?>
            <div class="dgptm-tab-config-item" data-tab-id="<?php echo esc_attr($tab['id']); ?>">
                <div class="dgptm-tab-config-header">
                    <span class="dashicons <?php echo esc_attr(!empty($tab['parent']) ? 'dashicons-arrow-right-alt' : 'dashicons-menu'); ?>" style="color:#999;"></span>
                    <strong class="dgptm-tab-config-label"><?php echo esc_html($tab['label']); ?></strong>
                    <code class="dgptm-tab-config-id"><?php echo esc_html($tab['id']); ?></code>
                    <?php if (!empty($tab['parent'])) : ?>
                        <span style="font-size:11px;color:#2271b1;background:#e8f0fe;padding:2px 8px;border-radius:3px;">↳ <?php echo esc_html($tab['parent']); ?></span>
                    <?php endif; ?>
                    <label class="dgptm-tab-config-toggle">
                        <input type="checkbox" class="dt-active" <?php checked($tab['active']); ?>>
                        Aktiv
                    </label>
                    <button type="button" class="button button-small dgptm-tab-expand">Details</button>
                    <button type="button" class="button button-small dgptm-tab-delete" style="color:#b32d2e;">Loeschen</button>
                </div>
                <div class="dgptm-tab-config-details" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th>Label</th>
                            <td><input type="text" class="dt-label regular-text" value="<?php echo esc_attr($tab['label']); ?>"></td>
                        </tr>
                        <tr>
                            <th>Uebergeordneter Tab</th>
                            <td>
                                <select class="dt-parent">
                                    <option value="">-- Kein (Top-Level) --</option>
                                    <?php foreach ($top_tabs as $ptab) :
                                        if ($ptab['id'] === $tab['id']) continue;
                                    ?>
                                        <option value="<?php echo esc_attr($ptab['id']); ?>" <?php selected($tab['parent'] ?? '', $ptab['id']); ?>>
                                            <?php echo esc_html($ptab['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Wird als Folder-Unter-Tab unter dem gewaehlten Tab angezeigt.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Berechtigungstyp</th>
                            <td>
                                <?php
                                // Also detect sc: type
                                if (strpos($perm, 'sc:') === 0) { $perm_type = 'shortcode'; $perm_sc = substr($perm, 3); }
                                else { $perm_sc = ''; }
                                ?>
                                <select class="dt-perm-type">
                                    <option value="always" <?php selected($perm_type, 'always'); ?>>Immer sichtbar</option>
                                    <option value="acf_field" <?php selected($perm_type, 'acf_field'); ?>>ACF-Feld</option>
                                    <option value="role" <?php selected($perm_type, 'role'); ?>>WordPress-Rolle</option>
                                    <option value="shortcode" <?php selected($perm_type, 'shortcode'); ?>>Shortcode (gibt 1/0 zurueck)</option>
                                    <option value="admin" <?php selected($perm_type, 'admin'); ?>>Nur Admins</option>
                                </select>
                            </td>
                        </tr>
                        <tr class="dt-row-acf" <?php if ($perm_type !== 'acf_field') echo 'style="display:none;"'; ?>>
                            <th>ACF-Feld</th>
                            <td>
                                <select class="dt-perm-acf">
                                    <?php foreach ($acf_fields as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($perm_acf, $key); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr class="dt-row-role" <?php if ($perm_type !== 'role') echo 'style="display:none;"'; ?>>
                            <th>Rollen (kommagetrennt)</th>
                            <td><input type="text" class="dt-perm-roles regular-text" value="<?php echo esc_attr($perm_roles); ?>" placeholder="administrator,editor,mitglied"></td>
                        </tr>
                        <tr class="dt-row-shortcode" <?php if ($perm_type !== 'shortcode') echo 'style="display:none;"'; ?>>
                            <th>Shortcode-Name</th>
                            <td>
                                <input type="text" class="dt-perm-sc regular-text" value="<?php echo esc_attr($perm_sc); ?>" placeholder="umfrageberechtigung">
                                <p class="description">Shortcode ohne Klammern. Muss <code>1</code> zurueckgeben fuer sichtbar, <code>0</code> fuer versteckt.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Inhalt (HTML / Shortcodes)</th>
                            <td>
                                <textarea class="dt-content large-text code" rows="10" style="font-family:Consolas,monospace;font-size:12px;"><?php echo esc_textarea($tab['content'] ?? ''); ?></textarea>
                                <p class="description">HTML und Shortcodes wie <code>[zoho_api_data_ajax field="Vorname"]</code> werden im Frontend verarbeitet.</p>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <h3 style="margin-top:24px;">Neuen Tab erstellen</h3>
        <div style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px;">
            <div>
                <label style="display:block;font-size:12px;margin-bottom:2px;">ID (eindeutig, keine Leerzeichen)</label>
                <input type="text" id="new-tab-id" class="regular-text" placeholder="mein-neuer-tab" style="width:180px;">
            </div>
            <div>
                <label style="display:block;font-size:12px;margin-bottom:2px;">Label</label>
                <input type="text" id="new-tab-label" class="regular-text" placeholder="Mein neuer Tab" style="width:200px;">
            </div>
            <div>
                <label style="display:block;font-size:12px;margin-bottom:2px;">Uebergeordneter Tab</label>
                <select id="new-tab-parent" style="width:180px;">
                    <option value="">-- Kein (Top-Level) --</option>
                    <?php foreach ($top_tabs as $ptab) : ?>
                        <option value="<?php echo esc_attr($ptab['id']); ?>"><?php echo esc_html($ptab['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="button button-secondary" id="dt-add-tab">+ Tab erstellen</button>
        </div>

        <p class="submit">
            <button type="button" class="button button-primary" id="dt-save">Alle Tabs speichern</button>
            <button type="button" class="button" id="dt-reset" style="margin-left: 8px;">Auf Standard zuruecksetzen</button>
        </p>
    </div>

    <!-- Settings -->
    <?php $settings = $tabs->get_settings(); ?>
    <div class="dgptm-admin-section" data-admin-panel="settings" style="display:none;">
        <h2>Allgemeine Einstellungen</h2>
        <table class="form-table">
            <tr>
                <th>Admin-Bypass</th>
                <td>
                    <label>
                        <input type="checkbox" id="dt-admin-bypass" <?php checked($settings['admin_bypass']); ?>>
                        Administratoren sehen alle Tabs unabhaengig von Berechtigungsregeln
                    </label>
                    <p class="description">
                        Wenn <strong>deaktiviert</strong>, werden auch fuer Administratoren nur die Tabs angezeigt,
                        fuer die sie laut Berechtigung freigeschaltet sind. "Immer sichtbar" und "Nur Admins" Tabs
                        bleiben davon unberuehrt.
                    </p>
                </td>
            </tr>
        </table>
        <p class="submit">
            <button type="button" class="button button-primary" id="dt-save-settings">Einstellungen speichern</button>
        </p>

        <hr>
        <h3>Berechtigungstypen</h3>
        <table class="widefat" style="max-width:700px;">
            <thead><tr><th>Typ</th><th>Format</th><th>Beschreibung</th></tr></thead>
            <tbody>
                <tr><td><strong>Immer sichtbar</strong></td><td><code>always</code></td><td>Fuer alle eingeloggten Benutzer</td></tr>
                <tr><td><strong>Nur Admins</strong></td><td><code>admin</code></td><td>Nur Benutzer mit manage_options</td></tr>
                <tr><td><strong>ACF-Feld</strong></td><td><code>acf:feldname</code></td><td>ACF True/False Feld auf dem Benutzerprofil (z.B. <code>acf:testbereich</code>)</td></tr>
                <tr><td><strong>WordPress-Rolle</strong></td><td><code>role:rolle1,rolle2</code></td><td>Benutzer mit einer der Rollen (OR-Logik)</td></tr>
                <tr><td><strong>Shortcode</strong></td><td><code>sc:shortcode_name</code></td><td>Shortcode muss <code>1</code> zurueckgeben fuer sichtbar. Beispiel: <code>sc:umfrageberechtigung</code></td></tr>
            </tbody>
        </table>
    </div>
</div>
