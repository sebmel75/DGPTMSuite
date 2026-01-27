<?php
/**
 * DGPTM Artikel-Einreichung - Database Installer
 * Creates required database tables for SharePoint versions and upload tokens
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Artikel_DB_Installer {

    /**
     * Database version
     */
    const DB_VERSION = '1.0.0';

    /**
     * Option name for tracking DB version
     */
    const DB_VERSION_OPTION = 'dgptm_artikel_db_version';

    /**
     * Install database tables
     */
    public static function install() {
        $installed_version = get_option(self::DB_VERSION_OPTION, '0');

        if (version_compare($installed_version, self::DB_VERSION, '<')) {
            self::create_tables();
            update_option(self::DB_VERSION_OPTION, self::DB_VERSION);
        }
    }

    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        // Table for upload tokens (one-time upload links)
        $tokens_table = $wpdb->prefix . 'dgptm_artikel_upload_tokens';
        $tokens_sql = "CREATE TABLE {$tokens_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            token VARCHAR(64) UNIQUE NOT NULL,
            artikel_id BIGINT UNSIGNED NOT NULL,
            token_type ENUM('gutachten', 'revision', 'autor') DEFAULT 'gutachten',
            reviewer_email VARCHAR(320) DEFAULT NULL,
            reviewer_name VARCHAR(255) DEFAULT NULL,
            created_by BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            used_at DATETIME DEFAULT NULL,
            is_one_time TINYINT(1) DEFAULT 1,
            description VARCHAR(255) DEFAULT NULL,
            INDEX idx_token (token),
            INDEX idx_artikel_id (artikel_id),
            INDEX idx_expires_at (expires_at)
        ) {$charset_collate};";

        // Table for SharePoint file versions
        $versions_table = $wpdb->prefix . 'dgptm_artikel_sp_versions';
        $versions_sql = "CREATE TABLE {$versions_table} (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            artikel_id BIGINT UNSIGNED NOT NULL,
            version_number INT UNSIGNED NOT NULL,
            version_type ENUM('einreichung', 'gutachten', 'revision', 'lektorat', 'gesetzt', 'final') NOT NULL,
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
            INDEX idx_artikel_id (artikel_id),
            INDEX idx_version_type (version_type)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($tokens_sql);
        dbDelta($versions_sql);
    }

    /**
     * Check if tables exist
     */
    public static function tables_exist() {
        global $wpdb;

        $tokens_table = $wpdb->prefix . 'dgptm_artikel_upload_tokens';
        $versions_table = $wpdb->prefix . 'dgptm_artikel_sp_versions';

        $tokens_exists = $wpdb->get_var("SHOW TABLES LIKE '{$tokens_table}'") === $tokens_table;
        $versions_exists = $wpdb->get_var("SHOW TABLES LIKE '{$versions_table}'") === $versions_table;

        return $tokens_exists && $versions_exists;
    }

    /**
     * Get table statistics
     */
    public static function get_stats() {
        global $wpdb;

        $tokens_table = $wpdb->prefix . 'dgptm_artikel_upload_tokens';
        $versions_table = $wpdb->prefix . 'dgptm_artikel_sp_versions';

        return array(
            'tokens' => array(
                'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tokens_table}"),
                'active' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tokens_table} WHERE expires_at > NOW() AND used_at IS NULL"),
                'used' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tokens_table} WHERE used_at IS NOT NULL"),
                'expired' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$tokens_table} WHERE expires_at <= NOW() AND used_at IS NULL"),
            ),
            'versions' => array(
                'total' => (int) $wpdb->get_var("SELECT COUNT(*) FROM {$versions_table}"),
                'total_size' => (int) $wpdb->get_var("SELECT SUM(file_size) FROM {$versions_table}"),
            ),
        );
    }

    /**
     * Uninstall - remove tables (use with caution)
     */
    public static function uninstall() {
        global $wpdb;

        $tokens_table = $wpdb->prefix . 'dgptm_artikel_upload_tokens';
        $versions_table = $wpdb->prefix . 'dgptm_artikel_sp_versions';

        $wpdb->query("DROP TABLE IF EXISTS {$tokens_table}");
        $wpdb->query("DROP TABLE IF EXISTS {$versions_table}");

        delete_option(self::DB_VERSION_OPTION);
    }
}
