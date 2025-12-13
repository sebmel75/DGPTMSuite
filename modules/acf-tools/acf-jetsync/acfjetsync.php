<?php
/**
 * Plugin Name: DGPTM - JetEngine to ACF Sync (Version 4.0, mit komplexen Feldern und bidirektionaler Sync)
 * Description: Liest Post-Typen, Taxonomien und Felder (auch komplex) aus wp_jet_post_types / wp_jet_taxonomies und importiert/aktualisiert sie in ACF-Feldgruppen. Bietet bidirektionalen Sync (ACF → JetEngine) sowie Export-/Import-Optionen als JSON.
 * Version: 4.0
 * Author: Dein Name
 */

if ( ! defined('ABSPATH') ) {
    exit; // Sicherheitsabfrage
}

 /**
  * Einfache Log-Funktion, die Einträge in wp-content/jetengine_acf_sync.log schreibt.
  */
function jetacf2_log($message) {
    $log_file = WP_CONTENT_DIR . '/jetengine_acf_sync.log';
    $timestamp = '[' . date('Y-m-d H:i:s') . '] ';
    file_put_contents($log_file, $timestamp . $message . "\n", FILE_APPEND);
}

/**
 * Hilfsfunktion zum (De-)Serialisieren oder JSON-Dekodieren
 */
function jetacf2_maybe_decode($raw) {
    // 1. Versuche JSON-Decode
    $data = json_decode($raw, true);
    if ( json_last_error() === JSON_ERROR_NONE && $data !== null ) {
        return $data;
    }

    // 2. Falls JSON nicht passte, versuche unserialize()
    $maybe = @unserialize($raw);
    if ( $maybe !== false || $raw === 'b:0;' ) {
        return $maybe;
    }

    // 3. Wenn alles fehlschlägt, liefere den Original-String zurück
    return $raw;
}

/**
 * Mapped JetEngine-Feldtypen auf ACF-Feldtypen. Liste nach Bedarf anpassen!
 */
function jetacf2_map_field_type($jet_type) {
    // Beispielhafte Mapping-Tabelle
    $map = array(
        'text'      => 'text',
        'textarea'  => 'textarea',
        'select'    => 'select',
        'checkbox'  => 'checkbox',
        'radio'     => 'radio',
        'date'      => 'date_picker',
        'media'     => 'image',
        'repeater'  => 'repeater',
        'group'     => 'group',
        'flexible'  => 'flexible_content', // Beispiel: In JetEngine heißt es "flexible"?
        'relation'  => 'relationship',     // Bsp. "relation" in JetEngine => "relationship" in ACF?
        // usw.
    );
    return isset($map[$jet_type]) ? $map[$jet_type] : 'text';
}

/**
 * Mapped ACF-Feldtypen zurück auf JetEngine-Feldtypen (für den bidirektionalen Export).
 * Pass das ebenfalls an Deine JetEngine-Typen an!
 */
function jetacf2_map_field_type_reverse($acf_type) {
    $map = array(
        'text'             => 'text',
        'textarea'         => 'textarea',
        'select'           => 'select',
        'checkbox'         => 'checkbox',
        'radio'            => 'radio',
        'date_picker'      => 'date',
        'image'            => 'media',
        'repeater'         => 'repeater',
        'group'            => 'group',
        'flexible_content' => 'flexible',
        'relationship'     => 'relation',
    );
    return isset($map[$acf_type]) ? $map[$acf_type] : 'text';
}

/**
 * REKURSIVE Hilfsfunktion:
 *    JetEngine-Felddaten => ACF-Felddaten
 *
 * $jet_field kann z.B. so aussehen:
 * array(
 *   'type' => 'repeater',
 *   'label' => 'Meine Wiederholung',
 *   'name'  => 'my_repeater',
 *   'sub_fields' => array(
 *       'einzelnes_feld' => array(
 *           'type' => 'text',
 *           'label' => 'Titel',
 *           'name' => 'title',
 *       )
 *   )
 * )
 *
 * Diese Funktion versucht, daraus ein verschachteltes ACF-Feld zu bauen.
 */
