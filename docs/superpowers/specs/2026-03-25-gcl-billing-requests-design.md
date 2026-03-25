# GoCardless Billing Requests — Bankdaten ändern via Hosted Flow

**Datum:** 2026-03-25
**Status:** Genehmigt
**Modul:** payment/gocardless

## Zusammenfassung

Neuer Shortcode `[gcl_formidable_new]` ersetzt den Formidable-Form-basierten Mandats-Flow durch die GoCardless Billing Requests API. Kein Formular — nur Statusanzeige + Aktions-Buttons. Dashboard-kompatibel (Event-Delegation, AJAX-safe).

## Architektur

- **Neue Datei:** `modules/payment/gocardless/gocardless-billing-requests.php`
- **Bestehender Code bleibt unverändert** (`gocardless-direct-debit-manager.php`)
- **Settings:** Eigene Option `dgptm_gcl_br_settings` (API Token wird aus bestehender `gocardless_settings` gelesen, neue Felder separat)
- **Kein Formidable Form** — eigenes HTML, AJAX-Handler, Event-Delegation
- **Laden:** `require_once` am Ende von `gocardless-direct-debit-manager.php`

## Shortcode-Anzeige

### Mit aktivem Mandat

```
SEPA-Lastschriftmandat
Status: ● Aktiv
Konto:  ••••27
Mandat: MD01K7KEF3Q6ZD

[Bankverbindung ändern]  [Mandat kündigen]
```

### Ohne Mandat

```
SEPA-Lastschriftmandat
Kein aktives Mandat vorhanden.

[Neues Mandat einrichten]
```

**IBAN-Anzeige:** GoCardless API liefert nur `account_number_ending` (letzte 2-4 Ziffern). Anzeige als `••••{ending}`. Keine vollständige IBAN verfügbar.

## Sicherheit: Alle IDs serverseitig ermittelt

**Kritische Regel:** Mandate-IDs, Bank-Account-IDs und Customer-IDs werden NIEMALS aus `$_POST` akzeptiert. Alle IDs werden serverseitig abgeleitet:

1. `$uid = get_current_user_id()`
2. `$gcl_customer_id` aus `do_shortcode('[zoho_api_data field="GoCardlessID"]')` ODER `get_user_meta($uid, 'goCardlessPayment', true)` — immer scoped auf den eingeloggten User
3. `$mandate_id` via `GET /mandates?customer={gcl_customer_id}&status=active` — aus API-Antwort
4. `$bank_account_id` via `mandate.links.customer_bank_account` — aus Mandat-Objekt

So kann kein User fremde Mandate/Konten manipulieren.

## Datenfluss

### Status laden (AJAX: `dgptm_gcl_load_status`)

1. `$gcl_customer_id` serverseitig ermitteln (siehe Sicherheit oben)
2. Falls leer → Anzeige: "Kein GoCardless-Kundenkonto vorhanden."
3. `GET /mandates?customer={gcl_customer_id}&status=active` + `status=pending_submission`
4. Mehrere Mandate möglich → erstes aktives nehmen (sortiert nach `created_at` DESC)
5. Wenn Mandat gefunden: `mandate.links.customer_bank_account` → `GET /customer_bank_accounts/{id}`
6. `account_number_ending` für Anzeige, `mandate.id` und `mandate.links.customer_bank_account` für Aktionen
7. HTML mit Status, Kontoendung, Mandat-ID rendern

### Bankverbindung ändern (AJAX: `dgptm_gcl_change_bank`)

