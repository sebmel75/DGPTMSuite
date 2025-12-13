<?php
/**
 * Plugin Name: DGPTM - Daten bearbeiten
 * Plugin URI:  https://dgptm.de
 * Description: Member data editing form with Zoho CRM synchronization
 * Version:     1.0.0
 * Author:      Sebastian Melzer
 * Author URI:  https://dgptm.de
 * License:     GPL2
 */

if (!defined('ABSPATH')) {
    exit;
}

// GoCardless API Token - aus wp-config.php oder direkt hier
if (!defined('DGPTM_GOCARDLESS_TOKEN')) {
    define('DGPTM_GOCARDLESS_TOKEN', 'REMOVED_TOKEN_CONFIGURE_IN_SETTINGS');
}

// Prevent class redeclaration
if (!class_exists('DGPTM_Daten_Bearbeiten')) {

    class DGPTM_Daten_Bearbeiten {

        private static $instance = null;
        private $plugin_path;
        private $plugin_url;
        private $version = '1.1.0';

        // Cache-Konstanten
        const CACHE_KEY_CLINICS = 'dgptm_clinics_cache';
        const CACHE_DURATION = DAY_IN_SECONDS; // 24 Stunden

        /**
         * Get singleton instance
         */
        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        /**
         * Private constructor
         */
        private function __construct() {
            $this->plugin_path = plugin_dir_path(__FILE__);
            $this->plugin_url = plugin_dir_url(__FILE__);

            $this->init_hooks();
        }

        /**
         * Initialize WordPress hooks
         */
        private function init_hooks() {
            // Enqueue assets
            add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

            // AJAX handlers
            add_action('wp_ajax_dgptm_load_member_data', [$this, 'ajax_load_member_data']);
            add_action('wp_ajax_dgptm_update_member_data', [$this, 'ajax_update_member_data']);
            add_action('wp_ajax_dgptm_load_accounts', [$this, 'ajax_load_accounts']);
            add_action('wp_ajax_dgptm_load_clinics', [$this, 'ajax_load_clinics']);
            add_action('wp_ajax_dgptm_refresh_clinics_cache', [$this, 'ajax_refresh_clinics_cache']);
            add_action('wp_ajax_dgptm_load_student_status', [$this, 'ajax_load_student_status']);
            add_action('wp_ajax_dgptm_upload_student_certificate', [$this, 'ajax_upload_student_certificate']);

            // Täglicher Cron-Job zum Aktualisieren des Clinics-Cache
            add_action('dgptm_daily_clinics_refresh', [$this, 'refresh_clinics_cache']);
            if (!wp_next_scheduled('dgptm_daily_clinics_refresh')) {
                wp_schedule_event(time(), 'daily', 'dgptm_daily_clinics_refresh');
            }

            // Shortcodes
            add_shortcode('dgptm-daten-bearbeiten', [$this, 'render_edit_form']);
            add_shortcode('dgptm-studistatus', [$this, 'render_student_status_form']);
            add_shortcode('dgptm-studistatus-banner', [$this, 'render_student_status_banner']);
        }

        /**
         * Enqueue frontend assets
         */
        public function enqueue_frontend_assets() {
            // Only enqueue on pages with shortcode
            global $post;
            if (!is_a($post, 'WP_Post') ||
                (!has_shortcode($post->post_content, 'dgptm-daten-bearbeiten') &&
                 !has_shortcode($post->post_content, 'dgptm-studistatus') &&
                 !has_shortcode($post->post_content, 'dgptm-studistatus-banner'))) {
                return;
            }

            wp_enqueue_style(
                'dgptm-daten-bearbeiten',
                $this->plugin_url . 'assets/css/style.css',
                [],
                $this->version
            );

            wp_enqueue_script(
                'dgptm-daten-bearbeiten',
                $this->plugin_url . 'assets/js/script.js',
                ['jquery'],
                $this->version,
                true
            );

            wp_localize_script('dgptm-daten-bearbeiten', 'dgptmDatenBearbeiten', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dgptm_daten_bearbeiten_nonce'),
                'redirectUrl' => 'https://perfusiologie.de/mitgliedschaft/interner-bereich/',
                'strings' => [
                    'loading' => 'Daten werden geladen...',
                    'saving' => 'Daten werden gespeichert...',
                    'success' => 'Daten erfolgreich gespeichert!',
                    'error' => 'Es ist ein Fehler aufgetreten. Bitte versuchen Sie es erneut.',
                ],
            ]);
        }

        /**
         * Render edit form shortcode
         */
        public function render_edit_form($atts) {
            if (!is_user_logged_in()) {
                return '<p>Sie müssen angemeldet sein, um Ihre Daten zu bearbeiten.</p>';
            }

            ob_start();
            include $this->plugin_path . 'templates/edit-form.php';
            return ob_get_clean();
        }

        /**
         * AJAX: Load member data from Zoho CRM
         */
        public function ajax_load_member_data() {
            $this->log('AJAX: Load member data called');

            check_ajax_referer('dgptm_daten_bearbeiten_nonce', 'nonce');

            if (!is_user_logged_in()) {
                $this->log('ERROR: User not logged in');
                wp_send_json_error(['message' => 'Nicht angemeldet']);
            }

            $user_id = get_current_user_id();
            $this->log('User ID: ' . $user_id);

            $zoho_id = get_user_meta($user_id, 'zoho_id', true);
            $this->log('Zoho ID from user meta: ' . ($zoho_id ? $zoho_id : 'EMPTY'));

            if (empty($zoho_id)) {
                $this->log('ERROR: No Zoho ID found for user ' . $user_id);
                wp_send_json_error(['message' => 'Keine Zoho ID gefunden. Bitte kontaktieren Sie die Geschäftsstelle.']);
            }

            // Get OAuth token
            $token = $this->get_oauth_token();
            if (!$token) {
                $this->log('ERROR: No OAuth token available');
                wp_send_json_error(['message' => 'OAuth-Token nicht verfügbar. Bitte aktivieren Sie das Mitgliedsantrag- oder CRM-Abruf-Modul.']);
            }

            $this->log('OAuth token obtained successfully');

            // Fetch contact data from Zoho CRM
            $contact_data = $this->fetch_contact_from_crm($zoho_id, $token);

            if (!$contact_data) {
                $this->log('ERROR: Contact not found in CRM for Zoho ID: ' . $zoho_id);
                wp_send_json_error(['message' => 'Kontakt nicht gefunden in Zoho CRM (ID: ' . $zoho_id . ')']);
            }

            $this->log('Contact data fetched successfully');

            // Map CRM data to form fields
            $form_data = $this->map_crm_to_form_data($contact_data);

            $this->log('Data mapped successfully, sending response');
            wp_send_json_success(['data' => $form_data]);
        }

        /**
         * AJAX: Update member data in Zoho CRM
         */
        public function ajax_update_member_data() {
            check_ajax_referer('dgptm_daten_bearbeiten_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Nicht angemeldet']);
            }

            $user_id = get_current_user_id();
            $zoho_id = get_user_meta($user_id, 'zoho_id', true);

            if (empty($zoho_id)) {
                wp_send_json_error(['message' => 'Keine Zoho ID gefunden']);
            }

            // Get OAuth token
            $token = $this->get_oauth_token();
            if (!$token) {
                wp_send_json_error(['message' => 'OAuth-Token nicht verfügbar']);
            }

            // Log raw POST data
            $this->log('Raw POST data: ' . wp_json_encode($_POST));

            // Sanitize form data
            $form_data = $this->sanitize_form_data($_POST);

            $this->log('Sanitized form data: ' . wp_json_encode($form_data));

            // Update user email in WordPress if changed
            $new_email = sanitize_email($form_data['mail1']);
            $user = wp_get_current_user();

            if ($new_email !== $user->user_email) {
                wp_update_user([
                    'ID' => $user_id,
                    'user_email' => $new_email,
                ]);

                // Also update in user meta
                update_user_meta($user_id, 'billing_email', $new_email);
            }

            // Map form data to CRM fields
            $crm_data = $this->map_form_to_crm_data($form_data);

            $this->log('CRM data to send: ' . wp_json_encode($crm_data));

            // Update contact in Zoho CRM
            $success = $this->update_contact_in_crm($zoho_id, $crm_data, $token);

            // Handle bank change request
            if (!empty($_POST['bank_change_requested']) && $_POST['bank_change_requested'] === 'true') {
                $this->log('Bank change requested by user');

                $bank_data = [
                    'vorname' => sanitize_text_field($_POST['bank_vorname'] ?? ''),
                    'nachname' => sanitize_text_field($_POST['bank_nachname'] ?? ''),
                    'iban' => sanitize_text_field($_POST['bank_iban'] ?? ''),
                ];

                // Get GoCardless Customer ID from Zoho CRM
                $contact_data = $this->fetch_contact_from_crm($zoho_id, $token);
                $gocardless_id = '';

                if ($contact_data && isset($contact_data['GoCardlessID'])) {
                    $gocardless_id = $contact_data['GoCardlessID'];
                } else {
                    // Fallback to user meta
                    $gocardless_id = get_user_meta($user_id, 'gocardless_customer_id', true);
                }

                $this->log('GoCardless Customer ID: ' . ($gocardless_id ?: 'EMPTY'));

                if (!empty($gocardless_id)) {
                    // Cancel GoCardless mandate
                    $cancel_result = $this->cancel_gocardless_mandate($gocardless_id);
                    $this->log('Mandate cancellation result: ' . ($cancel_result ? 'SUCCESS' : 'FAILED'));
                }

                // Notify admin
                $member_data = [
                    'vorname' => $contact_data['First_Name'] ?? '',
                    'nachname' => $contact_data['Last_Name'] ?? '',
                    'email' => $user->user_email,
                    'zoho_id' => $zoho_id,
                ];
                $this->notify_admin_bank_change($member_data, $bank_data);
            }

            if ($success) {
                wp_send_json_success(['message' => 'Daten erfolgreich aktualisiert']);
            } else {
                wp_send_json_error(['message' => 'Fehler beim Aktualisieren der Daten']);
            }
        }

        /**
         * Get OAuth token from mitgliedsantrag or crm-abruf module
         */
        private function get_oauth_token() {
            $this->log('Attempting to get OAuth token...');

            // Try to get token from mitgliedsantrag module (with automatic refresh)
            if (class_exists('DGPTM_Mitgliedsantrag')) {
                $this->log('DGPTM_Mitgliedsantrag class exists');
                $mitgliedsantrag = DGPTM_Mitgliedsantrag::get_instance();

                if (method_exists($mitgliedsantrag, 'get_access_token')) {
                    $this->log('get_access_token method exists, calling it...');
                    $token = $mitgliedsantrag->get_access_token();

                    if ($token) {
                        $this->log('Token obtained from mitgliedsantrag module (automatically refreshed if needed)');
                        return $token;
                    } else {
                        $this->log('mitgliedsantrag returned empty token');
                    }
                } else {
                    $this->log('get_access_token method does not exist in mitgliedsantrag');
                }
            } else {
                $this->log('DGPTM_Mitgliedsantrag class not found');
            }

            // Try crm-abruf module
            $this->log('Checking crm-abruf module...');
            if (class_exists('DGPTM_Zoho_CRM_Hardened')) {
                $this->log('DGPTM_Zoho_CRM_Hardened class exists');
                $crm_abruf = DGPTM_Zoho_CRM_Hardened::get_instance();
                if (method_exists($crm_abruf, 'get_access_token')) {
                    $this->log('get_access_token method exists, calling it...');
                    $token = $crm_abruf->get_access_token();
                    if ($token) {
                        $this->log('Token obtained from crm-abruf module');
                        return $token;
                    } else {
                        $this->log('crm-abruf returned empty token');
                    }
                } else {
                    $this->log('get_access_token method does not exist');
                }
            } else {
                $this->log('DGPTM_Zoho_CRM_Hardened class not found');
            }

            $this->log('ERROR: No OAuth token available from any source');
            return false;
        }

        /**
         * Fetch contact from Zoho CRM
         */
        private function fetch_contact_from_crm($zoho_id, $token) {
            $response = wp_remote_get(
                'https://www.zohoapis.eu/crm/v2/Contacts/' . $zoho_id,
                [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $token,
                    ],
                    'timeout' => 30
                ]
            );

            if (is_wp_error($response)) {
                $this->log('ERROR: Failed to fetch contact: ' . $response->get_error_message());
                return false;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($http_code === 200 && isset($body['data'][0])) {
                return $body['data'][0];
            }

            return false;
        }

        /**
         * Update contact in Zoho CRM
         */
        private function update_contact_in_crm($zoho_id, $crm_data, $token) {
            $response = wp_remote_request(
                'https://www.zohoapis.eu/crm/v2/Contacts/' . $zoho_id,
                [
                    'method' => 'PUT',
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $token,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => wp_json_encode(['data' => [$crm_data]]),
                    'timeout' => 30
                ]
            );

            if (is_wp_error($response)) {
                $this->log('ERROR: Failed to update contact: ' . $response->get_error_message());
                return false;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            $this->log('Update response HTTP Code: ' . $http_code);
            $this->log('Update response: ' . wp_json_encode($body));

            return ($http_code >= 200 && $http_code < 300);
        }

        /**
         * Map CRM data to form fields
         */
        private function map_crm_to_form_data($crm_data) {
            return [
                // Read-only fields
                'vorname' => $crm_data['First_Name'] ?? '',
                'nachname' => $crm_data['Last_Name'] ?? '',
                'geburtsdatum' => $crm_data['Date_of_Birth'] ?? '',

                // Editable fields
                'ansprache' => $crm_data['greeting'] ?? 'Liebe(r)',
                'akad_titel' => $crm_data['Academic_Title'] ?? '',
                'titel_nach_name' => $crm_data['Title_After_The_Name'] ?? '',

                // Email addresses
                'mail1' => $crm_data['Email'] ?? '',
                'mail2' => $crm_data['Secondary_Email'] ?? '',
                'mail3' => $crm_data['Third_Email'] ?? '',

                // Address (using Mailing_ prefix)
                'strasse' => $crm_data['Mailing_Street'] ?? '',
                'adresszusatz' => $crm_data['Mailing_Street_Additional'] ?? '',
                'plz' => $crm_data['Mailing_Zip'] ?? '',
                'ort' => $crm_data['Mailing_City'] ?? '',
                'land' => $crm_data['Mailing_Country'] ?? 'Deutschland',

                // Phone numbers
                'telefon' => $crm_data['Phone'] ?? '',
                'mobil' => $crm_data['Mobile'] ?? '',
                'diensttelefon' => $crm_data['Work_Phone'] ?? '',

                // Journal preferences
                'journal_post' => ($crm_data['journal_post'] ?? false) ? 'true' : 'false',
                'journal_mail' => ($crm_data['journal_mail'] ?? false) ? 'true' : 'false',

                // Employer (can be lookup object or string)
                'employer' => $this->extract_employer_name($crm_data['employer'] ?? ''),
                'employer_id' => $this->extract_employer_id($crm_data['employer'] ?? ''),
                'employer_free' => $crm_data['Employer_free'] ?? '',
                'temporary_work' => $crm_data['temporary_work'] ?? '',

                // Status (read-only)
                'status' => $crm_data['Contact_Status'] ?? '',

                // DGPTM Account Info
                'dgptm_mail' => $crm_data['DGPTMMail'] ?? '',
                'hat_postfach' => $crm_data['hatPostfach'] ?? false,
            ];
        }

        /**
         * Map form data to CRM fields
         */
        private function map_form_to_crm_data($form_data) {
            // Helper function for boolean parsing
            $parse_bool = function($value) {
                return ($value === true || $value === 'true' || $value === '1' || $value === 1);
            };

            $crm_data = [
                'greeting' => $form_data['ansprache'] ?? '',
                'Academic_Title' => $form_data['akad_titel'] ?? '',
                'Title_After_The_Name' => $form_data['titel_nach_name'] ?? '',

                'Email' => $form_data['mail1'] ?? '',
                'Secondary_Email' => $form_data['mail2'] ?? '',
                'Third_Email' => $form_data['mail3'] ?? '',

                'Mailing_Street' => $form_data['strasse'] ?? '',
                'Mailing_Street_Additional' => $form_data['adresszusatz'] ?? '',
                'Mailing_Zip' => $form_data['plz'] ?? '',
                'Mailing_City' => $form_data['ort'] ?? '',
                'Mailing_Country' => $form_data['land'] ?? 'Deutschland',

                'Phone' => $form_data['telefon'] ?? '',
                'Mobile' => $form_data['mobil'] ?? '',
                'Work_Phone' => $form_data['diensttelefon'] ?? '',

                'journal_post' => $parse_bool($form_data['journal_post'] ?? false),
                'journal_mail' => $parse_bool($form_data['journal_mail'] ?? false),

                'temporary_work' => $form_data['temporary_work'] ?? '',
            ];

            // Handle employer: if manual entry, use Employer_free and set employer to null
            // Otherwise use employer lookup
            $is_manual = ($form_data['is_manual_employer'] === 'true' || $form_data['is_manual_employer'] === true);

            $this->log('Employer mapping - is_manual: ' . ($is_manual ? 'YES' : 'NO'));
            $this->log('Employer mapping - employer: ' . ($form_data['employer'] ?? 'EMPTY'));
            $this->log('Employer mapping - employer_id: ' . ($form_data['employer_id'] ?? 'EMPTY'));
            $this->log('Employer mapping - is_manual_employer value: ' . ($form_data['is_manual_employer'] ?? 'NOT SET'));

            if ($is_manual) {
                // Manual entry: write to Employer_free, set employer to null
                $crm_data['Employer_free'] = $form_data['employer'] ?? '';
                $crm_data['employer'] = null;
                $this->log('→ Using MANUAL employer, writing to Employer_free: "' . ($form_data['employer'] ?? '') . '"');
                $this->log('→ Setting employer lookup to NULL');
            } else {
                // Lookup entry: set employer ID, clear Employer_free
                if (!empty($form_data['employer_id'])) {
                    $crm_data['employer'] = $form_data['employer_id'];
                    $crm_data['Employer_free'] = '';
                    $this->log('→ Using LOOKUP employer, writing ID to employer: "' . $form_data['employer_id'] . '"');
                    $this->log('→ Clearing Employer_free');
                } else {
                    // No employer selected
                    $crm_data['employer'] = null;
                    $crm_data['Employer_free'] = '';
                    $this->log('→ NO employer selected, setting both to NULL/empty');
                }
            }

            return $crm_data;
        }

        /**
         * Sanitize form data
         */
        private function sanitize_form_data($post_data) {
            return [
                'ansprache' => sanitize_text_field($post_data['ansprache'] ?? ''),
                'akad_titel' => sanitize_text_field($post_data['akad_titel'] ?? ''),
                'titel_nach_name' => sanitize_text_field($post_data['titel_nach_name'] ?? ''),

                'mail1' => sanitize_email($post_data['mail1'] ?? ''),
                'mail2' => sanitize_email($post_data['mail2'] ?? ''),
                'mail3' => sanitize_email($post_data['mail3'] ?? ''),

                'strasse' => sanitize_text_field($post_data['strasse'] ?? ''),
                'adresszusatz' => sanitize_text_field($post_data['adresszusatz'] ?? ''),
                'plz' => sanitize_text_field($post_data['plz'] ?? ''),
                'ort' => sanitize_text_field($post_data['ort'] ?? ''),
                'land' => sanitize_text_field($post_data['land'] ?? 'Deutschland'),

                'telefon' => sanitize_text_field($post_data['telefon'] ?? ''),
                'mobil' => sanitize_text_field($post_data['mobil'] ?? ''),
                'diensttelefon' => sanitize_text_field($post_data['diensttelefon'] ?? ''),

                'journal_post' => sanitize_text_field($post_data['journal_post'] ?? 'false'),
                'journal_mail' => sanitize_text_field($post_data['journal_mail'] ?? 'false'),

                'employer' => sanitize_text_field($post_data['employer'] ?? ''),
                'employer_id' => sanitize_text_field($post_data['employer_id'] ?? ''),
                'is_manual_employer' => sanitize_text_field($post_data['is_manual_employer'] ?? ''),
                'temporary_work' => sanitize_text_field($post_data['temporary_work'] ?? ''),
            ];
        }

        /**
         * AJAX: Search accounts in Zoho CRM
         */
        public function ajax_load_accounts() {
            check_ajax_referer('dgptm_daten_bearbeiten_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Nicht angemeldet']);
            }

            $search_term = sanitize_text_field($_POST['search'] ?? '');

            if (empty($search_term) || strlen($search_term) < 2) {
                wp_send_json_error(['message' => 'Bitte geben Sie mindestens 2 Zeichen ein']);
            }

            // Get OAuth token
            $token = $this->get_oauth_token();
            if (!$token) {
                wp_send_json_error(['message' => 'OAuth-Token nicht verfügbar']);
            }

            // Search accounts in Zoho CRM
            $accounts = $this->search_accounts($token, $search_term);

            if ($accounts === false) {
                wp_send_json_error(['message' => 'Fehler beim Suchen der Arbeitgeber']);
            }

            wp_send_json_success(['accounts' => $accounts]);
        }

        /**
         * AJAX: Load clinic accounts from Zoho CRM (mit Cache)
         */
        public function ajax_load_clinics() {
            check_ajax_referer('dgptm_daten_bearbeiten_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Nicht angemeldet']);
            }

            // Versuche aus Cache zu laden
            $clinics = $this->get_cached_clinics();

            if ($clinics === false) {
                wp_send_json_error(['message' => 'Fehler beim Laden der Kliniken']);
            }

            // Prüfe ob Cache aktuell ist
            $cache_time = get_transient(self::CACHE_KEY_CLINICS . '_timestamp');
            $cache_age = $cache_time ? (time() - $cache_time) : 0;

            wp_send_json_success([
                'clinics' => $clinics,
                'cached' => true,
                'cache_age_hours' => round($cache_age / 3600, 1)
            ]);
        }

        /**
         * AJAX: Manuelles Aktualisieren des Clinics-Cache
         */
        public function ajax_refresh_clinics_cache() {
            check_ajax_referer('dgptm_daten_bearbeiten_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Nicht angemeldet']);
            }

            $clinics = $this->refresh_clinics_cache();

            if ($clinics === false) {
                wp_send_json_error(['message' => 'Fehler beim Aktualisieren der Kliniken']);
            }

            wp_send_json_success([
                'clinics' => $clinics,
                'message' => 'Kliniken-Liste erfolgreich aktualisiert'
            ]);
        }

        /**
         * Search accounts in Zoho CRM by name
         */
        private function search_accounts($token, $search_term) {
            // Use Zoho Search API
            $url = 'https://www.zohoapis.eu/crm/v2/Accounts/search?criteria=(Account_Name:starts_with:' . urlencode($search_term) . ')&per_page=50';

            $response = wp_remote_get(
                $url,
                [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $token,
                    ],
                    'timeout' => 30
                ]
            );

            if (is_wp_error($response)) {
                $this->log('ERROR: Failed to search accounts: ' . $response->get_error_message());
                return false;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($http_code === 204) {
                // No results found
                return [];
            }

            if ($http_code !== 200 || !isset($body['data'])) {
                $this->log('ERROR: Invalid response when searching accounts (HTTP ' . $http_code . ')');
                return false;
            }

            $accounts = [];
            foreach ($body['data'] as $account) {
                $accounts[] = [
                    'id' => $account['id'],
                    'name' => $account['Account_Name'] ?? 'Unbekannt',
                    'industry' => $account['Industry'] ?? '',
                ];
            }

            // Sort accounts alphabetically by name
            usort($accounts, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });

            $this->log('Found ' . count($accounts) . ' accounts matching "' . $search_term . '"');

            return $accounts;
        }

        /**
         * Fetch all accounts from Zoho CRM, optionally filtered by Industry
         */
        private function fetch_all_accounts($token, $industry_filter = null) {
            $all_accounts = [];
            $page = 1;
            $per_page = 200;

            do {
                $url = 'https://www.zohoapis.eu/crm/v2/Accounts?per_page=' . $per_page . '&page=' . $page;

                $response = wp_remote_get(
                    $url,
                    [
                        'headers' => [
                            'Authorization' => 'Zoho-oauthtoken ' . $token,
                        ],
                        'timeout' => 30
                    ]
                );

                if (is_wp_error($response)) {
                    $this->log('ERROR: Failed to fetch accounts: ' . $response->get_error_message());
                    return false;
                }

                $http_code = wp_remote_retrieve_response_code($response);
                $body = json_decode(wp_remote_retrieve_body($response), true);

                if ($http_code !== 200 || !isset($body['data'])) {
                    $this->log('ERROR: Invalid response when fetching accounts (HTTP ' . $http_code . ')');
                    return false;
                }

                foreach ($body['data'] as $account) {
                    // Filter by Industry if specified
                    if ($industry_filter && ($account['Industry'] ?? '') !== $industry_filter) {
                        continue;
                    }

                    $all_accounts[] = [
                        'id' => $account['id'],
                        'name' => $account['Account_Name'] ?? 'Unbekannt',
                        'industry' => $account['Industry'] ?? '',
                    ];
                }

                $has_more = isset($body['info']['more_records']) && $body['info']['more_records'];
                $page++;

            } while ($has_more);

            // Sort accounts alphabetically by name
            usort($all_accounts, function($a, $b) {
                return strcmp($a['name'], $b['name']);
            });

            $this->log('Fetched ' . count($all_accounts) . ' accounts' . ($industry_filter ? ' (Industry: ' . $industry_filter . ')' : ''));

            return $all_accounts;
        }

        /**
         * Hole Clinics aus Cache oder lade sie neu
         *
         * @return array|false Array mit Clinics oder false bei Fehler
         */
        private function get_cached_clinics() {
            // Versuche aus Cache zu laden
            $cached_clinics = get_transient(self::CACHE_KEY_CLINICS);

            if ($cached_clinics !== false) {
                $this->log('Clinics aus Cache geladen (' . count($cached_clinics) . ' Einträge)');
                return $cached_clinics;
            }

            // Cache ist leer oder abgelaufen - neu laden
            $this->log('Clinics-Cache leer oder abgelaufen - lade neu von Zoho CRM');
            return $this->refresh_clinics_cache();
        }

        /**
         * Aktualisiere den Clinics-Cache
         *
         * @return array|false Array mit Clinics oder false bei Fehler
         */
        public function refresh_clinics_cache() {
            // Get OAuth token
            $token = $this->get_oauth_token();
            if (!$token) {
                $this->log('ERROR: Kein OAuth-Token verfügbar für Clinics-Cache-Refresh');
                return false;
            }

            // Fetch clinic accounts from Zoho CRM
            $clinics = $this->fetch_all_accounts($token, 'Klinik');

            if ($clinics === false) {
                $this->log('ERROR: Fehler beim Laden der Kliniken für Cache');
                return false;
            }

            // Speichere im Cache (24 Stunden)
            set_transient(self::CACHE_KEY_CLINICS, $clinics, self::CACHE_DURATION);
            set_transient(self::CACHE_KEY_CLINICS . '_timestamp', time(), self::CACHE_DURATION);

            $this->log('Clinics-Cache aktualisiert: ' . count($clinics) . ' Kliniken gespeichert');

            return $clinics;
        }

        /**
         * Extract employer name from employer field (can be lookup object or string)
         */
        private function extract_employer_name($employer_data) {
            if (empty($employer_data)) {
                $this->log('Employer data is empty');
                return '';
            }

            $this->log('Employer data type: ' . gettype($employer_data));
            $this->log('Employer data: ' . wp_json_encode($employer_data));

            // If it's an array/object with 'name' field (lookup)
            if (is_array($employer_data) && isset($employer_data['name'])) {
                $this->log('Extracted employer name: ' . $employer_data['name']);
                return $employer_data['name'];
            }

            // If it's a string
            if (is_string($employer_data)) {
                $this->log('Employer is string: ' . $employer_data);
                return $employer_data;
            }

            $this->log('Could not extract employer name');
            return '';
        }

        /**
         * Extract employer ID from employer field (can be lookup object or string)
         */
        private function extract_employer_id($employer_data) {
            if (empty($employer_data)) {
                return '';
            }

            $this->log('Extracting employer ID from: ' . wp_json_encode($employer_data));

            // If it's an array/object with 'id' field (lookup)
            if (is_array($employer_data) && isset($employer_data['id'])) {
                $this->log('Extracted employer ID: ' . $employer_data['id']);
                return $employer_data['id'];
            }

            return '';
        }

        /**
         * Cancel GoCardless mandate
         */
        private function cancel_gocardless_mandate($customer_id) {
            $token = DGPTM_GOCARDLESS_TOKEN;

            if (empty($token)) {
                $this->log('ERROR: GoCardless Token nicht konfiguriert');
                return false;
            }

            $this->log('Fetching mandates for customer: ' . $customer_id);

            // 1. Mandate des Kunden abrufen
            $response = wp_remote_get(
                'https://api.gocardless.com/mandates?customer=' . $customer_id,
                [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $token,
                        'GoCardless-Version' => '2015-07-06',
                        'Accept' => 'application/json',
                    ],
                    'timeout' => 30
                ]
            );

            if (is_wp_error($response)) {
                $this->log('ERROR: Failed to fetch mandates: ' . $response->get_error_message());
                return false;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            $this->log('Mandates response HTTP ' . $http_code . ': ' . wp_json_encode($body));

            if ($http_code !== 200 || !isset($body['mandates'])) {
                $this->log('ERROR: Invalid mandates response');
                return false;
            }

            $mandates = $body['mandates'];
            $cancelled_count = 0;

            // 2. Jedes aktive Mandate canceln
            foreach ($mandates as $mandate) {
                $mandate_id = $mandate['id'];
                $status = $mandate['status'] ?? '';

                $this->log('Processing mandate ' . $mandate_id . ' with status: ' . $status);

                // Nur aktive Mandate canceln
                if ($status === 'active' || $status === 'pending_customer_approval' || $status === 'pending_submission') {
                    $cancel_response = wp_remote_post(
                        'https://api.gocardless.com/mandates/' . $mandate_id . '/actions/cancel',
                        [
                            'headers' => [
                                'Authorization' => 'Bearer ' . $token,
                                'GoCardless-Version' => '2015-07-06',
                                'Content-Type' => 'application/json',
                            ],
                            'body' => wp_json_encode([
                                'data' => [
                                    'metadata' => [
                                        'cancelled_reason' => 'Bankdaten-Änderung durch Mitglied'
                                    ]
                                ]
                            ]),
                            'timeout' => 30
                        ]
                    );

                    if (is_wp_error($cancel_response)) {
                        $this->log('ERROR: Failed to cancel mandate ' . $mandate_id . ': ' . $cancel_response->get_error_message());
                        continue;
                    }

                    $cancel_code = wp_remote_retrieve_response_code($cancel_response);
                    $cancel_body = wp_remote_retrieve_body($cancel_response);

                    $this->log('Cancel mandate ' . $mandate_id . ' response HTTP ' . $cancel_code . ': ' . $cancel_body);

                    if ($cancel_code >= 200 && $cancel_code < 300) {
                        $cancelled_count++;
                    }
                } else {
                    $this->log('Skipping mandate ' . $mandate_id . ' - already ' . $status);
                }
            }

            $this->log('Cancelled ' . $cancelled_count . ' mandate(s) for customer ' . $customer_id);

            return $cancelled_count > 0;
        }

        /**
         * Admin über Bankdatenänderung informieren
         */
        private function notify_admin_bank_change($member_data, $new_bank_data) {
            $admin_email = get_option('admin_email');
            $subject = 'Bankdaten-Änderung: ' . $member_data['vorname'] . ' ' . $member_data['nachname'];

            $message = "Ein Mitglied möchte seine Bankdaten ändern:\n\n";
            $message .= "Mitglied: {$member_data['vorname']} {$member_data['nachname']}\n";
            $message .= "E-Mail: {$member_data['email']}\n";
            $message .= "Zoho ID: {$member_data['zoho_id']}\n\n";
            $message .= "Neue Bankdaten:\n";
            $message .= "Kontoinhaber: {$new_bank_data['vorname']} {$new_bank_data['nachname']}\n";
            $message .= "IBAN: {$new_bank_data['iban']}\n\n";
            $message .= "Das GoCardless-Mandat wurde automatisch deaktiviert.\n";
            $message .= "Bitte neues Mandat manuell in GoCardless anlegen.";

            $this->log('Sending bank change notification to: ' . $admin_email);

            $sent = wp_mail($admin_email, $subject, $message);

            if ($sent) {
                $this->log('Bank change notification sent successfully');
            } else {
                $this->log('ERROR: Failed to send bank change notification');
            }

            return $sent;
        }

        /**
         * Render student status form shortcode
         */
        public function render_student_status_form($atts) {
            if (!is_user_logged_in()) {
                return '<p>Sie müssen angemeldet sein, um Ihren Studierendenstatus zu verwalten.</p>';
            }

            ob_start();
            include $this->plugin_path . 'templates/student-status-form.php';
            return ob_get_clean();
        }

        /**
         * Render student status banner shortcode (Q4 warning)
         */
        public function render_student_status_banner($atts) {
            if (!is_user_logged_in()) {
                return '';
            }

            // Check if we're in Q4 (October, November, December)
            $current_month = (int) date('n');
            $current_year = (int) date('Y');
            $is_last_quarter = ($current_month >= 10 && $current_month <= 12);

            if (!$is_last_quarter) {
                return ''; // Not in Q4, don't show banner
            }

            // Get user's Zoho ID
            $user_id = get_current_user_id();
            $zoho_id = get_user_meta($user_id, 'zoho_id', true);

            if (empty($zoho_id)) {
                return ''; // No Zoho ID, can't check status
            }

            // Get OAuth token
            $token = $this->get_oauth_token();
            if (!$token) {
                return ''; // No token, can't check status
            }

            // Fetch contact data from Zoho CRM
            $contact_data = $this->fetch_contact_from_crm($zoho_id, $token);

            if (!$contact_data) {
                return ''; // No contact data
            }

            // Check if student status exists
            $valid_through = $contact_data['Valid_Through'] ?? '';

            if (empty($valid_through)) {
                return ''; // No student status, hide banner
            }

            $valid_year = (int) $valid_through;

            // Only show if status expires in current year
            if ($valid_year !== $current_year) {
                return ''; // Status expires in future year, hide banner
            }

            // Show banner - status expires at end of current year
            ob_start();
            include $this->plugin_path . 'templates/student-status-banner.php';
            return ob_get_clean();
        }

        /**
         * AJAX: Load student status from Zoho CRM
         */
        public function ajax_load_student_status() {
            check_ajax_referer('dgptm_daten_bearbeiten_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Nicht angemeldet']);
            }

            $user_id = get_current_user_id();
            $zoho_id = get_user_meta($user_id, 'zoho_id', true);

            if (empty($zoho_id)) {
                wp_send_json_error(['message' => 'Keine Zoho ID gefunden']);
            }

            // Get OAuth token
            $token = $this->get_oauth_token();
            if (!$token) {
                wp_send_json_error(['message' => 'OAuth-Token nicht verfügbar']);
            }

            // Fetch contact data from Zoho CRM
            $contact_data = $this->fetch_contact_from_crm($zoho_id, $token);

            if (!$contact_data) {
                wp_send_json_error(['message' => 'Kontakt nicht gefunden in Zoho CRM']);
            }

            // Extract student status fields
            $contact_status = $contact_data['Contact_Status'] ?? '';
            $student_status_raw = $contact_data['Student_Status'] ?? false;

            // Log the raw values for debugging
            $this->log('Raw Contact_Status: "' . $contact_status . '"');
            $this->log('Raw Student_Status: ' . ($student_status_raw ? 'true' : 'false'));

            // Determine student status display based on Contact_Status and Student_Status
            $student_status_display = 'inactive'; // default

            // Check for "Prüfung des Studentenstatus" - exact match
            if ($contact_status === 'Prüfung des Studentenstatus') {
                // Blueprint status indicates verification in progress
                $student_status_display = 'in_review';
                $this->log('Status detected: IN_REVIEW (Contact_Status matches)');
            } elseif ($student_status_raw === true || $student_status_raw === 'true' || $student_status_raw === 1) {
                // Student_Status is true and not in review
                $student_status_display = 'active';
                $this->log('Status detected: ACTIVE (Student_Status is true)');
            } else {
                $this->log('Status detected: INACTIVE (default)');
            }

            // CRM-Daten-Bereinigung: Wenn Student_Status = false, Valid_Through löschen
            $valid_through = $contact_data['Valid_Through'] ?? '';

            // Prüfe ob Student_Status explizit false ist (nicht aktiv)
            $is_student_status_false = (
                $student_status_raw === false ||
                $student_status_raw === 'false' ||
                $student_status_raw === 0 ||
                $student_status_raw === '0' ||
                $student_status_raw === null ||
                $student_status_raw === ''
            );

            // Wenn Student_Status = false UND Valid_Through gesetzt ist → bereinigen
            if ($is_student_status_false && !empty($valid_through)) {
                $this->log('WARNING: Student_Status is FALSE but Valid_Through is set (' . $valid_through . '). Cleaning up CRM data...');

                // Lösche Valid_Through aus CRM
                $cleanup_result = $this->update_contact_in_crm($zoho_id, [
                    'Valid_Through' => null
                ], $token);

                if ($cleanup_result) {
                    $this->log('Valid_Through successfully cleared from CRM (Student_Status = false)');
                    $valid_through = ''; // Auch lokal leeren für die Antwort
                } else {
                    $this->log('ERROR: Failed to clear Valid_Through from CRM');
                }
            }

            // Zusätzliche Prüfung: Wenn Status inaktiv (nicht in Prüfung) und Valid_Through gesetzt
            if ($student_status_display === 'inactive' && !empty($valid_through)) {
                $this->log('WARNING: Status display is INACTIVE but Valid_Through is still set (' . $valid_through . '). Attempting cleanup...');

                // Nochmal versuchen zu löschen (falls oben nicht gegriffen hat)
                $cleanup_result = $this->update_contact_in_crm($zoho_id, [
                    'Valid_Through' => null
                ], $token);

                if ($cleanup_result) {
                    $this->log('Valid_Through successfully cleared from CRM (status display = inactive)');
                    $valid_through = '';
                } else {
                    $this->log('ERROR: Failed to clear Valid_Through from CRM');
                }
            }

            $status_data = [
                'student_status' => $student_status_raw,
                'student_status_display' => $student_status_display,
                'contact_status' => $contact_status,
                'valid_through' => $valid_through,
                'has_certificate' => !empty($contact_data['StudinachweisDirekt']),
                'certificate_info' => $contact_data['StudinachweisDirekt'] ?? null,
            ];

            $this->log('Final status_data: ' . wp_json_encode($status_data));
            wp_send_json_success(['data' => $status_data]);
        }

        /**
         * AJAX: Upload student certificate to Zoho CRM
         */
        public function ajax_upload_student_certificate() {
            check_ajax_referer('dgptm_daten_bearbeiten_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Nicht angemeldet']);
            }

            $user_id = get_current_user_id();
            $zoho_id = get_user_meta($user_id, 'zoho_id', true);

            if (empty($zoho_id)) {
                wp_send_json_error(['message' => 'Keine Zoho ID gefunden']);
            }

            // Get OAuth token
            $token = $this->get_oauth_token();
            if (!$token) {
                wp_send_json_error(['message' => 'OAuth-Token nicht verfügbar']);
            }

            // Validate year
            $year = isset($_POST['year']) ? sanitize_text_field($_POST['year']) : '';
            if (empty($year) || !is_numeric($year)) {
                wp_send_json_error(['message' => 'Bitte geben Sie ein gültiges Jahr ein']);
            }

            $year = (int) $year;
            $current_year = (int) date('Y');
            $current_month = (int) date('n'); // 1-12
            $is_q4 = ($current_month >= 10); // Oktober, November, Dezember

            // Zeitbasierte Validierung
            if ($is_q4) {
                // Q4: Nur Folgejahr oder später akzeptieren (max. 2 Jahre im Voraus)
                $min_year = $current_year + 1;
                $max_year = $current_year + 3; // Folgejahr + 2 weitere Jahre

                if ($year < $min_year) {
                    wp_send_json_error([
                        'message' => 'Ab dem 4. Quartal (Oktober-Dezember) können nur Bescheinigungen für das Folgejahr oder später eingereicht werden. Frühestes akzeptiertes Jahr: ' . $min_year
                    ]);
                }

                if ($year > $max_year) {
                    wp_send_json_error([
                        'message' => 'Das Jahr darf maximal 2 Jahre nach dem Folgejahr liegen. Spätestes akzeptiertes Jahr: ' . $max_year
                    ]);
                }
            } else {
                // Q1-Q3: Aktuelles Jahr bis max. 2 Jahre im Voraus akzeptieren
                $min_year = $current_year;
                $max_year = $current_year + 2;

                if ($year < $min_year) {
                    wp_send_json_error([
                        'message' => 'Das Jahr darf nicht in der Vergangenheit liegen. Frühestes akzeptiertes Jahr: ' . $min_year
                    ]);
                }

                if ($year > $max_year) {
                    wp_send_json_error([
                        'message' => 'Das Jahr darf maximal 2 Jahre in der Zukunft liegen. Spätestes akzeptiertes Jahr: ' . $max_year
                    ]);
                }
            }

            $this->log('Year validation passed. Q4: ' . ($is_q4 ? 'yes' : 'no') . ', Accepted range: ' . $min_year . '-' . $max_year . ', Submitted: ' . $year);

            // Validate file
            if (!isset($_FILES['certificate_file'])) {
                wp_send_json_error(['message' => 'Keine Datei empfangen']);
            }

            $file = $_FILES['certificate_file'];

            // Max 2MB
            if ($file['size'] > 2 * 1024 * 1024) {
                wp_send_json_error(['message' => 'Die Datei darf maximal 2MB groß sein']);
            }

            // Allowed MIME types
            $allowed_mimes = [
                'application/pdf',
                'image/jpeg',
                'image/jpg',
                'image/png',
            ];

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime_type = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            if (!in_array($mime_type, $allowed_mimes)) {
                wp_send_json_error(['message' => 'Nur PDF, JPG und PNG-Dateien sind erlaubt']);
            }

            $this->log('Starting certificate upload for user ' . $user_id . ', Zoho ID: ' . $zoho_id . ', Year: ' . $year);

            // First, fetch current contact data
            $contact_data = $this->fetch_contact_from_crm($zoho_id, $token);

            // IMPORTANT: Delete existing file(s) first to avoid LIMIT_EXCEEDED error
            // Zoho file upload fields can only hold 1 file, so we must clear before uploading
            if ($contact_data && !empty($contact_data['StudinachweisDirekt'])) {
                $this->log('Existing file(s) found in StudinachweisDirekt, deleting...');
                $existing_files = $contact_data['StudinachweisDirekt'];

                // Extract attachment IDs from existing files
                $attachment_ids = [];
                if (is_array($existing_files)) {
                    foreach ($existing_files as $file_info) {
                        if (isset($file_info['attachment_Id'])) {
                            $attachment_ids[] = $file_info['attachment_Id'];
                        }
                    }
                }

                $this->log('Found ' . count($attachment_ids) . ' attachment(s) to delete: ' . wp_json_encode($attachment_ids));

                if (!empty($attachment_ids)) {
                    $clear_result = $this->delete_file_from_zoho($zoho_id, 'StudinachweisDirekt', $attachment_ids, $token);
                    if ($clear_result) {
                        $this->log('File(s) deleted successfully via v8 API');

                        // Wait briefly for Zoho to process the deletion
                        // Using v8 DELETE endpoint should be faster than update method
                        $this->log('Waiting 2 seconds for Zoho to finalize deletion...');
                        sleep(2);

                        // Verify deletion by fetching contact data again
                        $this->log('Verifying deletion...');
                        $verify_data = $this->fetch_contact_from_crm($zoho_id, $token);
                        if ($verify_data && !empty($verify_data['StudinachweisDirekt'])) {
                            $this->log('WARNING: File still exists after deletion. Waiting another 3 seconds...');
                            sleep(3);

                            // Verify again
                            $verify_data2 = $this->fetch_contact_from_crm($zoho_id, $token);
                            if ($verify_data2 && !empty($verify_data2['StudinachweisDirekt'])) {
                                $this->log('ERROR: File still exists after 5 seconds total. Upload may fail.');
                            } else {
                                $this->log('Deletion verified after additional wait');
                            }
                        } else {
                            $this->log('Deletion verified - field is now empty');
                        }
                    } else {
                        $this->log('WARNING: Failed to delete file(s), but continuing with upload');
                    }
                }
            } else {
                $this->log('No existing files in StudinachweisDirekt field');
            }

            // Upload new file to Zoho
            $upload_result = $this->upload_file_to_zoho($zoho_id, $file, 'StudinachweisDirekt', $token);

            if (!$upload_result) {
                $this->log('ERROR: Failed to upload certificate to Zoho');
                wp_send_json_error(['message' => 'Fehler beim Hochladen der Datei zu Zoho CRM']);
            }

            $this->log('Certificate uploaded successfully');

            // Check current blueprint status in Contact_Status field
            $current_blueprint_status = $contact_data['Contact_Status'] ?? null;
            $this->log('Current Contact_Status (Blueprint): ' . ($current_blueprint_status ?: 'NOT SET'));

            // Blueprint configuration
            $target_blueprint_status = 'Studierendenstatus prüfen';

            // Prepare data to update
            $crm_data = [
                'Student_Status' => true,
                'Valid_Through' => intval($year),
            ];

            $update_success = false;

            // Check if blueprint status is already set to target
            if ($current_blueprint_status === $target_blueprint_status) {
                // Blueprint already in correct state - just update fields
                $this->log('Blueprint already in target state - updating fields only');
                $update_success = $this->update_contact_in_crm($zoho_id, $crm_data, $token);
            } else {
                // Blueprint not in target state
                // Strategy: First update fields, then trigger blueprint transition
                $this->log('Blueprint not in target state - updating fields first, then triggering transition');

                // Step 1: Update the fields
                $fields_updated = $this->update_contact_in_crm($zoho_id, $crm_data, $token);

                if ($fields_updated) {
                    $this->log('Fields updated successfully, now triggering blueprint transition');
                    // Step 2: Trigger blueprint transition (without data, just transition)
                    $blueprint_success = $this->trigger_blueprint_transition($zoho_id, [], $token);

                    if ($blueprint_success) {
                        $this->log('Blueprint transition successful');
                        $update_success = true;
                    } else {
                        $this->log('WARNING: Blueprint transition failed, but fields were updated');
                        $update_success = true; // Fields are updated, that's the main goal
                    }
                } else {
                    $this->log('ERROR: Failed to update fields');
                    $update_success = false;
                }
            }

            if (!$update_success) {
                $this->log('ERROR: Failed to update student status fields');
                wp_send_json_error(['message' => 'Datei hochgeladen, aber Status-Aktualisierung fehlgeschlagen']);
            }

            $this->log('Student status updated successfully');
            wp_send_json_success([
                'message' => 'Studienbescheinigung erfolgreich hochgeladen und Status aktualisiert',
                'valid_through' => $year
            ]);
        }

        /**
         * Upload file to Zoho CRM attachment field
         *
         * Uses Zoho's 2-step process:
         * 1. Upload file to /crm/v2/files and get file_id
         * 2. Link file_id to contact field
         */
        private function upload_file_to_zoho($zoho_id, $file, $field_name, $token) {
            $this->log('Uploading file to Zoho - Contact: ' . $zoho_id . ', Field: ' . $field_name);

            $file_name = basename($file['name']);
            $file_contents = file_get_contents($file['tmp_name']);

            if ($file_contents === false) {
                $this->log('ERROR: Could not read file contents');
                return false;
            }

            // Prepare multipart form data
            $boundary = wp_generate_password(24, false);

            // Build multipart body
            $body = '';
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="file"; filename="' . $file_name . '"' . "\r\n";
            $body .= 'Content-Type: ' . $file['type'] . "\r\n\r\n";
            $body .= $file_contents . "\r\n";
            $body .= '--' . $boundary . '--';

            // Step 1: Upload file to /crm/v2/files endpoint
            $this->log('Step 1: Uploading to /crm/v2/files endpoint');

            $response = wp_remote_post(
                'https://www.zohoapis.eu/crm/v2/files',
                [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $token,
                        'Content-Type' => 'multipart/form-data; boundary=' . $boundary
                    ],
                    'body' => $body,
                    'timeout' => 60
                ]
            );

            if (is_wp_error($response)) {
                $this->log('ERROR: Zoho file upload failed: ' . $response->get_error_message());
                return false;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            $this->log('Zoho file upload HTTP Code: ' . $http_code);
            $this->log('Zoho file upload response: ' . wp_json_encode($response_body));

            // Get file_id from response
            if (!isset($response_body['data'][0]['details']['id'])) {
                $this->log('ERROR: No file ID in response');
                return false;
            }

            $file_id = $response_body['data'][0]['details']['id'];
            $this->log('File uploaded to Zoho with file_id: ' . $file_id);

            // Step 2: Link file to contact field
            $this->log('Step 2: Linking file_id to contact field ' . $field_name);

            // Use trigger=workflow to bypass some validation
            $update_data = [
                $field_name => [$file_id] // IMPORTANT: Must be an array (replaces any existing files)
            ];

            $response = wp_remote_request(
                'https://www.zohoapis.eu/crm/v2/Contacts/' . $zoho_id . '?trigger=workflow',
                [
                    'method' => 'PUT',
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $token,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => wp_json_encode(['data' => [$update_data]]),
                    'timeout' => 30
                ]
            );

            if (is_wp_error($response)) {
                $this->log('ERROR: Failed to link file to contact: ' . $response->get_error_message());
                return false;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            $this->log('Link file response HTTP ' . $http_code . ': ' . wp_json_encode($response_body));

            // Check if the response indicates success
            // HTTP 200-299 is success, but also check the response body for errors
            if ($http_code >= 200 && $http_code < 300) {
                // Check if response contains error messages
                if (isset($response_body['data'][0]['status']) && $response_body['data'][0]['status'] === 'error') {
                    $error_code = $response_body['data'][0]['code'] ?? 'UNKNOWN';
                    $error_message = $response_body['data'][0]['message'] ?? 'Unknown error';
                    $this->log('ERROR: File link failed despite HTTP 2xx: ' . $error_code . ' - ' . $error_message);

                    // Special handling for LIMIT_EXCEEDED
                    if ($error_code === 'LIMIT_EXCEEDED') {
                        $this->log('LIMIT_EXCEEDED detected - field was not properly cleared');
                    }

                    return false;
                }
                return true;
            }

            return false;
        }

        /**
         * Delete file from Zoho CRM File Upload custom field
         *
         * Uses the UPDATE method with the Deluge-compatible format:
         * {"Field_Name": [{"attachment_id": "xxx", "_delete": null}]}
         *
         * IMPORTANT: Use "attachment_id" (lowercase 'id'), not "attachment_Id"
         *
         * @param string $zoho_id Contact ID
         * @param string $field_name Field API name
         * @param array $attachment_ids Array of attachment IDs to delete
         * @param string $token OAuth token
         * @return bool Success status
         */
        private function delete_file_from_zoho($zoho_id, $field_name, $attachment_ids, $token) {
            $this->log('Deleting file(s) from Zoho - Contact: ' . $zoho_id . ', Field: ' . $field_name);
            $this->log('Attachment IDs to delete: ' . wp_json_encode($attachment_ids));

            // Build delete array using Deluge format
            // Format: [{"attachment_id": "xxx", "_delete": null}]
            // Note: Use "attachment_id" (lowercase), not "attachment_Id"
            $delete_array = [];
            foreach ($attachment_ids as $attachment_id) {
                $delete_array[] = [
                    'attachment_id' => $attachment_id,  // lowercase 'id'
                    '_delete' => null
                ];
            }

            $crm_data = [
                $field_name => $delete_array
            ];

            $this->log('Delete payload: ' . wp_json_encode(['data' => [$crm_data]]));

            $response = wp_remote_request(
                'https://www.zohoapis.eu/crm/v2/Contacts/' . $zoho_id,
                [
                    'method' => 'PUT',
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $token,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => wp_json_encode(['data' => [$crm_data]]),
                    'timeout' => 30
                ]
            );

            if (is_wp_error($response)) {
                $this->log('ERROR: Failed to delete file: ' . $response->get_error_message());
                return false;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            $this->log('Delete file response HTTP ' . $http_code . ': ' . wp_json_encode($body));

            // Check for success in response body
            if ($http_code >= 200 && $http_code < 300) {
                if (isset($body['data'][0]['code'])) {
                    if ($body['data'][0]['code'] === 'SUCCESS') {
                        $this->log('File(s) deleted successfully');
                        return true;
                    } else {
                        $this->log('ERROR: Delete failed with code: ' . $body['data'][0]['code']);
                        if (isset($body['data'][0]['message'])) {
                            $this->log('Error message: ' . $body['data'][0]['message']);
                        }
                        return false;
                    }
                }
                // If no explicit code, assume success based on HTTP code
                return true;
            }

            return false;
        }

        /**
         * Trigger Blueprint transition in Zoho CRM
         *
         * Triggers the student status verification blueprint process
         */
        private function trigger_blueprint_transition($zoho_id, $additional_data, $token) {
            $this->log('Triggering Blueprint transition for Contact: ' . $zoho_id);

            // Blueprint configuration
            // ID: 548256000001285820
            // Name: Studierendenstatus prüfen
            // Next Field Value: Prüfung des Studentenstatus
            $blueprint_transition_id = '548256000001285820';

            // Prepare blueprint transition data
            // If additional_data is empty, use empty object {}, not empty array []
            $blueprint_data = !empty($additional_data) ? $additional_data : new stdClass();

            $transition_data = [
                'blueprint' => [
                    [
                        'transition_id' => $blueprint_transition_id,
                        'data' => $blueprint_data
                    ]
                ]
            ];

            $this->log('Blueprint transition data: ' . wp_json_encode($transition_data));

            $response = wp_remote_request(
                'https://www.zohoapis.eu/crm/v2/Contacts/' . $zoho_id . '/actions/blueprint',
                [
                    'method' => 'PUT',
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $token,
                        'Content-Type' => 'application/json'
                    ],
                    'body' => wp_json_encode($transition_data),
                    'timeout' => 30
                ]
            );

            if (is_wp_error($response)) {
                $this->log('ERROR: Blueprint transition failed: ' . $response->get_error_message());
                return false;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            $this->log('Blueprint transition response HTTP ' . $http_code . ': ' . wp_json_encode($body));

            // If blueprint transition failed, try fallback: just update fields without blueprint
            if ($http_code >= 400) {
                $this->log('WARNING: Blueprint transition failed, falling back to direct field update');
                return $this->update_contact_in_crm($zoho_id, $additional_data, $token);
            }

            return ($http_code >= 200 && $http_code < 300);
        }

        /**
         * Log message
         */
        private function log($message) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[DGPTM Daten bearbeiten] ' . $message);
            }
        }
    }
}

// Prevent double initialization
if (!isset($GLOBALS['dgptm_daten_bearbeiten_initialized'])) {
    $GLOBALS['dgptm_daten_bearbeiten_initialized'] = true;
    DGPTM_Daten_Bearbeiten::get_instance();
}
