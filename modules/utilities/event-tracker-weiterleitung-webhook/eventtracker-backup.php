<?php
/**
 * Plugin Name: Event Tracker – Weiterleitung & Webhook
 * Description: Listet Veranstaltungen und leitet – innerhalb eines Gültigkeitszeitraums – zur Ziel-URL weiter. Übergibt dabei (nur bei Gültigkeit) alle Query-Parameter (GET) an eine konfigurierbare Webhook-URL. Feste Routing-URL: /eventtracker. Enthält zusätzlich einen kombinierten Shortcode mit HTML-Mailer (Webhook-JSON) inkl. Vorlagen, Testmail und Versandliste.
 * Version:     1.16.2
 * Author:      Seb
 * Text Domain: event-tracker
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

final class ET_Event_Tracker {

    /** CPTs & Optionen */
    private const CPT                   = 'et_event';
    private const CPT_MAIL_LOG          = 'et_mail';
    private const CPT_MAIL_TPL          = 'et_mail_tpl';

    private const OPT_KEY               = 'et_settings';

    /** Event-Metadaten */
    private const META_START_TS         = '_et_start_ts';
    private const META_END_TS           = '_et_end_ts';
    private const META_REDIRECT_URL     = '_et_redirect_url';
    private const META_ZOHO_ID          = '_et_zoho_id';
    private const META_RECORDING_URL    = '_et_recording_url'; // Aufzeichnungs-Link
    /** NEU: Mehrtägige Events - Array von zusätzlichen Start/End-Paaren */
    private const META_ADDITIONAL_DATES = '_et_additional_dates'; // serialized array: [['start'=>ts, 'end'=>ts], ...]
    /** NEU: Live-Stream optional im Iframe statt Redirect (Desktop: Popup, Smartphone: neues Fenster) */
    private const META_IFRAME_ENABLE    = '_et_iframe_enable'; // '1'|'0'
    private const META_IFRAME_URL       = '_et_iframe_url';    // url to embed (e.g. Teams)

    /** Mail-Log Metadaten */
    private const META_MAIL_EVENT_ID    = '_et_mail_event_id';
    private const META_MAIL_ZOHO_ID     = '_et_mail_zoho_id';
    private const META_MAIL_RAW_HTML    = '_et_mail_raw_html';
    private const META_MAIL_STATUS      = '_et_mail_status'; // sent|error|test|queued|stopped|draft
    private const META_MAIL_HTTP_CODE   = '_et_mail_http_code';
    private const META_MAIL_HTTP_BODY   = '_et_mail_http_body';

    /** Mail-Planung & Optionen */
    private const META_MAIL_SUBJECT       = '_et_mail_subject';
    private const META_MAIL_SCHED_KIND    = '_et_mail_sched_kind'; // now|at|event_start|until_start
    private const META_MAIL_SCHED_TS      = '_et_mail_sched_ts';   // int timestamp (nächster Fälligkeitstermin)
    private const META_MAIL_RECURRING     = '_et_mail_recurring';  // '1'|'0'
    private const META_MAIL_INTERVAL      = '_et_mail_interval_sec'; // int seconds
    private const META_MAIL_STOPPED       = '_et_mail_manual_stop';  // '1'|'0'
    private const META_MAIL_IGNOREDATE    = '_et_mail_ignoredate';   // '1'|'0'
    /** NEU: Intervall-Startzeitpunkt */
    private const META_MAIL_INTERVAL_FROM = '_et_mail_interval_from_ts'; // int timestamp (optional)

    /** Locks (gegen Doppel-Ausführung) */
    private const META_MAIL_LOCK        = '_et_mail_processing_lock';

    private const CRON_HOOK_SINGLE      = 'et_run_mail_job';     // single event (arg: log_id)

    /** User-Meta (Toggle) */
    private const USER_META_ACCESS      = 'et_mailer_access';

    /** Query Keys */
    private const QUERY_KEY_EVENT       = 'et_event';
    private const QUERY_KEY_TRACKER     = 'et_tracker'; // signalisiert /eventtracker

    /** One-time inline script flags */
    private static $mailer_script_added = false;
    private static $panels_script_added = false;

    /** Intern: temporäre Cap-Überbrückung nur während Plugin-Aktionen */
    private static $cap_override        = false;

    /** Fallback: max. fällige Jobs pro Request ausführen */
    private const MAX_JOBS_PER_TICK     = 5;

    public function __construct() {
        add_action( 'init', [ $this, 'register_cpt' ] );
        add_action( 'init', [ $this, 'register_mail_cpts' ] );
        add_action( 'init', [ $this, 'add_rewrite' ] );
        add_filter( 'query_vars', [ $this, 'register_query_vars' ] );

        add_action( 'add_meta_boxes', [ $this, 'add_metabox' ] );
        add_action( 'save_post_' . self::CPT, [ $this, 'save_metabox' ] );

        add_filter( 'manage_edit-' . self::CPT . '_columns', [ $this, 'admin_columns' ] );
        add_action( 'manage_' . self::CPT . '_posts_custom_column', [ $this, 'admin_column_content' ], 10, 2 );

        add_action( 'admin_menu', [ $this, 'register_settings_page' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );

        // Shortcodes (kombiniert)
        add_shortcode( 'event_tracker',        [ $this, 'shortcode_combined' ] );
        add_shortcode( 'event_mailer',         [ $this, 'shortcode_combined' ] ); // Alias
        add_shortcode( 'event_mailer_right',   [ $this, 'shortcode_mailer_right' ] );

        // Rendering nur über /eventtracker
        add_filter( 'template_include', [ $this, 'intercept_template' ] );

        // AJAX
        add_action( 'wp_ajax_et_get_template',     [ $this, 'ajax_get_template' ] );
        add_action( 'wp_ajax_et_save_template',    [ $this, 'ajax_save_template' ] );
        add_action( 'wp_ajax_et_delete_template',  [ $this, 'ajax_delete_template' ] );
        add_action( 'wp_ajax_et_send_mail',        [ $this, 'ajax_send_mail' ] );
        add_action( 'wp_ajax_et_test_mail',        [ $this, 'ajax_test_mail' ] );
        add_action( 'wp_ajax_et_delete_mail_log',  [ $this, 'ajax_delete_mail_log' ] );

        // Panels (Liste/Formular via Button)
        add_action( 'wp_ajax_et_fetch_event_list',        [ $this, 'ajax_fetch_event_list' ] );
        add_action( 'wp_ajax_nopriv_et_fetch_event_list', [ $this, 'ajax_fetch_event_list' ] );

        // WICHTIG: auch für nicht eingeloggte Requests registrieren, damit nie "0" von admin-ajax.php kommt,
        // die Methode selbst prüft Berechtigungen und gibt JSON mit Fehlermeldung zurück.
        add_action( 'wp_ajax_et_fetch_event_form',        [ $this, 'ajax_fetch_event_form' ] );
        add_action( 'wp_ajax_nopriv_et_fetch_event_form', [ $this, 'ajax_fetch_event_form' ] );

        // Stop für wiederkehrende Jobs
        add_action( 'wp_ajax_et_stop_mail_job',   [ $this, 'ajax_stop_mail_job' ] );

        // Benutzerprofil: Toggle als Schieberegler
        add_action( 'show_user_profile', [ $this, 'render_user_meta' ] );
        add_action( 'edit_user_profile', [ $this, 'render_user_meta' ] );
        add_action( 'personal_options_update', [ $this, 'save_user_meta' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_user_meta' ] );

        // Caps bei aktivem Toggle – im Plugin-Kontext (AJAX & Adminseiten für unsere CPTs)
        add_filter( 'user_has_cap', [ $this, 'filter_user_caps_for_toggle' ], 10, 4 );

        // Cron & Fallback
        add_action( self::CRON_HOOK_SINGLE, [ $this, 'cron_run_mail_job' ], 10, 1 );
        add_action( 'init', [ $this, 'maybe_process_due_jobs' ] );
    }

    /* ---------------------------------------------
     * Interne Helfer: Toggle-Recht + Cap-Override
     * -------------------------------------------*/
    private function user_has_plugin_access( int $user_id = 0 ) : bool {
        $uid = $user_id ?: get_current_user_id();
        if ( ! $uid ) return false;
        return get_user_meta( $uid, self::USER_META_ACCESS, true ) === '1';
    }
    private function begin_cap_override() : void { self::$cap_override = true; }
    private function end_cap_override()   : void { self::$cap_override = false; }

    /** Admin-Kontext nur für unsere CPTs erkennen (damit Caps gezielt angehoben werden) */
    private function is_plugin_admin_request() : bool {
        if ( ! is_admin() ) return false;

        if ( function_exists( 'get_current_screen' ) ) {
            $scr = get_current_screen();
            if ( $scr && isset( $scr->post_type ) && in_array( $scr->post_type, [ self::CPT, self::CPT_MAIL_LOG, self::CPT_MAIL_TPL ], true ) ) {
                return true;
            }
        }
        $pt = isset( $_REQUEST['post_type'] ) ? sanitize_key( (string) $_REQUEST['post_type'] ) : '';
        if ( $pt && in_array( $pt, [ self::CPT, self::CPT_MAIL_LOG, self::CPT_MAIL_TPL ], true ) ) return true;

        $pid = isset( $_REQUEST['post'] ) ? absint( $_REQUEST['post'] ) : 0;
        if ( $pid ) {
            $ppt = get_post_type( $pid );
            if ( $ppt && in_array( $ppt, [ self::CPT, self::CPT_MAIL_LOG, self::CPT_MAIL_TPL ], true ) ) return true;
        }
        return false;
    }

    public function filter_user_caps_for_toggle( $allcaps, $caps, $args, $user ) {
        // Plugin-AJAX? (unsere Actions beginnen mit et_)
        $is_plugin_ajax  = ( function_exists( 'wp_doing_ajax' ) && wp_doing_ajax() && isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) && 0 === strpos( $_REQUEST['action'], 'et_' ) );
        // Adminbildschirme unserer CPTs?
        $is_plugin_admin = $this->is_plugin_admin_request();
        // Auch während Cap-Override (z. B. Frontend-Form speichern) und generell im Adminbereich (Menüaufbau) aktivieren
        $in_admin        = is_admin();
        $should_elevate  = ( self::$cap_override || $is_plugin_ajax || $is_plugin_admin || $in_admin );

        if ( ! $should_elevate ) return $allcaps;

        $uid = isset( $user->ID ) ? (int) $user->ID : 0;
        if ( ! $uid || ! $this->user_has_plugin_access( $uid ) ) return $allcaps;

        // Nur die für unseren CPT relevanten, eigenen Caps vergeben (kein globales edit_posts etc.).
        // Der CPT ist auf capability_type 'et_event' gemappt (map_meta_cap=true).
        foreach ( [
            'read',
            'upload_files',
            'edit_et_event','read_et_event','delete_et_event',
            'edit_et_events','edit_others_et_events','publish_et_events','read_private_et_events',
            'delete_et_events','delete_private_et_events','delete_published_et_events','delete_others_et_events',
            'edit_private_et_events','edit_published_et_events',
        ] as $cap ) {
            $allcaps[ $cap ] = true;
        }

        return $allcaps;
    }

    /* ---------------------------------------------
     * Rewrite /eventtracker
     * -------------------------------------------*/
    public function add_rewrite() {
        add_rewrite_rule( '^eventtracker/?$', 'index.php?' . self::QUERY_KEY_TRACKER . '=1', 'top' );
    }
    public function register_query_vars( $vars ) {
        $vars[] = self::QUERY_KEY_TRACKER;
        $vars[] = self::QUERY_KEY_EVENT;
        return $vars;
    }

    /* ---------------------------------------------
     * CPT: Veranstaltungen
     * -------------------------------------------*/
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
        register_post_type( self::CPT, [
            'labels'          => $labels,
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => true,
            'menu_position'   => 25,
            'menu_icon'       => 'dashicons-calendar-alt',
            'supports'        => [ 'title' ],
            // WICHTIG: eigene Capabilities verwenden, damit wir gezielt nur für dieses CPT Rechte heben können
            'capability_type' => 'et_event',
            'map_meta_cap'    => true,
        ] );
    }

    /* ---------------------------------------------
     * Zusätzliche CPTs: Mail-Log & Mail-Vorlagen
     * -------------------------------------------*/
    public function register_mail_cpts() {
        register_post_type( self::CPT_MAIL_LOG, [
            'labels'      => [
                'name'          => __( 'Mail-Logs', 'event-tracker' ),
                'singular_name' => __( 'Mail-Log', 'event-tracker' ),
                'menu_name'     => __( 'Mail-Logs', 'event-tracker' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'edit.php?post_type=' . self::CPT,
            'supports'     => [ 'title', 'editor', 'author' ],
            'menu_icon'    => 'dashicons-email-alt',
            // an dieselben Caps wie das Event-CPT koppeln
            'capability_type' => 'et_event',
            'map_meta_cap'    => true,
        ] );
        register_post_type( self::CPT_MAIL_TPL, [
            'labels'      => [
                'name'          => __( 'Mail-Vorlagen', 'event-tracker' ),
                'singular_name' => __( 'Mail-Vorlage', 'event-tracker' ),
                'menu_name'     => __( 'Mail-Vorlagen', 'event-tracker' ),
                'add_new_item'  => __( 'Neue Mail-Vorlage', 'event-tracker' ),
                'edit_item'     => __( 'Mail-Vorlage bearbeiten', 'event-tracker' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'edit.php?post_type=' . self::CPT,
            'supports'     => [ 'title', 'editor' ],
            'menu_icon'    => 'dashicons-media-text',
            // an dieselben Caps wie das Event-CPT koppeln
            'capability_type' => 'et_event',
            'map_meta_cap'    => true,
        ] );
    }

    /* ---------------------------------------------
     * Metabox: Zeitraum, URL, Zoho-ID, Aufzeichnung + (NEU) Iframe-Optionen
     * -------------------------------------------*/
    public function add_metabox() {
        add_meta_box(
            'et_event_meta',
            __( 'Gültigkeitszeitraum & Weiterleitungslink', 'event-tracker' ),
            [ $this, 'render_metabox' ],
            self::CPT,
            'normal',
            'high'
        );
    }

    public function render_metabox( $post ) {
        wp_nonce_field( 'et_save_meta', 'et_meta_nonce' );
        $tz        = wp_timezone();
        $start_ts  = (int) get_post_meta( $post->ID, self::META_START_TS, true );
        $end_ts    = (int) get_post_meta( $post->ID, self::META_END_TS, true );
        $url       = (string) get_post_meta( $post->ID, self::META_REDIRECT_URL, true );
        $zoho_id   = (string) get_post_meta( $post->ID, self::META_ZOHO_ID, true );
        $rec_url   = (string) get_post_meta( $post->ID, self::META_RECORDING_URL, true );

        // NEU: Iframe-Optionen
        $iframe_on  = (string) get_post_meta( $post->ID, self::META_IFRAME_ENABLE, true ) === '1';
        $iframe_url = (string) get_post_meta( $post->ID, self::META_IFRAME_URL, true );

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
                <div class="et-help"><?php esc_html_e( 'Wohin weitergeleitet wird, wenn die Veranstaltung gerade gültig ist (sofern nicht die Iframe-Option aktiv ist).', 'event-tracker' ); ?></div>
            </div>
            <div class="wide">
                <label for="et_zoho_id"><strong><?php esc_html_e( 'Zoho-ID', 'event-tracker' ); ?></strong></label>
                <input id="et_zoho_id" name="et_zoho_id" type="text" placeholder="z. B. 1234567890" value="<?php echo esc_attr( $zoho_id ); ?>" />
                <div class="et-help"><?php esc_html_e( 'Wird für Mail-Webhook und Tracking verwendet.', 'event-tracker' ); ?></div>
            </div>
            <div class="wide">
                <label for="et_recording_url"><strong><?php esc_html_e( 'Aufzeichnungs-URL (optional)', 'event-tracker' ); ?></strong></label>
                <input id="et_recording_url" name="et_recording_url" type="url" placeholder="https://example.com/aufzeichnung" value="<?php echo esc_attr( $rec_url ); ?>" />
                <div class="et-help"><?php esc_html_e( 'Nach Veranstaltungsende wird – falls gesetzt – nach 10 Sekunden zu dieser URL weitergeleitet. Kein Webhook-Aufruf.', 'event-tracker' ); ?></div>
            </div>

            <!-- NEU: Iframe-Optionen -->
            <div class="wide">
                <label><strong><?php esc_html_e( 'Live-Stream: Iframe statt Weiterleitung', 'event-tracker' ); ?></strong></label>
                <div class="et-inline">
                    <label for="et_iframe_enable" class="et-inline">
                        <input id="et_iframe_enable" name="et_iframe_enable" type="checkbox" value="1" <?php checked( $iframe_on ); ?> />
                        <?php esc_html_e( 'Aktivieren (Desktop: Popup, Smartphone: neues Fenster)', 'event-tracker' ); ?>
                    </label>
                </div>
                <div class="et-help"><?php esc_html_e( 'Wenn aktiv und eine Iframe-URL hinterlegt ist, wird nach Webhook-Aufruf der Stream angezeigt statt zur Weiterleitungs-URL zu springen.', 'event-tracker' ); ?></div>
            </div>
            <div class="wide">
                <label for="et_iframe_url"><strong><?php esc_html_e( 'Iframe-URL (optional)', 'event-tracker' ); ?></strong></label>
                <input id="et_iframe_url" name="et_iframe_url" type="url" placeholder="https://teams.microsoft.com/convene/..." value="<?php echo esc_attr( $iframe_url ); ?>" />
                <div class="et-help"><?php esc_html_e( 'Beispiel (Teams Townhall): https://teams.microsoft.com/convene/townhall?eventId=...&sessionId=...', 'event-tracker' ); ?></div>
            </div>
        </div>
        <?php
    }

    public function save_metabox( $post_id ) {
        if ( ! isset( $_POST['et_meta_nonce'] ) || ! wp_verify_nonce( $_POST['et_meta_nonce'], 'et_save_meta' ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        $tz = wp_timezone();

        $start = isset( $_POST['et_start'] ) ? sanitize_text_field( wp_unslash( $_POST['et_start'] ) ) : '';
        $end   = isset( $_POST['et_end'] ) ? sanitize_text_field( wp_unslash( $_POST['et_end'] ) ) : '';
        $url_r = isset( $_POST['et_url'] ) ? trim( wp_unslash( $_POST['et_url'] ) ) : '';
        $zoho  = isset( $_POST['et_zoho_id'] ) ? sanitize_text_field( wp_unslash( $_POST['et_zoho_id'] ) ) : '';
        $rec_r = isset( $_POST['et_recording_url'] ) ? trim( wp_unslash( $_POST['et_recording_url'] ) ) : '';

        // NEU
        $iframe_enable = isset( $_POST['et_iframe_enable'] ) ? '1' : '0';
        $iframe_r      = isset( $_POST['et_iframe_url'] ) ? trim( wp_unslash( $_POST['et_iframe_url'] ) ) : '';

        $start_ts = 0; if ( $start ) { try { $start_ts = ( new DateTimeImmutable( $start, $tz ) )->getTimestamp(); } catch ( \Exception $e ) {} }
        $end_ts   = 0; if ( $end )   { try { $end_ts   = ( new DateTimeImmutable( $end,   $tz ) )->getTimestamp(); } catch ( \Exception $e ) {} }

        $url  = $url_r ? esc_url_raw( $url_r, [ 'http', 'https' ] ) : '';
        $rec  = $rec_r ? esc_url_raw( $rec_r, [ 'http', 'https' ] ) : '';
        $iframe = $iframe_r ? esc_url_raw( $iframe_r, [ 'http', 'https' ] ) : '';

        update_post_meta( $post_id, self::META_START_TS, $start_ts );
        update_post_meta( $post_id, self::META_END_TS, $end_ts );
        update_post_meta( $post_id, self::META_REDIRECT_URL, $url );
        update_post_meta( $post_id, self::META_ZOHO_ID, $zoho );
        update_post_meta( $post_id, self::META_RECORDING_URL, $rec );

        // NEU
        update_post_meta( $post_id, self::META_IFRAME_ENABLE, $iframe_enable );
        update_post_meta( $post_id, self::META_IFRAME_URL,    $iframe );
    }

    /* ---------------------------------------------
     * Admin-Listendarstellung
     * -------------------------------------------*/
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
    public function admin_column_content( $column, $post_id ) {
        $tz  = wp_timezone();
        $df  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
        switch ( $column ) {
            case 'et_valid_from':
                $ts = (int) get_post_meta( $post_id, self::META_START_TS, true );
                echo $ts ? esc_html( wp_date( $df, $ts, $tz ) ) : '—';
                break;
            case 'et_valid_to':
                $ts = (int) get_post_meta( $post_id, self::META_END_TS, true );
                echo $ts ? esc_html( wp_date( $df, $ts, $tz ) ) : '—';
                break;
            case 'et_target':
                $url = (string) get_post_meta( $post_id, self::META_REDIRECT_URL, true );
                echo $url ? '<a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html( $url ) . '</a>' : '—';
                break;
            case 'et_zoho':
                $z = (string) get_post_meta( $post_id, self::META_ZOHO_ID, true );
                echo $z ? esc_html( $z ) : '—';
                break;
        }
    }

    /* ---------------------------------------------
     * Einstellungen
     * -------------------------------------------*/
    public function register_settings_page() {
        add_options_page(
            __( 'Event Tracker', 'event-tracker' ),
            __( 'Event Tracker', 'event-tracker' ),
            'manage_options',
            'event-tracker',
            [ $this, 'render_settings_page' ]
        );
    }
    public function register_settings() {
        register_setting( 'et_settings_group', self::OPT_KEY, [ $this, 'sanitize_settings' ] );

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
    public function sanitize_settings( $input ) {
        $out = get_option( self::OPT_KEY, [] );

        $out['webhook_url'] = '';
        if ( ! empty( $input['webhook_url'] ) ) {
            $url = esc_url_raw( $input['webhook_url'], [ 'http', 'https' ] );
            if ( $url ) $out['webhook_url'] = $url;
        }

        $default_msg = 'Dieses Event ist derzeit nicht aktiv. {name} ist gültig von {from} bis {to}. {countdown}';
        $out['error_message'] = isset( $input['error_message'] ) && is_string( $input['error_message'] )
            ? wp_kses_post( $input['error_message'] )
            : $default_msg;

        $out['recording_alt_text'] = isset( $input['recording_alt_text'] ) && is_string( $input['recording_alt_text'] )
            ? sanitize_text_field( $input['recording_alt_text'] )
            : 'Aufzeichnung bald verfügbar.';

        $out['mail_webhook_url'] = '';
        if ( ! empty( $input['mail_webhook_url'] ) ) {
            $url2 = esc_url_raw( $input['mail_webhook_url'], [ 'http', 'https'] );
            if ( $url2 ) $out['mail_webhook_url'] = $url2;
        }
        return $out;
    }
    public function field_webhook() {
        $opts = get_option( self::OPT_KEY, [] );
        $val  = isset( $opts['webhook_url'] ) ? $opts['webhook_url'] : '';
        echo '<input type="url" class="regular-text" name="' . esc_attr( self::OPT_KEY ) . '[webhook_url]" value="' . esc_attr( $val ) . '" placeholder="https://example.com/webhook" />';
        echo '<p class="description">' . esc_html__( 'Nur bei Gültigkeit wird an diese URL (per GET) getrackt. Es werden alle Query-Parameter plus event_id und zoho_id übertragen.', 'event-tracker' ) . '</p>';
    }
    public function field_error_message() {
        $opts = get_option( self::OPT_KEY, [] );
        $val  = isset( $opts['error_message'] ) ? $opts['error_message'] : 'Dieses Event ist derzeit nicht aktiv. {name} ist gültig von {from} bis {to}. {countdown}';
        echo '<textarea name="' . esc_attr( self::OPT_KEY ) . '[error_message]" rows="3" class="large-text">' . esc_textarea( $val ) . '</textarea>';
        echo '<p class="description">' . esc_html__( 'Platzhalter: {name}, {from}, {to}, {countdown}', 'event-tracker' ) . '</p>';
    }
    public function field_recording_alt_text() {
        $opts = get_option( self::OPT_KEY, [] );
        $val  = isset( $opts['recording_alt_text'] ) ? $opts['recording_alt_text'] : 'Aufzeichnung bald verfügbar.';
        echo '<input type="text" class="regular-text" name="' . esc_attr( self::OPT_KEY ) . '[recording_alt_text]" value="' . esc_attr( $val ) . '" placeholder="Aufzeichnung bald verfügbar." />';
        echo '<p class="description">' . esc_html__( 'Wird angezeigt, wenn die Veranstaltung beendet ist und keine Aufzeichnungs-URL hinterlegt wurde.', 'event-tracker' ) . '</p>';
    }
    public function field_mail_webhook() {
        $opts = get_option( self::OPT_KEY, [] );
        $val  = isset( $opts['mail_webhook_url'] ) ? $opts['mail_webhook_url'] : '';
        echo '<input type="url" class="regular-text" name="' . esc_attr( self::OPT_KEY ) . '[mail_webhook_url]" value="' . esc_attr( $val ) . '" placeholder="https://example.com/mail-webhook" />';
    }
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Event Tracker – Einstellungen', 'event-tracker' ); ?></h1>
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

    /* ---------------------------------------------
     * Rendering (/eventtracker)
     * -------------------------------------------*/
    public function intercept_template( $template ) {
        if ( intval( get_query_var( self::QUERY_KEY_TRACKER ) ) !== 1 ) return $template;

        $event_id = absint( get_query_var( self::QUERY_KEY_EVENT ) );
        if ( $event_id ) {
            $this->handle_event_request( $event_id ); // endet mit Redirect oder Ausgabe
            return $template; // nicht erreicht
        }

        status_header( 200 );
        nocache_headers();
        echo $this->render_list_table( /*show_action_link=*/false );
        exit;
    }

    private function render_list_table( bool $show_action_link, array $forward_get = null ) {
        $q   = new WP_Query( [
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'meta_value_num',
            'meta_key'       => self::META_START_TS,
            'order'          => 'ASC',
        ] );
        $tz  = wp_timezone();
        $df  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
        $now = time();

        $can_edit = $this->user_has_plugin_access();

        ob_start();
        ?>
        <style>
            .et-table{width:100%;border-collapse:collapse;table-layout:auto}
            .et-table th,.et-table td{border-bottom:1px solid #e5e7eb;padding:10px;text-align:left}
            .et-table th{font-weight:600}
            .et-badge{display:inline-block;padding:.2rem .5rem;border-radius:.5rem;font-size:.75rem}
            .et-badge--live{background:#d1fae5}
            .et-badge--future{background:#e0e7ff}
            .et-badge--past{background:#fce7f3}
            .et-actions a,.et-actions button{display:inline-block;margin:.2rem .2rem;padding:.5rem .75rem;border-radius:.5rem;border:1px solid #e5e7eb;text-decoration:none;background:#f9fafb;cursor:pointer}
            @media (max-width:640px){
                .et-table thead{display:none}
                .et-table tr{display:block;margin-bottom:12px;border:1px solid #e5e7eb;border-radius:10px;padding:8px}
                .et-table td{display:flex;justify-content:space-between;border:none;padding:6px 0}
                .et-table td::before{content:attr(data-label);font-weight:600;margin-right:8px}
                .et-actions a,.et-actions button{width:100%;text-align:center}
            }
        </style>
        <table class="et-table">
            <thead>
            <tr>
                <th><?php esc_html_e( 'Veranstaltungsname', 'event-tracker' ); ?></th>
                <th><?php esc_html_e( 'Gültig ab', 'event-tracker' ); ?></th>
                <th><?php esc_html_e( 'Gültig bis', 'event-tracker' ); ?></th>
                <th><?php esc_html_e( 'Status', 'event-tracker' ); ?></th>
                <?php if ( $show_action_link ): ?>
                    <th><?php esc_html_e( 'Aktionen', 'event-tracker' ); ?></th>
                <?php endif; ?>
            </tr>
            </thead>
            <tbody>
            <?php
            if ( $q->have_posts() ) :
                while ( $q->have_posts() ) : $q->the_post();
                    $post_id = get_the_ID();
                    $name    = get_the_title();
                    $start   = (int) get_post_meta( $post_id, self::META_START_TS, true );
                    $end     = (int) get_post_meta( $post_id, self::META_END_TS, true );
                    $target  = (string) get_post_meta( $post_id, self::META_REDIRECT_URL, true );

                    $status = 'future';
                    if ( $start && $end ) {
                        if ( $now < $start ) $status = 'future';
                        elseif ( $now > $end ) $status = 'past';
                        else $status = 'live';
                    }

                    $badge_text = [
                        'future' => __( 'Noch nicht aktiv', 'event-tracker' ),
                        'live'   => __( 'Aktiv', 'event-tracker' ),
                        'past'   => __( 'Abgelaufen', 'event-tracker' ),
                    ][ $status ];

                    $badge_class = [
                        'future' => 'et-badge et-badge--future',
                        'live'   => 'et-badge et-badge--live',
                        'past'   => 'et-badge et-badge--past',
                    ][ $status ];

                    $link = '';
                    if ( $show_action_link ) {
                        $base  = home_url( '/eventtracker' );
                        $query = [];
                        $src = is_array( $forward_get ) ? $forward_get : $_GET; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                        foreach ( $src as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                            if ( is_scalar( $v ) && $k !== self::QUERY_KEY_EVENT && $k !== self::QUERY_KEY_TRACKER ) {
                                $query[ sanitize_key( $k ) ] = sanitize_text_field( wp_unslash( (string) $v ) );
                            }
                        }
                        $query[ self::QUERY_KEY_EVENT ] = (string) $post_id;
                        $link = add_query_arg( $query, $base );
                    }
                    ?>
                    <tr>
                        <td data-label="<?php esc_attr_e( 'Veranstaltungsname', 'event-tracker' ); ?>"><?php echo esc_html( $name ); ?></td>
                        <td data-label="<?php esc_attr_e( 'Gültig ab', 'event-tracker' ); ?>"><?php echo $start ? esc_html( wp_date( $df, $start, $tz ) ) : '—'; ?></td>
                        <td data-label="<?php esc_attr_e( 'Gültig bis', 'event-tracker' ); ?>"><?php echo $end ? esc_html( wp_date( $df, $end, $tz ) ) : '—'; ?></td>
                        <td data-label="<?php esc_attr_e( 'Status', 'event-tracker' ); ?>"><span class="<?php echo esc_attr( $badge_class ); ?>"><?php echo esc_html( $badge_text ); ?></span></td>
                        <?php if ( $show_action_link ): ?>
                            <td data-label="<?php esc_attr_e( 'Aktionen', 'event-tracker' ); ?>" class="et-actions">
                                <?php if ( $target ) : ?>
                                    <a href="<?php echo esc_url( $link ); ?>"><?php esc_html_e( 'Zum Event / prüfen', 'event-tracker' ); ?></a>
                                <?php else : ?>
                                    <em><?php esc_html_e( 'Keine Ziel-URL hinterlegt', 'event-tracker' ); ?></em>
                                <?php endif; ?>
                                <?php if ( $can_edit ) : ?>
                                    <button type="button" class="et-btn" data-action="edit" data-event-id="<?php echo esc_attr( $post_id ); ?>"><?php esc_html_e( 'Bearbeiten', 'event-tracker' ); ?></button>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                    <?php
                endwhile;
                wp_reset_postdata();
            else :
                ?>
                <tr><td colspan="<?php echo $show_action_link ? 5 : 4; ?>"><?php esc_html_e( 'Keine Veranstaltungen vorhanden.', 'event-tracker' ); ?></td></tr>
                <?php
            endif;
            ?>
            </tbody>
        </table>
        <?php
        return ob_get_clean();
    }

    /* ---------------------------------------------
     * Shortcode (Buttons + Panels + Mailer)
     * -------------------------------------------*/
    public function shortcode_combined( $atts = [] ) : string {
        $out = '';
        if ( isset( $_POST['et_save_event'] ) ) {
            $out .= $this->handle_frontend_save();
        }
        $this->enqueue_panels_script_once();

        $ajax_url = esc_url( admin_url( 'admin-ajax.php' ) );
        $nonce    = wp_create_nonce( 'et_panels' );

        ob_start();
        ?>
        <style>
            .et-panels{margin:16px 0}
            .et-panel-controls{display:flex;gap:.5rem;flex-wrap:wrap}
            .et-btn{display:inline-flex;align-items:center;gap:.5rem;border:1px solid #e5e7eb;border-radius:8px;padding:.5rem .8rem;background:#f9fafb;cursor:pointer}
            .et-btn.primary{background:#2563eb;color:#fff;border-color:#2563eb}
            .et-btn[disabled]{opacity:.6;pointer-events:none}
            .et-panel{margin-top:12px;border:1px solid #e5e7eb;border-radius:12px;padding:12px}
            .et-hidden{display:none!important}
            .et-msg{margin-top:8px;font-size:.9rem;color:#065f46}
            .et-msg.error{color:#7f1d1d}
        </style>
        <div class="et-panels" data-ajax="<?php echo $ajax_url; ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>">
            <div class="et-panel-controls">
                <button type="button" class="et-btn" data-panel="list"><?php esc_html_e( 'Veranstaltungen anzeigen', 'event-tracker' ); ?></button>
                <?php if ( is_user_logged_in() ) : ?>
                    <button type="button" class="et-btn" data-panel="form" data-form-mode="new"><?php esc_html_e( 'Veranstaltung hinzufügen', 'event-tracker' ); ?></button>
                <?php endif; ?>
            </div>
            <div class="et-panel et-hidden" data-name="list" aria-hidden="true"></div>
            <div class="et-panel et-hidden" data-name="form" aria-hidden="true"></div>
            <div class="et-msg" aria-live="polite"></div>
        </div>
        <?php
        $out .= ob_get_clean();

        if ( $this->user_has_plugin_access() ) {
            $out .= $this->render_mailer_section();
        }

        return $out;
    }

    private function render_frontend_form( int $event_id = 0 ): string {
        $tzObj  = wp_timezone();
        $tz     = wp_timezone_string();
        $is_edit = $event_id > 0;
        $title  = '';
        $start_ts = 0; $end_ts = 0; $url = ''; $zoho=''; $rec=''; $if_on=false; $if_url='';

        if ( $is_edit && get_post_type( $event_id ) === self::CPT ) {
            $title    = get_the_title( $event_id );
            $start_ts = (int) get_post_meta( $event_id, self::META_START_TS, true );
            $end_ts   = (int) get_post_meta( $event_id, self::META_END_TS, true );
            $url      = (string) get_post_meta( $event_id, self::META_REDIRECT_URL, true );
            $zoho     = (string) get_post_meta( $event_id, self::META_ZOHO_ID, true );
            $rec      = (string) get_post_meta( $event_id, self::META_RECORDING_URL, true );
            $if_on    = (string) get_post_meta( $event_id, self::META_IFRAME_ENABLE, true ) === '1';
            $if_url   = (string) get_post_meta( $event_id, self::META_IFRAME_URL, true );
        }

        $start_val = $start_ts ? ( new DateTimeImmutable( '@' . $start_ts ) )->setTimezone( $tzObj )->format( 'Y-m-d\TH:i' ) : '';
        $end_val   = $end_ts   ? ( new DateTimeImmutable( '@' . $end_ts ) )->setTimezone( $tzObj )->format( 'Y-m-d\TH:i' ) : '';

        $action = esc_url( add_query_arg( [] ) );
        ob_start();
        ?>
        <style>
            .et-form{margin-top:12px;border:1px solid #e5e7eb;padding:16px;border-radius:12px;max-width:820px}
            .et-form h3{margin:0 0 12px 0}
            .et-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
            .et-grid .full{grid-column:1/-1}
            .et-form input[type="text"], .et-form input[type="url"], .et-form input[type="datetime-local"]{width:100%}
            .et-hint{font-size:.9rem;color:#6b7280}
            @media (max-width:640px){.et-grid{grid-template-columns:1fr}}
        </style>
        <form class="et-form" method="post" action="<?php echo $action; ?>">
            <h3><?php echo $is_edit ? esc_html__( 'Veranstaltung bearbeiten', 'event-tracker' ) : esc_html__( 'Neue Veranstaltung anlegen', 'event-tracker' ); ?></h3>
            <?php wp_nonce_field( 'et_front_save', 'et_front_nonce' ); ?>
            <input type="hidden" name="et_save_event" value="1" />
            <input type="hidden" name="et_event_id" value="<?php echo esc_attr( $event_id ); ?>" />
            <div class="et-grid">
                <div class="full">
                    <label><strong><?php esc_html_e( 'Titel (Veranstaltungsname)', 'event-tracker' ); ?></strong></label>
                    <input type="text" name="et_title" required value="<?php echo esc_attr( $title ); ?>" />
                </div>
                <div>
                    <label><strong><?php esc_html_e( 'Gültig ab (Datum & Uhrzeit)', 'event-tracker' ); ?></strong></label>
                    <input type="datetime-local" name="et_start" required value="<?php echo esc_attr( $start_val ); ?>" />
                    <div class="et-hint"><?php echo esc_html( sprintf( __( 'Zeitzone: %s', 'event-tracker' ), $tz ) ); ?></div>
                </div>
                <div>
                    <label><strong><?php esc_html_e( 'Gültig bis (Datum & Uhrzeit)', 'event-tracker' ); ?></strong></label>
                    <input type="datetime-local" name="et_end" required value="<?php echo esc_attr( $end_val ); ?>" />
                </div>
                <div class="full">
                    <label><strong><?php esc_html_e( 'Weiterleitungs-URL', 'event-tracker' ); ?></strong></label>
                    <input type="url" name="et_url" placeholder="https://example.com/ziel" required value="<?php echo esc_attr( $url ); ?>" />
                </div>
                <div class="full">
                    <label><strong><?php esc_html_e( 'Zoho-ID (optional, für Mail/Webhook)', 'event-tracker' ); ?></strong></label>
                    <input type="text" name="et_zoho_id" value="<?php echo esc_attr( $zoho ); ?>" />
                </div>
                <div class="full">
                    <label><strong><?php esc_html_e( 'Aufzeichnungs-URL (optional)', 'event-tracker' ); ?></strong></label>
                    <input type="url" name="et_recording_url" placeholder="https://example.com/aufzeichnung" value="<?php echo esc_attr( $rec ); ?>" />
                </div>
                <div class="full">
                    <label class="et-inline">
                        <input type="checkbox" name="et_iframe_enable" value="1" <?php checked( $if_on ); ?> />
                        <strong><?php esc_html_e( 'Live-Stream im Iframe anzeigen (statt Redirect)', 'event-tracker' ); ?></strong>
                    </label>
                    <input type="url" name="et_iframe_url" placeholder="https://teams.microsoft.com/convene/..." value="<?php echo esc_attr( $if_url ); ?>" />
                    <div class="et-hint"><?php esc_html_e( 'Desktop: Popup, Smartphone: neues Fenster', 'event-tracker' ); ?></div>
                </div>
            </div>
            <p><button type="submit" class="button button-primary"><?php echo $is_edit ? esc_html__( 'Änderungen speichern', 'event-tracker' ) : esc_html__( 'Veranstaltung erstellen', 'event-tracker' ); ?></button></p>
        </form>
        <?php
        return ob_get_clean();
    }

    private function handle_frontend_save(): string {
        // GEÄNDERT: Jeder eingeloggte User kann Events erstellen/bearbeiten
        if ( ! is_user_logged_in() ) {
            return $this->notice( __( 'Sie müssen eingeloggt sein, um eine Veranstaltung zu speichern.', 'event-tracker' ), 'error' );
        }
        if ( ! isset( $_POST['et_front_nonce'] ) || ! wp_verify_nonce( $_POST['et_front_nonce'], 'et_front_save' ) ) {
            return $this->notice( __( 'Sicherheitsprüfung fehlgeschlagen.', 'event-tracker' ), 'error' );
        }

        $event_id = isset( $_POST['et_event_id'] ) ? absint( $_POST['et_event_id'] ) : 0;
        $title = isset( $_POST['et_title'] ) ? sanitize_text_field( wp_unslash( $_POST['et_title'] ) ) : '';
        $start = isset( $_POST['et_start'] ) ? sanitize_text_field( wp_unslash( $_POST['et_start'] ) ) : '';
        $end   = isset( $_POST['et_end'] )   ? sanitize_text_field( wp_unslash( $_POST['et_end'] ) )   : '';
        $url_r = isset( $_POST['et_url'] )   ? trim( wp_unslash( $_POST['et_url'] ) )                  : '';
        $zoho  = isset( $_POST['et_zoho_id'] ) ? sanitize_text_field( wp_unslash( $_POST['et_zoho_id'] ) ) : '';
        $rec_r = isset( $_POST['et_recording_url'] ) ? trim( wp_unslash( $_POST['et_recording_url'] ) ) : '';
        $iframe_enable = isset( $_POST['et_iframe_enable'] ) ? '1' : '0';
        $iframe_r      = isset( $_POST['et_iframe_url'] ) ? trim( wp_unslash( $_POST['et_iframe_url'] ) ) : '';

        if ( ! $title || ! $start || ! $end || ! $url_r ) {
            return $this->notice( __( 'Bitte fülle alle Felder aus.', 'event-tracker' ), 'error' );
        }

        $tz = wp_timezone();
        try { $start_ts = ( new DateTimeImmutable( $start, $tz ) )->getTimestamp(); } catch ( \Exception $e ) { $start_ts = 0; }
        try { $end_ts   = ( new DateTimeImmutable( $end,   $tz ) )->getTimestamp(); } catch ( \Exception $e ) { $end_ts = 0; }
        if ( ! $start_ts || ! $end_ts || $end_ts <= $start_ts ) {
            return $this->notice( __( 'Ungültiger Zeitraum (Ende muss nach Start liegen).', 'event-tracker' ), 'error' );
        }

        $url    = esc_url_raw( $url_r, [ 'http', 'https' ] );
        $rec    = $rec_r ? esc_url_raw( $rec_r, [ 'http', 'https' ] ) : '';
        $iframe = $iframe_r ? esc_url_raw( $iframe_r, [ 'http', 'https' ] ) : '';

        if ( ! $url ) {
            return $this->notice( __( 'Bitte eine gültige Ziel-URL (http/https) angeben.', 'event-tracker' ), 'error' );
        }

        if ( $event_id > 0 ) {
            if ( self::CPT !== get_post_type( $event_id ) ) {
                return $this->notice( __( 'Ungültige Veranstaltung.', 'event-tracker' ), 'error' );
            }
            $this->begin_cap_override();
            $res = wp_update_post( [
                'ID'          => $event_id,
                'post_title'  => $title,
                'post_status' => 'publish',
            ], true );
            $this->end_cap_override();
            if ( is_wp_error( $res ) ) {
                return $this->notice( sprintf( __( 'Fehler beim Speichern: %s', 'event-tracker' ), $res->get_error_message() ), 'error' );
            }
            update_post_meta( $event_id, self::META_START_TS, $start_ts );
            update_post_meta( $event_id, self::META_END_TS,   $end_ts );
            update_post_meta( $event_id, self::META_REDIRECT_URL, $url );
            update_post_meta( $event_id, self::META_ZOHO_ID,  $zoho );
            update_post_meta( $event_id, self::META_RECORDING_URL, $rec );
            update_post_meta( $event_id, self::META_IFRAME_ENABLE, $iframe_enable );
            update_post_meta( $event_id, self::META_IFRAME_URL,    $iframe );

            return $this->notice( __( 'Veranstaltung wurde aktualisiert.', 'event-tracker' ), 'success' );
        }

        $this->begin_cap_override();
        $post_id = wp_insert_post( [
            'post_type'   => self::CPT,
            'post_title'  => $title,
            'post_status' => 'publish',
        ], true );
        $this->end_cap_override();

        if ( is_wp_error( $post_id ) ) {
            return $this->notice( sprintf( __( 'Fehler beim Erstellen: %s', 'event-tracker' ), $post_id->get_error_message() ), 'error' );
        }

        update_post_meta( $post_id, self::META_START_TS, $start_ts );
        update_post_meta( $post_id, self::META_END_TS,   $end_ts );
        update_post_meta( $post_id, self::META_REDIRECT_URL, $url );
        update_post_meta( $post_id, self::META_ZOHO_ID,  $zoho );
        update_post_meta( $post_id, self::META_RECORDING_URL, $rec );
        update_post_meta( $post_id, self::META_IFRAME_ENABLE, $iframe_enable );
        update_post_meta( $post_id, self::META_IFRAME_URL,    $iframe );

        return $this->notice( __( 'Veranstaltung wurde erstellt.', 'event-tracker' ), 'success' );
    }

    private function notice( string $msg, string $type = 'success' ): string {
        $color = $type === 'success' ? '#d1fae5' : '#fee2e2';
        return '<div style="margin:16px 0;padding:10px;border-radius:8px;background:' . esc_attr( $color ) . ';">' . esc_html( $msg ) . '</div>';
    }

    /* ---------------------------------------------
     * HILFSFUNKTION: Prüfe ob Event aktuell gültig ist (inkl. zusätzliche Termine)
     * -------------------------------------------*/
    private function is_event_valid_now( int $event_id, int $now = 0 ): bool {
        if ( ! $now ) $now = time();

        // Haupt-Zeitraum prüfen
        $start = (int) get_post_meta( $event_id, self::META_START_TS, true );
        $end   = (int) get_post_meta( $event_id, self::META_END_TS, true );

        if ( $start && $end && ( $now >= $start ) && ( $now <= $end ) ) {
            return true;
        }

        // Zusätzliche Termine prüfen (mehrtägige Events)
        $additional = get_post_meta( $event_id, self::META_ADDITIONAL_DATES, true );
        if ( is_array( $additional ) && ! empty( $additional ) ) {
            foreach ( $additional as $date_range ) {
                if ( ! is_array( $date_range ) ) continue;
                $range_start = isset( $date_range['start'] ) ? (int) $date_range['start'] : 0;
                $range_end   = isset( $date_range['end'] )   ? (int) $date_range['end']   : 0;

                if ( $range_start && $range_end && ( $now >= $range_start ) && ( $now <= $range_end ) ) {
                    return true;
                }
            }
        }

        return false;
    }

    /* ---------------------------------------------
     * Eventhandling: Webhook + Redirect + Seiten davor/danach
     * -------------------------------------------*/
    private function handle_event_request( int $event_id ) {
        if ( ! $event_id || self::CPT !== get_post_type( $event_id ) || 'publish' !== get_post_status( $event_id ) ) return;

        $name      = get_the_title( $event_id );
        $start     = (int) get_post_meta( $event_id, self::META_START_TS, true );
        $end       = (int) get_post_meta( $event_id, self::META_END_TS, true );
        $target    = (string) get_post_meta( $event_id, self::META_REDIRECT_URL, true );
        $zoho      = (string) get_post_meta( $event_id, self::META_ZOHO_ID, true );
        $recording = (string) get_post_meta( $event_id, self::META_RECORDING_URL, true );

        // NEU: Iframe-Option (statt Redirect)
        $iframe_on  = (string) get_post_meta( $event_id, self::META_IFRAME_ENABLE, true ) === '1';
        $iframe_src = (string) get_post_meta( $event_id, self::META_IFRAME_URL, true );

        $opts        = get_option( self::OPT_KEY, [] );
        $webhook_url = isset( $opts['webhook_url'] ) ? $opts['webhook_url'] : '';

        $tz  = wp_timezone();
        $df  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );
        $now = time();

        // GEÄNDERT: Nutze neue Hilfsfunktion die auch zusätzliche Termine prüft
        $is_valid   = $this->is_event_valid_now( $event_id, $now );
        $can_iframe = $iframe_on && $iframe_src && esc_url_raw( $iframe_src, [ 'http', 'https' ] );

        /* ---------- NEU 1a) Innerhalb des Zeitraums: Webhook + Iframe statt Redirect ---------- */
        if ( $is_valid && $can_iframe ) {
            if ( $webhook_url ) {
                $query = [];
                foreach ( $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    if ( is_scalar( $v ) ) $query[ sanitize_key( $k ) ] = sanitize_text_field( wp_unslash( (string) $v ) );
                }
                $query['event_id'] = (string) $event_id;
                if ( $zoho !== '' ) $query['zoho_id'] = $zoho;

                $final_webhook = add_query_arg( $query, $webhook_url );
                wp_remote_get( $final_webhook, [ 'timeout' => 3, 'redirection' => 0 ] );
            }

            $from = $start ? wp_date( $df, $start, $tz ) : '—';
            $to   = $end   ? wp_date( $df, $end,   $tz ) : '—';

            $html = '
<style>
    .et-live-wrap{min-height:100vh;background:#0b1020;color:#e5e7eb;display:flex;flex-direction:column;gap:16px;align-items:center;justify-content:center;padding:24px}
    .et-live-info{max-width:960px;text-align:center}
    .et-live-info h2{margin:0 0 8px 0;font-size:1.25rem}
    .et-live-muted{opacity:.8;font-size:.9rem}
    .et-actions{margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;justify-content:center}
    .et-actions a,.et-actions button{display:inline-block;border:1px solid rgba(255,255,255,.25);background:rgba(255,255,255,.08);color:#fff;padding:.6rem .9rem;border-radius:10px;text-decoration:none;cursor:pointer}
    .et-overlay{position:fixed;inset:0;background:rgba(0,0,0,.65);display:none;align-items:center;justify-content:center;z-index:99999;padding:20px}
    .et-modal{background:#000;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.6);width:min(96vw,1280px);aspect-ratio:16/9;position:relative;overflow:hidden}
    .et-modal iframe{position:absolute;inset:0;width:100%;height:100%;border:0}
    .et-close{position:absolute;top:8px;right:8px;font-size:1.2rem;line-height:1;border:1px solid rgba(255,255,255,.35);background:rgba(255,255,255,.08);color:#fff;border-radius:8px;padding:.35rem .6rem}
    @media (max-width:768px){.et-live-wrap{padding:16px}}
</style>
<div class="et-live-wrap">
    <div class="et-live-info">
        <h2>' . esc_html__( 'Webinar ist live', 'event-tracker' ) . '</h2>
        <div class="et-live-muted">' . esc_html( $name ) . ' • ' . esc_html( $from ) . ' → ' . esc_html( $to ) . '</div>
        <div class="et-actions">
            <!-- Smartphone/Tablet & Desktop: Popup (Iframe) per Overlay -->
            <button type="button" id="et-open-popup">' . esc_html__( 'Im Popup öffnen', 'event-tracker' ) . '</button>
            <!-- Smartphone/Tablet: zweites Angebot = neues Fenster mit Iframe-URL -->
            <a href="' . esc_url( $iframe_src ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'In neuem Fenster öffnen', 'event-tracker' ) . '</a>
        </div>
    </div>

    <div class="et-overlay" id="et-ov" role="dialog" aria-label="' . esc_attr__( 'Webinar-Stream', 'event-tracker' ) . '">
        <div class="et-modal">
            <button type="button" class="et-close" id="et-close">×</button>
            <iframe id="et-frame" src="' . esc_url( $iframe_src ) . '" allow="autoplay; camera; microphone" allowfullscreen scrolling="no"></iframe>
        </div>
    </div>
</div>
<script>
(function(){
    var ov       = document.getElementById("et-ov");
    var btnOpen  = document.getElementById("et-open-popup");
    var btnClose = document.getElementById("et-close");

    function showPopup(){ if(ov){ ov.style.display="flex"; } }
    function hidePopup(){ if(ov){ ov.style.display="none"; } }

    if(btnOpen){ btnOpen.addEventListener("click", function(){ showPopup(); }); }
    if(btnClose){ btnClose.addEventListener("click", function(){ hidePopup(); }); }
    document.addEventListener("keydown", function(e){ if(e.key==="Escape"){ hidePopup(); } });

    // Erkennung: Mobile → kein Auto-Open, Desktop → sofort ins Popup
    var isMobile = (function(){
        var w = Math.min(window.innerWidth||9999, screen.width||9999);
        var touch = (("ontouchstart" in window) || navigator.maxTouchPoints>0);
        var ua = navigator.userAgent||"";
        return touch && ( /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(ua) || w < 768 );
    }());
    if(!isMobile){
        // Desktop: direkt ins Popup springen
        showPopup();
    }
}());
</script>
<noscript>
    <!-- Kein JS: Seite zeigt direkt ein Vollbild-Iframe -->
    <style>
        html,body{margin:0;height:100%;background:#000}
        .et-ns{height:100%}
        .et-ns iframe{border:0;width:100%;height:100%}
    </style>
    <div class="et-ns">
        <iframe src="' . esc_url( $iframe_src ) . '" allow="autoplay; camera; microphone" allowfullscreen scrolling="no"></iframe>
    </div>
</noscript>';

            status_header( 200 ); nocache_headers();
            wp_die( $html, esc_html__( 'Webinar live', 'event-tracker' ), [ 'response' => 200 ] );
        }

        /* ---------- 1b) Innerhalb des Zeitraums: Webhook + Redirect (Standard) ---------- */
        if ( $is_valid && $target && esc_url_raw( $target, [ 'http', 'https' ] ) ) {

            if ( $webhook_url ) {
                $query = [];
                foreach ( $_GET as $k => $v ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
                    if ( is_scalar( $v ) ) $query[ sanitize_key( $k ) ] = sanitize_text_field( wp_unslash( (string) $v ) );
                }
                $query['event_id'] = (string) $event_id;
                if ( $zoho !== '' ) $query['zoho_id'] = $zoho;

                $final_webhook = add_query_arg( $query, $webhook_url );
                wp_remote_get( $final_webhook, [ 'timeout' => 3, 'redirection' => 0 ] );
            }

            wp_redirect( $target, 302 );
            exit;
        }

        /* ---------- 2) Nach Veranstaltungsende: Aufzeichnung oder Alternativtext ---------- */
        if ( $end && $now > $end ) {
            if ( $recording && esc_url_raw( $recording, [ 'http', 'https' ] ) ) {
                $from = $start ? wp_date( $df, $start, $tz ) : '—';
                $to   = $end   ? wp_date( $df, $end,   $tz ) : '—';
                $html = '
            <style>
                .et-error-wrap{min-height:40vh;display:flex;align-items:center;justify-content:center;padding:24px}
                .et-card{max-width:720px;width:100%;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 1px 2px rgba(0,0,0,.05)}
                .et-card h2{margin:0 0 12px 0;font-size:1.25rem}
                .et-card p{margin:0 0 8px 0;line-height:1.5}
                .et-muted{color:#6b7280;font-size:.9rem}
                .et-actions{margin-top:12px}
                .et-actions a{display:inline-block;border:1px solid #e5e7eb;border-radius:8px;padding:.5rem .8rem;text-decoration:none}
                @media (max-width:640px){.et-card{padding:18px;border-radius:12px}}
            </style>
            <div class="et-error-wrap">
                <div class="et-card">
                    <h2>' . esc_html__( 'Aufzeichnung', 'event-tracker' ) . '</h2>
                    <p class="et-muted">' . esc_html( $name ) . ' • ' . esc_html( $from ) . ' → ' . esc_html( $to ) . '</p>
                    <p>' . esc_html__( 'Sie werden zur Aufzeichnung weitergeleitet in', 'event-tracker' ) . ' <strong><span id="et-rec-ct">10</span> ' . esc_html__( 'Sekunden', 'event-tracker' ) . '</strong>.</p>
                    <p class="et-actions"><a href="' . esc_url( $recording ) . '">' . esc_html__( 'Jetzt öffnen', 'event-tracker' ) . '</a></p>
                    <noscript><p>' . esc_html__( 'JavaScript ist deaktiviert. Bitte klicken Sie auf „Jetzt öffnen“, um zur Aufzeichnung zu gelangen.', 'event-tracker' ) . '</p></noscript>
                </div>
            </div>
            <script>(function(){var s=10,el=document.getElementById("et-rec-ct");var t=setInterval(function(){s--;if(el)el.textContent=String(Math.max(0,s));if(s<=0){clearInterval(t);location.href=' . wp_json_encode( esc_url( $recording ) ) . ';}},1000);}());</script>';

                status_header( 200 ); nocache_headers();
                wp_die( $html, esc_html__( 'Aufzeichnung', 'event-tracker' ), [ 'response' => 200 ] );
            }

            $alt = isset( $opts['recording_alt_text'] ) && $opts['recording_alt_text'] !== '' ? $opts['recording_alt_text'] : 'Aufzeichnung bald verfügbar.';
            $html = '
        <style>
            .et-error-wrap{min-height:40vh;display:flex;align-items:center;justify-content:center;padding:24px}
            .et-card{max-width:720px;width:100%;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 1px 2px rgba(0,0,0,.05)}
            .et-card h2{margin:0 0 12px 0;font-size:1.25rem}
            .et-card p{margin:0 0 8px 0;line-height:1.5}
            .et-muted{color:#6b7280;font-size:.9rem}
            @media (max-width:640px){.et-card{padding:18px;border-radius:12px}}
        </style>
        <div class="et-error-wrap"><div class="et-card">
            <h2>' . esc_html__( 'Veranstaltung beendet', 'event-tracker' ) . '</h2>
            <p>' . esc_html( $alt ) . '</p>
        </div></div>';
            status_header( 200 ); nocache_headers();
            wp_die( $html, esc_html__( 'Veranstaltung beendet', 'event-tracker' ), [ 'response' => 200 ] );
        }

        /* ---------- 3) Vor Beginn: Hinweis + {countdown} ---------- */
        $tpl = isset( $opts['error_message'] ) ? $opts['error_message'] : 'Dieses Event ist derzeit nicht aktiv. {name} ist gültig von {from} bis {to}. {countdown}';
        $from = $start ? wp_date( $df, $start, $tz ) : '—';
        $to   = $end   ? wp_date( $df, $end,   $tz ) : '—';

        $countdown_placeholder = '<span id="et-ct"></span>';
        $message = strtr( $tpl, [
            '{name}'      => esc_html( $name ),
            '{from}'      => esc_html( $from ),
            '{to}'        => esc_html( $to ),
            '{countdown}' => $countdown_placeholder,
        ] );

        $html = '
    <style>
        .et-error-wrap{min-height:40vh;display:flex;align-items:center;justify-content:center;padding:24px}
        .et-card{max-width:720px;width:100%;border:1px solid #e5e7eb;border-radius:16px;padding:24px;box-shadow:0 1px 2px rgba(0,0,0,.05)}
        .et-card h2{margin:0 0 12px 0;font-size:1.25rem}
        .et-card p{margin:0 0 8px 0;line-height:1.5}
        .et-muted{color:#6b7280;font-size:.9rem}
        @media (max-width:640px){.et-card{padding:18px;border-radius:12px}}
    </style>
    <div class="et-error-wrap">
        <div class="et-card">
            <p>' . wp_kses_post( $message ) . '</p>
            <p class="et-muted">' . esc_html__( 'Bitte warten Sie bis zum Veranstaltungsbeginn.', 'event-tracker' ) . '</p>
        </div>
    </div>
    <script>
    (function(){
        var start = ' . (int) $start . ' * 1000;
        var el = document.getElementById("et-ct");
        function plural(n, s, p){return n===1?s:p;}
        function tick(){
            var d = Math.max(0, Math.floor((start - Date.now())/1000));
            var h = Math.floor(d/3600); d -= h*3600;
            var m = Math.floor(d/60);   d -= m*60;
            var s = d;
            var txt = "Sie werden in " + h + " " + plural(h,"Stunde","Stunden") + " " + m + " " + plural(m,"Minute","Minuten") + " und " + s + " " + plural(s,"Sekunde","Sekunden") + " zum Webinar weitergeleitet";
            if (el) el.textContent = txt;
            if (h===0 && m===0 && s===0) {
                // Jetzt ist der Start erreicht → Reload: Server löst Webhook aus und leitet weiter/zeigt Iframe
                location.replace(location.href);
            }
        }
        tick(); setInterval(tick, 1000);
    }());
    </script>';

        status_header( 200 ); nocache_headers();
        wp_die( $html, esc_html__( 'Event noch nicht aktiv', 'event-tracker' ), [ 'response' => 200 ] );
    }

    /* ======================================================================
     * Mailer-Sektion
     * ====================================================================*/
    private function render_mailer_section(): string {
        $this->begin_cap_override();
        if ( function_exists( 'wp_enqueue_editor' ) ) wp_enqueue_editor();
        if ( function_exists( 'wp_enqueue_media' ) ) wp_enqueue_media();
        $this->end_cap_override();

        $this->enqueue_mailer_script_once();

        $ajax_url   = esc_url( admin_url( 'admin-ajax.php' ) );
        $nonce      = wp_create_nonce( 'et_mailer' );
        $uid        = uniqid( 'etm_' );
        $editor_id  = 'etm_html_' . $uid;

        // Nur laufende & zukünftige Events
        $events = get_posts( [
            'post_type'      => self::CPT,
            'post_status'    => 'publish',
            'numberposts'    => -1,
            'orderby'        => 'meta_value_num',
            'meta_key'       => self::META_START_TS,
            'order'          => 'ASC',
            'fields'         => 'ids',
        ] );
        $now = time(); $event_opts = [];
        foreach ( $events as $eid ) {
            $s = (int) get_post_meta( $eid, self::META_START_TS, true );
            $e = (int) get_post_meta( $eid, self::META_END_TS, true );
            if ( ( $s && $e && $e >= $now ) || ( $s && ! $e ) ) $event_opts[] = $eid;
        }

        $logs = get_posts( [
            'post_type'      => self::CPT_MAIL_LOG,
            'post_status'    => 'any',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ] );

        $tz  = wp_timezone();
        $df  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

        ob_start();
        ?>
        <style>
            .etm-wrap{border:1px solid #e5e7eb;border-radius:12px;padding:16px;margin:24px 0;max-width:980px}
            .etm-grid{display:grid;grid-template-columns:1fr;gap:12px}
            .etm-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
            .etm-row .full{grid-column:1/-1}
            .etm-inline{display:flex;gap:8px;align-items:center;flex-wrap:wrap}
            .etm-input, .etm-select{width:100%}
            .etm-help{font-size:.9rem;color:#6b7280}
            .etm-btn{display:inline-flex;align-items:center;gap:.5rem;border:1px solid #e5e7eb;border-radius:8px;padding:.5rem .8rem;background:#f9fafb;cursor:pointer}
            .etm-btn.primary{background:#2563eb;color:#fff;border-color:#2563eb}
            .etm-btn.danger{background:#ef4444;color:#fff;border-color:#ef4444}
            .etm-btn[disabled]{opacity:.6;pointer-events:none}
            .etm-table{width:100%;border-collapse:collapse;margin-top:16px}
            .etm-table th,.etm-table td{border-bottom:1px solid #e5e7eb;padding:8px;text-align:left;vertical-align:top}
            .etm-badge{display:inline-block;font-size:.75rem;padding:.15rem .5rem;border-radius:.5rem;background:#e5e7eb}
            details.etm-preview{background:#f8fafc;border:1px solid #e5e7eb;border-radius:8px;padding:8px}
            @media (max-width:800px){.etm-row{grid-template-columns:1fr}}

            .etm-switch{position:relative;display:inline-block;width:46px;height:26px;vertical-align:middle}
            .etm-switch input{opacity:0;width:0;height:0}
            .etm-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#d1d5db;transition:.2s;border-radius:999px}
            .etm-slider:before{position:absolute;content:"";height:20px;width:20px;left:3px;bottom:3px;background:white;transition:.2s;border-radius:50%}
            .etm-switch input:checked + .etm-slider{background:#16a34a}
            .etm-switch input:checked + .etm-slider:before{transform:translateX(20px)}
        </style>

        <div class="etm-wrap" data-ajax="<?php echo $ajax_url; ?>" data-nonce="<?php echo esc_attr( $nonce ); ?>" data-editor="<?php echo esc_attr( $editor_id ); ?>">
            <h3><?php esc_html_e( 'HTML-Mail verfassen & versenden', 'event-tracker' ); ?></h3>
            <div class="etm-help">
                <?php echo wp_kses_post( __( 'Der Platzhalter <code>{{URL}}</code> wird durch einen funktionierenden Link ersetzt. Der Platzhalter kann auch innerhalb von <code>&lt;a&gt;</code>-Links oder Buttons verwendet werden. Der Platzhalter <code>{{NAME}}</code> wird durch den Namen des Teilnehmers ersetzt.', 'event-tracker' ) ); ?>
            </div>

            <div class="etm-grid">
                <div class="etm-row">
                    <div class="full">
                        <label><strong><?php esc_html_e( 'Veranstaltung auswählen', 'event-tracker' ); ?></strong></label>
                        <select class="etm-select etm-event-id">
                            <option value=""><?php esc_html_e( '— Veranstaltung wählen —', 'event-tracker' ); ?></option>
                            <?php foreach ( $event_opts as $eid ) :
                                $s = (int) get_post_meta( $eid, self::META_START_TS, true );
                                $e = (int) get_post_meta( $eid, self::META_END_TS, true );
                                $label = get_the_title( $eid );
                                if ( $s ) $label .= ' – ' . wp_date( $df, $s, $tz );
                                if ( $e ) $label .= ' → ' . wp_date( $df, $e, $tz );
                                ?>
                                <option value="<?php echo esc_attr( $eid ); ?>"><?php echo esc_html( $label ); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="etm-help"><?php esc_html_e( 'Es werden nur laufende oder zukünftige Veranstaltungen angezeigt.', 'event-tracker' ); ?></div>
                    </div>
                </div>

                <div class="etm-row">
                    <div class="full">
                        <label><strong><?php esc_html_e( 'Betreff', 'event-tracker' ); ?></strong></label>
                        <input type="text" class="etm-input etm-subject" placeholder="<?php esc_attr_e( 'Betreff der E-Mail', 'event-tracker' ); ?>">
                    </div>
                </div>

                <div class="etm-row">
                    <div class="full">
                        <label><strong><?php esc_html_e( 'HTML-Mail', 'event-tracker' ); ?></strong></label>
                        <div class="etm-editor-wrap">
                            <?php
                            $this->begin_cap_override();
                            $settings  = [
                                'textarea_name' => $editor_id,
                                'editor_height' => 320,
                                'media_buttons' => current_user_can( 'upload_files' ),
                                'tinymce'       => true,
                                'quicktags'     => true,
                            ];
                            wp_editor( '', $editor_id, $settings );
                            $this->end_cap_override();
                            ?>
                        </div>
                    </div>
                </div>

                <div class="etm-row">
                    <div class="full">
                        <label><strong><?php esc_html_e( 'Sendezeitpunkt', 'event-tracker' ); ?></strong></label>
                        <div class="etm-inline">
                            <label><input type="radio" name="etm-when" value="now" checked> <?php esc_html_e( 'Sofort', 'event-tracker' ); ?></label>
                            <label><input type="radio" name="etm-when" value="event_start"> <?php esc_html_e( 'Zu Veranstaltungsbeginn', 'event-tracker' ); ?></label>
                            <label><input type="radio" name="etm-when" value="at"> <?php esc_html_e( 'Am', 'event-tracker' ); ?></label>
                            <input type="datetime-local" class="etm-input etm-when-at" style="max-width:260px;display:none" />
                            <label><input type="radio" name="etm-when" value="until_start"> <?php esc_html_e( 'Intervall bis Veranstaltungsbeginn', 'event-tracker' ); ?></label>
                            <select class="etm-input etm-interval-min" style="max-width:220px;display:none">
                                <option value=""><?php esc_html_e( '— Intervall wählen —', 'event-tracker' ); ?></option>
                                <option value="5">5 <?php esc_html_e( 'Minuten', 'event-tracker' ); ?></option>
                                <option value="15">15 <?php esc_html_e( 'Minuten', 'event-tracker' ); ?></option>
                                <option value="30">30 <?php esc_html_e( 'Minuten', 'event-tracker' ); ?></option>
                                <option value="60">60 <?php esc_html_e( 'Minuten', 'event-tracker' ); ?></option>
                                <option value="120">120 <?php esc_html_e( 'Minuten', 'event-tracker' ); ?></option>
                            </select>
                            <!-- NEU: Intervall-Start -->
                            <input type="datetime-local" class="etm-input etm-interval-start" style="max-width:260px;display:none" placeholder="<?php esc_attr_e( 'Intervall-Start (optional)', 'event-tracker' ); ?>" />
                            <span class="etm-help"><?php echo esc_html( sprintf( __( 'Zeitzone: %s', 'event-tracker' ), wp_timezone_string() ) ); ?></span>
                        </div>
                        <div class="etm-help"><?php esc_html_e( 'Optional: Intervall erst ab einem bestimmten Zeitpunkt starten (z. B. morgen 08:00).', 'event-tracker' ); ?></div>
                    </div>
                </div>

                <div class="etm-row">
                    <div class="full">
                        <div class="etm-inline">
                            <label class="etm-switch" title="<?php echo esc_attr__( 'Pro Veranstaltung wird normalerweise nur eine Nachricht verschickt. Durch Aktivierung dieses Felds wird diese Regel außer Kraft gesetzt und die Nachricht trotzdem verschickt.', 'event-tracker' ); ?>">
                                <input type="checkbox" class="etm-toggle-ignoredate" id="etm_ignoredate" />
                                <span class="etm-slider"></span>
                            </label>
                            <label for="etm_ignoredate"><strong><?php esc_html_e( 'Datumsprüfung deaktivieren', 'event-tracker' ); ?></strong></label>
                        </div>
                        <div class="etm-help"><?php esc_html_e( 'Pro Veranstaltung wird pro Tag normalerweise nur eine Nachricht verschickt. Durch Aktivierung dieses Felds wird diese Regel außer Kraft gesetzt und die Nachricht trotzdem verschickt.', 'event-tracker' ); ?></div>
                    </div>
                </div>

                <div class="etm-row">
                    <div class="full etm-inline">
                        <button type="button" class="etm-btn primary" data-action="send-submit"><?php esc_html_e( 'Abschicken / Planen', 'event-tracker' ); ?></button>
                    </div>
                </div>

                <div class="etm-row">
                    <div class="etm-inline full">
                        <input type="email" class="etm-input etm-test-email" placeholder="<?php esc_attr_e( 'Test-E-Mail-Adresse', 'event-tracker' ); ?>" />
                        <button type="button" class="etm-btn" data-action="send-test"><?php esc_html_e( 'Testmail senden ({{URL}} ersetzen)', 'event-tracker' ); ?></button>
                    </div>
                </div>

                <div class="etm-msg etm-help"></div>
            </div>

            <h3 style="margin-top:24px"><?php esc_html_e( 'Verschickte & geplante Mails', 'event-tracker' ); ?></h3>
            <table class="etm-table etm-log-table">
                <thead>
                <tr>
                    <th><?php esc_html_e( 'Datum', 'event-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Event', 'event-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Zoho-ID', 'event-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'event-tracker' ); ?></th>
                    <th><?php esc_html_e( 'Aktionen', 'event-tracker' ); ?></th>
                </tr>
                </thead>
                <tbody>
                <?php if ( $logs ) :
                    foreach ( $logs as $log ) :
                        $event_id = (int) get_post_meta( $log->ID, self::META_MAIL_EVENT_ID, true );
                        $zoho     = (string) get_post_meta( $log->ID, self::META_MAIL_ZOHO_ID, true );
                        $status   = (string) get_post_meta( $log->ID, self::META_MAIL_STATUS, true );
                        $raw_html = (string) get_post_meta( $log->ID, self::META_MAIL_RAW_HTML, true );
                        $http     = (string) get_post_meta( $log->ID, self::META_MAIL_HTTP_CODE, true );
                        $sched_ts = (int) get_post_meta( $log->ID, self::META_MAIL_SCHED_TS, true );
                        $subject  = (string) get_post_meta( $log->ID, self::META_MAIL_SUBJECT, true );
                        $is_rec   = (string) get_post_meta( $log->ID, self::META_MAIL_RECURRING, true ) === '1';
                        $interval = (int) get_post_meta( $log->ID, self::META_MAIL_INTERVAL, true );
                        $stopped  = (string) get_post_meta( $log->ID, self::META_MAIL_STOPPED, true ) === '1';
                        $from_ts  = (int) get_post_meta( $log->ID, self::META_MAIL_INTERVAL_FROM, true );

                        $event_title = $event_id ? get_the_title( $event_id ) : '—';
                        $status_text = $status . ( $http ? " ($http)" : '' );
                        if ( $status === 'queued' && $sched_ts ) $status_text .= ' – ' . sprintf( __( 'geplant: %s', 'event-tracker' ), wp_date( $df, $sched_ts, $tz ) );
                        if ( $is_rec && $interval ) $status_text .= ' – ' . sprintf( __( 'alle %d Min bis Start', 'event-tracker' ), (int) round( $interval / 60 ) );
                        if ( $from_ts ) $status_text .= ' – ' . sprintf( __( 'ab %s', 'event-tracker' ), wp_date( $df, $from_ts, $tz ) );
                        if ( $stopped ) $status_text = __( 'stopped', 'event-tracker' );
                        $badge = '<span class="etm-badge">' . esc_html( $status_text ) . '</span>';
                        ?>
                        <tr data-log-id="<?php echo esc_attr( $log->ID ); ?>" data-subject="<?php echo esc_attr( $subject ); ?>">
                            <td><?php echo esc_html( get_date_from_gmt( gmdate( 'Y-m-d H:i:s', strtotime( $log->post_date_gmt ) ), 'Y-m-d H:i' ) ); ?></td>
                            <td><?php echo $event_id ? esc_html( $event_title . ' (#' . $event_id . ')' ) : '—'; ?></td>
                            <td><?php echo $zoho ? esc_html( $zoho ) : '—'; ?></td>
                            <td><?php echo $badge; ?></td>
                            <td>
                                <button type="button" class="etm-btn" data-action="reuse" data-event="<?php echo esc_attr( $event_id ); ?>"><?php esc_html_e( 'Wiederverwenden', 'event-tracker' ); ?></button>
                                <button type="button" class="etm-btn" data-action="view"><?php esc_html_e( 'Ansehen', 'event-tracker' ); ?></button>
                                <?php if ( $is_rec && ! $stopped && $status === 'queued' ) : ?>
                                    <button type="button" class="etm-btn danger" data-action="stop"><?php esc_html_e( 'Stoppen', 'event-tracker' ); ?></button>
                                <?php else : ?>
                                    <button type="button" class="etm-btn danger" data-action="delete"><?php esc_html_e( 'Löschen', 'event-tracker' ); ?></button>
                                <?php endif; ?>
                                <details class="etm-preview" style="margin-top:8px">
                                    <summary><?php esc_html_e( 'Vorschau', 'event-tracker' ); ?></summary>
                                    <div class="etm-preview-html"><?php echo wp_kses_post( $raw_html ); ?></div>
                                </details>
                                <textarea class="etm-raw-html" style="display:none"><?php echo esc_textarea( $raw_html ); ?></textarea>
                            </td>
                        </tr>
                    <?php endforeach; else: ?>
                        <tr><td colspan="5"><?php esc_html_e( 'Noch keine Einträge.', 'event-tracker' ); ?></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
        return ob_get_clean();
    }

    private function enqueue_mailer_script_once(): void {
        if ( self::$mailer_script_added ) return;
        self::$mailer_script_added = true;

        // Fix: inline-only Handle ohne leeres src
        wp_register_script( 'et-mailer', false, [], '1.16.1', true );
        wp_enqueue_script( 'et-mailer' );

        $i18n = [
            'selectTemplate'            => __( 'Bitte eine Vorlage auswählen.', 'event-tracker' ),
            'templateInserted'          => __( 'Vorlage eingefügt.', 'event-tracker' ),
            'templateLoadError'         => __( 'Vorlage konnte nicht geladen werden.', 'event-tracker' ),
            'networkTemplateError'      => __( 'Netzwerkfehler beim Laden der Vorlage.', 'event-tracker' ),
            'needTitle'                 => __( 'Bitte einen Vorlagen-Titel eingeben.', 'event-tracker' ),
            'noContentToSave'           => __( 'Keine Inhalte zum Speichern vorhanden.', 'event-tracker' ),
            'templateSaved'             => __( 'Vorlage gespeichert.', 'event-tracker' ),
            'templateSaveError'         => __( 'Vorlage konnte nicht gespeichert werden.', 'event-tracker' ),
            'networkTemplateSaveError'  => __( 'Netzwerkfehler beim Speichern der Vorlage.', 'event-tracker' ),
            'selectTemplateFirst'       => __( 'Bitte zuerst eine Vorlage auswählen.', 'event-tracker' ),
            'confirmDeleteTemplate'     => __( 'Diese Vorlage wirklich löschen?', 'event-tracker' ),
            'templateDeleted'           => __( 'Vorlage gelöscht.', 'event-tracker' ),
            'templateDeleteError'       => __( 'Vorlage konnte nicht gelöscht werden.', 'event-tracker' ),
            'networkTemplateDeleteError'=> __( 'Netzwerkfehler beim Löschen der Vorlage.', 'event-tracker' ),
            'selectEvent'               => __( 'Bitte zuerst eine Veranstaltung auswählen.', 'event-tracker' ),
            'needSubject'               => __( 'Bitte Betreff eingeben.', 'event-tracker' ),
            'mustContainURL'            => __( 'Mail muss den Platzhalter {{URL}} enthalten.', 'event-tracker' ),
            'confirmSendAll'            => __( 'Wirklich abschicken/planen?', 'event-tracker' ),
            'sending'                   => __( 'Sende …', 'event-tracker' ),
            'queuedOk'                  => __( 'Mail wurde geplant.', 'event-tracker' ),
            'queuedOkAlert'             => __( 'Die Mail wurde erfolgreich eingeplant.', 'event-tracker' ),
            'sentOk'                    => __( 'Erfolgreich abgeschickt.', 'event-tracker' ),
            'sentOkAlert'               => __( 'Mail wurde ans System übergeben.', 'event-tracker' ),
            'sendError'                 => __( 'Fehler beim Versand.', 'event-tracker' ),
            'networkSendError'          => __( 'Netzwerkfehler beim Versand.', 'event-tracker' ),
            'needHTML'                  => __( 'Bitte HTML-Inhalt einfügen.', 'event-tracker' ),
            'needTestEmail'             => __( 'Bitte Test-E-Mail-Adresse eingeben.', 'event-tracker' ),
            'confirmSendTest'           => __( 'Testmail jetzt wirklich senden?', 'event-tracker' ),
            'sendingTest'               => __( 'Sende Testmail …', 'event-tracker' ),
            'testOk'                    => __( 'Testmail versendet ({{URL}} wurde ersetzt).', 'event-tracker' ),
            'testOkAlert'               => __( 'Testmail wurde gesendet.', 'event-tracker' ),
            'testError'                 => __( 'Fehler beim Senden der Testmail.', 'event-tracker' ),
            'networkTestError'          => __( 'Netzwerkfehler beim Senden der Testmail.', 'event-tracker' ),
            'reused'                    => __( 'Mailinhalt ins Formular übernommen. Bitte prüfen.', 'event-tracker' ),
            'confirmDeleteLog'          => __( 'Diesen Log-Eintrag wirklich löschen?', 'event-tracker' ),
            'logDeleted'                => __( 'Eintrag gelöscht.', 'event-tracker' ),
            'logDeleteError'            => __( 'Löschen fehlgeschlagen.', 'event-tracker' ),
            'networkLogDeleteError'     => __( 'Netzwerkfehler beim Löschen.', 'event-tracker' ),
            'needWhenAt'                => __( 'Bitte Datum & Uhrzeit für die Planung angeben.', 'event-tracker' ),
            'needInterval'              => __( 'Bitte Intervall auswählen.', 'event-tracker' ),
            'confirmStop'               => __( 'Wirklich stoppen? Geplante Wiederholungen werden abgebrochen.', 'event-tracker' ),
            'stopping'                  => __( 'Stoppe …', 'event-tracker' ),
            'stoppedOk'                 => __( 'Wiederholungen gestoppt.', 'event-tracker' ),
            'stopError'                 => __( 'Konnte nicht gestoppt werden.', 'event-tracker' ),
            'networkStopError'          => __( 'Netzwerkfehler beim Stoppen.', 'event-tracker' ),
        ];
        wp_add_inline_script( 'et-mailer', 'window.ET_MAILER_I18N = ' . wp_json_encode( $i18n ) . ';', 'before' );

        $js = <<<JS
(function(){
'use strict';
function escSel(s){try{return (window.CSS&&CSS.escape)?CSS.escape(s):s.replace(/([ !"#$%&'()*+,./:;<=>?@[\\]^`{|}~])/g,'\\\\$1');}catch(e){return s;}}
function closestWrap(el){while(el && el!==document && !(el.classList&&el.classList.contains('etm-wrap'))){el=el.parentElement;}return (el && el.classList && el.classList.contains('etm-wrap'))?el:null;}
function getEditorId(wrap){return wrap?wrap.getAttribute('data-editor')||'':'';}
function getHTML(wrap){var id=getEditorId(wrap);if(id&&window.tinymce&&tinymce.get(id)){return tinymce.get(id).getContent();}var ta=wrap.querySelector('#'+escSel(id));return ta?ta.value:'';}
function setHTML(wrap,html){var id=getEditorId(wrap);if(id&&window.tinymce&&tinymce.get(id)){tinymce.get(id).setContent(html||'');}else{var ta=wrap.querySelector('#'+escSel(id));if(ta)ta.value=html||'';}}
function ajax(wrap,action,fields){var fd=new FormData();fd.append('action',action);fd.append('nonce',wrap.getAttribute('data-nonce')||'');if(fields){Object.keys(fields).forEach(function(k){fd.append(k,fields[k]);});}return fetch(wrap.getAttribute('data-ajax'),{method:'POST',credentials:'include',body:fd}).then(function(r){return r.text();}).then(function(t){try{return JSON.parse(t);}catch(e){throw new Error('Invalid JSON: '+t);}});}
function showMsg(wrap,msg,ok){var el=wrap.querySelector('.etm-msg');if(!el)return;el.textContent=msg||'';el.style.color=(ok===false)?'#7f1d1d':'#065f46';}
function setBusy(btn,b){if(!btn)return;if(b)btn.setAttribute('disabled','disabled');else btn.removeAttribute('disabled');}

// Toggle Terminierungsfelder
document.addEventListener('change',function(ev){
  var r=ev.target;
  if(!r || r.name!=='etm-when') return;
  var wrap=closestWrap(r); if(!wrap) return;
  var at=wrap.querySelector('.etm-when-at');
  var it=wrap.querySelector('.etm-interval-min');
  var from=wrap.querySelector('.etm-interval-start');
  if(at) at.style.display = (r.value==='at') ? '' : 'none';
  if(it) it.style.display = (r.value==='until_start') ? '' : 'none';
  if(from) from.style.display = (r.value==='until_start') ? '' : 'none';
});

document.addEventListener('click',function(ev){
  var btn=ev.target.closest('.etm-wrap button[data-action]'); if(!btn) return;
  var wrap=closestWrap(btn); if(!wrap) return;
  var action=btn.getAttribute('data-action');
  var eventSel=wrap.querySelector('.etm-event-id');
  var testInput=wrap.querySelector('.etm-test-email');
  var subjectEl=wrap.querySelector('.etm-subject');
  var table=wrap.querySelector('.etm-log-table tbody');
  var html=getHTML(wrap)||'';
  var subject=subjectEl?(subjectEl.value||'').trim():'';
  var when=(wrap.querySelector('input[name="etm-when"]:checked')||{}).value||'now';
  var whenAtEl=wrap.querySelector('.etm-when-at');
  var whenAt=whenAtEl?(whenAtEl.value||'').trim():'';
  var intervalEl=wrap.querySelector('.etm-interval-min');
  var intervalMin=intervalEl?(intervalEl.value||'').trim():'';
  var fromEl=wrap.querySelector('.etm-interval-start');
  var intervalFrom=fromEl?(fromEl.value||'').trim():'';
  var ignoredateEl=wrap.querySelector('.etm-toggle-ignoredate');
  var ignoredate=(ignoredateEl&&ignoredateEl.checked)?'1':'0';

  if(action==='send-submit'){
    if(!eventSel||!parseInt(eventSel.value||'0',10)){showMsg(wrap,ET_MAILER_I18N.selectEvent,false);return;}
    if(!subject){showMsg(wrap,ET_MAILER_I18N.needSubject,false);return;}
    if(!html||html.indexOf('{{URL}}')===-1){showMsg(wrap,ET_MAILER_I18N.mustContainURL,false);return;}
    if(when==='at' && !whenAt){showMsg(wrap,ET_MAILER_I18N.needWhenAt,false);return;}
    if(when==='until_start' && !intervalMin){showMsg(wrap,ET_MAILER_I18N.needInterval,false);return;}
    if(!confirm(ET_MAILER_I18N.confirmSendAll)) return;
    setBusy(btn,true); showMsg(wrap,ET_MAILER_I18N.sending,true);
    ajax(wrap,'et_send_mail',{event_id:eventSel.value,subject:subject,html:html,schedule:when,schedule_at:whenAt, schedule_interval:intervalMin, schedule_interval_start:intervalFrom, ignoredate:ignoredate})
    .then(function(data){
      if(data&&data.ok){
        if(data.mode==='queued'){ showMsg(wrap,ET_MAILER_I18N.queuedOk,true); alert(ET_MAILER_I18N.queuedOkAlert); }
        else{ showMsg(wrap,ET_MAILER_I18N.sentOk,true); alert(ET_MAILER_I18N.sentOkAlert); }
        if(data.row_html&&table){
          var tmp=document.createElement('tbody'); tmp.innerHTML=(data.row_html||'').trim();
          var row=tmp.firstElementChild;
          if(row){ if(table.firstElementChild){table.insertBefore(row,table.firstElementChild);} else {table.appendChild(row);} }
        }
      }else{
        showMsg(wrap,(data&&data.msg)?data.msg:ET_MAILER_I18N.sendError,false);
      }
    })
    .catch(function(){showMsg(wrap,ET_MAILER_I18N.networkSendError,false);})
    .then(function(){setBusy(btn,false);});
  }
  else if(action==='send-test'){
    if(!eventSel||!parseInt(eventSel.value||'0',10)){showMsg(wrap,ET_MAILER_I18N.selectEvent,false);return;}
    if(!subject){showMsg(wrap,ET_MAILER_I18N.needSubject,false);return;}
    if(!html){showMsg(wrap,ET_MAILER_I18N.needHTML,false);return;}
    var to=testInput?(testInput.value||'').trim():'';
    if(!to){showMsg(wrap,ET_MAILER_I18N.needTestEmail,false);return;}
    if(!confirm(ET_MAILER_I18N.confirmSendTest)) return;
    setBusy(btn,true); showMsg(wrap,ET_MAILER_I18N.sendingTest,true);
    ajax(wrap,'et_test_mail',{event_id:eventSel.value,subject:subject,html:html,to:to})
    .then(function(data){
      if(data&&data.ok){showMsg(wrap,ET_MAILER_I18N.testOk,true); alert(ET_MAILER_I18N.testOkAlert);}
      else{showMsg(wrap,(data&&data.msg)?data.msg:ET_MAILER_I18N.testError,false);}
    })
    .catch(function(){showMsg(wrap,ET_MAILER_I18N.networkTestError,false);})
    .then(function(){setBusy(btn,false);});
  }
  else if(action==='reuse'){
    var tr=btn.closest('tr'); if(!tr) return;
    var raw=tr.querySelector('.etm-raw-html'); var htmlPrev=raw?raw.value:'';
    if(!htmlPrev){ var prev=tr.querySelector('.etm-preview-html'); htmlPrev=prev?prev.innerHTML:''; }
    if(htmlPrev){
      setHTML(wrap,htmlPrev);
      var evId=btn.getAttribute('data-event')||'';
      if(eventSel && evId){
        if(!eventSel.querySelector('option[value="'+evId+'"]')){
          var opt=document.createElement('option');
          opt.value=evId; opt.textContent='#'+evId+' (aus Archiv)';
          eventSel.appendChild(opt);
        }
        eventSel.value=String(evId);
      }
      if(subjectEl){ var subj=tr.getAttribute('data-subject')||''; if(subj) subjectEl.value=subj; }
      try{ window.scrollTo({top:wrap.offsetTop,behavior:'smooth'}); }catch(e){}
      showMsg(wrap,ET_MAILER_I18N.reused,true);
    }
  }
  else if(action==='view'){
    var tr=btn.closest('tr'); if(!tr) return; var d=tr.querySelector('details.etm-preview'); if(d) d.open=!d.open;
  }
  else if(action==='delete'){
    if(!confirm(ET_MAILER_I18N.confirmDeleteLog)) return;
    var tr=btn.closest('tr'); if(!tr) return; var id=tr.getAttribute('data-log-id');
    setBusy(btn,true);
    ajax(wrap,'et_delete_mail_log',{id:id})
    .then(function(data){
      if(data&&data.ok){ tr.parentNode.removeChild(tr); showMsg(wrap,ET_MAILER_I18N.logDeleted,true); }
      else{ showMsg(wrap,(data&&data.msg)?data.msg:ET_MAILER_I18N.logDeleteError,false); }
    })
    .catch(function(){showMsg(wrap,ET_MAILER_I18N.networkLogDeleteError,false);})
    .then(function(){setBusy(btn,false);});
  }
  else if(action==='stop'){
    if(!confirm(ET_MAILER_I18N.confirmStop)) return;
    var tr=btn.closest('tr'); if(!tr) return; var id=tr.getAttribute('data-log-id');
    setBusy(btn,true); showMsg(wrap,ET_MAILER_I18N.stopping,true);
    ajax(wrap,'et_stop_mail_job',{id:id})
    .then(function(data){
      if(data&&data.ok){
        var badge=tr.querySelector('.etm-badge'); if(badge) badge.textContent='stopped';
        btn.setAttribute('disabled','disabled');
        showMsg(wrap,ET_MAILER_I18N.stoppedOk,true);
      }else{
        showMsg(wrap,(data&&data.msg)?data.msg:ET_MAILER_I18N.stopError,false);
      }
    })
    .catch(function(){showMsg(wrap,ET_MAILER_I18N.networkStopError,false);})
    .then(function(){setBusy(btn,false);});
  }
});
})();
JS;
        wp_add_inline_script( 'et-mailer', $js, 'after' );
    }

    /* ---------------------------------------------
     * Panels-Script
     * -------------------------------------------*/
    private function enqueue_panels_script_once(): void {
        if ( self::$panels_script_added ) return;
        self::$panels_script_added = true;

        // Fix: inline-only Handle ohne leeres src
        wp_register_script( 'et-panels', false, [], '1.16.1', true );
        wp_enqueue_script( 'et-panels' );

        $i18n = [
            'loading'         => __( 'Laden …', 'event-tracker' ),
            'show'            => __( 'Anzeigen', 'event-tracker' ),
            'hide'            => __( 'Ausblenden', 'event-tracker' ),
            'listBtn'         => __( 'Veranstaltungen anzeigen', 'event-tracker' ),
            'formBtn'         => __( 'Veranstaltung hinzufügen', 'event-tracker' ),
            'loadError'       => __( 'Fehler beim Laden.', 'event-tracker' ),
            'noAccess'        => __( 'Keine Berechtigung.', 'event-tracker' ),
        ];
        wp_add_inline_script( 'et-panels', 'window.ET_PANELS_I18N = ' . wp_json_encode( $i18n ) . ';', 'before' );

        $js = <<<JS
(function(){
'use strict';
function closest(el,sel){while(el && el!==document && !el.matches(sel)){el=el.parentElement;}return (el&&el.matches(sel))?el:null;}
function ajax(wrap,action,fields){
  var fd=new FormData();
  fd.append('action',action);
  fd.append('nonce',wrap.getAttribute('data-nonce')||'');
  if(fields){Object.keys(fields).forEach(function(k){fd.append(k,fields[k]);});}
  return fetch(wrap.getAttribute('data-ajax'),{method:'POST',credentials:'include',body:fd})
    .then(function(r){return r.text();})
    .then(function(t){try{return JSON.parse(t);}catch(e){throw new Error('Invalid JSON: '+t);}});
}
function setBusy(btn,on){
  if(!btn) return;
  if(on){ btn.setAttribute('data-text', btn.textContent); btn.textContent = ET_PANELS_I18N.loading; btn.setAttribute('disabled','disabled'); }
  else { btn.textContent = btn.getAttribute('data-text')||btn.textContent; btn.removeAttribute('disabled'); btn.removeAttribute('data-text'); }
}
function ensurePanelLoaded(wrap,name,extra){
  var panel = wrap.querySelector('.et-panel[data-name="'+name+'"]'); if(!panel) return Promise.reject();
  var msg = wrap.querySelector('.et-msg');
  var params = extra || {};
  if(panel.hasAttribute('data-loaded') && !params.reload){
    panel.classList.remove('et-hidden');
    panel.setAttribute('aria-hidden','false');
    return Promise.resolve(panel);
  }
  var action = (name==='list') ? 'et_fetch_event_list' : 'et_fetch_event_form';
  var forward = window.location.search || '';
  params.forward = forward;
  return ajax(wrap, action, params).then(function(data){
    if(!data || !data.ok){ throw new Error((data&&data.msg)||ET_PANELS_I18N.loadError); }
    panel.innerHTML = data.html || '';
    panel.setAttribute('data-loaded','1');
    panel.classList.remove('et-hidden');
    panel.setAttribute('aria-hidden','false');
    if(msg){ msg.textContent=''; msg.classList.remove('error'); }
    try{ panel.scrollIntoView({behavior:'smooth',block:'start'}); }catch(e){}
    return panel;
  }).catch(function(err){
    if(msg){ msg.textContent = (err && err.message) ? err.message : ET_PANELS_I18N.loadError; msg.classList.add('error'); }
    throw err;
  });
}
function togglePanel(btn, name){
  var wrap = closest(btn, '.et-panels'); if(!wrap) return;
  var panel = wrap.querySelector('.et-panel[data-name="'+name+'"]'); if(!panel) return;
  if(panel.hasAttribute('data-loaded')){
    var hidden = panel.classList.toggle('et-hidden');
    panel.setAttribute('aria-hidden', hidden ? 'true' : 'false');
    btn.textContent = (hidden ? (name==='list'?ET_PANELS_I18N.listBtn:ET_PANELS_I18N.formBtn) : ET_PANELS_I18N.hide);
    return;
  }
  setBusy(btn,true);
  ensurePanelLoaded(wrap, name, {}).then(function(){ btn.textContent = ET_PANELS_I18N.hide; }, function(){})
  .then(function(){ setBusy(btn,false); });
}

document.addEventListener('click', function(ev){
  // Panel Buttons
  var t = ev.target.closest('.et-panels .et-btn[data-panel]'); 
  if(t){ var name = t.getAttribute('data-panel'); togglePanel(t, name); return; }

  // Edit-Button in der Liste
  var editBtn = ev.target.closest('.et-panels [data-action="edit"][data-event-id]');
  if(editBtn){
    var wrap = closest(editBtn, '.et-panels'); if(!wrap) return;
    var eid = parseInt(editBtn.getAttribute('data-event-id')||'0',10);
    var formToggle = wrap.querySelector('.et-panel-controls .et-btn[data-panel="form"]');
    if(formToggle){ formToggle.textContent = ET_PANELS_I18N.hide; }
    ensurePanelLoaded(wrap, 'form', {id:String(eid), reload:true}).then(function(){}, function(){});
    ev.preventDefault();
    return;
  }
});
})();
JS;
        wp_add_inline_script( 'et-panels', $js, 'after' );
    }

    /* ---------------------------------------------
     * AJAX: Vorlage laden/speichern/löschen
     * -------------------------------------------*/
    public function ajax_get_template() {
        if ( ! is_user_logged_in() || ! $this->user_has_plugin_access() ) wp_send_json_error();
        check_ajax_referer( 'et_mailer', 'nonce' );
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id || self::CPT_MAIL_TPL !== get_post_type( $id ) ) wp_send_json( [ 'html' => '' ] );
        $content = get_post_field( 'post_content', $id );
        wp_send_json( [ 'html' => $content ] );
    }
    public function ajax_save_template() {
        if ( ! is_user_logged_in() || ! $this->user_has_plugin_access() ) wp_send_json_error();
        check_ajax_referer( 'et_mailer', 'nonce' );
        $title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
        $html  = isset( $_POST['html'] )  ? wp_unslash( $_POST['html'] ) : '';
        if ( ! $title ) wp_send_json( [ 'ok' => false, 'msg' => __( 'Kein Titel übermittelt.', 'event-tracker' ) ] );
        if ( ! $html )  wp_send_json( [ 'ok' => false, 'msg' => __( 'Kein Inhalt übermittelt.', 'event-tracker' ) ] );

        $this->begin_cap_override();
        $id = wp_insert_post( [
            'post_type'   => self::CPT_MAIL_TPL,
            'post_status' => 'publish',
            'post_title'  => $title,
            'post_content'=> $html,
            'post_author' => get_current_user_id(),
        ], true );
        $this->end_cap_override();

        if ( is_wp_error( $id ) ) wp_send_json( [ 'ok' => false, 'msg' => $id->get_error_message() ] );
        wp_send_json( [ 'ok' => true, 'id' => $id, 'title' => get_the_title( $id ) ] );
    }
    public function ajax_delete_template() {
        if ( ! is_user_logged_in() || ! $this->user_has_plugin_access() ) wp_send_json_error();
        check_ajax_referer( 'et_mailer', 'nonce' );
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id || self::CPT_MAIL_TPL !== get_post_type( $id ) ) wp_send_json( [ 'ok' => false, 'msg' => __( 'Ungültige Vorlage.', 'event-tracker' ) ] );
        $this->begin_cap_override();
        $res = wp_delete_post( $id, true );
        $this->end_cap_override();
        if ( ! $res ) wp_send_json( [ 'ok' => false, 'msg' => __( 'Vorlage konnte nicht gelöscht werden.', 'event-tracker' ) ] );
        wp_send_json( [ 'ok' => true ] );
    }

    /* ---------------------------------------------
     * AJAX: Mail Versand/Planung + Test
     * -------------------------------------------*/
    public function ajax_send_mail() {
        if ( ! is_user_logged_in() || ! $this->user_has_plugin_access() ) wp_send_json_error();
        check_ajax_referer( 'et_mailer', 'nonce' );

        $event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
        $subject  = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
        $html     = isset( $_POST['html'] ) ? wp_unslash( $_POST['html'] ) : '';

        // NEU: Option zum Speichern als Entwurf ohne Versenden
        $save_as_draft = isset( $_POST['save_as_draft'] ) && $_POST['save_as_draft'] === '1';

        $schedule = isset( $_POST['schedule'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule'] ) ) : 'now';
        $schedule = in_array( $schedule, [ 'now', 'at', 'event_start', 'until_start' ], true ) ? $schedule : 'now';
        $schedule_at_raw = isset( $_POST['schedule_at'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_at'] ) ) : '';
        $schedule_interval_min = isset( $_POST['schedule_interval'] ) ? (int) $_POST['schedule_interval'] : 0;
        $schedule_interval_start_raw = isset( $_POST['schedule_interval_start'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_interval_start'] ) ) : '';

        $ignoredate = ( isset( $_POST['ignoredate'] ) && sanitize_text_field( wp_unslash( $_POST['ignoredate'] ) ) === '1' ) ? '1' : '0';
        $ignoredate_bool = ( $ignoredate === '1' );

        if ( ! $event_id || self::CPT !== get_post_type( $event_id ) ) wp_send_json( [ 'ok' => false, 'msg' => __( 'Ungültige Veranstaltung.', 'event-tracker' ) ] );
        if ( $subject === '' ) wp_send_json( [ 'ok' => false, 'msg' => __( 'Betreff fehlt.', 'event-tracker' ) ] );
        if ( ! $html || strpos( $html, '{{URL}}' ) === false ) wp_send_json( [ 'ok' => false, 'msg' => __( 'Mail muss den Platzhalter {{URL}} enthalten.', 'event-tracker' ) ] );

        $zoho = (string) get_post_meta( $event_id, self::META_ZOHO_ID, true );
        if ( '' === $zoho ) wp_send_json( [ 'ok' => false, 'msg' => __( 'Zoho-ID ist für dieses Event nicht hinterlegt.', 'event-tracker' ) ] );

        // NEU: Wenn als Entwurf gespeichert wird, sofort Log-Eintrag erstellen und beenden
        if ( $save_as_draft ) {
            $this->begin_cap_override();
            $log_id = wp_insert_post( [
                'post_type'   => self::CPT_MAIL_LOG,
                'post_status' => 'publish',
                'post_title'  => sprintf( 'Entwurf – %s (#%d)', get_the_title( $event_id ), $event_id ),
                'post_content'=> wp_kses_post( $html ),
                'post_author' => get_current_user_id(),
            ], true );
            $this->end_cap_override();

            if ( is_wp_error( $log_id ) ) wp_send_json( [ 'ok' => false, 'msg' => $log_id->get_error_message() ] );

            update_post_meta( $log_id, self::META_MAIL_EVENT_ID,   $event_id );
            update_post_meta( $log_id, self::META_MAIL_ZOHO_ID,    $zoho );
            update_post_meta( $log_id, self::META_MAIL_RAW_HTML,   $html );
            update_post_meta( $log_id, self::META_MAIL_SUBJECT,    $subject );
            update_post_meta( $log_id, self::META_MAIL_STATUS,     'draft' );
            update_post_meta( $log_id, self::META_MAIL_RECURRING,  '0' );
            update_post_meta( $log_id, self::META_MAIL_STOPPED,    '0' );
            update_post_meta( $log_id, self::META_MAIL_IGNOREDATE, $ignoredate );

            $row_html = $this->render_log_row_html( $log_id );
            wp_send_json( [ 'ok' => true, 'mode' => 'draft', 'row_html' => $row_html ] );
        }

        $opts = get_option( self::OPT_KEY, [] );
        $webhook = isset( $opts['mail_webhook_url'] ) ? $opts['mail_webhook_url'] : '';
        if ( ! $webhook ) wp_send_json( [ 'ok' => false, 'msg' => __( 'Mail Webhook-URL ist nicht konfiguriert.', 'event-tracker' ) ] );

        $tz = wp_timezone();
        $now = time();
        $when_ts = 0;

        if ( $schedule === 'event_start' ) {
            $when_ts = (int) get_post_meta( $event_id, self::META_START_TS, true );
            if ( ! $when_ts ) $schedule = 'now';
        } elseif ( $schedule === 'at' ) {
            if ( $schedule_at_raw ) {
                try { $when_ts = ( new DateTimeImmutable( $schedule_at_raw, $tz ) )->getTimestamp(); } catch ( \Exception $e ) { $when_ts = 0; }
            }
            if ( ! $when_ts ) wp_send_json( [ 'ok' => false, 'msg' => __( 'Ungültiges Planungsdatum.', 'event-tracker' ) ] );
        } elseif ( $schedule === 'until_start' ) {
            $event_start = (int) get_post_meta( $event_id, self::META_START_TS, true );
            if ( ! $event_start || $event_start <= $now ) wp_send_json( [ 'ok' => false, 'msg' => __( 'Veranstaltungsbeginn liegt nicht in der Zukunft.', 'event-tracker' ) ] );
            $interval_min = max( 1, (int) $schedule_interval_min );
            $interval_sec = $interval_min * 60;

            $from_ts = 0;
            if ( $schedule_interval_start_raw ) {
                try { $from_ts = ( new DateTimeImmutable( $schedule_interval_start_raw, $tz ) )->getTimestamp(); } catch ( \Exception $e ) { $from_ts = 0; }
                if ( $from_ts <= $now ) $from_ts = 0; // Vergangenes ignorieren
            }

            // erster Fälligkeitstermin:
            if ( $from_ts ) {
                $when_ts = min( $from_ts, $event_start );
            } else {
                $when_ts = min( $now + $interval_sec, $event_start );
            }

            // Log anlegen
            $this->begin_cap_override();
            $log_id = wp_insert_post( [
                'post_type'   => self::CPT_MAIL_LOG,
                'post_status' => 'publish',
                'post_title'  => sprintf( 'Geplant (Intervall) – %s (#%d)', get_the_title( $event_id ), $event_id ),
                'post_content'=> wp_kses_post( $html ),
                'post_author' => get_current_user_id(),
            ], true );
            $this->end_cap_override();

            if ( is_wp_error( $log_id ) ) wp_send_json( [ 'ok' => false, 'msg' => $log_id->get_error_message() ] );

            update_post_meta( $log_id, self::META_MAIL_EVENT_ID,   $event_id );
            update_post_meta( $log_id, self::META_MAIL_ZOHO_ID,    $zoho );
            update_post_meta( $log_id, self::META_MAIL_RAW_HTML,   $html );
            update_post_meta( $log_id, self::META_MAIL_SUBJECT,    $subject );
            update_post_meta( $log_id, self::META_MAIL_STATUS,     'queued' );
            update_post_meta( $log_id, self::META_MAIL_SCHED_KIND, 'until_start' );
            update_post_meta( $log_id, self::META_MAIL_SCHED_TS,   $when_ts );
            update_post_meta( $log_id, self::META_MAIL_RECURRING,  '1' );
            update_post_meta( $log_id, self::META_MAIL_INTERVAL,   $interval_sec );
            update_post_meta( $log_id, self::META_MAIL_STOPPED,    '0' );
            update_post_meta( $log_id, self::META_MAIL_IGNOREDATE, $ignoredate );
            update_post_meta( $log_id, self::META_MAIL_INTERVAL_FROM, $from_ts );

            if ( ! wp_next_scheduled( self::CRON_HOOK_SINGLE, [ $log_id ] ) ) {
                wp_schedule_single_event( $when_ts, self::CRON_HOOK_SINGLE, [ $log_id ] );
            }

            $row_html = $this->render_log_row_html( $log_id );
            wp_send_json( [ 'ok' => true, 'mode' => 'queued', 'row_html' => $row_html ] );
        }

        $is_future = ( $schedule !== 'now' && $when_ts && $when_ts > $now + 5 );

        if ( $is_future ) {
            $this->begin_cap_override();
            $log_id = wp_insert_post( [
                'post_type'   => self::CPT_MAIL_LOG,
                'post_status' => 'publish',
                'post_title'  => sprintf( 'Geplant – %s (#%d)', get_the_title( $event_id ), $event_id ),
                'post_content'=> wp_kses_post( $html ),
                'post_author' => get_current_user_id(),
            ], true );
            $this->end_cap_override();

            if ( is_wp_error( $log_id ) ) wp_send_json( [ 'ok' => false, 'msg' => $log_id->get_error_message() ] );

            update_post_meta( $log_id, self::META_MAIL_EVENT_ID,   $event_id );
            update_post_meta( $log_id, self::META_MAIL_ZOHO_ID,    $zoho );
            update_post_meta( $log_id, self::META_MAIL_RAW_HTML,   $html );
            update_post_meta( $log_id, self::META_MAIL_SUBJECT,    $subject );
            update_post_meta( $log_id, self::META_MAIL_STATUS,     'queued' );
            update_post_meta( $log_id, self::META_MAIL_SCHED_KIND, $schedule );
            update_post_meta( $log_id, self::META_MAIL_SCHED_TS,   $when_ts );
            update_post_meta( $log_id, self::META_MAIL_RECURRING,  '0' );
            update_post_meta( $log_id, self::META_MAIL_STOPPED,    '0' );
            update_post_meta( $log_id, self::META_MAIL_IGNOREDATE, $ignoredate );

            if ( ! wp_next_scheduled( self::CRON_HOOK_SINGLE, [ $log_id ] ) ) {
                wp_schedule_single_event( $when_ts, self::CRON_HOOK_SINGLE, [ $log_id ] );
            }

            $row_html = $this->render_log_row_html( $log_id );
            wp_send_json( [ 'ok' => true, 'mode' => 'queued', 'row_html' => $row_html ] );
        }

        // Sofort senden
        DGPTM_Logger::info( "Event Tracker: Sende Mail für Event #$event_id an Webhook: $webhook" );

        $resp = wp_remote_post( $webhook, [
            'timeout'     => 20,
            'redirection' => 0,
            'headers'     => [ 'Content-Type' => 'application/json; charset=utf-8' ],
            'body'        => wp_json_encode( [
                'event_id'   => $event_id,
                'zoho_id'    => $zoho,
                'subject'    => $subject,
                'mail_html'  => $html,
                'ignoredate' => $ignoredate_bool,
            ] ),
        ] );

        $http_code = 0; $body = ''; $status = 'error';
        if ( ! is_wp_error( $resp ) ) {
            $http_code = (int) wp_remote_retrieve_response_code( $resp );
            $body      = (string) wp_remote_retrieve_body( $resp );
            $status    = ( $http_code >= 200 && $http_code < 300 ) ? 'sent' : 'error';

            if ( $status === 'error' ) {
                DGPTM_Logger::error( "Event Tracker: Webhook antwortete mit HTTP $http_code für Event #$event_id. Body: $body" );
            } else {
                DGPTM_Logger::info( "Event Tracker: Mail erfolgreich gesendet für Event #$event_id (HTTP $http_code)" );
            }
        } else {
            DGPTM_Logger::error( "Event Tracker: Webhook-Fehler für Event #$event_id: " . $resp->get_error_message() );
        }

        $this->begin_cap_override();
        $log_id = wp_insert_post( [
            'post_type'   => self::CPT_MAIL_LOG,
            'post_status' => 'publish',
            'post_title'  => sprintf( 'Mail an Webhook – %s (#%d)', get_the_title( $event_id ), $event_id ),
            'post_content'=> wp_kses_post( $html ),
            'post_author' => get_current_user_id(),
        ], true );
        $this->end_cap_override();

        if ( ! is_wp_error( $log_id ) ) {
            update_post_meta( $log_id, self::META_MAIL_EVENT_ID,   $event_id );
            update_post_meta( $log_id, self::META_MAIL_ZOHO_ID,    $zoho );
            update_post_meta( $log_id, self::META_MAIL_RAW_HTML,   $html );
            update_post_meta( $log_id, self::META_MAIL_SUBJECT,    $subject );
            update_post_meta( $log_id, self::META_MAIL_STATUS,     $status );
            update_post_meta( $log_id, self::META_MAIL_RECURRING,  '0' );
            update_post_meta( $log_id, self::META_MAIL_STOPPED,    '0' );
            update_post_meta( $log_id, self::META_MAIL_IGNOREDATE, $ignoredate );
            if ( $http_code ) update_post_meta( $log_id, self::META_MAIL_HTTP_CODE, $http_code );
            if ( $body )      update_post_meta( $log_id, self::META_MAIL_HTTP_BODY, $body );
        }

        if ( $status !== 'sent' ) {
            $msg = is_wp_error( $resp ) ? $resp->get_error_message() : sprintf( __( 'Webhook antwortete mit HTTP %d.', 'event-tracker' ), $http_code );
            wp_send_json( [ 'ok' => false, 'msg' => $msg ] );
        }

        $row_html = $this->render_log_row_html( $log_id );
        wp_send_json( [ 'ok' => true, 'mode' => 'sent', 'row_html' => $row_html ] );
    }

    private function render_log_row_html( int $log_id ): string {
        $log = get_post( $log_id );
        if ( ! $log ) return '';
        $tz  = wp_timezone();
        $df  = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

        $event_id = (int) get_post_meta( $log_id, self::META_MAIL_EVENT_ID, true );
        $zoho     = (string) get_post_meta( $log_id, self::META_MAIL_ZOHO_ID, true );
        $status   = (string) get_post_meta( $log_id, self::META_MAIL_STATUS, true );
        $http     = (string) get_post_meta( $log_id, self::META_MAIL_HTTP_CODE, true );
        $raw_html = (string) get_post_meta( $log_id, self::META_MAIL_RAW_HTML, true );
        $sched_ts = (int) get_post_meta( $log_id, self::META_MAIL_SCHED_TS, true );
        $subject  = (string) get_post_meta( $log_id, self::META_MAIL_SUBJECT, true );
        $is_rec   = (string) get_post_meta( $log_id, self::META_MAIL_RECURRING, true ) === '1';
        $interval = (int) get_post_meta( $log_id, self::META_MAIL_INTERVAL, true );
        $stopped  = (string) get_post_meta( $log_id, self::META_MAIL_STOPPED, true ) === '1';
        $from_ts  = (int) get_post_meta( $log_id, self::META_MAIL_INTERVAL_FROM, true );

        $event_title = $event_id ? get_the_title( $event_id ) : '—';
        $status_text = $status . ( $http ? " ($http)" : '' );
        if ( $status === 'queued' && $sched_ts ) $status_text .= ' – ' . sprintf( __( 'geplant: %s', 'event-tracker' ), wp_date( $df, $sched_ts, $tz ) );
        if ( $is_rec && $interval ) $status_text .= ' – ' . sprintf( __( 'alle %d Min bis Start', 'event-tracker' ), (int) round( $interval / 60 ) );
        if ( $from_ts ) $status_text .= ' – ' . sprintf( __( 'ab %s', 'event-tracker' ), wp_date( $df, $from_ts, $tz ) );
        if ( $stopped ) $status_text = __( 'stopped', 'event-tracker' );
        $badge = '<span class="etm-badge">' . esc_html( $status_text ) . '</span>';
        $date = get_date_from_gmt( gmdate( 'Y-m-d H:i:s', strtotime( $log->post_date_gmt ) ), 'Y-m-d H:i' );

        $html  = '<tr data-log-id="' . esc_attr( $log_id ) . '" data-subject="' . esc_attr( $subject ) . '">';
        $html .= '<td>' . esc_html( $date ) . '</td>';
        $html .= '<td>' . ( $event_id ? esc_html( $event_title . ' (#' . $event_id . ')' ) : '—' ) . '</td>';
        $html .= '<td>' . ( $zoho ? esc_html( $zoho ) : '—' ) . '</td>';
        $html .= '<td>' . $badge . '</td>';
        $html .= '<td>';
        $html .= '<button type="button" class="etm-btn" data-action="reuse" data-event="' . esc_attr( $event_id ) . '">' . esc_html__( 'Wiederverwenden', 'event-tracker' ) . '</button> ';
        $html .= '<button type="button" class="etm-btn" data-action="view">' . esc_html__( 'Ansehen', 'event-tracker' ) . '</button> ';
        if ( $is_rec && ! $stopped && $status === 'queued' ) {
            $html .= '<button type="button" class="etm-btn danger" data-action="stop">' . esc_html__( 'Stoppen', 'event-tracker' ) . '</button>';
        } else {
            $html .= '<button type="button" class="etm-btn danger" data-action="delete">' . esc_html__( 'Löschen', 'event-tracker' ) . '</button>';
        }
        $html .= '<details class="etm-preview" style="margin-top:8px"><summary>' . esc_html__( 'Vorschau', 'event-tracker' ) . '</summary><div class="etm-preview-html">' . wp_kses_post( $raw_html ) . '</div></details>';
        $html .= '<textarea class="etm-raw-html" style="display:none">' . esc_textarea( $raw_html ) . '</textarea>';
        $html .= '</td></tr>';

        return $html;
    }

    /* ---------------------------------------------
     * Cron & Fallback: geplante Jobs abarbeiten (mit Lock)
     * -------------------------------------------*/
    public function cron_run_mail_job( $log_id ) {
        $log = get_post( $log_id );
        if ( ! $log || self::CPT_MAIL_LOG !== $log->post_type ) return;
        $status = (string) get_post_meta( $log_id, self::META_MAIL_STATUS, true );
        if ( $status !== 'queued' ) return;
        $when_ts = (int) get_post_meta( $log_id, self::META_MAIL_SCHED_TS, true );
        if ( $when_ts && $when_ts > time() + 2 ) return;
        $this->process_log_send( $log_id );
    }
    public function maybe_process_due_jobs() {
        $now = time();
        $q = new WP_Query( [
            'post_type'      => self::CPT_MAIL_LOG,
            'post_status'    => 'any',
            'posts_per_page' => self::MAX_JOBS_PER_TICK,
            'orderby'        => 'meta_value_num',
            'meta_key'       => self::META_MAIL_SCHED_TS,
            'order'          => 'ASC',
            'meta_query'     => [
                [ 'key' => self::META_MAIL_STATUS,   'value' => 'queued', 'compare' => '=' ],
                [ 'key' => self::META_MAIL_SCHED_TS, 'value' => $now,     'compare' => '<=', 'type' => 'NUMERIC' ],
            ],
            'fields'         => 'ids',
        ] );
        if ( $q->have_posts() ) {
            foreach ( $q->posts as $log_id ) $this->process_log_send( (int) $log_id );
        }
        wp_reset_postdata();
    }

    private function acquire_log_lock( int $log_id ) : bool {
        return add_post_meta( $log_id, self::META_MAIL_LOCK, time(), true );
    }
    private function release_log_lock( int $log_id ) : void {
        delete_post_meta( $log_id, self::META_MAIL_LOCK );
    }

    /**
     * Versand eines queued-Logs (mit Lock + Wiederholung bis Start)
     */
    private function process_log_send( int $log_id ) : void {
        if ( ! $this->acquire_log_lock( $log_id ) ) return;

        try {
            $event_id = (int) get_post_meta( $log_id, self::META_MAIL_EVENT_ID, true );
            $zoho     = (string) get_post_meta( $log_id, self::META_MAIL_ZOHO_ID, true );
            $subject  = (string) get_post_meta( $log_id, self::META_MAIL_SUBJECT, true );
            $html     = (string) get_post_meta( $log_id, self::META_MAIL_RAW_HTML, true );
            $ignore   = get_post_meta( $log_id, self::META_MAIL_IGNOREDATE, true ) === '1';

            if ( ! $event_id || self::CPT !== get_post_type( $event_id ) || '' === $zoho || $subject === '' || $html === '' ) {
                update_post_meta( $log_id, self::META_MAIL_STATUS, 'error' );
                update_post_meta( $log_id, self::META_MAIL_HTTP_BODY, 'Invalid queued job data.' );
                return;
            }

            $opts    = get_option( self::OPT_KEY, [] );
            $webhook = isset( $opts['mail_webhook_url'] ) ? $opts['mail_webhook_url'] : '';
            if ( ! $webhook ) {
                update_post_meta( $log_id, self::META_MAIL_STATUS, 'error' );
                update_post_meta( $log_id, self::META_MAIL_HTTP_BODY, 'Webhook not configured.' );
                return;
            }

            $resp = wp_remote_post( $webhook, [
                'timeout'     => 20,
                'redirection' => 0,
                'headers'     => [ 'Content-Type' => 'application/json; charset=utf-8' ],
                'body'        => wp_json_encode( [
                    'event_id'   => $event_id,
                    'zoho_id'    => $zoho,
                    'subject'    => $subject,
                    'mail_html'  => $html,
                    'ignoredate' => $ignore,
                ] ),
            ] );

            $http_code = 0; $body = ''; $status = 'error';
            if ( ! is_wp_error( $resp ) ) {
                $http_code = (int) wp_remote_retrieve_response_code( $resp );
                $body      = (string) wp_remote_retrieve_body( $resp );
                $status    = ( $http_code >= 200 && $http_code < 300 ) ? 'sent' : 'error';
            } else {
                $body = $resp->get_error_message();
            }

            if ( $http_code ) update_post_meta( $log_id, self::META_MAIL_HTTP_CODE, $http_code );
            if ( $body )      update_post_meta( $log_id, self::META_MAIL_HTTP_BODY, $body );

            $is_rec     = (string) get_post_meta( $log_id, self::META_MAIL_RECURRING, true ) === '1';
            $stopped    = (string) get_post_meta( $log_id, self::META_MAIL_STOPPED, true ) === '1';
            $interval   = (int) get_post_meta( $log_id, self::META_MAIL_INTERVAL, true );
            $event_start= (int) get_post_meta( $event_id, self::META_START_TS, true );
            $due_ts     = (int) get_post_meta( $log_id, self::META_MAIL_SCHED_TS, true );

            if ( $is_rec && ! $stopped && $interval > 0 && $event_start ) {
                $next = $due_ts + $interval; // NEU: auf Basis des letzten Fälligkeitstermins, nicht "jetzt"
                if ( $next <= $event_start ) {
                    update_post_meta( $log_id, self::META_MAIL_STATUS,   'queued' );
                    update_post_meta( $log_id, self::META_MAIL_SCHED_TS, $next );
                    if ( ! wp_next_scheduled( self::CRON_HOOK_SINGLE, [ $log_id ] ) ) {
                        wp_schedule_single_event( $next, self::CRON_HOOK_SINGLE, [ $log_id ] );
                    }
                    return;
                }
            }
            update_post_meta( $log_id, self::META_MAIL_STATUS, $status );
        } finally {
            $this->release_log_lock( $log_id );
        }
    }

    /* ---------------------------------------------
     * AJAX: Testmail
     * -------------------------------------------*/
    public function ajax_test_mail() {
        if ( ! is_user_logged_in() || ! $this->user_has_plugin_access() ) wp_send_json_error();
        check_ajax_referer( 'et_mailer', 'nonce' );

        $event_id = isset( $_POST['event_id'] ) ? absint( $_POST['event_id'] ) : 0;
        $subject  = isset( $_POST['subject'] ) ? sanitize_text_field( wp_unslash( $_POST['subject'] ) ) : '';
        $to       = isset( $_POST['to'] )       ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
        $html     = isset( $_POST['html'] )     ? wp_unslash( $_POST['html'] ) : '';

        if ( ! $event_id || self::CPT !== get_post_type( $event_id ) ) wp_send_json( [ 'ok' => false, 'msg' => __( 'Ungültige Veranstaltung.', 'event-tracker' ) ] );
        if ( $subject === '' ) wp_send_json( [ 'ok' => false, 'msg' => __( 'Betreff fehlt.', 'event-tracker' ) ] );
        if ( ! $to || ! is_email( $to ) ) wp_send_json( [ 'ok' => false, 'msg' => __( 'Ungültige E-Mail-Adresse.', 'event-tracker' ) ] );
        if ( ! $html ) wp_send_json( [ 'ok' => false, 'msg' => __( 'Kein HTML-Inhalt.', 'event-tracker' ) ] );

        $url = $this->build_event_link( $event_id );
        $html_replaced = str_replace( '{{URL}}', esc_url( $url ), $html );

        $headers = [ 'Content-Type: text/html; charset=UTF-8' ];
        $ok = wp_mail( $to, $subject, $html_replaced, $headers );
        if ( ! $ok ) wp_send_json( [ 'ok' => false, 'msg' => __( 'Senden fehlgeschlagen (wp_mail).', 'event-tracker' ) ] );

        $this->begin_cap_override();
        $log_id = wp_insert_post( [
            'post_type'   => self::CPT_MAIL_LOG,
            'post_status' => 'publish',
            'post_title'  => sprintf( 'Testmail – %s (#%d)', get_the_title( $event_id ), $event_id ),
            'post_content'=> wp_kses_post( $html_replaced ),
            'post_author' => get_current_user_id(),
        ], true );
        $this->end_cap_override();

        if ( ! is_wp_error( $log_id ) ) {
            update_post_meta( $log_id, self::META_MAIL_EVENT_ID, $event_id );
            update_post_meta( $log_id, self::META_MAIL_ZOHO_ID,  (string) get_post_meta( $event_id, self::META_ZOHO_ID, true ) );
            update_post_meta( $log_id, self::META_MAIL_RAW_HTML, $html_replaced );
            update_post_meta( $log_id, self::META_MAIL_STATUS,   'test' );
            update_post_meta( $log_id, self::META_MAIL_SUBJECT,  $subject );
            update_post_meta( $log_id, self::META_MAIL_IGNOREDATE, '0' );
        }

        wp_send_json( [ 'ok' => true ] );
    }

    public function filter_mail_content_type( $type ) { return 'text/html; charset=UTF-8'; }
    private function build_event_link( int $event_id ): string {
        $base  = home_url( '/eventtracker' );
        $query = [ self::QUERY_KEY_EVENT => (string) $event_id ];
        return add_query_arg( $query, $base );
    }

    /* ---------------------------------------------
     * Panels: Liste/Formular via AJAX
     * -------------------------------------------*/
    public function ajax_fetch_event_list() {
        check_ajax_referer( 'et_panels', 'nonce' );

        $forward_raw = isset( $_POST['forward'] ) ? (string) wp_unslash( $_POST['forward'] ) : '';
        $forward_get = [];
        if ( $forward_raw ) {
            $tmp = [];
            parse_str( ltrim( $forward_raw, '?' ), $tmp );
            foreach ( $tmp as $k => $v ) {
                if ( is_scalar( $v ) && $k !== self::QUERY_KEY_EVENT && $k !== self::QUERY_KEY_TRACKER ) {
                    $forward_get[ sanitize_key( $k ) ] = sanitize_text_field( (string) $v );
                }
            }
        }

        $html = $this->render_list_table( /*show_action_link=*/true, $forward_get );
        wp_send_json( [ 'ok' => true, 'html' => $html ] );
    }
    public function ajax_fetch_event_form() {
        // GEÄNDERT: Jeder eingeloggte User kann das Formular holen
        // (verhindert "0" von admin-ajax.php bei nicht eingeloggten Requests)
        if ( ! is_user_logged_in() ) {
            wp_send_json( [ 'ok' => false, 'msg' => __( 'Sie müssen eingeloggt sein.', 'event-tracker' ) ] );
        }
        check_ajax_referer( 'et_panels', 'nonce' );
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        $html = $this->render_frontend_form( $id );
        wp_send_json( [ 'ok' => true, 'html' => $html ] );
    }

    /* ---------------------------------------------
     * AJAX: Log löschen / Job stoppen
     * -------------------------------------------*/
    public function ajax_delete_mail_log() {
        if ( ! is_user_logged_in() || ! $this->user_has_plugin_access() ) wp_send_json_error();
        check_ajax_referer( 'et_mailer', 'nonce' );
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id || self::CPT_MAIL_LOG !== get_post_type( $id ) ) wp_send_json( [ 'ok' => false, 'msg' => __( 'Ungültiger Eintrag.', 'event-tracker' ) ] );
        $this->begin_cap_override();
        $res = wp_delete_post( $id, true );
        $this->end_cap_override();
        if ( ! $res ) wp_send_json( [ 'ok' => false, 'msg' => __( 'Konnte nicht gelöscht werden.', 'event-tracker' ) ] );
        wp_send_json( [ 'ok' => true ] );
    }
    public function ajax_stop_mail_job() {
        if ( ! is_user_logged_in() || ! $this->user_has_plugin_access() ) wp_send_json_error();
        check_ajax_referer( 'et_mailer', 'nonce' );
        $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
        if ( ! $id || self::CPT_MAIL_LOG !== get_post_type( $id ) ) wp_send_json( [ 'ok' => false, 'msg' => __( 'Ungültiger Eintrag.', 'event-tracker' ) ] );
        update_post_meta( $id, self::META_MAIL_STOPPED, '1' );
        update_post_meta( $id, self::META_MAIL_STATUS,  'stopped' );
        wp_clear_scheduled_hook( self::CRON_HOOK_SINGLE, [ $id ] );
        wp_send_json( [ 'ok' => true ] );
    }

    /* ---------------------------------------------
     * Shortcode: [event_mailer_right]
     * -------------------------------------------*/
    public function shortcode_mailer_right( $atts = [] ) : string {
        $uid = get_current_user_id();
        if ( ! $uid ) return '0';
        $val = get_user_meta( $uid, self::USER_META_ACCESS, true );
        return ( $val === '1' ) ? '1' : '0';
    }

    /* ---------------------------------------------
     * Benutzerprofil: Toggle als Schieberegler
     * -------------------------------------------*/
    public function render_user_meta( $user ) {
        $val = get_user_meta( $user->ID, self::USER_META_ACCESS, true );
        $checked = ( $val === '1' ) ? 'checked' : '';
        ?>
        <h2><?php esc_html_e( 'Event Mailer Zugriff', 'event-tracker' ); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="et_mailer_access"><?php esc_html_e( 'Zugriff erlaubt', 'event-tracker' ); ?></label></th>
                <td>
                    <style>
                        .et-switch{position:relative;display:inline-block;width:52px;height:28px;vertical-align:middle}
                        .et-switch input{opacity:0;width:0;height:0}
                        .et-slider{position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:#d1d5db;transition:.2s;border-radius:999px}
                        .et-slider:before{position:absolute;content:"";height:22px;width:22px;left:3px;bottom:3px;background:white;transition:.2s;border-radius:50%}
                        input:checked + .et-slider{background:#16a34a}
                        input:checked + .et-slider:before{transform:translateX(24px)}
                    </style>
                    <label class="et-switch">
                        <input type="checkbox" id="et_mailer_access" name="et_mailer_access" value="1" <?php echo $checked; ?> />
                        <span class="et-slider"></span>
                    </label>
                    <p class="description"><?php esc_html_e( 'Wenn aktiv, liefert der Shortcode [event_mailer_right] den Wert "1".', 'event-tracker' ); ?></p>
                </td>
            </tr>
        </table>
        <?php
    }
    public function save_user_meta( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) return;
        $val = isset( $_POST['et_mailer_access'] ) ? '1' : '0';
        update_user_meta( $user_id, self::USER_META_ACCESS, $val );
    }
}

new ET_Event_Tracker();

/* ---------------------------------------------
 * Aktivierung/Deaktivierung
 * -------------------------------------------*/
register_activation_hook( __FILE__, function () {
    $inst = new ET_Event_Tracker();
    $inst->register_cpt();
    $inst->register_mail_cpts();
    $inst->add_rewrite();
    flush_rewrite_rules();
} );
register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );
