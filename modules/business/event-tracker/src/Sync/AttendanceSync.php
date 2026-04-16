<?php
/**
 * Anwesenheits-Synchronisation nach Webinar-Ende
 *
 * Holt den Attendee-Report aus Zoho Meeting und aktualisiert
 * Anwesenheit, daysonEvent, letzter_Scan und Blueprint in CRM.
 *
 * @package EventTracker\Sync
 * @since 2.3.0
 */

namespace EventTracker\Sync;

use EventTracker\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AttendanceSync {

	/** @var WebinarProvider */
	private $provider;

	/** @var CrmClient */
	private $crm;

	/** @var string|null Gecachte Blueprint-Transition-ID fuer "Teilgenommen" */
	private $bp_transition_teilgenommen;

	/** @var string|null Gecachte Blueprint-Transition-ID fuer "Nicht teilgenommen" */
	private $bp_transition_nicht_teilgenommen;

	public function __construct( WebinarProvider $provider, CrmClient $crm ) {
		$this->provider = $provider;
		$this->crm      = $crm;
	}

	/**
	 * Attendance Sync ausfuehren.
	 *
	 * @param string $crm_event_id DGFK_Events Record-ID.
	 * @param string $meeting_key  Webinar Meeting-Key.
	 * @return array [ 'attended' => int, 'not_attended' => int, 'errors' => int ]
	 */
	public function sync( string $crm_event_id, string $meeting_key ): array {
		$stats = [ 'attended' => 0, 'not_attended' => 0, 'errors' => 0 ];

		// 1. Attendee Report laden
		$report = $this->provider->get_attendee_report( $meeting_key );
		if ( ! $report['ok'] ) {
			Helpers::log( 'AttendanceSync: Report Fehler — ' . ( $report['message'] ?? '' ), 'error' );
			return $stats;
		}

		$attendee_data   = $report['data']['attendeeData'] ?? [];
		$attendee_emails = [];
		foreach ( $attendee_data as $a ) {
			$email = strtolower( trim( $a['email'] ?? '' ) );
			if ( $email ) {
				$attendee_emails[ $email ] = true;
			}
		}

		Helpers::log( sprintf( 'AttendanceSync: %d Anwesende im Report', count( $attendee_emails ) ), 'info' );

		// 2. CRM-Teilnahmen laden (nur Status "Angemeldet")
		$participants = $this->crm->coql_query(
			"SELECT id, Email, Status FROM Veranstal_X_Contacts WHERE Veranstaltungen = '{$crm_event_id}' AND Status = 'Angemeldet' LIMIT 2000"
		);

		if ( empty( $participants ) ) {
			Helpers::log( 'AttendanceSync: Keine offenen Teilnahmen gefunden', 'info' );
			return $stats;
		}

		// 3. Blueprint-Transitions ermitteln
		$this->load_blueprint_transitions( $participants[0]['id'] ?? '' );

		$today    = gmdate( 'Y-m-d' );
		$today_de = gmdate( 'd.m.Y' );

		// 4. Anwesende und Nicht-Anwesende verarbeiten
		$attended_updates = [];

		foreach ( $participants as $p ) {
			$email  = strtolower( trim( $p['Email'] ?? '' ) );
			$rec_id = $p['id'] ?? '';

			if ( ! $rec_id ) {
				continue;
			}

			if ( isset( $attendee_emails[ $email ] ) ) {
				// Teilgenommen
				$attended_updates[] = [
					'id'           => $rec_id,
					'Anwesenheit'  => 'Anwesenheit erfasst: ' . $today_de,
					'daysonEvent'  => 1,
					'letzter_Scan' => $today,
				];

				if ( $this->bp_transition_teilgenommen ) {
					$this->crm->execute_blueprint( 'Veranstal_X_Contacts', $rec_id, $this->bp_transition_teilgenommen );
					usleep( 300000 );
				}

				$stats['attended']++;
			} else {
				// Nicht teilgenommen
				if ( $this->bp_transition_nicht_teilgenommen ) {
					$this->crm->execute_blueprint( 'Veranstal_X_Contacts', $rec_id, $this->bp_transition_nicht_teilgenommen );
					usleep( 300000 );
				}

				$stats['not_attended']++;
			}
		}

		// 5. Batch-Update der Felder fuer Anwesende
		if ( $attended_updates ) {
			foreach ( array_chunk( $attended_updates, 100 ) as $batch ) {
				$this->crm->update_records( 'Veranstal_X_Contacts', $batch );
				usleep( 600000 );
			}
		}

		Helpers::log( sprintf( 'AttendanceSync: %d teilgenommen, %d nicht teilgenommen',
			$stats['attended'], $stats['not_attended'] ), 'info' );

		return $stats;
	}

	/**
	 * Blueprint-Transition-IDs laden und cachen.
	 */
	private function load_blueprint_transitions( string $sample_record_id ): void {
		if ( ! $sample_record_id ) {
			return;
		}

		$cached = get_transient( 'et_vxc_blueprint_transitions' );
		if ( $cached ) {
			$this->bp_transition_teilgenommen      = $cached['teilgenommen'] ?? null;
			$this->bp_transition_nicht_teilgenommen = $cached['nicht_teilgenommen'] ?? null;
			return;
		}

		$bp          = $this->crm->get_blueprint( 'Veranstal_X_Contacts', $sample_record_id );
		$transitions = $bp['transitions'] ?? [];

		foreach ( $transitions as $t ) {
			$next = $t['next_field_value'] ?? '';
			if ( $next === 'Teilgenommen' ) {
				$this->bp_transition_teilgenommen = $t['id'];
			} elseif ( $next === 'Nicht teilgenommen' ) {
				$this->bp_transition_nicht_teilgenommen = $t['id'];
			}
		}

		set_transient( 'et_vxc_blueprint_transitions', [
			'teilgenommen'       => $this->bp_transition_teilgenommen,
			'nicht_teilgenommen' => $this->bp_transition_nicht_teilgenommen,
		], DAY_IN_SECONDS );
	}
}
