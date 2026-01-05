<?php
/**
 * Cron Handler for Projektmanagement
 * Handles scheduled email notifications
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PM_Cron_Handler')) {

    class PM_Cron_Handler {

        /**
         * Cron hook name
         */
        const CRON_HOOK = 'pm_daily_task_emails';

        /**
         * Email handler instance
         */
        private $email_handler;

        /**
         * Constructor
         *
         * @param PM_Email_Handler $email_handler Email handler instance
         */
        public function __construct($email_handler) {
            $this->email_handler = $email_handler;

            // Schedule daily cron
            add_action('init', [$this, 'schedule_daily_email']);

            // Hook for cron execution
            add_action(self::CRON_HOOK, [$this, 'send_daily_emails']);
        }

        /**
         * Schedule the daily email cron job
         */
        public function schedule_daily_email() {
            if (!wp_next_scheduled(self::CRON_HOOK)) {
                // Schedule for 7:00 AM server time
                $timestamp = strtotime('today 07:00:00');
                if ($timestamp < time()) {
                    $timestamp = strtotime('tomorrow 07:00:00');
                }
                wp_schedule_event($timestamp, 'daily', self::CRON_HOOK);
            }
        }

        /**
         * Unschedule the cron job (called on deactivation)
         */
        public static function unschedule() {
            $timestamp = wp_next_scheduled(self::CRON_HOOK);
            if ($timestamp) {
                wp_unschedule_event($timestamp, self::CRON_HOOK);
            }
        }

        /**
         * Send daily summary emails to all users with open tasks
         */
        public function send_daily_emails() {
            $this->log('Starting daily email job');

            // Get all users with open tasks
            $users_with_tasks = $this->get_users_with_open_tasks();

            $sent_count = 0;
            $error_count = 0;

            foreach ($users_with_tasks as $user_id => $tasks) {
                $user = get_userdata($user_id);

                if (!$user || empty($user->user_email)) {
                    $this->log("Skipping user {$user_id}: no email");
                    continue;
                }

                $result = $this->email_handler->send_daily_summary($user, $tasks);

                if ($result) {
                    $sent_count++;
                    $this->log("Email sent to {$user->user_email} ({$user_id}) with " . count($tasks) . " tasks");
                } else {
                    $error_count++;
                    $this->log("Failed to send email to {$user->user_email} ({$user_id})");
                }
            }

            $this->log("Daily email job completed. Sent: {$sent_count}, Errors: {$error_count}");
        }

        /**
         * Get all users who have open tasks assigned to them
         *
         * @return array Associative array: user_id => array of tasks
         */
        private function get_users_with_open_tasks() {
            $tasks = get_posts([
                'post_type'      => 'dgptm_task',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'meta_query'     => [
                    [
                        'key'     => '_pm_status',
                        'value'   => 'completed',
                        'compare' => '!=',
                    ],
                ],
            ]);

            $users_tasks = [];

            foreach ($tasks as $task) {
                $assignee = get_post_meta($task->ID, '_pm_assignee', true);

                if (!$assignee || $assignee == 0) {
                    continue; // Task not assigned
                }

                if (!isset($users_tasks[$assignee])) {
                    $users_tasks[$assignee] = [];
                }

                $users_tasks[$assignee][] = $task;
            }

            // Sort tasks by due date for each user
            foreach ($users_tasks as $user_id => &$tasks) {
                usort($tasks, function($a, $b) {
                    $date_a = get_post_meta($a->ID, '_pm_due_date', true);
                    $date_b = get_post_meta($b->ID, '_pm_due_date', true);

                    if (!$date_a && !$date_b) return 0;
                    if (!$date_a) return 1;
                    if (!$date_b) return -1;

                    return strtotime($date_a) - strtotime($date_b);
                });
            }

            return $users_tasks;
        }

        /**
         * Log message for debugging
         *
         * @param string $message Log message
         */
        private function log($message) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[PM Cron] ' . $message);
            }
        }

        /**
         * Manually trigger daily emails (for testing)
         *
         * @return array Results
         */
        public function trigger_manual() {
            $users_with_tasks = $this->get_users_with_open_tasks();

            $results = [
                'users_count' => count($users_with_tasks),
                'sent'        => [],
                'failed'      => [],
            ];

            foreach ($users_with_tasks as $user_id => $tasks) {
                $user = get_userdata($user_id);

                if (!$user || empty($user->user_email)) {
                    $results['failed'][] = [
                        'user_id' => $user_id,
                        'reason'  => 'No email address',
                    ];
                    continue;
                }

                $result = $this->email_handler->send_daily_summary($user, $tasks);

                if ($result) {
                    $results['sent'][] = [
                        'user_id'    => $user_id,
                        'email'      => $user->user_email,
                        'task_count' => count($tasks),
                    ];
                } else {
                    $results['failed'][] = [
                        'user_id' => $user_id,
                        'email'   => $user->user_email,
                        'reason'  => 'wp_mail failed',
                    ];
                }
            }

            return $results;
        }

        /**
         * Get cron status info
         *
         * @return array Cron status
         */
        public function get_status() {
            $next_run = wp_next_scheduled(self::CRON_HOOK);

            return [
                'scheduled'      => $next_run !== false,
                'next_run'       => $next_run ? date_i18n('d.m.Y H:i:s', $next_run) : null,
                'next_run_timestamp' => $next_run,
            ];
        }
    }
}
