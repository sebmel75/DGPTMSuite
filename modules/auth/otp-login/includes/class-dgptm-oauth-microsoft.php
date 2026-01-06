<?php
/**
 * Microsoft OAuth 2.0 / OpenID Connect Integration
 *
 * Ermöglicht Login mit Microsoft-Konten (persönlich, Arbeit, Schule)
 * über den "common" Endpoint.
 *
 * @package DGPTM_OTP_Login
 * @since 3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'DGPTM_OAuth_Microsoft' ) ) {

    class DGPTM_OAuth_Microsoft {

        private static $instance = null;

        // Microsoft OAuth Endpoints (common = alle Account-Typen)
        const AUTHORIZE_URL = 'https://login.microsoftonline.com/common/oauth2/v2.0/authorize';
        const TOKEN_URL     = 'https://login.microsoftonline.com/common/oauth2/v2.0/token';
        const USERINFO_URL  = 'https://graph.microsoft.com/v1.0/me';

        // Benötigte Scopes
        const SCOPES = 'openid email profile';

        // Transient-Prefix für State-Tokens
        const STATE_PREFIX = 'dgptm_oauth_state_';

        /**
         * Singleton Instance
         */
        public static function get_instance() {
            if ( null === self::$instance ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Constructor
         */
        private function __construct() {
            // REST API Endpoint registrieren
            add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

            // Admin-Einstellungen
            add_action( 'admin_init', [ $this, 'register_settings' ] );
        }

        /**
         * Prüft ob Microsoft OAuth aktiviert und konfiguriert ist
         */
        public function is_enabled() {
            $enabled   = (int) dgptm_get_option( 'dgptm_oauth_microsoft_enabled', 0 );
            $client_id = trim( (string) dgptm_get_option( 'dgptm_oauth_microsoft_client_id', '' ) );
            $secret    = trim( (string) dgptm_get_option( 'dgptm_oauth_microsoft_client_secret', '' ) );

            return $enabled && ! empty( $client_id ) && ! empty( $secret );
        }

        /**
         * Generiert die Authorization URL für Microsoft Login
         */
        public function get_authorization_url( $redirect_after_login = '' ) {
            $client_id = trim( (string) dgptm_get_option( 'dgptm_oauth_microsoft_client_id', '' ) );

            if ( empty( $client_id ) ) {
                return new WP_Error( 'missing_config', __( 'Microsoft OAuth ist nicht konfiguriert.', 'dgptm' ) );
            }

            // State-Token generieren (CSRF-Schutz)
            $state = $this->generate_state_token( $redirect_after_login );

            // PKCE Code Verifier und Challenge generieren
            $code_verifier  = $this->generate_code_verifier();
            $code_challenge = $this->generate_code_challenge( $code_verifier );

            // Code Verifier in Transient speichern (für Token-Austausch)
            set_transient( self::STATE_PREFIX . 'verifier_' . $state, $code_verifier, 600 );

            $params = [
                'client_id'             => $client_id,
                'response_type'         => 'code',
                'redirect_uri'          => $this->get_callback_url(),
                'response_mode'         => 'query',
                'scope'                 => self::SCOPES,
                'state'                 => $state,
                'code_challenge'        => $code_challenge,
                'code_challenge_method' => 'S256',
                'prompt'                => 'select_account', // Immer Account-Auswahl zeigen
            ];

            return self::AUTHORIZE_URL . '?' . http_build_query( $params );
        }

        /**
         * Callback URL für Microsoft OAuth
         */
        public function get_callback_url() {
            return rest_url( 'dgptm/v1/oauth/microsoft/callback' );
        }

        /**
         * REST API Routes registrieren
         */
        public function register_rest_routes() {
            register_rest_route( 'dgptm/v1', '/oauth/microsoft/callback', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_callback' ],
                'permission_callback' => '__return_true',
            ] );

            register_rest_route( 'dgptm/v1', '/oauth/microsoft/init', [
                'methods'             => 'GET',
                'callback'            => [ $this, 'handle_init' ],
                'permission_callback' => '__return_true',
            ] );
        }

        /**
         * Initiiert den OAuth-Flow (Redirect zu Microsoft)
         */
        public function handle_init( WP_REST_Request $request ) {
            if ( ! $this->is_enabled() ) {
                wp_die( __( 'Microsoft Login ist nicht aktiviert.', 'dgptm' ), __( 'Fehler', 'dgptm' ), [ 'response' => 403 ] );
            }

            $redirect_after = sanitize_url( $request->get_param( 'redirect_to' ) ?: home_url( '/' ) );
            $auth_url = $this->get_authorization_url( $redirect_after );

            if ( is_wp_error( $auth_url ) ) {
                wp_die( $auth_url->get_error_message(), __( 'Fehler', 'dgptm' ), [ 'response' => 500 ] );
            }

            wp_redirect( $auth_url );
            exit;
        }

        /**
         * Verarbeitet den Callback von Microsoft
         */
        public function handle_callback( WP_REST_Request $request ) {
            // Fehler von Microsoft prüfen
            $error = $request->get_param( 'error' );
            if ( $error ) {
                $error_desc = $request->get_param( 'error_description' ) ?: $error;
                return $this->redirect_with_error( __( 'Microsoft Login fehlgeschlagen: ', 'dgptm' ) . $error_desc );
            }

            // Authorization Code und State extrahieren
            $code  = $request->get_param( 'code' );
            $state = $request->get_param( 'state' );

            if ( empty( $code ) || empty( $state ) ) {
                return $this->redirect_with_error( __( 'Ungültige Antwort von Microsoft.', 'dgptm' ) );
            }

            // State validieren (CSRF-Schutz)
            $state_data = $this->validate_state_token( $state );
            if ( ! $state_data ) {
                return $this->redirect_with_error( __( 'Sicherheitstoken ungültig oder abgelaufen.', 'dgptm' ) );
            }

            // Code Verifier abrufen
            $verifier_key = self::STATE_PREFIX . 'verifier_' . $state;
            $code_verifier = get_transient( $verifier_key );
            delete_transient( $verifier_key );

            if ( ! $code_verifier ) {
                return $this->redirect_with_error( __( 'Sicherheitstoken abgelaufen. Bitte erneut versuchen.', 'dgptm' ) );
            }

            // Authorization Code gegen Access Token tauschen
            $tokens = $this->exchange_code_for_tokens( $code, $code_verifier );
            if ( is_wp_error( $tokens ) ) {
                return $this->redirect_with_error( $tokens->get_error_message() );
            }

            // Erst versuchen, E-Mail aus ID Token zu extrahieren (braucht keine Graph API)
            $email = $this->extract_email_from_id_token( $tokens );

            // Falls nicht im ID Token, Graph API versuchen
            if ( empty( $email ) ) {
                $user_info = $this->get_user_info( $tokens['access_token'] );
                if ( ! is_wp_error( $user_info ) ) {
                    $email = $this->extract_email_from_user_info( $user_info );
                }
            }

            if ( empty( $email ) ) {
                return $this->redirect_with_error( __( 'Keine E-Mail-Adresse von Microsoft erhalten. Bitte prüfen Sie Ihre Microsoft-Kontoeinstellungen.', 'dgptm' ) );
            }

            // WordPress-Benutzer mit dieser E-Mail suchen
            $wp_user = get_user_by( 'email', $email );
            if ( ! $wp_user ) {
                return $this->redirect_with_error(
                    sprintf(
                        __( 'Kein Benutzerkonto mit der E-Mail-Adresse %s gefunden. Bitte kontaktieren Sie den Administrator.', 'dgptm' ),
                        esc_html( $email )
                    )
                );
            }

            // Benutzer einloggen
            wp_set_current_user( $wp_user->ID );
            wp_set_auth_cookie( $wp_user->ID, true ); // Remember me = true
            do_action( 'wp_login', $wp_user->user_login, $wp_user );

            // Zur gewünschten Seite weiterleiten
            $redirect_to = ! empty( $state_data['redirect'] ) ? $state_data['redirect'] : home_url( '/' );
            wp_safe_redirect( $redirect_to );
            exit;
        }

        /**
         * Tauscht Authorization Code gegen Tokens
         */
        private function exchange_code_for_tokens( $code, $code_verifier ) {
            $client_id     = trim( (string) dgptm_get_option( 'dgptm_oauth_microsoft_client_id', '' ) );
            $client_secret = trim( (string) dgptm_get_option( 'dgptm_oauth_microsoft_client_secret', '' ) );

            $response = wp_remote_post( self::TOKEN_URL, [
                'timeout' => 30,
                'body'    => [
                    'client_id'     => $client_id,
                    'client_secret' => $client_secret,
                    'code'          => $code,
                    'redirect_uri'  => $this->get_callback_url(),
                    'grant_type'    => 'authorization_code',
                    'code_verifier' => $code_verifier,
                ],
            ] );

            if ( is_wp_error( $response ) ) {
                return new WP_Error( 'token_error', __( 'Verbindung zu Microsoft fehlgeschlagen.', 'dgptm' ) );
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( isset( $body['error'] ) ) {
                $error_msg = $body['error_description'] ?? $body['error'];
                return new WP_Error( 'token_error', __( 'Token-Fehler: ', 'dgptm' ) . $error_msg );
            }

            if ( empty( $body['access_token'] ) ) {
                return new WP_Error( 'token_error', __( 'Kein Access Token erhalten.', 'dgptm' ) );
            }

            return $body;
        }

        /**
         * Ruft Benutzerinformationen von Microsoft Graph API ab
         */
        private function get_user_info( $access_token ) {
            $response = wp_remote_get( self::USERINFO_URL, [
                'timeout' => 30,
                'headers' => [
                    'Authorization' => 'Bearer ' . $access_token,
                    'Content-Type'  => 'application/json',
                ],
            ] );

            if ( is_wp_error( $response ) ) {
                return new WP_Error( 'userinfo_error', __( 'Benutzerinformationen konnten nicht abgerufen werden.', 'dgptm' ) );
            }

            $body = json_decode( wp_remote_retrieve_body( $response ), true );

            if ( isset( $body['error'] ) ) {
                return new WP_Error( 'userinfo_error', $body['error']['message'] ?? __( 'Unbekannter Fehler', 'dgptm' ) );
            }

            return $body;
        }

        /**
         * Extrahiert die E-Mail-Adresse aus dem ID Token
         */
        private function extract_email_from_id_token( $tokens ) {
            if ( empty( $tokens['id_token'] ) ) {
                return null;
            }

            $id_token_parts = explode( '.', $tokens['id_token'] );
            if ( count( $id_token_parts ) !== 3 ) {
                return null;
            }

            $payload = json_decode( base64_decode( strtr( $id_token_parts[1], '-_', '+/' ) ), true );

            // Priorität 1: email claim
            if ( ! empty( $payload['email'] ) && is_email( $payload['email'] ) ) {
                return strtolower( $payload['email'] );
            }

            // Priorität 2: preferred_username (oft E-Mail-Format bei Azure AD)
            if ( ! empty( $payload['preferred_username'] ) && is_email( $payload['preferred_username'] ) ) {
                return strtolower( $payload['preferred_username'] );
            }

            // Priorität 3: upn (User Principal Name)
            if ( ! empty( $payload['upn'] ) && is_email( $payload['upn'] ) ) {
                return strtolower( $payload['upn'] );
            }

            return null;
        }

        /**
         * Extrahiert die E-Mail-Adresse aus den Graph API Benutzerinformationen
         */
        private function extract_email_from_user_info( $user_info ) {
            // Priorität 1: mail-Feld
            if ( ! empty( $user_info['mail'] ) && is_email( $user_info['mail'] ) ) {
                return strtolower( $user_info['mail'] );
            }

            // Priorität 2: userPrincipalName
            if ( ! empty( $user_info['userPrincipalName'] ) && is_email( $user_info['userPrincipalName'] ) ) {
                return strtolower( $user_info['userPrincipalName'] );
            }

            return null;
        }

        /**
         * Generiert einen sicheren State-Token
         */
        private function generate_state_token( $redirect_after = '' ) {
            $state = bin2hex( random_bytes( 32 ) );

            $data = [
                'created'  => time(),
                'redirect' => $redirect_after,
            ];

            set_transient( self::STATE_PREFIX . $state, $data, 600 ); // 10 Minuten gültig

            return $state;
        }

        /**
         * Validiert einen State-Token
         */
        private function validate_state_token( $state ) {
            $state = preg_replace( '/[^a-f0-9]/', '', $state );
            $data  = get_transient( self::STATE_PREFIX . $state );

            if ( ! $data ) {
                return false;
            }

            // State-Token löschen (Einmalverwendung)
            delete_transient( self::STATE_PREFIX . $state );

            // Zeitprüfung (max 10 Minuten)
            if ( time() - $data['created'] > 600 ) {
                return false;
            }

            return $data;
        }

        /**
         * Generiert PKCE Code Verifier
         */
        private function generate_code_verifier() {
            return rtrim( strtr( base64_encode( random_bytes( 64 ) ), '+/', '-_' ), '=' );
        }

        /**
         * Generiert PKCE Code Challenge aus Verifier
         */
        private function generate_code_challenge( $verifier ) {
            $hash = hash( 'sha256', $verifier, true );
            return rtrim( strtr( base64_encode( $hash ), '+/', '-_' ), '=' );
        }

        /**
         * Redirect mit Fehlermeldung zur Login-Seite
         */
        private function redirect_with_error( $message ) {
            $login_page = dgptm_get_option( 'dgptm_oauth_login_page', '' );

            if ( empty( $login_page ) ) {
                // Fallback: Home mit Error-Parameter
                $redirect_url = add_query_arg( 'oauth_error', urlencode( $message ), home_url( '/' ) );
            } else {
                $redirect_url = add_query_arg( 'oauth_error', urlencode( $message ), $login_page );
            }

            wp_safe_redirect( $redirect_url );
            exit;
        }

        /**
         * Registriert Admin-Einstellungen
         */
        public function register_settings() {
            // Einstellungen werden in class-dgptm-oauth-settings.php registriert
        }

        /**
         * Rendert den Microsoft Login Button (für Shortcode)
         */
        public function render_login_button( $redirect_to = '' ) {
            if ( ! $this->is_enabled() ) {
                return '';
            }

            $init_url = rest_url( 'dgptm/v1/oauth/microsoft/init' );
            if ( ! empty( $redirect_to ) ) {
                $init_url = add_query_arg( 'redirect_to', urlencode( $redirect_to ), $init_url );
            }

            ob_start();
            ?>
            <a href="<?php echo esc_url( $init_url ); ?>" class="dgptm-oauth-btn dgptm-oauth-microsoft">
                <svg xmlns="http://www.w3.org/2000/svg" width="21" height="21" viewBox="0 0 21 21">
                    <rect x="1" y="1" width="9" height="9" fill="#f25022"/>
                    <rect x="11" y="1" width="9" height="9" fill="#7fba00"/>
                    <rect x="1" y="11" width="9" height="9" fill="#00a4ef"/>
                    <rect x="11" y="11" width="9" height="9" fill="#ffb900"/>
                </svg>
                <span><?php esc_html_e( 'Mit Microsoft anmelden', 'dgptm' ); ?></span>
            </a>
            <?php
            return ob_get_clean();
        }
    }
}

// Initialisieren
if ( ! isset( $GLOBALS['dgptm_oauth_microsoft_initialized'] ) ) {
    $GLOBALS['dgptm_oauth_microsoft_initialized'] = true;
    DGPTM_OAuth_Microsoft::get_instance();
}
