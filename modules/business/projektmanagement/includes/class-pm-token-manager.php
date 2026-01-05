<?php
/**
 * Token Manager for Projektmanagement
 * Handles token generation, verification, and invalidation
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PM_Token_Manager')) {

    class PM_Token_Manager {

        /**
         * Generate unique token for task assignment
         *
         * @param int $task_id Task post ID
         * @param int $user_id Assigned user ID
         * @return string Generated token
         */
        public function generate_token($task_id, $user_id) {
            // Generate cryptographically secure 32-character token
            $token = wp_generate_password(32, false, false);

            // Store token in post meta
            update_post_meta($task_id, '_pm_access_token', $token);
            update_post_meta($task_id, '_pm_token_user_id', $user_id);
            update_post_meta($task_id, '_pm_token_valid', 1);
            update_post_meta($task_id, '_pm_token_created', current_time('mysql'));

            return $token;
        }

        /**
         * Verify token and return task if valid
         *
         * @param string $token Access token
         * @return WP_Post|false Task post object or false if invalid
         */
        public function verify_token($token) {
            if (empty($token) || strlen($token) !== 32) {
                return false;
            }

            // Sanitize token
            $token = sanitize_text_field($token);

            $tasks = get_posts([
                'post_type'      => 'dgptm_task',
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'meta_query'     => [
                    'relation' => 'AND',
                    [
                        'key'   => '_pm_access_token',
                        'value' => $token,
                    ],
                    [
                        'key'   => '_pm_token_valid',
                        'value' => 1,
                    ],
                ],
            ]);

            if (empty($tasks)) {
                return false;
            }

            return $tasks[0];
        }

        /**
         * Invalidate token (called when task completed)
         *
         * @param int $task_id Task post ID
         */
        public function invalidate_token($task_id) {
            update_post_meta($task_id, '_pm_token_valid', 0);
            update_post_meta($task_id, '_pm_token_invalidated', current_time('mysql'));
        }

        /**
         * Get token URL for task
         *
         * @param int $task_id Task post ID
         * @return string|false Token URL or false if no token
         */
        public function get_token_url($task_id) {
            $token = get_post_meta($task_id, '_pm_access_token', true);

            if (!$token) {
                return false;
            }

            // Use site URL with query parameter
            return add_query_arg('pm_token', $token, home_url('/'));
        }

        /**
         * Regenerate token for task
         *
         * @param int $task_id Task post ID
         * @return string New token
         */
        public function regenerate_token($task_id) {
            $user_id = get_post_meta($task_id, '_pm_assignee', true);

            // Invalidate old token
            $this->invalidate_token($task_id);

            // Generate new token
            return $this->generate_token($task_id, $user_id);
        }

        /**
         * Check if token is valid for a specific task
         *
         * @param int $task_id Task post ID
         * @return bool
         */
        public function is_token_valid($task_id) {
            return (int) get_post_meta($task_id, '_pm_token_valid', true) === 1;
        }

        /**
         * Get task ID by token
         *
         * @param string $token Access token
         * @return int|false Task ID or false
         */
        public function get_task_id_by_token($token) {
            $task = $this->verify_token($token);
            return $task ? $task->ID : false;
        }
    }
}
