# EIV-Fobi Sync Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Täglicher automatischer Abruf von BÄK-Fortbildungsdaten über die EIV-Fobi API, Caching von Veranstaltungen, und Erstellung von `fortbildung`-Posts mit EBCP-Punkten.

**Architecture:** Neue Datei `eiv-fobi-sync.php` im EFN-Manager Modul. Trennung: EFN-Manager = Datenquelle (Token, API, Cache), Fortbildungsverwaltung = Consumer (CPT + Mapping). Token-Kette: Zoho OAuth → Zoho Function `test_baek` → JWT → BÄK API.

**Tech Stack:** WordPress 5.8+ / PHP 7.4+ / ACF / Zoho OAuth (crm-abruf) / BÄK EIV-Fobi REST API

**Spec:** `docs/superpowers/specs/2026-03-24-eiv-fobi-sync-design.md`

---

## File Structure

| Action | Datei | Verantwortung |
|--------|-------|---------------|
| Create | `modules/utilities/efn-manager/eiv-fobi-sync.php` | Gesamte EIV-Sync-Logik: CPT, Token, API-Client, Cache, Import, Cron, Admin-UI |
| Modify | `modules/utilities/efn-manager/dgptm-efn-manager.php:1306-1309` | `require_once` der neuen Datei vor Initialisierung |
| Modify | `modules/utilities/efn-manager/module.json` | `optional_dependencies`, `category` korrigieren |

Die neue Datei folgt dem Muster des Fortbildungs-Moduls (mehrere Dateien pro Modul, z.B. `fortbildungsupload.php`, `fortbildung-csv-import.php`).

---

### Task 1: module.json aktualisieren

**Files:**
- Modify: `modules/utilities/efn-manager/module.json`

- [ ] **Step 1: module.json anpassen**

```json
{
    "id": "efn-manager",
    "name": "DGPTM - EFN Manager",
    "description": "Zentrales Management-System für die Einheitliche Fortbildungsnummer (EFN). Umfasst: JsBarcode-basierte Code128-Barcodes, A4-Aufkleberbogen-Generierung (7 Vorlagen mit FPDF), Self-Service-Kiosk mit Scanner-Integration, PrintNode Silent Printing, Benutzerprofil-Verwaltung, Zoho CRM-Integration, Webhook-Verarbeitung, präzise Druckkalibierung und EIV-Fobi-Sync (täglicher Abruf von BÄK-Fortbildungsdaten).",
    "version": "1.1.0",
    "author": "Sebastian Melzer",
    "main_file": "dgptm-efn-manager.php",
    "dependencies": [
        "crm-abruf"
    ],
    "optional_dependencies": [
        "fortbildung"
    ],
    "wp_dependencies": {
        "plugins": [
            "advanced-custom-fields"
        ]
    },
    "requires_php": "7.4",
    "requires_wp": "5.8",
    "category": "utilities",
    "icon": "dashicons-id-alt",
    "active": false,
    "can_export": true
}
```

Änderungen: `version` 1.0.3→1.1.0, `optional_dependencies` + `["fortbildung"]`, `wp_dependencies.plugins` + ACF, `category` "fortbildung"→"utilities", `description` erweitert.

- [ ] **Step 2: Commit**

```bash
git add modules/utilities/efn-manager/module.json
git commit -m "chore(efn-manager): update module.json for EIV-Fobi sync"
```

---

### Task 2: Neue Datei anlegen — CPT-Registrierung + Konstanten

**Files:**
- Create: `modules/utilities/efn-manager/eiv-fobi-sync.php`

- [ ] **Step 1: Datei mit Header, Konstanten und CPT-Registrierung erstellen**

```php
<?php
/**
 * EIV-Fobi Sync – BÄK-Fortbildungsdaten automatisch abrufen
 *
 * Täglicher Bulk-Abruf über die BÄK EIV-Fobi API:
 * Zoho OAuth → test_baek JWT → fobi_punkte → Event-Cache → fortbildung-Posts
 *
 * @package DGPTM\EFN_Manager
 * @since   1.1.0
 */
if ( ! defined( 'ABSPATH' ) ) exit;

/* ============================================================
 * Konstanten
 * ============================================================ */
define( 'DGPTM_EIV_OPTION_KEY',  'dgptm_eiv_sync_settings' );
define( 'DGPTM_EIV_CRON_HOOK',   'dgptm_eiv_daily_sync' );

function dgptm_eiv_default_settings() {
    return [
        'batch_enabled'  => '0',
        'start_date'     => '',
        'zoho_function'  => 'test_baek',
        'api_base'       => 'https://backend.eiv-fobi.de',
        'last_call'      => '',
    ];
}

function dgptm_eiv_get_settings() {
    return wp_parse_args(
        get_option( DGPTM_EIV_OPTION_KEY, [] ),
        dgptm_eiv_default_settings()
    );
}

/* ============================================================
 * CPT: eiv_event_cache
 * ============================================================ */
add_action( 'init', function() {
    if ( post_type_exists( 'eiv_event_cache' ) ) return;
    register_post_type( 'eiv_event_cache', [
        'labels' => [
            'name'               => 'EIV Event-Cache',
            'singular_name'      => 'EIV Event',
            'menu_name'          => 'EIV Event-Cache',
            'all_items'          => 'Alle Events',
            'search_items'       => 'Events suchen',
            'not_found'          => 'Keine gecachten Events',
        ],
        'public'              => false,
        'publicly_queryable'  => false,
        'show_ui'             => true,
        'show_in_menu'        => 'options-general.php',
        'query_var'           => false,
        'rewrite'             => false,
        'capability_type'     => 'post',
        'has_archive'         => false,
        'hierarchical'        => false,
        'supports'            => [ 'title', 'custom-fields' ],
        'show_in_rest'        => false,
    ]);
});
```

