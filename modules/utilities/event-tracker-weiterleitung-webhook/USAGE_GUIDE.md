# Event Tracker 2.0 - Nutzungsanleitung

## Inhaltsverzeichnis

1. [Überblick](#überblick)
2. [Event-Verwaltung](#event-verwaltung)
3. [Mail-System](#mail-system)
4. [Mehrtägige Events](#mehrtägige-events)
5. [User-Berechtigungen](#user-berechtigungen)
6. [Technische Details](#technische-details)

---

## Überblick

Event Tracker 2.0 ist ein vollständig überarbeitetes Plugin zur Verwaltung von Online-Events (Webinare, Live-Streams) mit integriertem Mail-System.

### Hauptfunktionen

- ✅ Event-Erstellung und -Verwaltung über Frontend-Shortcode
- ✅ Zeitbasierte Weiterleitung zu Live-Streams
- ✅ Automatische Anzeige von Aufzeichnungen nach Event-Ende
- ✅ Mail-Versand mit Scheduling (sofort, geplant, wiederkehrend)
- ✅ Template-System für wiederkehrende Mails
- ✅ Mehrtägige Events mit gleichem Zugangs-Link
- ✅ Webhook-Integration für Zugriffs-Tracking und Mail-Versand
- ✅ User-Permissions für Nicht-Administratoren

---

## Event-Verwaltung

### Shortcode einbinden

Fügen Sie den Shortcode auf einer beliebigen WordPress-Seite ein:

```
[event_tracker]
```

Dies zeigt drei Tabs:
1. **Events** - Liste aller Events mit Status
2. **Event erstellen/bearbeiten** - Formular für Event-Verwaltung
3. **Mailer** - Mail-System für Event-Benachrichtigungen

### Event erstellen

1. Wechseln Sie zum Tab "Event erstellen/bearbeiten"
2. Füllen Sie die Pflichtfelder aus:
   - **Event-Name** - Titel der Veranstaltung
   - **Start** - Datum und Uhrzeit (HTML5 datetime-local)
   - **Ende** - Datum und Uhrzeit
   - **Iframe-URL** - Link zum Live-Stream (YouTube, Zoom, Vimeo etc.)

3. Optional:
   - **Aufzeichnung URL** - Link zur Aufzeichnung (wird nach Event-Ende angezeigt)
   - **Zoho Contact ID** - ID für CRM-Integration
   - **Mehrtägige Events** - Zusätzliche Termine (siehe unten)

4. Klicken Sie "Event speichern"

### Event-Status in der Liste

Die Event-Liste zeigt:
- **🟢 Aktiv** - Event läuft gerade (innerhalb Start-Ende)
- **🔵 Bevorstehend** - Event startet in Zukunft
- **⚫ Beendet** - Event ist vorbei
- **🔴 Keine URL** - Kein Stream-Link hinterlegt

**Aktionen:**
- ✏️ **Bearbeiten** - Event-Daten ändern
- 🗑️ **Löschen** - Event entfernen
- 📋 **Link kopieren** - Event-URL in Zwischenablage

### Event-URL-Format

Jedes Event erhält eine eindeutige URL:

```
https://ihre-domain.de/eventtracker?id=123
```

**Funktionsweise:**
1. User öffnet Event-URL
2. System prüft:
   - Existiert Event?
   - Ist Event aktiv (Zeit-Check)?
   - Ist Iframe-URL vorhanden?
3. Bei Erfolg:
   - Webhook-Trigger (falls konfiguriert)
   - Desktop: Iframe-Seite mit Stream
   - Mobile: "In neuem Fenster öffnen"-Link
4. Nach Event-Ende:
   - Falls Aufzeichnung vorhanden: Aufzeichnungs-Seite
   - Sonst: Fehlerseite "Event beendet"

---

## Mail-System

### Übersicht

Das Mail-System erlaubt:
- HTML-Mails mit TinyMCE-Editor
- Template-Verwaltung (Entwürfe)
- Verschiedene Versand-Zeitpunkte
- Test-Mails vor echtem Versand
- Mail-Log mit Status-Tracking

### Webhook-basierter Versand

**WICHTIG:** Mails werden nicht direkt versendet, sondern über Webhook an externes System übergeben.

Das Webhook erhält JSON-Daten:
```json
{
  "event_id": 123,
  "zoho_id": "456789",
  "subject": "Dein Event startet bald",
  "html": "<p>Hallo,<br>dein Event...</p>",
  "timestamp": 1700000000
}
```

**Konfiguration:** (TODO - Settings-Page)
```php
$settings = get_option('et_settings', []);
$settings['mail_webhook_url'] = 'https://api.example.com/send-mail';
update_option('et_settings', $settings);
```

### Mail erstellen und versenden

#### 1. Template erstellen (optional)

1. Wechseln Sie zum Tab "Mailer"
2. Betreff und HTML-Inhalt eingeben
3. "Template speichern" klicken
4. Template-Namen vergeben (z.B. "Event-Erinnerung")
5. Template ist nun in Dropdown verfügbar

**Platzhalter:**
- `{{URL}}` - Wird ersetzt durch `/eventtracker?id=123`
- `{{NAME}}` - Wird ersetzt durch Event-Titel

#### 2. Mail senden

1. **Event wählen** - Dropdown zeigt nur zukünftige/aktuelle Events
2. **Template laden** (optional) - Wählen Sie Template aus Dropdown
3. **Betreff eingeben**
4. **HTML-Inhalt** - Nutzen Sie TinyMCE-Editor:
   - Fett, Kursiv, Listen
   - Links einfügen
   - Bilder hochladen
   - HTML-Modus

5. **Versand-Zeitpunkt wählen:**

   **Option A: Sofort**
   - Mail wird direkt über Webhook versendet
   - Status: `sent`

   **Option B: Zu Veranstaltungsbeginn**
   - Mail wird automatisch bei Event-Start versendet
   - WordPress Cron erstellt scheduled job
   - Status: `queued`

   **Option C: Am... (geplanter Versand)**
   - Datum/Uhrzeit auswählen
   - Cron versendet Mail zum gewählten Zeitpunkt
   - Status: `queued`

   **Option D: Intervall bis Start**
   - Wiederkehrende Mails bis Event-Beginn
   - **Intervall wählen:**
     - Täglich (24h)
     - Wöchentlich (7 Tage)
     - 3 Tage
     - 1 Stunde
   - **Startpunkt:**
     - Sofort
     - Morgen (00:00 Uhr)
     - Nächste Woche
   - Status: `recurring`
   - Mail wird automatisch bei Event-Start gestoppt

6. Klicken Sie "Mail senden" oder "Test-Mail senden"

### Test-Mail senden

1. Füllen Sie Mail-Formular aus
2. Geben Sie Test-E-Mail-Adresse ein
3. Klicken Sie "Test-Mail senden"

**Hinweis:** Test-Mails:
- Ersetzen Platzhalter
- Werden über Webhook versendet
- Erstellen KEIN Log-Eintrag
- Sind nicht im Cron geplant

### Mail-Log

Die Tabelle zeigt alle Mails mit:

| Spalte | Beschreibung |
|--------|--------------|
| Event | Event-Name (Link) |
| Betreff | Mail-Subject |
| Zeitplan | Versandzeitpunkt oder "Wiederkehrend" |
| Status | sent/queued/recurring/error/stopped |
| Datum | Erstellungsdatum |
| Aktionen | Löschen/Stoppen |

**Status-Badges:**
- 🟢 **sent** - Erfolgreich versendet
- 🔵 **queued** - Wartet auf Versand (geplant)
- 🟡 **recurring** - Wiederkehrend aktiv
- 🔴 **error** - Fehler beim Versand
- ⚫ **stopped** - Manuell gestoppt

**Aktionen:**
- **Löschen** (🗑️) - Nur für: sent, error, stopped
- **Stoppen** (⏸️) - Nur für: recurring

### Cron-System

Das Plugin nutzt WordPress Cron für geplante Mails:

**Hooks:**
- `et_mail_send_job` - Einzelner Mail-Versand
- `et_recurring_mail_job` - Wiederkehrende Mails

**Fallback-System:**
Falls Cron ausfällt, prüft Plugin bei jedem Seitenaufruf auf überfällige Mails und versendet diese.

**Cron prüfen:**
```php
// Alle geplanten Events anzeigen
$cron = _get_cron_array();
foreach ($cron as $timestamp => $hooks) {
    if (isset($hooks['et_mail_send_job'])) {
        error_log("Mail scheduled for: " . date('Y-m-d H:i:s', $timestamp));
    }
}
```

---

## Mehrtägige Events

### Use Case

Events, die an mehreren Tagen stattfinden, aber den gleichen Zugangs-Link nutzen sollen:

**Beispiel:**
- Workshop-Reihe: Montag, Mittwoch, Freitag
- Gleicher Zoom-Link für alle drei Termine
- Ein Event mit drei Zeiträumen

### Einrichtung

1. Event-Formular öffnen
2. Haupt-Termin eingeben (Start/Ende)
3. Zu Abschnitt "Mehrtägige Events" scrollen
4. Klicken Sie "+ Weiteren Termin hinzufügen"
5. Start/Ende für zweiten Termin eingeben
6. Weitere Termine nach Bedarf hinzufügen
7. Termine mit "×" Button entfernen

### Technische Funktionsweise

**Speicherung:**
```php
// Haupt-Termin: post_meta
update_post_meta($event_id, 'et_event_start', 1700000000);
update_post_meta($event_id, 'et_event_end', 1700003600);

// Zusätzliche Termine: serialized array
$additional = [
    ['start' => 1700086400, 'end' => 1700090000],
    ['start' => 1700172800, 'end' => 1700176400],
];
update_post_meta($event_id, 'et_additional_dates', $additional);
```

**Validierung:**
```php
// Helpers::is_event_valid() prüft ALLE Zeiträume
public static function is_event_valid($event_id, $now = 0) {
    $now = $now ?: time();

    // Check main time range
    $start = get_post_meta($event_id, 'et_event_start', true);
    $end = get_post_meta($event_id, 'et_event_end', true);
    if ($now >= $start && $now <= $end) {
        return true;
    }

    // Check additional dates
    $additional = get_post_meta($event_id, 'et_additional_dates', true);
    if (is_array($additional)) {
        foreach ($additional as $range) {
            if ($now >= $range['start'] && $now <= $range['end']) {
                return true;
            }
        }
    }

    return false;
}
```

**Event-Liste:**
- Status zeigt "Aktiv" wenn EINER der Zeiträume gültig ist
- Anzeige aller Termine in Event-Details

---

## User-Berechtigungen

### Standard-Verhalten

- **Administratoren** - Voller Zugriff auf Events
- **Andere Rollen** - Kein Zugriff auf Event-Verwaltung

### Zugriff für Nicht-Admins aktivieren

1. WordPress Admin → Benutzer → Profil öffnen
2. Scrollen zu "Event Tracker Berechtigung"
3. Checkbox aktivieren: "Benutzer kann Events erstellen und verwalten"
4. Profil speichern

**Technische Details:**

**User Meta:**
```php
// Zugriff aktivieren
update_user_meta($user_id, 'et_mailer_access', '1');

// Zugriff prüfen
$has_access = get_user_meta($user_id, 'et_mailer_access', true) === '1';
```

**Capability-Filter:**
```php
// Plugin::filter_user_caps()
// Grants capabilities:
add_filter('user_has_cap', function($allcaps, $caps, $args, $user) {
    if (get_user_meta($user->ID, 'et_mailer_access', true) === '1') {
        $allcaps['edit_et_events'] = true;
        $allcaps['publish_et_events'] = true;
        // ... weitere event-spezifische capabilities
    }
    return $allcaps;
}, 10, 4);
```

**Kontext-Abhängigkeit:**

Capabilities werden nur vergeben wenn:
- User ist im Admin-Bereich, ODER
- User macht Plugin-AJAX-Request, ODER
- Cap-Override ist aktiv

Dies verhindert unerwünschte Rechte-Elevation im Frontend.

---

## Technische Details

### Dateistruktur

```
event-tracker/
├── event-tracker.php           # Entry point
├── src/
│   ├── Autoloader.php          # PSR-4 Autoloader
│   ├── Core/
│   │   ├── Plugin.php          # Main plugin class
│   │   ├── Constants.php       # CPT names, meta keys
│   │   └── Helpers.php         # Utility functions
│   ├── Admin/
│   │   ├── CPT.php             # Post type registration
│   │   └── Settings.php        # Settings page (TODO)
│   ├── Frontend/
│   │   ├── Shortcodes.php      # [event_tracker] shortcode
│   │   └── RedirectHandler.php # /eventtracker URL routing
│   ├── Ajax/
│   │   └── Handler.php         # All AJAX endpoints
│   └── Mailer/
│       └── MailerCore.php      # Mail sending logic
├── assets/
│   ├── css/
│   │   └── frontend.css        # Shortcode styles
│   └── js/
│       └── frontend.js         # jQuery handlers
├── QUICKSTART.md               # Quick start guide
├── USAGE_GUIDE.md              # This file
└── module.json                 # DGPTM module config
```

### Custom Post Types

**et_event** - Events
- Meta fields: start, end, iframe_url, recording_url, zoho_id, additional_dates

**et_mail** - Mail-Log
- Meta fields: event_id, subject, html, schedule, schedule_at, status

**et_mail_tpl** - Mail-Templates
- Post content: HTML content
- Post title: Template name

### AJAX Endpoints

**Events:**
- `et_get_events` - Load event list
- `et_get_event_form` - Load event form (create/edit)
- `et_save_event` - Save event
- `et_delete_event` - Delete event

**Mails:**
- `et_send_mail` - Send/schedule mail
- `et_test_mail` - Send test mail
- `et_delete_mail_log` - Delete mail log entry
- `et_stop_mail_job` - Stop recurring mail
- `et_get_template` - Load template content
- `et_save_template` - Save new template
- `et_delete_template` - Delete template

Alle Endpoints:
- Prüfen Nonce: `et_ajax_nonce`
- Prüfen Capabilities (außer get_events)
- Nutzen `wp_send_json_success()` / `wp_send_json_error()`

### WordPress Hooks

**Lifecycle:**
```php
register_activation_hook(__FILE__, ['EventTracker\Core\Plugin', 'activate']);
register_deactivation_hook(__FILE__, ['EventTracker\Core\Plugin', 'deactivate']);
```

**Init:**
```php
add_action('plugins_loaded', [$plugin, 'on_plugins_loaded']);
add_action('init', [$plugin, 'on_init']);
```

**Capabilities:**
```php
add_filter('user_has_cap', [$plugin, 'filter_user_caps'], 10, 4);
```

**Template Routing:**
```php
add_filter('template_include', [$redirect_handler, 'intercept_template']);
```

**Cron:**
```php
add_action('et_mail_send_job', [$mailer, 'execute_mail_send']);
add_action('et_recurring_mail_job', [$mailer, 'execute_recurring_mail']);
```

### Konstanten

Alle in `EventTracker\Core\Constants`:

```php
const CPT = 'et_event';
const CPT_MAIL = 'et_mail';
const CPT_MAIL_TPL = 'et_mail_tpl';

const META_START = 'et_event_start';
const META_END = 'et_event_end';
const META_IFRAME = 'et_iframe_url';
const META_RECORDING = 'et_recording_url';
const META_ZOHO = 'et_zoho_id';
const META_ADDITIONAL_DATES = 'et_additional_dates';

const USER_META_ACCESS = 'et_mailer_access';

const OPT_KEY = 'et_settings';

// Mail statuses
const MAIL_STATUS_SENT = 'sent';
const MAIL_STATUS_QUEUED = 'queued';
const MAIL_STATUS_RECURRING = 'recurring';
const MAIL_STATUS_ERROR = 'error';
const MAIL_STATUS_STOPPED = 'stopped';
```

### Debugging

**Logs aktivieren:**
```php
// In wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

**Plugin-Logs:**
```php
// Helpers::log($message, $level)
use EventTracker\Core\Helpers;
Helpers::log('Test message', 'info');
```

**Cron prüfen:**
```bash
# WordPress Admin → Tools → Cron Events
# Oder wp-cli:
wp cron event list --format=table
```

**Mail-Webhook testen:**
```bash
curl -X POST https://ihre-webhook-url.com/endpoint \
  -H "Content-Type: application/json" \
  -d '{
    "event_id": 123,
    "zoho_id": "456",
    "subject": "Test",
    "html": "<p>Test</p>",
    "timestamp": 1700000000
  }'
```

---

## Troubleshooting

### Mails werden nicht versendet

**Prüfen:**
1. Ist Webhook-URL konfiguriert?
   ```php
   $settings = get_option('et_settings');
   var_dump($settings['mail_webhook_url']);
   ```

2. Läuft WordPress Cron?
   ```bash
   wp cron event list
   ```

3. Gibt es Fehler im Log?
   ```bash
   tail -f wp-content/debug.log | grep "Event Tracker"
   ```

4. Webhook erreichbar?
   ```bash
   curl -I https://ihre-webhook-url.com
   ```

### Event-URL zeigt Fehler

**"Event nicht gefunden":**
- Post-ID existiert nicht
- Post-Type ist nicht `et_event`
- Post ist im Papierkorb

**"Event ist nicht aktiv":**
- Aktuelle Zeit außerhalb Start-Ende
- Zusätzliche Termine auch nicht gültig
- Zeitzone-Probleme (Server vs. Browser)

**"Keine URL hinterlegt":**
- `et_iframe_url` ist leer
- Event-Ende überschritten UND keine Recording-URL

### Shortcode zeigt nichts

1. Plugin aktiviert?
2. Assets geladen? (Browser-Konsole prüfen)
3. JavaScript-Fehler? (Browser-Konsole)
4. AJAX-Endpoint erreichbar?
   ```bash
   curl https://ihre-domain.de/wp-admin/admin-ajax.php \
     -d "action=et_get_events&nonce=XXX"
   ```

---

## Nächste Schritte

### TODO: Settings-Page

Noch zu implementieren:

```php
// Settings-Felder:
- mail_webhook_url (string)
- event_webhook_url (string)
- default_mail_template (int - post_id)
- cron_interval (string - hourly/twicedaily/daily)
- delete_old_logs (bool)
- log_retention_days (int)
```

### Mögliche Erweiterungen

- 📊 Analytics (Event-Zugriffe, Mail-Öffnungsraten)
- 📧 Mail-Personalisierung (Merge-Tags aus CRM)
- 🔔 Push-Benachrichtigungen
- 📱 Mobile App Integration
- 🎨 Custom Email-Templates (Drag & Drop)
- 📅 iCal Export
- 🔗 Zoom API Integration (automatische Meeting-Erstellung)

---

**Viel Erfolg mit Event Tracker 2.0! 🎉**
