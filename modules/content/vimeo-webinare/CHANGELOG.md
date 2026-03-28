# Changelog - DGPTM Vimeo Webinare

## Version 1.2.6 - Trigger-Fix & Debug-Tools (2025-11-28)

### 🐛 Kritischer Bugfix: Assets wurden nicht geladen

**Problem:** Bei dynamischen Webinar-Seiten (`/webinar/{id}`) wurden JavaScript und CSS nicht korrekt geladen, weil `template_redirect` zu spät aufgerufen wird (nach `wp_head()`).

**Lösung:** Neuer `wp` Hook lädt Assets früher:
```php
add_action('wp', [$this, 'maybe_load_webinar_assets']);
```

**Technische Details:**
- Assets werden jetzt im `wp` Hook geladen (VOR `wp_head()`)
- `wp_localize_script()` wird immer aufgerufen, auch wenn Script bereits enqueued
- Sichert dass `vwData` (AJAX-URL, Nonce) immer verfügbar ist

### 🔍 Umfangreiches JavaScript-Debugging

**Neue Console-Logs:**
```javascript
VW Script loaded!
VW Container found: 1
VW Init: {webinarId, completionRequired, isLoggedIn, playerElement, vimeoSDK, vwData}
VW: Creating Vimeo Player...
VW: Video duration loaded: 1800
VW: Play event - starting tracking
VW Progress Check: {...}  // Jede Sekunde
VW: COMPLETION TRIGGERED!
VW: completeWebinar called for webinar ID: 123
VW: AJAX response: {...}
```

**Fehler-Erkennung:**
- Warnt wenn Player-Element nicht gefunden
- Warnt wenn Vimeo SDK nicht geladen
- Lädt Vimeo SDK dynamisch nach falls fehlend
- Detaillierte AJAX-Fehler mit xhr, status, error

### 🧪 Debug-Tools (nur bei WP_DEBUG=true)

**Test-Buttons im Player-Template:**
1. **🧪 Completion manuell testen** - Löst AJAX-Call direkt aus
2. **📊 JavaScript-Status prüfen** - Zeigt vwData, jQuery, Vimeo SDK Status

**Globale Test-Funktion:**
```javascript
// In Browser Console:
window.vwCompleteWebinar(123);  // Webinar-ID
```

### 🔧 Verbesserungen

#### force_enqueue_assets()
```php
// VORHER: wp_localize_script nur beim ersten Enqueue
if (!wp_script_is('vw-script', 'enqueued')) {
    wp_enqueue_script(...);
    wp_localize_script(...);  // Nur hier!
}

// JETZT: Localize immer aufrufen
if (!wp_script_is('vw-script', 'enqueued')) {
    wp_enqueue_script(...);
}
// Immer localize, egal ob schon enqueued:
if (wp_script_is('vw-script', 'enqueued') || wp_script_is('vw-script', 'registered')) {
    wp_localize_script(...);
}
```

#### Vimeo SDK Fallback
```javascript
if (typeof Vimeo === 'undefined') {
    console.error('VW: Vimeo SDK not loaded!');
    console.log('VW: Trying to load Vimeo SDK...');
    // Lädt SDK dynamisch nach
    const script = document.createElement('script');
    script.src = 'https://player.vimeo.com/api/player.js';
    script.onload = () => location.reload();
    document.head.appendChild(script);
}
```

### 📊 Erweiterte AJAX-Fehlerbehandlung

```javascript
error: function(xhr, status, error) {
    hideLoading();
    console.error('VW: AJAX error:', {xhr, status, error});
    showNotification('Netzwerkfehler: ' + error, 'error');
}
```

### 🔧 Asset-Versionen aktualisiert

CSS und JS Version von `1.0.1` auf `1.2.6` erhöht für Cache-Busting.

### 💡 Debugging-Workflow

