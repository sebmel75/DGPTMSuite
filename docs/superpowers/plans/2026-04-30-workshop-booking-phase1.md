# Workshop-Booking Phase 1 — Implementierungs-Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Phase 1 des `workshop-booking`-Moduls (DGPTMSuite v0.3.0) liefert einen lauffähigen Buchungsfluss inklusive AGB-konformer Status-Sync-Architektur (Sync_Coordinator, State-Machine, Drift-Reconciliation).

**Architecture:** WordPress-Plugin-Modul mit Singleton-Pattern. Sync_Coordinator ist Single Entry Point für alle Schreibzugriffe auf `Veranstal_X_Contacts`. `Quelle ≠ 'Modul'` (Backstage-Records) wird konsequent geskippt. Stripe-Webhook und Reconciliation-Cron (alle 15 min) sind die zwei Update-Pfade.

**Tech Stack:** PHP 7.4+, WordPress 5.8+, Zoho CRM API v3 (COQL), Stripe Checkout API, kein Test-Framework — Verifizierung über manuelle Smoke-Tests + DGPTM-Logger + `error_log`.

**Quelle:** Spec `docs/superpowers/specs/2026-04-22-workshop-buchen-entscheidungsvorlage.md`, Abschnitte 4, 4a, 5, 6.

**Scope-Cut:** Phase 1 liefert KEIN Ticket-PDF, KEIN QR, KEINE Books-Rechnungs-Erzeugung, KEINEN Mitgliederbereich-Tab, KEINE Storno-UI, KEINE Reminder-Mails, KEINE Mehrsprachigkeit. Stubs/Hooks dafür werden gesetzt, aber nicht implementiert.

---

## Task 1 — DB-Installer + Module-Bootstrap

**Files:**
- Modify: `modules/business/workshop-booking/dgptm-workshop-booking.php`
- Create: `modules/business/workshop-booking/includes/class-installer.php`
- Modify: `modules/business/workshop-booking/module.json` (Version 0.3.0, Dependencies)

- [ ] **Step 1.1 — module.json aktualisieren**

```json
{
    "id": "workshop-booking",
    "name": "DGPTM - Workshop Buchung",
    "description": "Buchung von Workshops und Webinaren aus Zoho CRM mit Stripe-Zahlung, Status-Sync und AGB-konformer Reconciliation.",
    "version": "0.3.0",
    "author": "Sebastian Melzer",
    "main_file": "dgptm-workshop-booking.php",
    "dependencies": ["crm-abruf"],
    "optional_dependencies": ["mitglieder-dashboard", "vimeo-webinare", "stipendium", "zoho-books-integration"],
    "wp_dependencies": { "plugins": [] },
    "requires_php": "7.4",
    "requires_wp": "5.8",
    "category": "business",
    "icon": "dashicons-tickets-alt",
    "active": false,
    "critical": false,
    "flags": ["development"],
    "comment": {
        "text": "v0.3.0 Phase 1: Buchungs-Flow + Sync-Coordinator. Tickets/QR/Books/Mitgliederbereich folgen in spaeteren Phasen.",
        "timestamp": 1745928000,
        "user_id": 1
    }
}
```

- [ ] **Step 1.2 — `class-installer.php` erstellen**

Verantwortlich: `wp_dgptm_workshop_sync_log`, `wp_dgptm_workshop_drift_alerts`, `wp_dgptm_workshop_pending_bookings` per `dbDelta` anlegen. Wird vom Bootstrap aufgerufen, prüft `dgptm_wsb_db_version`-Option und führt `dbDelta` nur bei Versionsänderung aus.

```php
<?php
/**
 * DB-Installer fuer Workshop-Booking Phase 1.
 *
 * Legt drei Tabellen an: sync_log (append-only Audit), drift_alerts (kuratierter Alert-Stream),
 * pending_bookings (Uebergang zwischen book() und Stripe-Webhook-Eingang).
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Installer {

    const DB_VERSION = '1';
    const OPT_DB_VERSION = 'dgptm_wsb_db_version';

    public static function maybe_install() {
        if (get_option(self::OPT_DB_VERSION) === self::DB_VERSION) {
            return;
        }
        self::install();
        update_option(self::OPT_DB_VERSION, self::DB_VERSION, false);
    }

    private static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$wpdb->prefix}dgptm_workshop_sync_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            veranstal_x_contact_id VARCHAR(40) NOT NULL,
            source VARCHAR(40) NOT NULL,
            intent_blueprint_state VARCHAR(80) NULL,
            intent_payment_status VARCHAR(40) NULL,
            previous_blueprint_state VARCHAR(80) NULL,
            previous_payment_status VARCHAR(40) NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            error_code VARCHAR(40) NULL,
            error_message TEXT NULL,
            payload_json LONGTEXT NULL,
            reason VARCHAR(160) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_contact (veranstal_x_contact_id),
            KEY idx_created (created_at)
        ) $charset;");

        dbDelta("CREATE TABLE {$wpdb->prefix}dgptm_workshop_drift_alerts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            veranstal_x_contact_id VARCHAR(40) NULL,
            code VARCHAR(60) NOT NULL,
            severity ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
            crm_state_snapshot LONGTEXT NULL,
            external_state_snapshot LONGTEXT NULL,
            proposed_action TEXT NULL,
            status ENUM('open','acknowledged','resolved','ignored') NOT NULL DEFAULT 'open',
            acknowledged_by BIGINT UNSIGNED NULL,
            acknowledged_at DATETIME NULL,
            resolved_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_contact (veranstal_x_contact_id),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) $charset;");

        dbDelta("CREATE TABLE {$wpdb->prefix}dgptm_workshop_pending_bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            veranstal_x_contact_id VARCHAR(40) NOT NULL,
            event_id VARCHAR(40) NOT NULL,
            attendees_json LONGTEXT NOT NULL,
            stripe_session_id VARCHAR(255) NULL,
            stripe_session_expires_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_contact (veranstal_x_contact_id),
            KEY idx_session (stripe_session_id),
            KEY idx_expires (stripe_session_expires_at)
        ) $charset;");
    }
}
```

- [ ] **Step 1.3 — Bootstrap erweitern**

`dgptm-workshop-booking.php`: Installer aufrufen, Komponenten in `load_components()` ergänzen. Nutze `register_activation_hook` + `init`-Fallback (für Module-Loader-Aktivierung).

```php
private function load_components() {
    require_once $this->plugin_path . 'includes/class-installer.php';
    DGPTM_WSB_Installer::maybe_install();

    require_once $this->plugin_path . 'includes/class-entscheidungsvorlage.php';
    $this->entscheidungsvorlage = new DGPTM_Workshop_Entscheidungsvorlage(
        $this->plugin_path,
        $this->plugin_url
    );

    // Phase 1 Komponenten — werden in Tasks 2–10 angelegt
    require_once $this->plugin_path . 'includes/class-sync-log-store.php';
    require_once $this->plugin_path . 'includes/class-drift-alert-store.php';
    require_once $this->plugin_path . 'includes/class-state-machine.php';
    require_once $this->plugin_path . 'includes/class-veranstal-x-contacts.php';
    require_once $this->plugin_path . 'includes/class-sync-coordinator.php';
    require_once $this->plugin_path . 'includes/class-event-source.php';
    require_once $this->plugin_path . 'includes/class-contact-lookup.php';
    require_once $this->plugin_path . 'includes/class-pending-bookings-store.php';
    require_once $this->plugin_path . 'includes/class-booking-service.php';
    require_once $this->plugin_path . 'includes/class-stripe-checkout.php';
    require_once $this->plugin_path . 'includes/class-stripe-webhook.php';
    require_once $this->plugin_path . 'includes/class-books-status-reader.php';
    require_once $this->plugin_path . 'includes/class-reconciliation-cron.php';
    require_once $this->plugin_path . 'includes/class-pending-cleanup-cron.php';
    require_once $this->plugin_path . 'includes/class-ics-builder.php';
    require_once $this->plugin_path . 'includes/class-mail-sender.php';
    require_once $this->plugin_path . 'includes/class-shortcodes.php';

    DGPTM_WSB_Stripe_Webhook::get_instance();
    DGPTM_WSB_Reconciliation_Cron::get_instance();
    DGPTM_WSB_Pending_Cleanup_Cron::get_instance();
    DGPTM_WSB_Shortcodes::get_instance($this->plugin_path, $this->plugin_url);
}
```

- [ ] **Step 1.4 — Verifikation**

```bash
# PHP-Syntax-Check für alle neuen Dateien
/d/php/php -l "modules/business/workshop-booking/includes/class-installer.php"
/d/php/php -l "modules/business/workshop-booking/dgptm-workshop-booking.php"
```

In WordPress: Modul aktivieren → drei Tabellen prüfen mit phpMyAdmin oder `SHOW TABLES LIKE 'wp_dgptm_workshop_%';`.

---

## Task 2 — Sync-Foundation: Value Objects + Stores

**Files:**
- Create: `modules/business/workshop-booking/includes/class-sync-intent.php`
- Create: `modules/business/workshop-booking/includes/class-sync-result.php`
- Create: `modules/business/workshop-booking/includes/class-sync-log-store.php`
- Create: `modules/business/workshop-booking/includes/class-drift-alert-store.php`
- Create: `modules/business/workshop-booking/includes/class-state-machine.php`

- [ ] **Step 2.1 — Sync_Intent (Value Object)**

```php
<?php
/**
 * Sync_Intent — was soll am Veranstal_X_Contacts-Eintrag passieren?
 * Immutable Value Object.
 */
if (!defined('ABSPATH')) exit;

final class DGPTM_WSB_Sync_Intent {

    public $veranstal_x_contact_id;
    public $target_blueprint_state;
    public $target_payment_status;
    public $source;
    public $payload;
    public $reason;

    const SOURCE_STRIPE_WEBHOOK  = 'stripe_webhook';
    const SOURCE_RECONCILIATION  = 'reconciliation';
    const SOURCE_MANUAL          = 'manual';
    const SOURCE_BOOKING_INIT    = 'booking_init';

    public function __construct($contact_id, $blueprint, $payment, $source, $payload, $reason) {
        $this->veranstal_x_contact_id = (string) $contact_id;
        $this->target_blueprint_state = $blueprint;
        $this->target_payment_status  = $payment;
        $this->source                 = $source;
        $this->payload                = is_array($payload) ? $payload : [];
        $this->reason                 = (string) $reason;
    }

    public function to_array() {
        return [
            'veranstal_x_contact_id' => $this->veranstal_x_contact_id,
            'target_blueprint_state' => $this->target_blueprint_state,
            'target_payment_status'  => $this->target_payment_status,
            'source'                 => $this->source,
            'payload'                => $this->payload,
            'reason'                 => $this->reason,
        ];
    }
}
```

- [ ] **Step 2.2 — Sync_Result (Value Object)**

