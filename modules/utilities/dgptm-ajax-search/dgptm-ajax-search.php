<?php
/**
 * Plugin Name: DGPTM AJAX Search
 * Description: AJAX-Suche mit Live-Ergebnissen. Ersetzt jet-ajax-search.
 * Version: 1.0.0
 * Author: Sebastian Melzer / DGPTM
 * Text Domain: dgptm-ajax-search
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DGPTM_AJAX_SEARCH_VERSION', '1.0.0');
define('DGPTM_AJAX_SEARCH_PATH', plugin_dir_path(__FILE__));
define('DGPTM_AJAX_SEARCH_URL', plugin_dir_url(__FILE__));

if (!class_exists('DGPTM_Ajax_Search')) {

    class DGPTM_Ajax_Search {

        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            // Assets registrieren
            add_action('wp_enqueue_scripts', [$this, 'register_assets']);

            // AJAX Handler
            add_action('wp_ajax_dgptm_ajax_search', [$this, 'ajax_search']);
            add_action('wp_ajax_nopriv_dgptm_ajax_search', [$this, 'ajax_search']);

            // Shortcode
            add_shortcode('dgptm_suche', [$this, 'render_shortcode']);
            add_shortcode('dgptm_search', [$this, 'render_shortcode']);

            // Elementor Widget
            add_action('elementor/widgets/register', [$this, 'register_widget']);
        }

        /**
         * Registriert Frontend-Assets
         */
        public function register_assets() {
            wp_register_style(
                'dgptm-ajax-search',
                DGPTM_AJAX_SEARCH_URL . 'assets/css/search.css',
                [],
                DGPTM_AJAX_SEARCH_VERSION
            );

            wp_register_script(
                'dgptm-ajax-search',
                DGPTM_AJAX_SEARCH_URL . 'assets/js/search.js',
                ['jquery'],
                DGPTM_AJAX_SEARCH_VERSION,
                true
            );

            wp_localize_script('dgptm-ajax-search', 'dgptmSearch', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dgptm_search_nonce'),
                'minChars' => 3,
                'strings' => [
                    'searching' => __('Suche...', 'dgptm-ajax-search'),
                    'noResults' => __('Keine Ergebnisse gefunden', 'dgptm-ajax-search'),
                    'error' => __('Fehler bei der Suche', 'dgptm-ajax-search'),
                    'viewAll' => __('Alle Ergebnisse anzeigen', 'dgptm-ajax-search'),
                ]
            ]);
        }

        /**
         * AJAX Search Handler
         */
        public function ajax_search() {
            check_ajax_referer('dgptm_search_nonce', 'nonce');

            $query = sanitize_text_field($_POST['query'] ?? '');
            $post_types = isset($_POST['post_types']) ? array_map('sanitize_key', (array)$_POST['post_types']) : ['post', 'page'];
            $limit = intval($_POST['limit'] ?? 10);
            $show_excerpt = filter_var($_POST['show_excerpt'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $show_thumbnail = filter_var($_POST['show_thumbnail'] ?? true, FILTER_VALIDATE_BOOLEAN);

            if (strlen($query) < 2) {
                wp_send_json_success(['results' => [], 'total' => 0]);
            }

            // Sanitize post types
            $allowed_post_types = get_post_types(['public' => true], 'names');
            $post_types = array_intersect($post_types, $allowed_post_types);

            if (empty($post_types)) {
                $post_types = ['post', 'page'];
            }

            $args = [
                'post_type' => $post_types,
                'posts_per_page' => $limit,
                'post_status' => 'publish',
                's' => $query,
                'orderby' => 'relevance',
            ];

            $search_query = new WP_Query($args);
            $results = [];

            if ($search_query->have_posts()) {
                while ($search_query->have_posts()) {
                    $search_query->the_post();

                    $result = [
                        'id' => get_the_ID(),
                        'title' => get_the_title(),
                        'url' => get_permalink(),
                        'post_type' => get_post_type(),
                        'post_type_label' => get_post_type_object(get_post_type())->labels->singular_name,
                    ];

                    if ($show_excerpt) {
                        $result['excerpt'] = wp_trim_words(get_the_excerpt(), 20, '...');
                    }

                    if ($show_thumbnail && has_post_thumbnail()) {
                        $result['thumbnail'] = get_the_post_thumbnail_url(get_the_ID(), 'thumbnail');
                    }

                    $results[] = $result;
                }
                wp_reset_postdata();
            }

            // Count total results
            $count_args = $args;
            $count_args['posts_per_page'] = -1;
            $count_args['fields'] = 'ids';
            $count_query = new WP_Query($count_args);
            $total = $count_query->found_posts;

            wp_send_json_success([
                'results' => $results,
                'total' => $total,
                'query' => $query,
                'search_url' => home_url('/?s=' . urlencode($query))
            ]);
        }

        /**
         * Shortcode [dgptm_suche]
         */
        public function render_shortcode($atts) {
            $atts = shortcode_atts([
                'placeholder' => __('Suchen...', 'dgptm-ajax-search'),
                'post_types' => 'post,page',
                'limit' => 8,
                'show_excerpt' => 'true',
                'show_thumbnail' => 'true',
                'show_type_badge' => 'true',
                'button_text' => '',
                'style' => 'default', // default, minimal, boxed
            ], $atts);

            wp_enqueue_style('dgptm-ajax-search');
            wp_enqueue_script('dgptm-ajax-search');

            $post_types = array_map('trim', explode(',', $atts['post_types']));
            $unique_id = 'dgptm-search-' . uniqid();

            ob_start();
            ?>
            <div class="dgptm-search-wrapper dgptm-search-<?php echo esc_attr($atts['style']); ?>" id="<?php echo esc_attr($unique_id); ?>">
                <form class="dgptm-search-form" role="search" data-post-types="<?php echo esc_attr(implode(',', $post_types)); ?>" data-limit="<?php echo esc_attr($atts['limit']); ?>" data-show-excerpt="<?php echo esc_attr($atts['show_excerpt']); ?>" data-show-thumbnail="<?php echo esc_attr($atts['show_thumbnail']); ?>" data-show-type-badge="<?php echo esc_attr($atts['show_type_badge']); ?>">
                    <div class="dgptm-search-input-wrapper">
                        <input type="search"
                               class="dgptm-search-input"
                               placeholder="<?php echo esc_attr($atts['placeholder']); ?>"
                               autocomplete="off"
                               aria-label="<?php echo esc_attr($atts['placeholder']); ?>">
                        <span class="dgptm-search-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor">
                                <path d="M15.5 14h-.79l-.28-.27A6.471 6.471 0 0 0 16 9.5 6.5 6.5 0 1 0 9.5 16c1.61 0 3.09-.59 4.23-1.57l.27.28v.79l5 4.99L20.49 19l-4.99-5zm-6 0C7.01 14 5 11.99 5 9.5S7.01 5 9.5 5 14 7.01 14 9.5 11.99 14 9.5 14z"/>
                            </svg>
                        </span>
                        <span class="dgptm-search-spinner" style="display: none;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="20" height="20" fill="currentColor" class="dgptm-spinner-icon">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.42 0-8-3.58-8-8s3.58-8 8-8 8 3.58 8 8-3.58 8-8 8z" opacity=".3"/>
                                <path d="M12 2v4c4.42 0 8 3.58 8 8h4c0-6.63-5.37-12-12-12z"/>
                            </svg>
                        </span>
                        <?php if (!empty($atts['button_text'])) : ?>
                            <button type="submit" class="dgptm-search-button">
                                <?php echo esc_html($atts['button_text']); ?>
                            </button>
                        <?php endif; ?>
                    </div>
                </form>
                <div class="dgptm-search-results" style="display: none;"></div>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Registriert Elementor Widget
         */
        public function register_widget($widgets_manager) {
            require_once DGPTM_AJAX_SEARCH_PATH . 'widgets/class-search-widget.php';

            if (class_exists('DGPTM_Search_Widget')) {
                $widgets_manager->register(new DGPTM_Search_Widget());
            }
        }
    }
}

// Prevent double initialization
if (!isset($GLOBALS['dgptm_ajax_search_initialized'])) {
    $GLOBALS['dgptm_ajax_search_initialized'] = true;
    DGPTM_Ajax_Search::get_instance();
}
