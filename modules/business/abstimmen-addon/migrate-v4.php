<?php
/**
 * Migration Script für Abstimmen-Addon v4.0.0
 *
 * Migriert von älteren Versionen (v3.7.0, v2.0, v1.1.0) zu v4.0.0
 *
 * @package DGPTM_Abstimmen
 * @version 4.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Migration Manager Class
 */
class DGPTM_Abstimmen_Migration {

	/**
	 * Current plugin version
	 *
	 * @var string
	 */
	const TARGET_VERSION = '4.0.0';

	/**
	 * Migration log
	 *
	 * @var array
	 */
	private $log = array();

	/**
	 * Run migration
	 */
	public function run() {
		$this->log( 'info', 'Starting migration to v' . self::TARGET_VERSION );

		// Get current version
		$current_version = get_option( 'dgptm_abstimmen_version', '0.0.0' );
		$this->log( 'info', 'Current version: ' . $current_version );

		// Check if migration needed
		if ( version_compare( $current_version, self::TARGET_VERSION, '>=' ) ) {
			$this->log( 'info', 'No migration needed. Already at v' . self::TARGET_VERSION );
			return array(
				'success' => true,
				'message' => 'Keine Migration erforderlich.',
				'log'     => $this->log,
			);
		}

		// Backup current settings
		$this->backup_settings();

		// Run migrations in order
		$migrations = array(
			'migrate_from_v1',
			'migrate_from_v2',
			'migrate_from_v3',
			'migrate_database_structure',
			'migrate_settings',
			'migrate_user_meta',
			'migrate_zoom_settings',
			'migrate_attendance_data',
			'cleanup_old_options',
		);

		foreach ( $migrations as $migration ) {
			if ( method_exists( $this, $migration ) ) {
				$this->log( 'info', "Running migration: {$migration}" );
				$result = $this->$migration( $current_version );

				if ( is_wp_error( $result ) ) {
					$this->log( 'error', $result->get_error_message() );
					return array(
						'success' => false,
						'message' => $result->get_error_message(),
						'log'     => $this->log,
					);
				}

				$this->log( 'success', "Completed: {$migration}" );
			}
		}

		// Update version number
		update_option( 'dgptm_abstimmen_version', self::TARGET_VERSION, false );
		$this->log( 'success', 'Updated version to ' . self::TARGET_VERSION );

		return array(
			'success' => true,
			'message' => 'Migration erfolgreich abgeschlossen.',
			'log'     => $this->log,
		);
	}

	/**
	 * Backup current settings
	 */
	private function backup_settings() {
		$backup = array(
			'timestamp' => current_time( 'mysql' ),
			'version'   => get_option( 'dgptm_abstimmen_version', '0.0.0' ),
			'settings'  => array(
				'zoom'        => get_option( 'dgptm_zoom_settings', array() ),
				'attendance'  => get_option( 'dgptm_zoom_attendance', array() ),
				'beamer'      => get_option( 'dgptm_beamer_state', array() ),
				'vote_log'    => get_option( 'dgptm_vote_zoom_log', array() ),
			),
		);

		update_option( 'dgptm_abstimmen_backup_' . time(), $backup, false );
		$this->log( 'info', 'Created settings backup' );
	}