function jetacf2_convert_jet_field_to_acf($jet_field, $slug, $parent_name = '') {
    // Standardwerte
    $jet_type  = $jet_field['type']  ?? 'text';
    $jet_label = $jet_field['label'] ?? ($jet_field['name'] ?? 'unnamed_field');
    $jet_name  = $jet_field['name']  ?? sanitize_title_with_dashes($jet_label);

    // ACF-Feldtyp zuordnen
    $acf_type = jetacf2_map_field_type($jet_type);

    // ACF-Key identifizieren
    // => Wir bauen uns eine stabile Kennung aus CPT-Slug, parent_name und feldname
    // => So werden Felder bei erneutem Sync aktualisiert statt neu angelegt.
    $base_key = 'field_jetacf2_' . $slug;
    if ($parent_name) {
        $base_key .= '_' . $parent_name; // Kaskadierung, falls verschachtelt
    }
    $base_key .= '_' . $jet_name;
    $acf_key = sanitize_title_with_dashes($base_key);

    $acf_field = array(
        'key'   => $acf_key,
        'label' => $jet_label,
        'name'  => sanitize_title_with_dashes($jet_name),
        'type'  => $acf_type,
    );

    // Je nach Feldtyp schauen wir, ob es Sub-Felder gibt
    if (in_array($acf_type, array('repeater','group','flexible_content'), true)) {
        // JetEngine könnte "sub_fields" oder "fields" oder ähnliches nutzen, je nach Version.
        // Wir gehen hier von 'sub_fields' aus.
        $sub_fields = $jet_field['sub_fields'] ?? array();

        // Repeater oder Group => ACF erwartet 'sub_fields'
        if ($acf_type === 'repeater' || $acf_type === 'group') {
            $acf_sub_fields = array();
            if (!empty($sub_fields) && is_array($sub_fields)) {
                foreach ($sub_fields as $child_key => $child_jet_field) {
                    $acf_sub_fields[] = jetacf2_convert_jet_field_to_acf($child_jet_field, $slug, $jet_name);
                }
            }
            // Im Repeater/Group-Fall nennt ACF das Feld-Array 'sub_fields'
            $acf_field['sub_fields'] = $acf_sub_fields;
        }

        // Flexible Content => ACF erwartet 'layouts'
        if ($acf_type === 'flexible_content') {
            // Wir nehmen an, JetEngine hat z.B. 'layouts' => array(...) – Das hängt jedoch stark vom Plugin-Aufbau ab.
            // Falls es nur "sub_fields" ohne Layouts gibt, musst Du hier anpassen.
            $layouts = array();

            // Beispiel: $sub_fields hat Keys, die Layouts darstellen:
            // $sub_fields['layout_1'] = array(
            //   'label' => 'Layout 1',
            //   'sub_fields' => array( ...)
            // );
            foreach ($sub_fields as $layout_slug => $layout_def) {
                $layout_label = $layout_def['label'] ?? $layout_slug;
                $layout_fields = $layout_def['sub_fields'] ?? array();

                // Jedes Layout-Array braucht in ACF: 
                //  "key" => "layout_{irgendwas}", "name", "label", "display", "sub_fields"
                // Mindestens "key", "name", "label", "sub_fields"
                $acf_layout_key = 'layout_' . sanitize_title_with_dashes($layout_slug);

                // Sub-Felder rekursiv anlegen
                $acf_layout_sub_fields = array();
                foreach ($layout_fields as $lf_key => $lf) {
                    $acf_layout_sub_fields[] = jetacf2_convert_jet_field_to_acf($lf, $slug, $layout_slug);
                }

                $layouts[$acf_layout_key] = array(
                    'key'        => $acf_layout_key,
                    'name'       => sanitize_title_with_dashes($layout_slug),
                    'label'      => $layout_label,
                    'display'    => 'block', // oder 'row' oder 'table'
                    'sub_fields' => $acf_layout_sub_fields,
                );
            }

            $acf_field['layouts'] = $layouts;
        }
    }

    // Relationship-Feld? Z.B. "relation" -> "relationship".
    // Hier könntest Du weitere Einstellungen vornehmen (z.B. Post-Typen einschränken).
    if ($acf_type === 'relationship') {
        // Falls in JetEngine definierst Du z.B. 'post_type' => array('my_cpt') etc.
        // Dann könntest Du das hier mappen:
        if (!empty($jet_field['post_types']) && is_array($jet_field['post_types'])) {
            $acf_field['post_type'] = $jet_field['post_types'];
        }
    }

    // Ggf. weitere Feld-Parameter wie 'instructions', 'required' etc. übertragen,
    // falls JetEngine sie bereitstellt.
    if (!empty($jet_field['required'])) {
        $acf_field['required'] = 1;
    }

    return $acf_field;
}

