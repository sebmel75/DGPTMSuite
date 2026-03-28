# Vimeo Webinare - Anleitung

## Ueberblick

Das Modul **Vimeo Webinare** stellt ein Webinar-System bereit, das Vimeo-Videos als Fortbildungs-Webinare mit automatischer Fortschrittsverfolgung, Abschlusserkennung und PDF-Zertifikaten anbietet. Es registriert einen eigenen Custom Post Type (`vimeo_webinar`), erzeugt dynamische URLs (`/webinar/{id}`), trackt die tatsaechlich angesehene Zeit (nicht die Video-Position) und erstellt bei Abschluss automatisch einen Fortbildungseintrag mit Zertifikat.

## Voraussetzungen

- **Advanced Custom Fields (ACF)** Plugin muss installiert und aktiv sein
- **FPDF-Bibliothek** muss unter `libraries/fpdf/fpdf.php` vorhanden sein (fuer PDF-Zertifikate)
- Empfohlen: Modul **Fortbildung** fuer die vollstaendige Integration der Fortbildungseintraege
- PHP 7.4+, WordPress 5.8+

## Installation & Aktivierung

1. Modul im DGPTM Suite Dashboard aktivieren.
2. Sicherstellen, dass ACF aktiv ist.
3. Nach der Aktivierung **Einstellungen > Permalinks** aufrufen und speichern (Rewrite Rules flushen).
4. Optional: Zertifikat-Einstellungen unter dem Admin-Menue konfigurieren.

## Konfiguration

### Webinar-Einstellungen (Global)

Die globalen Zertifikat-Einstellungen werden unter der WordPress-Option `vw_certificate_settings` gespeichert. Folgende Werte sind konfigurierbar:

| Einstellung | Beschreibung |
|---|---|
| `orientation` | PDF-Ausrichtung: `L` (Querformat) oder `P` (Hochformat) |
| `background_image` | Attachment-ID fuer ein Hintergrundbild |
| `logo_image` | Attachment-ID fuer ein Logo |
| `header_text` | Ueberschrift auf dem Zertifikat (Standard: "Teilnahmebescheinigung") |
| `footer_text` | Fusszeile auf dem Zertifikat |
| `signature_text` | Optionaler Signatur-Text |
| `mail_enabled` | E-Mail-Versand aktivieren (true/false) |
| `mail_subject` | Betreff-Vorlage (Platzhalter: `{webinar_title}`) |
| `mail_body` | E-Mail-Text-Vorlage (Platzhalter siehe unten) |
| `mail_from` | Absender-E-Mail |
| `mail_from_name` | Absender-Name |

**E-Mail-Platzhalter:** `{user_name}`, `{user_first_name}`, `{user_last_name}`, `{user_email}`, `{webinar_title}`, `{webinar_url}`, `{certificate_url}`, `{points}`, `{date}`

### ACF-Felder pro Webinar

Beim Bearbeiten eines Webinars stehen folgende Felder zur Verfuegung:

| Feld | Beschreibung | Pflicht |
|---|---|---|
| **Vimeo Video ID** | Die numerische Vimeo-Video-ID (z.B. `123456789`) | Ja |
| **Erforderlicher Fortschritt (%)** | Prozentsatz des Videos, der angesehen werden muss (Standard: 90%) | Ja |
| **Fortbildungspunkte (EBCP)** | Anzahl der EBCP-Punkte bei Abschluss (Standard: 1, Schrittweite 0.5) | Ja |
| **VNR** | Veranstaltungsnummer (optional) | Nein |
| **Art der Fortbildung** | Typ-Bezeichnung (Standard: "Webinar") | Nein |
| **Ort** | Veranstaltungsort (Standard: "Online") | Nein |
| **Zertifikat Hintergrundbild** | Optional: PNG/JPG als Zertifikat-Hintergrund | Nein |
| **Zertifikat Wasserzeichen** | Optional: PNG als Wasserzeichen | Nein |

## Benutzung

### Webinar erstellen (Backend)

1. Im WordPress-Admin unter **Webinare > Neues Webinar** einen neuen Beitrag anlegen.
2. Titel und Beschreibung eingeben.
3. Die **Vimeo Video ID** eintragen (die Zahl aus der Vimeo-URL, z.B. `https://vimeo.com/123456789` --> `123456789`).
4. Fortbildungspunkte und ggf. VNR festlegen.
5. Beitrag veroeffentlichen.

### Webinar erstellen (Frontend-Manager)

Berechtigte Benutzer koennen ueber den Shortcode `[vimeo_webinar_manager]` Webinare im Frontend erstellen, bearbeiten und loeschen.

### Webinar aufrufen

Webinare sind ueber zwei URL-Formate erreichbar:

- **Saubere URL:** `https://ihre-domain.de/webinar/{post-id}`
- **Query-String:** `https://ihre-domain.de/webinar?id={post-id}`

### Anti-Skip-Mechanismus (zeitbasiertes Tracking)

Das Modul verwendet **zeitbasiertes Tracking** statt positionsbasiertem Tracking. Das bedeutet:

