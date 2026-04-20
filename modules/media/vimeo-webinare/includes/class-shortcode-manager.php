<?php
/**
 * Shortcode [vimeo_webinar_manager] — Admin-Manager für Webinare.
 *
 * Rendert Liste + Inline-Editor. Alle schreibenden AJAX-Endpoints
 * prüfen Nonce + user_can_manage_webinars().
 */

if (!defined('ABSPATH')) exit;

if (!class_exists('DGPTM_VW_Shortcode_Manager')) {

    class DGPTM_VW_Shortcode_Manager {

        const NONCE_ACTION = 'dgptm_vw_manager';

        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) self::$instance = new self();
            return self::$instance;
        }

        private function __construct() {
            add_shortcode('vimeo_webinar_manager', [$this, 'render']);
            add_action('wp_ajax_dgptm_vw_save',    [$this, 'ajax_save']);
            add_action('wp_ajax_dgptm_vw_delete',  [$this, 'ajax_delete']);
            add_action('wp_ajax_dgptm_vw_get_row', [$this, 'ajax_get_row']);
        }

        public function render($atts): string {
            if (!is_user_logged_in()) {
                return '<p>Bitte melden Sie sich an.</p>';
            }
            // Defense-in-Depth: Assets hier registrieren, falls enqueue_assets()
            // den Shortcode-Kontext nicht erkannt hat (Dashboard-Tab vs. post_content).
            DGPTM_Vimeo_Webinare::get_instance()->enqueue_dashboard_assets('manager');

            $repo = DGPTM_VW_Webinar_Repository::get_instance();
            $webinars = $repo->get_all();
            $ids = array_map(function ($w) { return $w['id']; }, $webinars);
            $stats = $repo->get_stats_batch($ids);

            $nonce = wp_create_nonce(self::NONCE_ACTION);

            ob_start();
            include plugin_dir_path(DGPTM_SUITE_FILE) . 'modules/media/vimeo-webinare/templates/manager-liste.php';
            return ob_get_clean();
        }

        public function ajax_save() {
            $this->require_auth();

            $result = DGPTM_VW_Webinar_Repository::get_instance()->save($_POST);
            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }
            $webinar = DGPTM_VW_Webinar_Repository::get_instance()->get($result);
            $stats   = DGPTM_VW_Webinar_Repository::get_instance()->get_stats_batch([$result]);

            wp_send_json_success([
                'id'   => $result,
                'row'  => $this->render_row($webinar, $stats[$result]),
            ]);
        }

        public function ajax_delete() {
            $this->require_auth();

            $id = intval($_POST['post_id'] ?? 0);
            if ($id <= 0) wp_send_json_error('Ungültige ID');

            if (!DGPTM_VW_Webinar_Repository::get_instance()->trash($id)) {
                wp_send_json_error('Löschen fehlgeschlagen');
            }
            wp_send_json_success(['id' => $id]);
        }

        public function ajax_get_row() {
            $this->require_auth();

            $id = intval($_POST['post_id'] ?? 0);
            $webinar = DGPTM_VW_Webinar_Repository::get_instance()->get($id);
            if (!$webinar) wp_send_json_error('Nicht gefunden');
            $stats = DGPTM_VW_Webinar_Repository::get_instance()->get_stats_batch([$id]);
            wp_send_json_success(['row' => $this->render_row($webinar, $stats[$id])]);
        }

        private function require_auth() {
            check_ajax_referer(self::NONCE_ACTION, 'nonce');
            $main = DGPTM_Vimeo_Webinare::get_instance();
            if (!$main->user_can_manage_webinars()) {
                wp_send_json_error('Keine Berechtigung');
            }
        }

        private function render_row(array $webinar, array $stats): string {
            ob_start();
            $w = $webinar;
            $s = $stats;
            include plugin_dir_path(DGPTM_SUITE_FILE) . 'modules/media/vimeo-webinare/templates/manager-row.php';
            return ob_get_clean();
        }
    }
}
