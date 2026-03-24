# Design: Modul "finanzen" — Zusammenführung finanzbericht + mitgliedsbeitrag

**Datum:** 2026-03-24
**Status:** Genehmigt
**Module ersetzt:** `finanzbericht` (v1.1.0), `mitgliedsbeitrag` (v1.0.0)
**Referenz-Implementierung:** Python-Tool `../Mitgliedsbeitrag/`

## Zusammenfassung

Zusammenführung der Module `finanzbericht` und `mitgliedsbeitrag` in ein neues Modul `finanzen`. Die Mitgliedsbeitrag-Logik wird 1:1 vom Python-Tool portiert. Zusätzlich werden drei neue Features aus dem Python-Tool portiert: Offene Rechnungen, Mitgliederliste, Schatzmeister-Tracker. Die bestehende Finanzbericht-Funktionalität (Zoho Books Reports) wird integriert.

## Entscheidungen

| Entscheidung | Gewählt | Alternativen |
|---|---|---|
| Umfang | Alle 9 Python-Menüpunkte + Finanzberichte | Teilportierung |
| UI | Shortcode-Dashboard mit Tab-Navigation | WP-Admin-Menü, Hybrid |
| Background Tasks | Chunk-basierte AJAX-Verarbeitung (20/Chunk) | Synchron, WP-Cron |
| Zoho-Clients | Zwei spezialisierte (CRM + Books) | Ein kombinierter |
| Migration | Deprecate + Redirect, später löschen | Sofort löschen, Parallel |

## Dateistruktur

```
modules/business/finanzen/
├── finanzen.php                          # Einstiegspunkt, Singleton, Shortcode, AJAX-Router
├── module.json
├── includes/
│   ├── class-zoho-crm.php              # CRM: Mitglieder, COQL, Variablen, Blueprints
│   ├── class-zoho-books.php            # Books: Rechnungen, Credits, Zahlungen, Reports
│   ├── class-gocardless.php            # GoCardless: SEPA-Lastschriften, Mandate
│   ├── class-billing-engine.php        # Abrechnungslogik (1:1 vom Python-Tool)
│   ├── class-invoice-manager.php       # Offene Rechnungen + Kreditentscheidungen
│   ├── class-member-list.php           # Mitgliederliste aus CRM
│   ├── class-treasurer.php             # Auslagen-/Erstattungstracker
│   ├── class-chunk-processor.php       # Chunk-basierte Verarbeitung mit Progress
│   ├── class-config.php                # Konfigurationsmanagement (JSON-basiert)
│   ├── class-report-builder.php        # Finanzberichte (aus finanzbericht)
│   ├── class-historical-data.php       # Statische Daten 2023-2024 (aus finanzbericht)
│   └── class-access-logger.php         # Zugriffs-Logging (aus finanzbericht)
├── templates/
│   ├── admin.php                       # WP-Admin: Config/Credentials
│   └── dashboard.php                   # Shortcode-Dashboard mit Tab-Navigation
└── assets/
    ├── css/finanzen.css
    └── js/finanzen.js                  # Tab-Routing, Chunk-Polling, Rendering
```

## Tab-Struktur & Berechtigungen

### 9 Tabs im Shortcode `[dgptm_finanzen]`

| # | Tab | Python-Äquivalent | Quelle | Beschreibung |
|---|-----|-------------------|--------|--------------|
| 1 | Dashboard | `/` | Neu | KPIs, letzte Abrechnung, Billing-Status |
| 2 | Abrechnung | `/abrechnung` | mitgliedsbeitrag | Billing-Run starten (Dry/Live), Chunk-Progress |
| 3 | Mitglieder | `/mitglieder` | NEU (Python-Port) | Mitgliederliste, Filter, Ad-hoc-Auswahl |
| 4 | Ergebnisse | `/ergebnisse` | mitgliedsbeitrag (erweitert) | Historische Billing-Results |
| 5 | Zahlungen | `/zahlungen` | mitgliedsbeitrag (erweitert) | GoCardless-Status, Mandats-Sync |
| 6 | Rechnungen | `/rechnungen` | NEU (Python-Port) | Offene Rechnungen, Kreditentscheidungen |
| 7 | Finanzberichte | — (PHP-only) | finanzbericht | JT, SKK, Zeitschrift, Mitgliederzahlen |
| 8 | Schatzmeister | `/schatzmeister` | NEU (Python-Port) | Auslagen-/Erstattungstracker |
| 9 | Einstellungen | `/einstellungen` | mitgliedsbeitrag (erweitert) | Config-Viewer, Credentials |

