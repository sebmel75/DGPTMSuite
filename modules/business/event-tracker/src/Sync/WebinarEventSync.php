<?php
/**
 * Webinar ↔ DGFK_Events Synchronisation
 *
 * Erkennt neue Webinare in Zoho Meeting und legt DGFK_Events-Records im CRM an.
 *
 * @package EventTracker\Sync
 * @since 2.3.0
 */

namespace EventTracker\Sync;

use EventTracker\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebinarEventSync {

	/** @var WebinarProvider */
	private $provider;

	/** @var CrmClient */
	private $crm;

	public function __construct( WebinarProvider $provider, CrmClient $crm ) {
		$this->provider = $provider;
		$this->crm      = $crm;
	}

	/**
	 * Sync ausfuehren: Neue Webinare → DGFK_Events anlegen.
	 *
	 * @return array [ 'created' => int, 'updated' => int, 'errors' => int ]
	 */
	public function sync(): array {
		$stats = [ 'created' => 0, 'updated' => 0, 'errors' => 0 ];

		// 1. Webinare aus Meeting laden
		$webinars = $this->provider->list_webinars();
		if ( ! $webinars['ok'] ) {
			Helpers::log( 'WebinarEventSync: Webinar-Liste Fehler — ' . ( $webinars['message'] ?? '' ), 'error' );
			return $stats;
		}

		if ( empty( $webinars['data'] ) ) {
			Helpers::log( 'WebinarEventSync: Keine Webinare gefunden', 'info' );
			return $stats;
		}

		// 2. Bestehende CRM-Events mit Meeting_Key laden
		$existing = $this->crm->coql_query(
			"SELECT id, Meeting_Key, Name FROM DGFK_Events WHERE Meeting_Key is not null LIMIT 2000"
		);

		$existing_keys = [];
		foreach ( $existing as $event ) {
			$key = $event['Meeting_Key'] ?? '';
			if ( $key ) {
				$existing_keys[ $key ] = $event['id'];
			}
		}

		Helpers::log( sprintf( 'WebinarEventSync: %d Webinare, %d bestehende CRM-Events',
			count( $webinars['data'] ), count( $existing_keys ) ), 'info' );

		// 3. Neue Webinare anlegen
		foreach ( $webinars['data'] as $webinar ) {
			$key = $webinar['key'] ?? '';
			if ( ! $key ) {
				continue;
			}

			if ( isset( $existing_keys[ $key ] ) ) {
				continue; // Bereits vorhanden
			}

			// Datum konvertieren
			$start_date = $this->parse_zoho_date( $webinar['startTime'] ?? '' );
			$end_date   = $this->parse_zoho_date( $webinar['endTime'] ?? '' );

			if ( ! $start_date ) {
				// Fallback: Millisekunden
				$start_ms   = intval( $webinar['startTimeMillis'] ?? 0 );
				$end_ms     = intval( $webinar['endTimeMillis'] ?? 0 );
				$start_date = $start_ms ? gmdate( 'Y-m-d', $start_ms / 1000 ) : '';
				$end_date   = $end_ms ? gmdate( 'Y-m-d', $end_ms / 1000 ) : $start_date;
			}

			$record_data = [
				'Name'         => $webinar['topic'] ?? 'Webinar',
				'Event_Type'   => 'Webinar',
				'From_Date'    => $start_date,
				'To_Date'      => $end_date ?: $start_date,
				'Meeting_Key'  => $key,
				'Event_Status' => 'In Planung',
			];

			$record_id = $this->crm->create_record( 'DGFK_Events', $record_data );

			if ( $record_id ) {
				$stats['created']++;
				Helpers::log( sprintf( 'WebinarEventSync: CRM-Event angelegt: %s (Key: %s, ID: %s)',
					$webinar['topic'] ?? '', $key, $record_id ), 'info' );
			} else {
				$stats['errors']++;
			}

			usleep( 600000 ); // 0.6s Rate Limit
		}

		return $stats;
	}

	/**
	 * CRM-Event fuer ein einzelnes Webinar anlegen (z.B. nach WordPress-Erstellung).
	 *
	 * @param string $meeting_key  Webinar-Key.
	 * @param string $topic        Webinar-Titel.
	 * @param string $start_date   Startdatum (Y-m-d).
	 * @param string $end_date     Enddatum (Y-m-d).
	 * @return string|false CRM Record-ID oder false.
	 */
	public function create_crm_event( string $meeting_key, string $topic, string $start_date, string $end_date = '' ) {
		return $this->crm->create_record( 'DGFK_Events', [
			'Name'         => $topic,
			'Event_Type'   => 'Webinar',
			'From_Date'    => $start_date,
			'To_Date'      => $end_date ?: $start_date,
			'Meeting_Key'  => $meeting_key,
			'Event_Status' => 'In Planung',
		] );
	}

	/**
	 * Zoho Meeting Datumsformat parsen.
	 *
	 * @param string $date_str z.B. "Apr 22, 2026 05:30 PM CEST"
	 * @return string Y-m-d oder leer.
	 */
	private function parse_zoho_date( string $date_str ): string {
		if ( ! $date_str ) {
			return '';
		}
		// Zeitzone am Ende entfernen (CEST, CET, IST etc.)
		$clean = preg_replace( '/\s+[A-Z]{2,5}$/', '', trim( $date_str ) );
		$ts    = strtotime( $clean );
		return $ts ? gmdate( 'Y-m-d', $ts ) : '';
	}
}
