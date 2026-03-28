# DGPTM - Vimeo Webinare

**Version:** 1.2.1 - Certificate Template & Settings
**Autor:** Sebastian Melzer
**Kategorie:** Media

## 📋 Übersicht

Das Vimeo Webinare Modul ermöglicht es, Vimeo-Videos als Webinare mit **anti-skip Zeit-Tracking**, automatischer Fortbildungspunkte-Vergabe, **editierbaren Zertifikaten** und vollständigem Frontend-Manager anzubieten.

**Neu in v1.2.1:**
- ✅ **Editierbare Zertifikat-Templates** - Hintergrund, Logo, Texte anpassbar
- ✅ **Einstellungsseite** unter Webinare → Einstellungen
- ✅ **Fortbildungsliste-Integration** - Automatischer Eintrag bei Completion
- ✅ **Optimierter Vimeo Player** - Keine Vimeo-Einblendungen außer Vollbild

**v1.2.0:** Benutzer können **nicht mehr durch Vorspulen** den Fortschritt erreichen. Das System misst die **tatsächlich angesehene Zeit** und Webinare sind jetzt **für alle Benutzer** verfügbar (nicht nur eingeloggte).

## ✨ Features

### Für Teilnehmer
- ✅ **Anti-Skip Tracking** (v1.2.0) - Vorspulen zählt NICHT als Fortschritt
- ✅ **Zeit-basiertes Tracking** (v1.2.0) - Misst tatsächlich angesehene Zeit in Sekunden
- ✅ **Öffentlich verfügbar** (v1.2.0) - Webinare für alle, Login für Fortbildungspunkte
- ✅ **Cookie Support** (v1.2.0) - Fortschritt auch ohne Login gespeichert
- ✅ **Vimeo Player Integration** - Nahtlose Einbindung von Vimeo Videos
- ✅ **Fortschritts-Tracking** - Automatische Verfolgung des Anschaufortschritts
- ✅ **Automatische Fortbildungspunkte** - Bei Erreichen des erforderlichen Fortschritts (nur eingeloggt)
- ✅ **PDF-Zertifikate** - Automatische Generierung mit FPDF (nur eingeloggt)
- ✅ **Anpassbare Zertifikate** - Hintergrundbild und Wasserzeichen
- ✅ **Fortbildungsliste** - Integration in bestehende Fortbildung-Struktur
- ✅ **Responsive Design** - Optimiert für Desktop und Mobile

### Für Manager/Administratoren
- ✅ **Frontend-Manager** - Webinare ohne Backend verwalten
- ✅ **CRUD-Operationen** - Erstellen, Bearbeiten, Löschen
- ✅ **Statistiken** - Detaillierte Auswertungen pro Webinar
- ✅ **Berechtigungssteuerung** - ACF-basiertes Zugriffskontrolle
- ✅ **Batch-Übersicht** - Alle Webinare auf einen Blick

## 🚀 Installation

### Voraussetzungen
- WordPress 5.8+
- PHP 7.4+
- Advanced Custom Fields (ACF) Plugin
- DGPTM Plugin Suite aktiviert
- Fortbildung-Modul (für automatische Einträge)

### Installation
1. Modul ist bereits im Plugin Suite enthalten
2. Aktivieren Sie das Modul über DGPTM Suite → Dashboard
3. ACF-Feldgruppen werden automatisch registriert
4. Custom Post Type "vimeo_webinar" wird erstellt

## 📖 Verwendung

### 1. Webinar erstellen

#### Via WordPress Backend
1. Gehen Sie zu **Webinare → Neu hinzufügen**
2. Titel eingeben
3. Beschreibung hinzufügen
4. **Webinar Einstellungen** konfigurieren:
   - Vimeo Video ID (z.B. 123456789)
   - Erforderlicher Fortschritt (z.B. 90%)
   - EBCP Fortbildungspunkte (z.B. 2.5)
   - VNR (optional)
   - Art der Fortbildung (z.B. "Webinar")
   - Ort (z.B. "Online")
   - Zertifikat Hintergrundbild (optional)
   - Zertifikat Wasserzeichen (optional)
