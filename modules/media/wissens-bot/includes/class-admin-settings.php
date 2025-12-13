<?php
/**
 * Admin-Einstellungen für Wissens-Bot
 */

if (!defined('ABSPATH')) {
    exit;
}

class Wissens_Bot_Admin_Settings {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            __('Wissens-Bot Einstellungen', 'wissens-bot'),
            __('Wissens-Bot', 'wissens-bot'),
            'manage_options',
            'wissens-bot',
            array($this, 'render_settings_page'),
            'dashicons-format-chat',
            30
        );
    }
    
    public function register_settings() {
        // Claude API Einstellungen
        register_setting('wissens_bot_settings', 'wissens_bot_claude_api_key');
        register_setting('wissens_bot_settings', 'wissens_bot_max_tokens');
        register_setting('wissens_bot_settings', 'wissens_bot_system_prompt');
        
        // Themeneingrenzung
        register_setting('wissens_bot_settings', 'wissens_bot_topic_keywords');
        
        // Datenquellen
        register_setting('wissens_bot_settings', 'wissens_bot_sharepoint_enabled');
        register_setting('wissens_bot_settings', 'wissens_bot_pubmed_enabled');
        register_setting('wissens_bot_settings', 'wissens_bot_scholar_enabled');
        
        // SharePoint Einstellungen
        register_setting('wissens_bot_settings', 'wissens_bot_sharepoint_tenant_id');
        register_setting('wissens_bot_settings', 'wissens_bot_sharepoint_client_id');
        register_setting('wissens_bot_settings', 'wissens_bot_sharepoint_client_secret');
        register_setting('wissens_bot_settings', 'wissens_bot_sharepoint_site_url');
        register_setting('wissens_bot_settings', 'wissens_bot_sharepoint_folders');
        
        // Sektionen
        add_settings_section(
            'wissens_bot_claude_section',
            __('Claude AI Konfiguration', 'wissens-bot'),
            array($this, 'render_claude_section'),
            'wissens-bot'
        );
        
        add_settings_section(
            'wissens_bot_topics_section',
            __('Themeneingrenzung', 'wissens-bot'),
            array($this, 'render_topics_section'),
            'wissens-bot'
        );
        
        add_settings_section(
            'wissens_bot_sources_section',
            __('Datenquellen', 'wissens-bot'),
            array($this, 'render_sources_section'),
            'wissens-bot'
        );
        
        add_settings_section(
            'wissens_bot_sharepoint_section',
            __('SharePoint Konfiguration', 'wissens-bot'),
            array($this, 'render_sharepoint_section'),
            'wissens-bot'
        );
        
        // Felder
        $this->add_settings_fields();
    }
    
    private function add_settings_fields() {
        // Claude API Key
        add_settings_field(
            'claude_api_key',
            __('Claude API Key', 'wissens-bot'),
            array($this, 'render_text_field'),
            'wissens-bot',
            'wissens_bot_claude_section',
            array(
                'label_for' => 'wissens_bot_claude_api_key',
                'type' => 'password',
                'description' => __('Ihr Anthropic API Key (erhältlich unter https://console.anthropic.com)', 'wissens-bot')
            )
        );
        
        // Max Tokens
        add_settings_field(
            'max_tokens',
            __('Max Tokens', 'wissens-bot'),
            array($this, 'render_text_field'),
            'wissens-bot',
            'wissens_bot_claude_section',
            array(
                'label_for' => 'wissens_bot_max_tokens',
                'type' => 'number',
                'description' => __('Maximale Anzahl der Tokens für die Antwort (Standard: 4000)', 'wissens-bot')
            )
        );
        
        // System Prompt
        add_settings_field(
            'system_prompt',
            __('System Prompt', 'wissens-bot'),
            array($this, 'render_textarea_field'),
            'wissens-bot',
            'wissens_bot_claude_section',
            array(
                'label_for' => 'wissens_bot_system_prompt',
                'description' => __('Grundlegende Anweisungen für den Bot', 'wissens-bot')
            )
        );
        
        // Topic Keywords
        add_settings_field(
            'topic_keywords',
            __('Themen-Keywords', 'wissens-bot'),
            array($this, 'render_textarea_field'),
            'wissens-bot',
            'wissens_bot_topics_section',
            array(
                'label_for' => 'wissens_bot_topic_keywords',
                'description' => __('Kommagetrennte Liste der erlaubten Themenbereiche', 'wissens-bot')
            )
        );
        
        // Datenquellen
        add_settings_field(
            'sharepoint_enabled',
            __('SharePoint aktivieren', 'wissens-bot'),
            array($this, 'render_checkbox_field'),
            'wissens-bot',
            'wissens_bot_sources_section',
            array('label_for' => 'wissens_bot_sharepoint_enabled')
        );
        
        add_settings_field(
            'pubmed_enabled',
            __('PubMed aktivieren', 'wissens-bot'),
            array($this, 'render_checkbox_field'),
            'wissens-bot',
            'wissens_bot_sources_section',
            array('label_for' => 'wissens_bot_pubmed_enabled')
        );
        
        add_settings_field(
            'scholar_enabled',
            __('Google Scholar aktivieren', 'wissens-bot'),
            array($this, 'render_checkbox_field'),
            'wissens-bot',
            'wissens_bot_sources_section',
            array('label_for' => 'wissens_bot_scholar_enabled')
        );
        
        // SharePoint Felder
        add_settings_field(
            'sharepoint_tenant_id',
            __('Tenant ID', 'wissens-bot'),
            array($this, 'render_text_field'),
            'wissens-bot',
            'wissens_bot_sharepoint_section',
            array(
                'label_for' => 'wissens_bot_sharepoint_tenant_id',
                'type' => 'text'
            )
        );
        
        add_settings_field(
            'sharepoint_client_id',
            __('Client ID', 'wissens-bot'),
            array($this, 'render_text_field'),
            'wissens-bot',
            'wissens_bot_sharepoint_section',
            array(
                'label_for' => 'wissens_bot_sharepoint_client_id',
                'type' => 'text'
            )
        );
        
        add_settings_field(
            'sharepoint_client_secret',
            __('Client Secret', 'wissens-bot'),
            array($this, 'render_text_field'),
            'wissens-bot',
            'wissens_bot_sharepoint_section',
            array(
                'label_for' => 'wissens_bot_sharepoint_client_secret',
                'type' => 'password'
            )
        );
        
        add_settings_field(
            'sharepoint_site_url',
            __('Site URL', 'wissens-bot'),
            array($this, 'render_text_field'),
            'wissens-bot',
            'wissens_bot_sharepoint_section',
            array(
                'label_for' => 'wissens_bot_sharepoint_site_url',
                'type' => 'url',
                'description' => __('z.B. https://ihreorganisation.sharepoint.com/sites/wissensbot', 'wissens-bot')
            )
        );
        
        add_settings_field(
            'sharepoint_folders',
            __('Ordner-Pfade', 'wissens-bot'),
            array($this, 'render_textarea_field'),
            'wissens-bot',
            'wissens_bot_sharepoint_section',
            array(
                'label_for' => 'wissens_bot_sharepoint_folders',
                'description' => __('Ein Pfad pro Zeile, z.B. /Shared Documents/Wissen', 'wissens-bot')
            )
        );
    }
    
    public function render_claude_section() {
        echo '<p>' . __('Konfigurieren Sie die Claude AI API-Verbindung.', 'wissens-bot') . '</p>';
    }
    
    public function render_topics_section() {
        echo '<p>' . __('Definieren Sie die erlaubten Themenbereiche für den Bot.', 'wissens-bot') . '</p>';
    }
    
    public function render_sources_section() {
        echo '<p>' . __('Wählen Sie, welche Datenquellen der Bot durchsuchen darf.', 'wissens-bot') . '</p>';
    }
    
    public function render_sharepoint_section() {
        echo '<p>' . __('SharePoint-Konfiguration für den Zugriff auf interne Dokumente.', 'wissens-bot') . '</p>';
        echo '<p><em>' . __('Hinweis: Sie benötigen eine App-Registrierung in Azure AD mit entsprechenden Berechtigungen.', 'wissens-bot') . '</em></p>';
    }
    
    public function render_text_field($args) {
        $option_name = $args['label_for'];
        $value = get_option($option_name);
        $type = isset($args['type']) ? $args['type'] : 'text';
        
        printf(
            '<input type="%s" id="%s" name="%s" value="%s" class="regular-text" />',
            esc_attr($type),
            esc_attr($option_name),
            esc_attr($option_name),
            esc_attr($value)
        );
        
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    public function render_textarea_field($args) {
        $option_name = $args['label_for'];
        $value = get_option($option_name);
        
        printf(
            '<textarea id="%s" name="%s" rows="5" class="large-text">%s</textarea>',
            esc_attr($option_name),
            esc_attr($option_name),
            esc_textarea($value)
        );
        
        if (isset($args['description'])) {
            printf('<p class="description">%s</p>', esc_html($args['description']));
        }
    }
    
    public function render_checkbox_field($args) {
        $option_name = $args['label_for'];
        $value = get_option($option_name);
        
        printf(
            '<input type="checkbox" id="%s" name="%s" value="1" %s />',
            esc_attr($option_name),
            esc_attr($option_name),
            checked(1, $value, false)
        );
    }
    
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            return;
        }
        
        if (isset($_GET['settings-updated'])) {
            add_settings_error(
                'wissens_bot_messages',
                'wissens_bot_message',
                __('Einstellungen gespeichert', 'wissens-bot'),
                'updated'
            );
        }
        
        settings_errors('wissens_bot_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            
            <div class="wissens-bot-admin-header">
                <p><?php _e('Konfigurieren Sie Ihren KI-gestützten Wissens-Bot mit Zugriff auf interne und wissenschaftliche Datenquellen.', 'wissens-bot'); ?></p>
            </div>
            
            <form action="options.php" method="post">
                <?php
                settings_fields('wissens_bot_settings');
                do_settings_sections('wissens-bot');
                submit_button(__('Einstellungen speichern', 'wissens-bot'));
                ?>
            </form>
            
            <div class="wissens-bot-shortcode-info">
                <h2><?php _e('Verwendung', 'wissens-bot'); ?></h2>
                <p><?php _e('Fügen Sie folgenden Shortcode in eine Seite oder einen Beitrag ein:', 'wissens-bot'); ?></p>
                <code>[wissens_bot]</code>
                <p><?php _e('Mit Optionen:', 'wissens-bot'); ?></p>
                <code>[wissens_bot title="Mein Bot" height="500px"]</code>
            </div>
        </div>
        <?php
    }
}
