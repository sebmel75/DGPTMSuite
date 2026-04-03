<?php
/**
 * Modul: EBCP-Nachweis-Upload mit KI (Claude)
 * Version: 3.10 - Workshop/Kongress-Differenzierung
 * Shortcode: [fobi_nachweis_upload] | [fobi_nachweis_pruefliste]
 * 
 * Changelog v3.10:
 * - ✅ NEU: Differenzierung zwischen Workshop (1 Punkt) und Kongress (4 Punkte)
 * - ✅ NEU: Internationale Kongresse geben 6 Punkte (vorher 8)
 * - ✅ NEU: DGPTM Jahrestagung (Fokustagung Herz) gibt automatisch 6 Punkte
 * - ✅ OPTIMIERUNG: Erweiterte EBCP-Matrix mit getrennten Workshop- und Kongress-Kategorien
 * - ✅ OPTIMIERUNG: Claude-Prompt zur besseren Unterscheidung Workshop vs. Kongress
 * 
 * Changelog v3.9:
 * - ✅ NEU: Frontend-Shortcode [fobi_nachweis_pruefliste] zur Prüfung von Nachweisen
 * - ✅ NEU: Modal mit Nachweis-Details, Attachment-Vorschau (Bilder/PDFs)
 * - ✅ NEU: Genehmigen-Button mit E-Mail-Benachrichtigung an Benutzer
 * - ✅ NEU: Ablehnen-Button mit individuellem Kommentar und E-Mail
 * - ✅ NEU: Alle E-Mail-Templates im Admin editierbar
 * - ✅ NEU: Template-Variablen: {user_name}, {title}, {points}, etc.
 * - ✅ OPTIMIERUNG: Zentrale E-Mail-Template-Funktion für alle Benachrichtigungen
 * - ✅ OPTIMIERUNG: E-Mail-Templates mit benutzerfreundlichem Editor
 * - ✅ OPTIMIERUNG: Berechtigungsprüfung (nur edit_posts kann prüfen)
 * 
 * Changelog v3.8:
 * - ✅ NEU: Detaillierte, spezifische Fehlermeldungen je nach Ablehnungsgrund
 * - ✅ NEU: Benutzerfreundlicher Tabellen-Editor für Kategorie-Matrix im Admin
 * - ✅ NEU: Deutsche Bezeichnungen werden in Fortbildungsliste eingetragen
 * - ✅ NEU: Hilfsfunktionen für Kategorie-Key und Label-Ermittlung
 * - ✅ NEU: Zusätzliche Meta-Daten für interne Kategorieverwaltung
 * - ✅ OPTIMIERUNG: Kategorien sind nach Gruppen sortiert (Passiv, Aktiv, Publikationen, ECTS)
 * - ✅ OPTIMIERUNG: Punkteberechnung vereinfacht und zentralisiert
 * - ✅ OPTIMIERUNG: Erfolgs-Nachricht zeigt deutsche Bezeichnung statt raw-category
 * 
 * Changelog v3.7:
 * - ✅ OPTIMIERUNG: Konfidenz unter 50% wird sofort mit Fehlermeldung abgelehnt
 * - ✅ OPTIMIERUNG: Uploads ohne Teilnehmername werden strikt abgelehnt
 * - ✅ OPTIMIERUNG: Fortbildungsflyer (ohne persönlichen Nachweis) werden erkannt und abgelehnt
 * - ✅ OPTIMIERUNG: Verbesserter Namensvergleich mit Fuzzy-Matching (75% statt 70%)
 * - ✅ OPTIMIERUNG: Teilnamen-Matching verbessert (60% der Namensbestandteile müssen matchen)
 * - ✅ OPTIMIERUNG: Substring-Matching für Namensteile hinzugefügt
 * - ✅ OPTIMIERUNG: Suspicious-Schwellenwert von 60% auf 70% erhöht
 * - ✅ OPTIMIERUNG: Claude-Prompt verbessert zur besseren Unterscheidung Flyer vs. Nachweis
 * 
 * Changelog v3.6:
 * - ✅ KRITISCHER FIX: Modellname korrigiert (claude-sonnet-4-5-20250929 statt 4.5)
 * - ✅ Fehler "model was not found" behoben
 * 
 * Changelog v3.5:
 * - ✅ "Die Daten stimmen nicht" Button im Erfolgsfenster
 * - ✅ Kommentarfeld für Korrekturanfragen
 * - ✅ E-Mail-Benachrichtigung an Admin mit allen Details
 * - ✅ Korrekturanfragen werden als Post Meta gespeichert
 * - ✅ Reply-To auf Benutzer-E-Mail gesetzt
 * 
 * Changelog v3.4:
 * - ✅ Backend-Link wird nur Benutzern mit Bearbeitungsrechten angezeigt
 * - ✅ Berechtigungsprüfung: current_user_can('edit_post')
 * 
 * Changelog v3.3:
 * - ✅ PDF-Support für Claude behoben (als "document" statt "image")
 * - ✅ Bildformat-Validierung hinzugefügt (JPEG, PNG, GIF, WebP)
 * - ✅ Fehlermeldungen ins Deutsche übersetzt
 * - ✅ Benutzerfreundliche Fehlertexte bei API-Problemen
 * 
 * Changelog v3.2:
 * - ✅ Event-Verifizierung deaktiviert (alle Events als gültig)
 * - ✅ "Jahrestagung" wird als Kongress erkannt
 * - ✅ ACF-Feldnamen korrigiert (user, date, location, type, points, attachements)
 * - ✅ Aktueller Benutzer wird korrekt zugeordnet
 * - ✅ Datum wird im Format Y-m-d gespeichert
 * - ✅ Fortbildungseinträge werden jetzt korrekt erstellt
 * 
 * Changelog v3.1:
 * - ✅ OpenAI entfernt - nur noch Claude-Varianten verfügbar
 * - ✅ Nonce-Bug beim Speichern behoben
 * - ✅ Vereinfachtes Interface
 * 
 * Changelog v3.0:
 * - ✅ Nur Claude und OpenAI Vision (kein OCR mehr!)
 * - ✅ Claude Sonnet 4.5 (neueste Version)
 * - ✅ Save-Bug behoben
 * - ✅ Vereinfachtes Interface
 */

if ( ! defined('ABSPATH') ) { exit; }

/* ============================================================
 * Optionen & Defaults
 * ============================================================ */
define('FOBI_EBCP_OPTION_KEY', 'fobi_ebcp_settings');

/* ============================================================
 * Geschuetzter Upload-Ordner fuer Fortbildungsnachweise
 * ============================================================ */

/**
 * Leitet WordPress-Uploads in geschuetzten Unterordner um
 */
function fobi_ebcp_protected_upload_dir($uploads) {
    $protected_dir = '/fobi-protected';
    $uploads['path']   = $uploads['basedir'] . $protected_dir;
    $uploads['url']    = $uploads['baseurl'] . $protected_dir;
    $uploads['subdir'] = $protected_dir;

    // Ordner erstellen falls nicht vorhanden
    if (!file_exists($uploads['path'])) {
        wp_mkdir_p($uploads['path']);
    }

    // .htaccess erstellen falls nicht vorhanden
    $htaccess = $uploads['path'] . '/.htaccess';
    if (!file_exists($htaccess)) {
        file_put_contents($htaccess, "# Direkter Zugriff verweigert — nur ueber PHP-Handler\nDeny from all\n");
    }

    // index.php als zusaetzlichen Schutz
    $index = $uploads['path'] . '/index.php';
    if (!file_exists($index)) {
        file_put_contents($index, "<?php // Silence is golden.\n");
    }

    return $uploads;
}

/**
 * Geschuetzter Download-Handler fuer Fortbildungsnachweise
 * Nur eingeloggte User mit edit_posts oder der Besitzer duerfen downloaden
 */
add_action('init', function() {
    if (empty($_GET['fobi_download']) || empty($_GET['attachment_id'])) return;

    $attachment_id = intval($_GET['attachment_id']);
    if (!$attachment_id) return;

    // Berechtigung pruefen
    if (!is_user_logged_in()) {
        wp_die('Bitte melden Sie sich an.', 'Zugriff verweigert', ['response' => 403]);
    }

    $user_id = get_current_user_id();
    $can_download = false;

    // Admins/Editoren duerfen immer
    if (current_user_can('edit_posts')) {
        $can_download = true;
    }

    // Besitzer des Fortbildungs-Posts darf auch
    if (!$can_download) {
        $parent_id = wp_get_post_parent_id($attachment_id);
        if ($parent_id) {
            $parent = get_post($parent_id);
            if ($parent && $parent->post_type === 'fortbildung') {
                // ACF User-Feld pruefen
                $fobi_user = function_exists('get_field') ? get_field('user', $parent_id) : null;
                $fobi_user_id = 0;
                if (is_array($fobi_user) && isset($fobi_user['ID'])) $fobi_user_id = $fobi_user['ID'];
                elseif (is_numeric($fobi_user)) $fobi_user_id = intval($fobi_user);

                if ($fobi_user_id === $user_id || intval($parent->post_author) === $user_id) {
                    $can_download = true;
                }
            }
        }
    }

    if (!$can_download) {
        wp_die('Keine Berechtigung.', 'Zugriff verweigert', ['response' => 403]);
    }

    $filepath = get_attached_file($attachment_id);
    if (!$filepath || !file_exists($filepath)) {
        wp_die('Datei nicht gefunden.', 'Fehler', ['response' => 404]);
    }

    $mime = get_post_mime_type($attachment_id) ?: 'application/octet-stream';
    $filename = basename($filepath);

    header('Content-Type: ' . $mime);
    header('Content-Disposition: inline; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filepath));
    header('Cache-Control: private, max-age=3600');
    readfile($filepath);
    exit;
});

/**
 * URLs von geschuetzten Attachments auf den Download-Handler umleiten
 */
add_filter('wp_get_attachment_url', function($url, $attachment_id) {
    $filepath = get_attached_file($attachment_id);
    if ($filepath && strpos($filepath, '/fobi-protected/') !== false) {
        return add_query_arg([
            'fobi_download' => '1',
            'attachment_id' => $attachment_id,
        ], home_url('/'));
    }
    return $url;
}, 10, 2);

function fobi_ebcp_default_settings() {
    return array(
        // Frontend
        'enable_upload' => '1',
        'max_file_mb'   => 12,
        'allowed_mimes' => array('application/pdf','image/jpeg','image/png'),

        // Moderation
        'auto_approve_min_conf' => 0.85,
        'store_proof_as_attachment' => '1',
        
        // Benachrichtigungen
        'notify_on_suspicious' => '1',
        'notification_email' => get_option('admin_email'),
        'notification_subject' => 'Unklaren Fortbildungsnachweis eingereicht',
        
        // E-Mail-Templates
        'email_suspicious_body' => "Verdächtiger Fortbildungsnachweis:\n\nBenutzer: {user_name} ({user_email})\nErwarteter Name: {expected_name}\n\nExtrahierte Daten:\n- Teilnehmer: {participant}\n- Titel: {title}\n- Ort: {location}\n- Datum: {date}\n- Kategorie: {category}\n- Konfidenz: {confidence}%\n\nBitte manuell prüfen:\n{edit_link}",
        
        'email_correction_subject' => 'Korrekturanfrage: Fortbildungsnachweis',
        'email_correction_body' => "Korrekturanfrage von Benutzer:\n\nBenutzer: {user_name} ({user_email})\nFortbildung: {title}\n\nKommentar:\n{comment}\n\n=== AKTION ERFORDERLICH ===\nBitte prüfen Sie die Fortbildung im WordPress Backend:\n{edit_link}",
        
        'email_approved_subject' => 'Ihr Fortbildungsnachweis wurde genehmigt',
        'email_approved_body' => "Hallo {user_firstname},\n\nIhr Fortbildungsnachweis wurde geprüft und genehmigt:\n\n📄 Titel: {title}\n📍 Ort: {location}\n📅 Datum: {date}\n🏷️ Art: {category}\n⭐ Punkte: {points}\n\nVielen Dank für Ihre Einreichung!\n\nMit freundlichen Grüßen\nIhr DGPTM-Team",
        
        'email_rejected_subject' => 'Ihr Fortbildungsnachweis wurde abgelehnt',
        'email_rejected_body' => "Hallo {user_firstname},\n\nIhr Fortbildungsnachweis wurde geprüft und leider abgelehnt:\n\n📄 Titel: {title}\n\n❌ Ablehnungsgrund:\n{reject_comment}\n\nBitte reichen Sie einen korrigierten Nachweis ein oder kontaktieren Sie uns bei Fragen.\n\nMit freundlichen Grüßen\nIhr DGPTM-Team",

        // KI-Modus
        'ai_mode' => 'claude', // claude|off (openai_vision entfernt in v3.1)
        
        // Claude AI
        'claude_api_key' => '',
        'claude_model' => 'claude-sonnet-4-5-20250929', // Neueste Version! (KORREKTUR: Bindestrich statt Punkt)
        'claude_max_tokens' => 2048,
        
        // OpenAI Vision
        'openai_vision_api_key' => '',
        'openai_vision_model' => 'gpt-4o',
        'openai_vision_max_tokens' => 2048,

        // EBCP-Matrix v3.11: Workshop 3 Punkte, aktiv +1
        'ebcp_mapping_json' => json_encode(array(
            // === PASSIVE TEILNAHME — Workshops (3 Punkte) ===
            array('key'=>'passive_workshop_inhouse',       'label'=>'In-house Workshop (passiv)',         'points'=>3),
            array('key'=>'passive_workshop_national',      'label'=>'Nationaler Workshop (passiv)',       'points'=>3),
            array('key'=>'passive_workshop_international', 'label'=>'Internationaler Workshop (passiv)',  'points'=>3),

            // === PASSIVE TEILNAHME — Webinar/Seminar/Kongress ===
            array('key'=>'passive_webinar',                'label'=>'Webinar (passiv)',                   'points'=>1),
            array('key'=>'passive_seminar_national',       'label'=>'Nationales Seminar (passiv)',        'points'=>4),
            array('key'=>'passive_kongress_national',      'label'=>'Nationaler Kongress (passiv)',       'points'=>4),
            array('key'=>'passive_kongress_international', 'label'=>'Internationaler Kongress (passiv)',  'points'=>6),
            array('key'=>'passive_dgptm_jahrestagung',     'label'=>'DGPTM Jahrestagung (Fokustagung Herz)', 'points'=>6),

            // === AKTIVE TEILNAHME — Workshops (4 Punkte = 3+1) ===
            array('key'=>'active_workshop_inhouse',  'label'=>'In-house Workshop (aktiv/Referent)',  'points'=>4),
            array('key'=>'active_workshop_national', 'label'=>'Nationaler Workshop (aktiv/Referent)','points'=>4),
            array('key'=>'active_workshop_international','label'=>'Internationaler Workshop (aktiv/Referent)','points'=>4),

            // === AKTIVE TEILNAHME — Sonstige ===
            array('key'=>'active_inhouse',        'label'=>'In-house Vortrag',                    'points'=>2),
            array('key'=>'active_webinar',        'label'=>'Webinar (aktiv/Referent)',             'points'=>2),
            array('key'=>'active_national_talk',  'label'=>'Nationaler Vortrag',                  'points'=>3),
            array('key'=>'active_national_mod',   'label'=>'Moderator national',                  'points'=>3),
            array('key'=>'active_intl_talk',      'label'=>'Internationaler Vortrag',             'points'=>5),
            array('key'=>'active_intl_mod',       'label'=>'Moderator international',             'points'=>5),

            // === PUBLIKATIONEN ===
            array('key'=>'pub_abstract',          'label'=>'Publizierter Abstract',               'points'=>1),
            array('key'=>'pub_no_editorial',      'label'=>'Zeitschrift ohne Editorial Policy',   'points'=>4),
            array('key'=>'pub_with_editorial',    'label'=>'Zeitschrift mit Editorial Policy',    'points'=>8),

            // === ECTS ===
            array('key'=>'ects_per_credit',       'label'=>'ECTS pro Credit (relevantes Fach)',   'points'=>1),
        ), JSON_UNESCAPED_UNICODE),

        'ebcp_international_list' => json_encode(array(
            'AACP','AATS','AHA','AMSECT','ASAIO','BelSECT','CROSECT','EACTA','EACTS','EBCP','ESAO','ESCS','FECECT',
            'ISCTS','ScanSECT','SCA','STS','CREF','Euro-ELSO','IMAD','ISMICS','ISHLT','WSCTS','NATA','MIETCIS','ECPR Prague School',
        ), JSON_UNESCAPED_UNICODE)
    );
}

function fobi_ebcp_get_settings() {
    return wp_parse_args( get_option(FOBI_EBCP_OPTION_KEY, array()), fobi_ebcp_default_settings() );
}

/* ============================================================
 * Admin-Seite: Einstellungen
 * ============================================================ */
// Meta-Box: KI-Analyse + Neubewertung im Post-Editor
add_action('add_meta_boxes', function(){
    add_meta_box(
        'fobi_ebcp_ai_metabox',
        'KI-Analyse & Neubewertung',
        'fobi_ebcp_render_ai_metabox',
        'fortbildung',
        'normal',
        'high'
    );
});

function fobi_ebcp_render_ai_metabox($post) {
    $post_id = $post->ID;
    $ai_response = get_post_meta($post_id, '_ebcp_ai_response', true);
    $ai_confidence = get_post_meta($post_id, '_ebcp_ai_confidence', true);
    $ai_category = get_post_meta($post_id, '_ebcp_category_key', true);
    $ai_doc_type = get_post_meta($post_id, '_ebcp_doc_type', true);
    $reevaluated = get_post_meta($post_id, '_ebcp_reevaluated_at', true);

    // Pruefe ob ein Attachment vorhanden ist
    $has_attachment = false;
    if (function_exists('get_field')) {
        $att = get_field('attachements', $post_id);
        $has_attachment = !empty($att);
    }

    echo '<div id="fobi-ai-metabox">';

    if ($ai_response) {
        echo '<h4 style="margin-top:0;">Letzte KI-Analyse' . ($reevaluated ? ' (Neubewertung: ' . esc_html($reevaluated) . ')' : '') . '</h4>';
        echo '<table class="widefat striped" style="margin-bottom:12px;"><tbody>';
        echo '<tr><th style="width:120px;">Konfidenz</th><td><strong>' . ($ai_confidence ? intval($ai_confidence * 100) . '%' : 'k.A.') . '</strong></td></tr>';
        echo '<tr><th>Kategorie-Key</th><td><code>' . esc_html($ai_category ?: 'k.A.') . '</code></td></tr>';
        echo '<tr><th>Dokumenttyp</th><td><code>' . esc_html($ai_doc_type ?: 'k.A.') . '</code></td></tr>';
        $vnr = function_exists('get_field') ? get_field('vnr', $post_id) : get_post_meta($post_id, 'vnr', true);
        if ($vnr) {
            echo '<tr><th>VNR</th><td><code>' . esc_html($vnr) . '</code></td></tr>';
        }
        echo '</tbody></table>';

        echo '<details style="margin-bottom:12px;"><summary style="cursor:pointer;font-size:12px;color:#0073aa;">Rohe KI-Antwort anzeigen</summary>';
        echo '<pre style="background:#f8f9fa;padding:8px;border:1px solid #ddd;border-radius:4px;font-size:11px;white-space:pre-wrap;max-height:300px;overflow:auto;">' . esc_html($ai_response) . '</pre>';
        echo '</details>';
    } else {
        echo '<p style="color:#888;font-style:italic;">Keine KI-Analyse vorhanden.</p>';
    }

    if ($has_attachment) {
        $nonce = wp_create_nonce('fobi_pruefliste');
        echo '<button type="button" id="fobi-reevaluate-backend" class="button button-primary" data-post-id="' . esc_attr($post_id) . '" data-nonce="' . esc_attr($nonce) . '">🔄 KI-Neubewertung starten</button>';
        echo '<span id="fobi-reevaluate-status" style="margin-left:10px;"></span>';

        echo '<script>
        jQuery(function($){
            $("#fobi-reevaluate-backend").on("click", function(){
                var $btn = $(this);
                var $status = $("#fobi-reevaluate-status");
                $btn.prop("disabled", true).text("⏳ KI analysiert...");
                $status.text("").css("color","");

                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "fobi_reevaluate_nachweis",
                        post_id: $btn.data("post-id"),
                        nonce: $btn.data("nonce")
                    },
                    timeout: 90000,
                    success: function(res){
                        $btn.prop("disabled", false).text("🔄 KI-Neubewertung starten");
                        if(res.success){
                            $status.text("✅ " + res.data.message).css("color","#46b450");
                            setTimeout(function(){ location.reload(); }, 2000);
                        } else {
                            var errMsg = (res.data && res.data.message) ? res.data.message : (typeof res.data === "string" ? res.data : "Fehler");
                            var errDetail = (res.data && res.data.error_detail) ? "\n\nAPI-Antwort:\n" + res.data.error_detail : "";
                            var errRaw = (res.data && res.data.raw) ? "\n\nRaw:\n" + JSON.stringify(res.data.raw) : "";
                            $status.html("❌ " + errMsg).css("color","#dc3232");
                            if(errDetail || errRaw) console.error("[Fobi Reevaluate]", errMsg, errDetail, errRaw);
                            alert("❌ " + errMsg + errDetail.substring(0, 500));
                        }
                    },
                    error: function(){
                        $btn.prop("disabled", false).text("🔄 KI-Neubewertung starten");
                        $status.text("❌ Verbindungsfehler").css("color","#dc3232");
                    }
                });
            });
        });
        </script>';
    } else {
        echo '<p style="color:#999;font-size:12px;">Kein Nachweis-Dokument hinterlegt — Neubewertung nicht moeglich.</p>';
    }

    echo '</div>';
}

