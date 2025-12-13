<?php
/**
 * Event Tracker Helper Functions
 *
 * Hilfsfunktionen und Utilities
 *
 * @package Event_Tracker
 * @since 1.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helper Functions Class
 */
class ET_Helpers {

	/** @var bool Cap-Override Flag */
	private static $cap_override = false;

	/**
	 * Prüft ob User Plugin-Zugriff hat
	 *
	 * @param int $user_id User ID (0 = current user)
	 * @return bool
	 */
	public static function user_has_plugin_access( $user_id = 0 ) {
		$uid = $user_id ?: get_current_user_id();
		if ( ! $uid ) {
			return false;
		}
		return get_user_meta( $uid, ET_Constants::USER_META_ACCESS, true ) === '1';
	}

	/**
	 * Startet Cap-Override (für Plugin-interne Operationen)
	 */
	public static function begin_cap_override() {
		self::$cap_override = true;
	}

	/**
	 * Beendet Cap-Override
	 */
	public static function end_cap_override() {
		self::$cap_override = false;
	}

	/**
	 * Prüft ob Cap-Override aktiv ist
	 *
	 * @return bool
	 */
	public static function is_cap_override_active() {
		return self::$cap_override;
	}

	/**
	 * Prüft ob Event aktuell gültig ist (inkl. zusätzliche Termine)
	 *
	 * @param int $event_id Event Post ID
	 * @param int $now      Timestamp (0 = jetzt)
	 * @return bool
	 */
	public static function is_event_valid_now( $event_id, $now = 0 ) {
		if ( ! $now ) {
			$now = time();
		}

		// Haupt-Zeitraum prüfen
		$start = (int) get_post_meta( $event_id, ET_Constants::META_START_TS, true );
		$end   = (int) get_post_meta( $event_id, ET_Constants::META_END_TS, true );

		if ( $start && $end && ( $now >= $start ) && ( $now <= $end ) ) {
			return true;
		}

		// Zusätzliche Termine prüfen (mehrtägige Events)
		$additional = get_post_meta( $event_id, ET_Constants::META_ADDITIONAL_DATES, true );
		if ( is_array( $additional ) && ! empty( $additional ) ) {
			foreach ( $additional as $date_range ) {
				if ( ! is_array( $date_range ) ) {
					continue;
				}
				$range_start = isset( $date_range['start'] ) ? (int) $date_range['start'] : 0;
				$range_end   = isset( $date_range['end'] )   ? (int) $date_range['end']   : 0;

				if ( $range_start && $range_end && ( $now >= $range_start ) && ( $now <= $range_end ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Rendert Notice-Box
	 *
	 * @param string $msg  Nachricht
	 * @param string $type success|error
	 * @return string HTML
	 */
	public static function notice( $msg, $type = 'success' ) {
		$color = $type === 'success' ? '#d1fae5' : '#fee2e2';
		return '<div style="margin:16px 0;padding:10px;border-radius:8px;background:' . esc_attr( $color ) . ';">' . esc_html( $msg ) . '</div>';
	}

	/**
	 * Prüft ob aktueller Request ein Plugin-Admin-Request ist
	 *
	 * @return bool
	 */
	public static function is_plugin_admin_request() {
		if ( ! is_admin() ) {
			return false;
		}

		if ( function_exists( 'get_current_screen' ) ) {
			$scr = get_current_screen();
			if ( $scr && isset( $scr->post_type ) && in_array( $scr->post_type, [ ET_Constants::CPT, ET_Constants::CPT_MAIL_LOG, ET_Constants::CPT_MAIL_TPL ], true ) ) {
				return true;
			}
		}

		// Fallback: aktuelle URL prüfen
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( strpos( $request_uri, 'post_type=' . ET_Constants::CPT ) !== false ||
		     strpos( $request_uri, 'post_type=' . ET_Constants::CPT_MAIL_LOG ) !== false ||
		     strpos( $request_uri, 'post_type=' . ET_Constants::CPT_MAIL_TPL ) !== false ) {
			return true;
		}

		// AJAX-Kontext
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
			if ( strpos( $action, 'et_' ) === 0 ) {
				return true;
			}
		}

		return false;
	}
}
