<?php
/**
 * Elementor Timeline Widget
 *
 * @package DGPTM_Timeline
 */

if (!defined('ABSPATH')) {
    exit;
}

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if (!class_exists('DGPTM_Timeline_Widget')) {

    class DGPTM_Timeline_Widget extends Widget_Base {

        public function get_name() {
            return 'dgptm-timeline';
        }

        public function get_title() {
            return 'DGPTM Timeline';
        }

        public function get_icon() {
            return 'eicon-time-line';
        }

        public function get_categories() {
            return ['general'];
        }

        public function get_keywords() {
            return ['timeline', 'chronik', 'zeit', 'history', 'dgptm'];
        }

        public function get_style_depends() {
            return ['dgptm-timeline'];
        }

        public function get_script_depends() {
            return ['dgptm-timeline'];
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
                'anzahl',
                [
                    'label' => 'Anzahl Einträge',
                    'type' => Controls_Manager::NUMBER,
                    'min' => -1,
                    'max' => 100,
                    'step' => 1,
                    'default' => -1,
                    'description' => '-1 für alle Einträge',
                ]
            );

            $this->add_control(
                'orderby',
                [
                    'label' => 'Sortierung nach',
                    'type' => Controls_Manager::SELECT,
                    'default' => 'timeline_sortierung',
                    'options' => [
                        'timeline_sortierung' => 'Sortierung (Feld)',
                        'timeline_jahr' => 'Jahr',
                        'timeline_datum' => 'Datum',
                        'title' => 'Titel',
                        'date' => 'Veröffentlichungsdatum',
                    ],
                ]
            );

            $this->add_control(
                'order',
                [
                    'label' => 'Reihenfolge',
                    'type' => Controls_Manager::SELECT,
                    'default' => 'ASC',
                    'options' => [
                        'ASC' => 'Aufsteigend',
                        'DESC' => 'Absteigend',
                    ],
                ]
            );

            $this->add_control(
                'layout',
                [
                    'label' => 'Layout',
                    'type' => Controls_Manager::SELECT,
                    'default' => 'vertical',
                    'options' => [
                        'vertical' => 'Vertikal',
                        'horizontal' => 'Horizontal',
                    ],
                ]
            );

            $this->add_control(
                'show_year',
                [
                    'label' => 'Jahres-Separator anzeigen',
                    'type' => Controls_Manager::SWITCHER,
                    'label_on' => 'Ja',
                    'label_off' => 'Nein',
                    'return_value' => 'true',
                    'default' => 'true',
                ]
            );

            $this->end_controls_section();

            // Style Section - Line
            $this->start_controls_section(
                'style_line_section',
                [
                    'label' => 'Linie',
                    'tab' => Controls_Manager::TAB_STYLE,
                ]
            );

            $this->add_control(
                'line_color',
                [
                    'label' => 'Linienfarbe',
                    'type' => Controls_Manager::COLOR,
                    'default' => '#e0e0e0',
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-timeline-line' => 'background-color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'line_width',
                [
                    'label' => 'Linienbreite',
                    'type' => Controls_Manager::SLIDER,
                    'size_units' => ['px'],
                    'range' => [
                        'px' => [
                            'min' => 1,
                            'max' => 10,
                        ],
                    ],
                    'default' => [
                        'unit' => 'px',
                        'size' => 3,
                    ],
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-timeline-vertical .dgptm-timeline-line' => 'width: {{SIZE}}{{UNIT}};',
                        '{{WRAPPER}} .dgptm-timeline-horizontal .dgptm-timeline-line' => 'height: {{SIZE}}{{UNIT}};',
                    ],
                ]
            );

            $this->end_controls_section();

            // Style Section - Marker
            $this->start_controls_section(
                'style_marker_section',
                [
                    'label' => 'Marker',
                    'tab' => Controls_Manager::TAB_STYLE,
                ]
            );

            $this->add_control(
                'marker_size',
                [
                    'label' => 'Marker-Größe',
                    'type' => Controls_Manager::SLIDER,
                    'size_units' => ['px'],
                    'range' => [
                        'px' => [
                            'min' => 20,
                            'max' => 60,
                        ],
                    ],
                    'default' => [
                        'unit' => 'px',
                        'size' => 40,
                    ],
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-timeline-marker' => 'width: {{SIZE}}{{UNIT}}; height: {{SIZE}}{{UNIT}};',
                    ],
                ]
            );

            $this->add_control(
                'marker_icon_color',
                [
                    'label' => 'Icon-Farbe',
                    'type' => Controls_Manager::COLOR,
                    'default' => '#ffffff',
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-timeline-marker .dashicons' => 'color: {{VALUE}};',
                    ],
                ]
            );

            $this->end_controls_section();

            // Style Section - Content
            $this->start_controls_section(
                'style_content_section',
                [
                    'label' => 'Inhalt',
                    'tab' => Controls_Manager::TAB_STYLE,
                ]
            );

            $this->add_control(
                'title_color',
                [
                    'label' => 'Titel-Farbe',
                    'type' => Controls_Manager::COLOR,
                    'default' => '#23282d',
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-timeline-title' => 'color: {{VALUE}};',
                        '{{WRAPPER}} .dgptm-timeline-title a' => 'color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'date_color',
                [
                    'label' => 'Datum-Farbe',
                    'type' => Controls_Manager::COLOR,
                    'default' => '#666666',
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-timeline-date' => 'color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'text_color',
                [
                    'label' => 'Text-Farbe',
                    'type' => Controls_Manager::COLOR,
                    'default' => '#333333',
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-timeline-text' => 'color: {{VALUE}};',
                    ],
                ]
            );

            $this->add_control(
                'year_color',
                [
                    'label' => 'Jahr-Badge Farbe',
                    'type' => Controls_Manager::COLOR,
                    'default' => '#005791',
                    'selectors' => [
                        '{{WRAPPER}} .dgptm-timeline-year span' => 'background-color: {{VALUE}};',
                    ],
                ]
            );

            $this->end_controls_section();
        }

        protected function render() {
            $settings = $this->get_settings_for_display();

            $shortcode_atts = [
                'anzahl' => $settings['anzahl'],
                'order' => $settings['order'],
                'orderby' => $settings['orderby'],
                'layout' => $settings['layout'],
                'show_year' => $settings['show_year'] === 'true' ? 'true' : 'false',
            ];

            $atts_string = '';
            foreach ($shortcode_atts as $key => $value) {
                $atts_string .= ' ' . $key . '="' . esc_attr($value) . '"';
            }

            echo do_shortcode('[dgptm_timeline' . $atts_string . ']');
        }

        protected function content_template() {
            ?>
            <div class="dgptm-timeline-preview">
                <p><strong>DGPTM Timeline</strong></p>
                <p>Timeline wird im Frontend dargestellt.</p>
            </div>
            <?php
        }
    }
}
