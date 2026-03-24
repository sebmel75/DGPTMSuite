<?php
/**
 * Plugin Name: DGPTM - Finanzbericht
 * Description: Finanzauswertung Jahrestagung, Sachkundekurs und Zeitschrift
 * Version:     1.0.0
 * Author:      Sebastian Melzer
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

define('DGPTM_FB_PATH', plugin_dir_path(__FILE__));
define('DGPTM_FB_URL', plugin_dir_url(__FILE__));

// Includes
require_once DGPTM_FB_PATH . 'includes/class-access-logger.php';
require_once DGPTM_FB_PATH . 'includes/class-historical-data.php';
require_once DGPTM_FB_PATH . 'includes/class-zoho-books-client.php';
require_once DGPTM_FB_PATH . 'includes/class-report-builder.php';

if (!class_exists('DGPTM_Finanzbericht')) {

    final class DGPTM_Finanzbericht {

        private static ?self $instance = null;

        const USER_META_KEY = 'dgptm_finanzbericht_access';
        const OPTION_CREDENTIALS = 'dgptm_finanzbericht_credentials';
        const NONCE_ACTION = 'dgptm_finanzbericht_nonce';

        // Verfuegbare Berichte
        const REPORTS = [
            'jahrestagung' => 'Jahrestagung',
            'sachkundekurs' => 'Sachkundekurs ECLS',
            'zeitschrift'   => 'Zeitschrift',
        ];

        public static function get_instance(): self {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            add_action('init', [$this, 'register_shortcode']);
            add_action('wp_ajax_dgptm_fb_get_report', [$this, 'ajax_get_report']);
            add_action('wp_ajax_dgptm_fb_upload_credentials', [$this, 'ajax_upload_credentials']);
            add_action('admin_menu', [$this, 'add_admin_menu']);

            // User-Profile: Berechtigungsfeld
            add_action('show_user_profile', [$this, 'render_user_meta_field']);
            add_action('edit_user_profile', [$this, 'render_user_meta_field']);
            add_action('personal_options_update', [$this, 'save_user_meta_field']);
            add_action('edit_user_profile_update', [$this, 'save_user_meta_field']);
        }

        /* ------------------------------------------------------------ */
        /* Shortcode                                                     */
        /* ------------------------------------------------------------ */

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
                return '<p class="dgptm-fb-error">Kein Zugriff auf Finanzberichte.</p>';
            }

            // Assets laden
            wp_enqueue_style('dgptm-fb', DGPTM_FB_URL . 'assets/css/finanzbericht.css', [], '1.0.0');
            wp_enqueue_script('dgptm-fb', DGPTM_FB_URL . 'assets/js/finanzbericht.js', ['jquery'], '1.0.0', true);
            wp_localize_script('dgptm-fb', 'dgptmFB', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce(self::NONCE_ACTION),
                'access'  => $access,
                'reports' => self::REPORTS,
            ]);

            ob_start();
            include DGPTM_FB_PATH . 'templates/dashboard.php';
            return ob_get_clean();
        }

        /* ------------------------------------------------------------ */
        /* AJAX: Report laden                                            */
        /* ------------------------------------------------------------ */

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

            // Access loggen
            DGPTM_FB_Access_Logger::log($user_id, $report, $year, 'granted');

            $builder = new DGPTM_FB_Report_Builder();
            $data = $builder->get_report($report, $year);

            wp_send_json_success($data);
        }

        /* ------------------------------------------------------------ */
        /* Benutzer-Berechtigungen                                       */
        /* ------------------------------------------------------------ */

        public function get_user_access(int $user_id): array {
            // Admins haben immer vollen Zugriff
            if (user_can($user_id, 'manage_options')) {
                return array_keys(self::REPORTS);
            }

            $meta = get_user_meta($user_id, self::USER_META_KEY, true);
            if (!is_array($meta)) {
                return [];
            }
            return array_intersect($meta, array_keys(self::REPORTS));
        }

        public function render_user_meta_field($user): void {
            if (!current_user_can('manage_options')) {
                return;
            }

            $access = get_user_meta($user->ID, self::USER_META_KEY, true);
            if (!is_array($access)) {
                $access = [];
            }
            ?>
            <h3>Finanzbericht-Zugriff</h3>
            <table class="form-table">
                <tr>
                    <th><label>Berichte</label></th>
                    <td>
                        <?php foreach (self::REPORTS as $key => $label): ?>
                            <label style="display:block;margin-bottom:5px;">
                                <input type="checkbox"
                                       name="dgptm_fb_access[]"
                                       value="<?php echo esc_attr($key); ?>"
                                       <?php checked(in_array($key, $access, true)); ?>>
                                <?php echo esc_html($label); ?>
                            </label>
                        <?php endforeach; ?>
                    </td>
                </tr>
            </table>
            <?php
        }

        public function save_user_meta_field(int $user_id): void {
            if (!current_user_can('manage_options')) {
                return;
            }
            $access = array_map('sanitize_key', $_POST['dgptm_fb_access'] ?? []);
            $access = array_intersect($access, array_keys(self::REPORTS));
            update_user_meta($user_id, self::USER_META_KEY, $access);
        }

        /* ------------------------------------------------------------ */
        /* Admin: Credentials Upload                                     */
        /* ------------------------------------------------------------ */

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

            // Validierung
            $required = ['accounts_domain', 'client_id', 'client_secret', 'refresh_token', 'organization_id'];
            foreach ($required as $key) {
                if (empty($data['zoho'][$key])) {
                    wp_send_json_error(['message' => "Fehlendes Feld: zoho.$key"]);
                }
            }

            update_option(self::OPTION_CREDENTIALS, $data, false); // autoload=false
            wp_send_json_success(['message' => 'Zugangsdaten gespeichert']);
        }
    }
}

// Initialisierung
if (!isset($GLOBALS['dgptm_finanzbericht_initialized'])) {
    $GLOBALS['dgptm_finanzbericht_initialized'] = true;
    DGPTM_Finanzbericht::get_instance();
}
