<?php
/**
 * Single-Map Widget für einzelnes Herzzentrum
 * 
 * @package DGPTM_Herzzentren
 * @since 4.0.0
 */

use Elementor\Widget_Base;
use Elementor\Controls_Manager;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Herzzentrum Single Map Widget Klasse
 */
class DGPTM_Herzzentrum_Single_Map_Widget extends Widget_Base {

    /**
     * Widget Name
     */
    public function get_name() {
        return 'dgptm-herzzentrum-single-map';
    }

    /**
     * Widget Titel
     */
    public function get_title() {
        return esc_html__( 'Herzzentrum Einzelkarte', 'dgptm-herzzentren' );
    }

    /**
     * Widget Icon
     */
    public function get_icon() {
        return 'eicon-google-maps';
    }

    /**
     * Widget Kategorien
     */
    public function get_categories() {
        return array( 'general' );
    }

    /**
     * Widget Keywords
     */
    public function get_keywords() {
        return array( 'map', 'karte', 'herzzentrum', 'single', 'einzeln' );
    }

    /**
     * Style Dependencies
     */
    public function get_style_depends() {
        return array( 'leaflet-css', 'dgptm-map-style' );
    }

    /**
     * Script Dependencies
     */
    public function get_script_depends() {
        return array( 'leaflet-js', 'dgptm-map-handler' );
    }

