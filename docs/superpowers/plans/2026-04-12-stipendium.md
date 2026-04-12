# Stipendienvergabe Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Digitales Bewerbungs- und Bewertungsverfahren fuer DGPTM-Stipendien — alle Infrastruktur-Komponenten bis zur Feedback-Grenze des Stipendiumsrats.

**Architecture:** Hybrid — Zoho CRM (2 Custom Modules + Deluge Workflows) fuer Daten/Logik, Zoho WorkDrive fuer Dokumente, WordPress-Modul fuer Frontend. Dieser Plan deckt Phase A (Zoho) und Phase B (WordPress Backend) ab. Phase C (Frontend) wartet auf Feedback.

**Tech Stack:** PHP 7.4+ (WordPress), Deluge (Zoho CRM), Zoho CRM v8 REST API, Zoho WorkDrive API, ACF

**Spec:** `docs/superpowers/specs/2026-04-12-stipendium-design.md`

---

## Dateistruktur

### Neue Dateien

| Datei | Verantwortung |
|-------|---------------|
| `modules/business/stipendium/includes/class-settings.php` | Admin-Einstellungen (Stipendientypen, Runden, Konfiguration) |
| `modules/business/stipendium/includes/class-zoho-stipendium.php` | Zoho CRM API-Wrapper fuer Stipendien + Bewertungen |
| `modules/business/stipendium/includes/class-workdrive.php` | WorkDrive Ordner-/Datei-Management |
| `modules/business/stipendium/includes/class-dashboard-tab.php` | Dashboard-Tab Registrierung + Shortcodes |
| `modules/business/stipendium/templates/admin-settings.php` | Admin-Settings HTML-Template |
| `modules/business/stipendium/deluge/setup-stipendien-modul.dg` | Einmaliges CRM-Setup-Skript: Modul + Felder |
| `modules/business/stipendium/deluge/setup-bewertungen-modul.dg` | Einmaliges CRM-Setup-Skript: Bewertungen-Modul |
| `modules/business/stipendium/deluge/wf-score-berechnung.dg` | Workflow: Score-Berechnung |
| `modules/business/stipendium/deluge/wf-aggregation.dg` | Workflow: Aggregation auf Stipendien-Record |
| `modules/business/stipendium/deluge/wf-loeschfrist.dg` | Workflow: Loeschfrist-Berechnung |
| `modules/business/stipendium/deluge/cf-ranking.dg` | Custom Function: Ranking-Berechnung |
| `modules/business/stipendium/deluge/sf-dsgvo-cleanup.dg` | Scheduled Function: DSGVO-Loesch-Cron |

### Bestehende Dateien (Modifikation)

| Datei | Aenderung |
|-------|-----------|
| `modules/business/stipendium/module.json` | Dependencies aktualisieren (`crm-abruf` Pflicht, Version 1.0.0) |
| `modules/business/stipendium/dgptm-stipendium.php` | Neue Klassen laden, ACF-Felder registrieren |

---

## Phase A: Zoho CRM Infrastructure

### Task 1: Deluge Setup-Skript — Custom Module "Stipendien"

**Files:**
- Create: `modules/business/stipendium/deluge/setup-stipendien-modul.dg`

Dieses Skript wird einmalig in Zoho CRM > Setup > Developer Space > Functions > Custom Function (standalone) ausgefuehrt. Es erstellt das Custom Module "Stipendien" mit allen Feldern.

**Hinweis:** Zoho CRM erlaubt keine programmatische Modul-Erstellung via Deluge `zoho.crm.*` APIs. Custom Modules werden ueber die Settings API (REST) oder manuell im UI erstellt. Dieses Skript dokumentiert die exakte Feld-Konfiguration und erstellt die Felder per API, nachdem das Modul manuell angelegt wurde.

- [ ] **Step 1: Deluge-Skript erstellen**

```
modules/business/stipendium/deluge/setup-stipendien-modul.dg
```

```deluge
// ============================================================
// DGPTM Stipendien — Custom Module Setup
// ============================================================
// VORBEREITUNG: Custom Module "Stipendien" manuell anlegen:
//   Zoho CRM > Setup > Customization > Modules and Fields
//   > Create New Module > Name: "Stipendien" > Singular: "Stipendium"
//
// Danach dieses Skript als Custom Function (standalone) ausfuehren.
// Es erstellt alle Felder im Modul "Stipendien".
// ============================================================

module_api_name = "Stipendien";

// --- Lookup: Bewerber (Contact) ---
// Manuell anlegen: Lookup-Feld "Bewerber" → Contacts
// (Lookup-Felder koennen nicht per API erstellt werden)

// --- Picklist: Stipendientyp ---
field_stipendientyp = Map();
field_stipendientyp.put("field_label", "Stipendientyp");
field_stipendientyp.put("data_type", "picklist");
field_stipendientyp.put("pick_list_values", [
    {"display_value": "Promotionsstipendium"},
    {"display_value": "Josef Guettler Stipendium"}
]);
resp = zoho.crm.invokeConnector("crm.post", {
    "url": "https://www.zohoapis.eu/crm/v8/settings/fields?module=" + module_api_name,
    "body": {"fields": [field_stipendientyp]}
});
info "Stipendientyp: " + resp;

// --- Single Line: Runde ---
field_runde = Map();
field_runde.put("field_label", "Runde");
field_runde.put("data_type", "text");
field_runde.put("length", 100);
resp = zoho.crm.invokeConnector("crm.post", {
    "url": "https://www.zohoapis.eu/crm/v8/settings/fields?module=" + module_api_name,
    "body": {"fields": [field_runde]}
});
info "Runde: " + resp;

// --- Picklist: Status ---
field_status = Map();
field_status.put("field_label", "Status");
field_status.put("data_type", "picklist");
field_status.put("pick_list_values", [
    {"display_value": "Eingegangen"},
    {"display_value": "Freigegeben"},
    {"display_value": "In Bewertung"},
    {"display_value": "Abgeschlossen"},
    {"display_value": "Abgelehnt"},
    {"display_value": "Archiviert"}
]);
resp = zoho.crm.invokeConnector("crm.post", {
    "url": "https://www.zohoapis.eu/crm/v8/settings/fields?module=" + module_api_name,
    "body": {"fields": [field_status]}
});
info "Status: " + resp;

// --- Date-Felder ---
date_fields = List();
date_fields.add({"field_label": "Eingangsdatum", "data_type": "date"});
date_fields.add({"field_label": "Freigabedatum", "data_type": "date"});
date_fields.add({"field_label": "Vergabedatum", "data_type": "date"});
date_fields.add({"field_label": "Stipendium_Abschluss", "data_type": "date"});
date_fields.add({"field_label": "Runde_Enddatum", "data_type": "date"});
date_fields.add({"field_label": "Loeschfrist", "data_type": "date"});
date_fields.add({"field_label": "DSGVO_Einwilligung_Datum", "data_type": "datetime"});

for each df in date_fields
{
    resp = zoho.crm.invokeConnector("crm.post", {
        "url": "https://www.zohoapis.eu/crm/v8/settings/fields?module=" + module_api_name,
        "body": {"fields": [df]}
    });
    info df.get("field_label") + ": " + resp;
}

// --- URL-Felder (WorkDrive-Links) ---
url_fields = List();
url_fields.add("Lebenslauf_URL");
url_fields.add("Motivationsschreiben_URL");
url_fields.add("Empfehlungsschreiben_URL");
url_fields.add("Studienleistungen_URL");
url_fields.add("Publikationen_URL");
url_fields.add("Zusatzqualifikationen_URL");

for each uf in url_fields
{
    field = Map();
    field.put("field_label", uf);
    field.put("data_type", "website");
    resp = zoho.crm.invokeConnector("crm.post", {
        "url": "https://www.zohoapis.eu/crm/v8/settings/fields?module=" + module_api_name,
        "body": {"fields": [field]}
    });
    info uf + ": " + resp;
}

// --- Text-Felder ---
text_fields = List();
text_fields.add("WorkDrive_Folder_ID");
text_fields.add("DSGVO_Einwilligung_Hash");

for each tf in text_fields
{
    field = Map();
    field.put("field_label", tf);
    field.put("data_type", "text");
    field.put("length", 255);
    resp = zoho.crm.invokeConnector("crm.post", {
        "url": "https://www.zohoapis.eu/crm/v8/settings/fields?module=" + module_api_name,
        "body": {"fields": [field]}
    });
    info tf + ": " + resp;
}

// --- Decimal-Felder ---
field_score = Map();
field_score.put("field_label", "Gesamtscore_Mittelwert");
field_score.put("data_type", "decimal");
field_score.put("decimal_place", 2);
resp = zoho.crm.invokeConnector("crm.post", {
    "url": "https://www.zohoapis.eu/crm/v8/settings/fields?module=" + module_api_name,
    "body": {"fields": [field_score]}
});
info "Gesamtscore_Mittelwert: " + resp;

// --- Integer-Felder ---
int_fields = List();
int_fields.add("Rang");
int_fields.add("Anzahl_Bewertungen");

for each intf in int_fields
{
    field = Map();
    field.put("field_label", intf);
    field.put("data_type", "integer");
    resp = zoho.crm.invokeConnector("crm.post", {
        "url": "https://www.zohoapis.eu/crm/v8/settings/fields?module=" + module_api_name,
        "body": {"fields": [field]}
    });
    info intf + ": " + resp;
}

// --- Boolean-Felder ---
bool_fields = List();
bool_fields.add("Foerderfaehig");
bool_fields.add("Vergeben");
bool_fields.add("DSGVO_Einwilligung");

for each bf in bool_fields
{
    field = Map();
    field.put("field_label", bf);
    field.put("data_type", "boolean");
    resp = zoho.crm.invokeConnector("crm.post", {
        "url": "https://www.zohoapis.eu/crm/v8/settings/fields?module=" + module_api_name,
        "body": {"fields": [field]}
    });
    info bf + ": " + resp;
}

info "=== Stipendien-Modul Setup abgeschlossen ===";
```

