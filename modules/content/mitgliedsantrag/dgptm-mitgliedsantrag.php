<?php
/**
 * Plugin Name: DGPTM - Mitgliedsantrag
 * Description: Satzungskonformes Mitgliedsantragsformular (§4) mit dynamischen Bürgenanforderungen, Qualifikationsnachweisen und Zoho CRM Integration
 * Version: 2.4.4
 * Author: Sebastian Melzer
 * Text Domain: dgptm-mitgliedsantrag
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Helper-Funktion für Mitgliedsantrag Settings
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
        private $version = '2.4.4';

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

            // Bürgen-Bestätigung Shortcode
            add_shortcode('buergenbestaetigung', [$this, 'render_buergenbestaetigung']);

            // AJAX Handler für Genehmigung/Ablehnung
            add_action('wp_ajax_dgptm_vorstand_entscheidung', [$this, 'ajax_vorstand_entscheidung']);
            add_action('wp_ajax_nopriv_dgptm_vorstand_entscheidung', [$this, 'ajax_vorstand_entscheidung']);

            // AJAX Handler für Bürgen-Bestätigung
            add_action('wp_ajax_dgptm_buerge_entscheidung', [$this, 'ajax_buerge_entscheidung']);
            add_action('wp_ajax_nopriv_dgptm_buerge_entscheidung', [$this, 'ajax_buerge_entscheidung']);

            // Download-Proxy für Zoho CRM File-Upload-Felder (Nachweise)
            add_action('wp_ajax_dgptm_download_crm_file', [$this, 'ajax_download_crm_file']);
            add_action('wp_ajax_nopriv_dgptm_download_crm_file', [$this, 'ajax_download_crm_file']);

            // Admin-Action: Vorstands-Cache leeren (z. B. nach Tag-Änderung im CRM)
            add_action('wp_ajax_dgptm_clear_vorstand_cache', [$this, 'ajax_clear_vorstand_cache']);
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
            // Temporärer Diagnose-Endpoint (Health-Check-Token oder Admin)
            register_rest_route('dgptm/v1', '/diagnose-contact/(?P<id>\d+)', [
                'methods' => 'GET',
                'callback' => [$this, 'rest_diagnose_contact'],
                'permission_callback' => function($request) {
                    if (current_user_can('manage_options')) return true;
                    $auth = $request->get_header('Authorization');
                    if ($auth && strpos($auth, 'Bearer ') === 0) {
                        $token = substr($auth, 7);
                        return $token === get_option('dgptm_health_check_token', '');
                    }
                    $token = $request->get_param('token');
                    return $token && $token === get_option('dgptm_health_check_token', '');
                },
                'args' => [
                    'id' => ['validate_callback' => function($param) { return is_numeric($param); }]
                ]
            ]);

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

            // Test-Endpoint für Bürgen-Mail (Admin oder Health-Check-Token)
            register_rest_route('dgptm/v1', '/test-buergenmail', [
                'methods'             => 'GET',
                'callback'            => [$this, 'rest_test_buergenmail'],
                'permission_callback' => function ($request) {
                    if (current_user_can('manage_options')) return true;
                    $token = $request->get_param('auth');
                    return $token && hash_equals(get_option('dgptm_health_check_token', ''), $token);
                },
                'args' => [
                    'contact_id' => ['required' => true, 'validate_callback' => function ($p) { return is_numeric($p); }],
                    'to'         => ['required' => true, 'validate_callback' => function ($p) { return is_email($p); }],
                    'slot'       => ['required' => false]
                ]
            ]);
        }

        /**
         * REST: Bürgen-Mail testweise an beliebige E-Mail-Adresse versenden.
         * Nutzt dieselbe Render- und Versand-Logik wie der automatische Flow,
         * überschreibt aber nur den Empfänger.
         */
        public function rest_test_buergenmail($request) {
            $contact_id = (string) $request->get_param('contact_id');
            $to         = sanitize_email((string) $request->get_param('to'));
            $slot_raw   = (string) ($request->get_param('slot') ?? '1');
            $slot       = in_array($slot_raw, ['1', '2'], true) ? (int) $slot_raw : 1;

            $oauth = $this->get_access_token();
            if (!$oauth) {
                return new WP_REST_Response(['error' => 'CRM-Verbindung fehlgeschlagen'], 502);
            }

            $contact = $this->get_contact_details($contact_id, $oauth);
            if (!$contact) {
                return new WP_REST_Response(['error' => 'Contact ' . $contact_id . ' nicht gefunden'], 404);
            }

            // Für den Test: Bürgen-Mail im Contact durch Empfänger-Adresse ersetzen,
            // damit der CTA-Link auf das Test-Postfach zeigt — egal ob der echte
            // Contact eine Bürgen-Mail hat oder nicht.
            $buerge_mail_real            = $contact['Guarantor_Mail_' . $slot] ?? null;
            $contact['Guarantor_Mail_' . $slot] = $to;

            $options     = dgptm_ma_get_options();
            $template    = !empty($options['buerge_mail_template'])
                ? $options['buerge_mail_template']
                : $this->get_default_buerge_mail_template();
            $subject_tpl = !empty($options['buerge_mail_subject'])
                ? $options['buerge_mail_subject']
                : 'Bürgschaft für Mitgliedsantrag ${Kontakte.Vorname} ${Kontakte.Nachname}';

            $body    = $this->render_buerge_mail_body($template, $contact, $slot);
            $subject = '[TEST] ' . wp_strip_all_tags($this->render_buerge_mail_body($subject_tpl, $contact, $slot));
            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: DGPTM Geschäftsstelle <nichtantworten@dgptm.de>'
            ];

            $sent = wp_mail($to, $subject, $body, $headers);

            return new WP_REST_Response([
                'sent'             => (bool) $sent,
                'to'               => $to,
                'contact_id'       => $contact_id,
                'slot'             => $slot,
                'subject'          => $subject,
                'buerge_mail_real' => $contact['Guarantor_Mail_' . $slot] ?? null,
                'note'             => $sent ? 'Testmail versendet.' : 'wp_mail() lieferte false — Mailserver-Konfiguration prüfen.'
            ], $sent ? 200 : 500);
        }

        public function rest_diagnose_contact($request) {
            $contact_id = $request->get_param('id');
            $token = $this->get_access_token();

            if (!$token) {
                return new WP_REST_Response(['error' => 'Kein OAuth-Token'], 500);
            }

            $result = [];

            // 1. Kontakt abrufen
            $contact_resp = wp_remote_get(
                'https://www.zohoapis.eu/crm/v8/Contacts/' . $contact_id,
                ['headers' => ['Authorization' => 'Zoho-oauthtoken ' . $token], 'timeout' => 30]
            );

            if (!is_wp_error($contact_resp)) {
                $contact_body = json_decode(wp_remote_retrieve_body($contact_resp), true);
                $contact = $contact_body['data'][0] ?? null;

                if ($contact) {
                    // Alle nicht-null Felder anzeigen + kritische Felder immer
                    $always_show = ['id','First_Name','Last_Name','Email','Secondary_Email','Third_Email',
                        'Membership_Type','Membership_Status','Contact_Status','Bemerkung',
                        'Other_Street','Other_City','Other_Zip','Other_State','Other_Country',
                        'Mailing_Street','Mailing_City','Mailing_Zip','Mailing_State','Mailing_Country',
                        'Guarantor_Name_1','Guarantor_Mail_1','Guarantor_Status_1',
                        'Guarantor_Name_2','Guarantor_Mail_2','Guarantor_Status_2',
                        'employer_name','Freigestellt_bis','profession',
                        'Salutation','Academic_Title','Phone','Work_Phone',
                        'SatzungAkzeptiert','BeitragAkzeptiert','Datenschutzakzeptiert',
                        'Modified_Time'];
                    $result['contact'] = [];
                    foreach ($always_show as $key) {
                        $result['contact'][$key] = $contact[$key] ?? null;
                    }
                    // Zusätzlich alle nicht-null Custom-Felder (Arrays als JSON-String)
                    $result['contact_extra'] = [];
                    foreach ($contact as $key => $val) {
                        if ($val === null || $val === '' || in_array($key, $always_show, true)) {
                            continue;
                        }
                        $result['contact_extra'][$key] = is_array($val) ? wp_json_encode($val) : $val;
                    }

                    // Spezialblock für File-Upload-Felder (Nachweise)
                    $result['nachweise_raw'] = [
                        'StudinachweisDirekt' => $contact['StudinachweisDirekt'] ?? null,
                        'QualiNachweisDirekt' => $contact['QualiNachweisDirekt'] ?? null
                    ];
                } else {
                    $result['contact'] = ['error' => 'Kontakt nicht gefunden', 'raw' => $contact_body];
                }
            } else {
                $result['contact'] = ['error' => $contact_resp->get_error_message()];
            }

            // 2. Blueprint-Status abfragen
            $bp_resp = wp_remote_get(
                'https://www.zohoapis.eu/crm/v8/Contacts/' . $contact_id . '/actions/blueprint',
                ['headers' => ['Authorization' => 'Zoho-oauthtoken ' . $token], 'timeout' => 30]
            );

            if (!is_wp_error($bp_resp)) {
                $result['blueprint'] = [
                    'http_code' => wp_remote_retrieve_response_code($bp_resp),
                    'response'  => json_decode(wp_remote_retrieve_body($bp_resp), true)
                ];
            } else {
                $result['blueprint'] = ['error' => $bp_resp->get_error_message()];
            }

            // 3. Check application status
            if (isset($contact)) {
                $result['application_check'] = $this->check_application_status($contact);
            }

            return new WP_REST_Response($result, 200);
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
            if (isset($input['blueprint_transition_id'])) {
                $sanitized['blueprint_transition_id'] = sanitize_text_field($input['blueprint_transition_id']);
            }
            if (isset($input['default_country_code'])) {
                $sanitized['default_country_code'] = sanitize_text_field($input['default_country_code']);
            }
            if (isset($input['field_mapping'])) {
                $sanitized['field_mapping'] = $input['field_mapping']; // Will be JSON
            }
            if (isset($input['buerge_mail_subject'])) {
                $sanitized['buerge_mail_subject'] = sanitize_text_field($input['buerge_mail_subject']);
            }
            if (isset($input['buerge_mail_template'])) {
                // Mail-Template darf HTML enthalten – keine aggressive Sanitize
                $sanitized['buerge_mail_template'] = wp_unslash($input['buerge_mail_template']);
            }

            return $sanitized;
        }

        /**
         * Default-HTML-Template für Bürg:innen-Mails (aus templates/buergen-mail-default.html).
         */
        private function get_default_buerge_mail_template() {
            $path = $this->plugin_path . 'templates/buergen-mail-default.html';
            return is_readable($path) ? file_get_contents($path) : '';
        }

        /**
         * Ersetzt Zoho-Style-Platzhalter ${Kontakte.Feldname} im Template
         * durch Werte aus dem Contact-Record + aktuellen Bürg:innen-Slot.
         */
        private function render_buerge_mail_body($template, $contact, $slot) {
            $buerge_name = $this->format_personen_name($contact['Guarantor_Name_' . $slot] ?? '');
            $buerge_mail = $contact['Guarantor_Mail_' . $slot] ?? '';
            $stadt       = $contact['Mailing_City'] ?? $contact['Other_City'] ?? '';

            $map = [
                'Vorname'           => $contact['First_Name'] ?? '',
                'Nachname'          => $contact['Last_Name']  ?? '',
                'Postadresse Stadt' => $stadt,
                'Bürge Name 1'      => $buerge_name,
                'Bürge Name 2'      => $buerge_name,
                'Bürge Mail 1'      => $buerge_mail,
                'Bürge Mail 2'      => $buerge_mail,
                'token'             => $contact['token']      ?? '',
                'Email'             => $contact['Email']      ?? ''
            ];

            return preg_replace_callback('/\$\{Kontakte\.([^\}]+)\}/u', function ($m) use ($map) {
                $key = trim($m[1]);
                return isset($map[$key]) ? (string) $map[$key] : $m[0];
            }, (string) $template);
        }

        /**
         * Sendet die Bürg:innen-Bestätigungs-Mail für den angegebenen Slot,
         * sofern Mail-Adresse vorhanden und Status noch nicht bestätigt ist.
         */
        private function send_buerge_mail($contact, $slot) {
            $mail = trim((string) ($contact['Guarantor_Mail_' . $slot] ?? ''));
            if ($mail === '' || !is_email($mail)) {
                return false;
            }
            if (!empty($contact['Guarantor_Status_' . $slot])) {
                return false;
            }

            $options     = dgptm_ma_get_options();
            $template    = !empty($options['buerge_mail_template'])
                ? $options['buerge_mail_template']
                : $this->get_default_buerge_mail_template();
            $subject_tpl = !empty($options['buerge_mail_subject'])
                ? $options['buerge_mail_subject']
                : 'Bürgschaft für Mitgliedsantrag ${Kontakte.Vorname} ${Kontakte.Nachname}';

            $body    = $this->render_buerge_mail_body($template, $contact, $slot);
            $subject = wp_strip_all_tags($this->render_buerge_mail_body($subject_tpl, $contact, $slot));

            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: DGPTM Geschäftsstelle <nichtantworten@dgptm.de>'
            ];

            $sent = wp_mail($mail, $subject, $body, $headers);
            $this->log('Bürgenmail Slot ' . $slot . ' an ' . $mail . ' -> ' . ($sent ? 'OK' : 'FEHLER'));
            return $sent;
        }

        /**
         * Schickt – sofern notwendig – Mails an beide Bürg:innen-Slots.
         * "Notwendig" = Mail-Adresse vorhanden + Guarantor_Status_X noch nicht true.
         * Nach erfolgreichem Versand wird das CRM-Feld mails_guarantor_sendet = true gesetzt.
         */
        private function maybe_send_buergen_mails($contact) {
            $sent_count = 0;
            foreach ([1, 2] as $slot) {
                if ($this->send_buerge_mail($contact, $slot)) {
                    $sent_count++;
                }
            }
            if ($sent_count > 0 && !empty($contact['id'])) {
                $this->mark_buergen_mails_sent($contact);
            }
            return $sent_count;
        }

        /**
         * Setzt im CRM das Flag mails_guarantor_sendet = true und ergänzt im
         * Bemerkung-Feld " | Bürgen angeschrieben: dd.mm.YYYY HH:MM".
         * Nicht blockierend — Fehler landen nur im Log.
         */
        private function mark_buergen_mails_sent($contact) {
            $oauth = $this->get_access_token();
            if (!$oauth) {
                $this->log('WARN mark_buergen_mails_sent: kein OAuth-Token verfügbar');
                return;
            }

            $zeitstempel    = current_time('d.m.Y H:i');
            $alt_bemerkung  = trim((string) ($contact['Bemerkung'] ?? ''));
            $suffix         = 'Bürgen angeschrieben: ' . $zeitstempel;
            $neue_bemerkung = $alt_bemerkung !== '' ? $alt_bemerkung . ' | ' . $suffix : $suffix;

            $response = wp_remote_request(
                'https://www.zohoapis.eu/crm/v8/Contacts/' . $contact['id'],
                [
                    'method'  => 'PUT',
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $oauth,
                        'Content-Type'  => 'application/json'
                    ],
                    'body'    => wp_json_encode(['data' => [[
                        'mails_guarantor_sendet' => true,
                        'Bemerkung'              => $neue_bemerkung
                    ]]]),
                    'timeout' => 30
                ]
            );

            if (is_wp_error($response)) {
                $this->log('WARN mark_buergen_mails_sent: ' . $response->get_error_message());
                return;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code < 200 || $http_code >= 300) {
                $this->log('WARN mark_buergen_mails_sent HTTP ' . $http_code . ' body: ' . substr(wp_remote_retrieve_body($response), 0, 300));
            }
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
            dgptm_log_verbose('OAuth: Starting get_access_token()', 'mitgliedsantrag');

            // Check if using crm-abruf module's tokens
            if (class_exists('DGPTM_Zoho_Plugin')) {
                dgptm_log_verbose('OAuth: DGPTM_Zoho_Plugin class exists', 'mitgliedsantrag');

                // Try to use crm-abruf module's get_oauth_token() method
                $crm_instance = DGPTM_Zoho_Plugin::get_instance();
                if (method_exists($crm_instance, 'get_oauth_token')) {
                    dgptm_log_verbose('OAuth: Calling crm-abruf get_oauth_token()', 'mitgliedsantrag');
                    $token = $crm_instance->get_oauth_token();
                    if (is_wp_error($token)) {
                        dgptm_log_error('OAuth: get_oauth_token() returned WP_Error: ' . $token->get_error_message(), 'mitgliedsantrag');
                    } elseif (!empty($token)) {
                        dgptm_log_verbose('OAuth: SUCCESS - Got token via get_oauth_token()', 'mitgliedsantrag');
                        $this->log('Using crm-abruf module OAuth token via get_oauth_token()');
                        return $token;
                    } else {
                        dgptm_log_warning('OAuth: get_oauth_token() returned empty', 'mitgliedsantrag');
                    }
                }

                // Fallback: read crm-abruf tokens directly from options
                $crm_access_token = get_option('dgptm_zoho_access_token', '');
                $crm_token_expires = (int) get_option('dgptm_zoho_token_expires', 0);
                $crm_refresh_token = get_option('dgptm_zoho_refresh_token', '');

                dgptm_log_verbose('OAuth: Direct options check:', 'mitgliedsantrag');
                dgptm_log_verbose('OAuth: - access_token exists: ' . (!empty($crm_access_token) ? 'YES' : 'NO'), 'mitgliedsantrag');
                dgptm_log_verbose('OAuth: - refresh_token exists: ' . (!empty($crm_refresh_token) ? 'YES' : 'NO'), 'mitgliedsantrag');
                dgptm_log_verbose('OAuth: - token_expires: ' . $crm_token_expires . ' (now: ' . time() . ')', 'mitgliedsantrag');

                if (!empty($crm_access_token) && time() < $crm_token_expires) {
                    dgptm_log_verbose('OAuth: SUCCESS - Token valid from options', 'mitgliedsantrag');
                    $this->log('Using crm-abruf module OAuth token from options');
                    return $crm_access_token;
                } elseif (!empty($crm_refresh_token)) {
                    // Token expired or missing, try to refresh
                    dgptm_log_verbose('OAuth: Token expired or missing, attempting refresh', 'mitgliedsantrag');
                    $client_id = get_option('dgptm_zoho_client_id', '');
                    $client_secret = get_option('dgptm_zoho_client_secret', '');

                    dgptm_log_verbose('OAuth: - client_id exists: ' . (!empty($client_id) ? 'YES' : 'NO'), 'mitgliedsantrag');
                    dgptm_log_verbose('OAuth: - client_secret exists: ' . (!empty($client_secret) ? 'YES' : 'NO'), 'mitgliedsantrag');

                    if (!empty($client_id) && !empty($client_secret)) {
                        $response = wp_remote_post('https://accounts.zoho.eu/oauth/v2/token', [
                            'body' => [
                                'refresh_token' => $crm_refresh_token,
                                'client_id' => $client_id,
                                'client_secret' => $client_secret,
                                'grant_type' => 'refresh_token'
                            ]
                        ]);

                        if (is_wp_error($response)) {
                            dgptm_log_error('OAuth: Refresh request failed: ' . $response->get_error_message(), 'mitgliedsantrag');
                        } else {
                            $body = json_decode(wp_remote_retrieve_body($response), true);
                            dgptm_log_verbose('OAuth: Refresh response: ' . wp_json_encode($body), 'mitgliedsantrag');

                            if (isset($body['access_token'])) {
                                update_option('dgptm_zoho_access_token', $body['access_token']);
                                update_option('dgptm_zoho_token_expires', time() + ($body['expires_in'] ?? 3600) - 60);
                                dgptm_log_info('OAuth: Token refreshed successfully', 'mitgliedsantrag');
                                $this->log('crm-abruf token refreshed successfully');
                                return $body['access_token'];
                            } else {
                                dgptm_log_error('OAuth: Refresh failed - no access_token in response', 'mitgliedsantrag');
                            }
                        }
                    } else {
                        dgptm_log_error('OAuth: Cannot refresh - missing client_id or client_secret', 'mitgliedsantrag');
                    }
                } else {
                    dgptm_log_warning('OAuth: No crm-abruf tokens available', 'mitgliedsantrag');
                }
            } else {
                dgptm_log_warning('OAuth: DGPTM_Zoho_Plugin class NOT found', 'mitgliedsantrag');
            }

            // Fallback to module's own tokens
            dgptm_log_verbose('OAuth: Checking module own tokens', 'mitgliedsantrag');
            if (empty($options['access_token'])) {
                dgptm_log_error('OAuth: No access token available anywhere', 'mitgliedsantrag');
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
            // Fallback: Name-Suche, dann Email abgleichen
            $criteria = '((First_Name:equals:' . urlencode($first_name) . ')and(Last_Name:equals:' . urlencode($last_name) . '))';
            $response = wp_remote_get(
                'https://www.zohoapis.eu/crm/v8/Contacts/search?criteria=' . $criteria,
                ['headers' => ['Authorization' => 'Zoho-oauthtoken ' . $token], 'timeout' => 30]
            );

            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);

                if (isset($body['data']) && !empty($body['data'])) {
                    foreach ($body['data'] as $contact) {
                        $contact_emails = [
                            strtolower($contact['Email'] ?? ''),
                            strtolower($contact['Secondary_Email'] ?? ''),
                            strtolower($contact['Third_Email'] ?? ''),
                            strtolower($contact['DGPTMMail'] ?? '')
                        ];

                        if (in_array(strtolower($email), $contact_emails)) {
                            $this->log('Contact found by name+email: ' . $contact['id']);
                            return $contact;
                        }
                    }

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
            $this->log('Searching for email in all email fields: ' . $email);

            // Alle E-Mail-Felder durchsuchen inkl. DGPTMMail
            $email_fields = ['Email', 'Secondary_Email', 'Third_Email', 'DGPTMMail'];
            $email_lower = strtolower($email);

            foreach ($email_fields as $field) {
                $this->log('Searching in field: ' . $field);
                $contact = $this->search_by_field($field, $email, $token);

                if ($contact) {
                    $this->log('Email found via ' . $field . ' - Contact ID: ' . $contact['id']);
                    return $contact;
                }
            }

            $this->log('Email not found in any email field');
            return false;
        }

        private function search_by_name($name, $token) {
            $name_parts = preg_split('/\s+/', trim($name));

            if (count($name_parts) < 2) {
                $this->log('Name search requires at least first and last name');
                return false;
            }

            // Exakte Suche
            $criteria = '((First_Name:equals:' . urlencode($name_parts[0]) . ')and(Last_Name:equals:' . urlencode(end($name_parts)) . '))';
            $response = wp_remote_get(
                'https://www.zohoapis.eu/crm/v8/Contacts/search?criteria=' . $criteria,
                ['headers' => ['Authorization' => 'Zoho-oauthtoken ' . $token], 'timeout' => 30]
            );

            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                if (isset($body['data']) && !empty($body['data'])) {
                    return $body['data'][0];
                }
            }

            // Fuzzy: nur Nachname
            $response2 = wp_remote_get(
                'https://www.zohoapis.eu/crm/v8/Contacts/search?criteria=(Last_Name:equals:' . urlencode(end($name_parts)) . ')',
                ['headers' => ['Authorization' => 'Zoho-oauthtoken ' . $token], 'timeout' => 30]
            );

            if (!is_wp_error($response2)) {
                $body2 = json_decode(wp_remote_retrieve_body($response2), true);
                if (isset($body2['data']) && !empty($body2['data'])) {
                    foreach ($body2['data'] as $contact) {
                        $full_name = strtolower(($contact['First_Name'] ?? '') . ' ' . ($contact['Last_Name'] ?? ''));
                        similar_text($full_name, strtolower($name), $percent);
                        if ($percent > 70) {
                            return $contact;
                        }
                    }
                }
            }

            return false;
        }

        private function search_by_field($field, $value, $token) {
            $url = 'https://www.zohoapis.eu/crm/v8/Contacts/search?criteria=(' . $field . ':equals:' . urlencode($value) . ')';

            $response = wp_remote_get($url, [
                'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $token],
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                dgptm_log_error('Search WP_Error: ' . $field . '=' . $value . ': ' . $response->get_error_message(), 'mitgliedsantrag');
                return false;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $raw = wp_remote_retrieve_body($response);
            $body = json_decode($raw, true);

            if (isset($body['data']) && !empty($body['data'])) {
                dgptm_log_info('Search: ' . $field . '=' . $value . ' -> gefunden: ' . $body['data'][0]['id'], 'mitgliedsantrag');
                return $body['data'][0];
            }

            // HTTP 204 = nicht gefunden (normal), alles andere loggen
            if ($http_code !== 204) {
                dgptm_log_warning('Search: ' . $field . '=' . $value . ' -> HTTP ' . $http_code . ' | ' . substr($raw, 0, 300), 'mitgliedsantrag');
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
            // Contact_Status aus Blueprint ist die primaere Quelle
            $contact_status = $contact['Contact_Status'] ?? '';
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
                'Contact_Status' => $contact_status,
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
                'https://www.zohoapis.eu/crm/v8/Contacts/search?criteria=' . urlencode($search_query),
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
            dgptm_log_verbose('ajax_submit_application called', 'mitgliedsantrag');

            try {
                check_ajax_referer('dgptm_mitgliedsantrag_nonce', 'nonce');
                dgptm_log_verbose('Nonce verified', 'mitgliedsantrag');
            } catch (Exception $e) {
                dgptm_log_error('Nonce verification failed: ' . $e->getMessage(), 'mitgliedsantrag');
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
                dgptm_log_warning('Satzung not accepted', 'mitgliedsantrag');
                wp_send_json_error(['message' => 'Bitte bestätigen Sie die Anerkennung der Satzung.']);
                return;
            }
            if (!$data['beitrag_akzeptiert']) {
                dgptm_log_warning('Beitrag not accepted', 'mitgliedsantrag');
                wp_send_json_error(['message' => 'Bitte bestätigen Sie die Kenntnisnahme der Beitragspflicht.']);
                return;
            }
            if (!$data['dsgvo_akzeptiert']) {
                dgptm_log_warning('DSGVO not accepted', 'mitgliedsantrag');
                wp_send_json_error(['message' => 'Bitte stimmen Sie der Datenschutzerklärung zu.']);
                return;
            }

            $this->log('Normalized phones - Phone1: ' . $data['telefon1'] . ', Phone2: ' . $data['telefon2']);

            // Handle file uploads
            dgptm_log_verbose('Data collected, processing file uploads if needed', 'mitgliedsantrag');

            // Student certificate upload
            $studienbescheinigung_id = 0;
            if ($data['ist_student'] && isset($_FILES['studienbescheinigung'])) {
                dgptm_log_verbose('Student certificate upload detected', 'mitgliedsantrag');
                $uploaded = $this->handle_file_upload($_FILES['studienbescheinigung']);
                if (!$uploaded) {
                    dgptm_log_error('Student certificate upload failed', 'mitgliedsantrag');
                    wp_send_json_error(['message' => 'Fehler beim Hochladen der Studienbescheinigung']);
                    return;
                }
                $studienbescheinigung_id = $uploaded;
                dgptm_log_verbose('Student certificate uploaded, ID: ' . $studienbescheinigung_id, 'mitgliedsantrag');
            }

            // Qualification certificate upload
            $qualifikation_nachweis_id = 0;
            if ($data['hat_qualifikation'] === 'ja' && isset($_FILES['qualifikation_nachweis'])) {
                dgptm_log_verbose('Qualification certificate upload detected', 'mitgliedsantrag');
                $uploaded = $this->handle_file_upload($_FILES['qualifikation_nachweis']);
                if (!$uploaded) {
                    dgptm_log_error('Qualification certificate upload failed', 'mitgliedsantrag');
                    wp_send_json_error(['message' => 'Fehler beim Hochladen des Qualifikationsnachweises']);
                    return;
                }
                $qualifikation_nachweis_id = $uploaded;
                dgptm_log_verbose('Qualification certificate uploaded, ID: ' . $qualifikation_nachweis_id, 'mitgliedsantrag');
            }

            // Create or update contact in Zoho CRM
            dgptm_log_verbose('Getting access token', 'mitgliedsantrag');
            $token = $this->get_access_token();
            if (!$token) {
                dgptm_log_error('No access token available', 'mitgliedsantrag');
                wp_send_json_error(['message' => 'OAuth-Verbindung nicht konfiguriert. Bitte konfigurieren Sie die Zoho CRM Verbindung in den Einstellungen.']);
                return;
            }

            dgptm_log_verbose('Access token obtained, creating/updating contact', 'mitgliedsantrag');
            $contact_result = $this->create_or_update_contact($data, $studienbescheinigung_id, $qualifikation_nachweis_id, $token);

            // Check if error was returned (existing application/membership)
            if (is_array($contact_result) && isset($contact_result['error'])) {
                dgptm_log_error('Application blocked: ' . $contact_result['message'], 'mitgliedsantrag');
                wp_send_json_error(['message' => $contact_result['message']]);
                return;
            }

            // Check if contact creation failed
            if (!$contact_result) {
                dgptm_log_error('Contact creation/update returned false', 'mitgliedsantrag');
                wp_send_json_error(['message' => 'Fehler beim Erstellen des Kontakts in Zoho CRM. Bitte prüfen Sie das Debug-Log für Details.']);
                return;
            }

            $contact_id = $contact_result;
            dgptm_log_verbose('Contact created/updated with ID: ' . $contact_id, 'mitgliedsantrag');

            // Blueprint-Transition ausloesen (startet CRM-Workflow)
            dgptm_log_verbose('Triggering blueprint transition', 'mitgliedsantrag');
            $blueprint_result = $this->trigger_blueprint($contact_id, $token);

            if (!$blueprint_result['success'] && empty($blueprint_result['skipped'])) {
                dgptm_log_warning('Blueprint trigger failed: ' . $blueprint_result['message'], 'mitgliedsantrag');
            } else {
                dgptm_log_verbose('Blueprint triggered successfully', 'mitgliedsantrag');
            }

            // Infomail an Geschäftsstelle
            $this->send_notification_email($data, $contact_id);

            // Bürg:innen-Mails versenden (sofern Mail-Adresse vorhanden + Status noch offen)
            $fresh_contact = $this->get_contact_details($contact_id, $token);
            if ($fresh_contact) {
                $sent = $this->maybe_send_buergen_mails($fresh_contact);
                dgptm_log_info('Bürgen-Mails versendet: ' . $sent . ' fuer Contact ' . $contact_id, 'mitgliedsantrag');
            }

            // Schedule deletion of uploaded certificates after 10 minutes
            if ($studienbescheinigung_id > 0) {
                wp_schedule_single_event(time() + 600, 'dgptm_delete_student_certificate', [$studienbescheinigung_id]);
                dgptm_log_verbose('Scheduled deletion of student certificate ID ' . $studienbescheinigung_id . ' in 10 minutes', 'mitgliedsantrag');
            }

            if ($qualifikation_nachweis_id > 0) {
                wp_schedule_single_event(time() + 600, 'dgptm_delete_student_certificate', [$qualifikation_nachweis_id]);
                dgptm_log_verbose('Scheduled deletion of qualification certificate ID ' . $qualifikation_nachweis_id . ' in 10 minutes', 'mitgliedsantrag');
            }

            dgptm_log_info('Application submitted successfully for contact ' . $contact_id, 'mitgliedsantrag');

            wp_send_json_success([
                'message' => 'Ihr Mitgliedsantrag wurde erfolgreich eingereicht!',
                'contact_id' => $contact_id
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
                'Mailing_Street' => 'strasse',
                'Mailing_City' => 'stadt',
                'Mailing_State' => 'bundesland',
                'Mailing_Zip' => 'plz',
                'Mailing_Country' => 'land',
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

            if ($existing_contact) {
                dgptm_log_info('Kontakt gefunden: ' . $existing_contact['id'] . ' (' . ($existing_contact['First_Name'] ?? '') . ' ' . ($existing_contact['Last_Name'] ?? '') . ') -> UPDATE', 'mitgliedsantrag');
            } else {
                dgptm_log_info('Kein Kontakt gefunden für ' . $data['email1'] . ' / ' . $data['vorname'] . ' ' . $data['nachname'] . ' -> CREATE', 'mitgliedsantrag');
            }

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

            // Zoho Date-Felder: leere/ungültige Werte verursachen INVALID_DATA
            $zoho_date_fields = ['Freigestellt_bis', 'Date_of_Birth'];

            // Map form data to CRM fields
            foreach ($mapping as $crm_field => $form_field) {
                if (isset($data[$form_field])) {
                    $value = $data[$form_field];

                    // Date-Felder: nur gültige YYYY-MM-DD Werte senden
                    if (in_array($crm_field, $zoho_date_fields)) {
                        if (empty($value) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                            dgptm_log_info('Skipping date field ' . $crm_field . ' (value: "' . $value . '")', 'mitgliedsantrag');
                            continue;
                        }
                    }

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

            // Qualifikationsnachweis-URL landet nicht im Bemerkung-Feld —
            // er liegt ohnehin als File-Upload im QualiNachweisDirekt-Feld und ist
            // über den Download-Proxy im Vorstandsgenehmigungs-Flow erreichbar.

            dgptm_log_info('Contact payload: ' . substr(wp_json_encode($contact_data), 0, 2000), 'mitgliedsantrag');

            if ($existing_contact) {
                // Update existing contact
                $contact_id = $existing_contact['id'];
                $this->log('Updating existing contact: ' . $contact_id);

                $response = wp_remote_request(
                    'https://www.zohoapis.eu/crm/v8/Contacts/' . $contact_id,
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
                    'https://www.zohoapis.eu/crm/v8/Contacts',
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
                dgptm_log_error('Zoho API WP_Error: ' . $response->get_error_message(), 'mitgliedsantrag');
                return false;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $raw_body = wp_remote_retrieve_body($response);
            $body = json_decode($raw_body, true);

            $this->log('Zoho CRM HTTP Code: ' . $http_code);
            $this->log('Zoho CRM response: ' . $raw_body);

            if (isset($body['data'][0]['details']['id'])) {
                $contact_id = $body['data'][0]['details']['id'];
                dgptm_log_info('Contact ' . ($existing_contact ? 'updated' : 'created') . ': ' . $contact_id, 'mitgliedsantrag');
            } elseif (isset($body['data'][0]['code']) && $body['data'][0]['code'] === 'SUCCESS') {
                $contact_id = $existing_contact['id'] ?? false;
                dgptm_log_info('Contact updated (no new ID): ' . $contact_id, 'mitgliedsantrag');
            } else {
                $zoho_code = $body['data'][0]['code'] ?? ($body['code'] ?? 'UNKNOWN');
                $zoho_msg = $body['data'][0]['message'] ?? ($body['message'] ?? '');
                $zoho_details = $body['data'][0]['details'] ?? [];

                // DUPLICATE_DATA: Kontakt existiert - ID aus Fehler extrahieren und UPDATE
                if ($zoho_code === 'DUPLICATE_DATA' && !empty($zoho_details['duplicate_record']['id'])) {
                    $dup_id = $zoho_details['duplicate_record']['id'];
                    dgptm_log_error('DUPLICATE_DATA erkannt -> Fallback UPDATE auf ' . $dup_id, 'mitgliedsantrag');

                    $retry = wp_remote_request(
                        'https://www.zohoapis.eu/crm/v8/Contacts/' . $dup_id,
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

                    if (!is_wp_error($retry)) {
                        $retry_code = wp_remote_retrieve_response_code($retry);
                        $retry_body = json_decode(wp_remote_retrieve_body($retry), true);

                        if ($retry_code >= 200 && $retry_code < 300) {
                            $contact_id = $dup_id;
                            dgptm_log_info('DUPLICATE_DATA Fallback UPDATE erfolgreich: ' . $contact_id, 'mitgliedsantrag');
                            // Weiter mit dem normalen Flow (File-Uploads etc.)
                        } else {
                            dgptm_log_error('DUPLICATE_DATA Fallback UPDATE fehlgeschlagen (HTTP ' . $retry_code . '): ' . wp_remote_retrieve_body($retry), 'mitgliedsantrag');
                            return false;
                        }
                    } else {
                        dgptm_log_error('DUPLICATE_DATA Fallback WP_Error: ' . $retry->get_error_message(), 'mitgliedsantrag');
                        return false;
                    }
                } else {
                    $log_msg = 'Zoho API Error (HTTP ' . $http_code . '): ' . $zoho_code . ' - ' . $zoho_msg;
                    if ($zoho_details) {
                        $log_msg .= ' | Details: ' . wp_json_encode($zoho_details);
                    }
                    dgptm_log_error($log_msg, 'mitgliedsantrag');
                    dgptm_log_error('Zoho full response: ' . substr($raw_body, 0, 2000), 'mitgliedsantrag');
                    return false;
                }
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
                'https://www.zohoapis.eu/crm/v8/files',
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
                'https://www.zohoapis.eu/crm/v8/Contacts/' . $contact_id,
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

        /**
         * Zoho CRM Blueprint-Transition ausloesen
         * Startet den Mitgliedsantrag-Workflow im CRM nach Contact Create/Update
         */
        private function trigger_blueprint($contact_id, $token) {
            $options = dgptm_ma_get_options();
            $transition_id = $options['blueprint_transition_id'] ?? '548256000001478468';

            if (empty($transition_id)) {
                $this->log('Blueprint skipped: Keine Transition-ID konfiguriert');
                return ['success' => true, 'message' => 'No blueprint configured', 'skipped' => true];
            }

            $this->log('Triggering blueprint transition ' . $transition_id . ' for contact ' . $contact_id);

            // Erst verfuegbare Transitions + Pflichtfelder abfragen
            $bp_info = wp_remote_get(
                'https://www.zohoapis.eu/crm/v8/Contacts/' . $contact_id . '/actions/blueprint',
                [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $token,
                        'Content-Type'  => 'application/json'
                    ],
                    'timeout' => 30
                ]
            );

            if (!is_wp_error($bp_info)) {
                $bp_body = wp_remote_retrieve_body($bp_info);
                dgptm_log_info('Blueprint GET (verfuegbare Transitions): ' . substr($bp_body, 0, 2000), 'mitgliedsantrag');
            }

            // Blueprint-Pflichtfelder aus Kontaktdaten befuellen
            $bp_data = [];

            // Kontakt abrufen für Pflichtfelder
            $contact_resp = wp_remote_get(
                'https://www.zohoapis.eu/crm/v8/Contacts/' . $contact_id,
                ['headers' => ['Authorization' => 'Zoho-oauthtoken ' . $token], 'timeout' => 15]
            );

            if (!is_wp_error($contact_resp)) {
                $contact_body = json_decode(wp_remote_retrieve_body($contact_resp), true);
                $contact_data = $contact_body['data'][0] ?? [];
                $bp_data['Last_Name'] = $contact_data['Last_Name'] ?? '';
                $bp_data['Salutation'] = $contact_data['Salutation'] ?? '';
                if (empty($bp_data['Salutation'])) {
                    $bp_data['Salutation'] = '-None-';
                }
            }

            $payload = [
                'blueprint' => [
                    [
                        'transition_id' => $transition_id,
                        'data' => $bp_data
                    ]
                ]
            ];

            $response = wp_remote_request(
                'https://www.zohoapis.eu/crm/v8/Contacts/' . $contact_id . '/actions/blueprint',
                [
                    'method'  => 'PUT',
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $token,
                        'Content-Type'  => 'application/json'
                    ],
                    'body'    => wp_json_encode($payload),
                    'timeout' => 30
                ]
            );

            if (is_wp_error($response)) {
                $this->log('ERROR: Blueprint trigger failed: ' . $response->get_error_message());
                return ['success' => false, 'message' => $response->get_error_message()];
            }

            $http_code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);

            $success = $http_code >= 200 && $http_code < 300;

            if ($success) {
                dgptm_log_info('Blueprint triggered for contact ' . $contact_id . ' (transition ' . $transition_id . ')', 'mitgliedsantrag');
            } else {
                dgptm_log_warning('Blueprint failed (HTTP ' . $http_code . ') for contact ' . $contact_id . ': ' . substr($body, 0, 1000), 'mitgliedsantrag');
            }

            return [
                'success'       => $success,
                'http_code'     => $http_code,
                'message'       => $success ? 'Blueprint triggered' : 'Blueprint failed (HTTP ' . $http_code . ')',
                'response_body' => $body
            ];
        }

        /**
         * Benachrichtigungs-E-Mail an Geschäftsstelle senden
         */
        private function send_notification_email($data, $contact_id) {
            $to = 'geschaeftsstelle@dgptm.de';
            $subject = 'Neuer Mitgliedsantrag: ' . ($data['vorname'] ?? '') . ' ' . ($data['nachname'] ?? '');

            $mitgliedsart_map = [
                'ordentliches'       => 'Ordentliches Mitglied',
                'außerordentliches'  => 'Außerordentliches Mitglied',
                'förderndes'         => 'Förderndes Mitglied'
            ];
            $mitgliedsart = $mitgliedsart_map[$data['mitgliedsart'] ?? ''] ?? ($data['mitgliedsart'] ?? '-');

            $name = trim(($data['akad_titel'] ?? '') . ' ' . ($data['vorname'] ?? '') . ' ' . ($data['nachname'] ?? ''));
            $adresse = trim(($data['strasse'] ?? '') . ', ' . ($data['plz'] ?? '') . ' ' . ($data['stadt'] ?? ''));
            if ($adresse === ',') $adresse = '-';
            $crm_link = 'https://crm.zoho.eu/crm/dgptm/tab/Contacts/' . $contact_id;

            $rows = '';
            $rows .= $this->mail_row('Name', esc_html($name));
            $rows .= $this->mail_row('E-Mail', esc_html($data['email1'] ?? ''));
            $rows .= $this->mail_row('Mitgliedsart', esc_html($mitgliedsart));
            $rows .= $this->mail_row('Adresse', esc_html($adresse));
            if (!empty($data['arbeitgeber'])) {
                $rows .= $this->mail_row('Arbeitgeber', esc_html($data['arbeitgeber']));
            }
            if (!empty($data['buerge1_name'])) {
                $rows .= $this->mail_row('Bürge 1', esc_html($data['buerge1_name']) . ' (' . esc_html($data['buerge1_email'] ?? '') . ')');
            }
            if (!empty($data['buerge2_name'])) {
                $rows .= $this->mail_row('Bürge 2', esc_html($data['buerge2_name']) . ' (' . esc_html($data['buerge2_email'] ?? '') . ')');
            }
            $rows .= $this->mail_row('Zoho CRM', '<a href="' . esc_url($crm_link) . '" style="color:#2393BB">' . esc_html($contact_id) . '</a>');

            $body = '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta content="width=device-width" name="viewport"></head><body>'
                . '<div style="width:700px;margin:0 auto">'
                // Header
                . '<table width="700" border="0" cellpadding="0" cellspacing="0" align="center"><tr>'
                . '<td style="padding:20px 0 20px 20px" align="left"><a href="https://dgptm.de/"><img src="https://perfusiologie.de/nichtwordpress/logo.png" alt="DGPTM" style="display:block;border:0;height:50px"></a></td>'
                . '<td style="padding:20px 20px 20px 0" align="right">'
                . '<a href="https://perfusiologie.de" style="font-size:12px;font-weight:500;text-transform:uppercase;color:#525252;text-decoration:none;padding:0 15px">Website</a>'
                . '<a href="https://perfusiologie.de/veranstaltungen" style="font-size:12px;font-weight:500;text-transform:uppercase;color:#525252;text-decoration:none;padding:0 15px">Veranstaltungen</a>'
                . '</td></tr></table>'
                // Banner
                . '<table height="150" width="700" border="0" cellpadding="0" cellspacing="0" align="center" style="background-color:#2393BB" background="https://perfusiologie.de/nichtwordpress/bg-mail-header.jpg"><tr>'
                . '<td align="center" valign="middle"><h1 style="margin:0;font-size:22px;font-weight:700;letter-spacing:4px;text-transform:uppercase;color:#fff;font-family:Calibri,Candara,Segoe UI,Arial,sans-serif">Neuer Mitgliedsantrag</h1></td>'
                . '</tr></table>'
                // Content
                . '<br><table width="600" border="0" cellpadding="0" cellspacing="0" align="center" style="font-size:14px;font-family:Calibri,Candara,Segoe UI,Arial,sans-serif"><tr><td>'
                . '<table border="0" cellpadding="0" cellspacing="0" width="100%"><tr><td height="25"></td></tr>'
                . '<tr><td style="font-size:16px;font-weight:600;color:#3c3c3c">Ein neuer Mitgliedsantrag wurde über das Online-Formular eingereicht.</td></tr>'
                . '<tr><td height="20"></td></tr></table>'
                // Data Table
                . '<table width="100%" border="0" cellpadding="8" cellspacing="0" style="font-size:14px;border-collapse:collapse">'
                . $rows
                . '</table>'
                . '<br><p style="font-size:14px;color:#3c3c3c;margin:15px 0">Der Blueprint-Workflow wurde im CRM gestartet.</p>'
                . '</td></tr></table>'
                // Footer
                . '<table width="600" align="center" border="0" cellpadding="0" cellspacing="0" style="margin-top:25px;text-align:center"><tr>'
                . '<td style="font-size:11pt;color:#242424;text-align:left;font-family:Calibri,Candara,Segoe UI,Arial,sans-serif">'
                . '<p style="margin:0">Beste Grüße,</p>'
                . '<p style="margin:0">die Deutsche Gesellschaft für Perfusiologie und Technische Medizin.</p>'
                . '</td></tr><tr>'
                . '<td style="border-top:1px solid #2492BA;padding-top:15px;font-size:12px;color:#000">'
                . '<p style="margin:0"><a href="https://perfusiologie.de/impressum/" style="text-decoration:none;color:#000">Impressum</a> | '
                . '<a href="https://perfusiologie.de/datenschutz/" style="text-decoration:none;color:#000">Datenschutz</a> | '
                . '<a href="tel:004934123805268" style="text-decoration:none;color:#000">Telefon: +49 341 2380 5268</a></p>'
                . '<p style="margin:12px 0 0 0">&copy; Deutsche Gesellschaft für Perfusiologie und Technische Medizin. Alle Rechte vorbehalten.</p>'
                . '</td></tr></table>'
                . '</div></body></html>';

            $headers = ['Content-Type: text/html; charset=UTF-8'];

            $sent = wp_mail($to, $subject, $body, $headers);

            if ($sent) {
                dgptm_log_info('Infomail gesendet an ' . $to . ' für Contact ' . $contact_id, 'mitgliedsantrag');
            } else {
                dgptm_log_warning('Infomail an ' . $to . ' fehlgeschlagen für Contact ' . $contact_id, 'mitgliedsantrag');
            }
        }

        private function mail_row($label, $value) {
            return '<tr><td style="padding:6px 8px;font-weight:600;color:#3c3c3c;border-bottom:1px solid #eee;width:140px;vertical-align:top">' . $label . '</td>'
                 . '<td style="padding:6px 8px;color:#3c3c3c;border-bottom:1px solid #eee">' . $value . '</td></tr>';
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
            // CSS sofort enqueuen, damit auch Fehler-/Info-Karten das Styling bekommen
            wp_enqueue_style(
                'dgptm-vorstandsgenehmigung',
                $this->plugin_url . 'assets/css/vorstandsgenehmigung.css',
                [],
                $this->version
            );

            $antragsteller_token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';

            if ($antragsteller_token === '') {
                return $this->render_error_message(
                    'Fehlende Parameter',
                    'Bitte verwende den vollständigen Link aus der E-Mail. Es fehlt der Identifikationstoken.'
                );
            }

            $oauth = $this->get_access_token();
            if (!$oauth) {
                return $this->render_error_message(
                    'Verbindungsfehler',
                    'Die Verbindung zum CRM-System konnte nicht hergestellt werden. Bitte versuche es später erneut.'
                );
            }

            $antragsteller = $this->get_contact_by_token($antragsteller_token, $oauth);
            if (!$antragsteller) {
                return $this->render_info_message(
                    'Abstimmung bereits abgeschlossen',
                    'Vielen Dank für dein Engagement! Die Abstimmungsphase zu diesem Mitgliedsantrag ist inzwischen beendet, eine Stimmabgabe über diesen Link ist daher nicht mehr möglich. Bei Rückfragen wende dich gerne an die Geschäftsstelle.'
                );
            }

            // Debug: Status-Check kann von Admins via ?skip_status_check=1 deaktiviert werden.
            // Nicht-Admins ignorieren den Parameter.
            $skip_status_check = current_user_can('manage_options') && !empty($_GET['skip_status_check']);

            // Nur Anträge mit Contact_Status "In Prüfung beim Vorstand" sind offen
            if (!$skip_status_check && ($antragsteller['Contact_Status'] ?? '') !== 'In Prüfung beim Vorstand') {
                return $this->render_info_message(
                    'Abstimmung bereits abgeschlossen',
                    'Vielen Dank für dein Engagement! Dieser Mitgliedsantrag befindet sich nicht mehr in der Vorstandsabstimmung. Bei Rückfragen wende dich gerne an die Geschäftsstelle.'
                );
            }

            $vorstaende_all    = $this->get_active_vorstaende($oauth);
            $abgestimmte_ids   = $this->get_bereits_abgestimmte_ids($antragsteller);
            $vorstaende_aktiv  = array_values(array_filter($vorstaende_all, function ($v) use ($abgestimmte_ids) {
                return !in_array((string) $v['id'], $abgestimmte_ids, true);
            }));

            // Vollständige Abstimmungsliste für die Anzeige der Kommentare
            $abstimmungen_alle = [];
            $raw_votes         = $antragsteller['Vorstand_Abstimmungen'] ?? '';
            if (!empty($raw_votes)) {
                $decoded = json_decode($raw_votes, true);
                if (is_array($decoded)) {
                    $abstimmungen_alle = $decoded;
                }
            }

            $summary = [
                'genehmigungen' => (int) ($antragsteller['Membership_Approved']     ?? 0),
                'ablehnungen'   => (int) ($antragsteller['Membership_Not_Approved'] ?? 0),
                'gesamt'        => count($abgestimmte_ids)
            ];

            wp_enqueue_script(
                'dgptm-vorstandsgenehmigung',
                $this->plugin_url . 'assets/js/vorstandsgenehmigung.js',
                ['jquery'],
                $this->version,
                true
            );

            wp_localize_script('dgptm-vorstandsgenehmigung', 'dgptmVorstand', [
                'ajaxUrl'            => admin_url('admin-ajax.php'),
                'nonce'              => wp_create_nonce('dgptm_vorstand_entscheidung'),
                'antragstellerToken' => $antragsteller_token,
                'skipStatusCheck'    => $skip_status_check ? 1 : 0,
                'strings'            => [
                    'confirm_approve'  => 'Möchtest du diesen Mitgliedsantrag wirklich GENEHMIGEN?',
                    'confirm_reject'   => 'Möchtest du diesen Mitgliedsantrag wirklich ABLEHNEN?',
                    'processing'       => 'Wird verarbeitet...',
                    'select_vorstand'  => 'Bitte wähle deinen Namen aus der Liste aus.',
                    'success_approved' => 'Der Antrag wurde erfolgreich genehmigt.',
                    'success_rejected' => 'Der Antrag wurde abgelehnt.',
                    'error'            => 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.'
                ]
            ]);

            $nachweise = $this->get_direct_uploads_from_contact($antragsteller);

            // Bürgen-Anforderung nach § 4 Satzung:
            //   Quali-Nachweis (ECCP o. ä.)        → 0 Bürg:innen (§ 4.1.1.1)
            //   Nur Studien-Nachweis              → 1 Bürge (§ 4.1.1.3)
            //   Weder Quali- noch Studien-Nachweis → 2 Bürg:innen (§ 4.1.1.2)
            // Es zählen nur BESTÄTIGTE Bürg:innen (Guarantor_Status_X == true) — wer
            // nicht reagiert hat, erfüllt die Satzungsvorgabe nicht.
            $hat_quali = !empty($antragsteller['QualiNachweisDirekt']);
            $hat_studi = !empty($antragsteller['StudinachweisDirekt']);
            $benötigte_buergen = $hat_quali ? 0 : ($hat_studi ? 1 : 2);

            $b1_name = !empty($antragsteller['Guarantor_Name_1']);
            $b2_name = !empty($antragsteller['Guarantor_Name_2']);
            $b1_ok   = !empty($antragsteller['Guarantor_Status_1']);
            $b2_ok   = !empty($antragsteller['Guarantor_Status_2']);
            $vorhandene_buergen  = ($b1_name ? 1 : 0) + ($b2_name ? 1 : 0);
            $bestätigte_buergen = ($b1_ok ? 1 : 0)   + ($b2_ok ? 1 : 0);
            $offene_buergen      = ($b1_name && !$b1_ok ? 1 : 0) + ($b2_name && !$b2_ok ? 1 : 0);
            $buergen_fehlen      = $bestätigte_buergen < $benötigte_buergen;

            // Adresse: Mailing_* bevorzugen, Other_* als Fallback
            $addr_street  = $antragsteller['Mailing_Street']  ?? $antragsteller['Other_Street']  ?? '';
            $addr_city    = $antragsteller['Mailing_City']    ?? $antragsteller['Other_City']    ?? '';
            $addr_zip     = $antragsteller['Mailing_Zip']     ?? $antragsteller['Other_Zip']     ?? '';
            $addr_state   = $antragsteller['Mailing_State']   ?? $antragsteller['Other_State']   ?? '';
            $addr_country = $antragsteller['Mailing_Country'] ?? $antragsteller['Other_Country'] ?? '';
            $addr_add     = $antragsteller['Mailing_Street_Additional'] ?? '';

            ob_start();
            ?>
            <div class="dgptm-vorstandsgenehmigung-container">
                <?php if ($skip_status_check): ?>
                    <div class="dgptm-vg-testmode">🧪 Testmodus aktiv — Contact-Status-Prüfung (<?php echo esc_html($antragsteller['Contact_Status'] ?? '-'); ?>) übergangen.</div>
                <?php endif; ?>
                <div class="dgptm-vg-header">
                    <h2>Mitgliedsantrag zur Genehmigung</h2>
                    <?php if ($summary['gesamt'] > 0): ?>
                    <p class="dgptm-vg-info">
                        Bisherige Abstimmungen:
                        <strong><?php echo $summary['genehmigungen']; ?></strong>&nbsp;Genehmigung(en),
                        <strong><?php echo $summary['ablehnungen']; ?></strong>&nbsp;Ablehnung(en)
                    </p>
                    <?php endif; ?>
                </div>

                <div class="dgptm-vg-antragsteller">
                    <h3>Antragsteller:in</h3>

                    <div class="dgptm-vg-section">
                        <h4>Persoenliche Daten</h4>
                        <table class="dgptm-vg-table">
                            <tr><th>Anrede:</th><td><?php echo esc_html($antragsteller['Salutation'] ?: ($antragsteller['greeting'] ?? '-')); ?></td></tr>
                            <tr><th>Akadem. Titel:</th><td><?php echo esc_html($antragsteller['Academic_Title'] ?? '-'); ?></td></tr>
                            <tr><th>Vorname:</th><td><?php echo esc_html($antragsteller['First_Name'] ?? '-'); ?></td></tr>
                            <tr><th>Nachname:</th><td><?php echo esc_html($antragsteller['Last_Name'] ?? '-'); ?></td></tr>
                            <?php if (!empty($antragsteller['Title_After_The_Name'])): ?>
                            <tr><th>Titel nachgestellt:</th><td><?php echo esc_html($antragsteller['Title_After_The_Name']); ?></td></tr>
                            <?php endif; ?>
                            <tr><th>Geburtsdatum:</th><td><?php echo esc_html($this->format_date($antragsteller['Date_of_Birth'] ?? '')); ?></td></tr>
                            <?php if (!empty($antragsteller['Geburtsort'])): ?>
                            <tr><th>Geburtsort:</th><td><?php echo esc_html($antragsteller['Geburtsort']); ?></td></tr>
                            <?php endif; ?>
                        </table>
                    </div>

                    <div class="dgptm-vg-section">
                        <h4>Kontaktdaten</h4>
                        <table class="dgptm-vg-table">
                            <tr><th>E-Mail 1:</th><td><?php echo esc_html($antragsteller['Email'] ?? '-'); ?></td></tr>
                            <?php if (!empty($antragsteller['Secondary_Email'])): ?>
                            <tr><th>E-Mail 2:</th><td><?php echo esc_html($antragsteller['Secondary_Email']); ?></td></tr>
                            <?php endif; ?>
                            <?php if (!empty($antragsteller['Third_Email'])): ?>
                            <tr><th>E-Mail 3:</th><td><?php echo esc_html($antragsteller['Third_Email']); ?></td></tr>
                            <?php endif; ?>
                            <tr><th>Telefon:</th><td><?php echo esc_html($antragsteller['Phone'] ?? '-'); ?></td></tr>
                            <?php if (!empty($antragsteller['Mobile'])): ?>
                            <tr><th>Mobil:</th><td><?php echo esc_html($antragsteller['Mobile']); ?></td></tr>
                            <?php endif; ?>
                            <?php if (!empty($antragsteller['Work_Phone'])): ?>
                            <tr><th>Telefon (Arbeit):</th><td><?php echo esc_html($antragsteller['Work_Phone']); ?></td></tr>
                            <?php endif; ?>
                        </table>
                    </div>

                    <div class="dgptm-vg-section">
                        <h4>Adresse</h4>
                        <table class="dgptm-vg-table">
                            <tr><th>Strasse:</th><td><?php echo esc_html($addr_street ?: '-'); ?></td></tr>
                            <?php if (!empty($addr_add)): ?>
                            <tr><th>Adresszusatz:</th><td><?php echo esc_html($addr_add); ?></td></tr>
                            <?php endif; ?>
                            <tr><th>PLZ / Ort:</th><td><?php echo esc_html(trim($addr_zip . ' ' . $addr_city)) ?: '-'; ?></td></tr>
                            <?php if (!empty($addr_state)): ?>
                            <tr><th>Bundesland:</th><td><?php echo esc_html($addr_state); ?></td></tr>
                            <?php endif; ?>
                            <tr><th>Land:</th><td><?php echo esc_html($addr_country ?: '-'); ?></td></tr>
                        </table>
                    </div>

                    <div class="dgptm-vg-section">
                        <h4>Mitgliedschaft</h4>
                        <table class="dgptm-vg-table">
                            <tr><th>Beantragte Mitgliedsart:</th><td><strong><?php echo esc_html($antragsteller['Membership_Type'] ?? '-'); ?></strong></td></tr>
                            <tr><th>Arbeitgeber:</th><td><?php echo esc_html($antragsteller['employer_name'] ?? '-'); ?></td></tr>
                            <tr><th>Beruf/Studienrichtung:</th><td><?php echo esc_html($antragsteller['profession'] ?? '-'); ?></td></tr>
                            <?php if (!empty($antragsteller['Student_Status'])): ?>
                            <tr><th>Studentenstatus:</th><td>Ja</td></tr>
                            <?php endif; ?>
                        </table>
                    </div>

                    <?php if ($hat_quali && $benötigte_buergen === 0): ?>
                    <div class="dgptm-vg-section dgptm-vg-note">
                        <h4>Keine Bürg:innen erforderlich</h4>
                        <p>
                            Für diese Antragstellerin / diesen Antragsteller liegt ein Qualifikationsnachweis vor —
                            gemäß § 4.1.1.1 der Satzung sind daher <strong>keine Bürg:innen</strong> erforderlich.
                            Bitte prüfe den Nachweis im Abschnitt <em>„Hochgeladene Nachweise"</em>.
                        </p>
                        <details class="dgptm-vg-satzung">
                            <summary>§&nbsp;4.1.1.1 der Satzung einblenden</summary>
                            <div class="dgptm-vg-satzung-text">
                                <p><strong>§&nbsp;4.1.1.1</strong> — Ordentliches Mitglied kann jede natürliche Person werden, die über eine anerkannte Qualifikation als „Kardiotechniker", „Perfusionist", „Perfusiologe" oder „Technischer Mediziner" verfügt oder sich in einer entsprechenden Ausbildung befindet. Anerkannte Qualifikationen sind insbesondere das <strong>ECCP-Zertifikat</strong> (European Certificate in Cardiovascular Perfusion) und/oder ein Abschluss als „Kardiotechniker" nach dem Berliner „Gesetz über Medizinalfachberufe".</p>
                            </div>
                        </details>
                    </div>
                    <?php endif; ?>

                    <?php if ($buergen_fehlen): ?>
                    <div class="dgptm-vg-section dgptm-vg-warning">
                        <h4>Bürg:innen-Anforderung nicht erfüllt</h4>
                        <p>
                            Laut Satzung (§&nbsp;4) werden für diese Mitgliedschaftsart
                            <strong><?php echo (int) $benötigte_buergen; ?>&nbsp;bestätigte Bürg:in<?php echo $benötigte_buergen === 1 ? '' : 'nen'; ?></strong>
                            benötigt, bestätigt <?php echo $bestätigte_buergen === 1 ? 'hat' : 'haben'; ?> bisher
                            <strong><?php echo (int) $bestätigte_buergen; ?></strong>
                            <?php if ($offene_buergen > 0): ?>
                                — <?php echo (int) $offene_buergen; ?>&nbsp;Bürg:in<?php echo $offene_buergen === 1 ? '' : 'nen'; ?> <?php echo $offene_buergen === 1 ? 'hat' : 'haben'; ?> noch nicht reagiert.
                            <?php else: ?>
                                — es <?php echo ($benötigte_buergen - $bestätigte_buergen) === 1 ? 'fehlt' : 'fehlen'; ?> <?php echo (int) ($benötigte_buergen - $bestätigte_buergen); ?>&nbsp;Eintrag<?php echo ($benötigte_buergen - $bestätigte_buergen) === 1 ? '' : 'e'; ?>.
                            <?php endif; ?>
                            Bitte prüfe vor deiner Entscheidung, ob eine Aufnahme dennoch gerechtfertigt ist.
                        </p>
                        <details class="dgptm-vg-satzung">
                            <summary>§&nbsp;4 der Satzung einblenden</summary>
                            <div class="dgptm-vg-satzung-text">
                                <p><strong>§&nbsp;4.1.1.1</strong> — Ordentliches Mitglied kann jede natürliche Person werden, die über eine anerkannte Qualifikation als „Kardiotechniker", „Perfusionist", „Perfusiologe" oder „Technischer Mediziner" verfügt oder sich in einer entsprechenden Ausbildung befindet. Anerkannte Qualifikationen sind insbesondere das ECCP-Zertifikat (European Certificate in Cardiovascular Perfusion) und/oder ein Abschluss als „Kardiotechniker" nach dem Berliner „Gesetz über Medizinalfachberufe".</p>
                                <p><strong>§&nbsp;4.1.1.2</strong> — Personen, die in dem Beruf arbeiten, ohne über eine der genannten Qualifikationen zu verfügen, können ordentliches Mitglied werden, wenn sie nachweislich eine Tätigkeit ausüben oder ausgeübt haben, die üblicherweise von Perfusionisten (Kardiotechnikern) ausgeübt wird. <strong>Als Nachweis ist das schriftliche Zeugnis von zwei ordentlichen Mitgliedern als Bürgen ausreichend und erforderlich</strong>, die die entsprechende Tätigkeit der Person bestätigen.</p>
                                <p><strong>§&nbsp;4.1.1.3</strong> — Ordentliche Mitglieder, die sich nachweislich noch in der Ausbildung befinden, können auf Antrag als Studentisches Mitglied geführt werden. Der Nachweis ist in geeigneter Form zu führen. <strong>Zusätzlich ist die Bestätigung des Studienschwerpunkts im Bereich Perfusion (Kardiotechnik), Medizintechnik, Perfusiologie, und Technische Medizin durch ein ordentliches Mitglied als Bürgen erforderlich.</strong> Studentische Mitglieder zahlen einen vergünstigten Beitrag.</p>
                                <p><strong>§&nbsp;4.2.1</strong> — Der Aufnahmeantrag kann jederzeit schriftlich an den Vorstand gestellt werden. <strong>Die nach Abs.&nbsp;1 lit.&nbsp;a) erforderlichen Nachweise oder Bürgschaften sind beizufügen.</strong> Über den Antrag entscheidet der Vorstand mit einfacher Mehrheit. Die Entscheidungen des Vorstands sind endgültig.</p>
                            </div>
                        </details>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($antragsteller['Guarantor_Name_1']) || !empty($antragsteller['Guarantor_Name_2'])): ?>
                    <div class="dgptm-vg-section">
                        <h4>Bürg:innen</h4>
                        <table class="dgptm-vg-table">
                            <?php if (!empty($antragsteller['Guarantor_Name_1'])): ?>
                            <tr>
                                <th>Bürge 1:</th>
                                <td>
                                    <?php echo esc_html($this->format_personen_name($antragsteller['Guarantor_Name_1'])); ?>
                                    <?php if (!empty($antragsteller['Guarantor_Mail_1'])): ?>
                                        (<?php echo esc_html($antragsteller['Guarantor_Mail_1']); ?>)
                                    <?php endif; ?>
                                    <?php if (!empty($antragsteller['Guarantor_Status_1'])): ?>
                                        <span class="dgptm-vg-status dgptm-vg-status-ok">✓ bestätigt</span>
                                    <?php else: ?>
                                        <span class="dgptm-vg-status dgptm-vg-status-pending">offen</span>
                                    <?php endif; ?>
                                    <?php if (!empty($antragsteller['guarantor_1_comment']) && $antragsteller['guarantor_1_comment'] !== 'false'): ?>
                                        <div class="dgptm-vg-buerge-kommentar"><em><?php echo esc_html($antragsteller['guarantor_1_comment']); ?></em></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                            <?php if (!empty($antragsteller['Guarantor_Name_2'])): ?>
                            <tr>
                                <th>Bürge 2:</th>
                                <td>
                                    <?php echo esc_html($this->format_personen_name($antragsteller['Guarantor_Name_2'])); ?>
                                    <?php if (!empty($antragsteller['Guarantor_Mail_2'])): ?>
                                        (<?php echo esc_html($antragsteller['Guarantor_Mail_2']); ?>)
                                    <?php endif; ?>
                                    <?php if (!empty($antragsteller['Guarantor_Status_2'])): ?>
                                        <span class="dgptm-vg-status dgptm-vg-status-ok">✓ bestätigt</span>
                                    <?php else: ?>
                                        <span class="dgptm-vg-status dgptm-vg-status-pending">offen</span>
                                    <?php endif; ?>
                                    <?php if (!empty($antragsteller['guarantor_2_comment']) && $antragsteller['guarantor_2_comment'] !== 'false'): ?>
                                        <div class="dgptm-vg-buerge-kommentar"><em><?php echo esc_html($antragsteller['guarantor_2_comment']); ?></em></div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </table>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($nachweise)): ?>
                    <div class="dgptm-vg-section">
                        <h4>Hochgeladene Nachweise</h4>
                        <div class="dgptm-vg-attachments">
                            <?php foreach ($nachweise as $n): ?>
                                <div class="dgptm-vg-attachment">
                                    <span class="dgptm-vg-attachment-icon">📄</span>
                                    <a href="<?php echo esc_url($this->build_download_proxy_url($antragsteller_token, $n['file_id'])); ?>" target="_blank" rel="noopener noreferrer">
                                        <?php echo esc_html($n['file_name']); ?>
                                    </a>
                                    <span class="dgptm-vg-attachment-meta"><?php echo esc_html($n['source'] . (!empty($n['file_size']) ? ' · ' . $n['file_size'] : '')); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($antragsteller['Bemerkung'])): ?>
                    <div class="dgptm-vg-section">
                        <h4>Bemerkung der Geschäftsstelle</h4>
                        <p><?php echo wp_kses_post(nl2br(esc_html($antragsteller['Bemerkung']))); ?></p>
                    </div>
                    <?php endif; ?>

                    <div class="dgptm-vg-section">
                        <h4>Akzeptierte Erklärungen</h4>
                        <table class="dgptm-vg-table">
                            <tr><th>Satzung akzeptiert:</th><td><?php echo !empty($antragsteller['SatzungAkzeptiert']) ? '✓ Ja' : '✗ Nein'; ?></td></tr>
                            <tr><th>Beitragsordnung akzeptiert:</th><td><?php echo !empty($antragsteller['BeitragAkzeptiert']) ? '✓ Ja' : '✗ Nein'; ?></td></tr>
                            <tr><th>Datenschutz akzeptiert:</th><td><?php echo !empty($antragsteller['Datenschutzakzeptiert']) ? '✓ Ja' : '✗ Nein'; ?></td></tr>
                        </table>
                    </div>

                    <?php if (!empty($abstimmungen_alle)): ?>
                    <div class="dgptm-vg-section">
                        <h4>Bisherige Vorstandsabstimmungen</h4>
                        <div class="dgptm-vg-votes">
                            <?php foreach ($abstimmungen_alle as $v):
                                $is_approve = ($v['entscheidung'] ?? '') === 'approve';
                                $cls        = $is_approve ? 'dgptm-vg-vote-approve' : 'dgptm-vg-vote-reject';
                                $icon       = $is_approve ? '✓' : '✗';
                                $verdict    = $is_approve ? 'Genehmigt' : 'Abgelehnt';
                                $datum_raw  = $v['datum'] ?? '';
                                $datum_fmt  = $datum_raw ? date_i18n('d.m.Y H:i', strtotime($datum_raw)) : '';
                                $name       = $v['vorstand_name'] ?? 'Unbekannt';
                                $bem        = trim((string) ($v['bemerkung'] ?? ''));
                            ?>
                                <div class="dgptm-vg-vote <?php echo esc_attr($cls); ?>">
                                    <div class="dgptm-vg-vote-head">
                                        <span class="dgptm-vg-vote-icon"><?php echo $icon; ?></span>
                                        <strong><?php echo esc_html($name); ?></strong>
                                        <span class="dgptm-vg-vote-verdict"><?php echo $verdict; ?></span>
                                        <?php if ($datum_fmt): ?>
                                            <span class="dgptm-vg-vote-date"><?php echo esc_html($datum_fmt); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($bem !== ''): ?>
                                        <div class="dgptm-vg-vote-bemerkung"><?php echo esc_html($bem); ?></div>
                                    <?php else: ?>
                                        <div class="dgptm-vg-vote-bemerkung dgptm-vg-vote-empty"><em>(ohne Kommentar)</em></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (empty($vorstaende_aktiv)): ?>
                    <div class="dgptm-vg-empty">
                        <h3>Abstimmung abgeschlossen</h3>
                        <p>Für diesen Antrag haben bereits alle Vorstandsmitglieder abgestimmt (<?php echo $summary['genehmigungen']; ?>&nbsp;Genehmigung(en), <?php echo $summary['ablehnungen']; ?>&nbsp;Ablehnung(en)).</p>
                    </div>
                <?php else: ?>
                    <div class="dgptm-vg-entscheidung">
                        <h3>Deine Entscheidung</h3>

                        <div class="dgptm-vg-select">
                            <label for="dgptm-vg-vorstand">Vorstandsmitglied (dein Name):</label>
                            <select id="dgptm-vg-vorstand" required>
                                <option value="">-- bitte auswählen --</option>
                                <?php foreach ($vorstaende_aktiv as $v): ?>
                                    <option value="<?php echo esc_attr($v['id']); ?>">
                                        <?php echo esc_html($this->format_vorstand_label($v)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="dgptm-vg-kommentar">
                            <label for="dgptm-vg-bemerkung">Bemerkung (optional):</label>
                            <textarea id="dgptm-vg-bemerkung" rows="3" placeholder="Optionale Bemerkung zur Entscheidung..."></textarea>
                        </div>

                        <div class="dgptm-vg-buttons">
                            <button type="button" class="dgptm-vg-btn dgptm-vg-btn-approve" data-action="approve">✓ Antrag genehmigen</button>
                            <button type="button" class="dgptm-vg-btn dgptm-vg-btn-reject" data-action="reject">✗ Antrag ablehnen</button>
                        </div>

                        <div class="dgptm-vg-result" style="display: none;"></div>
                    </div>
                <?php endif; ?>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Shortcode [buergenbestätigung] — Bürgschaftsbestätigung per Link.
         * URL: ?token=<antragsteller.token>&email=<buerge.email>
         * Der Server matcht die E-Mail gegen Guarantor_Mail_1 / _2 und ermittelt
         * daraus den Slot. Groß-/Kleinschreibung wird ignoriert.
         */
        public function render_buergenbestaetigung($atts) {
            wp_enqueue_style(
                'dgptm-vorstandsgenehmigung',
                $this->plugin_url . 'assets/css/vorstandsgenehmigung.css',
                [],
                $this->version
            );

            $antragsteller_token = isset($_GET['token']) ? sanitize_text_field($_GET['token']) : '';
            $buerge_email_raw    = isset($_GET['email']) ? sanitize_email(wp_unslash($_GET['email'])) : '';

            if ($antragsteller_token === '' || $buerge_email_raw === '') {
                return $this->render_error_message(
                    'Fehlende Parameter',
                    'Bitte verwende den vollständigen Link aus der E-Mail. Es fehlen Identifikationstoken oder Bürg:innen-E-Mail.'
                );
            }

            $oauth = $this->get_access_token();
            if (!$oauth) {
                return $this->render_error_message(
                    'Verbindungsfehler',
                    'Die Verbindung zum CRM-System konnte nicht hergestellt werden. Bitte versuche es später erneut.'
                );
            }

            $antragsteller = $this->get_contact_by_token($antragsteller_token, $oauth);
            if (!$antragsteller) {
                return $this->render_info_message(
                    'Anfrage nicht mehr aktiv',
                    'Vielen Dank! Zu diesem Link ist aktuell kein offener Aufnahmeantrag zugeordnet. Die Bürgschaftsanfrage ist vermutlich bereits abgeschlossen. Bei Rückfragen wende dich gerne an die Geschäftsstelle.'
                );
            }

            $slot = $this->match_buerge_slot_by_email($antragsteller, $buerge_email_raw);
            if ($slot === 0) {
                return $this->render_error_message(
                    'Bürg:innen-Eintrag nicht gefunden',
                    'Unter diesem Antrag ist die angegebene E-Mail-Adresse nicht als Bürg:in hinterlegt. Bitte prüfe, ob du den richtigen Link verwendest.'
                );
            }

            $buerge_name   = $this->format_personen_name($antragsteller['Guarantor_Name_' . $slot] ?? '');
            $buerge_mail   = $antragsteller['Guarantor_Mail_' . $slot]   ?? '';
            $buerge_status = !empty($antragsteller['Guarantor_Status_' . $slot]);

            $antragsteller_name = $antragsteller['Full_Name']
                ?? trim(($antragsteller['First_Name'] ?? '') . ' ' . ($antragsteller['Last_Name'] ?? ''));

            // Falls bereits bestätigt → freundliche Dankekarte, keine Buttons
            if ($buerge_status) {
                return $this->render_info_message(
                    'Bereits bestätigt',
                    sprintf('Vielen Dank! Deine Bürgschaft für den Aufnahmeantrag von %s wurde bereits erfasst. Eine erneute Bestätigung ist nicht erforderlich.', esc_html($antragsteller_name))
                );
            }

            wp_enqueue_script(
                'dgptm-buergenbestaetigung',
                $this->plugin_url . 'assets/js/buergenbestaetigung.js',
                ['jquery'],
                $this->version,
                true
            );
            wp_localize_script('dgptm-buergenbestaetigung', 'dgptmBuerge', [
                'ajaxUrl'            => admin_url('admin-ajax.php'),
                'nonce'              => wp_create_nonce('dgptm_buerge_entscheidung'),
                'antragstellerToken' => $antragsteller_token,
                'buergeEmail'        => $buerge_email_raw,
                'strings'            => [
                    'confirm_confirm' => 'Möchtest du die Bürgschaft wirklich bestätigen?',
                    'confirm_reject'  => 'Möchtest du die Bürgschaft wirklich ablehnen?',
                    'processing'      => 'Wird verarbeitet...',
                    'error'           => 'Ein Fehler ist aufgetreten. Bitte versuche es erneut.'
                ]
            ]);

            $hat_quali = !empty($antragsteller['QualiNachweisDirekt']);
            $hat_studi = !empty($antragsteller['StudinachweisDirekt']);
            $benötigt = $hat_quali ? 0 : ($hat_studi ? 1 : 2);

            ob_start();
            ?>
            <div class="dgptm-vorstandsgenehmigung-container">
                <div class="dgptm-vg-header">
                    <h2>Bürgschaftsbestätigung</h2>
                    <p class="dgptm-vg-info">
                        Liebe(r) <strong><?php echo esc_html($buerge_name ?: $buerge_mail); ?></strong>,
                        Sie wurden von <strong><?php echo esc_html($antragsteller_name); ?></strong> als Bürge benannt.
                        Bitte bestätigen Sie, dass <?php echo esc_html($antragsteller_name); ?>
                        die Anforderungen für die Aufnahme als
                        <?php echo esc_html($antragsteller['Membership_Type'] ?? 'Ordentliches Mitglied'); ?>
                        in die DGPTM erfüllt.
                    </p>
                </div>

                <div class="dgptm-vg-antragsteller">
                    <div class="dgptm-vg-section">
                        <h4>Antrag von</h4>
                        <table class="dgptm-vg-table">
                            <tr><th>Name:</th><td><strong><?php echo esc_html($antragsteller_name ?: '-'); ?></strong></td></tr>
                            <tr><th>E-Mail:</th><td><?php echo esc_html($antragsteller['Email'] ?? '-'); ?></td></tr>
                            <tr><th>Beantragte Mitgliedsart:</th><td><?php echo esc_html($antragsteller['Membership_Type'] ?? '-'); ?></td></tr>
                            <?php if (!empty($antragsteller['employer_name'])): ?>
                            <tr><th>Arbeitgeber:</th><td><?php echo esc_html($antragsteller['employer_name']); ?></td></tr>
                            <?php endif; ?>
                            <?php if (!empty($antragsteller['profession'])): ?>
                            <tr><th>Beruf/Studienrichtung:</th><td><?php echo esc_html($antragsteller['profession']); ?></td></tr>
                            <?php endif; ?>
                        </table>
                    </div>

                    <div class="dgptm-vg-section">
                        <h4>Worum es bei einer Bürgschaft geht</h4>
                        <p>
                            Die Satzung sieht für diesen Antrag <strong><?php echo (int) $benötigt; ?>&nbsp;Bürg<?php echo $benötigt === 1 ? 'en' : 'innen'; ?></strong> vor.
                            <?php if ($hat_quali): ?>
                                Da ein Qualifikationsnachweis vorliegt, sind eigentlich keine Bürg:innen nötig — der Antrag wurde dennoch mit dir hinterlegt.
                            <?php elseif ($hat_studi): ?>
                                Als studentisches Mitglied benötigt der Antrag die Bestätigung eines ordentlichen Mitglieds, dass der Studienschwerpunkt im Bereich Perfusion/Technische Medizin liegt.
                            <?php else: ?>
                                Ohne formalen Qualifikationsnachweis braucht der Antrag das schriftliche Zeugnis von zwei ordentlichen Mitgliedern, die bestätigen, dass der/die Antragsteller:in eine entsprechende Tätigkeit ausübt oder ausgeübt hat.
                            <?php endif; ?>
                        </p>
                        <details class="dgptm-vg-satzung">
                            <summary>§&nbsp;4 der Satzung einblenden</summary>
                            <div class="dgptm-vg-satzung-text">
                                <p><strong>§&nbsp;4.1.1.2</strong> — Personen, die in dem Beruf arbeiten, ohne ueber eine der genannten Qualifikationen zu verfuegen, können ordentliches Mitglied werden, wenn sie nachweislich eine Tätigkeit ausueben oder ausgeübt haben, die ueblicherweise von Perfusionisten (Kardiotechnikern) ausgeübt wird. <strong>Als Nachweis ist das schriftliche Zeugnis von zwei ordentlichen Mitgliedern als Bürgen ausreichend und erforderlich</strong>, die die entsprechende Tätigkeit der Person bestätigen.</p>
                                <p><strong>§&nbsp;4.1.1.3</strong> — Ordentliche Mitglieder, die sich nachweislich noch in der Ausbildung befinden, können auf Antrag als Studentisches Mitglied geführt werden. <strong>Zusätzlich ist die Bestätigung des Studienschwerpunkts durch ein ordentliches Mitglied als Bürgen erforderlich.</strong></p>
                            </div>
                        </details>
                    </div>
                </div>

                <div class="dgptm-vg-entscheidung">
                    <h3>Deine Entscheidung</h3>
                    <div class="dgptm-vg-kommentar">
                        <label for="dgptm-buerge-bemerkung">Bemerkung (optional):</label>
                        <textarea id="dgptm-buerge-bemerkung" rows="3" placeholder="Optionale Bemerkung zu deiner Bestätigung oder Ablehnung..."></textarea>
                    </div>
                    <div class="dgptm-vg-buttons">
                        <button type="button" class="dgptm-vg-btn dgptm-vg-btn-approve" data-action="confirm">✓ Bürgschaft bestätigen</button>
                        <button type="button" class="dgptm-vg-btn dgptm-vg-btn-reject"  data-action="reject">✗ Bürgschaft ablehnen</button>
                    </div>
                    <div class="dgptm-vg-result" style="display: none;"></div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Formatiert historische "Nachname, Titel/Anrede"-Einträge um:
         * "Hellmut Haardt, Herr" → "Herr Hellmut Haardt".
         * Strings ohne Komma bleiben unverändert.
         */
        private function format_personen_name($name) {
            $name = trim((string) $name);
            if ($name === '' || strpos($name, ',') === false) {
                return $name;
            }
            $parts = array_map('trim', explode(',', $name, 2));
            if (count($parts) === 2 && $parts[1] !== '') {
                return $parts[1] . ' ' . $parts[0];
            }
            return $name;
        }

        /**
         * Ermittelt den Bürgen-Slot (1 oder 2) anhand der E-Mail-Adresse.
         * Liefert 0 wenn kein Match.
         */
        private function match_buerge_slot_by_email($antragsteller, $email) {
            $needle = strtolower(trim((string) $email));
            if ($needle === '') {
                return 0;
            }
            foreach ([1, 2] as $i) {
                $val = strtolower(trim((string) ($antragsteller['Guarantor_Mail_' . $i] ?? '')));
                if ($val !== '' && $val === $needle) {
                    return $i;
                }
            }
            return 0;
        }

        /**
         * AJAX: Bürgen-Bestätigung oder -Ablehnung.
         * Setzt Guarantor_Status_<slot> = true bei Bestätigung (Ablehnung belässt
         * den Wert auf false und dokumentiert die Entscheidung als Notiz).
         */
        public function ajax_buerge_entscheidung() {
            check_ajax_referer('dgptm_buerge_entscheidung', 'nonce');

            $antragsteller_token = sanitize_text_field($_POST['antragsteller_token'] ?? '');
            $buerge_email        = sanitize_email(wp_unslash($_POST['buerge_email'] ?? ''));
            $action              = sanitize_text_field($_POST['entscheidung'] ?? '');
            $bemerkung           = sanitize_textarea_field($_POST['bemerkung'] ?? '');

            if ($antragsteller_token === '' || $buerge_email === '' || !in_array($action, ['confirm', 'reject'], true)) {
                wp_send_json_error(['message' => 'Ungültige Parameter']);
                return;
            }

            $oauth = $this->get_access_token();
            if (!$oauth) {
                wp_send_json_error(['message' => 'CRM-Verbindung fehlgeschlagen']);
                return;
            }

            $antragsteller = $this->get_contact_by_token($antragsteller_token, $oauth);
            if (!$antragsteller) {
                wp_send_json_error(['message' => 'Antrag nicht mehr zugeordnet.']);
                return;
            }

            $slot = $this->match_buerge_slot_by_email($antragsteller, $buerge_email);
            if ($slot === 0) {
                wp_send_json_error(['message' => 'Die angegebene E-Mail ist nicht als Bürg:in hinterlegt.']);
                return;
            }

            $status_field = 'Guarantor_Status_' . $slot;
            if (!empty($antragsteller[$status_field]) && $action === 'confirm') {
                wp_send_json_error(['message' => 'Diese Bürgschaft wurde bereits bestätigt.']);
                return;
            }

            $buerge_name = $antragsteller['Guarantor_Name_' . $slot] ?? ('Bürge ' . $slot);
            $antragsteller_id = $antragsteller['id'];

            $update_data = [];
            if ($action === 'confirm') {
                $update_data[$status_field] = true;
            }

            if (!empty($update_data)) {
                $response = wp_remote_request(
                    'https://www.zohoapis.eu/crm/v8/Contacts/' . $antragsteller_id,
                    [
                        'method'  => 'PUT',
                        'headers' => [
                            'Authorization' => 'Zoho-oauthtoken ' . $oauth,
                            'Content-Type'  => 'application/json'
                        ],
                        'body'    => wp_json_encode(['data' => [$update_data]]),
                        'timeout' => 30
                    ]
                );
                if (is_wp_error($response)) {
                    $this->log('ERROR buerge_entscheidung update: ' . $response->get_error_message());
                    wp_send_json_error(['message' => 'Speichern fehlgeschlagen.']);
                    return;
                }
                $http_code = wp_remote_retrieve_response_code($response);
                if ($http_code < 200 || $http_code >= 300) {
                    $this->log('ERROR buerge_entscheidung HTTP ' . $http_code . ': ' . wp_remote_retrieve_body($response));
                    wp_send_json_error(['message' => 'CRM-Fehler: HTTP ' . $http_code]);
                    return;
                }
            }

            $this->speichere_buergschafts_note($antragsteller_id, $slot, $buerge_name, $action, $bemerkung, $oauth);

            $msg = $action === 'confirm'
                ? 'Vielen Dank! Deine Bestätigung wurde erfasst.'
                : 'Deine Rückmeldung (Ablehnung) wurde erfasst. Die Geschäftsstelle wird informiert.';
            wp_send_json_success(['message' => $msg, 'action' => $action]);
        }

        /**
         * Legt die Bürgschaftsentscheidung als Notiz am Antragsteller ab.
         */
        private function speichere_buergschafts_note($contact_id, $slot, $buerge_name, $action, $bemerkung, $oauth) {
            $aktion_text    = $action === 'confirm' ? 'Bestätigung' : 'Ablehnung';
            $bemerkung_trim = trim((string) $bemerkung);
            $title   = 'Bürgschaft Slot ' . (int) $slot . ': ' . $aktion_text . ' – ' . $buerge_name;
            $content = sprintf(
                "Bürge (Slot %d): %s\nEntscheidung: %s\nDatum: %s\n\nBemerkung:\n%s",
                (int) $slot,
                $buerge_name,
                $aktion_text,
                current_time('d.m.Y H:i'),
                $bemerkung_trim !== '' ? $bemerkung_trim : '(keine)'
            );

            $response = wp_remote_post(
                'https://www.zohoapis.eu/crm/v8/Contacts/' . $contact_id . '/Notes',
                [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $oauth,
                        'Content-Type'  => 'application/json'
                    ],
                    'body'    => wp_json_encode(['data' => [[
                        'Note_Title'   => $title,
                        'Note_Content' => $content
                    ]]]),
                    'timeout' => 30
                ]
            );

            if (is_wp_error($response)) {
                $this->log('WARN speichere_buergschafts_note: ' . $response->get_error_message());
                return;
            }
            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code < 200 || $http_code >= 300) {
                $this->log('WARN speichere_buergschafts_note HTTP ' . $http_code);
            }
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
         * Rendert eine neutrale Info-Meldung (kein Fehler, z. B. Abstimmungsphase beendet).
         * Nutzt den bestehenden .dgptm-vg-empty-Stil aus dem Leerfall-Block.
         */
        private function render_info_message($title, $message) {
            return sprintf(
                '<div class="dgptm-vorstandsgenehmigung-container"><div class="dgptm-vg-empty"><h3>%s</h3><p>%s</p></div></div>',
                esc_html($title),
                esc_html($message)
            );
        }

        /**
         * Formatiert ein Datum für die Anzeige
         */
        private function format_date($date) {
            if (empty($date)) return '-';
            $timestamp = strtotime($date);
            if (!$timestamp) return $date;
            return date_i18n('d.m.Y', $timestamp);
        }

        /**
         * Prüft ob ein Kontakt den Tag "Vorstand" hat
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

            // Auch alternative Feldnamen prüfen
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
                    // Zoho gibt Tags als Array von Objekten zurück: [{"name": "Vorstand", "id": "123"}]
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

            // Geschäftsstellen-Mitarbeitende sind nie stimmberechtigt, auch mit Vorstand-Tag
            if ($is_vorstand && $this->contact_has_tag($contact, 'Mitarbeitende')) {
                $this->log('check_vorstand_tag: ' . $contact_id . ' hat Mitarbeitende-Tag -> is_vorstand=false');
                $is_vorstand = false;
            }

            $this->log('check_vorstand_tag: Result for ' . $contact_id . ': is_vorstand=' . ($is_vorstand ? 'true' : 'false'));

            return [
                'is_vorstand' => $is_vorstand,
                'contact' => $contact
            ];
        }

        /**
         * Prüft ob ein Vorstandsmitglied bereits ueber diesen Antrag abgestimmt hat
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

            // Prüfen ob dieses Vorstandsmitglied bereits abgestimmt hat
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
                'https://www.zohoapis.eu/crm/v8/Contacts/' . $contact_id,
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
         * Holt Anhänge eines Kontakts aus Zoho CRM
         */
        private function get_contact_attachments($contact_id, $token) {
            $attachments = [];

            // Versuche verschiedene Felder für Anhänge
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
                        $download_url = 'https://www.zohoapis.eu/crm/v8/Contacts/' . $contact_id . '/Attachments/' . $file_id;

                        $attachments[] = [
                            'id' => $file_id,
                            'name' => $field,
                            'url' => $download_url . '?oauth_token=' . $token
                        ];
                    }
                }
            }

            // Auch allgemeine Attachments prüfen
            $attachments_response = wp_remote_get(
                'https://www.zohoapis.eu/crm/v8/Contacts/' . $contact_id . '/Attachments',
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
                            'url' => 'https://www.zohoapis.eu/crm/v8/Contacts/' . $contact_id . '/Attachments/' . $att['id'] . '?oauth_token=' . $token
                        ];
                    }
                }
            }

            return $attachments;
        }

        /**
         * AJAX Handler für Vorstandsentscheidung
         * Erwartet POST: antragsteller_token, vorstand_id (Dropdown-Auswahl), entscheidung, bemerkung
         */
        public function ajax_vorstand_entscheidung() {
            check_ajax_referer('dgptm_vorstand_entscheidung', 'nonce');

            $antragsteller_token = sanitize_text_field($_POST['antragsteller_token'] ?? '');
            $vorstand_id         = sanitize_text_field($_POST['vorstand_id'] ?? '');
            $action              = sanitize_text_field($_POST['entscheidung'] ?? '');
            $bemerkung           = sanitize_textarea_field($_POST['bemerkung'] ?? '');

            if ($antragsteller_token === '' || $vorstand_id === '' || $action === '') {
                wp_send_json_error(['message' => 'Fehlende Parameter']);
                return;
            }

            if (!in_array($action, ['approve', 'reject'], true)) {
                wp_send_json_error(['message' => 'Ungültige Aktion']);
                return;
            }

            $oauth = $this->get_access_token();
            if (!$oauth) {
                wp_send_json_error(['message' => 'CRM-Verbindung fehlgeschlagen']);
                return;
            }

            // Antragsteller per Token auflösen (kein raw-ID im URL mehr)
            $antragsteller = $this->get_contact_by_token($antragsteller_token, $oauth);
            if (!$antragsteller) {
                wp_send_json_error(['message' => 'Die Abstimmungsphase für diesen Antrag ist bereits beendet.']);
                return;
            }
            $skip_status_check = current_user_can('manage_options') && !empty($_POST['skip_status_check']);
            if (!$skip_status_check && ($antragsteller['Contact_Status'] ?? '') !== 'In Prüfung beim Vorstand') {
                wp_send_json_error(['message' => 'Dieser Antrag befindet sich nicht mehr in der Vorstandsabstimmung.']);
                return;
            }
            $antragsteller_id = $antragsteller['id'];

            // Gewähltes Vorstandsmitglied muss Tag "Vorstand" tragen
            $vorstand_check = $this->check_vorstand_tag($vorstand_id, $oauth);
            if (empty($vorstand_check['is_vorstand'])) {
                wp_send_json_error(['message' => 'Das gewählte Mitglied ist nicht als Vorstand autorisiert.']);
                return;
            }

            // Race-Schutz: nochmals gegen aktuelle Abstimmungsliste prüfen
            if (in_array((string) $vorstand_id, $this->get_bereits_abgestimmte_ids($antragsteller), true)) {
                wp_send_json_error(['message' => 'Für dieses Vorstandsmitglied liegt bereits eine Abstimmung vor.']);
                return;
            }

            $vorstand      = $vorstand_check['contact'] ?? $this->get_contact_details($vorstand_id, $oauth);
            $vorstand_name = $vorstand
                ? ($vorstand['Full_Name'] ?? trim(($vorstand['First_Name'] ?? '') . ' ' . ($vorstand['Last_Name'] ?? '')))
                : 'Unbekannt';

            $result = $this->speichere_abstimmung($antragsteller_id, $vorstand_id, $vorstand_name, $action, $bemerkung, $oauth);

            if (!$result['success']) {
                wp_send_json_error(['message' => $result['message']]);
                return;
            }

            $action_text = $action === 'approve' ? 'genehmigt' : 'abgelehnt';
            wp_send_json_success([
                'message' => 'Deine Entscheidung wurde gespeichert. Der Antrag wurde ' . $action_text . '.',
                'action'  => $action
            ]);
        }

        /**
         * Holt einen Contact anhand des CRM-Feldes "token" (Antragsteller-Token).
         * Die Search-API gibt File-Upload-Felder (StudinachweisDirekt, QualiNachweisDirekt)
         * nicht zurück — deshalb nach dem Hit noch einen Direct-Get, der den
         * vollständigen Datensatz inkl. dieser Felder liefert.
         */
        private function get_contact_by_token($token_value, $oauth) {
            $token_value = trim((string) $token_value);
            if ($token_value === '') {
                return null;
            }

            $url = 'https://www.zohoapis.eu/crm/v8/Contacts/search?criteria=' .
                urlencode('(token:equals:' . $token_value . ')');

            $response = wp_remote_get($url, [
                'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $oauth],
                'timeout' => 30
            ]);

            if (is_wp_error($response)) {
                $this->log('ERROR get_contact_by_token: ' . $response->get_error_message());
                return null;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code === 204) {
                return null;
            }

            $body     = json_decode(wp_remote_retrieve_body($response), true);
            $hit      = $body['data'][0] ?? null;
            if (!$hit || empty($hit['id'])) {
                return null;
            }

            $full = $this->get_contact_details($hit['id'], $oauth);
            return $full ?: $hit;
        }

        /**
         * Prüft, ob das Tag-Feld eines Contacts einen bestimmten Tag-Namen enthält.
         */
        private function contact_has_tag($contact, $tag_name) {
            $tags = $contact['Tag'] ?? [];
            if (is_string($tags)) {
                $tags = array_map('trim', explode(',', $tags));
            }
            if (!is_array($tags)) {
                return false;
            }
            foreach ($tags as $t) {
                $name = is_array($t) ? ($t['name'] ?? $t['Name'] ?? '') : (is_string($t) ? $t : '');
                if (strcasecmp(trim($name), $tag_name) === 0) {
                    return true;
                }
            }
            return false;
        }

        /**
         * Holt alle Contacts mit Tag "Vorstand" aus dem CRM.
         * Geschäftsstellen-Mitarbeitende (Tag "Mitarbeitende") werden ausgeschlossen,
         * auch wenn sie zusätzlich den Tag "Vorstand" tragen.
         * 24h-Transient-Cache. Ergebnis ist alphabetisch nach Nachname sortiert.
         */
        private function get_active_vorstaende($oauth, $bypass_cache = false) {
            // Cache-Key versioniert — inkrementieren, wenn Filter-Logik sich ändert
            $cache_key = 'dgptm_vorstand_liste_v2';

            if (!$bypass_cache) {
                $cached = get_transient($cache_key);
                if (is_array($cached)) {
                    return $cached;
                }
            }

            $vorstaende = [];
            $page       = 1;
            $page_size  = 200;

            do {
                $url = 'https://www.zohoapis.eu/crm/v8/Contacts/search?criteria=' .
                    urlencode('(Tag:equals:Vorstand)') .
                    '&page=' . $page . '&per_page=' . $page_size;

                $response = wp_remote_get($url, [
                    'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $oauth],
                    'timeout' => 30
                ]);

                if (is_wp_error($response)) {
                    $this->log('ERROR get_active_vorstaende: ' . $response->get_error_message());
                    break;
                }

                $http_code = wp_remote_retrieve_response_code($response);
                if ($http_code === 204) {
                    break;
                }

                $body = json_decode(wp_remote_retrieve_body($response), true);
                $data = $body['data'] ?? [];

                foreach ($data as $c) {
                    // Geschäftsstellen-Mitarbeitende rausfiltern, auch wenn sie "Vorstand" tragen
                    if ($this->contact_has_tag($c, 'Mitarbeitende')) {
                        continue;
                    }
                    $vorstaende[] = [
                        'id'             => $c['id'],
                        'first_name'     => $c['First_Name'] ?? '',
                        'last_name'      => $c['Last_Name'] ?? '',
                        'full_name'      => $c['Full_Name'] ?? trim(($c['First_Name'] ?? '') . ' ' . ($c['Last_Name'] ?? '')),
                        'salutation'     => $c['Salutation'] ?? '',
                        'academic_title' => $c['Academic_Title'] ?? '',
                        'title_after'    => $c['Title_After_The_Name'] ?? ''
                    ];
                }

                $more = !empty($body['info']['more_records']);
                $page++;
            } while ($more && $page < 20);

            usort($vorstaende, function ($a, $b) {
                $cmp = strcasecmp($a['last_name'], $b['last_name']);
                return $cmp !== 0 ? $cmp : strcasecmp($a['first_name'], $b['first_name']);
            });

            set_transient($cache_key, $vorstaende, DAY_IN_SECONDS);
            return $vorstaende;
        }

        /**
         * Baut das Anzeigeformat eines Vorstandsmitglieds für das Dropdown.
         * Berücksichtigt Titel und Nachname-Zusatz.
         */
        private function format_vorstand_label($v) {
            $parts = array_filter([
                $v['salutation']     ?? '',
                $v['academic_title'] ?? '',
                $v['first_name']     ?? '',
                $v['last_name']      ?? ''
            ], function ($s) { return trim((string) $s) !== ''; });

            $label = implode(' ', $parts);
            if (!empty($v['title_after'])) {
                $label .= ', ' . $v['title_after'];
            }
            return $label !== '' ? $label : ($v['full_name'] ?? '(Unbekannt)');
        }

        /**
         * Liefert Liste der IDs, die für diesen Antragsteller bereits abgestimmt haben.
         */
        private function get_bereits_abgestimmte_ids($antragsteller) {
            $raw = $antragsteller['Vorstand_Abstimmungen'] ?? '';
            if (empty($raw)) {
                return [];
            }
            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                return [];
            }
            $ids = [];
            foreach ($decoded as $a) {
                if (!empty($a['vorstand_id'])) {
                    $ids[] = (string) $a['vorstand_id'];
                }
            }
            return $ids;
        }

        /**
         * Sammelt Dateien aus den File-Upload-Feldern StudinachweisDirekt und QualiNachweisDirekt.
         */
        private function get_direct_uploads_from_contact($contact) {
            $uploads = [];
            if (!is_array($contact)) {
                return $uploads;
            }

            $field_map = [
                'StudinachweisDirekt' => 'Studienbescheinigung',
                'QualiNachweisDirekt' => 'Qualifikationsnachweis'
            ];

            foreach ($field_map as $api_field => $label) {
                $files = $contact[$api_field] ?? null;
                if (!is_array($files)) {
                    continue;
                }
                foreach ($files as $f) {
                    // v8-API liefert File_Id__s / File_Name__s / Size__s / id,
                    // ältere UI-Payloads haben file_Id / file_Name / file_Size / attachment_Id.
                    // file_id ist der für /crm/v8/files?id=... benötigte Hash-String.
                    $file_id = (string) ($f['File_Id__s'] ?? $f['file_Id'] ?? '');
                    if ($file_id === '') {
                        continue;
                    }
                    $size_bytes = $f['Size__s'] ?? null;
                    $file_size  = $f['file_Size'] ?? ($size_bytes !== null ? $this->format_bytes((int) $size_bytes) : '');
                    $uploads[] = [
                        'file_id'       => $file_id,
                        'attachment_id' => (string) ($f['id'] ?? $f['attachment_Id'] ?? ''),
                        'file_name'     => $f['File_Name__s'] ?? $f['file_Name'] ?? 'Dokument',
                        'file_size'     => $file_size,
                        'source'        => $label
                    ];
                }
            }
            return $uploads;
        }

        /**
         * Formatiert Bytes als lesbare Größe (KB/MB).
         */
        private function format_bytes($bytes) {
            if ($bytes < 1024) {
                return $bytes . ' B';
            }
            if ($bytes < 1048576) {
                return number_format($bytes / 1024, 1, ',', '.') . ' KB';
            }
            return number_format($bytes / 1048576, 1, ',', '.') . ' MB';
        }

        /**
         * URL zum Download-Proxy für eine Nachweis-Datei des Antragstellers.
         * file_id ist der Zoho-File-ID-Hash (nicht die numerische attachment_Id).
         */
        private function build_download_proxy_url($antragsteller_token, $file_id) {
            return add_query_arg([
                'action'              => 'dgptm_download_crm_file',
                'antragsteller_token' => $antragsteller_token,
                'file_id'             => $file_id
            ], admin_url('admin-ajax.php'));
        }

        /**
         * AJAX: Download-Proxy für Zoho-File-Upload-Felder.
         * Autorisierung: Antragsteller-Token muss zu einem Contact gehören, in dessen
         * File-Upload-Feldern (StudinachweisDirekt / QualiNachweisDirekt) die angefragte
         * file_id steht. Der Vorstand klickt den WP-Link, der Server holt die Datei mit
         * OAuth von Zoho und streamt sie zurück — der Browser sieht nur WordPress.
         */
        public function ajax_download_crm_file() {
            $antragsteller_token = sanitize_text_field($_GET['antragsteller_token'] ?? '');
            $file_id             = sanitize_text_field($_GET['file_id'] ?? '');

            if ($antragsteller_token === '' || $file_id === '') {
                wp_die('Ungültige Parameter', 'Download', ['response' => 400]);
            }

            $oauth = $this->get_access_token();
            if (!$oauth) {
                wp_die('CRM-Verbindung fehlgeschlagen', 'Download', ['response' => 502]);
            }

            $antragsteller = $this->get_contact_by_token($antragsteller_token, $oauth);
            if (!$antragsteller) {
                wp_die('Nicht autorisiert', 'Download', ['response' => 403]);
            }

            $uploads   = $this->get_direct_uploads_from_contact($antragsteller);
            $allowed   = false;
            $file_name = 'download.bin';
            foreach ($uploads as $u) {
                if ($u['file_id'] === $file_id) {
                    $allowed   = true;
                    $file_name = $u['file_name'];
                    break;
                }
            }
            if (!$allowed) {
                wp_die('Datei ist für diesen Antrag nicht freigegeben', 'Download', ['response' => 403]);
            }

            // Zoho-Endpoint für File-Upload-Felder: /crm/v8/files?id=<file_Id>
            $url = 'https://www.zohoapis.eu/crm/v8/files?id=' . urlencode($file_id);
            $response = wp_remote_get($url, [
                'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $oauth],
                'timeout' => 60
            ]);

            if (is_wp_error($response)) {
                $this->log('ERROR download-proxy: ' . $response->get_error_message());
                wp_die('Fehler beim Laden der Datei', 'Download', ['response' => 502]);
            }

            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code !== 200) {
                $body_err = wp_remote_retrieve_body($response);
                $this->log('ERROR download-proxy HTTP ' . $http_code . ' body: ' . substr($body_err, 0, 500));
                wp_die('CRM-Fehler beim Download: HTTP ' . $http_code, 'Download', ['response' => 502]);
            }

            $body         = wp_remote_retrieve_body($response);
            $content_type = wp_remote_retrieve_header($response, 'content-type');
            if (empty($content_type)) {
                $content_type = $this->guess_content_type_from_name($file_name);
            }

            nocache_headers();
            header('Content-Type: ' . $content_type);
            header('Content-Disposition: inline; filename="' . rawurlencode($file_name) . '"');
            header('Content-Length: ' . strlen($body));
            echo $body;
            exit;
        }

        /**
         * Content-Type-Heuristik als Fallback, wenn Zoho keinen Header mitsendet.
         */
        private function guess_content_type_from_name($file_name) {
            $ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            $map = [
                'pdf'  => 'application/pdf',
                'png'  => 'image/png',
                'jpg'  => 'image/jpeg',
                'jpeg' => 'image/jpeg',
                'gif'  => 'image/gif',
                'webp' => 'image/webp',
                'heic' => 'image/heic',
                'doc'  => 'application/msword',
                'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
            ];
            return $map[$ext] ?? 'application/octet-stream';
        }

        /**
         * AJAX (Admin): Vorstands-Cache leeren (nach Tag-Änderungen im CRM).
         */
        public function ajax_clear_vorstand_cache() {
            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung'], 403);
                return;
            }
            check_ajax_referer('dgptm_mitgliedsantrag_admin_nonce', 'nonce');
            delete_transient('dgptm_vorstand_liste');
            delete_transient('dgptm_vorstand_liste_v2');
            wp_send_json_success(['message' => 'Vorstands-Cache geleert.']);
        }

        /**
         * Speichert eine Vorstandsabstimmung im Zoho CRM
         */
        private function speichere_abstimmung($antragsteller_id, $vorstand_id, $vorstand_name, $entscheidung, $bemerkung, $token) {
            // Aktuelle Abstimmungen laden
            $antragsteller = $this->get_contact_details($antragsteller_id, $token);

            if (!$antragsteller) {
                return ['success' => false, 'message' => 'Antragsteller:in nicht gefunden'];
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
                'Vorstand_Abstimmungen'    => wp_json_encode($abstimmungen),
                'Membership_Approved'      => $genehmigt_count,
                'Membership_Not_Approved'  => $abgelehnt_count
            ];

            // Wenn abgelehnt, Antragsstatus aktualisieren
            if ($entscheidung === 'reject') {
                $update_data['Application_Status'] = 'Abgelehnt';
                $update_data['Antragsstatus'] = 'Abgelehnt durch Vorstand';
            }

            // Update im CRM ausführen
            $response = wp_remote_request(
                'https://www.zohoapis.eu/crm/v8/Contacts/' . $antragsteller_id,
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
                // Bemerkung als Notiz am Antragsteller-Contact ablegen (Fehler sind
                // nicht blockierend — die Abstimmung selbst ist bereits gespeichert).
                $this->speichere_abstimmungs_note($antragsteller_id, $vorstand_name, $entscheidung, $bemerkung, $token);
                return ['success' => true, 'message' => 'Abstimmung gespeichert'];
            }

            $body = wp_remote_retrieve_body($response);
            $this->log('ERROR: Vote save returned HTTP ' . $http_code . ': ' . $body);
            return ['success' => false, 'message' => 'CRM-Fehler: HTTP ' . $http_code];
        }

        /**
         * Legt die Vorstandsabstimmung (inkl. Bemerkung) als Notiz am Antragsteller ab.
         * Titel: "Vorstandsabstimmung: Genehmigung/Ablehnung – <Name>"
         * Inhalt: Vorstand, Entscheidung, Zeitstempel, Bemerkung.
         * Bei leerer Bemerkung wird trotzdem eine Audit-Note geschrieben.
         */
        private function speichere_abstimmungs_note($contact_id, $vorstand_name, $entscheidung, $bemerkung, $oauth) {
            $entscheidung_text = $entscheidung === 'approve' ? 'Genehmigung' : 'Ablehnung';
            $title             = 'Vorstandsabstimmung: ' . $entscheidung_text . ' – ' . $vorstand_name;
            $bemerkung_trim    = trim((string) $bemerkung);

            $content = sprintf(
                "Vorstandsmitglied: %s\nEntscheidung: %s\nDatum: %s\n\nBemerkung:\n%s",
                $vorstand_name,
                $entscheidung_text,
                current_time('d.m.Y H:i'),
                $bemerkung_trim !== '' ? $bemerkung_trim : '(keine)'
            );

            $response = wp_remote_post(
                'https://www.zohoapis.eu/crm/v8/Contacts/' . $contact_id . '/Notes',
                [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $oauth,
                        'Content-Type'  => 'application/json'
                    ],
                    'body'    => wp_json_encode(['data' => [[
                        'Note_Title'   => $title,
                        'Note_Content' => $content
                    ]]]),
                    'timeout' => 30
                ]
            );

            if (is_wp_error($response)) {
                $this->log('WARN speichere_abstimmungs_note: ' . $response->get_error_message());
                return;
            }

            $http_code = wp_remote_retrieve_response_code($response);
            if ($http_code < 200 || $http_code >= 300) {
                $body = wp_remote_retrieve_body($response);
                $this->log('WARN speichere_abstimmungs_note HTTP ' . $http_code . ': ' . substr($body, 0, 300));
            }
        }

        private function log($message) {
            dgptm_log_info($message, 'mitgliedsantrag');
        }
    }
}

// Initialize the module
if (!isset($GLOBALS['dgptm_mitgliedsantrag_initialized'])) {
    $GLOBALS['dgptm_mitgliedsantrag_initialized'] = true;
    DGPTM_Mitgliedsantrag::get_instance();
}
