<?php
/**
 * Plugin Name: DGPTM - Zoho CRM & Zusätzliche Endpunkte (Hardened)
 * Description: Kombiniert den Zoho-CRM-Datenabruf (OAuth2) mit zusätzlichen Endpunkten. Enthält SSRF-Härtung, sichere OAuth-Weiterleitung, Admin-UI mit Anleitung sowie Debug-Logging. Neu: Pro Endpunkt kann per Toggle **„Nur interne Aufrufe“** erzwungen werden – dann sind Aufrufe ausschließlich aus internem WordPress/PHP-Code erlaubt (HMAC-Header, automatisch gesetzt bei internen Aufrufen). Die frühere Token-Header-Funktion (x-webhook-token) wurde entfernt. Shortcodes: [zoho_api_data], [zoho_api_data_ajax], [ifcrmfield], [zoho_api_antwort]/[dgptm_api_antwort], [api-abfrage], [api-abruf], [efn_barcode].
 * Version: 1.8.0
 * Author: Sebastian Melzer (gehärtet)
 * Text Domain: dgptm-zoho
 */

if ( ! defined('ABSPATH') ) exit;
if ( ! defined('WPINC') )   exit;

define('DGPTM_ZOHO_VERSION', '1.8.0');

/**
 * ========================================================
 * Globale Helfer & Security
 * ========================================================
 */

/** Debug-Option prüfen (Option + WP_DEBUG_LOG müssen aktiv sein) */
if ( ! function_exists('dgptm_is_debug_enabled') ) {
    function dgptm_is_debug_enabled() : bool {
        $opt = get_option('dgptm_debug_log', false);
        return (bool) $opt;
    }
}

/** Sensible Keys bei Logs ausblenden */
if ( ! function_exists('dgptm_redact_array') ) {
    function dgptm_redact_array( $arr ) {
        $keys = ['authorization','Authorization','x-webhook-token','X-Webhook-Token','secret_token','access_token','refresh_token','client_secret','x-dgptm-internal','x-dgptm-ts'];
        $clean = [];
        foreach ((array)$arr as $k => $v) {
            if (in_array($k, $keys, true)) {
                $clean[$k] = '[REDACTED]';
            } elseif (is_array($v)) {
                $clean[$k] = dgptm_redact_array($v);
            } else {
                $clean[$k] = $v;
            }
        }
        return $clean;
    }
}

/** In debug.log schreiben (wenn aktiviert) */
if ( ! function_exists('dgptm_log') ) {
    function dgptm_log( string $message, array $context = [] ) : void {
        if ( ! dgptm_is_debug_enabled() ) { return; }
        $prefix = '[DGPTM] ';
        if (!empty($context)) {
            $safe = dgptm_redact_array($context);
            $message .= ' ' . wp_json_encode($safe, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        }
        if ( defined('WP_DEBUG_LOG') && WP_DEBUG_LOG ) {
            error_log($prefix . $message);
        }
    }
}

/** Kryptostarkes Random-Token (Hex) – (nur intern genutzt) */
if ( ! function_exists('dgptm_random_token') ) {
    function dgptm_random_token( $length = 64 ) {
        $bytes = max(1, (int) ceil($length / 2));
        try {
            $raw = random_bytes($bytes);
        } catch ( Exception $e ) {
            $raw = wp_generate_password($bytes, true, true); // Fallback
        }
        return substr(bin2hex($raw), 0, $length);
    }
}

/** Rekursive Sanitization für Arrays (Keys + Values). */
if ( ! function_exists('dgptm_recursive_sanitize') ) {
    function dgptm_recursive_sanitize( $value ) {
        if ( is_array($value) ) {
            $out = [];
            foreach ( $value as $k => $v ) {
                $kk = is_string($k) ? sanitize_key($k) : $k;
                $out[$kk] = dgptm_recursive_sanitize($v);
            }
            return $out;
        }
        if ( is_scalar($value) ) {
            return is_string($value) ? sanitize_text_field(wp_unslash($value)) : $value;
        }
        return '';
    }
}

/**
 * Sanitizer für REST-Routen-Slugs:
 * - erlaubt a-z, A-Z, 0-9, Unterstrich, Bindestrich und SCHRÄGSTRICH
 * - entfernt führende Slashes (wir fügen sie beim Registrieren wieder an)
 * - reduziert doppelte Slashes
 */
if ( ! function_exists('dgptm_sanitize_route_slug') ) {
    function dgptm_sanitize_route_slug( $slug ) {
        $slug = (string) $slug;
        $slug = trim($slug);
        $slug = ltrim($slug, '/');
        $slug = preg_replace('#[^a-zA-Z0-9/_-]#', '', $slug);
        $slug = preg_replace('#/{2,}#', '/', $slug);
        return $slug;
    }
}

/** IPv4 Private/Lo/Link-Local/CGNAT/Reserved/Multicast/Broadcast */
if ( ! function_exists('dgptm_is_private_ipv4') ) {
    function dgptm_is_private_ipv4( $ip ) {
        if ( ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ) return false;
        $long = ip2long($ip);
        if ( $long === false ) return false;
        $ranges = [
            ['10.0.0.0',     '10.255.255.255'],
            ['172.16.0.0',   '172.31.255.255'],
            ['192.168.0.0',  '192.168.255.255'],
            ['127.0.0.0',    '127.255.255.255'],
            ['169.254.0.0',  '169.254.255.255'],
            ['100.64.0.0',   '100.127.255.255'],
            ['0.0.0.0',      '0.255.255.255'],
            ['224.0.0.0',    '239.255.255.255'],
            ['240.0.0.0',    '255.255.255.255'],
        ];
        foreach ($ranges as $r) {
            if ($long >= ip2long($r[0]) && $long <= ip2long($r[1])) return true;
        }
        return false;
    }
}

/** IPv6 Prefix-Match */
if ( ! function_exists('dgptm_ipv6_prefix_match') ) {
    function dgptm_ipv6_prefix_match( $ip, $prefix, $masklen ) {
        $ip_bin   = @inet_pton($ip);
        $pref_bin = @inet_pton($prefix);
        if ( $ip_bin === false || $pref_bin === false ) return false;
        $bytes = (int) floor($masklen / 8);
        $bits  = $masklen % 8;

        if ( $bytes > 0 && substr($ip_bin, 0, $bytes) !== substr($pref_bin, 0, $bytes) ) return false;
        if ( $bits === 0 ) return true;

        $mask = chr( (0xFF << (8 - $bits)) & 0xFF );
        return ( (ord($ip_bin[$bytes]) & ord($mask)) === (ord($pref_bin[$bytes]) & ord($mask)) );
    }
}

/** IPv6 Private/Ula/Link-Local/Loopback/Multicast/Doc. */
if ( ! function_exists('dgptm_is_private_ipv6') ) {
    function dgptm_is_private_ipv6( $ip ) {
        if ( ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ) return false;
        if ( $ip === '::' || $ip === '::1' ) return true;
        if ( dgptm_ipv6_prefix_match($ip, 'fc00::', 7) )  return true;  // ULA
        if ( dgptm_ipv6_prefix_match($ip, 'fe80::', 10) ) return true; // Link-Local
        if ( dgptm_ipv6_prefix_match($ip, 'ff00::', 8) )  return true; // Multicast
        if ( dgptm_ipv6_prefix_match($ip, '2001:db8::', 32) ) return true; // Doc
        return false;
    }
}

/** IP-Checker (IPv4/IPv6). */
if ( ! function_exists('dgptm_is_private_ip') ) {
    function dgptm_is_private_ip( $ip ) {
        if ( filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) ) return dgptm_is_private_ipv4($ip);
        if ( filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ) return dgptm_is_private_ipv6($ip);
        return true; // konservativ
    }
}

/** DNS-Resolution prüfen. */
if ( ! function_exists('dgptm_host_resolves_to_public') ) {
    function dgptm_host_resolves_to_public( $host ) {
        if ( ! function_exists('dns_get_record') ) return null;
        $records = @dns_get_record($host, DNS_A + DNS_AAAA);
        if ( ! is_array($records) || empty($records) ) return null;
        foreach ( $records as $rec ) {
            $ip = $rec['type'] === 'A' ? ($rec['ip'] ?? '') : ($rec['ipv6'] ?? '');
            if ( $ip && dgptm_is_private_ip($ip) ) return false;
        }
        return true;
    }
}

/** Erlaubt nur HTTPS, blockt Local/Private, prüft DNS, optional Allowlist. */
if ( ! function_exists('dgptm_is_allowed_url') ) {
    function dgptm_is_allowed_url( $url ) {
        if ( ! $url || ! wp_http_validate_url( $url ) ) {
            dgptm_log('URL validation failed', ['url'=>$url]);
            return false;
        }
        $p = wp_parse_url( $url );
        if ( ! $p || empty( $p['host'] ) ) {
            dgptm_log('URL parse failed', ['url'=>$url]);
            return false;
        }
        $scheme = isset($p['scheme']) ? strtolower($p['scheme']) : '';
        if ( $scheme !== 'https' ) {
            dgptm_log('URL blocked: non-HTTPS', ['url'=>$url]);
            return false;
        }
        $host = strtolower($p['host']);

        $blocked_hosts = ['localhost', 'localdomain', '127.0.0.1', '::1'];
        if ( in_array($host, $blocked_hosts, true) || (function_exists('str_ends_with') && str_ends_with($host, '.local')) ) {
            dgptm_log('URL blocked: host blocked', ['url'=>$url,'host'=>$host]);
            return false;
        }

        if ( filter_var($host, FILTER_VALIDATE_IP) ) {
            if ( dgptm_is_private_ip($host) ) {
                dgptm_log('URL blocked: private IP', ['url'=>$url,'ip'=>$host]);
                return false;
            }
        } else {
            $public = dgptm_host_resolves_to_public( $host );
            $require_resolution = apply_filters('dgptm_require_dns_resolution', false);
            if ( $public === false ) {
                dgptm_log('URL blocked: DNS resolves to private', ['url'=>$url,'host'=>$host]);
                return false;
            }
            if ( $require_resolution && $public === null ) {
                dgptm_log('URL blocked: DNS resolution required/unknown', ['url'=>$url,'host'=>$host]);
                return false;
            }
        }

        $allowed = apply_filters('dgptm_allowed_hosts', []);
        if ( is_array($allowed) && ! empty($allowed) ) {
            $allowed_lower = array_map('strtolower', $allowed);
            $ok = in_array($host, $allowed_lower, true);
            if ( ! $ok ) dgptm_log('URL blocked: not in allowlist', ['url'=>$url,'host'=>$host,'allowlist'=>$allowed_lower]);
            return $ok;
        }
        return true;
    }
}

/** Sichere Remote-Requests. */
if ( ! function_exists('dgptm_safe_remote') ) {
    function dgptm_safe_remote( $method, $url, $args = [] ) {
        if ( ! dgptm_is_allowed_url( $url ) ) {
            return new WP_Error('forbidden_target', __('Nicht erlaubte Ziel-URL.', 'dgptm-zoho'));
        }
        $args = wp_parse_args( $args, [
            'timeout'             => 10,
            'redirection'         => 3,
            'reject_unsafe_urls'  => true,
            'headers'             => [],
            'user-agent'          => 'DGPTM-Zoho/' . DGPTM_ZOHO_VERSION . ' (' . home_url() . ')',
        ]);
        $args = apply_filters('dgptm_safe_remote_args', $args, $method, $url);

        dgptm_log('Forwarding HTTP request', [
            'method'  => strtoupper($method),
            'url'     => $url,
            'headers' => array_map(function(){ return '[SET]'; }, (array)$args['headers']),
        ]);

        if ( strtoupper($method) === 'GET' ) {
            $res = wp_safe_remote_get( $url, $args );
        } else {
            $res = wp_safe_remote_post( $url, $args );
        }

        if ( is_wp_error($res) ) {
            dgptm_log('Forwarding failed', ['error'=>$res->get_error_message()]);
        } else {
            $code = wp_remote_retrieve_response_code($res);
            $len  = strlen( (string) wp_remote_retrieve_body($res) );
            dgptm_log('Forwarding done', ['status'=>$code,'body_bytes'=>$len]);
        }
        return $res;
    }
}

/**
 * --------------------------------------------------------
 * Interne Aufrufe: Signatur-Header (HMAC, zeitbasiert)
 * --------------------------------------------------------
 * - Keine UI-Konfiguration nötig (kein Token-Management).
 * - Header werden automatisch in internen Aufrufen gesetzt.
 * - Erlaubt nur Aufrufe aus WordPress/PHP (rest_do_request etc.).
 */

/** Liefert Header für einen internen Aufruf eines Slugs */
if ( ! function_exists('dgptm_internal_signature_headers') ) {
    function dgptm_internal_signature_headers( string $slug, string $method = 'GET', ?int $ts = null ) : array {
        $ts = $ts ?? time();
        $secret  = wp_salt('auth');
        $payload = 'dgptm|' . $slug . '|' . strtoupper($method) . '|' . $ts . '|' . home_url('/');
        $sig     = hash_hmac('sha256', $payload, $secret);
        return [
            'x-dgptm-ts'       => (string) $ts,
            'x-dgptm-internal' => $sig,
        ];
    }
}

/** Validiert, ob Request intern signiert ist (innerhalb Drift) */
if ( ! function_exists('dgptm_validate_internal_request') ) {
    function dgptm_validate_internal_request( WP_REST_Request $req, string $slug ) : bool {
        $ts_hdr = (string) $req->get_header('x-dgptm-ts');
        $sig    = (string) $req->get_header('x-dgptm-internal');
        if ( $ts_hdr === '' || $sig === '' ) {
            dgptm_log('Internal check failed: missing headers', ['slug'=>$slug]);
            return false;
        }
        if ( ! ctype_digit($ts_hdr) ) {
            dgptm_log('Internal check failed: invalid ts', ['slug'=>$slug,'ts'=>$ts_hdr]);
            return false;
        }
        $ts  = (int) $ts_hdr;
        $drift = (int) apply_filters('dgptm_internal_max_drift', 300); // 5 min
        if ( abs(time() - $ts) > $drift ) {
            dgptm_log('Internal check failed: ts drift', ['slug'=>$slug,'ts'=>$ts,'drift'=>$drift]);
            return false;
        }
        $secret  = wp_salt('auth');
        $payload = 'dgptm|' . $slug . '|' . strtoupper($req->get_method()) . '|' . $ts . '|' . home_url('/');
        $expected = hash_hmac('sha256', $payload, $secret);
        $ok = hash_equals($expected, $sig);
        if ( ! $ok ) {
            dgptm_log('Internal check failed: signature mismatch', ['slug'=>$slug]);
        }
        return $ok;
    }
}

