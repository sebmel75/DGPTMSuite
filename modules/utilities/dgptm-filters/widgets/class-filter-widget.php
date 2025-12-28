<?php
/**
 * Elementor Filter Widget
 *
 * @package DGPTM_Filters
 */

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if (!class_exists('DGPTM_Filter_Widget')) {

    class DGPTM_Filter_Widget extends Widget_Base {

        public function get_name() {
            return 'dgptm-filter';
        }

        public function get_title() {
            return 'DGPTM Filter';
        }

        public function get_icon() {
            return 'eicon-filter';
        }

        public function get_categories() {
            return ['general'];
        }

        public function get_keywords() {
            return ['filter', 'suche', 'ajax', 'smart', 'dgptm'];
        }

        public function get_style_depends() {
            return ['dgptm-filters'];
        }

        public function get_script_depends() {
            return ['dgptm-filters'];
        }

        protected function register_controls() {

            // Content Section
            $this->start_controls_section(
                'content_section',
                [
                    'label' => 'Filter-Einstellungen',
                    'tab' => Controls_Manager::TAB_CONTENT,
                ]
            );

            // Get all public post types
            $post_types = get_post_types(['public' => true], 'objects');
            $post_type_options = [];
            foreach ($post_types as $pt) {
                $post_type_options[$pt->name] = $pt->labels->name;
            }

            $this->add_control(
                'post_type',
                [
                    'label' => 'Post Type',
                    'type' => Controls_Manager::SELECT,
                    'options' => $post_type_options,
                    'default' => 'post',
                ]
            );

            // Get all taxonomies
            $taxonomies = get_taxonomies(['public' => true], 'objects');
            $taxonomy_options = [];
            foreach ($taxonomies as $tax) {
                $taxonomy_options[$tax->name] = $tax->labels->name;
            }

            $this->add_control(
                'taxonomies',
                [
                    'label' => 'Taxonomie-Filter',
                    'type' => Controls_Manager::SELECT2,
                    'multiple' => true,
                    'options' => $taxonomy_options,
                    'default' => [],
                    'description' => 'Wähle Taxonomien für Dropdown-Filter',
                ]
            );

            $this->add_control(
                'show_search',
                [
                    'label' => 'Suchfeld anzeigen',
                    'type' => Controls_Manager::SWITCHER,
                    'label_on' => 'Ja',
                    'label_off' => 'Nein',
                    'return_value' => 'true',
                    'default' => 'true',
                ]
            );

            $this->add_control(
                'show_reset',
                [
                    'label' => 'Zurücksetzen-Button',
                    'type' => Controls_Manager::SWITCHER,
                    'label_on' => 'Ja',
                    'label_off' => 'Nein',
                    'return_value' => 'true',
                    'default' => 'true',
                ]
            );

            $this->add_control(
                'target_id',
                [
                    'label' => 'Ziel-Element ID',
                    'type' => Controls_Manager::TEXT,
                    'description' => 'ID des Ergebnis-Containers (ohne #)',
                    'default' => '',
                ]
            );

            $this->add_control(
                'layout',
                [
                    'label' => 'Layout',
                    'type' => Controls_Manager::SELECT,
                    'default' => 'horizontal',
                    'options' => [
                        'horizontal' => 'Horizontal',
                        'vertical' => 'Vertikal',
                        'inline' => 'Inline',
                    ],
                ]
            );

            $this->end_controls_section();

            // Results Section
            $this->start_controls_section(
                'results_section',
                [
                    'label' => 'Ergebnis-Einstellungen',
                    'tab' => Controls_Manager::TAB_CONTENT,
                ]
            );

            $this->add_control(
                'show_results',
                [
                    'label' => 'Ergebnisse anzeigen',
                    'type' => Controls_Manager::SWITCHER,
                    'label_on' => 'Ja',
                    'label_off' => 'Nein',
                    'return_value' => 'true',
                    'default' => 'true',
                    'description' => 'Zeigt Ergebnisse direkt unter dem Filter an',
                ]
            );

            $this->add_control(
                'per_page',
                [
                    'label' => 'Einträge pro Seite',
                    'type' => Controls_Manager::NUMBER,
                    'min' => 1,
                    'max' => 50,
                    'default' => 12,
                    'condition' => [
                        'show_results' => 'true',
                    ],
                ]
            );

            $this->add_control(
                'columns',
                [
                    'label' => 'Spalten',
                    'type' => Controls_Manager::SELECT,
                    'default' => '3',
                    'options' => [
                        '1' => '1',
                        '2' => '2',
                        '3' => '3',
                        '4' => '4',
                    ],
                    'condition' => [
                        'show_results' => 'true',
                    ],
                ]
            );

            $this->add_control(
                'orderby',
                [
                    'label' => 'Sortierung',
                    'type' => Controls_Manager::SELECT,
                    'default' => 'date',
                    'options' => [
                        'date' => 'Datum',
                        'title' => 'Titel',
                        'menu_order' => 'Reihenfolge',
                        'rand' => 'Zufällig',
                    ],
                    'condition' => [
                        'show_results' => 'true',
                    ],
                ]
            );

            $this->add_control(
                'order',
                [
                    'label' => 'Reihenfolge',
                    'type' => Controls_Manager::SELECT,
                    'default' => 'DESC',
                    'options' => [
                        'DESC' => 'Absteigend',
                        'ASC' => 'Aufsteigend',
                    ],
                    'condition' => [
                        'show_results' => 'true',
                    ],
                ]
            );

            $this->add_control(
                'show_pagination',
                [
                    'label' => 'Seitennavigation',
                    'type' => Controls_Manager::SWITCHER,
                    'label_on' => 'Ja',
                    'label_off' => 'Nein',
                    'return_value' => 'true',
                    'default' => 'true',
                    'condition' => [
                        'show_results' => 'true',
                    ],
                ]
            );

            $this->end_controls_section();

            // Style Section
            $this->start_controls_section(
                'style_section',
                [
                    'label' => 'Stil',
                    'tab' => Controls_Manager::TAB_STYLE,
                ]
            );

            $this->add_control(
                'input_background',
                [
                    'label' => 'Eingabefeld Hintergrund',
                    'type' => Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-filter-input, {{WRAPPER}} .dgptm-filter-select' => 'background-color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'input_border_color',
                [
                    'label' => 'Eingabefeld Rahmen',
                    'type' => Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-filter-input, {{WRAPPER}} .dgptm-filter-select' => 'border-color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'button_background',
                [
                    'label' => 'Button Hintergrund',
                    'type' => Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-filter-prev, {{WRAPPER}} .dgptm-filter-next' => 'background-color: {{VALUE}};',
                    ],
                ]
            );

            $this->end_controls_section();
        }

        protected function render() {
            $settings = $this->get_settings_for_display();

            $taxonomies = is_array($settings['taxonomies']) ? implode(',', $settings['taxonomies']) : '';

            // Render filter
            $filter_atts = [
                'post_type' => $settings['post_type'],
                'taxonomy' => $taxonomies,
                'show_search' => $settings['show_search'] === 'true' ? 'true' : 'false',
                'show_reset' => $settings['show_reset'] === 'true' ? 'true' : 'false',
                'target' => $settings['target_id'],
                'style' => $settings['layout'],
            ];

            $filter_string = '';
            foreach ($filter_atts as $key => $value) {
                if (!empty($value) || $value === 'false') {
                    $filter_string .= ' ' . $key . '="' . esc_attr($value) . '"';
                }
            }

            echo do_shortcode('[dgptm_filter' . $filter_string . ']');

            // Render results if enabled
            if ($settings['show_results'] === 'true') {
                $results_atts = [
                    'post_type' => $settings['post_type'],
                    'per_page' => $settings['per_page'],
                    'columns' => $settings['columns'],
                    'orderby' => $settings['orderby'],
                    'order' => $settings['order'],
                    'show_pagination' => $settings['show_pagination'] === 'true' ? 'true' : 'false',
                ];

                $results_string = '';
                foreach ($results_atts as $key => $value) {
                    $results_string .= ' ' . $key . '="' . esc_attr($value) . '"';
                }

                echo do_shortcode('[dgptm_filter_results' . $results_string . ']');
            }
        }

        protected function content_template() {
            ?>
            <div class="dgptm-filter-preview">
                <p><strong>DGPTM Filter</strong></p>
                <p>Post Type: {{ settings.post_type }}</p>
                <p>Filter werden im Frontend angezeigt.</p>
            </div>
            <?php
        }
    }
}