- [ ] **Step 2: Commit**

```bash
git add modules/utilities/efn-manager/eiv-fobi-sync.php
git commit -m "feat(eiv-sync): add eiv-fobi-sync.php with CPT registration and constants"
```

---

### Task 3: BÄK Token Service

**Files:**
- Modify: `modules/utilities/efn-manager/eiv-fobi-sync.php`

- [ ] **Step 1: Token-Funktion hinzufügen**

Anhängen nach der CPT-Registrierung:

```php
/* ============================================================
 * BÄK Token Service
 * Zoho OAuth → Zoho Function "test_baek" → JWT Bearer Token
 * ============================================================ */

/**
 * Holt einen frischen BÄK JWT Token via Zoho Function.
 *
 * @return string|WP_Error  JWT Token oder WP_Error bei Fehler
 */
function dgptm_eiv_get_baek_token() {
    // 1) Zoho OAuth Token aus crm-abruf
    if ( ! class_exists( 'DGPTM_Zoho_Plugin' ) ) {
        return new WP_Error( 'eiv_no_zoho', 'crm-abruf Modul nicht verfügbar.' );
    }
    $zoho = DGPTM_Zoho_Plugin::get_instance();
    $oauth_token = $zoho->get_oauth_token();
    if ( is_wp_error( $oauth_token ) ) {
        return new WP_Error( 'eiv_oauth_fail', 'Zoho OAuth fehlgeschlagen: ' . $oauth_token->get_error_message() );
    }

    // 2) Zoho Function aufrufen → BÄK JWT
    $settings = dgptm_eiv_get_settings();
    $func_name = sanitize_text_field( $settings['zoho_function'] );
    $zoho_url = "https://www.zohoapis.eu/crm/v7/functions/{$func_name}/actions/execute?auth_type=oauth";

    $resp = wp_remote_get( $zoho_url, [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Zoho-oauthtoken ' . $oauth_token,
            'Accept'        => 'application/json',
        ],
    ]);

    if ( is_wp_error( $resp ) ) {
        return new WP_Error( 'eiv_zoho_func_fail', 'Zoho Function HTTP-Fehler: ' . $resp->get_error_message() );
    }

    $code = wp_remote_retrieve_response_code( $resp );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'eiv_zoho_func_http', "Zoho Function HTTP {$code}: " . wp_remote_retrieve_body( $resp ) );
    }

    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( ! is_array( $body ) || empty( $body['details']['output'] ) ) {
        return new WP_Error( 'eiv_zoho_func_parse', 'Zoho Function: Kein Token in Antwort. Body: ' . wp_remote_retrieve_body( $resp ) );
    }

    return $body['details']['output'];
}
```

- [ ] **Step 2: Commit**

```bash
git add modules/utilities/efn-manager/eiv-fobi-sync.php
git commit -m "feat(eiv-sync): add BÄK token service via Zoho function"
```

---

### Task 4: EIV API Client

**Files:**
- Modify: `modules/utilities/efn-manager/eiv-fobi-sync.php`

- [ ] **Step 1: API-Abruf-Funktionen hinzufügen**

```php
/* ============================================================
 * EIV API Client
 * ============================================================ */

/**
 * Ruft Fortbildungspunkte (Teilnahmen) von der BÄK ab.
 *
 * @param string $jwt    Bearer Token
 * @param string $since  ISO 8601 Zeitstempel (since-Filter)
 * @return array|WP_Error  Array von Teilnahme-Datensätzen
 */
function dgptm_eiv_fetch_fobi_punkte( $jwt, $since ) {
    $settings = dgptm_eiv_get_settings();
    $base     = untrailingslashit( $settings['api_base'] );
    $url      = $base . '/aek/oidc/fobi/fobi_punkte?' . http_build_query([
        'limit'  => 0,
        'offset' => 0,
        'since'  => $since,
    ]);

    $resp = wp_remote_get( $url, [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $jwt,
            'Accept'        => 'application/json',
        ],
    ]);

    if ( is_wp_error( $resp ) ) {
        return new WP_Error( 'eiv_punkte_http', 'fobi_punkte HTTP-Fehler: ' . $resp->get_error_message() );
    }

    $code = wp_remote_retrieve_response_code( $resp );
    if ( $code === 429 ) {
        return new WP_Error( 'eiv_punkte_rate_limit', 'fobi_punkte: Rate Limit (429) erreicht.' );
    }
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'eiv_punkte_fail', "fobi_punkte HTTP {$code}: " . wp_remote_retrieve_body( $resp ) );
    }

    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( ! is_array( $data ) ) {
        return new WP_Error( 'eiv_punkte_parse', 'fobi_punkte: Antwort nicht parsebar.' );
    }

    return $data;
}

/**
 * Ruft Veranstaltungsdetails für eine VNR ab.
 *
 * @param string $jwt  Bearer Token
 * @param string $vnr  Veranstaltungsnummer
 * @return array|WP_Error  Veranstaltungs-Daten (erstes Element) oder WP_Error
 */
function dgptm_eiv_fetch_veranstaltung( $jwt, $vnr ) {
    $settings = dgptm_eiv_get_settings();
    $base     = untrailingslashit( $settings['api_base'] );
    $url      = $base . '/aek/oidc/fobi/anerkannte_veranstaltungen?' . http_build_query([
        'vnr' => $vnr,
    ]);

    $resp = wp_remote_get( $url, [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Bearer ' . $jwt,
            'Accept'        => 'application/json',
        ],
    ]);

    if ( is_wp_error( $resp ) ) {
        return new WP_Error( 'eiv_event_http', "Veranstaltung {$vnr} HTTP-Fehler: " . $resp->get_error_message() );
    }

    $code = wp_remote_retrieve_response_code( $resp );
    if ( $code < 200 || $code >= 300 ) {
        return new WP_Error( 'eiv_event_fail', "Veranstaltung {$vnr} HTTP {$code}" );
    }

    $data = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( ! is_array( $data ) || empty( $data ) ) {
        return new WP_Error( 'eiv_event_empty', "Veranstaltung {$vnr}: Keine Daten." );
    }

    // API gibt Array zurück — erstes Element nehmen
    return is_array( $data[0] ?? null ) ? $data[0] : $data;
}
```

