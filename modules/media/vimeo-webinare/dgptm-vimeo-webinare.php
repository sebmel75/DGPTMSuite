<?php
/**
 * Plugin Name: DGPTM - Vimeo Webinare
 * Plugin URI: https://dgptm.de
 * Description: Vimeo Videos als Webinare mit dynamischen URLs, automatischen Fortbildungspunkten, Zertifikaten und Frontend-Manager
 * Version: 2.0.0
 * Author: Sebastian Melzer
 * Author URI: https://dgptm.de
 * License: GPL v2 or later
 * Text Domain: dgptm-vimeo-webinare
 */

if (!defined('ABSPATH')) {
    exit;
}

// ACF-Feld für Manager/Statistiken-Berechtigung (zentral konfigurierbar)
if (!defined('DGPTM_VW_PERMISSION_FIELD')) {
    define('DGPTM_VW_PERMISSION_FIELD', 'webinar');
}

/**
 * Helper-Funktion fuer Vimeo Webinare Settings
 * Verwendet das zentrale DGPTM Settings-System mit Fallback auf alte Options
 */
if (!function_exists('dgptm_vw_get_setting')) {
    function dgptm_vw_get_setting($key, $default = null) {
        // Mapping von alten Option-Keys auf neue zentrale Keys
        static $key_mapping = [
            'vimeo_webinar_api_token' => 'api_token',
            'vw_webinar_page_id' => 'webinar_page_id'
        ];

        // Neuen Key bestimmen
        $new_key = isset($key_mapping[$key]) ? $key_mapping[$key] : $key;

        // Zuerst im zentralen System suchen
        if (function_exists('dgptm_get_module_setting')) {
            $value = dgptm_get_module_setting('vimeo-webinare', $new_key, null);
            if ($value !== null) {
                return $value;
            }
        }

        // Fallback auf alten Option-Key
        return get_option($key, $default);
    }
}

/**
 * Helper fuer Certificate Settings (Array)
 *
 * Basis ist immer die Option "vw_certificate_settings" — enthaelt ALLE
 * Felder (orientation, Bilder, Texte, Template-HTML/CSS, Mail). Nur die
 * drei Mail-Felder koennen optional aus dem zentralen DGPTM-Module-
 * Settings-System ueberschrieben werden, falls dort gepflegt.
 *
 * Fixt zwei Altbugs:
 *   - Rueckgabe verlor alle Nicht-Mail-Felder, sobald zentrale Mail-
 *     Settings existierten (Template-Felder wurden so nach dem Speichern
 *     sofort wieder als leer gelesen).
 *   - Endlos-Rekursion im Fallback-Zweig (Funktion rief sich selbst).
 */
if (!function_exists('dgptm_vw_get_certificate_settings')) {
    function dgptm_vw_get_certificate_settings() {
        $settings = get_option('vw_certificate_settings', []);
        if (!is_array($settings)) $settings = [];

        if (function_exists('dgptm_get_module_setting')) {
            foreach (['mail_subject', 'mail_body', 'mail_from'] as $k) {
                $v = dgptm_get_module_setting('vimeo-webinare', $k, null);
                if ($v !== null && $v !== '') {
                    $settings[$k] = $v;
                }
            }
        }

        // Defensive Defaults (nur wenn nichts in DB)
        if (empty($settings['mail_subject'])) $settings['mail_subject'] = 'Ihre Fortbildungsbescheinigung';
        if (!isset($settings['mail_from']) || $settings['mail_from'] === '') $settings['mail_from'] = get_option('admin_email');
        if (!isset($settings['mail_body'])) $settings['mail_body'] = '';

        return $settings;
    }
}

