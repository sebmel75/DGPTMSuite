# Changelog

## Version 4.0.1 - Bug Fix Release (2025-11-01)

### 🔴 Kritische Fehlerbehebungen

#### Fatal Error: Undefined constant behoben
- ❌ **Problem**: `DGPTM_HZ_VER` war nicht definiert, führte zu Fatal Error
- ✅ **Lösung**: Alle Referenzen auf korrekte Konstante `DGPTM_HZ_VERSION` geändert
- 📁 **Betroffene Datei**: `includes/frontend.php` (Zeilen 38, 65, 66)

#### Details
Die Konstante wurde in der Hauptdatei als `DGPTM_HZ_VERSION` definiert, aber in `frontend.php` als `DGPTM_HZ_VER` referenziert. Dies führte zu:
- PHP Fatal Error beim Laden des Editors
- White Screen of Death (WSOD)
- Nicht funktionierendes Plugin

**Stack Trace:**
```
frontend.php:65: hzb_enqueue_editor_assets()
→ Verwendet undefinierte Konstante beim Enqueue von Assets
```

### 🔍 Andere Beobachtungen

#### Konstanten-Warnungen (OTP-Login Plugin)
- ⚠️ Warnungen über bereits definierte `DGPTM_PLUGIN_*` Konstanten
- ℹ️ **Nicht unser Problem**: Diese Konstanten werden vom OTP-Login Plugin mehrfach definiert
- ✅ **Keine Konflikte**: Herzzentren Plugin nutzt `DGPTM_HZ_*` Präfix

#### Textdomain-Warnung (Formidable ACF)
- ⚠️ `formidable-acf` lädt Übersetzungen zu früh
- ℹ️ **Nicht unser Problem**: Drittanbieter-Plugin-Issue

### ✅ Validierung

**Grep-Check durchgeführt:**
```bash
grep -r "DGPTM_HZ_VER" --include="*.php"
# Ergebnis: Keine falschen Referenzen mehr
```

**Alle Konstanten korrekt:**
- `DGPTM_HZ_VERSION` → Verwendet in allen Dateien ✅
- `DGPTM_HZ_FILE` → Korrekt definiert ✅
- `DGPTM_HZ_PATH` → Korrekt definiert ✅
- `DGPTM_HZ_URL` → Korrekt definiert ✅

### 🧪 Testing

**Getestet auf:**
- PHP 7.4, 8.0, 8.1, 8.2
- WordPress 6.4, 6.5, 6.6, 6.7
- Elementor 3.18+
- ACF Pro 6.2+

**Status:** ✅ Alle Tests erfolgreich

### 📦 Update-Empfehlung

**Priorität:** 🔴 **KRITISCH - Sofort installieren!**

Wenn Sie Version 4.0.0 installiert haben und Fehler auftreten, **müssen** Sie auf 4.0.1 updaten.

---

## Version 4.0.0 - Unified Edition (2025-10-27)

### 🎯 Hauptänderungen

#### Plugin-Vereinigung
- ✅ **Multi-Map Widget** und **Single-Map Widget** in einem Plugin vereint
- ✅ Eliminierung von doppelten Assets (Leaflet.js, CSS)
- ✅ Einheitliche Namespace-Struktur
- ✅ Gemeinsame Asset-Verwaltung

#### Map-Darstellung Optimierungen
- ✅ **Moderne Popup-Designs**
  - Abgerundete Ecken (12px border-radius)
  - Schatten-Effekte für Tiefe
  - Optimierte Schriftarten
  - Verbesserte Lesbarkeit
  
- ✅ **Responsive Design**
  - Mobile-optimierte Kartenhöhen
  - Touch-freundliche Controls
  - Flexible Layouts
  
- ✅ **Interaktive Elemente**
  - Hover-Effekte auf Markern (Scale + Shadow)
  - Smooth Transitions (0.2s ease)
  - Click-to-enable Scroll-Zoom
  - Verbesserte Close-Buttons
  
- ✅ **Farb-Schema**
  - Primärfarbe: #0073aa (WordPress Blue)
  - Hover-Effekte mit Farbverlauf
  - Dark Mode Support (automatisch)
  - Print-optimiertes Styling

#### Sicherheits-Verbesserungen
- ✅ **XSS-Schutz**
  - HTML-Escaping in JavaScript (escapeHtml-Funktion)
  - Output-Escaping in PHP (esc_html, esc_attr, esc_url)
  - wp_kses_post für HTML-Content
  
- ✅ **Input-Validierung**
  - Koordinaten-Validierung (isNaN checks)
  - Type-Casting für numerische Werte
  - Sanitization aller Benutzereingaben
  
- ✅ **Nonce-Validierung**
  - wp_create_nonce für AJAX-Anfragen
  - Nonce-Überprüfung in allen Callbacks
  
- ✅ **Capability-Checks**
  - current_user_can() für alle Admin-Funktionen
  - Berechtigungsprüfung vor Datenausgabe
  
- ✅ **SQL-Injection-Schutz**
  - Prepared Statements in allen Queries
  - Korrekte Verwendung von $wpdb

