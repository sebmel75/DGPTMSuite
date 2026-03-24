<?php
if (!defined('ABSPATH')) exit;

class DGPTM_FB_Access_Logger {

    const TABLE_SUFFIX = 'dgptm_fb_access_log';

    public static function get_table_name(): string {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_SUFFIX;
    }

    public static function ensure_table(): void {
        global $wpdb;
        $table = self::get_table_name();
        $charset = $wpdb->get_charset_collate();

        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") === $table) {
            return;
        }

        $sql = "CREATE TABLE $table (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            report_type varchar(50) NOT NULL,
            report_year int(4) NOT NULL,
            access_result varchar(20) NOT NULL DEFAULT 'granted',
            ip_address varchar(45) DEFAULT NULL,
            accessed_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_user (user_id),
            KEY idx_report (report_type, report_year),
            KEY idx_date (accessed_at)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public static function log(int $user_id, string $report, int $year, string $result = 'granted'): void {
        global $wpdb;
        self::ensure_table();

        $wpdb->insert(self::get_table_name(), [
            'user_id'       => $user_id,
            'report_type'   => sanitize_key($report),
            'report_year'   => $year,
            'access_result' => $result,
            'ip_address'    => self::get_ip(),
            'accessed_at'   => current_time('mysql'),
        ], ['%d', '%s', '%d', '%s', '%s', '%s']);
    }

    public static function get_recent(int $limit = 50): array {
        global $wpdb;
        self::ensure_table();
        $table = self::get_table_name();

        return $wpdb->get_results($wpdb->prepare(
            "SELECT l.*, u.display_name
             FROM $table l
             LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID
             ORDER BY l.accessed_at DESC
             LIMIT %d",
            $limit
        ), ARRAY_A);
    }

    private static function get_ip(): string {
        $headers = ['HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR'];
        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $ip = explode(',', $_SERVER[$h])[0];
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        return '0.0.0.0';
    }
}