1. **WordPress Debug aktivieren:**
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   ```

2. **Webinar-Seite öffnen:**
   - `/webinar/{id}`

3. **Browser Console öffnen (F12):**
   - Sehen Sie "VW Script loaded!"?
   - Sehen Sie "VW Init:" mit allen Daten?
   - Ist `vwData` definiert?

4. **Test-Button klicken:**
   - "🧪 Completion manuell testen"
   - Prüfen Sie Console-Output
   - Prüfen Sie Network-Tab (admin-ajax.php)

5. **debug.log prüfen:**
   - `/wp-content/debug.log`
   - Suchen nach "VW Complete Webinar"

### 🐛 Bekannte Probleme behoben

- ✅ JavaScript lädt nicht → Gelöst durch frühere Asset-Ladung
- ✅ `vwData` ist undefined → Gelöst durch immer-localize
- ✅ Vimeo SDK fehlt → Gelöst durch Fallback-Loader
- ✅ Kein Trigger bei Completion → Sollte jetzt funktionieren!

### ⚠️ Nach Update zu tun

1. **Cache leeren:**
   - Browser-Cache (Strg+Shift+R)
   - WordPress-Cache-Plugin

2. **Test durchführen:**
   - Mit WP_DEBUG=true
   - Test-Button verwenden
   - Console-Logs prüfen

---

## Version 1.2.5 - URL-Vereinfachung & Zertifikat-Attachment (2025-11-28)

### 🔄 URL-Struktur vereinfacht

**Änderung:** Dynamische Webinar-URLs von `/wissen/webinar/{id}` zu `/webinar/{id}` vereinfacht.

**Betroffene Dateien:**
- `dgptm-vimeo-webinare.php` - Rewrite Rules geändert (Zeile 101-114)
- `templates/manager.php` - Link aktualisiert (Zeile 75)
- `templates/player.php` - Login-URL aktualisiert (Zeile 35)
- `templates/liste.php` - Card-Link aktualisiert (Zeile 96)

**Neue URLs:**
- Webinar ansehen: `/webinar/{id}` oder `/webinar?id={id}`
- Beispiel: `https://ihre-domain.de/webinar/123`

**Migration:**
- **WICHTIG:** Nach dem Update Permalinks neu speichern!
- WordPress Admin → Einstellungen → Permalinks → "Änderungen speichern"

### 📎 Zertifikat als ACF Attachment

**Neue Funktionalität:** Generierte Zertifikate werden jetzt als WordPress-Attachment in der Mediathek gespeichert und automatisch mit dem Fortbildungs-Post verknüpft.

**Technische Änderungen:**

#### 1. generate_certificate_pdf() - Neuer Return-Typ
**Vorher:**
```php
return $pdf_url; // String
```

**Jetzt:**
```php
return [
    'url' => $pdf_url,
    'attachment_id' => $attachment_id
]; // Array
```

**Implementierung:**
- PDF wird mit `wp_insert_attachment()` in Mediathek importiert
- Attachment-Metadaten werden generiert
- Attachment-ID wird zum Fortbildungs-Post als ACF-Feld "attachements" hinzugefügt

#### 2. ajax_complete_webinar() - Erweitert
```php
// Add certificate as attachment to fortbildung post
if ($attachment_id) {
    update_field('attachements', $attachment_id, $fortbildung_id);
    error_log('VW Complete Webinar - Certificate attached to fortbildung: ' . $attachment_id);
}
```

#### 3. ajax_generate_certificate() - Angepasst
- Arbeitet jetzt mit Array-Return von `generate_certificate_pdf()`
- Extrahiert URL aus Result-Array

### 🎯 ACF Fortbildung-Integration

**Verifiziert:** Alle ACF-Felder des Fortbildung Post Types werden korrekt gesetzt:

| Feld | Wert | Quelle |
|------|------|--------|
| `user` | User-ID | `$user_id` |
| `date` | Aktuelles Datum | `current_time('Y-m-d')` |
| `location` | "Online" | Fest |
| `type` | "Webinar" | Fest |
| `points` | EBCP-Punkte | ACF vom Webinar |
| `vnr` | VNR | ACF vom Webinar |
| `token` | Verifikations-Token | 32 Zeichen generiert |
| `freigegeben` | true | Automatisch freigegeben |
| `freigabe_durch` | "System (Webinar)" | Fest |
| `freigabe_mail` | Admin-E-Mail | `get_option('admin_email')` |
| `attachements` | Zertifikat-PDF | **NEU:** Attachment-ID |

