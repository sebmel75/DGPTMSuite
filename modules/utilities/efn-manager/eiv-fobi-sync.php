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
        'api_base'        => 'https://backend.eiv-fobi.de',
        'last_call'       => '',
        'claude_api_key'  => '',
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
    $zoho_url = 'https://www.zohoapis.eu/crm/v7/functions/' . rawurlencode( $func_name ) . '/actions/execute?auth_type=oauth';

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
        return new WP_Error( 'eiv_zoho_func_http', "Zoho Function HTTP {$code}" );
    }

    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
    if ( ! is_array( $body ) || empty( $body['details']['output'] ) ) {
        return new WP_Error( 'eiv_zoho_func_parse', 'Zoho Function: Kein Token in Antwort.' );
    }

    return $body['details']['output'];
}

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
        'post_author' => get_current_user_id() ?: 0,
    ]);

    if ( is_wp_error( $pid ) ) return $pid;

    // Beginn/Ende parsen
    $beginn_raw = $event['beginn'] ?? '';
    $ende_raw   = $event['ende'] ?? '';
    $date_str   = $beginn_raw ? wp_date( 'Y-m-d', strtotime( $beginn_raw ) ) : '';
    $end_str    = $ende_raw   ? wp_date( 'Y-m-d', strtotime( $ende_raw ) )   : '';

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

/* ============================================================
 * Punkte-Berechnung (nutzt Mapping aus fobi_aek_settings)
 * ============================================================ */

/**
 * Berechnet EBCP-Punkte basierend auf BÄK-Kategorie × Veranstaltungstage.
 *
 * Feste Punkte pro Tag und Kategorie. Die Anzahl der Veranstaltungstage
 * wird aus beginn/ende berechnet (mindestens 1 Tag).
 *
 * @param string $type_code  Veranstaltungs-Typcode (A-K)
 * @param int    $days       Anzahl Veranstaltungstage (mindestens 1)
 * @return array  ['points' => float, 'label' => string]
 */
function dgptm_eiv_calculate_points( $type_code, $days = 1 ) {
    $type_code = strtoupper( trim( $type_code ) );
    $days = max( 1, intval( $days ) );

    // Feste EBCP-Punkte pro Tag je Kategorie
    $mapping = [
        'A' => [ 'points' => 1, 'label' => 'Vortragsveranstaltung' ],
        'B' => [ 'points' => 2, 'label' => 'Kongress' ],
        'C' => [ 'points' => 2, 'label' => 'Workshop' ],
        'D' => [ 'points' => 1, 'label' => 'Print / elektronisch' ],
        'E' => [ 'points' => 1, 'label' => 'Unbestimmt' ],
        'F' => [ 'points' => 2, 'label' => 'Unbestimmt' ],
        'G' => [ 'points' => 1, 'label' => 'Hospitation' ],
        'H' => [ 'points' => 1, 'label' => 'Curricula' ],
        'I' => [ 'points' => 1, 'label' => 'eLearning' ],
        'J' => [ 'points' => 2, 'label' => 'Unbestimmt' ],
        'K' => [ 'points' => 1, 'label' => 'Blended Learning' ],
    ];

    if ( ! isset( $mapping[ $type_code ] ) ) {
        return [ 'points' => 0.0, 'label' => 'Unbekannt' ];
    }

    $pts_per_day = (float) $mapping[ $type_code ]['points'];
    return [
        'points' => $pts_per_day * $days,
        'label'  => $mapping[ $type_code ]['label'],
    ];
}

/**
 * Berechnet die Anzahl Veranstaltungstage aus beginn/ende.
 *
 * @param string $date_begin  Startdatum (Y-m-d oder ISO 8601)
 * @param string $date_end    Enddatum (Y-m-d oder ISO 8601)
 * @return int  Anzahl Tage (mindestens 1)
 */
function dgptm_eiv_event_days( $date_begin, $date_end ) {
    if ( ! $date_begin || ! $date_end ) return 1;
    $ts_begin = strtotime( $date_begin );
    $ts_end   = strtotime( $date_end );
    if ( ! $ts_begin || ! $ts_end || $ts_end <= $ts_begin ) return 1;
    // Differenz in Tagen (aufgerundet)
    $diff_days = (int) ceil( ( $ts_end - $ts_begin ) / 86400 );
    return max( 1, $diff_days );
}

/* ============================================================
 * Dubletten-Check (dreistufig)
 *
 * 1. Exakte VNR-Übereinstimmung → Dublette
 * 2. Grobe Übereinstimmung (gleicher User, ähnliches Datum/Titel,
 *    aber ohne VNR) → Claude KI befragen
 * 3. Wenn Dublette mit/ohne VNR: Eintrag MIT VNR bevorzugen
 *
 * Regel: Unterschiedliche VNRs = unterschiedliche Veranstaltungen
 * ============================================================ */

