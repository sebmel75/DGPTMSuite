<?php
/**
 * Zoho CRM API Client fuer Sync-Operationen
 *
 * Wrapper um DGPTM_Zoho_Auth fuer Event-/Teilnahme-/Contact-Operationen.
 *
 * @package EventTracker\Sync
 * @since 2.3.0
 */

namespace EventTracker\Sync;

use EventTracker\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CrmClient {

	const API_BASE = 'https://www.zohoapis.eu/crm/v6';

	/** @var \DGPTM_Zoho_Auth|null */
	private $auth;

	public function __construct() {
		if ( class_exists( 'DGPTM_Zoho_Auth' ) ) {
			$this->auth = \DGPTM_Zoho_Auth::get_instance();
		}
	}

	/**
	 * Prueft ob CRM-Zugriff verfuegbar ist.
	 */
	public function is_available(): bool {
		return $this->auth !== null;
	}

	/**
	 * COQL-Query ausfuehren.
	 *
	 * @param string $query COQL SELECT query.
	 * @return array Records oder leeres Array.
	 */
	public function coql_query( string $query ): array {
		$response = $this->auth->request( 'POST', self::API_BASE . '/coql', [
			'body' => wp_json_encode( [ 'select_query' => $query ] ),
		] );

		if ( is_wp_error( $response ) ) {
			Helpers::log( 'CRM COQL Fehler: ' . $response->get_error_message(), 'error' );
			return [];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['data'] ?? [];
	}

	/**
	 * Record in einem Modul erstellen.
	 *
	 * @param string $module Modul-API-Name.
	 * @param array  $data   Feld-Werte.
	 * @return string|false Record-ID oder false.
	 */
	public function create_record( string $module, array $data ) {
		$response = $this->auth->request( 'POST', self::API_BASE . '/' . $module, [
			'body' => wp_json_encode( [ 'data' => [ $data ] ] ),
		] );

		if ( is_wp_error( $response ) ) {
			Helpers::log( sprintf( 'CRM Create %s Fehler: %s', $module, $response->get_error_message() ), 'error' );
			return false;
		}

		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$record = $body['data'][0] ?? [];

		if ( ( $record['code'] ?? '' ) === 'SUCCESS' ) {
			return $record['details']['id'] ?? false;
		}

		Helpers::log( sprintf( 'CRM Create %s fehlgeschlagen: %s', $module, wp_json_encode( $record ) ), 'error' );
		return false;
	}

	/**
	 * Records in einem Modul aktualisieren (Batch, max 100).
	 *
	 * @param string $module  Modul-API-Name.
	 * @param array  $records Array von ['id' => '...', 'field' => 'value'].
	 * @return int Anzahl erfolgreicher Updates.
	 */
	public function update_records( string $module, array $records ): int {
		if ( empty( $records ) ) {
			return 0;
		}

		$response = $this->auth->request( 'PUT', self::API_BASE . '/' . $module, [
			'body' => wp_json_encode( [ 'data' => $records ] ),
		] );

		if ( is_wp_error( $response ) ) {
			Helpers::log( sprintf( 'CRM Update %s Fehler: %s', $module, $response->get_error_message() ), 'error' );
			return 0;
		}

		$body    = json_decode( wp_remote_retrieve_body( $response ), true );
		$success = 0;
		foreach ( $body['data'] ?? [] as $r ) {
			if ( ( $r['code'] ?? '' ) === 'SUCCESS' ) {
				$success++;
			}
		}
		return $success;
	}

	/**
	 * Contact per E-Mail suchen (alle 4 Mail-Felder).
	 *
	 * @param string $email E-Mail-Adresse.
	 * @return array|null Contact-Record oder null.
	 */
	public function find_contact_by_email( string $email ) {
		$email = strtolower( trim( $email ) );
		if ( ! $email ) {
			return null;
		}

		// 1. Native E-Mail-Suche (durchsucht Email, Secondary_Email, Third_Email)
		$response = $this->auth->request( 'GET', self::API_BASE . '/Contacts/search?email=' . urlencode( $email ) );

		if ( ! is_wp_error( $response ) ) {
			$body = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( ! empty( $body['data'][0] ) ) {
				return $body['data'][0];
			}
		}

		// 2. COQL-Fallback fuer Fourth_Email
		$escaped = addslashes( $email );
		$records = $this->coql_query( "SELECT id, First_Name, Last_Name, Email FROM Contacts WHERE Fourth_Email = '{$escaped}' LIMIT 1" );

		return $records[0] ?? null;
	}

	/**
	 * Blueprint-Transition ausfuehren.
	 *
	 * @param string $module        Modul-API-Name.
	 * @param string $record_id     Record-ID.
	 * @param string $transition_id Blueprint-Transition-ID.
	 * @return bool Erfolg.
	 */
	public function execute_blueprint( string $module, string $record_id, string $transition_id ): bool {
		$url  = self::API_BASE . '/' . $module . '/' . $record_id . '/actions/blueprint';
		$body = [
			'blueprint' => [
				[ 'transition_id' => $transition_id, 'data' => (object) [] ],
			],
		];

		$response = $this->auth->request( 'PUT', $url, [
			'body' => wp_json_encode( $body ),
		] );

		if ( is_wp_error( $response ) ) {
			Helpers::log( sprintf( 'Blueprint Fehler %s/%s: %s', $module, $record_id, $response->get_error_message() ), 'error' );
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		return $code >= 200 && $code < 300;
	}

	/**
	 * Blueprint-Transitions fuer einen Record abrufen.
	 *
	 * @param string $module    Modul-API-Name.
	 * @param string $record_id Record-ID.
	 * @return array [ 'transitions' => [ ['id' => '...', 'next_field_value' => '...'] ] ]
	 */
	public function get_blueprint( string $module, string $record_id ): array {
		$url      = self::API_BASE . '/' . $module . '/' . $record_id . '/actions/blueprint';
		$response = $this->auth->request( 'GET', $url );

		if ( is_wp_error( $response ) ) {
			return [];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return $body['blueprint'] ?? [];
	}
}
