# EIV-Fobi Sync: BÄK-Fortbildungsdaten in WordPress

**Datum:** 2026-03-24
**Status:** Genehmigt
**Module:** EFN-Manager (Quelle), Fortbildungsverwaltung (Consumer)

## Zusammenfassung

Täglicher automatischer Abruf von Fortbildungs-Teilnahmedaten der Mitglieder über die BÄK EIV-Fobi API. Die Daten werden im EFN-Manager verarbeitet, Veranstaltungen im `eiv_event_cache` CPT gecacht und als `fortbildung`-Posts mit EBCP-Punkten angelegt. Ersetzt den bisherigen Zoho-Deluge-Workflow durch eine native WordPress-Lösung.

## Architektur (Option C)

- **EFN-Manager** = Datenquelle: Token-Beschaffung, BÄK-API-Abruf, Event-Cache
- **Fortbildungsverwaltung** = Consumer: Stellt CPT `fortbildung` und Punkte-Mapping bereit

**Verworfene Alternativen:**
- *Option A:* Bestehende AEK-Logik im Fortbildungs-Modul ersetzen — abgelehnt, da Fortbildungs-Datei bereits >1700 Zeilen
- *Option B:* Alles im EFN-Manager, Fortbildung unverändert — abgelehnt, Mapping-Konfiguration wäre dupliziert

## Authentifizierungskette

```
WordPress crm-abruf OAuth Token
  → Zoho Function "test_baek"
  → JWT (5 min TTL, Rollen: fobi + efn-abo)
  → BÄK API (backend.eiv-fobi.de)
```

Token wird pro Batch-Lauf frisch geholt (nicht gecacht).

## Datenfluss

```
Cron (daily) / Manueller Button
  1. Zoho OAuth Token holen (DGPTM_Zoho_Plugin::get_oauth_token())
  2. Zoho Function "test_baek" aufrufen → JWT
  3. BÄK API: GET /aek/oidc/fobi/fobi_punkte?limit=0&offset=0&since={lastCall}
  4. Für jede Teilnahme:
     a. VNR in eiv_event_cache suchen
     b. Cache-Miss → GET /aek/oidc/fobi/anerkannte_veranstaltungen?vnr={VNR}
        → eiv_event_cache Post erstellen
     c. Event storniert? → Überspringen
     d. WP User finden (user_meta 'EFN' = teilnahme.efn)
        → Kein User? → Loggen, überspringen
     e. Dubletten-Check: fortbildung WHERE meta vnr = VNR AND meta user = user_id
        → Existiert? → Überspringen
     f. Punkte berechnen via fobi_aek_settings → mapping_json
     g. fortbildung-Post erstellen (wp_insert_post + update_field)
  5. eiv_last_call Timestamp aktualisieren (erst nach Erfolg)
```

## EIV Event Cache

**CPT:** `eiv_event_cache`
- ACF-Feldgruppe `group_eiv_event_cache` ist bereits in WordPress vorhanden (manuell angelegt)
- CPT `eiv_event_cache` muss per Code registriert werden
- Nicht öffentlich, Admin-UI unter EFN-Manager-Menü

**ACF-Felder:**

| Feld | ACF Key | Typ | Quelle (BÄK API) |
|------|---------|-----|-------------------|
| VNR | `field_eiv_vnr` | text (required, unique) | `vnr` |
| Titel | `field_eiv_title` | text | `thema` |
| Typ-Code | `field_eiv_typecode` | text | `kategorie` |
| Datum (Beginn) | `field_eiv_date` | text | `beginn` |
| Datum (Ende) | `field_eiv_enddate` | text | `ende` |
| Dauer (Minuten) | `field_eiv_duration` | number | berechnet aus beginn/ende |
| Ort | `field_eiv_location` | text | `ort` |
| Anbieter | `field_eiv_provider` | text | — (nicht in API) |
| Punkte | `field_eiv_points` | number | `punkte_basis` |
| Status | `field_eiv_status` | text | `storniert` → "storniert"/"aktiv" |

