<?php
/**
 * Tab storage: simple array in wp_options.
 * Each tab: {id, label, parent, active, order, permission, content}
 *
 * Permission formats:
 *   "always"                  - everyone
 *   "admin"                   - manage_options capability
 *   "acf:field_name"          - ACF True/False field on user profile
 *   "role:role1,role2"        - WordPress user roles (OR)
 *   "sc:shortcode_name"       - Shortcode that returns "1" = visible
 *
 * Hinweis: Webhook-URLs (z.B. Zoho Flow, Bescheinigungs-Trigger) sind im
 * content_html der jeweiligen Tabs gespeichert und ueber das Admin-Dashboard
 * unter "Mitglieder-Dashboard → Tabs verwalten" editierbar. Keine hartkodierten
 * URLs im PHP-Code.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Dashboard_Tabs {
    private static $instance = null;
    const OPT = 'dgptm_dash_tabs_v3';
    const OPT_SETTINGS = 'dgptm_dash_settings';

    public static function get_instance() {
        if (!self::$instance) self::$instance = new self();
        return self::$instance;
    }

    // ─── Settings ───

    public function get_settings() {
        return wp_parse_args(get_option(self::OPT_SETTINGS, []), [
            'admin_bypass' => true,  // true = admins see all tabs regardless of rules
        ]);
    }

    public function save_settings($settings) {
        update_option(self::OPT_SETTINGS, $settings, false);
    }

    // ─── Tabs ───

    public function get_all() {
        $tabs = get_option(self::OPT, null);
        if (!is_array($tabs) || empty($tabs)) {
            $tabs = $this->defaults();
            update_option(self::OPT, $tabs, false);
        }
        usort($tabs, function($a, $b) { return ($a['order'] ?? 99) - ($b['order'] ?? 99); });
        return $tabs;
    }

    public function get_tab($id) {
        foreach ($this->get_all() as $t) {
            if ($t['id'] === $id) return $t;
        }
        return null;
    }

    public function get_visible_tabs($user_id) {
        return array_values(array_filter($this->get_all(), function($t) use ($user_id) {
            if (empty($t['active'])) return false;
            // Hide tabs with no content, no link (unless they might have children)
            if (empty(trim($t['content'] ?? '')) && empty($t['link']) && !empty($t['parent'])) {
                return false;
            }
            return $this->check_permission($user_id, $t);
        }));
    }

    public function save($tabs) {
        $clean = [];
        foreach ($tabs as $t) {
            $vis = sanitize_key($t['visibility'] ?? 'all');
            if (!in_array($vis, ['all', 'mobile', 'desktop'], true)) $vis = 'all';
            $clean[] = [
                'id'         => sanitize_key($t['id'] ?? ''),
                'label'      => sanitize_text_field($t['label'] ?? ''),
                'parent'     => sanitize_key($t['parent'] ?? ''),
                'active'     => !empty($t['active']),
                'order'      => absint($t['order'] ?? 99),
                'permission' => sanitize_text_field($t['permission'] ?? 'always'),
                'link'       => esc_url_raw($t['link'] ?? ''),
                'content'        => $t['content'] ?? '',
                'content_mobile' => $t['content_mobile'] ?? '',
                'visibility'     => $vis,
            ];
        }
        update_option(self::OPT, $clean, false);
    }

    public function reset() {
        delete_option(self::OPT);
    }

    // ─── Permission Check ───

    private function check_permission($user_id, $tab) {
        $perm = $tab['permission'] ?? 'always';
        if ($perm === 'always') return true;

        $is_admin = user_can($user_id, 'manage_options');
        $settings = $this->get_settings();
        $admin_bypass = !empty($settings['admin_bypass']);

        // "admin" permission: only admins
        if ($perm === 'admin') return $is_admin;

        // Kombinierte Berechtigungen (AND-Logik mit "+")
        // z.B. "acf:testbereich+role:administrator" → beide muessen erfuellt sein
        if (strpos($perm, '+') !== false) {
            if ($admin_bypass && $is_admin) return true;
            $parts = array_map('trim', explode('+', $perm));
            foreach ($parts as $part) {
                $sub_tab = ['permission' => $part];
                // Rekursiver Check ohne admin_bypass (bereits oben geprueft)
                if (!$this->check_single_permission($user_id, $part)) return false;
            }
            return true;
        }

        if ($admin_bypass && $is_admin) return true;
        return $this->check_single_permission($user_id, $perm);
    }

    /**
     * Prueft eine einzelne Berechtigungsregel (ohne AND-Kombination).
     */
    private function check_single_permission($user_id, $perm) {
        // ACF field check (e.g. "acf:testbereich" or "acf:testbereich,umfragen,webinar")
        if (strpos($perm, 'acf:') === 0) {
            $fields = array_map('trim', explode(',', substr($perm, 4)));
            foreach ($fields as $field) {
                if (empty($field)) continue;
                $val = function_exists('get_field')
                    ? get_field($field, 'user_' . $user_id)
                    : get_user_meta($user_id, $field, true);
                if (!empty($val)) return true; // OR-Logik: eines genuegt
            }
            return false;
        }

        // Role check (e.g. "role:jahrestagung,administrator")
        if (strpos($perm, 'role:') === 0) {
            $roles = explode(',', substr($perm, 5));
            $user = get_userdata($user_id);
            if (!$user) return false;
            foreach ($roles as $r) {
                if (in_array(trim($r), $user->roles, true)) return true;
            }
            return false;
        }

        // Shortcode check (e.g. "sc:umfrageberechtigung")
        if (strpos($perm, 'sc:') === 0) {
            $sc_name = substr($perm, 3);
            $exists = shortcode_exists($sc_name);
            $raw = $exists ? do_shortcode('[' . $sc_name . ']') : '(not registered)';
            $result = strtolower(trim($raw));
            $pass = ($result === '1' || $result === 'true');
            error_log("[Dashboard] sc:{$sc_name} exists={$exists} raw='{$raw}' result='{$result}' pass={$pass}");
            if (!$exists) return false;
            return $pass;
        }

        if ($perm === 'admin') return user_can($user_id, 'manage_options');

        return true;
    }

    // ─── Defaults ───

    private function defaults() {
        return [
            ['id' => 'profil', 'label' => 'Willkommen', 'parent' => '', 'active' => true, 'order' => 10, 'permission' => 'always',
             'content' => "<h2>Willkommen im Mitgliederbereich</h2>\n<p>Mitgliederbereich der DGPTM</p>\n\n[zoho_books_outstanding_banner]\n[dgptm-studistatus-banner]\n\n<div class=\"dgptm-profile-meta\">\n<span class=\"dgptm-badge dgptm-badge--primary\">[zoho_api_data_ajax field=\"Mitgliedsart\"]</span>\n<span class=\"dgptm-badge dgptm-badge--primary\">Nr. [zoho_api_data_ajax field=\"MitgliedsNr\"]</span>\n<span class=\"dgptm-badge dgptm-badge--success\">[zoho_api_data_ajax field=\"Status\"]</span>\n<span class=\"dgptm-badge dgptm-badge--accent\">EFN: [zoho_api_data_ajax field=\"EFN\"]</span>\n</div>\n\n<div class=\"dgptm-card\">\n<h3>Kontaktdaten</h3>\n<dl class=\"dgptm-data-list\">\n<dt>Adresse</dt><dd>[zoho_api_data_ajax field=\"Strasse\"]<br>[zoho_api_data_ajax field=\"PLZ\"] [zoho_api_data_ajax field=\"Ort\"]</dd>\n<dt>Telefon</dt><dd>[zoho_api_data_ajax field=\"TelDienst\"]</dd>\n<dt>Mobil</dt><dd>[zoho_api_data_ajax field=\"TelMobil\"]</dd>\n<dt>E-Mail</dt><dd>[zoho_api_data_ajax field=\"Mail1\"]</dd>\n</dl>\n</div>\n\n<p><a href=\"/mitgliedschaft/interner-bereich/fortbildungsnachweis/\" class=\"dgptm-btn dgptm-btn--primary\">Fortbildungsnachweis</a></p>"],

            ['id' => 'profil-stammdaten', 'label' => 'Stammdaten bearbeiten', 'parent' => 'profil', 'active' => true, 'order' => 11, 'permission' => 'always',
             'content' => '[dgptm-daten-bearbeiten]'],

            ['id' => 'profil-rechnungen', 'label' => 'Rechnungen', 'parent' => 'profil', 'active' => true, 'order' => 12, 'permission' => 'always',
             'content' => '[zoho_books_transactions]'],

            ['id' => 'profil-lastschrift', 'label' => 'Lastschrift & Bescheinigung', 'parent' => 'profil', 'active' => true, 'order' => 13, 'permission' => 'always',
             'content' => "<h4>Lastschriftmandat</h4>\n[gcl_formidable]\n<h4>Mitgliedsbescheinigung</h4>\n[webhook_ajax_trigger url=\"https://flow.zoho.eu/20086283718/flow/webhook/incoming?zapikey=1001.61e55251780c1730ee213bfe02d8a192.eb83171de88e8e99371cf264aa47e96c&isdebug=false\" method=\"POST\" user_field=\"zoho_id\" cooldown=\"6\" status_id=\"mgb\" cooldown_message=\"Du hast heute schon eine Bescheinigung angefordert.\"]\n[webhook_status_output id=\"mgb\"]\n<h4>Studierendenstatus</h4>\n[dgptm-studistatus]"],

            ['id' => 'profil-efn', 'label' => 'EFN-Etiketten', 'parent' => 'profil', 'active' => true, 'order' => 14, 'permission' => 'always',
             'content' => "[efn_barcode_js]\n[efn_label_sheet]"],

            ['id' => 'profil-fortbildung', 'label' => 'Fortbildung', 'parent' => 'profil', 'active' => true, 'order' => 15, 'permission' => 'always',
             'content' => "<a href=\"/mitgliedschaft/interner-bereich/fortbildungsnachweis/\" class=\"dgptm-btn dgptm-btn--primary\">Fortbildungsnachweis (inkl. Quiz)</a>\n[fobi_nachweis_pruefliste]"],

            ['id' => 'news', 'label' => 'News & Events', 'parent' => '', 'active' => true, 'order' => 40, 'permission' => 'always',
             'content' => "<h3>Nachrichten</h3>\n[news-modal category=\"intern\" layout=\"list\" title_length=\"100\" show_pubdate=true]\n<h3>Veranstaltungen</h3>\n[news-modal category=\"events\" layout=\"list\" sort_field=\"event_start\" sort_dir=\"ASC\" title_length=\"50\"]"],

            ['id' => 'gehalt', 'label' => 'Gehaltsbarometer', 'parent' => '', 'active' => true, 'order' => 90, 'permission' => 'always',
             'content' => "[gehaltsbarometer_popup_guard id=\"37862\"]\n[gehaltsbarometer]\n[gehaltsbarometer_is][gehaltsbarometer_chart][/gehaltsbarometer_is]"],

            ['id' => 'stellenanzeigen', 'label' => 'Stellenanzeigen', 'parent' => '', 'active' => true, 'order' => 170, 'permission' => 'always',
             'content' => '[stellenanzeigen_editor]'],

            ['id' => 'feedback', 'label' => 'Feedback', 'parent' => '', 'active' => true, 'order' => 190, 'permission' => 'always',
             'content' => "<h3>Anregungen und Wuensche</h3>\n<p>Wir sind fuer alle Wuensche und Hinweise offen.</p>\n[formidable id=13]"],
        ];
    }
}
