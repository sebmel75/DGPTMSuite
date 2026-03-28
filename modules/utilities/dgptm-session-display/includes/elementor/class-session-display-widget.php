<?php
/**
 * Elementor Widget für Session Display
 */

if (!defined('ABSPATH')) {
    exit;
}

// Nur laden wenn Elementor verfügbar ist
if (!class_exists('\Elementor\Widget_Base')) {
    return;
}

class DGPTM_Session_Display_Widget extends \Elementor\Widget_Base {

    /**
     * Widget-Name
     */
    public function get_name() {
        return 'dgptm_session_display';
    }

    /**
     * Widget-Titel
     */
    public function get_title() {
        return 'Session Display';
    }

    /**
     * Widget-Icon
     */
    public function get_icon() {
        return 'eicon-calendar';
    }

    /**
     * Widget-Kategorie
     */
    public function get_categories() {
        return ['general'];
    }

    /**
     * Widget-Keywords
     */
    public function get_keywords() {
        return ['session', 'event', 'conference', 'dgptm', 'display'];
    }

    /**
     * Widget-Controls registrieren
     */
    protected function register_controls() {

        // Content Section
        $this->start_controls_section(
            'content_section',
            [
                'label' => 'Einstellungen',
                'tab' => \Elementor\Controls_Manager::TAB_CONTENT,
            ]
        );

        // Display-Typ
        $this->add_control(
            'display_type',
            [
                'label' => 'Display-Typ',
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'single',
                'options' => [
                    'single' => 'Einzelraum',
                    'overview' => 'Übersicht',
                ],
            ]
        );

        // Raum-Auswahl (nur bei Einzelraum)
        $this->add_control(
            'room',
            [
                'label' => 'Raum',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'Raum 1',
                'placeholder' => 'Raum 1',
                'condition' => [
                    'display_type' => 'single',
                ],
            ]
        );

        // Session-Typ (nur bei Einzelraum)
        $this->add_control(
            'session_type',
            [
                'label' => 'Anzuzeigende Sessions',
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'current',
                'options' => [
                    'current' => 'Nur aktuelle Session',
                    'next' => 'Nur nächste Session',
                    'both' => 'Aktuelle und nächste Session',
                ],
                'condition' => [
                    'display_type' => 'single',
                ],
            ]
        );

        // Etage (nur bei Übersicht)
        $this->add_control(
            'floor',
            [
                'label' => 'Etage',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => 'EG, OG1, OG2, ...',
                'description' => 'Leer lassen für alle Etagen',
                'condition' => [
                    'display_type' => 'overview',
                ],
            ]
        );

        // Räume (nur bei Übersicht)
        $this->add_control(
            'rooms',
            [
                'label' => 'Räume',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => 'Raum 1, Raum 2, Raum 3',
                'description' => 'Kommagetrennte Liste. Leer = alle Räume',
                'condition' => [
                    'display_type' => 'overview',
                ],
            ]
        );

        // Layout (nur bei Übersicht)
        $this->add_control(
            'layout',
            [
                'label' => 'Layout',
                'type' => \Elementor\Controls_Manager::SELECT,
                'default' => 'grid',
                'options' => [
                    'grid' => 'Grid',
                    'list' => 'Liste',
                ],
                'condition' => [
                    'display_type' => 'overview',
                ],
            ]
        );

        // Sponsoren anzeigen
        $this->add_control(
            'show_sponsors',
            [
                'label' => 'Sponsoren anzeigen',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'Ja',
                'label_off' => 'Nein',
                'return_value' => 'true',
                'default' => 'true',
            ]
        );

        // Auto-Refresh
        $this->add_control(
            'auto_refresh',
            [
                'label' => 'Automatische Aktualisierung',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'An',
                'label_off' => 'Aus',
                'return_value' => 'auto',
                'default' => 'auto',
            ]
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style_section',
            [
                'label' => 'Stil',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            ]
        );

        // Hintergrundfarbe
        $this->add_control(
            'background_color',
            [
                'label' => 'Hintergrundfarbe',
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#667eea',
                'selectors' => [
                    '{{WRAPPER}} .dgptm-session-display' => 'background: {{VALUE}};',
                ],
            ]
        );

        // Textfarbe
        $this->add_control(
            'text_color',
            [
                'label' => 'Textfarbe',
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => [
                    '{{WRAPPER}} .dgptm-session-display' => 'color: {{VALUE}};',
                ],
            ]
        );

        // Schriftgröße Titel
        $this->add_control(
            'title_font_size',
            [
                'label' => 'Titel-Schriftgröße',
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 20,
                        'max' => 100,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 42,
                ],
                'selectors' => [
                    '{{WRAPPER}} .session-title' => 'font-size: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        // Border Radius
        $this->add_control(
            'border_radius',
            [
                'label' => 'Border Radius',
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => ['px'],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 50,
                        'step' => 1,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 20,
                ],
                'selectors' => [
                    '{{WRAPPER}} .dgptm-session-display' => 'border-radius: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->end_controls_section();
    }

    /**
     * Widget rendern
     */
    protected function render() {
        $settings = $this->get_settings_for_display();

        $display_type = $settings['display_type'];

        if ($display_type === 'single') {
            $this->render_single_display($settings);
        } else {
            $this->render_overview($settings);
        }
    }

    /**
     * Einzelraum-Display rendern
     */
    private function render_single_display($settings) {
        $atts = [
            'room' => $settings['room'],
            'type' => $settings['session_type'],
            'show_sponsors' => $settings['show_sponsors'],
            'refresh' => $settings['auto_refresh'],
        ];

        $display_controller = new DGPTM_Session_Display_Controller();
        $display_controller->render_display($atts);
    }

    /**
     * Übersicht rendern
     */
    private function render_overview($settings) {
        $atts = [
            'floor' => $settings['floor'],
            'rooms' => $settings['rooms'],
            'layout' => $settings['layout'],
            'show_time' => 'true',
        ];

        $display_controller = new DGPTM_Session_Display_Controller();
        $display_controller->render_overview($atts);
    }

    /**
     * Content Template (für Live-Editor)
     */
    protected function content_template() {
        ?>
        <#
        if ( settings.display_type === 'single' ) {
            #>
            <div class="dgptm-session-display" data-room="{{ settings.room }}" data-type="{{ settings.session_type }}">
                <div class="session-display-header">
                    <div class="room-name">{{ settings.room }}</div>
                    <div class="current-time">12:00</div>
                </div>
                <div class="session-display-content">
                    <div class="current-session">
                        <div class="session-badge">Jetzt</div>
                        <div class="session-time">
                            <span class="time-start">14:00</span>
                            <span class="time-separator">-</span>
                            <span class="time-end">15:30</span>
                        </div>
                        <h2 class="session-title">Beispiel Session-Titel</h2>
                        <div class="session-description">
                            Dies ist ein Beispiel für eine Session-Beschreibung.
                        </div>
                    </div>
                </div>
                <div class="session-display-footer">
                    <div class="dgptm-logo">DGPTM Jahrestagung</div>
                    <div class="last-update">Stand: 2025-11-20 12:00:00</div>
                </div>
            </div>
            <#
        } else {
            #>
            <div class="dgptm-session-overview layout-{{ settings.layout }}">
                <div class="overview-header">
                    <h2>Sessionübersicht</h2>
                    <div class="current-time">12:00</div>
                </div>
                <div class="overview-content">
                    <div class="room-card status-active">
                        <div class="room-header">
                            <h3 class="room-name">Raum 1</h3>
                            <span class="room-status-badge">active</span>
                        </div>
                        <div class="room-content">
                            <div class="current-session">
                                <div class="session-indicator">Jetzt</div>
                                <div class="session-time">14:00 - 15:30</div>
                                <div class="session-title">Beispiel Session</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <#
        }
        #>
        <?php
    }
}
