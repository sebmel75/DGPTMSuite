<?php
/**
 * Plugin Name: DGPTM - Mitgliedsbeitrag
 * Description: Mitgliedsbeitragsverwaltung mit Zoho CRM/Books und GoCardless
 * Version:     1.0.0
 * Author:      Sebastian Melzer
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('DGPTM_MB_PATH', plugin_dir_path(__FILE__));
define('DGPTM_MB_URL', plugin_dir_url(__FILE__));

require_once DGPTM_MB_PATH . 'includes/class-config.php';
require_once DGPTM_MB_PATH . 'includes/class-zoho-crm.php';
require_once DGPTM_MB_PATH . 'includes/class-zoho-books.php';
require_once DGPTM_MB_PATH . 'includes/class-gocardless.php';
require_once DGPTM_MB_PATH . 'includes/class-billing-engine.php';

if (!class_exists('DGPTM_Mitgliedsbeitrag_Module')) {

    final class DGPTM_Mitgliedsbeitrag_Module {

        private static ?self $instance = null;
        const NONCE = 'dgptm_mb_nonce';
        const OPT_CONFIG = 'dgptm_mb_config';
        const OPT_RESULTS = 'dgptm_mb_last_results';

        public static function get_instance(): self {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            add_action('init', [$this, 'register_shortcode']);

            // AJAX handlers
            add_action('wp_ajax_dgptm_mb_get_status', [$this, 'ajax_get_status']);
            add_action('wp_ajax_dgptm_mb_start_billing', [$this, 'ajax_start_billing']);
            add_action('wp_ajax_dgptm_mb_process_payments', [$this, 'ajax_process_payments']);
            add_action('wp_ajax_dgptm_mb_sync_mandates', [$this, 'ajax_sync_mandates']);
            add_action('wp_ajax_dgptm_mb_save_config', [$this, 'ajax_save_config']);

            // Admin-Menue
            add_action('admin_menu', [$this, 'add_admin_menu']);

            // Zoho-Scopes registrieren
            add_filter('dgptm_zoho_required_scopes', function($scopes) {
                $scopes['Mitgliedsbeitrag'] = [
                    'ZohoCRM.modules.ALL',
                    'ZohoCRM.settings.variables.READ',
                    'ZohoCRM.coql.READ',
                    'ZohoBooks.fullaccess.all',
                ];
                return $scopes;
            });
        }

        /* ============================================================ */
        /* Berechtigung                                                  */
        /* ============================================================ */

        /**
         * Schatzmeister-Zugriff (Billing-Tools)
         */
        public function user_has_access(int $user_id = 0): bool {
            if (!$user_id) $user_id = get_current_user_id();
            if (user_can($user_id, 'manage_options')) return true;
            if (function_exists('get_field')) {
                return (bool) get_field('schatzmeister', 'user_' . $user_id);
            }
            return false;
        }

        /**
         * Statistik-Zugriff (Praesident, Schatzmeister, Geschaeftsstelle)
         */
        public function user_can_view_stats(int $user_id = 0): bool {
            if (!$user_id) $user_id = get_current_user_id();
            if (user_can($user_id, 'manage_options')) return true;
            if (!function_exists('get_field')) return false;
            return (bool) get_field('schatzmeister', 'user_' . $user_id)
                || (bool) get_field('praesident', 'user_' . $user_id)
                || (bool) get_field('geschaeftsstelle', 'user_' . $user_id);
        }

        /* ============================================================ */
        /* Shortcode                                                     */
        /* ============================================================ */

        public function register_shortcode(): void {
            add_shortcode('dgptm_mitgliedsbeitrag', [$this, 'render_shortcode']);
        }

        public function render_shortcode($atts): string {
            if (!is_user_logged_in()) {
                return '<p>Bitte einloggen.</p>';
            }
            if (!$this->user_can_view_stats()) {
                return '<p>Kein Zugriff. Berechtigung: Praesident, Schatzmeister oder Geschaeftsstelle.</p>';
            }

            wp_enqueue_style('dgptm-mb', DGPTM_MB_URL . 'assets/css/mitgliedsbeitrag.css', [], '1.0.0');
            wp_enqueue_script('dgptm-mb', DGPTM_MB_URL . 'assets/js/mitgliedsbeitrag.js', ['jquery'], '1.0.0', true);
            wp_localize_script('dgptm-mb', 'dgptmMB', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce(self::NONCE),
            ]);

            ob_start();
            include DGPTM_MB_PATH . 'templates/dashboard.php';
            return ob_get_clean();
        }

        /* ============================================================ */
        /* AJAX: Status abrufen                                          */
        /* ============================================================ */

        public function ajax_get_status(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_has_access()) {
                wp_send_json_error(['message' => 'Kein Zugriff']);
            }

            $config = DGPTM_MB_Config::load();

            // Mitgliederzahlen aus Cache oder live
            $stats = get_transient('dgptm_mb_member_stats');
            if (false === $stats) {
                $crm = new DGPTM_MB_Zoho_CRM($config);
                $stats = $crm->get_member_stats();
                set_transient('dgptm_mb_member_stats', $stats, DAY_IN_SECONDS);
            }

            $last_results = get_option(self::OPT_RESULTS, []);

            wp_send_json_success([
                'stats'        => $stats,
                'last_run'     => $last_results,
                'config_set'   => $config->is_valid(),
            ]);
        }

        /* ============================================================ */
        /* AJAX: Beitragsabrechnungslauf starten                         */
        /* ============================================================ */

        public function ajax_start_billing(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_has_access()) {
                wp_send_json_error(['message' => 'Kein Zugriff']);
            }

            $year     = intval($_POST['year'] ?? date('Y'));
            $dry_run  = filter_var($_POST['dry_run'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $send     = filter_var($_POST['send_invoices'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $contacts = !empty($_POST['contact_ids']) ? array_map('sanitize_text_field', (array) $_POST['contact_ids']) : [];

            $config = DGPTM_MB_Config::load();
            if (!$config->is_valid()) {
                wp_send_json_error(['message' => 'Konfiguration unvollstaendig. Bitte im Admin-Bereich konfigurieren.']);
            }

            $engine = new DGPTM_MB_Billing_Engine($config);

            try {
                $results = $engine->run_billing($year, $dry_run, true, $send, $contacts);

                if (!$dry_run) {
                    update_option(self::OPT_RESULTS, [
                        'year'      => $year,
                        'timestamp' => current_time('c'),
                        'summary'   => $results['summary'] ?? [],
                    ], false);
                    delete_transient('dgptm_mb_member_stats');
                }

                $this->log(sprintf(
                    'Beitragsabrechnungslauf %s: Jahr=%d, %d Mitglieder verarbeitet',
                    $dry_run ? 'DRY-RUN' : 'LIVE',
                    $year,
                    $results['summary']['total'] ?? 0
                ), $dry_run ? 'info' : 'warning');

                wp_send_json_success($results);
            } catch (\Throwable $e) {
                $this->log('Billing-Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: Zahlungen verarbeiten                                   */
        /* ============================================================ */

        public function ajax_process_payments(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_has_access()) {
                wp_send_json_error(['message' => 'Kein Zugriff']);
            }

            $dry_run = filter_var($_POST['dry_run'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $config  = DGPTM_MB_Config::load();

            if (!$config->is_valid()) {
                wp_send_json_error(['message' => 'Konfiguration unvollstaendig.']);
            }

            $engine = new DGPTM_MB_Billing_Engine($config);

            try {
                $results = $engine->process_gocardless_payments($dry_run);
                $this->log(sprintf('Zahlungsverarbeitung %s: %d Rechnungen', $dry_run ? 'DRY-RUN' : 'LIVE', $results['total'] ?? 0), 'info');
                wp_send_json_success($results);
            } catch (\Throwable $e) {
                $this->log('Payment-Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: Mandats-Sync                                            */
        /* ============================================================ */

        public function ajax_sync_mandates(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_has_access()) {
                wp_send_json_error(['message' => 'Kein Zugriff']);
            }

            $dry_run = filter_var($_POST['dry_run'] ?? true, FILTER_VALIDATE_BOOLEAN);
            $config  = DGPTM_MB_Config::load();

            if (!$config->is_valid()) {
                wp_send_json_error(['message' => 'Konfiguration unvollstaendig.']);
            }

            $engine = new DGPTM_MB_Billing_Engine($config);

            try {
                $results = $engine->sync_gocardless_mandates($dry_run);
                $this->log(sprintf('Mandats-Sync %s: %d aktualisiert', $dry_run ? 'DRY-RUN' : 'LIVE', $results['updated'] ?? 0), 'info');
                wp_send_json_success($results);
            } catch (\Throwable $e) {
                $this->log('Mandats-Sync Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: Konfiguration speichern                                 */
        /* ============================================================ */

        public function ajax_save_config(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $json = stripslashes($_POST['config_json'] ?? '');
            $data = json_decode($json, true);

            if (!$data) {
                wp_send_json_error(['message' => 'Ungueltiges JSON']);
            }

            update_option(self::OPT_CONFIG, $data, false);
            $this->log('Mitgliedsbeitrag-Konfiguration aktualisiert', 'info');
            wp_send_json_success(['message' => 'Konfiguration gespeichert']);
        }

        /* ============================================================ */
        /* Admin                                                         */
        /* ============================================================ */

        public function add_admin_menu(): void {
            add_submenu_page(
                'dgptm-suite',
                'Mitgliedsbeitrag',
                'Mitgliedsbeitrag',
                'manage_options',
                'dgptm-mitgliedsbeitrag',
                [$this, 'render_admin_page']
            );
        }

        public function render_admin_page(): void {
            $config = DGPTM_MB_Config::load();
            include DGPTM_MB_PATH . 'templates/admin.php';
        }

        /* ============================================================ */
        /* Logging                                                       */
        /* ============================================================ */

        private function log(string $message, string $level = 'info'): void {
            if (class_exists('DGPTM_Logger') && method_exists('DGPTM_Logger', $level)) {
                \DGPTM_Logger::$level($message, 'mitgliedsbeitrag');
            }
        }
    }
}

if (!isset($GLOBALS['dgptm_mitgliedsbeitrag_module_initialized'])) {
    $GLOBALS['dgptm_mitgliedsbeitrag_module_initialized'] = true;
    DGPTM_Mitgliedsbeitrag_Module::get_instance();
}
