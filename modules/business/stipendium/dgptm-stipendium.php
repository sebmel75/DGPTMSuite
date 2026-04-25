<?php
/**
 * Plugin Name: DGPTM Stipendienvergabe
 * Description: Digitales Bewerbungs- und Bewertungsverfahren fuer DGPTM-Stipendien
 * Version: 1.2.0
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('DGPTM_Stipendium')) {

    class DGPTM_Stipendium {

        private static $instance = null;
        private $plugin_path;
        private $plugin_url;
        private $settings;
        private $zoho;
        private $workdrive;
        private $token_manager;
        private $gutachter_form;
        private $orcid_lookup;
        private $vorsitz_dashboard;
        private $bewerbung_manuell;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->plugin_path = plugin_dir_path(__FILE__);
            $this->plugin_url  = plugin_dir_url(__FILE__);

            $this->load_components();

            add_action('init', [$this, 'register_acf_fields']);
        }

        private function load_components() {
            // DB-Installer (immer laden, prueft intern ob Update noetig)
            require_once $this->plugin_path . 'includes/class-token-installer.php';
            DGPTM_Stipendium_Token_Installer::install();

            // Freigabe-Komponente (bereits implementiert)
            require_once $this->plugin_path . 'includes/class-freigabe.php';
            new DGPTM_Stipendium_Freigabe($this->plugin_path, $this->plugin_url);

            // Seeder fuer Freigaben/Kommentare aus dem Diskussionsstand
            require_once $this->plugin_path . 'includes/class-freigabe-seeder.php';
            DGPTM_Stipendium_Freigabe_Seeder::maybe_seed();

            // Einstellungen
            if (file_exists($this->plugin_path . 'includes/class-settings.php')) {
                require_once $this->plugin_path . 'includes/class-settings.php';
                $this->settings = new DGPTM_Stipendium_Settings($this->plugin_path, $this->plugin_url);
            }

            // Zoho CRM API (nur laden wenn crm-abruf verfuegbar)
            if (class_exists('DGPTM_CRM_Abruf') || function_exists('dgptm_get_zoho_token')) {
                if (file_exists($this->plugin_path . 'includes/class-zoho-stipendium.php')) {
                    require_once $this->plugin_path . 'includes/class-zoho-stipendium.php';
                    $this->zoho = new DGPTM_Stipendium_Zoho($this->settings);
                }

                if (file_exists($this->plugin_path . 'includes/class-workdrive.php')) {
                    require_once $this->plugin_path . 'includes/class-workdrive.php';
                    $this->workdrive = new DGPTM_Stipendium_WorkDrive($this->settings);
                }
            }

            // Token-Manager (braucht DB-Tabelle)
            require_once $this->plugin_path . 'includes/class-gutachter-token.php';
            $this->token_manager = new DGPTM_Stipendium_Gutachter_Token();

            // Mail-Templates
            require_once $this->plugin_path . 'includes/class-mail-templates.php';

            // ORCID-Lookup
            require_once $this->plugin_path . 'includes/class-orcid-lookup.php';
            $this->orcid_lookup = new DGPTM_Stipendium_ORCID_Lookup();

            // Gutachter-Bewertungsbogen (Shortcode + AJAX, auch fuer nicht-eingeloggte)
            require_once $this->plugin_path . 'includes/class-gutachter-form.php';
            $this->gutachter_form = new DGPTM_Stipendium_Gutachter_Form(
                $this->plugin_path,
                $this->plugin_url,
                $this->token_manager,
                $this->zoho
            );

            // Manuell eingepflegte Bewerbungen (lokale Tabelle)
            require_once $this->plugin_path . 'includes/class-bewerbung-manuell.php';
            $this->bewerbung_manuell = new DGPTM_Stipendium_Bewerbung_Manuell();

            // Gutachter-Stammdaten-Pool
            require_once $this->plugin_path . 'includes/class-gutachter-pool.php';
            new DGPTM_Stipendium_Gutachter_Pool();

            // Vorsitzenden-Dashboard
            require_once $this->plugin_path . 'includes/class-vorsitz-dashboard.php';
            $this->vorsitz_dashboard = new DGPTM_Stipendium_Vorsitz_Dashboard(
                $this->plugin_path,
                $this->plugin_url,
                $this->settings,
                $this->zoho,
                $this->token_manager
            );

            // Dashboard-Tab Registrierung (nur wenn Dashboard-Modul aktiv)
            if (class_exists('DGPTM_Mitglieder_Dashboard') || shortcode_exists('dgptm_dashboard')) {
                if (file_exists($this->plugin_path . 'includes/class-dashboard-tab.php')) {
                    require_once $this->plugin_path . 'includes/class-dashboard-tab.php';
                    new DGPTM_Stipendium_Dashboard_Tab(
                        $this->plugin_path,
                        $this->plugin_url,
                        $this->settings,
                        $this->vorsitz_dashboard
                    );
                }
            }
        }

        /**
         * ACF-Felder fuer Stipendiumsrat-Berechtigung registrieren.
         */
        public function register_acf_fields() {
            if (!function_exists('acf_add_local_field_group')) return;

            acf_add_local_field_group([
                'key'      => 'group_stipendiumsrat',
                'title'    => 'Stipendiumsrat',
                'fields'   => [
                    [
                        'key'           => 'field_stipendiumsrat_mitglied',
                        'label'         => 'Mitglied im Stipendiumsrat',
                        'name'          => 'stipendiumsrat_mitglied',
                        'type'          => 'true_false',
                        'default_value' => 0,
                        'ui'            => 1,
                        'instructions'  => 'Aktivieren, wenn diese Person dem Stipendiumsrat angehoert.',
                    ],
                    [
                        'key'           => 'field_stipendiumsrat_vorsitz',
                        'label'         => 'Vorsitzende/r des Stipendiumsrats',
                        'name'          => 'stipendiumsrat_vorsitz',
                        'type'          => 'true_false',
                        'default_value' => 0,
                        'ui'            => 1,
                        'instructions'  => 'Aktivieren fuer den/die Vorsitzende/n. Hat Zugang zur Auswertung und Freigabe.',
                    ],
                ],
                'location' => [
                    [
                        [
                            'param'    => 'user_form',
                            'operator' => '==',
                            'value'    => 'all',
                        ],
                    ],
                ],
                'menu_order' => 50,
            ]);
        }

        public function get_path() { return $this->plugin_path; }
        public function get_url()  { return $this->plugin_url; }
        public function get_settings() { return $this->settings; }
        public function get_zoho() { return $this->zoho; }
        public function get_workdrive() { return $this->workdrive; }
        public function get_token_manager() { return $this->token_manager; }
        public function get_vorsitz_dashboard() { return $this->vorsitz_dashboard; }
    }
}

if (!isset($GLOBALS['dgptm_stipendium_initialized'])) {
    $GLOBALS['dgptm_stipendium_initialized'] = true;
    DGPTM_Stipendium::get_instance();
}
