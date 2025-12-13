<?php
/**
 * Module Settings Manager
 *
 * Zentrale Verwaltung für Modul-Einstellungen mit Schema-Support
 * Module registrieren ihre Settings hier und werden automatisch in der Admin-UI angezeigt
 *
 * @version 2.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Module_Settings_Manager {

    private static $instance = null;
    private $registered_modules = [];
    private $settings_cache = [];

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
        add_action('admin_menu', [$this, 'add_admin_menu'], 90);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
    }

    /**
     * Registriert ein Modul mit seinen Einstellungen
     *
     * @param array $args {
     *     @type string   $id          Modul-ID (required)
     *     @type string   $title       Seitentitel (required)
     *     @type string   $menu_title  Tab-Titel (optional, default: $title)
     *     @type string   $icon        Dashicon-Klasse (optional)
     *     @type int      $priority    Sortierung (optional, default: 10)
     *     @type array    $sections    Settings-Sektionen (optional)
     *     @type array    $fields      Settings-Felder (required)
     *     @type callable $callback    Custom render callback (optional)
     * }
     * @return bool
     */
    public function register_module_settings($args) {
        $defaults = [
            'id' => '',
            'title' => '',
            'menu_title' => '',
            'icon' => 'dashicons-admin-generic',
            'priority' => 10,
            'sections' => [],
            'fields' => [],
            'callback' => null
        ];

        $args = wp_parse_args($args, $defaults);

        // Validierung
        if (empty($args['id']) || empty($args['title'])) {
            return false;
        }

        if (empty($args['menu_title'])) {
            $args['menu_title'] = $args['title'];
        }

        // Debug-Sektion automatisch hinzufügen falls nicht vorhanden
        $has_debug_section = false;
        foreach ($args['sections'] as $section) {
            if ($section['id'] === 'debug') {
                $has_debug_section = true;
                break;
            }
        }

        if (!$has_debug_section) {
            $args['sections'][] = [
                'id' => 'debug',
                'title' => __('Debug-Einstellungen', 'dgptm'),
                'description' => __('Logging-Konfiguration für dieses Modul.', 'dgptm')
            ];

            $args['fields'][] = [
                'id' => 'debug_level',
                'section' => 'debug',
                'title' => __('Debug-Level', 'dgptm'),
                'type' => 'select',
                'options' => [
                    'global' => __('Global-Einstellung verwenden', 'dgptm'),
                    'verbose' => 'Verbose (alles)',
                    'info' => 'Info',
                    'warning' => 'Warning',
                    'error' => 'Error',
                    'critical' => 'Critical (nur kritische)'
                ],
                'default' => 'global',
                'description' => __('Überschreibt das globale Debug-Level für dieses Modul.', 'dgptm')
            ];
        }

        $this->registered_modules[$args['id']] = $args;

        return true;
    }

    /**
     * Holt registrierte Module
     */
    public function get_registered_modules() {
        // Nach Priorität sortieren
        uasort($this->registered_modules, function($a, $b) {
            return $a['priority'] - $b['priority'];
        });

        return $this->registered_modules;
    }

    /**
     * Holt die Einstellungen eines Moduls
     *
     * @param string $module_id Modul-ID
     * @return array
     */
    public function get_module_settings($module_id) {
        if (isset($this->settings_cache[$module_id])) {
            return $this->settings_cache[$module_id];
        }

        $option_name = 'dgptm_module_settings_' . $module_id;
        $settings = get_option($option_name, []);

        // Defaults anwenden
        if (isset($this->registered_modules[$module_id])) {
            $module = $this->registered_modules[$module_id];
            foreach ($module['fields'] as $field) {
                if (!isset($settings[$field['id']]) && isset($field['default'])) {
                    $settings[$field['id']] = $field['default'];
                }
            }
        }

        $this->settings_cache[$module_id] = $settings;

        return $settings;
    }

    /**
     * Holt einen einzelnen Setting-Wert
     *
     * @param string $module_id Modul-ID
     * @param string $key Setting-Key
     * @param mixed $default Default-Wert
     * @return mixed
     */
    public function get_setting($module_id, $key, $default = null) {
        $settings = $this->get_module_settings($module_id);

        if (isset($settings[$key])) {
            return $settings[$key];
        }

        // Default aus Feld-Definition holen
        if (isset($this->registered_modules[$module_id])) {
            foreach ($this->registered_modules[$module_id]['fields'] as $field) {
                if ($field['id'] === $key && isset($field['default'])) {
                    return $field['default'];
                }
            }
        }

        return $default;
    }

    /**
     * Aktualisiert einen einzelnen Setting-Wert
     *
     * @param string $module_id Modul-ID
     * @param string $key Setting-Key
     * @param mixed $value Neuer Wert
     * @return bool
     */
    public function update_setting($module_id, $key, $value) {
        $option_name = 'dgptm_module_settings_' . $module_id;
        $settings = get_option($option_name, []);

        $settings[$key] = $value;

        // Cache invalidieren
        unset($this->settings_cache[$module_id]);

        // Debug-Level Synchronisation
        if ($key === 'debug_level') {
            dgptm_set_module_log_level($module_id, $value);
        }

        return update_option($option_name, $settings);
    }

    /**
     * Aktualisiert mehrere Settings auf einmal
     *
     * @param string $module_id Modul-ID
     * @param array $settings Neue Settings
     * @return bool
     */
    public function update_settings($module_id, $settings) {
        $option_name = 'dgptm_module_settings_' . $module_id;

        // Cache invalidieren
        unset($this->settings_cache[$module_id]);

        // Debug-Level Synchronisation
        if (isset($settings['debug_level'])) {
            dgptm_set_module_log_level($module_id, $settings['debug_level']);
        }

        return update_option($option_name, $settings);
    }

    /**
     * Löscht alle Settings eines Moduls
     *
     * @param string $module_id Modul-ID
     * @return bool
     */
    public function delete_settings($module_id) {
        $option_name = 'dgptm_module_settings_' . $module_id;
        unset($this->settings_cache[$module_id]);
        return delete_option($option_name);
    }

    /**
     * Registriert Admin-Menü
     */
    public function add_admin_menu() {
        add_submenu_page(
            'dgptm-suite',
            __('Modul-Einstellungen', 'dgptm'),
            __('Modul-Einstellungen', 'dgptm'),
            'manage_options',
            'dgptm-module-settings',
            [$this, 'render_settings_page']
        );
    }

    /**
     * Registriert WordPress Settings
     */
    public function register_settings() {
        foreach ($this->registered_modules as $module_id => $module) {
            $option_name = 'dgptm_module_settings_' . $module_id;

            register_setting(
                'dgptm_module_settings_' . $module_id,
                $option_name,
                [
                    'type' => 'array',
                    'sanitize_callback' => [$this, 'sanitize_settings']
                ]
            );
        }
    }

    /**
     * Sanitize Settings
     */
    public function sanitize_settings($input) {
        if (!is_array($input)) {
            return [];
        }

        // Modul-ID aus dem Kontext holen
        $module_id = null;
        if (isset($_POST['dgptm_module_id'])) {
            $module_id = sanitize_text_field($_POST['dgptm_module_id']);
        }

        if (!$module_id || !isset($this->registered_modules[$module_id])) {
            return $input;
        }

        $module = $this->registered_modules[$module_id];
        $sanitized = [];

        foreach ($module['fields'] as $field) {
            $key = $field['id'];

            if (!isset($input[$key])) {
                // Checkbox: nicht gesetzt = false
                if ($field['type'] === 'checkbox') {
                    $sanitized[$key] = false;
                }
                continue;
            }

            $value = $input[$key];

            // Sanitize basierend auf Feldtyp
            switch ($field['type']) {
                case 'text':
                case 'password':
                    $sanitized[$key] = isset($field['sanitize']) ? call_user_func($field['sanitize'], $value) : sanitize_text_field($value);
                    break;

                case 'email':
                    $sanitized[$key] = sanitize_email($value);
                    break;

                case 'url':
                    $sanitized[$key] = esc_url_raw($value);
                    break;

                case 'number':
                    $sanitized[$key] = intval($value);
                    if (isset($field['min']) && $sanitized[$key] < $field['min']) {
                        $sanitized[$key] = $field['min'];
                    }
                    if (isset($field['max']) && $sanitized[$key] > $field['max']) {
                        $sanitized[$key] = $field['max'];
                    }
                    break;

                case 'textarea':
                    $sanitized[$key] = isset($field['sanitize']) ? call_user_func($field['sanitize'], $value) : sanitize_textarea_field($value);
                    break;

                case 'checkbox':
                    $sanitized[$key] = (bool) $value;
                    break;

                case 'select':
                case 'radio':
                    // Validiere gegen erlaubte Optionen
                    if (isset($field['options']) && array_key_exists($value, $field['options'])) {
                        $sanitized[$key] = $value;
                    } elseif (isset($field['default'])) {
                        $sanitized[$key] = $field['default'];
                    }
                    break;

                case 'multiselect':
                    $sanitized[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : [];
                    break;

                case 'color':
                    $sanitized[$key] = sanitize_hex_color($value);
                    break;

                case 'editor':
                    $sanitized[$key] = wp_kses_post($value);
                    break;

                case 'media':
                    $sanitized[$key] = absint($value);
                    break;

                case 'code':
                    // Code wird nicht sanitized (Vorsicht!)
                    $sanitized[$key] = $value;
                    break;

                default:
                    $sanitized[$key] = sanitize_text_field($value);
            }
        }

        // Cache invalidieren
        unset($this->settings_cache[$module_id]);

        // Debug-Level Synchronisation
        if (isset($sanitized['debug_level'])) {
            dgptm_set_module_log_level($module_id, $sanitized['debug_level']);
        }

        return $sanitized;
    }

    /**
     * Assets laden
     */
    public function enqueue_assets($hook) {
        if (strpos($hook, 'dgptm-module-settings') === false) {
            return;
        }

        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_media();

        // Custom CSS
        wp_add_inline_style('wp-admin', '
            .dgptm-settings-form .form-table th {
                width: 200px;
            }
            .dgptm-settings-section {
                background: #fff;
                border: 1px solid #ccd0d4;
                border-radius: 4px;
                padding: 20px;
                margin: 20px 0;
            }
            .dgptm-settings-section h3 {
                margin-top: 0;
                padding-bottom: 10px;
                border-bottom: 1px solid #eee;
            }
            .dgptm-settings-section .description {
                color: #666;
                font-style: italic;
            }
            .dgptm-field-required label::after {
                content: " *";
                color: #d63638;
            }
            .dgptm-module-tabs {
                display: flex;
                flex-wrap: wrap;
                gap: 5px;
                margin-bottom: 20px;
            }
            .dgptm-module-tab {
                display: inline-flex;
                align-items: center;
                gap: 5px;
                padding: 10px 15px;
                background: #f0f0f1;
                border: 1px solid #c3c4c7;
                border-radius: 4px;
                text-decoration: none;
                color: #1d2327;
            }
            .dgptm-module-tab:hover {
                background: #fff;
            }
            .dgptm-module-tab.active {
                background: #2271b1;
                color: #fff;
                border-color: #2271b1;
            }
            .dgptm-module-tab .dashicons {
                font-size: 16px;
                width: 16px;
                height: 16px;
            }
        ');
    }

    /**
     * Rendert die Einstellungsseite
     */
    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die(__('Keine Berechtigung', 'dgptm'));
        }

        $modules = $this->get_registered_modules();
        $active_module = isset($_GET['module']) ? sanitize_text_field($_GET['module']) : '';

        // Falls kein Modul ausgewählt, nimm das erste
        if (empty($active_module) && !empty($modules)) {
            $active_module = array_key_first($modules);
        }

        // Settings speichern
        if (isset($_POST['dgptm_save_module_settings']) && check_admin_referer('dgptm_module_settings_' . $active_module)) {
            $option_name = 'dgptm_module_settings_' . $active_module;
            $input = isset($_POST[$option_name]) ? $_POST[$option_name] : [];
            $_POST['dgptm_module_id'] = $active_module;

            $sanitized = $this->sanitize_settings($input);
            $this->update_settings($active_module, $sanitized);

            echo '<div class="notice notice-success is-dismissible"><p>' . __('Einstellungen gespeichert.', 'dgptm') . '</p></div>';
        }

        ?>
        <div class="wrap">
            <h1><?php _e('Modul-Einstellungen', 'dgptm'); ?></h1>

            <?php if (empty($modules)): ?>
                <div class="notice notice-info">
                    <p><?php _e('Keine Module haben Einstellungen registriert.', 'dgptm'); ?></p>
                    <p><?php _e('Module können ihre Einstellungen mit', 'dgptm'); ?> <code>DGPTM_Module_Settings_Manager::get_instance()->register_module_settings()</code> <?php _e('registrieren.', 'dgptm'); ?></p>
                </div>
            <?php else: ?>

                <!-- Modul-Tabs -->
                <div class="dgptm-module-tabs">
                    <?php foreach ($modules as $module_id => $module): ?>
                        <a href="?page=dgptm-module-settings&module=<?php echo esc_attr($module_id); ?>"
                           class="dgptm-module-tab <?php echo $active_module === $module_id ? 'active' : ''; ?>">
                            <span class="dashicons <?php echo esc_attr($module['icon']); ?>"></span>
                            <?php echo esc_html($module['menu_title']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>

                <?php if (isset($modules[$active_module])): ?>
                    <?php $module = $modules[$active_module]; ?>

                    <div class="dgptm-settings-form">
                        <h2>
                            <span class="dashicons <?php echo esc_attr($module['icon']); ?>"></span>
                            <?php echo esc_html($module['title']); ?>
                        </h2>

                        <?php if (!empty($module['callback']) && is_callable($module['callback'])): ?>
                            <?php call_user_func($module['callback']); ?>
                        <?php else: ?>
                            <?php $this->render_module_settings($active_module, $module); ?>
                        <?php endif; ?>
                    </div>

                <?php endif; ?>

            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Rendert die Settings eines Moduls
     */
    private function render_module_settings($module_id, $module) {
        $option_name = 'dgptm_module_settings_' . $module_id;
        $settings = $this->get_module_settings($module_id);

        // Sektionen nach ID indexieren
        $sections = [];
        foreach ($module['sections'] as $section) {
            $sections[$section['id']] = $section;
        }

        // Felder nach Sektion gruppieren
        $fields_by_section = [];
        $fields_without_section = [];

        foreach ($module['fields'] as $field) {
            if (!empty($field['section']) && isset($sections[$field['section']])) {
                $fields_by_section[$field['section']][] = $field;
            } else {
                $fields_without_section[] = $field;
            }
        }

        ?>
        <form method="post" action="">
            <?php wp_nonce_field('dgptm_module_settings_' . $module_id); ?>
            <input type="hidden" name="dgptm_module_id" value="<?php echo esc_attr($module_id); ?>">

            <?php
            // Felder ohne Sektion
            if (!empty($fields_without_section)):
            ?>
                <div class="dgptm-settings-section">
                    <table class="form-table">
                        <?php foreach ($fields_without_section as $field): ?>
                            <?php $this->render_field($field, $option_name, $settings); ?>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endif; ?>

            <?php
            // Sektionen rendern
            foreach ($sections as $section_id => $section):
                if (!isset($fields_by_section[$section_id])) continue;
            ?>
                <div class="dgptm-settings-section">
                    <h3><?php echo esc_html($section['title']); ?></h3>
                    <?php if (!empty($section['description'])): ?>
                        <p class="description"><?php echo esc_html($section['description']); ?></p>
                    <?php endif; ?>

                    <table class="form-table">
                        <?php foreach ($fields_by_section[$section_id] as $field): ?>
                            <?php $this->render_field($field, $option_name, $settings); ?>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endforeach; ?>

            <p class="submit">
                <button type="submit" name="dgptm_save_module_settings" class="button button-primary">
                    <?php _e('Einstellungen speichern', 'dgptm'); ?>
                </button>
            </p>
        </form>
        <?php
    }

    /**
     * Rendert ein einzelnes Feld
     */
    private function render_field($field, $option_name, $settings) {
        $field_id = $field['id'];
        $field_name = $option_name . '[' . $field_id . ']';
        $value = isset($settings[$field_id]) ? $settings[$field_id] : (isset($field['default']) ? $field['default'] : '');
        $required_class = !empty($field['required']) ? 'dgptm-field-required' : '';

        ?>
        <tr class="<?php echo esc_attr($required_class); ?>">
            <th scope="row">
                <label for="<?php echo esc_attr($field_id); ?>">
                    <?php echo esc_html($field['title']); ?>
                </label>
            </th>
            <td>
                <?php
                switch ($field['type']) {
                    case 'text':
                    case 'email':
                    case 'url':
                        ?>
                        <input type="<?php echo esc_attr($field['type']); ?>"
                               id="<?php echo esc_attr($field_id); ?>"
                               name="<?php echo esc_attr($field_name); ?>"
                               value="<?php echo esc_attr($value); ?>"
                               class="regular-text"
                               <?php echo !empty($field['required']) ? 'required' : ''; ?>
                               <?php echo !empty($field['placeholder']) ? 'placeholder="' . esc_attr($field['placeholder']) . '"' : ''; ?>>
                        <?php
                        break;

                    case 'password':
                        ?>
                        <input type="password"
                               id="<?php echo esc_attr($field_id); ?>"
                               name="<?php echo esc_attr($field_name); ?>"
                               value="<?php echo esc_attr($value); ?>"
                               class="regular-text"
                               autocomplete="new-password"
                               <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                        <?php
                        break;

                    case 'number':
                        ?>
                        <input type="number"
                               id="<?php echo esc_attr($field_id); ?>"
                               name="<?php echo esc_attr($field_name); ?>"
                               value="<?php echo esc_attr($value); ?>"
                               class="small-text"
                               <?php echo isset($field['min']) ? 'min="' . esc_attr($field['min']) . '"' : ''; ?>
                               <?php echo isset($field['max']) ? 'max="' . esc_attr($field['max']) . '"' : ''; ?>
                               <?php echo isset($field['step']) ? 'step="' . esc_attr($field['step']) . '"' : ''; ?>
                               <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                        <?php
                        break;

                    case 'textarea':
                        ?>
                        <textarea id="<?php echo esc_attr($field_id); ?>"
                                  name="<?php echo esc_attr($field_name); ?>"
                                  rows="<?php echo isset($field['rows']) ? esc_attr($field['rows']) : 5; ?>"
                                  class="large-text"
                                  <?php echo !empty($field['required']) ? 'required' : ''; ?>><?php echo esc_textarea($value); ?></textarea>
                        <?php
                        break;

                    case 'checkbox':
                        ?>
                        <label>
                            <input type="checkbox"
                                   id="<?php echo esc_attr($field_id); ?>"
                                   name="<?php echo esc_attr($field_name); ?>"
                                   value="1"
                                   <?php checked($value, true); ?>>
                            <?php echo !empty($field['label']) ? esc_html($field['label']) : ''; ?>
                        </label>
                        <?php
                        break;

                    case 'select':
                        ?>
                        <select id="<?php echo esc_attr($field_id); ?>"
                                name="<?php echo esc_attr($field_name); ?>"
                                <?php echo !empty($field['required']) ? 'required' : ''; ?>>
                            <?php foreach ($field['options'] as $opt_value => $opt_label): ?>
                                <option value="<?php echo esc_attr($opt_value); ?>" <?php selected($value, $opt_value); ?>>
                                    <?php echo esc_html($opt_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php
                        break;

                    case 'radio':
                        foreach ($field['options'] as $opt_value => $opt_label):
                            ?>
                            <label style="display: block; margin-bottom: 5px;">
                                <input type="radio"
                                       name="<?php echo esc_attr($field_name); ?>"
                                       value="<?php echo esc_attr($opt_value); ?>"
                                       <?php checked($value, $opt_value); ?>>
                                <?php echo esc_html($opt_label); ?>
                            </label>
                            <?php
                        endforeach;
                        break;

                    case 'multiselect':
                        $value = is_array($value) ? $value : [];
                        ?>
                        <select id="<?php echo esc_attr($field_id); ?>"
                                name="<?php echo esc_attr($field_name); ?>[]"
                                multiple
                                style="min-height: 100px;">
                            <?php foreach ($field['options'] as $opt_value => $opt_label): ?>
                                <option value="<?php echo esc_attr($opt_value); ?>" <?php selected(in_array($opt_value, $value)); ?>>
                                    <?php echo esc_html($opt_label); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php
                        break;

                    case 'color':
                        ?>
                        <input type="text"
                               id="<?php echo esc_attr($field_id); ?>"
                               name="<?php echo esc_attr($field_name); ?>"
                               value="<?php echo esc_attr($value); ?>"
                               class="dgptm-color-picker"
                               data-default-color="<?php echo isset($field['default']) ? esc_attr($field['default']) : '#000000'; ?>">
                        <script>
                            jQuery(document).ready(function($) {
                                $('#<?php echo esc_js($field_id); ?>').wpColorPicker();
                            });
                        </script>
                        <?php
                        break;

                    case 'editor':
                        wp_editor($value, $field_id, [
                            'textarea_name' => $field_name,
                            'textarea_rows' => isset($field['rows']) ? $field['rows'] : 10,
                            'media_buttons' => isset($field['media_buttons']) ? $field['media_buttons'] : true,
                            'teeny' => isset($field['teeny']) ? $field['teeny'] : false
                        ]);
                        break;

                    case 'media':
                        ?>
                        <div class="dgptm-media-field">
                            <input type="hidden"
                                   id="<?php echo esc_attr($field_id); ?>"
                                   name="<?php echo esc_attr($field_name); ?>"
                                   value="<?php echo esc_attr($value); ?>">
                            <?php if ($value): ?>
                                <div class="dgptm-media-preview" style="margin-bottom: 10px;">
                                    <?php echo wp_get_attachment_image($value, 'thumbnail'); ?>
                                </div>
                            <?php endif; ?>
                            <button type="button" class="button dgptm-media-upload" data-target="<?php echo esc_attr($field_id); ?>">
                                <?php _e('Bild auswählen', 'dgptm'); ?>
                            </button>
                            <?php if ($value): ?>
                                <button type="button" class="button dgptm-media-remove" data-target="<?php echo esc_attr($field_id); ?>">
                                    <?php _e('Entfernen', 'dgptm'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                        <script>
                            jQuery(document).ready(function($) {
                                $('.dgptm-media-upload[data-target="<?php echo esc_js($field_id); ?>"]').click(function(e) {
                                    e.preventDefault();
                                    var frame = wp.media({
                                        title: '<?php _e('Bild auswählen', 'dgptm'); ?>',
                                        button: { text: '<?php _e('Auswählen', 'dgptm'); ?>' },
                                        multiple: false
                                    });
                                    frame.on('select', function() {
                                        var attachment = frame.state().get('selection').first().toJSON();
                                        $('#<?php echo esc_js($field_id); ?>').val(attachment.id);
                                        location.reload();
                                    });
                                    frame.open();
                                });
                                $('.dgptm-media-remove[data-target="<?php echo esc_js($field_id); ?>"]').click(function(e) {
                                    e.preventDefault();
                                    $('#<?php echo esc_js($field_id); ?>').val('');
                                    location.reload();
                                });
                            });
                        </script>
                        <?php
                        break;

                    case 'code':
                        ?>
                        <textarea id="<?php echo esc_attr($field_id); ?>"
                                  name="<?php echo esc_attr($field_name); ?>"
                                  rows="<?php echo isset($field['rows']) ? esc_attr($field['rows']) : 10; ?>"
                                  class="large-text code"
                                  style="font-family: monospace;"><?php echo esc_textarea($value); ?></textarea>
                        <?php
                        break;

                    default:
                        ?>
                        <input type="text"
                               id="<?php echo esc_attr($field_id); ?>"
                               name="<?php echo esc_attr($field_name); ?>"
                               value="<?php echo esc_attr($value); ?>"
                               class="regular-text">
                        <?php
                }

                // Beschreibung
                if (!empty($field['description'])):
                    ?>
                    <p class="description"><?php echo esc_html($field['description']); ?></p>
                    <?php
                endif;
                ?>
            </td>
        </tr>
        <?php
    }
}

// Initialisieren
DGPTM_Module_Settings_Manager::get_instance();

/**
 * Globale Hilfsfunktionen für Module
 */

/**
 * Holt einen Modul-Setting-Wert
 *
 * @param string $module_id Modul-ID
 * @param string $key Setting-Key
 * @param mixed $default Default-Wert
 * @return mixed
 */
function dgptm_get_module_setting($module_id, $key, $default = null) {
    return DGPTM_Module_Settings_Manager::get_instance()->get_setting($module_id, $key, $default);
}

/**
 * Aktualisiert einen Modul-Setting-Wert
 *
 * @param string $module_id Modul-ID
 * @param string $key Setting-Key
 * @param mixed $value Neuer Wert
 * @return bool
 */
function dgptm_update_module_setting($module_id, $key, $value) {
    return DGPTM_Module_Settings_Manager::get_instance()->update_setting($module_id, $key, $value);
}

/**
 * Holt alle Settings eines Moduls
 *
 * @param string $module_id Modul-ID
 * @return array
 */
function dgptm_get_module_settings($module_id) {
    return DGPTM_Module_Settings_Manager::get_instance()->get_module_settings($module_id);
}

/**
 * Registriert Modul-Settings
 *
 * @param array $args Settings-Konfiguration
 * @return bool
 */
function dgptm_register_module_settings($args) {
    return DGPTM_Module_Settings_Manager::get_instance()->register_module_settings($args);
}
