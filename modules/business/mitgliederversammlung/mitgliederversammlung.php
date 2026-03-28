<?php
/**
 * Plugin Name: DGPTM - Mitgliederversammlung
 * Description: Hybride Mitgliederversammlung mit anonymer Abstimmung, Anwesenheitserfassung und Beamer-Ansicht
 * Version: 1.0.0
 * Author: Sebastian Melzer
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DGPTM_MV_VERSION', '1.0.0' );
define( 'DGPTM_MV_PATH', plugin_dir_path( __FILE__ ) );
define( 'DGPTM_MV_URL', plugin_dir_url( __FILE__ ) );

if ( ! class_exists( 'DGPTM_Mitgliederversammlung' ) ) {

	final class DGPTM_Mitgliederversammlung {

		private static $instance = null;

		/** @var DGPTM_MV_Database */
		public $database;
		/** @var DGPTM_MV_Assembly */
		public $assembly;
		/** @var DGPTM_MV_Attendance */
		public $attendance;
		/** @var DGPTM_MV_Voting */
		public $voting;
		/** @var DGPTM_MV_Zoom */
		public $zoom;
		/** @var DGPTM_MV_CRM */
		public $crm;
		/** @var DGPTM_MV_Scanner */
		public $scanner;
		/** @var DGPTM_MV_Beamer */
		public $beamer;
		/** @var DGPTM_MV_Export */
		public $export;
		/** @var DGPTM_MV_Rest_API */
		public $rest_api;
		/** @var DGPTM_MV_Admin */
		public $admin;

		public static function instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		private function __construct() {
			$this->load_includes();
			$this->init_components();
			$this->register_hooks();
		}

		private function load_includes() {
			$dir = DGPTM_MV_PATH . 'includes/';
			require_once $dir . 'class-database.php';
			require_once $dir . 'class-assembly.php';
			require_once $dir . 'class-attendance.php';
			require_once $dir . 'class-voting.php';
			require_once $dir . 'class-zoom-integration.php';
			require_once $dir . 'class-crm-integration.php';
			require_once $dir . 'class-presence-scanner.php';
			require_once $dir . 'class-beamer.php';
			require_once $dir . 'class-export.php';
			require_once $dir . 'class-rest-api.php';
			require_once $dir . 'class-admin.php';
		}

		private function init_components() {
			$this->database   = new DGPTM_MV_Database();
			$this->assembly   = new DGPTM_MV_Assembly();
			$this->attendance = new DGPTM_MV_Attendance();
			$this->voting     = new DGPTM_MV_Voting();
			$this->zoom       = new DGPTM_MV_Zoom();
			$this->crm        = new DGPTM_MV_CRM();
			$this->scanner    = new DGPTM_MV_Scanner();
			$this->beamer     = new DGPTM_MV_Beamer();
			$this->export     = new DGPTM_MV_Export();
			$this->rest_api   = new DGPTM_MV_Rest_API();
			$this->admin      = new DGPTM_MV_Admin();
		}

		private function register_hooks() {
			register_activation_hook( __FILE__, [ $this->database, 'install' ] );
			add_action( 'plugins_loaded', [ $this->database, 'maybe_upgrade' ] );
			add_action( 'init', [ $this, 'register_shortcodes' ] );
			add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_frontend_assets' ] );
			add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );

			// User profile field
			add_action( 'show_user_profile', [ $this, 'user_profile_fields' ] );
			add_action( 'edit_user_profile', [ $this, 'user_profile_fields' ] );
			add_action( 'personal_options_update', [ $this, 'save_user_profile_fields' ] );
			add_action( 'edit_user_profile_update', [ $this, 'save_user_profile_fields' ] );
		}

		public function register_shortcodes() {
			// Versammlungssteuerung (Manager)
			add_shortcode( 'mv_manager', [ $this->assembly, 'shortcode_manager' ] );
			// Abstimmung (Mitglieder)
			add_shortcode( 'mv_vote', [ $this->voting, 'shortcode_vote' ] );
			// Beamer-Ansicht
			add_shortcode( 'mv_beamer', [ $this->beamer, 'shortcode_beamer' ] );
			// Anwesenheitsliste
			add_shortcode( 'mv_attendance', [ $this->attendance, 'shortcode_attendance' ] );
			// Praesenz-Scanner
			add_shortcode( 'mv_scanner', [ $this->scanner, 'shortcode_scanner' ] );
			// Manuelle Stimmabgabe (Manager)
			add_shortcode( 'mv_manual_vote', [ $this->voting, 'shortcode_manual_vote' ] );

			// Legacy-Aliase fuer Rueckwaertskompatibilitaet
			if ( ! shortcode_exists( 'manage_poll' ) ) {
				add_shortcode( 'manage_poll', [ $this->assembly, 'shortcode_manager' ] );
			}
			if ( ! shortcode_exists( 'member_vote' ) ) {
				add_shortcode( 'member_vote', [ $this->voting, 'shortcode_vote' ] );
			}
			if ( ! shortcode_exists( 'beamer_view' ) ) {
				add_shortcode( 'beamer_view', [ $this->beamer, 'shortcode_beamer' ] );
			}
			if ( ! shortcode_exists( 'dgptm_presence_scanner' ) ) {
				add_shortcode( 'dgptm_presence_scanner', [ $this->scanner, 'shortcode_scanner' ] );
			}
			if ( ! shortcode_exists( 'dgptm_presence_table' ) ) {
				add_shortcode( 'dgptm_presence_table', [ $this->attendance, 'shortcode_attendance' ] );
			}
		}

		public function enqueue_frontend_assets() {
			wp_register_style(
				'dgptm-mv-frontend',
				DGPTM_MV_URL . 'assets/css/frontend.css',
				[],
				DGPTM_MV_VERSION
			);
			wp_register_script(
				'dgptm-mv-voting',
				DGPTM_MV_URL . 'assets/js/voting.js',
				[ 'jquery' ],
				DGPTM_MV_VERSION,
				true
			);
			wp_register_script(
				'dgptm-mv-scanner',
				DGPTM_MV_URL . 'assets/js/scanner.js',
				[ 'jquery' ],
				DGPTM_MV_VERSION,
				true
			);
			wp_register_script(
				'dgptm-mv-beamer',
				DGPTM_MV_URL . 'assets/js/beamer.js',
				[ 'jquery' ],
				DGPTM_MV_VERSION,
				true
			);

			wp_localize_script( 'dgptm-mv-voting', 'dgptm_mv', [
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'rest_url'   => rest_url( 'dgptm-mv/v1/' ),
				'rest_nonce' => wp_create_nonce( 'wp_rest' ),
				'nonce'      => wp_create_nonce( 'dgptm_mv_nonce' ),
			] );
			wp_localize_script( 'dgptm-mv-scanner', 'dgptm_mv', [
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'rest_url'   => rest_url( 'dgptm-mv/v1/' ),
				'rest_nonce' => wp_create_nonce( 'wp_rest' ),
				'nonce'      => wp_create_nonce( 'dgptm_mv_nonce' ),
			] );
			wp_localize_script( 'dgptm-mv-beamer', 'dgptm_mv', [
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'rest_url'   => rest_url( 'dgptm-mv/v1/' ),
				'rest_nonce' => wp_create_nonce( 'wp_rest' ),
				'nonce'      => wp_create_nonce( 'dgptm_mv_nonce' ),
			] );
		}

		public function enqueue_admin_assets( $hook ) {
			if ( strpos( $hook, 'dgptm-mv' ) === false ) {
				return;
			}
			wp_enqueue_style(
				'dgptm-mv-admin',
				DGPTM_MV_URL . 'assets/css/admin.css',
				[],
				DGPTM_MV_VERSION
			);
			wp_enqueue_script(
				'dgptm-mv-admin',
				DGPTM_MV_URL . 'assets/js/admin.js',
				[ 'jquery' ],
				DGPTM_MV_VERSION,
				true
			);
			wp_localize_script( 'dgptm-mv-admin', 'dgptm_mv', [
				'ajax_url'   => admin_url( 'admin-ajax.php' ),
				'rest_url'   => rest_url( 'dgptm-mv/v1/' ),
				'rest_nonce' => wp_create_nonce( 'wp_rest' ),
				'nonce'      => wp_create_nonce( 'dgptm_mv_nonce' ),
			] );
		}

		public function user_profile_fields( $user ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$is_manager = get_user_meta( $user->ID, 'dgptm_mv_manager', true );
			?>
			<h2>Mitgliederversammlung</h2>
			<table class="form-table">
				<tr>
					<th><label for="dgptm_mv_manager">Versammlungsleiter</label></th>
					<td>
						<label>
							<input type="checkbox" name="dgptm_mv_manager" id="dgptm_mv_manager" value="1" <?php checked( $is_manager, '1' ); ?>>
							Zugriff auf [mv_manager], [mv_beamer] und [mv_manual_vote]
						</label>
					</td>
				</tr>
			</table>
			<?php
		}

		public function save_user_profile_fields( $user_id ) {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			$val = isset( $_POST['dgptm_mv_manager'] ) ? '1' : '0';
			update_user_meta( $user_id, 'dgptm_mv_manager', $val );
		}

		/**
		 * Prueft ob der aktuelle User MV-Manager ist.
		 */
		public static function is_manager( $user_id = null ) {
			if ( null === $user_id ) {
				$user_id = get_current_user_id();
			}
			if ( ! $user_id ) {
				return false;
			}
			if ( current_user_can( 'manage_options' ) ) {
				return true;
			}
			return (bool) get_user_meta( $user_id, 'dgptm_mv_manager', true );
		}

		/**
		 * Prueft ob ein User stimmberechtigt ist (ordentliches Mitglied gemaess Satzung §4/§7).
		 */
		public static function is_eligible_voter( $user_id = null ) {
			if ( null === $user_id ) {
				$user_id = get_current_user_id();
			}
			if ( ! $user_id ) {
				return false;
			}
			// Pruefe Mitgliedsstatus via User-Meta oder Rolle
			$mv_flag = get_user_meta( $user_id, 'mitgliederversammlung', true );
			if ( $mv_flag === 'true' || $mv_flag === '1' || $mv_flag === true ) {
				return true;
			}
			// Pruefe Zoho-Mitgliedsart
			$art = get_user_meta( $user_id, 'dgptm_vote_zoho_mitgliedsart', true );
			if ( stripos( $art, 'ordentlich' ) !== false ) {
				return true;
			}
			// Pruefe WP-Rolle
			$user = get_userdata( $user_id );
			if ( $user && in_array( 'mitglied', (array) $user->roles, true ) ) {
				return true;
			}
			return false;
		}
	}
}

// Initialisierung
if ( ! isset( $GLOBALS['dgptm_mv_initialized'] ) ) {
	$GLOBALS['dgptm_mv_initialized'] = true;
	DGPTM_Mitgliederversammlung::instance();
}