- [ ] **Step 2: Modul manuell in Zoho CRM anlegen**

Zoho CRM > Setup > Customization > Modules and Fields > Create New Module:
- Module Name: `Stipendien`
- Singular Name: `Stipendium`
- Tab Visibility: Alle Profile die Zugriff brauchen

- [ ] **Step 3: Lookup-Feld manuell anlegen**

Im Stipendien-Modul > Layout:
- Neues Feld: Lookup > Name: "Bewerber" > Zielmodul: "Contacts"

- [ ] **Step 4: Skript ausfuehren**

Zoho CRM > Setup > Developer Space > Functions > New Function:
- Name: `setup_stipendien_modul`
- Category: Standalone
- Code einfuegen und ausfuehren
- Info-Logs pruefen: Alle Felder sollten "success" zeigen

- [ ] **Step 5: Commit**

```bash
git add modules/business/stipendium/deluge/setup-stipendien-modul.dg
git commit -m "feat(stipendium): Deluge Setup-Skript fuer Stipendien CRM-Modul"
```

---

### Task 2: Deluge Setup-Skript — Custom Module "Stipendien_Bewertungen"

**Files:**
- Create: `modules/business/stipendium/deluge/setup-bewertungen-modul.dg`

- [ ] **Step 1: Deluge-Skript erstellen**

```
modules/business/stipendium/deluge/setup-bewertungen-modul.dg
```

```deluge
// ============================================================
// DGPTM Stipendien_Bewertungen — Custom Module Setup
// ============================================================
// VORBEREITUNG: Custom Module "Stipendien_Bewertungen" manuell anlegen:
//   Zoho CRM > Setup > Customization > Modules and Fields
//   > Create New Module > Name: "Stipendien_Bewertungen"
//   > Singular: "Stipendien_Bewertung"
//
// Lookup-Felder manuell anlegen:
//   1. "Stipendium" → Stipendien
//   2. "Gutachter" → Contacts
//
// Danach dieses Skript als Custom Function (standalone) ausfuehren.
// ============================================================

module_api_name = "Stipendien_Bewertungen";

// --- Noten-Felder (Integer 1-10) ---
// A: Wissenschaftlicher Wert
note_fields = List();
note_fields.add("A1_Note");
note_fields.add("A2_Note");
note_fields.add("A3_Note");
note_fields.add("B1_Note");
note_fields.add("B2_Note");
note_fields.add("B3_Note");
note_fields.add("C1_Note");
note_fields.add("C2_Note");
note_fields.add("C3_Note");
note_fields.add("D1_Note");
note_fields.add("D2_Note");
note_fields.add("D3_Note");

for each nf in note_fields
{
    field = Map();
    field.put("field_label", nf);
    field.put("data_type", "integer");
    resp = zoho.crm.invokeConnector("crm.post", {
        "url": "https://www.zohoapis.eu/crm/v8/settings/fields?module=" + module_api_name,
        "body": {"fields": [field]}
    });
    info nf + ": " + resp;
}

// --- Kommentar-Felder (Multi Line) ---
comment_fields = List();
comment_fields.add("A_Kommentar");
comment_fields.add("B_Kommentar");
comment_fields.add("C_Kommentar");
comment_fields.add("D_Kommentar");
comment_fields.add("Gesamtanmerkungen");

for each cf in comment_fields
{
    field = Map();
    field.put("field_label", cf);
    field.put("data_type", "textarea");
    resp = zoho.crm.invokeConnector("crm.post", {
        "url": "https://www.zohoapis.eu/crm/v8/settings/fields?module=" + module_api_name,
        "body": {"fields": [field]}
    });
    info cf + ": " + resp;
}

// --- Gewichtete Score-Felder (Decimal) ---
score_fields = List();
score_fields.add("A_Gewichtet");
score_fields.add("B_Gewichtet");
score_fields.add("C_Gewichtet");
score_fields.add("D_Gewichtet");
score_fields.add("Gesamtscore");

for each sf in score_fields
{
    field = Map();
    field.put("field_label", sf);
    field.put("data_type", "decimal");
    field.put("decimal_place", 2);
    resp = zoho.crm.invokeConnector("crm.post", {
        "url": "https://www.zohoapis.eu/crm/v8/settings/fields?module=" + module_api_name,
        "body": {"fields": [field]}
    });
    info sf + ": " + resp;
}

// --- DateTime: Bewertungsdatum ---
field_datum = Map();
field_datum.put("field_label", "Bewertungsdatum");
field_datum.put("data_type", "datetime");
resp = zoho.crm.invokeConnector("crm.post", {
    "url": "https://www.zohoapis.eu/crm/v8/settings/fields?module=" + module_api_name,
    "body": {"fields": [field_datum]}
});
info "Bewertungsdatum: " + resp;

// --- Picklist: Status ---
field_status = Map();
field_status.put("field_label", "Status");
field_status.put("data_type", "picklist");
field_status.put("pick_list_values", [
    {"display_value": "Entwurf"},
    {"display_value": "Abgeschlossen"}
]);
resp = zoho.crm.invokeConnector("crm.post", {
    "url": "https://www.zohoapis.eu/crm/v8/settings/fields?module=" + module_api_name,
    "body": {"fields": [field_status]}
});
info "Status: " + resp;

info "=== Stipendien_Bewertungen-Modul Setup abgeschlossen ===";
```

- [ ] **Step 2: Modul + Lookups manuell anlegen, Skript ausfuehren**

Gleicher Ablauf wie Task 1: Modul manuell erstellen, Lookup-Felder manuell, dann Skript ausfuehren.

- [ ] **Step 3: Commit**

```bash
git add modules/business/stipendium/deluge/setup-bewertungen-modul.dg
git commit -m "feat(stipendium): Deluge Setup-Skript fuer Bewertungen CRM-Modul"
```

---

### Task 3: Deluge Workflow Rules — Score-Berechnung + Aggregation

**Files:**
- Create: `modules/business/stipendium/deluge/wf-score-berechnung.dg`
- Create: `modules/business/stipendium/deluge/wf-aggregation.dg`

- [ ] **Step 1: Score-Berechnungs-Skript erstellen**

```
modules/business/stipendium/deluge/wf-score-berechnung.dg
```

```deluge
// ============================================================
// Workflow Rule: stipendium_score_berechnen
// Modul: Stipendien_Bewertungen
// Trigger: On Edit — Bedingung: Status == "Abgeschlossen"
// ============================================================
// Setup in Zoho CRM:
//   Automation > Workflow Rules > Create Rule
//   Module: Stipendien_Bewertungen
//   When: A record is edited
//   Condition: Status is "Abgeschlossen"
//   Instant Action: Custom Function > Diesen Code einfuegen
// ============================================================

// Rubrik-Durchschnitte berechnen
a_avg = (input.A1_Note + input.A2_Note + input.A3_Note) / 3.0;
b_avg = (input.B1_Note + input.B2_Note + input.B3_Note) / 3.0;
c_avg = (input.C1_Note + input.C2_Note + input.C3_Note) / 3.0;
d_avg = (input.D1_Note + input.D2_Note + input.D3_Note) / 3.0;

// Gewichtung anwenden (A:30%, B:30%, C:25%, D:15%)
a_gew = (a_avg * 0.30).round(2);
b_gew = (b_avg * 0.30).round(2);
c_gew = (c_avg * 0.25).round(2);
d_gew = (d_avg * 0.15).round(2);

// Gesamtscore (max 10.0)
gesamt = (a_gew + b_gew + c_gew + d_gew).round(2);

// Bewertungs-Record aktualisieren
update_map = Map();
update_map.put("A_Gewichtet", a_gew);
update_map.put("B_Gewichtet", b_gew);
update_map.put("C_Gewichtet", c_gew);
update_map.put("D_Gewichtet", d_gew);
update_map.put("Gesamtscore", gesamt);
update_map.put("Bewertungsdatum", zoho.currenttime);

resp = zoho.crm.updateRecord("Stipendien_Bewertungen", input.id, update_map);
info "Score berechnet fuer Bewertung " + input.id + ": " + gesamt + " — " + resp;
```

- [ ] **Step 2: Aggregations-Skript erstellen**

```
modules/business/stipendium/deluge/wf-aggregation.dg
```

```deluge
// ============================================================
// Workflow Rule: stipendium_aggregation
// Modul: Stipendien_Bewertungen
// Trigger: On Edit — Bedingung: Gesamtscore is not empty
// ============================================================
// Dieses Skript wird NACH der Score-Berechnung ausgefuehrt.
// Es aggregiert alle abgeschlossenen Bewertungen eines Stipendiums
// und aktualisiert den Stipendien-Record.
// ============================================================

// Stipendien-Record ID aus Lookup holen
stip_id = input.Stipendium.id;

if (stip_id == null)
{
    info "Fehler: Kein Stipendium verknuepft fuer Bewertung " + input.id;
    return;
}

// Alle Bewertungen fuer dieses Stipendium holen
bewertungen = zoho.crm.getRelatedRecords("Stipendien_Bewertungen", "Stipendien", stip_id);

sum_score = 0.0;
count = 0;

for each bew in bewertungen
{
    if (bew.get("Status") == "Abgeschlossen" && bew.get("Gesamtscore") != null)
    {
        sum_score = sum_score + bew.get("Gesamtscore").toDecimal();
        count = count + 1;
    }
}

if (count > 0)
{
    mittelwert = (sum_score / count).round(2);
    foerderfaehig = mittelwert >= 6.0;

    update_map = Map();
    update_map.put("Gesamtscore_Mittelwert", mittelwert);
    update_map.put("Anzahl_Bewertungen", count);
    update_map.put("Foerderfaehig", foerderfaehig);

    resp = zoho.crm.updateRecord("Stipendien", stip_id, update_map);
    info "Aggregation fuer Stipendium " + stip_id + ": Mittelwert=" + mittelwert + " (n=" + count + ") — " + resp;
}
```

