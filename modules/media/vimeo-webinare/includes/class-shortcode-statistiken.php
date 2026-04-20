<?php
/**
 * Shortcode [vimeo_webinar_statistiken] — Admin-Statistiken.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('DGPTM_VW_Shortcode_Statistiken')) {

    class DGPTM_VW_Shortcode_Statistiken {

        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) self::$instance = new self();
            return self::$instance;
        }

        private function __construct() {
            add_shortcode('vimeo_webinar_statistiken', [$this, 'render']);
        }

        public function render($atts): string {
            if (!is_user_logged_in()) {
                return '<p>Bitte melden Sie sich an.</p>';
            }
            DGPTM_Vimeo_Webinare::get_instance()->enqueue_dashboard_assets('statistiken');

            $repo = DGPTM_VW_Webinar_Repository::get_instance();
            $webinars = $repo->get_all();
            $ids = array_map(function ($w) { return $w['id']; }, $webinars);
            $stats = $repo->get_stats_batch($ids);

            $total_webinars = count($webinars);
            $total_completed = 0;
            $total_in_progress = 0;
            $total_views = 0;
            foreach ($stats as $s) {
                $total_completed += $s['completed'];
                $total_in_progress += $s['in_progress'];
                $total_views += $s['total_views'];
            }
            $avg_rate = $repo->get_average_completion_rate();

            ob_start();
            include plugin_dir_path(DGPTM_SUITE_FILE) . 'modules/media/vimeo-webinare/templates/statistiken.php';
            return ob_get_clean();
        }
    }
}