- [ ] **Step 2: Commit**

```bash
git add modules/utilities/efn-manager/eiv-fobi-sync.php
git commit -m "feat(eiv-sync): add EIV API client (fobi_punkte + anerkannte_veranstaltungen)"
```

---

### Task 5: Event-Cache Logik

**Files:**
- Modify: `modules/utilities/efn-manager/eiv-fobi-sync.php`

- [ ] **Step 1: Cache-Lookup und Cache-Write Funktionen hinzufügen**

```php
/* ============================================================
 * Event-Cache (eiv_event_cache)
 * ============================================================ */

/**
 * Sucht eine Veranstaltung im Cache nach VNR.
 *
 * @param string $vnr  Veranstaltungsnummer
 * @return array|null  ACF-Felder als Array oder null bei Cache-Miss
 */
function dgptm_eiv_cache_lookup( $vnr ) {
    $q = new WP_Query([
        'post_type'      => 'eiv_event_cache',
        'posts_per_page' => 1,
        'post_status'    => 'publish',
        'meta_query'     => [
            [ 'key' => 'vnr', 'value' => $vnr, 'compare' => '=' ],
        ],
        'fields'         => 'ids',
    ]);

    if ( ! $q->have_posts() ) {
        wp_reset_postdata();
        return null;
    }

    $pid = $q->posts[0];
    wp_reset_postdata();
    return [
        'post_id'         => $pid,
        'vnr'             => get_field( 'vnr', $pid ),
        'title'           => get_field( 'title', $pid ),
        'typeCode'        => get_field( 'typeCode', $pid ),
        'date'            => get_field( 'date', $pid ),
        'endDate'         => get_field( 'endDate', $pid ),
        'durationMinutes' => (int) get_field( 'durationMinutes', $pid ),
        'location'        => get_field( 'location', $pid ),
        'provider'        => get_field( 'provider', $pid ),
        'points'          => (float) get_field( 'points', $pid ),
        'status'          => get_field( 'status', $pid ),
    ];
}

/**
 * Speichert eine Veranstaltung im Cache.
 *
 * @param array $event  Veranstaltungsdaten aus der BÄK API
 * @return int|WP_Error  Post-ID oder WP_Error
 */
function dgptm_eiv_cache_store( $event ) {
    $vnr   = sanitize_text_field( $event['vnr'] ?? '' );
    $thema = sanitize_text_field( $event['thema'] ?? '' );

    // Doppel-Check: existiert bereits?
    if ( dgptm_eiv_cache_lookup( $vnr ) ) {
        return new WP_Error( 'eiv_cache_exists', "VNR {$vnr} bereits im Cache." );
    }

    $pid = wp_insert_post([
        'post_title'  => $thema ?: "VNR {$vnr}",
        'post_type'   => 'eiv_event_cache',
        'post_status' => 'publish',
        'post_author' => 1,
    ]);

    if ( is_wp_error( $pid ) ) return $pid;

    // Beginn/Ende parsen
    $beginn_raw = $event['beginn'] ?? '';
    $ende_raw   = $event['ende'] ?? '';
    $date_str   = $beginn_raw ? date( 'Y-m-d', strtotime( $beginn_raw ) ) : '';
    $end_str    = $ende_raw   ? date( 'Y-m-d', strtotime( $ende_raw ) )   : '';

    // Dauer berechnen (Minuten)
    $duration = 0;
    if ( $beginn_raw && $ende_raw ) {
        $diff = strtotime( $ende_raw ) - strtotime( $beginn_raw );
        if ( $diff > 0 ) $duration = (int) round( $diff / 60 );
    }

    // Storniert-Status
    $storniert = ! empty( $event['storniert'] ) && $event['storniert'] !== false;

    update_field( 'vnr',             $vnr,                                    $pid );
    update_field( 'title',           $thema,                                  $pid );
    update_field( 'typeCode',        sanitize_text_field( $event['kategorie'] ?? '' ), $pid );
    update_field( 'date',            $date_str,                               $pid );
    update_field( 'endDate',         $end_str,                                $pid );
    update_field( 'durationMinutes', $duration,                               $pid );
    update_field( 'location',        sanitize_text_field( $event['ort'] ?? '' ),       $pid );
    update_field( 'points',          floatval( $event['punkte_basis'] ?? 0 ), $pid );
    update_field( 'status',          $storniert ? 'storniert' : 'aktiv',      $pid );

    return $pid;
}

/**
 * Holt Event-Daten: erst Cache, dann API-Abruf + Cache-Speicherung.
 *
 * @param string $jwt  BÄK JWT
 * @param string $vnr  Veranstaltungsnummer
 * @return array|WP_Error  Event-Daten Array oder WP_Error
 */
function dgptm_eiv_get_event( $jwt, $vnr ) {
    // Cache-Lookup
    $cached = dgptm_eiv_cache_lookup( $vnr );
    if ( $cached ) return $cached;

    // API-Abruf
    $api_event = dgptm_eiv_fetch_veranstaltung( $jwt, $vnr );
    if ( is_wp_error( $api_event ) ) return $api_event;

    // Cachen
    $store_result = dgptm_eiv_cache_store( $api_event );
    if ( is_wp_error( $store_result ) ) {
        // Cache-Fehler loggen, aber Daten trotzdem zurückgeben
        if ( function_exists( 'dgptm_crm_log' ) ) {
            dgptm_crm_log( 'EIV-Cache-Fehler: ' . $store_result->get_error_message() );
        }
    }

    // Nochmal aus Cache lesen (für einheitliches Format)
    $cached = dgptm_eiv_cache_lookup( $vnr );
    if ( $cached ) return $cached;

    // Fallback: API-Daten direkt aufbereiten
    return [
        'vnr'             => $vnr,
        'title'           => $api_event['thema'] ?? '',
        'typeCode'        => $api_event['kategorie'] ?? '',
        'date'            => isset($api_event['beginn']) ? date('Y-m-d', strtotime($api_event['beginn'])) : '',
        'endDate'         => isset($api_event['ende']) ? date('Y-m-d', strtotime($api_event['ende'])) : '',
        'durationMinutes' => 0,
        'location'        => $api_event['ort'] ?? '',
        'points'          => floatval( $api_event['punkte_basis'] ?? 0 ),
        'status'          => ! empty($api_event['storniert']) ? 'storniert' : 'aktiv',
    ];
}
```

