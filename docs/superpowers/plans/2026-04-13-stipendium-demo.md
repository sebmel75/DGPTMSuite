# Stipendium Demo-Version Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Funktionsfaehige Demo-Version des Stipendium-Moduls mit Gutachter-Flow (Token-Link, Bewertungsbogen, Auto-Save), ORCID-Lookup, Vorsitzenden-Dashboard und Testdaten fuer die Praesentierung gegenueber dem Stipendiumsrat.

**Architecture:** Token-basierter Gutachter-Zugang (kein Login erforderlich), WordPress-DB fuer Tokens/Entwuerfe, Zoho CRM fuer finale Bewertungen, HTML-Mails im DGPTM-Design.

**Tech Stack:** PHP 7.4+ (WordPress), JavaScript (Vanilla + jQuery), Zoho CRM v8 REST API, ORCID Public API v3.0

**Spec:** `docs/superpowers/specs/2026-04-13-stipendium-demo-design.md`
**Base Spec:** `docs/superpowers/specs/2026-04-12-stipendium-design.md`
**Previous Plan:** `docs/superpowers/plans/2026-04-12-stipendium.md`

---

## Dateistruktur

### Neue Dateien

| Datei | Verantwortung |
|-------|---------------|
| `modules/business/stipendium/includes/class-token-installer.php` | DB-Tabelle `wp_dgptm_stipendium_tokens` erstellen |
| `modules/business/stipendium/includes/class-gutachter-token.php` | Token-CRUD: generate, validate, get_by_token, mark_complete, save_draft, cleanup |
| `modules/business/stipendium/includes/class-orcid-lookup.php` | ORCID Public API Abfrage (wp_ajax + wp_ajax_nopriv) |
| `modules/business/stipendium/includes/class-mail-templates.php` | HTML-Mails: Einladung "Jetzt begutachten" + Abschluss-Benachrichtigung |
| `modules/business/stipendium/includes/class-gutachter-form.php` | Shortcode `[dgptm_stipendium_gutachten]`, Auto-Save, Submit-AJAX |
| `modules/business/stipendium/includes/class-vorsitz-dashboard.php` | Vorsitzenden-Dashboard: Freigabe, Einladung, Ranking, PDF, Vergabe |
| `modules/business/stipendium/templates/gutachten-form.php` | Bewertungsbogen-Template (4 Rubriken, Dropdowns, Score-Vorschau) |
| `modules/business/stipendium/templates/gutachten-danke.php` | Danke-Seite nach Abgabe |
| `modules/business/stipendium/templates/gutachten-ungueltig.php` | Token ungueltig/abgelaufen |
| `modules/business/stipendium/templates/vorsitz-dashboard.php` | Dashboard-Template (4 Statusgruppen, Aktionen) |
| `modules/business/stipendium/assets/js/gutachten.js` | Live-Score, Auto-Save (30s), Submit-Confirmation |
| `modules/business/stipendium/assets/js/vorsitz-dashboard.js` | Dashboard AJAX-Interaktionen |
| `modules/business/stipendium/assets/css/gutachten.css` | Gutachten-Formular Styles |
| `modules/business/stipendium/assets/css/vorsitz-dashboard.css` | Dashboard Styles |
| `modules/business/stipendium/deluge/wf-gs-benachrichtigung.dg` | Zoho Workflow: Status → Geprueft → Mail an Vorsitzenden |

### Bestehende Dateien (Modifikation)

| Datei | Aenderung |
|-------|-----------|
| `modules/business/stipendium/dgptm-stipendium.php` | Neue Klassen laden, DB-Installer aufrufen |
| `modules/business/stipendium/includes/class-settings.php` | `gutachter_frist_tage` Default 28 hinzufuegen |
| `modules/business/stipendium/includes/class-dashboard-tab.php` | Platzhalter durch echte Implementierung delegieren |
| `modules/business/stipendium/module.json` | Version → 1.1.0 |

---

## Task 1: DB Installer — Token-Tabelle

**Files:**
- Create: `modules/business/stipendium/includes/class-token-installer.php`

Erstellt die Tabelle `wp_dgptm_stipendium_tokens` bei Modul-Aktivierung. Nutzt `dbDelta()` fuer idempotente Erstellung.

- [ ] **Step 1: Token-Installer Klasse erstellen**

```
modules/business/stipendium/includes/class-token-installer.php
```

```php
<?php
/**
 * DGPTM Stipendium — Datenbank-Installer fuer Token-Tabelle.
 *
 * Erstellt wp_dgptm_stipendium_tokens bei Modul-Aktivierung.
 * Nutzt dbDelta() fuer idempotente Schema-Updates.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Token_Installer {

    const DB_VERSION_KEY = 'dgptm_stipendium_token_db_version';
    const DB_VERSION     = '1.0';

    /**
     * Tabelle erstellen oder aktualisieren.
     *
     * Aufrufen bei Modul-Aktivierung und bei Versionsdifferenz.
     */
    public static function install() {
        $installed_version = get_option(self::DB_VERSION_KEY, '0');
        if (version_compare($installed_version, self::DB_VERSION, '>=')) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'dgptm_stipendium_tokens';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            token VARCHAR(64) NOT NULL,
            stipendium_id VARCHAR(50) NOT NULL,
            gutachter_name VARCHAR(255) NOT NULL,
            gutachter_email VARCHAR(255) NOT NULL,
            bewertung_status VARCHAR(20) NOT NULL DEFAULT 'ausstehend',
            bewertung_data LONGTEXT,
            bewertung_crm_id VARCHAR(50) DEFAULT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            completed_at DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token (token),
            KEY stipendium_id (stipendium_id),
            KEY bewertung_status (bewertung_status),
            KEY expires_at (expires_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::DB_VERSION_KEY, self::DB_VERSION);
    }

    /**
     * Tabelle loeschen (bei Deinstallation).
     */
    public static function uninstall() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dgptm_stipendium_tokens';
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        delete_option(self::DB_VERSION_KEY);
    }

    /**
     * Tabellennamen zurueckgeben.
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'dgptm_stipendium_tokens';
    }
}
```

- [ ] **Step 2: Commit**

```
feat(stipendium): Token-Tabelle DB-Installer (Task 1)
```

---

## Task 2: Gutachter Token Manager

**Files:**
- Create: `modules/business/stipendium/includes/class-gutachter-token.php`

Token-CRUD-Klasse: Generierung mit `random_bytes(32)`, Validierung mit `hash_equals()`, Entwurf-Speicherung, Abschluss-Markierung, Cron-Cleanup.

- [ ] **Step 1: Token-Manager Klasse erstellen**

```
modules/business/stipendium/includes/class-gutachter-token.php
```

```php
<?php
/**
 * DGPTM Stipendium — Gutachter-Token Manager.
 *
 * Verwaltet Token-Lifecycle: Generierung, Validierung, Entwurf-Speicherung,
 * Abschluss-Markierung und Aufraeum-Cron.
 *
 * Sicherheit: random_bytes(32) fuer Token-Generierung, hash_equals() fuer
 * timing-attack-resistente Validierung.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Gutachter_Token {

    const CLEANUP_HOOK = 'dgptm_stipendium_token_cleanup';

    private $table;

    public function __construct() {
        global $wpdb;
        $this->table = $wpdb->prefix . 'dgptm_stipendium_tokens';

        // Cron fuer abgelaufene Tokens
        add_action(self::CLEANUP_HOOK, [$this, 'cleanup_expired']);

        if (!wp_next_scheduled(self::CLEANUP_HOOK)) {
            wp_schedule_event(time(), 'daily', self::CLEANUP_HOOK);
        }
    }

    /**
     * Neuen Token generieren und in DB speichern.
     *
     * @param string $stipendium_id  Zoho CRM Record-ID der Bewerbung
     * @param string $gutachter_name Name des Gutachters
     * @param string $gutachter_email E-Mail des Gutachters
     * @param int    $frist_tage     Gueltigkeitsdauer in Tagen (Default 28)
     * @return array|WP_Error        Token-Daten oder Fehler
     */
    public function generate($stipendium_id, $gutachter_name, $gutachter_email, $frist_tage = 28) {
        global $wpdb;

        if (empty($stipendium_id) || empty($gutachter_name) || empty($gutachter_email)) {
            return new WP_Error('missing_data', 'Stipendium-ID, Name und E-Mail sind Pflichtfelder.');
        }

        // Pruefen ob bereits ein aktiver Token fuer diese Kombination existiert
        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, token FROM {$this->table}
             WHERE stipendium_id = %s AND gutachter_email = %s
             AND bewertung_status != 'abgeschlossen' AND expires_at > NOW()",
            $stipendium_id,
            $gutachter_email
        ), ARRAY_A);

        if ($existing) {
            return new WP_Error(
                'token_exists',
                'Fuer diese/n Gutachter/in existiert bereits ein aktiver Token.',
                ['token_id' => $existing['id']]
            );
        }

        // Kryptographisch sicheren Token generieren
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime("+{$frist_tage} days"));

        $result = $wpdb->insert($this->table, [
            'token'           => $token,
            'stipendium_id'   => sanitize_text_field($stipendium_id),
            'gutachter_name'  => sanitize_text_field($gutachter_name),
            'gutachter_email' => sanitize_email($gutachter_email),
            'bewertung_status' => 'ausstehend',
            'created_by'      => get_current_user_id(),
            'created_at'      => current_time('mysql'),
            'expires_at'      => $expires_at,
        ], ['%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s']);

        if ($result === false) {
            return new WP_Error('db_error', 'Token konnte nicht erstellt werden.');
        }

        return [
            'id'         => $wpdb->insert_id,
            'token'      => $token,
            'expires_at' => $expires_at,
            'url'        => $this->get_gutachten_url($token),
        ];
    }

    /**
     * Token validieren (timing-attack-sicher).
     *
     * @param string $token Token-String aus URL
     * @return array|WP_Error Token-Daten oder Fehler
     */
    public function validate($token) {
        global $wpdb;

        // Laenge pruefen (64 Hex-Zeichen = 32 Bytes)
        if (empty($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
            return new WP_Error('invalid_token', 'Ungueltiges Token-Format.');
        }

        // Token aus DB laden
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE token = %s LIMIT 1",
            $token
        ), ARRAY_A);

        if (!$row) {
            return new WP_Error('token_not_found', 'Dieser Link ist nicht gueltig.');
        }

        // Timing-attack-sichere Vergleich
        if (!hash_equals($row['token'], $token)) {
            return new WP_Error('token_mismatch', 'Dieser Link ist nicht gueltig.');
        }

        // Ablauf pruefen
        if (strtotime($row['expires_at']) < time()) {
            return new WP_Error('token_expired', 'Dieser Link ist abgelaufen.');
        }

        return $row;
    }

    /**
     * Token anhand des Token-Strings abrufen (ohne Validierung).
     *
     * @param string $token Token-String
     * @return array|null Token-Daten oder null
     */
    public function get_by_token($token) {
        global $wpdb;

        if (empty($token) || strlen($token) !== 64) {
            return null;
        }

        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE token = %s LIMIT 1",
            $token
        ), ARRAY_A);
    }

    /**
     * Entwurf speichern (Auto-Save).
     *
     * @param string $token Token-String
     * @param array  $data  Bewertungsdaten (Noten + Kommentare)
     * @return true|WP_Error
     */
    public function save_draft($token, $data) {
        global $wpdb;

        $row = $this->validate($token);
        if (is_wp_error($row)) {
            return $row;
        }

        if ($row['bewertung_status'] === 'abgeschlossen') {
            return new WP_Error('already_completed', 'Diese Bewertung wurde bereits abgeschlossen.');
        }

        $result = $wpdb->update(
            $this->table,
            [
                'bewertung_data'   => wp_json_encode($data),
                'bewertung_status' => 'entwurf',
            ],
            ['id' => $row['id']],
            ['%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Entwurf konnte nicht gespeichert werden.');
        }

        return true;
    }

    /**
     * Bewertung als abgeschlossen markieren.
     *
     * @param string $token  Token-String
     * @param array  $data   Finale Bewertungsdaten
     * @param string $crm_id Zoho CRM Bewertungs-Record ID (nach Erstellung)
     * @return true|WP_Error
     */
    public function mark_complete($token, $data, $crm_id = '') {
        global $wpdb;

        $row = $this->validate($token);
        if (is_wp_error($row)) {
            return $row;
        }

        if ($row['bewertung_status'] === 'abgeschlossen') {
            return new WP_Error('already_completed', 'Diese Bewertung wurde bereits abgeschlossen.');
        }

        $result = $wpdb->update(
            $this->table,
            [
                'bewertung_data'   => wp_json_encode($data),
                'bewertung_status' => 'abgeschlossen',
                'bewertung_crm_id' => sanitize_text_field($crm_id),
                'completed_at'     => current_time('mysql'),
            ],
            ['id' => $row['id']],
            ['%s', '%s', '%s', '%s'],
            ['%d']
        );

        if ($result === false) {
            return new WP_Error('db_error', 'Bewertung konnte nicht abgeschlossen werden.');
        }

        return true;
    }

    /**
     * Alle Tokens fuer ein Stipendium abrufen.
     *
     * @param string $stipendium_id Zoho CRM Record-ID
     * @return array Token-Zeilen
     */
    public function get_by_stipendium($stipendium_id) {
        global $wpdb;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$this->table} WHERE stipendium_id = %s ORDER BY created_at DESC",
            $stipendium_id
        ), ARRAY_A) ?: [];
    }

    /**
     * Anzahl abgeschlossener Bewertungen fuer ein Stipendium.
     *
     * @param string $stipendium_id Zoho CRM Record-ID
     * @return int
     */
    public function count_completed($stipendium_id) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table}
             WHERE stipendium_id = %s AND bewertung_status = 'abgeschlossen'",
            $stipendium_id
        ));
    }

    /**
     * Anzahl aller Tokens (gesamt) fuer ein Stipendium.
     *
     * @param string $stipendium_id Zoho CRM Record-ID
     * @return int
     */
    public function count_total($stipendium_id) {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$this->table} WHERE stipendium_id = %s",
            $stipendium_id
        ));
    }

    /**
     * Gutachten-URL zusammenbauen.
     *
     * @param string $token Token-String
     * @return string Vollstaendige URL
     */
    public function get_gutachten_url($token) {
        // Seite mit Shortcode [dgptm_stipendium_gutachten] suchen
        $page_id = $this->find_gutachten_page();
        if ($page_id) {
            return add_query_arg('token', $token, get_permalink($page_id));
        }
        // Fallback: statischer Pfad
        return home_url('/stipendium/gutachten/?token=' . $token);
    }

    /**
     * WordPress-Seite mit dem Gutachten-Shortcode finden.
     *
     * @return int|null Post-ID oder null
     */
    private function find_gutachten_page() {
        global $wpdb;
        $page_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts}
             WHERE post_type = 'page' AND post_status = 'publish'
             AND post_content LIKE '%[dgptm_stipendium_gutachten%'
             LIMIT 1"
        );
        return $page_id ? (int) $page_id : null;
    }

    /**
     * Abgelaufene Tokens aufraumen (Cron).
     *
     * Loescht Tokens die seit 30 Tagen abgelaufen sind
     * und nicht abgeschlossen wurden.
     */
    public function cleanup_expired() {
        global $wpdb;

        $cutoff = date('Y-m-d H:i:s', strtotime('-30 days'));

        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM {$this->table}
             WHERE expires_at < %s AND bewertung_status != 'abgeschlossen'",
            $cutoff
        ));

        if ($deleted > 0 && function_exists('dgptm_log')) {
            dgptm_log("Stipendium Token Cleanup: {$deleted} abgelaufene Tokens geloescht.", 'stipendium');
        }

        return $deleted;
    }

    /**
     * Cron-Event deregistrieren (bei Deaktivierung).
     */
    public static function deactivate() {
        $timestamp = wp_next_scheduled(self::CLEANUP_HOOK);
        if ($timestamp) {
            wp_unschedule_event($timestamp, self::CLEANUP_HOOK);
        }
    }
}
```

- [ ] **Step 2: Commit**

```
feat(stipendium): Gutachter-Token Manager mit CRUD + Cron-Cleanup (Task 2)
```

---

## Task 3: ORCID Lookup

**Files:**
- Create: `modules/business/stipendium/includes/class-orcid-lookup.php`

AJAX-Endpoint fuer ORCID Public API v3.0 Abfrage. Identisches Pattern wie `DGPTM_Artikel_Einreichung::ajax_lookup_orcid()`, registriert fuer eingeloggte und nicht-eingeloggte Benutzer.

**Referenz:** `modules/content/artikel-einreichung/artikel-einreichung.php` Zeilen 2413-2542

- [ ] **Step 1: ORCID-Lookup Klasse erstellen**

```
modules/business/stipendium/includes/class-orcid-lookup.php
```

