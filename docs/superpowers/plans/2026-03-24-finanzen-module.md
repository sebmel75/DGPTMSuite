# Finanzen-Modul Implementierungsplan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Zusammenführung der Module `finanzbericht` und `mitgliedsbeitrag` in ein neues konsolidiertes `finanzen`-Modul mit vollständiger Python-Tool-Portierung.

**Architecture:** WordPress-Modul mit Singleton-Pattern, 12 PHP-Klassen in `includes/`, einem Shortcode-Dashboard mit 9 rollenbasierten Tabs, chunk-basierter AJAX-Verarbeitung für Billing-Runs, und zwei spezialisierten Zoho-API-Clients (CRM + Books). Die Billing-Logik wird 1:1 vom Python-Referenz-Tool portiert.

**Tech Stack:** PHP 7.4+ / WordPress 5.8+ / jQuery / Zoho CRM v8 + Books v3 API / GoCardless API / ACF

**Spec:** `docs/superpowers/specs/2026-03-24-finanzen-module-design.md`
**Python-Referenz:** `../Mitgliedsbeitrag/membership_billing.py`

**Hinweis:** Kein automatisiertes Test-Framework vorhanden. Verifizierung erfolgt über WordPress-Admin (Modul aktivieren, System-Logs prüfen, Browser-Konsole prüfen).

---

## Task 1: Modul-Skeleton + Konfiguration

**Files:**
- Create: `modules/business/finanzen/module.json`
- Create: `modules/business/finanzen/finanzen.php`
- Create: `modules/business/finanzen/includes/class-config.php`

Ziel: Leeres Modul das in der DGPTM Suite sichtbar ist und aktiviert werden kann.

- [ ] **Step 1: module.json erstellen**

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

- [ ] **Step 2: class-config.php erstellen**

Portiert von `modules/business/mitgliedsbeitrag/includes/class-config.php` (118 Zeilen). Klasse `DGPTM_FIN_Config`. Option-Key: `dgptm_fin_config`. Gleiche Dot-Notation-API, gleiche Convenience-Accessors, erweitertes Config-Schema mit Books-Credentials (zusammengeführt aus finanzbericht).

Zusätzliche Accessors gegenüber dem Original:
- `is_valid(): bool` — Prüft alle erforderlichen Felder (zoho.client.*, gocardless.access_token, books.*). Portiert von mitgliedsbeitrag Zeilen 25-30.
- `books_credentials(): array` — Zugriff auf `zoho.books` Sub-Config (aus finanzbericht migriert)
- `blueprint(string $name): string` — Zugriff auf `zoho.blueprints.$name`
- `chargeback_fee(): float` — Zugriff auf `books.chargeback_fee` (default: 5.0)
- `bank_account(): array` — Zugriff auf `bank_account`

- [ ] **Step 3: finanzen.php Skeleton erstellen**

Hauptklasse `DGPTM_Finanzen` (final, Singleton). Boilerplate nach CLAUDE.md-Vorgabe. Registriert:
- Shortcode `[dgptm_finanzen]` via `init` Hook
- Admin-Menü via `admin_menu` Hook
- Alle AJAX-Handler (18 Stück, zunächst als leere Stubs mit `wp_send_json_error('Not implemented')`)
- Cron-Job `dgptm_fin_nightly_refresh` (täglich 3:00 Uhr)
- `includes/`-Dateien via `require_once` (nur wenn Datei existiert, für inkrementelle Entwicklung)

Konstanten:
```php
const NONCE = 'dgptm_fin_nonce';
const OPT_CONFIG = 'dgptm_fin_config';
const OPT_RESULTS = 'dgptm_fin_last_results';
const OPT_HISTORY = 'dgptm_fin_billing_history';
const ROLE_FIELDS = ['schatzmeister', 'praesident', 'geschaeftsstelle'];
```

Rollen-Methoden (aus finanzbericht.php Zeilen 84-119 portiert):
- `get_user_role(int $user_id): string`
- `user_has_access(int $user_id): bool` — Schatzmeister oder höher
- `user_can_view(int $user_id): bool` — Alle 4 Rollen
- `user_is_admin(int $user_id): bool` — manage_options
- `get_visible_tabs(int $user_id): array` — Gibt Tab-IDs zurück basierend auf Rolle. Admin: alle 9. Schatzmeister: 1-8. Präsident/Geschäftsstelle: [dashboard, results, reports].
- `get_tab_access(int $user_id): array` — Assoziatives Array `{tab_id => bool}` für JS-Rendering.

- [ ] **Step 4: Verifizierung**

Modul in DGPTM Suite Admin sichtbar, aktivierbar, Shortcode rendert Platzhalter.

- [ ] **Step 5: Commit**

```
feat(finanzen): Modul-Skeleton mit Config-Klasse und AJAX-Stubs
```

---

## Task 2: Zoho CRM Client

**Files:**
- Create: `modules/business/finanzen/includes/class-zoho-crm.php`

Konsolidiert aus `mitgliedsbeitrag/includes/class-zoho-crm.php` (292 Zeilen) + `finanzbericht.php` COQL-Queries (Zeilen 264-338).

- [ ] **Step 1: Klasse DGPTM_FIN_Zoho_CRM erstellen**

Referenz: `mitgliedsbeitrag/includes/class-zoho-crm.php` als Basis. Klassenname: `DGPTM_FIN_Zoho_CRM`.