/**
 * ========================================================
 * Aktivierung
 * ========================================================
 */
register_activation_hook(__FILE__, function() {
    global $wpdb;
    $keys = [
        'dgptm_zoho_access_token'  => '',
        'dgptm_zoho_refresh_token' => '',
        'dgptm_zoho_token_expires' => 0,
        'dgptm_zoho_client_id'     => '',
        'dgptm_zoho_client_secret' => '',
        'dgptm_debug_log'          => 0,
        'wf_endpoints'             => [],
    ];
    foreach ($keys as $k => $default) {
        if ( get_option($k, null) === null ) {
            add_option($k, $default, '', 'no');
        } else {
            $wpdb->update($wpdb->options, ['autoload' => 'no'], ['option_name' => $k]);
        }
    }

    if ( ! get_role('mitglied') ) {
        add_role('mitglied', __('Mitglied', 'dgptm-zoho'), ['read' => true]);
    }
    
    // Tabelle für Rollenwechsel-Logging erstellen
    $table_name = $wpdb->prefix . 'dgptm_role_changes';
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id bigint(20) UNSIGNED NOT NULL,
        username varchar(60) NOT NULL,
        action varchar(20) NOT NULL,
        role_name varchar(50) NOT NULL,
        zoho_value varchar(10) DEFAULT NULL,
        previous_roles text DEFAULT NULL,
        new_roles text DEFAULT NULL,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP,
        ip_address varchar(45) DEFAULT NULL,
        user_agent varchar(255) DEFAULT NULL,
        PRIMARY KEY  (id),
        KEY user_id (user_id),
        KEY timestamp (timestamp),
        KEY action (action)
    ) $charset_collate;";
    
    require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
    dbDelta( $sql );
    
    dgptm_log('Plugin activated, role_changes table created/updated');
});

/* ========================================================
   DGPTM_Role_Manager – Automatische Rollenverwaltung
   ======================================================== */
class DGPTM_Role_Manager {
    private static $instance = null;
    private $session_key = 'dgptm_role_sync_done';
    private $log_table = null;
    
    private function __construct() {
        global $wpdb;
        $this->log_table = $wpdb->prefix . 'dgptm_role_changes';
        
        // Session bei Login zurücksetzen
        add_action('wp_login', [$this, 'reset_sync_flag'], 10, 2);
    }
    
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Reset sync flag beim Login
     */
    public function reset_sync_flag($user_login, $user) {
        if (!session_id()) {
            session_start();
        }
        unset($_SESSION[$this->session_key]);
        dgptm_log('Role sync flag reset on login', ['user_id' => $user->ID]);
    }
    
    /**
     * Prüft, ob Synchronisation bereits in dieser Session durchgeführt wurde
     */
    public function is_synced() {
        if (!session_id()) {
            session_start();
        }
        return isset($_SESSION[$this->session_key]) && $_SESSION[$this->session_key] === true;
    }
    
    /**
     * Markiert Synchronisation als durchgeführt
     */
    private function mark_as_synced() {
        if (!session_id()) {
            session_start();
        }
        $_SESSION[$this->session_key] = true;
    }
    
    /**
     * Hauptfunktion: Synchronisiert Rolle basierend auf Zoho-Daten
     * Wird automatisch beim ersten fetch_zoho_data() Aufruf nach Login ausgeführt
     */
    public function sync_member_role($zoho_data) {
        // Nur für eingeloggte User
        if (!is_user_logged_in()) {
            return;
        }
        
        // Prüfen ob bereits synchronisiert
        if ($this->is_synced()) {
            dgptm_log('Role sync skipped: already done in this session');
            return;
        }
        
        // Zoho-Wert extrahieren
        $aktives_mitglied = $this->extract_aktives_mitglied($zoho_data);
        
        // Rolle synchronisieren
        $this->process_role_sync($aktives_mitglied);
        
        // Als synchronisiert markieren
        $this->mark_as_synced();
    }
    
    /**
     * Extrahiert den Wert von "aktives_mitglied" aus Zoho-Daten
     */
    private function extract_aktives_mitglied($zoho_data) {
        if (!is_array($zoho_data)) {
            return null;
        }
        
        // Verschiedene mögliche Feldnamen prüfen
        $possible_fields = ['aktives_mitglied', 'Aktives_Mitglied', 'aktives_Mitglied', 'AktivesMitglied'];
        
        foreach ($possible_fields as $field) {
            if (isset($zoho_data[$field])) {
                return $zoho_data[$field];
            }
        }
        
        return null;
    }
    
    /**
     * Verarbeitet die Rollensynchronisation
     */
    private function process_role_sync($zoho_value) {
        $user_id = get_current_user_id();
        $user = get_user_by('id', $user_id);
        
        if (!$user) {
            dgptm_log('Role sync error: user not found', ['user_id' => $user_id]);
            return;
        }
        
        // Wert normalisieren
        $is_active = $this->parse_boolean($zoho_value);
        
        // Aktuelle Rollen speichern (für Logging)
        $previous_roles = $user->roles;
        $has_mitglied = in_array('mitglied', $previous_roles);
        
        $action = null;
        
        if ($is_active && !$has_mitglied) {
            // Rolle hinzufügen
            $user->add_role('mitglied');
            $action = 'added';
            dgptm_log('Role added', ['user_id' => $user_id, 'role' => 'mitglied', 'zoho_value' => $zoho_value]);
            
        } elseif (!$is_active && $has_mitglied) {
            // Rolle entfernen
            $user->remove_role('mitglied');
            $action = 'removed';
            
            // Fallback auf subscriber wenn keine Rolle mehr vorhanden
            $updated_user = get_user_by('id', $user_id);
            if (empty($updated_user->roles)) {
                $user->add_role('subscriber');
                $action = 'removed_with_fallback';
                dgptm_log('Role removed with fallback', ['user_id' => $user_id, 'fallback' => 'subscriber', 'zoho_value' => $zoho_value]);
            } else {
                dgptm_log('Role removed', ['user_id' => $user_id, 'role' => 'mitglied', 'zoho_value' => $zoho_value]);
            }
        }
        
        // Logging in Datenbank
        if ($action) {
            $updated_user = get_user_by('id', $user_id);
            $new_roles = $updated_user->roles;
            $this->log_role_change($user_id, $user->user_login, $action, 'mitglied', $zoho_value, $previous_roles, $new_roles);
        } else {
            dgptm_log('Role sync: no change needed', ['user_id' => $user_id, 'zoho_value' => $zoho_value, 'has_mitglied' => $has_mitglied]);
        }
    }
    
    /**
     * Parst verschiedene Boolean-Formate
     */
    private function parse_boolean($value) {
        if ($value === null) {
            return false;
        }
        
        // Numerische Prüfung
        if (is_numeric($value)) {
            return (int)$value === 1;
        }
        
        // String in Kleinbuchstaben
        $value_lower = strtolower(trim((string)$value));
        
        // True-Werte
        if (in_array($value_lower, ['true', '1', 'yes', 'ja', 'aktiv', 'active'], true)) {
            return true;
        }
        
        // False-Werte (inkl. leerer String)
        return false;
    }
    
    /**
     * Loggt Rollenwechsel in Datenbank
     */
    private function log_role_change($user_id, $username, $action, $role_name, $zoho_value, $previous_roles, $new_roles) {
        global $wpdb;
        
        $ip_address = $this->get_client_ip();
        $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr(sanitize_text_field($_SERVER['HTTP_USER_AGENT']), 0, 255) : '';
        
        $wpdb->insert(
            $this->log_table,
            [
                'user_id' => $user_id,
                'username' => $username,
                'action' => $action,
                'role_name' => $role_name,
                'zoho_value' => (string)$zoho_value,
                'previous_roles' => implode(',', $previous_roles),
                'new_roles' => implode(',', $new_roles),
                'ip_address' => $ip_address,
                'user_agent' => $user_agent,
            ],
            ['%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
        
        if ($wpdb->last_error) {
            dgptm_log('Role change logging failed', ['error' => $wpdb->last_error]);
        } else {
            dgptm_log('Role change logged to database', ['log_id' => $wpdb->insert_id]);
        }
    }
    
    /**
     * Ermittelt Client-IP (mit Proxy-Support)
     */
    private function get_client_ip() {
        $ip_keys = ['HTTP_CLIENT_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'REMOTE_ADDR'];
        
        foreach ($ip_keys as $key) {
            if (array_key_exists($key, $_SERVER)) {
                $ip_list = explode(',', $_SERVER[$key]);
                foreach ($ip_list as $ip) {
                    $ip = trim($ip);
                    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                        return substr($ip, 0, 45);
                    }
                }
            }
        }
        
        return isset($_SERVER['REMOTE_ADDR']) ? substr(sanitize_text_field($_SERVER['REMOTE_ADDR']), 0, 45) : '';
    }
    
    /**
     * Gibt Rollenwechsel-Logs aus (für Admin-UI)
     */
    public function get_role_logs($limit = 50, $offset = 0, $user_id = null) {
        global $wpdb;
        
        $where = $user_id ? $wpdb->prepare("WHERE user_id = %d", $user_id) : "";
        $query = $wpdb->prepare(
            "SELECT * FROM {$this->log_table} {$where} ORDER BY timestamp DESC LIMIT %d OFFSET %d",
            $limit, $offset
        );
        
        return $wpdb->get_results($query);
    }
    
    /**
     * Zählt Rollenwechsel-Logs
     */
    public function count_role_logs($user_id = null) {
        global $wpdb;
        
        if ($user_id) {
            return $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$this->log_table} WHERE user_id = %d", $user_id));
        }
        
        return $wpdb->get_var("SELECT COUNT(*) FROM {$this->log_table}");
    }
}

/* ========================================================
   DGPTM_Zoho_Plugin – Hauptklasse
   ======================================================== */
class DGPTM_Zoho_Plugin {
    private static $instance;
    private $zoho_data = null;
    private $counter = 0;

    private function __construct() {
        add_action('show_user_profile',        [$this, 'add_zoho_id_user_field']);
        add_action('edit_user_profile',        [$this, 'add_zoho_id_user_field']);
        add_action('personal_options_update',  [$this, 'save_zoho_id_user_field']);
        add_action('edit_user_profile_update', [$this, 'save_zoho_id_user_field']);

        add_action('rest_api_init', [$this, 'register_user_meta_for_rest']);
        add_action('wp_footer',      [$this, 'inject_zoho_data_in_footer']);
        add_action('send_headers',   [$this, 'disable_caching']);
        add_action('admin_menu',     [$this, 'register_settings_page']);
        add_action('admin_init',     [$this, 'register_settings']);
        add_action('wp_ajax_dgptm_fetch_api_data', [$this, 'ajax_fetch_api_data']);

        add_action('admin_init',     [$this, 'handle_oauth_callback']);

        add_action('init', function() {
            load_plugin_textdomain('dgptm-zoho', false, dirname(plugin_basename(__FILE__)) . '/languages');
        });

        add_shortcode('zoho_api_data',      [$this, 'zoho_api_data_shortcode']);
        add_shortcode('zoho_api_data_ajax', [$this, 'zoho_api_data_ajax_shortcode']);
        add_shortcode('ifcrmfield',         [$this, 'ifcrmfield_shortcode']);

        register_uninstall_hook(__FILE__, ['DGPTM_Zoho_Plugin', 'uninstall']);
    }

    public static function get_instance() {
        if ( ! isset(self::$instance) ) self::$instance = new self();
        return self::$instance;
    }

    public static function uninstall() { dgptm_log('Plugin uninstall'); }

