<?php
/**
 * Plugin Name: Wissens-Bot mit Claude AI
 * Plugin URI: https://dgptm.de
 * Description: KI-gestützter Wissens-Bot mit Anbindung an SharePoint, PubMed, Google Scholar und weitere Datenbanken
 * Version: 1.0.0
 * Author: Sebastian / DGPTM
 * Author URI: https://dgptm.de
 * License: GPL v2 or later
 * Text Domain: wissens-bot
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

// Plugin-Konstanten
define('WISSENS_BOT_VERSION', '1.0.0');
define('WISSENS_BOT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WISSENS_BOT_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Hauptklasse des Plugins
 */
class Wissens_Bot {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->load_dependencies();
        $this->init_hooks();
    }
    
    private function load_dependencies() {
        require_once WISSENS_BOT_PLUGIN_DIR . 'includes/class-admin-settings.php';
        require_once WISSENS_BOT_PLUGIN_DIR . 'includes/class-api-handler.php';
        require_once WISSENS_BOT_PLUGIN_DIR . 'includes/class-sharepoint-connector.php';
        require_once WISSENS_BOT_PLUGIN_DIR . 'includes/class-pubmed-connector.php';
        require_once WISSENS_BOT_PLUGIN_DIR . 'includes/class-scholar-connector.php';
        require_once WISSENS_BOT_PLUGIN_DIR . 'includes/class-chat-frontend.php';
    }
    
    private function init_hooks() {
        add_action('init', array($this, 'load_textdomain'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // Shortcode registrieren
        add_shortcode('wissens_bot', array($this, 'render_chat_widget'));
        
        // AJAX-Hooks
        add_action('wp_ajax_wissens_bot_chat', array($this, 'handle_chat_request'));
        add_action('wp_ajax_nopriv_wissens_bot_chat', array($this, 'handle_chat_request'));
        
        // Admin-Einstellungen initialisieren
        if (is_admin()) {
            new Wissens_Bot_Admin_Settings();
        }
    }
    
    public function load_textdomain() {
        load_plugin_textdomain('wissens-bot', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function enqueue_frontend_assets() {
        if (has_shortcode(get_the_content(), 'wissens_bot') || is_page()) {
            wp_enqueue_style(
                'wissens-bot-style',
                WISSENS_BOT_PLUGIN_URL . 'assets/css/style.css',
                array(),
                WISSENS_BOT_VERSION
            );
            
            wp_enqueue_script(
                'wissens-bot-script',
                WISSENS_BOT_PLUGIN_URL . 'assets/js/chat.js',
                array('jquery'),
                WISSENS_BOT_VERSION,
                true
            );
            
            wp_localize_script('wissens-bot-script', 'wissensBotAjax', array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('wissens_bot_nonce'),
                'strings' => array(
                    'thinking' => __('Denke nach...', 'wissens-bot'),
                    'error' => __('Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.', 'wissens-bot'),
                    'placeholder' => __('Stellen Sie Ihre Frage...', 'wissens-bot')
                )
            ));
        }
    }
    
    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_wissens-bot' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'wissens-bot-admin',
            WISSENS_BOT_PLUGIN_URL . 'assets/css/admin.css',
            array(),
            WISSENS_BOT_VERSION
        );
    }
    
    public function render_chat_widget($atts) {
        $atts = shortcode_atts(array(
            'title' => __('Wissens-Bot', 'wissens-bot'),
            'height' => '600px'
        ), $atts);
        
        $frontend = new Wissens_Bot_Frontend();
        return $frontend->render_widget($atts);
    }
    
    public function handle_chat_request() {
        check_ajax_referer('wissens_bot_nonce', 'nonce');
        
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        $conversation_history = isset($_POST['history']) ? json_decode(stripslashes($_POST['history']), true) : array();
        
        if (empty($message)) {
            wp_send_json_error(array('message' => __('Keine Nachricht übermittelt.', 'wissens-bot')));
        }
        
        $api_handler = new Wissens_Bot_API_Handler();
        $response = $api_handler->process_chat_message($message, $conversation_history);
        
        if (is_wp_error($response)) {
            wp_send_json_error(array('message' => $response->get_error_message()));
        }
        
        wp_send_json_success($response);
    }
}

/**
 * Aktivierungs-Hook
 */
function wissens_bot_activate() {
    // Standard-Optionen setzen
    $default_options = array(
        'claude_api_key' => '',
        'topic_keywords' => 'Perfusiologie, Herz-Lungen-Maschine, IABP, ECLS, ECMO',
        'sharepoint_enabled' => true,
        'pubmed_enabled' => true,
        'scholar_enabled' => true,
        'sharepoint_tenant_id' => '',
        'sharepoint_client_id' => '',
        'sharepoint_client_secret' => '',
        'sharepoint_site_url' => '',
        'sharepoint_folders' => '',
        'system_prompt' => 'Du bist ein Experte für Perfusionstechnologie und Extrakorporale Zirkulation. Beantworte nur Fragen zu den konfigurierten Themenbereichen.',
        'max_tokens' => 4000
    );
    
    foreach ($default_options as $key => $value) {
        if (get_option('wissens_bot_' . $key) === false) {
            add_option('wissens_bot_' . $key, $value);
        }
    }
}
register_activation_hook(__FILE__, 'wissens_bot_activate');

/**
 * Plugin initialisieren
 */
function wissens_bot_init() {
    return Wissens_Bot::get_instance();
}
add_action('plugins_loaded', 'wissens_bot_init');
