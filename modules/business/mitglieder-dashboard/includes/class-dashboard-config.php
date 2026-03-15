<?php
/**
 * Dashboard Configuration - Tab definitions with parent/child hierarchy
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Dashboard_Config {

    const OPTION_KEY = 'dgptm_dashboard_config';

    private $config = null;

    public function get_config() {
        if ($this->config === null) {
            $this->config = get_option(self::OPTION_KEY, []);
            if (empty($this->config) || empty($this->config['tabs'])) {
                $this->config = $this->get_defaults();
                update_option(self::OPTION_KEY, $this->config);
            } else {
                // Auto-repair: fill missing fields from defaults
                $defaults = $this->get_defaults();
                $default_map = [];
                foreach ($defaults['tabs'] as $dtab) {
                    $default_map[$dtab['id']] = $dtab;
                }

                $repaired = false;
                foreach ($this->config['tabs'] as &$tab) {
                    // Remove legacy template field
                    if (isset($tab['template'])) {
                        unset($tab['template']);
                        $repaired = true;
                    }
                    // Ensure parent_tab
                    if (!isset($tab['parent_tab'])) {
                        $tab['parent_tab'] = $default_map[$tab['id']]['parent_tab'] ?? '';
                        $repaired = true;
                    }
                    // Ensure content_html - fill from defaults if missing or empty
                    if (!isset($tab['content_html']) || ($tab['content_html'] === '' && !empty($default_map[$tab['id']]['content_html']))) {
                        $tab['content_html'] = $default_map[$tab['id']]['content_html'] ?? '';
                        $repaired = true;
                    }
                }
                unset($tab);

                // Add tabs that exist in defaults but not in saved config
                $saved_ids = array_column($this->config['tabs'], 'id');
                foreach ($defaults['tabs'] as $dtab) {
                    if (!in_array($dtab['id'], $saved_ids, true)) {
                        $this->config['tabs'][] = $dtab;
                        $repaired = true;
                    }
                }

                if ($repaired) {
                    update_option(self::OPTION_KEY, $this->config);
                }
            }
        }
        return $this->config;
    }

    public function save_config($config) {
        $this->config = $config;
        update_option(self::OPTION_KEY, $config);
    }

    public function get_setting($key, $default = null) {
        $config = $this->get_config();
        return $config['settings'][$key] ?? $default;
    }

    public function get_tabs() {
        $config = $this->get_config();
        $tabs = $config['tabs'] ?? [];
        usort($tabs, function ($a, $b) {
            return ($a['order'] ?? 999) - ($b['order'] ?? 999);
        });
        return $tabs;
    }

    /**
     * Get only top-level tabs (no parent)
     */
    public function get_top_level_tabs() {
        return array_values(array_filter($this->get_tabs(), function ($tab) {
            return empty($tab['parent_tab']);
        }));
    }

    /**
     * Get child tabs for a given parent
     */
    public function get_child_tabs($parent_id) {
        return array_values(array_filter($this->get_tabs(), function ($tab) use ($parent_id) {
            return ($tab['parent_tab'] ?? '') === $parent_id;
        }));
    }

    /**
     * Check if a tab has children
     */
    public function has_children($tab_id) {
        return !empty($this->get_child_tabs($tab_id));
    }

    public function get_tab_by_id($tab_id) {
        foreach ($this->get_tabs() as $tab) {
            if ($tab['id'] === $tab_id) {
                return $tab;
            }
        }
        return null;
    }

    /**
     * Get all tab IDs (for admin dropdown)
     */
    public function get_all_tab_ids() {
        $ids = [];
        foreach ($this->get_tabs() as $tab) {
            $ids[$tab['id']] = $tab['label'];
        }
        return $ids;
    }

    public function get_defaults() {
        return [
            'tabs' => [
                // === Top-level: Mein Profil (parent with children) ===
                [
                    'id'               => 'profil',
                    'label'            => 'Mein Profil',
                    'icon'             => 'dashicons-id-alt',
                    'permission_type'  => 'always',
                    'permission_field' => '',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 10,
                    'parent_tab'       => '',

                    'content_html'     => "<h2>Willkommen im Mitgliederbereich</h2>\n<p>Mitgliederbereich der DGPTM</p>\n\n[zoho_books_outstanding_banner]\n[dgptm-studistatus-banner]\n\n<div class=\"dgptm-profile-meta\">\n<span class=\"dgptm-badge dgptm-badge--primary\">[zoho_api_data_ajax field=\"Mitgliedsart\"]</span>\n<span class=\"dgptm-badge dgptm-badge--primary\">Nr. [zoho_api_data_ajax field=\"MitgliedsNr\"]</span>\n<span class=\"dgptm-badge dgptm-badge--success\">[zoho_api_data_ajax field=\"Status\"]</span>\n<span class=\"dgptm-badge dgptm-badge--accent\">EFN: [zoho_api_data_ajax field=\"EFN\"]</span>\n</div>\n\n<div class=\"dgptm-card\">\n<h3>Kontaktdaten</h3>\n<dl class=\"dgptm-data-list\">\n<dt>Adresse</dt>\n<dd>[zoho_api_data_ajax field=\"Strasse\"]<br>[zoho_api_data_ajax field=\"PLZ\"] [zoho_api_data_ajax field=\"Ort\"]</dd>\n<dt>Telefon</dt>\n<dd>[zoho_api_data_ajax field=\"TelDienst\"]</dd>\n<dt>Mobil</dt>\n<dd>[zoho_api_data_ajax field=\"TelMobil\"]</dd>\n<dt>E-Mail</dt>\n<dd>[zoho_api_data_ajax field=\"Mail1\"]</dd>\n</dl>\n</div>\n\n<p><a href=\"/mitgliedschaft/interner-bereich/fortbildungsnachweis/\" class=\"dgptm-btn dgptm-btn--primary\">Fortbildungsnachweis (inkl. Quiz)</a></p>",
                ],
                // Children of profil
                [
                    'id'               => 'profil-stammdaten',
                    'label'            => 'Stammdaten bearbeiten',
                    'icon'             => '',
                    'permission_type'  => 'always',
                    'permission_field' => '',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 11,
                    'parent_tab'       => 'profil',
                    'content_html'     => '[dgptm-daten-bearbeiten]',
                ],
                [
                    'id'               => 'profil-transaktionen',
                    'label'            => 'Rechnungen',
                    'icon'             => '',
                    'permission_type'  => 'always',
                    'permission_field' => '',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 12,
                    'parent_tab'       => 'profil',
                    'content_html'     => '[zoho_books_transactions]',
                ],
                [
                    'id'               => 'profil-lastschrift',
                    'label'            => 'Lastschrift & Bescheinigung',
                    'icon'             => '',
                    'permission_type'  => 'always',
                    'permission_field' => '',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 13,
                    'parent_tab'       => 'profil',
                    'content_html'     => "<h4>Lastschriftmandat</h4>\n[gcl_formidable]\n\n<h4 style=\"margin-top:20px\">Mitgliedsbescheinigung</h4>\n[webhook_ajax_trigger url=\"https://flow.zoho.eu/20086283718/flow/webhook/incoming?zapikey=1001.61e55251780c1730ee213bfe02d8a192.eb83171de88e8e99371cf264aa47e96c&isdebug=false\" method=\"POST\" user_field=\"zoho_id\" cooldown=\"6\" status_id=\"mgb\" cooldown_message=\"Du hast heute schon eine Bescheinigung angefordert.\"]\n[webhook_status_output id=\"mgb\"]\n\n<h4 style=\"margin-top:20px\">Studierendenstatus</h4>\n[dgptm-studistatus]",
                ],
                [
                    'id'               => 'profil-efn',
                    'label'            => 'EFN-Etiketten',
                    'icon'             => '',
                    'permission_type'  => 'always',
                    'permission_field' => '',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 14,
                    'parent_tab'       => 'profil',
                    'content_html'     => "<h4>EFN-Barcode</h4>\n[efn_barcode_js]\n\n<h4 style=\"margin-top:20px\">EFN-Etiketten drucken</h4>\n[efn_label_sheet]",
                ],
                [
                    'id'               => 'profil-fortbildung',
                    'label'            => 'Fortbildung',
                    'icon'             => '',
                    'permission_type'  => 'always',
                    'permission_field' => '',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 15,
                    'parent_tab'       => 'profil',
                    'content_html'     => "<a href=\"/mitgliedschaft/interner-bereich/fortbildungsnachweis/\" class=\"dgptm-btn dgptm-btn--primary\">Fortbildungsnachweis (inkl. Quiz)</a>\n\n[fobi_nachweis_pruefliste]",
                ],
                // === Other top-level tabs ===
                [
                    'id'               => 'jahrestagung',
                    'label'            => 'Jahrestagung',
                    'icon'             => 'dashicons-calendar-alt',
                    'permission_type'  => 'role',
                    'permission_field' => '',
                    'permission_roles' => ['jahrestagung', 'administrator'],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 20,
                    'parent_tab'       => '',
                    'content_html'     => "<h3>Jahrestagung</h3>\n<p><a href=\"/kiosk-jahrestagung/\">Anwesenheitserfassung</a> | <a href=\"/efn-kiosk/\">EFN Kiosk</a> | <a href=\"/mvv\">Anwesenheitserfassung MVV</a> | <a href=\"/mvvanwesenheit/\">Anwesenheitsliste MVV</a> | <a href=\"/jahrestagungsstreams2025/\">Alle Streams</a> | <a href=\"https://dgptm.sharepoint.com/:f:/g/EiNx__K-Vc9EgK7r3ZkKt1MBtW_4sNB0dxmWeeKVqtBO-Q?e=8WHfLt\" target=\"_blank\">Tagungsbuero (SharePoint)</a></p>",
                ],
                [
                    'id'               => 'mitgliederversammlung',
                    'label'            => 'Mitgliederversammlung',
                    'icon'             => 'dashicons-groups',
                    'permission_type'  => 'acf_field',
                    'permission_field' => 'mitgliederversammlung',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 30,
                    'parent_tab'       => '',
                    'content_html'     => "<h3>Mitgliederversammlung 2025</h3>\n<p>Alle Mitglieder haben die Moeglichkeit alternativ online an der Mitgliederversammlung teilzunehmen.</p>\n[online_abstimmen_button meeting_number=\"82770189111\" kind=\"webinar\" test=\"1\"]\n<p style=\"color:red\">Rot = Nicht online teilnehmen</p>\n<p style=\"color:green\">Gruen = Online teilnehmen</p>\n[online_abstimmen_liste]",
                ],
                [
                    'id'               => 'news',
                    'label'            => 'News & Events',
                    'icon'             => 'dashicons-megaphone',
                    'permission_type'  => 'always',
                    'permission_field' => '',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 40,
                    'parent_tab'       => '',
                    'content_html'     => "<h3>News &amp; Veranstaltungen</h3>\n<h4>Nachrichten fuer Mitglieder</h4>\n[news-modal category=\"intern\" layout=\"list\" title_length=\"100\" show_pubdate=true]\n\n<h4>Veranstaltungen</h4>\n[news-modal category=\"events\" layout=\"list\" sort_field=\"event_start\" sort_dir=\"ASC\" title_length=\"50\"]",
                ],
                [
                    'id'               => 'admin',
                    'label'            => 'Admin & Test',
                    'icon'             => 'dashicons-admin-tools',
                    'permission_type'  => 'acf_field',
                    'permission_field' => 'testbereich',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 50,
                    'parent_tab'       => '',
                    'content_html'     => "<h3>Admin &amp; Testbereich</h3>\n[timeline_manager]\n[gehaltsbarometer_statistik]\n[wissens_bot]\n[herzzentren_benutzer_liste]\n[doi_liste]\n[publikation_list_frontend count=\"5\"]\n[publikation_edit_frontend]\n[list_blaue_seiten]\n[efn_label_sheet default=\"Avery L7160 (63.5x38.1, 3x8)\" show_text=\"yes\"]\n[fortbildung_bestaetigung]\n[events_by_month]",
                ],
                [
                    'id'               => 'abstimmung',
                    'label'            => 'Abstimmung',
                    'icon'             => 'dashicons-yes-alt',
                    'permission_type'  => 'acf_field',
                    'permission_field' => 'mitgliederversammlung',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 60,
                    'parent_tab'       => '',
                    'content_html'     => "<h3>Abstimmungsmanager</h3>\n<p><a href=\"/mitgliedschaft/interner-bereich/abstimmungsmanager/\">Abstimmungsmanager</a> | <a href=\"/mitgliedschaft/interner-bereich/abstimmungsmanager/abstimmungstool-beamer/\" target=\"_blank\">Beameranzeige (Vollbild)</a></p>\n[member_vote]",
                ],
                [
                    'id'               => 'eventtracker',
                    'label'            => 'Eventtracker',
                    'icon'             => 'dashicons-location',
                    'permission_type'  => 'acf_field',
                    'permission_field' => 'eventtracker',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 70,
                    'parent_tab'       => '',
                    'content_html'     => '<h3>Eventtracker</h3>\n[event_tracker]',
                ],
                [
                    'id'               => 'zeitschrift',
                    'label'            => 'Zeitschrift',
                    'icon'             => 'dashicons-book',
                    'permission_type'  => 'acf_field',
                    'permission_field' => 'zeitschriftmanager',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 80,
                    'parent_tab'       => '',
                    'content_html'     => '<h3>Zeitschriftenmanager</h3>\n<p><a href="/mitgliedschaft/interner-bereich/zeitschrift-manager/">Zeitschriftenmanager aufrufen</a></p>',
                ],
                [
                    'id'               => 'gehalt',
                    'label'            => 'Gehaltsbarometer',
                    'icon'             => 'dashicons-chart-bar',
                    'permission_type'  => 'always',
                    'permission_field' => '',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 90,
                    'parent_tab'       => '',
                    'content_html'     => "<h3>Gehaltsbarometer</h3>\n[gehaltsbarometer_popup_guard id=\"37862\"]\n[gehaltsbarometer]\n[gehaltsbarometer_is][gehaltsbarometer_chart][/gehaltsbarometer_is]",
                ],
                [
                    'id'               => 'quiz',
                    'label'            => 'Quizzmaster',
                    'icon'             => 'dashicons-welcome-learn-more',
                    'permission_type'  => 'acf_field',
                    'permission_field' => 'quizz_verwalten',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 100,
                    'parent_tab'       => '',
                    'content_html'     => "<h3>Quizzmaster Area</h3>\n[ays_quiz_frontend_requests]\n[quiz_manager]",
                ],
                [
                    'id'               => 'webinar',
                    'label'            => 'Webinare',
                    'icon'             => 'dashicons-video-alt3',
                    'permission_type'  => 'acf_field',
                    'permission_field' => 'webinar',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 110,
                    'parent_tab'       => '',
                    'content_html'     => '<h3>Webinar-Verwaltung</h3>\n[vimeo_webinar_manager]',
                ],
                [
                    'id'               => 'microsoft',
                    'label'            => 'Microsoft 365',
                    'icon'             => 'dashicons-cloud',
                    'permission_type'  => 'admin',
                    'permission_field' => '',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 120,
                    'parent_tab'       => '',
                    'content_html'     => "<h3>Microsoft 365 Gruppen</h3>\n<p><strong>Achtung:</strong> Hier koennen Mitglieder zu Gruppen hinzugefuegt werden, die in Microsoft 365 definiert sind. Bitte sorgfaeltig verwenden.</p>\n[ms365_group_manager]",
                ],
                [
                    'id'               => 'ebcp',
                    'label'            => 'EBCP',
                    'icon'             => 'dashicons-awards',
                    'permission_type'  => 'acf_field',
                    'permission_field' => 'delegate',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 130,
                    'parent_tab'       => '',
                    'content_html'     => '<h3>EBCP Delegierte</h3>\n[delegierte_liste]',
                ],
                [
                    'id'               => 'checklisten',
                    'label'            => 'Checklisten',
                    'icon'             => 'dashicons-yes',
                    'permission_type'  => 'acf_field',
                    'permission_field' => 'checkliste',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 140,
                    'parent_tab'       => '',
                    'content_html'     => '<h3>Checklisten</h3>\n[clm_active_checklists]',
                ],
                [
                    'id'               => 'umfragen',
                    'label'            => 'Umfragen',
                    'icon'             => 'dashicons-forms',
                    'permission_type'  => 'acf_field',
                    'permission_field' => 'umfragen',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 150,
                    'parent_tab'       => '',
                    'content_html'     => '<h3>Umfragen</h3>\n[dgptm_umfrage_editor]',
                ],
                [
                    'id'               => 'news-editor',
                    'label'            => 'News bearbeiten',
                    'icon'             => 'dashicons-edit',
                    'permission_type'  => 'acf_field',
                    'permission_field' => 'news_schreiben',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 160,
                    'parent_tab'       => '',
                    'content_html'     => '<h3>News & Veranstaltungen bearbeiten</h3>\n<p><a href="/mitgliedschaft/interner-bereich/news-editor/">News erstellen und bearbeiten</a></p>',
                ],
                [
                    'id'               => 'stellenanzeigen',
                    'label'            => 'Stellenanzeigen',
                    'icon'             => 'dashicons-businessperson',
                    'permission_type'  => 'acf_field',
                    'permission_field' => 'stellenanzeigen_anlegen',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 170,
                    'parent_tab'       => '',
                    'content_html'     => '<h3>Stellenanzeigen verwalten</h3>\n[stellenanzeigen_editor]',
                ],
                [
                    'id'               => 'herzzentrum',
                    'label'            => 'Herzzentrum',
                    'icon'             => 'dashicons-heart',
                    'permission_type'  => 'custom_callback',
                    'permission_field' => '',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 180,
                    'parent_tab'       => '',
                    'content_html'     => '<h3>Herzzentrum bearbeiten</h3>\n[hzb_edit_form_link]',
                ],
                [
                    'id'               => 'feedback',
                    'label'            => 'Feedback',
                    'icon'             => 'dashicons-feedback',
                    'permission_type'  => 'always',
                    'permission_field' => '',
                    'permission_roles' => [],
                    'datetime_start'   => '',
                    'datetime_end'     => '',
                    'active'           => true,
                    'order'            => 190,
                    'parent_tab'       => '',
                    'content_html'     => "<h3>Anregungen und Wuensche</h3>\n<p>Wir sind fuer alle Wuensche und Hinweise offen.</p>\n[formidable id=13]",
                ],
            ],
            'settings' => [
                'cache_ttl'       => 900,
                'primary_color'   => '#005792',
                'accent_color'    => '#bd1622',
                'default_tab'     => 'profil',
                'mobile_dropdown' => true,
            ],
        ];
    }

    public function reset_to_defaults() {
        $defaults = $this->get_defaults();
        $this->save_config($defaults);
        return $defaults;
    }
}