- [ ] **Step 3: Workflow Rules in Zoho CRM konfigurieren**

1. **Rule 1:** stipendium_score_berechnen
   - Module: Stipendien_Bewertungen
   - When: Record is edited
   - Condition: Status == "Abgeschlossen" AND A1_Note is not empty
   - Action: Custom Function → Code aus `wf-score-berechnung.dg`

2. **Rule 2:** stipendium_aggregation
   - Module: Stipendien_Bewertungen
   - When: Record is edited
   - Condition: Gesamtscore is not empty
   - Action: Custom Function → Code aus `wf-aggregation.dg`
   - **Execution Order:** Nach Rule 1 (Priority setzen)

- [ ] **Step 4: Testen mit Testdaten**

In Zoho CRM:
1. Stipendien-Record anlegen (Status: Freigegeben, Runde: "Test 2026")
2. Bewertungs-Record anlegen mit Noten (z.B. alle 8)
3. Status auf "Abgeschlossen" setzen
4. Pruefen: Gesamtscore = (8*0.30 + 8*0.30 + 8*0.25 + 8*0.15) = 8.0
5. Pruefen: Stipendien-Record hat Gesamtscore_Mittelwert = 8.0

- [ ] **Step 5: Commit**

```bash
git add modules/business/stipendium/deluge/wf-score-berechnung.dg modules/business/stipendium/deluge/wf-aggregation.dg
git commit -m "feat(stipendium): Deluge Workflows Score-Berechnung + Aggregation"
```

---

### Task 4: Deluge Custom Functions — Ranking + Loeschfrist

**Files:**
- Create: `modules/business/stipendium/deluge/cf-ranking.dg`
- Create: `modules/business/stipendium/deluge/wf-loeschfrist.dg`

- [ ] **Step 1: Ranking-Skript erstellen**

```
modules/business/stipendium/deluge/cf-ranking.dg
```

```deluge
// ============================================================
// Custom Function: stipendium_ranking
// Typ: Custom Button auf Stipendien-Listenansicht
// ============================================================
// Setup: Zoho CRM > Setup > Customization > Modules > Stipendien
//   > Links & Buttons > Create Button
//   > Name: "Ranking berechnen"
//   > Placement: List View (selected records)
//   > Action: Custom Function > Diesen Code einfuegen
// ============================================================

// Parameter: ids (List von Stipendien-IDs, uebergeben vom Button)
// Alternativ: runde und typ als Parameter (wenn standalone)

// Aus dem ersten Record Runde und Typ ermitteln
if (ids.size() == 0)
{
    return "Keine Records ausgewaehlt.";
}

first_record = zoho.crm.getRecordById("Stipendien", ids.get(0));
runde = first_record.get("Runde");
typ = first_record.get("Stipendientyp");

// Alle foerderfaehigen Stipendien dieser Runde und dieses Typs holen
stipendien = zoho.crm.searchRecords("Stipendien",
    "(Runde:equals:" + runde + ") and (Stipendientyp:equals:" + typ + ") and (Foerderfaehig:equals:true)");

if (stipendien.size() == 0)
{
    return "Keine foerderfaehigen Bewerbungen in Runde '" + runde + "' gefunden.";
}

// Nach Gesamtscore absteigend sortieren
sorted = stipendien.sort("Gesamtscore_Mittelwert", false);

rang = 1;
prev_score = -1.0;
same_rank_count = 0;

for each stip in sorted
{
    score = stip.get("Gesamtscore_Mittelwert").toDecimal();

    if (score != prev_score)
    {
        current_rang = rang;
        same_rank_count = 0;
    }
    else
    {
        // Gleichstand: gleicher Rang
        same_rank_count = same_rank_count + 1;
    }

    zoho.crm.updateRecord("Stipendien", stip.get("id"), {"Rang": current_rang});
    prev_score = score;
    rang = rang + 1;
}

return "Ranking berechnet: " + sorted.size() + " foerderfaehige Bewerbungen in '" + runde + "'.";
```

- [ ] **Step 2: Loeschfrist-Skript erstellen**

```
modules/business/stipendium/deluge/wf-loeschfrist.dg
```

```deluge
// ============================================================
// Workflow Rule: stipendium_loeschfrist
// Modul: Stipendien
// Trigger: On Edit — Bedingung: Status oder Vergeben geaendert
// ============================================================

frist = null;

if (input.Vergeben == true)
{
    if (input.Stipendium_Abschluss != null)
    {
        // Abgeschlossenes Stipendium: 10 Jahre nach Abschluss
        frist = input.Stipendium_Abschluss.addYear(10);
    }
    // else: Laufendes Stipendium — keine Loeschfrist (frist bleibt null)
}
else if (input.Status == "Abgeschlossen" || input.Status == "Archiviert")
{
    // Nicht vergeben: 12 Monate nach Rundenende (konfigurierbar)
    runden_ende = input.Runde_Enddatum;
    if (runden_ende != null)
    {
        frist = runden_ende.addMonth(12);
    }
    else
    {
        // Fallback: 12 Monate ab jetzt
        frist = zoho.currentdate.addMonth(12);
    }
}

zoho.crm.updateRecord("Stipendien", input.id, {"Loeschfrist": frist});
info "Loeschfrist fuer Stipendium " + input.id + " gesetzt: " + frist;
```

- [ ] **Step 3: Custom Button + Workflow Rule in Zoho CRM konfigurieren**

1. **Custom Button:** "Ranking berechnen"
   - Module: Stipendien, List View
   - Code aus `cf-ranking.dg`

2. **Workflow Rule:** stipendium_loeschfrist
   - Module: Stipendien
   - When: Record is edited
   - Condition: Status changed OR Vergeben changed
   - Action: Custom Function → Code aus `wf-loeschfrist.dg`

- [ ] **Step 4: Commit**

```bash
git add modules/business/stipendium/deluge/cf-ranking.dg modules/business/stipendium/deluge/wf-loeschfrist.dg
git commit -m "feat(stipendium): Deluge Ranking-Berechnung + Loeschfrist-Workflow"
```

---

### Task 5: Deluge Scheduled Function — DSGVO Cleanup

**Files:**
- Create: `modules/business/stipendium/deluge/sf-dsgvo-cleanup.dg`

- [ ] **Step 1: Scheduled Function erstellen**

```
modules/business/stipendium/deluge/sf-dsgvo-cleanup.dg
```

```deluge
// ============================================================
// Scheduled Function: stipendium_dsgvo_cleanup
// Ausfuehrung: Taeglich um 02:00 Uhr
// ============================================================
// Setup: Zoho CRM > Setup > Automation > Schedules > Create Schedule
//   Name: stipendium_dsgvo_cleanup
//   Frequency: Daily, 02:00
//   Function: Diesen Code einfuegen
// ============================================================

today = zoho.currentdate;
warn_date = today.addDay(30);

// --- 1. Vorsitzenden ermitteln ---
vorsitz_search = zoho.crm.searchRecords("Contacts", "(Stipendiumsrat_Vorsitz:equals:true)");
if (vorsitz_search == null || vorsitz_search.size() == 0)
{
    info "WARNUNG: Kein Vorsitzender mit Stipendiumsrat_Vorsitz=true gefunden.";
    return;
}
vorsitzender_email = vorsitz_search.get(0).get("Email");
vorsitzender_name = vorsitz_search.get(0).get("Full_Name");

// --- 2. Erinnerung: Loeschfrist naht (30 Tage vorher) ---
bald_faellig = zoho.crm.searchRecords("Stipendien",
    "((Loeschfrist:greater_equal:" + today.toString("yyyy-MM-dd") + ") and (Loeschfrist:less_equal:" + warn_date.toString("yyyy-MM-dd") + "))");

if (bald_faellig != null && bald_faellig.size() > 0)
{
    mail_body = "Sehr geehrte/r " + vorsitzender_name + ",\n\n";
    mail_body = mail_body + "fuer folgende Stipendien-Bewerbungen naehert sich die Loeschfrist:\n\n";

    for each stip in bald_faellig
    {
        bewerber = stip.get("Bewerber");
        bewerber_name = ifnull(bewerber, Map()).get("name");
        if (bewerber_name == null) { bewerber_name = "(unbekannt)"; }

        mail_body = mail_body + "- " + bewerber_name;
        mail_body = mail_body + " | Runde: " + ifnull(stip.get("Runde"), "-");
        mail_body = mail_body + " | Loeschfrist: " + stip.get("Loeschfrist").toString("dd.MM.yyyy");
        mail_body = mail_body + "\n";
    }

    mail_body = mail_body + "\nBitte pruefen Sie, ob eine Verlaengerung noetig ist.\n";
    mail_body = mail_body + "\nMit freundlichen Gruessen\nIhr DGPTM-System";

    sendmail
    [
        from: zoho.adminuserid
        to: vorsitzender_email
        subject: "DGPTM DSGVO-Hinweis: " + bald_faellig.size() + " Stipendien-Loeschfrist(en) in 30 Tagen"
        message: mail_body
    ];

    info "Erinnerung gesendet: " + bald_faellig.size() + " Records an " + vorsitzender_email;
}

// --- 3. Automatische Loeschung (nur archivierte Records mit abgelaufener Frist) ---
loesch_faellig = zoho.crm.searchRecords("Stipendien",
    "((Loeschfrist:less_than:" + today.toString("yyyy-MM-dd") + ") and (Status:equals:Archiviert))");

if (loesch_faellig != null && loesch_faellig.size() > 0)
{
    geloescht_count = 0;

    for each stip in loesch_faellig
    {
        stip_id = stip.get("id");

        // WorkDrive-Ordner loeschen
        folder_id = stip.get("WorkDrive_Folder_ID");
        if (folder_id != null && folder_id != "")
        {
            try
            {
                zoho.workdrive.deleteFile(folder_id);
                info "WorkDrive Ordner geloescht: " + folder_id;
            }
            catch (e)
            {
                info "WARNUNG: WorkDrive-Loeschung fehlgeschlagen fuer " + folder_id + ": " + e;
            }
        }

        // Alle zugehoerigen Bewertungen loeschen
        bewertungen = zoho.crm.getRelatedRecords("Stipendien_Bewertungen", "Stipendien", stip_id);
        if (bewertungen != null)
        {
            for each bew in bewertungen
            {
                zoho.crm.deleteRecord("Stipendien_Bewertungen", bew.get("id"));
            }
            info "Bewertungen geloescht fuer Stipendium " + stip_id + ": " + bewertungen.size();
        }

        // Stipendien-Record loeschen
        zoho.crm.deleteRecord("Stipendien", stip_id);
        geloescht_count = geloescht_count + 1;
    }

    // Bestaetigungsmail an Vorsitzenden
    sendmail
    [
        from: zoho.adminuserid
        to: vorsitzender_email
        subject: "DGPTM DSGVO: " + geloescht_count + " Stipendien-Datensaetze geloescht"
        message: "Es wurden " + geloescht_count + " archivierte Stipendien-Datensaetze inkl. Bewertungen und Dokumente automatisch geloescht (Loeschfrist abgelaufen)."
    ];

    info "DSGVO Cleanup abgeschlossen: " + geloescht_count + " Records geloescht.";
}
else
{
    info "DSGVO Cleanup: Keine loeschfaelligen Records.";
}
```