- Gemessen wird die **tatsaechlich angesehene Zeit**, nicht die aktuelle Position im Video.
- Vorspulen zaehlt nicht als angesehene Zeit.
- Der Fortschritt berechnet sich als: `(angesehene Zeit / Gesamtdauer) * 100`.
- Erst wenn der konfigurierte Fortschritt erreicht ist (Standard: 90%), gilt das Webinar als abgeschlossen.
- Fuer eingeloggte Benutzer wird der Fortschritt in der Datenbank gespeichert (User-Meta).
- Nicht eingeloggte Benutzer erhalten eine Cookie-basierte Fortschrittsspeicherung (ohne Fortbildungseintrag).

### Abschluss und Zertifikat

Wenn ein eingeloggter Benutzer den erforderlichen Fortschritt erreicht:

1. Es wird automatisch ein **Fortbildungseintrag** (CPT `fortbildung`) erstellt mit:
   - Benutzer-Zuordnung
   - Datum (aktuelles Datum)
   - Ort: "Online"
   - Typ: "Webinar"
   - Konfigurierte EBCP-Punkte und VNR
   - Status: automatisch freigegeben
2. Ein **PDF-Zertifikat** wird generiert und in der Mediathek gespeichert.
3. Das Zertifikat wird als Attachment am Fortbildungseintrag angehaengt.
4. Eine **E-Mail** mit dem Zertifikat-Link wird an den Benutzer gesendet (wenn aktiviert).
5. Ein Doubletten-Check verhindert doppelte Eintraege fuer dasselbe Webinar und denselben Benutzer.

### Shortcodes

| Shortcode | Parameter | Beschreibung |
|---|---|---|
| `[vimeo_webinar id="123"]` | `id` = Post-ID des Webinars | Bettet den Vimeo-Player mit Fortschrittsanzeige ein |
| `[vimeo_webinar_liste]` | keine | Zeigt eine Liste aller veroeffentlichten Webinare mit Fortschritt pro Benutzer |
| `[vimeo_webinar_manager]` | keine | Frontend-Manager zum Erstellen, Bearbeiten und Loeschen von Webinaren |

**Hinweis:** Alle Shortcodes erfordern einen eingeloggten Benutzer.

## Fehlerbehebung

| Problem | Loesung |
|---|---|
| "Webinar nicht gefunden" (404) | Permalinks unter Einstellungen > Permalinks neu speichern |
| "Vimeo Video ID fehlt" | Im Webinar-Beitrag das ACF-Feld "Vimeo Video ID" ausfuellen |
| Fortschritt wird nicht gespeichert | Pruefen, ob der Benutzer eingeloggt ist; AJAX-URL und Nonce in der Browser-Konsole pruefen |
| Zertifikat wird nicht generiert | FPDF-Bibliothek unter `libraries/fpdf/fpdf.php` pruefen; Schreibrechte fuer `wp-content/uploads/webinar-certificates/` sicherstellen |
| Fortbildungseintrag fehlt | ACF muss aktiv sein; CPT `fortbildung` muss registriert sein (Modul Fortbildung oder anderes Modul) |
| E-Mail kommt nicht an | `vw_certificate_settings` > `mail_enabled` pruefen; WordPress-E-Mail-Versand testen |
| Video laesst sich nicht abspielen | Vimeo-Video muss im Vimeo-Account eingebettet sein (Embed-Einstellungen pruefen) |

## Technische Details

- **Custom Post Type:** `vimeo_webinar`
- **Rewrite Rules:** `^webinar/([0-9]+)/?$` --> `index.php?vw_webinar_id=$matches[1]`
- **Query Vars:** `vw_webinar_id`, `vw_webinar_page`
- **User-Meta-Keys:**
  - `_vw_watched_time_{webinar_id}` -- angesehene Zeit in Sekunden
  - `_vw_completed_{webinar_id}` -- Abschluss-Flag (boolean)
  - `_vw_completed_{webinar_id}_date` -- Abschluss-Datum
  - `_vw_fortbildung_{webinar_id}` -- zugehoerige Fortbildungs-Post-ID
- **Post-Meta-Keys:**
  - `_vw_video_duration` -- gecachte Video-Dauer in Sekunden
  - `_vw_webinar_id` -- Referenz auf den Fortbildungseintrag (Doubletten-Check)
- **AJAX-Actions:**
  - `vw_track_progress` -- Fortschritt speichern
  - `vw_complete_webinar` -- Webinar abschliessen
  - `vw_generate_certificate` -- Zertifikat nachtraeglich generieren
  - `vw_manager_create`, `vw_manager_update`, `vw_manager_delete`, `vw_manager_stats` -- Frontend-Manager
- **Hooks:**
  - `dgptm_fortbildung_created` -- wird nach Erstellen eines Fortbildungseintrags ausgeloest (Parameter: `$fortbildung_id`, `$user_id`, `$webinar_id`)
- **JS/CSS Assets:** `assets/js/script.js`, `assets/css/style.css`, Vimeo Player API (`player.vimeo.com/api/player.js`)
- **Templates:** `templates/player.php`, `templates/liste.php`, `templates/manager.php`
- **PDF-Speicherort:** `wp-content/uploads/webinar-certificates/`
