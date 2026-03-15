<?php
if (!defined('ABSPATH')) exit;

$all_tabs = $config->get_tabs();
$settings = $config->get_config()['settings'] ?? [];
$acf_fields = [
    '' => '-- Keine --',
    'news_schreiben' => 'news_schreiben',
    'news_alle' => 'news_alle',
    'stellenanzeigen_anlegen' => 'stellenanzeigen_anlegen',
    'testbereich' => 'testbereich',
    'eventtracker' => 'eventtracker',
    'quizz_verwalten' => 'quizz_verwalten',
    'delegate' => 'delegate',
    'timeline' => 'timeline',
    'mitgliederversammlung' => 'mitgliederversammlung',
    'checkliste' => 'checkliste',
    'webinar' => 'webinar',
    'checkliste_erstellen' => 'checkliste_erstellen',
    'checkliste_template_erstellen' => 'checkliste_template_erstellen',
    'editor_in_chief' => 'editor_in_chief',
    'redaktion_perfusiologie' => 'redaktion_perfusiologie',
    'zeitschriftmanager' => 'zeitschriftmanager',
    'umfragen' => 'umfragen',
];
?>
<div class="wrap dgptm-dashboard-admin">
    <h1>Dashboard-Einstellungen</h1>
    <p class="description">Konfiguriere Tabs, Reihenfolge, Berechtigungen und Darstellung des Mitglieder-Dashboards.</p>

    <div class="nav-tab-wrapper">
        <a href="#" class="nav-tab nav-tab-active" data-admin-tab="tabs">Tabs</a>
        <a href="#" class="nav-tab" data-admin-tab="settings">Einstellungen</a>
    </div>

    <!-- Tabs Config -->
    <div class="dgptm-admin-section" data-admin-panel="tabs">
        <h2>Tab-Verwaltung</h2>
        <p class="description">Drag & Drop zum Umsortieren. Deaktivierte Tabs werden nicht angezeigt.</p>

        <div id="dgptm-tab-list" class="dgptm-tab-list">
            <?php foreach ($all_tabs as $tab) : ?>
            <div class="dgptm-tab-config-item" data-tab-id="<?php echo esc_attr($tab['id']); ?>">
                <div class="dgptm-tab-config-header">
                    <span class="dgptm-drag-handle dashicons dashicons-move"></span>
                    <span class="dashicons <?php echo esc_attr($tab['icon'] ?? 'dashicons-admin-page'); ?>"></span>
                    <strong class="dgptm-tab-config-label"><?php echo esc_html($tab['label']); ?></strong>
                    <code class="dgptm-tab-config-id"><?php echo esc_html($tab['id']); ?></code>
                    <label class="dgptm-tab-config-toggle">
                        <input type="checkbox" class="dgptm-tab-active" <?php checked($tab['active']); ?>>
                        Aktiv
                    </label>
                    <button type="button" class="button button-small dgptm-tab-expand">Details</button>
                </div>
                <div class="dgptm-tab-config-details" style="display:none;">
                    <table class="form-table">
                        <tr>
                            <th>Label</th>
                            <td><input type="text" class="dgptm-tab-label regular-text" value="<?php echo esc_attr($tab['label']); ?>"></td>
                        </tr>
                        <tr>
                            <th>Icon (Dashicons)</th>
                            <td><input type="text" class="dgptm-tab-icon regular-text" value="<?php echo esc_attr($tab['icon'] ?? ''); ?>" placeholder="dashicons-admin-page"></td>
                        </tr>
                        <tr>
                            <th>Uebergeordneter Tab</th>
                            <td>
                                <select class="dgptm-tab-parent">
                                    <option value="">-- Kein (Top-Level) --</option>
                                    <?php foreach ($all_tabs as $ptab) :
                                        if ($ptab['id'] === $tab['id']) continue;
                                        if (!empty($ptab['parent_tab'])) continue; // Only top-level as parents
                                    ?>
                                        <option value="<?php echo esc_attr($ptab['id']); ?>" <?php selected($tab['parent_tab'] ?? '', $ptab['id']); ?>>
                                            <?php echo esc_html($ptab['label']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <p class="description">Wird als Unter-Tab (Folder) unter dem gewaehlten Tab angezeigt.</p>
                            </td>
                        </tr>
                        <tr>
                            <th>Berechtigungstyp</th>
                            <td>
                                <select class="dgptm-tab-permission-type">
                                    <option value="always" <?php selected($tab['permission_type'] ?? '', 'always'); ?>>Immer sichtbar</option>
                                    <option value="acf_field" <?php selected($tab['permission_type'] ?? '', 'acf_field'); ?>>ACF-Feld</option>
                                    <option value="role" <?php selected($tab['permission_type'] ?? '', 'role'); ?>>WordPress-Rolle</option>
                                    <option value="admin" <?php selected($tab['permission_type'] ?? '', 'admin'); ?>>Nur Admins</option>
                                    <option value="custom_callback" <?php selected($tab['permission_type'] ?? '', 'custom_callback'); ?>>Custom</option>
                                </select>
                            </td>
                        </tr>
                        <tr class="dgptm-field-row-acf">
                            <th>ACF-Feld</th>
                            <td>
                                <select class="dgptm-tab-permission-field">
                                    <?php foreach ($acf_fields as $key => $label) : ?>
                                        <option value="<?php echo esc_attr($key); ?>" <?php selected($tab['permission_field'] ?? '', $key); ?>><?php echo esc_html($label); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr class="dgptm-field-row-role">
                            <th>Rollen (kommagetrennt)</th>
                            <td><input type="text" class="dgptm-tab-roles regular-text" value="<?php echo esc_attr(implode(',', $tab['permission_roles'] ?? [])); ?>" placeholder="administrator,editor"></td>
                        </tr>
                        <tr>
                            <th>Zeitfenster Start</th>
                            <td><input type="datetime-local" class="dgptm-tab-datetime-start" value="<?php echo esc_attr($tab['datetime_start'] ?? ''); ?>"></td>
                        </tr>
                        <tr>
                            <th>Zeitfenster Ende</th>
                            <td><input type="datetime-local" class="dgptm-tab-datetime-end" value="<?php echo esc_attr($tab['datetime_end'] ?? ''); ?>"></td>
                        </tr>
                        <tr>
                            <th>Inhalt (HTML)</th>
                            <td>
                                <p class="description" style="margin-bottom:8px;">Optionaler HTML-Inhalt. Wenn leer, wird die Template-Datei verwendet. Shortcodes werden verarbeitet.</p>
                                <?php
                                $editor_id = 'dgptm_tab_content_' . preg_replace('/[^a-z0-9]/', '', $tab['id']);
                                $content_html = $tab['content_html'] ?? '';
                                wp_editor($content_html, $editor_id, [
                                    'textarea_name' => 'tab_content_' . $tab['id'],
                                    'textarea_rows' => 8,
                                    'media_buttons' => true,
                                    'teeny'         => false,
                                    'quicktags'     => true,
                                    'tinymce'       => [
                                        'toolbar1' => 'formatselect,bold,italic,bullist,numlist,link,unlink,wp_adv',
                                        'toolbar2' => 'strikethrough,hr,forecolor,removeformat,charmap,outdent,indent,undo,redo,wp_help',
                                    ],
                                ]);
                                ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <h3 style="margin-top:24px;">Neuen Tab erstellen</h3>
        <div class="dgptm-new-tab-form" style="display:flex;gap:8px;align-items:flex-end;flex-wrap:wrap;margin-bottom:16px;">
            <div>
                <label style="display:block;font-size:12px;margin-bottom:2px;">ID (eindeutig, keine Leerzeichen)</label>
                <input type="text" id="dgptm-new-tab-id" class="regular-text" placeholder="mein-neuer-tab" style="width:180px;">
            </div>
            <div>
                <label style="display:block;font-size:12px;margin-bottom:2px;">Label</label>
                <input type="text" id="dgptm-new-tab-label" class="regular-text" placeholder="Mein neuer Tab" style="width:200px;">
            </div>
            <div>
                <label style="display:block;font-size:12px;margin-bottom:2px;">Uebergeordneter Tab</label>
                <select id="dgptm-new-tab-parent" style="width:180px;">
                    <option value="">-- Kein (Top-Level) --</option>
                    <?php foreach ($all_tabs as $ptab) :
                        if (!empty($ptab['parent_tab'])) continue;
                    ?>
                        <option value="<?php echo esc_attr($ptab['id']); ?>"><?php echo esc_html($ptab['label']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" class="button button-secondary" id="dgptm-add-tab">+ Tab erstellen</button>
        </div>
        <p class="description">Nach dem Erstellen: Template-Datei <code>templates/tabs/tab-{id}.php</code> im Modul-Ordner anlegen.</p>

        <p class="submit">
            <button type="button" class="button button-primary" id="dgptm-save-tabs">Alle Tabs speichern</button>
            <button type="button" class="button" id="dgptm-reset-tabs" style="margin-left: 8px;">Auf Standard zuruecksetzen</button>
        </p>
    </div>

    <!-- Settings -->
    <div class="dgptm-admin-section" data-admin-panel="settings" style="display:none;">
        <h2>Allgemeine Einstellungen</h2>
        <table class="form-table">
            <tr>
                <th>CRM-Cache Dauer (Sekunden)</th>
                <td><input type="number" id="dgptm-cache-ttl" value="<?php echo esc_attr($settings['cache_ttl'] ?? 900); ?>" min="60" max="3600" step="60" class="small-text"> <span class="description">Standard: 900 (15 Min)</span></td>
            </tr>
            <tr>
                <th>Primaerfarbe</th>
                <td><input type="color" id="dgptm-primary-color" value="<?php echo esc_attr($settings['primary_color'] ?? '#005792'); ?>"> <code><?php echo esc_html($settings['primary_color'] ?? '#005792'); ?></code></td>
            </tr>
            <tr>
                <th>Akzentfarbe</th>
                <td><input type="color" id="dgptm-accent-color" value="<?php echo esc_attr($settings['accent_color'] ?? '#bd1622'); ?>"> <code><?php echo esc_html($settings['accent_color'] ?? '#bd1622'); ?></code></td>
            </tr>
            <tr>
                <th>Standard-Tab</th>
                <td>
                    <select id="dgptm-default-tab">
                        <?php foreach ($all_tabs as $tab) : ?>
                            <option value="<?php echo esc_attr($tab['id']); ?>" <?php selected($settings['default_tab'] ?? 'profil', $tab['id']); ?>><?php echo esc_html($tab['label']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th>Mobile Ansicht</th>
                <td>
                    <label>
                        <input type="checkbox" id="dgptm-mobile-dropdown" <?php checked($settings['mobile_dropdown'] ?? true); ?>>
                        Dropdown statt Tab-Leiste auf Mobilgeraeten
                    </label>
                </td>
            </tr>
        </table>
        <p class="submit">
            <button type="button" class="button button-primary" id="dgptm-save-settings">Einstellungen speichern</button>
        </p>
    </div>
</div>
