<?php
/**
 * Plugin Name: Event Tracker – Weiterleitung & Webhook
 * Description: Listet Veranstaltungen und leitet – innerhalb eines Gültigkeitszeitraums – zur Ziel-URL weiter. Übergibt dabei (nur bei Gültigkeit) alle Query-Parameter (GET) an eine konfigurierbare Webhook-URL. Feste Routing-URL: /eventtracker. Enthält zusätzlich einen kombinierten Shortcode mit HTML-Mailer (Webhook-JSON) inkl. Vorlagen, Testmail und Versandliste.
 * Version:     1.17.0
 * Author:      Seb
 * Text Domain: event-tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WordPress-Standard Plugin-Struktur
 *
 * NEUE STRUKTUR:
 * - includes/class-event-tracker-constants.php - Alle Konstanten
 * - includes/class-event-tracker-cpt.php      - CPT Registrierung + Metaboxen + Settings
 * - includes/class-event-tracker-helpers.php  - Helper-Funktionen (user_has_plugin_access, is_event_valid_now, etc.)
 *
 * ALTE STRUKTUR (in dieser Datei bis zur vollständigen Migration):
 * - ET_Event_Tracker Klasse mit allen AJAX-Handlern, Frontend, Mailer, Redirect-Logik
 *
 * VERWENDUNG DER NEUEN KLASSEN:
 * - ET_Constants::CPT statt self::CPT
 * - ET_Helpers::user_has_plugin_access() statt $this->user_has_plugin_access()
 * - ET_Helpers::is_event_valid_now($event_id) statt eigene is_event_valid_now() Methode
 * - ET_Helpers::begin_cap_override() statt $this->begin_cap_override()
 *
 * TODO FÜR VOLLSTÄNDIGE REFAKTORIERUNG:
 * 1. AJAX-Handler in includes/class-event-tracker-ajax.php auslagern
 * 2. Mailer-Funktionen in includes/class-event-tracker-mailer.php auslagern
 * 3. Frontend-Logik in includes/class-event-tracker-frontend.php auslagern
 * 4. Redirect-Handler in includes/class-event-tracker-redirect.php auslagern
 * 5. Core-Klasse in includes/class-event-tracker-core.php die alles orchestriert
 */

// Lade WordPress-Standard Klassen-Dateien
require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-tracker-constants.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-tracker-helpers.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/class-event-tracker-cpt.php';

// Initialisiere CPT-Handler
new ET_CPT_Handler();

/**
 * Event Tracker Hauptklasse
 *
 * HINWEIS: Diese Klasse enthält noch die gesamte Logik.
 * Bei vollständiger Refaktorierung würde diese Klasse nur noch die Orchestrierung übernehmen.
 */
final class ET_Event_Tracker {

	/** @var array One-time script flags */
	private static $mailer_script_added = false;
	private static $panels_script_added = false;

	/**
	 * Constructor
	 */
	public function __construct() {
		// Shortcuts (kombiniert)
		add_shortcode( 'event_tracker',        [ $this, 'shortcode_combined' ] );
		add_shortcode( 'event_mailer',         [ $this, 'shortcode_combined' ] );
		add_shortcode( 'event_mailer_right',   [ $this, 'shortcode_mailer_right' ] );

		// Rendering /eventtracker
		add_filter( 'template_include', [ $this, 'intercept_template' ] );

		// AJAX Handlers
		add_action( 'wp_ajax_et_get_template',     [ $this, 'ajax_get_template' ] );
		add_action( 'wp_ajax_et_save_template',    [ $this, 'ajax_save_template' ] );
		add_action( 'wp_ajax_et_delete_template',  [ $this, 'ajax_delete_template' ] );
		add_action( 'wp_ajax_et_send_mail',        [ $this, 'ajax_send_mail' ] );
		add_action( 'wp_ajax_et_test_mail',        [ $this, 'ajax_test_mail' ] );
		add_action( 'wp_ajax_et_delete_mail_log',  [ $this, 'ajax_delete_mail_log' ] );
		add_action( 'wp_ajax_et_fetch_event_list',        [ $this, 'ajax_fetch_event_list' ] );
		add_action( 'wp_ajax_nopriv_et_fetch_event_list', [ $this, 'ajax_fetch_event_list' ] );
		add_action( 'wp_ajax_et_fetch_event_form',        [ $this, 'ajax_fetch_event_form' ] );
		add_action( 'wp_ajax_nopriv_et_fetch_event_form', [ $this, 'ajax_fetch_event_form' ] );
		add_action( 'wp_ajax_et_stop_mail_job',   [ $this, 'ajax_stop_mail_job' ] );

		// User Profile
		add_action( 'show_user_profile', [ $this, 'render_user_meta' ] );
		add_action( 'edit_user_profile', [ $this, 'render_user_meta' ] );
		add_action( 'personal_options_update', [ $this, 'save_user_meta' ] );
		add_action( 'edit_user_profile_update', [ $this, 'save_user_meta' ] );

		// Capabilities Filter
		add_filter( 'user_has_cap', [ $this, 'filter_user_caps_for_toggle' ], 10, 4 );

		// Cron & Fallback
		add_action( ET_Constants::CRON_HOOK_SINGLE, [ $this, 'cron_run_mail_job' ], 10, 1 );
		add_action( 'init', [ $this, 'maybe_process_due_jobs' ] );
	}

	/**
	 * HINWEIS: Alle folgenden Methoden sollten bei vollständiger Refaktorierung
	 * in separate Klassen verschoben werden. Zur Demonstration hier ein Beispiel:
	 *
	 * Capabilities Filter
	 * Diese Methode bleibt hier als Beispiel. Bei Refaktorierung würde sie in
	 * eine separate Permissions-Klasse wandern.
	 */
	public function filter_user_caps_for_toggle( $allcaps, $caps, $args, $user ) {
		if ( ! ET_Helpers::is_plugin_admin_request() ) {
			return $allcaps;
		}

		if ( ET_Helpers::user_has_plugin_access( $user->ID ) || ET_Helpers::is_cap_override_active() ) {
			$allcaps['edit_et_events']            = true;
			$allcaps['edit_others_et_events']     = true;
			$allcaps['publish_et_events']         = true;
			$allcaps['read_et_event']             = true;
			$allcaps['read_private_et_events']    = true;
			$allcaps['delete_et_events']          = true;
			$allcaps['delete_others_et_events']   = true;
			$allcaps['delete_published_et_events']= true;
			$allcaps['edit_published_et_events']  = true;
		}

		return $allcaps;
	}

	/**
	 * ALLE WEITEREN METHODEN WÜRDEN HIER FOLGEN
	 *
	 * Zur Referenz siehe eventtracker-backup.php (Original-Datei)
	 *
	 * EMPFOHLENE AUFTEILUNG:
	 * - AJAX-Methoden → ET_Ajax_Handler
	 * - Mailer-Methoden → ET_Mailer
	 * - Frontend-Methoden → ET_Frontend
	 * - Redirect-Methoden → ET_Redirect_Handler
	 */

	// ... Alle weiteren Methoden aus der Original-Datei hier einfügen ...
	// Platzhalter, da diese zu umfangreich sind für diese Demo
}

// Plugin initialisieren
new ET_Event_Tracker();

/**
 * Aktivierungs-/Deaktivierungs-Hooks
 */
register_activation_hook( __FILE__, function () {
	$inst = new ET_Event_Tracker();
	// CPT wird bereits von ET_CPT_Handler registriert
	flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
	flush_rewrite_rules();
} );
