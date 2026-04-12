<?php
/**
 * Plugin Name: DGPTM Stipendienvergabe
 * Description: Digitales Bewerbungs- und Bewertungsverfahren fuer DGPTM-Stipendien
 * Version: 0.1.0
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('DGPTM_Stipendium')) {

    class DGPTM_Stipendium {

        private static $instance = null;
        private $plugin_path;
        private $plugin_url;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->plugin_path = plugin_dir_path(__FILE__);
            $this->plugin_url  = plugin_dir_url(__FILE__);

            $this->load_components();
        }

        private function load_components() {
            require_once $this->plugin_path . 'includes/class-freigabe.php';
            new DGPTM_Stipendium_Freigabe($this->plugin_path, $this->plugin_url);
        }

        public function get_path() {
            return $this->plugin_path;
        }

        public function get_url() {
            return $this->plugin_url;
        }
    }
}

if (!isset($GLOBALS['dgptm_stipendium_initialized'])) {
    $GLOBALS['dgptm_stipendium_initialized'] = true;
    DGPTM_Stipendium::get_instance();
}