	/**
	 * Migrate from v1.x (abstimmenadon.php - Presence Scanner)
	 */
	private function migrate_from_v1( $current_version ) {
		if ( version_compare( $current_version, '2.0', '>=' ) ) {
			return true; // Already migrated
		}

		$this->log( 'info', 'Migrating from v1.x (Presence Scanner)' );

		// v1 used manual presence tracking
		// Ensure dgptm_zoom_attendance has correct structure
		$attendance = get_option( 'dgptm_zoom_attendance', array() );

		if ( ! empty( $attendance ) ) {
			foreach ( $attendance as $key => &$meeting ) {
				if ( isset( $meeting['participants'] ) ) {
					foreach ( $meeting['participants'] as $pk => &$participant ) {
						// Add missing fields with defaults
						if ( ! isset( $participant['manual'] ) ) {
							$participant['manual'] = 0;
						}
						if ( ! isset( $participant['join_first'] ) ) {
							$participant['join_first'] = 0;
						}
						if ( ! isset( $participant['leave_last'] ) ) {
							$participant['leave_last'] = 0;
						}
						if ( ! isset( $participant['total'] ) ) {
							$participant['total'] = 0;
						}
					}
				}
			}

			update_option( 'dgptm_zoom_attendance', $attendance, false );
			$this->log( 'success', 'Updated attendance structure from v1' );
		}

		return true;
	}

	/**
	 * Migrate from v2.x (onlineabstimmung.php - Zoom Integration)
	 */
	private function migrate_from_v2( $current_version ) {
		if ( version_compare( $current_version, '3.0', '>=' ) ) {
			return true; // Already migrated
		}

		$this->log( 'info', 'Migrating from v2.x (Zoom Integration)' );

		// v2 introduced Zoom settings
		$zoom_settings = get_option( 'dgptm_zoom_settings', array() );

		// Ensure all required fields exist
		$defaults = array(
			'account_id'     => '',
			'client_id'      => '',
			'client_secret'  => '',
			'zoom_id'        => '',
			'zoom_type'      => 'meeting',
			'webhook_secret' => '',
		);

		$zoom_settings = wp_parse_args( $zoom_settings, $defaults );
		update_option( 'dgptm_zoom_settings', $zoom_settings, false );

		$this->log( 'success', 'Updated Zoom settings structure from v2' );

		return true;
	}

