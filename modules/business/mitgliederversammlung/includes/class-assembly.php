<?php
/**
 * Versammlungs-Management.
 *
 * Verwaltet Mitgliederversammlungen, Tagesordnung und Steuerung.
 * Satzung §7: Ordentliche MV jaehrlich, ausserordentliche auf Vorstandsbeschluss.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DGPTM_MV_Assembly {

	public function __construct() {
		// AJAX-Handler fuer Manager
		add_action( 'wp_ajax_dgptm_mv_create_assembly', [ $this, 'ajax_create_assembly' ] );
		add_action( 'wp_ajax_dgptm_mv_update_assembly', [ $this, 'ajax_update_assembly' ] );
		add_action( 'wp_ajax_dgptm_mv_toggle_assembly_status', [ $this, 'ajax_toggle_status' ] );
		add_action( 'wp_ajax_dgptm_mv_get_assembly_details', [ $this, 'ajax_get_details' ] );

		// Tagesordnung
		add_action( 'wp_ajax_dgptm_mv_add_agenda_item', [ $this, 'ajax_add_agenda_item' ] );
		add_action( 'wp_ajax_dgptm_mv_update_agenda_item', [ $this, 'ajax_update_agenda_item' ] );
		add_action( 'wp_ajax_dgptm_mv_delete_agenda_item', [ $this, 'ajax_delete_agenda_item' ] );

		// Polls
		add_action( 'wp_ajax_dgptm_mv_create_poll', [ $this, 'ajax_create_poll' ] );
		add_action( 'wp_ajax_dgptm_mv_activate_poll', [ $this, 'ajax_activate_poll' ] );
		add_action( 'wp_ajax_dgptm_mv_stop_poll', [ $this, 'ajax_stop_poll' ] );
		add_action( 'wp_ajax_dgptm_mv_release_results', [ $this, 'ajax_release_results' ] );
		add_action( 'wp_ajax_dgptm_mv_delete_poll', [ $this, 'ajax_delete_poll' ] );
		add_action( 'wp_ajax_dgptm_mv_set_beamer_state', [ $this, 'ajax_set_beamer_state' ] );
	}

	/**
	 * Gibt die aktive Versammlung zurueck.
	 */
	public static function get_active_assembly() {
		global $wpdb;
		return $wpdb->get_row(
			"SELECT * FROM " . DGPTM_MV_Database::table('assemblies') .
			" WHERE status = 'active' ORDER BY assembly_date DESC LIMIT 1"
		);
	}

	/**
	 * Gibt alle Versammlungen zurueck.
	 */
	public static function get_all_assemblies() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT * FROM " . DGPTM_MV_Database::table('assemblies') .
			" ORDER BY assembly_date DESC"
		);
	}

	/**
	 * Gibt Tagesordnungspunkte einer Versammlung zurueck.
	 */
	public static function get_agenda_items( $assembly_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . DGPTM_MV_Database::table('agenda_items') .
			" WHERE assembly_id = %d ORDER BY sort_order ASC",
			$assembly_id
		) );
	}

	/**
	 * Gibt Polls einer Versammlung zurueck.
	 */
	public static function get_polls( $assembly_id ) {
		global $wpdb;
		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM " . DGPTM_MV_Database::table('polls') .
			" WHERE assembly_id = %d ORDER BY created_at ASC",
			$assembly_id
		) );
	}

	/**
	 * Gibt die aktive Abstimmung zurueck.
	 */
	public static function get_active_poll( $assembly_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . DGPTM_MV_Database::table('polls') .
			" WHERE assembly_id = %d AND status = 'active' LIMIT 1",
			$assembly_id
		) );
	}

	// --- Manager-Shortcode ---

	public function shortcode_manager( $atts ) {
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			return '<p>Keine Berechtigung. Nur Versammlungsleiter koennen auf diesen Bereich zugreifen.</p>';
		}

		wp_enqueue_style( 'dgptm-mv-frontend' );
		wp_enqueue_script( 'dgptm-mv-voting' );

		$assembly = self::get_active_assembly();
		$assemblies = self::get_all_assemblies();

		ob_start();
		?>
		<div id="dgptm-mv-manager" class="dgptm-mv-wrap">
			<h2>Mitgliederversammlung - Steuerung</h2>

			<?php if ( $assembly ) : ?>
				<?php $this->render_active_assembly( $assembly ); ?>
			<?php else : ?>
				<div class="dgptm-mv-notice">Keine aktive Versammlung. Erstellen Sie eine neue.</div>
			<?php endif; ?>

			<hr>
			<h3>Neue Versammlung anlegen</h3>
			<form id="dgptm-mv-create-form" class="dgptm-mv-form">
				<?php wp_nonce_field( 'dgptm_mv_nonce', 'nonce' ); ?>
				<div class="dgptm-mv-field">
					<label>Bezeichnung</label>
					<input type="text" name="name" required placeholder="z.B. Ordentliche MV 2026">
				</div>
				<div class="dgptm-mv-field">
					<label>Datum</label>
					<input type="datetime-local" name="assembly_date" required>
				</div>
				<div class="dgptm-mv-field">
					<label>Ort</label>
					<input type="text" name="location" placeholder="z.B. Leipzig, Kongresshalle">
				</div>
				<div class="dgptm-mv-field">
					<label>
						<input type="checkbox" name="is_hybrid" value="1" checked>
						Hybride Versammlung (Praesenz + Online)
					</label>
				</div>
				<button type="submit" class="dgptm-mv-btn primary">Versammlung anlegen</button>
			</form>

			<?php if ( ! empty( $assemblies ) ) : ?>
				<hr>
				<h3>Alle Versammlungen</h3>
				<table class="dgptm-mv-table">
					<thead>
						<tr>
							<th>Name</th>
							<th>Datum</th>
							<th>Status</th>
							<th>Aktionen</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $assemblies as $a ) : ?>
						<tr>
							<td><?php echo esc_html( $a->name ); ?></td>
							<td><?php echo esc_html( wp_date( 'd.m.Y H:i', strtotime( $a->assembly_date ) ) ); ?></td>
							<td><span class="dgptm-mv-badge <?php echo esc_attr( $a->status ); ?>"><?php echo esc_html( ucfirst( $a->status ) ); ?></span></td>
							<td>
								<?php if ( $a->status === 'planned' ) : ?>
									<button class="dgptm-mv-btn small" onclick="dgptmMV.toggleAssemblyStatus(<?php echo (int) $a->id; ?>, 'active')">Aktivieren</button>
								<?php elseif ( $a->status === 'active' ) : ?>
									<button class="dgptm-mv-btn small danger" onclick="dgptmMV.toggleAssemblyStatus(<?php echo (int) $a->id; ?>, 'closed')">Schliessen</button>
								<?php endif; ?>
							</td>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_active_assembly( $assembly ) {
		$stats  = DGPTM_MV_Database::get_assembly_stats( $assembly->id );
		$polls  = self::get_polls( $assembly->id );
		$active = self::get_active_poll( $assembly->id );
		$agenda = self::get_agenda_items( $assembly->id );
		?>
		<div class="dgptm-mv-active-assembly">
			<div class="dgptm-mv-header">
				<h3><?php echo esc_html( $assembly->name ); ?></h3>
				<span class="dgptm-mv-badge active">AKTIV</span>
			</div>

			<div class="dgptm-mv-stats">
				<div class="stat"><strong><?php echo $stats['total']; ?></strong><span>Anwesend</span></div>
				<div class="stat"><strong><?php echo $stats['eligible']; ?></strong><span>Stimmberechtigt</span></div>
				<div class="stat"><strong><?php echo $stats['presence']; ?></strong><span>Praesenz</span></div>
				<div class="stat"><strong><?php echo $stats['online']; ?></strong><span>Online</span></div>
			</div>

			<!-- Tagesordnung -->
			<div class="dgptm-mv-section">
				<h4>Tagesordnung</h4>
				<?php if ( ! empty( $agenda ) ) : ?>
					<ol class="dgptm-mv-agenda">
					<?php foreach ( $agenda as $item ) : ?>
						<li class="<?php echo esc_attr( $item->status ); ?>">
							<strong><?php echo esc_html( $item->title ); ?></strong>
							<span class="dgptm-mv-badge small <?php echo esc_attr( $item->item_type ); ?>"><?php echo esc_html( $item->item_type ); ?></span>
						</li>
					<?php endforeach; ?>
					</ol>
				<?php endif; ?>
				<form id="dgptm-mv-add-agenda" class="dgptm-mv-inline-form">
					<input type="hidden" name="assembly_id" value="<?php echo (int) $assembly->id; ?>">
					<input type="text" name="title" placeholder="TOP hinzufuegen..." required>
					<select name="item_type">
						<option value="discussion">Besprechung</option>
						<option value="report">Bericht</option>
						<option value="vote">Abstimmung</option>
						<option value="election">Wahl</option>
					</select>
					<button type="submit" class="dgptm-mv-btn small">+</button>
				</form>
			</div>

			<!-- Abstimmungen -->
			<div class="dgptm-mv-section">
				<h4>Abstimmungen</h4>

				<?php if ( $active ) : ?>
					<div class="dgptm-mv-active-poll">
						<span class="dgptm-mv-badge active pulse">LIVE</span>
						<strong><?php echo esc_html( $active->question ); ?></strong>
						<button class="dgptm-mv-btn small danger" onclick="dgptmMV.stopPoll(<?php echo (int) $active->id; ?>)">Abstimmung beenden</button>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $polls ) ) : ?>
					<table class="dgptm-mv-table compact">
						<thead>
							<tr>
								<th>Frage</th>
								<th>Typ</th>
								<th>Mehrheit</th>
								<th>Status</th>
								<th>Stimmen</th>
								<th>Aktionen</th>
							</tr>
						</thead>
						<tbody>
						<?php foreach ( $polls as $poll ) :
							$vote_count = $this->get_vote_count( $poll->id );
							$majority_labels = [
								'simple'         => 'Einfach',
								'three_quarters' => '3/4',
								'absolute'       => 'Absolut (>50%)',
							];
							$type_labels = [
								'simple'   => 'Abstimmung',
								'election' => 'Wahl',
								'satzung'  => 'Satzungsaenderung',
							];
						?>
							<tr data-poll-id="<?php echo (int) $poll->id; ?>">
								<td><?php echo esc_html( $poll->question ); ?></td>
								<td><?php echo esc_html( $type_labels[ $poll->poll_type ] ?? $poll->poll_type ); ?></td>
								<td><?php echo esc_html( $majority_labels[ $poll->required_majority ] ?? $poll->required_majority ); ?></td>
								<td><span class="dgptm-mv-badge <?php echo esc_attr( $poll->status ); ?>"><?php echo esc_html( $poll->status ); ?></span></td>
								<td><?php echo $vote_count; ?></td>
								<td>
									<?php if ( $poll->status === 'prepared' ) : ?>
										<button class="dgptm-mv-btn small" onclick="dgptmMV.activatePoll(<?php echo (int) $poll->id; ?>)">Starten</button>
										<button class="dgptm-mv-btn small danger" onclick="dgptmMV.deletePoll(<?php echo (int) $poll->id; ?>)">Loeschen</button>
									<?php elseif ( $poll->status === 'active' ) : ?>
										<button class="dgptm-mv-btn small danger" onclick="dgptmMV.stopPoll(<?php echo (int) $poll->id; ?>)">Beenden</button>
									<?php elseif ( $poll->status === 'stopped' ) : ?>
										<?php if ( ! $poll->results_released ) : ?>
											<button class="dgptm-mv-btn small" onclick="dgptmMV.releaseResults(<?php echo (int) $poll->id; ?>)">Ergebnis freigeben</button>
										<?php else : ?>
											<span class="dgptm-mv-badge released">Freigegeben</span>
										<?php endif; ?>
										<button class="dgptm-mv-btn small" onclick="dgptmMV.showOnBeamer(<?php echo (int) $poll->id; ?>)">Beamer</button>
									<?php endif; ?>
								</td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>

				<!-- Neue Abstimmung -->
				<details class="dgptm-mv-details">
					<summary>Neue Abstimmung anlegen</summary>
					<form id="dgptm-mv-create-poll" class="dgptm-mv-form">
						<input type="hidden" name="assembly_id" value="<?php echo (int) $assembly->id; ?>">
						<div class="dgptm-mv-field">
							<label>Frage / Wahlvorschlag</label>
							<input type="text" name="question" required placeholder="z.B. Entlastung des Vorstandes">
						</div>
						<div class="dgptm-mv-field">
							<label>Beschreibung (optional)</label>
							<textarea name="description" rows="2"></textarea>
						</div>
						<div class="dgptm-mv-field">
							<label>Typ</label>
							<select name="poll_type" onchange="dgptmMV.onPollTypeChange(this)">
								<option value="simple">Abstimmung (Ja/Nein/Enthaltung)</option>
								<option value="election">Wahl (Personen)</option>
								<option value="satzung">Satzungsaenderung (3/4 Mehrheit)</option>
							</select>
						</div>
						<div class="dgptm-mv-field" id="dgptm-mv-choices-field">
							<label>Antwortmoeglichkeiten (eine pro Zeile)</label>
							<textarea name="choices" rows="4" placeholder="Ja&#10;Nein&#10;Enthaltung">Ja
