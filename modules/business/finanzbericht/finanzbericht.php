<?php
/**
 * Plugin Name: DGPTM - Finanzbericht
 * Description: Finanzauswertung mit rollenbasiertem Zugang (ACF: schatzmeister/praesident/geschaeftsstelle)
 * Version:     1.1.0
 * Author:      Sebastian Melzer
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('DGPTM_FB_PATH', plugin_dir_path(__FILE__));
define('DGPTM_FB_URL', plugin_dir_url(__FILE__));

require_once DGPTM_FB_PATH . 'includes/class-access-logger.php';
require_once DGPTM_FB_PATH . 'includes/class-historical-data.php';
require_once DGPTM_FB_PATH . 'includes/class-zoho-books-client.php';
require_once DGPTM_FB_PATH . 'includes/class-report-builder.php';

if (!class_exists('DGPTM_Finanzbericht')) {

    final class DGPTM_Finanzbericht {

        private static ?self $instance = null;

        const OPTION_CREDENTIALS = 'dgptm_finanzbericht_credentials';
        const NONCE_ACTION = 'dgptm_finanzbericht_nonce';

        /**
         * Rollen-basierte Berichte:
         * - praesident: Alle Berichte + Mitgliederzahlen
         * - schatzmeister: Alle Berichte + Mitgliederzahlen + Mitgliedsbeitrag
         * - geschaeftsstelle: Alle Berichte + Mitgliederzahlen
         */
        const REPORTS = [
            'jahrestagung'    => 'Jahrestagung',
            'sachkundekurs'   => 'Sachkundekurs ECLS',
            'zeitschrift'     => 'Zeitschrift',
            'mitgliederzahl'  => 'Mitgliederzahlen',
        ];

        /**
         * ACF-Felder fuer rollenbasierte Berechtigungen
         * Werden als User-Meta geprueft (ACF User Fields)
         */
        const ROLE_FIELDS = ['schatzmeister', 'praesident', 'geschaeftsstelle'];

        public static function get_instance(): self {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            add_action('init', [$this, 'register_shortcode']);
            add_action('wp_ajax_dgptm_fb_get_report', [$this, 'ajax_get_report']);
            add_action('wp_ajax_dgptm_fb_get_member_stats', [$this, 'ajax_get_member_stats']);
            add_action('wp_ajax_dgptm_fb_upload_credentials', [$this, 'ajax_upload_credentials']);
            add_action('wp_ajax_dgptm_fb_import_historical', [$this, 'ajax_import_historical']);
            add_action('wp_ajax_dgptm_fb_refresh_cache', [$this, 'ajax_refresh_cache']);
            add_action('admin_menu', [$this, 'add_admin_menu']);

            // Naechtlicher Cron: Daten um 3:00 Uhr aktualisieren
            add_action('dgptm_fb_nightly_refresh', [$this, 'cron_refresh_all_caches']);
            if (!wp_next_scheduled('dgptm_fb_nightly_refresh')) {
                $next_3am = strtotime('tomorrow 03:00:00');
                wp_schedule_event($next_3am, 'daily', 'dgptm_fb_nightly_refresh');
            }

        }

        /* ============================================================ */
        /* Berechtigungen (ACF User-Felder: schatzmeister, praesident,   */
        /* geschaeftsstelle — bereits in ACF-Gruppe "Berechtigungen")    */
        /* ============================================================ */

        /**
         * Rolle des Users ermitteln (erste passende)
         */
        public function get_user_role(int $user_id): string {
            if (user_can($user_id, 'manage_options')) {
                return 'admin';
            }

            foreach (self::ROLE_FIELDS as $field) {
                $val = get_field($field, 'user_' . $user_id);
                if ($val) {
                    return $field;
                }
            }

            return '';
        }

        /**
         * Zugriffsliste fuer einen User
         */
        public function get_user_access(int $user_id): array {
            $role = $this->get_user_role($user_id);

            if (!$role) {
                return [];
            }

            // Alle Rollen sehen alle Berichte
            return array_keys(self::REPORTS);
        }

        /**
         * Prueft ob User Schatzmeister-Tools sehen darf
         */
        public function user_is_schatzmeister(int $user_id): bool {
            $role = $this->get_user_role($user_id);
            return in_array($role, ['admin', 'schatzmeister'], true);
        }

        /* ============================================================ */
        /* Shortcode                                                     */
        /* ============================================================ */

        public function register_shortcode(): void {
            add_shortcode('dgptm_finanzbericht', [$this, 'render_shortcode']);
        }

        public function render_shortcode($atts): string {
            if (!is_user_logged_in()) {
                return '<p class="dgptm-fb-error">Bitte einloggen.</p>';
            }

            $user_id = get_current_user_id();
            $access = $this->get_user_access($user_id);

            if (empty($access)) {
                return '<p class="dgptm-fb-error">Kein Zugriff auf Finanzberichte. Benoetigte Rolle: Praesident, Schatzmeister oder Geschaeftsstelle.</p>';
            }

            $role = $this->get_user_role($user_id);
            $is_schatzmeister = $this->user_is_schatzmeister($user_id);

            $is_ajax  = defined('DOING_AJAX') && DOING_AJAX;
            $version  = '1.1.0.' . gmdate('ymd');
            $css_url  = DGPTM_FB_URL . 'assets/css/finanzbericht.css?ver=' . $version;
            $js_url   = DGPTM_FB_URL . 'assets/js/finanzbericht.js?ver=' . $version;
            $ajax_url = esc_url(admin_url('admin-ajax.php'));
            $nonce    = wp_create_nonce(self::NONCE_ACTION);

            $localize = wp_json_encode([
                'ajaxUrl'         => admin_url('admin-ajax.php'),
                'nonce'           => $nonce,
                'access'          => $access,
                'reports'         => self::REPORTS,
                'role'            => $role,
                'isSchatzmeister' => $is_schatzmeister,
            ]);

            if ($is_ajax) {
                $inline_assets = "<script>(function(){if(window.dgptmFBLoaded)return;window.dgptmFBLoaded=true;window.dgptmFB={$localize};if(!document.querySelector('link[href*=\"finanzbericht\"]')){var l=document.createElement('link');l.rel='stylesheet';l.href='{$css_url}';document.head.appendChild(l);}if(!document.querySelector('script[src*=\"finanzbericht\"]')){var s=document.createElement('script');s.src='{$js_url}';document.body.appendChild(s);}})();</script>";
            } else {
                wp_enqueue_style('dgptm-fb', DGPTM_FB_URL . 'assets/css/finanzbericht.css', [], $version);
                wp_enqueue_script('dgptm-fb', DGPTM_FB_URL . 'assets/js/finanzbericht.js', ['jquery'], $version, true);
                wp_localize_script('dgptm-fb', 'dgptmFB', json_decode($localize, true));
                $inline_assets = '';
            }

            ob_start();
            include DGPTM_FB_PATH . 'templates/dashboard.php';
            return ob_get_clean() . $inline_assets;
        }

        /* ============================================================ */
        /* AJAX: Report laden                                            */
        /* ============================================================ */

        public function ajax_get_report(): void {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            $user_id = get_current_user_id();
            $report  = sanitize_key($_POST['report'] ?? '');
            $year    = intval($_POST['year'] ?? date('Y'));

            if (!$report || !isset(self::REPORTS[$report])) {
                wp_send_json_error(['message' => 'Ungueltiger Bericht']);
            }

            $access = $this->get_user_access($user_id);
            if (!in_array($report, $access, true)) {
                DGPTM_FB_Access_Logger::log($user_id, $report, $year, 'denied');
                wp_send_json_error(['message' => 'Kein Zugriff']);
            }

            DGPTM_FB_Access_Logger::log($user_id, $report, $year, 'granted');

            // Mitgliederzahlen separat behandeln
            if ($report === 'mitgliederzahl') {
                $data = $this->get_member_stats_data();
                wp_send_json_success($data);
            }

            $builder = new DGPTM_FB_Report_Builder();
            $data = $builder->get_report($report, $year);

            wp_send_json_success($data);
        }

        /* ============================================================ */
        /* AJAX: Mitgliederzahlen                                        */
        /* ============================================================ */

        public function ajax_get_member_stats(): void {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            $user_id = get_current_user_id();
            if (empty($this->get_user_access($user_id))) {
                wp_send_json_error(['message' => 'Kein Zugriff']);
            }

            $data = $this->get_member_stats_data();
            wp_send_json_success($data);
        }

        private function get_member_stats_data(bool $force_refresh = false): array {
            // Transient-Cache: 24 Stunden (wird naechtlich per Cron aktualisiert)
            if (!$force_refresh) {
                $cached = get_transient('dgptm_fb_member_stats');
                if (false !== $cached) {
                    $cached['source'] = 'cache';
                    return $cached;
                }
            }

            $data = [
                'title'  => 'Mitgliederzahlen',
                'source' => 'live',
            ];

            // Zoho CRM abfragen ueber crm-abruf
            if (!class_exists('DGPTM_Zoho_Plugin')) {
                $data['error'] = 'CRM-Modul nicht verfuegbar';
                return $data;
            }

            $crm = \DGPTM_Zoho_Plugin::get_instance();
            $token = $crm->get_oauth_token();

            if (!$token) {
                $data['error'] = 'Kein CRM OAuth-Token verfuegbar';
                return $data;
            }

            $api_url = get_option('dgptm_zoho_api_url', 'https://www.zohoapis.eu');

            // COQL Abfrage: Aktive Mitglieder nach Typ
            $stats = $this->query_member_stats($token, $api_url);
            $data = array_merge($data, $stats);

            set_transient('dgptm_fb_member_stats', $data, DAY_IN_SECONDS);
            return $data;
        }

        private function query_member_stats(string $token, string $api_url): array {
            $result = [
                'total_active' => 0,
                'by_type' => [],
                'billing_status' => [],
                'timestamp' => current_time('c'),
            ];

            // Abfrage 1: Mitglieder nach Typ
            $coql = "SELECT COUNT(id) as cnt, Membership_Type FROM Contacts WHERE Mitglied = true AND Contact_Status in ('Aktiv', 'Freigestellt') GROUP BY Membership_Type";
            $response = wp_remote_post($api_url . '/crm/v7/coql', [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode(['select_query' => $coql]),
                'timeout' => 30,
            ]);

            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (!empty($body['data'])) {
                    foreach ($body['data'] as $row) {
                        $type = $row['Membership_Type'] ?? 'Unbekannt';
                        $count = (int) ($row['cnt'] ?? 0);
                        $result['by_type'][$type] = $count;
                        $result['total_active'] += $count;
                    }
                }
            }

            // Abfrage 2: Beitragslauf-Status (letztesBeitragsjahr)
            $current_year = (int) date('Y');
            $coql2 = "SELECT COUNT(id) as cnt, letztesBeitragsjahr FROM Contacts WHERE Mitglied = true AND Contact_Status in ('Aktiv', 'Freigestellt') GROUP BY letztesBeitragsjahr";
            $response2 = wp_remote_post($api_url . '/crm/v7/coql', [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $token,
                    'Content-Type'  => 'application/json',
                ],
                'body' => wp_json_encode(['select_query' => $coql2]),
                'timeout' => 30,
            ]);

            if (!is_wp_error($response2)) {
                $body2 = json_decode(wp_remote_retrieve_body($response2), true);
                $billed_current = 0;
                $billed_previous = 0;
                $never_billed = 0;

                if (!empty($body2['data'])) {
                    foreach ($body2['data'] as $row) {
                        $year = $row['letztesBeitragsjahr'] ?? null;
                        $count = (int) ($row['cnt'] ?? 0);

                        if ($year === null || $year === '' || $year === 'null') {
                            $never_billed += $count;
                        } elseif ((int) $year >= $current_year) {
                            $billed_current += $count;
                        } else {
                            $billed_previous += $count;
                        }
                    }
                }

                $result['billing_status'] = [
                    'current_year'    => $current_year,
                    'billed_current'  => $billed_current,
                    'billed_previous' => $billed_previous,
                    'never_billed'    => $never_billed,
                    'pending'         => $result['total_active'] - $billed_current,
                ];
            }

            return $result;
        }

        /* ============================================================ */
        /* AJAX: Historische Daten importieren                           */
        /* ============================================================ */

        public function ajax_import_historical(): void {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $json_raw = stripslashes($_POST['import_json'] ?? '');
            $data = json_decode($json_raw, true);

            if (!$data || !is_array($data)) {
                wp_send_json_error(['message' => 'Ungueltiges JSON-Format. Erwartet: {"jahrestagung": {"2024": {...}}, ...}']);
            }

            $imported = DGPTM_FB_Historical_Data::import($data);
            wp_send_json_success(['message' => "Import erfolgreich: $imported Datensaetze", 'count' => $imported]);
        }

        /* ============================================================ */
        /* AJAX: Cache manuell aktualisieren                             */
        /* ============================================================ */

        public function ajax_refresh_cache(): void {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            $user_id = get_current_user_id();
            if (empty($this->get_user_access($user_id))) {
                wp_send_json_error(['message' => 'Kein Zugriff']);
            }

            // Cache loeschen und neu laden
            delete_transient('dgptm_fb_member_stats');
            $data = $this->get_member_stats_data(true);
            $data['source'] = 'refreshed';

            if (class_exists('DGPTM_Logger')) {
                \DGPTM_Logger::info('Finanzbericht Cache manuell aktualisiert', 'finanzbericht');
            }

            wp_send_json_success($data);
        }

        /**
         * Cron: Naechtliche Aktualisierung aller Caches
         */
        public function cron_refresh_all_caches(): void {
            delete_transient('dgptm_fb_member_stats');
            $this->get_member_stats_data(true);

            if (class_exists('DGPTM_Logger')) {
                \DGPTM_Logger::info('Finanzbericht Caches naechtlich aktualisiert', 'finanzbericht');
            }
        }

        /* ============================================================ */
        /* Admin: Credentials + Import                                   */
        /* ============================================================ */

        public function add_admin_menu(): void {
            add_submenu_page(
                'dgptm-suite',
                'Finanzbericht',
                'Finanzbericht',
                'manage_options',
                'dgptm-finanzbericht',
                [$this, 'render_admin_page']
            );
        }

        public function render_admin_page(): void {
            $has_credentials = !empty(get_option(self::OPTION_CREDENTIALS));
            $log_entries = DGPTM_FB_Access_Logger::get_recent(50);
            include DGPTM_FB_PATH . 'templates/admin.php';
        }

        public function ajax_upload_credentials(): void {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $json_raw = stripslashes($_POST['credentials_json'] ?? '');
            $data = json_decode($json_raw, true);

            if (!$data || !isset($data['zoho'])) {
                wp_send_json_error(['message' => 'Ungueltiges JSON-Format']);
            }

            $required = ['accounts_domain', 'client_id', 'client_secret', 'refresh_token', 'organization_id'];
            foreach ($required as $key) {
                if (empty($data['zoho'][$key])) {
                    wp_send_json_error(['message' => "Fehlendes Feld: zoho.$key"]);
                }
            }

            update_option(self::OPTION_CREDENTIALS, $data, false);
            wp_send_json_success(['message' => 'Zugangsdaten gespeichert']);
        }
    }
}

if (!isset($GLOBALS['dgptm_finanzbericht_initialized'])) {
    $GLOBALS['dgptm_finanzbericht_initialized'] = true;
    DGPTM_Finanzbericht::get_instance();
}
