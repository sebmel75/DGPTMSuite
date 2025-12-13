# Event Tracker 2.0 - Quick Start Guide

## 🚀 Sofort loslegen

### 1. Plugin aktivieren

Das Plugin ist bereits **einsatzbereit** und kann sofort aktiviert werden!

```bash
DGPTM Suite Dashboard → Event Tracker → Aktivieren
```

### 2. Was funktioniert bereits?

#### ✅ Voll funktionsfähig (Stand: v2.0.0):
- **CPT Registration** - Event-Post-Types sind registriert
- **Admin-Menu** - "Event Tracker" erscheint im WordPress-Admin
- **Rewrite Rules** - `/eventtracker` URL funktioniert
- **Autoloading** - PSR-4 Autoloader lädt alle Klassen
- **Logging** - Integration mit DGPTM Logger
- **Frontend-Shortcodes** - `[event_tracker]` zeigt Event-Liste und Formulare
- **AJAX-Handler** - Event-Liste, Event-Formulare, Event-Speicherung
- **Redirect-Logic** - Zeitbasierte Weiterleitung zu Events
- **Iframe-Support** - Live-Streams im Iframe (Desktop) / neues Fenster (Mobile)
- **Aufzeichnungs-Links** - Automatische Anzeige nach Event-Ende
- **User-Permissions** - Toggle für Event-Verwaltung im Benutzerprofil
- **Webhook-Integration** - Automatischer Webhook-Trigger bei Event-Zugriff
- **Capability-Filter** - Dynamische Rechtevergabe für autorisierte Benutzer
- **Mail-System** - Versand, Entwürfe, Cron, Test-Mails
- **Mail-Vorlagen** - Template-Verwaltung mit TinyMCE-Editor
- **Mehrtägige Events** - UI für zusätzliche Termine (gleiches Event, mehrere Zeiträume)
- **Mail-Scheduling** - Sofort, geplant, zu Event-Beginn, wiederkehrend
- **Mail-Log** - Übersicht über gesendete/geplante/gestoppte Mails

#### 🔄 Noch nicht implementiert:
- Settings-Page (Konfiguration für Webhook-URL etc.)

### 3. Erste Schritte

#### Admin-Bereich öffnen:
```
WordPress Admin → Event Tracker → Alle Veranstaltungen
```

#### Event erstellen:
```
WordPress Admin → Event Tracker → Neu hinzufügen
```

**Hinweis:** Metaboxen für Event-Details werden in zukünftigen Updates hinzugefügt.

### 4. Entwickler-Zugriff

#### Plugin-Instance abrufen:
```php
$plugin = event_tracker_init();
```

#### Komponenten verwenden:
```php
use EventTracker\Core\Constants;
use EventTracker\Core\Helpers;

// Konstanten
$cpt = Constants::CPT; // 'et_event'

// Helpers
if ( Helpers::user_has_access() ) {
    // User hat Zugriff
}
```

## 🎯 Roadmap

### Phase 1: Kern-Funktionalität (✅ Fertig)
- [x] Plugin-Architektur
- [x] Autoloader
- [x] Constants & Helpers
- [x] CPT Registration
- [x] Komponenten-System

### Phase 2: Admin-Features (✅ Fertig)
- [x] CPT & Metaboxen
- [x] Rewrite Rules
- [x] Query Vars
- [x] User-Permissions (Toggle im Profil)
- [x] Capability-Filter

### Phase 3: Frontend (✅ Fertig)
- [x] Shortcodes (`[event_tracker]`)
- [x] Formulare (Erstellen/Bearbeiten)
- [x] Event-Listen (Tabelle mit Status)
- [x] Redirect-Handler (zeitbasiert)
- [x] Iframe-Support
- [x] Aufzeichnungs-Links

### Phase 4: Mail-System (✅ Fertig)
- [x] Mail-Templates (Template-Verwaltung mit Save/Load/Delete)
- [x] Cron-Jobs (Scheduled & Recurring Mails)
- [x] Entwürfe (Template-System für Mail-Speicherung)
- [x] Mail-Versand (Webhook-basiert mit Test-Mail-Funktion)
- [x] Mail-Log (Tracking von gesendeten/geplanten Mails)
- [x] TinyMCE-Integration (Visual Editor für HTML-Mails)

### Phase 5: Erweiterte Features (🔄 In Arbeit)
- [x] Mehrtägige Events (UI für zusätzliche Termine)
- [ ] Settings-Page (Webhook-URL Konfiguration)
- [ ] Analytics
- [ ] Export/Import

## 📖 Nützliche Befehle

### Debugging
```php
// Komponente prüfen
$plugin = event_tracker_init();
var_dump( $plugin->get_component( 'cpt' ) );

// Logging
use EventTracker\Core\Helpers;
Helpers::log( 'Test-Nachricht', 'info' );
```

### CPT prüfen
```php
// Ist CPT registriert?
$cpts = get_post_types( [], 'objects' );
var_dump( isset( $cpts['et_event'] ) ); // sollte true sein
```

### Settings prüfen
```php
// Settings-Option abrufen
$settings = get_option( \EventTracker\Core\Constants::OPT_KEY );
var_dump( $settings );
```

## 🔧 Migration von v1.x

