<?php
/**
 * Plugin Name: DGPTM Timeline
 * Description: Timeline-Anzeige mit CPT timeline_entry und Elementor-Widget. Ersetzt jet-timeline.
 * Version: 1.0.0
 * Author: Sebastian Melzer / DGPTM
 * Text Domain: dgptm-timeline
 */

if (!defined('ABSPATH')) {
    exit;
}

// Konstanten
define('DGPTM_TIMELINE_VERSION', '1.0.0');
define('DGPTM_TIMELINE_PATH', plugin_dir_path(__FILE__));
define('DGPTM_TIMELINE_URL', plugin_dir_url(__FILE__));

if (!class_exists('DGPTM_Timeline')) {

    class DGPTM_Timeline {

        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            // CPT registrieren (frühe Priorität)
            add_action('init', [$this, 'register_post_type'], 5);

            // ACF Felder registrieren
            add_action('acf/init', [$this, 'register_acf_fields']);

            // Elementor Widget registrieren
            add_action('elementor/widgets/register', [$this, 'register_widgets']);

            // Assets registrieren
            add_action('wp_enqueue_scripts', [$this, 'register_assets']);

            // Shortcode registrieren
            add_shortcode('dgptm_timeline', [$this, 'render_shortcode']);

            // Rewrite Rules flushen
            add_action('admin_init', [$this, 'maybe_flush_rewrite_rules']);
        }

        /**
         * Registriert den CPT timeline_entry
         */
        public function register_post_type() {
            // Prüfen ob bereits von JetEngine registriert
            if (post_type_exists('timeline_entry')) {
                return;
            }

            $labels = [
                'name'                  => 'Timeline-Einträge',
                'singular_name'         => 'Timeline-Eintrag',
                'menu_name'             => 'Timeline',
                'name_admin_bar'        => 'Timeline-Eintrag',
                'add_new'               => 'Neu erstellen',
                'add_new_item'          => 'Neuen Eintrag erstellen',
                'new_item'              => 'Neuer Eintrag',
                'edit_item'             => 'Eintrag bearbeiten',
                'view_item'             => 'Eintrag ansehen',
                'all_items'             => 'Alle Einträge',
                'search_items'          => 'Einträge durchsuchen',
                'not_found'             => 'Keine Einträge gefunden.',
                'not_found_in_trash'    => 'Keine Einträge im Papierkorb.',
            ];

            $args = [
                'labels'             => $labels,
                'public'             => true,
                'publicly_queryable' => true,
                'show_ui'            => true,
                'show_in_menu'       => true,
                'query_var'          => true,
                'rewrite'            => ['slug' => 'timeline', 'with_front' => false],
                'capability_type'    => 'post',
                'has_archive'        => true,
                'hierarchical'       => false,
                'menu_position'      => 28,
                'menu_icon'          => 'dashicons-clock',
                'supports'           => ['title', 'editor', 'thumbnail', 'custom-fields', 'excerpt'],
                'show_in_rest'       => true,
                'rest_base'          => 'timeline-entries',
            ];

            register_post_type('timeline_entry', $args);
        }

        /**
         * Registriert ACF-Felder für Timeline
         */
        public function register_acf_fields() {
            if (!function_exists('acf_add_local_field_group')) {
                return;
            }

            acf_add_local_field_group([
                'key' => 'group_timeline_entry_fields',
                'title' => 'Timeline-Daten',
                'fields' => [
                    [
                        'key' => 'field_timeline_datum',
                        'label' => 'Datum',
                        'name' => 'timeline_datum',
                        'type' => 'date_picker',
                        'display_format' => 'd.m.Y',
                        'return_format' => 'Y-m-d',
                        'first_day' => 1,
                    ],
                    [
                        'key' => 'field_timeline_jahr',
                        'label' => 'Jahr',
                        'name' => 'timeline_jahr',
                        'type' => 'number',
                        'instructions' => 'Das Jahr für die Timeline-Darstellung',
                        'min' => 1900,
                        'max' => 2100,
                    ],
                    [
                        'key' => 'field_timeline_icon',
                        'label' => 'Icon',
                        'name' => 'timeline_icon',
                        'type' => 'text',
                        'instructions' => 'Dashicons-Klasse (z.B. dashicons-star-filled)',
                        'default_value' => 'dashicons-marker',
                    ],
                    [
                        'key' => 'field_timeline_farbe',
                        'label' => 'Farbe',
                        'name' => 'timeline_farbe',
                        'type' => 'color_picker',
                        'default_value' => '#005791',
                    ],
                    [
                        'key' => 'field_timeline_link',
                        'label' => 'Link',
                        'name' => 'timeline_link',
                        'type' => 'url',
                        'instructions' => 'Optionaler Link für mehr Informationen',
                    ],
                    [
                        'key' => 'field_timeline_sortierung',
                        'label' => 'Sortierung',
                        'name' => 'timeline_sortierung',
                        'type' => 'number',
                        'instructions' => 'Niedrigere Zahlen werden zuerst angezeigt',
                        'default_value' => 0,
                    ],
                ],
                'location' => [
                    [
                        [
                            'param' => 'post_type',
                            'operator' => '==',
                            'value' => 'timeline_entry',
                        ],
                    ],
                ],
                'menu_order' => 0,
                'position' => 'normal',
                'style' => 'default',
                'label_placement' => 'top',
            ]);
        }

        /**
         * Registriert das Elementor-Widget
         */
        public function register_widgets($widgets_manager) {
            require_once DGPTM_TIMELINE_PATH . 'widgets/class-timeline-widget.php';

            if (class_exists('DGPTM_Timeline_Widget')) {
                $widgets_manager->register(new DGPTM_Timeline_Widget());
            }
        }

        /**
         * Registriert Frontend-Assets
         */
        public function register_assets() {
            wp_register_style(
                'dgptm-timeline',
                DGPTM_TIMELINE_URL . 'assets/css/timeline.css',
                [],
                DGPTM_TIMELINE_VERSION
            );

            wp_register_script(
                'dgptm-timeline',
                DGPTM_TIMELINE_URL . 'assets/js/timeline.js',
                ['jquery'],
                DGPTM_TIMELINE_VERSION,
                true
            );
        }

        /**
         * Shortcode [dgptm_timeline]
         *
         * @param array $atts Shortcode-Attribute
         * @return string HTML-Output
         */
        public function render_shortcode($atts) {
            $atts = shortcode_atts([
                'anzahl' => -1,
                'order' => 'ASC',
                'orderby' => 'timeline_sortierung',
                'layout' => 'vertical', // vertical oder horizontal
                'show_year' => 'true',
            ], $atts);

            wp_enqueue_style('dgptm-timeline');
            wp_enqueue_script('dgptm-timeline');

            $args = [
                'post_type' => 'timeline_entry',
                'posts_per_page' => intval($atts['anzahl']),
                'post_status' => 'publish',
            ];

            // Sortierung
            if ($atts['orderby'] === 'timeline_sortierung') {
                $args['meta_key'] = 'timeline_sortierung';
                $args['orderby'] = 'meta_value_num';
            } elseif ($atts['orderby'] === 'timeline_jahr') {
                $args['meta_key'] = 'timeline_jahr';
                $args['orderby'] = 'meta_value_num';
            } elseif ($atts['orderby'] === 'timeline_datum') {
                $args['meta_key'] = 'timeline_datum';
                $args['orderby'] = 'meta_value';
            } else {
                $args['orderby'] = $atts['orderby'];
            }

            $args['order'] = strtoupper($atts['order']);

            $entries = get_posts($args);

            if (empty($entries)) {
                return '<p class="dgptm-timeline-empty">Keine Timeline-Einträge gefunden.</p>';
            }

            $layout_class = $atts['layout'] === 'horizontal' ? 'dgptm-timeline-horizontal' : 'dgptm-timeline-vertical';
            $show_year = $atts['show_year'] === 'true';

            ob_start();
            ?>
            <div class="dgptm-timeline <?php echo esc_attr($layout_class); ?>">
                <div class="dgptm-timeline-line"></div>
                <?php
                $current_year = null;
                foreach ($entries as $entry) :
                    $jahr = get_field('timeline_jahr', $entry->ID);
                    $datum = get_field('timeline_datum', $entry->ID);
                    $icon = get_field('timeline_icon', $entry->ID) ?: 'dashicons-marker';
                    $farbe = get_field('timeline_farbe', $entry->ID) ?: '#005791';
                    $link = get_field('timeline_link', $entry->ID);

                    // Jahr-Separator
                    if ($show_year && $jahr && $jahr !== $current_year) :
                        $current_year = $jahr;
                        ?>
                        <div class="dgptm-timeline-year">
                            <span><?php echo esc_html($jahr); ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="dgptm-timeline-entry">
                        <div class="dgptm-timeline-marker" style="background-color: <?php echo esc_attr($farbe); ?>;">
                            <span class="dashicons <?php echo esc_attr($icon); ?>"></span>
                        </div>
                        <div class="dgptm-timeline-content">
                            <?php if ($datum) : ?>
                                <div class="dgptm-timeline-date">
                                    <?php echo esc_html(date_i18n('j. F Y', strtotime($datum))); ?>
                                </div>
                            <?php endif; ?>
                            <h3 class="dgptm-timeline-title">
                                <?php if ($link) : ?>
                                    <a href="<?php echo esc_url($link); ?>"><?php echo esc_html($entry->post_title); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html($entry->post_title); ?>
                                <?php endif; ?>
                            </h3>
                            <?php if (!empty($entry->post_content)) : ?>
                                <div class="dgptm-timeline-text">
                                    <?php echo wp_kses_post($entry->post_content); ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Flush Rewrite Rules bei Bedarf
         */
        public function maybe_flush_rewrite_rules() {
            $flush_key = 'dgptm_timeline_flush_done_100';

            if (get_option($flush_key) !== 'yes') {
                flush_rewrite_rules();
                update_option($flush_key, 'yes');
            }
        }
    }
}

// Prevent double initialization
if (!isset($GLOBALS['dgptm_timeline_initialized'])) {
    $GLOBALS['dgptm_timeline_initialized'] = true;
    DGPTM_Timeline::get_instance();
}