### Berechtigungsmatrix

| Rolle | Sichtbare Tabs |
|-------|---------------|
| Administrator | Alle 9 |
| Schatzmeister | 1-8 (alles außer Einstellungen) |
| Präsident | 1, 4, 7 (Dashboard, Ergebnisse, Finanzberichte) |
| Geschäftsstelle | 1, 4, 7 (Dashboard, Ergebnisse, Finanzberichte) |

### Rollen-Ermittlung

Prüfreihenfolge: `manage_options` → ACF-Felder `schatzmeister` → `praesident` → `geschaeftsstelle`.

## Billing-Engine (1:1 Python-Port)

### 5 Invoice-Varianten

| Variante | Bedingung | Kredit | GoCardless |
|----------|-----------|--------|------------|
| `credit_sufficient` | Kredit ≥ Beitrag | Voll | Nein |
| `gocardless` | Mandat & Kredit = 0 | Nein | Ja |
| `gocardless_with_credit` | Mandat & 0 < Kredit < Beitrag | Teilweise | Ja |
| `transfer_with_credit` | Kein Mandat & 0 < Kredit < Beitrag | Teilweise | Nein |
| `transfer_no_credit` | Kein Mandat & Kredit = 0 | Nein | Nein |

### Prozess pro Mitglied

1. **Skip-Checks**: kein Typ, `skip_billing`, Status "Gestrichen", bereits abgerechnet (`letztesBeitragsjahr >= Jahr`), Status nicht in `allowed_contact_statuses`, Beitrag ≤ 0
2. **Freistellung**: `Freigestellt_bis ≥ 31.12.{Jahr}` → Status "exempted"; abgelaufen → Blueprint "Wieder kostenpflichtig machen" triggern
3. **Studentenbeitrag**: `Student_Status = true` UND `Valid_Through ≥ Jahr` → €10; sonst Reset (`Student_Status`, `Valid_Through`, `StudinachweisDirekt`)
4. **Books-Kontakt**: Suche/Reaktivierung via `Finance_ID`, Kredit abrufen (`unused_credits_receivable_amount`)
5. **Mandat**: Aus vorgeladenem Cache, Variante bestimmen
6. **Rechnung**: Erstellen mit Nummer `{Mitgliedsnr}-{Jahr}`, Duplikat-Check (Books Code 1001)
7. **Kredit/Zahlung**: Kredit anwenden, GoCardless-Payment erstellen (Amount in Cents)
8. **CRM-Update**: `letztesBeitragsjahr`, `last_fee`, `lastMembershipInvoicing`, `last_invoice`, `goCardlessPayment`, ggf. `Guthaben2`

### Rechnungsdatum-Logik (identisch zu Python)

```
invoice_date = max(1. März des Abrechnungsjahres, heute)
due_date = min(invoice_date + 4 Wochen, 31.12. des Abrechnungsjahres)
Falls Nachholabrechnung (Jahr < aktuelles Jahr): due_date = invoice_date + 4 Wochen
```

### Caching (Pre-Initialization)

| Cache | Quelle | Lebensdauer |
|-------|--------|-------------|
| Fees | CRM-Variablen (Gruppe "beitraege") | Pro Billing-Run |
| Mandate | GoCardless API (alle aktiven + pending_submission) | Pro Billing-Run |
| Books-Credits | Zoho Books API (Batch aller Finance_IDs) | Pro Billing-Run |
| Member-Stats | CRM COQL | 24h Transient, nightly Cron |

## Chunk-basierte Verarbeitung

### Flow

```
Frontend                          Backend (PHP)
   │                                  │
   ├─ AJAX: start_billing ──────────► Mitglieder laden, Caches aufbauen
   │  {year, dry_run, send_invoices}  Alles in Transient speichern
   │                                  ◄── {status: "ready", total: 454, chunk_size: 20}
   │                                  │
   ├─ AJAX: process_chunk ──────────► 20 Mitglieder verarbeiten
   │  {session_id, offset: 0}        Ergebnis akkumulieren in Transient
   │                                  ◄── {processed: 20, total: 454, results: [...]}
   │                                  │
   ├─ AJAX: process_chunk ──────────► Nächste 20
   │  {session_id, offset: 20}       ...
   │                                  │
   ├─ (Schleife bis offset >= total)  │
   │                                  │
   └─ AJAX: finalize_billing ───────► Zusammenfassung, Transient aufräumen
      {session_id}                    Ergebnis in wp_options speichern
                                      ◄── {summary: {...}, details: [...]}
```

