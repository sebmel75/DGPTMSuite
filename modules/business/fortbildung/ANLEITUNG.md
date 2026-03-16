# Fortbildungsverwaltung - Anleitung

## Ueberblick

Das Modul **Fortbildungsverwaltung** ist das zentrale System zur Verwaltung von Fortbildungseintraegen der DGPTM-Mitglieder. Es stellt zwei Custom Post Types bereit (`fortbildung` und `fobi_certificate`), importiert automatisch bestandene Quiz-Ergebnisse, generiert Fortbildungsnachweise als PDF mit QR-Code-Verifikation und bietet eine umfassende Frontend-Ansicht fuer Mitglieder. Zusaetzlich umfasst das Modul Add-ons fuer Statistiken, CSV-Import, KI-gestuetzten Nachweis-Upload und Doubletten-Bereinigung.

## Voraussetzungen

- **Quiz Manager** Modul (Abhaengigkeit)
- **Advanced Custom Fields (ACF)** Plugin (WordPress-Abhaengigkeit)
- **CRM-Abruf** Modul (optionale Abhaengigkeit, fuer Adressdaten im PDF-Nachweis)
- **FPDF-Bibliothek** unter `libraries/fpdf/fpdf.php` (fuer PDF-Generierung)
- Optional: FPDI-Bibliothek fuer PDF-Vorlagen als Hintergrund
- PHP 7.4+, WordPress 5.8+

## Installation & Aktivierung

1. Modul im DGPTM Suite Dashboard aktivieren.
2. Sicherstellen, dass ACF und das Quiz Manager Modul aktiv sind.
3. Die FPDF-Bibliothek muss im Verzeichnis `libraries/fpdf/` vorhanden sein.
4. Optional: Unter **Fortbildungen > Einstellungen** die PDF- und E-Mail-Konfiguration vornehmen.

## Konfiguration

### Einstellungen (Admin)

Die Einstellungen werden unter der WordPress-Option `fobi_aek_settings` gespeichert. Konfigurierbar unter **Fortbildungen > Einstellungen** im Admin:

**API / Aerztekammer (EIV):**

| Einstellung | Beschreibung |
|---|---|
| `access_token` | API-Token fuer die EIV-Schnittstelle |
| `scans_endpoint_tpl` | URL-Template fuer EFN-Scans (Platzhalter: `{EFN}`) |
| `event_endpoint_tpl` | URL-Template fuer Veranstaltungen (Platzhalter: `{VNR}`) |

**Mapping Veranstaltungsarten zu EBCP-Punkten:**

Die Zuordnung von Veranstaltungsarten zu Punkten erfolgt ueber eine JSON-Konfiguration. Jede Kategorie hat:
- **Code** (z.B. A, B, C)
- **Label** (z.B. "Vortragsveranstaltung", "Kongress")
- **Berechnungsart** (`unit` = pro Zeiteinheit, `fixed` = feste Punkte, `per_hour` = pro Stunde)
- **Punkte** und ggf. **Zeiteinheit in Minuten**

**PDF-Nachweis:**

| Einstellung | Beschreibung |
|---|---|
| `pdf_logo_attachment_id` | Attachment-ID des Logos (PNG) |
| `pdf_template_attachment_id` | Attachment-ID einer PDF-Vorlage (Seite 1 wird als Hintergrund genutzt) |
| `qr_verify_base` | Basis-URL fuer QR-Code-Verifikation (Standard: `/verify/`) |
| `pdf_sender_name` | Absendername auf dem Nachweis |
| `pdf_sender_email` | Absender-E-Mail |
| `enable_certificate_button` | Nachweis-Button aktivieren (`1`/`0`) |
| `certificate_button_roles` | Rollen, die den Nachweis erstellen duerfen (Array, z.B. `["administrator"]`) |

**E-Mail:**

| Einstellung | Beschreibung |
|---|---|
| `email_enabled` | E-Mail-Versand aktivieren (`1`/`0`) |
| `email_subject_tpl` | Betreff-Vorlage (Platzhalter: `{period_label}`) |
| `email_body_tpl` | Text-Vorlage (Platzhalter: `{name}`, `{period_label}`, `{verify_url}`, `{verify_code}`, `{site_name}`) |
| `email_attach_pdf` | PDF als Anhang mitsenden (`1`/`0`) |

**Batch-Import:**

| Einstellung | Beschreibung |
|---|---|
| `batch_enabled` | Automatischen Import aktivieren (`1`/`0`) |
| `batch_interval` | Cron-Intervall (z.B. `daily`) |

### ACF-Felder des Fortbildungs-CPT