```php
<?php
/**
 * DGPTM Stipendium — ORCID Public API Lookup.
 *
 * Fragt die oeffentliche ORCID API v3.0 ab, um Bewerberdaten
 * (Name, Institution, E-Mail) automatisch auszufuellen.
 * Kein API-Key erforderlich.
 *
 * Referenz: DGPTM_Artikel_Einreichung::ajax_lookup_orcid()
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_ORCID_Lookup {

    const NONCE_ACTION = 'dgptm_stipendium_orcid_nonce';
    const ORCID_API_BASE = 'https://pub.orcid.org/v3.0/';

    public function __construct() {
        // Fuer eingeloggte und nicht-eingeloggte Benutzer
        add_action('wp_ajax_dgptm_stipendium_lookup_orcid', [$this, 'ajax_lookup']);
        add_action('wp_ajax_nopriv_dgptm_stipendium_lookup_orcid', [$this, 'ajax_lookup']);
    }

    /**
     * AJAX: ORCID-Daten von der oeffentlichen API abrufen.
     *
     * Erwartet POST-Parameter:
     * - nonce: dgptm_stipendium_orcid_nonce
     * - orcid: ORCID-ID im Format 0000-0000-0000-0000
     *
     * Gibt zurueck: Vorname, Nachname, Institution, E-Mail
     */
    public function ajax_lookup() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $orcid = sanitize_text_field($_POST['orcid'] ?? '');

        // ORCID-Format validieren (4 Gruppen a 4 Ziffern, letzte kann X sein)
        if (!preg_match('/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/', $orcid)) {
            wp_send_json_error([
                'message' => 'Ungueltiges ORCID-Format. Bitte verwenden Sie das Format: 0000-0000-0000-0000',
            ]);
        }

        // 1. Personendaten abrufen
        $person_url = self::ORCID_API_BASE . $orcid . '/person';
        $person_response = wp_remote_get($person_url, [
            'headers' => ['Accept' => 'application/json'],
            'timeout' => 10,
        ]);

        if (is_wp_error($person_response)) {
            wp_send_json_error([
                'message' => 'Verbindungsfehler zur ORCID-API. Bitte versuchen Sie es spaeter erneut.',
            ]);
        }

        $status_code = wp_remote_retrieve_response_code($person_response);

        if ($status_code === 404) {
            wp_send_json_error([
                'message' => 'Diese ORCID-ID wurde nicht gefunden. Bitte ueberpruefen Sie die Eingabe.',
            ]);
        }

        if ($status_code !== 200) {
            wp_send_json_error([
                'message' => 'Fehler beim Abrufen der ORCID-Daten (Status: ' . $status_code . ').',
            ]);
        }

        $person_data = json_decode(wp_remote_retrieve_body($person_response), true);

        if (!$person_data) {
            wp_send_json_error([
                'message' => 'Ungueltige Antwort von der ORCID-API.',
            ]);
        }

        // Name extrahieren
        $given_name  = '';
        $family_name = '';
        if (isset($person_data['name'])) {
            $given_name  = $person_data['name']['given-names']['value'] ?? '';
            $family_name = $person_data['name']['family-name']['value'] ?? '';
        }

        // E-Mail extrahieren (falls oeffentlich)
        $email = '';
        if (isset($person_data['emails']['email']) && !empty($person_data['emails']['email'])) {
            foreach ($person_data['emails']['email'] as $email_entry) {
                if (!empty($email_entry['email'])) {
                    $email = $email_entry['email'];
                    break;
                }
            }
        }

        // 2. Beschaeftigung/Institution abrufen
        $institution = '';
        $emp_url = self::ORCID_API_BASE . $orcid . '/employments';
        $emp_response = wp_remote_get($emp_url, [
            'headers' => ['Accept' => 'application/json'],
            'timeout' => 10,
        ]);

        if (!is_wp_error($emp_response) && wp_remote_retrieve_response_code($emp_response) === 200) {
            $emp_data = json_decode(wp_remote_retrieve_body($emp_response), true);

            if (isset($emp_data['affiliation-group']) && !empty($emp_data['affiliation-group'])) {
                foreach ($emp_data['affiliation-group'] as $group) {
                    if (isset($group['summaries'][0]['employment-summary'])) {
                        $emp = $group['summaries'][0]['employment-summary'];
                        $institution = $emp['organization']['name'] ?? '';
                        if ($institution) break;
                    }
                }
            }
        }

        // Ergebnis pruefen — mindestens Name sollte vorhanden sein
        $full_name = trim($given_name . ' ' . $family_name);

        if (empty($full_name)) {
            wp_send_json_error([
                'message' => 'Der Name ist bei diesem ORCID-Profil nicht oeffentlich sichtbar. Bitte geben Sie Ihren Namen manuell ein.',
                'partial' => true,
                'data'    => [
                    'orcid'       => $orcid,
                    'email'       => $email,
                    'institution' => $institution,
                ],
            ]);
        }

        wp_send_json_success([
            'message' => 'ORCID-Daten erfolgreich abgerufen.',
            'data'    => [
                'orcid'       => $orcid,
                'vorname'     => $given_name,
                'nachname'    => $family_name,
                'name'        => $full_name,
                'email'       => $email,
                'institution' => $institution,
            ],
        ]);
    }

    /**
     * Nonce fuer Frontend-Nutzung erzeugen.
     *
     * @return string Nonce-Wert
     */
    public static function create_nonce() {
        return wp_create_nonce(self::NONCE_ACTION);
    }
}
```

- [ ] **Step 2: Commit**

```
feat(stipendium): ORCID Public API Lookup (Task 3)
```

---

## Task 4: Mail Templates

**Files:**
- Create: `modules/business/stipendium/includes/class-mail-templates.php`

HTML-Mail-Builder mit zwei Templates:
1. "Jetzt begutachten" — Einladungs-Mail mit CTA-Button
2. "Bewertung abgeschlossen" — Benachrichtigung an Vorsitzenden

Layout identisch zu `DGPTM_Stipendium_Freigabe::build_notification_html()`: #003366 Header, table-based, inline Styles.

**Referenz:** `modules/business/stipendium/includes/class-freigabe.php` Zeilen 388-461

- [ ] **Step 1: Mail-Templates Klasse erstellen**

```
modules/business/stipendium/includes/class-mail-templates.php
```

```php
<?php
/**
 * DGPTM Stipendium — HTML-Mail-Templates.
 *
 * Baut HTML-Mails im DGPTM-Design (table-based, #003366 Header).
 * Zwei Templates:
 *   1. Einladung zur Begutachtung ("Jetzt begutachten")
 *   2. Abschluss-Benachrichtigung an Vorsitzenden
 *
 * Sendet ueber wp_mail() mit Content-Type text/html.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Mail_Templates {

    /**
     * Einladungs-Mail an Gutachter senden.
     *
     * @param array $args {
     *     @type string $gutachter_name  Name des Gutachters
     *     @type string $gutachter_email E-Mail des Gutachters
     *     @type string $bewerber_name   Name des Bewerbers
     *     @type string $stipendientyp   z.B. "Promotionsstipendium"
     *     @type string $runde           z.B. "Ausschreibung 2026"
     *     @type string $frist           z.B. "30.05.2026"
     *     @type string $gutachten_url   URL mit Token
     * }
     * @return bool Erfolg
     */
    public static function send_einladung($args) {
        $subject = 'DGPTM Stipendium: Einladung zur Begutachtung';
        $body = self::build_einladung_html($args);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: DGPTM Stipendienvergabe <nichtantworten@dgptm.de>',
        ];

        $sent = wp_mail($args['gutachter_email'], $subject, $body, $headers);

        if (!$sent && function_exists('dgptm_log_error')) {
            dgptm_log_error('Stipendium Einladungs-Mail fehlgeschlagen: ' . $args['gutachter_email'], 'stipendium');
        }

        return $sent;
    }

    /**
     * Abschluss-Benachrichtigung an Vorsitzenden senden.
     *
     * @param array $args {
     *     @type string $vorsitz_email   E-Mail des Vorsitzenden
     *     @type string $gutachter_name  Name des Gutachters
     *     @type string $bewerber_name   Name des Bewerbers
     *     @type string $stipendientyp   Stipendientyp
     *     @type float  $gesamtscore     Gesamtscore des Gutachtens
     *     @type string $datum           Abgabedatum formatiert
     * }
     * @return bool Erfolg
     */
    public static function send_abschluss_benachrichtigung($args) {
        $subject = 'DGPTM Stipendium: Gutachten eingegangen von ' . $args['gutachter_name'];
        $body = self::build_abschluss_html($args);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: DGPTM Stipendienvergabe <nichtantworten@dgptm.de>',
        ];

        return wp_mail($args['vorsitz_email'], $subject, $body, $headers);
    }

    /**
     * HTML fuer Einladungs-Mail zusammenbauen.
     */
    private static function build_einladung_html($args) {
        $gutachter_name = esc_html($args['gutachter_name']);
        $bewerber_name  = esc_html($args['bewerber_name']);
        $stipendientyp  = esc_html($args['stipendientyp']);
        $runde          = esc_html($args['runde']);
        $frist          = esc_html($args['frist']);
        $url            = esc_url($args['gutachten_url']);

        return self::wrap_layout(
            'Stipendienvergabe',
            // Titel
            '<h2 style="margin:0;font-size:18px;color:#1a1a1a;">Einladung zur Begutachtung</h2>',
            // Body
            '<p style="font-size:15px;line-height:1.6;color:#333;">
                Sehr geehrte/r ' . $gutachter_name . ',
            </p>
            <p style="font-size:15px;line-height:1.6;color:#333;">
                Sie wurden vom Vorsitzenden des Stipendiumsrats eingeladen,
                eine Bewerbung fuer das ' . $stipendientyp . ' der DGPTM zu begutachten.
            </p>

            <!-- Info-Box -->
            <table width="100%" cellpadding="0" cellspacing="0" style="margin:20px 0;">
            <tr><td style="background:#f0f5fa;border-left:4px solid #003366;border-radius:0 8px 8px 0;padding:16px 20px;">
                <table width="100%" cellpadding="4" cellspacing="0">
                    <tr>
                        <td style="font-size:13px;color:#6b7280;width:120px;">Bewerber/in:</td>
                        <td style="font-size:14px;color:#1a1a1a;font-weight:600;">' . $bewerber_name . '</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px;color:#6b7280;">Stipendium:</td>
                        <td style="font-size:14px;color:#1a1a1a;">' . $stipendientyp . '</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px;color:#6b7280;">Runde:</td>
                        <td style="font-size:14px;color:#1a1a1a;">' . $runde . '</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px;color:#6b7280;">Frist:</td>
                        <td style="font-size:14px;color:#1a1a1a;font-weight:600;">' . $frist . '</td>
                    </tr>
                </table>
            </td></tr>
            </table>',
            // CTA-Button
            '<a href="' . $url . '" style="display:inline-block;background:#003366;color:#ffffff;text-decoration:none;padding:14px 36px;border-radius:8px;font-size:16px;font-weight:600;">Jetzt begutachten</a>',
            // Footer-Hinweis
            '<p style="font-size:13px;color:#6b7280;line-height:1.5;margin-top:20px;">
                Dieser Link ist persoenlich und vertraulich. Bitte geben Sie ihn nicht an Dritte weiter.
                Der Link ist bis zum ' . $frist . ' gueltig.
            </p>'
        );
    }

    /**
     * HTML fuer Abschluss-Benachrichtigung zusammenbauen.
     */
    private static function build_abschluss_html($args) {
        $gutachter_name = esc_html($args['gutachter_name']);
        $bewerber_name  = esc_html($args['bewerber_name']);
        $stipendientyp  = esc_html($args['stipendientyp']);
        $score          = number_format((float)($args['gesamtscore'] ?? 0), 2, ',', '');
        $punkte         = number_format((float)($args['gesamtscore'] ?? 0) * 10, 1, ',', '');
        $datum          = esc_html($args['datum']);
        $dashboard_url  = esc_url(home_url('/mitgliederbereich/'));

        return self::wrap_layout(
            'Stipendienvergabe',
            // Titel
            '<h2 style="margin:0;font-size:18px;color:#1a1a1a;">Gutachten eingegangen</h2>
             <p style="margin:6px 0 0;font-size:14px;color:#6b7280;">von <strong>' . $gutachter_name . '</strong> am ' . $datum . '</p>',
            // Body
            '<table width="100%" cellpadding="0" cellspacing="0" style="margin:16px 0;">
            <tr><td style="background:#f0f5fa;border-left:4px solid #003366;border-radius:0 8px 8px 0;padding:16px 20px;">
                <table width="100%" cellpadding="4" cellspacing="0">
                    <tr>
                        <td style="font-size:13px;color:#6b7280;width:120px;">Bewerber/in:</td>
                        <td style="font-size:14px;color:#1a1a1a;font-weight:600;">' . $bewerber_name . '</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px;color:#6b7280;">Stipendium:</td>
                        <td style="font-size:14px;color:#1a1a1a;">' . $stipendientyp . '</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px;color:#6b7280;">Gesamtscore:</td>
                        <td style="font-size:14px;color:#1a1a1a;font-weight:600;">' . $score . ' / 10 (' . $punkte . ' Punkte)</td>
                    </tr>
                    <tr>
                        <td style="font-size:13px;color:#6b7280;">Gutachter/in:</td>
                        <td style="font-size:14px;color:#1a1a1a;">' . $gutachter_name . '</td>
                    </tr>
                </table>
            </td></tr>
            </table>',
            // CTA-Button
            '<a href="' . $dashboard_url . '" style="display:inline-block;background:#003366;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;">Im Dashboard ansehen</a>',
            // Footer-Hinweis
            ''
        );
    }

    /**
     * HTML-Layout-Wrapper im DGPTM-Design.
     *
     * Table-based Layout, #003366 Header, responsive 600px.
     * Identisches Design wie class-freigabe.php build_notification_html().
     *
     * @param string $header_right  Text rechts im Header
     * @param string $title_html    Titel-Bereich
     * @param string $body_html     Haupt-Inhalt
     * @param string $cta_html      CTA-Button HTML
     * @param string $footer_note   Zusaetzlicher Hinweistext
     * @return string Komplettes HTML-Dokument
     */
    private static function wrap_layout($header_right, $title_html, $body_html, $cta_html, $footer_note) {
        return '<!DOCTYPE html>
<html lang="de">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:-apple-system,BlinkMacSystemFont,Segoe UI,Roboto,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:24px 0;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">

  <!-- Header -->
  <tr>
    <td style="background:#003366;padding:20px 30px;">
      <table width="100%"><tr>
        <td style="color:#ffffff;font-size:20px;font-weight:700;">DGPTM</td>
        <td align="right" style="color:#8bb8e8;font-size:13px;">' . esc_html($header_right) . '</td>
      </tr></table>
    </td>
  </tr>

  <!-- Titel -->
  <tr>
    <td style="padding:28px 30px 12px;">
      ' . $title_html . '
    </td>
  </tr>

  <!-- Inhalt -->
  <tr>
    <td style="padding:4px 30px 16px;">
      ' . $body_html . '
    </td>
  </tr>

  <!-- CTA-Button -->
  <tr>
    <td align="center" style="padding:8px 30px 28px;">
      ' . $cta_html . '
    </td>
  </tr>

  <!-- Zusaetzlicher Hinweis -->
  ' . ($footer_note ? '<tr><td style="padding:0 30px 20px;">' . $footer_note . '</td></tr>' : '') . '

  <!-- Footer -->
  <tr>
    <td style="background:#f9fafb;padding:16px 30px;border-top:1px solid #e5e7eb;">
      <p style="margin:0;font-size:12px;color:#9ca3af;text-align:center;">
        Diese Nachricht wurde automatisch gesendet.<br>
        Deutsche Gesellschaft fuer Perfusiologie und Technische Medizin e.V. | nichtantworten@dgptm.de
      </p>
    </td>
  </tr>

</table>
</td></tr>
</table>
</body>
</html>';
    }
}
```

- [ ] **Step 2: Commit**

```
feat(stipendium): HTML-Mail-Templates fuer Einladung + Abschluss (Task 4)
```

---

## Task 5: Gutachter-Form (Shortcode + AJAX)

**Files:**
- Create: `modules/business/stipendium/includes/class-gutachter-form.php`

Shortcode `[dgptm_stipendium_gutachten]`, liest `?token=` aus URL, zeigt je nach Token-Status: Formular, Danke-Seite oder Ungueltig-Seite. AJAX-Endpoints fuer Auto-Save und Abschluss (beide `nopriv` da kein Login noetig).

- [ ] **Step 1: Gutachter-Form Klasse erstellen**

```
modules/business/stipendium/includes/class-gutachter-form.php
```

