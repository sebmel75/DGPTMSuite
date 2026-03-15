<?php
/**
 * Dashboard AJAX Handlers
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Dashboard_Ajax {

    private $config;
    private $permissions;
    private $renderer;
    private $crm_cache;

    public function __construct(
        DGPTM_Dashboard_Config $config,
        DGPTM_Dashboard_Permissions $permissions,
        DGPTM_Dashboard_Renderer $renderer,
        DGPTM_Dashboard_CRM_Cache $crm_cache
    ) {
        $this->config      = $config;
        $this->permissions = $permissions;
        $this->renderer    = $renderer;
        $this->crm_cache   = $crm_cache;

        add_action('wp_ajax_dgptm_dashboard_load_tab', [$this, 'ajax_load_tab']);
        add_action('wp_ajax_dgptm_dashboard_load_subtab', [$this, 'ajax_load_subtab']);
        add_action('wp_ajax_dgptm_dashboard_refresh_crm', [$this, 'ajax_refresh_crm']);
        add_action('wp_ajax_dgptm_dashboard_save_config', [$this, 'ajax_save_config']);
        add_action('wp_ajax_dgptm_dashboard_reorder_tabs', [$this, 'ajax_reorder_tabs']);
    }

    /**
     * Lazy-load a tab's content
     */
    public function ajax_load_tab() {
        check_ajax_referer('dgptm_dashboard_nonce', 'nonce');

        $tab_id  = sanitize_text_field($_POST['tab_id'] ?? '');
        $user_id = get_current_user_id();

        if (!$tab_id || !$user_id) {
            wp_send_json_error(['message' => 'Ungueltige Anfrage']);
        }

        $tab = $this->config->get_tab_by_id($tab_id);
        if (!$tab) {
            wp_send_json_error(['message' => 'Tab nicht gefunden']);
        }

        if (!$this->permissions->user_can_see_tab($user_id, $tab)) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        $html = $this->renderer->render_tab_content($tab_id, $user_id);

        wp_send_json_success([
            'html'   => $html,
            'tab_id' => $tab_id,
        ]);
    }

    /**
     * Lazy-load a sub-tab's content (generalized: any child tab)
     */
    public function ajax_load_subtab() {
        check_ajax_referer('dgptm_dashboard_nonce', 'nonce');

        $subtab_id = sanitize_text_field($_POST['subtab_id'] ?? '');
        $user_id   = get_current_user_id();

        if (!$subtab_id || !$user_id) {
            wp_send_json_error(['message' => 'Ungueltige Anfrage']);
        }

        // Load as a regular tab via render_single_tab
        $tab = $this->config->get_tab_by_id($subtab_id);
        if (!$tab) {
            wp_send_json_error(['message' => 'Sub-Tab nicht gefunden: ' . $subtab_id]);
        }

        if (!$this->permissions->user_can_see_tab($user_id, $tab)) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        $html = $this->renderer->render_single_tab($tab, $user_id);

        wp_send_json_success([
            'html'      => $html,
            'subtab_id' => $subtab_id,
        ]);
    }

    /**
     * Refresh CRM data cache
     */
    public function ajax_refresh_crm() {
        check_ajax_referer('dgptm_dashboard_nonce', 'nonce');

        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(['message' => 'Nicht eingeloggt']);
        }

        $data = $this->crm_cache->refresh($user_id);

        wp_send_json_success([
            'message' => 'Daten aktualisiert',
            'data'    => $data,
        ]);
    }

    /**
     * Save admin configuration (admin only)
     */
    public function ajax_save_config() {
        check_ajax_referer('dgptm_dashboard_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        $config_json = wp_unslash($_POST['config'] ?? '');
        $config = json_decode($config_json, true);

        if (json_last_error() !== JSON_ERROR_NONE || !is_array($config)) {
            if (function_exists('dgptm_log_error')) {
                dgptm_log_error('Dashboard config save: JSON decode failed. Error: ' . json_last_error_msg() . ' Raw length: ' . strlen($config_json), 'mitglieder-dashboard');
            }
            wp_send_json_error(['message' => 'Ungueltige Konfiguration: ' . json_last_error_msg()]);
        }

        // Reset to defaults
        if (!empty($config['_reset'])) {
            $this->config->reset_to_defaults();
            wp_send_json_success(['message' => 'Auf Standard zurueckgesetzt']);
        }

        // Sanitize tabs
        if (isset($config['tabs']) && is_array($config['tabs'])) {
            foreach ($config['tabs'] as &$tab) {
                $tab['id']               = sanitize_key($tab['id'] ?? '');
                $tab['label']            = sanitize_text_field($tab['label'] ?? '');
                $tab['icon']             = sanitize_text_field($tab['icon'] ?? 'dashicons-admin-page');
                $tab['parent_tab']       = sanitize_key($tab['parent_tab'] ?? '');
                $tab['permission_type']  = sanitize_key($tab['permission_type'] ?? 'always');
                $tab['permission_field'] = sanitize_key($tab['permission_field'] ?? '');
                $tab['active']           = !empty($tab['active']);
                $tab['order']            = absint($tab['order'] ?? 999);
                // Remove legacy template field
                unset($tab['template']);
                // Content HTML: admin-only, capability already verified
                $new_html = $tab['content_html'] ?? null;
                if ($new_html === null) {
                    $existing_tab = $this->config->get_tab_by_id($tab['id'] ?? '');
                    $tab['content_html'] = $existing_tab['content_html'] ?? '';
                } else {
                    $tab['content_html'] = $new_html;
                }

                if (isset($tab['permission_roles']) && is_array($tab['permission_roles'])) {
                    $tab['permission_roles'] = array_map('sanitize_key', $tab['permission_roles']);
                }

                $tab['datetime_start'] = sanitize_text_field($tab['datetime_start'] ?? '');
                $tab['datetime_end']   = sanitize_text_field($tab['datetime_end'] ?? '');
            }
            unset($tab);
        }

        // Sanitize settings
        if (isset($config['settings'])) {
            $config['settings']['cache_ttl']      = absint($config['settings']['cache_ttl'] ?? 900);
            $config['settings']['primary_color']   = sanitize_hex_color($config['settings']['primary_color'] ?? '#005792') ?: '#005792';
            $config['settings']['accent_color']    = sanitize_hex_color($config['settings']['accent_color'] ?? '#bd1622') ?: '#bd1622';
            $config['settings']['default_tab']     = sanitize_key($config['settings']['default_tab'] ?? 'profil');
            $config['settings']['mobile_dropdown'] = !empty($config['settings']['mobile_dropdown']);
        }

        $this->config->save_config($config);

        // Return saved tab count and profil content length for verification
        $profil_html_len = 0;
        foreach ($config['tabs'] as $t) {
            if ($t['id'] === 'profil') {
                $profil_html_len = strlen($t['content_html'] ?? '');
            }
        }

        wp_send_json_success([
            'message' => 'Konfiguration gespeichert (' . count($config['tabs']) . ' Tabs, Profil HTML: ' . $profil_html_len . ' Zeichen)',
        ]);
    }

    /**
     * Reorder tabs (admin only)
     */
    public function ajax_reorder_tabs() {
        check_ajax_referer('dgptm_dashboard_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Keine Berechtigung']);
        }

        $order = $_POST['order'] ?? [];
        if (!is_array($order)) {
            wp_send_json_error(['message' => 'Ungueltige Daten']);
        }

        $config = $this->config->get_config();

        foreach ($config['tabs'] as &$tab) {
            $pos = array_search($tab['id'], $order, true);
            if ($pos !== false) {
                $tab['order'] = ($pos + 1) * 10;
            }
        }
        unset($tab);

        $this->config->save_config($config);

        wp_send_json_success(['message' => 'Reihenfolge gespeichert']);
    }
}
