<?php
/**
 * Plugin Name: DGPTM - Vimeo Webinare
 * Plugin URI: https://dgptm.de
 * Description: Vimeo Videos als Webinare mit dynamischen URLs (/webinar/{id}), automatischen Fortbildungspunkten, Zertifikaten und Frontend-Manager
 * Version: 1.2.6
 * Author: Sebastian Melzer
 * Author URI: https://dgptm.de
 * License: GPL v2 or later
 * Text Domain: dgptm-vimeo-webinare
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent class redeclaration
if (!class_exists('DGPTM_Vimeo_Webinare')) {

class DGPTM_Vimeo_Webinare {

    private static $instance = null;
    private $plugin_path;
    private $plugin_url;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        $this->plugin_path = plugin_dir_path(__FILE__);
        $this->plugin_url = plugin_dir_url(__FILE__);

        // Initialize
        add_action('init', [$this, 'register_post_types']);
        add_action('init', [$this, 'register_acf_fields']);
        add_action('init', [$this, 'add_rewrite_rules']);

        // Early check for webinar pages to load assets
        add_action('wp', [$this, 'maybe_load_webinar_assets']);

        // Template redirect for dynamic pages
        add_action('template_redirect', [$this, 'handle_webinar_page']);

        // Shortcodes
        add_shortcode('vimeo_webinar', [$this, 'webinar_player_shortcode']);
        add_shortcode('vimeo_webinar_manager', [$this, 'webinar_manager_shortcode']);
        add_shortcode('vimeo_webinar_liste', [$this, 'webinar_liste_shortcode']);

        // AJAX Handlers
        add_action('wp_ajax_vw_track_progress', [$this, 'ajax_track_progress']);
        add_action('wp_ajax_vw_complete_webinar', [$this, 'ajax_complete_webinar']);
        add_action('wp_ajax_vw_generate_certificate', [$this, 'ajax_generate_certificate']);
        add_action('wp_ajax_vw_manager_create', [$this, 'ajax_manager_create']);
        add_action('wp_ajax_vw_manager_update', [$this, 'ajax_manager_update']);
        add_action('wp_ajax_vw_manager_delete', [$this, 'ajax_manager_delete']);
        add_action('wp_ajax_vw_manager_stats', [$this, 'ajax_manager_stats']);

        // Enqueue Scripts & Styles
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

        // Admin
        add_action('admin_menu', [$this, 'add_admin_menu']);

        // Flush rewrite rules on activation (only once)
        register_activation_hook(__FILE__, 'flush_rewrite_rules');
    }

    /**
     * Register Custom Post Type: Webinar
     */
    public function register_post_types() {
        register_post_type('vimeo_webinar', [
            'labels' => [
                'name' => 'Webinare',
                'singular_name' => 'Webinar',
                'add_new' => 'Neues Webinar',
                'add_new_item' => 'Neues Webinar hinzufügen',
                'edit_item' => 'Webinar bearbeiten',
                'view_item' => 'Webinar ansehen',
                'all_items' => 'Alle Webinare',
                'search_items' => 'Webinare suchen',
            ],
            'public' => true,
            'publicly_queryable' => true,
            'show_ui' => true,
            'show_in_menu' => true,
            'show_in_rest' => true,
            'menu_icon' => 'dashicons-video-alt2',
            'menu_position' => 25,
            'supports' => ['title', 'editor', 'thumbnail', 'custom-fields'],
            'has_archive' => false,
            'rewrite' => false, // We handle URLs manually
        ]);
    }

    /**
     * Add custom rewrite rules
     */
    public function add_rewrite_rules() {
        // Pattern: /webinar/{id}
        add_rewrite_rule(
            '^webinar/([0-9]+)/?$',
            'index.php?vw_webinar_id=$matches[1]',
            'top'
        );

        // Pattern: /webinar?id={id}
        add_rewrite_rule(
            '^webinar/?$',
            'index.php?vw_webinar_page=1',
            'top'
        );

        // Register query vars
        add_filter('query_vars', function($vars) {
            $vars[] = 'vw_webinar_id';
            $vars[] = 'vw_webinar_page';
            return $vars;
        });
    }

    /**
     * Maybe load webinar assets early (before wp_head)
     */
    public function maybe_load_webinar_assets() {
        $webinar_id = get_query_var('vw_webinar_id');
        $is_webinar_page = get_query_var('vw_webinar_page');

        // Handle /webinar?id=123
        if ($is_webinar_page && isset($_GET['id'])) {
            $webinar_id = intval($_GET['id']);
        }

        // Load assets if this is a webinar page
        if ($webinar_id) {
            $this->force_enqueue_assets();
        }
    }

    /**
     * Handle webinar page template
     */
    public function handle_webinar_page() {
        global $wp_query;

        // Check if this is a webinar page request
        $webinar_id = get_query_var('vw_webinar_id');
        $is_webinar_page = get_query_var('vw_webinar_page');

        // Handle /webinar?id=123
        if ($is_webinar_page && isset($_GET['id'])) {
            $webinar_id = intval($_GET['id']);
        }

        if (!$webinar_id) {
            return;
        }

        // Verify webinar exists
        $post = get_post($webinar_id);
        if (!$post || get_post_type($webinar_id) !== 'vimeo_webinar') {
            wp_die('Webinar nicht gefunden', 'Fehler', ['response' => 404]);
        }

        // Get webinar data
        $vimeo_id = get_field('vimeo_id', $webinar_id);
        $completion_percentage = get_field('completion_percentage', $webinar_id) ?: 90;

        if (!$vimeo_id) {
            wp_die('Vimeo Video ID fehlt für dieses Webinar', 'Fehler');
        }

        // User data (kann 0 sein wenn nicht eingeloggt)
        $user_id = get_current_user_id();
        $progress = 0;
        $watched_time = 0;
        $is_completed = false;

        if ($user_id) {
            // Eingeloggter Benutzer - Daten aus DB laden
            $progress = $this->get_user_progress($user_id, $webinar_id);
            $watched_time = $this->get_watched_time($user_id, $webinar_id);
            $is_completed = $this->is_webinar_completed($user_id, $webinar_id);
        } else {
            // Nicht eingeloggt - Daten aus Cookie laden
            $cookie_data = $this->get_cookie_data($webinar_id);
            $watched_time = $cookie_data['watched_time'];
            $progress = $cookie_data['progress'];
        }

        // Set post_id for template
        $post_id = $webinar_id;

        // Assets are already loaded in maybe_load_webinar_assets()
        // No need to call force_enqueue_assets() again

        // Render template
        $this->render_webinar_template($post_id, $vimeo_id, $completion_percentage, $progress, $watched_time, $is_completed, $user_id);
        exit;
    }

    /**
     * Render webinar template with WordPress header/footer
     */
    private function render_webinar_template($post_id, $vimeo_id, $completion_percentage, $progress, $watched_time, $is_completed, $user_id) {
        // Get WordPress header
        get_header();

        // Output player template
        echo '<div class="vw-page-wrapper">';
        include $this->plugin_path . 'templates/player.php';
        echo '</div>';

        // Get WordPress footer
        get_footer();
    }

    /**
     * Register ACF Fields
     */
    public function register_acf_fields() {
        if (!function_exists('acf_add_local_field_group')) {
            return;
        }

        // Webinar Settings
        acf_add_local_field_group([
            'key' => 'group_vimeo_webinar',
            'title' => 'Webinar Einstellungen',
            'fields' => [
                [
                    'key' => 'field_vw_vimeo_id',
                    'label' => 'Vimeo Video ID',
                    'name' => 'vimeo_id',
                    'type' => 'text',
                    'required' => 1,
                    'instructions' => 'Geben Sie die Vimeo Video ID ein (z.B. 123456789)',
                ],
                [
                    'key' => 'field_vw_completion_percentage',
                    'label' => 'Erforderlicher Fortschritt (%)',
                    'name' => 'completion_percentage',
                    'type' => 'number',
                    'required' => 1,
                    'default_value' => 90,
                    'min' => 1,
                    'max' => 100,
                    'instructions' => 'Prozentsatz des Videos, der angesehen werden muss',
                ],
                [
                    'key' => 'field_vw_points',
                    'label' => 'Fortbildungspunkte (EBCP)',
                    'name' => 'ebcp_points',
                    'type' => 'number',
                    'required' => 1,
                    'default_value' => 1,
                    'step' => 0.5,
                    'min' => 0,
                ],
                [
                    'key' => 'field_vw_vnr',
                    'label' => 'VNR',
                    'name' => 'vnr',
                    'type' => 'text',
                    'required' => 0,
                ],
                [
                    'key' => 'field_vw_type',
                    'label' => 'Art der Fortbildung',
                    'name' => 'fortbildung_type',
                    'type' => 'text',
                    'required' => 0,
                    'default_value' => 'Webinar',
                ],
                [
                    'key' => 'field_vw_location',
                    'label' => 'Ort',
                    'name' => 'location',
                    'type' => 'text',
                    'required' => 0,
                    'default_value' => 'Online',
                ],
                [
                    'key' => 'field_vw_certificate_bg',
                    'label' => 'Zertifikat Hintergrundbild',
                    'name' => 'certificate_background',
                    'type' => 'image',
                    'required' => 0,
                    'return_format' => 'array',
                    'instructions' => 'Optional: PNG/JPG als Hintergrund für das Zertifikat',
                ],
                [
                    'key' => 'field_vw_certificate_watermark',
                    'label' => 'Zertifikat Wasserzeichen',
                    'name' => 'certificate_watermark',
                    'type' => 'image',
                    'required' => 0,
                    'return_format' => 'array',
                    'instructions' => 'Optional: PNG als Wasserzeichen für das Zertifikat',
                ],
            ],
            'location' => [
                [
                    [
                        'param' => 'post_type',
                        'operator' => '==',
                        'value' => 'vimeo_webinar',
                    ],
                ],
            ],
        ]);

    }

    /**
     * Enqueue Frontend Assets
     */
    public function enqueue_assets() {
        global $post;

        if (!$post) {
            return;
        }

        $content = $post->post_content ?? '';
        $has_shortcode = has_shortcode($content, 'vimeo_webinar') ||
                        has_shortcode($content, 'vimeo_webinar_manager') ||
                        has_shortcode($content, 'vimeo_webinar_liste');

        if (!$has_shortcode && get_post_type() !== 'vimeo_webinar') {
            return;
        }

        $this->force_enqueue_assets();
    }

    /**
     * Force enqueue assets (called from shortcodes)
     */
    public function force_enqueue_assets() {
        // Vimeo Player API
        if (!wp_script_is('vimeo-player', 'enqueued')) {
            wp_enqueue_script('vimeo-player', 'https://player.vimeo.com/api/player.js', [], null, true);
        }

        // Plugin CSS
        if (!wp_style_is('vw-style', 'enqueued')) {
            wp_enqueue_style('vw-style', $this->plugin_url . 'assets/css/style.css', [], '1.2.5');
        }

        // Plugin JS
        if (!wp_script_is('vw-script', 'enqueued')) {
            wp_enqueue_script('vw-script', $this->plugin_url . 'assets/js/script.js', ['jquery', 'vimeo-player'], '1.2.5', true);
        }

        // WICHTIG: Localize immer aufrufen, auch wenn Script bereits enqueued ist!
        // Dies stellt sicher dass vwData verfügbar ist
        if (wp_script_is('vw-script', 'enqueued') || wp_script_is('vw-script', 'registered')) {
            wp_localize_script('vw-script', 'vwData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('vw_nonce'),
                'userId' => get_current_user_id(),
            ]);
        }
    }

    /**
     * Shortcode: Vimeo Webinar Player
     * Usage: [vimeo_webinar id="123"]
     */
    public function webinar_player_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="vw-error-message"><p>Sie müssen angemeldet sein, um Webinare anzusehen.</p></div>';
        }

        $atts = shortcode_atts([
            'id' => 0,
        ], $atts);

        $post_id = intval($atts['id']);

        // Debug: Check if post_id is valid
        if (!$post_id) {
            return '<div class="vw-error-message"><p>Fehler: Keine Webinar-ID angegeben. Verwenden Sie: [vimeo_webinar id="123"]</p></div>';
        }

        // Check if post exists and is correct type
        $post = get_post($post_id);
        if (!$post) {
            return '<div class="vw-error-message"><p>Fehler: Webinar mit ID ' . $post_id . ' nicht gefunden.</p></div>';
        }

        if (get_post_type($post_id) !== 'vimeo_webinar') {
            return '<div class="vw-error-message"><p>Fehler: Post ID ' . $post_id . ' ist kein Webinar (Typ: ' . get_post_type($post_id) . ').</p></div>';
        }

        // Get ACF fields
        $vimeo_id = get_field('vimeo_id', $post_id);
        $completion_percentage = get_field('completion_percentage', $post_id);

        // Set defaults if empty
        if (!$completion_percentage) {
            $completion_percentage = 90;
        }

        if (!$vimeo_id) {
            return '<div class="vw-error-message"><p>Fehler: Vimeo Video ID fehlt. Bitte bearbeiten Sie das Webinar und fügen Sie eine Vimeo ID hinzu.</p></div>';
        }

        $user_id = get_current_user_id();
        $progress = $this->get_user_progress($user_id, $post_id);
        $is_completed = $this->is_webinar_completed($user_id, $post_id);

        // Force assets to load
        $this->force_enqueue_assets();

        ob_start();
        include $this->plugin_path . 'templates/player.php';
        return ob_get_clean();
    }

    /**
     * Shortcode: Webinar Liste für Benutzer
     * Usage: [vimeo_webinar_liste]
     */
    public function webinar_liste_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="vw-error-message"><p>Sie müssen angemeldet sein.</p></div>';
        }

        $user_id = get_current_user_id();

        // Get all webinars
        $webinars = get_posts([
            'post_type' => 'vimeo_webinar',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        // Force assets to load
        $this->force_enqueue_assets();

        ob_start();
        include $this->plugin_path . 'templates/liste.php';
        return ob_get_clean();
    }

    /**
     * Shortcode: Frontend Manager
     * Usage: [vimeo_webinar_manager]
     * Note: Berechtigung wird anderweitig vergeben (z.B. via Seiten-Zugriffskontrolle)
     */
    public function webinar_manager_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="vw-error-message"><p>Sie müssen angemeldet sein.</p></div>';
        }

        // Force assets to load
        $this->force_enqueue_assets();

        ob_start();
        include $this->plugin_path . 'templates/manager.php';
        return ob_get_clean();
    }

    /**
     * AJAX: Track Progress (speichert angesehene Zeit, nicht Position)
     */
    public function ajax_track_progress() {
        check_ajax_referer('vw_nonce', 'nonce');

        $webinar_id = intval($_POST['webinar_id'] ?? 0);
        $watched_time = floatval($_POST['watched_time'] ?? 0); // Sekunden tatsächlich angesehen
        $duration = floatval($_POST['duration'] ?? 0); // Gesamt-Dauer des Videos

        if (!$webinar_id) {
            wp_send_json_error(['message' => 'Invalid webinar ID']);
        }

        $user_id = get_current_user_id();

        // Video-Dauer cachen (einmalig)
        if ($duration > 0) {
            $cached_duration = get_post_meta($webinar_id, '_vw_video_duration', true);
            if (!$cached_duration) {
                update_post_meta($webinar_id, '_vw_video_duration', $duration);
            }
        }

        if ($user_id) {
            // Eingeloggter Benutzer - in DB speichern
            $meta_key = '_vw_watched_time_' . $webinar_id;
            $current_watched = floatval(get_user_meta($user_id, $meta_key, true));

            // Addiere neue angesehene Zeit (aber max. Videolänge)
            $new_watched = min($duration, $current_watched + $watched_time);
            update_user_meta($user_id, $meta_key, $new_watched);

            // Berechne Prozent
            $progress = $duration > 0 ? min(100, ($new_watched / $duration) * 100) : 0;

            wp_send_json_success([
                'watched_time' => $new_watched,
                'progress' => $progress,
                'logged_in' => true
            ]);
        } else {
            // Nicht eingeloggt - nur Cookie, keine DB-Speicherung
            wp_send_json_success([
                'watched_time' => $watched_time,
                'progress' => 0,
                'logged_in' => false,
                'message' => 'Bitte einloggen für Fortbildungsnachweis'
            ]);
        }
    }

    /**
     * AJAX: Complete Webinar
     */
    public function ajax_complete_webinar() {
        check_ajax_referer('vw_nonce', 'nonce');

        $user_id = get_current_user_id();
        $webinar_id = intval($_POST['webinar_id'] ?? 0);

        error_log('VW Complete Webinar - User: ' . $user_id . ', Webinar: ' . $webinar_id);

        if (!$user_id || !$webinar_id) {
            error_log('VW Complete Webinar - ERROR: Invalid data');
            wp_send_json_error(['message' => 'Invalid data']);
        }

        // Check if already completed
        if ($this->is_webinar_completed($user_id, $webinar_id)) {
            error_log('VW Complete Webinar - Already completed');
            wp_send_json_error(['message' => 'Webinar bereits abgeschlossen']);
        }

        // Create Fortbildung entry
        error_log('VW Complete Webinar - Creating Fortbildung entry...');
        $fortbildung_id = $this->create_fortbildung_entry($user_id, $webinar_id);

        if (!$fortbildung_id) {
            error_log('VW Complete Webinar - ERROR: Failed to create Fortbildung entry');
            wp_send_json_error(['message' => 'Fehler beim Erstellen des Fortbildungseintrags']);
        }

        error_log('VW Complete Webinar - Fortbildung created: ' . $fortbildung_id);

        // Generate certificate
        error_log('VW Complete Webinar - Generating certificate...');
        $pdf_result = $this->generate_certificate_pdf($user_id, $webinar_id);

        if ($pdf_result && isset($pdf_result['url'])) {
            $pdf_url = $pdf_result['url'];
            $attachment_id = $pdf_result['attachment_id'] ?? 0;

            error_log('VW Complete Webinar - Certificate generated: ' . $pdf_url);

            // Add certificate as attachment to fortbildung post
            if ($attachment_id) {
                update_field('attachements', $attachment_id, $fortbildung_id);
                error_log('VW Complete Webinar - Certificate attached to fortbildung: ' . $attachment_id);
            }

            // Send email with certificate
            error_log('VW Complete Webinar - Sending email...');
            $mail_sent = $this->send_certificate_email($user_id, $webinar_id, $pdf_url);
            error_log('VW Complete Webinar - Email sent: ' . ($mail_sent ? 'Yes' : 'No'));
        } else {
            error_log('VW Complete Webinar - ERROR: Failed to generate certificate');
            $pdf_url = null;
        }

        error_log('VW Complete Webinar - SUCCESS!');

        wp_send_json_success([
            'message' => 'Webinar erfolgreich abgeschlossen!',
            'fortbildung_id' => $fortbildung_id,
            'certificate_url' => $pdf_url,
        ]);
    }

    /**
     * AJAX: Generate Certificate
     */
    public function ajax_generate_certificate() {
        check_ajax_referer('vw_nonce', 'nonce');

        $user_id = get_current_user_id();
        $webinar_id = intval($_POST['webinar_id'] ?? 0);

        if (!$user_id || !$webinar_id) {
            wp_send_json_error(['message' => 'Invalid data']);
        }

        // Check if completed
        if (!$this->is_webinar_completed($user_id, $webinar_id)) {
            wp_send_json_error(['message' => 'Webinar noch nicht abgeschlossen']);
        }

        // Generate PDF
        $pdf_result = $this->generate_certificate_pdf($user_id, $webinar_id);

        if (!$pdf_result || !isset($pdf_result['url'])) {
            wp_send_json_error(['message' => 'Fehler beim Generieren des Zertifikats']);
        }

        wp_send_json_success(['pdf_url' => $pdf_result['url']]);
    }

    /**
     * Helper: Get User Progress (Prozent basierend auf angesehener Zeit)
     */
    private function get_user_progress($user_id, $webinar_id) {
        $watched_time = $this->get_watched_time($user_id, $webinar_id);
        $total_duration = $this->get_video_duration($webinar_id);

        if ($total_duration > 0) {
            return min(100, ($watched_time / $total_duration) * 100);
        }

        return 0;
    }

    /**
     * Helper: Get watched time in seconds
     */
    private function get_watched_time($user_id, $webinar_id) {
        if (!$user_id) {
            return 0;
        }

        $meta_key = '_vw_watched_time_' . $webinar_id;
        $watched_time = get_user_meta($user_id, $meta_key, true);
        return floatval($watched_time);
    }

    /**
     * Helper: Get video duration (cached in post meta)
     */
    private function get_video_duration($webinar_id) {
        $duration = get_post_meta($webinar_id, '_vw_video_duration', true);
        return floatval($duration);
    }

    /**
     * Helper: Get cookie data for non-logged-in users
     */
    private function get_cookie_data($webinar_id) {
        $cookie_name = 'vw_webinar_' . $webinar_id;

        if (isset($_COOKIE[$cookie_name])) {
            $data = json_decode(stripslashes($_COOKIE[$cookie_name]), true);

            if (is_array($data)) {
                return [
                    'watched_time' => floatval($data['watched_time'] ?? 0),
                    'progress' => floatval($data['progress'] ?? 0),
                ];
            }
        }

        return [
            'watched_time' => 0,
            'progress' => 0,
        ];
    }

    /**
     * Helper: Check if Webinar Completed
     */
    private function is_webinar_completed($user_id, $webinar_id) {
        if (!$user_id) {
            return false;
        }

        $meta_key = '_vw_completed_' . $webinar_id;
        return (bool) get_user_meta($user_id, $meta_key, true);
    }

    /**
     * Helper: Create Fortbildung Entry
     */
    private function create_fortbildung_entry($user_id, $webinar_id) {
        error_log('VW Create Fortbildung - Start: User ' . $user_id . ', Webinar ' . $webinar_id);

        $webinar = get_post($webinar_id);

        if (!$webinar) {
            error_log('VW Create Fortbildung - ERROR: Webinar not found');
            return false;
        }

        error_log('VW Create Fortbildung - Webinar found: ' . $webinar->post_title);

        // Doubletten-Prüfung: Gibt es bereits einen Eintrag für dieses Webinar?
        $existing = get_posts([
            'post_type' => 'fortbildung',
            'meta_query' => [
                'relation' => 'AND',
                [
                    'key' => 'user',
                    'value' => $user_id,
                    'compare' => '='
                ],
                [
                    'key' => '_vw_webinar_id',
                    'value' => $webinar_id,
                    'compare' => '='
                ]
            ],
            'posts_per_page' => 1,
            'post_status' => 'publish'
        ]);

        if (!empty($existing)) {
            error_log('VW Create Fortbildung - Existing entry found: ' . $existing[0]->ID);
            return $existing[0]->ID;
        }

        error_log('VW Create Fortbildung - No existing entry, creating new...');

        $points = get_field('ebcp_points', $webinar_id) ?: 1;
        $vnr = get_field('vnr', $webinar_id) ?: '';
        $user = get_userdata($user_id);

        error_log('VW Create Fortbildung - Points: ' . $points . ', VNR: ' . $vnr);

        // Token generieren für Verifikation
        $token = wp_generate_password(32, false);

        // Fortbildung Post erstellen
        $fortbildung_id = wp_insert_post([
            'post_type' => 'fortbildung',
            'post_title' => $webinar->post_title . ' - ' . $user->display_name,
            'post_status' => 'publish',
            'post_author' => 1, // System-User
        ], true); // true = return WP_Error on failure

        if (is_wp_error($fortbildung_id)) {
            error_log('VW Create Fortbildung - ERROR: wp_insert_post failed: ' . $fortbildung_id->get_error_message());
            return false;
        }

        if (!$fortbildung_id) {
            error_log('VW Create Fortbildung - ERROR: wp_insert_post returned 0');
            return false;
        }

        error_log('VW Create Fortbildung - Post created: ' . $fortbildung_id);

        // ACF Felder setzen
        error_log('VW Create Fortbildung - Setting ACF fields...');
        update_field('user', $user_id, $fortbildung_id);
        update_field('date', current_time('Y-m-d'), $fortbildung_id);
        update_field('location', 'Online', $fortbildung_id); // Immer "Online"
        update_field('type', 'Webinar', $fortbildung_id);
        update_field('points', $points, $fortbildung_id);
        update_field('vnr', $vnr, $fortbildung_id);
        update_field('token', $token, $fortbildung_id);
        update_field('freigegeben', true, $fortbildung_id); // Automatisch freigegeben
        update_field('freigabe_durch', 'System (Webinar)', $fortbildung_id);
        update_field('freigabe_mail', get_option('admin_email'), $fortbildung_id);

        error_log('VW Create Fortbildung - ACF fields set');

        // Webinar-ID als Meta speichern für Doubletten-Check
        update_post_meta($fortbildung_id, '_vw_webinar_id', $webinar_id);

        // Mark as completed
        $meta_key = '_vw_completed_' . $webinar_id;
        update_user_meta($user_id, $meta_key, true);
        update_user_meta($user_id, $meta_key . '_date', current_time('mysql'));
        update_user_meta($user_id, '_vw_fortbildung_' . $webinar_id, $fortbildung_id);

        error_log('VW Create Fortbildung - Completed. Fortbildung ID: ' . $fortbildung_id);

        return $fortbildung_id;
    }

    /**
     * Helper: Generate Certificate PDF
     */
    private function generate_certificate_pdf($user_id, $webinar_id) {
        // Load FPDF
        $fpdf_path = DGPTM_SUITE_PATH . 'libraries/fpdf/fpdf.php';

        if (!file_exists($fpdf_path)) {
            return false;
        }

        require_once $fpdf_path;

        $user = get_userdata($user_id);
        $webinar = get_post($webinar_id);
        $points = get_field('ebcp_points', $webinar_id) ?: 1;
        $vnr = get_field('vnr', $webinar_id) ?: '';
        $date = current_time('d.m.Y');

        // Get global certificate settings
        $settings = get_option('vw_certificate_settings', []);
        $orientation = $settings['orientation'] ?? 'L';
        $bg_id = $settings['background_image'] ?? 0;
        $logo_id = $settings['logo_image'] ?? 0;
        $header_text = $settings['header_text'] ?? 'Teilnahmebescheinigung';
        $footer_text = $settings['footer_text'] ?? get_bloginfo('name');
        $signature_text = $settings['signature_text'] ?? '';

        // Create PDF
        $pdf = new FPDF($orientation, 'mm', 'A4');
        $pdf->AddPage();

        // Background image
        if ($bg_id) {
            $bg_url = wp_get_attachment_url($bg_id);
            if ($bg_url) {
                $bg_path = str_replace(home_url('/'), ABSPATH, $bg_url);
                if (file_exists($bg_path)) {
                    $width = $orientation === 'L' ? 297 : 210;
                    $height = $orientation === 'L' ? 210 : 297;
                    $pdf->Image($bg_path, 0, 0, $width, $height);
                }
            }
        }

        // Logo
        if ($logo_id) {
            $logo_url = wp_get_attachment_url($logo_id);
            if ($logo_url) {
                $logo_path = str_replace(home_url('/'), ABSPATH, $logo_url);
                if (file_exists($logo_path)) {
                    $pdf->Image($logo_path, 15, 15, 40);
                }
            }
        }

        // Header
        $pdf->SetFont('Arial', 'B', 24);
        $pdf->SetY(50);
        $pdf->Cell(0, 10, $this->pdf_text($header_text), 0, 1, 'C');

        // Webinar Title
        $pdf->SetFont('Arial', 'B', 18);
        $pdf->SetY(80);
        $pdf->MultiCell(0, 10, $this->pdf_text($webinar->post_title), 0, 'C');

        // User Name
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->SetY(110);
        $pdf->Cell(0, 10, $this->pdf_text($user->display_name), 0, 1, 'C');

        // Details
        $pdf->SetFont('Arial', '', 12);
        $pdf->SetY(130);
        $pdf->Cell(0, 8, $this->pdf_text('hat erfolgreich am o.g. Webinar teilgenommen'), 0, 1, 'C');

        // Points
        $pdf->SetY(150);
        $pdf->SetFont('Arial', 'B', 14);
        $pdf->Cell(0, 10, $this->pdf_text('Fortbildungspunkte: ' . $points . ' EBCP'), 0, 1, 'C');

        // VNR
        if ($vnr) {
            $pdf->SetFont('Arial', '', 12);
            $pdf->SetY(165);
            $pdf->Cell(0, 8, $this->pdf_text('VNR: ' . $vnr), 0, 1, 'C');
        }

        // Date
        $pdf->SetFont('Arial', '', 11);
        $pdf->SetY(180);
        $pdf->Cell(0, 8, $this->pdf_text('Datum: ' . $date), 0, 1, 'C');

        // Signature
        if ($signature_text) {
            $pdf->SetY(195);
            $pdf->SetFont('Arial', 'I', 10);
            $pdf->Cell(0, 5, $this->pdf_text($signature_text), 0, 1, 'C');
        }

        // Footer
        if ($footer_text) {
            $pdf->SetY(-15);
            $pdf->SetFont('Arial', '', 9);
            $pdf->Cell(0, 10, $this->pdf_text($footer_text), 0, 0, 'C');
        }

        // Save PDF
        $upload_dir = wp_upload_dir();
        $pdf_dir = $upload_dir['basedir'] . '/webinar-certificates/';

        if (!file_exists($pdf_dir)) {
            wp_mkdir_p($pdf_dir);
        }

        $filename = 'certificate-' . $user_id . '-' . $webinar_id . '-' . time() . '.pdf';
        $pdf_path = $pdf_dir . $filename;
        $pdf->Output('F', $pdf_path);

        $pdf_url = $upload_dir['baseurl'] . '/webinar-certificates/' . $filename;

        // Import PDF into WordPress Media Library as attachment
        $attachment_id = 0;

        // Check if file exists
        if (file_exists($pdf_path)) {
            $filetype = wp_check_filetype($filename, null);

            $attachment = [
                'guid' => $pdf_url,
                'post_mime_type' => $filetype['type'],
                'post_title' => sanitize_file_name(pathinfo($filename, PATHINFO_FILENAME)),
                'post_content' => '',
                'post_status' => 'inherit'
            ];

            // Insert attachment
            $attachment_id = wp_insert_attachment($attachment, $pdf_path);

            if (!is_wp_error($attachment_id) && $attachment_id) {
                // Generate attachment metadata
                require_once(ABSPATH . 'wp-admin/includes/image.php');
                $attachment_data = wp_generate_attachment_metadata($attachment_id, $pdf_path);
                wp_update_attachment_metadata($attachment_id, $attachment_data);

                error_log('VW Certificate PDF - Attachment created: ' . $attachment_id);
            } else {
                error_log('VW Certificate PDF - ERROR: Failed to create attachment');
            }
        }

        // Return URL and attachment ID
        return [
            'url' => $pdf_url,
            'attachment_id' => $attachment_id
        ];
    }

    /**
     * Helper: Convert text for FPDF (UTF-8 to ISO-8859-1)
     */
    private function pdf_text($text) {
        if (function_exists('iconv')) {
            return iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $text);
        }
        return utf8_decode($text);
    }

    /**
     * Helper: Send Certificate Email
     */
    private function send_certificate_email($user_id, $webinar_id, $pdf_url) {
        $user = get_userdata($user_id);
        $webinar = get_post($webinar_id);

        if (!$user || !$webinar) {
            return false;
        }

        // Get mail settings
        $settings = get_option('vw_certificate_settings', []);
        $mail_enabled = $settings['mail_enabled'] ?? true;
        $mail_subject = $settings['mail_subject'] ?? 'Ihr Webinar-Zertifikat: {webinar_title}';
        $mail_body = $settings['mail_body'] ?? "Hallo {user_name},\n\nvielen Dank für Ihre Teilnahme am Webinar \"{webinar_title}\".\n\nIhr Teilnahmezertifikat steht zum Download bereit:\n{certificate_url}\n\nMit freundlichen Grüßen\nIhr Webinar-Team";
        $mail_from = $settings['mail_from'] ?? get_option('admin_email');
        $mail_from_name = $settings['mail_from_name'] ?? get_bloginfo('name');

        if (!$mail_enabled) {
            return false;
        }

        $points = get_field('ebcp_points', $webinar_id) ?: 1;

        // Replace placeholders
        $replacements = [
            '{user_name}' => $user->display_name,
            '{user_first_name}' => $user->first_name,
            '{user_last_name}' => $user->last_name,
            '{user_email}' => $user->user_email,
            '{webinar_title}' => $webinar->post_title,
            '{webinar_url}' => home_url('/wissen/webinar/' . $webinar_id),
            '{certificate_url}' => $pdf_url,
            '{points}' => $points,
            '{date}' => current_time('d.m.Y'),
        ];

        $subject = str_replace(array_keys($replacements), array_values($replacements), $mail_subject);
        $message = str_replace(array_keys($replacements), array_values($replacements), $mail_body);

        // Set headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $mail_from_name . ' <' . $mail_from . '>'
        ];

        // Convert line breaks to HTML
        $message = nl2br($message);

        // Send email
        return wp_mail($user->user_email, $subject, $message, $headers);
    }

    /**
     * Helper: Get Webinar URL
     */
    public function get_webinar_url($webinar_id) {
        return home_url('/wissen/webinar/' . $webinar_id);
    }

    /**
     * Helper: Get Webinar URL (alternative query string format)
     */
    public function get_webinar_url_query($webinar_id) {
        return home_url('/wissen/webinar?id=' . $webinar_id);
    }

    /**
     * AJAX: Manager Create Webinar
     */
    public function ajax_manager_create() {
        check_ajax_referer('vw_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        $title = sanitize_text_field($_POST['title'] ?? '');
        $description = wp_kses_post($_POST['description'] ?? '');
        $vimeo_id = sanitize_text_field($_POST['vimeo_id'] ?? '');
        $completion = intval($_POST['completion_percentage'] ?? 90);
        $points = floatval($_POST['points'] ?? 1);
        $vnr = sanitize_text_field($_POST['vnr'] ?? '');

        if (!$title || !$vimeo_id) {
            wp_send_json_error(['message' => 'Titel und Vimeo ID sind erforderlich']);
        }

        $post_id = wp_insert_post([
            'post_type' => 'vimeo_webinar',
            'post_title' => $title,
            'post_content' => $description,
            'post_status' => 'publish',
        ]);

        if (!$post_id) {
            wp_send_json_error(['message' => 'Fehler beim Erstellen']);
        }

        update_field('vimeo_id', $vimeo_id, $post_id);
        update_field('completion_percentage', $completion, $post_id);
        update_field('ebcp_points', $points, $post_id);
        update_field('vnr', $vnr, $post_id);

        wp_send_json_success(['post_id' => $post_id, 'message' => 'Webinar erstellt']);
    }

    /**
     * AJAX: Manager Update Webinar
     */
    public function ajax_manager_update() {
        check_ajax_referer('vw_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        $post_id = intval($_POST['post_id'] ?? 0);

        if (!$post_id || get_post_type($post_id) !== 'vimeo_webinar') {
            wp_send_json_error(['message' => 'Ungültige Webinar ID']);
        }

        $title = sanitize_text_field($_POST['title'] ?? '');
        $description = wp_kses_post($_POST['description'] ?? '');
        $vimeo_id = sanitize_text_field($_POST['vimeo_id'] ?? '');
        $completion = intval($_POST['completion_percentage'] ?? 90);
        $points = floatval($_POST['points'] ?? 1);
        $vnr = sanitize_text_field($_POST['vnr'] ?? '');

        wp_update_post([
            'ID' => $post_id,
            'post_title' => $title,
            'post_content' => $description,
        ]);

        update_field('vimeo_id', $vimeo_id, $post_id);
        update_field('completion_percentage', $completion, $post_id);
        update_field('ebcp_points', $points, $post_id);
        update_field('vnr', $vnr, $post_id);

        wp_send_json_success(['message' => 'Webinar aktualisiert']);
    }

    /**
     * AJAX: Manager Delete Webinar
     */
    public function ajax_manager_delete() {
        check_ajax_referer('vw_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        $post_id = intval($_POST['post_id'] ?? 0);

        if (!$post_id || get_post_type($post_id) !== 'vimeo_webinar') {
            wp_send_json_error(['message' => 'Ungültige Webinar ID']);
        }

        $result = wp_delete_post($post_id, true);

        if (!$result) {
            wp_send_json_error(['message' => 'Fehler beim Löschen']);
        }

        wp_send_json_success(['message' => 'Webinar gelöscht']);
    }

    /**
     * AJAX: Manager Statistics
     */
    public function ajax_manager_stats() {
        check_ajax_referer('vw_nonce', 'nonce');

        if (!is_user_logged_in()) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        $webinar_id = intval($_POST['webinar_id'] ?? 0);

        if (!$webinar_id) {
            wp_send_json_error(['message' => 'Webinar ID erforderlich']);
        }

        // Get completion statistics
        $stats = $this->get_webinar_stats($webinar_id);

        wp_send_json_success($stats);
    }

    /**
     * Helper: Get Webinar Statistics
     */
    private function get_webinar_stats($webinar_id) {
        global $wpdb;

        // Count completed users
        $meta_key = '_vw_completed_' . $webinar_id;
        $completed = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = '1'",
            $meta_key
        ));

        // Get users with progress > 0
        $progress_meta_key = '_vw_progress_' . $webinar_id;
        $in_progress = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(DISTINCT user_id) FROM {$wpdb->usermeta} WHERE meta_key = %s AND CAST(meta_value AS DECIMAL) > 0 AND CAST(meta_value AS DECIMAL) < 100",
            $progress_meta_key
        ));

        return [
            'completed' => intval($completed),
            'in_progress' => intval($in_progress),
            'total_views' => intval($completed) + intval($in_progress),
        ];
    }

    /**
     * Add Admin Menu
     */
    public function add_admin_menu() {
        add_submenu_page(
            'edit.php?post_type=vimeo_webinar',
            'Webinar Einstellungen',
            'Einstellungen',
            'manage_options',
            'vimeo-webinar-settings',
            [$this, 'render_admin_settings']
        );

        add_submenu_page(
            'edit.php?post_type=vimeo_webinar',
            'Webinar Statistiken',
            'Statistiken',
            'manage_options',
            'vimeo-webinar-stats',
            [$this, 'render_admin_stats']
        );
    }

    /**
     * Render Admin Settings Page
     */
    public function render_admin_settings() {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung');
        }

        // Save settings
        if (isset($_POST['vw_save_settings']) && check_admin_referer('vw_settings_nonce', 'vw_settings_nonce')) {
            $settings = [
                'orientation' => sanitize_text_field($_POST['orientation'] ?? 'L'),
                'background_image' => intval($_POST['background_image'] ?? 0),
                'logo_image' => intval($_POST['logo_image'] ?? 0),
                'header_text' => sanitize_text_field($_POST['header_text'] ?? 'Teilnahmebescheinigung'),
                'footer_text' => sanitize_text_field($_POST['footer_text'] ?? get_bloginfo('name')),
                'signature_text' => sanitize_text_field($_POST['signature_text'] ?? ''),
                // Mail settings
                'mail_enabled' => isset($_POST['mail_enabled']),
                'mail_subject' => sanitize_text_field($_POST['mail_subject'] ?? 'Ihr Webinar-Zertifikat: {webinar_title}'),
                'mail_body' => wp_kses_post($_POST['mail_body'] ?? ''),
                'mail_from' => sanitize_email($_POST['mail_from'] ?? get_option('admin_email')),
                'mail_from_name' => sanitize_text_field($_POST['mail_from_name'] ?? get_bloginfo('name')),
            ];

            update_option('vw_certificate_settings', $settings);
            echo '<div class="notice notice-success"><p>Einstellungen gespeichert!</p></div>';
        }

        $settings = get_option('vw_certificate_settings', []);
        $orientation = $settings['orientation'] ?? 'L';
        $bg_id = $settings['background_image'] ?? 0;
        $logo_id = $settings['logo_image'] ?? 0;
        $header_text = $settings['header_text'] ?? 'Teilnahmebescheinigung';
        $footer_text = $settings['footer_text'] ?? get_bloginfo('name');
        $signature_text = $settings['signature_text'] ?? '';
        // Mail settings
        $mail_enabled = $settings['mail_enabled'] ?? true;
        $mail_subject = $settings['mail_subject'] ?? 'Ihr Webinar-Zertifikat: {webinar_title}';
        $mail_body = $settings['mail_body'] ?? "Hallo {user_name},\n\nvielen Dank für Ihre Teilnahme am Webinar \"{webinar_title}\".\n\nIhr Teilnahmezertifikat steht zum Download bereit:\n{certificate_url}\n\nMit freundlichen Grüßen\nIhr Webinar-Team";
        $mail_from = $settings['mail_from'] ?? get_option('admin_email');
        $mail_from_name = $settings['mail_from_name'] ?? get_bloginfo('name');

        ?>
        <div class="wrap">
            <h1>Webinar Einstellungen</h1>

            <form method="post" enctype="multipart/form-data">
                <?php wp_nonce_field('vw_settings_nonce', 'vw_settings_nonce'); ?>

                <h2>Zertifikat-Template</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">Ausrichtung</th>
                        <td>
                            <select name="orientation">
                                <option value="L" <?php selected($orientation, 'L'); ?>>Querformat (Landscape)</option>
                                <option value="P" <?php selected($orientation, 'P'); ?>>Hochformat (Portrait)</option>
                            </select>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Hintergrundbild</th>
                        <td>
                            <input type="hidden" name="background_image" id="background_image" value="<?php echo esc_attr($bg_id); ?>">
                            <button type="button" class="button vw-upload-image" data-target="background_image">Bild hochladen</button>
                            <button type="button" class="button vw-remove-image" data-target="background_image">Entfernen</button>
                            <div id="background_image_preview" style="margin-top: 10px;">
                                <?php if ($bg_id): ?>
                                    <img src="<?php echo esc_url(wp_get_attachment_url($bg_id)); ?>" style="max-width: 300px; height: auto;">
                                <?php endif; ?>
                            </div>
                            <p class="description">Empfohlene Größe: 297x210mm (Querformat) oder 210x297mm (Hochformat) bei 300 DPI</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Logo</th>
                        <td>
                            <input type="hidden" name="logo_image" id="logo_image" value="<?php echo esc_attr($logo_id); ?>">
                            <button type="button" class="button vw-upload-image" data-target="logo_image">Logo hochladen</button>
                            <button type="button" class="button vw-remove-image" data-target="logo_image">Entfernen</button>
                            <div id="logo_image_preview" style="margin-top: 10px;">
                                <?php if ($logo_id): ?>
                                    <img src="<?php echo esc_url(wp_get_attachment_url($logo_id)); ?>" style="max-width: 200px; height: auto;">
                                <?php endif; ?>
                            </div>
                            <p class="description">Wird oben links platziert (40mm Breite)</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Kopfzeile</th>
                        <td>
                            <input type="text" name="header_text" value="<?php echo esc_attr($header_text); ?>" class="regular-text">
                            <p class="description">Hauptüberschrift des Zertifikats</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Fußzeile</th>
                        <td>
                            <input type="text" name="footer_text" value="<?php echo esc_attr($footer_text); ?>" class="regular-text">
                            <p class="description">Text am unteren Rand</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Unterschrift/Bestätigung</th>
                        <td>
                            <input type="text" name="signature_text" value="<?php echo esc_attr($signature_text); ?>" class="regular-text">
                            <p class="description">z.B. "Unterschrift", "Veranstalter", etc.</p>
                        </td>
                    </tr>
                </table>

                <h2>E-Mail-Benachrichtigungen</h2>
                <table class="form-table">
                    <tr>
                        <th scope="row">E-Mail aktiviert</th>
                        <td>
                            <label>
                                <input type="checkbox" name="mail_enabled" value="1" <?php checked($mail_enabled, true); ?>>
                                Zertifikat automatisch per E-Mail verschicken
                            </label>
                            <p class="description">Sendet das Zertifikat nach Abschluss automatisch an den Benutzer</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Absender-Name</th>
                        <td>
                            <input type="text" name="mail_from_name" value="<?php echo esc_attr($mail_from_name); ?>" class="regular-text">
                            <p class="description">Name des Absenders (z.B. Ihr Organisationsname)</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">Absender-E-Mail</th>
                        <td>
                            <input type="email" name="mail_from" value="<?php echo esc_attr($mail_from); ?>" class="regular-text">
                            <p class="description">E-Mail-Adresse des Absenders</p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">E-Mail-Betreff</th>
                        <td>
                            <input type="text" name="mail_subject" value="<?php echo esc_attr($mail_subject); ?>" class="large-text">
                            <p class="description">
                                Verfügbare Platzhalter:
                                <code>{user_name}</code>,
                                <code>{user_first_name}</code>,
                                <code>{user_last_name}</code>,
                                <code>{webinar_title}</code>,
                                <code>{points}</code>,
                                <code>{date}</code>
                            </p>
                        </td>
                    </tr>

                    <tr>
                        <th scope="row">E-Mail-Text</th>
                        <td>
                            <textarea name="mail_body" rows="10" class="large-text code"><?php echo esc_textarea($mail_body); ?></textarea>
                            <p class="description">
                                Verfügbare Platzhalter:
                                <code>{user_name}</code>,
                                <code>{user_first_name}</code>,
                                <code>{user_last_name}</code>,
                                <code>{user_email}</code>,
                                <code>{webinar_title}</code>,
                                <code>{webinar_url}</code>,
                                <code>{certificate_url}</code>,
                                <code>{points}</code>,
                                <code>{date}</code>
                            </p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" name="vw_save_settings" class="button button-primary" value="Einstellungen speichern">
                </p>
            </form>
        </div>

        <script>
        jQuery(document).ready(function($) {
            var mediaUploader;

            $('.vw-upload-image').on('click', function(e) {
                e.preventDefault();
                var button = $(this);
                var targetId = button.data('target');

                if (mediaUploader) {
                    mediaUploader.open();
                    return;
                }

                mediaUploader = wp.media({
                    title: 'Bild auswählen',
                    button: {
                        text: 'Bild verwenden'
                    },
                    multiple: false
                });

                mediaUploader.on('select', function() {
                    var attachment = mediaUploader.state().get('selection').first().toJSON();
                    $('#' + targetId).val(attachment.id);
                    $('#' + targetId + '_preview').html('<img src="' + attachment.url + '" style="max-width: 300px; height: auto;">');
                });

                mediaUploader.open();
            });

            $('.vw-remove-image').on('click', function(e) {
                e.preventDefault();
                var targetId = $(this).data('target');
                $('#' + targetId).val('');
                $('#' + targetId + '_preview').html('');
            });
        });
        </script>
        <?php
    }

    /**
     * Render Admin Statistics Page
     */
    public function render_admin_stats() {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung');
        }

        $webinars = get_posts([
            'post_type' => 'vimeo_webinar',
            'posts_per_page' => -1,
            'post_status' => 'publish',
        ]);

        echo '<div class="wrap">';
        echo '<h1>Webinar Statistiken</h1>';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Webinar</th><th>Abgeschlossen</th><th>In Bearbeitung</th><th>Gesamt Ansichten</th></tr></thead>';
        echo '<tbody>';

        foreach ($webinars as $webinar) {
            $stats = $this->get_webinar_stats($webinar->ID);
            echo '<tr>';
            echo '<td>' . esc_html($webinar->post_title) . '</td>';
            echo '<td>' . $stats['completed'] . '</td>';
            echo '<td>' . $stats['in_progress'] . '</td>';
            echo '<td>' . $stats['total_views'] . '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';
        echo '</div>';
    }
}

} // End class check

// Prevent double initialization
if (!isset($GLOBALS['dgptm_vimeo_webinare_initialized'])) {
    $GLOBALS['dgptm_vimeo_webinare_initialized'] = true;
    DGPTM_Vimeo_Webinare::get_instance();
}