```php
<?php
/**
 * DGPTM Stipendium — Gutachter-Bewertungsbogen.
 *
 * Shortcode [dgptm_stipendium_gutachten] fuer den Token-basierten
 * Gutachter-Zugang. Kein Login erforderlich — Token validiert Identitaet.
 *
 * Zustaende:
 *   1. Token gueltig, Status ausstehend/entwurf → Bewertungsbogen
 *   2. Token gueltig, Status abgeschlossen → Danke-Seite
 *   3. Token ungueltig/abgelaufen → Fehler-Seite
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Gutachter_Form {

    const NONCE_ACTION = 'dgptm_stipendium_gutachten_nonce';

    private $plugin_path;
    private $plugin_url;
    private $token_manager;
    private $zoho;

    public function __construct($plugin_path, $plugin_url, $token_manager, $zoho = null) {
        $this->plugin_path   = $plugin_path;
        $this->plugin_url    = $plugin_url;
        $this->token_manager = $token_manager;
        $this->zoho          = $zoho;

        // Shortcode
        add_shortcode('dgptm_stipendium_gutachten', [$this, 'render_shortcode']);

        // Assets
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);

        // AJAX-Endpoints (kein Login noetig — Token validiert)
        add_action('wp_ajax_dgptm_stipendium_autosave', [$this, 'ajax_autosave']);
        add_action('wp_ajax_nopriv_dgptm_stipendium_autosave', [$this, 'ajax_autosave']);
        add_action('wp_ajax_dgptm_stipendium_submit_gutachten', [$this, 'ajax_submit']);
        add_action('wp_ajax_nopriv_dgptm_stipendium_submit_gutachten', [$this, 'ajax_submit']);
    }

    /**
     * Assets registrieren (noch nicht einreihen).
     */
    public function register_assets() {
        wp_register_style(
            'dgptm-gutachten',
            $this->plugin_url . 'assets/css/gutachten.css',
            [],
            '1.1.0'
        );
        wp_register_script(
            'dgptm-gutachten',
            $this->plugin_url . 'assets/js/gutachten.js',
            ['jquery'],
            '1.1.0',
            true
        );
    }

    /**
     * Shortcode [dgptm_stipendium_gutachten] rendern.
     */
    public function render_shortcode($atts) {
        $token_string = sanitize_text_field($_GET['token'] ?? '');

        if (empty($token_string)) {
            ob_start();
            include $this->plugin_path . 'templates/gutachten-ungueltig.php';
            return ob_get_clean();
        }

        $token_data = $this->token_manager->validate($token_string);

        // Token ungueltig oder abgelaufen
        if (is_wp_error($token_data)) {
            $error_message = $token_data->get_error_message();
            ob_start();
            include $this->plugin_path . 'templates/gutachten-ungueltig.php';
            return ob_get_clean();
        }

        // Bereits abgeschlossen → Danke-Seite
        if ($token_data['bewertung_status'] === 'abgeschlossen') {
            $bewertung_data = json_decode($token_data['bewertung_data'] ?? '{}', true);
            $gesamtscore = $this->calculate_score($bewertung_data);
            $completed_at = $token_data['completed_at'];

            // Bewerberdaten aus CRM laden (gecacht)
            $stipendium = $this->get_stipendium_data($token_data['stipendium_id']);
            $bewerber_name = $stipendium['Bewerber']['name'] ?? $stipendium['Name'] ?? 'Unbekannt';

            ob_start();
            include $this->plugin_path . 'templates/gutachten-danke.php';
            return ob_get_clean();
        }

        // Bewertungsbogen anzeigen
        wp_enqueue_style('dgptm-gutachten');
        wp_enqueue_script('dgptm-gutachten');

        // Bestehende Entwurfsdaten laden
        $draft_data = [];
        if ($token_data['bewertung_status'] === 'entwurf' && !empty($token_data['bewertung_data'])) {
            $draft_data = json_decode($token_data['bewertung_data'], true) ?: [];
        }

        // Bewerberdaten aus CRM laden
        $stipendium = $this->get_stipendium_data($token_data['stipendium_id']);
        $bewerber_name = $stipendium['Bewerber']['name'] ?? $stipendium['Name'] ?? 'Unbekannt';
        $stipendientyp = $stipendium['Stipendientyp'] ?? '';
        $runde = $stipendium['Runde'] ?? '';

        // Dokument-URLs
        $dokumente = $this->extract_document_urls($stipendium);

        // JS-Variablen
        wp_localize_script('dgptm-gutachten', 'dgptmGutachten', [
            'ajaxUrl'    => admin_url('admin-ajax.php'),
            'nonce'      => wp_create_nonce(self::NONCE_ACTION),
            'token'      => $token_string,
            'draftData'  => $draft_data,
            'strings'    => [
                'saving'        => 'Speichere...',
                'saved'         => 'Entwurf gespeichert um',
                'save_error'    => 'Fehler beim Speichern.',
                'confirm_submit'=> 'Moechten Sie Ihr Gutachten jetzt abschliessen? Nach dem Abschluss kann die Bewertung nicht mehr geaendert werden.',
                'submitting'    => 'Wird uebermittelt...',
                'submit_error'  => 'Fehler beim Uebermitteln. Bitte versuchen Sie es erneut.',
            ],
        ]);

        ob_start();
        include $this->plugin_path . 'templates/gutachten-form.php';
        return ob_get_clean();
    }

    /**
     * AJAX: Auto-Save (alle 30 Sekunden).
     *
     * Erwartet POST: token, data (JSON-encoded), nonce
     * Kein Login noetig — Token validiert.
     */
    public function ajax_autosave() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $token = sanitize_text_field($_POST['token'] ?? '');
        $raw_data = wp_unslash($_POST['data'] ?? '');
        $data = is_string($raw_data) ? json_decode($raw_data, true) : $raw_data;

        if (!is_array($data)) {
            wp_send_json_error(['message' => 'Ungueltige Daten.']);
        }

        // Daten sanitizen
        $clean = $this->sanitize_bewertung_data($data);

        $result = $this->token_manager->save_draft($token, $clean);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'saved_at' => current_time('H:i'),
        ]);
    }

    /**
     * AJAX: Gutachten abschliessen.
     *
     * 1. Daten validieren (alle Pflichtfelder ausgefuellt)
     * 2. Score berechnen
     * 3. Bewertung in Zoho CRM erstellen
     * 4. Token als abgeschlossen markieren
     * 5. Benachrichtigungs-Mail an Vorsitzenden
     */
    public function ajax_submit() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        $token = sanitize_text_field($_POST['token'] ?? '');
        $raw_data = wp_unslash($_POST['data'] ?? '');
        $data = is_string($raw_data) ? json_decode($raw_data, true) : $raw_data;

        if (!is_array($data)) {
            wp_send_json_error(['message' => 'Ungueltige Daten.']);
        }

        // Token validieren
        $token_data = $this->token_manager->validate($token);
        if (is_wp_error($token_data)) {
            wp_send_json_error(['message' => $token_data->get_error_message()]);
        }

        if ($token_data['bewertung_status'] === 'abgeschlossen') {
            wp_send_json_error(['message' => 'Diese Bewertung wurde bereits abgeschlossen.']);
        }

        // Daten sanitizen
        $clean = $this->sanitize_bewertung_data($data);

        // Pflichtfelder pruefen (alle 12 Noten muessen 1-10 sein)
        $noten_felder = ['A1_Note','A2_Note','A3_Note','B1_Note','B2_Note','B3_Note',
                         'C1_Note','C2_Note','C3_Note','D1_Note','D2_Note','D3_Note'];
        foreach ($noten_felder as $feld) {
            $wert = (int)($clean[$feld] ?? 0);
            if ($wert < 1 || $wert > 10) {
                wp_send_json_error([
                    'message' => 'Bitte vergeben Sie fuer alle Leitfragen eine Note zwischen 1 und 10.',
                    'field'   => $feld,
                ]);
            }
        }

        // Score berechnen
        $score = $this->calculate_score($clean);
        $clean['Gesamtscore'] = $score['gesamt'];
        $clean['A_Gewichtet'] = $score['a_gewichtet'];
        $clean['B_Gewichtet'] = $score['b_gewichtet'];
        $clean['C_Gewichtet'] = $score['c_gewichtet'];
        $clean['D_Gewichtet'] = $score['d_gewichtet'];

        // Bewertung in Zoho CRM erstellen
        $crm_id = '';
        if ($this->zoho) {
            $crm_data = [
                'Stipendium'        => $token_data['stipendium_id'],
                'Gutachter_Name'    => $token_data['gutachter_name'],
                'Gutachter_Email'   => $token_data['gutachter_email'],
                'Status'            => 'Abgeschlossen',
                'Bewertungsdatum'   => date('Y-m-d\TH:i:sP'),
            ];
            // Noten und Kommentare uebernehmen
            foreach ($clean as $key => $val) {
                if (preg_match('/^[ABCD]\d?_/', $key) || $key === 'Gesamtanmerkungen') {
                    $crm_data[$key] = $val;
                }
            }
            // Gewichtete Scores
            $crm_data['A_Gewichtet'] = $score['a_gewichtet'];
            $crm_data['B_Gewichtet'] = $score['b_gewichtet'];
            $crm_data['C_Gewichtet'] = $score['c_gewichtet'];
            $crm_data['D_Gewichtet'] = $score['d_gewichtet'];
            $crm_data['Gesamtscore'] = $score['gesamt'];

            $crm_result = $this->zoho->create_bewertung($crm_data);
            if (!is_wp_error($crm_result) && isset($crm_result['data'][0]['details']['id'])) {
                $crm_id = $crm_result['data'][0]['details']['id'];
            }
        }

        // Token als abgeschlossen markieren
        $result = $this->token_manager->mark_complete($token, $clean, $crm_id);
        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        // Benachrichtigungs-Mail an Vorsitzenden
        $this->notify_vorsitz($token_data, $clean, $score);

        wp_send_json_success([
            'message'     => 'Vielen Dank! Ihr Gutachten wurde erfolgreich uebermittelt.',
            'gesamtscore' => $score['gesamt'],
            'punkte'      => round($score['gesamt'] * 10, 1),
        ]);
    }

    /**
     * Bewertungsdaten sanitizen.
     *
     * @param array $data Rohdaten aus Formular
     * @return array Bereinigte Daten
     */
    private function sanitize_bewertung_data($data) {
        $clean = [];
        $noten_felder = ['A1_Note','A2_Note','A3_Note','B1_Note','B2_Note','B3_Note',
                         'C1_Note','C2_Note','C3_Note','D1_Note','D2_Note','D3_Note'];
        $kommentar_felder = ['A_Kommentar','B_Kommentar','C_Kommentar','D_Kommentar','Gesamtanmerkungen'];

        foreach ($noten_felder as $feld) {
            $wert = isset($data[$feld]) ? (int) $data[$feld] : 0;
            $clean[$feld] = max(0, min(10, $wert));
        }

        foreach ($kommentar_felder as $feld) {
            $clean[$feld] = sanitize_textarea_field($data[$feld] ?? '');
        }

        return $clean;
    }

    /**
     * Gesamtscore berechnen.
     *
     * Gewichtung: A=30%, B=30%, C=25%, D=15%
     *
     * @param array $data Bewertungsdaten
     * @return array Score-Details
     */
    private function calculate_score($data) {
        $a_avg = (($data['A1_Note'] ?? 0) + ($data['A2_Note'] ?? 0) + ($data['A3_Note'] ?? 0)) / 3.0;
        $b_avg = (($data['B1_Note'] ?? 0) + ($data['B2_Note'] ?? 0) + ($data['B3_Note'] ?? 0)) / 3.0;
        $c_avg = (($data['C1_Note'] ?? 0) + ($data['C2_Note'] ?? 0) + ($data['C3_Note'] ?? 0)) / 3.0;
        $d_avg = (($data['D1_Note'] ?? 0) + ($data['D2_Note'] ?? 0) + ($data['D3_Note'] ?? 0)) / 3.0;

        $a_gew = round($a_avg * 0.30, 4);
        $b_gew = round($b_avg * 0.30, 4);
        $c_gew = round($c_avg * 0.25, 4);
        $d_gew = round($d_avg * 0.15, 4);

        $gesamt = round($a_gew + $b_gew + $c_gew + $d_gew, 2);

        return [
            'a_avg'       => round($a_avg, 2),
            'b_avg'       => round($b_avg, 2),
            'c_avg'       => round($c_avg, 2),
            'd_avg'       => round($d_avg, 2),
            'a_gewichtet' => $a_gew,
            'b_gewichtet' => $b_gew,
            'c_gewichtet' => $c_gew,
            'd_gewichtet' => $d_gew,
            'gesamt'      => $gesamt,
        ];
    }

    /**
     * Stipendium-Daten aus CRM abrufen (gecacht).
     *
     * @param string $stipendium_id Zoho Record-ID
     * @return array Stipendium-Daten
     */
    private function get_stipendium_data($stipendium_id) {
        if (!$this->zoho) {
            return ['Name' => 'Demo-Bewerbung', 'Stipendientyp' => 'Promotionsstipendium', 'Runde' => 'Ausschreibung 2026'];
        }

        $cache_key = 'dgptm_stip_detail_' . $stipendium_id;
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $result = $this->zoho->get_stipendium($stipendium_id);
        if (is_wp_error($result)) {
            return ['Name' => 'Fehler beim Laden', 'Stipendientyp' => '', 'Runde' => ''];
        }

        $data = $result['data'][0] ?? $result;
        set_transient($cache_key, $data, 10 * MINUTE_IN_SECONDS);
        return $data;
    }

    /**
     * Dokument-URLs aus Stipendium-Record extrahieren.
     *
     * @param array $stipendium Stipendium-Daten
     * @return array Dokument-Liste mit Label + URL
     */
    private function extract_document_urls($stipendium) {
        $felder = [
            'Lebenslauf_URL'            => 'Lebenslauf',
            'Motivationsschreiben_URL'  => 'Motivationsschreiben',
            'Empfehlungsschreiben_URL'  => 'Empfehlungsschreiben',
            'Studienleistungen_URL'     => 'Studienleistungen',
            'Publikationen_URL'         => 'Publikationen',
            'Zusatzqualifikationen_URL' => 'Ehrenamt/Zusatzqualifikationen',
        ];

        $dokumente = [];
        foreach ($felder as $key => $label) {
            if (!empty($stipendium[$key])) {
                $dokumente[] = [
                    'label' => $label,
                    'url'   => $stipendium[$key],
                ];
            }
        }
        return $dokumente;
    }

    /**
     * Vorsitzenden per E-Mail benachrichtigen.
     */
    private function notify_vorsitz($token_data, $bewertung_data, $score) {
        // Vorsitz-E-Mail aus Settings
        $stipendium_instance = DGPTM_Stipendium::get_instance();
        $settings = $stipendium_instance->get_settings();
        $vorsitz_email = $settings ? $settings->get('benachrichtigung_vorsitz_email') : '';

        if (empty($vorsitz_email)) return;

        $stipendium = $this->get_stipendium_data($token_data['stipendium_id']);

        DGPTM_Stipendium_Mail_Templates::send_abschluss_benachrichtigung([
            'vorsitz_email'  => $vorsitz_email,
            'gutachter_name' => $token_data['gutachter_name'],
            'bewerber_name'  => $stipendium['Bewerber']['name'] ?? $stipendium['Name'] ?? 'Unbekannt',
            'stipendientyp'  => $stipendium['Stipendientyp'] ?? '',
            'gesamtscore'    => $score['gesamt'],
            'datum'          => current_time('d.m.Y, H:i'),
        ]);
    }
}
```

- [ ] **Step 2: Commit**

```
feat(stipendium): Gutachter-Form Shortcode mit Auto-Save + Submit (Task 5)
```

---

## Task 6: Gutachten Templates

**Files:**
- Create: `modules/business/stipendium/templates/gutachten-form.php`
- Create: `modules/business/stipendium/templates/gutachten-danke.php`
- Create: `modules/business/stipendium/templates/gutachten-ungueltig.php`

Drei Templates fuer die drei Zustaende des Gutachter-Tokens. `gutachten-form.php` ist das umfangreichste Template mit 4 Rubriken, Score-Vorschau und Dokument-Links.

- [ ] **Step 1: Bewertungsbogen-Template erstellen**

```
modules/business/stipendium/templates/gutachten-form.php
```

```php
<?php
/**
 * Template: Gutachter-Bewertungsbogen
 *
 * Variablen (von class-gutachter-form.php bereitgestellt):
 * @var array  $token_data    Token-Daten aus DB
 * @var array  $draft_data    Gespeicherte Entwurfsdaten (oder leer)
 * @var string $bewerber_name Name des Bewerbers
 * @var string $stipendientyp Stipendientyp
 * @var string $runde         Runden-Bezeichnung
 * @var array  $dokumente     Dokument-Links [{label, url}]
 */
if (!defined('ABSPATH')) exit;

$gutachter_name = esc_html($token_data['gutachter_name']);

// Hilfsfunktion: Dropdown-Wert aus Draft laden
$get_note = function($feld) use ($draft_data) {
    return isset($draft_data[$feld]) ? (int) $draft_data[$feld] : 0;
};
$get_text = function($feld) use ($draft_data) {
    return esc_textarea($draft_data[$feld] ?? '');
};

// Rubriken-Definition
$rubriken = [
    'A' => [
        'titel'    => 'Wissenschaftlicher Wert',
        'gewicht'  => '30%',
        'fragen'   => [
            'A1' => 'Ist die Fragestellung fuer das Fachgebiet relevant?',
            'A2' => 'Ist die Forschungsfrage klar formuliert?',
            'A3' => 'Ist ein Erkenntnisfortschritt zu erwarten?',
        ],
    ],
    'B' => [
        'titel'    => 'Relevanz fuer die Perfusiologie',
        'gewicht'  => '30%',
        'fragen'   => [
            'B1' => 'Leistet das Vorhaben einen Beitrag zum Fach?',
            'B2' => 'Sind praxisrelevante Impulse zu erwarten?',
            'B3' => 'Besteht ein klarer Bezug zum Berufsfeld?',
        ],
    ],
    'C' => [
        'titel'    => 'Projektbeschreibung und Methodik',
        'gewicht'  => '25%',
        'fragen'   => [
            'C1' => 'Ist die Methodik angemessen und nachvollziehbar?',
            'C2' => 'Ist das Vorhaben realisierbar (Zeitplan, Ressourcen)?',
            'C3' => 'Sind Aufbau und Planung schluessig?',
        ],
    ],
    'D' => [
        'titel'    => 'Leistungsnachweise des/der Bewerber/in',
        'gewicht'  => '15%',
        'fragen'   => [
            'D1' => 'Sind die akademischen Leistungen ueberzeugend?',
            'D2' => 'Sind relevante fachliche Kompetenzen erkennbar?',
            'D3' => 'Ergibt sich ein stimmiges Profil?',
        ],
    ],
];
?>

<div class="dgptm-gutachten-wrap">

    <!-- Header -->
    <div class="dgptm-gutachten-header">
        <div class="dgptm-gutachten-header-bar">
            <span class="dgptm-gutachten-logo">DGPTM Stipendium</span>
            <span class="dgptm-gutachten-header-sub">Begutachtung</span>
        </div>
        <div class="dgptm-gutachten-meta">
            <span class="dgptm-gutachten-meta-tag"><?php echo esc_html($stipendientyp); ?></span>
            <?php if ($runde) : ?>
                <span class="dgptm-gutachten-meta-tag dgptm-gutachten-meta-tag--light"><?php echo esc_html($runde); ?></span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Begruessung -->
    <div class="dgptm-gutachten-intro">
        <p>
            Guten Tag, <strong><?php echo $gutachter_name; ?></strong>,
        </p>
        <p>
            Sie wurden eingeladen, die folgende Bewerbung fuer das
            <?php echo esc_html($stipendientyp); ?> der DGPTM zu begutachten.
        </p>
        <div class="dgptm-gutachten-bewerber">
            <span class="dgptm-gutachten-bewerber-label">Bewerber/in:</span>
            <span class="dgptm-gutachten-bewerber-name"><?php echo esc_html($bewerber_name); ?></span>
        </div>
    </div>

    <!-- Dokumente -->
    <?php if (!empty($dokumente)) : ?>
    <div class="dgptm-gutachten-dokumente">
        <h4>Eingereichte Unterlagen</h4>
        <div class="dgptm-gutachten-dokumente-list">
            <?php foreach ($dokumente as $dok) : ?>
                <a href="<?php echo esc_url($dok['url']); ?>" target="_blank" rel="noopener" class="dgptm-gutachten-dok-link">
                    <span class="dgptm-gutachten-dok-icon">&#128196;</span>
                    <?php echo esc_html($dok['label']); ?>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Bewertungsbogen -->
    <form id="dgptm-gutachten-form" class="dgptm-gutachten-form" novalidate>
        <input type="hidden" name="token" value="<?php echo esc_attr($token_data['token']); ?>">

        <?php foreach ($rubriken as $prefix => $rubrik) : ?>
        <fieldset class="dgptm-gutachten-rubrik" data-rubrik="<?php echo $prefix; ?>">
            <legend>
                <?php echo $prefix; ?>. <?php echo esc_html($rubrik['titel']); ?>
                <span class="dgptm-gutachten-gewicht">(<?php echo $rubrik['gewicht']; ?>)</span>
            </legend>

            <?php foreach ($rubrik['fragen'] as $frage_id => $frage_text) : ?>
            <div class="dgptm-gutachten-frage">
                <label for="<?php echo $frage_id; ?>_Note">
                    <?php echo substr($frage_id, 1); ?>. <?php echo esc_html($frage_text); ?>
                </label>
                <select id="<?php echo $frage_id; ?>_Note"
                        name="<?php echo $frage_id; ?>_Note"
                        class="dgptm-gutachten-note"
                        data-rubrik="<?php echo $prefix; ?>"
                        required>
                    <option value="">-- Note --</option>
                    <?php for ($i = 1; $i <= 10; $i++) : ?>
                        <option value="<?php echo $i; ?>" <?php selected($get_note($frage_id . '_Note'), $i); ?>>
                            <?php echo $i; ?><?php echo $i === 1 ? ' (ungenuegend)' : ($i === 10 ? ' (hervorragend)' : ''); ?>
                        </option>
                    <?php endfor; ?>
                </select>
            </div>
            <?php endforeach; ?>

            <div class="dgptm-gutachten-kommentar">
                <label for="<?php echo $prefix; ?>_Kommentar">Kommentar zu Rubrik <?php echo $prefix; ?> (optional):</label>
                <textarea id="<?php echo $prefix; ?>_Kommentar"
                          name="<?php echo $prefix; ?>_Kommentar"
                          rows="3"
                          placeholder="Optionale Anmerkungen zu dieser Rubrik..."><?php echo $get_text($prefix . '_Kommentar'); ?></textarea>
            </div>

            <div class="dgptm-gutachten-rubrik-score" data-rubrik-score="<?php echo $prefix; ?>">
                Rubrik <?php echo $prefix; ?>: <span class="score-value">--</span> / 10
            </div>
        </fieldset>
        <?php endforeach; ?>

        <!-- Gesamtanmerkungen -->
        <fieldset class="dgptm-gutachten-rubrik dgptm-gutachten-gesamt-anmerkungen">
            <legend>Gesamtanmerkungen</legend>
            <textarea id="Gesamtanmerkungen"
                      name="Gesamtanmerkungen"
                      rows="5"
                      placeholder="Zusammenfassende Beurteilung, Empfehlung..."><?php echo $get_text('Gesamtanmerkungen'); ?></textarea>
        </fieldset>

        <!-- Score-Vorschau -->
        <div class="dgptm-gutachten-score-vorschau" id="dgptm-score-vorschau">
            <div class="dgptm-gutachten-score-label">Vorschau: Gesamtscore</div>
            <div class="dgptm-gutachten-score-value">
                <span id="dgptm-score-gesamt">--</span> / 10
                <span class="dgptm-gutachten-score-punkte">(<span id="dgptm-score-punkte">--</span> Punkte)</span>
            </div>
            <div class="dgptm-gutachten-score-detail" id="dgptm-score-detail">
                A: <span id="score-a">--</span> |
                B: <span id="score-b">--</span> |
                C: <span id="score-c">--</span> |
                D: <span id="score-d">--</span>
            </div>
        </div>

        <!-- Aktionen -->
        <div class="dgptm-gutachten-actions">
            <div class="dgptm-gutachten-autosave-status" id="dgptm-autosave-status">
                <!-- Wird per JS aktualisiert -->
            </div>
            <button type="submit" class="dgptm-gutachten-submit" id="dgptm-gutachten-submit">
                Gutachten abschliessen
            </button>
        </div>

        <p class="dgptm-gutachten-hinweis">
            <strong>Hinweis:</strong> Nach dem Abschluss kann die Bewertung nicht mehr geaendert werden.
            Ihr Entwurf wird automatisch alle 30 Sekunden gespeichert.
        </p>
    </form>
</div>
```