- [ ] **Step 2: Commit**

```bash
git add modules/utilities/efn-manager/eiv-fobi-sync.php
git commit -m "feat(eiv-sync): add event cache (lookup, store, get with API fallback)"
```

---

### Task 6: Punkte-Berechnung + Dubletten-Check

**Files:**
- Modify: `modules/utilities/efn-manager/eiv-fobi-sync.php`

- [ ] **Step 1: Punkte-Berechnung und Dubletten-Check hinzufügen**

```php
/* ============================================================
 * Punkte-Berechnung (nutzt Mapping aus fobi_aek_settings)
 * ============================================================ */

/**
 * Berechnet EBCP-Punkte basierend auf Typcode und Dauer.
 *
 * @param string $type_code     Veranstaltungs-Typcode (A-K)
 * @param int    $duration_min  Dauer in Minuten
 * @return array  ['points' => float, 'label' => string]
 */
function dgptm_eiv_calculate_points( $type_code, $duration_min ) {
    $type_code = strtoupper( trim( $type_code ) );

    // Mapping aus Fortbildungs-Einstellungen laden
    $fobi_settings = [];
    if ( defined( 'FOBI_AEK_OPTION_KEY' ) ) {
        $fobi_settings = get_option( FOBI_AEK_OPTION_KEY, [] );
    }
    $mapping_json = $fobi_settings['mapping_json'] ?? '[]';
    $mapping = json_decode( $mapping_json, true );
    if ( ! is_array( $mapping ) ) $mapping = [];

    // Mapping nach Code indexieren
    $map = [];
    foreach ( $mapping as $row ) {
        $c = strtoupper( trim( $row['code'] ?? '' ) );
        if ( $c !== '' ) $map[ $c ] = $row;
    }

    if ( ! isset( $map[ $type_code ] ) ) {
        return [ 'points' => 0.0, 'label' => 'Unbekannt' ];
    }

    $m     = $map[ $type_code ];
    $calc  = strtolower( $m['calc'] ?? 'fixed' );
    $base  = floatval( $m['points'] ?? 0 );
    $label = $m['label'] ?? 'Unbekannt';

    switch ( $calc ) {
        case 'unit':
            $unit  = max( 1, intval( $m['unit_minutes'] ?? 45 ) );
            $units = $duration_min > 0 ? ceil( $duration_min / $unit ) : 1;
            $pts   = $base * $units;
            break;
        case 'per_hour':
            $hours = $duration_min > 0 ? ( $duration_min / 60.0 ) : 1.0;
            $pts   = round( $base * $hours, 1 );
            break;
        default: // fixed
            $pts = $base;
            break;
    }

    return [ 'points' => $pts, 'label' => $label ];
}

/* ============================================================
 * Dubletten-Check
 * ============================================================ */

/**
 * Prüft ob eine Fortbildung für User+VNR bereits existiert.
 * Checkt sowohl ACF-Feld 'vnr' als auch altes Meta 'aek_vnr'.
 *
 * @param int    $user_id  WordPress User-ID
 * @param string $vnr      Veranstaltungsnummer
 * @return bool  true wenn bereits vorhanden
 */
function dgptm_eiv_fortbildung_exists( $user_id, $vnr ) {
    $q = new WP_Query([
        'post_type'      => 'fortbildung',
        'posts_per_page' => 1,
        'post_status'    => 'any',
        'fields'         => 'ids',
        'meta_query'     => [
            'relation' => 'AND',
            [
                'relation' => 'OR',
                [ 'key' => 'vnr',     'value' => $vnr,     'compare' => '=' ],
                [ 'key' => 'aek_vnr', 'value' => $vnr,     'compare' => '=' ],
            ],
            [ 'key' => 'user', 'value' => $user_id, 'compare' => '=' ],
        ],
    ]);

    $found = $q->have_posts();
    wp_reset_postdata();
    return $found;
}
```

- [ ] **Step 2: Commit**

```bash
git add modules/utilities/efn-manager/eiv-fobi-sync.php
git commit -m "feat(eiv-sync): add points calculation and duplicate check"
```

---

### Task 7: Haupt-Sync-Funktion

**Files:**
- Modify: `modules/utilities/efn-manager/eiv-fobi-sync.php`

- [ ] **Step 1: Zentrale Sync-Funktion implementieren**