Jeder Fortbildungseintrag besitzt folgende ACF-Felder:

| Feld | Beschreibung |
|---|---|
| `user` | WordPress User-ID des Teilnehmers |
| `date` | Datum der Fortbildung (Format: Y-m-d) |
| `location` | Veranstaltungsort |
| `type` | Art der Fortbildung (z.B. "Quiz", "Webinar", "Kongress") |
| `points` | EBCP-Punkte |
| `vnr` | Veranstaltungsnummer |
| `token` | Verifikations-Token |
| `freigegeben` | Freigabestatus (Ja/Nein) -- nur freigegebene Eintraege erscheinen im Nachweis |
| `freigabe_durch` | Wer hat freigegeben (z.B. "System (Webinar)") |
| `freigabe_mail` | E-Mail des Freigebenden |
| `attachements` | Optionale Anhaenge (z.B. Zertifikat-PDF) |

## Benutzung

### Fortbildungsliste im Frontend

Der Shortcode `[fortbildung_liste]` zeigt eingeloggten Benutzern ihre persoenliche Fortbildungsliste:

- **Zeitraum-Filter:** Auswahl von Jahr (von/bis), maximal 3 Jahre
- **Responsive Darstellung:** Tabelle auf Desktop, Karten auf Mobilgeraeten
- **EBCP-Gesamtpunkte** werden pro Zeitraum summiert (nur freigegebene Eintraege)
- **Fortbildungsnachweis-Button:** Erstellt ein PDF mit allen freigegebenen Fortbildungen im gewaehlten Zeitraum

### Fortbildungsnachweis (PDF) erstellen

1. Auf der Seite mit `[fortbildung_liste]` den gewuenschten Zeitraum waehlen.
2. Auf "Fortbildungsnachweis erstellen" klicken.
3. Das PDF wird generiert mit:
   - Logo und optionalem PDF-Vorlagen-Hintergrund
   - Absenderadresse und Empfaengeradresse (aus Zoho CRM via CRM-Abruf)
   - Tabellarische Auflistung aller freigegebenen Fortbildungen
   - Gesamtpunktzahl
   - QR-Code mit 8-stelligem Verifikationscode
   - Verifikations-URL (z.B. `https://domain.de/verify/ABCD1234`)
4. Der Nachweis kann heruntergeladen werden und wird optional per E-Mail versendet.

**Tageslimit:** Jeder Zeitraum kann nur einmal pro Kalendertag generiert werden.

### Verifikationsseite

Unter `/verify/{CODE}` kann ein Nachweis mit dem 8-stelligen Code verifiziert werden. Der Code ist 365 Tage gueltig.

### Quiz-Import (automatisch)

Der Quiz Report Importer laeuft taeglich als Cron-Job (`qr_import_daily_event`):

1. Liest bestandene Quiz-Reports aus der Tabelle `{prefix}_aysquiz_reports`.
2. Prueft anhand diverser Felder, ob das Quiz bestanden wurde.
3. Erstellt fuer jeden bestandenen Report einen Fortbildungseintrag (Typ: "Quiz", Ort: "Online").
4. Quiz-Punkte: 0.5 EBCP pro Quiz, max. 12 Quizze pro Jahr.
5. Doubletten werden anhand der `quiz_report_id` verhindert.
6. Nach dem Import wird automatisch eine Doubletten-Bereinigung ausgefuehrt.

Der Import kann auch manuell per AJAX-Action `process_quiz_reports` ausgeloest werden.

### CSV-Import (Teilnehmerlisten)

Unter **Fortbildungen > CSV-Import** koennen Teilnehmerlisten als CSV importiert werden:

- **Format:** `FIRST_NAME`, `LAST_NAME`, `EMAIL`, `SESSION_TITLE`
- Teilnehmer werden anhand der E-Mail WordPress-Benutzern zugeordnet.
- Nur Administratoren (`manage_options`) haben Zugriff.

### Nachweis-Upload mit KI

Der Shortcode `[fobi_nachweis_upload]` ermoeglicht Benutzern, Fortbildungsnachweise als Bild oder PDF hochzuladen:

- Claude AI analysiert das hochgeladene Dokument automatisch.
- Erkannte Daten (Titel, Datum, Ort, Kategorie) werden als Fortbildungseintrag vorgeschlagen.
- Punkteberechnung erfolgt anhand der EBCP-Matrix.
- Erfordert Pruefung und Freigabe durch berechtigte Benutzer.

Der Shortcode `[fobi_nachweis_pruefliste]` zeigt eine Pruef-Ansicht fuer Nachweise mit Genehmigen/Ablehnen-Buttons.