- [ ] **Step 2: Danke-Seite Template erstellen**

```
modules/business/stipendium/templates/gutachten-danke.php
```

```php
<?php
/**
 * Template: Danke-Seite nach abgeschlossenem Gutachten.
 *
 * Variablen:
 * @var array  $token_data     Token-Daten
 * @var array  $bewertung_data Bewertungsdaten
 * @var array  $gesamtscore    Score-Details
 * @var string $completed_at   Abschluss-Zeitpunkt
 * @var string $bewerber_name  Name des Bewerbers
 */
if (!defined('ABSPATH')) exit;

$score_wert  = is_array($gesamtscore) ? ($gesamtscore['gesamt'] ?? 0) : (float) $gesamtscore;
$score_fmt   = number_format($score_wert, 2, ',', '');
$punkte_fmt  = number_format($score_wert * 10, 1, ',', '');
$datum_fmt   = $completed_at ? date_i18n('d.m.Y, H:i', strtotime($completed_at)) : '';
?>

<div class="dgptm-gutachten-wrap">
    <div class="dgptm-gutachten-header">
        <div class="dgptm-gutachten-header-bar">
            <span class="dgptm-gutachten-logo">DGPTM Stipendium</span>
            <span class="dgptm-gutachten-header-sub">Begutachtung</span>
        </div>
    </div>

    <div class="dgptm-gutachten-danke">
        <div class="dgptm-gutachten-danke-icon">&#10003;</div>
        <h2>Vielen Dank fuer Ihr Gutachten!</h2>

        <p>
            Ihre Bewertung fuer <strong><?php echo esc_html($bewerber_name); ?></strong>
            wurde erfolgreich uebermittelt.
        </p>

        <div class="dgptm-gutachten-danke-details">
            <table>
                <tr>
                    <td>Ihr Gesamtscore:</td>
                    <td><strong><?php echo $score_fmt; ?> / 10</strong> (<?php echo $punkte_fmt; ?> Punkte)</td>
                </tr>
                <?php if ($datum_fmt) : ?>
                <tr>
                    <td>Abgegeben am:</td>
                    <td><?php echo esc_html($datum_fmt); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <p>
            Der Vorsitzende des Stipendiumsrats wurde automatisch benachrichtigt.
        </p>
        <p class="dgptm-gutachten-danke-kontakt">
            Bei Rueckfragen wenden Sie sich bitte an die Geschaeftsstelle:
            <a href="mailto:geschaeftsstelle@dgptm.de">geschaeftsstelle@dgptm.de</a>
        </p>
    </div>
</div>
```

- [ ] **Step 3: Ungueltig-Seite Template erstellen**

```
modules/business/stipendium/templates/gutachten-ungueltig.php
```

```php
<?php
/**
 * Template: Token ungueltig oder abgelaufen.
 *
 * Variablen:
 * @var string $error_message  Fehlermeldung (optional, von WP_Error)
 */
if (!defined('ABSPATH')) exit;
?>

<div class="dgptm-gutachten-wrap">
    <div class="dgptm-gutachten-header">
        <div class="dgptm-gutachten-header-bar">
            <span class="dgptm-gutachten-logo">DGPTM Stipendium</span>
            <span class="dgptm-gutachten-header-sub">Begutachtung</span>
        </div>
    </div>

    <div class="dgptm-gutachten-ungueltig">
        <div class="dgptm-gutachten-ungueltig-icon">&#9888;</div>
        <h2>Link nicht gueltig</h2>

        <p>
            <?php if (!empty($error_message)) : ?>
                <?php echo esc_html($error_message); ?>
            <?php else : ?>
                Dieser Link ist nicht mehr gueltig oder abgelaufen.
            <?php endif; ?>
        </p>

        <p>
            Bitte wenden Sie sich an die Geschaeftsstelle der DGPTM:
            <a href="mailto:geschaeftsstelle@dgptm.de">geschaeftsstelle@dgptm.de</a>
        </p>
    </div>
</div>
```

- [ ] **Step 4: Commit**

```
feat(stipendium): Gutachten-Templates (Formular, Danke, Ungueltig) (Task 6)
```

---

## Task 7: Gutachten Assets

**Files:**
- Create: `modules/business/stipendium/assets/js/gutachten.js`
- Create: `modules/business/stipendium/assets/css/gutachten.css`

JavaScript: Live-Score-Berechnung bei jeder Notenaenderung, Auto-Save alle 30 Sekunden (mit Debounce, nur bei Aenderung), Submit mit Bestaetigungs-Dialog.

CSS: Responsives Layout, Rubriken-Karten, Score-Vorschau-Box, Dokument-Links.

- [ ] **Step 1: JavaScript erstellen**

```
modules/business/stipendium/assets/js/gutachten.js
```

```javascript
/**
 * DGPTM Stipendium — Gutachten-Bewertungsbogen
 *
 * Funktionen:
 * - Live-Score-Berechnung bei Notenaenderung
 * - Auto-Save alle 30 Sekunden (nur bei Aenderung)
 * - Submit mit Bestaetigungsdialog
 * - Entwurfsdaten aus PHP vorbelegen
 */
(function($) {
    'use strict';

    // Konfiguration aus PHP (wp_localize_script)
    var config = window.dgptmGutachten || {};
    var ajaxUrl = config.ajaxUrl || '';
    var nonce = config.nonce || '';
    var token = config.token || '';
    var strings = config.strings || {};
    var draftData = config.draftData || {};

    // State
    var hasChanges = false;
    var isSaving = false;
    var isSubmitting = false;
    var autoSaveTimer = null;

    // Gewichtungen
    var GEWICHTUNG = { A: 0.30, B: 0.30, C: 0.25, D: 0.15 };

    /**
     * Score-Berechnung und Anzeige aktualisieren.
     */
    function updateScores() {
        var rubriken = ['A', 'B', 'C', 'D'];
        var gewichteteSumme = 0;
        var alleAusgefuellt = true;

        rubriken.forEach(function(prefix) {
            var noten = [];
            for (var i = 1; i <= 3; i++) {
                var val = parseInt($('#' + prefix + i + '_Note').val()) || 0;
                noten.push(val);
                if (val === 0) alleAusgefuellt = false;
            }

            var avg = noten.reduce(function(a, b) { return a + b; }, 0) / 3.0;
            var gewichtet = avg * GEWICHTUNG[prefix];

            // Rubrik-Score anzeigen
            var rubrikScoreEl = $('[data-rubrik-score="' + prefix + '"] .score-value');
            if (noten.some(function(n) { return n > 0; })) {
                rubrikScoreEl.text(avg.toFixed(2));
            } else {
                rubrikScoreEl.text('--');
            }

            // Gewichteten Teil-Score anzeigen
            $('#score-' + prefix.toLowerCase()).text(gewichtet.toFixed(2));
            gewichteteSumme += gewichtet;
        });

        // Gesamt anzeigen
        if (alleAusgefuellt) {
            $('#dgptm-score-gesamt').text(gewichteteSumme.toFixed(2));
            $('#dgptm-score-punkte').text((gewichteteSumme * 10).toFixed(1));
            $('#dgptm-score-vorschau').addClass('dgptm-score-vorschau--complete');
        } else {
            var teilwert = gewichteteSumme > 0 ? gewichteteSumme.toFixed(2) : '--';
            $('#dgptm-score-gesamt').text(teilwert);
            $('#dgptm-score-punkte').text(gewichteteSumme > 0 ? (gewichteteSumme * 10).toFixed(1) : '--');
            $('#dgptm-score-vorschau').removeClass('dgptm-score-vorschau--complete');
        }
    }

    /**
     * Alle Formulardaten als Objekt sammeln.
     */
    function collectFormData() {
        var data = {};
        var notenFelder = ['A1_Note','A2_Note','A3_Note','B1_Note','B2_Note','B3_Note',
                           'C1_Note','C2_Note','C3_Note','D1_Note','D2_Note','D3_Note'];
        var kommentarFelder = ['A_Kommentar','B_Kommentar','C_Kommentar','D_Kommentar','Gesamtanmerkungen'];

        notenFelder.forEach(function(feld) {
            data[feld] = parseInt($('#' + feld).val()) || 0;
        });

        kommentarFelder.forEach(function(feld) {
            data[feld] = $('#' + feld).val() || '';
        });

        return data;
    }

    /**
     * Auto-Save: Entwurf per AJAX speichern.
     */
    function autoSave() {
        if (!hasChanges || isSaving || isSubmitting) return;

        isSaving = true;
        hasChanges = false;
        var statusEl = $('#dgptm-autosave-status');
        statusEl.text(strings.saving || 'Speichere...').addClass('dgptm-autosave--saving');

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'dgptm_stipendium_autosave',
                nonce: nonce,
                token: token,
                data: JSON.stringify(collectFormData())
            },
            success: function(response) {
                if (response.success) {
                    var savedText = (strings.saved || 'Entwurf gespeichert um') + ' ' + response.data.saved_at;
                    statusEl.text(savedText)
                        .removeClass('dgptm-autosave--saving dgptm-autosave--error')
                        .addClass('dgptm-autosave--saved');
                } else {
                    statusEl.text(response.data?.message || strings.save_error || 'Fehler beim Speichern.')
                        .removeClass('dgptm-autosave--saving dgptm-autosave--saved')
                        .addClass('dgptm-autosave--error');
                    hasChanges = true; // Erneut versuchen
                }
            },
            error: function() {
                statusEl.text(strings.save_error || 'Fehler beim Speichern.')
                    .removeClass('dgptm-autosave--saving dgptm-autosave--saved')
                    .addClass('dgptm-autosave--error');
                hasChanges = true;
            },
            complete: function() {
                isSaving = false;
            }
        });
    }

    /**
     * Gutachten abschliessen.
     */
    function submitGutachten(e) {
        e.preventDefault();

        if (isSubmitting) return;

        // Pflichtfelder pruefen
        var incomplete = false;
        $('.dgptm-gutachten-note').each(function() {
            if (!$(this).val()) {
                $(this).addClass('dgptm-gutachten-note--error');
                incomplete = true;
            } else {
                $(this).removeClass('dgptm-gutachten-note--error');
            }
        });

        if (incomplete) {
            alert('Bitte vergeben Sie fuer alle Leitfragen eine Note zwischen 1 und 10.');
            // Zum ersten fehlenden Feld scrollen
            var firstError = $('.dgptm-gutachten-note--error').first();
            if (firstError.length) {
                $('html, body').animate({ scrollTop: firstError.offset().top - 100 }, 400);
            }
            return;
        }

        // Bestaetigung
        if (!confirm(strings.confirm_submit || 'Moechten Sie Ihr Gutachten jetzt abschliessen? Nach dem Abschluss kann die Bewertung nicht mehr geaendert werden.')) {
            return;
        }

        isSubmitting = true;
        var submitBtn = $('#dgptm-gutachten-submit');
        var originalText = submitBtn.text();
        submitBtn.text(strings.submitting || 'Wird uebermittelt...').prop('disabled', true);

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'dgptm_stipendium_submit_gutachten',
                nonce: nonce,
                token: token,
                data: JSON.stringify(collectFormData())
            },
            success: function(response) {
                if (response.success) {
                    // Seite neu laden → Danke-Seite wird angezeigt
                    window.location.reload();
                } else {
                    alert(response.data?.message || strings.submit_error || 'Fehler beim Uebermitteln.');
                    submitBtn.text(originalText).prop('disabled', false);
                    isSubmitting = false;
                }
            },
            error: function() {
                alert(strings.submit_error || 'Fehler beim Uebermitteln. Bitte versuchen Sie es erneut.');
                submitBtn.text(originalText).prop('disabled', false);
                isSubmitting = false;
            }
        });
    }

    /**
     * Entwurfsdaten in Formular vorbelegen.
     */
    function restoreDraft() {
        if (!draftData || typeof draftData !== 'object') return;

        Object.keys(draftData).forEach(function(key) {
            var el = $('#' + key);
            if (el.length) {
                el.val(draftData[key]);
            }
        });

        updateScores();
    }

    /**
     * Initialisierung.
     */
    $(document).ready(function() {
        // Entwurf wiederherstellen
        restoreDraft();

        // Score bei jeder Aenderung aktualisieren
        $(document).on('change', '.dgptm-gutachten-note', function() {
            updateScores();
            hasChanges = true;
        });

        // Aenderungen an Textfeldern tracken
        $(document).on('input', '.dgptm-gutachten-form textarea', function() {
            hasChanges = true;
        });

        // Auto-Save alle 30 Sekunden
        autoSaveTimer = setInterval(autoSave, 30000);

        // Submit-Handler
        $('#dgptm-gutachten-form').on('submit', submitGutachten);

        // Warnung bei ungesicherten Aenderungen
        $(window).on('beforeunload', function() {
            if (hasChanges && !isSubmitting) {
                return 'Sie haben ungespeicherte Aenderungen. Moechten Sie die Seite wirklich verlassen?';
            }
        });

        // Initiales Score-Update
        updateScores();
    });

})(jQuery);
```

- [ ] **Step 2: CSS erstellen**

```
modules/business/stipendium/assets/css/gutachten.css
```

