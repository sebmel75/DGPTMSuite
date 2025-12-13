<?php
/**
 * Central Settings Registry
 *
 * Registriert alle Modul-Settings zentral unter "Modul-Einstellungen"
 * Module behalten ihre Funktionalitaet, aber die Konfiguration erfolgt hier
 *
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Central_Settings_Registry {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Settings bei admin_init registrieren (nachdem alles geladen ist)
        add_action('admin_init', [$this, 'register_all_settings'], 5);

        // Legacy admin_menu Hooks entfernen
        add_action('admin_menu', [$this, 'remove_legacy_menus'], 999);
    }

    /**
     * Registriert Settings fuer alle Module
     */
    public function register_all_settings() {
        // Core Infrastructure
        $this->register_crm_abruf_settings();
        $this->register_rest_api_settings();
        $this->register_menu_control_settings();

        // Auth
        $this->register_otp_login_settings();

        // Business
        $this->register_gehaltsbarometer_settings();
        $this->register_event_tracker_settings();
        $this->register_timeline_settings();

        // Content
        $this->register_mitgliedsantrag_settings();
        $this->register_stellenanzeige_settings();
        $this->register_vimeo_webinare_settings();

        // Media
        $this->register_wissens_bot_settings();
        $this->register_vimeo_streams_settings();

        // Payment
        $this->register_stripe_formidable_settings();
        $this->register_gocardless_settings();
        $this->register_zoho_books_settings();

        // Utilities
        $this->register_efn_manager_settings();
        $this->register_role_manager_settings();
        $this->register_session_display_settings();
        $this->register_elementor_ai_export_settings();
        $this->register_acf_jetsync_settings();
        $this->register_github_sync_settings();
        $this->register_daten_bearbeiten_settings();

        // Business (weitere)
        $this->register_abstimmen_addon_settings();
    }

    /**
     * Entfernt Legacy-Menus aus dem Admin
     */
    public function remove_legacy_menus() {
        // Diese Menus werden durch die zentrale Settings-Seite ersetzt
        remove_menu_page('wissens-bot');
        remove_submenu_page('options-general.php', 'stripe-formidable-settings');
        remove_submenu_page('options-general.php', 'gcl-settings');
        remove_submenu_page('options-general.php', 'dgptm-online-abstimmen');
        remove_submenu_page('options-general.php', 'et_settings');
        remove_submenu_page('options-general.php', 'dgptm_staz_settings');
        remove_menu_page('vimeo-webinars');
        remove_menu_page('dgptm-mitgliedsantrag');
        remove_submenu_page('edit.php?post_type=fortbildung', 'fobi-ebcp-settings');
        remove_submenu_page('edit.php?post_type=fortbildung', 'fobi-aek-settings');

        // OTP Login Submenu
        remove_submenu_page('options-general.php', 'dgptm-otp-settings');

        // GitHub Sync, Daten bearbeiten
        remove_submenu_page('options-general.php', 'dgptm-daten-bearbeiten');
    }

    // ============================================================
    // CORE INFRASTRUCTURE
    // ============================================================

    /**
     * CRM Abruf (Zoho API) Settings
     */
    private function register_crm_abruf_settings() {
        dgptm_register_module_settings([
            'id' => 'crm-abruf',
            'title' => 'Zoho CRM API',
            'menu_title' => 'Zoho CRM',
            'icon' => 'dashicons-cloud',
            'priority' => 5,
            'sections' => [
                ['id' => 'oauth', 'title' => 'OAuth-Konfiguration', 'description' => 'Zoho API Zugangsdaten fuer die CRM-Integration.'],
                ['id' => 'endpoints', 'title' => 'API-Endpunkte', 'description' => 'Konfiguration der REST-Endpunkte.']
            ],
            'fields' => [
                ['id' => 'api_url', 'section' => 'endpoints', 'title' => 'API URL', 'type' => 'url', 'description' => 'Zoho CRM API Endpunkt URL'],
                ['id' => 'client_id', 'section' => 'oauth', 'title' => 'Client ID', 'type' => 'text', 'required' => true],
                ['id' => 'client_secret', 'section' => 'oauth', 'title' => 'Client Secret', 'type' => 'password', 'required' => true],
                ['id' => 'refresh_token', 'section' => 'oauth', 'title' => 'Refresh Token', 'type' => 'password', 'description' => 'Wird automatisch bei OAuth-Verbindung gesetzt'],
                ['id' => 'data_center', 'section' => 'oauth', 'title' => 'Data Center', 'type' => 'select', 'options' => [
                    'eu' => 'EU (zoho.eu)',
                    'com' => 'US (zoho.com)',
                    'in' => 'India (zoho.in)',
                    'au' => 'Australia (zoho.com.au)'
                ], 'default' => 'eu'],
                ['id' => 'cache_ttl', 'section' => 'endpoints', 'title' => 'Cache-Dauer (Sekunden)', 'type' => 'number', 'default' => 300, 'min' => 60, 'max' => 3600],
                ['id' => 'enable_logging', 'section' => 'endpoints', 'title' => 'API-Logging aktivieren', 'type' => 'checkbox', 'default' => false]
            ]
        ]);

        $this->migrate_crm_abruf_settings();
    }

    private function migrate_crm_abruf_settings() {
        // Immer pruefen ob neue Werte aus alten Options uebernommen werden koennen
        $current_settings = get_option('dgptm_module_settings_crm-abruf', []);

        // Die tatsaechlichen Option-Namen mit dgptm_ Prefix
        $old_keys = [
            'dgptm_zoho_client_id' => 'client_id',
            'dgptm_zoho_client_secret' => 'client_secret',
            'dgptm_zoho_refresh_token' => 'refresh_token',
            'dgptm_zoho_api_url' => 'api_url'
        ];

        $updated = false;
        foreach ($old_keys as $old => $new) {
            // Nur migrieren wenn zentrale Einstellung leer ist
            if (empty($current_settings[$new])) {
                $val = get_option($old);
                if ($val) {
                    $current_settings[$new] = $val;
                    $updated = true;
                }
            }
        }

        if ($updated) {
            update_option('dgptm_module_settings_crm-abruf', $current_settings);
        }
    }

    /**
     * REST API Extension Settings
     */
    private function register_rest_api_settings() {
        dgptm_register_module_settings([
            'id' => 'rest-api-extension',
            'title' => 'REST API Erweiterung',
            'menu_title' => 'REST API',
            'icon' => 'dashicons-rest-api',
            'priority' => 6,
            'sections' => [
                ['id' => 'security', 'title' => 'Sicherheit', 'description' => 'API-Sicherheitseinstellungen.']
            ],
            'fields' => [
                ['id' => 'enable_public_endpoints', 'section' => 'security', 'title' => 'Oeffentliche Endpoints aktivieren', 'type' => 'checkbox', 'default' => false],
                ['id' => 'rate_limit', 'section' => 'security', 'title' => 'Rate Limit (Anfragen/Minute)', 'type' => 'number', 'default' => 60, 'min' => 10, 'max' => 1000]
            ]
        ]);
    }

    /**
     * Menu Control Settings
     */
    private function register_menu_control_settings() {
        dgptm_register_module_settings([
            'id' => 'menu-control',
            'title' => 'Menu Control',
            'menu_title' => 'Menu Control',
            'icon' => 'dashicons-menu',
            'priority' => 7,
            'sections' => [
                ['id' => 'general', 'title' => 'Allgemein', 'description' => 'Einstellungen fuer die Menu-Steuerung.']
            ],
            'fields' => [
                ['id' => 'hide_wp_logo', 'section' => 'general', 'title' => 'WordPress-Logo ausblenden', 'type' => 'checkbox', 'default' => false],
                ['id' => 'custom_footer', 'section' => 'general', 'title' => 'Custom Admin Footer', 'type' => 'text', 'default' => '']
            ]
        ]);
    }

    // ============================================================
    // AUTH
    // ============================================================

    /**
     * OTP Login + Preloader Settings
     */
    private function register_otp_login_settings() {
        dgptm_register_module_settings([
            'id' => 'otp-login',
            'title' => 'OTP Login & Preloader',
            'menu_title' => 'OTP Login',
            'icon' => 'dashicons-lock',
            'priority' => 30,
            'sections' => [
                ['id' => 'otp', 'title' => 'OTP-Einstellungen', 'description' => 'Konfiguration des Einmal-Passwort Login-Systems.'],
                ['id' => 'email', 'title' => 'E-Mail-Einstellungen', 'description' => 'Templates fuer OTP-E-Mails. Platzhalter: {user_login}, {otp}, {otp_valid_minutes}, {site_name}'],
                ['id' => 'preloader', 'title' => 'Preloader', 'description' => 'Einstellungen fuer den Lade-Animation.'],
                ['id' => 'security', 'title' => 'Sicherheit', 'description' => 'Zusaetzliche Sicherheitsoptionen.']
            ],
            'fields' => [
                // OTP
                ['id' => 'rate_limit', 'section' => 'otp', 'title' => 'OTP Rate Limit', 'type' => 'number', 'default' => 3, 'min' => 1, 'max' => 10, 'description' => 'Maximale OTP-Anfragen pro Session'],
                ['id' => 'remember_me_days', 'section' => 'otp', 'title' => '"Angemeldet bleiben" Tage', 'type' => 'number', 'default' => 30, 'min' => 1, 'max' => 365],
                // Email
                ['id' => 'email_subject', 'section' => 'email', 'title' => 'E-Mail Betreff', 'type' => 'text', 'default' => 'Ihr Login-Code fuer {site_name}'],
                ['id' => 'email_body', 'section' => 'email', 'title' => 'E-Mail Text', 'type' => 'textarea', 'rows' => 6, 'default' => "Hallo {user_login},\n\nIhr Einmal-Code lautet: {otp}\nEr ist {otp_valid_minutes} Minuten gueltig.\n\nViele Gruesse\n{site_name}"],
                // Preloader
                ['id' => 'preloader_enabled', 'section' => 'preloader', 'title' => 'Preloader aktivieren', 'type' => 'checkbox', 'default' => true],
                ['id' => 'preloader_logo', 'section' => 'preloader', 'title' => 'Logo (Attachment ID)', 'type' => 'media'],
                ['id' => 'preloader_bg_color', 'section' => 'preloader', 'title' => 'Hintergrundfarbe', 'type' => 'color', 'default' => '#ffffff'],
                // Security
                ['id' => 'disable_wp_login', 'section' => 'security', 'title' => 'WP-Login deaktivieren', 'type' => 'checkbox', 'default' => false, 'description' => 'Blockiert den Standard WordPress Login'],
                ['id' => 'webhook_enable', 'section' => 'security', 'title' => 'Login-Webhook aktivieren', 'type' => 'checkbox', 'default' => false],
                ['id' => 'webhook_url', 'section' => 'security', 'title' => 'Webhook URL', 'type' => 'url']
            ]
        ]);

        $this->migrate_otp_login_settings();
    }

    private function migrate_otp_login_settings() {
        if (get_option('dgptm_otp_login_migrated')) return;

        $new_settings = [];
        $mapping = [
            'dgptm_otp_rate_limit' => 'rate_limit',
            'dgptm_email_subject' => 'email_subject',
            'dgptm_email_body' => 'email_body',
            'dgptm_preloader_enabled' => 'preloader_enabled',
            'dgptm_disable_wp_login' => 'disable_wp_login',
            'dgptm_webhook_enable' => 'webhook_enable',
            'dgptm_webhook_url' => 'webhook_url'
        ];

        foreach ($mapping as $old => $new) {
            $val = get_option($old);
            if ($val !== false && $val !== '') $new_settings[$new] = $val;
        }

        if (!empty($new_settings)) {
            update_option('dgptm_module_settings_otp-login', $new_settings);
        }
        update_option('dgptm_otp_login_migrated', true);
    }

    // ============================================================
    // BUSINESS
    // ============================================================

    /**
     * Gehaltsbarometer Settings
     */
    private function register_gehaltsbarometer_settings() {
        dgptm_register_module_settings([
            'id' => 'gehaltsstatistik',
            'title' => 'Gehaltsbarometer',
            'menu_title' => 'Gehaltsbarometer',
            'icon' => 'dashicons-chart-bar',
            'priority' => 20,
            'sections' => [
                ['id' => 'formidable', 'title' => 'Formidable Integration', 'description' => 'Verbindung mit Formidable Forms.'],
                ['id' => 'display', 'title' => 'Anzeige', 'description' => 'Darstellungsoptionen.']
            ],
            'fields' => [
                ['id' => 'form_id', 'section' => 'formidable', 'title' => 'Formular-ID', 'type' => 'number', 'default' => 24, 'description' => 'ID des Formidable Formulars'],
                ['id' => 'form_intro', 'section' => 'display', 'title' => 'Info-Text (HTML)', 'type' => 'textarea', 'rows' => 6, 'description' => 'Platzhalter {jahr} wird durch das aktuelle Jahr ersetzt'],
                ['id' => 'min_entries_per_region', 'section' => 'display', 'title' => 'Mindestanzahl Eintraege pro Region', 'type' => 'number', 'default' => 3, 'min' => 1, 'max' => 100, 'description' => 'Regionen mit weniger Eintraegen werden nicht angezeigt (Datenschutz)'],
                ['id' => 'show_anonymized', 'section' => 'display', 'title' => 'Anonymisierte Ansicht', 'type' => 'checkbox', 'default' => true, 'description' => 'Zeigt PII-Felder anonymisiert']
            ]
        ]);

        $this->migrate_gehaltsbarometer_settings();
    }

    private function migrate_gehaltsbarometer_settings() {
        if (get_option('dgptm_gehaltsbarometer_migrated')) return;

        $new_settings = [];

        $form_intro = get_option('gb_form_intro');
        if ($form_intro) $new_settings['form_intro'] = $form_intro;

        $min_entries = get_option('gb_min_entries_per_bundesland');
        if ($min_entries) $new_settings['min_entries_per_region'] = $min_entries;

        if (!empty($new_settings)) {
            update_option('dgptm_module_settings_gehaltsstatistik', $new_settings);
        }
        update_option('dgptm_gehaltsbarometer_migrated', true);
    }

    /**
     * Event Tracker Settings
     */
    private function register_event_tracker_settings() {
        dgptm_register_module_settings([
            'id' => 'event-tracker',
            'title' => 'Event Tracker',
            'menu_title' => 'Event Tracker',
            'icon' => 'dashicons-calendar-alt',
            'priority' => 35,
            'sections' => [
                ['id' => 'general', 'title' => 'Allgemein', 'description' => 'Grundeinstellungen fuer Event-Tracking.'],
                ['id' => 'mail', 'title' => 'E-Mail Einstellungen', 'description' => 'Konfiguration der Event-Benachrichtigungen.']
            ],
            'fields' => [
                ['id' => 'redirect_url', 'section' => 'general', 'title' => 'Redirect-URL', 'type' => 'url', 'description' => 'URL nach Event-Check-in'],
                ['id' => 'webhook_url', 'section' => 'general', 'title' => 'Webhook URL', 'type' => 'url', 'description' => 'CRM-Webhook fuer Event-Updates'],
                ['id' => 'mail_enabled', 'section' => 'mail', 'title' => 'E-Mails aktivieren', 'type' => 'checkbox', 'default' => true],
                ['id' => 'mail_from', 'section' => 'mail', 'title' => 'Absender E-Mail', 'type' => 'email'],
                ['id' => 'mail_from_name', 'section' => 'mail', 'title' => 'Absender Name', 'type' => 'text']
            ]
        ]);

        $this->migrate_event_tracker_settings();
    }

    private function migrate_event_tracker_settings() {
        if (get_option('dgptm_event_tracker_migrated')) return;

        $old_settings = get_option('et_settings', []);
        if (!empty($old_settings)) {
            update_option('dgptm_module_settings_event-tracker', $old_settings);
        }
        update_option('dgptm_event_tracker_migrated', true);
    }

    /**
     * Timeline Settings
     */
    private function register_timeline_settings() {
        dgptm_register_module_settings([
            'id' => 'timeline-manager',
            'title' => 'Timeline Manager',
            'menu_title' => 'Timeline',
            'icon' => 'dashicons-clock',
            'priority' => 36,
            'sections' => [
                ['id' => 'display', 'title' => 'Anzeige', 'description' => 'Darstellungsoptionen fuer die Timeline.']
            ],
            'fields' => [
                ['id' => 'default_order', 'section' => 'display', 'title' => 'Standard-Sortierung', 'type' => 'select', 'options' => [
                    'DESC' => 'Neueste zuerst',
                    'ASC' => 'Aelteste zuerst'
                ], 'default' => 'DESC'],
                ['id' => 'entries_per_page', 'section' => 'display', 'title' => 'Eintraege pro Seite', 'type' => 'number', 'default' => 10, 'min' => 5, 'max' => 100]
            ]
        ]);
    }

    // ============================================================
    // CONTENT
    // ============================================================

    /**
     * Mitgliedsantrag Settings
     */
    private function register_mitgliedsantrag_settings() {
        dgptm_register_module_settings([
            'id' => 'mitgliedsantrag',
            'title' => 'Mitgliedsantrag',
            'menu_title' => 'Mitgliedsantrag',
            'icon' => 'dashicons-id-alt',
            'priority' => 40,
            'sections' => [
                ['id' => 'general', 'title' => 'Allgemein', 'description' => 'Allgemeine Einstellungen.'],
                ['id' => 'apis', 'title' => 'API-Integrationen', 'description' => 'Google Maps und Webhook-Konfiguration.'],
                ['id' => 'zoho', 'title' => 'Zoho CRM OAuth', 'description' => 'Zoho CRM Verbindung fuer Buergen-Verifizierung.']
            ],
            'fields' => [
                // General
                ['id' => 'disable_formidable_conflicts', 'section' => 'general', 'title' => 'Formidable Konflikte verhindern', 'type' => 'checkbox', 'default' => true, 'description' => 'Verhindert dass Formidable den [mitgliedsantrag] Shortcode ueberschreibt'],
                ['id' => 'default_country_code', 'section' => 'general', 'title' => 'Standard Laendervorwahl', 'type' => 'text', 'default' => '+49', 'description' => 'z.B. +49 fuer Deutschland, +43 fuer Oesterreich'],
                // APIs
                ['id' => 'google_maps_api_key', 'section' => 'apis', 'title' => 'Google Maps API Key', 'type' => 'password', 'description' => 'Fuer Adressvalidierung'],
                ['id' => 'webhook_url', 'section' => 'apis', 'title' => 'Webhook URL', 'type' => 'url', 'description' => 'Empfaengt alle Formulardaten als JSON POST'],
                ['id' => 'webhook_test_mode', 'section' => 'apis', 'title' => 'Webhook Test-Modus', 'type' => 'checkbox', 'default' => false, 'description' => 'Sendet X-DGPTM-Test-Mode: 1 Header'],
                // Zoho
                ['id' => 'client_id', 'section' => 'zoho', 'title' => 'Zoho Client ID', 'type' => 'text'],
                ['id' => 'client_secret', 'section' => 'zoho', 'title' => 'Zoho Client Secret', 'type' => 'password']
            ]
        ]);

        $this->migrate_mitgliedsantrag_settings();
    }

    private function migrate_mitgliedsantrag_settings() {
        if (get_option('dgptm_mitgliedsantrag_migrated')) return;

        $old_options = get_option('dgptm_mitgliedsantrag_options', []);
        if (!empty($old_options)) {
            $new_settings = [];
            $mapping = [
                'disable_formidable_conflicts' => 'disable_formidable_conflicts',
                'default_country_code' => 'default_country_code',
                'google_maps_api_key' => 'google_maps_api_key',
                'webhook_url' => 'webhook_url',
                'webhook_test_mode' => 'webhook_test_mode',
                'client_id' => 'client_id',
                'client_secret' => 'client_secret'
            ];

            foreach ($mapping as $old => $new) {
                if (isset($old_options[$old])) {
                    $new_settings[$new] = $old_options[$old];
                }
            }

            if (!empty($new_settings)) {
                update_option('dgptm_module_settings_mitgliedsantrag', $new_settings);
            }
        }
        update_option('dgptm_mitgliedsantrag_migrated', true);
    }

    /**
     * Stellenanzeige Settings
     */
    private function register_stellenanzeige_settings() {
        // Seiten-Dropdown
        $page_options = [0 => '-- Seite waehlen --'];
        if (function_exists('get_pages')) {
            $pages = get_pages(['post_status' => 'publish']);
            if ($pages) {
                foreach ($pages as $page) {
                    $page_options[$page->ID] = $page->post_title;
                }
            }
        }

        dgptm_register_module_settings([
            'id' => 'stellenanzeige',
            'title' => 'Stellenanzeigen Manager',
            'menu_title' => 'Stellenanzeigen',
            'icon' => 'dashicons-businessman',
            'priority' => 45,
            'sections' => [
                ['id' => 'pages', 'title' => 'Seiten-Zuordnung', 'description' => 'Seiten mit den Stellenanzeigen-Shortcodes.']
            ],
            'fields' => [
                ['id' => 'editor_page_id', 'section' => 'pages', 'title' => 'Editor-Seite', 'type' => 'select', 'options' => $page_options, 'description' => 'Seite mit [stellenanzeigen_editor]'],
                ['id' => 'bearbeiten_page_id', 'section' => 'pages', 'title' => 'Bearbeiten-Seite', 'type' => 'select', 'options' => $page_options, 'description' => 'Seite mit [stellenanzeige_bearbeiten]']
            ]
        ]);

        $this->migrate_stellenanzeige_settings();
    }

    private function migrate_stellenanzeige_settings() {
        if (get_option('dgptm_stellenanzeige_migrated')) return;

        $new_settings = [];
        $editor = get_option('dgptm_staz_editor_page_id');
        $bearbeiten = get_option('dgptm_staz_bearbeiten_page_id');

        if ($editor) $new_settings['editor_page_id'] = $editor;
        if ($bearbeiten) $new_settings['bearbeiten_page_id'] = $bearbeiten;

        if (!empty($new_settings)) {
            update_option('dgptm_module_settings_stellenanzeige', $new_settings);
        }
        update_option('dgptm_stellenanzeige_migrated', true);
    }

    /**
     * Vimeo Webinare Settings
     */
    private function register_vimeo_webinare_settings() {
        // Seiten-Dropdown
        $page_options = [0 => '-- Seite waehlen --'];
        if (function_exists('get_pages')) {
            $pages = get_pages(['post_status' => 'publish']);
            if ($pages) {
                foreach ($pages as $page) {
                    $page_options[$page->ID] = $page->post_title;
                }
            }
        }

        dgptm_register_module_settings([
            'id' => 'vimeo-webinare',
            'title' => 'Vimeo Webinare',
            'menu_title' => 'Webinare',
            'icon' => 'dashicons-video-alt3',
            'priority' => 46,
            'sections' => [
                ['id' => 'api', 'title' => 'Vimeo API', 'description' => 'Vimeo API-Zugangsdaten fuer Webinar-Import.'],
                ['id' => 'pages', 'title' => 'Seiten-Einstellungen', 'description' => 'Zuordnung der Webinar-Seiten.'],
                ['id' => 'certificate', 'title' => 'Zertifikat-Einstellungen', 'description' => 'E-Mail-Templates fuer Zertifikate.']
            ],
            'fields' => [
                ['id' => 'api_token', 'section' => 'api', 'title' => 'Vimeo API Token', 'type' => 'password', 'required' => true],
                ['id' => 'webinar_page_id', 'section' => 'pages', 'title' => 'Webinar-Seite', 'type' => 'select', 'options' => $page_options],
                ['id' => 'mail_subject', 'section' => 'certificate', 'title' => 'E-Mail Betreff', 'type' => 'text', 'default' => 'Ihre Fortbildungsbescheinigung'],
                ['id' => 'mail_body', 'section' => 'certificate', 'title' => 'E-Mail Text', 'type' => 'textarea', 'rows' => 8, 'description' => 'Platzhalter: {user_name}, {webinar_title}, {points}'],
                ['id' => 'mail_from', 'section' => 'certificate', 'title' => 'Absender-E-Mail', 'type' => 'email']
            ]
        ]);

        $this->migrate_vimeo_webinare_settings();
    }

    private function migrate_vimeo_webinare_settings() {
        if (get_option('dgptm_vimeo_webinare_migrated')) return;

        $new_settings = [];

        $token = get_option('vimeo_webinar_api_token');
        if ($token) $new_settings['api_token'] = $token;

        $page_id = get_option('vw_webinar_page_id');
        if ($page_id) $new_settings['webinar_page_id'] = $page_id;

        $cert_settings = get_option('vw_certificate_settings', []);
        if (!empty($cert_settings)) {
            if (isset($cert_settings['mail_subject'])) $new_settings['mail_subject'] = $cert_settings['mail_subject'];
            if (isset($cert_settings['mail_body'])) $new_settings['mail_body'] = $cert_settings['mail_body'];
            if (isset($cert_settings['mail_from'])) $new_settings['mail_from'] = $cert_settings['mail_from'];
        }

        if (!empty($new_settings)) {
            update_option('dgptm_module_settings_vimeo-webinare', $new_settings);
        }
        update_option('dgptm_vimeo_webinare_migrated', true);
    }

    // ============================================================
    // MEDIA
    // ============================================================

    /**
     * Wissens-Bot Settings
     */
    private function register_wissens_bot_settings() {
        dgptm_register_module_settings([
            'id' => 'wissens-bot',
            'title' => 'Wissens-Bot (KI)',
            'menu_title' => 'Wissens-Bot',
            'icon' => 'dashicons-format-chat',
            'priority' => 10,
            'sections' => [
                ['id' => 'claude', 'title' => 'Claude API', 'description' => 'Anthropic Claude API Konfiguration.'],
                ['id' => 'sharepoint', 'title' => 'SharePoint', 'description' => 'Microsoft SharePoint Integration.'],
                ['id' => 'sources', 'title' => 'Wissensquellen', 'description' => 'Konfiguration der Datenquellen.']
            ],
            'fields' => [
                ['id' => 'claude_api_key', 'section' => 'claude', 'title' => 'Claude API Key', 'type' => 'password', 'required' => true],
                ['id' => 'claude_model', 'section' => 'claude', 'title' => 'Claude Modell', 'type' => 'text', 'default' => 'claude-sonnet-4-5-20250929'],
                ['id' => 'max_tokens', 'section' => 'claude', 'title' => 'Max Tokens', 'type' => 'number', 'default' => 4096, 'min' => 256, 'max' => 8192],
                ['id' => 'sharepoint_tenant', 'section' => 'sharepoint', 'title' => 'Tenant ID', 'type' => 'text'],
                ['id' => 'sharepoint_client_id', 'section' => 'sharepoint', 'title' => 'Client ID', 'type' => 'text'],
                ['id' => 'sharepoint_client_secret', 'section' => 'sharepoint', 'title' => 'Client Secret', 'type' => 'password'],
                ['id' => 'enable_pubmed', 'section' => 'sources', 'title' => 'PubMed aktivieren', 'type' => 'checkbox', 'default' => true],
                ['id' => 'enable_scholar', 'section' => 'sources', 'title' => 'Google Scholar aktivieren', 'type' => 'checkbox', 'default' => false],
                ['id' => 'enable_website', 'section' => 'sources', 'title' => 'Website-Suche aktivieren', 'type' => 'checkbox', 'default' => true]
            ]
        ]);

        $this->migrate_wissens_bot_settings();
    }

    private function migrate_wissens_bot_settings() {
        if (get_option('dgptm_wissens_bot_migrated')) return;

        $old_options = get_option('wissens_bot_settings', []);
        if (!empty($old_options)) {
            update_option('dgptm_module_settings_wissens-bot', $old_options);
        }
        update_option('dgptm_wissens_bot_migrated', true);
    }

    /**
     * Vimeo Streams Settings
     */
    private function register_vimeo_streams_settings() {
        dgptm_register_module_settings([
            'id' => 'vimeo-streams',
            'title' => 'Vimeo Streams',
            'menu_title' => 'Vimeo Streams',
            'icon' => 'dashicons-video-alt2',
            'priority' => 47,
            'sections' => [
                ['id' => 'api', 'title' => 'Vimeo API', 'description' => 'API-Zugangsdaten fuer Vimeo Streaming.']
            ],
            'fields' => [
                ['id' => 'access_token', 'section' => 'api', 'title' => 'Access Token', 'type' => 'password', 'required' => true],
                ['id' => 'default_privacy', 'section' => 'api', 'title' => 'Standard-Datenschutz', 'type' => 'select', 'options' => [
                    'anybody' => 'Oeffentlich',
                    'nobody' => 'Privat',
                    'password' => 'Passwortgeschuetzt',
                    'users' => 'Nur Vimeo-Nutzer'
                ], 'default' => 'anybody']
            ]
        ]);
    }

    // ============================================================
    // PAYMENT
    // ============================================================

    /**
     * Stripe Formidable Settings
     */
    private function register_stripe_formidable_settings() {
        dgptm_register_module_settings([
            'id' => 'stripe-formidable',
            'title' => 'Stripe + Formidable',
            'menu_title' => 'Stripe',
            'icon' => 'dashicons-money-alt',
            'priority' => 50,
            'sections' => [
                ['id' => 'keys', 'title' => 'API-Schluessel', 'description' => 'Stripe API Zugangsdaten.'],
                ['id' => 'forms', 'title' => 'Formular-Zuordnung', 'description' => 'Formidable Forms Konfiguration.']
            ],
            'fields' => [
                ['id' => 'publishable_key', 'section' => 'keys', 'title' => 'Publishable Key', 'type' => 'text', 'required' => true],
                ['id' => 'secret_key', 'section' => 'keys', 'title' => 'Secret Key', 'type' => 'password', 'required' => true],
                ['id' => 'test_mode', 'section' => 'keys', 'title' => 'Test-Modus', 'type' => 'checkbox', 'default' => true],
                ['id' => 'webhook_secret', 'section' => 'keys', 'title' => 'Webhook Secret', 'type' => 'password'],
                ['id' => 'form_id', 'section' => 'forms', 'title' => 'Formular-ID', 'type' => 'number', 'description' => 'ID des Zahlungsformulars'],
                ['id' => 'amount_field', 'section' => 'forms', 'title' => 'Betrag-Feld ID', 'type' => 'number'],
                ['id' => 'email_field', 'section' => 'forms', 'title' => 'E-Mail-Feld ID', 'type' => 'number']
            ]
        ]);

        $this->migrate_stripe_settings();
    }

    private function migrate_stripe_settings() {
        if (get_option('dgptm_stripe_migrated')) return;

        $old_options = get_option('stripe_formidable_settings', []);
        if (!empty($old_options)) {
            update_option('dgptm_module_settings_stripe-formidable', $old_options);
        }
        update_option('dgptm_stripe_migrated', true);
    }

    /**
     * GoCardless Settings
     */
    private function register_gocardless_settings() {
        dgptm_register_module_settings([
            'id' => 'gocardless',
            'title' => 'GoCardless SEPA',
            'menu_title' => 'GoCardless',
            'icon' => 'dashicons-bank',
            'priority' => 51,
            'sections' => [
                ['id' => 'api', 'title' => 'API-Konfiguration', 'description' => 'GoCardless API Zugangsdaten.'],
                ['id' => 'defaults', 'title' => 'Standard-Werte', 'description' => 'Standard-Einstellungen fuer Lastschriften.']
            ],
            'fields' => [
                ['id' => 'access_token', 'section' => 'api', 'title' => 'Access Token', 'type' => 'password', 'required' => true],
                ['id' => 'environment', 'section' => 'api', 'title' => 'Umgebung', 'type' => 'select', 'options' => [
                    'sandbox' => 'Sandbox (Test)',
                    'live' => 'Live (Produktion)'
                ], 'default' => 'sandbox'],
                ['id' => 'webhook_secret', 'section' => 'api', 'title' => 'Webhook Secret', 'type' => 'password'],
                ['id' => 'default_currency', 'section' => 'defaults', 'title' => 'Standard-Waehrung', 'type' => 'select', 'options' => [
                    'EUR' => 'Euro (EUR)',
                    'GBP' => 'Britisches Pfund (GBP)'
                ], 'default' => 'EUR'],
                ['id' => 'creditor_id', 'section' => 'defaults', 'title' => 'Glaeubiger-ID', 'type' => 'text']
            ]
        ]);

        $this->migrate_gocardless_settings();
    }

    private function migrate_gocardless_settings() {
        if (get_option('dgptm_gocardless_migrated')) return;

        $old_options = get_option('gcl_settings', []);
        if (!empty($old_options)) {
            update_option('dgptm_module_settings_gocardless', $old_options);
        }
        update_option('dgptm_gocardless_migrated', true);
    }

    /**
     * Zoho Books Integration Settings
     */
    private function register_zoho_books_settings() {
        dgptm_register_module_settings([
            'id' => 'zoho-books-integration',
            'title' => 'Zoho Books',
            'menu_title' => 'Zoho Books',
            'icon' => 'dashicons-book',
            'priority' => 52,
            'sections' => [
                ['id' => 'api', 'title' => 'API-Konfiguration', 'description' => 'Zoho Books API Zugangsdaten.']
            ],
            'fields' => [
                ['id' => 'organization_id', 'section' => 'api', 'title' => 'Organization ID', 'type' => 'text', 'required' => true],
                ['id' => 'client_id', 'section' => 'api', 'title' => 'Client ID', 'type' => 'text'],
                ['id' => 'client_secret', 'section' => 'api', 'title' => 'Client Secret', 'type' => 'password'],
                ['id' => 'refresh_token', 'section' => 'api', 'title' => 'Refresh Token', 'type' => 'password']
            ]
        ]);
    }

    // ============================================================
    // UTILITIES
    // ============================================================

    /**
     * EFN Manager Settings
     */
    private function register_efn_manager_settings() {
        $template_options = [
            'Avery Zweckform 3667 (48.5×16.9, 4×16)' => 'Avery Zweckform 3667',
            'LabelIdent EBL048X017PP (48,5×16,9, 4×16)' => 'LabelIdent EBL048X017PP',
            'Zweckform L6011 (63.5×33.9, 3×8)' => 'Zweckform L6011',
            'Zweckform L6021 (70×37, 3×8)' => 'Zweckform L6021',
            'Avery L7160 (63.5×38.1, 3×7)' => 'Avery L7160',
            'Avery L7563 (99.1×38.1, 2×7)' => 'Avery L7563',
            'Zweckform L6021REV-25 (45.7×16.9, 4×16)' => 'Zweckform L6021REV-25'
        ];

        dgptm_register_module_settings([
            'id' => 'efn-manager',
            'title' => 'EFN Manager',
            'menu_title' => 'EFN Manager',
            'icon' => 'dashicons-id',
            'priority' => 60,
            'sections' => [
                ['id' => 'general', 'title' => 'Allgemein', 'description' => 'Allgemeine Einstellungen.'],
                ['id' => 'kiosk', 'title' => 'Kiosk-System', 'description' => 'Einstellungen fuer den Self-Service Kiosk.'],
                ['id' => 'calibration', 'title' => 'Druckkalibierung', 'description' => 'Feinjustage fuer praezisen Druck.'],
                ['id' => 'footer', 'title' => 'Fusszeile', 'description' => 'Footer-Einstellungen auf dem PDF.'],
                ['id' => 'printnode', 'title' => 'PrintNode', 'description' => 'Silent Printing via PrintNode.']
            ],
            'fields' => [
                // General
                ['id' => 'autofill_on_init', 'section' => 'general', 'title' => 'EFN Autofill beim Login', 'type' => 'checkbox', 'default' => true, 'description' => 'EFN automatisch aus Zoho uebernehmen wenn leer'],
                ['id' => 'default_template', 'section' => 'general', 'title' => 'Standard-Vorlage (Download)', 'type' => 'select', 'options' => $template_options, 'default' => 'LabelIdent EBL048X017PP (48,5×16,9, 4×16)'],
                // Kiosk
                ['id' => 'kiosk_webhook', 'section' => 'kiosk', 'title' => 'Kiosk Webhook URL', 'type' => 'url', 'description' => 'Zoho Functions URL fuer Code-Lookup'],
                ['id' => 'kiosk_mode', 'section' => 'kiosk', 'title' => 'Kiosk Modus', 'type' => 'select', 'options' => ['browser' => 'Browser (Chrome Kiosk)', 'printnode' => 'PrintNode (Silent)'], 'default' => 'browser'],
                ['id' => 'kiosk_template', 'section' => 'kiosk', 'title' => 'Kiosk Vorlage', 'type' => 'select', 'options' => $template_options, 'default' => 'LabelIdent EBL048X017PP (48,5×16,9, 4×16)'],
                ['id' => 'debug_default', 'section' => 'kiosk', 'title' => 'Debug-Modus Standard', 'type' => 'checkbox', 'default' => false],
                // Calibration
                ['id' => 'top_correction_mm', 'section' => 'calibration', 'title' => 'Oberste Reihe (mm)', 'type' => 'number', 'default' => -5.0, 'step' => 0.1, 'description' => 'Negativ = nach oben'],
                ['id' => 'bottom_correction_mm', 'section' => 'calibration', 'title' => 'Unterste Reihe (mm)', 'type' => 'number', 'default' => 5.0, 'step' => 0.1, 'description' => 'Positiv = nach unten'],
                ['id' => 'left_correction_mm', 'section' => 'calibration', 'title' => 'Linke Spalte (mm)', 'type' => 'number', 'default' => -5.0, 'step' => 0.1],
                ['id' => 'right_correction_mm', 'section' => 'calibration', 'title' => 'Rechte Spalte (mm)', 'type' => 'number', 'default' => 5.0, 'step' => 0.1],
                // Footer
                ['id' => 'footer_show', 'section' => 'footer', 'title' => 'Fusszeile anzeigen', 'type' => 'checkbox', 'default' => true],
                ['id' => 'footer_from_bottom_mm', 'section' => 'footer', 'title' => 'Abstand vom unteren Rand (mm)', 'type' => 'number', 'default' => 7.0, 'step' => 0.1],
                // PrintNode
                ['id' => 'printnode_api_key', 'section' => 'printnode', 'title' => 'PrintNode API Key', 'type' => 'password'],
                ['id' => 'printnode_printer_id', 'section' => 'printnode', 'title' => 'Printer ID', 'type' => 'number']
            ]
        ]);

        $this->migrate_efn_manager_settings();
    }

    private function migrate_efn_manager_settings() {
        if (get_option('dgptm_efn_manager_migrated')) return;

        $new_settings = [];
        $mapping = [
            'dgptm_efn_autofill_on_init' => 'autofill_on_init',
            'dgptm_default_template' => 'default_template',
            'dgptm_kiosk_webhook' => 'kiosk_webhook',
            'dgptm_kiosk_mode' => 'kiosk_mode',
            'dgptm_kiosk_template' => 'kiosk_template',
            'dgptm_debug_default' => 'debug_default',
            'dgptm_kiosk_top_correction_mm' => 'top_correction_mm',
            'dgptm_kiosk_bottom_correction_mm' => 'bottom_correction_mm',
            'dgptm_kiosk_left_correction_mm' => 'left_correction_mm',
            'dgptm_kiosk_right_correction_mm' => 'right_correction_mm',
            'dgptm_footer_show' => 'footer_show',
            'dgptm_footer_from_bottom_mm' => 'footer_from_bottom_mm',
            'dgptm_printnode_api_key' => 'printnode_api_key',
            'dgptm_printnode_printer_id' => 'printnode_printer_id'
        ];

        foreach ($mapping as $old => $new) {
            $val = get_option($old);
            if ($val !== false && $val !== '') {
                $new_settings[$new] = $val;
            }
        }

        if (!empty($new_settings)) {
            update_option('dgptm_module_settings_efn-manager', $new_settings);
        }
        update_option('dgptm_efn_manager_migrated', true);
    }

    /**
     * Role Manager Settings
     */
    private function register_role_manager_settings() {
        dgptm_register_module_settings([
            'id' => 'role-manager',
            'title' => 'Rollen Manager',
            'menu_title' => 'Rollen Manager',
            'icon' => 'dashicons-groups',
            'priority' => 61,
            'sections' => [
                ['id' => 'sync', 'title' => 'CRM-Synchronisation', 'description' => 'Einstellungen fuer die Rollen-Synchronisation mit Zoho CRM.']
            ],
            'fields' => [
                ['id' => 'auto_sync', 'section' => 'sync', 'title' => 'Auto-Sync aktivieren', 'type' => 'checkbox', 'default' => false, 'description' => 'Automatische Rollenzuweisung bei Login'],
                ['id' => 'sync_field', 'section' => 'sync', 'title' => 'CRM-Feld fuer Rollen', 'type' => 'text', 'default' => 'Member_Role', 'description' => 'Feldname in Zoho CRM']
            ]
        ]);
    }

    /**
     * Session Display Settings
     */
    private function register_session_display_settings() {
        dgptm_register_module_settings([
            'id' => 'dgptm-session-display',
            'title' => 'Session Display',
            'menu_title' => 'Session Display',
            'icon' => 'dashicons-visibility',
            'priority' => 62,
            'sections' => [
                ['id' => 'general', 'title' => 'Allgemein', 'description' => 'Einstellungen fuer die Session-Anzeige.']
            ],
            'fields' => [
                ['id' => 'show_in_admin_bar', 'section' => 'general', 'title' => 'In Admin Bar anzeigen', 'type' => 'checkbox', 'default' => true],
                ['id' => 'debug_mode', 'section' => 'general', 'title' => 'Debug-Modus', 'type' => 'checkbox', 'default' => false]
            ]
        ]);
    }

    /**
     * Elementor AI Export Settings
     */
    private function register_elementor_ai_export_settings() {
        dgptm_register_module_settings([
            'id' => 'elementor-ai-export',
            'title' => 'Elementor AI Export',
            'menu_title' => 'Elementor AI',
            'icon' => 'dashicons-download',
            'priority' => 63,
            'sections' => [
                ['id' => 'export', 'title' => 'Export-Einstellungen', 'description' => 'Konfiguration des Elementor-Exports.']
            ],
            'fields' => [
                ['id' => 'include_styles', 'section' => 'export', 'title' => 'CSS einbinden', 'type' => 'checkbox', 'default' => true],
                ['id' => 'minify_output', 'section' => 'export', 'title' => 'Ausgabe minimieren', 'type' => 'checkbox', 'default' => false]
            ]
        ]);
    }

    /**
     * ACF JetEngine Sync Settings
     */
    private function register_acf_jetsync_settings() {
        dgptm_register_module_settings([
            'id' => 'acf-jetsync',
            'title' => 'ACF JetEngine Sync',
            'menu_title' => 'ACF JetSync',
            'icon' => 'dashicons-update',
            'priority' => 80,
            'sections' => [
                ['id' => 'sync', 'title' => 'Sync-Einstellungen', 'description' => 'Konfiguration der Synchronisation zwischen ACF und JetEngine.']
            ],
            'fields' => [
                ['id' => 'auto_sync', 'section' => 'sync', 'title' => 'Auto-Sync aktivieren', 'type' => 'checkbox', 'default' => false],
                ['id' => 'sync_direction', 'section' => 'sync', 'title' => 'Sync-Richtung', 'type' => 'select', 'options' => [
                    'acf_to_jet' => 'ACF zu JetEngine',
                    'jet_to_acf' => 'JetEngine zu ACF',
                    'bidirectional' => 'Bidirektional'
                ], 'default' => 'acf_to_jet']
            ]
        ]);
    }

    /**
     * GitHub Sync Settings
     */
    private function register_github_sync_settings() {
        dgptm_register_module_settings([
            'id' => 'github-sync',
            'title' => 'GitHub Sync',
            'menu_title' => 'GitHub Sync',
            'icon' => 'dashicons-update-alt',
            'priority' => 81,
            'sections' => [
                ['id' => 'webhook', 'title' => 'Webhook-Konfiguration', 'description' => 'Einstellungen fuer die GitHub Webhook-Integration.'],
                ['id' => 'backup', 'title' => 'Backup-Einstellungen', 'description' => 'Konfiguration der automatischen Backups.']
            ],
            'fields' => [
                ['id' => 'webhook_secret', 'section' => 'webhook', 'title' => 'Webhook Secret', 'type' => 'password', 'required' => true, 'description' => 'Geheimer Schluessel zur Verifizierung von GitHub Webhooks'],
                ['id' => 'auto_backup', 'section' => 'backup', 'title' => 'Auto-Backup vor Sync', 'type' => 'checkbox', 'default' => true, 'description' => 'Erstellt automatisch ein Backup vor jedem Sync'],
                ['id' => 'backup_retention', 'section' => 'backup', 'title' => 'Backup-Aufbewahrung (Tage)', 'type' => 'number', 'default' => 7, 'min' => 1, 'max' => 30, 'description' => 'Wie lange Backups aufbewahrt werden']
            ]
        ]);

        $this->migrate_github_sync_settings();
    }

    private function migrate_github_sync_settings() {
        $current_settings = get_option('dgptm_module_settings_github-sync', []);
        $old_options = get_option('dgptm_github_sync_options', []);

        $updated = false;
        $keys = ['webhook_secret', 'auto_backup', 'backup_retention'];
        foreach ($keys as $key) {
            if (empty($current_settings[$key]) && isset($old_options[$key])) {
                $current_settings[$key] = $old_options[$key];
                $updated = true;
            }
        }

        if ($updated) {
            update_option('dgptm_module_settings_github-sync', $current_settings);
        }
    }

    /**
     * Daten bearbeiten Settings
     */
    private function register_daten_bearbeiten_settings() {
        dgptm_register_module_settings([
            'id' => 'daten-bearbeiten',
            'title' => 'Daten bearbeiten',
            'menu_title' => 'Daten bearbeiten',
            'icon' => 'dashicons-edit',
            'priority' => 82,
            'sections' => [
                ['id' => 'gocardless', 'title' => 'GoCardless API', 'description' => 'GoCardless API-Zugangsdaten fuer SEPA-Lastschriften.']
            ],
            'fields' => [
                ['id' => 'gocardless_token', 'section' => 'gocardless', 'title' => 'GoCardless API Token', 'type' => 'password', 'required' => true, 'description' => 'Live- oder Sandbox-Token von GoCardless']
            ]
        ]);

        $this->migrate_daten_bearbeiten_settings();
    }

    private function migrate_daten_bearbeiten_settings() {
        $current_settings = get_option('dgptm_module_settings_daten-bearbeiten', []);

        // Nur migrieren wenn zentrale Settings leer
        if (!empty($current_settings['gocardless_token'])) {
            return;
        }

        $token = '';

        // Versuch 1: Array-Option
        $old_options = get_option('dgptm_daten_bearbeiten_options', []);
        if (is_array($old_options) && !empty($old_options['gocardless_token'])) {
            $token = $old_options['gocardless_token'];
        }

        // Versuch 2: Direkter Option-Name
        if (empty($token)) {
            $direct = get_option('dgptm_gocardless_token', '');
            if (!empty($direct)) {
                $token = $direct;
            }
        }

        // Versuch 3: Alte gcl_settings (GoCardless Modul)
        if (empty($token)) {
            $gcl = get_option('gcl_settings', []);
            if (is_array($gcl) && !empty($gcl['api_token'])) {
                $token = $gcl['api_token'];
            }
        }

        if (!empty($token)) {
            $current_settings['gocardless_token'] = $token;
            update_option('dgptm_module_settings_daten-bearbeiten', $current_settings);
        }
    }

    /**
     * Abstimmen-Addon Settings
     */
    private function register_abstimmen_addon_settings() {
        dgptm_register_module_settings([
            'id' => 'abstimmen-addon',
            'title' => 'Abstimmungs-Tool',
            'menu_title' => 'Abstimmen',
            'icon' => 'dashicons-chart-pie',
            'priority' => 25,
            'sections' => [
                ['id' => 'general', 'title' => 'Allgemein', 'description' => 'Grundeinstellungen fuer das Abstimmungs-Tool.'],
                ['id' => 'zoom', 'title' => 'Zoom Integration', 'description' => 'Zoom Meeting/Webinar Integration (S2S OAuth).'],
                ['id' => 'notifications', 'title' => 'Benachrichtigungen', 'description' => 'E-Mail-Einstellungen.']
            ],
            'fields' => [
                // General
                ['id' => 'default_question_type', 'section' => 'general', 'title' => 'Standard Fragetyp', 'type' => 'select', 'options' => [
                    'single' => 'Einzelauswahl',
                    'multi' => 'Mehrfachauswahl'
                ], 'default' => 'single'],
                ['id' => 'results_visible', 'section' => 'general', 'title' => 'Ergebnisse nach Abstimmung anzeigen', 'type' => 'checkbox', 'default' => true],
                // Zoom
                ['id' => 'zoom_account_id', 'section' => 'zoom', 'title' => 'Zoom Account ID', 'type' => 'text'],
                ['id' => 'zoom_client_id', 'section' => 'zoom', 'title' => 'Zoom Client ID', 'type' => 'text'],
                ['id' => 'zoom_client_secret', 'section' => 'zoom', 'title' => 'Zoom Client Secret', 'type' => 'password'],
                ['id' => 'zoom_webhook_secret', 'section' => 'zoom', 'title' => 'Zoom Webhook Secret', 'type' => 'password'],
                // Notifications
                ['id' => 'send_email_on_vote', 'section' => 'notifications', 'title' => 'E-Mail bei Stimmabgabe', 'type' => 'checkbox', 'default' => false],
                ['id' => 'admin_email', 'section' => 'notifications', 'title' => 'Admin E-Mail', 'type' => 'email', 'description' => 'E-Mail fuer Benachrichtigungen']
            ]
        ]);

        $this->migrate_abstimmen_addon_settings();
    }

    private function migrate_abstimmen_addon_settings() {
        $current_settings = get_option('dgptm_module_settings_abstimmen-addon', []);
        $old_options = get_option('dgptm_vote_settings', []);

        $mapping = [
            'default_question_type' => 'default_question_type',
            'results_visible' => 'results_visible',
            'zoom_account_id' => 'zoom_account_id',
            'zoom_client_id' => 'zoom_client_id',
            'zoom_client_secret' => 'zoom_client_secret',
            'zoom_webhook_secret' => 'zoom_webhook_secret',
            'send_email_on_vote' => 'send_email_on_vote',
            'admin_email' => 'admin_email'
        ];

        $updated = false;
        foreach ($mapping as $old => $new) {
            if (empty($current_settings[$new]) && isset($old_options[$old])) {
                $current_settings[$new] = $old_options[$old];
                $updated = true;
            }
        }

        if ($updated) {
            update_option('dgptm_module_settings_abstimmen-addon', $current_settings);
        }
    }
}

// Initialisieren - direkt beim Laden der Datei
DGPTM_Central_Settings_Registry::get_instance();
