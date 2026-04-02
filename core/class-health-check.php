<?php
/**
 * DGPTM Suite Health-Check REST API
 *
 * Stellt einen authentifizierten REST-Endpoint bereit, der aktuelle
 * Fehler, Warnungen und Modul-Status zurueckgibt.
 *
 * Endpoint: /wp-json/dgptm/v1/health-check
 * Auth: Bearer-Token (in wp_options: dgptm_health_check_token)
 *
 * @package DGPTM_Suite
 * @since 3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DGPTM_Health_Check {

	private static $instance = null;

	const OPT_TOKEN = 'dgptm_health_check_token';

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
		add_action( 'admin_init', [ $this, 'maybe_generate_token' ] );
	}

	/**
	 * Token generieren falls noch keiner existiert
	 */
	public function maybe_generate_token() {
		if ( ! get_option( self::OPT_TOKEN ) ) {
			update_option( self::OPT_TOKEN, wp_generate_password( 48, false ), false );
		}
	}

	/**
	 * REST-Routen registrieren
	 */
	public function register_routes() {
		register_rest_route( 'dgptm/v1', '/health-check', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_health_check' ],
			'permission_callback' => [ $this, 'check_auth' ],
		] );

		register_rest_route( 'dgptm/v1', '/user-check', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_user_check' ],
			'permission_callback' => [ $this, 'check_auth' ],
		] );

		register_rest_route( 'dgptm/v1', '/survey-diag', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_survey_diag' ],
			'permission_callback' => [ $this, 'check_auth' ],
		] );

		register_rest_route( 'dgptm/v1', '/fobi-repair', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_fobi_repair' ],
			'permission_callback' => [ $this, 'check_auth' ],
		] );

		register_rest_route( 'dgptm/v1', '/fobi-attachment', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_fobi_attachment' ],
			'permission_callback' => [ $this, 'check_auth' ],
		] );

		register_rest_route( 'dgptm/v1', '/fobi-reevaluate', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'handle_fobi_reevaluate' ],
			'permission_callback' => [ $this, 'check_auth' ],
		] );

		register_rest_route( 'dgptm/v1', '/forum-diag', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'handle_forum_diag' ],
			'permission_callback' => [ $this, 'check_auth' ],
		] );
	}

	/**
	 * User-Check: ACF-Felder und Rollen eines Users pruefen
	 * ?login=jokuhle&fields=fobiupload,zeitschriftmanager,testbereich
	 */
	/**
	 * Fobi-Neubewertung via REST API
	 * POST /wp-json/dgptm/v1/fobi-reevaluate?post_id=41983
	 */
	/**
	 * Fobi Attachment Debug
	 * GET /wp-json/dgptm/v1/fobi-attachment?post_id=41979
	 */
	/**
	 * Survey-Diagnose: Alle Surveys mit Fragen und Antwort-Statistiken
	 * GET /wp-json/dgptm/v1/survey-diag?id=1 (einzeln) oder ohne id (alle)
	 */
	public function handle_survey_diag( $request ) {
		global $wpdb;
		$prefix = $wpdb->prefix . 'dgptm_survey_';
		$survey_id = intval( $request->get_param( 'id' ) ?? 0 );

		// Surveys laden
		if ( $survey_id ) {
			$surveys = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$prefix}s WHERE id = %d", $survey_id ) );
		} else {
			$surveys = $wpdb->get_results( "SELECT * FROM {$prefix}s ORDER BY id" );
		}

		$result = [];
		foreach ( $surveys as $s ) {
			$questions = $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM {$prefix}questions WHERE survey_id = %d ORDER BY sort_order",
				$s->id
			) );

			$response_count = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$prefix}responses WHERE survey_id = %d AND status = 'completed'",
				$s->id
			) );

			$q_data = [];
			foreach ( $questions as $q ) {
				$choices = $q->choices ? json_decode( $q->choices, true ) : [];
				$skip = $q->skip_logic ? json_decode( $q->skip_logic, true ) : [];
				$validation = $q->validation_rules ? json_decode( $q->validation_rules, true ) : [];

				// Antwort-Statistik fuer diese Frage
				$answers = $wpdb->get_col( $wpdb->prepare(
					"SELECT a.answer_value FROM {$prefix}answers a
					 JOIN {$prefix}responses r ON r.id = a.response_id
					 WHERE a.question_id = %d AND r.status = 'completed'",
					$q->id
				) );

				$q_data[] = [
					'id' => $q->id,
					'sort_order' => $q->sort_order,
					'group_label' => $q->group_label,
					'type' => $q->question_type,
					'text' => $q->question_text,
					'description' => $q->description,
					'choices' => $choices,
					'required' => (bool) $q->is_required,
					'skip_logic' => $skip,
					'validation' => $validation,
					'parent_id' => $q->parent_question_id,
					'parent_value' => $q->parent_answer_value,
					'answer_count' => count( $answers ),
					'sample_answers' => array_slice( $answers, 0, 5 ),
				];
			}

			$result[] = [
				'id' => $s->id,
				'title' => $s->title,
				'slug' => $s->slug,
				'description' => $s->description,
				'status' => $s->status,
				'access_mode' => $s->access_mode,
				'duplicate_check' => $s->duplicate_check,
				'show_progress' => (bool) $s->show_progress,
				'allow_save_resume' => (bool) $s->allow_save_resume,
				'completion_text' => $s->completion_text,
				'response_count' => (int) $response_count,
				'questions' => $q_data,
			];
		}

		return new WP_REST_Response( $result, 200 );
	}

	public function handle_fobi_attachment( $request ) {
		$post_id = intval( $request->get_param( 'post_id' ) ?? 0 );
		if ( ! $post_id ) return new WP_REST_Response( [ 'error' => 'post_id fehlt' ], 400 );

		global $wpdb;

		// Alle relevanten Meta-Felder lesen
		$acf_val = function_exists( 'get_field' ) ? get_field( 'attachements', $post_id ) : 'ACF not loaded';
		$meta_val = get_post_meta( $post_id, 'attachements', true );
		$meta_underscore = get_post_meta( $post_id, '_attachements', true );

		// Alle post_meta mit "attach" im Key
		$all_attach_meta = $wpdb->get_results( $wpdb->prepare(
			"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
			$post_id, '%attach%'
		) );

		return new WP_REST_Response( [
			'post_id' => $post_id,
			'acf_get_field' => $acf_val,
			'meta_attachements' => $meta_val,
			'meta__attachements' => $meta_underscore,
			'all_attach_meta' => $all_attach_meta,
		], 200 );
	}

	/**
	 * Fobi Attachment Repair
	 * POST /wp-json/dgptm/v1/fobi-repair
	 */
	public function handle_fobi_repair( $request ) {
		global $wpdb;

		$upload_dir = wp_upload_dir();
		$protected_path = $upload_dir['basedir'] . '/fobi-protected';
		$protected_url = $upload_dir['baseurl'] . '/fobi-protected';

		if ( ! is_dir( $protected_path ) ) {
			return new WP_REST_Response( [ 'error' => 'fobi-protected Ordner nicht gefunden' ], 404 );
		}

		// Alle Dateien im geschuetzten Ordner
		$files = glob( $protected_path . '/*.*' );
		$file_map = [];
		foreach ( $files as $f ) {
			$file_map[ basename( $f ) ] = $f;
		}

		// Alle Fortbildungen mit attachements = 0 oder leer
		$broken = $wpdb->get_results(
			"SELECT p.ID, p.post_title, pm.meta_value
			 FROM {$wpdb->posts} p
			 JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = 'attachements'
			 WHERE p.post_type = 'fortbildung'
			 AND (pm.meta_value = '0' OR pm.meta_value = '' OR pm.meta_value IS NULL)"
		);

		$repaired = 0;
		$not_found = 0;
		$details = [];

		foreach ( $broken as $post ) {
			$post_id = $post->ID;
			$title = $post->post_title;

			// Versuche Attachment ueber post_parent zu finden
			$attachment = $wpdb->get_row( $wpdb->prepare(
				"SELECT ID, guid FROM {$wpdb->posts} WHERE post_parent = %d AND post_type = 'attachment' LIMIT 1",
				$post_id
			) );

			if ( $attachment ) {
				$att_id = $attachment->ID;
				$att_path = get_attached_file( $att_id );

				// Pruefen ob Datei existiert (evtl. schon verschoben)
				if ( ! $att_path || ! file_exists( $att_path ) ) {
					// In fobi-protected suchen
					$att_filename = basename( $attachment->guid );
					if ( isset( $file_map[ $att_filename ] ) ) {
						// Pfad in WP aktualisieren
						update_attached_file( $att_id, $file_map[ $att_filename ] );
						$att_path = $file_map[ $att_filename ];
					}
				}

				// ACF-Feld auf Attachment-ID setzen (nicht URL!)
				update_post_meta( $post_id, 'attachements', strval( $att_id ) );
				$repaired++;
				$details[] = [ 'post_id' => $post_id, 'title' => $title, 'att_id' => $att_id, 'status' => 'repaired' ];
			} else {
				$not_found++;
				$details[] = [ 'post_id' => $post_id, 'title' => $title, 'status' => 'no_attachment' ];
			}
		}

		return new WP_REST_Response( [
			'broken_total' => count( $broken ),
			'repaired' => $repaired,
			'not_found' => $not_found,
			'files_in_protected' => count( $files ),
			'details_sample' => array_slice( $details, 0, 20 ),
		], 200 );
	}

	public function handle_fobi_reevaluate( $request ) {
		$post_id = intval( $request->get_param( 'post_id' ) ?? 0 );
		if ( ! $post_id ) {
			return new WP_REST_Response( [ 'error' => 'post_id fehlt' ], 400 );
		}

		if ( ! function_exists( 'fobi_ebcp_analyze_document' ) ) {
			return new WP_REST_Response( [ 'error' => 'Fobi-Upload Modul nicht geladen' ], 500 );
		}

		$post = get_post( $post_id );
		if ( ! $post || $post->post_type !== 'fortbildung' ) {
			return new WP_REST_Response( [ 'error' => 'Fortbildung nicht gefunden' ], 404 );
		}

		$s = fobi_ebcp_get_settings();

		// Attachment finden
		$attachments = function_exists( 'get_field' ) ? get_field( 'attachements', $post_id ) : null;
		$filepath = '';
		$mime = '';

		if ( is_numeric( $attachments ) ) {
			$filepath = get_attached_file( $attachments );
			$mime = get_post_mime_type( $attachments );
		} elseif ( is_array( $attachments ) && isset( $attachments['ID'] ) ) {
			$filepath = get_attached_file( $attachments['ID'] );
			$mime = get_post_mime_type( $attachments['ID'] );
		} elseif ( is_string( $attachments ) && filter_var( $attachments, FILTER_VALIDATE_URL ) ) {
			// URL — Download in temp
			$tmp = download_url( $attachments, 30 );
			if ( ! is_wp_error( $tmp ) ) {
				$filepath = $tmp;
				$ext = strtolower( pathinfo( $attachments, PATHINFO_EXTENSION ) );
				$mime_map = [ 'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png' ];
				$mime = $mime_map[ $ext ] ?? 'application/pdf';
			}
		}

		if ( empty( $filepath ) || ! file_exists( $filepath ) ) {
			return new WP_REST_Response( [ 'error' => 'Kein Attachment oder Datei nicht gefunden', 'attachment_raw' => $attachments ], 404 );
		}

		// Analyse
		$result = fobi_ebcp_analyze_document( $filepath, $mime, 'REST-API-Test', $s );

		return new WP_REST_Response( [
			'post_id' => $post_id,
			'model' => $s['claude_model'] ?? '(unbekannt)',
			'analysis' => $result,
		], 200 );
	}

	public function handle_user_check( $request ) {
		$login = sanitize_text_field( $request->get_param( 'login' ) ?? '' );
		$field_names = sanitize_text_field( $request->get_param( 'fields' ) ?? 'fobiupload,zeitschriftmanager,testbereich,editor_in_chief' );

		if ( ! $login ) {
			return new WP_REST_Response( [ 'error' => 'Parameter login fehlt' ], 400 );
		}

		$user = get_user_by( 'login', $login );
		if ( ! $user ) {
			return new WP_REST_Response( [ 'error' => 'User nicht gefunden: ' . $login ], 404 );
		}

		$fields = array_map( 'trim', explode( ',', $field_names ) );
		$acf_values = [];
		foreach ( $fields as $field ) {
			$acf_val = function_exists( 'get_field' ) ? get_field( $field, 'user_' . $user->ID ) : null;
			$meta_val = get_user_meta( $user->ID, $field, true );
			$acf_values[ $field ] = [
				'acf' => $acf_val,
				'meta' => $meta_val,
			];
		}

		return new WP_REST_Response( [
			'user_id' => $user->ID,
			'login' => $user->user_login,
			'display_name' => $user->display_name,
			'email' => $user->user_email,
			'roles' => $user->roles,
			'acf_fields' => $acf_values,
		], 200 );
	}

	/**
	 * Forum-Diagnose: zeigt DB-Inhalte der Forum-Tabellen
	 */
	public function handle_forum_diag( $request ) {
		global $wpdb;
		$prefix = $wpdb->prefix . 'dgptm_forum_';

		$diag = [];

		// AGs (Hauptgruppen)
		$diag['ags'] = $wpdb->get_results( "SELECT id, name, slug, group_type, status, is_hidden FROM {$prefix}ags ORDER BY sort_order" );

		// Topics
		$diag['topics'] = $wpdb->get_results( "SELECT id, ag_id, title, slug, access_mode, status, thread_count FROM {$prefix}topics ORDER BY ag_id, sort_order" );

		// Threads (alle, inkl. Status)
		$diag['threads'] = $wpdb->get_results(
			"SELECT th.id, th.topic_id, th.title, th.status, th.is_pinned, th.reply_count, th.author_id, th.created_at, t.ag_id, t.title AS topic_title
			 FROM {$prefix}threads th
			 JOIN {$prefix}topics t ON t.id = th.topic_id
			 ORDER BY th.created_at DESC
			 LIMIT 50"
		);

		// Threads die nicht angezeigt werden (status = deleted ODER topic/ag inaktiv)
		$diag['hidden_threads'] = $wpdb->get_results(
			"SELECT th.id, th.title, th.status AS thread_status, th.author_id, th.created_at,
			        t.id AS topic_id, t.title AS topic_title, t.status AS topic_status, t.access_mode,
			        a.id AS ag_id, a.name AS ag_name, a.status AS ag_status, a.group_type, a.is_hidden
			 FROM {$prefix}threads th
			 JOIN {$prefix}topics t ON t.id = th.topic_id
			 JOIN {$prefix}ags a ON a.id = t.ag_id
			 WHERE th.status = 'deleted'
			    OR t.status != 'active'
			    OR a.status != 'active'
			    OR a.is_hidden = 1
			 ORDER BY th.created_at DESC"
		);

		// Replies Count
		$diag['reply_count'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$prefix}replies" );

		// Memberships
		$diag['memberships'] = $wpdb->get_results(
			"SELECT m.ag_id, a.name AS ag_name, COUNT(*) AS member_count
			 FROM {$prefix}ag_members m
			 JOIN {$prefix}ags a ON a.id = m.ag_id
			 GROUP BY m.ag_id"
		);

		return new WP_REST_Response( $diag, 200 );
	}

	/**
	 * Auth pruefen: Bearer-Token oder eingeloggter Admin
	 *
	 * @param WP_REST_Request $request
	 * @return bool
	 */
	public function check_auth( $request ) {
		// Admin-User immer erlaubt
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Bearer-Token pruefen
		$auth = $request->get_header( 'Authorization' );
		if ( $auth && preg_match( '/^Bearer\s+(.+)$/i', $auth, $m ) ) {
			$token = get_option( self::OPT_TOKEN, '' );
			return $token && hash_equals( $token, trim( $m[1] ) );
		}

		return false;
	}

	/**
	 * Health-Check ausfuehren
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function handle_health_check( $request ) {
		$hours     = min( max( absint( $request->get_param( 'hours' ) ?: 24 ), 1 ), 168 );
		$inc_logs  = $request->get_param( 'logs' ) !== 'false';
		$log_limit = min( 200, max( 10, absint( $request->get_param( 'log_limit' ) ?: 50 ) ) );

		$result = [
			'timestamp'     => current_time( 'c' ),
			'site_url'      => home_url(),
			'suite_version' => defined( 'DGPTM_SUITE_VERSION' ) ? DGPTM_SUITE_VERSION : 'unknown',
			'php_version'   => PHP_VERSION,
			'wp_version'    => get_bloginfo( 'version' ),
		];

		// 1. Letzte Fehler/Warnungen aus dem Logger + debug.log
		$result['logs'] = $this->get_recent_logs( $hours, $log_limit, $inc_logs );

		// 2. Modul-Status
		$result['modules'] = $this->get_module_status();

		// 3. System-Checks
		$result['system'] = $this->get_system_checks();

		// 4. Zusammenfassung mit Issues-Array
		$issues = [];
		$failed_count = count( $result['modules']['failed'] );
		if ( $failed_count > 0 ) {
			$issues[] = $failed_count . ' Modul(e) mit Ladefehlern';
		}
		if ( $result['modules']['active_not_loaded'] > 0 ) {
			$issues[] = $result['modules']['active_not_loaded'] . ' aktive(s) Modul(e) nicht geladen';
		}

		$error_count   = ( $result['logs']['counts']['error'] ?? 0 ) + ( $result['logs']['counts']['critical'] ?? 0 );
		$warning_count = $result['logs']['counts']['warning'] ?? 0;
		if ( $error_count > 0 ) {
			$issues[] = $error_count . ' Fehler in den letzten ' . $hours . ' Stunden';
		}
		if ( isset( $result['system']['zoho_connected'] ) && ! $result['system']['zoho_connected'] ) {
			$issues[] = 'Zoho CRM nicht verbunden';
		}
		if ( ( $result['system']['debug_log_size_mb'] ?? 0 ) > 50 ) {
			$issues[] = 'debug.log zu gross (' . $result['system']['debug_log_size_mb'] . ' MB)';
		}

		if ( empty( $issues ) ) {
			$result['status']  = 'healthy';
			$result['summary'] = 'Keine Fehler in den letzten ' . $hours . ' Stunden';
		} else {
			$result['status']  = ( $error_count > 10 || $failed_count > 3 ) ? 'critical' : 'degraded';
			$result['issues']  = $issues;
			$result['summary'] = implode( '; ', $issues );
		}

		return new WP_REST_Response( $result, 200 );
	}

	/**
	 * Letzte Log-Eintraege abrufen
	 */
	private function get_recent_logs( $hours, $limit = 50, $include_details = true ) {
		$result = [
			'available' => false,
			'hours'     => $hours,
			'counts'    => [],
			'by_module' => [],
			'errors'    => [],
			'warnings'  => [],
		];

		if ( ! class_exists( 'DGPTM_Logger' ) || ! class_exists( 'DGPTM_Logger_Installer' ) ) {
			return $result;
		}
		if ( ! DGPTM_Logger_Installer::table_exists() ) {
			return $result;
		}

		$result['available'] = true;
		$cutoff = date( 'Y-m-d H:i:s', time() - ( $hours * 3600 ) );

		// Counts pro Level (alle)
		global $wpdb;
		$table = DGPTM_Logger_Installer::get_table_name();
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT level, COUNT(*) as cnt FROM {$table} WHERE timestamp >= %s GROUP BY level",
			$cutoff
		), ARRAY_A );
		foreach ( $rows ?: [] as $r ) {
			$result['counts'][ $r['level'] ] = (int) $r['cnt'];
		}

		// Counts nach Modul + Level
		$by_module = $wpdb->get_results( $wpdb->prepare(
			"SELECT module_id, level, COUNT(*) as cnt
			 FROM {$table}
			 WHERE timestamp >= %s AND module_id IS NOT NULL
			 GROUP BY module_id, level
			 ORDER BY cnt DESC",
			$cutoff
		), ARRAY_A );
		$result['by_module'] = $by_module ?: [];

		if ( $include_details ) {
			$errors = DGPTM_Logger::query_logs( [
				'level'     => [ 'error', 'critical' ],
				'date_from' => $cutoff,
				'per_page'  => $limit,
				'order'     => 'DESC',
			] );
			$result['errors'] = $errors['logs'];

			$warnings = DGPTM_Logger::query_logs( [
				'level'     => 'warning',
				'date_from' => $cutoff,
				'per_page'  => $limit,
				'order'     => 'DESC',
			] );
			$result['warnings'] = $warnings['logs'];
		}

		// DB-Stats
		$result['db_stats'] = DGPTM_Logger_Installer::get_stats();

		// debug.log Tail (DGPTM-relevante + PHP-Fehler)
		$result['debug_log'] = $this->read_debug_log_tail( $hours, $limit );

		return $result;
	}

	/**
	 * Letzte relevante Zeilen aus debug.log lesen
	 */
	private function read_debug_log_tail( $hours, $limit ) {
		$path = WP_CONTENT_DIR . '/debug.log';
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return [ 'available' => false, 'entries' => [] ];
		}

		$size_mb   = round( filesize( $path ) / 1024 / 1024, 2 );
		$max_bytes = 512 * 1024;
		$fsize     = filesize( $path );
		$offset    = max( 0, $fsize - $max_bytes );

		$fp = fopen( $path, 'r' );
		if ( ! $fp ) {
			return [ 'available' => false, 'entries' => [] ];
		}

		fseek( $fp, $offset );
		if ( $offset > 0 ) {
			fgets( $fp ); // unvollstaendige erste Zeile ueberspringen
		}

		$lines = [];
		while ( ( $line = fgets( $fp ) ) !== false ) {
			$line = trim( $line );
			if ( $line !== '' ) {
				$lines[] = $line;
			}
		}
		fclose( $fp );

		$cutoff   = strtotime( "-{$hours} hours" );
		$filtered = [];

		foreach ( $lines as $line ) {
			if ( preg_match( '/^\[(\d{2}-\w{3}-\d{4}\s+\d{2}:\d{2}:\d{2})\s/', $line, $m ) ) {
				$ts = strtotime( $m[1] );
				if ( $ts && $ts < $cutoff ) {
					continue;
				}
			}

			if (
				stripos( $line, 'DGPTM' ) !== false ||
				stripos( $line, 'dgptm' ) !== false ||
				stripos( $line, 'Fatal error' ) !== false ||
				stripos( $line, 'PHP Warning' ) !== false ||
				stripos( $line, 'PHP Parse error' ) !== false ||
				stripos( $line, 'Cannot redeclare' ) !== false ||
				stripos( $line, 'Cannot declare class' ) !== false
			) {
				$filtered[] = $line;
			}
		}

		return [
			'available' => true,
			'size_mb'   => $size_mb,
			'entries'   => array_slice( $filtered, -$limit ),
		];
	}

	/**
	 * Modul-Status abrufen
	 */
	private function get_module_status() {
		$module_loader = dgptm_suite()->get_module_loader();
		$available = $module_loader->get_available_modules();
		$loaded    = $module_loader->get_loaded_modules();
		$settings  = get_option( 'dgptm_suite_settings', [] );
		$active    = $settings['active_modules'] ?? [];

		$active_count  = count( array_filter( $active ) );
		$loaded_count  = count( $loaded );
		$total         = count( $available );

		// Fehlgeschlagene Aktivierungen
		$safe_loader = DGPTM_Safe_Loader::get_instance();
		$failed      = $safe_loader->get_failed_activations();
		$failed_list = [];
		foreach ( $failed as $mid => $info ) {
			$failed_list[] = [
				'module'  => $mid,
				'error'   => $info['error']['message'] ?? $info['error'] ?? 'Unbekannt',
				'time'    => date( 'c', $info['timestamp'] ?? 0 ),
			];
		}

		return [
			'total'    => $total,
			'active'   => $active_count,
			'loaded'   => $loaded_count,
			'failed'   => $failed_list,
			'active_not_loaded' => $active_count - $loaded_count,
		];
	}

	/**
	 * System-Checks
	 */
	private function get_system_checks() {
		$checks = [];

		// PHP-Speicher
		$mem_limit = ini_get( 'memory_limit' );
		$mem_usage = memory_get_peak_usage( true );
		$checks['memory'] = [
			'limit'    => $mem_limit,
			'peak_mb'  => round( $mem_usage / 1048576, 1 ),
		];

		// Cron-Status
		$next_cleanup = wp_next_scheduled( 'dgptm_logs_cleanup' );
		$checks['cron'] = [
			'logs_cleanup' => $next_cleanup ? date( 'c', $next_cleanup ) : 'nicht geplant',
		];

		// debug.log Groesse
		$debug_log = WP_CONTENT_DIR . '/debug.log';
		if ( file_exists( $debug_log ) ) {
			$checks['debug_log_size_mb'] = round( filesize( $debug_log ) / 1048576, 2 );
		}

		// Zoho-Verbindung
		if ( function_exists( 'dgptm_zoho_auth' ) ) {
			$checks['zoho_connected'] = dgptm_zoho_auth()->is_connected();
		}

		return $checks;
	}

	/**
	 * Token fuer Anzeige in Admin abrufen
	 */
	public static function get_token() {
		return get_option( self::OPT_TOKEN, '' );
	}
}
