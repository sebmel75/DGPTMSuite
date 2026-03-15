<?php
/**
 * Dashboard Configuration - Tab definitions, wp_options storage
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

    public function get_tab_by_id($tab_id) {
        foreach ($this->get_tabs() as $tab) {
            if ($tab['id'] === $tab_id) {
                return $tab;
            }
        }
        return null;
    }

    public function get_defaults() {
        return [
            'tabs' => [
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
                    'template'         => 'tabs/tab-profil.php',
                ],
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
                    'template'         => 'tabs/tab-jahrestagung.php',
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
                    'template'         => 'tabs/tab-mitgliederversammlung.php',
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
                    'template'         => 'tabs/tab-news.php',
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
                    'template'         => 'tabs/tab-admin.php',
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
                    'template'         => 'tabs/tab-abstimmung.php',
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
                    'template'         => 'tabs/tab-eventtracker.php',
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
                    'template'         => 'tabs/tab-zeitschrift.php',
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
                    'template'         => 'tabs/tab-gehalt.php',
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
                    'template'         => 'tabs/tab-quiz.php',
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
                    'template'         => 'tabs/tab-webinar.php',
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
                    'template'         => 'tabs/tab-microsoft.php',
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
                    'template'         => 'tabs/tab-ebcp.php',
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
                    'template'         => 'tabs/tab-checklisten.php',
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
                    'template'         => 'tabs/tab-umfragen.php',
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
                    'template'         => 'tabs/tab-news-editor.php',
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
                    'template'         => 'tabs/tab-stellenanzeigen.php',
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
                    'template'         => 'tabs/tab-herzzentrum.php',
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
                    'template'         => 'tabs/tab-feedback.php',
                ],
            ],
            'settings' => [
                'cache_ttl'      => 900,
                'primary_color'  => '#005792',
                'accent_color'   => '#bd1622',
                'default_tab'    => 'profil',
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
