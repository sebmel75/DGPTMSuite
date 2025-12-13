<?php
/**
 * Module Generator für DGPTM Plugin Suite
 * Erstellt neue Module direkt im Admin
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Module_Generator {

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
     * Neues Modul erstellen
     *
     * @param array $config Modul-Konfiguration
     * @return array|WP_Error
     */
    public function create_module($config) {
        // Validierung
        $validation = $this->validate_config($config);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Bereinige ID (slug-format)
        $config['id'] = sanitize_title($config['id']);

        // Pfade
        $category_path = DGPTM_SUITE_PATH . 'modules/' . $config['category'] . '/';
        $module_path = $category_path . $config['id'] . '/';

        // Prüfe ob Modul bereits existiert
        if (is_dir($module_path)) {
            return new WP_Error('module_exists', __('A module with this ID already exists.', 'dgptm-suite'));
        }

        // Erstelle Verzeichnis
        if (!wp_mkdir_p($module_path)) {
            return new WP_Error('mkdir_failed', __('Could not create module directory.', 'dgptm-suite'));
        }

        // Erstelle module.json
        $json_result = $this->create_module_json($module_path, $config);
        if (is_wp_error($json_result)) {
            return $json_result;
        }

        // Erstelle Haupt-PHP-Datei
        $php_result = $this->create_main_file($module_path, $config);
        if (is_wp_error($php_result)) {
            return $php_result;
        }

        // Erstelle README.md
        $this->create_readme($module_path, $config);

        // Optional: Assets-Verzeichnisse
        if (!empty($config['needs_assets'])) {
            wp_mkdir_p($module_path . 'assets/css/');
            wp_mkdir_p($module_path . 'assets/js/');
            wp_mkdir_p($module_path . 'assets/images/');
        }

        // Optional: Includes-Verzeichnis
        if (!empty($config['needs_includes'])) {
            wp_mkdir_p($module_path . 'includes/');
        }

        return [
            'success' => true,
            'module_id' => $config['id'],
            'path' => $module_path,
            'message' => __('Module created successfully!', 'dgptm-suite')
        ];
    }

    /**
     * Konfiguration validieren
     */
    private function validate_config($config) {
        $required = ['id', 'name', 'description', 'version', 'category', 'main_file'];

        foreach ($required as $field) {
            if (empty($config[$field])) {
                return new WP_Error('missing_field', sprintf(__('Required field missing: %s', 'dgptm-suite'), $field));
            }
        }

        // Validiere Kategorie
        $valid_categories = $this->get_available_categories();
        if (!in_array($config['category'], $valid_categories)) {
            return new WP_Error('invalid_category', __('Invalid category. Available: ', 'dgptm-suite') . implode(', ', $valid_categories));
        }

        // Validiere ID (nur Kleinbuchstaben, Zahlen, Bindestriche)
        if (!preg_match('/^[a-z0-9-]+$/', $config['id'])) {
            return new WP_Error('invalid_id', __('Module ID can only contain lowercase letters, numbers and hyphens.', 'dgptm-suite'));
        }

        // Validiere Version
        if (!preg_match('/^\d+\.\d+(\.\d+)?$/', $config['version'])) {
            return new WP_Error('invalid_version', __('Invalid version format. Use format: 1.0.0', 'dgptm-suite'));
        }

        return true;
    }

    /**
     * module.json erstellen
     */
    private function create_module_json($module_path, $config) {
        $json_data = [
            'id' => $config['id'],
            'name' => $config['name'],
            'description' => $config['description'],
            'version' => $config['version'],
            'author' => $config['author'] ?? get_bloginfo('name'),
            'main_file' => $config['main_file'],
            'dependencies' => $config['dependencies'] ?? [],
            'optional_dependencies' => $config['optional_dependencies'] ?? [],
            'wp_dependencies' => [
                'plugins' => $config['wp_plugins'] ?? [],
            ],
            'requires_php' => $config['requires_php'] ?? '7.4',
            'requires_wp' => $config['requires_wp'] ?? '5.8',
            'category' => $config['category'],
            'icon' => $config['icon'] ?? 'dashicons-admin-plugins',
            'active' => false,
            'can_export' => true,
        ];

        $json_content = json_encode($json_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if (file_put_contents($module_path . 'module.json', $json_content) === false) {
            return new WP_Error('json_write_failed', __('Could not write module.json', 'dgptm-suite'));
        }

        return true;
    }

    /**
     * Haupt-PHP-Datei erstellen
     */
    private function create_main_file($module_path, $config) {
        $template = $this->get_php_template($config);

        if (file_put_contents($module_path . $config['main_file'], $template) === false) {
            return new WP_Error('php_write_failed', __('Could not write main PHP file', 'dgptm-suite'));
        }

        return true;
    }

    /**
     * PHP-Template generieren
     */
    private function get_php_template($config) {
        $class_name = str_replace('-', '_', ucwords($config['id'], '-'));

        $template = "<?php\n";
        $template .= "/**\n";
        $template .= " * Plugin Name: " . $config['name'] . "\n";
        $template .= " * Description: " . $config['description'] . "\n";
        $template .= " * Version: " . $config['version'] . "\n";
        $template .= " * Author: " . ($config['author'] ?? get_bloginfo('name')) . "\n";
        $template .= " * Text Domain: " . $config['id'] . "\n";
        $template .= " */\n\n";

        $template .= "if (!defined('ABSPATH')) {\n";
        $template .= "    exit;\n";
        $template .= "}\n\n";

        // Einfache Klassen-Struktur
        $template .= "class " . $class_name . " {\n\n";
        $template .= "    private static \$instance = null;\n\n";

        $template .= "    public static function get_instance() {\n";
        $template .= "        if (null === self::\$instance) {\n";
        $template .= "            self::\$instance = new self();\n";
        $template .= "        }\n";
        $template .= "        return self::\$instance;\n";
        $template .= "    }\n\n";

        $template .= "    private function __construct() {\n";
        $template .= "        \$this->init();\n";
        $template .= "    }\n\n";

        $template .= "    private function init() {\n";
        $template .= "        // Add your initialization code here\n";
        $template .= "        add_action('init', [\$this, 'register_hooks']);\n";
        $template .= "    }\n\n";

        $template .= "    public function register_hooks() {\n";
        $template .= "        // Register your WordPress hooks here\n";
        $template .= "        // Example: add_action('wp_enqueue_scripts', [\$this, 'enqueue_assets']);\n";
        $template .= "    }\n\n";

        // Beispiel-Methoden je nach Modul-Typ
        if (strpos($config['category'], 'business') !== false || strpos($config['category'], 'content') !== false) {
            $template .= "    /**\n";
            $template .= "     * Register custom post type (if needed)\n";
            $template .= "     */\n";
            $template .= "    public function register_post_type() {\n";
            $template .= "        // register_post_type(...)\n";
            $template .= "    }\n\n";
        }

        $template .= "    /**\n";
        $template .= "     * Enqueue assets\n";
        $template .= "     */\n";
        $template .= "    public function enqueue_assets() {\n";
        $template .= "        // wp_enqueue_style(...)\n";
        $template .= "        // wp_enqueue_script(...)\n";
        $template .= "    }\n";

        $template .= "}\n\n";

        $template .= "// Initialize module\n";
        $template .= $class_name . "::get_instance();\n";

        return $template;
    }

    /**
     * README.md erstellen
     */
    private function create_readme($module_path, $config) {
        $readme = "# " . $config['name'] . "\n\n";
        $readme .= $config['description'] . "\n\n";
        $readme .= "**Version:** " . $config['version'] . "\n";
        $readme .= "**Author:** " . ($config['author'] ?? get_bloginfo('name')) . "\n";
        $readme .= "**Category:** " . $config['category'] . "\n\n";

        $readme .= "## Installation\n\n";
        $readme .= "This module is part of the DGPTM Plugin Suite.\n\n";

        if (!empty($config['dependencies'])) {
            $readme .= "### Dependencies\n\n";
            $readme .= "This module requires:\n";
            foreach ($config['dependencies'] as $dep) {
                $readme .= "- " . $dep . "\n";
            }
            $readme .= "\n";
        }

        if (!empty($config['wp_plugins'])) {
            $readme .= "### WordPress Plugin Dependencies\n\n";
            foreach ($config['wp_plugins'] as $plugin) {
                $readme .= "- " . $plugin . "\n";
            }
            $readme .= "\n";
        }

        $readme .= "## Usage\n\n";
        $readme .= "Add your usage instructions here.\n\n";

        $readme .= "## Changelog\n\n";
        $readme .= "### Version " . $config['version'] . "\n";
        $readme .= "- Initial release\n";

        file_put_contents($module_path . 'README.md', $readme);
    }

    /**
     * Modul-Template generieren (für fortgeschrittene Nutzer)
     */
    public function generate_template($template_type, $module_path, $config) {
        $templates = [
            'shortcode' => $this->get_shortcode_template($config),
            'widget' => $this->get_widget_template($config),
            'rest-api' => $this->get_rest_api_template($config),
            'custom-post-type' => $this->get_cpt_template($config),
            'admin-page' => $this->get_admin_page_template($config),
        ];

        if (isset($templates[$template_type])) {
            $filename = $module_path . 'includes/' . $template_type . '.php';
            wp_mkdir_p(dirname($filename));
            file_put_contents($filename, $templates[$template_type]);
            return true;
        }

        return false;
    }

    /**
     * Shortcode-Template
     */
    private function get_shortcode_template($config) {
        $shortcode_name = str_replace('-', '_', $config['id']);

        return "<?php\n\n" .
               "// Shortcode: [" . $shortcode_name . "]\n" .
               "add_shortcode('" . $shortcode_name . "', function(\$atts) {\n" .
               "    \$atts = shortcode_atts([\n" .
               "        'example' => 'default',\n" .
               "    ], \$atts);\n\n" .
               "    ob_start();\n" .
               "    ?>\n" .
               "    <div class=\"" . $config['id'] . "-shortcode\">\n" .
               "        <!-- Your shortcode output here -->\n" .
               "    </div>\n" .
               "    <?php\n" .
               "    return ob_get_clean();\n" .
               "});\n";
    }

    /**
     * REST API-Template
     */
    private function get_rest_api_template($config) {
        $namespace = str_replace('-', '/', $config['id']);

        return "<?php\n\n" .
               "add_action('rest_api_init', function() {\n" .
               "    register_rest_route('" . $namespace . "/v1', '/endpoint', [\n" .
               "        'methods' => 'GET',\n" .
               "        'callback' => function(\$request) {\n" .
               "            return ['message' => 'Hello from " . $config['name'] . "'];\n" .
               "        },\n" .
               "        'permission_callback' => '__return_true',\n" .
               "    ]);\n" .
               "});\n";
    }

    /**
     * Custom Post Type Template
     */
    private function get_cpt_template($config) {
        $cpt_slug = $config['id'];

        return "<?php\n\n" .
               "add_action('init', function() {\n" .
               "    register_post_type('" . $cpt_slug . "', [\n" .
               "        'labels' => [\n" .
               "            'name' => __('" . $config['name'] . "', '" . $config['id'] . "'),\n" .
               "            'singular_name' => __('" . $config['name'] . "', '" . $config['id'] . "'),\n" .
               "        ],\n" .
               "        'public' => true,\n" .
               "        'has_archive' => true,\n" .
               "        'supports' => ['title', 'editor', 'thumbnail'],\n" .
               "        'show_in_rest' => true,\n" .
               "    ]);\n" .
               "});\n";
    }

    /**
     * Admin Page Template
     */
    private function get_admin_page_template($config) {
        return "<?php\n\n" .
               "add_action('admin_menu', function() {\n" .
               "    add_menu_page(\n" .
               "        __('" . $config['name'] . "', '" . $config['id'] . "'),\n" .
               "        __('" . $config['name'] . "', '" . $config['id'] . "'),\n" .
               "        'manage_options',\n" .
               "        '" . $config['id'] . "',\n" .
               "        function() {\n" .
               "            ?>\n" .
               "            <div class=\"wrap\">\n" .
               "                <h1><?php echo esc_html(get_admin_page_title()); ?></h1>\n" .
               "                <!-- Your admin page content here -->\n" .
               "            </div>\n" .
               "            <?php\n" .
               "        }\n" .
               "    );\n" .
               "});\n";
    }

    /**
     * Widget-Template
     */
    private function get_widget_template($config) {
        $class_name = str_replace('-', '_', ucwords($config['id'], '-')) . '_Widget';

        return "<?php\n\n" .
               "class " . $class_name . " extends WP_Widget {\n" .
               "    public function __construct() {\n" .
               "        parent::__construct(\n" .
               "            '" . $config['id'] . "_widget',\n" .
               "            __('" . $config['name'] . " Widget', '" . $config['id'] . "'),\n" .
               "            ['description' => __('" . $config['description'] . "', '" . $config['id'] . "')]\n" .
               "        );\n" .
               "    }\n\n" .
               "    public function widget(\$args, \$instance) {\n" .
               "        echo \$args['before_widget'];\n" .
               "        // Widget output\n" .
               "        echo \$args['after_widget'];\n" .
               "    }\n" .
               "}\n\n" .
               "add_action('widgets_init', function() {\n" .
               "    register_widget('" . $class_name . "');\n" .
               "});\n";
    }

    /**
     * Verfügbare Kategorien abrufen
     *
     * @return array Liste der Kategorie-IDs
     */
    private function get_available_categories() {
        // Standard-Kategorien (immer verfügbar)
        $default_categories = [
            'core-infrastructure',
            'business',
            'payment',
            'auth',
            'media',
            'content',
            'acf-tools',
            'utilities'
        ];

        // Benutzerdefinierte Kategorien aus Datenbank
        $stored_categories = get_option('dgptm_suite_categories', []);
        $custom_categories = array_keys($stored_categories);

        // Kombinieren und deduplizieren
        return array_unique(array_merge($default_categories, $custom_categories));
    }
}
