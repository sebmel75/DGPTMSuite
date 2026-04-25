<?php
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Dashboard_Tab {

    private $plugin_path;
    private $plugin_url;
    private $settings;
    private $vorsitz_dashboard;

    public function __construct($plugin_path, $plugin_url, $settings, $vorsitz_dashboard = null) {
        $this->plugin_path       = $plugin_path;
        $this->plugin_url        = $plugin_url;
        $this->settings          = $settings;
        $this->vorsitz_dashboard = $vorsitz_dashboard;

        add_action('init', [$this, 'ensure_dashboard_tabs']);
        add_shortcode('dgptm_stipendium_dashboard',  [$this, 'render_dashboard']);
        add_shortcode('dgptm_stipendium_auswertung', [$this, 'render_auswertung']);

        // Permission-Wrapper: das Mitglieder-Dashboard prueft "sc:..."-Berechtigungen
        // ueber den Shortcode-Output ('1'/'0'). Wir liefern hier eine robuste
        // Auswertung von ACF *und* user_meta, damit auch Toggles, die nicht ueber
        // ACF gesetzt wurden, akzeptiert werden.
        add_shortcode('dgptm_stip_perm_mitglied', [$this, 'sc_perm_mitglied']);
        add_shortcode('dgptm_stip_perm_vorsitz',  [$this, 'sc_perm_vorsitz']);
    }

    /* ──────────────────────────────────────────
     * Permission-Helfer
     * ────────────────────────────────────────── */

    /**
     * Robust: ACF-Feld ODER user_meta ODER Admin.
     */
    public static function user_has_flag($user_id, $field) {
        if (!$user_id) return false;
        if (user_can($user_id, 'manage_options')) return true;
        if (function_exists('get_field')) {
            $v = get_field($field, 'user_' . $user_id);
            if (!empty($v)) return true;
        }
        $v = get_user_meta($user_id, $field, true);
        return !empty($v);
    }

    public function sc_perm_mitglied() {
        $uid = get_current_user_id();
        if (!$uid) return '0';
        // Vorsitz schliesst Mitglied automatisch ein
        if (self::user_has_flag($uid, 'stipendiumsrat_mitglied')) return '1';
        if (self::user_has_flag($uid, 'stipendiumsrat_vorsitz'))  return '1';
        return '0';
    }

    public function sc_perm_vorsitz() {
        $uid = get_current_user_id();
        if (!$uid) return '0';
        return self::user_has_flag($uid, 'stipendiumsrat_vorsitz') ? '1' : '0';
    }

    /**
     * Dashboard-Tabs synchronisieren — bei jedem Init.
     *
     * Es gibt nur EINEN Stipendium-Tab im Mitglieder-Dashboard:
     *   "Stipendien" → [dgptm_stipendium_dashboard]
     * Die Auswertung wird im selben Tab je nach Berechtigung gerendert.
     *
     * Frueher angelegte Tabs (stipendien_auswertung, stipendien_freigabe)
     * werden aktiv entfernt — Konzept-Freigabe lebt ausschliesslich auf
     * der eigenen Seite via [dgptm_stipendium_freigabe].
     */
    public function ensure_dashboard_tabs() {
        $option_key = 'dgptm_dash_tabs_v3';
        $tabs = get_option($option_key, []);
        if (!is_array($tabs)) $tabs = [];

        $soll = [
            'stipendien' => [
                'id'         => 'stipendien',
                'label'      => 'Stipendien',
                'parent'     => '',
                'active'     => true,
                'order'      => 60,
                'permission' => 'sc:dgptm_stip_perm_mitglied',
                'link'       => '',
                'content'    => '[dgptm_stipendium_dashboard]',
                'content_mobile' => '',
                'visibility' => 'all',
            ],
        ];

        // Tabs, die NICHT mehr existieren sollen — werden aktiv entfernt.
        $remove_ids = ['stipendien_auswertung', 'stipendien_freigabe'];

        $changed = false;
        $known_ids = array_keys($soll);
        $found_ids = [];

        $tabs = array_values(array_filter($tabs, function ($t) use ($remove_ids, &$changed) {
            if (in_array($t['id'] ?? '', $remove_ids, true)) {
                $changed = true;
                return false;
            }
            return true;
        }));

        foreach ($tabs as $idx => $existing) {
            $eid = $existing['id'] ?? '';
            if (isset($soll[$eid])) {
                $found_ids[] = $eid;
                $merged = array_merge($existing, $soll[$eid]);
                if ($merged !== $existing) {
                    $tabs[$idx] = $merged;
                    $changed = true;
                }
            }
        }

        foreach ($known_ids as $eid) {
            if (!in_array($eid, $found_ids, true)) {
                $tabs[] = $soll[$eid];
                $changed = true;
            }
        }

        if ($changed) {
            update_option($option_key, $tabs, false);
        }
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
        $is_vorsitz  = self::user_has_flag($user_id, 'stipendiumsrat_vorsitz');
        $is_mitglied = self::user_has_flag($user_id, 'stipendiumsrat_mitglied');

        if (!$is_vorsitz && !$is_mitglied) return '';

        // Vorsitz erhaelt das vollstaendige Dashboard direkt im Haupt-Tab.
        if ($is_vorsitz && $this->vorsitz_dashboard) {
            return $this->vorsitz_dashboard->render();
        }

        // Mitglieder sehen die Runden-Uebersicht.
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
                            Die Bewertung erfolgt persönlich über den Einladungslink des Vorsitzenden.
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
        if (!self::user_has_flag($user_id, 'stipendiumsrat_vorsitz')) return '';

        // An Vorsitzenden-Dashboard delegieren
        if ($this->vorsitz_dashboard) {
            return $this->vorsitz_dashboard->render();
        }

        // Fallback (sollte nicht vorkommen)
        return '<p>Vorsitzenden-Dashboard nicht verfuegbar.</p>';
    }
}
