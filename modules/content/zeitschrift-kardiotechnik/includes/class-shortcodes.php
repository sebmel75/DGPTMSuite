<?php
/**
 * Zeitschrift Kardiotechnik - Shortcodes
 *
 * @package DGPTM_Zeitschrift_Kardiotechnik
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('ZK_Shortcodes')) {

    class ZK_Shortcodes {

        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            add_shortcode('zeitschrift_uebersicht', [$this, 'render_overview']);
            add_shortcode('zeitschrift_detail', [$this, 'render_detail']);
            add_shortcode('zeitschrift_verwaltung', [$this, 'render_admin']);
        }

        /**
         * Prüft ob Benutzer Verwaltungszugriff hat
         * Admin, zeitschriftmanager oder editor_in_chief User-Meta
         */
        public static function user_can_manage() {
            if (!is_user_logged_in()) {
                return false;
            }

            // Admins haben immer Zugriff
            if (current_user_can('manage_options')) {
                return true;
            }

            // User-Meta prüfen
            $user_id = get_current_user_id();

            // zeitschriftmanager
            $is_manager = get_user_meta($user_id, 'zeitschriftmanager', true);
            if ($is_manager === '1' || $is_manager === true || $is_manager === 1) {
                return true;
            }

            // editor_in_chief
            $is_editor = get_user_meta($user_id, 'editor_in_chief', true);
            if ($is_editor === '1' || $is_editor === true || $is_editor === 1) {
                return true;
            }

            return false;
        }

        /**
         * Shortcode: [zeitschrift_uebersicht]
         * Zeigt Grid mit Thumbnails und Hover-Popup
         *
         * @param array $atts Attribute: anzahl, jahr, spalten
         */
        public function render_overview($atts) {
            $atts = shortcode_atts([
                'anzahl' => -1,
                'jahr' => '',
                'spalten' => 4
            ], $atts, 'zeitschrift_uebersicht');

            // Assets laden
            wp_enqueue_style('zk-frontend');
            wp_enqueue_script('zk-frontend');

            // Query-Args
            $args = [
                'posts_per_page' => intval($atts['anzahl'])
            ];

            // Jahr-Filter
            if (!empty($atts['jahr'])) {
                $args['meta_query'] = [
                    [
                        'key' => 'jahr',
                        'value' => $atts['jahr'],
                        'compare' => '='
                    ]
                ];
            }

            $issues = DGPTM_Zeitschrift_Kardiotechnik::get_visible_issues($args);

            ob_start();
            include ZK_PLUGIN_DIR . 'templates/grid-overview.php';
            return ob_get_clean();
        }

        /**
         * Shortcode: [zeitschrift_detail]
         * Zeigt Einzelansicht einer Ausgabe
         *
         * @param array $atts Attribute: id
         */
        public function render_detail($atts) {
            $atts = shortcode_atts([
                'id' => ''
            ], $atts, 'zeitschrift_detail');

            // ID-Ermittlung in folgender Reihenfolge:
            // 1. Shortcode-Attribut id=""
            // 2. URL-Parameter ?p= (WordPress-Standard)
            // 3. Aktueller Post (wenn auf Single-Seite des CPT)
            $post_id = intval($atts['id']);

            if (!$post_id && isset($_GET['p'])) {
                $post_id = intval($_GET['p']);
            }

            if (!$post_id) {
                global $post;
                if ($post && $post->post_type === ZK_POST_TYPE) {
                    $post_id = $post->ID;
                }
            }

            if (!$post_id) {
                return '<p class="zk-error">Keine Ausgabe angegeben.</p>';
            }

            // Post prüfen
            $issue = get_post($post_id);
            if (!$issue || $issue->post_type !== ZK_POST_TYPE) {
                return '<p class="zk-error">Ausgabe nicht gefunden.</p>';
            }

            // Sichtbarkeit prüfen (außer für Manager)
            if (!DGPTM_Zeitschrift_Kardiotechnik::is_issue_visible($post_id) && !self::user_can_manage()) {
                return '<p class="zk-error">Diese Ausgabe ist noch nicht verfügbar.</p>';
            }

            // Assets laden
            wp_enqueue_style('zk-frontend');
            wp_enqueue_script('zk-frontend');

            $articles = DGPTM_Zeitschrift_Kardiotechnik::get_issue_articles($post_id);

            ob_start();
            include ZK_PLUGIN_DIR . 'templates/single-issue.php';
            return ob_get_clean();
        }

        /**
         * Shortcode: [zeitschrift_verwaltung]
         * Admin-Tool zur Verwaltung der Zeitschriften
         */
        public function render_admin($atts) {
            // Berechtigungsprüfung
            if (!self::user_can_manage()) {
                return '<p class="zk-error">Sie haben keine Berechtigung für diesen Bereich.</p>';
            }

            // Assets laden
            wp_enqueue_style('zk-admin');
            wp_enqueue_script('zk-admin');

            // Alle Ausgaben holen (auch zukünftige)
            $issues = get_posts([
                'post_type' => ZK_POST_TYPE,
                'posts_per_page' => -1,
                'post_status' => 'publish',
                'meta_key' => 'jahr',
                'orderby' => 'meta_value_num',
                'order' => 'DESC'
            ]);

            // Nach Jahr und Ausgabe sortieren
            usort($issues, function($a, $b) {
                $year_a = (int) get_field('jahr', $a->ID);
                $year_b = (int) get_field('jahr', $b->ID);

                if ($year_a !== $year_b) {
                    return $year_b - $year_a;
                }

                $ausgabe_a = (int) get_field('ausgabe', $a->ID);
                $ausgabe_b = (int) get_field('ausgabe', $b->ID);

                return $ausgabe_b - $ausgabe_a;
            });

            ob_start();
            include ZK_PLUGIN_DIR . 'templates/admin-manager.php';
            return ob_get_clean();
        }

        /**
         * Hilfsfunktion: Holt Autoren-String einer Publikation
         */
        public static function get_authors_string($publication) {
            if (!$publication || !is_object($publication)) {
                return '';
            }

            $authors = get_field('autoren', $publication->ID);
            $main_author = get_field('hauptautorin', $publication->ID);

            if ($authors) {
                return $authors;
            } elseif ($main_author) {
                return $main_author;
            }

            return '';
        }

        /**
         * Hilfsfunktion: Formatiert Artikel-Typ für Anzeige
         */
        public static function get_article_type_label($type) {
            $labels = [
                'editorial' => 'Editorial',
                'journalclub' => 'Journal Club',
                'tutorial' => 'Tutorial',
                'artikel' => 'Fachartikel'
            ];

            return $labels[$type] ?? $type;
        }
    }
}
