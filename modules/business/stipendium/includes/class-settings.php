<?php
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Settings {

    private $plugin_path;
    private $plugin_url;

    const OPTION_KEY = 'dgptm_stipendium_settings';

    /** Standard-Einstellungen */
    private $defaults = [
        'stipendientypen' => [
            [
                'id'          => 'promotionsstipendium',
                'bezeichnung' => 'Promotionsstipendium',
                'runde'       => '',
                'start'       => '',
                'ende'        => '',
            ],
            [
                'id'          => 'josef_guettler',
                'bezeichnung' => 'Josef Guettler Stipendium',
                'runde'       => '',
                'start'       => '',
                'ende'        => '',
            ],
        ],
        'freigabe_modus'                  => 'vorsitz',
        'gleichstand_regel'               => 'rubrik_a',
        'loeschfrist_monate_nicht_vergeben' => 12,
        'loeschfrist_jahre_vergeben'       => 10,
        'auto_loeschung'                  => false,
        'bestaetigungsmail_text'          => "Sehr geehrte/r {name},\n\nvielen Dank fuer Ihre Bewerbung fuer das {stipendientyp} der DGPTM.\n\nIhre Bewerbung ist eingegangen und wird geprueft. Sie erhalten eine weitere Benachrichtigung, sobald das Verfahren abgeschlossen ist.\n\nMit freundlichen Gruessen\nDGPTM - Stipendiumsrat",
        'workdrive_team_folder_id'        => '',
        'benachrichtigung_vorsitz_email'  => '',
        'gutachter_frist_tage'            => 28,
    ];

    public function __construct($plugin_path, $plugin_url) {
        $this->plugin_path = $plugin_path;
        $this->plugin_url  = $plugin_url;

        if (is_admin()) {
            add_action('admin_menu', [$this, 'add_settings_page']);
            add_action('wp_ajax_dgptm_stipendium_save_settings', [$this, 'ajax_save_settings']);
        }
    }

    /**
     * Alle Einstellungen abrufen (mit Defaults gemergt).
     */
    public function get_all() {
        $saved = get_option(self::OPTION_KEY, []);
        return wp_parse_args($saved, $this->defaults);
    }

    /**
     * Einzelne Einstellung abrufen.
     */
    public function get($key) {
        $all = $this->get_all();
        return $all[$key] ?? ($this->defaults[$key] ?? null);
    }

    /**
     * Stipendientyp-Konfiguration nach ID abrufen.
     */
    public function get_stipendientyp($typ_id) {
        $typen = $this->get('stipendientypen');
        foreach ($typen as $typ) {
            if ($typ['id'] === $typ_id) {
                return $typ;
            }
        }
        return null;
    }

    /**
     * Pruefen ob Bewerbungszeitraum fuer einen Typ aktiv ist.
     */
    public function is_bewerbung_offen($typ_id) {
        $typ = $this->get_stipendientyp($typ_id);
        if (!$typ || empty($typ['start']) || empty($typ['ende'])) {
            return false;
        }
        $now   = current_time('Y-m-d');
        return ($now >= $typ['start'] && $now <= $typ['ende']);
    }

    /**
     * Naechstes Bewerbungsende fuer einen Typ (fuer Hinweistext).
     */
    public function naechster_bewerbungsschluss($typ_id) {
        $typ = $this->get_stipendientyp($typ_id);
        if (!$typ || empty($typ['start'])) {
            return null;
        }
        $now = current_time('Y-m-d');
        if ($now < $typ['start']) {
            return $typ['start'];
        }
        return $typ['ende'] ?? null;
    }

    /**
     * Admin-Menue hinzufuegen (unter DGPTM Suite).
     */
    public function add_settings_page() {
        add_submenu_page(
            'dgptm-suite',
            'Stipendium Einstellungen',
            'Stipendium',
            'manage_options',
            'dgptm-stipendium-settings',
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page() {
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.');
        }
        $settings = $this->get_all();
        $nonce = wp_create_nonce('dgptm_stipendium_settings_nonce');
        include $this->plugin_path . 'templates/admin-settings.php';
    }

    /**
     * AJAX: Einstellungen speichern.
     */
    public function ajax_save_settings() {
        check_ajax_referer('dgptm_stipendium_settings_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung.', 403);
        }

        $raw = wp_unslash($_POST['settings'] ?? '');
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            wp_send_json_error('Ungueltige Daten.');
        }

        // Sanitize
        $clean = [];

        // Stipendientypen
        if (isset($data['stipendientypen']) && is_array($data['stipendientypen'])) {
            $clean['stipendientypen'] = [];
            foreach ($data['stipendientypen'] as $typ) {
                $clean['stipendientypen'][] = [
                    'id'          => sanitize_key($typ['id'] ?? ''),
                    'bezeichnung' => sanitize_text_field($typ['bezeichnung'] ?? ''),
                    'runde'       => sanitize_text_field($typ['runde'] ?? ''),
                    'start'       => sanitize_text_field($typ['start'] ?? ''),
                    'ende'        => sanitize_text_field($typ['ende'] ?? ''),
                ];
            }
        }

        // Einfache Felder
        $clean['freigabe_modus']     = in_array($data['freigabe_modus'] ?? '', ['vorsitz', 'direkt']) ? $data['freigabe_modus'] : 'vorsitz';
        $clean['gleichstand_regel']  = in_array($data['gleichstand_regel'] ?? '', ['rubrik_a', 'mehrheit', 'manuell']) ? $data['gleichstand_regel'] : 'rubrik_a';
        $clean['loeschfrist_monate_nicht_vergeben'] = absint($data['loeschfrist_monate_nicht_vergeben'] ?? 12);
        $clean['loeschfrist_jahre_vergeben']        = absint($data['loeschfrist_jahre_vergeben'] ?? 10);
        $clean['auto_loeschung']     = !empty($data['auto_loeschung']);
        $clean['bestaetigungsmail_text'] = sanitize_textarea_field($data['bestaetigungsmail_text'] ?? '');
        $clean['workdrive_team_folder_id'] = sanitize_text_field($data['workdrive_team_folder_id'] ?? '');
        $clean['benachrichtigung_vorsitz_email'] = sanitize_email($data['benachrichtigung_vorsitz_email'] ?? '');
        $clean['gutachter_frist_tage'] = absint($data['gutachter_frist_tage'] ?? 28);
        if ($clean['gutachter_frist_tage'] < 7) $clean['gutachter_frist_tage'] = 7;
        if ($clean['gutachter_frist_tage'] > 90) $clean['gutachter_frist_tage'] = 90;

        update_option(self::OPTION_KEY, $clean, false);

        wp_send_json_success(['message' => 'Einstellungen gespeichert.']);
    }
}
