<?php
/**
 * DGPTM Asset Registry
 *
 * Zentrale Registrierung von CSS/JS-Assets mit bedingtem Laden.
 * Module registrieren ihre Assets mit Ladebedingungen,
 * die Registry entscheidet beim Enqueue was tatsaechlich geladen wird.
 *
 * Verwendung:
 *   $registry = DGPTM_Asset_Registry::get_instance();
 *   $registry->register_style('my-style', $src, [], '1.0.0', ['shortcodes' => ['my_shortcode']]);
 *   $registry->register_script('my-script', $src, [], '1.0.0', true, ['always' => true]);
 *
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Asset_Registry {

    private static $instance = null;

    /**
     * Registrierte Styles
     * @var array
     */
    private $styles = [];

    /**
     * Registrierte Scripts
     * @var array
     */
    private $scripts = [];

    /**
     * Singleton Instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private Konstruktor fuer Singleton
     */
    private function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets'], 999);
    }

    /**
     * Style registrieren
     *
     * @param string $handle   Eindeutiger Handle
     * @param string $src      URL zur CSS-Datei
     * @param array  $deps     Abhaengigkeiten
     * @param string $ver      Versionsnummer
     * @param array  $conditions Ladebedingungen (OR-Logik)
     */
    public function register_style($handle, $src, $deps = [], $ver = '', $conditions = []) {
        $this->styles[$handle] = [
            'src'        => $src,
            'deps'       => $deps,
            'ver'        => $ver,
            'conditions' => $conditions,
        ];
    }

    /**
     * Script registrieren
     *
     * @param string $handle    Eindeutiger Handle
     * @param string $src       URL zur JS-Datei
     * @param array  $deps      Abhaengigkeiten
     * @param string $ver       Versionsnummer
     * @param bool   $in_footer Im Footer laden
     * @param array  $conditions Ladebedingungen (OR-Logik)
     */
    public function register_script($handle, $src, $deps = [], $ver = '', $in_footer = true, $conditions = []) {
        $this->scripts[$handle] = [
            'src'        => $src,
            'deps'       => $deps,
            'ver'        => $ver,
            'in_footer'  => $in_footer,
            'conditions' => $conditions,
        ];
    }

    /**
     * Assets basierend auf Bedingungen enqueuen
     * Wird auf wp_enqueue_scripts mit Prioritaet 999 ausgefuehrt
     */
    public function enqueue_assets() {
        // Post-Content einmalig holen fuer Shortcode-Checks
        $post_content = $this->get_current_post_content();

        foreach ($this->styles as $handle => $asset) {
            if ($this->should_enqueue($asset['conditions'], $post_content)) {
                wp_enqueue_style($handle, $asset['src'], $asset['deps'], $asset['ver']);
            }
        }

        foreach ($this->scripts as $handle => $asset) {
            if ($this->should_enqueue($asset['conditions'], $post_content)) {
                wp_enqueue_script($handle, $asset['src'], $asset['deps'], $asset['ver'], $asset['in_footer']);
            }
        }
    }

    /**
     * Prueft ob ein Asset geladen werden soll
     * Bedingungen werden mit OR-Logik ausgewertet
     *
     * @param array  $conditions  Ladebedingungen
     * @param string $post_content Aktueller Post-Content
     * @return bool
     */
    private function should_enqueue($conditions, $post_content) {
        // Keine Bedingungen = nicht laden (explizites Opt-in)
        if (empty($conditions)) {
            return false;
        }

        // 'always' => true: immer laden
        if (!empty($conditions['always'])) {
            return true;
        }

        // Shortcode-Check
        if (!empty($conditions['shortcodes']) && $post_content) {
            foreach ($conditions['shortcodes'] as $shortcode) {
                if (has_shortcode($post_content, $shortcode)) {
                    return true;
                }
            }
        }

        // Post-Type-Check
        if (!empty($conditions['post_types'])) {
            foreach ($conditions['post_types'] as $post_type) {
                if (is_singular($post_type)) {
                    return true;
                }
            }
        }

        // Page-ID-Check
        if (!empty($conditions['page_ids'])) {
            foreach ($conditions['page_ids'] as $page_id) {
                if (is_page($page_id)) {
                    return true;
                }
            }
        }

        // Custom Callbacks
        if (!empty($conditions['callbacks'])) {
            foreach ($conditions['callbacks'] as $callback) {
                if (is_callable($callback) && call_user_func($callback)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Post-Content des aktuellen Posts holen
     *
     * @return string
     */
    private function get_current_post_content() {
        $post = get_queried_object();

        if ($post instanceof WP_Post) {
            return $post->post_content;
        }

        return '';
    }

    /**
     * Registrierten Style entfernen
     *
     * @param string $handle
     */
    public function deregister_style($handle) {
        unset($this->styles[$handle]);
    }

    /**
     * Registriertes Script entfernen
     *
     * @param string $handle
     */
    public function deregister_script($handle) {
        unset($this->scripts[$handle]);
    }

    /**
     * Alle registrierten Assets abrufen (fuer Debugging)
     *
     * @return array
     */
    public function get_registered_assets() {
        return [
            'styles'  => $this->styles,
            'scripts' => $this->scripts,
        ];
    }
}
