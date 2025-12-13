<?php
/**
 * Plugin Name: DGPTM – Online-Abstimmen (+ Zoom-Merge: Korrekte Namen, Debug umschaltbar, Abgleich lokal↔Zoom)
 * Description: Button (AN/AUS, Codes, CSV/PDF/Mails, Zoho-Snapshot) – mit Zoom (S2S): Registrierung bei Grün, Cancel bei Rot, Sofort-Join (optional), Debug-Log/Frontend-Diagnose abschaltbar. Admin: Zoom-Verbindungstest, Registrants-Liste, Abgleich „gültige Benutzer“ (lokal on) ↔ Zoom (approved/pending) inkl. Massen-Registrieren/Canceln. Webhook (Zoom) für Anwesenheitsliste + Live-Status + Shortcode + Präsenz-Scanner.
 * Version:     2.0
 * Author:      Seb
 * Text Domain: dgptm-online-abstimmen
 */

if ( ! defined( 'ABSPATH' ) ) { exit; }

final class DGPTM_Online_Abstimmen {

	/** Optionen / Metas / Nonces */
	const OPT_KEY                = 'dgptm_vote_settings';
	const OPT_ASSIGNED_MAP       = 'dgptm_vote_assigned_codes';
	const OPT_AUTO_SENT_AT       = 'dgptm_vote_auto_sent_at';
	const OPT_SCHEDULE_TS        = 'dgptm_vote_schedule_ts';

	// Zoom: User-Metas
	const USER_META_ZOOM_JOIN    = 'dgptm_vote_zoom_join_url';
	const USER_META_ZOOM_STATE   = 'dgptm_vote_zoom_reg_status'; // approved|cancelled|pending|denied|''

	const USER_META_STATUS       = 'dgptm_vote_status';         // 'on'|'off'|''
	const USER_META_CODE         = 'dgptm_vote_code';           // 6-stellig
	const USER_META_HAD_ON       = 'dgptm_vote_had_on';         // 1/0
	const USER_META_TS           = 'dgptm_vote_ts';             // Unix TS
	const USER_META_MV_FLAG      = 'mitgliederversammlung';     // true/false

	// Zoho-Snapshots
	const USER_META_ZOHO_MNR     = 'dgptm_vote_zoho_mitgliedsnr';
	const USER_META_ZOHO_ART     = 'dgptm_vote_zoho_mitgliedsart';
	const USER_META_ZOHO_STATUS  = 'dgptm_vote_zoho_status';

	const NONCE_ACTION_TOGGLE    = 'dgptm_vote_toggle';
	const AJAX_ACTION_TOGGLE     = 'dgptm_vote_toggle_ajax';

	// Admin actions
	const ACTION_EXPORT_CSV      = 'dgptm_vote_export_csv';
	const ACTION_EXPORT_PDF      = 'dgptm_vote_export_pdf';
	const ACTION_SEND_BOTH       = 'dgptm_vote_send_both';
	const ACTION_DELETE_USER     = 'dgptm_vote_delete_user';

	// Zoom Admin actions
	const ACTION_ZOOM_PULL       = 'dgptm_vote_zoom_pull';
	const ACTION_ZOOM_STATUS     = 'dgptm_vote_zoom_status';

	// Zoom Massenabgleich
	const ACTION_ZOOM_SYNC       = 'dgptm_vote_zoom_sync'; // do=register_missing|cancel_extras

	// Zoom Log Admin actions
	const ACTION_ZOOM_LOG_CLEAR  = 'dgptm_vote_zoom_log_clear';
	const ACTION_ZOOM_LOG_DL     = 'dgptm_vote_zoom_log_download';

	// Cron
	const CRON_HOOK              = 'dgptm_vote_send_summary';

	// REST (für Zoom-Test & Reg)
	const REST_NS                = 'dgptm-vote/v1';
	const R_ZOOM_TEST            = '/zoom-test';
	const R_ZOOM_REGISTER        = '/zoom-register';

	// Zoom Log Option
	const OPT_ZOOM_LOG           = 'dgptm_vote_zoom_log';
	const ZOOM_LOG_MAX           = 500; // max. gespeicherte Einträge

	/* ===== Attendance / Webhook ===== */
	const REST_NS_ZOOM           = 'dgptm-zoom/v1';
	const R_ZOOM_LIVE            = '/live';
	const R_ZOOM_WEBHOOK         = '/webhook';
	const R_ZOOM_PRESENCE        = '/presence'; // Präsenz-API (vom Scanner aufgerufen)

	const OPT_ZOOM_ATTEN         = 'dgptm_zoom_attendance'; // Autoload = no

	const ACTION_ATTEN_EXPORT_CSV = 'dgptm_attendance_export_csv';
	const ACTION_ATTEN_EXPORT_PDF = 'dgptm_attendance_export_pdf';
	const ACTION_ATTEN_CLEAR      = 'dgptm_attendance_clear';
	const ACTION_ATTEN_DELETE     = 'dgptm_attendance_delete';

	/* ===== Präsenz-Scanner (Option) ===== */
	const OPT_PRESENCE_WEBHOOK   = 'dgptm_presence_webhook_url';

	private static $instance = null;

	public static function instance() {
		return self::$instance ?: ( self::$instance = new self() );
	}

	/** ===== Debug-Log nach wp-content/debug.log ===== */
	private function dbg($label, $data = null): void {
		// nur loggen, wenn WP_DEBUG_LOG aktiv ist
		if (!defined('WP_DEBUG_LOG') || !WP_DEBUG_LOG) return;
		$prefix = '[DGPTM-VOTE] ';
		$line = is_null($data) ? $label : ($label.' :: '. (is_string($data) ? $data : wp_json_encode($data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)));
		// Hard-Limit, damit keine >1MB Zeilen entstehen
		if (strlen($line) > 120000) $line = substr($line, 0, 120000) . ' …(truncated)…';
		error_log($prefix . $line);
	}

	/** Schalter: Aggressiv loggen, wenn Option + WP_DEBUG_LOG */
	private function dbg_enabled(): bool {
		$opts = get_option(self::OPT_KEY, $this->defaults());
		$optOn = !empty($opts['zoom_log_enable']); // Setting im Backend
		$wpLog = (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG);
		return $optOn && $wpLog;
	}

	/** HTTP Debug Tap (WP_HTTP) – zusätzlich Zoom in Admin-Log mitschneiden */
	public function http_debug_tap($response, $context, $class, $args, $url) {
		// debug.log – nur wenn explizit gewünscht
		if ($this->dbg_enabled()) {
			if ($context === 'request') {
				$hdr = isset($args['headers']) ? (array)$args['headers'] : [];
				foreach (['Authorization','authorization'] as $h) { if (isset($hdr[$h])) $hdr[$h] = '***'; }
				$bodyPreview = isset($args['body']) ? (is_string($args['body']) ? $args['body'] : wp_json_encode($args['body'])) : '';
				if (strlen($bodyPreview) > 4000) $bodyPreview = substr($bodyPreview, 0, 4000) . '…';
				$this->dbg('HTTP request', ['method'=>$args['method'] ?? 'GET','url'=>$url,'headers'=>$hdr,'body'=>$bodyPreview]);
			}

			if ($context === 'response') {
				if (is_wp_error($response)) { $this->dbg('HTTP response', ['url'=>$url,'error'=>$response->get_error_message()]); return; }
				$code = (int) wp_remote_retrieve_response_code($response);
				$body = (string) wp_remote_retrieve_body($response);
				$headers = (array) wp_remote_retrieve_headers($response);
				foreach (['set-cookie','Set-Cookie'] as $h) { if (isset($headers[$h])) $headers[$h] = '***'; }
				if (strlen($body) > 4000) $body = substr($body, 0, 4000) . '…';
				$this->dbg('HTTP response', ['url'=>$url,'code'=>$code,'headers'=>$headers,'body'=>$body]);
			}
		}

		// Zusätzlich: Zoom‑Traffic (sanitisiert) in Admin‑Log, falls aktiviert
		$opts = get_option(self::OPT_KEY, $this->defaults());
		if (empty($opts['zoom_log_enable'])) return;
		if (stripos((string)$url, 'zoom.us/') === false) return;

		if ($context === 'request') {
			$hdr = isset($args['headers']) ? (array)$args['headers'] : [];
			foreach (['Authorization','authorization'] as $h) { if (isset($hdr[$h])) $hdr[$h] = '***'; }
			$this->zoom_log_add([
				'phase'=>'http','event'=>'request','method'=>$args['method'] ?? 'GET',
				'path'=>$url, 'request'=>$this->zoom_log_mask(['headers'=>$hdr,'body'=>$args['body'] ?? ''])
			]);
		}
		if ($context === 'response') {
			if (is_wp_error($response)) {
				$this->zoom_log_add(['phase'=>'http','event'=>'response','path'=>$url,'ok'=>false,'code'=>0,'error'=>$response->get_error_message()]);
				return;
			}
			$code = (int) wp_remote_retrieve_response_code($response);
			$body = (string) wp_remote_retrieve_body($response);
			$this->zoom_log_add([
				'phase'=>'http','event'=>'response','path'=>$url,'ok'=>($code>=200&&$code<300),'code'=>$code,
				'response'=>$this->zoom_log_body_trunc($this->zoom_log_mask($body))
			]);
		}
	}

	private function __construct() {
		// Einstellungen / Admin
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_menu', [ $this, 'admin_menu' ] );

		// Admin posts
		add_action( 'admin_post_' . self::ACTION_EXPORT_CSV, [ $this, 'handle_export_csv' ] );
		add_action( 'admin_post_' . self::ACTION_EXPORT_PDF, [ $this, 'handle_export_pdf' ] );
		add_action( 'admin_post_' . self::ACTION_SEND_BOTH,  [ $this, 'handle_send_both' ] );
		add_action( 'admin_post_' . self::ACTION_DELETE_USER, [ $this, 'handle_delete_user' ] );

		add_action( 'admin_post_' . self::ACTION_ZOOM_PULL,   [ $this, 'handle_zoom_pull' ] );
		add_action( 'admin_post_' . self::ACTION_ZOOM_STATUS, [ $this, 'handle_zoom_status' ] );
		add_action( 'admin_post_' . self::ACTION_ZOOM_SYNC,   [ $this, 'handle_zoom_sync' ] );

		add_action( 'admin_post_' . self::ACTION_ZOOM_LOG_CLEAR, [ $this, 'handle_zoom_log_clear' ] );
		add_action( 'admin_post_' . self::ACTION_ZOOM_LOG_DL,    [ $this, 'handle_zoom_log_download' ] );

		// Attendance Admin actions
		add_action( 'admin_post_' . self::ACTION_ATTEN_EXPORT_CSV, [ $this, 'handle_attendance_export_csv' ] );
		add_action( 'admin_post_' . self::ACTION_ATTEN_EXPORT_PDF, [ $this, 'handle_attendance_export_pdf' ] );
		add_action( 'admin_post_' . self::ACTION_ATTEN_CLEAR,      [ $this, 'handle_attendance_clear' ] );
		add_action( 'admin_post_' . self::ACTION_ATTEN_DELETE,     [ $this, 'handle_attendance_delete' ] );

		// Shortcodes
		add_shortcode( 'online_abstimmen_button', [ $this, 'shortcode_button' ] );
		add_shortcode( 'dgptm_presence_table', [ $this, 'shortcode_presence_table' ] );
		add_shortcode( 'online_abstimmen_liste',  [ $this, 'shortcode_list' ] );
		add_shortcode( 'mitgliederversammlung_flag', [ $this, 'shortcode_mv_flag' ] );
		add_shortcode( 'online_abstimmen_code', [ $this, 'shortcode_code' ] );
		add_shortcode( 'online_abstimmen_switch', [ $this, 'shortcode_switch_flag' ] );
		add_shortcode( 'zoom_register_and_join', [ $this, 'shortcode_zoom_register_and_join' ] );
		add_shortcode( 'online_abstimmen_zoom_link', [ $this, 'shortcode_zoom_link' ] );
		add_shortcode( 'zoom_live_state', [ $this, 'shortcode_zoom_live_state' ] );
		add_shortcode( 'dgptm_presence_scanner', [ $this, 'shortcode_presence_scanner' ] );

		// Frontend
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_ajax_' . self::AJAX_ACTION_TOGGLE, [ $this, 'ajax_toggle' ] );
		add_action( 'wp_ajax_nopriv_' . self::AJAX_ACTION_TOGGLE, [ $this, 'ajax_toggle' ] );

		// Cron
		add_action( self::CRON_HOOK, [ $this, 'send_summary_mail' ] );
		add_action( 'init', [ $this, 'failsafe_autosend_if_due' ] );
		add_action( 'update_option_' . self::OPT_KEY, [ $this, 'reschedule_autosend' ], 10, 3 );

		// REST
		add_action( 'rest_api_init', [ $this, 'register_rest' ], 5 );       // vote endpoints
		add_action( 'rest_api_init', [ $this, 'register_rest_zoom' ], 5 );  // zoom endpoints (webhook/live/presence)

		// Globales HTTP-Debug für alle WP-HTTP-Requests (inkl. Zoom)
		add_action('http_api_debug', [ $this, 'http_debug_tap' ], 10, 5);
	}

	/** Defaults */
	private function defaults() {
		return [
			// Texte:
			'button_text_on'        => 'Ich möchte an der Abstimmung online teilnehmen.',
			'button_text_off'       => 'Ich möchte nicht online an der Abstimmung teilnehmen',
			'inactive_text'         => 'Online-Abstimmung ist derzeit nicht möglich.',
			'not_logged_in_text'    => 'Bitte einloggen, um abzustimmen.',
			'not_eligible_text'     => 'Sie sind für diese Abstimmung nicht freigeschaltet.',
			'start_datetime'        => '',
			'end_datetime'          => '',
			'timezone'              => wp_timezone_string(),
			'mail_recipient'        => get_bloginfo( 'admin_email' ),
			'button_role'           => 'mitglied',
			'list_requires_mv'      => 1,

			// Webhook Secret (Zoom)
			'zoom_webhook_secret'   => '',   // Secret Token für Zoom-Webhooks (x-zm-signature)

			// Mail-Templates
			'mail_user_subject_on'  => 'Bestätigung: Online-Teilnahme aktiviert',
			'mail_user_body_on'     => "Hallo {name},\n\ndanke für deine Rückmeldung. Du nimmst online an der Abstimmung teil.\nDein Code: {code}\n\nViele Grüße\n{site}",
			'mail_user_subject_off' => 'Bestätigung: Online-Teilnahme zurückgezogen',
			'mail_user_body_off'    => "Hallo {name},\n\ndu hast deine Online-Teilnahme an der Abstimmung zurückgezogen.\n(Dein Code bleibt dir ggf. zugeordnet: {code})\n\nViele Grüße\n{site}",
			'mail_user_bcc_admin'   => 0,

			/* ===== Zoom-Integration ===== */
			'zoom_enable'            => 0,
			'zoom_kind'              => 'webinar',      // webinar|meeting|auto
			'zoom_meeting_number'    => '',             // 11-stellig
			'zoom_s2s_account_id'    => '',
			'zoom_s2s_client_id'     => '',
			'zoom_s2s_client_secret' => '',
			'zoom_register_on_green' => 1,
			'zoom_cancel_on_red'     => 1,
			'zoom_redirect_on_green' => 'auto',         // auto|app|web|none
			'zoom_log_enable'        => 1,
			'zoom_frontend_debug'    => 0,

			/* ===== Anzeige/Debug ===== */
			'expired_note_text'      => 'Registrierung zur Online-Abstimmung abgelaufen',

			/* ===== Anwesenheit / Webhook ===== */
			'zoom_attendance_enable' => 1,
			'zoom_webhook_token'     => '', // (legacy optional) – Fallback, falls zoom_webhook_secret leer ist

			/* ===== Präsenz-Scanner ===== */
			'presence_webhook_url'   => '', // optional – kann per Shortcode überschrieben werden
		];
	}

	public function register_settings() {
		register_setting( 'dgptm_vote_group', self::OPT_KEY, [ $this, 'sanitize_settings' ] );
		add_settings_section( 'dgptm_vote_main', 'Online-Abstimmen – Einstellungen', '__return_empty_string', self::OPT_KEY );

		$fields = [
			['button_text_on',     'Button-Text (grün/aktiv)', 'text'],
			['button_text_off',    'Button-Text (rot/inaktiv)', 'text'],
			['inactive_text',      'Ersatztext außerhalb des Startzeitpunkts', 'text'],
			['not_logged_in_text', 'Hinweis für nicht eingeloggte Nutzer', 'text'],
			['not_eligible_text',  'Hinweis (historisch, Rollenprüfung aus)', 'text'],
			['start_datetime',     'Start (YYYY-MM-DD HH:MM)', 'text'],
			['end_datetime',       'Ende (YYYY-MM-DD HH:MM)', 'text'],
			['timezone',           'Zeitzone (nur Anzeige)', 'text', true],
			['button_role',        'Rolle (nicht genutzt)', 'text', true],
			['list_requires_mv',   'Liste nur anzeigen, wenn beim aufrufenden Benutzer "mitgliederversammlung" = true', 'checkbox'],
			['expired_note_text',  'Hinweistext nach Endzeit', 'text'],

			['mail_recipient',     'E-Mail-Empfänger (CSV+PDF Sammelmail)', 'text'],
			['mail_user_subject_on',  'Mail-Betreff (AN/grün)', 'text'],
			['mail_user_body_on',     'Mail-Text (AN/grün)', 'textarea'],
			['mail_user_subject_off', 'Mail-Betreff (AUS/rot)', 'text'],
			['mail_user_body_off',    'Mail-Text (AUS/rot)', 'textarea'],
			['mail_user_bcc_admin',   'Kopie an Admin (BCC) senden', 'checkbox'],
		];
		foreach ( $fields as $f ) {
			add_settings_field( $f[0], esc_html( $f[1] ), [ $this, 'render_field' ], self::OPT_KEY, 'dgptm_vote_main', [
				'key'=>$f[0], 'type'=>$f[2], 'readonly'=> isset($f[3])?(bool)$f[3]:false
			]);
		}

		/* ===== Zoom-Integration ===== */
		add_settings_section( 'dgptm_vote_zoom', 'Zoom-Integration (optional)', function(){
			echo '<p><strong>Hinweis:</strong> Der Button <code>[online_abstimmen_button]</code> registriert bei <em>grün</em> automatisch in Zoom (S2S) und kann bei Live-Status sofort weiterleiten. Bei <em>rot</em> wird die Registrierung in Zoom storniert. Ab der Endzeit werden keine Codes/Mails mehr erzeugt, Zoom bleibt aktiv.</p>';
		}, self::OPT_KEY );

		$Z = function($id,$label,$type='text',$ph='',$help=''){
			add_settings_field($id,$label,[$this,'render_field'],self::OPT_KEY,'dgptm_vote_zoom',
				['key'=>$id,'type'=>$type,'placeholder'=>$ph,'help'=>$help]);
		};
		$Z('zoom_enable','Zoom-Kopplung aktivieren','checkbox');
		$Z('zoom_kind','Event-Typ (webinar/meeting/auto)','text','webinar');
		$Z('zoom_meeting_number','Zoom-ID (Meeting/Webinar)','text','z. B. 82770189111','Nur Ziffern.');
		$Z('zoom_s2s_account_id','S2S OAuth – Account ID');
		$Z('zoom_s2s_client_id','S2S OAuth – Client ID');
		$Z('zoom_s2s_client_secret','S2S OAuth – Client Secret','password');
		$Z('zoom_register_on_green','Bei grün automatisch registrieren','checkbox');
		$Z('zoom_cancel_on_red','Bei rot Registrierung canceln','checkbox');
		$Z('zoom_redirect_on_green','Sofort-Join bei grün','text','auto | app | web | none','Standard: auto');
		$Z('zoom_log_enable','Zoom-Log aktivieren','checkbox','', 'Wenn aus, werden keine Zoom-Requests/Responses gespeichert.');
		$Z('zoom_frontend_debug','Frontend-Debug (Shortcode test=1 erlauben)','checkbox','', 'Wenn aus, zeigen Shortcodes keine Diagnoseflächen, auch wenn test=1 gesetzt ist.');
		$Z('zoom_webhook_secret','Webhook Secret Token (für x-zm-signature)','password','', 'Zoom → App → Features → Event Subscriptions');

		// Mini-Testkonsole im Admin
		add_settings_field('__zoom_test__','Zoom-Verbindung testen',[$this,'render_zoom_test'], self::OPT_KEY,'dgptm_vote_zoom',[]);

		// Zoom-Log anzeigen
		add_settings_section( 'dgptm_vote_zoomlog', 'Zoom – Kommunikations-Log', '__return_empty_string', self::OPT_KEY );
		add_settings_field('__zoom_log__','Letzte Einträge',[$this,'render_zoom_log'], self::OPT_KEY,'dgptm_vote_zoomlog',[]);

		// Abgleich
		add_settings_section( 'dgptm_vote_zoomsync', 'Zoom – Abgleich (lokal ↔ Zoom)', '__return_empty_string', self::OPT_KEY );
		add_settings_field('__zoom_sync__','Abgleich ausführen',[$this,'render_zoom_sync'], self::OPT_KEY,'dgptm_vote_zoomsync',[]);

		/* ===== Anwesenheit (Webhook & Live) ===== */
		add_settings_section( 'dgptm_vote_attendance', 'Zoom – Anwesenheit (Webhook & Live-Status)', function(){
			$url_webhook = esc_url( rest_url( self::REST_NS_ZOOM . self::R_ZOOM_WEBHOOK ) );
			$url_live    = esc_url( rest_url( self::REST_NS_ZOOM . self::R_ZOOM_LIVE ) );
			echo '<p><strong>Webhook-Endpunkt:</strong> <code>'.$url_webhook.'</code><br>';
			echo 'In Zoom (Event Subscriptions) als Endpoint URL hinterlegen. Validierung (endpoint.url_validation) und HMAC-Signatur (x-zm-signature) werden unterstützt.</p>';
			echo '<p><strong>Live-Check:</strong> <code>'.$url_live.'</code> (vom Frontend genutzt)</p>';
		}, self::OPT_KEY );

		add_settings_field( 'zoom_attendance_enable', 'Anwesenheits-Webhook verarbeiten', [ $this, 'render_field' ],
			self::OPT_KEY, 'dgptm_vote_attendance', [ 'key'=>'zoom_attendance_enable', 'type'=>'checkbox' ] );
		add_settings_field( 'zoom_webhook_token', 'Webhook-Secret (Fallback, legacy)', [ $this, 'render_field' ],
			self::OPT_KEY, 'dgptm_vote_attendance', [ 'key'=>'zoom_webhook_token', 'type'=>'text', 'placeholder'=>'(optional) falls Secret-Feld leer' ] );

		/* ===== Präsenz-Scanner ===== */
add_settings_section( 'dgptm_vote_presence', 'Präsenz-Scanner', function(){
	echo '<p>Shortcode: <code>[dgptm_presence_scanner]</code>. Optional per Attribut <code>webhook="…"</code> überschreibbar.</p>';
}, self::OPT_KEY );

add_settings_field(
	'presence_webhook_url',
	'Präsenz-Scanner – Webhook-URL (GET)',
	[ $this, 'render_field' ],
	self::OPT_KEY,
	'dgptm_vote_presence',
	[
		'key'         => 'presence_webhook_url',
		'type'        => 'text',
		'placeholder' => 'https://…/check?scan={scan}',
		'help'        => 'Code vom Badge scannen. Wenn Mitglieder nicht erknnt werden, Doubletencheck im CRM machen.'
	]
	
	
	
);
		
		// In den Plugin-Einstellungen hinzufügen
add_settings_field(
    'dgptm_search_webhook_url',
    'Such-Webhook URL',
    'dgptm_search_webhook_url_callback',
    'dgptm-settings',
    'dgptm_settings_section'
);

function dgptm_search_webhook_url_callback() {
    $value = get_option('dgptm_search_webhook_url', '');
    echo '<input type="url" name="dgptm_search_webhook_url" value="' . esc_attr($value) . '" class="regular-text" />';
    echo '<p class="description">URL für die manuelle Mitgliedersuche</p>';
}

// Option registrieren
register_setting('dgptm-settings', 'dgptm_search_webhook_url');

		
		
		
	}