    // Profilfeld
    public function add_zoho_id_user_field($user) {
        if ( ! current_user_can('edit_user', $user->ID) ) return; ?>
        <h3><?php esc_html_e('Zusätzliche Informationen', 'dgptm-zoho'); ?></h3>
        <table class="form-table">
            <tr>
                <th><label for="zoho_id"><?php esc_html_e('Zoho-ID', 'dgptm-zoho'); ?></label></th>
                <td>
                    <input type="text" name="zoho_id" id="zoho_id" value="<?php echo esc_attr(get_user_meta($user->ID, 'zoho_id', true)); ?>" class="regular-text" />
                    <br /><span class="description"><?php esc_html_e('Bitte die eindeutige Zoho-ID des Benutzers eingeben.', 'dgptm-zoho'); ?></span>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_zoho_id_user_field($user_id) {
        if ( ! current_user_can('edit_user', $user_id) ) return;
        if ( isset($_POST['zoho_id']) ) {
            $zoho_id = sanitize_text_field($_POST['zoho_id']);
            if ( ! empty($zoho_id) && ! $this->is_zoho_id_unique($zoho_id, $user_id) ) {
                wp_die(__('Die eingegebene Zoho-ID wird bereits von einem anderen Benutzer verwendet. Bitte wählen Sie eine eindeutige Zoho-ID.', 'dgptm-zoho'), __('Fehler', 'dgptm-zoho'), ['back_link' => true]);
            }
            update_user_meta($user_id, 'zoho_id', $zoho_id);
            dgptm_log('Saved user zoho_id', ['user_id'=>$user_id,'zoho_id_set'=> (bool)$zoho_id ]);
        }
    }

    private function is_zoho_id_unique($zoho_id, $user_id) {
        global $wpdb;
        $existing_user = $wpdb->get_var($wpdb->prepare(
            "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = 'zoho_id' AND meta_value = %s AND user_id != %d",
            $zoho_id, $user_id
        ));
        return ! $existing_user;
    }

    public function register_user_meta_for_rest() {
        register_meta('user', 'zoho_id', [
            'type' => 'string','description' => 'Zoho-ID','single' => true,'show_in_rest' => true,
            'auth_callback' => function() { return current_user_can('edit_users'); },
        ]);
    }

    public function inject_zoho_data_in_footer() {
        if ( ! is_user_logged_in() ) return;
        $data = $this->fetch_zoho_data();
        if ( is_string($data) || empty($data) ) return;
        wp_register_script('dgptm-zoho-data', '', [], false, true);
        wp_enqueue_script('dgptm-zoho-data');
        wp_add_inline_script('dgptm-zoho-data', 'window.zohoData = ' . wp_json_encode($data) . ';', 'after');
    }

    private function fetch_zoho_data() {
        if ( $this->zoho_data === null ) {
            $this->zoho_data = $this->fetch_zoho_data_with_oauth();
            
            // Rollensynchronisation beim ersten Abruf nach Login durchführen
            if (is_array($this->zoho_data) && !empty($this->zoho_data)) {
                $role_manager = DGPTM_Role_Manager::get_instance();
                $role_manager->sync_member_role($this->zoho_data);
            }
        }
        return $this->zoho_data;
    }

    private function extract_zoho_payload( $decoded ) {
        if ( isset($decoded['details']['output']) ) {
            $inner = json_decode($decoded['details']['output'], true);
            return is_array($inner) && isset($inner['data']) ? $inner['data'] : (is_array($inner) ? $inner : []);
        } elseif ( isset($decoded['data']['output']) ) {
            $inner = json_decode($decoded['data']['output'], true);
            return is_array($inner) && isset($inner['data']) ? $inner['data'] : (is_array($inner) ? $inner : []);
        } elseif ( isset($decoded['data']) && is_array($decoded['data']) ) {
            return $decoded['data'];
        }
        return [];
    }

    public function fetch_zoho_data_with_oauth() {
        $base_url = get_option('dgptm_zoho_api_url', '');
        if ( empty($base_url) ) return [];
        $user_id = get_current_user_id();
        if ( ! $user_id ) return [];
        $cid = get_user_meta($user_id, 'zoho_id', true);
        if ( empty($cid) ) return [];

        $access_token = $this->get_valid_access_token();
        if ( is_wp_error($access_token) || empty($access_token) ) return [];

        $url = add_query_arg(['cid'=>$cid,'wpid'=>$user_id], $base_url);
        $response = dgptm_safe_remote('GET', $url, [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'Accept'        => 'application/json',
            ],
            'timeout' => 20,
        ]);
        if ( is_wp_error($response) ) return [];
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        if ( ! is_array($decoded) ) return [];
        return $this->extract_zoho_payload($decoded);
    }

    public function fetch_zoho_data_for_user($user_id) {
        $base_url = get_option('dgptm_zoho_api_url', '');
        if ( empty($base_url) || ! $user_id ) return [];
        $cid = get_user_meta($user_id, 'zoho_id', true);
        if ( empty($cid) ) return [];

        $access_token = $this->get_valid_access_token();
        if ( is_wp_error($access_token) || empty($access_token) ) return [];

        $url = add_query_arg(['cid'=>$cid,'wpid'=>$user_id], $base_url);
        $response = dgptm_safe_remote('GET', $url, [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                'Accept'        => 'application/json',
            ],
            'timeout' => 20,
        ]);
        if ( is_wp_error($response) ) return [];
        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        if ( ! is_array($decoded) ) return [];
        return $this->extract_zoho_payload($decoded);
    }

    public function get_oauth_token() { return $this->get_valid_access_token(); }

    private function get_valid_access_token() {
        $access_token = get_option('dgptm_zoho_access_token', '');
        $expires_at   = (int) get_option('dgptm_zoho_token_expires', 0);
        if ( empty($access_token) || time() >= $expires_at ) {
            $refresh_token = get_option('dgptm_zoho_refresh_token', '');
            $client_id     = get_option('dgptm_zoho_client_id', '');
            $client_secret = get_option('dgptm_zoho_client_secret', '');
            if ( empty($refresh_token) || empty($client_id) || empty($client_secret) ) {
                return new WP_Error('oauth_error', __('Fehlende OAuth2-Konfiguration.', 'dgptm-zoho'));
            }
            $token_url = 'https://accounts.zoho.eu/oauth/v2/token';
            $response = dgptm_safe_remote('POST', $token_url, [
                'headers' => [
                    'Content-Type' => 'application/x-www-form-urlencoded',
                    'Accept'       => 'application/json'
                ],
                'body' => [
                    'refresh_token' => $refresh_token,
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'grant_type'    => 'refresh_token'
                ],
                'timeout' => 20,
            ]);
            if ( is_wp_error($response) ) {
                return new WP_Error('oauth_error', __('Fehler beim Aktualisieren des Tokens: ', 'dgptm-zoho') . $response->get_error_message());
            }
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            if ( isset($data['access_token']) ) {
                $access_token = sanitize_text_field($data['access_token']);
                $expires_in   = isset($data['expires_in']) ? (int) $data['expires_in'] : 3600;
                update_option('dgptm_zoho_access_token', $access_token);
                update_option('dgptm_zoho_token_expires', time() + $expires_in - 60);
                dgptm_log('Refreshed Zoho access token');
            } else {
                return new WP_Error('oauth_error', __('Kein Zugriffstoken gefunden.', 'dgptm-zoho'));
            }
        }
        return $access_token;
    }

