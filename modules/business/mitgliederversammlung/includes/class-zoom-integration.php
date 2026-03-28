<?php
/**
 * Zoom S2S OAuth Integration (EINE konsolidierte Implementierung).
 *
 * Ersetzt die duplizierte Zoom-Logik aus abstimmen-addon und anwesenheitsscanner.
 * Unterstuetzt Meetings und Webinare fuer hybride Versammlungen.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DGPTM_MV_Zoom {

	const OPT_KEY      = 'dgptm_mv_zoom_settings';
	const LOG_KEY      = 'dgptm_mv_zoom_log';
	const LOG_MAX      = 500;
	const REST_NS      = 'dgptm-mv/v1';

	private $defaults = [
		'zoom_enable'           => 0,
		'zoom_kind'             => 'meeting',
		'zoom_meeting_id'       => '',
		'zoom_s2s_account_id'   => '',
		'zoom_s2s_client_id'    => '',
		'zoom_s2s_client_secret'=> '',
		'zoom_webhook_secret'   => '',
		'zoom_webhook_token'    => '',
		'zoom_log_enable'       => 1,
	];

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ], 5 );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	public function get_settings() {
		return wp_parse_args( get_option( self::OPT_KEY, [] ), $this->defaults );
	}

	public function register_settings() {
		register_setting( 'dgptm_mv_zoom', self::OPT_KEY );
	}

	// --- OAuth Token ---

	public function get_token( $opts = null, $force = false ) {
		if ( ! $opts ) $opts = $this->get_settings();

		$key = 'dgptm_mv_s2s_' . md5( $opts['zoom_s2s_account_id'] . '|' . $opts['zoom_s2s_client_id'] );
		if ( ! $force && ( $cached = get_transient( $key ) ) ) {
			return $cached;
		}

		$auth = base64_encode( $opts['zoom_s2s_client_id'] . ':' . $opts['zoom_s2s_client_secret'] );
		$url  = add_query_arg( [
			'grant_type' => 'account_credentials',
			'account_id' => $opts['zoom_s2s_account_id'],
		], 'https://zoom.us/oauth/token' );

		$r = wp_remote_post( $url, [
			'timeout' => 15,
			'headers' => [
				'Authorization' => 'Basic ' . $auth,
				'Content-Type'  => 'application/x-www-form-urlencoded',
			],
			'body' => '',
		] );

		if ( is_wp_error( $r ) ) {
			$this->log( 'token', 'error', $r->get_error_message() );
			throw new \RuntimeException( $r->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $r );
		$body = json_decode( wp_remote_retrieve_body( $r ), true );

		if ( $code < 200 || $code >= 300 || empty( $body['access_token'] ) ) {
			$this->log( 'token', 'error', "HTTP {$code}" );
			throw new \RuntimeException( "S2S Token HTTP {$code}" );
		}

		$ttl = max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 60 );
		set_transient( $key, $body, $ttl );
		$this->log( 'token', 'ok', 'Token erhalten' );
		return $body;
	}

	// --- API Calls ---

	public function api( $method, $path, $payload = null, $opts = null ) {
		if ( ! $opts ) $opts = $this->get_settings();

		$tok  = $this->get_token( $opts );
		$url  = 'https://api.zoom.us/v2' . $path;
		$args = [
			'timeout' => 20,
			'headers' => [
				'Authorization' => 'Bearer ' . $tok['access_token'],
				'Content-Type'  => 'application/json',
			],
		];

		if ( $method === 'GET' ) {
			$r = wp_remote_get( $url, $args );
		} else {
			$args['method'] = $method;
			if ( $payload ) $args['body'] = wp_json_encode( $payload );
			$r = wp_remote_request( $url, $args );
		}

		if ( is_wp_error( $r ) ) {
			$this->log( 'api', 'error', $method . ' ' . $path . ': ' . $r->get_error_message() );
			throw new \RuntimeException( $r->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $r );
		$body = json_decode( wp_remote_retrieve_body( $r ), true );

		$this->log( 'api', $code >= 200 && $code < 300 ? 'ok' : 'error', $method . ' ' . $path . ' → ' . $code );

		if ( $code < 200 || $code >= 300 ) {
			throw new \RuntimeException( "Zoom API {$code}: " . substr( wp_remote_retrieve_body( $r ), 0, 400 ) );
		}

		return $body;
	}

	// --- REST Routes ---

	public function register_rest_routes() {
		register_rest_route( self::REST_NS, '/zoom/webhook', [
			'methods'             => [ 'POST', 'GET' ],
			'callback'            => [ $this, 'rest_webhook' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( self::REST_NS, '/zoom/live', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rest_live_status' ],
			'permission_callback' => '__return_true',
		] );
	}

	/**
	 * Zoom-Webhook Handler.
	 * Verarbeitet: meeting/webinar started/ended, participant joined/left.
	 */
	public function rest_webhook( \WP_REST_Request $req ) {
		if ( $req->get_method() === 'GET' ) {
			return new \WP_REST_Response( [ 'ok' => true ], 200 );
		}

		$opts   = $this->get_settings();
		$secret = $opts['zoom_webhook_secret'] ?? '';
		$raw    = (string) $req->get_body();
		$data   = json_decode( $raw, true );

		// URL-Validation Challenge
		if ( is_array( $data ) && ( $data['event'] ?? '' ) === 'endpoint.url_validation' ) {
			$plain = (string) ( $data['payload']['plainToken'] ?? '' );
			if ( empty( $plain ) || empty( $secret ) ) {
				return new \WP_REST_Response( [ 'error' => 'missing_data' ], 400 );
			}
			return new \WP_REST_Response( [
				'plainToken'     => $plain,
				'encryptedToken' => hash_hmac( 'sha256', $plain, $secret ),
			], 200 );
		}

		// Signatur pruefen
		if ( ! $this->verify_signature( $req, $secret ) ) {
			$this->log( 'webhook', 'error', 'Signatur ungueltig' );
			return new \WP_REST_Response( [ 'error' => 'unauthorized' ], 401 );
		}

		if ( ! is_array( $data ) ) {
			return new \WP_REST_Response( [ 'ok' => true ], 200 );
		}

		$event = (string) ( $data['event'] ?? '' );
		$obj   = $data['payload']['object'] ?? [];

		// Aktive Versammlung finden
		$assembly = DGPTM_MV_Assembly::get_active_assembly();
		if ( ! $assembly ) {
			return new \WP_REST_Response( [ 'ok' => true, 'note' => 'no_active_assembly' ], 200 );
		}

		// Participant Join → Check-in
		if ( strpos( $event, 'participant_joined' ) !== false ) {
			$p = $obj['participant'] ?? [];
			DGPTM_MV_Attendance::checkin_from_zoom( $assembly->id, $p );

			// Zoom-Session speichern
			global $wpdb;
			$wpdb->insert( DGPTM_MV_Database::table('zoom_sessions'), [
				'assembly_id'       => $assembly->id,
				'participant_name'  => sanitize_text_field( $p['user_name'] ?? '' ),
				'participant_email' => sanitize_email( $p['email'] ?? '' ),
				'zoom_user_id'      => sanitize_text_field( $p['user_id'] ?? ( $p['id'] ?? '' ) ),
				'join_time'         => current_time( 'mysql' ),
			] );

			$this->log( 'webhook', 'ok', 'Joined: ' . ( $p['user_name'] ?? 'Unknown' ) );
			return new \WP_REST_Response( [ 'ok' => true, 'joined' => 1 ], 200 );
		}

		// Participant Leave → Session schliessen
		if ( strpos( $event, 'participant_left' ) !== false ) {
			$p = $obj['participant'] ?? [];
			global $wpdb;

			$email = sanitize_email( $p['email'] ?? '' );
			$zoom_uid = sanitize_text_field( $p['user_id'] ?? ( $p['id'] ?? '' ) );

			// Letzte offene Session schliessen
			$session = $wpdb->get_row( $wpdb->prepare(
				"SELECT * FROM " . DGPTM_MV_Database::table('zoom_sessions') .
				" WHERE assembly_id = %d AND (participant_email = %s OR zoom_user_id = %s) AND leave_time IS NULL
				  ORDER BY join_time DESC LIMIT 1",
				$assembly->id, $email, $zoom_uid
			) );

			if ( $session ) {
				$now = current_time( 'mysql' );
				$dur = strtotime( $now ) - strtotime( $session->join_time );
				$wpdb->update( DGPTM_MV_Database::table('zoom_sessions'), [
					'leave_time'       => $now,
					'duration_seconds' => max( 0, $dur ),
				], [ 'id' => $session->id ] );
			}

			$this->log( 'webhook', 'ok', 'Left: ' . ( $p['user_name'] ?? 'Unknown' ) );
			return new \WP_REST_Response( [ 'ok' => true, 'left' => 1 ], 200 );
		}

		// Meeting started/ended
		if ( strpos( $event, '.started' ) !== false || strpos( $event, '.ended' ) !== false ) {
			$this->log( 'webhook', 'ok', $event );
			return new \WP_REST_Response( [ 'ok' => true, 'event' => $event ], 200 );
		}

		return new \WP_REST_Response( [ 'ok' => true, 'note' => 'ignored' ], 200 );
	}

	/**
	 * Live-Status eines Zoom-Meetings.
	 */
	public function rest_live_status( \WP_REST_Request $req ) {
		$opts = $this->get_settings();
		$mid  = preg_replace( '/\D/', '', $opts['zoom_meeting_id'] ?? '' );
		$kind = $opts['zoom_kind'] ?? 'meeting';

		if ( empty( $mid ) ) {
			return new \WP_REST_Response( [ 'live' => false, 'reason' => 'no_meeting_id' ], 200 );
		}

		try {
			$path = "/{$kind}s/{$mid}";
			$data = $this->api( 'GET', $path, null, $opts );
			$live = ( $data['status'] ?? '' ) === 'started';
			return new \WP_REST_Response( [ 'live' => $live ], 200 );
		} catch ( \Exception $e ) {
			return new \WP_REST_Response( [ 'live' => false, 'error' => $e->getMessage() ], 200 );
		}
	}

	// --- Hilfsmethoden ---

	private function verify_signature( \WP_REST_Request $req, $secret ) {
		if ( empty( $secret ) ) return true; // Kein Secret → Skip

		$sig = $req->get_header( 'x-zm-signature' );
		$ts  = $req->get_header( 'x-zm-request-timestamp' );

		if ( $sig && $ts ) {
			$now = time();
			if ( abs( $now - (int) $ts ) > 300 ) return false;

			$msg    = "v0:{$ts}:" . $req->get_body();
			$digest = 'v0=' . hash_hmac( 'sha256', $msg, $secret );
			return hash_equals( $digest, $sig );
		}

		// Fallback: Legacy Token
		$token  = $req->get_header( 'x-zm-token' ) ?: $req->get_header( 'authorization' );
		$legacy = get_option( self::OPT_KEY, [] )['zoom_webhook_token'] ?? '';
		if ( $legacy && $token ) {
			return hash_equals( $legacy, $token );
		}

		return false;
	}

	private function log( $phase, $status, $message ) {
		$opts = $this->get_settings();
		if ( empty( $opts['zoom_log_enable'] ) ) return;

		$log = get_option( self::LOG_KEY, [] );
		if ( ! is_array( $log ) ) $log = [];

		array_unshift( $log, [
			'time'    => current_time( 'mysql' ),
			'phase'   => $phase,
			'status'  => $status,
			'message' => $message,
		] );

		if ( count( $log ) > self::LOG_MAX ) {
			$log = array_slice( $log, 0, self::LOG_MAX );
		}

		update_option( self::LOG_KEY, $log, false );
	}
}