/**
 * Prüft ob eine Fortbildung für User+VNR bereits existiert (exakt).
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

/**
 * Sucht grobe Übereinstimmungen: gleicher User, ähnliches Datum (±7 Tage),
 * aber OHNE VNR (oder mit leerer VNR).
 *
 * @param int    $user_id    WordPress User-ID
 * @param string $event_date Veranstaltungsdatum (Y-m-d)
 * @param string $title      Veranstaltungstitel
 * @return array  Array von möglichen Dubletten [{post_id, title, date, vnr, type, points}]
 */
function dgptm_eiv_find_fuzzy_matches( $user_id, $event_date, $title ) {
    if ( ! $event_date ) return [];

    // Zeitfenster: ±7 Tage
    $ts = strtotime( $event_date );
    if ( ! $ts ) return [];
    $date_from = date( 'Y-m-d', $ts - 7 * 86400 );
    $date_to   = date( 'Y-m-d', $ts + 7 * 86400 );

    // Alle Fortbildungen dieses Users im Zeitfenster
    $q = new WP_Query([
        'post_type'      => 'fortbildung',
        'posts_per_page' => 20,
        'post_status'    => 'any',
        'meta_query'     => [
            'relation' => 'AND',
            [ 'key' => 'user', 'value' => $user_id, 'compare' => '=' ],
            [ 'key' => 'date', 'value' => [ $date_from, $date_to ], 'compare' => 'BETWEEN', 'type' => 'DATE' ],
        ],
    ]);

    $matches = [];
    while ( $q->have_posts() ) {
        $q->the_post();
        $pid = get_the_ID();
        $existing_vnr = get_field( 'vnr', $pid );
        $existing_aek = get_post_meta( $pid, 'aek_vnr', true );

        // Nur Einträge OHNE VNR sind potenzielle Fuzzy-Dubletten
        // (Einträge MIT anderer VNR sind per Definition andere Veranstaltungen)
        if ( ! empty( $existing_vnr ) || ! empty( $existing_aek ) ) continue;

        $matches[] = [
            'post_id' => $pid,
            'title'   => get_the_title(),
            'date'    => get_field( 'date', $pid ),
            'type'    => get_field( 'type', $pid ),
            'points'  => floatval( get_field( 'points', $pid ) ),
        ];
    }
    wp_reset_postdata();

    return $matches;
}

/**
 * Fragt Claude KI ob zwei Einträge Dubletten sind.
 *
 * @param array  $new_entry      Neuer Eintrag [title, date, type, vnr]
 * @param array  $existing_entry Bestehender Eintrag [title, date, type, points]
 * @return bool|null  true = Dublette, false = keine Dublette, null = KI nicht verfügbar
 */
function dgptm_eiv_ask_claude_duplicate( $new_entry, $existing_entry ) {
    $settings = dgptm_eiv_get_settings();
    $api_key  = $settings['claude_api_key'] ?? '';

    // Fallback: Key aus Fortbildungs-Upload-Einstellungen
    if ( empty( $api_key ) ) {
        $upload_settings = get_option( 'fobi_upload_settings', [] );
        $api_key = $upload_settings['claude_api_key'] ?? '';
    }

    if ( empty( $api_key ) ) return null;

    $prompt = sprintf(
        'Sind diese zwei Fortbildungseinträge desselben Teilnehmers die GLEICHE Veranstaltung (Dublette)?

NEUER EINTRAG (aus BÄK mit VNR):
- Titel: %s
- Datum: %s
- Typ: %s
- VNR: %s

BESTEHENDER EINTRAG (manuell/ohne VNR):
- Titel: %s
- Datum: %s
- Typ: %s

Antworte NUR mit einem JSON-Objekt: {"duplicate": true} oder {"duplicate": false}
Kriterien: Gleiche Veranstaltung wenn Titel und Datum grob übereinstimmen (z.B. "DGPTM Jahrestagung" und "Jahrestagung DGPTM 2025" am gleichen Tag). Unterschiedliche Veranstaltungen wenn Titel klar verschieden oder Datum >3 Tage auseinander.',
        $new_entry['title'], $new_entry['date'], $new_entry['type'], $new_entry['vnr'],
        $existing_entry['title'], $existing_entry['date'], $existing_entry['type']
    );

    $resp = wp_remote_post( 'https://api.anthropic.com/v1/messages', [
        'timeout' => 15,
        'headers' => [
            'Content-Type'      => 'application/json',
            'x-api-key'         => $api_key,
            'anthropic-version' => '2023-06-01',
        ],
        'body' => wp_json_encode([
            'model'      => 'claude-haiku-4-5-20251001',
            'max_tokens' => 100,
            'messages'   => [
                [ 'role' => 'user', 'content' => $prompt ],
            ],
        ]),
    ]);

    if ( is_wp_error( $resp ) ) return null;
    $code = wp_remote_retrieve_response_code( $resp );
    if ( $code < 200 || $code >= 300 ) return null;

    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
    $text = $body['content'][0]['text'] ?? '';

    // JSON aus Antwort extrahieren
    if ( preg_match( '/\{[^}]*"duplicate"\s*:\s*(true|false)[^}]*\}/', $text, $m ) ) {
        return $m[1] === 'true';
    }

    return null;
}

