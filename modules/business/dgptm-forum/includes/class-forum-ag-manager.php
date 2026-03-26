<?php
if (!defined('ABSPATH')) exit;

class DGPTM_Forum_AG_Manager {

    /**
     * Get all AGs, optionally filtered by status.
     */
    public static function get_all_ags($status = 'active') {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_forum_ags';

        return $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE status = %s ORDER BY sort_order ASC, name ASC",
                $status
            )
        );
    }

    /**
     * Get a single AG by ID.
     */
    public static function get_ag($ag_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_forum_ags';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $ag_id)
        );
    }

    /**
     * Create a new AG.
     */
    public static function create_ag($data) {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_forum_ags';

        $name = isset($data['name']) ? sanitize_text_field($data['name']) : '';
        if (empty($name)) {
            return new WP_Error('missing_name', 'Name der Hauptgruppe ist erforderlich.');
        }

        $result = $wpdb->insert($table, [
            'name'           => $name,
            'slug'           => sanitize_title($name),
            'description'    => isset($data['description']) ? sanitize_textarea_field($data['description']) : '',
            'group_type'     => isset($data['group_type']) ? sanitize_text_field($data['group_type']) : 'open',
            'is_hidden'      => !empty($data['is_hidden']) ? 1 : 0,
            'moderator_id'   => isset($data['moderator_id']) ? absint($data['moderator_id']) : 0,
            'leader_user_id' => isset($data['leader_user_id']) ? absint($data['leader_user_id']) : 0,
            'created_at'     => current_time('mysql'),
            'created_by'     => get_current_user_id(),
        ], ['%s', '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%d']);

        if (false === $result) {
            return new WP_Error('db_error', 'Hauptgruppe konnte nicht erstellt werden: ' . $wpdb->last_error);
        }

        return $wpdb->insert_id;
    }

    /**
     * Update an existing AG.
     */
    public static function update_ag($ag_id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_forum_ags';

        $update = [];
        $format = [];

        if (isset($data['name'])) {
            $update['name'] = sanitize_text_field($data['name']);
            $update['slug'] = sanitize_title($data['name']);
            $format[] = '%s';
            $format[] = '%s';
        }
        if (isset($data['description'])) {
            $update['description'] = sanitize_textarea_field($data['description']);
            $format[] = '%s';
        }
        if (isset($data['group_type'])) {
            $update['group_type'] = sanitize_text_field($data['group_type']);
            $format[] = '%s';
        }
        if (array_key_exists('is_hidden', $data)) {
            $update['is_hidden'] = !empty($data['is_hidden']) ? 1 : 0;
            $format[] = '%d';
        }
        if (isset($data['moderator_id'])) {
            $update['moderator_id'] = absint($data['moderator_id']);
            $format[] = '%d';
        }
        if (isset($data['leader_user_id'])) {
            $update['leader_user_id'] = absint($data['leader_user_id']);
            $format[] = '%d';
        }
        if (isset($data['status'])) {
            $update['status'] = sanitize_text_field($data['status']);
            $format[] = '%s';
        }
        if (isset($data['sort_order'])) {
            $update['sort_order'] = intval($data['sort_order']);
            $format[] = '%d';
        }

        if (empty($update)) {
            return false;
        }

        return $wpdb->update($table, $update, ['id' => absint($ag_id)], $format, ['%d']);
    }

    /**
     * Delete an AG and all related data.
     */
    public static function delete_ag($ag_id) {
        global $wpdb;

        $ag_id          = absint($ag_id);
        $ags_table      = $wpdb->prefix . 'dgptm_forum_ags';
        $members_table  = $wpdb->prefix . 'dgptm_forum_ag_members';
        $topics_table   = $wpdb->prefix . 'dgptm_forum_topics';

        // Delete topic references for this AG
        $wpdb->delete($topics_table, ['ag_id' => $ag_id], ['%d']);

        // Delete all AG members
        $wpdb->delete($members_table, ['ag_id' => $ag_id], ['%d']);

        // Delete the AG itself
        return $wpdb->delete($ags_table, ['id' => $ag_id], ['%d']);
    }

    /**
     * Get all members of an AG with user details.
     */
    public static function get_ag_members($ag_id) {
        global $wpdb;

        $members_table = $wpdb->prefix . 'dgptm_forum_ag_members';
        $users_table   = $wpdb->users;

        return $wpdb->get_results($wpdb->prepare(
            "SELECT m.user_id, u.display_name, u.user_email, m.role, m.joined_at
             FROM {$members_table} m
             JOIN {$users_table} u ON u.ID = m.user_id
             WHERE m.ag_id = %d
             ORDER BY u.display_name ASC",
            $ag_id
        ));
    }

    /**
     * Add a member to an AG.
     */
    public static function add_member($ag_id, $user_id, $role = 'member') {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_forum_ag_members';

        return $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (ag_id, user_id, role, joined_at)
             VALUES (%d, %d, %s, %s)",
            absint($ag_id),
            absint($user_id),
            sanitize_text_field($role),
            current_time('mysql')
        ));
    }

    /**
     * Remove a member from an AG.
     */
    public static function remove_member($ag_id, $user_id) {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_forum_ag_members';

        return $wpdb->delete($table, [
            'ag_id'   => absint($ag_id),
            'user_id' => absint($user_id),
        ], ['%d', '%d']);
    }

    /**
     * Set the role of a member within an AG.
     */
    public static function set_member_role($ag_id, $user_id, $role) {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_forum_ag_members';

        return $wpdb->update(
            $table,
            ['role' => sanitize_text_field($role)],
            [
                'ag_id'   => absint($ag_id),
                'user_id' => absint($user_id),
            ],
            ['%s'],
            ['%d', '%d']
        );
    }

    /**
     * Get all AGs a user belongs to.
     */
    public static function get_user_ags($user_id) {
        global $wpdb;

        $ags_table     = $wpdb->prefix . 'dgptm_forum_ags';
        $members_table = $wpdb->prefix . 'dgptm_forum_ag_members';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT a.*, m.role, m.joined_at
             FROM {$members_table} m
             JOIN {$ags_table} a ON a.id = m.ag_id
             WHERE m.user_id = %d
             ORDER BY a.name ASC",
            absint($user_id)
        ));
    }

    /**
     * Search WordPress users by display name or email.
     */
    public static function search_users($search_term, $limit = 20) {
        global $wpdb;

        $like = '%' . $wpdb->esc_like(sanitize_text_field($search_term)) . '%';

        return $wpdb->get_results($wpdb->prepare(
            "SELECT ID AS id, user_login, display_name, user_email
             FROM {$wpdb->users}
             WHERE display_name LIKE %s OR user_email LIKE %s OR user_login LIKE %s
             ORDER BY display_name ASC
             LIMIT %d",
            $like,
            $like,
            $like,
            absint($limit)
        ));
    }
}