Token-Transient: `dgptm_fin_crm_token` (55 Min). Token-Quelle: Zuerst `crm-abruf` Modul prüfen (`class_exists('DGPTM_Zoho_Plugin')`), sonst eigener Refresh.

Private Methoden:
- `get_token(): ?string` — OAuth-Token abrufen (aus mitgliedsbeitrag Zeilen 26-64)
- `api_request(string $endpoint, string $method, ?array $body): ?array` — HTTP-Client (Zeilen 66-99)
- `coql_query(string $query): array` — COQL-Ausführung (Zeilen 101-131)
- `build_or_clause(string $field, array $values): string` — **NEU**: Dynamischer OR-Ketten-Builder für COQL

Public Methoden:
- `get_members_for_billing(int $year): array` — 3 COQL-Gruppen (Zeilen 137-161, mit OR statt IN)
- `get_member_stats(): array` — Mitgliederzahlen + Billing-Status (aus finanzbericht.php Zeilen 264-338)
- `get_member_list(array $filters = []): array` — **NEU**: Für Mitglieder-Tab, COQL mit optionalen Filtern
- `get_contact(string $id): ?array` — Einzelkontakt (Zeilen 166-169)
- `update_contact(string $id, array $data): bool` — Kontakt-Update (Zeilen 174-179)
- `get_variable(string $name): ?float` — CRM-Variable (Zeilen 185-200, Transient 1 Tag)
- `get_all_fees(): array` — Alle Beitragsgebühren (Zeilen 205-216)
- `trigger_blueprint(string $contact_id, string $transition_name): bool` — Blueprint (Zeilen 222-243)
- `get_all_members_with_finance_id(): array` — **NEU**: Für CRM-Lookup in Rechnungs-Tab

**COQL-Regel:** Alle Queries nutzen `build_or_clause()` für Picklist-Felder (Contact_Status, Membership_Type). Niemals IN-Operator.

- [ ] **Step 2: Verifizierung**

Klasse ohne Syntaxfehler ladbar. Token-Abruf testbar via temporären AJAX-Stub.

- [ ] **Step 3: Commit**

```
feat(finanzen): Zoho CRM Client mit COQL OR-Builder
```

---

## Task 3: Zoho Books Client

**Files:**
- Create: `modules/business/finanzen/includes/class-zoho-books.php`

Konsolidiert aus `mitgliedsbeitrag/includes/class-zoho-books.php` (202 Zeilen) + `finanzbericht/includes/class-zoho-books-client.php` (412 Zeilen).

- [ ] **Step 1: Klasse DGPTM_FIN_Zoho_Books erstellen**

Token-Transient: `dgptm_fin_books_token` (55 Min). API-Base: `https://www.zohoapis.eu/books/v3`.

Private Methoden:
- `get_token(): string` — OAuth mit Transient-Cache
- `api_request(string $endpoint, string $method, ?array $body, array $query): ?array` — HTTP-Client mit 401-Retry
- `api_get_all(string $endpoint, string $list_key, array $params): array` — Paginierung (200/Seite)

**Aus mitgliedsbeitrag (Rechnungen):**
- `get_contact_by_crm_id(string $crm_id): ?array`
- `mark_contact_active(string $contact_id): bool` — **NEU** (Python Zeile 245)
- `get_customer_credits(string $customer_id): float`
- `create_invoice(array $data, bool $ignore_auto_number = true): ?array` — mit Code-1001-Handling
- `update_invoice(string $invoice_id, array $data, string $reason = ''): ?array`
- `get_invoice(string $invoice_id): ?array` — **NEU** (Python Zeile 422)
- `get_invoice_by_number(string $number): ?array`
- `get_unpaid_invoices(?string $cf_filter = null): array` — Paginiert
- `send_invoice(string $invoice_id, array $to_emails, string $template_id = ''): bool`
- `get_invoice_email_content(string $invoice_id, ?string $template_id = null): ?array` — **NEU** (Python Zeile 600)
- `apply_credits_to_invoice(string $invoice_id, float $amount): bool` — **Erweitert**: Credit Notes + Excess Payments (Python Zeilen 339-420)
- `record_payment(...): ?array` — Vollständig (mitgliedsbeitrag Zeilen 181-201)
- `delete_payment(string $payment_id): bool` — **NEU** (Python Zeile 514)
- `add_charge_to_invoice(string $invoice_id, float $amount, string $description, string $account_id): bool` — **NEU** (Python Zeile 526)
- `list_taxes(): array` — **NEU** (Python Zeile 328)

**Aus finanzbericht (Reports):**
- `get_jt_income(string $start, string $end): array` — JT-Einnahmen (finanzbericht Zeilen 145-199)
- `get_jt_expenses(string $start, string $end): array` — JT-Ausgaben (Zeilen 205-259)
- `get_skk_income(string $start, string $end): array` — SKK-Einnahmen (Zeilen 265-312)
- `get_skk_expenses(string $start, string $end): array` — SKK-Ausgaben (Zeilen 318-320)
- `get_zeitschrift_income(string $start, string $end): array` — Zeitschrift-Einnahmen (Zeilen 326-348)
- `get_zeitschrift_expenses(string $start, string $end): array` — Zeitschrift-Ausgaben (Zeilen 354-356)
- `get_open_invoices(): array` — Offene Rechnungen (Zeile 362-364)

- [ ] **Step 2: Verifizierung**

Klasse ohne Syntaxfehler ladbar. Methoden-Signaturen prüfen.

- [ ] **Step 3: Commit**

```
feat(finanzen): Konsolidierter Zoho Books Client (Rechnungen + Reports)
```

