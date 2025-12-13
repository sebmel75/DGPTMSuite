<?php
/**
 * Plugin Name: Vimeo Stream Manager Multi
 * Plugin URI: https://dgptm.de
 * Description: Zeigt mehrere Vimeo-Streams gleichzeitig mit Multi-Layout. Oben kleine Nebenstreams (stumm), unten großer Hauptstream (mit Ton). Klick auf Nebenstream macht ihn zum Hauptstream. Mobile: Maximieren-Funktion für Vollbild.
 * Version: 3.1.0
 * Author: DGPTM
 * Author URI: https://dgptm.de
 * License: GPL v2 or later
 * Text Domain: vimeo-stream-manager
 *
 * Changelog v3.1.0:
 * - Neue Maximieren-Funktion für Mobile Devices
 * - Hauptstream kann auf Mobile in Vollbild maximiert werden
 * - Close-Button (X) zum Schließen des Vollbild-Modus
 * - Verbesserte Touch-Interaktion
 * - Optimiert für Portrait und Landscape Modus
 */

// Direkten Zugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

class VimeoStreamManagerMulti {
    
    private $option_name = 'vsm_streams_data';
    private $settings_name = 'vsm_settings';
    
    public function __construct() {
        // Admin-Menü registrieren
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // Shortcode registrieren
        add_shortcode('vimeo_streams', array($this, 'render_streams'));
        
        // Scripts und Styles einbinden
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_assets'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
        
        // AJAX-Handler
        add_action('wp_ajax_vsm_save_streams', array($this, 'ajax_save_streams'));
        add_action('wp_ajax_vsm_delete_day', array($this, 'ajax_delete_day'));
        add_action('wp_ajax_vsm_get_day_streams', array($this, 'ajax_get_day_streams'));
        add_action('wp_ajax_vsm_save_settings', array($this, 'ajax_save_settings'));
        add_action('wp_ajax_vsm_check_password', array($this, 'ajax_check_password'));
        add_action('wp_ajax_nopriv_vsm_check_password', array($this, 'ajax_check_password'));
    }
    
    /**
     * Admin-Menü hinzufügen
     */
    public function add_admin_menu() {
        add_menu_page(
            'Vimeo Streams',
            'Vimeo Streams',
            'manage_options',
            'vimeo-stream-manager',
            array($this, 'render_admin_page'),
            'dashicons-video-alt3',
            30
        );
    }
    
    /**
     * Einstellungen registrieren
     */
    public function register_settings() {
        register_setting('vsm_options', $this->option_name);
        register_setting('vsm_options', $this->settings_name);
    }
    
    /**
     * Frontend-Assets einbinden
     */
    public function enqueue_frontend_assets() {
        global $post;
        if ($post && has_shortcode($post->post_content ?? '', 'vimeo_streams')) {
            // Plugin Styles
            wp_enqueue_style(
                'vsm-frontend-style',
                plugin_dir_url(__FILE__) . 'assets/css/frontend.css',
                array(),
                '3.1.0'
            );

            // Plugin Scripts (ohne Vimeo Player API, da wir direkt iframes nutzen)
            wp_enqueue_script(
                'vsm-frontend-script',
                plugin_dir_url(__FILE__) . 'assets/js/frontend.js',
                array('jquery'),
                '3.1.0',
                true
            );
            
            // Daten an JavaScript übergeben
            wp_localize_script('vsm-frontend-script', 'vsmData', array(
                'streams' => $this->get_streams_data(),
                'settings' => $this->get_settings(),
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'texts' => array(
                    'noStream' => 'Kein Stream verfügbar',
                    'selectDay' => 'Bitte wählen Sie einen Tag',
                    'loading' => 'Lade Stream...'
                )
            ));
        }
    }
    