5. Veröffentlichen

#### Via Frontend Manager
1. Benutzer benötigt ACF User Meta: `vw_is_manager = true`
2. Seite mit Shortcode `[vimeo_webinar_manager]` aufrufen
3. Button "Neues Webinar erstellen" klicken
4. Formular ausfüllen
5. Speichern

### 2. Webinar anzeigen

#### Einzelnes Webinar
```
[vimeo_webinar id="123"]
```
Wobei `123` die Post-ID des Webinars ist.

**Funktionalität:**
- Zeigt Vimeo Player
- Fortschrittsbalken mit Live-Update
- Automatische Speicherung alle 5% Fortschritt
- Bei Erreichen des erforderlichen Fortschritts:
  - Automatischer Fortbildungseintrag
  - Benachrichtigung für Benutzer
  - Zertifikat-Download-Button

#### Webinar-Liste
```
[vimeo_webinar_liste]
```

**Funktionalität:**
- Grid-Ansicht aller verfügbaren Webinare
- Status-Anzeige (Nicht begonnen, In Bearbeitung, Abgeschlossen)
- Fortschrittsbalken für begonnene Webinare
- Suchfunktion
- Filter nach Status
- Vimeo Thumbnail-Vorschau

### 3. Frontend Manager

```
[vimeo_webinar_manager]
```

**Zugriff:**
- Benutzer mit ACF User Meta `vw_is_manager = true` ODER
- Administratoren (`manage_options`)

**Funktionen:**
- **Liste-Tab:**
  - Alle Webinare in Tabellenansicht
  - Suchfunktion
  - Bearbeiten-Button
  - Löschen-Button (mit Bestätigung)
  - Statistik-Button
  - Link zum Webinar

- **Statistik-Tab:**
  - Gesamtanzahl Webinare
  - Gesamt Abschlüsse
  - Gesamt in Bearbeitung
  - Performance-Tabelle pro Webinar
  - Completion Rate

### 4. Manager-Berechtigung vergeben

1. WordPress Backend → **Benutzer → Bearbeiten**
2. Scrollen zu **Webinar Manager Berechtigung**
3. Checkbox "Webinar Manager" aktivieren
4. Benutzer speichern

## 🎨 Zertifikat-Anpassung

### Hintergrundbild
- Format: PNG oder JPG
- Empfohlene Größe: 297x210mm (A4 Querformat)
- Wird als vollflächiger Hintergrund verwendet

### Wasserzeichen
- Format: PNG (mit Transparenz empfohlen)
- Wird mittig platziert mit 30% Deckkraft
- Empfohlene Größe: ca. 100mm breit

### Zertifikat-Inhalt
Das automatisch generierte Zertifikat enthält:
- "Teilnahmebescheinigung" als Titel
- Webinar-Titel
- Teilnehmer-Name
- Fortbildungspunkte (EBCP)
- Datum des Abschlusses
- Optional: Hintergrundbild
- Optional: Wasserzeichen

## 🔧 Technische Details

### Fortschritts-Tracking

Das Modul nutzt die **Vimeo Player API** für präzises Tracking:

```javascript
player.on('timeupdate', function(data) {
    // Berechnung: (aktuelle Sekunde / Gesamtdauer) * 100
    currentProgress = (data.seconds / duration) * 100;

    // Speicherung alle 5%
    if (Math.abs(currentProgress - lastSavedProgress) >= 5) {
        saveProgress(webinarId, currentProgress);
    }

    // Prüfung auf Abschluss
    if (currentProgress >= completionRequired) {
        completeWebinar(webinarId);
    }
});
```

### Datenspeicherung

#### User Meta
- `_vw_progress_{webinar_id}` - Fortschritt in % (Float)
- `_vw_completed_{webinar_id}` - Abgeschlossen? (Boolean)
- `_vw_fortbildung_{webinar_id}` - Fortbildung Post ID (Integer)