- [ ] **Step 2: Schedule in Zoho CRM konfigurieren**

Zoho CRM > Setup > Automation > Schedules:
- Name: `stipendium_dsgvo_cleanup`
- Frequency: Daily at 02:00
- Function: Code aus `sf-dsgvo-cleanup.dg`

- [ ] **Step 3: Commit**

```bash
git add modules/business/stipendium/deluge/sf-dsgvo-cleanup.dg
git commit -m "feat(stipendium): Deluge DSGVO Scheduled Function (taegliche Loeschpruefung)"
```

---

## Phase B: WordPress Backend Infrastructure

### Task 6: Module.json + Hauptklasse aktualisieren

**Files:**
- Modify: `modules/business/stipendium/module.json`
- Modify: `modules/business/stipendium/dgptm-stipendium.php`

- [ ] **Step 1: module.json aktualisieren — Dependencies hinzufuegen**

Aktuellen Inhalt von `modules/business/stipendium/module.json` ersetzen:

```json
{
    "id": "stipendium",
    "name": "Stipendienvergabe",
    "description": "Digitales Bewerbungs- und Bewertungsverfahren fuer DGPTM-Stipendien",
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

- [ ] **Step 2: Hauptklasse erweitern — neue Komponenten laden**

`modules/business/stipendium/dgptm-stipendium.php` ersetzen:

```php
<?php
/**
 * Plugin Name: DGPTM Stipendienvergabe
 * Description: Digitales Bewerbungs- und Bewertungsverfahren fuer DGPTM-Stipendien
 * Version: 1.0.0
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('DGPTM_Stipendium')) {

    class DGPTM_Stipendium {

        private static $instance = null;
        private $plugin_path;
        private $plugin_url;
        private $settings;
        private $zoho;
        private $workdrive;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->plugin_path = plugin_dir_path(__FILE__);
            $this->plugin_url  = plugin_dir_url(__FILE__);

            $this->load_components();

            add_action('init', [$this, 'register_acf_fields']);
        }

        private function load_components() {
            // Freigabe-Komponente (bereits implementiert)
            require_once $this->plugin_path . 'includes/class-freigabe.php';
            new DGPTM_Stipendium_Freigabe($this->plugin_path, $this->plugin_url);

            // Einstellungen
            require_once $this->plugin_path . 'includes/class-settings.php';
            $this->settings = new DGPTM_Stipendium_Settings($this->plugin_path, $this->plugin_url);

            // Zoho CRM API (nur laden wenn crm-abruf verfuegbar)
            if (class_exists('DGPTM_CRM_Abruf') || function_exists('dgptm_get_zoho_token')) {
                require_once $this->plugin_path . 'includes/class-zoho-stipendium.php';
                $this->zoho = new DGPTM_Stipendium_Zoho($this->settings);

                require_once $this->plugin_path . 'includes/class-workdrive.php';
                $this->workdrive = new DGPTM_Stipendium_WorkDrive($this->settings);
            }

            // Dashboard-Tab Registrierung (nur wenn Dashboard-Modul aktiv)
            if (class_exists('DGPTM_Mitglieder_Dashboard') || shortcode_exists('dgptm_dashboard')) {
                require_once $this->plugin_path . 'includes/class-dashboard-tab.php';
                new DGPTM_Stipendium_Dashboard_Tab($this->plugin_path, $this->plugin_url, $this->settings);
            }
        }

        /**
         * ACF-Felder fuer Stipendiumsrat-Berechtigung registrieren.
         */
        public function register_acf_fields() {
            if (!function_exists('acf_add_local_field_group')) return;

            acf_add_local_field_group([
                'key'      => 'group_stipendiumsrat',
                'title'    => 'Stipendiumsrat',
                'fields'   => [
                    [
                        'key'           => 'field_stipendiumsrat_mitglied',
                        'label'         => 'Mitglied im Stipendiumsrat',
                        'name'          => 'stipendiumsrat_mitglied',
                        'type'          => 'true_false',
                        'default_value' => 0,
                        'ui'            => 1,
                        'instructions'  => 'Aktivieren, wenn diese Person dem Stipendiumsrat angehoert.',
                    ],
                    [
                        'key'           => 'field_stipendiumsrat_vorsitz',
                        'label'         => 'Vorsitzende/r des Stipendiumsrats',
                        'name'          => 'stipendiumsrat_vorsitz',
                        'type'          => 'true_false',
                        'default_value' => 0,
                        'ui'            => 1,
                        'instructions'  => 'Aktivieren fuer den/die Vorsitzende/n. Hat Zugang zur Auswertung und Freigabe.',
                    ],
                ],
                'location' => [
                    [
                        [
                            'param'    => 'user_form',
                            'operator' => '==',
                            'value'    => 'all',
                        ],
                    ],
                ],
                'menu_order' => 50,
            ]);
        }

        public function get_path() { return $this->plugin_path; }
        public function get_url()  { return $this->plugin_url; }
        public function get_settings() { return $this->settings; }
        public function get_zoho() { return $this->zoho; }
        public function get_workdrive() { return $this->workdrive; }
    }
}