**Cache-Strategie:** Write-once, kein Invalidieren. Stornierte Events werden gecacht aber nicht importiert.

## Fortbildungs-Erstellung

**Pro Teilnahme-Datensatz:**

```php
$pid = wp_insert_post([
    'post_title'  => $event_title ?: 'BÄK-Veranstaltung ' . $vnr,
    'post_type'   => 'fortbildung',
    'post_status' => 'publish',
]);

update_field('user',       $wp_user_id, $pid);
update_field('date',       $date_ymd,   $pid);    // Y-m-d
update_field('location',   $location,   $pid);
update_field('points',     $ebcp_points,$pid);
update_field('type',       $type_label, $pid);     // z.B. "Kongress"
update_field('vnr',        $vnr,        $pid);
update_field('freigegeben', true,       $pid);

// Zusätzlich: post_author explizit setzen (Cron-Kontext hat keinen User)
// post_author = 1 (Admin) oder konfigurierbar
```

## Punkte-Berechnung

Nutzt bestehendes konfigurierbares Mapping aus `fobi_aek_settings → mapping_json`:

```json
[
  {"code": "A", "label": "Vortragsveranstaltung", "calc": "unit",  "points": 1, "unit_minutes": 45},
  {"code": "B", "label": "Kongress",              "calc": "fixed", "points": 3},
  ...
]
```

**calc-Modi:**
- `unit`: `ceil(dauer / unit_minutes) * points`
- `fixed`: `points` (konstant)
- `per_hour`: `round((dauer / 60) * points, 1)` (auf eine Dezimalstelle gerundet)
- Fallback bei fehlendem Mapping: 0 Punkte + Log-Warnung

## Dubletten-Vermeidung (zweistufig)

1. **Event-Cache:** `WP_Query` auf `eiv_event_cache` mit `meta_query` auf `vnr` → verhindert doppelte API-Calls
2. **Fortbildung:** `WP_Query` auf `fortbildung` mit `meta_query` auf `vnr` + `user` → verhindert doppelte Einträge pro User+Veranstaltung

**Hinweis zum VNR-Meta-Key:** Der neue Sync nutzt das ACF-Feld `vnr` (wie im Deluge-Skript und anderen Modulen). Die bestehende `fobi_aek_import_for_user_id()` nutzt den rohen Post-Meta-Key `aek_vnr`. Beide Wege koexistieren — der Dubletten-Check prüft **beide** Keys (`vnr` OR `aek_vnr` für denselben User), um Duplikate zwischen altem und neuem Import zu vermeiden.

## Admin-Settings (EFN-Manager)

| Setting | Typ | Default | Beschreibung |
|---------|-----|---------|--------------|
| `eiv_batch_enabled` | Checkbox | aus | Täglichen Abruf aktivieren |
| `eiv_start_date` | Date | leer | Startdatum für allerersten Abruf |
| `eiv_zoho_function` | Text | `test_baek` | Zoho-Funktion für BÄK-Token |
| `eiv_api_base` | Text | `https://backend.eiv-fobi.de` | BÄK API Base-URL |
| `eiv_last_call` | Readonly | automatisch | Letzter erfolgreicher Abruf (ISO 8601, z.B. `2026-03-24T08:00:00+01:00`) |

## Cron

- **Hook:** `dgptm_eiv_daily_sync`
- **Intervall:** `daily`
- **Scheduling:** Bei `eiv_batch_enabled` = true → `wp_schedule_event()`
- **Deaktivierung:** `wp_unschedule_event()`
- **lastCall-Logik:**
  - Erster Lauf: `since` = `eiv_start_date`
  - Folgeläufe: `since` = `eiv_last_call`
  - Aktualisierung erst nach erfolgreicher Verarbeitung

## Manueller Abruf