### Transient-Struktur

```php
'dgptm_fin_billing_{session_id}' => [
    'members'    => [...],      // Alle geladenen Mitglieder
    'caches'     => [...],      // Fees, Mandate, Credits
    'config'     => [...],      // Billing-Parameter
    'results'    => [...],      // Akkumulierte Ergebnisse
    'processed'  => 0,          // Fortschrittszähler
    'started_at' => '...',
]
```

Timeout: 30 Minuten. Bei Abbruch durch Frontend: expliziter Cancel-Endpoint oder Transient läuft aus.

### Chunk-Processor Klasse (`class-chunk-processor.php`)

Generischer Prozessor für chunk-basierte Operationen. Wird von `billing-engine`, `process_payments` und `sync_mandates` genutzt.

```php
class DGPTM_FIN_Chunk_Processor {
    // Erstellt neue Session, speichert Items + Caches in Transient
    // Gibt session_id (uniqid + user_id Hash) zurück
    public function start(string $type, array $items, array $caches, array $config): string;

    // Verarbeitet nächsten Chunk. Offset kommt aus Transient (nicht vom Frontend).
    // Ruft $callback pro Item auf, akkumuliert Ergebnisse.
    public function process_next_chunk(string $session_id, callable $callback, int $chunk_size = 20): array;

    // Gibt Zusammenfassung zurück, löscht Transient
    public function finalize(string $session_id): array;

    // Expliziter Abbruch
    public function cancel(string $session_id): void;

    // Status abfragen (für Page-Refresh)
    public function get_status(string $session_id): ?array;

    // Prüft ob ein Billing-Run aktiv ist (globaler Lock)
    public static function is_locked(string $type = 'billing'): bool;
}
```

**Concurrency Guard:** `start()` setzt einen globalen Lock-Transient `dgptm_fin_lock_{type}`. Weitere `start()`-Aufrufe werden abgelehnt solange der Lock existiert. Lock wird bei `finalize()` oder `cancel()` entfernt. Timeout: 30 Min (gleich wie Session).

**Offset-Sicherheit:** Der `processed`-Zähler im Transient bestimmt den nächsten Offset. Das Frontend sendet nur `session_id`, keinen Offset. Doppelte Verarbeitung ist damit ausgeschlossen.

### Beziehung Billing-Engine ↔ Chunk-Processor

```
finanzen.php (AJAX-Handler)
    │
    ├─ start_billing()    → BillingEngine::prepare($year, ...) → ChunkProcessor::start('billing', $members, $caches)
    ├─ process_chunk()    → ChunkProcessor::process_next_chunk($sid, [BillingEngine, 'process_member'])
    ├─ finalize_billing() → ChunkProcessor::finalize($sid) → Ergebnis in wp_options speichern
    └─ cancel_billing()   → ChunkProcessor::cancel($sid)
```

`BillingEngine::prepare()` lädt Mitglieder + baut Caches auf (Fees, Mandate, Credits). `BillingEngine::process_member()` ist eine stateless Methode die einen einzelnen Kontakt verarbeitet und das Ergebnis-Dict zurückgibt. Die Caches werden aus dem Transient geladen.

## Neue Features (Python-Port)

### Rechnungen-Tab (`class-invoice-manager.php`)

- Alle unbezahlten Rechnungen mit `cf_beitragsrechnung = true` laden
- Anreicherung: GoCardless-Status, Kreditguthaben pro Kunde
- Sortierung: Entwürfe → Chargebacks → Fehlgeschlagen → Im Einzug → Hat Mandat → Rest
- **Aktionen pro Rechnung** (via `dgptm_fin_invoice_action` mit Parameter `action_type`):
  - `collect`: Zahlung einziehen — GoCardless-Payment erstellen, Payment-ID in `cf_gocardlessid` speichern
  - `apply_credit`: Kredit anwenden — `apply_credits_to_invoice()` + optional `collect` für Restbetrag
  - `chargeback`: Chargeback behandeln — `delete_payment()` für die bestehende Books-Zahlung, dann `update_invoice()` um €5 Gebühr als Zeile hinzuzufügen (konfigurierbar via `books.chargeback_fee`)
  - `send_draft`: Entwurf ohne Kredit versenden — Status prüfen (muss "draft" sein), dann `send_invoice()`