/**
 * Erweiterte Dublettenprüfung mit Fuzzy-Match und Claude KI.
 *
 * @param int    $user_id    WordPress User-ID
 * @param string $vnr        Veranstaltungsnummer
 * @param string $event_date Veranstaltungsdatum (Y-m-d)
 * @param string $title      Veranstaltungstitel
 * @param string $type_label Typ-Label (z.B. "Workshop")
 * @param callable|null $log Log-Funktion
 * @return string  'none' | 'exact_vnr' | 'replaced_without_vnr' | 'ai_duplicate'
 */
function dgptm_eiv_check_duplicate( $user_id, $vnr, $event_date, $title, $type_label, $log = null ) {
    // 1. Exakte VNR-Übereinstimmung
    if ( dgptm_eiv_fortbildung_exists( $user_id, $vnr ) ) {
        return 'exact_vnr';
    }

    // 2. Fuzzy-Match: gleicher User, ähnliches Datum, OHNE VNR
    $fuzzy = dgptm_eiv_find_fuzzy_matches( $user_id, $event_date, $title );
    if ( empty( $fuzzy ) ) return 'none';

    foreach ( $fuzzy as $match ) {
        // Claude KI befragen
        $is_dup = dgptm_eiv_ask_claude_duplicate(
            [ 'title' => $title, 'date' => $event_date, 'type' => $type_label, 'vnr' => $vnr ],
            $match
        );

        if ( $is_dup === true ) {
            // Dublette erkannt: Eintrag OHNE VNR löschen → Eintrag MIT VNR wird neu erstellt
            wp_delete_post( $match['post_id'], true );
            if ( $log ) $log( sprintf(
                'Dublette erkannt (KI): "%s" (%s) ersetzt durch VNR-Eintrag "%s" (%s).',
                $match['title'], $match['date'], $title, $vnr
            ));
            return 'replaced_without_vnr';
        }

        if ( $is_dup === null && $log ) {
            $log( "KI-Dublettencheck nicht verfügbar (API-Key fehlt oder Fehler). Fuzzy-Match übersprungen." );
        }
    }

    return 'none';
}

/* ============================================================
 * CRM-Fallback: User über Barcode (EFN) im CRM suchen
 * ============================================================ */

/**
 * Sucht einen WordPress-User via Zoho CRM Barcode-Feld (EFN).
 * Wenn gefunden: EFN im WP-User nachtragen und ggf. zoho_id korrigieren.
 *
 * @param string        $efn  Die EFN aus der BÄK-Teilnahme
 * @param callable|null $log  Log-Funktion
 * @return object|null  WP_User-Objekt (ID + display_name) oder null
 */
