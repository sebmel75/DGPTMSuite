# Modul-Struktur: Vimeo Webinare

## 📁 Dateistruktur

```
vimeo-webinare/
│
├── dgptm-vimeo-webinare.php          # Hauptdatei (PHP-Klasse)
├── module.json                        # Modul-Konfiguration
├── README.md                          # Vollständige Dokumentation
├── INSTALLATION.md                    # Installations- & Setup-Anleitung
├── STRUCTURE.md                       # Diese Datei
│
├── assets/                            # Frontend Assets
│   ├── css/
│   │   └── style.css                 # Alle Frontend-Styles
│   └── js/
│       └── script.js                 # Vimeo Player, AJAX, UI-Interaktionen
│
└── templates/                         # PHP-Templates für Shortcodes
    ├── player.php                    # Einzelner Webinar-Player
    ├── liste.php                     # Webinar-Übersicht (Grid)
    └── manager.php                   # Frontend-Manager (CRUD)
```

## 📄 Datei-Übersicht

### Core Files

#### `dgptm-vimeo-webinare.php` (Hauptdatei)
**Größe:** ~1200 Zeilen
**Zweck:** Zentrale Plugin-Logik

**Klasse:** `DGPTM_Vimeo_Webinare`

**Hauptmethoden:**
```php
// Initialisierung
__construct()                          # Singleton-Konstruktor
register_post_types()                  # Registriert 'vimeo_webinar' CPT
register_acf_fields()                  # ACF-Feldgruppen für Webinare & User

// Shortcodes
webinar_player_shortcode($atts)        # [vimeo_webinar id="123"]
webinar_liste_shortcode($atts)         # [vimeo_webinar_liste]
webinar_manager_shortcode($atts)       # [vimeo_webinar_manager]

// AJAX - Teilnehmer
ajax_track_progress()                  # Fortschritt speichern
ajax_complete_webinar()                # Webinar abschließen
ajax_generate_certificate()            # PDF-Zertifikat

// AJAX - Manager
ajax_manager_create()                  # Webinar erstellen
ajax_manager_update()                  # Webinar aktualisieren
ajax_manager_delete()                  # Webinar löschen
ajax_manager_stats()                   # Statistiken abrufen

// Helper
get_user_progress($user_id, $webinar_id)        # Fortschritt abrufen
is_webinar_completed($user_id, $webinar_id)    # Abgeschlossen?
create_fortbildung_entry($user_id, $webinar_id) # Fortbildung erstellen
generate_certificate_pdf($user_id, $webinar_id) # PDF generieren
get_webinar_stats($webinar_id)                  # Statistiken
can_manage()                                    # Manager-Berechtigung prüfen

// Admin
add_admin_menu()                       # Admin-Menü
render_admin_stats()                   # Statistik-Seite

// Assets
enqueue_assets()                       # CSS/JS einbinden
```

**ACF-Feldgruppen:**
1. **Webinar Settings** (für vimeo_webinar Post Type)
   - `vimeo_id` - Vimeo Video ID
   - `completion_percentage` - Erforderlicher Fortschritt
   - `ebcp_points` - Fortbildungspunkte
   - `vnr` - VNR
   - `fortbildung_type` - Art der Fortbildung
   - `location` - Ort
   - `certificate_background` - Hintergrundbild
   - `certificate_watermark` - Wasserzeichen

2. **User Meta** (für Benutzer)
   - `vw_is_manager` - Manager-Berechtigung

**User Meta Keys:**
- `_vw_progress_{webinar_id}` - Fortschritt (Float 0-100)
- `_vw_completed_{webinar_id}` - Abgeschlossen (Boolean)
- `_vw_fortbildung_{webinar_id}` - Fortbildung Post ID (Integer)

#### `module.json`
**Zweck:** Modul-Konfiguration für DGPTM Suite

```json
{
  "id": "vimeo-webinare",
  "name": "DGPTM - Vimeo Webinare",
  "category": "media",
  "wp_dependencies": {
    "plugins": ["advanced-custom-fields"]
  }
}
```

### Templates

#### `templates/player.php`
**Zweck:** Einzelner Webinar-Player

**Verfügbare Variablen:**
- `$post_id` - Webinar Post ID
- `$vimeo_id` - Vimeo Video ID
- `$completion_percentage` - Erforderlicher Fortschritt
- `$progress` - Aktueller Benutzer-Fortschritt
- `$is_completed` - Abgeschlossen? (Boolean)
- `$user_id` - Aktueller Benutzer

**Komponenten:**
- Player-Header (Titel, Meta-Infos)
- Completed-Banner (wenn abgeschlossen)
- Vimeo Player (iframe)
- Fortschrittsbalken
- Info-Box
- Webinar-Beschreibung

#### `templates/liste.php`
**Zweck:** Webinar-Übersicht als Grid

**Verfügbare Variablen:**
- `$webinars` - Array aller Webinare
- `$user_id` - Aktueller Benutzer