```php
/* ============================================================
 * Haupt-Sync-Funktion
 * ============================================================ */

/**
 * Führt den kompletten EIV-Fobi Sync durch.
 *
 * @param string|null $since_override  Optionales Override-Datum (ISO 8601). Null = automatisch.
 * @return array  Ergebnis-Array mit 'logs', 'imported', 'skipped', 'errors', 'results'
 */
function dgptm_eiv_run_sync( $since_override = null ) {
    $result = [
        'logs'     => [],
        'imported' => 0,
        'skipped'  => 0,
        'errors'   => 0,
        'results'  => [],  // Für Ergebnistabelle
    ];

    $log = function( $msg ) use ( &$result ) {
        $result['logs'][] = $msg;
        if ( function_exists( 'dgptm_crm_log' ) ) {
            dgptm_crm_log( 'EIV-Sync: ' . $msg );
        }
    };

    // Voraussetzungen prüfen
    if ( ! function_exists( 'update_field' ) ) {
        $log( 'ABBRUCH: ACF (Advanced Custom Fields) nicht verfügbar.' );
        return $result;
    }
    if ( ! post_type_exists( 'fortbildung' ) ) {
        $log( 'ABBRUCH: CPT "fortbildung" nicht registriert. Ist das Fortbildungs-Modul aktiv?' );
        return $result;
    }

    // Since-Datum ermitteln
    $settings = dgptm_eiv_get_settings();
    if ( $since_override ) {
        $since = $since_override;
    } elseif ( ! empty( $settings['last_call'] ) ) {
        $since = $settings['last_call'];
    } elseif ( ! empty( $settings['start_date'] ) ) {
        $since = date( 'c', strtotime( $settings['start_date'] ) );
    } else {
        $log( 'ABBRUCH: Kein Startdatum und kein letzter Abruf konfiguriert.' );
        return $result;
    }
    $log( "Starte Sync seit: {$since}" );

    // 1) BÄK Token holen
    $jwt = dgptm_eiv_get_baek_token();
    if ( is_wp_error( $jwt ) ) {
        $log( 'ABBRUCH Token-Fehler: ' . $jwt->get_error_message() );
        return $result;
    }
    $log( 'BÄK JWT Token erhalten.' );

    // 2) Teilnahmen abrufen
    $teilnahmen = dgptm_eiv_fetch_fobi_punkte( $jwt, $since );
    if ( is_wp_error( $teilnahmen ) ) {
        $log( 'ABBRUCH API-Fehler: ' . $teilnahmen->get_error_message() );
        return $result;
    }
    $count = count( $teilnahmen );
    $log( "{$count} Teilnahme-Datensätze abgerufen." );

    if ( $count === 0 ) {
        // Auch bei 0 Ergebnissen lastCall aktualisieren
        $settings['last_call'] = wp_date( 'c' );
        update_option( DGPTM_EIV_OPTION_KEY, $settings );
        $log( 'Keine neuen Teilnahmen. lastCall aktualisiert.' );
        return $result;
    }

    // 3) Teilnahmen verarbeiten
    foreach ( $teilnahmen as $tn ) {
        $efn = trim( (string) ( $tn['efn'] ?? '' ) );
        $vnr = trim( (string) ( $tn['vnr'] ?? '' ) );

        if ( $vnr === '' ) {
            $result['skipped']++;
            $result['results'][] = [
                'user' => '—', 'event' => '—', 'points' => 0,
                'vnr' => '—', 'status' => 'Übersprungen (keine VNR)',
            ];
            continue;
        }

        // User finden
        $users = get_users([
            'meta_key'   => 'EFN',
            'meta_value' => $efn,
            'number'     => 1,
            'fields'     => [ 'ID', 'display_name' ],
        ]);
        if ( empty( $users ) ) {
            $result['skipped']++;
            $result['results'][] = [
                'user' => "EFN {$efn} (kein User)", 'event' => $vnr,
                'points' => 0, 'vnr' => $vnr,
                'status' => 'Übersprungen (kein User)',
            ];
            continue;
        }
        $wp_user = $users[0];

        // Dubletten-Check
        if ( dgptm_eiv_fortbildung_exists( $wp_user->ID, $vnr ) ) {
            $result['skipped']++;
            $result['results'][] = [
                'user' => $wp_user->display_name, 'event' => $vnr,
                'points' => 0, 'vnr' => $vnr,
                'status' => 'Übersprungen (Dublette)',
            ];
            continue;
        }

        // Event-Daten holen (Cache oder API)
        $event = dgptm_eiv_get_event( $jwt, $vnr );
        if ( is_wp_error( $event ) ) {
            $result['errors']++;
            $log( "Fehler bei VNR {$vnr}: " . $event->get_error_message() );
            $result['results'][] = [
                'user' => $wp_user->display_name, 'event' => $vnr,
                'points' => 0, 'vnr' => $vnr,
                'status' => 'Fehler: ' . $event->get_error_message(),
            ];
            continue;
        }

        // Storniert?
        if ( ( $event['status'] ?? '' ) === 'storniert' ) {
            $result['skipped']++;
            $result['results'][] = [
                'user' => $wp_user->display_name,
                'event' => $event['title'] ?? $vnr,
                'points' => 0, 'vnr' => $vnr,
                'status' => 'Übersprungen (storniert)',
            ];
            continue;
        }

        // Punkte berechnen
        $type_code = $event['typeCode'] ?? '';
        $duration  = (int) ( $event['durationMinutes'] ?? 0 );
        $calc      = dgptm_eiv_calculate_points( $type_code, $duration );

        // Fortbildung erstellen
        $pid = wp_insert_post([
            'post_title'  => ( $event['title'] ?? '' ) ?: 'BÄK-Veranstaltung ' . $vnr,
            'post_type'   => 'fortbildung',
            'post_status' => 'publish',
            'post_author' => 1,
        ]);

        if ( is_wp_error( $pid ) || ! $pid ) {
            $result['errors']++;
            $log( "Fehler beim Erstellen: VNR {$vnr}" );
            continue;
        }

        $date_store = ( $event['date'] ?? '' ) ?: current_time( 'Y-m-d' );
        update_field( 'user',        $wp_user->ID,           $pid );
        update_field( 'date',        $date_store,             $pid );
        update_field( 'location',    $event['location'] ?? '',$pid );
        update_field( 'points',      floatval( $calc['points'] ), $pid );
        update_field( 'type',        $calc['label'],          $pid );
        update_field( 'vnr',         $vnr,                    $pid );
        update_field( 'freigegeben', true,                    $pid );

        $result['imported']++;
        $result['results'][] = [
            'user'   => $wp_user->display_name,
            'event'  => $event['title'] ?? $vnr,
            'points' => $calc['points'],
            'vnr'    => $vnr,
            'status' => 'Importiert',
        ];
    }

    // 4) lastCall aktualisieren (nur wenn KEINE Fehler aufgetreten)
    if ( $result['errors'] === 0 ) {
        $settings['last_call'] = wp_date( 'c' );
        update_option( DGPTM_EIV_OPTION_KEY, $settings );
        $log( 'lastCall aktualisiert.' );
    } else {
        $log( sprintf( 'lastCall NICHT aktualisiert wegen %d Fehler – nächster Lauf wiederholt ab gleichem Zeitpunkt.', $result['errors'] ) );
    }

    $log( sprintf(
        'Sync abgeschlossen: %d importiert, %d übersprungen, %d Fehler.',
        $result['imported'], $result['skipped'], $result['errors']
    ));

    return $result;
}
```

