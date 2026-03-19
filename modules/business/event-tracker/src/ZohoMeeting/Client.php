<?php
/**
 * Zoho Meeting API Client
 *
 * OAuth2-basierter Client fuer die Zoho Webinar API.
 * Nutzt die bestehenden CRM-Zugangsdaten (Client-ID, Secret, Refresh-Token).
 *
 * Basiert auf funktionierendem Muster (ZohoWebinar Referenz-Klasse).
 *
 * @package EventTracker\ZohoMeeting
 * @since 2.1.0
 */

namespace EventTracker\ZohoMeeting;

use EventTracker\Core\Constants;
use EventTracker\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Zoho Meeting API Client
 */
class Client {

	/**
	 * Access Token
	 *
	 * @var string|false
	 */
	private $access_token = false;

	/**
	 * API Base URL
	 *
	 * @var string
	 */
	private $api_base;

	/**
	 * Zoho Organization/Session ID (zsoid)
	 *
	 * @var string
	 */
	private $zsoid;

	/**
	 * Zoho User ID (ZUID) of the presenter
	 *
	 * @var string
	 */
	private $presenter_zuid;

	/**
	 * Transient key for access token
	 */
	const TOKEN_TRANSIENT = 'et_zoho_meeting_access_token';

	/**
	 * Default configuration (matching working reference)
	 */
	const DEFAULT_ZSOID     = '20086233025';
	const DEFAULT_PRESENTER = '20086597172';
	const DEFAULT_API_BASE  = 'https://meeting.zoho.eu';
	const AUTH_URL          = 'https://accounts.zoho.eu/oauth/v2/token';

	/**
	 * Constructor
	 */
	public function __construct() {
		$settings = get_option( Constants::OPT_KEY, [] );

		$this->api_base       = ! empty( $settings['zoho_meeting_api_base'] )
			? rtrim( $settings['zoho_meeting_api_base'], '/' )
			: self::DEFAULT_API_BASE;
		$this->zsoid          = ! empty( $settings['zoho_meeting_zsoid'] )
			? $settings['zoho_meeting_zsoid']
			: self::DEFAULT_ZSOID;
		$this->presenter_zuid = ! empty( $settings['zoho_meeting_presenter'] )
			? $settings['zoho_meeting_presenter']
			: self::DEFAULT_PRESENTER;

		Helpers::log( sprintf(
			'ZohoMeeting Client init: api_base=%s, zsoid=%s, presenter=%s',
			$this->api_base,
			$this->zsoid,
			$this->presenter_zuid
		), 'info' );
	}

	/* =========================================================================
	 * Token Management
	 * ======================================================================= */

	/**
	 * Get access token (cached in transient)
	 *
	 * @return string|false
	 */
	private function get_access_token() {
		if ( $this->access_token ) {
			return $this->access_token;
		}

		$token = get_transient( self::TOKEN_TRANSIENT );
		if ( $token ) {
			$this->access_token = $token;
			return $token;
		}

		$this->access_token = $this->refresh_access_token();
		return $this->access_token;
	}

