# Stipendium Demo-Version — Design Spec

**Datum:** 2026-04-13
**Status:** Genehmigt
**Vorgaenger:** `2026-04-12-stipendium-design.md` (Basis-Architektur)

## Zusammenfassung

Demo-Version des Stipendium-Moduls mit funktionsfaehigem Gutachter-Flow, ORCID-Lookup, Token-basiertem Gutachter-Zugang, Vorsitzenden-Dashboard und Testdaten im CRM. Die GS arbeitet ausschliesslich im Zoho CRM.

---

## 1. CRM Blueprint (aktualisiert)

Neuer Status "Geprueft" fuer GS-Vorpruefung:

```
[GS im Zoho CRM]
  Eingegangen → Geprueft

[Vorsitzender im WordPress-Dashboard]
  Geprueft → Freigegeben → In Bewertung → Abgeschlossen → Archiviert
```

### Neues Feld im Stipendien-Modul

| Feld | API-Name | Typ | Beschreibung |
|------|----------|-----|-------------|
| Status | `Stipendium_Status` | Picklist | Eingegangen, **Geprueft**, Freigegeben, In Bewertung, Abgeschlossen, Abgelehnt, Archiviert |

### Zoho Workflow Rule: GS-Benachrichtigung

**Trigger:** Stipendien-Record Status aendert sich auf "Geprueft"
**Aktion:** E-Mail an Vorsitzenden (aus CRM-Feld oder WordPress-Setting)

---

## 2. ORCID-Lookup im Bewerbungsformular

### Funktionsweise

Bewerber gibt ORCID-ID ein (Format: `0000-0000-0000-0000`). System ruft oeffentliche ORCID API ab und fuellt automatisch aus:
- Vorname + Nachname
- Institution (aktuelle Beschaeftigung)
- E-Mail (falls oeffentlich)

### Technische Umsetzung

- AJAX-Endpoint: `dgptm_stipendium_lookup_orcid` (auch fuer nicht-eingeloggte: `wp_ajax_nopriv_`)
- API-Calls: `https://pub.orcid.org/v3.0/{orcid}/person` + `https://pub.orcid.org/v3.0/{orcid}/employments`
- Kein API-Key erforderlich (oeffentliche API)
- Pattern identisch zu `DGPTM_Artikel_Einreichung::ajax_lookup_orcid()`
- Validierung: `/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/`

### UI im Formular (Step 1)

```
ORCID-ID: [0000-0000-0000-0000] [Abrufen]
           ↓ (bei Erfolg)
Vorname:  [Max]        ← automatisch ausgefuellt
Nachname: [Mustermann] ← automatisch ausgefuellt
Institution: [Uniklinikum Beispielstadt] ← automatisch ausgefuellt
E-Mail:   [max@example.de] ← automatisch ausgefuellt (falls oeffentlich)
```

---

## 3. Token-basierter Gutachter-Zugang

### Datenbank-Tabelle: `wp_dgptm_stipendium_tokens`

| Spalte | Typ | Beschreibung |
|--------|-----|-------------|
| `id` | BIGINT AUTO_INCREMENT | Primary Key |
| `token` | VARCHAR(64) UNIQUE | Zufaelliger Hex-Token (32 Bytes) |
| `stipendium_id` | VARCHAR(50) | Zoho CRM Stipendien-Record ID |
| `gutachter_name` | VARCHAR(255) | Name des Gutachters |
| `gutachter_email` | VARCHAR(255) | E-Mail des Gutachters |
| `bewertung_status` | ENUM('ausstehend','entwurf','abgeschlossen') | Default: ausstehend |
| `bewertung_data` | LONGTEXT | JSON: Noten, Kommentare (Zwischenspeicher) |
| `bewertung_crm_id` | VARCHAR(50) | Zoho CRM Bewertungs-Record ID (nach Abschluss) |
| `created_by` | BIGINT | WordPress User-ID des Vorsitzenden |
| `created_at` | DATETIME | Erstellungszeitpunkt |
| `expires_at` | DATETIME | Ablaufdatum (Default: 28 Tage) |
| `completed_at` | DATETIME | Abschlusszeitpunkt |

### Token-Lifecycle