- **Gemeinsame Parameter:** `{action_type, invoice_id}` + aktions-spezifische Felder
- **NEU-Methoden** (im Python vorhanden, in PHP noch nicht): `delete_payment()`, `mark_contact_active()` — werden neu implementiert

### Mitglieder-Tab (`class-member-list.php`)

- COQL: Alle aktiven Mitglieder mit Billing-Feldern
- Tabelle: Name, Typ, Mitgliedsnr., Status, letztesBeitragsjahr, Mandat (ja/nein), Kredit
- Filter: Typ, Status, Abrechnungsstatus (abgerechnet/ausstehend/nie)
- Auswahl für Ad-hoc-Abrechnung → übergibt `contact_ids` an Abrechnungs-Tab

### Schatzmeister-Tab (`class-treasurer.php`)

- Auslagen-/Zuschuss-Erstattungstracker
- Datenspeicherung: **Zoho CRM + Books** (nicht wp_options) — 1:1 wie im Python-Tool
  - CRM-Modul "Expenses" (Status: "An Schatzmeister übergeben")
  - CRM-Modul "EduGrant" (Status: "An Schatzmeister übergeben")
  - Books-Bills (Custom Field `cf_zahlstatus` = "Schatzmeister benachrichtigt")
- Aktionen: "Überwiesen"-Button triggert Blueprint-Transition in CRM oder Status-Update in Books
- Bankdaten aus Config anzeigen (kein QR-Code wie im Python-Tool)

## Zoho-API-Clients

### `class-zoho-crm.php`

Konsolidiert aus mitgliedsbeitrag + finanzbericht CRM-Aufrufe:

- OAuth-Token via `crm-abruf`-Modul (Fallback: eigener Refresh)
- `get_members_for_billing($year)` — 3 COQL-Gruppen (OR statt IN)
- `get_member_stats()` — Mitgliederzahlen + Billing-Status
- `get_member_list($filters)` — NEU: Für Mitglieder-Tab
- `get_contact($id)`, `update_contact($id, $data)`
- `get_variable($name)`, `get_all_fees()`
- `trigger_blueprint($contact_id, $transition_name)`

### `class-zoho-books.php`

Konsolidiert aus beiden Modulen:

**Aus mitgliedsbeitrag (Rechnungen):**
- `create_invoice()`, `update_invoice()`, `send_invoice()`
- `get_invoice_by_number()`, `get_unpaid_invoices()`
- `get_contact_by_crm_id()`, `mark_contact_active()`
- `get_customer_credits()`, `apply_credits_to_invoice()`
- `record_payment()`, `delete_payment()`

**Aus finanzbericht (Reports):**
- `get_jt_income/expenses()`, `get_skk_income/expenses()`, `get_zeitschrift_income/expenses()`
- `get_open_invoices()`
- Pagination via `api_get_all()`

**Gemeinsam:**
- OAuth-Token-Management (Transient-Cache, Auto-Refresh bei 401)
- API-Base: `https://www.zohoapis.eu/books/v3`

### OAuth-Token-Strategie

| Transient-Key | API | Lifetime |
|---|---|---|
| `dgptm_fin_crm_token` | Zoho CRM | 55 Min |
| `dgptm_fin_books_token` | Zoho Books | 55 Min |

Beide Clients prüfen zuerst ob `crm-abruf` geladen ist (`class_exists('DGPTM_Zoho_Plugin')`). Falls ja: Token von dort. Falls nein: eigener Refresh via `zoho.client` Credentials aus `dgptm_fin_config`. Alte Transients (`dgptm_mb_crm_token`, `dgptm_mb_books_token`, `dgptm_fb_zoho_token`) laufen natürlich aus — keine Migration nötig.

### Beziehung zum bestehenden `gocardless`-Modul

