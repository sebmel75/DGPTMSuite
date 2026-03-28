<?php
/**
 * REST API Endpunkte.
 *
 * Konsolidiert alle REST-Routes unter dem Namespace dgptm-mv/v1.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DGPTM_MV_Rest_API {

	const NS = 'dgptm-mv/v1';

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {
		// Oeffentlich: Aktive Versammlung + Abstimmung
		register_rest_route( self::NS, '/active', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_active' ],
			'permission_callback' => '__return_true',
		] );

		// Ergebnisse (oeffentlich fuer Beamer)
		register_rest_route( self::NS, '/results/(?P<poll_id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_results' ],
			'permission_callback' => '__return_true',
		] );

		// Anwesenheitsliste (eingeloggt)
		register_rest_route( self::NS, '/attendance', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_attendance' ],
			'permission_callback' => function() { return is_user_logged_in(); },
		] );

		// Stimmabgabe-Status pruefen
		register_rest_route( self::NS, '/vote-status/(?P<poll_id>\d+)', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_vote_status' ],
			'permission_callback' => function() { return is_user_logged_in(); },
		] );
	}

	public function get_active( \WP_REST_Request $req ) {
		$assembly = DGPTM_MV_Assembly::get_active_assembly();
		if ( ! $assembly ) {
			return new \WP_REST_Response( [ 'active' => false ], 200 );
		}

		$poll  = DGPTM_MV_Assembly::get_active_poll( $assembly->id );
		$stats = DGPTM_MV_Database::get_assembly_stats( $assembly->id );

		return new \WP_REST_Response( [
			'active'   => true,
			'assembly' => [
				'id'   => $assembly->id,
				'name' => $assembly->name,
			],
			'poll' => $poll ? [
				'id'       => $poll->id,
				'question' => $poll->question,
				'status'   => $poll->status,
			] : null,
			'stats' => $stats,
		], 200 );
	}

	public function get_results( \WP_REST_Request $req ) {
		$poll_id = (int) $req->get_param( 'poll_id' );

		global $wpdb;
		$poll = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . DGPTM_MV_Database::table('polls') . " WHERE id = %d",
			$poll_id
		) );

		if ( ! $poll || ! $poll->results_released ) {
			return new \WP_REST_Response( [ 'released' => false ], 200 );
		}

		$choices = json_decode( $poll->choices, true ) ?: [];
		$counts  = array_fill( 0, count( $choices ), 0 );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT choice_index, COUNT(*) AS cnt FROM " . DGPTM_MV_Database::table('votes') .
			" WHERE poll_id = %d GROUP BY choice_index",
			$poll_id
		) );

		foreach ( $rows as $r ) {
			if ( isset( $counts[ $r->choice_index ] ) ) {
				$counts[ $r->choice_index ] = (int) $r->cnt;
			}
		}

		return new \WP_REST_Response( [
			'released' => true,
			'question' => $poll->question,
			'choices'  => $choices,
			'votes'    => $counts,
			'total'    => array_sum( $counts ),
		], 200 );
	}

	public function get_attendance( \WP_REST_Request $req ) {
		$assembly = DGPTM_MV_Assembly::get_active_assembly();
		if ( ! $assembly ) {
			return new \WP_REST_Response( [ 'ok' => false ], 200 );
		}

		$stats = DGPTM_MV_Database::get_assembly_stats( $assembly->id );
		return new \WP_REST_Response( [ 'ok' => true, 'stats' => $stats ], 200 );
	}

	public function get_vote_status( \WP_REST_Request $req ) {
		$poll_id = (int) $req->get_param( 'poll_id' );
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return new \WP_REST_Response( [ 'voted' => false, 'eligible' => false ], 200 );
		}

		global $wpdb;
		$voted = (bool) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . DGPTM_MV_Database::table('vote_receipts') .
			" WHERE poll_id = %d AND user_id = %d",
			$poll_id, $user_id
		) );

		return new \WP_REST_Response( [
			'voted'    => $voted,
			'eligible' => DGPTM_Mitgliederversammlung::is_eligible_voter( $user_id ),
		], 200 );
	}
}
