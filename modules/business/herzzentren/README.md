# DGPTM Herzzentren Editor (Unified) - Version 4.0.0

## Beschreibung

Das vereinigte Plugin für die Verwaltung und Darstellung von Herzzentren auf der DGPTM-Website. 
Kombiniert Multi-Map und Single-Map Funktionalität in einem Plugin.

## Was ist neu in Version 4.0.0?

### ✅ Vereinigung der Plugins
- **Multi-Map Widget** (alle Herzzentren) und **Single-Map Widget** (einzelnes Herzzentrum) in einem Plugin
- Keine doppelten Assets mehr - optimierte Ladeleistung
- Einheitliche Code-Basis für einfachere Wartung

### 🎨 Optimierte Map-Darstellung
- **Moderne Popup-Designs** mit abgerundeten Ecken und Schatten
- **Responsive Design** - optimiert für alle Bildschirmgrößen
- **Verbesserte Marker-Icons** mit Hover-Effekten
- **Smooth Transitions** für bessere User Experience
- **Dark Mode Support** (automatische Erkennung)
- **Print-optimiertes Styling**

### 🔒 Erhöhte Sicherheit
- **XSS-Schutz** durch HTML-Escaping in JavaScript
- **Input-Validierung** für alle Koordinaten und Benutzereingaben
- **Nonce-Validierung** für AJAX-Anfragen
- **SQL-Injection-Schutz** durch Prepared Statements
- **Output-Escaping** in allen Templates
- **Capability-Checks** für alle Admin-Funktionen

### 🐛 Fehlerbehebungen
- Namespace-Konflikte zwischen den Plugins behoben
- Doppelte Asset-Registrierung eliminiert
- Verbesserte Asset-Verwaltung
- Konsistente ID-Generierung für Maps
- Bessere Fehlerbehandlung

### ⚡ Performance-Optimierungen
- Lazy-Loading für Map-Assets
- Optimierte JavaScript-Ausführung
- Besseres Caching
- Reduzierte HTTP-Requests

## Installation

1. Alte Plugins deaktivieren:
   - `DGPTM - Herzzentrum Editor` (Version 3.7)
   - `GRT Elementor Herzzentren Map Single`

2. Neues Plugin hochladen und aktivieren

3. **Wichtig:** Elementor und Elementor Pro müssen installiert sein

## Verwendung

### Multi-Map Widget

Zeigt alle Herzzentren auf einer interaktiven Karte an.

**Elementor:**
1. Widget "Herzzentren Karte" zum Layout hinzufügen
2. Einstellungen anpassen:
   - Kartenhöhe
   - Anfangs-Zoom
   - Popup bei Seitenaufruf öffnen
   - Popup-Farben

**Features:**
- Automatische Anzeige aller veröffentlichten Herzzentren
- Intelligente Bounds-Anpassung
- Click-to-Enable Scroll-Zoom
- Responsive Design

### Single-Map Widget

Zeigt einen einzelnen Standort auf einer Karte an.

**Elementor:**
1. Widget "Herzzentrum Einzelkarte" zum Layout hinzufügen
2. Koordinaten eingeben:
   - Breitengrad (Latitude)
   - Längengrad (Longitude)
3. Optional:
   - Marker-Titel
   - Marker-Beschreibung
   - Kartenhöhe
   - Zoom-Level

**Features:**
- Dynamische Felder für Koordinaten (ACF/Elementor Pro)
- Optionaler Marker mit Popup
- Einstellbarer Zoom-Level
- Scroll-Zoom optional deaktivierbar

## Editor-Funktionalität

Das Plugin behält alle Editor-Funktionen des Original-Plugins:

- Frontend-Bearbeitung von Herzzentren
- ACF-Integration
- Berechtigungssystem
- AJAX-basierte Formularverarbeitung
- Medien-Upload
- WYSIWYG-Editor für Anschrift und Ansprechpartner

## Technische Details

### Anforderungen
- WordPress: 5.8+
- PHP: 7.4+
- Elementor: neueste Version
- Elementor Pro: empfohlen

### Verwendete Technologien
- Leaflet.js 1.9.4 (Open-Source Kartenbibliothek)
- OpenStreetMap Tiles
- Advanced Custom Fields (ACF)
- jQuery

### Plugin-Struktur
```
dgptm-herzzentren-unified/
├── dgptm-herzzentrum-editor.php (Hauptdatei)
├── assets/
│   ├── css/
│   │   ├── map-style.css (Optimiertes Map-Styling)
│   │   ├── hzb-editor.css
│   │   └── hzb-media-modal.css
│   ├── js/
│   │   ├── map-handler.js (Haupt-JavaScript)
│   │   ├── hzb-editor.js
│   │   ├── hzb-media.js
│   │   └── hzb-direct-upload.js
│   ├── images/
│   │   └── marker-2.png (Custom Marker Icon)
│   ├── leaflet.js
│   └── leaflet.css
├── widgets/
│   ├── class-herzzentren-map-widget.php (Multi-Map)
│   └── class-herzzentrum-single-map-widget.php (Single-Map)
└── includes/
    ├── acf.php
    ├── admin.php
    ├── editor.php
    ├── frontend.php
    ├── ajax.php
    ├── permissions.php
    └── ...
```

## Sicherheitshinweise

Das Plugin implementiert folgende Sicherheitsmaßnahmen:

1. **Input-Validierung**: Alle Benutzereingaben werden validiert
2. **Output-Escaping**: Alle Ausgaben werden escaped
3. **Nonce-Überprüfung**: Alle AJAX-Anfragen nutzen Nonces
4. **Capability-Checks**: Berechtigungsprüfungen für alle Admin-Funktionen
5. **SQL-Sicherheit**: Prepared Statements für Datenbankabfragen

## Entwickler-Hinweise

### JavaScript-API

```javascript
// Maps neu initialisieren (z.B. nach AJAX-Load)
window.dgptmMaps.reinit();

// Spezifische Map zerstören
window.dgptmMaps.destroy('dgptm-map-123');

// Zugriff auf Map-Instanzen
const mapInstance = window.dgptmMaps.instances['dgptm-map-123'];
```

### Filter und Actions

```php
// Map-Daten filtern
add_filter('dgptm_herzzentren_map_data', function($herzzentren) {
    // Daten modifizieren
    return $herzzentren;
});

// Asset-URL anpassen
add_filter('dgptm_assets_url', function($url) {
    return $url;
});
```

## Changelog

### Version 4.0.0 (2025-10-27)
- Vereinigung von Multi-Map und Single-Map Plugins
- Optimierte Map-Darstellung mit modernem Design
- Erhöhte Sicherheit (XSS-Schutz, Input-Validierung)
- Fehlerbeseitigungen (Namespace-Konflikte, Asset-Verwaltung)
- Performance-Optimierungen
- Dark Mode Support
- Responsive Design Verbesserungen
- Verbesserte Accessibility
- Umfangreiche Code-Dokumentation

### Version 3.7 (Vorherige Version)
- WYSIWYG für Anschrift & Ansprechpartner
- Elementor-Namespace-Fix
- Map-Assets Bugfixes

## Support

Bei Fragen oder Problemen:
- Sebastian Melzer
- DGPTM (Deutsche Gesellschaft für Perfusionstechnologie)

## Credits

- **Leaflet**: https://leafletjs.com/
- **OpenStreetMap**: https://www.openstreetmap.org/
- **Original Entwicklung**: Jan Hintelmann (GRT Agentur)
- **Vereinigung & Optimierung**: Sebastian Melzer

## Lizenz

Dieses Plugin wurde speziell für die DGPTM entwickelt.