#### ACF Fields (Webinar)
- `vimeo_id` - Vimeo Video ID
- `completion_percentage` - Erforderlicher Fortschritt (%)
- `ebcp_points` - Fortbildungspunkte
- `vnr` - VNR
- `fortbildung_type` - Art der Fortbildung
- `location` - Ort
- `certificate_background` - Zertifikat Hintergrundbild
- `certificate_watermark` - Zertifikat Wasserzeichen

#### ACF Fields (User)
- `vw_is_manager` - Manager-Berechtigung (Boolean)

### AJAX Endpoints

**Teilnehmer:**
- `vw_track_progress` - Fortschritt speichern
- `vw_complete_webinar` - Webinar abschließen + Fortbildung erstellen
- `vw_generate_certificate` - PDF-Zertifikat generieren

**Manager:**
- `vw_manager_create` - Webinar erstellen
- `vw_manager_update` - Webinar aktualisieren
- `vw_manager_delete` - Webinar löschen
- `vw_manager_stats` - Statistiken abrufen

### Integration mit Fortbildung-Modul

Bei Webinar-Abschluss wird automatisch ein Fortbildungseintrag erstellt:

```php
wp_insert_post([
    'post_type' => 'fortbildung',
    'post_title' => $webinar->post_title,
    'post_status' => 'publish',
    'post_author' => $user_id,
]);

// ACF Fields
update_field('user', $user_id, $fortbildung_id);
update_field('date', current_time('Y-m-d'), $fortbildung_id);
update_field('points', $points, $fortbildung_id);
update_field('vnr', $vnr, $fortbildung_id);
update_field('freigegeben', true, $fortbildung_id);
update_field('freigabe_durch', 'System (Webinar)', $fortbildung_id);
```

## 📊 Statistiken

### Webinar-Statistiken
Für jedes Webinar werden folgende Metriken erfasst:
- **Abgeschlossen** - Anzahl Benutzer, die das Webinar abgeschlossen haben
- **In Bearbeitung** - Anzahl Benutzer mit Fortschritt > 0% und < 100%
- **Gesamt Ansichten** - Abgeschlossen + In Bearbeitung
- **Completion Rate** - Abgeschlossen / Gesamt Ansichten * 100

### Admin-Statistikseite
WordPress Backend → **Webinare → Statistiken**

Zeigt alle Webinare mit:
- Titel
- Anzahl Abschlüsse
- Anzahl in Bearbeitung
- Gesamt Ansichten

## 🎯 Anwendungsfälle

### Use Case 1: Online-Fortbildung
1. Administrator lädt Fortbildungsvideo auf Vimeo hoch
2. Webinar wird erstellt mit 2.5 EBCP Punkten
3. Erforderlicher Fortschritt: 90%
4. Teilnehmer schauen Video
5. Bei 90% Fortschritt: Automatische Fortbildungspunkte
6. Teilnehmer laden Zertifikat herunter

### Use Case 2: Webinar-Serie verwalten
1. Manager erhält Berechtigung (`vw_is_manager = true`)
2. Manager öffnet Frontend-Manager
3. Erstellt 10 Webinare für eine Serie
4. Überwacht Statistiken
5. Passt Webinare bei Bedarf an

### Use Case 3: Teilnehmer-Übersicht
1. Teilnehmer ruft Webinar-Liste auf
2. Sieht alle verfügbaren Webinare
3. Filtert nach Status "In Bearbeitung"
4. Setzt begonnenes Webinar fort
5. Schließt ab und lädt Zertifikat

## ⚙️ Konfiguration

### Standard-Werte
- **Erforderlicher Fortschritt:** 90%
- **Fortbildungspunkte:** 1.0 EBCP
- **Art:** "Webinar"
- **Ort:** "Online"

### Anpassbare Elemente
- Vimeo Video ID
- Erforderlicher Fortschritt (1-100%)
- Fortbildungspunkte (0+, Schritte: 0.5)
- VNR
- Art der Fortbildung
- Ort
- Zertifikat-Hintergrundbild
- Zertifikat-Wasserzeichen

