# DGPTM Plugin Suite

**Version:** 3.0.0
**Author:** Sebastian Melzer / DGPTM
**License:** GPL v2 or later
**Repository:** https://github.com/sebmel75/DGPTMSuite

## Übersicht

DGPTM Plugin Suite ist ein umfassendes WordPress Plugin-Management-System, das **50+ individuelle Module** in einer einheitlichen Administrationsoberfläche konsolidiert. Es bietet zentralisierte Kontrolle über alle DGPTM-Plugins mit Features wie individuelle Aktivierung/Deaktivierung, Abhängigkeitsmanagement, Update-Verwaltung und Standalone-Plugin-Export.

## Features

- **Zentrales Dashboard** - Alle Module von einer Oberfläche verwalten
- **Abhängigkeitsmanagement** - Automatische Abhängigkeitsprüfung und -auflösung
- **Modul-Metadaten** - Flags, Kommentare und Versionsverwaltung pro Modul
- **Modul-Export** - Jedes Modul als eigenständiges WordPress-Plugin exportieren (ZIP)
- **Update-Management** - Zentrales Update-System für alle Module
- **Kategorieorganisation** - Module nach Funktion gruppiert
- **System-Logging** - Ausführliche Logs mit automatischer Rotation
- **Suche & Filter** - Module schnell finden nach Name, Kategorie oder Status

---

## Enthaltene Module (50+)

### Core Infrastructure (7 Module)

| Modul | Beschreibung |
|-------|--------------|
| **crm-abruf** | Zoho CRM Integration mit OAuth2, API Endpoints und Webhook-Unterstützung |
| **rest-api-extension** | Benutzerdefinierte REST API Endpoints für DGPTM |
| **webhook-trigger** | Webhook-Management und Trigger-System |
| **menu-control** | Erweiterte Menü-Sichtbarkeit und -Kontrolle |
| **side-restrict** | Seiten- und Inhaltszugangsbeschränkungen mit dynamischen ACF-Berechtigungen |
| **otp-login** | Sicheres OTP-basiertes Login mit Rate Limiting und Multisite-Unterstützung |
| **role-manager** | Backend-Zugriffskontrolle, Multiple Rollen und Toolbar-Management |

### Business & Events (6 Module)

| Modul | Beschreibung |
|-------|--------------|
| **event-tracker** | Moderne Event-Management-Suite mit Webhook-Integration, Mail-System und mehrtägigen Events |
| **quiz-manager** | Quiz-Verwaltung mit öffentlicher Anzeige, bestandenen Quizzes und Zoho-CRM Integration |
| **session-display** | Dynamische Anzeige von Jahrestagung-Sessions via Zoho Backstage API mit Elementor-Integration |
| **timeline-manager** | Timeline-Management mit Custom Post Type und Frontend-Editor |
| **microsoft-gruppen** | Microsoft 365 Gruppen-Management Integration |
| **zoho-books-integration** | Zeigt Rechnungen und Gutschriften aus Zoho Books basierend auf Finance ID |

### Fortbildung & Training (5 Module)

| Modul | Beschreibung |
|-------|--------------|
| **fortbildung** | Fortbildungsverwaltung mit Quiz-Import und PDF-Zertifikaten |
| **abstimmen-addon** | Umfassendes Voting-System mit Umfragen, Zoom-Integration und Präsenz-Scanner |
| **anwesenheitsscanner** | Anwesenheitserfassung mit PDF und Barcode-Generierung |
| **efn-manager** | EFN-Management mit Barcode-Generierung, Aufkleber-Druck und Kiosk-Modus |
| **gehaltsstatistik** | Gehaltsdaten-Analyse und Statistiken |

### Content Management (11 Module)

| Modul | Beschreibung |
|-------|--------------|
| **herzzentren** | Herzzentrum-Editor mit Karten und Elementor-Widgets |
| **news-management** | News-Bearbeitungs- und Listensystem |
| **publication-workflow** | Publikations-Management und Workflow |
| **ebcp-guidelines** | EBCP-Guidelines Viewer mit Mehrsprachigkeit, Suche und PDF-Export |
| **mitgliedsantrag** | Mitgliedsantragsformular mit Zoho CRM Integration und Adressvalidierung |
| **kardiotechnik-archiv** | Archiv-Suche der Verbandszeitschrift Kardiotechnik |
| **stellenanzeige** | Stellenanzeigen-Manager mit Frontend-Editor und ACF-Integration |
| **create-quiz-from-frontend** | Quiz Maker Add-on für Frontend Quiz-Erstellung |
| **blaue-seiten** | Verzeichnis-Funktionalität (Blaue Seiten) |
| **wissens-bot** | KI-gestützter Wissens-Bot mit Claude AI und Multi-Datenbank-Integration |
| **custom-content-shortcode** | Anzeige von Posts, Custom Fields, Dateien, Menüs und Widget-Areas |

### Media & Video (3 Module)

| Modul | Beschreibung |
|-------|--------------|
| **vimeo-webinare** | Vimeo Webinare mit dynamischen URLs, automatischen Fortbildungspunkten und Zertifikaten |
| **vimeo-streams** | Multi-Stream Vimeo Video-Management mit Mobile Maximize Support |
| **kiosk-jahrestagung** | Kiosk-Modus für Jahrestagungen |

