<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('dgptm_activate_plugin')) {
    function dgptm_activate_plugin() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $table_polls        = $wpdb->prefix . 'dgptm_abstimmung_polls';
        $table_questions    = $wpdb->prefix . 'dgptm_abstimmung_poll_questions';
        $table_votes        = $wpdb->prefix . 'dgptm_abstimmung_votes';
        $table_participants = $wpdb->prefix . 'dgptm_abstimmung_participants';

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

        $sql_polls = "CREATE TABLE $table_polls (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            status VARCHAR(20) NOT NULL,
            created DATETIME NOT NULL,
            ended DATETIME DEFAULT NULL,
            requires_signup TINYINT(1) NOT NULL DEFAULT 0,
            time_limit INT NOT NULL DEFAULT 0,
            logo_url VARCHAR(500) DEFAULT '',
            PRIMARY KEY (id)
        ) $charset_collate;";

        $sql_questions = "CREATE TABLE $table_questions (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            poll_id BIGINT(20) UNSIGNED NOT NULL,
            question VARCHAR(255) NOT NULL,
            choices TEXT NOT NULL,
            max_votes INT NOT NULL DEFAULT 1,
            status VARCHAR(20) NOT NULL,
            results_released TINYINT(1) NOT NULL DEFAULT 0,
            created DATETIME NOT NULL,
            ended DATETIME DEFAULT NULL,
            time_limit INT NOT NULL DEFAULT 0,
            max_choices INT NOT NULL DEFAULT 0,
            is_repeatable TINYINT(1) NOT NULL DEFAULT 1,
            is_anonymous TINYINT(1) NOT NULL DEFAULT 0,
            chart_type VARCHAR(10) NOT NULL DEFAULT 'bar',
            in_overall TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY poll_id (poll_id)
        ) $charset_collate;";

        $sql_votes = "CREATE TABLE $table_votes (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            question_id BIGINT(20) UNSIGNED NOT NULL,
            choice_index INT NOT NULL,
            user_id BIGINT(20) UNSIGNED DEFAULT 0,
            vote_time DATETIME NOT NULL,
            ip VARCHAR(100) NOT NULL,
            is_invalid TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (id),
            KEY question_id (question_id),
            KEY user_id (user_id)
        ) $charset_collate;";

        $sql_participants = "CREATE TABLE $table_participants (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            poll_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED DEFAULT 0,
            fullname VARCHAR(255) NOT NULL,
            cookie_id VARCHAR(100) DEFAULT '',
            joined_time DATETIME NOT NULL,
            first_name VARCHAR(150) DEFAULT '',
            last_name VARCHAR(150) DEFAULT '',
            member_no VARCHAR(150) DEFAULT '',
            member_status VARCHAR(150) DEFAULT '',
            email VARCHAR(255) DEFAULT '',
            token VARCHAR(190) DEFAULT '',
            source VARCHAR(50) DEFAULT '',
            PRIMARY KEY (id),
            KEY poll_id (poll_id),
            KEY token (token),
            UNIQUE KEY uniq_member (poll_id, member_no)
        ) $charset_collate;";

        dbDelta($sql_polls);
        dbDelta($sql_questions);
        dbDelta($sql_votes);
        dbDelta($sql_participants);

        add_option('dgptm_db_version', DGPTMVOTE_VERSION);
    }
}