- [ ] **Step 2: Commit**

```bash
git add modules/utilities/efn-manager/eiv-fobi-sync.php
git commit -m "feat(eiv-sync): add main sync function with full pipeline"
```

---

### Task 8: Cron-Scheduling

**Files:**
- Modify: `modules/utilities/efn-manager/eiv-fobi-sync.php`

- [ ] **Step 1: Cron-Verwaltung hinzufügen**

```php
/* ============================================================
 * Cron-Scheduling
 * ============================================================ */

/**
 * Plant den täglichen Cron-Job (oder entfernt ihn).
 */
function dgptm_eiv_reschedule_cron( $settings = null ) {
    if ( ! $settings ) $settings = dgptm_eiv_get_settings();

    $ts = wp_next_scheduled( DGPTM_EIV_CRON_HOOK );
    if ( $ts ) wp_unschedule_event( $ts, DGPTM_EIV_CRON_HOOK );

    if ( $settings['batch_enabled'] !== '1' ) return;

    wp_schedule_event( time() + 60, 'daily', DGPTM_EIV_CRON_HOOK );
}

// Cron-Handler
add_action( DGPTM_EIV_CRON_HOOK, function() {
    $settings = dgptm_eiv_get_settings();
    if ( $settings['batch_enabled'] !== '1' ) return;

    $result = dgptm_eiv_run_sync(); // null = automatisches since-Datum

    if ( function_exists( 'dgptm_crm_log' ) ) {
        dgptm_crm_log( sprintf(
            'EIV-Sync Cron: %d importiert, %d übersprungen, %d Fehler.',
            $result['imported'], $result['skipped'], $result['errors']
        ));
    }
});

// Cron bei Plugin-Laden prüfen/einplanen
add_action( 'init', function() {
    $settings = dgptm_eiv_get_settings();
    if ( $settings['batch_enabled'] === '1' && ! wp_next_scheduled( DGPTM_EIV_CRON_HOOK ) ) {
        wp_schedule_event( time() + 60, 'daily', DGPTM_EIV_CRON_HOOK );
    }
});
```

- [ ] **Step 2: Commit**

```bash
git add modules/utilities/efn-manager/eiv-fobi-sync.php
git commit -m "feat(eiv-sync): add daily cron scheduling"
```

---

### Task 9: Admin-Settings UI + AJAX-Handler

**Files:**
- Modify: `modules/utilities/efn-manager/eiv-fobi-sync.php`

- [ ] **Step 1: Admin-Menü, Settings-Seite und AJAX-Handler hinzufügen**

