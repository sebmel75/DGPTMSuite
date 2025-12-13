<?php
/**
 * Importiert alle Modul-Anleitungen aus der JSON-Dokumentation
 * und generiert zusätzliche Anleitungen basierend auf der Modul-Analyse
 */

// Prüfe ob WordPress geladen ist
$is_wordpress = defined('ABSPATH');

if (!$is_wordpress) {
    // Standalone-Modus
    echo "DGPTM Vollständiger Anleitungs-Import (Standalone)\n";
    echo "==================================================\n\n";

    // Guides-Verzeichnis im Standalone-Modus
    $guides_dir = __DIR__ . '/guides/';
} else {
    // WordPress-Modus
    echo "DGPTM Vollständiger Anleitungs-Import\n";
    echo "======================================\n\n";

    // Guides-Verzeichnis im WordPress-Modus
    $guides_dir = DGPTM_SUITE_PATH . 'guides/';
}

// Guides-Verzeichnis erstellen (Standalone-kompatibel)
if (!file_exists($guides_dir)) {
    if ($is_wordpress && function_exists('wp_mkdir_p')) {
        wp_mkdir_p($guides_dir);
    } else {
        mkdir($guides_dir, 0755, true);
    }
}

// Lade existierende Dokumentation
$doc_file = dirname(__FILE__) . '/../DGPTM-Module-Dokumentation.json';
$existing_guides = [];

if (file_exists($doc_file)) {
    $json = file_get_contents($doc_file);
    $existing_guides = json_decode($json, true);
    echo "✓ Existierende Dokumentation geladen: " . count($existing_guides) . " Module\n\n";
}