---

## Task 4: GoCardless Client

**Files:**
- Create: `modules/business/finanzen/includes/class-gocardless.php`

Portiert von `mitgliedsbeitrag/includes/class-gocardless.php` (134 Zeilen), erweitert um Python-Methoden.

- [ ] **Step 1: Klasse DGPTM_FIN_GoCardless erstellen**

1:1 Port von mitgliedsbeitrag `DGPTM_MB_GoCardless`. Klassenname: `DGPTM_FIN_GoCardless`.

Zusätzliche Methoden aus Python (GoCardless Klasse, Zeilen 667-827):
- `get_payment_with_fees(string $payment_id): ?array` — Payment + Fee-Berechnung (€0.20 + 1%)
- `get_payout(string $payout_id): ?array` — Payout-Details

Alle bestehenden Methoden 1:1 übernehmen:
- `get_mandate()`, `is_mandate_active()`, `get_usable_mandate_for_customer()`
- `get_all_active_mandates()`, `create_payment()`, `get_payment()`, `get_all_customers()`

- [ ] **Step 2: Commit**

```
feat(finanzen): GoCardless Client mit Fee-Berechnung
```

---

## Task 5: Chunk-Processor

**Files:**
- Create: `modules/business/finanzen/includes/class-chunk-processor.php`

Neue Klasse gemäß Spec. Generischer Prozessor für chunk-basierte Operationen.

- [ ] **Step 1: Klasse DGPTM_FIN_Chunk_Processor erstellen**

```php
class DGPTM_FIN_Chunk_Processor {
    const LOCK_PREFIX = 'dgptm_fin_lock_';
    const SESSION_PREFIX = 'dgptm_fin_session_';
    const TTL = 1800; // 30 Minuten
    const CHUNK_SIZE = 20;
```

Methoden:
- `start(string $type, array $items, array $caches, array $config): string` — Session erstellen, Lock setzen, Transient speichern. Session-ID: `uniqid($type . '_' . get_current_user_id() . '_')`. Prüft globalen Lock.
- `process_next_chunk(string $session_id, callable $callback, int $chunk_size = 20): array` — Nächste N Items aus Transient holen (Offset aus `processed`-Zähler), Callback pro Item, Ergebnis akkumulieren, Transient aktualisieren. Returns: `{processed, total, chunk_results, done}`.
- `finalize(string $session_id): array` — Zusammenfassung aus Transient holen, Transient + Lock löschen. Returns: akkumuliertes Ergebnis.
- `cancel(string $session_id): void` — Transient + Lock sofort löschen.
- `get_status(string $session_id): ?array` — Aktuellen Status abrufen (für Page-Refresh). Returns: `{type, processed, total, started_at, config}` oder null.
- `static is_locked(string $type = 'billing'): bool` — Prüft ob Lock-Transient existiert.

Transient-Struktur:
```php
[
    'type'       => 'billing',
    'items'      => [...],
    'caches'     => [...],
    'config'     => [...],
    'results'    => [],
    'processed'  => 0,
    'total'      => count($items),
    'started_at' => current_time('mysql'),
    'user_id'    => get_current_user_id(),
]
```

- [ ] **Step 2: Commit**

```
feat(finanzen): Chunk-Processor mit Concurrency-Lock
```

---

## Task 6: Billing-Engine

**Files:**
- Create: `modules/business/finanzen/includes/class-billing-engine.php`

1:1 Port von Python `MembershipBilling` (Zeilen 829-2441) + bestehendem PHP `DGPTM_MB_Billing_Engine` (491 Zeilen). Refactored für Chunk-Processing.

- [ ] **Step 1: Klasse DGPTM_FIN_Billing_Engine erstellen — Prepare + Caches**

Statische und Cache-Methoden zuerst. Referenz: Python Zeilen 876-1043, PHP Zeilen 470-489.

```php
class DGPTM_FIN_Billing_Engine {
    private $crm;     // DGPTM_FIN_Zoho_CRM
    private $books;   // DGPTM_FIN_Zoho_Books
    private $gc;      // DGPTM_FIN_GoCardless
    private $config;  // DGPTM_FIN_Config
```

Methoden:
- `prepare(int $year, bool $dry_run, bool $send_invoices, array $contact_ids = []): array` — Lädt Mitglieder + baut Caches auf. Returns: `{members, caches: {fees, mandates, books_credits}, config: {year, dry_run, send_invoices}}`. Wird von `start_billing` AJAX aufgerufen und in Transient gespeichert.
- `preload_all_fees(): array` — CRM-Variablen laden (Python Zeilen 876-904)
- `preload_gocardless_mandates(): array` — Alle Mandate laden (Python Zeilen 906-970)
- `preload_books_credits(array $members): array` — Credits batch-laden (Python Zeilen 972-1023)

- [ ] **Step 2: process_member() portieren**

Kernlogik 1:1 vom Python (Zeilen 1303-1557) + bestehendem PHP (Zeilen 97-266). Methode muss stateless sein (bekommt Caches als Parameter).

```php
public static function process_member(
    array $member,
    int $year,
    bool $dry_run,
    bool $send_invoices,
    array $caches,
    DGPTM_FIN_Config $config,
    DGPTM_FIN_Zoho_CRM $crm,
    DGPTM_FIN_Zoho_Books $books,
    DGPTM_FIN_GoCardless $gc
): array
```

Exakte Skip-Check Reihenfolge (Python Zeilen 1320-1385):
1. Kein membership_type → skip
2. skip_billing in Config → skip
3. Contact_Status == "Gestrichen" → skip
4. letztesBeitragsjahr >= year → skip
5. Contact_Status nicht in allowed_statuses → skip
6. Freigestellt_bis >= 31.12.{year} → exempted
7. Fee <= 0 → skip
8. Freistellung abgelaufen → Blueprint triggern

Dann: Studentenbeitrag, Finance_ID, Credit, Mandate, Variante, Rechnung, Zahlung, CRM-Update.

Hilfsmethoden:
- `check_student_status(array $contact, int $year): array` — Python Zeilen 1074-1102
- `get_effective_fee(array $contact, string $type, int $year, array $fee_cache): array` — Python Zeilen 1104-1123
- `determine_variant(float $fee, float $credit, bool $has_mandate): string` — PHP Zeilen 272-286
- `create_membership_invoice(...)` — PHP Zeilen 292-340 + Python Zeilen 1593-1659
- `get_invoice_notes(string $variant, float $credit, float $fee, int $year): string` — Python Zeilen 1290-1301
- `calculate_invoice_dates(int $year): array` — Python Zeilen 1559-1591
- `get_contact_emails(array $contact): array` — Python Zeilen 1205-1223
- `get_or_update_finance_id(array $contact): ?string` — Python Zeilen 1125-1172
- `get_books_credit(array $contact, array $credit_cache): float` — Python Zeilen 1174-1203
- `get_missing_billing_years(array $contact, int $year): array` — Python Zeilen 1688-1716

- [ ] **Step 3: process_gocardless_payments() portieren**

Port von PHP Zeilen 346-394 + Python Zeilen 1867-2164. Erweitert um:
- Auto-Collection für Rechnungen ohne Payment-ID (Python Zeilen 1920-1965)
- Fee-Berechnung: €0.20 + 1% (Python Zeile 803)
- Chargeback-Erkennung (Python Zeilen 2020-2050)

- [ ] **Step 4: sync_gocardless_mandates() portieren**

Port von PHP Zeilen 400-464 + Python Zeilen 1718-1865. Erweitert um:
- 4-Feld E-Mail-Abgleich (Email, Secondary_Email, Third_Email, DGPTMMail)
- Case-insensitive Matching

- [ ] **Step 5: Commit**

```
feat(finanzen): Billing-Engine mit 5 Varianten und Chunk-Kompatibilitaet
```

---

## Task 7: Invoice-Manager (Python-Port)

**Files:**
- Create: `modules/business/finanzen/includes/class-invoice-manager.php`

Portiert von Python `dashboard/routes/invoices.py` (595 Zeilen).

- [ ] **Step 1: Klasse DGPTM_FIN_Invoice_Manager erstellen**

Methoden:
- `get_open_invoices(): array` — Offene Beitragsrechnungen laden, anreichern, sortieren. Transient-Cache 5 Min (`dgptm_fin_open_invoices`). Referenz: Python `api_invoices()` (Zeilen ca. 60-150).
- `enrich_invoices(array $invoices): array` — GoCardless-Status + Credits pro Rechnung (Python Zeilen ca. 150-200).
- `sort_invoices(array $invoices): array` — Sortierreihenfolge: Entwürfe → Chargebacks → Failed → Im Einzug → Hat Mandat → Rest.
- `collect_payment(string $invoice_id): array` — GoCardless-Payment erstellen (Python `collect_payment()`, Zeilen ca. 300-380).
- `handle_chargeback(string $invoice_id, string $action): array` — Chargeback behandeln: delete_payment + add_fee (Python `handle_chargeback()`, Zeilen ca. 380-430).
- `apply_credit(string $invoice_id, bool $collect_remainder = false): array` — Kredit anwenden (Python `apply_credit()`, Zeilen ca. 430-530).
- `send_without_credit(string $invoice_id): array` — Entwurf senden (Python `send_without_credit()`, Zeilen ca. 530-560).
- `invalidate_cache(): void` — Cache löschen.

- [ ] **Step 2: Commit**

```
feat(finanzen): Invoice-Manager mit Kredit-/Chargeback-Handling
```

---

## Task 8: Member-List + Treasurer + Bestandsklassen

**Files:**
- Create: `modules/business/finanzen/includes/class-member-list.php`
- Create: `modules/business/finanzen/includes/class-treasurer.php`
- Create: `modules/business/finanzen/includes/class-report-builder.php`
- Create: `modules/business/finanzen/includes/class-historical-data.php`
- Create: `modules/business/finanzen/includes/class-access-logger.php`

- [ ] **Step 1: class-member-list.php erstellen**

Portiert von Python `dashboard/routes/members.py` (140 Zeilen). Klasse `DGPTM_FIN_Member_List`.

Methoden:
- `get_members(int $year, array $filters = []): array` — COQL-Abfrage aller aktiven Mitglieder. Filter: Typ, Status, Abrechnungsstatus. Nutzt `DGPTM_FIN_Zoho_CRM::get_member_list()`.
- `get_billing_errors(int $year): array` — Fehlerzuordnung aus letztem Billing-Ergebnis (Python `_get_billing_errors()`).

- [ ] **Step 2: class-treasurer.php erstellen**

Portiert von Python `dashboard/routes/treasurer.py` (338 Zeilen). Klasse `DGPTM_FIN_Treasurer`. Speicherung: `wp_options` Key `dgptm_fin_treasurer_entries`.

Methoden:
- `get_entries(): array` — Alle offenen Erstattungsanträge laden. Aus CRM-Modulen "Expenses" + "EduGrant" + Books-Bills.
- `mark_transferred(string $module, string $record_id, string $transition_id): bool` — Blueprint triggern oder Books-Status updaten.

Hinweis: Kein QR-Code (Python nutzt `segno`). Bankdaten aus Config anzeigen stattdessen.

**Wichtig:** Der Treasurer-Tab lädt Daten aus Zoho CRM-Modulen ("Expenses", "EduGrant") und Zoho Books (Bills mit `cf_zahlstatus`), NICHT aus `wp_options`. Dies weicht von der Spec ab (die `wp_options`-basiertes CRUD beschreibt), entspricht aber dem Python-Tool. Die Spec wird entsprechend aktualisiert.

- [ ] **Step 3: class-report-builder.php kopieren + anpassen**

1:1 von `finanzbericht/includes/class-report-builder.php` (183 Zeilen). Klassenname: `DGPTM_FIN_Report_Builder`. Änderungen:
- Books-Client als Constructor-Parameter (Dependency Injection) statt `new DGPTM_FB_Zoho_Books_Client()` in `build_dynamic()` (Zeile 73)
- CRM API-Version: Konfigurierbar via Config statt hardcoded `/crm/v7/` (finanzbericht.php Zeile 274 nutzte v7, neues Modul nutzt v8)

- [ ] **Step 4: class-historical-data.php kopieren + anpassen**

1:1 von `finanzbericht/includes/class-historical-data.php` (242 Zeilen). Klassenname: `DGPTM_FIN_Historical_Data`. Import-Option: `dgptm_fin_imported_data`.

- [ ] **Step 5: class-access-logger.php kopieren + anpassen**

1:1 von `finanzbericht/includes/class-access-logger.php` (83 Zeilen). Klassenname: `DGPTM_FIN_Access_Logger`. Gleicher Tabellenname `wp_dgptm_fb_access_log` (Daten bleiben erhalten).

- [ ] **Step 6: Commit**

```
feat(finanzen): Member-List, Treasurer, Report-Builder, Historical-Data, Access-Logger
```

---

## Task 9: AJAX-Handler implementieren

**Files:**
- Modify: `modules/business/finanzen/finanzen.php`

Alle 18 AJAX-Stubs mit echter Logik befüllen.

- [ ] **Step 1: Dashboard + Stats Handler**

- `ajax_get_dashboard()` — KPIs laden: Member-Stats (24h Cache), letzte Billing-Ergebnisse, offene Rechnungen-Count. Berechtigung: `user_can_view()`.
- `ajax_refresh_cache()` — Member-Stats-Transient löschen, neu laden. Berechtigung: `user_has_access()`.

Referenz: finanzbericht.php Zeilen 213-262 (member stats), mitgliedsbeitrag.php Zeilen 161-219 (get_status).

- [ ] **Step 2: Billing Handler (Chunk-basiert)**

- `ajax_start_billing()` — `BillingEngine::prepare()` aufrufen, `ChunkProcessor::start()`. Params: year, dry_run, send_invoices, contact_ids. Berechtigung: `user_has_access()`. Hinweis: Der `create_invoices`-Parameter aus dem alten Modul entfällt — `dry_run=false` impliziert `create_invoices=true`.
- `ajax_process_chunk()` — Service-Objekte pro Request neu erstellen (nicht serialisierbar):

```php
$config = DGPTM_FIN_Config::load();
$crm = new DGPTM_FIN_Zoho_CRM($config);
$books = new DGPTM_FIN_Zoho_Books($config);
$gc = new DGPTM_FIN_GoCardless($config);
$callback = function($member) use ($session_data, $config, $crm, $books, $gc) {
    return DGPTM_FIN_Billing_Engine::process_member(
        $member, $session_data['config']['year'],
        $session_data['config']['dry_run'],
        $session_data['config']['send_invoices'],
        $session_data['caches'], $config, $crm, $books, $gc
    );
};
$result = $processor->process_next_chunk($session_id, $callback);
```

Param: session_id. Berechtigung: `user_has_access()`.
- `ajax_finalize_billing()` — `ChunkProcessor::finalize()`, Ergebnis in `dgptm_fin_last_results` + `dgptm_fin_billing_history` speichern. Berechtigung: `user_has_access()`.
- `ajax_cancel_billing()` — `ChunkProcessor::cancel()`. Berechtigung: `user_has_access()`.
- `ajax_get_billing_status()` — `ChunkProcessor::get_status()`. Berechtigung: `user_has_access()`.

- [ ] **Step 3: Member + Results Handler**

- `ajax_get_members()` — `MemberList::get_members()`. Params: year, filter_type, filter_status. Berechtigung: `user_has_access()`.
- `ajax_get_results()` — Billing-History aus `dgptm_fin_billing_history` laden. Berechtigung: `user_can_view()`.

- [ ] **Step 4: Payments + Mandates Handler**

- `ajax_process_payments()` — `BillingEngine::process_gocardless_payments()`. Param: dry_run. Berechtigung: `user_has_access()`. Chunk-basiert via ChunkProcessor.
- `ajax_sync_mandates()` — `BillingEngine::sync_gocardless_mandates()`. Param: dry_run. Berechtigung: `user_has_access()`. Chunk-basiert via ChunkProcessor.

- [ ] **Step 5: Invoice Handler**

