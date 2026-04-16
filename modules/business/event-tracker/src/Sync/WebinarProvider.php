<?php
/**
 * Webinar Provider Interface
 *
 * Abstraktion fuer verschiedene Webinar-Plattformen (Zoho Meeting, Zoom).
 *
 * @package EventTracker\Sync
 * @since 2.3.0
 */

namespace EventTracker\Sync;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface WebinarProvider {

	/**
	 * Alle Webinare der Organisation abrufen.
	 *
	 * @return array [ 'ok' => bool, 'data' => [ ['key' => '...', 'topic' => '...', 'startTime' => '...', 'endTime' => '...', 'sysId' => '...'] ] ]
	 */
	public function list_webinars(): array;

	/**
	 * Teilnehmer fuer ein Webinar registrieren.
	 *
	 * @param string $webinar_key  Webinar-Schluessel.
	 * @param string $instance_id  Instanz-ID (plattformspezifisch).
	 * @param array  $registrants  [ ['email' => '', 'firstName' => '', 'lastName' => ''] ]
	 * @return array [ 'ok' => bool, 'data' => [ 'registrant' => [ ['email' => '', 'joinLink' => ''] ] ] ]
	 */
	public function register_attendees( string $webinar_key, string $instance_id, array $registrants ): array;

	/**
	 * Registrierte Teilnehmer eines Webinars abrufen.
	 *
	 * @param string $webinar_key Webinar-Schluessel.
	 * @return array [ 'ok' => bool, 'data' => [ ['email' => '', 'name' => ''] ] ]
	 */
	public function get_registrants( string $webinar_key ): array;

	/**
	 * Anwesenheitsbericht nach Webinar-Ende abrufen.
	 *
	 * @param string $webinar_key Webinar-Schluessel.
	 * @return array [ 'ok' => bool, 'data' => [ 'attendeeData' => [ ['email' => '', 'name' => ''] ] ] ]
	 */
	public function get_attendee_report( string $webinar_key ): array;

	/**
	 * Provider-Name (fuer Logging).
	 *
	 * @return string
	 */
	public function get_name(): string;
}