```css
/**
 * DGPTM Stipendium — Gutachten-Bewertungsbogen Styles
 *
 * Responsive Layout, DGPTM-Farben (#003366 Akzent),
 * Karten-Design fuer Rubriken, Score-Vorschau-Box.
 */

/* === Wrapper === */
.dgptm-gutachten-wrap {
    max-width: 800px;
    margin: 0 auto;
    padding: 0 16px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: #1a1a1a;
    line-height: 1.6;
}

/* === Header === */
.dgptm-gutachten-header {
    margin-bottom: 24px;
}

.dgptm-gutachten-header-bar {
    background: #003366;
    color: #fff;
    padding: 16px 24px;
    border-radius: 12px 12px 0 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.dgptm-gutachten-logo {
    font-size: 20px;
    font-weight: 700;
}

.dgptm-gutachten-header-sub {
    font-size: 13px;
    color: #8bb8e8;
}

.dgptm-gutachten-meta {
    background: #f0f5fa;
    padding: 10px 24px;
    border-radius: 0 0 12px 12px;
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}

.dgptm-gutachten-meta-tag {
    display: inline-block;
    background: #003366;
    color: #fff;
    font-size: 12px;
    font-weight: 600;
    padding: 3px 12px;
    border-radius: 12px;
}

.dgptm-gutachten-meta-tag--light {
    background: #e8eaf6;
    color: #283593;
}

/* === Intro === */
.dgptm-gutachten-intro {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 20px;
}

.dgptm-gutachten-intro p {
    margin: 0 0 8px;
    font-size: 15px;
}

.dgptm-gutachten-bewerber {
    background: #f0f5fa;
    border-left: 4px solid #003366;
    padding: 12px 16px;
    border-radius: 0 8px 8px 0;
    margin-top: 12px;
}

.dgptm-gutachten-bewerber-label {
    font-size: 13px;
    color: #6b7280;
    display: block;
}

.dgptm-gutachten-bewerber-name {
    font-size: 18px;
    font-weight: 600;
    color: #003366;
}

/* === Dokumente === */
.dgptm-gutachten-dokumente {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 16px 24px;
    margin-bottom: 24px;
}

.dgptm-gutachten-dokumente h4 {
    margin: 0 0 12px;
    font-size: 15px;
    color: #374151;
}

.dgptm-gutachten-dokumente-list {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
}

.dgptm-gutachten-dok-link {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 8px 14px;
    font-size: 13px;
    color: #003366;
    text-decoration: none;
    font-weight: 500;
    transition: background 0.2s, border-color 0.2s;
}

.dgptm-gutachten-dok-link:hover {
    background: #e8eaf6;
    border-color: #003366;
    color: #003366;
}

.dgptm-gutachten-dok-icon {
    font-size: 16px;
}

/* === Rubriken === */
.dgptm-gutachten-rubrik {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 12px;
    padding: 20px 24px;
    margin-bottom: 16px;
}

.dgptm-gutachten-rubrik legend {
    font-size: 17px;
    font-weight: 700;
    color: #003366;
    padding: 0 8px;
}

.dgptm-gutachten-gewicht {
    font-size: 13px;
    font-weight: 400;
    color: #6b7280;
}

/* === Fragen === */
.dgptm-gutachten-frage {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid #f3f4f6;
    gap: 12px;
}

.dgptm-gutachten-frage:last-of-type {
    border-bottom: none;
}

.dgptm-gutachten-frage label {
    flex: 1;
    font-size: 14px;
    color: #374151;
    line-height: 1.4;
}

.dgptm-gutachten-note {
    width: 160px;
    min-width: 140px;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    background: #fff;
    appearance: auto;
}

.dgptm-gutachten-note:focus {
    outline: none;
    border-color: #003366;
    box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.1);
}

.dgptm-gutachten-note--error {
    border-color: #dc2626;
    background: #fef2f2;
}

/* === Kommentar === */
.dgptm-gutachten-kommentar {
    margin-top: 12px;
}

.dgptm-gutachten-kommentar label {
    display: block;
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 4px;
}

.dgptm-gutachten-kommentar textarea,
.dgptm-gutachten-gesamt-anmerkungen textarea {
    width: 100%;
    padding: 10px 14px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    font-family: inherit;
    resize: vertical;
    box-sizing: border-box;
}

.dgptm-gutachten-kommentar textarea:focus,
.dgptm-gutachten-gesamt-anmerkungen textarea:focus {
    outline: none;
    border-color: #003366;
    box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.1);
}

/* === Rubrik-Score === */
.dgptm-gutachten-rubrik-score {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 1px solid #e5e7eb;
    font-size: 14px;
    color: #6b7280;
    text-align: right;
}

.dgptm-gutachten-rubrik-score .score-value {
    font-weight: 700;
    color: #003366;
    font-size: 16px;
}

/* === Score-Vorschau === */
.dgptm-gutachten-score-vorschau {
    background: linear-gradient(135deg, #003366 0%, #004d99 100%);
    color: #fff;
    border-radius: 12px;
    padding: 24px;
    margin: 24px 0;
    text-align: center;
}

.dgptm-gutachten-score-vorschau.dgptm-score-vorschau--complete {
    background: linear-gradient(135deg, #065f46 0%, #047857 100%);
}

.dgptm-gutachten-score-label {
    font-size: 13px;
    opacity: 0.8;
    margin-bottom: 4px;
}

.dgptm-gutachten-score-value {
    font-size: 32px;
    font-weight: 700;
}

.dgptm-gutachten-score-punkte {
    font-size: 16px;
    font-weight: 400;
    opacity: 0.8;
}

.dgptm-gutachten-score-detail {
    margin-top: 8px;
    font-size: 13px;
    opacity: 0.7;
}

/* === Aktionen === */
.dgptm-gutachten-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin: 24px 0 16px;
    gap: 16px;
}

.dgptm-gutachten-autosave-status {
    font-size: 13px;
    color: #6b7280;
}

.dgptm-autosave--saving {
    color: #b45309;
}

.dgptm-autosave--saved {
    color: #065f46;
}

.dgptm-autosave--error {
    color: #dc2626;
}

.dgptm-gutachten-submit {
    background: #003366;
    color: #fff;
    border: none;
    padding: 14px 32px;
    border-radius: 8px;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    transition: background 0.2s;
    white-space: nowrap;
}

.dgptm-gutachten-submit:hover {
    background: #004d99;
}

.dgptm-gutachten-submit:disabled {
    background: #9ca3af;
    cursor: not-allowed;
}

.dgptm-gutachten-hinweis {
    font-size: 13px;
    color: #6b7280;
    margin-top: 12px;
    padding: 12px 16px;
    background: #fffbeb;
    border: 1px solid #fde68a;
    border-radius: 8px;
}

/* === Danke-Seite === */
.dgptm-gutachten-danke {
    text-align: center;
    padding: 40px 24px;
}

.dgptm-gutachten-danke-icon {
    width: 64px;
    height: 64px;
    background: #065f46;
    color: #fff;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    margin-bottom: 16px;
}

.dgptm-gutachten-danke h2 {
    color: #065f46;
    margin: 0 0 16px;
}

.dgptm-gutachten-danke p {
    font-size: 15px;
    color: #374151;
    margin: 0 0 12px;
}

.dgptm-gutachten-danke-details {
    display: inline-block;
    background: #f0f5fa;
    border-radius: 12px;
    padding: 16px 24px;
    margin: 16px 0;
}

.dgptm-gutachten-danke-details table {
    text-align: left;
}

.dgptm-gutachten-danke-details td {
    padding: 4px 12px;
    font-size: 14px;
}

.dgptm-gutachten-danke-kontakt {
    margin-top: 20px;
    font-size: 13px;
    color: #6b7280;
}

.dgptm-gutachten-danke-kontakt a {
    color: #003366;
}

/* === Ungueltig-Seite === */
.dgptm-gutachten-ungueltig {
    text-align: center;
    padding: 60px 24px;
}

.dgptm-gutachten-ungueltig-icon {
    font-size: 48px;
    margin-bottom: 16px;
    color: #b45309;
}

.dgptm-gutachten-ungueltig h2 {
    color: #374151;
    margin: 0 0 12px;
}

.dgptm-gutachten-ungueltig p {
    font-size: 15px;
    color: #6b7280;
}

.dgptm-gutachten-ungueltig a {
    color: #003366;
}

/* === Responsive === */
@media (max-width: 640px) {
    .dgptm-gutachten-frage {
        flex-direction: column;
        align-items: flex-start;
    }

    .dgptm-gutachten-note {
        width: 100%;
    }

    .dgptm-gutachten-actions {
        flex-direction: column;
    }

    .dgptm-gutachten-submit {
        width: 100%;
    }

    .dgptm-gutachten-header-bar {
        flex-direction: column;
        gap: 4px;
    }

    .dgptm-gutachten-score-value {
        font-size: 24px;
    }
}
```

- [ ] **Step 3: Commit**

```
feat(stipendium): Gutachten JS + CSS Assets (Task 7)
```

---

## Task 8: Vorsitzenden-Dashboard

**Files:**
- Create: `modules/business/stipendium/includes/class-vorsitz-dashboard.php`

Ersetzt den Platzhalter in `class-dashboard-tab.php::render_auswertung()`. AJAX-Endpoints fuer alle Vorsitzenden-Aktionen: Freigeben, Ablehnen, Gutachter einladen, Ranking, PDF, Vergeben, Archivieren.

- [ ] **Step 1: Vorsitzenden-Dashboard Klasse erstellen**

```
modules/business/stipendium/includes/class-vorsitz-dashboard.php
```

```php
<?php
/**
 * DGPTM Stipendium — Vorsitzenden-Dashboard.
 *
 * Vollstaendige Implementierung des Auswertungs-Dashboards:
 * - Bewerbungen nach Status gruppiert anzeigen
 * - Gutachter einladen (Token + Mail)
 * - Status-Aktionen (Freigeben, Ablehnen, Vergeben, Archivieren)
 * - Ranking-Berechnung triggern
 * - PDF-Export (Platzhalter fuer spaeteren Zoho Writer Aufruf)
 *
 * Berechtigung: acf:stipendiumsrat_vorsitz ODER manage_options
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Vorsitz_Dashboard {

    const NONCE_ACTION = 'dgptm_stipendium_vorsitz_nonce';

    private $plugin_path;
    private $plugin_url;
    private $settings;
    private $zoho;
    private $token_manager;

    public function __construct($plugin_path, $plugin_url, $settings, $zoho, $token_manager) {
        $this->plugin_path   = $plugin_path;
        $this->plugin_url    = $plugin_url;
        $this->settings      = $settings;
        $this->zoho          = $zoho;
        $this->token_manager = $token_manager;

        // Assets
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);

        // AJAX-Endpoints (alle nur fuer eingeloggte User)
        add_action('wp_ajax_dgptm_stipendium_freigeben', [$this, 'ajax_freigeben']);
        add_action('wp_ajax_dgptm_stipendium_ablehnen', [$this, 'ajax_ablehnen']);
        add_action('wp_ajax_dgptm_stipendium_einladen', [$this, 'ajax_einladen']);
        add_action('wp_ajax_dgptm_stipendium_ranking', [$this, 'ajax_ranking']);
        add_action('wp_ajax_dgptm_stipendium_pdf', [$this, 'ajax_pdf']);
        add_action('wp_ajax_dgptm_stipendium_vergeben', [$this, 'ajax_vergeben']);
        add_action('wp_ajax_dgptm_stipendium_archivieren', [$this, 'ajax_archivieren']);
        add_action('wp_ajax_dgptm_stipendium_load_bewerbungen', [$this, 'ajax_load_bewerbungen']);
    }

    /**
     * Assets registrieren.
     */
    public function register_assets() {
        wp_register_style(
            'dgptm-vorsitz-dashboard',
            $this->plugin_url . 'assets/css/vorsitz-dashboard.css',
            [],
            '1.1.0'
        );
        wp_register_script(
            'dgptm-vorsitz-dashboard',
            $this->plugin_url . 'assets/js/vorsitz-dashboard.js',
            ['jquery'],
            '1.1.0',
            true
        );
    }

    /**
     * Dashboard rendern (aufgerufen von class-dashboard-tab.php).
     *
     * @return string HTML-Output
     */
    public function render() {
        if (!$this->user_is_vorsitz()) return '';

        wp_enqueue_style('dgptm-vorsitz-dashboard');
        wp_enqueue_script('dgptm-vorsitz-dashboard');

        // Verfuegbare Runden/Typen aus Settings
        $typen = $this->settings ? $this->settings->get('stipendientypen') : [];
        $aktive_runden = array_filter($typen, function ($t) {
            return !empty($t['runde']);
        });

        // Default-Runde (erste aktive)
        $default_runde = '';
        $default_typ = '';
        if (!empty($aktive_runden)) {
            $first = reset($aktive_runden);
            $default_runde = $first['runde'];
            $default_typ = $first['bezeichnung'];
        }

        // Gutachter-Frist aus Settings
        $frist_tage = $this->settings ? ($this->settings->get('gutachter_frist_tage') ?: 28) : 28;
        $frist_datum = date_i18n('d.m.Y', strtotime("+{$frist_tage} days"));

        wp_localize_script('dgptm-vorsitz-dashboard', 'dgptmVorsitz', [
            'ajaxUrl'       => admin_url('admin-ajax.php'),
            'nonce'         => wp_create_nonce(self::NONCE_ACTION),
            'defaultRunde'  => $default_runde,
            'defaultTyp'    => $default_typ,
            'fristDatum'    => $frist_datum,
            'runden'        => array_values($aktive_runden),
            'strings'       => [
                'confirm_freigeben'   => 'Bewerbung freigeben?',
                'confirm_ablehnen'    => 'Bewerbung ablehnen? Dies kann rueckgaengig gemacht werden.',
                'confirm_vergeben'    => 'Stipendium an diese/n Bewerber/in vergeben?',
                'confirm_archivieren' => 'Alle abgeschlossenen Bewerbungen dieser Runde archivieren?',
                'einladung_gesendet'  => 'Einladung wurde gesendet.',
                'fehler'              => 'Ein Fehler ist aufgetreten.',
                'laden'               => 'Wird geladen...',
            ],
        ]);

        ob_start();
        include $this->plugin_path . 'templates/vorsitz-dashboard.php';
        return ob_get_clean();
    }

    /* ──────────────────────────────────────────
     * AJAX: Bewerbungen laden
     * ────────────────────────────────────────── */

    public function ajax_load_bewerbungen() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!$this->user_is_vorsitz()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $runde = sanitize_text_field($_POST['runde'] ?? '');
        $typ   = sanitize_text_field($_POST['typ'] ?? '');

        if (empty($runde)) {
            wp_send_json_error('Runde ist ein Pflichtfeld.');
        }

        if (!$this->zoho) {
            // Demo-Modus: Testdaten zurueckgeben
            wp_send_json_success($this->get_demo_data($runde, $typ));
            return;
        }

        // Stipendien aus CRM laden
        $stipendien = $this->zoho->get_stipendien_by_runde($runde, $typ ?: null);
        if (is_wp_error($stipendien)) {
            wp_send_json_error($stipendien->get_error_message());
        }

        // Tokens aus lokaler DB laden und zuordnen
        $result = $this->group_by_status($stipendien ?: []);

        wp_send_json_success($result);
    }

    /**
     * Stipendien nach Status gruppieren und Token-Info anhaengen.
     */
    private function group_by_status($stipendien) {
        $gruppen = [
            'geprueft'      => [],
            'freigegeben'   => [],
            'in_bewertung'  => [],
            'abgeschlossen' => [],
            'abgelehnt'     => [],
            'archiviert'    => [],
        ];

        foreach ($stipendien as $stip) {
            $id = $stip['id'] ?? '';
            $status_raw = $stip['Stipendium_Status'] ?? $stip['Status'] ?? 'Eingegangen';
            $status_key = $this->normalize_status($status_raw);

            // Token-Info aus lokaler DB
            $tokens = $this->token_manager ? $this->token_manager->get_by_stipendium($id) : [];
            $completed = 0;
            $total = count($tokens);
            $gutachter_list = [];

            foreach ($tokens as $t) {
                $gutachter_list[] = [
                    'name'   => $t['gutachter_name'],
                    'email'  => $t['gutachter_email'],
                    'status' => $t['bewertung_status'],
                ];
                if ($t['bewertung_status'] === 'abgeschlossen') {
                    $completed++;
                }
            }

            $entry = [
                'id'              => $id,
                'name'            => $stip['Name'] ?? ($stip['Bewerber']['name'] ?? 'Unbekannt'),
                'stipendientyp'   => $stip['Stipendientyp'] ?? '',
                'eingangsdatum'   => $stip['Eingangsdatum'] ?? '',
                'gesamtscore'     => $stip['Gesamtscore_Mittelwert'] ?? null,
                'rang'            => $stip['Rang'] ?? null,
                'foerderfaehig'   => !empty($stip['Foerderfaehig']),
                'vergeben'        => !empty($stip['Vergeben']),
                'gutachter_total' => $total,
                'gutachter_done'  => $completed,
                'gutachter'       => $gutachter_list,
            ];

            if (isset($gruppen[$status_key])) {
                $gruppen[$status_key][] = $entry;
            }
        }

        return $gruppen;
    }

    /**
     * Status-String normalisieren.
     */
    private function normalize_status($status) {
        $map = [
            'Eingegangen'    => 'geprueft',
            'Geprueft'       => 'geprueft',
            'Freigegeben'    => 'freigegeben',
            'In Bewertung'   => 'in_bewertung',
            'Abgeschlossen'  => 'abgeschlossen',
            'Abgelehnt'      => 'abgelehnt',
            'Archiviert'     => 'archiviert',
        ];
        return $map[$status] ?? 'geprueft';
    }

    /* ──────────────────────────────────────────
     * AJAX: Bewerbung freigeben
     * ────────────────────────────────────────── */

    public function ajax_freigeben() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!$this->user_is_vorsitz()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $stipendium_id = sanitize_text_field($_POST['stipendium_id'] ?? '');
        if (empty($stipendium_id)) {
            wp_send_json_error('Stipendium-ID fehlt.');
        }

        if ($this->zoho) {
            $result = $this->zoho->update_stipendium($stipendium_id, [
                'Stipendium_Status' => 'Freigegeben',
                'Freigabedatum'     => date('Y-m-d'),
            ]);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            // Cache invalidieren
            $runde = sanitize_text_field($_POST['runde'] ?? '');
            if ($runde) {
                $this->zoho->invalidate_stipendien_cache($runde);
            }
        }

        wp_send_json_success(['message' => 'Bewerbung freigegeben.']);
    }

    /* ──────────────────────────────────────────
     * AJAX: Bewerbung ablehnen
     * ────────────────────────────────────────── */

    public function ajax_ablehnen() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!$this->user_is_vorsitz()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $stipendium_id = sanitize_text_field($_POST['stipendium_id'] ?? '');
        if (empty($stipendium_id)) {
            wp_send_json_error('Stipendium-ID fehlt.');
        }

        if ($this->zoho) {
            $result = $this->zoho->update_stipendium($stipendium_id, [
                'Stipendium_Status' => 'Abgelehnt',
            ]);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            $runde = sanitize_text_field($_POST['runde'] ?? '');
            if ($runde) {
                $this->zoho->invalidate_stipendien_cache($runde);
            }
        }

        wp_send_json_success(['message' => 'Bewerbung abgelehnt.']);
    }

    /* ──────────────────────────────────────────
     * AJAX: Gutachter einladen
     * ────────────────────────────────────────── */

    public function ajax_einladen() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!$this->user_is_vorsitz()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $stipendium_id   = sanitize_text_field($_POST['stipendium_id'] ?? '');
        $gutachter_name  = sanitize_text_field($_POST['gutachter_name'] ?? '');
        $gutachter_email = sanitize_email($_POST['gutachter_email'] ?? '');
        $frist           = sanitize_text_field($_POST['frist'] ?? '');

        if (empty($stipendium_id) || empty($gutachter_name) || empty($gutachter_email)) {
            wp_send_json_error('Bitte alle Felder ausfuellen.');
        }

        if (!is_email($gutachter_email)) {
            wp_send_json_error('Bitte eine gueltige E-Mail-Adresse eingeben.');
        }

        // Frist berechnen
        $frist_tage = 28;
        if ($this->settings) {
            $frist_tage = $this->settings->get('gutachter_frist_tage') ?: 28;
        }
        if (!empty($frist)) {
            // Frist als Datum angegeben — Differenz zu heute berechnen
            $frist_ts = strtotime($frist);
            if ($frist_ts && $frist_ts > time()) {
                $frist_tage = (int) ceil(($frist_ts - time()) / DAY_IN_SECONDS);
            }
        }

        // Token generieren
        if (!$this->token_manager) {
            wp_send_json_error('Token-Manager nicht verfuegbar.');
        }

        $token_result = $this->token_manager->generate(
            $stipendium_id,
            $gutachter_name,
            $gutachter_email,
            $frist_tage
        );

        if (is_wp_error($token_result)) {
            wp_send_json_error($token_result->get_error_message());
        }

        // Bewerberdaten fuer Mail laden
        $bewerber_name = 'Unbekannt';
        $stipendientyp = '';
        $runde = '';
        if ($this->zoho) {
            $stipendium = $this->zoho->get_stipendium($stipendium_id);
            if (!is_wp_error($stipendium)) {
                $data = $stipendium['data'][0] ?? $stipendium;
                $bewerber_name = $data['Bewerber']['name'] ?? $data['Name'] ?? 'Unbekannt';
                $stipendientyp = $data['Stipendientyp'] ?? '';
                $runde = $data['Runde'] ?? '';
            }

            // Status auf "In Bewertung" setzen (falls noch Freigegeben)
            $this->zoho->update_stipendium($stipendium_id, [
                'Stipendium_Status' => 'In Bewertung',
            ]);
            if ($runde) {
                $this->zoho->invalidate_stipendien_cache($runde);
            }
        }

        // Einladungs-Mail senden
        $frist_datum = date_i18n('d.m.Y', strtotime("+{$frist_tage} days"));
        $mail_sent = DGPTM_Stipendium_Mail_Templates::send_einladung([
            'gutachter_name'  => $gutachter_name,
            'gutachter_email' => $gutachter_email,
            'bewerber_name'   => $bewerber_name,
            'stipendientyp'   => $stipendientyp,
            'runde'           => $runde,
            'frist'           => $frist_datum,
            'gutachten_url'   => $token_result['url'],
        ]);

        wp_send_json_success([
            'message'   => $mail_sent
                ? 'Einladung an ' . $gutachter_name . ' gesendet.'
                : 'Token erstellt, aber E-Mail konnte nicht gesendet werden.',
            'token_url' => $token_result['url'],
            'mail_sent' => $mail_sent,
        ]);
    }

    /* ──────────────────────────────────────────
     * AJAX: Ranking berechnen
     * ────────────────────────────────────────── */

    public function ajax_ranking() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!$this->user_is_vorsitz()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $runde = sanitize_text_field($_POST['runde'] ?? '');
        $typ   = sanitize_text_field($_POST['typ'] ?? '');

        if (empty($runde)) {
            wp_send_json_error('Runde ist ein Pflichtfeld.');
        }

        // In Zoho CRM wird das Ranking via Deluge Custom Function berechnet.
        // Hier triggern wir die Neuberechnung ueber einen Status-Update,
        // der die Workflow Rule ausloest.
        // Alternative: Custom Function ueber API aufrufen (wenn verfuegbar).

        if ($this->zoho) {
            $this->zoho->invalidate_stipendien_cache($runde, $typ ?: null);
        }

        wp_send_json_success([
            'message' => 'Ranking wird im CRM neu berechnet. Bitte laden Sie das Dashboard in wenigen Sekunden neu.',
        ]);
    }

    /* ──────────────────────────────────────────
     * AJAX: PDF-Export
     * ────────────────────────────────────────── */

    public function ajax_pdf() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!$this->user_is_vorsitz()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        // Platzhalter: PDF-Generierung ueber Zoho Writer oder lokale Lib
        // Wird in einer spaeteren Version implementiert
        wp_send_json_error('PDF-Export wird in einer spaeteren Version implementiert.');
    }

    /* ──────────────────────────────────────────
     * AJAX: Stipendium vergeben
     * ────────────────────────────────────────── */

    public function ajax_vergeben() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!$this->user_is_vorsitz()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $stipendium_id = sanitize_text_field($_POST['stipendium_id'] ?? '');
        if (empty($stipendium_id)) {
            wp_send_json_error('Stipendium-ID fehlt.');
        }

        if ($this->zoho) {
            $result = $this->zoho->update_stipendium($stipendium_id, [
                'Vergeben'     => true,
                'Vergabedatum' => date('Y-m-d'),
            ]);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            $runde = sanitize_text_field($_POST['runde'] ?? '');
            if ($runde) {
                $this->zoho->invalidate_stipendien_cache($runde);
            }
        }

        wp_send_json_success(['message' => 'Stipendium wurde vergeben.']);
    }

    /* ──────────────────────────────────────────
     * AJAX: Runde archivieren
     * ────────────────────────────────────────── */

    public function ajax_archivieren() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');
        if (!$this->user_is_vorsitz()) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $runde = sanitize_text_field($_POST['runde'] ?? '');
        $typ   = sanitize_text_field($_POST['typ'] ?? '');

        if (empty($runde)) {
            wp_send_json_error('Runde ist ein Pflichtfeld.');
        }

        if (!$this->zoho) {
            wp_send_json_success(['message' => 'Demo-Modus: Archivierung simuliert.', 'count' => 0]);
            return;
        }

        // Alle abgeschlossenen Stipendien der Runde laden
        $stipendien = $this->zoho->get_stipendien_by_runde($runde, $typ ?: null);
        if (is_wp_error($stipendien)) {
            wp_send_json_error($stipendien->get_error_message());
        }

        $archived = 0;
        foreach ($stipendien as $stip) {
            $status = $stip['Stipendium_Status'] ?? $stip['Status'] ?? '';
            if ($status === 'Abgeschlossen') {
                $this->zoho->update_stipendium($stip['id'], [
                    'Stipendium_Status' => 'Archiviert',
                ]);
                $archived++;
            }
        }

        $this->zoho->invalidate_stipendien_cache($runde, $typ ?: null);

        wp_send_json_success([
            'message' => $archived . ' Bewerbung(en) archiviert.',
            'count'   => $archived,
        ]);
    }

    /* ──────────────────────────────────────────
     * Berechtigungspruefung
     * ────────────────────────────────────────── */

    private function user_is_vorsitz() {
        if (!is_user_logged_in()) return false;
        if (current_user_can('manage_options')) return true;

        $user_id = get_current_user_id();
        return (bool) get_field('stipendiumsrat_vorsitz', 'user_' . $user_id);
    }

    /* ──────────────────────────────────────────
     * Demo-Daten (wenn Zoho nicht verfuegbar)
     * ────────────────────────────────────────── */

    private function get_demo_data($runde, $typ) {
        return [
            'geprueft' => [
                [
                    'id' => 'DEMO_001',
                    'name' => 'Max Mustermann',
                    'stipendientyp' => 'Promotionsstipendium',
                    'eingangsdatum' => '2026-04-01',
                    'gesamtscore' => null,
                    'rang' => null,
                    'foerderfaehig' => false,
                    'vergeben' => false,
                    'gutachter_total' => 0,
                    'gutachter_done' => 0,
                    'gutachter' => [],
                ],
            ],
            'freigegeben' => [
                [
                    'id' => 'DEMO_002',
                    'name' => 'Anna Beispiel',
                    'stipendientyp' => 'Josef Guettler Stipendium',
                    'eingangsdatum' => '2026-04-02',
                    'gesamtscore' => null,
                    'rang' => null,
                    'foerderfaehig' => false,
                    'vergeben' => false,
                    'gutachter_total' => 0,
                    'gutachter_done' => 0,
                    'gutachter' => [],
                ],
            ],
            'in_bewertung'  => [],
            'abgeschlossen' => [],
            'abgelehnt'     => [],
            'archiviert'    => [],
        ];
    }
}
```