### Payment & Finance (3 Module)

| Modul | Beschreibung |
|-------|--------------|
| **stripe-formidable** | Stripe SEPA und Kartenzahlung Integration für Formidable Forms |
| **gocardless** | GoCardless Lastschrift-Mandatsverwaltung |
| **daten-bearbeiten** | Mitgliedsdaten-Bearbeitung mit Zoho CRM Sync und GoCardless Integration |

### ACF Tools (4 Module)

| Modul | Beschreibung |
|-------|--------------|
| **acf-anzeiger** | ACF Feld-Anzeige mit erweiterter Formatierung |
| **acf-jetsync** | ACF zu JetEngine Synchronisation |
| **acf-toggle** | ACF Toggle-Funktionen für Feldsichtbarkeit |
| **acf-permissions-manager** | ACF-Berechtigungen verwalten mit Batch-Zuweisung |

### Utilities (11 Module)

| Modul | Beschreibung |
|-------|--------------|
| **elementor-doctor** | Scannt und repariert fehlerhafte Elementor-Seiten |
| **elementor-ai-export** | Exportiert Elementor-Seiten in Claude-freundliches Format |
| **frontend-page-editor** | Frontend-Seitenbearbeitung für Nicht-Admins mit Elementor |
| **conditional-logic** | Bedingte Inhaltsanzeige |
| **shortcode-tools** | Shortcode-Editoren und Grid-Layouts |
| **exif-data** | Bild-EXIF-Metadaten-Verwaltung |
| **zoho-role-manager** | Rollenverwaltung basierend auf Zoho CRM Daten |
| **installer** | Plugin-Installations-Helfer |
| **event-tracker-weiterleitung** | Event-Tracker Weiterleitungs-Webhook |

---

## Installation

1. `dgptm-plugin-suite` Ordner nach `/wp-content/plugins/` hochladen
2. "DGPTM Plugin Suite" in WordPress aktivieren
3. Zu **DGPTM Suite** im Admin-Menü navigieren
4. Gewünschte Module vom Dashboard aktivieren

## Systemanforderungen

- **PHP:** 7.4 oder höher
- **WordPress:** 5.8 oder höher
- **Extensions:** ZipArchive (für Export-Funktionalität)

### Modul-spezifische Anforderungen

| Anforderung | Module |
|-------------|--------|
| Advanced Custom Fields | ACF-Module, Herzzentren, Side-Restrict, Stellenanzeige |
| Elementor | Herzzentren, Session-Display, Frontend-Page-Editor |
| Formidable Forms | Stripe-Formidable, GoCardless |
| Quiz Maker | Quiz-Manager, Create-Quiz-from-Frontend |

## Verzeichnisstruktur

```
dgptm-plugin-suite/
├── dgptm-master.php          # Haupt-Plugin-Datei
├── categories.json           # Kategorie- und Flag-Definitionen
├── CLAUDE.md                 # Claude Code Dokumentation
├── README.md                 # Diese Datei
│
├── admin/                    # Admin-Interface
│   ├── class-plugin-manager.php
│   ├── views/               # Dashboard, Settings, Export, Logs
│   └── assets/              # CSS und JavaScript
│
├── core/                     # Kern-Funktionalität
│   ├── class-module-loader.php
│   ├── class-dependency-manager.php
│   ├── class-module-metadata-file.php
│   ├── class-safe-loader.php
│   ├── class-logger.php
│   └── class-zip-generator.php
│
├── modules/                  # Alle Module
│   ├── core-infrastructure/
│   ├── business/
│   ├── payment/
│   ├── auth/
│   ├── media/
│   ├── content/
│   ├── acf-tools/
│   └── utilities/
│
├── libraries/                # Shared Libraries
│   ├── fpdf/                # PDF-Generierung
│   └── class-code128.php    # Barcode-Generierung
│
├── guides/                   # Modul-Dokumentationen (JSON)
└── exports/                  # Generierte Exports
```

## Modul-Konfiguration

Jedes Modul enthält eine `module.json` Datei:

```json
{
  "id": "module-id",
  "name": "Modul Name",
  "description": "Beschreibung",
  "version": "1.0.0",
  "author": "Autor",
  "main_file": "main-file.php",
  "dependencies": ["andere-module"],
  "wp_dependencies": {"plugins": ["required-plugin"]},
  "category": "utilities",
  "icon": "dashicons-admin-plugins",
  "active": false,
  "critical": false,
  "flags": ["production"],
  "comment": {"text": "Notiz", "timestamp": 1700000000}
}
```

## Sicherheit

- Alle Operationen erfordern `manage_options` Berechtigung
- Nonce-Verifizierung bei allen AJAX-Aufrufen
- Input-Sanitisierung und -Validierung
- Abhängigkeitsprüfung vor Aktivierung
- ABSPATH-Checks in allen Dateien
- API-Tokens werden sicher in der Datenbank gespeichert

## Support & Links

- **Website:** https://www.dgptm.de/
- **Repository:** https://github.com/sebmel75/DGPTMSuite
- **Issues:** https://github.com/sebmel75/DGPTMSuite/issues

---

**Entwickelt von Sebastian Melzer für DGPTM (Deutsche Gesellschaft für Prävention und Telemedizin e.V.)**
