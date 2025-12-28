<?php
/**
 * Plugin Name: DGPTM - Herzzentrum Editor (Unified)
 * Description: Version 4.1.0: CPT-Migration - Herzzentrum CPT wird jetzt nativ registriert (JetEngine nicht mehr erforderlich). Vereinigtes Plugin mit Multi-Map & Single-Map Widgets.
 * Version:     4.1.0
 * Author:      Sebastian Melzer
 * Text Domain: dgptm-herzzentren
 * Domain Path: /languages
 * Requires PHP: 7.4
 * Requires at least: 5.8
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Plugin-Konstanten
define( 'DGPTM_HZ_VERSION', '4.1.0' );
define( 'DGPTM_HZ_FILE', __FILE__ );
define( 'DGPTM_HZ_PATH', plugin_dir_path( __FILE__ ) );
define( 'DGPTM_HZ_URL', plugin_dir_url( __FILE__ ) );

// Includes
require_once DGPTM_HZ_PATH . 'includes/class-post-types.php';
require_once DGPTM_HZ_PATH . 'includes/acf.php';
require_once DGPTM_HZ_PATH . 'includes/admin.php';
require_once DGPTM_HZ_PATH . 'includes/editor.php';
require_once DGPTM_HZ_PATH . 'includes/frontend.php';
require_once DGPTM_HZ_PATH . 'includes/ajax.php';
require_once DGPTM_HZ_PATH . 'includes/ausbildungs-karte.php';
require_once DGPTM_HZ_PATH . 'includes/permissions.php';
require_once DGPTM_HZ_PATH . 'includes/hzl-enqueue.php';
require_once DGPTM_HZ_PATH . 'includes/hzl-generate-output.php';
require_once DGPTM_HZ_PATH . 'includes/hzl-ajax.php';
require_once DGPTM_HZ_PATH . 'includes/hzl-shortcode.php';
require_once DGPTM_HZ_PATH . 'includes/hzl-admin-page.php';
require_once DGPTM_HZ_PATH . 'includes/hzl-textdomain.php';

/**
 * Hauptklasse für Elementor Widgets
 */
class DGPTM_Elementor_Herzzentren {
    
    private static $instance = null;
    
    /**
     * Singleton Instance
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Konstruktor
     */
    private function __construct() {
        add_action( 'plugins_loaded', array( $this, 'init' ) );
        add_action( 'admin_notices', array( $this, 'admin_notices' ) );
    }
    
    /**
     * Initialisierung
     */
    public function init() {
        // Textdomain laden
        load_plugin_textdomain( 'dgptm-herzzentren', false, dirname( plugin_basename( DGPTM_HZ_FILE ) ) . '/languages' );
        
        // Elementor-Integration
        if ( did_action( 'elementor/loaded' ) ) {
            add_action( 'elementor/widgets/register', array( $this, 'register_widgets' ) );
            add_action( 'elementor/frontend/after_register_scripts', array( $this, 'register_frontend_scripts' ) );
            add_action( 'elementor/frontend/after_register_styles', array( $this, 'register_frontend_styles' ) );
        }
    }
    
    /**
     * Widget-Dateien einbinden
     */
    private function include_widgets_files() {
        require_once DGPTM_HZ_PATH . 'widgets/class-herzzentren-map-widget.php';
        require_once DGPTM_HZ_PATH . 'widgets/class-herzzentrum-single-map-widget.php';
    }
    
    /**
     * Widgets registrieren
     */
    public function register_widgets( $widgets_manager ) {
        $this->include_widgets_files();
        
        // Multi-Map Widget registrieren
        if ( class_exists( 'DGPTM_Herzzentren_Map_Widget' ) ) {
            $widgets_manager->register( new DGPTM_Herzzentren_Map_Widget() );
        }
        
        // Single-Map Widget registrieren
        if ( class_exists( 'DGPTM_Herzzentrum_Single_Map_Widget' ) ) {
            $widgets_manager->register( new DGPTM_Herzzentrum_Single_Map_Widget() );
        }
    }
    
    /**
     * Frontend-Scripts registrieren
     */
    public function register_frontend_scripts() {
        wp_register_script(
            'leaflet-js',
            DGPTM_HZ_URL . 'assets/leaflet.js',
            array(),
            '1.9.4',
            true
        );
        
        wp_register_script(
            'dgptm-map-handler',
            DGPTM_HZ_URL . 'assets/js/map-handler.js',
            array( 'leaflet-js' ),
            DGPTM_HZ_VERSION,
            true
        );
        
        // Lokalisierung für JavaScript
        wp_localize_script( 'dgptm-map-handler', 'dgptmMapConfig', array(
            'pluginUrl' => DGPTM_HZ_URL,
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce' => wp_create_nonce( 'dgptm_map_nonce' ),
        ) );
    }
    
    /**
     * Frontend-Styles registrieren
     */
    public function register_frontend_styles() {
        wp_register_style(
            'leaflet-css',
            DGPTM_HZ_URL . 'assets/leaflet.css',
            array(),
            '1.9.4'
        );
        
        wp_register_style(
            'dgptm-map-style',
            DGPTM_HZ_URL . 'assets/css/map-style.css',
            array( 'leaflet-css' ),
            DGPTM_HZ_VERSION
        );
    }
    
    /**
     * Admin-Notices für fehlende Abhängigkeiten
     */
    public function admin_notices() {
        if ( ! did_action( 'elementor/loaded' ) ) {
            echo '<div class="notice notice-warning is-dismissible">';
            echo '<p><strong>' . esc_html__( 'DGPTM Herzzentrum Editor', 'dgptm-herzzentren' ) . ':</strong> ';
            echo esc_html__( 'Dieses Plugin benötigt Elementor. Bitte installieren und aktivieren Sie Elementor.', 'dgptm-herzzentren' );
            echo '</p></div>';
        } elseif ( ! class_exists( 'ElementorPro\Plugin' ) ) {
            echo '<div class="notice notice-info is-dismissible">';
            echo '<p><strong>' . esc_html__( 'DGPTM Herzzentrum Editor', 'dgptm-herzzentren' ) . ':</strong> ';
            echo esc_html__( 'Für erweiterte Funktionen wird Elementor Pro empfohlen.', 'dgptm-herzzentren' );
            echo '</p></div>';
        }
    }
}

// Plugin initialisieren
DGPTM_Elementor_Herzzentren::get_instance();
