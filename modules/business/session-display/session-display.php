<?php
/**
 * Plugin Name: DGPTM Session Display
 * Description: Dynamische Anzeige von Jahrestagung-Sessions und Vorträgen via Zoho Backstage API
 * Version: 1.1.2
 * Author: Sebastian Melzer / DGPTM
 */

if (!defined('ABSPATH')) {
    exit;
}

// Konstanten definieren
define('DGPTM_SESSION_DISPLAY_VERSION', '1.1.2');
define('DGPTM_SESSION_DISPLAY_PATH', plugin_dir_path(__FILE__));
define('DGPTM_SESSION_DISPLAY_URL', plugin_dir_url(__FILE__));

/**
 * Hauptklasse für Session Display
 */
class DGPTM_Session_Display {

    private static $instance = null;

    /**
     * Singleton Instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor
     */
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }

    /**
     * Abhängigkeiten laden
     */
    private function load_dependencies() {
        require_once DGPTM_SESSION_DISPLAY_PATH . 'includes/class-zoho-backstage-api.php';
        require_once DGPTM_SESSION_DISPLAY_PATH . 'includes/class-session-manager.php';
        require_once DGPTM_SESSION_DISPLAY_PATH . 'includes/class-display-controller.php';
        require_once DGPTM_SESSION_DISPLAY_PATH . 'includes/class-room-mapper.php';

        // Admin-Klasse immer laden (wird für Admin-Menü benötigt)
        require_once DGPTM_SESSION_DISPLAY_PATH . 'admin/class-admin-settings.php';

        // Elementor Widget nur laden wenn Elementor aktiv ist
        if (did_action('elementor/loaded') && class_exists('\Elementor\Plugin')) {
            $widget_file = DGPTM_SESSION_DISPLAY_PATH . 'includes/elementor/class-session-display-widget.php';
            if (file_exists($widget_file)) {
                require_once $widget_file;
            }
        }
    }

    /**
     * Hooks initialisieren
     */
    private function init_hooks() {
        // OAuth-Callback verarbeiten (muss VOR allen Admin-Seiten laufen)
        add_action('admin_init', [$this, 'handle_oauth_callback']);

        // Admin-Menü
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // Scripts und Styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_scripts']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_scripts']);

        // AJAX Hooks
        add_action('wp_ajax_dgptm_get_current_session', [$this, 'ajax_get_current_session']);
        add_action('wp_ajax_nopriv_dgptm_get_current_session', [$this, 'ajax_get_current_session']);
        add_action('wp_ajax_dgptm_refresh_sessions', [$this, 'ajax_refresh_sessions']);
        add_action('wp_ajax_nopriv_dgptm_refresh_sessions', [$this, 'ajax_refresh_sessions']);

        // Shortcodes
        add_shortcode('session_display', [$this, 'session_display_shortcode']);
        add_shortcode('session_overview', [$this, 'session_overview_shortcode']);

        // Elementor Widget
        add_action('elementor/widgets/register', [$this, 'register_elementor_widgets']);

        // Cron für automatische Updates
        add_action('dgptm_session_display_update', [$this, 'scheduled_session_update']);
        add_filter('cron_schedules', [$this, 'add_custom_cron_intervals']);

        // Initialisierung (statt Aktivierungs-Hook, da Modul über DGPTM Suite geladen wird)
        add_action('init', [$this, 'maybe_initialize'], 1);

        // Cron neu planen wenn Einstellungen geändert werden
        add_action('update_option_dgptm_session_display_auto_update_interval', [$this, 'reschedule_cron'], 10, 2);
    }

    /**
     * Admin-Menü hinzufügen
     */
    public function add_admin_menu() {
        // Hauptmenü
        add_menu_page(
            'Session Display',                          // Page title
            'Session Display',                          // Menu title
            'manage_options',                           // Capability
            'dgptm-session-display',                    // Menu slug
            [$this, 'admin_page'],                      // Callback
            'dashicons-calendar-alt',                   // Icon
            30                                          // Position
        );

        // Übersicht (Dashboard)
        add_submenu_page(
            'dgptm-session-display',
            'Übersicht - Session Display',
            'Übersicht',
            'manage_options',
            'dgptm-session-display',
            [$this, 'admin_page']
        );

        // Einstellungen
        add_submenu_page(
            'dgptm-session-display',
            'Einstellungen - Session Display',
            'Einstellungen',
            'manage_options',
            'dgptm-session-display-settings',
            [$this, 'settings_page']
        );

        // Raumzuordnung (Venues)
        add_submenu_page(
            'dgptm-session-display',
            'Venue-Zuordnung - Session Display',
            'Venue-Zuordnung',
            'manage_options',
            'dgptm-session-display-venues',
            [$this, 'venues_page']
        );

        // Sponsoren
        add_submenu_page(
            'dgptm-session-display',
            'Sponsoren - Session Display',
            'Sponsoren',
            'manage_options',
            'dgptm-session-display-sponsors',
            [$this, 'sponsors_page']
        );

        // API-Tester
        add_submenu_page(
            'dgptm-session-display',
            'API-Tester - Session Display',
            'API-Tester',
            'manage_options',
            'dgptm-session-display-api-test',
            [$this, 'api_test_page']
        );

        // Sessions Übersicht
        add_submenu_page(
            'dgptm-session-display',
            'Sessions Übersicht - Session Display',
            'Sessions Übersicht',
            'manage_options',
            'dgptm-session-display-sessions-overview',
            [$this, 'sessions_overview_page']
        );
    }

    /**
     * Übersicht / Dashboard Seite
     */
    public function admin_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $admin_settings = new DGPTM_Session_Display_Admin();
        $admin_settings->render_overview_page();
    }

    /**
     * Einstellungen-Seite
     */
    public function settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $admin_settings = new DGPTM_Session_Display_Admin();
        $admin_settings->render_settings_page();
    }

    /**
     * Venue-Zuordnung-Seite
     */
    public function venues_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $admin_settings = new DGPTM_Session_Display_Admin();
        $admin_settings->render_venues_page();
    }

    /**
     * Sponsoren-Seite
     */
    public function sponsors_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $admin_settings = new DGPTM_Session_Display_Admin();
        $admin_settings->render_sponsors_page();
    }

    /**
     * API-Tester-Seite
     */
    public function api_test_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $admin_settings = new DGPTM_Session_Display_Admin();
        $admin_settings->render_api_test_page();
    }

    /**
     * Sessions Übersicht-Seite
     */
    public function sessions_overview_page() {
        if (!current_user_can('manage_options')) {
            return;
        }

        $admin_settings = new DGPTM_Session_Display_Admin();
        $admin_settings->render_sessions_overview_page();
    }

    /**
     * Frontend Scripts einbinden
     */
    public function enqueue_frontend_scripts() {
        wp_enqueue_style(
            'dgptm-session-display',
            DGPTM_SESSION_DISPLAY_URL . 'assets/css/session-display.css',
            [],
            DGPTM_SESSION_DISPLAY_VERSION
        );

        wp_enqueue_script(
            'dgptm-session-display',
            DGPTM_SESSION_DISPLAY_URL . 'assets/js/session-display.js',
            ['jquery'],
            DGPTM_SESSION_DISPLAY_VERSION,
            true
        );

        wp_localize_script('dgptm-session-display', 'dgptmSessionDisplay', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dgptm_session_display_nonce'),
            'refreshInterval' => $this->get_refresh_interval(),
            'autoRefresh' => get_option('dgptm_session_display_auto_refresh', true)
        ]);
    }

    /**
     * Admin Scripts einbinden
     */
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'dgptm-session-display') === false) {
            return;
        }

        wp_enqueue_style(
            'dgptm-session-display-admin',
            DGPTM_SESSION_DISPLAY_URL . 'assets/css/admin.css',
            [],
            DGPTM_SESSION_DISPLAY_VERSION
        );

        wp_enqueue_script(
            'dgptm-session-display-admin',
            DGPTM_SESSION_DISPLAY_URL . 'assets/js/admin.js',
            ['jquery'],
            DGPTM_SESSION_DISPLAY_VERSION,
            true
        );

        wp_localize_script('dgptm-session-display-admin', 'dgptmSessionAdmin', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('dgptm_session_display_admin_nonce')
        ]);
    }

    /**
     * AJAX: Aktuelle Session abrufen
     */
    public function ajax_get_current_session() {
        check_ajax_referer('dgptm_session_display_nonce', 'nonce');

        $room_id = isset($_POST['room_id']) ? sanitize_text_field($_POST['room_id']) : '';
        $display_type = isset($_POST['display_type']) ? sanitize_text_field($_POST['display_type']) : 'current';

        $display_controller = new DGPTM_Session_Display_Controller();
        $session_data = $display_controller->get_session_for_display($room_id, $display_type);

        wp_send_json_success($session_data);
    }

    /**
     * AJAX: Sessions aktualisieren
     */
    public function ajax_refresh_sessions() {
        check_ajax_referer('dgptm_session_display_nonce', 'nonce');

        $session_manager = new DGPTM_Session_Manager();
        $result = $session_manager->fetch_and_cache_sessions();

        if ($result) {
            wp_send_json_success(['message' => 'Sessions erfolgreich aktualisiert']);
        } else {
            wp_send_json_error(['message' => 'Fehler beim Aktualisieren der Sessions']);
        }
    }

    /**
     * Session Display Shortcode
     */
    public function session_display_shortcode($atts) {
        $atts = shortcode_atts([
            'room' => '',
            'type' => 'current', // current, next, both
            'show_sponsors' => 'true',
            'refresh' => 'auto'
        ], $atts);

        ob_start();

        $display_controller = new DGPTM_Session_Display_Controller();
        $display_controller->render_display($atts);

        return ob_get_clean();
    }

    /**
     * Session Overview Shortcode
     */
    public function session_overview_shortcode($atts) {
        $atts = shortcode_atts([
            'floor' => '',
            'rooms' => '',
            'layout' => 'grid', // grid, list, timeline
            'show_time' => 'true'
        ], $atts);

        ob_start();

        $display_controller = new DGPTM_Session_Display_Controller();
        $display_controller->render_overview($atts);

        return ob_get_clean();
    }

    /**
     * Elementor Widgets registrieren
     */
    public function register_elementor_widgets($widgets_manager) {
        // Prüfen ob Elementor und das Widget verfügbar sind
        if (!class_exists('\Elementor\Plugin')) {
            return;
        }

        if (class_exists('DGPTM_Session_Display_Widget')) {
            try {
                $widgets_manager->register(new DGPTM_Session_Display_Widget());
            } catch (Exception $e) {
                error_log('DGPTM Session Display: Fehler beim Registrieren des Elementor Widgets - ' . $e->getMessage());
            }
        }
    }

    /**
     * Geplantes Session-Update
     */
    public function scheduled_session_update() {
        $event_id = get_option('dgptm_session_display_event_id');

        if (empty($event_id)) {
            error_log('DGPTM Session Display: Automatisches Update übersprungen - keine Event-ID konfiguriert');
            return;
        }

        $session_manager = new DGPTM_Session_Manager();
        $result = $session_manager->fetch_and_cache_sessions();

        if ($result) {
            error_log('DGPTM Session Display: Automatisches Update erfolgreich - ' . date('Y-m-d H:i:s'));
        } else {
            error_log('DGPTM Session Display: Automatisches Update fehlgeschlagen - ' . date('Y-m-d H:i:s'));
        }
    }

    /**
     * Custom Cron Intervals registrieren
     */
    public function add_custom_cron_intervals($schedules) {
        // Backend-Update-Intervall (für Cron) - Standard: 5 Minuten
        $update_interval = get_option('dgptm_session_display_auto_update_interval', 300); // Sekunden

        $schedules['dgptm_session_update_interval'] = [
            'interval' => $update_interval,
            'display' => sprintf(__('Alle %d Sekunden (Session Display)'), $update_interval)
        ];

        return $schedules;
    }

    /**
     * Cron-Job neu planen
     */
    public function reschedule_cron($old_value, $new_value) {
        // Alten Cron entfernen
        $timestamp = wp_next_scheduled('dgptm_session_display_update');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'dgptm_session_display_update');
        }

        // Neuen Cron planen
        if (!wp_next_scheduled('dgptm_session_display_update')) {
            wp_schedule_event(time(), 'dgptm_session_update_interval', 'dgptm_session_display_update');
        }

        error_log('DGPTM Session Display: Cron neu geplant - Intervall: ' . $new_value . ' Sekunden');
    }

    /**
     * Aktualisierungsintervall abrufen (Frontend)
     */
    private function get_refresh_interval() {
        return get_option('dgptm_session_display_refresh_interval', 60000); // 60 Sekunden Standard
    }

    /**
     * Initialisierung beim ersten Laden
     */
    public function maybe_initialize() {
        // Prüfen ob bereits initialisiert
        if (get_option('dgptm_session_display_initialized')) {
            return;
        }

        // Standard-Optionen setzen
        add_option('dgptm_session_display_refresh_interval', 60000); // 60 Sekunden (Frontend)
        add_option('dgptm_session_display_auto_update_interval', 300); // 5 Minuten (Backend/Cron)
        add_option('dgptm_session_display_auto_refresh', true);
        add_option('dgptm_session_display_show_sponsors', true);
        add_option('dgptm_session_display_sponsor_interval', 10000); // 10 Sekunden
        add_option('dgptm_session_display_portal_id', '20086233464');
        add_option('dgptm_session_display_event_id', ''); // Muss vom Admin konfiguriert werden
        add_option('dgptm_session_display_sponsors', []);
        add_option('dgptm_session_display_room_mapping', []);
        add_option('dgptm_session_display_template_color', '#2563eb');
        add_option('dgptm_session_display_template_logo', '');
        add_option('dgptm_session_display_oauth_scopes', ['zohobackstage.agenda.READ']); // Standard: Nur Sessions lesen

        // Cron-Job einrichten mit Custom-Intervall
        if (!wp_next_scheduled('dgptm_session_display_update')) {
            wp_schedule_event(time(), 'dgptm_session_update_interval', 'dgptm_session_display_update');
        }

        // Initialisiert markieren
        update_option('dgptm_session_display_initialized', true);
        update_option('dgptm_session_display_version', DGPTM_SESSION_DISPLAY_VERSION);

        error_log('DGPTM Session Display: Modul initialisiert - Version ' . DGPTM_SESSION_DISPLAY_VERSION);
    }

    /**
     * OAuth-Callback verarbeiten (läuft bei admin_init VOR allen Seiten)
     */
    public function handle_oauth_callback() {
        // Nur verarbeiten wenn es ein OAuth-Callback ist
        if (!isset($_GET['page']) || $_GET['page'] !== 'dgptm-session-display') {
            return;
        }

        if (!isset($_GET['action']) || $_GET['action'] !== 'oauth_callback') {
            return;
        }

        if (!isset($_GET['code'])) {
            return;
        }

        // Sicherstellen dass API-Klasse geladen ist
        if (!class_exists('DGPTM_Zoho_Backstage_API')) {
            require_once DGPTM_SESSION_DISPLAY_PATH . 'includes/class-zoho-backstage-api.php';
        }

        // Code gegen Token tauschen
        $code = sanitize_text_field($_GET['code']);
        $result = DGPTM_Zoho_Backstage_API::exchange_code_for_token($code);

        // Ergebnis-Nachricht als Transient speichern (wird nach Redirect angezeigt)
        if ($result['success']) {
            set_transient('dgptm_session_display_oauth_message', [
                'type' => 'success',
                'message' => '✓ Erfolgreich mit Zoho Backstage verbunden!'
            ], 30);

            error_log('DGPTM Session Display: OAuth erfolgreich - Refresh Token gespeichert');
        } else {
            set_transient('dgptm_session_display_oauth_message', [
                'type' => 'error',
                'message' => 'OAuth-Fehler: ' . ($result['message'] ?? 'Unbekannter Fehler')
            ], 30);

            error_log('DGPTM Session Display: OAuth fehlgeschlagen - ' . print_r($result, true));
        }

        // Weiterleitung zur Einstellungsseite (ohne Callback-Parameter)
        wp_redirect(admin_url('admin.php?page=dgptm-session-display-settings'));
        exit;
    }

    /**
     * Plugin-Aktivierung (für Backup/manuelle Aktivierung)
     */
    public function activate() {
        $this->maybe_initialize();
    }

    /**
     * Plugin-Deaktivierung
     */
    public function deactivate() {
        // Cron-Job entfernen
        $timestamp = wp_next_scheduled('dgptm_session_display_update');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'dgptm_session_display_update');
        }

        // Initialisierungs-Flag entfernen (beim nächsten Laden wieder initialisieren)
        delete_option('dgptm_session_display_initialized');
    }
}

// Plugin initialisieren
function dgptm_session_display_init() {
    return DGPTM_Session_Display::get_instance();
}

add_action('plugins_loaded', 'dgptm_session_display_init');