### 📝 Sonstige Änderungen

- **Kategorie geändert:** Von "media" zu "content" in `module.json`
- **Beschreibung aktualisiert:** Neue URL-Struktur dokumentiert

### 🔧 Logging erweitert

Zusätzliche Log-Ausgaben für Zertifikat-Attachment:
```
VW Certificate PDF - Attachment created: 789
VW Complete Webinar - Certificate attached to fortbildung: 789
```

### 🐛 Bug Fixes

- Zertifikat wird jetzt korrekt als WordPress-Attachment gespeichert
- ACF "attachements" Feld wird automatisch mit Zertifikat-PDF verknüpft
- Fortbildungs-Einträge enthalten jetzt direkten Download-Link zum Zertifikat

### 💡 Hinweise für Entwickler

**Wenn Sie `generate_certificate_pdf()` verwenden:**
```php
// ALT (v1.2.4 und früher):
$pdf_url = $this->generate_certificate_pdf($user_id, $webinar_id);

// NEU (v1.2.5+):
$pdf_result = $this->generate_certificate_pdf($user_id, $webinar_id);
$pdf_url = $pdf_result['url'];
$attachment_id = $pdf_result['attachment_id'];
```

---

## Version 1.2.4 - Umfangreiches Logging für Debugging (2025-11-27)

### 🔍 Debugging-Verbesserungen

**Problem:** Automatische Completion beim Erreichen der Bestehensgrenze funktioniert nicht (kein Fortbildungseintrag, kein Zertifikat, keine E-Mail).

**Lösung:** Umfangreiche Logging-Instrumentierung hinzugefügt, um Fehlerquelle zu identifizieren.

#### Frontend (JavaScript):
- **Console-Logging** - Detaillierte Fortschritts-Logs jede Sekunde
- **Progress-Check-Output** - Zeigt: watched, duration, progress, required, willComplete
- **Completion-Trigger-Log** - "VW: COMPLETION TRIGGERED!" wenn Schwelle erreicht
- Hilft zu identifizieren: Läuft Tracking? Wird Duration geladen? Wird Completion erkannt?

**Beispiel Console-Output:**
```javascript
VW Progress Check: {
  watched: 1620,
  duration: 1800,
  progress: "90.00",
  required: 90,
  willComplete: true
}
VW: COMPLETION TRIGGERED!
```

#### Backend (PHP):
- **ajax_complete_webinar()** - Vollständig instrumentiert
  - User-ID und Webinar-ID logging
  - "Creating Fortbildung entry..." Status
  - Fortbildung-ID bei Erfolg
  - "Generating certificate..." Status
  - Certificate-URL bei Erfolg
  - "Sending email..." Status
  - E-Mail-Status (Yes/No)
  - "SUCCESS!" bei komplettem Durchlauf

- **create_fortbildung_entry()** - Detailliertes Logging
  - Start-Parameter (User, Webinar)
  - Webinar gefunden/nicht gefunden
  - Doubletten-Check-Ergebnis
  - Post-Creation-Status
  - ACF-Felder-Setting-Status
  - Finale Fortbildung-ID

**Beispiel debug.log Output:**
```
VW Complete Webinar - User: 123, Webinar: 456
VW Complete Webinar - Creating Fortbildung entry...
VW Create Fortbildung - Start: User 123, Webinar 456
VW Create Fortbildung - Webinar found: Test Webinar
VW Create Fortbildung - No existing entry, creating new...
VW Create Fortbildung - Points: 2.5, VNR: 12345
VW Create Fortbildung - Post created: 789
VW Create Fortbildung - Setting ACF fields...
VW Create Fortbildung - ACF fields set
VW Create Fortbildung - Completed. Fortbildung ID: 789
VW Complete Webinar - Fortbildung created: 789
VW Complete Webinar - Generating certificate...
VW Complete Webinar - Certificate generated: https://...
VW Complete Webinar - Sending email...
VW Complete Webinar - Email sent: Yes
VW Complete Webinar - SUCCESS!
```

