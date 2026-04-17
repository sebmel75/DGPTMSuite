<?php
/**
 * Bidirektionale Registrierungs-Synchronisation
 *
 * CRM → Meeting: Veranstal_X_Contacts ohne WebinarLink in Meeting registrieren.
 * Meeting → CRM: Meeting-Registrierungen ohne CRM-Eintrag anlegen.
 *
 * @package EventTracker\Sync
 * @since 2.3.0
 */

namespace EventTracker\Sync;

use EventTracker\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RegistrationSync {

	/** @var WebinarProvider */
	private $provider;

	/** @var CrmClient */
	private $crm;

	public function __construct( WebinarProvider $provider, CrmClient $crm ) {
		$this->provider = $provider;
		$this->crm      = $crm;
	}

	/**
	 * Sync ausfuehren fuer ein bestimmtes Event.
	 *
	 * @param string $crm_event_id  DGFK_Events Record-ID.
	 * @param string $meeting_key   Webinar Meeting-Key.
	 * @param string $instance_id   Webinar sysId/instanceId.
	 * @return array [ 'crm_to_meeting' => int, 'meeting_to_crm' => int, 'contacts_created' => int ]
	 */
	public function sync( string $crm_event_id, string $meeting_key, string $instance_id ): array {
		$stats = [ 'crm_to_meeting' => 0, 'meeting_to_crm' => 0, 'contacts_created' => 0 ];

		// Meeting-Registrierungen laden
		$meeting_regs   = $this->provider->get_registrants( $meeting_key );
		$meeting_emails = [];
		if ( $meeting_regs['ok'] ) {
			foreach ( $meeting_regs['data']['registrant'] ?? $meeting_regs['data'] ?? [] as $reg ) {
				$email = strtolower( trim( $reg['email'] ?? '' ) );
				if ( $email ) {
					$meeting_emails[ $email ] = $reg;
				}
			}
		}

		// CRM-Teilnahmen laden
		$crm_participants = $this->crm->coql_query(
			"SELECT id, Email, Teilnehmer, WebinarLink, Status FROM Veranstal_X_Contacts WHERE Veranstaltungen = '{$crm_event_id}' LIMIT 2000"
		);

		$crm_emails = [];
		foreach ( $crm_participants as $p ) {
			$email = strtolower( trim( $p['Email'] ?? '' ) );
			if ( $email ) {
				$crm_emails[ $email ] = $p;
			}
		}

		Helpers::log( sprintf( 'RegistrationSync: Event %s — CRM: %d, Meeting: %d',
			$crm_event_id, count( $crm_emails ), count( $meeting_emails ) ), 'info' );

		// Richtung 1: CRM → Meeting
		$stats['crm_to_meeting'] = $this->sync_crm_to_meeting(
			$crm_participants, $meeting_emails, $meeting_key, $instance_id
		);

		// Richtung 2: Meeting → CRM
		$meeting_to_crm_result      = $this->sync_meeting_to_crm( $meeting_emails, $crm_emails, $crm_event_id );
		$stats['meeting_to_crm']    = $meeting_to_crm_result['linked'];
		$stats['contacts_created']  = $meeting_to_crm_result['created'];

		return $stats;
	}

	/**
	 * CRM → Meeting: Teilnehmer ohne WebinarLink in Meeting registrieren.
	 */
	private function sync_crm_to_meeting( array $crm_participants, array $meeting_emails, string $meeting_key, string $instance_id ): int {
		$to_register  = [];
		$register_map = []; // email → crm record id

		foreach ( $crm_participants as $p ) {
			$email = strtolower( trim( $p['Email'] ?? '' ) );
			$link  = $p['WebinarLink'] ?? '';

			if ( ! $email || $link ) {
				continue; // Kein E-Mail oder bereits registriert
			}

			if ( isset( $meeting_emails[ $email ] ) ) {
				continue; // Bereits in Meeting registriert
			}

			// Name aus Teilnehmer-Lookup extrahieren
			$name = '';
			$tn   = $p['Teilnehmer'] ?? null;
			if ( is_array( $tn ) ) {
				$name = $tn['name'] ?? '';
			}
			$parts = explode( ' ', $name, 2 );

			$to_register[] = [
				'email'     => $email,
				'firstName' => $parts[0] ?? '',
				'lastName'  => $parts[1] ?? '',
			];
			$register_map[ $email ] = $p['id'];
		}

		if ( empty( $to_register ) ) {
			return 0;
		}

		Helpers::log( sprintf( 'RegistrationSync CRM→Meeting: %d zu registrieren', count( $to_register ) ), 'info' );

		// Batch-Registrierung (max 50 pro Call)
		$registered = 0;
		foreach ( array_chunk( $to_register, 50 ) as $batch ) {
			$result = $this->provider->register_attendees( $meeting_key, $instance_id, $batch );

			if ( ! $result['ok'] ) {
				Helpers::log( 'RegistrationSync: Meeting-Registrierung fehlgeschlagen — ' . ( $result['message'] ?? '' ), 'error' );
				continue;
			}

			// JoinLinks in CRM speichern
			$updates = [];
			foreach ( $result['data']['registrant'] ?? [] as $reg ) {
				$email = strtolower( trim( $reg['email'] ?? '' ) );
				$link  = $reg['joinLink'] ?? '';
				if ( $email && $link && isset( $register_map[ $email ] ) ) {
					$updates[] = [
						'id'          => $register_map[ $email ],
						'WebinarLink' => $link,
					];
					$registered++;
				}
			}

			if ( $updates ) {
				$this->crm->update_records( 'Veranstal_X_Contacts', $updates );
			}

			usleep( 1000000 ); // 1s zwischen Meeting-Calls
		}

		return $registered;
	}

	/**
	 * Meeting → CRM: Registrierungen ohne CRM-Eintrag anlegen.
	 */
	private function sync_meeting_to_crm( array $meeting_emails, array $crm_emails, string $crm_event_id ): array {
		$stats = [ 'linked' => 0, 'created' => 0 ];

		foreach ( $meeting_emails as $email => $reg ) {
			if ( isset( $crm_emails[ $email ] ) ) {
				continue; // Bereits im CRM verknuepft
			}

			// Contact suchen (4 Mail-Felder)
			$contact = $this->crm->find_contact_by_email( $email );

			if ( ! $contact ) {
				// Neuen Contact anlegen
				$name_parts = $this->parse_name( $reg );
				$contact_id = $this->crm->create_record( 'Contacts', [
					'First_Name' => $name_parts['first'],
					'Last_Name'  => $name_parts['last'],
					'Email'      => $email,
				] );

				if ( ! $contact_id ) {
					Helpers::log( sprintf( 'RegistrationSync: Contact-Anlage fehlgeschlagen fuer %s', $email ), 'error' );
					continue;
				}

				$stats['created']++;
				Helpers::log( sprintf( 'RegistrationSync: Neuer Contact angelegt: %s %s (%s) → ID %s',
					$name_parts['first'], $name_parts['last'], $email, $contact_id ), 'info' );
			} else {
				$contact_id = $contact['id'];
			}

			// Veranstal_X_Contacts anlegen
			$join_link = $reg['joinLink'] ?? '';
			$vxc_id    = $this->crm->create_record( 'Veranstal_X_Contacts', [
				'Veranstaltungen' => $crm_event_id,
				'Teilnehmer'      => $contact_id,
				'WebinarLink'     => $join_link,
			] );

			if ( $vxc_id ) {
				$stats['linked']++;
				Helpers::log( sprintf( 'RegistrationSync: Teilnahme angelegt: %s → Event %s', $email, $crm_event_id ), 'info' );
			}

			usleep( 600000 ); // 0.6s Rate Limit
		}

		return $stats;
	}

	/**
	 * Name aus Meeting-Registrierung extrahieren.
	 */
	private function parse_name( array $reg ): array {
		$first = $reg['firstName'] ?? $reg['name'] ?? '';
		$last  = $reg['lastName'] ?? '';

		if ( ! $last && strpos( $first, ' ' ) !== false ) {
			$parts = explode( ' ', $first, 2 );
			$first = $parts[0];
			$last  = $parts[1];
		}

		return [
			'first' => $first ?: 'Unbekannt',
			'last'  => $last ?: 'Unbekannt',
		];
	}
}
