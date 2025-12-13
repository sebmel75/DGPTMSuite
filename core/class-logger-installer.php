<?php
/**
 * DGPTM Suite Logger Installer
 * Erstellt und verwaltet die Datenbank-Tabelle für strukturiertes Logging
 *
 * @version 1.0.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Logger_Installer {

    const DB_VERSION = '1.0.0';
    const DB_VERSION_OPTION = 'dgptm_logs_db_version';
    const TABLE_NAME = 'dgptm_logs';

    /**
     * Installiert die Datenbank-Tabelle
     */
    public static function install() {
        global $wpdb;

        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE $table_name (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            level VARCHAR(20) NOT NULL DEFAULT 'info',
            module_id VARCHAR(64) DEFAULT NULL,
            message TEXT NOT NULL,
            context LONGTEXT DEFAULT NULL,
            user_id BIGINT UNSIGNED DEFAULT NULL,
            request_uri VARCHAR(512) DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT NULL,
            PRIMARY KEY (id),
            INDEX idx_timestamp (timestamp),
            INDEX idx_level (level),
            INDEX idx_module_id (module_id),
            INDEX idx_module_level (module_id, level),
            INDEX idx_timestamp_level (timestamp, level)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        update_option(self::DB_VERSION_OPTION, self::DB_VERSION);

        // Initial log entry
        self::log_installation();
    }

    /**
     * Deinstalliert die Datenbank-Tabelle
     */
    public static function uninstall() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        delete_option(self::DB_VERSION_OPTION);
    }

    /**
     * Prüft ob ein Upgrade notwendig ist
     */
    public static function needs_upgrade() {
        $installed_version = get_option(self::DB_VERSION_OPTION, '0.0.0');
        return version_compare($installed_version, self::DB_VERSION, '<');
    }

    /**
     * Führt Upgrade durch falls notwendig
     */
    public static function maybe_upgrade() {
        if (self::needs_upgrade()) {
            self::install();
        }
    }

    /**
     * Prüft ob die Tabelle existiert
     */
    public static function table_exists() {
        global $wpdb;
        $table_name = $wpdb->prefix . self::TABLE_NAME;
        return $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    }

    /**
     * Holt den Tabellennamen mit Präfix
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Loggt die Installation
     */
    private static function log_installation() {
        global $wpdb;

        if (!self::table_exists()) {
            return;
        }

        $table_name = self::get_table_name();

        $wpdb->insert(
            $table_name,
            [
                'level' => 'info',
                'module_id' => 'dgptm-suite',
                'message' => 'DGPTM Suite Logging-System installiert (DB Version: ' . self::DB_VERSION . ')',
                'user_id' => get_current_user_id(),
                'request_uri' => isset($_SERVER['REQUEST_URI']) ? sanitize_text_field($_SERVER['REQUEST_URI']) : '',
                'ip_address' => self::get_client_ip()
            ],
            ['%s', '%s', '%s', '%d', '%s', '%s']
        );
    }

    /**
     * Bereinigt alte Log-Einträge
     *
     * @param int $max_age_hours Maximales Alter in Stunden (Standard: aus Settings)
     * @param bool $preserve_critical Critical/Error Einträge behalten
     * @return int Anzahl gelöschter Einträge
     */
    public static function cleanup_old_logs($max_age_hours = null, $preserve_critical = true) {
        global $wpdb;

        if (!self::table_exists()) {
            return 0;
        }

        // Standard aus Settings
        if ($max_age_hours === null) {
            $settings = get_option('dgptm_suite_settings', []);
            $max_age_hours = isset($settings['log_cleanup_age']) ? absint($settings['log_cleanup_age']) : 24;
        }

        $table_name = self::get_table_name();
        $cutoff_date = date('Y-m-d H:i:s', strtotime("-{$max_age_hours} hours"));

        if ($preserve_critical) {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name WHERE timestamp < %s AND level NOT IN ('error', 'critical')",
                $cutoff_date
            ));
        } else {
            $deleted = $wpdb->query($wpdb->prepare(
                "DELETE FROM $table_name WHERE timestamp < %s",
                $cutoff_date
            ));
        }

        return $deleted !== false ? $deleted : 0;
    }

    /**
     * Bereinigt basierend auf maximaler Anzahl
     *
     * @param int $max_entries Maximale Anzahl Einträge
     * @return int Anzahl gelöschter Einträge
     */
    public static function cleanup_by_count($max_entries = 100000) {
        global $wpdb;

        if (!self::table_exists()) {
            return 0;
        }

        $table_name = self::get_table_name();
        $current_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        if ($current_count <= $max_entries) {
            return 0;
        }

        $to_delete = $current_count - $max_entries;

        // Lösche die ältesten Einträge (außer critical/error)
        $deleted = $wpdb->query($wpdb->prepare(
            "DELETE FROM $table_name
             WHERE id IN (
                SELECT id FROM (
                    SELECT id FROM $table_name
                    WHERE level NOT IN ('error', 'critical')
                    ORDER BY timestamp ASC
                    LIMIT %d
                ) AS tmp
             )",
            $to_delete
        ));

        return $deleted !== false ? $deleted : 0;
    }

    /**
     * Holt Statistiken über die Logs
     */
    public static function get_stats() {
        global $wpdb;

        if (!self::table_exists()) {
            return [
                'total' => 0,
                'by_level' => [],
                'by_module' => [],
                'oldest' => null,
                'newest' => null,
                'table_size' => '0 B'
            ];
        }

        $table_name = self::get_table_name();

        // Gesamtanzahl
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

        // Nach Level gruppiert
        $by_level = $wpdb->get_results(
            "SELECT level, COUNT(*) as count FROM $table_name GROUP BY level ORDER BY count DESC",
            ARRAY_A
        );

        // Nach Modul gruppiert (Top 10)
        $by_module = $wpdb->get_results(
            "SELECT module_id, COUNT(*) as count FROM $table_name WHERE module_id IS NOT NULL GROUP BY module_id ORDER BY count DESC LIMIT 10",
            ARRAY_A
        );

        // Ältester und neuester Eintrag
        $oldest = $wpdb->get_var("SELECT MIN(timestamp) FROM $table_name");
        $newest = $wpdb->get_var("SELECT MAX(timestamp) FROM $table_name");

        // Tabellengröße
        $table_size = $wpdb->get_row($wpdb->prepare(
            "SELECT
                ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
            FROM information_schema.TABLES
            WHERE table_schema = %s AND table_name = %s",
            DB_NAME,
            $table_name
        ));

        return [
            'total' => (int) $total,
            'by_level' => $by_level ?: [],
            'by_module' => $by_module ?: [],
            'oldest' => $oldest,
            'newest' => $newest,
            'table_size' => $table_size ? $table_size->size_mb . ' MB' : '0 B'
        ];
    }

    /**
     * Holt die Client-IP-Adresse
     */
    private static function get_client_ip() {
        $ip_keys = [
            'HTTP_CF_CONNECTING_IP',  // Cloudflare
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                // Bei X-Forwarded-For kann es mehrere IPs geben
                if (strpos($ip, ',') !== false) {
                    $ip = trim(explode(',', $ip)[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