### 📚 Neue Dokumentation

#### DEBUG-COMPLETION.md (400+ Zeilen)
Umfassende Troubleshooting-Anleitung mit:
- Schritt-für-Schritt Debugging-Prozedur
- WordPress Debug-Modus aktivieren
- Browser Console interpretieren
- debug.log lesen und verstehen
- 6 häufige Probleme mit Lösungen
- Checkliste für vollständige Completion
- Manuelle Test-Prozeduren
- Support-Informationen sammeln

#### TESTING-GUIDE.md
Schnelltest-Anleitung mit:
- 2-Minuten-Vorbereitung
- Was bei Erfolg zu sehen ist
- 8 Fehlerfälle mit Diagnose
- Quick-Test (30 Sekunden)
- Support-Paket erstellen

#### QUICK-REFERENCE.md
Referenzkarte (zum Ausdrucken) mit:
- 5-Sekunden-Fehler-Diagnose-Tabelle
- Häufigste Lösungen
- System-Voraussetzungen
- 2-Minuten-Test-Prozedur
- Kritische Fehler-Liste
- Pro-Tipps (z.B. Bestehensgrenze temporär senken)

### 🔧 Technische Änderungen

**Dateien geändert:**
- `assets/js/script.js` (Zeilen 76-113) - Console-Logging hinzugefügt
- `dgptm-vimeo-webinare.php` (Zeilen 503-555, 658-753) - error_log() hinzugefügt

**Dateien neu:**
- `DEBUG-COMPLETION.md` - Vollständige Debugging-Anleitung
- `TESTING-GUIDE.md` - Schnelltest-Guide
- `QUICK-REFERENCE.md` - Referenzkarte

### 💡 Nächste Schritte für User

1. WordPress Debug aktivieren (wp-config.php)
2. Webinar bis zur Bestehensgrenze ansehen
3. Browser Console öffnen (F12)
4. Logs analysieren (siehe DEBUG-COMPLETION.md)
5. Fehlerquelle identifizieren:
   - Frontend-Problem? (Keine Console-Logs)
   - AJAX-Problem? (Console OK, keine Backend-Logs)
   - Backend-Problem? (Spezifischer Fehler in debug.log)

### 🐛 Bekanntes Problem

**Status:** Automatische Completion funktioniert nicht in Produktionsumgebung

**Diagnose-Status:** Code instrumentiert, wartet auf Logs vom User

**Mögliche Ursachen:**
- Frontend: JavaScript nicht geladen, Vimeo SDK fehlt, User nicht eingeloggt erkannt
- AJAX: Nonce-Fehler, 403/500 Fehler, AJAX-URL falsch
- Backend: Post Type fehlt, ACF-Felder nicht registriert, FPDF fehlt, wp_mail() nicht konfiguriert

**Hinweis:** Zertifikat-Download-Button existiert bereits in `templates/liste.php` (Zeilen 100-104).

---

## Version 1.2.3 - Permissions Removed (2025-11-27)

### 🔓 Änderungen

#### Manager-Berechtigung entfernt
- **ACF User Meta Feld entfernt** - `vw_is_manager` nicht mehr vorhanden
- **`can_manage()` Funktion gelöscht** - Keine interne Berechtigungsprüfung mehr
- **Nur Login erforderlich** - Alle eingeloggten Benutzer haben Zugriff auf Manager
- **Berechtigung wird anderweitig vergeben** - z.B. via Seiten-Zugriffskontrolle, Mitgliedschafts-Plugin, etc.

### 🔧 Technische Änderungen

#### Backend:
- `can_manage()` Funktion komplett entfernt
- Alle AJAX-Handler prüfen nur noch `is_user_logged_in()`
- `webinar_manager_shortcode()` zeigt Manager für alle eingeloggten User
- ACF Feldgruppe `group_vw_user_meta` entfernt