/**
 * REKURSIVE Hilfsfunktion:
 *    ACF-Feld => JetEngine-Feld
 *
 * So können wir ACF-Felder wieder zurück in das JetEngine-Format transformieren,
 * soweit sinnvoll. Auch hier kann es Abweichungen je nach JetEngine-Version geben.
 */
function jetacf2_convert_acf_field_to_jet($acf_field) {
    $acf_type = $acf_field['type'];
    $jet_type = jetacf2_map_field_type_reverse($acf_type);

    // Minimaler Datensatz (Anpassen je nach Bedarf!)
    $jet_field = array(
        'type'  => $jet_type,
        'label' => $acf_field['label'],
        'name'  => $acf_field['name'],
    );

    // Repeater/Group
    if ($acf_type === 'repeater' || $acf_type === 'group') {
        // ACF hat 'sub_fields'
        if (!empty($acf_field['sub_fields']) && is_array($acf_field['sub_fields'])) {
            $sub_fields = array();
            foreach ($acf_field['sub_fields'] as $sf) {
                $sub_fields[] = jetacf2_convert_acf_field_to_jet($sf);
            }
            $jet_field['sub_fields'] = $sub_fields;
        }
    }
    // Flexible Content
    elseif ($acf_type === 'flexible_content') {
        // ACF hat 'layouts'
        $layouts = array();
        if (!empty($acf_field['layouts']) && is_array($acf_field['layouts'])) {
            foreach ($acf_field['layouts'] as $layout_key => $layout_def) {
                // Layout-Label
                $layout_label = $layout_def['label'] ?? $layout_key;
                // sub_fields
                $acf_layout_sub_fields = $layout_def['sub_fields'] ?? array();
                $converted_sub_fields = array();
                foreach ($acf_layout_sub_fields as $lf) {
                    $converted_sub_fields[] = jetacf2_convert_acf_field_to_jet($lf);
                }

                // In JetEngine (fiktiv) legen wir das so ab:
                // "sub_fields" => array( ... ), "label" => layout_label
                $layouts[$layout_key] = array(
                    'label'      => $layout_label,
                    'sub_fields' => $converted_sub_fields,
                );
            }
        }
        $jet_field['sub_fields'] = $layouts;
    }
    // Relationship-Feld => "relation"
    elseif ($acf_type === 'relationship') {
        // ACF hat "post_type" => array(...)
        if (!empty($acf_field['post_type'])) {
            $jet_field['post_types'] = $acf_field['post_type'];
        }
    }

    // Required
    if (!empty($acf_field['required'])) {
        $jet_field['required'] = true;
    }

    return $jet_field;
}

/**
 * Kernfunktion für JetEngine → ACF
 */
