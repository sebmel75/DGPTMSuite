<?php
/**
 * Plugin Name: DGPTM - Mitgliedsantrag
 * Description: Satzungskonformes Mitgliedsantragsformular (§4) mit dynamischen Bürgenanforderungen, Qualifikationsnachweisen und Zoho CRM Integration
 * Version: 2.0.0
 * Author: Sebastian Melzer
 * Text Domain: dgptm-mitgliedsantrag
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper-Funktion fuer Mitgliedsantrag Settings
 * Verwendet das zentrale DGPTM Settings-System mit Fallback auf alte Options
 */
if (!function_exists('dgptm_ma_get_options')) {
    function dgptm_ma_get_options() {
        // Zuerst im zentralen System suchen
        if (function_exists('dgptm_get_module_settings')) {
            $central_settings = dgptm_get_module_settings('mitgliedsantrag');
            if (!empty($central_settings)) {
                return $central_settings;
            }
        }

        // Fallback auf alte Option
        return get_option('dgptm_mitgliedsantrag_options', []);
    }
}

if (!class_exists('DGPTM_Mitgliedsantrag')) {
    class DGPTM_Mitgliedsantrag {
        private static $instance = null;
        private $plugin_path;
        private $plugin_url;
        private $version = '2.0.0';

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->plugin_path = plugin_dir_path(__FILE__);
            $this->plugin_url = plugin_dir_url(__FILE__);

            // Register hooks
            // Use priority 5 to register shortcode early (before most plugins at priority 10)
            add_action('init', [$this, 'register_shortcode'], 5);
            // Use priority 999 to re-register shortcode late (after other plugins)
            add_action('wp_loaded', [$this, 'force_register_shortcode'], 999);

            // Vorstandsgenehmigung Shortcode
            add_shortcode('vorstandsgenehmigung', [$this, 'render_vorstandsgenehmigung']);

            // AJAX Handler für Genehmigung/Ablehnung
            add_action('wp_ajax_dgptm_vorstand_entscheidung', [$this, 'ajax_vorstand_entscheidung']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);

            // AJAX handlers
            add_action('wp_ajax_dgptm_verify_guarantor', [$this, 'ajax_verify_guarantor']);
            add_action('wp_ajax_nopriv_dgptm_verify_guarantor', [$this, 'ajax_verify_guarantor']);
            add_action('wp_ajax_dgptm_validate_address', [$this, 'ajax_validate_address']);
            add_action('wp_ajax_nopriv_dgptm_validate_address', [$this, 'ajax_validate_address']);
            add_action('wp_ajax_dgptm_validate_email', [$this, 'ajax_validate_email']);
            add_action('wp_ajax_nopriv_dgptm_validate_email', [$this, 'ajax_validate_email']);
            add_action('wp_ajax_dgptm_check_email_in_crm', [$this, 'ajax_check_email_in_crm']);
            add_action('wp_ajax_nopriv_dgptm_check_email_in_crm', [$this, 'ajax_check_email_in_crm']);
            add_action('wp_ajax_dgptm_submit_application', [$this, 'ajax_submit_application']);
            add_action('wp_ajax_nopriv_dgptm_submit_application', [$this, 'ajax_submit_application']);
            add_action('wp_ajax_dgptm_test_webhook', [$this, 'ajax_test_webhook']);

            // Scheduled event for deleting student certificates
            add_action('dgptm_delete_student_certificate', [$this, 'delete_student_certificate']);

            // REST API endpoint for certificate deletion
            add_action('rest_api_init', [$this, 'register_rest_routes']);

            // Log initialization
            $this->log('Mitgliedsantrag module initialized');
        }

        public function register_rest_routes() {
            register_rest_route('dgptm/v1', '/delete-certificate/(?P<id>\d+)', [
                'methods' => 'GET',
                'callback' => [$this, 'rest_delete_certificate'],
                'permission_callback' => '__return_true', // Public endpoint - anyone with the URL can delete
                'args' => [
                    'id' => [
                        'validate_callback' => function($param) {
                            return is_numeric($param);
                        }
                    ]
                ]
            ]);
        }

        public function rest_delete_certificate($request) {
            $attachment_id = $request->get_param('id');

            $this->log('REST API: Delete certificate request for ID ' . $attachment_id);

            // Check if attachment exists
            if (!get_post($attachment_id)) {
                $this->log('REST API: Certificate ID ' . $attachment_id . ' not found');
                return new WP_Error('not_found', 'Certificate not found', ['status' => 404]);
            }

            // Delete the attachment
            $deleted = wp_delete_attachment($attachment_id, true);

            if ($deleted) {
                $this->log('REST API: Successfully deleted certificate ID ' . $attachment_id);
                return new WP_REST_Response([
                    'success' => true,
                    'message' => 'Certificate deleted successfully',
                    'attachment_id' => $attachment_id
                ], 200);
            } else {
                $this->log('REST API: Failed to delete certificate ID ' . $attachment_id);
                return new WP_Error('delete_failed', 'Failed to delete certificate', ['status' => 500]);
            }
        }

        public function register_shortcode() {
            add_shortcode('dgptm-mitgliedsantrag', [$this, 'render_form']);
            // Also register the short version as alias
            add_shortcode('mitgliedsantrag', [$this, 'render_form']);
            $this->log('Shortcodes registered: [dgptm-mitgliedsantrag], [mitgliedsantrag]');
        }

        /**
         * Force re-register shortcode after all plugins loaded
         * This ensures our shortcode overrides Formidable Forms
         */
        public function force_register_shortcode() {
            global $shortcode_tags;

            // Check if 'mitgliedsantrag' shortcode exists and is not ours
            if (isset($shortcode_tags['mitgliedsantrag'])) {
                $current_callback = $shortcode_tags['mitgliedsantrag'];

                // If it's not our callback, log warning and override it
                if (!is_array($current_callback) || $current_callback[0] !== $this) {
                    $this->log('WARNING: Shortcode [mitgliedsantrag] was hijacked by another plugin. Reclaiming it.');

                    // Remove the conflicting shortcode
                    remove_shortcode('mitgliedsantrag');
                }
            }

            // Re-register our shortcodes with force
            add_shortcode('dgptm-mitgliedsantrag', [$this, 'render_form']);
            add_shortcode('mitgliedsantrag', [$this, 'render_form']);

            $this->log('Shortcodes force-registered after all plugins loaded');
        }

        public function enqueue_frontend_assets() {
            // Check if we're on a page that might have the shortcode
            global $post;

            if (!is_a($post, 'WP_Post')) {
                return;
            }

            // Only enqueue if shortcode is present (check both versions)
            if (!has_shortcode($post->post_content, 'dgptm-mitgliedsantrag') && !has_shortcode($post->post_content, 'mitgliedsantrag')) {
                return;
            }

            wp_enqueue_style(
                'dgptm-mitgliedsantrag',
                $this->plugin_url . 'assets/css/style.css',
                [],
                $this->version
            );

            wp_enqueue_script(
                'dgptm-mitgliedsantrag',
                $this->plugin_url . 'assets/js/script.js',
                ['jquery'],
                $this->version,
                true
            );

            wp_localize_script('dgptm-mitgliedsantrag', 'dgptmMitgliedsantrag', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dgptm_mitgliedsantrag_nonce'),
                'strings' => [
                    'verifying' => 'Überprüfe...',
                    'verified' => 'Verifiziert',
                    'notFound' => 'Nicht gefunden',
                    'notMember' => 'Kein Mitglied',
                    'invalidEmail' => 'Ungültige E-Mail-Adresse',
                    'invalidAddress' => 'Adresse konnte nicht verifiziert werden',
                    'pleaseWait' => 'Bitte warten...',
                    'submitting' => 'Antrag wird gesendet...',
                    'error' => 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.',
                ]
            ]);
        }

        public function enqueue_admin_assets($hook) {
            if ('toplevel_page_dgptm-mitgliedsantrag' !== $hook) {
                return;
            }

            wp_enqueue_style(
                'dgptm-mitgliedsantrag-admin',
                $this->plugin_url . 'assets/css/admin-style.css',
                [],
                $this->version
            );

            wp_enqueue_script(
                'dgptm-mitgliedsantrag-admin',
                $this->plugin_url . 'assets/js/admin-script.js',
                ['jquery'],
                $this->version,
                true
            );

            wp_localize_script('dgptm-mitgliedsantrag-admin', 'dgptmMitgliedsantragAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dgptm_mitgliedsantrag_admin_nonce')
            ]);
        }

        public function add_admin_menu() {
            add_menu_page(
                'Mitgliedsantrag',
                'Mitgliedsantrag',
                'manage_options',
                'dgptm-mitgliedsantrag',
                [$this, 'render_admin_page'],
                'dashicons-id-alt',
                30
            );
        }

        public function register_settings() {
            register_setting('dgptm_mitgliedsantrag_settings', 'dgptm_mitgliedsantrag_options', [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings']
            ]);

            // Add action to disable Formidable Forms shortcode conflicts
            add_action('init', [$this, 'disable_formidable_conflicts'], 999);
        }

        /**
         * Disable Formidable Forms shortcode conflicts
         * This removes Formidable's generic shortcode handlers that might conflict
         */
        public function disable_formidable_conflicts() {
            $options = dgptm_ma_get_options();

            if (isset($options['disable_formidable_conflicts']) && $options['disable_formidable_conflicts']) {
                // Remove Formidable's shortcode if it exists
                if (shortcode_exists('formidable')) {
                    $this->log('Formidable Forms conflict prevention enabled - checking for conflicts');

                    // We don't remove [formidable] itself, but we ensure [mitgliedsantrag] is ours
                    global $shortcode_tags;
                    if (isset($shortcode_tags['mitgliedsantrag'])) {
                        $callback = $shortcode_tags['mitgliedsantrag'];
                        if (!is_array($callback) || $callback[0] !== $this) {
                            remove_shortcode('mitgliedsantrag');
                            add_shortcode('mitgliedsantrag', [$this, 'render_form']);
                            $this->log('Removed conflicting [mitgliedsantrag] shortcode and registered ours');
                        }
                    }
                }
            }
        }

        public function sanitize_settings($input) {
            $sanitized = [];

            if (isset($input['client_id'])) {
                $sanitized['client_id'] = sanitize_text_field($input['client_id']);
            }
            if (isset($input['client_secret'])) {
                $sanitized['client_secret'] = sanitize_text_field($input['client_secret']);
            }
            if (isset($input['redirect_uri'])) {
                $sanitized['redirect_uri'] = esc_url_raw($input['redirect_uri']);
            }
            if (isset($input['access_token'])) {
                $sanitized['access_token'] = sanitize_text_field($input['access_token']);
            }
            if (isset($input['refresh_token'])) {
                $sanitized['refresh_token'] = sanitize_text_field($input['refresh_token']);
            }
            if (isset($input['token_expiry'])) {
                $sanitized['token_expiry'] = intval($input['token_expiry']);
            }
            if (isset($input['disable_formidable_conflicts'])) {
                $sanitized['disable_formidable_conflicts'] = (bool) $input['disable_formidable_conflicts'];
            }
            if (isset($input['google_maps_api_key'])) {
                $sanitized['google_maps_api_key'] = sanitize_text_field($input['google_maps_api_key']);
            }
            if (isset($input['webhook_url'])) {
                $sanitized['webhook_url'] = esc_url_raw($input['webhook_url']);
            }
            if (isset($input['webhook_test_mode'])) {
                $sanitized['webhook_test_mode'] = (bool) $input['webhook_test_mode'];
            }
            if (isset($input['default_country_code'])) {
                $sanitized['default_country_code'] = sanitize_text_field($input['default_country_code']);
            }
            if (isset($input['field_mapping'])) {
                $sanitized['field_mapping'] = $input['field_mapping']; // Will be JSON
            }

            return $sanitized;
        }

        public function render_admin_page() {
            if (!current_user_can('manage_options')) {
                wp_die(__('Sie haben keine Berechtigung, auf diese Seite zuzugreifen.'));
            }

            $options = dgptm_ma_get_options();

            // Handle OAuth callback
            if (isset($_GET['code']) && isset($_GET['page']) && $_GET['page'] === 'dgptm-mitgliedsantrag') {
                $this->handle_oauth_callback($_GET['code']);
                wp_redirect(admin_url('admin.php?page=dgptm-mitgliedsantrag&oauth_success=1'));
                exit;
            }

            include $this->plugin_path . 'templates/admin-settings.php';
        }

        public function handle_oauth_callback($code) {
            $options = dgptm_ma_get_options();

            if (empty($options['client_id']) || empty($options['client_secret']) || empty($options['redirect_uri'])) {
                $this->log('OAuth callback failed: missing credentials');
                return false;
            }

            $response = wp_remote_post('https://accounts.zoho.eu/oauth/v2/token', [
                'body' => [
                    'code' => $code,
                    'client_id' => $options['client_id'],
                    'client_secret' => $options['client_secret'],
                    'redirect_uri' => $options['redirect_uri'],
                    'grant_type' => 'authorization_code'
                ]
            ]);

            if (is_wp_error($response)) {
                $this->log('OAuth token request failed: ' . $response->get_error_message());
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['access_token'])) {
                $options['access_token'] = $body['access_token'];
                $options['refresh_token'] = $body['refresh_token'] ?? '';
                $options['token_expiry'] = time() + ($body['expires_in'] ?? 3600);
                update_option('dgptm_mitgliedsantrag_options', $options);
                $this->log('OAuth tokens saved successfully');
                return true;
            }

            $this->log('OAuth token response invalid: ' . wp_json_encode($body));
            return false;
        }

        public function get_access_token() {
            $options = dgptm_ma_get_options();

            // Check if using crm-abruf module's tokens
            if (class_exists('DGPTM_Zoho_CRM_Hardened')) {
                // Try to use crm-abruf module's OAuth tokens
                $crm_options = get_option('dgptm_zoho_config', []);
                if (!empty($crm_options['access_token'])) {
                    $this->log('Using crm-abruf module OAuth token');
                    return $crm_options['access_token'];
                }
            }

            if (empty($options['access_token'])) {
                $this->log('No access token available');
                return false;
            }

            // Check if token is expired
            if (isset($options['token_expiry']) && $options['token_expiry'] < time()) {
                $this->log('Access token expired, attempting refresh');
                return $this->refresh_access_token();
            }

            return $options['access_token'];
        }

        private function refresh_access_token() {
            $options = dgptm_ma_get_options();

            if (empty($options['refresh_token']) || empty($options['client_id']) || empty($options['client_secret'])) {
                $this->log('Cannot refresh token: missing credentials');
                return false;
            }

            $response = wp_remote_post('https://accounts.zoho.eu/oauth/v2/token', [
                'body' => [
                    'refresh_token' => $options['refresh_token'],
                    'client_id' => $options['client_id'],
                    'client_secret' => $options['client_secret'],
                    'grant_type' => 'refresh_token'
                ]
            ]);

            if (is_wp_error($response)) {
                $this->log('Token refresh failed: ' . $response->get_error_message());
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['access_token'])) {
                $options['access_token'] = $body['access_token'];
                $options['token_expiry'] = time() + ($body['expires_in'] ?? 3600);
                update_option('dgptm_mitgliedsantrag_options', $options);
                $this->log('Access token refreshed successfully');
                return $body['access_token'];
            }

            $this->log('Token refresh response invalid: ' . wp_json_encode($body));
            return false;
        }

        public function render_form($atts) {
            $this->log('Rendering membership application form');
            ob_start();

            // Check if template file exists
            $template_file = $this->plugin_path . 'templates/form.php';
            if (!file_exists($template_file)) {
                $this->log('ERROR: Template file not found: ' . $template_file);
                return '<div class="error"><p>Fehler: Formular-Template nicht gefunden.</p></div>';
            }

            include $template_file;
            return ob_get_clean();
        }

        public function ajax_verify_guarantor() {
            check_ajax_referer('dgptm_mitgliedsantrag_nonce', 'nonce');

            $input = sanitize_text_field($_POST['input'] ?? '');

            if (empty($input)) {
                wp_send_json_error(['message' => 'Eingabe erforderlich']);
            }

            $token = $this->get_access_token();
            if (!$token) {
                wp_send_json_error(['message' => 'OAuth-Verbindung nicht konfiguriert']);
            }

            // Search for contact by name or email
            $contact = $this->search_contact($input, $token);

            if ($contact) {
                $is_member = $this->is_valid_member($contact);

                wp_send_json_success([
                    'found' => true,
                    'is_member' => $is_member,
                    'contact' => [
                        'id' => $contact['id'],
                        'name' => $contact['First_Name'] . ' ' . $contact['Last_Name'],
                        'email' => $contact['Email'],
                        'membership_type' => $contact['Membership_Type'] ?? ''
                    ]
                ]);
            } else {
                wp_send_json_success([
                    'found' => false,
                    'is_member' => false
                ]);
            }
        }

        private function search_contact($input, $token) {
            // Check if input is email
            if (filter_var($input, FILTER_VALIDATE_EMAIL)) {
                return $this->search_by_email($input, $token);
            } else {
                return $this->search_by_name($input, $token);
            }
        }

        /**
         * Robuste Kontaktsuche: Erst E-Mail, dann Name+E-Mail Kombination
         *
         * @param string $email Primäre E-Mail-Adresse
         * @param string $first_name Vorname
         * @param string $last_name Nachname
         * @param string $token Zoho OAuth Token
         * @return array|false Contact data or false if not found
         */
        private function search_contact_robust($email, $first_name, $last_name, $token) {
            $this->log('Starting robust contact search - Email: ' . $email . ', Name: ' . $first_name . ' ' . $last_name);

            // Step 1: Search by email (primary, secondary, third)
            $contact_by_email = $this->search_by_email($email, $token);

            if ($contact_by_email) {
                $this->log('Contact found by email: ' . $contact_by_email['id']);

                // Verify name matches to ensure it's the same person
                $crm_first = strtolower(trim($contact_by_email['First_Name'] ?? ''));
                $crm_last = strtolower(trim($contact_by_email['Last_Name'] ?? ''));
                $input_first = strtolower(trim($first_name));
                $input_last = strtolower(trim($last_name));

                // Check if names are similar (allow for typos or variations)
                $first_name_match = ($crm_first === $input_first) ||
                                   (levenshtein($crm_first, $input_first) <= 2);
                $last_name_match = ($crm_last === $input_last) ||
                                  (levenshtein($crm_last, $input_last) <= 2);

                if ($first_name_match && $last_name_match) {
                    $this->log('Name verification passed - Same person confirmed');
                    return $contact_by_email;
                } else {
                    $this->log('WARNING: Email match but name mismatch. CRM: ' . $crm_first . ' ' . $crm_last . ', Input: ' . $input_first . ' ' . $input_last);
                    // Continue searching - might be a different person with same email
                }
            }

            // Step 2: Search by name + email combination using criteria query
            $criteria = '((First_Name:equals:' . urlencode($first_name) . ')and(Last_Name:equals:' . urlencode($last_name) . '))';

            $response = wp_remote_get(
                'https://www.zohoapis.eu/crm/v2/Contacts/search?criteria=' . $criteria,
                [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $token
                    ],
                    'timeout' => 30
                ]
            );

            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);

                if (isset($body['data']) && !empty($body['data'])) {
                    // Check if any of these contacts have matching email
                    foreach ($body['data'] as $contact) {
                        $contact_emails = [
                            strtolower($contact['Email'] ?? ''),
                            strtolower($contact['Secondary_Email'] ?? ''),
                            strtolower($contact['Third_Email'] ?? '')
                        ];

                        if (in_array(strtolower($email), $contact_emails)) {
                            $this->log('Contact found by name+email combination: ' . $contact['id']);
                            return $contact;
                        }
                    }

                    // If we found contacts with same name but different email, log warning
                    $this->log('WARNING: Found contacts with same name but different email addresses');
                }
            }

            $this->log('No existing contact found');
            return false;
        }

        /**
         * Search contact by email in ONLY the three main email fields
         * Does NOT search in Guarantor_Mail_1 or Guarantor_Mail_2
         *
         * @param string $email Email to search
         * @param string $token Zoho OAuth token
         * @return array|false Contact data or false
         */
        private function search_by_email($email, $token) {
            $this->log('Searching for email in main email fields only: ' . $email);

            // Search in all three email fields explicitly
            $email_fields = ['Email', 'Secondary_Email', 'Third_Email'];
            $email_lower = strtolower($email);

            foreach ($email_fields as $field) {
                $this->log('Searching in field: ' . $field);
                $contact = $this->search_by_field($field, $email, $token);

                if ($contact) {
                    // Verify the email is REALLY in one of the three main fields
                    // and NOT just in guarantor fields
                    $contact_emails = [
                        strtolower($contact['Email'] ?? ''),
                        strtolower($contact['Secondary_Email'] ?? ''),
                        strtolower($contact['Third_Email'] ?? '')
                    ];

                    if (in_array($email_lower, $contact_emails)) {
                        $this->log('Email found in field ' . $field . ' - Contact ID: ' . $contact['id']);
                        return $contact;
                    } else {
                        // Email might be in Guarantor fields - skip this result
                        $this->log('WARNING: Email found but not in main email fields - skipping (might be in Guarantor_Mail fields)');
                        continue;
                    }
                }
            }

            $this->log('Email not found in any main email field');
            return false;
        }

        private function search_by_name($name, $token) {
            // Fuzzy name matching - split input and try different combinations
            $name_parts = preg_split('/\s+/', trim($name));

            if (count($name_parts) < 2) {
                $this->log('Name search requires at least first and last name');
                return false;
            }

            // Try exact match first
            $response = wp_remote_get(
                'https://www.zohoapis.eu/crm/v2/Contacts/search?criteria=(First_Name:equals:' . urlencode($name_parts[0]) . ')and(Last_Name:equals:' . urlencode(end($name_parts)) . ')',
                [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $token
                    ]
                ]
            );

            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($body['data']) && !empty($body['data'])) {
                    return $body['data'][0];
                }
            }

            // Try fuzzy matching with contains
            $response = wp_remote_get(
                'https://www.zohoapis.eu/crm/v2/Contacts/search?criteria=(Last_Name:contains:' . urlencode(end($name_parts)) . ')',
                [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $token
                    ]
                ]
            );

            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($body['data']) && !empty($body['data'])) {
                    // Find best match by comparing names
                    foreach ($body['data'] as $contact) {
                        $full_name = strtolower($contact['First_Name'] . ' ' . $contact['Last_Name']);
                        $input_lower = strtolower($name);

                        similar_text($full_name, $input_lower, $percent);
                        if ($percent > 70) { // 70% similarity threshold
                            return $contact;
                        }
                    }
                }
            }

            return false;
        }

        private function search_by_field($field, $value, $token) {
            $response = wp_remote_get(
                'https://www.zohoapis.eu/crm/v2/Contacts/search?criteria=(' . $field . ':equals:' . urlencode($value) . ')',
                [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $token
                    ]
                ]
            );

            if (is_wp_error($response)) {
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['data']) && !empty($body['data'])) {
                return $body['data'][0];
            }

            return false;
        }

        private function is_valid_member($contact) {
            $valid_types = [
                'Ordentliches Mitglied',
                'Außerordentliches Mitglied',
                'Korrespondierendes Mitglied'
            ];

            $membership = $contact['Membership_Type'] ?? '';
            return in_array($membership, $valid_types);
        }

        /**
         * Prüft ob bereits ein Antrag oder eine Mitgliedschaft vorliegt
         *
         * @param array $contact CRM Contact data
         * @return array ['has_application' => bool, 'has_membership' => bool, 'status_text' => string]
         */
        private function check_application_status($contact) {
            $result = [
                'has_application' => false,
                'has_membership' => false,
                'status_text' => ''
            ];

            if (!$contact) {
                return $result;
            }

            // Check membership status
            $membership_type = $contact['Membership_Type'] ?? '';
            $membership_status = $contact['Membership_Status'] ?? '';

            // Active membership types
            $active_memberships = [
                'Ordentliches Mitglied',
                'Außerordentliches Mitglied',
                'Korrespondierendes Mitglied',
                'Förderndes Mitglied',
                'Ehrenmitglied',
                'Senior-Mitglied'
            ];

            if (in_array($membership_type, $active_memberships)) {
                $result['has_membership'] = true;
                $result['status_text'] = 'Sie sind bereits als "' . $membership_type . '" registriert.';

                if ($membership_status) {
                    $result['status_text'] .= ' Status: ' . $membership_status . '.';
                }

                $this->log('Existing membership found: ' . $membership_type . ' (' . $membership_status . ')');
                return $result;
            }

            // Check for pending application
            // Common fields that indicate pending application status
            $application_status = $contact['Application_Status'] ?? '';
            $antragsstatus = $contact['Antragsstatus'] ?? '';
            $lead_status = $contact['Lead_Status'] ?? '';

            $pending_statuses = [
                'Pending',
                'In Review',
                'Antrag gestellt',
                'Antrag eingereicht',
                'In Bearbeitung',
                'Zur Prüfung',
                'Awaiting Approval',
                'Under Review'
            ];

            // Check all possible status fields
            $status_fields = [
                'Application_Status' => $application_status,
                'Antragsstatus' => $antragsstatus,
                'Lead_Status' => $lead_status
            ];

            foreach ($status_fields as $field_name => $status_value) {
                if (!empty($status_value)) {
                    foreach ($pending_statuses as $pending_status) {
                        if (stripos($status_value, $pending_status) !== false) {
                            $result['has_application'] = true;
                            $result['status_text'] = 'Es liegt bereits ein Mitgliedsantrag vor (' . $field_name . ': ' . $status_value . ').';
                            $this->log('Pending application found: ' . $field_name . ' = ' . $status_value);
                            return $result;
                        }
                    }
                }
            }

            // Check if there's a Bemerkung (remark) indicating recent application
            $bemerkung = $contact['Bemerkung'] ?? '';
            if (stripos($bemerkung, 'Mitgliedsantrag') !== false) {
                // Check if remark is recent (last modified date)
                $modified_time = $contact['Modified_Time'] ?? '';
                if (!empty($modified_time)) {
                    $modified_timestamp = strtotime($modified_time);
                    $days_since_modified = (time() - $modified_timestamp) / (60 * 60 * 24);

                    // If modified within last 90 days and has application mention
                    if ($days_since_modified < 90) {
                        $result['has_application'] = true;
                        $result['status_text'] = 'Es liegt bereits ein Mitgliedsantrag vor (eingereicht vor ' . round($days_since_modified) . ' Tagen).';
                        $this->log('Recent application found in Bemerkung field (modified ' . round($days_since_modified) . ' days ago)');
                        return $result;
                    }
                }
            }

            $this->log('No existing application or membership found');
            return $result;
        }

        public function ajax_validate_address() {
            check_ajax_referer('dgptm_mitgliedsantrag_nonce', 'nonce');

            $street = sanitize_text_field($_POST['street'] ?? '');
            $city = sanitize_text_field($_POST['city'] ?? '');
            $zip = sanitize_text_field($_POST['zip'] ?? '');
            $country = sanitize_text_field($_POST['country'] ?? 'Deutschland');

            // Basic validation
            if (empty($street) || empty($city) || empty($zip)) {
                wp_send_json_error(['message' => 'Bitte füllen Sie alle Adressfelder aus']);
            }

            // Validate German ZIP code format
            if ($country === 'Deutschland' && !preg_match('/^\d{5}$/', $zip)) {
                wp_send_json_error(['message' => 'Ungültige Postleitzahl (Format: 12345)']);
            }

            // Validate with Google Maps Geocoding API
            $options = dgptm_ma_get_options();
            $api_key = $options['google_maps_api_key'] ?? '';

            if (!empty($api_key)) {
                $validation_result = $this->validate_address_with_google($street, $city, $zip, $country, $api_key);

                if (!$validation_result['valid']) {
                    wp_send_json_error([
                        'message' => $validation_result['message'],
                        'details' => $validation_result['details'] ?? []
                    ]);
                }

                wp_send_json_success([
                    'valid' => true,
                    'message' => 'Adresse erfolgreich verifiziert',
                    'formatted_address' => $validation_result['formatted_address'] ?? '',
                    'coordinates' => $validation_result['coordinates'] ?? []
                ]);
            }

            // Basic validation passed (no Google API)
            wp_send_json_success([
                'valid' => true,
                'message' => 'Adresse validiert (Basis-Validierung)'
            ]);
        }

        private function validate_address_with_google($street, $city, $zip, $country, $api_key) {
            $address = trim($street . ', ' . $zip . ' ' . $city . ', ' . $country);

            $url = 'https://maps.googleapis.com/maps/api/geocode/json?' . http_build_query([
                'address' => $address,
                'key' => $api_key,
                'language' => 'de',
                'region' => 'de'
            ]);

            $response = wp_remote_get($url, [
                'timeout' => 10
            ]);

            if (is_wp_error($response)) {
                $this->log('Google Maps API error: ' . $response->get_error_message());
                return [
                    'valid' => false,
                    'message' => 'Fehler bei der Adressvalidierung. Bitte versuchen Sie es erneut.'
                ];
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (!isset($body['status'])) {
                $this->log('Google Maps API invalid response');
                return [
                    'valid' => false,
                    'message' => 'Ungültige Antwort von der Adressvalidierung.'
                ];
            }

            if ($body['status'] === 'ZERO_RESULTS') {
                return [
                    'valid' => false,
                    'message' => 'Die angegebene Adresse konnte nicht gefunden werden. Bitte überprüfen Sie Ihre Eingabe.'
                ];
            }

            if ($body['status'] !== 'OK') {
                $this->log('Google Maps API status: ' . $body['status']);
                return [
                    'valid' => false,
                    'message' => 'Adresse konnte nicht verifiziert werden. Status: ' . $body['status']
                ];
            }

            if (empty($body['results'][0])) {
                return [
                    'valid' => false,
                    'message' => 'Keine Ergebnisse für die angegebene Adresse gefunden.'
                ];
            }

            $result = $body['results'][0];

            // Check if it's a precise address (street level)
            $types = $result['types'] ?? [];
            $is_precise = in_array('street_address', $types) || in_array('premise', $types) || in_array('subpremise', $types);

            if (!$is_precise) {
                return [
                    'valid' => false,
                    'message' => 'Bitte geben Sie eine vollständige Adresse mit Straße und Hausnummer an.',
                    'details' => [
                        'formatted_address' => $result['formatted_address'],
                        'precision' => 'low'
                    ]
                ];
            }

            // Verify ZIP code matches
            $found_zip = '';
            foreach ($result['address_components'] as $component) {
                if (in_array('postal_code', $component['types'])) {
                    $found_zip = $component['long_name'];
                    break;
                }
            }

            if ($found_zip && $found_zip !== $zip) {
                return [
                    'valid' => false,
                    'message' => "Die Postleitzahl stimmt nicht überein. Gefunden: $found_zip",
                    'details' => [
                        'expected_zip' => $zip,
                        'found_zip' => $found_zip
                    ]
                ];
            }

            return [
                'valid' => true,
                'message' => 'Adresse erfolgreich verifiziert',
                'formatted_address' => $result['formatted_address'],
                'coordinates' => [
                    'lat' => $result['geometry']['location']['lat'],
                    'lng' => $result['geometry']['location']['lng']
                ]
            ];
        }

        public function ajax_validate_email() {
            check_ajax_referer('dgptm_mitgliedsantrag_nonce', 'nonce');

            $email = sanitize_email($_POST['email'] ?? '');

            if (empty($email)) {
                wp_send_json_error(['message' => 'E-Mail-Adresse erforderlich']);
            }

            if (!is_email($email)) {
                wp_send_json_error(['message' => 'Ungültige E-Mail-Adresse']);
            }

            // Check DNS MX records for email domain
            $domain = substr(strrchr($email, "@"), 1);
            if (!checkdnsrr($domain, 'MX')) {
                wp_send_json_error(['message' => 'E-Mail-Domain existiert nicht']);
            }

            wp_send_json_success([
                'valid' => true,
                'message' => 'E-Mail-Adresse validiert'
            ]);
        }

        /**
         * Check if email exists in CRM (for members)
         * Prevents duplicate member applications
         */
        public function ajax_check_email_in_crm() {
            check_ajax_referer('dgptm_mitgliedsantrag_nonce', 'nonce');

            $email = sanitize_email($_POST['email'] ?? '');

            if (empty($email)) {
                wp_send_json_error(['message' => 'E-Mail-Adresse erforderlich']);
                return;
            }

            $this->log('Checking email in CRM: ' . $email);

            // Get access token
            $token = $this->get_access_token();
            if (!$token) {
                // If no CRM connection, just skip the check (graceful degradation)
                wp_send_json_success([
                    'exists' => false,
                    'message' => 'CRM nicht verbunden - Prüfung übersprungen'
                ]);
                return;
            }

            // Search for email in all three email fields
            $search_query = sprintf(
                '((Email:equals:%s) or (Secondary_Email:equals:%s) or (Third_Email:equals:%s))',
                $email,
                $email,
                $email
            );

            $response = wp_remote_get(
                'https://www.zohoapis.eu/crm/v2/Contacts/search?criteria=' . urlencode($search_query),
                [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $token
                    ],
                    'timeout' => 15
                ]
            );

            if (is_wp_error($response)) {
                $this->log('ERROR: Email CRM check failed: ' . $response->get_error_message());
                // Graceful degradation - don't block form submission
                wp_send_json_success([
                    'exists' => false,
                    'message' => 'CRM-Prüfung fehlgeschlagen - fortfahren'
                ]);
                return;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            // Check if contact found
            if (isset($body['data']) && !empty($body['data'])) {
                $contact = $body['data'][0];
                $membership_type = $contact['Membership_Type'] ?? '';

                $this->log('Email found in CRM for contact: ' . $contact['id'] . ' - Membership: ' . $membership_type);

                // Check if it's an actual member (not just a contact)
                $valid_member_types = [
                    'Ordentliches Mitglied',
                    'Außerordentliches Mitglied',
                    'Förderndes Mitglied',
                    'Korrespondierendes Mitglied'
                ];

                if (in_array($membership_type, $valid_member_types)) {
                    wp_send_json_error([
                        'exists' => true,
                        'message' => 'E-Mail-Adresse bei Mitglied gefunden, bitte andere E-Mail-Adresse nutzen.',
                        'contact_name' => $contact['Full_Name'] ?? 'Unbekannt',
                        'membership_type' => $membership_type
                    ]);
                    return;
                }

                // Contact exists but not a member - allow
                $this->log('Email found but contact is not a member - allowing');
            }

            // Email not found or not a member - OK
            wp_send_json_success([
                'exists' => false,
                'message' => 'E-Mail-Adresse verfügbar'
            ]);
        }

        public function ajax_submit_application() {
            // Force logging for debugging
            error_log('[DGPTM Mitgliedsantrag DEBUG] ajax_submit_application called');

            try {
                check_ajax_referer('dgptm_mitgliedsantrag_nonce', 'nonce');
                error_log('[DGPTM Mitgliedsantrag DEBUG] Nonce verified');
            } catch (Exception $e) {
                error_log('[DGPTM Mitgliedsantrag ERROR] Nonce verification failed: ' . $e->getMessage());
                wp_send_json_error(['message' => 'Sicherheitsprüfung fehlgeschlagen']);
                return;
            }

            $this->log('Application submission started');

            // Sanitize all input data
            $data = [
                'ansprache' => sanitize_text_field($_POST['ansprache'] ?? ''),
                'geburtsdatum' => sanitize_text_field($_POST['geburtsdatum'] ?? ''),
                'akad_titel' => sanitize_text_field($_POST['akad_titel'] ?? ''),
                'vorname' => sanitize_text_field($_POST['vorname'] ?? ''),
                'nachname' => sanitize_text_field($_POST['nachname'] ?? ''),
                'titel_nachgestellt' => sanitize_text_field($_POST['titel_nachgestellt'] ?? ''),
                'mitgliedsart' => sanitize_text_field($_POST['mitgliedsart'] ?? ''),
                'hat_qualifikation' => sanitize_text_field($_POST['hat_qualifikation'] ?? ''),
                'qualifikation_typ' => sanitize_text_field($_POST['qualifikation_typ'] ?? ''),
                'buerge1_name' => sanitize_text_field($_POST['buerge1_name'] ?? ''),
                'buerge1_email' => sanitize_email($_POST['buerge1_email'] ?? ''),
                'buerge1_id' => sanitize_text_field($_POST['buerge1_id'] ?? ''),
                'buerge2_name' => sanitize_text_field($_POST['buerge2_name'] ?? ''),
                'buerge2_email' => sanitize_email($_POST['buerge2_email'] ?? ''),
                'buerge2_id' => sanitize_text_field($_POST['buerge2_id'] ?? ''),
                'strasse' => sanitize_text_field($_POST['strasse'] ?? ''),
                'zusatz' => sanitize_text_field($_POST['zusatz'] ?? ''),
                'stadt' => sanitize_text_field($_POST['stadt'] ?? ''),
                'bundesland' => sanitize_text_field($_POST['bundesland'] ?? ''),
                'plz' => sanitize_text_field($_POST['plz'] ?? ''),
                'land' => sanitize_text_field($_POST['land'] ?? 'Deutschland'),
                'email1' => sanitize_email($_POST['email1'] ?? ''),
                'email2' => sanitize_email($_POST['email2'] ?? ''),
                'email3' => sanitize_email($_POST['email3'] ?? ''),
                'telefon1' => $this->normalize_phone_number(sanitize_text_field($_POST['telefon1'] ?? '')),
                'telefon2' => $this->normalize_phone_number(sanitize_text_field($_POST['telefon2'] ?? '')),
                'arbeitgeber' => sanitize_text_field($_POST['arbeitgeber'] ?? ''),
                'ist_student' => isset($_POST['ist_student']) && $_POST['ist_student'] === 'true',
                'studienrichtung' => sanitize_text_field($_POST['studienrichtung'] ?? ''),
                'studienbescheinigung_gueltig_bis' => sanitize_text_field($_POST['studienbescheinigung_gueltig_bis'] ?? ''),
                'satzung_akzeptiert' => isset($_POST['satzung_akzeptiert']) && $_POST['satzung_akzeptiert'] === 'true',
                'beitrag_akzeptiert' => isset($_POST['beitrag_akzeptiert']) && $_POST['beitrag_akzeptiert'] === 'true',
                'dsgvo_akzeptiert' => isset($_POST['dsgvo_akzeptiert']) && $_POST['dsgvo_akzeptiert'] === 'true'
            ];

            // Validate all required confirmations (Step 5)
            if (!$data['satzung_akzeptiert']) {
                error_log('[DGPTM Mitgliedsantrag ERROR] Satzung not accepted');
                wp_send_json_error(['message' => 'Bitte bestätigen Sie die Anerkennung der Satzung.']);
                return;
            }
            if (!$data['beitrag_akzeptiert']) {
                error_log('[DGPTM Mitgliedsantrag ERROR] Beitrag not accepted');
                wp_send_json_error(['message' => 'Bitte bestätigen Sie die Kenntnisnahme der Beitragspflicht.']);
                return;
            }
            if (!$data['dsgvo_akzeptiert']) {
                error_log('[DGPTM Mitgliedsantrag ERROR] DSGVO not accepted');
                wp_send_json_error(['message' => 'Bitte stimmen Sie der Datenschutzerklärung zu.']);
                return;
            }

            $this->log('Normalized phones - Phone1: ' . $data['telefon1'] . ', Phone2: ' . $data['telefon2']);

            // Handle file uploads
            error_log('[DGPTM Mitgliedsantrag DEBUG] Data collected, processing file uploads if needed');

            // Student certificate upload
            $studienbescheinigung_id = 0;
            if ($data['ist_student'] && isset($_FILES['studienbescheinigung'])) {
                error_log('[DGPTM Mitgliedsantrag DEBUG] Student certificate upload detected');
                $uploaded = $this->handle_file_upload($_FILES['studienbescheinigung']);
                if (!$uploaded) {
                    error_log('[DGPTM Mitgliedsantrag ERROR] Student certificate upload failed');
                    wp_send_json_error(['message' => 'Fehler beim Hochladen der Studienbescheinigung']);
                    return;
                }
                $studienbescheinigung_id = $uploaded;
                error_log('[DGPTM Mitgliedsantrag DEBUG] Student certificate uploaded, ID: ' . $studienbescheinigung_id);
            }

            // Qualification certificate upload
            $qualifikation_nachweis_id = 0;
            if ($data['hat_qualifikation'] === 'ja' && isset($_FILES['qualifikation_nachweis'])) {
                error_log('[DGPTM Mitgliedsantrag DEBUG] Qualification certificate upload detected');
                $uploaded = $this->handle_file_upload($_FILES['qualifikation_nachweis']);
                if (!$uploaded) {
                    error_log('[DGPTM Mitgliedsantrag ERROR] Qualification certificate upload failed');
                    wp_send_json_error(['message' => 'Fehler beim Hochladen des Qualifikationsnachweises']);
                    return;
                }
                $qualifikation_nachweis_id = $uploaded;
                error_log('[DGPTM Mitgliedsantrag DEBUG] Qualification certificate uploaded, ID: ' . $qualifikation_nachweis_id);
            }

            // Create or update contact in Zoho CRM
            error_log('[DGPTM Mitgliedsantrag DEBUG] Getting access token');
            $token = $this->get_access_token();
            if (!$token) {
                error_log('[DGPTM Mitgliedsantrag ERROR] No access token available');
                wp_send_json_error(['message' => 'OAuth-Verbindung nicht konfiguriert. Bitte konfigurieren Sie die Zoho CRM Verbindung in den Einstellungen.']);
                return;
            }

            error_log('[DGPTM Mitgliedsantrag DEBUG] Access token obtained, creating/updating contact');
            $contact_result = $this->create_or_update_contact($data, $studienbescheinigung_id, $qualifikation_nachweis_id, $token);

            // Check if error was returned (existing application/membership)
            if (is_array($contact_result) && isset($contact_result['error'])) {
                error_log('[DGPTM Mitgliedsantrag ERROR] Application blocked: ' . $contact_result['message']);
                wp_send_json_error(['message' => $contact_result['message']]);
                return;
            }

            // Check if contact creation failed
            if (!$contact_result) {
                error_log('[DGPTM Mitgliedsantrag ERROR] Contact creation/update returned false');
                wp_send_json_error(['message' => 'Fehler beim Erstellen des Kontakts in Zoho CRM. Bitte prüfen Sie das Debug-Log für Details.']);
                return;
            }

            $contact_id = $contact_result;
            error_log('[DGPTM Mitgliedsantrag DEBUG] Contact created/updated with ID: ' . $contact_id);

            // Trigger webhook
            error_log('[DGPTM Mitgliedsantrag DEBUG] Triggering webhook');
            $webhook_result = $this->trigger_webhook($data, $contact_id, $studienbescheinigung_id, $qualifikation_nachweis_id);

            if (!$webhook_result['success']) {
                error_log('[DGPTM Mitgliedsantrag WARNING] Webhook failed: ' . $webhook_result['message']);
                // Don't fail the whole application, just log the warning
            } else {
                error_log('[DGPTM Mitgliedsantrag DEBUG] Webhook triggered successfully');
            }

            // Schedule deletion of uploaded certificates after 10 minutes
            if ($studienbescheinigung_id > 0) {
                wp_schedule_single_event(time() + 600, 'dgptm_delete_student_certificate', [$studienbescheinigung_id]);
                error_log('[DGPTM Mitgliedsantrag DEBUG] Scheduled deletion of student certificate ID ' . $studienbescheinigung_id . ' in 10 minutes');
            }

            if ($qualifikation_nachweis_id > 0) {
                wp_schedule_single_event(time() + 600, 'dgptm_delete_student_certificate', [$qualifikation_nachweis_id]);
                error_log('[DGPTM Mitgliedsantrag DEBUG] Scheduled deletion of qualification certificate ID ' . $qualifikation_nachweis_id . ' in 10 minutes');
            }

            error_log('[DGPTM Mitgliedsantrag SUCCESS] Application submitted successfully for contact ' . $contact_id);

            wp_send_json_success([
                'message' => 'Ihr Mitgliedsantrag wurde erfolgreich eingereicht!',
                'contact_id' => $contact_id,
                'webhook_result' => $webhook_result
            ]);
        }

        private function handle_file_upload($file) {
            if (!function_exists('wp_handle_upload')) {
                require_once(ABSPATH . 'wp-admin/includes/file.php');
            }

            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];

            if (!in_array($file['type'], $allowed_types)) {
                $this->log('Invalid file type: ' . $file['type']);
                return false;
            }

            $upload_overrides = [
                'test_form' => false,
                'mimes' => [
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png' => 'image/png',
                    'pdf' => 'application/pdf'
                ]
            ];

            $uploaded_file = wp_handle_upload($file, $upload_overrides);

            if (isset($uploaded_file['error'])) {
                $this->log('File upload error: ' . $uploaded_file['error']);
                return false;
            }

            // Create attachment
            $attachment = [
                'post_mime_type' => $uploaded_file['type'],
                'post_title' => sanitize_file_name($file['name']),
                'post_content' => '',
                'post_status' => 'inherit'
            ];

            $attach_id = wp_insert_attachment($attachment, $uploaded_file['file']);

            if (!function_exists('wp_generate_attachment_metadata')) {
                require_once(ABSPATH . 'wp-admin/includes/image.php');
            }

            $attach_data = wp_generate_attachment_metadata($attach_id, $uploaded_file['file']);
            wp_update_attachment_metadata($attach_id, $attach_data);

            return $attach_id;
        }

        private function normalize_phone_number($phone) {
            if (empty($phone)) {
                return '';
            }

            // Remove all non-digit characters except + at start
            $phone = preg_replace('/[^\d+]/', '', $phone);

            // If already has country code, return as-is
            if (strpos($phone, '+') === 0) {
                return $phone;
            }

            // Get default country code from settings
            $options = dgptm_ma_get_options();
            $country_code = $options['default_country_code'] ?? '+49'; // Default to Germany

            // Remove leading zeros
            $phone = ltrim($phone, '0');

            // Add country code
            return $country_code . $phone;
        }

        private function get_field_mapping() {
            $options = dgptm_ma_get_options();

            // Get custom mapping or use defaults
            if (!empty($options['field_mapping'])) {
                $mapping = json_decode($options['field_mapping'], true);
                if (is_array($mapping)) {
                    return $mapping;
                }
            }

            // Default field mapping
            return [
                'First_Name' => 'vorname',
                'Last_Name' => 'nachname',
                'Academic_Title' => 'akad_titel',
                'greeting' => 'ansprache',
                'Date_of_Birth' => 'geburtsdatum',
                'Other_Street' => 'strasse',
                'Other_City' => 'stadt',
                'Other_State' => 'bundesland',
                'Other_Zip' => 'plz',
                'Other_Country' => 'land',
                'Email' => 'email1',
                'Secondary_Email' => 'email2',
                'Third_Email' => 'email3',
                'Phone' => 'telefon1',
                'Work_Phone' => 'telefon2',
                'employer_name' => 'arbeitgeber',
                'Guarantor_Name_1' => 'buerge1_name',
                'Guarantor_Mail_1' => 'buerge1_email',
                'Guarantor_Status_1' => 'buerge1_id',
                'Guarantor_Name_2' => 'buerge2_name',
                'Guarantor_Mail_2' => 'buerge2_email',
                'Guarantor_Status_2' => 'buerge2_id',
                'profession' => 'studienrichtung',
                'Freigestellt_bis' => 'studienbescheinigung_gueltig_bis',
                'Membership_Type' => 'mitgliedsart',
                'SatzungAkzeptiert' => 'satzung_akzeptiert',
                'BeitragAkzeptiert' => 'beitrag_akzeptiert',
                'Datenschutzakzeptiert' => 'dsgvo_akzeptiert'
            ];
        }

        private function create_or_update_contact($data, $studienbescheinigung_id, $qualifikation_nachweis_id, $token) {
            // Robust contact search by name + email
            $existing_contact = $this->search_contact_robust(
                $data['email1'],
                $data['vorname'],
                $data['nachname'],
                $token
            );

            $this->log('Creating contact data. Existing contact: ' . ($existing_contact ? $existing_contact['id'] : 'none'));

            // Check if contact has existing application or membership
            if ($existing_contact) {
                $status_check = $this->check_application_status($existing_contact);

                if ($status_check['has_membership'] || $status_check['has_application']) {
                    $this->log('ERROR: Application blocked - ' . $status_check['status_text']);

                    // Return error that will be caught by calling function
                    return [
                        'error' => true,
                        'message' => $status_check['status_text'] . ' Bitte wenden Sie sich an die Geschäftsstelle: https://perfusiologie.de/ueber-uns/kontakt/',
                        'contact_id' => $existing_contact['id']
                    ];
                }

                $this->log('Contact exists but no active application/membership - proceeding with update');
            }

            // Get field mapping
            $mapping = $this->get_field_mapping();
            $contact_data = [];

            // Helper function for robust boolean parsing
            $parse_bool = function($value) {
                return ($value === true || $value === 'true' || $value === '1' || $value === 1);
            };

            // Map form data to CRM fields
            foreach ($mapping as $crm_field => $form_field) {
                if (isset($data[$form_field])) {
                    $value = $data[$form_field];

                    // Special handling for confirmation checkbox fields
                    if (in_array($form_field, ['satzung_akzeptiert', 'beitrag_akzeptiert', 'dsgvo_akzeptiert'])) {
                        $contact_data[$crm_field] = $parse_bool($value);
                    }
                    // Special handling for guarantor status - handled separately in Bug 3 fix
                    elseif (in_array($form_field, ['buerge1_id', 'buerge2_id'])) {
                        // Zoho IDs are long numeric strings
                        $is_valid_zoho_id = !empty($value) && is_string($value) && strlen($value) > 10 && ctype_digit($value);
                        $contact_data[$crm_field] = $is_valid_zoho_id;
                    }
                    // Special handling for membership type
                    elseif ($form_field === 'mitgliedsart') {
                        $membership_map = [
                            'ordentliches' => 'Ordentliches Mitglied',
                            'außerordentliches' => 'Außerordentliches Mitglied',
                            'förderndes' => 'Förderndes Mitglied'
                        ];
                        $contact_data[$crm_field] = $membership_map[$value] ?? $value;
                    }
                    // Regular field mapping
                    else {
                        $contact_data[$crm_field] = $value;
                    }
                }
            }

            // Always add remark
            $contact_data['Bemerkung'] = 'Mitgliedsantrag über Online-Formular eingereicht';

            // Add student certificate URL if applicable
            if ($data['ist_student'] && $studienbescheinigung_id > 0) {
                $file_url = wp_get_attachment_url($studienbescheinigung_id);
                $contact_data['Bemerkung'] .= ' | Studienbescheinigung: ' . $file_url;
            }

            // Add qualification certificate URL if applicable
            if ($data['hat_qualifikation'] === 'ja' && $qualifikation_nachweis_id > 0) {
                $file_url = wp_get_attachment_url($qualifikation_nachweis_id);
                $contact_data['Bemerkung'] .= ' | Qualifikationsnachweis (' . $data['qualifikation_typ'] . '): ' . $file_url;
            }

            $this->log('Contact data prepared: ' . wp_json_encode($contact_data));

            if ($existing_contact) {
                // Update existing contact
                $contact_id = $existing_contact['id'];
                $this->log('Updating existing contact: ' . $contact_id);

                $response = wp_remote_request(
                    'https://www.zohoapis.eu/crm/v2/Contacts/' . $contact_id,
                    [
                        'method' => 'PUT',
                        'headers' => [
                            'Authorization' => 'Zoho-oauthtoken ' . $token,
                            'Content-Type' => 'application/json'
                        ],
                        'body' => wp_json_encode(['data' => [$contact_data]]),
                        'timeout' => 30
                    ]
                );
            } else {
                // Create new contact
                $this->log('Creating new contact');

                $response = wp_remote_post(
                    'https://www.zohoapis.eu/crm/v2/Contacts',
                    [
                        'headers' => [
                            'Authorization' => 'Zoho-oauthtoken ' . $token,
                            'Content-Type' => 'application/json'
                        ],
                        'body' => wp_json_encode(['data' => [$contact_data]]),
                        'timeout' => 30
                    ]
                );
            }

            if (is_wp_error($response)) {
                $this->log('ERROR: Contact creation/update failed: ' . $response->get_error_message());
                return false;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            $this->log('Zoho CRM HTTP Code: ' . $http_code);
            $this->log('Zoho CRM response: ' . wp_json_encode($body));

            if (isset($body['data'][0]['details']['id'])) {
                $contact_id = $body['data'][0]['details']['id'];
                $this->log('SUCCESS: Contact ID: ' . $contact_id);
            } elseif (isset($body['data'][0]['code']) && $body['data'][0]['code'] === 'SUCCESS') {
                $contact_id = $existing_contact['id'] ?? false;
                $this->log('SUCCESS: Updated contact ID: ' . $contact_id);
            } else {
                $this->log('ERROR: Unexpected response format');
                return false;
            }

            // Upload files to CRM file fields
            if ($contact_id) {
                // Upload qualification certificate to QualiNachweisDirekt field
                if ($data['hat_qualifikation'] === 'ja' && $qualifikation_nachweis_id > 0) {
                    $this->log('Uploading qualification certificate to CRM field QualiNachweisDirekt');
                    $upload_result = $this->upload_file_to_crm($contact_id, $qualifikation_nachweis_id, 'QualiNachweisDirekt', $token);
                    if ($upload_result) {
                        $this->log('SUCCESS: Qualification certificate uploaded to CRM');
                    } else {
                        $this->log('WARNING: Qualification certificate upload to CRM failed (will be in Bemerkung as fallback)');
                    }
                }

                // Upload student certificate to StudinachweisDirekt field
                if ($data['ist_student'] && $studienbescheinigung_id > 0) {
                    $this->log('Uploading student certificate to CRM field StudinachweisDirekt');
                    $upload_result = $this->upload_file_to_crm($contact_id, $studienbescheinigung_id, 'StudinachweisDirekt', $token);
                    if ($upload_result) {
                        $this->log('SUCCESS: Student certificate uploaded to CRM');
                    } else {
                        $this->log('WARNING: Student certificate upload to CRM failed (will be in Bemerkung as fallback)');
                    }
                }
            }

            return $contact_id;
        }

        /**
         * Upload file to Zoho CRM file field
         * Uses correct /crm/v2/files endpoint and links file ID to contact field
         *
         * @param string $contact_id Zoho Contact ID
         * @param int $attachment_id WordPress attachment ID
         * @param string $field_name CRM field name (QualiNachweisDirekt or StudinachweisDirekt)
         * @param string $token Zoho OAuth token
         * @return bool Success status
         */
        private function upload_file_to_crm($contact_id, $attachment_id, $field_name, $token) {
            $file_path = get_attached_file($attachment_id);
            $file_name = basename($file_path);

            if (!file_exists($file_path)) {
                $this->log('ERROR: File not found: ' . $file_path);
                return false;
            }

            $this->log('Uploading file to CRM: ' . $file_name . ' to field ' . $field_name);

            // Read file contents
            $file_contents = file_get_contents($file_path);
            if ($file_contents === false) {
                $this->log('ERROR: Could not read file contents');
                return false;
            }

            // Prepare multipart form data
            $boundary = wp_generate_password(24, false);
            $file_type = mime_content_type($file_path);

            // Build multipart body
            $body = '';
            $body .= '--' . $boundary . "\r\n";
            $body .= 'Content-Disposition: form-data; name="file"; filename="' . $file_name . '"' . "\r\n";
            $body .= 'Content-Type: ' . $file_type . "\r\n\r\n";
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
                $this->log('ERROR: CRM file upload failed: ' . $response->get_error_message());
                return false;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            $this->log('CRM file upload HTTP Code: ' . $http_code);
            $this->log('CRM file upload response: ' . wp_json_encode($response_body));

            // Get file_id from response
            if (!isset($response_body['data'][0]['details']['id'])) {
                $this->log('ERROR: No file ID in response');
                return false;
            }

            $file_id = $response_body['data'][0]['details']['id'];
            $this->log('File uploaded to CRM with file_id: ' . $file_id);

            // Step 2: Link file to contact field using PUT with file_id in array
            $this->log('Step 2: Linking file_id to contact field ' . $field_name);

            $update_data = [
                $field_name => [$file_id] // IMPORTANT: Must be an array
            ];

            $response = wp_remote_request(
                'https://www.zohoapis.eu/crm/v2/Contacts/' . $contact_id,
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
                $this->log('ERROR: Field linking failed: ' . $response->get_error_message());
                return false;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $response_body = json_decode(wp_remote_retrieve_body($response), true);

            $this->log('Field linking HTTP Code: ' . $http_code);
            $this->log('Field linking response: ' . wp_json_encode($response_body));

            if ($http_code >= 200 && $http_code < 300) {
                $this->log('SUCCESS: File linked to field ' . $field_name);
                return true;
            }

            $this->log('ERROR: Field linking returned non-success HTTP code: ' . $http_code);
            return false;
        }

        private function trigger_webhook($form_data, $contact_id, $studienbescheinigung_id, $qualifikation_nachweis_id) {
            $options = dgptm_ma_get_options();
            $webhook_url = $options['webhook_url'] ?? '';
            $test_mode = !empty($options['webhook_test_mode']);

            if (empty($webhook_url)) {
                $this->log('Webhook skipped: No webhook URL configured');
                return ['success' => true, 'message' => 'No webhook configured', 'skipped' => true];
            }

            // Prepare webhook payload
            $payload = [
                'event' => 'mitgliedsantrag_submitted',
                'timestamp' => current_time('mysql'),
                'test_mode' => $test_mode,
                'contact_id' => $contact_id,
                'form_data' => $form_data
            ];

            // Add student certificate URL and delete URL if available
            if ($studienbescheinigung_id > 0) {
                $payload['studienbescheinigung_url'] = wp_get_attachment_url($studienbescheinigung_id);
                $payload['studienbescheinigung_delete_url'] = home_url('/wp-json/dgptm/v1/delete-certificate/' . $studienbescheinigung_id);
            }

            // Add qualification certificate URL and delete URL if available
            if ($qualifikation_nachweis_id > 0) {
                $payload['qualifikation_nachweis_url'] = wp_get_attachment_url($qualifikation_nachweis_id);
                $payload['qualifikation_nachweis_delete_url'] = home_url('/wp-json/dgptm/v1/delete-certificate/' . $qualifikation_nachweis_id);
            }

            $this->log('Triggering webhook to: ' . $webhook_url . ($test_mode ? ' (TEST MODE)' : ''));
            $this->log('Webhook payload: ' . wp_json_encode($payload));

            $response = wp_remote_post(
                $webhook_url,
                [
                    'method' => 'POST',
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'X-DGPTM-Event' => 'mitgliedsantrag_submitted',
                        'X-DGPTM-Test-Mode' => $test_mode ? '1' : '0'
                    ],
                    'body' => wp_json_encode($payload),
                    'timeout' => 30
                ]
            );

            if (is_wp_error($response)) {
                $error_msg = $response->get_error_message();
                $this->log('ERROR: Webhook request failed: ' . $error_msg);
                return [
                    'success' => false,
                    'message' => 'Webhook request failed: ' . $error_msg
                ];
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            $this->log('Webhook HTTP Code: ' . $http_code);
            $this->log('Webhook response: ' . $body);

            $success = $http_code >= 200 && $http_code < 300;

            return [
                'success' => $success,
                'http_code' => $http_code,
                'message' => $success ? 'Webhook triggered successfully' : 'Webhook returned error code: ' . $http_code,
                'response_body' => $body
            ];
        }

        public function ajax_test_webhook() {
            check_ajax_referer('dgptm_mitgliedsantrag_admin_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $options = dgptm_ma_get_options();
            $webhook_url = $options['webhook_url'] ?? '';

            if (empty($webhook_url)) {
                wp_send_json_error(['message' => 'Keine Webhook-URL konfiguriert']);
            }

            // Get test type - simple or full
            $test_type = sanitize_text_field($_POST['test_type'] ?? 'simple');

            if ($test_type === 'full') {
                // Create FULL test payload with ALL form fields
                $test_payload = [
                    'event' => 'mitgliedsantrag_submitted',
                    'timestamp' => current_time('mysql'),
                    'test_mode' => true,
                    'contact_id' => '548256000023416013', // Real Zoho test ID
                    'form_data' => [
                        // Basisdaten (Step 1)
                        'ansprache' => 'Herr',
                        'geburtsdatum' => '1985-03-15',
                        'akad_titel' => 'Dr. med.',
                        'vorname' => 'Maximilian',
                        'nachname' => 'Mustermann',
                        'titel_nachgestellt' => 'MBA, ECCP',
                        'mitgliedsart' => 'ordentliches',

                        // Qualification (Step 1 - for ordentliches Mitglied)
                        'hat_qualifikation' => 'ja',
                        'qualifikation_typ' => 'ECCP',

                        // Adresse (Step 2)
                        'strasse' => 'Musterstraße 123',
                        'zusatz' => 'Hinterhaus, 2. Stock',
                        'stadt' => 'München',
                        'bundesland' => 'Bayern',
                        'plz' => '80331',
                        'land' => 'Deutschland',
                        'email1' => 'max.mustermann@example.com',
                        'email2' => 'dr.mustermann@klinik-example.de',
                        'email3' => 'm.mustermann@privat-example.com',
                        'telefon1' => '+4989123456789',
                        'telefon2' => '+491701234567',
                        'arbeitgeber' => 'Universitätsklinikum München',

                        // Student-Daten (Step 3 - optional)
                        'ist_student' => false,
                        'studienrichtung' => '',
                        'studienbescheinigung_gueltig_bis' => '',

                        // Bürgen (Step 4) - none required with qualification
                        'buerge1_name' => '',
                        'buerge1_email' => '',
                        'buerge1_id' => '',
                        'buerge2_name' => '',
                        'buerge2_email' => '',
                        'buerge2_id' => '',

                        // DSGVO (Step 5)
                        'dsgvo_akzeptiert' => true
                    ],
                    'studienbescheinigung_url' => null,
                    'studienbescheinigung_delete_url' => null,
                    'qualifikation_nachweis_url' => home_url('/wp-content/uploads/test-qualifikation.pdf'),
                    'qualifikation_nachweis_delete_url' => home_url('/wp-json/dgptm/v1/delete-certificate/99999')
                ];
            } else {
                // Simple connection test payload
                $test_payload = [
                    'event' => 'test_webhook',
                    'timestamp' => current_time('mysql'),
                    'test_mode' => true,
                    'message' => 'Dies ist ein einfacher Verbindungstest vom DGPTM Mitgliedsantrag Modul',
                    'contact_id' => 'TEST_CONNECTION',
                    'form_data' => [
                        'vorname' => 'Test',
                        'nachname' => 'Verbindung',
                        'email1' => 'test@example.com',
                        'telefon1' => '+491234567890',
                        'mitgliedsart' => 'ordentliches'
                    ]
                ];
            }

            $this->log('Testing webhook (' . $test_type . ' mode) to: ' . $webhook_url);

            $event_type = $test_type === 'full' ? 'mitgliedsantrag_submitted' : 'test_webhook';

            $response = wp_remote_post(
                $webhook_url,
                [
                    'method' => 'POST',
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'X-DGPTM-Event' => $event_type,
                        'X-DGPTM-Test-Mode' => '1'
                    ],
                    'body' => wp_json_encode($test_payload),
                    'timeout' => 30
                ]
            );

            if (is_wp_error($response)) {
                $error_msg = $response->get_error_message();
                $this->log('ERROR: Test webhook failed: ' . $error_msg);
                wp_send_json_error([
                    'message' => 'Webhook-Test fehlgeschlagen: ' . $error_msg,
                    'test_type' => $test_type
                ]);
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            $this->log('Test webhook HTTP Code: ' . $http_code);
            $this->log('Test webhook response: ' . $body);

            $success = $http_code >= 200 && $http_code < 300;

            $message = $success ? 'Webhook-Test erfolgreich!' : 'Webhook erreichbar, aber Fehlercode zurückgegeben';
            if ($test_type === 'full') {
                $message .= ' (Vollständige Testdaten mit allen Feldern gesendet)';
            }

            wp_send_json_success([
                'message' => $message,
                'http_code' => $http_code,
                'response_body' => $body,
                'sent_payload' => $test_payload,
                'test_type' => $test_type
            ]);
        }

        public function delete_student_certificate($attachment_id) {
            $this->log('Executing scheduled deletion of certificate ID: ' . $attachment_id);

            // Check if attachment exists
            if (!get_post($attachment_id)) {
                $this->log('Certificate ID ' . $attachment_id . ' does not exist, skipping deletion');
                return;
            }

            // Get file path before deletion for logging
            $file_path = get_attached_file($attachment_id);

            // Delete the attachment and its file
            $deleted = wp_delete_attachment($attachment_id, true);

            if ($deleted) {
                $this->log('Successfully deleted certificate ID ' . $attachment_id . ' (File: ' . $file_path . ')');
            } else {
                $this->log('ERROR: Failed to delete certificate ID ' . $attachment_id);
            }
        }

        // =====================================================================
        // VORSTANDSGENEHMIGUNG SHORTCODE
        // =====================================================================

        /**
         * Rendert das Vorstandsgenehmigung-Formular
         * GET-Parameter: ID = Zoho Contact ID des Antragstellers
         *                VMID = Zoho Contact ID des Vorstandsmitglieds
         */
        public function render_vorstandsgenehmigung($atts) {
            // Parameter aus URL holen
            $antragsteller_id = isset($_GET['ID']) ? sanitize_text_field($_GET['ID']) : '';
            $vorstand_id = isset($_GET['VMID']) ? sanitize_text_field($_GET['VMID']) : '';

            // Parameter validieren
            if (empty($antragsteller_id) || empty($vorstand_id)) {
                return $this->render_error_message(
                    'Fehlende Parameter',
                    'Bitte verwenden Sie den vollstaendigen Link aus der E-Mail. Es fehlen erforderliche Identifikationsparameter.'
                );
            }

            // Token holen
            $token = $this->get_access_token();
            if (!$token) {
                return $this->render_error_message(
                    'Verbindungsfehler',
                    'Die Verbindung zum CRM-System konnte nicht hergestellt werden. Bitte versuchen Sie es spaeter erneut.'
                );
            }

            // Pruefen ob Vorstandsmitglied den Tag "Vorstand" hat
            $vorstand_check = $this->check_vorstand_tag($vorstand_id, $token);
            if (!$vorstand_check['is_vorstand']) {
                return $this->render_error_message(
                    'Keine Berechtigung',
                    'Sie sind nicht als Vorstandsmitglied autorisiert, Mitgliedsantraege zu bearbeiten.'
                );
            }

            // Pruefen ob dieses Vorstandsmitglied bereits abgestimmt hat
            $bereits_abgestimmt = $this->hat_bereits_abgestimmt($antragsteller_id, $vorstand_id, $token);
            if ($bereits_abgestimmt) {
                return $this->render_error_message(
                    'Bereits abgestimmt',
                    'Sie haben fuer diesen Antrag bereits eine Entscheidung getroffen. Eine erneute Abstimmung ist nicht moeglich.'
                );
            }

            // Antragsteller-Daten laden
            $antragsteller = $this->get_contact_details($antragsteller_id, $token);
            if (!$antragsteller) {
                return $this->render_error_message(
                    'Antragsteller nicht gefunden',
                    'Die Daten des Antragstellers konnten nicht geladen werden. Bitte pruefen Sie den Link.'
                );
            }

            // Vorstandsmitglied-Daten laden (fuer Anzeige)
            $vorstand = $this->get_contact_details($vorstand_id, $token);

            // Frontend Assets laden
            wp_enqueue_style(
                'dgptm-vorstandsgenehmigung',
                $this->plugin_url . 'assets/css/vorstandsgenehmigung.css',
                [],
                $this->version
            );

            wp_enqueue_script(
                'dgptm-vorstandsgenehmigung',
                $this->plugin_url . 'assets/js/vorstandsgenehmigung.js',
                ['jquery'],
                $this->version,
                true
            );

            wp_localize_script('dgptm-vorstandsgenehmigung', 'dgptmVorstand', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('dgptm_vorstand_entscheidung'),
                'antragstellerId' => $antragsteller_id,
                'vorstandId' => $vorstand_id,
                'strings' => [
                    'confirm_approve' => 'Moechten Sie diesen Mitgliedsantrag wirklich GENEHMIGEN?',
                    'confirm_reject' => 'Moechten Sie diesen Mitgliedsantrag wirklich ABLEHNEN?',
                    'processing' => 'Wird verarbeitet...',
                    'success_approved' => 'Der Antrag wurde erfolgreich genehmigt.',
                    'success_rejected' => 'Der Antrag wurde abgelehnt.',
                    'error' => 'Ein Fehler ist aufgetreten. Bitte versuchen Sie es erneut.'
                ]
            ]);

            // Template rendern
            ob_start();
            ?>
            <div class="dgptm-vorstandsgenehmigung-container">
                <div class="dgptm-vg-header">
                    <h2>Mitgliedsantrag zur Genehmigung</h2>
                    <p class="dgptm-vg-info">
                        Sie bearbeiten diesen Antrag als: <strong><?php echo esc_html($vorstand['Full_Name'] ?? ($vorstand['First_Name'] . ' ' . $vorstand['Last_Name'])); ?></strong>
                    </p>
                </div>

                <div class="dgptm-vg-antragsteller">
                    <h3>Antragsteller</h3>

                    <div class="dgptm-vg-section">
                        <h4>Persoenliche Daten</h4>
                        <table class="dgptm-vg-table">
                            <tr>
                                <th>Anrede:</th>
                                <td><?php echo esc_html($antragsteller['greeting'] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Akadem. Titel:</th>
                                <td><?php echo esc_html($antragsteller['Academic_Title'] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Vorname:</th>
                                <td><?php echo esc_html($antragsteller['First_Name'] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Nachname:</th>
                                <td><?php echo esc_html($antragsteller['Last_Name'] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Geburtsdatum:</th>
                                <td><?php echo esc_html($this->format_date($antragsteller['Date_of_Birth'] ?? '')); ?></td>
                            </tr>
                        </table>
                    </div>

                    <div class="dgptm-vg-section">
                        <h4>Kontaktdaten</h4>
                        <table class="dgptm-vg-table">
                            <tr>
                                <th>E-Mail 1:</th>
                                <td><?php echo esc_html($antragsteller['Email'] ?? '-'); ?></td>
                            </tr>
                            <?php if (!empty($antragsteller['Secondary_Email'])): ?>
                            <tr>
                                <th>E-Mail 2:</th>
                                <td><?php echo esc_html($antragsteller['Secondary_Email']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($antragsteller['Third_Email'])): ?>
                            <tr>
                                <th>E-Mail 3:</th>
                                <td><?php echo esc_html($antragsteller['Third_Email']); ?></td>
                            </tr>
                            <?php endif; ?>
                            <tr>
                                <th>Telefon:</th>
                                <td><?php echo esc_html($antragsteller['Phone'] ?? '-'); ?></td>
                            </tr>
                            <?php if (!empty($antragsteller['Work_Phone'])): ?>
                            <tr>
                                <th>Telefon (Arbeit):</th>
                                <td><?php echo esc_html($antragsteller['Work_Phone']); ?></td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>

                    <div class="dgptm-vg-section">
                        <h4>Adresse</h4>
                        <table class="dgptm-vg-table">
                            <tr>
                                <th>Strasse:</th>
                                <td><?php echo esc_html($antragsteller['Other_Street'] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <th>PLZ / Ort:</th>
                                <td><?php echo esc_html(($antragsteller['Other_Zip'] ?? '') . ' ' . ($antragsteller['Other_City'] ?? '')); ?></td>
                            </tr>
                            <tr>
                                <th>Bundesland:</th>
                                <td><?php echo esc_html($antragsteller['Other_State'] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Land:</th>
                                <td><?php echo esc_html($antragsteller['Other_Country'] ?? '-'); ?></td>
                            </tr>
                        </table>
                    </div>

                    <div class="dgptm-vg-section">
                        <h4>Mitgliedschaft</h4>
                        <table class="dgptm-vg-table">
                            <tr>
                                <th>Beantragte Mitgliedsart:</th>
                                <td><strong><?php echo esc_html($antragsteller['Membership_Type'] ?? '-'); ?></strong></td>
                            </tr>
                            <tr>
                                <th>Arbeitgeber:</th>
                                <td><?php echo esc_html($antragsteller['employer_name'] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <th>Beruf/Studienrichtung:</th>
                                <td><?php echo esc_html($antragsteller['profession'] ?? '-'); ?></td>
                            </tr>
                        </table>
                    </div>

                    <?php if (!empty($antragsteller['Guarantor_Name_1']) || !empty($antragsteller['Guarantor_Name_2'])): ?>
                    <div class="dgptm-vg-section">
                        <h4>Buergen</h4>
                        <table class="dgptm-vg-table">
                            <?php if (!empty($antragsteller['Guarantor_Name_1'])): ?>
                            <tr>
                                <th>Buerge 1:</th>
                                <td>
                                    <?php echo esc_html($antragsteller['Guarantor_Name_1']); ?>
                                    <?php if (!empty($antragsteller['Guarantor_Mail_1'])): ?>
                                        (<?php echo esc_html($antragsteller['Guarantor_Mail_1']); ?>)
                                    <?php endif; ?>
                                    <?php if (!empty($antragsteller['Guarantor_Status_1'])): ?>
                                        <span class="dgptm-vg-status"><?php echo esc_html($antragsteller['Guarantor_Status_1']); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($antragsteller['Guarantor_Name_2'])): ?>
                            <tr>
                                <th>Buerge 2:</th>
                                <td>
                                    <?php echo esc_html($antragsteller['Guarantor_Name_2']); ?>
                                    <?php if (!empty($antragsteller['Guarantor_Mail_2'])): ?>
                                        (<?php echo esc_html($antragsteller['Guarantor_Mail_2']); ?>)
                                    <?php endif; ?>
                                    <?php if (!empty($antragsteller['Guarantor_Status_2'])): ?>
                                        <span class="dgptm-vg-status"><?php echo esc_html($antragsteller['Guarantor_Status_2']); ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <?php endif; ?>

                    <?php
                    // Nachweise anzeigen
                    $nachweise = $this->get_contact_attachments($antragsteller_id, $token);
                    if (!empty($nachweise)):
                    ?>
                    <div class="dgptm-vg-section">
                        <h4>Hochgeladene Nachweise</h4>
                        <div class="dgptm-vg-attachments">
                            <?php foreach ($nachweise as $nachweis): ?>
                            <div class="dgptm-vg-attachment">
                                <span class="dgptm-vg-attachment-icon">📄</span>
                                <a href="<?php echo esc_url($nachweis['url']); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo esc_html($nachweis['name']); ?>
                                </a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="dgptm-vg-section">
                        <h4>Akzeptierte Erklaerungen</h4>
                        <table class="dgptm-vg-table">
                            <tr>
                                <th>Satzung akzeptiert:</th>
                                <td><?php echo $antragsteller['SatzungAkzeptiert'] ? '✓ Ja' : '✗ Nein'; ?></td>
                            </tr>
                            <tr>
                                <th>Beitragsordnung akzeptiert:</th>
                                <td><?php echo $antragsteller['BeitragAkzeptiert'] ? '✓ Ja' : '✗ Nein'; ?></td>
                            </tr>
                            <tr>
                                <th>Datenschutz akzeptiert:</th>
                                <td><?php echo $antragsteller['Datenschutzakzeptiert'] ? '✓ Ja' : '✗ Nein'; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>

                <div class="dgptm-vg-entscheidung">
                    <h3>Ihre Entscheidung</h3>

                    <div class="dgptm-vg-kommentar">
                        <label for="dgptm-vg-bemerkung">Bemerkung (optional):</label>
                        <textarea id="dgptm-vg-bemerkung" rows="3" placeholder="Optionale Bemerkung zur Entscheidung..."></textarea>
                    </div>

                    <div class="dgptm-vg-buttons">
                        <button type="button" class="dgptm-vg-btn dgptm-vg-btn-approve" data-action="approve">
                            ✓ Antrag genehmigen
                        </button>
                        <button type="button" class="dgptm-vg-btn dgptm-vg-btn-reject" data-action="reject">
                            ✗ Antrag ablehnen
                        </button>
                    </div>

                    <div class="dgptm-vg-result" style="display: none;"></div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Rendert eine Fehlermeldung
         */
        private function render_error_message($title, $message) {
            return sprintf(
                '<div class="dgptm-vorstandsgenehmigung-error">
                    <h3>%s</h3>
                    <p>%s</p>
                </div>',
                esc_html($title),
                esc_html($message)
            );
        }

        /**
         * Formatiert ein Datum fuer die Anzeige
         */
        private function format_date($date) {
            if (empty($date)) return '-';
            $timestamp = strtotime($date);
            if (!$timestamp) return $date;
            return date_i18n('d.m.Y', $timestamp);
        }

        /**
         * Prueft ob ein Kontakt den Tag "Vorstand" hat
         */
        private function check_vorstand_tag($contact_id, $token) {
            $contact = $this->get_contact_details($contact_id, $token);

            if (!$contact) {
                $this->log('check_vorstand_tag: Contact not found: ' . $contact_id);
                return ['is_vorstand' => false, 'error' => 'Kontakt nicht gefunden'];
            }

            // Tags aus dem Kontakt holen
            $tags = $contact['Tag'] ?? [];

            $this->log('check_vorstand_tag: Contact ' . $contact_id . ' has tags: ' . wp_json_encode($tags));

            // Wenn Tag ein String ist (z.B. kommasepariert)
            if (is_string($tags)) {
                $tags = array_map('trim', explode(',', $tags));
            }

            // Auch alternative Feldnamen pruefen
            if (empty($tags)) {
                if (isset($contact['Tags'])) {
                    $tags = $contact['Tags'];
                } elseif (isset($contact['tag'])) {
                    $tags = $contact['tag'];
                }

                if (is_string($tags)) {
                    $tags = array_map('trim', explode(',', $tags));
                }
            }

            $is_vorstand = false;

            if (is_array($tags)) {
                foreach ($tags as $tag) {
                    // Zoho gibt Tags als Array von Objekten zurueck: [{"name": "Vorstand", "id": "123"}]
                    if (is_array($tag)) {
                        $tag_name = $tag['name'] ?? $tag['Name'] ?? '';
                        $this->log('check_vorstand_tag: Checking tag object: ' . wp_json_encode($tag) . ' -> name: ' . $tag_name);
                        if (strtolower(trim($tag_name)) === 'vorstand') {
                            $is_vorstand = true;
                            break;
                        }
                    } elseif (is_string($tag)) {
                        $this->log('check_vorstand_tag: Checking tag string: ' . $tag);
                        if (strtolower(trim($tag)) === 'vorstand') {
                            $is_vorstand = true;
                            break;
                        }
                    }
                }
            }

            $this->log('check_vorstand_tag: Result for ' . $contact_id . ': is_vorstand=' . ($is_vorstand ? 'true' : 'false'));

            return [
                'is_vorstand' => $is_vorstand,
                'contact' => $contact
            ];
        }

        /**
         * Prueft ob ein Vorstandsmitglied bereits ueber diesen Antrag abgestimmt hat
         * Speichert Abstimmungen im Feld "Vorstand_Abstimmungen" als JSON
         */
        private function hat_bereits_abgestimmt($antragsteller_id, $vorstand_id, $token) {
            $antragsteller = $this->get_contact_details($antragsteller_id, $token);

            if (!$antragsteller) {
                return false;
            }

            // Abstimmungen aus dem Kontakt laden
            $abstimmungen_raw = $antragsteller['Vorstand_Abstimmungen'] ?? '';

            if (empty($abstimmungen_raw)) {
                return false;
            }

            // Als JSON dekodieren
            $abstimmungen = json_decode($abstimmungen_raw, true);

            if (!is_array($abstimmungen)) {
                return false;
            }

            // Pruefen ob dieses Vorstandsmitglied bereits abgestimmt hat
            foreach ($abstimmungen as $abstimmung) {
                if (isset($abstimmung['vorstand_id']) && $abstimmung['vorstand_id'] === $vorstand_id) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Holt detaillierte Kontaktinformationen aus Zoho CRM
         */
        private function get_contact_details($contact_id, $token) {
            $response = wp_remote_get(
                'https://www.zohoapis.eu/crm/v2/Contacts/' . $contact_id,
                [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $token
                    ],
                    'timeout' => 30
                ]
            );

            if (is_wp_error($response)) {
                $this->log('ERROR: Failed to get contact details: ' . $response->get_error_message());
                return null;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (!isset($body['data'][0])) {
                $this->log('ERROR: Contact not found: ' . $contact_id);
                return null;
            }

            return $body['data'][0];
        }

        /**
         * Holt Anhaenge eines Kontakts aus Zoho CRM
         */
        private function get_contact_attachments($contact_id, $token) {
            $attachments = [];

            // Versuche verschiedene Felder fuer Anhaenge
            $attachment_fields = [
                'Studienbescheinigung',
                'Qualifikationsnachweis',
                'Student_Certificate',
                'Qualification_Certificate'
            ];

            $contact = $this->get_contact_details($contact_id, $token);

            if (!$contact) {
                return $attachments;
            }

            foreach ($attachment_fields as $field) {
                if (!empty($contact[$field])) {
                    $file_ids = $contact[$field];

                    if (!is_array($file_ids)) {
                        $file_ids = [$file_ids];
                    }

                    foreach ($file_ids as $file_id) {
                        // Download-URL generieren
                        $download_url = 'https://www.zohoapis.eu/crm/v2/Contacts/' . $contact_id . '/Attachments/' . $file_id;

                        $attachments[] = [
                            'id' => $file_id,
                            'name' => $field,
                            'url' => $download_url . '?oauth_token=' . $token
                        ];
                    }
                }
            }

            // Auch allgemeine Attachments pruefen
            $attachments_response = wp_remote_get(
                'https://www.zohoapis.eu/crm/v2/Contacts/' . $contact_id . '/Attachments',
                [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $token
                    ],
                    'timeout' => 30
                ]
            );

            if (!is_wp_error($attachments_response)) {
                $att_body = json_decode(wp_remote_retrieve_body($attachments_response), true);

                if (isset($att_body['data']) && is_array($att_body['data'])) {
                    foreach ($att_body['data'] as $att) {
                        $attachments[] = [
                            'id' => $att['id'],
                            'name' => $att['File_Name'] ?? $att['$file_name'] ?? 'Dokument',
                            'url' => 'https://www.zohoapis.eu/crm/v2/Contacts/' . $contact_id . '/Attachments/' . $att['id'] . '?oauth_token=' . $token
                        ];
                    }
                }
            }

            return $attachments;
        }

        /**
         * AJAX Handler fuer Vorstandsentscheidung
         */
        public function ajax_vorstand_entscheidung() {
            check_ajax_referer('dgptm_vorstand_entscheidung', 'nonce');

            $antragsteller_id = sanitize_text_field($_POST['antragsteller_id'] ?? '');
            $vorstand_id = sanitize_text_field($_POST['vorstand_id'] ?? '');
            $action = sanitize_text_field($_POST['entscheidung'] ?? '');
            $bemerkung = sanitize_textarea_field($_POST['bemerkung'] ?? '');

            if (empty($antragsteller_id) || empty($vorstand_id) || empty($action)) {
                wp_send_json_error(['message' => 'Fehlende Parameter']);
                return;
            }

            if (!in_array($action, ['approve', 'reject'])) {
                wp_send_json_error(['message' => 'Ungueltige Aktion']);
                return;
            }

            // Token holen
            $token = $this->get_access_token();
            if (!$token) {
                wp_send_json_error(['message' => 'CRM-Verbindung fehlgeschlagen']);
                return;
            }

            // Nochmals pruefen ob Vorstand
            $vorstand_check = $this->check_vorstand_tag($vorstand_id, $token);
            if (!$vorstand_check['is_vorstand']) {
                wp_send_json_error(['message' => 'Keine Vorstandsberechtigung']);
                return;
            }

            // Nochmals pruefen ob bereits abgestimmt
            if ($this->hat_bereits_abgestimmt($antragsteller_id, $vorstand_id, $token)) {
                wp_send_json_error(['message' => 'Sie haben bereits abgestimmt']);
                return;
            }

            // Vorstandsmitglied-Details holen
            $vorstand = $this->get_contact_details($vorstand_id, $token);
            $vorstand_name = $vorstand ? ($vorstand['Full_Name'] ?? ($vorstand['First_Name'] . ' ' . $vorstand['Last_Name'])) : 'Unbekannt';

            // Abstimmung speichern
            $result = $this->speichere_abstimmung($antragsteller_id, $vorstand_id, $vorstand_name, $action, $bemerkung, $token);

            if (!$result['success']) {
                wp_send_json_error(['message' => $result['message']]);
                return;
            }

            $action_text = $action === 'approve' ? 'genehmigt' : 'abgelehnt';
            wp_send_json_success([
                'message' => 'Ihre Entscheidung wurde gespeichert. Der Antrag wurde ' . $action_text . '.',
                'action' => $action
            ]);
        }

        /**
         * Speichert eine Vorstandsabstimmung im Zoho CRM
         */
        private function speichere_abstimmung($antragsteller_id, $vorstand_id, $vorstand_name, $entscheidung, $bemerkung, $token) {
            // Aktuelle Abstimmungen laden
            $antragsteller = $this->get_contact_details($antragsteller_id, $token);

            if (!$antragsteller) {
                return ['success' => false, 'message' => 'Antragsteller nicht gefunden'];
            }

            // Bestehende Abstimmungen laden
            $abstimmungen_raw = $antragsteller['Vorstand_Abstimmungen'] ?? '';
            $abstimmungen = [];

            if (!empty($abstimmungen_raw)) {
                $decoded = json_decode($abstimmungen_raw, true);
                if (is_array($decoded)) {
                    $abstimmungen = $decoded;
                }
            }

            // Neue Abstimmung hinzufuegen
            $abstimmungen[] = [
                'vorstand_id' => $vorstand_id,
                'vorstand_name' => $vorstand_name,
                'entscheidung' => $entscheidung,
                'bemerkung' => $bemerkung,
                'datum' => current_time('mysql')
            ];

            // Gesamtergebnis berechnen (alle bisherigen Abstimmungen)
            $genehmigt_count = 0;
            $abgelehnt_count = 0;
            foreach ($abstimmungen as $a) {
                if ($a['entscheidung'] === 'approve') {
                    $genehmigt_count++;
                } else {
                    $abgelehnt_count++;
                }
            }

            // Update-Daten vorbereiten
            $update_data = [
                'Vorstand_Abstimmungen' => wp_json_encode($abstimmungen),
                'Vorstand_Genehmigungen' => $genehmigt_count,
                'Vorstand_Ablehnungen' => $abgelehnt_count,
                'Letzte_Vorstand_Abstimmung' => current_time('Y-m-d')
            ];

            // Wenn abgelehnt, Antragsstatus aktualisieren
            if ($entscheidung === 'reject') {
                $update_data['Application_Status'] = 'Abgelehnt';
                $update_data['Antragsstatus'] = 'Abgelehnt durch Vorstand';
            }

            // Update im CRM ausfuehren
            $response = wp_remote_request(
                'https://www.zohoapis.eu/crm/v2/Contacts/' . $antragsteller_id,
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
                $this->log('ERROR: Failed to save vote: ' . $response->get_error_message());
                return ['success' => false, 'message' => 'Speichern fehlgeschlagen: ' . $response->get_error_message()];
            }

            $http_code = wp_remote_retrieve_response_code($response);

            if ($http_code >= 200 && $http_code < 300) {
                $this->log('Vote saved successfully for contact ' . $antragsteller_id . ' by ' . $vorstand_id);
                return ['success' => true, 'message' => 'Abstimmung gespeichert'];
            }

            $body = wp_remote_retrieve_body($response);
            $this->log('ERROR: Vote save returned HTTP ' . $http_code . ': ' . $body);
            return ['success' => false, 'message' => 'CRM-Fehler: HTTP ' . $http_code];
        }

        private function log($message) {
            if (defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
                error_log('[DGPTM Mitgliedsantrag] ' . $message);
            }
        }
    }
}

// Initialize the module
if (!isset($GLOBALS['dgptm_mitgliedsantrag_initialized'])) {
    $GLOBALS['dgptm_mitgliedsantrag_initialized'] = true;
    DGPTM_Mitgliedsantrag::get_instance();
}