## 🔒 Sicherheit

- ✅ Nonce-Verifizierung bei allen AJAX-Calls
- ✅ Berechtigungsprüfung (logged in, Manager-Rolle)
- ✅ Input-Sanitization (sanitize_text_field, wp_kses_post)
- ✅ Prepared SQL-Statements
- ✅ ABSPATH-Check in allen Dateien

## 🐛 Troubleshooting

### Webinar wird nicht abgeschlossen
**Problem:** Fortschritt wird getrackt, aber kein Abschluss

**Lösung:**
1. Prüfen Sie den erforderlichen Fortschritt
2. Browser-Konsole öffnen und nach Fehlern suchen
3. WordPress Debug Log prüfen
4. Vimeo Player API geladen? (F12 → Network → player.js)

### Zertifikat zeigt Umlaute falsch
**Problem:** Ä, Ö, Ü werden als "?" angezeigt

**Lösung:**
- FPDF nutzt ISO-8859-1
- Funktion `pdf_text()` konvertiert automatisch
- Prüfen Sie, ob `iconv` Extension aktiviert ist

### Manager-Berechtigung funktioniert nicht
**Problem:** Benutzer sieht Manager nicht, obwohl Checkbox aktiviert

**Lösung:**
1. ACF User Meta prüfen: `get_field('vw_is_manager', 'user_' . $user_id)`
2. Cache leeren (falls Object Cache aktiv)
3. Logout/Login des Benutzers

### Fortbildungseintrag wird nicht erstellt
**Problem:** Webinar abgeschlossen, aber kein Fortbildungseintrag

**Lösung:**
1. Prüfen Sie, ob Fortbildung Post Type existiert
2. ACF Fields für Fortbildung vorhanden?
3. WordPress Debug Log prüfen
4. User Meta `_vw_completed_{id}` prüfen

## 📝 Changelog

### Version 1.0.0 (2025-11-27)
- Initial Release
- Vimeo Player Integration mit Fortschritts-Tracking
- Automatische Fortbildungseinträge
- PDF-Zertifikat-Generierung mit FPDF
- Frontend-Manager mit CRUD-Funktionen
- Statistik-Dashboard
- ACF-Integration
- Responsive Design

## 🔗 Abhängigkeiten

### WordPress Plugins
- **Advanced Custom Fields** (erforderlich)

### DGPTM Module
- Keine direkten Abhängigkeiten
- Optional: **Fortbildung-Modul** (für automatische Einträge)

### JavaScript Libraries
- Vimeo Player API (https://player.vimeo.com/api/player.js)
- jQuery (WordPress Core)

### PHP Libraries
- FPDF (DGPTM_SUITE_PATH/libraries/fpdf/fpdf.php)

## 💡 Best Practices

1. **Vimeo-Videos:**
   - Verwenden Sie die Vimeo-ID, nicht die vollständige URL
   - Aktivieren Sie Embedding in Vimeo-Einstellungen
   - Verwenden Sie hochwertige Videos (mind. 720p)

2. **Fortschritt-Tracking:**
   - Setzen Sie realistische Completion-Werte (80-95%)
   - Zu hohe Werte frustrieren Teilnehmer
   - Zu niedrige Werte mindern Lerneffekt

3. **Zertifikate:**
   - Verwenden Sie professionelle Designs
   - Hintergrundbild in Druckqualität (300 DPI)
   - Wasserzeichen dezent einsetzen

4. **Manager-Berechtigung:**
   - Vergeben Sie diese nur an vertrauenswürdige Benutzer
   - Administratoren haben automatisch Zugriff
   - Nutzen Sie für große Teams separate Manager-Accounts

## 🎓 Support

Bei Fragen oder Problemen:
1. Prüfen Sie die Dokumentation
2. Checken Sie WordPress Debug Log
3. Kontaktieren Sie DGPTM Support

---

**Entwickelt für DGPTM e.V.**
**Made with ❤️ by Sebastian Melzer**