if (!isset($GLOBALS['dgptm_stipendium_initialized'])) {
    $GLOBALS['dgptm_stipendium_initialized'] = true;
    DGPTM_Stipendium::get_instance();
}
```

- [ ] **Step 3: Commit**

```bash
git add modules/business/stipendium/module.json modules/business/stipendium/dgptm-stipendium.php
git commit -m "feat(stipendium): Hauptklasse mit ACF-Feldern und Komponenten-Loader"
```

---

### Task 7: class-settings.php — Admin-Einstellungen

**Files:**
- Create: `modules/business/stipendium/includes/class-settings.php`
- Create: `modules/business/stipendium/templates/admin-settings.php`

- [ ] **Step 1: Settings-Klasse erstellen**

```
modules/business/stipendium/includes/class-settings.php
```

```php
<?php
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Settings {

    private $plugin_path;
    private $plugin_url;

    const OPTION_KEY = 'dgptm_stipendium_settings';

    /** Standard-Einstellungen */
    private $defaults = [
        'stipendientypen' => [
            [
                'id'          => 'promotionsstipendium',
                'bezeichnung' => 'Promotionsstipendium',
                'runde'       => '',
                'start'       => '',
                'ende'        => '',
            ],
            [
                'id'          => 'josef_guettler',
                'bezeichnung' => 'Josef Guettler Stipendium',
                'runde'       => '',
                'start'       => '',
                'ende'        => '',
            ],
        ],
        'freigabe_modus'                  => 'vorsitz',
        'gleichstand_regel'               => 'rubrik_a',
        'loeschfrist_monate_nicht_vergeben' => 12,
        'loeschfrist_jahre_vergeben'       => 10,
        'auto_loeschung'                  => false,
        'bestaetigungsmail_text'          => "Sehr geehrte/r {name},\n\nvielen Dank fuer Ihre Bewerbung fuer das {stipendientyp} der DGPTM.\n\nIhre Bewerbung ist eingegangen und wird geprueft. Sie erhalten eine weitere Benachrichtigung, sobald das Verfahren abgeschlossen ist.\n\nMit freundlichen Gruessen\nDGPTM - Stipendiumsrat",
        'workdrive_team_folder_id'        => '',
        'benachrichtigung_vorsitz_email'  => '',
    ];

    public function __construct($plugin_path, $plugin_url) {
        $this->plugin_path = $plugin_path;
        $this->plugin_url  = $plugin_url;

        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_settings_page']);
            add_action('wp_ajax_dgptm_stipendium_save_settings', [$this, 'ajax_save_settings']);
        }
    }

    /**
     * Alle Einstellungen abrufen (mit Defaults gemergt).
     */
    public function get_all() {
        $saved = get_option(self::OPTION_KEY, []);
        return wp_parse_args($saved, $this->defaults);
    }

    /**
     * Einzelne Einstellung abrufen.
     */
    public function get($key) {
        $all = $this->get_all();
        return $all[$key] ?? ($this->defaults[$key] ?? null);
    }

    /**
     * Stipendientyp-Konfiguration nach ID abrufen.
     */
    public function get_stipendientyp($typ_id) {
        $typen = $this->get('stipendientypen');
        foreach ($typen as $typ) {
            if ($typ['id'] === $typ_id) {
                return $typ;
            }
        }
        return null;
    }

    /**
     * Pruefen ob Bewerbungszeitraum fuer einen Typ aktiv ist.
     */
    public function is_bewerbung_offen($typ_id) {
        $typ = $this->get_stipendientyp($typ_id);
        if (!$typ || empty($typ['start']) || empty($typ['ende'])) {
            return false;
        }
        $now   = current_time('Y-m-d');
        return ($now >= $typ['start'] && $now <= $typ['ende']);
    }

    /**
     * Naechstes Bewerbungsende fuer einen Typ (fuer Hinweistext).
     */
    public function naechster_bewerbungsschluss($typ_id) {
        $typ = $this->get_stipendientyp($typ_id);
        if (!$typ || empty($typ['start'])) {
            return null;
        }
        $now = current_time('Y-m-d');
        if ($now < $typ['start']) {
            return $typ['start'];
        }
        return $typ['ende'] ?? null;
    }

    /**
     * Admin-Menue hinzufuegen (unter DGPTM Suite).
     */
    public function add_settings_page() {
        add_submenu_page(
            'dgptm-suite',
            'Stipendium Einstellungen',
            'Stipendium',
            'manage_options',
            'dgptm-stipendium-settings',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.');
        }
        $settings = $this->get_all();
        $nonce = wp_create_nonce('dgptm_stipendium_settings_nonce');
        include $this->plugin_path . 'templates/admin-settings.php';
    }

    /**
     * AJAX: Einstellungen speichern.
     */
    public function ajax_save_settings() {
        check_ajax_referer('dgptm_stipendium_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $raw = wp_unslash($_POST['settings'] ?? '');
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            wp_send_json_error('Ungueltige Daten.');
        }

        // Sanitize
        $clean = [];

        // Stipendientypen
        if (isset($data['stipendientypen']) && is_array($data['stipendientypen'])) {
            $clean['stipendientypen'] = [];
            foreach ($data['stipendientypen'] as $typ) {
                $clean['stipendientypen'][] = [
                    'id'          => sanitize_key($typ['id'] ?? ''),
                    'bezeichnung' => sanitize_text_field($typ['bezeichnung'] ?? ''),
                    'runde'       => sanitize_text_field($typ['runde'] ?? ''),
                    'start'       => sanitize_text_field($typ['start'] ?? ''),
                    'ende'        => sanitize_text_field($typ['ende'] ?? ''),
                ];
            }
        }

        // Einfache Felder
        $clean['freigabe_modus']     = in_array($data['freigabe_modus'] ?? '', ['vorsitz', 'direkt']) ? $data['freigabe_modus'] : 'vorsitz';
        $clean['gleichstand_regel']  = in_array($data['gleichstand_regel'] ?? '', ['rubrik_a', 'mehrheit', 'manuell']) ? $data['gleichstand_regel'] : 'rubrik_a';
        $clean['loeschfrist_monate_nicht_vergeben'] = absint($data['loeschfrist_monate_nicht_vergeben'] ?? 12);
        $clean['loeschfrist_jahre_vergeben']        = absint($data['loeschfrist_jahre_vergeben'] ?? 10);
        $clean['auto_loeschung']     = !empty($data['auto_loeschung']);
        $clean['bestaetigungsmail_text'] = sanitize_textarea_field($data['bestaetigungsmail_text'] ?? '');
        $clean['workdrive_team_folder_id'] = sanitize_text_field($data['workdrive_team_folder_id'] ?? '');
        $clean['benachrichtigung_vorsitz_email'] = sanitize_email($data['benachrichtigung_vorsitz_email'] ?? '');

        update_option(self::OPTION_KEY, $clean, false);

        wp_send_json_success(['message' => 'Einstellungen gespeichert.']);
    }
}
```

- [ ] **Step 2: Admin-Settings Template erstellen**

```
modules/business/stipendium/templates/admin-settings.php
```

```php
<?php if (!defined('ABSPATH')) exit; ?>
<div class="wrap">
    <h1>Stipendium — Einstellungen</h1>

    <div id="dgptm-stipendium-settings-app">
        <form id="dgptm-stipendium-settings-form">
            <input type="hidden" name="nonce" value="<?php echo esc_attr($nonce); ?>">

            <h2>Stipendientypen & Bewerbungszeitraeume</h2>
            <table class="widefat" id="stipendientypen-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Bezeichnung</th>
                        <th>Aktuelle Runde</th>
                        <th>Bewerbung Start</th>
                        <th>Bewerbung Ende</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($settings['stipendientypen'] as $i => $typ) : ?>
                    <tr data-index="<?php echo $i; ?>">
                        <td><input type="text" name="stipendientypen[<?php echo $i; ?>][id]" value="<?php echo esc_attr($typ['id']); ?>" class="regular-text" readonly></td>
                        <td><input type="text" name="stipendientypen[<?php echo $i; ?>][bezeichnung]" value="<?php echo esc_attr($typ['bezeichnung']); ?>" class="regular-text"></td>
                        <td><input type="text" name="stipendientypen[<?php echo $i; ?>][runde]" value="<?php echo esc_attr($typ['runde']); ?>" class="regular-text" placeholder="z.B. Ausschreibung 2026"></td>
                        <td><input type="date" name="stipendientypen[<?php echo $i; ?>][start]" value="<?php echo esc_attr($typ['start']); ?>"></td>
                        <td><input type="date" name="stipendientypen[<?php echo $i; ?>][ende]" value="<?php echo esc_attr($typ['ende']); ?>"></td>
                        <td><button type="button" class="button remove-typ" title="Entfernen">&times;</button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <p><button type="button" class="button" id="add-stipendientyp">+ Stipendientyp hinzufuegen</button></p>

            <hr>
            <h2>Verfahrenseinstellungen</h2>
            <table class="form-table">
                <tr>
                    <th>Freigabe-Modus</th>
                    <td>
                        <select name="freigabe_modus">
                            <option value="vorsitz" <?php selected($settings['freigabe_modus'], 'vorsitz'); ?>>Freigabe durch Vorsitzenden</option>
                            <option value="direkt" <?php selected($settings['freigabe_modus'], 'direkt'); ?>>Direktzugriff (alle sehen sofort)</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th>Gleichstand-Regel</th>
                    <td>
                        <select name="gleichstand_regel">
                            <option value="rubrik_a" <?php selected($settings['gleichstand_regel'], 'rubrik_a'); ?>>Rubrik A entscheidet</option>
                            <option value="mehrheit" <?php selected($settings['gleichstand_regel'], 'mehrheit'); ?>>Mehrheitsentscheid</option>
                            <option value="manuell" <?php selected($settings['gleichstand_regel'], 'manuell'); ?>>Manuell (Vorsitzender entscheidet)</option>
                        </select>
                    </td>
                </tr>
            </table>

            <hr>
            <h2>DSGVO / Loeschfristen</h2>
            <table class="form-table">
                <tr>
                    <th>Loeschfrist (nicht vergeben)</th>
                    <td><input type="number" name="loeschfrist_monate_nicht_vergeben" value="<?php echo esc_attr($settings['loeschfrist_monate_nicht_vergeben']); ?>" min="1" max="60"> Monate nach Rundenende</td>
                </tr>
                <tr>
                    <th>Loeschfrist (vergeben, abgeschlossen)</th>
                    <td><input type="number" name="loeschfrist_jahre_vergeben" value="<?php echo esc_attr($settings['loeschfrist_jahre_vergeben']); ?>" min="1" max="30"> Jahre nach Stipendiums-Abschluss</td>
                </tr>
                <tr>
                    <th>Automatische Loeschung</th>
                    <td><label><input type="checkbox" name="auto_loeschung" value="1" <?php checked($settings['auto_loeschung']); ?>> Daten nach Fristablauf automatisch loeschen (sonst nur Erinnerung)</label></td>
                </tr>
            </table>

            <hr>
            <h2>Integrationen</h2>
            <table class="form-table">
                <tr>
                    <th>WorkDrive Team-Folder ID</th>
                    <td><input type="text" name="workdrive_team_folder_id" value="<?php echo esc_attr($settings['workdrive_team_folder_id']); ?>" class="regular-text" placeholder="z.B. abc123def456"></td>
                </tr>
                <tr>
                    <th>E-Mail Vorsitzende/r</th>
                    <td><input type="email" name="benachrichtigung_vorsitz_email" value="<?php echo esc_attr($settings['benachrichtigung_vorsitz_email']); ?>" class="regular-text"></td>
                </tr>
            </table>

            <hr>
            <h2>E-Mail Eingangsbestaetigung</h2>
            <table class="form-table">
                <tr>
                    <th>Text</th>
                    <td>
                        <textarea name="bestaetigungsmail_text" rows="8" class="large-text"><?php echo esc_textarea($settings['bestaetigungsmail_text']); ?></textarea>
                        <p class="description">Platzhalter: {name}, {stipendientyp}, {runde}, {datum}</p>
                    </td>
                </tr>
            </table>

            <p class="submit">
                <button type="submit" class="button button-primary">Einstellungen speichern</button>
                <span id="dgptm-stipendium-save-status"></span>
            </p>
        </form>
    </div>

    <script>
    jQuery(function($) {
        // Speichern
        $('#dgptm-stipendium-settings-form').on('submit', function(e) {
            e.preventDefault();
            var formData = {};
            $(this).find('input, select, textarea').each(function() {
                var name = $(this).attr('name');
                if (!name || name === 'nonce') return;
                if ($(this).attr('type') === 'checkbox') {
                    formData[name] = $(this).is(':checked');
                } else {
                    formData[name] = $(this).val();
                }
            });

            // Stipendientypen als Array aufbauen
            var typen = [];
            $('#stipendientypen-table tbody tr').each(function() {
                var idx = $(this).data('index');
                typen.push({
                    id:          $(this).find('[name$="[id]"]').val(),
                    bezeichnung: $(this).find('[name$="[bezeichnung]"]').val(),
                    runde:       $(this).find('[name$="[runde]"]').val(),
                    start:       $(this).find('[name$="[start]"]').val(),
                    ende:        $(this).find('[name$="[ende]"]').val()
                });
            });
            formData.stipendientypen = typen;

            $('#dgptm-stipendium-save-status').text('Wird gespeichert...');
            $.post(ajaxurl, {
                action: 'dgptm_stipendium_save_settings',
                nonce: $('[name="nonce"]').val(),
                settings: JSON.stringify(formData)
            }, function(res) {
                $('#dgptm-stipendium-save-status').text(res.success ? 'Gespeichert!' : 'Fehler: ' + res.data);
                setTimeout(function() { $('#dgptm-stipendium-save-status').text(''); }, 3000);
            });
        });

        // Typ entfernen
        $(document).on('click', '.remove-typ', function() {
            if (confirm('Stipendientyp wirklich entfernen?')) {
                $(this).closest('tr').remove();
            }
        });

        // Typ hinzufuegen
        $('#add-stipendientyp').on('click', function() {
            var idx = $('#stipendientypen-table tbody tr').length;
            var row = '<tr data-index="' + idx + '">' +
                '<td><input type="text" name="stipendientypen[' + idx + '][id]" class="regular-text" placeholder="eindeutige_id"></td>' +
                '<td><input type="text" name="stipendientypen[' + idx + '][bezeichnung]" class="regular-text"></td>' +
                '<td><input type="text" name="stipendientypen[' + idx + '][runde]" class="regular-text" placeholder="z.B. Ausschreibung 2027"></td>' +
                '<td><input type="date" name="stipendientypen[' + idx + '][start]"></td>' +
                '<td><input type="date" name="stipendientypen[' + idx + '][ende]"></td>' +
                '<td><button type="button" class="button remove-typ">&times;</button></td>' +
                '</tr>';
            $('#stipendientypen-table tbody').append(row);
        });
    });
    </script>