```php
<?php
if (!defined('ABSPATH')) exit;

final class DGPTM_WSB_Sync_Result {

    public $success;
    public $error_code;
    public $log_id;
    public $alert_id;

    const ERR_TRANSITION_FORBIDDEN  = 'transition_forbidden';
    const ERR_ZOHO_API_ERROR        = 'zoho_api_error';
    const ERR_DRIFT_DETECTED        = 'drift_detected';
    const ERR_SOURCE_SKIPPED        = 'source_skipped';
    const ERR_CONTACT_NOT_FOUND     = 'contact_not_found';
    const ERR_INVALID_INTENT        = 'invalid_intent';

    private function __construct($success, $error_code, $log_id, $alert_id) {
        $this->success    = (bool) $success;
        $this->error_code = $error_code;
        $this->log_id     = (string) $log_id;
        $this->alert_id   = $alert_id;
    }

    public static function ok($log_id) {
        return new self(true, null, $log_id, null);
    }

    public static function fail($error_code, $log_id, $alert_id = null) {
        return new self(false, $error_code, $log_id, $alert_id);
    }

    public static function skipped($log_id) {
        return new self(true, self::ERR_SOURCE_SKIPPED, $log_id, null);
    }
}
```

- [ ] **Step 2.3 — Sync_Log_Store (append-only)**

```php
<?php
/**
 * Append-only Audit-Log fuer alle Sync-Operationen.
 * AGB §6 Abs. 3 (Schriftform): jede Aenderung am Buchungsstatus ist hier nachvollziehbar.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Sync_Log_Store {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dgptm_workshop_sync_log';
    }

    /**
     * Schreibt einen Audit-Eintrag und liefert die neue ID zurueck.
     *
     * @param DGPTM_WSB_Sync_Intent $intent
     * @param array  $previous   ['blueprint' => ..., 'payment' => ...]
     * @param bool   $success
     * @param string|null $error_code
     * @param string|null $error_message
     * @return string log_id
     */
    public static function record($intent, array $previous, $success, $error_code = null, $error_message = null) {
        global $wpdb;
        $wpdb->insert(self::table(), [
            'veranstal_x_contact_id'   => $intent->veranstal_x_contact_id,
            'source'                   => $intent->source,
            'intent_blueprint_state'   => $intent->target_blueprint_state,
            'intent_payment_status'    => $intent->target_payment_status,
            'previous_blueprint_state' => isset($previous['blueprint']) ? $previous['blueprint'] : null,
            'previous_payment_status'  => isset($previous['payment'])   ? $previous['payment']   : null,
            'success'                  => $success ? 1 : 0,
            'error_code'               => $error_code,
            'error_message'            => $error_message,
            'payload_json'             => wp_json_encode($intent->payload),
            'reason'                   => $intent->reason,
            'created_at'               => current_time('mysql'),
        ]);
        return (string) $wpdb->insert_id;
    }
}
```

- [ ] **Step 2.4 — Drift_Alert_Store**

```php
<?php
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Drift_Alert_Store {

    const SEVERITY_INFO     = 'info';
    const SEVERITY_WARNING  = 'warning';
    const SEVERITY_CRITICAL = 'critical';

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dgptm_workshop_drift_alerts';
    }

    public static function open_alert($contact_id, $code, $severity, array $crm_snapshot, array $external_snapshot, $proposed_action) {
        global $wpdb;

        // De-duplizieren: gibt es bereits einen offenen Alert mit gleichem Code fuer denselben Contact?
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . self::table() . " WHERE veranstal_x_contact_id = %s AND code = %s AND status = 'open' LIMIT 1",
            $contact_id, $code
        ));
        if ($existing) {
            return (string) $existing;
        }

        $wpdb->insert(self::table(), [
            'veranstal_x_contact_id'  => $contact_id,
            'code'                    => $code,
            'severity'                => $severity,
            'crm_state_snapshot'      => wp_json_encode($crm_snapshot),
            'external_state_snapshot' => wp_json_encode($external_snapshot),
            'proposed_action'         => $proposed_action,
            'status'                  => 'open',
            'created_at'              => current_time('mysql'),
        ]);
        return (string) $wpdb->insert_id;
    }

    public static function count_open() {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::table() . " WHERE status = 'open'");
    }
}
```

- [ ] **Step 2.5 — State_Machine**

```php
<?php
/**
 * Erlaubte Blueprint-Uebergaenge zwischen den 8 Anmelde-Status.
 * Spec §4a.3.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_State_Machine {

    const S_ZAHLUNG_AUSSTEHEND = 'Zahlung ausstehend';
    const S_ANGEMELDET         = 'Angemeldet';
    const S_WARTELISTE         = 'Warteliste';
    const S_NACHRUECKER        = 'Nachrücker:in – Zahlung ausstehend';
    const S_ABGEBROCHEN        = 'Abgebrochen';
    const S_STORNIERT          = 'Storniert';
    const S_TEILGENOMMEN       = 'Teilgenommen';
    const S_NICHT_TEILGENOMMEN = 'Nicht teilgenommen';

    /**
     * Erlaubte Uebergaenge. Schluessel = von, Wert = Liste erlaubter Ziele.
     * Manuelle Overrides (source=manual mit manage_options) duerfen jeden Uebergang.
     */
    private static $transitions = [
        // Initial → Zahlung ausstehend ist Sonderfall in apply_intent()
        self::S_ZAHLUNG_AUSSTEHEND => [
            self::S_ANGEMELDET,
            self::S_ABGEBROCHEN,
        ],
        self::S_ANGEMELDET => [
            self::S_STORNIERT,
            self::S_TEILGENOMMEN,
            self::S_NICHT_TEILGENOMMEN,
        ],
        self::S_WARTELISTE => [
            self::S_NACHRUECKER,
            self::S_ABGEBROCHEN,
        ],
        self::S_NACHRUECKER => [
            self::S_ANGEMELDET,
            self::S_ABGEBROCHEN,
        ],
        self::S_STORNIERT          => [],
        self::S_ABGEBROCHEN        => [],
        self::S_TEILGENOMMEN       => [],
        self::S_NICHT_TEILGENOMMEN => [],
    ];

    public static function can_transition($from, $to, $source) {
        if ($from === null || $from === '') {
            // Erstanlage: nur in Zahlung ausstehend, Warteliste oder Angemeldet (z.B. kostenloses Ticket)
            return in_array($to, [self::S_ZAHLUNG_AUSSTEHEND, self::S_WARTELISTE, self::S_ANGEMELDET], true);
        }
        if ($from === $to) {
            return true; // Idempotenz
        }
        if ($source === DGPTM_WSB_Sync_Intent::SOURCE_MANUAL && current_user_can('manage_options')) {
            return true;
        }
        $allowed = isset(self::$transitions[$from]) ? self::$transitions[$from] : [];
        return in_array($to, $allowed, true);
    }

    public static function all_states() {
        return [
            self::S_ZAHLUNG_AUSSTEHEND, self::S_ANGEMELDET, self::S_WARTELISTE,
            self::S_NACHRUECKER, self::S_ABGEBROCHEN, self::S_STORNIERT,
            self::S_TEILGENOMMEN, self::S_NICHT_TEILGENOMMEN,
        ];
    }
}
```

- [ ] **Step 2.6 — Verifikation**

```bash
for f in class-sync-intent class-sync-result class-sync-log-store class-drift-alert-store class-state-machine; do
    /d/php/php -l "modules/business/workshop-booking/includes/$f.php"
done
```

---

## Task 3 — Veranstal_X_Contacts_Writer + Sync_Coordinator

**Files:**
- Create: `modules/business/workshop-booking/includes/class-veranstal-x-contacts.php`
- Create: `modules/business/workshop-booking/includes/class-sync-coordinator.php`

- [ ] **Step 3.1 — `class-veranstal-x-contacts.php` (private API)**

Verantwortlich für die direkten Zoho-API-Aufrufe (lesen + schreiben). Holt Bearer-Token von `crm-abruf` über `DGPTM_Zoho_Plugin::get_instance()->get_oauth_token()`. Niemand außer dem Sync_Coordinator ruft diese Klasse direkt auf — Code-Konvention, keine PHP-Sprach-Garantie.

```php
<?php
/**
 * Privater Schreib-/Lese-Layer fuer Zoho-Modul Veranstal_X_Contacts.
 *
 * INTERN: Wird ausschliesslich vom Sync_Coordinator aufgerufen.
 * Direkter Aufruf ausserhalb gilt als Bug — alle Status-Aenderungen
 * gehen durch Sync_Coordinator (Audit, Drift-Erkennung, State-Machine).
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Veranstal_X_Contacts {

    const ZOHO_API_BASE   = 'https://www.zohoapis.eu/crm/v3';
    const ZOHO_MODULE     = 'Veranstal_X_Contacts';
    const FIELD_BLUEPRINT = 'Anmelde_Status';
    const FIELD_PAYMENT   = 'Zahlungsstatus';
    const FIELD_QUELLE    = 'Quelle';
    const FIELD_LAST_SYNC = 'Last_Sync_At';

    public static function get_token() {
        if (!class_exists('DGPTM_Zoho_Plugin')) {
            return null;
        }
        $plugin = DGPTM_Zoho_Plugin::get_instance();
        if (!method_exists($plugin, 'get_oauth_token')) {
            return null;
        }
        return $plugin->get_oauth_token();
    }

    /**
     * Liest einen Veranstal_X_Contacts-Eintrag (oder null bei 404).
     *
     * @return array|null  ['Anmelde_Status'=>..., 'Zahlungsstatus'=>..., 'Quelle'=>..., 'id'=>...]
     */
    public static function fetch($contact_id) {
        $token = self::get_token();
        if (!$token) return null;

        $url = self::ZOHO_API_BASE . '/' . self::ZOHO_MODULE . '/' . rawurlencode($contact_id);
        $resp = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
            ],
        ]);
        if (is_wp_error($resp)) return null;
        $code = wp_remote_retrieve_response_code($resp);
        if ($code === 404) return null;
        if ($code !== 200) return null;
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (empty($body['data'][0])) return null;
        return $body['data'][0];
    }

    /**
     * Anlegen oder Update eines Veranstal_X_Contacts-Eintrags.
     *
     * @param array $data Felder, die geschrieben werden sollen
     * @param string|null $contact_id  null = neu anlegen
     * @return array  ['success'=>bool, 'id'=>?string, 'error'=>?string]
     */
    public static function upsert(array $data, $contact_id = null) {
        $token = self::get_token();
        if (!$token) {
            return ['success' => false, 'id' => null, 'error' => 'no_oauth_token'];
        }

        $url = self::ZOHO_API_BASE . '/' . self::ZOHO_MODULE;
        $method = 'POST';
        if ($contact_id) {
            $url .= '/' . rawurlencode($contact_id);
            $method = 'PUT';
        }

        $resp = wp_remote_request($url, [
            'method'  => $method,
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['data' => [$data]]),
        ]);

        if (is_wp_error($resp)) {
            return ['success' => false, 'id' => null, 'error' => $resp->get_error_message()];
        }
        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);

        if (!in_array($code, [200, 201], true)) {
            $err = isset($body['data'][0]['code']) ? $body['data'][0]['code'] : ('http_' . $code);
            return ['success' => false, 'id' => null, 'error' => $err];
        }

        $id = $contact_id ?: (isset($body['data'][0]['details']['id']) ? $body['data'][0]['details']['id'] : null);
        return ['success' => true, 'id' => $id, 'error' => null];
    }
}
```

