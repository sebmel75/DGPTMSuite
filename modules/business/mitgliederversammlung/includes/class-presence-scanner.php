<?php
/**
 * Praesenz-Scanner fuer Badge/QR-Code Check-in.
 *
 * Unterstuetzt:
 * - USB-Badge-Scanner (HID-Geraet)
 * - CRM-Ticket-Validierung
 * - Manuelle Namenssuche
 * - Direkte Anwesenheitserfassung
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DGPTM_MV_Scanner {

	public function __construct() {
		// REST Route fuer Praesenz-Toggle
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ], 5 );
	}

	public function register_rest_routes() {
		register_rest_route( 'dgptm-mv/v1', '/scanner/checkin', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rest_scanner_checkin' ],
			'permission_callback' => function() { return is_user_logged_in(); },
		] );
	}

	/**
	 * Shortcode: Praesenz-Scanner.
	 */
	public function shortcode_scanner( $atts ) {
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			return '<p>Keine Berechtigung.</p>';
		}

		wp_enqueue_style( 'dgptm-mv-frontend' );
		wp_enqueue_script( 'dgptm-mv-scanner' );

		$assembly = DGPTM_MV_Assembly::get_active_assembly();
		if ( ! $assembly ) {
			return '<div class="dgptm-mv-wrap"><div class="dgptm-mv-notice">Keine aktive Versammlung.</div></div>';
		}

		$atts = shortcode_atts( [
			'use_crm' => '1',
			'save_on' => 'green,yellow',
		], $atts );

		ob_start();
		?>
		<div class="dgptm-mv-scanner-wrap" data-assembly-id="<?php echo (int) $assembly->id; ?>"
			 data-usecrm="<?php echo esc_attr( $atts['use_crm'] ); ?>"
			 data-saveon="<?php echo esc_attr( $atts['save_on'] ); ?>">

			<div class="dgptm-mv-scanner-flash"></div>

			<div class="dgptm-mv-scanner-header">
				<h2><?php echo esc_html( $assembly->name ); ?></h2>
				<p>Anwesenheitserfassung</p>
			</div>

			<div class="dgptm-mv-scanner-input-area">
				<input type="text" class="dgptm-mv-scan-input" placeholder="Badge scannen & Enter" autofocus autocomplete="one-time-code">
			</div>

			<div class="dgptm-mv-scanner-result">
				<div class="dgptm-mv-scanner-info" aria-live="polite"></div>
				<div class="dgptm-mv-scanner-sub"></div>
			</div>

			<div class="dgptm-mv-scanner-actions">
				<button class="dgptm-mv-btn" id="dgptm-mv-manual-search-btn">Manuelle Suche</button>
			</div>

			<!-- Manuelle Suche Modal -->
			<div id="dgptm-mv-search-modal" class="dgptm-mv-modal" style="display:none;">
				<div class="dgptm-mv-modal-content">
					<div class="dgptm-mv-modal-header">
						<h3>Mitglied suchen</h3>
						<button class="dgptm-mv-modal-close">&times;</button>
					</div>
					<div class="dgptm-mv-modal-body">
						<input type="text" id="dgptm-mv-search-input" placeholder="Name oder Mitgliedsnummer...">
						<div id="dgptm-mv-search-results"></div>
					</div>
				</div>
			</div>

			<!-- Letzte Eintraege -->
			<div class="dgptm-mv-scanner-history">
				<h3>Letzte Check-ins</h3>
				<div id="dgptm-mv-scanner-list"></div>
			</div>

			<!-- Live-Statistik -->
			<div class="dgptm-mv-scanner-stats" id="dgptm-mv-scanner-stats"></div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * REST: Scanner Check-in.
	 */
	public function rest_scanner_checkin( \WP_REST_Request $req ) {
		$assembly = DGPTM_MV_Assembly::get_active_assembly();
		if ( ! $assembly ) {
			return new \WP_REST_Response( [ 'ok' => false, 'error' => 'Keine aktive Versammlung' ], 200 );
		}

		$params = $req->get_json_params();
		$name          = sanitize_text_field( $params['name'] ?? '' );
		$email         = sanitize_email( $params['email'] ?? '' );
		$member_no     = sanitize_text_field( $params['member_no'] ?? '' );
		$member_status = sanitize_text_field( $params['member_status'] ?? '' );
		$scan_code     = sanitize_text_field( $params['scan_code'] ?? '' );
		$user_id       = (int) ( $params['user_id'] ?? 0 );

		// Versuche User via E-Mail oder Mitgliedsnummer zu finden
		if ( ! $user_id && $email ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) $user_id = $user->ID;
		}

		if ( ! $user_id && $member_no ) {
			$users = get_users( [
				'meta_key'   => 'dgptm_vote_zoho_mitgliedsnr',
				'meta_value' => $member_no,
				'number'     => 1,
			] );
			if ( ! empty( $users ) ) $user_id = $users[0]->ID;
		}

		// Stimmberechtigung pruefen
		$is_eligible = 0;
		if ( $user_id ) {
			$is_eligible = DGPTM_Mitgliederversammlung::is_eligible_voter( $user_id ) ? 1 : 0;
		} elseif ( stripos( $member_status, 'ordentlich' ) !== false ) {
			$is_eligible = 1;
		}

		global $wpdb;

		// Duplikat-Check
		$exists = null;
		if ( $user_id ) {
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM " . DGPTM_MV_Database::table('attendance') .
				" WHERE assembly_id = %d AND user_id = %d",
				$assembly->id, $user_id
			) );
		} elseif ( $member_no ) {
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM " . DGPTM_MV_Database::table('attendance') .
				" WHERE assembly_id = %d AND member_no = %s",
				$assembly->id, $member_no
			) );
		}

		if ( $exists ) {
			return new \WP_REST_Response( [
				'ok'      => true,
				'status'  => 'already',
				'message' => $name . ' bereits eingecheckt',
				'name'    => $name,
			], 200 );
		}

		$wpdb->insert( DGPTM_MV_Database::table('attendance'), [
			'assembly_id'       => $assembly->id,
			'user_id'           => $user_id,
			'member_name'       => $name,
			'member_email'      => $email,
			'member_no'         => $member_no,
			'member_status'     => $member_status,
			'attendance_type'   => 'presence',
			'checked_in_at'     => current_time( 'mysql' ),
			'is_eligible_voter' => $is_eligible,
			'scan_code'         => $scan_code,
			'source'            => 'scanner',
		] );

		$stats = DGPTM_MV_Database::get_assembly_stats( $assembly->id );

		return new \WP_REST_Response( [
			'ok'       => true,
			'status'   => 'new',
			'message'  => $name . ' eingecheckt',
			'name'     => $name,
			'eligible' => (bool) $is_eligible,
			'stats'    => $stats,
		], 200 );
	}
}
