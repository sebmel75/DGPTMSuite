# DGPTM Abstimmen-Addon - Benutzerhandbuch

**Version:** 4.0.0
**Letzte Aktualisierung:** Dezember 2024
**Kategorie:** Business Module

---

## 📋 Inhaltsverzeichnis

1. [Übersicht](#übersicht)
2. [Features](#features)
3. [Installation & Aktivierung](#installation--aktivierung)
4. [Systemanforderungen](#systemanforderungen)
5. [Erste Schritte](#erste-schritte)
6. [Voting-System](#voting-system)
7. [Zoom-Integration](#zoom-integration)
8. [Anwesenheitserfassung](#anwesenheitserfassung)
9. [Präsenz-Scanner](#präsenz-scanner)
10. [Beamer-Ansicht](#beamer-ansicht)
11. [Shortcodes](#shortcodes)
12. [Einstellungen](#einstellungen)
13. [Troubleshooting](#troubleshooting)

---

## Übersicht

Das **DGPTM Abstimmen-Addon** ist ein umfassendes System für Online-Abstimmungen, Teilnehmerverwaltung und Anwesenheitserfassung. Es kombiniert mehrere Funktionsbereiche:

- **Voting-System**: Umfragen mit Multi-Choice-Fragen erstellen und verwalten
- **Zoom-Integration**: Automatische Meeting/Webinar-Registrierung mit S2S OAuth
- **Anwesenheitserfassung**: Live-Tracking via Zoom-Webhook
- **Präsenz-Scanner**: QR-Code-basierte Einlass-Kontrolle
- **Beamer-Ansicht**: Live-Ergebnisse für Projektion

### Typische Anwendungsfälle

- **Mitgliederversammlungen**: Online-Abstimmungen mit Teilnehmerverwaltung
- **Webinare**: Automatische Zoom-Registrierung und Anwesenheitstracking
- **Konferenzen**: Präsenz-Erfassung via QR-Code/Badge-Scanner
- **Live-Events**: Beamer-Darstellung von Umfrageergebnissen in Echtzeit

---

## Features

### ✅ Voting-System
- Umfragen mit unbegrenzt vielen Fragen erstellen
- Single-Choice und Multi-Choice Abstimmungen
- Token-basierte Teilnehmerverwaltung
- QR-Code-Generierung für mobile Teilnahme
- Live-Beamer-Ansicht mit verschiedenen Diagrammtypen
- CSV/PDF Export von Ergebnissen
- E-Mail-Einladungen mit persönlichen Links

### ✅ Zoom-Integration
- Server-to-Server (S2S) OAuth 2.0 Authentifizierung
- Automatische Meeting/Webinar-Registrierung
- Persönliche Join-URLs pro Teilnehmer
- Status-Synchronisation (approved/pending/denied)
- Massenabgleich: Lokale Berechtigungen ↔ Zoom-Registrierungen
- Webhook für Live-Anwesenheit
- Debug-Log für API-Calls (umschaltbar)

### ✅ Anwesenheitserfassung
- Live-Tracking via Zoom-Webhook
- Join/Leave-Events in Echtzeit
- Sessiondauer-Berechnung
- Manuelle Einträge via Namenssuche
- Zoho CRM Integration für Teilnehmerdaten
- Export als CSV/PDF
- Live-Status-Anzeige

### ✅ Präsenz-Scanner
- QR-Code/Badge-Scanner für physische Events
- Manuelle Namenssuche (Modal mit Doppelklick-Auswahl)
- Automatische Übernahme von Mitgliedsart aus Zoho
- Markierung manueller vs. gescannter Einträge
- Live-Liste der letzten Einträge
- Integration mit Zoom-Anwesenheitsliste

---

## Installation & Aktivierung

### Voraussetzungen

Das Modul benötigt folgende DGPTM-Module:
- **webhook-trigger** (erforderlich)
- **crm-abruf** (optional, für Zoho CRM Integration)

### Aktivierung

1. DGPTM Suite Dashboard öffnen
2. Modul **abstimmen-addon** suchen
3. Abhängigkeiten prüfen (webhook-trigger muss aktiv sein)
4. Toggle-Schalter aktivieren
5. Einstellungen konfigurieren (siehe [Einstellungen](#einstellungen))

---

## Systemanforderungen

- **PHP:** 7.4 oder höher
- **WordPress:** 5.8 oder höher
- **MySQL:** 5.7+ oder MariaDB 10.2+
- **PHP Extensions:**
  - `json` (für API-Kommunikation)
  - `curl` (für HTTP-Requests)
  - `gd` oder `imagick` (optional, für QR-Code-Generierung)

### Datenbank-Tabellen

Das Modul erstellt bei Aktivierung folgende Tabellen:

```sql
wp_dgptm_abstimmung_polls          -- Umfragen
wp_dgptm_abstimmung_poll_questions -- Fragen pro Umfrage
wp_dgptm_abstimmung_participants   -- Teilnehmer mit Token
wp_dgptm_abstimmung_votes          -- Abgegebene Stimmen
```

---

## Erste Schritte

### Schritt 1: Manager-Rechte vergeben

Um Umfragen zu verwalten, benötigen Benutzer entweder:
- Die WordPress-Berechtigung `manage_options` (Administrator)
- ODER das User-Meta `toggle_abstimmungsmanager = 1`

**Manager-Rechte setzen:**

1. WordPress Admin → Benutzer → Benutzer bearbeiten
2. Zum Abschnitt "Abstimmungsmanager" scrollen
3. Toggle aktivieren: "Ist Abstimmungsmanager?"
4. Speichern

### Schritt 2: Erste Umfrage erstellen

1. Seite mit Shortcode `[manage_poll]` erstellen
2. Als Manager einloggen und Seite aufrufen
3. Button "Neue Umfrage anlegen" klicken
4. Umfragenamen eingeben
5. Optional: Logo-URL angeben (JPG/PNG)
6. "Umfrage anlegen" klicken

### Schritt 3: Fragen hinzufügen

1. In der Umfrageliste auf "Details" klicken
2. "Neue Frage hinzufügen" klicken
3. Frage formulieren
4. Antwortoptionen eingeben (eine pro Zeile)
5. Max. Stimmen festlegen (1 = Single-Choice, >1 = Multi-Choice)
6. "Frage speichern" klicken

### Schritt 4: Umfrage aktivieren

1. Toggle-Schalter bei "Aktiv/Archiv?" aktivieren
2. Frage aktivieren (Toggle bei gewünschter Frage)
3. Teilnehmer-URL kopieren oder QR-Code generieren

---

## Voting-System

### Manager-Ansicht: `[manage_poll]`

Die Manager-Ansicht bietet volle Kontrolle über Umfragen:

**Hauptfunktionen:**
- Umfragen erstellen/bearbeiten/archivieren
- Fragen erstellen/bearbeiten/aktivieren
- Teilnehmer-Tokens generieren
- QR-Codes für mobile Teilnahme
- Live-Status der aktiven Fragen
- Export-Funktionen (CSV/PDF)

**Umfrage-Status:**
- `active` - Umfrage ist aktiv, Teilnahme möglich
- `archived` - Umfrage archiviert, keine Teilnahme

**Frage-Status:**
- `active` - Frage ist aktiv und wird angezeigt
- `inactive` - Frage nicht sichtbar
- `ended` - Abstimmung beendet, nur Ergebnisse
- `released` - Ergebnisse freigegeben für Beamer

### Beamer-Modi

Der Beamer hat verschiedene Anzeigemodi:

- **auto**: Zeigt automatisch die aktive Frage
- **manual**: Zeigt spezifische Frage (Question ID)
- **results_all**: Zeigt alle freigegebenen Ergebnisse einer Umfrage
- **chart_X**: Zeigt spezifisches Diagramm (bar/pie/doughnut)

**Beamer-Modus setzen:**

1. Manager-Ansicht öffnen
2. Toggle "Beamer: Alle Ergebnisse" aktivieren
3. Oder über AJAX: `dgptm_set_beamer_mode`

### Member-Ansicht: `[member_vote]`

Die Teilnehmer-Ansicht ermöglicht das Abstimmen:

**Zugriff:**
- URL-Parameter: `?dgptm_member=1`
- Mit Token: `?dgptm_member=1&poll_id=X&token=ABC123`
- Oder eingeloggt als WordPress-Benutzer

**Workflow:**
1. Teilnehmer erhält Link/QR-Code
2. Login ODER Name eingeben
3. Aktive Frage wird angezeigt
4. Antworten auswählen
5. "Abstimmen" klicken
6. Bestätigung + optional Live-Status

---

## Zoom-Integration

### Einrichtung

#### 1. Zoom Server-to-Server OAuth App erstellen

1. https://marketplace.zoom.us/develop/create aufrufen
2. "Server-to-Server OAuth" auswählen
3. App-Name eingeben (z.B. "DGPTM Voting")
4. Berechtigungen hinzufügen:
   - `meeting:read:admin`
   - `meeting:write:admin`
   - `webinar:read:admin`
   - `webinar:write:admin`
5. Account ID, Client ID, Client Secret notieren

#### 2. WordPress-Einstellungen

1. WordPress Admin → Einstellungen → Online-Abstimmen
2. Zum Abschnitt "Zoom-Integration" scrollen
3. Felder ausfüllen:
   - **Zoom aktivieren**: Haken setzen
   - **Meeting/Webinar**: Art auswählen
   - **Meeting Number**: 11-stellige ID eingeben
   - **Account ID**: Von Zoom kopieren
   - **Client ID**: Von Zoom kopieren
   - **Client Secret**: Von Zoom kopieren
4. "Änderungen speichern"

#### 3. Webhook einrichten (für Anwesenheit)

1. Zoom Marketplace → Feature → Webhooks
2. "Add new event subscription" klicken
3. Event type auswählen:
   - Meeting: `participant.joined`, `participant.left`
   - Webinar: `webinar.participant_joined`, `webinar.participant_left`
4. Endpoint URL eingeben: `https://ihre-domain.de/wp-json/dgptm-zoom/v1/webhook`
5. Secret Token generieren lassen (optional)
6. Token in WordPress-Einstellungen eintragen

### Funktionsweise

#### Automatische Registrierung

Wenn ein Benutzer auf "ON" gesetzt wird:

1. System prüft Zoom-Aktivierung
2. API-Call an Zoom: `POST /meetings/{meetingId}/registrants`
3. Zoom antwortet mit Join-URL
4. Join-URL wird in User-Meta gespeichert
5. E-Mail mit Link wird versendet
6. Status: `approved`

#### Automatische Stornierung

Wenn ein Benutzer auf "OFF" gesetzt wird:

1. System prüft, ob Registrierung existiert
2. API-Call an Zoom: `PUT /meetings/{meetingId}/registrants/status`
3. Status wird auf `cancelled` gesetzt
4. Join-URL bleibt gespeichert (für spätere Reaktivierung)

#### Massenabgleich

**Funktion:** Synchronisiert lokale Berechtigungen mit Zoom

**Szenarien:**

1. **Register Missing**: Alle lokalen "ON"-Benutzer ohne Zoom-Registrierung werden registriert
2. **Cancel Extras**: Alle Zoom-Registrierungen ohne lokales "ON" werden storniert

**Verwendung:**

1. Manager-Ansicht → Tab "Zoom-Verwaltung"
2. "Registrants von Zoom laden" klicken
3. Liste vergleichen
4. "Fehlende registrieren" ODER "Extras canceln" klicken

### Shortcodes

#### `[online_abstimmen_button]`

Zeigt Toggle-Button für Teilnahmewunsch (ON/OFF).

**Parameter:** Keine

**Verhalten:**
- **Grün (ON)**: Registriert bei Zoom, sendet E-Mail mit Join-URL
- **Rot (OFF)**: Storniert Zoom-Registrierung
- Zeigt personalisierten Code (6-stellig)

#### `[zoom_register_and_join]`

Kombinierter Button: Registrieren + optional sofort beitreten.

**Parameter:**
- `redirect` (optional): `auto|app|web|none` - Weiterleitungsverhalten

**Beispiel:**
```
[zoom_register_and_join redirect="app"]
```

#### `[online_abstimmen_zoom_link]`

Zeigt persönlichen Zoom-Join-Link.

**Parameter:** Keine

**Ausgabe:**
- Link wird angezeigt, wenn Benutzer registriert ist
- Sonst: Hinweis auf fehlende Registrierung

#### `[zoom_live_state]`

Zeigt Live-Status des Meetings/Webinars.

**Parameter:** Keine

**Ausgabe:**
- "Meeting läuft" / "Meeting nicht aktiv"
- Aktualisiert sich automatisch alle 30 Sekunden

---

## Anwesenheitserfassung

### Funktionsweise

Das System erfasst Anwesenheit über zwei Kanäle:

1. **Zoom-Webhook**: Join/Leave-Events in Echtzeit
2. **Präsenz-Scanner**: Manuelle Erfassung vor Ort

Beide Kanäle schreiben in dieselbe Datenstruktur (`dgptm_zoom_attendance`).

### Anwesenheitsliste anzeigen

#### Shortcode: `[dgptm_presence_table]`

Zeigt Live-Anwesenheitsliste mit:
- Teilnehmername
- E-Mail
- Status (Mitgliedsart)
- Join-Zeit (erste)
- Leave-Zeit (letzte)
- Gesamtdauer
- Manuell-Flag (X = manuell erfasst)

**Parameter:**
- `meeting` (optional): Meeting-ID (Standard: aus Einstellungen)
- `kind` (optional): `auto|meeting|webinar` (Standard: `auto`)
- `poll_interval` (optional): Aktualisierungsintervall in ms (Standard: 10000)

**Beispiel:**
```
[dgptm_presence_table meeting="12345678901" kind="webinar" poll_interval="5000"]
```

### Export

**CSV-Export:**

1. Manager-Ansicht → Tab "Anwesenheit"
2. Meeting/Webinar auswählen
3. "CSV exportieren" klicken

**PDF-Export:**

1. Selbe Schritte wie CSV
2. "PDF exportieren" klicken
3. PDF enthält:
   - Meeting-Info
   - Teilnehmerliste mit Zeitstempeln
   - Zusammenfassung

### Datenverwaltung

**Anwesenheitsdaten löschen:**

1. Manager-Ansicht → Tab "Anwesenheit"
2. Meeting/Webinar auswählen
3. "Anwesenheit löschen" klicken
4. Bestätigen

**Alle Anwesenheitsdaten löschen:**

1. "Alle Anwesenheiten löschen" klicken
2. Bestätigen (ACHTUNG: Nicht rückgängig!)

---

## Präsenz-Scanner

### Einrichtung

#### 1. Webhook-Endpunkt konfigurieren

Für die Namenssuche (Zoho CRM Integration):

1. Einstellungen → Online-Abstimmen
2. Feld "Präsenz-Webhook URL" ausfüllen
3. Oder per Shortcode überschreiben (siehe unten)

#### 2. Scanner-Seite erstellen

1. Neue Seite erstellen
2. Shortcode einfügen: `[dgptm_presence_scanner]`
3. Seite veröffentlichen
4. Zugriff auf autorisierte Benutzer beschränken

### Verwendung

#### QR-Code/Badge-Scan

1. Scanner-Seite öffnen
2. Badge in Inputfeld scannen
3. Enter drücken
4. Teilnehmer wird automatisch erfasst

#### Manuelle Namenssuche

1. Button "Manuelle Abfrage" klicken
2. Modal öffnet sich
3. Namen eingeben (wird automatisch in Titlecase konvertiert)
4. "Suchen" klicken
5. Ergebnis auswählen (Doppelklick = sofort übernehmen)
6. Eintrag erscheint in der Liste mit "Manuell: X"

### Shortcode-Parameter

```
[dgptm_presence_scanner
    webhook="https://zoom-webhook.url"
    meeting_number="12345678901"
    kind="auto|meeting|webinar"
    save_on="green,yellow"
    search_webhook="https://zoho-search.url"
]
```

**Parameter:**
- `webhook`: Zoom-Webhook URL für Anwesenheitsspeicherung
- `meeting_number`: Meeting/Webinar ID
- `kind`: Typ (auto erkennt automatisch)
- `save_on`: Farb-Codes, bei denen gespeichert wird
- `search_webhook`: URL für Namenssuche (Standard: WordPress REST API)

### Datenfelder

Erfasste Felder pro Teilnehmer:

- **name**: Vollständiger Name (Titlecase)
- **email**: E-Mail-Adresse
- **status**: Status = Mitgliedsart (aus Zoho)
- **mitgliedsart**: Mitgliedsart
- **mitgliedsnummer**: Mitgliedsnummer
- **manual**: Flag (1 = manuell, 0 = gescannt)
- **join_first**: Erster Join-Zeitstempel
- **leave_last**: Letzter Leave-Zeitstempel
- **total**: Gesamtdauer in Sekunden

---

## Beamer-Ansicht

### Shortcode: `[beamer_view]`

Die Beamer-Ansicht ist für Projektion optimiert:

**Features:**
- Vollbild-Darstellung
- Auto-Refresh (konfigurierbar)
- Verschiedene Diagrammtypen
- Live-Ergebnis-Updates
- Logo-Anzeige (optional)

**Anzeigemodi:**

1. **auto**: Aktive Frage automatisch anzeigen
2. **manual**: Spezifische Frage (Question ID)
3. **results_all**: Alle freigegebenen Ergebnisse einer Umfrage
4. **chart_bar**: Balkendiagramm
5. **chart_pie**: Kreisdiagramm
6. **chart_doughnut**: Donut-Diagramm

**Steuerung:**

Modus wird zentral gesteuert via:
- Manager-Ansicht (Toggle-Schalter)
- Oder AJAX-Call: `dgptm_set_beamer_mode`

**Beispiel-Setup:**

1. Seite erstellen: "Beamer-Ansicht"
2. Shortcode einfügen: `[beamer_view]`
3. Seite in neuem Tab/Fenster öffnen
4. Vollbild aktivieren (F11)
5. Auf Beamer projizieren
6. Über Manager-Ansicht steuern

---

## Shortcodes

### Übersicht aller Shortcodes

| Shortcode | Zweck | Berechtigung |
|-----------|-------|--------------|
| `[manage_poll]` | Umfragen verwalten | Manager |
| `[beamer_view]` | Beamer-Projektion | Manager |
| `[member_vote]` | Abstimmen | Alle |
| `[online_abstimmen_button]` | ON/OFF Toggle | Eingeloggt |
| `[online_abstimmen_liste]` | Teilnehmerliste | Manager |
| `[online_abstimmen_code]` | Persönlicher Code | Eingeloggt |
| `[zoom_register_and_join]` | Zoom beitreten | Eingeloggt |
| `[zoom_live_state]` | Meeting-Status | Alle |
| `[dgptm_presence_table]` | Anwesenheitsliste | Manager |
| `[dgptm_presence_scanner]` | Präsenz-Scanner | Manager |
| `[mitgliederversammlung_flag]` | MV-Flag anzeigen | Eingeloggt |
| `[abstimmungsmanager_toggle]` | Manager-Status (1/0) | Eingeloggt |
| `[dgptm_registration_monitor]` | Registrierungs-Monitor | Manager |

### Shortcode-Details

#### `[abstimmungsmanager_toggle]`

Gibt `1` oder `0` zurück, je nach Manager-Status.

**Verwendung in Elementor:**
```
Condition: [abstimmungsmanager_toggle] equals 1
→ Widget wird nur für Manager angezeigt
```

#### `[mitgliederversammlung_flag]`

Gibt Mitgliederversammlungs-Flag des Benutzers zurück.

**Rückgabewert:**
- `true` - Benutzer ist für MV berechtigt
- `false` - Nicht berechtigt

#### `[online_abstimmen_liste]`

Zeigt Teilnehmerliste (ON-Benutzer).

**Parameter:** Keine

**Ausgabe:**
- Tabelle mit Name, E-Mail, Code, Zeitstempel
- Nur wenn `list_requires_mv = 0` ODER Benutzer hat MV-Flag

#### `[dgptm_registration_monitor]`

Live-Monitor für Zoom-Registrierungen.

**Features:**
- Zeigt eingehende Registrierungen in Echtzeit
- Auto-Approve/Deny basierend auf lokaler Berechtigung
- Webhook-Listener für Registrierungs-Events

**Verwendung:**
1. Seite erstellen mit Shortcode
2. Zoom-Webhook konfigurieren (Event: `meeting.registration_created`)
3. Monitor im Browser offen lassen während Event

---

## Einstellungen

### Zugriff

WordPress Admin → Einstellungen → Online-Abstimmen

### Einstellungs-Bereiche

#### 1. Allgemeine Einstellungen

| Feld | Beschreibung | Standard |
|------|--------------|----------|
| Button-Text (grün/aktiv) | Text für ON-Button | "Ich möchte an der Abstimmung online teilnehmen." |
| Button-Text (rot/inaktiv) | Text für OFF-Button | "Ich möchte nicht online an der Abstimmung teilnehmen" |
| Ersatztext außerhalb des Startzeitpunkts | Meldung vor/nach Zeitraum | "Online-Abstimmung ist derzeit nicht möglich." |
| Start (YYYY-MM-DD HH:MM) | Startzeitpunkt | - |
| Ende (YYYY-MM-DD HH:MM) | Endzeitpunkt | - |
| Zeitzone | Anzeige-Zeitzone | WordPress-Zeitzone |
| Liste nur anzeigen, wenn MV = true | Zugriffsbeschränkung | Aktiviert |
| Hinweistext nach Endzeit | Meldung nach Ende | "Registrierung zur Online-Abstimmung abgelaufen" |
| E-Mail-Empfänger (CSV+PDF Sammelmail) | Admin-E-Mail | WordPress Admin-E-Mail |

#### 2. E-Mail-Templates

| Feld | Beschreibung | Platzhalter |
|------|--------------|-------------|
| Mail-Betreff (AN/grün) | Betreff bei ON | {name}, {code}, {site} |
| Mail-Text (AN/grün) | E-Mail-Body bei ON | {name}, {code}, {site} |
| Mail-Betreff (AUS/rot) | Betreff bei OFF | {name}, {code}, {site} |
| Mail-Text (AUS/rot) | E-Mail-Body bei OFF | {name}, {code}, {site} |
| Kopie an Admin (BCC) senden | BCC an Admin | Deaktiviert |

#### 3. Zoom-Integration

| Feld | Beschreibung | Erforderlich |
|------|--------------|--------------|
| Zoom aktivieren | Master-Schalter | - |
| Meeting/Webinar | Art (webinar/meeting/auto) | Ja |
| Meeting Number | 11-stellige ID | Ja |
| Account ID | S2S OAuth Account ID | Ja |
| Client ID | S2S OAuth Client ID | Ja |
| Client Secret | S2S OAuth Client Secret | Ja |
| Registrieren bei Grün | Auto-Register ON | Aktiviert |
| Canceln bei Rot | Auto-Cancel OFF | Aktiviert |
| Redirect bei Grün | Weiterleitungstyp | auto |
| Log aktivieren | Debug-Log | Aktiviert |
| Frontend-Debug | Debug-Ausgabe sichtbar | Deaktiviert |
| Webhook Secret | Zoom-Webhook Secret | Optional |

#### 4. Anwesenheit

| Feld | Beschreibung |
|------|--------------|
| Zoom Attendance aktivieren | Webhook-Tracking | Aktiviert |
| Webhook Token | Legacy Token (Fallback) | Optional |
| Präsenz-Webhook URL | URL für Scanner | Optional |

### Speichern

Nach Änderungen unbedingt **"Änderungen speichern"** klicken!

---

## Troubleshooting

### Häufige Probleme

#### Problem: "Keine Berechtigung" bei `[manage_poll]`

**Ursache:** Benutzer ist kein Manager.

**Lösung:**
1. Benutzer → Profil bearbeiten
2. "Ist Abstimmungsmanager?" aktivieren
3. Oder Benutzer zu Administrator machen

#### Problem: Zoom-Registrierung schlägt fehl

**Mögliche Ursachen:**

1. **Ungültige Credentials**
   - Lösung: Account ID, Client ID, Secret überprüfen

2. **Meeting existiert nicht**
   - Lösung: Meeting Number korrekt eingeben (11-stellig)

3. **API-Rate Limit**
   - Lösung: 1 Minute warten, erneut versuchen
   - System hat Exponential Backoff integriert

4. **Berechtigungen fehlen**
   - Lösung: Zoom App-Scopes prüfen (meeting:write:admin)

**Debug:**
1. Einstellungen → Zoom → "Log aktivieren" Haken setzen
2. Aktion auslösen (Benutzer auf ON setzen)
3. WordPress Admin → Tab "Zoom-Log"
4. Log durchsuchen nach Fehler-Messages

#### Problem: Anwesenheit wird nicht erfasst

**Ursache:** Zoom-Webhook nicht konfiguriert oder falsch.

**Lösung:**
1. Zoom Marketplace → Webhooks prüfen
2. Endpoint URL validieren: `https://ihre-domain.de/wp-json/dgptm-zoom/v1/webhook`
3. Secret Token in WordPress-Einstellungen eintragen
4. Test-Event versenden (Zoom bietet Test-Function)

#### Problem: QR-Code wird nicht generiert

**Ursache:** JavaScript-Bibliothek nicht geladen.

**Lösung:**
1. Browser-Konsole öffnen (F12)
2. Nach Fehlern suchen
3. Falls `QRCode is not defined`: CDN-URL prüfen
4. Fallback auf API-basierte QR-Generierung (automatisch)

#### Problem: "Duplikat-Eintrag" Fehler bei Abstimmung

**Ursache:** Benutzer hat bereits abgestimmt.

**Lösung:**
- Gewünscht: Mehrfachabstimmung verhindern
- Wenn erlaubt: Constraint in Datenbank entfernen (NICHT empfohlen)

#### Problem: Beamer zeigt keine Live-Updates

**Ursache:** JavaScript nicht geladen oder Fehler.

**Lösung:**
1. Browser-Konsole prüfen (F12)
2. Seite neu laden (Strg+F5)
3. Cache leeren
4. Fallback auf manuelle Aktualisierung

### Debug-Modi

#### WordPress Debug Log

In `wp-config.php` aktivieren:

```php
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'WP_DEBUG_DISPLAY', false );
```

Log-Datei: `wp-content/debug.log`

#### Zoom Debug Log

1. Einstellungen → Zoom-Integration
2. "Log aktivieren" Haken setzen
3. WordPress-Einstellungen speichern
4. Admin → Tab "Zoom-Log" öffnen
5. Log durchsuchen
6. "Log herunterladen" für vollständigen Export

**Log-Inhalte:**
- HTTP-Requests (Method, URL, Headers, Body)
- HTTP-Responses (Code, Headers, Body)
- Fehler-Messages
- Timestamps

**Log-Verwaltung:**
- Max. 500 Einträge (automatisch rotiert)
- "Log löschen" Button zum Zurücksetzen

### Support

Bei weiteren Problemen:

1. **Logs prüfen**: WordPress debug.log + Zoom-Log
2. **Browser-Konsole**: JavaScript-Fehler identifizieren
3. **Server-Logs**: PHP-Fehler finden
4. **Issue melden**: GitHub-Repository (falls vorhanden)

---

## Changelog

### Version 4.0.0 (Dezember 2024)
- ✅ Konsolidierung aller Features in eine Hauptdatei
- ✅ Vereinheitlichte Einstellungen
- ✅ Verbesserte Dokumentation
- ✅ Code-Cleanup und Optimierungen
- ✅ Bessere Fehlerbehandlung
- ✅ Erweiterte Debug-Funktionen

### Version 3.7.0
- Voting-System mit Umfragen
- QR-Code-Generierung
- Beamer-Ansicht

### Version 2.0
- Zoom S2S OAuth Integration
- Anwesenheitserfassung
- Webhook-Listener

### Version 1.1.0
- Präsenz-Scanner
- Manuelle Namenssuche
- Zoho CRM Integration

---

## Lizenz

© 2024 DGPTM (Deutsche Gesellschaft für Prävention und Telemedizin e.V.)
Alle Rechte vorbehalten.

---

**Hinweis:** Dieses Modul ist Teil der DGPTM Plugin Suite und erfordert die Suite-Infrastruktur für volle Funktionalität.
