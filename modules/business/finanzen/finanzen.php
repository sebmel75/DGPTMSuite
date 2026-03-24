<?php
/**
 * Plugin Name: DGPTM Finanzen
 * Description: Konsolidiertes Finanzmodul — Mitgliedsbeitrag, Finanzberichte, Rechnungen, GoCardless
 * Version: 1.0.0
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('DGPTM_FIN_PATH', plugin_dir_path(__FILE__));
define('DGPTM_FIN_URL', plugin_dir_url(__FILE__));

if (!class_exists('DGPTM_Finanzen')) {

    final class DGPTM_Finanzen {

        private static ?self $instance = null;

        const NONCE       = 'dgptm_fin_nonce';
        const OPT_CONFIG  = 'dgptm_fin_config';
        const OPT_RESULTS = 'dgptm_fin_last_results';
        const OPT_HISTORY = 'dgptm_fin_billing_history';

        const ROLE_FIELDS = ['schatzmeister', 'praesident', 'geschaeftsstelle'];

        const TABS = [
            'dashboard',
            'billing',
            'members',
            'results',
            'payments',
            'invoices',
            'reports',
            'treasurer',
            'settings',
        ];

        public static function get_instance(): self {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            // Includes laden (mit file_exists Guard fuer inkrementelle Entwicklung)
            $includes = [
                'includes/class-config.php',
            ];
            foreach ($includes as $file) {
                $path = DGPTM_FIN_PATH . $file;
                if (file_exists($path)) {
                    require_once $path;
                }
            }

            // Shortcode & Admin
            add_action('init', [$this, 'register_shortcode']);
            add_action('admin_menu', [$this, 'add_admin_menu']);

            // AJAX handlers
            add_action('wp_ajax_dgptm_fin_get_dashboard',      [$this, 'ajax_get_dashboard']);
            add_action('wp_ajax_dgptm_fin_start_billing',       [$this, 'ajax_start_billing']);
            add_action('wp_ajax_dgptm_fin_process_chunk',       [$this, 'ajax_process_chunk']);
            add_action('wp_ajax_dgptm_fin_finalize_billing',    [$this, 'ajax_finalize_billing']);
            add_action('wp_ajax_dgptm_fin_cancel_billing',      [$this, 'ajax_cancel_billing']);
            add_action('wp_ajax_dgptm_fin_get_billing_status',  [$this, 'ajax_get_billing_status']);
            add_action('wp_ajax_dgptm_fin_get_members',         [$this, 'ajax_get_members']);
            add_action('wp_ajax_dgptm_fin_get_results',         [$this, 'ajax_get_results']);
            add_action('wp_ajax_dgptm_fin_process_payments',    [$this, 'ajax_process_payments']);
            add_action('wp_ajax_dgptm_fin_sync_mandates',       [$this, 'ajax_sync_mandates']);
            add_action('wp_ajax_dgptm_fin_get_invoices',        [$this, 'ajax_get_invoices']);
            add_action('wp_ajax_dgptm_fin_invoice_action',      [$this, 'ajax_invoice_action']);
            add_action('wp_ajax_dgptm_fin_get_report',          [$this, 'ajax_get_report']);
            add_action('wp_ajax_dgptm_fin_refresh_cache',       [$this, 'ajax_refresh_cache']);
            add_action('wp_ajax_dgptm_fin_treasurer_crud',      [$this, 'ajax_treasurer_crud']);
            add_action('wp_ajax_dgptm_fin_save_config',         [$this, 'ajax_save_config']);
            add_action('wp_ajax_dgptm_fin_upload_credentials',  [$this, 'ajax_upload_credentials']);
            add_action('wp_ajax_dgptm_fin_import_historical',   [$this, 'ajax_import_historical']);

            // Naechtlicher Cron: Daten um 3:00 Uhr aktualisieren
            add_action('dgptm_fin_nightly_refresh', [$this, 'cron_nightly_refresh']);
            if (!wp_next_scheduled('dgptm_fin_nightly_refresh')) {
                $next_3am = strtotime('tomorrow 03:00:00');
                wp_schedule_event($next_3am, 'daily', 'dgptm_fin_nightly_refresh');
            }
        }

        /* ============================================================ */
        /* Berechtigungen (ACF User-Felder: schatzmeister, praesident,   */
        /* geschaeftsstelle — bereits in ACF-Gruppe "Berechtigungen")    */
        /* ============================================================ */

        /**
         * Rolle des Users ermitteln (erste passende)
         */
        public function get_user_role(int $user_id = 0): string {
            if ($user_id === 0) {
                $user_id = get_current_user_id();
            }

            if (user_can($user_id, 'manage_options')) {
                return 'admin';
            }

            if (function_exists('get_field')) {
                foreach (self::ROLE_FIELDS as $field) {
                    $val = get_field($field, 'user_' . $user_id);
                    if ($val) {
                        return $field;
                    }
                }
            }

            return '';
        }

        /**
         * Schatzmeister-Zugriff (Billing-/Payment-Tools)
         */
        public function user_has_access(int $user_id = 0): bool {
            if ($user_id === 0) {
                $user_id = get_current_user_id();
            }

            $role = $this->get_user_role($user_id);
            return in_array($role, ['admin', 'schatzmeister'], true);
        }

        /**
         * Lese-Zugriff (jede der 4 Rollen)
         */
        public function user_can_view(int $user_id = 0): bool {
            if ($user_id === 0) {
                $user_id = get_current_user_id();
            }

            $role = $this->get_user_role($user_id);
            return $role !== '';
        }

        /**
         * Prueft ob User Admin ist
         */
        public function user_is_admin(int $user_id = 0): bool {
            if ($user_id === 0) {
                $user_id = get_current_user_id();
            }

            return user_can($user_id, 'manage_options');
        }

        /**
         * Sichtbare Tabs fuer den User
         *
         * Admin: alle 9 Tabs
         * Schatzmeister: Tabs 1-8 (alles ausser settings)
         * Praesident/Geschaeftsstelle: dashboard, results, reports
         */
        public function get_visible_tabs(int $user_id = 0): array {
            if ($user_id === 0) {
                $user_id = get_current_user_id();
            }

            $role = $this->get_user_role($user_id);

            switch ($role) {
                case 'admin':
                    return self::TABS;

                case 'schatzmeister':
                    return array_slice(self::TABS, 0, 8); // alle ausser settings

                case 'praesident':
                case 'geschaeftsstelle':
                    return ['dashboard', 'results', 'reports'];

                default:
                    return [];
            }
        }

        /**
         * Tab-Zugriffsmatrix fuer den User
         *
         * @return array<string, bool>
         */
        public function get_tab_access(int $user_id = 0): array {
            if ($user_id === 0) {
                $user_id = get_current_user_id();
            }

            $visible = $this->get_visible_tabs($user_id);
            $access  = [];

            foreach (self::TABS as $tab) {
                $access[$tab] = in_array($tab, $visible, true);
            }

            return $access;
        }

        /* ============================================================ */
        /* Shortcode                                                     */
        /* ============================================================ */

        public function register_shortcode(): void {
            add_shortcode('dgptm_finanzen', [$this, 'render_shortcode']);
        }

        public function render_shortcode($atts): string {
            if (!is_user_logged_in()) {
                return '<p>Bitte einloggen.</p>';
            }
            if (!$this->user_can_view()) {
                return '<p>Kein Zugriff. Berechtigung: Praesident, Schatzmeister oder Geschaeftsstelle.</p>';
            }

            return '<div class="dgptm-fin-wrap"><p>Finanzen-Dashboard wird geladen...</p></div>';
        }

        /* ============================================================ */
        /* Admin Menu                                                    */
        /* ============================================================ */

        public function add_admin_menu(): void {
            add_submenu_page(
                'dgptm-suite',
                'Finanzen',
                'Finanzen',
                'manage_options',
                'dgptm-finanzen',
                [$this, 'render_admin_page']
            );
        }

        public function render_admin_page(): void {
            echo '<div class="wrap"><h1>DGPTM Finanzen</h1><p>Admin-Seite wird in einem spaeteren Task implementiert.</p></div>';
        }

        /* ============================================================ */
        /* AJAX Stubs                                                    */
        /* ============================================================ */

        public function ajax_get_dashboard(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            wp_send_json_error(['message' => 'Not implemented']);
        }

        public function ajax_start_billing(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            wp_send_json_error(['message' => 'Not implemented']);
        }

        public function ajax_process_chunk(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            wp_send_json_error(['message' => 'Not implemented']);
        }

        public function ajax_finalize_billing(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            wp_send_json_error(['message' => 'Not implemented']);
        }

        public function ajax_cancel_billing(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            wp_send_json_error(['message' => 'Not implemented']);
        }

        public function ajax_get_billing_status(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            wp_send_json_error(['message' => 'Not implemented']);
        }

        public function ajax_get_members(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            wp_send_json_error(['message' => 'Not implemented']);
        }

        public function ajax_get_results(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            wp_send_json_error(['message' => 'Not implemented']);
        }

        public function ajax_process_payments(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            wp_send_json_error(['message' => 'Not implemented']);
        }

        public function ajax_sync_mandates(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            wp_send_json_error(['message' => 'Not implemented']);
        }

        public function ajax_get_invoices(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            wp_send_json_error(['message' => 'Not implemented']);
        }

        public function ajax_invoice_action(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            wp_send_json_error(['message' => 'Not implemented']);
        }

        public function ajax_get_report(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            wp_send_json_error(['message' => 'Not implemented']);
        }

        public function ajax_refresh_cache(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            wp_send_json_error(['message' => 'Not implemented']);
        }

        public function ajax_treasurer_crud(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            wp_send_json_error(['message' => 'Not implemented']);
        }

        public function ajax_save_config(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            wp_send_json_error(['message' => 'Not implemented']);
        }

        public function ajax_upload_credentials(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            wp_send_json_error(['message' => 'Not implemented']);
        }

        public function ajax_import_historical(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            wp_send_json_error(['message' => 'Not implemented']);
        }

        /* ============================================================ */
        /* Cron                                                          */
        /* ============================================================ */

        public function cron_nightly_refresh(): void {
            $this->log('Naechtlicher Refresh gestartet');
            // Wird in spaeteren Tasks implementiert
            $this->log('Naechtlicher Refresh abgeschlossen');
        }

        /* ============================================================ */
        /* Logging                                                       */
        /* ============================================================ */

        private function log(string $message, string $level = 'info'): void {
            if (class_exists('DGPTM_Logger') && method_exists('DGPTM_Logger', $level)) {
                \DGPTM_Logger::$level($message, 'finanzen');
            }
        }
    }
}

if (!isset($GLOBALS['dgptm_finanzen_initialized'])) {
    $GLOBALS['dgptm_finanzen_initialized'] = true;
    DGPTM_Finanzen::get_instance();
}
