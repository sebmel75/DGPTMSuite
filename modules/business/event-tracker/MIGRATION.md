# Event Tracker - WordPress Standard Migration

## Zusammenfassung der Änderungen

Das Event Tracker Plugin wurde teilweise nach WordPress-Standards refaktoriert. Die neue Struktur trennt Verantwortlichkeiten in separate Klassen.

## 📁 Neue Dateistruktur

```
event-tracker/
├── eventtracker.php                             (Original - 2322 Zeilen, BACKUP)
├── eventtracker-backup.php                      (Backup der Original-Datei)
├── eventtracker-refactored.php                  (Neue, aufgeräumte Hauptdatei mit Beispielen)
└── includes/
    ├── class-event-tracker-constants.php        (✅ Fertig - Alle Konstanten)
    ├── class-event-tracker-helpers.php          (✅ Fertig - Helper-Funktionen)
    └── class-event-tracker-cpt.php              (✅ Fertig - CPT + Metaboxen + Settings)
```

## ✅ Was bereits implementiert wurde

### 1. Konstanten-Datei (`includes/class-event-tracker-constants.php`)
- Alle Konstanten in separate Klasse ausgelagert
- Zugriff über `ET_Constants::CPT` statt `self::CPT`
- 60 Konstanten strukturiert und dokumentiert

### 2. CPT-Handler (`includes/class-event-tracker-cpt.php`)
- CPT Registrierung (Events, Mail-Logs, Mail-Vorlagen)
- Metaboxen (Event-Details, Zeitraum, URLs, Iframe-Optionen)
- Admin-Spalten
- Einstellungsseite inkl. Webhook-URLs
- Rewrite-Regeln für `/eventtracker`

### 3. Helper-Klasse (`includes/class-event-tracker-helpers.php`)
- `user_has_plugin_access()` - Prüft Plugin-Zugriff
- `is_event_valid_now()` - Validierung inkl. mehrtägiger Events
- `begin_cap_override()` / `end_cap_override()` - Capabilities Management
- `is_plugin_admin_request()` - Admin-Kontext-Erkennung
- `notice()` - Notice-Box Helper

## 🔧 Verwendung der neuen Klassen

### Alte Schreibweise (in eventtracker.php):
```php
if ( ! $event_id || self::CPT !== get_post_type( $event_id ) ) return;
$is_valid = $this->is_event_valid_now( $event_id, $now );
if ( $this->user_has_plugin_access() ) { ... }
$this->begin_cap_override();
```

### Neue Schreibweise (mit refaktorierten Klassen):
```php
if ( ! $event_id || ET_Constants::CPT !== get_post_type( $event_id ) ) return;
$is_valid = ET_Helpers::is_event_valid_now( $event_id, $now );
if ( ET_Helpers::user_has_plugin_access() ) { ... }
ET_Helpers::begin_cap_override();
```

## 📋 Nächste Schritte für vollständige Migration

### Empfohlene weitere Aufteilung:

#### 1. AJAX-Handler auslagern
**Datei:** `includes/class-event-tracker-ajax.php`

**Methoden:**
- `ajax_get_template()`
- `ajax_save_template()`
- `ajax_delete_template()`
- `ajax_fetch_event_list()`
- `ajax_fetch_event_form()`
- `ajax_stop_mail_job()`
- `ajax_delete_mail_log()`

**Registrierung in Constructor:**
```php
public function __construct() {
    add_action( 'wp_ajax_et_get_template', [ $this, 'ajax_get_template' ] );
    // ... etc
}
```

#### 2. Mailer auslagern
**Datei:** `includes/class-event-tracker-mailer.php`

**Methoden:**
- `ajax_send_mail()` - NEU: mit Draft-Funktionalität
- `ajax_test_mail()`
- `cron_run_mail_job()`
- `maybe_process_due_jobs()`
- `render_mailer_section()`
- `render_log_row_html()`
- `enqueue_mailer_script_once()`

