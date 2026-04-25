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
    const DB_VERSION     = '1.2';

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
        $charset_collate = $wpdb->get_charset_collate();
        $token_table  = $wpdb->prefix . 'dgptm_stipendium_tokens';
        $manual_table = $wpdb->prefix . 'dgptm_stipendium_manual';

        $sql_tokens = "CREATE TABLE {$token_table} (
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

        $sql_manual = "CREATE TABLE {$manual_table} (
            id VARCHAR(20) NOT NULL,
            runde VARCHAR(100) NOT NULL,
            stipendientyp VARCHAR(100) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'Geprueft',
            bewerber_name VARCHAR(255) NOT NULL,
            bewerber_email VARCHAR(255) DEFAULT '',
            bewerber_orcid VARCHAR(20) DEFAULT '',
            bewerber_institution VARCHAR(255) DEFAULT '',
            projekt_titel TEXT,
            projekt_zusammenfassung LONGTEXT,
            projekt_methodik LONGTEXT,
            dokument_urls LONGTEXT,
            eingangsdatum DATE DEFAULT NULL,
            freigabedatum DATE DEFAULT NULL,
            vergeben TINYINT(1) NOT NULL DEFAULT 0,
            vergabedatum DATE DEFAULT NULL,
            bemerkung TEXT,
            created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY runde (runde),
            KEY stipendientyp (stipendientyp),
            KEY status (status)
        ) {$charset_collate};";

        $gutachter_table = $wpdb->prefix . 'dgptm_stipendium_gutachter';
        $sql_gutachter = "CREATE TABLE {$gutachter_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            fachgebiet VARCHAR(255) DEFAULT '',
            mitglied TINYINT(1) NOT NULL DEFAULT 0,
            notizen TEXT,
            aktiv TINYINT(1) NOT NULL DEFAULT 1,
            created_by BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY email (email),
            KEY aktiv (aktiv)
        ) {$charset_collate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql_tokens);
        dbDelta($sql_manual);
        dbDelta($sql_gutachter);

        update_option(self::DB_VERSION_KEY, self::DB_VERSION);
    }

    /**
     * Tabellen loeschen (bei Deinstallation).
     */
    public static function uninstall() {
        global $wpdb;
        $token_table     = $wpdb->prefix . 'dgptm_stipendium_tokens';
        $manual_table    = $wpdb->prefix . 'dgptm_stipendium_manual';
        $gutachter_table = $wpdb->prefix . 'dgptm_stipendium_gutachter';
        $wpdb->query("DROP TABLE IF EXISTS {$token_table}");
        $wpdb->query("DROP TABLE IF EXISTS {$manual_table}");
        $wpdb->query("DROP TABLE IF EXISTS {$gutachter_table}");
        delete_option(self::DB_VERSION_KEY);
    }

    /**
     * Tabellenname Token-Tabelle.
     */
    public static function table_name() {
        global $wpdb;
        return $wpdb->prefix . 'dgptm_stipendium_tokens';
    }

    /**
     * Tabellenname manuelle Bewerbungen.
     */
    public static function manual_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'dgptm_stipendium_manual';
    }

    /**
     * Tabellenname Gutachter-Stammdaten.
     */
    public static function gutachter_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'dgptm_stipendium_gutachter';
    }
}
