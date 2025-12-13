# Event Tracker 2.0 - Strukturübersicht

## 📁 Dateistruktur

```
event-tracker/
│
├── 📄 event-tracker.php              ← NEUE Hauptdatei (Bootstrap)
├── 📄 eventtracker.php               ← Alte Version (Referenz)
├── 📄 eventtracker-backup.php        ← Backup
├── 📄 module.json                    ← DGPTM Suite Config
│
├── 📚 Dokumentation
│   ├── README.md                     ← Hauptdokumentation
│   ├── QUICKSTART.md                 ← Schnelleinstieg
│   ├── STRUCTURE.md                  ← Diese Datei
│   ├── MIGRATION.md                  ← Migrations-Anleitung
│   └── README-REFACTORING.md         ← Refactoring-Details
│
├── 📦 includes/ (ALT)
│   ├── class-event-tracker-constants.php
│   ├── class-event-tracker-helpers.php
│   └── class-event-tracker-cpt.php
│
└── 🎯 src/ (NEU - PSR-4)
    ├── Autoloader.php                ← PSR-4 Autoloader
    │
    ├── Core/                         ← Kern-Funktionalität
    │   ├── Plugin.php                → Hauptklasse, orchestriert alles
    │   ├── Constants.php             → Alle Konstanten (CPT, Meta, Status)
    │   └── Helpers.php               → Utility-Funktionen
    │
    ├── Admin/                        ← WordPress Admin
    │   ├── CPT.php                   → CPT Registration & Rewrite Rules
    │   ├── Settings.php              → Settings Page
    │   ├── Metaboxes.php             → (Zukünftig) Event-Metaboxen
    │   └── Permissions.php           → (Zukünftig) User Capabilities
    │
    ├── Ajax/                         ← AJAX Endpoints
    │   ├── Handler.php               → Haupt-AJAX-Handler
    │   ├── Events.php                → (Zukünftig) Event AJAX
    │   └── Mails.php                 → (Zukünftig) Mail AJAX
    │
    ├── Frontend/                     ← Public-Facing
    │   ├── Shortcodes.php            → Shortcode-Handler
    │   ├── RedirectHandler.php       → /eventtracker Routing
    │   ├── Forms.php                 → (Zukünftig) Frontend-Formulare
    │   └── Lists.php                 → (Zukünftig) Event-Listen
    │
    └── Mailer/                       ← Mail-System
        ├── MailerCore.php            → Mail-Logik & Cron
        ├── Templates.php             → (Zukünftig) Mail-Templates
        └── Webhook.php               → (Zukünftig) Webhook-Integration
```

## 🧩 Komponenten-Übersicht

### 1. Core (Kern)

#### `Autoloader.php`
```php
\EventTracker\Autoloader::register( $base_dir );
```
- PSR-4 kompatibles Autoloading
- Automatisches Laden aller Klassen im `EventTracker\` Namespace

#### `Core\Plugin.php`
```php
$plugin = \EventTracker\Core\Plugin::instance();
$component = $plugin->get_component( 'cpt' );
```
- Singleton Pattern
- Lädt alle Komponenten
- Orchestriert Plugin-Lifecycle
- Bereitstellt Helper-Methoden

#### `Core\Constants.php`
```php
use EventTracker\Core\Constants;

Constants::CPT                    // 'et_event'
Constants::META_START_TS          // '_et_start_ts'
Constants::STATUS_DRAFT           // 'draft'
```
- 50+ Konstanten
- Typsicher durch Klassen-Konstanten
- Zentrale Referenz

#### `Core\Helpers.php`
```php
use EventTracker\Core\Helpers;

Helpers::is_event_valid( $id )    // Event-Validierung
Helpers::user_has_access()        // Permission-Check
Helpers::log( $msg, 'error' )     // Logging
```
- Statische Utility-Funktionen
- Wiederverwendbar
- Testbar

### 2. Admin (WordPress Admin)

#### `Admin\CPT.php`
- Registriert 3 CPTs:
  - `et_event` - Veranstaltungen
  - `et_mail` - Mail-Logs
  - `et_mail_tpl` - Mail-Vorlagen
- Rewrite Rules für `/eventtracker`
- Query Vars Registration

#### `Admin\Settings.php`
- Settings Page unter Einstellungen → Event Tracker
- Settings API Integration
- Optionen-Management

### 3. Ajax (AJAX Endpoints)

#### `Ajax\Handler.php`
- Registriert AJAX-Actions
- Nonce-Verifikation
- Permission-Checks
- JSON-Responses

### 4. Frontend (Öffentlich)

#### `Frontend\Shortcodes.php`
- `[event_tracker]` - Haupt-Shortcode
- `[event_mailer]` - Mail-Interface
- `[event_mailer_right]` - Permission-Check

#### `Frontend\RedirectHandler.php`
- Intercepted `/eventtracker` URLs
- Event-Validierung
- Webhook-Calls
- Iframe-Rendering

### 5. Mailer (Mail-System)

#### `Mailer\MailerCore.php`
- Cron-Job Handler
- Mail-Queue
- Webhook-Integration
- Draft-System

## 🔄 Datenfluss

### Event-Erstellung (Admin)
```
User Input
    ↓
