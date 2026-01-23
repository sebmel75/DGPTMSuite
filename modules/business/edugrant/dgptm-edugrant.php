<?php
/**
 * Plugin Name: DGPTM - EduGrant Manager
 * Description: EduGrant-Verwaltung mit Zoho CRM Integration. Zeigt Events an und ermöglicht Beantragung von EduGrants.
 * Version: 1.0.0
 * Author: Sebastian Melzer
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('DGPTM_EduGrant_Manager')) {

    class DGPTM_EduGrant_Manager {

        private static $instance = null;
        private $plugin_path;
        private $plugin_url;

        // Zoho CRM API Endpoints
        const ZOHO_COQL_ENDPOINT = 'https://www.zohoapis.eu/crm/v8/coql';

        // Zoho Module names (as they appear in API)
        const ZOHO_MODULE_EVENTS = 'DGFK_Events';
        const ZOHO_MODULE_EDUGRANT = 'EduGrant';
        const ZOHO_MODULE_TICKETS = 'Ticket';
        const ZOHO_MODULE_CONTACTS = 'Contacts';

        // Valid ticket statuses for EduGrant eligibility
        const VALID_TICKET_STATUSES = ['Bezahlt', 'Freiticket', 'ReferentIn', 'Nicht abgerechnet'];

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->plugin_path = plugin_dir_path(__FILE__);
            $this->plugin_url = plugin_dir_url(__FILE__);

            // Register shortcodes
            add_shortcode('edugrant_events', [$this, 'shortcode_events']);
            add_shortcode('meine_edugrantes', [$this, 'shortcode_user_edugrantes']);
            add_shortcode('edugrant_antragsformular', [$this, 'shortcode_application_form']);

            // Enqueue scripts/styles
            add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

            // AJAX handlers (logged in users)
            add_action('wp_ajax_dgptm_edugrant_submit', [$this, 'ajax_submit_application']);
            add_action('wp_ajax_dgptm_edugrant_get_events', [$this, 'ajax_get_events']);
            add_action('wp_ajax_dgptm_edugrant_get_user_grants', [$this, 'ajax_get_user_grants']);
            add_action('wp_ajax_dgptm_edugrant_get_grant_details', [$this, 'ajax_get_grant_details']);
            add_action('wp_ajax_dgptm_edugrant_submit_documents', [$this, 'ajax_submit_documents']);
            add_action('wp_ajax_dgptm_edugrant_get_event_details', [$this, 'ajax_get_event_details']);
            add_action('wp_ajax_dgptm_edugrant_check_ticket', [$this, 'ajax_check_ticket_eligibility']);
            add_action('wp_ajax_dgptm_edugrant_check_guest_email', [$this, 'ajax_check_guest_email']);

            // AJAX handlers (non-logged-in users / guests)
            add_action('wp_ajax_nopriv_dgptm_edugrant_get_events', [$this, 'ajax_get_events']);
            add_action('wp_ajax_nopriv_dgptm_edugrant_get_event_details', [$this, 'ajax_get_event_details']);
            add_action('wp_ajax_nopriv_dgptm_edugrant_check_guest_email', [$this, 'ajax_check_guest_email']);
            add_action('wp_ajax_nopriv_dgptm_edugrant_submit', [$this, 'ajax_submit_guest_application']);

            // Admin menu for settings
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
        }

        /**
         * Enqueue frontend assets
         */
        public function enqueue_assets() {
            if (!is_admin()) {
                wp_enqueue_style(
                    'dgptm-edugrant-style',
                    $this->plugin_url . 'assets/css/edugrant.css',
                    [],
                    '1.0.0'
                );

                wp_enqueue_script(
                    'dgptm-edugrant-script',
                    $this->plugin_url . 'assets/js/edugrant.js',
                    ['jquery'],
                    '1.0.0',
                    true
                );

                wp_localize_script('dgptm-edugrant-script', 'dgptmEdugrant', [
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce('dgptm_edugrant_nonce'),
                    'i18n' => [
                        'loading' => __('Laden...', 'dgptm-edugrant'),
                        'error' => __('Ein Fehler ist aufgetreten.', 'dgptm-edugrant'),
                        'success' => __('Antrag erfolgreich eingereicht!', 'dgptm-edugrant'),
                        'confirmSubmit' => __('Möchten Sie diesen EduGrant-Antrag wirklich einreichen?', 'dgptm-edugrant'),
                    ]
                ]);
            }
        }

        /**
         * Get valid OAuth access token from crm-abruf module
         */
        private function get_access_token() {
            // Check if crm-abruf class exists
            if (class_exists('DGPTM_CRM_Abruf')) {
                $crm = DGPTM_CRM_Abruf::get_instance();
                if (method_exists($crm, 'get_oauth_token')) {
                    return $crm->get_oauth_token();
                }
            }

            // Fallback: Try to get token directly from options
            $access_token = get_option('dgptm_zoho_access_token', '');
            $expires_at = (int) get_option('dgptm_zoho_token_expires', 0);

            if (!empty($access_token) && time() < $expires_at) {
                return $access_token;
            }

            // Try to refresh token
            return $this->refresh_access_token();
        }

        /**
         * Refresh OAuth token
         */
        private function refresh_access_token() {
            $refresh_token = get_option('dgptm_zoho_refresh_token', '');
            $client_id = get_option('dgptm_zoho_client_id', '');
            $client_secret = get_option('dgptm_zoho_client_secret', '');

            if (empty($refresh_token) || empty($client_id) || empty($client_secret)) {
                error_log('EduGrant: Missing OAuth2 configuration');
                return new WP_Error('oauth_error', 'Fehlende OAuth2-Konfiguration.');
            }

            $response = wp_remote_post('https://accounts.zoho.eu/oauth/v2/token', [
                'body' => [
                    'refresh_token' => $refresh_token,
                    'client_id' => $client_id,
                    'client_secret' => $client_secret,
                    'grant_type' => 'refresh_token'
                ],
                'timeout' => 20
            ]);

            if (is_wp_error($response)) {
                error_log('EduGrant: Token refresh failed: ' . $response->get_error_message());
                return $response;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['access_token'])) {
                $access_token = sanitize_text_field($body['access_token']);
                $expires_in = isset($body['expires_in']) ? (int) $body['expires_in'] : 3600;

                update_option('dgptm_zoho_access_token', $access_token);
                update_option('dgptm_zoho_token_expires', time() + $expires_in - 60);

                return $access_token;
            }

            error_log('EduGrant: No access token in response: ' . print_r($body, true));
            return new WP_Error('oauth_error', 'Kein Zugriffstoken erhalten.');
        }

        /**
         * Log message to DGPTM Suite Logger or error_log
         */
        private function log($message, $context = [], $level = 'info') {
            $log_entry = '[EduGrant] ' . $message;
            if (!empty($context)) {
                $log_entry .= ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

            // Try DGPTM Suite Logger first
            if (function_exists('dgptm_log')) {
                dgptm_log($message, array_merge(['module' => 'edugrant'], $context), $level);
            }

            // Always also log to error_log for immediate debugging
            error_log($log_entry);
        }

        /**
         * Execute COQL query against Zoho CRM
         */
        private function execute_coql_query($query) {
            // Log the query being executed
            $this->log('COQL Query executing', [
                'query' => $query,
                'endpoint' => self::ZOHO_COQL_ENDPOINT
            ], 'info');

            $access_token = $this->get_access_token();

            if (is_wp_error($access_token)) {
                $this->log('COQL Query failed - no access token', [
                    'error' => $access_token->get_error_message()
                ], 'error');
                return $access_token;
            }

            $request_body = json_encode(['select_query' => $query]);

            $response = wp_remote_post(self::ZOHO_COQL_ENDPOINT, [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'body' => $request_body,
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                $this->log('COQL Query WP Error', [
                    'query' => $query,
                    'error' => $response->get_error_message()
                ], 'error');
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            $body = json_decode($response_body, true);

            // Log the full response for debugging
            $this->log('COQL Query Response', [
                'query' => $query,
                'status_code' => $status_code,
                'response' => $body
            ], $status_code === 200 ? 'info' : 'error');

            if ($status_code !== 200) {
                $error_message = $body['message'] ?? ($body['error']['message'] ?? 'Unbekannter Fehler');
                $error_details = $body['details'] ?? ($body['error']['details'] ?? []);

                $this->log('COQL Query API Error', [
                    'query' => $query,
                    'status_code' => $status_code,
                    'error_message' => $error_message,
                    'error_details' => $error_details,
                    'full_response' => $body
                ], 'error');

                return new WP_Error('api_error', 'API-Fehler: ' . $error_message);
            }

            $result_count = isset($body['data']) ? count($body['data']) : 0;
            $this->log('COQL Query Success', [
                'query' => substr($query, 0, 100) . '...',
                'result_count' => $result_count
            ], 'info');

            return $body['data'] ?? [];
        }

        /**
         * Get events with EduGrant budget available
         * Uses Zoho Records API instead of COQL for better field compatibility
         */
        public function get_available_events() {
            $today = date('Y-m-d');

            $this->log('Fetching events via Records API', ['module' => self::ZOHO_MODULE_EVENTS], 'info');

            $access_token = $this->get_access_token();
            if (is_wp_error($access_token)) {
                $this->log('Get Events failed - no token', ['error' => $access_token->get_error_message()], 'error');
                return $access_token;
            }

            // Use Records API to get all events - must specify fields parameter
            // API Field Names from Modules.json
            $fields = [
                'Name',              // Veranstaltungsbezeichnung
                'From_Date',         // Von
                'To_Date',           // Bis
                'Budget',            // Budget
                'Maximum_Attendees', // Max Anzahl TN
                'EduGrant_applications', // Genehmigte EduGrant
                'EduBeantragt',      // Anzahl beantragter EduGrants
                'External_Event',    // Externe Veranstaltung
                'Maximum_Promotion', // Maximale Förderung
                'Event_Number',      // Veranstaltungsnummer
                'Location',          // Ort (lookup)
                'City'               // Stadt
            ];

            // Filter for future events only (To_Date >= today)
            $criteria = '(To_Date:greater_equal:' . $today . ')';

            $url = 'https://www.zohoapis.eu/crm/v8/' . self::ZOHO_MODULE_EVENTS
                 . '?fields=' . implode(',', $fields)
                 . '&criteria=' . urlencode($criteria);

            $this->log('Get Events API Request', ['url' => $url], 'info');

            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                    'Accept' => 'application/json'
                ],
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                $this->log('Get Events WP Error', ['error' => $response->get_error_message()], 'error');
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            $this->log('Get Events API Response', [
                'status_code' => $status_code,
                'record_count' => isset($body['data']) ? count($body['data']) : 0,
                'first_record_fields' => isset($body['data'][0]) ? array_keys($body['data'][0]) : [],
                'info' => $body['info'] ?? null,
                'full_response' => $body // Log full response for debugging
            ], $status_code === 200 ? 'info' : 'error');

            if ($status_code !== 200) {
                $error_msg = $body['message'] ?? $body['code'] ?? 'Fehler beim Abrufen der Events';
                return new WP_Error('api_error', $error_msg);
            }

            $events = $body['data'] ?? [];

            // Filter events where applications are still possible
            // API Field Names from Modules.json:
            // - Name = Veranstaltungsbezeichnung
            // - From_Date = Von
            // - To_Date = Bis
            // - Budget = Budget
            // - Maximum_Attendees = Max Anzahl TN
            // - EduGrant_applications = Genehmigte EduGrant (approved)
            // - EduBeantragt = Anzahl beantragt (applied, not yet approved)
            // - External_Event = Externe Veranstaltung
            // - Maximum_Promotion = Maximale Förderung
            // - Event_Number = Veranstaltungsnummer
            $filtered_events = [];
            foreach ($events as $event) {
                // Get end date using correct API field name
                $end_date = $event['To_Date'] ?? '';
                $budget = $event['Budget'] ?? null;

                // Skip events without budget or past events
                if (empty($budget) || (!empty($end_date) && strtotime($end_date) < strtotime($today))) {
                    continue;
                }

                // Application deadline: 3 days before event start
                $event_start = $event['From_Date'] ?? '';
                if (!empty($event_start)) {
                    $deadline = strtotime($event_start . ' -3 days');
                    $event['application_deadline'] = date('Y-m-d', $deadline);
                    $event['can_apply'] = (time() < $deadline);
                } else {
                    $event['can_apply'] = false;
                }

                // Tiered availability check:
                // - Maximum_Attendees = hard limit set by board
                // - EduGrant_applications = approved grants (hard block when >= max)
                // - EduBeantragt = submitted applications (soft warning when >= max but approved < max)
                $max_attendees = (int) ($event['Maximum_Attendees'] ?? 0);
                $approved_grants = (int) ($event['EduGrant_applications'] ?? 0);
                $applied_grants = (int) ($event['EduBeantragt'] ?? 0);

                // Hard block: approved >= max
                $event['max_reached'] = ($max_attendees > 0 && $approved_grants >= $max_attendees);

                // Soft warning: applied >= max but approved < max
                $event['over_quota_warning'] = ($max_attendees > 0 && $applied_grants >= $max_attendees && $approved_grants < $max_attendees);

                // For display purposes
                $event['spots_available'] = $max_attendees > 0 ? max(0, $max_attendees - $approved_grants) : 999;
                $event['has_spots'] = !$event['max_reached'];

                $filtered_events[] = $event;
            }

            $this->log('Events filtered', [
                'total_events' => count($events),
                'filtered_events' => count($filtered_events)
            ], 'info');

            return $filtered_events;
        }

        /**
         * Get EduGrants for a specific user (by Zoho Contact ID)
         */
        public function get_user_edugrantes($user_id = null) {
            if (!$user_id) {
                $user_id = get_current_user_id();
            }

            if (!$user_id) {
                return new WP_Error('not_logged_in', 'Benutzer nicht angemeldet.');
            }

            // Get Zoho Contact ID from user meta
            $zoho_contact_id = get_user_meta($user_id, 'zoho_id', true);

            if (empty($zoho_contact_id)) {
                return new WP_Error('no_zoho_id', 'Keine Zoho-Kontakt-ID gefunden.');
            }

            // COQL Query: Get EduGrants for this contact
            $query = "SELECT id, Name, Kontakt, Veranstaltung, Status, Nummer,
                      Beantragt_am, Genehmigt_am, Maximale_Forderung, Summe,
                      Unterkunft, Fahrtkosten, Teilnahmegebuehren, Ordner_mit_Nachweisen,
                      Text_Ablehnung
                      FROM " . self::ZOHO_MODULE_EDUGRANT . "
                      WHERE Kontakt = '{$zoho_contact_id}'
                      ORDER BY Beantragt_am DESC
                      LIMIT 50";

            return $this->execute_coql_query($query);
        }

        /**
         * Shortcode: Display available events
         */
        public function shortcode_events($atts) {
            $atts = shortcode_atts([
                'show_past' => 'false',
                'limit' => 20
            ], $atts);

            $events = $this->get_available_events();

            if (is_wp_error($events)) {
                return '<div class="edugrant-error">' . esc_html($events->get_error_message()) . '</div>';
            }

            if (empty($events)) {
                return '<div class="edugrant-notice">Aktuell sind keine Veranstaltungen mit EduGrant-Budget verfügbar.</div>';
            }

            ob_start();
            include $this->plugin_path . 'templates/events-list.php';
            return ob_get_clean();
        }

        /**
         * Shortcode: Display user's EduGrants
         */
        public function shortcode_user_edugrantes($atts) {
            if (!is_user_logged_in()) {
                return '<div class="edugrant-notice">Bitte melden Sie sich an, um Ihre EduGrants zu sehen.</div>';
            }

            $atts = shortcode_atts([
                'show_form_link' => 'true'
            ], $atts);

            $grants = $this->get_user_edugrantes();

            if (is_wp_error($grants)) {
                if ($grants->get_error_code() === 'no_zoho_id') {
                    return '<div class="edugrant-notice">Ihr Konto ist nicht mit Zoho CRM verknüpft. Bitte kontaktieren Sie den Support.</div>';
                }
                return '<div class="edugrant-error">' . esc_html($grants->get_error_message()) . '</div>';
            }

            ob_start();
            include $this->plugin_path . 'templates/user-grants.php';
            return ob_get_clean();
        }

        /**
         * Shortcode: Display application form
         * Now supports both logged-in members and guests
         */
        public function shortcode_application_form($atts) {
            $atts = shortcode_atts([
                'event_id' => '',
                'edugrant_code' => ''
            ], $atts);

            // For logged-in users, check if they have Zoho ID (but don't block)
            if (is_user_logged_in()) {
                $zoho_id = get_user_meta(get_current_user_id(), 'zoho_id', true);
                if (empty($zoho_id)) {
                    // Warning but don't block - they can still submit
                    // The form will be shown but submission might require manual processing
                }
            }

            // Get event_id from URL parameter if not in shortcode
            if (empty($atts['event_id'])) {
                $atts['event_id'] = isset($_GET['event_id']) ? sanitize_text_field($_GET['event_id']) : '';
            }

            // Get edugrant_code from URL parameter
            if (empty($atts['edugrant_code'])) {
                $atts['edugrant_code'] = isset($_GET['edugrant_code']) ? sanitize_text_field($_GET['edugrant_code']) : '';
            }

            ob_start();
            include $this->plugin_path . 'templates/application-form.php';
            return ob_get_clean();
        }

        /**
         * AJAX: Get events list
         */
        public function ajax_get_events() {
            check_ajax_referer('dgptm_edugrant_nonce', 'nonce');

            $events = $this->get_available_events();

            if (is_wp_error($events)) {
                wp_send_json_error(['message' => $events->get_error_message()]);
            }

            wp_send_json_success(['events' => $events]);
        }

        /**
         * AJAX: Get user grants
         */
        public function ajax_get_user_grants() {
            check_ajax_referer('dgptm_edugrant_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Nicht angemeldet.']);
            }

            $grants = $this->get_user_edugrantes();

            if (is_wp_error($grants)) {
                wp_send_json_error(['message' => $grants->get_error_message()]);
            }

            wp_send_json_success(['grants' => $grants]);
        }

        /**
         * AJAX: Submit application
         * Note: This creates a record in Zoho CRM EduGrant module
         */
        public function ajax_submit_application() {
            check_ajax_referer('dgptm_edugrant_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Nicht angemeldet.']);
            }

            $user_id = get_current_user_id();
            $zoho_contact_id = get_user_meta($user_id, 'zoho_id', true);

            if (empty($zoho_contact_id)) {
                wp_send_json_error(['message' => 'Keine Zoho-Kontakt-ID gefunden.']);
            }

            $event_id = sanitize_text_field($_POST['event_id'] ?? '');

            if (empty($event_id)) {
                wp_send_json_error(['message' => 'Keine Veranstaltung ausgewählt.']);
            }

            // Check if event is internal or external
            $event = $this->get_event_by_id($event_id);
            if (is_wp_error($event)) {
                wp_send_json_error(['message' => $event->get_error_message()]);
            }

            // Server-side eligibility check (deadline + quota)
            $eligibility = $this->check_event_eligibility($event);
            if (!$eligibility['eligible']) {
                wp_send_json_error(['message' => $eligibility['message']]);
            }

            $is_external = $event['External_Event'] ?? false;
            $is_external = ($is_external === true || $is_external === 'true');

            // For INTERNAL events: Verify ticket exists
            if (!$is_external) {
                $ticket_check = $this->check_user_has_ticket($zoho_contact_id, $event_id);

                if (is_wp_error($ticket_check)) {
                    wp_send_json_error(['message' => $ticket_check->get_error_message()]);
                }

                if (!$ticket_check['has_ticket']) {
                    wp_send_json_error([
                        'message' => 'Für diese Veranstaltung ist vor Beantragung ein gültiges Ticket erforderlich.',
                        'requires_ticket' => true
                    ]);
                }
            }

            // Create EduGrant record in Zoho CRM (pass is_external flag)
            $result = $this->create_edugrant_record($zoho_contact_id, $event_id, $is_external);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success([
                'message' => 'EduGrant-Antrag erfolgreich eingereicht!',
                'edugrant_id' => $result['id'] ?? '',
                'edugrant_number' => $result['Nummer'] ?? ''
            ]);
        }

        /**
         * AJAX: Check guest email - find contact and check for ticket
         */
        public function ajax_check_guest_email() {
            check_ajax_referer('dgptm_edugrant_nonce', 'nonce');

            $email = sanitize_email($_POST['email'] ?? '');
            $event_id = sanitize_text_field($_POST['event_id'] ?? '');

            if (empty($email) || !is_email($email)) {
                wp_send_json_error(['message' => 'Ungültige E-Mail-Adresse.']);
            }

            if (empty($event_id)) {
                wp_send_json_error(['message' => 'Keine Veranstaltung ausgewählt.']);
            }

            $this->log('Guest email check', ['email' => $email, 'event_id' => $event_id], 'info');

            // Get event details first to check if external
            $event = $this->get_event_by_id($event_id);
            if (is_wp_error($event)) {
                wp_send_json_error(['message' => $event->get_error_message()]);
            }

            // Check event eligibility (deadline + quota) BEFORE allowing any application
            $eligibility = $this->check_event_eligibility($event);
            if (!$eligibility['eligible']) {
                wp_send_json_error([
                    'message' => $eligibility['message'],
                    'reason' => $eligibility['reason'],
                    'can_apply' => false
                ]);
            }

            $is_external = $event['External_Event'] ?? false;
            $is_external = ($is_external === true || $is_external === 'true');

            // Search for contact by email
            $contact = $this->find_contact_by_email($email);

            $result = [
                'contact_found' => false,
                'contact_id' => '',
                'has_ticket' => false,
                'ticket_status' => null,
                'is_external' => $is_external,
                'event_name' => $event['Name'] ?? 'Unbekannt',
                'needs_contact_data' => true,
                'needs_eligibility_proof' => true,
                'can_apply' => false,
                'message' => ''
            ];

            if (!is_wp_error($contact) && !empty($contact)) {
                $result['contact_found'] = true;
                $result['contact_id'] = $contact['id'] ?? '';
                $result['message'] = 'Kontakt gefunden.';

                // Check if contact is a member (Mitglied = true)
                $is_member = $contact['Mitglied'] ?? false;
                $is_member = ($is_member === true || $is_member === 'true');
                $result['is_member'] = $is_member;

                $this->log('Contact found by email', [
                    'contact_id' => $result['contact_id'],
                    'is_member' => $is_member
                ], 'info');

                // Check for ticket if internal event
                if (!$is_external) {
                    $ticket_check = $this->check_user_has_ticket($result['contact_id'], $event_id);

                    if (!is_wp_error($ticket_check) && $ticket_check['has_ticket']) {
                        $result['has_ticket'] = true;
                        $result['ticket_status'] = $ticket_check['status'] ?? 'Gültig';
                        $result['needs_contact_data'] = false;
                        $result['needs_eligibility_proof'] = false; // Has ticket = no proof needed
                        $result['can_apply'] = true;
                        $result['message'] = 'Kontakt und gültiges Ticket gefunden.';
                    } else {
                        $result['can_apply'] = false;
                        $result['message'] = 'Kontakt gefunden, aber kein gültiges Ticket für diese Veranstaltung. Bitte buchen Sie zunächst ein Ticket.';
                    }
                } else {
                    // External event: contact found
                    $result['needs_contact_data'] = false; // Contact exists, no need to fill data

                    // Members don't need eligibility proof
                    if ($is_member) {
                        $result['needs_eligibility_proof'] = false;
                        $result['can_apply'] = true;
                        $result['message'] = 'Kontakt gefunden.';
                    } else {
                        $result['needs_eligibility_proof'] = true; // Non-members need proof
                        $result['can_apply'] = true;
                        $result['message'] = 'Kontakt gefunden.';
                    }
                }
            } else {
                // No contact found
                $this->log('No contact found for email', ['email' => $email], 'info');

                if (!$is_external) {
                    // Internal event: Must have ticket, which requires contact
                    $result['can_apply'] = false;
                    $result['message'] = 'Kein Kontakt mit dieser E-Mail-Adresse gefunden. Für interne Veranstaltungen muss zunächst ein Ticket gebucht werden.';
                } else {
                    // External event: Can create new contact
                    $result['needs_contact_data'] = true;
                    $result['needs_eligibility_proof'] = true;
                    $result['can_apply'] = true;
                    $result['message'] = 'Kein Kontakt gefunden. Bitte Kontaktdaten und Berechtigung angeben.';
                }
            }

            wp_send_json_success($result);
        }

        /**
         * Find contact by email in any of the 4 email fields
         * Uses Zoho Search API with correct field names: Email, Secondary_Email, Third_Email, DGPTMMail
         */
        private function find_contact_by_email($email) {
            $email_clean = trim($email);

            $this->log('Searching contact by email', ['email' => $email_clean], 'info');

            $access_token = $this->get_access_token();

            if (is_wp_error($access_token)) {
                return $access_token;
            }

            // Build search criteria for all 4 email fields
            $criteria = '((Email:equals:' . $email_clean . ')or(Third_Email:equals:' . $email_clean . ')or(Secondary_Email:equals:' . $email_clean . ')or(DGPTMMail:equals:' . $email_clean . '))';

            $url = 'https://www.zohoapis.eu/crm/v8/' . self::ZOHO_MODULE_CONTACTS . '/search?criteria=' . urlencode($criteria);

            $this->log('Contact search API request', ['url' => $url], 'info');

            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                    'Accept' => 'application/json'
                ],
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                $this->log('Contact search WP Error', ['error' => $response->get_error_message()], 'error');
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            $this->log('Contact search response', [
                'status_code' => $status_code,
                'count' => isset($body['data']) ? count($body['data']) : 0
            ], $status_code === 200 ? 'info' : 'error');

            // 200 = found, 204 = no content (not found)
            if ($status_code === 204 || empty($body['data'])) {
                return null;
            }

            if ($status_code !== 200) {
                $error_msg = $body['message'] ?? 'Fehler bei der Kontaktsuche';
                return new WP_Error('api_error', $error_msg);
            }

            // Return the first matching contact
            return $body['data'][0] ?? null;
        }

        /**
         * AJAX: Submit guest application (non-logged-in users)
         */
        public function ajax_submit_guest_application() {
            check_ajax_referer('dgptm_edugrant_nonce', 'nonce');

            $event_id = sanitize_text_field($_POST['event_id'] ?? '');
            $email = sanitize_email($_POST['guest_email'] ?? '');
            $contact_id = sanitize_text_field($_POST['guest_contact_id'] ?? '');
            $contact_found = sanitize_text_field($_POST['guest_contact_found'] ?? '0') === '1';

            if (empty($event_id)) {
                wp_send_json_error(['message' => 'Keine Veranstaltung ausgewählt.']);
            }

            if (empty($email) || !is_email($email)) {
                wp_send_json_error(['message' => 'Ungültige E-Mail-Adresse.']);
            }

            $this->log('Guest application submit', [
                'event_id' => $event_id,
                'email' => $email,
                'contact_id' => $contact_id,
                'contact_found' => $contact_found
            ], 'info');

            // Get event details
            $event = $this->get_event_by_id($event_id);
            if (is_wp_error($event)) {
                wp_send_json_error(['message' => $event->get_error_message()]);
            }

            // Server-side eligibility check (deadline + quota)
            $eligibility = $this->check_event_eligibility($event);
            if (!$eligibility['eligible']) {
                wp_send_json_error(['message' => $eligibility['message']]);
            }

            $is_external = $event['External_Event'] ?? false;
            $is_external = ($is_external === true || $is_external === 'true');

            // If no contact found, need to create one (only for external events)
            if (!$contact_found || empty($contact_id)) {
                if (!$is_external) {
                    wp_send_json_error(['message' => 'Für diese Veranstaltung ist vor Beantragung ein gültiges Ticket erforderlich.']);
                }

                // Create new contact
                $vorname = sanitize_text_field($_POST['guest_vorname'] ?? '');
                $nachname = sanitize_text_field($_POST['guest_nachname'] ?? '');
                $strasse = sanitize_text_field($_POST['guest_strasse'] ?? '');
                $plz = sanitize_text_field($_POST['guest_plz'] ?? '');
                $ort = sanitize_text_field($_POST['guest_ort'] ?? '');

                if (empty($vorname) || empty($nachname) || empty($strasse) || empty($plz) || empty($ort)) {
                    wp_send_json_error(['message' => 'Bitte alle Kontaktdaten ausfüllen.']);
                }

                $new_contact = $this->create_contact([
                    'First_Name' => $vorname,
                    'Last_Name' => $nachname,
                    'Email' => $email,
                    'Mailing_Street' => $strasse,
                    'Mailing_Zip' => $plz,
                    'Mailing_City' => $ort
                ]);

                if (is_wp_error($new_contact)) {
                    wp_send_json_error(['message' => 'Fehler beim Anlegen des Kontakts: ' . $new_contact->get_error_message()]);
                }

                $contact_id = $new_contact['id'] ?? '';
                $this->log('New contact created', ['contact_id' => $contact_id], 'info');
            }

            if (empty($contact_id)) {
                wp_send_json_error(['message' => 'Kontakt-ID fehlt.']);
            }

            // Note: Ticket verification already done in ajax_check_guest_email()
            // No need to check again here - the form is only shown if ticket was verified

            // Get member status from form (was determined during email check)
            $is_member = sanitize_text_field($_POST['guest_is_member'] ?? '0') === '1';

            // Determine if proof is required (non-members need proof)
            $nachweis_erforderlich = !$is_member;
            $nachweis_file = null;

            // Check for uploaded proof file
            if ($nachweis_erforderlich && !empty($_FILES['guest_nachweis']) && $_FILES['guest_nachweis']['error'] === UPLOAD_ERR_OK) {
                $nachweis_file = $_FILES['guest_nachweis'];
            }

            $this->log('Proof requirement check', [
                'contact_id' => $contact_id,
                'is_member' => $is_member,
                'nachweis_erforderlich' => $nachweis_erforderlich,
                'has_file' => !empty($nachweis_file)
            ], 'info');

            // Create EduGrant record
            $result = $this->create_edugrant_record_guest($contact_id, $event_id, $email, $is_external, $nachweis_erforderlich, $nachweis_file);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            $response_data = [
                'message' => 'EduGrant-Antrag erfolgreich eingereicht!',
                'edugrant_id' => $result['id'] ?? '',
                'edugrant_number' => $result['Nummer'] ?? ''
            ];

            // Include existing record info if applicable (duplicate application)
            if (!empty($result['_is_existing'])) {
                $response_data['_is_existing'] = true;
                $response_data['_message'] = $result['_message'] ?? 'Ihr Antrag für diese Veranstaltung ist bereits bei uns eingegangen.';
            }

            wp_send_json_success($response_data);
        }

        /**
         * Create contact in Zoho CRM
         */
        private function create_contact($data) {
            $this->log('Creating contact', $data, 'info');

            $access_token = $this->get_access_token();

            if (is_wp_error($access_token)) {
                return $access_token;
            }

            $record_data = [
                'data' => [$data]
            ];

            $url = 'https://www.zohoapis.eu/crm/v8/' . self::ZOHO_MODULE_CONTACTS;

            $response = wp_remote_post($url, [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'body' => json_encode($record_data),
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                $this->log('Create Contact WP Error', ['error' => $response->get_error_message()], 'error');
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            $this->log('Create Contact Response', [
                'status_code' => $status_code,
                'response' => $body
            ], $status_code === 201 || $status_code === 200 ? 'info' : 'error');

            if ($status_code !== 201 && $status_code !== 200) {
                $error_msg = $body['message'] ?? ($body['data'][0]['message'] ?? 'Unbekannter Fehler');
                return new WP_Error('api_error', $error_msg);
            }

            return $body['data'][0]['details'] ?? [];
        }

        /**
         * Create EduGrant record for guest (non-logged-in user)
         */
        private function create_edugrant_record_guest($contact_id, $event_id, $email, $is_external = false, $nachweis_erforderlich = false, $nachweis_file = null) {
            $this->log('Creating EduGrant record (guest)', [
                'contact_id' => $contact_id,
                'event_id' => $event_id,
                'email' => $email,
                'is_external' => $is_external,
                'nachweis_erforderlich' => $nachweis_erforderlich,
                'has_nachweis_file' => !empty($nachweis_file)
            ], 'info');

            $access_token = $this->get_access_token();

            if (is_wp_error($access_token)) {
                return $access_token;
            }

            // Generate a unique name for the EduGrant record
            $edugrant_name = 'EduGrant-' . date('Y-m-d') . '-Guest-' . substr(md5($email), 0, 8);

            $record_data = [
                'data' => [
                    [
                        'Name' => $edugrant_name,
                        'Contact' => $contact_id,
                        'Veranstaltung' => $event_id,
                        'Status' => 'Beantragt',
                        'Beantragt_am' => date('Y-m-d'),
                        'Email' => $email,
                        'Externe_Veranstaltung' => $is_external,
                        'Nachweis_erforderlich' => $nachweis_erforderlich
                    ]
                ]
            ];

            $url = 'https://www.zohoapis.eu/crm/v8/' . self::ZOHO_MODULE_EDUGRANT;

            $this->log('Create EduGrant Guest API Request', [
                'url' => $url,
                'data' => $record_data
            ], 'info');

            $response = wp_remote_post($url, [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'body' => json_encode($record_data),
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                $this->log('Create EduGrant Guest WP Error', ['error' => $response->get_error_message()], 'error');
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            $this->log('Create EduGrant Guest Response', [
                'status_code' => $status_code,
                'response' => $body
            ], $status_code === 201 || $status_code === 200 ? 'info' : 'error');

            // Check for duplicate data error
            $is_duplicate = false;
            $error_code = $body['data'][0]['code'] ?? ($body['code'] ?? '');
            $error_msg = $body['message'] ?? ($body['data'][0]['message'] ?? 'Unbekannter Fehler');

            if ($status_code !== 201 && $status_code !== 200) {
                // Check if it's a duplicate error
                if (stripos($error_code, 'DUPLICATE') !== false || stripos($error_msg, 'duplicate') !== false) {
                    $is_duplicate = true;
                    $this->log('Duplicate EduGrant detected, searching for existing record', [
                        'contact_id' => $contact_id,
                        'event_id' => $event_id
                    ], 'info');
                } else {
                    return new WP_Error('api_error', 'Fehler beim Erstellen: ' . $error_msg);
                }
            }

            $created_id = null;
            $is_existing_record = false;

            if ($is_duplicate) {
                // Find existing EduGrant record
                $existing = $this->find_existing_edugrant($contact_id, $event_id);

                if ($existing && !is_wp_error($existing)) {
                    $created_id = $existing['id'];
                    $is_existing_record = true;
                    $this->log('Found existing EduGrant', [
                        'id' => $created_id,
                        'nummer' => $existing['Nummer'] ?? 'N/A'
                    ], 'info');
                } else {
                    return new WP_Error('api_error', 'Ein Antrag für diese Veranstaltung ist bereits bei uns eingegangen, konnte aber nicht gefunden werden.');
                }
            } else {
                // Get the created record ID
                $created_id = $body['data'][0]['details']['id'] ?? null;

                // Increment EduBeantragt counter on the event (only for new applications)
                if ($created_id) {
                    $this->increment_edubeantragt($event_id);
                }
            }

            if ($created_id) {
                // Upload proof file if provided (works for both new and existing records)
                if (!empty($nachweis_file)) {
                    $upload_result = $this->upload_file_to_record($created_id, 'Berechtigungsnachweis', $nachweis_file);

                    if (is_wp_error($upload_result)) {
                        $this->log('Failed to upload Berechtigungsnachweis', [
                            'edugrant_id' => $created_id,
                            'error' => $upload_result->get_error_message()
                        ], 'error');
                    } else {
                        $this->log('Berechtigungsnachweis uploaded successfully', [
                            'edugrant_id' => $created_id,
                            'is_existing_record' => $is_existing_record
                        ], 'info');
                    }
                }

                $full_record = $this->get_edugrant_by_id($created_id);
                if (!is_wp_error($full_record)) {
                    // Mark as existing record if it was a duplicate
                    if ($is_existing_record) {
                        $full_record['_is_existing'] = true;
                        $full_record['_message'] = 'Ihr Antrag für diese Veranstaltung ist bereits bei uns eingegangen.' .
                            (!empty($nachweis_file) ? ' Der Berechtigungsnachweis wurde aktualisiert.' : '');
                    }

                    $this->log('EduGrant Guest record processed', [
                        'id' => $created_id,
                        'nummer' => $full_record['Nummer'] ?? 'N/A',
                        'is_existing' => $is_existing_record
                    ], 'info');
                    return $full_record;
                }
            }

            return $body['data'][0]['details'] ?? [];
        }

        /**
         * Upload file to a record's file upload field
         *
         * Zoho CRM File Upload requires a two-step process:
         * 1. Upload file to ZFS (Zoho File System): POST /crm/v8/files
         * 2. Update the record with the file ID: PUT /crm/v8/{module}/{record_id}
         *
         * @see https://www.zoho.com/crm/developer/docs/api/v8/upload-files-to-zfs.html
         */
        private function upload_file_to_record($record_id, $field_name, $file) {
            $this->log('Uploading file to record (two-step ZFS process)', [
                'record_id' => $record_id,
                'field_name' => $field_name,
                'file_name' => $file['name'] ?? 'unknown',
                'file_tmp' => $file['tmp_name'] ?? 'no tmp_name',
                'file_size' => $file['size'] ?? 0
            ], 'info');

            // Validate file exists
            if (empty($file['tmp_name']) || !file_exists($file['tmp_name'])) {
                $this->log('File upload error - file not found', [
                    'tmp_name' => $file['tmp_name'] ?? 'empty'
                ], 'error');
                return new WP_Error('file_not_found', 'Uploaded file not found');
            }

            $access_token = $this->get_access_token();

            if (is_wp_error($access_token)) {
                return $access_token;
            }

            // Step 1: Upload file to ZFS (Zoho File System)
            $zfs_url = 'https://www.zohoapis.eu/crm/v8/files';

            $this->log('Step 1: Uploading to ZFS', ['url' => $zfs_url], 'info');

            $ch = curl_init();

            // Create CURLFile object for the upload
            $cfile = new CURLFile($file['tmp_name'], $file['type'] ?: 'application/octet-stream', $file['name']);

            $post_data = [
                'file' => $cfile
            ];

            curl_setopt_array($ch, [
                CURLOPT_URL => $zfs_url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $post_data,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => [
                    'Authorization: Zoho-oauthtoken ' . $access_token
                ],
                CURLOPT_TIMEOUT => 60
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);

            if ($curl_error) {
                $this->log('ZFS upload curl error', ['error' => $curl_error], 'error');
                return new WP_Error('curl_error', $curl_error);
            }

            $response_body = json_decode($response, true);

            $this->log('ZFS upload response', [
                'http_code' => $http_code,
                'response' => $response_body
            ], ($http_code === 200 || $http_code === 201) ? 'info' : 'error');

            if ($http_code !== 200 && $http_code !== 201) {
                $error_msg = $response_body['message'] ?? 'Fehler beim Hochladen in ZFS';
                return new WP_Error('zfs_upload_error', $error_msg);
            }

            // Extract the file ID from the ZFS response
            $file_id = $response_body['data'][0]['details']['id'] ?? null;

            if (empty($file_id)) {
                $this->log('ZFS upload - no file ID returned', ['response' => $response_body], 'error');
                return new WP_Error('zfs_no_file_id', 'Keine Datei-ID von ZFS erhalten');
            }

            $this->log('ZFS upload SUCCESS', [
                'file_id' => $file_id,
                'file_name' => $response_body['data'][0]['details']['name'] ?? 'unknown'
            ], 'info');

            // Step 2: Update the record with the file ID
            // IMPORTANT: file_id must be in a simple array, not as {'file_id': 'xxx'}
            $update_url = 'https://www.zohoapis.eu/crm/v8/' . self::ZOHO_MODULE_EDUGRANT . '/' . $record_id;

            $update_data = [
                'data' => [
                    [
                        $field_name => [$file_id]
                    ]
                ]
            ];

            $this->log('Step 2: Updating record with file ID', [
                'url' => $update_url,
                'field_name' => $field_name,
                'file_id' => $file_id
            ], 'info');

            $update_response = wp_remote_request($update_url, [
                'method' => 'PUT',
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'body' => json_encode($update_data),
                'timeout' => 30
            ]);

            if (is_wp_error($update_response)) {
                $this->log('Record update WP Error', ['error' => $update_response->get_error_message()], 'error');
                return $update_response;
            }

            $update_status = wp_remote_retrieve_response_code($update_response);
            $update_body = json_decode(wp_remote_retrieve_body($update_response), true);

            $this->log('Record update response', [
                'http_code' => $update_status,
                'response' => $update_body
            ], ($update_status === 200) ? 'info' : 'error');

            if ($update_status !== 200) {
                $error_msg = $update_body['message'] ?? ($update_body['data'][0]['message'] ?? 'Fehler beim Aktualisieren des Records');
                return new WP_Error('record_update_error', $error_msg);
            }

            $this->log('File upload to record SUCCESS', [
                'record_id' => $record_id,
                'field_name' => $field_name,
                'file_id' => $file_id
            ], 'info');

            return $update_body;
        }

        /**
         * Create EduGrant record in Zoho CRM
         */
        private function create_edugrant_record($contact_id, $event_id, $is_external = false) {
            $this->log('Creating EduGrant record', [
                'contact_id' => $contact_id,
                'event_id' => $event_id,
                'is_external' => $is_external
            ], 'info');

            $access_token = $this->get_access_token();

            if (is_wp_error($access_token)) {
                $this->log('Create EduGrant failed - no token', ['error' => $access_token->get_error_message()], 'error');
                return $access_token;
            }

            $user = wp_get_current_user();

            // Generate a unique name for the EduGrant record
            $edugrant_name = 'EduGrant-' . date('Y-m-d') . '-' . $user->ID;

            // Prepare record data - Name is a mandatory field
            $record_data = [
                'data' => [
                    [
                        'Name' => $edugrant_name,
                        'Contact' => $contact_id,
                        'Veranstaltung' => $event_id,
                        'Status' => 'Beantragt',
                        'Beantragt_am' => date('Y-m-d'),
                        'Email' => $user->user_email,
                        'Externe_Veranstaltung' => $is_external
                    ]
                ]
            ];

            $url = 'https://www.zohoapis.eu/crm/v8/' . self::ZOHO_MODULE_EDUGRANT;

            $this->log('Create EduGrant API Request', [
                'url' => $url,
                'data' => $record_data
            ], 'info');

            $response = wp_remote_post($url, [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'body' => json_encode($record_data),
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                $this->log('Create EduGrant WP Error', ['error' => $response->get_error_message()], 'error');
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            $this->log('Create EduGrant API Response', [
                'status_code' => $status_code,
                'response' => $body
            ], $status_code === 201 || $status_code === 200 ? 'info' : 'error');

            if ($status_code !== 201 && $status_code !== 200) {
                $error_msg = $body['message'] ?? ($body['data'][0]['message'] ?? 'Unbekannter Fehler');
                return new WP_Error('api_error', 'Fehler beim Erstellen: ' . $error_msg);
            }

            // Get the created record ID
            $created_id = $body['data'][0]['details']['id'] ?? null;

            if ($created_id) {
                // Increment EduBeantragt counter on the event
                $this->increment_edubeantragt($event_id);

                // Fetch the full record to get auto-generated fields like Nummer
                $full_record = $this->get_edugrant_by_id($created_id);
                if (!is_wp_error($full_record)) {
                    $this->log('Created EduGrant full record', [
                        'id' => $created_id,
                        'nummer' => $full_record['Nummer'] ?? 'N/A'
                    ], 'info');
                    return $full_record;
                }
            }

            // Fallback: return details from creation response
            if (isset($body['data'][0]['details'])) {
                return $body['data'][0]['details'];
            }

            return $body['data'][0] ?? [];
        }

        /**
         * AJAX: Get specific EduGrant details by ID (eduid parameter)
         */
        public function ajax_get_grant_details() {
            check_ajax_referer('dgptm_edugrant_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Nicht angemeldet.']);
            }

            $eduid = sanitize_text_field($_POST['eduid'] ?? '');

            if (empty($eduid)) {
                wp_send_json_error(['message' => 'Keine EduGrant-ID angegeben.']);
            }

            // Fetch EduGrant record from Zoho
            $grant = $this->get_edugrant_by_id($eduid);

            if (is_wp_error($grant)) {
                wp_send_json_error(['message' => $grant->get_error_message()]);
            }

            // Verify that this grant belongs to the current user
            $user_zoho_id = get_user_meta(get_current_user_id(), 'zoho_id', true);
            $grant_contact_id = $grant['Kontakt']['id'] ?? $grant['Kontakt'] ?? '';

            if ($grant_contact_id !== $user_zoho_id) {
                wp_send_json_error(['message' => 'Zugriff verweigert.']);
            }

            wp_send_json_success(['grant' => $grant]);
        }

        /**
         * Get EduGrant by Zoho Record ID
         */
        private function get_edugrant_by_id($edugrant_id) {
            $access_token = $this->get_access_token();

            if (is_wp_error($access_token)) {
                return $access_token;
            }

            $url = 'https://www.zohoapis.eu/crm/v8/' . self::ZOHO_MODULE_EDUGRANT . '/' . $edugrant_id;

            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                    'Accept' => 'application/json'
                ],
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                error_log('EduGrant Get Error: ' . $response->get_error_message());
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($status_code !== 200) {
                error_log('EduGrant Get HTTP Error: ' . $status_code . ' - ' . print_r($body, true));
                return new WP_Error('api_error', 'EduGrant nicht gefunden.');
            }

            return $body['data'][0] ?? [];
        }

        /**
         * Find existing EduGrant for a contact and event
         */
        private function find_existing_edugrant($contact_id, $event_id) {
            $access_token = $this->get_access_token();

            if (is_wp_error($access_token)) {
                return $access_token;
            }

            // Search for EduGrant with this contact and event
            $criteria = '((Contact:equals:' . $contact_id . ')and(Veranstaltung:equals:' . $event_id . '))';
            $url = 'https://www.zohoapis.eu/crm/v8/' . self::ZOHO_MODULE_EDUGRANT . '/search?criteria=' . urlencode($criteria);

            $this->log('Searching for existing EduGrant', [
                'contact_id' => $contact_id,
                'event_id' => $event_id,
                'url' => $url
            ], 'info');

            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                    'Accept' => 'application/json'
                ],
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            $this->log('Existing EduGrant search result', [
                'status_code' => $status_code,
                'found' => !empty($body['data'])
            ], 'info');

            if ($status_code === 200 && !empty($body['data'])) {
                return $body['data'][0]; // Return first match
            }

            return null; // Not found
        }

        /**
         * AJAX: Get specific event details with eligibility check
         */
        public function ajax_get_event_details() {
            check_ajax_referer('dgptm_edugrant_nonce', 'nonce');

            $event_id = sanitize_text_field($_POST['event_id'] ?? '');

            if (empty($event_id)) {
                wp_send_json_error(['message' => 'Keine Event-ID angegeben.']);
            }

            $event = $this->get_event_by_id($event_id);

            if (is_wp_error($event)) {
                wp_send_json_error(['message' => $event->get_error_message()]);
            }

            // Check event eligibility for EduGrant application
            $eligibility = $this->check_event_eligibility($event);

            $this->log('Event eligibility check result in ajax_get_event_details', [
                'event_id' => $event_id,
                'eligible' => $eligibility['eligible'],
                'reason' => $eligibility['reason'] ?? null,
                'message' => $eligibility['message'] ?? null
            ], 'info');

            if (!$eligibility['eligible']) {
                wp_send_json_error([
                    'message' => $eligibility['message'],
                    'reason' => $eligibility['reason']
                ]);
            }

            // Add eligibility info to event data
            $event['edugrant_eligible'] = true;
            $event['application_deadline'] = $eligibility['deadline'];
            $event['spots_remaining'] = $eligibility['spots_remaining'];
            $event['over_quota_warning'] = $eligibility['over_quota_warning'] ?? false;

            wp_send_json_success(['event' => $event]);
        }

        /**
         * Check if event is eligible for EduGrant applications
         * - Must be at least 3 days before event start
         * - Tiered quota check:
         *   - Hard block: EduGrant_applications >= Maximum_Attendees
         *   - Soft warning: EduBeantragt >= Maximum_Attendees but approved < max
         */
        private function check_event_eligibility($event) {
            $event_name = $event['Name'] ?? 'Unbekannte Veranstaltung';
            $from_date = $event['From_Date'] ?? null;
            $max_attendees = intval($event['Maximum_Attendees'] ?? 0);
            $approved_grants = intval($event['EduGrant_applications'] ?? 0);
            $applied_grants = intval($event['EduBeantragt'] ?? 0);
            $event_id = $event['id'] ?? '';

            $this->log('Checking event eligibility', [
                'event_id' => $event_id,
                'event_name' => $event_name,
                'from_date' => $from_date,
                'max_attendees' => $max_attendees,
                'approved_grants' => $approved_grants,
                'applied_grants' => $applied_grants
            ], 'info');

            // Check 1: Deadline - must be at least 3 days before event start
            if (!empty($from_date)) {
                $event_start = strtotime($from_date);
                $deadline = strtotime('-3 days', $event_start);
                $now = time();

                $this->log('Deadline check', [
                    'from_date' => $from_date,
                    'event_start_timestamp' => $event_start,
                    'event_start_formatted' => date('Y-m-d H:i:s', $event_start),
                    'deadline_timestamp' => $deadline,
                    'deadline_formatted' => date('Y-m-d H:i:s', $deadline),
                    'now_timestamp' => $now,
                    'now_formatted' => date('Y-m-d H:i:s', $now),
                    'is_past_deadline' => ($now > $deadline)
                ], 'info');

                if ($now > $deadline) {
                    $deadline_date = date('d.m.Y', $deadline);
                    return [
                        'eligible' => false,
                        'reason' => 'deadline_passed',
                        'message' => "Die Antragsfrist für diese Veranstaltung ist abgelaufen (Frist: {$deadline_date}).",
                        'deadline' => $deadline_date,
                        'spots_remaining' => 0,
                        'over_quota_warning' => false
                    ];
                }
            }

            // Check 2: Tiered quota check
            if ($max_attendees > 0) {
                // Hard block: approved grants >= max
                if ($approved_grants >= $max_attendees) {
                    return [
                        'eligible' => false,
                        'reason' => 'quota_exhausted',
                        'message' => "Das EduGrant-Kontingent für diese Veranstaltung ist ausgeschöpft.",
                        'deadline' => !empty($from_date) ? date('d.m.Y', strtotime('-3 days', strtotime($from_date))) : null,
                        'spots_remaining' => 0,
                        'over_quota_warning' => false
                    ];
                }

                // Soft warning: applied >= max but approved < max
                $over_quota_warning = ($applied_grants >= $max_attendees && $approved_grants < $max_attendees);

                $spots_remaining = $max_attendees - $approved_grants;
            } else {
                // No quota limit set
                $spots_remaining = 999;
                $over_quota_warning = false;
            }

            return [
                'eligible' => true,
                'reason' => null,
                'message' => null,
                'deadline' => !empty($from_date) ? date('d.m.Y', strtotime('-3 days', strtotime($from_date))) : null,
                'spots_remaining' => $spots_remaining,
                'over_quota_warning' => $over_quota_warning
            ];
        }

        /**
         * Count existing EduGrant applications for an event
         */
        private function count_edugrant_applications($event_id) {
            $access_token = $this->get_access_token();

            if (is_wp_error($access_token)) {
                return 0;
            }

            // Search for EduGrant records linked to this event
            $criteria = '(Veranstaltung:equals:' . $event_id . ')';
            $url = 'https://www.zohoapis.eu/crm/v8/' . self::ZOHO_MODULE_EDUGRANT . '/search?criteria=' . urlencode($criteria);

            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                    'Accept' => 'application/json'
                ],
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                $this->log('Count EduGrant WP Error', ['error' => $response->get_error_message()], 'error');
                return 0;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            // 200 = found results, 204 = no content
            if ($status_code === 200 && isset($body['info']['count'])) {
                $count = intval($body['info']['count']);
                $this->log('EduGrant applications count', [
                    'event_id' => $event_id,
                    'count' => $count
                ], 'info');
                return $count;
            }

            return 0;
        }

        /**
         * Get Event by Zoho Record ID
         * Note: Single record endpoint returns all fields by default (no fields parameter needed)
         */
        private function get_event_by_id($event_id) {
            $this->log('Fetching Event by ID', ['event_id' => $event_id], 'info');

            $access_token = $this->get_access_token();

            if (is_wp_error($access_token)) {
                $this->log('Get Event failed - no token', ['error' => $access_token->get_error_message()], 'error');
                return $access_token;
            }

            // Single record endpoint - returns all fields automatically
            $url = 'https://www.zohoapis.eu/crm/v8/' . self::ZOHO_MODULE_EVENTS . '/' . $event_id;

            $this->log('Get Event API Request', ['url' => $url], 'info');

            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                    'Accept' => 'application/json'
                ],
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                $this->log('Get Event WP Error', ['error' => $response->get_error_message()], 'error');
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            $event_data = $body['data'][0] ?? [];

            $this->log('Get Event API Response', [
                'status_code' => $status_code,
                'event_name' => $event_data['Name'] ?? 'N/A',
                'from_date' => $event_data['From_Date'] ?? 'N/A',
                'city' => $event_data['City'] ?? 'N/A',
                'external_event' => $event_data['External_Event'] ?? 'N/A',
                'max_promotion' => $event_data['Maximum_Promotion'] ?? 'N/A',
                'all_fields' => array_keys($event_data)
            ], $status_code === 200 ? 'info' : 'error');

            if ($status_code !== 200) {
                return new WP_Error('api_error', 'Veranstaltung nicht gefunden.');
            }

            return $event_data;
        }

        /**
         * AJAX: Check ticket eligibility for internal events
         */
        public function ajax_check_ticket_eligibility() {
            check_ajax_referer('dgptm_edugrant_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Nicht angemeldet.']);
            }

            $event_id = sanitize_text_field($_POST['event_id'] ?? '');

            if (empty($event_id)) {
                wp_send_json_error(['message' => 'Keine Event-ID angegeben.']);
            }

            $user_id = get_current_user_id();
            $zoho_contact_id = get_user_meta($user_id, 'zoho_id', true);

            if (empty($zoho_contact_id)) {
                wp_send_json_error(['message' => 'Keine Zoho-Kontakt-ID gefunden.']);
            }

            // Get event to check if external
            $event = $this->get_event_by_id($event_id);
            if (is_wp_error($event)) {
                wp_send_json_error(['message' => $event->get_error_message()]);
            }

            // Check event eligibility (deadline + quota)
            $eligibility = $this->check_event_eligibility($event);
            if (!$eligibility['eligible']) {
                wp_send_json_success([
                    'eligible' => false,
                    'message' => $eligibility['message'],
                    'reason' => $eligibility['reason']
                ]);
            }

            $is_external = $event['External_Event'] ?? false;

            // External events: no ticket check needed
            if ($is_external === true || $is_external === 'true') {
                wp_send_json_success([
                    'eligible' => true,
                    'is_external' => true,
                    'message' => 'Externe Veranstaltung - keine Ticket-Prüfung erforderlich.'
                ]);
            }

            // Internal event: check for valid ticket
            $ticket_check = $this->check_user_has_ticket($zoho_contact_id, $event_id);

            if (is_wp_error($ticket_check)) {
                wp_send_json_error(['message' => $ticket_check->get_error_message()]);
            }

            wp_send_json_success([
                'eligible' => $ticket_check['has_ticket'],
                'is_external' => false,
                'ticket_status' => $ticket_check['status'] ?? null,
                'message' => $ticket_check['has_ticket']
                    ? 'Ticket gefunden: ' . ($ticket_check['status'] ?? 'Gültig')
                    : 'Kein gültiges Ticket für diese Veranstaltung gefunden. Bitte erwerben Sie zunächst ein Ticket.'
            ]);
        }

        /**
         * Check if user has a valid ticket for the event
         * Checks all 4 email fields from Contact against Ticket emails
         */
        private function check_user_has_ticket($contact_id, $event_id) {
            $this->log('Checking ticket for contact', [
                'contact_id' => $contact_id,
                'event_id' => $event_id
            ], 'info');

            // Step 1: Get contact with all email fields
            $contact = $this->get_contact_by_id($contact_id);
            if (is_wp_error($contact)) {
                return $contact;
            }

            // Collect all email addresses from contact (4 fields)
            // API field names: Email, Secondary_Email, Third_Email, DGPTMMail
            $emails = [];
            $email_fields = ['Email', 'Secondary_Email', 'Third_Email', 'DGPTMMail'];

            foreach ($email_fields as $field) {
                $email = $contact[$field] ?? '';
                if (!empty($email) && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $emails[] = strtolower(trim($email));
                }
            }

            $emails = array_unique($emails);

            $this->log('Contact emails collected', [
                'contact_id' => $contact_id,
                'emails' => $emails
            ], 'info');

            if (empty($emails)) {
                return new WP_Error('no_email', 'Keine E-Mail-Adresse im Kontakt gefunden.');
            }

            // Step 2: Search for tickets matching contact ID AND event, or fallback to email
            $ticket = $this->find_ticket_by_emails_and_event($emails, $event_id, $contact_id);

            if (is_wp_error($ticket)) {
                return $ticket;
            }

            return $ticket;
        }

        /**
         * Get Contact by Zoho Record ID
         */
        private function get_contact_by_id($contact_id) {
            $access_token = $this->get_access_token();

            if (is_wp_error($access_token)) {
                return $access_token;
            }

            $url = 'https://www.zohoapis.eu/crm/v8/' . self::ZOHO_MODULE_CONTACTS . '/' . $contact_id;

            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                    'Accept' => 'application/json'
                ],
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                error_log('EduGrant Contact Get Error: ' . $response->get_error_message());
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($status_code !== 200) {
                error_log('EduGrant Contact HTTP Error: ' . $status_code);
                return new WP_Error('api_error', 'Kontakt nicht gefunden.');
            }

            return $body['data'][0] ?? [];
        }

        /**
         * Find ticket by email addresses and event ID
         */
        private function find_ticket_by_emails_and_event($emails, $event_id, $contact_id) {
            // Use Search API instead of COQL (COQL has issues with IN operator)
            // Ticket fields: 'event' for event lookup, 'TN' for contact lookup

            $this->log('Searching for ticket via Search API', [
                'contact_id' => $contact_id,
                'event_id' => $event_id,
                'valid_statuses' => self::VALID_TICKET_STATUSES
            ], 'info');

            $access_token = $this->get_access_token();
            if (is_wp_error($access_token)) {
                return $access_token;
            }

            // Build status criteria with OR conditions
            $status_conditions = [];
            foreach (self::VALID_TICKET_STATUSES as $status) {
                $status_conditions[] = '(Status_Abrechnung:equals:' . $status . ')';
            }
            $status_criteria = '(' . implode('or', $status_conditions) . ')';

            // First try by TN (Contact) AND event
            $criteria = '((TN:equals:' . $contact_id . ')and(event:equals:' . $event_id . ')and' . $status_criteria . ')';
            $url = 'https://www.zohoapis.eu/crm/v8/' . self::ZOHO_MODULE_TICKETS . '/search?criteria=' . urlencode($criteria);

            $this->log('Ticket Search API (by contact)', ['url' => $url, 'criteria' => $criteria], 'info');

            $response = wp_remote_get($url, [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                    'Accept' => 'application/json'
                ],
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                $this->log('Ticket Search WP Error', ['error' => $response->get_error_message()], 'error');
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            $this->log('Ticket Search response (by contact)', [
                'status_code' => $status_code,
                'count' => isset($body['data']) ? count($body['data']) : 0
            ], 'info');

            // 200 = found, 204 = no content
            if ($status_code === 200 && !empty($body['data'])) {
                $ticket = $body['data'][0];
                return [
                    'has_ticket' => true,
                    'status' => $ticket['Status_Abrechnung'] ?? 'Gültig',
                    'ticket_id' => $ticket['id'] ?? ''
                ];
            }

            // Fallback: Search by email addresses
            foreach ($emails as $email) {
                $criteria = '(((Email:equals:' . $email . ')or(Secondary_Email:equals:' . $email . '))and(event:equals:' . $event_id . ')and' . $status_criteria . ')';
                $url = 'https://www.zohoapis.eu/crm/v8/' . self::ZOHO_MODULE_TICKETS . '/search?criteria=' . urlencode($criteria);

                $this->log('Ticket Search API (by email)', ['email' => $email, 'url' => $url], 'info');

                $response = wp_remote_get($url, [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                        'Accept' => 'application/json'
                    ],
                    'timeout' => 30
                ]);

                if (!is_wp_error($response)) {
                    $status_code = wp_remote_retrieve_response_code($response);
                    $body = json_decode(wp_remote_retrieve_body($response), true);

                    if ($status_code === 200 && !empty($body['data'])) {
                        $ticket = $body['data'][0];
                        return [
                            'has_ticket' => true,
                            'status' => $ticket['Status_Abrechnung'] ?? 'Gültig',
                            'ticket_id' => $ticket['id'] ?? '',
                            'matched_email' => $email
                        ];
                    }
                }
            }

            // No ticket found
            $this->log('No ticket found', [
                'contact_id' => $contact_id,
                'event_id' => $event_id,
                'emails_checked' => $emails
            ], 'info');

            return [
                'has_ticket' => false,
                'status' => null
            ];
        }

        /**
         * Check if event is external (no ticket verification needed)
         */
        public function is_external_event($event_id) {
            $event = $this->get_event_by_id($event_id);
            if (is_wp_error($event)) {
                return false;
            }
            return ($event['External_Event'] ?? false) === true;
        }

        /**
         * AJAX: Submit documents for EduGrant settlement
         */
        public function ajax_submit_documents() {
            check_ajax_referer('dgptm_edugrant_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Nicht angemeldet.']);
            }

            $eduid = sanitize_text_field($_POST['eduid'] ?? '');

            if (empty($eduid)) {
                wp_send_json_error(['message' => 'Keine EduGrant-ID angegeben.']);
            }

            // Verify ownership
            $grant = $this->get_edugrant_by_id($eduid);
            if (is_wp_error($grant)) {
                wp_send_json_error(['message' => $grant->get_error_message()]);
            }

            $user_zoho_id = get_user_meta(get_current_user_id(), 'zoho_id', true);
            $grant_contact_id = $grant['Kontakt']['id'] ?? $grant['Kontakt'] ?? '';

            if ($grant_contact_id !== $user_zoho_id) {
                wp_send_json_error(['message' => 'Zugriff verweigert.']);
            }

            // Prepare update data
            $update_data = [
                'Unterkunft' => floatval($_POST['unterkunft'] ?? 0),
                'Fahrtkosten' => floatval($_POST['fahrtkosten'] ?? 0),
                'Kilometer' => intval($_POST['kilometer'] ?? 0),
                'Hin_und_Rueckfahrt_mit_PKW' => ($_POST['hin_rueck'] ?? '0') === '1',
                'Teilnahmegebuehren' => floatval($_POST['teilnahmegebuehren'] ?? 0),
                'IBAN' => sanitize_text_field($_POST['iban'] ?? ''),
                'Kontoinhaber' => sanitize_text_field($_POST['kontoinhaber'] ?? ''),
                'Status' => 'Abrechnung eingereicht',
                'Abrechnung_eingereicht' => date('Y-m-d')
            ];

            // Calculate total
            $kilometer_cost = $update_data['Kilometer'] * 0.2;
            if ($update_data['Hin_und_Rueckfahrt_mit_PKW']) {
                $kilometer_cost *= 2;
            }
            $update_data['Summe_Eingaben'] = $update_data['Unterkunft'] + $update_data['Fahrtkosten'] + $kilometer_cost + $update_data['Teilnahmegebuehren'];

            // Update record in Zoho
            $result = $this->update_edugrant_record($eduid, $update_data);

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            wp_send_json_success([
                'message' => 'Abrechnung erfolgreich eingereicht!',
                'total' => $update_data['Summe_Eingaben']
            ]);
        }

        /**
         * Update EduGrant record in Zoho CRM
         */
        private function update_edugrant_record($edugrant_id, $data) {
            $access_token = $this->get_access_token();

            if (is_wp_error($access_token)) {
                return $access_token;
            }

            $url = 'https://www.zohoapis.eu/crm/v8/' . self::ZOHO_MODULE_EDUGRANT . '/' . $edugrant_id;

            $response = wp_remote_request($url, [
                'method' => 'PUT',
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'body' => json_encode(['data' => [$data]]),
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                error_log('EduGrant Update Error: ' . $response->get_error_message());
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            if ($status_code !== 200) {
                error_log('EduGrant Update HTTP Error: ' . $status_code . ' - ' . print_r($body, true));
                return new WP_Error('api_error', 'Fehler beim Aktualisieren: ' . ($body['message'] ?? 'Unbekannter Fehler'));
            }

            return $body;
        }

        /**
         * Increment EduBeantragt counter on event after successful application
         * This tracks the number of submitted (not yet approved) applications
         */
        private function increment_edubeantragt($event_id) {
            $this->log('Incrementing EduBeantragt for event', ['event_id' => $event_id], 'info');

            $access_token = $this->get_access_token();

            if (is_wp_error($access_token)) {
                $this->log('Increment EduBeantragt failed - no token', ['error' => $access_token->get_error_message()], 'error');
                return $access_token;
            }

            // First, get current value
            $event = $this->get_event_by_id($event_id);
            if (is_wp_error($event)) {
                $this->log('Increment EduBeantragt failed - event not found', ['event_id' => $event_id], 'error');
                return $event;
            }

            $current_value = intval($event['EduBeantragt'] ?? 0);
            $new_value = $current_value + 1;

            $this->log('EduBeantragt values', [
                'event_id' => $event_id,
                'current' => $current_value,
                'new' => $new_value
            ], 'info');

            // Update the event record
            $url = 'https://www.zohoapis.eu/crm/v8/' . self::ZOHO_MODULE_EVENTS . '/' . $event_id;

            $update_data = [
                'data' => [
                    [
                        'EduBeantragt' => $new_value
                    ]
                ]
            ];

            $response = wp_remote_request($url, [
                'method' => 'PUT',
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token,
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json'
                ],
                'body' => json_encode($update_data),
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                $this->log('Increment EduBeantragt WP Error', ['error' => $response->get_error_message()], 'error');
                return $response;
            }

            $status_code = wp_remote_retrieve_response_code($response);
            $body = json_decode(wp_remote_retrieve_body($response), true);

            $this->log('Increment EduBeantragt response', [
                'status_code' => $status_code,
                'response' => $body
            ], $status_code === 200 ? 'info' : 'error');

            if ($status_code !== 200) {
                $error_msg = $body['message'] ?? 'Unbekannter Fehler';
                return new WP_Error('api_error', 'Fehler beim Aktualisieren von EduBeantragt: ' . $error_msg);
            }

            return $new_value;
        }

        /**
         * Admin menu
         */
        public function add_admin_menu() {
            add_submenu_page(
                'dgptm-suite',
                'EduGrant Einstellungen',
                'EduGrant',
                'manage_options',
                'dgptm-edugrant-settings',
                [$this, 'render_settings_page']
            );
        }

        /**
         * Register settings
         */
        public function register_settings() {
            register_setting('dgptm_edugrant_settings', 'dgptm_edugrant_form_page');
            register_setting('dgptm_edugrant_settings', 'dgptm_edugrant_field_mapping');
        }

        /**
         * Render settings page
         */
        public function render_settings_page() {
            include $this->plugin_path . 'templates/admin-settings.php';
        }

        /**
         * Get status label and color
         */
        public static function get_status_info($status) {
            $statuses = [
                'Beantragt' => ['label' => 'Beantragt', 'color' => '#f0ad4e', 'icon' => 'clock'],
                'Genehmigt' => ['label' => 'Genehmigt', 'color' => '#5cb85c', 'icon' => 'yes'],
                'Unterlagen angefordert' => ['label' => 'Unterlagen angefordert', 'color' => '#5bc0de', 'icon' => 'upload'],
                'Abrechnung eingereicht' => ['label' => 'Abrechnung eingereicht', 'color' => '#337ab7', 'icon' => 'media-document'],
                'Überwiesen' => ['label' => 'Überwiesen', 'color' => '#5cb85c', 'icon' => 'yes-alt'],
                'Abgelehnt' => ['label' => 'Abgelehnt', 'color' => '#d9534f', 'icon' => 'no'],
                'Nachberechnen' => ['label' => 'Nachberechnung', 'color' => '#f0ad4e', 'icon' => 'warning']
            ];

            return $statuses[$status] ?? ['label' => $status, 'color' => '#999', 'icon' => 'marker'];
        }
    }
}

// Prevent double initialization
if (!isset($GLOBALS['dgptm_edugrant_initialized'])) {
    $GLOBALS['dgptm_edugrant_initialized'] = true;
    DGPTM_EduGrant_Manager::get_instance();
}
