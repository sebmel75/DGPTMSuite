<?php
/**
 * Plugin Name: DGPTM Frontend Seiteneditor
 * Description: Ermöglicht Benutzern die Bearbeitung ausgewählter Seiten mit Elementor oder WordPress Editor
 * Version: 4.1.0
 * Author: Sebastian Melzer
 * Text Domain: dgptm-fpe
 *
 * CHANGELOG v4.1.0 (CRITICAL SECURITY FIX):
 * - Entfernte allgemeine edit_pages und edit_published_pages Capabilities
 * - Implementierte Backend-Zugriffsbeschränkungen (restrict_backend_access)
 * - Verstecke Admin-Menüs für eingeschränkte User
 * - Blockiere Navigation zu anderen Admin-Seiten
 * - User können jetzt NUR ihre zugewiesene Seite bearbeiten, kein voller Backend-Zugriff mehr
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Frontend Page Editor - Komplett überarbeitete Version
 *
 * Ermöglicht Usern ohne Admin-Rechte die Bearbeitung zugewiesener Seiten
 * via Elementor oder WordPress Editor
 */
class DGPTM_Frontend_Page_Editor {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
	
	
	public function enqueue_frontend_styles() {
    // Annahme: Die CSS-Datei heißt 'frontend.css' und liegt im selben Ordner
    wp_enqueue_style(
        'dgptm-fpe-style', // Einzigartiger Name für dein Stylesheet
        plugin_dir_url(__FILE__) . 'css/frontend-page-editor.css', // Der Pfad zur CSS-Datei
        [], // Keine Abhängigkeiten
        '4.1.0' // Versionsnummer, gut für Cache-Aktualisierungen
    );
}

    private function __construct() {
		  add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_styles']);
		
        // Shortcodes
        add_shortcode('frontend_page_editor', [$this, 'render_page_list']);
		
        add_shortcode('has_page_edit_access', [$this, 'shortcode_has_page_access']);

        // Admin: User-Meta Verwaltung
        if (is_admin()) {
            add_action('show_user_profile', [$this, 'display_page_selector'], 10);
            add_action('edit_user_profile', [$this, 'display_page_selector'], 10);
            add_action('personal_options_update', [$this, 'save_page_assignment'], 10);
            add_action('edit_user_profile_update', [$this, 'save_page_assignment'], 10);

            // SECURITY: Admin-Bereich Restrictions
            add_action('admin_init', [$this, 'restrict_backend_access'], 1);
            add_action('admin_menu', [$this, 'hide_admin_menus'], 999);
            add_action('admin_head', [$this, 'hide_admin_bar_items'], 999);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_security_script'], 999);
        }

        // Redirect Handler
        add_action('template_redirect', [$this, 'handle_edit_redirect'], 1);

        // Capability Granting
        add_filter('user_has_cap', [$this, 'grant_editing_capabilities'], 999, 4);
        add_filter('map_meta_cap', [$this, 'map_page_capabilities'], 10, 4);
    }

    /**
     * Hole zugewiesene Seiten für einen User
     */
    private function get_assigned_pages($user_id) {
        $pages = [];

        // 1. Natives Meta prüfen
        $meta_pages = get_user_meta($user_id, 'dgptm_editable_pages', true);
        if (!empty($meta_pages) && is_array($meta_pages)) {
            $pages = array_map('intval', $meta_pages);
        }

        // 2. ACF als Fallback (falls vorhanden und natives Meta leer)
        if (empty($pages) && function_exists('get_field')) {
            $acf_pages = get_field('editable_pages', 'user_' . $user_id);
            if (!empty($acf_pages) && is_array($acf_pages)) {
                $pages = array_map('intval', $acf_pages);
            }
        }

        return $pages;
    }

    /**
     * Prüfe ob User eine bestimmte Seite bearbeiten darf
     */
    public function user_can_edit_page($user_id, $page_id) {
        $user = get_userdata($user_id);
        if (!$user) return false;

        // Admins und Editoren haben immer Zugriff
        if (in_array('administrator', $user->roles) || in_array('editor', $user->roles)) {
            return true;
        }

        $assigned = $this->get_assigned_pages($user_id);
        return in_array((int)$page_id, $assigned, true);
    }

