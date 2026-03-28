<?php
/**
 * Anonymes Abstimmungssystem.
 *
 * Satzung §7 Abs. 7: frei, geheim, gleich, persoenlich, unmittelbar.
 * Satzung §7 Abs. 8: Einfache Mehrheit, Stimmenthaltungen = ungueltig.
 *
 * ANONYMITAETSPRINZIP:
 * - Tabelle 'votes': Speichert NUR choice_index + poll_id (KEIN user_id)
 * - Tabelle 'vote_receipts': Speichert NUR user_id + poll_id (KEINE choice)
 * - Beide Inserts passieren in einer Transaktion, aber die Daten sind nicht verknuepfbar
 * - Selbst bei DB-Zugriff kann niemand nachvollziehen, wer was gewaehlt hat
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DGPTM_MV_Voting {

	public function __construct() {
		// Digitale Stimmabgabe
		add_action( 'wp_ajax_dgptm_mv_cast_vote', [ $this, 'ajax_cast_vote' ] );

		// Manuelle Stimmabgabe durch Manager
		add_action( 'wp_ajax_dgptm_mv_cast_manual_vote', [ $this, 'ajax_cast_manual_vote' ] );

		// Polling fuer Frontend
		add_action( 'wp_ajax_dgptm_mv_get_vote_view', [ $this, 'ajax_get_vote_view' ] );
		add_action( 'wp_ajax_nopriv_dgptm_mv_get_vote_view', [ $this, 'ajax_get_vote_view' ] );

		// Ergebnis-Payload fuer Beamer
		add_action( 'wp_ajax_dgptm_mv_get_beamer_payload', [ $this, 'ajax_get_beamer_payload' ] );
		add_action( 'wp_ajax_nopriv_dgptm_mv_get_beamer_payload', [ $this, 'ajax_get_beamer_payload' ] );

		// Manager: Eligible voters fuer manuelle Stimmabgabe
		add_action( 'wp_ajax_dgptm_mv_get_eligible_voters', [ $this, 'ajax_get_eligible_voters' ] );
	}

	/**
	 * Shortcode: Abstimmungs-Interface fuer Mitglieder.
	 */
	public function shortcode_vote( $atts ) {
		wp_enqueue_style( 'dgptm-mv-frontend' );
		wp_enqueue_script( 'dgptm-mv-voting' );

		if ( ! is_user_logged_in() ) {
			return '<div class="dgptm-mv-wrap"><div class="dgptm-mv-notice">Bitte melden Sie sich im Mitgliederbereich an, um an der Abstimmung teilzunehmen.</div></div>';
		}

		$user_id = get_current_user_id();
		$is_eligible = DGPTM_Mitgliederversammlung::is_eligible_voter( $user_id );
		$assembly = DGPTM_MV_Assembly::get_active_assembly();

		if ( ! $assembly ) {
			return '<div class="dgptm-mv-wrap"><div class="dgptm-mv-notice">Derzeit findet keine Mitgliederversammlung statt.</div></div>';
		}

		ob_start();
		?>
		<div id="dgptm-mv-vote" class="dgptm-mv-wrap"
			 data-assembly-id="<?php echo (int) $assembly->id; ?>"
			 data-eligible="<?php echo $is_eligible ? '1' : '0'; ?>">

			<div class="dgptm-mv-vote-header">
				<h2><?php echo esc_html( $assembly->name ); ?></h2>
				<?php if ( $is_eligible ) : ?>
					<span class="dgptm-mv-badge eligible">Stimmberechtigt</span>
				<?php else : ?>
					<span class="dgptm-mv-badge not-eligible">Nicht stimmberechtigt</span>
					<p class="dgptm-mv-hint">Gemaess Satzung §4 sind nur ordentliche Mitglieder stimmberechtigt.</p>
				<?php endif; ?>
			</div>

			<div id="dgptm-mv-vote-content">
				<div class="dgptm-mv-loading">Lade aktuelle Abstimmung...</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Shortcode: Manuelle Stimmabgabe (fuer Mitglieder ohne Smartphone).
	 * Nur fuer Manager zugaenglich.
	 */
	public function shortcode_manual_vote( $atts ) {
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			return '<p>Keine Berechtigung.</p>';
		}

		wp_enqueue_style( 'dgptm-mv-frontend' );
		wp_enqueue_script( 'dgptm-mv-voting' );

		$assembly = DGPTM_MV_Assembly::get_active_assembly();
		if ( ! $assembly ) {
			return '<div class="dgptm-mv-wrap"><div class="dgptm-mv-notice">Keine aktive Versammlung.</div></div>';
		}

		ob_start();
		?>
		<div id="dgptm-mv-manual-vote" class="dgptm-mv-wrap" data-assembly-id="<?php echo (int) $assembly->id; ?>">
			<h2>Manuelle Stimmabgabe</h2>
			<p class="dgptm-mv-hint">
				Fuer Mitglieder ohne Smartphone: Waehlen Sie das Mitglied aus und geben Sie die Stimme in dessen Namen ab.
				Die Stimmabgabe bleibt anonym - es wird nur dokumentiert, dass das Mitglied seine Stimme abgegeben hat.
			</p>

			<div class="dgptm-mv-manual-section">
				<h3>1. Mitglied auswaehlen</h3>
				<div class="dgptm-mv-field">
					<input type="text" id="dgptm-mv-member-search" placeholder="Name oder Mitgliedsnummer eingeben...">
				</div>
				<div id="dgptm-mv-member-results"></div>
				<div id="dgptm-mv-selected-member" style="display:none;">
					<strong>Ausgewaehltes Mitglied:</strong>
					<span id="dgptm-mv-selected-name"></span>
					<input type="hidden" id="dgptm-mv-selected-user-id" value="">
				</div>
			</div>

			<div class="dgptm-mv-manual-section">
				<h3>2. Aktuelle Abstimmung</h3>
				<div id="dgptm-mv-manual-poll-content">
					<div class="dgptm-mv-loading">Lade...</div>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	// --- AJAX: Digitale Stimmabgabe ---

	/**
	 * Anonyme Stimmabgabe.
	 *
	 * SICHERHEIT:
	 * 1. User muss eingeloggt sein
	 * 2. User muss stimmberechtigt sein (ordentliches Mitglied)
	 * 3. User muss zur Versammlung eingecheckt sein
	 * 4. User darf nur einmal pro Abstimmung waehlen (vote_receipt)
	 * 5. Stimme wird OHNE user_id gespeichert (Anonymitaet)
	 */
	public function ajax_cast_vote() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );

		if ( ! is_user_logged_in() ) {
			wp_send_json_error( 'Bitte melden Sie sich an.' );
		}

		$user_id = get_current_user_id();
		$poll_id = (int) ( $_POST['poll_id'] ?? 0 );
		$choices = isset( $_POST['choices'] ) ? array_map( 'intval', (array) $_POST['choices'] ) : [];

		if ( ! $poll_id || empty( $choices ) ) {
			wp_send_json_error( 'Ungueltige Eingabe.' );
		}

		// Pruefe: Stimmberechtigung
		if ( ! DGPTM_Mitgliederversammlung::is_eligible_voter( $user_id ) ) {
			wp_send_json_error( 'Sie sind nicht stimmberechtigt. Nur ordentliche Mitglieder duerfen abstimmen (Satzung §4).' );
		}

		// Pruefe: Abstimmung aktiv
		global $wpdb;
		$poll = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . DGPTM_MV_Database::table('polls') . " WHERE id = %d",
			$poll_id
		) );

		if ( ! $poll || $poll->status !== 'active' ) {
			wp_send_json_error( 'Diese Abstimmung ist nicht mehr aktiv.' );
		}

		// Pruefe: Max choices
		if ( count( $choices ) > (int) $poll->max_choices ) {
			wp_send_json_error( 'Maximal ' . $poll->max_choices . ' Stimmen erlaubt.' );
		}

		// Pruefe: Choices im gueltigen Bereich
		$valid_choices = json_decode( $poll->choices, true );
		foreach ( $choices as $c ) {
			if ( $c < 0 || $c >= count( $valid_choices ) ) {
				wp_send_json_error( 'Ungueltige Auswahl.' );
			}
		}

		// Pruefe: Anwesenheit (eingecheckt zur Versammlung)
		$is_checked_in = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . DGPTM_MV_Database::table('attendance') .
			" WHERE assembly_id = %d AND user_id = %d",
			$poll->assembly_id, $user_id
		) );

		if ( ! $is_checked_in ) {
			wp_send_json_error( 'Sie muessen zuerst als anwesend registriert sein.' );
		}

		// Pruefe: Noch nicht abgestimmt
		$already_voted = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . DGPTM_MV_Database::table('vote_receipts') .
			" WHERE poll_id = %d AND user_id = %d",
			$poll_id, $user_id
		) );

		if ( $already_voted ) {
			wp_send_json_error( 'Sie haben bei dieser Abstimmung bereits Ihre Stimme abgegeben.' );
		}

		// --- ANONYME STIMMABGABE (Transaktion) ---
		$wpdb->query( 'START TRANSACTION' );

		try {
			$now = current_time( 'mysql' );

			// 1. Anonyme Stimme(n) speichern (KEIN user_id!)
			foreach ( $choices as $choice_index ) {
				$wpdb->insert( DGPTM_MV_Database::table('votes'), [
					'poll_id'      => $poll_id,
					'choice_index' => $choice_index,
					'vote_time'    => $now,
				] );
			}

			// 2. Quittung speichern (WER hat gestimmt, NICHT was)
			$wpdb->insert( DGPTM_MV_Database::table('vote_receipts'), [
				'poll_id'     => $poll_id,
				'user_id'     => $user_id,
				'voted_at'    => $now,
				'vote_method' => 'digital',
				'entered_by'  => null,
			] );

			$wpdb->query( 'COMMIT' );
		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			wp_send_json_error( 'Fehler bei der Stimmabgabe. Bitte versuchen Sie es erneut.' );
		}

		wp_send_json_success( [
			'message' => 'Ihre Stimme wurde erfolgreich und anonym erfasst.',
		] );
	}

	/**
	 * Manuelle Stimmabgabe durch Manager (fuer Mitglieder ohne Smartphone).
	 *
	 * Identisches Anonymitaetsprinzip: Die Stimme wird ohne user_id gespeichert,
	 * nur die Quittung vermerkt, DASS das Mitglied gestimmt hat (+ wer es eingegeben hat).
	 */
	public function ajax_cast_manual_vote() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );

		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_send_json_error( 'Keine Berechtigung.' );
		}

		$manager_id = get_current_user_id();
		$voter_id   = (int) ( $_POST['voter_user_id'] ?? 0 );
		$poll_id    = (int) ( $_POST['poll_id'] ?? 0 );
		$choices    = isset( $_POST['choices'] ) ? array_map( 'intval', (array) $_POST['choices'] ) : [];

		if ( ! $voter_id || ! $poll_id || empty( $choices ) ) {
			wp_send_json_error( 'Ungueltige Eingabe.' );
		}

		// Pruefe: Mitglied stimmberechtigt
		if ( ! DGPTM_Mitgliederversammlung::is_eligible_voter( $voter_id ) ) {
			wp_send_json_error( 'Dieses Mitglied ist nicht stimmberechtigt.' );
		}

		global $wpdb;
		$poll = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . DGPTM_MV_Database::table('polls') . " WHERE id = %d",
			$poll_id
		) );

		if ( ! $poll || $poll->status !== 'active' ) {
			wp_send_json_error( 'Abstimmung nicht aktiv.' );
		}

		if ( count( $choices ) > (int) $poll->max_choices ) {
			wp_send_json_error( 'Zu viele Stimmen.' );
		}

		// Pruefe: Anwesenheit
		$is_checked_in = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . DGPTM_MV_Database::table('attendance') .
			" WHERE assembly_id = %d AND user_id = %d",
			$poll->assembly_id, $voter_id
		) );

		if ( ! $is_checked_in ) {
			wp_send_json_error( 'Das Mitglied ist nicht als anwesend registriert.' );
		}

		// Pruefe: Noch nicht gestimmt
		$already_voted = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . DGPTM_MV_Database::table('vote_receipts') .
			" WHERE poll_id = %d AND user_id = %d",
			$poll_id, $voter_id
		) );

		if ( $already_voted ) {
			wp_send_json_error( 'Dieses Mitglied hat bereits abgestimmt.' );
		}

		// --- ANONYME STIMMABGABE (identisch zur digitalen) ---
		$wpdb->query( 'START TRANSACTION' );

		try {
			$now = current_time( 'mysql' );

			foreach ( $choices as $choice_index ) {
				$wpdb->insert( DGPTM_MV_Database::table('votes'), [
					'poll_id'      => $poll_id,
					'choice_index' => $choice_index,
					'vote_time'    => $now,
				] );
			}

			$wpdb->insert( DGPTM_MV_Database::table('vote_receipts'), [
				'poll_id'     => $poll_id,
				'user_id'     => $voter_id,
				'voted_at'    => $now,
				'vote_method' => 'manual',
				'entered_by'  => $manager_id,
			] );

			$wpdb->query( 'COMMIT' );
		} catch ( \Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			wp_send_json_error( 'Fehler bei der Stimmabgabe.' );
		}

		$voter = get_userdata( $voter_id );
		$name  = $voter ? trim( $voter->first_name . ' ' . $voter->last_name ) : 'Unbekannt';

		wp_send_json_success( [
			'message' => 'Stimme fuer ' . $name . ' wurde anonym erfasst.',
		] );
	}

	// --- AJAX: Vote View fuer Frontend ---

	public function ajax_get_vote_view() {
		$assembly = DGPTM_MV_Assembly::get_active_assembly();
		if ( ! $assembly ) {
			wp_send_json_error( [ 'html' => '<div class="dgptm-mv-notice">Keine aktive Versammlung.</div>' ] );
		}

		$poll = DGPTM_MV_Assembly::get_active_poll( $assembly->id );

		if ( ! $poll ) {
			wp_send_json_error( [ 'html' => '<div class="dgptm-mv-notice">Derzeit keine aktive Abstimmung. Bitte warten...</div>' ] );
		}

		$user_id    = get_current_user_id();
		$is_eligible = $user_id ? DGPTM_Mitgliederversammlung::is_eligible_voter( $user_id ) : false;

		global $wpdb;

		// Pruefe ob schon abgestimmt
		$already_voted = false;
		if ( $user_id ) {
			$already_voted = (bool) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM " . DGPTM_MV_Database::table('vote_receipts') .
				" WHERE poll_id = %d AND user_id = %d",
				$poll->id, $user_id
			) );
		}

		$choices = json_decode( $poll->choices, true );
		if ( ! is_array( $choices ) ) $choices = [];

		$type = ( (int) $poll->max_choices === 1 ) ? 'radio' : 'checkbox';

		// Abstimmungsstatistik
		$stats = DGPTM_MV_Database::get_assembly_stats( $assembly->id );
		$total_votes = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . DGPTM_MV_Database::table('vote_receipts') . " WHERE poll_id = %d",
			$poll->id
		) );

		$majority_labels = [
			'simple'         => 'Einfache Mehrheit',
			'three_quarters' => 'Dreiviertelmehrheit (Satzung §14)',
			'absolute'       => 'Absolute Mehrheit >50% (Satzung §7)',
		];

		ob_start();
		?>
		<div class="dgptm-mv-poll-card" data-poll-id="<?php echo (int) $poll->id; ?>">
			<div class="dgptm-mv-poll-header">
				<h3><?php echo esc_html( $poll->question ); ?></h3>
				<?php if ( $poll->description ) : ?>
					<p class="dgptm-mv-poll-desc"><?php echo esc_html( $poll->description ); ?></p>
				<?php endif; ?>
				<div class="dgptm-mv-poll-meta">
					<span>Erforderlich: <?php echo esc_html( $majority_labels[ $poll->required_majority ] ?? $poll->required_majority ); ?></span>
					<span>Stimmen: <?php echo $total_votes; ?> / <?php echo $stats['eligible']; ?> Stimmberechtigte</span>
				</div>
			</div>

			<?php if ( $already_voted ) : ?>
				<div class="dgptm-mv-voted-notice">
					<strong>Ihre Stimme wurde erfasst.</strong><br>
					Vielen Dank fuer Ihre Teilnahme. Ihre Stimmabgabe war anonym.
				</div>
			<?php elseif ( ! is_user_logged_in() ) : ?>
				<div class="dgptm-mv-notice">Bitte melden Sie sich an, um abzustimmen.</div>
			<?php elseif ( ! $is_eligible ) : ?>
				<div class="dgptm-mv-notice">
					Sie sind nicht stimmberechtigt (nur ordentliche Mitglieder, Satzung §4).
				</div>
			<?php else : ?>
				<form id="dgptm-mv-vote-form" data-poll-id="<?php echo (int) $poll->id; ?>">
					<div class="dgptm-mv-choices">
						<?php foreach ( $choices as $idx => $label ) : ?>
							<label class="dgptm-mv-choice">
								<input type="<?php echo $type; ?>" name="choices[]" value="<?php echo (int) $idx; ?>">
								<span><?php echo esc_html( $label ); ?></span>
							</label>
						<?php endforeach; ?>
					</div>
					<?php if ( (int) $poll->max_choices > 1 ) : ?>
						<p class="dgptm-mv-hint">Sie koennen bis zu <?php echo (int) $poll->max_choices; ?> Stimmen vergeben.</p>
					<?php endif; ?>
					<button type="submit" class="dgptm-mv-btn primary large" id="dgptm-mv-submit-vote">
						Stimme abgeben
					</button>
					<p class="dgptm-mv-anon-hint">
						Ihre Stimmabgabe ist anonym. Es wird nur dokumentiert, dass Sie Ihre Stimme abgegeben haben,
						nicht wofuer Sie gestimmt haben (Satzung §7 Abs. 7).
					</p>
				</form>
			<?php endif; ?>
		</div>
		<?php
		$html = ob_get_clean();
		wp_send_json_success( [ 'html' => $html, 'poll_id' => $poll->id ] );
	}

	// --- AJAX: Beamer-Payload ---

	public function ajax_get_beamer_payload() {
		$assembly = DGPTM_MV_Assembly::get_active_assembly();
		$state = json_decode( get_option( 'dgptm_mv_beamer_state', '{"mode":"idle"}' ), true );
		if ( ! is_array( $state ) ) $state = [ 'mode' => 'idle' ];

		$payload = [
			'beamer_state'    => $state,
			'active_assembly' => null,
			'active_poll'     => null,
			'results'         => null,
			'stats'           => null,
		];

		if ( ! $assembly ) {
			wp_send_json_success( $payload );
		}

		$stats = DGPTM_MV_Database::get_assembly_stats( $assembly->id );
		$payload['active_assembly'] = [
			'id'       => $assembly->id,
			'name'     => $assembly->name,
			'logo_url' => $assembly->logo_url,
		];
		$payload['stats'] = $stats;

		$active_poll = DGPTM_MV_Assembly::get_active_poll( $assembly->id );

		if ( $active_poll ) {
			global $wpdb;
			$total_votes = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM " . DGPTM_MV_Database::table('vote_receipts') . " WHERE poll_id = %d",
				$active_poll->id
			) );

			$payload['active_poll'] = [
				'id'                => $active_poll->id,
				'question'          => $active_poll->question,
				'required_majority' => $active_poll->required_majority,
				'total_votes'       => $total_votes,
			];
		}

		// Modus: results_one oder results_all
		$mode = $state['mode'] ?? 'idle';

		if ( $mode === 'results_one' && ! empty( $state['poll_id'] ) ) {
			$payload['results'] = $this->build_results( (int) $state['poll_id'] );
		}

		if ( $mode === 'results_all' ) {
			global $wpdb;
			$polls = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM " . DGPTM_MV_Database::table('polls') .
				" WHERE assembly_id = %d AND status = 'stopped' AND results_released = 1 AND in_overall = 1 ORDER BY created_at ASC",
				$assembly->id
			) );

			$all = [];
			foreach ( $polls as $p ) {
				$all[] = $this->build_results( $p->id );
			}
			$payload['all_results'] = $all;
		}

		wp_send_json_success( $payload );
	}

	/**
	 * Baut das Ergebnis-Array fuer eine Abstimmung.
	 * Berechnet Mehrheiten gemaess Satzung §7 Abs. 8.
	 */
	private function build_results( $poll_id ) {
		global $wpdb;

		$poll = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM " . DGPTM_MV_Database::table('polls') . " WHERE id = %d", $poll_id
		) );

		if ( ! $poll ) {
			return null;
		}

		$choices = json_decode( $poll->choices, true );
		if ( ! is_array( $choices ) ) $choices = [];

		$vote_counts = array_fill( 0, count( $choices ), 0 );

		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT choice_index, COUNT(*) AS cnt FROM " . DGPTM_MV_Database::table('votes') .
			" WHERE poll_id = %d GROUP BY choice_index",
			$poll_id
		) );

		foreach ( $rows as $r ) {
			if ( isset( $vote_counts[ $r->choice_index ] ) ) {
				$vote_counts[ $r->choice_index ] = (int) $r->cnt;
			}
		}

		$total_votes = array_sum( $vote_counts );
		$total_receipts = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM " . DGPTM_MV_Database::table('vote_receipts') . " WHERE poll_id = %d",
			$poll_id
		) );

		// Enthaltungen identifizieren (typischerweise letzter Eintrag "Enthaltung")
		$abstention_index = null;
		foreach ( $choices as $idx => $label ) {
			if ( stripos( $label, 'enthaltung' ) !== false ) {
				$abstention_index = $idx;
				break;
			}
		}

		// Gueltige Stimmen = alle minus Enthaltungen (Satzung §7 Abs. 8)
		$valid_votes = $total_votes;
		if ( $abstention_index !== null ) {
			$valid_votes = $total_votes - ( $vote_counts[ $abstention_index ] ?? 0 );
		}

		// Mehrheit berechnen
		$majority_met   = false;
		$majority_info  = '';
		$winner_index   = null;

		if ( $valid_votes > 0 ) {
			switch ( $poll->required_majority ) {
				case 'simple':
					// Einfache Mehrheit: meiste gueltige Stimmen
					$max_val   = 0;
					$max_idx   = -1;
					$is_tie    = false;
					foreach ( $vote_counts as $idx => $cnt ) {
						if ( $idx === $abstention_index ) continue;
						if ( $cnt > $max_val ) {
							$max_val = $cnt;
							$max_idx = $idx;
							$is_tie  = false;
						} elseif ( $cnt === $max_val && $max_val > 0 ) {
							$is_tie = true;
						}
					}
					if ( ! $is_tie && $max_val > 0 ) {
						$majority_met  = true;
						$winner_index  = $max_idx;
						$majority_info = 'Einfache Mehrheit erreicht';
					} else {
						$majority_info = $is_tie ? 'Stimmengleichheit' : 'Keine Mehrheit';
					}
					break;

				case 'three_quarters':
					// 3/4 Mehrheit (Satzungsaenderung §14)
					$threshold = ceil( $valid_votes * 0.75 );
					foreach ( $vote_counts as $idx => $cnt ) {
						if ( $idx === $abstention_index ) continue;
						if ( $cnt >= $threshold ) {
							$majority_met  = true;
							$winner_index  = $idx;
							$majority_info = "3/4-Mehrheit erreicht ({$cnt} von {$valid_votes}, Schwelle: {$threshold})";
							break;
						}
					}
					if ( ! $majority_met ) {
						$majority_info = "3/4-Mehrheit nicht erreicht (Schwelle: {$threshold} von {$valid_votes})";
					}
					break;

				case 'absolute':
					// Absolute Mehrheit >50% (Wahlen §7 Abs. 8)
					$threshold = floor( $valid_votes / 2 ) + 1;
					foreach ( $vote_counts as $idx => $cnt ) {
						if ( $idx === $abstention_index ) continue;
						if ( $cnt >= $threshold ) {
							$majority_met  = true;
							$winner_index  = $idx;
							$majority_info = "Absolute Mehrheit erreicht ({$cnt} von {$valid_votes}, Schwelle: {$threshold})";
							break;
						}
					}
					if ( ! $majority_met ) {
						$majority_info = "Absolute Mehrheit nicht erreicht (Schwelle: {$threshold} von {$valid_votes}). Stichwahl erforderlich.";
					}
					break;
			}
		}

		return [
			'poll_id'           => $poll->id,
			'question'          => $poll->question,
			'poll_type'         => $poll->poll_type,
			'required_majority' => $poll->required_majority,
			'choices'           => array_values( $choices ),
			'votes'             => array_values( $vote_counts ),
			'total_votes'       => $total_votes,
			'total_receipts'    => $total_receipts,
			'valid_votes'       => $valid_votes,
			'majority_met'      => $majority_met,
			'majority_info'     => $majority_info,
			'winner_index'      => $winner_index,
			'chart_type'        => $poll->chart_type,
			'released'          => (bool) $poll->results_released,
		];
	}

	// --- AJAX: Eligible voters fuer manuelle Stimmabgabe ---

	public function ajax_get_eligible_voters() {
		check_ajax_referer( 'dgptm_mv_nonce', 'nonce' );
		if ( ! DGPTM_Mitgliederversammlung::is_manager() ) {
			wp_send_json_error( 'Keine Berechtigung.' );
		}

		$assembly = DGPTM_MV_Assembly::get_active_assembly();
		if ( ! $assembly ) {
			wp_send_json_error( 'Keine aktive Versammlung.' );
		}

		$poll_id = (int) ( $_POST['poll_id'] ?? 0 );
		$search  = sanitize_text_field( $_POST['search'] ?? '' );

		global $wpdb;

		// Alle eingecheckten, stimmberechtigten Mitglieder
		$attendees = $wpdb->get_results( $wpdb->prepare(
			"SELECT a.*, u.display_name, u.user_email
			 FROM " . DGPTM_MV_Database::table('attendance') . " a
			 LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
			 WHERE a.assembly_id = %d AND a.is_eligible_voter = 1
			 ORDER BY a.member_name ASC",
			$assembly->id
		) );

		// Filtern nach Suchbegriff
		if ( $search ) {
			$attendees = array_filter( $attendees, function( $a ) use ( $search ) {
				return stripos( $a->member_name, $search ) !== false
					|| stripos( $a->member_no, $search ) !== false
					|| stripos( $a->display_name ?? '', $search ) !== false;
			} );
		}

		// Bereits abgestimmte markieren
		$voted_ids = [];
		if ( $poll_id ) {
			$results = $wpdb->get_col( $wpdb->prepare(
				"SELECT user_id FROM " . DGPTM_MV_Database::table('vote_receipts') . " WHERE poll_id = %d",
				$poll_id
			) );
			$voted_ids = array_map( 'intval', $results );
		}

		$list = [];
		foreach ( $attendees as $a ) {
			$list[] = [
				'user_id'     => (int) $a->user_id,
				'name'        => $a->member_name ?: ( $a->display_name ?? 'Unbekannt' ),
				'member_no'   => $a->member_no,
				'has_voted'   => in_array( (int) $a->user_id, $voted_ids, true ),
				'type'        => $a->attendance_type,
			];
		}

		wp_send_json_success( $list );
	}
}
