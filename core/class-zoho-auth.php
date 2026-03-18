<?php
/**
 * Zentrale Zoho OAuth2-Authentifizierung
 *
 * Einziger Ort fuer Zoho-Credentials (Client ID, Secret, Refresh Token).
 * Module registrieren benoetigte Scopes per Filter.
 * Rueckwaertskompatibel mit alten dgptm_zoho_* Options.
 *
 * @package DGPTM_Suite
 * @since 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * DGPTM Zoho Auth
 */
final class DGPTM_Zoho_Auth {

	/**
	 * Singleton
	 *
	 * @var DGPTM_Zoho_Auth|null
	 */
	private static $instance = null;

	/**
	 * Option key fuer zentrale Credentials
	 */
	const OPT_KEY = 'dgptm_zoho_auth';

	/**
	 * Transient key fuer Access Token
	 */
	const TOKEN_TRANSIENT = 'dgptm_zoho_access_token_central';

	/**
	 * REST namespace
	 */
	const REST_NAMESPACE = 'dgptm/v1';

	/**
	 * Datacenter-Mapping
	 *
	 * @var array
	 */
	private $datacenters = [
		'EU' => [ 'accounts' => 'https://accounts.zoho.eu', 'api' => 'https://www.zohoapis.eu' ],
		'US' => [ 'accounts' => 'https://accounts.zoho.com', 'api' => 'https://www.zohoapis.com' ],
		'IN' => [ 'accounts' => 'https://accounts.zoho.in', 'api' => 'https://www.zohoapis.in' ],
		'AU' => [ 'accounts' => 'https://accounts.zoho.com.au', 'api' => 'https://www.zohoapis.com.au' ],
		'JP' => [ 'accounts' => 'https://accounts.zoho.jp', 'api' => 'https://www.zohoapis.jp' ],
		'CA' => [ 'accounts' => 'https://accounts.zohocloud.ca', 'api' => 'https://www.zohoapis.ca' ],
	];

	/**
	 * Cached settings
	 *
	 * @var array|null
	 */
	private $settings = null;

	/**
	 * Cached access token
	 *
	 * @var string|false
	 */
	private $access_token = false;

	/**
	 * Get singleton instance
	 *
	 * @return DGPTM_Zoho_Auth
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_init', [ $this, 'handle_oauth_callback' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

		// Migrate existing credentials on first load
		$this->maybe_migrate();
	}

	/* =========================================================================
	 * Public API
	 * ======================================================================= */

	/**
	 * Check if Zoho is connected (has refresh token)
	 *
	 * @return bool
	 */
	public function is_connected() {
		$s = $this->get_settings();
		return ! empty( $s['refresh_token'] ) && ! empty( $s['client_id'] ) && ! empty( $s['client_secret'] );
	}

	/**
	 * Get a valid access token (refreshes automatically)
	 *
	 * @return string|false
	 */
	public function get_access_token() {
		if ( $this->access_token ) {
			return $this->access_token;
		}

		$token = get_transient( self::TOKEN_TRANSIENT );
		if ( $token ) {
			$this->access_token = $token;
			return $token;
		}

		$this->access_token = $this->refresh_access_token();
		return $this->access_token;
	}

	/**
	 * Get accounts URL for current datacenter
	 *
	 * @return string
	 */
	public function get_accounts_url() {
		$s  = $this->get_settings();
		$dc = ! empty( $s['dc'] ) ? $s['dc'] : 'EU';
		return isset( $this->datacenters[ $dc ] ) ? $this->datacenters[ $dc ]['accounts'] : $this->datacenters['EU']['accounts'];
	}

	/**
	 * Get API domain for current datacenter
	 *
	 * @return string
	 */
	public function get_api_domain() {
		$s  = $this->get_settings();
		$dc = ! empty( $s['dc'] ) ? $s['dc'] : 'EU';
		return isset( $this->datacenters[ $dc ] ) ? $this->datacenters[ $dc ]['api'] : $this->datacenters['EU']['api'];
	}

