<?php
/**
 * Plugin Name: DGPTM Abstimmen-Addon (Consolidated v4.0)
 * Description: Umfassendes Voting-System mit Zoom-Integration, Anwesenheitserfassung, Präsenz-Scanner und Beamer-Ansicht
 * Version: 4.0.0
 * Author: Sebastian Melzer
 * Text Domain: dgptm-abstimmen
 *
 * Features:
 * - Umfragen mit Multi-Choice Fragen
 * - Zoom Meeting/Webinar Integration (S2S OAuth)
 * - Anwesenheitstracking via Webhook
 * - QR-Code basierte Präsenz-Erfassung
 * - Beamer-Ansicht für Live-Ergebnisse
 * - CSV/PDF Export
 * - E-Mail-Benachrichtigungen
 *
 * Diese Datei vereint die Funktionalität von:
 * - dgptm-abstimmungstool.php (v3.7.0) - Voting-System
 * - onlineabstimmung.php (v2.0) - Zoom-Integration
 * - abstimmenadon.php (v1.1.0) - Präsenz-Scanner
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin Constants
if ( ! defined( 'DGPTM_ABSTIMMEN_VERSION' ) ) {
	define( 'DGPTM_ABSTIMMEN_VERSION', '4.0.0' );
}
if ( ! defined( 'DGPTM_ABSTIMMEN_PATH' ) ) {
	define( 'DGPTM_ABSTIMMEN_PATH', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'DGPTM_ABSTIMMEN_URL' ) ) {
	define( 'DGPTM_ABSTIMMEN_URL', plugin_dir_url( __FILE__ ) );
}

// Legacy constants for backwards compatibility
if ( ! defined( 'DGPTMVOTE_VERSION' ) ) {
	define( 'DGPTMVOTE_VERSION', '4.0.0' );
}
if ( ! defined( 'DGPTMVOTE_COOKIE' ) ) {
	define( 'DGPTMVOTE_COOKIE', 'DGPTMVOTE_voteid' );
}

/**
 * Main Plugin Class - Singleton
 */
final class DGPTM_Abstimmen_Addon {

	private static $instance = null;

	/**
	 * Get singleton instance
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor - Initialize plugin
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load required files
	 */
	private function load_dependencies() {
		// Migration script (must be loaded first)
		require_once DGPTM_ABSTIMMEN_PATH . 'migrate-v4.php';

		// Core functionality (Voting System v3.7.0 base)
		require_once DGPTM_ABSTIMMEN_PATH . 'includes/common/helpers.php';
		require_once DGPTM_ABSTIMMEN_PATH . 'includes/common/install.php';
		require_once DGPTM_ABSTIMMEN_PATH . 'includes/common/enqueue.php';

		// Admin functionality
		if ( is_admin() ) {
			require_once DGPTM_ABSTIMMEN_PATH . 'includes/admin/manage-poll.php';
			require_once DGPTM_ABSTIMMEN_PATH . 'includes/admin/admin-ajax.php';
		}

		// Frontend voting functionality
		require_once DGPTM_ABSTIMMEN_PATH . 'includes/public/member-vote.php';
		require_once DGPTM_ABSTIMMEN_PATH . 'includes/ajax/vote.php';

		// Beamer functionality
		require_once DGPTM_ABSTIMMEN_PATH . 'includes/beamer/payload.php';
		require_once DGPTM_ABSTIMMEN_PATH . 'includes/beamer/view.php';

		// Registration & Monitoring
		require_once DGPTM_ABSTIMMEN_PATH . 'includes/registration/monitor.php';
		require_once DGPTM_ABSTIMMEN_PATH . 'includes/registration/registration-helpers.php';
		require_once DGPTM_ABSTIMMEN_PATH . 'includes/registration/registration-ajax.php';

		// Export functionality
		require_once DGPTM_ABSTIMMEN_PATH . 'includes/export/export.php';

		// Zoom Integration (v2.0 - onlineabstimmung.php)
		require_once DGPTM_ABSTIMMEN_PATH . 'includes/zoom/class-zoom-integration.php';

		// Presence Scanner (v1.1.0 - abstimmenadon.php)
		require_once DGPTM_ABSTIMMEN_PATH . 'includes/presence/class-presence-scanner.php';
	}

	/**
	 * Initialize WordPress hooks
	 */
	private function init_hooks() {
		// Activation & Installation
		register_activation_hook( __FILE__, 'dgptm_activate_plugin' );
		add_action( 'admin_init', 'dgptm_maybe_upgrade_db' );

		// Migration check
		add_action( 'admin_init', array( $this, 'check_migration_needed' ) );
		add_action( 'admin_notices', array( $this, 'migration_admin_notice' ) );

		// Shortcodes registration (Voting System base shortcodes)
		add_action( 'init', array( $this, 'register_base_shortcodes' ), 5 );

		// Query vars for member view
		add_filter( 'query_vars', 'dgptm_add_query_var' );
		add_action( 'template_redirect', 'dgptm_template_redirect' );

		// User meta UI
		add_action( 'show_user_profile', 'dgptm_user_field_abstimmungsmanager' );
		add_action( 'edit_user_profile', 'dgptm_user_field_abstimmungsmanager' );
		add_action( 'personal_options_update', 'dgptm_save_user_field_abstimmungsmanager' );
		add_action( 'edit_user_profile_update', 'dgptm_save_user_field_abstimmungsmanager' );
	}

