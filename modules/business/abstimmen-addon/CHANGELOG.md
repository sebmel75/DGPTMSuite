# Changelog - DGPTM Abstimmen-Addon

## [4.0.0] - 2025-01-29

### 🎉 Major Consolidation Release

Dieses Release vereint drei separate Plugins in ein einziges, gut strukturiertes Modul:
- ✅ `dgptm-abstimmungstool.php` (v3.7.0) - Voting-System
- ✅ `onlineabstimmung.php` (v2.0) - Zoom-Integration
- ✅ `abstimmenadon.php` (v1.1.0) - Präsenz-Scanner

### Added
- **Neue Hauptdatei**: `abstimmen-addon.php` als zentraler Einstiegspunkt
- **Assets externalisiert**:
  - `assets/css/admin.css` (400+ Zeilen)
  - `assets/css/frontend.css` (350+ Zeilen)
  - `assets/js/admin.js` (350+ Zeilen)
  - `assets/js/frontend.js` (450+ Zeilen)
- **REST API Dokumentation**: Vollständige OpenAPI 3.0.3 Spezifikation (`api-documentation.yaml`)
- **PHPUnit Tests**: 51 Testfälle für kritische Funktionen
  - `tests/unit/HelpersTest.php` (4 Tests)
  - `tests/unit/VotingTest.php` (10 Tests)
  - `tests/unit/ZoomTest.php` (12 Tests)
  - `tests/integration/AttendanceTest.php` (10 Tests)
  - `tests/integration/RestApiTest.php` (15 Tests)
- **Screenshot-Dokumentation**: Vollständige Liste benötigter Screenshots (`screenshots/SCREENSHOTS.md`)
- **Migrations-System**: Automatische Migration von älteren Versionen (`migrate-v4.php`)
  - Auto-Migration bei Erstaktivierung
  - Admin-Notice mit One-Click-Migration
  - Vollständige Backup-Funktionalität
- **Modular Wrapper-Klassen**:
  - `includes/zoom/class-zoom-integration.php` - Zoom-Wrapper
  - `includes/presence/class-presence-scanner.php` - Scanner-Wrapper

### Changed
- **Enqueue-System konsolidiert**: `includes/common/enqueue.php` jetzt mit bedingtem Laden
- **Hauptmodul-Architektur**: Singleton-Pattern mit klarer Dependency-Injection
- **Shortcode-Registrierung**: Zentral im Hauptmodul, keine Duplikate mehr
- **Legacy-Konstanten**: Für Rückwärtskompatibilität beibehalten

### Improved
- **Performance**: Assets werden nur geladen, wenn benötigt
- **Caching**: Browser-Caching durch externe CSS/JS-Dateien
- **Code-Qualität**: Konsistente Formatierung und Kommentierung
- **Wartbarkeit**: Klare Ordnerstruktur und Separation of Concerns
- **Documentation**: Umfassende README.md (500+ Zeilen)

### Deprecated
- `dgptm-abstimmungstool.php` - Funktionalität in Hauptmodul integriert
- `onlineabstimmung.php` - Wird nur noch als Klasse geladen
- `abstimmenadon.php` - Wird nur noch als Klasse geladen
- Alle drei Dateien bleiben aus Kompatibilitätsgründen, sollten aber nicht mehr direkt verwendet werden

### Technical Details

**Module Structure:**
```
abstimmen-addon/
├── abstimmen-addon.php (NEW - Main entry point v4.0.0)
├── migrate-v4.php (NEW - Migration system)
├── api-documentation.yaml (NEW - OpenAPI spec)
├── assets/ (NEW - Externalized assets)
│   ├── css/
│   │   ├── admin.css
│   │   └── frontend.css
│   └── js/
│       ├── admin.js
│       └── frontend.js
├── includes/
│   ├── common/ (enqueue.php consolidated)
│   ├── zoom/
│   │   └── class-zoom-integration.php (NEW)
│   ├── presence/
│   │   └── class-presence-scanner.php (NEW)
│   ├── admin/ (unchanged)
│   ├── ajax/ (unchanged)
│   ├── beamer/ (unchanged)
│   ├── export/ (unchanged)
│   ├── public/ (unchanged)
│   └── registration/ (unchanged)
├── tests/ (NEW - PHPUnit test suite)
│   ├── phpunit.xml
│   ├── bootstrap.php
│   ├── helpers/TestHelpers.php
│   ├── unit/ (3 test files)
│   └── integration/ (2 test files)
├── screenshots/
│   └── SCREENSHOTS.md (NEW)
├── README.md (Enhanced - 500+ lines)
└── CHANGELOG.md (NEW - This file)
```

**Migration Path:**
- v1.x → v2.x → v3.x → v4.0.0 (automatic)
- All settings, polls, questions, votes preserved
- User metadata migrated
- Zoom settings migrated
- Attendance data migrated
- Automatic backup created

**Breaking Changes:**
None - Fully backwards compatible. All existing functionality preserved.

**Upgrade Instructions:**
1. Deactivate old plugins (if activated separately)
2. Activate `abstimmen-addon` module in DGPTM Suite
3. Migration runs automatically
4. Or use admin notice "Migration jetzt ausführen" button
5. Verify all settings in WordPress Admin → DGPTM Voting

---

## [3.7.0] - Previous Release (dgptm-abstimmungstool.php)

### Features (Legacy)
- Voting-/Beamer-Plugin mit Verwaltung
- Beamer-Ansicht
- Teilnehmerverwaltung
- CSV/PDF-Export
- QR-Teilnahme (Token)
- Stimmenliste & Ungültig-Markierung
- Diagrammwahl
- Gesamtstatistik
- Registrierungsmonitor (Webhook)
- E-Mail-Einladung

---

## [2.0] - Previous Release (onlineabstimmung.php)

### Features (Legacy)
- Zoom S2S OAuth Integration
- Meeting/Webinar Registrierung
- Webhook für Anwesenheitserfassung
- Live-Status
- CSV/PDF Export für Anwesenheit
- Debug-Log mit Frontend-Diagnose

---

## [1.1.0] - Previous Release (abstimmenadon.php)

### Features (Legacy)
- Präsenz-Scanner mit QR-Code
- Manuelle Namenssuche
- Doppelklick-Übernahme
- Status = Mitgliedsart Zuweisung

---

## Version History Summary

| Version | Release Date | Main File | Status |
|---------|--------------|-----------|--------|
| 4.0.0 | 2025-01-29 | abstimmen-addon.php | ✅ Current |
| 3.7.0 | Previous | dgptm-abstimmungstool.php | 🔄 Deprecated |
| 2.0 | Previous | onlineabstimmung.php | 🔄 Legacy |
| 1.1.0 | Previous | abstimmenadon.php | 🔄 Legacy |
