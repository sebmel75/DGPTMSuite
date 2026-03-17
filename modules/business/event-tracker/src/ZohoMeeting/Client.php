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
			Helpers::log( 'Zoho Meeting: CRM-Zugangsdaten fehlen (client_id, client_secret oder refresh_token)', 'error' );
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
			return new \WP_Error( 'no_token', __( 'Kein Zoho Access-Token verfuegbar.', 'event-tracker' ) );
		}

		if ( ! $this->zsoid ) {
			return new \WP_Error( 'no_zsoid', __( 'Zoho Meeting ZSOID nicht konfiguriert.', 'event-tracker' ) );
		}

		$url = $this->api_base . '/api/v2/' . $this->zsoid . '/' . ltrim( $endpoint, '/' );

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
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			Helpers::log( 'Zoho Meeting API Fehler: ' . $response->get_error_message(), 'error' );
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		// 401 retry: refresh token and try once more
		if ( $status_code === 401 && ! $is_retry ) {
			delete_transient( self::TOKEN_TRANSIENT );
			$this->access_token = $this->refresh_access_token();
			if ( $this->access_token ) {
				return $this->make_request( $endpoint, $method, $body, true );
			}
		}

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

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
		$result = $this->make_request( 'sessions.json?limit=1' );

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
	 * @param array $data Webinar data (topic, start_time, duration, timezone, type).
	 * @return array
	 */
	public function create_webinar( $data ) {
		$payload = [
			'session' => array_merge(
				[
					'type'     => 'webinar',
					'timezone' => wp_timezone_string(),
				],
				$data
			),
		];

		$result = $this->make_request( 'sessions.json', 'POST', $payload );

		if ( is_wp_error( $result ) ) {
			return [ 'ok' => false, 'message' => $result->get_error_message() ];
		}

		if ( $result['code'] >= 200 && $result['code'] < 300 ) {
			return [
				'ok'   => true,
				'data' => $result['body'],
			];
		}

		$error = isset( $result['body']['message'] ) ? $result['body']['message'] : 'HTTP ' . $result['code'];
		return [ 'ok' => false, 'message' => $error ];
	}

	/**
	 * Get webinar details
	 *
	 * @param string $session_key Session key.
	 * @return array
	 */
	public function get_webinar( $session_key ) {
		$result = $this->make_request( 'sessions/' . urlencode( $session_key ) . '.json' );

		if ( is_wp_error( $result ) ) {
			return [ 'ok' => false, 'message' => $result->get_error_message() ];
		}

		if ( $result['code'] >= 200 && $result['code'] < 300 ) {
			return [
				'ok'   => true,
				'data' => $result['body'],
			];
		}

		$error = isset( $result['body']['message'] ) ? $result['body']['message'] : 'HTTP ' . $result['code'];
		return [ 'ok' => false, 'message' => $error ];
	}

	/**
	 * Update webinar
	 *
	 * @param string $session_key Session key.
	 * @param array  $data        Data to update.
	 * @return array
	 */
	public function update_webinar( $session_key, $data ) {
		$payload = [
			'session' => $data,
		];

		$result = $this->make_request( 'sessions/' . urlencode( $session_key ) . '.json', 'PUT', $payload );

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
	 * Delete webinar
	 *
	 * @param string $session_key Session key.
	 * @return array
	 */
	public function delete_webinar( $session_key ) {
		$result = $this->make_request( 'sessions/' . urlencode( $session_key ) . '.json', 'DELETE' );

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
	 * @param string $session_key Session key.
	 * @return array
	 */
	public function get_recordings( $session_key ) {
		$result = $this->make_request( 'sessions/' . urlencode( $session_key ) . '/recordings.json' );

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
	 * Add co-host to session
	 *
	 * @param string $session_key Session key.
	 * @param string $email       Co-host email.
	 * @return array
	 */
	public function add_cohost( $session_key, $email ) {
		$payload = [
			'cohosts' => [
				[ 'email' => $email ],
			],
		];

		$result = $this->make_request( 'sessions/' . urlencode( $session_key ) . '/cohosts.json', 'PUT', $payload );

		if ( is_wp_error( $result ) ) {
			return [ 'ok' => false, 'message' => $result->get_error_message() ];
		}

		if ( $result['code'] >= 200 && $result['code'] < 300 ) {
			return [ 'ok' => true, 'data' => $result['body'] ];
		}

		$error = isset( $result['body']['message'] ) ? $result['body']['message'] : 'HTTP ' . $result['code'];
		return [ 'ok' => false, 'message' => $error ];
	}
}