- [ ] **Step 3.2 — `class-sync-coordinator.php` (Single Entry Point)**

```php
<?php
/**
 * Single Entry Point fuer alle Status-Schreibzugriffe auf Veranstal_X_Contacts.
 *
 * Spec Abschnitt 4a.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Sync_Coordinator {

    const QUELLE_MODUL     = 'Modul';
    const QUELLE_BACKSTAGE = 'Backstage';

    /**
     * Anwenden eines Sync_Intent.
     */
    public static function apply_intent(DGPTM_WSB_Sync_Intent $intent) {
        // 1) Aktuellen CRM-Stand lesen
        $current = DGPTM_WSB_Veranstal_X_Contacts::fetch($intent->veranstal_x_contact_id);

        // 2) Sonderfall: Erstanlage (kein Contact-ID, sondern leerer)
        $is_create = (empty($intent->veranstal_x_contact_id) || $current === null);

        // 3) Backstage-Skip
        if ($current !== null) {
            $quelle = isset($current[DGPTM_WSB_Veranstal_X_Contacts::FIELD_QUELLE])
                    ? $current[DGPTM_WSB_Veranstal_X_Contacts::FIELD_QUELLE] : null;
            if ($quelle !== self::QUELLE_MODUL) {
                $log_id = DGPTM_WSB_Sync_Log_Store::record(
                    $intent,
                    self::snapshot_previous($current),
                    true,
                    DGPTM_WSB_Sync_Result::ERR_SOURCE_SKIPPED,
                    'Quelle=' . ($quelle ?: 'leer')
                );
                if ($intent->source === DGPTM_WSB_Sync_Intent::SOURCE_STRIPE_WEBHOOK) {
                    // Edge-Case: Webhook auf Backstage-Record → Drift-Alert
                    DGPTM_WSB_Drift_Alert_Store::open_alert(
                        $intent->veranstal_x_contact_id,
                        'backstage_with_stripe',
                        DGPTM_WSB_Drift_Alert_Store::SEVERITY_CRITICAL,
                        ['quelle' => $quelle, 'current' => $current],
                        ['intent' => $intent->to_array()],
                        'Manuelle Pruefung: warum hat ein Backstage-Record eine Modul-Stripe-Charge?'
                    );
                }
                return DGPTM_WSB_Sync_Result::skipped($log_id);
            }
        }

        // 4) State-Machine pruefen
        $previous = self::snapshot_previous($current);
        if ($intent->target_blueprint_state !== null) {
            $from = $previous['blueprint'];
            $to   = $intent->target_blueprint_state;
            if (!DGPTM_WSB_State_Machine::can_transition($from, $to, $intent->source)) {
                $log_id = DGPTM_WSB_Sync_Log_Store::record(
                    $intent, $previous, false,
                    DGPTM_WSB_Sync_Result::ERR_TRANSITION_FORBIDDEN,
                    "Forbidden: {$from} → {$to} (source={$intent->source})"
                );
                return DGPTM_WSB_Sync_Result::fail(DGPTM_WSB_Sync_Result::ERR_TRANSITION_FORBIDDEN, $log_id);
            }
        }

        // 5) Update-Daten zusammenstellen
        $data = [];
        if ($intent->target_blueprint_state !== null) {
            $data[DGPTM_WSB_Veranstal_X_Contacts::FIELD_BLUEPRINT] = $intent->target_blueprint_state;
        }
        if ($intent->target_payment_status !== null) {
            $data[DGPTM_WSB_Veranstal_X_Contacts::FIELD_PAYMENT] = $intent->target_payment_status;
        }
        if ($is_create) {
            $data[DGPTM_WSB_Veranstal_X_Contacts::FIELD_QUELLE] = self::QUELLE_MODUL;
        }
        $data[DGPTM_WSB_Veranstal_X_Contacts::FIELD_LAST_SYNC] = current_time('mysql');

        // Zusatz-Felder aus Payload (z.B. Stripe_Charge_ID)
        if (!empty($intent->payload['extra_fields']) && is_array($intent->payload['extra_fields'])) {
            foreach ($intent->payload['extra_fields'] as $k => $v) {
                $data[$k] = $v;
            }
        }

        // 6) Bei Erstanlage: TN-Kerndaten setzen (Name, E-Mail, Event-Lookup, Contact-Lookup)
        if ($is_create && !empty($intent->payload['initial_fields']) && is_array($intent->payload['initial_fields'])) {
            foreach ($intent->payload['initial_fields'] as $k => $v) {
                $data[$k] = $v;
            }
        }

        // 7) Push zum CRM
        $result = DGPTM_WSB_Veranstal_X_Contacts::upsert($data, $is_create ? null : $intent->veranstal_x_contact_id);
        if (!$result['success']) {
            $log_id = DGPTM_WSB_Sync_Log_Store::record(
                $intent, $previous, false,
                DGPTM_WSB_Sync_Result::ERR_ZOHO_API_ERROR,
                $result['error']
            );
            return DGPTM_WSB_Sync_Result::fail(DGPTM_WSB_Sync_Result::ERR_ZOHO_API_ERROR, $log_id);
        }

        // 8) Bei Erstanlage: ID nachtragen ins Intent (fuer Caller)
        if ($is_create && $result['id']) {
            $intent->veranstal_x_contact_id = $result['id'];
        }

        $log_id = DGPTM_WSB_Sync_Log_Store::record($intent, $previous, true);
        return DGPTM_WSB_Sync_Result::ok($log_id);
    }

    private static function snapshot_previous($current) {
        return [
            'blueprint' => isset($current[DGPTM_WSB_Veranstal_X_Contacts::FIELD_BLUEPRINT])
                        ? $current[DGPTM_WSB_Veranstal_X_Contacts::FIELD_BLUEPRINT] : null,
            'payment'   => isset($current[DGPTM_WSB_Veranstal_X_Contacts::FIELD_PAYMENT])
                        ? $current[DGPTM_WSB_Veranstal_X_Contacts::FIELD_PAYMENT] : null,
        ];
    }
}
```

- [ ] **Step 3.3 — Verifikation**

PHP-Lint + ein erster Smoke-Test in Modul-Aktivierung: Sync_Intent für nicht-existierenden Contact dispatchen, sicherstellen dass kein Crash, sondern strukturiertes Fail-Result.

---

## Task 4 — Event_Source + Contact_Lookup

**Files:**
- Create: `modules/business/workshop-booking/includes/class-event-source.php`
- Create: `modules/business/workshop-booking/includes/class-contact-lookup.php`

- [ ] **Step 4.1 — `class-event-source.php`**

Liest aktive Workshops/Webinare aus `DGfK_Events` per COQL. Filter `Event_Type IN ('Workshop','Webinar')` + `From_Date >= heute`. Cached für 5 Minuten via WP-Transient.

```php
<?php
/**
 * Liefert kommende Workshops/Webinare aus DGfK_Events.
 * Cache: 5 Minuten via Transient.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Event_Source {

    const TRANSIENT_KEY = 'dgptm_wsb_events_v1';
    const TTL           = 300; // 5 Min
    const COQL_URL      = 'https://www.zohoapis.eu/crm/v3/coql';

    public static function fetch_upcoming() {
        $cached = get_transient(self::TRANSIENT_KEY);
        if (is_array($cached)) {
            return $cached;
        }
        $events = self::query_zoho();
        if (is_array($events)) {
            set_transient(self::TRANSIENT_KEY, $events, self::TTL);
        }
        return is_array($events) ? $events : [];
    }

    public static function fetch_one($event_id) {
        foreach (self::fetch_upcoming() as $ev) {
            if (isset($ev['id']) && $ev['id'] === $event_id) {
                return $ev;
            }
        }
        return null;
    }

    private static function query_zoho() {
        $token = DGPTM_WSB_Veranstal_X_Contacts::get_token();
        if (!$token) return [];

        $select = 'id, Name, From_Date, End_Date, Event_Type, Maximum_Attendees, Tickets, Sprache, Storno_Frist_Tage, Anwesenheits_Schwelle_Prozent, EduGrant_Verfuegbar, EduGrant_Hoehe_EUR, EduGrant_Plaetze_Gesamt, EduGrant_Plaetze_Vergeben, Verantwortliche_Person, Ticket_Layout';
        $today  = date('Y-m-d');
        $coql   = "select $select from DGfK_Events where (Event_Type = 'Workshop' or Event_Type = 'Webinar') and From_Date >= '$today' limit 200";

        $resp = wp_remote_post(self::COQL_URL, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['select_query' => $coql]),
        ]);
        if (is_wp_error($resp)) return [];
        if (wp_remote_retrieve_response_code($resp) !== 200) return [];
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return isset($body['data']) && is_array($body['data']) ? $body['data'] : [];
    }

    public static function flush_cache() {
        delete_transient(self::TRANSIENT_KEY);
    }
}
```

- [ ] **Step 4.2 — `class-contact-lookup.php` (4-Felder-E-Mail-Suche)**

```php
<?php
/**
 * 4-Felder-E-Mail-Suche im Zoho-CRM-Modul Contacts.
 * Felder: Email, Secondary_Email, Third_Email, Fourth_Email.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Contact_Lookup {

    const COQL_URL = 'https://www.zohoapis.eu/crm/v3/coql';

    /**
     * Sucht einen Kontakt per E-Mail in 4 Feldern. Liefert die Zoho-Contact-ID oder null.
     */
    public static function find_by_email($email) {
        $email = strtolower(trim($email));
        if (empty($email) || !is_email($email)) {
            return null;
        }
        $token = DGPTM_WSB_Veranstal_X_Contacts::get_token();
        if (!$token) return null;

        $email_q = esc_sql($email);
        $coql = "select id from Contacts where Email = '$email_q' or Secondary_Email = '$email_q' or Third_Email = '$email_q' or Fourth_Email = '$email_q' limit 1";

        $resp = wp_remote_post(self::COQL_URL, [
            'timeout' => 12,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['select_query' => $coql]),
        ]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
            return null;
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return isset($body['data'][0]['id']) ? $body['data'][0]['id'] : null;
    }

    /**
     * Legt einen neuen Contact in Zoho an. Liefert die Contact-ID.
     */
    public static function create($first_name, $last_name, $email, array $extra = []) {
        $token = DGPTM_WSB_Veranstal_X_Contacts::get_token();
        if (!$token) return null;

        $data = array_merge([
            'First_Name' => $first_name,
            'Last_Name'  => $last_name,
            'Email'      => $email,
        ], $extra);

        $resp = wp_remote_post('https://www.zohoapis.eu/crm/v3/Contacts', [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['data' => [$data]]),
        ]);
        if (is_wp_error($resp)) return null;
        if (!in_array(wp_remote_retrieve_response_code($resp), [200, 201], true)) return null;
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return isset($body['data'][0]['details']['id']) ? $body['data'][0]['details']['id'] : null;
    }
}
```

- [ ] **Step 4.3 — Verifikation**

PHP-Lint. Manuell: ein bekannter E-Mail-Treffer (eigene DGPTM-Mail) → Contact-ID. Eine unbekannte E-Mail → null.