Admin\CPT::save_metabox()
    ↓
update_post_meta()
    ↓
Event in DB gespeichert
```

### Event-Zugriff (Frontend)
```
User besucht /eventtracker?et_event=123
    ↓
Frontend\RedirectHandler::intercept_template()
    ↓
Helpers::is_event_valid( 123 )
    ↓
[VALID] → Webhook-Call → Redirect zu Event-URL
[INVALID] → Error-Seite mit Countdown
```

### Mail-Versand
```
AJAX-Call: et_send_mail
    ↓
Ajax\Handler::send_mail()
    ↓
[DRAFT] → Save ohne Send
[NOW] → Webhook-Call
[SCHEDULED] → Cron-Job erstellen
    ↓
Mailer\MailerCore::run_mail_job()
    ↓
Webhook-Call → Log erstellen
```

## 🎨 Design Patterns

### 1. Singleton
```php
class Plugin {
    private static $instance = null;

    public static function instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

### 2. Component Pattern
```php
class Plugin {
    private $components = [];

    private function load_components() {
        $this->components['cpt'] = new CPT();
        $this->components['ajax'] = new AjaxHandler();
    }
}
```

### 3. Static Utilities
```php
class Helpers {
    public static function is_event_valid( $id ) {
        // Stateless utility function
    }
}
```

### 4. Dependency Injection
```php
class MailerCore {
    public function __construct( AjaxHandler $ajax ) {
        $this->ajax = $ajax;
    }
}
```

## 📊 Klassendiagramm

```
Plugin (Singleton)
    │
    ├─── CPT
    │     └─── register_cpt()
    │     └─── register_mail_cpts()
    │
    ├─── Settings
    │     └─── render_settings_page()
    │
    ├─── AjaxHandler
    │     └─── fetch_event_list()
    │     └─── send_mail()
    │
    ├─── Shortcodes
    │     └─── event_tracker_shortcode()
    │
    ├─── RedirectHandler
    │     └─── intercept_template()
    │
    └─── MailerCore
          └─── run_mail_job()

Helpers (Static Utilities)
    ├─── is_event_valid()
    ├─── user_has_access()
    ├─── log()
    └─── begin_cap_override()

Constants (Static Data)
    ├─── CPT = 'et_event'
    ├─── META_START_TS = '_et_start_ts'
    └─── STATUS_DRAFT = 'draft'
```

## 🔌 Hooks & Filters

### Actions
```php
// Plugin Lifecycle
add_action( 'plugins_loaded', 'event_tracker_init' );
add_action( 'event_tracker_init', ... );

// CPT Registration
add_action( 'init', [ CPT, 'register_cpt' ] );

// AJAX
add_action( 'wp_ajax_et_send_mail', [ Handler, 'send_mail' ] );

// Cron
add_action( 'et_run_mail_job', [ MailerCore, 'run_mail_job' ] );
```

### Filters
```php
// Template Interception
add_filter( 'template_include', [ RedirectHandler, 'intercept_template' ] );

// Query Vars
add_filter( 'query_vars', [ CPT, 'register_query_vars' ] );
```

## 🎯 Namespaces

```
EventTracker\
    ├── Core\
    │   ├── Plugin
    │   ├── Constants
    │   └── Helpers
    ├── Admin\
    │   ├── CPT
    │   └── Settings
    ├── Ajax\
    │   └── Handler
    ├── Frontend\
    │   ├── Shortcodes
    │   └── RedirectHandler
    └── Mailer\
        └── MailerCore
```

## 📦 Dependencies

### WordPress
- Requires: 5.8+
- Post Types
- Rewrite Rules
- Cron API
- AJAX
- Settings API

### PHP
- Requires: 7.4+
- Namespaces
- Type Hints
- DateTimeImmutable
- SPL Autoloader

### DGPTM Suite
- Optional: DGPTM_Logger
- Integration: Module System

## 🚀 Erweiterbarkeit

### Eigene Komponente hinzufügen
1. Erstelle `src/Custom/MyComponent.php`
2. Registriere in `Plugin::load_components()`
3. Nutze via `$plugin->get_component( 'my_component' )`

### Eigene Hooks nutzen
```php
do_action( 'event_tracker_before_send_mail', $mail_id );
apply_filters( 'event_tracker_event_valid', $is_valid, $event_id );
```

### Eigene Constants
```php
// In Constants.php ergänzen
const MY_CUSTOM_META = '_et_my_custom';
```

---

**Version:** 2.0.0
**Architektur:** PSR-4, Component-Based, Namespace-Organized
**Status:** Production Ready (Core), Erweitert in Entwicklung