#### Shortcode:
- `[vimeo_webinar_manager]` - Keine Berechtigungsprüfung mehr
- Nur Login-Status wird geprüft
- Zugriffskontrolle kann über WordPress-Seiten-Berechtigungen erfolgen

### 💡 Migration

**Von v1.2.2 zu v1.2.3:**
- Keine Aktion erforderlich
- Alte `vw_is_manager` User Meta bleiben in DB (werden ignoriert)
- Manager-Seite ist jetzt für alle eingeloggten Benutzer zugänglich

**Zugriffskontrolle einrichten:**
Nutzen Sie eine dieser Methoden:
1. **Seiten-Zugriff** - WordPress-Seite mit Manager-Shortcode auf "Privat" setzen
2. **Membership-Plugin** - z.B. Restrict Content Pro, MemberPress
3. **Capability Manager** - User Role Editor für spezifische Capabilities
4. **Custom Code** - Hook in `template_redirect` für eigene Logik

---

## Version 1.2.2 - Fortbildung Post Type & Email Notifications (2025-11-27)

### ✨ Neue Features

#### 1. Fortbildung Post Type Integration
- **Schreibt in Fortbildung Post Type** statt Repeater
- **Doubletten-Prüfung** - Keine doppelten Einträge für dasselbe Webinar
- **Automatische Felder:**
  - User, Date, Location (immer "Online"), Type (Webinar)
  - Points, VNR, Token (32 Zeichen)
  - Freigegeben (automatisch true), Freigabe durch ("System (Webinar)")
  - Freigabe Mail (Admin-E-Mail)
- **Meta-Speicherung:** `_vw_webinar_id` für Doubletten-Check

#### 2. Automatische E-Mail-Benachrichtigungen
- **E-Mail bei Completion** mit Zertifikat-Link
- **Konfigurierbare Einstellungen:**
  - E-Mail aktivieren/deaktivieren
  - Absender-Name und E-Mail
  - Betreff und Text anpassbar
- **Platzhalter-System:**
  - `{user_name}`, `{user_first_name}`, `{user_last_name}`
  - `{user_email}`, `{webinar_title}`, `{webinar_url}`
  - `{certificate_url}`, `{points}`, `{date}`
- HTML-Formatierung mit `nl2br()`

#### 3. Zertifikat-Download nach Completion
- **Download-Button** automatisch nach Abschluss sichtbar
- **Seiten-Reload** nach Completion zeigt Completed-Banner
- **Zertifikat-Generierung** direkt bei Completion

### 🔧 Technische Änderungen

#### Backend:
- `create_fortbildung_entry()` - Komplette Umstellung auf Post Type
- `ajax_complete_webinar()` - Generiert Zertifikat und verschickt E-Mail
- `send_certificate_email()` - Neue Funktion für E-Mail-Versand
- `render_admin_settings()` - E-Mail-Einstellungen hinzugefügt

#### Datenstruktur:
- Post Meta: `_vw_webinar_id` - Referenz für Doubletten-Check
- User Meta: `_vw_fortbildung_{webinar_id}` - Fortbildung Post ID
- Option: `vw_certificate_settings` erweitert um Mail-Settings

### 🐛 Bug Fixes
- Fortbildung wird korrekt als Post Type angelegt
- Keine doppelten Einträge mehr möglich
- Ort ist immer "Online" (wie gefordert)
- Zertifikat wird generiert und per E-Mail verschickt

---

## Version 1.2.1 - Certificate Template & Settings (2025-11-27)

### ✨ Neue Features

#### 1. Editierbares Zertifikat-Template
- **Globale Einstellungsseite** unter Webinare → Einstellungen
- **Anpassbare Elemente:**
  - Ausrichtung (Querformat/Hochformat)
  - Hintergrundbild (per Media Library)
  - Logo (oben links)
  - Kopfzeile (Hauptüberschrift)
  - Fußzeile (z.B. Organisationsname)
  - Unterschrift/Bestätigung