    /**
     * Admin-Assets einbinden
     */
    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_vimeo-stream-manager' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'vsm-admin-style',
            plugin_dir_url(__FILE__) . 'assets/css/admin.css',
            array(),
            '3.1.0'
        );

        wp_enqueue_script(
            'vsm-admin-script',
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            array('jquery'),
            '3.1.0',
            true
        );
        
        wp_localize_script('vsm-admin-script', 'vsmAdmin', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vsm-admin-nonce')
        ));
    }
    
    /**
     * Stream-Daten abrufen
     */
    private function get_streams_data() {
        $data = get_option($this->option_name, array());
        return is_array($data) ? $data : array();
    }
    
    /**
     * Einstellungen abrufen
     */
    private function get_settings() {
        $defaults = array(
            'banner_enabled' => false,
            'banner_text' => '',
            'top_stream_height' => 250,
            'bottom_stream_height' => 500,
            'max_top_streams' => 4,
            'password_enabled' => false,
            'password' => '',
            'grid_columns' => 2,
            'auto_switch_sound' => true
        );
        
        $settings = get_option($this->settings_name, array());
        return wp_parse_args($settings, $defaults);
    }
    
    /**
     * Admin-Seite rendern
     */
    public function render_admin_page() {
        ?>
        <div class="wrap vsm-admin-wrap">
            <h1 class="wp-heading-inline">
                <span class="dashicons dashicons-video-alt3"></span>
                Vimeo Stream Manager - Multi Layout
            </h1>
            
            <div class="vsm-admin-container">
                <div class="nav-tab-wrapper">
                    <a href="#streams" class="nav-tab nav-tab-active">Stream-Verwaltung</a>
                    <a href="#settings" class="nav-tab">Einstellungen</a>
                    <a href="#help" class="nav-tab">Hilfe</a>
                </div>
                
                <div id="streams" class="tab-content active">
                    <?php $this->render_streams_tab(); ?>
                </div>
                
                <div id="settings" class="tab-content">
                    <?php $this->render_settings_tab(); ?>
                </div>
                
                <div id="help" class="tab-content">
                    <?php $this->render_help_tab(); ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Streams-Tab rendern
     */
    private function render_streams_tab() {
        $streams_data = $this->get_streams_data();
        ?>
        <div class="vsm-streams-section">
            <div class="vsm-card">
                <h2>📅 Tag-Verwaltung</h2>
                <p>Definieren Sie eigene Tage/Events und ordnen Sie bis zu 5 Vimeo-Streams zu.</p>
                
                <div class="vsm-day-form">
                    <h3>Neuen Tag hinzufügen</h3>
                    <div class="vsm-form-row">
                        <label>Tag-Name:</label>
                        <input type="text" id="vsm-new-day-name" placeholder="z.B. Tag 1, Montag, 15.03.2024">
                        <button id="vsm-add-day" class="button button-primary">Tag hinzufügen</button>
                    </div>
                </div>
                
                <div class="vsm-days-list">
                    <h3>Vorhandene Tage</h3>
                    <?php if (empty($streams_data)): ?>
                        <p class="vsm-no-days">Noch keine Tage angelegt.</p>
                    <?php else: ?>
                        <div class="vsm-days-grid">
                            <?php foreach ($streams_data as $day => $streams): ?>
                                <div class="vsm-day-card" data-day="<?php echo esc_attr($day); ?>">
                                    <div class="vsm-day-header">
                                        <h4><?php echo esc_html($day); ?></h4>
                                        <div class="vsm-day-actions">
                                            <button class="vsm-edit-day button button-secondary">Bearbeiten</button>
                                            <button class="vsm-delete-day button button-link-delete">Löschen</button>
                                        </div>
                                    </div>
                                    <div class="vsm-day-streams">
                                        <?php 
                                        $stream_count = 0;
                                        for ($i = 1; $i <= 5; $i++):
                                            if (!empty($streams['stream_' . $i])):
                                                $stream_count++;
                                            endif;
                                        endfor;
                                        ?>
                                        <p><?php echo $stream_count; ?> Stream(s) konfiguriert</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Stream-Editor Modal -->
        <div id="vsm-stream-editor" class="vsm-modal" style="display: none;">
            <div class="vsm-modal-content">
                <div class="vsm-modal-header">
                    <h2>Streams bearbeiten: <span id="vsm-edit-day-name"></span></h2>
                    <button class="vsm-modal-close">&times;</button>
                </div>
                <div class="vsm-modal-body">
                    <p class="description">Die ersten Streams werden oben angezeigt (klein, stumm). Der letzte konfigurierte Stream wird unten groß mit Ton angezeigt.</p>
                    
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                    <div class="vsm-stream-input">
                        <label>Stream <?php echo $i; ?>:</label>
                        <div class="vsm-input-group">
                            <input type="text" 
                                   id="stream_<?php echo $i; ?>" 
                                   placeholder="Video: 123456789 oder Event: event/12345"
                                   class="regular-text">
                            <input type="text" 
                                   id="caption_<?php echo $i; ?>" 
                                   placeholder="Beschriftung (optional)"
                                   class="regular-text">
                        </div>
                        <p class="description">
                            <?php if ($i < 5): ?>
                                Wird als kleiner Stream oben angezeigt (stumm)
                            <?php else: ?>
                                <strong>Hauptstream - wird groß unten angezeigt (mit Ton)</strong>
                            <?php endif; ?>
                            <br>
                            <small>Für Livestreams/Events verwenden Sie: <code>event/12345</code></small>
                        </p>
                    </div>
                    <?php endfor; ?>
                </div>
                <div class="vsm-modal-footer">
                    <button id="vsm-save-streams" class="button button-primary">Speichern</button>
                    <button class="vsm-modal-close button">Abbrechen</button>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Einstellungen-Tab rendern
     */
    private function render_settings_tab() {
        $settings = $this->get_settings();
        ?>
        <div class="vsm-settings-section">
            <form id="vsm-settings-form">
                <div class="vsm-card">
                    <h2>🎨 Layout-Einstellungen</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="grid_columns">Grid-Spalten</label>
                            </th>
                            <td>
                                <select id="grid_columns" name="grid_columns">
                                    <option value="2" <?php selected($settings['grid_columns'], 2); ?>>2 Spalten</option>
                                    <option value="3" <?php selected($settings['grid_columns'], 3); ?>>3 Spalten</option>
                                    <option value="4" <?php selected($settings['grid_columns'], 4); ?>>4 Spalten</option>
                                </select>
                                <p class="description">Anzahl der Spalten für die oberen Streams</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="max_top_streams">Max. obere Streams</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="max_top_streams" 
                                       name="max_top_streams" 
                                       value="<?php echo esc_attr($settings['max_top_streams']); ?>" 
                                       min="1" 
                                       max="4"
                                       class="small-text">
                                <p class="description">Maximale Anzahl der kleinen Streams oben (1-4)</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="top_stream_height">Höhe obere Streams</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="top_stream_height" 
                                       name="top_stream_height" 
                                       value="<?php echo esc_attr($settings['top_stream_height']); ?>" 
                                       min="150" 
                                       max="500"
                                       class="small-text"> px
                                <p class="description">Höhe der kleinen Streams oben</p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="bottom_stream_height">Höhe Hauptstream</label>
                            </th>
                            <td>
                                <input type="number" 
                                       id="bottom_stream_height" 
                                       name="bottom_stream_height" 
                                       value="<?php echo esc_attr($settings['bottom_stream_height']); ?>" 
                                       min="300" 
                                       max="800"
                                       class="small-text"> px
                                <p class="description">Höhe des großen Hauptstreams unten</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="vsm-card">
                    <h2>🔊 Audio-Einstellungen</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="auto_switch_sound">Auto-Switch Audio</label>
                            </th>
                            <td>
                                <label class="vsm-toggle">
                                    <input type="checkbox" 
                                           id="auto_switch_sound" 
                                           name="auto_switch_sound" 
                                           value="1" 
                                           <?php checked($settings['auto_switch_sound'], true); ?>>
                                    <span class="vsm-toggle-slider"></span>
                                </label>
                                <p class="description">Ton automatisch zum Hauptstream wechseln beim Klick</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="vsm-card">
                    <h2>📢 Banner</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="banner_enabled">Banner anzeigen</label>
                            </th>
                            <td>
                                <label class="vsm-toggle">
                                    <input type="checkbox" 
                                           id="banner_enabled" 
                                           name="banner_enabled" 
                                           value="1" 
                                           <?php checked($settings['banner_enabled'], true); ?>>
                                    <span class="vsm-toggle-slider"></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="banner_text">Banner-Text</label>
                            </th>
                            <td>
                                <textarea id="banner_text" 
                                          name="banner_text" 
                                          rows="3" 
                                          class="large-text"
                                          placeholder="z.B. Willkommen zum Live-Stream!"><?php echo esc_textarea($settings['banner_text']); ?></textarea>
                                <p class="description">HTML ist erlaubt. Wird über den Streams angezeigt.</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="vsm-card">
                    <h2>🔒 Passwortschutz</h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="password_enabled">Passwortschutz aktivieren</label>
                            </th>
                            <td>
                                <label class="vsm-toggle">
                                    <input type="checkbox" 
                                           id="password_enabled" 
                                           name="password_enabled" 
                                           value="1" 
                                           <?php checked($settings['password_enabled'], true); ?>>
                                    <span class="vsm-toggle-slider"></span>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="password">Passwort</label>
                            </th>
                            <td>
                                <input type="text" 
                                       id="password" 
                                       name="password" 
                                       value="<?php echo esc_attr($settings['password']); ?>" 
                                       class="regular-text"
                                       placeholder="z.B. dgptm2024">
                                <p class="description">Besucher müssen dieses Passwort eingeben</p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="vsm-settings-actions">
                    <button type="submit" class="button button-primary button-large">
                        Einstellungen speichern
                    </button>
                </div>
                
                <div class="vsm-settings-message" style="display: none;"></div>
            </form>
        </div>
        <?php
    }
    
    /**
     * Hilfe-Tab rendern
     */
    private function render_help_tab() {
        ?>
        <div class="vsm-help-section">
            <div class="vsm-card">
                <h2>📖 Verwendung</h2>
                <p>Verwenden Sie den folgenden Shortcode, um die Streams auf einer Seite anzuzeigen:</p>
                <code class="vsm-shortcode">[vimeo_streams]</code>
                
                <h3>Shortcode-Parameter:</h3>
                <ul>
                    <li><code>[vimeo_streams buttons="off"]</code> - Versteckt die Tag-Auswahl-Buttons</li>
                    <li><code>[vimeo_streams tag="Tag 1"]</code> - Zeigt direkt einen bestimmten Tag an</li>
                    <li><code>[vimeo_streams columns="3"]</code> - Überschreibt die Grid-Spalten</li>
                    <li><code>[vimeo_streams password="geheim123"]</code> - Setzt ein Passwort für diese Instanz</li>
                    <li><code>[vimeo_streams allowed_referers="domain1.de,domain2.de"]</code> - Erlaubt nur Iframe-Einbindung von diesen Domains</li>
                </ul>
                
                <h3>Beispiele:</h3>
                <h4>Mit Passwortschutz:</h4>
                <code class="vsm-shortcode">[vimeo_streams password="event2024"]</code>
                
                <h4>Nur als Iframe von bestimmter Domain:</h4>
                <code class="vsm-shortcode">[vimeo_streams allowed_referers="2025.fokusperfusion.de"]</code>
                
                <h4>Kombiniert:</h4>
                <code class="vsm-shortcode">[vimeo_streams password="dgptm" allowed_referers="event.dgptm.de,2025.fokusperfusion.de" tag="Tag 1" buttons="off"]</code>
            </div>
            
            <div class="vsm-card">
                <h2>🎬 Multi-Stream-Layout</h2>
                <ul>
                    <li><strong>Obere Streams:</strong> Kleine Vorschau-Streams (automatisch stumm geschaltet)</li>
                    <li><strong>Hauptstream:</strong> Großer Stream unten (mit Ton)</li>
                    <li><strong>Interaktion:</strong> Klick auf einen oberen Stream macht ihn zum Hauptstream</li>
                    <li><strong>Audio-Verwaltung:</strong> Nur der Hauptstream hat Ton, alle anderen sind stumm</li>
                </ul>
            </div>
            
            <div class="vsm-card">
                <h2>🆔 Vimeo Video IDs finden</h2>
                
                <h3>Normale Videos:</h3>
                <ol>
                    <li>Öffnen Sie das Video auf Vimeo</li>
                    <li>Die URL sieht so aus: <code>https://vimeo.com/123456789</code></li>
                    <li>Die Zahlen am Ende (123456789) sind die Video ID</li>
                    <li>Geben Sie nur die Zahlen ein: <code>123456789</code></li>
                </ol>
                
                <h3>Events/Livestreams:</h3>
                <ol>
                    <li>Öffnen Sie das Event auf Vimeo</li>
                    <li>Die URL sieht so aus: <code>https://vimeo.com/event/12345</code></li>
                    <li>Die Event-ID ist die Zahl nach /event/</li>
                    <li>Geben Sie ein: <code>event/12345</code></li>
                </ol>
                
                <div style="background: #f0f8ff; padding: 15px; border-left: 4px solid #0073aa; margin-top: 15px;">
                    <strong>⚠️ Wichtig für Events/Livestreams:</strong>
                    <ul style="margin: 10px 0 0 20px;">
                        <li>Events können nicht im Background-Modus laufen</li>
                        <li>Obere Events sind stumm bis zur User-Interaktion</li>
                        <li>Hauptstream (unten) startet automatisch mit Ton</li>
                        <li>Format: <code>event/</code> gefolgt von der Event-ID</li>
                    </ul>
                </div>
            </div>
            
            <div class="vsm-card">
                <h2>⚡ Performance-Tipps</h2>
                <ul>
                    <li>Verwenden Sie maximal 4-5 Streams gleichzeitig für beste Performance</li>
                    <li>Optimale Stream-Höhen: Oben 200-300px, Unten 400-600px</li>
                    <li>Nicht benötigte Stream-Slots einfach leer lassen</li>
                </ul>
            </div>
        </div>
        <?php
    }
    
    /**
     * Shortcode rendern
     */
    public function render_streams($atts) {
        $settings = $this->get_settings();
        
        // Shortcode-Attribute verarbeiten
        $atts = shortcode_atts(array(
            'buttons' => 'on',
            'tag' => '',
            'columns' => '',
            'password' => '',
            'allowed_referers' => ''
        ), $atts);
        
        // Referer-Check wenn allowed_referers gesetzt
        if (!empty($atts['allowed_referers'])) {
            $allowed_domains = array_map('trim', explode(',', $atts['allowed_referers']));
            $referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
            $is_allowed = false;
            
            // Prüfe ob Referer von erlaubter Domain kommt
            foreach ($allowed_domains as $domain) {
                // Domain ohne Protokoll für flexibleren Check
                $domain = str_replace(array('http://', 'https://'), '', $domain);
                if (strpos($referer, $domain) !== false) {
                    $is_allowed = true;
                    break;
                }
            }
            
            // Wenn kein erlaubter Referer und direkter Zugriff
            if (!$is_allowed) {
                return $this->render_referer_error($allowed_domains);
            }
        }
        
        // Passwort-Check
        // Priorität: 1. Shortcode-Parameter, 2. Globale Einstellung
        $password_to_check = !empty($atts['password']) ? $atts['password'] : 
                           ($settings['password_enabled'] && !empty($settings['password']) ? $settings['password'] : '');
        
        if (!empty($password_to_check)) {
            // Session starten für Passwort-Check
            if (!session_id()) {
                session_start();
            }
            
            // Eindeutiger Session-Key basierend auf Passwort
            $session_key = 'vsm_auth_' . md5($password_to_check);
            
            // Passwort-Check via Cookie oder Session
            $cookie_hash = isset($_COOKIE[$session_key]) ? $_COOKIE[$session_key] : '';
            $session_valid = isset($_SESSION[$session_key]) && $_SESSION[$session_key] === true;
            $expected_hash = md5($password_to_check . SECURE_AUTH_SALT);
            
            // Wenn Passwort-Formular gesendet wurde
            if (isset($_POST['vsm_password_submit'])) {
                $submitted_password = isset($_POST['vsm_password']) ? $_POST['vsm_password'] : '';
                if ($submitted_password === $password_to_check) {
                    // Passwort korrekt - Session und Cookie setzen
                    $_SESSION[$session_key] = true;
                    setcookie($session_key, $expected_hash, time() + (86400 * 7), COOKIEPATH, COOKIE_DOMAIN);
                    $session_valid = true;
                    $cookie_hash = $expected_hash;
                }
            }
            
            // Wenn nicht authentifiziert
            if ($cookie_hash !== $expected_hash && !$session_valid) {
                return $this->render_password_form($password_to_check);
            }
        }
        
        // Weitere Parameter
        $show_buttons = strtolower($atts['buttons']) !== 'off';
        $fixed_tag = !empty($atts['tag']) ? sanitize_text_field($atts['tag']) : '';
        $override_columns = !empty($atts['columns']) ? intval($atts['columns']) : 0;
        
        ob_start();
        ?>
        <div class="vsm-container" 
             data-show-buttons="<?php echo $show_buttons ? 'true' : 'false'; ?>" 
             data-fixed-tag="<?php echo esc_attr($fixed_tag); ?>"
             data-columns="<?php echo $override_columns ?: $settings['grid_columns']; ?>">
            
            <?php if ($settings['banner_enabled'] && !empty($settings['banner_text'])): ?>
            <div class="vsm-banner">
                <?php echo wp_kses_post($settings['banner_text']); ?>
            </div>
            <?php endif; ?>
            
            <div class="vsm-streams-wrapper">
                <div class="vsm-grid" data-columns="<?php echo $override_columns ?: $settings['grid_columns']; ?>">
                    <!-- Obere kleine Streams (dynamisch generiert via JS) -->
                    <div class="vsm-top-streams"></div>

                    <!-- Unterer großer Hauptstream -->
                    <div class="vsm-bottom-stream">
                        <div class="vsm-stream vsm-main-stream" data-stream="main">
                            <div class="vsm-placeholder">
                                <div class="vsm-placeholder-content">
                                    <span class="dashicons dashicons-video-alt3"></span>
                                    <p>Hauptstream</p>
                                </div>
                            </div>
                            <div class="vsm-stream-caption"></div>
                        </div>

                        <!-- Fullscreen Controls (nur Mobile) -->
                        <div class="vsm-fullscreen-controls">
                            <button class="vsm-fullscreen-button" type="button" aria-label="Vollbild">
                                Vollbild
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Close-Button für manuellen Fullscreen -->
            <button class="vsm-fullscreen-close" type="button" aria-label="Schließen">×</button>
            
            <?php if ($show_buttons): ?>
            <div class="vsm-day-selector"></div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Referer-Fehler anzeigen
     */
    private function render_referer_error($allowed_domains) {
        ob_start();
        ?>
        <div class="vsm-referer-error">
            <div class="vsm-error-box">
                <h2>🚫 Zugriff verweigert</h2>
                <p>Diese Seite kann nur als eingebetteter Inhalt (iframe) von folgenden Domains aufgerufen werden:</p>
                <ul>
                    <?php foreach ($allowed_domains as $domain): ?>
                        <li><?php echo esc_html($domain); ?></li>
                    <?php endforeach; ?>
                </ul>
                <p>Direkter Zugriff ist nicht gestattet.</p>
            </div>
        </div>
        <style>
        .vsm-referer-error {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 400px;
            padding: 40px 20px;
        }
        .vsm-error-box {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 500px;
            width: 100%;
            text-align: center;
            border: 2px solid #dc3545;
        }
        .vsm-error-box h2 {
            margin: 0 0 15px 0;
            color: #dc3545;
            font-size: 24px;
        }
        .vsm-error-box p {
            margin: 15px 0;
            color: #666;
        }
        .vsm-error-box ul {
            list-style: none;
            padding: 0;
            margin: 20px 0;
        }
        .vsm-error-box li {
            background: #f8f9fa;
            padding: 10px;
            margin: 5px 0;
            border-radius: 4px;
            font-family: monospace;
            color: #333;
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * Passwort-Formular rendern
     */
    private function render_password_form($required_password = null) {
        // Verwende entweder das übergebene Passwort oder das aus den Settings
        if ($required_password === null) {
            $settings = $this->get_settings();
            $required_password = $settings['password'];
        }
        ob_start();
        ?>
        <div class="vsm-password-container">
            <div class="vsm-password-box">
                <h2>🔒 Passwortgeschützter Bereich</h2>
                <p>Bitte geben Sie das Passwort ein, um die Streams anzusehen.</p>
                <form class="vsm-password-form" method="post">
                    <input type="password" 
                           name="vsm_password" 
                           class="vsm-password-input" 
                           placeholder="Passwort eingeben" 
                           autocomplete="off"
                           required>
                    <button type="submit" name="vsm_password_submit" value="1" class="vsm-password-submit">Absenden</button>
                    <?php if (isset($_POST['vsm_password_submit']) && isset($_POST['vsm_password'])): ?>
                        <div class="vsm-password-error">Falsches Passwort. Bitte versuchen Sie es erneut.</div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
        <style>
        .vsm-password-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 400px;
            padding: 40px 20px;
        }
        .vsm-password-box {
            background: white;
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            max-width: 400px;
            width: 100%;
            text-align: center;
        }
        .vsm-password-box h2 {
            margin: 0 0 15px 0;
            color: #333;
            font-size: 24px;
        }
        .vsm-password-box p {
            margin: 0 0 25px 0;
            color: #666;
        }
        .vsm-password-form {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            flex-direction: row;
        }
        .vsm-password-input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #ddd;
            border-radius: 6px;
            font-size: 16px;
        }
        .vsm-password-input:focus {
            outline: none;
            border-color: #0073aa;
        }
        .vsm-password-submit {
            padding: 12px 24px;
            background: #0073aa;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
        }
        .vsm-password-submit:hover {
            background: #005a87;
        }
        .vsm-password-error {
            background: #ffd9dc;
            color: #b32d2e;
            padding: 12px;
            border-radius: 6px;
            border-left: 4px solid #d63638;
            margin-top: 15px;
        }
        @media (max-width: 480px) {
            .vsm-password-form {
                flex-direction: column;
            }
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    /**
     * AJAX: Streams speichern
     */
    public function ajax_save_streams() {
        check_ajax_referer('vsm-admin-nonce', 'nonce');
        
        $day = sanitize_text_field($_POST['day']);
        $streams = array();
        
        for ($i = 1; $i <= 5; $i++) {
            $streams['stream_' . $i] = sanitize_text_field($_POST['stream_' . $i] ?? '');
            $streams['caption_' . $i] = sanitize_text_field($_POST['caption_' . $i] ?? '');
        }
        
        $all_streams = $this->get_streams_data();
        $all_streams[$day] = $streams;
        
        update_option($this->option_name, $all_streams);
        
        wp_send_json_success(array('message' => 'Streams gespeichert'));
    }
    
    /**
     * AJAX: Tag löschen
     */
    public function ajax_delete_day() {
        check_ajax_referer('vsm-admin-nonce', 'nonce');
        
        $day = sanitize_text_field($_POST['day']);
        
        $all_streams = $this->get_streams_data();
        unset($all_streams[$day]);
        
        update_option($this->option_name, $all_streams);
        
        wp_send_json_success(array('message' => 'Tag gelöscht'));
    }
    
    /**
     * AJAX: Tag-Streams abrufen
     */
    public function ajax_get_day_streams() {
        check_ajax_referer('vsm-admin-nonce', 'nonce');
        
        $day = sanitize_text_field($_POST['day']);
        $all_streams = $this->get_streams_data();
        
        $streams = isset($all_streams[$day]) ? $all_streams[$day] : array();
        
        wp_send_json_success($streams);
    }
    
    /**
     * AJAX: Einstellungen speichern
     */
    public function ajax_save_settings() {
        check_ajax_referer('vsm-admin-nonce', 'nonce');
        
        $settings = array(
            'banner_enabled' => isset($_POST['banner_enabled']),
            'banner_text' => wp_kses_post($_POST['banner_text'] ?? ''),
            'top_stream_height' => intval($_POST['top_stream_height'] ?? 250),
            'bottom_stream_height' => intval($_POST['bottom_stream_height'] ?? 500),
            'max_top_streams' => intval($_POST['max_top_streams'] ?? 4),
            'password_enabled' => isset($_POST['password_enabled']),
            'password' => sanitize_text_field($_POST['password'] ?? ''),
            'grid_columns' => intval($_POST['grid_columns'] ?? 2),
            'auto_switch_sound' => isset($_POST['auto_switch_sound'])
        );
        
        update_option($this->settings_name, $settings);
        
        wp_send_json_success(array('message' => 'Einstellungen gespeichert'));
    }
    
    /**
     * AJAX: Passwort prüfen
     */
    public function ajax_check_password() {
        $settings = $this->get_settings();
        $password = sanitize_text_field($_POST['password'] ?? '');
        
        if ($password === $settings['password']) {
            $hash = md5($password . SECURE_AUTH_SALT);
            setcookie('vsm_auth', $hash, time() + (86400 * 7), COOKIEPATH, COOKIE_DOMAIN);
            wp_send_json_success(array('valid' => true));
        } else {
            wp_send_json_error(array('message' => 'Falsches Passwort'));
        }
    }
}

// Plugin initialisieren
new VimeoStreamManagerMulti();