- Button "Jetzt von BÄK abrufen" auf der EFN-Manager Settings-Seite
- AJAX-Handler mit Live-Statusausgabe
- Erfordert `manage_options` Capability + Nonce-Verifizierung
- **Datums-Eingabe:** Vor dem Abruf wird ein Datum abgefragt, ab wann abgerufen werden soll (Date-Picker, Default: `eiv_last_call` oder `eiv_start_date`)
- **Ergebnistabelle:** Nach dem Abruf werden alle importierten Fortbildungen angezeigt:

| Spalte | Quelle |
|--------|--------|
| Benutzer (Klarname) | `display_name` des zugeordneten WP-Users |
| Veranstaltung | Titel aus Event-Cache |
| Punkte | Berechnete EBCP-Punkte |
| VNR | Veranstaltungsnummer |
| Status | "Importiert" / "Übersprungen (Dublette)" / "Übersprungen (kein User)" |

## Abhängigkeiten

**module.json (EFN-Manager):**
```json
{
  "dependencies": ["crm-abruf"],
  "optional_dependencies": ["fortbildung"]
}
```

**Laufzeit-Checks:**
- `function_exists('update_field')` → ACF vorhanden
- `post_type_exists('fortbildung')` → Fortbildungsmodul aktiv
- Falls nicht: Cron loggt Warnung und bricht ab

## API-Endpunkte (BÄK EIV-Fobi)

**Base-URL:** `https://backend.eiv-fobi.de`

| Endpunkt | Methode | Parameter | Zweck |
|----------|---------|-----------|-------|
| `/aek/oidc/fobi/fobi_punkte` | GET | `limit=0`, `offset=0`, `since` | Alle Teilnahmen seit Zeitpunkt |
| `/aek/oidc/fobi/anerkannte_veranstaltungen` | GET | `vnr` | Veranstaltungsdetails nach VNR |

**Auth-Header:** `Authorization: Bearer {JWT}`

**Hinweis `limit=0`:** Laut Swagger-Doku bedeutet `limit=0` "kein Limit" — alle Ergebnisse werden zurückgegeben. Falls die API dennoch paginiert antwortet, wird die Gesamtzahl der Ergebnisse geloggt.

## Fehlerbehandlung & Timeouts

- **HTTP-Timeout:** 30 Sekunden für alle API-Calls (`wp_remote_get` timeout)
- **Token-Fehler:** Wenn Zoho-OAuth oder BÄK-JWT fehlschlagen → Cron-Lauf abbrechen, loggen, `eiv_last_call` nicht aktualisieren
- **API-Fehler (4xx/5xx):** Einzelne fehlgeschlagene Event-Abrufe → überspringen, loggen, restliche Teilnahmen weiter verarbeiten
- **Rate Limiting (429):** Loggen und Cron-Lauf beenden — nächster Lauf holt fehlende Daten via `since`
- **Kein Retry:** Bei transientem Fehler wird nicht wiederholt — der nächste tägliche Cron-Lauf holt alle Daten seit `eiv_last_call` nach

## Bestehender Code

Die existierende `fobi_aek_import_for_user_id()` in der Fortbildungsverwaltung bleibt bestehen (Backward Compatibility). Der neue EIV-Sync im EFN-Manager ist ein paralleler, effizienterer Workflow (Bulk statt per-User).

## module.json Anpassungen

**EFN-Manager `module.json`** muss aktualisiert werden:
- `"optional_dependencies": ["fortbildung"]` hinzufügen (statt leeres Array)
- `"category": "utilities"` korrigieren (aktuell fälschlicherweise `"fortbildung"`, Kategorie existiert nicht)

## Logging

Nutzt den DGPTM Suite Logger (`dgptm_crm_log()` aus crm-abruf) für alle Sync-Aktivitäten. Zusammenfassung pro Lauf: "EIV-Sync: X importiert, Y übersprungen (davon Z Dubletten), W Fehler". Sichtbar im Plugin-Suite Dashboard unter System Logs.