### Doubletten-Bereinigung

Unter **Fortbildungen > Doubletten manuell pruefen**:

- Intelligente Gruppierung aehnlicher Eintraege
- Flexible Suchoptionen (Ort und/oder Datum ignorieren)
- "Online"-Eintraege duerfen mehrfach vorkommen
- Automatische Bereinigung laeuft auch nach dem Cron-Quiz-Import

### Statistiken

Der Shortcode `[fortbildung_statistik]` zeigt Auswertungen:

| Parameter | Beschreibung |
|---|---|
| `default_years="5"` | Anzahl der standardmaessig angezeigten Jahre |

Dargestellt werden u.a.: Fortbildungen pro Jahr, Durchschnitt pro Mitglied, Teilnehmer pro Veranstaltung.

## Shortcodes (Gesamtuebersicht)

| Shortcode | Beschreibung |
|---|---|
| `[fortbildung_liste]` | Persoenliche Fortbildungsliste mit Nachweis-Button |
| `[fortbildung_statistik]` | Statistische Auswertungen |
| `[fobi_nachweis_upload]` | KI-gestuetzter Nachweis-Upload |
| `[fobi_nachweis_pruefliste]` | Pruef-Ansicht fuer hochgeladene Nachweise |

## Fehlerbehebung

| Problem | Loesung |
|---|---|
| "FPDF-Bibliothek nicht gefunden" | Pruefen, ob `libraries/fpdf/fpdf.php` vorhanden ist |
| Keine Fortbildungen sichtbar | Benutzer muss eingeloggt sein; ACF-Feld `user` muss gesetzt sein |
| Nachweis-Button deaktiviert | Rolle pruefen (`certificate_button_roles` in Einstellungen); Funktion muss aktiviert sein |
| "Heute bereits ein Nachweis erzeugt" | Jeder Zeitraum kann nur einmal pro Tag generiert werden |
| Quiz-Import findet keine Reports | Tabelle `{prefix}_aysquiz_reports` pruefen; Reports muessen Status "finished" haben |
| Adressdaten im PDF fehlen | CRM-Abruf Modul muss aktiv sein; Zoho-Felder "Strasse", "PLZ", "Ort" muessen im CRM gepflegt sein |
| QR-Code fehlt im PDF | phpqrcode-Bibliothek pruefen oder Google Charts API-Erreichbarkeit |
| PDF mit falscher Zeichenkodierung | FPDF nutzt ISO-8859-1; Sonderzeichen werden per iconv konvertiert |
| Punkte werden nicht gezaehlt | ACF-Feld `freigegeben` muss auf "Ja"/"true"/"1" stehen |

## Technische Details

- **Custom Post Types:**
  - `fortbildung` -- Fortbildungseintraege
  - `fobi_certificate` -- Fortbildungsnachweise (PDF-Referenzen)
- **Cron-Jobs:**
  - `qr_import_daily_event` -- taeglicher Quiz-Report-Import
  - `fobi_certificate_cleanup_daily` -- taegliches Loeschen abgelaufener Zertifikate (>365 Tage)
- **AJAX-Actions:**
  - `fobi_filter_fortbildungen` -- Fortbildungsliste nach Zeitraum filtern
  - `fobi_can_create_today` -- Live-Pruefung, ob Nachweis heute erstellt werden darf
  - `create_fortbildungsnachweis` -- PDF-Nachweis erstellen
  - `process_quiz_reports` -- Quiz-Reports manuell importieren
  - `fobi_process_csv_import` -- CSV-Import starten
- **Options:**
  - `fobi_aek_settings` -- Hauptkonfiguration
- **Admin-Menues:**
  - Fortbildungen (CPT-Liste)
  - Nachweise (Unter-Menue)
  - CSV-Import (Unter-Menue)
  - Doubletten manuell pruefen (Unter-Menue)
- **Verifikations-Route:** `/verify/{8-stelliger-Code}`
- **Download-Route:** `/dgptm-download/?cid={id}&sig={hmac-signatur}`
- **Dateien im Modul:**
  - `fortbildung-liste-plugin.php` -- Hauptdatei (CPTs, Shortcode, Quiz-Importer, PDF-Erstellung)
  - `fortbildungsupload.php` -- KI-gestuetzter Nachweis-Upload (Claude AI)
  - `FortbildungStatistikAdon.php` -- Statistik-Shortcode
  - `fortbildung-csv-import.php` -- CSV-Import
  - `doublettencheck.php` -- Doubletten-Bereinigung
  - `erweiterte-suche.php` -- Admin-Filter und erweiterte Suche