---

## Task 5 — Pending_Bookings_Store + Booking_Service

**Files:**
- Create: `modules/business/workshop-booking/includes/class-pending-bookings-store.php`
- Create: `modules/business/workshop-booking/includes/class-booking-service.php`

- [ ] **Step 5.1 — Pending_Bookings_Store**

```php
<?php
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Pending_Bookings_Store {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dgptm_workshop_pending_bookings';
    }

    public static function insert($contact_id, $event_id, array $attendees) {
        global $wpdb;
        $wpdb->insert(self::table(), [
            'veranstal_x_contact_id'    => $contact_id,
            'event_id'                  => $event_id,
            'attendees_json'            => wp_json_encode($attendees),
            'created_at'                => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function attach_session($contact_id, $session_id, $expires_at) {
        global $wpdb;
        $wpdb->update(self::table(),
            [
                'stripe_session_id'         => $session_id,
                'stripe_session_expires_at' => $expires_at,
            ],
            ['veranstal_x_contact_id' => $contact_id]
        );
    }

    public static function get_by_contact($contact_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE veranstal_x_contact_id = %s",
            $contact_id
        ), ARRAY_A);
    }

    public static function delete_by_contact($contact_id) {
        global $wpdb;
        $wpdb->delete(self::table(), ['veranstal_x_contact_id' => $contact_id]);
    }

    public static function get_expired() {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE stripe_session_expires_at IS NOT NULL AND stripe_session_expires_at < %s",
            current_time('mysql')
        ), ARRAY_A);
    }
}
```

- [ ] **Step 5.2 — Booking_Service (Orchestrator)**

```php
<?php
/**
 * Buchungs-Orchestrator. Entry-Point fuer Frontend-Buchungen.
 *
 * Spec Abschnitt 5.1.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Booking_Service {

    const RESULT_CHECKOUT  = 'checkout_required';
    const RESULT_FREE      = 'free_confirmed';
    const RESULT_WAITLIST  = 'waitlist';
    const RESULT_FULL      = 'full_no_waitlist';
    const RESULT_ERROR     = 'error';

    /**
     * @param string $event_id Zoho DGfK_Events-ID
     * @param array  $attendees Liste mit ['first_name','last_name','email','ticket_type','price_eur', ...]
     * @return array  ['result'=>..., 'checkout_url'=>?, 'contact_ids'=>[?], 'error'=>?]
     */
    public static function book($event_id, array $attendees) {
        $event = DGPTM_WSB_Event_Source::fetch_one($event_id);
        if (!$event) {
            return ['result' => self::RESULT_ERROR, 'error' => 'event_not_found'];
        }

        // Capacity-Check (Phase 1: einfach; Warteliste-Logik erst in Phase 6)
        $max  = isset($event['Maximum_Attendees']) ? (int) $event['Maximum_Attendees'] : 0;
        $taken = self::count_active_bookings($event_id);
        $needed = count($attendees);
        if ($max > 0 && ($taken + $needed) > $max) {
            return ['result' => self::RESULT_FULL, 'error' => 'capacity_exceeded'];
        }

        $contact_ids = [];
        $total_price = 0.0;
        foreach ($attendees as $attendee) {
            // 1) Contact-Lookup oder Neu-Anlage
            $zoho_contact_id = DGPTM_WSB_Contact_Lookup::find_by_email($attendee['email']);
            if (!$zoho_contact_id) {
                $zoho_contact_id = DGPTM_WSB_Contact_Lookup::create(
                    $attendee['first_name'],
                    $attendee['last_name'],
                    $attendee['email']
                );
            }
            if (!$zoho_contact_id) {
                return ['result' => self::RESULT_ERROR, 'error' => 'contact_creation_failed'];
            }

            // 2) Veranstal_X_Contacts-Eintrag anlegen via Sync_Coordinator
            $intent = new DGPTM_WSB_Sync_Intent(
                '', // leer = neu
                DGPTM_WSB_State_Machine::S_ZAHLUNG_AUSSTEHEND,
                'Ausstehend',
                DGPTM_WSB_Sync_Intent::SOURCE_BOOKING_INIT,
                [
                    'initial_fields' => [
                        'Contact_Name' => ['id' => $zoho_contact_id],
                        'Event_Name'   => ['id' => $event_id],
                        'Ticket_Type'  => isset($attendee['ticket_type']) ? $attendee['ticket_type'] : '',
                        'Price_EUR'    => isset($attendee['price_eur']) ? (float) $attendee['price_eur'] : 0,
                    ],
                ],
                'Booking initialisiert'
            );
            $sync = DGPTM_WSB_Sync_Coordinator::apply_intent($intent);
            if (!$sync->success) {
                return ['result' => self::RESULT_ERROR, 'error' => $sync->error_code];
            }
            $contact_ids[]  = $intent->veranstal_x_contact_id;
            $total_price   += isset($attendee['price_eur']) ? (float) $attendee['price_eur'] : 0;

            // 3) Pending-Booking eintragen
            DGPTM_WSB_Pending_Bookings_Store::insert($intent->veranstal_x_contact_id, $event_id, [$attendee]);
        }

        // 4a) Kostenlos? Direkt auf Angemeldet ohne Stripe.
        if ($total_price <= 0) {
            foreach ($contact_ids as $cid) {
                $intent = new DGPTM_WSB_Sync_Intent(
                    $cid,
                    DGPTM_WSB_State_Machine::S_ANGEMELDET,
                    'Bezahlt',
                    DGPTM_WSB_Sync_Intent::SOURCE_BOOKING_INIT,
                    [],
                    'Freiticket: keine Stripe-Session'
                );
                DGPTM_WSB_Sync_Coordinator::apply_intent($intent);
                DGPTM_WSB_Pending_Bookings_Store::delete_by_contact($cid);
                DGPTM_WSB_Mail_Sender::send_confirmation($cid, $event);
            }
            return ['result' => self::RESULT_FREE, 'contact_ids' => $contact_ids];
        }

        // 4b) Stripe-Checkout-Session
        $checkout = DGPTM_WSB_Stripe_Checkout::create_session($event, $attendees, $contact_ids);
        if (!$checkout['success']) {
            return ['result' => self::RESULT_ERROR, 'error' => $checkout['error']];
        }
        foreach ($contact_ids as $cid) {
            DGPTM_WSB_Pending_Bookings_Store::attach_session($cid, $checkout['session_id'], $checkout['expires_at']);
        }
        return [
            'result'       => self::RESULT_CHECKOUT,
            'checkout_url' => $checkout['url'],
            'contact_ids'  => $contact_ids,
        ];
    }

    /**
     * Zaehlt aktive (nicht-stornierte, nicht-abgebrochene) Buchungen fuer ein Event via COQL.
     */
    private static function count_active_bookings($event_id) {
        $token = DGPTM_WSB_Veranstal_X_Contacts::get_token();
        if (!$token) return 0;

        $event_id_q = esc_sql($event_id);
        $coql = "select count(id) from Veranstal_X_Contacts where Event_Name = '$event_id_q' and Anmelde_Status not in ('Storniert','Abgebrochen','Nicht teilgenommen')";
        $resp = wp_remote_post('https://www.zohoapis.eu/crm/v3/coql', [
            'timeout' => 12,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['select_query' => $coql]),
        ]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return 0;
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return isset($body['data'][0]['count(id)']) ? (int) $body['data'][0]['count(id)'] : 0;
    }
}
```

- [ ] **Step 5.3 — Verifikation**

PHP-Lint. Echter Test folgt nach Stripe-Klassen (Task 6).

---

## Task 6 — Stripe_Checkout + Stripe_Webhook

**Files:**
- Create: `modules/business/workshop-booking/includes/class-stripe-checkout.php`
- Create: `modules/business/workshop-booking/includes/class-stripe-webhook.php`

- [ ] **Step 6.1 — Stripe_Checkout**

Phase 1 setzt das eigene Unterkonto noch nicht voraus — Konfig über WP-Option `dgptm_wsb_stripe_secret_key` (per Filter überschreibbar). Wenn das Unterkonto eingerichtet ist, kann hier der `Stripe-Account`-Header gesetzt werden.

```php
<?php
/**
 * Stripe-Checkout-Session-Erzeugung. Nutzt direkten Curl auf api.stripe.com (kein Stripe-PHP-SDK,
 * um keine Composer-Abhaengigkeit zu erzeugen).
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Stripe_Checkout {

    const API_BASE = 'https://api.stripe.com/v1';

    public static function get_secret_key() {
        $key = get_option('dgptm_wsb_stripe_secret_key', '');
        return apply_filters('dgptm_wsb_stripe_secret_key', $key);
    }

    public static function get_account_id() {
        return apply_filters('dgptm_wsb_stripe_account_id', get_option('dgptm_wsb_stripe_account_id', ''));
    }

    public static function get_webhook_secret() {
        return apply_filters('dgptm_wsb_stripe_webhook_secret', get_option('dgptm_wsb_stripe_webhook_secret', ''));
    }

    public static function create_session($event, array $attendees, array $contact_ids) {
        $key = self::get_secret_key();
        if (!$key) {
            return ['success' => false, 'error' => 'no_stripe_key'];
        }

        $line_items = [];
        foreach ($attendees as $i => $a) {
            $name  = sprintf('%s — %s %s', isset($event['Name']) ? $event['Name'] : 'Workshop', $a['first_name'], $a['last_name']);
            $price = isset($a['price_eur']) ? (float) $a['price_eur'] : 0;
            $line_items["line_items[$i][price_data][currency]"]     = 'eur';
            $line_items["line_items[$i][price_data][product_data][name]"] = $name;
            $line_items["line_items[$i][price_data][unit_amount]"]  = (int) round($price * 100);
            $line_items["line_items[$i][quantity]"]                 = 1;
        }

        $params = array_merge([
            'mode'                       => 'payment',
            'payment_method_types[0]'    => 'card',
            'payment_method_types[1]'    => 'sepa_debit',
            'success_url'                => self::success_url(),
            'cancel_url'                 => self::cancel_url(),
            'allow_promotion_codes'      => 'false',
            'metadata[event_id]'         => isset($event['id']) ? $event['id'] : '',
            'metadata[contact_ids]'      => implode(',', $contact_ids),
            'expires_at'                 => time() + 30 * 60, // 30 Min
        ], $line_items);

        $headers = [
            'Authorization' => 'Bearer ' . $key,
            'Content-Type'  => 'application/x-www-form-urlencoded',
        ];
        $account = self::get_account_id();
        if ($account) {
            $headers['Stripe-Account'] = $account;
        }

        $resp = wp_remote_post(self::API_BASE . '/checkout/sessions', [
            'timeout' => 20,
            'headers' => $headers,
            'body'    => $params,
        ]);
        if (is_wp_error($resp)) {
            return ['success' => false, 'error' => $resp->get_error_message()];
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if (wp_remote_retrieve_response_code($resp) !== 200) {
            return ['success' => false, 'error' => isset($body['error']['message']) ? $body['error']['message'] : 'stripe_error'];
        }
        return [
            'success'    => true,
            'session_id' => $body['id'],
            'url'        => $body['url'],
            'expires_at' => date('Y-m-d H:i:s', $body['expires_at']),
        ];
    }

    public static function success_url() {
        return add_query_arg(['dgptm_wsb' => 'success'], home_url('/buchung-bestaetigt/'));
    }

    public static function cancel_url() {
        return add_query_arg(['dgptm_wsb' => 'cancelled'], home_url('/'));
    }
}
```