Nein
Enthaltung</textarea>
						</div>
						<div class="dgptm-mv-field">
							<label>Max. Stimmen pro Person</label>
							<input type="number" name="max_choices" value="1" min="1" max="20">
						</div>
						<div class="dgptm-mv-field">
							<label>
								<input type="checkbox" name="is_secret" value="1" checked>
								Geheime Abstimmung (Satzung §7 Abs. 7)
							</label>
						</div>
						<button type="submit" class="dgptm-mv-btn primary">Abstimmung anlegen</button>
					</form>
				</details>
			</div>

			<!-- Beamer-Steuerung -->
			<div class="dgptm-mv-section">
				<h4>Beamer-Steuerung</h4>
				<div class="dgptm-mv-beamer-controls">
					<button class="dgptm-mv-btn" onclick="dgptmMV.setBeamerState('idle')">Warte-Bildschirm</button>
					<button class="dgptm-mv-btn" onclick="dgptmMV.setBeamerState('live')">Live-Abstimmung</button>
					<button class="dgptm-mv-btn" onclick="dgptmMV.setBeamerState('results_all')">Alle Ergebnisse</button>
					<label>
						<input type="checkbox" id="dgptm-mv-beamer-qr" onchange="dgptmMV.toggleBeamerQR(this.checked)">
						QR-Code anzeigen
					</label>
				</div>
			</div>
		</div>
		<?php
	}

	private function get_vote_count( $poll_id ) {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . DGPTM_MV_Database::table('vote_receipts') . " WHERE poll_id = %d",
			$poll_id
		) );
	}

	// --- AJAX Handlers ---

	public function ajax_create_assembly() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_send_json_error( 'Keine Berechtigung.' );
		}

		global $wpdb;
		$name = sanitize_text_field( $_POST['name'] ?? '' );
		$date = sanitize_text_field( $_POST['assembly_date'] ?? '' );
		$location = sanitize_text_field( $_POST['location'] ?? '' );
		$is_hybrid = ! empty( $_POST['is_hybrid'] ) ? 1 : 0;

		if ( empty( $name ) || empty( $date ) ) {
			wp_send_json_error( 'Name und Datum sind Pflichtfelder.' );
		}

		$wpdb->insert( DGPTM_MV_Database::table('assemblies'), [
			'name'          => $name,
			'assembly_date' => $date,
			'location'      => $location,
			'is_hybrid'     => $is_hybrid,
			'status'        => 'planned',
			'created_at'    => current_time( 'mysql' ),
			'created_by'    => get_current_user_id(),
		] );

		wp_send_json_success( [ 'id' => $wpdb->insert_id, 'message' => 'Versammlung angelegt.' ] );
	}

	public function ajax_update_assembly() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_send_json_error( 'Keine Berechtigung.' );
		}

		global $wpdb;
		$id = (int) ( $_POST['id'] ?? 0 );
		$data = [];

		if ( isset( $_POST['name'] ) ) $data['name'] = sanitize_text_field( $_POST['name'] );
		if ( isset( $_POST['location'] ) ) $data['location'] = sanitize_text_field( $_POST['location'] );
		if ( isset( $_POST['zoom_meeting_id'] ) ) $data['zoom_meeting_id'] = preg_replace( '/\D/', '', $_POST['zoom_meeting_id'] );
		if ( isset( $_POST['zoom_kind'] ) ) $data['zoom_kind'] = sanitize_text_field( $_POST['zoom_kind'] );
		if ( isset( $_POST['logo_url'] ) ) $data['logo_url'] = esc_url_raw( $_POST['logo_url'] );

		if ( empty( $data ) || ! $id ) {
			wp_send_json_error( 'Ungueltige Parameter.' );
		}

		$wpdb->update( DGPTM_MV_Database::table('assemblies'), $data, [ 'id' => $id ] );
		wp_send_json_success( 'Aktualisiert.' );
	}

	public function ajax_toggle_status() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_send_json_error( 'Keine Berechtigung.' );
		}

		global $wpdb;
		$id     = (int) ( $_POST['id'] ?? 0 );
		$status = sanitize_text_field( $_POST['status'] ?? '' );

		if ( ! in_array( $status, [ 'planned', 'active', 'closed', 'archived' ], true ) ) {
			wp_send_json_error( 'Ungueltiger Status.' );
		}

		// Nur eine aktive Versammlung gleichzeitig
		if ( $status === 'active' ) {
			$wpdb->update(
				DGPTM_MV_Database::table('assemblies'),
				[ 'status' => 'closed' ],
				[ 'status' => 'active' ]
			);
		}

		$data = [ 'status' => $status ];
		if ( $status === 'closed' ) {
			$data['closed_at'] = current_time( 'mysql' );
		}

		$wpdb->update( DGPTM_MV_Database::table('assemblies'), $data, [ 'id' => $id ] );
		wp_send_json_success( 'Status geaendert.' );
	}

	public function ajax_get_details() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_send_json_error( 'Keine Berechtigung.' );
		}

		$id = (int) ( $_POST['id'] ?? 0 );
		global $wpdb;

		$assembly = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . DGPTM_MV_Database::table('assemblies') . " WHERE id = %d", $id
		) );

		if ( ! $assembly ) {
			wp_send_json_error( 'Nicht gefunden.' );
		}

		$stats  = DGPTM_MV_Database::get_assembly_stats( $id );
		$polls  = self::get_polls( $id );
		$agenda = self::get_agenda_items( $id );

		wp_send_json_success( [
			'assembly' => $assembly,
			'stats'    => $stats,
			'polls'    => $polls,
			'agenda'   => $agenda,
		] );
	}

	public function ajax_add_agenda_item() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_send_json_error( 'Keine Berechtigung.' );
		}

		global $wpdb;
		$assembly_id = (int) ( $_POST['assembly_id'] ?? 0 );
		$title       = sanitize_text_field( $_POST['title'] ?? '' );
		$item_type   = sanitize_text_field( $_POST['item_type'] ?? 'discussion' );

		if ( ! $assembly_id || empty( $title ) ) {
			wp_send_json_error( 'Pflichtfelder fehlen.' );
		}

		$max_order = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT MAX(sort_order) FROM " . DGPTM_MV_Database::table('agenda_items') . " WHERE assembly_id = %d",
			$assembly_id
		) );

		$wpdb->insert( DGPTM_MV_Database::table('agenda_items'), [
			'assembly_id' => $assembly_id,
			'title'       => $title,
			'item_type'   => $item_type,
			'sort_order'  => $max_order + 1,
			'status'      => 'pending',
		] );

		wp_send_json_success( [ 'id' => $wpdb->insert_id ] );
	}

	public function ajax_update_agenda_item() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_send_json_error( 'Keine Berechtigung.' );
		}

		global $wpdb;
		$id   = (int) ( $_POST['id'] ?? 0 );
		$data = [];

		if ( isset( $_POST['title'] ) ) $data['title'] = sanitize_text_field( $_POST['title'] );
		if ( isset( $_POST['status'] ) ) $data['status'] = sanitize_text_field( $_POST['status'] );
		if ( isset( $_POST['sort_order'] ) ) $data['sort_order'] = (int) $_POST['sort_order'];

		if ( $id && ! empty( $data ) ) {
			$wpdb->update( DGPTM_MV_Database::table('agenda_items'), $data, [ 'id' => $id ] );
		}

		wp_send_json_success( 'Aktualisiert.' );
	}

	public function ajax_delete_agenda_item() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_send_json_error( 'Keine Berechtigung.' );
		}

		global $wpdb;
		$id = (int) ( $_POST['id'] ?? 0 );
		$wpdb->delete( DGPTM_MV_Database::table('agenda_items'), [ 'id' => $id ] );
		wp_send_json_success( 'Geloescht.' );
	}

	public function ajax_create_poll() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_send_json_error( 'Keine Berechtigung.' );
		}

		global $wpdb;

		$assembly_id  = (int) ( $_POST['assembly_id'] ?? 0 );
		$question     = sanitize_text_field( $_POST['question'] ?? '' );
		$description  = sanitize_textarea_field( $_POST['description'] ?? '' );
		$poll_type    = sanitize_text_field( $_POST['poll_type'] ?? 'simple' );
		$choices_raw  = sanitize_textarea_field( $_POST['choices'] ?? "Ja\nNein\nEnthaltung" );
		$max_choices  = max( 1, (int) ( $_POST['max_choices'] ?? 1 ) );
		$is_secret    = ! empty( $_POST['is_secret'] ) ? 1 : 0;

		if ( ! $assembly_id || empty( $question ) ) {
			wp_send_json_error( 'Pflichtfelder fehlen.' );
		}

		$choices = array_values( array_filter( array_map( 'trim', explode( "\n", $choices_raw ) ) ) );
		if ( count( $choices ) < 2 ) {
			wp_send_json_error( 'Mindestens 2 Antwortmoeglichkeiten erforderlich.' );
		}

		// Mehrheit automatisch bestimmen nach Satzung §7
		$majority = 'simple';
		if ( $poll_type === 'satzung' ) {
			$majority = 'three_quarters'; // §14: 3/4 Mehrheit
		} elseif ( $poll_type === 'election' ) {
			$majority = 'absolute'; // §7 Abs. 8: >50%
		}

		$wpdb->insert( DGPTM_MV_Database::table('polls'), [
			'assembly_id'       => $assembly_id,
			'question'          => $question,
			'description'       => $description,
			'poll_type'         => $poll_type,
			'choices'           => wp_json_encode( $choices ),
			'max_choices'       => $max_choices,
			'required_majority' => $majority,
			'status'            => 'prepared',
			'is_secret'         => $is_secret,
			'chart_type'        => 'bar',
			'created_at'        => current_time( 'mysql' ),
		] );

		wp_send_json_success( [ 'id' => $wpdb->insert_id, 'message' => 'Abstimmung angelegt.' ] );
	}

	public function ajax_activate_poll() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_send_json_error( 'Keine Berechtigung.' );
		}

		global $wpdb;
		$id = (int) ( $_POST['poll_id'] ?? 0 );
		$poll = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . DGPTM_MV_Database::table('polls') . " WHERE id = %d", $id
		) );

		if ( ! $poll ) {
			wp_send_json_error( 'Abstimmung nicht gefunden.' );
		}

		// Andere aktive Polls der selben Versammlung stoppen
		$wpdb->update(
			DGPTM_MV_Database::table('polls'),
			[ 'status' => 'stopped', 'stopped_at' => current_time( 'mysql' ) ],
			[ 'assembly_id' => $poll->assembly_id, 'status' => 'active' ]
		);

		$wpdb->update(
			DGPTM_MV_Database::table('polls'),
			[ 'status' => 'active', 'started_at' => current_time( 'mysql' ) ],
			[ 'id' => $id ]
		);

		// Beamer auf Live setzen
		update_option( 'dgptm_mv_beamer_state', wp_json_encode( [
			'mode'    => 'live',
			'poll_id' => $id,
		] ) );

		wp_send_json_success( 'Abstimmung gestartet.' );
	}

	public function ajax_stop_poll() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_send_json_error( 'Keine Berechtigung.' );
		}

		global $wpdb;
		$id = (int) ( $_POST['poll_id'] ?? 0 );

		$wpdb->update(
			DGPTM_MV_Database::table('polls'),
			[ 'status' => 'stopped', 'stopped_at' => current_time( 'mysql' ) ],
			[ 'id' => $id ]
		);

		wp_send_json_success( 'Abstimmung beendet.' );
	}

	public function ajax_release_results() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_send_json_error( 'Keine Berechtigung.' );
		}

		global $wpdb;
		$id = (int) ( $_POST['poll_id'] ?? 0 );

		$wpdb->update(
			DGPTM_MV_Database::table('polls'),
			[ 'results_released' => 1, 'in_overall' => 1 ],
			[ 'id' => $id ]
		);

		wp_send_json_success( 'Ergebnis freigegeben.' );
	}

	public function ajax_delete_poll() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_send_json_error( 'Keine Berechtigung.' );
		}

		global $wpdb;
		$id = (int) ( $_POST['poll_id'] ?? 0 );

		$poll = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . DGPTM_MV_Database::table('polls') . " WHERE id = %d", $id
		) );
		if ( $poll && $poll->status === 'prepared' ) {
			$wpdb->delete( DGPTM_MV_Database::table('votes'), [ 'poll_id' => $id ] );
			$wpdb->delete( DGPTM_MV_Database::table('vote_receipts'), [ 'poll_id' => $id ] );
			$wpdb->delete( DGPTM_MV_Database::table('polls'), [ 'id' => $id ] );
			wp_send_json_success( 'Geloescht.' );
		} else {
			wp_send_json_error( 'Nur vorbereitete Abstimmungen koennen geloescht werden.' );
		}
	}

	public function ajax_set_beamer_state() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_send_json_error( 'Keine Berechtigung.' );
		}

		$state = [];
		$state['mode']       = sanitize_text_field( $_POST['mode'] ?? 'idle' );
		$state['poll_id']    = (int) ( $_POST['poll_id'] ?? 0 );
		$state['qr_visible'] = ! empty( $_POST['qr_visible'] );

		if ( ! empty( $_POST['assembly_id'] ) ) {
			$state['assembly_id'] = (int) $_POST['assembly_id'];
		}

		update_option( 'dgptm_mv_beamer_state', wp_json_encode( $state ) );
		wp_send_json_success( 'Beamer-Status aktualisiert.' );
	}
}
