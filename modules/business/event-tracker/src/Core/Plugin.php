<?php
/**
 * Event Tracker Main Plugin Class
 *
 * @package EventTracker\Core
 * @since 2.0.0
 */

namespace EventTracker\Core;

use EventTracker\Admin\CPT;
use EventTracker\Admin\Settings;
use EventTracker\Ajax\Handler as AjaxHandler;
use EventTracker\Frontend\Shortcodes;
use EventTracker\Frontend\RedirectHandler;
use EventTracker\Mailer\MailerCore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Plugin Class
 */
class Plugin {

	/**
	 * Plugin Version
	 *
	 * @var string
	 */
	const VERSION = '2.2.0';

	/**
	 * Singleton Instance
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * Plugin Directory Path
	 *
	 * @var string
	 */
	private $plugin_path;

	/**
	 * Plugin Directory URL
	 *
	 * @var string
	 */
	private $plugin_url;

	/**
	 * Components
	 *
	 * @var array
	 */
	private $components = [];

	/**
	 * Get Singleton Instance
	 *
	 * @return Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->plugin_path = plugin_dir_path( dirname( dirname( __FILE__ ) ) );
		$this->plugin_url  = plugin_dir_url( dirname( dirname( __FILE__ ) ) );

		$this->init();
	}

	/**
	 * Initialize Plugin
	 */
	private function init() {
		// Zentrale Zoho-Auth: Meeting-Scopes registrieren
		add_filter( 'dgptm_zoho_required_scopes', function( $scopes ) {
			$scopes['Event Tracker'] = [
				'ZohoMeeting.webinar.CREATE',
				'ZohoMeeting.webinar.READ',
				'ZohoMeeting.webinar.UPDATE',
				'ZohoMeeting.webinar.DELETE',
				'ZohoMeeting.recording.READ',
			];
			return $scopes;
		} );

		// Load Components
		$this->load_components();

		// Hooks
		add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );
		add_action( 'init', [ $this, 'on_init' ] );
		add_filter( 'user_has_cap', [ $this, 'filter_user_caps' ], 10, 4 );

		// User profile hooks
		add_action( 'show_user_profile', [ $this, 'render_user_meta' ] );
		add_action( 'edit_user_profile', [ $this, 'render_user_meta' ] );
		add_action( 'personal_options_update', [ $this, 'save_user_meta' ] );
		add_action( 'edit_user_profile_update', [ $this, 'save_user_meta' ] );

		// Cache invalidation for event list transients
		add_action( 'save_post_' . Constants::CPT, [ $this, 'flush_event_list_cache' ] );
		add_action( 'deleted_post', [ $this, 'flush_event_list_cache_on_delete' ] );

		// WP Rocket: exclude our CSS/JS from combine/minify to prevent stale bundles
		add_filter( 'rocket_exclude_css', [ $this, 'rocket_exclude_assets' ] );
		add_filter( 'rocket_exclude_js', [ $this, 'rocket_exclude_assets' ] );