- `ajax_get_invoices()` — `InvoiceManager::get_open_invoices()`. Berechtigung: `user_has_access()`.
- `ajax_invoice_action()` — Dispatcher: Params `action_type` + `invoice_id`. Routes zu `collect_payment()`, `apply_credit()`, `handle_chargeback()`, `send_without_credit()`. Berechtigung: `user_has_access()`.

- [ ] **Step 6: Report Handler**

- `ajax_get_report()` — `ReportBuilder::get_report()` für Finanzberichte oder Member-Stats für mitgliederzahl. Berechtigung: `user_can_view()`. Access-Logging. Referenz: finanzbericht.php Zeilen 178-207.

- [ ] **Step 7: Treasurer + Config Handler**

- `ajax_treasurer_crud()` — `Treasurer::get_entries()` (GET) oder `Treasurer::mark_transferred()` (POST). Berechtigung: `user_has_access()`.
- `ajax_save_config()` — JSON validieren, in `dgptm_fin_config` speichern. Berechtigung: `user_is_admin()`.
- `ajax_upload_credentials()` — Books-Credentials speichern. Berechtigung: `user_is_admin()`.
- `ajax_import_historical()` — `HistoricalData::import()`. Berechtigung: `user_is_admin()`.

- [ ] **Step 8: Nightly Cron**

- `cron_refresh_all_caches()` — Member-Stats-Transient löschen + neu laden. Referenz: finanzbericht.php Zeilen 389-396.

- [ ] **Step 9: Commit**

```
feat(finanzen): Alle 18 AJAX-Handler implementiert
```

---

## Task 10: Frontend — Dashboard-Template + CSS

**Files:**
- Create: `modules/business/finanzen/templates/dashboard.php`
- Create: `modules/business/finanzen/assets/css/finanzen.css`

- [ ] **Step 1: dashboard.php erstellen**

9 Tabs mit rollenbasierter Sichtbarkeit. PHP rendert nur die erlaubten Tabs. Jedes Panel hat eine ID `panel-{tab}` und wird via JS lazy-loaded.

Tab-Panels:
1. `dashboard` — KPI-Boxen + Letzte Abrechnung (ähnlich finanzbericht dashboard.php)
2. `billing` — Year-Select + Contact-IDs + Dry/Live Buttons + Progress-Bar + Ergebnisse (ähnlich mitgliedsbeitrag dashboard.php Zeilen 18-47)
3. `members` — Filter-Controls + Tabelle + "Zur Abrechnung"-Button
4. `results` — Ergebnis-Historie-Tabelle mit Expandable Details
5. `payments` — Process Payments + Sync Mandates Buttons + Ergebnisse
6. `invoices` — Offene-Rechnungen-Tabelle mit Aktions-Buttons pro Zeile
7. `reports` — Report-Tabs + Year-Select + Reload (aus finanzbericht dashboard.php)
8. `treasurer` — Erstattungsliste + Mark-Transferred Buttons
9. `settings` — Config-Viewer (maskiert) + Membership-Types

Referenzen für Markup-Patterns:
- finanzbericht/templates/dashboard.php (40 Zeilen) — Tab-Navigation, KPIs, Report-Panels
- mitgliedsbeitrag/templates/dashboard.php (56 Zeilen) — Controls, Buttons, Results

- [ ] **Step 2: finanzen.css erstellen**

Konsolidiert aus:
- `finanzbericht/assets/css/finanzbericht.css` (225 Zeilen) — Tab-Styles, KPI-Boxen, Tabellen
- `mitgliedsbeitrag/assets/css/mitgliedsbeitrag.css` (157 Zeilen) — Controls, Buttons, Log

Prefix: `dgptm-fin-`. Farben:
- Primary: `#1a3a5c` (dark blue)
- Success: `#d4edda` / `#155724`
- Error: `#f8d7da` / `#721c24`
- Blue: `#cce5ff` / `#004085`

Neue Klassen:
- `.dgptm-fin-progress` — Fortschrittsbalken für Chunk-Processing
- `.dgptm-fin-invoice-actions` — Aktions-Buttons in Rechnungstabelle
- `.dgptm-fin-filter-bar` — Filter-Controls für Mitgliederliste

- [ ] **Step 3: Commit**

```
feat(finanzen): Dashboard-Template mit 9 Tabs + konsolidiertes CSS
```

---

## Task 11: Frontend — JavaScript

**Files:**
- Create: `modules/business/finanzen/assets/js/finanzen.js`

Konsolidiert aus `finanzbericht.js` (270 Zeilen) + `mitgliedsbeitrag.js` (218 Zeilen) + neue Tab-Controller.

- [ ] **Step 1: Core-Modul + Tab-Switching**

```javascript
const DgptmFin = {
    state: { activeTab: 'dashboard', billingSession: null },
    config: {}, // Wird via wp_localize_script gesetzt

    init() { /* Tab-Click Handler, ersten Tab laden */ },
    switchTab(name) { /* Panel wechseln, load() aufrufen */ },
    ajax(action, data) { /* $.post Wrapper mit Nonce */ },
    feur(amount) { /* EUR-Formatierung, de-DE locale */ },
    kpi(label, value, color) { /* KPI-Box HTML */ },
    esc(str) { /* HTML-Escaping */ },
    renderTable(headers, rows) { /* Generische Tabelle */ },
};
```

- [ ] **Step 2: Dashboard-Tab Controller**

`DgptmFin.tabs.dashboard.load()` — AJAX `dgptm_fin_get_dashboard`. Rendert KPIs (Aktive Mitglieder, Abgerechnet, Ausstehend, Offene Rechnungen) + letzte Billing-Zusammenfassung. Referenz: mitgliedsbeitrag.js `renderStats()` (Zeilen 40-86).