- **Media Library Integration** - Bilder direkt hochladen

#### 2. Fortbildungsliste-Integration
- **Automatischer Eintrag** in ACF Fortbildung Repeater bei Completion
- Speichert: Datum, Titel, Veranstalter, Ort, Punkte, VNR, Kategorie, Status
- Nachweis-URL zum Webinar wird gespeichert
- Direkt im User-Profil (user meta) gespeichert

#### 3. Optimierter Vimeo Player
- **Keine Vimeo-Einblendungen** außer Vollbild-Button
- Parameter: `title=0&byline=0&portrait=0&badge=0`
- Cleaner Player ohne Ablenkungen

### 🔧 Technische Änderungen

#### Backend:
- `create_fortbildung_entry()` - Schreibt direkt in ACF Repeater statt Post Type
- `generate_certificate_pdf()` - Nutzt globale Settings aus `vw_certificate_settings`
- `render_admin_settings()` - Neue Einstellungsseite mit Media Library
- Menu-Struktur: Einstellungen + Statistiken unter Webinare

#### Template:
- Vimeo iframe URL mit zusätzlichen Parametern

#### Datenstruktur:
- Option: `vw_certificate_settings` - Globale Zertifikat-Einstellungen
- User Meta: `fortbildung` Repeater - Fortbildungsliste

### 🐛 Bug Fixes
- Fortbildungsliste-Eintrag funktioniert jetzt korrekt
- Zertifikat wird generiert und ist herunterladbar
- Clean Vimeo Player ohne störende Elemente

---

## Version 1.2.0 - Anti-Skip Time Tracking (2025-11-27)

### 🎯 Kritische Verbesserung: Anti-Skip Tracking

**Problem behoben:** Benutzer konnten durch Vorspulen den erforderlichen Fortschritt erreichen, ohne das Video tatsächlich anzusehen.

### ✨ Neue Features

#### 1. Zeit-basiertes Tracking
- Misst **tatsächlich angesehene Zeit** in Sekunden
- Interval-basiert (1 Sekunde = 1 Sekunde Fortschritt)
- Vorspulen zählt **NICHT** als angesehene Zeit
- `seeked` Event Detection für Skip-Forward

#### 2. Öffentliche Webinare
- Webinare für **ALLE Benutzer** verfügbar (nicht mehr login-required)
- Nicht eingeloggte Benutzer können Videos ansehen
- Login-Hinweis mit Link: "Zum Eintrag in den Fortbildungsnachweis bitte einloggen"
- Nur eingeloggte Benutzer erhalten Fortbildungspunkte und Zertifikate

#### 3. Dual Storage System
- **Eingeloggt:** Fortschritt in Datenbank (User Meta `_vw_watched_time_{id}`)
- **Nicht eingeloggt:** Fortschritt in Cookies (30 Tage Gültigkeit)
- Nahtlose Synchronisation beim Login

#### 4. Performance-Optimierung
- Reduktion Server-Last um **~97%**
- AJAX-Calls: Von ~240/Min auf ~6/Min
- Speichern alle 10 Sekunden (statt bei jedem `timeupdate` Event)

### 🔧 Technische Änderungen

#### Backend (dgptm-vimeo-webinare.php)
- Neue Methode: `get_watched_time($user_id, $webinar_id)` - Abrufen der angesehenen Zeit
- Neue Methode: `get_video_duration($webinar_id)` - Gecachte Video-Dauer
- Neue Methode: `get_cookie_data($webinar_id)` - Cookie-Daten für nicht eingeloggte
- Geändert: `handle_webinar_page()` - Kein Login-Requirement mehr
- Geändert: `ajax_track_progress()` - Zeit-basiert statt positionsbasiert
- Geändert: `get_user_progress()` - Berechnet aus `watched_time / duration`

