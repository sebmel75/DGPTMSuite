<?php
/**
 * Elementor AJAX Search Widget
 *
 * @package DGPTM_Ajax_Search
 */

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if (!class_exists('DGPTM_Search_Widget')) {

    class DGPTM_Search_Widget extends Widget_Base {

        public function get_name() {
            return 'dgptm-ajax-search';
        }

        public function get_title() {
            return 'DGPTM AJAX Suche';
        }

        public function get_icon() {
            return 'eicon-search';
        }

        public function get_categories() {
            return ['general'];
        }

        public function get_keywords() {
            return ['search', 'suche', 'ajax', 'live', 'dgptm'];
        }

        public function get_style_depends() {
            return ['dgptm-ajax-search'];
        }

        public function get_script_depends() {
            return ['dgptm-ajax-search'];
        }

        protected function register_controls() {

            // Content Section
            $this->start_controls_section(
                'content_section',
                [
                    'label' => 'Inhalt',
                    'tab' => Controls_Manager::TAB_CONTENT,
                ]
            );

            $this->add_control(
                'placeholder',
                [
                    'label' => 'Placeholder-Text',
                    'type' => Controls_Manager::TEXT,
                    'default' => 'Suchen...',
                ]
            );

            // Get all public post types
            $post_types = get_post_types(['public' => true], 'objects');
            $post_type_options = [];
            foreach ($post_types as $pt) {
                $post_type_options[$pt->name] = $pt->labels->name;
            }

            $this->add_control(
                'post_types',
                [
                    'label' => 'Post Types durchsuchen',
                    'type' => Controls_Manager::SELECT2,
                    'multiple' => true,
                    'options' => $post_type_options,
                    'default' => ['post', 'page'],
                ]
            );

            $this->add_control(
                'limit',
                [
                    'label' => 'Max. Ergebnisse',
                    'type' => Controls_Manager::NUMBER,
                    'min' => 3,
                    'max' => 20,
                    'default' => 8,
                ]
            );

            $this->add_control(
                'show_excerpt',
                [
                    'label' => 'Auszug anzeigen',
                    'type' => Controls_Manager::SWITCHER,
                    'label_on' => 'Ja',
                    'label_off' => 'Nein',
                    'return_value' => 'true',
                    'default' => 'true',
                ]
            );

            $this->add_control(
                'show_thumbnail',
                [
                    'label' => 'Vorschaubild anzeigen',
                    'type' => Controls_Manager::SWITCHER,
                    'label_on' => 'Ja',
                    'label_off' => 'Nein',
                    'return_value' => 'true',
                    'default' => 'true',
                ]
            );

            $this->add_control(
                'show_type_badge',
                [
                    'label' => 'Post-Type Badge anzeigen',
                    'type' => Controls_Manager::SWITCHER,
                    'label_on' => 'Ja',
                    'label_off' => 'Nein',
                    'return_value' => 'true',
                    'default' => 'true',
                ]
            );

            $this->add_control(
                'button_text',
                [
                    'label' => 'Button-Text',
                    'type' => Controls_Manager::TEXT,
                    'default' => '',
                    'description' => 'Leer lassen für keinen Button',
                ]
            );

            $this->add_control(
                'style',
                [
                    'label' => 'Stil',
                    'type' => Controls_Manager::SELECT,
                    'default' => 'default',
                    'options' => [
                        'default' => 'Standard',
                        'minimal' => 'Minimal',
                        'boxed' => 'Boxed',
                    ],
                ]
            );

            $this->end_controls_section();

            // Style Section - Input
            $this->start_controls_section(
                'style_input_section',
                [
                    'label' => 'Eingabefeld',
                    'tab' => Controls_Manager::TAB_STYLE,
                ]
            );

            $this->add_control(
                'input_background',
                [
                    'label' => 'Hintergrund',
                    'type' => Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-search-input' => 'background-color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'input_border_color',
                [
                    'label' => 'Rahmenfarbe',
                    'type' => Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-search-input' => 'border-color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'input_text_color',
                [
                    'label' => 'Textfarbe',
                    'type' => Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-search-input' => 'color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'input_border_radius',
                [
                    'label' => 'Eckenradius',
                    'type' => Controls_Manager::SLIDER,
                    'size_units' => ['px'],
                    'range' => [
                        'px' => [
                            'min' => 0,
                            'max' => 50,
                        ],
                    ],
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-search-input' => 'border-radius: {{SIZE}}{{UNIT}};',
                    ],
                ]
            );

            $this->end_controls_section();

            // Style Section - Results
            $this->start_controls_section(
                'style_results_section',
                [
                    'label' => 'Ergebnisse',
                    'tab' => Controls_Manager::TAB_STYLE,
                ]
            );

            $this->add_control(
                'results_background',
                [
                    'label' => 'Hintergrund',
                    'type' => Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-search-results' => 'background-color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'results_title_color',
                [
                    'label' => 'Titel-Farbe',
                    'type' => Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-search-result-title' => 'color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'results_excerpt_color',
                [
                    'label' => 'Auszug-Farbe',
                    'type' => Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-search-result-excerpt' => 'color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'badge_background',
                [
                    'label' => 'Badge Hintergrund',
                    'type' => Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-search-type-badge' => 'background-color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'badge_text_color',
                [
                    'label' => 'Badge Textfarbe',
                    'type' => Controls_Manager::COLOR,
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-search-type-badge' => 'color: {{VALUE}};',
                    ],
                ]
            );

            $this->end_controls_section();
        }

        protected function render() {
            $settings = $this->get_settings_for_display();

            $post_types = is_array($settings['post_types']) ? implode(',', $settings['post_types']) : 'post,page';

            $shortcode_atts = [
                'placeholder' => $settings['placeholder'],
                'post_types' => $post_types,
                'limit' => $settings['limit'],
                'show_excerpt' => $settings['show_excerpt'] === 'true' ? 'true' : 'false',
                'show_thumbnail' => $settings['show_thumbnail'] === 'true' ? 'true' : 'false',
                'show_type_badge' => $settings['show_type_badge'] === 'true' ? 'true' : 'false',
                'button_text' => $settings['button_text'],
                'style' => $settings['style'],
            ];

            $atts_string = '';
            foreach ($shortcode_atts as $key => $value) {
                if (!empty($value) || $value === 'false') {
                    $atts_string .= ' ' . $key . '="' . esc_attr($value) . '"';
                }
            }

            echo do_shortcode('[dgptm_suche' . $atts_string . ']');
        }

        protected function content_template() {
            ?>
            <div class="dgptm-search-preview">
                <p><strong>DGPTM AJAX Suche</strong></p>
                <div style="display: flex; align-items: center; gap: 10px; margin-top: 10px;">
                    <input type="text" placeholder="{{ settings.placeholder }}" style="flex: 1; padding: 12px; border: 2px solid #e0e0e0; border-radius: 8px;">
                    <# if (settings.button_text) { #>
                        <button style="padding: 12px 24px; background: #005791; color: #fff; border: none; border-radius: 8px;">{{ settings.button_text }}</button>
                    <# } #>
                </div>
            </div>
            <?php
        }
    }
}