	/**
	 * Refresh access token via OAuth2
	 *
	 * @return string|false
	 */
	private function refresh_access_token() {
		$client_id     = get_option( 'dgptm_zoho_client_id', '' );
		$client_secret = get_option( 'dgptm_zoho_client_secret', '' );
		$refresh_token = get_option( 'dgptm_zoho_refresh_token', '' );

		if ( ! $client_id || ! $client_secret || ! $refresh_token ) {
			Helpers::log( sprintf(
				'Token-Refresh: Credentials fehlen — client_id=%s, client_secret=%s, refresh_token=%s',
				$client_id ? 'gesetzt' : 'FEHLT',
				$client_secret ? 'gesetzt' : 'FEHLT',
				$refresh_token ? 'gesetzt' : 'FEHLT'
			), 'error' );
			return false;
		}

		$response = wp_remote_post(
			self::AUTH_URL,
			[
				'timeout' => 15,
				'body'    => [
					'grant_type'    => 'refresh_token',
					'client_id'     => $client_id,
					'client_secret' => $client_secret,
					'refresh_token' => $refresh_token,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			Helpers::log( 'Token-Refresh fehlgeschlagen: ' . $response->get_error_message(), 'error' );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			Helpers::log( 'Token-Refresh Fehler: ' . wp_json_encode( $body ), 'error' );
			return false;
		}

		$token      = $body['access_token'];
		$expires_in = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 3600;

		// Cache: 55 Minuten (wie in Referenz-Implementierung)
		set_transient( self::TOKEN_TRANSIENT, $token, 55 * MINUTE_IN_SECONDS );

		Helpers::log( 'Access-Token erfolgreich erneuert', 'info' );

		return $token;
	}

	/* =========================================================================
	 * API Request
	 * ======================================================================= */

	/**
	 * Make API request with automatic 401 retry
	 *
	 * Verwendet wp_remote_post fuer POST-Requests (wie Referenz-Implementierung).
	 *
	 * @param string $endpoint API endpoint (relative to base + zsoid).
	 * @param string $method   HTTP method.
	 * @param array  $body     Request body (will be JSON-encoded for POST/PUT).
	 * @param bool   $is_retry Whether this is a retry after 401.
	 * @return array|WP_Error
	 */
	private function make_request( $endpoint, $method = 'GET', $body = [], $is_retry = false ) {
		$token = $this->get_access_token();

		if ( ! $token ) {
			Helpers::log( 'make_request abgebrochen: Kein Access-Token verfuegbar', 'error' );
			return new \WP_Error( 'no_token', 'Kein Zoho Access-Token verfuegbar.' );
		}

		$url = $this->api_base . '/api/v2/' . $this->zsoid . '/' . ltrim( $endpoint, '/' );

		Helpers::log( sprintf( 'API Request: %s %s', $method, $url ), 'info' );

		$headers = [
			'Content-Type'  => 'application/json;charset=UTF-8',
			'Authorization' => 'Zoho-oauthtoken ' . $token,
		];

		// POST/PUT: wp_remote_post mit JSON body (wie Referenz)
		if ( in_array( $method, [ 'POST', 'PUT' ], true ) && ! empty( $body ) ) {
			$json_body = wp_json_encode( $body );
			Helpers::log( sprintf( 'Request Body: %s', $json_body ), 'info' );

			$response = wp_remote_post( $url, [
				'headers' => $headers,
				'body'    => $json_body,
				'timeout' => 30,
				'method'  => $method,
			] );
		} else {
			// GET/DELETE
			$response = wp_remote_request( $url, [
				'method'  => $method,
				'headers' => $headers,
				'timeout' => 30,
			] );
		}

		if ( is_wp_error( $response ) ) {
			Helpers::log( 'API WP_Error: ' . $response->get_error_message(), 'error' );
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$raw_body    = wp_remote_retrieve_body( $response );
		$parsed_body = json_decode( $raw_body, true );

		Helpers::log( sprintf( 'API Response: HTTP %d, Body: %s', $status_code, substr( $raw_body, 0, 500 ) ), 'info' );

		// 401 handling
		if ( $status_code === 401 && ! $is_retry ) {
			$error_key = isset( $parsed_body['error']['key'] ) ? $parsed_body['error']['key'] : '';
			$no_retry_keys = [ 'INVALID_PRESENTER_ID', 'INVALID_SCOPE', 'INVALID_REQUEST' ];

			if ( in_array( $error_key, $no_retry_keys, true ) ) {
				Helpers::log( sprintf( 'HTTP 401 Fehler-Key %s — kein Token-Retry', $error_key ), 'error' );
			} else {
				Helpers::log( 'HTTP 401 — Token erneuern und erneut versuchen', 'warning' );
				delete_transient( self::TOKEN_TRANSIENT );
				$this->access_token = false;
				$this->access_token = $this->refresh_access_token();
				if ( $this->access_token ) {
					return $this->make_request( $endpoint, $method, $body, true );
				}
				Helpers::log( 'Token-Refresh nach 401 fehlgeschlagen', 'error' );
			}
		}

		if ( $status_code >= 400 ) {
			$error_msg = $this->extract_error( $parsed_body, $status_code );
			Helpers::log( sprintf( 'API Fehler: HTTP %d — %s', $status_code, $error_msg ), 'error' );
		}

		return [
			'code' => $status_code,
			'body' => is_array( $parsed_body ) ? $parsed_body : [],
		];
	}

	/* =========================================================================
	 * Public API Methods
	 * ======================================================================= */

	/**
	 * Test API connection
	 *
	 * @return array
	 */
	public function test_connection() {
		$result = $this->make_request( 'webinar.json?limit=1' );

		if ( is_wp_error( $result ) ) {
			return [ 'ok' => false, 'message' => $result->get_error_message() ];
		}

		if ( $result['code'] >= 200 && $result['code'] < 300 ) {
			return [ 'ok' => true, 'message' => 'Verbindung erfolgreich.' ];
		}

		return [ 'ok' => false, 'message' => 'API-Fehler: ' . $this->extract_error( $result['body'], $result['code'] ) ];
	}

	/**
	 * Create webinar (ohne Registrierung)
	 *
	 * Payload-Struktur exakt wie Referenz-Implementierung.
	 *
	 * @param array $data Webinar data (topic, agenda, startTime, duration, timezone).
	 * @return array
	 */
	public function create_webinar( $data ) {
		$session = array_merge(
			[
				'presenter' => $this->presenter_zuid,
				'timezone'  => wp_timezone_string(),
			],
			$data
		);

		$payload = [ 'session' => $session ];

		$result = $this->make_request( 'webinar.json', 'POST', $payload );

		if ( is_wp_error( $result ) ) {
			return [ 'ok' => false, 'message' => $result->get_error_message() ];
		}

		// Zoho gibt HTTP 201 bei Erfolg zurueck
		if ( $result['code'] === 201 && ! empty( $result['body']['session'] ) ) {
			return [
				'ok'   => true,
				'data' => $this->normalize_session( $result['body'] ),
			];
		}

		// Auch 200 akzeptieren (fuer Robustheit)
		if ( $result['code'] >= 200 && $result['code'] < 300 ) {
			return [
				'ok'   => true,
				'data' => $this->normalize_session( $result['body'] ),
			];
		}

		return [ 'ok' => false, 'message' => $this->extract_error( $result['body'], $result['code'] ) ];
	}

	/**
	 * Get webinar details
	 *
	 * @param string $session_key Meeting key.
	 * @return array
	 */
	public function get_webinar( $session_key ) {
		$result = $this->make_request( 'webinar/' . urlencode( $session_key ) . '.json' );

		if ( is_wp_error( $result ) ) {
			return [ 'ok' => false, 'message' => $result->get_error_message() ];
		}

		if ( $result['code'] >= 200 && $result['code'] < 300 ) {
			return [ 'ok' => true, 'data' => $this->normalize_session( $result['body'] ) ];
		}

		return [ 'ok' => false, 'message' => $this->extract_error( $result['body'], $result['code'] ) ];
	}

	/**
	 * Update webinar
	 *
	 * @param string $session_key Meeting key.
	 * @param array  $data        Data to update.
	 * @return array
	 */
	public function update_webinar( $session_key, $data ) {
		$result = $this->make_request( 'webinar/' . urlencode( $session_key ) . '.json', 'PUT', [ 'session' => $data ] );

		if ( is_wp_error( $result ) ) {
			return [ 'ok' => false, 'message' => $result->get_error_message() ];
		}

		if ( $result['code'] >= 200 && $result['code'] < 300 ) {
			return [ 'ok' => true, 'data' => $this->normalize_session( $result['body'] ) ];
		}

		return [ 'ok' => false, 'message' => $this->extract_error( $result['body'], $result['code'] ) ];
	}

	/**
	 * Delete webinar
	 *
	 * @param string $session_key Meeting key.
	 * @return array
	 */
	public function delete_webinar( $session_key ) {
		$result = $this->make_request( 'webinar/' . urlencode( $session_key ) . '.json', 'DELETE' );

		if ( is_wp_error( $result ) ) {
			return [ 'ok' => false, 'message' => $result->get_error_message() ];
		}

		if ( $result['code'] >= 200 && $result['code'] < 300 ) {
			return [ 'ok' => true, 'message' => 'Webinar geloescht.' ];
		}

		return [ 'ok' => false, 'message' => $this->extract_error( $result['body'], $result['code'] ) ];
	}

	/**
	 * Get recordings for a session
	 *
	 * @param string $session_key Meeting key.
	 * @return array
	 */
	public function get_recordings( $session_key ) {
		$result = $this->make_request( 'recordings.json?meetingKey=' . urlencode( $session_key ) );

		if ( is_wp_error( $result ) ) {
			return [ 'ok' => false, 'message' => $result->get_error_message() ];
		}

		if ( $result['code'] >= 200 && $result['code'] < 300 ) {
			$recordings = isset( $result['body']['recordings'] ) ? $result['body']['recordings'] : [];
			$normalized = [];
			foreach ( $recordings as $rec ) {
				$normalized[] = [
					'play_url'     => $rec['playUrl'] ?? $rec['play_url'] ?? '',
					'download_url' => $rec['downloadUrl'] ?? $rec['download_url'] ?? '',
					'share_url'    => $rec['shareUrl'] ?? $rec['share_url'] ?? '',
				];
			}
			return [ 'ok' => true, 'data' => [ 'recordings' => $normalized ] ];
		}

		return [ 'ok' => false, 'message' => $this->extract_error( $result['body'], $result['code'] ) ];
	}

	/**
	 * Add co-host/participant to webinar
	 *
	 * Zoho Meeting hat keinen separaten Co-Host-Endpoint.
	 * Teilnehmer werden ueber den Webinar-Update-Endpoint als participants hinzugefuegt.
	 *
	 * @param string $session_key Meeting key.
	 * @param string $email       Co-host/participant email.
	 * @return array
	 */
	public function add_cohost( $session_key, $email ) {
		Helpers::log( sprintf( 'Co-Host hinzufuegen: key=%s, email=%s', $session_key, $email ), 'info' );

		// Zoho Meeting: Co-Presenter werden via Webinar-Update als copresenter hinzugefuegt
		$payload = [
			'session' => [
				'copresenter' => [
					[ 'email' => $email ],
				],
			],
		];

		$result = $this->make_request( 'webinar/' . urlencode( $session_key ) . '.json', 'PUT', $payload );

		if ( is_wp_error( $result ) ) {
			return [ 'ok' => false, 'message' => $result->get_error_message() ];
		}

		// Pruefen ob Co-Presenter in der Antwort enthalten ist
		if ( $result['code'] >= 200 && $result['code'] < 300 ) {
			Helpers::log( sprintf( 'Co-Host Response: %s', wp_json_encode( $result['body'] ) ), 'info' );
			return [ 'ok' => true, 'data' => $result['body'] ];
		}

		// Falls copresenter nicht funktioniert, Fallback auf participants
		Helpers::log( sprintf( 'Co-Host copresenter fehlgeschlagen (HTTP %d), versuche participants...', $result['code'] ), 'warning' );

		$payload_fallback = [
			'session' => [
				'participants' => [
					[ 'email' => $email ],
				],
			],
		];

		$result2 = $this->make_request( 'webinar/' . urlencode( $session_key ) . '.json', 'PUT', $payload_fallback );

		if ( is_wp_error( $result2 ) ) {
			return [ 'ok' => false, 'message' => $result2->get_error_message() ];
		}

		if ( $result2['code'] >= 200 && $result2['code'] < 300 ) {
			Helpers::log( sprintf( 'Co-Host participants Response: %s', wp_json_encode( $result2['body'] ) ), 'info' );
			return [ 'ok' => true, 'data' => $result2['body'] ];
		}

		return [ 'ok' => false, 'message' => $this->extract_error( $result2['body'], $result2['code'] ) ];
	}

	/* =========================================================================
	 * Helpers
	 * ======================================================================= */

	/**
	 * Normalize Zoho API response field names.
	 *
	 * Zoho returns: meetingKey, startLink, joinLink, registrationLink
	 * We normalize to: session_key, start_url, join_url
	 *
	 * @param array $body Raw API response body.
	 * @return array Normalized with 'session' key.
	 */
	private function normalize_session( $body ) {
		$session = isset( $body['session'] ) ? $body['session'] : $body;

		$meeting_key = $session['meetingKey'] ?? $session['session_key'] ?? $session['key'] ?? '';

		$normalized = [
			'session_key' => $meeting_key,
			'start_url'   => $session['startLink'] ?? $session['start_url'] ?? $session['startUrl'] ?? '',
			'join_url'    => $session['joinLink']
				?? $session['join_url']
				?? $session['joinUrl']
				?? $session['registrationLink']
				// Fallback: Join-Link aus meetingKey generieren (wie Referenz)
				?? ( $meeting_key ? $this->api_base . '/join?key=' . $meeting_key : '' ),
			'status'      => $session['status'] ?? '',
			'topic'       => $session['topic'] ?? '',
			'startTime'   => $session['startTime'] ?? '',
			'endTime'     => $session['endTime'] ?? '',
		];

		// Pass through all other fields
		$consumed = [ 'meetingKey', 'session_key', 'key', 'startLink', 'start_url', 'startUrl',
			'joinLink', 'join_url', 'joinUrl', 'registrationLink', 'status', 'topic', 'startTime', 'endTime' ];

		foreach ( $session as $k => $v ) {
			if ( ! isset( $normalized[ $k ] ) && ! in_array( $k, $consumed, true ) ) {
				$normalized[ $k ] = $v;
			}
		}

		return [ 'session' => $normalized ];
	}

	/**
	 * Extract error message from Zoho API response.
	 *
	 * Handles: {"error":{"message":"..."}}, {"message":"..."}, {"error":"string"}
	 *
	 * @param array|null $body Response body.
	 * @param int        $code HTTP status code.
	 * @return string
	 */
	private function extract_error( $body, $code ) {
		if ( ! is_array( $body ) ) {
			return 'HTTP ' . $code;
		}
		if ( isset( $body['error'] ) && is_array( $body['error'] ) && isset( $body['error']['message'] ) ) {
			return $body['error']['message'];
		}
		if ( isset( $body['message'] ) ) {
			return $body['message'];
		}
		if ( isset( $body['error'] ) && is_string( $body['error'] ) ) {
			return $body['error'];
		}
		return 'Unbekannter Fehler (HTTP ' . $code . ')';
	}
}