    /**
     * Prüfe ob User mindestens eine Seite bearbeiten darf
     */
    public function user_has_page_access($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        if (!$user_id) {
            return false;
        }

        $user = get_userdata($user_id);
        if (!$user) return false;

        // Admins und Editoren haben immer Zugriff
        if (in_array('administrator', $user->roles) || in_array('editor', $user->roles)) {
            return true;
        }

        $assigned = $this->get_assigned_pages($user_id);
        return !empty($assigned);
    }

    /**
     * Shortcode: [has_page_edit_access]
     * Gibt 1 zurück wenn User Zugriff hat, sonst 0
     */
    public function shortcode_has_page_access($atts) {
        if (!is_user_logged_in()) {
            return '0';
        }

        $user_id = get_current_user_id();
        return $this->user_has_page_access($user_id) ? '1' : '0';
    }

    /**
     * User-Profil: Zeige Seiten-Auswahl
     */
    public function display_page_selector($user) {
        // Nur Admins/Editoren können Seiten zuweisen
        if (!current_user_can('edit_users')) {
            return;
        }

        // Hole alle Seiten
        $all_pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ]);

        // Hole aktuell zugewiesene Seiten
        $assigned_pages = $this->get_assigned_pages($user->ID);

        ?>
        <h3>Frontend Seiteneditor - Zugewiesene Seiten</h3>
        <table class="form-table">
            <tr>
                <th><label for="dgptm_editable_pages">Bearbeitbare Seiten</label></th>
                <td>
                    <select name="dgptm_editable_pages[]" id="dgptm_editable_pages" multiple style="width: 100%; min-height: 200px;">
                        <?php foreach ($all_pages as $page): ?>
                            <option value="<?php echo esc_attr($page->ID); ?>"
                                    <?php selected(in_array($page->ID, $assigned_pages)); ?>>
                                <?php echo esc_html($page->post_title); ?>
                                <?php
                                // Zeige Editor-Type
                                $is_elementor = get_post_meta($page->ID, '_elementor_edit_mode', true) === 'builder';
                                echo $is_elementor ? ' [Elementor]' : ' [WP Editor]';
                                ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">
                        Halten Sie STRG (Windows) oder CMD (Mac) gedrückt, um mehrere Seiten auszuwählen.<br>
                        Der Benutzer kann diese Seiten im Frontend mit dem jeweiligen Editor bearbeiten.
                    </p>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Speichere Seitenzuordnung
     */
    public function save_page_assignment($user_id) {
        // Sicherheitsprüfung
        if (!current_user_can('edit_user', $user_id)) {
            return;
        }

        // Speichere Seitenzuordnung
        if (isset($_POST['dgptm_editable_pages']) && is_array($_POST['dgptm_editable_pages'])) {
            $pages = array_map('intval', $_POST['dgptm_editable_pages']);
            update_user_meta($user_id, 'dgptm_editable_pages', $pages);
        } else {
            // Keine Seiten ausgewählt - lösche Meta
            delete_user_meta($user_id, 'dgptm_editable_pages');
        }
    }

    /**
     * Redirect Handler für Frontend Edit-Requests
     */
    public function handle_edit_redirect() {
        if (!isset($_GET['dgptm_edit_page'])) {
            return;
        }

        $page_id = intval($_GET['dgptm_edit_page']);
        $nonce = isset($_GET['nonce']) ? $_GET['nonce'] : '';

        // Nonce prüfen
        if (!wp_verify_nonce($nonce, 'dgptm_edit_' . $page_id)) {
            wp_die('Ungültige Anfrage', 'Fehler', ['back_link' => true]);
        }

        // Login prüfen
        if (!is_user_logged_in()) {
            auth_redirect();
        }

        $user_id = get_current_user_id();

        // Berechtigung prüfen
        if (!$this->user_can_edit_page($user_id, $page_id)) {
            wp_die(
                'Sie haben keine Berechtigung, diese Seite zu bearbeiten.',
                'Keine Berechtigung',
                ['back_link' => true]
            );
        }

        // Session setzen (1 Stunde gültig)
        set_transient('dgptm_editing_' . $user_id, $page_id, 3600);

        // Prüfe ob Elementor-Seite
        $is_elementor = get_post_meta($page_id, '_elementor_edit_mode', true) === 'builder';

        if ($is_elementor && defined('ELEMENTOR_VERSION')) {
            // Elementor Editor
            $edit_url = add_query_arg([
                'post' => $page_id,
                'action' => 'elementor'
            ], admin_url('post.php'));
        } else {
            // WordPress Standard Editor
            $edit_url = add_query_arg([
                'post' => $page_id,
                'action' => 'edit'
            ], admin_url('post.php'));
        }

        wp_redirect($edit_url);
        exit;
    }

    /**
     * Gebe NUR spezifische Editing-Capabilities (SECURITY FIX)
     *
     * WICHTIG: Wir geben hier KEINE allgemeinen Capabilities wie 'edit_pages' mehr,
     * da diese dem User Zugriff auf alle Seiten im Backend geben würden.
     * Stattdessen arbeiten wir nur mit map_meta_cap für spezifische Posts.
     */
    public function grant_editing_capabilities($allcaps, $caps, $args, $user) {
        // Nur im Admin-Bereich
        if (!is_admin()) {
            return $allcaps;
        }

        // Admin hat schon alle Rechte
        if (!empty($allcaps['manage_options'])) {
            return $allcaps;
        }

        if (!$user || !$user->ID) {
            return $allcaps;
        }

        // Prüfe ob User in einer Edit-Session ist
        $editing_page = get_transient('dgptm_editing_' . $user->ID);
        if (!$editing_page) {
            return $allcaps;
        }

        // Berechtigung prüfen
        if (!$this->user_can_edit_page($user->ID, $editing_page)) {
            return $allcaps;
        }

        // SECURITY FIX: Gebe nur minimal notwendige Capabilities
        // KEINE allgemeinen edit_pages/edit_published_pages mehr!
        $allcaps['upload_files'] = true;  // Für Medien-Upload
        $allcaps['read'] = true;  // Basis-Zugriff

        // Elementor-spezifisch (nur wenn Elementor-Editor geöffnet)
        if (isset($_GET['action']) && $_GET['action'] === 'elementor') {
            $allcaps['elementor'] = true;
            $allcaps['edit_with_elementor'] = true;
        }

        return $allcaps;
    }

    /**
     * Mappe Post-spezifische Capabilities
     */
    public function map_page_capabilities($caps, $cap, $user_id, $args) {
        // Prüfe nur post-spezifische Capabilities
        if (!in_array($cap, ['edit_post', 'edit_page', 'delete_post', 'delete_page'])) {
            return $caps;
        }

        // Brauchen eine Post-ID
        if (empty($args[0])) {
            return $caps;
        }

        $post_id = $args[0];

        // Prüfe ob User diese Seite bearbeiten darf
        if (!$this->user_can_edit_page($user_id, $post_id)) {
            return $caps;
        }

        // Prüfe aktive Session
        $editing_page = get_transient('dgptm_editing_' . $user_id);
        if ($editing_page != $post_id) {
            return $caps;
        }

        // Erlaube Zugriff auf diese spezifische Seite
        // Setze nur 'exist' als Requirement (das hat jeder User)
        return ['exist'];
    }

    /**
     * SECURITY: Blockiere Zugriff auf Admin-Bereiche außer zugewiesener Seite
     */
    public function restrict_backend_access() {
        // Admins und Editoren nicht einschränken
        if (current_user_can('manage_options') || current_user_can('edit_others_pages')) {
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        // Prüfe ob User eine Edit-Session hat
        $editing_page = get_transient('dgptm_editing_' . $user_id);
        if (!$editing_page) {
            // Keine Session - User sollte nicht im Admin sein
            $assigned_pages = $this->get_assigned_pages($user_id);
            if (!empty($assigned_pages)) {
                // User hat zugewiesene Seiten, aber keine aktive Session
                wp_die(
                    'Bitte nutzen Sie den Frontend-Editor-Link, um Ihre Seiten zu bearbeiten.',
                    'Zugriff verweigert',
                    ['back_link' => true]
                );
            }
            return;
        }

        // User hat Session - prüfe ob er auf der richtigen Seite ist
        $current_page = 0;
        if (isset($_GET['post'])) {
            $current_page = intval($_GET['post']);
        } elseif (isset($_POST['post_ID'])) {
            $current_page = intval($_POST['post_ID']);
        }

        // AJAX-Requests für Elementor/WP Editor erlauben
        if (wp_doing_ajax()) {
            // Prüfe ob es ein erlaubter AJAX-Request ist
            $allowed_actions = [
                'elementor_ajax',
                'heartbeat',
                'upload-attachment',
                'query-attachments',
                'save-attachment',
                'save-attachment-compat',
                'editpost',
                'inline-save',
                'wp_link_ajax',
                'autocomplete-user'
            ];

            $action = isset($_REQUEST['action']) ? $_REQUEST['action'] : '';
            if (in_array($action, $allowed_actions)) {
                return;
            }
        }

        // Erlaube nur post.php mit der richtigen Seite
        global $pagenow;
        $allowed_pages = ['post.php', 'admin-ajax.php', 'async-upload.php', 'media-upload.php'];

        if (!in_array($pagenow, $allowed_pages)) {
            // Redirect zu zugewiesener Seite
            $is_elementor = get_post_meta($editing_page, '_elementor_edit_mode', true) === 'builder';

            if ($is_elementor && defined('ELEMENTOR_VERSION')) {
                $edit_url = add_query_arg([
                    'post' => $editing_page,
                    'action' => 'elementor'
                ], admin_url('post.php'));
            } else {
                $edit_url = add_query_arg([
                    'post' => $editing_page,
                    'action' => 'edit'
                ], admin_url('post.php'));
            }

            wp_redirect($edit_url);
            exit;
        }

        // Wenn auf post.php, prüfe dass es die richtige Seite ist
        if ($pagenow === 'post.php' && $current_page && $current_page != $editing_page) {
            wp_die(
                'Sie haben keine Berechtigung, diese Seite zu bearbeiten. Sie können nur Ihre zugewiesene Seite bearbeiten.',
                'Zugriff verweigert',
                ['back_link' => true]
            );
        }
    }

    /**
     * SECURITY: Verstecke Admin-Menüs für eingeschränkte User
     */
    public function hide_admin_menus() {
        // Admins und Editoren nicht einschränken
        if (current_user_can('manage_options') || current_user_can('edit_others_pages')) {
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        // Prüfe ob User eine Edit-Session hat
        $editing_page = get_transient('dgptm_editing_' . $user_id);
        if (!$editing_page) {
            return;
        }

        // Entferne ALLE Admin-Menüs
        global $menu, $submenu;
        $menu = [];
        $submenu = [];
    }

    /**
     * SECURITY: Verstecke Admin Bar Items
     */
    public function hide_admin_bar_items() {
        // Admins und Editoren nicht einschränken
        if (current_user_can('manage_options') || current_user_can('edit_others_pages')) {
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        // Prüfe ob User eine Edit-Session hat
        $editing_page = get_transient('dgptm_editing_' . $user_id);
        if (!$editing_page) {
            return;
        }

        // CSS zum Verstecken von Admin Bar Items
        echo '<style>
            #wpadminbar .ab-top-menu > li:not(#wp-admin-bar-my-account) { display: none !important; }
            #adminmenu, #adminmenuwrap { display: none !important; }
            #wpcontent, #wpfooter { margin-left: 0 !important; }
        </style>';
    }

    /**
     * SECURITY: JavaScript zum Blockieren von Navigation
     */
    public function enqueue_security_script() {
        // Admins und Editoren nicht einschränken
        if (current_user_can('manage_options') || current_user_can('edit_others_pages')) {
            return;
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            return;
        }

        // Prüfe ob User eine Edit-Session hat
        $editing_page = get_transient('dgptm_editing_' . $user_id);
        if (!$editing_page) {
            return;
        }

        // Inline JavaScript zum Blockieren von Navigation
        ?>
        <script>
        jQuery(document).ready(function($) {
            // Blockiere Klicks auf Admin-Links (außer Post-Editor)
            $('a').on('click', function(e) {
                var $link = $(this);

                // WICHTIG: Elementor Panel und UI-Elemente NICHT blockieren
                if ($link.closest('#elementor-panel, .elementor-panel, #elementor-navigator, .elementor-element, .elementor-editor-active, .e-route-panel').length > 0) {
                    return; // Elementor UI-Elemente durchlassen
                }

                // Links ohne href oder mit # durchlassen (oft JavaScript-Navigation)
                var href = $link.attr('href');
                if (!href || href === '#' || href.indexOf('#') === 0) {
                    return;
                }

                // Erlaube nur bestimmte URLs
                var allowed = [
                    'post.php',
                    'admin-ajax.php',
                    'async-upload.php',
                    'media-upload.php',
                    'elementor'
                ];

                var isAllowed = false;
                for (var i = 0; i < allowed.length; i++) {
                    if (href.indexOf(allowed[i]) !== -1) {
                        isAllowed = true;
                        break;
                    }
                }

                // Externe Links und Frontend-Links erlauben
                if (href.indexOf('wp-admin') === -1 || href.indexOf('<?php echo home_url(); ?>') !== -1) {
                    isAllowed = true;
                }

                if (!isAllowed) {
                    e.preventDefault();
                    alert('Navigation im Admin-Bereich ist eingeschränkt. Sie können nur Ihre zugewiesene Seite bearbeiten.');
                    return false;
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Hole bearbeitbare Seiten für aktuellen User
     */
    public function get_editable_pages_for_user($user_id) {
        $result = [];
        $user = get_userdata($user_id);
        if (!$user) return $result;

        // Admin/Editor sehen alle Seiten
        if (in_array('administrator', $user->roles) || in_array('editor', $user->roles)) {
            $pages = get_posts([
                'post_type' => 'page',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC'
            ]);
        } else {
            // Normale User: nur zugewiesene Seiten
            $assigned_ids = $this->get_assigned_pages($user_id);
            if (empty($assigned_ids)) return $result;

            $pages = get_posts([
                'post_type' => 'page',
                'post_status' => 'publish',
                'post__in' => $assigned_ids,
                'posts_per_page' => -1,
                'orderby' => 'title',
                'order' => 'ASC'
            ]);
        }

        foreach ($pages as $page) {
            $is_elementor = get_post_meta($page->ID, '_elementor_edit_mode', true) === 'builder';

            $result[] = [
                'id' => $page->ID,
                'title' => $page->post_title,
                'is_elementor' => $is_elementor,
                'edit_url' => add_query_arg([
                    'dgptm_edit_page' => $page->ID,
                    'nonce' => wp_create_nonce('dgptm_edit_' . $page->ID)
                ], home_url('/')),
                'view_url' => get_permalink($page->ID),
                'modified' => $page->post_modified
            ];
        }

        return $result;
    }

    /**
     * Shortcode: Zeige bearbeitbare Seiten im Frontend
     */
    public function render_page_list($atts) {
        // CSS nur einmal laden
        static $css_loaded = false;
      
        // Login-Check
        if (!is_user_logged_in()) {
            return '<div class="dgptm-fpe-notice"><p>Bitte melden Sie sich an, um Ihre Seiten zu bearbeiten.</p></div>';
        }

        $user_id = get_current_user_id();
        $pages = $this->get_editable_pages_for_user($user_id);

        if (empty($pages)) {
            return '<div class="dgptm-fpe-notice"><p>Ihnen wurden noch keine Seiten zur Bearbeitung zugewiesen.</p></div>';
        }

        ob_start();
        ?>
        <div class="dgptm-fpe-list">
            <h2>Meine bearbeitbaren Seiten</h2>
            <div class="dgptm-fpe-grid">
                <?php foreach ($pages as $page): ?>
                    <div class="dgptm-fpe-card">
                        <span class="dgptm-fpe-badge">
                            <?php echo $page['is_elementor'] ? 'Elementor' : 'WordPress Editor'; ?>
                        </span>
                        <h3><?php echo esc_html($page['title']); ?></h3>
                        <div>
                            <a href="<?php echo esc_url($page['edit_url']); ?>" class="dgptm-fpe-btn dgptm-fpe-btn-primary">
                                Bearbeiten
                            </a>
                            <a href="<?php echo esc_url($page['view_url']); ?>" class="dgptm-fpe-btn dgptm-fpe-btn-outline" target="_blank">
                                Ansehen
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
}

// Init
DGPTM_Frontend_Page_Editor::get_instance();