function dgptm_eiv_find_user_via_crm( $efn, $log = null ) {
    if ( ! class_exists( 'DGPTM_Zoho_Plugin' ) ) return null;

    $zoho = DGPTM_Zoho_Plugin::get_instance();
    $token = $zoho->get_oauth_token();
    if ( is_wp_error( $token ) ) {
        if ( $log ) $log( "CRM-Fallback: Zoho OAuth fehlgeschlagen." );
        return null;
    }

    // CRM-Suche: Contact mit Barcode = EFN
    $search_url = 'https://www.zohoapis.eu/crm/v7/Contacts/search?' . http_build_query([
        'criteria' => "(Barcode:equals:{$efn})",
    ]);

    $resp = wp_remote_get( $search_url, [
        'timeout' => 30,
        'headers' => [
            'Authorization' => 'Zoho-oauthtoken ' . $token,
            'Accept'        => 'application/json',
        ],
    ]);

    if ( is_wp_error( $resp ) || wp_remote_retrieve_response_code( $resp ) < 200 || wp_remote_retrieve_response_code( $resp ) >= 300 ) {
        if ( $log ) $log( "CRM-Fallback: Suche nach Barcode={$efn} fehlgeschlagen." );
        return null;
    }

    $body = json_decode( wp_remote_retrieve_body( $resp ), true );
    $contacts = $body['data'] ?? [];
    if ( empty( $contacts ) ) {
        if ( $log ) $log( "CRM-Fallback: Kein CRM-Kontakt mit Barcode={$efn} gefunden." );
        return null;
    }

    $contact = $contacts[0];
    $wp_id   = intval( $contact['IDPerfusiologie'] ?? 0 );
    $crm_id  = $contact['id'] ?? '';
    $name    = trim( ( $contact['First_Name'] ?? '' ) . ' ' . ( $contact['Last_Name'] ?? '' ) );

    // IDPerfusiologie leer → Fallback: User über Website_Login (= user_login) finden
    if ( $wp_id <= 0 ) {
        $website_login = trim( (string) ( $contact['Website_Login'] ?? '' ) );
        if ( $website_login === '' ) {
            if ( $log ) $log( "CRM-Fallback: Kontakt '{$name}' hat weder IDPerfusiologie noch Website_Login." );
            return null;
        }

        $wp_user = get_user_by( 'login', $website_login );
        if ( ! $wp_user ) {
            if ( $log ) $log( "CRM-Fallback: Kein WP-User mit Login '{$website_login}' gefunden." );
            return null;
        }

        $wp_id = $wp_user->ID;
        if ( $log ) $log( "User '{$wp_user->display_name}' (ID {$wp_id}) über Website_Login '{$website_login}' gefunden." );

        // IDPerfusiologie im CRM nachtragen
        $update_url = "https://www.zohoapis.eu/crm/v7/Contacts/{$crm_id}";
        $update_resp = wp_remote_request( $update_url, [
            'method'  => 'PUT',
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode([
                'data' => [[ 'IDPerfusiologie' => (string) $wp_id ]],
            ]),
        ]);

        if ( ! is_wp_error( $update_resp ) && wp_remote_retrieve_response_code( $update_resp ) >= 200 && wp_remote_retrieve_response_code( $update_resp ) < 300 ) {
            if ( $log ) $log( "IDPerfusiologie={$wp_id} im CRM-Kontakt '{$name}' (ID {$crm_id}) nachgetragen." );
        } else {
            if ( $log ) $log( "WARNUNG: IDPerfusiologie konnte im CRM nicht aktualisiert werden für Kontakt '{$name}'." );
        }
    } else {
        // WordPress-User per IDPerfusiologie prüfen
        $wp_user = get_userdata( $wp_id );
        if ( ! $wp_user ) {
            if ( $log ) $log( "CRM-Fallback: WP-User ID {$wp_id} existiert nicht." );
            return null;
        }
    }

    // EFN im WordPress-User nachtragen
    $current_efn = get_user_meta( $wp_id, 'EFN', true );
    if ( $current_efn !== $efn ) {
        update_user_meta( $wp_id, 'EFN', $efn );
        if ( $log ) $log( "EFN '{$efn}' für User '{$wp_user->display_name}' (ID {$wp_id}) nachgetragen." );
    }

    // zoho_id korrigieren falls falsch
    $current_zoho_id = get_user_meta( $wp_id, 'zoho_id', true );
    if ( $crm_id && $current_zoho_id !== $crm_id ) {
        update_user_meta( $wp_id, 'zoho_id', $crm_id );
        if ( $log ) $log( "CRM-Zuordnung für User '{$wp_user->display_name}' korrigiert: {$current_zoho_id} → {$crm_id}" );
    }

    return (object) [ 'ID' => $wp_id, 'display_name' => $wp_user->display_name ];
}

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

        // User finden (1. via EFN-Meta, 2. Fallback via CRM Barcode)
        $users = get_users([
            'meta_key'   => 'EFN',
            'meta_value' => $efn,
            'number'     => 1,
            'fields'     => [ 'ID', 'display_name' ],
        ]);

        if ( ! empty( $users ) ) {
            $wp_user = $users[0];
        } else {
            // CRM-Fallback: User über Barcode (EFN) im CRM suchen
            $wp_user = dgptm_eiv_find_user_via_crm( $efn, $log );
            if ( ! $wp_user ) {
                $result['skipped']++;
                $result['results'][] = [
                    'user' => "EFN {$efn} (kein User)", 'event' => $vnr,
                    'points' => 0, 'vnr' => $vnr,
                    'status' => 'Übersprungen (kein User, auch nicht im CRM)',
                ];
                continue;
            }
            $result['results'][] = [
                'user' => $wp_user->display_name, 'event' => '—',
                'points' => 0, 'vnr' => $vnr,
                'status' => 'EFN via CRM nachgetragen',
            ];
        }

        // Event-Daten holen (Cache oder API) — VOR Dubletten-Check, damit Titel verfügbar
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

        // Punkte berechnen (Punkte pro Tag × Veranstaltungstage)
        $type_code  = $event['typeCode'] ?? '';
        $event_days = dgptm_eiv_event_days( $event['date'] ?? '', $event['endDate'] ?? '' );
        $calc       = dgptm_eiv_calculate_points( $type_code, $event_days );

        // Dozentenpunkte (punkte_referent) 1:1 addieren
        $referent_punkte = intval( $tn['punkte_referent'] ?? 0 );
        if ( $referent_punkte > 0 ) {
            $calc['points'] += $referent_punkte;
        }

        // Erweiterte Dublettenprüfung (exakt + Fuzzy + KI)
        $dup_result = dgptm_eiv_check_duplicate(
            $wp_user->ID, $vnr,
            $event['date'] ?? '', $event['title'] ?? '', $calc['label'],
            $log
        );
        if ( $dup_result === 'exact_vnr' ) {
            $result['skipped']++;
            $result['results'][] = [
                'user' => $wp_user->display_name, 'event' => $event['title'] ?? $vnr,
                'points' => 0, 'vnr' => $vnr,
                'status' => 'Übersprungen (Dublette)',
            ];
            continue;
        }
        if ( $dup_result === 'replaced_without_vnr' ) {
            // Alter Eintrag ohne VNR wurde gelöscht → neuer mit VNR wird erstellt
            $result['results'][] = [
                'user' => $wp_user->display_name, 'event' => $event['title'] ?? $vnr,
                'points' => $calc['points'], 'vnr' => $vnr,
                'status' => 'Ersetzt (Dublette ohne VNR gelöscht)',
            ];
        }

        // Fortbildung erstellen
        $pid = wp_insert_post([
            'post_title'  => ( $event['title'] ?? '' ) ?: 'BÄK-Veranstaltung ' . $vnr,
            'post_type'   => 'fortbildung',
            'post_status' => 'publish',
            'post_author' => get_current_user_id() ?: 0,
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
    if ( function_exists( 'set_time_limit' ) ) set_time_limit( 300 );
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

    nocache_headers();
    $since_raw = sanitize_text_field( $_POST['since_date'] ?? '' );
    if ( $since_raw && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $since_raw ) ) {
        $ts = strtotime( $since_raw );
        $since = ( $ts !== false ) ? wp_date( 'c', $ts ) : null;
    } else {
        $since = null;
    }

    $result = dgptm_eiv_run_sync( $since );
    wp_send_json_success( $result );
});

