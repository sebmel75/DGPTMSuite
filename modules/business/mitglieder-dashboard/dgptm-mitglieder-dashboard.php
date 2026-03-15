<?php
/**
 * Plugin Name: DGPTM - Mitglieder Dashboard
 * Description: Modernes Mitglieder-Dashboard mit Tab-Navigation, AJAX Lazy-Loading und CRM-Caching
 * Version: 1.0.0
 * Author: Sebastian Melzer
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DGPTM_DASHBOARD_VERSION', '1.0.0');
define('DGPTM_DASHBOARD_PATH', plugin_dir_path(__FILE__));
define('DGPTM_DASHBOARD_URL', plugin_dir_url(__FILE__));

if (!class_exists('DGPTM_Mitglieder_Dashboard')) {

    class DGPTM_Mitglieder_Dashboard {

        private static $instance = null;
        private $config;
        private $permissions;
        private $renderer;
        private $ajax;
        private $crm_cache;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->load_includes();
            $this->init_components();

            add_action('init', [$this, 'register_shortcodes']);
            add_action('admin_menu', [$this, 'register_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        }

        private function load_includes() {
            require_once DGPTM_DASHBOARD_PATH . 'includes/class-dashboard-config.php';
            require_once DGPTM_DASHBOARD_PATH . 'includes/class-dashboard-permissions.php';
            require_once DGPTM_DASHBOARD_PATH . 'includes/class-crm-cache.php';
            require_once DGPTM_DASHBOARD_PATH . 'includes/class-dashboard-renderer.php';
            require_once DGPTM_DASHBOARD_PATH . 'includes/class-dashboard-ajax.php';
        }

        private function init_components() {
            $this->config      = new DGPTM_Dashboard_Config();
            $this->permissions = new DGPTM_Dashboard_Permissions($this->config);
            $this->crm_cache   = new DGPTM_Dashboard_CRM_Cache($this->config);
            $this->renderer    = new DGPTM_Dashboard_Renderer($this->config, $this->permissions, $this->crm_cache);
            $this->ajax        = new DGPTM_Dashboard_Ajax($this->config, $this->permissions, $this->renderer, $this->crm_cache);
        }

        public function register_shortcodes() {
            add_shortcode('dgptm_dashboard', [$this->renderer, 'render_shortcode']);
        }

        public function register_admin_menu() {
            add_submenu_page(
                'dgptm-suite',
                'Dashboard-Einstellungen',
                'Dashboard Config',
                'manage_options',
                'dgptm-dashboard-config',
                [$this, 'render_admin_page']
            );
        }

        public function render_admin_page() {
            if (!current_user_can('manage_options')) {
                wp_die('Keine Berechtigung');
            }
            $config = $this->config;
            $permissions = $this->permissions;
            include DGPTM_DASHBOARD_PATH . 'templates/admin-config.php';
        }

        public function enqueue_admin_assets($hook) {
            if (strpos($hook, 'dgptm-dashboard-config') === false) {
                return;
            }

            wp_enqueue_style(
                'dgptm-dashboard-admin',
                DGPTM_DASHBOARD_URL . 'assets/css/admin.css',
                [],
                DGPTM_DASHBOARD_VERSION
            );

            wp_enqueue_script(
                'dgptm-dashboard-admin',
                DGPTM_DASHBOARD_URL . 'assets/js/admin.js',
                ['jquery'],
                DGPTM_DASHBOARD_VERSION,
                true
            );

            wp_localize_script('dgptm-dashboard-admin', 'dgptmDashboardAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('dgptm_dashboard_nonce'),
            ]);
        }

        public function enqueue_frontend_assets() {
            if (wp_style_is('dgptm-dashboard-frontend', 'enqueued')) {
                return;
            }

            wp_enqueue_style(
                'dgptm-dashboard-frontend',
                DGPTM_DASHBOARD_URL . 'assets/css/dashboard.css',
                [],
                DGPTM_DASHBOARD_VERSION
            );

            wp_enqueue_script(
                'dgptm-dashboard-frontend',
                DGPTM_DASHBOARD_URL . 'assets/js/dashboard.js',
                ['jquery'],
                DGPTM_DASHBOARD_VERSION,
                true
            );

            wp_localize_script('dgptm-dashboard-frontend', 'dgptmDashboard', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('dgptm_dashboard_nonce'),
                'strings' => [
                    'loading'      => 'Wird geladen...',
                    'error'        => 'Fehler beim Laden',
                    'refreshed'    => 'Daten aktualisiert',
                    'unavailable'  => 'Dieses Modul ist derzeit nicht verfuegbar.',
                ],
            ]);
        }

        public function get_config() {
            return $this->config;
        }

        public function get_crm_cache() {
            return $this->crm_cache;
        }
    }
}

if (!isset($GLOBALS['dgptm_mitglieder_dashboard_initialized'])) {
    $GLOBALS['dgptm_mitglieder_dashboard_initialized'] = true;
    DGPTM_Mitglieder_Dashboard::get_instance();
}