// Prevent class redeclaration
if (!class_exists('DGPTM_Vimeo_Webinare')) {

class DGPTM_Vimeo_Webinare {

    private static $instance = null;
    private $plugin_path;
    private $plugin_url;
    
    // Datenbank-Version für Migrations-Tracking
    const DB_VERSION = '1.3.1';
    const DB_VERSION_OPTION = 'vw_db_version';

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);

        // Repository-Klasse für Daten- und Stats-Zugriffe
        require_once $this->plugin_path . 'includes/class-webinar-repository.php';

        // Zertifikat-Presets
        require_once $this->plugin_path . 'includes/class-certificate-presets.php';

        // Shortcode-Klasse: Manager (registriert eigene Shortcode- und AJAX-Handler)
        require_once $this->plugin_path . 'includes/class-shortcode-manager.php';
        DGPTM_VW_Shortcode_Manager::get_instance();

        // Shortcode-Klasse: Statistiken
        require_once $this->plugin_path . 'includes/class-shortcode-statistiken.php';
        DGPTM_VW_Shortcode_Statistiken::get_instance();

        // Shortcode-Klasse: Frontend-Liste
        require_once $this->plugin_path . 'includes/class-shortcode-liste.php';
        DGPTM_VW_Shortcode_Liste::get_instance();

        // Datenbank-Check bei erster Nutzung (nicht bei Aktivierung)
        add_action('init', [$this, 'maybe_create_tables'], 1);

        // Initialize
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_acf_fields']);
        add_action('init', [$this, 'add_rewrite_rules']);

        // Template redirect for dynamic pages
        add_action('template_redirect', [$this, 'handle_webinar_page']);

        // Shortcodes
        add_shortcode('vimeo_webinar', [$this, 'webinar_player_shortcode']);
        // vimeo_webinar_manager wird in DGPTM_VW_Shortcode_Manager registriert
        // vimeo_webinar_liste wird in DGPTM_VW_Shortcode_Liste registriert
        // vimeo_webinar_statistiken wird in DGPTM_VW_Shortcode_Statistiken registriert

        // AJAX Handlers - Logged in users
        add_action('wp_ajax_vw_track_progress', [$this, 'ajax_track_progress']);
        add_action('wp_ajax_vw_complete_webinar', [$this, 'ajax_complete_webinar']);
        add_action('wp_ajax_vw_generate_certificate', [$this, 'ajax_generate_certificate']);
        // vw_manager_* AJAX-Handler wurden durch dgptm_vw_* in DGPTM_VW_Shortcode_Manager ersetzt
        add_action('wp_ajax_vw_transfer_cookie_progress', [$this, 'ajax_transfer_cookie_progress']);
        add_action('wp_ajax_vw_preview_certificate', [$this, 'ajax_preview_certificate']);

        // PDF-Live-Preview fuer den HTML/CSS-Template-Designer
        add_action('admin_post_dgptm_vw_preview_certificate', [$this, 'handle_preview_certificate_pdf']);

        // AJAX Handlers - Non-logged in users (für Cookie-Tracking)
        add_action('wp_ajax_nopriv_vw_track_progress', [$this, 'ajax_track_progress_nopriv']);

        // Batch Import AJAX Handlers
        add_action('wp_ajax_vw_test_vimeo_connection', [$this, 'ajax_test_vimeo_connection']);
        add_action('wp_ajax_vw_get_vimeo_folders', [$this, 'ajax_get_vimeo_folders']);
        add_action('wp_ajax_vw_import_folder_videos', [$this, 'ajax_import_folder_videos']);

        // Load Vimeo API class
        require_once $this->plugin_path . 'includes/class-vimeo-api.php';

        // Enqueue Scripts & Styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Admin
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'admin_enqueue_scripts']);
    }

    /**
     * Idempotente Asset-Registrierung fuer Dashboard-Shortcodes.
     *
     * Wird aus den Shortcode-Klassen aufgerufen, falls enqueue_assets() den
     * Shortcode nicht erkannt hat (Dashboard-Tab-Inhalte leben nicht im
     * post_content). Funktioniert nur bei initial aktiven Tabs — AJAX-
     * Nachladungen werden durch die Tab-Config-Detection in enqueue_assets()
     * abgefangen.
     *
     * @param string $which 'manager' | 'liste' | 'statistiken'
     */
    public function enqueue_dashboard_assets(string $which): void {
        if (!wp_style_is('dgptm-vw-dashboard-integration', 'enqueued')) {
            wp_enqueue_style(
                'dgptm-vw-dashboard-integration',
                $this->plugin_url . 'assets/css/dashboard-integration.css',
                [],
                '2.0.0'
            );
        }
        wp_enqueue_style('dashicons');

        $assets = [
            'manager'     => ['css' => 'manager.css',     'js' => 'manager.js'],
            'liste'       => ['css' => 'liste.css',       'js' => 'liste.js'],
            'statistiken' => ['css' => 'statistiken.css', 'js' => 'statistiken.js'],
        ];
        if (!isset($assets[$which])) return;

        $handle = 'dgptm-vw-' . $which;
        if (!wp_style_is($handle, 'enqueued')) {
            wp_enqueue_style(
                $handle,
                $this->plugin_url . 'assets/css/' . $assets[$which]['css'],
                ['dgptm-vw-dashboard-integration'],
                '2.0.0'
            );
        }
        if (!wp_script_is($handle, 'enqueued')) {
            wp_enqueue_script(
                $handle,
                $this->plugin_url . 'assets/js/' . $assets[$which]['js'],
                ['jquery'],
                '2.0.0',
                true
            );
        }
    }

    /**
     * Autorisierungs-Pruefung fuer schreibende Manager-Operationen.
     *
     * Prueft eingeloggt + ACF-Feld DGPTM_VW_PERMISSION_FIELD am User.
     * Bewusst kein current_user_can() / Rollencheck - Berechtigung ist
     * Sache der ACF-Konfiguration im User-Profil.
     */
    public function user_can_manage_webinars(): bool {
        if (!is_user_logged_in()) return false;
        if (!function_exists('get_field')) return false;
        return (bool) get_field(
            DGPTM_VW_PERMISSION_FIELD,
            'user_' . get_current_user_id()
        );
    }

    /**
     * Datenbank-Tabellen bei erster Nutzung erstellen
     * Wird bei jedem Init geprüft, aber nur ausgeführt wenn nötig
     */
    public function maybe_create_tables() {
        $installed_version = get_option(self::DB_VERSION_OPTION);
        
        // Wenn Version gleich, nichts tun
        if ($installed_version === self::DB_VERSION) {
            return;
        }
        
        // Tabellen erstellen/aktualisieren
        $this->create_tables();
        
        // Rewrite Rules flushen (einmalig nach Installation)
        if (!$installed_version) {
            // Erste Installation
            flush_rewrite_rules();
        }
        
        // Version speichern
        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
    }

    /**
     * Erstellt alle benötigten Datenbank-Tabellen
     */
    private function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabelle für Webinar-Fortschritt (Alternative zu User-Meta für bessere Performance)
        $table_progress = $wpdb->prefix . 'vw_progress';
        
        $sql_progress = "CREATE TABLE $table_progress (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            webinar_id bigint(20) unsigned NOT NULL,
            watched_time float NOT NULL DEFAULT 0,
            progress float NOT NULL DEFAULT 0,
            completed tinyint(1) NOT NULL DEFAULT 0,
            completed_date datetime DEFAULT NULL,
            fortbildung_id bigint(20) unsigned DEFAULT NULL,
            last_access datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_webinar (user_id, webinar_id),
            KEY user_id (user_id),
            KEY webinar_id (webinar_id),
            KEY completed (completed),
            KEY last_access (last_access)
        ) $charset_collate;";
        
        // Tabelle für Webinar-Sessions (detailliertes Tracking)
        $table_sessions = $wpdb->prefix . 'vw_sessions';
        
        $sql_sessions = "CREATE TABLE $table_sessions (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned DEFAULT NULL,
            webinar_id bigint(20) unsigned NOT NULL,
            session_token varchar(64) DEFAULT NULL,
            watched_time float NOT NULL DEFAULT 0,
            ip_address varchar(45) DEFAULT NULL,
            user_agent varchar(255) DEFAULT NULL,
            started_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            ended_at datetime DEFAULT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY webinar_id (webinar_id),
            KEY session_token (session_token),
            KEY started_at (started_at)
        ) $charset_collate;";
        
        // Tabelle für Zertifikate
        $table_certificates = $wpdb->prefix . 'vw_certificates';
        
        $sql_certificates = "CREATE TABLE $table_certificates (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            webinar_id bigint(20) unsigned NOT NULL,
            fortbildung_id bigint(20) unsigned DEFAULT NULL,
            certificate_url varchar(500) NOT NULL,
            certificate_hash varchar(64) DEFAULT NULL,
            points float NOT NULL DEFAULT 0,
            generated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY webinar_id (webinar_id),
            KEY fortbildung_id (fortbildung_id),
            KEY certificate_hash (certificate_hash)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($sql_progress);
        dbDelta($sql_sessions);
        dbDelta($sql_certificates);
        
        // Standard-Einstellungen setzen wenn nicht vorhanden
        if (!dgptm_vw_get_certificate_settings()) {
            $default_settings = [
                'orientation' => 'L',
                'background_image' => 0,
                'logo_image' => 0,
                'watermark_image' => 0,
                'watermark_opacity' => 30,
                'watermark_position' => 'center',
                'header_text' => 'Teilnahmebescheinigung',
                'footer_text' => get_bloginfo('name'),
                'signature_text' => '',
                'mail_enabled' => true,
                'mail_subject' => 'Ihr Webinar-Zertifikat: {webinar_title}',
                'mail_body' => "Hallo {user_name},\n\nvielen Dank für Ihre Teilnahme am Webinar \"{webinar_title}\".\n\nIhr Teilnahmezertifikat steht zum Download bereit:\n{certificate_url}\n\nMit freundlichen Grüßen\nIhr Webinar-Team",
                'mail_from' => get_option('admin_email'),
                'mail_from_name' => get_bloginfo('name'),
            ];
            add_option('vw_certificate_settings', $default_settings);
        }
        
        // Zertifikate-Verzeichnis erstellen
        $upload_dir = wp_upload_dir();
        $cert_dir = $upload_dir['basedir'] . '/webinar-certificates/';
        if (!file_exists($cert_dir)) {
            wp_mkdir_p($cert_dir);
            // .htaccess für Schutz (optional)
            file_put_contents($cert_dir . '.htaccess', "Options -Indexes\n");
        }
        
        dgptm_log_info('Database tables created/updated to version ' . self::DB_VERSION, 'vimeo-webinare');
    }

    /**
     * Tabellen löschen (für Deinstallation)
     */
    public static function drop_tables() {
        global $wpdb;
        
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}vw_progress");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}vw_sessions");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}vw_certificates");
        
        delete_option(self::DB_VERSION_OPTION);
        delete_option('vw_certificate_settings');
    }

    /**
     * Register Custom Post Type: Webinar
     */
    public function register_post_types() {
        register_post_type('vimeo_webinar', [
            'labels' => [
                'name' => 'Webinare',
                'singular_name' => 'Webinar',
                'add_new' => 'Neues Webinar',
                'add_new_item' => 'Neues Webinar hinzufügen',
                'edit_item' => 'Webinar bearbeiten',
                'view_item' => 'Webinar ansehen',
                'all_items' => 'Alle Webinare',
                'search_items' => 'Webinare suchen',
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-video-alt2',
            'menu_position' => 25,
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'has_archive' => false,
            'rewrite' => false, // We handle URLs manually
        ]);
    }

    /**
     * Add custom rewrite rules
     */
    public function add_rewrite_rules() {
        // Hole die konfigurierte Webinar-Seite (falls vorhanden)
        $webinar_page_id = dgptm_vw_get_setting('vw_webinar_page_id', 0);
        
        // Pattern: /wissen/webinar/{id} - nur wenn keine eigene Seite konfiguriert
        if (!$webinar_page_id) {
            add_rewrite_rule(
                '^wissen/webinar/([0-9]+)/?$',
                'index.php?vw_webinar_id=$matches[1]',
                'top'
            );

            // Pattern: /wissen/webinar?id={id}
            add_rewrite_rule(
                '^wissen/webinar/?$',
                'index.php?vw_webinar_page=1',
                'top'
            );
        }

        // Register query vars
        add_filter('query_vars', function($vars) {
            $vars[] = 'vw_webinar_id';
            $vars[] = 'vw_webinar_page';
            return $vars;
        });
    }

    /**
     * Handle webinar page template
     * Nur aktiv wenn KEINE eigene Seite konfiguriert ist
     */
    public function handle_webinar_page() {
        global $wp_query;

        // Prüfe ob eine eigene Seite konfiguriert ist
        $webinar_page_id = dgptm_vw_get_setting('vw_webinar_page_id', 0);
        if ($webinar_page_id) {
            // Eigene Seite mit Shortcode - nichts tun
            return;
        }

        // Check if this is a webinar page request
        $webinar_id = get_query_var('vw_webinar_id');
        $is_webinar_page = get_query_var('vw_webinar_page');

        // Handle /wissen/webinar?id=123
        if ($is_webinar_page && isset($_GET['id'])) {
            $webinar_id = intval($_GET['id']);
        }

        if (!$webinar_id) {
            return;
        }

        // Verify webinar exists
        $post = get_post($webinar_id);
        if (!$post || get_post_type($webinar_id) !== 'vimeo_webinar') {
            wp_die('Webinar nicht gefunden', 'Fehler', ['response' => 404]);
        }

        // Get webinar data
        $vimeo_id = get_field('vimeo_id', $webinar_id);
        $completion_percentage = get_field('completion_percentage', $webinar_id) ?: 90;

        if (!$vimeo_id) {
            wp_die('Vimeo Video ID fehlt für dieses Webinar', 'Fehler');
        }

        // User data (kann 0 sein wenn nicht eingeloggt)
        $user_id = get_current_user_id();
        $progress = 0;
        $watched_time = 0;
        $is_completed = false;

        if ($user_id) {
            // Eingeloggter Benutzer - Daten aus DB laden
            $progress = $this->get_user_progress($user_id, $webinar_id);
            $watched_time = $this->get_watched_time($user_id, $webinar_id);
            $is_completed = $this->is_webinar_completed($user_id, $webinar_id);
        } else {
            // Nicht eingeloggt - Daten aus Cookie laden
            $cookie_data = $this->get_cookie_data($webinar_id);
            $watched_time = $cookie_data['watched_time'];
            $progress = $cookie_data['progress'];
        }

        // Set post_id for template
        $post_id = $webinar_id;

        // Force load assets
        $this->force_enqueue_assets();

        // Render template
        $this->render_webinar_template($post_id, $vimeo_id, $completion_percentage, $progress, $watched_time, $is_completed, $user_id);
        exit;
    }

    /**
     * Render webinar template with WordPress header/footer
     */
    private function render_webinar_template($post_id, $vimeo_id, $completion_percentage, $progress, $watched_time, $is_completed, $user_id) {
        // Get WordPress header
        get_header();

        // Output player template
        echo '<div class="vw-page-wrapper">';
        include $this->plugin_path . 'templates/player.php';
        echo '</div>';

        // Get WordPress footer
        get_footer();
    }

    /**
     * Register ACF Fields
     */
    public function register_acf_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        // Webinar Settings
        acf_add_local_field_group([
            'key' => 'group_vimeo_webinar',
            'title' => 'Webinar Einstellungen',
            'fields' => [
                [
                    'key' => 'field_vw_vimeo_id',
                    'label' => 'Vimeo Video ID',
                    'name' => 'vimeo_id',
                    'type' => 'text',
                    'required' => 1,
                    'instructions' => 'Geben Sie die Vimeo Video ID ein (z.B. 123456789)',
                ],
                [
                    'key' => 'field_vw_completion_percentage',
                    'label' => 'Erforderlicher Fortschritt (%)',
                    'name' => 'completion_percentage',
                    'type' => 'number',
                    'required' => 1,
                    'default_value' => 90,
                    'min' => 1,
                    'max' => 100,
                    'instructions' => 'Prozentsatz des Videos, der angesehen werden muss',
                ],
                [
                    'key' => 'field_vw_points',
                    'label' => 'Fortbildungspunkte (EBCP)',
                    'name' => 'ebcp_points',
                    'type' => 'number',
                    'required' => 1,
                    'default_value' => 1,
                    'step' => 0.5,
                    'min' => 0,
                ],
                [
                    'key' => 'field_vw_vnr',
                    'label' => 'VNR',
                    'name' => 'vnr',
                    'type' => 'text',
                    'required' => 0,
                ],
                [
                    'key' => 'field_vw_webinar_date',
                    'label' => 'Datum',
                    'name' => 'webinar_date',
                    'type' => 'date_picker',
                    'required' => 0,
                    'display_format' => 'd.m.Y',
                    'return_format' => 'Y-m-d',
                    'first_day' => 1,
                    'instructions' => 'Optional: Datum des Webinars (wird in der Liste angezeigt)',
                ],
                [
                    'key' => 'field_vw_type',
                    'label' => 'Art der Fortbildung',
                    'name' => 'fortbildung_type',
                    'type' => 'text',
                    'required' => 0,
                    'default_value' => 'Webinar',
                ],
                [
                    'key' => 'field_vw_location',
                    'label' => 'Ort',
                    'name' => 'location',
                    'type' => 'text',
                    'required' => 0,
                    'default_value' => 'Online',
                ],
                [
                    'key' => 'field_vw_certificate_bg',
                    'label' => 'Zertifikat Hintergrundbild',
                    'name' => 'certificate_background',
                    'type' => 'image',
                    'required' => 0,
                    'return_format' => 'array',
                    'instructions' => 'Optional: PNG/JPG als Hintergrund für das Zertifikat',
                ],
                [
                    'key' => 'field_vw_certificate_watermark',
                    'label' => 'Zertifikat Wasserzeichen',
                    'name' => 'certificate_watermark',
                    'type' => 'image',
                    'required' => 0,
                    'return_format' => 'array',
                    'instructions' => 'Optional: PNG als Wasserzeichen für das Zertifikat',
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'vimeo_webinar',
                    ],
                ],
            ],
        ]);

    }

    /**
     * Enqueue Frontend Assets
     */
    public function enqueue_assets() {
        global $post;

        if (!$post) {
            return;
        }

        $content = $post->post_content ?? '';
        $has_player_shortcode = has_shortcode($content, 'vimeo_webinar');
        $has_manager         = has_shortcode($content, 'vimeo_webinar_manager');
        $has_liste           = has_shortcode($content, 'vimeo_webinar_liste');
        $has_stats           = has_shortcode($content, 'vimeo_webinar_statistiken');

        // Wenn Mitglieder-Dashboard auf der Seite ist: Tab-Konfiguration durchsuchen.
        // Das Dashboard rendert Tab-Inhalte erst per AJAX nach (dgptm_dash_load_tab);
        // dort feuert wp_enqueue_scripts nicht mehr. Deshalb muessen die Assets schon
        // beim initialen Page-Load mit erkannt und geladen werden, auch wenn unsere
        // Shortcodes nur im Tab-Content (nicht im post_content) vorkommen.
        // Wenn das Mitglieder-Dashboard-Modul aktiv ist und Vimeo-Shortcodes in
        // seiner Tab-Konfiguration vorkommen, laden wir die Assets auf allen
        // eingeloggten Frontend-Seiten. Grund: Dashboard-Seiten werden oft ueber
        // Page-Builder (Elementor/Divi/Custom Templates) eingebunden — der
        // [dgptm_dashboard]-Shortcode taucht dann nicht zuverlaessig im
        // post_content auf. Tabs werden zudem per AJAX nachgeladen, dort ist
        // wp_enqueue zu spaet. Overhead: ~30 KB CSS+JS pro eingeloggter Seite.
        if (is_user_logged_in() && class_exists('DGPTM_Dashboard_Tabs')) {
            $dash_tabs = DGPTM_Dashboard_Tabs::get_instance()->get_all();
            foreach ($dash_tabs as $t) {
                $tc = ($t['content'] ?? '') . ' ' . ($t['content_mobile'] ?? '');
                if (strpos($tc, 'vimeo_webinar_manager') !== false)      $has_manager = true;
                if (strpos($tc, 'vimeo_webinar_liste') !== false)        $has_liste   = true;
                if (strpos($tc, 'vimeo_webinar_statistiken') !== false)  $has_stats   = true;
            }
        }

        $has_any_shortcode = $has_player_shortcode || $has_manager || $has_liste || $has_stats;

        if (!$has_any_shortcode && get_post_type() !== 'vimeo_webinar') {
            return;
        }

        // Legacy-Assets (Player und alte Liste nutzen style.css/script.js)
        $this->force_enqueue_assets();

        // Dashboard-Design-Tokens + neue Komponenten fuer alle drei neuen Shortcodes
        if ($has_manager || $has_liste || $has_stats) {
            wp_enqueue_style(
                'dgptm-vw-dashboard-integration',
                $this->plugin_url . 'assets/css/dashboard-integration.css',
                [],
                '2.0.0'
            );
            wp_enqueue_style('dashicons');
        }

        // Manager (Dashboard-Tab)
        if ($has_manager) {
            wp_enqueue_style(
                'dgptm-vw-manager',
                $this->plugin_url . 'assets/css/manager.css',
                ['dgptm-vw-dashboard-integration'],
                '2.0.0'
            );
            wp_enqueue_script(
                'dgptm-vw-manager',
                $this->plugin_url . 'assets/js/manager.js',
                ['jquery'],
                '2.0.0',
                true
            );
        }

        // Statistiken (Dashboard-Tab)
        if ($has_stats) {
            wp_enqueue_style(
                'dgptm-vw-statistiken',
                $this->plugin_url . 'assets/css/statistiken.css',
                ['dgptm-vw-dashboard-integration'],
                '2.0.0'
            );
            wp_enqueue_script(
                'dgptm-vw-statistiken',
                $this->plugin_url . 'assets/js/statistiken.js',
                ['jquery'],
                '2.0.0',
                true
            );
        }

        // Frontend-Liste (oeffentliche Seite)
        if ($has_liste) {
            wp_enqueue_style(
                'dgptm-vw-liste',
                $this->plugin_url . 'assets/css/liste.css',
                ['dgptm-vw-dashboard-integration'],
                '2.0.0'
            );
            wp_enqueue_script(
                'dgptm-vw-liste',
                $this->plugin_url . 'assets/js/liste.js',
                ['jquery'],
                '2.0.0',
                true
            );
        }
    }

    /**
     * Force enqueue assets (called from shortcodes)
     */
    public function force_enqueue_assets() {
        // Vimeo Player API - im Header laden für bessere Verfügbarkeit
        if (!wp_script_is('vimeo-player', 'enqueued')) {
            wp_enqueue_script('vimeo-player', 'https://player.vimeo.com/api/player.js', [], null, false); // false = im Header
        }

        // Plugin CSS
        if (!wp_style_is('vw-style', 'enqueued')) {
            wp_enqueue_style('vw-style', $this->plugin_url . 'assets/css/style.css', [], '1.3.1');
        }

        // Plugin JS - im Footer nach jQuery und Vimeo
        if (!wp_script_is('vw-script', 'enqueued')) {
            wp_enqueue_script('vw-script', $this->plugin_url . 'assets/js/script.js', ['jquery', 'vimeo-player'], '1.3.1', true);

            // Localize
            wp_localize_script('vw-script', 'vwData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vw_nonce'),
                'userId' => get_current_user_id(),
                'isLoggedIn' => is_user_logged_in(),
                'loginUrl' => wp_login_url(),
            ]);
        }
    }

    /**
     * Admin Enqueue Scripts
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'vimeo-webinar') !== false) {
            wp_enqueue_media();
            wp_enqueue_style('vw-admin-style', $this->plugin_url . 'assets/css/admin-style.css', [], '1.3.0');
            wp_enqueue_script('vw-admin-script', $this->plugin_url . 'assets/js/admin-script.js', ['jquery', 'wp-color-picker'], '1.3.0', true);
            wp_enqueue_style('wp-color-picker');
        }
    }

    /**
     * Shortcode: Vimeo Webinar Player
     * Usage: [vimeo_webinar id="123"] oder [vimeo_webinar] (ID aus URL)
     */
    public function webinar_player_shortcode($atts) {
        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);

        $post_id = intval($atts['id']);

        // Wenn keine ID im Shortcode, versuche aus URL zu lesen
        if (!$post_id) {
            // Aus Query Var
            $post_id = intval(get_query_var('vw_webinar_id'));
        }
        if (!$post_id && isset($_GET['id'])) {
            // Aus GET Parameter
            $post_id = intval($_GET['id']);
        }
        if (!$post_id && isset($_GET['webinar'])) {
            // Alternativer GET Parameter
            $post_id = intval($_GET['webinar']);
        }

        // Debug: Check if post_id is valid
        if (!$post_id) {
            return '<div class="vw-error-message"><p>Fehler: Keine Webinar-ID angegeben. Verwenden Sie: [vimeo_webinar id="123"] oder fügen Sie ?id=123 zur URL hinzu.</p></div>';
        }

        // Check if post exists and is correct type
        $post = get_post($post_id);
        if (!$post) {
            return '<div class="vw-error-message"><p>Fehler: Webinar mit ID ' . $post_id . ' nicht gefunden.</p></div>';
        }

        if (get_post_type($post_id) !== 'vimeo_webinar') {
            return '<div class="vw-error-message"><p>Fehler: Post ID ' . $post_id . ' ist kein Webinar (Typ: ' . get_post_type($post_id) . ').</p></div>';
        }

        // Get ACF fields
        $vimeo_id = get_field('vimeo_id', $post_id);
        $completion_percentage = get_field('completion_percentage', $post_id);

        // Set defaults if empty
        if (!$completion_percentage) {
            $completion_percentage = 90;
        }

        if (!$vimeo_id) {
            return '<div class="vw-error-message"><p>Fehler: Vimeo Video ID fehlt. Bitte bearbeiten Sie das Webinar und fügen Sie eine Vimeo ID hinzu.</p></div>';
        }

        $user_id = get_current_user_id();
        $progress = 0;
        $watched_time = 0;
        $is_completed = false;

        if ($user_id) {
            $progress = $this->get_user_progress($user_id, $post_id);
            $watched_time = $this->get_watched_time($user_id, $post_id);
            $is_completed = $this->is_webinar_completed($user_id, $post_id);
        } else {
            // Nicht eingeloggt - aus Cookie laden
            $cookie_data = $this->get_cookie_data($post_id);
            $watched_time = $cookie_data['watched_time'];
            $progress = $cookie_data['progress'];
        }

        // Force assets to load
        $this->force_enqueue_assets();

        ob_start();
        include $this->plugin_path . 'templates/player.php';
        return ob_get_clean();
    }

    /**
     * AJAX: Track Progress (für eingeloggte Benutzer)
     */
    public function ajax_track_progress() {
        check_ajax_referer('vw_nonce', 'nonce');

        $webinar_id = intval($_POST['webinar_id'] ?? 0);
        $watched_time = floatval($_POST['watched_time'] ?? 0);
        $duration = floatval($_POST['duration'] ?? 0);

        if (!$webinar_id) {
            wp_send_json_error(['message' => 'Invalid webinar ID']);
        }

        $user_id = get_current_user_id();

        // Video-Dauer cachen (einmalig)
        if ($duration > 0) {
            $cached_duration = get_post_meta($webinar_id, '_vw_video_duration', true);
            if (!$cached_duration) {
                update_post_meta($webinar_id, '_vw_video_duration', $duration);
            }
        }

        if ($user_id) {
            // Eingeloggter Benutzer - in DB speichern
            $current_watched = $this->get_watched_time($user_id, $webinar_id);

            // Addiere neue angesehene Zeit (aber max. Videolänge)
            $new_watched = min($duration, $current_watched + $watched_time);
            
            // Berechne Prozent
            $progress = $duration > 0 ? min(100, ($new_watched / $duration) * 100) : 0;
            
            // In DB speichern (nutzt Tabelle oder User-Meta als Fallback)
            $this->save_user_progress($user_id, $webinar_id, $new_watched, $progress);

            wp_send_json_success([
                'watched_time' => $new_watched,
                'progress' => $progress,
                'logged_in' => true
            ]);
        } else {
            wp_send_json_success([
                'watched_time' => $watched_time,
                'progress' => 0,
                'logged_in' => false,
                'message' => 'Bitte einloggen für Fortbildungsnachweis'
            ]);
        }
    }

    /**
     * AJAX: Track Progress für nicht eingeloggte Benutzer
     */
    public function ajax_track_progress_nopriv() {
        // Kein Nonce-Check für nicht eingeloggte Benutzer nötig
        // Cookie wird clientseitig gesetzt

        $webinar_id = intval($_POST['webinar_id'] ?? 0);
        $duration = floatval($_POST['duration'] ?? 0);

        if (!$webinar_id) {
            wp_send_json_error(['message' => 'Invalid webinar ID']);
        }

        // Video-Dauer cachen
        if ($duration > 0) {
            $cached_duration = get_post_meta($webinar_id, '_vw_video_duration', true);
            if (!$cached_duration) {
                update_post_meta($webinar_id, '_vw_video_duration', $duration);
            }
        }

        wp_send_json_success([
            'logged_in' => false,
            'message' => 'Fortschritt wird lokal gespeichert. Für Fortbildungspunkte bitte einloggen.'
        ]);
    }

    /**
     * AJAX: Transfer Cookie Progress nach Login
     */
    public function ajax_transfer_cookie_progress() {
        check_ajax_referer('vw_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Nicht eingeloggt']);
        }

        $cookie_data = $_POST['cookie_data'] ?? [];

        if (empty($cookie_data) || !is_array($cookie_data)) {
            wp_send_json_error(['message' => 'Keine Cookie-Daten']);
        }

        $transferred = [];

        foreach ($cookie_data as $webinar_id => $data) {
            $webinar_id = intval($webinar_id);
            if (!$webinar_id) continue;

            $watched_time = floatval($data['watched_time'] ?? 0);
            $progress = floatval($data['progress'] ?? 0);

            if ($watched_time <= 0) continue;

            // Nur übertragen wenn mehr als aktueller Fortschritt
            $current_watched = $this->get_watched_time($user_id, $webinar_id);

            if ($watched_time > $current_watched) {
                $this->save_user_progress($user_id, $webinar_id, $watched_time, $progress);
                $transferred[] = $webinar_id;
            }
        }

        wp_send_json_success([
            'transferred' => $transferred,
            'message' => count($transferred) . ' Webinar-Fortschritte übertragen'
        ]);
    }

    /**
     * AJAX: Complete Webinar
     */
    public function ajax_complete_webinar() {
        check_ajax_referer('vw_nonce', 'nonce');

        $user_id = get_current_user_id();
        $webinar_id = intval($_POST['webinar_id'] ?? 0);

        dgptm_log_verbose('Complete Webinar - User: ' . $user_id . ', Webinar: ' . $webinar_id, 'vimeo-webinare');

        if (!$user_id || !$webinar_id) {
            dgptm_log_error('Complete Webinar - Invalid data', 'vimeo-webinare');
            wp_send_json_error(['message' => 'Invalid data']);
        }

        // Prüfe ob wirklich genug geschaut wurde
        $watched_time = $this->get_watched_time($user_id, $webinar_id);
        $duration = $this->get_video_duration($webinar_id);
        $required = get_field('completion_percentage', $webinar_id) ?: 90;

        $actual_progress = $duration > 0 ? ($watched_time / $duration) * 100 : 0;

        if ($actual_progress < $required) {
            dgptm_log_error('Complete Webinar - Not enough progress. Actual: ' . $actual_progress . ', Required: ' . $required, 'vimeo-webinare');
            wp_send_json_error([
                'message' => 'Nicht genug angesehen. Aktuell: ' . number_format($actual_progress, 1) . '%, Erforderlich: ' . $required . '%'
            ]);
        }

        // Check if already completed
        if ($this->is_webinar_completed($user_id, $webinar_id)) {
            dgptm_log_warning('Complete Webinar - Already completed', 'vimeo-webinare');
            wp_send_json_error(['message' => 'Webinar bereits abgeschlossen']);
        }

        // Create Fortbildung entry
        dgptm_log_verbose('Complete Webinar - Creating Fortbildung entry...', 'vimeo-webinare');
        $fortbildung_id = $this->create_fortbildung_entry($user_id, $webinar_id);

        if (!$fortbildung_id) {
            dgptm_log_error('Complete Webinar - Failed to create Fortbildung entry', 'vimeo-webinare');
            wp_send_json_error(['message' => 'Fehler beim Erstellen des Fortbildungseintrags']);
        }

        dgptm_log_verbose('Complete Webinar - Fortbildung created: ' . $fortbildung_id, 'vimeo-webinare');

        // Generate certificate
        dgptm_log_verbose('Complete Webinar - Generating certificate...', 'vimeo-webinare');
        $pdf_url = $this->generate_certificate_pdf($user_id, $webinar_id);

        if ($pdf_url) {
            dgptm_log_verbose('Complete Webinar - Certificate generated: ' . $pdf_url, 'vimeo-webinare');

            // Send email with certificate
            dgptm_log_verbose('Complete Webinar - Sending email...', 'vimeo-webinare');
            $mail_sent = $this->send_certificate_email($user_id, $webinar_id, $pdf_url);
            dgptm_log_verbose('Complete Webinar - Email sent: ' . ($mail_sent ? 'Yes' : 'No'), 'vimeo-webinare');
        } else {
            dgptm_log_error('Complete Webinar - Failed to generate certificate', 'vimeo-webinare');
        }

        dgptm_log_info('Complete Webinar - SUCCESS!', 'vimeo-webinare');

        $points = get_field('ebcp_points', $webinar_id) ?: 1;

        wp_send_json_success([
            'message' => 'Webinar erfolgreich abgeschlossen!',
            'fortbildung_id' => $fortbildung_id,
            'certificate_url' => $pdf_url,
            'points' => $points,
        ]);
    }

    /**
     * AJAX: Generate Certificate
     */
    public function ajax_generate_certificate() {
        check_ajax_referer('vw_nonce', 'nonce');

        $user_id = get_current_user_id();
        $webinar_id = intval($_POST['webinar_id'] ?? 0);

        if (!$user_id || !$webinar_id) {
            wp_send_json_error(['message' => 'Invalid data']);
        }

        // Check if completed
        if (!$this->is_webinar_completed($user_id, $webinar_id)) {
            wp_send_json_error(['message' => 'Webinar noch nicht abgeschlossen']);
        }

        // Generate PDF
        $pdf_url = $this->generate_certificate_pdf($user_id, $webinar_id);

        if (!$pdf_url) {
            wp_send_json_error(['message' => 'Fehler beim Generieren des Zertifikats']);
        }

        wp_send_json_success(['pdf_url' => $pdf_url]);
    }

    /**
     * AJAX: Preview Certificate (Admin)
     */
    public function ajax_preview_certificate() {
        check_ajax_referer('vw_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        $user_id = get_current_user_id();
        
        // Erstelle ein Dummy-Webinar für die Vorschau
        $pdf_url = $this->generate_certificate_preview($user_id);

        if (!$pdf_url) {
            wp_send_json_error(['message' => 'Fehler beim Generieren der Vorschau']);
        }

        wp_send_json_success(['pdf_url' => $pdf_url]);
    }

    /**
     * Helper: Get User Progress (Prozent basierend auf angesehener Zeit)
     * Nutzt zuerst die DB-Tabelle, dann Fallback auf User-Meta
     */
    public function get_user_progress($user_id, $webinar_id) {
        global $wpdb;
        
        if (!$user_id) {
            return 0;
        }
        
        // Versuche zuerst aus der Tabelle zu lesen
        $table = $wpdb->prefix . 'vw_progress';
        if ($this->table_exists($table)) {
            $progress = $wpdb->get_var($wpdb->prepare(
                "SELECT progress FROM $table WHERE user_id = %d AND webinar_id = %d",
                $user_id, $webinar_id
            ));
            if ($progress !== null) {
                return floatval($progress);
            }
        }
        
        // Fallback: aus watched_time berechnen
        $watched_time = $this->get_watched_time($user_id, $webinar_id);
        $total_duration = $this->get_video_duration($webinar_id);

        if ($total_duration > 0) {
            return min(100, ($watched_time / $total_duration) * 100);
        }

        return 0;
    }

    /**
     * Helper: Get watched time in seconds
     * Nutzt zuerst die DB-Tabelle, dann Fallback auf User-Meta
     */
    public function get_watched_time($user_id, $webinar_id) {
        global $wpdb;
        
        if (!$user_id) {
            return 0;
        }

        // Versuche zuerst aus der Tabelle zu lesen
        $table = $wpdb->prefix . 'vw_progress';
        if ($this->table_exists($table)) {
            $watched = $wpdb->get_var($wpdb->prepare(
                "SELECT watched_time FROM $table WHERE user_id = %d AND webinar_id = %d",
                $user_id, $webinar_id
            ));
            if ($watched !== null) {
                return floatval($watched);
            }
        }
        
        // Fallback: User-Meta
        $meta_key = '_vw_watched_time_' . $webinar_id;
        $watched_time = get_user_meta($user_id, $meta_key, true);
        return floatval($watched_time);
    }

    /**
     * Helper: Save user progress to DB table
     */
    public function save_user_progress($user_id, $webinar_id, $watched_time, $progress) {
        global $wpdb;
        
        if (!$user_id) {
            return false;
        }
        
        $table = $wpdb->prefix . 'vw_progress';
        
        // Prüfe ob Tabelle existiert
        if (!$this->table_exists($table)) {
            // Fallback: User-Meta verwenden
            update_user_meta($user_id, '_vw_watched_time_' . $webinar_id, $watched_time);
            update_user_meta($user_id, '_vw_progress_' . $webinar_id, $progress);
            update_user_meta($user_id, '_vw_last_access_' . $webinar_id, current_time('mysql'));
            return true;
        }
        
        // INSERT oder UPDATE
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table WHERE user_id = %d AND webinar_id = %d",
            $user_id, $webinar_id
        ));
        
        if ($exists) {
            return $wpdb->update(
                $table,
                [
                    'watched_time' => $watched_time,
                    'progress' => $progress,
                    'last_access' => current_time('mysql'),
                ],
                ['user_id' => $user_id, 'webinar_id' => $webinar_id],
                ['%f', '%f', '%s'],
                ['%d', '%d']
            );
        } else {
            return $wpdb->insert(
                $table,
                [
                    'user_id' => $user_id,
                    'webinar_id' => $webinar_id,
                    'watched_time' => $watched_time,
                    'progress' => $progress,
                    'last_access' => current_time('mysql'),
                    'created_at' => current_time('mysql'),
                ],
                ['%d', '%d', '%f', '%f', '%s', '%s']
            );
        }
    }

    /**
     * Helper: Mark webinar as completed in DB
     */
    public function mark_webinar_completed($user_id, $webinar_id, $fortbildung_id = null) {
        global $wpdb;
        
        if (!$user_id) {
            return false;
        }
        
        $table = $wpdb->prefix . 'vw_progress';
        
        if ($this->table_exists($table)) {
            // Update oder Insert in Tabelle
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table WHERE user_id = %d AND webinar_id = %d",
                $user_id, $webinar_id
            ));
            
            if ($exists) {
                $wpdb->update(
                    $table,
                    [
                        'completed' => 1,
                        'completed_date' => current_time('mysql'),
                        'fortbildung_id' => $fortbildung_id,
                        'progress' => 100,
                    ],
                    ['user_id' => $user_id, 'webinar_id' => $webinar_id],
                    ['%d', '%s', '%d', '%f'],
                    ['%d', '%d']
                );
            } else {
                $wpdb->insert(
                    $table,
                    [
                        'user_id' => $user_id,
                        'webinar_id' => $webinar_id,
                        'completed' => 1,
                        'completed_date' => current_time('mysql'),
                        'fortbildung_id' => $fortbildung_id,
                        'progress' => 100,
                        'created_at' => current_time('mysql'),
                    ],
                    ['%d', '%d', '%d', '%s', '%d', '%f', '%s']
                );
            }
        }
        
        // Auch in User-Meta speichern (für Kompatibilität)
        update_user_meta($user_id, '_vw_completed_' . $webinar_id, true);
        update_user_meta($user_id, '_vw_completed_' . $webinar_id . '_date', current_time('mysql'));
        if ($fortbildung_id) {
            update_user_meta($user_id, '_vw_fortbildung_' . $webinar_id, $fortbildung_id);
        }
        
        return true;
    }

    /**
     * Helper: Check if table exists
     */
    private function table_exists($table_name) {
        global $wpdb;
        static $cache = [];
        
        if (!isset($cache[$table_name])) {
            $cache[$table_name] = $wpdb->get_var(
                $wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
            ) === $table_name;
        }
        
        return $cache[$table_name];
    }

    /**
     * Helper: Get video duration (cached in post meta)
     */
    public function get_video_duration($webinar_id) {
        $duration = get_post_meta($webinar_id, '_vw_video_duration', true);
        return floatval($duration);
    }

    /**
     * Helper: Get cookie data for non-logged-in users
     */
    private function get_cookie_data($webinar_id) {
        $cookie_name = 'vw_webinar_' . $webinar_id;

        if (isset($_COOKIE[$cookie_name])) {
            $data = json_decode(stripslashes($_COOKIE[$cookie_name]), true);

            if (is_array($data)) {
                return [
                    'watched_time' => floatval($data['watched_time'] ?? 0),
                    'progress' => floatval($data['progress'] ?? 0),
                ];
            }
        }

        return [
            'watched_time' => 0,
            'progress' => 0,
        ];
    }

    /**
     * Helper: Get all cookie progress data
     */
    public function get_all_cookie_progress() {
        $progress_data = [];

        foreach ($_COOKIE as $name => $value) {
            if (strpos($name, 'vw_webinar_') === 0) {
                $webinar_id = intval(str_replace('vw_webinar_', '', $name));
                if ($webinar_id) {
                    $data = json_decode(stripslashes($value), true);
                    if (is_array($data)) {
                        $progress_data[$webinar_id] = [
                            'watched_time' => floatval($data['watched_time'] ?? 0),
                            'progress' => floatval($data['progress'] ?? 0),
                        ];
                    }
                }
            }
        }

        return $progress_data;
    }

    /**
     * Helper: Check if Webinar Completed
     * Prüft zuerst die DB-Tabelle, dann User-Meta
     */
    public function is_webinar_completed($user_id, $webinar_id) {
        global $wpdb;
        
        if (!$user_id) {
            return false;
        }

        // Versuche zuerst aus der Tabelle zu lesen
        $table = $wpdb->prefix . 'vw_progress';
        if ($this->table_exists($table)) {
            $completed = $wpdb->get_var($wpdb->prepare(
                "SELECT completed FROM $table WHERE user_id = %d AND webinar_id = %d",
                $user_id, $webinar_id
            ));
            if ($completed !== null) {
                return (bool) $completed;
            }
        }
        
        // Fallback: User-Meta
        $meta_key = '_vw_completed_' . $webinar_id;
        return (bool) get_user_meta($user_id, $meta_key, true);
    }

    /**
     * Helper: Get User Webinar History
     * Nutzt zuerst die DB-Tabelle, dann Fallback auf User-Meta
     */
    public function get_user_webinar_history($user_id, $limit = 10) {
        global $wpdb;

        if (!$user_id) {
            return [];
        }

        $history = [];
        $table = $wpdb->prefix . 'vw_progress';
        
        // Versuche zuerst aus der Tabelle zu lesen
        if ($this->table_exists($table)) {
            $results = $wpdb->get_results($wpdb->prepare(
                "SELECT webinar_id, watched_time, progress, completed, last_access 
                 FROM $table 
                 WHERE user_id = %d 
                 ORDER BY last_access DESC 
                 LIMIT %d",
                $user_id, $limit
            ));
            
            foreach ($results as $row) {
                $webinar = get_post($row->webinar_id);
                if ($webinar && $webinar->post_status === 'publish') {
                    $history[] = [
                        'webinar_id' => $row->webinar_id,
                        'title' => $webinar->post_title,
                        'last_access' => $row->last_access,
                        'progress' => floatval($row->progress),
                        'completed' => (bool) $row->completed,
                    ];
                }
            }
            
            if (!empty($history)) {
                return $history;
            }
        }

        // Fallback: User-Meta
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT meta_key, meta_value 
             FROM {$wpdb->usermeta} 
             WHERE user_id = %d 
             AND meta_key LIKE '_vw_last_access_%%'
             ORDER BY meta_value DESC
             LIMIT %d",
            $user_id,
            $limit
        ));

        foreach ($results as $row) {
            $webinar_id = intval(str_replace('_vw_last_access_', '', $row->meta_key));
            if ($webinar_id) {
                $webinar = get_post($webinar_id);
                if ($webinar && $webinar->post_status === 'publish') {
                    $history[] = [
                        'webinar_id' => $webinar_id,
                        'title' => $webinar->post_title,
                        'last_access' => $row->meta_value,
                        'progress' => $this->get_user_progress($user_id, $webinar_id),
                        'completed' => $this->is_webinar_completed($user_id, $webinar_id),
                    ];
                }
            }
        }

        return $history;
    }

    /**
     * Helper: Save certificate to DB
     */
    public function save_certificate($user_id, $webinar_id, $certificate_url, $fortbildung_id = null, $points = 0) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'vw_certificates';
        
        if (!$this->table_exists($table)) {
            return false;
        }
        
        return $wpdb->insert(
            $table,
            [
                'user_id' => $user_id,
                'webinar_id' => $webinar_id,
                'fortbildung_id' => $fortbildung_id,
                'certificate_url' => $certificate_url,
                'certificate_hash' => md5($certificate_url),
                'points' => $points,
                'generated_at' => current_time('mysql'),
            ],
            ['%d', '%d', '%d', '%s', '%s', '%f', '%s']
        );
    }

    /**
     * Helper: Create Fortbildung Entry
     */
    private function create_fortbildung_entry($user_id, $webinar_id) {
        dgptm_log_verbose('Create Fortbildung - Start: User ' . $user_id . ', Webinar ' . $webinar_id, 'vimeo-webinare');

        $webinar = get_post($webinar_id);

        if (!$webinar) {
            dgptm_log_error('Create Fortbildung - Webinar not found', 'vimeo-webinare');
            return false;
        }

        dgptm_log_verbose('Create Fortbildung - Webinar found: ' . $webinar->post_title, 'vimeo-webinare');

        // Doubletten-Prüfung: Gibt es bereits einen Eintrag für dieses Webinar?
        $existing = get_posts([
            'post_type' => 'fortbildung',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'user',
                    'value' => $user_id,
                    'compare' => '='
                ],
                [
                    'key' => '_vw_webinar_id',
                    'value' => $webinar_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ]);

        if (!empty($existing)) {
            dgptm_log_info('Create Fortbildung - Existing entry found: ' . $existing[0]->ID, 'vimeo-webinare');
            return $existing[0]->ID;
        }

        dgptm_log_verbose('Create Fortbildung - No existing entry, creating new...', 'vimeo-webinare');

        $points = get_field('ebcp_points', $webinar_id) ?: 1;
        $vnr = get_field('vnr', $webinar_id) ?: '';
        $user = get_userdata($user_id);

        dgptm_log_verbose('Create Fortbildung - Points: ' . $points . ', VNR: ' . $vnr, 'vimeo-webinare');

        // Token generieren für Verifikation
        $token = wp_generate_password(32, false);

        // Fortbildung Post erstellen
        $fortbildung_id = wp_insert_post([
            'post_type' => 'fortbildung',
            'post_title' => $webinar->post_title . ' - ' . $user->display_name,
            'post_status' => 'publish',
            'post_author' => 1, // System-User
        ], true);

        if (is_wp_error($fortbildung_id)) {
            dgptm_log_error('Create Fortbildung - wp_insert_post failed: ' . $fortbildung_id->get_error_message(), 'vimeo-webinare');
            return false;
        }

        if (!$fortbildung_id) {
            dgptm_log_error('Create Fortbildung - wp_insert_post returned 0', 'vimeo-webinare');
            return false;
        }

        dgptm_log_verbose('Create Fortbildung - Post created: ' . $fortbildung_id, 'vimeo-webinare');

        // ACF Felder setzen
        dgptm_log_verbose('Create Fortbildung - Setting ACF fields...', 'vimeo-webinare');
        update_field('user', $user_id, $fortbildung_id);
        update_field('date', current_time('Y-m-d'), $fortbildung_id);
        update_field('location', 'Online', $fortbildung_id);
        update_field('type', 'Webinar', $fortbildung_id);
        update_field('points', $points, $fortbildung_id);
        update_field('vnr', $vnr, $fortbildung_id);
        update_field('token', $token, $fortbildung_id);
        update_field('freigegeben', true, $fortbildung_id);
        update_field('freigabe_durch', 'System (Webinar)', $fortbildung_id);
        update_field('freigabe_mail', get_option('admin_email'), $fortbildung_id);

        dgptm_log_verbose('Create Fortbildung - ACF fields set', 'vimeo-webinare');

        // Webinar-ID als Meta speichern für Doubletten-Check
        update_post_meta($fortbildung_id, '_vw_webinar_id', $webinar_id);

        // Mark as completed (nutzt neue DB-Methode)
        $this->mark_webinar_completed($user_id, $webinar_id, $fortbildung_id);

        dgptm_log_info('Create Fortbildung - Completed. Fortbildung ID: ' . $fortbildung_id, 'vimeo-webinare');

        return $fortbildung_id;
    }

    /**
     * Helper: Generate Certificate PDF
     */
    /**
     * Platzhalter-Mapping fuer Zertifikat-Templates.
     */
    private function get_certificate_placeholders($user_id, $webinar_id): array {
        $user = get_userdata($user_id);
        $webinar = get_post($webinar_id);
        $settings = dgptm_vw_get_certificate_settings();

        $points = get_field('ebcp_points', $webinar_id) ?: 1;
        $vnr    = get_field('vnr', $webinar_id) ?: '';
        $location = get_field('location', $webinar_id) ?: 'Online';
        $fortbildung_type = get_field('fortbildung_type', $webinar_id) ?: 'Webinar';

        $webinar_date_raw = (string) get_field('webinar_date', $webinar_id);
        $webinar_date_display = '';
        if ($webinar_date_raw) {
            $ts = strtotime($webinar_date_raw);
            if ($ts) $webinar_date_display = date_i18n('d.m.Y', $ts);
        }
        $today = current_time('d.m.Y');

        $full_name = trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? ''));
        if ($full_name === '') $full_name = $user->display_name ?? '';

        return [
            '{{user_name}}'        => $full_name,
            '{{user_email}}'       => $user->user_email ?? '',
            '{{webinar_title}}'    => $webinar->post_title ?? '',
            '{{webinar_date}}'     => $webinar_date_display,
            '{{issue_date}}'       => $today,
            '{{date}}'             => $webinar_date_display ?: $today,
            '{{ebcp_points}}'      => number_format_i18n((float) $points, 1),
            '{{vnr}}'              => $vnr,
            '{{location}}'         => $location,
            '{{fortbildung_type}}' => $fortbildung_type,
            '{{site_name}}'        => get_bloginfo('name'),
            '{{header_text}}'      => $settings['header_text']    ?? '',
            '{{footer_text}}'      => $settings['footer_text']    ?? '',
            '{{signature_text}}'   => $settings['signature_text'] ?? '',
        ];
    }

    /**
     * HTML/CSS-Template via Dompdf rendern.
     * Rueckgabe: PDF-URL oder false bei Fehler.
     */
    private function generate_certificate_html_to_pdf($user_id, $webinar_id) {
        $autoload = $this->plugin_path . 'vendor/autoload.php';
        if (!file_exists($autoload)) {
            dgptm_log_error('Certificate (HTML) - Dompdf vendor nicht gefunden: ' . $autoload, 'vimeo-webinare');
            return false;
        }
        require_once $autoload;
        if (!class_exists('Dompdf\\Dompdf')) {
            dgptm_log_error('Certificate (HTML) - Dompdf-Klasse nicht geladen', 'vimeo-webinare');
            return false;
        }

        $settings = dgptm_vw_get_certificate_settings();
        $template_html = (string) ($settings['template_html'] ?? '');
        if (trim($template_html) === '') return false;
        $template_css = (string) ($settings['template_css'] ?? '');
        $orientation = $settings['orientation'] ?? 'L';

        $replacements = $this->get_certificate_placeholders($user_id, $webinar_id);
        $rendered = str_replace(array_keys($replacements), array_values($replacements), $template_html);

        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
              . $template_css
              . '</style></head><body>' . $rendered . '</body></html>';

        try {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->setPaper('A4', ($orientation === 'L') ? 'landscape' : 'portrait');
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->render();

            $upload_dir = wp_upload_dir();
            $pdf_dir = $upload_dir['basedir'] . '/webinar-certificates/';
            if (!file_exists($pdf_dir)) wp_mkdir_p($pdf_dir);
            $filename = 'zertifikat-' . $webinar_id . '-' . $user_id . '-' . time() . '.pdf';
            $pdf_path = $pdf_dir . $filename;

            $bytes = @file_put_contents($pdf_path, $dompdf->output());
            if ($bytes === false) {
                dgptm_log_error('Certificate (HTML) - PDF konnte nicht geschrieben werden: ' . $pdf_path, 'vimeo-webinare');
                return false;
            }

            return $upload_dir['baseurl'] . '/webinar-certificates/' . $filename;
        } catch (\Throwable $e) {
            dgptm_log_error('Certificate (HTML) - Dompdf-Fehler: ' . $e->getMessage(), 'vimeo-webinare');
            return false;
        }
    }

    /**
     * Vordefinierte Starter-Templates.
     */
    public function get_certificate_presets(): array {
        return DGPTM_VW_Certificate_Presets::get_all();
    }

    /**
     * Live-Vorschau: rendert HTML/CSS aus POST mit Beispieldaten als PDF
     * und streamt es inline in den Browser. Wird vom "PDF-Vorschau"-Button
     * im Zertifikat-Designer aufgerufen (admin-post.php).
     */
    public function handle_preview_certificate_pdf() {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.');
        }
        check_admin_referer('vw_certificate_nonce', 'vw_certificate_nonce');

        $template_html = wp_unslash($_POST['template_html'] ?? '');
        $template_css  = wp_unslash($_POST['template_css']  ?? '');
        if (trim($template_html) === '') {
            wp_die('Kein HTML-Template vorhanden. Bitte HTML eingeben oder ein Preset laden.');
        }

        $autoload = $this->plugin_path . 'vendor/autoload.php';
        if (!file_exists($autoload)) {
            wp_die('Dompdf-Vendor-Verzeichnis nicht gefunden. PDF-Vorschau ist erst nach dem nächsten Deploy verfügbar.');
        }
        require_once $autoload;
        if (!class_exists('Dompdf\\Dompdf')) {
            wp_die('Dompdf-Klasse nicht geladen.');
        }

        $settings = dgptm_vw_get_certificate_settings();
        $orientation = $settings['orientation'] ?? 'L';

        $replacements = [
            '{{user_name}}'        => 'Max Mustermann',
            '{{user_email}}'       => 'max.mustermann@example.com',
            '{{webinar_title}}'    => 'Erweiterte Perfusiologie in der extrakorporalen Zirkulation',
            '{{webinar_date}}'     => '15.04.2026',
            '{{issue_date}}'       => current_time('d.m.Y'),
            '{{date}}'             => '15.04.2026',
            '{{ebcp_points}}'      => '1,0',
            '{{vnr}}'              => 'VNR-2026-042',
            '{{location}}'         => 'Online',
            '{{fortbildung_type}}' => 'Webinar',
            '{{site_name}}'        => get_bloginfo('name'),
            '{{header_text}}'      => $settings['header_text']    ?? 'Teilnahmebescheinigung',
            '{{footer_text}}'      => $settings['footer_text']    ?? get_bloginfo('name'),
            '{{signature_text}}'   => $settings['signature_text'] ?? 'Präsidium',
        ];
        $rendered = str_replace(array_keys($replacements), array_values($replacements), $template_html);
        $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"><style>'
              . $template_css
              . '</style></head><body>' . $rendered . '</body></html>';

        try {
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', true);
            $options->set('isHtml5ParserEnabled', true);
            $options->set('defaultFont', 'DejaVu Sans');

            $dompdf = new \Dompdf\Dompdf($options);
            $dompdf->setPaper('A4', ($orientation === 'L') ? 'landscape' : 'portrait');
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->render();

            while (ob_get_level() > 0) ob_end_clean();
            $dompdf->stream('zertifikat-vorschau.pdf', ['Attachment' => false]);
            exit;
        } catch (\Throwable $e) {
            wp_die('Dompdf-Fehler bei der Vorschau: ' . esc_html($e->getMessage()));
        }
    }

    private function generate_certificate_pdf($user_id, $webinar_id) {
        // Neuer Pfad: HTML/CSS-Template via Dompdf, sofern vorhanden.
        $settings_early = dgptm_vw_get_certificate_settings();
        if (trim((string) ($settings_early['template_html'] ?? '')) !== '') {
            $html_url = $this->generate_certificate_html_to_pdf($user_id, $webinar_id);
            if ($html_url !== false) return $html_url;
            dgptm_log_warning('Certificate - HTML-Rendering fehlgeschlagen, Fallback auf FPDF', 'vimeo-webinare');
        }
        // Fallback: klassisches FPDF-Rendering
        // Load FPDF
        $fpdf_path = defined('DGPTM_SUITE_PATH') ? DGPTM_SUITE_PATH . 'libraries/fpdf/fpdf.php' : '';

        // Fallback-Pfade
        if (!file_exists($fpdf_path)) {
            $fpdf_path = WP_PLUGIN_DIR . '/dgptm-suite/libraries/fpdf/fpdf.php';
        }
        if (!file_exists($fpdf_path)) {
            $fpdf_path = $this->plugin_path . 'libraries/fpdf/fpdf.php';
        }

        if (!file_exists($fpdf_path)) {
            dgptm_log_error('Certificate - FPDF not found at: ' . $fpdf_path, 'vimeo-webinare');
            return false;
        }

        require_once $fpdf_path;

        $user = get_userdata($user_id);
        $webinar = get_post($webinar_id);
        $points = get_field('ebcp_points', $webinar_id) ?: 1;
        $vnr = get_field('vnr', $webinar_id) ?: '';
        $date = current_time('d.m.Y');

        // Get global certificate settings
        $settings = dgptm_vw_get_certificate_settings();
        $orientation = $settings['orientation'] ?? 'L';
        $bg_id = $settings['background_image'] ?? 0;
        $logo_id = $settings['logo_image'] ?? 0;
        $watermark_id = $settings['watermark_image'] ?? 0;
        $watermark_opacity = intval($settings['watermark_opacity'] ?? 30);
        $watermark_position = $settings['watermark_position'] ?? 'center';
        $header_text = $settings['header_text'] ?? 'Teilnahmebescheinigung';
        $footer_text = $settings['footer_text'] ?? get_bloginfo('name');
        $signature_text = $settings['signature_text'] ?? '';

        // Webinar-spezifisches Wasserzeichen (überschreibt global)
        $webinar_watermark = get_field('certificate_watermark', $webinar_id);
        if ($webinar_watermark && isset($webinar_watermark['ID'])) {
            $watermark_id = $webinar_watermark['ID'];
        }

        // Create PDF
        $pdf = new FPDF($orientation, 'mm', 'A4');
        $pdf->AddPage();

        $width = $orientation === 'L' ? 297 : 210;
        $height = $orientation === 'L' ? 210 : 297;

        // Background image
        if ($bg_id) {
            $bg_path = get_attached_file($bg_id);
            if ($bg_path && file_exists($bg_path)) {
                $pdf->Image($bg_path, 0, 0, $width, $height);
            }
        }

        // Watermark (UNTER dem Text, aber über dem Hintergrund)
        if ($watermark_id) {
            $wm_path = get_attached_file($watermark_id);
            if ($wm_path && file_exists($wm_path)) {
                // Wasserzeichen-Position berechnen
                $wm_info = getimagesize($wm_path);
                if ($wm_info) {
                    $wm_width = min(150, $width * 0.6);
                    $wm_ratio = $wm_info[1] / $wm_info[0];
                    $wm_height = $wm_width * $wm_ratio;

                    switch ($watermark_position) {
                        case 'top-left':
                            $wm_x = 20;
                            $wm_y = 20;
                            break;
                        case 'top-right':
                            $wm_x = $width - $wm_width - 20;
                            $wm_y = 20;
                            break;
                        case 'bottom-left':
                            $wm_x = 20;
                            $wm_y = $height - $wm_height - 20;
                            break;
                        case 'bottom-right':
                            $wm_x = $width - $wm_width - 20;
                            $wm_y = $height - $wm_height - 20;
                            break;
                        case 'center':
                        default:
                            $wm_x = ($width - $wm_width) / 2;
                            $wm_y = ($height - $wm_height) / 2;
                            break;
                    }

                    $pdf->Image($wm_path, $wm_x, $wm_y, $wm_width);
                }
            }
        }

        // Logo
        if ($logo_id) {
            $logo_path = get_attached_file($logo_id);
            if ($logo_path && file_exists($logo_path)) {
                $pdf->Image($logo_path, 15, 15, 40);
            }
        }

        // Header
        $pdf->SetFont('Arial', 'B', 24);
        $pdf->SetY(50);
        $pdf->Cell(0, 10, $this->pdf_text($header_text), 0, 1, 'C');

        // Webinar Title
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->SetY(80);
        $pdf->MultiCell(0, 10, $this->pdf_text($webinar->post_title), 0, 'C');

        // User Name
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetY(110);
        $pdf->Cell(0, 10, $this->pdf_text($user->display_name), 0, 1, 'C');

        // Details
        $pdf->SetFont('Arial', '', 12);
        $pdf->SetY(130);
        $pdf->Cell(0, 8, $this->pdf_text('hat erfolgreich am o.g. Webinar teilgenommen'), 0, 1, 'C');

        // Points
        $pdf->SetY(150);
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, $this->pdf_text('Fortbildungspunkte: ' . $points . ' EBCP'), 0, 1, 'C');

        // VNR
        if ($vnr) {
            $pdf->SetFont('Arial', '', 12);
            $pdf->SetY(165);
            $pdf->Cell(0, 8, $this->pdf_text('VNR: ' . $vnr), 0, 1, 'C');
        }

        // Date
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetY(180);
        $pdf->Cell(0, 8, $this->pdf_text('Datum: ' . $date), 0, 1, 'C');

        // Signature
        if ($signature_text) {
            $pdf->SetY(195);
            $pdf->SetFont('Arial', 'I', 10);
            $pdf->Cell(0, 5, $this->pdf_text($signature_text), 0, 1, 'C');
        }

        // Footer
        if ($footer_text) {
            $pdf->SetY(-15);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 10, $this->pdf_text($footer_text), 0, 0, 'C');
        }

        // Save PDF
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/webinar-certificates/';

        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }

        $filename = 'certificate-' . $user_id . '-' . $webinar_id . '-' . time() . '.pdf';
        $pdf_path = $pdf_dir . $filename;
        $pdf->Output('F', $pdf_path);

        // Return URL
        $pdf_url = $upload_dir['baseurl'] . '/webinar-certificates/' . $filename;
        return $pdf_url;
    }

    /**
     * Helper: Generate Certificate Preview (Admin)
     */
    private function generate_certificate_preview($user_id) {
        // Load FPDF
        $fpdf_path = defined('DGPTM_SUITE_PATH') ? DGPTM_SUITE_PATH . 'libraries/fpdf/fpdf.php' : '';

        if (!file_exists($fpdf_path)) {
            $fpdf_path = WP_PLUGIN_DIR . '/dgptm-suite/libraries/fpdf/fpdf.php';
        }
        if (!file_exists($fpdf_path)) {
            $fpdf_path = $this->plugin_path . 'libraries/fpdf/fpdf.php';
        }

        if (!file_exists($fpdf_path)) {
            return false;
        }

        require_once $fpdf_path;

        $user = get_userdata($user_id);
        $date = current_time('d.m.Y');

        // Get settings
        $settings = dgptm_vw_get_certificate_settings();
        $orientation = $settings['orientation'] ?? 'L';
        $bg_id = $settings['background_image'] ?? 0;
        $logo_id = $settings['logo_image'] ?? 0;
        $watermark_id = $settings['watermark_image'] ?? 0;
        $watermark_position = $settings['watermark_position'] ?? 'center';
        $header_text = $settings['header_text'] ?? 'Teilnahmebescheinigung';
        $footer_text = $settings['footer_text'] ?? get_bloginfo('name');
        $signature_text = $settings['signature_text'] ?? '';

        $pdf = new FPDF($orientation, 'mm', 'A4');
        $pdf->AddPage();

        $width = $orientation === 'L' ? 297 : 210;
        $height = $orientation === 'L' ? 210 : 297;

        // Background
        if ($bg_id) {
            $bg_path = get_attached_file($bg_id);
            if ($bg_path && file_exists($bg_path)) {
                $pdf->Image($bg_path, 0, 0, $width, $height);
            }
        }

        // Watermark
        if ($watermark_id) {
            $wm_path = get_attached_file($watermark_id);
            if ($wm_path && file_exists($wm_path)) {
                $wm_info = getimagesize($wm_path);
                if ($wm_info) {
                    $wm_width = min(150, $width * 0.6);
                    $wm_ratio = $wm_info[1] / $wm_info[0];
                    $wm_height = $wm_width * $wm_ratio;

                    switch ($watermark_position) {
                        case 'top-left':
                            $wm_x = 20; $wm_y = 20;
                            break;
                        case 'top-right':
                            $wm_x = $width - $wm_width - 20; $wm_y = 20;
                            break;
                        case 'bottom-left':
                            $wm_x = 20; $wm_y = $height - $wm_height - 20;
                            break;
                        case 'bottom-right':
                            $wm_x = $width - $wm_width - 20; $wm_y = $height - $wm_height - 20;
                            break;
                        default:
                            $wm_x = ($width - $wm_width) / 2; $wm_y = ($height - $wm_height) / 2;
                    }

                    $pdf->Image($wm_path, $wm_x, $wm_y, $wm_width);
                }
            }
        }

        // Logo
        if ($logo_id) {
            $logo_path = get_attached_file($logo_id);
            if ($logo_path && file_exists($logo_path)) {
                $pdf->Image($logo_path, 15, 15, 40);
            }
        }

        // Header
        $pdf->SetFont('Arial', 'B', 24);
        $pdf->SetY(50);
        $pdf->Cell(0, 10, $this->pdf_text($header_text), 0, 1, 'C');

        // Preview Title
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->SetY(80);
        $pdf->MultiCell(0, 10, $this->pdf_text('[ Beispiel-Webinar Titel ]'), 0, 'C');

        // User Name
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetY(110);
        $pdf->Cell(0, 10, $this->pdf_text($user->display_name), 0, 1, 'C');

        // Details
        $pdf->SetFont('Arial', '', 12);
        $pdf->SetY(130);
        $pdf->Cell(0, 8, $this->pdf_text('hat erfolgreich am o.g. Webinar teilgenommen'), 0, 1, 'C');

        // Points
        $pdf->SetY(150);
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, $this->pdf_text('Fortbildungspunkte: 2 EBCP'), 0, 1, 'C');

        // Date
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetY(180);
        $pdf->Cell(0, 8, $this->pdf_text('Datum: ' . $date), 0, 1, 'C');

        // Signature
        if ($signature_text) {
            $pdf->SetY(195);
            $pdf->SetFont('Arial', 'I', 10);
            $pdf->Cell(0, 5, $this->pdf_text($signature_text), 0, 1, 'C');
        }

        // Footer
        if ($footer_text) {
            $pdf->SetY(-15);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 10, $this->pdf_text($footer_text), 0, 0, 'C');
        }

        // Preview watermark
        $pdf->SetTextColor(200, 200, 200);
        $pdf->SetFont('Arial', 'B', 60);
        $pdf->SetXY(30, 100);
        $pdf->Cell(0, 10, 'VORSCHAU', 0, 0, 'C');

        // Save
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/webinar-certificates/';

        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }

        $filename = 'preview-' . $user_id . '-' . time() . '.pdf';
        $pdf_path = $pdf_dir . $filename;
        $pdf->Output('F', $pdf_path);

        return $upload_dir['baseurl'] . '/webinar-certificates/' . $filename;
    }

    /**
     * Helper: Convert text for FPDF (UTF-8 to ISO-8859-1)
     */
    private function pdf_text($text) {
        if (function_exists('iconv')) {
            return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
        }
        return utf8_decode($text);
    }

    /**
     * Helper: Send Certificate Email
     */
    private function send_certificate_email($user_id, $webinar_id, $pdf_url) {
        $user = get_userdata($user_id);
        $webinar = get_post($webinar_id);

        if (!$user || !$webinar) {
            return false;
        }

        // Get mail settings
        $settings = dgptm_vw_get_certificate_settings();
        $mail_enabled = $settings['mail_enabled'] ?? true;
        $mail_subject = $settings['mail_subject'] ?? 'Ihr Webinar-Zertifikat: {webinar_title}';
        $mail_body = $settings['mail_body'] ?? "Hallo {user_name},\n\nvielen Dank für Ihre Teilnahme am Webinar \"{webinar_title}\".\n\nIhr Teilnahmezertifikat steht zum Download bereit:\n{certificate_url}\n\nMit freundlichen Grüßen\nIhr Webinar-Team";
        $mail_from = $settings['mail_from'] ?? get_option('admin_email');
        $mail_from_name = $settings['mail_from_name'] ?? get_bloginfo('name');

        if (!$mail_enabled) {
            return false;
        }

        $points = get_field('ebcp_points', $webinar_id) ?: 1;

        // Replace placeholders
        $replacements = [
            '{user_name}' => $user->display_name,
            '{user_first_name}' => $user->first_name,
            '{user_last_name}' => $user->last_name,
            '{user_email}' => $user->user_email,
            '{webinar_title}' => $webinar->post_title,
            '{webinar_url}' => home_url('/wissen/webinar/' . $webinar_id),
            '{certificate_url}' => $pdf_url,
            '{points}' => $points,
            '{date}' => current_time('d.m.Y'),
        ];

        $subject = str_replace(array_keys($replacements), array_values($replacements), $mail_subject);
        $message = str_replace(array_keys($replacements), array_values($replacements), $mail_body);

        // Set headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $mail_from_name . ' <' . $mail_from . '>'
        ];

        // Convert line breaks to HTML
        $message = nl2br($message);

        // Send email
        return wp_mail($user->user_email, $subject, $message, $headers);
    }

    /**
     * Helper: Get Webinar URL
     */
    public function get_webinar_url($webinar_id) {
        return home_url('/wissen/webinar/' . $webinar_id);
    }

    /**
     * Add Admin Menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=vimeo_webinar',
            'Vimeo Batch Import',
            'Batch Import',
            'manage_options',
            'vimeo-webinar-import',
            [$this, 'render_admin_import']
        );

        add_submenu_page(
            'edit.php?post_type=vimeo_webinar',
            'Webinar Einstellungen',
            'Einstellungen',
            'manage_options',
            'vimeo-webinar-settings',
            [$this, 'render_admin_settings']
        );

        add_submenu_page(
            'edit.php?post_type=vimeo_webinar',
            'Zertifikat Designer',
            'Zertifikat Designer',
            'manage_options',
            'vimeo-webinar-certificate',
            [$this, 'render_certificate_designer']
        );

        add_submenu_page(
            'edit.php?post_type=vimeo_webinar',
            'Webinar Statistiken',
            'Statistiken',
            'manage_options',
            'vimeo-webinar-stats',
            [$this, 'render_admin_stats']
        );
    }

    /**
     * Render Admin Settings Page
     */
    public function render_admin_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung');
        }

        // Save settings
        if (isset($_POST['vw_save_settings']) && check_admin_referer('vw_settings_nonce', 'vw_settings_nonce')) {
            $settings = [
                'orientation' => sanitize_text_field($_POST['orientation'] ?? 'L'),
                'background_image' => intval($_POST['background_image'] ?? 0),
                'logo_image' => intval($_POST['logo_image'] ?? 0),
                'watermark_image' => intval($_POST['watermark_image'] ?? 0),
                'watermark_opacity' => intval($_POST['watermark_opacity'] ?? 30),
                'watermark_position' => sanitize_text_field($_POST['watermark_position'] ?? 'center'),
                'header_text' => sanitize_text_field($_POST['header_text'] ?? 'Teilnahmebescheinigung'),
                'footer_text' => sanitize_text_field($_POST['footer_text'] ?? get_bloginfo('name')),
                'signature_text' => sanitize_text_field($_POST['signature_text'] ?? ''),
                // Mail settings
                'mail_enabled' => isset($_POST['mail_enabled']),
                'mail_subject' => sanitize_text_field($_POST['mail_subject'] ?? 'Ihr Webinar-Zertifikat: {webinar_title}'),
                'mail_body' => wp_kses_post($_POST['mail_body'] ?? ''),
                'mail_from' => sanitize_email($_POST['mail_from'] ?? get_option('admin_email')),
                'mail_from_name' => sanitize_text_field($_POST['mail_from_name'] ?? get_bloginfo('name')),
            ];

            update_option('vw_certificate_settings', $settings);
            
            // Webinar-Seite Option separat speichern
            $webinar_page_id = intval($_POST['webinar_page_id'] ?? 0);
            $old_page_id = dgptm_vw_get_setting('vw_webinar_page_id', 0);
            update_option('vw_webinar_page_id', $webinar_page_id);
            
            // Rewrite Rules flushen wenn Seite geändert wurde
            if ($webinar_page_id !== $old_page_id) {
                flush_rewrite_rules();
            }
            
            echo '<div class="notice notice-success"><p>Einstellungen gespeichert!</p></div>';
        }

        $settings = dgptm_vw_get_certificate_settings();
        include $this->plugin_path . 'templates/admin-settings.php';
    }

    /**
     * Render Certificate Designer Page
     */
    public function render_certificate_designer() {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung');
        }

        // Save settings
        if (isset($_POST['vw_save_certificate']) && check_admin_referer('vw_certificate_nonce', 'vw_certificate_nonce')) {
            $settings = dgptm_vw_get_certificate_settings();

            $settings['orientation'] = sanitize_text_field($_POST['orientation'] ?? 'L');
            $settings['background_image'] = intval($_POST['background_image'] ?? 0);
            $settings['logo_image'] = intval($_POST['logo_image'] ?? 0);
            $settings['watermark_image'] = intval($_POST['watermark_image'] ?? 0);
            $settings['watermark_opacity'] = intval($_POST['watermark_opacity'] ?? 30);
            $settings['watermark_position'] = sanitize_text_field($_POST['watermark_position'] ?? 'center');
            $settings['header_text'] = sanitize_text_field($_POST['header_text'] ?? 'Teilnahmebescheinigung');
            $settings['footer_text'] = sanitize_text_field($_POST['footer_text'] ?? get_bloginfo('name'));
            $settings['signature_text'] = sanitize_text_field($_POST['signature_text'] ?? '');

            update_option('vw_certificate_settings', $settings);
            echo '<div class="notice notice-success"><p>Zertifikat-Einstellungen gespeichert!</p></div>';
        }

        // Save HTML/CSS-Template
        if (isset($_POST['vw_save_template']) && check_admin_referer('vw_certificate_nonce', 'vw_certificate_nonce')) {
            $settings = dgptm_vw_get_certificate_settings();
            $settings['template_html'] = wp_unslash($_POST['template_html'] ?? '');
            $settings['template_css']  = wp_unslash($_POST['template_css']  ?? '');
            update_option('vw_certificate_settings', $settings);
            echo '<div class="notice notice-success"><p>HTML/CSS-Template gespeichert.</p></div>';
        }

        // Import JSON (mit optionalem Nachladen externer Bilder)
        $import_msg = '';
        if (isset($_POST['vw_import_certificate']) && check_admin_referer('vw_certificate_nonce', 'vw_certificate_nonce')) {
            $json_raw = wp_unslash($_POST['vw_import_json'] ?? '');

            // File-Upload hat Vorrang vor Textarea, wenn vorhanden
            if (!empty($_FILES['vw_import_file']['tmp_name']) && is_uploaded_file($_FILES['vw_import_file']['tmp_name'])) {
                $file_content = @file_get_contents($_FILES['vw_import_file']['tmp_name']);
                if ($file_content !== false) $json_raw = $file_content;
            }

            $result = $this->import_certificate_from_json($json_raw);
            if (is_wp_error($result)) {
                echo '<div class="notice notice-error"><p><strong>Import fehlgeschlagen:</strong> ' . esc_html($result->get_error_message()) . '</p></div>';
            } else {
                $detail = !empty($result['sideloaded']) ? ' (' . count($result['sideloaded']) . ' Bild(er) aus URLs nachgeladen)' : '';
                echo '<div class="notice notice-success"><p>Zertifikat-Konfiguration importiert' . esc_html($detail) . '.</p></div>';
            }
        }

        $settings = dgptm_vw_get_certificate_settings();
        $export_json = $this->export_certificate_as_json($settings);

        wp_localize_script('vw-admin-script', 'vwCertData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vw_nonce'),
        ]);

        include $this->plugin_path . 'templates/admin-certificate.php';
    }

    /**
     * Serialisiert die aktuellen Zertifikat-Einstellungen als JSON.
     * Claude-freundliche Struktur: beschreibende Keys, Images mit id/url/filename.
     */
    public function export_certificate_as_json(array $settings): string {
        $bg  = $this->image_to_export_obj($settings['background_image'] ?? 0);
        $lg  = $this->image_to_export_obj($settings['logo_image'] ?? 0);
        $wm  = $this->image_to_export_obj($settings['watermark_image'] ?? 0);

        $data = [
            '$schema'     => 'dgptm-vimeo-webinare-certificate',
            'version'     => 1,
            'exported_at' => gmdate('c'),
            'site_url'    => home_url(),
            'orientation' => $settings['orientation'] ?? 'L',
            'texts' => [
                'header'    => $settings['header_text'] ?? '',
                'footer'    => $settings['footer_text'] ?? '',
                'signature' => $settings['signature_text'] ?? '',
            ],
            'mail' => [
                'subject' => $settings['mail_subject'] ?? '',
                'body'    => $settings['mail_body'] ?? '',
                'from'    => $settings['mail_from'] ?? '',
            ],
            'images' => [
                'background' => $bg,
                'logo'       => $lg,
                'watermark'  => array_merge($wm, [
                    'opacity'  => (int) ($settings['watermark_opacity'] ?? 30),
                    'position' => $settings['watermark_position'] ?? 'center',
                ]),
            ],
            'template' => [
                'html' => (string) ($settings['template_html'] ?? ''),
                'css'  => (string) ($settings['template_css']  ?? ''),
            ],
        ];

        return wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    private function image_to_export_obj($id) {
        $id = (int) $id;
        if ($id <= 0) return ['id' => 0, 'url' => '', 'filename' => ''];
        $url = wp_get_attachment_url($id);
        if (!$url) return ['id' => 0, 'url' => '', 'filename' => ''];
        $path = get_attached_file($id);
        return [
            'id'       => $id,
            'url'      => $url,
            'filename' => $path ? basename($path) : '',
        ];
    }

    /**
     * Importiert Zertifikat-Konfiguration aus JSON.
     *
     * Bilder werden so aufgelöst:
     *   1. Wenn "id" angegeben und Attachment existiert → Attachment-ID verwendet.
     *   2. Sonst: wenn "url" angegeben → per media_sideload_image() in Media Library geladen.
     *   3. Sonst: leer (ID 0).
     *
     * @return array|WP_Error ['settings' => array, 'sideloaded' => array<string,int>]
     */
    public function import_certificate_from_json(string $json_raw) {
        $json_raw = trim($json_raw);
        if ($json_raw === '') return new WP_Error('empty', 'Kein JSON übergeben.');

        $data = json_decode($json_raw, true);
        if (!is_array($data)) {
            return new WP_Error('invalid_json', 'Kein gültiges JSON: ' . json_last_error_msg());
        }
        if (($data['$schema'] ?? '') !== 'dgptm-vimeo-webinare-certificate') {
            return new WP_Error('wrong_schema', 'Unbekanntes Schema. Erwartet: dgptm-vimeo-webinare-certificate');
        }
        $version = (int) ($data['version'] ?? 0);
        if ($version !== 1) {
            return new WP_Error('unsupported_version', 'Unbekannte Version: ' . $version);
        }

        $sideloaded = [];
        $resolve_image = function ($node, $key) use (&$sideloaded) {
            $id = (int) ($node['id'] ?? 0);
            if ($id > 0 && wp_get_attachment_url($id)) return $id;
            $url = isset($node['url']) ? (string) $node['url'] : '';
            if ($url === '') return 0;
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $new_id = media_sideload_image($url, 0, null, 'id');
            if (is_wp_error($new_id)) return 0;
            $sideloaded[$key] = (int) $new_id;
            return (int) $new_id;
        };

        $images = $data['images'] ?? [];
        $bg_id = $resolve_image($images['background'] ?? [], 'background');
        $lg_id = $resolve_image($images['logo']       ?? [], 'logo');
        $wm_id = $resolve_image($images['watermark']  ?? [], 'watermark');

        $watermark_opacity  = (int) ($images['watermark']['opacity'] ?? 30);
        if ($watermark_opacity < 0 || $watermark_opacity > 100) $watermark_opacity = 30;
        $watermark_position = sanitize_text_field($images['watermark']['position'] ?? 'center');

        $orientation = sanitize_text_field($data['orientation'] ?? 'L');
        if (!in_array($orientation, ['L', 'P'], true)) $orientation = 'L';

        $texts    = is_array($data['texts']    ?? null) ? $data['texts']    : [];
        $mail     = is_array($data['mail']     ?? null) ? $data['mail']     : [];
        $template = is_array($data['template'] ?? null) ? $data['template'] : [];

        $settings = array_merge(
            dgptm_vw_get_certificate_settings() ?: [],
            [
                'orientation'        => $orientation,
                'background_image'   => $bg_id,
                'logo_image'         => $lg_id,
                'watermark_image'    => $wm_id,
                'watermark_opacity'  => $watermark_opacity,
                'watermark_position' => $watermark_position,
                'header_text'        => sanitize_text_field($texts['header']    ?? 'Teilnahmebescheinigung'),
                'footer_text'        => sanitize_text_field($texts['footer']    ?? get_bloginfo('name')),
                'signature_text'     => sanitize_text_field($texts['signature'] ?? ''),
                'mail_subject'       => sanitize_text_field($mail['subject']    ?? 'Ihr Webinar-Zertifikat: {webinar_title}'),
                'mail_body'          => wp_kses_post($mail['body']              ?? ''),
                'mail_from'          => sanitize_email($mail['from']            ?? get_option('admin_email')),
                'template_html'      => (string) ($template['html'] ?? ''),
                'template_css'       => (string) ($template['css']  ?? ''),
            ]
        );

        update_option('vw_certificate_settings', $settings);

        return ['settings' => $settings, 'sideloaded' => $sideloaded];
    }

    /**
     * Render Admin Statistics Page
     */
    public function render_admin_stats() {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung');
        }

        $webinars = get_posts([
            'post_type' => 'vimeo_webinar',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);

        echo '<div class="wrap">';
        echo '<h1>Webinar Statistiken</h1>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Webinar</th><th>Abgeschlossen</th><th>In Bearbeitung</th><th>Gesamt Ansichten</th></tr></thead>';
        echo '<tbody>';

        foreach ($webinars as $webinar) {
            $stats = $this->get_webinar_stats($webinar->ID);
            echo '<tr>';
            echo '<td>' . esc_html($webinar->post_title) . '</td>';
            echo '<td>' . $stats['completed'] . '</td>';
            echo '<td>' . $stats['in_progress'] . '</td>';
            echo '<td>' . $stats['total_views'] . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }

    /**
     * Render Admin Import Page
     */
    public function render_admin_import() {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung');
        }

        // Enqueue admin styles and scripts
        wp_enqueue_style('vw-admin-import', $this->plugin_url . 'assets/css/admin-import.css', [], '1.0.0');
        wp_enqueue_script('vw-admin-import', $this->plugin_url . 'assets/js/admin-import.js', ['jquery'], '1.0.0', true);

        wp_localize_script('vw-admin-import', 'vwImportData', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vw_import_nonce')
        ]);

        include $this->plugin_path . 'templates/admin-import.php';
    }

    /**
     * AJAX: Test Vimeo Connection
     */
    public function ajax_test_vimeo_connection() {
        check_ajax_referer('vw_import_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        $token = sanitize_text_field($_POST['token'] ?? '');

        if (empty($token)) {
            wp_send_json_error(['message' => 'Kein API Token angegeben']);
        }

        // Save token
        update_option('vimeo_webinar_api_token', $token);

        // Test connection
        $api = new DGPTM_Vimeo_API($token);
        $result = $api->test_connection();

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => 'Verbindung erfolgreich!',
            'user' => $result['name'] ?? 'Unbekannt'
        ]);
    }

    /**
     * AJAX: Get Vimeo Folders
     */
    public function ajax_get_vimeo_folders() {
        check_ajax_referer('vw_import_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        $api = new DGPTM_Vimeo_API();
        $folders = $api->get_folders();

        if (is_wp_error($folders)) {
            wp_send_json_error(['message' => $folders->get_error_message()]);
        }

        wp_send_json_success(['folders' => $folders]);
    }

    /**
     * AJAX: Import Folder Videos
     */
    public function ajax_import_folder_videos() {
        check_ajax_referer('vw_import_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        $folder_uri = sanitize_text_field($_POST['folder_uri'] ?? '');
        $category = sanitize_text_field($_POST['category'] ?? '');
        $auto_punkte = isset($_POST['auto_punkte']) && $_POST['auto_punkte'] === 'true';
        $default_punkte = intval($_POST['default_punkte'] ?? 1);

        if (empty($folder_uri)) {
            wp_send_json_error(['message' => 'Kein Ordner ausgewählt']);
        }

        $folder_id = DGPTM_Vimeo_API::extract_folder_id($folder_uri);

        if (!$folder_id) {
            wp_send_json_error(['message' => 'Ungültige Ordner-ID']);
        }

        // Get videos from folder
        $api = new DGPTM_Vimeo_API();
        $videos = $api->get_folder_videos($folder_id);

        if (is_wp_error($videos)) {
            wp_send_json_error(['message' => $videos->get_error_message()]);
        }

        $imported = [];
        $skipped = [];
        $errors = [];

        foreach ($videos as $video) {
            $video_id = DGPTM_Vimeo_API::extract_video_id($video['uri']);

            if (!$video_id) {
                $errors[] = 'Video-ID nicht erkennbar: ' . $video['name'];
                continue;
            }

            // Check if already exists
            $existing = get_posts([
                'post_type' => 'vimeo_webinar',
                'meta_key' => 'vimeo_id',
                'meta_value' => $video_id,
                'posts_per_page' => 1,
                'post_status' => 'any'
            ]);

            if (!empty($existing)) {
                $skipped[] = $video['name'] . ' (bereits vorhanden)';
                continue;
            }

            // Create webinar post
            $post_data = [
                'post_title' => $video['name'],
                'post_content' => $video['description'] ?? '',
                'post_status' => 'draft',
                'post_type' => 'vimeo_webinar'
            ];

            $post_id = wp_insert_post($post_data);

            if (is_wp_error($post_id)) {
                $errors[] = $video['name'] . ': ' . $post_id->get_error_message();
                continue;
            }

            // Set meta fields
            update_post_meta($post_id, 'vimeo_id', $video_id);
            update_post_meta($post_id, 'vimeo_url', $video['link']);
            update_post_meta($post_id, 'duration', intval($video['duration'] ?? 0));

            if (!empty($category)) {
                update_post_meta($post_id, 'kategorie', $category);
            }

            // Set Fortbildungspunkte
            if ($auto_punkte && isset($video['duration'])) {
                $punkte = max(1, ceil($video['duration'] / 3600));
                update_post_meta($post_id, 'fortbildungspunkte', $punkte);
            } else {
                update_post_meta($post_id, 'fortbildungspunkte', $default_punkte);
            }

            // Set thumbnail if available
            if (isset($video['pictures']['sizes'])) {
                $thumbnail_url = end($video['pictures']['sizes'])['link'] ?? '';
                if ($thumbnail_url) {
                    $this->set_post_thumbnail_from_url($post_id, $thumbnail_url);
                }
            }

            $imported[] = $video['name'];
        }

        wp_send_json_success([
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'total' => count($videos)
        ]);
    }

    /**
     * Helper: Set post thumbnail from URL
     */
    private function set_post_thumbnail_from_url($post_id, $image_url) {
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $image_id = media_sideload_image($image_url, $post_id, null, 'id');

        if (!is_wp_error($image_id)) {
            set_post_thumbnail($post_id, $image_id);
        }
    }
}

} // End class check

// Prevent double initialization
if (!isset($GLOBALS['dgptm_vimeo_webinare_initialized'])) {
    $GLOBALS['dgptm_vimeo_webinare_initialized'] = true;
    DGPTM_Vimeo_Webinare::get_instance();
}
