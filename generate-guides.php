<?php
/**
 * Skript zum Generieren aller Modul-Anleitungen
 * Dieses Skript wird einmalig ausgeführt um alle Guide-Dateien zu erstellen
 */

// WordPress laden
require_once dirname(__FILE__) . '/../../../wp-load.php';

if (!defined('ABSPATH')) {
    die('WordPress not loaded');
}

// Guide-Daten für alle Module
$guides_data = [
    'crm-abruf' => [
        'title' => 'DGPTM - Zoho CRM & API Endpoints',
        'description' => 'Integriert Zoho CRM mit OAuth2-Authentifizierung und stellt Custom REST API Endpunkte bereit.',
        'content' => '# DGPTM - Zoho CRM & API Endpoints

## Übersicht

Dieses Modul ist das Herzstück der DGPTM-Suite und integriert Zoho CRM mit WordPress. Es bietet OAuth2-Authentifizierung, Custom REST API Endpunkte, Shortcodes für CRM-Datenabfragen und umfassende Sicherheitsfunktionen.

## Hauptfunktionen

- **Zoho CRM OAuth2 Integration**: Sichere Verbindung zu Zoho CRM
- **Custom REST API Endpunkte**: Mit HMAC-Sicherheit für interne Aufrufe
- **Shortcodes**: [zoho_api_data], [api-abfrage], [ifcrmfield], [efn_barcode]
- **Webhook-Support**: Mit SSRF-Protection
- **Debug-Logging**: Mit automatischer Credential-Redaction

## Shortcodes

### [zoho_api_data]
Ruft Daten aus Zoho CRM ab und zeigt sie an.

```
[zoho_api_data field="Email"]
```

### [api-abfrage]
Generische API-Abfrage für verschiedene Endpunkte.

```
[api-abfrage endpoint="contacts" field="Full_Name"]
```

### [ifcrmfield]
Bedingte Anzeige basierend auf CRM-Feldern.

```
[ifcrmfield field="Membership_Status" value="Active"]
  Nur für aktive Mitglieder sichtbar
[/ifcrmfield]
```

### [efn_barcode]
Generiert Barcodes für Teilnehmer-Ausweise.

```
[efn_barcode]
```

## Einrichtung

1. Modul im Dashboard aktivieren
2. Zoho CRM OAuth2 Credentials in den Einstellungen hinterlegen
3. Authentifizierung durchführen
4. Shortcodes in Seiten einbinden

## Sicherheit

- SSRF-Härtung für Webhook-URLs
- HMAC-Signierung für interne API-Aufrufe
- Automatische Credential-Redaction im Logging
- Rate-Limiting für API-Anfragen

## Abhängigkeiten

Keine - Dies ist ein Core-Modul, von dem andere Module abhängen können.',
        'features' => [
            'Zoho CRM OAuth2 Integration',
            'Custom REST API Endpunkte mit HMAC-Sicherheit',
            'Shortcodes: [zoho_api_data], [api-abfrage], [ifcrmfield], [efn_barcode]',
            'Webhook-Support mit SSRF-Protection',
            'Debug-Logging mit Credential-Redaction'
        ],
        'keywords' => ['zoho', 'crm', 'oauth2', 'api', 'rest', 'webhook', 'barcode', 'security']
    ],

    'rest-api-extension' => [
        'title' => 'REST API Extensions',
        'description' => 'Erweitert die WordPress REST API um benutzerdefinierte Endpunkte für Multisite-Verwaltung.',
        'content' => '# REST API Extensions

## Übersicht

Dieses Modul erweitert die WordPress REST API um leistungsstarke Endpunkte für Multisite-Verwaltung, Benutzerrollenverwaltung und Site-Management.

## API-Endpunkte

### dgptm/v1/user-sites
Verwaltet Benutzerrollen über alle Sites im Netzwerk.

**GET** - Listet alle Sites eines Benutzers
```
GET /wp-json/dgptm/v1/user-sites?user_id=123
```

**POST** - Fügt Benutzer zu Site hinzu
```json
{
  "user_id": 123,
  "site_id": 5,
  "role": "editor"
}
```

### dgptm/v1/sites
Ruft Liste aller Sites im Netzwerk ab.

```
GET /wp-json/dgptm/v1/sites
```

### dgptm/v1/delete-user
Löscht Benutzer mit optionaler Beitragsumverteilung.

```json
{
  "user_id": 123,
  "reassign_to": 456
}
```

## Berechtigungen

Alle Endpunkte erfordern die `manage_sites` Capability. Diese ist standardmäßig nur für Super-Admins verfügbar.

## Verwendung

1. Modul aktivieren
2. REST API Endpunkte über HTTP-Client aufrufen
3. Bearer-Token oder Cookie-Authentifizierung verwenden

## Sicherheit

- Capability-Checks für alle Endpunkte
- Nonce-Validierung bei Cookie-Auth
- Sanitierung aller Eingabedaten',
        'features' => [
            'Multisite Benutzerrollenverwaltung (dgptm/v1/user-sites)',
            'Sites-Liste abrufen (dgptm/v1/sites)',
            'Benutzer löschen mit Beitragsumverteilung (dgptm/v1/delete-user)',
            'Nur für Benutzer mit manage_sites Capability'
        ],
        'keywords' => ['rest', 'api', 'multisite', 'users', 'roles', 'network']
    ],

    'webhook-trigger' => [
        'title' => 'Webhook Trigger',
        'description' => 'Sendet Webhooks per AJAX und bietet ein Studienbescheinigungs-Upload-Formular.',
        'content' => '# Webhook Trigger

## Übersicht

Dieses Modul ermöglicht das Auslösen von Webhooks per AJAX und bietet ein spezialisiertes Upload-Formular für Studienbescheinigungen mit automatischer Bereinigung.

## Shortcodes

### [webhook_ajax_trigger]
Löst einen Webhook per AJAX aus.

```
[webhook_ajax_trigger url="https://api.example.com/webhook" button_text="Senden"]
```

**Parameter:**
- `url`: Webhook-URL (erforderlich)
- `button_text`: Button-Beschriftung (optional)
- `method`: HTTP-Methode (GET/POST, Standard: POST)

### [studierendenstatue_beantragen]
Upload-Formular für Studienbescheinigungen.

```
[studierendenstatue_beantragen]
```

## Features

### Automatische Bereinigung
- Uploads werden nach 7 Tagen automatisch gelöscht
- Täglicher Cron-Job für Cleanup
- Speicherplatzsparend

### Token-basiertes Löschen
Benutzer erhalten nach Upload einen Token zum manuellen Löschen ohne Login.

```
https://example.com/delete-upload/?token=abc123
```

### Webhook-Datenübertragung
Bei Webhook-Trigger werden folgende Benutzer-Metadaten übertragen:
- zoho_id
- user_id
- E-Mail
- Name

## Sicherheit

- Nonce-Validierung für alle AJAX-Requests
- File-Type-Validierung für Uploads
- Token-Verifizierung beim Löschen
- Automatische Bereinigung sensibler Daten',
        'features' => [
            'AJAX Webhook Trigger Shortcode [webhook_ajax_trigger]',
            'Studienbescheinigungs-Upload Shortcode [studierendenstatue_beantragen]',
            'Automatische Bereinigung alter Uploads (7 Tage)',
            'Token-basiertes Löschen ohne Login'
        ],
        'keywords' => ['webhook', 'ajax', 'upload', 'students', 'forms', 'automation']
    ],

    // ... (weitere Module folgen im gleichen Format)
];

// Guides-Verzeichnis erstellen
$guides_dir = DGPTM_SUITE_PATH . 'guides/';
if (!file_exists($guides_dir)) {
    wp_mkdir_p($guides_dir);
}

// Guides generieren
$generated = 0;
foreach ($guides_data as $module_id => $guide_data) {
    $guide_file = $guides_dir . $module_id . '.json';

    $guide_data['module_id'] = $module_id;
    $guide_data['generated_at'] = current_time('mysql');
    $guide_data['generated_by'] = 'system';

    $result = file_put_contents($guide_file, json_encode($guide_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    if ($result !== false) {
        echo "✓ Guide generiert: $module_id\n";
        $generated++;
    } else {
        echo "✗ Fehler bei: $module_id\n";
    }
}

echo "\n$generated von " . count($guides_data) . " Guides erfolgreich generiert.\n";
echo "Bitte weitere Module manuell hinzufügen über das Admin-Interface.\n";