1. Vorsitzender gibt Name + E-Mail ein → Token generiert
2. HTML-Mail mit Token-Link versendet
3. Gutachter oeffnet Link → Bewertungsbogen
4. Auto-Save speichert Entwurf in `bewertung_data` (JSON in DB)
5. "Gutachten abschliessen" → Daten zu Zoho CRM schreiben → Status "abgeschlossen"
6. Erneuter Aufruf → Danke-Seite

### Shortcode

`[dgptm_stipendium_gutachten]` — Auf einer eigenen Seite (z.B. `/stipendium/gutachten/`)

Liest `?token=...` aus dem URL-Parameter. Kein Login erforderlich.

---

## 4. Gutachter-Bewertungsbogen (Token-Seite)

### Zustand 1: Ausstehend / Entwurf

```
┌─────────────────────────────────────────────────────────┐
│  DGPTM Stipendium — Begutachtung                       │
│  Promotionsstipendium | Ausschreibung 2026              │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  Guten Tag, [Gutachter-Name],                           │
│  Sie wurden eingeladen, die folgende Bewerbung           │
│  fuer das Promotionsstipendium der DGPTM zu begutachten.│
│                                                          │
│  Bewerber/in: [Name aus CRM]                            │
│                                                          │
│  Eingereichte Unterlagen:                                │
│  [PDF] Lebenslauf  [PDF] Motivationsschreiben            │
│  [PDF] Expose      [PDF] Empfehlungsschreiben            │
│  [PDF] Studienleistungen                                 │
│                                                          │
│  ──────────────────────────────────────────────          │
│                                                          │
│  A. Wissenschaftlicher Wert (30%)                        │
│  1. Ist die Fragestellung relevant?     [Dropdown 1-10]  │
│  2. Klar formuliert?                    [Dropdown 1-10]  │
│  3. Erkenntnisfortschritt?              [Dropdown 1-10]  │
│  Kommentar: [_______________________________]            │
│                                                          │
│  B. Relevanz Perfusiologie (30%)                         │
│  [...gleich...]                                          │
│                                                          │
│  C. Projektbeschreibung (25%)                            │
│  [...gleich...]                                          │
│                                                          │
│  D. Leistungsnachweise (15%)                             │
│  [...gleich...]                                          │
│                                                          │
│  Gesamtanmerkungen: [___________________________]        │
│                                                          │
│  ════════════════════════════════════════════════         │
│  Vorschau: Gesamtscore 7.45 / 10 (74.5 Punkte)         │
│  ════════════════════════════════════════════════         │
│                                                          │
│  [Entwurf gespeichert um 14:32]   [Gutachten abschliessen]│
│                                                          │
│  Hinweis: Nach Abschluss kann die Bewertung nicht        │
│  mehr geaendert werden.                                  │
└─────────────────────────────────────────────────────────┘
```

### Zustand 2: Abgeschlossen (Danke-Seite)