	public function sanitize_settings( $input ) {
		$D = $this->defaults();
		$out = [];
		$keys_text = [
			'button_text_on','button_text_off','inactive_text','not_logged_in_text','not_eligible_text',
			'start_datetime','end_datetime','timezone','button_role','mail_recipient',
			'mail_user_subject_on','mail_user_subject_off',
			'zoom_kind','zoom_meeting_number','zoom_s2s_account_id','zoom_s2s_client_id','zoom_s2s_client_secret','zoom_redirect_on_green',
			'expired_note_text',
			'zoom_webhook_token',      // legacy
			'zoom_webhook_secret',     // HMAC Secret
			'presence_webhook_url',
		];
		foreach ( $keys_text as $k ) {
			$out[$k] = sanitize_text_field( $input[$k] ?? ($D[$k] ?? '') );
		}

		$out['list_requires_mv']       = ! empty( $input['list_requires_mv'] ) ? 1 : 0;
		$out['mail_user_bcc_admin']    = ! empty( $input['mail_user_bcc_admin'] ) ? 1 : 0;
		$out['mail_user_body_on']      = isset( $input['mail_user_body_on'] )  ? wp_kses_post( $input['mail_user_body_on'] )  : $D['mail_user_body_on'];
		$out['mail_user_body_off']     = isset( $input['mail_user_body_off'] ) ? wp_kses_post( $input['mail_user_body_off'] ) : $D['mail_user_body_off'];

		// Zoom
		$out['zoom_enable']            = ! empty( $input['zoom_enable'] ) ? 1 : 0;
		$out['zoom_register_on_green'] = ! empty( $input['zoom_register_on_green'] ) ? 1 : 0;
		$out['zoom_cancel_on_red']     = ! empty( $input['zoom_cancel_on_red'] ) ? 1 : 0;
		$out['zoom_meeting_number']    = preg_replace('/\D/','', (string)$out['zoom_meeting_number'] );
		$allowed_redirect = ['auto','app','web','none'];
		if ( ! in_array( $out['zoom_redirect_on_green'], $allowed_redirect, true ) ) { $out['zoom_redirect_on_green'] = $D['zoom_redirect_on_green']; }
		$out['zoom_log_enable']        = ! empty( $input['zoom_log_enable'] ) ? 1 : 0;
		$out['zoom_frontend_debug']    = ! empty( $input['zoom_frontend_debug'] ) ? 1 : 0;

		// Attendance
		$out['zoom_attendance_enable'] = ! empty( $input['zoom_attendance_enable'] ) ? 1 : 0;

		return $out;
	}

	public function render_field( $args ) {
		$opts = get_option( self::OPT_KEY, $this->defaults() );
		$key  = $args['key']; $type = $args['type']; $ro = ! empty( $args['readonly'] ) ? 'readonly' : '';
		$val  = $opts[ $key ] ?? '';
		$ph   = $args['placeholder'] ?? '';
		switch ( $type ) {
			case 'text':
				printf('<input type="text" class="regular-text" name="%1$s[%2$s]" value="%3$s" %4$s placeholder="%5$s" />',
					esc_attr( self::OPT_KEY ), esc_attr( $key ), esc_attr( $val ), $ro, esc_attr($ph) );
				if ( $key === 'start_datetime' || $key === 'end_datetime' ) {
					echo '<p class="description">Format: <code>YYYY-MM-DD HH:MM</code> – Zeitzone: ' . esc_html( $opts['timezone'] ?? wp_timezone_string() ) . '</p>';
				}
				if ( strpos( $key, 'mail_user_subject_' ) === 0 ) {
					echo '<p class="description">Platzhalter: {name}, {email}, {code}, {site}, {datetime}</p>';
				}
				break;
			case 'password':
				printf('<input type="password" class="regular-text" name="%1$s[%2$s]" value="%3$s" %4$s placeholder="%5$s" autocomplete="off"/>',
					esc_attr( self::OPT_KEY ), esc_attr( $key ), esc_attr( $val ), $ro, esc_attr($ph) );
				break;
			case 'checkbox':
				printf('<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s /> aktiv</label>',
					esc_attr( self::OPT_KEY ), esc_attr( $key ), checked( ! empty( $val ), true, false ) );
				break;
			case 'textarea':
				printf('<textarea name="%1$s[%2$s]" rows="5" cols="60" class="large-text code">%3$s</textarea>',
					esc_attr( self::OPT_KEY ), esc_attr( $key ), esc_textarea( $val ) );
				echo '<p class="description">Platzhalter: {name}, {email}, {code}, {site}, {datetime}</p>';
				break;
		}
		if ( ! empty( $args['help'] ) ) { echo '<p class="description">'.esc_html($args['help']).'</p>'; }
	}

	public function render_zoom_test() {
		$rest = esc_url_raw( rest_url( self::REST_NS . self::R_ZOOM_TEST ) );
		$nonce = wp_create_nonce('wp_rest');
		echo '<p><button type="button" class="button button-secondary" id="dgptm-zoom-test-btn">Zoom-Verbindung testen</button> <span id="dgptm-zoom-test-status"></span></p>';
		echo '<pre id="dgptm-zoom-test-out" style="display:none; max-width:1100px; white-space:pre-wrap; background:#f6f8fa; padding:12px; border:1px solid #d0d7de; border-radius:6px;"></pre>';
		?>
<script>
(function(){
  const btn=document.getElementById('dgptm-zoom-test-btn');
  const status=document.getElementById('dgptm-zoom-test-status');
  const out=document.getElementById('dgptm-zoom-test-out');
  if(!btn) return;
  btn.addEventListener('click', async (ev)=>{
    ev.preventDefault();
    status.textContent='läuft…'; out.style.display='none'; out.textContent='';
    try{
      const r = await fetch('<?php echo $rest; ?>',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':'<?php echo esc_js($nonce); ?>'},credentials:'same-origin',body:'{}'});
      const d = await r.json();
      out.textContent = JSON.stringify(d,null,2);
      out.style.display='block';
      status.textContent = d && d.ok ? '✔ OK' : '⚠ Prüfen';
    }catch(e){
      out.textContent=String(e);
      out.style.display='block';
      status.textContent='✖ Fehler';
    }
  });
})();
</script>
		<?php
	}

	public function shortcode_code( $atts ) {
		$atts = shortcode_atts( [
			'user_id' => '',   // optional: bestimmte User-ID; Standard = aktueller Benutzer
			'snippet' => '0',  // '1' => in <code>...</code> ausgeben, sonst nur der Klartext
		], $atts, 'online_abstimmen_code' );

		$uid = $atts['user_id'] !== '' ? intval( $atts['user_id'] ) : get_current_user_id();
		if ( ! $uid ) {
			return '';
		}

		$status = get_user_meta( $uid, self::USER_META_STATUS, true );
		$code   = (string) get_user_meta( $uid, self::USER_META_CODE, true );
		if ( $status !== 'on' || $code === '' ) {
			return '';
		}

		if ( $atts['snippet'] === '1' ) {
			return '<code>' . esc_html( $code ) . '</code>';
		}
		return esc_html( $code );
	}

	public function render_zoom_log() {
		$opts = get_option( self::OPT_KEY, $this->defaults() );
		$log = get_option(self::OPT_ZOOM_LOG, []);
		if (!is_array($log)) { $log = []; }
		$log = array_reverse($log); // neueste zuerst
		$show = array_slice($log, 0, 200); // Anzeige: letzte 200

		$url_clear = wp_nonce_url( admin_url('admin-post.php?action='.self::ACTION_ZOOM_LOG_CLEAR), self::ACTION_ZOOM_LOG_CLEAR );
		$url_dl    = wp_nonce_url( admin_url('admin-post.php?action='.self::ACTION_ZOOM_LOG_DL),    self::ACTION_ZOOM_LOG_DL );

		echo '<p><a href="'.esc_url($url_clear).'" class="button">Log leeren</a> <a href="'.esc_url($url_dl).'" class="button button-secondary">Log als JSON herunterladen</a></p>';
		if ( empty($opts['zoom_log_enable']) ) {
			echo '<p><em>Logging ist derzeit deaktiviert (Einstellung „Zoom-Log aktivieren“).</em></p>';
		}

		if (empty($show)) { echo '<p><em>Keine Log-Einträge vorhanden.</em></p>'; return; }

		echo '<style>.dgptm-zoomlog{max-width:1100px}.dgptm-zoomlog details{margin:.4rem 0;border:1px solid #d0d7de;border-radius:6px;background:#fff} .dgptm-zoomlog summary{padding:.45rem .7rem;font-weight:600;cursor:pointer} .dgptm-zoomlog pre{margin:0;padding:.6rem;border-top:1px solid #d0d7de;background:#f6f8fa;white-space:pre-wrap}</style>';
		echo '<div class="dgptm-zoomlog">';
		foreach ($show as $i => $e) {
			$title = '['.esc_html($e['when']??'').'] '.esc_html($e['phase']??'api').' '.esc_html($e['method']??'').' '.esc_html($e['path']??'').' → '.esc_html((string)($e['code']??'')) . ( !empty($e['ok']) ? ' ✔' : (isset($e['ok'])?' ✖':'') );
			echo '<details'.($i<5?' open':'').'><summary>'.$title.'</summary>';
			echo '<pre>'.esc_html( json_encode( $e, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE ) ).'</pre>';
			echo '</details>';
		}
		echo '</div>';
	}
	
	public function shortcode_zoom_link( $atts ) {
    $a = shortcode_atts([
        'label'   => 'Jetzt teilnehmen',
        'target'  => '_blank',  // _blank | _self
        'class'   => '',
        'raw'     => '0',       // '1' => nur die URL als Text ausgeben
        'user_id' => '',        // optional: anderen Nutzer ausgeben
    ], $atts, 'online_abstimmen_zoom_link');

    $uid = $a['user_id'] !== '' ? intval($a['user_id']) : get_current_user_id();
    if ( ! $uid ) return '';

    $url = (string) get_user_meta( $uid, self::USER_META_ZOOM_JOIN, true );
    if ( $url === '' ) return '';

    if ( $a['raw'] === '1' ) {
        return esc_url( $url );
    }

    $target = ($a['target'] === '_self') ? '_self' : '_blank';
    $class  = trim((string)$a['class']);
    $class  = $class !== '' ? ' '.esc_attr($class) : '';

    return '<a class="dgptm-zoom-link'.$class.'" href="'.esc_url($url).'" target="'.$target.'" rel="noopener">'.
           esc_html($a['label']).'</a>';
}
	
	

	public function shortcode_switch_flag( $atts ) {
		$a = shortcode_atts([
			'user_id' => '', // optional
		], $atts, 'online_abstimmen_switch');

		$uid = $a['user_id'] !== '' ? intval($a['user_id']) : get_current_user_id();
		if ( ! $uid ) return '0';

		$status = get_user_meta( $uid, self::USER_META_STATUS, true );
		return ($status === 'on') ? '1' : '0';
	}

	public function render_zoom_sync() {
		$opts = get_option( self::OPT_KEY, $this->defaults() );
		echo '<p>„Gültige Benutzer“ = alle WordPress-Benutzer mit Status <code>on</code> (grün). Verglichen wird per E-Mail mit den in Zoom registrierten (approved + pending).</p>';
		if ( ! $this->zoom_ready($opts) ) {
			echo '<p><em>Zoom ist nicht vollständig konfiguriert.</em></p>'; return;
		}
		try {
			$regs = $this->zoom_list_registrants_all($opts);
			$zoomSet = [];
			foreach ($regs as $r) { if (!empty($r['email'])) { $zoomSet[strtolower($r['email'])]=$r['status']??'approved'; } }

			$localOn = $this->get_local_on_users();
			$localSet = [];
			foreach ($localOn as $u) { $localSet[strtolower($u->user_email)] = $u; }

			$missing=[];
			foreach ($localSet as $mail => $u) { if (!isset($zoomSet[$mail])) $missing[] = $u; }

			$extras=[];
			foreach ($zoomSet as $mail => $st) { if (!isset($localSet[$mail])) $extras[] = ['email'=>$mail,'status'=>$st]; }

			$urlReg = wp_nonce_url( admin_url('admin-post.php?action='.self::ACTION_ZOOM_SYNC.'&do=register_missing'), self::ACTION_ZOOM_SYNC );
			$urlCan = wp_nonce_url( admin_url('admin-post.php?action='.self::ACTION_ZOOM_SYNC.'&do=cancel_extras'),  self::ACTION_ZOOM_SYNC );

			echo '<p><strong>Fehlende in Zoom:</strong> '.count($missing).' &nbsp; ';
			if ($missing) echo '<a class="button" href="'.esc_url($urlReg).'">Alle fehlenden registrieren</a>';
			echo '</p>';
			if ($missing) {
				echo '<ul style="max-height:220px;overflow:auto;border:1px solid #ddd;padding:.5rem;border-radius:6px">';
				foreach ($missing as $u) { echo '<li>'.esc_html($u->display_name).' &lt;'.esc_html($u->user_email).'&gt;</li>'; }
				echo '</ul>';
			}

			echo '<p style="margin-top:1rem"><strong>Überzählige in Zoom:</strong> '.count($extras).' &nbsp; ';
			if ($extras) echo '<a class="button button-secondary" href="'.esc_url($urlCan).'" onclick="return confirm(\'Wirklich alle überzähligen in Zoom abmelden?\');">Alle überzähligen abmelden</a>';
			echo '</p>';
			if ($extras) {
				echo '<ul style="max-height:220px;overflow:auto;border:1px solid #ddd;padding:.5rem;border-radius:6px">';
				foreach ($extras as $e) { echo '<li>'.esc_html($e['email']).' <small>('.esc_html($e['status']).')</small></li>'; }
				echo '</ul>';
			}

		} catch (\Throwable $e) {
			echo '<p><em>Abgleich fehlgeschlagen: '.esc_html($e->getMessage()).'</em></p>';
		}
	}

	private function get_local_on_users(): array {
		$args = [
			'meta_query' => [
				[ 'key' => self::USER_META_STATUS, 'value' => 'on', 'compare' => '=' ],
			],
			'number' => 9999,
			'fields' => 'all',
		];
		return get_users($args);
	}

	public function admin_menu() {
		add_options_page( 'Online-Abstimmen', 'Online-Abstimmen', 'manage_options', 'dgptm-online-abstimmen', [ $this, 'render_settings_page' ] );
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		$opts = get_option( self::OPT_KEY, $this->defaults() );
		$sched_ts    = (int) get_option( self::OPT_SCHEDULE_TS, 0 );
		$auto_sent_at= (int) get_option( self::OPT_AUTO_SENT_AT, 0 );

		echo '<div class="wrap"><h1>DGPTM – Online-Abstimmen</h1>';

		// Tabs
		echo '<h2 class="nav-tab-wrapper" id="dgptm-tabs">'
			.'<a href="#tab-general" class="nav-tab">Allgemein</a>'
			.'<a href="#tab-zoom" class="nav-tab">Zoom</a>'
			.'<a href="#tab-attendance" class="nav-tab">Anwesenheit</a>'
			.'<a href="#tab-presence" class="nav-tab">Präsenz-Scanner</a>'
			.'<a href="#tab-log" class="nav-tab">Log</a>'
			.'<a href="#tab-sync" class="nav-tab">Abgleich</a>'
			.'</h2>';

		echo '<form id="dgptm-settings-form" method="post" action="options.php">';
		settings_fields( 'dgptm_vote_group' );
		do_settings_sections( self::OPT_KEY );
		submit_button();
		echo '</form>';

		echo '<hr><h2>Export & Versand</h2>';
		$nonce_csv  = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_EXPORT_CSV ),  self::ACTION_EXPORT_CSV );
		$nonce_pdf  = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_EXPORT_PDF ),  self::ACTION_EXPORT_PDF );
		$nonce_both = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_SEND_BOTH ),   self::ACTION_SEND_BOTH );

		echo '<p><a class="button button-secondary" href="' . esc_url( $nonce_csv ) . '">CSV exportieren (Download)</a> ';
		echo '<a class="button button-secondary" href="' . esc_url( $nonce_pdf ) . '">PDF exportieren (Download)</a></p>';
		echo '<p><a class="button button-primary" href="' . esc_url( $nonce_both ) . '">CSV + PDF per E-Mail senden</a></p>';
		echo '<p class="description">Empfänger: <strong>' . esc_html( $opts['mail_recipient'] ) . '</strong></p>';

		echo '<h3>Automatischer Versand</h3>';
		echo '<p>Geplantes Ende: <code>' . esc_html( $opts['end_datetime'] ?: '—' ) . '</code></p>';
		echo '<p>Cron-TS: <code>' . esc_html( $sched_ts ?: 0 ) . '</code> | Letzter Auto-Versand: <code>' . esc_html( $auto_sent_at ?: 0 ) . '</code></p>';

		/* ===== Zoom-Join-Links (lokal gespeichert) ===== */
		echo '<hr><h2>Zoom-Registrierungen (Join-Links, lokal)</h2>';
		$rows = $this->collect_zoom_rows();
		if ( $rows ) {
			echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Benutzer</th><th>E-Mail</th><th>Status</th><th>Join-URL</th><th>Letzte Änderung</th></tr></thead><tbody>';
			foreach ( $rows as $r ) {
				echo '<tr>';
				echo '<td>'.esc_html($r['name']).'</td>';
				echo '<td><a href="mailto:'.esc_attr($r['email']).'">'.esc_html($r['email']).'</a></td>';
				echo '<td>'.esc_html($r['zoom_state']).'</td>';
				echo '<td>'.( $r['join_url'] ? '<a href="'.esc_url($r['join_url']).'" target="_blank" rel="noopener">öffnen</a>' : '—' ).'</td>';
				echo '<td>'.esc_html(date_i18n('Y-m-d H:i', $r['ts'])).'</td>';
				echo '</tr>';
			}
			echo '</tbody></table>';
		} else {
			echo '<p>Noch keine Zoom-Join-Links gespeichert.</p>';
		}

		/* ===== Zoom-Registrants (live) ===== */
		echo '<hr><h2>Zoom – Registranten (live)</h2>';
		$nonce_pull = wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_ZOOM_PULL ), self::ACTION_ZOOM_PULL );
		echo '<p><a class="button" href="'.esc_url($nonce_pull).'">Liste aktualisieren (Seite neu laden)</a></p>';

		$optsReady = $this->zoom_ready($opts);
		if ( $optsReady ) {
			try {
				$registrants = $this->zoom_list_registrants_all($opts);
				if ($registrants) {
					echo '<table class="widefat striped" style="max-width:1100px"><thead><tr><th>Name</th><th>E-Mail</th><th>Status</th><th>Aktionen</th></tr></thead><tbody>';
					foreach ($registrants as $reg) {
						$name = trim(($reg['first_name']??'').' '.($reg['last_name']??''));
						$email= $reg['email'] ?? '';
						$status = $reg['status'] ?? '';
						echo '<tr>';
						echo '<td>'.esc_html($name ?: '—').'</td>';
						echo '<td><a href="mailto:'.esc_attr($email).'">'.esc_html($email).'</a></td>';
						echo '<td>'.esc_html($status).'</td>';
						echo '<td>';
						$urlA = wp_nonce_url( admin_url('admin-post.php?action='.self::ACTION_ZOOM_STATUS.'&do=approve&email='.rawurlencode($email)), self::ACTION_ZOOM_STATUS );
						$urlC = wp_nonce_url( admin_url('admin-post.php?action='.self::ACTION_ZOOM_STATUS.'&do=cancel&email='.rawurlencode($email)),  self::ACTION_ZOOM_STATUS );
						echo '<a class="button button-small" href="'.esc_url($urlA).'" style="margin-right:6px">Anmelden (approve)</a>';
						echo '<a class="button button-small" href="'.esc_url($urlC).'">Abmelden (cancel)</a>';
						echo '</td>';
						echo '</tr>';
					}
					echo '</tbody></table>';
				} else {
					echo '<p>Keine Registranten gefunden.</p>';
				}
			} catch (\Throwable $e) {
				echo '<p><em>Fehler beim Abrufen: '.esc_html($e->getMessage()).'</em></p>';
			}
		} else {
			echo '<p><em>Zoom ist nicht vollständig konfiguriert.</em></p>';
		}

		/* ===== Attendance-Panel (Admin) ===== */
		echo '<div id="dgptm-attendance-wrap" class="dgptm-section">';
		echo '<h2>Anwesenheit (Webhook)</h2>';
		$keys = $this->attendance_meeting_keys();
		if (empty($keys)) {
			echo '<p><em>Noch keine Anwesenheitsdaten empfangen.</em></p>';
		} else {
			$cur = isset($_GET['att_key']) ? sanitize_text_field($_GET['att_key']) : array_key_first($keys);
			echo '<form method="get" style="margin:0 0 1rem 0">';
			echo '<input type="hidden" name="page" value="dgptm-online-abstimmen" />';
			echo '<label>Meeting/Webinar:&nbsp; <select name="att_key" onchange="this.form.submit()">';
			foreach ($keys as $k => $label) {
				echo '<option value="'.esc_attr($k).'" '.selected($cur,$k,false).'>'.esc_html($label).'</option>';
			}
			echo '</select></label></form>';
			$this->attendance_render_admin_table($cur);
		}
		echo '</div>';

		/* ===== Tabs-Script ===== */
		echo '<style>
  .dgptm-section{display:none}
  .dgptm-section.active{display:block}
  #dgptm-tabs{margin-top:12px}