### Option A: Neue Version nutzen (Empfohlen)
Die neue Version ist bereits aktiv, da `module.json` auf `event-tracker.php` zeigt.

### Option B: Zurück zur alten Version
Falls du zur alten Version zurück möchtest:

1. Öffne `module.json`
2. Ändere:
   ```json
   "main_file": "eventtracker.php"
   ```
3. Deaktiviere/Reaktiviere das Modul in der DGPTM Suite

## 🐛 Problemlösung

### Plugin lädt nicht
```bash
# Prüfe PHP-Version
php -v  # Sollte >= 7.4 sein

# Prüfe Logs
DGPTM Suite → System Logs → Event Tracker filtern
```

### Autoloader-Fehler
```bash
# Prüfe ob Dateien existieren
ls src/Autoloader.php
ls src/Core/Plugin.php
```

### CPT erscheint nicht
```bash
# Flush Rewrite Rules
WordPress Admin → Einstellungen → Permalinks → Speichern
```

## 📞 Support

- **README**: Vollständige Dokumentation
- **MIGRATION.md**: Migrations-Details
- **Logs**: DGPTM Suite → System Logs

## ✨ Was ist neu in 2.0?

### Architektur
- ✅ PSR-4 Autoloading statt require_once
- ✅ Namespaces statt globale Klassen
- ✅ Komponenten statt Monolith
- ✅ Single Responsibility Principle

### Code-Qualität
- ✅ Type Hints
- ✅ DocBlocks
- ✅ WordPress Coding Standards
- ✅ Testbare Struktur

### Performance
- ✅ Lazy Loading
- ✅ Minimal Bootstrap
- ✅ Optimierte Hooks

### Wartbarkeit
- ✅ Kleine Dateien (< 200 Zeilen)
- ✅ Klare Struktur
- ✅ Einfaches Erweitern

## 📧 Mail-System nutzen

### Erste Schritte:

1. **Shortcode einfügen:**
   ```
   [event_tracker]
   ```
   Dies zeigt Event-Liste, Event-Formulare UND Mail-System.

2. **Event erstellen:**
   - Event-Name, Start, Ende, URLs eingeben
   - Optional: Mehrtägige Events mit "+ Weiteren Termin hinzufügen"

3. **Mail-Template erstellen:**
   - Im Mail-Bereich: Betreff und HTML-Inhalt eingeben
   - "Template speichern" klicken und Namen vergeben
   - Template wird als `et_mail_tpl` CPT gespeichert

4. **Mail versenden:**
   - Event auswählen (zeigt nur zukünftige/aktuelle Events)
   - Optional: Template laden
   - Betreff und Inhalt bearbeiten (TinyMCE-Editor)
   - Versand-Zeitpunkt wählen:
     - **Sofort** - Direkt über Webhook versenden
     - **Zu Veranstaltungsbeginn** - Automatisch bei Event-Start
     - **Am...** - Zu bestimmtem Datum/Uhrzeit
     - **Intervall bis Start** - Wiederkehrend (täglich/wöchentlich) bis Event-Beginn
   - "Mail senden" klicken

5. **Test-Mail:**
   - E-Mail-Adresse im Feld "Test-Mail an" eingeben
   - "Test-Mail senden" klicken
   - Ersetzt Platzhalter: `{{URL}}` und `{{NAME}}`

### Mail-Log:

Die Tabelle zeigt alle Mails mit:
- **Status-Badges:**
  - 🟢 `sent` - Erfolgreich versendet
  - 🔵 `queued` - Geplant (wartet auf Versand)
  - 🟡 `recurring` - Wiederkehrend aktiv
  - 🔴 `error` - Fehler beim Versand
  - ⚫ `stopped` - Manuell gestoppt

- **Aktionen:**
  - 🗑️ Löschen (nur für sent/error/stopped)
  - ⏸️ Stoppen (nur für recurring)

### Platzhalter in Mails:

- `{{URL}}` - Wird ersetzt durch Event-URL (`/eventtracker?id=123`)
- `{{NAME}}` - Wird ersetzt durch Event-Titel

### Mehrtägige Events:

Events können an mehreren Tagen mit gleichem Link stattfinden:

1. Im Event-Formular zum Abschnitt "Mehrtägige Events" scrollen
2. "+ Weiteren Termin hinzufügen" klicken
3. Start/Ende für zusätzlichen Termin eingeben
4. Weitere Termine nach Bedarf hinzufügen
5. Mit "×" können Termine entfernt werden

**Funktionsweise:**
- Gleicher Link (`/eventtracker?id=123`) funktioniert für alle Termine
- Zeitbasierte Validierung prüft Haupt-Termin UND zusätzliche Termine
- Event ist aktiv, wenn EINER der Zeiträume gültig ist

### Webhook-Konfiguration:

**WICHTIG:** Für Mail-Versand muss Webhook-URL konfiguriert werden.

Aktuell wird sie aus Settings gelesen:
```php
$settings = get_option( \EventTracker\Core\Constants::OPT_KEY );
$webhook_url = $settings['mail_webhook_url'] ?? '';
```

**TODO:** Settings-Page erstellen für:
- Mail-Webhook-URL
- Event-Webhook-URL
- Standard-Mail-Template
- Cron-Intervall

---

**Los geht's! 🎉**

Aktiviere das Plugin und beginne mit der Entwicklung!
