<?php
/**
 * Automatische Generierung aller Modul-Anleitungen
 * Basierend auf der Modulanalyse
 */

// WordPress laden
$wp_load_path = dirname(__FILE__) . '/../../../wp-load.php';
if (file_exists($wp_load_path)) {
    require_once $wp_load_path;
} else {
    die("WordPress konnte nicht geladen werden.\n");
}

if (!defined('ABSPATH')) {
    die('WordPress nicht geladen');
}

echo "DGPTM Guide Generator\n";
echo "====================\n\n";

// Guides-Verzeichnis erstellen
$guides_dir = DGPTM_SUITE_PATH . 'guides/';
if (!file_exists($guides_dir)) {
    wp_mkdir_p($guides_dir);
    echo "✓ Guides-Verzeichnis erstellt\n";
}

// Modul-Daten aus der vollständigen Analyse
$modules_data = json_decode('{
  "crm-abruf": {
    "name": "DGPTM - Zoho CRM & API Endpoints",
    "description": "Integriert Zoho CRM mit OAuth2-Authentifizierung und stellt Custom REST API Endpunkte bereit. Bietet Shortcodes für CRM-Datenabfragen, Webhook-Trigger und Barcode-Generierung. Enthält SSRF-Härtung, HMAC-Sicherheit für interne Aufrufe und umfassendes Debug-Logging.",
    "category": "core-infrastructure",
    "main_features": [
      "Zoho CRM OAuth2 Integration",
      "Custom REST API Endpunkte mit HMAC-Sicherheit",
      "Shortcodes: [zoho_api_data], [api-abfrage], [ifcrmfield], [efn_barcode]",
      "Webhook-Support mit SSRF-Protection",
      "Debug-Logging mit Credential-Redaction"
    ]
  },
  "rest-api-extension": {
    "name": "REST API Extensions",
    "description": "Erweitert die WordPress REST API um benutzerdefinierte Endpunkte für Multisite-Verwaltung. Ermöglicht Benutzerrollenverwaltung über alle Sites, Abruf von Site-Listen und Benutzerlöschung mit optionaler Beitragsumverteilung.",
    "category": "core-infrastructure",
    "main_features": [
      "Multisite Benutzerrollenverwaltung (dgptm/v1/user-sites)",
      "Sites-Liste abrufen (dgptm/v1/sites)",
      "Benutzer löschen mit Beitragsumverteilung (dgptm/v1/delete-user)",
      "Nur für Benutzer mit manage_sites Capability"
    ]
  },
  "webhook-trigger": {
    "name": "Webhook Trigger",
    "description": "Sendet Webhooks per AJAX und bietet ein Studienbescheinigungs-Upload-Formular. Webhooks übertragen Benutzer-Metadaten (zoho_id, user_id). Upload-Dateien werden automatisch nach 7 Tagen gelöscht und können per Token gelöscht werden.",
    "category": "core-infrastructure",
    "main_features": [
      "AJAX Webhook Trigger Shortcode [webhook_ajax_trigger]",
      "Studienbescheinigungs-Upload Shortcode [studierendenstatue_beantragen]",
      "Automatische Bereinigung alter Uploads (7 Tage)",
      "Token-basiertes Löschen ohne Login"
    ]
  },
  "menu-control": {
    "name": "Menu Control",
    "description": "Ermöglicht das Ein- und Ausblenden von WordPress-Menüpunkten basierend auf Benutzerrollen und Login-Status. Bietet fein granulare Steuerung über Menüsichtbarkeit mit benutzerdefinierten Feldern im Menü-Editor.",
    "category": "core-infrastructure",
    "main_features": [
      "Rollenbasierte Menüsichtbarkeit",
      "Separate Einstellungen für eingeloggte/nicht-eingeloggte Benutzer",
      "Multi-Select Rollenauswahl im Menü-Editor",
      "Admin CSS für bessere UI"
    ]
  },
  "side-restrict": {
    "name": "Side Restrict",
    "description": "Erweiterte Zugriffssteuerung für WordPress-Seiten basierend auf Benutzerrollen, ACF-Feldern und spezifischen Benutzern. Toggle-basierte Aktivierung mit ODER-verknüpften Kriterien. Seiten sind standardmäßig öffentlich.",
    "category": "core-infrastructure",
    "main_features": [
      "Toggle für Seitenzugriff (öffentlich/nur angemeldet)",
      "Rollenbasierte Zugriffskontrolle",
      "ACF-Berechtigungsfelder (news_schreiben, timeline, etc.)",
      "Spezifische Benutzerauswahl",
      "Umleitungsseite konfigurierbar"
    ]
  }
}', true);

// Funktion zum Generieren einer Guide-Datei
function generate_guide($module_id, $data, $guides_dir) {
    $guide = [
        'title' => $data['name'],
        'description' => $data['description'],
        'content' => generate_guide_content($data),
        'features' => $data['main_features'],
        'keywords' => generate_keywords($module_id, $data),
        'category' => $data['category'],
        'module_id' => $module_id,
        'generated_at' => current_time('mysql'),
        'last_updated' => current_time('mysql'),
        'version' => '1.0'
    ];

    $guide_file = $guides_dir . $module_id . '.json';
    $result = file_put_contents($guide_file, json_encode($guide, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    return $result !== false;
}

// Markdown-Content generieren
function generate_guide_content($data) {
    $content = "# {$data['name']}\n\n";
    $content .= "## Übersicht\n\n";
    $content .= "{$data['description']}\n\n";
    $content .= "## Hauptfunktionen\n\n";

    foreach ($data['main_features'] as $feature) {
        $content .= "- $feature\n";
    }

    $content .= "\n## Kategorie\n\n";
    $content .= "Dieses Modul gehört zur Kategorie: **{$data['category']}**\n\n";

    $content .= "## Verwendung\n\n";
    $content .= "1. Aktivieren Sie das Modul im DGPTM Suite Dashboard\n";
    $content .= "2. Konfigurieren Sie die Einstellungen nach Bedarf\n";
    $content .= "3. Verwenden Sie die bereitgestellten Shortcodes oder Features\n\n";

    $content .= "## Support\n\n";
    $content .= "Bei Fragen oder Problemen wenden Sie sich bitte an den DGPTM Support.\n";

    return $content;
}

// Keywords generieren
function generate_keywords($module_id, $data) {
    $keywords = [$module_id, $data['name'], $data['category']];

    // Zusätzliche Keywords aus Features extrahieren
    foreach ($data['main_features'] as $feature) {
        $words = explode(' ', strtolower($feature));
        foreach ($words as $word) {
            if (strlen($word) > 4) {
                $keywords[] = trim($word, '[]():,.');
            }
        }
    }

    return array_unique($keywords);
}

// Guides generieren
$generated = 0;
$failed = 0;

foreach ($modules_data as $module_id => $data) {
    if (generate_guide($module_id, $data, $guides_dir)) {
        echo "✓ Guide generiert: $module_id ({$data['name']})\n";
        $generated++;
    } else {
        echo "✗ Fehler bei: $module_id\n";
        $failed++;
    }
}

echo "\n====================\n";
echo "Zusammenfassung:\n";
echo "- Generiert: $generated\n";
echo "- Fehler: $failed\n";
echo "- Gesamt: " . ($generated + $failed) . "\n\n";

echo "Hinweis: Weitere 28 Module müssen noch hinzugefügt werden.\n";
echo "Verwenden Sie das Admin-Interface unter DGPTM Suite > Module Guides,\n";
echo "um Anleitungen zu bearbeiten oder neue hinzuzufügen.\n";
