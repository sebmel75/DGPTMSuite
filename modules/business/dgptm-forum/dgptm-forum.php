<?php
/**
 * Plugin Name: DGPTM - Forum
 * Description: Diskussionsforum mit Arbeitsgemeinschaften, Themen und verschachtelten Antworten
 * Version: 1.0.0
 * Author: Sebastian Melzer
 */
if (!defined('ABSPATH')) exit;

define('DGPTM_FORUM_VERSION', '1.0.0');
define('DGPTM_FORUM_PATH', plugin_dir_path(__FILE__));
define('DGPTM_FORUM_URL', plugin_dir_url(__FILE__));

if (!class_exists('DGPTM_Forum')) {

    class DGPTM_Forum {

        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->load_dependencies();
            $this->init_hooks();
        }

        private function load_dependencies() {
            require_once DGPTM_FORUM_PATH . 'includes/class-forum-installer.php';
            require_once DGPTM_FORUM_PATH . 'includes/class-forum-permissions.php';
            require_once DGPTM_FORUM_PATH . 'includes/class-forum-ag-manager.php';

            if (file_exists(DGPTM_FORUM_PATH . 'includes/class-forum-notifications.php')) {
                require_once DGPTM_FORUM_PATH . 'includes/class-forum-notifications.php';
            }
            if (file_exists(DGPTM_FORUM_PATH . 'includes/class-forum-renderer.php')) {
                require_once DGPTM_FORUM_PATH . 'includes/class-forum-renderer.php';
            }
            if (file_exists(DGPTM_FORUM_PATH . 'includes/class-forum-admin-renderer.php')) {
                require_once DGPTM_FORUM_PATH . 'includes/class-forum-admin-renderer.php';
            }
            if (file_exists(DGPTM_FORUM_PATH . 'includes/class-forum-ajax.php')) {
                require_once DGPTM_FORUM_PATH . 'includes/class-forum-ajax.php';
            }
        }

        private function init_hooks() {
            add_action('init', [$this, 'ensure_tables'], 1);
            add_action('init', [$this, 'register_shortcodes']);

            // Forum view AJAX actions
            $forum_actions = [
                'dgptm_forum_load_view',
                'dgptm_forum_load_thread',
                'dgptm_forum_create_thread',
                'dgptm_forum_create_reply',
                'dgptm_forum_edit_post',
                'dgptm_forum_delete_post',
                'dgptm_forum_upload_file',
                'dgptm_forum_subscribe',
                'dgptm_forum_unsubscribe',
            ];

            // Admin AJAX actions
            $admin_actions = [
                'dgptm_forum_admin_save_ag',
                'dgptm_forum_admin_delete_ag',
                'dgptm_forum_admin_add_member',
                'dgptm_forum_admin_remove_member',
                'dgptm_forum_admin_save_topic',
                'dgptm_forum_admin_delete_topic',
                'dgptm_forum_admin_grant_access',
                'dgptm_forum_admin_revoke_access',
                'dgptm_forum_admin_search_users',
                'dgptm_forum_admin_toggle_pin',
                'dgptm_forum_admin_close_thread',
                'dgptm_forum_admin_set_forum_admin',
                'dgptm_forum_admin_load_tab',
            ];

            foreach (array_merge($forum_actions, $admin_actions) as $action) {
                add_action('wp_ajax_' . $action, [$this, 'handle_ajax']);
            }

            add_action('wp_enqueue_scripts', [$this, 'maybe_enqueue_assets']);
        }

        public function ensure_tables() {
            $db_version = get_option('dgptm_forum_db_version', '');
            if ($db_version !== DGPTM_FORUM_VERSION) {
                DGPTM_Forum_Installer::install();
                update_option('dgptm_forum_db_version', DGPTM_FORUM_VERSION);
            }
        }

        public function register_shortcodes() {
            add_shortcode('dgptm-forum', [$this, 'shortcode_forum']);
            add_shortcode('dgptm-forum-admin', [$this, 'shortcode_forum_admin']);
            add_shortcode('is-forum-admin', [$this, 'shortcode_is_forum_admin']);
        }

        public function shortcode_forum($atts = []) {
            if (!is_user_logged_in()) {
                return 'Bitte anmelden.';
            }

            $this->enqueue_assets();

            return '<div class="dgptm-forum-wrap">'
                . '<div class="dgptm-forum-content">'
                . '<p>Forum wird geladen...</p>'
                . '</div>'
                . '</div>';
        }

        public function shortcode_forum_admin($atts = []) {
            if (!is_user_logged_in()) {
                return '';
            }

            if (!DGPTM_Forum_Permissions::is_forum_admin()) {
                return '<p>Keine Berechtigung.</p>';
            }

            $this->enqueue_assets();

            $html = '<div class="dgptm-forum-admin-wrap">';
            $html .= '<nav class="dgptm-forum-admin-tabs">';
            $html .= '<a href="#" class="dgptm-forum-admin-tab active" data-tab="ags">AGs verwalten</a>';
            $html .= '<a href="#" class="dgptm-forum-admin-tab" data-tab="themen">Themen</a>';
            $html .= '<a href="#" class="dgptm-forum-admin-tab" data-tab="admins">Forum-Admins</a>';
            $html .= '<a href="#" class="dgptm-forum-admin-tab" data-tab="moderation">Moderation</a>';
            $html .= '</nav>';
            $html .= '<div class="dgptm-forum-admin-content"></div>';
            $html .= '</div>';

            return $html;
        }

        public function shortcode_is_forum_admin($atts = []) {
            return DGPTM_Forum_Permissions::is_forum_admin() ? '1' : '0';
        }

        public function handle_ajax() {
            if (class_exists('DGPTM_Forum_Ajax')) {
                DGPTM_Forum_Ajax::get_instance()->dispatch();
            } else {
                wp_send_json_error('AJAX-Handler nicht verfügbar.');
            }
        }

        public function enqueue_assets() {
            wp_enqueue_style(
                'dgptm-forum',
                DGPTM_FORUM_URL . 'assets/css/forum.css',
                [],
                DGPTM_FORUM_VERSION
            );

            wp_enqueue_script(
                'dgptm-forum',
                DGPTM_FORUM_URL . 'assets/js/forum.js',
                ['jquery'],
                DGPTM_FORUM_VERSION,
                true
            );

            wp_localize_script('dgptm-forum', 'dgptmForum', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('dgptm_forum'),
                'isAdmin' => DGPTM_Forum_Permissions::is_forum_admin() ? 1 : 0,
            ]);
        }

        public function maybe_enqueue_assets() {
            global $post;
            if (is_a($post, 'WP_Post') && has_shortcode($post->post_content, 'dgptm_dashboard')) {
                $this->enqueue_assets();
            }
        }
    }
}

if (!isset($GLOBALS['dgptm_forum_initialized'])) {
    $GLOBALS['dgptm_forum_initialized'] = true;
    DGPTM_Forum::get_instance();
}