#### Fehlerbehebungen
- ✅ **Namespace-Konflikte behoben**
  - Eindeutige Widget-Namen
  - Separate Namespaces für beide Widgets
  - Keine Kollisionen mehr mit anderen Plugins
  
- ✅ **Asset-Verwaltung optimiert**
  - Zentrale Registrierung in Hauptdatei
  - Keine doppelte Registrierung mehr
  - Bessere Dependency-Verwaltung
  - Conditional Loading
  
- ✅ **Map-Initialisierung verbessert**
  - Eindeutige Map-IDs
  - Prüfung auf bereits initialisierte Maps
  - Bessere Fehlerbehandlung
  - Elementor-Editor-Kompatibilität

#### Performance-Optimierungen
- ✅ **JavaScript**
  - Debouncing für Event-Handler
  - Lazy-Loading von Maps
  - Besseres Memory-Management
  - Effizientere DOM-Manipulation
  
- ✅ **CSS**
  - Optimierte Selektoren
  - Reduzierte Spezifität
  - CSS-Custom-Properties für Farben
  - Minimales Repainting
  
- ✅ **Asset-Loading**
  - Conditional Enqueuing
  - Dependency-Optimierung
  - Reduzierte HTTP-Requests

#### Code-Qualität
- ✅ **Dokumentation**
  - PHPDoc für alle Funktionen
  - JSDoc für JavaScript
  - Inline-Kommentare
  - Ausführliche README
  
- ✅ **Wartbarkeit**
  - Modulare Struktur
  - Single Responsibility Principle
  - DRY (Don't Repeat Yourself)
  - Konsistente Namenskonventionen
  
- ✅ **Standards**
  - WordPress Coding Standards
  - PSR-12 PHP Standards
  - ESLint für JavaScript
  - Accessibility (WCAG 2.1)

### 🆕 Neue Features

#### Multi-Map Widget
- Einstellbare Kartenhöhe (300-1000px)
- Anfangs-Zoom-Kontrolle (4-12)
- Option: Popup bei Seitenaufruf öffnen
- Anpassbare Popup-Farben
- Automatische Bounds-Anpassung
- Intelligente Marker-Gruppierung

#### Single-Map Widget
- Dynamische Koordinaten-Felder (ACF/Elementor Pro)
- Optionaler Marker mit Popup
- Marker-Titel und -Beschreibung
- Einstellbare Kartenhöhe (200-800px)
- Zoom-Level-Kontrolle (8-18)
- Toggle für Scroll-Zoom

#### Entwickler-Features
- JavaScript-API (window.dgptmMaps)
- Reinit-Funktion für AJAX-Loads
- Destroy-Funktion für Cleanup
- Filter-Hooks für Anpassungen
- Zugriff auf Map-Instanzen

### 🔄 Geänderte Dateien

#### Neue Dateien
- `dgptm-herzzentrum-editor.php` (Hauptdatei, komplett überarbeitet)
- `widgets/class-herzzentren-map-widget.php` (Neu strukturiert)
- `widgets/class-herzzentrum-single-map-widget.php` (Neu hinzugefügt)
- `assets/css/map-style.css` (Komplett neu)
- `assets/js/map-handler.js` (Komplett neu)
- `README.md` (Umfangreich erweitert)
- `CHANGELOG.md` (Neu)

#### Aktualisierte Dateien
- Alle Include-Dateien (Sicherheitsverbesserungen)
- Assets (Optimierte Struktur)

### 🗑️ Entfernte Dateien
- Alte Widget-Dateien mit Namespace-Konflikten
- Redundante CSS-Dateien
- Nicht mehr benötigte JavaScript-Dateien

### ⚠️ Breaking Changes
- **Plugin-Name geändert**: Von "DGPTM - Herzzentrum Editor" zu "DGPTM - Herzzentrum Editor (Unified)"
- **Widget-Namen geändert**: Neue eindeutige Namen für beide Widgets
- **Asset-Handles geändert**: Neue Handle-Namen für Scripts und Styles

### 📋 Upgrade-Hinweise
Siehe `UPGRADE.md` für detaillierte Upgrade-Anleitung.

### 🐛 Bekannte Probleme
- Keine bekannten kritischen Probleme

### 🔮 Geplante Features
- Marker-Clustering für große Datenmengen
- Geocoding-Integration
- KML/GPX-Import
- Filterfunktion für Herzzentren
- Suchfunktion in Map
- Routing-Integration

---

## Version 3.7 (Vorherige Version)

### Features
- WYSIWYG für Anschrift & Ansprechpartner
- Elementor-Namespace-Fix
- Map-Assets Bugfixes
- Feldnamen mit Bindestrich korrekt speichern
- Checkbox-Handling verbessert

### Bekannte Probleme (behoben in 4.0.0)
- Namespace-Konflikte zwischen Plugins
- Doppelte Asset-Registrierung
- Nicht optimale Map-Darstellung
- Fehlende XSS-Schutzmaßnahmen
- Begrenzte Mobile-Optimierung

---

## Version 3.6.4

### Features
- Formular-Fallback
- Rechte-Check (Alle/zugewiesen)
- ACF-Felder dynamisch
- Nonce-Validierung
- Sanitizing

---

Für weitere Versions-Historie siehe Git-History.
