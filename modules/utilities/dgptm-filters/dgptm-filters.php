<?php
/**
 * Plugin Name: DGPTM AJAX Filters
 * Description: AJAX-Filter für Post-Listen. Ersetzt JetSmartFilters.
 * Version: 1.0.0
 * Author: Sebastian Melzer / DGPTM
 * Text Domain: dgptm-filters
 */

if (!defined('ABSPATH')) {
    exit;
}

define('DGPTM_FILTERS_VERSION', '1.0.0');
define('DGPTM_FILTERS_PATH', plugin_dir_path(__FILE__));
define('DGPTM_FILTERS_URL', plugin_dir_url(__FILE__));

if (!class_exists('DGPTM_Filters')) {

    class DGPTM_Filters {

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
            add_action('wp_ajax_dgptm_filter_posts', [$this, 'ajax_filter_posts']);
            add_action('wp_ajax_nopriv_dgptm_filter_posts', [$this, 'ajax_filter_posts']);

            // Shortcodes
            add_shortcode('dgptm_filter', [$this, 'render_filter_shortcode']);
            add_shortcode('dgptm_filter_results', [$this, 'render_results_shortcode']);

            // Elementor Widget
            add_action('elementor/widgets/register', [$this, 'register_widgets']);
        }

        /**
         * Registriert Frontend-Assets
         */
        public function register_assets() {
            wp_register_style(
                'dgptm-filters',
                DGPTM_FILTERS_URL . 'assets/css/filters.css',
                [],
                DGPTM_FILTERS_VERSION
            );

            wp_register_script(
                'dgptm-filters',
                DGPTM_FILTERS_URL . 'assets/js/filters.js',
                ['jquery'],
                DGPTM_FILTERS_VERSION,
                true
            );

            wp_localize_script('dgptm-filters', 'dgptmFilters', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dgptm_filters_nonce'),
                'strings' => [
                    'loading' => __('Laden...', 'dgptm-filters'),
                    'noResults' => __('Keine Ergebnisse gefunden', 'dgptm-filters'),
                    'showingResults' => __('%d Ergebnisse', 'dgptm-filters'),
                    'all' => __('Alle', 'dgptm-filters'),
                ]
            ]);
        }

        /**
         * AJAX Filter Handler
         */
        public function ajax_filter_posts() {
            check_ajax_referer('dgptm_filters_nonce', 'nonce');

            $post_type = sanitize_key($_POST['post_type'] ?? 'post');
            $taxonomy_filters = isset($_POST['taxonomies']) ? $this->sanitize_taxonomy_filters($_POST['taxonomies']) : [];
            $meta_filters = isset($_POST['meta']) ? $this->sanitize_meta_filters($_POST['meta']) : [];
            $search = sanitize_text_field($_POST['search'] ?? '');
            $page = intval($_POST['page'] ?? 1);
            $per_page = intval($_POST['per_page'] ?? 12);
            $orderby = sanitize_key($_POST['orderby'] ?? 'date');
            $order = strtoupper(sanitize_key($_POST['order'] ?? 'DESC'));
            $template = sanitize_text_field($_POST['template'] ?? 'default');

            // Build query args
            $args = [
                'post_type' => $post_type,
                'posts_per_page' => $per_page,
                'paged' => $page,
                'post_status' => 'publish',
            ];

            // Ordering
            if ($orderby === 'title') {
                $args['orderby'] = 'title';
                $args['order'] = $order;
            } elseif ($orderby === 'menu_order') {
                $args['orderby'] = 'menu_order';
                $args['order'] = $order;
            } elseif (strpos($orderby, 'meta_') === 0) {
                $meta_key = str_replace('meta_', '', $orderby);
                $args['meta_key'] = $meta_key;
                $args['orderby'] = 'meta_value';
                $args['order'] = $order;
            } else {
                $args['orderby'] = 'date';
                $args['order'] = $order;
            }

            // Search
            if (!empty($search)) {
                $args['s'] = $search;
            }

            // Taxonomy query
            if (!empty($taxonomy_filters)) {
                $tax_query = ['relation' => 'AND'];
                foreach ($taxonomy_filters as $taxonomy => $terms) {
                    if (!empty($terms)) {
                        $tax_query[] = [
                            'taxonomy' => $taxonomy,
                            'field' => 'term_id',
                            'terms' => $terms,
                            'operator' => 'IN',
                        ];
                    }
                }
                if (count($tax_query) > 1) {
                    $args['tax_query'] = $tax_query;
                }
            }

            // Meta query
            if (!empty($meta_filters)) {
                $meta_query = ['relation' => 'AND'];
                foreach ($meta_filters as $key => $value) {
                    if (!empty($value)) {
                        $meta_query[] = [
                            'key' => $key,
                            'value' => $value,
                            'compare' => is_array($value) ? 'IN' : '=',
                        ];
                    }
                }
                if (count($meta_query) > 1) {
                    $args['meta_query'] = $meta_query;
                }
            }

            $query = new WP_Query($args);

            $results = [];
            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $results[] = $this->format_post_data(get_the_ID(), $template);
                }
                wp_reset_postdata();
            }

            wp_send_json_success([
                'results' => $results,
                'total' => $query->found_posts,
                'pages' => $query->max_num_pages,
                'current_page' => $page,
                'html' => $this->render_results_html($results, $template),
            ]);
        }

        /**
         * Formatiert Post-Daten für AJAX Response
         */
        private function format_post_data($post_id, $template = 'default') {
            $data = [
                'id' => $post_id,
                'title' => get_the_title($post_id),
                'url' => get_permalink($post_id),
                'excerpt' => get_the_excerpt($post_id),
                'date' => get_the_date('', $post_id),
                'post_type' => get_post_type($post_id),
            ];

            if (has_post_thumbnail($post_id)) {
                $data['thumbnail'] = get_the_post_thumbnail_url($post_id, 'medium');
                $data['thumbnail_large'] = get_the_post_thumbnail_url($post_id, 'large');
            }

            // Add ACF fields if available
            if (function_exists('get_fields')) {
                $fields = get_fields($post_id);
                if ($fields) {
                    $data['acf'] = $fields;
                }
            }

            return $data;
        }

        /**
         * Rendert HTML für Ergebnisse
         */
        private function render_results_html($results, $template) {
            if (empty($results)) {
                return '<div class="dgptm-filter-no-results">' . esc_html__('Keine Ergebnisse gefunden', 'dgptm-filters') . '</div>';
            }

            ob_start();
            ?>
            <div class="dgptm-filter-grid">
                <?php foreach ($results as $item) : ?>
                    <article class="dgptm-filter-item">
                        <?php if (!empty($item['thumbnail'])) : ?>
                            <div class="dgptm-filter-item-image">
                                <a href="<?php echo esc_url($item['url']); ?>">
                                    <img src="<?php echo esc_url($item['thumbnail']); ?>" alt="<?php echo esc_attr($item['title']); ?>" loading="lazy">
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="dgptm-filter-item-content">
                            <h3 class="dgptm-filter-item-title">
                                <a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['title']); ?></a>
                            </h3>
                            <?php if (!empty($item['excerpt'])) : ?>
                                <div class="dgptm-filter-item-excerpt">
                                    <?php echo wp_kses_post($item['excerpt']); ?>
                                </div>
                            <?php endif; ?>
                            <div class="dgptm-filter-item-meta">
                                <span class="dgptm-filter-item-date"><?php echo esc_html($item['date']); ?></span>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Sanitize taxonomy filters
         */
        private function sanitize_taxonomy_filters($filters) {
            $sanitized = [];
            if (is_array($filters)) {
                foreach ($filters as $taxonomy => $terms) {
                    $taxonomy = sanitize_key($taxonomy);
                    if (taxonomy_exists($taxonomy)) {
                        $sanitized[$taxonomy] = array_map('intval', (array)$terms);
                    }
                }
            }
            return $sanitized;
        }

        /**
         * Sanitize meta filters
         */
        private function sanitize_meta_filters($filters) {
            $sanitized = [];
            if (is_array($filters)) {
                foreach ($filters as $key => $value) {
                    $sanitized[sanitize_key($key)] = is_array($value)
                        ? array_map('sanitize_text_field', $value)
                        : sanitize_text_field($value);
                }
            }
            return $sanitized;
        }

        /**
         * Filter Shortcode [dgptm_filter]
         */
        public function render_filter_shortcode($atts) {
            $atts = shortcode_atts([
                'post_type' => 'post',
                'taxonomy' => '',
                'meta_fields' => '',
                'show_search' => 'true',
                'show_reset' => 'true',
                'target' => '', // ID of results container
                'style' => 'horizontal', // horizontal, vertical, inline
            ], $atts);

            wp_enqueue_style('dgptm-filters');
            wp_enqueue_script('dgptm-filters');

            $unique_id = 'dgptm-filter-' . uniqid();
            $taxonomies = array_filter(array_map('trim', explode(',', $atts['taxonomy'])));
            $meta_fields = array_filter(array_map('trim', explode(',', $atts['meta_fields'])));

            ob_start();
            ?>
            <div class="dgptm-filter-wrapper dgptm-filter-<?php echo esc_attr($atts['style']); ?>"
                 id="<?php echo esc_attr($unique_id); ?>"
                 data-post-type="<?php echo esc_attr($atts['post_type']); ?>"
                 data-target="<?php echo esc_attr($atts['target']); ?>">

                <form class="dgptm-filter-form">
                    <?php if ($atts['show_search'] === 'true') : ?>
                        <div class="dgptm-filter-field dgptm-filter-search">
                            <input type="text"
                                   name="search"
                                   class="dgptm-filter-input"
                                   placeholder="<?php esc_attr_e('Suchen...', 'dgptm-filters'); ?>">
                        </div>
                    <?php endif; ?>

                    <?php foreach ($taxonomies as $taxonomy) :
                        if (!taxonomy_exists($taxonomy)) continue;
                        $tax_obj = get_taxonomy($taxonomy);
                        $terms = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => true]);
                        if (empty($terms) || is_wp_error($terms)) continue;
                    ?>
                        <div class="dgptm-filter-field dgptm-filter-taxonomy" data-taxonomy="<?php echo esc_attr($taxonomy); ?>">
                            <label class="dgptm-filter-label"><?php echo esc_html($tax_obj->labels->singular_name); ?></label>
                            <select name="taxonomy_<?php echo esc_attr($taxonomy); ?>" class="dgptm-filter-select">
                                <option value=""><?php echo esc_html__('Alle', 'dgptm-filters'); ?></option>
                                <?php foreach ($terms as $term) : ?>
                                    <option value="<?php echo esc_attr($term->term_id); ?>">
                                        <?php echo esc_html($term->name); ?> (<?php echo esc_html($term->count); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($atts['show_reset'] === 'true') : ?>
                        <div class="dgptm-filter-field dgptm-filter-actions">
                            <button type="reset" class="dgptm-filter-reset">
                                <?php esc_html_e('Zurücksetzen', 'dgptm-filters'); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </form>

                <div class="dgptm-filter-status">
                    <span class="dgptm-filter-count"></span>
                    <span class="dgptm-filter-loading" style="display: none;">
                        <?php esc_html_e('Laden...', 'dgptm-filters'); ?>
                    </span>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Results Shortcode [dgptm_filter_results]
         */
        public function render_results_shortcode($atts) {
            $atts = shortcode_atts([
                'post_type' => 'post',
                'per_page' => 12,
                'orderby' => 'date',
                'order' => 'DESC',
                'columns' => 3,
                'show_pagination' => 'true',
            ], $atts);

            wp_enqueue_style('dgptm-filters');
            wp_enqueue_script('dgptm-filters');

            $unique_id = 'dgptm-results-' . uniqid();

            // Initial query
            $args = [
                'post_type' => $atts['post_type'],
                'posts_per_page' => intval($atts['per_page']),
                'post_status' => 'publish',
                'orderby' => $atts['orderby'],
                'order' => strtoupper($atts['order']),
            ];

            $query = new WP_Query($args);
            $results = [];

            if ($query->have_posts()) {
                while ($query->have_posts()) {
                    $query->the_post();
                    $results[] = $this->format_post_data(get_the_ID());
                }
                wp_reset_postdata();
            }

            ob_start();
            ?>
            <div class="dgptm-filter-results-wrapper"
                 id="<?php echo esc_attr($unique_id); ?>"
                 data-post-type="<?php echo esc_attr($atts['post_type']); ?>"
                 data-per-page="<?php echo esc_attr($atts['per_page']); ?>"
                 data-orderby="<?php echo esc_attr($atts['orderby']); ?>"
                 data-order="<?php echo esc_attr($atts['order']); ?>"
                 data-columns="<?php echo esc_attr($atts['columns']); ?>"
                 style="--dgptm-columns: <?php echo intval($atts['columns']); ?>;">

                <div class="dgptm-filter-results-container">
                    <?php echo $this->render_results_html($results, 'default'); ?>
                </div>

                <?php if ($atts['show_pagination'] === 'true' && $query->max_num_pages > 1) : ?>
                    <div class="dgptm-filter-pagination"
                         data-total-pages="<?php echo esc_attr($query->max_num_pages); ?>"
                         data-current-page="1">
                        <button class="dgptm-filter-prev" disabled>
                            <?php esc_html_e('Zurück', 'dgptm-filters'); ?>
                        </button>
                        <span class="dgptm-filter-page-info">
                            <span class="current">1</span> / <span class="total"><?php echo esc_html($query->max_num_pages); ?></span>
                        </span>
                        <button class="dgptm-filter-next">
                            <?php esc_html_e('Weiter', 'dgptm-filters'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Registriert Elementor Widget
         */
        public function register_widgets($widgets_manager) {
            require_once DGPTM_FILTERS_PATH . 'widgets/class-filter-widget.php';

            if (class_exists('DGPTM_Filter_Widget')) {
                $widgets_manager->register(new DGPTM_Filter_Widget());
            }
        }
    }
}

// Prevent double initialization
if (!isset($GLOBALS['dgptm_filters_initialized'])) {
    $GLOBALS['dgptm_filters_initialized'] = true;
    DGPTM_Filters::get_instance();
}
