<?php
/**
 * DGPTM Suite Health-Check REST API
 *
 * Stellt einen authentifizierten REST-Endpoint bereit, der aktuelle
 * Fehler, Warnungen und Modul-Status zurueckgibt.
 *
 * Endpoint: /wp-json/dgptm/v1/health-check
 * Auth: Bearer-Token (in wp_options: dgptm_health_check_token)
 *
 * @package DGPTM_Suite
 * @since 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DGPTM_Health_Check {

	private static $instance = null;

	const OPT_TOKEN = 'dgptm_health_check_token';

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'admin_init', [ $this, 'maybe_generate_token' ] );
	}

	/**
	 * Token generieren falls noch keiner existiert
	 */
	public function maybe_generate_token() {
		if ( ! get_option( self::OPT_TOKEN ) ) {
			update_option( self::OPT_TOKEN, wp_generate_password( 48, false ), false );
		}
	}

	/**
	 * REST-Routen registrieren
	 */
	public function register_routes() {
		register_rest_route( 'dgptm/v1', '/health-check', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_health_check' ],
			'permission_callback' => [ $this, 'check_auth' ],
		] );
	}

	/**
	 * Auth pruefen: Bearer-Token oder eingeloggter Admin
	 *
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function check_auth( $request ) {
		// Admin-User immer erlaubt
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Bearer-Token pruefen
		$auth = $request->get_header( 'Authorization' );
		if ( $auth && preg_match( '/^Bearer\s+(.+)$/i', $auth, $m ) ) {
			$token = get_option( self::OPT_TOKEN, '' );
			return $token && hash_equals( $token, trim( $m[1] ) );
		}

		return false;
	}

	/**
	 * Health-Check ausfuehren
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_health_check( $request ) {
		$hours = absint( $request->get_param( 'hours' ) ?: 24 );
		$hours = min( $hours, 168 ); // Max 7 Tage

		$result = [
			'timestamp'   => current_time( 'c' ),
			'site_url'    => home_url(),
			'suite_version' => defined( 'DGPTM_SUITE_VERSION' ) ? DGPTM_SUITE_VERSION : 'unknown',
			'php_version' => PHP_VERSION,
			'wp_version'  => get_bloginfo( 'version' ),
		];

		// 1. Letzte Fehler/Warnungen aus dem Logger
		$result['logs'] = $this->get_recent_logs( $hours );

		// 2. Modul-Status
		$result['modules'] = $this->get_module_status();

		// 3. System-Checks
		$result['system'] = $this->get_system_checks();

		// 4. Zusammenfassung
		$error_count   = $result['logs']['counts']['error'] ?? 0;
		$critical_count = $result['logs']['counts']['critical'] ?? 0;
		$warning_count = $result['logs']['counts']['warning'] ?? 0;

		if ( $critical_count > 0 ) {
			$result['status'] = 'critical';
			$result['summary'] = sprintf( '%d kritische Fehler in den letzten %d Stunden', $critical_count, $hours );
		} elseif ( $error_count > 0 ) {
			$result['status'] = 'error';
			$result['summary'] = sprintf( '%d Fehler in den letzten %d Stunden', $error_count, $hours );
		} elseif ( $warning_count > 0 ) {
			$result['status'] = 'warning';
			$result['summary'] = sprintf( '%d Warnungen in den letzten %d Stunden', $warning_count, $hours );
		} else {
			$result['status'] = 'healthy';
			$result['summary'] = sprintf( 'Keine Fehler in den letzten %d Stunden', $hours );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Letzte Log-Eintraege abrufen
	 */
	private function get_recent_logs( $hours ) {
		if ( ! class_exists( 'DGPTM_Logger' ) ) {
			return [ 'available' => false, 'message' => 'Logger nicht geladen' ];
		}

		$cutoff = date( 'Y-m-d H:i:s', time() - ( $hours * 3600 ) );

		// Fehler und Warnungen
		$errors = DGPTM_Logger::query_logs( [
			'level'     => [ 'error', 'critical' ],
			'date_from' => $cutoff,
			'per_page'  => 50,
			'order'     => 'DESC',
		] );

		$warnings = DGPTM_Logger::query_logs( [
			'level'     => 'warning',
			'date_from' => $cutoff,
			'per_page'  => 20,
			'order'     => 'DESC',
		] );

		// Counts pro Level
		$counts = [];
		$count_result = DGPTM_Logger::query_logs( [
			'level'     => [ 'error', 'critical', 'warning' ],
			'date_from' => $cutoff,
			'per_page'  => 1,
		] );

		// Zaehlung aus DB
		global $wpdb;
		$table = $wpdb->prefix . 'dgptm_logs';
		if ( DGPTM_Logger_Installer::table_exists() ) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT level, COUNT(*) as cnt FROM {$table} WHERE timestamp >= %s AND level IN ('error','critical','warning') GROUP BY level",
				$cutoff
			), ARRAY_A );
			foreach ( $rows as $r ) {
				$counts[ $r['level'] ] = (int) $r['cnt'];
			}
		}

		// Fehler nach Modul gruppieren
		$by_module = [];
		foreach ( $errors['logs'] as $log ) {
			$mod = $log['module_id'] ?: '(kein Modul)';
			if ( ! isset( $by_module[ $mod ] ) ) {
				$by_module[ $mod ] = 0;
			}
			$by_module[ $mod ]++;
		}
		arsort( $by_module );

		return [
			'available'  => true,
			'hours'      => $hours,
			'counts'     => $counts,
			'by_module'  => $by_module,
			'errors'     => array_slice( $errors['logs'], 0, 20 ),
			'warnings'   => array_slice( $warnings['logs'], 0, 10 ),
		];
	}

	/**
	 * Modul-Status abrufen
	 */
	private function get_module_status() {
		$module_loader = dgptm_suite()->get_module_loader();
		$available = $module_loader->get_available_modules();
		$loaded    = $module_loader->get_loaded_modules();
		$settings  = get_option( 'dgptm_suite_settings', [] );
		$active    = $settings['active_modules'] ?? [];

		$active_count  = count( array_filter( $active ) );
		$loaded_count  = count( $loaded );
		$total         = count( $available );

		// Fehlgeschlagene Aktivierungen
		$safe_loader = DGPTM_Safe_Loader::get_instance();
		$failed      = $safe_loader->get_failed_activations();
		$failed_list = [];
		foreach ( $failed as $mid => $info ) {
			$failed_list[] = [
				'module'  => $mid,
				'error'   => $info['error']['message'] ?? $info['error'] ?? 'Unbekannt',
				'time'    => date( 'c', $info['timestamp'] ?? 0 ),
			];
		}

		return [
			'total'    => $total,
			'active'   => $active_count,
			'loaded'   => $loaded_count,
			'failed'   => $failed_list,
			'active_not_loaded' => $active_count - $loaded_count,
		];
	}

	/**
	 * System-Checks
	 */
	private function get_system_checks() {
		$checks = [];

		// PHP-Speicher
		$mem_limit = ini_get( 'memory_limit' );
		$mem_usage = memory_get_peak_usage( true );
		$checks['memory'] = [
			'limit'    => $mem_limit,
			'peak_mb'  => round( $mem_usage / 1048576, 1 ),
		];

		// Cron-Status
		$next_cleanup = wp_next_scheduled( 'dgptm_logs_cleanup' );
		$checks['cron'] = [
			'logs_cleanup' => $next_cleanup ? date( 'c', $next_cleanup ) : 'nicht geplant',
		];

		// debug.log Groesse
		$debug_log = WP_CONTENT_DIR . '/debug.log';
		if ( file_exists( $debug_log ) ) {
			$checks['debug_log_size_mb'] = round( filesize( $debug_log ) / 1048576, 2 );
		}

		// Zoho-Verbindung
		if ( function_exists( 'dgptm_zoho_auth' ) ) {
			$checks['zoho_connected'] = dgptm_zoho_auth()->is_connected();
		}

		return $checks;
	}

	/**
	 * Token fuer Anzeige in Admin abrufen
	 */
	public static function get_token() {
		return get_option( self::OPT_TOKEN, '' );
	}
}