	/**
	 * Get client ID
	 *
	 * @return string
	 */
	public function get_client_id() {
		$s = $this->get_settings();
		return $s['client_id'] ?? '';
	}

	/**
	 * Get client secret
	 *
	 * @return string
	 */
	public function get_client_secret() {
		$s = $this->get_settings();
		return $s['client_secret'] ?? '';
	}

	/**
	 * Get refresh token
	 *
	 * @return string
	 */
	public function get_refresh_token() {
		$s = $this->get_settings();
		return $s['refresh_token'] ?? '';
	}

	/**
	 * Make an API request with automatic token handling and 401 retry
	 *
	 * @param string $method HTTP method.
	 * @param string $url    Full API URL.
	 * @param array  $body   Request body (JSON-encoded for POST/PUT).
	 * @param bool   $retry  Whether this is a retry after 401.
	 * @return array ['code' => int, 'body' => array] or WP_Error.
	 */
	public function request( $method, $url, $body = [], $retry = false ) {
		$token = $this->get_access_token();
		if ( ! $token ) {
			return new WP_Error( 'no_token', 'Kein Zoho Access-Token verfuegbar.' );
		}

		$args = [
			'method'  => $method,
			'timeout' => 30,
			'headers' => [
				'Authorization' => 'Zoho-oauthtoken ' . $token,
				'Content-Type'  => 'application/json',
			],
		];

		if ( ! empty( $body ) && in_array( $method, [ 'POST', 'PUT' ], true ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		// 401 retry: force token refresh and try once more
		if ( 401 === $code && ! $retry ) {
			delete_transient( self::TOKEN_TRANSIENT );
			$this->access_token = false;
			return $this->request( $method, $url, $body, true );
		}

		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		return [
			'code' => $code,
			'body' => is_array( $response_body ) ? $response_body : [],
		];
	}

	/**
	 * Get all required scopes (collected from active modules via filter)
	 *
	 * @return array Associative: [ 'module_name' => ['scope1', 'scope2'], ... ]
	 */
	public function get_required_scopes() {
		return apply_filters( 'dgptm_zoho_required_scopes', [] );
	}

	/**
	 * Get flat list of all unique scopes
	 *
	 * @return array
	 */
	public function get_all_scopes() {
		$grouped = $this->get_required_scopes();
		$all     = [];
		foreach ( $grouped as $scopes ) {
			if ( is_array( $scopes ) ) {
				$all = array_merge( $all, $scopes );
			}
		}
		return array_unique( $all );
	}

	/* =========================================================================
	 * Token Management
	 * ======================================================================= */

	/**
	 * Refresh access token via OAuth2
	 *
	 * @return string|false
	 */
	private function refresh_access_token() {
		// Prevent concurrent refresh requests (race condition)
		$lock_key = 'dgptm_zoho_token_refresh_lock';
		if ( get_transient( $lock_key ) ) {
			// Another process is refreshing; wait briefly and check for new token
			usleep( 500000 ); // 0.5s
			$token = get_transient( self::TOKEN_TRANSIENT );
			return $token ?: false;
		}
		set_transient( $lock_key, 1, 30 );

		$s = $this->get_settings();

		if ( empty( $s['client_id'] ) || empty( $s['client_secret'] ) || empty( $s['refresh_token'] ) ) {
			delete_transient( $lock_key );
			return false;
		}

		$accounts_url = $this->get_accounts_url();

		$response = wp_remote_post(
			$accounts_url . '/oauth/v2/token',
			[
				'timeout' => 15,
				'body'    => [
					'grant_type'    => 'refresh_token',
					'client_id'     => $s['client_id'],
					'client_secret' => $s['client_secret'],
					'refresh_token' => $s['refresh_token'],
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			delete_transient( $lock_key );
			$this->log( 'Token-Refresh fehlgeschlagen: ' . $response->get_error_message(), 'error' );
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['access_token'] ) ) {
			delete_transient( $lock_key );
			$error = isset( $body['error'] ) ? $body['error'] : 'unknown';
			$this->log( 'Token-Refresh Fehler: ' . $error, 'error' );
			return false;
		}

		$token      = $body['access_token'];
		$expires_in = isset( $body['expires_in'] ) ? (int) $body['expires_in'] : 3600;

		// Cache with 5-minute buffer
		set_transient( self::TOKEN_TRANSIENT, $token, max( $expires_in - 300, 60 ) );

		// Sync to legacy option for backward compatibility
		$this->sync_legacy_options( 'token', $token, $expires_in );

		delete_transient( $lock_key );
		$this->log( 'Access-Token erfolgreich erneuert', 'info' );

		return $token;
	}

	/* =========================================================================
	 * Settings
	 * ======================================================================= */

	/**
	 * Get settings with defaults
	 *
	 * @return array
	 */
	public function get_settings() {
		if ( null !== $this->settings ) {
			return $this->settings;
		}

		$defaults = [
			'dc'            => 'EU',
			'client_id'     => '',
			'client_secret' => '',
			'refresh_token' => '',
			'api_domain'    => '',
			'connected_at'  => 0,
		];

		$this->settings = wp_parse_args( get_option( self::OPT_KEY, [] ), $defaults );
		return $this->settings;
	}

	/**
	 * Update settings
	 *
	 * @param array $new_settings Settings to merge.
	 */
	private function update_settings( $new_settings ) {
		$current        = $this->get_settings();
		$merged         = array_merge( $current, $new_settings );
		$this->settings = $merged;
		update_option( self::OPT_KEY, $merged, false );

		// Sync to legacy options
		$this->sync_legacy_options( 'credentials', null, null );
	}

	/* =========================================================================
	 * Legacy Backward Compatibility
	 * ======================================================================= */

	/**
	 * Migrate existing credentials from old options to central storage
	 */
	private function maybe_migrate() {
		$existing = get_option( self::OPT_KEY );
		if ( false !== $existing && ! empty( $existing['client_id'] ) ) {
			return; // Already migrated
		}

		$old_client_id     = get_option( 'dgptm_zoho_client_id', '' );
		$old_client_secret = get_option( 'dgptm_zoho_client_secret', '' );
		$old_refresh_token = get_option( 'dgptm_zoho_refresh_token', '' );

		if ( $old_client_id || $old_client_secret || $old_refresh_token ) {
			$this->settings = null; // Reset cache
			$this->update_settings( [
				'dc'            => 'EU',
				'client_id'     => $old_client_id,
				'client_secret' => $old_client_secret,
				'refresh_token' => $old_refresh_token,
				'connected_at'  => $old_refresh_token ? time() : 0,
			] );
			$this->log( 'Bestehende Zoho-Credentials in zentrale Verwaltung migriert', 'info' );
		}
	}

	/**
	 * Sync central credentials to legacy option keys
	 *
	 * @param string      $type       'credentials' or 'token'.
	 * @param string|null $token      Access token (for type=token).
	 * @param int|null    $expires_in Expires in seconds (for type=token).
	 */
	private function sync_legacy_options( $type, $token = null, $expires_in = null ) {
		if ( 'credentials' === $type ) {
			$s = $this->get_settings();
			update_option( 'dgptm_zoho_client_id', $s['client_id'] );
			update_option( 'dgptm_zoho_client_secret', $s['client_secret'] );
			update_option( 'dgptm_zoho_refresh_token', $s['refresh_token'] );
		}

		if ( 'token' === $type && $token ) {
			update_option( 'dgptm_zoho_access_token', $token );
			if ( $expires_in ) {
				// Same 5-minute buffer as central transient
				update_option( 'dgptm_zoho_token_expires', time() + $expires_in - 300 );
			}
		}
	}

	/* =========================================================================
	 * Admin Menu & Settings Page
	 * ======================================================================= */

	/**
	 * Add admin menu
	 */
	public function add_admin_menu() {
		add_options_page(
			'Zoho Verbindungen',
			'Zoho Verbindungen',
			'manage_options',
			'dgptm-zoho-auth',
			[ $this, 'render_admin_page' ]
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting( 'dgptm_zoho_auth_group', self::OPT_KEY, [
			'type'              => 'array',
			'sanitize_callback' => [ $this, 'sanitize_settings' ],
		] );
	}

	/**
	 * Sanitize settings input
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$current = $this->get_settings();

		$sanitized = [
			'dc'            => isset( $input['dc'] ) && isset( $this->datacenters[ $input['dc'] ] ) ? $input['dc'] : $current['dc'],
			'client_id'     => isset( $input['client_id'] ) ? sanitize_text_field( $input['client_id'] ) : $current['client_id'],
			'client_secret' => isset( $input['client_secret'] ) ? sanitize_text_field( $input['client_secret'] ) : $current['client_secret'],
			'refresh_token' => $current['refresh_token'], // Only set via OAuth callback
			'api_domain'    => $current['api_domain'],
			'connected_at'  => $current['connected_at'],
		];

		// Sync to legacy
		$this->settings = $sanitized;
		$this->sync_legacy_options( 'credentials' );

		return $sanitized;
	}

	/**
	 * Render admin page
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$s         = $this->get_settings();
		$connected = $this->is_connected();
		$scopes    = $this->get_required_scopes();
		$all_scopes = $this->get_all_scopes();

		// Build OAuth authorize URL
		$callback_url = admin_url( 'options-general.php?page=dgptm-zoho-auth' );
		$state        = wp_create_nonce( 'dgptm_zoho_oauth' );
		$scope_str    = implode( ',', $all_scopes );
		$accounts_url = $this->get_accounts_url();

		$auth_url = add_query_arg( [
			'scope'         => $scope_str,
			'client_id'     => $s['client_id'],
			'response_type' => 'code',
			'access_type'   => 'offline',
			'prompt'        => 'consent',
			'redirect_uri'  => $callback_url,
			'state'         => $state,
		], $accounts_url . '/oauth/v2/auth' );

		// Group scopes by Zoho product
		$scope_groups = $this->group_scopes_by_product( $all_scopes );

		$this->render_admin_html( $s, $connected, $scopes, $scope_groups, $auth_url, $callback_url );
	}

	/**
	 * Render admin page HTML
	 */
	private function render_admin_html( $s, $connected, $module_scopes, $scope_groups, $auth_url, $callback_url ) {
		?>
		<div class="wrap">
			<h1>Zoho Verbindungen</h1>

			<?php settings_errors(); ?>

			<!-- Connection Status -->
			<div class="dgptm-zoho-status" style="background:<?php echo $connected ? '#ecfdf5' : '#fef2f2'; ?>;border:1px solid <?php echo $connected ? '#86efac' : '#fca5a5'; ?>;border-radius:8px;padding:16px 20px;margin:16px 0;display:flex;align-items:center;gap:12px;">
				<span style="font-size:1.5em;"><?php echo $connected ? '&#9679;' : '&#9675;'; ?></span>
				<div>
					<strong style="color:<?php echo $connected ? '#166534' : '#991b1b'; ?>;">
						<?php echo $connected ? 'Verbunden' : 'Nicht verbunden'; ?>
					</strong>
					<?php if ( $connected && $s['connected_at'] ) : ?>
						<br><span style="font-size:0.85em;color:#64748b;">
							Verbunden seit <?php echo esc_html( wp_date( 'd.m.Y H:i', $s['connected_at'] ) ); ?>
							&middot; Datacenter: <?php echo esc_html( $s['dc'] ); ?>
						</span>
					<?php endif; ?>
				</div>
			</div>

			<!-- Settings Form -->
			<form method="post" action="options.php">
				<?php settings_fields( 'dgptm_zoho_auth_group' ); ?>

				<h2>Zugangsdaten</h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="dc">Datacenter</label></th>
						<td>
							<select name="<?php echo self::OPT_KEY; ?>[dc]" id="dc">
								<?php foreach ( $this->datacenters as $code => $urls ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $s['dc'], $code ); ?>>
										<?php echo esc_html( $code . ' – ' . $urls['accounts'] ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="client_id">Client ID</label></th>
						<td><input type="text" class="regular-text" id="client_id" name="<?php echo self::OPT_KEY; ?>[client_id]" value="<?php echo esc_attr( $s['client_id'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="client_secret">Client Secret</label></th>
						<td><input type="password" class="regular-text" id="client_secret" name="<?php echo self::OPT_KEY; ?>[client_secret]" value="<?php echo esc_attr( $s['client_secret'] ); ?>"></td>
					</tr>
				</table>

				<?php submit_button( 'Zugangsdaten speichern' ); ?>
			</form>

			<!-- Redirect URI -->
			<h2>Redirect-URI</h2>
			<p>Diese URI muss in der Zoho API Console als Redirect-URI hinterlegt sein:</p>
			<p><code style="background:#f1f5f9;padding:6px 12px;border-radius:4px;font-size:0.9em;"><?php echo esc_html( $callback_url ); ?></code></p>

			<!-- Required Scopes -->
			<h2>Benoetigte Scopes</h2>
			<?php if ( empty( $module_scopes ) ) : ?>
				<p style="color:#64748b;">Keine Module haben Scopes registriert. Aktiviere Module mit Zoho-Integration.</p>
			<?php else : ?>
				<table class="widefat striped" style="max-width:800px;">
					<thead>
						<tr><th>Modul</th><th>Scopes</th></tr>
					</thead>
					<tbody>
					<?php foreach ( $module_scopes as $module => $scopes_list ) : ?>
						<?php if ( is_array( $scopes_list ) ) : ?>
						<tr>
							<td><strong><?php echo esc_html( $module ); ?></strong></td>
							<td><code style="font-size:0.8em;"><?php echo esc_html( implode( ', ', $scopes_list ) ); ?></code></td>
						</tr>
						<?php endif; ?>
					<?php endforeach; ?>
					</tbody>
				</table>

				<h3 style="margin-top:20px;">Zusammengefasst nach Produkt</h3>
				<?php foreach ( $scope_groups as $product => $product_scopes ) : ?>
					<details style="margin-bottom:8px;" <?php echo $product === 'Zoho CRM' ? 'open' : ''; ?>>
						<summary style="cursor:pointer;font-weight:600;padding:6px 0;">
							<?php echo esc_html( $product ); ?>
							<span style="font-weight:400;color:#64748b;font-size:0.85em;">(<?php echo count( $product_scopes ); ?> Scopes)</span>
						</summary>
						<div style="padding:8px 0 8px 20px;">
							<?php foreach ( $product_scopes as $scope ) : ?>
								<code style="display:block;font-size:0.8em;padding:2px 0;"><?php echo esc_html( $scope ); ?></code>
							<?php endforeach; ?>
						</div>
					</details>
				<?php endforeach; ?>
			<?php endif; ?>

			<!-- Authorization -->
			<h2>Autorisierung</h2>
			<?php if ( empty( $s['client_id'] ) || empty( $s['client_secret'] ) ) : ?>
				<p style="color:#991b1b;">Bitte zuerst Client ID und Client Secret speichern.</p>
			<?php elseif ( empty( $module_scopes ) ) : ?>
				<p style="color:#92400e;">Keine Scopes registriert. Aktiviere Module mit Zoho-Integration.</p>
			<?php else : ?>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $auth_url ); ?>">
						<?php echo $connected ? 'Erneut autorisieren' : 'Mit Zoho verbinden'; ?>
					</a>
				</p>
				<p class="description">Oeffnet Zoho-Login. Nach Zustimmung wirst du hierher zurueckgeleitet.</p>
			<?php endif; ?>

			<!-- Disconnect -->
			<?php if ( $connected ) : ?>
				<h2>Verbindung trennen</h2>
				<form method="post">
					<?php wp_nonce_field( 'dgptm_zoho_disconnect' ); ?>
					<p>
						<button type="submit" name="dgptm_zoho_disconnect" value="1" class="button button-link-delete" onclick="return confirm('Zoho-Verbindung wirklich trennen? Alle Module verlieren den Zugriff.');">
							Verbindung trennen
						</button>
					</p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/* =========================================================================
	 * OAuth Callback
	 * ======================================================================= */

	/**
	 * Handle OAuth callback (authorization code exchange)
	 */
	public function handle_oauth_callback() {
		if ( ! is_admin() || ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Handle disconnect
		if ( isset( $_POST['dgptm_zoho_disconnect'] ) && wp_verify_nonce( $_POST['_wpnonce'] ?? '', 'dgptm_zoho_disconnect' ) ) {
			$this->update_settings( [
				'refresh_token' => '',
				'api_domain'    => '',
				'connected_at'  => 0,
			] );
			delete_transient( self::TOKEN_TRANSIENT );
			$this->access_token = false;

			// Clear legacy options too
			update_option( 'dgptm_zoho_refresh_token', '' );
			update_option( 'dgptm_zoho_access_token', '' );
			delete_option( 'dgptm_zoho_token_expires' );
			delete_transient( 'et_zoho_meeting_access_token' );

			add_settings_error( self::OPT_KEY, 'disconnected', 'Zoho-Verbindung getrennt.', 'updated' );
			return;
		}

		// Handle OAuth code
		if ( empty( $_GET['code'] ) || empty( $_GET['state'] ) ) {
			return;
		}

		// Verify we're on our settings page
		if ( ! isset( $_GET['page'] ) || 'dgptm-zoho-auth' !== $_GET['page'] ) {
			return;
		}

		// Verify state nonce
		if ( ! wp_verify_nonce( sanitize_text_field( $_GET['state'] ), 'dgptm_zoho_oauth' ) ) {
			add_settings_error( self::OPT_KEY, 'invalid_state', 'Ungueltiger OAuth State. Bitte erneut versuchen.', 'error' );
			return;
		}

		$code         = sanitize_text_field( $_GET['code'] );
		$s            = $this->get_settings();
		$accounts_url = $this->get_accounts_url();
		$callback_url = admin_url( 'options-general.php?page=dgptm-zoho-auth' );

		$response = wp_remote_post(
			$accounts_url . '/oauth/v2/token',
			[
				'timeout' => 25,
				'body'    => [
					'grant_type'    => 'authorization_code',
					'client_id'     => $s['client_id'],
					'client_secret' => $s['client_secret'],
					'redirect_uri'  => $callback_url,
					'code'          => $code,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			add_settings_error( self::OPT_KEY, 'token_error', 'Token-Request fehlgeschlagen: ' . $response->get_error_message(), 'error' );
			return;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['access_token'] ) ) {
			$error = isset( $data['error'] ) ? $data['error'] : 'Unbekannter Fehler';
			add_settings_error( self::OPT_KEY, 'token_error', 'Zoho-Fehler: ' . $error, 'error' );
			return;
		}

		$refresh = ! empty( $data['refresh_token'] ) ? $data['refresh_token'] : $s['refresh_token'];

		$this->update_settings( [
			'refresh_token' => $refresh,
			'api_domain'    => $data['api_domain'] ?? '',
			'connected_at'  => time(),
		] );

		// Cache access token
		$expires_in = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600;
		set_transient( self::TOKEN_TRANSIENT, $data['access_token'], max( $expires_in - 300, 60 ) );
		$this->access_token = $data['access_token'];

		// Sync to legacy
		$this->sync_legacy_options( 'token', $data['access_token'], $expires_in );

		$this->log( 'Zoho OAuth erfolgreich autorisiert', 'info' );
		add_settings_error( self::OPT_KEY, 'connected', 'Zoho erfolgreich verbunden!', 'updated' );
	}

	/* =========================================================================
	 * REST API (alternative callback for REST-based OAuth)
	 * ======================================================================= */

	/**
	 * Register REST routes
	 */
	public function register_rest_routes() {
		register_rest_route( self::REST_NAMESPACE, '/zoho-callback', [
			'methods'             => 'GET',
			'permission_callback' => function() {
				return current_user_can( 'manage_options' );
			},
			'callback'            => [ $this, 'rest_oauth_callback' ],
		] );
	}

	/**
	 * REST OAuth callback handler
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public function rest_oauth_callback( $request ) {
		$code  = sanitize_text_field( $request->get_param( 'code' ) );
		$error = sanitize_text_field( $request->get_param( 'error' ) );

		if ( $error ) {
			return new WP_REST_Response( 'OAuth Fehler: ' . $error, 400 );
		}

		if ( ! $code ) {
			return new WP_REST_Response( 'Kein Authorization Code erhalten.', 400 );
		}

		$s            = $this->get_settings();
		$accounts_url = $this->get_accounts_url();
		$redirect_uri = get_rest_url( null, self::REST_NAMESPACE . '/zoho-callback' );

		$response = wp_remote_post(
			$accounts_url . '/oauth/v2/token',
			[
				'timeout' => 25,
				'body'    => [
					'grant_type'    => 'authorization_code',
					'client_id'     => $s['client_id'],
					'client_secret' => $s['client_secret'],
					'redirect_uri'  => $redirect_uri,
					'code'          => $code,
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			return new WP_REST_Response( 'Fehler: ' . $response->get_error_message(), 500 );
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $data['access_token'] ) ) {
			return new WP_REST_Response( 'Token-Fehler: ' . wp_json_encode( $data ), 500 );
		}

		$refresh = ! empty( $data['refresh_token'] ) ? $data['refresh_token'] : $s['refresh_token'];

		$this->update_settings( [
			'refresh_token' => $refresh,
			'api_domain'    => $data['api_domain'] ?? '',
			'connected_at'  => time(),
		] );

		$expires_in = isset( $data['expires_in'] ) ? (int) $data['expires_in'] : 3600;
		set_transient( self::TOKEN_TRANSIENT, $data['access_token'], max( $expires_in - 300, 60 ) );
		$this->sync_legacy_options( 'token', $data['access_token'], $expires_in );

		wp_redirect( admin_url( 'options-general.php?page=dgptm-zoho-auth&connected=1' ) );
		exit;
	}

	/* =========================================================================
	 * Helpers
	 * ======================================================================= */

	/**
	 * Group scopes by Zoho product name
	 *
	 * @param array $scopes Flat scope list.
	 * @return array Grouped by product.
	 */
	private function group_scopes_by_product( $scopes ) {
		$groups = [];
		foreach ( $scopes as $scope ) {
			if ( stripos( $scope, 'ZohoCRM' ) === 0 ) {
				$groups['Zoho CRM'][] = $scope;
			} elseif ( stripos( $scope, 'ZohoMeeting' ) === 0 ) {
				$groups['Zoho Meeting'][] = $scope;
			} elseif ( stripos( $scope, 'ZohoBooks' ) === 0 || stripos( $scope, 'zohobooks' ) === 0 ) {
				$groups['Zoho Books'][] = $scope;
			} elseif ( stripos( $scope, 'zohobackstage' ) === 0 ) {
				$groups['Zoho Backstage'][] = $scope;
			} elseif ( stripos( $scope, 'ZohoMarketingAutomation' ) === 0 ) {
				$groups['Zoho Marketing'][] = $scope;
			} else {
				$groups['Sonstige'][] = $scope;
			}
		}
		return $groups;
	}

	/**
	 * Log message via DGPTM Logger if available
	 *
	 * @param string $message Message.
	 * @param string $level   Level (info, error, warning).
	 */
	private function log( $message, $level = 'info' ) {
		if ( class_exists( 'DGPTM_Logger' ) && method_exists( 'DGPTM_Logger', $level ) ) {
			DGPTM_Logger::$level( 'Zoho Auth: ' . $message );
		}
	}
}

/**
 * Global accessor function
 *
 * @return DGPTM_Zoho_Auth
 */
function dgptm_zoho_auth() {
	return DGPTM_Zoho_Auth::get_instance();
}
