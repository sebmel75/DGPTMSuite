<?php
/**
 * DGPTM Core Assets
 *
 * Registriert plugin-weite, modul-uebergreifende Assets.
 * Aktuell: nur dgptm-shared-buttons.css (einheitliche Button-Klassen).
 *
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Core_Assets {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $registry = DGPTM_Asset_Registry::get_instance();

        $registry->register_style(
            'dgptm-shared-buttons',
            DGPTM_SUITE_URL . 'core/assets/css/dgptm-shared-buttons.css',
            [],
            DGPTM_SUITE_VERSION,
            ['always' => true]
        );

        add_action('admin_enqueue_scripts', [$this, 'enqueue_in_admin']);
    }

    public function enqueue_in_admin() {
        wp_enqueue_style(
            'dgptm-shared-buttons',
            DGPTM_SUITE_URL . 'core/assets/css/dgptm-shared-buttons.css',
            [],
            DGPTM_SUITE_VERSION
        );
    }
}