**Komponenten:**
- Suchfeld
- Status-Filter (Dropdown)
- Webinar-Grid (Cards)
  - Thumbnail (Vimeo)
  - Status-Badge
  - Titel & Meta
  - Fortschrittsbalken (wenn begonnen)
  - Aktions-Buttons

**Card-Stati:**
- `not-started` - Noch nicht begonnen
- `in-progress` - In Bearbeitung
- `completed` - Abgeschlossen

#### `templates/manager.php`
**Zweck:** Frontend-Manager mit CRUD

**Verfügbare Variablen:**
- `$user_id` - Aktueller Benutzer
- `$is_manager` - Manager? (Boolean)
- `$is_admin` - Admin? (Boolean)

**Komponenten:**
- Manager-Header mit "Neu erstellen"-Button
- Tab-Navigation (Liste, Statistiken)
- **Liste-Tab:**
  - Suchfeld
  - Webinar-Tabelle
  - Aktions-Icons (Bearbeiten, Statistik, Löschen)
- **Statistik-Tab:**
  - Stat-Cards (Gesamt, Abschlüsse, In Bearbeitung)
  - Performance-Tabelle
- **Modals:**
  - Create/Edit-Modal (Formular)
  - Statistik-Modal (Details)

### Assets

#### `assets/css/style.css`
**Größe:** ~1000 Zeilen
**Zweck:** Alle Frontend-Styles

**Haupt-Sektionen:**
1. **Player Container** - Webinar-Player Styles
2. **Vimeo Player** - 16:9 Responsive Wrapper
3. **Progress Bar** - Fortschrittsbalken mit Animation
4. **Webinar Liste** - Grid-Layout, Cards
5. **Manager Container** - Tabellen, Formulare
6. **Statistiken** - Stat-Cards, Performance-Tabellen
7. **Modals** - Lightbox-Modals
8. **Formulare** - Input-Felder, Buttons
9. **Loading/Notifications** - Overlay, Toast-Messages
10. **Responsive** - Mobile Breakpoints

**CSS-Variablen (implizit):**
- Primärfarbe: `#2196F3` (Blau)
- Erfolgsfarbe: `#4CAF50` (Grün)
- Warnfarbe: `#FF9800` (Orange)
- Fehlerfarbe: `#f44336` (Rot)

#### `assets/js/script.js`
**Größe:** ~600 Zeilen
**Zweck:** Frontend-Interaktivität

**Haupt-Sektionen:**

1. **Vimeo Player & Tracking**
   ```javascript
   // Initialisierung
   const player = new Vimeo.Player(playerElement);

   // Event: timeupdate
   player.on('timeupdate', function(data) {
       // Fortschritt berechnen
       // UI aktualisieren
       // Fortschritt speichern (alle 5%)
       // Completion prüfen
   });

   // Event: ended
   player.on('ended', function() {
       // Webinar abschließen
   });
   ```

2. **AJAX-Funktionen**
   ```javascript
   saveProgress(webinarId, progress)           // Fortschritt speichern
   completeWebinar(webinarId)                  // Webinar abschließen
   ```

3. **Zertifikat-Generierung**
   ```javascript
   $('.vw-generate-certificate').on('click')   // Download PDF
   ```

4. **Liste - Suche & Filter**
   ```javascript
   $('.vw-search-input').on('input')           // Live-Suche
   $('.vw-status-filter').on('change')         // Status-Filter
   ```

5. **Manager - CRUD**
   ```javascript
   $('#vw-create-new').on('click')             // Modal öffnen (Create)
   $('.vw-edit').on('click')                   // Modal öffnen (Edit)
   $('.vw-delete').on('click')                 // Webinar löschen
   $('#vw-webinar-form').on('submit')          // Formular absenden
   $('.vw-view-stats').on('click')             // Statistik-Modal
   ```

6. **Manager - Tabs & Suche**
   ```javascript
   $('.vw-tab-btn').on('click')                // Tab wechseln
   $('.vw-manager-search-input').on('input')   // Tabelle durchsuchen
   ```

7. **Helper-Funktionen**
   ```javascript
   showLoading()                               // Overlay anzeigen
   hideLoading()                               // Overlay ausblenden
   showNotification(message, type)             // Toast-Nachricht
   ```

**AJAX-Endpoints:**
- `vw_track_progress`
- `vw_complete_webinar`
- `vw_generate_certificate`
- `vw_manager_create`
- `vw_manager_update`
- `vw_manager_delete`
- `vw_manager_stats`

**Localized Data (vwData):**
```javascript
{
    ajaxUrl: "wp-admin/admin-ajax.php",
    nonce: "...",
    userId: 123
}
```

## 🔄 Datenfluss

