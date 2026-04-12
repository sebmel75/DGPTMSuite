# Stipendienvergabe — Design Spec

**Datum:** 2026-04-12
**Status:** Genehmigt
**Modul-ID:** `stipendium`
**Kategorie:** business

## Zusammenfassung

Digitales Bewerbungs- und Bewertungsverfahren für DGPTM-Stipendien (Promotionsstipendium, Josef Güttler Stipendium, erweiterbar). Ersetzt den bisherigen manuellen E-Mail-Prozess durch ein strukturiertes Online-System mit öffentlichem Bewerbungsformular, internem Bewertungsbogen im Mitglieder-Dashboard und automatisierter Auswertung mit Ranking.

**Architektur-Ansatz:** Hybrid — WordPress für Frontend/UX, Zoho CRM für Daten/Workflow/Compliance, Zoho WorkDrive für Dokumentenspeicherung.

## Entscheidungslog

| Thema | Entscheidung | Bemerkung |
|-------|-------------|-----------|
| Dateispeicherung | Zoho WorkDrive | Ordner pro Bewerbung, Share-Links für Gutachter |
| Bewerbungs-Freigabe | Konfigurierbar | Default: Freigabe durch Vorsitzenden, umschaltbar auf Direktzugriff |
| Gleichstand-Regel | Konfigurierbar | Default: Rubrik A schlägt aus. Alternativen: Mehrheit, Manuell |
| Anonymisierung | Nein | Volle Transparenz, da Dokumente ohnehin identifizierend |
| CRM-Struktur | 2 Custom Modules | `Stipendien` + `Stipendien_Bewertungen` |
| Bewerbungszeitraum | WordPress-Einstellung mit Runden-Konzept | Pro Stipendientyp konfigurierbar |
| DSGVO-Löschfristen | Differenziert | Nicht vergeben: konfigurierbar (Default 12 Monate). Vergeben: 10 Jahre nach Abschluss |
| Berechtigung | ACF-Felder | `stipendiumsrat_mitglied` + `stipendiumsrat_vorsitz` |
| Stipendientypen | Mehrere via Picklist | Promotionsstipendium, Josef Güttler Stipendium, erweiterbar |

---

## 1. Zoho CRM Module & Felder

### Custom Module: `Stipendien`

Ein Record = eine Bewerbung in einer bestimmten Runde.

| Feld | API-Name | Typ | Beschreibung |
|------|----------|-----|-------------|
| Bewerber/in | `Bewerber` | Lookup → Contacts | Verknüpfung zum Contact-Record |
| Stipendientyp | `Stipendientyp` | Picklist | "Promotionsstipendium", "Josef Güttler Stipendium" |
| Runde | `Runde` | Single Line | z.B. "Ausschreibung 2026" |
| Status | `Status` | Picklist | Eingegangen, Freigegeben, In Bewertung, Abgeschlossen, Abgelehnt, Archiviert |
| Eingangsdatum | `Eingangsdatum` | Date | Automatisch beim Erstellen |
| Freigabedatum | `Freigabedatum` | Date | Vom Vorsitzenden gesetzt |
| Lebenslauf | `Lebenslauf_URL` | URL | WorkDrive-Link |
| Motivationsschreiben | `Motivationsschreiben_URL` | URL | WorkDrive-Link |
| Empfehlungsschreiben | `Empfehlungsschreiben_URL` | URL | WorkDrive-Link |
| Studienleistungen | `Studienleistungen_URL` | URL | WorkDrive-Link |
| Publikationen | `Publikationen_URL` | URL | WorkDrive-Link (optional) |
| Ehrenamt/Zusatzquali | `Zusatzqualifikationen_URL` | URL | WorkDrive-Link (optional) |
| WorkDrive Ordner | `WorkDrive_Folder_ID` | Single Line | Ordner-ID für alle Dokumente |
| Gesamtscore (Mittelwert) | `Gesamtscore_Mittelwert` | Decimal | Aggregiert aus Bewertungen (Deluge) |
| Rang | `Rang` | Integer | Berechnet nach Abschluss |
| Foerderfaehig | `Foerderfaehig` | Boolean | true wenn Gesamtscore >= 6.0 |
| Anzahl Bewertungen | `Anzahl_Bewertungen` | Integer | Rollup |
| Vergeben | `Vergeben` | Boolean | true wenn Stipendium an diese Person vergeben |
| Vergabedatum | `Vergabedatum` | Date | Datum der Vergabeentscheidung |
| Stipendium Abschluss | `Stipendium_Abschluss` | Date | Wann das Stipendium endet/endete |
| DSGVO Einwilligung | `DSGVO_Einwilligung` | Boolean | Pflicht |
| DSGVO Einwilligung Datum | `DSGVO_Einwilligung_Datum` | DateTime | Timestamp |
| DSGVO Einwilligung Hash | `DSGVO_Einwilligung_Hash` | Single Line | SHA-256 Hash der IP |
| Runde Enddatum | `Runde_Enddatum` | Date | Bewerbungsschluss dieser Runde (von WordPress übermittelt) |
| Loeschfrist | `Loeschfrist` | Date | Berechnet je nach Vergabestatus |