#### Frontend (assets/js/script.js)
- **Komplett umgeschrieben** für Interval-Tracking
- Neue Variablen: `sessionWatchedTime`, `watchedTime`, `trackingInterval`
- Neue Funktion: `startTracking()` - 1-Sekunden-Interval
- Neue Funktion: `stopTracking()` - Cleanup
- Neue Funktion: `saveToCookie()` - Cookie-Storage für nicht eingeloggte
- Event: `seeked` - Erkennt Vorspulen (logged, aber nicht gezählt)
- Event: `beforeunload` - Speichert vor Page-Exit

#### Template (templates/player.php)
- Neu: `.vw-login-notice` - Login-Hinweis für nicht eingeloggte
- Neu: Data-Attribut `data-watched-time` - Initiale angesehene Zeit
- Neu: Data-Attribut `data-user-logged-in` - Login-Status
- Geändert: Zeit-Display `MM:SS` statt Prozent
- Geändert: Fortschritts-Sektion nur für eingeloggte sichtbar

#### CSS (assets/css/style.css)
- Neu: `.vw-login-notice` - Blauer Info-Banner mit Icon
- Neu: `.vw-watched-time-display` - Styled für angesehene Zeit
- Neu: `.vw-separator` - Separator zwischen Zeit und Fortschritt

### 📊 Daten-Schema

#### User Meta (Eingeloggte):
```
_vw_watched_time_{webinar_id} = float (Sekunden)
Beispiel: _vw_watched_time_123 = 542.5 (= 9:02 Min)
```

#### Post Meta (Video-Dauer Cache):
```
_vw_video_duration = float (Sekunden)
Beispiel: 1800 (= 30 Min Video)
```

#### Cookie (Nicht eingeloggte):
```
vw_webinar_{webinar_id} = JSON
{
  "watched_time": 542.5,
  "progress": 30.14
}
Expires: 30 Tage
```

### 🔄 Migration

**Automatisch!** Keine Aktion erforderlich.
- Alte Daten (`_vw_progress_{id}`) bleiben erhalten
- Neue Daten (`_vw_watched_time_{id}`) werden parallel angelegt
- System nutzt beide Quellen

### 🐛 Bug Fixes

- **KRITISCH:** Vorspulen zählt nicht mehr als Fortschritt
- Performance: Server-Last um 97% reduziert
- UX: Klarer Hinweis für nicht eingeloggte Benutzer

---

## Version 1.1.0 - Dynamische URLs (2025-11-26)

### ✨ Neue Features

#### 1. Dynamische Webinar-Seiten
- Automatische Seiten unter `/wissen/webinar/{id}`
- Keine manuellen Seiten mehr erforderlich
- Custom Rewrite Rules
- Template Redirect Handler

#### 2. Flexible URL-Formate
- Pretty URLs: `/wissen/webinar/123`
- Query URLs: `/wissen/webinar?id=123`
- Beide Formate unterstützt

#### 3. Shortcode bleibt verfügbar
- `[vimeo_webinar id="123"]` für flexible Einbindung
- Kann auf beliebigen Seiten verwendet werden

### 🔧 Technische Änderungen

#### Backend:
- Neu: `register_rewrite_rules()` - Custom URL Routing
- Neu: `add_query_vars()` - Query Variable Support
- Neu: `handle_webinar_page()` - Template Redirect Handler
- Neu: `render_dynamic_template()` - Dynamisches Rendering

#### Templates:
- Alle Links geändert zu: `home_url('/wissen/webinar/' . $id)`
- Keine Shortcode-URLs mehr in Liste/Manager

### 📝 Dokumentation

- Neu: `UPDATE-V1.1.md` - Update-Guide
- Neu: `QUICKSTART-V1.1.md` - Quick Start Guide
- Aktualisiert: `README.md` - Neue URL-Struktur

### 🔄 Migration

**Erforderlich:** Permalink Flush
1. WordPress Admin → Einstellungen → Permalinks
2. "Änderungen speichern" klicken
3. Fertig!

---

## Version 1.0.1 - Bug Fixes (2025-11-25)

### 🐛 Bug Fixes

