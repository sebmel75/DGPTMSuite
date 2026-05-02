<?php
/**
 * Plugin Name: DGPTM Workshop Buchung
 * Description: Buchung von Workshops und Webinaren aus Zoho CRM mit Stripe-Zahlung, Status-Sync-Coordinator und AGB-konformer Reconciliation.
 * Version: 0.3.0
 *
 * Phase 1: Buchungsfluss + Sync-Coordinator (Hybrid Webhook + Reconciliation-Cron).
 * Tickets/QR (Phase 2), Mitgliederbereich (Phase 3), Bescheinigungen (Phase 4),
 * Webinar-Anbindung (Phase 5), Storno (Phase 6), Books (Phase 7), DE/EN (Phase 8) folgen.
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
            // Composer-Autoload (Phase 2: dompdf + endroid/qr-code) — optional, da
            // vendor/ nicht im Repo. Wenn fehlend: Buchungsfluss laeuft weiter,
            // QR/PDF werden geskippt und im sync_log dokumentiert.
            $autoload = $this->plugin_path . 'vendor/autoload.php';
            if (file_exists($autoload)) {
                require_once $autoload;
            }

            // Installer zuerst — DB-Tabellen muessen vor allen anderen Komponenten existieren
            require_once $this->plugin_path . 'includes/class-installer.php';
            DGPTM_WSB_Installer::maybe_install();

            // Bestehende Entscheidungsvorlage (existierte vor Phase 1)
            require_once $this->plugin_path . 'includes/class-entscheidungsvorlage.php';
            $this->entscheidungsvorlage = new DGPTM_Workshop_Entscheidungsvorlage(
                $this->plugin_path,
                $this->plugin_url
            );

            // Phase 1: Sync-Foundation (Value Objects, Stores, State-Machine)
            require_once $this->plugin_path . 'includes/class-sync-intent.php';
            require_once $this->plugin_path . 'includes/class-sync-result.php';
            require_once $this->plugin_path . 'includes/class-sync-log-store.php';
            require_once $this->plugin_path . 'includes/class-drift-alert-store.php';
            require_once $this->plugin_path . 'includes/class-state-machine.php';

            // Phase 1: CRM-Layer (privat) + Coordinator (Single Entry Point)
            require_once $this->plugin_path . 'includes/class-veranstal-x-contacts.php';
            require_once $this->plugin_path . 'includes/class-sync-coordinator.php';

            // Phase 1: Zoho-Read
            require_once $this->plugin_path . 'includes/class-event-source.php';
            require_once $this->plugin_path . 'includes/class-contact-lookup.php';

            // Phase 1: Booking
            require_once $this->plugin_path . 'includes/class-pending-bookings-store.php';
            require_once $this->plugin_path . 'includes/class-booking-service.php';

            // Phase 1: Stripe
            require_once $this->plugin_path . 'includes/class-stripe-checkout.php';
            require_once $this->plugin_path . 'includes/class-stripe-webhook.php';

            // Phase 1: Mail
            require_once $this->plugin_path . 'includes/class-ics-builder.php';
            require_once $this->plugin_path . 'includes/class-mail-sender.php';

            // Phase 1: Reconciliation + Cleanup
            require_once $this->plugin_path . 'includes/class-books-status-reader.php';
            require_once $this->plugin_path . 'includes/class-reconciliation-cron.php';
            require_once $this->plugin_path . 'includes/class-pending-cleanup-cron.php';

            // Phase 2: Tickets + QR + PDF + Token
            require_once $this->plugin_path . 'includes/class-ticket-number.php';
            require_once $this->plugin_path . 'includes/class-qr-generator.php';
            require_once $this->plugin_path . 'includes/class-ticket-pdf.php';
            require_once $this->plugin_path . 'includes/class-token-installer.php';
            require_once $this->plugin_path . 'includes/class-token-store.php';

            DGPTM_WSB_Token_Installer::maybe_install();

            // Phase 1: Frontend
            require_once $this->plugin_path . 'includes/class-shortcodes.php';

            // Singletons starten (Hooks/Routen registrieren)
            DGPTM_WSB_Stripe_Webhook::get_instance();
            DGPTM_WSB_Reconciliation_Cron::get_instance();
            DGPTM_WSB_Pending_Cleanup_Cron::get_instance();
            DGPTM_WSB_Shortcodes::get_instance($this->plugin_path, $this->plugin_url);
        }

        public function get_path() { return $this->plugin_path; }
        public function get_url()  { return $this->plugin_url; }
    }
}

if (!isset($GLOBALS['dgptm_workshop_booking_initialized'])) {
    $GLOBALS['dgptm_workshop_booking_initialized'] = true;
    DGPTM_Workshop_Booking::get_instance();
}