### Custom Module: `Stipendien_Bewertungen`

Ein Record = eine Bewertung eines Gutachters für eine Bewerbung.

| Feld | API-Name | Typ | Beschreibung |
|------|----------|-----|-------------|
| Stipendium | `Stipendium` | Lookup → Stipendien | Verknüpfung zur Bewerbung |
| Gutachter/in | `Gutachter` | Lookup → Contacts | Verknüpfung zum Gutachter |
| A1 Note | `A1_Note` | Integer (1-10) | Wissenschaftliche Relevanz |
| A2 Note | `A2_Note` | Integer (1-10) | Klare Forschungsfrage |
| A3 Note | `A3_Note` | Integer (1-10) | Erkenntnisfortschritt |
| A Kommentar | `A_Kommentar` | Multi Line | Optional |
| B1 Note | `B1_Note` | Integer (1-10) | Beitrag zum Fach |
| B2 Note | `B2_Note` | Integer (1-10) | Praxisrelevante Impulse |
| B3 Note | `B3_Note` | Integer (1-10) | Bezug zum Berufsfeld |
| B Kommentar | `B_Kommentar` | Multi Line | Optional |
| C1 Note | `C1_Note` | Integer (1-10) | Methodik |
| C2 Note | `C2_Note` | Integer (1-10) | Realisierbarkeit |
| C3 Note | `C3_Note` | Integer (1-10) | Aufbau/Planung |
| C Kommentar | `C_Kommentar` | Multi Line | Optional |
| D1 Note | `D1_Note` | Integer (1-10) | Akademische Leistungen |
| D2 Note | `D2_Note` | Integer (1-10) | Fachliche Kompetenzen |
| D3 Note | `D3_Note` | Integer (1-10) | Erkennbares Profil |
| D Kommentar | `D_Kommentar` | Multi Line | Optional |
| Gesamtanmerkungen | `Gesamtanmerkungen` | Multi Line | Freitext |
| A Gewichtet | `A_Gewichtet` | Decimal | Deluge: avg(A1,A2,A3) x 0.30 |
| B Gewichtet | `B_Gewichtet` | Decimal | Deluge: avg(B1,B2,B3) x 0.30 |
| C Gewichtet | `C_Gewichtet` | Decimal | Deluge: avg(C1,C2,C3) x 0.25 |
| D Gewichtet | `D_Gewichtet` | Decimal | Deluge: avg(D1,D2,D3) x 0.15 |
| Gesamtscore | `Gesamtscore` | Decimal | Summe A-D gewichtet (max 10) |
| Bewertungsdatum | `Bewertungsdatum` | DateTime | Automatisch |
| Status | `Status` | Picklist | Entwurf, Abgeschlossen |

### Blueprint: Stipendien-Workflow

```
Eingegangen → [Vorsitzender: Freigeben] → Freigegeben
Freigegeben → [System: Erste Bewertung eingegangen] → In Bewertung
In Bewertung → [System: Alle Bewertungen abgegeben] → Abgeschlossen
Abgeschlossen → [Vorsitzender: Archivieren] → Archiviert
```

---

## 2. Zoho WorkDrive Struktur