// AJAX: Fortbildungen gruppiert nach Veranstaltungsname laden
add_action( 'wp_ajax_dgptm_eiv_list_groups', function() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
    check_ajax_referer( 'dgptm_eiv_sync_nonce' );
    nocache_headers();

    $q = new WP_Query([
        'post_type'      => 'fortbildung',
        'posts_per_page' => -1,
        'post_status'    => 'any',
    ]);

    $groups = [];
    while ( $q->have_posts() ) {
        $q->the_post();
        $pid   = get_the_ID();
        $title = get_the_title();
        $vnr   = get_field( 'vnr', $pid ) ?: get_post_meta( $pid, 'aek_vnr', true );
        $pts   = floatval( get_field( 'points', $pid ) );
        $date  = get_field( 'date', $pid );
        $type  = get_field( 'type', $pid );
        $user_id = get_field( 'user', $pid );
        $uname = $user_id ? get_userdata( $user_id ) : null;

        $key = sanitize_title( $title ) ?: 'ohne-titel-' . $pid;
        if ( ! isset( $groups[ $key ] ) ) {
            $groups[ $key ] = [
                'title'  => $title,
                'vnr'    => $vnr ?: '',
                'type'   => $type,
                'count'  => 0,
                'points' => $pts,
                'entries' => [],
            ];
        }
        $groups[ $key ]['count']++;
        $groups[ $key ]['entries'][] = [
            'id'     => $pid,
            'user'   => $uname ? $uname->display_name : "User #{$user_id}",
            'date'   => $date,
            'points' => $pts,
            'vnr'    => $vnr,
        ];
    }
    wp_reset_postdata();

    // Nach Anzahl sortieren (größte Gruppen zuerst)
    uasort( $groups, function( $a, $b ) { return $b['count'] - $a['count']; } );

    wp_send_json_success( [ 'groups' => array_values( $groups ) ] );
});

// AJAX: Punkte einer Gruppe (alle Einträge mit gleichem Titel) ändern
add_action( 'wp_ajax_dgptm_eiv_update_group_points', function() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
    check_ajax_referer( 'dgptm_eiv_sync_nonce' );
    nocache_headers();

    $post_ids   = array_map( 'intval', (array) ( $_POST['post_ids'] ?? [] ) );
    $new_points = floatval( $_POST['new_points'] ?? 0 );

    $updated = 0;
    foreach ( $post_ids as $pid ) {
        if ( $pid <= 0 ) continue;
        if ( get_post_type( $pid ) !== 'fortbildung' ) continue;
        update_field( 'points', $new_points, $pid );
        $updated++;
    }

    wp_send_json_success( [ 'updated' => $updated ] );
});

