<?php
/**
 * Event Tracker Settings
 *
 * @package EventTracker\Admin
 * @since 2.0.0
 */

namespace EventTracker\Admin;

use EventTracker\Core\Constants;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings Class
 */
class Settings {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'add_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Add settings page
	 */
	public function add_settings_page() {
		add_options_page(
			__( 'Event Tracker', 'event-tracker' ),
			__( 'Event Tracker', 'event-tracker' ),
			'manage_options',
			'event-tracker-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting( 'et_settings_group', Constants::OPT_KEY, [ $this, 'sanitize_settings' ] );

		add_settings_section(
			'et_section_main',
			__( 'Allgemein', 'event-tracker' ),
			function () {
				echo '<p>' . esc_html__( 'Webhook-URL und Meldungen konfigurieren.', 'event-tracker' ) . '</p>';
			},
			'event-tracker-settings'
		);

		add_settings_field(
			'webhook_url',
			__( 'Webhook-URL (Redirect/Tracking)', 'event-tracker' ),
			[ $this, 'field_webhook' ],
			'event-tracker-settings',
			'et_section_main'
		);

		add_settings_field(
			'error_message',
			__( 'Fehlermeldung (Template)', 'event-tracker' ),
			[ $this, 'field_error_message' ],
			'event-tracker-settings',
			'et_section_main'
		);

		add_settings_field(
			'recording_alt_text',
			__( 'Text nach Veranstaltungsende (ohne Aufzeichnung)', 'event-tracker' ),
			[ $this, 'field_recording_alt_text' ],
			'event-tracker-settings',
			'et_section_main'
		);

		add_settings_section(
			'et_section_mail',
			__( 'Mail-Webhook', 'event-tracker' ),
			function () {
				echo '<p>' . esc_html__( 'Ziel-Endpoint für den Mailversand per Webhook (JSON).', 'event-tracker' ) . '</p>';
			},
			'event-tracker-settings'
		);

		add_settings_field(
			'mail_webhook_url',
			__( 'Mail Webhook-URL (JSON)', 'event-tracker' ),
			[ $this, 'field_mail_webhook' ],
			'event-tracker-settings',
			'et_section_mail'
		);
	}

	/**
	 * Sanitize settings
	 *
	 * @param array $input Input values.
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$out = get_option( Constants::OPT_KEY, [] );

		$out['webhook_url'] = '';
		if ( ! empty( $input['webhook_url'] ) ) {
			$url = esc_url_raw( $input['webhook_url'], [ 'http', 'https' ] );
			if ( $url ) {
				$out['webhook_url'] = $url;
			}
		}

		$default_msg            = 'Dieses Event ist derzeit nicht aktiv. {name} ist gültig von {from} bis {to}. {countdown}';
		$out['error_message']   = isset( $input['error_message'] ) && is_string( $input['error_message'] )
			? wp_kses_post( $input['error_message'] )
			: $default_msg;

		$out['recording_alt_text'] = isset( $input['recording_alt_text'] ) && is_string( $input['recording_alt_text'] )
			? sanitize_text_field( $input['recording_alt_text'] )
			: 'Aufzeichnung bald verfügbar.';

		$out['mail_webhook_url'] = '';
		if ( ! empty( $input['mail_webhook_url'] ) ) {
			$url2 = esc_url_raw( $input['mail_webhook_url'], [ 'http', 'https' ] );
			if ( $url2 ) {
				$out['mail_webhook_url'] = $url2;
			}
		}

		return $out;
	}

	/**
	 * Webhook field
	 */
	public function field_webhook() {
		$opts = get_option( Constants::OPT_KEY, [] );
		$val  = isset( $opts['webhook_url'] ) ? $opts['webhook_url'] : '';
		echo '<input type="url" class="regular-text" name="' . esc_attr( Constants::OPT_KEY ) . '[webhook_url]" value="' . esc_attr( $val ) . '" placeholder="https://example.com/webhook" />';
		echo '<p class="description">' . esc_html__( 'Nur bei Gültigkeit wird an diese URL (per GET) getrackt. Es werden alle Query-Parameter plus event_id und zoho_id übertragen.', 'event-tracker' ) . '</p>';
	}

	/**
	 * Error message field
	 */
	public function field_error_message() {
		$opts = get_option( Constants::OPT_KEY, [] );
		$val  = isset( $opts['error_message'] ) ? $opts['error_message'] : 'Dieses Event ist derzeit nicht aktiv. {name} ist gültig von {from} bis {to}. {countdown}';
		echo '<textarea name="' . esc_attr( Constants::OPT_KEY ) . '[error_message]" rows="3" class="large-text">' . esc_textarea( $val ) . '</textarea>';
		echo '<p class="description">' . esc_html__( 'Platzhalter: {name}, {from}, {to}, {countdown}', 'event-tracker' ) . '</p>';
	}

	/**
	 * Recording alt text field
	 */
	public function field_recording_alt_text() {
		$opts = get_option( Constants::OPT_KEY, [] );
		$val  = isset( $opts['recording_alt_text'] ) ? $opts['recording_alt_text'] : 'Aufzeichnung bald verfügbar.';
		echo '<input type="text" class="regular-text" name="' . esc_attr( Constants::OPT_KEY ) . '[recording_alt_text]" value="' . esc_attr( $val ) . '" />';
		echo '<p class="description">' . esc_html__( 'Angezeigt, wenn Event beendet ist aber keine Aufzeichnungs-URL hinterlegt ist.', 'event-tracker' ) . '</p>';
	}

	/**
	 * Mail webhook field
	 */
	public function field_mail_webhook() {
		$opts = get_option( Constants::OPT_KEY, [] );
		$val  = isset( $opts['mail_webhook_url'] ) ? $opts['mail_webhook_url'] : '';
		echo '<input type="url" class="regular-text" name="' . esc_attr( Constants::OPT_KEY ) . '[mail_webhook_url]" value="' . esc_attr( $val ) . '" placeholder="https://example.com/send-mail" />';
		echo '<p class="description">' . esc_html__( 'Diese URL erhält per POST (JSON) die Mail-Daten: event_id, zoho_id, subject, html, timestamp.', 'event-tracker' ) . '</p>';
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Event Tracker Einstellungen', 'event-tracker' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'et_settings_group' );
				do_settings_sections( 'event-tracker-settings' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
