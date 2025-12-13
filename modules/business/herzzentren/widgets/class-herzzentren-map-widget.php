<?php
/**
 * Multi-Map Widget für alle Herzzentren
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
 * Herzzentren Map Widget Klasse
 */
class DGPTM_Herzzentren_Map_Widget extends Widget_Base {

    /**
     * Widget Name
     */
    public function get_name() {
        return 'dgptm-herzzentren-map';
    }

    /**
     * Widget Titel
     */
    public function get_title() {
        return esc_html__( 'Herzzentren Karte', 'dgptm-herzzentren' );
    }

    /**
     * Widget Icon
     */
    public function get_icon() {
        return 'eicon-map-pin';
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
        return array( 'map', 'karte', 'herzzentren', 'herzzentrum' );
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
                        'min' => 300,
                        'max' => 1000,
                        'step' => 50,
                    ),
                ),
                'default' => array(
                    'unit' => 'px',
                    'size' => 550,
                ),
            )
        );

        $this->add_control(
            'initial_zoom',
            array(
                'label' => esc_html__( 'Anfangs-Zoom', 'dgptm-herzzentren' ),
                'type' => Controls_Manager::SLIDER,
                'range' => array(
                    'px' => array(
                        'min' => 4,
                        'max' => 12,
                        'step' => 1,
                    ),
                ),
                'default' => array(
                    'size' => 6,
                ),
            )
        );

        $this->add_control(
            'show_popup_on_load',
            array(
                'label' => esc_html__( 'Popup bei Seitenaufruf öffnen', 'dgptm-herzzentren' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Ja', 'dgptm-herzzentren' ),
                'label_off' => esc_html__( 'Nein', 'dgptm-herzzentren' ),
                'return_value' => 'yes',
                'default' => 'no',
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
        
        // Herzzentren abrufen
        $herzzentren = $this->get_herzzentren_data();
        
        if ( empty( $herzzentren ) ) {
            if ( current_user_can( 'edit_posts' ) ) {
                echo '<div class="elementor-alert elementor-alert-warning">';
                echo esc_html__( 'Keine Herzzentren gefunden. Bitte fügen Sie Herzzentren im Backend hinzu.', 'dgptm-herzzentren' );
                echo '</div>';
            }
            return;
        }

        $map_height = ! empty( $settings['map_height']['size'] ) ? absint( $settings['map_height']['size'] ) : 550;
        $initial_zoom = ! empty( $settings['initial_zoom']['size'] ) ? absint( $settings['initial_zoom']['size'] ) : 6;
        $show_popup = ( 'yes' === $settings['show_popup_on_load'] ) ? 'true' : 'false';
        
        $unique_id = 'dgptm-map-' . $this->get_id();
        
        ?>
        <div class="dgptm-herzzentren-map-wrapper" data-widget-id="<?php echo esc_attr( $this->get_id() ); ?>">
            <div 
                id="<?php echo esc_attr( $unique_id ); ?>" 
                class="dgptm-map-canvas" 
                style="height: <?php echo esc_attr( $map_height ); ?>px;"
                data-markers="<?php echo esc_attr( wp_json_encode( $herzzentren ) ); ?>"
                data-zoom="<?php echo esc_attr( $initial_zoom ); ?>"
                data-show-popup="<?php echo esc_attr( $show_popup ); ?>"
                data-icon-url="<?php echo esc_url( DGPTM_HZ_URL . 'assets/images/marker-2.png' ); ?>"
            ></div>
        </div>
        <?php
    }

    /**
     * Herzzentren-Daten abrufen
     * 
     * @return array Array mit Herzzentrum-Daten
     */
    private function get_herzzentren_data() {
        $posts = get_posts( array(
            'post_type' => 'herzzentrum',
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ) );

        if ( empty( $posts ) ) {
            return array();
        }

        $herzzentren = array();
        
        foreach ( $posts as $post ) {
            $lat = get_post_meta( $post->ID, 'map-marker-latitude', true );
            $lng = get_post_meta( $post->ID, 'map-marker-longitude', true );
            
            // Nur Herzzentren mit gültigen Koordinaten hinzufügen
            if ( empty( $lat ) || empty( $lng ) ) {
                continue;
            }

            $anschrift = get_post_meta( $post->ID, 'anschrift', true );
            $post_url = get_permalink( $post->ID );
            
            $herzzentren[] = array(
                'id' => $post->ID,
                'title' => wp_kses_post( $post->post_title ),
                'address' => wp_kses_post( $anschrift ),
                'lat' => (float) $lat,
                'lng' => (float) $lng,
                'url' => esc_url( $post_url ),
            );
        }

        return $herzzentren;
    }

    /**
     * Render Widget Content für den Elementor Editor
     */
    protected function content_template() {
        ?>
        <#
        var mapHeight = settings.map_height.size || 550;
        #>
        <div class="dgptm-herzzentren-map-wrapper">
            <div class="dgptm-map-canvas" style="height: {{ mapHeight }}px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #666;">
                <p><?php echo esc_html__( 'Karten-Vorschau (nur im Frontend sichtbar)', 'dgptm-herzzentren' ); ?></p>
            </div>
        </div>
        <?php
    }
}