    public function handle_oauth_callback() {
        if ( ! is_admin() || ( $_GET['page'] ?? '' ) !== 'dgptm-zoho-api-settings' ) return;
        if ( ! isset($_GET['code']) ) return;
        $code          = sanitize_text_field($_GET['code']);
        $client_id     = get_option('dgptm_zoho_client_id', '');
        $client_secret = get_option('dgptm_zoho_client_secret', '');
        $redirect_uri  = admin_url('options-general.php?page=dgptm-zoho-api-settings');
        if ( empty($code) || empty($client_id) || empty($client_secret) ) return;

        $token_url = 'https://accounts.zoho.eu/oauth/v2/token';
        $response  = dgptm_safe_remote('POST', $token_url, [
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept'       => 'application/json'
            ],
            'body' => [
                'code'          => $code,
                'client_id'     => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri'  => $redirect_uri,
                'grant_type'    => 'authorization_code'
            ],
            'timeout' => 20
        ]);
        if ( is_wp_error($response) ) {
            add_action('admin_notices', function() use ($response) {
                echo '<div class="error"><p>' . esc_html(__('Fehler beim Abrufen des Zugriffstokens: ', 'dgptm-zoho') . $response->get_error_message()) . '</p></div>';
            });
            dgptm_log('OAuth callback failed', ['error'=>$response->get_error_message()]);
            return;
        }
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        if ( isset($data['access_token']) ) {
            update_option('dgptm_zoho_access_token', sanitize_text_field($data['access_token']));
            if ( isset($data['refresh_token']) ) {
                update_option('dgptm_zoho_refresh_token', sanitize_text_field($data['refresh_token']));
            }
            $expires_in = isset($data['expires_in']) ? (int) $data['expires_in'] : 3600;
            update_option('dgptm_zoho_token_expires', time() + $expires_in - 60);
            add_action('admin_notices', function() {
                echo '<div class="updated"><p>' . esc_html(__('OAuth2-Verbindung hergestellt.', 'dgptm-zoho')) . '</p></div>';
            });
            dgptm_log('OAuth callback success');
        } else {
            add_action('admin_notices', function() use ($data) {
                echo '<div class="error"><p>' . esc_html(__('Fehler beim Abrufen des Zugriffstokens. Antwort: ', 'dgptm-zoho') . print_r($data, true)) . '</p></div>';
            });
            dgptm_log('OAuth callback response without token', ['data'=>$data]);
        }
        wp_redirect(admin_url('options-general.php?page=dgptm-zoho-api-settings'));
        exit;
    }

    public function ajax_fetch_api_data() {
        if ( ! current_user_can('manage_options') ) wp_send_json_error(__('Keine Berechtigung.', 'dgptm-zoho'));
        check_ajax_referer('dgptm_api_test_nonce', 'nonce');
        $user_id = isset($_POST['user_id']) ? (int) $_POST['user_id'] : 0;
        if ( ! $user_id ) wp_send_json_error(__('Ungültiger Benutzer.', 'dgptm-zoho'));
        $data = $this->fetch_zoho_data_for_user($user_id);
        if ( is_string($data) ) wp_send_json_error($data);
        wp_send_json_success($data);
    }

    public function disable_caching() {
        $route = isset($_GET['rest_route']) ? (string) $_GET['rest_route'] : '';
        if ( defined('REST_REQUEST') && REST_REQUEST && function_exists('str_starts_with') && str_starts_with($route, '/dgptm/v1') ) {
            nocache_headers();
        } elseif ( defined('REST_REQUEST') && REST_REQUEST && strpos($route, '/dgptm/v1') === 0 ) {
            nocache_headers();
        }
    }

    public function register_settings_page() {
        add_options_page(
            __('Zoho API Einstellungen', 'dgptm-zoho'),
            __('Zoho API', 'dgptm-zoho'),
            'manage_options',
            'dgptm-zoho-api-settings',
            [$this, 'settings_page_html']
        );
        
        // Neue Admin-Seite für Rollenwechsel-Logs
        add_users_page(
            __('Rollenwechsel-Protokoll', 'dgptm-zoho'),
            __('Rollenwechsel-Log', 'dgptm-zoho'),
            'manage_options',
            'dgptm-role-changes-log',
            [$this, 'role_changes_log_page']
        );
    }

    public function register_settings() {
        register_setting('dgptm_zoho_api_settings', 'dgptm_zoho_api_url', [
            'type'              => 'string',
            'description'       => __('Die URL der Zoho API.', 'dgptm-zoho'),
            'sanitize_callback' => function( $val ) {
                $val = esc_url_raw($val);
                return dgptm_is_allowed_url($val) ? $val : '';
            },
            'default'           => '',
        ]);
        register_setting('dgptm_zoho_api_settings', 'dgptm_zoho_client_id', [
            'type'              => 'string',
            'description'       => __('Zoho OAuth2 Client ID.', 'dgptm-zoho'),
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);
        register_setting('dgptm_zoho_api_settings', 'dgptm_zoho_client_secret', [
            'type'              => 'string',
            'description'       => __('Zoho OAuth2 Client Secret.', 'dgptm-zoho'),
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ]);
        register_setting('dgptm_zoho_api_settings', 'dgptm_debug_log', [
            'type'              => 'boolean',
            'description'       => __('Debug-Logging in debug.log aktivieren.', 'dgptm-zoho'),
            'sanitize_callback' => function($v){ return (int) (bool)$v; },
            'default'           => 0,
        ]);
    }

    public function settings_page_html() {
        if ( ! current_user_can('manage_options') ) return;
        $endpoints_ui = isset($GLOBALS['dgptm_additional_endpoints']) && $GLOBALS['dgptm_additional_endpoints'] instanceof Additional_Zoho_Endpoints
            ? $GLOBALS['dgptm_additional_endpoints'] : null;

        $active_tab = ( isset($_GET['dgptm_tab']) && $_GET['dgptm_tab'] === 'guide' ) ? 'guide' : 'settings';
        $base_url   = admin_url('options-general.php?page=dgptm-zoho-api-settings');
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Zoho API Einstellungen','dgptm-zoho'); ?></h1>

            <style>
                .dgptm-tabs .nav-tab{margin-right:4px}
                .dgptm-guide pre{background:#f6f7f7;border:1px solid #ccd0d4;padding:10px;overflow:auto}
                .dgptm-guide code{font-family:Menlo,Monaco,Consolas,monospace}
                .dgptm-section{margin-top:20px}
            </style>

            <h2 class="nav-tab-wrapper dgptm-tabs">
                <a href="<?php echo esc_url(add_query_arg('dgptm_tab','settings',$base_url)); ?>"
                   class="nav-tab <?php echo ($active_tab==='settings') ? 'nav-tab-active' : ''; ?>">
                   <?php esc_html_e('Einstellungen','dgptm-zoho'); ?>
                </a>
                <a href="<?php echo esc_url(add_query_arg('dgptm_tab','guide',$base_url)); ?>"
                   class="nav-tab <?php echo ($active_tab==='guide') ? 'nav-tab-active' : ''; ?>">
                   <?php esc_html_e('Anleitung','dgptm-zoho'); ?>
                </a>
            </h2>

            <!-- TAB: Einstellungen -->
            <div id="dgptm-tab-settings" style="<?php echo ($active_tab==='settings') ? '' : 'display:none'; ?>">
                <form action="options.php" method="post">
                    <?php settings_fields('dgptm_zoho_api_settings'); do_settings_sections('dgptm_zoho_api_settings'); ?>
                    <table class="form-table">
                        <tr>
                            <th><label for="dgptm_zoho_api_url"><?php esc_html_e('API-URL','dgptm-zoho'); ?></label></th>
                            <td>
                                <input type="url" name="dgptm_zoho_api_url" id="dgptm_zoho_api_url" value="<?php echo esc_attr(get_option('dgptm_zoho_api_url','')); ?>" class="regular-text"/>
                                <p class="description"><?php esc_html_e('HTTPS-URL der Zoho API (wird auf interne/unsichere Hosts geprüft).','dgptm-zoho'); ?></p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="dgptm_zoho_client_id"><?php esc_html_e('Zoho Client ID','dgptm-zoho'); ?></label></th>
                            <td><input type="text" name="dgptm_zoho_client_id" id="dgptm_zoho_client_id" value="<?php echo esc_attr(get_option('dgptm_zoho_client_id','')); ?>" class="regular-text"/></td>
                        </tr>
                        <tr>
                            <th><label for="dgptm_zoho_client_secret"><?php esc_html_e('Zoho Client Secret','dgptm-zoho'); ?></label></th>
                            <td><input type="text" name="dgptm_zoho_client_secret" id="dgptm_zoho_client_secret" value="<?php echo esc_attr(get_option('dgptm_zoho_client_secret','')); ?>" class="regular-text"/></td>
                        </tr>
                        <tr>
                            <th><label for="dgptm_debug_log"><?php esc_html_e('Debug-Logging aktivieren','dgptm-zoho'); ?></label></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="dgptm_debug_log" id="dgptm_debug_log" value="1" <?php checked( (int) get_option('dgptm_debug_log', 0), 1 ); ?> />
                                    <?php esc_html_e('Ereignisse in wp-content/debug.log protokollieren (WP_DEBUG_LOG muss aktiv sein).','dgptm-zoho'); ?>
                                </label>
                                <p class="description">
                                    <?php esc_html_e('Zum Aktivieren in wp-config.php: define("WP_DEBUG", true); define("WP_DEBUG_LOG", true);','dgptm-zoho'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Speichern','dgptm-zoho')); ?>
                </form>

                <div class="dgptm-section">
                    <h2><?php esc_html_e('OAuth2 Verbindung','dgptm-zoho'); ?></h2>
                    <?php
                    $client_id = get_option('dgptm_zoho_client_id','');
                    if ( ! empty($client_id) ) {
                        $redirect_uri = admin_url('options-general.php?page=dgptm-zoho-api-settings');
                        // Extended scopes for:
                        // - functions.execute: Custom CRM functions (existing)
                        // - modules.READ: Direct access to CRM modules like Contacts, Accounts (for daten-bearbeiten)
                        // - modules.ALL: Full module access including update operations
                        // - files.READ/CREATE: File upload operations (for student certificates)
                        $scope = 'ZohoCRM.functions.execute.READ,ZohoCRM.functions.execute.CREATE,ZohoCRM.modules.READ,ZohoCRM.modules.ALL,ZohoCRM.files.READ,ZohoCRM.files.CREATE';
                        $auth_url = 'https://accounts.zoho.eu/oauth/v2/auth?scope=' . urlencode($scope) . '&client_id=' . urlencode($client_id) . '&response_type=code&access_type=offline&redirect_uri=' . urlencode($redirect_uri);
                        echo '<p><a class="button" href="' . esc_url($auth_url) . '">' . esc_html__('Mit Zoho verbinden','dgptm-zoho') . '</a></p>';
                    } else {
                        echo '<p>' . esc_html__('Bitte zuerst Zoho Client ID speichern.','dgptm-zoho') . '</p>';
                    }
                    $access_token = get_option('dgptm_zoho_access_token','');
                    if ( ! empty($access_token) ) {
                        echo '<p>' . esc_html__('OAuth2 Verbindung ist hergestellt.','dgptm-zoho') . '</p>';
                    } else {
                        echo '<p>' . esc_html__('Keine OAuth2 Verbindung vorhanden.','dgptm-zoho') . '</p>';
                    }
                    ?>
                </div>

                <div class="dgptm-section">
                    <h2><?php esc_html_e('API-Daten Test','dgptm-zoho'); ?></h2>
                    <p><?php esc_html_e('Wählen Sie einen Benutzer aus und klicken Sie auf "API-Daten abrufen".','dgptm-zoho'); ?></p>
                    <select id="dgptm_test_user_id">
                        <?php
                        $users = get_users(['orderby'=>'display_name']);
                        foreach($users as $user){
                            $zoho_id = get_user_meta($user->ID, 'zoho_id', true);
                            $label   = $user->display_name . ' (' . $user->user_login . ')';
                            if ( $zoho_id ) $label .= ' - ' . esc_html($zoho_id);
                            echo '<option value="' . esc_attr($user->ID) . '">' . esc_html($label) . '</option>';
                        }
                        ?>
                    </select>
                    <button type="button" id="dgptm_fetch_api_data"><?php esc_html_e('API-Daten abrufen','dgptm-zoho'); ?></button>
                    <div id="dgptm_api_test_result" style="margin-top:20px;border:1px solid #ccc;padding:10px;"></div>
                    <script>
                        jQuery(document).ready(function($){
                            $('#dgptm_fetch_api_data').click(function(){
                                var userId = $('#dgptm_test_user_id').val();
                                var nonce  = '<?php echo wp_create_nonce('dgptm_api_test_nonce'); ?>';
                                $('#dgptm_api_test_result').text('<?php echo esc_js(__('Lade API-Daten...','dgptm-zoho')); ?>');
                                $.post(ajaxurl, { action: 'dgptm_fetch_api_data', nonce: nonce, user_id: userId }, function(resp){
                                    if(resp && resp.success){
                                        $('#dgptm_api_test_result').html('<pre>'+JSON.stringify(resp.data, null, 2)+'</pre>');
                                    } else {
                                        $('#dgptm_api_test_result').html('<span style="color:red;">'+(resp && resp.data ? resp.data : 'Error')+'</span>');
                                    }
                                });
                            });
                        });
                    </script>
                </div>

                <div class="dgptm-section">
                    <?php
                    if ( $endpoints_ui ) {
                        echo $endpoints_ui->render_endpoints_settings();
                    } else {
                        echo '<p><em>'.esc_html__('Die Endpunkte-Verwaltung ist nicht geladen.','dgptm-zoho').'</em></p>';
                    }
                    ?>
                </div>
            </div>

            <!-- TAB: Anleitung -->
            <div id="dgptm-tab-guide" class="dgptm-guide" style="<?php echo ($active_tab==='guide') ? '' : 'display:none'; ?>">
                <h2><?php esc_html_e('Anleitung','dgptm-zoho'); ?></h2>

                <h3>Installation / Update</h3>
                <ol>
                    <li>Alte Version deaktivieren.</li>
                    <li>Datei in <code>wp-content/plugins/dgptm-zoho-hardened/</code> ablegen und Plugin aktivieren.</li>
                    <li>Unter <strong>Einstellungen → Zoho API</strong> API‑URL, Client ID und Client Secret eintragen.</li>
                    <li>Auf <strong>„Mit Zoho verbinden“</strong> klicken und OAuth2 autorisieren.</li>
                </ol>

                <h3>Endpunkte anlegen</h3>
                <p>Bereich: <strong>Einstellungen → Zoho API → „Zusätzliche Endpunkte“</strong></p>
                <ul>
                    <li><strong>Slug</strong>: Pfad unter <code>/wp-json/dgptm/v1/{slug}</code> (hierarchisch erlaubt, z. B. <code>crm/webhook</code>).</li>
                    <li><strong>Ziel‑URL</strong>: Muss HTTPS und öffentlich erreichbar sein (SSRF‑Schutz aktiv).</li>
                    <li><strong>Nur interne Aufrufe</strong>: Wenn aktiviert, sind Aufrufe ausschließlich aus internem WordPress/PHP‑Code erlaubt (z. B. via <code>rest_do_request</code>). Externe HTTP‑Aufrufe werden mit 403 abgewiesen.</li>
                    <li><strong>Weiterleitungs‑Methode</strong>: <code>GET</code> oder <code>POST</code> für den Forward.</li>
                    <li><strong>Zoho‑Auth mitsenden</strong>: Fügt <code>Authorization: Zoho-oauthtoken &lt;token&gt;</code> hinzu.</li>
                    <li><strong>Verknüpfte WP‑Seite</strong>: Optionaler 302‑Redirect auf eine Seite (für Shortcodes). <em>Hinweis:</em> Ist „Nur interne Aufrufe“ aktiv, wird auch der Redirect blockiert (403) – Option daher nur nutzen, wenn kein öffentlicher Redirect gewünscht ist.</li>
                </ul>

                <h3>Interne Aufrufe programmatisch (für Plugins/Themes)</h3>
                <p>Beispiel: Internen Endpoint <code>zusage</code> aufrufen (GET) und Query‑Parameter übergeben:</p>
<pre><code><?php echo esc_html('
$slug   = "zusage";
$route  = "/dgptm/v1/" . $slug;
$req    = new WP_REST_Request("GET", $route);

// Query-Parameter:
$req->set_param("ref", "111");

// Interne HMAC-Header setzen (zeitbasiert, serverseitiges Secret):
$hdrs = dgptm_internal_signature_headers($slug, "GET");
$req->set_header("x-dgptm-ts",       $hdrs["x-dgptm-ts"]);
$req->set_header("x-dgptm-internal", $hdrs["x-dgptm-internal"]);

// Request ausführen:
$resp = rest_do_request($req);
if ( ! is_wp_error($resp) && ! $resp->is_error() ) {
    $data = $resp->get_data();
    // weiter verarbeiten...
}
'); ?></code></pre>
                <p>Für <code>POST</code> analog – Methode <code>"POST"</code> setzen und bei Bedarf Body/JSON per <code>$req->set_body()</code> hinzufügen; dann ebenso <code>dgptm_internal_signature_headers($slug, "POST")</code> verwenden.</p>

                <h3>Shortcodes (Auszug)</h3>
                <ul>
                    <li><code>[zoho_api_data field="FELD"]</code> – Feld aus den via OAuth geladenen Benutzer‑Zoho‑Daten.</li>
                    <li><code>[zoho_api_data_ajax field="FELD"]</code> – clientseitiger Zugriff auf <code>window.zohoData</code>.</li>
                    <li><code>[ifcrmfield]</code> – Conditional Rendering basierend auf Zoho‑Feldern.</li>
                    <li><code>[api-abfrage slug="..." field="..."]</code> – interner REST‑Aufruf auf <code>/dgptm/v1/{slug}</code> (setzt interne Signatur‑Header automatisch, wenn „Nur interne Aufrufe" aktiv ist).</li>
                    <li><code>[api-abruf]</code> / <code>[zoho_api_antwort]</code> – server‑/ajax‑seitige Abrufe der Ziel‑URL (Forwarder‑Konfiguration wird genutzt).</li>
                </ul>

                <h3>Debugging</h3>
                <ol>
                    <li>In <code>wp-config.php</code> aktivieren:
<code>define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);</code></li>
                    <li>In diesem Tab: <strong>Debug‑Logging aktivieren</strong> (Checkbox).</li>
                    <li>Endpoint testen (intern oder extern) und <code>wp-content/debug.log</code> prüfen. Wichtige Einträge: <code>REST routes registered</code>, <code>REST route hit</code>, <code>Permission check</code>, <code>Preparing forward</code>, <code>Forwarding done</code>, <code>Invalid JSON from target</code>.</li>
                </ol>

                <h3>Sicherheit / Allowlist</h3>
<pre><code><?php echo esc_html('add_filter("dgptm_allowed_hosts", function($hosts){ return ["functions.zoho.eu", "api.mein-webhook.de"]; });'); ?></code></pre>
<pre><code><?php echo esc_html('add_filter("dgptm_require_dns_resolution", "__return_true");'); ?></code></pre>

                <h3>Troubleshooting</h3>
                <ul>
                    <li><strong>403 „Nur interne Aufrufe“</strong> – Der Endpoint ist auf intern gestellt; externe HTTP‑Aufrufe sind gesperrt. Für interne Aufrufe <code>dgptm_internal_signature_headers()</code> nutzen.</li>
                    <li><strong>302 statt Forward</strong> – Eine WP‑Seite ist verknüpft (Absicht). Bei „Nur interne Aufrufe“ wird extern bereits geblockt, noch bevor Redirect erfolgt.</li>
                    <li><strong>„Nicht erlaubte Ziel‑URL“</strong> – Ziel ist nicht HTTPS/öffentlich oder durch Allowlist geblockt (siehe Debug‑Logs).</li>
                    <li><strong>„Die API‑Antwort ist kein gültiges JSON.“</strong> – Der Webhook antwortete nicht mit JSON. Forward fand statt.</li>
                </ul>
            </div>
        </div>
        <?php
    }


    /**
     * Admin-Seite für Rollenwechsel-Protokoll
     */
    public function role_changes_log_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        $role_manager = DGPTM_Role_Manager::get_instance();
        
        // Pagination
        $per_page = 50;
        $current_page = isset($_GET['paged']) ? max(1, (int)$_GET['paged']) : 1;
        $offset = ($current_page - 1) * $per_page;
        
        // Filter nach User
        $filter_user_id = isset($_GET['filter_user_id']) ? (int)$_GET['filter_user_id'] : null;
        
        // Logs abrufen
        $logs = $role_manager->get_role_logs($per_page, $offset, $filter_user_id);
        $total_logs = $role_manager->count_role_logs($filter_user_id);
        $total_pages = ceil($total_logs / $per_page);
        
        ?>
        <div class="wrap">
            <h1><?php esc_html_e('Rollenwechsel-Protokoll', 'dgptm-zoho'); ?></h1>
            
            <style>
                .dgptm-log-table { width: 100%; margin-top: 20px; }
                .dgptm-log-table th { text-align: left; font-weight: 600; padding: 10px; background: #f0f0f1; }
                .dgptm-log-table td { padding: 10px; border-bottom: 1px solid #ddd; }
                .dgptm-log-table tr:hover { background: #f9f9f9; }
                .dgptm-action-added { color: #46b450; font-weight: 600; }
                .dgptm-action-removed { color: #dc3232; font-weight: 600; }
                .dgptm-action-removed_with_fallback { color: #ffb900; font-weight: 600; }
                .dgptm-filter-form { background: #fff; padding: 15px; margin: 20px 0; border: 1px solid #ccd0d4; }
                .dgptm-stats { display: flex; gap: 20px; margin: 20px 0; }
                .dgptm-stat-box { background: #fff; padding: 15px 20px; border-left: 4px solid #2271b1; min-width: 150px; }
                .dgptm-stat-box h3 { margin: 0 0 5px 0; font-size: 28px; font-weight: 600; }
                .dgptm-stat-box p { margin: 0; color: #666; font-size: 13px; }
            </style>
            
            <!-- Statistiken -->
            <div class="dgptm-stats">
                <div class="dgptm-stat-box">
                    <h3><?php echo esc_html(number_format_i18n($total_logs)); ?></h3>
                    <p><?php esc_html_e('Gesamt Ereignisse', 'dgptm-zoho'); ?></p>
                </div>
            </div>
            
            <!-- Filter -->
            <div class="dgptm-filter-form">
                <form method="get">
                    <input type="hidden" name="page" value="dgptm-role-changes-log" />
                    <label for="filter_user_id"><?php esc_html_e('Filter nach Benutzer-ID:', 'dgptm-zoho'); ?></label>
                    <input type="number" name="filter_user_id" id="filter_user_id" value="<?php echo esc_attr($filter_user_id); ?>" style="width: 100px;" />
                    <button type="submit" class="button"><?php esc_html_e('Filtern', 'dgptm-zoho'); ?></button>
                    <?php if ($filter_user_id): ?>
                        <a href="<?php echo esc_url(admin_url('users.php?page=dgptm-role-changes-log')); ?>" class="button"><?php esc_html_e('Filter zurücksetzen', 'dgptm-zoho'); ?></a>
                    <?php endif; ?>
                </form>
            </div>
            
            <?php if (empty($logs)): ?>
                <p><?php esc_html_e('Keine Rollenwechsel-Ereignisse gefunden.', 'dgptm-zoho'); ?></p>
            <?php else: ?>
                <table class="dgptm-log-table widefat">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Zeitstempel', 'dgptm-zoho'); ?></th>
                            <th><?php esc_html_e('Benutzer', 'dgptm-zoho'); ?></th>
                            <th><?php esc_html_e('Aktion', 'dgptm-zoho'); ?></th>
                            <th><?php esc_html_e('Rolle', 'dgptm-zoho'); ?></th>
                            <th><?php esc_html_e('Zoho-Wert', 'dgptm-zoho'); ?></th>
                            <th><?php esc_html_e('Vorherige Rollen', 'dgptm-zoho'); ?></th>
                            <th><?php esc_html_e('Neue Rollen', 'dgptm-zoho'); ?></th>
                            <th><?php esc_html_e('IP-Adresse', 'dgptm-zoho'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo esc_html(mysql2date(get_option('date_format') . ' ' . get_option('time_format'), $log->timestamp)); ?></td>
                                <td>
                                    <strong><?php echo esc_html($log->username); ?></strong><br>
                                    <small>ID: <?php echo esc_html($log->user_id); ?></small>
                                </td>
                                <td>
                                    <span class="dgptm-action-<?php echo esc_attr($log->action); ?>">
                                        <?php 
                                        switch ($log->action) {
                                            case 'added':
                                                echo '✓ ' . esc_html__('Hinzugefügt', 'dgptm-zoho');
                                                break;
                                            case 'removed':
                                                echo '✗ ' . esc_html__('Entfernt', 'dgptm-zoho');
                                                break;
                                            case 'removed_with_fallback':
                                                echo '⚠ ' . esc_html__('Entfernt + Fallback', 'dgptm-zoho');
                                                break;
                                            default:
                                                echo esc_html($log->action);
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td><code><?php echo esc_html($log->role_name); ?></code></td>
                                <td><code><?php echo esc_html($log->zoho_value); ?></code></td>
                                <td><small><?php echo esc_html($log->previous_roles); ?></small></td>
                                <td><small><?php echo esc_html($log->new_roles); ?></small></td>
                                <td><small><?php echo esc_html($log->ip_address); ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                
                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="tablenav">
                        <div class="tablenav-pages">
                            <span class="displaying-num"><?php printf(esc_html__('%s Einträge', 'dgptm-zoho'), number_format_i18n($total_logs)); ?></span>
                            <?php
                            $page_links = paginate_links([
                                'base' => add_query_arg('paged', '%#%'),
                                'format' => '',
                                'prev_text' => __('&laquo;'),
                                'next_text' => __('&raquo;'),
                                'total' => $total_pages,
                                'current' => $current_page,
                            ]);
                            echo $page_links ? '<span class="pagination-links">' . $page_links . '</span>' : '';
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
            
            <div style="margin-top: 30px; padding: 15px; background: #f0f6fc; border-left: 4px solid #2271b1;">
                <h3><?php esc_html_e('Hinweise', 'dgptm-zoho'); ?></h3>
                <ul>
                    <li><?php esc_html_e('Die Rollensynchronisation erfolgt automatisch beim ersten Zugriff auf Zoho-Daten nach dem Login.', 'dgptm-zoho'); ?></li>
                    <li><?php esc_html_e('Das Zoho-Feld "aktives_mitglied" steuert die "mitglied"-Rolle (true/1 = hinzufügen, false/0 = entfernen).', 'dgptm-zoho'); ?></li>
                    <li><?php esc_html_e('Bei Rollenverlust ohne weitere Rollen wird automatisch "subscriber" als Fallback zugewiesen.', 'dgptm-zoho'); ?></li>
                    <li><?php esc_html_e('Andere Benutzerrollen bleiben unverändert.', 'dgptm-zoho'); ?></li>
                </ul>
            </div>
        </div>
        <?php
    }

    // Shortcodes
    public function zoho_api_data_shortcode($atts) {
        $atts = shortcode_atts(['field' => ''], $atts);
        $data = $this->fetch_zoho_data();
        if ( is_string($data) ) return '';
        $fieldname = $atts['field'];
        if ( $fieldname === '' || ! isset($data[$fieldname]) ) return '';
        $val = (string) $data[$fieldname];

        if ( filter_var($val, FILTER_VALIDATE_URL) && dgptm_is_allowed_url($val) ) {
            $allow_iframe = (bool) apply_filters('dgptm_allow_iframe', false, $val, $fieldname, $data);
            if ( $allow_iframe ) {
                return '<iframe src="' . esc_url($val) . '" width="600" height="600" loading="lazy" referrerpolicy="no-referrer" sandbox></iframe>';
            } else {
                return '<a href="'.esc_url($val).'" target="_blank" rel="noopener noreferrer">'.esc_html($val).'</a>';
            }
        }
        return esc_html($val);
    }

    public function zoho_api_data_ajax_shortcode($atts) {
        $this->counter++;
        $atts = shortcode_atts(['field' => ''], $atts);
        $unique_id = 'zoho-data-' . $this->counter;
        ob_start(); ?>
        <span id="<?php echo esc_attr($unique_id); ?>" data-field="<?php echo esc_attr($atts['field']); ?>"></span>
        <script>
            document.addEventListener('DOMContentLoaded', function(){
                if(!window.zohoData){ return; }
                var el = document.getElementById('<?php echo esc_js($unique_id); ?>');
                var name = el.getAttribute('data-field') || '';
                el.textContent = window.zohoData[name] || '';
            });
        </script>
        <?php
        return ob_get_clean();
    }

    public function ifcrmfield_shortcode($atts, $content = null){
        $data = $this->fetch_zoho_data();
        if ( is_string($data) || $content === null ) return '';
        $atts = shortcode_atts(['field' => '', 'value' => ''], $atts);
        $parent_field = $atts['field'];
        $content = preg_replace('/\[\/ifcrmfield\]/', '', $content);

        if ( $this->check_condition($parent_field, $atts['value'], $data) ) {
            $output = preg_replace('/\[(elseif|else).*?\].*$/s', '', $content);
            return $this->process_nested_shortcodes(trim($output));
        }

        $pattern = '/\[(elseif|else)(?:\s+field="(?<field>[^"]*)"(?:\s+value="(?<value>[^"]*)")?)?\](?<content>.*?)(?=\[(elseif|else|\/ifcrmfield)\]|$)/s';
        preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);
        $condition_met = false;
        foreach($matches as $match){
            $type  = $match[1];
            $field = $match['field'] ?? null;
            $value = $match['value'] ?? null;
            $block = $match['content'];
            if ( empty($field) ) $field = $parent_field;
            if ( $type === 'elseif' && ! $condition_met ){
                if ( $this->check_condition($field, $value, $data) ){
                    $condition_met = true;
                    return $this->process_nested_shortcodes(trim($block));
                }
            } elseif ( $type === 'else' && ! $condition_met ){
                return $this->process_nested_shortcodes(trim($block));
            }
        }
        return '';
    }

    private function process_nested_shortcodes($content){
        while(
            has_shortcode($content, 'ifcrmfield') ||
            has_shortcode($content, 'zoho_api_data') ||
            has_shortcode($content, 'zoho_api_data_ajax')
        ){
            $content = do_shortcode($content);
        }
        return $content;
    }

    private function check_condition($field, $value, $data){
        if ( ! isset($data[$field]) ) return false;
        $field_val = trim((string)$data[$field]);
        $compare   = trim((string)$value);
        if ( $compare === '' ) return ($field_val === '' || is_null($field_val));
        return (strcasecmp($field_val, $compare) === 0);
    }
}

/* ========================================================
   Additional_Zoho_Endpoints – Zusätzliche Endpunkte
   ======================================================== */
class Additional_Zoho_Endpoints {
    private $option_name = 'wf_endpoints';
    private static $api_cache = [];

  public function __construct() {
    add_action('admin_init',   [$this, 'register_endpoint_settings']);
    add_action('rest_api_init',[$this, 'register_endpoint_routes']);

    // Shortcodes direkt registrieren
    add_shortcode('zoho_debug_response', [$this, 'debug_response_shortcode']);
    add_shortcode('zoho_api_antwort',    [$this, 'zoho_api_antwort_shortcode']);
    add_shortcode('dgptm_api_antwort',   [$this, 'zoho_api_antwort_shortcode']);
    add_shortcode('api-abfrage',         [$this, 'api_abfrage_shortcode']);
    add_shortcode('api-abfrage-debug',   [$this, 'api_abfrage_debug_shortcode']);
    add_shortcode('api-abruf',           [$this, 'api_abruf_shortcode']);

    add_action('wp_ajax_api_abruf', [$this, 'ajax_api_abruf_handler']);
}

    public function register_endpoint_settings() {
        register_setting('wf_endpoints_settings', $this->option_name, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitize_endpoints'],
            'default'           => [],
        ]);
    }

    public function sanitize_endpoints($input){
        $normalize_endpoint = function($ep) {
            $out = [];
            $out['name']           = sanitize_text_field( $ep['name'] ?? '' );
            $out['slug']           = dgptm_sanitize_route_slug( $ep['slug'] ?? '' );
            $out['target_url']     = esc_url_raw( $ep['target_url'] ?? '' );
            $out['internal_only']  = (bool) ( $ep['internal_only'] ?? false );
            $method                = strtoupper( $ep['forward_method'] ?? 'GET' );
            $out['forward_method'] = in_array($method, ['GET','POST'], true) ? $method : 'GET';
            $out['send_zoho_auth'] = (bool) ( $ep['send_zoho_auth'] ?? false );
            $out['wp_page']        = isset($ep['wp_page']) ? (int) $ep['wp_page'] : 0;

            if ( empty($out['target_url']) || ! dgptm_is_allowed_url($out['target_url']) ) {
                dgptm_log('Endpoint target_url rejected by SSRF policy', ['slug'=>$out['slug'], 'target_url'=>$out['target_url']]);
                $out['target_url'] = '';
            }
            return $out;
        };

        if ( is_string($input) ) {
            $decoded = json_decode($input, true);
            if (json_last_error() !== JSON_ERROR_NONE) return [];
            $input = $decoded;
        }
        if ( ! is_array($input) ) return [];

        $result = [];
        foreach ( $input as $ep ) {
            $norm = $normalize_endpoint($ep);
            if ( $norm['slug'] && $norm['target_url'] ) {
                $result[] = $norm;
            } else {
                dgptm_log('Endpoint skipped (missing slug or target_url)', ['slug'=>$norm['slug'] ?? null]);
            }
        }
        return $result;
    }

   public function register_endpoint_routes() {
    $endpoints = $this->get_endpoints();
    if ( ! is_array($endpoints) ) return;

    foreach($endpoints as $ep){
        $ep = wp_parse_args($ep, [
            'internal_only'  => false,
            'forward_method' => 'GET',
            'send_zoho_auth' => false,
            'wp_page'        => 0,
        ]);
        if ( empty($ep['slug']) || empty($ep['target_url']) ) continue;

        $route = '/' . ltrim($ep['slug'], '/');

        register_rest_route('dgptm/v1', $route, [
            'methods'  => 'GET, POST, PUT, PATCH, DELETE', // Alle Methoden erlauben, da wir intern prüfen
            'callback' => function($req) use ($ep) {
                // --- NEU: Berechtigungsprüfung hierher verschoben ---
                if ( ! empty($ep['internal_only']) ) {
                    $is_internal_ok = dgptm_validate_internal_request($req, $ep['slug']);
                    dgptm_log('Internal-only route: Permission check in callback', ['slug'=>$ep['slug'], 'ok'=>$is_internal_ok]);
                    if ( ! $is_internal_ok ) {
                        return new WP_Error(
                            'rest_forbidden', 
                            __('Nur interne Aufrufe sind für diesen Endpunkt erlaubt.', 'dgptm-zoho'), 
                            ['status' => 403]
                        );
                    }
                }
                
                dgptm_log('REST route hit (in callback)', [
                    'route'         => method_exists($req,'get_route') ? $req->get_route() : '(n/a)',
                    'slug'          => $ep['slug'],
                    'method'        => $req->get_method(),
                    'internal_only' => (bool)$ep['internal_only'],
                    'wp_page'       => (int) $ep['wp_page'],
                ]);

                if ( ! empty($ep['wp_page']) ) {
                    $page_url = get_permalink($ep['wp_page']);
                    if ( ! $page_url ) {
                        dgptm_log('REST redirect failed: page not found', ['slug'=>$ep['slug'],'wp_page'=>$ep['wp_page']]);
                        return new WP_Error('invalid_page', __('Die verknüpfte WordPress-Seite existiert nicht oder ist nicht veröffentlicht.', 'dgptm-zoho'), ['status' => 404]);
                    }
                    dgptm_log('REST redirect to page', ['slug'=>$ep['slug'],'location'=>$page_url]);
                    $response = new WP_REST_Response(null, 302);
                    $response->header('Location', $page_url);
                    return $response;
                }
                
                $resp = $this->forward_request($req, $ep, $req->get_method());
                if ( is_wp_error($resp) ) {
                    dgptm_log('Forward request returned WP_Error', ['slug'=>$ep['slug'], 'error'=>$resp->get_error_message()]);
                } else {
                    dgptm_log('Forward request finished', ['slug'=>$ep['slug'], 'status'=>$resp->get_status()]);
                }
                return $resp;
            },
            // --- ALT: permission_callback wird vereinfacht ---
            'permission_callback' => '__return_true'
        ]);
    }
    dgptm_log('REST routes registered', ['count'=>count($endpoints)]);
}


    private function forward_request($request, $ep, $ignored_method) { // $ignored_method wird nicht mehr gebraucht
    // KORREKTUR: Die konfigurierte Methode des Endpunkts verwenden, nicht die des eingehenden Requests.
    $forward_method = isset($ep['forward_method']) ? strtoupper($ep['forward_method']) : 'GET';

    $headers = ['Accept' => 'application/json'];
    if ( $forward_method === 'POST' ) $headers['Content-Type'] = 'application/json';
    if ( ! empty($ep['send_zoho_auth']) ) {
        $token = DGPTM_Zoho_Plugin::get_instance()->get_oauth_token();
        if ( ! is_wp_error($token) && ! empty($token) ) {
            $headers['Authorization'] = 'Zoho-oauthtoken ' . $token;
        }
    }
    $headers = apply_filters('dgptm_forward_headers', $headers, $ep, $request);

    $target_url = $ep['target_url'];

    dgptm_log('Preparing forward', [
        'slug'    => $ep['slug'],
        'method'  => $forward_method, // Verwendet jetzt die korrekte Methode
        'url'     => $target_url,
        'hdr_set' => array_keys($headers),
    ]);

    if ( $forward_method === 'GET' ) {
        $query_params = dgptm_recursive_sanitize( $request->get_query_params() );
        if ( ! empty($query_params) ) {
            $target_url = add_query_arg($query_params, $target_url);
        }
        dgptm_log('GET forward', ['slug'=>$ep['slug'],'final_url'=>$target_url,'query'=>$query_params]);
        $response = dgptm_safe_remote('GET', $target_url, [
            'headers' => $headers,
            'timeout' => 10,
        ]);
    } else { // POST
        $body = $request->get_body();
        if ( empty($body) ) {
            $json_params = $request->get_json_params();
            if ( ! empty($json_params) ) {
                $body = wp_json_encode( dgptm_recursive_sanitize($json_params) );
            } else {
                $data = dgptm_recursive_sanitize( $request->get_query_params() );
                $body = wp_json_encode( ['data' => $data] );
            }
        }
        $body = apply_filters('dgptm_forward_body', $body, $ep, $request);
        dgptm_log('POST forward', ['slug'=>$ep['slug'],'url'=>$target_url,'body_bytes'=>strlen((string)$body)]);
        $response = dgptm_safe_remote('POST', $target_url, [
            'headers' => $headers,
            'body'    => $body,
            'timeout' => 10,
        ]);
    }

    if ( is_wp_error($response) ) {
        return new WP_Error('forward_error', __('Fehler beim Weiterleiten: ', 'dgptm-zoho') . $response->get_error_message(), ['status' => 500]);
    }

    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    $decoded = json_decode($body, true);
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        dgptm_log('Invalid JSON from target', ['slug'=>$ep['slug'],'status'=>$code,'body_bytes'=>strlen((string)$body)]);
        return new WP_Error('invalid_json', __('Die API-Antwort ist kein gültiges JSON.', 'dgptm-zoho'), ['status' => 500]);
    }

    // ... (Rest der Funktion bleibt gleich)
    if ( isset($decoded['details']) && is_array($decoded['details']) ) {
        if ( isset($decoded['details']['output']) && is_string($decoded['details']['output']) ) {
            $inner = json_decode($decoded['details']['output'], true);
            if ( json_last_error() === JSON_ERROR_NONE ) $decoded['details']['output'] = $inner;
        }
        if ( isset($decoded['details']['userMessage']) ) {
            if ( is_string($decoded['details']['userMessage']) ) {
                $inner = json_decode($decoded['details']['userMessage'], true);
                if ( json_last_error() === JSON_ERROR_NONE ) $decoded['details']['userMessage'] = $inner;
            } elseif ( is_array($decoded['details']['userMessage']) ) {
                foreach ($decoded['details']['userMessage'] as $k => $msg) {
                    if ( is_string($msg) ) {
                        $inner = json_decode($msg, true);
                        if ( json_last_error() === JSON_ERROR_NONE ) $decoded['details']['userMessage'][$k] = $inner;
                    }
                }
            }
        }
    }
    return new WP_REST_Response($decoded, $code);
}


    private function get_api_data($slug) {
        $query = dgptm_recursive_sanitize( $_GET );
        $cache_key = $slug . '_' . md5( wp_json_encode( $query ) );
        if ( isset(self::$api_cache[$cache_key]) ) return self::$api_cache[$cache_key];

        $endpoint = $this->get_endpoint_by_slug($slug);
        if ( ! $endpoint ) {
            dgptm_log('get_api_data: endpoint not found', ['slug'=>$slug]);
            return null;
        }

        $target_url = $endpoint['target_url'];
        $method     = isset($endpoint['forward_method']) ? strtoupper($endpoint['forward_method']) : 'POST';
        $headers    = ['Accept' => 'application/json'];
        if ( $method === 'POST' ) $headers['Content-Type'] = 'application/json';
        if ( ! empty($endpoint['send_zoho_auth']) ) {
            $token = DGPTM_Zoho_Plugin::get_instance()->get_oauth_token();
            if ( ! is_wp_error($token) && ! empty($token) ) $headers['Authorization'] = 'Zoho-oauthtoken ' . $token;
        }

        dgptm_log('get_api_data start', ['slug'=>$slug,'method'=>$method,'url'=>$target_url]);

        if ( $method === 'GET' ) {
            if ( ! empty($query) ) $target_url = add_query_arg($query, $target_url);
            $response = dgptm_safe_remote('GET', $target_url, ['headers'=>$headers,'timeout'=>10]);
        } else {
            $raw_input = file_get_contents('php://input');
            if ( ! empty($raw_input) ) {
                $decoded_input = json_decode($raw_input, true);
                if ( json_last_error() === JSON_ERROR_NONE ) {
                    $json_body = wp_json_encode( dgptm_recursive_sanitize($decoded_input) );
                } else {
                    $json_body = wp_json_encode( ['data' => $query] );
                }
            } else {
                $json_body = wp_json_encode( ['data' => $query] );
            }
            $response = dgptm_safe_remote('POST', $target_url, ['headers'=>$headers,'body'=>$json_body,'timeout'=>10]);
        }

        if ( is_wp_error($response) ) {
            dgptm_log('get_api_data remote error', ['slug'=>$slug,'error'=>$response->get_error_message()]);
            return null;
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            dgptm_log('get_api_data invalid json', ['slug'=>$slug,'bytes'=>strlen((string)$body)]);
            return null;
        }

        if ( isset($decoded['details']) && is_array($decoded['details']) ) {
            if ( isset($decoded['details']['output']) && is_string($decoded['details']['output']) ) {
                $inner = json_decode($decoded['details']['output'], true);
                if ( json_last_error() === JSON_ERROR_NONE ) $decoded['details']['output'] = $inner;
            }
            if ( isset($decoded['details']['userMessage']) ) {
                if ( is_string($decoded['details']['userMessage']) ) {
                    $inner = json_decode($decoded['details']['userMessage'], true);
                    if ( json_last_error() === JSON_ERROR_NONE ) $decoded['details']['userMessage'] = $inner;
                } elseif ( is_array($decoded['details']['userMessage']) ) {
                    foreach ( $decoded['details']['userMessage'] as $k => $msg ) {
                        if ( is_string($msg) ) {
                            $inner = json_decode($msg, true);
                            if ( json_last_error() === JSON_ERROR_NONE ) $decoded['details']['userMessage'][$k] = $inner;
                        }
                    }
                }
            }
        }
        self::$api_cache[$cache_key] = $decoded;
        dgptm_log('get_api_data ok', ['slug'=>$slug]);
        return $decoded;
    }

    private function get_nested_value($data, $field) {
        $parts = explode('.', $field);
        foreach($parts as $part){
            if ( is_array($data) && array_key_exists($part, $data) ) {
                $data = $data[$part];
            } else {
                return null;
            }
        }
        return $data;
    }

    private function get_endpoint_by_slug($slug) {
        $eps = $this->get_endpoints();
        if (is_array($eps)) {
            foreach ($eps as $ep) {
                if (isset($ep['slug']) && $ep['slug'] === $slug) return $ep;
            }
        }
        return null;
    }

    /**
     * Shortcode: [zoho_debug_response slug="SLUG"]
     * Nur für Administratoren – ruft die Ziel-URL des konfigurierten Endpunkts direkt ab (nicht die interne REST-Route).
     */
    public function debug_response_shortcode($atts) {
        if ( ! current_user_can('manage_options') ) return '';
        $atts = shortcode_atts(['slug'=>'','webhook'=>''], $atts);
        if ( empty($atts['slug']) && ! empty($atts['webhook']) ) $atts['slug'] = $atts['webhook'];

        $slug = dgptm_sanitize_route_slug($atts['slug']);
        if ( empty($slug) ) return '<p style="color:red;">Kein Slug angegeben.</p>';

        $endpoint = $this->get_endpoint_by_slug($slug);
        if ( ! $endpoint ) return '<p style="color:red;">Endpunkt "'.esc_html($slug).'" nicht gefunden.</p>';

        $headers = ['Content-Type'=>'application/json'];
        if ( ! empty($endpoint['send_zoho_auth']) ) {
            $zoho_token = DGPTM_Zoho_Plugin::get_instance()->get_oauth_token();
            if ( ! is_wp_error($zoho_token) && ! empty($zoho_token) ) $headers['Authorization'] = 'Zoho-oauthtoken '.$zoho_token;
        }

        $params = dgptm_recursive_sanitize( $_GET );
        unset($params['rest_route']);

        $method = isset($endpoint['forward_method']) ? strtoupper($endpoint['forward_method']) : 'POST';
        if ( $method === 'GET' ) {
            $url = add_query_arg($params, $endpoint['target_url']);
            $response = dgptm_safe_remote('GET', $url, ['headers'=>$headers, 'timeout'=>10]);
        } else {
            $response = dgptm_safe_remote('POST', $endpoint['target_url'], [
                'headers' => $headers,
                'body'    => wp_json_encode($params),
                'timeout' => 10,
            ]);
        }

        if ( is_wp_error($response) ) {
            return '<p style="color:red;">Fehler: '.esc_html($response->get_error_message()).'</p>';
        }

        $raw = wp_remote_retrieve_body($response);
        $json = json_decode($raw, true);
        if ( json_last_error() === JSON_ERROR_NONE ) {
            $pretty = wp_json_encode($json, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
            return '<pre style="background:#f8f8f8;border:1px solid #ccc;padding:10px;">'. esc_html($pretty) .'</pre>';
        } else {
            return '<pre style="background:#f8f8f8;border:1px solid #ccc;padding:10px;">'. esc_html($raw) .'</pre>';
        }
    }

 /**
 * KORRIGIERTER SHORTCODE: [api-abfrage]
 * 
 * Ersetzen Sie die bestehende api_abfrage_shortcode Funktion in der 
 * Additional_Zoho_Endpoints Klasse mit dieser Version:
 */

/**
 * Shortcode: [api-abfrage slug="..." field="..." param1="value1" param2="value2"]
 * Interner REST-Aufruf mit optionalen Parametern + Request-Caching
 */
public function api_abfrage_shortcode($atts) {
    // Standard-Attribute extrahieren
    $defaults = ['slug' => '', 'webhook' => '', 'field' => ''];
    $atts = shortcode_atts($defaults, $atts, 'api-abfrage');
    
    // Backward compatibility für 'webhook' Parameter
    if ( empty($atts['slug']) && ! empty($atts['webhook']) ) {
        $atts['slug'] = $atts['webhook'];
    }
    
    // Pflichtfelder prüfen
    if ( empty($atts['slug']) || empty($atts['field']) ) {
        dgptm_log('api_abfrage_shortcode: missing slug or field', ['atts' => $atts]);
        return '';
    }

    $slug = dgptm_sanitize_route_slug($atts['slug']);
    $field = sanitize_text_field($atts['field']);
    
    // Endpunkt-Konfiguration laden
    $endpoint = $this->get_endpoint_by_slug($slug);
    if ( ! $endpoint ) {
        dgptm_log('api_abfrage_shortcode: endpoint not found', ['slug' => $slug]);
        return '';
    }

    // Zusätzliche Parameter sammeln (alle außer slug, webhook, field)
    $custom_params = array_diff_key($atts, $defaults);
    
    // Query-Parameter aus $_GET hinzufügen (falls vorhanden)
    $all_params = array_merge(
        dgptm_recursive_sanitize($_GET),
        dgptm_recursive_sanitize($custom_params)
    );
    
    // === CACHE-PRÜFUNG ===
    $cache_key = $slug . '_' . md5(wp_json_encode($all_params));
    
    // Prüfen, ob bereits gecacht
    if ( isset(self::$api_cache[$cache_key]) ) {
        dgptm_log('api_abfrage_shortcode: using cached response', [
            'slug' => $slug,
            'cache_key' => $cache_key
        ]);
        
        $cached_data = self::$api_cache[$cache_key];
        $value = $this->get_nested_value($cached_data, $field);
        
        if ( $value === null ) {
            dgptm_log('api_abfrage_shortcode: field not found in cache', [
                'slug' => $slug,
                'field' => $field
            ]);
            return '';
        }
        
        return esc_html( is_scalar($value) ? (string) $value : wp_json_encode($value) );
    }
    // === ENDE CACHE-PRÜFUNG ===
    
    // REST-Request vorbereiten
    $route = '/dgptm/v1/' . $slug;
    
    // Methode des Endpunkts respektieren
    $method = isset($endpoint['forward_method']) ? strtoupper($endpoint['forward_method']) : 'GET';
    $request = new WP_REST_Request($method, $route);
    
    // Parameter setzen (als Query-Parameter für GET, als Body für POST)
    if ( $method === 'GET' ) {
        foreach ( $all_params as $param => $value ) {
            $request->set_param($param, $value);
        }
    } else {
        // Bei POST: Parameter im Body senden
        if ( ! empty($all_params) ) {
            $request->set_header('Content-Type', 'application/json');
            $request->set_body(wp_json_encode($all_params));
        }
    }

    // Interne Signatur setzen, wenn "Nur interne Aufrufe" aktiv ist
    if ( ! empty($endpoint['internal_only']) ) {
        $hdrs = dgptm_internal_signature_headers($slug, $method);
        $request->set_header('x-dgptm-ts', $hdrs['x-dgptm-ts']);
        $request->set_header('x-dgptm-internal', $hdrs['x-dgptm-internal']);
        
        dgptm_log('api_abfrage_shortcode: internal signature set', [
            'slug' => $slug,
            'method' => $method
        ]);
    }

    // Request ausführen
    dgptm_log('api_abfrage_shortcode: executing request (not cached)', [
        'slug' => $slug,
        'method' => $method,
        'route' => $route,
        'params_count' => count($all_params),
        'cache_key' => $cache_key
    ]);
    
    $response = rest_do_request($request);
    
    // Fehlerbehandlung
    if ( is_wp_error($response) ) {
        dgptm_log('api_abfrage_shortcode: WP_Error', [
            'slug' => $slug,
            'error' => $response->get_error_message(),
            'error_data' => $response->get_error_data()
        ]);
        return '';
    }
    
    if ( method_exists($response, 'is_error') && $response->is_error() ) {
        dgptm_log('api_abfrage_shortcode: REST error', [
            'slug' => $slug,
            'status' => $response->get_status(),
            'data' => $response->get_data()
        ]);
        return '';
    }
    
    // Daten extrahieren
    $data = $response->get_data();
    
    // === DATEN CACHEN ===
    self::$api_cache[$cache_key] = $data;
    dgptm_log('api_abfrage_shortcode: response cached', [
        'slug' => $slug,
        'cache_key' => $cache_key,
        'data_keys' => is_array($data) ? array_keys($data) : 'not_array'
    ]);
    // === ENDE CACHING ===
    
    dgptm_log('api_abfrage_shortcode: response received', [
        'slug' => $slug,
        'status' => method_exists($response, 'get_status') ? $response->get_status() : 'unknown',
        'data_keys' => is_array($data) ? array_keys($data) : 'not_array'
    ]);
    
    // Verschachtelten Wert extrahieren
    $value = $this->get_nested_value($data, $field);
    
    if ( $value === null ) {
        dgptm_log('api_abfrage_shortcode: field not found', [
            'slug' => $slug,
            'field' => $field,
            'available_keys' => is_array($data) ? array_keys($data) : 'not_array'
        ]);
        return '';
    }
    
    dgptm_log('api_abfrage_shortcode: success', [
        'slug' => $slug,
        'field' => $field,
        'value_type' => gettype($value)
    ]);
    
    // Rückgabe
    return esc_html( is_scalar($value) ? (string) $value : wp_json_encode($value) );
}

/**
 * ZUSÄTZLICHE HILFSFUNKTION für besseres Debugging
 * Fügen Sie diese auch in die Additional_Zoho_Endpoints Klasse ein:
 */

/**
 * Debug-Shortcode: [api-abfrage-debug slug="..." field="..."]
 * Zeigt die komplette Response-Struktur an (nur für Admins)
 */
public function api_abfrage_debug_shortcode($atts) {
    if ( ! current_user_can('manage_options') ) {
        return '<p style="color:red;">Nur für Administratoren</p>';
    }
    
    $defaults = ['slug' => '', 'field' => ''];
    $atts = shortcode_atts($defaults, $atts, 'api-abfrage-debug');
    
    if ( empty($atts['slug']) ) {
        return '<p style="color:red;">Slug fehlt</p>';
    }

    $slug = dgptm_sanitize_route_slug($atts['slug']);
    $endpoint = $this->get_endpoint_by_slug($slug);
    
    if ( ! $endpoint ) {
        return '<p style="color:red;">Endpunkt "' . esc_html($slug) . '" nicht gefunden</p>';
    }

    $route = '/dgptm/v1/' . $slug;
    $method = isset($endpoint['forward_method']) ? strtoupper($endpoint['forward_method']) : 'GET';
    $request = new WP_REST_Request($method, $route);
    
    // Parameter hinzufügen
    foreach ( dgptm_recursive_sanitize($_GET) as $param => $value ) {
        if ( $method === 'GET' ) {
            $request->set_param($param, $value);
        } else {
            $request->set_body(wp_json_encode([$param => $value]));
        }
    }

    // Interne Signatur bei Bedarf
    if ( ! empty($endpoint['internal_only']) ) {
        $hdrs = dgptm_internal_signature_headers($slug, $method);
        $request->set_header('x-dgptm-ts', $hdrs['x-dgptm-ts']);
        $request->set_header('x-dgptm-internal', $hdrs['x-dgptm-internal']);
    }

    $response = rest_do_request($request);
    
    ob_start();
    ?>
    <div style="background:#f8f8f8;border:1px solid #ccc;padding:15px;margin:10px 0;">
        <h3>API Debug Info für Slug: <?php echo esc_html($slug); ?></h3>
        
        <h4>Endpunkt-Konfiguration:</h4>
        <pre><?php echo esc_html(print_r($endpoint, true)); ?></pre>
        
        <h4>Request-Details:</h4>
        <pre>Methode: <?php echo esc_html($method); ?>
Route: <?php echo esc_html($route); ?>
Internal Only: <?php echo !empty($endpoint['internal_only']) ? 'Ja' : 'Nein'; ?></pre>
        
        <?php if ( is_wp_error($response) ): ?>
            <h4 style="color:red;">Fehler:</h4>
            <pre><?php echo esc_html($response->get_error_message()); ?></pre>
        <?php else: ?>
            <h4>Response Status:</h4>
            <pre><?php echo esc_html($response->get_status()); ?></pre>
            
            <h4>Response Daten:</h4>
            <pre><?php echo esc_html(print_r($response->get_data(), true)); ?></pre>
            
            <?php if ( ! empty($atts['field']) ): ?>
                <h4>Gefundener Wert für "<?php echo esc_html($atts['field']); ?>":</h4>
                <?php 
                $value = $this->get_nested_value($response->get_data(), $atts['field']);
                if ( $value === null ) {
                    echo '<pre style="color:red;">Feld nicht gefunden!</pre>';
                } else {
                    echo '<pre>' . esc_html(print_r($value, true)) . '</pre>';
                }
                ?>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}


    /**
     * Shortcode: [api-abruf slug="..." field="..."]
     * Lädt die API-Daten asynchron per Ajax (admin-ajax.php) – nur für eingeloggte Nutzer.
     */
    public function api_abruf_shortcode($atts) {
        $atts = shortcode_atts(['slug' => '', 'field' => '', 'webhook' => ''], $atts);
        if ( empty($atts['slug']) && ! empty($atts['webhook']) ) $atts['slug'] = $atts['webhook'];
        if ( empty($atts['slug']) || empty($atts['field']) ) return '';
        static $counter = 0; $counter++;
        $unique_id = 'api-abruf-' . $counter;
        $nonce = wp_create_nonce('dgptm_api_abruf_nonce');
        ob_start(); ?>
        <span id="<?php echo esc_attr($unique_id); ?>" data-slug="<?php echo esc_attr($atts['slug']); ?>" data-field="<?php echo esc_attr($atts['field']); ?>">
            <?php echo esc_html(__('Lade Daten...', 'dgptm-zoho')); ?>
        </span>
        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var el = document.getElementById('<?php echo esc_js($unique_id); ?>');
                var data = { action:'api_abruf', slug:el.getAttribute('data-slug'), field:el.getAttribute('data-field'), nonce:'<?php echo esc_js($nonce); ?>' };
                var url = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";
                if (window.jQuery) {
                    jQuery.post(url, data, function(resp){ el.textContent = (resp && resp.success) ? resp.data : ''; });
                } else {
                    var fd = new FormData(); for (var k in data) fd.append(k, data[k]);
                    fetch(url, {method:'POST', body:fd, credentials:'same-origin'})
                        .then(r=>r.json()).then(resp=>{ el.textContent = (resp && resp.success) ? resp.data : ''; })
                        .catch(()=>{ el.textContent=''; });
                }
            });
        </script>
        <?php
        return ob_get_clean();
    }

    public function ajax_api_abruf_handler() {
        if ( ! is_user_logged_in() ) wp_send_json_error('Unauthorized', 401);
        check_ajax_referer('dgptm_api_abruf_nonce', 'nonce');

        $slug  = isset($_REQUEST['slug'])  ? dgptm_sanitize_route_slug($_REQUEST['slug'])  : '';
        $field = isset($_REQUEST['field']) ? sanitize_text_field($_REQUEST['field']) : '';
        if ( empty($slug) || empty($field) ) wp_send_json_error('Missing parameters');
        $data = $this->get_api_data($slug);
        if ( ! $data ) wp_send_json_success('');
        $value = $this->get_nested_value($data, $field);
        if ($value === null) $value = '';
        wp_send_json_success( is_scalar($value) ? (string) $value : wp_json_encode($value) );
    }

    public function get_endpoints(){
        $eps = get_option($this->option_name, []);
        if ( ! is_array($eps) ) $eps = [];
        return $eps;
    }

    public function render_endpoints_settings(){
        $eps = $this->get_endpoints();
        if ( ! is_array($eps) ) $eps = [];
        ob_start();
        ?>
        <h2><?php esc_html_e('Zusätzliche Endpunkte','dgptm-zoho'); ?></h2>
        <?php
        if ( isset($_GET['edit_endpoint']) && ! empty($_GET['edit_endpoint']) ){
            $edit_slug = dgptm_sanitize_route_slug($_GET['edit_endpoint']);
            $edit_ep = null;
            foreach($eps as $ep){
                if(isset($ep['slug']) && $ep['slug'] === $edit_slug){ $edit_ep = $ep; break; }
            }
            if($edit_ep):
                ?>
                <h3><?php esc_html_e('Endpunkt bearbeiten','dgptm-zoho'); ?></h3>
                <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="POST">
                    <?php wp_nonce_field('update_wf_endpoint_nonce'); ?>
                    <input type="hidden" name="action" value="update_wf_endpoint"/>
                    <input type="hidden" name="wf_endpoint_edit[original_slug]" value="<?php echo esc_attr($edit_ep['slug']); ?>"/>
                    <table class="form-table">
                        <tr>
                            <th><label for="edit_ep_name"><?php esc_html_e('Name','dgptm-zoho'); ?></label></th>
                            <td><input type="text" id="edit_ep_name" name="wf_endpoint_edit[name]" class="regular-text" value="<?php echo esc_attr($edit_ep['name'] ?? ''); ?>" required/></td>
                        </tr>
                        <tr>
                            <th><label for="edit_ep_slug"><?php esc_html_e('Slug','dgptm-zoho'); ?></label></th>
                            <td><input type="text" id="edit_ep_slug" name="wf_endpoint_edit[slug]" class="regular-text" value="<?php echo esc_attr($edit_ep['slug'] ?? ''); ?>" required/></td>
                        </tr>
                        <tr>
                            <th><label for="edit_ep_target_url"><?php esc_html_e('Ziel-URL','dgptm-zoho'); ?></label></th>
                            <td><input type="url" id="edit_ep_target_url" name="wf_endpoint_edit[target_url]" class="regular-text" value="<?php echo esc_attr($edit_ep['target_url'] ?? ''); ?>" required/></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Nur interne Aufrufe','dgptm-zoho'); ?></th>
                            <td>
                                <input type="checkbox" id="edit_ep_internal_only" name="wf_endpoint_edit[internal_only]" value="1" <?php checked(isset($edit_ep['internal_only']) && $edit_ep['internal_only']); ?>/>
                                <label for="edit_ep_internal_only"><?php esc_html_e('Externe HTTP-Aufrufe blockieren (403)', 'dgptm-zoho'); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit_ep_forward_method"><?php esc_html_e('Weiterleitungs-Methode','dgptm-zoho'); ?></label></th>
                            <td>
                                <select id="edit_ep_forward_method" name="wf_endpoint_edit[forward_method]">
                                    <option value="POST" <?php selected(($edit_ep['forward_method'] ?? 'GET'), 'POST'); ?>>POST</option>
                                    <option value="GET"  <?php selected(($edit_ep['forward_method'] ?? 'GET'), 'GET');  ?>>GET</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e('Zoho-Authentifizierung mitsenden','dgptm-zoho'); ?></th>
                            <td>
                                <input type="checkbox" id="edit_ep_send_zoho_auth" name="wf_endpoint_edit[send_zoho_auth]" value="1" <?php checked(isset($edit_ep['send_zoho_auth']) && $edit_ep['send_zoho_auth']); ?>/>
                                <label for="edit_ep_send_zoho_auth"><?php esc_html_e('Zoho OAuth2 Token im Request-Header mitsenden.','dgptm-zoho'); ?></label>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit_ep_wp_page"><?php esc_html_e('Verknüpfte WP-Seite','dgptm-zoho'); ?></label></th>
                            <td>
                                <select id="edit_ep_wp_page" name="wf_endpoint_edit[wp_page]">
                                    <option value="0"><?php esc_html_e('Keine Seite', 'dgptm-zoho'); ?></option>
                                    <?php
                                    $pages = get_pages();
                                    foreach($pages as $page) {
                                        $selected = (isset($edit_ep['wp_page']) && (int) $edit_ep['wp_page'] === $page->ID) ? 'selected' : '';
                                        echo '<option value="' . esc_attr($page->ID) . '" ' . $selected . '>' . esc_html($page->post_title) . ' (ID: ' . esc_html($page->ID) . ')</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description"><?php esc_html_e('Bei Aufruf des Endpunkts erfolgt ein 302-Redirect auf diese Seite. Hinweis: „Nur interne Aufrufe“ blockiert externe Redirects.', 'dgptm-zoho'); ?></p>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(__('Endpunkt aktualisieren','dgptm-zoho')); ?>
                </form>
                <hr/>
                <?php
            endif;
        }

        if ( ! empty($eps) ): ?>
            <h3><?php esc_html_e('Bestehende Endpunkte','dgptm-zoho'); ?></h3>
            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Name','dgptm-zoho'); ?></th>
                        <th><?php esc_html_e('Slug','dgptm-zoho'); ?></th>
                        <th><?php esc_html_e('Ziel-URL','dgptm-zoho'); ?></th>
                        <th><?php esc_html_e('Nur intern','dgptm-zoho'); ?></th>
                        <th><?php esc_html_e('Weiterleitungs-Methode','dgptm-zoho'); ?></th>
                        <th><?php esc_html_e('Zoho-Auth','dgptm-zoho'); ?></th>
                        <th><?php esc_html_e('Verknüpfte WP-Seite','dgptm-zoho'); ?></th>
                        <th><?php esc_html_e('Aktionen','dgptm-zoho'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($eps as $ep): ?>
                        <tr>
                            <td><?php echo esc_html($ep['name'] ?? ''); ?></td>
                            <td><?php echo esc_html($ep['slug'] ?? ''); ?></td>
                            <td><?php echo esc_url($ep['target_url'] ?? ''); ?></td>
                            <td><?php echo (!empty($ep['internal_only'])) ? __('Ja','dgptm-zoho') : __('Nein','dgptm-zoho'); ?></td>
                            <td><?php echo (isset($ep['forward_method']) ? esc_html($ep['forward_method']) : 'GET'); ?></td>
                            <td><?php echo (!empty($ep['send_zoho_auth'])) ? __('Ja','dgptm-zoho') : __('Nein','dgptm-zoho'); ?></td>
                            <td><?php echo (!empty($ep['wp_page'])) ? (int) $ep['wp_page'] : '-'; ?></td>
                            <td>
                                <?php
                                $edit_url = add_query_arg(['edit_endpoint' => $ep['slug']], admin_url('options-general.php?page=dgptm-zoho-api-settings'));
                                $del_url = wp_nonce_url(admin_url('admin-post.php?action=delete_wf_endpoint&slug=' . urlencode($ep['slug'])), 'delete_wf_endpoint_nonce');
                                ?>
                                <a href="<?php echo esc_url($edit_url); ?>"><?php esc_html_e('Editieren','dgptm-zoho'); ?></a> |
                                <a href="<?php echo esc_url($del_url); ?>" onclick="return confirm('<?php echo esc_js(__('Wollen Sie diesen Endpunkt wirklich löschen?','dgptm-zoho')); ?>');">
                                    <?php esc_html_e('Löschen','dgptm-zoho'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p><?php esc_html_e('Keine Endpunkte konfiguriert.','dgptm-zoho'); ?></p>
        <?php endif; ?>

        <h3><?php esc_html_e('Neuen Endpunkt hinzufügen','dgptm-zoho'); ?></h3>
        <form action="<?php echo esc_url(admin_url('admin-post.php')); ?>" method="POST">
            <?php wp_nonce_field('save_wf_endpoints_nonce'); ?>
            <input type="hidden" name="action" value="save_wf_endpoints"/>
            <table class="form-table">
                <tr>
                    <th><label for="ep_name"><?php esc_html_e('Name','dgptm-zoho'); ?></label></th>
                    <td><input type="text" id="ep_name" name="wf_endpoints_new[name]" class="regular-text" required/></td>
                </tr>
                <tr>
                    <th><label for="ep_slug"><?php esc_html_e('Slug','dgptm-zoho'); ?></label></th>
                    <td><input type="text" id="ep_slug" name="wf_endpoints_new[slug]" class="regular-text" required/></td>
                </tr>
                <tr>
                    <th><label for="ep_target_url"><?php esc_html_e('Ziel-URL','dgptm-zoho'); ?></label></th>
                    <td><input type="url" id="ep_target_url" name="wf_endpoints_new[target_url]" class="regular-text" required/></td>
                </tr>
                <tr>
                    <th><?php esc_html_e('Nur interne Aufrufe','dgptm-zoho'); ?></th>
                    <td>
                        <input type="checkbox" id="ep_internal_only" name="wf_endpoints_new[internal_only]" value="1"/>
                        <label for="ep_internal_only"><?php esc_html_e('Externe HTTP-Aufrufe blockieren (403).','dgptm-zoho'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><label for="ep_forward_method"><?php esc_html_e('Weiterleitungs-Methode','dgptm-zoho'); ?></label></th>
                    <td>
                        <select id="ep_forward_method" name="wf_endpoints_new[forward_method]">
                            <option value="GET">GET</option>
                            <option value="POST">POST</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="ep_send_zoho_auth"><?php esc_html_e('Zoho-Authentifizierung mitsenden','dgptm-zoho'); ?></label></th>
                    <td>
                        <input type="checkbox" id="ep_send_zoho_auth" name="wf_endpoints_new[send_zoho_auth]" value="1"/>
                        <label for="ep_send_zoho_auth"><?php esc_html_e('Zoho OAuth2 Token im Request-Header mitsenden.','dgptm-zoho'); ?></label>
                    </td>
                </tr>
                <tr>
                    <th><label for="ep_wp_page"><?php esc_html_e('Verknüpfte WP-Seite','dgptm-zoho'); ?></label></th>
                    <td>
                        <select id="ep_wp_page" name="wf_endpoints_new[wp_page]">
                            <option value="0"><?php esc_html_e('Keine Seite', 'dgptm-zoho'); ?></option>
                            <?php
                            $pages = get_pages();
                            foreach($pages as $page) {
                                echo '<option value="' . esc_attr($page->ID) . '">' . esc_html($page->post_title) . ' (ID: ' . esc_html($page->ID) . ')</option>';
                            }
                            ?>
                        </select>
                        <p class="description"><?php esc_html_e('Bei Verknüpfung: 302-Redirect auf diese Seite (kein Forward). „Nur interne Aufrufe“ blockiert externe Redirects.', 'dgptm-zoho'); ?></p>
                    </td>
                </tr>
            </table>
            <?php submit_button(__('Neuen Endpunkt hinzufügen','dgptm-zoho')); ?>
        </form>
        <?php
        return ob_get_clean();
    }
}

/* ========================================================
   Admin-Post-Aktionen
   ======================================================== */
function save_wf_endpoints(){
    if ( ! current_user_can('manage_options') ) wp_die(__('Fehlende Berechtigung.','dgptm-zoho'));
    check_admin_referer('save_wf_endpoints_nonce');

    if ( isset($_POST['wf_endpoints_new']) && is_array($_POST['wf_endpoints_new']) ) {
        $raw = wp_unslash($_POST['wf_endpoints_new']);

        $name = sanitize_text_field($raw['name'] ?? '');
        $slug = dgptm_sanitize_route_slug($raw['slug'] ?? '');
        $url  = esc_url_raw($raw['target_url'] ?? '');
        $internal_only = isset($raw['internal_only']);
        $method   = isset($raw['forward_method']) ? strtoupper($raw['forward_method']) : 'GET';
        $sendauth = isset($raw['send_zoho_auth']);
        $wp_page  = isset($raw['wp_page']) ? (int) $raw['wp_page'] : 0;

        if ( ! $slug ) wp_die(__('Ungültiger Slug.','dgptm-zoho'));
        if ( empty($url) || ! dgptm_is_allowed_url($url) ) wp_die(__('Ziel-URL ist leer oder nicht erlaubt.','dgptm-zoho'));

        $eps = get_option('wf_endpoints', []);
        if ( ! is_array($eps) ) $eps = [];

        foreach ( $eps as $ep ) {
            if ( isset($ep['slug']) && $ep['slug'] === $slug ) wp_die(__('Slug ist bereits vergeben.','dgptm-zoho'));
        }

        $new_ep = [
            'name'            => $name,
            'slug'            => $slug,
            'target_url'      => $url,
            'internal_only'   => (bool) $internal_only,
            'forward_method'  => in_array($method, ['GET','POST'], true) ? $method : 'GET',
            'send_zoho_auth'  => (bool) $sendauth,
            'wp_page'         => $wp_page,
        ];

        $eps[] = $new_ep;
        update_option('wf_endpoints', $eps);
        dgptm_log('Endpoint created', ['slug'=>$slug,'method'=>$new_ep['forward_method'],'internal_only'=>$new_ep['internal_only'],'wp_page'=>$wp_page]);
    }
    wp_redirect(admin_url('options-general.php?page=dgptm-zoho-api-settings&updated=true'));
    exit;
}
add_action('admin_post_save_wf_endpoints','save_wf_endpoints');

function delete_wf_endpoint(){
    if ( ! current_user_can('manage_options') ) wp_die(__('Fehlende Berechtigung.','dgptm-zoho'));
    check_admin_referer('delete_wf_endpoint_nonce');
    $slug = isset($_GET['slug']) ? dgptm_sanitize_route_slug($_GET['slug']) : '';
    if ( empty($slug) ) wp_die(__('Kein Endpunkt-Slug angegeben.','dgptm-zoho'));
    $eps = get_option('wf_endpoints', []);
    if ( ! is_array($eps) ) $eps = [];
    $new = [];
    foreach($eps as $ep){
        if ( isset($ep['slug']) && $ep['slug'] === $slug ) { continue; }
        $new[] = $ep;
    }
    update_option('wf_endpoints', $new);
    dgptm_log('Endpoint deleted', ['slug'=>$slug]);
    wp_redirect(admin_url('options-general.php?page=dgptm-zoho-api-settings&updated=true'));
    exit;
}
add_action('admin_post_delete_wf_endpoint','delete_wf_endpoint');

function update_wf_endpoint(){
    if ( ! current_user_can('manage_options') ) wp_die(__('Fehlende Berechtigung.','dgptm-zoho'));
    check_admin_referer('update_wf_endpoint_nonce');
    if ( isset($_POST['wf_endpoint_edit']) && is_array($_POST['wf_endpoint_edit']) ){
        $raw = wp_unslash($_POST['wf_endpoint_edit']);
        $original_slug = dgptm_sanitize_route_slug($raw['original_slug'] ?? '');
        if ( empty($original_slug) ) wp_die(__('Kein Original-Slug angegeben.','dgptm-zoho'));
        $eps = get_option('wf_endpoints', []);
        if ( ! is_array($eps) ) $eps = [];

        $new_slug = dgptm_sanitize_route_slug($raw['slug'] ?? '');
        $new_url  = esc_url_raw($raw['target_url'] ?? '');
        if ( ! $new_slug ) wp_die(__('Ungültiger Slug.','dgptm-zoho'));
        if ( empty($new_url) || ! dgptm_is_allowed_url($new_url) ) wp_die(__('Ziel-URL ist leer oder nicht erlaubt.','dgptm-zoho'));

        foreach ( $eps as $ep ) {
            if ( isset($ep['slug']) && $ep['slug'] === $new_slug && $ep['slug'] !== $original_slug ) {
                wp_die(__('Slug ist bereits vergeben.','dgptm-zoho'));
            }
        }

        foreach($eps as &$ep){
            if ( isset($ep['slug']) && $ep['slug'] === $original_slug ){
                $ep['name']           = sanitize_text_field($raw['name'] ?? '');
                $ep['slug']           = $new_slug;
                $ep['target_url']     = $new_url;
                $ep['internal_only']  = isset($raw['internal_only']);
                $method               = isset($raw['forward_method']) ? strtoupper($raw['forward_method']) : 'GET';
                $ep['forward_method'] = in_array($method, ['GET','POST'], true) ? $method : 'GET';
                $ep['send_zoho_auth'] = isset($raw['send_zoho_auth']);
                $ep['wp_page']        = isset($raw['wp_page']) ? (int) $raw['wp_page'] : 0;
                dgptm_log('Endpoint updated', ['old_slug'=>$original_slug,'new_slug'=>$new_slug,'method'=>$ep['forward_method'],'internal_only'=>$ep['internal_only'],'wp_page'=>$ep['wp_page']]);
                break;
            }
        }
        update_option('wf_endpoints', $eps);
    }
    wp_redirect(admin_url('options-general.php?page=dgptm-zoho-api-settings&updated=true'));
    exit;
}
add_action('admin_post_update_wf_endpoint','update_wf_endpoint');

/**
 * Test-Endpunkt (AJAX) – unverändert: Testet die Ziel-URL mit Zoho-Auth.
 */
function test_zoho_endpoint_ajax_handler(){
    check_ajax_referer('test_zoho_endpoint_nonce','nonce');
    if ( ! current_user_can('manage_options') ) wp_send_json_error(__('Keine Berechtigung.','dgptm-zoho'));
    $slug = isset($_POST['slug']) ? dgptm_sanitize_route_slug($_POST['slug']) : '';
    if ( empty($slug) ) wp_send_json_error(__('Kein Endpunkt-Slug angegeben.','dgptm-zoho'));
    $eps = get_option('wf_endpoints', []);
    $sel = null;
    foreach($eps as $ep){
        if ( isset($ep['slug']) && $ep['slug'] === $slug ){ $sel = $ep; break; }
    }
    if ( ! $sel ) wp_send_json_error(__('Endpunkt nicht gefunden.','dgptm-zoho'));
    $token = DGPTM_Zoho_Plugin::get_instance()->get_oauth_token();
    if ( is_wp_error($token) || empty($token) ) wp_send_json_error(__('Kein gültiger OAuth-Token verfügbar.','dgptm-zoho'));
    $res = dgptm_safe_remote('POST', $sel['target_url'], [
        'headers' => [
            'Content-Type'  => 'application/json',
            'Authorization' => 'Zoho-oauthtoken ' . $token,
            'Accept'        => 'application/json'
        ],
        'body'    => wp_json_encode(['test' => true]),
        'timeout' => 10,
    ]);
    if ( is_wp_error($res) ) wp_send_json_error(__('Fehler beim Testen: ', 'dgptm-zoho') . $res->get_error_message());
    $code = wp_remote_retrieve_response_code($res);
    $body = wp_remote_retrieve_body($res);
    wp_send_json_success(['response_code' => $code, 'response_body' => $body]);
}
add_action('wp_ajax_test_zoho_endpoint','test_zoho_endpoint_ajax_handler');

// Plugin aktivieren
DGPTM_Zoho_Plugin::get_instance();
global $dgptm_additional_endpoints;
$dgptm_additional_endpoints = new Additional_Zoho_Endpoints();