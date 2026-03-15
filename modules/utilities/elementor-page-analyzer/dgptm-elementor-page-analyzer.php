<?php
/**
 * Plugin Name: DGPTM - Elementor Page Analyzer
 * Description: Analysiert Elementor-Seiten semantisch und erzeugt ein Blueprint mit Sichtbarkeitsbedingungen, Shortcodes und Berechtigungsmatrix
 * Version: 1.0.0
 * Author: Sebastian Melzer
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('DGPTM_Elementor_Page_Analyzer')) {
    class DGPTM_Elementor_Page_Analyzer {

        private static $instance = null;
        private $plugin_path;
        private $plugin_url;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->plugin_path = plugin_dir_path(__FILE__);
            $this->plugin_url = plugin_dir_url(__FILE__);

            require_once $this->plugin_path . 'includes/class-page-analyzer.php';

            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);

            // AJAX handlers
            add_action('wp_ajax_dgptm_analyzer_get_pages', [$this, 'ajax_get_pages']);
            add_action('wp_ajax_dgptm_analyzer_analyze', [$this, 'ajax_analyze']);
        }

        public function add_admin_menu() {
            add_submenu_page(
                'dgptm-suite',
                'Page Analyzer',
                'Page Analyzer',
                'manage_options',
                'dgptm-page-analyzer',
                [$this, 'render_admin_page']
            );
        }

        public function enqueue_assets($hook) {
            if ('dgptm-suite_page_dgptm-page-analyzer' !== $hook) {
                return;
            }

            wp_enqueue_style(
                'dgptm-page-analyzer',
                $this->plugin_url . 'assets/css/admin.css',
                [],
                '1.0.0'
            );

            wp_enqueue_script(
                'dgptm-page-analyzer',
                $this->plugin_url . 'assets/js/admin.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('dgptm-page-analyzer', 'dgptmAnalyzer', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('dgptm_analyzer_nonce'),
            ]);
        }

        public function render_admin_page() {
            require_once $this->plugin_path . 'views/admin-page.php';
        }

        /**
         * AJAX: List all Elementor pages
         */
        public function ajax_get_pages() {
            check_ajax_referer('dgptm_analyzer_nonce', 'nonce');
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $query = new WP_Query([
                'post_type'      => ['page', 'post'],
                'posts_per_page' => -1,
                'meta_query'     => [[
                    'key'     => '_elementor_edit_mode',
                    'value'   => 'builder',
                    'compare' => '=',
                ]],
                'orderby' => 'title',
                'order'   => 'ASC',
            ]);

            $pages = [];
            foreach ($query->posts as $post) {
                $pages[] = [
                    'id'    => $post->ID,
                    'title' => $post->post_title,
                    'type'  => $post->post_type,
                    'url'   => get_permalink($post->ID),
                ];
            }

            wp_send_json_success(['pages' => $pages]);
        }

        /**
         * AJAX: Analyze a page and save blueprint
         */
        public function ajax_analyze() {
            check_ajax_referer('dgptm_analyzer_nonce', 'nonce');
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $page_id = absint($_POST['page_id'] ?? 0);
            if (!$page_id) {
                wp_send_json_error(['message' => 'Keine Seiten-ID']);
            }

            $analyzer = new DGPTM_Page_Analyzer();
            $blueprint = $analyzer->analyze($page_id);

            if (is_wp_error($blueprint)) {
                wp_send_json_error(['message' => $blueprint->get_error_message()]);
            }

            // Save to guides/ directory as JSON
            $guides_dir = DGPTM_SUITE_PATH . 'guides/';
            if (!is_dir($guides_dir)) {
                wp_mkdir_p($guides_dir);
            }

            $post = get_post($page_id);
            $filename = 'page-blueprint-' . sanitize_file_name($post->post_name ?: $page_id) . '.json';
            $filepath = $guides_dir . $filename;

            $json = wp_json_encode($blueprint, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            file_put_contents($filepath, $json);

            wp_send_json_success([
                'message'   => 'Blueprint gespeichert: guides/' . $filename,
                'filename'  => $filename,
                'filepath'  => 'guides/' . $filename,
                'blueprint' => $blueprint,
            ]);
        }
    }
}

// Prevent double initialization
if (!isset($GLOBALS['dgptm_elementor_page_analyzer_initialized'])) {
    $GLOBALS['dgptm_elementor_page_analyzer_initialized'] = true;
    DGPTM_Elementor_Page_Analyzer::get_instance();
}