- [ ] **Step 6.2 — Stripe_Webhook (REST-Endpoint)**

```php
<?php
/**
 * REST-Endpoint /wp-json/dgptm-workshop/v1/stripe-webhook.
 *
 * Empfaengt Stripe-Events und uebersetzt sie in Sync_Intents.
 * Signatur-Pruefung mit Stripe-Webhook-Secret.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Stripe_Webhook {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', [$this, 'register_route']);
    }

    public function register_route() {
        register_rest_route('dgptm-workshop/v1', '/stripe-webhook', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle(WP_REST_Request $request) {
        $payload   = $request->get_body();
        $signature = $request->get_header('stripe_signature');
        $secret    = DGPTM_WSB_Stripe_Checkout::get_webhook_secret();

        if (!$this->verify_signature($payload, $signature, $secret)) {
            return new WP_REST_Response(['error' => 'invalid_signature'], 401);
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['type'])) {
            return new WP_REST_Response(['error' => 'invalid_payload'], 400);
        }

        switch ($event['type']) {
            case 'checkout.session.completed':
                $this->handle_completed($event['data']['object']);
                break;
            case 'checkout.session.expired':
                $this->handle_expired($event['data']['object']);
                break;
            case 'charge.refunded':
                $this->handle_refunded($event['data']['object']);
                break;
            default:
                // Ignorieren
                break;
        }

        return new WP_REST_Response(['ok' => true], 200);
    }

    private function verify_signature($payload, $sig_header, $secret) {
        if (empty($secret) || empty($sig_header)) return false;
        // Stripe-Signature: t=1614265460,v1=abcdef...
        $parts = [];
        foreach (explode(',', $sig_header) as $kv) {
            $kv = explode('=', $kv, 2);
            if (count($kv) === 2) $parts[$kv[0]] = $kv[1];
        }
        if (empty($parts['t']) || empty($parts['v1'])) return false;
        $signed_payload = $parts['t'] . '.' . $payload;
        $expected = hash_hmac('sha256', $signed_payload, $secret);
        return hash_equals($expected, $parts['v1']);
    }

    private function handle_completed($session) {
        $contact_ids = isset($session['metadata']['contact_ids']) ? explode(',', $session['metadata']['contact_ids']) : [];
        foreach ($contact_ids as $cid) {
            $cid = trim($cid);
            if (!$cid) continue;
            $intent = new DGPTM_WSB_Sync_Intent(
                $cid,
                DGPTM_WSB_State_Machine::S_ANGEMELDET,
                'Bezahlt',
                DGPTM_WSB_Sync_Intent::SOURCE_STRIPE_WEBHOOK,
                [
                    'stripe_event_id' => isset($session['id']) ? $session['id'] : '',
                    'extra_fields'    => [
                        'Stripe_Session_ID' => isset($session['id']) ? $session['id'] : '',
                        'Stripe_Charge_ID'  => isset($session['payment_intent']) ? $session['payment_intent'] : '',
                    ],
                ],
                'checkout.session.completed'
            );
            $result = DGPTM_WSB_Sync_Coordinator::apply_intent($intent);
            if ($result->success) {
                $event_id = isset($session['metadata']['event_id']) ? $session['metadata']['event_id'] : '';
                $event = $event_id ? DGPTM_WSB_Event_Source::fetch_one($event_id) : null;
                DGPTM_WSB_Mail_Sender::send_confirmation($cid, $event);
                DGPTM_WSB_Pending_Bookings_Store::delete_by_contact($cid);
            }
        }
    }

    private function handle_expired($session) {
        $contact_ids = isset($session['metadata']['contact_ids']) ? explode(',', $session['metadata']['contact_ids']) : [];
        foreach ($contact_ids as $cid) {
            $cid = trim($cid);
            if (!$cid) continue;
            $intent = new DGPTM_WSB_Sync_Intent(
                $cid,
                DGPTM_WSB_State_Machine::S_ABGEBROCHEN,
                null,
                DGPTM_WSB_Sync_Intent::SOURCE_STRIPE_WEBHOOK,
                ['stripe_event_id' => isset($session['id']) ? $session['id'] : ''],
                'checkout.session.expired'
            );
            DGPTM_WSB_Sync_Coordinator::apply_intent($intent);
            DGPTM_WSB_Pending_Bookings_Store::delete_by_contact($cid);
        }
    }

    private function handle_refunded($charge) {
        // Charge-ID → suche zugehoerigen Veranstal_X_Contacts-Eintrag via Stripe_Charge_ID
        $contact_id = $this->find_contact_by_charge(isset($charge['payment_intent']) ? $charge['payment_intent'] : '');
        if (!$contact_id) return;

        $intent = new DGPTM_WSB_Sync_Intent(
            $contact_id,
            DGPTM_WSB_State_Machine::S_STORNIERT,
            'Erstattet',
            DGPTM_WSB_Sync_Intent::SOURCE_STRIPE_WEBHOOK,
            ['stripe_charge_id' => isset($charge['id']) ? $charge['id'] : ''],
            'charge.refunded'
        );
        DGPTM_WSB_Sync_Coordinator::apply_intent($intent);
    }

    private function find_contact_by_charge($payment_intent_id) {
        if (!$payment_intent_id) return null;
        $token = DGPTM_WSB_Veranstal_X_Contacts::get_token();
        if (!$token) return null;
        $pi = esc_sql($payment_intent_id);
        $coql = "select id from Veranstal_X_Contacts where Stripe_Charge_ID = '$pi' limit 1";
        $resp = wp_remote_post('https://www.zohoapis.eu/crm/v3/coql', [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['select_query' => $coql]),
        ]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return null;
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return isset($body['data'][0]['id']) ? $body['data'][0]['id'] : null;
    }
}
```

- [ ] **Step 6.3 — Verifikation**

PHP-Lint. Stripe-CLI: `stripe trigger checkout.session.completed --add checkout_session:metadata.contact_ids=TEST` gegen den lokalen Endpoint, prüfen ob `wp_dgptm_workshop_sync_log` einen Eintrag mit `success=0` (Contact existiert nicht) bekommt. Im Fail-Fall ist nur das Signature-Check-Verhalten relevant.

---

## Task 7 — ICS_Builder + Mail_Sender

**Files:**
- Create: `modules/business/workshop-booking/includes/class-ics-builder.php`
- Create: `modules/business/workshop-booking/includes/class-mail-sender.php`
- Create: `modules/business/workshop-booking/templates/mails/booking-confirmation.php`

- [ ] **Step 7.1 — ICS_Builder**

```php
<?php
/**
 * Erzeugt eine VCALENDAR-Datei (RFC 5545) als ICS-Anhang.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_ICS_Builder {

    public static function build($event, $contact_email) {
        $name      = isset($event['Name']) ? $event['Name'] : 'DGPTM Workshop';
        $from      = isset($event['From_Date']) ? $event['From_Date'] : null;
        $to        = isset($event['End_Date']) ? $event['End_Date'] : $from;
        $uid       = sha1((isset($event['id']) ? $event['id'] : '') . '|' . $contact_email) . '@dgptm.de';
        $stamp     = gmdate('Ymd\THis\Z');
        $start_utc = self::format_dtstart($from);
        $end_utc   = self::format_dtstart($to);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//DGPTM//Workshop-Booking//DE',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $stamp,
            'DTSTART:' . $start_utc,
            'DTEND:'   . $end_utc,
            'SUMMARY:' . self::esc($name),
            'DESCRIPTION:' . self::esc('Buchung der DGPTM (Deutsche Gesellschaft für Perfusiologie und Technische Medizin e.V.)'),
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];
        return implode("\r\n", $lines) . "\r\n";
    }

    private static function format_dtstart($date) {
        if (!$date) return gmdate('Ymd\THis\Z');
        // Akzeptiert sowohl Datum als auch Datetime
        $ts = strtotime($date);
        if ($ts === false) return gmdate('Ymd\THis\Z');
        return gmdate('Ymd\THis\Z', $ts);
    }

    private static function esc($text) {
        $text = str_replace(["\\","\n",",",";"], ["\\\\","\\n","\\,","\\;"], $text);
        return $text;
    }
}
```

- [ ] **Step 7.2 — Mail_Sender**

```php
<?php
/**
 * Versendet Buchungs-Bestaetigung mit ICS-Anhang.
 * Phase 1: nur send_confirmation(). Reminder, Storno, Verlegung in spaeteren Phasen.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Mail_Sender {

    public static function send_confirmation($veranstal_x_contact_id, $event) {
        $contact = DGPTM_WSB_Veranstal_X_Contacts::fetch($veranstal_x_contact_id);
        if (!$contact) return false;

        $email = self::extract_email($contact);
        if (!$email) return false;

        $first_name = isset($contact['Contact_Name']['First_Name']) ? $contact['Contact_Name']['First_Name'] : '';
        $last_name  = isset($contact['Contact_Name']['Last_Name'])  ? $contact['Contact_Name']['Last_Name']  : '';
        $full_name  = trim($first_name . ' ' . $last_name);

        $event_name = isset($event['Name']) ? $event['Name'] : 'Workshop';
        $event_from = isset($event['From_Date']) ? date_i18n('d.m.Y', strtotime($event['From_Date'])) : '';

        $body = self::render_template($full_name, $event_name, $event_from);

        $tmp = wp_tempnam('dgptm_wsb_ics');
        file_put_contents($tmp, DGPTM_WSB_ICS_Builder::build($event, $email));
        $ics_path = $tmp . '.ics';
        rename($tmp, $ics_path);

        $subject = 'DGPTM Buchungsbestätigung: ' . $event_name;
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $sent = wp_mail($email, $subject, $body, $headers, [$ics_path]);
        @unlink($ics_path);
        return $sent;
    }

    private static function render_template($name, $event_name, $event_from) {
        ob_start();
        $tpl = dirname(__DIR__) . '/templates/mails/booking-confirmation.php';
        if (file_exists($tpl)) {
            include $tpl;
        }
        return ob_get_clean();
    }

    private static function extract_email($contact) {
        if (!empty($contact['Contact_Name']['email'])) return $contact['Contact_Name']['email'];
        if (!empty($contact['Email'])) return $contact['Email'];
        return null;
    }
}
```

- [ ] **Step 7.3 — Mail-Template**