</style>
<script>
(function(){
  const titleToId = {
    "Online-Abstimmen – Einstellungen":"dgptm_vote_main",
    "Zoom-Integration (optional)":"dgptm_vote_zoom",
    "Zoom – Kommunikations-Log":"dgptm_vote_zoomlog",
    "Zoom – Abgleich (lokal ↔ Zoom)":"dgptm_vote_zoomsync",
    "Zoom – Anwesenheit (Webhook & Live-Status)":"dgptm_vote_attendance",
    "Präsenz-Scanner":"dgptm_vote_presence"
  };
  const form = document.getElementById("dgptm-settings-form");
  if(form){
    const children = Array.from(form.children);
    let currentWrap = null;
    children.forEach(function(node){
      if(node.tagName==="H2"){
        const id = titleToId[node.textContent.trim()] || ("dgptm-sec-"+Math.random().toString(36).slice(2));
        currentWrap = document.createElement("div");
        currentWrap.id = id;
        currentWrap.className = "dgptm-section";
        form.insertBefore(currentWrap, node);
        currentWrap.appendChild(node);
      }else if(currentWrap){
        currentWrap.appendChild(node);
      }
    });
  }
  function activate(hash){
    const map = {
      "#tab-general": ["dgptm_vote_main"],
      "#tab-zoom": ["dgptm_vote_zoom"],
      "#tab-attendance": ["dgptm_vote_attendance","dgptm-attendance-wrap"],
      "#tab-presence":["dgptm_vote_presence"],
      "#tab-log": ["dgptm_vote_zoomlog"],
      "#tab-sync": ["dgptm_vote_zoomsync"]
    };
    const all = ["dgptm_vote_main","dgptm_vote_zoom","dgptm_vote_attendance","dgptm-attendance-wrap","dgptm_vote_presence","dgptm_vote_zoomlog","dgptm_vote_zoomsync"];
    all.forEach(function(id){
      var el=document.getElementById(id);
      if(el){ el.classList.remove("active"); el.classList.add("dgptm-section"); }
    });
    (map[hash]||map["#tab-general"]).forEach(function(id){
      var el=document.getElementById(id);
      if(el){ el.classList.add("active"); }
    });
    document.querySelectorAll("#dgptm-tabs .nav-tab").forEach(function(a){ a.classList.remove("nav-tab-active"); });
    var cur = document.querySelector(\'#dgptm-tabs a[href="\'+hash+\'"]\');
    if (cur) cur.classList.add("nav-tab-active");
  }
  window.addEventListener("hashchange", function(){ activate(location.hash); });
  activate(location.hash || "#tab-general");
})();
</script>';

		echo '</div>'; // .wrap
	}

