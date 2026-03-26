<?php
/**
 * DGPTM Forum – Permissions
 *
 * Statische Hilfsklasse für alle Berechtigungsprüfungen im Forum-Modul.
 *
 * @package DGPTM_Forum
 */

if (!defined('ABSPATH')) exit;

class DGPTM_Forum_Permissions {

    /**
     * Statischer Cache für Topic-Rows (vermeidet wiederholte DB-Abfragen).
     *
     * @var array
     */
    private static $topic_cache = [];

    // ------------------------------------------------------------------
    //  Rollen-Prüfungen
    // ------------------------------------------------------------------

    /**
     * Prüft, ob ein Benutzer Forum-Administrator ist.
     *
     * @param int|null $user_id  WordPress-User-ID (null = aktueller Benutzer).
     * @return bool
     */
    public static function is_forum_admin($user_id = null) {
        if ($user_id === null) {
            $user_id = get_current_user_id();
        }

        if (user_can($user_id, 'manage_options')) {
            return true;
        }

        return (string) get_user_meta($user_id, 'dgptm_forum_admin', true) === '1';
    }

    /**
     * Prüft, ob ein Benutzer Mitglied einer Arbeitsgruppe ist.
     *
     * @param int $user_id
     * @param int $ag_id
     * @return bool
     */
    public static function is_ag_member($user_id, $ag_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'dgptm_forum_ag_members';

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND ag_id = %d",
            (int) $user_id,
            (int) $ag_id
        ));

        return (int) $result > 0;
    }

    /**
     * Prüft, ob ein Benutzer Leiter einer Arbeitsgruppe ist.
     *
     * @param int $user_id
     * @param int $ag_id
     * @return bool
     */
    public static function is_ag_leiter($user_id, $ag_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'dgptm_forum_ag_members';

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND ag_id = %d AND role = 'leiter'",
            (int) $user_id,
            (int) $ag_id
        ));

        return (int) $result > 0;
    }

    /**
     * Prüft, ob ein Benutzer für ein Thema verantwortlich ist.
     *
     * @param int $user_id
     * @param int $topic_id
     * @return bool
     */
    public static function is_topic_responsible($user_id, $topic_id) {
        $topic = self::get_topic($topic_id);

        if (!$topic) {
            return false;
        }

        return (int) $topic->responsible_id === (int) $user_id;
    }

    // ------------------------------------------------------------------
    //  Zugriffs-Prüfungen
    // ------------------------------------------------------------------

    /**
     * Prüft, ob ein Benutzer ein Thema sehen darf.
     *
     * @param int $user_id
     * @param int $topic_id
     * @return bool
     */
    public static function can_view_topic($user_id, $topic_id) {
        // Admins dürfen alles sehen.
        if (user_can($user_id, 'manage_options') || self::is_forum_admin($user_id)) {
            return true;
        }

        $topic = self::get_topic($topic_id);

        if (!$topic) {
            return false;
        }

        switch ($topic->access_mode) {
            case 'open':
                // Jeder eingeloggte Benutzer darf zugreifen.
                return true;

            case 'ag_only':
                return self::is_ag_member($user_id, (int) $topic->ag_id);

            case 'ag_plus':
                return self::is_ag_member($user_id, (int) $topic->ag_id)
                    || self::has_individual_access($user_id, $topic_id);

            default:
                return false;
        }
    }

    /**
     * Prüft, ob ein Benutzer in einem Thema posten darf.
     *
     * Derzeit identisch mit can_view_topic – wer lesen darf, darf auch posten.
     *
     * @param int $user_id
     * @param int $topic_id
     * @return bool
     */
    public static function can_post_in_topic($user_id, $topic_id) {
        return self::can_view_topic($user_id, $topic_id);
    }

    /**
     * Prüft, ob ein Benutzer ein Thema moderieren darf.
     *
     * @param int $user_id
     * @param int $topic_id
     * @return bool
     */
    public static function can_moderate_topic($user_id, $topic_id) {
        if (user_can($user_id, 'manage_options') || self::is_forum_admin($user_id)) {
            return true;
        }

        if (self::is_topic_responsible($user_id, $topic_id)) {
            return true;
        }

        $topic = self::get_topic($topic_id);

        if ($topic && !empty($topic->ag_id) && self::is_ag_leiter($user_id, (int) $topic->ag_id)) {
            return true;
        }

        return false;
    }

    /**
     * Prüft, ob ein Benutzer einen Beitrag bearbeiten darf.
     *
     * Eigene Beiträge dürfen immer bearbeitet werden; Moderatoren dürfen fremde
     * Beiträge ebenfalls bearbeiten.
     *
     * @param int    $user_id
     * @param string $post_type  Tabellen-Suffix, z. B. 'posts' oder 'replies'.
     * @param int    $post_id
     * @return bool
     */
    public static function can_edit_post($user_id, $post_type, $post_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'dgptm_forum_' . sanitize_key($post_type);

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT author_id, topic_id FROM {$table} WHERE id = %d",
            (int) $post_id
        ));

        if (!$row) {
            return false;
        }

        // Eigener Beitrag?
        if ((int) $row->author_id === (int) $user_id) {
            return true;
        }

        // Moderationsrechte für das zugehörige Thema?
        return self::can_moderate_topic($user_id, (int) $row->topic_id);
    }

    /**
     * Prüft, ob ein Benutzer einen Beitrag löschen darf.
     *
     * Nur Moderatoren dürfen Beiträge löschen.
     *
     * @param int    $user_id
     * @param string $post_type  Tabellen-Suffix, z. B. 'posts' oder 'replies'.
     * @param int    $post_id
     * @return bool
     */
    public static function can_delete_post($user_id, $post_type, $post_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'dgptm_forum_' . sanitize_key($post_type);

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT topic_id FROM {$table} WHERE id = %d",
            (int) $post_id
        ));

        if (!$row) {
            return false;
        }

        return self::can_moderate_topic($user_id, (int) $row->topic_id);
    }

    /**
     * Prüft, ob ein Benutzer individuellen Zugriff auf ein Thema hat.
     *
     * @param int $user_id
     * @param int $topic_id
     * @return bool
     */
    public static function has_individual_access($user_id, $topic_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'dgptm_forum_topic_access';

        $result = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND topic_id = %d",
            (int) $user_id,
            (int) $topic_id
        ));

        return (int) $result > 0;
    }

    // ------------------------------------------------------------------
    //  Admin-Verwaltung
    // ------------------------------------------------------------------

    /**
     * Setzt oder entfernt den Forum-Admin-Status eines Benutzers.
     *
     * @param int  $user_id
     * @param bool $is_admin
     * @return void
     */
    public static function set_forum_admin($user_id, $is_admin) {
        if ($is_admin) {
            update_user_meta($user_id, 'dgptm_forum_admin', 1);
        } else {
            delete_user_meta($user_id, 'dgptm_forum_admin');
        }
    }

    /**
     * Gibt alle Benutzer zurück, die Forum-Administrator sind.
     *
     * @return \WP_User[]
     */
    public static function get_forum_admins() {
        $query = new \WP_User_Query([
            'meta_key'   => 'dgptm_forum_admin',
            'meta_value' => '1',
        ]);

        return $query->get_results();
    }

    // ------------------------------------------------------------------
    //  Interner Helfer
    // ------------------------------------------------------------------

    /**
     * Lädt eine Topic-Zeile aus der Datenbank (mit statischem Cache).
     *
     * @param int $topic_id
     * @return object|null
     */
    private static function get_topic($topic_id) {
        $topic_id = (int) $topic_id;

        if (isset(self::$topic_cache[$topic_id])) {
            return self::$topic_cache[$topic_id];
        }

        global $wpdb;

        $table = $wpdb->prefix . 'dgptm_forum_topics';

        $topic = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table} WHERE id = %d",
            $topic_id
        ));

        self::$topic_cache[$topic_id] = $topic;

        return $topic;
    }
}