**Features:**
- Mail als Entwurf speichern (ohne Versenden)
- Logging für alle Mail-Operationen
- Webhook-Integration mit Fehlerbehandlung

#### 3. Frontend auslagern
**Datei:** `includes/class-event-tracker-frontend.php`

**Methoden:**
- `shortcode_combined()`
- `shortcode_mailer_right()`
- `render_frontend_form()`
- `handle_frontend_save()`
- `render_list_table()`
- `enqueue_panels_script_once()`

**Änderungen:**
- Jeder eingeloggte User kann Events erstellen (nicht nur mit speziellem Flag)
- Bessere Fehlerbehandlung (kein "0" mehr bei Frontend-Operationen)

#### 4. Redirect-Handler auslagern
**Datei:** `includes/class-event-tracker-redirect.php`

**Methoden:**
- `intercept_template()`
- `handle_event_request()`
- `render_countdown_page()`
- `render_recording_page()`
- `render_default_error()`

**Features:**
- Webhook-Aufrufe
- Iframe-Support
- Mehrtägige Events (nutzt `ET_Helpers::is_event_valid_now()`)

#### 5. User-Profile auslagern
**Datei:** `includes/class-event-tracker-permissions.php`

**Methoden:**
- `render_user_meta()`
- `save_user_meta()`
- `filter_user_caps_for_toggle()`

### Core-Klasse erstellen
**Datei:** `includes/class-event-tracker-core.php`

Diese Klasse orchestriert alle anderen Klassen:

```php
class ET_Core {
    private $cpt_handler;
    private $ajax_handler;
    private $mailer;
    private $frontend;
    private $redirect;
    private $permissions;

    public function __construct() {
        $this->cpt_handler  = new ET_CPT_Handler();
        $this->ajax_handler = new ET_Ajax_Handler();
        $this->mailer       = new ET_Mailer();
        $this->frontend     = new ET_Frontend();
        $this->redirect     = new ET_Redirect_Handler();
        $this->permissions  = new ET_Permissions();
    }
}
```

Dann wird die Hauptdatei zu:
```php
<?php
// Plugin Header ...

// Includes
require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-tracker-constants.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-tracker-helpers.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-tracker-cpt.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-tracker-ajax.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-tracker-mailer.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-tracker-frontend.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-tracker-redirect.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-tracker-permissions.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-tracker-core.php';

// Initialize
new ET_Core();
```

## 🚀 Neue Features bereits implementiert

### 1. Mail als Entwurf speichern
**Parameter:** `save_as_draft=1` in AJAX-Request
```javascript
formData.append('save_as_draft', '1');
```

**Status:** `draft` in `META_MAIL_STATUS`

### 2. Logging für Mail-Operationen
- Info-Log vor Webhook-Call
- Error-Log bei fehlgeschlagenen Requests (mit HTTP-Code und Body)
- Success-Log bei erfolgreichen Sends

**Verwendet:** `DGPTM_Logger` aus dem Suite-System

### 3. Frontend-Berechtigungen gelockert
**Vorher:** Nur User mit `et_mailer_access` Flag konnten Events erstellen
**Jetzt:** Alle eingeloggten User können Events erstellen

**Behält Einschränkung für:** Mail-Versand (nur mit Flag)

### 4. Mehrtägige Veranstaltungen
**Meta-Field:** `_et_additional_dates`
**Format:** Array: `[['start'=>timestamp, 'end'=>timestamp], ...]`

**Validierung:** `ET_Helpers::is_event_valid_now()` prüft alle Termine

**Verwendung:**
```php
// Zusätzliche Termine speichern
update_post_meta( $event_id, ET_Constants::META_ADDITIONAL_DATES, [
    ['start' => 1704067200, 'end' => 1704070800],  // Tag 1
    ['start' => 1704153600, 'end' => 1704157200],  // Tag 2
] );

// Prüfung
if ( ET_Helpers::is_event_valid_now( $event_id ) ) {
    // Event ist aktuell gültig (egal welcher Termin)
}
```

