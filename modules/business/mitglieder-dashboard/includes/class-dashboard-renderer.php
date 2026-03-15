<?php
/**
 * Dashboard Renderer - Supports parent/child tab hierarchy with folder sub-tabs
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

    /**
     * Shortcode handler for [dgptm_dashboard]
     */
    public function render_shortcode($atts) {
        if (!is_user_logged_in()) {
            return '<div class="dgptm-dashboard-login-required"><p>Bitte melden Sie sich an.</p></div>';
        }

        $user_id = get_current_user_id();
        $this->permissions->preload_permissions($user_id);

        // Only top-level tabs in main nav
        $visible_tabs = $this->get_visible_top_level_tabs($user_id);

        if (empty($visible_tabs)) {
            return '<div class="dgptm-dashboard-empty"><p>Keine Inhalte verfuegbar.</p></div>';
        }

        $default_tab = $this->config->get_setting('default_tab', 'profil');
        $active_tab_id = $default_tab;

        $found = false;
        foreach ($visible_tabs as $tab) {
            if ($tab['id'] === $active_tab_id) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $active_tab_id = $visible_tabs[0]['id'];
        }

        $crm_data = $this->crm_cache->get_user_data($user_id);

        DGPTM_Mitglieder_Dashboard::get_instance()->enqueue_frontend_assets();

        $primary = esc_attr($this->config->get_setting('primary_color', '#005792'));
        $accent  = esc_attr($this->config->get_setting('accent_color', '#bd1622'));
        wp_add_inline_style('dgptm-dashboard-frontend', ":root{--dgptm-primary:{$primary};--dgptm-accent:{$accent};}");

        ob_start();
        include DGPTM_DASHBOARD_PATH . 'templates/frontend-dashboard.php';
        return ob_get_clean();
    }

    /**
     * Get visible top-level tabs for a user
     */
    public function get_visible_top_level_tabs($user_id) {
        $top_level = $this->config->get_top_level_tabs();
        return array_values(array_filter($top_level, function ($tab) use ($user_id) {
            return !empty($tab['active']) && $this->permissions->user_can_see_tab($user_id, $tab);
        }));
    }

    /**
     * Get visible child tabs for a parent
     */
    public function get_visible_children($parent_id, $user_id) {
        $children = $this->config->get_child_tabs($parent_id);
        return array_values(array_filter($children, function ($tab) use ($user_id) {
            return !empty($tab['active']) && $this->permissions->user_can_see_tab($user_id, $tab);
        }));
    }

    /**
     * Render a tab panel content - handles parent tabs with children automatically
     */
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

        // Check if this tab has children - if so, render folder layout
        $children = $this->get_visible_children($tab_id, $user_id);

        if (!empty($children)) {
            return $this->render_parent_with_children($tab, $children, $user_id);
        }

        // Simple tab - just render its template
        return $this->render_single_tab($tab, $user_id);
    }

    /**
     * Render a parent tab with folder sub-tabs for its children
     */
    private function render_parent_with_children($parent, $children, $user_id) {
        // Parent tab is the first folder tab, children follow
        $all_folder_tabs = array_merge([$parent], $children);

        $crm_data    = $this->crm_cache->get_user_data($user_id);
        $permissions = $this->permissions;
        $config      = $this->config;

        ob_start();
        ?>
        <div class="dgptm-folder-tabs" data-subtab-group="<?php echo esc_attr($parent['id']); ?>">
            <div class="dgptm-folder-nav">
                <?php foreach ($all_folder_tabs as $i => $ftab) : ?>
                    <a href="#"
                       class="dgptm-folder-tab<?php echo $i === 0 ? ' dgptm-folder-tab--active' : ''; ?>"
                       data-subtab="<?php echo esc_attr($ftab['id']); ?>">
                        <?php echo esc_html($ftab['label']); ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <div class="dgptm-folder-content">
                <?php foreach ($all_folder_tabs as $i => $ftab) : ?>
                    <div class="dgptm-subtab-panel<?php echo $i === 0 ? ' dgptm-subtab-panel--active' : ''; ?>"
                         data-subtab-panel="<?php echo esc_attr($ftab['id']); ?>"
                         data-subtab-loaded="<?php echo $i === 0 ? 'true' : 'false'; ?>"
                         data-subtab-action="<?php echo esc_attr($ftab['id']); ?>">
                        <?php if ($i === 0) : ?>
                            <?php echo $this->render_single_tab($ftab, $user_id); ?>
                        <?php else : ?>
                            <div class="dgptm-tab-loading"><div class="dgptm-spinner"></div><span>Wird geladen...</span></div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render a single tab - content_html (from admin) takes priority over template file
     */
    public function render_single_tab($tab, $user_id) {
        // If admin-defined HTML content exists, use it (with shortcode processing)
        if (!empty($tab['content_html'])) {
            return do_shortcode($tab['content_html']);
        }

        // Otherwise fall back to template file
        $safe_id = preg_replace('/[^a-z0-9\-]/', '', $tab['id']);
        $template_file = DGPTM_DASHBOARD_PATH . 'templates/tabs/tab-' . $safe_id . '.php';

        if (!file_exists($template_file)) {
            return '<div class="dgptm-tab-error">Kein Inhalt und kein Template fuer: ' . esc_html($tab['label']) . '</div>';
        }

        $crm_data    = $this->crm_cache->get_user_data($user_id);
        $permissions = $this->permissions;
        $config      = $this->config;

        ob_start();
        include $template_file;
        return do_shortcode(ob_get_clean());
    }

    /**
     * Render the main tab navigation (top-level only)
     */
    public function render_tab_navigation($visible_tabs, $active_tab_id) {
        $use_dropdown = $this->config->get_setting('mobile_dropdown', true);
        ?>
        <nav class="dgptm-tab-nav" role="tablist" aria-label="Dashboard Navigation">
            <div class="dgptm-tab-nav__scroll">
                <?php foreach ($visible_tabs as $tab) :
                    $is_active = ($tab['id'] === $active_tab_id);
                ?>
                    <a href="#tab-<?php echo esc_attr($tab['id']); ?>"
                       class="dgptm-tab-nav__item<?php echo $is_active ? ' dgptm-tab-nav__item--active' : ''; ?>"
                       role="tab"
                       aria-selected="<?php echo $is_active ? 'true' : 'false'; ?>"
                       aria-controls="dgptm-panel-<?php echo esc_attr($tab['id']); ?>"
                       data-tab-id="<?php echo esc_attr($tab['id']); ?>">
                        <span class="dgptm-tab-nav__label"><?php echo esc_html($tab['label']); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </nav>

        <?php if ($use_dropdown) : ?>
        <div class="dgptm-tab-mobile-select">
            <select id="dgptm-mobile-tab-select" aria-label="Tab auswaehlen">
                <?php foreach ($visible_tabs as $tab) : ?>
                    <option value="<?php echo esc_attr($tab['id']); ?>"
                            <?php selected($tab['id'], $active_tab_id); ?>>
                        <?php echo esc_html($tab['label']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php endif;
    }
}
