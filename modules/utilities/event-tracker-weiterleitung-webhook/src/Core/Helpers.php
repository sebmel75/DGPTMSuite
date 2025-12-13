<?php
/**
 * Event Tracker Helper Functions
 *
 * @package EventTracker\Core
 * @since 2.0.0
 */

namespace EventTracker\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Helpers Class
 */
class Helpers {

	/**
	 * Cap Override Flag
	 *
	 * @var bool
	 */
	private static $cap_override = false;

	/**
	 * Check if user has plugin access
	 *
	 * @param int $user_id User ID (0 = current user).
	 * @return bool
	 */
	public static function user_has_access( $user_id = 0 ) {
		$uid = $user_id ?: get_current_user_id();
		if ( ! $uid ) {
			return false;
		}
		return get_user_meta( $uid, Constants::USER_META_ACCESS, true ) === '1';
	}

	/**
	 * Begin capability override
	 */
	public static function begin_cap_override() {
		self::$cap_override = true;
	}

	/**
	 * End capability override
	 */
	public static function end_cap_override() {
		self::$cap_override = false;
	}

	/**
	 * Check if cap override is active
	 *
	 * @return bool
	 */
	public static function is_cap_override() {
		return self::$cap_override;
	}

	/**
	 * Check if event is valid now (including multi-day events)
	 *
	 * @param int $event_id Event post ID.
	 * @param int $now      Current timestamp (0 = now).
	 * @return bool
	 */
	public static function is_event_valid( $event_id, $now = 0 ) {
		if ( ! $now ) {
			$now = time();
		}

		// Check main time range
		$start = (int) get_post_meta( $event_id, Constants::META_START_TS, true );
		$end   = (int) get_post_meta( $event_id, Constants::META_END_TS, true );

		if ( $start && $end && ( $now >= $start ) && ( $now <= $end ) ) {
			return true;
		}

		// Check additional dates (multi-day events)
		$additional = get_post_meta( $event_id, Constants::META_ADDITIONAL_DATES, true );
		if ( is_array( $additional ) && ! empty( $additional ) ) {
			foreach ( $additional as $range ) {
				if ( ! is_array( $range ) ) {
					continue;
				}

				$range_start = isset( $range['start'] ) ? (int) $range['start'] : 0;
				$range_end   = isset( $range['end'] ) ? (int) $range['end'] : 0;

				if ( $range_start && $range_end && ( $now >= $range_start ) && ( $now <= $range_end ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check if current request is plugin admin request
	 *
	 * @return bool
	 */
	public static function is_plugin_admin_request() {
		if ( ! is_admin() ) {
			return false;
		}

		// Check screen
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( $screen && isset( $screen->post_type ) ) {
				return in_array(
					$screen->post_type,
					[ Constants::CPT, Constants::CPT_MAIL_LOG, Constants::CPT_MAIL_TPL ],
					true
				);
			}
		}

		// Check REQUEST_URI
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		if ( strpos( $uri, 'post_type=' . Constants::CPT ) !== false ||
		     strpos( $uri, 'post_type=' . Constants::CPT_MAIL_LOG ) !== false ||
		     strpos( $uri, 'post_type=' . Constants::CPT_MAIL_TPL ) !== false ) {
			return true;
		}

		// Check AJAX
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : '';
			return strpos( $action, 'et_' ) === 0;
		}

		return false;
	}

	/**
	 * Render notice box
	 *
	 * @param string $message Message text.
	 * @param string $type    Notice type (success|error).
	 * @return string HTML
	 */
	public static function notice( $message, $type = 'success' ) {
		$color = ( $type === 'success' ) ? '#d1fae5' : '#fee2e2';
		return sprintf(
			'<div style="margin:16px 0;padding:10px;border-radius:8px;background:%s;">%s</div>',
			esc_attr( $color ),
			esc_html( $message )
		);
	}

	/**
	 * Sanitize datetime-local input
	 *
	 * @param string $input Input value.
	 * @return int Timestamp or 0
	 */
	public static function sanitize_datetime_local( $input ) {
		if ( empty( $input ) ) {
			return 0;
		}

		try {
			$dt = new \DateTimeImmutable( $input, wp_timezone() );
			return $dt->getTimestamp();
		} catch ( \Exception $e ) {
			return 0;
		}
	}

	/**
	 * Format timestamp for datetime-local input
	 *
	 * @param int $timestamp Unix timestamp.
	 * @return string
	 */
	public static function format_datetime_local( $timestamp ) {
		if ( ! $timestamp ) {
			return '';
		}

		try {
			$dt = new \DateTimeImmutable( '@' . $timestamp );
			$dt = $dt->setTimezone( wp_timezone() );
			return $dt->format( 'Y-m-d\TH:i' );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Log message via DGPTM Logger if available
	 *
	 * @param string $message Message to log.
	 * @param string $level   Log level (info|error|warning).
	 */
	public static function log( $message, $level = 'info' ) {
		if ( ! class_exists( 'DGPTM_Logger' ) ) {
			return;
		}

		$prefix = 'Event Tracker: ';

		switch ( $level ) {
			case 'error':
				\DGPTM_Logger::error( $prefix . $message );
				break;
			case 'warning':
				\DGPTM_Logger::warning( $prefix . $message );
				break;
			default:
				\DGPTM_Logger::info( $prefix . $message );
				break;
		}
	}
}
