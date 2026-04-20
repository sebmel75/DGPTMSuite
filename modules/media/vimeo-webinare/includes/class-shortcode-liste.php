<?php
/**
 * Shortcode [vimeo_webinar_liste] — öffentlicher Frontend-Katalog.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('DGPTM_VW_Shortcode_Liste')) {

    class DGPTM_VW_Shortcode_Liste {

        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) self::$instance = new self();
            return self::$instance;
        }

        private function __construct() {
            add_shortcode('vimeo_webinar_liste', [$this, 'render']);
        }

        public function render($atts): string {
            $user_id = get_current_user_id();
            $is_logged_in = is_user_logged_in();

            $webinars_raw = get_posts([
                'post_type'      => 'vimeo_webinar',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);

            $main = DGPTM_Vimeo_Webinare::get_instance();
            $cookie_progress = $is_logged_in ? [] : $main->get_all_cookie_progress();

            $history = [];
            if ($is_logged_in && $user_id) {
                $history = $main->get_user_webinar_history($user_id, 5);
            }

            ob_start();
            include plugin_dir_path(DGPTM_SUITE_FILE) . 'modules/media/vimeo-webinare/templates/liste.php';
            return ob_get_clean();
        }
    }
}