### Ordnerstruktur

```
DGPTM Stipendien/                              (Team Folder)
├── Promotionsstipendium/
│   ├── 2026 - Ausschreibung 2026/
│   │   ├── Bewerbung_001_Nachname_Vorname/
│   │   │   ├── Lebenslauf.pdf
│   │   │   ├── Motivationsschreiben.pdf
│   │   │   ├── Empfehlungsschreiben.pdf
│   │   │   ├── Studienleistungen.pdf
│   │   │   ├── Publikationen.pdf              (optional)
│   │   │   └── Zusatzqualifikationen.pdf      (optional)
│   │   └── Bewerbung_002_Nachname_Vorname/
│   └── 2027 - .../
├── Josef Güttler Stipendium/
│   ├── 2026/
│   └── ...
└── [Weiterer Typ]/
```

---

## 3. Deluge Custom Functions

### 3.1 Score-Berechnung

**Trigger:** Workflow Rule auf `Stipendien_Bewertungen`, bei Edit wenn Status = "Abgeschlossen"

```deluge
a_avg = (input.A1_Note + input.A2_Note + input.A3_Note) / 3.0;
b_avg = (input.B1_Note + input.B2_Note + input.B3_Note) / 3.0;
c_avg = (input.C1_Note + input.C2_Note + input.C3_Note) / 3.0;
d_avg = (input.D1_Note + input.D2_Note + input.D3_Note) / 3.0;

a_gew = a_avg * 0.30;
b_gew = b_avg * 0.30;
c_gew = c_avg * 0.25;
d_gew = d_avg * 0.15;

gesamt = a_gew + b_gew + c_gew + d_gew;

zoho.crm.updateRecord("Stipendien_Bewertungen", input.id,
  {"A_Gewichtet": a_gew, "B_Gewichtet": b_gew,
   "C_Gewichtet": c_gew, "D_Gewichtet": d_gew,
   "Gesamtscore": gesamt});
```

### 3.2 Aggregation auf Stipendien-Record

**Trigger:** Workflow Rule auf `Stipendien_Bewertungen`, nach Score-Berechnung

```deluge
stip_id = input.Stipendium.id;
bewertungen = zoho.crm.getRelatedRecords("Stipendien_Bewertungen", "Stipendien", stip_id);

sum_score = 0.0;
count = 0;

for each bew in bewertungen
{
    if (bew.get("Status") == "Abgeschlossen")
    {
        sum_score = sum_score + bew.get("Gesamtscore").toDecimal();
        count = count + 1;
    }
}

if (count > 0)
{
    mittelwert = sum_score / count;
    foerderfaehig = mittelwert >= 6.0;

    zoho.crm.updateRecord("Stipendien", stip_id,
      {"Gesamtscore_Mittelwert": mittelwert,
       "Anzahl_Bewertungen": count,
       "Foerderfaehig": foerderfaehig});
}
```

### 3.3 Ranking-Berechnung

**Trigger:** Custom Button auf Stipendien-Listenansicht (manuell durch Vorsitzenden) oder Status-Transition "Abgeschlossen"

```deluge
runde = input.Runde;
typ = input.Stipendientyp;
stipendien = zoho.crm.searchRecords("Stipendien",
  "(Runde:equals:" + runde + ") and (Stipendientyp:equals:" + typ + ") and (Foerderfaehig:equals:true)");

sorted = stipendien.sort("Gesamtscore_Mittelwert", false);

rang = 1;
prev_score = -1;

for each stip in sorted
{
    score = stip.get("Gesamtscore_Mittelwert").toDecimal();
    if (score != prev_score)
    {
        current_rang = rang;
    }
    // Bei Gleichstand: Tiebreaker anwenden (konfigurierbar)
    // Default "rubrik_a": Höherer Durchschnitt in Rubrik A gewinnt
    // "mehrheit": Wer von mehr Gutachtern besser bewertet wurde
    // "manuell": Gleicher Rang, Vorsitzender entscheidet
    zoho.crm.updateRecord("Stipendien", stip.get("id"), {"Rang": current_rang});
    prev_score = score;
    rang = rang + 1;
}
```

