<?php
/**
 * Zoho Meeting Provider
 *
 * Implementiert WebinarProvider fuer Zoho Meeting/Webinar.
 *
 * @package EventTracker\Sync
 * @since 2.3.0
 */

namespace EventTracker\Sync;

use EventTracker\ZohoMeeting\Client;
use EventTracker\Core\Constants;
use EventTracker\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ZohoMeetingProvider implements WebinarProvider {

	/** @var Client */
	private $client;

	public function __construct( ?Client $client = null ) {
		$this->client = $client ?: new Client();
	}

	public function list_webinars(): array {
		$settings = get_option( Constants::OPT_KEY, [] );
		$zsoid    = $settings['zoho_meeting_zsoid'] ?? '';
		$api_base = $settings['zoho_meeting_api_base'] ?? 'https://meeting.zoho.eu';

		if ( ! $zsoid ) {
			return [ 'ok' => false, 'data' => [], 'message' => 'ZSOID nicht konfiguriert' ];
		}

		// Token sicherstellen (test_connection loest Token-Refresh aus)
		$test = $this->client->test_connection();
		if ( ! $test['ok'] ) {
			return [ 'ok' => false, 'data' => [], 'message' => $test['message'] ?? 'Verbindung fehlgeschlagen' ];
		}

		$token = get_transient( Client::TOKEN_TRANSIENT );
		if ( ! $token ) {
			return [ 'ok' => false, 'data' => [], 'message' => 'Kein Access-Token verfuegbar' ];
		}

		$url      = $api_base . '/api/v2/' . $zsoid . '/webinar.json';
		$response = wp_remote_get( $url, [
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $token,
				'Content-Type'  => 'application/json;charset=UTF-8',
			],
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return [ 'ok' => false, 'data' => [], 'message' => $response->get_error_message() ];
		}

		$body     = json_decode( wp_remote_retrieve_body( $response ), true );
		$sessions = $body['session'] ?? [];

		$webinars = [];
		foreach ( $sessions as $s ) {
			$webinars[] = [
				'key'             => $s['meetingKey'] ?? '',
				'topic'           => $s['topic'] ?? '',
				'startTime'       => $s['startTime'] ?? '',
				'endTime'         => $s['endTime'] ?? '',
				'sysId'           => $s['sysId'] ?? '',
				'startTimeMillis' => $s['startTimeMillisec'] ?? 0,
				'endTimeMillis'   => $s['endTimeMillisec'] ?? 0,
			];
		}

		return [ 'ok' => true, 'data' => $webinars ];
	}

	public function register_attendees( string $webinar_key, string $instance_id, array $registrants ): array {
		return $this->client->register_attendees( $webinar_key, $instance_id, $registrants );
	}

	public function get_registrants( string $webinar_key ): array {
		return $this->client->get_registrants( $webinar_key );
	}

	public function get_attendee_report( string $webinar_key ): array {
		return $this->client->get_attendee_report( $webinar_key );
	}

	public function get_name(): string {
		return 'Zoho Meeting';
	}
}