- Fix: Webinar wird nicht auf Player-Seite angezeigt
- Verbessert: Error Messages mit Details
- Hinzugefügt: `force_enqueue_assets()` für CSS/JS
- Hinzugefügt: Validierung für ACF-Felder

### 📝 Dokumentation

- Neu: `DEBUGGING.md` - Troubleshooting Guide
- Aktualisiert: `README.md` - Error Handling

---

## Version 1.0.0 - Initial Release (2025-11-24)

### ✨ Features

#### 1. Vimeo Video Player
- Vimeo Player API Integration
- Responsive 16:9 Player
- Auto-play Support
- Fullscreen Support

#### 2. Fortschritts-Tracking
- Position-basierte Verfolgung (v1.0-1.1)
- Prozentuale Fortschrittsanzeige
- Completion-Percentage konfigurierbar
- User Meta Storage

#### 3. Fortbildungspunkte (EBCP)
- Automatischer Eintrag bei Completion
- Integration mit ACF Fortbildung-Liste
- Konfigurierbare Punktzahl
- VNR Support

#### 4. Zertifikate
- FPDF-basierte PDF-Generierung
- Anpassbare Hintergrund-Grafik
- Wasserzeichen-Support
- Download-Button nach Completion

#### 5. Frontend Manager
- CRUD-Interface für Manager
- ACF-basierte Berechtigungen
- User Meta: `vw_manager = true/false`
- Modal-basierte Formulare
- Statistiken pro Webinar

#### 6. Webinar-Liste
- Grid-Layout mit Cards
- Status-Badges (Neu/In Bearbeitung/Abgeschlossen)
- Progress-Bars
- Such- und Filter-Funktion
- Responsive Design

### 🔧 Technische Basis

#### Custom Post Type:
```php
'vimeo_webinar' (öffentlich, kein UI)
```

#### ACF Fields (Webinar):
- `vimeo_id` (Text) - Vimeo Video ID
- `ebcp_points` (Number) - Fortbildungspunkte
- `vnr` (Text) - Vereinheitlichte Registrierungsnummer
- `completion_percentage` (Number) - Erforderlicher Fortschritt

#### ACF Fields (User Meta):
- `vw_manager` (True/False) - Manager-Berechtigung
- `fortbildung` (Repeater) - Fortbildungsliste

#### Shortcodes:
- `[vimeo_webinar id="123"]` - Einzelner Player
- `[vimeo_webinar_liste]` - Webinar-Grid
- `[vimeo_webinar_manager]` - Manager-Interface

### 📝 Dokumentation

- `README.md` - Vollständige Feature-Dokumentation
- `INSTALLATION.md` - Schritt-für-Schritt Setup
- `STRUCTURE.md` - Code-Struktur für Entwickler

### 🎨 Assets

- `assets/css/style.css` - Responsive Frontend Styles
- `assets/js/script.js` - Vimeo Player + AJAX Logic
- Vimeo Player SDK (CDN)

### 🔐 Sicherheit

- Nonce Verification auf allen AJAX Calls
- Capability Checks (`manage_options` für Manager)
- Input Sanitization
- ABSPATH Checks

---

## Upgrade-Pfad

### 1.0.0 → 1.0.1
- Automatisch, keine Aktion erforderlich

### 1.0.1 → 1.1.0
- **Erforderlich:** Permalink Flush (Einstellungen → Permalinks → Speichern)

### 1.1.0 → 1.2.0
- Automatisch, keine Aktion erforderlich
- Alte Fortschrittsdaten bleiben erhalten
- Neue Tracking-Methode wird parallel aufgebaut

---

## Support

**Dokumentation:**
- `README.md` - Features & Verwendung
- `INSTALLATION.md` - Setup-Anleitung
- `QUICKSTART-V1.1.md` - 3-Minuten-Start
- `DEBUGGING.md` - Problemlösung
- `UPDATE-V1.1.md` - Dynamische URLs
- `UPDATE-V1.2.md` - Zeit-Tracking

**DGPTM Support:**
Kontakt mit:
- WordPress Version
- PHP Version
- Browser + Version
- Fehlermeldung/Screenshot
