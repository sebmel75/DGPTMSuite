<?php
/**
 * Plugin Name: DGPTM - Mitglieder Dashboard
 * Description: Modernes Mitglieder-Dashboard mit Tab-Navigation
 * Version: 3.1.0
 */
if (!defined('ABSPATH')) exit;

define('DGPTM_DASHBOARD_VERSION', '3.3.1');
define('DGPTM_DASHBOARD_PATH', plugin_dir_path(__FILE__));
define('DGPTM_DASHBOARD_URL', plugin_dir_url(__FILE__));

if (!class_exists('DGPTM_Mitglieder_Dashboard')) {

    class DGPTM_Mitglieder_Dashboard {
        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) self::$instance = new self();
            return self::$instance;
        }

        private function __construct() {
            require_once DGPTM_DASHBOARD_PATH . 'includes/class-tabs.php';

            add_action('init', [$this, 'register_shortcodes']);
            add_action('admin_menu', [$this, 'admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
            add_action('wp_ajax_dgptm_dash_save', [$this, 'ajax_save']);
            add_action('wp_ajax_dgptm_dash_save_settings', [$this, 'ajax_save_settings']);
            add_action('wp_ajax_dgptm_dash_load_tab', [$this, 'ajax_load_tab']);

            // Nicht-eingeloggte User: klare Fehlermeldung statt "0"
            add_action('wp_ajax_nopriv_dgptm_dash_load_tab', [$this, 'ajax_load_tab_nopriv']);

            // WP Rocket: Dashboard-Seite nie cachen (Nonce ist user-spezifisch)
            add_filter('rocket_cache_reject_uri', [$this, 'exclude_from_cache']);

            // ACF-Felder fuer Permission-Auswahl im Tab-Editor laden
            add_action('wp_ajax_dgptm_dash_get_acf_fields', [$this, 'ajax_get_acf_fields']);
        }

        /**
         * Dashboard-Seite von WP Rocket Caching ausschliessen
         */
        public function exclude_from_cache($uris) {
            $uris[] = '/mitgliedschaft/interner-bereich/(.*)';
            $uris[] = '/interner-bereich/(.*)';
            return $uris;
        }

        /**
         * AJAX: Verfuegbare ACF User-Felder (True/False) fuer Permission-Dropdown
         */
        public function ajax_get_acf_fields() {
            check_ajax_referer('dgptm_dash_admin', 'nonce');
            if (!current_user_can('manage_options')) wp_send_json_error('Keine Berechtigung');

            $fields = [];

            if (function_exists('acf_get_field_groups')) {
                $groups = acf_get_field_groups(['user_form' => 'all']);
                foreach ($groups as $group) {
                    $group_fields = acf_get_fields($group['key']);
                    if (!is_array($group_fields)) continue;
                    foreach ($group_fields as $f) {
                        if ($f['type'] === 'true_false') {
                            $fields[] = [
                                'name' => $f['name'],
                                'label' => $f['label'],
                                'group' => $group['title'],
                            ];
                        }
                    }
                }
            }

            wp_send_json_success(['fields' => $fields]);
        }

        public function register_shortcodes() {
            add_shortcode('dgptm_dashboard', [$this, 'render_dashboard']);
            add_shortcode('dgptm_dashboard_debug', [$this, 'render_debug']);
        }

        // ─── FRONTEND ───

        public function render_dashboard($atts) {
            if (!is_user_logged_in()) {
                return '<p>Bitte melden Sie sich an.</p>';
            }

            wp_enqueue_style('dgptm-dash', DGPTM_DASHBOARD_URL . 'assets/css/dashboard.css', [], DGPTM_DASHBOARD_VERSION);
            wp_enqueue_script('dgptm-dash', DGPTM_DASHBOARD_URL . 'assets/js/dashboard.js', ['jquery'], DGPTM_DASHBOARD_VERSION, true);
            wp_localize_script('dgptm-dash', 'dgptmDash', [
                'ajax' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dgptm_dash'),
            ]);

            $tabs = DGPTM_Dashboard_Tabs::get_instance();
            $user_id = get_current_user_id();
            $all = $tabs->get_visible_tabs($user_id);

            if (empty($all)) return '<p>Keine Inhalte.</p>';

            // Split into top-level and children
            $top = [];
            $children = [];
            foreach ($all as $t) {
                if (empty($t['parent'])) $top[] = $t;
                else $children[$t['parent']][] = $t;
            }

            // Filter: hide top-level tabs that have no content, no link, and no children
            $top = array_values(array_filter($top, function($t) use ($children) {
                if (!empty($t['link'])) return true;           // Has link
                if (!empty(trim($t['content'] ?? ''))) return true; // Has content
                if (!empty($children[$t['id']])) return true;  // Has children
                return false;
            }));

            // Determine active tab
            $active = '';
            // Check URL for tab hint (e.g. survey_action=edit activates umfragen tab)
            if (!empty($_GET['survey_action'])) {
                foreach ($top as $t) {
                    if (strpos($t['content'] ?? '', 'dgptm_umfrage_editor') !== false) {
                        $active = $t['id']; break;
                    }
                }
            }
            // Default: first non-link tab
            if (!$active) {
                foreach ($top as $t) {
                    if (empty($t['link'])) { $active = $t['id']; break; }
                }
            }
            $html = '<div class="dgptm-dash" data-active="' . esc_attr($active) . '">';

            // Mobile: Dropdown-Navigation
            $html .= '<div class="dgptm-nav-mobile">';
            $html .= '<select id="dgptm-nav-select" class="dgptm-nav-select">';
            foreach ($top as $t) {
                if (!empty($t['link'])) continue;
                $sel = $t['id'] === $active ? ' selected' : '';
                $html .= '<option value="' . esc_attr($t['id']) . '"' . $sel . '>Menü: ' . esc_html($t['label']) . '</option>';
            }
            $html .= '</select>';
            $html .= '</div>';

            // Desktop: Tab-Navigation
            $html .= '<nav class="dgptm-nav">';
            foreach ($top as $t) {
                $vis = $t['visibility'] ?? 'all';
                $vis_cls = $vis !== 'all' ? ' dgptm-vis-' . $vis : '';
                if (!empty($t['link'])) {
                    $target = (strpos($t['link'], home_url()) === 0) ? '' : ' target="_blank" rel="noopener"';
                    $html .= '<a href="' . esc_url($t['link']) . '" class="dgptm-nav-item dgptm-nav-link' . $vis_cls . '"' . $target . '>'
                        . esc_html($t['label']) . ' <span class="dgptm-link-icon">↗</span></a>';
                } else {
                    $cls = $t['id'] === $active ? ' dgptm-nav-active' : '';
                    $html .= '<a href="#" class="dgptm-nav-item' . $cls . $vis_cls . '" data-tab="' . esc_attr($t['id']) . '">' . esc_html($t['label']) . '</a>';
                }
            }
            $html .= '</nav>';

            // Panels (skip link tabs)
            foreach ($top as $t) {
                if (!empty($t['link'])) continue; // Link tabs have no panel
                $is_active = $t['id'] === $active;
                $html .= '<div class="dgptm-panel' . ($is_active ? ' dgptm-panel-active' : '') . '" data-panel="' . esc_attr($t['id']) . '"' . ($is_active ? '' : ' style="display:none"') . '>';

                $kids = $children[$t['id']] ?? [];
                if (!empty($kids)) {
                    // Folder tabs: include parent only if it has own content
                    $parent_has_content = !empty(trim($t['content'] ?? ''));
                    $folder = $parent_has_content ? array_merge([$t], $kids) : $kids;
                    $html .= '<div class="dgptm-folder">';
                    // Filter out link tabs for panel rendering, keep for nav
                    $folder_content = [];
                    $html .= '<div class="dgptm-folder-nav">';
                    $first_content = true;
                    foreach ($folder as $f) {
                        if (!empty($f['link'])) {
                            $target = (strpos($f['link'], home_url()) === 0) ? '' : ' target="_blank" rel="noopener"';
                            $html .= '<a href="' . esc_url($f['link']) . '" class="dgptm-ftab dgptm-ftab-link"' . $target . '>'
                                . esc_html($f['label']) . ' <span class="dgptm-link-icon">↗</span></a>';
                        } else {
                            $cls = $first_content ? ' dgptm-ftab-active' : '';
                            $html .= '<a href="#" class="dgptm-ftab' . $cls . '" data-ftab="' . esc_attr($f['id']) . '">' . esc_html($f['label']) . '</a>';
                            $folder_content[] = ['tab' => $f, 'first' => $first_content];
                            $first_content = false;
                        }
                    }
                    $html .= '</div>';
                    foreach ($folder_content as $fc) {
                        $f = $fc['tab'];
                        $html .= '<div class="dgptm-fpanel' . ($fc['first'] ? ' dgptm-fpanel-active' : '') . '" data-fpanel="' . esc_attr($f['id']) . '"' . ($fc['first'] ? '' : ' style="display:none"') . '>';
                        if ($fc['first']) {
                            $html .= self::render_content_with_mobile($f);
                        } else {
                            $html .= '<div class="dgptm-loading">Wird geladen...</div>';
                        }
                        $html .= '</div>';
                    }
                    $html .= '</div>';
                } else {
                    // Simple tab
                    if ($is_active) {
                        $html .= self::render_content_with_mobile($t);
                    } else {
                        $html .= '<div class="dgptm-loading">Wird geladen...</div>';
                    }
                }

                $html .= '</div>';
            }

            $html .= '</div>';
            return $html;
        }

        /**
         * Rendert Content mit optionalem Mobile-Alternativinhalt.
         * Desktop-Content in .dgptm-content-desktop, Mobile in .dgptm-content-mobile.
         * Falls kein Mobile-Content: nur normaler Content ohne Wrapper.
         */
        public static function render_content_with_mobile($tab) {
            $content = $tab['content'] ?? '';
            $mobile  = $tab['content_mobile'] ?? '';

            if (empty(trim($mobile))) {
                // Kein mobiler Inhalt → normaler Content für alle Geräte
                return do_shortcode($content);
            }

            // Desktop + Mobile Content in separaten Containern
            return '<div class="dgptm-content-desktop">' . do_shortcode($content) . '</div>'
                 . '<div class="dgptm-content-mobile">' . do_shortcode($mobile) . '</div>';
        }

        // ─── AJAX: Load tab content ───

        public function ajax_load_tab_nopriv() {
            wp_send_json_error('Ihre Sitzung ist abgelaufen. Bitte melden Sie sich erneut an.');
        }

        public function ajax_load_tab() {
            // PHP-Warnings abfangen damit JSON nicht kaputt geht
            ob_start();

            if ( ! wp_verify_nonce( $_POST['nonce'] ?? '', 'dgptm_dash' ) ) {
                ob_end_clean();
                wp_send_json_error('Sitzung abgelaufen. Bitte Seite neu laden (Strg+R).');
            }
            $tab_id = sanitize_key($_POST['tab'] ?? '');
            $tabs_mgr = DGPTM_Dashboard_Tabs::get_instance();
            $tab = $tabs_mgr->get_tab($tab_id);

            if (!$tab) {
                ob_end_clean();
                wp_send_json_error('Tab nicht gefunden: ' . $tab_id);
            }

            $user_id = get_current_user_id();
            $all_visible = $tabs_mgr->get_visible_tabs($user_id);
            $kids = [];
            foreach ($all_visible as $t) {
                if (($t['parent'] ?? '') === $tab_id) $kids[] = $t;
            }

            // PHP-Warnings die bis hierher aufgelaufen sind verwerfen
            $php_warnings = ob_get_clean();

            try {
                if (!empty($kids)) {
                    $parent_has_content = !empty(trim($tab['content'] ?? ''));
                    $folder = $parent_has_content ? array_merge([$tab], $kids) : $kids;
                    $html = '<div class="dgptm-folder"><div class="dgptm-folder-nav">';
                    $folder_content = [];
                    $first_content = true;
                    foreach ($folder as $f) {
                        if (!empty($f['link'])) {
                            $target = (strpos($f['link'], home_url()) === 0) ? '' : ' target="_blank" rel="noopener"';
                            $html .= '<a href="' . esc_url($f['link']) . '" class="dgptm-ftab dgptm-ftab-link"' . $target . '>'
                                . esc_html($f['label']) . ' <span class="dgptm-link-icon">↗</span></a>';
                        } else {
                            $cls = $first_content ? ' dgptm-ftab-active' : '';
                            $html .= '<a href="#" class="dgptm-ftab' . $cls . '" data-ftab="' . esc_attr($f['id']) . '">' . esc_html($f['label']) . '</a>';
                            $folder_content[] = ['tab' => $f, 'first' => $first_content];
                            $first_content = false;
                        }
                    }
                    $html .= '</div>';
                    foreach ($folder_content as $fc) {
                        $f = $fc['tab'];
                        $html .= '<div class="dgptm-fpanel' . ($fc['first'] ? ' dgptm-fpanel-active' : '') . '" data-fpanel="' . esc_attr($f['id']) . '"' . ($fc['first'] ? '' : ' style="display:none"') . '>';
                        if ($fc['first']) {
                            $html .= self::render_content_with_mobile($f);
                        } else {
                            $html .= '<div class="dgptm-loading">Wird geladen...</div>';
                        }
                        $html .= '</div>';
                    }
                    $html .= '</div>';
                    wp_send_json_success(['html' => $html]);
                }

                // Simple tab
                wp_send_json_success(['html' => self::render_content_with_mobile($tab)]);

            } catch (\Throwable $e) {
                wp_send_json_error('Fehler im Tab "' . $tab_id . '": ' . $e->getMessage());
            }
        }

        // ─── ADMIN ───

        public function admin_menu() {
            add_submenu_page('dgptm-suite', 'Dashboard Config', 'Dashboard Config', 'manage_options', 'dgptm-dash-config', [$this, 'admin_page']);
        }

        public function admin_assets($hook) {
            if (strpos($hook, 'dgptm-dash-config') === false) return;
            wp_enqueue_style('dgptm-dash-admin', DGPTM_DASHBOARD_URL . 'assets/css/admin.css', [], DGPTM_DASHBOARD_VERSION);
            wp_enqueue_script('dgptm-dash-admin', DGPTM_DASHBOARD_URL . 'assets/js/admin.js', ['jquery'], DGPTM_DASHBOARD_VERSION, true);
            wp_localize_script('dgptm-dash-admin', 'dgptmDashAdmin', [
                'ajax' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dgptm_dash_admin'),
            ]);
        }

        public function admin_page() {
            if (!current_user_can('manage_options')) wp_die('Nope');
            $tabs = DGPTM_Dashboard_Tabs::get_instance();
            include DGPTM_DASHBOARD_PATH . 'includes/admin-page.php';
        }

        public function ajax_save_settings() {
            check_ajax_referer('dgptm_dash_admin', 'nonce');
            if (!current_user_can('manage_options')) wp_send_json_error('Keine Berechtigung');

            $tabs = DGPTM_Dashboard_Tabs::get_instance();
            $tabs->save_settings([
                'admin_bypass' => ($_POST['admin_bypass'] ?? '1') === '1',
            ]);

            wp_send_json_success('Einstellungen gespeichert');
        }

        public function ajax_save() {
            check_ajax_referer('dgptm_dash_admin', 'nonce');
            if (!current_user_can('manage_options')) wp_send_json_error('Keine Berechtigung');

            $raw = wp_unslash($_POST['tabs'] ?? '');

            // Reset
            if ($raw === '__RESET__') {
                $tabs = DGPTM_Dashboard_Tabs::get_instance();
                $tabs->reset();
                wp_send_json_success('Auf Standard zurueckgesetzt');
            }

            $data = json_decode($raw, true);
            if (!is_array($data)) wp_send_json_error('Ungueltige Daten: ' . json_last_error_msg());

            $tabs = DGPTM_Dashboard_Tabs::get_instance();
            $tabs->save($data);

            wp_send_json_success('Gespeichert (' . count($data) . ' Tabs)');
        }

        // ─── DEBUG ───

        public function render_debug() {
            if (!current_user_can('manage_options')) return '';
            $tabs = DGPTM_Dashboard_Tabs::get_instance();
            $all = $tabs->get_all();
            $out = '<pre style="background:#f5f5f5;padding:12px;font-size:11px;border:1px solid #ddd;overflow:auto;">';
            $out .= "Dashboard v" . DGPTM_DASHBOARD_VERSION . " | Tabs: " . count($all) . "\n\n";
            foreach ($all as $t) {
                $out .= sprintf("%-25s active=%-3s parent=%-15s content=%d chars\n",
                    $t['id'], $t['active'] ? 'Y' : 'N', $t['parent'] ?: '-', strlen($t['content']));
            }
            $out .= '</pre>';
            return $out;
        }
    }
}

if (!isset($GLOBALS['dgptm_dash_init'])) {
    $GLOBALS['dgptm_dash_init'] = true;
    DGPTM_Mitglieder_Dashboard::get_instance();
}