if (!function_exists('dgptm_maybe_upgrade_db')) {
    function dgptm_maybe_upgrade_db() {
        global $wpdb;
        $current = get_option('dgptm_db_version', '0');
        if (version_compare($current, DGPTMVOTE_VERSION, '>=')) return;

        $p = $wpdb->prefix.'dgptm_abstimmung_polls';
        $q = $wpdb->prefix.'dgptm_abstimmung_poll_questions';
        $v = $wpdb->prefix.'dgptm_abstimmung_votes';
        $t = $wpdb->prefix.'dgptm_abstimmung_participants';

        $has = function($table,$col) use($wpdb){
            $rows = $wpdb->get_results("SHOW COLUMNS FROM $table", ARRAY_A);
            if (!$rows) return false;
            foreach($rows as $r){ if($r['Field']===$col) return true; }
            return false;
        };

        if(!$has($p,'requires_signup')) $wpdb->query("ALTER TABLE $p ADD COLUMN requires_signup TINYINT(1) NOT NULL DEFAULT 0");
        if(!$has($p,'time_limit'))      $wpdb->query("ALTER TABLE $p ADD COLUMN time_limit INT NOT NULL DEFAULT 0");
        if(!$has($p,'logo_url'))        $wpdb->query("ALTER TABLE $p ADD COLUMN logo_url VARCHAR(500) DEFAULT ''");
        if(!$has($p,'ended'))           $wpdb->query("ALTER TABLE $p ADD COLUMN ended DATETIME NULL DEFAULT NULL");

        if(!$has($q,'results_released')) $wpdb->query("ALTER TABLE $q ADD COLUMN results_released TINYINT(1) NOT NULL DEFAULT 0");
        if(!$has($q,'ended'))            $wpdb->query("ALTER TABLE $q ADD COLUMN ended DATETIME NULL DEFAULT NULL");
        if(!$has($q,'time_limit'))       $wpdb->query("ALTER TABLE $q ADD COLUMN time_limit INT NOT NULL DEFAULT 0");
        if(!$has($q,'max_choices'))      $wpdb->query("ALTER TABLE $q ADD COLUMN max_choices INT NOT NULL DEFAULT 0");
        if(!$has($q,'is_repeatable'))    $wpdb->query("ALTER TABLE $q ADD COLUMN is_repeatable TINYINT(1) NOT NULL DEFAULT 1");
        if(!$has($q,'is_anonymous'))     $wpdb->query("ALTER TABLE $q ADD COLUMN is_anonymous TINYINT(1) NOT NULL DEFAULT 0");
        if(!$has($q,'chart_type'))       $wpdb->query("ALTER TABLE $q ADD COLUMN chart_type VARCHAR(10) NOT NULL DEFAULT 'bar'");
        if(!$has($q,'in_overall'))       $wpdb->query("ALTER TABLE $q ADD COLUMN in_overall TINYINT(1) NOT NULL DEFAULT 0");

        if(!$has($v,'is_invalid'))       $wpdb->query("ALTER TABLE $v ADD COLUMN is_invalid TINYINT(1) NOT NULL DEFAULT 0");

        if(!$has($t,'cookie_id'))        $wpdb->query("ALTER TABLE $t ADD COLUMN cookie_id VARCHAR(100) DEFAULT ''");
        if(!$has($t,'first_name'))       $wpdb->query("ALTER TABLE $t ADD COLUMN first_name VARCHAR(150) DEFAULT ''");
        if(!$has($t,'last_name'))        $wpdb->query("ALTER TABLE $t ADD COLUMN last_name VARCHAR(150) DEFAULT ''");
        if(!$has($t,'member_no'))        $wpdb->query("ALTER TABLE $t ADD COLUMN member_no VARCHAR(150) DEFAULT ''");
        if(!$has($t,'member_status'))    $wpdb->query("ALTER TABLE $t ADD COLUMN member_status VARCHAR(150) DEFAULT ''");
        if(!$has($t,'email'))            $wpdb->query("ALTER TABLE $t ADD COLUMN email VARCHAR(255) DEFAULT ''");
        if(!$has($t,'token'))            $wpdb->query("ALTER TABLE $t ADD COLUMN token VARCHAR(190) DEFAULT ''");
        if(!$has($t,'source'))           $wpdb->query("ALTER TABLE $t ADD COLUMN source VARCHAR(50) DEFAULT ''");

        // Unique key for (poll_id, member_no)
        $keys = $wpdb->get_results("SHOW INDEX FROM $t");
        $has_uniq = false;
        if($keys){
            foreach($keys as $k){
                if(isset($k->Key_name) && $k->Key_name==='uniq_member'){ $has_uniq = true; break; }
            }
        }
        if(!$has_uniq){
            $wpdb->query("ALTER TABLE $t ADD UNIQUE KEY uniq_member (poll_id, member_no)");
        }

        update_option('dgptm_db_version', DGPTMVOTE_VERSION);
    }
}
