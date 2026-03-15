<?php
/**
 * Plugin Name: DGPTM - Mitglieder Dashboard
 * Description: Modernes Mitglieder-Dashboard mit Tab-Navigation
 * Version: 3.1.0
 */
if (!defined('ABSPATH')) exit;

define('DGPTM_DASHBOARD_VERSION', '3.1.0');
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

            // First non-link tab is active
            $active = '';
            foreach ($top as $t) {
                if (empty($t['link'])) { $active = $t['id']; break; }
            }
            $html = '<div class="dgptm-dash" data-active="' . esc_attr($active) . '">';

            // Main nav
            $html .= '<nav class="dgptm-nav">';
            foreach ($top as $t) {
                if (!empty($t['link'])) {
                    // Direct link tab - opens URL, no panel
                    $target = (strpos($t['link'], home_url()) === 0) ? '' : ' target="_blank" rel="noopener"';
                    $html .= '<a href="' . esc_url($t['link']) . '" class="dgptm-nav-item dgptm-nav-link"' . $target . '>'
                        . esc_html($t['label']) . ' <span class="dgptm-link-icon">↗</span></a>';
                } else {
                    $cls = $t['id'] === $active ? ' dgptm-nav-active' : '';
                    $html .= '<a href="#" class="dgptm-nav-item' . $cls . '" data-tab="' . esc_attr($t['id']) . '">' . esc_html($t['label']) . '</a>';
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
                    // Folder tabs: parent + children
                    $folder = array_merge([$t], $kids);
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
                        if ($fc['first'] && $is_active) {
                            $html .= do_shortcode($f['content']);
                        } else {
                            $html .= '<div class="dgptm-loading">Wird geladen...</div>';
                        }
                        $html .= '</div>';
                    }
                    $html .= '</div>';
                } else {
                    // Simple tab
                    if ($is_active) {
                        $html .= do_shortcode($t['content']);
                    } else {
                        $html .= '<div class="dgptm-loading">Wird geladen...</div>';
                    }
                }

                $html .= '</div>';
            }

            $html .= '</div>';
            return $html;
        }

        // ─── AJAX: Load tab content ───

        public function ajax_load_tab() {
            check_ajax_referer('dgptm_dash', 'nonce');
            $tab_id = sanitize_key($_POST['tab'] ?? '');
            $tabs = DGPTM_Dashboard_Tabs::get_instance();
            $tab = $tabs->get_tab($tab_id);

            if (!$tab) wp_send_json_error('Tab nicht gefunden');

            wp_send_json_success(['html' => do_shortcode($tab['content'])]);
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
