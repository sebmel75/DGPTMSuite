<?php
/**
 * Plugin Name: Zeitschrift Kardiotechnik Manager
 * Description: Verwaltung und Anzeige der Fachzeitschrift Kardiotechnik
 * Version: 1.0.0
 * Author: Sebastian Melzer / DGPTM
 */

if (!defined('ABSPATH')) {
    exit;
}

// Konstanten
define('ZK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZK_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZK_VERSION', '1.2.0');
define('ZK_POST_TYPE', 'zeitschkardiotechnik');
define('ZK_PUBLIKATION_TYPE', 'publikation');

if (!class_exists('DGPTM_Zeitschrift_Kardiotechnik')) {

    class DGPTM_Zeitschrift_Kardiotechnik {

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
            require_once ZK_PLUGIN_DIR . 'includes/class-shortcodes.php';
            require_once ZK_PLUGIN_DIR . 'includes/class-admin.php';
        }

        private function init_hooks() {
            // Frontend Assets
            add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

            // Admin Assets
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

            // Initialize Shortcodes
            ZK_Shortcodes::get_instance();

            // Initialize Admin
            ZK_Admin::get_instance();

            // AJAX Handlers
            add_action('wp_ajax_zk_update_publish_date', [$this, 'ajax_update_publish_date']);
            add_action('wp_ajax_zk_publish_now', [$this, 'ajax_publish_now']);
            add_action('wp_ajax_zk_get_accepted_articles', [$this, 'ajax_get_accepted_articles']);
        }

        public function enqueue_frontend_assets() {
            wp_register_style(
                'zk-frontend',
                ZK_PLUGIN_URL . 'assets/css/frontend.css',
                [],
                ZK_VERSION
            );

            wp_register_script(
                'zk-frontend',
                ZK_PLUGIN_URL . 'assets/js/frontend.js',
                ['jquery'],
                ZK_VERSION,
                true
            );
        }

        public function enqueue_admin_assets($hook) {
            wp_register_style(
                'zk-admin',
                ZK_PLUGIN_URL . 'assets/css/admin.css',
                [],
                ZK_VERSION
            );

            wp_register_script(
                'zk-admin',
                ZK_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery'],
                ZK_VERSION,
                true
            );

            wp_localize_script('zk-admin', 'zkAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'adminUrl' => admin_url(),
                'nonce' => wp_create_nonce('zk_admin_nonce')
            ]);
        }

        /**
         * Prüft, ob eine Ausgabe sichtbar ist (verfuegbar_ab <= heute)
         */
        public static function is_issue_visible($post_id) {
            $available = get_field('verfuegbar_ab', $post_id);
            if (!$available) {
                return true; // Kein Datum = immer sichtbar
            }

            $date = DateTime::createFromFormat('d/m/Y', $available);
            if (!$date) {
                return true;
            }

            $today = new DateTime();
            $today->setTime(0, 0, 0);

            return $date <= $today;
        }

        /**
         * Sammelt alle Artikel einer Ausgabe
         */
        public static function get_issue_articles($post_id) {
            $articles = [];

            // Spezielle Felder
            $special_fields = ['editorial', 'journalclub', 'tutorial'];
            foreach ($special_fields as $field) {
                $pub = get_field($field, $post_id);
                if ($pub && is_object($pub)) {
                    $articles[$field] = [
                        'type' => $field,
                        'publication' => $pub,
                        'supplement' => get_field('supplement', $post_id)
                    ];
                }
            }

            // Nummerierte Publikationen (pub1-pub6)
            for ($i = 1; $i <= 6; $i++) {
                $pub = get_field('pub' . $i, $post_id);
                if ($pub && is_object($pub)) {
                    $supplement_field = ($i === 1) ? 'supplement' : 'supplement_' . $i;
                    $articles['pub' . $i] = [
                        'type' => 'artikel',
                        'publication' => $pub,
                        'supplement' => get_field($supplement_field, $post_id)
                    ];
                }
            }

            return $articles;
        }

        /**
         * Holt alle sichtbaren Zeitschrift-Ausgaben
         */
        public static function get_visible_issues($args = []) {
            $defaults = [
                'post_type' => ZK_POST_TYPE,
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_key' => 'jahr',
                'orderby' => 'meta_value_num',
                'order' => 'DESC'
            ];

            $query_args = wp_parse_args($args, $defaults);
            $posts = get_posts($query_args);

            // Filter nach Sichtbarkeit
            $visible = [];
            foreach ($posts as $post) {
                if (self::is_issue_visible($post->ID)) {
                    $visible[] = $post;
                }
            }

            // Sortieren nach Jahr und Ausgabe
            usort($visible, function($a, $b) {
                $year_a = (int) get_field('jahr', $a->ID);
                $year_b = (int) get_field('jahr', $b->ID);

                if ($year_a !== $year_b) {
                    return $year_b - $year_a;
                }

                $ausgabe_a = (int) get_field('ausgabe', $a->ID);
                $ausgabe_b = (int) get_field('ausgabe', $b->ID);

                return $ausgabe_b - $ausgabe_a;
            });

            return $visible;
        }

        /**
         * AJAX: Veröffentlichungsdatum ändern
         */
        public function ajax_update_publish_date() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $post_id = intval($_POST['post_id'] ?? 0);
            $date = sanitize_text_field($_POST['date'] ?? '');

            if (!$post_id || !$date) {
                wp_send_json_error(['message' => 'Ungültige Parameter']);
            }

            update_field('verfuegbar_ab', $date, $post_id);

            wp_send_json_success([
                'message' => 'Datum aktualisiert',
                'date' => $date,
                'is_visible' => self::is_issue_visible($post_id)
            ]);
        }

        /**
         * AJAX: Jetzt veröffentlichen
         */
        public function ajax_publish_now() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $post_id = intval($_POST['post_id'] ?? 0);

            if (!$post_id) {
                wp_send_json_error(['message' => 'Ungültige Post-ID']);
            }

            $today = date('d/m/Y');
            update_field('verfuegbar_ab', $today, $post_id);

            wp_send_json_success([
                'message' => 'Ausgabe veröffentlicht',
                'date' => $today
            ]);
        }

        /**
         * AJAX: Akzeptierte Artikel aus artikel-einreichung holen
         */
        public function ajax_get_accepted_articles() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!current_user_can('edit_posts')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            // Prüfen ob artikel-einreichung aktiv ist
            if (!post_type_exists('artikel_einreichung')) {
                wp_send_json_error(['message' => 'Modul artikel-einreichung nicht aktiv']);
            }

            $articles = get_posts([
                'post_type' => 'artikel_einreichung',
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_query' => [
                    [
                        'key' => 'artikel_status',
                        'value' => 'angenommen',
                        'compare' => '='
                    ]
                ],
                'meta_key' => 'submitted_at',
                'orderby' => 'meta_value',
                'order' => 'DESC'
            ]);

            $result = [];
            foreach ($articles as $article) {
                $result[] = [
                    'id' => $article->ID,
                    'title' => $article->post_title,
                    'author' => get_field('hauptautorin', $article->ID),
                    'submission_id' => get_field('submission_id', $article->ID),
                    'publikationsart' => get_field('publikationsart', $article->ID),
                    'submitted_at' => get_field('submitted_at', $article->ID)
                ];
            }

            wp_send_json_success(['articles' => $result]);
        }

        /**
         * Formatiert Ausgabe-Info (z.B. "2024/3")
         */
        public static function format_issue_label($post_id) {
            $jahr = get_field('jahr', $post_id);
            $ausgabe = get_field('ausgabe', $post_id);

            if ($jahr && $ausgabe) {
                return $jahr . '/' . $ausgabe;
            } elseif ($jahr) {
                return $jahr;
            }

            return '';
        }

        /**
         * Holt Publikations-Details für Anzeige
         */
        public static function get_publication_display_data($publication) {
            if (!$publication || !is_object($publication)) {
                return null;
            }

            return [
                'id' => $publication->ID,
                'title' => $publication->post_title,
                'authors' => get_field('autoren', $publication->ID),
                'main_author' => get_field('hauptautorin', $publication->ID),
                'doi' => get_field('doi', $publication->ID),
                'abstract_de' => get_field('abstract-deutsch', $publication->ID),
                'abstract_en' => get_field('abstract', $publication->ID),
                'keywords_de' => get_field('keywords-deutsch', $publication->ID),
                'pdf_volltext' => get_field('pdf-volltext', $publication->ID),
                'pdf_abstract' => get_field('pdf-abstract', $publication->ID)
            ];
        }
    }
}

// Prevent double initialization
if (!isset($GLOBALS['zk_initialized'])) {
    $GLOBALS['zk_initialized'] = true;
    DGPTM_Zeitschrift_Kardiotechnik::get_instance();
}
