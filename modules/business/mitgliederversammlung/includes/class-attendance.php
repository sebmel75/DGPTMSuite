<?php
/**
 * Anwesenheitserfassung.
 *
 * Hybrid: Praesenz (Scanner/manuell) + Online (Zoom-Webhook).
 * Satzung §7 Abs. 1: Jedes ordentliche Mitglied hat eine Stimme.
 * Stimmrecht erfordert Anwesenheit (Check-in).
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DGPTM_MV_Attendance {

	public function __construct() {
		add_action( 'wp_ajax_dgptm_mv_checkin', [ $this, 'ajax_checkin' ] );
		add_action( 'wp_ajax_dgptm_mv_checkout', [ $this, 'ajax_checkout' ] );
		add_action( 'wp_ajax_dgptm_mv_delete_attendee', [ $this, 'ajax_delete_attendee' ] );
		add_action( 'wp_ajax_dgptm_mv_get_attendance_list', [ $this, 'ajax_get_attendance_list' ] );
	}

	/**
	 * Shortcode: Anwesenheitsliste.
	 */
	public function shortcode_attendance( $atts ) {
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			return '<p>Keine Berechtigung.</p>';
		}

		wp_enqueue_style( 'dgptm-mv-frontend' );
		wp_enqueue_script( 'dgptm-mv-voting' );

		$atts = shortcode_atts( [
			'refresh' => '10',
		], $atts );

		$assembly = DGPTM_MV_Assembly::get_active_assembly();
		if ( ! $assembly ) {
			return '<div class="dgptm-mv-wrap"><div class="dgptm-mv-notice">Keine aktive Versammlung.</div></div>';
		}

		$stats = DGPTM_MV_Database::get_assembly_stats( $assembly->id );

		ob_start();
		?>
		<div id="dgptm-mv-attendance" class="dgptm-mv-wrap"
			 data-assembly-id="<?php echo (int) $assembly->id; ?>"
			 data-refresh="<?php echo (int) $atts['refresh']; ?>">

			<h2>Anwesenheitsliste - <?php echo esc_html( $assembly->name ); ?></h2>

			<div class="dgptm-mv-stats" id="dgptm-mv-att-stats">
				<div class="stat"><strong id="att-total"><?php echo $stats['total']; ?></strong><span>Gesamt</span></div>
				<div class="stat"><strong id="att-eligible"><?php echo $stats['eligible']; ?></strong><span>Stimmberechtigt</span></div>
				<div class="stat"><strong id="att-presence"><?php echo $stats['presence']; ?></strong><span>Praesenz</span></div>
				<div class="stat"><strong id="att-online"><?php echo $stats['online']; ?></strong><span>Online</span></div>
			</div>

			<div class="dgptm-mv-toolbar">
				<button class="dgptm-mv-btn small" onclick="dgptmMV.exportAttendance('csv')">CSV Export</button>
				<button class="dgptm-mv-btn small" onclick="dgptmMV.exportAttendance('pdf')">PDF Export</button>
			</div>

			<table class="dgptm-mv-table" id="dgptm-mv-att-table">
				<thead>
					<tr>
						<th>Name</th>
						<th>E-Mail</th>
						<th>Mitgl.-Nr.</th>
						<th>Status</th>
						<th>Art</th>
						<th>Stimmberechtigt</th>
						<th>Check-in</th>
						<th>Aktionen</th>
					</tr>
				</thead>
				<tbody id="dgptm-mv-att-body">
					<tr><td colspan="8">Lade...</td></tr>
				</tbody>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Check-in eines Mitglieds (Praesenz oder manuell).
	 */
	public function ajax_checkin() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );

		$assembly = DGPTM_MV_Assembly::get_active_assembly();
		if ( ! $assembly ) {
			wp_send_json_error( 'Keine aktive Versammlung.' );
		}

		global $wpdb;

		$user_id     = (int) ( $_POST['user_id'] ?? 0 );
		$name        = sanitize_text_field( $_POST['name'] ?? '' );
		$email       = sanitize_email( $_POST['email'] ?? '' );
		$member_no   = sanitize_text_field( $_POST['member_no'] ?? '' );
		$member_status = sanitize_text_field( $_POST['member_status'] ?? '' );
		$att_type    = sanitize_text_field( $_POST['attendance_type'] ?? 'presence' );
		$scan_code   = sanitize_text_field( $_POST['scan_code'] ?? '' );
		$source      = sanitize_text_field( $_POST['source'] ?? 'scanner' );

		if ( empty( $name ) && $user_id ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				$name  = trim( $user->first_name . ' ' . $user->last_name ) ?: $user->display_name;
				$email = $user->user_email;
			}
		}

		if ( empty( $name ) && empty( $member_no ) && ! $user_id ) {
			wp_send_json_error( 'Name, Mitgliedsnummer oder User-ID erforderlich.' );
		}

		// Stimmberechtigung pruefen
		$is_eligible = 0;
		if ( $user_id ) {
			$is_eligible = DGPTM_Mitgliederversammlung::is_eligible_voter( $user_id ) ? 1 : 0;
		} elseif ( stripos( $member_status, 'ordentlich' ) !== false ) {
			$is_eligible = 1;
		}

		// Duplikat-Check
		$exists = false;
		if ( $user_id ) {
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM " . DGPTM_MV_Database::table('attendance') .
				" WHERE assembly_id = %d AND user_id = %d LIMIT 1",
				$assembly->id, $user_id
			) );
		} elseif ( $member_no ) {
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM " . DGPTM_MV_Database::table('attendance') .
				" WHERE assembly_id = %d AND member_no = %s LIMIT 1",
				$assembly->id, $member_no
			) );
		}

		if ( $exists ) {
			wp_send_json_success( [
				'status'  => 'already',
				'message' => $name . ' ist bereits eingecheckt.',
				'name'    => $name,
			] );
			return;
		}

		$wpdb->insert( DGPTM_MV_Database::table('attendance'), [
			'assembly_id'      => $assembly->id,
			'user_id'          => $user_id,
			'member_name'      => $name,
			'member_email'     => $email,
			'member_no'        => $member_no,
			'member_status'    => $member_status,
			'attendance_type'  => $att_type,
			'checked_in_at'    => current_time( 'mysql' ),
			'is_eligible_voter' => $is_eligible,
			'scan_code'        => $scan_code,
			'source'           => $source,
		] );

		wp_send_json_success( [
			'status'  => 'new',
			'message' => $name . ' eingecheckt.',
			'name'    => $name,
			'eligible' => $is_eligible,
			'id'      => $wpdb->insert_id,
		] );
	}

	/**
	 * Check-out.
	 */
	public function ajax_checkout() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );

		global $wpdb;
		$id = (int) ( $_POST['id'] ?? 0 );

		$wpdb->update(
			DGPTM_MV_Database::table('attendance'),
			[ 'checked_out_at' => current_time( 'mysql' ) ],
			[ 'id' => $id ]
		);

		wp_send_json_success( 'Ausgecheckt.' );
	}

	/**
	 * Teilnehmer entfernen.
	 */
	public function ajax_delete_attendee() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_send_json_error( 'Keine Berechtigung.' );
		}

		global $wpdb;
		$id = (int) ( $_POST['id'] ?? 0 );
		$wpdb->delete( DGPTM_MV_Database::table('attendance'), [ 'id' => $id ] );
		wp_send_json_success( 'Entfernt.' );
	}

	/**
	 * Anwesenheitsliste als JSON.
	 */
	public function ajax_get_attendance_list() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );

		$assembly_id = (int) ( $_POST['assembly_id'] ?? 0 );
		if ( ! $assembly_id ) {
			$assembly = DGPTM_MV_Assembly::get_active_assembly();
			$assembly_id = $assembly ? $assembly->id : 0;
		}

		if ( ! $assembly_id ) {
			wp_send_json_error( 'Keine Versammlung.' );
		}

		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . DGPTM_MV_Database::table('attendance') .
			" WHERE assembly_id = %d ORDER BY checked_in_at DESC",
			$assembly_id
		) );

		$stats = DGPTM_MV_Database::get_assembly_stats( $assembly_id );

		$list = [];
		foreach ( $rows as $r ) {
			$list[] = [
				'id'             => (int) $r->id,
				'user_id'        => (int) $r->user_id,
				'name'           => $r->member_name,
				'email'          => $r->member_email,
				'member_no'      => $r->member_no,
				'member_status'  => $r->member_status,
				'type'           => $r->attendance_type,
				'eligible'       => (bool) $r->is_eligible_voter,
				'checked_in'     => $r->checked_in_at,
				'checked_out'    => $r->checked_out_at,
				'source'         => $r->source,
			];
		}

		wp_send_json_success( [
			'attendees' => $list,
			'stats'     => $stats,
		] );
	}

	/**
	 * Check-in via Zoom-Webhook (wird von Zoom-Integration aufgerufen).
	 */
	public static function checkin_from_zoom( $assembly_id, $participant_data ) {
		global $wpdb;

		$email = sanitize_email( $participant_data['email'] ?? '' );
		$name  = sanitize_text_field( $participant_data['user_name'] ?? '' );

		if ( empty( $email ) && empty( $name ) ) {
			return;
		}

		// Versuche User ueber E-Mail zu finden
		$user_id = 0;
		if ( $email ) {
			$user = get_user_by( 'email', $email );
			if ( $user ) {
				$user_id = $user->ID;
			}
		}

		// Duplikat-Check
		$exists = false;
		if ( $user_id ) {
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM " . DGPTM_MV_Database::table('attendance') .
				" WHERE assembly_id = %d AND user_id = %d",
				$assembly_id, $user_id
			) );
		} elseif ( $email ) {
			$exists = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM " . DGPTM_MV_Database::table('attendance') .
				" WHERE assembly_id = %d AND member_email = %s",
				$assembly_id, $email
			) );
		}

		if ( $exists ) {
			return;
		}

		$is_eligible = $user_id ? ( DGPTM_Mitgliederversammlung::is_eligible_voter( $user_id ) ? 1 : 0 ) : 0;

		$wpdb->insert( DGPTM_MV_Database::table('attendance'), [
			'assembly_id'      => $assembly_id,
			'user_id'          => $user_id,
			'member_name'      => $name,
			'member_email'     => $email,
			'attendance_type'  => 'online',
			'checked_in_at'    => current_time( 'mysql' ),
			'is_eligible_voter' => $is_eligible,
			'source'           => 'zoom',
		] );
	}
}
