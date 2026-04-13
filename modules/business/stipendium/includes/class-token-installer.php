<?php
/**
 * DGPTM Stipendium — Datenbank-Installer fuer Token-Tabelle.
 *
 * Erstellt wp_dgptm_stipendium_tokens bei Modul-Aktivierung.
 * Nutzt dbDelta() fuer idempotente Schema-Updates.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Token_Installer {

    const DB_VERSION_KEY = 'dgptm_stipendium_token_db_version';
    const DB_VERSION     = '1.0';

    /**
     * Tabelle erstellen oder aktualisieren.
     *
     * Aufrufen bei Modul-Aktivierung und bei Versionsdifferenz.
     */
    public static function install() {
        $installed_version = get_option(self::DB_VERSION_KEY, '0');
        if (version_compare($installed_version, self::DB_VERSION, '>=')) {
            return;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'dgptm_stipendium_tokens';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table_name} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            token VARCHAR(64) NOT NULL,
            stipendium_id VARCHAR(50) NOT NULL,
            gutachter_name VARCHAR(255) NOT NULL,
            gutachter_email VARCHAR(255) NOT NULL,
            bewertung_status VARCHAR(20) NOT NULL DEFAULT 'ausstehend',
            bewertung_data LONGTEXT,
            bewertung_crm_id VARCHAR(50) DEFAULT NULL,
            created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            completed_at DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY token (token),
            KEY stipendium_id (stipendium_id),
            KEY bewertung_status (bewertung_status),
            KEY expires_at (expires_at)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option(self::DB_VERSION_KEY, self::DB_VERSION);
    }

    /**
     * Tabelle loeschen (bei Deinstallation).
     */
    public static function uninstall() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'dgptm_stipendium_tokens';
        $wpdb->query("DROP TABLE IF EXISTS {$table_name}");
        delete_option(self::DB_VERSION_KEY);
    }

    /**
     * Tabellennamen zurueckgeben.
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'dgptm_stipendium_tokens';
    }
}
