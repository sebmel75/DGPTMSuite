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
        /* AJAX: Dashboard                                               */
        /* ============================================================ */

        public function ajax_get_dashboard(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_can_view()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            try {
                $config = DGPTM_FIN_Config::load();

                if (!$config->is_valid()) {
                    wp_send_json_success([
                        'stats'        => ['total_active' => 0, 'by_type' => [], 'billing_status' => []],
                        'last_run'     => [],
                        'config_valid' => false,
                    ]);
                }

                // Mitgliederzahlen aus Cache oder live
                $stats = get_transient('dgptm_fin_member_stats');
                if (false === $stats) {
                    $crm   = new DGPTM_FIN_Zoho_CRM($config);
                    $stats = $crm->get_member_stats();
                    set_transient('dgptm_fin_member_stats', $stats, DAY_IN_SECONDS);
                }

                $last_results = get_option(self::OPT_RESULTS, []);

                wp_send_json_success([
                    'stats'        => $stats,
                    'last_run'     => $last_results,
                    'config_valid' => true,
                ]);
            } catch (\Throwable $e) {
                $this->log('Dashboard-Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: Beitragsabrechnungslauf starten                         */
        /* ============================================================ */

        public function ajax_start_billing(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_has_access()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            try {
                $year          = intval($_POST['year'] ?? date('Y'));
                $dry_run       = filter_var($_POST['dry_run'] ?? true, FILTER_VALIDATE_BOOLEAN);
                $send_invoices = filter_var($_POST['send_invoices'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $contact_ids   = [];
                if (!empty($_POST['contact_ids'])) {
                    $raw = is_array($_POST['contact_ids'])
                        ? $_POST['contact_ids']
                        : explode(',', sanitize_text_field($_POST['contact_ids']));
                    $contact_ids = array_map('trim', array_filter($raw));
                }

                $config = DGPTM_FIN_Config::load();
                if (!$config->is_valid()) {
                    wp_send_json_error(['message' => 'Konfiguration unvollstaendig. Bitte im Settings-Tab konfigurieren.']);
                }

                $engine  = new DGPTM_FIN_Billing_Engine($config);
                $prepare = $engine->prepare($year, $dry_run, $send_invoices, $contact_ids);

                $processor  = new DGPTM_FIN_Chunk_Processor();
                $session_id = $processor->start(
                    'billing',
                    $prepare['members'],
                    $prepare['caches'],
                    $prepare['config']
                );

                $this->log(sprintf(
                    'Billing gestartet: Jahr=%d, %s, %d Mitglieder, Session=%s',
                    $year,
                    $dry_run ? 'DRY-RUN' : 'LIVE',
                    count($prepare['members']),
                    $session_id
                ));

                wp_send_json_success([
                    'session_id' => $session_id,
                    'total'      => count($prepare['members']),
                    'chunk_size' => DGPTM_FIN_Chunk_Processor::DEFAULT_CHUNK_SIZE,
                ]);
            } catch (\Throwable $e) {
                $this->log('Billing-Start Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: Chunk verarbeiten                                       */
        /* ============================================================ */

        public function ajax_process_chunk(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_has_access()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            try {
                $session_id = sanitize_text_field($_POST['session_id'] ?? '');
                if (!$session_id) {
                    wp_send_json_error(['message' => 'Keine Session-ID angegeben.']);
                }

                $processor = new DGPTM_FIN_Chunk_Processor();
                $config    = DGPTM_FIN_Config::load();
                $crm       = new DGPTM_FIN_Zoho_CRM($config);
                $books     = new DGPTM_FIN_Zoho_Books($config);
                $gc        = new DGPTM_FIN_GoCardless($config);

                // Session-Daten lesen um config/caches zu erhalten
                $status = $processor->get_status($session_id);
                if (!$status) {
                    wp_send_json_error(['message' => 'Session nicht gefunden oder abgelaufen.']);
                }

                $session_config = $status['config'];

                $callback = function (array $member) use ($session_config, $config, $crm, $books, $gc, $session_id) {
                    // Caches aus Session laden
                    $session = get_transient(DGPTM_FIN_Chunk_Processor::SESSION_PREFIX . $session_id);
                    $caches  = $session['caches'] ?? [];

                    return DGPTM_FIN_Billing_Engine::process_member(
                        $member,
                        $session_config['year'],
                        $session_config['dry_run'],
                        $session_config['send_invoices'],
                        $caches,
                        $config,
                        $crm,
                        $books,
                        $gc
                    );
                };

                $result = $processor->process_next_chunk($session_id, $callback);

                wp_send_json_success($result);
            } catch (\Throwable $e) {
                $this->log('Chunk-Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: Billing finalisieren                                    */
        /* ============================================================ */

        public function ajax_finalize_billing(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_has_access()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            try {
                $session_id = sanitize_text_field($_POST['session_id'] ?? '');
                if (!$session_id) {
                    wp_send_json_error(['message' => 'Keine Session-ID angegeben.']);
                }

                $processor = new DGPTM_FIN_Chunk_Processor();
                $results   = $processor->finalize($session_id);

                // Zusammenfassung erstellen
                $summary = [
                    'total'     => count($results),
                    'success'   => 0,
                    'skipped'   => 0,
                    'errors'    => 0,
                    'timestamp' => current_time('c'),
                ];
                foreach ($results as $r) {
                    $s = $r['status'] ?? 'error';
                    if ($s === 'success' || $s === 'billed') {
                        $summary['success']++;
                    } elseif ($s === 'skipped') {
                        $summary['skipped']++;
                    } else {
                        $summary['errors']++;
                    }
                }

                // Ergebnisse speichern
                update_option(self::OPT_RESULTS, $results, false);

                // An Billing-History anhaengen
                $history   = get_option(self::OPT_HISTORY, []);
                $history[] = [
                    'timestamp' => current_time('c'),
                    'summary'   => $summary,
                    'user_id'   => get_current_user_id(),
                ];
                // Maximal die letzten 50 Eintraege behalten
                if (count($history) > 50) {
                    $history = array_slice($history, -50);
                }
                update_option(self::OPT_HISTORY, $history, false);

                // Member-Stats Cache invalidieren
                delete_transient('dgptm_fin_member_stats');

                $this->log(sprintf(
                    'Billing finalisiert: %d gesamt, %d erfolgreich, %d uebersprungen, %d Fehler',
                    $summary['total'],
                    $summary['success'],
                    $summary['skipped'],
                    $summary['errors']
                ));

                wp_send_json_success([
                    'summary' => $summary,
                    'results' => $results,
                ]);
            } catch (\Throwable $e) {
                $this->log('Finalize-Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: Billing abbrechen                                       */
        /* ============================================================ */

        public function ajax_cancel_billing(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_has_access()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            try {
                $session_id = sanitize_text_field($_POST['session_id'] ?? '');
                if (!$session_id) {
                    wp_send_json_error(['message' => 'Keine Session-ID angegeben.']);
                }

                $processor = new DGPTM_FIN_Chunk_Processor();
                $processor->cancel($session_id);

                $this->log('Billing abgebrochen: Session ' . $session_id);

                wp_send_json_success(['message' => 'Abrechnungslauf abgebrochen.']);
            } catch (\Throwable $e) {
                $this->log('Cancel-Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: Billing-Status abfragen                                 */
        /* ============================================================ */

        public function ajax_get_billing_status(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_has_access()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            try {
                $session_id = sanitize_text_field($_POST['session_id'] ?? '');
                if (!$session_id) {
                    wp_send_json_error(['message' => 'Keine Session-ID angegeben.']);
                }

                $processor = new DGPTM_FIN_Chunk_Processor();
                $status    = $processor->get_status($session_id);

                if (!$status) {
                    wp_send_json_error(['message' => 'Session nicht gefunden oder abgelaufen.']);
                }

                wp_send_json_success($status);
            } catch (\Throwable $e) {
                $this->log('Status-Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: Mitglieder laden                                        */
        /* ============================================================ */

        public function ajax_get_members(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_has_access()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            try {
                $year    = intval($_POST['year'] ?? date('Y'));
                $filters = [];
                if (!empty($_POST['type'])) {
                    $filters['type'] = sanitize_text_field($_POST['type']);
                }
                if (!empty($_POST['status'])) {
                    $filters['status'] = sanitize_text_field($_POST['status']);
                }
                if (!empty($_POST['billing_status'])) {
                    $filters['billing_status'] = sanitize_text_field($_POST['billing_status']);
                }

                $config     = DGPTM_FIN_Config::load();
                $member_list = new DGPTM_FIN_Member_List($config);
                $members    = $member_list->get_members($year, $filters);

                wp_send_json_success(['members' => $members]);
            } catch (\Throwable $e) {
                $this->log('Members-Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: Billing-Ergebnisse / Historie                           */
        /* ============================================================ */

        public function ajax_get_results(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_can_view()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            try {
                $history = get_option(self::OPT_HISTORY, []);

                wp_send_json_success(['history' => $history]);
            } catch (\Throwable $e) {
                $this->log('Results-Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: GoCardless-Zahlungen verarbeiten                        */
        /* ============================================================ */

        public function ajax_process_payments(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_has_access()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            try {
                $dry_run = filter_var($_POST['dry_run'] ?? true, FILTER_VALIDATE_BOOLEAN);
                $config  = DGPTM_FIN_Config::load();

                if (!$config->is_valid()) {
                    wp_send_json_error(['message' => 'Konfiguration unvollstaendig.']);
                }

                $engine  = new DGPTM_FIN_Billing_Engine($config);
                $results = $engine->process_gocardless_payments($dry_run);

                $this->log(sprintf(
                    'Zahlungsverarbeitung %s: %d Rechnungen',
                    $dry_run ? 'DRY-RUN' : 'LIVE',
                    $results['total'] ?? 0
                ));

                wp_send_json_success($results);
            } catch (\Throwable $e) {
                $this->log('Payment-Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: GoCardless Mandats-Sync                                 */
        /* ============================================================ */

        public function ajax_sync_mandates(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_has_access()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            try {
                $dry_run = filter_var($_POST['dry_run'] ?? true, FILTER_VALIDATE_BOOLEAN);
                $config  = DGPTM_FIN_Config::load();

                if (!$config->is_valid()) {
                    wp_send_json_error(['message' => 'Konfiguration unvollstaendig.']);
                }

                $engine  = new DGPTM_FIN_Billing_Engine($config);
                $results = $engine->sync_gocardless_mandates($dry_run);

                $this->log(sprintf(
                    'Mandats-Sync %s: %d aktualisiert',
                    $dry_run ? 'DRY-RUN' : 'LIVE',
                    $results['updated'] ?? 0
                ));

                wp_send_json_success($results);
            } catch (\Throwable $e) {
                $this->log('Mandats-Sync Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: Offene Rechnungen laden                                 */
        /* ============================================================ */

        public function ajax_get_invoices(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_has_access()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            try {
                $config  = DGPTM_FIN_Config::load();
                $manager = new DGPTM_FIN_Invoice_Manager($config);
                $invoices = $manager->get_open_invoices();

                wp_send_json_success(['invoices' => $invoices]);
            } catch (\Throwable $e) {
                $this->log('Invoices-Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: Rechnungs-Aktion                                        */
        /* ============================================================ */

        public function ajax_invoice_action(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_has_access()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            try {
                $action_type = sanitize_text_field($_POST['action_type'] ?? '');
                $invoice_id  = sanitize_text_field($_POST['invoice_id'] ?? '');

                if (!$action_type || !$invoice_id) {
                    wp_send_json_error(['message' => 'action_type und invoice_id sind Pflichtfelder.']);
                }

                $config  = DGPTM_FIN_Config::load();
                $manager = new DGPTM_FIN_Invoice_Manager($config);

                switch ($action_type) {
                    case 'collect':
                        $mandate_id = sanitize_text_field($_POST['mandate_id'] ?? '');
                        $contact_id = sanitize_text_field($_POST['contact_id'] ?? '');
                        $result = $manager->collect_payment($invoice_id, $mandate_id, $contact_id);
                        break;

                    case 'apply_credit':
                        $collect_remainder = filter_var($_POST['collect_remainder'] ?? false, FILTER_VALIDATE_BOOLEAN);
                        $mandate_id        = sanitize_text_field($_POST['mandate_id'] ?? '');
                        $contact_id        = sanitize_text_field($_POST['contact_id'] ?? '');
                        $result = $manager->apply_credit($invoice_id, $collect_remainder, $mandate_id, $contact_id);
                        break;

                    case 'chargeback':
                        $cb_action       = sanitize_text_field($_POST['cb_action'] ?? 'both');
                        $books_payment_id = sanitize_text_field($_POST['books_payment_id'] ?? '');
                        $result = $manager->handle_chargeback($invoice_id, $cb_action, $books_payment_id);
                        break;

                    case 'send_draft':
                        $result = $manager->send_without_credit($invoice_id);
                        break;

                    default:
                        wp_send_json_error(['message' => 'Unbekannter action_type: ' . $action_type]);
                        return; // unreachable, but explicit
                }

                $this->log(sprintf('Invoice-Aktion: %s auf %s', $action_type, $invoice_id));

                wp_send_json_success($result);
            } catch (\Throwable $e) {
                $this->log('Invoice-Action Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: Finanzbericht                                           */
        /* ============================================================ */

        public function ajax_get_report(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_can_view()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            try {
                $report = sanitize_key($_POST['report'] ?? '');
                $year   = intval($_POST['year'] ?? date('Y'));

                if (!$report) {
                    wp_send_json_error(['message' => 'Kein Berichtstyp angegeben.']);
                }

                // Mitgliederzahlen separat behandeln
                if ($report === 'mitgliederzahl') {
                    $config = DGPTM_FIN_Config::load();
                    if (!$config->is_valid()) {
                        wp_send_json_error(['message' => 'Konfiguration unvollstaendig.']);
                    }
                    $crm   = new DGPTM_FIN_Zoho_CRM($config);
                    $stats = $crm->get_member_stats();

                    DGPTM_FIN_Access_Logger::log(get_current_user_id(), $report, $year, 'granted');

                    wp_send_json_success($stats);
                }

                // Finanzbericht via Books API
                $config  = DGPTM_FIN_Config::load();
                $books   = new DGPTM_FIN_Zoho_Books($config);
                $builder = new DGPTM_FIN_Report_Builder($books);
                $data    = $builder->get_report($report, $year);

                DGPTM_FIN_Access_Logger::log(get_current_user_id(), $report, $year, 'granted');

                wp_send_json_success($data);
            } catch (\Throwable $e) {
                $this->log('Report-Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: Cache aktualisieren                                     */
        /* ============================================================ */

        public function ajax_refresh_cache(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_has_access()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            try {
                delete_transient('dgptm_fin_member_stats');

                $config = DGPTM_FIN_Config::load();
                if (!$config->is_valid()) {
                    wp_send_json_error(['message' => 'Konfiguration unvollstaendig.']);
                }

                $crm   = new DGPTM_FIN_Zoho_CRM($config);
                $stats = $crm->get_member_stats();
                set_transient('dgptm_fin_member_stats', $stats, DAY_IN_SECONDS);

                $this->log('Cache manuell aktualisiert');

                wp_send_json_success([
                    'stats'   => $stats,
                    'message' => 'Cache erfolgreich aktualisiert.',
                ]);
            } catch (\Throwable $e) {
                $this->log('Cache-Refresh Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: Schatzmeister CRUD                                      */
        /* ============================================================ */

        public function ajax_treasurer_crud(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_has_access()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            try {
                $action_type = sanitize_text_field($_POST['action_type'] ?? '');

                $config    = DGPTM_FIN_Config::load();
                $treasurer = new DGPTM_FIN_Treasurer($config);

                switch ($action_type) {
                    case 'get':
                        $entries = $treasurer->get_entries();
                        wp_send_json_success($entries);
                        break;

                    case 'mark_transferred':
                        $module    = sanitize_text_field($_POST['module'] ?? '');
                        $record_id = sanitize_text_field($_POST['record_id'] ?? '');
                        $transition_id = sanitize_text_field($_POST['transition_id'] ?? '');

                        if (!$module || !$record_id) {
                            wp_send_json_error(['message' => 'module und record_id sind Pflichtfelder.']);
                        }

                        $success = $treasurer->mark_transferred($module, $record_id, $transition_id);

                        if ($success) {
                            $this->log(sprintf('Schatzmeister: %s/%s als ueberwiesen markiert', $module, $record_id));
                            wp_send_json_success(['message' => 'Erfolgreich als ueberwiesen markiert.']);
                        } else {
                            wp_send_json_error(['message' => 'Markierung fehlgeschlagen.']);
                        }
                        break;

                    default:
                        wp_send_json_error(['message' => 'Unbekannter action_type: ' . $action_type]);
                }
            } catch (\Throwable $e) {
                $this->log('Treasurer-Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: Konfiguration speichern                                 */
        /* ============================================================ */

        public function ajax_save_config(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_is_admin()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            try {
                $json = stripslashes($_POST['config'] ?? '');
                $data = json_decode($json, true);

                if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
                    wp_send_json_error(['message' => 'Ungueltiges JSON: ' . json_last_error_msg()]);
                }

                update_option(self::OPT_CONFIG, $data, false);

                $this->log('Finanzen-Konfiguration aktualisiert');

                wp_send_json_success(['message' => 'Konfiguration gespeichert.']);
            } catch (\Throwable $e) {
                $this->log('Config-Save Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: Zoho Books Credentials hochladen                        */
        /* ============================================================ */

        public function ajax_upload_credentials(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_is_admin()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            try {
                $json = stripslashes($_POST['credentials'] ?? '');
                $credentials = json_decode($json, true);

                if (!is_array($credentials) || json_last_error() !== JSON_ERROR_NONE) {
                    wp_send_json_error(['message' => 'Ungueltiges JSON: ' . json_last_error_msg()]);
                }

                // Pflichtfelder pruefen
                $required = ['client_id', 'client_secret', 'refresh_token'];
                foreach ($required as $field) {
                    if (empty($credentials[$field])) {
                        wp_send_json_error(['message' => 'Pflichtfeld fehlt: ' . $field]);
                    }
                }

                // In bestehende Config unter zoho.books einhaengen
                $config_data = get_option(self::OPT_CONFIG, []);
                if (!is_array($config_data)) {
                    $config_data = [];
                }
                if (!isset($config_data['zoho'])) {
                    $config_data['zoho'] = [];
                }
                $config_data['zoho']['books'] = $credentials;
                update_option(self::OPT_CONFIG, $config_data, false);

                $this->log('Zoho Books Credentials aktualisiert');

                wp_send_json_success(['message' => 'Credentials gespeichert.']);
            } catch (\Throwable $e) {
                $this->log('Credentials-Upload Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* AJAX: Historische Daten importieren                           */
        /* ============================================================ */

        public function ajax_import_historical(): void {
            check_ajax_referer(self::NONCE, 'nonce');
            if (!$this->user_is_admin()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            try {
                $json = stripslashes($_POST['data'] ?? '');
                $data = json_decode($json, true);

                if (!is_array($data) || json_last_error() !== JSON_ERROR_NONE) {
                    wp_send_json_error(['message' => 'Ungueltiges JSON: ' . json_last_error_msg()]);
                }

                $count = DGPTM_FIN_Historical_Data::import($data);

                $this->log(sprintf('Historische Daten importiert: %d Datensaetze', $count));

                wp_send_json_success([
                    'count'   => $count,
                    'message' => sprintf('%d Datensaetze importiert.', $count),
                ]);
            } catch (\Throwable $e) {
                $this->log('Import-Fehler: ' . $e->getMessage(), 'error');
                wp_send_json_error(['message' => 'Fehler: ' . $e->getMessage()]);
            }
        }

        /* ============================================================ */
        /* Cron                                                          */
        /* ============================================================ */

        public function cron_nightly_refresh(): void {
            $this->log('Naechtlicher Refresh gestartet');

            try {
                delete_transient('dgptm_fin_member_stats');

                $config = DGPTM_FIN_Config::load();
                if (!$config->is_valid()) {
                    $this->log('Naechtlicher Refresh abgebrochen: Konfiguration unvollstaendig', 'warning');
                    return;
                }

                $crm   = new DGPTM_FIN_Zoho_CRM($config);
                $stats = $crm->get_member_stats();
                set_transient('dgptm_fin_member_stats', $stats, DAY_IN_SECONDS);

                $this->log(sprintf(
                    'Naechtlicher Refresh abgeschlossen: %d aktive Mitglieder',
                    $stats['total_active'] ?? 0
                ));
            } catch (\Throwable $e) {
                $this->log('Naechtlicher Refresh Fehler: ' . $e->getMessage(), 'error');
            }
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