```
┌─────────────────────────────────────────────────────────┐
│  DGPTM Stipendium — Begutachtung                       │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  ✓ Vielen Dank fuer Ihr Gutachten!                      │
│                                                          │
│  Ihre Bewertung fuer [Bewerber-Name] wurde               │
│  erfolgreich uebermittelt.                               │
│                                                          │
│  Ihr Gesamtscore: 7.45 / 10 (74.5 Punkte)              │
│  Abgegeben am: 13.04.2026, 15:22                        │
│                                                          │
│  Der Vorsitzende des Stipendiumsrats wurde               │
│  automatisch benachrichtigt.                             │
│                                                          │
│  Bei Rueckfragen wenden Sie sich bitte an die            │
│  Geschaeftsstelle: geschaeftsstelle@dgptm.de             │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

### Zustand 3: Token ungueltig / abgelaufen

```
Dieser Link ist nicht mehr gueltig. Bitte wenden Sie sich
an die Geschaeftsstelle der DGPTM.
```

---

## 5. Auto-Save (AJAX)

### Endpoint

`wp_ajax_nopriv_dgptm_stipendium_autosave` (kein Login noetig, Token validiert)

### Payload

```json
{
  "token": "abc123...",
  "data": {
    "A1_Note": 8, "A2_Note": 7, "A3_Note": 9,
    "A_Kommentar": "Sehr relevante Fragestellung...",
    "B1_Note": 7, ...
    "Gesamtanmerkungen": "..."
  }
}
```

### Verhalten

- Speichert in `bewertung_data` (JSON) in der Token-Tabelle
- Setzt `bewertung_status` auf "entwurf"
- Automatisch alle 30 Sekunden bei Aenderung (Debounce)
- Response: `{"success": true, "saved_at": "14:32"}`
- Kein Zoho-Call beim Auto-Save (nur lokal in DB)

---

## 6. Benachrichtigungsmail "Jetzt begutachten"

### HTML-Template

Gleiches Layout wie die Kommentar-Benachrichtigungs-Mails (DGPTM-Header, blauer Akzent):

```
┌─────────────────────────────────────────────┐
│  DGPTM                  Stipendienvergabe   │  ← dunkelblauer Header
├─────────────────────────────────────────────┤
│                                              │
│  Einladung zur Begutachtung                  │
│                                              │
│  Sehr geehrte/r [Gutachter-Name],           │
│                                              │
│  Sie wurden vom Vorsitzenden des             │
│  Stipendiumsrats eingeladen, eine            │
│  Bewerbung fuer das [Stipendientyp]          │
│  der DGPTM zu begutachten.                   │
│                                              │
│  ┌─ ──────────────────────────────────┐      │
│  │  Bewerber/in: [Name]               │      │
│  │  Stipendium: [Typ]                 │      │
│  │  Runde: [Bezeichnung]              │      │
│  │  Frist: [Datum]                    │      │
│  └────────────────────────────────────┘      │
│                                              │
│      [    Jetzt begutachten    ]             │  ← grosser CTA-Button
│                                              │
│  Dieser Link ist persoenlich und             │
│  vertraulich. Bitte geben Sie ihn            │
│  nicht an Dritte weiter.                     │
│                                              │
├─────────────────────────────────────────────┤
│  DGPTM e.V. | nichtantworten@dgptm.de      │
└─────────────────────────────────────────────┘
```

---

## 7. Vorsitzenden-Dashboard im Mitgliederbereich

### Tab: "Stipendien" (acf:stipendiumsrat_vorsitz)

Shortcode: `[dgptm_stipendium_auswertung]` (bereits registriert, Platzhalter ersetzen)

### Ansicht

```
┌─────────────────────────────────────────────────────────┐
│  Stipendien — Vorsitzenden-Dashboard                    │
│                                                          │
│  Runde: [Ausschreibung 2026 ▼]  Typ: [Alle ▼]         │
├─────────────────────────────────────────────────────────┤
│                                                          │
│  Geprueft (bereit zur Freigabe):                        │
│  ┌──────────────────────────────────────────────┐       │
│  │ Max Mustermann | Promotion | Eingang 15.04.  │       │
│  │ [Dokumente ansehen] [Freigeben] [Ablehnen]   │       │
│  └──────────────────────────────────────────────┘       │
│                                                          │
│  Freigegeben (Gutachter einladen):                      │
│  ┌──────────────────────────────────────────────┐       │
│  │ Anna Beispiel | Promotion | Freigegeben 16.04│       │
│  │ Gutachter: 0/3 zugewiesen                     │       │
│  │ [+ Gutachter einladen]                        │       │
│  │                                               │       │
│  │ Gutachter einladen:                           │       │
│  │ Name:  [________________]                     │       │
│  │ E-Mail: [________________]                    │       │
│  │ Frist:  [30.05.2026    ]                      │       │
│  │ [Einladung senden]                            │       │
│  └──────────────────────────────────────────────┘       │
│                                                          │
│  In Bewertung:                                           │
│  ┌──────────────────────────────────────────────┐       │
│  │ Lisa Schmidt | Guettler | 2/3 Gutachten      │       │
│  │ Gutachter 1: Dr. Mueller    ✓ abgeschlossen  │       │
│  │ Gutachter 2: Prof. Koch     ✓ abgeschlossen  │       │
│  │ Gutachter 3: Dr. Weber      ◌ ausstehend     │       │
│  └──────────────────────────────────────────────┘       │
│                                                          │
│  Abgeschlossen:                                          │
│  ┌──────────────────────────────────────────────┐       │
│  │ Rang │ Name          │ Score │ Gutachten      │       │
│  │ 1    │ Anna Beispiel │ 8.25  │ 3/3           │       │
│  │ 2    │ Lisa Schmidt  │ 6.80  │ 3/3           │       │
│  │ —    │ Tom Weber     │ 4.90  │ nicht foerderf.│       │
│  │                                               │       │
│  │ [PDF-Export] [Stipendium vergeben] [Archivieren]│     │
│  └──────────────────────────────────────────────┘       │
│                                                          │
└─────────────────────────────────────────────────────────┘
```

### Aktionen des Vorsitzenden

| Aktion | AJAX-Endpoint | Beschreibung |
|--------|--------------|-------------|
| Freigeben | `dgptm_stipendium_freigeben` | Status Geprueft → Freigegeben |
| Ablehnen | `dgptm_stipendium_ablehnen` | Status → Abgelehnt |
| Gutachter einladen | `dgptm_stipendium_einladen` | Token generieren + HTML-Mail senden |
| Ranking berechnen | `dgptm_stipendium_ranking` | Deluge Custom Function triggern |
| PDF-Export | `dgptm_stipendium_pdf` | Auswertungs-PDF generieren |
| Vergeben | `dgptm_stipendium_vergeben` | Vergeben-Flag setzen |
| Archivieren | `dgptm_stipendium_archivieren` | Bulk-Status → Archiviert |

---

## 8. Testdaten

### Zoho CRM Records

**Stipendien-Records (2 Stueck):**

| Bewerber | Typ | Runde | Status |
|----------|-----|-------|--------|
| Max Mustermann | Promotionsstipendium | Ausschreibung 2026 | Freigegeben |
| Anna Beispiel | Josef Guettler Stipendium | 2026 | Freigegeben |

**Gutachter-Tokens (6 Stueck):**

| Stipendium | Gutachter | E-Mail | Status |
|------------|-----------|--------|--------|
| Mustermann | Dr. Mueller | test1@dgptm.de | ausstehend |
| Mustermann | Prof. Koch | test2@dgptm.de | ausstehend |
| Mustermann | Dr. Weber | test3@dgptm.de | ausstehend |
| Beispiel | Dr. Mueller | test1@dgptm.de | ausstehend |
| Beispiel | Prof. Koch | test2@dgptm.de | ausstehend |
| Beispiel | Dr. Weber | test3@dgptm.de | ausstehend |

**WorkDrive Platzhalter-Dokumente:**
- Lebenslauf_Mustermann.pdf (Platzhalter)
- Motivationsschreiben_Mustermann.pdf (Platzhalter)
- Expose_Mustermann.pdf (Platzhalter)
- (gleiches Set fuer Beispiel)

### WordPress-Konfiguration

- Bewerbungszeitraum: 01.04.2026 – 30.06.2026
- Runde: "Ausschreibung 2026"
- Stipendientypen: Promotionsstipendium + Josef Guettler

---

## 9. Dateistruktur (neue/geaenderte Dateien)

### Neue Dateien

| Datei | Verantwortung |
|-------|---------------|
| `includes/class-gutachter-token.php` | Token-Generierung, -Validierung, DB-Tabelle |
| `includes/class-gutachter-form.php` | Shortcode `[dgptm_stipendium_gutachten]`, Auto-Save, Abschluss |
| `includes/class-orcid-lookup.php` | ORCID API-Abfrage |
| `includes/class-vorsitz-dashboard.php` | Vorsitzenden-Dashboard (ersetzt Platzhalter) |
| `includes/class-mail-templates.php` | HTML-Mail-Templates (Einladung, Bestaetigung) |
| `templates/gutachten-form.php` | Bewertungsbogen-Template |
| `templates/gutachten-danke.php` | Danke-Seite nach Abgabe |
| `templates/gutachten-ungueltig.php` | Token ungueltig/abgelaufen |
| `templates/vorsitz-dashboard.php` | Vorsitzenden-Dashboard-Template |
| `assets/js/gutachten.js` | Live-Score, Auto-Save, Formular-Logik |
| `assets/js/vorsitz-dashboard.js` | Dashboard-Interaktionen, Gutachter-Einladung |
| `assets/css/gutachten.css` | Gutachten-Formular Styles |
| `assets/css/vorsitz-dashboard.css` | Dashboard Styles |
| `deluge/wf-gs-benachrichtigung.dg` | Workflow: GS setzt Status Geprueft → Mail an Vorsitzenden |

### Geaenderte Dateien

| Datei | Aenderung |
|-------|-----------|
| `dgptm-stipendium.php` | Neue Klassen laden |
| `includes/class-dashboard-tab.php` | Platzhalter-Shortcodes durch echte Implementierung ersetzen |
| `includes/class-settings.php` | Gutachter-Frist Setting hinzufuegen |
| `module.json` | Version 1.1.0 |
