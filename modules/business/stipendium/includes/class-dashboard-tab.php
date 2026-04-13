<?php
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Dashboard_Tab {

    private $plugin_path;
    private $plugin_url;
    private $settings;
    private $vorsitz_dashboard;

    const TABS_REGISTERED_KEY = 'dgptm_stipendium_tabs_registered_v1';

    public function __construct($plugin_path, $plugin_url, $settings, $vorsitz_dashboard = null) {
        $this->plugin_path       = $plugin_path;
        $this->plugin_url        = $plugin_url;
        $this->settings          = $settings;
        $this->vorsitz_dashboard = $vorsitz_dashboard;

        add_action('init', [$this, 'ensure_dashboard_tabs']);
        add_shortcode('dgptm_stipendium_dashboard', [$this, 'render_dashboard']);
        add_shortcode('dgptm_stipendium_auswertung', [$this, 'render_auswertung']);
    }

    /**
     * Dashboard-Tabs registrieren (einmalig).
     *
     * Fuegt den "Stipendien"-Tab und den Unter-Tab "Auswertung"
     * in das Mitglieder-Dashboard ein.
     */
    public function ensure_dashboard_tabs() {
        if (get_option(self::TABS_REGISTERED_KEY)) return;

        $tabs = get_option('dgptm_dash_tabs_v3', []);

        // Pruefen ob Tab bereits existiert
        $existing_ids = array_column($tabs, 'id');

        if (!in_array('stipendien', $existing_ids)) {
            $tabs[] = [
                'id'         => 'stipendien',
                'label'      => 'Stipendien',
                'parent'     => '',
                'active'     => true,
                'order'      => 60,
                'permission' => 'acf:stipendiumsrat_mitglied',
                'link'       => '',
                'content'    => '[dgptm_stipendium_dashboard]',
                'content_mobile' => '',
                'visibility' => 'all',
            ];
        }

        if (!in_array('stipendien_auswertung', $existing_ids)) {
            $tabs[] = [
                'id'         => 'stipendien_auswertung',
                'label'      => 'Auswertung',
                'parent'     => 'stipendien',
                'active'     => true,
                'order'      => 61,
                'permission' => 'acf:stipendiumsrat_vorsitz',
                'link'       => '',
                'content'    => '[dgptm_stipendium_auswertung]',
                'content_mobile' => '',
                'visibility' => 'all',
            ];
        }

        update_option('dgptm_dash_tabs_v3', $tabs, false);
        update_option(self::TABS_REGISTERED_KEY, 1);
    }

    /**
     * Shortcode: Gutachter-Dashboard (Bewerbungsliste + Bewertung).
     *
     * HINWEIS: Vollstaendige Implementierung erfolgt nach Feedback des Stipendiumsrats.
     * Aktuell: Platzhalter mit Status-Anzeige.
     */
    public function render_dashboard($atts) {
        if (!is_user_logged_in()) return '';

        $user_id = get_current_user_id();
        $ist_mitglied = get_field('stipendiumsrat_mitglied', 'user_' . $user_id);
        if (!$ist_mitglied && !current_user_can('manage_options')) return '';

        // Aktive Runden ermitteln
        $typen = $this->settings->get('stipendientypen');
        $aktive_runden = array_filter($typen, function ($t) {
            return !empty($t['runde']);
        });

        ob_start();
        ?>
        <div class="dgptm-stipendium-dashboard">
            <h3>Stipendien</h3>
            <?php if (empty($aktive_runden)) : ?>
                <p>Aktuell sind keine Stipendienrunden konfiguriert.</p>
            <?php else : ?>
                <?php foreach ($aktive_runden as $typ) : ?>
                    <div class="dgptm-stipendium-runde-card" style="background:#f8f9fa;border:1px solid #dee2e6;border-radius:8px;padding:16px;margin-bottom:12px;">
                        <h4 style="margin:0 0 8px;"><?php echo esc_html($typ['bezeichnung']); ?></h4>
                        <p style="margin:0;color:#666;">
                            Runde: <strong><?php echo esc_html($typ['runde']); ?></strong>
                            <?php if (!empty($typ['start']) && !empty($typ['ende'])) : ?>
                                | Bewerbungszeitraum: <?php echo esc_html(date_i18n('d.m.Y', strtotime($typ['start']))); ?> &ndash; <?php echo esc_html(date_i18n('d.m.Y', strtotime($typ['ende']))); ?>
                            <?php endif; ?>
                        </p>
                        <p style="margin:8px 0 0;color:#888;font-style:italic;">
                            Bewerbungsliste und Bewertungsbogen werden nach Freigabe des Konzepts freigeschaltet.
                        </p>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Shortcode: Auswertungs-Dashboard (nur Vorsitzender).
     */
    public function render_auswertung($atts) {
        if (!is_user_logged_in()) return '';

        $user_id = get_current_user_id();
        $ist_vorsitz = get_field('stipendiumsrat_vorsitz', 'user_' . $user_id);
        if (!$ist_vorsitz && !current_user_can('manage_options')) return '';

        // An Vorsitzenden-Dashboard delegieren
        if ($this->vorsitz_dashboard) {
            return $this->vorsitz_dashboard->render();
        }

        // Fallback (sollte nicht vorkommen)
        return '<p>Vorsitzenden-Dashboard nicht verfuegbar.</p>';
    }
}
