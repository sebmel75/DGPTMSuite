<?php
/**
 * DB-Installer fuer Workshop-Booking Phase 1.
 *
 * Legt drei Tabellen an:
 *   - sync_log         (append-only Audit, AGB §6 Abs. 3 Schriftform-Backup)
 *   - drift_alerts     (kuratierter Alert-Stream fuer Geschaeftsstelle)
 *   - pending_bookings (Uebergang zwischen book() und Stripe-Webhook)
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Installer {

    const DB_VERSION    = '1';
    const OPT_DB_VERSION = 'dgptm_wsb_db_version';

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

        dbDelta("CREATE TABLE {$wpdb->prefix}dgptm_workshop_sync_log (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            veranstal_x_contact_id VARCHAR(40) NOT NULL,
            source VARCHAR(40) NOT NULL,
            intent_blueprint_state VARCHAR(80) NULL,
            intent_payment_status VARCHAR(40) NULL,
            previous_blueprint_state VARCHAR(80) NULL,
            previous_payment_status VARCHAR(40) NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            error_code VARCHAR(40) NULL,
            error_message TEXT NULL,
            payload_json LONGTEXT NULL,
            reason VARCHAR(160) NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_contact (veranstal_x_contact_id),
            KEY idx_created (created_at)
        ) $charset;");

        dbDelta("CREATE TABLE {$wpdb->prefix}dgptm_workshop_drift_alerts (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            veranstal_x_contact_id VARCHAR(40) NULL,
            code VARCHAR(60) NOT NULL,
            severity ENUM('info','warning','critical') NOT NULL DEFAULT 'warning',
            crm_state_snapshot LONGTEXT NULL,
            external_state_snapshot LONGTEXT NULL,
            proposed_action TEXT NULL,
            status ENUM('open','acknowledged','resolved','ignored') NOT NULL DEFAULT 'open',
            acknowledged_by BIGINT UNSIGNED NULL,
            acknowledged_at DATETIME NULL,
            resolved_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY idx_contact (veranstal_x_contact_id),
            KEY idx_status (status),
            KEY idx_created (created_at)
        ) $charset;");

        dbDelta("CREATE TABLE {$wpdb->prefix}dgptm_workshop_pending_bookings (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            veranstal_x_contact_id VARCHAR(40) NOT NULL,
            event_id VARCHAR(40) NOT NULL,
            attendees_json LONGTEXT NOT NULL,
            stripe_session_id VARCHAR(255) NULL,
            stripe_session_expires_at DATETIME NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_contact (veranstal_x_contact_id),
            KEY idx_session (stripe_session_id),
            KEY idx_expires (stripe_session_expires_at)
        ) $charset;");
    }
}
