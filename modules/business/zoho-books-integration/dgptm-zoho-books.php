<?php
/**
 * Plugin Name: DGPTM - Zoho Books Integration
 * Description: Zeigt Rechnungen und Gutschriften aus Zoho Books basierend auf der Finance ID aus Zoho CRM
 * Version: 1.0.0
 * Author: Sebastian Melzer
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent class redeclaration
if (!class_exists('DGPTM_Zoho_Books')) {
    class DGPTM_Zoho_Books {
        private static $instance = null;
        private $plugin_path;
        private $plugin_url;
        private $option_name = 'dgptm_zoho_books_settings';

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->plugin_path = plugin_dir_path(__FILE__);
            $this->plugin_url = plugin_dir_url(__FILE__);

            // Hooks
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_init', [$this, 'register_settings']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
            add_action('wp_enqueue_scripts', [$this, 'enqueue_frontend_assets']);

            // Shortcodes
            add_shortcode('zoho_books_transactions', [$this, 'shortcode_transactions']);
            add_shortcode('zoho_books_outstanding_banner', [$this, 'shortcode_outstanding_banner']);

            // AJAX handlers
            add_action('wp_ajax_zoho_books_oauth_callback', [$this, 'handle_oauth_callback']);
            add_action('wp_ajax_zoho_books_refresh_token', [$this, 'ajax_refresh_token']);

            // OAuth callback handler
            add_action('init', [$this, 'handle_oauth_redirect']);

            $this->log('Module initialized');
        }

        /**
         * Logging helper
         */
        private function log($message, $data = null) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                $log_message = 'Zoho Books Integration - ' . $message;
                if ($data !== null) {
                    $log_message .= ': ' . print_r($data, true);
                }
                error_log($log_message);
            }
        }

        /**
         * Add admin menu
         */
        public function add_admin_menu() {
            add_submenu_page(
                'dgptm-suite',
                'Zoho Books Integration',
                'Zoho Books',
                'manage_options',
                'dgptm-zoho-books',
                [$this, 'render_admin_page']
            );
        }

        /**
         * Register settings
         */
        public function register_settings() {
            register_setting('dgptm_zoho_books_settings_group', $this->option_name, [
                'type' => 'array',
                'sanitize_callback' => [$this, 'sanitize_settings']
            ]);
        }

        /**
         * Sanitize settings
         */
        public function sanitize_settings($input) {
            $sanitized = [];

            if (isset($input['client_id'])) {
                $sanitized['client_id'] = sanitize_text_field($input['client_id']);
            }

            if (isset($input['client_secret'])) {
                $sanitized['client_secret'] = sanitize_text_field($input['client_secret']);
            }

            if (isset($input['organization_id'])) {
                $sanitized['organization_id'] = sanitize_text_field($input['organization_id']);
            }

            if (isset($input['redirect_uri'])) {
                $sanitized['redirect_uri'] = esc_url_raw($input['redirect_uri']);
            }

            // Preserve existing tokens
            $existing = get_option($this->option_name, []);
            if (isset($existing['access_token'])) {
                $sanitized['access_token'] = $existing['access_token'];
            }
            if (isset($existing['refresh_token'])) {
                $sanitized['refresh_token'] = $existing['refresh_token'];
            }
            if (isset($existing['token_expires_at'])) {
                $sanitized['token_expires_at'] = $existing['token_expires_at'];
            }

            return $sanitized;
        }

        /**
         * Enqueue admin assets
         */
        public function enqueue_admin_assets($hook) {
            if ($hook !== 'dgptm-suite_page_dgptm-zoho-books') {
                return;
            }

            wp_enqueue_style(
                'dgptm-zoho-books-admin',
                $this->plugin_url . 'assets/css/admin.css',
                [],
                '1.0.0'
            );
        }

        /**
         * Enqueue frontend assets
         */
        public function enqueue_frontend_assets() {
            if (!is_singular() && !is_page()) {
                return;
            }

            global $post;
            if (has_shortcode($post->post_content, 'zoho_books_transactions') ||
                has_shortcode($post->post_content, 'zoho_books_outstanding_banner')) {

                wp_enqueue_style(
                    'dgptm-zoho-books-frontend',
                    $this->plugin_url . 'assets/css/frontend.css',
                    [],
                    '1.0.0'
                );

                wp_enqueue_script(
                    'dgptm-zoho-books-frontend',
                    $this->plugin_url . 'assets/js/frontend.js',
                    ['jquery'],
                    '1.0.0',
                    true
                );
            }
        }

        /**
         * Render admin page
         */
        public function render_admin_page() {
            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }

            $settings = get_option($this->option_name, []);
            $is_connected = !empty($settings['access_token']);
            $has_refresh_token = !empty($settings['refresh_token']);
            $token_expires_at = $settings['token_expires_at'] ?? 0;
            $last_refresh = $settings['last_token_refresh'] ?? 0;

            // Check token status
            $token_expired = $token_expires_at > 0 && $token_expires_at < time();
            $token_expires_soon = $token_expires_at > 0 && $token_expires_at < (time() + 600); // Expires in 10 minutes

            ?>
            <div class="wrap">
                <h1>Zoho Books Integration - Einstellungen</h1>

                <?php if ($is_connected && $has_refresh_token): ?>
                    <div class="notice notice-success">
                        <p>
                            <strong>✓ Verbindung aktiv</strong><br>
                            <?php if ($last_refresh > 0): ?>
                                Letzter Token-Refresh: <?php echo date('d.m.Y H:i:s', $last_refresh); ?><br>
                            <?php endif; ?>
                            <?php if ($token_expires_at > 0): ?>
                                Access-Token gültig bis: <?php echo date('d.m.Y H:i:s', $token_expires_at); ?>
                                <?php if ($token_expires_soon && !$token_expired): ?>
                                    <span style="color: orange;">(läuft bald ab, wird automatisch erneuert)</span>
                                <?php elseif ($token_expired): ?>
                                    <span style="color: red;">(abgelaufen, wird beim nächsten Abruf erneuert)</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </p>
                    </div>
                <?php elseif ($is_connected && !$has_refresh_token): ?>
                    <div class="notice notice-error">
                        <p><strong>⚠ Verbindung unvollständig</strong> - Refresh-Token fehlt. Bitte Verbindung neu herstellen.</p>
                    </div>
                <?php else: ?>
                    <div class="notice notice-warning">
                        <p><strong>Nicht verbunden</strong> - Bitte OAuth-Verbindung herstellen</p>
                    </div>
                <?php endif; ?>

                <form method="post" action="options.php">
                    <?php
                    settings_fields('dgptm_zoho_books_settings_group');
                    ?>

                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="client_id">Zoho Client ID</label>
                            </th>
                            <td>
                                <input type="text"
                                       id="client_id"
                                       name="<?php echo esc_attr($this->option_name); ?>[client_id]"
                                       value="<?php echo esc_attr($settings['client_id'] ?? ''); ?>"
                                       class="regular-text">
                                <p class="description">Zoho API Client ID (aus Zoho Developer Console)</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="client_secret">Zoho Client Secret</label>
                            </th>
                            <td>
                                <input type="password"
                                       id="client_secret"
                                       name="<?php echo esc_attr($this->option_name); ?>[client_secret]"
                                       value="<?php echo esc_attr($settings['client_secret'] ?? ''); ?>"
                                       class="regular-text">
                                <p class="description">Zoho API Client Secret</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="organization_id">Zoho Books Organization ID</label>
                            </th>
                            <td>
                                <input type="text"
                                       id="organization_id"
                                       name="<?php echo esc_attr($this->option_name); ?>[organization_id]"
                                       value="<?php echo esc_attr($settings['organization_id'] ?? ''); ?>"
                                       class="regular-text">
                                <p class="description">Zoho Books Organization ID</p>
                            </td>
                        </tr>

                        <tr>
                            <th scope="row">
                                <label for="redirect_uri">Redirect URI</label>
                            </th>
                            <td>
                                <input type="text"
                                       id="redirect_uri"
                                       name="<?php echo esc_attr($this->option_name); ?>[redirect_uri]"
                                       value="<?php echo esc_attr($settings['redirect_uri'] ?? admin_url('admin.php?page=dgptm-zoho-books&oauth_callback=1')); ?>"
                                       class="regular-text"
                                       readonly>
                                <p class="description">Diese URI in der Zoho Developer Console als Redirect URI eintragen</p>
                            </td>
                        </tr>
                    </table>

                    <?php submit_button('Einstellungen speichern'); ?>
                </form>

                <hr>

                <h2>OAuth-Verbindung</h2>

                <?php if (!empty($settings['client_id']) && !empty($settings['client_secret'])): ?>
                    <p>
                        <a href="<?php echo esc_url($this->get_oauth_url()); ?>"
                           class="button button-primary">
                            <?php echo $is_connected ? 'Verbindung erneuern' : 'Mit Zoho verbinden'; ?>
                        </a>
                    </p>

                    <p class="description">
                        Klicken Sie auf "<?php echo $is_connected ? 'Verbindung erneuern' : 'Mit Zoho verbinden'; ?>" um die OAuth-Verbindung zu Zoho CRM und Zoho Books <?php echo $is_connected ? 'neu herzustellen' : 'herzustellen'; ?>.
                    </p>

                    <div class="notice notice-info" style="margin-top: 15px;">
                        <p>
                            <strong>ℹ️ Wichtig zur OAuth-Verbindung:</strong><br>
                            • Der <strong>Access-Token</strong> läuft nach 1 Stunde ab und wird automatisch erneuert<br>
                            • Der <strong>Refresh-Token</strong> bleibt dauerhaft gültig, solange er regelmäßig verwendet wird<br>
                            • Bei jedem API-Abruf wird der Access-Token automatisch aktualisiert, falls nötig<br>
                            • Wenn ein neuer Refresh-Token von Zoho bereitgestellt wird, wird er automatisch gespeichert<br>
                            • Bei Problemen werden Sie per E-Mail benachrichtigt
                        </p>
                    </div>
                <?php else: ?>
                    <p class="description">
                        Bitte zuerst Client ID und Client Secret eingeben und speichern.
                    </p>
                <?php endif; ?>

                <hr>

                <h2>Shortcodes</h2>

                <h3>1. Transaktionsliste</h3>
                <code>[zoho_books_transactions]</code>
                <p class="description">Zeigt eine sortierbare, nach Jahren filterbare Liste aller Rechnungen und Gutschriften</p>

                <h3>2. Banner für offene Beträge</h3>
                <code>[zoho_books_outstanding_banner]</code>
                <p class="description">Zeigt ein rotes Banner wenn offene Beträge vorhanden sind</p>

                <hr>

                <h2>Benutzer-Meta</h2>
                <p class="description">
                    Die Zoho CRM Contact ID muss im Benutzer-Meta-Feld <strong>"zoho_id"</strong> gespeichert sein.<br>
                    Das Modul holt dann automatisch die Finance_ID aus dem Contact und zeigt die zugehörigen Transaktionen aus Zoho Books an.
                </p>
            </div>
            <?php
        }

        /**
         * Get OAuth authorization URL
         */
        private function get_oauth_url() {
            $settings = get_option($this->option_name, []);

            $params = [
                'scope' => 'ZohoCRM.modules.READ,ZohoBooks.fullaccess.all',
                'client_id' => $settings['client_id'],
                'response_type' => 'code',
                'access_type' => 'offline',
                'redirect_uri' => admin_url('admin.php?page=dgptm-zoho-books&oauth_callback=1'),
                'prompt' => 'consent'
            ];

            return 'https://accounts.zoho.eu/oauth/v2/auth?' . http_build_query($params);
        }

        /**
         * Handle OAuth redirect
         */
        public function handle_oauth_redirect() {
            if (!isset($_GET['page']) || $_GET['page'] !== 'dgptm-zoho-books') {
                return;
            }

            if (!isset($_GET['oauth_callback']) || $_GET['oauth_callback'] !== '1') {
                return;
            }

            if (!isset($_GET['code'])) {
                return;
            }

            if (!current_user_can('manage_options')) {
                wp_die('Unauthorized');
            }

            $this->log('OAuth callback received with code');

            $settings = get_option($this->option_name, []);

            // Exchange code for tokens
            $response = wp_remote_post('https://accounts.zoho.eu/oauth/v2/token', [
                'body' => [
                    'code' => $_GET['code'],
                    'client_id' => $settings['client_id'],
                    'client_secret' => $settings['client_secret'],
                    'redirect_uri' => admin_url('admin.php?page=dgptm-zoho-books&oauth_callback=1'),
                    'grant_type' => 'authorization_code'
                ]
            ]);

            if (is_wp_error($response)) {
                $this->log('OAuth token exchange failed', $response->get_error_message());
                wp_die('OAuth-Fehler: ' . $response->get_error_message());
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['error'])) {
                $this->log('OAuth token exchange error', $body);
                wp_die('OAuth-Fehler: ' . ($body['error'] ?? 'Unknown error'));
            }

            if (!isset($body['access_token'])) {
                $this->log('No access token in response', $body);
                wp_die('OAuth-Fehler: Kein Access Token erhalten');
            }

            // Save tokens
            $settings['access_token'] = $body['access_token'];
            $settings['refresh_token'] = $body['refresh_token'] ?? '';
            $settings['token_expires_at'] = time() + ($body['expires_in'] ?? 3600);

            update_option($this->option_name, $settings);

            $this->log('OAuth tokens saved successfully');

            // Redirect to settings page
            wp_redirect(admin_url('admin.php?page=dgptm-zoho-books&oauth_success=1'));
            exit;
        }

        /**
         * Refresh access token
         */
        private function refresh_access_token() {
            $settings = get_option($this->option_name, []);

            if (empty($settings['refresh_token'])) {
                $this->log('No refresh token available');
                return false;
            }

            $this->log('Refreshing access token');

            $response = wp_remote_post('https://accounts.zoho.eu/oauth/v2/token', [
                'body' => [
                    'refresh_token' => $settings['refresh_token'],
                    'client_id' => $settings['client_id'],
                    'client_secret' => $settings['client_secret'],
                    'grant_type' => 'refresh_token'
                ]
            ]);

            if (is_wp_error($response)) {
                $this->log('Token refresh failed', $response->get_error_message());
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (isset($body['error']) || !isset($body['access_token'])) {
                $this->log('Token refresh error', $body);

                // If refresh token is invalid, send notification
                if (isset($body['error']) && $body['error'] === 'invalid_code') {
                    $this->send_oauth_error_notification('Refresh-Token ungültig oder abgelaufen');
                }

                return false;
            }

            // Update access token
            $settings['access_token'] = $body['access_token'];
            $settings['token_expires_at'] = time() + ($body['expires_in'] ?? 3600);

            // Update refresh token if a new one is provided
            // Note: Zoho may provide a new refresh token, always save it if present
            if (isset($body['refresh_token'])) {
                $settings['refresh_token'] = $body['refresh_token'];
                $this->log('Refresh token updated (new token received from Zoho)');
            }

            // Track last successful refresh
            $settings['last_token_refresh'] = time();

            update_option($this->option_name, $settings);

            $this->log('Access token refreshed successfully');

            return true;
        }

        /**
         * Get valid access token
         */
        private function get_access_token() {
            $settings = get_option($this->option_name, []);

            if (empty($settings['access_token'])) {
                $this->log('No access token available');
                return false;
            }

            // Check if token is expired
            $expires_at = $settings['token_expires_at'] ?? 0;
            if ($expires_at < time() + 300) { // Refresh 5 minutes before expiry
                $this->log('Token expired or expiring soon, refreshing');
                if (!$this->refresh_access_token()) {
                    return false;
                }
                $settings = get_option($this->option_name, []);
            }

            return $settings['access_token'];
        }

        /**
         * Send OAuth error notification to admin
         */
        private function send_oauth_error_notification($error_message) {
            $admin_email = get_option('admin_email');

            $subject = '[Zoho Books] OAuth-Verbindung fehlgeschlagen';

            $message = "Die OAuth-Verbindung zu Zoho ist fehlgeschlagen.\n\n";
            $message .= "Fehler: {$error_message}\n\n";
            $message .= "Bitte gehen Sie zur Zoho Books Einstellungsseite und stellen Sie die Verbindung erneut her:\n";
            $message .= admin_url('admin.php?page=dgptm-zoho-books') . "\n\n";
            $message .= "Zeitpunkt: " . date('d.m.Y H:i:s') . "\n";

            wp_mail($admin_email, $subject, $message);

            $this->log('OAuth error notification sent to admin: ' . $error_message);
        }

        /**
         * Send error notification to admin
         */
        private function send_error_notification($error_type, $user_id, $zoho_id, $finance_id = null) {
            $admin_email = get_option('admin_email');
            $user = get_user_by('id', $user_id);

            $subject = '[Zoho Books] Fehler beim Abruf - ' . $error_type;

            $message = "Es ist ein Fehler beim Abruf aus Zoho Books aufgetreten.\n\n";
            $message .= "Fehlertyp: {$error_type}\n\n";
            $message .= "Benutzer-Informationen:\n";
            $message .= "- User-ID: {$user_id}\n";
            $message .= "- Benutzername: " . ($user ? $user->user_login : 'Unbekannt') . "\n";
            $message .= "- E-Mail: " . ($user ? $user->user_email : 'Unbekannt') . "\n";
            $message .= "- Zoho Contact ID: {$zoho_id}\n";

            if ($finance_id) {
                $message .= "- Finance ID (Zoho Books): {$finance_id}\n";
            }

            $message .= "\nZeitpunkt: " . date('d.m.Y H:i:s') . "\n";
            $message .= "\nBitte überprüfen Sie die Zoho-Konfiguration und die Benutzer-Metadaten.\n";

            wp_mail($admin_email, $subject, $message);

            $this->log('Error notification sent to admin: ' . $error_type, [
                'user_id' => $user_id,
                'zoho_id' => $zoho_id,
                'finance_id' => $finance_id
            ]);
        }

        /**
         * Get Finance ID from Zoho CRM
         */
        private function get_finance_id($zoho_contact_id) {
            $access_token = $this->get_access_token();

            if (!$access_token) {
                $this->log('Cannot get Finance ID - no access token');
                return false;
            }

            $this->log('Fetching Finance ID for contact: ' . $zoho_contact_id);

            $response = wp_remote_get('https://www.zohoapis.eu/crm/v2/Contacts/' . $zoho_contact_id, [
                'headers' => [
                    'Authorization' => 'Zoho-oauthtoken ' . $access_token
                ]
            ]);

            if (is_wp_error($response)) {
                $this->log('CRM API request failed', $response->get_error_message());
                $this->send_error_notification(
                    'Zoho CRM API Fehler: ' . $response->get_error_message(),
                    get_current_user_id(),
                    $zoho_contact_id
                );
                return false;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);

            if (!isset($body['data'][0]['Finance_ID'])) {
                $this->log('Finance_ID not found in contact', $body);
                $this->send_error_notification(
                    'Finance_ID nicht in Zoho CRM Contact gefunden',
                    get_current_user_id(),
                    $zoho_contact_id
                );
                return false;
            }

            $finance_id = $body['data'][0]['Finance_ID'];
            $this->log('Finance ID found: ' . $finance_id);

            return $finance_id;
        }

        /**
         * Get transactions from Zoho Books
         */
        private function get_transactions($customer_id) {
            $access_token = $this->get_access_token();
            $settings = get_option($this->option_name, []);

            if (!$access_token) {
                $this->log('Cannot get transactions - no access token');
                return false;
            }

            if (empty($settings['organization_id'])) {
                $this->log('Cannot get transactions - no organization ID');
                return false;
            }

            $this->log('Fetching transactions for customer: ' . $customer_id);

            // Get invoices
            $invoices_response = wp_remote_get(
                'https://www.zohoapis.eu/books/v3/invoices?' . http_build_query([
                    'organization_id' => $settings['organization_id'],
                    'customer_id' => $customer_id
                ]),
                [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $access_token
                    ]
                ]
            );

            // Get credit notes
            $credits_response = wp_remote_get(
                'https://www.zohoapis.eu/books/v3/creditnotes?' . http_build_query([
                    'organization_id' => $settings['organization_id'],
                    'customer_id' => $customer_id
                ]),
                [
                    'headers' => [
                        'Authorization' => 'Zoho-oauthtoken ' . $access_token
                    ]
                ]
            );

            // Check for errors
            if (is_wp_error($invoices_response)) {
                $this->log('Zoho Books invoices API request failed', $invoices_response->get_error_message());
                $user_id = get_current_user_id();
                $zoho_id = get_user_meta($user_id, 'zoho_id', true);
                $this->send_error_notification(
                    'Zoho Books API Fehler (Invoices): ' . $invoices_response->get_error_message(),
                    $user_id,
                    $zoho_id,
                    $customer_id
                );
                return false;
            }

            if (is_wp_error($credits_response)) {
                $this->log('Zoho Books credit notes API request failed', $credits_response->get_error_message());
                // Don't fail completely if only credit notes fail, just log it
            }

            $transactions = [];

            // Process invoices
            $invoices_body = json_decode(wp_remote_retrieve_body($invoices_response), true);
            if (isset($invoices_body['code']) && $invoices_body['code'] != 0) {
                // Zoho Books API error
                $this->log('Zoho Books API error', $invoices_body);
                $user_id = get_current_user_id();
                $zoho_id = get_user_meta($user_id, 'zoho_id', true);
                $this->send_error_notification(
                    'Zoho Books API Fehler: ' . ($invoices_body['message'] ?? 'Unknown error'),
                    $user_id,
                    $zoho_id,
                    $customer_id
                );
                return false;
            }

            if (isset($invoices_body['invoices'])) {
                foreach ($invoices_body['invoices'] as $invoice) {
                    $transactions[] = [
                        'type' => 'invoice',
                        'id' => $invoice['invoice_id'],
                        'number' => $invoice['invoice_number'],
                        'date' => $invoice['date'],
                        'total' => $invoice['total'],
                        'balance' => $invoice['balance'],
                        'status' => $invoice['status'],
                        'currency_code' => $invoice['currency_code'],
                        'invoice_url' => $invoice['invoice_url'] ?? ''
                    ];
                }
            }

            // Process credit notes
            if (!is_wp_error($credits_response)) {
                $credits_body = json_decode(wp_remote_retrieve_body($credits_response), true);
                if (isset($credits_body['creditnotes'])) {
                    foreach ($credits_body['creditnotes'] as $credit) {
                        $transactions[] = [
                            'type' => 'creditnote',
                            'id' => $credit['creditnote_id'],
                            'number' => $credit['creditnote_number'],
                            'date' => $credit['date'],
                            'total' => -$credit['total'], // Negative for credit
                            'balance' => -($credit['balance'] ?? 0),
                            'status' => $credit['status'],
                            'currency_code' => $credit['currency_code'],
                            'invoice_url' => ''
                        ];
                    }
                }
            }

            $this->log('Found ' . count($transactions) . ' transactions');

            return $transactions;
        }

        /**
         * Translate transaction status to German
         */
        private function translate_status($status, $type) {
            $translations = [
                // Invoice statuses
                'draft' => 'Entwurf',
                'approved' => 'Genehmigt',
                'sent' => 'Versendet',
                'viewed' => 'Angesehen',
                'paid' => 'Bezahlt',
                'partially_paid' => 'Teilweise bezahlt',
                'unpaid' => 'Unbezahlt',
                'overdue' => 'Überfällig',
                'void' => 'Storniert',
                'accepted' => 'Akzeptiert',
                'declined' => 'Abgelehnt',

                // Credit note statuses
                'open' => 'Offen',
                'closed' => 'Erledigt',
                'refunded' => 'Erstattet'
            ];

            return $translations[strtolower($status)] ?? $status;
        }

        /**
         * Shortcode: Transaction list
         */
        public function shortcode_transactions($atts) {
            if (!is_user_logged_in()) {
                return '<p>Bitte melden Sie sich an, um Ihre Transaktionen zu sehen.</p>';
            }

            $user_id = get_current_user_id();
            $zoho_contact_id = get_user_meta($user_id, 'zoho_id', true);

            if (empty($zoho_contact_id)) {
                return '<p>Keine Zoho-ID für Ihren Benutzer hinterlegt.</p>';
            }

            $finance_id = $this->get_finance_id($zoho_contact_id);

            if (!$finance_id) {
                return '<p>Keine Finance ID gefunden.</p>';
            }

            $transactions = $this->get_transactions($finance_id);

            if (!$transactions || empty($transactions)) {
                return '<p>Keine Transaktionen gefunden.</p>';
            }

            // Filter out void, draft, and approved transactions
            $transactions = array_filter($transactions, function($t) {
                $status = strtolower($t['status']);
                return $status !== 'void' && $status !== 'draft' && $status !== 'approved';
            });

            if (empty($transactions)) {
                return '<p>Keine Transaktionen gefunden.</p>';
            }

            // Sort by date (newest first)
            usort($transactions, function($a, $b) {
                return strtotime($b['date']) - strtotime($a['date']);
            });

            // Get unique years
            $years = array_unique(array_map(function($t) {
                return date('Y', strtotime($t['date']));
            }, $transactions));
            rsort($years);

            // Get current year
            $current_year = date('Y');

            ob_start();
            ?>
            <div class="dgptm-zoho-books-wrapper">
                <div class="zoho-books-filters">
                    <label for="year-filter">Jahr filtern:</label>
                    <select id="year-filter">
                        <option value="">Alle Jahre</option>
                        <?php foreach ($years as $year): ?>
                            <option value="<?php echo esc_attr($year); ?>" <?php selected($year, $current_year); ?>>
                                <?php echo esc_html($year); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <table class="zoho-books-transactions" id="transactions-table">
                    <thead>
                        <tr>
                            <th class="sortable" data-sort="type">Typ</th>
                            <th class="sortable" data-sort="number">Nummer</th>
                            <th class="sortable" data-sort="date">Datum</th>
                            <th class="sortable" data-sort="total">Betrag</th>
                            <th class="sortable" data-sort="balance">Offen</th>
                            <th class="sortable" data-sort="status">Status</th>
                            <th>Aktion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($transactions as $transaction): ?>
                            <?php
                            $year = date('Y', strtotime($transaction['date']));
                            $type_label = $transaction['type'] === 'invoice' ? 'Rechnung' : 'Gutschrift';
                            $type_class = $transaction['type'] === 'invoice' ? 'invoice' : 'creditnote';

                            // Mark unpaid invoices
                            $status_lower = strtolower($transaction['status']);
                            $is_unpaid = $transaction['type'] === 'invoice' &&
                                        ($status_lower === 'unpaid' || $status_lower === 'overdue' || $status_lower === 'sent' || $status_lower === 'viewed');
                            $row_class = 'transaction-' . $type_class;
                            if ($is_unpaid) {
                                $row_class .= ' unpaid-invoice';
                            }

                            // Translate status
                            $translated_status = $this->translate_status($transaction['status'], $transaction['type']);
                            ?>
                            <tr data-year="<?php echo esc_attr($year); ?>" class="<?php echo esc_attr($row_class); ?>">
                                <td data-sort-value="<?php echo esc_attr($transaction['type']); ?>">
                                    <span class="transaction-type <?php echo esc_attr($type_class); ?>">
                                        <?php echo esc_html($type_label); ?>
                                    </span>
                                </td>
                                <td data-sort-value="<?php echo esc_attr($transaction['number']); ?>">
                                    <?php echo esc_html($transaction['number']); ?>
                                </td>
                                <td data-sort-value="<?php echo strtotime($transaction['date']); ?>">
                                    <?php echo esc_html(date('d.m.Y', strtotime($transaction['date']))); ?>
                                </td>
                                <td data-sort-value="<?php echo esc_attr($transaction['total']); ?>">
                                    <?php echo esc_html(number_format($transaction['total'], 2, ',', '.') . ' ' . $transaction['currency_code']); ?>
                                </td>
                                <td data-sort-value="<?php echo esc_attr($transaction['balance']); ?>" class="<?php echo $transaction['balance'] > 0 ? 'balance-outstanding' : ''; ?>">
                                    <?php echo esc_html(number_format($transaction['balance'], 2, ',', '.') . ' ' . $transaction['currency_code']); ?>
                                </td>
                                <td data-sort-value="<?php echo esc_attr($transaction['status']); ?>">
                                    <span class="transaction-status status-<?php echo esc_attr(strtolower($transaction['status'])); ?>">
                                        <?php echo esc_html($translated_status); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if (!empty($transaction['invoice_url'])): ?>
                                        <a href="<?php echo esc_url($transaction['invoice_url']); ?>"
                                           target="_blank"
                                           class="button-view-invoice">
                                            Ansehen
                                        </a>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
            return ob_get_clean();
        }

        /**
         * Shortcode: Outstanding balance banner
         */
        public function shortcode_outstanding_banner($atts) {
            if (!is_user_logged_in()) {
                return '';
            }

            $user_id = get_current_user_id();
            $zoho_contact_id = get_user_meta($user_id, 'zoho_id', true);

            if (empty($zoho_contact_id)) {
                return '';
            }

            $finance_id = $this->get_finance_id($zoho_contact_id);

            if (!$finance_id) {
                return '';
            }

            $transactions = $this->get_transactions($finance_id);

            if (!$transactions || empty($transactions)) {
                return '';
            }

            // Filter out void, draft, and approved transactions
            $transactions = array_filter($transactions, function($t) {
                $status = strtolower($t['status']);
                return $status !== 'void' && $status !== 'draft' && $status !== 'approved';
            });

            // Calculate total overdue balance (only overdue invoices)
            $total_overdue = 0;
            $overdue_invoices = [];

            foreach ($transactions as $transaction) {
                // Only show overdue invoices (not just unpaid)
                $status = strtolower($transaction['status']);
                if ($status === 'overdue' && $transaction['balance'] > 0 && $transaction['type'] === 'invoice') {
                    $total_overdue += $transaction['balance'];
                    $overdue_invoices[] = $transaction;
                }
            }

            if ($total_overdue <= 0 || empty($overdue_invoices)) {
                return '';
            }

            // Get currency from first transaction
            $currency = !empty($overdue_invoices) ? $overdue_invoices[0]['currency_code'] : 'EUR';

            ob_start();
            ?>
            <div class="dgptm-zoho-books-outstanding-banner">
                <div class="banner-content">
                    <div class="banner-header">
                        <span class="banner-icon">⚠️</span>
                        <span class="banner-text">
                            <strong>Überfällige Rechnungen:</strong>
                            <span class="banner-amount"><?php echo esc_html(number_format($total_overdue, 2, ',', '.') . ' ' . $currency); ?></span>
                        </span>
                    </div>
                    <div class="banner-invoices">
                        <?php foreach ($overdue_invoices as $invoice): ?>
                            <div class="outstanding-invoice-item">
                                <div class="invoice-info">
                                    <span class="invoice-number"><?php echo esc_html($invoice['number']); ?></span>
                                    <span class="invoice-amount"><?php echo esc_html(number_format($invoice['balance'], 2, ',', '.') . ' ' . $invoice['currency_code']); ?></span>
                                </div>
                                <?php if (!empty($invoice['invoice_url'])): ?>
                                    <a href="<?php echo esc_url($invoice['invoice_url']); ?>"
                                       target="_blank"
                                       class="invoice-button">
                                        Rechnung ansehen
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php
            return ob_get_clean();
        }
    }
}

// Prevent double initialization
if (!isset($GLOBALS['dgptm_zoho_books_initialized'])) {
    $GLOBALS['dgptm_zoho_books_initialized'] = true;
    DGPTM_Zoho_Books::get_instance();
}