	/**
	 * Migrate from v3.x (dgptm-abstimmungstool.php - Voting System)
	 */
	private function migrate_from_v3( $current_version ) {
		if ( version_compare( $current_version, '4.0', '>=' ) ) {
			return true; // Already at v4+
		}

		$this->log( 'info', 'Migrating from v3.x (Voting System)' );

		global $wpdb;

		// v3 had voting tables - ensure they exist
		$tables = array(
			$wpdb->prefix . 'dgptm_abstimmung_polls',
			$wpdb->prefix . 'dgptm_abstimmung_poll_questions',
			$wpdb->prefix . 'dgptm_abstimmung_votes',
			$wpdb->prefix . 'dgptm_abstimmung_participants',
		);

		foreach ( $tables as $table ) {
			if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table}'" ) !== $table ) {
				$this->log( 'warning', "Table {$table} does not exist - will be created on activation" );
			}
		}

		return true;
	}

	/**
	 * Migrate database structure
	 */
	private function migrate_database_structure() {
		$this->log( 'info', 'Checking database structure' );

		global $wpdb;
		$charset_collate = $wpdb->get_charset_collate();

		// Polls table
		$table_polls = $wpdb->prefix . 'dgptm_abstimmung_polls';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_polls}'" ) !== $table_polls ) {
			$sql = "CREATE TABLE {$table_polls} (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				name varchar(255) NOT NULL,
				status varchar(20) DEFAULT 'active',
				requires_signup tinyint(1) DEFAULT 0,
				logo_url varchar(500) DEFAULT '',
				created datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY status (status)
			) {$charset_collate};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
			$this->log( 'success', 'Created polls table' );
		}

		// Questions table
		$table_questions = $wpdb->prefix . 'dgptm_abstimmung_poll_questions';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_questions}'" ) !== $table_questions ) {
			$sql = "CREATE TABLE {$table_questions} (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				poll_id bigint(20) UNSIGNED NOT NULL,
				question text NOT NULL,
				choices text NOT NULL,
				max_votes int DEFAULT 1,
				status varchar(20) DEFAULT 'active',
				chart_type varchar(20) DEFAULT 'bar',
				released tinyint(1) DEFAULT 0,
				created datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY poll_id (poll_id),
				KEY status (status),
				KEY released (released)
			) {$charset_collate};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
			$this->log( 'success', 'Created questions table' );
		}

		// Votes table
		$table_votes = $wpdb->prefix . 'dgptm_abstimmung_votes';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_votes}'" ) !== $table_votes ) {
			$sql = "CREATE TABLE {$table_votes} (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				question_id bigint(20) UNSIGNED NOT NULL,
				choice int NOT NULL,
				user_id bigint(20) UNSIGNED DEFAULT 0,
				cookie_id varchar(100) DEFAULT '',
				voted_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY question_id (question_id),
				KEY user_id (user_id),
				KEY cookie_id (cookie_id)
			) {$charset_collate};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
			$this->log( 'success', 'Created votes table' );
		}

		// Participants table
		$table_participants = $wpdb->prefix . 'dgptm_abstimmung_participants';
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_participants}'" ) !== $table_participants ) {
			$sql = "CREATE TABLE {$table_participants} (
				id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				user_id bigint(20) UNSIGNED NOT NULL,
				poll_id bigint(20) UNSIGNED NOT NULL,
				cookie_id varchar(100) DEFAULT '',
				joined_at datetime DEFAULT CURRENT_TIMESTAMP,
				PRIMARY KEY (id),
				KEY user_id (user_id),
				KEY poll_id (poll_id),
				KEY cookie_id (cookie_id)
			) {$charset_collate};";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
			$this->log( 'success', 'Created participants table' );
		}

		return true;
	}

	/**
	 * Migrate settings
	 */
	private function migrate_settings() {
		$this->log( 'info', 'Migrating settings' );

		// Ensure all options have proper structure
		$options = array(
			'dgptm_zoom_settings'    => array(),
			'dgptm_zoom_attendance'  => array(),
			'dgptm_beamer_state'     => array(),
			'dgptm_vote_zoom_log'    => array(),
		);

		foreach ( $options as $option => $default ) {
			$value = get_option( $option, $default );
			if ( ! is_array( $value ) ) {
				update_option( $option, $default, false );
				$this->log( 'warning', "Reset {$option} to default array" );
			}
		}

		return true;
	}

	/**
	 * Migrate user meta
	 */
	private function migrate_user_meta() {
		$this->log( 'info', 'Migrating user meta' );

		// Get all users
		$users = get_users( array( 'fields' => 'ID' ) );

		foreach ( $users as $user_id ) {
			// Ensure dgptm_vote_status exists
			$status = get_user_meta( $user_id, 'dgptm_vote_status', true );
			if ( empty( $status ) ) {
				update_user_meta( $user_id, 'dgptm_vote_status', 'off' );
			}

			// Ensure dgptm_vote_code exists
			$code = get_user_meta( $user_id, 'dgptm_vote_code', true );
			if ( empty( $code ) ) {
				$code = sprintf( '%06d', wp_rand( 0, 999999 ) );
				update_user_meta( $user_id, 'dgptm_vote_code', $code );
			}

			// Ensure mitgliederversammlung flag exists
			$mv = get_user_meta( $user_id, 'mitgliederversammlung', true );
			if ( $mv === '' ) {
				update_user_meta( $user_id, 'mitgliederversammlung', 'false' );
			}
		}

		$this->log( 'success', 'Updated user meta for ' . count( $users ) . ' users' );

		return true;
	}

	/**
	 * Migrate Zoom settings
	 */
	private function migrate_zoom_settings() {
		$this->log( 'info', 'Migrating Zoom settings' );

		$settings = get_option( 'dgptm_zoom_settings', array() );

		// Ensure all fields exist
		$defaults = array(
			'account_id'     => '',
			'client_id'      => '',
			'client_secret'  => '',
			'zoom_id'        => '',
			'zoom_type'      => 'meeting',
			'webhook_secret' => '',
		);

		$settings = wp_parse_args( $settings, $defaults );

		// Validate zoom_type
		if ( ! in_array( $settings['zoom_type'], array( 'meeting', 'webinar' ), true ) ) {
			$settings['zoom_type'] = 'meeting';
		}

		update_option( 'dgptm_zoom_settings', $settings, false );

		return true;
	}

	/**
	 * Migrate attendance data
	 */
	private function migrate_attendance_data() {
		$this->log( 'info', 'Migrating attendance data' );

		$attendance = get_option( 'dgptm_zoom_attendance', array() );

		if ( empty( $attendance ) ) {
			return true;
		}

		$updated = false;

		foreach ( $attendance as $key => &$meeting ) {
			if ( ! isset( $meeting['participants'] ) || ! is_array( $meeting['participants'] ) ) {
				continue;
			}

			foreach ( $meeting['participants'] as $pk => &$participant ) {
				// Ensure required fields exist
				$required_fields = array(
					'name'              => '',
					'status'            => 'Teilnehmer',
					'mitgliedsart'      => '',
					'mitgliedsnummer'   => '',
					'join_first'        => 0,
					'leave_last'        => 0,
					'total'             => 0,
					'manual'            => 0,
				);

				foreach ( $required_fields as $field => $default ) {
					if ( ! isset( $participant[ $field ] ) ) {
						$participant[ $field ] = $default;
						$updated = true;
					}
				}
			}
		}

		if ( $updated ) {
			update_option( 'dgptm_zoom_attendance', $attendance, false );
			$this->log( 'success', 'Updated attendance data structure' );
		}

		return true;
	}

	/**
	 * Cleanup old options
	 */
	private function cleanup_old_options() {
		$this->log( 'info', 'Cleaning up old options' );

		// List of old/deprecated options to remove
		$old_options = array(
			'dgptm_abstimmung_old_version',
			'dgptm_vote_legacy_settings',
			'dgptm_zoom_legacy_cache',
		);

		foreach ( $old_options as $option ) {
			if ( get_option( $option ) !== false ) {
				delete_option( $option );
				$this->log( 'success', "Removed old option: {$option}" );
			}
		}

		return true;
	}

	/**
	 * Add log entry
	 *
	 * @param string $level Log level (info, success, warning, error)
	 * @param string $message Log message
	 */
	private function log( $level, $message ) {
		$this->log[] = array(
			'timestamp' => current_time( 'mysql' ),
			'level'     => $level,
			'message'   => $message,
		);

		// Also log to WordPress error log
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( "DGPTM Abstimmen Migration [{$level}]: {$message}" );
		}
	}
}