### 3.4 Löschfrist-Berechnung

**Trigger:** Workflow Rule bei Status-Änderung auf Stipendien

```deluge
if (input.Vergeben == true)
{
    if (input.Stipendium_Abschluss != null)
    {
        frist = input.Stipendium_Abschluss.addYear(10);
    }
    else
    {
        frist = null;  // Laufendes Stipendium: keine Löschfrist
    }
}
else if (input.Status == "Abgeschlossen" || input.Status == "Archiviert")
{
    // Nicht vergeben: konfigurierbare Frist nach Rundenende
    runden_ende = input.Runde_Enddatum;
    loeschfrist_monate = 12; // Default, konfigurierbar via WordPress-Settings
    frist = runden_ende.addMonth(loeschfrist_monate);
}

zoho.crm.updateRecord("Stipendien", input.id, {"Loeschfrist": frist});
```

### 3.5 DSGVO Scheduled Function

**Trigger:** Täglicher Cron, 02:00 Uhr

```deluge
today = zoho.currentdate;
warn_date = today.addDay(30);

// Erinnerung: Löschfrist naht
faellige = zoho.crm.searchRecords("Stipendien",
  "(Loeschfrist:not_equal:null) and (Loeschfrist:less_than:" + warn_date + ") and (Loeschfrist:greater_than:" + today + ") and (Status:not_equal:Archiviert)");

// Vorsitzender-E-Mail aus CRM-Organisation-Settings oder dediziertem Contact
vorsitzender = zoho.crm.searchRecords("Contacts", "(Stipendiumsrat_Vorsitz:equals:true)");
vorsitzender_email = vorsitzender.get(0).get("Email");

for each stip in faellige
{
    sendmail [from: "noreply@perfusiologie.de"
              to: vorsitzender_email
              subject: "DSGVO: Stipendium-Löschfrist naht"
              message: "Bewerbung " + stip.get("Name") + " wird am " + stip.get("Loeschfrist") + " gelöscht."];
}

// Automatische Löschung (nur Records mit Löschfrist und Status Archiviert)
loesch_faellig = zoho.crm.searchRecords("Stipendien",
  "(Loeschfrist:not_equal:null) and (Loeschfrist:less_than:" + today + ") and (Status:equals:Archiviert)");

for each stip in loesch_faellig
{
    folder_id = stip.get("WorkDrive_Folder_ID");
    if (folder_id != null)
    {
        zoho.workdrive.deleteFile(folder_id);
    }
    bewertungen = zoho.crm.getRelatedRecords("Stipendien_Bewertungen", "Stipendien", stip.get("id"));
    for each bew in bewertungen
    {
        zoho.crm.deleteRecord("Stipendien_Bewertungen", bew.get("id"));
    }
    zoho.crm.deleteRecord("Stipendien", stip.get("id"));
}
```

---

## 4. WordPress-Modul Architektur

### Modulstruktur

```
modules/business/stipendium/
├── module.json
├── dgptm-stipendium.php              (Hauptklasse, Singleton)
├── includes/
│   ├── class-bewerbung-form.php      (Öffentliches Bewerbungsformular)
│   ├── class-bewertung-form.php      (Bewertungsbogen im Dashboard)
│   ├── class-dashboard-tab.php       (Dashboard-Integration)
│   ├── class-zoho-stipendium.php     (Zoho CRM API: Stipendien + Bewertungen)
│   ├── class-workdrive.php           (WorkDrive Upload/Ordner-Management)
│   └── class-settings.php            (Modul-Einstellungen)
├── templates/
│   ├── bewerbung-form.php            (Formular-Template, öffentlich)
│   ├── bewerbung-geschlossen.php     (Hinweis außerhalb Zeitraum)
│   ├── dashboard-uebersicht.php      (Bewerbungsliste im Dashboard)
│   ├── dashboard-bewertung.php       (Bewertungsbogen-Template)
│   ├── dashboard-auswertung.php      (Ranking/Auswertung, Vorsitzender)
│   └── admin-settings.php            (Backend-Einstellungen)
├── assets/
│   ├── js/
│   │   ├── bewerbung.js              (Formular-Validierung + Upload)
│   │   ├── bewertung.js              (Score-Vorschau + Formular-Logik)
│   │   └── auswertung.js             (Ranking-Anzeige, PDF-Trigger)
│   └── css/
│       └── stipendium.css
└── docs/
```