/* ============================================================
 * Migration: Bestehende Fortbildungs-Attachments in geschuetzten Ordner verschieben
 * Aufruf ueber WP-Admin → Fortbildungen → Upload-Einstellungen → "Dateien schuetzen"
 * ============================================================ */
add_action('wp_ajax_fobi_migrate_protected', function(){
    check_ajax_referer('fobi_migrate_protected', 'nonce');

    if (!current_user_can('manage_options')) {
        wp_send_json_error('Keine Berechtigung.');
    }

    $upload_dir = wp_upload_dir();
    $protected_path = $upload_dir['basedir'] . '/fobi-protected';

    // Geschuetzten Ordner erstellen
    if (!file_exists($protected_path)) {
        wp_mkdir_p($protected_path);
    }
    if (!file_exists($protected_path . '/.htaccess')) {
        file_put_contents($protected_path . '/.htaccess', "Deny from all\n");
    }
    if (!file_exists($protected_path . '/index.php')) {
        file_put_contents($protected_path . '/index.php', "<?php // Silence is golden.\n");
    }

    // Alle Fortbildungen mit Attachments finden
    $posts = get_posts([
        'post_type' => 'fortbildung',
        'posts_per_page' => -1,
        'post_status' => 'any',
    ]);

    $migrated = 0;
    $skipped = 0;
    $errors = [];

    foreach ($posts as $post) {
        $att_field = function_exists('get_field') ? get_field('attachements', $post->ID) : null;

        // Attachment-ID und Dateipfad ermitteln (ACF gibt URL, ID oder Array zurueck)
        $att_id = 0;
        $old_path = '';

        if (is_numeric($att_field)) {
            $att_id = intval($att_field);
            $old_path = get_attached_file($att_id);
        } elseif (is_array($att_field) && isset($att_field['ID'])) {
            $att_id = intval($att_field['ID']);
            $old_path = get_attached_file($att_id);
        } elseif (is_string($att_field) && filter_var($att_field, FILTER_VALIDATE_URL)) {
            // URL → in lokalen Pfad umwandeln
            $upload_base_url = $upload_dir['baseurl'];
            if (strpos($att_field, $upload_base_url) === 0) {
                $relative = substr($att_field, strlen($upload_base_url));
                $old_path = $upload_dir['basedir'] . $relative;
            }
            // Attachment-ID ueber URL finden
            $att_id = attachment_url_to_postid($att_field);
        }

        if (!$old_path || !file_exists($old_path)) {
            $skipped++;
            continue;
        }

        // Schon im geschuetzten Ordner?
        if (strpos($old_path, '/fobi-protected/') !== false) {
            $skipped++;
            continue;
        }

        // Neue Position
        $filename = basename($old_path);
        $new_path = $protected_path . '/' . $filename;

        // Dateiname-Kollision vermeiden
        $i = 1;
        while (file_exists($new_path)) {
            $info = pathinfo($filename);
            $new_path = $protected_path . '/' . $info['filename'] . '-' . $i . '.' . ($info['extension'] ?? 'pdf');
            $i++;
        }

        // Datei verschieben
        if (rename($old_path, $new_path)) {
            // WordPress Attachment-Metadaten aktualisieren
            if ($att_id) {
                update_attached_file($att_id, $new_path);
            }
            // ACF-Feld aktualisieren wenn es eine URL war
            if (is_string($att_field) && filter_var($att_field, FILTER_VALIDATE_URL)) {
                $new_url = $upload_dir['baseurl'] . '/fobi-protected/' . basename($new_path);
                if (function_exists('update_field')) {
                    update_field('attachements', $new_url, $post->ID);
                }
            }
            $migrated++;
        } else {
            $errors[] = 'Fehler beim Verschieben: ' . basename($old_path);
        }
    }

    wp_send_json_success([
        'message' => sprintf('%d Dateien geschuetzt, %d uebersprungen, %d Fehler.', $migrated, $skipped, count($errors)),
        'migrated' => $migrated,
        'skipped' => $skipped,
        'errors' => $errors,
    ]);
});

add_action('admin_menu', function(){
    // Unter Fortbildungen CPT
    add_submenu_page(
        'edit.php?post_type=fortbildung',
        'Fortbildungsnachweis-Upload Einstellungen',
        'Upload-Einstellungen',
        'manage_options',
        'fobi-ebcp-settings',
        'fobi_ebcp_settings_page_render'
    );
});