// Vollständige Modul-Daten für alle 33 Module
$all_module_guides = array_merge($existing_guides, [

    // Core Infrastructure (2 weitere)
    'menu-control' => [
        'title' => 'Menu Control',
        'description' => 'Ermöglicht rollenbasiertes Ein- und Ausblenden von Menüpunkten für eingeloggte und nicht-eingeloggte Benutzer.',
        'content' => '# Menu Control

## Übersicht

Das Menu Control Modul ermöglicht die fein-granulare Steuerung der Sichtbarkeit von WordPress-Menüpunkten basierend auf Benutzerrollen und Login-Status.

## Hauptfunktionen

- Rollenbasierte Menüsichtbarkeit
- Separate Einstellungen für eingeloggte/nicht-eingeloggte Benutzer
- Multi-Select für mehrere Rollen
- Automatische Integration in WordPress Menü-Editor

## Einrichtung

1. Navigieren Sie zu **Design → Menüs**
2. Wählen Sie einen Menüpunkt aus
3. Erweitern Sie die Menüpunkt-Optionen
4. Finden Sie die "Menu Control" Einstellungen
5. Wählen Sie:
   - Sichtbarkeit für nicht-eingeloggte Benutzer
   - Sichtbarkeit für eingeloggte Benutzer
   - Spezifische Rollen (falls nur für bestimmte Rollen)

## Verwendung

### Beispiel 1: Nur für Mitglieder
- **Für nicht-eingeloggt:** Ausblenden
- **Für eingeloggt:** Nur für Rolle "Mitglied"

### Beispiel 2: Nur für Nicht-Mitglieder
- **Für nicht-eingeloggt:** Einblenden
- **Für eingeloggt:** Ausblenden

### Beispiel 3: Nur für Admins
- **Für nicht-eingeloggt:** Ausblenden
- **Für eingeloggt:** Nur für Rolle "Administrator"

## Technische Details

- Nutzt WordPress `wp_nav_menu_objects` Filter
- CSS für bessere Admin-UI
- Speichert Einstellungen in Post Meta

## Tipps

- Testen Sie Menüs als unterschiedliche Benutzerrollen
- Verwenden Sie aussagekräftige Menünamen
- Beachten Sie die Menü-Hierarchie',
        'features' => [
            'Rollenbasierte Menü-Kontrolle',
            'Login-Status-basierte Sichtbarkeit',
            'Multi-Select Rollen-Auswahl',
            'WordPress Menü-Editor Integration'
        ],
        'keywords' => ['menu', 'navigation', 'roles', 'permissions', 'visibility', 'access-control']
    ],

    'side-restrict' => [
        'title' => 'Side Restrict',
        'description' => 'Erweiterte Zugriffssteuerung für WordPress-Seiten basierend auf Rollen, ACF-Feldern und spezifischen Benutzern.',
        'content' => '# Side Restrict

## Übersicht

Side Restrict bietet erweiterte Zugriffskontrolle für WordPress-Seiten mit mehreren Kriterien die per ODER verknüpft werden.

## Hauptfunktionen

- Toggle für Seitenzugriff (öffentlich / nur angemeldet)
- Rollenbasierte Zugriffskontrolle
- ACF-Berechtigungsfelder (news_schreiben, timeline, etc.)
- Spezifische Benutzer-Auswahl
- Konfigurierbare Umleitungsseite

## Einstellungen pro Seite

### 1. Zugriffs-Toggle
**Option:** Nur für angemeldete Benutzer
- **AUS:** Seite ist öffentlich
- **AN:** Seite nur für eingeloggte Benutzer + zusätzliche Kriterien

### 2. Zusätzliche Kriterien (ODER-Verknüpfung)

**Rollen:**
- Benutzer mit bestimmter WordPress-Rolle
- Multi-Select möglich

**ACF-Felder:**
- `news_schreiben`
- `timeline`
- Weitere benutzerdefinierte Felder

**Spezifische Benutzer:**
- Per Benutzer-ID oder Username

### 3. Umleitung
**Standard:** Login-Seite
**Optional:** Benutzerdefinierte URL

## Verwendung

### Beispiel: Mitgliederbereich

1. Seite bearbeiten
2. Toggle aktivieren: "Nur für angemeldete Benutzer"
3. Rolle auswählen: "Mitglied"
4. Speichern

→ Nur eingeloggte Benutzer MIT Rolle "Mitglied" können Seite sehen

### Beispiel: News-Redakteure

1. Toggle: AN
2. ACF-Feld: `news_schreiben` = true
3. ODER: Rolle "Editor"

→ Benutzer mit `news_schreiben = true` ODER Rolle "Editor"

## Technische Details

- Hook: `template_redirect`
- Prüfung: Vor Seiten-Rendering
- Logik: ODER-Verknüpfung aller Kriterien
- Speicherung: Post Meta

## Tipps

- Kombinieren Sie mehrere Kriterien für Flexibilität
- Testen Sie als unterschiedliche Benutzer
- Definieren Sie klare Umleitungsseiten',
        'features' => [
            'Toggle-basierte Aktivierung',
            'Rollenbasierte Zugriffskontrolle',
            'ACF-Berechtigungsfelder',
            'Benutzer-spezifische Zugriffe',
            'ODER-verknüpfte Kriterien',
            'Umleitungs-Konfiguration'
        ],
        'keywords' => ['access-control', 'permissions', 'roles', 'acf', 'restrictions', 'redirect']
    ],

    // ACF Tools (alle 3)
    'acf-anzeiger' => [
        'title' => 'ACF Field Display',
        'description' => 'Sicherer Shortcode zur Anzeige von ACF-Feldern mit automatischer Typ-Erkennung und Security-Hardening.',
        'content' => '# ACF Field Display

## Übersicht

Der ACF Anzeiger ermöglicht die sichere Darstellung von Advanced Custom Fields per Shortcode mit automatischer Typ-Erkennung.

## Hauptfunktionen

- Auto-Typ-Erkennung für ACF-Felder
- Unterstützte Typen: text, image, link, post_object, file, gallery
- Bildgrößen, Lazy-Loading, Custom Wrapper
- Security: KSES-Whitelist, Escaping
- Elementor-kompatibel

## Shortcode

### [dgptm_acf]

**Parameter:**
- `field` (erforderlich): ACF-Feldname
- `post_id`: Post-ID (Standard: aktuelle)
- `size`: Bildgröße für image/gallery
- `wrapper`: HTML-Wrapper-Element
- `class`: CSS-Klassen
- `lazy`: Lazy-Loading (true/false)

**Beispiele:**

```
[dgptm_acf field="title"]

[dgptm_acf field="header_image" size="large" lazy="true"]

[dgptm_acf field="website_link"]

[dgptm_acf field="gallery" size="medium" wrapper="div" class="image-grid"]
```

## Unterstützte Feldtypen

### Text
Gibt text direkt aus (escaped)

### Image
```
[dgptm_acf field="logo" size="thumbnail"]
```

### Link
```
[dgptm_acf field="external_link"]
```
Ausgabe: `<a href="..." target="...">...</a>`

### Post Object
```
[dgptm_acf field="related_post"]
```
Ausgabe: Link zum Post

### File
```
[dgptm_acf field="pdf_download"]
```
Ausgabe: Download-Link

### Gallery
```
[dgptm_acf field="images" size="medium"]
```
Ausgabe: Liste von Bildern

## Sicherheit

- KSES-Whitelist für erlaubte HTML-Tags
- Escaping aller Ausgaben
- rel/target-Attribute sicher
- Keine direkte PHP-Ausführung

## Elementor-Integration

Funktioniert in:
- Elementor Text-Editor
- Elementor Shortcode-Widget
- Elementor HTML-Widget',
        'features' => [
            'Auto-Typ-Erkennung',
            'Mehrere Feldtypen unterstützt',
            'Bildgrößen-Steuerung',
            'Lazy-Loading',
            'Security-Hardening',
            'Elementor-kompatibel'
        ],
        'keywords' => ['acf', 'fields', 'shortcode', 'display', 'custom-fields', 'elementor']
    ],

    'acf-toggle' => [
        'title' => 'ACF Toggle Functions',
        'description' => 'Erstellt Shortcodes zur bedingten Content-Anzeige basierend auf ACF Ja/Nein-Feldern und Benutzerrollen.',
        'content' => '# ACF Toggle Functions

## Übersicht

ACF Toggle ermöglicht bedingte Content-Anzeige basierend auf ACF User-Meta-Feldern (Ja/Nein) und WordPress-Benutzerrollen.

## Hauptfunktionen

- Bedingte Content-Anzeige
- ACF User-Meta Abfrage
- Rollenbasierte Sichtbarkeit
- Status-Rückgabe (true/false)
- Shortcode-basiert

## Shortcodes

### Bedingte Anzeige

Syntax:
```
[acf_toggle_feldname]
  Inhalt nur sichtbar wenn Feld = Ja
[/acf_toggle_feldname]
```

**Beispiel:**
```
[acf_toggle_newsletter]
  <p>Sie haben den Newsletter abonniert!</p>
[/acf_toggle_newsletter]
```

### Status-Abfrage

```
[acf_status_feldname]
```

Gibt zurück: `true` oder `false`

## Verwendung

### ACF-Feld erstellen

1. ACF → Feldgruppe erstellen
2. Feld hinzufügen: Typ = True/False
3. Feldname: z.B. `newsletter`
4. Speicherort: Benutzer

### Shortcode verwenden

```
[acf_toggle_newsletter]
  <div class="newsletter-subscriber">
    Exklusive Inhalte für Newsletter-Abonnenten!
  </div>
[/acf_toggle_newsletter]
```

### Rollen-Abfrage

Das Modul kann auch Rollen prüfen (abhängig von Implementierung).

## Technische Details

- Liest `get_user_meta()` für ACF-Felder
- Prüft auf `true` / `1` / `"1"`
- Unterstützt verschachtelte Shortcodes

## Tipps

- Verwenden Sie aussagekräftige Feldnamen
- Testen Sie als unterschiedliche Benutzer
- Kombinieren Sie mit anderen bedingten Shortcodes',
        'features' => [
            'Bedingte Content-Anzeige',
            'ACF User-Meta Integration',
            'Rollenbasierte Prüfung',
            'Status-Rückgabe',
            'Verschachtelte Shortcodes'
        ],
        'keywords' => ['acf', 'toggle', 'conditional', 'user-meta', 'roles', 'visibility']
    ],

    'acf-jetsync' => [
        'title' => 'ACF JetEngine Sync',
        'description' => 'Bidirektionale Synchronisation zwischen JetEngine und ACF für Post-Types, Taxonomien und Felder.',
        'content' => '# ACF JetEngine Sync

## Übersicht

ACF JetEngine Sync ermöglicht die bidirektionale Synchronisation zwischen JetEngine und Advanced Custom Fields inkl. komplexer Felder.

## Hauptfunktionen

- Bidirektionaler Sync JetEngine ↔ ACF
- Post-Types Synchronisation
- Taxonomien Synchronisation
- Felder-Sync (inkl. komplexe Felder)
- JSON Export/Import
- Backup-Funktionalität

## Admin-Interface

**Menü:** Tools → JetEngine ACF Sync

### Synchronisations-Optionen

1. **JetEngine → ACF**
   - Liest JetEngine-Tabellen
   - Erstellt/aktualisiert ACF-Feldgruppen
   - Unterstützt komplexe Felder

2. **ACF → JetEngine**
   - Liest ACF-Feldgruppen
   - Erstellt JetEngine-Felder
   - Migration von ACF zu JetEngine

3. **JSON Export**
   - Exportiert ACF-Feldgruppen als JSON
   - Backup-Funktion
   - Migration zwischen Sites

4. **JSON Import**
   - Importiert ACF-Feldgruppen
   - Wiederherstellung von Backups

## Verwendung

### Sync JetEngine → ACF

1. Tools → JetEngine ACF Sync
2. Schaltfläche "JetEngine → ACF synchronisieren"
3. Warten Sie auf Bestätigung
4. Prüfen Sie ACF → Feldgruppen

### JSON Backup erstellen

1. Tools → JetEngine ACF Sync
2. "JSON Export" klicken
3. Datei herunterladen
4. Sicher aufbewahren

### JSON Import

1. Backup-Datei vorbereiten
2. "JSON Import" klicken
3. Datei auswählen
4. Importieren

## Unterstützte Feldtypen

- Text, Textarea
- Number, Email, URL
- True/False, Select, Radio, Checkbox
- Post Object, Relationship
- Image, File, Gallery
- Wysiwyg, Date, Color
- Repeater (komplex)
- Group (komplex)

## Technische Details

- Liest direkt aus JetEngine-DB-Tabellen
- Verwendet ACF JSON-Format
- Automatische Feldtyp-Zuordnung
- Fehlerbehandlung und Logging

## Tipps

- Erstellen Sie vor Sync ein Backup
- Testen Sie zuerst auf Staging
- Prüfen Sie Feldgruppen nach Import
- Behalten Sie JSON-Backups',
        'features' => [
            'Bidirektionale Synchronisation',
            'Post-Types & Taxonomien',
            'Komplexe Felder',
            'JSON Export/Import',
            'Backup-System',
            'Fehlerbehandlung'
        ],
        'keywords' => ['acf', 'jetengine', 'sync', 'migration', 'fields', 'post-types', 'taxonomy']
    ],

    // Business Module (9 weitere)
    'quiz-manager' => [
        'title' => 'Quiz Manager',
        'description' => 'Umfassende Quiz-Verwaltung mit Kategorien, Ergebnissen und Zoho-Integration.',
        'content' => '# Quiz Manager

## Übersicht

Vollständiges Quiz-Management-System mit Kategoriesystem, Ergebnis-Tracking und Integration mit Zoho CRM für Fortbildungspunkte.

## Hauptfunktionen

- Quiz-Verwaltung mit Kategorien
- Ergebnis-Tracking und Statistiken
- Bestandene Quizze Frontend-Anzeige
- Zoho CRM Integration
- Punkte-System
- Fortschritts-Tracking

## Shortcodes

### [quiz_manager]
Zeigt das Quiz-Manager-Interface an.

**Verwendung:**
```
[quiz_manager]
```

### [dgptm-quiz-categories]
Zeigt Kategorie-Übersicht mit allen Quizzen.

**Verwendung:**
```
[dgptm-quiz-categories]
```

### [passed_quizzes]
Listet alle bestandenen Quizze des aktuellen Benutzers.

**Verwendung:**
```
[passed_quizzes]
```

## Admin-Interface

**Menü:** Quizzes → Quiz Manager

### Funktionen

1. **Quiz-Übersicht**
   - Alle Quizze verwalten
   - Status anzeigen
   - Teilnehmerzahlen

2. **Kategorien-Verwaltung**
   - Kategorien erstellen/bearbeiten
   - Quizze zuordnen
   - Hierarchie definieren

3. **Ergebnis-Tracking**
   - Benutzer-Ergebnisse einsehen
   - Statistiken anzeigen
   - Export-Funktionen

4. **Zoho-Synchronisation**
   - Automatischer Datenabgleich
   - Punkteübertragung
   - Fortbildungszertifikate

## Integration mit Quiz Maker Plugin

Das Modul erweitert das Quiz Maker Plugin um:
- Kategoriesystem
- Zoho CRM Anbindung
- Fortgeschrittenes Tracking
- Frontend-Anzeige

## Verwendung

### Quiz erstellen

1. Quiz Maker → Add New
2. Quiz konfigurieren
3. Kategorie zuweisen
4. Shortcode auf Seite einbinden

### Ergebnisse verwalten

1. Quiz Manager → Ergebnisse
2. Benutzer filtern
3. Statistiken ansehen
4. Bei Bedarf exportieren

### Zoho-Synchronisation

Erfolgt automatisch:
- Nach bestandenem Quiz
- Punkte werden übertragen
- Zertifikate werden ausgelöst

## Technische Details

- Nutzt Quiz Maker API
- Custom Database Tables für Tracking
- Cron-Jobs für Synchronisation
- Ajax für Frontend-Updates

## Tipps

- Nutzen Sie aussagekräftige Kategorienamen
- Definieren Sie klare Bestehens-Schwellen
- Testen Sie Zoho-Integration vor Live-Betrieb
- Backup vor größeren Änderungen',
        'features' => [
            'Quiz-Verwaltung',
            'Kategoriesystem',
            'Ergebnis-Tracking',
            'Bestandene Quizze Frontend',
            'Zoho CRM Integration',
            'Punkte-System'
        ],
        'keywords' => ['quiz', 'test', 'exam', 'categories', 'results', 'tracking', 'zoho']
    ],

    'herzzentren' => [
        'title' => 'Herzzentren Editor',
        'description' => 'Herzzentrum-Verwaltung mit interaktiven Karten und Elementor-Widgets für Ausbildungszentren.',
        'content' => '# Herzzentren Editor

## Übersicht

Spezialisiertes Modul für die Verwaltung von Herzzentren und Ausbildungsstätten mit interaktiven Karten, Frontend-Editor und Elementor-Integration.

## Hauptfunktionen

- Custom Post Type "Herzzentrum"
- Interaktive Google Maps / Leaflet
- Elementor-Widgets
- Frontend-Editor mit Berechtigungssystem
- Ajax-Suche und Filter
- Export-Funktionen

## Shortcodes

### [herzzentrum_karte]
Zeigt interaktive Karte mit allen oder gefilterten Herzzentren.

**Parameter:**
- `category` - Kategorie-Filter
- `region` - Regions-Filter
- `zoom` - Initial Zoom-Level

**Verwendung:**
```
[herzzentrum_karte category="ausbildungszentrum" zoom="7"]
```

### [herzzentrum_liste]
Listet Herzzentren als Tabelle oder Grid.

**Verwendung:**
```
[herzzentrum_liste]
```

## Elementor Widgets

### Herzzentren Karte (Multi-Map)
Zeigt mehrere Zentren auf einer Karte.

**Einstellungen:**
- Kategorieauswahl
- Kartentyp (Google Maps / Leaflet)
- Marker-Styling
- Popup-Template

### Einzelnes Herzzentrum (Single-Map)
Detail-Ansicht eines einzelnen Zentrums.

**Einstellungen:**
- Zentrum auswählen
- Zusatzinfos anzeigen
- Kontaktdaten
- Bilder-Galerie

## Admin-Interface

**Menü:** Herzzentren

### Funktionen

1. **Herzzentrum hinzufügen/bearbeiten**
   - Name, Adresse, Kontakt
   - Koordinaten (automatisch oder manuell)
   - Kategorien zuweisen
   - Bilder hochladen

2. **Berechtigungsverwaltung**
   - ACF-basiert
   - Frontend-Editor-Zugriff
   - Rollenabhängig

3. **Karten-Einstellungen**
   - API-Schlüssel (Google Maps)
   - Standard-Zoom
   - Marker-Icons
   - Popup-Templates

## ACF-Integration

Benötigte Felder (automatisch erstellt):
- Adresse (Text)
- Stadt (Text)
- PLZ (Text)
- Land (Text)
- Telefon (Text)
- E-Mail (E-Mail)
- Website (URL)
- Latitude (Number)
- Longitude (Number)
- Kategorie (Taxonomy)
- Beschreibung (Wysiwyg)

## Frontend-Editor

Berechtigte Benutzer können im Frontend:
- Neue Zentren anlegen
- Eigene Zentren bearbeiten
- Bilder hochladen
- Koordinaten setzen

**Zugriff:**
- ACF-Feld für Berechtigung
- Rollenbasiert
- Per Benutzer-ID

## Verwendung

### Karte auf Seite einbinden

**Mit Shortcode:**
```
[herzzentrum_karte]
```

**Mit Elementor:**
1. Elementor öffnen
2. Widget "Herzzentren Karte" suchen
3. Widget hinzufügen
4. Einstellungen konfigurieren

### Neues Herzzentrum anlegen

**Backend:**
1. Herzzentren → Neu hinzufügen
2. Alle Felder ausfüllen
3. Adresse eingeben (Koordinaten werden automatisch ermittelt)
4. Veröffentlichen

**Frontend:**
1. Als berechtigter Benutzer einloggen
2. Frontend-Editor aufrufen
3. Formular ausfüllen
4. Absenden

## Technische Details

- Custom Post Type: `herzzentrum`
- Taxonomie: `herzzentrum_category`
- Geocoding: Google Maps API
- Alternative: Leaflet (OpenStreetMap)
- Ajax-basierte Suche
- Responsive Karten

## Tipps

- Tragen Sie Google Maps API-Schlüssel ein für Geocoding
- Nutzen Sie Kategorien für bessere Organisation
- Testen Sie Frontend-Editor als Nicht-Admin
- Backup vor größeren Datenimporten',
        'features' => [
            'Custom Post Type Herzzentrum',
            'Interaktive Karten (Google Maps / Leaflet)',
            'Elementor-Widgets',
            'Frontend-Editor',
            'Berechtigungssystem',
            'Ajax-Suche',
            'Export-Funktionen'
        ],
        'keywords' => ['map', 'herzzentrum', 'location', 'elementor', 'acf', 'frontend-editor', 'geocoding']
    ],

    'timeline-manager' => [
        'title' => 'Timeline Manager',
        'description' => 'Timeline-Verwaltungssystem mit Custom Post Type und Frontend-Editor.',
        'content' => '# Timeline Manager

## Übersicht

Umfassendes Timeline-Management für chronologische Darstellung von Ereignissen mit Frontend-Editor und Ajax-Funktionalität.

## Hauptfunktionen

- Custom Post Type "Timeline"
- Frontend-Editor für berechtigte Benutzer
- Chronologische Sortierung
- Kategoriesystem
- Ajax-basierte Interaktionen
- Responsive Design
- Filter und Suche

## Shortcodes

### [timeline_manager]
Zeigt Timeline-Manager Interface.

**Verwendung:**
```
[timeline_manager]
```

### [timeline-manager]
Alias für timeline_manager.

**Verwendung:**
```
[timeline-manager]
```

## Admin-Interface

**Menü:** Timelines

### Custom Post Type

1. **Timeline-Eintrag erstellen**
   - Titel
   - Beschreibung
   - Datum
   - Kategorie
   - Medien (Bilder, Videos)

2. **Kategorien**
   - Ereignis-Kategorien
   - Farbcodierung
   - Icons

3. **Einstellungen**
   - Anzeigeoptionen
   - Sortierung
   - Berechtigungen

## Frontend-Editor

Berechtigte Benutzer können:
- Timeline-Einträge erstellen
- Eigene Einträge bearbeiten
- Medien hochladen
- Kategorien zuweisen

## Verwendung

### Timeline anzeigen

```
[timeline_manager]
```

### Timeline mit Filter

Nutzen Sie die integrierten Filter:
- Nach Datum
- Nach Kategorie
- Nach Suchbegriff

## Darstellung

Die Timeline zeigt:
- Chronologische Reihenfolge
- Datum prominent
- Titel und Beschreibung
- Zugeordnete Medien
- Kategorie-Badge

## Technische Details

- Custom Post Type: `timeline`
- Taxonomie: `timeline_category`
- Ajax für Filter und Suche
- Responsive CSS-Grid-Layout
- Meta-Felder für Datum

## Tipps

- Nutzen Sie aussagekräftige Titel
- Kategorien helfen bei der Organisation
- Bilder erhöhen Attraktivität
- Regelmäßig aktualisieren',
        'features' => [
            'Custom Post Type Timeline',
            'Frontend-Editor',
            'Chronologische Sortierung',
            'Kategoriesystem',
            'Ajax-Interaktionen',
            'Responsive Design'
        ],
        'keywords' => ['timeline', 'chronology', 'events', 'frontend-editor', 'ajax', 'responsive']
    ],

    'event-tracker' => [
        'title' => 'Event Tracker',
        'description' => 'Event-Routing-System mit Webhooks und E-Mail-Benachrichtigungen.',
        'content' => '# Event Tracker

## Übersicht

Leistungsstarkes Event-Routing-System für automatische Webhooks und E-Mail-Benachrichtigungen basierend auf WordPress-Events.

## Hauptfunktionen

- Event-Routing mit Bedingungslogik
- Webhook-Trigger
- E-Mail-System mit Templates
- Multiple Actions pro Event
- Event-Logging
- Template-System

## Admin-Interface

**Menü:** Einstellungen → Event Tracker

### Konfiguration

1. **Event-Routing-Regeln**
   - WordPress-Event auswählen
   - Bedingungen definieren
   - Aktionen zuweisen

2. **Webhook-Konfiguration**
   - URL definieren
   - Payload-Template
   - Authentifizierung
   - Retry-Logik

3. **E-Mail-Templates**
   - Template erstellen
   - Platzhalter nutzen
   - HTML/Plain-Text
   - Empfänger definieren

4. **Event-Log**
   - Triggered Events ansehen
   - Fehler untersuchen
   - Statistiken

## Verwendung

### Event-Regel erstellen

1. Einstellungen → Event Tracker
2. "Neue Regel" klicken
3. WordPress-Event wählen (z.B. `user_register`)
4. Bedingung definieren (optional)
5. Aktion hinzufügen:
   - Webhook senden
   - E-Mail versenden
   - Beide

### Webhook konfigurieren

**Beispiel: Benutzer-Registrierung**
```
Event: user_register
Webhook-URL: https://example.com/api/user-created
Payload:
{
  "email": "{user_email}",
  "name": "{user_name}",
  "id": "{user_id}"
}
```

### E-Mail-Template

**Beispiel:**
```
Betreff: Neuer Benutzer registriert

Hallo Admin,

ein neuer Benutzer hat sich registriert:
Name: {user_name}
E-Mail: {user_email}
Datum: {registration_date}
```

## Unterstützte Events

- `user_register` - Benutzerregistrierung
- `profile_update` - Profil aktualisiert
- `publish_post` - Beitrag veröffentlicht
- `wp_login` - Benutzer eingeloggt
- `comment_post` - Kommentar erstellt
- Beliebige Custom Events

## Bedingungslogik

Definieren Sie Bedingungen:
- Feldwert = X
- Rolle ist Y
- Meta-Feld enthält Z

**Beispiel:**
```
Event: user_register
Bedingung: user_role == "member"
Aktion: Webhook + Willkommens-E-Mail
```

## Platzhalter

Verfügbare Platzhalter:
- `{user_id}` - Benutzer-ID
- `{user_email}` - E-Mail
- `{user_name}` - Name
- `{post_title}` - Post-Titel
- `{post_id}` - Post-ID
- `{date}` - Aktuelles Datum
- `{time}` - Aktuelle Zeit

## Technische Details

- Dynamische REST-Endpunkt-Registrierung
- Webhook-Retry mit Exponential Backoff
- Event-Logging in Custom DB-Table
- Template-Engine für Platzhalter
- HMAC-Signierung für Webhooks (optional)

## Tipps

- Testen Sie Webhooks mit RequestBin
- Nutzen Sie Event-Log für Debugging
- Aktivieren Sie Retry für kritische Webhooks
- Templates sauber strukturieren
- Bedingungen präzise formulieren',
        'features' => [
            'Event-Routing',
            'Webhook-Trigger',
            'E-Mail-System',
            'Bedingungslogik',
            'Event-Logging',
            'Template-System'
        ],
        'keywords' => ['events', 'webhooks', 'automation', 'email', 'routing', 'tracking']
    ],

    'abstimmen-addon' => [
        'title' => 'Abstimmen Addon',
        'description' => 'Online-Abstimmungssystem mit Zoom-Integration und Anwesenheitstracking.',
        'content' => '# Abstimmen Addon

## Übersicht

Umfassendes Online-Abstimmungssystem mit Zoom-Meeting-Integration, Anwesenheitstracking und QR-Code-Scanner für Mitgliederversammlungen.

## Hauptfunktionen

- Online-Abstimmungen durchführen
- Zoom-Meeting-Integration
- Anwesenheitstracking (QR-Code + Zoom)
- Manuelle Anwesenheit setzen
- Echtzeit-Aktualisierung
- Ergebnis-Export

## Shortcodes

### [dgptm_presence_scanner]
QR-Code-Scanner für Anwesenheit.

**Verwendung:**
```
[dgptm_presence_scanner]
```

## REST API Endpunkte

### GET/POST /wp-json/dgptm/v1/manual-flags
Manuelle Flags abrufen.

### POST /wp-json/dgptm/v1/mark-manual
Benutzer manuell als anwesend markieren.

**Payload:**
```json
{
  "user_id": 123
}
```

## Admin-Interface

**Menü:** Abstimmungen

### Funktionen

1. **Abstimmungs-Verwaltung**
   - Abstimmungen erstellen
   - Fragen definieren
   - Antwortoptionen
   - Zeitraum festlegen

2. **Teilnehmer-Liste**
   - Anwesende Teilnehmer
   - Abstimmungsberechtigung
   - Manuelle Markierung

3. **Ergebnisse**
   - Live-Anzeige
   - Prozentuale Verteilung
   - Export (PDF, CSV)

4. **Zoom-Integration**
   - Zoom-Meeting verknüpfen
   - Auto-Anwesenheit via Zoom
   - Webhook-Empfang

## Verwendung

### Abstimmung erstellen

1. Abstimmungen → Neu hinzufügen
2. Frage formulieren
3. Antwortoptionen hinzufügen
4. Zeitraum festlegen
5. Zoom-Meeting verknüpfen (optional)
6. Veröffentlichen

### Anwesenheit erfassen

**QR-Code:**
1. Scanner auf Seite einbinden: `[dgptm_presence_scanner]`
2. Teilnehmer scannen QR-Code
3. Automatische Erfassung

**Zoom:**
- Automatisch via Zoom-Webhook
- Teilnehmer in Zoom-Meeting = automatisch anwesend

**Manuell:**
1. Admin-Interface öffnen
2. Teilnehmer-Liste
3. Manuell markieren

### Abstimmung durchführen

1. Teilnehmer loggt sich ein
2. Öffnet Abstimmungsseite
3. Gibt Stimme ab
4. Echtzeit-Aktualisierung der Ergebnisse

## Zoom-Integration

**Voraussetzungen:**
- Zoom Account
- Zoom App mit Webhooks
- Webhook-URL in Zoom konfiguriert

**Automatische Erfassung:**
- Teilnehmer tritt Zoom-Meeting bei
- Zoom sendet Webhook
- System erfasst Anwesenheit automatisch

## Technische Details

- REST API für externe Integration
- Webhook-Empfang von Zoom
- QR-Code-Generierung
- Echtzeit-Updates via Ajax
- Export-Funktionen (PDF mit FPDF)

## Tipps

- Testen Sie Zoom-Integration vor wichtigen Meetings
- QR-Codes groß genug darstellen
- Manuelle Backup-Option vorsehen
- Ergebnisse regelmäßig exportieren',
        'features' => [
            'Online-Abstimmungen',
            'Zoom-Meeting-Integration',
            'Anwesenheitstracking',
            'QR-Code-Scanner',
            'Manuelle Anwesenheit',
            'Echtzeit-Aktualisierung',
            'Ergebnis-Export'
        ],
        'keywords' => ['voting', 'poll', 'zoom', 'presence', 'qr-code', 'attendance', 'meeting']
    ],

    'microsoft-gruppen' => [
        'title' => 'Microsoft Gruppen',
        'description' => 'Microsoft 365 Gruppen-Management mit Graph API Integration.',
        'content' => '# Microsoft Gruppen

## Übersicht

Umfassendes Microsoft 365 Gruppen-Management mit Graph API, OAuth2-Authentifizierung und Benutzer-Synchronisation.

## Hauptfunktionen

- Microsoft 365 Graph API Integration
- Gruppenverwaltung
- Benutzer-Synchronisation
- OAuth2-Authentifizierung
- Mitgliedschaftsprüfung

## Shortcodes

### [ms365_group_manager]
Zeigt Gruppen-Manager Interface.

**Verwendung:**
```
[ms365_group_manager]
```

### [ms365_has_any_group]
Prüft, ob Benutzer in mindestens einer Gruppe ist.

**Verwendung:**
```
[ms365_has_any_group]
  Inhalt nur für Gruppenmitglieder
[/ms365_has_any_group]
```

## Admin-Interface

**Menü:** Einstellungen → Microsoft 365

### Konfiguration

1. **OAuth-Konfiguration**
   - Client ID
   - Client Secret
   - Tenant ID
   - Redirect URI

2. **Gruppen-Synchronisation**
   - Automatische Synchronisation
   - Synchronisations-Intervall
   - Zu synchronisierende Gruppen

3. **Berechtigungen**
   - Graph API Scopes
   - Erforderliche Permissions:
     - `Group.Read.All`
     - `User.Read.All`

## Verwendung

### Einrichtung

1. Microsoft Azure Portal öffnen
2. App-Registrierung erstellen
3. Client ID und Secret kopieren
4. In WordPress-Einstellungen eintragen
5. OAuth-Flow durchlaufen

### Synchronisation

**Automatisch:**
- Täglich via Cron-Job
- Synchronisiert Gruppenmitgliedschaften
- Aktualisiert Benutzer-Daten

**Manuell:**
1. Einstellungen → Microsoft 365
2. "Jetzt synchronisieren" klicken
3. Warten auf Bestätigung

### Gruppenmitgliedschaft prüfen

**Im Code:**
```php
if (dgptm_user_in_ms365_group($user_id, $group_id)) {
    // Benutzer ist Mitglied
}
```

**Per Shortcode:**
```
[ms365_has_any_group]
  Exklusiver Inhalt
[/ms365_has_any_group]
```

## Graph API Endpunkte

Das Modul nutzt folgende Microsoft Graph Endpunkte:
- `/v1.0/groups` - Gruppen abrufen
- `/v1.0/groups/{id}/members` - Mitglieder abrufen
- `/v1.0/users` - Benutzer abrufen

## Technische Details

- OAuth2 Flow mit PKCE
- Token-Caching für Performance
- Automatische Token-Erneuerung
- Rate-Limiting-Handling
- Fehlerbehandlung und Logging

## Tipps

- Verwenden Sie Service Account für Synchronisation
- Testen Sie OAuth-Flow zuerst
- Prüfen Sie API-Permissions in Azure
- Backup vor Synchronisation
- Monitor Sync-Logs',
        'features' => [
            'Microsoft 365 Graph API',
            'Gruppenverwaltung',
            'Benutzer-Synchronisation',
            'OAuth2-Authentifizierung',
            'Mitgliedschaftsprüfung'
        ],
        'keywords' => ['microsoft', '365', 'groups', 'graph-api', 'oauth', 'sync', 'azure']
    ],

    'anwesenheitsscanner' => [
        'title' => 'Anwesenheitsscanner',
        'description' => 'Umfassendes Anwesenheitstracking-System mit QR-Codes, Zoom-Integration und PDF-Export.',
        'content' => '# Anwesenheitsscanner

## Übersicht

Vollständiges Anwesenheitstracking-System mit QR-Code-Scanning, Zoom-Integration, automatischen Webhooks und PDF-Teilnehmerlisten.

## Hauptfunktionen

- QR-Code-Generierung und -Scanning
- Zoom-Meeting-Integration mit Webhooks
- Anwesenheitstracking (manuell + automatisch)
- PDF-Generierung (Teilnehmerlisten)
- Barcode-Erstellung für Ausweise
- Live-Status-Anzeige
- REST API für externe Systeme

## Shortcodes

### [online_abstimmen_button]
Button zum Einchecken.

**Verwendung:**
```
[online_abstimmen_button]
```

### [dgptm_presence_table]
Anwesenheitstabelle anzeigen.

**Verwendung:**
```
[dgptm_presence_table]
```

### [online_abstimmen_liste]
Teilnehmer-Liste.

**Verwendung:**
```
[online_abstimmen_liste]
```

### [mitgliederversammlung_flag]
Zeigt MV-Flag des Benutzers.

**Verwendung:**
```
[mitgliederversammlung_flag]
```

### [online_abstimmen_code]
QR-Code für Benutzer generieren.

**Verwendung:**
```
[online_abstimmen_code]
```

### [online_abstimmen_switch]
Toggle zum Setzen des MV-Flags.

**Verwendung:**
```
[online_abstimmen_switch]
```

### [zoom_register_and_join]
Zoom-Registrierung und Beitritt.

**Verwendung:**
```
[zoom_register_and_join meeting_id="123456789"]
```

### [online_abstimmen_zoom_link]
Zoom-Link generieren.

**Verwendung:**
```
[online_abstimmen_zoom_link]
```

### [zoom_live_state]
Zoom Live-Status.

**Verwendung:**
```
[zoom_live_state]
```

### [dgptm_presence_scanner]
Präsenzscanner (identisch mit online_abstimmen_code).

**Verwendung:**
```
[dgptm_presence_scanner]
```

## REST API Endpunkte

### GET/POST /wp-json/dgptm/v1/zoom-test
Zoom API testen.

### POST /wp-json/dgptm/v1/zoom-register
Zoom-Registrierung durchführen.

**Payload:**
```json
{
  "meeting_id": "123456789",
  "email": "user@example.com",
  "first_name": "John",
  "last_name": "Doe"
}
```

### GET /wp-json/zoom/v1/live
Live-Meeting-Status abrufen.

### GET/POST /wp-json/zoom/v1/webhook
Zoom-Webhooks empfangen.

### POST /wp-json/zoom/v1/presence
Präsenz eintragen.

**Payload:**
```json
{
  "user_id": 123
}
```

### GET /wp-json/zoom/v1/presence-list
Präsenz-Liste abrufen.

### POST /wp-json/zoom/v1/presence-delete
Präsenz löschen.

**Payload:**
```json
{
  "presence_id": 456
}
```

### GET /wp-json/zoom/v1/presence-pdf
PDF-Teilnehmerliste generieren.

## Verwendung

### QR-Code-Check-in

1. QR-Code auf Seite einbinden:
```
[online_abstimmen_code]
```

2. Benutzer scannt mit Smartphone
3. Automatische Anwesenheitserfassung

### Zoom-Integration

**Setup:**
1. Zoom Account mit JWT/OAuth
2. Webhook-URL in Zoom konfigurieren
3. Meeting-ID in WordPress hinterlegen

**Automatische Erfassung:**
- Teilnehmer tritt Meeting bei → Webhook → Anwesenheit erfasst
- Teilnehmer verlässt Meeting → Webhook → Status aktualisiert

### Manuelle Anwesenheit

Admins können manuell Teilnehmer als anwesend markieren:
1. Präsenz-Tabelle öffnen
2. Benutzer suchen
3. "Als anwesend markieren" klicken

### PDF-Export

Teilnehmerliste als PDF:
```
GET /wp-json/zoom/v1/presence-pdf?meeting_id=123
```

## Zoom-Webhooks

Das Modul empfängt folgende Zoom-Events:
- `meeting.participant_joined` - Teilnehmer beigetreten
- `meeting.participant_left` - Teilnehmer gegangen
- `meeting.started` - Meeting gestartet
- `meeting.ended` - Meeting beendet

## Technische Details

- QR-Code-Library: phpqrcode
- PDF-Generierung: FPDF
- Barcode: Code128
- Zoom API v2
- Webhook-Verarbeitung mit Signature-Validation
- Real-time Updates via Ajax

## Tipps

- Testen Sie Zoom-Webhooks vor Live-Events
- QR-Codes groß darstellen (min. 200x200px)
- PDF-Export für Dokumentation nutzen
- Backup-Plan bei technischen Problemen',
        'features' => [
            'QR-Code-Scanning',
            'Zoom-Meeting-Integration',
            'Webhook-Empfang',
            'Anwesenheitstracking',
            'PDF-Teilnehmerlisten',
            'Barcode-Generierung',
            'Live-Status',
            'REST API'
        ],
        'keywords' => ['presence', 'attendance', 'qr-code', 'zoom', 'barcode', 'pdf', 'webhook', 'tracking']
    ],

    'gehaltsstatistik' => [
        'title' => 'Gehaltsstatistik',
        'description' => 'Anonymisiertes Gehaltsbarometer mit Statistiken, Einladungssystem und Chart-Visualisierung.',
        'content' => '# Gehaltsstatistik

## Übersicht

Anonymisiertes Gehaltsbarometer-System mit statistischen Auswertungen, Chart-Visualisierung und Einladungs-Management.

## Hauptfunktionen

- Anonymisierte Gehaltserfassung
- Statistische Auswertungen (Median, Durchschnitt, Quartile)
- Chart-Visualisierung mit Chart.js
- Einladungssystem
- Export-Funktionen (CSV/Excel)
- Bedingte Shortcodes
- Popup-Steuerung
- Admin-Dashboard mit Filterung

## Shortcodes

### [gehaltsbarometer]
Gehalts-Eingabeformular.

**Verwendung:**
```
[gehaltsbarometer]
```

### [gehaltsbarometer_statistik]
Statistik-Anzeige mit Diagrammen.

**Verwendung:**
```
[gehaltsbarometer_statistik]
```

### [gehaltsbarometer_einladung]
Einladungssystem für Teilnahme.

**Verwendung:**
```
[gehaltsbarometer_einladung]
```

### [gehaltsbarometer_chart]
Chart-Visualisierung.

**Verwendung:**
```
[gehaltsbarometer_chart type="bar"]
```

### [gehaltsbarometer_is]
Zeigt Inhalt wenn Benutzer teilgenommen hat.

**Verwendung:**
```
[gehaltsbarometer_is value="participated"]
  Vielen Dank für Ihre Teilnahme!
[/gehaltsbarometer_is]
```

### [gehaltsbarometer_isnot]
Zeigt Inhalt wenn Benutzer nicht teilgenommen hat.

**Verwendung:**
```
[gehaltsbarometer_isnot value="participated"]
  Bitte nehmen Sie teil!
[/gehaltsbarometer_isnot]
```

### [gehaltsbarometer_filled]
Prüft ob Formular ausgefüllt wurde.

**Verwendung:**
```
[gehaltsbarometer_filled]
  Sie haben bereits teilgenommen.
[/gehaltsbarometer_filled]
```

### [gehaltsbarometer_popup_guard]
Popup-Schutz (verhindert mehrfache Anzeige).

**Verwendung:**
```
[gehaltsbarometer_popup_guard]
  <!-- Popup-Inhalt -->
[/gehaltsbarometer_popup_guard]
```

## Admin-Interface

**Menü:** Gehaltsbarometer

### Funktionen

1. **Admin-Dashboard**
   - Gesamtstatistiken
   - Teilnehmerzahlen
   - Durchschnittswerte
   - Verteilungsdiagramme

2. **Statistik-Übersicht**
   - Nach Fachgebiet
   - Nach Berufserfahrung
   - Nach Region
   - Zeitlicher Verlauf

3. **Export-Funktionen**
   - CSV-Export (anonymisiert)
   - Excel-Export
   - PDF-Report

4. **Einladungs-Management**
   - E-Mail-Kampagnen
   - Reminder versenden
   - Teilnahme-Status

## Verwendung

### Eingabeformular einbinden

```
[gehaltsbarometer]
```

Benutzer kann eingeben:
- Bruttogehalt
- Fachgebiet
- Berufserfahrung (Jahre)
- Position
- Bundesland/Region

### Statistiken anzeigen

```
[gehaltsbarometer_statistik]
```

Zeigt:
- Durchschnitt
- Median
- Quartile (25%, 75%)
- Min/Max (ohne Ausreißer)
- Anzahl Teilnehmer

### Bedingte Inhalte

**Für Teilnehmer:**
```
[gehaltsbarometer_is value="participated"]
  Vielen Dank! Hier Ihre personalisierten Vergleichsdaten:
  [gehaltsbarometer_chart]
[/gehaltsbarometer_is]
```

**Für Nicht-Teilnehmer:**
```
[gehaltsbarometer_isnot value="participated"]
  <a href="/teilnahme">Jetzt teilnehmen</a>
[/gehaltsbarometer_isnot]
```

## Chart-Typen

- `bar` - Balkendiagramm (Gehaltsverteilung)
- `line` - Liniendiagramm (zeitlicher Verlauf)
- `pie` - Kreisdiagramm (Fachgebiete)
- `box` - Box-Plot (Quartile)

**Beispiel:**
```
[gehaltsbarometer_chart type="bar" field="salary"]
```

## Anonymisierung

- Keine personenbezogenen Daten gespeichert
- Nur aggregierte Statistiken
- Minimum 5 Teilnehmer für Anzeige
- Keine Rückverfolgbarkeit

## Technische Details

- Chart.js für Visualisierung
- Statistische Berechnungen server-seitig
- Ajax-basierte Formulare
- Transients für Performance
- Export via PhpSpreadsheet

## Tipps

- Mindestens 10 Teilnehmer für aussagekräftige Statistiken
- Regelmäßige Reminder versenden
- Anonymität betonen
- Ergebnisse nur aggregiert zeigen',
        'features' => [
            'Anonymisierte Gehaltserfassung',
            'Statistische Auswertungen',
            'Chart-Visualisierung',
            'Einladungssystem',
            'Export (CSV/Excel/PDF)',
            'Bedingte Shortcodes',
            'Popup-Steuerung',
            'Admin-Dashboard'
        ],
        'keywords' => ['salary', 'statistics', 'survey', 'anonymous', 'charts', 'analytics', 'barometer']
    ],

    // Payment Module (2)
    'stripe-formidable' => [
        'title' => 'Stripe Formidable',
        'description' => 'Stripe-Integration für Formidable Forms mit SEPA und Kartenzahlung.',
        'content' => '# Stripe Formidable

## Übersicht

Vollständige Stripe-Integration für Formidable Forms mit Unterstützung für SEPA-Lastschrift und Kreditkartenzahlungen.

## Hauptfunktionen

- SEPA-Lastschrift-Zahlungen
- Kreditkartenzahlungen
- Formidable Forms Integration
- Stripe Elements UI
- Webhook-Handling für Zahlungsstatus
- Test/Live-Modus
- Zahlungsstatus-Tracking

## Shortcodes

### [stripe_formidable]
Zeigt Stripe-Zahlungsformular.

**Parameter:**
- `form_id` - Formidable Form ID
- `mode` - `test` oder `live`

**Verwendung:**
```
[stripe_formidable form_id="5" mode="live"]
```

## Admin-Interface

**Menü:** Formidable → Stripe Settings

### Einstellungen

1. **API-Schlüssel**
   - Live Publishable Key
   - Live Secret Key
   - Test Publishable Key
   - Test Secret Key

2. **Webhook-Konfiguration**
   - Webhook-URL anzeigen
   - Webhook-Secret
   - Event-Types konfigurieren

3. **Zahlungseinstellungen**
   - Akzeptierte Zahlungsmethoden
   - Währung
   - Standard-Beschreibung

## Verwendung

### Setup

1. Stripe Account erstellen
2. API-Schlüssel abrufen
3. In WordPress-Einstellungen eintragen
4. Webhook in Stripe konfigurieren

### Formular erstellen

1. Formidable → Formulare → Neu
2. Formularfelder hinzufügen
3. Stripe-Zahlungsfeld hinzufügen
4. Betrag festlegen (fest oder Feld-basiert)
5. Veröffentlichen

### Shortcode einbinden

```
[stripe_formidable form_id="5" mode="live"]
```

## Zahlungsmethoden

### SEPA-Lastschrift

**Erforderliche Felder:**
- Name
- E-Mail
- IBAN
- SEPA-Mandat (Checkbox)

**Flow:**
1. Benutzer gibt IBAN ein
2. SEPA-Mandat akzeptieren
3. Zahlung wird initiiert
4. Bestätigung nach Verarbeitung

### Kreditkarte

**Erforderliche Felder:**
- Kartennummer
- Ablaufdatum
- CVC
- Name auf Karte

**Flow:**
1. Stripe Elements lädt
2. Kartendet als eingeben
3. Zahlung wird sofort verarbeitet
4. Bestätigung

## Webhook-Events

Das Modul verarbeitet folgende Stripe-Events:
- `payment_intent.succeeded` - Zahlung erfolgreich
- `payment_intent.payment_failed` - Zahlung fehlgeschlagen
- `charge.refunded` - Rückerstattung
- `customer.created` - Kunde erstellt

## Technische Details

- Stripe API v2023-10-16
- Stripe Elements für sichere Karteneingabe
- PCI-DSS-konform (Stripe hosted)
- Webhook-Signatur-Validierung
- Formidable Forms Entry-Verknüpfung

## Tipps

- Testen Sie im Test-Modus zuerst
- Verwenden Sie Stripe Test-Karten
- Webhook-Signatur validieren
- Fehlerbehandlung implementieren
- Benutzer über Status informieren',
        'features' => [
            'SEPA-Lastschrift',
            'Kreditkartenzahlung',
            'Formidable Forms Integration',
            'Stripe Elements',
            'Webhook-Handling',
            'Test/Live-Modus',
            'Zahlungsstatus-Tracking'
        ],
        'keywords' => ['stripe', 'payment', 'sepa', 'credit-card', 'formidable', 'checkout', 'webhook']
    ],

    'gocardless' => [
        'title' => 'GoCardless Direct Debit',
        'description' => 'GoCardless SEPA-Lastschrift-Integration mit Mandatsverwaltung.',
        'content' => '# GoCardless Direct Debit Manager

## Übersicht

Umfassende GoCardless-Integration für SEPA-Lastschriftzahlungen mit Mandatsverwaltung und Formidable Forms-Anbindung.

## Hauptfunktionen

- SEPA-Lastschrift via GoCardless
- Mandatsverwaltung
- Formidable Forms Integration
- Zahlungs-Tracking
- Webhook-Verarbeitung
- Mandats-PDF-Generierung

## Shortcodes

### [gcl_formidable]
GoCardless-Zahlungsformular.

**Parameter:**
- `form_id` - Formidable Form ID

**Verwendung:**
```
[gcl_formidable form_id="7"]
```

## Admin-Interface

**Menü:** Formidable → GoCardless

### Funktionen

1. **API-Konfiguration**
   - Access Token
   - Sandbox/Live-Modus
   - Webhook-Secret

2. **Mandats-Verwaltung**
   - Aktive Mandate anzeigen
   - Status prüfen
   - Mandate stornieren

3. **Zahlungs-Übersicht**
   - Alle Zahlungen
   - Status (pending, confirmed, failed)
   - Filter und Suche

## Verwendung

### Setup

1. GoCardless Account erstellen
2. Access Token generieren
3. In WordPress-Einstellungen eintragen
4. Webhook-URL in GoCardless konfigurieren

### Lastschrift-Formular erstellen

1. Formidable → Formulare → Neu
2. Felder hinzufügen:
   - Name
   - E-Mail
   - Adresse
   - IBAN (optional - GoCardless UI)
3. GoCardless-Feld hinzufügen
4. Betrag definieren
5. Veröffentlichen

### Flow

1. Benutzer füllt Formular aus
2. Klick auf "Zahlung einrichten"
3. Weiterleitung zu GoCardless
4. IBAN eingeben + Mandat akzeptieren
5. Redirect zurück zu WordPress
6. Zahlung wird erstellt

## Mandats-Verwaltung

**Mandatsstatus:**
- `pending_customer_approval` - Wartet auf Kunde
- `pending_submission` - Wartet auf Einreichung
- `submitted` - Bei Bank eingereicht
- `active` - Aktiv
- `failed` - Fehlgeschlagen
- `cancelled` - Storniert

**Aktionen:**
- Mandat anzeigen
- Mandat stornieren
- PDF generieren

## Zahlungs-Tracking

**Status:**
- `pending_submission` - Wartet auf Einreichung
- `submitted` - Eingereicht
- `confirmed` - Bestätigt
- `paid_out` - Ausgezahlt
- `failed` - Fehlgeschlagen
- `cancelled` - Storniert

## Webhooks

Verarbeitete Events:
- `mandates` - Mandats-Updates
- `payments` - Zahlungs-Updates
- `payouts` - Auszahlungs-Updates

## PDF-Generierung

Automatische Generierung von:
- Mandatsbestätigung
- Zahlungsquittungen
- Übersichten

## Technische Details

- GoCardless API v2015-07-06
- Webhook-Signatur-Validierung
- FPDF für PDF-Generierung
- Formidable Entry-Verknüpfung
- Cron für Status-Updates

## Tipps

- Sandbox-Modus für Tests nutzen
- Webhooks immer validieren
- Status regelmäßig synchronisieren
- Mandate vor Zahlungen prüfen',
        'features' => [
            'SEPA-Lastschrift',
            'Mandatsverwaltung',
            'Formidable Forms Integration',
            'Zahlungs-Tracking',
            'Webhook-Verarbeitung',
            'Mandats-PDF'
        ],
        'keywords' => ['gocardless', 'sepa', 'direct-debit', 'mandate', 'payment', 'formidable', 'webhook']
    ],

    // Auth Module (1)
    'otp-login' => [
        'title' => 'OTP Login',
        'description' => 'Sicheres OTP-basiertes Login-System mit Rate Limiting und Multisite-Support.',
        'content' => '# OTP Login

## Übersicht

Sicheres One-Time-Password (OTP) Login-System mit E-Mail-Versand, Rate Limiting, Multisite-Support und rotierendem Preloader.

## Hauptfunktionen

- OTP-basierte Authentifizierung (kein Passwort)
- E-Mail-Versand von 6-stelligen Codes
- Rate Limiting (IP + E-Mail)
- Brute-Force-Schutz
- Multisite-Support
- Rotierender Logo-Preloader
- SMTP-Konfiguration
- Session-Management

## Shortcodes

### [dgptm_otp_login]
Zeigt OTP-Login-Formular.

**Parameter:**
- `redirect` - Redirect-URL nach Login
- `title` - Formular-Titel
- `subtitle` - Untertitel

**Verwendung:**
```
[dgptm_otp_login redirect="/dashboard" title="Anmelden"]
```

### [dgptm_logout_link]
Logout-Link.

**Parameter:**
- `text` - Link-Text
- `class` - CSS-Klasse
- `redirect` - Redirect nach Logout

**Verwendung:**
```
[dgptm_logout_link text="Abmelden" redirect="/"]
```

### [dgptm_logout_url]
Gibt Logout-URL zurück.

**Verwendung:**
```
<a href="[dgptm_logout_url]">Abmelden</a>
```

## Admin-Interface

**Menü:** DGPTM → OTP Login

### Tabs

1. **E-Mail & Sicherheit**
   - SMTP-Server
   - SMTP-Port
   - SMTP-Benutzername
   - SMTP-Passwort
   - Absender-E-Mail
   - Absender-Name
   - Rate Limiting:
     - Max. Anfragen pro IP
     - Max. Anfragen pro E-Mail
     - Zeitfenster (Minuten)
     - Cooldown (Sekunden)

2. **Preloader**
   - Logo hochladen
   - Hintergrundfarbe
   - Spinner-Farbe
   - Rotation-Geschwindigkeit
   - Vorschau

3. **Anleitung**
   - Setup-Dokumentation
   - Code-Beispiele
   - Troubleshooting

## Verwendung

### Login-Flow

1. Benutzer gibt E-Mail ein
2. OTP wird generiert und per E-Mail versendet
3. Benutzer gibt 6-stelligen Code ein
4. Bei korrektem Code: Login
5. Bei falschem Code: Fehler (3 Versuche)

### SMTP konfigurieren

1. DGPTM → OTP Login → E-Mail & Sicherheit
2. SMTP-Server eintragen (z.B. smtp.gmail.com)
3. SMTP-Port (587 für TLS, 465 für SSL)
4. Credentials eintragen
5. Test-E-Mail senden

### Preloader anpassen

1. DGPTM → OTP Login → Preloader
2. Logo hochladen (PNG, transparent empfohlen)
3. Farben anpassen
4. Geschwindigkeit einstellen
5. Vorschau ansehen
6. Speichern

## Sicherheitsfeatures

### Rate Limiting

**IP-basiert:**
- Max. 5 OTP-Anfragen pro 15 Minuten
- Nach Überschreitung: Cooldown 30 Minuten

**E-Mail-basiert:**
- Max. 3 OTP-Anfragen pro 15 Minuten
- Verhindert E-Mail-Spam

### Brute-Force-Schutz

- Max. 3 Versuche pro OTP
- OTP-Ablauf nach 10 Minuten
- IP-Tracking

### Code-Generierung

- 6-stelliger numerischer Code
- Kryptographisch sicher (random_int)
- Einmalige Verwendung

## Multisite-Support

- Funktioniert auf Haupt-Site und Sub-Sites
- Site-spezifische Einstellungen
- Netzwerk-weite Konfiguration möglich

## Technische Details

- Session-basierte OTP-Speicherung
- Transients für Rate Limiting
- PHPMailer für E-Mail-Versand
- CSS3-Animationen für Preloader
- AJAX für nahtlose UX

## Tipps

- SMTP statt wp_mail() nutzen
- Rate Limits je nach Benutzeranzahl anpassen
- Logo als PNG mit transparentem Hintergrund
- Redirect nach Login konfigurieren
- Test-E-Mails regelmäßig prüfen',
        'features' => [
            'OTP-Authentifizierung',
            'E-Mail-Code-Versand',
            'Rate Limiting',
            'Multisite-Support',
            'Rotierender Preloader',
            'SMTP-Konfiguration',
            'Session-Management',
            'Brute-Force-Schutz'
        ],
        'keywords' => ['otp', 'login', 'authentication', 'email', 'security', 'rate-limiting', 'smtp', 'preloader']
    ],

    // Media Module (2)
    'vimeo-streams' => [
        'title' => 'Vimeo Streams',
        'description' => 'Multi-Stream Vimeo-Video-Management mit Playlist-Unterstützung.',
        'content' => '# Vimeo Streams

## Übersicht

Umfassendes Vimeo-Video-Management mit Multi-Stream-Unterstützung, Playlist-Funktionen und Zugriffskontrolle.

## Hauptfunktionen

- Vimeo API Integration
- Multi-Stream-Unterstützung
- Playlist-Management
- Responsive Player
- Zugriffskontrolle (ACF/Rollen)
- Statistik-Tracking
- Video-Kategorien

## Shortcodes

### [vimeo_stream]
Einzelnes Vimeo-Video einbetten.

**Parameter:**
- `id` - Vimeo Video-ID
- `width` - Breite (optional)
- `height` - Höhe (optional)

**Verwendung:**
```
[vimeo_stream id="123456789"]
[vimeo_stream id="123456789" width="800" height="450"]
```

### [vimeo_playlist]
Vimeo-Playlist anzeigen.

**Parameter:**
- `ids` - Komma-getrennte Video-IDs

**Verwendung:**
```
[vimeo_playlist ids="123456789,987654321,456789123"]
```

## Admin-Interface

**Menü:** Vimeo Streams

### Funktionen

1. **Video-Verwaltung**
   - Videos hinzufügen (per Vimeo-ID)
   - Titel und Beschreibung
   - Kategorien zuweisen
   - Zugriffskontrolle setzen

2. **Playlist-Editor**
   - Playlists erstellen
   - Videos sortieren (Drag & Drop)
   - Playlist-Titel und Beschreibung
   - Shortcode generieren

3. **API-Konfiguration**
   - Vimeo Access Token
   - Client ID
   - Client Secret
   - Sandbox-Modus

## Verwendung

### Vimeo API konfigurieren

1. Vimeo-Developer-Account erstellen
2. App erstellen auf developer.vimeo.com
3. Access Token generieren
4. In WordPress eintragen (Vimeo Streams → Einstellungen)

### Video hinzufügen

1. Vimeo Streams → Neu hinzufügen
2. Vimeo-ID eintragen (z.B. 123456789)
3. Video wird automatisch geladen (Titel, Thumbnail)
4. Optional: Beschreibung ergänzen
5. Kategorie wählen
6. Zugriffskontrolle (öffentlich / Rollen / ACF)
7. Veröffentlichen

### Playlist erstellen

1. Vimeo Streams → Playlists → Neu
2. Titel eingeben
3. Videos hinzufügen (Mehrfachauswahl)
4. Videos sortieren
5. Shortcode kopieren
6. Auf Seite einbinden

### Zugriffskontrolle

**Optionen:**
- **Öffentlich:** Alle können sehen
- **Nur eingeloggt:** Nur angemeldete Benutzer
- **Rollen:** Nur bestimmte Rollen
- **ACF-Feld:** Basierend auf User-Meta-Feld

**Beispiel:**
```
Zugriff: Nur für Rolle "Mitglied"
```

## Responsive Player

Player passt sich automatisch an:
- Desktop: Volle Breite
- Tablet: 100% Container
- Mobile: Responsiv mit Aspect Ratio

## Statistik-Tracking

**Erfasst:**
- Aufrufe
- Wiedergabezeit
- Abschlussrate
- Benutzer-Aktivität

**Ansicht:**
- Admin-Dashboard
- Pro Video
- Gesamt-Statistiken

## Technische Details

- Vimeo API v3.4
- Vimeo Player SDK
- Responsive Embed mit CSS
- Access Token Caching
- Rate Limiting

## Tipps

- Nutzen Sie Vimeo Pro für bessere Features
- Kategorien für Organisation
- Playlists für Kursstrukturen
- Zugriffskontrolle für Premium-Content',
        'features' => [
            'Vimeo API Integration',
            'Multi-Stream',
            'Playlist-Management',
            'Responsive Player',
            'Zugriffskontrolle',
            'Statistik-Tracking'
        ],
        'keywords' => ['vimeo', 'video', 'streaming', 'playlist', 'player', 'embed', 'api']
    ],

    'wissens-bot' => [
        'title' => 'Wissens-Bot',
        'description' => 'KI-gestützter Chatbot mit Claude AI und Multi-Datenbank-Integration.',
        'content' => '# Wissens-Bot

## Übersicht

KI-gestützter Chatbot powered by Claude AI (Anthropic) mit Multi-Datenbank-Zugriff und Kontext-Management.

## Hauptfunktionen

- Claude AI Integration (Anthropic)
- Multi-Datenbank-Zugriff (WordPress, externe DBs)
- Chat-Widget mit floating button
- Kontext-Management
- Verlaufs-Speicherung
- Admin-Dashboard
- Anpassbares Design

## Shortcodes

### [wissens_bot]
Zeigt Chat-Widget.

**Verwendung:**
```
[wissens_bot]
```

## Admin-Interface

**Menü:** Wissens-Bot

### Konfiguration

1. **Claude API**
   - API-Schlüssel (Anthropic)
   - Model-Auswahl (Claude 3.5 Sonnet, etc.)
   - Max. Tokens
   - Temperatur

2. **Datenquellen-Verwaltung**
   - WordPress-Datenbank (Posts, Pages, Custom Post Types)
   - Externe Datenbanken
   - API-Endpunkte
   - Gewichtung der Quellen

3. **Chat-Einstellungen**
   - Widget-Position (rechts unten / links unten / eingebettet)
   - Farben (primär, sekundär, text)
   - Willkommensnachricht
   - Platzhalter-Text

4. **Verlaufs-Ansicht**
   - Alle Conversations
   - Nach Benutzer filtern
   - Nach Datum filtern
   - Exportieren

## Verwendung

### Setup

1. Anthropic API-Schlüssel erhalten (console.anthropic.com)
2. Wissens-Bot → Einstellungen
3. API-Schlüssel eintragen
4. Modell wählen (empfohlen: claude-3-5-sonnet-20241022)
5. Datenquellen konfigurieren
6. Speichern

### Datenquellen hinzufügen

**WordPress-Inhalte:**
- Automatisch: Posts, Pages, Custom Post Types
- Durchsuchbar: Titel, Inhalt, Meta-Felder

**Externe Datenbank:**
1. Datenquellen → Neue hinzufügen
2. Typ: Datenbank
3. Credentials eintragen
4. Tabellen auswählen
5. Speichern

**API-Endpunkt:**
1. Datenquellen → Neue hinzufügen
2. Typ: API
3. Endpoint-URL
4. Authentifizierung
5. Response-Mapping

### Chat-Widget einbinden

**Floating Button:**
Widget erscheint automatisch auf allen Seiten (konfigurierbar in Einstellungen).

**Eingebettet:**
```
[wissens_bot]
```

### Kontext-Management

Der Bot nutzt:
1. Aktuelle Seite als Kontext
2. Benutzer-Profil (wenn eingeloggt)
3. Conversation-History
4. Konfigurierte Datenquellen

## Claude AI Features

**Verwendet:**
- Claude 3.5 Sonnet (neuestes Modell)
- 200k Token Context Window
- Deutsch-Unterstützung
- Streaming für Echtzeit-Antworten

**Capabilities:**
- Natürliche Konversation
- Faktenwissen aus Datenquellen
- Mehrsprachig
- Code-Verständnis

## Datenschutz

- Conversations werden lokal gespeichert
- Nur verschickt an Claude API (verschlüsselt)
- DSGVO-konform mit Einwilligung
- Löschung auf Anfrage

## Technische Details

- Anthropic Claude API
- Server-Side-Rendering für Sicherheit
- Streaming API für Echtzeit-Responses
- Vector-Search für bessere Antworten (optional)
- Caching für Performance

## Tipps

- Verwenden Sie klare Datenquellen-Namen
- Testen Sie Bot mit typischen Fragen
- Passen Sie Willkommensnachricht an
- Monitor API-Kosten in Anthropic Console
- Nutzen Sie Conversation-History für Verbesserungen',
        'features' => [
            'Claude AI Integration',
            'Multi-Datenbank-Zugriff',
            'Chat-Widget',
            'Kontext-Management',
            'Verlaufs-Speicherung',
            'Admin-Dashboard'
        ],
        'keywords' => ['ai', 'chatbot', 'claude', 'anthropic', 'chat', 'assistant', 'knowledge-base']
    ],

    // Content Module (3)
    'publication-workflow' => [
        'title' => 'Publication Workflow',
        'description' => 'Publikations-Workflow-Management-System mit Status-Tracking und Freigabe-Prozess.',
        'content' => '# Publication Workflow

## Übersicht

Umfassendes Workflow-Management-System für Publikationen mit mehrstufigem Freigabe-Prozess und Status-Tracking.

## Hauptfunktionen

- Workflow-Management
- Status-Tracking (Draft, Review, Approved, Published)
- Freigabe-Prozess mit Rollen
- Benachrichtigungen
- Aufgaben-Zuweisung
- Dashboard-Übersicht

## Shortcodes

### [publication_workflow]
Workflow-Interface anzeigen.

**Verwendung:**
```
[publication_workflow]
```

## Admin-Interface

**Menü:** Publikationen

### Funktionen

1. **Workflow-Status**
   - Alle Publikationen nach Status
   - Draft → Review → Approved → Published
   - Abgelehnte Publikationen

2. **Aufgaben-Verwaltung**
   - Zugewiesene Aufgaben
   - Pending Reviews
   - Freigaben erforderlich

3. **Freigabe-Prozess**
   - Review-Anfrage stellen
   - Freigabe erteilen/ablehnen
   - Kommentare hinzufügen
   - Historie anzeigen

## Verwendung

### Publikation erstellen

1. Publikationen → Neu hinzufügen
2. Inhalt erstellen
3. Status: Draft
4. Zur Review einreichen

### Review-Prozess

**Autor:**
1. Publikation fertigstellen
2. "Zur Review einreichen"
3. Reviewer wird benachrichtigt

**Reviewer:**
1. Benachrichtigung erhalten
2. Publikationen → Pending Reviews
3. Inhalt prüfen
4. Freigeben oder Ablehnen (mit Kommentar)

**Editor:**
1. Genehmigte Publikationen → Approve
2. Final freigeben
3. Veröffentlichen

### Status-Übersicht

**Draft:**
- Noch in Bearbeitung
- Autor kann bearbeiten

**Review:**
- Wartet auf Reviewer
- Autor kann nicht mehr bearbeiten

**Approved:**
- Von Reviewer freigegeben
- Wartet auf finale Freigabe

**Published:**
- Veröffentlicht
- Öffentlich sichtbar

**Rejected:**
- Abgelehnt
- Zurück an Autor mit Kommentaren

## Benachrichtigungen

**E-Mail bei:**
- Neue Review-Anfrage
- Freigabe erteilt
- Ablehnung
- Veröffentlichung

## Berechtigungen

**Rollen:**
- **Autor:** Erstellen, zur Review einreichen
- **Reviewer:** Reviewen, Freigeben/Ablehnen
- **Editor:** Finale Freigabe, Veröffentlichen
- **Administrator:** Alles

## Dashboard

**Zeigt:**
- Meine Publikationen nach Status
- Pending Reviews
- Aufgaben
- Statistiken

## Technische Details

- Custom Post Type: `publication`
- Status als Post-Status oder Meta
- E-Mail-Benachrichtigungen via wp_mail
- ACL (Access Control List) für Berechtigungen

## Tipps

- Definieren Sie klare Review-Richtlinien
- Nutzen Sie Kommentare für Feedback
- Dashboard für Überblick
- Benachrichtigungen aktivieren',
        'features' => [
            'Workflow-Management',
            'Status-Tracking',
            'Freigabe-Prozess',
            'Benachrichtigungen',
            'Aufgaben-Zuweisung',
            'Dashboard'
        ],
        'keywords' => ['workflow', 'publication', 'review', 'approval', 'status', 'editorial']
    ],

    'news-management' => [
        'title' => 'News Management',
        'description' => 'News-Verwaltungssystem mit Frontend-Editor und Berechtigungen.',
        'content' => '# News Management

## Übersicht

Umfassendes News-Verwaltungssystem mit Frontend-Editor, Berechtigungssystem und ACF-Integration.

## Hauptfunktionen

- Custom Post Type "News"
- Frontend-Editor
- Berechtigungssystem (ACF: news_schreiben, news_alle)
- Kategorien und Tags
- Datum-Filter
- Autor-Filter
- Responsive Design

## Shortcodes

### [news-list]
News-Liste anzeigen.

**Parameter:**
- `category` - Kategorie-Filter
- `limit` - Anzahl (Standard: 10)
- `author` - Autor-ID
- `orderby` - Sortierung (date, title)
- `order` - ASC/DESC

**Verwendung:**
```
[news-list limit="5" category="aktuelles"]
[news-list author="3" orderby="date" order="DESC"]
```

### [news-edit-form]
Frontend News-Editor.

**Verwendung:**
```
[news-edit-form]
```

## Admin-Interface

**Menü:** News (Custom Post Type)

### Funktionen

1. **News-Verwaltung**
   - Alle News anzeigen
   - Filtern nach Kategorie, Autor, Datum
   - Bulk-Aktionen

2. **Kategorien**
   - News-Kategorien verwalten
   - Hierarchie

3. **Tags**
   - Schlagwörter zuweisen

## Berechtigungssystem

### ACF-Felder (User Meta)

**news_schreiben:**
- Wert: true/false
- Berechtigung: News erstellen und eigene bearbeiten

**news_alle:**
- Wert: true/false
- Berechtigung: Alle News bearbeiten und löschen

### Frontend-Editor-Zugriff

**Nur news_schreiben = true:**
- Sieht nur eigene News
- Kann nur eigene bearbeiten

**news_alle = true:**
- Sieht alle News
- Kann alle bearbeiten und löschen

## Verwendung

### News-Liste anzeigen

```
[news-list limit="10"]
```

### Frontend-Editor

1. Benutzer mit `news_schreiben` = true
2. Seite mit `[news-edit-form]` aufrufen
3. Formular zum Erstellen/Bearbeiten erscheint
4. News erstellen
5. Speichern → Status: Pending (wenn nicht news_alle)
6. Admin muss veröffentlichen (oder auto-publish wenn news_alle)

### News-Kategorien

1. News → Kategorien
2. Neue Kategorie erstellen
3. In News zuweisen
4. In Liste filtern: `[news-list category="slug"]`

## Frontend-Editor Features

**Felder:**
- Titel
- Inhalt (Wysiwyg)
- Ausschnitt/Excerpt
- Kategorien (Mehrfachauswahl)
- Tags
- Featured Image

**Buttons:**
- Speichern als Draft
- Veröffentlichen (wenn berechtigt)
- Vorschau
- Löschen (nur eigene)

## Technische Details

- Custom Post Type: `news`
- Taxonomien: `news_category`, `news_tag`
- ACF für Berechtigungen
- Frontend-Editor mit Ajax
- Responsive CSS

## Tipps

- Nutzen Sie Kategorien für Organisation
- Tags für bessere Suche
- Frontend-Editor für Autoren ohne Backend-Zugriff
- news_alle nur für vertrauenswürdige Redakteure',
        'features' => [
            'Custom Post Type News',
            'Frontend-Editor',
            'ACF-Berechtigungen (news_schreiben, news_alle)',
            'Kategorien und Tags',
            'Datum-Filter',
            'Autor-Filter'
        ],
        'keywords' => ['news', 'posts', 'frontend-editor', 'acf', 'permissions', 'categories']
    ],

    'ebcp-guidelines' => [
        'title' => 'EBCP Guidelines',
        'description' => 'EBCP-Richtlinien-Viewer mit Multi-Language, Filter und Auto-Translate.',
        'content' => '# EBCP Guidelines

## Übersicht

Mehrsprachiger EBCP-Richtlinien-Viewer mit Filter, Suche, Übersetzungs-Editor und Auto-Translate via DeepL/LibreTranslate.

## Hauptfunktionen

- Mehrsprachige Richtlinien (DE, EN, FR, ES, IT)
- Filterbare Tabelle
- Suchfunktion
- Bearbeitungsfunktion
- PDF-Export
- Übersetzungs-Editor mit Auto-Translate
- DeepL API Integration
- LibreTranslate Fallback
- Kategorien-Filter
- Responsive Design

## Shortcodes

### [ebcpdgptm_guidelines]
Richtlinien-Tabelle anzeigen.

**Parameter:**
- `lang` - Sprache (de, en, fr, es, it)
- `category` - Kategorie-Filter

**Verwendung:**
```
[ebcpdgptm_guidelines lang="de"]
[ebcpdgptm_guidelines lang="en" category="cardiovascular"]
```

## REST API Endpunkte

### GET /wp-json/ebcp/v1/guidelines
Alle Richtlinien abrufen.

**Query-Parameter:**
- `lang` - Sprache
- `category` - Kategorie
- `search` - Suchbegriff

### POST /wp-json/ebcp/v1/translate
Auto-Translate mit DeepL oder LibreTranslate.

**Payload:**
```json
{
  "text": "Zu übersetzender Text",
  "source_lang": "DE",
  "target_lang": "EN",
  "engine": "deepl"
}
```

**Response:**
```json
{
  "translation": "Translated text",
  "engine": "deepl",
  "source_lang": "DE",
  "target_lang": "EN"
}
```

### GET /wp-json/ebcp/v1/translation-info
Übersetzungsstatus abrufen.

**Response:**
```json
{
  "total_guidelines": 150,
  "translations": {
    "de": 150,
    "en": 145,
    "fr": 120,
    "es": 100,
    "it": 90
  },
  "missing": {
    "en": 5,
    "fr": 30,
    "es": 50,
    "it": 60
  }
}
```

## Admin-Interface

**Menü:** EBCP Guidelines

### Tabs

1. **Richtlinien-Editor**
   - Alle Richtlinien
   - Hinzufügen/Bearbeiten
   - Löschen
   - Kategorien zuweisen

2. **Übersetzungs-Editor**
   - Fehlende Übersetzungen anzeigen
   - DeepL/LibreTranslate Auto-Translate
   - Manuelle Übersetzung
   - Übersetzungsstatus

3. **DeepL/LibreTranslate Integration**
   - DeepL API-Schlüssel
   - LibreTranslate Server-URL
   - Fallback-Engine
   - Test-Funktion

4. **PDF-Export-Einstellungen**
   - Logo
   - Header/Footer
   - Schriftarten
   - Layout

## Verwendung

### Richtlinien anzeigen

```
[ebcpdgptm_guidelines lang="de"]
```

**Features:**
- Filterbare Tabelle (Kategorie, Suchbegriff)
- Sortierbar (Titel, Kategorie, Datum)
- Paginierung
- Responsive

### Übersetzungs-Workflow

**Auto-Translate:**
1. EBCP Guidelines → Übersetzungs-Editor
2. Fehlende Übersetzungen werden angezeigt
3. "Auto-Translate" klicken
4. DeepL übersetzt automatisch
5. Bei Fehler: LibreTranslate Fallback
6. Übersetzung prüfen und ggf. anpassen
7. Speichern

**Manuelle Übersetzung:**
1. Richtlinie auswählen
2. Sprache wählen
3. Übersetzung eingeben
4. Speichern

### PDF-Export

**Für Benutzer:**
- Richtlinien-Tabelle anzeigen
- "Als PDF exportieren" klicken
- PDF wird generiert

**Admin-Konfiguration:**
1. EBCP Guidelines → PDF-Export
2. Logo hochladen
3. Header/Footer-Text
4. Schriftgröße
5. Layout wählen

## DeepL Integration

**Setup:**
1. DeepL API-Schlüssel erhalten (deepl.com)
2. EBCP Guidelines → DeepL Integration
3. API-Schlüssel eintragen
4. Test-Funktion nutzen
5. Speichern

**Unterstützte Sprachen:**
- DE ↔ EN, FR, ES, IT
- EN ↔ DE, FR, ES, IT
- Alle Kombinationen

**Limits:**
- Free: 500.000 Zeichen/Monat
- Pro: Unbegrenzt (kostenpflichtig)

## LibreTranslate Fallback

**Setup:**
1. LibreTranslate Server-URL
2. (Optional) API-Schlüssel
3. Test-Funktion
4. Als Fallback aktivieren

**Vorteile:**
- Open Source
- Selbst-gehostet möglich
- Kostenlos
- DSGVO-konform

## Kategorien

Vordefinierte Kategorien:
- Cardiovascular
- Oncology
- Neurology
- Diabetes
- Respiratory
- Custom

## Technische Details

- Custom Database Table: `wp_ebcp_guidelines`
- DeepL API v2
- LibreTranslate API
- FPDF für PDF-Export
- DataTables.js für Tabelle
- REST API für Frontend

## Tipps

- Nutzen Sie DeepL für beste Qualität
- LibreTranslate als DSGVO-konforme Alternative
- Prüfen Sie Auto-Übersetzungen
- PDF-Export für Offline-Nutzung
- Kategorien für Organisation',
        'features' => [
            'Mehrsprachig (DE, EN, FR, ES, IT)',
            'Filterbare Tabelle',
            'Suchfunktion',
            'PDF-Export',
            'Übersetzungs-Editor',
            'DeepL Auto-Translate',
            'LibreTranslate Fallback',
            'Kategorien'
        ],
        'keywords' => ['guidelines', 'ebcp', 'multilingual', 'translation', 'deepl', 'pdf', 'medical']
    ],

    // Utilities Module (8)
    'kiosk-jahrestagung' => [
        'title' => 'Kiosk Jahrestagung',
        'description' => 'Kiosk-Modus für Jahrestagungen mit Vollbild und Auto-Reload.',
        'content' => '# Kiosk Jahrestagung

## Übersicht

Kiosk-Modus für Jahrestagungen und Events mit Vollbild, Auto-Reload und Inaktivitäts-Detection.

## Hauptfunktionen

- Vollbild-Modus
- Auto-Reload nach Inaktivität
- Touch-Optimierung
- Inaktivitäts-Detection
- Custom Reload-Intervall
- Zurück zur Startseite

## Shortcodes

### [kiosk_mode]
Aktiviert Kiosk-Modus für Seite.

**Parameter:**
- `interval` - Auto-Reload Intervall in Sekunden (Standard: 300)

**Verwendung:**
```
[kiosk_mode]
[kiosk_mode interval="600"]
```

## Verwendung

### Kiosk-Seite erstellen

1. Neue Seite erstellen
2. Inhalt hinzufügen (z.B. Programm, Sponsoren)
3. Shortcode einfügen: `[kiosk_mode]`
4. Veröffentlichen

### Features

**Vollbild:**
- Automatisch beim Laden
- F11 oder ESC zum Beenden
- Browser-Vollbild-API

**Auto-Reload:**
- Nach Inaktivität (Standard: 5 Minuten)
- Lädt Seite neu
- Aktualisiert Inhalte

**Touch-Optimierung:**
- Touch-Events werden erkannt
- Große Buttons
- Swipe-Gesten

**Inaktivitäts-Detection:**
- Keine Maus-Bewegung
- Keine Touch-Events
- Keine Tastatur-Eingaben
- → Reload nach Intervall

## Konfiguration

**Im Shortcode:**
```
[kiosk_mode interval="600"]
```
→ Reload nach 10 Minuten Inaktivität

**Standard:**
```
[kiosk_mode]
```
→ Reload nach 5 Minuten

## Anwendungsfall

**Jahrestagung:**
- Kiosk-Terminal im Foyer
- Zeigt Programm, Pläne, Sponsoren
- Auto-Reload für aktuelle Infos
- Vollbild für professionelle Darstellung

**Messe:**
- Info-Terminal
- Produktkatalog
- Interaktive Karte

## Technische Details

- JavaScript für Vollbild-API
- setTimeout für Auto-Reload
- Event-Listener für Activity-Detection
- Touch-Events für Mobile

## Tipps

- Testen Sie Intervall im Vorfeld
- Nutzen Sie große, gut lesbare Schrift
- Touch-freundliche Navigation
- Vollbild-Modus in Browser-Einstellungen erlauben',
        'features' => [
            'Vollbild-Modus',
            'Auto-Reload',
            'Inaktivitäts-Detection',
            'Touch-Optimierung'
        ],
        'keywords' => ['kiosk', 'fullscreen', 'auto-reload', 'event', 'conference', 'touch']
    ],

    'exif-data' => [
        'title' => 'EXIF Data',
        'description' => 'EXIF-Metadaten-Management für Bilder.',
        'content' => '# EXIF Data

## Übersicht

EXIF-Metadaten-Management für WordPress-Medien mit Anzeige und Bearbeitungsfunktionen.

## Hauptfunktionen

- EXIF-Daten auslesen
- Metadaten anzeigen
- GPS-Koordinaten extrahieren
- Kamera-Informationen
- Aufnahme-Parameter
- Copyright-Management

## Shortcodes

### [exif_data]
Zeigt EXIF-Daten eines Bildes.

**Parameter:**
- `attachment_id` - WordPress Attachment-ID
- `field` - Spezifisches EXIF-Feld (optional)

**Verwendung:**
```
[exif_data attachment_id="123"]
[exif_data attachment_id="123" field="Camera"]
```

## Admin-Interface

**Menü:** Medien → EXIF-Daten

### Funktionen

1. **EXIF-Anzeige**
   - Alle EXIF-Daten eines Bildes
   - Übersichtlich formatiert
   - Gruppiert nach Kategorien

2. **Metadaten-Editor**
   - Copyright bearbeiten
   - Beschreibung anpassen
   - GPS-Daten entfernen (Datenschutz)

## Verfügbare EXIF-Felder

**Kamera:**
- Hersteller (Make)
- Modell (Model)
- Seriennummer

**Aufnahme-Parameter:**
- Blende (FNumber)
- Belichtungszeit (ExposureTime)
- ISO (ISOSpeedRatings)
- Brennweite (FocalLength)
- Blitz (Flash)

**Datum/Zeit:**
- Aufnahmedatum (DateTimeOriginal)
- Digitalisierung (DateTimeDigitized)

**GPS:**
- Latitude (Breitengrad)
- Longitude (Längengrad)
- Altitude (Höhe)

**Copyright:**
- Copyright
- Artist (Fotograf)

**Software:**
- Software (z.B. Adobe Lightroom)

## Verwendung

### EXIF-Daten anzeigen

**Auf Seite/Post:**
```
[exif_data attachment_id="123"]
```

**Spezifisches Feld:**
```
Aufgenommen mit: [exif_data attachment_id="123" field="Camera"]
```

### GPS-Daten entfernen

1. Medien → EXIF-Daten
2. Bild auswählen
3. "GPS-Daten entfernen" klicken
4. Bestätigen

**Wichtig:** Aus Datenschutz-Gründen empfohlen!

### Copyright setzen

1. Medien → EXIF-Daten
2. Bild auswählen
3. Copyright-Feld ausfüllen
4. Speichern

## Datenschutz

**GPS-Daten:**
- Können Aufnahme-Ort verraten
- Datenschutz-Problem bei Privatfotos
- Tool zum Entfernen nutzen

**DSGVO:**
- EXIF-Daten können personenbezogen sein
- Vor Upload prüfen und ggf. entfernen
- WordPress entfernt EXIF standardmäßig NICHT

## Technische Details

- PHP EXIF-Extension erforderlich
- Liest EXIF aus JPEG, TIFF
- Unterstützt IPTC-Daten
- GPS-Koordinaten-Konvertierung

## Tipps

- GPS-Daten vor Upload entfernen
- Copyright immer setzen
- EXIF für Fotografie-Portfolios nutzen
- Prüfen Sie EXIF-Support in PHP',
        'features' => [
            'EXIF-Daten auslesen',
            'Metadaten anzeigen',
            'GPS-Koordinaten',
            'Kamera-Informationen',
            'Copyright-Management'
        ],
        'keywords' => ['exif', 'metadata', 'images', 'gps', 'photography', 'copyright']
    ],

    'blaue-seiten' => [
        'title' => 'Blaue Seiten',
        'description' => 'Verzeichnis-Funktionalität (Blaue Seiten / Yellow Pages).',
        'content' => '# Blaue Seiten

## Übersicht

Verzeichnis-System analog zu "Gelben Seiten" für Mitglieder, Firmen oder Organisationen.

## Hauptfunktionen

- Verzeichnis-Anzeige
- Filterung nach Kategorie
- Suche
- Kategoriesystem
- Alphabetische Sortierung
- Detail-Ansichten

## Shortcodes

### [custom_posts_page]
Zeigt Custom-Post-Seite.

**Parameter:**
- `post_type` - Custom Post Type
- `category` - Kategorie-Filter
- `orderby` - Sortierung

**Verwendung:**
```
[custom_posts_page post_type="verzeichnis"]
```

### [list_blaue_seiten]
Zeigt Blaue-Seiten-Liste.

**Verwendung:**
```
[list_blaue_seiten]
```

## Verwendung

### Verzeichnis anzeigen

```
[list_blaue_seiten]
```

**Features:**
- Alphabetische Tabs (A-Z)
- Kategorien-Filter
- Suchfeld
- Detail-Links

### Verzeichnis-Eintrag erstellen

1. Backend: Blaue Seiten → Neu
2. Firmenname, Adresse, Kontakt
3. Kategorie zuweisen
4. Beschreibung
5. Logo/Bild hochladen
6. Veröffentlichen

### Kategorien

Beispiel-Kategorien:
- Kliniken
- Niedergelassene Ärzte
- Apotheken
- Sanitätshäuser
- Labore

## Darstellung

**Liste:**
- Firmenname (groß)
- Kategorie (Badge)
- Kurzinfo
- "Mehr Info" Button

**Detail-Ansicht:**
- Vollständige Informationen
- Kontaktdaten
- Karte (wenn Adresse)
- Logo
- Beschreibung

## Technische Details

- Custom Post Type: `blaue_seiten` oder `verzeichnis`
- Taxonomie: `verzeichnis_kategorie`
- Responsive CSS-Grid
- Ajax für Filter

## Tipps

- Aussagekräftige Kategorien
- Vollständige Kontaktdaten
- Logo für Wiedererkennungswert
- Regelmäßig aktualisieren',
        'features' => [
            'Verzeichnis-Anzeige',
            'Filterung',
            'Suche',
            'Kategorien',
            'Alphabetische Sortierung'
        ],
        'keywords' => ['directory', 'yellow-pages', 'listing', 'verzeichnis', 'companies']
    ],

    'shortcode-tools' => [
        'title' => 'Shortcode Tools',
        'description' => 'Shortcode-Editoren und Grid-Layouts.',
        'content' => '# Shortcode Tools

## Übersicht

Sammlung von Shortcode-Tools für Grid-Layouts, Editoren und Content-Strukturierung.

## Hauptfunktionen

- Shortcode-Generator
- Grid-Layout-System
- News-Editor
- Responsive Layouts
- Flexible Column-Systeme

## Shortcodes

### [news-edit-form]
News-Editor-Formular.

**Verwendung:**
```
[news-edit-form]
```

### [grid_layout]
Grid-Layout für Content.

**Parameter:**
- `columns` - Anzahl Spalten (2, 3, 4)

**Verwendung:**
```
[grid_layout columns="3"]
  <div>Inhalt Spalte 1</div>
  <div>Inhalt Spalte 2</div>
  <div>Inhalt Spalte 3</div>
[/grid_layout]
```

## Verwendung

### Grid-Layout

**2 Spalten:**
```
[grid_layout columns="2"]
  <div>Linke Spalte</div>
  <div>Rechte Spalte</div>
[/grid_layout]
```

**3 Spalten:**
```
[grid_layout columns="3"]
  <div>Spalte 1</div>
  <div>Spalte 2</div>
  <div>Spalte 3</div>
[/grid_layout]
```

**4 Spalten:**
```
[grid_layout columns="4"]
  <div>Box 1</div>
  <div>Box 2</div>
  <div>Box 3</div>
  <div>Box 4</div>
[/grid_layout]
```

### Responsive Verhalten

- **Desktop:** Volle Spaltenanzahl
- **Tablet:** 2 Spalten
- **Mobile:** 1 Spalte (gestapelt)

## Kombinationen

**Mit anderen Shortcodes:**
```
[grid_layout columns="2"]
  <div>[vimeo_stream id="123"]</div>
  <div>[news-list limit="5"]</div>
[/grid_layout]
```

## Technische Details

- CSS Grid für Layout
- Flexbox-Fallback
- Responsive Breakpoints
- No JavaScript required

## Tipps

- Nutzen Sie Grid für konsistente Layouts
- Kombinieren Sie mit anderen Shortcodes
- Testen Sie Responsive-Verhalten
- Verwenden Sie div-Container für jeden Grid-Item',
        'features' => [
            'Shortcode-Generator',
            'Grid-Layout-System',
            'News-Editor',
            'Responsive Layouts'
        ],
        'keywords' => ['shortcode', 'grid', 'layout', 'editor', 'columns', 'responsive']
    ],

    'stellenanzeige' => [
        'title' => 'Stellenanzeige',
        'description' => 'Stellenanzeigen-Verwaltungssystem mit Frontend-Editor.',
        'content' => '# Stellenanzeige

## Übersicht

Umfassendes Stellenanzeigen-System mit Frontend-Editor, Berechtigungssystem und ACF-Integration.

## Hauptfunktionen

- Custom Post Type "Stellenanzeige"
- Frontend-Editor
- Berechtigungssystem (ACF: stellenanzeigen_anlegen)
- Kategorien (Fachbereich)
- Filterung
- Bewerbungs-Formular

## Shortcodes

### [stellenanzeigen]
Stellenanzeigen-Liste.

**Parameter:**
- `category` - Kategorie-Filter
- `limit` - Anzahl
- `orderby` - Sortierung

**Verwendung:**
```
[stellenanzeigen limit="10"]
[stellenanzeigen category="kardiologie"]
```

### [stellenanzeigen_editor]
Frontend-Editor für Stellenanzeigen.

**Verwendung:**
```
[stellenanzeigen_editor]
```

### [stellenanzeige_bearbeiten]
Einzelne Stellenanzeige bearbeiten.

**Parameter:**
- `id` - Stellenanzeigen-ID

**Verwendung:**
```
[stellenanzeige_bearbeiten id="123"]
```

## Admin-Interface

**Menü:** Stellenanzeigen (Custom Post Type)

### Funktionen

1. **Stellenanzeigen-Verwaltung**
   - Alle Stellenanzeigen
   - Filtern nach Kategorie
   - Status (aktiv/abgelaufen)

2. **Kategorien**
   - Fachbereiche verwalten
   - Z.B. Kardiologie, Chirurgie, Innere Medizin

## Berechtigungssystem

### ACF-Feld

**stellenanzeigen_anlegen:**
- Wert: true/false
- Berechtigung: Stellenanzeigen erstellen und eigene bearbeiten

### Zugriff

**Mit Berechtigung:**
- Frontend-Editor zugänglich
- Kann Stellenanzeigen erstellen
- Kann eigene bearbeiten/löschen

**Ohne Berechtigung:**
- Nur Anzeigen sehen
- Kein Editor-Zugriff

## Verwendung

### Stellenanzeigen anzeigen

```
[stellenanzeigen limit="10"]
```

### Stellenanzeige erstellen (Frontend)

1. Benutzer mit `stellenanzeigen_anlegen` = true
2. Seite mit `[stellenanzeigen_editor]` aufrufen
3. Formular ausfüllen:
   - Stellentitel
   - Fachbereich
   - Beschreibung
   - Anforderungen
   - Kontakt
   - Bewerbungsfrist
4. Veröffentlichen

### Stellenanzeige bearbeiten

1. Eigene Stellenanzeigen-Liste öffnen
2. "Bearbeiten" klicken
3. Änderungen vornehmen
4. Speichern

## Stellenanzeigen-Felder

**Grunddaten:**
- Stellentitel
- Fachbereich
- Standort
- Arbeitszeit (Vollzeit/Teilzeit)

**Beschreibung:**
- Aufgabenbeschreibung
- Anforderungsprofil
- Was wir bieten

**Bewerbung:**
- Kontaktperson
- E-Mail
- Telefon
- Bewerbungsfrist

## Kategorien

Beispiele:
- Kardiologie
- Chirurgie
- Innere Medizin
- Pädiatrie
- Verwaltung
- Pflege

## Technische Details

- Custom Post Type: `stellenanzeige`
- Taxonomie: `stellenanzeigen_kategorie`
- ACF für Felder und Berechtigungen
- Frontend-Editor mit Ajax
- Responsive Design

## Tipps

- Klare Stellentitel
- Vollständige Beschreibungen
- Bewerbungsfristen setzen
- Abgelaufene Anzeigen archivieren',
        'features' => [
            'Custom Post Type Stellenanzeige',
            'Frontend-Editor',
            'ACF-Berechtigungen',
            'Kategorien',
            'Filterung'
        ],
        'keywords' => ['jobs', 'stellenanzeigen', 'career', 'frontend-editor', 'acf', 'categories']
    ],

    'conditional-logic' => [
        'title' => 'Conditional Logic',
        'description' => 'Bedingte Content-Anzeige mit flexibler Logik.',
        'content' => '# Conditional Logic

## Übersicht

Flexibles System für bedingte Content-Anzeige basierend auf User-Meta, Rollen, Custom-Fields und mehr.

## Hauptfunktionen

- Bedingte Shortcodes
- Operatoren: =, !=, >, <, >=, <=, contains
- Verschachtelbar
- User-Meta-Zugriff
- Custom Field Support
- Rollen-Abfrage

## Shortcodes

### [ifdgptm]
Bedingter Shortcode.

**Parameter:**
- `condition` - Zu prüfende Bedingung
- `value` - Erwarteter Wert
- `operator` - Vergleichsoperator (Standard: =)

**Verwendung:**
```
[ifdgptm condition="user_role" value="subscriber"]
  Nur für Abonnenten sichtbar
[/ifdgptm]
```

## Operatoren

**Vergleich:**
- `=` - Gleich
- `!=` - Ungleich
- `>` - Größer als
- `<` - Kleiner als
- `>=` - Größer oder gleich
- `<=` - Kleiner oder gleich

**Spezial:**
- `contains` - Enthält (für Strings)
- `not_contains` - Enthält nicht

## Verwendung

### Rollenbasierte Anzeige

```
[ifdgptm condition="user_role" value="administrator"]
  Admin-Inhalt
[/ifdgptm]
```

### User-Meta prüfen

```
[ifdgptm condition="user_meta:newsletter" value="true"]
  Newsletter-Abonnenten-Content
[/ifdgptm]
```

### Custom-Field prüfen

```
[ifdgptm condition="meta:premium_member" value="true"]
  Premium-Content
[/ifdgptm]
```

### Numerische Vergleiche

```
[ifdgptm condition="user_meta:login_count" value="10" operator=">"]
  Für erfahrene Benutzer (mehr als 10 Logins)
[/ifdgptm]
```

### String-Vergleiche

```
[ifdgptm condition="user_meta:country" value="Germany" operator="contains"]
  Content für Deutschland
[/ifdgptm]
```

### Verschachtelte Bedingungen

```
[ifdgptm condition="user_role" value="subscriber"]
  [ifdgptm condition="user_meta:verified" value="true"]
    Nur für verifizierte Abonnenten
  [/ifdgptm]
[/ifdgptm]
```

## Verfügbare Conditions

**user_role:**
- Prüft WordPress-Benutzerrolle
- Werte: administrator, editor, author, contributor, subscriber

**user_meta:FELDNAME:**
- Prüft beliebiges User-Meta-Feld
- Z.B. `user_meta:newsletter`

**meta:FELDNAME:**
- Prüft Custom-Field des aktuellen Posts
- Z.B. `meta:featured`

**is_logged_in:**
- Prüft ob Benutzer eingeloggt
- Werte: true, false

## Technische Details

- Shortcode-basiert
- Unterstützt Verschachtelung
- PHP-Evaluierung
- Sichere Operatoren-Verarbeitung

## Tipps

- Kombinieren Sie mit anderen Shortcodes
- Testen Sie als unterschiedliche Benutzer
- Nutzen Sie für Premium-Content
- Verschachteln für komplexe Logik',
        'features' => [
            'Bedingte Shortcodes',
            'Operatoren (=, !=, >, <, contains)',
            'Verschachtelbar',
            'User-Meta-Zugriff',
            'Custom Field Support'
        ],
        'keywords' => ['conditional', 'logic', 'shortcode', 'if', 'visibility', 'user-meta']
    ],

    'installer' => [
        'title' => 'Installer',
        'description' => 'Plugin-Installations-Helfer für Batch-Installation.',
        'content' => '# Installer

## Übersicht

Plugin-Installations-Helfer für Batch-Installation von Plugins mit Abhängigkeits-Prüfung.

## Hauptfunktionen

- Plugin-Batch-Installation
- Abhängigkeits-Prüfung
- ZIP-Upload
- Automatische Aktivierung
- Installations-Log
- Fehlerbehandlung

## Admin-Interface

**Menü:** Tools → Plugin Installer

### Funktionen

1. **Plugin-Upload**
   - ZIP-Dateien hochladen
   - Mehrere Plugins gleichzeitig
   - Drag & Drop

2. **Batch-Installation**
   - Alle Plugins installieren
   - Automatisch aktivieren
   - Fortschrittsanzeige

3. **Abhängigkeits-Checker**
   - Prüft erforderliche Plugins
   - Zeigt fehlende Abhängigkeiten
   - Installiert Abhängigkeiten zuerst

## Verwendung

### Einzelnes Plugin installieren

1. Tools → Plugin Installer
2. ZIP-Datei auswählen
3. "Hochladen & Installieren"
4. Optional: "Aktivieren"

### Batch-Installation

1. Mehrere ZIP-Dateien auswählen
2. "Alle hochladen"
3. "Batch-Installation starten"
4. Warten auf Abschluss
5. Installations-Log prüfen

### Abhängigkeiten prüfen

1. Plugin auswählen
2. "Abhängigkeiten prüfen"
3. Liste der erforderlichen Plugins
4. Fehlende automatisch installieren

## Abhängigkeits-System

**Format (in plugin.php):**
```php
/**
 * Requires Plugins: advanced-custom-fields, contact-form-7
 */
```

**Prüfung:**
- Liest Plugin-Header
- Prüft ob erforderliche Plugins installiert
- Installiert fehlende Plugins
- Aktiviert in korrekter Reihenfolge

## Installations-Log

**Zeigt:**
- Erfolgreich installierte Plugins
- Fehler bei Installation
- Aktivierungs-Status
- Abhängigkeits-Installationen

**Export:**
- Log als TXT herunterladen
- Für Dokumentation
- Fehleranalyse

## Technische Details

- WordPress Plugin-API
- ZIP-Extraktion
- Abhängigkeits-Graph
- Topologische Sortierung für Installationsreihenfolge
- Fehler-Rollback

## Tipps

- Prüfen Sie ZIP-Dateien vor Upload
- Nutzen Sie Abhängigkeits-System
- Log für Fehleranalyse
- Backup vor Batch-Installation',
        'features' => [
            'Batch-Installation',
            'Abhängigkeits-Prüfung',
            'ZIP-Upload',
            'Automatische Aktivierung',
            'Installations-Log'
        ],
        'keywords' => ['installer', 'plugins', 'batch', 'dependencies', 'upload', 'automation']
    ],

    'zoho-role-manager' => [
        'title' => 'Zoho Role Manager',
        'description' => 'Erweiterte Rollenverwaltung basierend auf Zoho CRM Daten.',
        'content' => '# Zoho Role Manager

## Übersicht

Erweiterte WordPress-Rollenverwaltung basierend auf Zoho CRM Daten mit automatischer Synchronisation.

## Hauptfunktionen

- Rollensynchronisation mit Zoho CRM
- Automatische Zuordnung basierend auf CRM-Feldern
- Manueller Sync-Trigger
- Logging von Rollenänderungen
- Session-basierte Kontrolle
- Cron-basierte Auto-Synchronisation

## Shortcodes

### [sync_mitglied_rolle]
Manuelle Rollensynchronisation für eingeloggten Benutzer.

**Verwendung:**
```
[sync_mitglied_rolle]
```

Zeigt Button "Rolle synchronisieren".

## Admin-Interface

Nutzt CRM-Abruf Admin-Seite (Einstellungen → DGPTM Zoho).

### Einstellungen

1. **Zoho CRM Verbindung**
   - Automatisch von CRM-Abruf-Modul

2. **Rollen-Mapping**
   - CRM-Feld → WordPress-Rolle
   - Z.B. `aktives_mitglied` = true → Rolle "Mitglied"

3. **Synchronisations-Einstellungen**
   - Auto-Sync aktivieren
   - Sync-Intervall (täglich, wöchentlich)
   - Manueller Sync

## Verwendung

### Automatische Synchronisation

**Trigger:**
- Bei jedem Login
- Täglich via Cron
- Nach CRM-Update (Webhook)

**Ablauf:**
1. Benutzer loggt sich ein
2. E-Mail wird in Zoho gesucht
3. CRM-Felder werden gelesen
4. WordPress-Rolle wird aktualisiert
5. Logging in DB-Tabelle `wp_dgptm_role_changes`

### Manuelle Synchronisation

**Frontend:**
1. Benutzer klickt Button: `[sync_mitglied_rolle]`
2. Ajax-Request an Server
3. Synchronisation wird durchgeführt
4. Bestätigung

**Admin:**
1. Einstellungen → DGPTM Zoho
2. "Alle Rollen synchronisieren" klicken
3. Alle Benutzer werden synchronisiert

### Rollen-Mapping

**Beispiel:**
```php
// In Zoho CRM:
// aktives_mitglied = true → WordPress Rolle: "Mitglied"
// aktives_mitglied = false → WordPress Rolle: "Subscriber"

// In WordPress:
if ($zoho_data[\'aktives_mitglied\'] == true) {
    $user->set_role(\'mitglied\');
} else {
    $user->set_role(\'subscriber\');
}
```

## Logging

**Tabelle:** `wp_dgptm_role_changes`

**Felder:**
- user_id
- old_role
- new_role
- changed_at
- reason (auto_login, manual, cron, webhook)

**Ansicht:**
- Einstellungen → DGPTM Zoho → Rollenwechsel-Log
- Zeigt alle Änderungen
- Filterbar nach Benutzer, Datum

## Cron-Job

**Standardmäßig:**
- Täglich um 3:00 Uhr
- Synchronisiert alle Benutzer
- Nur Änderungen werden geloggt

**Anpassen:**
```php
// In wp-config.php oder Theme:
add_filter(\'dgptm_role_sync_schedule\', function() {
    return \'hourly\'; // hourly, daily, weekly
});
```

## Technische Details

- Abhängigkeit: CRM-Abruf-Modul
- Nutzt Zoho API via CRM-Abruf
- Custom DB-Table für Logging
- WordPress Cron API
- Session-Control für Rate-Limiting

## Tipps

- Testen Sie Rollen-Mapping zuerst
- Prüfen Sie Log nach Synchronisation
- Nutzen Sie manuelle Sync für wichtige Änderungen
- Backup vor Massen-Synchronisation',
        'features' => [
            'Zoho-Rollensynchronisation',
            'Automatische Zuordnung',
            'Manueller Sync-Trigger',
            'Logging',
            'Session-Control',
            'Cron-basiert'
        ],
        'keywords' => ['zoho', 'roles', 'sync', 'crm', 'automation', 'users']
    ],

]);