/**
 * Run migration (can be called from admin or CLI)
 */
function dgptm_abstimmen_run_migration() {
	$migration = new DGPTM_Abstimmen_Migration();
	return $migration->run();
}

/**
 * AJAX handler for migration
 */
function dgptm_abstimmen_ajax_migrate() {
	check_ajax_referer( 'dgptm_admin_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Keine Berechtigung' ) );
	}

	$result = dgptm_abstimmen_run_migration();

	if ( $result['success'] ) {
		wp_send_json_success( $result );
	} else {
		wp_send_json_error( $result );
	}
}
add_action( 'wp_ajax_dgptm_abstimmen_migrate', 'dgptm_abstimmen_ajax_migrate' );

/**
 * AJAX handler for dismissing migration notice
 */
function dgptm_abstimmen_ajax_dismiss_migration() {
	check_ajax_referer( 'dgptm_admin_nonce', 'nonce' );

	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => 'Keine Berechtigung' ) );
	}

	update_user_meta( get_current_user_id(), 'dgptm_abstimmen_migration_dismissed', true );

	wp_send_json_success( array( 'message' => 'Migration-Hinweis ausgeblendet' ) );
}
add_action( 'wp_ajax_dgptm_abstimmen_dismiss_migration', 'dgptm_abstimmen_ajax_dismiss_migration' );
