<?php
/**
 * Zoho Backstage API Handler
 *
 * Verwaltet die Kommunikation mit der Zoho Backstage API
 * inklusive OAuth2 Authentifizierung
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Zoho_Backstage_API {

    /**
     * API Base URL (Europa)
     */
    private $api_base_url = 'https://www.zohoapis.eu/backstage/v3';

    /**
     * Portal ID
     */
    private $portal_id;

    /**
     * Access Token
     */
    private $access_token;

    /**
     * Konstruktor
     */
    public function __construct() {
        $this->portal_id = get_option('dgptm_session_display_portal_id', '20086233464');
        $this->access_token = $this->get_access_token();
    }

    /**
     * Access Token abrufen (mit Refresh wenn nötig)
     */
    private function get_access_token() {
        $token = get_transient('dgptm_zoho_backstage_access_token');

        if (!$token) {
            $token = $this->refresh_access_token();
        }

        return $token;
    }

    /**
     * Access Token erneuern
     */
    private function refresh_access_token() {
        $refresh_token = get_option('dgptm_session_display_refresh_token');
        $client_id = get_option('dgptm_session_display_client_id');
        $client_secret = get_option('dgptm_session_display_client_secret');

        if (empty($refresh_token) || empty($client_id) || empty($client_secret)) {
            error_log('DGPTM Session Display: OAuth2-Credentials fehlen');
            return false;
        }

        $response = wp_remote_post('https://accounts.zoho.eu/oauth/v2/token', [
            'body' => [
                'refresh_token' => $refresh_token,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'grant_type' => 'refresh_token'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            error_log('DGPTM Session Display: Token-Refresh fehlgeschlagen - ' . $response->get_error_message());
            return false;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['access_token'])) {
            $access_token = $body['access_token'];
            $expires_in = isset($body['expires_in']) ? intval($body['expires_in']) : 3600;

            // Token cachen (mit Puffer von 5 Minuten)
            set_transient('dgptm_zoho_backstage_access_token', $access_token, $expires_in - 300);

            return $access_token;
        }

        error_log('DGPTM Session Display: Ungültige Token-Response - ' . print_r($body, true));
        return false;
    }

    /**
     * API-Request durchführen
     */
    private function make_request($endpoint, $method = 'GET', $body = null) {
        if (!$this->access_token) {
            return new WP_Error('no_token', 'Kein gültiger Access Token verfügbar');
        }

        $url = $this->api_base_url . $endpoint;

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $this->access_token,
                'Content-Type' => 'application/json'
            ],
            'timeout' => 30
        ];

        if ($body && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            $args['body'] = json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log('DGPTM Session Display API Error: ' . $response->get_error_message());
            return $response;
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Token abgelaufen - erneuern und erneut versuchen
        if ($status_code === 401) {
            $this->access_token = $this->refresh_access_token();
            if ($this->access_token) {
                return $this->make_request($endpoint, $method, $body);
            }
        }

        if ($status_code >= 400) {
            error_log('DGPTM Session Display API Error: Status ' . $status_code . ' - ' . $body);
            return new WP_Error('api_error', 'API-Fehler: ' . $status_code, $data);
        }

        return $data;
    }

    /**
     * Events abrufen
     */
    public function get_events() {
        $endpoint = "/portals/{$this->portal_id}/events";
        return $this->make_request($endpoint);
    }

    /**
     * Event-Details abrufen
     */
    public function get_event($event_id) {
        $endpoint = "/portals/{$this->portal_id}/events/{$event_id}";
        return $this->make_request($endpoint);
    }

    /**
     * Sessions eines Events abrufen
     */
    public function get_sessions($event_id = null) {
        // Wenn keine Event-ID angegeben, Standard-Event verwenden
        if (!$event_id) {
            $event_id = get_option('dgptm_session_display_event_id');
        }

        if (!$event_id) {
            return new WP_Error('no_event_id', 'Keine Event-ID konfiguriert');
        }

        $endpoint = "/portals/{$this->portal_id}/events/{$event_id}/sessions";
        return $this->make_request($endpoint);
    }

    /**
     * Session-Details abrufen
     */
    public function get_session($event_id, $session_id) {
        $endpoint = "/portals/{$this->portal_id}/events/{$event_id}/sessions/{$session_id}";
        return $this->make_request($endpoint);
    }

    /**
     * Tracks (Räume/Tracks) abrufen
     */
    public function get_tracks($event_id = null) {
        if (!$event_id) {
            $event_id = get_option('dgptm_session_display_event_id');
        }

        if (!$event_id) {
            return new WP_Error('no_event_id', 'Keine Event-ID konfiguriert');
        }

        $endpoint = "/portals/{$this->portal_id}/events/{$event_id}/tracks";
        return $this->make_request($endpoint);
    }

    /**
     * Speakers abrufen
     */
    public function get_speakers($event_id = null) {
        if (!$event_id) {
            $event_id = get_option('dgptm_session_display_event_id');
        }

        if (!$event_id) {
            return new WP_Error('no_event_id', 'Keine Event-ID konfiguriert');
        }

        $endpoint = "/portals/{$this->portal_id}/events/{$event_id}/speakers";
        return $this->make_request($endpoint);
    }

    /**
     * Testen der API-Verbindung
     */
    public function test_connection() {
        $events = $this->get_events();

        if (is_wp_error($events)) {
            return [
                'success' => false,
                'message' => 'Verbindung fehlgeschlagen: ' . $events->get_error_message()
            ];
        }

        return [
            'success' => true,
            'message' => 'Verbindung erfolgreich',
            'data' => $events
        ];
    }

    /**
     * OAuth2 Authorization URL generieren
     */
    public static function get_authorization_url() {
        $client_id = get_option('dgptm_session_display_client_id');
        $redirect_uri = admin_url('admin.php?page=dgptm-session-display&action=oauth_callback');

        $params = [
            'scope' => 'ZohoBackstage.events.READ,ZohoBackstage.portals.READ',
            'client_id' => $client_id,
            'response_type' => 'code',
            'access_type' => 'offline',
            'redirect_uri' => $redirect_uri,
            'prompt' => 'consent'
        ];

        return 'https://accounts.zoho.eu/oauth/v2/auth?' . http_build_query($params);
    }

    /**
     * OAuth2 Code gegen Token tauschen
     */
    public static function exchange_code_for_token($code) {
        $client_id = get_option('dgptm_session_display_client_id');
        $client_secret = get_option('dgptm_session_display_client_secret');
        $redirect_uri = admin_url('admin.php?page=dgptm-session-display&action=oauth_callback');

        $response = wp_remote_post('https://accounts.zoho.eu/oauth/v2/token', [
            'body' => [
                'code' => $code,
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'redirect_uri' => $redirect_uri,
                'grant_type' => 'authorization_code'
            ],
            'timeout' => 30
        ]);

        if (is_wp_error($response)) {
            return [
                'success' => false,
                'message' => 'Token-Exchange fehlgeschlagen: ' . $response->get_error_message()
            ];
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['access_token']) && isset($body['refresh_token'])) {
            // Access Token cachen
            $expires_in = isset($body['expires_in']) ? intval($body['expires_in']) : 3600;
            set_transient('dgptm_zoho_backstage_access_token', $body['access_token'], $expires_in - 300);

            // Refresh Token speichern
            update_option('dgptm_session_display_refresh_token', $body['refresh_token']);

            return [
                'success' => true,
                'message' => 'Authentifizierung erfolgreich',
                'data' => $body
            ];
        }

        return [
            'success' => false,
            'message' => 'Ungültige Token-Response',
            'data' => $body
        ];
    }
}
