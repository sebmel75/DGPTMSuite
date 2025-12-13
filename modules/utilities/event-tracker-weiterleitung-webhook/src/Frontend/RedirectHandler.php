<?php
/**
 * Event Tracker Redirect Handler
 *
 * @package EventTracker\Frontend
 * @since 2.0.0
 */

namespace EventTracker\Frontend;

use EventTracker\Core\Constants;
use EventTracker\Core\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirect Handler Class
 */
class RedirectHandler {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_filter( 'template_include', [ $this, 'intercept_template' ] );
	}

	/**
	 * Intercept template for /eventtracker
	 *
	 * @param string $template Template path.
	 * @return string
	 */
	public function intercept_template( $template ) {
		// Check if this is eventtracker page
		$is_tracker = get_query_var( Constants::QUERY_KEY_TRACKER );

		// Also check if URL is /eventtracker directly
		if ( ! $is_tracker ) {
			$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			if ( strpos( $request_uri, '/eventtracker' ) === false ) {
				return $template;
			}
		}

		// Get event ID from query (try both $_GET and query_var)
		$event_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
		if ( ! $event_id ) {
			$event_id = get_query_var( Constants::QUERY_KEY_EVENT );
		}
		if ( ! $event_id ) {
			$this->render_simple_error( __( 'Keine Veranstaltung angegeben.', 'event-tracker' ) );
			exit;
		}

		$event_id = absint( $event_id );

		// Validate event exists
		if ( get_post_type( $event_id ) !== Constants::CPT ) {
			$this->render_simple_error( __( 'Veranstaltung nicht gefunden.', 'event-tracker' ) );
			exit;
		}

		// Get event data
		$name         = get_the_title( $event_id );
		$start        = (int) get_post_meta( $event_id, Constants::META_START_TS, true );
		$end          = (int) get_post_meta( $event_id, Constants::META_END_TS, true );
		$target       = get_post_meta( $event_id, Constants::META_REDIRECT_URL, true );
		$zoho         = get_post_meta( $event_id, Constants::META_ZOHO_ID, true );
		$recording    = get_post_meta( $event_id, Constants::META_RECORDING_URL, true );
		$iframe_enable = get_post_meta( $event_id, Constants::META_IFRAME_ENABLE, true ) === '1';
		$iframe_url   = get_post_meta( $event_id, Constants::META_IFRAME_URL, true );

		// Get settings
		$opts        = get_option( Constants::OPT_KEY, [] );
		$webhook_url = isset( $opts['webhook_url'] ) ? $opts['webhook_url'] : '';

		// Check if event is valid (time-based, including multi-day)
		$now      = time();
		$is_valid = Helpers::is_event_valid( $event_id, $now );

		$tz = wp_timezone();
		$df = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		// Log access
		Helpers::log( sprintf( 'Event access: %s (ID: %d, Valid: %s)', $name, $event_id, $is_valid ? 'Yes' : 'No' ), 'info' );

		/* ---------- 1a) Innerhalb des Zeitraums + Iframe-Modus ---------- */
		if ( $is_valid && $iframe_enable && $iframe_url ) {
			// Trigger webhook
			$this->trigger_webhook( $event_id, $webhook_url, $zoho, $_GET );

			$this->render_iframe_page( $name, $iframe_url );
			exit;
		}

		/* ---------- 1b) Innerhalb des Zeitraums: Webhook + Redirect (Standard) ---------- */
		if ( $is_valid && $target && esc_url_raw( $target, [ 'http', 'https' ] ) ) {
			// Trigger webhook
			$this->trigger_webhook( $event_id, $webhook_url, $zoho, $_GET );

			// Redirect to target
			wp_redirect( $target, 302 );
			exit;
		}

		/* ---------- 2) Nach Veranstaltungsende: Aufzeichnung oder Alternativtext ---------- */
		// Prüfe ob Event definitiv vorbei ist (alle Zeiträume beendet)
		$all_ended = true;

		// Prüfe Haupt-Zeitraum
		if ( $end && $now <= $end ) {
			$all_ended = false;
		}

		// Prüfe zusätzliche Termine
		$additional = get_post_meta( $event_id, Constants::META_ADDITIONAL_DATES, true );
		if ( is_array( $additional ) && ! empty( $additional ) ) {
			foreach ( $additional as $range ) {
				if ( ! is_array( $range ) ) {
					continue;
				}
				$range_end = isset( $range['end'] ) ? (int) $range['end'] : 0;
				if ( $range_end && $now <= $range_end ) {
					$all_ended = false;
					break;
				}
			}
		}

		if ( $all_ended && $end && $now > $end ) {
			// Event ist vorbei
			if ( $recording && esc_url_raw( $recording, [ 'http', 'https' ] ) ) {
				$this->render_recording_page( $name, $start, $end, $recording, $tz, $df );
				exit;
			}

			// Kein Recording vorhanden
			$alt  = isset( $opts['recording_alt_text'] ) && $opts['recording_alt_text'] !== '' ? $opts['recording_alt_text'] : 'Aufzeichnung bald verfügbar.';
			$html = '
		<style>
			.et-error-wrap{min-height:40vh;display:flex;align-items:center;justify-content:center;padding:24px}
			.et-card{max-width:720px;width:100%;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 1px 2px rgba(0,0,0,.05)}
			.et-card h2{margin:0 0 12px 0;font-size:1.25rem}
			.et-card p{margin:0 0 8px 0;line-height:1.5}
			.et-muted{color:#6b7280;font-size:.9rem}
			@media (max-width:640px){.et-card{padding:18px;border-radius:12px}}
		</style>
		<div class="et-error-wrap"><div class="et-card">
			<h2>' . esc_html__( 'Veranstaltung beendet', 'event-tracker' ) . '</h2>
			<p>' . esc_html( $alt ) . '</p>
		</div></div>';
			status_header( 200 );
			nocache_headers();
			wp_die( $html, esc_html__( 'Veranstaltung beendet', 'event-tracker' ), [ 'response' => 200 ] );
		}

		/* ---------- 3) Vor Beginn oder zwischen Teilen: Hinweis + {countdown} ---------- */
		// Finde nächsten Start
		$next_start = null;
		$next_end   = null;

		// Prüfe Haupt-Start
		if ( $start && $start > $now ) {
			$next_start = $start;
			$next_end   = $end;
		}

		// Prüfe zusätzliche Termine
		if ( is_array( $additional ) && ! empty( $additional ) ) {
			foreach ( $additional as $range ) {
				if ( ! is_array( $range ) ) {
					continue;
				}
				$range_start = isset( $range['start'] ) ? (int) $range['start'] : 0;
				$range_end   = isset( $range['end'] ) ? (int) $range['end'] : 0;

				if ( $range_start && $range_start > $now ) {
					// Wenn noch kein next_start gesetzt oder dieser früher ist
					if ( ! $next_start || $range_start < $next_start ) {
						$next_start = $range_start;
						$next_end   = $range_end;
					}
				}
			}
		}

		// Verwende den gefundenen nächsten Start oder fallback auf Haupt-Start
		$countdown_start = $next_start ? $next_start : $start;
		$countdown_end   = $next_end ? $next_end : $end;

		// Template aus Settings
		$tpl  = isset( $opts['error_message'] ) ? $opts['error_message'] : 'Dieses Event ist derzeit nicht aktiv. {name} ist gültig von {from} bis {to}. {countdown}';
		$from = $countdown_start ? wp_date( $df, $countdown_start, $tz ) : '—';
		$to   = $countdown_end   ? wp_date( $df, $countdown_end,   $tz ) : '—';

		$countdown_placeholder = '<span id="et-ct"></span>';
		$message = strtr( $tpl, [
			'{name}'      => esc_html( $name ),
			'{from}'      => esc_html( $from ),
			'{to}'        => esc_html( $to ),
			'{countdown}' => $countdown_placeholder,
		] );

		$html = '
	<style>
		.et-error-wrap{min-height:40vh;display:flex;align-items:center;justify-content:center;padding:24px}
		.et-card{max-width:720px;width:100%;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 1px 2px rgba(0,0,0,.05)}
		.et-card h2{margin:0 0 12px 0;font-size:1.25rem}
		.et-card p{margin:0 0 8px 0;line-height:1.5}
		.et-muted{color:#6b7280;font-size:.9rem}
		@media (max-width:640px){.et-card{padding:18px;border-radius:12px}}
	</style>
	<div class="et-error-wrap">
		<div class="et-card">
			<p>' . wp_kses_post( $message ) . '</p>
			<p class="et-muted">' . esc_html__( 'Bitte warten Sie bis zum Veranstaltungsbeginn.', 'event-tracker' ) . '</p>
		</div>
	</div>
	<script>
	(function(){
		var start = ' . (int) $countdown_start . ' * 1000;
		var el = document.getElementById("et-ct");
		function plural(n, s, p){return n===1?s:p;}
		function tick(){
			var d = Math.max(0, Math.floor((start - Date.now())/1000));
			var h = Math.floor(d/3600); d -= h*3600;
			var m = Math.floor(d/60);   d -= m*60;
			var s = d;
			var txt = "Sie werden in " + h + " " + plural(h,"Stunde","Stunden") + " " + m + " " + plural(m,"Minute","Minuten") + " und " + s + " " + plural(s,"Sekunde","Sekunden") + " zum Webinar weitergeleitet";
			if (el) el.textContent = txt;
			if (h===0 && m===0 && s===0) {
				// Jetzt ist der Start erreicht → Reload: Server löst Webhook aus und leitet weiter/zeigt Iframe
				location.replace(location.href);
			}
		}
		tick(); setInterval(tick, 1000);
	}());
	</script>';

		status_header( 200 );
		nocache_headers();
		wp_die( $html, esc_html__( 'Event noch nicht aktiv', 'event-tracker' ), [ 'response' => 200 ] );
	}

	/**
	 * Trigger webhook for event access
	 *
	 * @param int    $event_id Event ID.
	 * @param string $webhook_url Webhook URL from settings.
	 * @param string $zoho Zoho ID.
	 * @param array  $get_params GET parameters.
	 */
	private function trigger_webhook( $event_id, $webhook_url, $zoho, $get_params ) {
		if ( ! $webhook_url ) {
			return;
		}

		// Prepare webhook query
		$query = [];
		foreach ( $get_params as $k => $v ) {
			if ( is_scalar( $v ) ) {
				$query[ sanitize_key( $k ) ] = sanitize_text_field( wp_unslash( (string) $v ) );
			}
		}
		$query['event_id'] = (string) $event_id;
		if ( $zoho !== '' ) {
			$query['zoho_id'] = $zoho;
		}

		$final_webhook = add_query_arg( $query, $webhook_url );
		wp_remote_get( $final_webhook, [ 'timeout' => 3, 'redirection' => 0 ] );

		Helpers::log( sprintf( 'Webhook triggered for event %d', $event_id ), 'info' );
	}

	/**
	 * Render iframe page
	 *
	 * @param string $title Event title.
	 * @param string $iframe_url Iframe URL.
	 */
	private function render_iframe_page( $title, $iframe_url ) {
		$html = '
<!DOCTYPE html>
<html ' . get_language_attributes() . '>
<head>
	<meta charset="' . get_bloginfo( 'charset' ) . '">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<title>' . esc_html( $title ) . ' - ' . get_bloginfo( 'name' ) . '</title>
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
		.header { background: #1e293b; color: #fff; padding: 1rem; text-align: center; }
		.header h1 { font-size: 1.5rem; font-weight: 600; }
		.iframe-container { position: relative; width: 100%; height: calc(100vh - 80px); }
		.iframe-container iframe { width: 100%; height: 100%; border: none; }
		.mobile-message { display: none; padding: 2rem; text-align: center; }
		.mobile-message .btn { display: inline-block; margin-top: 1rem; padding: 0.75rem 1.5rem; background: #2563eb; color: #fff; text-decoration: none; border-radius: 8px; }
		@media (max-width: 768px) {
			.iframe-container { display: none; }
			.mobile-message { display: block; }
		}
	</style>
</head>
<body>
	<div class="header">
		<h1>' . esc_html( $title ) . '</h1>
	</div>
	<div class="iframe-container">
		<iframe src="' . esc_url( $iframe_url ) . '" allow="camera; microphone; fullscreen" allowfullscreen></iframe>
	</div>
	<div class="mobile-message">
		<h2>' . esc_html__( 'Mobiler Zugriff', 'event-tracker' ) . '</h2>
		<p>' . esc_html__( 'Auf mobilen Geräten öffnen wir die Veranstaltung in einem neuen Fenster.', 'event-tracker' ) . '</p>
		<a href="' . esc_url( $iframe_url ) . '" target="_blank" class="btn">' . esc_html__( 'Zum Live-Stream', 'event-tracker' ) . '</a>
	</div>
</body>
</html>';

		status_header( 200 );
		nocache_headers();
		wp_die( $html, esc_html__( 'Webinar live', 'event-tracker' ), [ 'response' => 200 ] );
	}

	/**
	 * Render recording page
	 *
	 * @param string $name Event name.
	 * @param int    $start Start timestamp.
	 * @param int    $end End timestamp.
	 * @param string $recording_url Recording URL.
	 * @param object $tz Timezone.
	 * @param string $df Date format.
	 */
	private function render_recording_page( $name, $start, $end, $recording_url, $tz, $df ) {
		$from = $start ? wp_date( $df, $start, $tz ) : '—';
		$to   = $end   ? wp_date( $df, $end,   $tz ) : '—';

		$html = '
	<style>
		.et-error-wrap{min-height:40vh;display:flex;align-items:center;justify-content:center;padding:24px}
		.et-card{max-width:720px;width:100%;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 1px 2px rgba(0,0,0,.05)}
		.et-card h2{margin:0 0 12px 0;font-size:1.25rem}
		.et-card p{margin:0 0 8px 0;line-height:1.5}
		.et-muted{color:#6b7280;font-size:.9rem}
		.et-actions{margin-top:12px}
		.et-actions a{display:inline-block;border:1px solid #e5e7eb;border-radius:8px;padding:.5rem .8rem;text-decoration:none}
		@media (max-width:640px){.et-card{padding:18px;border-radius:12px}}
	</style>
	<div class="et-error-wrap">
		<div class="et-card">
			<h2>' . esc_html__( 'Aufzeichnung', 'event-tracker' ) . '</h2>
			<p class="et-muted">' . esc_html( $name ) . ' • ' . esc_html( $from ) . ' → ' . esc_html( $to ) . '</p>
			<p>' . esc_html__( 'Sie werden zur Aufzeichnung weitergeleitet in', 'event-tracker' ) . ' <strong><span id="et-rec-ct">10</span> ' . esc_html__( 'Sekunden', 'event-tracker' ) . '</strong>.</p>
			<p class="et-actions"><a href="' . esc_url( $recording_url ) . '">' . esc_html__( 'Jetzt öffnen', 'event-tracker' ) . '</a></p>
			<noscript><p>' . esc_html__( 'JavaScript ist deaktiviert. Bitte klicken Sie auf „Jetzt öffnen", um zur Aufzeichnung zu gelangen.', 'event-tracker' ) . '</p></noscript>
		</div>
	</div>
	<script>(function(){var s=10,el=document.getElementById("et-rec-ct");var t=setInterval(function(){s--;if(el)el.textContent=String(Math.max(0,s));if(s<=0){clearInterval(t);location.href=' . wp_json_encode( esc_url( $recording_url ) ) . ';}},1000);}());</script>';

		status_header( 200 );
		nocache_headers();
		wp_die( $html, esc_html__( 'Aufzeichnung', 'event-tracker' ), [ 'response' => 200 ] );
	}

	/**
	 * Render simple error page
	 *
	 * @param string $message Error message.
	 */
	private function render_simple_error( $message ) {
		$html = '
	<style>
		* { margin: 0; padding: 0; box-sizing: border-box; }
		body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f9fafb; }
		.container { max-width: 600px; margin: 4rem auto; padding: 2rem; background: #fff; border-radius: 12px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); text-align: center; }
		.container h1 { font-size: 1.5rem; margin-bottom: 1rem; color: #dc2626; }
		.container p { font-size: 1.1rem; color: #64748b; }
	</style>
	<div class="container">
		<h1>' . esc_html__( 'Veranstaltung nicht verfügbar', 'event-tracker' ) . '</h1>
		<p>' . esc_html( $message ) . '</p>
	</div>';

		status_header( 200 );
		nocache_headers();
		wp_die( $html, esc_html__( 'Fehler', 'event-tracker' ), [ 'response' => 200 ] );
	}
}
