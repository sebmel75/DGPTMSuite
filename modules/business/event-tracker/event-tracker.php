<?php
/**
 * Plugin Name: Event Tracker – Weiterleitung & Webhook
 * Description: Moderne WordPress-Plugin-Suite für Event-Management mit Webhook-Integration, Mail-System und mehrtägigen Veranstaltungen
 * Version: 2.0.0
 * Author: Sebastian Melzer / DGPTM
 * Author URI: https://dgptm.de
 * Text Domain: event-tracker
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.8
 *
 * @package EventTracker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Event Tracker Plugin Bootstrap
 *
 * NEUE ARCHITEKTUR (v2.0.0):
 * - PSR-4 Autoloading
 * - Namespaces (EventTracker\*)
 * - Klare Trennung der Verantwortlichkeiten
 * - WordPress Coding Standards
 * - Moderne PHP-Features
 *
 * FEATURES:
 * - ✅ Mehrtägige Events (gleicher Link, mehrere Zeiträume)
 * - ✅ Mail-Entwürfe (speichern ohne senden)
 * - ✅ Detailliertes Logging (DGPTM Logger Integration)
 * - ✅ Gelockerte Frontend-Permissions (alle eingeloggten User)
 * - ✅ Webhook-Integration mit Fehlerbehandlung
 * - ✅ Iframe-Support für Live-Streams
 * - ✅ Cron-Jobs für geplante Mails
 *
 * VERZEICHNISSTRUKTUR:
 * /src
 *   /Core       - Plugin-Kern (Plugin, Constants, Helpers, Autoloader)
 *   /Admin      - Admin-Funktionen (CPT, Settings, Metaboxen)
 *   /Ajax       - AJAX-Handler
 *   /Frontend   - Shortcodes, Formulare, Redirect-Logic
 *   /Mailer     - Mail-System, Cron-Jobs, Templates
 */

// Plugin Constants
define( 'EVENT_TRACKER_VERSION', '2.0.0' );
define( 'EVENT_TRACKER_FILE', __FILE__ );
define( 'EVENT_TRACKER_PATH', plugin_dir_path( __FILE__ ) );
define( 'EVENT_TRACKER_URL', plugin_dir_url( __FILE__ ) );

// Load Autoloader
require_once EVENT_TRACKER_PATH . 'src/Autoloader.php';
\EventTracker\Autoloader::register( EVENT_TRACKER_PATH );

// Load Core Classes (manually for critical dependencies)
require_once EVENT_TRACKER_PATH . 'src/Core/Constants.php';
require_once EVENT_TRACKER_PATH . 'src/Core/Helpers.php';

// Initialize Plugin
function event_tracker_init() {
	return \EventTracker\Core\Plugin::instance();
}

// Start Plugin on plugins_loaded with priority 10
add_action( 'plugins_loaded', 'event_tracker_init', 10 );

// Activation Hook
register_activation_hook( __FILE__, [ '\\EventTracker\\Core\\Plugin', 'activate' ] );

// Deactivation Hook
register_deactivation_hook( __FILE__, [ '\\EventTracker\\Core\\Plugin', 'deactivate' ] );

/**
 * HINWEISE FÜR ENTWICKLER:
 *
 * 1. NAMESPACE USAGE:
 *    use EventTracker\Core\Constants;
 *    use EventTracker\Core\Helpers;
 *
 * 2. KONSTANTEN:
 *    Constants::CPT          // 'et_event'
 *    Constants::STATUS_DRAFT // 'draft'
 *
 * 3. HELPERS:
 *    Helpers::is_event_valid( $event_id )
 *    Helpers::user_has_access()
 *    Helpers::log( 'Message', 'error' )
 *
 * 4. PLUGIN INSTANCE:
 *    $plugin = event_tracker_init();
 *    $cpt = $plugin->get_component( 'cpt' );
 *
 * 5. MIGRATION VON ALTER VERSION:
 *    - Alte Datei: eventtracker-backup.php
 *    - Migration Guide: MIGRATION.md
 *    - Alle Metadaten bleiben kompatibel
 */
