<?php
/**
 * Plugin Name: DGPTM Workshop Buchung
 * Description: Buchung von Workshops aus Zoho CRM mit Stripe-Zahlung und Warteliste
 * Version: 0.2.1
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('DGPTM_Workshop_Booking')) {

    class DGPTM_Workshop_Booking {

        private static $instance = null;
        private $plugin_path;
        private $plugin_url;
        private $entscheidungsvorlage;

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
            require_once $this->plugin_path . 'includes/class-entscheidungsvorlage.php';
            $this->entscheidungsvorlage = new DGPTM_Workshop_Entscheidungsvorlage(
                $this->plugin_path,
                $this->plugin_url
            );
        }

        public function get_path() { return $this->plugin_path; }
        public function get_url()  { return $this->plugin_url; }
    }
}

if (!isset($GLOBALS['dgptm_workshop_booking_initialized'])) {
    $GLOBALS['dgptm_workshop_booking_initialized'] = true;
    DGPTM_Workshop_Booking::get_instance();
}
