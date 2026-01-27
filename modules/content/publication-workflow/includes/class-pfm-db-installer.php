<?php
/**
 * Publication Frontend Manager - Database Installer
 * Creates required database tables for SharePoint versioning and upload tokens
 */

if (!defined('ABSPATH')) {
    exit;
}

class PFM_DB_Installer {

    /**
     * Database version for tracking schema changes
     */
    const DB_VERSION = '1.0.0';

    /**
     * Option key for tracking installed DB version
     */
    const DB_VERSION_OPTION = 'pfm_db_version';

    /**
     * Install database tables
     */
    public static function install() {
        $installed_version = get_option(self::DB_VERSION_OPTION, '0.0.0');

        if (version_compare($installed_version, self::DB_VERSION, '<')) {
            self::create_tables();
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        }
    }

    /**
     * Create all required tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Create upload tokens table
        self::create_upload_tokens_table($charset_collate);

        // Create SharePoint versions table
        self::create_sharepoint_versions_table($charset_collate);
    }

    /**
     * Create upload tokens table
     *
     * @param string $charset_collate
     */
    private static function create_upload_tokens_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'pfm_upload_tokens';

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            token VARCHAR(64) NOT NULL,
            publication_id BIGINT UNSIGNED NOT NULL,
            token_type VARCHAR(20) DEFAULT 'gutachten',
            reviewer_email VARCHAR(320) DEFAULT NULL,
            reviewer_name VARCHAR(255) DEFAULT NULL,
            created_by BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            is_one_time TINYINT(1) DEFAULT 1,
            description VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY idx_token (token),
            KEY idx_publication_id (publication_id),
            KEY idx_expires_at (expires_at),
            KEY idx_token_type (token_type)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Create SharePoint versions table
     *
     * @param string $charset_collate
     */
    private static function create_sharepoint_versions_table($charset_collate) {
        global $wpdb;

        $table_name = $wpdb->prefix . 'pfm_sharepoint_versions';

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            publication_id BIGINT UNSIGNED NOT NULL,
            version_number INT UNSIGNED NOT NULL,
            version_type VARCHAR(30) NOT NULL,
            original_filename VARCHAR(255) NOT NULL,
            sharepoint_path VARCHAR(1024) NOT NULL,
            sharepoint_item_id VARCHAR(128) DEFAULT NULL,
            sharepoint_web_url VARCHAR(1024) DEFAULT NULL,
            file_size BIGINT UNSIGNED DEFAULT NULL,
            uploaded_by BIGINT UNSIGNED DEFAULT NULL,
            uploaded_by_name VARCHAR(255) DEFAULT NULL,
            uploaded_via_token BIGINT UNSIGNED DEFAULT NULL,
            uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            notes TEXT DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_publication_id (publication_id),
            KEY idx_version_type (version_type),
            KEY idx_uploaded_at (uploaded_at)
        ) {$charset_collate};";

        dbDelta($sql);
    }

    /**
     * Check if tables exist
     *
     * @return array Status of each table
     */
    public static function check_tables() {
        global $wpdb;

        $tables = array(
            'pfm_upload_tokens' => $wpdb->prefix . 'pfm_upload_tokens',
            'pfm_sharepoint_versions' => $wpdb->prefix . 'pfm_sharepoint_versions',
        );

        $status = array();

        foreach ($tables as $key => $table_name) {
            $exists = $wpdb->get_var(
                $wpdb->prepare("SHOW TABLES LIKE %s", $table_name)
            );
            $status[$key] = !empty($exists);
        }

        return $status;
    }

    /**
     * Get table statistics
     *
     * @return array
     */
    public static function get_table_stats() {
        global $wpdb;

        $stats = array();

        // Tokens table
        $tokens_table = $wpdb->prefix . 'pfm_upload_tokens';
        $stats['tokens'] = array(
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tokens_table}"),
            'active' => (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tokens_table} WHERE expires_at > %s AND used_at IS NULL",
                    current_time('mysql')
                )
            ),
            'used' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tokens_table} WHERE used_at IS NOT NULL"),
            'expired' => (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$tokens_table} WHERE expires_at <= %s AND used_at IS NULL",
                    current_time('mysql')
                )
            ),
        );

        // SharePoint versions table
        $versions_table = $wpdb->prefix . 'pfm_sharepoint_versions';
        $stats['versions'] = array(
            'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$versions_table}"),
            'publications' => (int) $wpdb->get_var("SELECT COUNT(DISTINCT publication_id) FROM {$versions_table}"),
            'total_size' => (int) $wpdb->get_var("SELECT SUM(file_size) FROM {$versions_table}"),
        );

        return $stats;
    }

    /**
     * Uninstall - remove tables
     * Only call this on complete plugin removal
     */
    public static function uninstall() {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}pfm_upload_tokens");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}pfm_sharepoint_versions");

        delete_option(self::DB_VERSION_OPTION);
    }
}