```php
/* ============================================================
 * Admin-UI: Settings & manueller Sync
 * ============================================================ */

add_action( 'admin_menu', function() {
    add_submenu_page(
        'options-general.php',
        'EIV-Fobi Sync',
        'EIV-Fobi Sync',
        'manage_options',
        'dgptm-eiv-sync',
        'dgptm_eiv_render_admin_page'
    );
});

// AJAX: Manueller Sync
add_action( 'wp_ajax_dgptm_eiv_manual_sync', function() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
    }
    check_ajax_referer( 'dgptm_eiv_sync_nonce' );

    $since = sanitize_text_field( $_POST['since_date'] ?? '' );
    if ( $since ) {
        $since = date( 'c', strtotime( $since ) );
    } else {
        $since = null; // automatisch
    }

    $result = dgptm_eiv_run_sync( $since );
    wp_send_json_success( $result );
});

// Settings speichern
add_action( 'admin_init', function() {
    if ( ! isset( $_POST['dgptm_eiv_save'] ) ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    check_admin_referer( 'dgptm_eiv_settings_save' );

    $settings = dgptm_eiv_get_settings();
    $settings['batch_enabled']  = isset( $_POST['eiv_batch_enabled'] ) ? '1' : '0';
    $settings['start_date']     = sanitize_text_field( $_POST['eiv_start_date'] ?? '' );
    $settings['zoho_function']  = sanitize_text_field( $_POST['eiv_zoho_function'] ?? 'test_baek' );
    $settings['api_base']       = esc_url_raw( $_POST['eiv_api_base'] ?? 'https://backend.eiv-fobi.de' );

    update_option( DGPTM_EIV_OPTION_KEY, $settings );
    dgptm_eiv_reschedule_cron( $settings );

    add_settings_error( 'dgptm_eiv', 'saved', 'Einstellungen gespeichert.', 'success' );
});

function dgptm_eiv_render_admin_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $settings = dgptm_eiv_get_settings();
    $nonce_sync = wp_create_nonce( 'dgptm_eiv_sync_nonce' );
    $next_cron  = wp_next_scheduled( DGPTM_EIV_CRON_HOOK );
    ?>
    <div class="wrap">
        <h1>EIV-Fobi Sync</h1>
        <?php settings_errors( 'dgptm_eiv' ); ?>

        <h2 class="nav-tab-wrapper">
            <a href="#tab-settings" class="nav-tab nav-tab-active">Einstellungen</a>
            <a href="#tab-sync" class="nav-tab">Manueller Abruf</a>
        </h2>

        <!-- Tab: Einstellungen -->
        <div id="tab-settings" class="dgptm-eiv-tab">
            <form method="post">
                <?php wp_nonce_field( 'dgptm_eiv_settings_save' ); ?>
                <table class="form-table">
                    <tr>
                        <th>Täglicher Abruf</th>
                        <td><label><input type="checkbox" name="eiv_batch_enabled" value="1" <?php checked( $settings['batch_enabled'], '1' ); ?>> Aktiviert</label>
                            <?php if ( $next_cron ) : ?>
                                <p class="description">Nächster Lauf: <?php echo date_i18n( 'd.m.Y H:i', $next_cron ); ?></p>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Startdatum</th>
                        <td><input type="date" name="eiv_start_date" value="<?php echo esc_attr( $settings['start_date'] ); ?>" class="regular-text">
                            <p class="description">Ab diesem Datum werden beim ersten Lauf Daten abgerufen.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Zoho-Funktion</th>
                        <td><input type="text" name="eiv_zoho_function" value="<?php echo esc_attr( $settings['zoho_function'] ); ?>" class="regular-text">
                            <p class="description">Name der Zoho CRM Function für den BÄK-Token.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>BÄK API Base-URL</th>
                        <td><input type="url" name="eiv_api_base" value="<?php echo esc_attr( $settings['api_base'] ); ?>" class="regular-text"></td>
                    </tr>
                    <tr>
                        <th>Letzter Abruf</th>
                        <td><code><?php echo esc_html( $settings['last_call'] ?: '— noch kein Abruf —' ); ?></code></td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="dgptm_eiv_save" class="button button-primary">Einstellungen speichern</button>
                </p>
            </form>
        </div>

        <!-- Tab: Manueller Abruf -->
        <div id="tab-sync" class="dgptm-eiv-tab" style="display:none;">
            <h3>Manueller BÄK-Abruf</h3>
            <table class="form-table">
                <tr>
                    <th>Abrufen ab Datum</th>
                    <td>
                        <input type="date" id="eiv-sync-since" value="<?php echo esc_attr( $settings['last_call'] ? date('Y-m-d', strtotime($settings['last_call'])) : $settings['start_date'] ); ?>" class="regular-text">
                    </td>
                </tr>
            </table>
            <p>
                <button id="eiv-sync-btn" class="button button-secondary">Jetzt von BÄK abrufen</button>
                <span id="eiv-sync-spinner" class="spinner" style="float:none;display:none;"></span>
            </p>
            <div id="eiv-sync-log" style="margin-top:10px;max-width:900px;padding:10px;background:#fff;border:1px solid #ccd0d4;line-height:1.5;max-height:200px;overflow-y:auto;display:none;"></div>

            <!-- Ergebnistabelle -->
            <div id="eiv-sync-results" style="margin-top:15px;display:none;">
                <h3>Ergebnis</h3>
                <table class="widefat striped" id="eiv-results-table">
                    <thead>
                        <tr>
                            <th>Benutzer</th>
                            <th>Veranstaltung</th>
                            <th>Punkte</th>
                            <th>VNR</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>

        <style>
            .dgptm-eiv-tab { padding-top: 15px; }
            #eiv-results-table .status-imported { color: #00a32a; font-weight: 600; }
            #eiv-results-table .status-skipped  { color: #996800; }
            #eiv-results-table .status-error    { color: #d63638; }
        </style>
        <script>
        jQuery(function($){
            // Tabs
            var key = 'dgptm_eiv_tab';
            function showTab(id) {
                $('.dgptm-eiv-tab').hide();
                $('#'+id).show();
                $('.nav-tab').removeClass('nav-tab-active');
                $('a[href="#'+id+'"]').addClass('nav-tab-active');
                localStorage.setItem(key, id);
            }
            $('.nav-tab-wrapper a').on('click', function(e){
                e.preventDefault();
                showTab(this.hash.replace('#',''));
            });
            var init = localStorage.getItem(key) || 'tab-settings';
            if (!$('#'+init).length) init = 'tab-settings';
            showTab(init);

            // Manueller Sync
            var nonce = <?php echo wp_json_encode( $nonce_sync ); ?>;
            function slog(msg) {
                var $box = $('#eiv-sync-log');
                $box.show();
                var t = new Date().toLocaleTimeString();
                $box.append('<div>['+t+'] '+msg+'</div>');
                $box.scrollTop($box.prop('scrollHeight'));
            }

            $('#eiv-sync-btn').on('click', function(e){
                e.preventDefault();
                var sinceDate = $('#eiv-sync-since').val();
                if (!sinceDate) { alert('Bitte ein Datum eingeben.'); return; }

                $('#eiv-sync-spinner').show();
                $('#eiv-sync-log').html('').show();
                $('#eiv-sync-results').hide();
                $('#eiv-results-table tbody').html('');
                slog('Starte BÄK-Abruf ab ' + sinceDate + '…');

                $.post(ajaxurl, {
                    action: 'dgptm_eiv_manual_sync',
                    _wpnonce: nonce,
                    since_date: sinceDate
                }, function(resp) {
                    $('#eiv-sync-spinner').hide();
                    if (!resp.success) {
                        slog('Fehler: ' + (resp.data?.message || 'Unbekannt'));
                        return;
                    }
                    var d = resp.data;
                    // Logs anzeigen
                    if (d.logs) {
                        d.logs.forEach(function(l){ slog(l); });
                    }
                    // Ergebnistabelle
                    if (d.results && d.results.length > 0) {
                        var $tb = $('#eiv-results-table tbody');
                        d.results.forEach(function(r){
                            var cls = 'status-skipped';
                            if (r.status === 'Importiert') cls = 'status-imported';
                            else if (r.status.indexOf('Fehler') === 0) cls = 'status-error';
                            $tb.append(
                                '<tr>' +
                                '<td>' + $('<span>').text(r.user).html() + '</td>' +
                                '<td>' + $('<span>').text(r.event).html() + '</td>' +
                                '<td>' + parseFloat(r.points).toFixed(1) + '</td>' +
                                '<td><code>' + $('<span>').text(r.vnr).html() + '</code></td>' +
                                '<td class="' + cls + '">' + $('<span>').text(r.status).html() + '</td>' +
                                '</tr>'
                            );
                        });
                        $('#eiv-sync-results').show();
                    }
                    slog('Fertig: ' + d.imported + ' importiert, ' + d.skipped + ' übersprungen, ' + d.errors + ' Fehler.');
                }).fail(function(xhr){
                    $('#eiv-sync-spinner').hide();
                    slog('HTTP-Fehler (' + xhr.status + ')');
                });
            });
        });
        </script>
    </div>
    <?php
}
```