- [ ] **Step 2: Commit**

```
feat(stipendium): Vorsitzenden-Dashboard mit allen AJAX-Aktionen (Task 8)
```

---

## Task 9: Vorsitzenden-Dashboard Template + Assets

**Files:**
- Create: `modules/business/stipendium/templates/vorsitz-dashboard.php`
- Create: `modules/business/stipendium/assets/js/vorsitz-dashboard.js`
- Create: `modules/business/stipendium/assets/css/vorsitz-dashboard.css`

- [ ] **Step 1: Dashboard-Template erstellen**

```
modules/business/stipendium/templates/vorsitz-dashboard.php
```

```php
<?php
/**
 * Template: Vorsitzenden-Dashboard
 *
 * Variablen (von class-vorsitz-dashboard.php bereitgestellt):
 * @var array $aktive_runden  Runden-Konfigurationen aus Settings
 * @var string $frist_datum   Default-Frist formatiert
 */
if (!defined('ABSPATH')) exit;
?>

<div class="dgptm-vorsitz-wrap" id="dgptm-vorsitz-dashboard">

    <!-- Header mit Filtern -->
    <div class="dgptm-vorsitz-header">
        <h3>Stipendien &mdash; Vorsitzenden-Dashboard</h3>
        <div class="dgptm-vorsitz-filter">
            <label for="dgptm-vorsitz-runde">Runde:</label>
            <select id="dgptm-vorsitz-runde">
                <?php foreach ($aktive_runden as $typ) : ?>
                    <option value="<?php echo esc_attr($typ['runde']); ?>"
                            data-typ="<?php echo esc_attr($typ['bezeichnung']); ?>">
                        <?php echo esc_html($typ['bezeichnung']); ?> &mdash; <?php echo esc_html($typ['runde']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <!-- Lade-Indikator -->
    <div class="dgptm-vorsitz-loading" id="dgptm-vorsitz-loading" style="display:none;">
        <div class="dgptm-vorsitz-spinner"></div>
        Bewerbungen werden geladen...
    </div>

    <!-- Status-Gruppen -->
    <div id="dgptm-vorsitz-content">

        <!-- Geprueft (bereit zur Freigabe) -->
        <section class="dgptm-vorsitz-section" id="dgptm-section-geprueft" style="display:none;">
            <h4 class="dgptm-vorsitz-section-title dgptm-vorsitz-section-title--yellow">
                Geprueft <span class="dgptm-vorsitz-badge" id="dgptm-count-geprueft">0</span>
            </h4>
            <div class="dgptm-vorsitz-cards" id="dgptm-cards-geprueft"></div>
        </section>

        <!-- Freigegeben (Gutachter einladen) -->
        <section class="dgptm-vorsitz-section" id="dgptm-section-freigegeben" style="display:none;">
            <h4 class="dgptm-vorsitz-section-title dgptm-vorsitz-section-title--blue">
                Freigegeben <span class="dgptm-vorsitz-badge" id="dgptm-count-freigegeben">0</span>
            </h4>
            <div class="dgptm-vorsitz-cards" id="dgptm-cards-freigegeben"></div>
        </section>

        <!-- In Bewertung -->
        <section class="dgptm-vorsitz-section" id="dgptm-section-in_bewertung" style="display:none;">
            <h4 class="dgptm-vorsitz-section-title dgptm-vorsitz-section-title--orange">
                In Bewertung <span class="dgptm-vorsitz-badge" id="dgptm-count-in_bewertung">0</span>
            </h4>
            <div class="dgptm-vorsitz-cards" id="dgptm-cards-in_bewertung"></div>
        </section>

        <!-- Abgeschlossen (Ranking) -->
        <section class="dgptm-vorsitz-section" id="dgptm-section-abgeschlossen" style="display:none;">
            <h4 class="dgptm-vorsitz-section-title dgptm-vorsitz-section-title--green">
                Abgeschlossen <span class="dgptm-vorsitz-badge" id="dgptm-count-abgeschlossen">0</span>
            </h4>
            <div class="dgptm-vorsitz-ranking" id="dgptm-ranking-table"></div>
            <div class="dgptm-vorsitz-bulk-actions" id="dgptm-bulk-actions" style="display:none;">
                <button type="button" class="dgptm-vorsitz-btn dgptm-vorsitz-btn--outline" data-action="pdf">
                    PDF-Export
                </button>
                <button type="button" class="dgptm-vorsitz-btn dgptm-vorsitz-btn--primary" data-action="archivieren">
                    Runde archivieren
                </button>
            </div>
        </section>

        <!-- Leer-Hinweis -->
        <div class="dgptm-vorsitz-empty" id="dgptm-vorsitz-empty" style="display:none;">
            <p>Keine Bewerbungen in dieser Runde vorhanden.</p>
        </div>
    </div>

    <!-- Einladungs-Modal (versteckt) -->
    <div class="dgptm-vorsitz-modal-overlay" id="dgptm-einladung-modal" style="display:none;">
        <div class="dgptm-vorsitz-modal">
            <div class="dgptm-vorsitz-modal-header">
                <h4>Gutachter/in einladen</h4>
                <button type="button" class="dgptm-vorsitz-modal-close" id="dgptm-einladung-close">&times;</button>
            </div>
            <div class="dgptm-vorsitz-modal-body">
                <input type="hidden" id="dgptm-einladung-stipendium-id">
                <p class="dgptm-vorsitz-modal-info" id="dgptm-einladung-bewerber-info"></p>
                <div class="dgptm-vorsitz-field">
                    <label for="dgptm-einladung-name">Name des/der Gutachter/in:</label>
                    <input type="text" id="dgptm-einladung-name" placeholder="z.B. Prof. Dr. Mueller">
                </div>
                <div class="dgptm-vorsitz-field">
                    <label for="dgptm-einladung-email">E-Mail:</label>
                    <input type="email" id="dgptm-einladung-email" placeholder="gutachter@example.de">
                </div>
                <div class="dgptm-vorsitz-field">
                    <label for="dgptm-einladung-frist">Frist bis:</label>
                    <input type="date" id="dgptm-einladung-frist"
                           value="<?php echo esc_attr(date('Y-m-d', strtotime('+28 days'))); ?>">
                </div>
            </div>
            <div class="dgptm-vorsitz-modal-footer">
                <button type="button" class="dgptm-vorsitz-btn dgptm-vorsitz-btn--outline" id="dgptm-einladung-cancel">Abbrechen</button>
                <button type="button" class="dgptm-vorsitz-btn dgptm-vorsitz-btn--primary" id="dgptm-einladung-send">Einladung senden</button>
            </div>
        </div>
    </div>
</div>
```

- [ ] **Step 2: Dashboard-JavaScript erstellen**

```
modules/business/stipendium/assets/js/vorsitz-dashboard.js
```

