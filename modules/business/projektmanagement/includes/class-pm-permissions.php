<?php
/**
 * Permissions Manager for Projektmanagement
 * Handles user permission checks based on user meta
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PM_Permissions')) {

    class PM_Permissions {

        /**
         * User meta key for project manager flag
         */
        const META_KEY = 'pm_is_projektmanager';

        /**
         * Check if user is a project manager
         *
         * @param int|null $user_id User ID (defaults to current user)
         * @return bool
         */
        public function is_projektmanager($user_id = null) {
            if ($user_id === null) {
                $user_id = get_current_user_id();
            }

            if (!$user_id) {
                return false;
            }

            // Administrators are always project managers
            if (user_can($user_id, 'manage_options')) {
                return true;
            }

            // Check user meta flag
            return get_user_meta($user_id, self::META_KEY, true) === '1';
        }

        /**
         * Set project manager status for user
         *
         * @param int $user_id User ID
         * @param bool $is_manager Whether user should be a manager
         */
        public function set_projektmanager($user_id, $is_manager) {
            update_user_meta($user_id, self::META_KEY, $is_manager ? '1' : '0');
        }

        /**
         * Check if user can view a specific task
         *
         * @param int $task_id Task post ID
         * @param int|null $user_id User ID (defaults to current user)
         * @return bool
         */
        public function can_view_task($task_id, $user_id = null) {
            if ($user_id === null) {
                $user_id = get_current_user_id();
            }

            // Managers can view all tasks
            if ($this->is_projektmanager($user_id)) {
                return true;
            }

            // Check if user is assigned to the task
            $assignee = get_post_meta($task_id, '_pm_assignee', true);
            return (int) $assignee === (int) $user_id;
        }

        /**
         * Check if user can edit a specific task
         *
         * @param int $task_id Task post ID
         * @param int|null $user_id User ID (defaults to current user)
         * @return bool
         */
        public function can_edit_task($task_id, $user_id = null) {
            if ($user_id === null) {
                $user_id = get_current_user_id();
            }

            // Only managers can edit tasks
            return $this->is_projektmanager($user_id);
        }

        /**
         * Check if user can complete a specific task
         *
         * @param int $task_id Task post ID
         * @param int|null $user_id User ID (defaults to current user)
         * @return bool
         */
        public function can_complete_task($task_id, $user_id = null) {
            if ($user_id === null) {
                $user_id = get_current_user_id();
            }

            // Managers can complete any task
            if ($this->is_projektmanager($user_id)) {
                return true;
            }

            // Assignee can complete their task
            $assignee = get_post_meta($task_id, '_pm_assignee', true);
            return (int) $assignee === (int) $user_id;
        }

        /**
         * Check if user can view a specific project
         *
         * @param int $project_id Project post ID
         * @param int|null $user_id User ID (defaults to current user)
         * @return bool
         */
        public function can_view_project($project_id, $user_id = null) {
            if ($user_id === null) {
                $user_id = get_current_user_id();
            }

            // Managers can view all projects
            if ($this->is_projektmanager($user_id)) {
                return true;
            }

            // Check if user has any tasks in this project
            $tasks = get_posts([
                'post_type'      => 'dgptm_task',
                'posts_per_page' => 1,
                'meta_query'     => [
                    'relation' => 'AND',
                    [
                        'key'   => '_pm_project_id',
                        'value' => $project_id,
                    ],
                    [
                        'key'   => '_pm_assignee',
                        'value' => $user_id,
                    ],
                ],
            ]);

            return !empty($tasks);
        }

        /**
         * Check if user can manage projects (create/edit/delete)
         *
         * @param int|null $user_id User ID (defaults to current user)
         * @return bool
         */
        public function can_manage_projects($user_id = null) {
            return $this->is_projektmanager($user_id);
        }

        /**
         * Check if user can manage templates
         *
         * @param int|null $user_id User ID (defaults to current user)
         * @return bool
         */
        public function can_manage_templates($user_id = null) {
            return $this->is_projektmanager($user_id);
        }

        /**
         * Get all project managers
         *
         * @return array Array of WP_User objects
         */
        public function get_all_projektmanagers() {
            $users = get_users([
                'meta_key'   => self::META_KEY,
                'meta_value' => '1',
            ]);

            // Also include administrators
            $admins = get_users([
                'role' => 'administrator',
            ]);

            // Merge and remove duplicates
            $all_managers = array_merge($users, $admins);
            $unique = [];

            foreach ($all_managers as $user) {
                $unique[$user->ID] = $user;
            }

            return array_values($unique);
        }

        /**
         * Get all users who can be assigned tasks
         *
         * @return array Array of user data
         */
        public function get_assignable_users() {
            $users = get_users([
                'fields' => ['ID', 'display_name', 'user_email'],
            ]);

            return array_map(function($user) {
                return [
                    'id'    => $user->ID,
                    'name'  => $user->display_name,
                    'email' => $user->user_email,
                ];
            }, $users);
        }
    }
}