### module.json

```json
{
  "id": "stipendium",
  "name": "Stipendienvergabe",
  "description": "Digitales Bewerbungs- und Bewertungsverfahren für DGPTM-Stipendien",
  "version": "1.0.0",
  "author": "Sebastian Melzer",
  "main_file": "dgptm-stipendium.php",
  "dependencies": ["crm-abruf"],
  "optional_dependencies": ["mitglieder-dashboard"],
  "wp_dependencies": {
    "plugins": ["advanced-custom-fields"]
  },
  "requires_php": "7.4",
  "requires_wp": "5.8",
  "category": "business",
  "icon": "dashicons-awards",
  "active": false,
  "can_export": true,
  "critical": false
}
```

### Hauptklasse: Verantwortlichkeiten

```
DGPTM_Stipendium (Singleton)
├── init()
│   ├── Shortcodes: [dgptm_stipendium_bewerbung typ="..."]
│   ├── ACF-Felder: stipendiumsrat_mitglied, stipendiumsrat_vorsitz
│   ├── Dashboard-Tabs registrieren (wenn mitglieder-dashboard aktiv)
│   └── Admin-Settings registrieren
├── AJAX-Endpoints
│   ├── dgptm_stipendium_submit        (Bewerbung einreichen, public, nonce)
│   ├── dgptm_stipendium_bewerten      (Bewertung abgeben, auth, nonce)
│   ├── dgptm_stipendium_freigeben     (Freigabe, Vorsitz, nonce)
│   ├── dgptm_stipendium_auswertung    (Ranking abrufen, Vorsitz, nonce)
│   └── dgptm_stipendium_export_pdf    (PDF-Export, Vorsitz, nonce)
└── Hooks
    ├── wp_enqueue_scripts → Assets nur auf relevanten Seiten
    └── dgptm_suite_modules_loaded → Abhängigkeiten prüfen
```

### Shortcode-Logik

`[dgptm_stipendium_bewerbung typ="promotionsstipendium"]`

```
Ist Bewerbungszeitraum für diesen Typ aktiv?
├── Nein → Template: bewerbung-geschlossen.php
│          "Der nächste Bewerbungszeitraum beginnt am {datum}."
└── Ja  → Template: bewerbung-form.php
           Step 1: Persönliche Daten (Name, E-Mail, Telefon)
           Step 2: Dokument-Upload (4 Pflicht + 2 Optional)
           Step 3: DSGVO-Einwilligung + Zusammenfassung
           Step 4: Bestätigung ("Jetzt einreichen")
```

---

## 5. Dashboard-Komponenten

### Tab-Registrierung

**Tab: "Stipendien"** (alle Ratsmitglieder)
```php
'id'         => 'stipendien',
'label'      => 'Stipendien',
'permission' => 'acf:stipendiumsrat_mitglied',
'content'    => '[dgptm_stipendium_dashboard]',
'order'      => 60
```

**Unter-Tab: "Stipendien-Auswertung"** (nur Vorsitzender)
```php
'id'         => 'stipendien_auswertung',
'parent'     => 'stipendien',
'permission' => 'acf:stipendiumsrat_vorsitz',
'content'    => '[dgptm_stipendium_auswertung]',
'order'      => 61
```

### Ansicht: Gutachter (dashboard-uebersicht.php)

- Liste aller freigegebenen Bewerbungen der aktuellen Runde
- Pro Bewerbung: Name, Eingangsdatum, Dokument-Links (WorkDrive Share-Links), Status
- Button "Bewertung abgeben" → Inline-Bewertungsbogen
- Eigene abgegebene Bewertung einsehbar (nicht die anderer Gutachter)

### Ansicht: Bewertungsbogen (dashboard-bewertung.php)

