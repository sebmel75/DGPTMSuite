<?php
/**
 * Dashboard Renderer - All content from content_html (no template files)
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Dashboard_Renderer {

    private $config;
    private $permissions;
    private $crm_cache;

    public function __construct(
        DGPTM_Dashboard_Config $config,
        DGPTM_Dashboard_Permissions $permissions,
        DGPTM_Dashboard_CRM_Cache $crm_cache
    ) {
        $this->config      = $config;
        $this->permissions = $permissions;
        $this->crm_cache   = $crm_cache;
    }

    public function render_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="dgptm-dashboard-login-required"><p>Bitte melden Sie sich an.</p></div>';
        }

        $user_id = get_current_user_id();
        $this->permissions->preload_permissions($user_id);

        $visible_tabs = $this->get_visible_top_level_tabs($user_id);
        if (empty($visible_tabs)) {
            return '<div class="dgptm-dashboard-empty"><p>Keine Inhalte verfuegbar.</p></div>';
        }

        $default_tab = $this->config->get_setting('default_tab', 'profil');
        $active_tab_id = $default_tab;
        $found = false;
        foreach ($visible_tabs as $tab) {
            if ($tab['id'] === $active_tab_id) { $found = true; break; }
        }
        if (!$found) {
            $active_tab_id = $visible_tabs[0]['id'];
        }

        DGPTM_Mitglieder_Dashboard::get_instance()->enqueue_frontend_assets();

        $primary = esc_attr($this->config->get_setting('primary_color', '#005792'));
        $accent  = esc_attr($this->config->get_setting('accent_color', '#bd1622'));
        wp_add_inline_style('dgptm-dashboard-frontend', ":root{--dgptm-primary:{$primary};--dgptm-accent:{$accent};}");

        // Render inline (no template file)
        $html = '<!-- DGPTM Dashboard v' . DGPTM_DASHBOARD_VERSION . ' -->' . "\n";
        $html .= '<div class="dgptm-dashboard" data-default-tab="' . esc_attr($active_tab_id) . '">';

        // Tab navigation
        $html .= $this->build_tab_navigation($visible_tabs, $active_tab_id);

        // Tab panels
        $html .= '<div class="dgptm-tab-panels">';
        foreach ($visible_tabs as $tab) {
            $is_active = ($tab['id'] === $active_tab_id);
            $html .= '<div class="dgptm-tab-panel' . ($is_active ? ' dgptm-tab-panel--active' : '') . '"'
                . ' id="dgptm-panel-' . esc_attr($tab['id']) . '"'
                . ' role="tabpanel"'
                . ' data-tab-id="' . esc_attr($tab['id']) . '"'
                . ' data-loaded="' . ($is_active ? 'true' : 'false') . '"'
                . ($is_active ? '' : ' style="display:none;"') . '>';

            if ($is_active) {
                $html .= $this->render_tab_content($tab['id'], $user_id);
            } else {
                $html .= '<div class="dgptm-tab-loading"><div class="dgptm-spinner"></div><span>Wird geladen...</span></div>';
            }

            $html .= '</div>';
        }
        $html .= '</div></div>';

        return $html;
    }

    public function get_visible_top_level_tabs($user_id) {
        $top_level = $this->config->get_top_level_tabs();
        return array_values(array_filter($top_level, function ($tab) use ($user_id) {
            return !empty($tab['active']) && $this->permissions->user_can_see_tab($user_id, $tab);
        }));
    }

    public function get_visible_children($parent_id, $user_id) {
        $children = $this->config->get_child_tabs($parent_id);
        return array_values(array_filter($children, function ($tab) use ($user_id) {
            return !empty($tab['active']) && $this->permissions->user_can_see_tab($user_id, $tab);
        }));
    }

    public function render_tab_content($tab_id, $user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }

        $tab = $this->config->get_tab_by_id($tab_id);
        if (!$tab) {
            return '<div class="dgptm-tab-error">Tab nicht gefunden.</div>';
        }

        if (!$this->permissions->user_can_see_tab($user_id, $tab)) {
            return '<div class="dgptm-tab-error">Keine Berechtigung.</div>';
        }

        $children = $this->get_visible_children($tab_id, $user_id);
        if (!empty($children)) {
            return $this->render_parent_with_children($tab, $children, $user_id);
        }

        return $this->render_single_tab($tab, $user_id);
    }

    private function render_parent_with_children($parent, $children, $user_id) {
        $all_folder_tabs = array_merge([$parent], $children);

        $html = '<div class="dgptm-folder-tabs" data-subtab-group="' . esc_attr($parent['id']) . '">';
        $html .= '<div class="dgptm-folder-nav">';
        foreach ($all_folder_tabs as $i => $ftab) {
            $html .= '<a href="#" class="dgptm-folder-tab' . ($i === 0 ? ' dgptm-folder-tab--active' : '') . '"'
                . ' data-subtab="' . esc_attr($ftab['id']) . '">'
                . esc_html($ftab['label']) . '</a>';
        }
        $html .= '</div><div class="dgptm-folder-content">';

        foreach ($all_folder_tabs as $i => $ftab) {
            $html .= '<div class="dgptm-subtab-panel' . ($i === 0 ? ' dgptm-subtab-panel--active' : '') . '"'
                . ' data-subtab-panel="' . esc_attr($ftab['id']) . '"'
                . ' data-subtab-loaded="' . ($i === 0 ? 'true' : 'false') . '"'
                . ' data-subtab-action="' . esc_attr($ftab['id']) . '">';

            if ($i === 0) {
                $html .= $this->render_single_tab($ftab, $user_id);
            } else {
                $html .= '<div class="dgptm-tab-loading"><div class="dgptm-spinner"></div><span>Wird geladen...</span></div>';
            }

            $html .= '</div>';
        }

        $html .= '</div></div>';
        return $html;
    }

    /**
     * Render a single tab from content_html only
     */
    public function render_single_tab($tab, $user_id) {
        $content = $tab['content_html'] ?? '';

        if (empty($content)) {
            return '<div class="dgptm-tab-error">Kein Inhalt fuer Tab "' . esc_html($tab['label']) . '". Bitte in Dashboard-Einstellungen bearbeiten.</div>';
        }

        return do_shortcode($content);
    }

    private function build_tab_navigation($visible_tabs, $active_tab_id) {
        $use_dropdown = $this->config->get_setting('mobile_dropdown', true);

        $html = '<nav class="dgptm-tab-nav" role="tablist" aria-label="Dashboard Navigation">';
        $html .= '<div class="dgptm-tab-nav__scroll">';
        foreach ($visible_tabs as $tab) {
            $is_active = ($tab['id'] === $active_tab_id);
            $html .= '<a href="#tab-' . esc_attr($tab['id']) . '"'
                . ' class="dgptm-tab-nav__item' . ($is_active ? ' dgptm-tab-nav__item--active' : '') . '"'
                . ' role="tab" aria-selected="' . ($is_active ? 'true' : 'false') . '"'
                . ' data-tab-id="' . esc_attr($tab['id']) . '">'
                . '<span class="dgptm-tab-nav__label">' . esc_html($tab['label']) . '</span></a>';
        }
        $html .= '</div></nav>';

        if ($use_dropdown) {
            $html .= '<div class="dgptm-tab-mobile-select"><select id="dgptm-mobile-tab-select" aria-label="Tab auswaehlen">';
            foreach ($visible_tabs as $tab) {
                $html .= '<option value="' . esc_attr($tab['id']) . '"'
                    . ($tab['id'] === $active_tab_id ? ' selected' : '') . '>'
                    . esc_html($tab['label']) . '</option>';
            }
            $html .= '</select></div>';
        }

        return $html;
    }
}