Das `modules/payment/gocardless/`-Modul ist eine Formidable-Forms-Integration für Mandatsverwaltung (Frontend). Die `class-gocardless.php` im Finanzen-Modul ist ein separater API-Client für Zahlungseinzug (Backend). Beide nutzen denselben GoCardless Access-Token aus der Config. Kein Konflikt, da sie unterschiedliche API-Endpunkte ansprechen (Mandats-Setup vs. Payment-Creation).

## COQL — Kein IN-Operator

Alle COQL-Queries verwenden OR statt IN für Picklist-Felder:

```sql
-- KORREKT (verwendet OR):
SELECT ... FROM Contacts
WHERE Mitglied = true
  AND (Contact_Status = 'Aktiv' or Contact_Status = 'Freigestellt')
  AND (Membership_Type = 'Ordentliches Mitglied' or Membership_Type = 'Außerordentliches Mitglied' or ...)

-- FALSCH (IN funktioniert nicht zuverlässig):
SELECT ... FROM Contacts
WHERE Contact_Status IN ('Aktiv', 'Freigestellt')
```

Die `allowed_contact_statuses` und `membership_types` aus der Config werden dynamisch zu OR-Ketten gebaut via Helper-Funktion `build_or_clause($field, $values)`.

## Migration

### Automatische Datenmigration (einmalig)

Beim ersten Laden von `finanzen`, falls `dgptm_fin_migrated` nicht gesetzt:

| Quelle (alt) | Ziel (neu) |
|---|---|
| `dgptm_mb_config` | `dgptm_fin_config` |
| `dgptm_finanzbericht_credentials` | Integriert in `dgptm_fin_config` unter `zoho.books` |
| `dgptm_mb_billing_history` | `dgptm_fin_billing_history` |
| `dgptm_fb_imported_data` | `dgptm_fin_imported_data` |
| `dgptm_mb_last_results` | `dgptm_fin_last_results` |

Flag: `dgptm_fin_migrated = true` nach erfolgreicher Migration.

### Deprecated-Module

Beide alten Module erhalten:
- `"deprecated": true` in `module.json`
- Shortcode-Redirect: `[dgptm_finanzbericht]` und `[dgptm_mitgliedsbeitrag]` rendern intern `[dgptm_finanzen]` (volle Shortcode-Ersetzung mit neuer Nonce, kein HTML-Redirect)
- Hinweisbanner im Admin: "Dieses Modul wurde durch 'Finanzen' ersetzt"
- Alte AJAX-Handler werden entfernt (neue Nonce `dgptm_fin_nonce`)
- Geplante Löschung nach 2-3 Monaten

### Cron-Job Migration

Der bestehende Cron `dgptm_fb_nightly_refresh` (3:00 Uhr) wird ersetzt durch `dgptm_fin_nightly_refresh`. Bei Migration: alten Cron via `wp_unschedule_event()` entfernen, neuen registrieren. Aufgabe: Member-Stats-Transient erneuern.

### Access-Logger Tabelle

Die Tabelle `wp_dgptm_fb_access_log` wird weiterverwendet (kein Rename). Die neue `class-access-logger.php` nutzt denselben Tabellennamen. Bestehende Log-Daten bleiben erhalten. Bei endgültiger Löschung des alten Moduls: keine Bereinigung nötig, da die Tabelle vom neuen Modul übernommen wurde.

### Transient-Bereinigung

Alte Transients laufen natürlich aus (kein manuelles Löschen nötig):
- `dgptm_mb_crm_token`, `dgptm_mb_books_token` (55 Min)
- `dgptm_fb_zoho_token` (55 Min)
- `dgptm_fb_member_stats`, `dgptm_mb_member_stats` (24h)
- `dgptm_mb_crm_var_*` (24h)

## Module-Konfiguration

### `module.json`