// AJAX: Bezeichnung einer Gruppe (alle Einträge) umbenennen
add_action( 'wp_ajax_dgptm_eiv_rename_group', function() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
    check_ajax_referer( 'dgptm_eiv_sync_nonce' );
    nocache_headers();

    $post_ids  = array_map( 'intval', (array) ( $_POST['post_ids'] ?? [] ) );
    $new_title = sanitize_text_field( $_POST['new_title'] ?? '' );
    if ( $new_title === '' ) wp_send_json_error( [ 'message' => 'Titel darf nicht leer sein.' ] );

    $updated = 0;
    foreach ( $post_ids as $pid ) {
        if ( $pid <= 0 || get_post_type( $pid ) !== 'fortbildung' ) continue;
        wp_update_post( [ 'ID' => $pid, 'post_title' => $new_title ] );
        $updated++;
    }

    wp_send_json_success( [ 'updated' => $updated ] );
});

// AJAX: Gruppe löschen (alle Einträge mit gleichem Titel)
add_action( 'wp_ajax_dgptm_eiv_delete_group', function() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
    check_ajax_referer( 'dgptm_eiv_sync_nonce' );
    nocache_headers();

    $post_ids = array_map( 'intval', (array) ( $_POST['post_ids'] ?? [] ) );

    $deleted = 0;
    foreach ( $post_ids as $pid ) {
        if ( $pid <= 0 ) continue;
        if ( get_post_type( $pid ) !== 'fortbildung' ) continue;
        wp_delete_post( $pid, true );
        $deleted++;
    }

    wp_send_json_success( [ 'deleted' => $deleted ] );
});