	/**
	 * Register base voting system shortcodes
	 * Note: Zoom and presence scanner shortcodes are registered by their respective classes
	 */
	public function register_base_shortcodes() {
		if ( ! shortcode_exists( 'manage_poll' ) ) {
			add_shortcode( 'manage_poll', 'dgptm_manage_poll' );
		}
		if ( ! shortcode_exists( 'beamer_view' ) ) {
			add_shortcode( 'beamer_view', 'dgptm_beamer_view' );
		}
		if ( ! shortcode_exists( 'member_vote' ) ) {
			add_shortcode( 'member_vote', 'dgptm_member_vote' );
		}
		if ( ! shortcode_exists( 'abstimmungsmanager_toggle' ) ) {
			add_shortcode( 'abstimmungsmanager_toggle', 'dgptm_shortcode_manager_toggle' );
		}
		if ( ! shortcode_exists( 'dgptm_registration_monitor' ) ) {
			add_shortcode( 'dgptm_registration_monitor', 'dgptm_registration_monitor_fn' );
		}
	}

	/**
	 * Check if migration is needed
	 */
	public function check_migration_needed() {
		$current_version = get_option( 'dgptm_abstimmen_version', '0.0.0' );

		// Auto-run migration on first activation
		if ( $current_version === '0.0.0' ) {
			$this->run_migration_silent();
		}
	}

	/**
	 * Show admin notice if migration is available
	 */
	public function migration_admin_notice() {
		$current_version = get_option( 'dgptm_abstimmen_version', '0.0.0' );
		$dismissed       = get_user_meta( get_current_user_id(), 'dgptm_abstimmen_migration_dismissed', true );

		// Don't show if already at target version or dismissed
		if ( version_compare( $current_version, DGPTM_ABSTIMMEN_VERSION, '>=' ) || $dismissed ) {
			return;
		}

		// Only show to admins
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		?>
		<div class="notice notice-warning is-dismissible" id="dgptm-migration-notice">
			<p>
				<strong>DGPTM Abstimmen-Addon:</strong>
				Es ist eine Migration zu Version <?php echo esc_html( DGPTM_ABSTIMMEN_VERSION ); ?> verfügbar.
			</p>
			<p>
				<button type="button" class="button button-primary" id="dgptm-run-migration">
					Migration jetzt ausführen
				</button>
				<button type="button" class="button" id="dgptm-dismiss-migration">
					Später erinnern
				</button>
			</p>
			<div id="dgptm-migration-result" style="margin-top: 10px; display: none;"></div>
		</div>
		<script>
		jQuery(document).ready(function($) {
			$('#dgptm-run-migration').on('click', function() {
				var $btn = $(this);
				var $result = $('#dgptm-migration-result');

				$btn.prop('disabled', true).text('Migration läuft...');
				$result.html('<p>Migration wird ausgeführt, bitte warten...</p>').show();

				$.ajax({
					url: ajaxurl,
					type: 'POST',
					data: {
						action: 'dgptm_abstimmen_migrate',
						nonce: '<?php echo wp_create_nonce( 'dgptm_admin_nonce' ); ?>'
					},
					success: function(response) {
						if (response.success) {
							$result.html('<div class="notice notice-success"><p><strong>Migration erfolgreich!</strong> Die Seite wird neu geladen...</p></div>');
							setTimeout(function() {
								location.reload();
							}, 2000);
						} else {
							$result.html('<div class="notice notice-error"><p><strong>Fehler:</strong> ' + response.data.message + '</p></div>');
							$btn.prop('disabled', false).text('Erneut versuchen');
						}
					},
					error: function() {
						$result.html('<div class="notice notice-error"><p>Ein unerwarteter Fehler ist aufgetreten.</p></div>');
						$btn.prop('disabled', false).text('Erneut versuchen');
					}
				});
			});

			$('#dgptm-dismiss-migration').on('click', function() {
				$.post(ajaxurl, {
					action: 'dgptm_abstimmen_dismiss_migration',
					nonce: '<?php echo wp_create_nonce( 'dgptm_admin_nonce' ); ?>'
				}, function() {
					$('#dgptm-migration-notice').fadeOut();
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * Run migration silently (on first activation)
	 */
	private function run_migration_silent() {
		$result = dgptm_abstimmen_run_migration();

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( 'DGPTM Abstimmen Auto-Migration: ' . ( $result['success'] ? 'Success' : 'Failed' ) );
		}
	}
}

/**
 * Initialize the plugin
 */
function dgptm_abstimmen_addon_init() {
	return DGPTM_Abstimmen_Addon::instance();
}

// Hook into plugins_loaded to ensure WordPress is fully loaded
add_action( 'plugins_loaded', 'dgptm_abstimmen_addon_init', 1 );