- 4 Rubriken (A-D) mit je 3 Leitfragen
- Pro Leitfrage: Dropdown/Slider 1-10 mit Tooltip (Bewertungsstufen-Referenz)
- Live-Score-Berechnung in JavaScript
- Optionale Kommentarfelder je Rubrik + Gesamtanmerkungen
- "Als Entwurf speichern" (Zoho Status: Entwurf) + "Bewertung abschließen" (Status: Abgeschlossen)
- Nach Abschluss nicht mehr editierbar

### Ansicht: Auswertung/Vorsitzender (dashboard-auswertung.php)

- Dropdown: Stipendientyp + Runde wählen
- Ranking-Tabelle: Rang, Name, Gesamtscore, Teilscores A-D
- Bewerbungen < 6.0 markiert als "nicht förderfähig"
- Aufklappbare Detailansicht: Einzelbewertungen aller Gutachter pro Bewerbung
- Freigabe-Steuerung (wenn Modus = "vorsitz"): Neue Bewerbungen freigeben/ablehnen
- Vergabe-Dialog: Stipendium einer Bewerbung zuweisen
- Buttons: "PDF-Export herunterladen", "Runde archivieren"

---

## 6. Einstellungen (WordPress)

| Einstellung | Typ | Default | Beschreibung |
|-------------|-----|---------|-------------|
| Stipendientypen | Repeater | 2 Einträge | Pro Typ: Bezeichnung, Runde, Start, Ende |
| `freigabe_modus` | Select | `vorsitz` | `vorsitz` oder `direkt` |
| `gleichstand_regel` | Select | `rubrik_a` | `rubrik_a`, `mehrheit`, `manuell` |
| `loeschfrist_monate_nicht_vergeben` | Number | 12 | Monate nach Rundenende |
| `loeschfrist_jahre_vergeben` | Number | 10 | Jahre nach Stipendiums-Abschluss |
| `auto_loeschung` | Boolean | false | Automatisch löschen oder nur erinnern |
| `bestaetigungsmail_text` | Textarea | (Vorlage) | E-Mail-Text Eingangsbestätigung |
| `workdrive_team_folder_id` | Text | — | WorkDrive Root-Ordner ID |
| `benachrichtigung_vorsitz_email` | Email | — | E-Mail des Vorsitzenden |

---

## 7. API-Interaktionsmatrix

| WordPress-Aktion | Zoho API Calls |
|-------------------|----------------|
| Bewerbung einreichen | 1. `POST /Contacts/search` → 2. `POST/PUT /Contacts` → 3. WorkDrive: Ordner erstellen → 4. WorkDrive: Dateien hochladen → 5. `POST /Stipendien` |
| Dashboard laden (Gutachter) | 1. `GET /Stipendien/search?criteria=(Runde+Typ)` → 2. `GET /Stipendien_Bewertungen/search?criteria=(Gutachter)` |
| Bewertung speichern | 1. `POST/PUT /Stipendien_Bewertungen` |
| Bewertung abschließen | 1. `PUT /Stipendien_Bewertungen/{id}` Status→Abgeschlossen (Deluge feuert) |
| Freigabe (Vorsitz) | 1. `PUT /Stipendien/{id}` Status→Freigegeben |
| Auswertung laden | 1. COQL: alle Bewertungen einer Runde in einem Call |
| PDF-Export | 1. Deluge Custom Function triggern |
| Runde archivieren | 1. Bulk-Update Status→Archiviert |

### Caching (WordPress Transients)

| Daten | TTL | Invalidierung |
|-------|-----|---------------|
| Bewerbungsliste einer Runde | 5 min | Bei Einreichung, Freigabe |
| Eigene Bewertungen | 5 min | Bei Speichern/Abschließen |
| Auswertung/Ranking | 10 min | Bei Bewertungs-Abschluss |
| Bewerbungszeitraum aktiv? | 1 h | Rein lokal, keine API |
| WorkDrive Share-Links | 24 h | Bei neuem Upload |

---

## 8. DSGVO-Konzept