    /**
     * Register Widget Controls
     */
    protected function register_controls() {
        
        // Content Section
        $this->start_controls_section(
            'content_section',
            array(
                'label' => esc_html__( 'Koordinaten', 'dgptm-herzzentren' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'latitude',
            array(
                'label' => esc_html__( 'Breitengrad (Latitude)', 'dgptm-herzzentren' ),
                'type' => Controls_Manager::TEXT,
                'default' => '51.165691',
                'placeholder' => '51.165691',
                'description' => esc_html__( 'z.B. 51.165691', 'dgptm-herzzentren' ),
                'dynamic' => array(
                    'active' => true,
                ),
            )
        );

        $this->add_control(
            'longitude',
            array(
                'label' => esc_html__( 'Längengrad (Longitude)', 'dgptm-herzzentren' ),
                'type' => Controls_Manager::TEXT,
                'default' => '10.451526',
                'placeholder' => '10.451526',
                'description' => esc_html__( 'z.B. 10.451526', 'dgptm-herzzentren' ),
                'dynamic' => array(
                    'active' => true,
                ),
            )
        );

        $this->add_control(
            'show_marker',
            array(
                'label' => esc_html__( 'Marker anzeigen', 'dgptm-herzzentren' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Ja', 'dgptm-herzzentren' ),
                'label_off' => esc_html__( 'Nein', 'dgptm-herzzentren' ),
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'marker_title',
            array(
                'label' => esc_html__( 'Marker-Titel (optional)', 'dgptm-herzzentren' ),
                'type' => Controls_Manager::TEXT,
                'default' => '',
                'placeholder' => esc_html__( 'Standort-Name', 'dgptm-herzzentren' ),
                'condition' => array(
                    'show_marker' => 'yes',
                ),
                'dynamic' => array(
                    'active' => true,
                ),
            )
        );

        $this->add_control(
            'marker_description',
            array(
                'label' => esc_html__( 'Marker-Beschreibung (optional)', 'dgptm-herzzentren' ),
                'type' => Controls_Manager::TEXTAREA,
                'default' => '',
                'placeholder' => esc_html__( 'Adresse oder Beschreibung...', 'dgptm-herzzentren' ),
                'condition' => array(
                    'show_marker' => 'yes',
                ),
                'dynamic' => array(
                    'active' => true,
                ),
            )
        );

        $this->end_controls_section();

        // Map Settings Section
        $this->start_controls_section(
            'map_settings_section',
            array(
                'label' => esc_html__( 'Karten-Einstellungen', 'dgptm-herzzentren' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            )
        );

        $this->add_control(
            'map_height',
            array(
                'label' => esc_html__( 'Kartenhöhe', 'dgptm-herzzentren' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => array( 'px' ),
                'range' => array(
                    'px' => array(
                        'min' => 200,
                        'max' => 800,
                        'step' => 50,
                    ),
                ),
                'default' => array(
                    'unit' => 'px',
                    'size' => 400,
                ),
            )
        );

        $this->add_control(
            'zoom_level',
            array(
                'label' => esc_html__( 'Zoom-Level', 'dgptm-herzzentren' ),
                'type' => Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array(
                        'min' => 8,
                        'max' => 18,
                        'step' => 1,
                    ),
                ),
                'default' => array(
                    'size' => 13,
                ),
            )
        );

        $this->add_control(
            'disable_scroll_zoom',
            array(
                'label' => esc_html__( 'Scroll-Zoom deaktivieren', 'dgptm-herzzentren' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Ja', 'dgptm-herzzentren' ),
                'label_off' => esc_html__( 'Nein', 'dgptm-herzzentren' ),
                'return_value' => 'yes',
                'default' => 'yes',
                'description' => esc_html__( 'Klick aktiviert Scroll-Zoom', 'dgptm-herzzentren' ),
            )
        );

        $this->end_controls_section();

        // Style Section
        $this->start_controls_section(
            'style_section',
            array(
                'label' => esc_html__( 'Popup-Stil', 'dgptm-herzzentren' ),
                'tab' => Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_control(
            'popup_bg_color',
            array(
                'label' => esc_html__( 'Popup-Hintergrundfarbe', 'dgptm-herzzentren' ),
                'type' => Controls_Manager::COLOR,
                'default' => '#ffffff',
                'selectors' => array(
                    '{{WRAPPER}} .leaflet-popup-content-wrapper' => 'background-color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'popup_text_color',
            array(
                'label' => esc_html__( 'Popup-Textfarbe', 'dgptm-herzzentren' ),
                'type' => Controls_Manager::COLOR,
                'default' => '#333333',
                'selectors' => array(
                    '{{WRAPPER}} .leaflet-popup-content' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->end_controls_section();
    }

    /**
     * Render Widget Output
     */
    protected function render() {
        $settings = $this->get_settings_for_display();
        
        // Koordinaten validieren und sanitizen
        $latitude = $this->sanitize_coordinate( $settings['latitude'], 51.165691 );
        $longitude = $this->sanitize_coordinate( $settings['longitude'], 10.451526 );
        
        $map_height = ! empty( $settings['map_height']['size'] ) ? absint( $settings['map_height']['size'] ) : 400;
        $zoom_level = ! empty( $settings['zoom_level']['size'] ) ? absint( $settings['zoom_level']['size'] ) : 13;
        $show_marker = ( 'yes' === $settings['show_marker'] );
        $disable_scroll = ( 'yes' === $settings['disable_scroll_zoom'] );
        
        $marker_data = null;
        if ( $show_marker ) {
            $marker_data = array(
                'lat' => $latitude,
                'lng' => $longitude,
                'title' => ! empty( $settings['marker_title'] ) ? wp_kses_post( $settings['marker_title'] ) : '',
                'description' => ! empty( $settings['marker_description'] ) ? wp_kses_post( $settings['marker_description'] ) : '',
            );
        }
        
        $unique_id = 'dgptm-single-map-' . $this->get_id();
        
        ?>
        <div class="dgptm-herzzentrum-single-map-wrapper" data-widget-id="<?php echo esc_attr( $this->get_id() ); ?>">
            <div 
                id="<?php echo esc_attr( $unique_id ); ?>" 
                class="dgptm-map-canvas-single" 
                style="height: <?php echo esc_attr( $map_height ); ?>px;"
                data-lat="<?php echo esc_attr( $latitude ); ?>"
                data-lng="<?php echo esc_attr( $longitude ); ?>"
                data-zoom="<?php echo esc_attr( $zoom_level ); ?>"
                data-disable-scroll="<?php echo esc_attr( $disable_scroll ? 'true' : 'false' ); ?>"
                data-icon-url="<?php echo esc_url( DGPTM_HZ_URL . 'assets/images/marker-2.png' ); ?>"
                <?php if ( $marker_data ) : ?>
                    data-marker="<?php echo esc_attr( wp_json_encode( $marker_data ) ); ?>"
                <?php endif; ?>
            ></div>
        </div>
        <?php
    }

    /**
     * Koordinate sanitizen
     * 
     * @param mixed $value Koordinaten-Wert
     * @param float $default Standard-Wert bei ungültiger Koordinate
     * @return float Sanitized Koordinate
     */
    private function sanitize_coordinate( $value, $default ) {
        $value = trim( $value );
        
        if ( empty( $value ) || ! is_numeric( $value ) ) {
            return $default;
        }
        
        return (float) $value;
    }

    /**
     * Render Widget Content für den Elementor Editor
     */
    protected function content_template() {
        ?>
        <#
        var mapHeight = settings.map_height.size || 400;
        #>
        <div class="dgptm-herzzentrum-single-map-wrapper">
            <div class="dgptm-map-canvas-single" style="height: {{ mapHeight }}px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #666;">
                <p><?php echo esc_html__( 'Einzelkarten-Vorschau (nur im Frontend sichtbar)', 'dgptm-herzzentren' ); ?></p>
            </div>
        </div>
        <?php
    }
}