function fobi_ebcp_settings_page_render(){
    if( ! current_user_can('manage_options')) wp_die('Keine Berechtigung.');
    $s = fobi_ebcp_get_settings();

    // Speichern
    if( isset($_POST['fobi_ebcp_save']) && check_admin_referer('fobi_ebcp_save_action', 'fobi_ebcp_nonce') ){
        $new_settings = array();
        
        // Allgemein
        $new_settings['enable_upload'] = isset($_POST['enable_upload']) ? '1' : '0';
        $new_settings['max_file_mb'] = max(1, intval($_POST['max_file_mb']));
        
        $allowed_m = isset($_POST['allowed_mimes']) ? (array)$_POST['allowed_mimes'] : array();
        $new_settings['allowed_mimes'] = array_map('sanitize_text_field', $allowed_m);
        
        $new_settings['auto_approve_min_conf'] = min(1.0, max(0.5, floatval($_POST['auto_approve_min_conf'])));
        $new_settings['store_proof_as_attachment'] = isset($_POST['store_proof_as_attachment']) ? '1' : '0';
        
        // Benachrichtigungen
        $new_settings['notify_on_suspicious'] = isset($_POST['notify_on_suspicious']) ? '1' : '0';
        $new_settings['notification_email'] = sanitize_email($_POST['notification_email']);
        $new_settings['notification_subject'] = sanitize_text_field($_POST['notification_subject']);
        
        // E-Mail-Templates
        $new_settings['email_suspicious_body'] = sanitize_textarea_field($_POST['email_suspicious_body']);
        $new_settings['email_correction_subject'] = sanitize_text_field($_POST['email_correction_subject']);
        $new_settings['email_correction_body'] = sanitize_textarea_field($_POST['email_correction_body']);
        $new_settings['email_approved_subject'] = sanitize_text_field($_POST['email_approved_subject']);
        $new_settings['email_approved_body'] = sanitize_textarea_field($_POST['email_approved_body']);
        $new_settings['email_rejected_subject'] = sanitize_text_field($_POST['email_rejected_subject']);
        $new_settings['email_rejected_body'] = sanitize_textarea_field($_POST['email_rejected_body']);
        
        // KI-Modus
        $new_settings['ai_mode'] = in_array($_POST['ai_mode'], array('claude','off')) ? $_POST['ai_mode'] : 'claude';
        
        // Claude
        $new_settings['claude_api_key'] = sanitize_text_field($_POST['claude_api_key']);
        $new_settings['claude_model'] = sanitize_text_field($_POST['claude_model']);
        $new_settings['claude_max_tokens'] = max(500, intval($_POST['claude_max_tokens']));
        
        // OpenAI Vision
        $new_settings['openai_vision_api_key'] = sanitize_text_field($_POST['openai_vision_api_key']);
        $new_settings['openai_vision_model'] = sanitize_text_field($_POST['openai_vision_model']);
        $new_settings['openai_vision_max_tokens'] = max(500, intval($_POST['openai_vision_max_tokens']));
        
        // EBCP-Matrix: Prüfe zuerst, ob Tabellendaten vorhanden sind
        if( isset($_POST['matrix_label']) && isset($_POST['matrix_points']) && is_array($_POST['matrix_label']) ){
            // Tabellen-Editor wurde verwendet
            $matrix_data = array();
            foreach($_POST['matrix_label'] as $key => $label){
                $points = isset($_POST['matrix_points'][$key]) ? floatval($_POST['matrix_points'][$key]) : 0;
                $matrix_data[] = array(
                    'key' => sanitize_text_field($key),
                    'label' => sanitize_text_field($label),
                    'points' => $points
                );
            }
            $new_settings['ebcp_mapping_json'] = json_encode($matrix_data, JSON_UNESCAPED_UNICODE);
        } elseif( !empty($_POST['ebcp_mapping_json_manual']) ){
            // Manueller JSON-Editor wurde verwendet (überschreibt Tabelle)
            $map_json = wp_unslash($_POST['ebcp_mapping_json_manual']);
            $test_decode = json_decode($map_json, true);
            if(json_last_error() === JSON_ERROR_NONE && is_array($test_decode)){
                $new_settings['ebcp_mapping_json'] = $map_json;
            } else {
                $new_settings['ebcp_mapping_json'] = $s['ebcp_mapping_json'];
            }
        } else {
            // Fallback: Alte Daten behalten
            $new_settings['ebcp_mapping_json'] = $s['ebcp_mapping_json'];
        }
        
        // Internationale Meetings Liste
        $intl_json = wp_unslash($_POST['ebcp_international_list']);
        $test_decode = json_decode($intl_json, true);
        if(json_last_error() === JSON_ERROR_NONE && is_array($test_decode)){
            $new_settings['ebcp_international_list'] = $intl_json;
        } else {
            $new_settings['ebcp_international_list'] = $s['ebcp_international_list'];
        }
        
        update_option(FOBI_EBCP_OPTION_KEY, $new_settings);
        $s = $new_settings; // Aktualisiere für Anzeige
        echo '<div class="notice notice-success"><p>✅ Einstellungen gespeichert.</p></div>';
    }

    ?>
    <div class="wrap">
        <h1>EBCP-Nachweis-Upload mit KI</h1>
		<h3>Mit der Einreichung stimme ich zu, dass mein Nachweis zu Claude.ai hochgeladen wird.</h3>
        <p class="description">Automatische Analyse von Fortbildungsnachweisen mit Claude Sonnet 4.5 von Anthropic</p>
        
        <form method="post">
            <?php wp_nonce_field('fobi_ebcp_save_action', 'fobi_ebcp_nonce'); ?>
            
            <h2 class="title">⚙️ Allgemein</h2>
            <table class="form-table">
                <tr>
                    <th>Upload aktiv</th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_upload" value="1" <?php checked($s['enable_upload'],'1'); ?>>
                            Shortcode [fobi_nachweis_upload] erlauben
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Max. Dateigröße</th>
                    <td><input type="number" name="max_file_mb" value="<?php echo esc_attr($s['max_file_mb']); ?>" min="1" step="1"> MB</td>
                </tr>
                <tr>
                    <th>Erlaubte Dateitypen</th>
                    <td>
                        <?php
                        $mimes_all = array('application/pdf'=>'PDF','image/jpeg'=>'JPEG/JPG','image/png'=>'PNG');
                        foreach($mimes_all as $mime=>$label){
                            $chk = in_array($mime, $s['allowed_mimes'], true) ? 'checked' : '';
                            echo '<label style="margin-right:12px;"><input type="checkbox" name="allowed_mimes[]" value="'.esc_attr($mime).'" '.$chk.'> '.esc_html($label).'</label>';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <th>Automatische Freigabe</th>
                    <td>
                        <label>ab Konfidenz ≥ <input type="number" name="auto_approve_min_conf" min="0.5" max="1" step="0.01" value="<?php echo esc_attr($s['auto_approve_min_conf']); ?>"></label>
                        <p class="description">Empfohlen: 0.85 (85%)</p>
                        <label>
                            <input type="checkbox" name="store_proof_as_attachment" value="1" <?php checked($s['store_proof_as_attachment'],'1'); ?>>
                            Originalbeleg als Attachment speichern
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Dateischutz</th>
                    <td>
                        <p class="description">Neue Uploads werden automatisch im geschuetzten Ordner <code>fobi-protected/</code> gespeichert (kein direkter HTTP-Zugriff). Bestehende Dateien wurden migriert.</p>
                        </script>
                    </td>
                </tr>
            </table>

            <h2 class="title">📧 Benachrichtigungen</h2>
            <table class="form-table">
                <tr>
                    <th>Bei verdächtigen Uploads</th>
                    <td>
                        <label>
                            <input type="checkbox" name="notify_on_suspicious" value="1" <?php checked($s['notify_on_suspicious'],'1'); ?>>
                            E-Mail senden bei suspekten Nachweisen
                        </label>
                    </td>
                </tr>
                <tr>
                    <th>Empfänger-E-Mail</th>
                    <td><input type="email" name="notification_email" value="<?php echo esc_attr($s['notification_email']); ?>" class="regular-text"></td>
                </tr>
            </table>

            <h2 class="title">✉️ E-Mail-Templates</h2>
            <p class="description">Passen Sie die E-Mail-Texte an. Verfügbare Platzhalter: <code>{user_name}</code>, <code>{user_firstname}</code>, <code>{user_email}</code>, <code>{expected_name}</code>, <code>{participant}</code>, <code>{title}</code>, <code>{location}</code>, <code>{date}</code>, <code>{category}</code>, <code>{points}</code>, <code>{confidence}</code>, <code>{comment}</code>, <code>{reject_comment}</code>, <code>{edit_link}</code></p>
            
            <h3>1. Verdächtiger Nachweis (an Admin)</h3>
            <table class="form-table">
                <tr>
                    <th>Betreff</th>
                    <td><input type="text" name="notification_subject" value="<?php echo esc_attr($s['notification_subject']); ?>" class="large-text"></td>
                </tr>
                <tr>
                    <th>E-Mail-Text</th>
                    <td>
                        <textarea name="email_suspicious_body" rows="10" class="large-text code"><?php echo esc_textarea($s['email_suspicious_body']); ?></textarea>
                        <p class="description">Wird an Admin gesendet, wenn ein Nachweis als verdächtig markiert wird.</p>
                    </td>
                </tr>
            </table>

            <h3>2. Korrekturanfrage (an Admin)</h3>
            <table class="form-table">
                <tr>
                    <th>Betreff</th>
                    <td><input type="text" name="email_correction_subject" value="<?php echo esc_attr($s['email_correction_subject']); ?>" class="large-text"></td>
                </tr>
                <tr>
                    <th>E-Mail-Text</th>
                    <td>
                        <textarea name="email_correction_body" rows="10" class="large-text code"><?php echo esc_textarea($s['email_correction_body']); ?></textarea>
                        <p class="description">Wird an Admin gesendet, wenn ein Benutzer "Die Daten stimmen nicht" anklickt.</p>
                    </td>
                </tr>
            </table>

            <h3>3. Nachweis genehmigt (an Benutzer)</h3>
            <table class="form-table">
                <tr>
                    <th>Betreff</th>
                    <td><input type="text" name="email_approved_subject" value="<?php echo esc_attr($s['email_approved_subject']); ?>" class="large-text"></td>
                </tr>
                <tr>
                    <th>E-Mail-Text</th>
                    <td>
                        <textarea name="email_approved_body" rows="10" class="large-text code"><?php echo esc_textarea($s['email_approved_body']); ?></textarea>
                        <p class="description">Wird an Benutzer gesendet, wenn sein Nachweis genehmigt wird.</p>
                    </td>
                </tr>
            </table>

            <h3>4. Nachweis abgelehnt (an Benutzer)</h3>
            <table class="form-table">
                <tr>
                    <th>Betreff</th>
                    <td><input type="text" name="email_rejected_subject" value="<?php echo esc_attr($s['email_rejected_subject']); ?>" class="large-text"></td>
                </tr>
                <tr>
                    <th>E-Mail-Text</th>
                    <td>
                        <textarea name="email_rejected_body" rows="10" class="large-text code"><?php echo esc_textarea($s['email_rejected_body']); ?></textarea>
                        <p class="description">Wird an Benutzer gesendet, wenn sein Nachweis abgelehnt wird. <code>{reject_comment}</code> enthält den Ablehnungsgrund.</p>
                    </td>
                </tr>
            </table>
                </tr>
            </table>

            <h2 class="title">🤖 KI-Provider</h2>
            <table class="form-table">
                <tr>
                    <th>Provider wählen</th>
                    <td>
                        <select name="ai_mode" id="ai_mode_select">
                            <option value="claude" <?php selected($s['ai_mode'],'claude'); ?>>✨ Claude (Anthropic) – empfohlen</option>
                            <option value="off" <?php selected($s['ai_mode'],'off'); ?>>❌ Aus</option>
                        </select>
                        <p class="description">Claude analysiert PDFs und Bilder direkt – keine OCR nötig!</p>
                    </td>
                </tr>
            </table>

            <div id="claude_settings" class="ai-settings-section">
                <h2 class="title">🟣 Claude Einstellungen</h2>
                <table class="form-table">
                    <tr>
                        <th>Claude API-Key</th>
                        <td>
                            <input type="password" name="claude_api_key" value="<?php echo esc_attr($s['claude_api_key']); ?>" class="regular-text" placeholder="sk-ant-...">
                            <p class="description">API-Key von <a href="https://console.anthropic.com/" target="_blank">console.anthropic.com</a></p>
                        </td>
                    </tr>
                    <tr>
                        <th>Claude Modell</th>
                        <td>
                            <select name="claude_model">
                                <option value="claude-sonnet-4-5-20250929" <?php selected($s['claude_model'],'claude-sonnet-4-5-20250929'); ?>>Claude Sonnet 4.5 (empfohlen) ⭐</option>
                                <option value="claude-3-5-sonnet-20241022" <?php selected($s['claude_model'],'claude-3-5-sonnet-20241022'); ?>>Claude 3.5 Sonnet (stabil)</option>
                                <option value="claude-3-5-haiku-20241022" <?php selected($s['claude_model'],'claude-3-5-haiku-20241022'); ?>>Claude 3.5 Haiku (guenstiger, schnell)</option>
                            </select>
                            <p class="description"><strong>Sonnet 4.6:</strong> Bestes Preis-Leistungs-Verhaeltnis | <strong>Kosten:</strong> ~$0.01/Analyse</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Max. Output Tokens</th>
                        <td>
                            <input type="number" name="claude_max_tokens" value="<?php echo esc_attr($s['claude_max_tokens']); ?>" min="500" step="100">
                            <p class="description">Empfohlen: 2048 für komplexe Dokumente</p>
                        </td>
                    </tr>
                </table>
            </div>

            <h2 class="title">📊 EBCP-Punktematrix</h2>
            <p class="description">Hier können Sie die Punktewerte und deutschen Bezeichnungen für alle Fortbildungskategorien definieren.</p>
            
            <div id="ebcp-matrix-editor">
                <h3>Kategorie-Editor</h3>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 30%;">Kategorie-Key</th>
                            <th style="width: 45%;">Deutsche Bezeichnung</th>
                            <th style="width: 15%;">Punkte</th>
                            <th style="width: 10%;">Gruppe</th>
                        </tr>
                    </thead>
                    <tbody id="ebcp-matrix-rows">
                        <?php
                        // Gespeicherte Matrix laden
                        $saved_matrix = json_decode($s['ebcp_mapping_json'], true);
                        if(!is_array($saved_matrix)) $saved_matrix = array();
                        $saved_bykey = array();
                        foreach($saved_matrix as $m){ $saved_bykey[$m['key']] = $m; }

                        // Default-Matrix als Referenz (enthaelt ALLE Keys)
                        $defaults = fobi_ebcp_default_settings();
                        $default_matrix = json_decode($defaults['ebcp_mapping_json'], true);

                        // Gruppierung anhand Key-Prefix
                        $group_labels = array(
                            'passive_workshop' => 'Passive Workshops',
                            'passive_' => 'Passive Teilnahme',
                            'active_workshop' => 'Aktive Workshops',
                            'active_' => 'Aktive Teilnahme',
                            'pub_' => 'Publikationen',
                            'ects_' => 'ECTS',
                        );

                        $last_group = '';
                        foreach($default_matrix as $def){
                            $key = $def['key'];

                            // Gruppe bestimmen
                            $group = '';
                            foreach($group_labels as $prefix => $label){
                                if(strpos($key, rtrim($prefix, '_')) === 0){ $group = $label; break; }
                            }

                            if($group !== $last_group){
                                echo '<tr class="group-header"><td colspan="4" style="background:#f0f0f1;font-weight:bold;padding:8px;">' . esc_html($group) . '</td></tr>';
                                $last_group = $group;
                            }

                            // Gespeicherte Werte vorziehen, Default als Fallback
                            $item = isset($saved_bykey[$key]) ? $saved_bykey[$key] : $def;

                            echo '<tr>';
                            echo '<td><code>' . esc_html($key) . '</code></td>';
                            echo '<td><input type="text" name="matrix_label[' . esc_attr($key) . ']" value="' . esc_attr($item['label']) . '" class="regular-text"></td>';
                            echo '<td><input type="number" name="matrix_points[' . esc_attr($key) . ']" value="' . esc_attr($item['points']) . '" step="0.5" min="0" style="width:80px;"></td>';
                            echo '<td style="color:#666;font-size:0.9em;">' . esc_html($group) . '</td>';
                            echo '</tr>';
                        }
                        ?>
                    </tbody>
                </table>

                <p class="description" style="margin-top:15px;">
                    <strong>Hinweis:</strong> Alle Keys werden automatisch an Claude uebergeben. Beim Speichern wird die Matrix in der Datenbank aktualisiert.<br>
                    Neue Kategorien koennen im Code unter <code>fobi_ebcp_default_settings()</code> hinzugefuegt werden.
                </p>
            </div>
            
            <details style="margin-top: 20px;">
                <summary style="cursor: pointer; font-weight: bold; padding: 10px; background: #f0f0f1; border-radius: 4px;">
                    🔧 Erweitert: JSON-Editor (für Fortgeschrittene)
                </summary>
                <table class="form-table" style="margin-top: 10px;">
                    <tr>
                        <th>Kategorien & Punkte (JSON)</th>
                        <td><textarea name="ebcp_mapping_json_manual" rows="12" class="large-text code"><?php echo esc_textarea($s['ebcp_mapping_json']); ?></textarea>
                        <p class="description">⚠️ Nur bearbeiten, wenn Sie mit JSON vertraut sind. Überschreibt die Tabelle oben.</p>
                        </td>
                    </tr>
                </table>
            </details>
            
            <h3 style="margin-top: 30px;">Internationale Kongresse</h3>
            <table class="form-table">
                <tr>
                    <th>Liste internationaler Meetings</th>
                    <td><textarea name="ebcp_international_list" rows="4" class="large-text code"><?php echo esc_textarea($s['ebcp_international_list']); ?></textarea>
                    <p class="description">JSON-Array mit Abkürzungen internationaler Kongresse (z.B. AACP, EACTS, etc.)</p>
                    </td>
                </tr>
            </table>

            <p class="submit"><input type="submit" name="fobi_ebcp_save" class="button button-primary" value="💾 Einstellungen speichern"></p>
        </form>
    </div>

    <script>
    jQuery(function($){
        function toggleAiSettings(){
            var mode = $('#ai_mode_select').val();
            $('.ai-settings-section').hide();
            if(mode === 'claude') $('#claude_settings').show();
        }
        $('#ai_mode_select').on('change', toggleAiSettings);
        toggleAiSettings();
    });
    </script>

    <style>
    .ai-settings-section { 
        display: none; 
        margin: 20px 0; 
        padding: 20px; 
        background: #f9f9f9; 
        border-left: 4px solid #0073aa;
        border-radius: 4px;
    }
    </style>
    <?php
}

/* ============================================================
 * Shortcode: [fobi_nachweis_upload]
 * ============================================================ */
add_shortcode('fobi_nachweis_upload', 'fobi_ebcp_shortcode_render');

function fobi_ebcp_shortcode_render($atts){
    $s = fobi_ebcp_get_settings();
    if( $s['enable_upload'] !== '1' ) return '<p>Upload-Funktion ist deaktiviert.</p>';
    if( ! is_user_logged_in() ) return '<p>Bitte melden Sie sich an, um Nachweise hochzuladen.</p>';

    ob_start();
    ?>
    <div class="fobi-ebcp-upload-wrap">
        <h3>Fortbildungsnachweis hochladen</h3>
        <p>Laden Sie Ihren Fortbildungsnachweis als PDF oder Bild hoch. Die KI analysiert das Dokument automatisch.</p>
        <form id="fobi-ebcp-form" enctype="multipart/form-data">
            <?php wp_nonce_field('fobi_ebcp_upload','fobi_ebcp_nonce'); ?>
            <p>
                <label for="fobi_file"><strong>Datei auswählen:</strong></label><br>
                <input type="file" id="fobi_file" name="fobi_file" accept=".pdf,.jpg,.jpeg,.png" required>
                <br><small>Erlaubt: PDF, JPG, PNG (max. <?php echo esc_html($s['max_file_mb']); ?> MB)</small>
            </p>
            <p>
                <button type="submit" class="button button-primary">📤 Hochladen & Analysieren</button>
            </p>
        </form>
        <div id="fobi-ebcp-result"></div>
    </div>

    <script>
    jQuery(function($){
        var currentPostId = null;
        var currentPostTitle = '';
        
        $('#fobi-ebcp-form').on('submit', function(e){
            e.preventDefault();
            var $btn = $(this).find('button[type=submit]');
            var $result = $('#fobi-ebcp-result');
            
            var fd = new FormData(this);
            fd.append('action', 'fobi_ebcp_upload');
            
            $btn.prop('disabled', true).text('⏳ Wird analysiert...');
            $result.html('<div class="fobi-loading"><p>⏳ Datei wird hochgeladen und analysiert. Bitte warten (20-40 Sekunden)...</p></div>');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: fd,
                processData: false,
                contentType: false,
                timeout: 90000,
                success: function(res){
                    if(res.success){
                        currentPostId = res.data.post_id;
                        currentPostTitle = res.data.post_title;
                        
                        var html = '<div class="fobi-success">✅ '+res.data.message;
                        html += '<hr style="margin: 20px 0; border: none; border-top: 1px solid #c3e6cb;">';
                        html += '<button type="button" class="fobi-correction-btn" style="background: #ffc107; color: #000; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; font-weight: bold;">⚠️ Die Daten stimmen nicht</button>';
                        html += '<div class="fobi-correction-form" style="display: none; margin-top: 15px;">';
                        html += '<p><strong>Was stimmt nicht?</strong></p>';
                        html += '<textarea class="fobi-correction-text" rows="4" style="width: 100%; padding: 10px; border: 1px solid #c3e6cb; border-radius: 4px;" placeholder="Bitte beschreiben Sie, welche Daten korrigiert werden müssen..."></textarea>';
                        html += '<button type="button" class="fobi-correction-submit" style="background: #28a745; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-top: 10px; margin-right: 10px;">✉️ Korrekturanfrage senden</button>';
                        html += '<button type="button" class="fobi-correction-cancel" style="background: #6c757d; color: #fff; border: none; padding: 10px 20px; border-radius: 4px; cursor: pointer; margin-top: 10px;">Abbrechen</button>';
                        html += '</div></div>';
                        
                        $result.html(html);
                        $('#fobi-ebcp-form')[0].reset();
                    } else {
                        $result.html('<div class="fobi-error">❌ '+res.data+'</div>');
                    }
                },
                error: function(xhr, status){
                    if(status === 'timeout'){
                        $result.html('<div class="fobi-error">❌ Timeout: Die Analyse dauert zu lange. Versuchen Sie eine kleinere Datei.</div>');
                    } else {
                        $result.html('<div class="fobi-error">❌ Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.</div>');
                    }
                },
                complete: function(){
                    $btn.prop('disabled', false).text('📤 Hochladen & Analysieren');
                }
            });
        });
        
        // Korrektur-Button Klick
        $(document).on('click', '.fobi-correction-btn', function(){
            $(this).hide();
            $('.fobi-correction-form').slideDown();
        });
        
        // Abbrechen-Button
        $(document).on('click', '.fobi-correction-cancel', function(){
            $('.fobi-correction-form').slideUp();
            $('.fobi-correction-btn').show();
            $('.fobi-correction-text').val('');
        });
        
        // Korrekturanfrage senden
        $(document).on('click', '.fobi-correction-submit', function(){
            var $btn = $(this);
            var comment = $('.fobi-correction-text').val().trim();
            
            if(!comment){
                alert('Bitte beschreiben Sie, was korrigiert werden muss.');
                return;
            }
            
            $btn.prop('disabled', true).text('⏳ Wird gesendet...');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'fobi_ebcp_correction_request',
                    nonce: '<?php echo wp_create_nonce('fobi_ebcp_correction'); ?>',
                    post_id: currentPostId,
                    post_title: currentPostTitle,
                    comment: comment
                },
                success: function(res){
                    if(res.success){
                        $('.fobi-correction-form').html('<div style="background: #d4edda; padding: 10px; border-radius: 4px; margin-top: 10px;">✅ '+res.data.message+'</div>');
                    } else {
                        alert('❌ Fehler: ' + res.data);
                        $btn.prop('disabled', false).text('✉️ Korrekturanfrage senden');
                    }
                },
                error: function(){
                    alert('❌ Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.');
                    $btn.prop('disabled', false).text('✉️ Korrekturanfrage senden');
                }
            });
        });
    });
    </script>

    <style>
    .fobi-ebcp-upload-wrap { max-width: 600px; margin: 20px auto; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 8px; }
    .fobi-ebcp-upload-wrap h3 { margin-top: 0; }
    .fobi-ebcp-upload-wrap input[type=file] { width: 100%; padding: 10px; border: 2px dashed #ccc; border-radius: 4px; }
    .fobi-ebcp-upload-wrap input[type=file]:hover { border-color: #0073aa; }
    #fobi-ebcp-result { margin-top: 20px; }
    .fobi-success, .fobi-error, .fobi-loading { padding: 15px; border-radius: 4px; margin: 10px 0; }
    .fobi-success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; }
    .fobi-error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; }
    .fobi-loading { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; }
    /* Dashboard-Integration (Forum-Vorbild) */
    .dgptm-dash .fobi-ebcp-upload-wrap { max-width: 100%; margin: 0; padding: 0; background: none; border: none; border-radius: 0; }
    .dgptm-dash .fobi-ebcp-upload-wrap h3 { font-size: 14px; margin: 0 0 8px; color: #1d2327; }
    .dgptm-dash .fobi-ebcp-upload-wrap p { font-size: 13px; color: #888; margin-bottom: 12px; }
    .dgptm-dash .fobi-ebcp-upload-wrap input[type=file] { border: 1px dashed #ccc; border-radius: 4px; padding: 8px; font-size: 13px; }
    .dgptm-dash .fobi-ebcp-upload-wrap button[type=submit] { display: inline-block !important; padding: 4px 10px !important; border: 1px solid #0073aa !important; border-radius: 4px !important; background: #0073aa !important; color: #fff !important; font-size: 12px !important; font-weight: 400 !important; line-height: 1.4 !important; cursor: pointer; }
    .dgptm-dash .fobi-ebcp-upload-wrap button[type=submit]:hover { background: #005d8c !important; }
    .dgptm-dash .fobi-success, .dgptm-dash .fobi-error, .dgptm-dash .fobi-loading { padding: 10px; font-size: 13px; border-radius: 4px; }
    </style>
    <?php
    return ob_get_clean();
}

/* ============================================================
 * AJAX-Handler: Upload & Analyse
 * ============================================================ */
add_action('wp_ajax_fobi_ebcp_upload', 'fobi_ebcp_ajax_upload');

function fobi_ebcp_ajax_upload(){
    check_ajax_referer('fobi_ebcp_upload','fobi_ebcp_nonce');
    
    if( ! is_user_logged_in() ){
        wp_send_json_error('Nicht angemeldet.');
    }
    
    $s = fobi_ebcp_get_settings();
    $u = wp_get_current_user();
    
    if( empty($_FILES['fobi_file']) || $_FILES['fobi_file']['error'] !== UPLOAD_ERR_OK ){
        wp_send_json_error('Datei konnte nicht hochgeladen werden.');
    }
    
    $file = $_FILES['fobi_file'];
    $max_bytes = $s['max_file_mb'] * 1024 * 1024;
    
    if( $file['size'] > $max_bytes ){
        wp_send_json_error('Datei ist zu groß (max. '.esc_html($s['max_file_mb']).' MB).');
    }
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    if( ! in_array($mime, $s['allowed_mimes'], true) ){
        wp_send_json_error('Dateityp nicht erlaubt. Nur PDF, JPG, PNG.');
    }
    
    // Zoho-Daten abrufen
    $expected_firstname = do_shortcode('[zoho_api_data field="Vorname"]');
    $expected_lastname = do_shortcode('[zoho_api_data field="Nachname"]');
    $expected_fullname = trim($expected_firstname . ' ' . $expected_lastname);
    
    if( empty($expected_fullname) || $expected_fullname === ' ' ){
        $expected_fullname = trim($u->first_name . ' ' . $u->last_name);
    }
    if( empty($expected_fullname) || $expected_fullname === ' ' ){
        $expected_fullname = $u->display_name;
    }
    
    // Datei analysieren
    $analysis = fobi_ebcp_analyze_document($file['tmp_name'], $mime, $expected_fullname, $s);
    
    if( ! $analysis['ok'] ){
        $error_msg = $analysis['error'] ?? 'Unbekannter Fehler';
        $error_detail = $analysis['error_detail'] ?? '';
        
        // Spezifische Fehlermeldung mit Details
        if( !empty($error_detail) ){
            wp_send_json_error($error_msg . ' ' . $error_detail);
        } else {
            wp_send_json_error('Analyse fehlgeschlagen: ' . $error_msg);
        }
    }
    
    $data = $analysis['data'];
    $confidence = floatval($analysis['confidence'] ?? 0);
    
    // ============================================================
    // NEUE VALIDIERUNG: Strikte Prüfungen mit detaillierten Fehlermeldungen
    // ============================================================
    
    // PRÜFUNG 1: Konfidenz unter 50% sofort ablehnen
    if( $confidence < 0.5 ){
        $reasons = array();
        
        // Detaillierte Gründe analysieren
        if( empty($data['participant']) ){
            $reasons[] = 'Kein Teilnehmername erkennbar';
        }
        if( empty($data['title']) ){
            $reasons[] = 'Kein Veranstaltungstitel erkennbar';
        }
        if( empty($data['start_date']) ){
            $reasons[] = 'Kein Datum erkennbar';
        }
        
        $detail = !empty($reasons) ? ' Probleme: ' . implode(', ', $reasons) . '.' : '';
        wp_send_json_error('❌ Die Qualität des Dokuments ist zu schlecht (Konfidenz: '.intval($confidence * 100).'%).' . $detail . ', oder der Name auf der Bescheinigung stimmt nicht überein.');
    }
    
    // PRÜFUNG 2: Uploads ohne Teilnehmer ablehnen
    if( empty($data['participant']) || trim($data['participant']) === '' ){
        wp_send_json_error('❌ Kein Teilnehmername erkennbar. Dies könnte ein Veranstaltungsflyer oder eine Ankündigung sein. Bitte laden Sie Ihre persönliche Teilnahmebestätigung mit Ihrem Namen hoch.');
    }
    
    // PRÜFUNG 3: Fortbildungsflyer erkennen (Veranstaltungsinfo aber kein persönlicher Nachweis)
    if( !empty($data['title']) && strlen(trim($data['participant'])) < 3 ){
        wp_send_json_error('❌ Dies scheint ein Veranstaltungsflyer oder -programm zu sein (Titel vorhanden, aber kein Teilnehmername). Bitte laden Sie Ihre persönliche Teilnahmebestätigung hoch.');
    }
    
    // PRÜFUNG 4: Mindestdaten vorhanden
    if( empty($data['title']) && empty($data['location']) && empty($data['start_date']) ){
        wp_send_json_error('❌ Keine relevanten Veranstaltungsdaten erkennbar. Das Dokument ist zu unleserlich oder es handelt sich nicht um einen Fortbildungsnachweis. Bitte prüfen Sie die Datei.');
    }
    
    // ============================================================
    // Race-Condition-Schutz: Transient-Lock gegen parallele Uploads
    // ============================================================
    $lock_key = 'fobi_upload_' . $u->ID . '_' . md5(mb_strtolower($data['title'], 'UTF-8'));
    if( get_transient($lock_key) ){
        wp_send_json_error('Diese Veranstaltung wird gerade verarbeitet. Bitte warten Sie einen Moment.');
    }
    set_transient($lock_key, true, 60); // 60 Sekunden Lock

    // ============================================================
    // Duplikaterkennung: Gleiche Veranstaltung schon eingereicht?
    // ============================================================
    $date_for_check = '';
    if( !empty($data['start_date']) ){
        $ts = strtotime($data['start_date']);
        if( $ts !== false ) $date_for_check = date('Y-m-d', $ts);
    }

    $end_date_for_check = '';
    if( !empty($data['end_date']) ){
        $ts_end = strtotime($data['end_date']);
        if( $ts_end !== false ) $end_date_for_check = date('Y-m-d', $ts_end);
    }

    $duplicate = fobi_ebcp_check_duplicate($u->ID, $data['title'], $date_for_check, $end_date_for_check);
    if( $duplicate ){
        delete_transient($lock_key);
        wp_send_json_error(sprintf(
            'Diese Veranstaltung wurde bereits eingereicht: „%s" (ID %d). Duplikate sind nicht erlaubt.',
            esc_html($duplicate->post_title),
            $duplicate->ID
        ));
    }
    $duplicate_warning = '';

    // ============================================================
    // Validierung + KI-Nachfrage bei Namensmismatch
    // ============================================================
    $auto_approve = $confidence >= $s['auto_approve_min_conf'];

    $participant_valid = fobi_ebcp_verify_participant($data['participant'], $expected_fullname);

    // Bei Namensmismatch: KI nochmal befragen (z.B. Titel, Umlaute, OCR-Fehler)
    $name_ai_override = false;
    if( !$participant_valid && !empty($data['participant']) ){
        $ai_says_same = fobi_ebcp_ai_verify_name($data['participant'], $expected_fullname, $s);
        if( $ai_says_same ){
            $participant_valid = true;
            $name_ai_override = true;
        }
    }

    $event_valid = fobi_ebcp_verify_event($data['title'], $data['location'], $data['start_date'], $s);

    // EBCP-Punkte vom Dokument haben Vorrang vor Matrix-Berechnung
    $ebcp_from_doc = floatval($data['ebcp_points'] ?? 0);
    if ($ebcp_from_doc > 0) {
        $points = $ebcp_from_doc;
    } else {
        $points = fobi_ebcp_calc_points($data, $s);
    }

    $is_valid = $participant_valid && $event_valid;
    $is_suspicious = !$participant_valid || !$event_valid || $confidence < 0.7;

    // ============================================================
    // VNR-Validierung: Aerztekammer-Daten abrufen + KI-Plausibilitaet
    // ============================================================
    $baek_verified = false;
    $baek_data = null;

    if (!empty($data['vnr']) && function_exists('dgptm_eiv_get_baek_token') && function_exists('dgptm_eiv_fetch_veranstaltung')) {
        $vnr_list = array_map('trim', explode(',', $data['vnr']));
        $vnr_primary = $vnr_list[0];

        $jwt = dgptm_eiv_get_baek_token();
        if (!is_wp_error($jwt) && !empty($jwt)) {
            $event_info = dgptm_eiv_fetch_veranstaltung($jwt, $vnr_primary);

            if (!is_wp_error($event_info) && is_array($event_info)) {
                $baek_data = $event_info;
                $baek_title = $event_info['titel'] ?? $event_info['thema'] ?? '';
                $baek_ort = $event_info['veranstaltungsort'] ?? $event_info['ort'] ?? '';
                $baek_datum = $event_info['beginn'] ?? $event_info['datum_von'] ?? '';

                error_log(sprintf('[Fobi-Upload] BÄK-Daten fuer VNR %s: titel=%s, ort=%s, datum=%s',
                    $vnr_primary, $baek_title, $baek_ort, $baek_datum));

                // KI-Plausibilitaetspruefung: stimmen Dokument und BÄK ueberein?
                $api_key = $s['claude_api_key'];
                if (!empty($api_key) && !empty($baek_title)) {
                    $plausibility_prompt = sprintf(
                        'Plausibilitaetspruefung: Ein Fortbildungsnachweis wurde eingereicht und die Aerztekammer hat folgende Daten zur VNR %s geliefert:' .
                        "\n\nAerztekammer-Daten:\n- Titel: %s\n- Ort: %s\n- Datum: %s" .
                        "\n\nDokument-Daten (KI-Extraktion):\n- Titel: %s\n- Ort: %s\n- Datum: %s\n- Teilnehmer: %s" .
                        "\n\nPrüfe:\n1. Stimmt der Veranstaltungstitel ueberein (auch bei leichten Abweichungen)?" .
                        "\n2. Stimmt der Ort ueberein?" .
                        "\n3. Stimmt das Datum ueberein (auch bei mehrtaegigen Events)?" .
                        "\n4. Ist das Dokument plausibel eine Teilnahmebestaetigung fuer diese Veranstaltung?" .
                        "\n\nAntworte NUR mit JSON: {\"plausible\": true/false, \"reason\": \"kurze Begruendung\"}",
                        $vnr_primary, $baek_title, $baek_ort, $baek_datum,
                        $data['title'], $data['location'], $data['start_date'], $data['participant']
                    );

                    $model = $s['claude_model'] ?? 'claude-sonnet-4-6-20250514';
                    $body = [
                        'model' => $model,
                        'max_tokens' => 200,
                        'messages' => [['role' => 'user', 'content' => $plausibility_prompt]]
                    ];

                    $ai_resp = wp_remote_post('https://api.anthropic.com/v1/messages', [
                        'headers' => [
                            'Content-Type' => 'application/json',
                            'x-api-key' => $api_key,
                            'anthropic-version' => '2023-06-01'
                        ],
                        'body' => json_encode($body),
                        'timeout' => 15
                    ]);

                    if (!is_wp_error($ai_resp) && wp_remote_retrieve_response_code($ai_resp) === 200) {
                        $ai_body = json_decode(wp_remote_retrieve_body($ai_resp), true);
                        $ai_text = $ai_body['content'][0]['text'] ?? '';
                        if (preg_match('/\{[\s\S]*\}/s', $ai_text, $ai_match)) {
                            $plausibility = json_decode($ai_match[0], true);
                            if (isset($plausibility['plausible']) && $plausibility['plausible']) {
                                $baek_verified = true;
                                error_log(sprintf('[Fobi-Upload] BÄK+KI verifiziert: VNR %s — %s', $vnr_primary, $plausibility['reason'] ?? 'OK'));
                            } else {
                                error_log(sprintf('[Fobi-Upload] KI-Plausibilitaet NICHT bestanden: %s', $plausibility['reason'] ?? 'unbekannt'));
                            }
                        }
                    }
                } else {
                    // Ohne KI: nur BÄK-Daten vorhanden → als verifiziert betrachten
                    $baek_verified = true;
                }
            }
        }
    }

    // Status: BÄK-verifiziert → direkt freigeben
    if ($baek_verified && $participant_valid) {
        $status = 'approved';
        $status_label = 'Automatisch freigegeben (Aerztekammer-verifiziert)';
    } elseif ($is_suspicious) {
        $status = 'suspicious';
        $status_label = 'Verdaechtig — Pruefung erforderlich';
    } else {
        $status = 'pending';
        $status_label = 'Pruefung erforderlich';
    }

    // Punkte-Debug: loggen warum 0
    $category_key_debug = fobi_ebcp_get_category_key($data);
    if( $points <= 0 ){
        error_log(sprintf('[Fobi-Upload] 0 Punkte! category=%s, key=%s, data=%s',
            $data['category'], $category_key_debug, json_encode($data)));
    }
    
    // ============================================================
    // Mehrtaegige Events in Einzeltage aufsplitten
    // ============================================================
    $start_date = $data['start_date'] ?? '';
    $end_date = $data['end_date'] ?? $start_date;
    $category_key = fobi_ebcp_get_category_key($data);
    $category_label = fobi_ebcp_get_category_label($category_key, $s);

    // Tage berechnen
    $event_days = [];
    $start_ts_calc = strtotime($start_date);
    $end_ts_calc = strtotime($end_date);

    if ($start_ts_calc && $end_ts_calc && $end_ts_calc > $start_ts_calc) {
        // Mehrtaegig: fuer jeden Tag einen Eintrag
        $current = $start_ts_calc;
        while ($current <= $end_ts_calc) {
            $event_days[] = date('Y-m-d', $current);
            $current = strtotime('+1 day', $current);
        }
    } else {
        // Eintaegig
        $event_days[] = $start_date;
    }

    $num_days = count($event_days);
    $group_id = $num_days > 1 ? 'fobi_group_' . wp_generate_password(12, false) : '';
    $created_post_ids = [];

    // Attachment einmal hochladen (wird bei allen Posts referenziert)
    $attachment_id = 0;
    if ($s['store_proof_as_attachment'] === '1') {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        add_filter('upload_dir', 'fobi_ebcp_protected_upload_dir');
        $attachment_id = media_handle_upload('fobi_file', 0);
        remove_filter('upload_dir', 'fobi_ebcp_protected_upload_dir');

        if (is_wp_error($attachment_id)) {
            $attachment_id = 0;
        }
    }

    foreach ($event_days as $day_index => $day_date) {
        $day_num = $day_index + 1;
        $title_suffix = $num_days > 1 ? sprintf(' (Tag %d/%d)', $day_num, $num_days) : '';

        $post_data = array(
            'post_type'   => 'fortbildung',
            'post_title'  => ($data['title'] ?: 'Fortbildung vom ' . date('d.m.Y')) . $title_suffix,
            'post_status' => 'publish',
            'post_author' => $u->ID,
        );

        $post_id = wp_insert_post($post_data);

        if (is_wp_error($post_id)) {
            continue;
        }

        $created_post_ids[] = $post_id;

        if ($name_ai_override) {
            update_post_meta($post_id, '_fobi_name_ai_override', sprintf(
                'KI bestaetigte: "%s" = "%s"', $data['participant'], $expected_fullname
            ));
        }

        if (function_exists('update_field')) {
            update_field('user', $u->ID, $post_id);
            update_field('date', $day_date, $post_id);
            update_field('location', $data['location'], $post_id);
            update_field('type', $category_label, $post_id);
            update_field('points', $points, $post_id);

            if (!empty($data['vnr'])) {
                update_field('vnr', $data['vnr'], $post_id);
            }

            update_field('token', wp_generate_password(32, false), $post_id);

            // Freigabe: BÄK-verifiziert → automatisch, sonst manuell
            if ($baek_verified && $participant_valid) {
                update_field('freigegeben', true, $post_id);
            } else {
                update_field('freigegeben', false, $post_id);
            }

            // KI-Rohdaten
            update_post_meta($post_id, '_ebcp_ai_response', json_encode($data, JSON_UNESCAPED_UNICODE));
            update_post_meta($post_id, '_ebcp_ai_confidence', $confidence);
            update_post_meta($post_id, '_ebcp_category_key', $category_key);
            update_post_meta($post_id, '_ebcp_raw_category', $data['category']);
            update_post_meta($post_id, '_ebcp_raw_subtype', $data['subtype'] ?? '');
            update_post_meta($post_id, '_ebcp_active_role', $data['active_role'] ?? '');
            update_post_meta($post_id, '_ebcp_doc_type', $data['doc_type'] ?? '');

            // BÄK-Verifikation speichern
            if ($baek_verified) {
                update_post_meta($post_id, '_fobi_baek_verified', true);
                update_post_meta($post_id, '_fobi_baek_vnr', $data['vnr']);
                if ($baek_data) {
                    update_post_meta($post_id, '_fobi_baek_data', json_encode($baek_data, JSON_UNESCAPED_UNICODE));
                }
            }

            // Mehrtages-Gruppierung
            if ($group_id) {
                update_post_meta($post_id, '_fobi_group_id', $group_id);
                update_post_meta($post_id, '_fobi_group_day', $day_num);
                update_post_meta($post_id, '_fobi_group_total_days', $num_days);
            }
        }

        // Attachment bei allen Posts referenzieren
        if ($attachment_id && function_exists('update_field')) {
            update_field('attachements', $attachment_id, $post_id);
        }
    }

    // Erste Post-ID als Haupt-Post (fuer Notification + Antwort)
    $post_id = !empty($created_post_ids) ? $created_post_ids[0] : 0;

    if (!$post_id) {
        wp_send_json_error('Fehler beim Erstellen der Fortbildung.');
    }

    // Notification senden
    if ($is_suspicious && $s['notify_on_suspicious'] === '1') {
        fobi_ebcp_send_notification($u, $data, $confidence, $expected_fullname, $s, $post_id);
    }
    
    $total_points = $points * $num_days;
    $date_display = $num_days > 1
        ? sprintf('%s bis %s (%d Tage × %s Pkt = %s Pkt gesamt)', esc_html($start_date), esc_html($end_date), $num_days, number_format($points, 1), number_format($total_points, 1))
        : esc_html($start_date);

    $message = sprintf(
        '<strong>%s</strong><br><br>📄 <strong>Titel:</strong> %s<br>👤 <strong>Teilnehmer:</strong> %s<br>📍 <strong>Ort:</strong> %s<br>📅 <strong>Zeitraum:</strong> %s<br>🏷️ <strong>Art:</strong> %s<br>⭐ <strong>Punkte pro Tag:</strong> %s<br>📊 <strong>Eintraege erstellt:</strong> %d<br>🎯 <strong>Konfidenz:</strong> %d%%',
        $status_label,
        esc_html($data['title']),
        esc_html($data['participant']),
        esc_html($data['location']),
        $date_display,
        esc_html($category_label),
        esc_html(number_format($points, 1)),
        count($created_post_ids),
        intval($confidence * 100)
    );

    // Duplikat-Warnung
    if( !empty($duplicate_warning) ){
        $message .= '<br><br>⚠️ ' . $duplicate_warning;
    }

    $message .= '<br><br>ℹ️ <strong>Dieser Nachweis muss manuell geprueft und freigegeben werden.</strong>';

    // Link zur erstellten Fortbildung nur für Benutzer mit Bearbeitungsrechten anzeigen
    if( current_user_can('edit_post', $post_id) ){
        $edit_link = admin_url('post.php?post='.$post_id.'&action=edit');
        $message .= sprintf('<br>📋 <a href="%s" target="_blank">Fortbildung im Backend anzeigen</a>', esc_url($edit_link));
    }
    
    // Race-Condition Lock freigeben
    delete_transient($lock_key);

    wp_send_json_success(array(
        'message' => $message,
        'post_id' => $post_id,
        'post_title' => $data['title'] ?: 'Fortbildung'
    ));
}

/* ============================================================
 * AJAX: Korrekturanfrage
 * ============================================================ */
add_action('wp_ajax_fobi_ebcp_correction_request', 'fobi_ebcp_ajax_correction_request');

function fobi_ebcp_ajax_correction_request(){
    check_ajax_referer('fobi_ebcp_correction', 'nonce');
    
    if( ! is_user_logged_in() ){
        wp_send_json_error('Nicht angemeldet.');
    }
    
    $post_id = intval($_POST['post_id'] ?? 0);
    $post_title = sanitize_text_field($_POST['post_title'] ?? '');
    $comment = sanitize_textarea_field($_POST['comment'] ?? '');
    
    if( ! $post_id || ! $comment ){
        wp_send_json_error('Fehlende Daten.');
    }
    
    // Prüfe ob Post existiert
    $post = get_post($post_id);
    if( ! $post || $post->post_type !== 'fortbildung' ){
        wp_send_json_error('Fortbildung nicht gefunden.');
    }
    
    $u = wp_get_current_user();
    $s = fobi_ebcp_get_settings();
    
    // E-Mail mit Template vorbereiten
    $subject_template = $s['email_correction_subject'];
    $body_template = $s['email_correction_body'];
    
    $vars = array(
        'user_name' => $u->display_name,
        'user_email' => $u->user_email,
        'user_firstname' => $u->first_name,
        'title' => $post_title,
        'comment' => $comment,
        'edit_link' => admin_url('post.php?post='.$post_id.'&action=edit')
    );
    
    $subject = fobi_ebcp_replace_email_vars($subject_template, $vars);
    $message = fobi_ebcp_replace_email_vars($body_template, $vars);
    
    // E-Mail senden
    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'Reply-To: ' . $u->user_email
    );
    
    $sent = wp_mail($s['notification_email'], $subject, $message, $headers);
    
    if( $sent ){
        // Optional: Kommentar als Post Meta speichern
        add_post_meta($post_id, '_correction_request', array(
            'user_id' => $u->ID,
            'user_name' => $u->display_name,
            'user_email' => $u->user_email,
            'comment' => $comment,
            'timestamp' => current_time('mysql')
        ));
        
        wp_send_json_success(array(
            'message' => 'Ihre Korrekturanfrage wurde versendet. Ein Administrator wird sich darum kümmern.'
        ));
    } else {
        wp_send_json_error('E-Mail konnte nicht versendet werden. Bitte kontaktieren Sie den Administrator direkt.');
    }
}

/* ============================================================
 * Dokumenten-Analyse (Hauptfunktion)
 * ============================================================ */
function fobi_ebcp_analyze_document($filepath, $mime, $expected_name, $s){
    $ai_mode = $s['ai_mode'] ?? 'off';
    
    if( $ai_mode === 'claude' && !empty($s['claude_api_key']) ){
        return fobi_ebcp_claude_analyze($filepath, $mime, $expected_name, $s);
    }
    
    // OpenAI Vision wurde in v3.1 entfernt - nur Claude verfügbar
    // if( $ai_mode === 'openai_vision' && !empty($s['openai_vision_api_key']) ){
    //     return fobi_ebcp_openai_vision_analyze($filepath, $mime, $expected_name, $s);
    // }
    
    return array('ok'=>false, 'error'=>'Kein KI-Modus aktiv oder API-Key fehlt');
}

/* ============================================================
 * Claude Vision API
 * ============================================================ */
function fobi_ebcp_claude_analyze($filepath, $mime, $expected_name, $s){
    $api_key = $s['claude_api_key'];
    $model = $s['claude_model'] ?? 'claude-sonnet-4-5-20250929';
    $max_tokens = intval($s['claude_max_tokens'] ?? 2048);
    
    $file_data = file_get_contents($filepath);
    if( $file_data === false ){
        return array('ok'=>false, 'error'=>'Datei konnte nicht gelesen werden');
    }
    
    $base64 = base64_encode($file_data);
    
    $categories_desc = fobi_ebcp_get_categories_description();
    $intl_list = fobi_ebcp_get_international_list($s);
    
    $prompt = "Analysiere dieses Dokument und bestimme zunaechst den DOKUMENTTYP, dann extrahiere die Daten als JSON.

SCHRITT 1 - DOKUMENTTYP BESTIMMEN (KRITISCH!):
Pruefe ZUERST, ob es sich um eine GUELTIGE TEILNAHMEBESTAETIGUNG handelt.
GUELTIGE Dokumente sind NUR:
- Teilnahmebescheinigungen / Teilnahmebestaetigungen
- Zertifikate / Certificates
- CME-Bescheinigungen mit Fortbildungspunkten
- Dokumente mit VNR-Nummern (Veranstaltungsnummern der Aerztekammer)
- Dokumente mit Unterschrift der wissenschaftlichen Leitung

UNGUELTIGE Dokumente (MUESSEN abgelehnt werden mit doc_type und confidence < 0.1):
- Rechnungen, Zahlungsinformationen, Zahlungsbelege, Invoices
- Anmeldebestaetigungen, Buchungsbestaetigungen, Registration Confirmations
- Flyer, Programme, Ankuendigungen, Call for Papers
- Abstracts, Einreichungsbestaetigungen
- Hotelbuchungen, Reisebestaetigungen

ERKENNUNGSMERKMALE fuer RECHNUNGEN/ZAHLUNGSBELEGE (IMMER ablehnen!):
- Woerter wie: Zahlungsinformation, Rechnung, Invoice, Anmeldegebuehr, Betrag, IBAN, BIC, Kontoinhaber, ueberweisen, ausstehender Betrag, Buchung, bezahlt
- Enthaelt Geldbetraege mit Waehrungssymbol oder USt.
- Enthaelt Bankdaten (IBAN, BIC, Kontonummer)
- Enthaelt Zahlungsfristen

ERKENNUNGSMERKMALE fuer GUELTIGE BESCHEINIGUNGEN:
- Woerter wie: Teilnahmebescheinigung, Zertifikat, Certificate, bescheinigt, bestaetigt hiermit, hat teilgenommen
- Enthaelt VNR-Nummern oder CME-Punkte
- Enthaelt Unterschriften der wissenschaftlichen Leitung
- Enthaelt Anwesenheitserfassung oder Punktekategorien

SCHRITT 2 - TEILNEHMER-VALIDIERUNG:
- Gib NUR einen participant-Wert zurueck, wenn eindeutig ein PERSOENLICHER TEILNEHMERNAME auf dem Dokument steht
- Bei ungueltigen Dokumenttypen: participant = \"\" (leer)
- NICHT verwechseln: Veranstalter, Referenten oder Rechnungsempfaenger sind KEINE Teilnehmer
- Ein Name auf einer RECHNUNG ist der RECHNUNGSEMPFAENGER, nicht der Teilnehmer!

ERWARTETER TEILNEHMER: {$expected_name}

KATEGORIEN:
{$categories_desc}

INTERNATIONALE MEETINGS: {$intl_list}

Antworte NUR mit JSON in diesem Format:
{
  \"doc_type\": \"certificate|invoice|registration|flyer|other\",
  \"participant\": \"Vollstaendiger Name des TEILNEHMERS (leer wenn ungueltig)\",
  \"title\": \"Veranstaltungstitel\",
  \"location\": \"Stadt, Land\",
  \"start_date\": \"YYYY-MM-DD\",
  \"end_date\": \"YYYY-MM-DD\",
  \"category\": \"passive_kongress_national\",
  \"subtype\": \"\",
  \"active_role\": \"no\",
  \"ects\": 0,
  \"cme_points\": 0,
  \"ebcp_points\": 0,
  \"vnr\": \"\",
  \"confidence\": 0.95
}

WICHTIG fuer vnr (Veranstaltungsnummer):
- VNR beginnt IMMER mit 276 und ist eine lange Ziffernfolge (ca. 15-20 Stellen)
- Kann als Text oder als Barcode auf dem Dokument stehen
- Kann auch als 'Veranstaltungsnummer', 'VNR' oder 'Veranst.-Nr.' beschriftet sein
- Mehrere VNRs moeglich (kommasepariert zurueckgeben)
- Wenn keine VNR erkennbar: vnr = \"\" (leer)

WICHTIG fuer doc_type:
- \"certificate\": Teilnahmebescheinigung, Zertifikat, CME-Nachweis
- \"invoice\": Rechnung, Zahlungsinformation, Zahlungsbeleg
- \"registration\": Anmeldebestaetigung, Buchungsbestaetigung
- \"flyer\": Flyer, Programm, Ankuendigung
- \"other\": Alles andere

WICHTIG fuer category-Werte:
- Verwende AUSSCHLIESSLICH einen Key aus der Kategorieliste oben
- KEIN eigener Key! Nur die exakt aufgelisteten Keys sind erlaubt
- Wenn das Dokument in keine Kategorie passt: \"category\": \"undefined\"
- Bei Workshops mit aktiver Teilnahme (Referent, Dozent): active_workshop_* statt passive_workshop_*
- Fuer DGPTM oder DGfK Jahrestagung verwende IMMER: \"category\": \"passive_dgptm_jahrestagung\"

WICHTIG fuer end_date:
- Wenn die Veranstaltung ueber mehrere Tage laeuft, gib das LETZTE Datum als end_date an
- Beispiel: '21. bis 23. Februar 2026' -> start_date='2026-02-21', end_date='2026-02-23'
- Bei eintaegigen Events: end_date = start_date (gleicher Tag)
- Wenn nur ein Datum erkennbar: end_date = start_date

WICHTIG fuer cme_points:
Falls das Dokument explizit CME/Fortbildungspunkte ausweist (z.B. '4 Punkte (A) 6 Punkte (B)'), gib die SUMME der Punkte zurueck.

WICHTIG fuer ebcp_points:
Falls das Dokument explizit EBCP-Punkte/Credits vergibt (z.B. 'EBCP Credits: 3', 'EBCP Points: 4'), diese Zahl 1:1 uebernehmen. EBCP-Punkte haben VORRANG vor der Matrix-Berechnung!

WICHTIG fuer national vs. international:
- Ein Workshop/Kongress ist INTERNATIONAL wenn: englischer Titel, internationale Organisation (EBCP, AMSECT, EACTS, ECC etc.), Teilnehmer aus mehreren Laendern, EBCP-Punkte vergeben werden
- Ein Workshop in Deutschland mit englischem Titel und EBCP-Zertifikat ist INTERNATIONAL
- Nur deutschsprachige Veranstaltungen von deutschen Fachgesellschaften sind NATIONAL

Konfidenz-Bewertung:
- 0.9-1.0: Gueltige Teilnahmebescheinigung mit allen Daten
- 0.7-0.9: Gueltige Bescheinigung, kleine Unschaerfen
- 0.5-0.7: Teilweise lesbar, wichtige Infos fehlen
- 0.1-0.5: Fragwuerdiges Dokument, koennte ungueltig sein
- 0.0-0.1: UNGUELTIG - Rechnung, Anmeldung, Flyer oder kein Nachweis

KRITISCH: Bei Rechnungen/Zahlungsbelegen -> doc_type=\"invoice\", participant=\"\", confidence=0.0";

    // Content abhängig vom Dateityp
    if( $mime === 'application/pdf' ){
        // PDFs als "document" senden
        $content_item = array(
            'type' => 'document',
            'source' => array(
                'type' => 'base64',
                'media_type' => 'application/pdf',
                'data' => $base64
            )
        );
    } else {
        // Bilder als "image" senden
        // Validiere, dass es ein unterstütztes Bildformat ist
        $supported_image_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
        if( !in_array($mime, $supported_image_types) ){
            return array('ok'=>false, 'error'=>'Ungültiges Dateiformat. Bitte nur PDF, JPEG, PNG, GIF oder WebP verwenden.');
        }
        
        $content_item = array(
            'type' => 'image',
            'source' => array(
                'type' => 'base64',
                'media_type' => $mime,
                'data' => $base64
            )
        );
    }

    $body = array(
        'model' => $model,
        'max_tokens' => $max_tokens,
        'messages' => array(
            array(
                'role' => 'user',
                'content' => array(
                    $content_item,
                    array(
                        'type' => 'text',
                        'text' => $prompt
                    )
                )
            )
        )
    );
    
    $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01'
        ),
        'body' => json_encode($body),
        'timeout' => 60
    ));
    
    if( is_wp_error($response) ){
        return array('ok'=>false, 'error'=>'Claude API-Verbindungsfehler: '.$response->get_error_message());
    }
    
    $code = wp_remote_retrieve_response_code($response);
    $body_str = wp_remote_retrieve_body($response);
    
    if( $code !== 200 ){
        $err_data = json_decode($body_str, true);
        $err_msg = isset($err_data['error']['message']) ? $err_data['error']['message'] : 'HTTP '.$code;
        $err_type = isset($err_data['error']['type']) ? $err_data['error']['type'] : '';

        // Modell nicht gefunden — haeufiger Fehler bei falschen Modellnamen
        if( strpos($err_msg, 'model') !== false || $err_type === 'not_found_error' ){
            $used_model = $s['claude_model'] ?? '(unbekannt)';
            return array('ok'=>false, 'error'=>'Modell "' . $used_model . '" nicht gefunden. Bitte in den Einstellungen pruefen.', 'error_detail'=>$body_str);
        }
        if( strpos($err_msg, 'media_type') !== false ){
            return array('ok'=>false, 'error'=>'Ungueltiges Dateiformat.', 'error_detail'=>$body_str);
        }
        if( strpos($err_msg, 'api_key') !== false || strpos($err_msg, 'authentication') !== false ){
            return array('ok'=>false, 'error'=>'Ungueltiger API-Key.', 'error_detail'=>$body_str);
        }
        if( strpos($err_msg, 'rate_limit') !== false || strpos($err_msg, 'quota') !== false ){
            return array('ok'=>false, 'error'=>'API-Limit erreicht.', 'error_detail'=>$body_str);
        }
        if( strpos($err_msg, 'overloaded') !== false ){
            return array('ok'=>false, 'error'=>'Claude-Server ueberlastet.', 'error_detail'=>$body_str);
        }

        return array('ok'=>false, 'error'=>'Claude API-Fehler (HTTP ' . $code . '): ' . $err_msg, 'error_detail'=>$body_str);
    }
    
    $result = json_decode($body_str, true);
    if( ! is_array($result) || empty($result['content'][0]['text']) ){
        return array('ok'=>false, 'error'=>'Unerwartete API-Antwort. Bitte erneut versuchen.');
    }
    
    $text = $result['content'][0]['text'];
    
    if( preg_match('/\{[\s\S]*\}/s', $text, $matches) ){
        $json_str = $matches[0];
    } else {
        $json_str = $text;
    }
    
    $data = json_decode($json_str, true);
    if( ! is_array($data) ){
        return array('ok'=>false, 'error'=>'Dokument konnte nicht vollständig analysiert werden. Bitte prüfen Sie die Lesbarkeit.');
    }
    
    $doc_type = strtolower(trim($data['doc_type'] ?? 'other'));

    // Ungueltige Dokumenttypen sofort ablehnen
    if( in_array($doc_type, array('invoice', 'registration', 'flyer')) ){
        $type_labels = array(
            'invoice' => 'eine Rechnung oder Zahlungsinformation',
            'registration' => 'eine Anmelde- oder Buchungsbestaetigung',
            'flyer' => 'ein Veranstaltungsflyer oder Programm'
        );
        $label = $type_labels[$doc_type] ?? 'kein gueltiger Fortbildungsnachweis';
        return array(
            'ok' => false,
            'error' => 'Dieses Dokument ist ' . $label . '.',
            'error_detail' => 'Bitte laden Sie Ihre persoenliche Teilnahmebescheinigung oder Ihr Zertifikat hoch.'
        );
    }

    $parsed = array(
        'participant' => trim(html_entity_decode(strip_tags($data['participant'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        'title' => trim(html_entity_decode(strip_tags($data['title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        'location' => trim($data['location'] ?? ''),
        'start_date' => trim($data['start_date'] ?? ''),
        'end_date' => trim($data['end_date'] ?? $data['start_date'] ?? ''),
        'category' => trim($data['category'] ?? ''),
        'subtype' => trim($data['subtype'] ?? ''),
        'active_role' => strtolower(trim($data['active_role'] ?? 'no')),
        'ects' => intval($data['ects'] ?? 0),
        'cme_points' => intval($data['cme_points'] ?? 0),
        'ebcp_points' => floatval($data['ebcp_points'] ?? 0),
        'vnr' => trim($data['vnr'] ?? ''),
        'doc_type' => $doc_type
    );

    return array(
        'ok' => true,
        'data' => $parsed,
        'confidence' => floatval($data['confidence'] ?? 0.7)
    );
}

/* ============================================================
 * OpenAI Vision API
 * ============================================================ */
function fobi_ebcp_openai_vision_analyze($filepath, $mime, $expected_name, $s){
    $api_key = $s['openai_vision_api_key'];
    $model = $s['openai_vision_model'] ?? 'gpt-4o';
    $max_tokens = intval($s['openai_vision_max_tokens'] ?? 2048);
    
    $file_data = file_get_contents($filepath);
    if( $file_data === false ){
        return array('ok'=>false, 'error'=>'Datei konnte nicht gelesen werden');
    }
    
    $base64 = base64_encode($file_data);
    
    // PDF → PNG konvertieren (OpenAI Vision kann keine PDFs direkt lesen)
    if( $mime === 'application/pdf' ){
        if( ! function_exists('shell_exec') ){
            return array('ok'=>false, 'error'=>'PDF-Verarbeitung erfordert shell_exec. Bitte Claude nutzen.');
        }
        
        $img_path = sys_get_temp_dir().'/'.uniqid('pdf_').'.png';
        shell_exec('gs -sDEVICE=png16m -r300 -dFirstPage=1 -dLastPage=1 -o '.escapeshellarg($img_path).' '.escapeshellarg($filepath).' 2>/dev/null');
        
        if( ! file_exists($img_path) ){
            return array('ok'=>false, 'error'=>'PDF-Konvertierung fehlgeschlagen. Ghostscript installieren oder Claude nutzen.');
        }
        
        $file_data = file_get_contents($img_path);
        $base64 = base64_encode($file_data);
        $mime = 'image/png';
        @unlink($img_path);
    }
    
    $image_url = "data:{$mime};base64,{$base64}";
    
    $categories_desc = fobi_ebcp_get_categories_description();
    $intl_list = fobi_ebcp_get_international_list($s);
    
    $prompt = "Analysiere dieses Fortbildungsnachweis-Dokument.

ERWARTETER TEILNEHMER: {$expected_name}

KATEGORIEN: {$categories_desc}

INTERNATIONALE MEETINGS: {$intl_list}

JSON-Format:
{\"participant\":\"...\", \"title\":\"...\", \"location\":\"...\", \"start_date\":\"YYYY-MM-DD\", \"category\":\"...\", \"subtype\":\"\", \"active_role\":\"yes/no\", \"ects\":0, \"confidence\":0.95}";

    $body = array(
        'model' => $model,
        'max_tokens' => $max_tokens,
        'messages' => array(
            array(
                'role' => 'user',
                'content' => array(
                    array('type' => 'text', 'text' => $prompt),
                    array('type' => 'image_url', 'image_url' => array('url' => $image_url, 'detail' => 'high'))
                )
            )
        ),
        'temperature' => 0.1
    );
    
    $response = wp_remote_post('https://api.openai.com/v1/chat/completions', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer '.$api_key
        ),
        'body' => json_encode($body),
        'timeout' => 60
    ));
    
    if( is_wp_error($response) ){
        return array('ok'=>false, 'error'=>'OpenAI API Fehler: '.$response->get_error_message());
    }
    
    $code = wp_remote_retrieve_response_code($response);
    $body_str = wp_remote_retrieve_body($response);
    
    if( $code !== 200 ){
        $err_data = json_decode($body_str, true);
        $err_msg = isset($err_data['error']['message']) ? $err_data['error']['message'] : 'HTTP '.$code;
        return array('ok'=>false, 'error'=>'OpenAI: '.$err_msg);
    }
    
    $result = json_decode($body_str, true);
    if( empty($result['choices'][0]['message']['content']) ){
        return array('ok'=>false, 'error'=>'Keine OpenAI-Antwort');
    }
    
    $content = $result['choices'][0]['message']['content'];
    
    if( preg_match('/\{[\s\S]*\}/s', $content, $m) ){
        $json_str = $m[0];
    } else {
        $json_str = $content;
    }
    
    $data = json_decode($json_str, true);
    if( ! is_array($data) ){
        return array('ok'=>false, 'error'=>'JSON-Parsing fehlgeschlagen');
    }
    
    $parsed = array(
        'participant' => trim(html_entity_decode(strip_tags($data['participant'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        'title' => trim(html_entity_decode(strip_tags($data['title'] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8')),
        'location' => trim($data['location'] ?? ''),
        'start_date' => trim($data['start_date'] ?? ''),
        'end_date' => trim($data['end_date'] ?? $data['start_date'] ?? ''),
        'category' => trim($data['category'] ?? ''),
        'subtype' => trim($data['subtype'] ?? ''),
        'active_role' => strtolower(trim($data['active_role'] ?? 'no')),
        'ects' => intval($data['ects'] ?? 0)
    );
    
    return array(
        'ok' => true,
        'data' => $parsed,
        'confidence' => floatval($data['confidence'] ?? 0.6)
    );
}

/* ============================================================
 * Helper-Funktionen
 * ============================================================ */
function fobi_ebcp_get_categories_description(){
    // Dynamisch aus Default-Matrix + gespeicherter Matrix generieren
    $s = fobi_ebcp_get_settings();
    $map = json_decode($s['ebcp_mapping_json'], true);
    if(!is_array($map) || empty($map)){
        $defaults = fobi_ebcp_default_settings();
        $map = json_decode($defaults['ebcp_mapping_json'], true);
    }

    $lines = "ERLAUBTE KATEGORIEN (NUR diese Keys verwenden!):\n";
    foreach($map as $r){
        $lines .= sprintf("- %s (%s Punkte) — %s\n", $r['key'], $r['points'], $r['label']);
    }
    $lines .= "- undefined (0 Punkte) — Kann keiner Kategorie zugeordnet werden, manuelle Bewertung\n";
    $lines .= "\nWICHTIG:\n";
    $lines .= "- Verwende AUSSCHLIESSLICH die oben gelisteten Keys als category-Wert\n";
    $lines .= "- Wenn das Dokument in keine Kategorie passt: category = \"undefined\"\n";
    $lines .= "- Workshop = interaktive Veranstaltung (Hands-on, Simulation, Training)\n";
    $lines .= "- Kongress = grosse Fachtagung mit Vortraegen und vielen Teilnehmern\n";
    $lines .= "- DGPTM Jahrestagung oder 'Fokustagung Herz' im Titel -> IMMER passive_dgptm_jahrestagung\n";
    return $lines;
}

function fobi_ebcp_get_international_list($s){
    $intl = json_decode($s['ebcp_international_list'], true);
    return is_array($intl) ? implode(', ', $intl) : '';
}

/* ============================================================
 * Duplikaterkennung: Wurde diese Veranstaltung schon eingereicht?
 * Prueft Titel-Aehnlichkeit + Datum fuer den aktuellen User
 * ============================================================ */
function fobi_ebcp_check_duplicate($user_id, $title, $date, $end_date = ''){
    if( empty($title) ) return false;

    // Datumsbereich fuer die Suche
    $dates_to_check = [];
    if( !empty($date) ) $dates_to_check[] = $date;
    if( !empty($end_date) && $end_date !== $date ){
        $s = strtotime($date);
        $e = strtotime($end_date);
        if( $s && $e && $e > $s ){
            $c = $s;
            while( $c <= $e ){
                $dates_to_check[] = date('Y-m-d', $c);
                $c = strtotime('+1 day', $c);
            }
        }
    }

    if( empty($dates_to_check) ) return false;

    // Suche bestehende Posts dieses Users im Datumsbereich
    $args = array(
        'post_type' => 'fortbildung',
        'posts_per_page' => 50,
        'post_status' => array('publish', 'draft', 'pending'),
        'author' => $user_id,
        'meta_query' => array(
            array(
                'key' => 'date',
                'value' => $dates_to_check,
                'compare' => 'IN'
            )
        ),
    );

    $existing = get_posts($args);
    if( empty($existing) ) return false;

    // Titel normalisieren: HTML-Entities dekodieren, Tags entfernen, Suffix entfernen
    $title_norm = mb_strtolower(trim(html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 'UTF-8');
    $title_norm = preg_replace('/\s*\(tag\s+\d+\/\d+\)\s*$/i', '', $title_norm);

    foreach( $existing as $post ){
        $existing_title = mb_strtolower(trim(html_entity_decode(strip_tags($post->post_title), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 'UTF-8');
        $existing_title = preg_replace('/\s*\(tag\s+\d+\/\d+\)\s*$/i', '', $existing_title);

        if( $title_norm === $existing_title ){
            return $post;
        }

        // 80% Aehnlichkeit fuer leichte Varianten
        similar_text($title_norm, $existing_title, $pct);
        if( $pct >= 80 ){
            return $post;
        }
    }

    return false;
}

/* ============================================================
 * KI-Nachfrage bei Namensabweichung
 * Befragt Claude erneut, ob es sich um dieselbe Person handelt
 * ============================================================ */
function fobi_ebcp_ai_verify_name($doc_name, $expected_name, $s){
    $api_key = $s['claude_api_key'];
    if( empty($api_key) ) return false;

    $model = $s['claude_model'] ?? 'claude-sonnet-4-5-20250929';

    $prompt = sprintf(
        'Auf einem Fortbildungsnachweis steht der Teilnehmername "%s". ' .
        'Der erwartete Name des Benutzers ist "%s". ' .
        'Koennte es sich um dieselbe Person handeln? Beruecksichtige: ' .
        'Titel (Dr., Prof.), Schreibvarianten, Umlaute, OCR-Fehler, ' .
        'abgekuerzte Vornamen, Doppelnamen, Geburtsname vs. Ehename. ' .
        'Antworte NUR mit JSON: {"same_person": true/false, "reason": "kurze Begruendung"}',
        $doc_name,
        $expected_name
    );

    $body = array(
        'model' => $model,
        'max_tokens' => 200,
        'messages' => array(
            array(
                'role' => 'user',
                'content' => $prompt
            )
        )
    );

    $response = wp_remote_post('https://api.anthropic.com/v1/messages', array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01'
        ),
        'body' => json_encode($body),
        'timeout' => 15
    ));

    if( is_wp_error($response) ) return false;

    $code = wp_remote_retrieve_response_code($response);
    if( $code !== 200 ) return false;

    $body_str = wp_remote_retrieve_body($response);
    $result = json_decode($body_str, true);

    if( empty($result['content'][0]['text']) ) return false;

    $text = $result['content'][0]['text'];
    if( preg_match('/\{[\s\S]*\}/s', $text, $matches) ){
        $data = json_decode($matches[0], true);
        if( isset($data['same_person']) ){
            return (bool) $data['same_person'];
        }
    }

    return false;
}

function fobi_ebcp_verify_participant($doc_name, $expected_name){
    $candidate = trim((string)$doc_name);
    $expected = trim((string)$expected_name);
    
    // Leere Namen sofort ablehnen
    if( $candidate === '' || $expected === '' ) return false;
    
    // Normalisierung für besseren Vergleich
    $candidate_norm = mb_strtolower($candidate, 'UTF-8');
    $expected_norm = mb_strtolower($expected, 'UTF-8');
    
    // 1. Exakte Übereinstimmung (case-insensitive)
    if( $candidate_norm === $expected_norm ) return true;
    
    // 2. Ähnlichkeitsvergleich (Gesamtstring) - Schwellenwert auf 75% erhöht
    similar_text($candidate_norm, $expected_norm, $pct);
    if( $pct >= 75 ) return true;
    
    // 3. Name-Teil-Matching (verbessertes Tokenmatching)
    $expected_parts = preg_split('/[\s\-,.]+/', $expected_norm);
    $candidate_parts = preg_split('/[\s\-,.]+/', $candidate_norm);
    
    // Entferne sehr kurze Teile (z.B. Titel wie "Dr.")
    $expected_parts = array_filter($expected_parts, function($p){ return strlen($p) >= 2; });
    $candidate_parts = array_filter($candidate_parts, function($p){ return strlen($p) >= 2; });
    
    if( empty($expected_parts) || empty($candidate_parts) ) return false;
    
    $matches = 0;
    $required_matches = max(2, ceil(count($expected_parts) * 0.6)); // Mindestens 60% der Teile müssen matchen
    
    foreach( $expected_parts as $exp_part ){
        foreach( $candidate_parts as $cand_part ){
            // Exakte Teilübereinstimmung
            if( $exp_part === $cand_part ){
                $matches++;
                break;
            }
            
            // Fuzzy-Match für Teile (85% Schwellenwert)
            similar_text($exp_part, $cand_part, $part_pct);
            if( $part_pct >= 85 ){
                $matches++;
                break;
            }
            
            // Substring-Match (ein Name enthält den anderen)
            if( strlen($exp_part) >= 3 && strlen($cand_part) >= 3 ){
                if( strpos($cand_part, $exp_part) !== false || strpos($exp_part, $cand_part) !== false ){
                    $matches++;
                    break;
                }
            }
        }
    }
    
    // Benötigte Übereinstimmungen erreicht?
    return $matches >= $required_matches;
}

function fobi_ebcp_verify_event($title, $location, $date, $s){
    // Event-Verifizierung deaktiviert - alle Events als gültig betrachten
    // Ein Titel ist ausreichend
    $title = (string)$title;
    if( $title === '' ) return false;
    
    // Immer true zurückgeben, wenn ein Titel vorhanden ist
    return true;
    
    /* Alte Verifizierungslogik (deaktiviert)
    $intl = json_decode($s['ebcp_international_list'], true);
    if(!is_array($intl)) $intl=array();
    
    $title_low = mb_strtolower($title,'UTF-8');
    
    foreach($intl as $needle){
        $needle = trim((string)$needle);
        if($needle==='') continue;
        if( strpos($title_low, mb_strtolower($needle,'UTF-8')) !== false ){
            return true;
        }
    }
    
    // Erkenne auch Jahrestagung als Kongress
    if( preg_match('/\b(congress|conference|kongress|symposium|workshop|seminar|tagung|jahrestagung)\b/i', $title) ){
        if( $date && strtotime($date) ) return true;
    }
    
    return false;
    */
}

/**
 * Ermittelt den Mapping-Key aus category, subtype und active_role
 * 
 * @return string Der Key für die EBCP-Matrix (z.B. 'passive_national', 'active_intl_talk')
 */
function fobi_ebcp_get_category_key($parsed){
    $cat = strtolower($parsed['category'] ?? '');
    $sub = strtolower($parsed['subtype'] ?? '');
    $active = strtolower($parsed['active_role'] ?? 'no') === 'yes';
    
    // NEU v3.10: Wenn category schon ein gültiger Key ist (Claude gibt direkt Keys zurück), direkt verwenden
    // Gueltige Keys dynamisch aus Default-Matrix lesen
    $defaults = fobi_ebcp_default_settings();
    $default_map = json_decode($defaults['ebcp_mapping_json'], true);
    $valid_keys = array('undefined');
    if (is_array($default_map)) {
        foreach ($default_map as $r) {
            if (!empty($r['key'])) $valid_keys[] = $r['key'];
        }
    }
    // Legacy-Keys
    $valid_keys = array_merge($valid_keys, array('passive_inhouse', 'passive_national', 'passive_international'));
    
    if( in_array($cat, $valid_keys) ){
        return $cat;
    }
    
    // Legacy-Mapping für alte category-Werte
    if( $cat === 'publication' ){
        if( $sub === 'abstract' ) return 'pub_abstract';
        if( $sub === 'journal_no_ed' ) return 'pub_no_editorial';
        if( $sub === 'journal_with_ed' ) return 'pub_with_editorial';
        return 'pub_abstract';
    }
    
    if( $cat === 'ects' ){
        return 'ects_per_credit';
    }
    
    if( $cat === 'webinar' ){
        return $active ? 'active_webinar' : 'passive_webinar';
    }
    
    if( $cat === 'inhouse' ){
        return $active ? 'active_inhouse' : 'passive_workshop_inhouse';
    }
    
    if( $cat === 'international' ){
        if( $active ){
            return ($sub === 'moderator') ? 'active_intl_mod' : 'active_intl_talk';
        }
        // Versuche zu erkennen ob es ein Workshop oder Kongress ist (Fallback auf Kongress)
        return 'passive_kongress_international';
    }
    
    // National (default)
    if( $active ){
        return ($sub === 'moderator') ? 'active_national_mod' : 'active_national_talk';
    }
    // Fallback auf Kongress wenn nicht eindeutig
    return 'passive_kongress_national';
}

/**
 * Gibt die deutsche Bezeichnung für eine Kategorie zurück
 * 
 * @param string $key Der Kategorie-Key (z.B. 'passive_national')
 * @param array $s Settings-Array
 * @return string Deutsche Bezeichnung
 */
function fobi_ebcp_get_category_label($key, $s){
    $map = json_decode($s['ebcp_mapping_json'], true);
    if(!is_array($map)) return $key;
    
    foreach($map as $r){
        if($r['key'] === $key){
            return $r['label'];
        }
    }
    
    return $key; // Fallback
}

/**
 * Berechnet Punkte basierend auf der Kategorie
 */
function fobi_ebcp_calc_points($parsed, $s){
    $map = json_decode($s['ebcp_mapping_json'], true);
    if(!is_array($map)) $map=array();

    $bykey = array();
    foreach($map as $r){
        if(!empty($r['key'])){
            $bykey[$r['key']] = floatval($r['points']);
        }
    }

    $key = fobi_ebcp_get_category_key($parsed);
    $ects = intval($parsed['ects'] ?? 0);

    // Spezialfall ECTS
    if( $key === 'ects_per_credit' ){
        return max(0, $ects) * ($bykey['ects_per_credit'] ?? 1.0);
    }

    // Key in gespeicherter Matrix suchen
    if( isset($bykey[$key]) ){
        return $bykey[$key];
    }

    // Fallback: Default-Matrix wenn Key in Settings fehlt
    $defaults = fobi_ebcp_default_settings();
    $default_map = json_decode($defaults['ebcp_mapping_json'], true);
    if(is_array($default_map)){
        foreach($default_map as $r){
            if(($r['key'] ?? '') === $key){
                error_log(sprintf('[Fobi-Upload] Key "%s" nicht in Settings-Matrix — Fallback auf Default: %s Punkte', $key, $r['points']));
                return floatval($r['points']);
            }
        }
    }

    error_log(sprintf('[Fobi-Upload] Key "%s" nirgends gefunden! category=%s', $key, $parsed['category'] ?? ''));
    return 0.0;
}

/**
 * Ersetzt Platzhalter in E-Mail-Templates
 * 
 * @param string $template Das Template mit Platzhaltern
 * @param array $vars Assoziatives Array mit Variablen
 * @return string Template mit ersetzten Platzhaltern
 */
function fobi_ebcp_replace_email_vars($template, $vars){
    foreach($vars as $key => $value){
        $template = str_replace('{'.$key.'}', $value, $template);
    }
    return $template;
}

/**
 * Sendet E-Mail bei verdächtigem Nachweis (an Admin)
 */
function fobi_ebcp_send_notification($user, $data, $confidence, $expected_name, $settings, $post_id = 0){
    $to = $settings['notification_email'];
    $subject = $settings['notification_subject'];
    $body_template = $settings['email_suspicious_body'];
    
    $vars = array(
        'user_name' => $user->display_name,
        'user_email' => $user->user_email,
        'user_firstname' => $user->first_name,
        'expected_name' => $expected_name,
        'participant' => $data['participant'] ?: '(leer)',
        'title' => $data['title'] ?: '(leer)',
        'location' => $data['location'] ?: '(leer)',
        'date' => $data['start_date'] ?: '(leer)',
        'category' => $data['category'] ?: '(leer)',
        'confidence' => intval($confidence * 100),
        'edit_link' => $post_id ? admin_url('post.php?post='.$post_id.'&action=edit') : ''
    );
    
    $message = fobi_ebcp_replace_email_vars($body_template, $vars);
    
    wp_mail($to, $subject, $message, array('Content-Type: text/plain; charset=UTF-8'));
}

/**
 * Sendet E-Mail bei genehmigtem Nachweis (an Benutzer)
 */
function fobi_ebcp_send_approved_email($user, $post_id, $settings){
    $subject_template = $settings['email_approved_subject'];
    $body_template = $settings['email_approved_body'];
    
    // Fortbildungsdaten laden
    $title = get_the_title($post_id);
    $location = get_field('location', $post_id);
    $date = get_field('date', $post_id);
    $type = get_field('type', $post_id);
    $points = get_field('points', $post_id);
    
    $vars = array(
        'user_name' => $user->display_name,
        'user_email' => $user->user_email,
        'user_firstname' => $user->first_name ?: $user->display_name,
        'title' => $title,
        'location' => $location,
        'date' => $date,
        'category' => $type,
        'points' => number_format($points, 1)
    );
    
    $subject = fobi_ebcp_replace_email_vars($subject_template, $vars);
    $message = fobi_ebcp_replace_email_vars($body_template, $vars);
    
    wp_mail($user->user_email, $subject, $message, array('Content-Type: text/plain; charset=UTF-8'));
}

/**
 * Sendet E-Mail bei abgelehntem Nachweis (an Benutzer)
 */
function fobi_ebcp_send_rejected_email($user, $post_id, $reject_comment, $settings){
    $subject_template = $settings['email_rejected_subject'];
    $body_template = $settings['email_rejected_body'];
    
    // Fortbildungsdaten laden
    $title = get_the_title($post_id);
    
    $vars = array(
        'user_name' => $user->display_name,
        'user_email' => $user->user_email,
        'user_firstname' => $user->first_name ?: $user->display_name,
        'title' => $title,
        'reject_comment' => $reject_comment
    );
    
    $subject = fobi_ebcp_replace_email_vars($subject_template, $vars);
    $message = fobi_ebcp_replace_email_vars($body_template, $vars);
    
    wp_mail($user->user_email, $subject, $message, array('Content-Type: text/plain; charset=UTF-8'));
}

/* ============================================================
 * Shortcode: [fobi_nachweis_pruefliste]
 * Zeigt zu prüfende Nachweise im Frontend an
 * ============================================================ */
add_shortcode('fobi_nachweis_pruefliste', 'fobi_ebcp_pruefliste_shortcode');


/* ============================================================
 * Shortcode: [fobi_nachweis_pruefliste]
 * Frontend-Pruefung von eingereichten Fortbildungsnachweisen
 * ============================================================ */

function fobi_ebcp_pruefliste_shortcode($atts){
    // Berechtigungsprüfung
    if( ! current_user_can('edit_posts') ){
        return '<p>Sie haben keine Berechtigung, Nachweise zu prüfen.</p>';
    }
    
    ob_start();
    ?>
    <div class="fobi-pruefliste-wrap">
        <div class="fobi-load-section">
            <button id="fobi-load-pruefliste-btn" class="fobi-btn fobi-btn-load">
                📋 Freizugebende Fortbildungen laden
            </button>
            <p class="fobi-info-text">Klicken Sie auf den Button, um alle zu prüfenden Fortbildungsnachweise anzuzeigen.</p>
        </div>
        
        <div id="fobi-pruefliste-content" style="display: none;">
            <!-- Wird per AJAX geladen -->
        </div>
    </div>
    
    <!-- Modal für Details -->
    <div id="fobi-modal" class="fobi-modal" style="display: none;">
        <div class="fobi-modal-content">
            <span class="fobi-modal-close">&times;</span>
            <div id="fobi-modal-body">
                <!-- Wird per AJAX gefüllt -->
            </div>
        </div>
    </div>
    
    <script>
    jQuery(function($){
        var currentPostId = null;
        var listLoaded = false;
        
        // Liste laden per AJAX
        $('#fobi-load-pruefliste-btn').on('click', function(){
            if(listLoaded) return;
            
            var $btn = $(this);
            $btn.prop('disabled', true).html('⏳ Lade Fortbildungen...');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'fobi_load_pruefliste',
                    nonce: '<?php echo wp_create_nonce('fobi_pruefliste'); ?>',
                    debug: <?php echo (isset($_GET['debug']) && current_user_can('manage_options')) ? '1' : '0'; ?>
                },
                success: function(res){
                    if(res.success){
                        $('#fobi-pruefliste-content').html(res.data.html).slideDown();
                        $('.fobi-load-section').fadeOut();
                        listLoaded = true;
                    } else {
                        alert('❌ Fehler: ' + res.data);
                        $btn.prop('disabled', false).html('📋 Freizugebende Fortbildungen laden');
                    }
                },
                error: function(){
                    alert('❌ Fehler beim Laden der Liste.');
                    $btn.prop('disabled', false).html('📋 Freizugebende Fortbildungen laden');
                }
            });
        });
        
        // Modal öffnen
        $(document).on('click', '.fobi-btn-view', function(){
            var postId = $(this).data('post-id');
            currentPostId = postId;
            
            $('#fobi-modal-body').html('<p>⏳ Laden...</p>');
            $('#fobi-modal').fadeIn();
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'fobi_load_nachweis_details',
                    post_id: postId,
                    nonce: '<?php echo wp_create_nonce('fobi_pruefliste'); ?>'
                },
                success: function(res){
                    if(res.success){
                        $('#fobi-modal-body').html(res.data.html);
                    } else {
                        $('#fobi-modal-body').html('<p class="error">❌ ' + res.data + '</p>');
                    }
                }
            });
        });
        
        // Modal schließen
        $('.fobi-modal-close, .fobi-modal').on('click', function(e){
            if( e.target === this ){
                $('#fobi-modal').fadeOut();
                currentPostId = null;
            }
        });
        
        // Genehmigen
        $(document).on('click', '.fobi-btn-approve', function(){
            if( ! currentPostId ) return;
            
            if( ! confirm('Diesen Nachweis genehmigen und Benutzer benachrichtigen?') ) return;
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('⏳ Wird genehmigt...');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'fobi_approve_nachweis',
                    post_id: currentPostId,
                    nonce: '<?php echo wp_create_nonce('fobi_pruefliste'); ?>'
                },
                success: function(res){
                    if(res.success){
                        $('#fobi-modal').fadeOut();
                        $('tr[data-post-id="'+currentPostId+'"]').fadeOut(function(){ 
                            $(this).remove();
                            var remaining = $('.fobi-pruefliste-table tbody tr').length;
                            $('.fobi-pruefliste-wrap h2').html('Zu prüfende Fortbildungsnachweise (' + remaining + ')');
                            if(remaining === 0){
                                $('#fobi-pruefliste-content').html('<p class="fobi-no-results">✅ Keine zu prüfenden Nachweise mehr vorhanden.</p>');
                            }
                        });
                        alert('✅ ' + res.data.message);
                    } else {
                        alert('❌ ' + res.data);
                        $btn.prop('disabled', false).text('✅ Genehmigen');
                    }
                }
            });
        });
        
        // Ablehnen - Kommentar-Feld anzeigen
        $(document).on('click', '.fobi-btn-reject-show', function(){
            $('.fobi-reject-form').slideDown();
            $(this).hide();
        });
        
        // Ablehnen - Absenden
        $(document).on('click', '.fobi-btn-reject-submit', function(){
            if( ! currentPostId ) return;
            
            var comment = $('.fobi-reject-comment').val().trim();
            if( ! comment ){
                alert('Bitte geben Sie einen Ablehnungsgrund ein.');
                return;
            }
            
            if( ! confirm('Diesen Nachweis ablehnen und Benutzer benachrichtigen?') ) return;
            
            var $btn = $(this);
            $btn.prop('disabled', true).text('⏳ Wird abgelehnt...');
            
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'fobi_reject_nachweis',
                    post_id: currentPostId,
                    comment: comment,
                    nonce: '<?php echo wp_create_nonce('fobi_pruefliste'); ?>'
                },
                success: function(res){
                    if(res.success){
                        $('#fobi-modal').fadeOut();
                        $('tr[data-post-id="'+currentPostId+'"]').fadeOut(function(){ 
                            $(this).remove();
                            var remaining = $('.fobi-pruefliste-table tbody tr').length;
                            $('.fobi-pruefliste-wrap h2').html('Zu prüfende Fortbildungsnachweise (' + remaining + ')');
                            if(remaining === 0){
                                $('#fobi-pruefliste-content').html('<p class="fobi-no-results">✅ Keine zu prüfenden Nachweise mehr vorhanden.</p>');
                            }
                        });
                        alert('✅ ' + res.data.message);
                    } else {
                        alert('❌ ' + res.data);
                        $btn.prop('disabled', false).text('❌ Ablehnen');
                    }
                }
            });
        });
        // Quick-Approve direkt aus der Liste
        $(document).on('click', '.fobi-btn-approve-quick', function(){
            var $btn = $(this);
            var postId = $btn.data('post-id');
            if( ! confirm('Diesen Nachweis genehmigen?') ) return;

            $btn.prop('disabled', true).text('...');
            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'fobi_approve_nachweis',
                    post_id: postId,
                    nonce: '<?php echo wp_create_nonce('fobi_pruefliste'); ?>'
                },
                success: function(res){
                    if(res.success){
                        $('tr[data-post-id="'+postId+'"]').fadeOut(function(){ $(this).remove(); });
                    } else {
                        alert('❌ ' + (res.data || 'Fehler'));
                        $btn.prop('disabled', false).text('✅');
                    }
                }
            });
        });

        // Neubewertung durch KI
        $(document).on('click', '.fobi-reevaluate-btn', function(){
            var postId = $(this).data('post-id') || currentPostId;
            if( ! postId ) return;

            var $btn = $(this);
            $btn.prop('disabled', true).text('⏳ KI analysiert...');

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'fobi_reevaluate_nachweis',
                    post_id: postId,
                    nonce: '<?php echo wp_create_nonce('fobi_pruefliste'); ?>'
                },
                timeout: 90000,
                success: function(res){
                    $btn.prop('disabled', false).text('🔄 KI-Neubewertung');
                    if(res.success){
                        alert('✅ ' + res.data.message + '\n\nKI-Antwort:\n' + JSON.stringify(res.data.ai_response, null, 2));
                        // Modal neu laden
                        $('.fobi-btn-view[data-post-id="' + postId + '"]').trigger('click');
                    } else {
                        alert('❌ ' + (res.data || 'Fehler'));
                    }
                },
                error: function(){
                    $btn.prop('disabled', false).text('🔄 KI-Neubewertung');
                    alert('❌ Verbindungsfehler oder Timeout');
                }
            });
        });
    });
    </script>

    <style>
    .fobi-pruefliste-wrap {
        max-width: 100%;
        margin: 20px 0;
    }
    .fobi-load-section {
        text-align: center;
        padding: 60px 20px;
        background: #f7f9fc;
        border: 2px dashed #0073aa;
        border-radius: 8px;
    }
    .fobi-btn-load {
        background: #0073aa;
        color: #fff;
        padding: 15px 30px;
        font-size: 18px;
        font-weight: bold;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s;
        display: inline-block;
    }
    .fobi-btn-load:hover {
        background: #005177;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0,115,170,0.3);
    }
    .fobi-btn-load:disabled {
        background: #ccc;
        cursor: not-allowed;
        transform: none;
    }
    .fobi-info-text {
        margin-top: 15px;
        color: #666;
        font-size: 14px;
    }
    .fobi-pruefliste-table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
        box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        margin-top: 20px;
    }
    .fobi-pruefliste-table thead {
        background: #0073aa;
        color: #fff;
    }
    .fobi-pruefliste-table th,
    .fobi-pruefliste-table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #ddd;
    }
    .fobi-pruefliste-table tbody tr:hover {
        background: #f5f5f5;
    }
    .fobi-btn {
        padding: 8px 15px;
        border: none;
        border-radius: 4px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.3s;
        text-decoration: none;
        display: inline-block;
    }
    .fobi-btn-view {
        background: #0073aa;
        color: #fff;
    }
    .fobi-btn-view:hover {
        background: #005177;
    }
    .fobi-no-results {
        padding: 40px;
        text-align: center;
        background: #f0f0f1;
        border-radius: 8px;
        font-size: 18px;
        margin-top: 20px;
    }
    .fobi-modal {
        position: fixed;
        z-index: 99999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
    }
    .fobi-modal-content {
        position: relative;
        background: #fff;
        margin: 5% auto;
        padding: 30px;
        width: 90%;
        max-width: 900px;
        max-height: 85vh;
        overflow-y: auto;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    .fobi-modal-close {
        position: absolute;
        right: 15px;
        top: 15px;
        font-size: 32px;
        font-weight: bold;
        color: #999;
        cursor: pointer;
        line-height: 1;
    }
    .fobi-modal-close:hover {
        color: #000;
    }
    .fobi-detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin: 20px 0;
    }
    .fobi-detail-item {
        padding: 15px;
        background: #f7f7f7;
        border-radius: 4px;
    }
    .fobi-detail-item label {
        display: block;
        font-weight: bold;
        margin-bottom: 5px;
        color: #666;
    }
    .fobi-detail-item .value {
        font-size: 16px;
        color: #000;
    }
    .fobi-attachment-preview {
        margin: 20px 0;
        text-align: center;
    }
    .fobi-attachment-preview img {
        max-width: 100%;
        height: auto;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    .fobi-actions {
        display: flex;
        gap: 15px;
        margin-top: 30px;
        padding-top: 20px;
        border-top: 2px solid #ddd;
    }
    .fobi-btn-approve {
        flex: 1;
        background: #46b450;
        color: #fff;
        padding: 15px;
        font-size: 16px;
        font-weight: bold;
    }
    .fobi-btn-approve:hover {
        background: #2c8a3c;
    }
    .fobi-btn-reject-show {
        flex: 1;
        background: #dc3232;
        color: #fff;
        padding: 15px;
        font-size: 16px;
        font-weight: bold;
    }
    .fobi-btn-reject-show:hover {
        background: #a72222;
    }
    .fobi-reject-form {
        display: none;
        margin-top: 20px;
        padding: 20px;
        background: #fff3cd;
        border: 1px solid #ffc107;
        border-radius: 4px;
    }
    .fobi-reject-form h4 {
        margin-top: 0;
    }
    .fobi-reject-comment {
        width: 100%;
        min-height: 120px;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-family: inherit;
    }
    .fobi-btn-reject-submit {
        background: #dc3232;
        color: #fff;
        padding: 12px 24px;
        margin-top: 10px;
        font-weight: bold;
    }
    .fobi-btn-reject-submit:hover {
        background: #a72222;
    }
    .fobi-debug-box {
        background: #fff3cd;
        border: 1px solid #ffc107;
        padding: 15px;
        margin: 20px 0;
        border-radius: 4px;
    }
    .fobi-debug-box h3 {
        margin-top: 0;
    }
    .fobi-debug-box ul {
        margin: 0;
        padding-left: 20px;
    }
    /* Dashboard-Integration (Forum-Vorbild) */
    .dgptm-dash .fobi-pruefliste-wrap { margin: 0; }
    .dgptm-dash .fobi-load-section { padding: 30px 15px; border: 1px dashed #ccc; border-radius: 4px; background: #f8f9fa; }
    .dgptm-dash .fobi-btn-load { padding: 4px 10px; font-size: 12px; font-weight: 400; border-radius: 4px; background: #0073aa; transition: background .15s; }
    .dgptm-dash .fobi-btn-load:hover { background: #005d8c; transform: none; box-shadow: none; }
    .dgptm-dash .fobi-info-text { font-size: 12px; margin-top: 8px; }
    .dgptm-dash .fobi-pruefliste-table { box-shadow: none; margin-top: 12px; }
    .dgptm-dash .fobi-pruefliste-table thead { background: none; color: #1d2327; }
    .dgptm-dash .fobi-pruefliste-table th { color: #1d2327; padding: 8px 12px; font-size: 12px; font-weight: 600; text-transform: none; letter-spacing: 0; border-bottom: 2px solid #eee; }
    .dgptm-dash .fobi-pruefliste-table td { padding: 10px 12px; border-bottom: 1px solid #eee; font-size: 13px; }
    .dgptm-dash .fobi-pruefliste-table tbody tr:hover { background: #f8f9fa; }
    .dgptm-dash .fobi-btn { padding: 4px 10px; font-size: 12px; border-radius: 4px; transition: background .15s; }
    .dgptm-dash .fobi-btn-view { background: #0073aa; }
    .dgptm-dash .fobi-btn-view:hover { background: #005d8c; }
    .dgptm-dash .fobi-btn-approve { padding: 4px 10px; font-size: 12px; font-weight: 400; flex: none; }
    .dgptm-dash .fobi-btn-reject-show { padding: 4px 10px; font-size: 12px; font-weight: 400; flex: none; }
    .dgptm-dash .fobi-btn-reject-submit { padding: 4px 10px; font-size: 12px; font-weight: 400; }
    .dgptm-dash .fobi-actions { gap: 8px; margin-top: 16px; padding-top: 12px; }
    .dgptm-dash .fobi-detail-grid { gap: 8px; margin: 12px 0; }
    .dgptm-dash .fobi-detail-item { padding: 10px; background: #f8f9fa; border-radius: 4px; }
    .dgptm-dash .fobi-detail-item label { font-size: 12px; color: #888; margin-bottom: 2px; }
    .dgptm-dash .fobi-detail-item .value { font-size: 14px; }
    .dgptm-dash .fobi-no-results { padding: 24px; font-size: 14px; border-radius: 4px; }
    .dgptm-dash .fobi-reject-form { padding: 12px; }
    .dgptm-dash .fobi-reject-comment { min-height: 80px; padding: 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 13px; }
    </style>
    <?php
    return ob_get_clean();
}


/* ============================================================
 * AJAX-Handler: Pruefliste laden
 * ============================================================ */
add_action('wp_ajax_fobi_load_pruefliste', 'fobi_ebcp_ajax_load_pruefliste');

function fobi_ebcp_ajax_load_pruefliste(){
    check_ajax_referer('fobi_pruefliste', 'nonce');
    
    if( ! current_user_can('edit_posts') ){
        wp_send_json_error('Keine Berechtigung.');
    }
    
    $debug = isset($_POST['debug']) && $_POST['debug'] == '1' && current_user_can('manage_options');
    $debug_info = array();
    
    $args = array(
        'post_type' => 'fortbildung',
        'posts_per_page' => -1,
        'post_status' => array('publish', 'draft', 'pending'),
        'orderby' => 'date',
        'order' => 'DESC'
    );
    
    $query = new WP_Query($args);
    
    if($debug){
        $debug_info[] = "Gesamt gefundene Fortbildungen: " . $query->post_count;
    }
    
    $filtered_posts = array();
    
    if( $query->have_posts() ){
        while( $query->have_posts() ){
            $query->the_post();
            $post_id = get_the_ID();
            
            $freigegeben = get_field('freigegeben', $post_id);
            
            if($debug){
                $debug_info[] = "Post #{$post_id}: freigegeben = " . var_export($freigegeben, true) . " (Type: " . gettype($freigegeben) . ")";
            }
            
            if( empty($freigegeben) || $freigegeben === false || $freigegeben === 0 || $freigegeben === '0' ){
                $filtered_posts[] = $post_id;
                
                if($debug){
                    $debug_info[] = "  ↳ Post #{$post_id} wird angezeigt (nicht freigegeben)";
                }
            }
        }
        wp_reset_postdata();
    }
    
    if($debug){
        $debug_info[] = "Gefilterte (nicht freigegebene) Fortbildungen: " . count($filtered_posts);
    }
    
    ob_start();
    
    if($debug){
        echo '<div class="fobi-debug-box">';
        echo '<h3>🔍 Debug-Informationen</h3>';
        echo '<ul>';
        foreach($debug_info as $info){
            echo '<li>' . esc_html($info) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }
    
    ?>
    <h2>Zu prüfende Fortbildungsnachweise (<?php echo count($filtered_posts); ?>)</h2>
    
    <?php if( ! empty($filtered_posts) ): ?>
        <table class="fobi-pruefliste-table">
            <thead>
                <tr>
                    <th>Datum</th>
                    <th>Benutzer</th>
                    <th>Titel</th>
                    <th>Art</th>
                    <th>Pkt.</th>
                    <th>KI %</th>
                    <th>Status</th>
                    <th>Aktionen</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($filtered_posts as $post_id): 
                    $post = get_post($post_id);
                    
                    // User-Feld laden (ACF return_format = array)
                    $user_field = get_field('user', $post_id);
                    $user = null;
                    
                    if($debug){
                        $debug_info[] = "Post #{$post_id} user field: " . var_export($user_field, true);
                    }
                    
                    if( is_array($user_field) && isset($user_field['ID']) ){
                        $user = get_userdata($user_field['ID']);
                    } elseif( is_object($user_field) && isset($user_field->ID) ){
                        $user = $user_field;
                    } elseif( is_numeric($user_field) ){
                        $user = get_userdata($user_field);
                    }
                    
                    $date = get_field('date', $post_id);
                    $location = get_field('location', $post_id);
                    $type = get_field('type', $post_id);
                    $points = get_field('points', $post_id);
                    
                    $post_status = get_post_status($post_id);
                    $status_label = '';
                    if($post_status === 'draft'){
                        $status_label = '<span style="background:#dc3232;color:#fff;padding:3px 8px;border-radius:3px;font-size:12px;">Entwurf</span>';
                    } elseif($post_status === 'pending'){
                        $status_label = '<span style="background:#f0ad4e;color:#fff;padding:3px 8px;border-radius:3px;font-size:12px;">Ausstehend</span>';
                    } else {
                        $status_label = '<span style="background:#0073aa;color:#fff;padding:3px 8px;border-radius:3px;font-size:12px;">Veröffentlicht</span>';
                    }
                ?>
                    <?php
                        $ai_conf = get_post_meta($post_id, '_ebcp_ai_confidence', true);
                        $ai_cat = get_post_meta($post_id, '_ebcp_category_key', true);
                        $has_att = !empty(get_field('attachements', $post_id));
                    ?>
                    <tr data-post-id="<?php echo $post_id; ?>">
                        <td><?php echo esc_html($date ? $date : '-'); ?></td>
                        <td><?php echo $user ? esc_html($user->display_name) : '<em>Unbekannt</em>'; ?></td>
                        <td><strong><?php echo esc_html($post->post_title); ?></strong></td>
                        <td><?php echo esc_html($type ? $type : '-'); ?></td>
                        <td><?php echo esc_html($points ? number_format($points, 1) : '-'); ?></td>
                        <td><?php echo $ai_conf ? intval($ai_conf * 100) . '%' : '-'; ?></td>
                        <td><?php echo $status_label; ?></td>
                        <td style="white-space:nowrap;">
                            <button class="fobi-btn fobi-btn-view" data-post-id="<?php echo $post_id; ?>">👁️</button>
                            <?php if($has_att): ?>
                                <button class="fobi-btn fobi-reevaluate-btn" data-post-id="<?php echo $post_id; ?>" title="KI-Neubewertung" style="background:#2271b1;color:#fff;">🔄</button>
                            <?php endif; ?>
                            <button class="fobi-btn fobi-btn-approve-quick" data-post-id="<?php echo $post_id; ?>" title="Genehmigen" style="background:#46b450;color:#fff;">✅</button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="fobi-no-results">✅ Keine zu prüfenden Nachweise vorhanden.</p>
    <?php endif; ?>
    <?php
    
    wp_send_json_success(array('html' => ob_get_clean()));
}

/* ============================================================
 * AJAX-Handler: Nachweis genehmigen
 * ============================================================ */
add_action('wp_ajax_fobi_approve_nachweis', 'fobi_ebcp_ajax_approve_nachweis');

function fobi_ebcp_ajax_approve_nachweis(){
    check_ajax_referer('fobi_pruefliste', 'nonce');

    if( ! current_user_can('edit_posts') ){
        wp_send_json_error('Keine Berechtigung.');
    }

    $post_id = intval($_POST['post_id']);
    $post = get_post($post_id);

    if( ! $post || $post->post_type !== 'fortbildung' ){
        wp_send_json_error('Fortbildung nicht gefunden.');
    }

    // Freigeben
    update_field('freigegeben', true, $post_id);
    wp_update_post(array('ID' => $post_id, 'post_status' => 'publish'));

    // E-Mail an Benutzer senden
    $user_field = get_field('user', $post_id);
    $user = null;
    if( is_array($user_field) && isset($user_field['ID']) ){
        $user = get_userdata($user_field['ID']);
    } elseif( is_numeric($user_field) ){
        $user = get_userdata($user_field);
    }

    if( $user ){
        $settings = fobi_ebcp_get_settings();
        fobi_ebcp_send_approved_email($user, $post_id, $settings);
    }

    wp_send_json_success(array('message' => 'Nachweis genehmigt und Benutzer benachrichtigt.'));
}

/* ============================================================
 * AJAX-Handler: Nachweis ablehnen
 * ============================================================ */
add_action('wp_ajax_fobi_reject_nachweis', 'fobi_ebcp_ajax_reject_nachweis');

function fobi_ebcp_ajax_reject_nachweis(){
    check_ajax_referer('fobi_pruefliste', 'nonce');

    if( ! current_user_can('edit_posts') ){
        wp_send_json_error('Keine Berechtigung.');
    }

    $post_id = intval($_POST['post_id']);
    $comment = sanitize_textarea_field($_POST['comment'] ?? '');
    $post = get_post($post_id);

    if( ! $post || $post->post_type !== 'fortbildung' ){
        wp_send_json_error('Fortbildung nicht gefunden.');
    }

    // Status auf Entwurf setzen und Ablehnungsgrund speichern
    wp_update_post(array('ID' => $post_id, 'post_status' => 'draft'));
    update_post_meta($post_id, 'fobi_reject_comment', $comment);
    update_post_meta($post_id, 'fobi_rejected_at', current_time('mysql'));

    // E-Mail an Benutzer senden
    $user_field = get_field('user', $post_id);
    $user = null;
    if( is_array($user_field) && isset($user_field['ID']) ){
        $user = get_userdata($user_field['ID']);
    } elseif( is_numeric($user_field) ){
        $user = get_userdata($user_field);
    }

    if( $user ){
        $settings = fobi_ebcp_get_settings();
        fobi_ebcp_send_rejected_email($user, $post_id, $comment, $settings);
    }

    wp_send_json_success(array('message' => 'Nachweis abgelehnt und Benutzer benachrichtigt.'));
}

/* ============================================================
 * AJAX-Handler: Neubewertung durch KI
 * ============================================================ */
add_action('wp_ajax_fobi_reevaluate_nachweis', 'fobi_ebcp_ajax_reevaluate');

function fobi_ebcp_ajax_reevaluate(){
    check_ajax_referer('fobi_pruefliste', 'nonce');

    if( ! current_user_can('edit_posts') ){
        wp_send_json_error('Keine Berechtigung.');
    }

    $post_id = intval($_POST['post_id']);
    $post = get_post($post_id);

    if( ! $post || $post->post_type !== 'fortbildung' ){
        wp_send_json_error('Fortbildung nicht gefunden.');
    }

    $s = fobi_ebcp_get_settings();

    // Attachment finden — ACF return_format kann variieren (ID, URL, Array, Object)
    $attachment_raw = function_exists('get_field') ? get_field('attachements', $post_id) : null;
    $filepath = '';
    $mime = '';
    $is_temp = false;

    // Fallback: auch post_meta direkt pruefen
    if( empty($attachment_raw) ){
        $attachment_raw = get_post_meta($post_id, 'attachements', true);
    }

    // Debug-Log fuer Diagnose
    error_log(sprintf('[Fobi-Reanalyze] Post %d: attachment_raw type=%s, value=%s',
        $post_id, gettype($attachment_raw), is_scalar($attachment_raw) ? $attachment_raw : json_encode($attachment_raw)));

    if( is_numeric($attachment_raw) && intval($attachment_raw) > 0 ){
        // Attachment-ID
        $filepath = get_attached_file(intval($attachment_raw));
        $mime = get_post_mime_type(intval($attachment_raw));
    } elseif( is_string($attachment_raw) ){
        // Geschuetzte Download-URL: ?fobi_download=1&attachment_id=XXXX
        if( preg_match('/attachment_id=(\d+)/', $attachment_raw, $m) ){
            $att_id = intval($m[1]);
            $filepath = get_attached_file($att_id);
            $mime = get_post_mime_type($att_id);
        }
        // Direkte URL — in Temp-Datei herunterladen
        elseif( filter_var($attachment_raw, FILTER_VALIDATE_URL) ){
            $tmp = download_url($attachment_raw, 30);
            if( !is_wp_error($tmp) ){
                $filepath = $tmp;
                $is_temp = true;
                $ext = strtolower(pathinfo(parse_url($attachment_raw, PHP_URL_PATH), PATHINFO_EXTENSION));
                $mime_map = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
                $mime = $mime_map[$ext] ?? 'application/pdf';
            }
        }
    } elseif( is_array($attachment_raw) ){
        // Array: ACF gibt {ID, url, ...} oder {id, url, ...} zurueck
        $att_id = $attachment_raw['ID'] ?? $attachment_raw['id'] ?? 0;
        $att_url = $attachment_raw['url'] ?? '';
        if( $att_id ){
            $filepath = get_attached_file(intval($att_id));
            $mime = get_post_mime_type(intval($att_id));
        } elseif( $att_url && filter_var($att_url, FILTER_VALIDATE_URL) ){
            $tmp = download_url($att_url, 30);
            if( !is_wp_error($tmp) ){
                $filepath = $tmp;
                $is_temp = true;
                $ext = strtolower(pathinfo(parse_url($att_url, PHP_URL_PATH), PATHINFO_EXTENSION));
                $mime_map = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];
                $mime = $mime_map[$ext] ?? 'application/pdf';
            }
        }
    } elseif( is_object($attachment_raw) && isset($attachment_raw->ID) ){
        $filepath = get_attached_file($attachment_raw->ID);
        $mime = get_post_mime_type($attachment_raw->ID);
    }

    if( empty($filepath) || !file_exists($filepath) ){
        wp_send_json_error(sprintf(
            'Kein Nachweis-Dokument hinterlegt oder Datei nicht gefunden. (Typ: %s, Wert: %s)',
            gettype($attachment_raw),
            is_scalar($attachment_raw) ? substr($attachment_raw, 0, 100) : json_encode($attachment_raw)
        ));
    }

    // User-Daten fuer Namensvergleich
    $user_field = function_exists('get_field') ? get_field('user', $post_id) : null;
    $expected_name = '';
    if( is_array($user_field) && isset($user_field['ID']) ){
        $u = get_userdata($user_field['ID']);
        if($u) $expected_name = trim($u->first_name . ' ' . $u->last_name) ?: $u->display_name;
    } elseif( is_numeric($user_field) ){
        $u = get_userdata($user_field);
        if($u) $expected_name = trim($u->first_name . ' ' . $u->last_name) ?: $u->display_name;
    }

    // KI-Analyse ausfuehren
    $result = fobi_ebcp_analyze_document($filepath, $mime, $expected_name, $s);

    // Temp-Datei aufraeumen
    if( $is_temp && !empty($filepath) && file_exists($filepath) ){
        @unlink($filepath);
    }

    if( !$result['ok'] ){
        wp_send_json_error([
            'message' => 'KI-Analyse fehlgeschlagen: ' . ($result['error'] ?? 'Unbekannt'),
            'error_detail' => $result['error_detail'] ?? '',
            'raw' => $result,
        ]);
    }

    $data = $result['data'];
    $confidence = floatval($result['confidence'] ?? 0);

    // EBCP-Punkte vom Dokument haben Vorrang
    $ebcp_from_doc = floatval($data['ebcp_points'] ?? 0);
    if ($ebcp_from_doc > 0) {
        $points = $ebcp_from_doc;
    } else {
        $points = fobi_ebcp_calc_points($data, $s);
    }
    $category_key = fobi_ebcp_get_category_key($data);
    $category_label = fobi_ebcp_get_category_label($category_key, $s);

    // ============================================================
    // Mehrtages-Erkennung: fehlende Tage nachtraeglich anlegen
    // ============================================================
    $start_date = $data['start_date'] ?? '';
    $end_date = $data['end_date'] ?? $start_date;
    $num_days = 1;
    $extra_posts_created = 0;

    $start_ts_calc = strtotime($start_date);
    $end_ts_calc = strtotime($end_date);

    if ($start_ts_calc && $end_ts_calc && $end_ts_calc > $start_ts_calc) {
        $num_days = (int) round(($end_ts_calc - $start_ts_calc) / 86400) + 1;
    }

    // Aktuellen Post aktualisieren (wird Tag 1)
    if( function_exists('update_field') ){
        $title_base = html_entity_decode(strip_tags(get_the_title($post_id)), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Alten Suffix entfernen falls vorhanden
        $title_base = preg_replace('/\s*\(Tag\s+\d+\/\d+\)\s*$/i', '', $title_base);

        if ($num_days > 1) {
            wp_update_post(['ID' => $post_id, 'post_title' => $title_base . ' (Tag 1/' . $num_days . ')']);
        }

        update_field('points', $points, $post_id);
        update_field('type', $category_label, $post_id);
        if( !empty($data['location']) ) update_field('location', $data['location'], $post_id);
        if( $start_ts_calc ) update_field('date', date('Y-m-d', $start_ts_calc), $post_id);
        if (!empty($data['vnr'])) update_field('vnr', $data['vnr'], $post_id);
    }

    // Meta aktualisieren
    update_post_meta($post_id, '_ebcp_ai_response', json_encode($data, JSON_UNESCAPED_UNICODE));
    update_post_meta($post_id, '_ebcp_ai_confidence', $confidence);
    update_post_meta($post_id, '_ebcp_category_key', $category_key);
    update_post_meta($post_id, '_ebcp_raw_category', $data['category']);
    update_post_meta($post_id, '_ebcp_doc_type', $data['doc_type'] ?? '');
    update_post_meta($post_id, '_ebcp_reevaluated_at', current_time('mysql'));

    // Mehrtages-Gruppen-Meta
    if ($num_days > 1) {
        $group_id = get_post_meta($post_id, '_fobi_group_id', true);
        if (empty($group_id)) {
            $group_id = 'fobi_group_' . wp_generate_password(12, false);
        }
        update_post_meta($post_id, '_fobi_group_id', $group_id);
        update_post_meta($post_id, '_fobi_group_day', 1);
        update_post_meta($post_id, '_fobi_group_total_days', $num_days);

        // Fehlende Tage anlegen (Tag 2 bis N)
        $post_obj = get_post($post_id);
        $author_id = $post_obj ? $post_obj->post_author : get_current_user_id();
        $user_field_val = function_exists('get_field') ? get_field('user', $post_id) : $author_id;
        $user_id_for_field = is_array($user_field_val) ? ($user_field_val['ID'] ?? $author_id) : ($user_field_val ?: $author_id);
        $attachment_val = $attachment_raw; // Gleicher Nachweis fuer alle Tage

        for ($day = 2; $day <= $num_days; $day++) {
            $day_date = date('Y-m-d', strtotime('+' . ($day - 1) . ' days', $start_ts_calc));

            // Duplikat-Check: existiert dieser Tag schon?
            $existing_day = get_posts([
                'post_type' => 'fortbildung',
                'posts_per_page' => 1,
                'meta_query' => [
                    ['key' => '_fobi_group_id', 'value' => $group_id],
                    ['key' => '_fobi_group_day', 'value' => $day],
                ],
            ]);

            if (!empty($existing_day)) continue;

            $new_id = wp_insert_post([
                'post_type' => 'fortbildung',
                'post_title' => $title_base . ' (Tag ' . $day . '/' . $num_days . ')',
                'post_status' => 'publish',
                'post_author' => $author_id,
            ]);

            if (is_wp_error($new_id) || !$new_id) continue;

            $extra_posts_created++;

            if (function_exists('update_field')) {
                update_field('user', $user_id_for_field, $new_id);
                update_field('date', $day_date, $new_id);
                update_field('location', $data['location'] ?? '', $new_id);
                update_field('type', $category_label, $new_id);
                update_field('points', $points, $new_id);
                if (!empty($data['vnr'])) update_field('vnr', $data['vnr'], $new_id);
                update_field('token', wp_generate_password(32, false), $new_id);
                update_field('freigegeben', false, $new_id);

                if ($attachment_val) {
                    update_field('attachements', $attachment_val, $new_id);
                }
            }

            update_post_meta($new_id, '_ebcp_ai_response', json_encode($data, JSON_UNESCAPED_UNICODE));
            update_post_meta($new_id, '_ebcp_ai_confidence', $confidence);
            update_post_meta($new_id, '_ebcp_category_key', $category_key);
            update_post_meta($new_id, '_fobi_group_id', $group_id);
            update_post_meta($new_id, '_fobi_group_day', $day);
            update_post_meta($new_id, '_fobi_group_total_days', $num_days);
        }
    }

    // Erfolgsmeldung
    $msg = sprintf('Neubewertung: %s — %s Pkt/Tag (Konfidenz: %d%%)',
        $category_label, number_format($points, 1), intval($confidence * 100));

    if ($num_days > 1) {
        $total = $points * $num_days;
        $msg .= sprintf(' | Mehrtaegig: %d Tage × %s = %s Pkt gesamt', $num_days, number_format($points, 1), number_format($total, 1));
        if ($extra_posts_created > 0) {
            $msg .= sprintf(' | %d neue Tageseintraege angelegt', $extra_posts_created);
        }
    }

    wp_send_json_success(array(
        'message' => $msg,
        'ai_response' => $data,
        'confidence' => $confidence,
        'points' => $points,
        'category_key' => $category_key,
        'category_label' => $category_label,
        'num_days' => $num_days,
        'extra_posts_created' => $extra_posts_created,
    ));
}


/* ============================================================
 * AJAX-Handler: Nachweis-Details laden (Modal)
 * ============================================================ */
add_action('wp_ajax_fobi_load_nachweis_details', 'fobi_ebcp_ajax_load_details');

function fobi_ebcp_ajax_load_details(){
    check_ajax_referer('fobi_pruefliste', 'nonce');
    
    if( ! current_user_can('edit_posts') ){
        wp_send_json_error('Keine Berechtigung.');
    }
    
    $post_id = intval($_POST['post_id']);
    $post = get_post($post_id);
    
    if( ! $post || $post->post_type !== 'fortbildung' ){
        wp_send_json_error('Fortbildung nicht gefunden.');
    }
    
    // User-Feld laden (ACF return_format = array)
    $user_field = get_field('user', $post_id);
    $user = null;
    
    if( is_array($user_field) && isset($user_field['ID']) ){
        $user = get_userdata($user_field['ID']);
    } elseif( is_object($user_field) && isset($user_field->ID) ){
        $user = $user_field;
    } elseif( is_numeric($user_field) ){
        $user = get_userdata($user_field);
    }
    
    $date = get_field('date', $post_id);
    $location = get_field('location', $post_id);
    $type = get_field('type', $post_id);
    $points = get_field('points', $post_id);
    
    // Attachment-Feld laden (ACF return_format = url)
    $attachments = get_field('attachements', $post_id);
    $attachment_url = '';
    $attachment_type = '';
    
    if( is_string($attachments) && filter_var($attachments, FILTER_VALIDATE_URL) ){
        $attachment_url = $attachments;
        $ext = pathinfo($attachment_url, PATHINFO_EXTENSION);
        if(in_array(strtolower($ext), array('pdf'))){
            $attachment_type = 'application/pdf';
        } elseif(in_array(strtolower($ext), array('jpg','jpeg','png','gif','webp'))){
            $attachment_type = 'image/' . $ext;
        }
    } elseif( is_numeric($attachments) ){
        $attachment_url = wp_get_attachment_url($attachments);
        $attachment_type = get_post_mime_type($attachments);
    } elseif( is_array($attachments) && isset($attachments['url']) ){
        $attachment_url = $attachments['url'];
        $attachment_type = isset($attachments['mime_type']) ? $attachments['mime_type'] : '';
    }
    
    ob_start();
    ?>
    <h2><?php echo esc_html(get_the_title($post_id)); ?></h2>
    
    <div class="fobi-detail-grid">
        <div class="fobi-detail-item">
            <label>Benutzer</label>
            <div class="value"><?php echo $user ? esc_html($user->display_name) . ' (' . esc_html($user->user_email) . ')' : '<em>Nicht zugeordnet</em>'; ?></div>
        </div>
        
        <div class="fobi-detail-item">
            <label>Datum</label>
            <div class="value"><?php echo esc_html($date ? $date : '-'); ?></div>
        </div>
        
        <div class="fobi-detail-item">
            <label>Ort</label>
            <div class="value"><?php echo esc_html($location ? $location : '-'); ?></div>
        </div>
        
        <div class="fobi-detail-item">
            <label>Art</label>
            <div class="value"><?php echo esc_html($type ? $type : '-'); ?></div>
        </div>
        
        <div class="fobi-detail-item">
            <label>Punkte</label>
            <div class="value"><strong><?php echo esc_html($points ? number_format($points, 1) : '0.0'); ?></strong></div>
        </div>
        
        <div class="fobi-detail-item">
            <label>Hochgeladen am</label>
            <div class="value"><?php echo get_the_date('d.m.Y H:i', $post_id); ?></div>
        </div>
    </div>
    
    <?php if( $attachment_url ): ?>
        <div class="fobi-attachment-preview">
            <h3>Nachweis-Dokument</h3>
            <?php if( strpos($attachment_type, 'image') !== false ): ?>
                <img src="<?php echo esc_url($attachment_url); ?>" alt="Nachweis">
            <?php elseif( strpos($attachment_type, 'pdf') !== false || pathinfo($attachment_url, PATHINFO_EXTENSION) === 'pdf' ): ?>
                <p><a href="<?php echo esc_url($attachment_url); ?>" target="_blank" class="fobi-btn" style="background:#dc3232;color:#fff;">📄 PDF in neuem Tab öffnen</a></p>
                <iframe src="<?php echo esc_url($attachment_url); ?>" style="width: 100%; height: 600px; border: 1px solid #ddd; border-radius: 4px;"></iframe>
            <?php else: ?>
                <p><a href="<?php echo esc_url($attachment_url); ?>" target="_blank" class="fobi-btn">📎 Dokument herunterladen</a></p>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div class="fobi-attachment-preview">
            <p style="color:#999;font-style:italic;">⚠️ Kein Nachweis-Dokument hinterlegt</p>
        </div>
    <?php endif; ?>
    
    <!-- KI-Rohdaten -->
    <?php
    $ai_response = get_post_meta($post_id, '_ebcp_ai_response', true);
    $ai_confidence = get_post_meta($post_id, '_ebcp_ai_confidence', true);
    $ai_category = get_post_meta($post_id, '_ebcp_category_key', true);
    $ai_doc_type = get_post_meta($post_id, '_ebcp_doc_type', true);
    $reevaluated = get_post_meta($post_id, '_ebcp_reevaluated_at', true);
    ?>
    <?php if ($ai_response): ?>
    <details style="margin: 15px 0; padding: 10px; background: #f0f6fc; border: 1px solid #c5d9ed; border-radius: 4px;">
        <summary style="cursor: pointer; font-weight: 600; font-size: 13px;">KI-Analyse anzeigen<?php echo $reevaluated ? ' (zuletzt: ' . esc_html($reevaluated) . ')' : ''; ?></summary>
        <pre style="margin: 8px 0; padding: 8px; background: #fff; border: 1px solid #ddd; border-radius: 4px; font-size: 11px; white-space: pre-wrap; overflow: auto; max-height: 300px;"><?php echo esc_html($ai_response); ?></pre>
        <p style="font-size: 12px; margin: 4px 0;">Konfidenz: <strong><?php echo $ai_confidence ? intval($ai_confidence * 100) . '%' : 'k.A.'; ?></strong> | Kategorie-Key: <code><?php echo esc_html($ai_category ?: 'k.A.'); ?></code> | Dokumenttyp: <code><?php echo esc_html($ai_doc_type ?: 'k.A.'); ?></code></p>
    </details>
    <?php endif; ?>

    <div class="fobi-actions">
        <button class="fobi-btn fobi-btn-approve">✅ Genehmigen & E-Mail senden</button>
        <button class="fobi-btn fobi-btn-reject-show">❌ Ablehnen</button>
        <button class="fobi-btn fobi-reevaluate-btn" data-post-id="<?php echo $post_id; ?>" style="background: #2271b1; color: #fff;">🔄 KI-Neubewertung</button>
    </div>
    
    <div class="fobi-reject-form">
        <h4>⚠️ Ablehnungsgrund angeben</h4>
        <textarea class="fobi-reject-comment" placeholder="Bitte geben Sie einen Grund für die Ablehnung an, der dem Benutzer per E-Mail zugestellt wird..."></textarea>
        <button class="fobi-btn fobi-btn-reject-submit">❌ Ablehnen & E-Mail senden</button>
    </div>
    <?php
    
    wp_send_json_success(array('html' => ob_get_clean()));
}