| Anforderung | Umsetzung |
|-------------|-----------|
| Rechtsgrundlage (Bewerbung) | Art. 6 Abs. 1 lit. a — Einwilligung |
| Rechtsgrundlage (Vergeben) | Art. 6 Abs. 1 lit. b — Vertragserfüllung |
| Rechtsgrundlage (Archiv) | Art. 6 Abs. 1 lit. f — Berechtigtes Interesse |
| Einwilligung | Pflicht-Checkbox mit Link zur Datenschutzerklärung |
| Zweckbindung | Daten ausschließlich für Stipendienvergabe |
| Datenminimierung | Nur Dokumente gemäß Gutachterleitfaden |
| Speicherort | Zoho CRM (EU) + Zoho WorkDrive (EU) |
| Zugriffsrechte | WordPress: ACF-Felder. Zoho: Modul-Berechtigungen |
| Löschfrist (nicht vergeben) | Konfigurierbar, Default 12 Monate nach Rundenende |
| Löschfrist (vergeben, abgeschlossen) | 10 Jahre nach Stipendiums-Abschluss |
| Löschfrist (laufend) | Keine Löschung während Laufzeit |
| Lösch-Umfang | Stipendien-Record + Bewertungen + WorkDrive-Ordner |
| Einwilligungs-Log | Timestamp + IP-Hash (SHA-256) im CRM-Record |
| Erinnerung | 30 Tage vor Löschfrist per E-Mail an Vorsitzenden |

---

## 9. E-Mail-Benachrichtigungen

| Auslöser | Empfänger | Template |
|----------|-----------|----------|
| Bewerbung eingereicht | Bewerber/in | Eingangsbestätigung mit Referenznummer |
| Bewerbung eingereicht | Vorsitzender | Neue Bewerbung zur Prüfung/Freigabe |
| Bewerbung freigegeben | Alle Ratsmitglieder | Bewerbung zur Bewertung verfügbar |
| Bewertung abgeschlossen | Vorsitzender | Gutachter X hat Bewerbung Y bewertet |
| Alle Bewertungen komplett | Vorsitzender | Auswertung bereit |
| Löschfrist naht | Vorsitzender | 30-Tage-Warnung |

---

## 10. Fehlerbehandlung

| Szenario | Verhalten |
|----------|-----------|
| Zoho API nicht erreichbar | Bewerbung: Fehlermeldung. Dashboard: Cached Daten mit Zeitstempel |
| WorkDrive-Upload fehlgeschlagen | Fallback: URLs im CRM-Bemerkungsfeld |
| Token abgelaufen | Auto-Refresh via crm-abruf get_valid_access_token() |
| Doppelte Bewerbung | Prüfung Contact-ID + Runde + Typ. Hinweis anzeigen |
| Gutachter bewertet sich selbst | Prüfung Gutachter-ID ungleich Bewerber-ID |
| Bewertung nach Rundenabschluss | Button deaktiviert |

---

## 11. Deluge Setup-Skript

Einmalig auszuführen in Zoho CRM Developer Space. Erstellt:
1. Custom Module "Stipendien" mit allen Feldern
2. Custom Module "Stipendien_Bewertungen" mit allen Feldern
3. Lookup-Beziehungen zwischen Modulen
4. Workflow Rules (Score-Berechnung, Aggregation, Löschfrist)
5. Scheduled Function (DSGVO Cleanup, täglich 02:00)
6. Custom Button (Ranking-Berechnung)
7. E-Mail-Templates (6 Templates)
8. Blueprint (4 Status-Transitionen)

Details: Siehe Abschnitt 3 für die Deluge-Logik aller Funktionen.

---

## Workflow Rules & Custom Functions (Übersicht)

| Name | Trigger | Funktion |
|------|---------|----------|
| `stipendium_score_berechnen` | Bewertung: Edit, Status=Abgeschlossen | Score-Berechnung |
| `stipendium_aggregation` | Bewertung: nach Score-Berechnung | Mittelwert aggregieren |
| `stipendium_ranking` | Custom Button / Status-Transition | Rang berechnen |
| `stipendium_loeschfrist` | Stipendium: Status-Änderung | Löschfrist setzen |
| `stipendium_status_check` | Bewertung: Create | Prüft ob alle Gutachter fertig |
| `stipendium_dsgvo_cleanup` | Scheduled Function (täglich) | Löschfristen + Erinnerungen |
| `stipendium_pdf_export` | Custom Button | PDF via Zoho Writer |