```
1. IDs serverseitig ermitteln (Customer → Mandat → Bank Account)
2. Guard: Nur disable wenn Konto noch enabled
   GET /customer_bank_accounts/{id} → prüfe enabled-Flag
3. POST /mandates/{id}/actions/cancel          → Altes Mandat kündigen
   (Fehler bei bereits cancelled → ignorieren, weiter)
4. POST /customer_bank_accounts/{id}/actions/disable  → Altes Konto deaktivieren
   (Nur wenn enabled, sonst überspringen)
5. POST /billing_requests
   {
     "billing_requests": {
       "mandate_request": { "scheme": "sepa_core" },
       "links": { "customer": "{gcl_customer_id}" }
     }
   }
6. POST /billing_request_flows
   {
     "billing_request_flows": {
       "redirect_uri": "{configured_redirect_url}",
       "exit_uri": "{configured_exit_url}",
       "lock_customer_details": true,
       "links": { "billing_request": "BRQ..." }
     }
   }
7. Response: authorisation_url → JSON zurück an JS → window.location redirect
```

**Fehler-Resilience:** Wenn Schritt 5 (Billing Request) fehlschlägt nachdem Mandat gekündigt wurde, wird trotzdem die `authorisation_url` nicht zurückgegeben. Der User sieht eine Fehlermeldung und kann "Neues Mandat einrichten" klicken (der Flow ohne Cancel-Schritte). Das alte Mandat ist dann cancelled, aber der User kann jederzeit ein neues einrichten.

### Neues Mandat einrichten (AJAX: `dgptm_gcl_new_mandate`)

Wie `dgptm_gcl_change_bank`, aber Schritte 1-4 entfallen:
- `$gcl_customer_id` serverseitig ermitteln
- Falls Customer-ID leer: Fehler "Kein GoCardless-Kundenkonto. Bitte Geschäftsstelle kontaktieren."
- Direkt zu Schritt 5-7 (Billing Request + Flow + Redirect)

### Mandat kündigen (AJAX: `dgptm_gcl_cancel_mandate`)

```
1. IDs serverseitig ermitteln
2. POST /mandates/{id}/actions/cancel
   (Fehler bei bereits cancelled → ignorieren)
3. POST /customer_bank_accounts/{id}/actions/disable
   (Nur wenn enabled)
4. Status-HTML per AJAX zurückgeben (zeigt "Kein aktives Mandat")
```

### CRM-Sync nach erfolgreichem Flow

**Trigger:** Der `dgptm_gcl_load_status` AJAX-Handler prüft bei jedem Aufruf, ob die aktuelle Mandat-ID vom gespeicherten `MandatID`-Wert im CRM abweicht. Wenn ja → CRM-Update.

```
1. Status laden → aktives Mandat gefunden → mandate.id
2. Zoho CRM: aktuelles MandatID-Feld lesen (aus user_meta 'zoho_id')
3. Wenn mandate.id ≠ gespeichertes MandatID:
   → PUT https://www.zohoapis.eu/crm/v7/Contacts/{zoho_id}
     Body: {"data": [{"MandatID": "{mandate.id}"}]}
   → Authentifiziert via DGPTM_Zoho_Plugin::get_oauth_token()
4. zoho_id kommt aus: get_user_meta(get_current_user_id(), 'zoho_id', true)
```

## AJAX-Handler

| Action | Beschreibung | Auth |
|--------|-------------|------|
| `dgptm_gcl_load_status` | Mandat-Status + IBAN laden + CRM-Sync | `is_user_logged_in()` |
| `dgptm_gcl_change_bank` | Cancel → Disable → Billing Request → URL | `is_user_logged_in()` |
| `dgptm_gcl_new_mandate` | Billing Request für neues Mandat | `is_user_logged_in()` |
| `dgptm_gcl_cancel_mandate` | Mandat kündigen + Konto deaktivieren | `is_user_logged_in()` |

**Nonce:** Ein einzelner Nonce `dgptm_gcl_nonce` (Action-String) für alle vier Handler. Erstellt bei Shortcode-Render und via `wp_localize_script` an JS übergeben. Geprüft mit `check_ajax_referer('dgptm_gcl_nonce')`.

## Settings

**Eigene Option:** `dgptm_gcl_br_settings`