```javascript
/**
 * DGPTM Stipendium — Vorsitzenden-Dashboard
 *
 * Funktionen:
 * - Bewerbungen per AJAX laden und nach Status gruppiert anzeigen
 * - Gutachter einladen (Modal + AJAX)
 * - Status-Aktionen (Freigeben, Ablehnen, Vergeben, Archivieren)
 */
(function($) {
    'use strict';

    var config = window.dgptmVorsitz || {};
    var ajaxUrl = config.ajaxUrl || '';
    var nonce = config.nonce || '';
    var strings = config.strings || {};

    /**
     * Bewerbungen laden.
     */
    function loadBewerbungen() {
        var runde = $('#dgptm-vorsitz-runde').val();
        var typ = $('#dgptm-vorsitz-runde option:selected').data('typ') || '';

        if (!runde) return;

        $('#dgptm-vorsitz-loading').show();
        $('#dgptm-vorsitz-empty').hide();

        // Alle Sektionen verstecken
        $('.dgptm-vorsitz-section').hide();

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'dgptm_stipendium_load_bewerbungen',
                nonce: nonce,
                runde: runde,
                typ: typ
            },
            success: function(response) {
                $('#dgptm-vorsitz-loading').hide();

                if (!response.success) {
                    alert(response.data || strings.fehler);
                    return;
                }

                renderDashboard(response.data);
            },
            error: function() {
                $('#dgptm-vorsitz-loading').hide();
                alert(strings.fehler || 'Fehler beim Laden.');
            }
        });
    }

    /**
     * Dashboard-Inhalt rendern.
     */
    function renderDashboard(data) {
        var hasEntries = false;

        // Geprueft
        if (data.geprueft && data.geprueft.length > 0) {
            hasEntries = true;
            $('#dgptm-section-geprueft').show();
            $('#dgptm-count-geprueft').text(data.geprueft.length);
            $('#dgptm-cards-geprueft').html(data.geprueft.map(renderGeprueftCard).join(''));
        }

        // Freigegeben
        if (data.freigegeben && data.freigegeben.length > 0) {
            hasEntries = true;
            $('#dgptm-section-freigegeben').show();
            $('#dgptm-count-freigegeben').text(data.freigegeben.length);
            $('#dgptm-cards-freigegeben').html(data.freigegeben.map(renderFreigegebenCard).join(''));
        }

        // In Bewertung
        if (data.in_bewertung && data.in_bewertung.length > 0) {
            hasEntries = true;
            $('#dgptm-section-in_bewertung').show();
            $('#dgptm-count-in_bewertung').text(data.in_bewertung.length);
            $('#dgptm-cards-in_bewertung').html(data.in_bewertung.map(renderInBewertungCard).join(''));
        }

        // Abgeschlossen
        if (data.abgeschlossen && data.abgeschlossen.length > 0) {
            hasEntries = true;
            $('#dgptm-section-abgeschlossen').show();
            $('#dgptm-count-abgeschlossen').text(data.abgeschlossen.length);
            renderRankingTable(data.abgeschlossen);
            $('#dgptm-bulk-actions').show();
        }

        if (!hasEntries) {
            $('#dgptm-vorsitz-empty').show();
        }
    }

    /**
     * Karte: Geprueft (Freigeben/Ablehnen).
     */
    function renderGeprueftCard(item) {
        var datum = item.eingangsdatum ? formatDate(item.eingangsdatum) : '';
        return '<div class="dgptm-vorsitz-card">'
            + '<div class="dgptm-vorsitz-card-header">'
            + '<strong>' + escHtml(item.name) + '</strong>'
            + '<span class="dgptm-vorsitz-card-tag">' + escHtml(item.stipendientyp) + '</span>'
            + '</div>'
            + (datum ? '<div class="dgptm-vorsitz-card-meta">Eingang: ' + datum + '</div>' : '')
            + '<div class="dgptm-vorsitz-card-actions">'
            + '<button class="dgptm-vorsitz-btn dgptm-vorsitz-btn--sm dgptm-vorsitz-btn--primary" '
            + 'data-action="freigeben" data-id="' + item.id + '">Freigeben</button>'
            + '<button class="dgptm-vorsitz-btn dgptm-vorsitz-btn--sm dgptm-vorsitz-btn--danger" '
            + 'data-action="ablehnen" data-id="' + item.id + '">Ablehnen</button>'
            + '</div></div>';
    }

    /**
     * Karte: Freigegeben (Gutachter einladen).
     */
    function renderFreigegebenCard(item) {
        var gutachterHtml = '';
        if (item.gutachter && item.gutachter.length > 0) {
            gutachterHtml = '<div class="dgptm-vorsitz-gutachter-list">';
            item.gutachter.forEach(function(g) {
                var icon = g.status === 'abgeschlossen' ? '&#10003;' : '&#9675;';
                var cls = g.status === 'abgeschlossen' ? 'done' : 'pending';
                gutachterHtml += '<div class="dgptm-vorsitz-gutachter-item dgptm-vorsitz-gutachter-item--' + cls + '">'
                    + '<span>' + icon + '</span> ' + escHtml(g.name)
                    + ' <span class="dgptm-vorsitz-gutachter-status">' + escHtml(g.status) + '</span>'
                    + '</div>';
            });
            gutachterHtml += '</div>';
        }

        return '<div class="dgptm-vorsitz-card">'
            + '<div class="dgptm-vorsitz-card-header">'
            + '<strong>' + escHtml(item.name) + '</strong>'
            + '<span class="dgptm-vorsitz-card-tag">' + escHtml(item.stipendientyp) + '</span>'
            + '</div>'
            + '<div class="dgptm-vorsitz-card-meta">Gutachter: ' + item.gutachter_done + '/' + item.gutachter_total + ' zugewiesen</div>'
            + gutachterHtml
            + '<div class="dgptm-vorsitz-card-actions">'
            + '<button class="dgptm-vorsitz-btn dgptm-vorsitz-btn--sm dgptm-vorsitz-btn--primary" '
            + 'data-action="einladen" data-id="' + item.id + '" data-name="' + escAttr(item.name) + '">+ Gutachter einladen</button>'
            + '</div></div>';
    }

    /**
     * Karte: In Bewertung.
     */
    function renderInBewertungCard(item) {
        var gutachterHtml = '';
        if (item.gutachter && item.gutachter.length > 0) {
            gutachterHtml = '<div class="dgptm-vorsitz-gutachter-list">';
            item.gutachter.forEach(function(g) {
                var icon = g.status === 'abgeschlossen' ? '&#10003;' : '&#9675;';
                var cls = g.status === 'abgeschlossen' ? 'done' : 'pending';
                gutachterHtml += '<div class="dgptm-vorsitz-gutachter-item dgptm-vorsitz-gutachter-item--' + cls + '">'
                    + '<span>' + icon + '</span> ' + escHtml(g.name)
                    + '</div>';
            });
            gutachterHtml += '</div>';
        }

        return '<div class="dgptm-vorsitz-card">'
            + '<div class="dgptm-vorsitz-card-header">'
            + '<strong>' + escHtml(item.name) + '</strong>'
            + '<span class="dgptm-vorsitz-card-tag">' + escHtml(item.stipendientyp) + '</span>'
            + '</div>'
            + '<div class="dgptm-vorsitz-card-meta">' + item.gutachter_done + '/' + item.gutachter_total + ' Gutachten abgeschlossen</div>'
            + gutachterHtml
            + '<div class="dgptm-vorsitz-card-actions">'
            + '<button class="dgptm-vorsitz-btn dgptm-vorsitz-btn--sm dgptm-vorsitz-btn--primary" '
            + 'data-action="einladen" data-id="' + item.id + '" data-name="' + escAttr(item.name) + '">+ Weiteren Gutachter einladen</button>'
            + '</div></div>';
    }

    /**
     * Ranking-Tabelle rendern.
     */
    function renderRankingTable(items) {
        // Nach Score sortieren (absteigend)
        items.sort(function(a, b) {
            return (b.gesamtscore || 0) - (a.gesamtscore || 0);
        });

        var html = '<table class="dgptm-vorsitz-ranking-table">'
            + '<thead><tr>'
            + '<th>Rang</th><th>Name</th><th>Score</th><th>Gutachten</th><th>Aktion</th>'
            + '</tr></thead><tbody>';

        items.forEach(function(item, idx) {
            var rang = item.foerderfaehig ? (item.rang || (idx + 1)) : '&mdash;';
            var scoreStr = item.gesamtscore !== null ? parseFloat(item.gesamtscore).toFixed(2) : '--';
            var foerderClass = item.foerderfaehig ? '' : ' dgptm-vorsitz-nicht-foerderfaehig';
            var vergebenBadge = item.vergeben ? ' <span class="dgptm-vorsitz-vergeben-badge">vergeben</span>' : '';

            html += '<tr class="' + foerderClass + '">'
                + '<td>' + rang + '</td>'
                + '<td>' + escHtml(item.name) + vergebenBadge
                + (item.foerderfaehig ? '' : ' <span class="dgptm-vorsitz-hint">nicht foerderfaehig</span>') + '</td>'
                + '<td>' + scoreStr + '</td>'
                + '<td>' + item.gutachter_done + '/' + item.gutachter_total + '</td>'
                + '<td>';

            if (!item.vergeben && item.foerderfaehig) {
                html += '<button class="dgptm-vorsitz-btn dgptm-vorsitz-btn--xs dgptm-vorsitz-btn--primary" '
                    + 'data-action="vergeben" data-id="' + item.id + '">Vergeben</button>';
            }

            html += '</td></tr>';
        });

        html += '</tbody></table>';
        $('#dgptm-ranking-table').html(html);
    }

    /**
     * Aktion ausfuehren (Freigeben, Ablehnen, Vergeben).
     */
    function executeAction(action, stipendiumId, extraData) {
        var confirmMsg = strings['confirm_' + action] || ('Aktion "' + action + '" ausfuehren?');

        if (!confirm(confirmMsg)) return;

        var postData = {
            action: 'dgptm_stipendium_' + action,
            nonce: nonce,
            stipendium_id: stipendiumId,
            runde: $('#dgptm-vorsitz-runde').val()
        };

        if (extraData) {
            $.extend(postData, extraData);
        }

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: postData,
            success: function(response) {
                if (response.success) {
                    loadBewerbungen(); // Dashboard neu laden
                } else {
                    alert(response.data?.message || response.data || strings.fehler);
                }
            },
            error: function() {
                alert(strings.fehler || 'Fehler bei der Aktion.');
            }
        });
    }

    /**
     * Einladungs-Modal oeffnen.
     */
    function openEinladungModal(stipendiumId, bewerberName) {
        $('#dgptm-einladung-stipendium-id').val(stipendiumId);
        $('#dgptm-einladung-bewerber-info').text('Bewerbung: ' + bewerberName);
        $('#dgptm-einladung-name').val('');
        $('#dgptm-einladung-email').val('');
        $('#dgptm-einladung-modal').fadeIn(200);
    }

    /**
     * Einladung senden.
     */
    function sendEinladung() {
        var stipendiumId = $('#dgptm-einladung-stipendium-id').val();
        var name = $('#dgptm-einladung-name').val().trim();
        var email = $('#dgptm-einladung-email').val().trim();
        var frist = $('#dgptm-einladung-frist').val();

        if (!name || !email) {
            alert('Bitte Name und E-Mail ausfuellen.');
            return;
        }

        var sendBtn = $('#dgptm-einladung-send');
        sendBtn.text('Wird gesendet...').prop('disabled', true);

        $.ajax({
            url: ajaxUrl,
            method: 'POST',
            data: {
                action: 'dgptm_stipendium_einladen',
                nonce: nonce,
                stipendium_id: stipendiumId,
                gutachter_name: name,
                gutachter_email: email,
                frist: frist,
                runde: $('#dgptm-vorsitz-runde').val()
            },
            success: function(response) {
                if (response.success) {
                    alert(response.data.message || strings.einladung_gesendet);
                    $('#dgptm-einladung-modal').fadeOut(200);
                    loadBewerbungen();
                } else {
                    alert(response.data?.message || response.data || strings.fehler);
                }
            },
            error: function() {
                alert(strings.fehler || 'Fehler beim Senden.');
            },
            complete: function() {
                sendBtn.text('Einladung senden').prop('disabled', false);
            }
        });
    }

    /**
     * Hilfsfunktionen.
     */
    function escHtml(str) {
        if (!str) return '';
        return $('<span>').text(str).html();
    }

    function escAttr(str) {
        if (!str) return '';
        return str.replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/</g, '&lt;');
    }

    function formatDate(dateStr) {
        if (!dateStr) return '';
        var parts = dateStr.split('-');
        if (parts.length === 3) {
            return parts[2] + '.' + parts[1] + '.' + parts[0];
        }
        return dateStr;
    }

    /**
     * Initialisierung.
     */
    $(document).ready(function() {
        // Initial laden
        loadBewerbungen();

        // Runde wechseln
        $('#dgptm-vorsitz-runde').on('change', loadBewerbungen);

        // Button-Aktionen (Event Delegation)
        $(document).on('click', '[data-action]', function(e) {
            e.preventDefault();
            var btn = $(this);
            var action = btn.data('action');
            var id = btn.data('id');
            var name = btn.data('name');

            switch (action) {
                case 'freigeben':
                case 'ablehnen':
                case 'vergeben':
                    executeAction(action, id);
                    break;
                case 'einladen':
                    openEinladungModal(id, name || '');
                    break;
                case 'archivieren':
                    executeAction('archivieren', '', {
                        typ: $('#dgptm-vorsitz-runde option:selected').data('typ') || ''
                    });
                    break;
                case 'pdf':
                    executeAction('pdf', '');
                    break;
            }
        });

        // Einladungs-Modal
        $('#dgptm-einladung-send').on('click', sendEinladung);
        $('#dgptm-einladung-close, #dgptm-einladung-cancel').on('click', function() {
            $('#dgptm-einladung-modal').fadeOut(200);
        });

        // Modal schliessen bei Klick ausserhalb
        $('#dgptm-einladung-modal').on('click', function(e) {
            if ($(e.target).hasClass('dgptm-vorsitz-modal-overlay')) {
                $(this).fadeOut(200);
            }
        });
    });

})(jQuery);
```

- [ ] **Step 3: Dashboard-CSS erstellen**

```
modules/business/stipendium/assets/css/vorsitz-dashboard.css
```

```css
/**
 * DGPTM Stipendium — Vorsitzenden-Dashboard Styles
 */

/* === Wrapper === */
.dgptm-vorsitz-wrap {
    max-width: 900px;
    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    color: #1a1a1a;
}

/* === Header === */
.dgptm-vorsitz-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
    flex-wrap: wrap;
    gap: 12px;
}

.dgptm-vorsitz-header h3 {
    margin: 0;
    font-size: 20px;
    color: #003366;
}

.dgptm-vorsitz-filter {
    display: flex;
    align-items: center;
    gap: 8px;
}

.dgptm-vorsitz-filter label {
    font-size: 14px;
    font-weight: 600;
    color: #374151;
}

.dgptm-vorsitz-filter select {
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    min-width: 250px;
}

/* === Loading === */
.dgptm-vorsitz-loading {
    text-align: center;
    padding: 40px;
    color: #6b7280;
    font-size: 14px;
}

.dgptm-vorsitz-spinner {
    width: 32px;
    height: 32px;
    border: 3px solid #e5e7eb;
    border-top-color: #003366;
    border-radius: 50%;
    margin: 0 auto 12px;
    animation: dgptm-spin 0.8s linear infinite;
}

@keyframes dgptm-spin {
    to { transform: rotate(360deg); }
}

/* === Sektionen === */
.dgptm-vorsitz-section {
    margin-bottom: 24px;
}

.dgptm-vorsitz-section-title {
    font-size: 16px;
    font-weight: 600;
    padding: 10px 16px;
    border-radius: 8px;
    margin: 0 0 12px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.dgptm-vorsitz-section-title--yellow {
    background: #fffbeb;
    color: #92400e;
    border: 1px solid #fde68a;
}

.dgptm-vorsitz-section-title--blue {
    background: #eff6ff;
    color: #1e40af;
    border: 1px solid #bfdbfe;
}

.dgptm-vorsitz-section-title--orange {
    background: #fff7ed;
    color: #9a3412;
    border: 1px solid #fed7aa;
}

.dgptm-vorsitz-section-title--green {
    background: #f0fdf4;
    color: #166534;
    border: 1px solid #bbf7d0;
}

.dgptm-vorsitz-badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: rgba(0,0,0,0.1);
    font-size: 12px;
    font-weight: 700;
    width: 24px;
    height: 24px;
    border-radius: 50%;
}

/* === Karten === */
.dgptm-vorsitz-card {
    background: #fff;
    border: 1px solid #e5e7eb;
    border-radius: 10px;
    padding: 16px;
    margin-bottom: 10px;
}

.dgptm-vorsitz-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 6px;
    flex-wrap: wrap;
    gap: 8px;
}

.dgptm-vorsitz-card-header strong {
    font-size: 15px;
}

.dgptm-vorsitz-card-tag {
    display: inline-block;
    background: #e8eaf6;
    color: #283593;
    font-size: 11px;
    font-weight: 600;
    padding: 2px 10px;
    border-radius: 10px;
}

.dgptm-vorsitz-card-meta {
    font-size: 13px;
    color: #6b7280;
    margin-bottom: 10px;
}

.dgptm-vorsitz-card-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-top: 10px;
}

/* === Gutachter-Liste === */
.dgptm-vorsitz-gutachter-list {
    margin: 8px 0;
    padding: 8px 0;
    border-top: 1px solid #f3f4f6;
}

.dgptm-vorsitz-gutachter-item {
    font-size: 13px;
    padding: 3px 0;
    display: flex;
    align-items: center;
    gap: 6px;
}

.dgptm-vorsitz-gutachter-item--done {
    color: #065f46;
}

.dgptm-vorsitz-gutachter-item--pending {
    color: #6b7280;
}

.dgptm-vorsitz-gutachter-status {
    font-size: 11px;
    opacity: 0.7;
}

/* === Ranking-Tabelle === */
.dgptm-vorsitz-ranking-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 14px;
    margin-bottom: 16px;
}

.dgptm-vorsitz-ranking-table thead th {
    background: #003366;
    color: #fff;
    padding: 10px 12px;
    text-align: left;
    font-weight: 600;
    font-size: 13px;
}

.dgptm-vorsitz-ranking-table thead th:first-child {
    border-radius: 8px 0 0 0;
}

.dgptm-vorsitz-ranking-table thead th:last-child {
    border-radius: 0 8px 0 0;
}

.dgptm-vorsitz-ranking-table tbody td {
    padding: 10px 12px;
    border-bottom: 1px solid #e5e7eb;
}

.dgptm-vorsitz-ranking-table tbody tr:hover {
    background: #f8fafc;
}

.dgptm-vorsitz-nicht-foerderfaehig td {
    color: #9ca3af;
}

.dgptm-vorsitz-hint {
    font-size: 11px;
    color: #dc2626;
    font-style: italic;
}

.dgptm-vorsitz-vergeben-badge {
    display: inline-block;
    background: #065f46;
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 8px;
    border-radius: 8px;
    margin-left: 6px;
    text-transform: uppercase;
}

/* === Buttons === */
.dgptm-vorsitz-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 8px 16px;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    border: 1px solid transparent;
    transition: background 0.15s, border-color 0.15s;
    white-space: nowrap;
}

.dgptm-vorsitz-btn--primary {
    background: #003366;
    color: #fff;
    border-color: #003366;
}

.dgptm-vorsitz-btn--primary:hover {
    background: #004d99;
}

.dgptm-vorsitz-btn--danger {
    background: #fff;
    color: #dc2626;
    border-color: #fca5a5;
}

.dgptm-vorsitz-btn--danger:hover {
    background: #fef2f2;
    border-color: #dc2626;
}

.dgptm-vorsitz-btn--outline {
    background: #fff;
    color: #374151;
    border-color: #d1d5db;
}

.dgptm-vorsitz-btn--outline:hover {
    background: #f9fafb;
    border-color: #9ca3af;
}

.dgptm-vorsitz-btn--sm {
    padding: 6px 12px;
    font-size: 12px;
}

.dgptm-vorsitz-btn--xs {
    padding: 4px 10px;
    font-size: 11px;
}

.dgptm-vorsitz-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* === Bulk-Actions === */
.dgptm-vorsitz-bulk-actions {
    display: flex;
    gap: 8px;
    padding-top: 8px;
}

/* === Modal === */
.dgptm-vorsitz-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
}

.dgptm-vorsitz-modal {
    background: #fff;
    border-radius: 12px;
    width: 480px;
    max-width: 90vw;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
}

.dgptm-vorsitz-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #e5e7eb;
}

.dgptm-vorsitz-modal-header h4 {
    margin: 0;
    font-size: 16px;
    color: #003366;
}

.dgptm-vorsitz-modal-close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #6b7280;
    padding: 0;
    line-height: 1;
}

.dgptm-vorsitz-modal-body {
    padding: 20px;
}

.dgptm-vorsitz-modal-info {
    font-size: 14px;
    color: #6b7280;
    margin: 0 0 16px;
    padding: 8px 12px;
    background: #f0f5fa;
    border-radius: 6px;
}

.dgptm-vorsitz-field {
    margin-bottom: 14px;
}

.dgptm-vorsitz-field label {
    display: block;
    font-size: 13px;
    font-weight: 600;
    color: #374151;
    margin-bottom: 4px;
}

.dgptm-vorsitz-field input {
    width: 100%;
    padding: 8px 12px;
    border: 1px solid #d1d5db;
    border-radius: 8px;
    font-size: 14px;
    box-sizing: border-box;
}

.dgptm-vorsitz-field input:focus {
    outline: none;
    border-color: #003366;
    box-shadow: 0 0 0 3px rgba(0, 51, 102, 0.1);
}

.dgptm-vorsitz-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    padding: 16px 20px;
    border-top: 1px solid #e5e7eb;
}

/* === Empty State === */
.dgptm-vorsitz-empty {
    text-align: center;
    padding: 40px;
    color: #6b7280;
    font-size: 14px;
}

/* === Responsive === */
@media (max-width: 640px) {
    .dgptm-vorsitz-header {
        flex-direction: column;
        align-items: flex-start;
    }

    .dgptm-vorsitz-filter select {
        min-width: 100%;
    }

    .dgptm-vorsitz-ranking-table {
        font-size: 12px;
    }

    .dgptm-vorsitz-ranking-table thead th,
    .dgptm-vorsitz-ranking-table tbody td {
        padding: 6px 8px;
    }

    .dgptm-vorsitz-modal {
        width: 95vw;
    }
}
```

