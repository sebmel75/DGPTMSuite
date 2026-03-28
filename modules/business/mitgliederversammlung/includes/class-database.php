<?php
/**
 * Datenbank-Schema fuer Mitgliederversammlung.
 *
 * Kernprinzip: Anonyme Abstimmung durch Trennung von Stimmen und Quittungen.
 * - votes: Speichert WAS gewaehlt wurde (OHNE user_id)
 * - vote_receipts: Speichert WER gewaehlt hat (OHNE choice)
 * Dadurch ist selbst bei DB-Zugriff keine Zuordnung moeglich.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class DGPTM_MV_Database {

	const DB_VERSION     = '1.0.0';
	const DB_VERSION_KEY = 'dgptm_mv_db_version';

	/**
	 * Tabellen-Namen mit Prefix.
	 */
	public static function table( $name ) {
		global $wpdb;
		return $wpdb->prefix . 'dgptm_mv_' . $name;
	}

	/**
	 * Erstellt alle Tabellen bei Aktivierung.
	 */
	public function install() {
		global $wpdb;
		$charset = $wpdb->get_charset_collate();

		$tables = [];

		// 1. Versammlungen
		$tables[] = "CREATE TABLE " . self::table('assemblies') . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name VARCHAR(255) NOT NULL,
			description TEXT,
			assembly_date DATETIME NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'planned',
			location VARCHAR(255) DEFAULT '',
			is_hybrid TINYINT(1) NOT NULL DEFAULT 1,
			zoom_meeting_id VARCHAR(50) DEFAULT '',
			zoom_kind VARCHAR(20) DEFAULT 'meeting',
			logo_url VARCHAR(500) DEFAULT '',
			quorum_required TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			closed_at DATETIME DEFAULT NULL,
			created_by BIGINT(20) UNSIGNED DEFAULT 0,
			PRIMARY KEY (id),
			KEY status (status)
		) $charset;";

		// 2. Tagesordnungspunkte
		$tables[] = "CREATE TABLE " . self::table('agenda_items') . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			assembly_id BIGINT(20) UNSIGNED NOT NULL,
			sort_order INT NOT NULL DEFAULT 0,
			title VARCHAR(255) NOT NULL,
			description TEXT,
			item_type VARCHAR(30) NOT NULL DEFAULT 'discussion',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			PRIMARY KEY (id),
			KEY assembly_id (assembly_id)
		) $charset;";

		// 3. Abstimmungen (Polls)
		$tables[] = "CREATE TABLE " . self::table('polls') . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			assembly_id BIGINT(20) UNSIGNED NOT NULL,
			agenda_item_id BIGINT(20) UNSIGNED DEFAULT NULL,
			question VARCHAR(500) NOT NULL,
			description TEXT,
			poll_type VARCHAR(30) NOT NULL DEFAULT 'simple',
			choices TEXT NOT NULL,
			max_choices INT NOT NULL DEFAULT 1,
			required_majority VARCHAR(30) NOT NULL DEFAULT 'simple',
			status VARCHAR(20) NOT NULL DEFAULT 'prepared',
			results_released TINYINT(1) NOT NULL DEFAULT 0,
			chart_type VARCHAR(10) NOT NULL DEFAULT 'bar',
			is_secret TINYINT(1) NOT NULL DEFAULT 1,
			in_overall TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			started_at DATETIME DEFAULT NULL,
			stopped_at DATETIME DEFAULT NULL,
			PRIMARY KEY (id),
			KEY assembly_id (assembly_id),
			KEY status (status)
		) $charset;";

		// 4. Anonyme Stimmen - KEIN user_id!
		// Satzung §7 Abs. 7: frei, geheim, gleich, persoenlich, unmittelbar
		$tables[] = "CREATE TABLE " . self::table('votes') . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			poll_id BIGINT(20) UNSIGNED NOT NULL,
			choice_index INT NOT NULL,
			vote_time DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY poll_id (poll_id)
		) $charset;";

		// 5. Stimmquittungen - WER hat gestimmt (NICHT was)
		// Verhindert Doppelabstimmung bei gleichzeitiger Anonymitaet
		$tables[] = "CREATE TABLE " . self::table('vote_receipts') . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			poll_id BIGINT(20) UNSIGNED NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			voted_at DATETIME NOT NULL,
			vote_method VARCHAR(20) NOT NULL DEFAULT 'digital',
			entered_by BIGINT(20) UNSIGNED DEFAULT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY unique_vote (poll_id, user_id),
			KEY poll_id (poll_id),
			KEY user_id (user_id)
		) $charset;";

		// 6. Anwesenheit
		$tables[] = "CREATE TABLE " . self::table('attendance') . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			assembly_id BIGINT(20) UNSIGNED NOT NULL,
			user_id BIGINT(20) UNSIGNED DEFAULT 0,
			member_name VARCHAR(255) NOT NULL DEFAULT '',
			member_email VARCHAR(255) DEFAULT '',
			member_no VARCHAR(50) DEFAULT '',
			member_status VARCHAR(100) DEFAULT '',
			attendance_type VARCHAR(20) NOT NULL DEFAULT 'presence',
			checked_in_at DATETIME NOT NULL,
			checked_out_at DATETIME DEFAULT NULL,
			is_eligible_voter TINYINT(1) NOT NULL DEFAULT 0,
			scan_code VARCHAR(100) DEFAULT '',
			source VARCHAR(30) NOT NULL DEFAULT 'scanner',
			PRIMARY KEY (id),
			KEY assembly_id (assembly_id),
			KEY user_id (user_id),
			UNIQUE KEY unique_attendance (assembly_id, user_id, member_no)
		) $charset;";

		// 7. Zoom-Anwesenheit (Online-Sessions)
		$tables[] = "CREATE TABLE " . self::table('zoom_sessions') . " (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			assembly_id BIGINT(20) UNSIGNED NOT NULL,
			participant_name VARCHAR(255) DEFAULT '',
			participant_email VARCHAR(255) DEFAULT '',
			zoom_user_id VARCHAR(100) DEFAULT '',
			join_time DATETIME DEFAULT NULL,
			leave_time DATETIME DEFAULT NULL,
			duration_seconds INT DEFAULT 0,
			PRIMARY KEY (id),
			KEY assembly_id (assembly_id)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		foreach ( $tables as $sql ) {
			dbDelta( $sql );
		}

		update_option( self::DB_VERSION_KEY, self::DB_VERSION );
	}

	/**
	 * Prueft ob DB-Upgrade noetig ist.
	 */
	public function maybe_upgrade() {
		$installed = get_option( self::DB_VERSION_KEY, '' );
		if ( $installed !== self::DB_VERSION ) {
			$this->install();
		}
	}

	/**
	 * Gibt Statistiken zur aktuellen Versammlung zurueck.
	 */
	public static function get_assembly_stats( $assembly_id ) {
		global $wpdb;
		$att = self::table( 'attendance' );

		$total = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $att WHERE assembly_id = %d", $assembly_id
		) );

		$eligible = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $att WHERE assembly_id = %d AND is_eligible_voter = 1", $assembly_id
		) );

		$presence = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $att WHERE assembly_id = %d AND attendance_type = 'presence'", $assembly_id
		) );

		$online = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM $att WHERE assembly_id = %d AND attendance_type = 'online'", $assembly_id
		) );

		return [
			'total'    => $total,
			'eligible' => $eligible,
			'presence' => $presence,
			'online'   => $online,
		];
	}
}