// AJAX: Punkte korrigieren (nur Einträge MIT VNR + Cache)
add_action( 'wp_ajax_dgptm_eiv_recalc_points', function() {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Keine Berechtigung.' ] );
    check_ajax_referer( 'dgptm_eiv_sync_nonce' );
    nocache_headers();

    $q = new WP_Query([
        'post_type'      => 'fortbildung',
        'posts_per_page' => -1,
        'post_status'    => 'any',
        'meta_query'     => [
            [ 'key' => 'vnr', 'value' => '', 'compare' => '!=' ],
        ],
    ]);

    $updated = 0;
    $skipped = 0;
    $results = [];

    while ( $q->have_posts() ) {
        $q->the_post();
        $pid = get_the_ID();
        $vnr = get_field( 'vnr', $pid );
        if ( ! $vnr ) continue;

        $cached = dgptm_eiv_cache_lookup( $vnr );
        if ( ! $cached ) {
            $skipped++;
            continue; // Kein Cache → Punkte NICHT anfassen
        }

        $type_code  = $cached['typeCode'] ?? '';
        $event_days = dgptm_eiv_event_days( $cached['date'] ?? '', $cached['endDate'] ?? '' );
        $calc       = dgptm_eiv_calculate_points( $type_code, $event_days );
        $old_points = floatval( get_field( 'points', $pid ) );
        $new_points = $calc['points'];

        if ( abs( $old_points - $new_points ) > 0.01 ) {
            update_field( 'points', $new_points, $pid );
            update_field( 'type', $calc['label'], $pid );
            $updated++;
            $results[] = [
                'title' => get_the_title(),
                'vnr'   => $vnr,
                'old'   => $old_points,
                'new'   => $new_points,
            ];
        }
    }
    wp_reset_postdata();

    wp_send_json_success( [ 'updated' => $updated, 'skipped_no_cache' => $skipped, 'total' => $q->found_posts, 'results' => $results ] );
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
    $settings['claude_api_key'] = sanitize_text_field( $_POST['eiv_claude_api_key'] ?? '' );

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
            <a href="#tab-groups" class="nav-tab">Fortbildungen verwalten</a>
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
                        <th>Claude API Key</th>
                        <td><input type="password" name="eiv_claude_api_key" value="<?php echo esc_attr( $settings['claude_api_key'] ); ?>" class="regular-text" autocomplete="off">
                            <p class="description">Für KI-gestützte Dublettenprüfung. Fallback: Key aus Fortbildungs-Upload-Einstellungen.</p>
                        </td>
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
                <button id="eiv-recalc-btn" class="button" style="margin-left:10px;">Punkte korrigieren</button>
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

        <!-- Tab: Fortbildungen verwalten -->
        <div id="tab-groups" class="dgptm-eiv-tab" style="display:none;">
            <h3>Fortbildungen gruppiert nach Veranstaltung</h3>
            <p>
                <button id="eiv-load-groups" class="button button-secondary">Gruppen laden</button>
                <span id="eiv-groups-spinner" class="spinner" style="float:none;display:none;"></span>
            </p>
            <div id="eiv-groups-container" style="margin-top:15px;"></div>
        </div>

        <style>
            .dgptm-eiv-tab { padding-top: 15px; }
            #eiv-results-table .status-imported { color: #00a32a; font-weight: 600; }
            #eiv-results-table .status-skipped  { color: #996800; }
            #eiv-results-table .status-error    { color: #d63638; }
            .eiv-group { background:#fff; border:1px solid #ccd0d4; margin-bottom:12px; border-radius:4px; }
            .eiv-group-header { display:flex; justify-content:space-between; align-items:center; padding:10px 15px; cursor:pointer; background:#f6f7f7; border-bottom:1px solid #ccd0d4; }
            .eiv-group-header:hover { background:#eef0f1; }
            .eiv-group-title { font-weight:600; flex:1; }
            .eiv-group-meta { color:#666; font-size:13px; margin-left:15px; }
            .eiv-group-body { padding:12px 15px; display:none; }
            .eiv-group-body.open { display:block; }
            .eiv-group-actions { display:flex; gap:8px; align-items:center; margin-bottom:10px; }
            .eiv-group-actions input[type=number] { width:80px; }
            .eiv-group-entries { font-size:13px; }
            .eiv-group-entries td { padding:4px 8px; }
            .eiv-badge { display:inline-block; padding:2px 8px; border-radius:3px; font-size:12px; }
            .eiv-badge-vnr { background:#e7f5e7; color:#1a7a1a; }
            .eiv-badge-no-vnr { background:#fef3e7; color:#996800; }
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

            // Punkte korrigieren
            $('#eiv-recalc-btn').on('click', function(e){
                e.preventDefault();
                if (!confirm('Alle EIV-importierten Fortbildungen werden neu berechnet. Fortfahren?')) return;

                $('#eiv-sync-spinner').show();
                $('#eiv-sync-log').html('').show();
                $('#eiv-sync-results').hide();
                $('#eiv-results-table tbody').html('');
                slog('Starte Punkte-Neuberechnung…');

                $.post(ajaxurl, {
                    action: 'dgptm_eiv_recalc_points',
                    _wpnonce: nonce
                }, function(resp) {
                    $('#eiv-sync-spinner').hide();
                    if (!resp.success) {
                        slog('Fehler: ' + (resp.data?.message || 'Unbekannt'));
                        return;
                    }
                    var d = resp.data;
                    slog(d.updated + ' von ' + d.total + ' Fortbildungen korrigiert.');
                    if (d.results && d.results.length > 0) {
                        var $tb = $('#eiv-results-table tbody');
                        d.results.forEach(function(r){
                            var cls = r.status === 'Korrigiert' ? 'status-imported' : 'status-skipped';
                            $tb.append(
                                '<tr>' +
                                '<td>' + $('<span>').text(r.title).html() + '</td>' +
                                '<td><code>' + $('<span>').text(r.vnr).html() + '</code></td>' +
                                '<td>' + r.old + ' → <strong>' + r['new'] + '</strong></td>' +
                                '<td>' + $('<span>').text(r.vnr).html() + '</td>' +
                                '<td class="' + cls + '">' + $('<span>').text(r.status).html() + '</td>' +
                                '</tr>'
                            );
                        });
                        $('#eiv-sync-results').show();
                    }
                }).fail(function(xhr){
                    $('#eiv-sync-spinner').hide();
                    slog('HTTP-Fehler (' + xhr.status + ')');
                });
            });

            // ===== Gruppen-Verwaltung =====
            $('#eiv-load-groups').on('click', function(e){
                e.preventDefault();
                $('#eiv-groups-spinner').show();
                $('#eiv-groups-container').html('');

                $.post(ajaxurl, { action:'dgptm_eiv_list_groups', _wpnonce:nonce }, function(resp){
                    $('#eiv-groups-spinner').hide();
                    if (!resp.success) { alert('Fehler: '+(resp.data?.message||'Unbekannt')); return; }
                    var groups = resp.data.groups;
                    if (!groups.length) { $('#eiv-groups-container').html('<p>Keine Fortbildungen vorhanden.</p>'); return; }

                    var html = '<p><strong>'+groups.length+' Veranstaltungsgruppen</strong></p>';
                    groups.forEach(function(g, idx){
                        var ids = g.entries.map(function(e){ return e.id; });
                        var badge = g.vnr ? '<span class="eiv-badge eiv-badge-vnr">VNR '+$('<span>').text(g.vnr).html()+'</span>' : '<span class="eiv-badge eiv-badge-no-vnr">Keine VNR</span>';

                        html += '<div class="eiv-group" data-idx="'+idx+'">';
                        html += '<div class="eiv-group-header" data-toggle="'+idx+'">';
                        html += '<span class="eiv-group-title">'+$('<span>').text(g.title||'(Ohne Titel)').html()+'</span>';
                        html += '<span class="eiv-group-meta">'+g.count+' Einträge &middot; '+g.points+' Pkt. '+badge+'</span>';
                        html += '</div>';
                        html += '<div class="eiv-group-body" id="eiv-gb-'+idx+'">';
                        html += '<div class="eiv-group-actions" style="flex-wrap:wrap;">';
                        html += '<label>Bezeichnung: <input type="text" value="'+$('<span>').text(g.title).html()+'" class="eiv-grp-title regular-text" style="width:300px;"></label>';
                        html += '<button class="button eiv-grp-rename" data-ids=\''+JSON.stringify(ids)+'\'>Umbenennen</button>';
                        html += '<span style="border-left:1px solid #ccc;margin:0 8px;"></span>';
                        html += '<label>Punkte: <input type="number" step="0.1" min="0" value="'+g.points+'" class="eiv-grp-pts"></label>';
                        html += '<button class="button eiv-grp-save" data-ids=\''+JSON.stringify(ids)+'\'>Punkte speichern</button>';
                        html += '<span style="border-left:1px solid #ccc;margin:0 8px;"></span>';
                        html += '<button class="button eiv-grp-delete" data-ids=\''+JSON.stringify(ids)+'\' style="color:#d63638;">Alle '+g.count+' löschen</button>';
                        html += '</div>';
                        html += '<table class="eiv-group-entries widefat striped"><thead><tr><th>Benutzer</th><th>Datum</th><th>Punkte</th><th>VNR</th></tr></thead><tbody>';
                        g.entries.forEach(function(e){
                            html += '<tr><td>'+$('<span>').text(e.user).html()+'</td><td>'+$('<span>').text(e.date||'—').html()+'</td><td>'+e.points+'</td><td><code>'+$('<span>').text(e.vnr||'—').html()+'</code></td></tr>';
                        });
                        html += '</tbody></table></div></div>';
                    });

                    $('#eiv-groups-container').html(html);

                }).fail(function(xhr){ $('#eiv-groups-spinner').hide(); alert('HTTP-Fehler ('+xhr.status+')'); });
            });

            // Event-Delegation auf dem festen Container (überlebt .html()-Ersetzungen)
            var $gc = $('#eiv-groups-container');

            // Toggle
            $gc.on('click', '.eiv-group-header', function(){
                var id = $(this).data('toggle');
                $('#eiv-gb-'+id).toggleClass('open');
            });

            // Umbenennen
            $gc.on('click', '.eiv-grp-rename', function(){
                var $btn = $(this);
                var ids = $btn.data('ids');
                var newTitle = $btn.closest('.eiv-group-actions').find('.eiv-grp-title').val().trim();
                if (!newTitle) { alert('Bezeichnung darf nicht leer sein.'); return; }
                if (!confirm('Alle '+ids.length+' Einträge umbenennen zu "'+newTitle+'"?')) return;
                $btn.prop('disabled',true).text('Speichere…');
                $.post(ajaxurl, { action:'dgptm_eiv_rename_group', _wpnonce:nonce, post_ids:ids, new_title:newTitle }, function(r){
                    $btn.prop('disabled',false).text('Umbenennen');
                    if (r.success) { alert(r.data.updated+' Einträge umbenannt.'); $('#eiv-load-groups').click(); }
                    else alert('Fehler: '+(r.data?.message||''));
                });
            });

            // Punkte speichern
            $gc.on('click', '.eiv-grp-save', function(){
                var $btn = $(this);
                var ids = $btn.data('ids');
                var pts = $btn.closest('.eiv-group-actions').find('.eiv-grp-pts').val();
                if (!confirm('Punkte für alle '+ids.length+' Einträge auf '+pts+' setzen?')) return;
                $btn.prop('disabled',true).text('Speichere…');
                $.post(ajaxurl, { action:'dgptm_eiv_update_group_points', _wpnonce:nonce, post_ids:ids, new_points:pts }, function(r){
                    $btn.prop('disabled',false).text('Punkte speichern');
                    if (r.success) { alert(r.data.updated+' Einträge aktualisiert.'); $('#eiv-load-groups').click(); }
                    else alert('Fehler: '+(r.data?.message||''));
                });
            });

            // Gruppe löschen
            $gc.on('click', '.eiv-grp-delete', function(){
                var $btn = $(this);
                var ids = $btn.data('ids');
                if (!confirm('ACHTUNG: Alle '+ids.length+' Fortbildungseinträge dieser Veranstaltung unwiderruflich löschen?')) return;
                $btn.prop('disabled',true).text('Lösche…');
                $.post(ajaxurl, { action:'dgptm_eiv_delete_group', _wpnonce:nonce, post_ids:ids }, function(r){
                    $btn.prop('disabled',false).text('Gelöscht');
                    if (r.success) { alert(r.data.deleted+' Einträge gelöscht.'); $('#eiv-load-groups').click(); }
                    else alert('Fehler: '+(r.data?.message||''));
                });
            });

            // ===== Manueller Sync =====
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