- [ ] **Step 3: Billing-Tab Controller (Chunk-Processing)**

```javascript
DgptmFin.tabs.billing = {
    startBilling(dryRun) { /* AJAX start_billing → processNextChunk Loop */ },
    processNextChunk() { /* AJAX process_chunk → Progress-Bar Update → Rekursion bis done */ },
    finalize() { /* AJAX finalize_billing → Ergebnisse rendern */ },
    cancel() { /* AJAX cancel_billing */ },
    renderProgress(processed, total) { /* Progress-Bar aktualisieren */ },
    renderResults(data) { /* Summary KPIs + Detail-Tabelle */ },
};
```

Referenz: mitgliedsbeitrag.js `runBilling()` (Zeilen 121-143) + `showResults()` (Zeilen 158-200).

- [ ] **Step 4: Members-Tab Controller**

`DgptmFin.tabs.members` — AJAX `dgptm_fin_get_members`. Filter-Controls (Typ/Status/Billing). Tabelle mit Checkbox-Selection. "Zur Abrechnung"-Button übergibt IDs an Billing-Tab.

Referenz: Python `dashboard/routes/members.py` (140 Zeilen).

- [ ] **Step 5: Results-Tab Controller**

`DgptmFin.tabs.results` — AJAX `dgptm_fin_get_results`. Ergebnis-Historie-Tabelle. Klick auf Zeile expandiert Details (Member-Liste mit Status/Variante).

Referenz: mitgliedsbeitrag.js `showResults()` (Zeilen 158-200).

- [ ] **Step 6: Payments-Tab Controller**

`DgptmFin.tabs.payments` — Buttons: Process Payments (Dry), Sync Mandates (Dry). Chunk-basiert wie Billing. Ergebnis-Anzeige.

Referenz: mitgliedsbeitrag.js Button-Handler (Zeilen 101-118).

- [ ] **Step 7: Invoices-Tab Controller**

`DgptmFin.tabs.invoices` — AJAX `dgptm_fin_get_invoices`. Tabelle mit Aktions-Buttons pro Zeile (Einziehen, Kredit, Chargeback, Senden). Jede Aktion ruft `dgptm_fin_invoice_action` mit passendem `action_type` auf.

Referenz: Python `dashboard/routes/invoices.py` (595 Zeilen).

- [ ] **Step 8: Reports-Tab Controller**

`DgptmFin.tabs.reports` — Report-Tabs (JT/SKK/Zeitschrift/Mitgliederzahlen) + Year-Select. AJAX `dgptm_fin_get_report`. Rendert KPIs + Kategorie-Tabellen.

1:1 von finanzbericht.js (270 Zeilen): `loadReport()`, `renderReport()`, `renderMemberStats()`, `categoryTable()`, `itemsTable()`.

- [ ] **Step 9: Treasurer + Settings Tab Controller**

`DgptmFin.tabs.treasurer` — AJAX `dgptm_fin_treasurer_crud`. Erstattungsliste + "Überwiesen"-Buttons.
`DgptmFin.tabs.settings` — Config-Anzeige (maskiert). Membership-Types-Tabelle.

- [ ] **Step 10: Commit**

```
feat(finanzen): JavaScript mit 9 Tab-Controllern und Chunk-Processing
```

---

## Task 12: Admin-Template

**Files:**
- Create: `modules/business/finanzen/templates/admin.php`

Konsolidiert aus `finanzbericht/templates/admin.php` (103 Zeilen) + `mitgliedsbeitrag/templates/admin.php` (179 Zeilen).

- [ ] **Step 1: admin.php erstellen**

Sektionen:
1. **Config-Import** — JSON-Upload oder Textarea (aus mitgliedsbeitrag Zeilen 100-122). POST-Handler mit Validierung.
2. **Zoho Books Credentials** — Separates JSON-Upload (aus finanzbericht Zeilen 5-24). Status-Anzeige.
3. **Aktive Konfiguration** — Tabelle mit maskierten Werten (aus mitgliedsbeitrag Zeilen 125-145).
4. **Billing-History Import** — JSON-Dateien hochladen (aus mitgliedsbeitrag Zeilen 43-95). Historie-Tabelle.
5. **Historische Finanzdaten** — JSON-Import (aus finanzbericht AJAX-Handler).
6. **Zugriffs-Log** — Letzte 50 Einträge (aus finanzbericht Zeilen 37-74).

Nonce: `dgptm_fin_nonce` (gleiche Nonce wie Frontend — eine Nonce für das gesamte Modul).

- [ ] **Step 2: render_shortcode() in finanzen.php implementieren**

Asset-Enqueuing: CSS + JS mit Version-Hash. **Wichtig:** Inline-Asset-Loading für AJAX-Kontexte portieren (aus mitgliedsbeitrag.php Zeilen 116-148 und finanzbericht.php Zeilen 160-167, da `wp_enqueue_style/script` in AJAX nicht funktioniert).

`wp_localize_script` mit:
```php
[
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce(self::NONCE),
    'role' => $this->get_user_role($user_id),
    'access' => $this->get_tab_access($user_id),
    'tabs' => $this->get_visible_tabs($user_id),
]
```

- [ ] **Step 3: Commit**

```
feat(finanzen): Admin-Template mit Config/Credentials/History
```

---

## Task 13: Migration + Deprecated-Module

