<?php
/**
 * Plugin Name: Zeitschrift Kardiotechnik Manager
 * Description: Verwaltung und Anzeige der Fachzeitschrift Kardiotechnik
 * Version: 1.9.0
 * Author: Sebastian Melzer / DGPTM
 */

if (!defined('ABSPATH')) {
    exit;
}

// Konstanten
define('ZK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('ZK_PLUGIN_URL', plugin_dir_url(__FILE__));
define('ZK_VERSION', '1.9.0');
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
            require_once ZK_PLUGIN_DIR . 'includes/class-post-types.php';
            require_once ZK_PLUGIN_DIR . 'includes/class-shortcodes.php';
            require_once ZK_PLUGIN_DIR . 'includes/class-admin.php';
            require_once ZK_PLUGIN_DIR . 'includes/class-pdf-import.php';
            require_once ZK_PLUGIN_DIR . 'includes/class-editor.php';
        }

        private function init_hooks() {
            // CPTs und Taxonomien registrieren (vor JetEngine falls aktiv)
            ZK_Post_Types::get_instance();

            // Frontend Assets
            add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

            // Admin Assets
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

            // Initialize Shortcodes
            ZK_Shortcodes::get_instance();

            // Initialize Admin
            ZK_Admin::get_instance();

            // Initialize PDF Import
            ZK_PDF_Import::get_instance();

            // Initialize Editor
            ZK_Editor::get_instance();

            // AJAX Handlers
            add_action('wp_ajax_zk_update_publish_date', [$this, 'ajax_update_publish_date']);
            add_action('wp_ajax_zk_publish_now', [$this, 'ajax_publish_now']);
            add_action('wp_ajax_zk_get_accepted_articles', [$this, 'ajax_get_accepted_articles']);

            // Frontend Manager AJAX Handlers - Ausgaben
            add_action('wp_ajax_zk_get_all_issues', [$this, 'ajax_get_all_issues']);
            add_action('wp_ajax_zk_create_issue', [$this, 'ajax_create_issue']);
            add_action('wp_ajax_zk_update_issue', [$this, 'ajax_update_issue']);
            add_action('wp_ajax_zk_get_issue_details', [$this, 'ajax_get_issue_details']);
            add_action('wp_ajax_zk_delete_issue', [$this, 'ajax_delete_issue']);
            add_action('wp_ajax_zk_get_available_years', [$this, 'ajax_get_available_years']);

            // Frontend Manager AJAX Handlers - Artikel
            add_action('wp_ajax_zk_get_all_articles', [$this, 'ajax_get_all_articles']);
            add_action('wp_ajax_zk_create_article', [$this, 'ajax_create_article']);
            add_action('wp_ajax_zk_update_article', [$this, 'ajax_update_article']);
            add_action('wp_ajax_zk_delete_article', [$this, 'ajax_delete_article']);
            add_action('wp_ajax_zk_get_article_details', [$this, 'ajax_get_article_details']);
            add_action('wp_ajax_zk_link_article', [$this, 'ajax_link_article']);
            add_action('wp_ajax_zk_unlink_article', [$this, 'ajax_unlink_article']);
            add_action('wp_ajax_zk_get_available_articles', [$this, 'ajax_get_available_articles']);
        }

        public function enqueue_frontend_assets() {
            // Frontend Assets
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

            // Admin Assets auch im Frontend registrieren (für Shortcode)
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
        }

        public function enqueue_admin_assets($hook) {
            // Admin Assets im Backend registrieren
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
         * Prüft ob User Verwaltungszugriff hat (für AJAX)
         */
        private function user_can_manage() {
            if (!is_user_logged_in()) {
                return false;
            }

            if (current_user_can('manage_options')) {
                return true;
            }

            $user_id = get_current_user_id();

            $is_manager = get_user_meta($user_id, 'zeitschriftmanager', true);
            if ($is_manager === '1' || $is_manager === true || $is_manager === 1) {
                return true;
            }

            $is_editor = get_user_meta($user_id, 'editor_in_chief', true);
            if ($is_editor === '1' || $is_editor === true || $is_editor === 1) {
                return true;
            }

            return false;
        }

        /**
         * AJAX: Alle Ausgaben laden
         */
        public function ajax_get_all_issues() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!$this->user_can_manage()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $status_filter = sanitize_text_field($_POST['status'] ?? '');
            $year_filter = sanitize_text_field($_POST['year'] ?? '');

            $args = [
                'post_type' => ZK_POST_TYPE,
                'posts_per_page' => -1,
                'post_status' => 'publish'
            ];

            // Jahr-Filter
            if (!empty($year_filter)) {
                $args['meta_query'][] = [
                    'key' => 'jahr',
                    'value' => $year_filter,
                    'compare' => '='
                ];
            }

            $issues = get_posts($args);

            // Sortieren nach Jahr und Ausgabe
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

            $result = [];
            foreach ($issues as $issue) {
                $is_visible = self::is_issue_visible($issue->ID);
                $verfuegbar_ab = get_field('verfuegbar_ab', $issue->ID);

                // Status-Filter
                if ($status_filter === 'online' && !$is_visible) {
                    continue;
                }
                if ($status_filter === 'scheduled' && $is_visible) {
                    continue;
                }

                $titelseite = get_field('titelseite', $issue->ID);
                $articles = self::get_issue_articles($issue->ID);

                $result[] = [
                    'id' => $issue->ID,
                    'title' => $issue->post_title,
                    'jahr' => get_field('jahr', $issue->ID),
                    'ausgabe' => get_field('ausgabe', $issue->ID),
                    'label' => self::format_issue_label($issue->ID),
                    'doi' => get_field('doi', $issue->ID),
                    'verfuegbar_ab' => $verfuegbar_ab,
                    'verfuegbar_ab_formatted' => $verfuegbar_ab ? $verfuegbar_ab : 'Sofort',
                    'is_visible' => $is_visible,
                    'status' => $is_visible ? 'online' : 'scheduled',
                    'status_label' => $is_visible ? 'Online' : 'Geplant',
                    'thumbnail' => $titelseite ? ($titelseite['sizes']['thumbnail'] ?? $titelseite['url']) : null,
                    'article_count' => count($articles),
                    'edit_url' => admin_url('post.php?post=' . $issue->ID . '&action=edit')
                ];
            }

            wp_send_json_success(['issues' => $result]);
        }

        /**
         * AJAX: Neue Ausgabe erstellen
         */
        public function ajax_create_issue() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!$this->user_can_manage()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $jahr = intval($_POST['jahr'] ?? 0);
            $ausgabe = intval($_POST['ausgabe'] ?? 0);
            $title = sanitize_text_field($_POST['title'] ?? '');
            $doi = sanitize_text_field($_POST['doi'] ?? '');
            $verfuegbar_ab = sanitize_text_field($_POST['verfuegbar_ab'] ?? '');

            if (!$jahr || !$ausgabe) {
                wp_send_json_error(['message' => 'Jahr und Ausgabe sind erforderlich']);
            }

            // Prüfen ob Ausgabe bereits existiert
            $existing = get_posts([
                'post_type' => ZK_POST_TYPE,
                'posts_per_page' => 1,
                'post_status' => 'any',
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key' => 'jahr',
                        'value' => $jahr,
                        'compare' => '='
                    ],
                    [
                        'key' => 'ausgabe',
                        'value' => $ausgabe,
                        'compare' => '='
                    ]
                ]
            ]);

            if (!empty($existing)) {
                wp_send_json_error(['message' => 'Diese Ausgabe existiert bereits']);
            }

            // Titel generieren wenn nicht angegeben
            if (empty($title)) {
                $title = 'Kardiotechnik ' . $jahr . '/' . $ausgabe;
            }

            // Post erstellen
            $post_id = wp_insert_post([
                'post_type' => ZK_POST_TYPE,
                'post_title' => $title,
                'post_status' => 'publish',
                'post_author' => get_current_user_id()
            ]);

            if (is_wp_error($post_id)) {
                wp_send_json_error(['message' => 'Fehler beim Erstellen: ' . $post_id->get_error_message()]);
            }

            // ACF Felder setzen
            update_field('jahr', $jahr, $post_id);
            update_field('ausgabe', $ausgabe, $post_id);

            if (!empty($doi)) {
                update_field('doi', $doi, $post_id);
            }

            if (!empty($verfuegbar_ab)) {
                // Konvertieren von YYYY-MM-DD zu DD/MM/YYYY
                $date = DateTime::createFromFormat('Y-m-d', $verfuegbar_ab);
                if ($date) {
                    update_field('verfuegbar_ab', $date->format('d/m/Y'), $post_id);
                }
            }

            wp_send_json_success([
                'message' => 'Ausgabe erstellt',
                'post_id' => $post_id,
                'title' => $title
            ]);
        }

        /**
         * AJAX: Ausgabe aktualisieren
         */
        public function ajax_update_issue() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!$this->user_can_manage()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $post_id = intval($_POST['post_id'] ?? 0);
            if (!$post_id) {
                wp_send_json_error(['message' => 'Ungültige Post-ID']);
            }

            $post = get_post($post_id);
            if (!$post || $post->post_type !== ZK_POST_TYPE) {
                wp_send_json_error(['message' => 'Ausgabe nicht gefunden']);
            }

            // Felder aktualisieren
            $jahr = intval($_POST['jahr'] ?? 0);
            $ausgabe = intval($_POST['ausgabe'] ?? 0);
            $title = sanitize_text_field($_POST['title'] ?? '');
            $doi = sanitize_text_field($_POST['doi'] ?? '');
            $verfuegbar_ab = sanitize_text_field($_POST['verfuegbar_ab'] ?? '');

            if ($jahr) {
                update_field('jahr', $jahr, $post_id);
            }
            if ($ausgabe) {
                update_field('ausgabe', $ausgabe, $post_id);
            }
            if (!empty($title)) {
                wp_update_post([
                    'ID' => $post_id,
                    'post_title' => $title
                ]);
            }
            update_field('doi', $doi, $post_id);

            // Datum konvertieren
            if (!empty($verfuegbar_ab)) {
                $date = DateTime::createFromFormat('Y-m-d', $verfuegbar_ab);
                if ($date) {
                    update_field('verfuegbar_ab', $date->format('d/m/Y'), $post_id);
                }
            } else {
                update_field('verfuegbar_ab', '', $post_id);
            }

            wp_send_json_success([
                'message' => 'Ausgabe aktualisiert',
                'post_id' => $post_id
            ]);
        }

        /**
         * AJAX: Ausgabe-Details für Bearbeitung
         */
        public function ajax_get_issue_details() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!$this->user_can_manage()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $post_id = intval($_POST['post_id'] ?? 0);
            if (!$post_id) {
                wp_send_json_error(['message' => 'Ungültige Post-ID']);
            }

            $post = get_post($post_id);
            if (!$post || $post->post_type !== ZK_POST_TYPE) {
                wp_send_json_error(['message' => 'Ausgabe nicht gefunden']);
            }

            $verfuegbar_ab = get_field('verfuegbar_ab', $post_id);
            $verfuegbar_ab_input = '';
            if ($verfuegbar_ab) {
                $date = DateTime::createFromFormat('d/m/Y', $verfuegbar_ab);
                if ($date) {
                    $verfuegbar_ab_input = $date->format('Y-m-d');
                }
            }

            $articles = self::get_issue_articles($post_id);
            $linked_articles = [];
            foreach ($articles as $key => $article) {
                $pub = $article['publication'];
                $linked_articles[] = [
                    'slot' => $key,
                    'field' => $key,
                    'id' => $pub->ID,
                    'title' => $pub->post_title,
                    'type' => $article['type'],
                    'authors' => get_field('autoren', $pub->ID) ?: get_field('hauptautorin', $pub->ID)
                ];
            }

            wp_send_json_success([
                'issue' => [
                    'id' => $post_id,
                    'title' => $post->post_title,
                    'jahr' => get_field('jahr', $post_id),
                    'ausgabe' => get_field('ausgabe', $post_id),
                    'doi' => get_field('doi', $post_id),
                    'verfuegbar_ab' => $verfuegbar_ab_input,
                    'verfuegbar_ab_display' => $verfuegbar_ab ?: 'Sofort'
                ],
                'articles' => $linked_articles
            ]);
        }

        /**
         * AJAX: Ausgabe löschen
         */
        public function ajax_delete_issue() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!$this->user_can_manage()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $post_id = intval($_POST['post_id'] ?? 0);
            if (!$post_id) {
                wp_send_json_error(['message' => 'Ungültige Post-ID']);
            }

            $post = get_post($post_id);
            if (!$post || $post->post_type !== ZK_POST_TYPE) {
                wp_send_json_error(['message' => 'Ausgabe nicht gefunden']);
            }

            $result = wp_trash_post($post_id);
            if (!$result) {
                wp_send_json_error(['message' => 'Fehler beim Löschen']);
            }

            wp_send_json_success(['message' => 'Ausgabe in Papierkorb verschoben']);
        }

        /**
         * AJAX: Verfügbare Jahre laden
         */
        public function ajax_get_available_years() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!$this->user_can_manage()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            global $wpdb;

            $years = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT pm.meta_value
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = %s
                 AND p.post_type = %s
                 AND p.post_status = 'publish'
                 ORDER BY pm.meta_value DESC",
                'jahr',
                ZK_POST_TYPE
            ));

            wp_send_json_success(['years' => $years]);
        }

        /**
         * AJAX: Alle Artikel laden
         */
        public function ajax_get_all_articles() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!$this->user_can_manage()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $search = sanitize_text_field($_POST['search'] ?? '');

            $sort_by = sanitize_text_field($_POST['sort_by'] ?? 'date');
            $sort_order = sanitize_text_field($_POST['sort_order'] ?? 'DESC');

            $args = [
                'post_type' => ZK_PUBLIKATION_TYPE,
                'posts_per_page' => 200,
                'post_status' => ['publish', 'draft', 'pending'],
                'orderby' => $sort_by === 'modified' ? 'modified' : 'date',
                'order' => strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC'
            ];

            if (!empty($search)) {
                $args['s'] = $search;
            }

            $articles = get_posts($args);

            $result = [];
            foreach ($articles as $article) {
                $result[] = [
                    'id' => $article->ID,
                    'title' => $article->post_title,
                    'authors' => get_field('autoren', $article->ID) ?: get_field('hauptautorin', $article->ID),
                    'doi' => get_field('doi', $article->ID),
                    'date' => get_the_date('d.m.Y', $article->ID),
                    'date_raw' => get_the_date('Y-m-d H:i:s', $article->ID),
                    'modified' => get_the_modified_date('d.m.Y', $article->ID),
                    'modified_raw' => get_the_modified_date('Y-m-d H:i:s', $article->ID),
                    'status' => $article->post_status,
                    'status_label' => get_post_status_object($article->post_status)->label ?? $article->post_status,
                    'linked_issue' => $this->get_article_linked_issue($article->ID)
                ];
            }

            wp_send_json_success(['articles' => $result]);
        }

        /**
         * Hilfsfunktion: Findet die Ausgabe, mit der ein Artikel verknüpft ist
         */
        private function get_article_linked_issue($article_id) {
            global $wpdb;

            // Suche in allen Ausgaben nach diesem Artikel
            $fields = ['editorial', 'journalclub', 'tutorial', 'pub1', 'pub2', 'pub3', 'pub4', 'pub5', 'pub6'];

            foreach ($fields as $field) {
                $issue_id = $wpdb->get_var($wpdb->prepare(
                    "SELECT post_id FROM {$wpdb->postmeta}
                     WHERE meta_key = %s AND meta_value = %d",
                    $field,
                    $article_id
                ));

                if ($issue_id) {
                    $issue = get_post($issue_id);
                    if ($issue && $issue->post_type === ZK_POST_TYPE) {
                        return [
                            'id' => $issue_id,
                            'label' => self::format_issue_label($issue_id),
                            'field' => $field
                        ];
                    }
                }
            }

            return null;
        }

        /**
         * AJAX: Neuen Artikel erstellen
         */
        public function ajax_create_article() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!$this->user_can_manage()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            // Grunddaten
            $title = sanitize_text_field($_POST['title'] ?? '');
            $unterueberschrift = sanitize_text_field($_POST['unterueberschrift'] ?? '');
            $publikationsart = sanitize_text_field($_POST['publikationsart'] ?? '');
            $doi = sanitize_text_field($_POST['doi'] ?? '');
            $kardiotechnikausgabe = sanitize_text_field($_POST['kardiotechnikausgabe'] ?? '');
            $supplement = sanitize_text_field($_POST['supplement'] ?? '');

            // Autoren
            $autoren = sanitize_text_field($_POST['autoren'] ?? '');
            $hauptautorin = sanitize_text_field($_POST['hauptautorin'] ?? '');

            // Inhalt
            $content = wp_kses_post($_POST['content'] ?? '');
            $literatur = wp_kses_post($_POST['literatur'] ?? '');

            // Abstracts & Keywords
            $abstract_deutsch = wp_kses_post($_POST['abstract_deutsch'] ?? '');
            $abstract = wp_kses_post($_POST['abstract'] ?? '');
            $keywords_deutsch = sanitize_text_field($_POST['keywords_deutsch'] ?? '');
            $keywords_englisch = sanitize_text_field($_POST['keywords_englisch'] ?? '');

            // Optionen
            $volltext_anzeigen = sanitize_text_field($_POST['volltext_anzeigen'] ?? '');

            if (empty($title)) {
                wp_send_json_error(['message' => 'Titel ist erforderlich']);
            }

            // Post erstellen
            $post_id = wp_insert_post([
                'post_type' => ZK_PUBLIKATION_TYPE,
                'post_title' => $title,
                'post_content' => $content,
                'post_status' => 'publish',
                'post_author' => get_current_user_id()
            ]);

            if (is_wp_error($post_id)) {
                wp_send_json_error(['message' => 'Fehler beim Erstellen: ' . $post_id->get_error_message()]);
            }

            // ACF Felder setzen - Grunddaten
            update_field('unterueberschrift', $unterueberschrift, $post_id);
            update_field('publikationsart', $publikationsart, $post_id);
            // Auch als post_meta für Fallback speichern
            update_post_meta($post_id, 'publikationsart', $publikationsart);
            update_field('doi', $doi, $post_id);
            update_field('kardiotechnikausgabe', $kardiotechnikausgabe, $post_id);
            update_field('supplement', $supplement, $post_id);

            // Autoren
            update_field('autoren', $autoren, $post_id);
            update_field('hauptautorin', $hauptautorin, $post_id);

            // Inhalt
            update_field('literatur', $literatur, $post_id);

            // Abstracts & Keywords
            update_field('abstract-deutsch', $abstract_deutsch, $post_id);
            update_field('abstract', $abstract, $post_id);
            update_field('keywords-deutsch', $keywords_deutsch, $post_id);
            update_field('keywords-englisch', $keywords_englisch, $post_id);

            // Optionen
            update_field('volltext_anzeigen', $volltext_anzeigen, $post_id);

            wp_send_json_success([
                'message' => 'Artikel erstellt',
                'post_id' => $post_id,
                'title' => $title
            ]);
        }

        /**
         * AJAX: Artikel aktualisieren
         */
        public function ajax_update_article() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!$this->user_can_manage()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            // Akzeptiert sowohl article_id als auch post_id
            $post_id = intval($_POST['article_id'] ?? $_POST['post_id'] ?? 0);
            if (!$post_id) {
                wp_send_json_error(['message' => 'Ungültige Post-ID']);
            }

            $post = get_post($post_id);
            if (!$post || $post->post_type !== ZK_PUBLIKATION_TYPE) {
                wp_send_json_error(['message' => 'Artikel nicht gefunden']);
            }

            // Grunddaten
            $title = sanitize_text_field($_POST['title'] ?? '');
            $unterueberschrift = sanitize_text_field($_POST['unterueberschrift'] ?? '');
            $publikationsart = sanitize_text_field($_POST['publikationsart'] ?? '');
            $doi = sanitize_text_field($_POST['doi'] ?? '');
            $kardiotechnikausgabe = sanitize_text_field($_POST['kardiotechnikausgabe'] ?? '');
            $supplement = sanitize_text_field($_POST['supplement'] ?? '');

            // Autoren
            $autoren = sanitize_text_field($_POST['autoren'] ?? '');
            $hauptautorin = sanitize_text_field($_POST['hauptautorin'] ?? '');

            // Inhalt
            $content = wp_kses_post($_POST['content'] ?? '');
            $literatur = wp_kses_post($_POST['literatur'] ?? '');

            // Abstracts & Keywords
            $abstract_deutsch = wp_kses_post($_POST['abstract_deutsch'] ?? '');
            $abstract = wp_kses_post($_POST['abstract'] ?? '');
            $keywords_deutsch = sanitize_text_field($_POST['keywords_deutsch'] ?? '');
            $keywords_englisch = sanitize_text_field($_POST['keywords_englisch'] ?? '');

            // Optionen
            $volltext_anzeigen = sanitize_text_field($_POST['volltext_anzeigen'] ?? '');

            // Post-Daten aktualisieren
            $update_data = ['ID' => $post_id];
            if (!empty($title)) {
                $update_data['post_title'] = $title;
            }
            $update_data['post_content'] = $content;
            wp_update_post($update_data);

            // ACF Felder setzen - Grunddaten
            update_field('unterueberschrift', $unterueberschrift, $post_id);
            update_field('publikationsart', $publikationsart, $post_id);
            // Auch als post_meta für Fallback speichern
            update_post_meta($post_id, 'publikationsart', $publikationsart);
            update_field('doi', $doi, $post_id);
            update_field('kardiotechnikausgabe', $kardiotechnikausgabe, $post_id);
            update_field('supplement', $supplement, $post_id);

            // Autoren
            update_field('autoren', $autoren, $post_id);
            update_field('hauptautorin', $hauptautorin, $post_id);

            // Inhalt
            update_field('literatur', $literatur, $post_id);

            // Abstracts & Keywords
            update_field('abstract-deutsch', $abstract_deutsch, $post_id);
            update_field('abstract', $abstract, $post_id);
            update_field('keywords-deutsch', $keywords_deutsch, $post_id);
            update_field('keywords-englisch', $keywords_englisch, $post_id);

            // Optionen
            update_field('volltext_anzeigen', $volltext_anzeigen, $post_id);

            wp_send_json_success([
                'message' => 'Artikel aktualisiert',
                'post_id' => $post_id
            ]);
        }

        /**
         * AJAX: Artikel-Details laden
         */
        public function ajax_get_article_details() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!$this->user_can_manage()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            // Akzeptiert sowohl article_id als auch post_id
            $post_id = intval($_POST['article_id'] ?? $_POST['post_id'] ?? 0);
            if (!$post_id) {
                wp_send_json_error(['message' => 'Ungültige Post-ID']);
            }

            $post = get_post($post_id);
            if (!$post || $post->post_type !== ZK_PUBLIKATION_TYPE) {
                wp_send_json_error(['message' => 'Artikel nicht gefunden']);
            }

            // Publikationsart abrufen
            $publikationsart = get_field('publikationsart', $post_id);
            if (empty($publikationsart)) {
                $publikationsart = get_post_meta($post_id, 'publikationsart', true);
            }

            // Alle ACF-Felder abrufen
            wp_send_json_success([
                'article' => [
                    // Grunddaten
                    'id' => $post_id,
                    'title' => $post->post_title,
                    'unterueberschrift' => get_field('unterueberschrift', $post_id),
                    'publikationsart' => $publikationsart,
                    'doi' => get_field('doi', $post_id),
                    'kardiotechnikausgabe' => get_field('kardiotechnikausgabe', $post_id),
                    'supplement' => get_field('supplement', $post_id),

                    // Autoren
                    'autoren' => get_field('autoren', $post_id),
                    'hauptautorin' => get_field('hauptautorin', $post_id),

                    // Inhalt
                    'content' => $post->post_content,
                    'literatur' => get_field('literatur', $post_id),

                    // Abstracts & Keywords
                    'abstract_deutsch' => get_field('abstract-deutsch', $post_id),
                    'abstract' => get_field('abstract', $post_id),
                    'keywords_deutsch' => get_field('keywords-deutsch', $post_id),
                    'keywords_englisch' => get_field('keywords-englisch', $post_id),

                    // Optionen
                    'volltext_anzeigen' => get_field('volltext_anzeigen', $post_id),

                    // Verknüpfung
                    'linked_issue' => $this->get_article_linked_issue($post_id)
                ]
            ]);
        }

        /**
         * AJAX: Artikel löschen
         */
        public function ajax_delete_article() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!$this->user_can_manage()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            // Akzeptiert sowohl article_id als auch post_id
            $post_id = intval($_POST['article_id'] ?? $_POST['post_id'] ?? 0);
            if (!$post_id) {
                wp_send_json_error(['message' => 'Ungültige Post-ID']);
            }

            $post = get_post($post_id);
            if (!$post || $post->post_type !== ZK_PUBLIKATION_TYPE) {
                wp_send_json_error(['message' => 'Artikel nicht gefunden']);
            }

            $result = wp_trash_post($post_id);
            if (!$result) {
                wp_send_json_error(['message' => 'Fehler beim Löschen']);
            }

            wp_send_json_success(['message' => 'Artikel in Papierkorb verschoben']);
        }

        /**
         * AJAX: Artikel mit Ausgabe verknüpfen
         */
        public function ajax_link_article() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!$this->user_can_manage()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $issue_id = intval($_POST['issue_id'] ?? 0);
            $article_id = intval($_POST['article_id'] ?? 0);
            $slot = sanitize_text_field($_POST['slot'] ?? '');

            if (!$issue_id || !$article_id || !$slot) {
                wp_send_json_error(['message' => 'Ungültige Parameter']);
            }

            // Prüfen ob Ausgabe existiert
            $issue = get_post($issue_id);
            if (!$issue || $issue->post_type !== ZK_POST_TYPE) {
                wp_send_json_error(['message' => 'Ausgabe nicht gefunden']);
            }

            // Prüfen ob Artikel existiert
            $article = get_post($article_id);
            if (!$article || $article->post_type !== ZK_PUBLIKATION_TYPE) {
                wp_send_json_error(['message' => 'Artikel nicht gefunden']);
            }

            // Gültige Slots
            $valid_slots = ['editorial', 'journalclub', 'tutorial', 'pub1', 'pub2', 'pub3', 'pub4', 'pub5', 'pub6'];
            if (!in_array($slot, $valid_slots)) {
                wp_send_json_error(['message' => 'Ungültiger Slot']);
            }

            // Artikel verknüpfen
            update_field($slot, $article_id, $issue_id);

            wp_send_json_success([
                'message' => 'Artikel verknüpft',
                'issue_id' => $issue_id,
                'article_id' => $article_id,
                'slot' => $slot
            ]);
        }

        /**
         * AJAX: Artikel von Ausgabe trennen
         */
        public function ajax_unlink_article() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!$this->user_can_manage()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $issue_id = intval($_POST['issue_id'] ?? 0);
            $slot = sanitize_text_field($_POST['slot'] ?? '');

            if (!$issue_id || !$slot) {
                wp_send_json_error(['message' => 'Ungültige Parameter']);
            }

            // Prüfen ob Ausgabe existiert
            $issue = get_post($issue_id);
            if (!$issue || $issue->post_type !== ZK_POST_TYPE) {
                wp_send_json_error(['message' => 'Ausgabe nicht gefunden']);
            }

            // Gültige Slots
            $valid_slots = ['editorial', 'journalclub', 'tutorial', 'pub1', 'pub2', 'pub3', 'pub4', 'pub5', 'pub6'];
            if (!in_array($slot, $valid_slots)) {
                wp_send_json_error(['message' => 'Ungültiger Slot']);
            }

            // Artikel trennen
            update_field($slot, '', $issue_id);

            wp_send_json_success([
                'message' => 'Artikel getrennt',
                'issue_id' => $issue_id,
                'slot' => $slot
            ]);
        }

        /**
         * AJAX: Verfügbare Artikel für Verknüpfung laden
         */
        public function ajax_get_available_articles() {
            check_ajax_referer('zk_admin_nonce', 'nonce');

            if (!$this->user_can_manage()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $search = sanitize_text_field($_POST['search'] ?? '');
            $exclude_issue = intval($_POST['exclude_issue'] ?? 0);

            $args = [
                'post_type' => ZK_PUBLIKATION_TYPE,
                'posts_per_page' => 50,
                'post_status' => 'publish',
                'orderby' => 'title',
                'order' => 'ASC'
            ];

            if (!empty($search)) {
                $args['s'] = $search;
            }

            $articles = get_posts($args);

            $result = [];
            foreach ($articles as $article) {
                $linked = $this->get_article_linked_issue($article->ID);

                // Bereits verknüpfte Artikel markieren
                $is_linked = !empty($linked);
                $linked_to_this = $is_linked && $linked['id'] == $exclude_issue;

                $result[] = [
                    'id' => $article->ID,
                    'title' => $article->post_title,
                    'authors' => get_field('autoren', $article->ID) ?: get_field('hauptautorin', $article->ID),
                    'is_linked' => $is_linked,
                    'linked_to_this' => $linked_to_this,
                    'linked_issue' => $linked
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
