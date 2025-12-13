<?php
/**
 * DGPTM Global Color Definitions
 *
 * Zentrale Verwaltung der DGPTM-Farbpalette für alle Module
 *
 * @package DGPTM_Plugin_Suite
 * @subpackage Core
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class DGPTM_Colors
 *
 * Zentrale Klasse für globale Farbdefinitionen
 */
class DGPTM_Colors {

    /**
     * Globale DGPTM-Farbpalette
     *
     * @var array
     */
    private static $colors = array(
        'hellblau'      => '#2492BA',
        'dunkelblau'    => '#005792',
        'dunkelblau-hover' => '#003d6b',
        'text'          => '#333333',
        'text-light'    => '#666666',
        'rot'           => '#BD1722',
        'success'       => '#28a745',
        'warning'       => '#ffa726',
        'error'         => '#ef5350',
        'border'        => '#e0e0e0',
        'bg-light'      => '#f8f9fa'
    );

    /**
     * Singleton-Instanz
     */
    private static $instance = null;

    /**
     * Singleton-Getter
     *
     * @return DGPTM_Colors
     */
    public static function get_instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor - registriert Hooks
     */
    private function __construct() {
        add_action('wp_enqueue_scripts', array($this, 'enqueue_global_colors'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_global_colors'));
    }

    /**
     * Holt eine Farbe nach Schlüssel
     *
     * @param string $key Farbschlüssel (z.B. 'dunkelblau', 'hellblau', 'rot')
     * @param string $default Standardfarbe falls Schlüssel nicht existiert
     * @return string Hex-Farbcode
     */
    public static function get($key, $default = '#000000') {
        return isset(self::$colors[$key]) ? self::$colors[$key] : $default;
    }

    /**
     * Gibt alle Farben zurück
     *
     * @return array Alle definierten Farben
     */
    public static function get_all() {
        return self::$colors;
    }

    /**
     * Gibt primäre Button-Farbe zurück (DGPTM Dunkelblau)
     *
     * @return string Hex-Farbcode
     */
    public static function get_primary() {
        return self::$colors['dunkelblau'];
    }

    /**
     * Gibt primäre Button-Hover-Farbe zurück
     *
     * @return string Hex-Farbcode
     */
    public static function get_primary_hover() {
        return self::$colors['dunkelblau-hover'];
    }

    /**
     * Fügt globale CSS-Variablen ein
     *
     * Diese werden als CSS Custom Properties verfügbar gemacht,
     * sodass alle Module darauf zugreifen können
     */
    public function enqueue_global_colors() {
        $css = ':root {' . "\n";
        foreach (self::$colors as $key => $value) {
            $css .= '    --dgptm-' . $key . ': ' . $value . ';' . "\n";
        }
        $css .= '}' . "\n";

        // Inline CSS einfügen
        wp_add_inline_style('wp-admin', $css);

        // Für Frontend: inline style ohne Abhängigkeit
        if (!is_admin()) {
            echo '<style id="dgptm-global-colors">' . $css . '</style>';
        }
    }

    /**
     * Gibt CSS für globale Farben als String zurück
     *
     * Nützlich für Module, die eigene CSS-Dateien haben
     *
     * @return string CSS-Code mit Custom Properties
     */
    public static function get_css_variables() {
        $css = ':root {' . "\n";
        foreach (self::$colors as $key => $value) {
            $css .= '    --dgptm-' . $key . ': ' . $value . ';' . "\n";
        }
        $css .= '}';
        return $css;
    }

    /**
     * Gibt Standard-Button-CSS zurück
     *
     * @return string CSS-Code für Standard-Buttons
     */
    public static function get_button_css() {
        return '
.dgptm-btn-primary {
    background: var(--dgptm-dunkelblau);
    color: #ffffff;
    border-color: var(--dgptm-dunkelblau);
}

.dgptm-btn-primary:hover {
    background: var(--dgptm-dunkelblau-hover);
    border-color: var(--dgptm-dunkelblau-hover);
    color: #ffffff;
}
        ';
    }
}

// Initialisiere die Singleton-Instanz
DGPTM_Colors::get_instance();