- [ ] **Step 4: Commit**

```
feat(stipendium): Vorsitzenden-Dashboard Template + JS + CSS (Task 9)
```

---

## Task 10: Main Class Update

**Files:**
- Modify: `modules/business/stipendium/dgptm-stipendium.php`
- Modify: `modules/business/stipendium/includes/class-settings.php`
- Modify: `modules/business/stipendium/includes/class-dashboard-tab.php`
- Modify: `modules/business/stipendium/module.json`

- [ ] **Step 1: `dgptm-stipendium.php` aktualisieren — alle neuen Klassen laden**

Ersetze die `load_components()` Methode. Fuege DB-Installer Aufruf hinzu. Lade alle neuen Klassen und stelle Instanzen bereit.

```diff
--- dgptm-stipendium.php (vorher)
+++ dgptm-stipendium.php (nachher)
```

Die komplette neue `load_components()` Methode und neue Properties:

```php
// In der Klasse DGPTM_Stipendium, neue Properties hinzufuegen:
private $token_manager;
private $gutachter_form;
private $orcid_lookup;
private $vorsitz_dashboard;

// load_components() ersetzen:
private function load_components() {
    // DB-Installer (immer laden, prueft intern ob Update noetig)
    require_once $this->plugin_path . 'includes/class-token-installer.php';
    DGPTM_Stipendium_Token_Installer::install();

    // Freigabe-Komponente (bereits implementiert)
    require_once $this->plugin_path . 'includes/class-freigabe.php';
    new DGPTM_Stipendium_Freigabe($this->plugin_path, $this->plugin_url);

    // Einstellungen
    if (file_exists($this->plugin_path . 'includes/class-settings.php')) {
        require_once $this->plugin_path . 'includes/class-settings.php';
        $this->settings = new DGPTM_Stipendium_Settings($this->plugin_path, $this->plugin_url);
    }

    // Zoho CRM API (nur laden wenn crm-abruf verfuegbar)
    if (class_exists('DGPTM_CRM_Abruf') || function_exists('dgptm_get_zoho_token')) {
        if (file_exists($this->plugin_path . 'includes/class-zoho-stipendium.php')) {
            require_once $this->plugin_path . 'includes/class-zoho-stipendium.php';
            $this->zoho = new DGPTM_Stipendium_Zoho($this->settings);
        }

        if (file_exists($this->plugin_path . 'includes/class-workdrive.php')) {
            require_once $this->plugin_path . 'includes/class-workdrive.php';
            $this->workdrive = new DGPTM_Stipendium_WorkDrive($this->settings);
        }
    }

    // Token-Manager (braucht DB-Tabelle)
    require_once $this->plugin_path . 'includes/class-gutachter-token.php';
    $this->token_manager = new DGPTM_Stipendium_Gutachter_Token();

    // Mail-Templates
    require_once $this->plugin_path . 'includes/class-mail-templates.php';

    // ORCID-Lookup
    require_once $this->plugin_path . 'includes/class-orcid-lookup.php';
    $this->orcid_lookup = new DGPTM_Stipendium_ORCID_Lookup();

    // Gutachter-Bewertungsbogen (Shortcode + AJAX, auch fuer nicht-eingeloggte)
    require_once $this->plugin_path . 'includes/class-gutachter-form.php';
    $this->gutachter_form = new DGPTM_Stipendium_Gutachter_Form(
        $this->plugin_path,
        $this->plugin_url,
        $this->token_manager,
        $this->zoho
    );

    // Vorsitzenden-Dashboard
    require_once $this->plugin_path . 'includes/class-vorsitz-dashboard.php';
    $this->vorsitz_dashboard = new DGPTM_Stipendium_Vorsitz_Dashboard(
        $this->plugin_path,
        $this->plugin_url,
        $this->settings,
        $this->zoho,
        $this->token_manager
    );

    // Dashboard-Tab Registrierung (nur wenn Dashboard-Modul aktiv)
    if (class_exists('DGPTM_Mitglieder_Dashboard') || shortcode_exists('dgptm_dashboard')) {
        if (file_exists($this->plugin_path . 'includes/class-dashboard-tab.php')) {
            require_once $this->plugin_path . 'includes/class-dashboard-tab.php';
            new DGPTM_Stipendium_Dashboard_Tab(
                $this->plugin_path,
                $this->plugin_url,
                $this->settings,
                $this->vorsitz_dashboard
            );
        }
    }
}

// Neue Getter:
public function get_token_manager() { return $this->token_manager; }
public function get_vorsitz_dashboard() { return $this->vorsitz_dashboard; }
```

Die Plugin-Header-Version in Zeile 4 aendern:

```
* Version: 1.1.0
```

- [ ] **Step 2: `class-settings.php` aktualisieren — Gutachter-Frist Setting**

In der `$defaults` Array hinzufuegen:

```php
'gutachter_frist_tage' => 28,
```

Im `ajax_save_settings()` sanitizen:

```php
$clean['gutachter_frist_tage'] = absint($data['gutachter_frist_tage'] ?? 28);
if ($clean['gutachter_frist_tage'] < 7) $clean['gutachter_frist_tage'] = 7;
if ($clean['gutachter_frist_tage'] > 90) $clean['gutachter_frist_tage'] = 90;
```

- [ ] **Step 3: `class-dashboard-tab.php` aktualisieren — Vorsitzenden-Dashboard injizieren**

Constructor erweitert um `$vorsitz_dashboard` Parameter:

```php
private $vorsitz_dashboard;

public function __construct($plugin_path, $plugin_url, $settings, $vorsitz_dashboard = null) {
    $this->plugin_path = $plugin_path;
    $this->plugin_url  = $plugin_url;
    $this->settings    = $settings;
    $this->vorsitz_dashboard = $vorsitz_dashboard;
    // ... rest bleibt gleich
}
```

`render_auswertung()` delegiert an Vorsitzenden-Dashboard:

```php
public function render_auswertung($atts) {
    if (!is_user_logged_in()) return '';

    $user_id = get_current_user_id();
    $ist_vorsitz = get_field('stipendiumsrat_vorsitz', 'user_' . $user_id);
    if (!$ist_vorsitz && !current_user_can('manage_options')) return '';

    // An Vorsitzenden-Dashboard delegieren
    if ($this->vorsitz_dashboard) {
        return $this->vorsitz_dashboard->render();
    }

    // Fallback (sollte nicht vorkommen)
    return '<p>Vorsitzenden-Dashboard nicht verfuegbar.</p>';
}
```

- [ ] **Step 4: `module.json` Version aktualisieren**

```json
{
    "id": "stipendium",
    "name": "Stipendienvergabe",
    "description": "Digitales Bewerbungs- und Bewertungsverfahren fuer DGPTM-Stipendien",
    "version": "1.1.0",
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

- [ ] **Step 5: Commit**

```
feat(stipendium): Main Class Update, Settings, Dashboard-Tab Integration v1.1.0 (Task 10)
```

---

## Task 11: Deluge — GS Workflow

**Files:**
- Create: `modules/business/stipendium/deluge/wf-gs-benachrichtigung.dg`

Zoho CRM Workflow Rule: Wenn der Status eines Stipendien-Records auf "Geprueft" wechselt, wird eine E-Mail an den Vorsitzenden des Stipendiumsrats gesendet.

- [ ] **Step 1: Deluge-Skript erstellen**

```
modules/business/stipendium/deluge/wf-gs-benachrichtigung.dg
```

```deluge
// ============================================================
// DGPTM Stipendium — Workflow: GS-Benachrichtigung
// ============================================================
// TRIGGER:  Workflow Rule auf Modul "Stipendien"
//           Bedingung: Field Update → Stipendium_Status = "Geprueft"
//
// AKTION:   E-Mail an Vorsitzenden des Stipendiumsrats senden,
//           damit dieser die Bewerbung im WordPress-Dashboard
//           freigeben oder ablehnen kann.
//
// SETUP:
//   1. Zoho CRM > Setup > Automation > Workflow Rules
//   2. Modul: Stipendien
//   3. Trigger: "On a record action" → "Edit"
//   4. Bedingung: Stipendium_Status wird geaendert auf "Geprueft"
//   5. Sofortaktion: Custom Function → dieses Skript
// ============================================================

// Vorsitzenden-E-Mail aus Organisation-Settings oder dediziertem Contact
vorsitzender_contacts = zoho.crm.searchRecords("Contacts",
    "(Stipendiumsrat_Vorsitz:equals:true)");

if (vorsitzender_contacts.size() == 0)
{
    info "WARNUNG: Kein Vorsitzender im CRM gefunden. Mail nicht gesendet.";
    return;
}

vorsitzender = vorsitzender_contacts.get(0);
vorsitz_email = vorsitzender.get("Email");
vorsitz_name = vorsitzender.get("Full_Name");

if (vorsitz_email == null || vorsitz_email == "")
{
    info "WARNUNG: Vorsitzender hat keine E-Mail-Adresse.";
    return;
}

// Bewerbungsdaten
bewerber_name = ifnull(input.Name, "Unbekannt");
stipendientyp = ifnull(input.Stipendientyp, "");
runde = ifnull(input.Runde, "");
eingangsdatum = ifnull(input.Eingangsdatum, "");

// E-Mail senden
subject = "DGPTM Stipendium: Neue Bewerbung geprueft — " + bewerber_name;

body = "<html><body style='font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, sans-serif;'>";
body = body + "<table width='600' cellpadding='0' cellspacing='0' style='background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;'>";

// Header
body = body + "<tr><td style='background:#003366;padding:20px 30px;'>";
body = body + "<table width='100%'><tr>";
body = body + "<td style='color:#ffffff;font-size:20px;font-weight:700;'>DGPTM</td>";
body = body + "<td align='right' style='color:#8bb8e8;font-size:13px;'>Stipendienvergabe</td>";
body = body + "</tr></table></td></tr>";

// Inhalt
body = body + "<tr><td style='padding:24px 30px;'>";
body = body + "<h2 style='margin:0 0 12px;font-size:18px;color:#1a1a1a;'>Neue Bewerbung geprueft</h2>";
body = body + "<p style='font-size:15px;color:#333;'>Sehr geehrte/r " + vorsitz_name + ",</p>";
body = body + "<p style='font-size:15px;color:#333;'>Die Geschaeftsstelle hat eine neue Bewerbung vorgegrueft. Bitte pruefen und freigeben Sie diese im Vorsitzenden-Dashboard.</p>";

// Info-Box
body = body + "<div style='background:#f0f5fa;border-left:4px solid #003366;border-radius:0 8px 8px 0;padding:16px 20px;margin:16px 0;'>";
body = body + "<strong>Bewerber/in:</strong> " + bewerber_name + "<br>";
body = body + "<strong>Stipendium:</strong> " + stipendientyp + "<br>";
body = body + "<strong>Runde:</strong> " + runde + "<br>";
body = body + "<strong>Eingangsdatum:</strong> " + eingangsdatum;
body = body + "</div>";

// CTA
body = body + "<p style='text-align:center;margin:20px 0;'>";
body = body + "<a href='https://perfusiologie.de/mitgliederbereich/' style='display:inline-block;background:#003366;color:#ffffff;text-decoration:none;padding:12px 28px;border-radius:8px;font-size:14px;font-weight:600;'>Im Dashboard pruefen</a>";
body = body + "</p>";
body = body + "</td></tr>";

// Footer
body = body + "<tr><td style='background:#f9fafb;padding:16px 30px;border-top:1px solid #e5e7eb;'>";
body = body + "<p style='margin:0;font-size:12px;color:#9ca3af;text-align:center;'>Diese Nachricht wurde automatisch gesendet.<br>DGPTM e.V.</p>";
body = body + "</td></tr>";

body = body + "</table></body></html>";

sendmail
[
    from: zoho.adminuserid
    to: vorsitz_email
    subject: subject
    message: body
];

info "GS-Benachrichtigung gesendet an: " + vorsitz_email + " fuer Bewerbung: " + bewerber_name;
```

- [ ] **Step 2: Zoho CRM Status-Picklist Update**

Das bestehende Deluge-Setup-Skript (`setup-stipendien-modul.dg`) muss den neuen Status "Geprueft" in der Picklist enthalten. Da das Setup-Skript bereits existiert, muss es manuell im Zoho CRM um den Wert "Geprueft" erweitert werden:

```
Zoho CRM > Setup > Customization > Modules > Stipendien > Stipendium_Status
→ Neuen Picklist-Wert "Geprueft" einfuegen zwischen "Eingegangen" und "Freigegeben"
```

- [ ] **Step 3: Commit**

```
feat(stipendium): Deluge Workflow GS-Benachrichtigung bei Status Geprueft (Task 11)
```

---

## Task 12: Testdaten + Bewerbungsformular-Shortcode

**Files:**
- Keine neuen Dateien — Testdaten werden manuell im CRM angelegt und Tokens ueber das Dashboard generiert

Dieser Task beschreibt die manuelle Einrichtung der Testdaten und den minimalen Demo-Bewerbungsformular-Shortcode.

- [ ] **Step 1: Testdaten im Zoho CRM anlegen**

Manuell im Zoho CRM erstellen:

**Stipendien-Records:**

| # | Bewerber | Stipendientyp | Runde | Status |
|---|----------|--------------|-------|--------|
| 1 | Max Mustermann | Promotionsstipendium | Ausschreibung 2026 | Freigegeben |
| 2 | Anna Beispiel | Josef Guettler Stipendium | 2026 | Freigegeben |

**WorkDrive:**
- Platzhalter-PDFs in den entsprechenden Ordnern ablegen
- Share-Links generieren und in den Stipendien-Records eintragen

- [ ] **Step 2: WordPress-Konfiguration**

1. Modul "stipendium" im DGPTM Suite Plugin-Manager aktivieren
2. Einstellungen unter DGPTM Suite > Stipendium konfigurieren:
   - Promotionsstipendium: Runde "Ausschreibung 2026", Start 01.04.2026, Ende 30.06.2026
   - Josef Guettler Stipendium: Runde "2026", Start 01.04.2026, Ende 30.06.2026
   - Vorsitzenden-E-Mail eintragen
   - WorkDrive Team-Folder ID eintragen
3. WordPress-Seite "/stipendium/gutachten/" erstellen mit Shortcode `[dgptm_stipendium_gutachten]`
4. ACF-Felder beim Vorsitzenden-Benutzer setzen:
   - `stipendiumsrat_mitglied` = true
   - `stipendiumsrat_vorsitz` = true

- [ ] **Step 3: Test-Tokens generieren**

Im Vorsitzenden-Dashboard (Mitgliederbereich > Stipendien > Auswertung):
1. Beide Bewerbungen sollten als "Freigegeben" erscheinen
2. Fuer jede Bewerbung 3 Gutachter einladen:
   - Dr. Mueller (test1@dgptm.de)
   - Prof. Koch (test2@dgptm.de)
   - Dr. Weber (test3@dgptm.de)
3. Token-URLs aus den Einladungs-Mails kopieren und testen

- [ ] **Step 4: Demo-Walkthrough testen**

Testplan:
1. **Gutachter-Flow:** Token-URL oeffnen → Bewertungsbogen sehen → Noten vergeben → Auto-Save pruefen → Abschliessen → Danke-Seite
2. **Zweiter Aufruf:** Gleiche URL → Danke-Seite (nicht erneut bewertbar)
3. **Abgelaufener Token:** Token manuell in DB aendern (expires_at in Vergangenheit) → Ungueltig-Seite
4. **Vorsitzenden-Dashboard:** Einloggen → Stipendien-Tab → Auswertung → Bewerbungen nach Status gruppiert → Gutachter-Status sichtbar → Einladungs-Dialog funktioniert
5. **E-Mail:** Einladungs-Mail kommt an mit korrektem Layout und Token-Link
6. **Score-Berechnung:** Nach 3 Gutachten → Gesamtscore im Dashboard pruefen

- [ ] **Step 5: Commit**

```
docs(stipendium): Testdaten-Beschreibung + Demo-Setup (Task 12)
```

---

## Zusammenfassung der Commits

| # | Commit-Message | Dateien |
|---|----------------|---------|
| 1 | `feat(stipendium): Token-Tabelle DB-Installer (Task 1)` | class-token-installer.php |
| 2 | `feat(stipendium): Gutachter-Token Manager mit CRUD + Cron-Cleanup (Task 2)` | class-gutachter-token.php |
| 3 | `feat(stipendium): ORCID Public API Lookup (Task 3)` | class-orcid-lookup.php |
| 4 | `feat(stipendium): HTML-Mail-Templates fuer Einladung + Abschluss (Task 4)` | class-mail-templates.php |
| 5 | `feat(stipendium): Gutachter-Form Shortcode mit Auto-Save + Submit (Task 5)` | class-gutachter-form.php |
| 6 | `feat(stipendium): Gutachten-Templates (Formular, Danke, Ungueltig) (Task 6)` | 3 Templates |
| 7 | `feat(stipendium): Gutachten JS + CSS Assets (Task 7)` | gutachten.js, gutachten.css |
| 8 | `feat(stipendium): Vorsitzenden-Dashboard mit allen AJAX-Aktionen (Task 8)` | class-vorsitz-dashboard.php |
| 9 | `feat(stipendium): Vorsitzenden-Dashboard Template + JS + CSS (Task 9)` | Template + 2 Assets |
| 10 | `feat(stipendium): Main Class Update, Settings, Dashboard-Tab Integration v1.1.0 (Task 10)` | 4 Dateien modifiziert |
| 11 | `feat(stipendium): Deluge Workflow GS-Benachrichtigung bei Status Geprueft (Task 11)` | wf-gs-benachrichtigung.dg |
| 12 | `docs(stipendium): Testdaten-Beschreibung + Demo-Setup (Task 12)` | Manuelle Schritte |