```php
<?php
/**
 * @var string $name
 * @var string $event_name
 * @var string $event_from
 */
if (!defined('ABSPATH')) exit;
?>
<!DOCTYPE html>
<html lang="de"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
  <tr><td style="background:#003366;padding:20px 30px;color:#ffffff;font-size:20px;font-weight:700;">DGPTM</td></tr>
  <tr><td style="padding:28px 30px 12px;">
    <h2 style="margin:0;font-size:18px;color:#1a1a1a;">Buchungsbestätigung</h2>
    <p style="margin:6px 0 0;font-size:14px;color:#6b7280;">Hallo <?php echo esc_html($name); ?>,</p>
  </td></tr>
  <tr><td style="padding:8px 30px 24px;font-size:15px;line-height:1.6;color:#1a1a1a;">
    <p>vielen Dank für deine Anmeldung zu <strong><?php echo esc_html($event_name); ?></strong>.</p>
    <p>Termin: <strong><?php echo esc_html($event_from); ?></strong></p>
    <p>Im Anhang findest du den Termin als Kalender-Datei (ICS).</p>
    <p>Weitere Informationen folgen rechtzeitig vor der Veranstaltung.</p>
  </td></tr>
  <tr><td style="background:#f9fafb;padding:16px 30px;border-top:1px solid #e5e7eb;">
    <p style="margin:0;font-size:12px;color:#9ca3af;text-align:center;">Deutsche Gesellschaft für Perfusiologie und Technische Medizin e.V.</p>
  </td></tr>
</table>
</td></tr></table>
</body></html>
```

- [ ] **Step 7.4 — Verifikation**

PHP-Lint. WordPress-Test: `DGPTM_WSB_Mail_Sender::send_confirmation('TEST_ID', $fake_event)` aus dem Functions-PHP triggern; checken ob Mail in `error_log` (oder Mailcatcher) ankommt.

---

## Task 8 — Books_Status_Reader + Reconciliation_Cron + Pending_Cleanup_Cron

**Files:**
- Create: `modules/business/workshop-booking/includes/class-books-status-reader.php`
- Create: `modules/business/workshop-booking/includes/class-reconciliation-cron.php`
- Create: `modules/business/workshop-booking/includes/class-pending-cleanup-cron.php`

- [ ] **Step 8.1 — Books_Status_Reader (Phase 1: Stub mit Read-only-API)**