| Key | Typ | Default |
|-----|-----|---------|
| `redirect_url` | URL | `https://perfusiologie.de/mitgliedschaft/interner-bereich/#tab-profil` |
| `exit_url` | URL | `https://perfusiologie.de/mitgliedschaft/interner-bereich/` |

**API Token:** Gelesen aus bestehender Option `gocardless_settings` → Sub-Key `gocardless_api_token`.

**Admin-Seite:** Eigene Settings-Sektion in der neuen Datei (`add_settings_section` unter der bestehenden GoCardless-Settings-Seite, oder eigene Unterseite). Bestehende Datei bleibt unverändert.

**Sanitization:** `esc_url_raw()` bei Speicherung, `esc_url()` bei Ausgabe.

## Frontend Assets

```php
wp_enqueue_style('dgptm-gcl-br', ...);
wp_enqueue_script('dgptm-gcl-br', ..., ['jquery'], ..., true);
wp_localize_script('dgptm-gcl-br', 'dgptmGclBr', [
    'ajaxUrl' => admin_url('admin-ajax.php'),
    'nonce'   => wp_create_nonce('dgptm_gcl_nonce'),
]);
```

Enqueue-Bedingung: `has_shortcode($content, 'gcl_formidable_new') || has_shortcode($content, 'dgptm_dashboard')`.

## Dashboard-Kompatibilität

- Alle Event-Handler via `$(document).on('click', '#id', ...)` (Event-Delegation)
- Keine globalen DOM-Referenzen — frische `$('#id')` Lookups in Handlern
- `$(document).on('dgptm_tab_loaded', initGclStatus)` für Re-Init nach AJAX-Tab-Laden
- `initGclStatus()`: Prüft ob `#dgptm-gcl-status` im DOM, lädt Status via AJAX
- Bestätigungs-Dialoge via `confirm()` (einfach, Dashboard-sicher)

## GoCardless API Helper

Eigene `dgptm_gcl_br_api($method, $endpoint, $body = null)` Funktion:
- Base URL: `https://api.gocardless.com/`
- Headers: `Authorization: Bearer {token}`, `GoCardless-Version: 2015-07-06`, `Content-Type: application/json`
- Timeout: 30s
- Prüft `is_wp_error()` UND `wp_remote_retrieve_response_code()` (4xx/5xx)
- Bei GoCardless-Fehler: parsed `error.message` aus Response-Body
- Returns: parsed JSON Array oder WP_Error

## Fehlerbehandlung

- **API Token fehlt** → Shortcode zeigt "GoCardless nicht konfiguriert" statt leeres Widget
- **GoCardlessID leer** → "Kein GoCardless-Kundenkonto vorhanden. Bitte Geschäftsstelle kontaktieren."
- **API-Fehler (4xx/5xx)** → User-freundliche Meldung aus `error.message`, im Status-Bereich angezeigt
- **Cancel auf bereits cancelled Mandat** → ignorieren (idempotent), weiter im Flow
- **Disable auf bereits disabled Konto** → überspringen (Guard wie in bestehendem Code v1.20)
- **Billing Request fehlschlägt nach Cancel** → Fehlermeldung, User kann "Neues Mandat einrichten"
- **Timeout** → "Zeitüberschreitung bei GoCardless. Bitte erneut versuchen."

## API-Endpunkte (GoCardless)

| Endpunkt | Methode | Zweck |
|----------|---------|-------|
| `/mandates?customer={id}&status=...` | GET | Mandate laden |
| `/mandates/{id}/actions/cancel` | POST | Mandat kündigen |
| `/customer_bank_accounts/{id}` | GET | Bankkonto-Details (account_number_ending) |
| `/customer_bank_accounts/{id}/actions/disable` | POST | Bankkonto deaktivieren |
| `/billing_requests` | POST | Billing Request erstellen |
| `/billing_request_flows` | POST | Hosted Flow erstellen (→ authorisation_url) |

**Auth:** `Authorization: Bearer {token}`, `GoCardless-Version: 2015-07-06`