		// Cron: Automatischer Recording-Abruf nach Webinar-Ende
		add_action( 'et_zm_fetch_recording_cron', [ $this, 'cron_fetch_recording' ] );
	}

	/**
	 * Exclude Event Tracker assets from WP Rocket combine/minify.
	 *
	 * @param array $excluded Excluded file paths.
	 * @return array
	 */
	public function rocket_exclude_assets( $excluded ) {
		$excluded[] = '/modules/business/event-tracker/assets/(.*)';
		return $excluded;
	}

	/**
	 * Load All Components
	 */
	private function load_components() {
		// Admin Components
		$this->components['cpt']      = new CPT();
		$this->components['settings'] = new Settings();

		// AJAX Components
		$this->components['ajax'] = new AjaxHandler();

		// Frontend Components
		$this->components['shortcodes'] = new Shortcodes();
		$this->components['redirect']   = new RedirectHandler();

		// Mailer Components
		$this->components['mailer'] = new MailerCore();
	}

	/**
	 * Plugins Loaded Hook
	 */
	public function on_plugins_loaded() {
		// Load text domain
		load_plugin_textdomain(
			'event-tracker',
			false,
			dirname( plugin_basename( $this->plugin_path . 'event-tracker.php' ) ) . '/languages'
		);
	}

	/**
	 * Init Hook
	 */
	public function on_init() {
		// Wird von Komponenten genutzt
		do_action( 'event_tracker_init' );
	}

	/**
	 * Get Component
	 *
	 * @param string $name Component name.
	 * @return object|null
	 */
	public function get_component( $name ) {
		return isset( $this->components[ $name ] ) ? $this->components[ $name ] : null;
	}

	/**
	 * Get Plugin Path
	 *
	 * @param string $path Optional path to append.
	 * @return string
	 */
	public function plugin_path( $path = '' ) {
		return $this->plugin_path . ltrim( $path, '/' );
	}

	/**
	 * Get Plugin URL
	 *
	 * @param string $path Optional path to append.
	 * @return string
	 */
	public function plugin_url( $path = '' ) {
		return $this->plugin_url . ltrim( $path, '/' );
	}

	/**
	 * Activation Hook
	 */
	public static function activate() {
		// Trigger CPT registration
		$cpt = new CPT();
		$cpt->register_cpt();
		$cpt->register_mail_cpts();
		$cpt->add_rewrite();

		// Flush rewrite rules
		flush_rewrite_rules();

		// Log activation
		if ( class_exists( 'DGPTM_Logger' ) ) {
			\DGPTM_Logger::info( 'Plugin aktiviert (v' . self::VERSION . ')', 'event-tracker' );
		}
	}

	/**
	 * Deactivation Hook
	 */
	public static function deactivate() {
		// Flush rewrite rules
		flush_rewrite_rules();

		// Log deactivation
		if ( class_exists( 'DGPTM_Logger' ) ) {
			\DGPTM_Logger::info( 'Plugin deaktiviert', 'event-tracker' );
		}
	}

	/**
	 * Filter User Capabilities
	 *
	 * @param array    $allcaps All capabilities.
	 * @param array    $caps    Required capabilities.
	 * @param array    $args    Capability arguments.
	 * @param \WP_User $user    User object.
	 * @return array
	 */
	public function filter_user_caps( $allcaps, $caps, $args, $user ) {
		// Check if this is plugin AJAX
		$is_plugin_ajax = ( defined( 'DOING_AJAX' ) && DOING_AJAX &&
		                    isset( $_REQUEST['action'] ) &&
		                    is_string( $_REQUEST['action'] ) &&
		                    strpos( $_REQUEST['action'], 'et_' ) === 0 );

		// Check if plugin admin request
		$is_plugin_admin = Helpers::is_plugin_admin_request();

		// Check if cap override active
		$is_cap_override = Helpers::is_cap_override();

		// Should we elevate caps?
		$should_elevate = ( $is_cap_override || $is_plugin_ajax || $is_plugin_admin || is_admin() );

		if ( ! $should_elevate ) {
			return $allcaps;
		}

		// Check if user has toggle enabled
		$uid = isset( $user->ID ) ? (int) $user->ID : 0;
		if ( ! $uid || ! Helpers::user_has_access( $uid ) ) {
			return $allcaps;
		}

		// Grant event-specific capabilities
		$event_caps = [
			'read',
			'upload_files',
			'edit_et_event',
			'read_et_event',
			'delete_et_event',
			'edit_et_events',
			'edit_others_et_events',
			'publish_et_events',
			'read_private_et_events',
			'delete_et_events',
			'delete_private_et_events',
			'delete_published_et_events',
			'delete_others_et_events',
			'edit_private_et_events',
			'edit_published_et_events',
		];

		foreach ( $event_caps as $cap ) {
			$allcaps[ $cap ] = true;
		}

		return $allcaps;
	}

	/**
	 * Render User Meta Field
	 *
	 * @param \WP_User $user User object.
	 */
	public function render_user_meta( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		$value = get_user_meta( $user->ID, Constants::USER_META_ACCESS, true );
		?>
		<h2><?php esc_html_e( 'Event Tracker Berechtigung', 'event-tracker' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Event Tracker Zugriff', 'event-tracker' ); ?></th>
				<td>
					<label class="et-toggle">
						<input type="checkbox" name="et_mailer_access" value="1" <?php checked( $value, '1' ); ?> />
						<span class="et-toggle-label"><?php esc_html_e( 'Benutzer kann Events erstellen und verwalten', 'event-tracker' ); ?></span>
					</label>
					<p class="description"><?php esc_html_e( 'Mit dieser Berechtigung kann der Benutzer Events im Event Tracker erstellen, bearbeiten und löschen.', 'event-tracker' ); ?></p>
				</td>
			</tr>
		</table>
		<style>
			.et-toggle { display: flex; align-items: center; gap: 8px; }
			.et-toggle input[type="checkbox"] { margin: 0; }
			.et-toggle-label { font-weight: 500; }
		</style>
		<?php
	}

	/**
	 * Save User Meta Field
	 *
	 * @param int $user_id User ID.
	 */
	public function save_user_meta( $user_id ) {
		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			return;
		}

		if ( isset( $_POST['et_mailer_access'] ) && $_POST['et_mailer_access'] === '1' ) {
			update_user_meta( $user_id, Constants::USER_META_ACCESS, '1' );
		} else {
			delete_user_meta( $user_id, Constants::USER_META_ACCESS );
		}
	}

	/**
	 * Flush event list transient cache when an event is saved/updated.
	 *
	 * @param int $post_id Post ID.
	 */
	public function flush_event_list_cache( $post_id ) {
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) ) {
			return;
		}
		$this->delete_event_transients();
	}

	/**
	 * Flush event list transient cache when a post is deleted (checks post type).
	 *
	 * @param int $post_id Post ID.
	 */
	public function flush_event_list_cache_on_delete( $post_id ) {
		if ( get_post_type( $post_id ) === Constants::CPT ) {
			$this->delete_event_transients();
		}
	}

	/**
	 * Delete all dgptm_events_* transients from the database.
	 */
	private function delete_event_transients() {
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_dgptm_events_%' OR option_name LIKE '_transient_timeout_dgptm_events_%'"
		);
	}

	/**
	 * Cron: Automatisch Recording-URL abrufen (1h nach Webinar-Ende)
	 *
	 * @param int $event_id Event ID.
	 */
	public function cron_fetch_recording( $event_id ) {
		$session_key = get_post_meta( $event_id, Constants::META_ZM_KEY, true );
		if ( ! $session_key ) {
			Helpers::log( sprintf( 'Cron Recording: Event %d hat keinen Meeting-Key', $event_id ), 'warning' );
			return;
		}

		$client = new \EventTracker\ZohoMeeting\Client();
		$result = $client->get_recordings( $session_key );

		if ( ! $result['ok'] ) {
			Helpers::log( sprintf( 'Cron Recording: Abruf fehlgeschlagen fuer Event %d: %s', $event_id, $result['message'] ?? 'unbekannt' ), 'error' );
			return;
		}

		$recordings = isset( $result['data']['recordings'] ) ? $result['data']['recordings'] : [];

		if ( empty( $recordings ) ) {
			Helpers::log( sprintf( 'Cron Recording: Keine Aufzeichnung fuer Event %d verfuegbar', $event_id ), 'info' );
			return;
		}

		$recording_url = $recordings[0]['play_url'] ?? $recordings[0]['share_url'] ?? $recordings[0]['download_url'] ?? '';

		if ( $recording_url ) {
			Helpers::begin_cap_override();
			update_post_meta( $event_id, Constants::META_ZM_RECORDING_URL, $recording_url );
			update_post_meta( $event_id, Constants::META_RECORDING_URL, $recording_url );
			update_post_meta( $event_id, Constants::META_ZM_STATUS, 'ended' );
			Helpers::end_cap_override();

			Helpers::log( sprintf( 'Cron Recording: URL gespeichert fuer Event %d: %s', $event_id, $recording_url ), 'info' );
		}
	}
}