Phase 1 reicht es, eine Methode `is_invoice_paid($invoice_id)` zu liefern. Falls Books-Modul nicht aktiv → null (= „unbekannt").

```php
<?php
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Books_Status_Reader {

    /**
     * @return bool|null  true=bezahlt, false=offen, null=unbekannt/Books nicht erreichbar
     */
    public static function is_invoice_paid($invoice_id) {
        if (empty($invoice_id)) return null;

        $token = DGPTM_WSB_Veranstal_X_Contacts::get_token();
        if (!$token) return null;
        $org_id = apply_filters('dgptm_wsb_books_org_id', get_option('dgptm_wsb_books_org_id', ''));
        if (!$org_id) return null;

        $url = 'https://www.zohoapis.eu/books/v3/invoices/' . rawurlencode($invoice_id) . '?organization_id=' . rawurlencode($org_id);
        $resp = wp_remote_get($url, [
            'timeout' => 10,
            'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $token],
        ]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return null;
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $status = isset($body['invoice']['status']) ? strtolower($body['invoice']['status']) : null;
        if ($status === 'paid') return true;
        if (in_array($status, ['sent', 'partially_paid', 'overdue', 'unpaid', 'draft'], true)) return false;
        return null;
    }
}
```

- [ ] **Step 8.2 — Reconciliation_Cron**

```php
<?php
/**
 * Cron alle 15 Min: prueft offene + kuerzlich aktive Modul-Buchungen auf Drift
 * zwischen Stripe/Books und CRM.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Reconciliation_Cron {

    const HOOK     = 'dgptm_wsb_reconcile';
    const INTERVAL = 'dgptm_wsb_15min';

    private static $instance = null;
    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_filter('cron_schedules', [$this, 'add_schedule']);
        add_action(self::HOOK, [$this, 'run']);
        add_action('init', [$this, 'maybe_schedule']);
    }

    public function add_schedule($schedules) {
        $schedules[self::INTERVAL] = ['interval' => 900, 'display' => 'Alle 15 Minuten'];
        return $schedules;
    }

    public function maybe_schedule() {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 60, self::INTERVAL, self::HOOK);
        }
    }

    public function run() {
        $token = DGPTM_WSB_Veranstal_X_Contacts::get_token();
        if (!$token) return;

        // COQL: nur Quelle=Modul, nur kuerzlich aktiv (7 Tage), nur relevante Status
        $coql = "select id, Anmelde_Status, Zahlungsstatus, Stripe_Session_ID, Stripe_Charge_ID, Quelle "
              . "from Veranstal_X_Contacts "
              . "where Quelle = 'Modul' "
              . "and Modified_Time >= '" . date('Y-m-d', strtotime('-7 days')) . "' "
              . "and Anmelde_Status in ('Zahlung ausstehend','Angemeldet','Storniert') "
              . "limit 200";

        $resp = wp_remote_post('https://www.zohoapis.eu/crm/v3/coql', [
            'timeout' => 25,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['select_query' => $coql]),
        ]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return;
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $rows = isset($body['data']) ? $body['data'] : [];

        foreach ($rows as $row) {
            $this->check_row($row);
        }
    }

    private function check_row(array $row) {
        $contact_id = $row['id'];
        $crm_blueprint = isset($row['Anmelde_Status'])  ? $row['Anmelde_Status']  : null;
        $crm_payment   = isset($row['Zahlungsstatus'])  ? $row['Zahlungsstatus']  : null;
        $session_id    = isset($row['Stripe_Session_ID']) ? $row['Stripe_Session_ID'] : null;
        $charge_id     = isset($row['Stripe_Charge_ID'])  ? $row['Stripe_Charge_ID']  : null;

        $stripe_status = $this->fetch_stripe_status($session_id, $charge_id);
        if (!$stripe_status) return;

        // Auto-Korrektur: Stripe paid, CRM ausstehend → Angemeldet/Bezahlt
        if ($stripe_status['paid'] && $crm_blueprint === DGPTM_WSB_State_Machine::S_ZAHLUNG_AUSSTEHEND) {
            $intent = new DGPTM_WSB_Sync_Intent(
                $contact_id,
                DGPTM_WSB_State_Machine::S_ANGEMELDET,
                'Bezahlt',
                DGPTM_WSB_Sync_Intent::SOURCE_RECONCILIATION,
                ['stripe_status' => $stripe_status],
                'reconciliation: stripe_paid_crm_pending'
            );
            DGPTM_WSB_Sync_Coordinator::apply_intent($intent);
            return;
        }

        // Auto-Korrektur: Stripe refunded, CRM Angemeldet → Storniert
        if ($stripe_status['refunded'] && $crm_blueprint === DGPTM_WSB_State_Machine::S_ANGEMELDET) {
            $intent = new DGPTM_WSB_Sync_Intent(
                $contact_id,
                DGPTM_WSB_State_Machine::S_STORNIERT,
                'Erstattet',
                DGPTM_WSB_Sync_Intent::SOURCE_RECONCILIATION,
                ['stripe_status' => $stripe_status],
                'reconciliation: stripe_refunded_crm_active'
            );
            DGPTM_WSB_Sync_Coordinator::apply_intent($intent);
            return;
        }

        // Alert: CRM Storniert, aber Stripe nicht erstattet
        if ($crm_blueprint === DGPTM_WSB_State_Machine::S_STORNIERT && $stripe_status['paid'] && !$stripe_status['refunded']) {
            DGPTM_WSB_Drift_Alert_Store::open_alert(
                $contact_id,
                'manual_storno_without_refund',
                DGPTM_WSB_Drift_Alert_Store::SEVERITY_WARNING,
                ['blueprint' => $crm_blueprint, 'payment' => $crm_payment],
                $stripe_status,
                'Pruefen: Storno im CRM gesetzt, aber Stripe-Charge nicht erstattet. Manuelle Erstattung oder Bar?'
            );
        }
    }

    private function fetch_stripe_status($session_id, $charge_id) {
        $key = DGPTM_WSB_Stripe_Checkout::get_secret_key();
        if (!$key) return null;

        $headers = ['Authorization' => 'Bearer ' . $key];
        $account = DGPTM_WSB_Stripe_Checkout::get_account_id();
        if ($account) $headers['Stripe-Account'] = $account;

        if (!empty($charge_id)) {
            // Charge-ID ist eigentlich PaymentIntent-ID (so legen wir es ab)
            $url = DGPTM_WSB_Stripe_Checkout::API_BASE . '/payment_intents/' . rawurlencode($charge_id);
            $resp = wp_remote_get($url, ['timeout' => 10, 'headers' => $headers]);
            if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return null;
            $pi = json_decode(wp_remote_retrieve_body($resp), true);
            $paid     = isset($pi['status']) && $pi['status'] === 'succeeded';
            $refunded = !empty($pi['charges']['data'][0]['refunded']);
            return ['paid' => $paid, 'refunded' => $refunded, 'raw_status' => isset($pi['status']) ? $pi['status'] : null];
        }
        if (!empty($session_id)) {
            $url = DGPTM_WSB_Stripe_Checkout::API_BASE . '/checkout/sessions/' . rawurlencode($session_id);
            $resp = wp_remote_get($url, ['timeout' => 10, 'headers' => $headers]);
            if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return null;
            $sess = json_decode(wp_remote_retrieve_body($resp), true);
            $paid = isset($sess['payment_status']) && $sess['payment_status'] === 'paid';
            return ['paid' => $paid, 'refunded' => false, 'raw_status' => isset($sess['payment_status']) ? $sess['payment_status'] : null];
        }
        return null;
    }
}
```

- [ ] **Step 8.3 — Pending_Cleanup_Cron**

```php
<?php
/**
 * Cron alle 15 Min: raeumt abgelaufene Stripe-Sessions auf
 * (Sync-Intent Abgebrochen + pending_bookings loeschen).
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Pending_Cleanup_Cron {

    const HOOK = 'dgptm_wsb_pending_cleanup';

    private static $instance = null;
    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action(self::HOOK, [$this, 'run']);
        add_action('init', [$this, 'maybe_schedule']);
    }

    public function maybe_schedule() {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 90, DGPTM_WSB_Reconciliation_Cron::INTERVAL, self::HOOK);
        }
    }

    public function run() {
        foreach (DGPTM_WSB_Pending_Bookings_Store::get_expired() as $row) {
            $intent = new DGPTM_WSB_Sync_Intent(
                $row['veranstal_x_contact_id'],
                DGPTM_WSB_State_Machine::S_ABGEBROCHEN,
                null,
                DGPTM_WSB_Sync_Intent::SOURCE_RECONCILIATION,
                ['expired_session' => $row['stripe_session_id']],
                'pending_cleanup: stripe_session_expired'
            );
            DGPTM_WSB_Sync_Coordinator::apply_intent($intent);
            DGPTM_WSB_Pending_Bookings_Store::delete_by_contact($row['veranstal_x_contact_id']);
        }
    }
}
```

- [ ] **Step 8.4 — Verifikation**

PHP-Lint. In WordPress: `wp cron event run dgptm_wsb_reconcile` (WP-CLI), Logs prüfen.

---

## Task 9 — Shortcodes + Templates + Frontend-Assets

**Files:**
- Create: `modules/business/workshop-booking/includes/class-shortcodes.php`
- Create: `modules/business/workshop-booking/templates/workshops-list.php`
- Create: `modules/business/workshop-booking/templates/booking-form.php`
- Create: `modules/business/workshop-booking/templates/booking-success.php`
- Create: `modules/business/workshop-booking/assets/css/frontend.css`
- Create: `modules/business/workshop-booking/assets/js/booking-form.js`

- [ ] **Step 9.1 — Shortcodes**

```php
<?php
/**
 * Shortcodes: [dgptm_workshops] (Liste/Detail/Formular), [dgptm_workshops_success] (Bestaetigung).
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Shortcodes {

    private static $instance = null;
    private $plugin_path;
    private $plugin_url;

    public static function get_instance($path = null, $url = null) {
        if (null === self::$instance) self::$instance = new self($path, $url);
        return self::$instance;
    }

    private function __construct($path, $url) {
        $this->plugin_path = $path;
        $this->plugin_url  = $url;

        add_shortcode('dgptm_workshops',         [$this, 'render_list']);
        add_shortcode('dgptm_workshops_success', [$this, 'render_success']);
        add_action('wp_enqueue_scripts',         [$this, 'register_assets']);
        add_action('wp_ajax_dgptm_wsb_book',          [$this, 'ajax_book']);
        add_action('wp_ajax_nopriv_dgptm_wsb_book',   [$this, 'ajax_book']);
    }

    public function register_assets() {
        wp_register_style('dgptm-wsb-frontend',
            $this->plugin_url . 'assets/css/frontend.css', [], '0.3.0');
        wp_register_script('dgptm-wsb-booking-form',
            $this->plugin_url . 'assets/js/booking-form.js', ['jquery'], '0.3.0', true);
    }

    public function render_list($atts) {
        wp_enqueue_style('dgptm-wsb-frontend');
        wp_enqueue_script('dgptm-wsb-booking-form');
        wp_localize_script('dgptm-wsb-booking-form', 'dgptmWsb', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('dgptm_wsb_book'),
        ]);

        $events = DGPTM_WSB_Event_Source::fetch_upcoming();
        ob_start();
        include $this->plugin_path . 'templates/workshops-list.php';
        return ob_get_clean();
    }

    public function render_success($atts) {
        wp_enqueue_style('dgptm-wsb-frontend');
        ob_start();
        include $this->plugin_path . 'templates/booking-success.php';
        return ob_get_clean();
    }

    public function ajax_book() {
        check_ajax_referer('dgptm_wsb_book', 'nonce');

        $event_id = sanitize_text_field(wp_unslash($_POST['event_id'] ?? ''));
        $raw_attendees = json_decode(wp_unslash($_POST['attendees'] ?? '[]'), true);
        if (empty($event_id) || !is_array($raw_attendees) || empty($raw_attendees)) {
            wp_send_json_error('invalid_input');
        }

        $attendees = [];
        foreach ($raw_attendees as $a) {
            if (empty($a['first_name']) || empty($a['last_name']) || empty($a['email']) || !is_email($a['email'])) {
                wp_send_json_error('invalid_attendee');
            }
            $attendees[] = [
                'first_name'  => sanitize_text_field($a['first_name']),
                'last_name'   => sanitize_text_field($a['last_name']),
                'email'       => sanitize_email($a['email']),
                'ticket_type' => isset($a['ticket_type']) ? sanitize_text_field($a['ticket_type']) : '',
                'price_eur'   => isset($a['price_eur']) ? (float) $a['price_eur'] : 0,
            ];
        }

        $result = DGPTM_WSB_Booking_Service::book($event_id, $attendees);

        if ($result['result'] === DGPTM_WSB_Booking_Service::RESULT_CHECKOUT) {
            wp_send_json_success(['redirect_url' => $result['checkout_url']]);
        }
        if ($result['result'] === DGPTM_WSB_Booking_Service::RESULT_FREE) {
            wp_send_json_success(['redirect_url' => add_query_arg(['dgptm_wsb' => 'success'], home_url('/buchung-bestaetigt/'))]);
        }
        wp_send_json_error($result['error'] ?? $result['result']);
    }
}
```

- [ ] **Step 9.2 — Template `workshops-list.php`**

```php
<?php
/**
 * @var array $events
 */
if (!defined('ABSPATH')) exit;
?>
<div class="dgptm-wsb-list">
    <?php if (empty($events)) : ?>
        <p>Aktuell sind keine Workshops oder Webinare buchbar.</p>
    <?php else : foreach ($events as $event) :
        $name      = isset($event['Name']) ? $event['Name'] : '';
        $event_id  = isset($event['id'])   ? $event['id']   : '';
        $from      = isset($event['From_Date']) ? date_i18n('d.m.Y', strtotime($event['From_Date'])) : '';
        $type      = isset($event['Event_Type']) ? $event['Event_Type'] : '';
        $tickets   = isset($event['Tickets']) && is_array($event['Tickets']) ? $event['Tickets'] : [];
    ?>
        <article class="dgptm-wsb-card" data-event-id="<?php echo esc_attr($event_id); ?>">
            <header>
                <span class="dgptm-wsb-card-type"><?php echo esc_html($type); ?></span>
                <h3><?php echo esc_html($name); ?></h3>
                <p class="dgptm-wsb-card-date"><?php echo esc_html($from); ?></p>
            </header>
            <button type="button" class="dgptm-wsb-card-book" data-event-id="<?php echo esc_attr($event_id); ?>" data-event-name="<?php echo esc_attr($name); ?>">
                Jetzt buchen
            </button>
        </article>
    <?php endforeach; endif; ?>
</div>

<dialog class="dgptm-wsb-dialog" id="dgptm-wsb-booking-dialog">
    <form id="dgptm-wsb-booking-form">
        <input type="hidden" name="event_id" value="">
        <h3>Buchung: <span class="dgptm-wsb-dialog-event-name"></span></h3>
        <div class="dgptm-wsb-attendee">
            <label>Vorname <input type="text" name="first_name" required></label>
            <label>Nachname <input type="text" name="last_name" required></label>
            <label>E-Mail <input type="email" name="email" required></label>
        </div>
        <div class="dgptm-wsb-actions">
            <button type="button" class="dgptm-wsb-cancel">Abbrechen</button>
            <button type="submit" class="dgptm-wsb-submit">Verbindlich buchen</button>
        </div>
        <p class="dgptm-wsb-feedback" aria-live="polite"></p>
    </form>
</dialog>
```

- [ ] **Step 9.3 — Templates `booking-form.php` (Stub) + `booking-success.php`**

`booking-form.php` ist in V1 Bestandteil von workshops-list.php (Dialog), wird aber als separate Datei für spätere Phasen vorbereitet:

```php
<?php
if (!defined('ABSPATH')) exit;
// Phase 1: Buchungs-Form ist Teil des List-Templates (Dialog).
// Diese Datei wird in Phase 2 (Tickets, Studi-Upload) ausgebaut.
?>
```

`booking-success.php`:

```php
<?php
if (!defined('ABSPATH')) exit;
?>
<div class="dgptm-wsb-success">
    <h2>Vielen Dank!</h2>
    <p>Deine Buchung ist eingegangen. Du erhältst in Kürze eine Bestätigungs-E-Mail mit Termin-Anhang.</p>
    <p>Bei Fragen wende dich bitte an die <a href="mailto:geschaeftsstelle@dgptm.de">Geschäftsstelle</a>.</p>
</div>
```

- [ ] **Step 9.4 — Frontend-CSS (Designsprache aus Umfragen-Modul)**

```css
/* assets/css/frontend.css */
.dgptm-wsb-list {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 16px;
    margin: 24px 0;
}

.dgptm-wsb-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.06);
    display: flex;
    flex-direction: column;
    gap: 12px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}

.dgptm-wsb-card header { display: flex; flex-direction: column; gap: 6px; }
.dgptm-wsb-card-type {
    font-size: 12px; text-transform: uppercase; letter-spacing: 0.04em;
    color: #4f46e5; font-weight: 600;
}
.dgptm-wsb-card h3 { margin: 0; font-size: 17px; color: #111827; }
.dgptm-wsb-card-date { margin: 0; font-size: 14px; color: #6b7280; }

.dgptm-wsb-card-book {
    margin-top: auto; align-self: flex-start;
    background: #4f46e5; color: #fff; border: none;
    padding: 10px 18px; border-radius: 8px;
    font-size: 14px; font-weight: 600; cursor: pointer;
    transition: background 0.2s ease;
}
.dgptm-wsb-card-book:hover { background: #4338ca; }

.dgptm-wsb-dialog {
    border: none; border-radius: 12px; padding: 0;
    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
    max-width: 480px; width: 90%;
}
.dgptm-wsb-dialog form { padding: 28px 30px; font-family: inherit; }
.dgptm-wsb-dialog h3 { margin: 0 0 16px; font-size: 18px; }
.dgptm-wsb-attendee { display: grid; gap: 12px; margin-bottom: 18px; }
.dgptm-wsb-attendee label {
    display: flex; flex-direction: column; gap: 4px;
    font-size: 14px; font-weight: 500;
}
.dgptm-wsb-attendee input {
    padding: 10px 14px; border: 1.5px solid #e5e7eb;
    background: #f9fafb; border-radius: 8px; font-size: 14px;
}
.dgptm-wsb-attendee input:focus {
    outline: none; border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79,70,229,0.1);
}
.dgptm-wsb-actions { display: flex; justify-content: flex-end; gap: 10px; }
.dgptm-wsb-actions button {
    padding: 10px 18px; border-radius: 8px; font-size: 14px;
    font-weight: 600; cursor: pointer; border: none;
}
.dgptm-wsb-cancel { background: #f3f4f6; color: #374151; }
.dgptm-wsb-submit { background: #4f46e5; color: #fff; }
.dgptm-wsb-feedback { margin: 12px 0 0; font-size: 13px; color: #ef4444; min-height: 18px; }

.dgptm-wsb-success {
    background: #ecfdf5; border: 1px solid #10b981;
    border-radius: 12px; padding: 24px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
}
.dgptm-wsb-success h2 { margin: 0 0 12px; color: #065f46; }
```

- [ ] **Step 9.5 — Frontend-JS**

```javascript
// assets/js/booking-form.js
(function ($) {
    'use strict';

    $(document).on('click', '.dgptm-wsb-card-book', function () {
        var $btn = $(this);
        var $dialog = $('#dgptm-wsb-booking-dialog');
        $dialog.find('input[name="event_id"]').val($btn.data('event-id'));
        $dialog.find('.dgptm-wsb-dialog-event-name').text($btn.data('event-name'));
        $dialog.find('.dgptm-wsb-feedback').text('');
        var dlg = $dialog.get(0);
        if (dlg && typeof dlg.showModal === 'function') {
            dlg.showModal();
        } else {
            $dialog.attr('open', 'open');
        }
    });

    $(document).on('click', '.dgptm-wsb-cancel', function () {
        var dlg = $('#dgptm-wsb-booking-dialog').get(0);
        if (dlg && typeof dlg.close === 'function') {
            dlg.close();
        } else {
            $(dlg).removeAttr('open');
        }
    });

    $(document).on('submit', '#dgptm-wsb-booking-form', function (e) {
        e.preventDefault();
        var $form = $(this);
        var $fb = $form.find('.dgptm-wsb-feedback').text('Wird gesendet...');
        var attendees = [{
            first_name: $form.find('input[name="first_name"]').val(),
            last_name:  $form.find('input[name="last_name"]').val(),
            email:      $form.find('input[name="email"]').val(),
            price_eur:  0
        }];
        $.post(dgptmWsb.ajaxUrl, {
            action: 'dgptm_wsb_book',
            nonce: dgptmWsb.nonce,
            event_id: $form.find('input[name="event_id"]').val(),
            attendees: JSON.stringify(attendees)
        }).done(function (resp) {
            if (resp && resp.success && resp.data && resp.data.redirect_url) {
                window.location.href = resp.data.redirect_url;
            } else {
                $fb.text('Buchung fehlgeschlagen: ' + (resp && resp.data ? resp.data : 'unbekannter Fehler'));
            }
        }).fail(function () {
            $fb.text('Netzwerkfehler. Bitte erneut versuchen.');
        });
    });
})(jQuery);
```

- [ ] **Step 9.6 — Verifikation**

PHP-Lint, JS-Syntaxcheck, manuell: `[dgptm_workshops]` auf einer Test-Seite einbauen, Workshop-Karten erscheinen, Dialog öffnet, Stripe-Redirect funktioniert (Test-Modus).

---

## Task 10 — End-to-End-Smoke-Test + Commit

- [ ] **Step 10.1 — CRM-Setup-Checkliste an Geschäftsstelle**

Erstellen unter `modules/business/workshop-booking/docs/CRM-SETUP.md` (NICHT der DGPTMSuite-docs-Ordner, sondern modul-intern):

```markdown
# CRM-Setup für Workshop-Booking

Vor V1-Go-Live müssen in Zoho CRM folgende Felder existieren:

## Modul `Veranstal_X_Contacts`
- `Quelle` (Picklist: Modul, Backstage; Default leer)
- `Anmelde_Status` (Picklist mit 8 Werten — siehe Spec 6.3)
- `Zahlungsstatus` (Picklist: Ausstehend, Bezahlt, Erstattet, Teilerstattet)
- `Stripe_Charge_ID` (Single Line Text)
- `Stripe_Session_ID` (Single Line Text)
- `Books_Invoice_ID` (Single Line Text)
- `Last_Sync_At` (Date/Time)

## Modul `DGfK_Events`
- `Storno_Frist_Tage` (Zahl, Default 42)
- `Anwesenheits_Schwelle_Prozent` (Zahl, Default 80)
- `EduGrant_Verfuegbar`, `EduGrant_Hoehe_EUR`, `EduGrant_Plaetze_Gesamt`, `EduGrant_Plaetze_Vergeben`
- `Verantwortliche_Person` (Lookup → Contacts)
- `Sprache` (Picklist DE/EN, Default DE)

## Bestehende Backstage-Records einmalig markieren
```sql
-- via Zoho Bulk-Update
UPDATE Veranstal_X_Contacts SET Quelle = 'Backstage' WHERE Quelle IS NULL;
```
```

- [ ] **Step 10.2 — Smoke-Test-Plan**

Erstellen unter `modules/business/workshop-booking/docs/SMOKE-TEST.md`:

```markdown
# Smoke-Test Phase 1

## Vorbereitung
- [ ] Modul aktiviert, Tabellen vorhanden (`SHOW TABLES LIKE 'wp_dgptm_workshop_%'`)
- [ ] CRM-Felder vorhanden (siehe CRM-SETUP.md)
- [ ] Stripe-Test-Key in `wp_options.dgptm_wsb_stripe_secret_key` eingetragen
- [ ] Stripe-Webhook-Secret in `wp_options.dgptm_wsb_stripe_webhook_secret`
- [ ] Webhook-Endpoint in Stripe-Dashboard auf `/wp-json/dgptm-workshop/v1/stripe-webhook` konfiguriert (Events: checkout.session.completed, checkout.session.expired, charge.refunded)
- [ ] Mind. ein Workshop in DGfK_Events mit `From_Date` in Zukunft, `Event_Type='Workshop'`

## Standard-Buchung
1. WordPress-Seite mit `[dgptm_workshops]` aufrufen → Karten erscheinen
2. „Jetzt buchen" → Dialog öffnet
3. Daten ausfüllen mit Test-E-Mail → Submit
4. Redirect auf Stripe-Test-Checkout
5. Mit Test-Karte 4242 4242 4242 4242 zahlen
6. Redirect auf `/buchung-bestaetigt/?dgptm_wsb=success`
7. Bestätigungs-Mail prüfen (mit ICS-Anhang)
8. CRM prüfen: neuer `Veranstal_X_Contacts`-Eintrag mit `Quelle=Modul`, `Anmelde_Status=Angemeldet`, `Zahlungsstatus=Bezahlt`
9. `wp_dgptm_workshop_sync_log` prüfen: 2 Einträge (booking_init, stripe_webhook)

## Backstage-Skip-Test
1. Manuell in CRM: `Veranstal_X_Contacts`-Eintrag mit `Quelle=Backstage` anlegen
2. Per WP-CLI: `wp shell` → `DGPTM_WSB_Sync_Coordinator::apply_intent(new DGPTM_WSB_Sync_Intent('BACKSTAGE_ID', 'Storniert', 'Erstattet', 'manual', [], 'test'))`
3. CRM-Eintrag bleibt UNVERÄNDERT
4. `wp_dgptm_workshop_sync_log` zeigt `error_code=source_skipped`

## Drift-Reconciliation
1. CRM manuell: vorhandene Modul-Buchung auf `Anmelde_Status=Storniert` setzen
2. WP-CLI: `wp cron event run dgptm_wsb_reconcile`
3. `wp_dgptm_workshop_drift_alerts` zeigt Alert `code=manual_storno_without_refund`

## Webhook-Signatur
1. Stripe-CLI: `stripe trigger checkout.session.completed`
2. Endpoint antwortet 200, sync_log bekommt Eintrag

## Pending-Cleanup
1. In `wp_dgptm_workshop_pending_bookings` einen Eintrag mit `stripe_session_expires_at` in der Vergangenheit anlegen
2. WP-CLI: `wp cron event run dgptm_wsb_pending_cleanup`
3. Eintrag verschwindet, sync_log zeigt Abgebrochen
```

- [ ] **Step 10.3 — Module-Bootstrap finalisieren**

Sicherstellen, dass `dgptm-workshop-booking.php` alle Klassen lädt + Singletons instanziiert. Nochmal `php -l` auf alle Dateien.

```bash
for f in $(find modules/business/workshop-booking/includes -name "class-*.php"); do
    /d/php/php -l "$f" || echo "FEHLER in $f"
done
```

- [ ] **Step 10.4 — Smoke-Test ausführen** (manuell, kein automatisierter Test)

Nach Aktivierung im Plesk-Test-Setup oder lokalem WP. Logs prüfen:

```bash
tail -f wp-content/debug.log
```

- [ ] **Step 10.5 — Commit**

```bash
git add modules/business/workshop-booking/
git add docs/superpowers/specs/2026-04-22-workshop-buchen-entscheidungsvorlage.md
git add docs/superpowers/plans/2026-04-30-workshop-booking-phase1.md
git commit -m "$(cat <<'EOF'
feat(workshop-booking): Phase 1 — Buchungsfluss + Status-Sync-Coordinator

Phase 1 des workshop-booking-Moduls (v0.3.0):

- Sync_Coordinator als Single Entry Point fuer alle CRM-Schreibzugriffe auf
  Veranstal_X_Contacts (Spec Abschnitt 4a)
- State-Machine mit den 8 Anmelde-Status und erlaubten Uebergaengen
- Hybrid-Sync: Stripe-Webhook (sofort) + Reconciliation-Cron (15 min)
  fuer Drift-Erkennung gegen Stripe und Books
- Backstage-Records (Quelle != 'Modul') werden konsequent geskippt;
  separate class-backstage-mirror.php entfaellt, weil Backstage ueber
  bestehende Zoho-Flows direkt schreibt
- Drei neue WP-Tabellen: sync_log (append-only Audit, AGB §6 Abs. 3),
  drift_alerts (kuratierter Alert-Stream), pending_bookings
- Booking-Service mit Capacity-Check, Contact-Lookup (4-Felder-E-Mail
  via crm-abruf), Stripe-Checkout-Session und Bestaetigungs-Mail mit
  ICS-Anhang
- Frontend: [dgptm_workshops] und [dgptm_workshops_success] Shortcodes;
  Designsprache aus modules/business/umfragen

Phase 2 (Tickets/QR/PDF), Phase 3 (Mitgliederbereich), Phase 4
(Bescheinigungen) folgen separat.

Refs: docs/superpowers/specs/2026-04-22-workshop-buchen-entscheidungsvorlage.md
Refs: docs/superpowers/plans/2026-04-30-workshop-booking-phase1.md

Co-Authored-By: Claude Opus 4.7 (1M context) <noreply@anthropic.com>
EOF
)"
```

---

## Self-Review

**Spec coverage check (kritische Spec-Abschnitte):**
- §4 Architektur — Bausteine 1, 2, 3, 5, 6, „Status-Sync" → Tasks 1–8 ✓
- §4a Sync-Architektur (komplett) → Tasks 2, 3, 8 ✓
- §4a.5 Backstage-Skip → Task 3 (Sync_Coordinator first check) ✓
- §5.1 Datenfluss → Tasks 5, 6, 7 ✓
- §5.3 Backstage-Behandlung → Task 3 (skip) ✓
- §6.2 Pflichtfelder → in CRM-Setup-Doku (Task 10.1) und durch Sync_Coordinator gesetzt ✓
- §6.3 8 Status-Werte → Task 2.5 (State_Machine) ✓
- §6.4 WP-Tabellen → Task 1 (Installer) ✓

**Phase-1-Scope-Cuts (bewusst nicht enthalten):**
- Ticketnummer (Präfix 99999), Ticket-PDF, QR — Phase 2
- Books-Rechnungs-Erzeugung — Phase 7 (Books_Status_Reader liest nur)
- Mitgliederbereich „Meine Tickets" — Phase 3
- Storno-UI, Übertragung — Phase 6
- Reminder 7d/1d — Phase 6
- Webinar-Anbindung, Aufzeichnungs-Verteiler — Phase 5
- Mehrsprachigkeit DE/EN — Phase 8 (Phase 1 nur DE)

**Placeholder-Scan:** keine TBD/TODO ohne konkreten Code; jeder Schritt enthält ausführbaren Code.

**Type/Naming-Konsistenz:**
- Klassen-Präfix `DGPTM_WSB_*` durchgehend
- Konstanten `S_ZAHLUNG_AUSSTEHEND` etc. wiederverwendet (State_Machine + Booking_Service + Webhook + Cron)
- Tabellen-Präfix `wp_dgptm_workshop_*` durchgehend
- Optionen-Präfix `dgptm_wsb_*`