// Generiere alle Guides
$generated = 0;
$updated = 0;
$failed = 0;

foreach ($all_module_guides as $module_id => $guide_data) {
    $guide_file = $guides_dir . $module_id . '.json';
    $exists = file_exists($guide_file);

    // Meta-Daten hinzufügen
    $guide_data['module_id'] = $module_id;

    $current_time = $is_wordpress && function_exists('current_time')
        ? current_time('mysql')
        : date('Y-m-d H:i:s');

    if ($exists) {
        $guide_data['last_updated'] = $current_time;
    } else {
        $guide_data['generated_at'] = $current_time;
        $guide_data['last_updated'] = $current_time;
    }

    $result = file_put_contents($guide_file, json_encode($guide_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    if ($result !== false) {
        if ($exists) {
            echo "↻ Aktualisiert: $module_id\n";
            $updated++;
        } else {
            echo "✓ Erstellt: $module_id\n";
            $generated++;
        }
    } else {
        echo "✗ Fehler: $module_id\n";
        $failed++;
    }
}

echo "\n======================================\n";
echo "Import-Zusammenfassung:\n";
echo "- Neu erstellt: $generated\n";
echo "- Aktualisiert: $updated\n";
echo "- Fehler: $failed\n";
echo "- Gesamt: " . ($generated + $updated) . " / 33 Module\n\n";

$remaining = 33 - ($generated + $updated);
if ($remaining > 0) {
    echo "HINWEIS: $remaining Module benötigen noch Anleitungen.\n";
    echo "Diese können über das Admin-Interface ergänzt werden.\n\n";
}

echo "Anleitungen verfügbar unter: DGPTM Suite → Module Guides\n";
