<?php
/**
 * Token-Installer fuer Phase 2.
 *
 * Legt die Tabelle wp_dgptm_workshop_tokens an. Pattern abgeleitet von
 * stipendium/class-token-installer.php.
 *
 * Genutzt fuer:
 *  - persoenliche Links fuer Nicht-Mitglieder (scope='booking')
 *    → Ticket-Anzeige + Self-Service-Storno (Phase 6)
 *  - persoenliche Links fuer externe Designer:innen (scope='layout')
 *    → Layout-Editor fuer Bescheinigungen (Phase 4)
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Token_Installer {

    const DB_VERSION    = '1';
    const OPT_DB_VERSION = 'dgptm_wsb_tokens_db_version';

    public static function maybe_install() {
        if (get_option(self::OPT_DB_VERSION) === self::DB_VERSION) {
            return;
        }
        self::install();
        update_option(self::OPT_DB_VERSION, self::DB_VERSION, false);
    }

    private static function install() {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();

        dbDelta("CREATE TABLE {$wpdb->prefix}dgptm_workshop_tokens (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            token VARCHAR(64) NOT NULL,
            scope ENUM('booking','layout') NOT NULL,
            veranstal_x_contact_id VARCHAR(40) NULL,
            event_id VARCHAR(40) NULL,
            email VARCHAR(190) NULL,
            invited_by BIGINT UNSIGNED NULL,
            expires_at DATETIME NOT NULL,
            revoked_at DATETIME NULL,
            last_used_at DATETIME NULL,
            use_count INT UNSIGNED NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_token (token),
            KEY idx_scope (scope),
            KEY idx_contact (veranstal_x_contact_id),
            KEY idx_event (event_id),
            KEY idx_expires (expires_at)
        ) $charset;");
    }
}
