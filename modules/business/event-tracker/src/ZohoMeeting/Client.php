<?php
/**
 * Zoho Meeting API Client
 *
 * OAuth2-basierter Client fuer die Zoho Meeting API.
 * Nutzt die bestehenden CRM-Zugangsdaten (Client-ID, Secret, Refresh-Token).
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
	 * Transient key for access token
	 *
	 * @var string
	 */
	const TOKEN_TRANSIENT = 'et_zoho_meeting_access_token';

	/**
	 * Constructor
	 */
	public function __construct() {
		$settings       = get_option( Constants::OPT_KEY, [] );
		$this->api_base = ! empty( $settings['zoho_meeting_api_base'] )
			? rtrim( $settings['zoho_meeting_api_base'], '/' )
			: 'https://meeting.zoho.eu';
		$this->zsoid    = ! empty( $settings['zoho_meeting_zsoid'] )
			? $settings['zoho_meeting_zsoid']
			: '';

		Helpers::log( sprintf(
			'ZohoMeeting Client init: api_base=%s, zsoid=%s',
			$this->api_base,
			$this->zsoid ?: '(NICHT GESETZT)'
		), 'info' );
	}

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
			'https://accounts.zoho.eu/oauth/v2/token',
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
			Helpers::log( 'Zoho Meeting: Token-Refresh fehlgeschlagen: ' . $response->get_error_message(), 'error' );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			$error = isset( $body['error'] ) ? $body['error'] : 'unknown';
			Helpers::log( 'Zoho Meeting: Token-Refresh Fehler: ' . $error, 'error' );
			return false;
		}

		$token      = $body['access_token'];
		$expires_in = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 3600;

		// Cache with 5-minute buffer
		set_transient( self::TOKEN_TRANSIENT, $token, max( $expires_in - 300, 60 ) );

		Helpers::log( 'Zoho Meeting: Access-Token erfolgreich erneuert', 'info' );

		return $token;
	}

	/**
	 * Make API request with automatic 401 retry
	 *
	 * @param string $endpoint API endpoint (relative to base + zsoid).
	 * @param string $method   HTTP method.
	 * @param array  $body     Request body (will be JSON-encoded for POST/PUT).
	 * @param bool   $is_retry Whether this is a retry after 401.
	 * @return array|WP_Error Response array with 'code' and 'body' keys.
	 */
	private function make_request( $endpoint, $method = 'GET', $body = [], $is_retry = false ) {
		$token = $this->get_access_token();

		if ( ! $token ) {
			Helpers::log( 'make_request abgebrochen: Kein Access-Token verfuegbar', 'error' );
			return new \WP_Error( 'no_token', __( 'Kein Zoho Access-Token verfuegbar.', 'event-tracker' ) );
		}

		if ( ! $this->zsoid ) {
			Helpers::log( 'make_request abgebrochen: ZSOID nicht konfiguriert (et_settings > zoho_meeting_zsoid)', 'error' );
			return new \WP_Error( 'no_zsoid', __( 'Zoho Meeting ZSOID nicht konfiguriert.', 'event-tracker' ) );
		}

		$url = $this->api_base . '/api/v2/' . $this->zsoid . '/' . ltrim( $endpoint, '/' );

		Helpers::log( sprintf( 'API Request: %s %s', $method, $url ), 'info' );

		$args = [
			'method'  => $method,
			'timeout' => 30,
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $token,
				'Content-Type'  => 'application/json',
			],
		];

		if ( ! empty( $body ) && in_array( $method, [ 'POST', 'PUT' ], true ) ) {
			$args['body'] = wp_json_encode( $body );
			Helpers::log( sprintf( 'Request Body: %s', $args['body'] ), 'info' );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			Helpers::log( 'API WP_Error: ' . $response->get_error_message(), 'error' );
			return $response;
		}

		$status_code   = wp_remote_retrieve_response_code( $response );
		$raw_body      = wp_remote_retrieve_body( $response );
		$response_body = json_decode( $raw_body, true );

		Helpers::log( sprintf( 'API Response: HTTP %d, Body: %s', $status_code, substr( $raw_body, 0, 500 ) ), 'info' );

		// 401 retry: refresh token and try once more
		if ( $status_code === 401 && ! $is_retry ) {
			Helpers::log( 'HTTP 401 — Token erneuern und erneut versuchen', 'warning' );
			delete_transient( self::TOKEN_TRANSIENT );
			$this->access_token = $this->refresh_access_token();
			if ( $this->access_token ) {
				return $this->make_request( $endpoint, $method, $body, true );
			}
			Helpers::log( 'Token-Refresh nach 401 fehlgeschlagen', 'error' );
		}

		if ( $status_code >= 400 ) {
			$error_msg = isset( $response_body['message'] ) ? $response_body['message'] : $raw_body;
			Helpers::log( sprintf( 'API Fehler: HTTP %d — %s', $status_code, $error_msg ), 'error' );
		}

		return [
			'code' => $status_code,
			'body' => is_array( $response_body ) ? $response_body : [],
		];
	}

	/**
	 * Test API connection
	 *
	 * @return array
	 */
	public function test_connection() {
		$result = $this->make_request( 'webinar.json?limit=1' );

		if ( is_wp_error( $result ) ) {
			return [
				'ok'      => false,
				'message' => $result->get_error_message(),
			];
		}

		if ( $result['code'] >= 200 && $result['code'] < 300 ) {
			return [
				'ok'      => true,
				'message' => __( 'Verbindung erfolgreich.', 'event-tracker' ),
			];
		}

		$error = isset( $result['body']['message'] ) ? $result['body']['message'] : 'HTTP ' . $result['code'];
		return [
			'ok'      => false,
			'message' => sprintf( __( 'API-Fehler: %s', 'event-tracker' ), $error ),
		];
	}

	/**
	 * Create webinar
	 *
	 * @param array $data Webinar data (topic, startTime, duration, timezone).
	 * @return array
	 */
	public function create_webinar( $data ) {
		$payload = [
			'session' => array_merge(
				[
					'timezone' => wp_timezone_string(),
				],
				$data
			),
		];

		$result = $this->make_request( 'webinar.json', 'POST', $payload );

		if ( is_wp_error( $result ) ) {
			return [ 'ok' => false, 'message' => $result->get_error_message() ];
		}

		if ( $result['code'] >= 200 && $result['code'] < 300 ) {
			return [
				'ok'   => true,
				'data' => $this->normalize_session( $result['body'] ),
			];
		}

		$error = isset( $result['body']['message'] ) ? $result['body']['message'] : 'HTTP ' . $result['code'];
		return [ 'ok' => false, 'message' => $error ];
	}

	/**
	 * Get webinar details
	 *
	 * @param string $session_key Session/meeting key.
	 * @return array
	 */
	public function get_webinar( $session_key ) {
		$result = $this->make_request( 'webinar/' . urlencode( $session_key ) . '.json' );

		if ( is_wp_error( $result ) ) {
			return [ 'ok' => false, 'message' => $result->get_error_message() ];
		}

		if ( $result['code'] >= 200 && $result['code'] < 300 ) {
			return [
				'ok'   => true,
				'data' => $this->normalize_session( $result['body'] ),
			];
		}

		$error = isset( $result['body']['message'] ) ? $result['body']['message'] : 'HTTP ' . $result['code'];
		return [ 'ok' => false, 'message' => $error ];
	}

	/**
	 * Update webinar
	 *
	 * @param string $session_key Session/meeting key.
	 * @param array  $data        Data to update.
	 * @return array
	 */
	public function update_webinar( $session_key, $data ) {
		$payload = [
			'session' => $data,
		];

		$result = $this->make_request( 'webinar/' . urlencode( $session_key ) . '.json', 'PUT', $payload );

		if ( is_wp_error( $result ) ) {
			return [ 'ok' => false, 'message' => $result->get_error_message() ];
		}

		if ( $result['code'] >= 200 && $result['code'] < 300 ) {
			return [ 'ok' => true, 'data' => $this->normalize_session( $result['body'] ) ];
		}

		$error = isset( $result['body']['message'] ) ? $result['body']['message'] : 'HTTP ' . $result['code'];
		return [ 'ok' => false, 'message' => $error ];
	}

	/**
	 * Delete webinar
	 *
	 * @param string $session_key Session/meeting key.
	 * @return array
	 */
	public function delete_webinar( $session_key ) {
		$result = $this->make_request( 'webinar/' . urlencode( $session_key ) . '.json', 'DELETE' );

		if ( is_wp_error( $result ) ) {
			return [ 'ok' => false, 'message' => $result->get_error_message() ];
		}

		if ( $result['code'] >= 200 && $result['code'] < 300 ) {
			return [ 'ok' => true, 'message' => __( 'Webinar geloescht.', 'event-tracker' ) ];
		}

		$error = isset( $result['body']['message'] ) ? $result['body']['message'] : 'HTTP ' . $result['code'];
		return [ 'ok' => false, 'message' => $error ];
	}

	/**
	 * Get recordings for a session
	 *
	 * @param string $session_key Session/meeting key.
	 * @return array
	 */
	public function get_recordings( $session_key ) {
		// Zoho Meeting recordings endpoint uses meetingKey as query param
		$result = $this->make_request( 'recordings.json?meetingKey=' . urlencode( $session_key ) );

		if ( is_wp_error( $result ) ) {
			return [ 'ok' => false, 'message' => $result->get_error_message() ];
		}

		if ( $result['code'] >= 200 && $result['code'] < 300 ) {
			// Normalize recording field names (camelCase → snake_case)
			$recordings = isset( $result['body']['recordings'] ) ? $result['body']['recordings'] : [];
			$normalized = [];
			foreach ( $recordings as $rec ) {
				$normalized[] = [
					'play_url'     => isset( $rec['playUrl'] ) ? $rec['playUrl'] : ( isset( $rec['play_url'] ) ? $rec['play_url'] : '' ),
					'download_url' => isset( $rec['downloadUrl'] ) ? $rec['downloadUrl'] : ( isset( $rec['download_url'] ) ? $rec['download_url'] : '' ),
					'share_url'    => isset( $rec['shareUrl'] ) ? $rec['shareUrl'] : ( isset( $rec['share_url'] ) ? $rec['share_url'] : '' ),
				];
			}
			return [ 'ok' => true, 'data' => [ 'recordings' => $normalized ] ];
		}

		$error = isset( $result['body']['message'] ) ? $result['body']['message'] : 'HTTP ' . $result['code'];
		return [ 'ok' => false, 'message' => $error ];
	}

	/**
	 * Add co-host to session
	 *
	 * @param string $session_key Session/meeting key.
	 * @param string $email       Co-host email.
	 * @return array
	 */
	public function add_cohost( $session_key, $email ) {
		$payload = [
			'cohosts' => [
				[ 'email' => $email ],
			],
		];

		$result = $this->make_request( 'webinar/' . urlencode( $session_key ) . '/cohosts.json', 'PUT', $payload );

		if ( is_wp_error( $result ) ) {
			return [ 'ok' => false, 'message' => $result->get_error_message() ];
		}

		if ( $result['code'] >= 200 && $result['code'] < 300 ) {
			return [ 'ok' => true, 'data' => $result['body'] ];
		}

		$error = isset( $result['body']['message'] ) ? $result['body']['message'] : 'HTTP ' . $result['code'];
		return [ 'ok' => false, 'message' => $error ];
	}

	/**
	 * Normalize Zoho API response field names.
	 *
	 * Zoho Meeting API returns camelCase field names (meetingKey, startLink, joinLink).
	 * This method normalizes them to our internal snake_case convention.
	 *
	 * @param array $body Raw API response body.
	 * @return array Normalized response with 'session' key.
	 */
	private function normalize_session( $body ) {
		$session = isset( $body['session'] ) ? $body['session'] : $body;

		// Map Zoho camelCase → our snake_case (check both variants for robustness)
		$key_map = [
			'session_key' => [ 'meetingKey', 'session_key', 'key' ],
			'start_url'   => [ 'startLink', 'start_url', 'startUrl' ],
			'join_url'    => [ 'joinLink', 'join_url', 'joinUrl', 'registrationLink' ],
			'status'      => [ 'status' ],
			'topic'       => [ 'topic' ],
		];

		$normalized = [];
		foreach ( $key_map as $target => $sources ) {
			$normalized[ $target ] = '';
			foreach ( $sources as $source ) {
				if ( ! empty( $session[ $source ] ) ) {
					$normalized[ $target ] = $session[ $source ];
					break;
				}
			}
		}

		// Collect consumed source keys to avoid duplication
		$consumed = [];
		foreach ( $key_map as $sources ) {
			foreach ( $sources as $source ) {
				$consumed[] = $source;
			}
		}

		// Pass through unconsumed fields only
		foreach ( $session as $k => $v ) {
			if ( ! isset( $normalized[ $k ] ) && ! in_array( $k, $consumed, true ) ) {
				$normalized[ $k ] = $v;
			}
		}

		return [ 'session' => $normalized ];
	}
}
