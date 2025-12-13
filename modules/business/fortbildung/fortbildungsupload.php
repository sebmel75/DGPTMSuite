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

        // EBCP-Matrix (v3.10: Workshop/Kongress-Differenzierung)
        'ebcp_mapping_json' => json_encode(array(
            // === PASSIVE TEILNAHME ===
            array('key'=>'passive_workshop_inhouse',       'label'=>'In-house Workshop',                  'points'=>1),
            array('key'=>'passive_workshop_national',      'label'=>'Nationaler Workshop',                'points'=>1),
            array('key'=>'passive_workshop_international', 'label'=>'Internationaler Workshop',           'points'=>1),
            array('key'=>'passive_webinar',                'label'=>'Webinar (passiv)',                   'points'=>1),
            array('key'=>'passive_seminar_national',       'label'=>'Nationales Seminar',                 'points'=>4),
            array('key'=>'passive_kongress_national',      'label'=>'Nationaler Kongress',                'points'=>4),
            array('key'=>'passive_kongress_international', 'label'=>'Internationaler Kongress',           'points'=>6),
            array('key'=>'passive_dgptm_jahrestagung',     'label'=>'DGPTM Jahrestagung (Fokustagung Herz)', 'points'=>6),
            
            // === AKTIVE TEILNAHME ===
            array('key'=>'active_inhouse',        'label'=>'In-house Vortrag/Workshop',           'points'=>2),
            array('key'=>'active_webinar',        'label'=>'Webinar (aktiv)',                     'points'=>2),
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
add_action('admin_menu', function(){
    add_submenu_page(
        'edit.php?post_type=fortbildung',
        'EBCP-Upload (KI) – Einstellungen',
        'EBCP-Upload (KI)',
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
                                <option value="claude-sonnet-4-5-20250929" <?php selected($s['claude_model'],'claude-sonnet-4-5-20250929'); ?>>Claude Sonnet 4.5 (neueste, empfohlen) ⭐</option>
                                <option value="claude-3-5-sonnet-20240620" <?php selected($s['claude_model'],'claude-3-5-sonnet-20240620'); ?>>Claude 3.5 Sonnet (stabil)</option>
                                <option value="claude-3-5-haiku-20241022" <?php selected($s['claude_model'],'claude-3-5-haiku-20241022'); ?>>Claude 3.5 Haiku (günstiger)</option>
                                <option value="claude-3-opus-20240229" <?php selected($s['claude_model'],'claude-3-opus-20240229'); ?>>Claude 3 Opus (höchste Genauigkeit)</option>
                            </select>
                            <p class="description"><strong>Sonnet 4.5:</strong> Neueste Version, beste Performance | <strong>Kosten:</strong> ~$0.02/Analyse</p>
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
                            <th style="width: 10%;">Kategorie-Gruppe</th>
                        </tr>
                    </thead>
                    <tbody id="ebcp-matrix-rows">
                        <?php
                        $matrix = json_decode($s['ebcp_mapping_json'], true);
                        if(!is_array($matrix)) $matrix = array();
                        
                        // Gruppierung für bessere Übersicht
                        $groups = array(
                            'Passive Teilnahme' => array('passive_inhouse', 'passive_webinar', 'passive_national', 'passive_international'),
                            'Aktive Teilnahme' => array('active_inhouse', 'active_webinar', 'active_national_talk', 'active_national_mod', 'active_intl_talk', 'active_intl_mod'),
                            'Publikationen' => array('pub_abstract', 'pub_no_editorial', 'pub_with_editorial'),
                            'ECTS' => array('ects_per_credit')
                        );
                        
                        foreach($groups as $group_name => $group_keys){
                            echo '<tr class="group-header"><td colspan="4" style="background: #f0f0f1; font-weight: bold; padding: 8px;">' . esc_html($group_name) . '</td></tr>';
                            
                            foreach($group_keys as $key){
                                $item = null;
                                foreach($matrix as $m){
                                    if($m['key'] === $key){
                                        $item = $m;
                                        break;
                                    }
                                }
                                
                                if(!$item){
                                    $item = array('key' => $key, 'label' => $key, 'points' => 0);
                                }
                                
                                echo '<tr>';
                                echo '<td><code>' . esc_html($item['key']) . '</code></td>';
                                echo '<td><input type="text" name="matrix_label[' . esc_attr($item['key']) . ']" value="' . esc_attr($item['label']) . '" class="regular-text"></td>';
                                echo '<td><input type="number" name="matrix_points[' . esc_attr($item['key']) . ']" value="' . esc_attr($item['points']) . '" step="0.5" min="0" style="width: 80px;"></td>';
                                echo '<td style="color: #666; font-size: 0.9em;">' . esc_html($group_name) . '</td>';
                                echo '</tr>';
                            }
                        }
                        ?>
                    </tbody>
                </table>
                
                <p class="description" style="margin-top: 15px;">
                    <strong>Hinweis:</strong> Die Kategorie-Keys werden automatisch aus den Claude-Analyse-Daten ermittelt.<br>
                    Die deutschen Bezeichnungen werden in der Fortbildungsliste angezeigt.
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
    
    $auto_approve = $confidence >= $s['auto_approve_min_conf'];
    
    $participant_valid = fobi_ebcp_verify_participant($data['participant'], $expected_fullname);
    $event_valid = fobi_ebcp_verify_event($data['title'], $data['location'], $data['start_date'], $s);
    $points = fobi_ebcp_calc_points($data, $s);
    
    $is_valid = $participant_valid && $event_valid && $points > 0;
    // Schwellenwert von 60% auf 70% erhöht für bessere Qualität
    $is_suspicious = !$participant_valid || !$event_valid || $confidence < 0.7;
    
    if( $is_suspicious ){
        $status = 'suspicious';
        $status_label = 'Verdächtig';
    } elseif( $auto_approve && $is_valid ){
        $status = 'approved';
        $status_label = 'Automatisch freigegeben';
    } else {
        $status = 'pending';
        $status_label = 'Prüfung erforderlich';
    }
    
    $post_data = array(
        'post_type'   => 'fortbildung',
        'post_title'  => $data['title'] ?: 'Fortbildung vom '.date('d.m.Y'),
        'post_status' => 'publish', // Immer publish, Freigabe wird über ACF-Feld gesteuert
        'post_author' => $u->ID,
    );
    
    $post_id = wp_insert_post($post_data);
    
    if( is_wp_error($post_id) ){
        wp_send_json_error('Fehler beim Erstellen: '.$post_id->get_error_message());
    }
    
    // Notification NACH Post-Insert senden, damit post_id verfügbar ist
    if( $is_suspicious && $s['notify_on_suspicious'] === '1' ){
        fobi_ebcp_send_notification($u, $data, $confidence, $expected_fullname, $s, $post_id);
    }
    
    if( function_exists('update_field') ){
        // Benutzer-ID zuweisen (ACF user field)
        update_field('user', $u->ID, $post_id);
        
        // Datum im Format Y-m-d
        if( !empty($data['start_date']) ){
            // Versuche das Datum zu parsen
            $timestamp = strtotime($data['start_date']);
            if( $timestamp !== false ){
                $date_formatted = date('Y-m-d', $timestamp);
                update_field('date', $date_formatted, $post_id);
            } else {
                // Fallback: Versuche direktes Speichern, falls bereits im richtigen Format
                update_field('date', $data['start_date'], $post_id);
            }
        }
        
        // Ort
        update_field('location', $data['location'], $post_id);
        
        // Art (Deutsche Bezeichnung aus Mapping-Matrix)
        $category_key = fobi_ebcp_get_category_key($data);
        $category_label = fobi_ebcp_get_category_label($category_key, $s);
        update_field('type', $category_label, $post_id);
        
        // Zusätzliche Metadaten für interne Verwendung speichern
        update_post_meta($post_id, '_ebcp_category_key', $category_key);
        update_post_meta($post_id, '_ebcp_raw_category', $data['category']);
        update_post_meta($post_id, '_ebcp_raw_subtype', $data['subtype']);
        update_post_meta($post_id, '_ebcp_active_role', $data['active_role']);
        
        // Punkte
        update_field('points', $points, $post_id);
        
        // Token für Verifizierung (kann später verwendet werden)
        update_field('token', wp_generate_password(32, false), $post_id);
        
        // Freigabe-Status
        if( $status === 'approved' ){
            update_field('freigegeben', true, $post_id);
            update_field('freigabe_durch', 'KI (automatisch)', $post_id);
            update_field('freigabe_mail', get_option('admin_email'), $post_id);
        } else {
            update_field('freigegeben', false, $post_id);
        }
    }
    
    if( $s['store_proof_as_attachment'] === '1' ){
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $attachment_id = media_handle_upload('fobi_file', $post_id);
        if( ! is_wp_error($attachment_id) && function_exists('update_field') ){
            // ACF field heißt "attachements" (mit e)
            update_field('attachements', $attachment_id, $post_id);
        }
    }
    
    // Deutsche Kategoriebezeichnung für Anzeige
    $category_key = fobi_ebcp_get_category_key($data);
    $category_label = fobi_ebcp_get_category_label($category_key, $s);
    
    $message = sprintf(
        '<strong>%s</strong><br><br>📄 <strong>Titel:</strong> %s<br>👤 <strong>Teilnehmer:</strong> %s<br>📍 <strong>Ort:</strong> %s<br>📅 <strong>Datum:</strong> %s<br>🏷️ <strong>Art:</strong> %s<br>⭐ <strong>Punkte:</strong> %s<br>🎯 <strong>Konfidenz:</strong> %d%%',
        $status_label,
        esc_html($data['title']),
        esc_html($data['participant']),
        esc_html($data['location']),
        esc_html($data['start_date']),
        esc_html($category_label),
        esc_html(number_format($points, 1)),
        intval($confidence * 100)
    );
    
    // Link zur erstellten Fortbildung nur für Benutzer mit Bearbeitungsrechten anzeigen
    if( current_user_can('edit_post', $post_id) ){
        $edit_link = admin_url('post.php?post='.$post_id.'&action=edit');
        $message .= sprintf('<br><br>📋 <a href="%s" target="_blank">Fortbildung im Backend anzeigen</a>', esc_url($edit_link));
    }
    
    if( $status === 'suspicious' ){
        $message .= '<br><br>⚠️ <strong>Hinweis:</strong> Dieser Nachweis muss manuell geprüft werden.';
    }
    
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
    
    $prompt = "Analysiere dieses Fortbildungsnachweis-Dokument und extrahiere die Daten als JSON.

WICHTIG - TEILNEHMER-VALIDIERUNG:
- Gib NUR einen participant-Wert zurück, wenn eindeutig ein PERSÖNLICHER TEILNEHMERNAME auf dem Dokument steht
- Bei Veranstaltungsflyern oder Ankündigungen OHNE Teilnehmernamen: participant = \"\" (leer)
- Der Teilnehmername muss auf dem Dokument als Person identifizierbar sein
- NICHT verwechseln: Veranstalter, Referenten oder allgemeine Namen sind KEINE Teilnehmer

ERWARTETER TEILNEHMER: {$expected_name}

KATEGORIEN:
{$categories_desc}

INTERNATIONALE MEETINGS: {$intl_list}

Antworte NUR mit JSON in diesem Format:
{
  \"participant\": \"Vollständiger Name des TEILNEHMERS (leer wenn kein Teilnehmer erkennbar)\",
  \"title\": \"Veranstaltungstitel\",
  \"location\": \"Stadt, Land\",
  \"start_date\": \"YYYY-MM-DD\",
  \"category\": \"passive_kongress_national\",
  \"subtype\": \"\",
  \"active_role\": \"no\",
  \"ects\": 0,
  \"confidence\": 0.95
}

WICHTIG für category-Werte: 
Verwende DIREKT die Keys aus der Kategorieliste oben (z.B. 'passive_workshop_national', 'passive_kongress_international', 'passive_dgptm_jahrestagung')
Für DGPTM oder DGfK Jahrestagung verwende IMMER: \"category\": \"passive_dgptm_jahrestagung\"

Konfidenz-Bewertung:
- 0.9-1.0: Vollständige, klare Teilnahmebestätigung mit allen Daten
- 0.7-0.9: Gute Qualität, kleine Unschärfen
- 0.5-0.7: Teilweise lesbar, wichtige Infos fehlen
- 0.0-0.5: Unleserlich oder kein persönlicher Nachweis (z.B. nur Flyer)

WICHTIG: Bei Veranstaltungsflyern ohne persönlichen Teilnehmer -> participant=\"\" UND confidence < 0.5";

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
        
        // Übersetze häufige Fehlermeldungen
        if( strpos($err_msg, 'media_type') !== false ){
            return array('ok'=>false, 'error'=>'Ungültiges Dateiformat. Bitte nur PDF, JPEG, PNG, GIF oder WebP verwenden.');
        }
        if( strpos($err_msg, 'api_key') !== false || strpos($err_msg, 'authentication') !== false ){
            return array('ok'=>false, 'error'=>'Ungültiger API-Key. Bitte in den Einstellungen prüfen.');
        }
        if( strpos($err_msg, 'rate_limit') !== false || strpos($err_msg, 'quota') !== false ){
            return array('ok'=>false, 'error'=>'API-Limit erreicht. Bitte später erneut versuchen.');
        }
        if( strpos($err_msg, 'overloaded') !== false ){
            return array('ok'=>false, 'error'=>'Claude-Server überlastet. Bitte in wenigen Sekunden erneut versuchen.');
        }
        
        return array('ok'=>false, 'error'=>'Claude API-Fehler: '.$err_msg);
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
    
    $parsed = array(
        'participant' => trim($data['participant'] ?? ''),
        'title' => trim($data['title'] ?? ''),
        'location' => trim($data['location'] ?? ''),
        'start_date' => trim($data['start_date'] ?? ''),
        'category' => trim($data['category'] ?? ''),
        'subtype' => trim($data['subtype'] ?? ''),
        'active_role' => strtolower(trim($data['active_role'] ?? 'no')),
        'ects' => intval($data['ects'] ?? 0)
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
        'participant' => trim($data['participant'] ?? ''),
        'title' => trim($data['title'] ?? ''),
        'location' => trim($data['location'] ?? ''),
        'start_date' => trim($data['start_date'] ?? ''),
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
    return "PASSIVE TEILNAHME:
- passive_workshop_inhouse (1 Punkt) - In-house Workshop
- passive_workshop_national (1 Punkt) - Nationaler Workshop  
- passive_workshop_international (1 Punkt) - Internationaler Workshop
- passive_webinar (1 Punkt) - Webinar als Teilnehmer
- passive_seminar_national (4 Punkte) - Nationales Seminar
- passive_kongress_national (4 Punkte) - Nationaler Kongress
- passive_kongress_international (6 Punkte) - Internationaler Kongress
- passive_dgptm_jahrestagung (6 Punkte) - DGPTM Jahrestagung / Fokustagung Herz

AKTIVE TEILNAHME:
- active_inhouse (2), active_webinar (2), active_national_talk (3), active_national_mod (3), active_intl_talk (5), active_intl_mod (5)

WICHTIG - Unterscheidung Workshop vs. Kongress:
- Workshop = kleine, interaktive Veranstaltung (oft 1 Tag) -> IMMER 1 Punkt
- Kongress = große Fachtagung mit vielen Teilnehmern -> 4 Punkte (national) oder 6 Punkte (international)
- DGPTM Jahrestagung oder 'Fokustagung Herz' im Titel -> IMMER passive_dgptm_jahrestagung (6 Punkte)";
}

function fobi_ebcp_get_international_list($s){
    $intl = json_decode($s['ebcp_international_list'], true);
    return is_array($intl) ? implode(', ', $intl) : '';
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
    $valid_keys = array(
        'passive_workshop_inhouse', 'passive_workshop_national', 'passive_workshop_international',
        'passive_webinar', 'passive_seminar_national', 'passive_kongress_national', 
        'passive_kongress_international', 'passive_dgptm_jahrestagung',
        'active_inhouse', 'active_webinar', 'active_national_talk', 'active_national_mod',
        'active_intl_talk', 'active_intl_mod',
        'pub_abstract', 'pub_no_editorial', 'pub_with_editorial',
        'ects_per_credit',
        // Legacy-Keys für Kompatibilität
        'passive_inhouse', 'passive_national', 'passive_international'
    );
    
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
        $bykey[$r['key']] = floatval($r['points']);
    }
    
    $key = fobi_ebcp_get_category_key($parsed);
    $ects = intval($parsed['ects'] ?? 0);
    
    // Spezialfall ECTS: Punkte pro Credit multiplizieren
    if( $key === 'ects_per_credit' ){
        return max(0, $ects) * ($bykey['ects_per_credit'] ?? 1.0);
    }
    
    return $bykey[$key] ?? 0.0;
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


/**
 * ============================================================
 * KORRIGIERTE VERSION v2.1: Shortcode [fobi_nachweis_pruefliste]
 * ============================================================
 * 
 * WICHTIG: Diese Datei enthält NUR die zu ersetzenden/hinzuzufügenden Funktionen!
 * 
 * INSTALLATION:
 * 1. Backup der Datei ebcp-nachweis-upload-v3_9-komplett.php erstellen
 * 2. Funktion fobi_ebcp_pruefliste_shortcode() ERSETZEN (siehe unten)
 * 3. Funktion fobi_ebcp_ajax_load_pruefliste() HINZUFÜGEN (siehe unten)
 * 4. Funktion fobi_ebcp_ajax_load_details() ERSETZEN (siehe unten)
 * 
 * ÄNDERUNGEN V2.1:
 * ✅ FIX: Kritische Fehler behoben
 * ✅ FIX: Benutzer-Feld korrekt laden (ACF return_format = array)
 * ✅ FIX: Attachment-Feld korrekt laden (ACF return_format = url)
 * ✅ NEU: AJAX-Button zum on-demand Laden der Liste
 * ✅ OPTIMIERUNG: Keine doppelten Funktionsdefinitionen
 * ============================================================
 */

/* ============================================================
 * SCHRITT 1: DIESE FUNKTION ERSETZEN
 * ============================================================
 * Suche in ebcp-nachweis-upload-v3_9-komplett.php nach:
 * - Zeile 1612: function fobi_ebcp_pruefliste_shortcode($atts){
 * 
 * Ersetze die KOMPLETTE Funktion (bis zur schließenden } vor dem nächsten Kommentar)
 * mit dem folgenden Code:
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
    </style>
    <?php
    return ob_get_clean();
}


/* ============================================================
 * SCHRITT 2: DIESE FUNKTION HINZUFÜGEN
 * ============================================================
 * Füge diese Funktion NACH der obigen Funktion ein, aber VOR
 * der nächsten AJAX-Handler-Funktion (fobi_ebcp_ajax_load_details)
 * 
 * WICHTIG: Prüfe ob diese Zeile bereits existiert:
 * add_action('wp_ajax_fobi_load_pruefliste', 'fobi_ebcp_ajax_load_pruefliste');
 * 
 * Falls JA: Ersetze nur die Funktion, nicht die add_action Zeile
 * Falls NEIN: Füge beides hinzu
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
                    <th>Punkte</th>
                    <th>Hochgeladen</th>
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
                    <tr data-post-id="<?php echo $post_id; ?>">
                        <td><?php echo esc_html($date ? $date : '-'); ?></td>
                        <td><?php echo $user ? esc_html($user->display_name) : '<em>Unbekannt</em>'; ?></td>
                        <td><strong><?php echo esc_html($post->post_title); ?></strong></td>
                        <td><?php echo esc_html($type ? $type : '-'); ?></td>
                        <td><?php echo esc_html($points ? number_format($points, 1) : '-'); ?></td>
                        <td><?php echo get_the_date('d.m.Y H:i', $post_id); ?></td>
                        <td><?php echo $status_label; ?></td>
                        <td>
                            <button class="fobi-btn fobi-btn-view" data-post-id="<?php echo $post_id; ?>">
                                👁️ Ansehen
                            </button>
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
 * SCHRITT 3: DIESE FUNKTION ERSETZEN
 * ============================================================
 * Suche in ebcp-nachweis-upload-v3_9-komplett.php nach:
 * - function fobi_ebcp_ajax_load_details()
 * 
 * Ersetze die KOMPLETTE Funktion mit dem folgenden Code:
 * 
 * WICHTIG: Behalte diese Zeile ÜBER der Funktion:
 * add_action('wp_ajax_fobi_load_nachweis_details', 'fobi_ebcp_ajax_load_details');
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
    
    <div class="fobi-actions">
        <button class="fobi-btn fobi-btn-approve">✅ Genehmigen & E-Mail senden</button>
        <button class="fobi-btn fobi-btn-reject-show">❌ Ablehnen</button>
    </div>
    
    <div class="fobi-reject-form">
        <h4>⚠️ Ablehnungsgrund angeben</h4>
        <textarea class="fobi-reject-comment" placeholder="Bitte geben Sie einen Grund für die Ablehnung an, der dem Benutzer per E-Mail zugestellt wird..."></textarea>
        <button class="fobi-btn fobi-btn-reject-submit">❌ Ablehnen & E-Mail senden</button>
    </div>
    <?php
    
    wp_send_json_success(array('html' => ob_get_clean()));
}