function jetacf2_sync() {
    global $wpdb;

    jetacf2_log("=== Synchronisation gestartet (v4.0) ===");

    // Prüfen, ob ACF-Pro verfügbar ist.
    if ( ! function_exists('acf_import_field_group') ) {
        jetacf2_log("ACF Pro-Funktion acf_import_field_group() nicht verfügbar. Abbruch!");
        return;
    }

    /*
     * 1) POST-TYPEN aus wp_jet_post_types
     */
    $post_types_table = $wpdb->prefix . 'jet_post_types';
    $table_exists_pt = $wpdb->get_var("SHOW TABLES LIKE '{$post_types_table}'");

    if ( $table_exists_pt ) {
        $rows = $wpdb->get_results("SELECT * FROM {$post_types_table}", ARRAY_A);
        if ( ! empty($rows) ) {
            foreach ($rows as $row) {

                // a) Post Type registrieren (falls noch nicht existiert)
                $slug = ! empty($row['slug']) ? $row['slug'] : false;
                $args_raw = isset($row['args']) ? $row['args'] : '';
                $args = jetacf2_maybe_decode($args_raw);

                if ( $slug && ! post_type_exists($slug) ) {
                    $labels_name = isset($args['labels']['name']) ? $args['labels']['name'] : $slug;

                    register_post_type($slug, array(
                        'label'     => $labels_name,
                        'public'    => true,
                        'show_ui'   => true,
                        'supports'  => array('title', 'editor', 'custom-fields'),
                    ));

                    jetacf2_log("Post-Typ registriert: {$slug}");
                } else {
                    jetacf2_log("Post-Typ existiert bereits oder kein Slug vorhanden: " . print_r($slug, true));
                }

                // b) Meta-Felder importieren => ACF-Feldgruppe
                if ( ! empty($row['meta_fields']) ) {
                    $meta_fields_raw = $row['meta_fields'];
                    $meta_fields = jetacf2_maybe_decode($meta_fields_raw);

                    if ( is_array($meta_fields) && ! empty($meta_fields) ) {
                        $acf_fields = array();

                        // $meta_fields könnte so aussehen: array( 'field_1' => array(...), 'field_2' => array(...))
                        // Wir iterieren darüber.
                        foreach ($meta_fields as $field_key => $field_settings) {
                            $acf_field = jetacf2_convert_jet_field_to_acf($field_settings, $slug);
                            $acf_fields[] = $acf_field;
                        }

                        // Feldgruppe anlegen/aktualisieren
                        $group_key = 'group_jet_cpt_' . $slug;
                        $field_group = array(
                            'key'      => $group_key,
                            'title'    => 'Felder für CPT: ' . $slug,
                            'fields'   => $acf_fields,
                            'location' => array(
                                array(
                                    array(
                                        'param'    => 'post_type',
                                        'operator' => '==',
                                        'value'    => $slug,
                                    )
                                )
                            ),
                        );

                        $result = acf_import_field_group($field_group);
                        if ( $result ) {
                            jetacf2_log("Feldgruppe importiert/aktualisiert für CPT: {$slug}");
                        } else {
                            jetacf2_log("Fehler beim Import der Feldgruppe für CPT: {$slug}");
                        }
                    }
                }
            }
        } else {
            jetacf2_log("Tabelle '{$post_types_table}' ist leer. Keine Post-Typen gefunden.");
        }
    } else {
        jetacf2_log("Tabelle '{$post_types_table}' existiert nicht. Keine Post-Typen zu importieren.");
    }

    /*
     * 2) TAXONOMIEN aus wp_jet_taxonomies
     */
    $tax_table = $wpdb->prefix . 'jet_taxonomies';
    $table_exists_tax = $wpdb->get_var("SHOW TABLES LIKE '{$tax_table}'");

    if ( $table_exists_tax ) {
        $tax_rows = $wpdb->get_results("SELECT * FROM {$tax_table}", ARRAY_A);
        if ( ! empty($tax_rows) ) {
            foreach ($tax_rows as $tax_row) {
                $tax_slug = ! empty($tax_row['slug']) ? $tax_row['slug'] : false;
                $tax_args_raw = isset($tax_row['args']) ? $tax_row['args'] : '';
                $tax_args = jetacf2_maybe_decode($tax_args_raw);
                $attached_pts_raw = isset($tax_row['post_types']) ? $tax_row['post_types'] : '';
                $attached_pts = jetacf2_maybe_decode($attached_pts_raw);

                if ( ! is_array($attached_pts) ) {
                    $attached_pts = array();
                }

                if ( $tax_slug && ! taxonomy_exists($tax_slug) ) {
                    register_taxonomy($tax_slug, $attached_pts, array(
                        'label'        => isset($tax_args['labels']['name']) ? $tax_args['labels']['name'] : $tax_slug,
                        'public'       => true,
                        'show_ui'      => true,
                        'hierarchical' => ! empty($tax_args['hierarchical']),
                    ));
                    jetacf2_log("Taxonomie registriert: {$tax_slug}");
                } else {
                    jetacf2_log("Taxonomie existiert bereits oder kein Slug: " . print_r($tax_slug, true));
                }
            }
        } else {
            jetacf2_log("Tabelle '{$tax_table}' ist leer. Keine Taxonomien gefunden.");
        }
    } else {
        jetacf2_log("Tabelle '{$tax_table}' existiert nicht. Keine Taxonomien zu importieren.");
    }

    jetacf2_log("=== Synchronisation beendet (v4.0) ===");
}

/**
 * Führt die Synchronisation einmal bei Plugin-Aktivierung aus.
 */
register_activation_hook(__FILE__, 'jetacf2_sync');

/**
 * Admin-Menüeinträge hinzufügen
 */