- [ ] **Step 2: Commit**

```bash
git add modules/utilities/efn-manager/eiv-fobi-sync.php
git commit -m "feat(eiv-sync): add admin settings page, manual sync with results table"
```

---

### Task 10: Integration in EFN-Manager Hauptdatei

**Files:**
- Modify: `modules/utilities/efn-manager/dgptm-efn-manager.php:1306-1309`

- [ ] **Step 1: require_once vor der Initialisierung einfügen**

Zeile 1306-1309 aktuell:
```php
/* ============================================================
 * Initialisierung
 * ============================================================ */
DGPTM_EFN_Labels::instance();
```

Ersetzen durch:
```php
/* ============================================================
 * EIV-Fobi Sync laden
 * ============================================================ */
$eiv_sync_file = __DIR__ . '/eiv-fobi-sync.php';
if ( file_exists( $eiv_sync_file ) ) {
    require_once $eiv_sync_file;
}

/* ============================================================
 * Initialisierung
 * ============================================================ */
DGPTM_EFN_Labels::instance();
```

- [ ] **Step 2: Version im Plugin-Header hochziehen**

Zeile 5 in `dgptm-efn-manager.php`:
```
 * Version: 1.0.3
```
Ändern zu:
```
 * Version: 1.1.0
```

- [ ] **Step 3: Commit**

```bash
git add modules/utilities/efn-manager/dgptm-efn-manager.php
git commit -m "feat(efn-manager): load eiv-fobi-sync module, bump version to 1.1.0"
```

---

### Task 11: Manueller Funktionstest

**Kein Code — nur Testschritte im WordPress-Admin:**

- [ ] **Step 1: Modul aktivieren**
Im DGPTM Plugin Suite Dashboard → EFN Manager aktivieren.
Prüfen: Keine PHP-Fehler in `debug.log` oder System Logs.

- [ ] **Step 2: CPT prüfen**
Admin → Einstellungen → EIV Event-Cache sollte als Unterseite erscheinen.
Prüfen: Leere Liste, keine Fehler.

- [ ] **Step 3: EIV-Fobi Sync Einstellungen**
Admin → Einstellungen → EIV-Fobi Sync.
Prüfen: Beide Tabs sichtbar (Einstellungen, Manueller Abruf).
Konfigurieren: Startdatum setzen, Zoho-Funktion `test_baek`, Speichern klicken.

- [ ] **Step 4: Manuellen Sync testen**
Tab "Manueller Abruf" → Datum eingeben → "Jetzt von BÄK abrufen" klicken.
Prüfen:
- Log zeigt Token-Abruf
- Log zeigt Anzahl Teilnahmen
- Ergebnistabelle erscheint mit Klarname, Veranstaltung, Punkte, VNR, Status
- Keine PHP-Fehler

- [ ] **Step 5: Dubletten-Check testen**
Gleichen Sync nochmal auslösen (gleicher Zeitraum).
Prüfen: Alle Einträge zeigen "Übersprungen (Dublette)" — keine Duplikate erstellt.

- [ ] **Step 6: Event-Cache prüfen**
Admin → Einstellungen → EIV Event-Cache.
Prüfen: Veranstaltungen wurden gecacht (Titel, VNR, Ort, Datum sichtbar).

- [ ] **Step 7: Fortbildungen prüfen**
Admin → Fortbildungen → Alle Fortbildungen.
Prüfen: Neue Einträge mit korrektem User, Datum, Ort, Punkten, Typ, VNR, freigegeben=Ja.

- [ ] **Step 8: Cron aktivieren**
Einstellungen → "Täglicher Abruf" aktivieren → Speichern.
Prüfen: `wp_next_scheduled('dgptm_eiv_daily_sync')` zeigt Timestamp (sichtbar auf Settings-Seite).

- [ ] **Step 9: Final Commit**

```bash
git add modules/utilities/efn-manager/
git commit -m "feat(eiv-sync): complete EIV-Fobi Sync implementation v1.1.0"
```
