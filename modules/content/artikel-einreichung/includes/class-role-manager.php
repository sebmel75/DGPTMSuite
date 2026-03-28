<?php
/**
 * Rollen-Manager fuer Reviewer und Autoren
 * Vergibt/entzieht WP-Rollen automatisch
 */
if (!defined('ABSPATH')) exit;

class DGPTM_Artikel_Role_Manager {

    public static function grant_reviewer_role($user_id) {
        $user = get_userdata($user_id);
        if ($user && !in_array('reviewer', $user->roles)) {
            $user->add_role('reviewer');
        }
    }

    public static function revoke_reviewer_role($user_id) {
        $user = get_userdata($user_id);
        if ($user && in_array('reviewer', $user->roles)) {
            $user->remove_role('reviewer');
        }
    }

    public static function grant_autor_role($user_id) {
        $user = get_userdata($user_id);
        if (!$user) return;

        if (!wp_roles()->is_role('zeitschrift_autor')) {
            add_role('zeitschrift_autor', 'Zeitschrift-Autor', ['read' => true]);
        }

        if (!in_array('zeitschrift_autor', $user->roles)) {
            $user->add_role('zeitschrift_autor');
        }
    }

    public static function maybe_revoke_autor_role($user_id) {
        $user = get_userdata($user_id);
        if (!$user || !in_array('zeitschrift_autor', $user->roles)) return;

        $terminal_statuses = ['abgelehnt', 'veroeffentlicht'];

        $active = get_posts([
            'post_type' => 'artikel_einreichung',
            'author' => $user_id,
            'posts_per_page' => 1,
            'meta_query' => [
                [
                    'key' => 'artikel_status',
                    'value' => $terminal_statuses,
                    'compare' => 'NOT IN'
                ]
            ]
        ]);

        if (empty($active)) {
            $user->remove_role('zeitschrift_autor');
        }
    }

    public static function create_user($email, $first_name, $last_name, $role = 'reviewer') {
        $username = sanitize_user(strstr($email, '@', true), true);

        $base = $username;
        $i = 1;
        while (username_exists($username)) {
            $username = $base . $i;
            $i++;
        }

        $password = wp_generate_password(16, true);

        $user_id = wp_insert_user([
            'user_login' => $username,
            'user_email' => $email,
            'user_pass' => $password,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name),
            'role' => $role,
        ]);

        if (!is_wp_error($user_id)) {
            wp_new_user_notification($user_id, null, 'user');
        }

        return $user_id;
    }

    public static function sync_reviewer_roles($pool) {
        foreach ($pool as $reviewer) {
            $user_id = intval($reviewer['user_id'] ?? 0);
            if (!$user_id) continue;

            if (!empty($reviewer['active'])) {
                self::grant_reviewer_role($user_id);
            } else {
                self::revoke_reviewer_role($user_id);
            }
        }
    }
}
