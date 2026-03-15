<?php
/**
 * Dashboard Renderer - Shortcode handler, tab navigation, content rendering
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

        // Preload permissions for efficiency
        $this->permissions->preload_permissions($user_id);

        // Get visible tabs
        $visible_tabs = $this->permissions->get_visible_tabs($user_id);

        if (empty($visible_tabs)) {
            return '<div class="dgptm-dashboard-empty"><p>Keine Inhalte verfuegbar.</p></div>';
        }

        // Determine active tab
        $default_tab = $this->config->get_setting('default_tab', 'profil');
        $active_tab_id = $default_tab;

        // Check if default tab is visible, otherwise use first visible
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

        // Preload CRM data
        $crm_data = $this->crm_cache->get_user_data($user_id);

        // Enqueue assets
        DGPTM_Mitglieder_Dashboard::get_instance()->enqueue_frontend_assets();

        // Inject CSS custom properties from settings
        $primary = esc_attr($this->config->get_setting('primary_color', '#005792'));
        $accent  = esc_attr($this->config->get_setting('accent_color', '#bd1622'));
        wp_add_inline_style('dgptm-dashboard-frontend', ":root{--dgptm-primary:{$primary};--dgptm-accent:{$accent};}");

        // Render
        ob_start();
        include DGPTM_DASHBOARD_PATH . 'templates/frontend-dashboard.php';
        return ob_get_clean();
    }

    /**
     * Render tab content (used by both initial load and AJAX)
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

        $template_file = DGPTM_DASHBOARD_PATH . 'templates/' . $tab['template'];
        if (!file_exists($template_file)) {
            return '<div class="dgptm-tab-error">Template nicht vorhanden: ' . esc_html($tab['template']) . '</div>';
        }

        // Make data available to templates
        $crm_data    = $this->crm_cache->get_user_data($user_id);
        $permissions = $this->permissions;
        $config      = $this->config;

        ob_start();
        include $template_file;
        $html = ob_get_clean();

        // Process shortcodes in the template output
        return do_shortcode($html);
    }

    /**
     * Render the tab navigation bar
     */
    public function render_tab_navigation($visible_tabs, $active_tab_id) {
        $use_dropdown = $this->config->get_setting('mobile_dropdown', true);
        ?>
        <nav class="dgptm-tab-nav" role="tablist" aria-label="Dashboard Navigation">
            <div class="dgptm-tab-nav__scroll">
                <?php foreach ($visible_tabs as $tab) :
                    $is_active = ($tab['id'] === $active_tab_id);
                    $icon = $tab['icon'] ?? 'dashicons-admin-page';
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
