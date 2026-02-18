<?php
/**
 * Plugin Name: DGPTM - MS365 Group Manager (Anzeigename + Mail + Position)
 * Description: Microsoft 365-Gruppenverwaltung in WordPress.
 *              Zeigt im Shortcode pro Gruppenmitglied neben dem Namen auch die Mailadresse und die Position (jobTitle) an.
 *              - Anzeigename ändern und Benutzer löschen nur für Admins (manage_options)
 *              - Position ändern NUR für Admins (manage_options)
 *              - Nur echte Microsoft 365-Gruppen („Unified“) werden angezeigt; optional auch Security-Gruppen.
 *              - Gruppen, deren Namen mit „ADMIN“ beginnen, sind ausgeblendet.
 *              - Einstellungsseite mit sichtbarer Microsoft-Kommunikation (Diagnose) und manuellem Gruppenabruf.
 * Version: 1.5.4
 * Author: Sebastian Melzer
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WP_MS365_Group_Manager {

    private static $instance = null;

    private $option_name     = 'wp_ms365_plugin_options';
    private $tenant_id       = '45f3b491-1cda-4bef-a406-e6937875236b';
    private $auth_endpoint   = '';
    private $token_endpoint  = '';
    private $graph_endpoint  = 'https://graph.microsoft.com/v1.0/';

    // Diagnose (letzte Graph-Fehler/Trace für Anzeige im Admin)
    private $last_graph_error = '';
    private $last_graph_trace = array();

    /**
     * Singleton
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->auth_endpoint  = 'https://login.microsoftonline.com/' . $this->tenant_id . '/oauth2/v2.0/authorize';
        $this->token_endpoint = 'https://login.microsoftonline.com/' . $this->tenant_id . '/oauth2/v2.0/token';

        // Admin-Menü
        add_action( 'admin_menu', array( $this, 'add_plugin_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );

        // Profilfelder (Erlaubte Gruppen je User) – vorher fehlend
        add_action( 'show_user_profile', array( $this, 'user_profile_fields' ) );
        add_action( 'edit_user_profile', array( $this, 'user_profile_fields' ) );
        add_action( 'personal_options_update', array( $this, 'save_user_profile_fields' ) );
        add_action( 'edit_user_profile_update', array( $this, 'save_user_profile_fields' ) );

        // AJAX (Add/Remove/GetMembers + rename/delete + set_position)
        add_action( 'wp_ajax_wp_ms365_add_member', array( $this, 'ajax_add_member' ) );
        add_action( 'wp_ajax_wp_ms365_remove_member', array( $this, 'ajax_remove_member' ) ); // Implementiert
        add_action( 'wp_ajax_wp_ms365_get_members', array( $this, 'ajax_get_members' ) );
        add_action( 'wp_ajax_wp_ms365_rename_user', array( $this, 'ajax_rename_user' ) );
        add_action( 'wp_ajax_wp_ms365_delete_user', array( $this, 'ajax_delete_user' ) );

        // Position ändern (nun NUR Admin)
        add_action( 'wp_ajax_wp_ms365_set_position', array( $this, 'ajax_set_position' ) );

        // Admin-Diagnose: manueller Gruppenabruf
        add_action( 'wp_ajax_wp_ms365_admin_fetch_groups', array( $this, 'ajax_admin_fetch_groups' ) );

        // Shortcodes
        add_shortcode( 'ms365_group_manager', array( $this, 'render_group_manager_shortcode' ) );
        add_shortcode( 'ms365_has_any_group', array( $this, 'render_has_any_group_shortcode' ) );

        // Invalidate group cache when plugin settings change
        add_action( 'update_option_' . $this->option_name, array( $this, 'invalidate_group_cache' ) );
    }

    /**
     * Invalidate tenant groups cache
     */
    public function invalidate_group_cache() {
        delete_transient('wp_ms365_tenant_groups');
    }

    /**
     * Debug-Logger (sensibles schwärzen)
     */
    private function log_debug_info($title, $data) {
        if ( ! (defined('WP_DEBUG') && WP_DEBUG) ) return;

        $redact_keys = array('client_secret','access_token','refresh_token','code','authorization','Authorization');
        $sanitize = function($value) use (&$sanitize, $redact_keys) {
            if (is_array($value)) {
                foreach ($value as $k => $v) {
                    if (in_array($k, $redact_keys, true)) {
                        $value[$k] = '[REDACTED]';
                    } else {
                        $value[$k] = $sanitize($v);
                    }
                }
                return $value;
            }
            if (is_object($value)) {
                foreach ($value as $k => $v) {
                    if (in_array($k, $redact_keys, true)) {
                        $value->$k = '[REDACTED]';
                    } else {
                        $value->$k = $sanitize($v);
                    }
                }
                return $value;
            }
            if (is_string($value) && stripos($value, 'Bearer ') === 0) {
                return 'Bearer [REDACTED]';
            }
            return $value;
        };

        $safe = $sanitize($data);
        if (is_array($safe) || is_object($safe)) { $safe = print_r($safe, true); }
        error_log("[MS365 Debug] $title: $safe");
    }

    /**
     * Diagnose: letzte Graph-Kommunikation merken (für Admin-Seite)
     */
    private function record_graph_trace($context, $method, $url, $headers = array(), $body_out = null, $wp_response = null) {
        // sensible Header/Body schwärzen
        $safeHeaders = array();
        foreach ((array)$headers as $k=>$v) {
            if (is_string($k) && stripos($k,'authorization') !== false) {
                $safeHeaders[$k] = '[REDACTED]';
            } else {
                $safeHeaders[$k] = $v;
            }
        }
        $resp = null;
        if ($wp_response instanceof WP_Error) {
            $resp = array(
                'wp_error' => $wp_response->get_error_message()
            );
        } elseif (is_array($wp_response)) {
            $resp = array(
                'status' => wp_remote_retrieve_response_code($wp_response),
                'body'   => substr(wp_remote_retrieve_body($wp_response), 0, 2000)
            );
        }

        $this->last_graph_trace = array(
            'context' => $context,
            'request' => array(
                'method' => $method,
                'url'    => $url,
                'headers'=> $safeHeaders,
                'body'   => is_string($body_out) ? substr($body_out,0,2000) : ( $body_out ? json_encode($body_out) : null )
            ),
            'response'=> $resp,
            'time'    => current_time('mysql')
        );
        update_option('wp_ms365_last_graph_trace', $this->last_graph_trace, false);
    }
    private function record_graph_error($msg) {
        $this->last_graph_error = $msg;
        update_option('wp_ms365_last_graph_error', $msg, false);
    }

    /**
     * Stabile, plugin-eigene Schlüsselableitung (persistenter Key, autoload=no)
     * Format der gespeicherten Option: base64(key32) . '|' . base64(hmac32)
     */
    private function get_enc_key() {
        $key = get_option('wp_ms365_enc_key');
        if (empty($key) || !is_string($key) || strlen($key) < 10) {
            $rawKey  = random_bytes(32);
            $rawHmac = random_bytes(32);
            $key = base64_encode($rawKey) . '|' . base64_encode($rawHmac);
            add_option('wp_ms365_enc_key', $key, '', 'no'); // autoload=no
        }
        return $key;
    }

    /**
     * AES-256-GCM Verschlüsselung mit Integritätsprüfung.
     * Format (base64): iv(12) || ct || tag(16) || hmac(32)
     */
    private function enc($plain){
        if ($plain === '' || $plain === null) return $plain;
        $parts = explode('|', $this->get_enc_key(), 2);
        $key = base64_decode($parts[0], true);
        $hmacKey = base64_decode($parts[1], true);

        $iv = random_bytes(12);
        $tag = '';
        $ct = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ct === false) return '';

        $blob = $iv . $ct . $tag;
        $mac  = hash_hmac('sha256', $blob, $hmacKey, true);
        return base64_encode($blob . $mac);
    }
    private function dec($blob){
        if ($blob === '' || $blob === null) return $blob;
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) < (12+16+32)) return '';

        $parts = explode('|', $this->get_enc_key(), 2);
        $key = base64_decode($parts[0], true);
        $hmacKey = base64_decode($parts[1], true);

        $macStored = substr($raw, -32);
        $payload   = substr($raw, 0, -32);
        $macCalc   = hash_hmac('sha256', $payload, $hmacKey, true);
        if (!hash_equals($macStored, $macCalc)) {
            return '';
        }

        $iv  = substr($payload, 0, 12);
        $tag = substr($payload, -16);
        $ct  = substr($payload, 12, -16);
        $pt  = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $pt === false ? '' : $pt;
    }

    /**
     * Fallback-Entschlüsselung für ALT gespeicherte Tokens (AES-256-CBC mit wp_salt('auth')).
     * Erwartetes Format: base64(iv(16) || ct)
     */
    private function legacy_dec_cbc($blob){
        if ($blob === '' || $blob === null) return '';
        $raw = base64_decode($blob, true);
        if ($raw === false || strlen($raw) < 17) return '';
        $key = hash('sha256', wp_salt('auth'), true);
        $iv  = substr($raw, 0, 16);
        $ct  = substr($raw, 16);
        $pt  = openssl_decrypt($ct, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
        return $pt === false ? '' : $pt;
    }

    /**
     * Admin-Menü
     */
    public function add_plugin_page() {
        add_options_page(
            'MS365 Plugin Einstellungen',
            'MS365 Plugin',
            'manage_options',
            'wp_ms365_plugin',
            array( $this, 'create_admin_page' )
        );
    }

    /**
     * Admin-Einstellungsseite (inkl. Diagnose & manueller Gruppenabruf)
     */
    public function create_admin_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }

        $error_description = '';
        if ( isset( $_GET['ms365_oauth'] ) ) {
            if ( isset($_GET['error_description']) ) {
                $error_description = urldecode( $_GET['error_description'] );
            } else {
                $this->handle_oauth_callback();
            }
        }

        $options = get_option( $this->option_name );
        $last_error = get_option('wp_ms365_last_graph_error', '');
        $last_trace = get_option('wp_ms365_last_graph_trace', array());
        $nonce_diag = wp_create_nonce('wp_ms365_admin_diag');

        $token_info = '';
        if (!empty($options['token_expires'])) {
            $token_info = 'läuft ab am: ' . esc_html( date_i18n( 'd.m.Y H:i:s', intval($options['token_expires']) ) );
        }

        $include_security = !empty($options['include_security_groups']) ? (bool)$options['include_security_groups'] : false;

        ?>
        <div class="wrap">
            <h1>MS365 Plugin Einstellungen</h1>
            <?php if ( $error_description ) : ?>
                <div style="border:1px solid #cc0000;padding:10px;background:#ffe5e5;margin-bottom:20px;">
                    <strong>Fehler bei der Anmeldung:</strong> <?php echo esc_html($error_description); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="options.php" autocomplete="off">
                <?php
                settings_fields( 'wp_ms365_plugin_options' );
                do_settings_sections( 'wp_ms365_plugin' );
                submit_button();
                ?>
            </form>

            <hr>
            <h2>OAuth-Verbindung</h2>
            <p><strong>Tenant:</strong> <?php echo esc_html($this->tenant_id); ?></p>
            <p><strong>Status:</strong>
                <?php echo ! empty( $options['access_token'] ) ? 'Verbunden' : 'Nicht verbunden'; ?>
                <?php if ($token_info) echo ' &mdash; <em>' . $token_info . '</em>'; ?>
            </p>
            <?php if ( empty( $options['access_token'] ) ) : ?>
                <a class="button button-primary" href="<?php echo esc_url( $this->get_authorization_url() ); ?>">Mit Microsoft verbinden</a>
            <?php else : ?>
                <a class="button" href="<?php echo esc_url( $this->get_authorization_url() ); ?>">Neu verbinden (re-auth)</a>
            <?php endif; ?>

            <hr>
            <h2>Diagnose: Kommunikation mit Microsoft</h2>
            <p>Hier siehst du die letzte Anfrage/Antwort (gekürzt) an die Microsoft Graph API.</p>
            <p><em>Gruppenfilter:</em> Unified (=Microsoft 365) immer; Security-Gruppen einbeziehen:
                <strong><?php echo $include_security ? 'Ja' : 'Nein'; ?></strong>
            </p>
            <p><button id="ms365-admin-fetch-groups" class="button button-secondary">Manuell: Gruppen abrufen (Test)</button></p>
            <pre id="ms365-admin-diagnose" style="background:#111;color:#eee;padding:12px;white-space:pre-wrap;max-height:420px;overflow:auto;">
