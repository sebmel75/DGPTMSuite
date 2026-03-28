<?php
/**
 * Zoho CRM Integration fuer Mitglieder-Validierung.
 *
 * Nutzt das crm-abruf Modul fuer OAuth-Token.
 * Prueft Tickets/Mitgliederdaten bei Scanner-Check-in.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DGPTM_MV_CRM {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ], 5 );
	}

	public function register_rest_routes() {
		register_rest_route( 'dgptm-mv/v1', '/ticket-check', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_ticket_check' ],
			'permission_callback' => '__return_true',
		] );

		register_rest_route( 'dgptm-mv/v1', '/member-search', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rest_member_search' ],
			'permission_callback' => function() { return is_user_logged_in(); },
		] );
	}

	/**
	 * OAuth-Token vom crm-abruf Modul holen.
	 */
	public function get_oauth_token() {
		// Versuche verschiedene CRM-Klassen
		$classes = [
			[ 'DGPTM_Zoho_CRM_Hardened', 'get_oauth_token' ],
			[ 'DGPTM_Zoho_Plugin', 'get_oauth_token' ],
			[ 'DGPTM_Mitgliedsantrag', 'get_access_token' ],
		];

		foreach ( $classes as [ $class, $method ] ) {
			if ( class_exists( $class ) ) {
				$instance = $class::get_instance();
				if ( method_exists( $instance, $method ) ) {
					$token = $instance->$method();
					if ( $token && ! is_wp_error( $token ) ) {
						return $token;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Ticket im CRM suchen.
	 */
	public function fetch_ticket( $scan_code ) {
		$token = $this->get_oauth_token();
		if ( ! $token ) {
			return null;
		}

		$url = 'https://www.zohoapis.eu/crm/v2/Ticket/search?criteria=(Name:equals:' . urlencode( $scan_code ) . ')';

		$r = wp_remote_get( $url, [
			'headers' => [ 'Authorization' => 'Zoho-oauthtoken ' . $token ],
			'timeout' => 10,
		] );

		if ( is_wp_error( $r ) ) return null;

		$code = wp_remote_retrieve_response_code( $r );
		if ( $code === 204 || $code !== 200 ) return null;

		$data = json_decode( wp_remote_retrieve_body( $r ), true );
		return $data['data'][0] ?? null;
	}

	/**
	 * Ticket-Status auswerten.
	 */
	public function evaluate_ticket( $ticket ) {
		if ( ! $ticket ) {
			return [
				'ok'     => false,
				'result' => 'red',
				'name'   => '',
				'status' => 'Nicht gefunden',
				'email'  => '',
				'member_no' => '',
				'member_status' => '',
			];
		}

		$name   = '';
		$email  = (string) ( $ticket['Email'] ?? '' );
		$status = (string) ( $ticket['Ticketart'] ?? '' );
		$member_type = (string) ( $ticket['MembershipType'] ?? '' );

		// Name aus TN-Lookup
		$tn = $ticket['TN'] ?? null;
		if ( is_array( $tn ) ) {
			$name = (string) ( $tn['name'] ?? '' );
		} elseif ( is_string( $tn ) ) {
			$name = $tn;
		}

		// Ergebnis-Farbe bestimmen
		$result = 'green';
		if ( empty( $name ) ) {
			$result = 'red';
			$status = 'Kein Name';
		} elseif ( stripos( $status, 'storno' ) !== false || stripos( $status, 'cancel' ) !== false ) {
			$result = 'red';
		} elseif ( stripos( $status, 'frei' ) !== false || stripos( $status, 'gast' ) !== false ) {
			$result = 'yellow';
		}

		return [
			'ok'            => true,
			'result'        => $result,
			'name'          => $name,
			'status'        => $status,
			'email'         => $email,
			'member_no'     => (string) ( $ticket['Name'] ?? '' ),
			'member_status' => $member_type,
		];
	}

	/**
	 * REST: Ticket-Check (Scanner).
	 */
	public function rest_ticket_check( \WP_REST_Request $req ) {
		$scan = sanitize_text_field( $req->get_param( 'scan' ) ?? '' );

		if ( empty( $scan ) ) {
			return new \WP_REST_Response( [
				'ok' => false, 'result' => 'red', 'name' => '', 'status' => 'Kein Code',
			], 200 );
		}

		$ticket = $this->fetch_ticket( $scan );
		$result = $this->evaluate_ticket( $ticket );

		return new \WP_REST_Response( $result, 200 );
	}

	/**
	 * REST: Mitglieder-Suche (manuell).
	 */
	public function rest_member_search( \WP_REST_Request $req ) {
		$search = sanitize_text_field( $req->get_param( 'search' ) ?? '' );

		if ( strlen( $search ) < 2 ) {
			return new \WP_REST_Response( [ 'ok' => false, 'results' => [] ], 200 );
		}

		// WordPress-User-Suche
		$users = get_users( [
			'search'  => '*' . $search . '*',
			'number'  => 20,
			'orderby' => 'display_name',
		] );

		$results = [];
		foreach ( $users as $u ) {
			$name = trim( $u->first_name . ' ' . $u->last_name ) ?: $u->display_name;
			$member_no = get_user_meta( $u->ID, 'dgptm_vote_zoho_mitgliedsnr', true );
			$member_status = get_user_meta( $u->ID, 'dgptm_vote_zoho_mitgliedsart', true );
			$eligible = DGPTM_Mitgliederversammlung::is_eligible_voter( $u->ID );

			$results[] = [
				'user_id'       => $u->ID,
				'name'          => $name,
				'email'         => $u->user_email,
				'member_no'     => $member_no,
				'member_status' => $member_status,
				'eligible'      => $eligible,
			];
		}

		return new \WP_REST_Response( [ 'ok' => true, 'results' => $results ], 200 );
	}
}