**Files:**
- Modify: `modules/business/finanzen/finanzen.php`
- Modify: `modules/business/finanzbericht/finanzbericht.php`
- Modify: `modules/business/finanzbericht/module.json`
- Modify: `modules/business/mitgliedsbeitrag/mitgliedsbeitrag.php`
- Modify: `modules/business/mitgliedsbeitrag/module.json`

- [ ] **Step 1: Migrationslogik in finanzen.php**

Methode `maybe_migrate()`, aufgerufen im Constructor. Prüft `get_option('dgptm_fin_migrated')`.

Migration:
```php
// 1. Config
$mb_config = get_option('dgptm_mb_config');
$fb_creds = get_option('dgptm_finanzbericht_credentials');
if ($mb_config) {
    $config = json_decode($mb_config, true);
    if ($fb_creds) {
        $creds = json_decode($fb_creds, true);
        // Merge Books-Credentials in Config
    }
    update_option(self::OPT_CONFIG, json_encode($config));
}

// 2. Billing History
// Billing History (existiert seit Commit e0508ba, kann aber null sein bei frischen Installationen)
$history = get_option('dgptm_mb_billing_history');
if ($history) update_option(self::OPT_HISTORY, $history);

// 3. Last Results
$results = get_option('dgptm_mb_last_results');
if ($results) update_option(self::OPT_RESULTS, $results);

// 4. Imported Data
$imported = get_option('dgptm_fb_imported_data');
if ($imported) update_option('dgptm_fin_imported_data', $imported);

// 5. Cron Migration
wp_unschedule_event(wp_next_scheduled('dgptm_fb_nightly_refresh'), 'dgptm_fb_nightly_refresh');

// 6. Flag
update_option('dgptm_fin_migrated', true);
```

- [ ] **Step 2: finanzbericht als deprecated markieren**

`module.json`: `"deprecated": true` hinzufügen.

`finanzbericht.php`: Shortcode-Handler ersetzen:
```php
public function render_shortcode($atts) {
    // Redirect zu neuem Modul
    return do_shortcode('[dgptm_finanzen]');
}
```

Admin-Seite: Banner "Dieses Modul wurde durch 'Finanzen' ersetzt" anzeigen.

- [ ] **Step 3: mitgliedsbeitrag als deprecated markieren**

Gleiche Änderungen wie finanzbericht: `module.json` deprecated, Shortcode-Redirect, Admin-Banner.

- [ ] **Step 4: Commit**

```
feat(finanzen): Automatische Migration + Deprecated-Flags fuer alte Module
```

---

## Task 14: Integration + Smoke-Test

**Files:**
- Modify: `modules/business/finanzen/finanzen.php` (require_once alle includes)

- [ ] **Step 1: Alle includes einbinden**

In `finanzen.php` alle 12 Klassen via `require_once` laden. Reihenfolge:
1. class-config.php
2. class-zoho-crm.php
3. class-zoho-books.php
4. class-gocardless.php
5. class-chunk-processor.php
6. class-billing-engine.php
7. class-invoice-manager.php
8. class-member-list.php
9. class-treasurer.php
10. class-report-builder.php
11. class-historical-data.php
12. class-access-logger.php

- [ ] **Step 2: Smoke-Test Checkliste**

Manuell im WordPress-Admin prüfen:
- [ ] Modul in DGPTM Suite sichtbar und aktivierbar
- [ ] Shortcode `[dgptm_finanzen]` rendert Dashboard mit Tabs
- [ ] Tab-Switching funktioniert (alle 9 Tabs)
- [ ] Dashboard-Tab lädt KPIs (AJAX)
- [ ] Finanzberichte-Tab zeigt historische Daten
- [ ] Alte Shortcodes `[dgptm_finanzbericht]` und `[dgptm_mitgliedsbeitrag]` leiten um
- [ ] Admin-Seite erreichbar
- [ ] Keine PHP-Fehler in debug.log
- [ ] Keine JavaScript-Fehler in Browser-Konsole
- [ ] Berechtigung: Nicht-Schatzmeister sehen nur 3 Tabs

- [ ] **Step 3: Commit**

```
feat(finanzen): Integration aller Klassen + Smoke-Test-Checkliste
```

---

## Zusammenfassung

| Task | Dateien | Geschätzter Umfang |
|------|---------|-------------------|
| 1. Skeleton + Config | 3 neue | ~300 Zeilen PHP |
| 2. Zoho CRM Client | 1 neue | ~400 Zeilen PHP |
| 3. Zoho Books Client | 1 neue | ~600 Zeilen PHP |
| 4. GoCardless Client | 1 neue | ~170 Zeilen PHP |
| 5. Chunk-Processor | 1 neue | ~200 Zeilen PHP |
| 6. Billing-Engine | 1 neue | ~700 Zeilen PHP |
| 7. Invoice-Manager | 1 neue | ~400 Zeilen PHP |
| 8. Member-List + Treasurer + Bestand | 5 neue | ~700 Zeilen PHP |
| 9. AJAX-Handler | 1 modifiziert | ~500 Zeilen PHP |
| 10. Dashboard + CSS | 2 neue | ~400 Zeilen HTML/CSS |
| 11. JavaScript | 1 neue | ~800 Zeilen JS |
| 12. Admin-Template | 1 modifiziert | ~200 Zeilen PHP |
| 13. Migration + Deprecated | 5 modifiziert | ~100 Zeilen PHP |
| 14. Integration + Test | 1 modifiziert | Minimal |
| **Gesamt** | **18 neue, 8 modifiziert** | **~5.000 Zeilen** |