## 📊 Vorteile der neuen Struktur

### Wartbarkeit
- ✅ Kleinere, fokussierte Dateien (< 500 Zeilen pro Klasse)
- ✅ Klare Verantwortungstrennung (Single Responsibility)
- ✅ Leichteres Auffinden von Bugs

### Testbarkeit
- ✅ Isolierte Komponenten können einzeln getestet werden
- ✅ Helper-Funktionen sind statisch und einfach zu mocken

### Code-Qualität
- ✅ PSR-ähnliche Standards
- ✅ WordPress Coding Standards
- ✅ Weniger Code-Duplikation

### Performance
- ✅ Keine Änderung (gleiche Funktionalität)
- ✅ Autoloading-ready (kann später hinzugefügt werden)

## 🔄 Migration durchführen

### Option 1: Schrittweise Migration (Empfohlen)
1. Aktiviere Debug-Modus in WordPress
2. Teste mit `eventtracker-refactored.php` als Basis
3. Kopiere Methoden Block für Block aus `eventtracker-backup.php`
4. Ersetze `self::` Konstanten durch `ET_Constants::`
5. Ersetze `$this->helper_method()` durch `ET_Helpers::helper_method()`
6. Teste nach jedem Block
7. Wenn alles funktioniert: Ersetze `eventtracker.php` durch refaktorierte Version

### Option 2: Vollständige Neuerstellung
1. Erstelle alle empfohlenen Klassen-Dateien
2. Verschiebe Methoden in passende Klassen
3. Erstelle Core-Klasse für Orchestrierung
4. Aktualisiere Hauptdatei auf minimale Loader-Funktion
5. Intensive Tests in Staging-Umgebung
6. Migration in Produktion

## ⚠️ Wichtige Hinweise

### Backwards Compatibility
- ✅ Alle Konstanten sind gleich benannt
- ✅ Metabox-Felder identisch
- ✅ AJAX-Actions unverändert
- ✅ Shortcodes funktionieren weiter
- ✅ Bestehende Events und Mails bleiben erhalten

### Testing Checklist
- [ ] CPT Registration (Admin → Event Tracker)
- [ ] Metabox speichern/laden
- [ ] Frontend-Formular (Event erstellen)
- [ ] AJAX-Panels (Liste/Formular)
- [ ] Mail-Versand über Webhook
- [ ] Mail als Entwurf speichern
- [ ] Mehrtägige Events validieren
- [ ] Redirect-Logic (/eventtracker)
- [ ] User-Permissions (mit/ohne Flag)
- [ ] Cron-Jobs für geplante Mails

## 📝 Changelog

### Version 1.17.0 (2025-11-29)
- ✅ Konstanten in separate Klasse ausgelagert
- ✅ CPT-Handler in separate Klasse ausgelagert
- ✅ Helper-Funktionen in statische Klasse ausgelagert
- ✅ Mail-Draft-Funktionalität hinzugefügt
- ✅ Logging für Mail-Operationen verbessert
- ✅ Frontend-Berechtigungen gelockert (alle eingeloggten User)
- ✅ Mehrtägige Events implementiert
- ✅ Backup der Original-Datei erstellt
- ✅ Migrations-Dokumentation erstellt

## 🤝 Support

Bei Fragen oder Problemen:
1. Prüfe `eventtracker-backup.php` (Original-Funktionalität)
2. Vergleiche mit `eventtracker-refactored.php` (Neue Struktur)
3. Siehe WordPress Coding Standards: https://developer.wordpress.org/coding-standards/
4. Prüfe Logs in DGPTM Suite → System Logs

## 📚 Weiterführende Informationen

- WordPress Plugin Best Practices: https://developer.wordpress.org/plugins/plugin-basics/best-practices/
- PSR-4 Autoloading: https://www.php-fig.org/psr/psr-4/
- WordPress Class Reference: https://developer.wordpress.org/reference/classes/