### Webinar ansehen
```
1. Benutzer öffnet Webinar-Seite
   └─> player.php wird geladen
       └─> Vimeo iframe eingebettet
           └─> Vimeo Player API initialisiert

2. Video wird abgespielt
   └─> timeupdate Event (alle ~250ms)
       └─> Fortschritt berechnen (Sekunden / Dauer * 100)
           └─> UI aktualisieren
               └─> Alle 5% → AJAX: vw_track_progress
                   └─> User Meta speichern: _vw_progress_{id}

3. Erforderlicher Fortschritt erreicht (z.B. 90%)
   └─> AJAX: vw_complete_webinar
       └─> create_fortbildung_entry()
           └─> wp_insert_post('fortbildung')
               └─> ACF Fields setzen
                   └─> User Meta setzen: _vw_completed_{id} = true
                       └─> Seite neu laden
                           └─> Completion-Banner anzeigen
```

### Zertifikat generieren
```
1. Button "Zertifikat herunterladen" klicken
   └─> AJAX: vw_generate_certificate
       └─> generate_certificate_pdf()
           └─> FPDF laden
               └─> PDF erstellen
                   ├─> Hintergrundbild (optional)
                   ├─> Titel, Name, Punkte, Datum
                   └─> Wasserzeichen (optional)
                       └─> PDF speichern in /uploads/webinar-certificates/
                           └─> URL zurückgeben
                               └─> window.open(pdf_url)
```

### Manager: Webinar erstellen
```
1. Button "Neues Webinar erstellen" klicken
   └─> Modal öffnen
       └─> Formular ausfüllen
           └─> Submit-Event
               └─> AJAX: vw_manager_create
                   └─> wp_insert_post('vimeo_webinar')
                       └─> ACF Fields setzen
                           └─> Erfolg-Benachrichtigung
                               └─> Seite neu laden
```

## 🎯 Shortcode-Parameter

### `[vimeo_webinar]`
**Pflicht-Parameter:**
- `id` - Post-ID des Webinars (Integer)

**Beispiele:**
```
[vimeo_webinar id="123"]
[vimeo_webinar id="456"]
```

### `[vimeo_webinar_liste]`
**Keine Parameter**

**Beispiel:**
```
[vimeo_webinar_liste]
```

### `[vimeo_webinar_manager]`
**Keine Parameter**

**Zugriffskontrolle:**
- ACF User Meta: `vw_is_manager = true` ODER
- WordPress Capability: `manage_options`

**Beispiel:**
```
[vimeo_webinar_manager]
```

## 🔐 Sicherheitsmaßnahmen

1. **AJAX-Sicherheit:**
   - Nonce-Verifizierung: `check_ajax_referer('vw_nonce', 'nonce')`
   - Benutzer-Anmeldung: `is_user_logged_in()`
   - Manager-Berechtigung: `can_manage()`

2. **Input-Sanitization:**
   - `sanitize_text_field()` für Text
   - `wp_kses_post()` für HTML
   - `intval()` / `floatval()` für Zahlen

3. **Datenbankabfragen:**
   - Prepared Statements: `$wpdb->prepare()`

4. **Dateizugriff:**
   - ABSPATH-Check in allen Templates
   - Keine direkten File-Includes von User-Input

## 📊 Performance-Optimierung

1. **Asset-Loading:**
   - CSS/JS nur bei Shortcode-Verwendung
   - Vimeo Player API nur bei Bedarf
   - Lazy Loading für Thumbnails möglich

2. **Datenbankabfragen:**
   - Prepared Statements gecacht
   - Nur aktive Webinare in Listen
   - Statistik-Queries optimiert

3. **AJAX-Calls:**
   - Fortschritt nur alle 5% gespeichert
   - Debouncing für Suche (nicht implementiert, kann erweitert werden)

## 🧩 Erweiterungsmöglichkeiten

### Zusätzliche Features
- **E-Mail-Benachrichtigung** bei Completion
- **Kommentar-System** für Webinare
- **Quiz** nach Webinar
- **Mehrsprachigkeit** (WPML/Polylang)
- **CSV-Export** von Statistiken
- **Gruppen-Zuordnung** (bestimmte Webinare für bestimmte Rollen)
- **Zeitlimit** (Webinar nur in bestimmtem Zeitraum verfügbar)
- **Wiederholungs-Sperre** (nur einmal abschließbar)

### Code-Erweiterungen
```php
// Hook für Custom Logic nach Completion
add_action('vw_webinar_completed', function($user_id, $webinar_id) {
    // Ihre Custom-Logik hier
}, 10, 2);

// Filter für Zertifikat-PDF
add_filter('vw_certificate_pdf', function($pdf, $user_id, $webinar_id) {
    // PDF modifizieren
    return $pdf;
}, 10, 3);
```

## 📞 Entwickler-Notizen

**Autor:** Sebastian Melzer
**Datum:** 2025-11-27
**Version:** 1.0.0

**Tested with:**
- WordPress 6.4+
- ACF 6.2+
- PHP 8.0+
- Modern Browsers (Chrome, Firefox, Safari, Edge)

**Known Issues:**
- Keine (Initial Release)

**Future Todos:**
- [ ] Add email notifications
- [ ] Add quiz integration
- [ ] Add certificate templates selector
- [ ] Add batch certificate generation
- [ ] Add advanced statistics (charts)
- [ ] Add export to CSV
- [ ] Add webhook support
- [ ] Add REST API endpoints
