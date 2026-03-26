<?php
/**
 * DGPTM Forum - Database Installer
 *
 * Creates and manages all custom database tables for the DGPTM Forum module.
 *
 * @package DGPTM_Forum
 * @since   1.0.0
 */

if (!defined('ABSPATH')) exit;

class DGPTM_Forum_Installer {

    /**
     * Install all forum database tables.
     *
     * Uses dbDelta for safe table creation and updates.
     *
     * @return void
     */
    public static function install() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();
        $prefix          = $wpdb->prefix . 'dgptm_forum_';

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. Arbeitsgemeinschaften
        $sql_ags = "CREATE TABLE {$prefix}ags (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            description TEXT,
            leader_user_id BIGINT UNSIGNED DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            sort_order INT DEFAULT 0,
            created_at DATETIME NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_slug (slug),
            KEY idx_status (status)
        ) $charset_collate;";

        // 2. AG Membership junction
        $sql_ag_members = "CREATE TABLE {$prefix}ag_members (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ag_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            role VARCHAR(30) DEFAULT 'member',
            joined_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_membership (ag_id, user_id),
            KEY idx_user_id (user_id),
            KEY idx_ag_id (ag_id)
        ) $charset_collate;";

        // 3. Oberthemen
        $sql_topics = "CREATE TABLE {$prefix}topics (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            ag_id BIGINT UNSIGNED DEFAULT NULL,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            description TEXT,
            access_mode VARCHAR(20) NOT NULL DEFAULT 'open',
            responsible_id BIGINT UNSIGNED DEFAULT 0,
            status VARCHAR(20) DEFAULT 'active',
            sort_order INT DEFAULT 0,
            thread_count INT UNSIGNED DEFAULT 0,
            last_activity DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL,
            created_by BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_ag_id (ag_id),
            KEY idx_access_mode (access_mode),
            KEY idx_status (status)
        ) $charset_collate;";

        // 4. Individual user access for ag_plus topics
        $sql_topic_access = "CREATE TABLE {$prefix}topic_access (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            topic_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            granted_at DATETIME NOT NULL,
            granted_by BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_access (topic_id, user_id),
            KEY idx_user_id (user_id)
        ) $charset_collate;";

        // 5. Discussion threads
        $sql_threads = "CREATE TABLE {$prefix}threads (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            topic_id BIGINT UNSIGNED NOT NULL,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT NOT NULL,
            author_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) DEFAULT 'open',
            is_pinned TINYINT(1) DEFAULT 0,
            reply_count INT UNSIGNED DEFAULT 0,
            last_reply_at DATETIME DEFAULT NULL,
            last_reply_by BIGINT UNSIGNED DEFAULT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_topic_id (topic_id),
            KEY idx_status (status),
            KEY idx_is_pinned (is_pinned),
            KEY idx_author_id (author_id)
        ) $charset_collate;";

        // 6. Nested replies (max 3 levels)
        $sql_replies = "CREATE TABLE {$prefix}replies (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            thread_id BIGINT UNSIGNED NOT NULL,
            parent_id BIGINT UNSIGNED DEFAULT 0,
            depth TINYINT UNSIGNED DEFAULT 1,
            content LONGTEXT NOT NULL,
            author_id BIGINT UNSIGNED NOT NULL,
            status VARCHAR(20) DEFAULT 'active',
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_thread_id (thread_id),
            KEY idx_parent_id (parent_id),
            KEY idx_author_id (author_id)
        ) $charset_collate;";

        // 7. File attachments
        $sql_attachments = "CREATE TABLE {$prefix}attachments (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            post_type VARCHAR(10) NOT NULL,
            post_id BIGINT UNSIGNED NOT NULL,
            attachment_id BIGINT UNSIGNED NOT NULL,
            filename VARCHAR(255) NOT NULL,
            filesize BIGINT UNSIGNED DEFAULT 0,
            mime_type VARCHAR(100) DEFAULT '',
            uploaded_at DATETIME NOT NULL,
            uploaded_by BIGINT UNSIGNED NOT NULL,
            PRIMARY KEY  (id),
            KEY idx_post (post_type, post_id)
        ) $charset_collate;";

        // 8. Email notification subscriptions
        $sql_subscriptions = "CREATE TABLE {$prefix}subscriptions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT UNSIGNED NOT NULL,
            scope VARCHAR(20) NOT NULL,
            scope_id BIGINT UNSIGNED DEFAULT 0,
            is_active TINYINT(1) DEFAULT 1,
            created_at DATETIME NOT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY unique_sub (user_id, scope, scope_id),
            KEY idx_scope (scope, scope_id),
            KEY idx_user_id (user_id)
        ) $charset_collate;";

        dbDelta($sql_ags);
        dbDelta($sql_ag_members);
        dbDelta($sql_topics);
        dbDelta($sql_topic_access);
        dbDelta($sql_threads);
        dbDelta($sql_replies);
        dbDelta($sql_attachments);
        dbDelta($sql_subscriptions);
    }

    /**
     * Handle database schema upgrades.
     *
     * Called when the module detects a version change. Add migration
     * logic here as the schema evolves.
     *
     * @param string $current_version The previously installed version.
     * @return void
     */
    public static function maybe_upgrade($current_version) {
        // Future upgrade logic goes here.
        // Example:
        // if (version_compare($current_version, '1.1.0', '<')) {
        //     self::upgrade_to_1_1_0();
        // }

        // Re-run install to pick up any column/index changes via dbDelta.
        self::install();
    }
}