```json
{
  "id": "finanzen",
  "name": "Finanzen",
  "description": "Mitgliedsbeitrag, Finanzberichte, Rechnungen, GoCardless — konsolidiertes Finanzmodul",
  "version": "1.0.0",
  "category": "business",
  "main_file": "finanzen.php",
  "icon": "dashicons-chart-area",
  "critical": false,
  "can_export": true,
  "php_required": "7.4",
  "wp_required": "5.8",
  "dependencies": [],
  "optional_dependencies": ["crm-abruf"],
  "features": [
    "Mitgliedsbeitrag-Abrechnungslauf (5 Varianten)",
    "Chunk-basierte Verarbeitung mit Fortschrittsanzeige",
    "Zoho CRM Integration (Mitglieder, COQL, Blueprints)",
    "Zoho Books Integration (Rechnungen, Credits, Finanzberichte)",
    "GoCardless SEPA-Lastschrift Integration",
    "Offene-Rechnungen-Management mit Kreditentscheidungen",
    "Mitgliederliste mit Filter und Ad-hoc-Abrechnung",
    "Finanzberichte (Jahrestagung, Sachkundekurs, Zeitschrift)",
    "Historische Finanzdaten 2023-2024",
    "Schatzmeister Auslagen-/Erstattungstracker",
    "Mandats-Synchronisation",
    "ACF-Berechtigungen (schatzmeister, praesident, geschaeftsstelle)",
    "Zugriffs-Logging"
  ]
}
```

## AJAX-Endpunkte

| Action | Handler | Berechtigung | Beschreibung |
|---|---|---|---|
| `dgptm_fin_get_dashboard` | Dashboard-KPIs | Alle Rollen | Übersichtsdaten |
| `dgptm_fin_start_billing` | Billing starten | Schatzmeister | Mitglieder laden, Caches aufbauen |
| `dgptm_fin_process_chunk` | Chunk verarbeiten | Schatzmeister | 20 Mitglieder pro Request |
| `dgptm_fin_finalize_billing` | Billing abschließen | Schatzmeister | Zusammenfassung + Aufräumen |
| `dgptm_fin_get_members` | Mitgliederliste | Schatzmeister | COQL mit Filtern |
| `dgptm_fin_get_results` | Ergebnisse laden | Alle Rollen | Historische Billing-Results |
| `dgptm_fin_process_payments` | Zahlungen verarbeiten | Schatzmeister | GoCardless paid_out buchen |
| `dgptm_fin_sync_mandates` | Mandate synchronisieren | Schatzmeister | E-Mail-Abgleich |
| `dgptm_fin_get_invoices` | Offene Rechnungen | Schatzmeister | Unbezahlte Beitragsrechnungen |
| `dgptm_fin_invoice_action` | Rechnungs-Aktion | Schatzmeister | Einziehen/Kredit/Chargeback/Senden |
| `dgptm_fin_get_report` | Finanzbericht | Alle Rollen | JT/SKK/Zeitschrift/Mitgliederzahlen |
| `dgptm_fin_refresh_cache` | Cache erneuern | Schatzmeister | Member-Stats neu laden |
| `dgptm_fin_treasurer_crud` | Schatzmeister CRUD | Schatzmeister | Erstattungen verwalten |
| `dgptm_fin_save_config` | Config speichern | Admin | JSON-Konfiguration |
| `dgptm_fin_upload_credentials` | Books-Credentials | Admin | Zoho Books Zugangsdaten |
| `dgptm_fin_import_historical` | Historische Daten | Admin | JSON-Import |
| `dgptm_fin_cancel_billing` | Billing abbrechen | Schatzmeister | Lock + Transient aufräumen |
| `dgptm_fin_get_billing_status` | Billing-Status | Schatzmeister | Aktive Session prüfen (Page-Refresh) |

Alle Endpunkte: Nonce-Verifizierung (`dgptm_fin_nonce`), Capability-Check. Nur `wp_ajax_`-Handler (kein `wp_ajax_nopriv_` — keine unauthentifizierten Zugriffe).

**"Alle Rollen"** = alle vier definierten Rollen (Admin, Schatzmeister, Präsident, Geschäftsstelle). Nicht eingeloggte Nutzer sehen nichts.

## Config-Schema (`dgptm_fin_config`)

Zusammengeführt aus mitgliedsbeitrag-Config + finanzbericht-Credentials:

```json
{
  "zoho": {
    "client": {
      "client_id": "...",
      "client_secret": "...",
      "refresh_token": "..."
    },
    "accounts_domain": "https://accounts.zoho.eu",
    "api_domain": "https://www.zohoapis.eu",
    "organization_id": "...",
    "crm_api_version": "v8",
    "books_api_version": "v3",
    "student_fee": 10.0,
    "allowed_contact_statuses": ["Aktiv", "Freigestellt"],
    "blueprints": {
      "reactivate_billing": "Wieder kostenpflichtig machen"
    }
  },
  "gocardless": {
    "access_token": "...",
    "api_url": "https://api.gocardless.com"
  },
  "membership_types": {
    "Ordentliches Mitglied": {
      "variable": "mbordentlich",
      "eligible_to_vote": true,
      "item_id": "...",
      "skip_billing": false
    }
  },
  "books": {
    "invoice_template_id": "...",
    "email_template_id": "...",
    "membership_item_id": "...",
    "account_id": "...",
    "gocardless_clearing_account_id": "...",
    "gocardless_fee_account_id": "...",
    "chargeback_fee": 5.0,
    "custom_fields": {
      "beitragsrechnung": "cf_beitragsrechnung",
      "gocardless_payment_id": "cf_gocardlessid"
    }
  },
  "invoice_variants": {
    "credit_sufficient": { "notes_template": "...", "process_gocardless": false, "apply_credit": true },
    "gocardless": { "notes_template": "...", "process_gocardless": true, "apply_credit": false },
    "gocardless_with_credit": { "notes_template": "...", "process_gocardless": true, "apply_credit": true },
    "transfer_with_credit": { "notes_template": "...", "process_gocardless": false, "apply_credit": true },
    "transfer_no_credit": { "notes_template": "...", "process_gocardless": false, "apply_credit": false }
  },
  "crm_fields": { "membership": {}, "student": {}, "payment": {}, "personal": {} },
  "bank_account": { "iban": "...", "bic": "...", "bank_name": "...", "account_holder": "..." }
}
```

Bei Migration: `dgptm_finanzbericht_credentials` wird unter `zoho.client` + `zoho.organization_id` eingemappt (Keys: `accounts_domain` → `zoho.accounts_domain`, `client_id` → `zoho.client.client_id`, etc.).

## Frontend-Architektur

### Dashboard-Template (`templates/dashboard.php`)

```html
<div class="dgptm-fin-wrap">
  <!-- Tab-Navigation (rollenbasiert gefiltert) -->
  <nav class="dgptm-fin-tabs">
    <button class="dgptm-fin-tab active" data-tab="dashboard">Dashboard</button>
    <button class="dgptm-fin-tab" data-tab="billing">Abrechnung</button>
    <!-- ... weitere Tabs, via PHP nur die erlaubten rendern -->
  </nav>

  <!-- Tab-Panels -->
  <div class="dgptm-fin-panel active" id="panel-dashboard">...</div>
  <div class="dgptm-fin-panel" id="panel-billing">...</div>
  <!-- ... -->
</div>
```

### JavaScript-Architektur (`assets/js/finanzen.js`)

Modularer Aufbau mit einem Controller-Objekt pro Tab:

```javascript
const DgptmFin = {
    state: { activeTab: 'dashboard', billingSession: null },
    tabs: {
        dashboard:  { init(), load() },
        billing:    { init(), startBilling(), processNextChunk(), finalize(), cancel() },
        members:    { init(), load(), filter(), selectForBilling() },
        results:    { init(), load() },
        payments:   { init(), processPayments(), syncMandates() },
        invoices:   { init(), load(), action(type, invoiceId) },
        reports:    { init(), loadReport() },
        treasurer:  { init(), crud() },
        settings:   { init(), save() },
    },
    // Gemeinsame Utilities
    ajax(action, data),    // $.post Wrapper mit Nonce
    feur(amount),          // EUR-Formatierung
    kpi(label, value, color),
    esc(html),
    renderTable(headers, rows),
};
```

**Tab-Switching:** Klick auf Tab → Panel wechseln → `tabs[name].load()` aufrufen (Lazy-Loading). Daten werden beim ersten Anzeigen geladen, nicht beim Seitenaufruf.

**CSS-Konvention:** BEM-ähnlich mit Prefix `dgptm-fin-`. Farben und Grid-Layout aus bestehenden Modulen übernommen.

## Nicht im Scope

- Keine neue Zoho-OAuth-Implementierung (nutzt `crm-abruf`)
- Kein CLI-Interface (Python hat `argparse`, nicht relevant für WordPress)
- Kein Thread-basiertes Task-Management (ersetzt durch Chunk-Processing)
- Keine QR-Code-Generierung (Python nutzt `segno`, nicht benötigt)
