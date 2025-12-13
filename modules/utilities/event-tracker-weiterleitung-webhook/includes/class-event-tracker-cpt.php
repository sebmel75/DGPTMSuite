<?php
/**
 * Event Tracker CPT Handler
 *
 * Verwaltet alle Custom Post Types und deren Metaboxen
 *
 * @package Event_Tracker
 * @since 1.17.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CPT Handler Class
 */
class ET_CPT_Handler {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'init', [ $this, 'register_cpt' ] );
		add_action( 'init', [ $this, 'register_mail_cpts' ] );
		add_action( 'init', [ $this, 'add_rewrite' ] );
		add_filter( 'query_vars', [ $this, 'register_query_vars' ] );

		add_action( 'add_meta_boxes', [ $this, 'add_metabox' ] );
		add_action( 'save_post_' . ET_Constants::CPT, [ $this, 'save_metabox' ] );

		add_filter( 'manage_edit-' . ET_Constants::CPT . '_columns', [ $this, 'admin_columns' ] );
		add_action( 'manage_' . ET_Constants::CPT . '_posts_custom_column', [ $this, 'admin_column_content' ], 10, 2 );

		add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
	}

	/**
	 * Rewrite-Regel für /eventtracker
	 */
	public function add_rewrite() {
		add_rewrite_rule( '^eventtracker/?$', 'index.php?' . ET_Constants::QUERY_KEY_TRACKER . '=1', 'top' );
	}

	/**
	 * Query-Variablen registrieren
	 */
	public function register_query_vars( $vars ) {
		$vars[] = ET_Constants::QUERY_KEY_TRACKER;
		$vars[] = ET_Constants::QUERY_KEY_EVENT;
		return $vars;
	}

	/**
	 * Haupt-CPT: Veranstaltungen
	 */
	public function register_cpt() {
		$labels = [
			'name'               => __( 'Veranstaltungen', 'event-tracker' ),
			'singular_name'      => __( 'Veranstaltung', 'event-tracker' ),
			'menu_name'          => __( 'Event Tracker', 'event-tracker' ),
			'add_new'            => __( 'Neu hinzufügen', 'event-tracker' ),
			'add_new_item'       => __( 'Veranstaltung hinzufügen', 'event-tracker' ),
			'edit_item'          => __( 'Veranstaltung bearbeiten', 'event-tracker' ),
			'new_item'           => __( 'Neue Veranstaltung', 'event-tracker' ),
			'view_item'          => __( 'Veranstaltung ansehen', 'event-tracker' ),
			'search_items'       => __( 'Veranstaltungen durchsuchen', 'event-tracker' ),
			'not_found'          => __( 'Keine Veranstaltungen gefunden', 'event-tracker' ),
			'not_found_in_trash' => __( 'Keine Veranstaltungen im Papierkorb', 'event-tracker' ),
		];
		register_post_type( ET_Constants::CPT, [
			'labels'          => $labels,
			'public'          => false,
			'show_ui'         => true,
			'show_in_menu'    => true,
			'menu_position'   => 25,
			'menu_icon'       => 'dashicons-calendar-alt',
			'supports'        => [ 'title' ],
			'capability_type' => 'et_event',
			'map_meta_cap'    => true,
		] );
	}

	/**
	 * Mail-CPTs: Log & Vorlagen
	 */
	public function register_mail_cpts() {
		register_post_type( ET_Constants::CPT_MAIL_LOG, [
			'labels'      => [
				'name'          => __( 'Mail-Logs', 'event-tracker' ),
				'singular_name' => __( 'Mail-Log', 'event-tracker' ),
				'menu_name'     => __( 'Mail-Logs', 'event-tracker' ),
			],
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'edit.php?post_type=' . ET_Constants::CPT,
			'supports'     => [ 'title', 'editor', 'author' ],
			'menu_icon'    => 'dashicons-email-alt',
			'capability_type' => 'et_event',
			'map_meta_cap'    => true,
		] );
		register_post_type( ET_Constants::CPT_MAIL_TPL, [
			'labels'      => [
				'name'          => __( 'Mail-Vorlagen', 'event-tracker' ),
				'singular_name' => __( 'Mail-Vorlage', 'event-tracker' ),
				'menu_name'     => __( 'Mail-Vorlagen', 'event-tracker' ),
				'add_new_item'  => __( 'Neue Mail-Vorlage', 'event-tracker' ),
				'edit_item'     => __( 'Mail-Vorlage bearbeiten', 'event-tracker' ),
			],
			'public'       => false,
			'show_ui'      => true,
			'show_in_menu' => 'edit.php?post_type=' . ET_Constants::CPT,
			'supports'     => [ 'title', 'editor' ],
			'menu_icon'    => 'dashicons-media-text',
			'capability_type' => 'et_event',
			'map_meta_cap'    => true,
		] );
	}

	/**
	 * Metabox hinzufügen
	 */
	public function add_metabox() {
		add_meta_box(
			'et_event_meta',
			__( 'Gültigkeitszeitraum & Weiterleitungslink', 'event-tracker' ),
			[ $this, 'render_metabox' ],
			ET_Constants::CPT,
			'normal',
			'high'
		);
	}

	/**
	 * Metabox rendern
	 */
	public function render_metabox( $post ) {
		wp_nonce_field( 'et_save_meta', 'et_meta_nonce' );
		$tz        = wp_timezone();
		$start_ts  = (int) get_post_meta( $post->ID, ET_Constants::META_START_TS, true );
		$end_ts    = (int) get_post_meta( $post->ID, ET_Constants::META_END_TS, true );
		$url       = (string) get_post_meta( $post->ID, ET_Constants::META_REDIRECT_URL, true );
		$zoho_id   = (string) get_post_meta( $post->ID, ET_Constants::META_ZOHO_ID, true );
		$rec_url   = (string) get_post_meta( $post->ID, ET_Constants::META_RECORDING_URL, true );
		$iframe_on  = (string) get_post_meta( $post->ID, ET_Constants::META_IFRAME_ENABLE, true ) === '1';
		$iframe_url = (string) get_post_meta( $post->ID, ET_Constants::META_IFRAME_URL, true );

		$start_val = $start_ts ? ( new DateTimeImmutable( '@' . $start_ts ) )->setTimezone( $tz )->format( 'Y-m-d\TH:i' ) : '';
		$end_val   = $end_ts   ? ( new DateTimeImmutable( '@' . $end_ts ) )->setTimezone( $tz )->format( 'Y-m-d\TH:i' ) : '';
		?>
		<style>
			.et-meta-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
			.et-meta-grid .wide{grid-column:1/-1}
			.et-meta-grid input[type="datetime-local"],
			.et-meta-grid input[type="url"],
			.et-meta-grid input[type="text"]{width:100%}
			.et-help{font-size:12px;color:#666;margin-top:4px}
			.et-inline{display:flex;align-items:center;gap:.5rem;flex-wrap:wrap}
		</style>
		<div class="et-meta-grid">
			<div>
				<label for="et_start"><strong><?php esc_html_e( 'Gültig ab (Datum & Uhrzeit)', 'event-tracker' ); ?></strong></label>
				<input id="et_start" name="et_start" type="datetime-local" value="<?php echo esc_attr( $start_val ); ?>" />
				<div class="et-help"><?php esc_html_e( 'Zeitangaben im Webseiten-Zeitzonen-Kontext', 'event-tracker' ); ?></div>
			</div>
			<div>
				<label for="et_end"><strong><?php esc_html_e( 'Gültig bis (Datum & Uhrzeit)', 'event-tracker' ); ?></strong></label>
				<input id="et_end" name="et_end" type="datetime-local" value="<?php echo esc_attr( $end_val ); ?>" />
			</div>
			<div class="wide">
				<label for="et_url"><strong><?php esc_html_e( 'Weiterleitungs-URL', 'event-tracker' ); ?></strong></label>
				<input id="et_url" name="et_url" type="url" placeholder="https://example.com/ziel" value="<?php echo esc_attr( $url ); ?>" />
				<div class="et-help"><?php esc_html_e( 'Wohin weitergeleitet wird, wenn die Veranstaltung gerade gültig ist.', 'event-tracker' ); ?></div>
			</div>
			<div class="wide">
				<label for="et_zoho_id"><strong><?php esc_html_e( 'Zoho-ID', 'event-tracker' ); ?></strong></label>
				<input id="et_zoho_id" name="et_zoho_id" type="text" value="<?php echo esc_attr( $zoho_id ); ?>" />
				<div class="et-help"><?php esc_html_e( 'Wird für Mail-Webhook und Tracking verwendet.', 'event-tracker' ); ?></div>
			</div>
			<div class="wide">
				<label for="et_recording_url"><strong><?php esc_html_e( 'Aufzeichnungs-URL (optional)', 'event-tracker' ); ?></strong></label>
				<input id="et_recording_url" name="et_recording_url" type="url" value="<?php echo esc_attr( $rec_url ); ?>" />
				<div class="et-help"><?php esc_html_e( 'Nach Veranstaltungsende wird – falls gesetzt – nach 10 Sekunden zu dieser URL weitergeleitet.', 'event-tracker' ); ?></div>
			</div>
			<div class="wide">
				<label><strong><?php esc_html_e( 'Live-Stream: Iframe statt Weiterleitung', 'event-tracker' ); ?></strong></label>
				<div class="et-inline">
					<label for="et_iframe_enable" class="et-inline">
						<input id="et_iframe_enable" name="et_iframe_enable" type="checkbox" value="1" <?php checked( $iframe_on ); ?> />
						<?php esc_html_e( 'Aktivieren (Desktop: Popup, Smartphone: neues Fenster)', 'event-tracker' ); ?>
					</label>
				</div>
			</div>
			<div class="wide">
				<label for="et_iframe_url"><strong><?php esc_html_e( 'Iframe-URL (optional)', 'event-tracker' ); ?></strong></label>
				<input id="et_iframe_url" name="et_iframe_url" type="url" value="<?php echo esc_attr( $iframe_url ); ?>" />
			</div>
		</div>
		<?php
	}

	/**
	 * Metabox speichern
	 */
	public function save_metabox( $post_id ) {
		if ( ! isset( $_POST['et_meta_nonce'] ) || ! wp_verify_nonce( $_POST['et_meta_nonce'], 'et_save_meta' ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$tz = wp_timezone();

		$start = isset( $_POST['et_start'] ) ? sanitize_text_field( wp_unslash( $_POST['et_start'] ) ) : '';
		$end   = isset( $_POST['et_end'] ) ? sanitize_text_field( wp_unslash( $_POST['et_end'] ) ) : '';
		$url_r = isset( $_POST['et_url'] ) ? trim( wp_unslash( $_POST['et_url'] ) ) : '';
		$zoho  = isset( $_POST['et_zoho_id'] ) ? sanitize_text_field( wp_unslash( $_POST['et_zoho_id'] ) ) : '';
		$rec_r = isset( $_POST['et_recording_url'] ) ? trim( wp_unslash( $_POST['et_recording_url'] ) ) : '';
		$iframe_enable = isset( $_POST['et_iframe_enable'] ) ? '1' : '0';
		$iframe_r      = isset( $_POST['et_iframe_url'] ) ? trim( wp_unslash( $_POST['et_iframe_url'] ) ) : '';

		$start_ts = 0;
		if ( $start ) {
			try {
				$start_ts = ( new DateTimeImmutable( $start, $tz ) )->getTimestamp();
			} catch ( \Exception $e ) {
				$start_ts = 0;
			}
		}

		$end_ts = 0;
		if ( $end ) {
			try {
				$end_ts = ( new DateTimeImmutable( $end, $tz ) )->getTimestamp();
			} catch ( \Exception $e ) {
				$end_ts = 0;
			}
		}

		$url    = $url_r ? esc_url_raw( $url_r, [ 'http', 'https' ] ) : '';
		$rec    = $rec_r ? esc_url_raw( $rec_r, [ 'http', 'https' ] ) : '';
		$iframe = $iframe_r ? esc_url_raw( $iframe_r, [ 'http', 'https' ] ) : '';

		update_post_meta( $post_id, ET_Constants::META_START_TS, $start_ts );
		update_post_meta( $post_id, ET_Constants::META_END_TS, $end_ts );
		update_post_meta( $post_id, ET_Constants::META_REDIRECT_URL, $url );
		update_post_meta( $post_id, ET_Constants::META_ZOHO_ID, $zoho );
		update_post_meta( $post_id, ET_Constants::META_RECORDING_URL, $rec );
		update_post_meta( $post_id, ET_Constants::META_IFRAME_ENABLE, $iframe_enable );
		update_post_meta( $post_id, ET_Constants::META_IFRAME_URL, $iframe );
	}

	/**
	 * Admin-Spalten
	 */
	public function admin_columns( $columns ) {
		$new = [];
		foreach ( $columns as $k => $v ) {
			$new[ $k ] = $v;
			if ( 'title' === $k ) {
				$new['et_valid_from'] = __( 'Gültig ab', 'event-tracker' );
				$new['et_valid_to']   = __( 'Gültig bis', 'event-tracker' );
				$new['et_target']     = __( 'Weiterleitungs-URL', 'event-tracker' );
				$new['et_zoho']       = __( 'Zoho-ID', 'event-tracker' );
			}
		}
		return $new;
	}

	/**
	 * Admin-Spalten Inhalt
	 */
	public function admin_column_content( $column, $post_id ) {
		$tz  = wp_timezone();
		$df  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
		switch ( $column ) {
			case 'et_valid_from':
				$ts = (int) get_post_meta( $post_id, ET_Constants::META_START_TS, true );
				echo $ts ? esc_html( wp_date( $df, $ts, $tz ) ) : '—';
				break;
			case 'et_valid_to':
				$ts = (int) get_post_meta( $post_id, ET_Constants::META_END_TS, true );
				echo $ts ? esc_html( wp_date( $df, $ts, $tz ) ) : '—';
				break;
			case 'et_target':
				$url = (string) get_post_meta( $post_id, ET_Constants::META_REDIRECT_URL, true );
				echo $url ? '<a href="' . esc_url( $url ) . '" target="_blank">' . esc_html( $url ) . '</a>' : '—';
				break;
			case 'et_zoho':
				$z = (string) get_post_meta( $post_id, ET_Constants::META_ZOHO_ID, true );
				echo $z ? esc_html( $z ) : '—';
				break;
		}
	}

	/**
	 * Einstellungsseite registrieren
	 */
	public function register_settings_page() {
		add_options_page(
			__( 'Event Tracker', 'event-tracker' ),
			__( 'Event Tracker', 'event-tracker' ),
			'manage_options',
			'event-tracker',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Einstellungen registrieren
	 */
	public function register_settings() {
		register_setting( 'et_settings_group', ET_Constants::OPT_KEY, [ $this, 'sanitize_settings' ] );

		add_settings_section(
			'et_section_main',
			__( 'Allgemein', 'event-tracker' ),
			function () {
				echo '<p>' . esc_html__( 'Webhook-URL und Meldungen konfigurieren.', 'event-tracker' ) . '</p>';
			},
			'event-tracker'
		);

		add_settings_field(
			'webhook_url',
			__( 'Webhook-URL (Redirect/Tracking)', 'event-tracker' ),
			[ $this, 'field_webhook' ],
			'event-tracker',
			'et_section_main'
		);
		add_settings_field(
			'error_message',
			__( 'Fehlermeldung (Template)', 'event-tracker' ),
			[ $this, 'field_error_message' ],
			'event-tracker',
			'et_section_main'
		);
		add_settings_field(
			'recording_alt_text',
			__( 'Text nach Veranstaltungsende (ohne Aufzeichnung)', 'event-tracker' ),
			[ $this, 'field_recording_alt_text' ],
			'event-tracker',
			'et_section_main'
		);

		add_settings_section(
			'et_section_mail',
			__( 'Mail-Webhook', 'event-tracker' ),
			function () {
				echo '<p>' . esc_html__( 'Ziel-Endpoint für den Mailversand per Webhook (JSON).', 'event-tracker' ) . '</p>';
			},
			'event-tracker'
		);
		add_settings_field(
			'mail_webhook_url',
			__( 'Mail Webhook-URL (JSON)', 'event-tracker' ),
			[ $this, 'field_mail_webhook' ],
			'event-tracker',
			'et_section_mail'
		);
	}

	/**
	 * Einstellungen sanitizen
	 */
	public function sanitize_settings( $input ) {
		$out = get_option( ET_Constants::OPT_KEY, [] );

		$out['webhook_url'] = '';
		if ( ! empty( $input['webhook_url'] ) ) {
			$url = esc_url_raw( $input['webhook_url'], [ 'http', 'https' ] );
			if ( $url ) {
				$out['webhook_url'] = $url;
			}
		}

		$default_msg = 'Dieses Event ist derzeit nicht aktiv. {name} ist gültig von {from} bis {to}. {countdown}';
		$out['error_message'] = isset( $input['error_message'] ) ? wp_kses_post( $input['error_message'] ) : $default_msg;

		$default_alt = 'Diese Veranstaltung ist beendet. Eine Aufzeichnung steht nicht zur Verfügung.';
		$out['recording_alt_text'] = isset( $input['recording_alt_text'] ) ? wp_kses_post( $input['recording_alt_text'] ) : $default_alt;

		$out['mail_webhook_url'] = '';
		if ( ! empty( $input['mail_webhook_url'] ) ) {
			$url = esc_url_raw( $input['mail_webhook_url'], [ 'http', 'https' ] );
			if ( $url ) {
				$out['mail_webhook_url'] = $url;
			}
		}

		return $out;
	}

	/**
	 * Einstellungsseite rendern
	 */
	public function render_settings_page() {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Event Tracker Einstellungen', 'event-tracker' ); ?></h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'et_settings_group' );
				do_settings_sections( 'event-tracker' );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}

	/**
	 * Feld: Webhook-URL
	 */
	public function field_webhook() {
		$opts = get_option( ET_Constants::OPT_KEY, [] );
		$val  = isset( $opts['webhook_url'] ) ? $opts['webhook_url'] : '';
		?>
		<input type="url" name="<?php echo esc_attr( ET_Constants::OPT_KEY ); ?>[webhook_url]" value="<?php echo esc_attr( $val ); ?>" class="regular-text" />
		<p class="description"><?php esc_html_e( 'URL die aufgerufen wird, wenn ein Event gültig ist (mit Query-Parametern).', 'event-tracker' ); ?></p>
		<?php
	}

	/**
	 * Feld: Fehlermeldung
	 */
	public function field_error_message() {
		$opts = get_option( ET_Constants::OPT_KEY, [] );
		$val  = isset( $opts['error_message'] ) ? $opts['error_message'] : '';
		?>
		<textarea name="<?php echo esc_attr( ET_Constants::OPT_KEY ); ?>[error_message]" rows="4" class="large-text"><?php echo esc_textarea( $val ); ?></textarea>
		<p class="description"><?php esc_html_e( 'Platzhalter: {name}, {from}, {to}, {countdown}', 'event-tracker' ); ?></p>
		<?php
	}

	/**
	 * Feld: Aufzeichnungs-Alternativtext
	 */
	public function field_recording_alt_text() {
		$opts = get_option( ET_Constants::OPT_KEY, [] );
		$val  = isset( $opts['recording_alt_text'] ) ? $opts['recording_alt_text'] : '';
		?>
		<textarea name="<?php echo esc_attr( ET_Constants::OPT_KEY ); ?>[recording_alt_text]" rows="3" class="large-text"><?php echo esc_textarea( $val ); ?></textarea>
		<?php
	}

	/**
	 * Feld: Mail-Webhook-URL
	 */
	public function field_mail_webhook() {
		$opts = get_option( ET_Constants::OPT_KEY, [] );
		$val  = isset( $opts['mail_webhook_url'] ) ? $opts['mail_webhook_url'] : '';
		?>
		<input type="url" name="<?php echo esc_attr( ET_Constants::OPT_KEY ); ?>[mail_webhook_url]" value="<?php echo esc_attr( $val ); ?>" class="regular-text" />
		<p class="description"><?php esc_html_e( 'Endpoint der JSON-Daten empfängt (event_id, zoho_id, subject, mail_html).', 'event-tracker' ); ?></p>
		<?php
	}
}