<?php
echo esc_html( $last_error ? ("Letzter Fehler: ".$last_error."\n") : "Keine Fehler protokolliert.\n" );
if (!empty($last_trace)) {
    echo esc_html( "Kontext: " . ($last_trace['context'] ?? '') . " @ " . ($last_trace['time'] ?? '') . "\n" );
    echo esc_html( "Request: " . ($last_trace['request']['method'] ?? '') . " " . ($last_trace['request']['url'] ?? '') . "\n" );
    echo esc_html( "Headers: " . json_encode($last_trace['request']['headers'] ?? array()) . "\n" );
    if (!empty($last_trace['request']['body'])) echo esc_html( "Body: " . substr($last_trace['request']['body'],0,1000) . "\n" );
    echo esc_html( "Response: " . json_encode($last_trace['response'] ?? array()) . "\n" );
}
?>
            </pre>

            <script>
            (function($){
                $('#ms365-admin-fetch-groups').on('click', function(e){
                    e.preventDefault();
                    var out = $('#ms365-admin-diagnose');
                    out.text('Starte Testabruf...');
                    $.post(ajaxurl, {
                        action: 'wp_ms365_admin_fetch_groups',
                        _ajax_nonce: '<?php echo esc_js($nonce_diag); ?>'
                    }, function(resp){
                        if (resp && resp.success) {
                            out.text(resp.data.output || 'OK');
                        } else {
                            out.text('Fehler: ' + (resp && resp.data ? resp.data : 'Unbekannt'));
                        }
                    });
                });
            })(jQuery);
            </script>
        </div>
        <?php
    }

    /**
     * Einstellungen
     */
    public function register_settings() {
        register_setting(
            'wp_ms365_plugin_options',
            $this->option_name,
            array( 'sanitize_callback' => array($this, 'sanitize_options') )
        );

        add_settings_section(
            'wp_ms365_section',
            'Basis-Einstellungen',
            null,
            'wp_ms365_plugin'
        );

        add_settings_field(
            'client_id',
            'Client ID',
            array( $this, 'field_client_id_callback' ),
            'wp_ms365_plugin',
            'wp_ms365_section'
        );

        add_settings_field(
            'client_secret',
            'Client Secret',
            array( $this, 'field_client_secret_callback' ),
            'wp_ms365_plugin',
            'wp_ms365_section'
        );

        add_settings_field(
            'redirect_uri',
            'Redirect URI',
            array( $this, 'field_redirect_uri_callback' ),
            'wp_ms365_plugin',
            'wp_ms365_section'
        );

        add_settings_field(
            'include_security_groups',
            'Security-Gruppen einbeziehen',
            array( $this, 'field_include_security_groups_callback' ),
            'wp_ms365_plugin',
            'wp_ms365_section'
        );
    }

    public function sanitize_options($input) {
        $opts = get_option($this->option_name, array());
        $out  = is_array($opts) ? $opts : array();

        $out['client_id']    = isset($input['client_id']) ? sanitize_text_field($input['client_id']) : ($out['client_id'] ?? '');
        $out['redirect_uri'] = isset($input['redirect_uri']) ? esc_url_raw($input['redirect_uri']) : ($out['redirect_uri'] ?? '');

        // Client Secret nur aktualisieren, wenn etwas eingegeben wurde
        if ( isset($input['client_secret']) && $input['client_secret'] !== '' ) {
            $out['client_secret'] = sanitize_text_field($input['client_secret']);
        }

        $out['include_security_groups'] = !empty($input['include_security_groups']) ? 1 : 0;

        return $out;
    }

    public function field_client_id_callback() {
        $options = get_option( $this->option_name );
        $value   = $options['client_id'] ?? '';
        echo '<input type="text" name="' . esc_attr($this->option_name) . '[client_id]" value="' . esc_attr($value) . '" style="width:400px;">';
    }

    public function field_client_secret_callback() {
        // Geheimnis nicht vorausfüllen; leer lassen = unverändert
        echo '<input type="password" name="' . esc_attr($this->option_name) . '[client_secret]" value="" autocomplete="new-password" style="width:400px;" placeholder="Nur ausfüllen, wenn neu setzen">';
        echo '<p class="description">Aus Sicherheitsgründen wird der aktuelle Wert nicht angezeigt.</p>';
    }

    public function field_redirect_uri_callback() {
        $options = get_option( $this->option_name );
        $value   = $options['redirect_uri'] ?? '';
        echo '<input type="text" name="' . esc_attr($this->option_name) . '[redirect_uri]" value="' . esc_attr($value) . '" style="width:400px;">';
        echo '<p class="description">Diese URL muss exakt in Azure AD eingetragen sein.</p>';
    }

    public function field_include_security_groups_callback() {
        $options = get_option( $this->option_name );
        $checked = !empty($options['include_security_groups']) ? 'checked' : '';
        echo '<label><input type="checkbox" name="' . esc_attr($this->option_name) . '[include_security_groups]" value="1" ' . $checked . '> Auch Security-Gruppen anzeigen</label>';
        echo '<p class="description">Wenn aktiv, werden zusätzlich zu Microsoft 365 (Unified) auch Security-Gruppen berücksichtigt.</p>';
    }

    /**
     * OAuth URL (mit state + PKCE)
     */
    public function get_authorization_url() {
        $options      = get_option( $this->option_name );
        $client_id    = $options['client_id']     ?? '';
        $redirect_uri = $options['redirect_uri']  ?? '';

        $state = wp_generate_uuid4();
        set_transient('ms365_oauth_state_' . get_current_user_id(), $state, 10 * MINUTE_IN_SECONDS);

        $code_verifier  = bin2hex(random_bytes(32));
        $code_challenge = rtrim(strtr(base64_encode(hash('sha256', $code_verifier, true)), '+/', '-_'), '=');
        set_transient('ms365_pkce_' . get_current_user_id(), $code_verifier, 10 * MINUTE_IN_SECONDS);

        // Scopes beibehalten, damit bestehende Funktionen nicht brechen
        $params = array(
            'client_id'      => $client_id,
            'response_type'  => 'code',
            'redirect_uri'   => $redirect_uri,
            'response_mode'  => 'query',
            'scope'          => 'openid profile offline_access User.Read Group.ReadWrite.All User.Invite.All Directory.ReadWrite.All',
            'state'          => $state,
            'code_challenge' => $code_challenge,
            'code_challenge_method' => 'S256',
        );
        return $this->auth_endpoint . '?' . http_build_query( $params );
    }

    /**
     * OAuth Callback (mit state + PKCE)
     */
    private function handle_oauth_callback() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Keine Berechtigung.' );
        }

        if ( isset( $_GET['code'] ) ) {
            $given_state = isset($_GET['state']) ? sanitize_text_field($_GET['state']) : '';
            $expected    = get_transient('ms365_oauth_state_' . get_current_user_id());
            delete_transient('ms365_oauth_state_' . get_current_user_id());
            if ( ! $given_state || ! hash_equals((string)$expected, (string)$given_state) ) {
                wp_die('Ungültiger OAuth-Status (state).');
            }

            $options       = get_option( $this->option_name );
            $client_id     = $options['client_id']     ?? '';
            $client_secret = $options['client_secret'] ?? '';
            $redirect_uri  = $options['redirect_uri']  ?? '';
            $code          = sanitize_text_field( $_GET['code'] );
            $code_verifier = get_transient('ms365_pkce_' . get_current_user_id());
            delete_transient('ms365_pkce_' . get_current_user_id());

            $args = array(
                'body' => array(
                    'client_id'     => $client_id,
                    'scope'         => 'openid profile offline_access User.Read Group.ReadWrite.All User.Invite.All Directory.ReadWrite.All',
                    'code'          => $code,
                    'redirect_uri'  => $redirect_uri,
                    'grant_type'    => 'authorization_code',
                    'client_secret' => $client_secret,
                ),
                'headers' => array(
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ),
                'timeout' => 30,
            );
            if ( $code_verifier ) {
                $args['body']['code_verifier'] = $code_verifier;
            }

            $response = wp_remote_post( $this->token_endpoint, $args );
            if ( is_wp_error( $response ) ) {
                $this->log_debug_info('OAuth WP_Error', $response->get_error_message());
                $this->record_graph_error('OAuth Fehler: ' . $response->get_error_message());
                return;
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( isset($body['access_token']) ) {
                $expires_in = intval($body['expires_in'] ?? 0);
                $options['access_token']  = $this->enc($body['access_token']);
                $options['refresh_token'] = isset($body['refresh_token']) ? $this->enc($body['refresh_token']) : ($options['refresh_token'] ?? '');
                $options['token_expires'] = time() + $expires_in;
                update_option( $this->option_name, $options );
                $this->record_graph_error('OAuth erfolgreich.');
            } else {
                $this->log_debug_info('OAuth Response Body Fehler', $body);
                $this->record_graph_error('OAuth Antwort ohne Token: ' . substr(json_encode($body),0,500));
            }

            wp_safe_redirect( admin_url('options-general.php?page=wp_ms365_plugin') );
            exit;
        }
    }

    /**
     * Access Token holen (inkl. Refresh & Entschlüsselung + Auto-Migration alter Tokens)
     */
    private function get_access_token() {
        $options = get_option( $this->option_name );
        if ( empty($options['access_token']) ) {
            $this->record_graph_error('Kein Access Token vorhanden. Bitte verbinden.');
            return false;
        }

        $expiry = intval($options['token_expires'] ?? 0);
        $now    = time() + 30;

        // 1) Token entschlüsseln (neues GCM-Format)
        $rawStored = $options['access_token'];
        $token     = $this->dec($rawStored);

        // 2) Migration: altes Plain-JWT?
        if (empty($token) && is_string($rawStored)) {
            if (substr_count($rawStored, '.') >= 2 && strlen($rawStored) > 100) {
                $token = $rawStored; // Legacy-Plain verwenden
                $options['access_token'] = $this->enc($token);
                update_option($this->option_name, $options);
                $this->log_debug_info('get_access_token', 'Legacy Plain Access-Token erkannt und migriert.');
            }
        }

        // 3) Migration: ALT-CBC-Verschlüsselung?
        if (empty($token) && is_string($rawStored)) {
            $legacy = $this->legacy_dec_cbc($rawStored);
            if (!empty($legacy)) {
                $token = $legacy;
                $options['access_token'] = $this->enc($token);
                update_option($this->option_name, $options);
                $this->log_debug_info('get_access_token', 'Legacy CBC Access-Token erkannt und migriert.');
            }
        }

        if (empty($token)) {
            $this->record_graph_error('Token leer/nicht entschlüsselbar. Bitte neu verbinden.');
            return false;
        }

        // 4) Falls abgelaufen → Refresh versuchen
        if ( $now >= $expiry ) {
            if ( ! $this->refresh_access_token() ) {
                $this->record_graph_error('Token-Refresh fehlgeschlagen.');
                return false;
            }
            $options = get_option( $this->option_name );
            $token   = $this->dec($options['access_token'] ?? '');
            if (empty($token)) {
                $this->log_debug_info('get_access_token decrypt after refresh failed', $options['access_token'] ?? '(missing)');
                $this->record_graph_error('Token nach Refresh leer. Bitte neu verbinden.');
                return false;
            }
        }

        return $token;
    }

    /**
     * Refresh Token-Flow (mit Entschlüsselung + Auto-Migration alter Refresh-Tokens)
     */
    private function refresh_access_token() {
        $options = get_option( $this->option_name );
        if ( empty($options['refresh_token']) ) {
            $this->record_graph_error('Kein Refresh-Token verfügbar.');
            return false;
        }

        // Entschlüsseln (neu, GCM)
        $storedRefresh = $options['refresh_token'];
        $refreshToken  = $this->dec($storedRefresh);

        // Migration: ALT-CBC?
        if (empty($refreshToken) && is_string($storedRefresh)) {
            $legacy = $this->legacy_dec_cbc($storedRefresh);
            if (!empty($legacy)) {
                $refreshToken = $legacy;
                $options['refresh_token'] = $this->enc($refreshToken);
                update_option($this->option_name, $options);
                $this->log_debug_info('refresh_access_token', 'Legacy CBC Refresh-Token erkannt und migriert.');
            }
        }

        // Migration: altes Plain (Fallback)
        if (empty($refreshToken) && is_string($storedRefresh)) {
            if (strlen($storedRefresh) > 50) {
                $refreshToken = $storedRefresh;
                $options['refresh_token'] = $this->enc($refreshToken);
                update_option($this->option_name, $options);
                $this->log_debug_info('refresh_access_token', 'Legacy Plain Refresh-Token erkannt und migriert.');
            }
        }

        if (empty($refreshToken)) {
            $this->record_graph_error('Refresh-Token leer/nicht entschlüsselbar.');
            return false;
        }

        $args = array(
            'body' => array(
                'client_id'     => $options['client_id'] ?? '',
                'grant_type'    => 'refresh_token',
                'refresh_token' => $refreshToken,
                'client_secret' => $options['client_secret'] ?? '',
            ),
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded'
            ),
            'timeout' => 30,
        );

        $response = wp_remote_post( $this->token_endpoint, $args );
        if ( is_wp_error($response) ) {
            $this->log_debug_info('refresh_access_token WP_Error', $response->get_error_message());
            $this->record_graph_error('Refresh-Fehler: ' . $response->get_error_message());
            return false;
        }

        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( isset($body['access_token']) ) {
            $expires_in = intval($body['expires_in'] ?? 0);
            $options['access_token']  = $this->enc($body['access_token']);
            if ( !empty($body['refresh_token']) ) {
                $options['refresh_token'] = $this->enc($body['refresh_token']);
            }
            $options['token_expires'] = time() + $expires_in;
            update_option( $this->option_name, $options );
            // Clear cached groups since we have a new token
            delete_transient('wp_ms365_tenant_groups');
            $this->record_graph_error('Refresh erfolgreich.');
            return true;
        }

        $this->log_debug_info('refresh_access_token Fehler', $body);
        $this->record_graph_error('Refresh-Antwort ungültig: ' . substr(json_encode($body),0,500));
        return false;
    }

    /**
     * Helfer: GUID-Check & Gruppenberechtigung
     */
    private function is_guid($id){
        // Fix: das '-' gehörte NICHT in die ersten/folgenden Blöcke
        return is_string($id) && preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/', $id);
    }

    private function current_user_can_manage_group($group_id){
        if ( ! is_user_logged_in() || ! $this->is_guid($group_id) ) return false;
        $allowed = get_user_meta(get_current_user_id(), 'ms365_allowed_groups', true);
        if ( ! is_array($allowed) ) $allowed = array();
        return in_array($group_id, $allowed, true);
    }

    /**
     * Holt alle Gruppen, filtert M365 (Unified) und optional Security; kein DisplayName mit "ADMIN"
     * Mit Pagination, $top und ausführlicher Diagnose.
     */
    private function get_tenant_groups() {
        // Check transient cache first
        $cached = get_transient('wp_ms365_tenant_groups');
        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $token = $this->get_access_token();
        if ( ! $token ) {
            return array();
        }

        $options = get_option( $this->option_name );
        $allowSecurityGroups = !empty($options['include_security_groups']);

        $params = array(
            '$select' => 'id,displayName,mailEnabled,securityEnabled,groupTypes',
            '$top'    => 999
        );
        $url = add_query_arg($params, $this->graph_endpoint . 'groups');

        $args_base = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token
            ),
            'timeout' => 30,
        );

        $groups = array();
        $loop   = 0;

        while ($url && $loop < 10) {
            $loop++;
            $args = $args_base;
            $this->record_graph_trace('get_tenant_groups', 'GET', $url, $args['headers'], null, null);
            $res = wp_remote_get($url, $args);
            $this->record_graph_trace('get_tenant_groups', 'GET', $url, $args['headers'], null, $res);

            if ( is_wp_error($res) ) {
                $this->record_graph_error('Graph-Fehler (Groups): ' . $res->get_error_message());
                break;
            }
            $code = wp_remote_retrieve_response_code($res);
            $body = json_decode( wp_remote_retrieve_body($res), true );

            if ($code < 200 || $code > 299) {
                $this->record_graph_error('HTTP '.$code.' beim Abruf der Gruppen: '.substr(json_encode($body),0,300));
                break;
            }

            $batch = $body['value'] ?? array();
            foreach ($batch as $g) {
                $dn        = $g['displayName'] ?? '';
                $types     = isset($g['groupTypes']) ? (array)$g['groupTypes'] : array();
                $isUnified = in_array('Unified', $types, true);

                if (!$isUnified && !$allowSecurityGroups) {
                    continue;
                }

                if (stripos($dn, 'ADMIN') === 0) continue;

                $groups[] = $g;
            }
            $url = isset($body['@odata.nextLink']) ? $body['@odata.nextLink'] : null;
        }

        if (empty($groups)) {
            $this->record_graph_error('Keine passenden Gruppen gefunden (prüfe: verwendet ihr Security-Gruppen? Scopes? Token?).');
            // Cache empty result for 5 minutes to avoid rapid retries on a failing API
            set_transient('wp_ms365_tenant_groups', array(), 5 * MINUTE_IN_SECONDS);
        } else {
            $this->record_graph_error('Gruppen erfolgreich geladen: '.count($groups));
            // Cache successful result for 1 hour
            set_transient('wp_ms365_tenant_groups', array_values($groups), HOUR_IN_SECONDS);
        }

        return array_values($groups);
    }

    /**
     * Nur Gruppen aus WP-Userprofil
     */
    private function get_user_allowed_groups() {
        $meta = get_user_meta( get_current_user_id(), 'ms365_allowed_groups', true );
        if ( ! is_array($meta) || empty($meta) ) {
            return array();
        }

        $all = $this->get_tenant_groups();
        $filtered = array_filter( $all, function($g) use($meta){
            return in_array($g['id'], $meta, true);
        });

        // If API returned groups, use them
        if (!empty($filtered)) {
            return array_values($filtered);
        }

        // Fallback: build minimal group objects from stored names
        $names = get_user_meta( get_current_user_id(), 'ms365_allowed_groups_names', true );
        if ( ! is_array($names) || empty($names) ) {
            return array();
        }

        $fallback = array();
        foreach ($meta as $gid) {
            if (isset($names[$gid])) {
                $fallback[] = array(
                    'id' => $gid,
                    'displayName' => $names[$gid],
                );
            }
        }
        return $fallback;
    }

    /**
     * Shortcode: 1 oder 0
     */
    public function render_has_any_group_shortcode() {
        if ( ! is_user_logged_in() ) {
            return '0';
        }
        $groups = $this->get_user_allowed_groups();
        return empty($groups) ? '0' : '1';
    }

    /**
     * Shortcode: M365 Group Manager
     * Zeigt Name (Mail) + Position (jobTitle) + Buttons
     */
    public function render_group_manager_shortcode( $atts ) {
        if ( ! is_user_logged_in() ) {
            return '<p>Bitte melde Dich an, um die Gruppen zu verwalten.</p>';
        }

        // Fix: jQuery im Frontend sicherstellen, sonst laufen die Events nicht.
        wp_enqueue_script('jquery');

        $groups = $this->get_user_allowed_groups();
        $count  = count($groups);
        $ms365_nonce = wp_create_nonce('wp_ms365_ajax');

        ob_start();
        ?>
        <div id="ms365-group-manager">
            <style>
                /* Strikt gekapseltes Styling NUR für dieses Widget */
                #ms365-group-manager { font-size: 14px; line-height: 1.4; }
                #ms365-group-manager select,
                #ms365-group-manager input[type="text"] {
                    max-width: 100%;
                    padding: 6px 8px;
                    font-size: 14px;
                    line-height: 1.3;
                    margin: 4px 0;
                }
                #ms365-group-manager button {
                    font-size: 12px;
                    line-height: 1.2;
                    padding: 4px 8px;
                    margin-left: 4px;
                    cursor: pointer;
                }
                #ms365-group-manager ul { list-style: none; padding-left: 0; }
                #ms365-group-manager li { margin: 6px 0; }
                #ms365-group-manager #ms365-add-member { margin-top: 10px; }
            </style>

        <?php if ( $count > 1 ) : ?>
            <select id="ms365-group-select" aria-label="Gruppe wählen">
                <option value="">Bitte Gruppe wählen</option>
                <?php foreach ($groups as $group) : ?>
                    <option value="<?php echo esc_attr($group['id']); ?>">
                        <?php echo esc_html($group['displayName']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        <?php elseif ( $count === 1 ) : ?>
            <?php
            $single = reset($groups);
            echo '<strong>' . esc_html($single['displayName']) . '</strong>';
            echo '<input type="hidden" id="ms365-single-group-id" value="' . esc_attr($single['id']) . '">';
            ?>
        <?php else : ?>
            <p>Keine Gruppen zugewiesen.</p>
        <?php endif; ?>

            <div id="ms365-group-members" aria-live="polite"></div>

            <div id="ms365-add-member">
                <input type="text" id="ms365-member-name"  placeholder="Vollständiger Name (optional)" aria-label="Vollständiger Name (optional)">
                <input type="text" id="ms365-member-email" placeholder="Mitglied per E-Mail hinzufügen" aria-label="E-Mail des Mitglieds">
                <button id="ms365-add-member-btn" type="button">Hinzufügen</button>
            </div>
        </div>

        <script type="text/javascript">
            (function($, w){
                // Kollision mit globalem 'ajaxurl' vermeiden:
                w.ms365AjaxUrl = "<?php echo esc_js( admin_url('admin-ajax.php') ); ?>";
                var ms365Nonce = "<?php echo esc_js($ms365_nonce); ?>";

                function getSelectedGroupId() {
                    var singleID = $('#ms365-single-group-id').val();
                    if (singleID) return singleID;
                    return $('#ms365-group-select').val();
                }

                function loadMembers() {
                    var groupId = getSelectedGroupId();
                    if (!groupId) {
                        $('#ms365-group-members').html('');
                        return;
                    }
                    $.post(w.ms365AjaxUrl, {
                        action: 'wp_ms365_get_members',
                        group_id: groupId,
                        _ajax_nonce: ms365Nonce
                    }, function(response){
                        if (response && response.success === false) {
                            $('#ms365-group-members').html('<p style="color:red;">' + (response.data || 'Fehler') + '</p>');
                        } else {
                            $('#ms365-group-members').html(response);
                        }
                    });
                }

                $(document).on('change', '#ms365-group-select', function(){
                    loadMembers();
                });

                $('#ms365-add-member-btn').on('click', function(){
                    var groupId  = getSelectedGroupId();
                    var email    = $('#ms365-member-email').val();
                    var fullName = $('#ms365-member-name').val();

                    if (!groupId || !email) {
                        alert("Bitte Gruppe und E-Mail angeben!");
                        return;
                    }

                    $.post(w.ms365AjaxUrl, {
                        action: 'wp_ms365_add_member',
                        group_id: groupId,
                        email: email,
                        full_name: fullName,
                        _ajax_nonce: ms365Nonce
                    }, function(response){
                        if (response.success) {
                            alert(response.data);
                            loadMembers();
                        } else {
                            alert("Fehler: " + response.data);
                        }
                    });
                });

                $('#ms365-group-members').on('click', '.ms365-remove-member', function(){
                    var groupId  = getSelectedGroupId();
                    var memberId = $(this).data('id');
                    if (!groupId || !memberId) return;

                    $.post(w.ms365AjaxUrl, {
                        action: 'wp_ms365_remove_member',
                        group_id: groupId,
                        member_id: memberId,
                        _ajax_nonce: ms365Nonce
                    }, function(response){
                        if (response.success) {
                            alert(response.data);
                            loadMembers();
                        } else {
                            alert("Fehler: " + response.data);
                        }
                    });
                });

                $('#ms365-group-members').on('click', '.ms365-rename-user', function(){
                    var memberId = $(this).data('id');
                    var newName  = prompt("Neuen Namen eingeben:");
                    if (!newName) return;

                    $.post(w.ms365AjaxUrl, {
                        action: 'wp_ms365_rename_user',
                        user_id: memberId,
                        display_name: newName,
                        _ajax_nonce: ms365Nonce
                    }, function(resp){
                        if (resp.success) {
                            alert(resp.data);
                            loadMembers();
                        } else {
                            alert("Fehler: " + resp.data);
                        }
                    });
                });

                $('#ms365-group-members').on('click', '.ms365-delete-user', function(){
                    var memberId = $(this).data('id');
                    if (!confirm("Diesen Microsoft-Benutzer wirklich löschen?")) return;

                    $.post(w.ms365AjaxUrl, {
                        action: 'wp_ms365_delete_user',
                        user_id: memberId,
                        _ajax_nonce: ms365Nonce
                    }, function(resp){
                        if (resp.success) {
                            alert(resp.data);
                            loadMembers();
                        } else {
                            alert("Fehler: " + resp.data);
                        }
                    });
                });

                $('#ms365-group-members').on('click', '.ms365-set-position', function(){
                    var memberId = $(this).data('id');
                    var newPos   = prompt("Neue Position eingeben:");
                    if (newPos === null) return;

                    $.post(w.ms365AjaxUrl, {
                        action: 'wp_ms365_set_position',
                        user_id: memberId,
                        position: newPos,
                        _ajax_nonce: ms365Nonce
                    }, function(resp){
                        if (resp.success) {
                            alert(resp.data);
                            loadMembers();
                        } else {
                            alert("Fehler: " + resp.data);
                        }
                    });
                });

                $(document).ready(function(){
                    if (getSelectedGroupId()) {
                        loadMembers();
                    }
                });
            })(jQuery, window);
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX: get_members
     */
    public function ajax_get_members() {
        check_ajax_referer('wp_ms365_ajax');

        if ( ! current_user_can('read') ) {
            wp_send_json_error('Keine Berechtigung.');
        }

        $group_id = isset($_POST['group_id']) ? sanitize_text_field($_POST['group_id']) : '';
        if ( ! $group_id ) {
            wp_send_json_error('Keine Gruppe angegeben.');
        }
        if ( ! $this->is_guid($group_id) ) {
            wp_send_json_error('Ungültige group_id.');
        }
        if ( ! $this->current_user_can_manage_group($group_id) ) {
            wp_send_json_error('Kein Zugriff auf diese Gruppe.');
        }

        $token = $this->get_access_token();
        if ( ! $token ) {
            wp_send_json_error('Kein gültiges Access Token (bitte erneut verbinden).');
        }

        $url = $this->graph_endpoint . 'groups/' . rawurlencode($group_id)
             . '/members?$select=id,displayName,userPrincipalName,mail,jobTitle';

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token
            ),
            'timeout' => 30,
        );
        $this->record_graph_trace('get_members', 'GET', $url, $args['headers'], null, null);
        $resp = wp_remote_get($url, $args);
        $this->record_graph_trace('get_members', 'GET', $url, $args['headers'], null, $resp);

        if ( is_wp_error($resp) ) {
            $this->record_graph_error('Mitgliederabruf WP_Error: '.$resp->get_error_message());
            wp_send_json_error('Fehler beim Abruf.');
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode( wp_remote_retrieve_body($resp), true );

        if ($code < 200 || $code > 299) {
            $this->record_graph_error('Mitgliederabruf HTTP '.$code.': '.substr(json_encode($body),0,300));
        }

        if ( empty($body['value']) ) {
            echo '<p>Keine Mitglieder gefunden.</p>';
            wp_die();
        }

        $canManage = current_user_can('manage_options');

        echo '<ul>';
        foreach ($body['value'] as $member ) {
            $displayName = !empty($member['displayName']) ? $member['displayName'] : ($member['userPrincipalName'] ?? '');
            $mail        = isset($member['mail']) && $member['mail'] ? $member['mail'] : ($member['userPrincipalName'] ?? '');
            $position    = isset($member['jobTitle']) ? $member['jobTitle'] : '';
            $memberId    = $member['id'];

            echo '<li>';
            echo esc_html($displayName)
                 . ' (Mail: ' . esc_html($mail) . ')'
                 . ' - Position: ' . esc_html($position);

            echo ' <button class="ms365-remove-member" data-id="' . esc_attr($memberId) . '">Entfernen</button>';

            if ( $canManage ) {
                echo ' <button class="ms365-rename-user" data-id="' . esc_attr($memberId) . '">Namen ändern</button>';
                echo ' <button class="ms365-delete-user" data-id="' . esc_attr($memberId) . '">Benutzer löschen</button>';
                echo ' <button class="ms365-set-position" data-id="' . esc_attr($memberId) . '">Position ändern</button>';
            }
            echo '</li>';
        }
        echo '</ul>';
        wp_die();
    }

    /**
     * Admin-Diagnose: Manueller Gruppenabruf
     */
    public function ajax_admin_fetch_groups() {
        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error('Keine Berechtigung.');
        }
        check_ajax_referer('wp_ms365_admin_diag');

        // Clear cache so we fetch fresh data
        delete_transient('wp_ms365_tenant_groups');

        $groups = $this->get_tenant_groups();
        if (empty($groups)) {
            $err = get_option('wp_ms365_last_graph_error', 'Keine Gruppen und kein Fehler protokolliert.');
            $trace = get_option('wp_ms365_last_graph_trace', array());
            $out = "Keine Gruppen gefunden.\nFehler: ".$err."\n";
            if (!empty($trace)) {
                $out .= "Letzte Anfrage: ".($trace['request']['method'] ?? 'GET')." ".($trace['request']['url'] ?? '')."\n";
                $out .= "HTTP: ". ( isset($trace['response']['status']) ? $trace['response']['status'] : 'n/a' ) . "\n";
                $out .= "Antwort: ". substr( json_encode( $trace['response'] ), 0, 1000 ) . "\n";
            }
            wp_send_json_error($out);
        }

        $preview = array_slice(array_map(function($g){
            return array(
                'id' => $g['id'] ?? '',
                'displayName' => $g['displayName'] ?? '',
                'types' => isset($g['groupTypes']) ? implode(',', (array)$g['groupTypes']) : ''
            );
        }, $groups), 0, 10);

        $out = "Gruppen gesamt: ".count($groups)."\n";
        $out .= "Vorschau (max 10):\n";
        foreach ($preview as $p) {
            $out .= "- {$p['displayName']} ({$p['id']}) [{$p['types']}]\n";
        }

        wp_send_json_success(array('output'=>$out));
    }

    /**
     * AJAX: set_position (nur Admins)
     */
    public function ajax_set_position() {
        check_ajax_referer('wp_ms365_ajax');

        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error('Keine Berechtigung (manage_options) für Positionsänderungen.');
        }

        $user_id  = isset($_POST['user_id'])  ? sanitize_text_field($_POST['user_id']) : '';
        $position = isset($_POST['position']) ? sanitize_text_field($_POST['position']) : '';

        if ( ! $this->is_guid($user_id) ) {
            wp_send_json_error('Ungültige user_id.');
        }

        $token = $this->get_access_token();
        if ( ! $token ) {
            wp_send_json_error('Kein gültiges Access Token.');
        }

        $url  = $this->graph_endpoint . 'users/' . rawurlencode($user_id);
        $data = array( 'jobTitle' => $position );

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ),
            'body'   => wp_json_encode($data),
            'method' => 'PATCH',
            'timeout' => 30,
        );

        $this->record_graph_trace('set_position', 'PATCH', $url, $args['headers'], $data, null);
        $resp = wp_remote_request($url, $args);
        $this->record_graph_trace('set_position', 'PATCH', $url, $args['headers'], $data, $resp);

        if ( is_wp_error($resp) ) {
            $this->record_graph_error('Positionsänderung WP_Error: '.$resp->get_error_message());
            wp_send_json_error('Positionsänderung fehlgeschlagen.');
        }

        $code = wp_remote_retrieve_response_code($resp);
        if ( $code < 200 || $code > 299 ) {
            $this->record_graph_error('Positionsänderung HTTP '.$code.': '.substr(wp_remote_retrieve_body($resp),0,300));
            wp_send_json_error('Positionsänderung fehlgeschlagen (Graph).');
        }

        wp_send_json_success("Position erfolgreich geändert.");
    }

    /**
     * AJAX: rename_user (nur Admins)
     */
    public function ajax_rename_user() {
        check_ajax_referer('wp_ms365_ajax');

        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error('Keine Berechtigung (manage_options).');
        }

        $user_id      = isset($_POST['user_id'])       ? sanitize_text_field($_POST['user_id'])       : '';
        $display_name = isset($_POST['display_name'])  ? sanitize_text_field($_POST['display_name'])  : '';

        if ( ! $this->is_guid($user_id) || $display_name === '' ) {
            wp_send_json_error('Ungültige Parameter.');
        }

        $token = $this->get_access_token();
        if ( ! $token ) {
            wp_send_json_error('Kein gültiges Access Token.');
        }

        $url  = $this->graph_endpoint . 'users/' . rawurlencode($user_id);
        $data = array( 'displayName' => $display_name );

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ),
            'body'   => wp_json_encode($data),
            'method' => 'PATCH',
            'timeout' => 30,
        );

        $this->record_graph_trace('rename_user', 'PATCH', $url, $args['headers'], $data, null);
        $resp = wp_remote_request($url, $args);
        $this->record_graph_trace('rename_user', 'PATCH', $url, $args['headers'], $data, $resp);

        if ( is_wp_error($resp) ) {
            $this->record_graph_error('Namensänderung WP_Error: '.$resp->get_error_message());
            wp_send_json_error('Namensänderung fehlgeschlagen.');
        }
        $code = wp_remote_retrieve_response_code($resp);

        if ( $code < 200 || $code > 299 ) {
            $this->record_graph_error('Namensänderung HTTP '.$code.': '.substr(wp_remote_retrieve_body($resp),0,300));
            wp_send_json_error("Namensänderung fehlgeschlagen (Graph).");
        }

        wp_send_json_success("Anzeigename erfolgreich geändert.");
    }

    /**
     * AJAX: delete_user (nur Admins)
     */
    public function ajax_delete_user() {
        check_ajax_referer('wp_ms365_ajax');

        if ( ! current_user_can('manage_options') ) {
            wp_send_json_error('Keine Berechtigung (manage_options).');
        }

        $user_id = isset($_POST['user_id']) ? sanitize_text_field($_POST['user_id']) : '';
        if ( ! $this->is_guid($user_id) ) {
            wp_send_json_error('Ungültige user_id.');
        }

        $token = $this->get_access_token();
        if ( ! $token ) {
            wp_send_json_error('Kein gültiges Access Token.');
        }

        $url = $this->graph_endpoint . 'users/' . rawurlencode($user_id);
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token
            ),
            'method'  => 'DELETE',
            'timeout' => 30,
        );

        $this->record_graph_trace('delete_user', 'DELETE', $url, $args['headers'], null, null);
        $resp = wp_remote_request($url, $args);
        $this->record_graph_trace('delete_user', 'DELETE', $url, $args['headers'], null, $resp);

        if ( is_wp_error($resp) ) {
            $this->record_graph_error('Löschung WP_Error: '.$resp->get_error_message());
            wp_send_json_error('Löschung fehlgeschlagen.');
        }

        $code = wp_remote_retrieve_response_code($resp);

        if ( $code < 200 || $code > 299 ) {
            $this->record_graph_error('Löschung HTTP '.$code.': '.substr(wp_remote_retrieve_body($resp),0,300));
            wp_send_json_error("Benutzer-Löschung fehlgeschlagen (Graph).");
        }

        wp_send_json_success("Benutzer erfolgreich gelöscht.");
    }

    /**
     * AJAX: remove_member (neu implementiert)
     * Entfernt ein Mitglied aus der Gruppe: DELETE /groups/{group-id}/members/{directory-object-id}/$ref
     */
    public function ajax_remove_member() {
        check_ajax_referer('wp_ms365_ajax');

        if ( ! current_user_can('read') ) {
            wp_send_json_error('Keine Berechtigung.');
        }

        $group_id  = isset($_POST['group_id'])  ? sanitize_text_field($_POST['group_id']) : '';
        $member_id = isset($_POST['member_id']) ? sanitize_text_field($_POST['member_id']) : '';

        if ( ! $this->is_guid($group_id) || ! $this->is_guid($member_id) ) {
            wp_send_json_error('Ungültige Parameter.');
        }
        if ( ! $this->current_user_can_manage_group($group_id) ) {
            wp_send_json_error('Kein Zugriff auf diese Gruppe.');
        }

        $token = $this->get_access_token();
        if ( ! $token ) {
            wp_send_json_error('Kein gültiges Access Token (bitte erneut verbinden).');
        }

        $url = $this->graph_endpoint . 'groups/' . rawurlencode($group_id) . '/members/' . rawurlencode($member_id) . '/$ref';

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token
            ),
            'method'  => 'DELETE',
            'timeout' => 30,
        );

        $this->record_graph_trace('remove_member', 'DELETE', $url, $args['headers'], null, null);
        $resp = wp_remote_request($url, $args);
        $this->record_graph_trace('remove_member', 'DELETE', $url, $args['headers'], null, $resp);

        if ( is_wp_error($resp) ) {
            $this->record_graph_error('remove_member WP_Error: '.$resp->get_error_message());
            wp_send_json_error('Fehler beim Entfernen des Mitglieds.');
        }
        $code = wp_remote_retrieve_response_code($resp);

        if ( $code < 200 || $code > 299 ) {
            $this->record_graph_error('remove_member HTTP '.$code.': '.substr(wp_remote_retrieve_body($resp),0,300));
            wp_send_json_error("Fehler beim Entfernen (Graph).");
        }

        wp_send_json_success("Mitglied wurde aus der Gruppe entfernt.");
    }

    /**
     * AJAX: add_member
     */
    public function ajax_add_member() {
        check_ajax_referer('wp_ms365_ajax');

        if ( ! current_user_can('read') ) {
            wp_send_json_error('Keine Berechtigung.');
        }

        $group_id  = isset($_POST['group_id'])  ? sanitize_text_field($_POST['group_id']) : '';
        $email     = isset($_POST['email'])     ? sanitize_email($_POST['email']) : '';
        $full_name = isset($_POST['full_name']) ? sanitize_text_field($_POST['full_name']) : '';

        if ( ! $group_id || ! $email ) {
            wp_send_json_error('Ungültige Parameter.');
        }
        if ( ! $this->is_guid($group_id) || ! $this->current_user_can_manage_group($group_id) ) {
            wp_send_json_error('Kein Zugriff auf diese Gruppe.');
        }

        $token = $this->get_access_token();
        if ( ! $token ) {
            wp_send_json_error('Kein gültiges Access Token (bitte erneut verbinden).');
        }

        // Rate-Limit für Einladungen pro Email
        $rate_key = 'ms365_invite_' . md5(strtolower($email));
        if ( get_transient($rate_key) ) {
            wp_send_json_error('Zu viele Einladungen. Bitte später erneut versuchen.');
        }
        set_transient($rate_key, 1, 30 * MINUTE_IN_SECONDS);

        $user_id = $this->get_user_id_by_email($email);
        if ( ! $user_id ) {
            $this->log_debug_info('ajax_add_member', "User $email nicht vorhanden, erstelle Einladung...");
            $user_id = $this->invite_guest_user($email, $full_name);
            if ( ! $user_id ) {
                wp_send_json_error("Benutzer ($email) konnte nicht eingeladen werden.");
            }
        }

        if ( ! $this->poll_user_existence($user_id, $token) ) {
            wp_send_json_error("Der neue Gastbenutzer ist noch nicht bereit. Bitte später erneut versuchen.");
        }

        $url  = $this->graph_endpoint . 'groups/' . rawurlencode($group_id) . '/members/$ref';
        $data = array(
            '@odata.id' => $this->graph_endpoint . 'directoryObjects/' . $user_id
        );
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ),
            'body'   => wp_json_encode($data),
            'method' => 'POST',
            'timeout' => 30,
        );

        $this->record_graph_trace('add_member', 'POST', $url, $args['headers'], $data, null);
        $resp = wp_remote_request($url, $args);
        $this->record_graph_trace('add_member', 'POST', $url, $args['headers'], $data, $resp);

        if ( is_wp_error($resp) ) {
            $this->record_graph_error('add_member WP_Error: '.$resp->get_error_message());
            wp_send_json_error('Fehler beim Hinzufügen.');
        }
        $code = wp_remote_retrieve_response_code($resp);

        if ( $code < 200 || $code > 299 ) {
            $this->record_graph_error('add_member HTTP '.$code.': '.substr(wp_remote_retrieve_body($resp),0,300));
            wp_send_json_error("Fehler beim Hinzufügen (Graph).");
        }

        wp_send_json_success("Mitglied ($email) wurde hinzugefügt.");
    }

    /**
     * Hilfsfunktion: BenutzerID anhand E-Mail holen (falls vorhanden)
     */
    private function get_user_id_by_email($email) {
        $token = $this->get_access_token();
        if ( ! $token ) {
            return false;
        }

        $filterEmail = rawurlencode("userPrincipalName eq '" . $email . "'");
        $url = $this->graph_endpoint . "users?\$filter={$filterEmail}&\$select=id,userPrincipalName";
        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token
            ),
            'timeout' => 30
        );
        $this->record_graph_trace('get_user_by_email', 'GET', $url, $args['headers'], null, null);
        $res = wp_remote_get($url, $args);
        $this->record_graph_trace('get_user_by_email', 'GET', $url, $args['headers'], null, $res);

        if ( is_wp_error($res) ) {
            $this->record_graph_error('get_user_by_email WP_Error: '.$res->get_error_message());
            return false;
        }
        $body = json_decode(wp_remote_retrieve_body($res), true);
        $value = $body['value'] ?? array();
        if ( ! empty($value) && isset($value[0]['id']) ) {
            return $value[0]['id'];
        }
        return false;
    }

    /**
     * Hilfsfunktion: Gastbenutzer einladen
     */
    private function invite_guest_user($email, $displayName) {
        $token = $this->get_access_token();
        if ( ! $token ) {
            return false;
        }

        $url = $this->graph_endpoint . 'invitations';
        $data = array(
            'invitedUserEmailAddress' => $email,
            'inviteRedirectUrl'       => home_url(),
            'sendInvitationMessage'   => true,
            'invitedUserDisplayName'  => $displayName
        );

        $args = array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json'
            ),
            'body'    => wp_json_encode($data),
            'method'  => 'POST',
            'timeout' => 30,
        );

        $this->record_graph_trace('invite_guest_user', 'POST', $url, $args['headers'], $data, null);
        $resp = wp_remote_request($url, $args);
        $this->record_graph_trace('invite_guest_user', 'POST', $url, $args['headers'], $data, $resp);

        if ( is_wp_error($resp) ) {
            $this->record_graph_error('invite_guest_user WP_Error: '.$resp->get_error_message());
            return false;
        }
        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        if ( $code < 200 || $code > 299 ) {
            $this->record_graph_error('invite_guest_user HTTP '.$code.': '.substr(json_encode($body),0,300));
            return false;
        }

        if ( isset($body['invitedUser']['id']) ) {
            return $body['invitedUser']['id'];
        }

        return false;
    }

    /**
     * Hilfsfunktion: Warten bis der Benutzer im Verzeichnis verfügbar ist
     */
    private function poll_user_existence($user_id, $token, $retries=5) {
        $url = $this->graph_endpoint . 'users/' . rawurlencode($user_id);

        for ($i = 0; $i < $retries; $i++) {
            $args = array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token
                ),
                'timeout' => 30
            );
            $this->record_graph_trace('poll_user_existence', 'GET', $url, $args['headers'], null, null);
            $resp = wp_remote_get($url, $args);
            $this->record_graph_trace('poll_user_existence', 'GET', $url, $args['headers'], null, $resp);

            if ( ! is_wp_error($resp) ) {
                $code = wp_remote_retrieve_response_code($resp);
                if ( $code >= 200 && $code < 300 ) {
                    return true;
                }
            }
            sleep(1);
        }
        $this->record_graph_error('User '.$user_id.' nicht verfügbar nach '.$retries.' Versuchen.');
        return false;
    }

    /**
     * Profilfelder - nur Admin
     */
    public function user_profile_fields( $user ) {
        if ( ! current_user_can('manage_options') ) {
            return;
        }

        $allGroups = $this->get_tenant_groups();
        $allowed   = get_user_meta($user->ID, 'ms365_allowed_groups', true );
        if ( ! is_array($allowed) ) {
            $allowed = array();
        }
        ?>
        <h3>MS365 Erlaubte Gruppen (nur für Admins sichtbar)</h3>
        <table class="form-table">
            <tr>
                <th><label for="ms365_allowed_groups">Gruppen auswählen</label></th>
                <td>
                <?php
                if ( empty($allGroups) ) {
                    $err   = get_option('wp_ms365_last_graph_error', 'Keine Gruppen gefunden.');
                    $trace = get_option('wp_ms365_last_graph_trace', array());
                    echo '<p style="color:#c00;">Keine Gruppen gefunden.</p>';
                    echo '<details><summary>Diagnose anzeigen</summary><pre style="background:#111;color:#eee;padding:8px;">';
                    echo esc_html("Fehler: ".$err."\n");
                    if (!empty($trace)) {
                        echo esc_html("Request: ".($trace['request']['method'] ?? '')." ".($trace['request']['url'] ?? '')."\n");
                        echo esc_html("Response: ".json_encode($trace['response'] ?? array())."\n");
                    }
                    echo '</pre></details>';
                    echo '<p>Hinweis: Prüfe OAuth-Verbindung/Scopes und versuche den <em>Manuellen Gruppenabruf</em> in den Einstellungen.</p>';
                } else {
                    foreach ($allGroups as $grp) {
                        $gid   = $grp['id'];
                        $gname = $grp['displayName'];
                        ?>
                        <label>
                            <input type="checkbox"
                                   name="ms365_allowed_groups[]"
                                   value="<?php echo esc_attr($gid); ?>"
                                   <?php checked( in_array($gid, $allowed, true) ); ?>>
                            <?php echo esc_html($gname); ?>
                        </label><br>
                        <?php
                    }
                }
                ?>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_user_profile_fields( $user_id ) {
        if ( ! current_user_can('manage_options') ) {
            return false;
        }
        $allowed = isset($_POST['ms365_allowed_groups']) ? (array)$_POST['ms365_allowed_groups'] : array();
        $clean   = array();
        foreach ($allowed as $gid) {
            $gid = sanitize_text_field($gid);
            if ($this->is_guid($gid)) {
                $clean[] = $gid;
            }
        }
        update_user_meta($user_id, 'ms365_allowed_groups', $clean);

        // Store group names for fallback when API is down
        $all_groups = $this->get_tenant_groups();
        $names = array();
        foreach ($all_groups as $grp) {
            if (in_array($grp['id'], $clean, true)) {
                $names[$grp['id']] = $grp['displayName'];
            }
        }
        if (!empty($names)) {
            update_user_meta($user_id, 'ms365_allowed_groups_names', $names);
        }
    }
}

/**
 * Init
 */
function wp_ms365_group_manager_init() {
    WP_MS365_Group_Manager::instance();
}
add_action( 'plugins_loaded', 'wp_ms365_group_manager_init' );

/**
 * Option mit autoload=no anlegen (Tokens nicht autoloaden)
 */
register_activation_hook(__FILE__, function(){
    if ( get_option('wp_ms365_plugin_options', null) === null ) {
        add_option('wp_ms365_plugin_options', array(), '', 'no'); // autoload = no
    }
    // Diagnose-Optionen initialisieren (nicht autoloaden)
    if ( get_option('wp_ms365_last_graph_error', null) === null ) {
        add_option('wp_ms365_last_graph_error', '', '', 'no');
    }
    if ( get_option('wp_ms365_last_graph_trace', null) === null ) {
        add_option('wp_ms365_last_graph_trace', array(), '', 'no');
    }
    // Verschlüsselungs-Key sicherstellen (autoload=no)
    if ( get_option('wp_ms365_enc_key', null) === null ) {
        $rawKey  = random_bytes(32);
        $rawHmac = random_bytes(32);
        $key = base64_encode($rawKey) . '|' . base64_encode($rawHmac);
        add_option('wp_ms365_enc_key', $key, '', 'no');
    }
});