add_action('admin_menu', function() {
    // Untermenü zum manuellen Sync (JetEngine -> ACF)
    add_submenu_page(
        'tools.php',
        'JetEngine to ACF Sync 4.0',
        'JetEngine to ACF Sync 4.0',
        'manage_options',
        'jetengine-to-acf-sync-4',
        'jetacf2_sync_admin_page'
    );

    // Untermenü: Export der JetEngine-ACF-Feldgruppen als JSON
    add_submenu_page(
        'tools.php',
        'Export JetEngine-ACF JSON',
        'Export JetEngine-ACF JSON',
        'manage_options',
        'jetacf2_export_json',
        'jetacf2_export_json_page'
    );

    // Untermenü: Rück-Sync (ACF -> JetEngine)
    add_submenu_page(
        'tools.php',
        'ACF to JetEngine Sync',
        'ACF to JetEngine Sync',
        'manage_options',
        'jetacf2_acf_to_jetengine',
        'jetacf2_acf_to_jetengine_page'
    );
});

/**
 * Admin-Seite mit Button, um jetacf2_sync() manuell auszulösen (JetEngine => ACF)
 */
function jetacf2_sync_admin_page() {
    // Sync auslösen, wenn Button geklickt wurde
    if ( isset($_POST['jetacf2_sync_action']) && check_admin_referer('jetacf2_sync_nonce', 'jetacf2_sync_nonce_field') ) {
        jetacf2_sync();
        echo '<div class="updated"><p><strong>Synchronisation (JetEngine -> ACF) abgeschlossen!</strong> Siehe Log-Datei <code>wp-content/jetengine_acf_sync.log</code>.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>JetEngine to ACF Sync (Version 4.0)</h1>
        <p>Mit einem Klick werden die in JetEngine definierten Post-Typen, Taxonomien und Meta-Felder (inkl. komplexer Felder) in ACF importiert bzw. aktualisiert.</p>
        <form method="post">
            <?php wp_nonce_field('jetacf2_sync_nonce', 'jetacf2_sync_nonce_field'); ?>
            <input type="hidden" name="jetacf2_sync_action" value="1" />
            <button type="submit" class="button button-primary">Synchronisation starten</button>
        </form>
        <p>Details findest Du in <code>wp-content/jetengine_acf_sync.log</code></p>
    </div>
    <?php
}

/**
 * Admin-Seite/Handler zum Export der ACF-Feldgruppen (nur jene mit Prefix "group_jet_cpt_")
 * als JSON-Download.
 */
function jetacf2_export_json_page() {
    // Zeigt dem Admin einen Button an, um den Export als Download auszulösen
    if ( isset($_POST['jetacf2_do_export']) && check_admin_referer('jetacf2_export_nonce', 'jetacf2_export_nonce_field') ) {
        jetacf2_export_json();
    }
    ?>
    <div class="wrap">
        <h1>Export der JetEngine-ACF-Feldgruppen</h1>
        <p>Klicke auf den Button, um alle Feldgruppen, die von JetEngine-to-ACF erstellt wurden, als <code>.json</code>-Datei herunterzuladen.</p>
        <form method="post">
            <?php wp_nonce_field('jetacf2_export_nonce', 'jetacf2_export_nonce_field'); ?>
            <input type="hidden" name="jetacf2_do_export" value="1" />
            <button type="submit" class="button button-primary">Export als JSON herunterladen</button>
        </form>
    </div>
    <?php
}

/**
 * Führt den eigentlichen JSON-Export aus und liefert eine Download-Datei.
 */
function jetacf2_export_json() {
    // Sicherheit: Nur Admins
    if ( ! current_user_can('manage_options') ) {
        wp_die("Keine Berechtigung.");
    }

    if ( ! function_exists('acf_get_field_groups') || ! function_exists('acf_get_fields') ) {
        wp_die("ACF ist nicht verfügbar. Export abgebrochen.");
    }

    // Alle Feldgruppen holen
    $all_groups = acf_get_field_groups();
    $export = array();

    // Nur jene mit Key-Prefix "group_jet_cpt_"
    foreach ($all_groups as $group) {
        if ( strpos($group['key'], 'group_jet_cpt_') === 0 ) {
            $fields = acf_get_fields($group['key']);
            $group['fields'] = $fields;
            $export[] = $group;
        }
    }

    // JSON ausgeben, Download-Header setzen
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename=acf-export-jetengine.json');
    echo json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Admin-Seite (ACF -> JetEngine) mit Button zum Rück-Sync
 */
function jetacf2_acf_to_jetengine_page() {
    // Button geklickt?
    if ( isset($_POST['jetacf2_reverse_sync_action']) && check_admin_referer('jetacf2_reverse_sync_nonce', 'jetacf2_reverse_sync_nonce_field') ) {
        jetacf2_sync_acf_to_jetengine();
        echo '<div class="updated"><p><strong>Synchronisation (ACF -> JetEngine) abgeschlossen!</strong> Siehe Log-Datei <code>wp-content/jetengine_acf_sync.log</code>.</p></div>';
    }
    ?>
    <div class="wrap">
        <h1>ACF to JetEngine Sync</h1>
        <p>Hiermit werden die ACF-Feldgruppen (sofern sie mit <code>group_jet_cpt_</code> beginnen) wieder zurück in JetEngine geschrieben.</p>
        <form method="post">
            <?php wp_nonce_field('jetacf2_reverse_sync_nonce', 'jetacf2_reverse_sync_nonce_field'); ?>
            <input type="hidden" name="jetacf2_reverse_sync_action" value="1" />
            <button type="submit" class="button button-primary">Rück-Sync starten</button>
        </form>
    </div>
    <?php
}

/**
 * Kernfunktion für ACF => JetEngine
 * Läuft alle ACF-Gruppen durch, die zum Prefix "group_jet_cpt_{slug}" gehören,
 * und baut daraus wieder JetEngine-Felder (meta_fields).
 */
function jetacf2_sync_acf_to_jetengine() {
    global $wpdb;

    jetacf2_log("=== Rück-Sync gestartet (ACF -> JetEngine) ===");

    if (!function_exists('acf_get_field_groups') || !function_exists('acf_get_fields')) {
        jetacf2_log("ACF-Funktion nicht vorhanden. Abbruch!");
        return;
    }

    // Alle Gruppen holen, filtern auf 'group_jet_cpt_'
    $all_groups = acf_get_field_groups();
    $groups_to_sync = array_filter($all_groups, function($g) {
        return (strpos($g['key'], 'group_jet_cpt_') === 0);
    });

    // wir brauchen die slug-Teilstelle. 
    // Wenn key = group_jet_cpt_MeinCPT => slug = MeinCPT
    // Achtung: Je nach Benennung evtl. anpassen.
    $post_types_table = $wpdb->prefix . 'jet_post_types';

    foreach ($groups_to_sync as $group) {
        $group_key = $group['key']; // z.B. "group_jet_cpt_meincpt"
        $slug = str_replace('group_jet_cpt_', '', $group_key);

        // Felder holen
        $fields = acf_get_fields($group['key']);
        if (empty($fields)) {
            jetacf2_log("Keine Felder in Feldgruppe {$group_key} gefunden.");
            continue;
        }

        // In JetEngine => array( 'field_1' => array(...), 'field_2' => array(...))
        // Wir nutzen den ACF-Feldname als Key
        $jet_meta_fields = array();
        foreach ($fields as $acf_field) {
            $converted = jetacf2_convert_acf_field_to_jet($acf_field);
            $field_name = $converted['name']; 
            $jet_meta_fields[$field_name] = $converted;
        }

        // In DB speichern
        // Wir gehen davon aus, dass in wp_jet_post_types die Zeile zu unserem CPT bereits existiert.
        // => Dann updaten wir die Spalte meta_fields
        //    Evtl. heißt sie "meta_fields". Falls nicht, passe den Spaltennamen an.
        $encoded_fields = maybe_serialize($jet_meta_fields); 
        // man könnte auch json_encode() verwenden, falls JetEngine das so erwartet.
        // => $encoded_fields = wp_json_encode($jet_meta_fields);

        // Updaten:
        $wpdb->update(
            $post_types_table,
            array('meta_fields' => $encoded_fields),
            array('slug' => $slug),
            array('%s'),
            array('%s')
        );

        if ($wpdb->rows_affected > 0) {
            jetacf2_log("Erfolgreich {$wpdb->rows_affected} Zeilen in wp_jet_post_types für CPT {$slug} aktualisiert (ACF -> JetEngine).");
        } else {
            jetacf2_log("Keine Änderung in wp_jet_post_types für CPT {$slug}. (ggf. Slug nicht gefunden oder identische Werte).");
        }
    }

    jetacf2_log("=== Rück-Sync beendet (ACF -> JetEngine) ===");
}