public function enqueue_assets() {
	wp_register_style( 'dgptm-vote-css', false, [], '1.9.5' );
	wp_register_script( 'dgptm-vote-js',  false, [ 'jquery' ], '1.9.5', true );

	$css = '
.dgptm-vote-wrap{margin:1em 0}
.dgptm-vote-btn{display:inline-block;padding:.75em 1.25em;font-weight:600;border-radius:.5em;border:0;cursor:pointer;transition:filter .2s}
.dgptm-vote-btn[disabled]{opacity:.6;cursor:not-allowed}
.dgptm-vote-btn.red{background:#b91c1c;color:#fff}
.dgptm-vote-btn.green{background:#16a34a;color:#fff}
.dgptm-vote-note{margin-top:.5em;color:#555}
.dgptm-vote-diag{margin-top:.5em;font:12px/1.4 monospace;white-space:pre-wrap;background:#f6f8fa;padding:.6rem;border:1px solid #d0d7de;border-radius:6px}
.dgptm-vote-table{width:100%;border-collapse:collapse;margin:1em 0;font-size:14px}
.dgptm-vote-table th,.dgptm-vote-table td{border:1px solid #ddd;padding:.5em .6em;vertical-align:top}
.dgptm-strike{text-decoration:line-through;opacity:.65}
.dgptm-presence-stats{margin:.2rem 0 .6rem; font-size:14px; font-weight:600}
.dgptm-presence-stats strong{margin-right:.25rem}
.dgptm-actions a{margin-right:.4em}

.dgptm-modal {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
    z-index: 9999;
}

.dgptm-modal-content {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    background: white;
    padding: 20px;
    border-radius: 5px;
    min-width: 400px;
    max-width: 600px;
}

.dgptm-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
    border-bottom: 1px solid #ddd;
    padding-bottom: 10px;
}

.dgptm-modal-close {
    font-size: 24px;
    cursor: pointer;
    color: #666;
}

.dgptm-modal-close:hover {
    color: #000;
}

.dgptm-search-result-item {
    padding: 10px;
    border: 1px solid #ddd;
    margin: 5px 0;
    cursor: pointer;
    background: #f9f9f9;
}

.dgptm-search-result-item:hover {
    background: #e0f7fa;
}

.dgptm-search-result-item.selected {
    background: #4CAF50;
    color: white;
}

.dgptm-manual-entry {
    background-color: #fff3cd !important;
}

.dgptm-manual-entry .dgptm-status-M {
    color: #856404;
    font-weight: bold;
}

.dgptm-scanner-controls {
    display: flex;
    gap: 10px;
    margin-bottom: 15px;
}

#dgptm-code-input {
    flex: 1;
}

/* Präsenz-Scanner */
.dgptm-presence{min-height:70vh;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:1rem}
.dgptm-presence .scan-input{font-size:28px; padding:.6rem 1rem; width:min(520px,90vw); text-align:center; border:2px solid #d0d7de; border-radius:10px}
.dgptm-presence .flash{position:fixed;inset:0;pointer-events:none;opacity:0;transition:opacity .15s}
.dgptm-presence .flash.show{opacity:.6}
.dgptm-presence .info{font:600 22px/1.2 system-ui, -apple-system, Segoe UI, Roboto, Arial}
.dgptm-presence .sub{font:16px/1.25 system-ui, -apple-system, Segoe UI, Roboto, Arial; opacity:.85}
.dgptm-presence .hint{font:14px/1.3 system-ui; color:#666}

/* Präsenz-Tabelle (Frontend) */
.dgptm-presence-table{width:100%;border-collapse:collapse;margin:1em 0;font-size:14px}
.dgptm-presence-table th,.dgptm-presence-table td{border:1px solid #ddd;padding:.5em .6em;vertical-align:top}
.dgptm-presence-toolbar {display:flex;gap:.5rem;align-items:center;margin:.5rem 0;}
.dgptm-presence-toolbar .btn{display:inline-block;padding:.45rem .8rem;border:1px solid #d0d7de;border-radius:6px;background:#f6f8fa;text-decoration:none}
.dgptm-presence-toolbar .btn.primary{background:#0d6efd;color:#fff;border-color:#0d6efd}
';
$js  = '
jQuery(function($){
	/* ---------- Helpers ---------- */
	function dtFmt(ts){
		if(!ts) return "—";
		try{
			// Erwartet Unix Sekunden (Server liefert so), oder ISO
			const d = (typeof ts==="number" && ts<2000000000) ? new Date(ts*1000) : new Date(ts);
			const f = new Intl.DateTimeFormat("de-DE",{day:"2-digit",month:"2-digit",year:"numeric",hour:"2-digit",minute:"2-digit"});
			// Soll: dd.MM.YYYY hh:mm
			return f.format(d).replace(",", "");
		}catch(_){ return "—"; }
	}
	function normalizeWebhookResponse(d){
		// { ok, result, name, status, email } ODER verschachtelt (Zoho Function):
		// {"code":"success","details":{"output":{"ok":true,"result":"green","name":"...","status":"...","email":"..."}},...}
		if(d && typeof d==="object" && d.details && d.details.output){ return d.details.output; }
		return d;
	}

	/* ---------- (bestehend) Button + Redirect ---------- */
	function setLabel(btn, status){
		if(status==="on"){ btn.text(dgptm_vote.text_on); } else { btn.text(dgptm_vote.text_off); }
	}
	async function isLive(kind, id){
		try{
			if(!dgptm_vote.rest_zoom_live){ return true; }
			const r=await fetch(dgptm_vote.rest_zoom_live,{method:"POST",headers:{"Content-Type":"application/json","Cache-Control":"no-store","X-WP-Nonce":dgptm_vote.rest_nonce},body:JSON.stringify({id:String(id||""),kind:String(kind||"webinar")}),credentials:"same-origin"});
			const d=await r.json();
			return !!(d && d.live);
		}catch(_){ return true; }
	}
	
	function buildAppLink(joinUrl){
		try{
			const u=new URL(joinUrl); const id=u.pathname.split("/").pop(); const ep=u.host; const q=new URLSearchParams(u.search);
			const pwd=q.get("pwd")||""; const tk=q.get("tk")||"";
			const proto=/Android|iPhone|iPad|iPod/i.test(navigator.userAgent)?"zoomus://":"zoommtg://";
			return proto+ep+"/join?action=join&confno="+id+(pwd?("&pwd="+encodeURIComponent(pwd)):"")+(tk?("&tk="+encodeURIComponent(tk)):"");
		}catch(_){ return null; }
	}
	async function handleRedirect($wrap, joinUrl){
		const mode = ($wrap.data("redirect")||"auto").toLowerCase();
		if(mode==="none" || !joinUrl){ return; }
		const kind = $wrap.data("kind") || "webinar";
		const mid  = $wrap.data("meeting") || "";
		const live = await isLive(kind, mid);
		const diag = $wrap.find(".dgptm-vote-diag");
		if(diag.length){ diag.append((diag.text()?"\n":"")+"[live-check] "+(live?"live=1":"live=0")); }
		if(!live){ alert("Registrierung erledigt. Dein persönlicher Teilnahme-Link ist gespeichert und funktioniert, sobald das Event live ist."); return; }
		if(mode==="web"){ window.location.href=joinUrl; return; }
		if(mode==="app"){
			window.location.href=joinUrl;
			setTimeout(()=>{ const app=buildAppLink(joinUrl); if(app){ window.location.href=app; } }, 900);
			return;
		}
		const timer=setTimeout(()=>{ const app=buildAppLink(joinUrl); if(app){ window.location.href=app; } }, 1000);
		window.location.href=joinUrl;
		setTimeout(()=>clearTimeout(timer),2000);
	}
	
	function toggle(btn){
		if(btn.prop("disabled")) return;
		const wrap = btn.closest(".dgptm-vote-wrap");
		const diag = wrap.find(".dgptm-vote-diag");
		btn.prop("disabled", true);
		$.post(dgptm_vote.ajax_url,{
			action: dgptm_vote.action,
			nonce: dgptm_vote.nonce,
			meeting_override: wrap.data("meeting") || "",
			kind_override:     wrap.data("kind")    || ""
		})
		.done(async function(resp){
			if(resp && resp.success){
				var st = resp.data.status;
				btn.toggleClass("green", st==="on");
				btn.toggleClass("red",   st!=="on");
				setLabel(btn, st);
				if(resp.data.msg && diag.length){ diag.append((diag.text()?"\n":"")+"[info] "+resp.data.msg); }
				if(resp.data.after_end && diag.length){ diag.append("\n[window] after_end=1"); }
				if(resp.data.zoom_diag && diag.length){ diag.append("\n[zoom] "+JSON.stringify(resp.data.zoom_diag)); }
				if(st==="on" && resp.data.zoom_join_url){
					if(diag.length){ diag.append("\n[join] "+resp.data.zoom_join_url); }
					await handleRedirect(wrap, resp.data.zoom_join_url);
				}
			}else{
				alert(resp && resp.data ? resp.data.message : "Fehler.");
				if(diag.length && resp && resp.data && resp.data.message){ diag.append((diag.text()?"\n":"")+"[error] "+resp.data.message); }
			}
		})
		.fail(function(){ alert("Netzwerkfehler."); if(diag.length){ diag.append((diag.text()?"\n":"")+"[error] network"); } })
		.always(function(){ btn.prop("disabled", false); });
	}
	$(".dgptm-vote-wrap").on("click",".dgptm-vote-btn",function(e){ e.preventDefault(); toggle($(this)); });

	/* ---------- Präsenz-Scanner: Modal-Funktionen für manuelle Suche ---------- */
	function openSearchModal(){
		$("#dgptm-search-modal").show();
		$("#dgptm-search-input").focus();
	}
	function closeSearchModal(){
		$("#dgptm-search-modal").hide();
		$("#dgptm-search-input").val("");
		$("#dgptm-search-results").html("");
	}
	
	/* ---------- Manuelle Suche ausführen ---------- */
	function executeSearch(){
		const searchTerm = $("#dgptm-search-input").val().trim();
		if(!searchTerm){
			alert("Bitte geben Sie einen Suchbegriff ein.");
			return;
		}
		
		const container = $(".dgptm-presence");
		const searchWebhook = container.data("search-webhook");
		
		if(!searchWebhook){
			alert("Kein Such-Webhook konfiguriert.");
			return;
		}
		
		$("#dgptm-search-results").html("<p>Suche läuft...</p>");
		
		fetch(searchWebhook, {
			method: "POST",
			headers: {"Content-Type": "application/json"},
			body: JSON.stringify({search: searchTerm})
		})
		.then(response => response.json())
		.then(data => {
			// Zoho-Standard-Format normalisieren
			const normalizedData = normalizeWebhookResponse(data);
			const results = Array.isArray(normalizedData) ? normalizedData : (normalizedData.results || []);
			displaySearchResults(results);
		})
		.catch(error => {
			console.error("Suchfehler:", error);
			$("#dgptm-search-results").html("<p style=\"color: red;\">Fehler bei der Suche.</p>");
		});
	}
	
	/* ---------- Suchergebnisse anzeigen ---------- */
	function displaySearchResults(results){
		const container = $("#dgptm-search-results");
		
		if(!Array.isArray(results) || results.length === 0){
			container.html("<p>Keine Ergebnisse gefunden.</p>");
			return;
		}
		
		let html = "<h4>Suchergebnisse:</h4>";
		results.forEach((member, index) => {
			const memberJson = JSON.stringify(member).replace(/"/g, "&quot;");
			html += `
				<div class="dgptm-search-result-item" data-index="${index}" data-member="${memberJson}">
					<strong>${member.fullname || "Unbekannt"}</strong><br>
					<small>Mitgliedsart: ${member.Mitgliedsart || "N/A"} | 
					Status: ${member.status || "N/A"} | 
					Nr: ${member.mitgliedsnummer || "N/A"}</small>
				</div>
			`;
		});
		
		html += "<button type=\"button\" id=\"dgptm-add-selected-btn\" class=\"button button-primary\" style=\"margin-top: 10px;\" disabled>Ausgewähltes Mitglied hinzufügen</button>";
		
		container.html(html);
		
		// Event Listeners für Ergebnisse
		$(".dgptm-search-result-item").on("click", function(){
			$(".dgptm-search-result-item").removeClass("selected");
			$(this).addClass("selected");
			$("#dgptm-add-selected-btn").prop("disabled", false);
		});
		
		// Event Listener für Hinzufügen-Button
		$("#dgptm-add-selected-btn").on("click", addSelectedMember);
	}
	
	/* ---------- Ausgewähltes Mitglied hinzufügen ---------- */
	function addSelectedMember(){
		const selected = $(".dgptm-search-result-item.selected");
		if(!selected.length) return;
		
		const memberData = JSON.parse(selected.attr("data-member").replace(/&quot;/g, \'"\'));
		
		// Prüfen auf Doubletten (basierend auf Mitgliedsnummer)
		const existingEntries = $(".dgptm-attendance-entry");
		let isDuplicate = false;
		existingEntries.each(function(){
			const existingNumber = $(this).find(".dgptm-member-number").text();
			if(existingNumber === "Nr: " + memberData.mitgliedsnummer){
				isDuplicate = true;
				return false; // break
			}
		});
		
		if(isDuplicate){
			alert("Dieses Mitglied ist bereits in der Anwesenheitsliste.");
			return;
		}
		
		// Mitglied zur Anwesenheitsliste hinzufügen
		addToAttendanceList(memberData, true);
		
		// Modal schließen
		closeSearchModal();
	}
	
	/* ---------- Zur Anwesenheitsliste hinzufügen ---------- */
	function addToAttendanceList(data, isManual){
		isManual = isManual || false;
		const list = $("#dgptm-attendance-list");
		const timestamp = new Date().toLocaleString("de-DE");
		
		const manualMarker = isManual ? "<span class=\"dgptm-status-M\">● </span>" : "";
		const entryClass = isManual ? "dgptm-attendance-entry dgptm-manual-entry" : "dgptm-attendance-entry";
		
		const entryDiv = $("<div/>").addClass(entryClass).html(`
			<div class="dgptm-entry-header">
				<strong>${manualMarker}${data.fullname || data.name || "Unbekannt"}</strong>
				<span class="dgptm-timestamp">${timestamp}</span>
			</div>
			<div class="dgptm-entry-details">
				<span class="dgptm-member-type">Typ: ${data.Mitgliedsart || "N/A"}</span>
				<span class="dgptm-member-status">Status: ${data.status || "N/A"}</span>
				<span class="dgptm-member-number">Nr: ${data.mitgliedsnummer || "N/A"}</span>
			</div>
		`);
		
		// Am Anfang der Liste einfügen
		list.prepend(entryDiv);
		
		// Automatisches Speichern falls aktiviert
		const container = $(".dgptm-presence");
		const saveOn = String(container.data("saveon")||"").split(",").map(s=>s.trim());
		const result = data.result || "green";
		
		if(container.data("saveon") === "on" || saveOn.indexOf(result) >= 0){
			saveMemberToDatabase(data, container);
		}
	}
	
	/* ---------- Mitglied in Datenbank speichern ---------- */
	async function saveMemberToDatabase(data, container){
		const kind = container.data("kind") || "auto";
		const mid = String(container.data("meeting") || "");
		
		try{
			await fetch(dgptm_vote.rest_presence, {
				method: "POST",
				headers: {
					"Content-Type": "application/json",
					"X-WP-Nonce": dgptm_vote.rest_nonce
				},
				credentials: "same-origin",
				body: JSON.stringify({
					id: mid,
					kind: kind,
					name: data.fullname || data.name || "",
					email: data.email || "",
					status: data.status || "",
					result: data.result || "green",
					ts: Date.now()
				})
			});
		}catch(e){
			console.error("Fehler beim Speichern:", e);
		}
	}
	
	/* ---------- Event Listeners für manuelle Suche ---------- */
	$(document).on("click", "#dgptm-manual-search-btn", function(e){
		e.preventDefault();
		openSearchModal();
	});
	
	$(document).on("click", ".dgptm-modal-close, #dgptm-search-cancel-btn", function(e){
		e.preventDefault();
		closeSearchModal();
	});
	
	$(document).on("click", "#dgptm-search-modal", function(e){
		if(e.target === this){
			closeSearchModal();
		}
	});
	
	$(document).on("click", "#dgptm-search-execute-btn", function(e){
		e.preventDefault();
		executeSearch();
	});
	
	$(document).on("keypress", "#dgptm-search-input", function(e){
		if(e.key === "Enter"){
			e.preventDefault();
			executeSearch();
		}
	});

	/* ---------- Präsenz-Scanner: Enter → Webhook GET + REST speichern ---------- */
	$(document).on("keydown",".dgptm-presence .scan-input", function(ev){
		if(ev.key !== "Enter") return;
		ev.preventDefault();
		const box = $(this).closest(".dgptm-presence");
		const code = $(this).val().trim();
		if(!code){ return; }
		const url  = box.data("webhook");
		const kind = box.data("kind") || "auto";
		const mid  = String(box.data("meeting") || "");
		const saveOn = String(box.data("saveon")||"green,yellow").split(",").map(s=>s.trim());

		function flash(color, name, status){
			const f = box.find(".flash");
			const info = box.find(".info");
			const sub  = box.find(".sub");
			let bg = "rgba(22,163,74,.8)"; // green
			if(color==="yellow") bg = "rgba(245,158,11,.9)";
			if(color==="red")    bg = "rgba(220,38,38,.9)";
			f.css("background", bg).addClass("show");
			if(name||status){ info.text(name||""); sub.text(status||""); }
			setTimeout(()=>{ f.removeClass("show"); info.text(""); sub.text(""); }, 1200);
		}
		function beep(ok){
			try {
				const ctx = new (window.AudioContext||window.webkitAudioContext)();
				const o = ctx.createOscillator(); const g = ctx.createGain();
				o.type = ok?"sine":"square"; o.frequency.setValueAtTime(ok?880:220, ctx.currentTime);
				g.gain.setValueAtTime(0.001, ctx.currentTime); g.gain.exponentialRampToValueAtTime(0.2, ctx.currentTime+.01);
				o.connect(g).connect(ctx.destination); o.start(); setTimeout(()=>{ g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime+.15); o.stop(ctx.currentTime+.2); }, 100);
			}catch(_){}
		}
		async function recordPresence(payload){
			try{
				await fetch(dgptm_vote.rest_presence,{
					method:"POST",
					headers:{"Content-Type":"application/json","X-WP-Nonce":dgptm_vote.rest_nonce},
					credentials:"same-origin",
					body: JSON.stringify(payload)
				});
			}catch(_){}
		}

		(async ()=>{
			try{
				const hook = url ? (
					url.indexOf("{scan}")>=0 ? url.replace("{scan}", encodeURIComponent(code)) :
					url.indexOf("{code}")>=0 ? url.replace("{code}", encodeURIComponent(code)) :
					url + (url.indexOf("?")>=0?"&":"?") + "scan=" + encodeURIComponent(code)
				) : "";

				let resNorm = {ok:false, result:"red", name:"", status:"", email:""};
				if(hook){
					const r = await fetch(hook, {method:"GET", mode:"cors", credentials:"omit", headers:{"Accept":"application/json"}});
					if(!r.ok){ throw new Error("HTTP "+r.status); }
					const raw = await r.json();
					resNorm = normalizeWebhookResponse(raw) || resNorm;
				}

				const result = (resNorm && typeof resNorm.result==="string") ? resNorm.result.toLowerCase() : "red";
				const good   = result==="green" || result==="yellow";
				flash(result, resNorm.name||"", resNorm.status||"");
				beep(good);

				if(saveOn.indexOf(result)>=0){
					await recordPresence({
						id: mid, kind: kind, name: resNorm.name||"", email: resNorm.email||"", status: resNorm.status||"", result: result, ts: Date.now()
					});
				}
				
				// Zur Anwesenheitsliste hinzufügen (ohne manuelle Markierung)
				addToAttendanceList(resNorm, false);
				
			}catch(e){
				flash("red","", "Fehler / keine Antwort"); beep(false);
			}finally{
				$(ev.target).val("");
				ev.target.focus();
			}
		})();
	});

	/* ---------- Präsenz-Tabelle (Frontend Shortcode) ---------- */
	async function fetchPresenceList(box){
		const params = {
			id: String(box.data("meeting")||""),
			kind: String(box.data("kind")||"auto")
		};
		const r = await fetch(dgptm_vote.rest_presence_list+"?id="+encodeURIComponent(params.id)+"&kind="+encodeURIComponent(params.kind), {
			method:"GET",
			headers:{"Accept":"application/json","X-WP-Nonce":dgptm_vote.rest_nonce},
			credentials:"same-origin"
		});
		const d = await r.json();
		if(!r.ok || !d.ok){ throw new Error(d?.error||("HTTP "+r.status)); }
		return d;
	}
	function renderPresenceTable(box, data){
		const tbody = box.find("tbody");
		tbody.empty();
		const rows = data.rows || [];
		rows.forEach(function(row){
			// Jede Zeile trägt ihren PK als data-pk → sicheres Löschen
			const tr = $("<tr/>").attr("data-pk", row.pk || "");
			tr.append($("<td/>").text(row.name||"—"));                                         // Name
			tr.append($("<td/>").html(row.email ? \'<a href="mailto:\'+row.email+\'">\'+row.email+\'</a>\' : "—")); // Email
			tr.append($("<td/>").text(row.status || ""));                                      // Status
			tr.append($("<td/>").text(dtFmt(row.join_first)));                                  // Anwesend ab
			tr.append($("<td/>").text(row.type==="presence"?"Präsenz":"Online"));               // Art
			tr.append($("<td/>").addClass("dgptm-manual-cell").text(row.manual ? "X" : ""));

			const actions = $("<td/>");
			if(String(box.data("allowdel"))==="1"){
				const del = $("<a/>").attr("href","#").addClass("btn").text("Löschen").on("click", async function(ev){
					ev.preventDefault();
					const pk = $(this).closest("tr").attr("data-pk") || "";
					if(!pk){ alert("Kein Schlüssel für diesen Eintrag gefunden."); return; }
					if(!confirm("Eintrag löschen?")) return;
					try{
						const r = await fetch(dgptm_vote.rest_presence_delete,{
							method:"POST",
							headers:{"Content-Type":"application/json","X-WP-Nonce":dgptm_vote.rest_nonce},
							credentials:"same-origin",
							body: JSON.stringify({key:data.mkey, pk:pk})
						});
						const j = await r.json();
						if(!r.ok || !j.ok) throw new Error(j?.error||("HTTP "+r.status));
						refreshNow(box);
					}catch(e){ alert("Fehler: "+(e?.message||e)); }
				});
				actions.append(del);
			}
			tr.append(actions);
			tbody.append(tr);
		});
		box.find(".dgptm-pres-count").text(String(rows.length));
	}
	async function exportPresencePDF(box){
		try{
			const body = { key: String(box.data("mkey")||""), id:String(box.data("meeting")||""), kind:String(box.data("kind")||"auto") };
			const r = await fetch(dgptm_vote.rest_presence_pdf,{
				method:"POST",
				headers:{"Content-Type":"application/json","X-WP-Nonce":dgptm_vote.rest_nonce},
				credentials:"same-origin",
				body: JSON.stringify(body)
			});
			const j = await r.json();
			if(!r.ok || !j.ok) throw new Error(j?.error||("HTTP "+r.status));
			window.location.href = j.url;
		}catch(e){ alert("PDF-Export fehlgeschlagen: "+(e?.message||e)); }
	}
	async function refreshNow(box){
		box.addClass("is-loading");
		try{
			const d = await fetchPresenceList(box);
			renderPresenceTable(box, d);
			box.data("mkey", d.mkey||"");
		}catch(e){
			alert("Laden fehlgeschlagen: "+(e?.message||e));
		}finally{
			box.removeClass("is-loading");
		}
	}
	$(document).on("click",".dgptm-presence-ui .js-refresh", function(ev){
		ev.preventDefault();
		const box=$(this).closest(".dgptm-presence-ui");
		refreshNow(box);
	});
	$(document).on("click",".dgptm-presence-ui .js-export", function(ev){
		ev.preventDefault();
		const box=$(this).closest(".dgptm-presence-ui");
		exportPresencePDF(box);
	});
	$(".dgptm-presence-ui").each(function(){
		const box=$(this);
		const every = Math.max(3, parseInt(String(box.data("refresh"))||"10",10));
		refreshNow(box);
		let timer = setInterval(()=>refreshNow(box), every*1000);
		box.on("remove", ()=>clearInterval(timer));
	});
});';

	wp_enqueue_style( 'dgptm-vote-css' );
	wp_add_inline_style( 'dgptm-vote-css', $css );

	$opts = get_option( self::OPT_KEY, $this->defaults() );
	wp_enqueue_script( 'dgptm-vote-js' );
	wp_localize_script( 'dgptm-vote-js', 'dgptm_vote', [
		'ajax_url'             => admin_url( 'admin-ajax.php' ),
		'action'               => self::AJAX_ACTION_TOGGLE,
		'nonce'                => wp_create_nonce( self::NONCE_ACTION_TOGGLE ),
		'text_on'              => $opts['button_text_on']  ?? $this->defaults()['button_text_on'],
		'text_off'             => $opts['button_text_off'] ?? $this->defaults()['button_text_off'],
		'rest_zoom_live'       => esc_url_raw( rest_url( 'dgptm-zoom/v1/live' ) ),
		'rest_presence'        => esc_url_raw( rest_url( 'dgptm-zoom/v1/presence' ) ),
		// Frontend-REST für Liste/Löschen/PDF
		'rest_presence_list'   => esc_url_raw( rest_url( 'dgptm-zoom/v1/presence-list' ) ),
		'rest_presence_delete' => esc_url_raw( rest_url( 'dgptm-zoom/v1/presence-delete' ) ),
		'rest_presence_pdf'    => esc_url_raw( rest_url( 'dgptm-zoom/v1/presence-pdf' ) ),
		'rest_nonce'           => wp_create_nonce('wp_rest'),
	] );
	wp_add_inline_script( 'dgptm-vote-js', $js );
}


	/** ===== Zeitfenster-Flags ===== */
	private function time_flags($opts): array {
		$tz = wp_timezone(); $now = new DateTime('now',$tz);
		$start_ts = 0; $end_ts = 0;
		if ( ! empty( $opts['start_datetime'] ) ) {
			$start = DateTime::createFromFormat('Y-m-d H:i', $opts['start_datetime'], $tz);
			if ($start instanceof DateTime) $start_ts = $start->getTimestamp();
		}
		if ( ! empty( $opts['end_datetime'] ) ) {
			$end = DateTime::createFromFormat('Y-m-d H:i', $opts['end_datetime'], $tz);
			if ($end instanceof DateTime) $end_ts = $end->getTimestamp();
		}
		$now_ts = $now->getTimestamp();
		return [
			'before_start' => ($start_ts>0 && $now_ts < $start_ts),
			'after_end'    => ($end_ts>0   && $now_ts >= $end_ts),
			'start_ts'     => $start_ts,
			'end_ts'       => $end_ts,
		];
	}

	/** ===== Shortcode: Button ===== */
	public function shortcode_button( $atts ) {
		$opts = get_option( self::OPT_KEY, $this->defaults() );

		$atts = shortcode_atts([
			'redirect'       => '',
			'test'           => '0',
			'meeting_number' => '',
			'kind'           => '',
		], $atts, 'online_abstimmen_button' );

		if ( ! is_user_logged_in() ) {
			return '<div class="dgptm-vote-wrap"><div class="dgptm-vote-note">' . esc_html( $opts['not_logged_in_text'] ) . '</div></div>';
		}

		$flags = $this->time_flags($opts);
		if ( $flags['before_start'] ) {
			return '<div class="dgptm-vote-wrap"><button class="dgptm-vote-btn red" disabled>' . esc_html( $opts['inactive_text'] ) . '</button></div>';
		}

		$user   = wp_get_current_user();
		$status = get_user_meta( $user->ID, self::USER_META_STATUS, true );
		$is_on  = ( $status === 'on' );
		$btn_cls= $is_on ? 'green' : 'red';
		$text   = $is_on ? ( $opts['button_text_on'] ?? $this->defaults()['button_text_on'] )
		                 : ( $opts['button_text_off'] ?? $this->defaults()['button_text_off'] );

		$kind_eff = strtolower( $atts['kind'] ?: ( $opts['zoom_kind'] ?? 'webinar' ) );
		$kind_eff = in_array( $kind_eff, ['meeting','webinar','auto'], true ) ? $kind_eff : 'auto';
		$mid_eff  = preg_replace('/\D/','', (string)($atts['meeting_number'] ?: ($opts['zoom_meeting_number'] ?? '')) );

		$redir= $atts['redirect'] !== '' ? strtolower($atts['redirect']) : strtolower((string)($opts['zoom_redirect_on_green'] ?? 'auto'));
		$allowed_redirect = ['auto','app','web','none'];
		if ( ! in_array( $redir, $allowed_redirect, true ) ) { $redir = 'auto'; }

		$diag = '';
		if ( !empty($opts['zoom_frontend_debug']) && $atts['test'] === '1' ) {
			$diag = '<div class="dgptm-vote-diag"></div>';
		}

		$note_expired = $flags['after_end'] ? '<div class="dgptm-vote-note">'.esc_html($opts['expired_note_text']).'</div>' : '';

		return '<div class="dgptm-vote-wrap" data-kind="'.esc_attr($kind_eff).'" data-meeting="'.esc_attr($mid_eff).'" data-redirect="'.esc_attr($redir).'"><button type="button" class="dgptm-vote-btn ' . esc_attr( $btn_cls ) . '">' . esc_html( $text ) . '</button>'.$note_expired.$diag.'</div>';
	}

	/** ===== Shortcode: Teilnehmerliste ===== */
	public function shortcode_list( $atts ) {
		$atts = shortcode_atts( [ 'include_all' => '1' ], $atts, 'online_abstimmen_liste' );
		$opts = get_option( self::OPT_KEY, $this->defaults() );

		if ( ! is_user_logged_in() ) { return '<p><em>Bitte einloggen.</em></p>'; }
		if ( ! empty( $opts['list_requires_mv'] ) ) {
			$flag_calling = get_user_meta( get_current_user_id(), self::USER_META_MV_FLAG, true );
			if ( ! self::is_truthy( $flag_calling ) ) { return '<p><em>Keine Berechtigung.</em></p>'; }
		}

		$args = [
			'meta_query' => [
				'relation' => 'OR',
				[ 'key' => self::USER_META_STATUS, 'compare' => 'EXISTS' ],
				[ 'key' => self::USER_META_CODE,   'compare' => 'EXISTS' ],
			],
			'number' => 9999,
			'fields' => 'all',
		];
		$users = get_users( $args );

		$html  = '<table class="dgptm-vote-table"><thead><tr>';
		$html .= '<th>Name</th><th>E-Mail</th><th>Mitgliedsnummer</th><th>Mitgliedsart</th><th>Status</th><th>Code</th><th>Zoom</th><th>Aktionen</th>';
		$html .= '</tr></thead><tbody>';

		foreach ( $users as $user ) {
			$uid    = $user->ID;
			$status = get_user_meta( $uid, self::USER_META_STATUS, true );
			$code   = get_user_meta( $uid, self::USER_META_CODE, true );
			$had_on = self::is_truthy( get_user_meta( $uid, self::USER_META_HAD_ON, true ) );

			if ( $atts['include_all'] !== '1' ) {
				if ( ! $had_on && empty( $code ) && $status !== 'on' ) { continue; }
			}

			$first = trim( (string) get_user_meta( $uid, 'first_name', true ) );
			$last  = trim( (string) get_user_meta( $uid, 'last_name',  true ) );
			$name  = trim( ($first . ' ' . $last) ) ?: $user->display_name;
			$email = $user->user_email;

			$mitgliedsnr  = get_user_meta( $uid, self::USER_META_ZOHO_MNR,    true );
			$mitgliedsart = get_user_meta( $uid, self::USER_META_ZOHO_ART,    true );
			$zoho_status  = get_user_meta( $uid, self::USER_META_ZOHO_STATUS, true );

			$mitgliedsnr  = $mitgliedsnr  !== '' ? $mitgliedsnr  : '—';
			$mitgliedsart = $mitgliedsart !== '' ? $mitgliedsart : '—';
			$zoho_status  = $zoho_status  !== '' ? $zoho_status  : '—';

			$row_class = ( $status !== 'on' && $had_on ) ? 'dgptm-strike' : '';

			$delete_url = wp_nonce_url(
				admin_url( 'admin-post.php?action=' . self::ACTION_DELETE_USER . '&uid=' . intval( $uid ) ),
				self::ACTION_DELETE_USER . '_' . intval( $uid )
			);

			$zoom_url   = (string) get_user_meta( $uid, self::USER_META_ZOOM_JOIN, true );
			$zoom_state = (string) get_user_meta( $uid, self::USER_META_ZOOM_STATE, true );
			$zoom_cell  = $zoom_url ? '<a href="'.esc_url($zoom_url).'" target="_blank" rel="noopener">Join</a>'.($zoom_state?(' <small>('.esc_html($zoom_state).')</small>'):'') : '—';

			$html .= '<tr class="' . esc_attr( $row_class ) . '">';
			$html .= '<td>' . esc_html( $name ) . '</td>';
			$html .= '<td><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></td>';
			$html .= '<td>' . esc_html( $mitgliedsnr ) . '</td>';
			$html .= '<td>' . esc_html( $mitgliedsart ) . '</td>';
			$html .= '<td>' . esc_html( $zoho_status ) . '</td>';
			$html .= '<td><code>' . esc_html( (string) $code ) . '</code></td>';
			$html .= '<td>' . $zoom_cell . '</td>';
			$html .= '<td class="dgptm-actions"><a href="' . esc_url( $delete_url ) . '" class="button button-small" onclick="return confirm(\'Benutzer aus der Liste entfernen? (Status & Code werden gelöscht)\');">Löschen</a></td>';
			$html .= '</tr>';
		}

		$html .= '</tbody></table>';
		return $html;
	}

	/** 0/1 anhand Benutzermeta "mitgliederversammlung" */
	public function shortcode_mv_flag( $atts ) {
		$atts = shortcode_atts( [ 'user_id' => '' ], $atts, 'mitgliederversammlung_flag' );
		$uid = $atts['user_id'] !== '' ? intval( $atts['user_id'] ) : get_current_user_id();
		if ( ! $uid ) { return '0'; }
		$flag = get_user_meta( $uid, self::USER_META_MV_FLAG, true );
		return self::is_truthy( $flag ) ? '1' : '0';
	}

	/** ===== AJAX Toggle ===== */
	public function ajax_toggle() {
		if ( ! is_user_logged_in() ) { wp_send_json_error( [ 'message' => 'Nicht eingeloggt.' ], 401 ); }
		check_ajax_referer( self::NONCE_ACTION_TOGGLE, 'nonce' );

		$opts  = get_option( self::OPT_KEY, $this->defaults() );
		$flags = $this->time_flags($opts);
		if ( $flags['before_start'] ) { wp_send_json_error( [ 'message' => 'Außerhalb des erlaubten Zeitfensters (vor Start).' ], 400 ); }

		$user = wp_get_current_user();
		$uid  = $user->ID;
		$curr = get_user_meta( $uid, self::USER_META_STATUS, true );
		$new  = ( $curr === 'on' ) ? 'off' : 'on';

		$kindOv = isset($_POST['kind_override']) ? sanitize_text_field($_POST['kind_override']) : '';
		$idOv   = isset($_POST['meeting_override']) ? preg_replace('/\D/','', (string)$_POST['meeting_override']) : '';

		$idn = $this->identity_for_zoom($uid, $user);
		if ( ! is_email($idn['email']) ) {
			wp_send_json_error( [ 'message' => 'Ungültige E-Mail aus Zoho/WP-Profil. Bitte Profil prüfen.' ] );
		}

		$diag = ['after_end'=>$flags['after_end']?1:0];

		if ( $new === 'on' ) {
			$join_url = '';
			$zoom_note= '';
			if ( $opts['zoom_enable'] && $opts['zoom_register_on_green'] && $this->zoom_ready($opts) ) {
			 try {
					$res = $this->zoom_register_user($opts, $uid, $idn['first'], $idn['last'], $idn['email'], $kindOv, $idOv);
					$join_url = (string) ($res['join_url'] ?? '');
					$zoom_note= (string) ($res['note'] ?? '');
					$diag['zoom_note'] = $zoom_note;
				} catch ( \Throwable $e ) {
					wp_send_json_error( [ 'message' => 'Zoom-Registrierung fehlgeschlagen: '.$e->getMessage() ] );
				}
			}
			if ( ! $flags['after_end'] ) {
				$this->ensure_six_digit_code_assigned( $uid );
				update_user_meta( $uid, self::USER_META_HAD_ON, 1 );
			}
			update_user_meta( $uid, self::USER_META_STATUS, 'on' );
			update_user_meta( $uid, self::USER_META_TS, time() );
			$this->snapshot_user_zoho_fields( $uid );
			if ( ! $flags['after_end'] ) { $this->send_user_toggle_mail( $user, 'on' ); }

			wp_send_json_success( [
				'status'=>'on','msg'=>'aktiv','zoom_join_url'=>$join_url,'after_end'=>$flags['after_end']?1:0,'zoom_diag'=>$diag
			] );
		}

		if ( $new === 'off' ) {
			update_user_meta( $uid, self::USER_META_STATUS, 'off' );
			update_user_meta( $uid, self::USER_META_TS, time() );
			$this->snapshot_user_zoho_fields( $uid );

			$msg = '';
			if ( $opts['zoom_enable'] && $opts['zoom_cancel_on_red'] && $this->zoom_ready($opts) ) {
				try {
					$this->zoom_cancel_user($opts, $uid, $idn['email'], $kindOv, $idOv);
					$msg = 'Zoom-Registrierung storniert';
				} catch ( \Throwable $e ) {
					$msg = 'Zoom-Cancel fehlgeschlagen: '.$e->getMessage();
				}
			}
			if ( ! $flags['after_end'] ) { $this->send_user_toggle_mail( $user, 'off' ); }
			wp_send_json_success( [ 'status'=>'off', 'msg'=>$msg, 'after_end'=>$flags['after_end']?1:0 ] );
		}
	}

	/** ===== sichere Identität ===== */
	private function identity_for_zoom(int $uid, \WP_User $user): array {
		$first = trim( wp_strip_all_tags( do_shortcode('[zoho_api_data field="Vorname" user_id="'.intval($uid).'"]') ) );
		$last  = trim( wp_strip_all_tags( do_shortcode('[zoho_api_data field="Nachname" user_id="'.intval($uid).'"]') ) );
		$mail  = trim( wp_strip_all_tags( do_shortcode('[zoho_api_data field="Mail1" user_id="'.intval($uid).'"]') ) );

		if ( $first === '' ) { $first = trim( (string) get_user_meta( $uid, 'first_name', true ) ); }
		if ( $last  === '' ) { $last  = trim( (string) get_user_meta( $uid, 'last_name',  true ) ); }
		if ( ! is_email($mail) ) { $mail = (string) $user->user_email; }

		if ( $first === '' && $last === '' ) {
			$dn = trim( (string)$user->display_name );
			if ($dn !== '') {
				$parts = preg_split('/\s+/', $dn);
				$first = $parts[0];
				$last  = count($parts)>1 ? $parts[count($parts)-1] : '-';
			}
		}
		if ($last === '') { $last = '-'; }

		return ['first'=>$first, 'last'=>$last, 'email'=>$mail];
	}

	/** ===== Codevergabe ===== */
	private function ensure_six_digit_code_assigned( $user_id ) {
		$existing = get_user_meta( $user_id, self::USER_META_CODE, true );
		if ( ! empty( $existing ) ) { return $existing; }

		$assigned = get_option( self::OPT_ASSIGNED_MAP, [] );
		$used_set = array_map( 'strval', array_values( $assigned ) );

		$tries = 0;
		do { $code = (string) wp_rand( 100000, 999999 ); $tries++; }
		while ( in_array( $code, $used_set, true ) && $tries < 50 );

		if ( in_array( $code, $used_set, true ) ) {
			$code = (string) ( (int) max( $used_set ?: [100000] ) + 1 );
			if ( strlen( $code ) > 6 ) { $code = substr( $code, -6 ); }
			if ( in_array( $code, $used_set, true ) ) { $code = (string) wp_rand( 100000, 999999 ); }
		}

		$assigned[ $user_id ] = $code;
		update_option( self::OPT_ASSIGNED_MAP, $assigned, false );
		update_user_meta( $user_id, self::USER_META_CODE, $code );
		return $code;
	}

	/** ===== Zoho Snapshot ===== */
	private function snapshot_user_zoho_fields( int $uid ) {
		$mnr  = do_shortcode( '[zoho_api_data field="MitgliedsNr" user_id="' . $uid . '"]' );
		$mart = do_shortcode( '[zoho_api_data field="Mitgliedsart" user_id="' . $uid . '"]' );
		$stat = do_shortcode( '[zoho_api_data field="Status" user_id="' . $uid . '"]' );

		$mnr  = trim( wp_strip_all_tags( (string) $mnr ) );
		$mart = trim( wp_strip_all_tags( (string) $mart ) );
		$stat = trim( wp_strip_all_tags( (string) $stat ) );

		if ( $mnr !== '' )  { update_user_meta( $uid, self::USER_META_ZOHO_MNR,    $mnr ); }
		if ( $mart !== '' ) { update_user_meta( $uid, self::USER_META_ZOHO_ART,    $mart ); }
		if ( $stat !== '' ) { update_user_meta( $uid, self::USER_META_ZOHO_STATUS, $stat ); }
	}

	/** ===== Nutzer-Mail ===== */
	private function send_user_toggle_mail( \WP_User $user, string $status_new ) {
		$opts = get_option( self::OPT_KEY, $this->defaults() );
		$flags= $this->time_flags($opts);
		if ($flags['after_end']) { return; }

		$first = trim( (string) get_user_meta( $user->ID, 'first_name', true ) );
		$last  = trim( (string) get_user_meta( $user->ID, 'last_name',  true ) );
		$name  = trim( ($first . ' ' . $last) ) ?: $user->display_name;

		$email= $user->user_email;
		$code = get_user_meta( $user->ID, self::USER_META_CODE, true ) ?: '';

		$repl = [
			'{name}'     => $name,
			'{email}'    => $email,
			'{code}'     => (string) $code,
			'{site}'     => wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ),
			'{datetime}' => wp_date( 'Y-m-d H:i' ),
		];

		if ( $status_new === 'on' ) {
			$subject = strtr( $opts['mail_user_subject_on'] ?? $this->defaults()['mail_user_subject_on'], $repl );
			$body    = strtr( wp_strip_all_tags( $opts['mail_user_body_on'] ?? $this->defaults()['mail_user_body_on'], false ), $repl );
		} else {
			$subject = strtr( $opts['mail_user_subject_off'] ?? $this->defaults()['mail_user_subject_off'], $repl );
			$body    = strtr( wp_strip_all_tags( $opts['mail_user_body_off'] ?? $this->defaults()['mail_user_body_off'], false ), $repl );
		}

		$blogname = $repl['{site}']; $from = get_option( 'admin_email' );
		$headers  = [ 'Content-Type: text/plain; charset=UTF-8', 'From: ' . $blogname . ' <' . $from . '>' ];
		if ( ! empty( $opts['mail_user_bcc_admin'] ) ) { $headers[] = 'Bcc: ' . $from; }

		@wp_mail( $email, $subject, $body, $headers );
	}

	/** Benutzer aus Liste löschen */
	public function handle_delete_user() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No permissions.' ); }
		$uid = isset( $_GET['uid'] ) ? (int) $_GET['uid'] : 0;
		check_admin_referer( self::ACTION_DELETE_USER . '_' . $uid );

		if ( $uid > 0 ) {
			delete_user_meta( $uid, self::USER_META_STATUS );
			delete_user_meta( $uid, self::USER_META_HAD_ON );
			delete_user_meta( $uid, self::USER_META_TS );

			$assigned = get_option( self::OPT_ASSIGNED_MAP, [] );
			if ( isset( $assigned[ $uid ] ) ) { unset( $assigned[ $uid ] ); update_option( self::OPT_ASSIGNED_MAP, $assigned, false ); }
			delete_user_meta( $uid, self::USER_META_CODE );
		}
		wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url( 'options-general.php?page=dgptm-online-abstimmen' ) );
		exit;
	}

	/** ===== Exporte / PDF / Cron ===== */
	private function collect_rows_for_csv_export() {
		$args = [
			'meta_query' => [
				'relation' => 'OR',
				[ 'key' => self::USER_META_STATUS, 'compare' => 'EXISTS' ],
				[ 'key' => self::USER_META_CODE,   'compare' => 'EXISTS' ],
			],
			'number' => 9999, 'fields' => 'all',
		];
		$users = get_users( $args );

		$rows = [];
		foreach ( $users as $user ) {
			$uid    = $user->ID;
			$code   = get_user_meta( $uid, self::USER_META_CODE, true );

			$first = trim( (string) get_user_meta( $uid, 'first_name', true ) );
			$last  = trim( (string) get_user_meta( $uid, 'last_name',  true ) );
			
			// Wenn first_name/last_name nicht vorhanden, display_name aufteilen
			if ( empty($first) && empty($last) ) {
				$parts = explode( ' ', $user->display_name, 2 );
				$first = isset($parts[0]) ? $parts[0] : '';
				$last  = isset($parts[1]) ? $parts[1] : '';
			}

			$mitgliedsnr  = get_user_meta( $uid, self::USER_META_ZOHO_MNR,    true );
			$mitgliedsart = get_user_meta( $uid, self::USER_META_ZOHO_ART,    true );

			// Nur "Ordentliches Mitglied" in die Liste aufnehmen
			if ( $mitgliedsart !== 'Ordentliches Mitglied' ) {
				continue;
			}

			// CSV-Zeile im XLSX-Format (27 Spalten, erste Spalte entfernt)
			$rows[] = [
				$last,                 // Spalte 1: Nachname
				$first,                // Spalte 2: Vorname
				'',                    // Spalte 3: Anrede (leer)
				$user->user_email,     // Spalte 4: E-Mail
				'',                    // Spalte 5: Telefon (leer)
				'',                    // Spalte 6: Firma (leer)
				$mitgliedsart ?: '',   // Spalte 7: Gruppe
				'',                    // Spalte 8: Rolle (leer)
				'',                    // Spalte 9: Vertreter von (leer)
				'',                    // Spalte 10: Gerät-Nr. (leer)
				$mitgliedsnr ?: '',    // Spalte 11: ID
				'',                    // Spalte 12: Gewichtung (leer)
				'',                    // Spalte 13: Vollmacht an (leer)
				'',                    // Spalte 14: PlanX (leer)
				'',                    // Spalte 15: PlanY (leer)
				'',                    // Spalte 16: Kandidatennummer (leer)
				$code ?: '',           // Spalte 17: PIN
				'',                    // Spalte 18: Kompetenz 1 (leer)
				'',                    // Spalte 19: Kompetenz 2 (leer)
				'',                    // Spalte 20: Kompetenz 3 (leer)
				'',                    // Spalte 21: Kompetenz 4 (leer)
				'',                    // Spalte 22: Kompetenz 5 (leer)
				'',                    // Spalte 23: Kompetenz 6 (leer)
				'',                    // Spalte 24: Kompetenz 7 (leer)
				'',                    // Spalte 25: Kompetenz 8 (leer)
				'',                    // Spalte 26: Kompetenz 9 (leer)
				''                     // Spalte 27: Kompetenz 10 (leer)
			];
		}
		return $rows;
	}

	private function collect_rows_for_pdf() {
		$args = [
			'meta_query' => [
				'relation' => 'OR',
				[ 'key' => self::USER_META_STATUS, 'compare' => 'EXISTS' ],
				[ 'key' => self::USER_META_CODE,   'compare' => 'EXISTS' ],
			],
			'number' => 9999, 'fields' => 'all',
		];
		$users = get_users( $args );

		$rows = [];
		foreach ( $users as $user ) {
			$uid    = $user->ID;
			$status = get_user_meta( $uid, self::USER_META_STATUS, true );
			$had_on = self::is_truthy( get_user_meta( $uid, self::USER_META_HAD_ON, true ) );
			$code   = get_user_meta( $uid, self::USER_META_CODE, true );

			$first = trim( (string) get_user_meta( $uid, 'first_name', true ) );
			$last  = trim( (string) get_user_meta( $uid, 'last_name',  true ) );
			$name  = trim( ($first . ' ' . $last) ) ?: $user->display_name;

			$mitgliedsnr  = get_user_meta( $uid, self::USER_META_ZOHO_MNR,    true );
			$mitgliedsart = get_user_meta( $uid, self::USER_META_ZOHO_ART,    true );
			$zoho_status  = get_user_meta( $uid, self::USER_META_ZOHO_STATUS, true );

			$rows[] = [
				'user_id'        => $uid,
				'display_name'   => $name,
				'user_email'     => $user->user_email,
				'mitgliedsnummer'=> (string) ($mitgliedsnr  !== '' ? $mitgliedsnr  : '—'),
				'mitgliedsart'   => (string) ($mitgliedsart !== '' ? $mitgliedsart : '—'),
				'status'         => (string) ($zoho_status  !== '' ? $zoho_status  : '—'),
				'code'           => (string) $code,
				'had_on'         => $had_on ? '1' : '0',
				'current_on'     => ($status === 'on') ? '1' : '0',
			];
		}
		return $rows;
	}

	private function temp_path_with_ext( $basename ) {
		$upload = wp_upload_dir();
		$dir = trailingslashit( $upload['basedir'] );
		if ( ! is_dir( $dir ) || ! is_writable( $dir ) ) { $dir = WP_CONTENT_DIR . '/uploads/'; if ( ! is_dir( $dir ) ) { wp_mkdir_p( $dir ); } }
		$path = $dir . $basename;
		$i = 0; $parts = pathinfo( $path );
		while ( file_exists( $path ) ) { $i++; $path = $parts['dirname'] . '/' . $parts['filename'] . '-' . $i . '.' . $parts['extension']; }
		return $path;
	}

	public function handle_export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No permissions.' ); }
		check_admin_referer( self::ACTION_EXPORT_CSV );

		$rows = $this->collect_rows_for_csv_export();
		$csv_path = $this->temp_path_with_ext( 'dgptm_teilnehmer.csv' );
	$fh = fopen( $csv_path, 'w' );
		// UTF-8 BOM für bessere Lesbarkeit in Excel/Windows
		fprintf( $fh, "\xEF\xBB\xBF" );
		// CSV-Header im XLSX-Format (27 Spalten)
		fputcsv( $fh, [ 
			'Nachname',  // Spalte 1
			'Vorname',  // Spalte 2
			'Anrede',  // Spalte 3
			'E-Mail',  // Spalte 4
			'Telefon',  // Spalte 5
			'Firma',  // Spalte 6
			'Gruppe',  // Spalte 7
			'Rolle',  // Spalte 8
			'Vertreter von' . "\n" . '(Vollmitglied ID)',  // Spalte 9
			'Gerät-Nr.',  // Spalte 10
			'ID',  // Spalte 11
			'Gewichtung',  // Spalte 12
			'Vollmacht an' . "\n" . '(Vertreter ID)',  // Spalte 13
			'PlanX',  // Spalte 14
			'PlanY',  // Spalte 15
			'Kandidatennummer',  // Spalte 16
			'PIN',  // Spalte 17
			'Kompetenz 1',  // Spalte 18
			'Kompetenz 2',  // Spalte 19
			'Kompetenz 3',  // Spalte 20
			'Kompetenz 4',  // Spalte 21
			'Kompetenz 5',  // Spalte 22
			'Kompetenz 6',  // Spalte 23
			'Kompetenz 7',  // Spalte 24
			'Kompetenz 8',  // Spalte 25
			'Kompetenz 9',  // Spalte 26
			'Kompetenz 10'  // Spalte 27
		] );
		foreach ( $rows as $r ) { fputcsv( $fh, $r ); }
		fclose( $fh );

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . basename( $csv_path ) );
		readfile( $csv_path ); @unlink( $csv_path ); exit;
	}

	public function handle_export_pdf() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No permissions.' ); }
		check_admin_referer( self::ACTION_EXPORT_PDF );

		$pdf_path = $this->generate_pdf();
		if ( ! $pdf_path ) { wp_die( 'PDF-Erstellung fehlgeschlagen (FPDF nicht gefunden?)' ); }

		nocache_headers();
		header( 'Content-Type: application/pdf' );
		header( 'Content-Disposition: attachment; filename=' . basename( $pdf_path ) );
		readfile( $pdf_path ); @unlink( $pdf_path ); exit;
	}

	public function handle_send_both() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No permissions.' ); }
		check_admin_referer( self::ACTION_SEND_BOTH );
		$this->send_summary_mail();
		wp_safe_redirect( add_query_arg( [ 'sent_both' => '1' ], wp_get_referer() ?: admin_url() ) ); exit;
	}

	private function generate_pdf() {
		$fpdf_path = dirname( __FILE__ ) . '/../fpdf/fpdf.php';
		if ( ! file_exists( $fpdf_path ) ) { return false; }
		require_once $fpdf_path;

		$rows = $this->collect_rows_for_pdf();

		$pdf = new \FPDF( 'P', 'mm', 'A4' );
		$pdf->AddPage();

		$title = 'DGPTM - Teilnehmerliste';
		$pdf->SetFont( 'Arial', 'B', 14 );
		$pdf->Cell( 0, 10, $this->pdf_txt( $title ), 0, 1, 'C' );
		$pdf->Ln( 2 );
		$pdf->SetFont( 'Arial', '', 9 );

		$headers = [ 'Name', 'E-Mail', 'Mitgliedsnummer', 'Mitgliedsart', 'Status', 'Code' ];
		$widths  = [ 45, 45, 25, 28, 25, 22 ];
		$row_h   = 6;

		$pdf->SetFillColor( 230, 230, 230 );
		foreach ( $headers as $i => $h ) { $pdf->Cell( $widths[$i], 7, $this->pdf_txt( $h ), 1, 0, 'C', true ); }
		$pdf->Ln();

		foreach ( $rows as $r ) {
			$y_start = $pdf->GetY();
			$x_start = $pdf->GetX();

			$cells = [ $this->pdf_txt( $r['display_name'] ), $this->pdf_txt( $r['user_email'] ), $this->pdf_txt( $r['mitgliedsnummer'] ),
			           $this->pdf_txt( $r['mitgliedsart'] ), $this->pdf_txt( $r['status'] ), $this->pdf_txt( $r['code'] ) ];

			for ( $i = 0; $i < count( $cells ); $i++ ) { $pdf->Cell( $widths[$i], $row_h, $cells[$i], 1 ); }
			$pdf->Ln();

			$is_strike = ( (string)$r['had_on'] === '1' && (string)$r['current_on'] === '0' );
			if ( $is_strike ) {
				$line_y = $y_start + ($row_h / 2);
				$pdf->SetDrawColor( 0, 0, 0 );
				$acc_x = $x_start;
				for ( $i = 0; $i < count( $widths ); $i++ ) {
					$pad = 1;
					$pdf->Line( $acc_x + $pad, $line_y, $acc_x + $widths[$i] - $pad, $line_y );
					$acc_x += $widths[$i];
				}
			}
		}

		$pdf->Ln( 5 );
		$pdf->SetFont( 'Arial', 'I', 9 );
		$note = 'Durchgestrichene Teilnehmer hatten die Onlineteilnahme beantragt und dann wieder zurückgezogen.';
		$pdf->MultiCell( 0, 5, $this->pdf_txt( $note ) );

		$pdf_path = $this->temp_path_with_ext( 'dgptm_teilnehmer.pdf' );
		$pdf->Output( 'F', $pdf_path );
		return $pdf_path;
	}

	private function pdf_txt( $s ) {
		$s = (string) $s;
		$map = [ '–'=>'-','—'=>'-','‚'=>',','‘'=>"'",'’'=>"'",'“'=>'"','”'=>'"','…'=>'...','•'=>'*' ];
		$s = strtr( $s, $map );
		$s = preg_replace( '/\s+/', ' ', $s );
		$out = @iconv( 'UTF-8', 'ISO-8859-1//TRANSLIT', $s );
		if ( $out === false ) { $out = mb_convert_encoding( $s, 'ISO-8859-1', 'UTF-8' ); }
		return $out;
	}

	public function send_summary_mail() {
		$opts = get_option( self::OPT_KEY, $this->defaults() );
		$to   = $opts['mail_recipient'];

		$rows = $this->collect_rows_for_csv_export();

		$csv_path = $this->temp_path_with_ext( 'dgptm_teilnehmer.csv' );
		$fh = fopen( $csv_path, 'w' );
		// UTF-8 BOM für bessere Lesbarkeit in Excel/Windows
		fprintf( $fh, "\xEF\xBB\xBF" );
		// CSV-Header im XLSX-Format (27 Spalten)
		fputcsv( $fh, [ 
			'Nachname',  // Spalte 1
			'Vorname',  // Spalte 2
			'Anrede',  // Spalte 3
			'E-Mail',  // Spalte 4
			'Telefon',  // Spalte 5
			'Firma',  // Spalte 6
			'Gruppe',  // Spalte 7
			'Rolle',  // Spalte 8
			'Vertreter von' . "\n" . '(Vollmitglied ID)',  // Spalte 9
			'Gerät-Nr.',  // Spalte 10
			'ID',  // Spalte 11
			'Gewichtung',  // Spalte 12
			'Vollmacht an' . "\n" . '(Vertreter ID)',  // Spalte 13
			'PlanX',  // Spalte 14
			'PlanY',  // Spalte 15
			'Kandidatennummer',  // Spalte 16
			'PIN',  // Spalte 17
			'Kompetenz 1',  // Spalte 18
			'Kompetenz 2',  // Spalte 19
			'Kompetenz 3',  // Spalte 20
			'Kompetenz 4',  // Spalte 21
			'Kompetenz 5',  // Spalte 22
			'Kompetenz 6',  // Spalte 23
			'Kompetenz 7',  // Spalte 24
			'Kompetenz 8',  // Spalte 25
			'Kompetenz 9',  // Spalte 26
			'Kompetenz 10'  // Spalte 27
		] );
		foreach ( $rows as $r ) { fputcsv( $fh, $r ); }
		fclose( $fh );

		$pdf_path = $this->generate_pdf();
		$attachments = array_filter( [ $csv_path, $pdf_path ], 'file_exists' );

		$blogname = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$from     = get_option( 'admin_email' );
		$headers  = [ 'Content-Type: text/plain; charset=UTF-8', 'From: ' . $blogname . ' <' . $from . '>' ];

		$body = "Anbei die aktuelle Teilnehmerliste als CSV und PDF.\nDiese Nachricht wurde automatisiert vom DGPTM-Online-Abstimmen-Plugin versendet.\n";
		$sent = wp_mail( $to, 'DGPTM – Teilnehmerliste (CSV & PDF)', $body, $headers, $attachments );

		foreach ( $attachments as $p ) { if ( file_exists( $p ) ) { @unlink( $p ); } }
		if ( $sent ) { update_option( self::OPT_AUTO_SENT_AT, time(), false ); }

		return $sent;
	}

	public function reschedule_autosend( $old_value, $value, $option ) {
		wp_clear_scheduled_hook( self::CRON_HOOK );
		$ts = $this->parse_end_ts( $value );
		if ( $ts && $ts > time() ) { wp_schedule_single_event( $ts, self::CRON_HOOK ); update_option( self::OPT_SCHEDULE_TS, $ts, false ); }
		else { update_option( self::OPT_SCHEDULE_TS, 0, false ); }
	}

	public function failsafe_autosend_if_due() {
		$opts = get_option( self::OPT_KEY, $this->defaults() );
		$end  = $this->parse_end_ts( $opts );
		$auto_sent_at = (int) get_option( self::OPT_AUTO_SENT_AT, 0 );
		$next_sched   = wp_next_scheduled( self::CRON_HOOK );
		if ( time() >= $end ) {
			if ( $auto_sent_at && $auto_sent_at >= $end ) return;
			if ( ! $next_sched ) { $this->send_summary_mail(); }
		}
	}

	private function parse_end_ts( $opts ) {
		$tz = wp_timezone();
		if ( empty( $opts['end_datetime'] ) ) return 0;
		$end = DateTime::createFromFormat( 'Y-m-d H:i', $opts['end_datetime'], $tz );
		if ( ! ( $end instanceof DateTime ) ) return 0;
		return $end->getTimestamp();
	}

	/** ===== Helper ===== */
	private static function is_truthy( $v ) {
		if ( is_bool( $v ) ) return $v;
		$true_vals = [ '1','true','yes','on','ja','wahr' ];
		return in_array( strtolower( (string) $v ), $true_vals, true ) || $v === 1;
	}

	/* ==========================
	 * Zoom S2S – Integration, REST & Logging
	 * ========================== */

	private function zoom_ready(array $opts): bool {
		return !empty($opts['zoom_enable'])
			&& !empty($opts['zoom_meeting_number'])
			&& !empty($opts['zoom_s2s_account_id'])
			&& !empty($opts['zoom_s2s_client_id'])
			&& !empty($opts['zoom_s2s_client_secret']);
	}

	/** Secret aus Optionen – bevorzugt HMAC-Secret, sonst legacy Token */
	private function zoom_webhook_secret_from_opts(array $opts): string {
		$sec = trim((string)($opts['zoom_webhook_secret'] ?? ''));
		if ($sec === '') { $sec = trim((string)($opts['zoom_webhook_token'] ?? '')); }
		return $sec;
	}

	/** Garantiert gültigen Kind-Wert (options) */
	private function zoom_kind(array $opts): string {
		$k = strtolower((string)($opts['zoom_kind'] ?? 'auto'));
		return in_array($k, ['meeting','webinar','auto'], true) ? $k : 'auto';
	}

	/** Bereinigte Meeting/Webinar-ID (options) */
	private function zoom_id(array $opts): string {
		return preg_replace('/\D/','', (string)($opts['zoom_meeting_number'] ?? ''));
	}

	/** Prüft ausschließlich die Zoom-Signatur gemäß Zoom-Doku. */
	private function zoom_only_signature_ok(\WP_REST_Request $req, string $secret): bool {
		if ($secret === '') { $this->dbg('sig', ['ok'=>false,'why'=>'no_secret']); return false; }

		$sigHeader = trim((string)$req->get_header('x-zm-signature'));
		$tsHeader  = trim((string)$req->get_header('x-zm-request-timestamp'));
		if ($sigHeader === '' || $tsHeader === '') {
			$this->dbg('sig', ['ok'=>false,'why'=>'missing_header','have_sig'=>$sigHeader!==''?'1':'0','have_ts'=>$tsHeader!==''?'1':'0']);
			return false;
		}
		if (!ctype_digit($tsHeader)) {
			$this->dbg('sig', ['ok'=>false,'why'=>'non_numeric_ts','ts'=>$tsHeader]);
			return false;
		}

		$ts  = (int) $tsHeader;
		$now = time();
		if ($ts < ($now - 300) || $ts > ($now + 300)) {
			$this->dbg('sig', ['ok'=>false,'why'=>'ts_skew','ts'=>$ts,'now'=>$now,'skew_sec'=>($ts-$now)]);
			return false;
		}

		$raw = (string)$req->get_body();                    // RAW-Body
		$base = 'v0:' . $ts . ':' . $raw;
		$digest = hash_hmac('sha256', $base, $secret);      // hex
		$expected = 'v0=' . $digest;

		$ok = hash_equals($expected, $sigHeader);
		if (!$ok) {
			$this->dbg('sig', [
				'ok'=>false,'why'=>'mismatch',
				'sig_recv_preview'=>substr($sigHeader,0,20).'…',
				'sig_exp_preview' =>substr($expected,0,20).'…',
				'len_body'=>strlen($raw)
			]);
		} else {
			$this->dbg('sig', ['ok'=>true]);
		}
		return $ok;
	}

	/** Zoom-Log (Option) */
	private function zoom_log_add(array $entry): void {
		$opts = get_option(self::OPT_KEY, $this->defaults());
		if (empty($opts['zoom_log_enable'])) { return; }
		$e = $entry;
		$e['when'] = wp_date('Y-m-d H:i:s');
		$log = get_option(self::OPT_ZOOM_LOG, []);
		if (!is_array($log)) { $log = []; }
		$log[] = $e;
		if (count($log) > self::ZOOM_LOG_MAX) { $log = array_slice($log, -self::ZOOM_LOG_MAX); }
		update_option(self::OPT_ZOOM_LOG, $log, false);
	}
	private function zoom_log_mask($v) {
		if (is_array($v)) {
			$out = [];
			foreach ($v as $k => $x) {
				$lk = strtolower((string)$k);
				if (in_array($lk, ['authorization','access_token','refresh_token','client_secret','password','token','x-zm-signature'], true)) {
					$out[$k] = '***';
				} else {
					$out[$k] = $this->zoom_log_mask($x);
				}
			}
			return $out;
		}
		if (is_string($v)) {
			return (strlen($v) > 1000) ? (substr($v, 0, 1000) . '…') : $v;
		}
		return $v;
	}
	private function zoom_log_body_trunc($data, int $max = 4000): string {
		$s = is_string($data) ? $data : json_encode($data, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
		if ($s === false) { $s = (string) print_r($data, true); }
		if (strlen($s) > $max) { $s = substr($s, 0, $max) . '…'; }
		return $s;
	}

	/** Zoom API: Token holen */
	private function zoom_token(array $opts, bool $force=false) {
		$key='dgptm_vote_s2s_'.md5($opts['zoom_s2s_account_id'].'|'.$opts['zoom_s2s_client_id']);
		if(!$force && ($c=get_transient($key))) return $c;
		$auth=base64_encode($opts['zoom_s2s_client_id'].':'.$opts['zoom_s2s_client_secret']);
		$url = add_query_arg(['grant_type'=>'account_credentials','account_id'=>$opts['zoom_s2s_account_id']], 'https://zoom.us/oauth/token');

		$this->zoom_log_add([
			'phase'=>'token','method'=>'POST','path'=>'/oauth/token','ok'=>null,'code'=>null,
			'request'=>['url'=>$url,'headers'=>['Authorization'=>'Basic ***','Content-Type'=>'application/x-www-form-urlencoded','Accept'=>'application/json']]
		]);

		$r = wp_remote_post($url, ['timeout'=>15,'headers'=>['Authorization'=>'Basic '.$auth,'Content-Type'=>'application/x-www-form-urlencoded','Accept'=>'application/json'],'body'=>'']);
		if(is_wp_error($r)) {
			$this->zoom_log_add(['phase'=>'token','method'=>'POST','path'=>'/oauth/token','ok'=>false,'code'=>0,'error'=>$r->get_error_message()]);
			throw new \RuntimeException($r->get_error_message());
		}
		$c=wp_remote_retrieve_response_code($r); $bRaw=wp_remote_retrieve_body($r); $b=json_decode($bRaw,true);
		$logBody = $b; if (is_array($logBody)) { unset($logBody['access_token']); unset($logBody['refresh_token']); }
		$this->zoom_log_add([
			'phase'=>'token','method'=>'POST','path'=>'/oauth/token','ok'=>($c>=200&&$c<300),'code'=>$c,
			'response'=>$this->zoom_log_body_trunc($this->zoom_log_mask($logBody), 1200)
		]);

		if($c<200||$c>=300||empty($b['access_token'])) throw new \RuntimeException('S2S Token HTTP '.$c);
		set_transient($key,$b,max(60,(int)$b['expires_in']-60)); return $b;
	}

	/** Zoom API: Request ausführen + Logging */
	private function zoom_api($opts,$method,$path,$payload=null,$kind=null,$id=null){
		$tok=$this->zoom_token($opts);
		$args=['timeout'=>20,'headers'=>['Authorization'=>'Bearer '.$tok['access_token'],'Content-Type'=>'application/json','Accept'=>'application/json']];
		$url='https://api.zoom.us/v2'.$path;

		$entryReq = ['phase'=>'api','method'=>$method,'path'=>$path,'kind'=>$kind,'id'=>$id,'request'=>$this->zoom_log_mask($payload)];
		$this->zoom_log_add($entryReq);

		if($method==='GET') $r=wp_remote_get($url,$args);
		else $r=wp_remote_request($url,$args+['method'=>$method,'body'=>$payload?json_encode($payload):'']);

		if(is_wp_error($r)) {
			$this->zoom_log_add(['phase'=>'api','method'=>$method,'path'=>$path,'kind'=>$kind,'id'=>$id,'ok'=>false,'code'=>0,'error'=>$r->get_error_message()]);
			throw new \RuntimeException($r->get_error_message());
		}
		$c=wp_remote_retrieve_response_code($r); $body=wp_remote_retrieve_body($r); $j=json_decode($body,true);
		$this->zoom_log_add([
			'phase'=>'api','method'=>$method,'path'=>$path,'kind'=>$kind,'id'=>$id,'ok'=>($c>=200&&$c<300),'code'=>$c,
			'response'=>$this->zoom_log_body_trunc($this->zoom_log_mask($j ?? $body))
		]);
		if($c<200||$c>=300) throw new \RuntimeException('Zoom API '.$c.': '.substr($body,0,400));
		return $j;
	}

	private function zoom_resource($opts, ?string $kind=null, ?string $id=null){
		list($kind,$id) = $this->zoom_pair($opts,$kind,$id);
		return ($kind==='webinar') ? $this->zoom_api($opts,'GET',"/webinars/$id",null,$kind,$id) : $this->zoom_api($opts,'GET',"/meetings/$id",null,$kind,$id);
	}

	private function zoom_required_std_fields_pair($opts,$kind,$id): array {
		try{
			$path = ($kind==='webinar') ? "/webinars/$id/registrants/questions" : "/meetings/$id/registrants/questions";
			$q = $this->zoom_api($opts,'GET',$path,null,$kind,$id);
			$req=['email'=>true,'first_name'=>true];
			foreach(($q['questions']??[]) as $it){
				if(!empty($it['required']) && !empty($it['field_name'])){ $req[$it['field_name']]=true; }
			}
			return array_keys($req);
		}catch(\Throwable $e){
			return ['email','first_name'];
		}
	}

	private function zoom_add_registrant($opts,$payload, ?string $kind=null, ?string $id=null){
		list($kind,$id) = $this->zoom_pair($opts,$kind,$id);
		$path = ($kind==='webinar') ? "/webinars/$id/registrants" : "/meetings/$id/registrants";
		return $this->zoom_api($opts,'POST',$path,$payload,$kind,$id);
	}

	private function zoom_update_status($opts, $action, $email, ?string $kind = null, ?string $id = null, ?string $occurrence_id = null){
		list($kind, $id) = $this->zoom_pair($opts, $kind, $id);
		$path = ($kind === 'webinar') ? "/webinars/$id/registrants/status" : "/meetings/$id/registrants/status";
		if ($occurrence_id) { $path .= (strpos($path,"?")===false ? "?" : "&") . "occurrence_id=" . rawurlencode($occurrence_id); }
		$payload = ['action'=>$action,'registrants'=>[['email'=>$email]]];

		try {
			return $this->zoom_api($opts, 'PUT', $path, $payload, $kind, $id);
		} catch (\RuntimeException $e) {
			if ($kind === 'webinar' && $action === 'cancel') {
				$payload['action'] = 'deny';
				return $this->zoom_api($opts, 'PUT', $path, $payload, $kind, $id);
			}
			throw $e;
		}
	}

	private function zoom_find_join_url($opts,$email, ?string $kind=null, ?string $id=null){
		list($kind,$id) = $this->zoom_pair($opts,$kind,$id);
		foreach (['approved','pending'] as $status){
			$path = ($kind==='webinar') ? "/webinars/$id/registrants?status=$status&page_size=300" : "/meetings/$id/registrants?status=$status&page_size=300";
			$list=$this->zoom_api($opts,'GET',$path,null,$kind,$id);
			foreach(($list['registrants']??[]) as $r){
				if(strtolower($r['email']??'')===strtolower($email)){
					if(!empty($r['join_url'])) return [$r['join_url'],$status];
				}
			}
		}
		return [null,null];
	}
	
	/** Registrant anhand der E-Mail suchen (approved|pending|denied) */
private function zoom_find_registrant_by_email($opts, string $email, ?string $kind = null, ?string $id = null, ?string $occurrence_id = null) : ?array {
	list($kind,$id) = $this->zoom_pair($opts,$kind,$id);
	$email = strtolower(trim($email));
	foreach (['approved','pending','denied'] as $status) {
		$path = ($kind==='webinar')
			? "/webinars/$id/registrants?status=$status&page_size=300"
			: "/meetings/$id/registrants?status=$status&page_size=300";
		if ($occurrence_id) { $path .= "&occurrence_id=".rawurlencode($occurrence_id); }
		$list = $this->zoom_api($opts,'GET',$path,null,$kind,$id);
		foreach (($list['registrants'] ?? []) as $r) {
			if (strtolower($r['email'] ?? '') === $email) {
				$rid = (string)($r['id'] ?? $r['registrant_id'] ?? '');
				if ($rid !== '') return ['registrant_id'=>$rid, 'status'=>$status, 'item'=>$r];
			}
		}
	}
	return null;
}

	/** Registranten wirklich in Zoom löschen (DELETE) – true bei Erfolg */
private function zoom_delete_registrant($opts, string $email, ?string $kind = null, ?string $id = null, ?string $occurrence_id = null): bool {
	list($kind,$id) = $this->zoom_pair($opts,$kind,$id);
	$found = $this->zoom_find_registrant_by_email($opts, $email, $kind, $id, $occurrence_id);
	if (!$found) { return false; } // nichts zu löschen
	$rid  = $found['registrant_id'];
	$path = ($kind==='webinar') ? "/webinars/$id/registrants/$rid" : "/meetings/$id/registrants/$rid";
	if ($occurrence_id) { $path .= "?occurrence_id=".rawurlencode($occurrence_id); }
	$this->zoom_api($opts,'DELETE',$path,null,$kind,$id); // loggt automatisch
	return true;
}


	private function zoom_resolve_kind_by_id(array $opts, string $id): string {
		$id = preg_replace('/\D/','',$id);
		$set = $this->zoom_kind($opts);
		if ($set!=='auto') return $set;
		try { $m = $this->zoom_api($opts,'GET',"/meetings/$id",null,'meeting',$id); if (!empty($m['id'])) return 'meeting'; } catch(\Throwable $e){}
		try { $w = $this->zoom_api($opts,'GET',"/webinars/$id",null,'webinar',$id); if (!empty($w['id'])) return 'webinar'; } catch(\Throwable $e){}
		throw new \RuntimeException('unknown_resource');
	}

	private function zoom_pair(array $opts, ?string $kindOverride, ?string $idOverride): array {
		$id = preg_replace('/\D/','', (string)($idOverride ?: ($opts['zoom_meeting_number'] ?? '')));
		$kind = strtolower( (string)($kindOverride ?: ($opts['zoom_kind'] ?? 'auto')) );
		if ($kind === '' || $kind === 'auto') {
			$kind = $this->zoom_resolve_kind_by_id($opts,$id);
		}
		if ($kind !== 'meeting' && $kind !== 'webinar') { $kind = 'webinar'; }
		return [$kind,$id];
	}

	private function zoom_store_user($uid,$join,$state){
		if($join) update_user_meta($uid,self::USER_META_ZOOM_JOIN,$join);
		if($state) update_user_meta($uid,self::USER_META_ZOOM_STATE,$state);
		update_user_meta($uid,self::USER_META_TS,time());
	}

	private function zoom_register_user(array $opts,int $uid,string $firstName,string $lastName,string $email, ?string $kindOverride=null, ?string $idOverride=null): array {
		list($kind,$id) = $this->zoom_pair($opts,$kindOverride,$idOverride);
		$required = $this->zoom_required_std_fields_pair($opts,$kind,$id);
		$payload=['email'=>$email];
		if(in_array('first_name',$required,true)) $payload['first_name']= $firstName !== '' ? $firstName : 'Teilnehmer';
		if(in_array('last_name',$required,true))  $payload['last_name']= $lastName  !== '' ? $lastName  : '-';

		try{
			$add = $this->zoom_add_registrant($opts,$payload,$kind,$id);
			$join= (string)($add['join_url']??'');
			if(!$join){
				if ($kind==='meeting') { $this->zoom_update_status($opts,'approve',$email,$kind,$id); }
				list($join,$st) = $this->zoom_find_join_url($opts,$email,$kind,$id);
			}
			if(!$join) throw new \RuntimeException('join_url_missing_after_create');
			$this->zoom_store_user($uid,$join,'approved');
			return ['created'=>true,'join_url'=>$join,'note'=>'registrant_created'];
		}catch(\Throwable $e){
			if ( stripos($e->getMessage(),'last_name')!==false && empty($payload['last_name']) ) {
				$payload['last_name'] = ($lastName!==''?$lastName:'-');
				$add = $this->zoom_add_registrant($opts,$payload,$kind,$id);
				$join= (string)($add['join_url']??'');
				if(!$join){
					if ($kind==='meeting') { $this->zoom_update_status($opts,'approve',$email,$kind,$id); }
					list($join,$st) = $this->zoom_find_join_url($opts,$email,$kind,$id);
				}
				if(!$join) throw new \RuntimeException('join_url_missing_after_create');
				$this->zoom_store_user($uid,$join,'approved');
				return ['created'=>true,'join_url'=>$join,'note'=>'registrant_created_with_last_name'];
			}
			try{
				if ($kind==='meeting') { $this->zoom_update_status($opts,'approve',$email,$kind,$id); }
				list($join,$st) = $this->zoom_find_join_url($opts,$email,$kind,$id);
				if($join){
					$this->zoom_store_user($uid,$join,'approved');
					return ['created'=>false,'join_url'=>$join,'note'=>'reapproved_or_found'];
				}
			}catch(\Throwable $e2){}
			throw $e;
		}
	}

private function zoom_cancel_user(array $opts, int $uid, string $email, ?string $kindOverride = null, ?string $idOverride = null): void {
	list($kind,$id) = $this->zoom_pair($opts, $kindOverride, $idOverride);
	$occurrence_id = null; // optional: falls du mit Occurrences arbeitest, hier setzen

	try {
		// 1) HARTE LÖSCHUNG versuchen
		$deleted = $this->zoom_delete_registrant($opts, $email, $kind, $id, $occurrence_id);
		delete_user_meta($uid, self::USER_META_ZOOM_JOIN);
		update_user_meta($uid, self::USER_META_ZOOM_STATE, $deleted ? 'deleted' : ($kind==='webinar' ? 'denied' : 'cancelled'));
		if ($deleted) return; // fertig

		// 2) Fallback auf Status-Änderung (altes Verhalten)
		$this->zoom_update_status($opts, 'cancel', $email, $kind, $id, $occurrence_id);
		delete_user_meta($uid, self::USER_META_ZOOM_JOIN);
		update_user_meta($uid, self::USER_META_ZOOM_STATE, $kind==='webinar' ? 'denied' : 'cancelled');
	} catch (\Throwable $e) {
		// Webinare: als Fallback auf deny wechseln, wenn cancel scheitert
		if ($kind === 'webinar') {
			try {
				$this->zoom_update_status($opts, 'deny', $email, $kind, $id, $occurrence_id);
				delete_user_meta($uid, self::USER_META_ZOOM_JOIN);
				update_user_meta($uid, self::USER_META_ZOOM_STATE, 'denied');
				return;
			} catch (\Throwable $e2) { /* durchfallen */ }
		}
		throw $e;
	}
}


	

public function shortcode_presence_table( $atts ){
    $opts = get_option( self::OPT_KEY, $this->defaults() );
    $a = shortcode_atts([
        'meeting_number' => $opts['zoom_meeting_number'] ?? '',
        'kind'           => $opts['zoom_kind'] ?? 'auto',
        'refresh'        => '10',
        'allow_delete'   => '1',
        'show_export'    => '1',
        'title'          => 'Anwesenheitsliste'
    ], $atts, 'dgptm_presence_table');

    $mid  = preg_replace('/\D/','', (string)$a['meeting_number']);
    $kind = strtolower((string)$a['kind']);
    if (!in_array($kind,['auto','meeting','webinar'],true)) $kind='auto';

    $uid = 'dgptm-pres-'.wp_generate_password(6,false,false);

    ob_start(); ?>
<div id="<?php echo esc_attr($uid); ?>" class="dgptm-presence-ui" data-meeting="<?php echo esc_attr($mid); ?>" data-kind="<?php echo esc_attr($kind); ?>" data-refresh="<?php echo esc_attr($a['refresh']); ?>" data-allowdel="<?php echo esc_attr($a['allow_delete']); ?>">
  <h3><?php echo esc_html($a['title']); ?> <small>(<span class="dgptm-pres-count">0</span>)</small></h3>
  <!-- NEU: Statistik-Zeile -->
  <div class="dgptm-presence-stats">
    Präsenz: <strong class="count-presence">0</strong> ·
    Online: <strong class="count-online">0</strong> ·
    Gesamt: <strong class="count-total dgptm-pres-count">0</strong>
  </div>
  <div class="dgptm-presence-toolbar">
    <a href="#" class="btn js-refresh">Aktualisieren</a>
    <?php if ($a['show_export']==='1'): ?>
    <a href="#" class="btn primary js-export">PDF exportieren</a>
    <?php endif; ?>
  </div>
  <table class="dgptm-presence-table">
    <thead>
      <tr>
        <th>Name</th>
        <th>Email</th>
        <th>Status</th>
        <th>Anwesend ab</th>
        <th>Art</th>
      </tr>
    </thead>
    <tbody>
      <tr><td colspan="6"><em>Lade Daten…</em></td></tr>
    </tbody>
  </table>
</div>
<?php
    return ob_get_clean();
}



	
	private function collect_zoom_rows(): array {
		$args=['meta_query'=>[['key'=>self::USER_META_ZOOM_JOIN,'compare'=>'EXISTS']],'number'=>9999,'fields'=>'all'];
		$users=get_users($args); $rows=[];
		foreach($users as $u){
			$first=trim((string)get_user_meta($u->ID,'first_name',true));
			$last =trim((string)get_user_meta($u->ID,'last_name',true));
			$name =trim(($first.' '.$last))?:$u->display_name;
			$rows[]=[
				'name'=>$name,
				'email'=>$u->user_email,
				'join_url'=>(string)get_user_meta($u->ID,self::USER_META_ZOOM_JOIN,true),
				'zoom_state'=>(string)get_user_meta($u->ID,self::USER_META_ZOOM_STATE,true),
				'ts'=>(int)(get_user_meta($u->ID,self::USER_META_TS,true)?:time())
			];
		}
		usort($rows,function($a,$b){return ($b['ts']??0)<=>($a['ts']??0);});
		return $rows;
	}

	private function zoom_list_registrants_all($opts): array {
		$all=[];
		$kind=$this->zoom_kind($opts); $id=$this->zoom_id($opts);
		if ($kind==='auto') { $kind=$this->zoom_resolve_kind_by_id($opts,$id); }
		foreach(['approved','pending','denied'] as $st){
			$path = ($kind==='webinar') ? "/webinars/$id/registrants?status=$st&page_size=300" : "/meetings/$id/registrants?status=$st&page_size=300";
			$list=$this->zoom_api($opts,'GET',$path,null,$kind,$id);
			foreach(($list['registrants']??[]) as $r){ $r['status']=$st; $all[]=$r; }
		}
		$uniq=[]; foreach($all as $r){ $key=strtolower($r['email']??''); if($key!=='') $uniq[$key]=$r; }
		return array_values($uniq);
	}

	/* ===== REST (bestehend) – NUR /zoom-test und /zoom-register ===== */
	public function register_rest() {
		register_rest_route(self::REST_NS, self::R_ZOOM_TEST, [
			'methods'  => 'POST',
			'callback' => [ $this, 'rest_zoom_test' ],
			'permission_callback' => '__return_true'
		]);
		register_rest_route(self::REST_NS, self::R_ZOOM_REGISTER, [
			'methods'  => 'POST',
			'callback' => [ $this, 'rest_zoom_register' ],
			'permission_callback' => '__return_true'
		]);
		// KEIN /webhook hier – wird in register_rest_zoom() registriert
	}
	public function rest_zoom_test(\WP_REST_Request $req){
		$opts = get_option( self::OPT_KEY, $this->defaults() );
		$checks=[]; $ok=true;

		if(!$this->zoom_ready($opts)){
			return new \WP_REST_Response(['ok'=>false,'error'=>'zoom_not_configured'],200);
		}
		try{
			$this->zoom_token($opts,true);
			$checks[]=['name'=>'token','ok'=>true];
		}catch(\Throwable $e){
			$checks[]=['name'=>'token','ok'=>false,'detail'=>$e->getMessage()]; $ok=false;
		}
		try{
			$r = $this->zoom_resource($opts,null,null);
			$checks[]=['name'=>'resource','ok'=>true,'id'=>$this->zoom_id($opts),'kind'=>$this->zoom_kind($opts),'topic'=>($r['topic']??''),'start_time'=>($r['start_time']??'')];
		}catch(\Throwable $e){
			$checks[]=['name'=>'resource','ok'=>false,'detail'=>$e->getMessage()]; $ok=false;
		}
		if($ok){
			try{
				$pairKind=$this->zoom_kind($opts); $pairId=$this->zoom_id($opts);
				if ($pairKind==='auto') { $pairKind=$this->zoom_resolve_kind_by_id($opts,$pairId); }
				$reqf = $this->zoom_required_std_fields_pair($opts,$pairKind,$pairId);
				$checks[]=['name'=>'required_fields','ok'=>true,'fields'=>$reqf];
			}catch(\Throwable $e){
				$checks[]=['name'=>'required_fields','ok'=>false,'detail'=>$e->getMessage()];
			}
		}
		return new \WP_REST_Response(['ok'=>$ok,'checks'=>$checks],200);
	}

	public function rest_zoom_register(\WP_REST_Request $req){
		if ( ! is_user_logged_in() ) { return new \WP_REST_Response(['ok'=>false,'error'=>'not_logged_in'],401); }
		$opts = get_option( self::OPT_KEY, $this->defaults() );
		if(!$this->zoom_ready($opts)){ return new \WP_REST_Response(['ok'=>false,'error'=>'zoom_not_configured'],400); }

		$uid  = get_current_user_id();
		$user = wp_get_current_user();

		$idn = $this->identity_for_zoom($uid, $user);
		if ( ! is_email($idn['email']) ) {
			return new \WP_REST_Response(['ok'=>false,'error'=>'invalid_email_from_zoho_wp'],400);
		}

		$body = $req->get_json_params();
		$kindOv = isset($body['kind']) ? sanitize_text_field($body['kind']) : '';
		$idOv   = isset($body['meeting_number']) ? preg_replace('/\D/','', (string)$body['meeting_number']) : '';

		try{
			$res = $this->zoom_register_user($opts,$uid,$idn['first'],$idn['last'],$idn['email'],$kindOv,$idOv);
			return new \WP_REST_Response(['ok'=>true,'join_url'=>$res['join_url'],'note'=>$res['note']],200);
		}catch(\Throwable $e){
			return new \WP_REST_Response(['ok'=>false,'error'=>$e->getMessage()],500);
		}
	}

	/* ===== Admin: Registrants Pull / Status ===== */
	public function handle_zoom_pull() {
		if ( ! current_user_can('manage_options') ) { wp_die('No permissions.'); }
		check_admin_referer( self::ACTION_ZOOM_PULL );
		wp_safe_redirect( admin_url('options-general.php?page=dgptm-online-abstimmen&zoom_refreshed=1') ); exit;
	}

	public function handle_zoom_status() {
		if ( ! current_user_can('manage_options') ) { wp_die('No permissions.'); }
		check_admin_referer( self::ACTION_ZOOM_STATUS );
		$opts  = get_option( self::OPT_KEY, $this->defaults() );
		$do    = isset($_GET['do']) ? sanitize_text_field($_GET['do']) : '';
		$email = isset($_GET['email']) ? sanitize_email($_GET['email']) : '';

		if(!$this->zoom_ready($opts) || !in_array($do,['approve','cancel'],true) || !is_email($email)){
			wp_safe_redirect( admin_url('options-general.php?page=dgptm-online-abstimmen&zoom_status=err') ); exit;
		}

		try{
			list($kind,$id) = $this->zoom_pair($opts, null, null);

			if ($do==='approve') {
    $this->zoom_update_status($opts,'approve',$email,$kind,$id);
    $u = get_user_by('email',$email);
    if($u){
        list($join,$st) = $this->zoom_find_join_url($opts,$email,$kind,$id);
        if($join){ $this->zoom_store_user($u->ID,$join,'approved'); }
    }
} else { // cancel in Admin = hart löschen + Fallback
    $deleted = false;
    try {
        $deleted = $this->zoom_delete_registrant($opts,$email,$kind,$id,null);
    } catch (\Throwable $e) { /* ignore, fallback folgt */ }
    if (!$deleted) {
        $this->zoom_update_status($opts,'cancel',$email,$kind,$id);
    }
    $u = get_user_by('email',$email);
    if($u){
        delete_user_meta($u->ID,self::USER_META_ZOOM_JOIN);
        update_user_meta($u->ID,self::USER_META_ZOOM_STATE, $deleted ? 'deleted' : ($kind==='webinar' ? 'denied' : 'cancelled'));
    }
}

			wp_safe_redirect( admin_url('options-general.php?page=dgptm-online-abstimmen&zoom_status=ok') ); exit;
		}catch(\Throwable $e){
			wp_safe_redirect( admin_url('options-general.php?page=dgptm-online-abstimmen&zoom_status=err&detail='.rawurlencode($e->getMessage())) ); exit;
		}
	}

	public function handle_zoom_sync() {
		if ( ! current_user_can('manage_options') ) { wp_die('No permissions.'); }
		check_admin_referer( self::ACTION_ZOOM_SYNC );
		$opts = get_option( self::OPT_KEY, $this->defaults() );
		if(!$this->zoom_ready($opts)){
			wp_safe_redirect( admin_url('options-general.php?page=dgptm-online-abstimmen&zoom_sync=cfg') ); exit;
		}
		$do = isset($_GET['do']) ? sanitize_text_field($_GET['do']) : '';
		try{
			$regs = $this->zoom_list_registrants_all($opts);
			$zoomSet=[];
			foreach ($regs as $r) { if (!empty($r['email'])) { $zoomSet[strtolower($r['email'])]=$r['status']??'approved'; } }
			$localOn = $this->get_local_on_users();
			$localSet=[];
			foreach ($localOn as $u) { $localSet[strtolower($u->user_email)]=$u; }

			$done=0; $fail=0;
			if ($do==='register_missing') {
				foreach ($localSet as $mail => $u) {
					if (!isset($zoomSet[$mail])) {
						$idn = $this->identity_for_zoom($u->ID, $u);
						if (!is_email($idn['email'])) { $fail++; continue; }
						try{
							$this->zoom_register_user($opts,$u->ID,$idn['first'],$idn['last'],$idn['email'],null,null);
							$done++;
						}catch(\Throwable $e){ $fail++; }
					}
				}
			} elseif ($do==='cancel_extras') {
				foreach ($zoomSet as $mail => $st) {
					if (!isset($localSet[$mail])) {
						try{
							$this->zoom_update_status($opts,'cancel',$mail,null,null);
							$u = get_user_by('email',$mail);
							if ($u) {
								delete_user_meta($u->ID,self::USER_META_ZOOM_JOIN);
								list($kind,$id) = $this->zoom_pair($opts, null, null);
								update_user_meta($u->ID,self::USER_META_ZOOM_STATE, $kind==='webinar' ? 'denied' : 'cancelled');
							}
							$done++;
						}catch(\Throwable $e){ $fail++; }
					}
				}
			}
			wp_safe_redirect( admin_url('options-general.php?page=dgptm-online-abstimmen&zoom_sync=ok&done='.$done.'&fail='.$fail) ); exit;
		}catch(\Throwable $e){
			wp_safe_redirect( admin_url('options-general.php?page=dgptm-online-abstimmen&zoom_sync=err&detail='.rawurlencode($e->getMessage())) ); exit;
		}
	}

	/* ===== Eigenständig belassener Shortcode ===== */
	public function shortcode_zoom_register_and_join($atts){
		$opts = get_option( self::OPT_KEY, $this->defaults() );
		$a = shortcode_atts([
			'kind'           => $opts['zoom_kind'] ?? 'webinar',
			'meeting_number' => $opts['zoom_meeting_number'] ?? '',
			'label'          => 'Direkt teilnehmen',
			'mode'           => 'auto',  // auto|app|web
			'test'           => '0'
		], $atts, 'zoom_register_and_join');

		if ( ! is_user_logged_in() ) { return '<p><em>Bitte einloggen.</em></p>'; }

		$uid   = 'zraj_'.wp_generate_password(6,false,false);
		$rest  = esc_url_raw( rest_url( self::REST_NS . self::R_ZOOM_REGISTER ) );
		$nonce = wp_create_nonce('wp_rest');
		$live  = esc_url_raw( rest_url( 'dgptm-zoom/v1/live' ) );
		$mid   = preg_replace('/\D/','', (string)$a['meeting_number'] );
		$mode  = strtolower((string)$a['mode']);
		if ( ! in_array($mode,['auto','app','web'],true) ) $mode='auto';

		$optsAll = get_option( self::OPT_KEY, $this->defaults() );
		$showDiag = (!empty($optsAll['zoom_frontend_debug']) && $a['test']==='1');

		ob_start(); ?>
<div id="<?php echo esc_attr($uid); ?>" class="dgptm-zoom-regbtn">
  <a href="#" class="dgptm-zoom-btn"><?php echo esc_html($a['label']); ?></a>
  <?php if ($showDiag): ?><div class="dgptm-zoom-diag" style="margin-top:.5rem;font:12px monospace;white-space:pre-wrap;background:#f6f8fa;padding:.5rem;border:1px solid #d0d7de;border-radius:6px"></div><?php endif; ?>
</div>
<script>
(function(){
  const box=document.getElementById('<?php echo esc_js($uid); ?>');
  if(!box) return;
  const btn=box.querySelector('.dgptm-zoom-btn');
  const diag=box.querySelector('.dgptm-zoom-diag');
  function log(m,o){ if(!diag) return; diag.textContent+=(diag.textContent?"\n":"")+m+(o?(" :: "+JSON.stringify(o)):""); }
  async function isLive(){
    try{
      const r=await fetch('<?php echo $live; ?>',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':'<?php echo esc_js($nonce); ?>'},body:JSON.stringify({id:'<?php echo esc_js($mid); ?>',kind:'<?php echo esc_js(strtolower($a['kind'])); ?>'}),credentials:'same-origin'});
      const d=await r.json(); <?php if ($showDiag): ?>log('[live]',d);<?php endif; ?>
      return !!(d && d.live);
    }catch(_){ return true; }
  }
  function buildAppLink(joinUrl){
    try{
      const u=new URL(joinUrl); const id=u.pathname.split('/').pop(); const ep=u.host; const q=new URLSearchParams(u.search);
      const pwd=q.get('pwd')||''; const tk=q.get('tk')||'';
      const proto=/Android|iPhone|iPad|iPod/i.test(navigator.userAgent)?'zoomus://':'zoommtg://';
      return proto+ep+'/join?action=join&confno='+id+(pwd?('&pwd='+encodeURIComponent(pwd)):'')+(tk?('&tk='+encodeURIComponent(tk)):'');
    }catch(_){ return null; }
  }
  function openJoin(joinUrl){
    const mode='<?php echo esc_js($mode); ?>';
    if(mode==='web'){ window.location.href=joinUrl; return; }
    if(mode==='app'){ window.location.href=joinUrl; setTimeout(()=>{ const app=buildAppLink(joinUrl); if(app){ window.location.href=app; } },900); return; }
    const timer=setTimeout(()=>{ const app=buildAppLink(joinUrl); if(app){ window.location.href=app; } },1000);
    window.location.href=joinUrl; setTimeout(()=>clearTimeout(timer),2000);
  }
  async function registerAndMaybeJoin(){
    try{
      btn.setAttribute('disabled','disabled'); btn.classList.add('is-busy');
      const body={ meeting_number:'<?php echo esc_js($mid); ?>', kind:'<?php echo esc_js(strtolower($a['kind'])); ?>' };
      const r=await fetch('<?php echo $rest; ?>',{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':'<?php echo esc_js($nonce); ?>'},credentials:'same-origin',body:JSON.stringify(body)});
      const d=await r.json(); <?php if ($showDiag): ?>log('[register]',d);<?php endif; ?>
      if(!r.ok||!d?.ok) throw new Error(d?.error||('HTTP '+r.status));
      const live = await isLive();
      if(live){ openJoin(d.join_url); }
      else { alert('Registrierung erledigt. Dein persönlicher Teilnahme-Link ist gespeichert und funktioniert, sobald das Event live ist.'); }
    }catch(e){
      alert('Fehler: '+(e?.message||e));
    }finally{
      btn.removeAttribute('disabled'); btn.classList.remove('is-busy');
    }
  }
  btn.addEventListener('click',(ev)=>{ev.preventDefault(); registerAndMaybeJoin();});
})();
</script>
<style>
  .dgptm-zoom-regbtn .dgptm-zoom-btn{display:inline-block;padding:.6rem 1rem;border:1px solid #d0d7de;border-radius:6px;background:#0d6efd;color:#fff;font-weight:600;text-decoration:none}
  .dgptm-zoom-regbtn .dgptm-zoom-btn.is-busy{opacity:.7;pointer-events:none}
</style>
<?php
		return ob_get_clean();
	}

	/* ===== Zoom Log Admin-Actions ===== */
	public function handle_zoom_log_clear() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No permissions.' ); }
		check_admin_referer( self::ACTION_ZOOM_LOG_CLEAR );
		update_option(self::OPT_ZOOM_LOG, [], false);
		wp_safe_redirect( admin_url('options-general.php?page=dgptm-online-abstimmen&zoomlog_cleared=1') ); exit;
	}
	public function handle_zoom_log_download() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'No permissions.' ); }
		check_admin_referer( self::ACTION_ZOOM_LOG_DL );
		$log = get_option(self::OPT_ZOOM_LOG, []);
		if (!is_array($log)) $log=[];
		$json = json_encode($log, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
		nocache_headers();
		header('Content-Type: application/json; charset=utf-8');
		header('Content-Disposition: attachment; filename=dgptm_zoom_log_'.date('Ymd_His').'.json');
		echo $json; exit;
	}

	/* ==========================
	 * Zoom LIVE & Webhook – REST + Attendance + Präsenz
	 * ========================== */

	public function register_rest_zoom() {
		register_rest_route(self::REST_NS_ZOOM, self::R_ZOOM_LIVE, [
			'methods'  => 'POST',
			'callback' => [ $this, 'rest_zoom_live' ],
			'permission_callback' => '__return_true',
		]);
		register_rest_route(self::REST_NS_ZOOM, self::R_ZOOM_WEBHOOK, [
			'methods'  => 'POST',
			'callback' => [ $this, 'rest_zoom_webhook' ],
			'permission_callback' => '__return_true',
		]);
		// GET für Reachability-/Verfügbarkeitscheck
		register_rest_route(self::REST_NS_ZOOM, self::R_ZOOM_WEBHOOK, [
			'methods'  => 'GET',
			'callback' => function(){ return new \WP_REST_Response(['ok'=>true,'note'=>'Zoom webhook endpoint. Use POST.'], 200); },
			'permission_callback' => '__return_true',
		]);
		register_rest_route(self::REST_NS_ZOOM, self::R_ZOOM_PRESENCE, [
			'methods'  => 'POST',
			'callback' => [ $this, 'rest_zoom_presence' ],
			'permission_callback' => '__return_true',
		]);
register_rest_route(self::REST_NS_ZOOM, '/presence-list', [
		'methods'  => 'GET',
		'callback' => [ $this, 'rest_presence_list' ],
		'permission_callback' => function(){ return is_user_logged_in(); }
	]);
	register_rest_route(self::REST_NS_ZOOM, '/presence-delete', [
		'methods'  => 'POST',
		'callback' => [ $this, 'rest_presence_delete' ],
		'permission_callback' => function(){ return is_user_logged_in(); }
	]);
	register_rest_route(self::REST_NS_ZOOM, '/presence-pdf', [
		'methods'  => 'POST',
		'callback' => [ $this, 'rest_presence_pdf' ],
		'permission_callback' => function(){ return is_user_logged_in(); }
	]);
}

public function rest_presence_list( \WP_REST_Request $req ){
    $opts = get_option( self::OPT_KEY, $this->defaults() );
    $id   = preg_replace('/\D/','', (string)($req->get_param('id') ?: ($opts['zoom_meeting_number'] ?? '')) );
    $kind = strtolower((string)($req->get_param('kind') ?: ($opts['zoom_kind'] ?? 'auto')));
    if ($kind==='auto' || $kind==='') { try { $kind = $this->zoom_resolve_kind_by_id($opts, $id); } catch(\Throwable $e){ $kind='webinar'; } }

    $s = $this->attendance_store_get();
    $mkey = $this->attendance_mkey($kind,$id);
    $rowsRaw = $s[$mkey]['participants'] ?? [];

    $rows = [];
    // NEU: Statistik zählen
    $cntPresence = 0; $cntOnline = 0; // NEU
    foreach ($rowsRaw as $pk => $r) {
        $type = (string)($r['type'] ?? 'online');
        if ($type === 'presence') { $cntPresence++; } else { $cntOnline++; } // NEU

        $rows[] = [
            'pk'         => $pk,
            'name'       => (string)($r['name'] ?? ''),
            'email'      => (string)($r['email'] ?? ''),
            'status'     => (string)($r['status'] ?? ''),
            'join_first' => (int)($r['join_first'] ?? 0),
            'type'       => $type,
        ];
    }

    usort($rows, function($a,$b){ return strcasecmp($a['name'], $b['name']); });

    // NEU: stats zurückgeben
    $stats = ['presence'=>$cntPresence, 'online'=>$cntOnline, 'total'=>($cntPresence+$cntOnline)]; // NEU
    return new \WP_REST_Response(['ok'=>true, 'mkey'=>$mkey, 'rows'=>$rows, 'stats'=>$stats], 200); // NEU: stats
}


public function rest_presence_delete( \WP_REST_Request $req ){
	$body = (array)$req->get_json_params();
	$key  = isset($body['key']) ? sanitize_text_field($body['key']) : '';
	$pk   = isset($body['pk'])  ? sanitize_text_field($body['pk'])  : '';

	if ($key==='' || $pk==='') {
		return new \WP_REST_Response(['ok'=>false,'error'=>'missing_params'], 400);
	}
	$s = $this->attendance_store_get();
	if (!isset($s[$key]['participants'][$pk])) {
		return new \WP_REST_Response(['ok'=>false,'error'=>'not_found'], 404);
	}
	unset($s[$key]['participants'][$pk]);
	$this->attendance_store_put($s);

	return new \WP_REST_Response(['ok'=>true], 200);
}

public function rest_presence_pdf( \WP_REST_Request $req ){
    $fpdf_path = dirname( __FILE__ ) . '/../fpdf/fpdf.php';
    if ( ! file_exists( $fpdf_path ) ) {
        return new \WP_REST_Response(['ok'=>false,'error'=>'FPDF_not_found'], 500);
    }
    require_once $fpdf_path;

    $opts = get_option( self::OPT_KEY, $this->defaults() );
    $body = (array)$req->get_json_params();
    $id   = preg_replace('/\D/','', (string)($body['id'] ?? ($opts['zoom_meeting_number'] ?? '')) );
    $kind = strtolower((string)($body['kind'] ?? ($opts['zoom_kind'] ?? 'auto')));
    if ($kind==='auto' || $kind==='') { try { $kind = $this->zoom_resolve_kind_by_id($opts, $id); } catch(\Throwable $e){ $kind='webinar'; } }

    $s = $this->attendance_store_get();
    $mkey = $this->attendance_mkey($kind,$id);
    $rows = $s[$mkey]['participants'] ?? [];
    $topic= $s[$mkey]['topic'] ?? strtoupper($kind).' '.$id;

    // NEU: Statistik
    $cntPresence = 0; $cntOnline = 0;
    foreach ($rows as $r) { ($r['type'] ?? 'online') === 'presence' ? $cntPresence++ : $cntOnline++; }
    $cntTotal = $cntPresence + $cntOnline;

    $pdf = new \FPDF("P","mm","A4");
    $pdf->AddPage();
    $pdf->SetFont("Arial","B",14);
    $pdf->Cell(0,10,$this->pdf_txt("Anwesenheit – ".$topic),0,1,"C");

    // NEU: Statistik unter dem Titel
    $pdf->SetFont("Arial","",10);
    $pdf->Cell(0,6,$this->pdf_txt("Statistik: Präsenz: ".$cntPresence."  -  Online: ".$cntOnline."  -  Gesamt: ".$cntTotal),0,1,"C");
    $pdf->Ln(2);

    $pdf->SetFont("Arial","",9);
    $headers = ['Name','Email','Status','Anwesend ab','Art'];
    $widths  = [44,46,30,40,20];
    $row_h   = 6;

    $pdf->SetFillColor(230,230,230);
    foreach ($headers as $i=>$h) { $pdf->Cell($widths[$i],7,$this->pdf_txt($h),1,0,"C",true); }
    $pdf->Ln();

    uasort($rows, function($a,$b){ return strcasecmp($a['name']??'', $b['name']??''); });
    foreach ($rows as $r) {
        $cells = [
            $this->pdf_txt($r['name'] ?? ''),
            $this->pdf_txt($r['email'] ?? ''),
            $this->pdf_txt($r['status'] ?? ''),
            $this->pdf_txt( !empty($r['join_first']) ? wp_date('d.m.Y H:i', (int)$r['join_first']) : '' ),
            $this->pdf_txt( (( $r['type'] ?? 'online')==='presence'?'Präsenz':'Online') ),
        ];
        for($i=0;$i<count($cells);$i++){ $pdf->Cell($widths[$i], $row_h, $cells[$i], 1); }
        $pdf->Ln();
    }

    $upload = wp_upload_dir();
    $dir = trailingslashit($upload["basedir"]);
    if (!is_dir($dir)) { wp_mkdir_p($dir); }
    $fname = "dgptm_presence_".$kind."_".$id."_".date("Ymd_His").".pdf";
    $path = $dir.$fname; $url = trailingslashit($upload["baseurl"]).$fname;

    $pdf->Output("F", $path);

    return new \WP_REST_Response(['ok'=>true,'url'=>$url,'mkey'=>$mkey], 200);
}





	public function rest_zoom_live( \WP_REST_Request $req ) {
		$body = $req->get_json_params();
		$id   = isset($body['id'])   ? preg_replace('/\D/','', (string)$body['id']) : '';
		$kind = isset($body['kind']) ? strtolower((string)$body['kind']) : '';

		$opts = get_option( self::OPT_KEY, $this->defaults() );
		if ( $id === '' )   { $id   = preg_replace('/\D/','', (string)($opts['zoom_meeting_number'] ?? '') ); }
		if ( $kind === '' ) { $kind = strtolower((string)($opts['zoom_kind'] ?? 'auto')); }
		if ( $kind === 'auto' ) { try { $kind = $this->zoom_resolve_kind_by_id($opts, $id); } catch(\Throwable $e) { $kind='webinar'; } }

		$stat = $this->attendance_get_meeting_state($kind, $id);
		if ($stat !== null) {
			return new \WP_REST_Response(['live' => (bool)$stat, 'source'=>'webhook'], 200);
		}

		$live=false; $source='unknown';
		if ( $this->zoom_ready($opts) && $id !== '' ) {
			try {
				$r = $this->zoom_resource($opts, $kind, $id);
				$status = strtolower((string)($r['status'] ?? ''));
				$live = in_array($status, ['started','active'], true);
				$source='api';
			} catch(\Throwable $e) {}
		}
		return new \WP_REST_Response(['live'=>$live, 'source'=>$source], 200);
	}

	public function rest_zoom_webhook(\WP_REST_Request $req) {
		$opts   = get_option( self::OPT_KEY, $this->defaults() );
		$secret = $this->zoom_webhook_secret_from_opts($opts);

		$raw  = (string)$req->get_body();
		$data = json_decode($raw, true);

		// 1) URL-Validation (Zoom ruft als POST mit plainToken auf)
		if (is_array($data) && ($data['event'] ?? '') === 'endpoint.url_validation') {
			$plain = (string)($data['payload']['plainToken'] ?? '');
			if ($plain === '' || $secret === '') {
				$this->zoom_log_add(['phase'=>'webhook','event'=>'endpoint.url_validation','ok'=>false,'code'=>400,'why'=>'missing_plain_or_secret']);
				$this->dbg('webhook validation', ['ok'=>false,'why'=>'missing_plain_or_secret']);
				return new \WP_REST_Response([ 'message' => 'missing_plain_or_secret' ], 400);
			}
			$encrypted = hash_hmac('sha256', $plain, $secret);
			$this->zoom_log_add(['phase'=>'webhook','event'=>'endpoint.url_validation','ok'=>true,'code'=>200,'note'=>'validated']);
			$this->dbg('webhook validation', ['ok'=>true,'plainToken'=>$plain,'encryptedToken_preview'=>substr($encrypted,0,12).'…']);
			return new \WP_REST_Response([ 'plainToken' => $plain, 'encryptedToken' => $encrypted ], 200);
		}

		// 2) Laufende Events: Signatur prüfen (einmal)
		$okSig = $this->zoom_only_signature_ok($req, $secret);
		$this->dbg('webhook signature_check', ['ok'=>$okSig]);
		if (!$okSig) {
			$this->zoom_log_add(['phase'=>'webhook','event'=>'unauthorized','ok'=>false,'code'=>401]);
			return new \WP_REST_Response([ 'message' => 'unauthorized' ], 401);
		}

		if (!is_array($data)) {
			return new \WP_REST_Response([ 'ok' => true ], 200);
		}

		$event = (string)($data['event'] ?? '');
		$obj   = $data['payload']['object'] ?? [];
		$kind  = (strpos($event,'webinar.') === 0) ? 'webinar' : 'meeting';
		$mid   = preg_replace('/\D/','', (string)($obj['id'] ?? ($obj['webinarid'] ?? '')));
		$topic = (string)($obj['topic'] ?? '');
		$evtTs = $data['event_ts'] ?? null;

		// 3) Live-Status (started/ended) merken
		if ($event === 'meeting.started' || $event === 'webinar.started') {
			$this->attendance_mark_live($kind, $mid, 1, $topic);
			$this->zoom_log_add(['phase'=>'webhook','event'=>$event,'kind'=>$kind,'id'=>$mid,'ok'=>true]);
			return new \WP_REST_Response([ 'ok'=>true, 'live'=>1 ], 200);
		}
		if ($event === 'meeting.ended' || $event === 'webinar.ended') {
			$this->attendance_mark_live($kind, $mid, 0, $topic);
			$this->attendance_close_open_sessions($kind, $mid, $this->attendance_parse_time(null, $evtTs));
			$this->zoom_log_add(['phase'=>'webhook','event'=>$event,'kind'=>$kind,'id'=>$mid,'ok'=>true]);
			return new \WP_REST_Response([ 'ok'=>true, 'live'=>0 ], 200);
		}

		// 4) Join/Leave – in die Anwesenheit übernehmen (Art = Online)
		if ($event === 'meeting.participant_joined' || $event === 'webinar.participant_joined') {
			$p  = $obj['participant'] ?? [];
			$ts = $this->attendance_parse_time($p['join_time'] ?? null, $evtTs);
			$this->attendance_mark_join($kind, $mid, $p, $ts, $topic);
			$this->zoom_log_add(['phase'=>'webhook','event'=>$event,'kind'=>$kind,'id'=>$mid,'ok'=>true]);
			return new \WP_REST_Response([ 'ok'=>true, 'joined'=>1 ], 200);
		}
		if ($event === 'meeting.participant_left' || $event === 'webinar.participant_left') {
			$p  = $obj['participant'] ?? [];
			$ts = $this->attendance_parse_time($p['leave_time'] ?? null, $evtTs);
			$this->attendance_mark_leave($kind, $mid, $p, $ts, $topic);
			$this->zoom_log_add(['phase'=>'webhook','event'=>$event,'kind'=>$kind,'id'=>$mid,'ok'=>true]);
			return new \WP_REST_Response([ 'ok'=>true, 'left'=>1 ], 200);
		}

		// Sonstige Events ok quittieren
		$this->zoom_log_add(['phase'=>'webhook','event'=>$event,'kind'=>$kind,'id'=>$mid,'ok'=>true,'note'=>'ignored']);
		return new \WP_REST_Response([ 'ok'=>true, 'note'=>'ignored_event' ], 200);
	}

	/** Präsenz-Endpoint (vom Scanner aufgerufen) – toggelt JOIN/LEAVE, Art=Präsenz */
	public function rest_zoom_presence( \WP_REST_Request $req ) {
		$body = $req->get_json_params();
		$opts = get_option( self::OPT_KEY, $this->defaults() );

		$id   = isset($body['id'])   ? preg_replace('/\D/','', (string)$body['id']) : preg_replace('/\D/','', (string)($opts['zoom_meeting_number'] ?? ''));
		$kind = isset($body['kind']) ? strtolower((string)$body['kind']) : strtolower((string)($opts['zoom_kind'] ?? 'auto'));
		if ($kind==='auto' || $kind==='') { try { $kind = $this->zoom_resolve_kind_by_id($opts, $id); } catch(\Throwable $e){ $kind='webinar'; } }

		$name = trim((string)($body['name'] ?? ''));
		$email= trim((string)($body['email'] ?? ''));
		$status = trim((string)($body['status'] ?? ''));
		$ts   = isset($body['ts']) ? (int)$body['ts'] : time();
		if ($ts > 2000000000) $ts = (int)floor($ts/1000);

		// Toggle: Falls offene Präsenz-Session -> LEAVE; sonst JOIN
		$this->attendance_mark_presence_toggle($kind, $id, $name, $email, $status, $ts);
		return new \WP_REST_Response(['ok'=>true], 200);
		
		// Manuelle Erfassung kennzeichnen (falls vom Client gesetzt)
if (!empty($body['manual'])) {
    $s  = $this->attendance_store_get();
    $k  = $this->attendance_mkey($kind, $id);
    $pk = $email !== '' ? ('mail:'.strtolower($email)) : ('name:'.md5($name!==''?$name:'Unknown'));
    if (isset($s[$k]['participants'][$pk])) {
        $s[$k]['participants'][$pk]['manual'] = 1;
        $this->attendance_store_put($s);
    }
}

		
	}
	
	

	/* ===== Attendance Speicher ===== */

	private function attendance_store_get(): array {
		$x = get_option(self::OPT_ZOOM_ATTEN, []);
		return is_array($x) ? $x : [];
	}
	private function attendance_store_put(array $x): void {
		update_option(self::OPT_ZOOM_ATTEN, $x, false);
	}
	private function attendance_mkey(string $kind, string $id): string {
		return strtolower($kind).':'.preg_replace('/\D/','', $id);
	}
	private function attendance_parse_time($isoOrNull, $eventTs=null): int {
		if (is_string($isoOrNull) && $isoOrNull !== '') {
			$t = strtotime($isoOrNull);
			if ($t !== false) return $t;
		}
		if (is_numeric($eventTs)) {
			$n = (int)$eventTs;
			if ($n > 2000000000) { $n = (int) floor($n / 1000); }
			return $n;
		}
		return time();
	}
	private function attendance_participant_key(array $p): string {
		$email = strtolower(trim((string)($p['email'] ?? '')));
		if ($email !== '') return 'mail:'.$email;
		if (!empty($p['user_id'])) return 'uid:'.(string)$p['user_id'];
		if (!empty($p['id']))     return 'id:'.(string)$p['id'];
		$name = (string)($p['user_name'] ?? ($p['name'] ?? 'Unknown'));
		return 'name:'.md5($name);
	}
	private function attendance_ensure_meeting(array &$store, string $kind, string $id, string $topic=''): array {
		$key = $this->attendance_mkey($kind,$id);
		if (!isset($store[$key])) {
			$store[$key] = [
				'kind'=>$kind, 'id'=>$id, 'topic'=>$topic, 'updated'=>time(),
				'live'=>0, 'participants'=>[]
			];
		} else {
			if ($topic !== '' && empty($store[$key]['topic'])) $store[$key]['topic'] = $topic;
			$store[$key]['updated'] = time();
		}
		return $store[$key];
	}
	private function attendance_mark_live(string $kind, string $id, int $live, string $topic=''): void {
		$s = $this->attendance_store_get();
		$this->attendance_ensure_meeting($s, $kind, $id, $topic);
		$key = $this->attendance_mkey($kind,$id);
		$s[$key]['live'] = $live ? 1 : 0;
		$s[$key]['updated'] = time();
		$this->attendance_store_put($s);
	}
	private function attendance_get_meeting_state(string $kind, string $id): ?int {
		$s = $this->attendance_store_get();
		$key = $this->attendance_mkey($kind,$id);
		if (!isset($s[$key])) return null;
		return (int)($s[$key]['live'] ?? 0);
	}

	/** Online: JOIN (Art = Online) */
	private function attendance_mark_join(string $kind, string $id, array $p, int $ts, string $topic=''): void {
		$s = $this->attendance_store_get();
		$this->attendance_ensure_meeting($s, $kind, $id, $topic);
		$key = $this->attendance_mkey($kind,$id);
		$pk  = $this->attendance_participant_key($p);

		if (!isset($s[$key]['participants'][$pk])) {
			$s[$key]['participants'][$pk] = [
				'type'  => 'online',
				'name'  => (string)($p['user_name'] ?? ''),
				'email' => (string)($p['email'] ?? ''),
				'user_id' => (string)($p['user_id'] ?? ($p['id'] ?? '')),
				'join_first' => $ts,
				'leave_last' => 0,
				'total' => 0,
				'sessions' => [],
			];
		}
		$ref = &$s[$key]['participants'][$pk];
		$ref['type'] = 'online';
		if (empty($ref['join_first']) || $ts < $ref['join_first']) $ref['join_first'] = $ts;
		$sess = end($ref['sessions']);
		if ($sess && empty($sess['leave'])) {
			// Doppel-Join -> ignorieren
		} else {
			$ref['sessions'][] = ['join'=>$ts, 'leave'=>0, 'dur'=>0];
		}
		$s[$key]['updated'] = time();
		$this->attendance_store_put($s);
	}
	/** Online: LEAVE (Art = Online) */
	private function attendance_mark_leave(string $kind, string $id, array $p, int $ts, string $topic=''): void {
		$s = $this->attendance_store_get();
		$this->attendance_ensure_meeting($s, $kind, $id, $topic);
		$key = $this->attendance_mkey($kind,$id);
		$pk  = $this->attendance_participant_key($p);
		if (!isset($s[$key]['participants'][$pk])) {
			$s[$key]['participants'][$pk] = [
				'type'=>'online','name'=>(string)($p['user_name'] ?? ''), 'email'=>(string)($p['email'] ?? ''),
				'user_id'=>(string)($p['user_id'] ?? ($p['id'] ?? '')),
				'join_first'=>$ts, 'leave_last'=>$ts, 'total'=>0, 'sessions'=>[]
			];
		}
		$ref = &$s[$key]['participants'][$pk];
		$ref['type'] = 'online';
		$idx = count($ref['sessions']) - 1;
		if ($idx >= 0 && empty($ref['sessions'][$idx]['leave'])) {
			$join = (int)$ref['sessions'][$idx]['join'];
			$dur  = max(0, $ts - $join);
			$ref['sessions'][$idx]['leave'] = $ts;
			$ref['sessions'][$idx]['dur']   = $dur;
			$ref['total'] += $dur;
		} else {
			$ref['sessions'][] = ['join'=>$ts, 'leave'=>$ts, 'dur'=>0];
		}
		if ($ts > (int)$ref['leave_last']) $ref['leave_last'] = $ts;
		$s[$key]['updated'] = time();
		$this->attendance_store_put($s);
	}

	/** Präsenz: Toggle JOIN/LEAVE (Art = Präsenz) */
	private function attendance_mark_presence_toggle(string $kind, string $id, string $name, string $email, string $status, int $ts): void {
		$s = $this->attendance_store_get();
		$this->attendance_ensure_meeting($s, $kind, $id);
		$key = $this->attendance_mkey($kind,$id);
		$pk  = $email !== '' ? ('mail:'.strtolower($email)) : ('name:'.md5($name!==''?$name:'Unknown'));

		if (!isset($s[$key]['participants'][$pk])) {
			$s[$key]['participants'][$pk] = [
				'type'=>'presence','name'=>$name,'email'=>$email,'status'=>$status,
				'join_first'=>$ts,'leave_last'=>0,'total'=>0,'sessions'=>[]
			];
		}
		$ref = &$s[$key]['participants'][$pk];
		$ref['type']   = 'presence';
		if ($name  !== '') $ref['name']  = $name;
		if ($email !== '') $ref['email'] = $email;
		if ($status!== '') $ref['status']= $status;

		$idx = count($ref['sessions']) - 1;
		if ($idx >= 0 && empty($ref['sessions'][$idx]['leave'])) {
			// Offene Session -> LEAVE
			$join = (int)$ref['sessions'][$idx]['join'];
			$dur  = max(0, $ts - $join);
			$ref['sessions'][$idx]['leave'] = $ts;
			$ref['sessions'][$idx]['dur']   = $dur;
			$ref['total'] += $dur;
			if ($ts > (int)$ref['leave_last']) $ref['leave_last'] = $ts;
		} else {
			// JOIN
			if (empty($ref['join_first']) || $ts < $ref['join_first']) $ref['join_first'] = $ts;
			$ref['sessions'][] = ['join'=>$ts,'leave'=>0,'dur'=>0];
		}
		$s[$key]['updated'] = time();
		$this->attendance_store_put($s);
	}

	/** Offene Sessions eines Meetings sauber schließen (z. B. bei *.ended) */
	private function attendance_close_open_sessions(string $kind, string $id, int $ts): void {
		$s = $this->attendance_store_get();
		$key = $this->attendance_mkey($kind,$id);
		if (empty($s[$key]['participants'])) { return; }
		foreach ($s[$key]['participants'] as &$r) {
			$idx = count($r['sessions'] ?? []) - 1;
			if ($idx >= 0 && empty($r['sessions'][$idx]['leave'])) {
				$join = (int)$r['sessions'][$idx]['join'];
				$dur  = max(0, $ts - $join);
				$r['sessions'][$idx]['leave'] = $ts;
				$r['sessions'][$idx]['dur']   = $dur;
				$r['total'] = (int)$r['total'] + $dur;
				if ($ts > (int)$r['leave_last']) $r['leave_last'] = $ts;
			}
		}
		unset($r);
		$s[$key]['updated'] = time();
		$this->attendance_store_put($s);
	}

	/* ===== Shortcode: Live-Flag 1/0 ===== */
	public function shortcode_zoom_live_state( $atts ) {
		$a = shortcode_atts([
			'id'      => '',
			'kind'    => 'auto',
			'default' => '0',
		], $atts, 'zoom_live_state');

		$opts = get_option( self::OPT_KEY, $this->defaults() );
		$id   = $a['id'] !== '' ? preg_replace('/\D/','', (string)$a['id']) : preg_replace('/\D/','', (string)($opts['zoom_meeting_number'] ?? '') );
		$kind = strtolower((string)$a['kind']);
		if ($kind==='auto' || $kind==='') {
			try { $kind = $this->zoom_resolve_kind_by_id($opts, $id); } catch(\Throwable $e) { $kind='webinar'; }
		}
		$st = $this->attendance_get_meeting_state($kind, $id);
		if ($st !== null) return $st ? '1' : '0';

		if ($this->zoom_ready($opts)) {
			try { $r = $this->zoom_resource($opts, $kind, $id); $status=strtolower((string)($r['status']??'')); return in_array($status,['started','active'],true)?'1':'0'; } catch(\Throwable $e){}
		}
		return (string)($a['default'] === '1' ? '1' : '0');
	}

	/** ===== Admin-UI: Attendance-Tabelle (mit Spalte „Art“) ===== */
	private function attendance_meeting_keys(): array {
		$s = $this->attendance_store_get();
		$out=[];
		foreach ($s as $k => $m) {
			$out[$k] = (($m['topic']??'') !== '' ? ($m['topic'].' ') : '').'['.($m['kind']??'').':'.($m['id']??'').']';
		}
		asort($out, SORT_NATURAL|SORT_FLAG_CASE);
		return $out;
	}
	private function attendance_render_admin_table(string $mkey) {
    $s = $this->attendance_store_get();
    if (empty($s[$mkey])) { echo '<p><em>Keine Daten.</em></p>'; return; }
    $m = $s[$mkey]; $rows = $m['participants'] ?? [];
    $keys = array_keys($rows); sort($keys, SORT_NATURAL|SORT_FLAG_CASE);

    $urlCsv  = wp_nonce_url( admin_url('admin-post.php?action='.self::ACTION_ATTEN_EXPORT_CSV.'&key='.rawurlencode($mkey)), self::ACTION_ATTEN_EXPORT_CSV );
    $urlPdf  = wp_nonce_url( admin_url('admin-post.php?action='.self::ACTION_ATTEN_EXPORT_PDF.'&key='.rawurlencode($mkey)), self::ACTION_ATTEN_EXPORT_PDF );
    $urlClr  = wp_nonce_url( admin_url('admin-post.php?action='.self::ACTION_ATTEN_CLEAR.'&key='.rawurlencode($mkey)),      self::ACTION_ATTEN_CLEAR );

    echo '<p><a class="button" href="'.esc_url($urlCsv).'">CSV exportieren</a> ';
    echo '<a class="button" href="'.esc_url($urlPdf).'">PDF exportieren</a> ';
    echo '<a class="button button-secondary" href="'.esc_url($urlClr).'" onclick="return confirm(\'Liste wirklich löschen?\');">Liste löschen</a></p>';

    // NEU: Statistik zählen + anzeigen
    $cntPresence = 0; $cntOnline = 0;
    foreach ($rows as $r) { ($r['type'] ?? 'online') === 'presence' ? $cntPresence++ : $cntOnline++; }
    $cntTotal = $cntPresence + $cntOnline;
    echo '<p><strong>Statistik:</strong> Präsenz: <strong>'.intval($cntPresence).'</strong> · Online: <strong>'.intval($cntOnline).'</strong> · Gesamt: <strong>'.intval($cntTotal).'</strong></p>'; // NEU

    echo '<table class="widefat striped" style="max-width:1100px"><thead><tr>';
    echo '<th>Name</th><th>E-Mail</th><th>Erster Login</th><th>Letzter Logout</th><th>Gesamt</th><th>Sessions</th><th>Art</th><th></th>';
    echo '</tr></thead><tbody>';

    foreach ($keys as $pk) {
        $r = $rows[$pk];
        $dJoin = !empty($r['join_first']) ? date_i18n('Y-m-d H:i', (int)$r['join_first']) : '—';
        $dLeft = !empty($r['leave_last']) ? date_i18n('Y-m-d H:i', (int)$r['leave_last']) : '—';
        $dur   = $this->attendance_fmt_dur((int)($r['total'] ?? 0));
        $type  = ($r['type'] ?? 'online') === 'presence' ? 'Präsenz' : 'Online';
        $urlDel= wp_nonce_url( admin_url('admin-post.php?action='.self::ACTION_ATTEN_DELETE.'&key='.rawurlencode($mkey).'&pk='.rawurlencode($pk)), self::ACTION_ATTEN_DELETE );
        echo '<tr>';
        echo '<td>'.esc_html($r['name'] ?? '—').'</td>';
        echo '<td>'.(!empty($r['email']) ? '<a href="mailto:'.esc_attr($r['email']).'">'.esc_html($r['email']).'</a>' : '—').'</td>';
        echo '<td>'.$dJoin.'</td><td>'.$dLeft.'</td><td>'.$dur.'</td>';
        echo '<td>'.intval(count($r['sessions'] ?? [])).'</td>';
        echo '<td>'.$type.'</td>';
        echo '<td><a class="button button-small" href="'.esc_url($urlDel).'" onclick="return confirm(\'Eintrag löschen?\');">Löschen</a></td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
}

	private function attendance_fmt_dur(int $sec): string {
		$h = floor($sec/3600); $m = floor(($sec%3600)/60);
		return sprintf('%02d:%02d h', $h, $m);
	}

	public function handle_attendance_clear() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die('No permissions.'); }
		check_admin_referer( self::ACTION_ATTEN_CLEAR );
		$key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
		$s = $this->attendance_store_get();
		if (isset($s[$key])) { unset($s[$key]); $this->attendance_store_put($s); }
		wp_safe_redirect( admin_url('options-general.php?page=dgptm-online-abstimmen#tab-attendance') ); exit;
	}
	public function handle_attendance_delete() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die('No permissions.'); }
		check_admin_referer( self::ACTION_ATTEN_DELETE );
		$key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
		$pk  = isset($_GET['pk'])  ? sanitize_text_field($_GET['pk'])  : '';
		$s = $this->attendance_store_get();
		if (isset($s[$key]['participants'][$pk])) { unset($s[$key]['participants'][$pk]); $this->attendance_store_put($s); }
		wp_safe_redirect( admin_url('options-general.php?page=dgptm-online-abstimmen&att_key='.rawurlencode($key).'#tab-attendance') ); exit;
	}
	public function handle_attendance_export_csv() {
		if ( ! current_user_can( 'manage_options' ) ) { wp_die('No permissions.'); }
		check_admin_referer( self::ACTION_ATTEN_EXPORT_CSV );
		$key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
		$s = $this->attendance_store_get();
		if (empty($s[$key])) wp_die('Unbekanntes Meeting/Webinar');
		$m = $s[$key]; $rows = $m['participants'] ?? [];
		$tmp = $this->temp_path_with_ext('dgptm_attendance.csv');
		$fh = fopen($tmp,'w');
		fputcsv($fh, ['name','email','join_first','leave_last','total_seconds','total_minutes','sessions','type']);
		foreach ($rows as $r) {
			fputcsv($fh, [
				$r['name'] ?? '', $r['email'] ?? '',
				!empty($r['join_first']) ? wp_date('Y-m-d H:i', (int)$r['join_first']) : '',
				!empty($r['leave_last']) ? wp_date('Y-m-d H:i', (int)$r['leave_last']) : '',
				(int)($r['total'] ?? 0), round(((int)($r['total'] ?? 0))/60),
				intval(count($r['sessions'] ?? [])),
				(($r['type'] ?? 'online')==='presence'?'Präsenz':'Online')
			]);
		}
		fclose($fh);
		nocache_headers();
		header('Content-Type: text/csv; charset=utf-8');
		header('Content-Disposition: attachment; filename='.basename($tmp));
		readfile($tmp); @unlink($tmp); exit;
	}

	public function handle_attendance_export_pdf() {
    if ( ! current_user_can( 'manage_options' ) ) { wp_die('No permissions.'); }
    check_admin_referer( self::ACTION_ATTEN_EXPORT_PDF );
    $key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
    $s = $this->attendance_store_get();
    if (empty($s[$key])) wp_die('Unbekanntes Meeting/Webinar');
    $fpdf_path = dirname( __FILE__ ) . '/../fpdf/fpdf.php';
    if ( ! file_exists( $fpdf_path ) ) { wp_die('FPDF nicht gefunden.'); }
    require_once $fpdf_path;

    $m = $s[$key]; $rows = $m['participants'] ?? [];

    // NEU: Statistik
    $cntPresence = 0; $cntOnline = 0;
    foreach ($rows as $r) { ($r['type'] ?? 'online') === 'presence' ? $cntPresence++ : $cntOnline++; }
    $cntTotal = $cntPresence + $cntOnline;

    $pdf = new \FPDF('P','mm','A4'); $pdf->AddPage();
    $title = 'Anwesenheit – '.(($m['topic'] ?? '') !== '' ? $m['topic'] : strtoupper($m['kind']).' '.$m['id']);
    $pdf->SetFont('Arial','B',14); $pdf->Cell(0,10,$this->pdf_txt($title),0,1,'C');

    // NEU: Statistik unter dem Titel
    $pdf->SetFont('Arial','',10);
    $pdf->Cell(0,6,$this->pdf_txt('Statistik: Präsenz: '.$cntPresence.'  -  Online: '.$cntOnline.'  -  Gesamt: '.$cntTotal),0,1,'C');
    $pdf->Ln(2);

    $pdf->SetFont('Arial','',9);
    $headers=['Name','E-Mail','Erster Login','Letzter Logout','Gesamt','Sessions','Art'];
    $widths=[40,42,28,28,20,18,16]; $row_h=6;
    $pdf->SetFillColor(230,230,230);
    foreach ($headers as $i=>$h) { $pdf->Cell($widths[$i],7,$this->pdf_txt($h),1,0,'C',true); }
    $pdf->Ln();
    uasort($rows, function($a,$b){ return strcasecmp($a['name']??'', $b['name']??''); });
    foreach ($rows as $r) {
        $cells = [
            $this->pdf_txt($r['name'] ?? ''),
            $this->pdf_txt($r['email'] ?? ''),
            $this->pdf_txt(!empty($r['join_first']) ? wp_date('Y-m-d H:i', (int)$r['join_first']) : ''),
            $this->pdf_txt(!empty($r['leave_last']) ? wp_date('Y-m-d H:i', (int)$r['leave_last']) : ''),
            $this->pdf_txt($this->attendance_fmt_dur((int)($r['total'] ?? 0))),
            $this->pdf_txt((string)intval(count($r['sessions'] ?? []))),
            $this->pdf_txt((( $r['type'] ?? 'online')==='presence'?'Präsenz':'Online'))
        ];
        for ($i=0;$i<count($cells);$i++) { $pdf->Cell($widths[$i], $row_h, $cells[$i], 1); }
        $pdf->Ln();
    }
    $tmp = $this->temp_path_with_ext('dgptm_attendance.pdf');
    $pdf->Output('F',$tmp);

    nocache_headers(); header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename='.basename($tmp));
    readfile($tmp); @unlink($tmp); exit;
}

	

public function shortcode_presence_scanner( $atts ) {
	$opts = get_option( self::OPT_KEY, $this->defaults() );
	$a = shortcode_atts([
		'webhook'        => $opts['presence_webhook_url'] ?? '',
		'meeting_number' => $opts['zoom_meeting_number'] ?? '',
		'kind'           => $opts['zoom_kind'] ?? 'auto',
		'save_on'        => 'green,yellow'
	], $atts, 'dgptm_presence_scanner');

	$mid  = preg_replace('/\D/','', (string)$a['meeting_number']);
	$kind = strtolower((string)$a['kind']);
	if (!in_array($kind,['auto','meeting','webinar'],true)) $kind='auto';
	$webhook = esc_url_raw($a['webhook']);

	ob_start(); ?>
<div class="hint">
 <h2 style="text-align: center;"><img class="alignnone size-medium wp-image-37698" src="https://perfusiologie.de/wp-content/uploads/2025/08/DGPTM_Logo_rgb_300_240911-300x65.png" alt="" width="300" height="65" /></h2>
<h2 style="text-align: center;">Mitgliedervollversammlung der DGPTM 2025</h2>
  </div>
<div class="dgptm-presence" data-webhook="<?php echo esc_attr($webhook); ?>" data-meeting="<?php echo esc_attr($mid); ?>" data-kind="<?php echo esc_attr($kind); ?>" data-saveon="<?php echo esc_attr($a['save_on']); ?>">
  <div class="flash"></div>
  <input type="text" class="scan-input" placeholder="Code scannen &amp; Enter" autofocus />
  <div class="info" aria-live="polite"></div>
  <div class="sub"></div>
  <div class="hint">
    Hinweis: <b>Bitte Badge scannen. Wenn ein Mitglied nicht erkannt wird, Doublettencheck im CRM machen.</b>
  </div>
</div>
<?php
	return ob_get_clean();
}


}

DGPTM_Online_Abstimmen::instance();