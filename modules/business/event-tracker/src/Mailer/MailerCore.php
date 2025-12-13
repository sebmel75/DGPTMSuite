<?php
/**
 * Event Tracker Mailer Core
 *
 * @package EventTracker\Mailer
 * @since 2.0.0
 */

namespace EventTracker\Mailer;

use EventTracker\Core\Constants;
use EventTracker\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Mailer Core Class
 */
class MailerCore {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( Constants::CRON_HOOK_SINGLE, [ $this, 'run_mail_job' ] );
		add_action( 'init', [ $this, 'maybe_process_due_jobs' ] );
	}

	/**
	 * Send Mail via Webhook
	 *
	 * @param int    $event_id Event ID.
	 * @param string $subject  Mail subject.
	 * @param string $html     Mail HTML content.
	 * @param string $schedule Schedule type (now|at|event_start|until_start).
	 * @param array  $options  Additional options.
	 * @return array Result array with 'ok' and 'message'.
	 */
	public function send_mail( $event_id, $subject, $html, $schedule = 'now', $options = [] ) {
		// Validate event
		if ( get_post_type( $event_id ) !== Constants::CPT ) {
			return [ 'ok' => false, 'message' => __( 'Ungültige Veranstaltung.', 'event-tracker' ) ];
		}

		// Validate content
		if ( ! $html || strpos( $html, '{{URL}}' ) === false ) {
			return [ 'ok' => false, 'message' => __( 'Mail muss den Platzhalter {{URL}} enthalten.', 'event-tracker' ) ];
		}

		$zoho_id     = get_post_meta( $event_id, Constants::META_ZOHO_ID, true );
		$schedule_at = isset( $options['schedule_at'] ) ? $options['schedule_at'] : '';
		$interval    = isset( $options['interval'] ) ? absint( $options['interval'] ) : 0;
		$from_ts     = isset( $options['interval_from'] ) ? $options['interval_from'] : '';
		$ignoredate  = isset( $options['ignoredate'] ) && $options['ignoredate'] === '1';

		// Create mail log entry
		$log_id = wp_insert_post(
			[
				'post_type'   => Constants::CPT_MAIL_LOG,
				'post_title'  => sprintf( '%s - %s', get_the_title( $event_id ), gmdate( 'Y-m-d H:i' ) ),
				'post_status' => 'publish',
				'post_author' => get_current_user_id(),
			]
		);

		if ( is_wp_error( $log_id ) || ! $log_id ) {
			return [ 'ok' => false, 'message' => __( 'Fehler beim Erstellen des Log-Eintrags.', 'event-tracker' ) ];
		}

		// Save meta
		update_post_meta( $log_id, Constants::META_MAIL_EVENT_ID, $event_id );
		update_post_meta( $log_id, Constants::META_MAIL_ZOHO_ID, $zoho_id );
		update_post_meta( $log_id, Constants::META_MAIL_RAW_HTML, $html );
		update_post_meta( $log_id, Constants::META_MAIL_SUBJECT, $subject );
		update_post_meta( $log_id, Constants::META_MAIL_SCHED_KIND, $schedule );
		update_post_meta( $log_id, Constants::META_MAIL_IGNOREDATE, $ignoredate ? '1' : '0' );

		// Handle scheduling
		$now = time();
		$next_run = 0;

		switch ( $schedule ) {
			case 'now':
				// Send immediately
				$result = $this->execute_mail_send( $log_id );
				return $result;

			case 'at':
				// Schedule at specific time
				if ( ! $schedule_at ) {
					wp_delete_post( $log_id, true );
					return [ 'ok' => false, 'message' => __( 'Datum/Uhrzeit fehlt.', 'event-tracker' ) ];
				}
				$next_run = Helpers::sanitize_datetime_local( $schedule_at );
				break;

			case 'event_start':
				// Schedule at event start
				$start = (int) get_post_meta( $event_id, Constants::META_START_TS, true );
				if ( ! $start ) {
					wp_delete_post( $log_id, true );
					return [ 'ok' => false, 'message' => __( 'Event hat kein Startdatum.', 'event-tracker' ) ];
				}
				$next_run = $start;
				break;

			case 'until_start':
				// Recurring interval until event start
				if ( ! $interval ) {
					wp_delete_post( $log_id, true );
					return [ 'ok' => false, 'message' => __( 'Intervall fehlt.', 'event-tracker' ) ];
				}

				$start = (int) get_post_meta( $event_id, Constants::META_START_TS, true );
				if ( ! $start ) {
					wp_delete_post( $log_id, true );
					return [ 'ok' => false, 'message' => __( 'Event hat kein Startdatum.', 'event-tracker' ) ];
				}

				// Calculate first run time
				if ( $from_ts ) {
					$from_timestamp = Helpers::sanitize_datetime_local( $from_ts );
					$next_run       = max( $now, $from_timestamp );
				} else {
					$next_run = $now;
				}

				// Save interval data
				update_post_meta( $log_id, Constants::META_MAIL_RECURRING, '1' );
				update_post_meta( $log_id, Constants::META_MAIL_INTERVAL, $interval * 60 ); // Convert to seconds
				if ( $from_ts ) {
					update_post_meta( $log_id, Constants::META_MAIL_INTERVAL_FROM, $from_timestamp );
				}
				break;

			default:
				wp_delete_post( $log_id, true );
				return [ 'ok' => false, 'message' => __( 'Ungültiger Zeitplan.', 'event-tracker' ) ];
		}

		// Schedule the mail
		update_post_meta( $log_id, Constants::META_MAIL_SCHED_TS, $next_run );
		update_post_meta( $log_id, Constants::META_MAIL_STATUS, Constants::STATUS_QUEUED );

		// Schedule cron job
		if ( ! wp_next_scheduled( Constants::CRON_HOOK_SINGLE, [ $log_id ] ) ) {
			wp_schedule_single_event( $next_run, Constants::CRON_HOOK_SINGLE, [ $log_id ] );
		}

		Helpers::log( sprintf( 'Mail scheduled (Log ID: %d, Event: %d, Next run: %s)', $log_id, $event_id, gmdate( 'Y-m-d H:i:s', $next_run ) ), 'info' );

		return [
			'ok'      => true,
			'message' => __( 'Mail geplant.', 'event-tracker' ),
			'mode'    => 'queued',
			'log_id'  => $log_id,
		];
	}

	/**
	 * Execute mail send via webhook
	 *
	 * @param int $log_id Mail log ID.
	 * @return array Result array.
	 */
	public function execute_mail_send( $log_id ) {
		// Get mail data
		$event_id = (int) get_post_meta( $log_id, Constants::META_MAIL_EVENT_ID, true );
		$zoho_id  = (string) get_post_meta( $log_id, Constants::META_MAIL_ZOHO_ID, true );
		$html     = (string) get_post_meta( $log_id, Constants::META_MAIL_RAW_HTML, true );
		$subject  = (string) get_post_meta( $log_id, Constants::META_MAIL_SUBJECT, true );

		if ( ! $event_id || ! $html ) {
			update_post_meta( $log_id, Constants::META_MAIL_STATUS, Constants::STATUS_ERROR );
			return [ 'ok' => false, 'message' => __( 'Fehlende Daten.', 'event-tracker' ) ];
		}

		// Get webhook URL from settings
		$settings    = get_option( Constants::OPT_KEY, [] );
		$webhook_url = isset( $settings['mail_webhook_url'] ) ? $settings['mail_webhook_url'] : '';

		if ( ! $webhook_url ) {
			update_post_meta( $log_id, Constants::META_MAIL_STATUS, Constants::STATUS_ERROR );
			return [ 'ok' => false, 'message' => __( 'Webhook-URL nicht konfiguriert.', 'event-tracker' ) ];
		}

		// Prepare data for webhook
		$data = [
			'event_id'  => $event_id,
			'zoho_id'   => $zoho_id,
			'subject'   => $subject,
			'html'      => $html,
			'timestamp' => time(),
		];

		// Send to webhook
		$response = wp_remote_post(
			$webhook_url,
			[
				'timeout' => 30,
				'body'    => wp_json_encode( $data ),
				'headers' => [
					'Content-Type' => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			update_post_meta( $log_id, Constants::META_MAIL_STATUS, Constants::STATUS_ERROR );
			update_post_meta( $log_id, Constants::META_MAIL_HTTP_BODY, $response->get_error_message() );
			Helpers::log( sprintf( 'Mail send error (Log ID: %d): %s', $log_id, $response->get_error_message() ), 'error' );
			return [ 'ok' => false, 'message' => $response->get_error_message() ];
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		update_post_meta( $log_id, Constants::META_MAIL_HTTP_CODE, $code );
		update_post_meta( $log_id, Constants::META_MAIL_HTTP_BODY, $body );

		if ( $code >= 200 && $code < 300 ) {
			update_post_meta( $log_id, Constants::META_MAIL_STATUS, Constants::STATUS_SENT );
			Helpers::log( sprintf( 'Mail sent successfully (Log ID: %d, HTTP: %d)', $log_id, $code ), 'info' );

			// Handle recurring mails
			$is_recurring = get_post_meta( $log_id, Constants::META_MAIL_RECURRING, true ) === '1';
			if ( $is_recurring ) {
				$this->schedule_next_recurrence( $log_id );
			}

			return [ 'ok' => true, 'message' => __( 'Mail gesendet.', 'event-tracker' ) ];
		} else {
			update_post_meta( $log_id, Constants::META_MAIL_STATUS, Constants::STATUS_ERROR );
			Helpers::log( sprintf( 'Mail send failed (Log ID: %d, HTTP: %d)', $log_id, $code ), 'error' );
			return [ 'ok' => false, 'message' => sprintf( __( 'Fehler: HTTP %d', 'event-tracker' ), $code ) ];
		}
	}

	/**
	 * Schedule next recurrence for recurring mails
	 *
	 * @param int $log_id Mail log ID.
	 */
	private function schedule_next_recurrence( $log_id ) {
		$event_id = (int) get_post_meta( $log_id, Constants::META_MAIL_EVENT_ID, true );
		$interval = (int) get_post_meta( $log_id, Constants::META_MAIL_INTERVAL, true );
		$stopped  = get_post_meta( $log_id, Constants::META_MAIL_STOPPED, true ) === '1';

		if ( $stopped || ! $interval ) {
			return;
		}

		// Get event start time
		$event_start = (int) get_post_meta( $event_id, Constants::META_START_TS, true );
		if ( ! $event_start ) {
			return;
		}

		// Calculate next run
		$now      = time();
		$next_run = $now + $interval;

		// Stop if past event start
		if ( $next_run >= $event_start ) {
			update_post_meta( $log_id, Constants::META_MAIL_STATUS, Constants::STATUS_STOPPED );
			Helpers::log( sprintf( 'Recurring mail stopped (reached event start, Log ID: %d)', $log_id ), 'info' );
			return;
		}

		// Schedule next run
		update_post_meta( $log_id, Constants::META_MAIL_SCHED_TS, $next_run );
		update_post_meta( $log_id, Constants::META_MAIL_STATUS, Constants::STATUS_QUEUED );

		if ( ! wp_next_scheduled( Constants::CRON_HOOK_SINGLE, [ $log_id ] ) ) {
			wp_schedule_single_event( $next_run, Constants::CRON_HOOK_SINGLE, [ $log_id ] );
		}

		Helpers::log( sprintf( 'Recurring mail rescheduled (Log ID: %d, Next run: %s)', $log_id, gmdate( 'Y-m-d H:i:s', $next_run ) ), 'info' );
	}

	/**
	 * Run mail job (Cron)
	 *
	 * @param int $log_id Mail log ID.
	 */
	public function run_mail_job( $log_id ) {
		// Prevent concurrent execution
		$lock = get_post_meta( $log_id, Constants::META_MAIL_LOCK, true );
		if ( $lock && ( time() - (int) $lock ) < 300 ) {
			Helpers::log( sprintf( 'Mail job locked (Log ID: %d)', $log_id ), 'warning' );
			return;
		}

		update_post_meta( $log_id, Constants::META_MAIL_LOCK, time() );

		// Execute send
		$result = $this->execute_mail_send( $log_id );

		// Remove lock
		delete_post_meta( $log_id, Constants::META_MAIL_LOCK );

		Helpers::log( sprintf( 'Cron mail job completed (Log ID: %d, Success: %s)', $log_id, $result['ok'] ? 'Yes' : 'No' ), 'info' );
	}

	/**
	 * Process due jobs (Fallback if cron doesn't work)
	 */
	public function maybe_process_due_jobs() {
		// Only run occasionally
		if ( rand( 1, 10 ) > 2 ) {
			return;
		}

		$now = time();

		// Find due mails
		$due_mails = get_posts(
			[
				'post_type'      => Constants::CPT_MAIL_LOG,
				'post_status'    => 'any',
				'posts_per_page' => Constants::MAX_JOBS_PER_TICK,
				'meta_query'     => [
					'relation' => 'AND',
					[
						'key'     => Constants::META_MAIL_STATUS,
						'value'   => Constants::STATUS_QUEUED,
						'compare' => '=',
					],
					[
						'key'     => Constants::META_MAIL_SCHED_TS,
						'value'   => $now,
						'compare' => '<=',
						'type'    => 'NUMERIC',
					],
				],
				'fields'         => 'ids',
			]
		);

		foreach ( $due_mails as $log_id ) {
			$this->run_mail_job( $log_id );
		}

		if ( count( $due_mails ) > 0 ) {
			Helpers::log( sprintf( 'Fallback: Processed %d due mail jobs', count( $due_mails ) ), 'info' );
		}
	}

	/**
	 * Stop recurring mail
	 *
	 * @param int $log_id Mail log ID.
	 * @return bool Success.
	 */
	public function stop_recurring_mail( $log_id ) {
		$is_recurring = get_post_meta( $log_id, Constants::META_MAIL_RECURRING, true ) === '1';
		if ( ! $is_recurring ) {
			return false;
		}

		update_post_meta( $log_id, Constants::META_MAIL_STOPPED, '1' );
		update_post_meta( $log_id, Constants::META_MAIL_STATUS, Constants::STATUS_STOPPED );

		// Clear cron
		$timestamp = wp_next_scheduled( Constants::CRON_HOOK_SINGLE, [ $log_id ] );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, Constants::CRON_HOOK_SINGLE, [ $log_id ] );
		}

		Helpers::log( sprintf( 'Recurring mail stopped (Log ID: %d)', $log_id ), 'info' );

		return true;
	}

	/**
	 * Send test mail
	 *
	 * @param string $email Test email address.
	 * @param string $subject Subject.
	 * @param string $html HTML content.
	 * @param int    $event_id Event ID for URL generation.
	 * @return array Result array.
	 */
	public function send_test_mail( $email, $subject, $html, $event_id ) {
		if ( ! is_email( $email ) ) {
			return [ 'ok' => false, 'message' => __( 'Ungültige E-Mail-Adresse.', 'event-tracker' ) ];
		}

		// Ensure $html is string
		$html = (string) $html;

		// Replace {{URL}} placeholder with test URL
		$test_url = home_url( '/eventtracker?id=' . $event_id );
		$html     = str_replace( '{{URL}}', $test_url, $html );
		$html     = str_replace( '{{NAME}}', 'Test User', $html );

		// Get webhook URL
		$settings    = get_option( Constants::OPT_KEY, [] );
		$webhook_url = isset( $settings['mail_webhook_url'] ) ? $settings['mail_webhook_url'] : '';

		if ( ! $webhook_url ) {
			return [ 'ok' => false, 'message' => __( 'Webhook-URL nicht konfiguriert.', 'event-tracker' ) ];
		}

		// Send test mail
		$data = [
			'event_id'  => $event_id,
			'subject'   => '[TEST] ' . $subject,
			'html'      => $html,
			'test_mode' => true,
			'test_email' => $email,
		];

		$response = wp_remote_post(
			$webhook_url,
			[
				'timeout' => 30,
				'body'    => wp_json_encode( $data ),
				'headers' => [
					'Content-Type' => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return [ 'ok' => false, 'message' => $response->get_error_message() ];
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 300 ) {
			return [ 'ok' => true, 'message' => __( 'Testmail gesendet.', 'event-tracker' ) ];
		} else {
			return [ 'ok' => false, 'message' => sprintf( __( 'Fehler: HTTP %d', 'event-tracker' ), $code ) ];
		}
	}
}