</div>
```

- [ ] **Step 3: Modul aktivieren und Einstellungsseite pruefen**

1. DGPTM Suite Dashboard > Modul "Stipendienvergabe" aktivieren
2. Im Menue sollte unter DGPTM Suite > "Stipendium" erscheinen
3. Einstellungsseite oeffnen: Stipendientypen, Zeitraeume, DSGVO-Settings sichtbar
4. Testweise speichern und pruefen ob Werte persistent sind

- [ ] **Step 4: Commit**

```bash
git add modules/business/stipendium/includes/class-settings.php modules/business/stipendium/templates/admin-settings.php
git commit -m "feat(stipendium): Admin-Einstellungsseite mit Stipendientypen und DSGVO-Konfiguration"
```

---

### Task 8: class-zoho-stipendium.php — Zoho CRM API-Wrapper

**Files:**
- Create: `modules/business/stipendium/includes/class-zoho-stipendium.php`

Diese Klasse kapselt alle Zoho CRM API-Aufrufe fuer die Module Stipendien und Stipendien_Bewertungen. Sie nutzt den bestehenden `crm-abruf` Token-Manager.

- [ ] **Step 1: API-Wrapper erstellen**

```
modules/business/stipendium/includes/class-zoho-stipendium.php
```

```php
<?php
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Zoho {

    private $settings;
    private $api_base = 'https://www.zohoapis.eu/crm/v8/';

    public function __construct($settings) {
        $this->settings = $settings;
    }

    /* ──────────────────────────────────────────
     * Token
     * ────────────────────────────────────────── */

    private function get_token() {
        // crm-abruf stellt die Funktion global bereit
        if (function_exists('dgptm_get_zoho_token')) {
            return dgptm_get_zoho_token();
        }
        // Fallback: direkt aus Options
        $token = get_option('dgptm_zoho_access_token', '');
        $expires = get_option('dgptm_zoho_token_expires', 0);
        if (time() >= $expires) {
            // Token abgelaufen — crm-abruf refresht automatisch
            if (class_exists('DGPTM_CRM_Abruf')) {
                $crm = DGPTM_CRM_Abruf::get_instance();
                if (method_exists($crm, 'get_valid_access_token')) {
                    return $crm->get_valid_access_token();
                }
            }
        }
        return $token;
    }

    private function headers() {
        return [
            'Authorization' => 'Zoho-oauthtoken ' . $this->get_token(),
            'Content-Type'  => 'application/json',
        ];
    }

    /* ──────────────────────────────────────────
     * Stipendien CRUD
     * ────────────────────────────────────────── */

    /**
     * Neuen Stipendien-Record erstellen.
     *
     * @param array $data Feld-Werte (API-Namen als Keys)
     * @return array|WP_Error Zoho Response oder Fehler
     */
    public function create_stipendium($data) {
        $data['Eingangsdatum'] = date('Y-m-d');
        $data['Status'] = 'Eingegangen';

        return $this->api_post('Stipendien', $data);
    }

    /**
     * Stipendien-Record aktualisieren.
     */
    public function update_stipendium($record_id, $data) {
        return $this->api_put('Stipendien', $record_id, $data);
    }

    /**
     * Stipendien einer Runde und eines Typs abrufen.
     */
    public function get_stipendien_by_runde($runde, $typ = null) {
        $cache_key = 'dgptm_stip_' . md5($runde . $typ);
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $criteria = '(Runde:equals:' . $runde . ')';
        if ($typ) {
            $criteria .= ' and (Stipendientyp:equals:' . $typ . ')';
        }

        $result = $this->api_search('Stipendien', $criteria);
        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
        }
        return $result;
    }

    /**
     * Einzelnes Stipendium per ID abrufen.
     */
    public function get_stipendium($record_id) {
        return $this->api_get('Stipendien', $record_id);
    }

    /* ──────────────────────────────────────────
     * Bewertungen CRUD
     * ────────────────────────────────────────── */

    /**
     * Neue Bewertung erstellen.
     */
    public function create_bewertung($data) {
        $data['Status'] = $data['Status'] ?? 'Entwurf';
        return $this->api_post('Stipendien_Bewertungen', $data);
    }

    /**
     * Bewertung aktualisieren.
     */
    public function update_bewertung($record_id, $data) {
        return $this->api_put('Stipendien_Bewertungen', $record_id, $data);
    }

    /**
     * Bewertungen eines Gutachters fuer eine Runde abrufen.
     */
    public function get_bewertungen_by_gutachter($contact_id, $stipendium_ids = []) {
        $cache_key = 'dgptm_stip_bew_' . md5($contact_id . implode(',', $stipendium_ids));
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $criteria = '(Gutachter:equals:' . $contact_id . ')';
        $result = $this->api_search('Stipendien_Bewertungen', $criteria);

        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
        }
        return $result;
    }

    /**
     * Alle Bewertungen fuer ein Stipendium abrufen.
     */
    public function get_bewertungen_by_stipendium($stipendium_id) {
        $criteria = '(Stipendium:equals:' . $stipendium_id . ')';
        return $this->api_search('Stipendien_Bewertungen', $criteria);
    }

    /**
     * Alle Bewertungen einer Runde via COQL (ein API-Call statt n).
     */
    public function get_alle_bewertungen_runde($runde, $typ = null) {
        $cache_key = 'dgptm_stip_bew_runde_' . md5($runde . $typ);
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $query = "SELECT Stipendium, Gutachter, A_Gewichtet, B_Gewichtet, C_Gewichtet, D_Gewichtet, Gesamtscore, Status, Gesamtanmerkungen "
               . "FROM Stipendien_Bewertungen "
               . "WHERE Stipendium.Runde = '" . esc_sql($runde) . "'";
        if ($typ) {
            $query .= " AND Stipendium.Stipendientyp = '" . esc_sql($typ) . "'";
        }

        $result = $this->api_coql($query);
        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);
        }
        return $result;
    }

    /* ──────────────────────────────────────────
     * Cache Invalidierung
     * ────────────────────────────────────────── */

    public function invalidate_stipendien_cache($runde, $typ = null) {
        delete_transient('dgptm_stip_' . md5($runde . $typ));
        delete_transient('dgptm_stip_bew_runde_' . md5($runde . $typ));
    }

    public function invalidate_bewertung_cache($contact_id) {
        // Alle Transients mit diesem Gutachter loeschen
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_dgptm_stip_bew_' . md5($contact_id) . '%'
        ));
    }

    /* ──────────────────────────────────────────
     * Low-Level API
     * ────────────────────────────────────────── */

    private function api_post($module, $data) {
        $url = $this->api_base . $module;
        $body = json_encode(['data' => [$data]]);

        $response = $this->safe_remote('POST', $url, $body);
        return $this->parse_response($response);
    }

    private function api_put($module, $record_id, $data) {
        $url = $this->api_base . $module . '/' . $record_id;
        $body = json_encode(['data' => [$data]]);

        $response = $this->safe_remote('PUT', $url, $body);
        return $this->parse_response($response);
    }

    private function api_get($module, $record_id) {
        $url = $this->api_base . $module . '/' . $record_id;
        $response = $this->safe_remote('GET', $url);
        return $this->parse_response($response);
    }

    private function api_search($module, $criteria) {
        $url = $this->api_base . $module . '/search?criteria=' . urlencode('(' . $criteria . ')');
        $response = $this->safe_remote('GET', $url);
        $parsed = $this->parse_response($response);
        if (is_wp_error($parsed)) return $parsed;
        return $parsed['data'] ?? [];
    }

    private function api_coql($query) {
        $url = $this->api_base . 'coql';
        $body = json_encode(['select_query' => $query]);
        $response = $this->safe_remote('POST', $url, $body);
        $parsed = $this->parse_response($response);
        if (is_wp_error($parsed)) return $parsed;
        return $parsed['data'] ?? [];
    }

    /**
     * SSRF-sichere Remote-Anfrage (nutzt dgptm_safe_remote falls verfuegbar).
     */
    private function safe_remote($method, $url, $body = null) {
        $args = [
            'method'  => $method,
            'headers' => $this->headers(),
            'timeout' => 30,
        ];
        if ($body) {
            $args['body'] = $body;
        }

        if (function_exists('dgptm_safe_remote')) {
            return dgptm_safe_remote($url, $args);
        }
        return wp_remote_request($url, $args);
    }

    private function parse_response($response) {
        if (is_wp_error($response)) {
            $this->log_error('API-Fehler: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $msg = $body['message'] ?? ('HTTP ' . $code);
            $this->log_error('API HTTP ' . $code . ': ' . $msg);
            return new WP_Error('zoho_api_error', $msg, ['status' => $code]);
        }

        return $body;
    }

    private function log_error($message) {
        if (function_exists('dgptm_log_error')) {
            dgptm_log_error($message, 'stipendium');
        }
    }
}
```

- [ ] **Step 2: Pruefen dass die Klasse geladen wird**

1. Modul aktivieren
2. WordPress Debug-Log pruefen: keine PHP-Fehler
3. In `wp-admin` > Seite mit `[dgptm_stipendium_bewerbung]` aufrufen: kein Fatal Error

- [ ] **Step 3: Commit**

```bash
git add modules/business/stipendium/includes/class-zoho-stipendium.php
git commit -m "feat(stipendium): Zoho CRM API-Wrapper mit Caching und COQL-Support"
```

---

### Task 9: class-workdrive.php — WorkDrive Integration

**Files:**
- Create: `modules/business/stipendium/includes/class-workdrive.php`

- [ ] **Step 1: WorkDrive-Klasse erstellen**

```
modules/business/stipendium/includes/class-workdrive.php
```

```php
<?php
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_WorkDrive {

    private $settings;
    private $api_base = 'https://www.zohoapis.eu/workdrive/api/v1/';

    public function __construct($settings) {
        $this->settings = $settings;
    }

    private function get_token() {
        if (function_exists('dgptm_get_zoho_token')) {
            return dgptm_get_zoho_token();
        }
        if (class_exists('DGPTM_CRM_Abruf')) {
            $crm = DGPTM_CRM_Abruf::get_instance();
            if (method_exists($crm, 'get_valid_access_token')) {
                return $crm->get_valid_access_token();
            }
        }
        return get_option('dgptm_zoho_access_token', '');
    }

    private function headers($content_type = 'application/json') {
        return [
            'Authorization' => 'Zoho-oauthtoken ' . $this->get_token(),
            'Content-Type'  => $content_type,
        ];
    }

    /**
     * Ordner fuer eine Bewerbung erstellen.
     *
     * Struktur: Team-Folder / Stipendientyp / Runde / Bewerbung_NNN_Nachname_Vorname
     *
     * @param string $stipendientyp z.B. "Promotionsstipendium"
     * @param string $runde         z.B. "2026 - Ausschreibung 2026"
     * @param string $nachname      Nachname des Bewerbers
     * @param string $vorname       Vorname des Bewerbers
     * @param int    $nummer        Laufende Nummer der Bewerbung
     * @return string|WP_Error Folder-ID oder Fehler
     */
    public function create_bewerbung_ordner($stipendientyp, $runde, $nachname, $vorname, $nummer) {
        $root_folder_id = $this->settings->get('workdrive_team_folder_id');
        if (empty($root_folder_id)) {
            return new WP_Error('workdrive_config', 'WorkDrive Team-Folder ID nicht konfiguriert.');
        }

        // 1. Stipendientyp-Ordner sicherstellen
        $typ_folder = $this->ensure_subfolder($root_folder_id, $stipendientyp);
        if (is_wp_error($typ_folder)) return $typ_folder;

        // 2. Runden-Ordner sicherstellen
        $runde_folder = $this->ensure_subfolder($typ_folder, $runde);
        if (is_wp_error($runde_folder)) return $runde_folder;

        // 3. Bewerbungs-Ordner erstellen
        $folder_name = sprintf('Bewerbung_%03d_%s_%s',
            $nummer,
            $this->sanitize_filename($nachname),
            $this->sanitize_filename($vorname)
        );
        return $this->create_folder($runde_folder, $folder_name);
    }

    /**
     * Datei in einen Ordner hochladen.
     *
     * @param string $folder_id   WorkDrive Folder-ID
     * @param string $file_path   Lokaler Dateipfad
     * @param string $file_name   Gewuenschter Dateiname in WorkDrive
     * @return array|WP_Error     Upload-Ergebnis mit 'id' und 'url'
     */
    public function upload_file($folder_id, $file_path, $file_name) {
        if (!file_exists($file_path)) {
            return new WP_Error('file_not_found', 'Datei nicht gefunden: ' . $file_path);
        }

        $url = $this->api_base . 'upload?parent_id=' . $folder_id . '&override-name-exist=true';

        $boundary = wp_generate_password(24, false);
        $body  = '--' . $boundary . "\r\n";
        $body .= 'Content-Disposition: form-data; name="content"; filename="' . $file_name . '"' . "\r\n";
        $body .= 'Content-Type: ' . wp_check_filetype($file_path)['type'] . "\r\n\r\n";
        $body .= file_get_contents($file_path) . "\r\n";
        $body .= '--' . $boundary . '--';

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $this->get_token(),
                'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
            ],
            'body'    => $body,
            'timeout' => 60,
        ]);

        if (is_wp_error($response)) {
            $this->log_error('WorkDrive Upload fehlgeschlagen: ' . $response->get_error_message());
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $file_id = $data['data'][0]['attributes']['resource_id'] ?? null;

        if (!$file_id) {
            return new WP_Error('workdrive_upload', 'Upload-Antwort ohne resource_id.');
        }

        return [
            'id'  => $file_id,
            'url' => $this->get_share_link($file_id),
        ];
    }

    /**
     * Share-Link fuer eine Datei erstellen.
     */
    public function get_share_link($resource_id) {
        $cache_key = 'dgptm_wd_share_' . $resource_id;
        $cached = get_transient($cache_key);
        if ($cached) return $cached;

        $url = $this->api_base . 'files/' . $resource_id . '/links';
        $body = json_encode([
            'data' => [
                'attributes' => [
                    'link_type'   => 'view',
                    'request_type' => 'externallink',
                    'allow_download' => true,
                ],
                'type' => 'links',
            ],
        ]);

        $response = wp_remote_post($url, [
            'headers' => $this->headers(),
            'body'    => $body,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) return '';

        $data = json_decode(wp_remote_retrieve_body($response), true);
        $link = $data['data']['attributes']['link'] ?? '';

        if ($link) {
            set_transient($cache_key, $link, DAY_IN_SECONDS);
        }
        return $link;
    }

    /**
     * Ordner loeschen (fuer DSGVO-Cleanup).
     */
    public function delete_folder($folder_id) {
        $url = $this->api_base . 'files/' . $folder_id;
        $response = wp_remote_request($url, [
            'method'  => 'PATCH',
            'headers' => $this->headers(),
            'body'    => json_encode([
                'data' => [
                    'attributes' => ['status' => '61'], // Trash
                    'type' => 'files',
                ],
            ]),
            'timeout' => 15,
        ]);
        return !is_wp_error($response);
    }

    /* ── Hilfsfunktionen ─────────────────────── */

    private function ensure_subfolder($parent_id, $name) {
        // Existierenden Ordner suchen
        $url = $this->api_base . 'files/' . $parent_id . '/files?filter[type]=folder';
        $response = wp_remote_get($url, [
            'headers' => $this->headers(),
            'timeout' => 15,
        ]);

        if (!is_wp_error($response)) {
            $data = json_decode(wp_remote_retrieve_body($response), true);
            foreach (($data['data'] ?? []) as $item) {
                if (($item['attributes']['name'] ?? '') === $name) {
                    return $item['id'];
                }
            }
        }

        // Nicht gefunden — neu erstellen
        return $this->create_folder($parent_id, $name);
    }

    private function create_folder($parent_id, $name) {
        $url = $this->api_base . 'files';
        $body = json_encode([
            'data' => [
                'attributes' => [
                    'name'      => $name,
                    'parent_id' => $parent_id,
                ],
                'type' => 'files',
            ],
        ]);

        $response = wp_remote_post($url, [
            'headers' => $this->headers(),
            'body'    => $body,
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            $this->log_error('WorkDrive Ordner-Erstellung fehlgeschlagen: ' . $response->get_error_message());
            return $response;
        }

        $data = json_decode(wp_remote_retrieve_body($response), true);
        return $data['data']['id'] ?? new WP_Error('workdrive_create', 'Ordner-ID nicht in Antwort.');
    }

    private function sanitize_filename($str) {
        $str = remove_accents($str);
        $str = preg_replace('/[^a-zA-Z0-9_-]/', '_', $str);
        return $str;
    }

    private function log_error($message) {
        if (function_exists('dgptm_log_error')) {
            dgptm_log_error($message, 'stipendium');
        }
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add modules/business/stipendium/includes/class-workdrive.php
git commit -m "feat(stipendium): WorkDrive Integration (Ordner, Upload, Share-Links, Cleanup)"
```

---

### Task 10: class-dashboard-tab.php — Dashboard-Tab Registrierung

**Files:**
- Create: `modules/business/stipendium/includes/class-dashboard-tab.php`

- [ ] **Step 1: Dashboard-Tab Klasse erstellen**

```
modules/business/stipendium/includes/class-dashboard-tab.php
```

```php
<?php
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Dashboard_Tab {

    private $plugin_path;
    private $plugin_url;
    private $settings;

    const TABS_REGISTERED_KEY = 'dgptm_stipendium_tabs_registered_v1';

    public function __construct($plugin_path, $plugin_url, $settings) {
        $this->plugin_path = $plugin_path;
        $this->plugin_url  = $plugin_url;
        $this->settings    = $settings;

        add_action('init', [$this, 'ensure_dashboard_tabs']);
        add_shortcode('dgptm_stipendium_dashboard', [$this, 'render_dashboard']);
        add_shortcode('dgptm_stipendium_auswertung', [$this, 'render_auswertung']);
    }

    /**
     * Dashboard-Tabs registrieren (einmalig).
     *
     * Fuegt den "Stipendien"-Tab und den Unter-Tab "Auswertung"
     * in das Mitglieder-Dashboard ein.
     */
    public function ensure_dashboard_tabs() {
        if (get_option(self::TABS_REGISTERED_KEY)) return;

        $tabs = get_option('dgptm_dash_tabs_v3', []);

        // Pruefen ob Tab bereits existiert
        $existing_ids = array_column($tabs, 'id');

        if (!in_array('stipendien', $existing_ids)) {
            $tabs[] = [
                'id'         => 'stipendien',
                'label'      => 'Stipendien',
                'parent'     => '',
                'active'     => true,
                'order'      => 60,
                'permission' => 'acf:stipendiumsrat_mitglied',
                'link'       => '',
                'content'    => '[dgptm_stipendium_dashboard]',
                'content_mobile' => '',
                'visibility' => 'all',
            ];
        }

        if (!in_array('stipendien_auswertung', $existing_ids)) {
            $tabs[] = [
                'id'         => 'stipendien_auswertung',
                'label'      => 'Auswertung',
                'parent'     => 'stipendien',
                'active'     => true,
                'order'      => 61,
                'permission' => 'acf:stipendiumsrat_vorsitz',
                'link'       => '',
                'content'    => '[dgptm_stipendium_auswertung]',
                'content_mobile' => '',
                'visibility' => 'all',
            ];
        }

        update_option('dgptm_dash_tabs_v3', $tabs, false);
        update_option(self::TABS_REGISTERED_KEY, 1);
    }

    /**
     * Shortcode: Gutachter-Dashboard (Bewerbungsliste + Bewertung).
     *
     * HINWEIS: Vollstaendige Implementierung erfolgt nach Feedback des Stipendiumsrats.
     * Aktuell: Platzhalter mit Status-Anzeige.
     */
    public function render_dashboard($atts) {
        if (!is_user_logged_in()) return '';

        $user_id = get_current_user_id();
        $ist_mitglied = get_field('stipendiumsrat_mitglied', 'user_' . $user_id);
        if (!$ist_mitglied && !current_user_can('manage_options')) return '';

        // Aktive Runden ermitteln
        $typen = $this->settings->get('stipendientypen');
        $aktive_runden = array_filter($typen, function ($t) {
            return !empty($t['runde']);
        });

        ob_start();
        ?>
        <div class="dgptm-stipendium-dashboard">
            <h3>Stipendien</h3>
            <?php if (empty($aktive_runden)) : ?>
                <p>Aktuell sind keine Stipendienrunden konfiguriert.</p>
            <?php else : ?>
                <?php foreach ($aktive_runden as $typ) : ?>
                    <div class="dgptm-stipendium-runde-card" style="background:#f8f9fa;border:1px solid #dee2e6;border-radius:8px;padding:16px;margin-bottom:12px;">
                        <h4 style="margin:0 0 8px;"><?php echo esc_html($typ['bezeichnung']); ?></h4>
                        <p style="margin:0;color:#666;">
                            Runde: <strong><?php echo esc_html($typ['runde']); ?></strong>
                            <?php if (!empty($typ['start']) && !empty($typ['ende'])) : ?>
                                | Bewerbungszeitraum: <?php echo esc_html(date_i18n('d.m.Y', strtotime($typ['start']))); ?> &ndash; <?php echo esc_html(date_i18n('d.m.Y', strtotime($typ['ende']))); ?>
                            <?php endif; ?>
                        </p>
                        <p style="margin:8px 0 0;color:#888;font-style:italic;">
                            Bewerbungsliste und Bewertungsbogen werden nach Freigabe des Konzepts freigeschaltet.
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode: Auswertungs-Dashboard (nur Vorsitzender).
     *
     * HINWEIS: Vollstaendige Implementierung erfolgt nach Feedback des Stipendiumsrats.
     */
    public function render_auswertung($atts) {
        if (!is_user_logged_in()) return '';

        $user_id = get_current_user_id();
        $ist_vorsitz = get_field('stipendiumsrat_vorsitz', 'user_' . $user_id);
        if (!$ist_vorsitz && !current_user_can('manage_options')) return '';

        ob_start();
        ?>
        <div class="dgptm-stipendium-auswertung">
            <h3>Stipendien — Auswertung</h3>
            <p style="color:#888;font-style:italic;">
                Die Auswertungsfunktion wird nach Freigabe des Konzepts durch den Stipendiumsrat freigeschaltet.
            </p>
        </div>
        <?php
        return ob_get_clean();
    }
}
```

- [ ] **Step 2: Dashboard-Tabs pruefen**

1. Modul aktivieren (falls nicht aktiv)
2. Option `dgptm_stipendium_tabs_registered_v1` loeschen (fuer erneute Registrierung):
   `wp option delete dgptm_stipendium_tabs_registered_v1`
3. Seite neu laden
4. Im Mitglieder-Dashboard pruefen: Tab "Stipendien" sollte erscheinen (nur fuer User mit ACF-Feld)
5. Unter-Tab "Auswertung" sollte nur fuer User mit `stipendiumsrat_vorsitz` sichtbar sein

- [ ] **Step 3: Commit**

```bash
git add modules/business/stipendium/includes/class-dashboard-tab.php
git commit -m "feat(stipendium): Dashboard-Tab Registrierung mit Platzhalter-Shortcodes"
```

---

## FEEDBACK-GRENZE

> **Ab hier wartet die Implementierung auf das Feedback des Stipendiumsrats.**
>
> Die folgenden Tasks werden erst umgesetzt, wenn die Rueckmeldungen aus der digitalen Freigabe
> (`[dgptm_stipendium_freigabe]`) eingearbeitet sind. Aenderungen koennten betreffen:
>
> - Bewerbungsformular (Pflichtfelder, Upload-Typen, Formular-Schritte)
> - Bewertungsbogen (Rubriken, Gewichtung, Leitfragen)
> - Auswertungs-UI (Ranking-Darstellung, Export-Format)
> - E-Mail-Benachrichtigungen (Texte, Empfaenger)
> - Datenschutz-Details (Loeschfristen, Einwilligungstext)
> - Konfigurierbare Einstellungen (neue Optionen)
>
> **Naechster Schritt nach Feedback:**
> 1. Kommentare aus der Freigabe-Komponente auswerten
> 2. Design-Spec aktualisieren
> 3. Diesen Plan um Phase C (Frontend) ergaenzen
> 4. Implementierung fortsetzen

---

## Phase C: WordPress Frontend (nach Feedback)

_Die folgenden Tasks sind Platzhalter und werden nach Einarbeitung des Feedbacks detailliert._

### Task 11: class-bewerbung-form.php — Oeffentliches Bewerbungsformular
- Shortcode `[dgptm_stipendium_bewerbung typ="..."]`
- Multi-Step Formular (4 Schritte)
- DSGVO-Einwilligung
- WorkDrive-Upload
- Zoho CRM Record-Erstellung

### Task 12: class-bewertung-form.php — Digitaler Bewertungsbogen
- Inline-Formular im Dashboard
- 4 Rubriken x 3 Leitfragen
- Live-Score-Berechnung (JS)
- Entwurf speichern / Abschliessen
- Zoho CRM Bewertungs-Record

### Task 13: Dashboard-Templates — Uebersicht + Auswertung
- dashboard-uebersicht.php (Gutachter-Ansicht)
- dashboard-bewertung.php (Bewertungsbogen)
- dashboard-auswertung.php (Vorsitzender: Ranking, Export, Vergabe)

### Task 14: Assets — JavaScript + CSS
- bewerbung.js (Formular-Validierung, Upload-Progress)
- bewertung.js (Live-Score, Entwurf-Auto-Save)
- auswertung.js (Ranking-Tabelle, PDF-Trigger)
- stipendium.css (Alle Styles)

### Task 15: E-Mail Templates + Benachrichtigungen
- 6 E-Mail-Templates (Zoho + WordPress wp_mail Fallback)
- Trigger bei Status-Aenderungen

### Task 16: Integration Testing
- Vollstaendiger Durchlauf: Bewerbung → Freigabe → Bewertung → Auswertung → Export
- DSGVO: Loeschung testen
- Edge Cases: Doppelte Bewerbung, Gleichstand, Token-Ablauf
