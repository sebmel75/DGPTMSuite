<?php
/**
 * Email Handler for Projektmanagement
 * Handles email templates and sending
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PM_Email_Handler')) {

    class PM_Email_Handler {

        /**
         * Send daily task summary email to a user
         *
         * @param WP_User $user User object
         * @param array $tasks Array of task posts
         * @return bool Success
         */
        public function send_daily_summary($user, $tasks) {
            if (empty($tasks)) {
                return false;
            }

            $subject = sprintf(
                'Ihre offenen Aufgaben - %s',
                date_i18n('d.m.Y')
            );

            $body = $this->build_daily_summary_body($user, $tasks);

            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
            ];

            return wp_mail($user->user_email, $subject, $body, $headers);
        }

        /**
         * Build daily summary email body
         *
         * @param WP_User $user User object
         * @param array $tasks Array of task posts
         * @return string HTML email body
         */
        private function build_daily_summary_body($user, $tasks) {
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
            $html .= '<style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: #fff; }
                h2 { color: #0073aa; margin-bottom: 20px; }
                .task-list { margin: 20px 0; }
                .task-item { background: #f9f9f9; padding: 15px; margin: 10px 0; border-left: 4px solid #0073aa; border-radius: 0 4px 4px 0; }
                .task-item.high { border-left-color: #dc3232; }
                .task-item.medium { border-left-color: #f0ad4e; }
                .task-item.low { border-left-color: #46b450; }
                .task-title { font-weight: bold; font-size: 16px; margin-bottom: 5px; }
                .task-meta { color: #666; font-size: 13px; margin: 5px 0; }
                .task-meta span { display: inline-block; margin-right: 15px; }
                .task-link { display: inline-block; margin-top: 10px; padding: 8px 15px; background: #0073aa; color: white !important; text-decoration: none; border-radius: 3px; font-size: 14px; }
                .task-link:hover { background: #005a87; }
                .priority-badge { display: inline-block; padding: 2px 8px; border-radius: 3px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
                .priority-high { background: #dc3232; color: white; }
                .priority-medium { background: #f0ad4e; color: white; }
                .priority-low { background: #46b450; color: white; }
                .overdue { color: #dc3232; font-weight: bold; }
                .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 12px; }
            </style>';
            $html .= '</head><body>';
            $html .= '<div class="container">';

            $html .= '<h2>Guten Morgen, ' . esc_html($user->display_name) . '!</h2>';
            $html .= '<p>Sie haben <strong>' . count($tasks) . ' offene Aufgabe(n)</strong>:</p>';

            $html .= '<div class="task-list">';

            foreach ($tasks as $task) {
                $priority = get_post_meta($task->ID, '_pm_priority', true) ?: 'medium';
                $due_date = get_post_meta($task->ID, '_pm_due_date', true);
                $project_id = get_post_meta($task->ID, '_pm_project_id', true);
                $project = get_post($project_id);
                $token = get_post_meta($task->ID, '_pm_access_token', true);

                // Check if overdue
                $is_overdue = false;
                if ($due_date && strtotime($due_date) < strtotime('today')) {
                    $is_overdue = true;
                }

                $html .= '<div class="task-item ' . esc_attr($priority) . '">';
                $html .= '<div class="task-title">' . esc_html($task->post_title) . '</div>';
                $html .= '<div class="task-meta">';
                $html .= '<span>Projekt: ' . esc_html($project ? $project->post_title : 'Unbekannt') . '</span>';
                $html .= '<span>Prioritaet: <span class="priority-badge priority-' . esc_attr($priority) . '">' . $this->get_priority_label($priority) . '</span></span>';

                if ($due_date) {
                    $formatted_date = date_i18n('d.m.Y', strtotime($due_date));
                    if ($is_overdue) {
                        $html .= '<span class="overdue">Faellig: ' . $formatted_date . ' (ueberfaellig!)</span>';
                    } else {
                        $html .= '<span>Faellig: ' . $formatted_date . '</span>';
                    }
                }

                $html .= '</div>';

                if ($token) {
                    $token_url = add_query_arg('pm_token', $token, home_url('/'));
                    $html .= '<a href="' . esc_url($token_url) . '" class="task-link">Aufgabe anzeigen &amp; abschliessen</a>';
                }

                $html .= '</div>';
            }

            $html .= '</div>';

            $html .= '<div class="footer">';
            $html .= '<p>Diese E-Mail wurde automatisch vom Projektmanagement-System generiert.</p>';
            $html .= '<p>' . get_bloginfo('name') . '</p>';
            $html .= '</div>';

            $html .= '</div></body></html>';

            return $html;
        }

        /**
         * Send task assignment notification
         *
         * @param int $task_id Task post ID
         * @param int $assignee_id Assigned user ID
         * @return bool Success
         */
        public function send_task_assigned($task_id, $assignee_id) {
            $user = get_userdata($assignee_id);
            if (!$user || empty($user->user_email)) {
                return false;
            }

            $task = get_post($task_id);
            if (!$task) {
                return false;
            }

            $project_id = get_post_meta($task_id, '_pm_project_id', true);
            $project = get_post($project_id);
            $priority = get_post_meta($task_id, '_pm_priority', true) ?: 'medium';
            $due_date = get_post_meta($task_id, '_pm_due_date', true);
            $token = get_post_meta($task_id, '_pm_access_token', true);

            $subject = 'Neue Aufgabe zugewiesen: ' . $task->post_title;

            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
            $html .= '<style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .task-box { background: #f9f9f9; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa; }
                .task-link { display: inline-block; margin-top: 15px; padding: 10px 20px; background: #0073aa; color: white !important; text-decoration: none; border-radius: 3px; }
            </style></head><body>';

            $html .= '<h2>Hallo ' . esc_html($user->display_name) . ',</h2>';
            $html .= '<p>Ihnen wurde eine neue Aufgabe zugewiesen:</p>';

            $html .= '<div class="task-box">';
            $html .= '<p><strong>Aufgabe:</strong> ' . esc_html($task->post_title) . '</p>';
            $html .= '<p><strong>Projekt:</strong> ' . esc_html($project ? $project->post_title : 'Unbekannt') . '</p>';
            $html .= '<p><strong>Prioritaet:</strong> ' . $this->get_priority_label($priority) . '</p>';

            if ($due_date) {
                $html .= '<p><strong>Faellig:</strong> ' . date_i18n('d.m.Y', strtotime($due_date)) . '</p>';
            }

            if ($task->post_content) {
                $html .= '<p><strong>Beschreibung:</strong><br>' . nl2br(esc_html($task->post_content)) . '</p>';
            }

            $html .= '</div>';

            if ($token) {
                $token_url = add_query_arg('pm_token', $token, home_url('/'));
                $html .= '<p><a href="' . esc_url($token_url) . '" class="task-link">Aufgabe anzeigen &amp; bearbeiten</a></p>';
                $html .= '<p><small>Mit diesem Link koennen Sie die Aufgabe ansehen, kommentieren und abschliessen - ohne Anmeldung.</small></p>';
            }

            $html .= '</body></html>';

            $headers = [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
            ];

            return wp_mail($user->user_email, $subject, $html, $headers);
        }

        /**
         * Send task completed notification to project manager
         *
         * @param int $task_id Task post ID
         * @param int $completed_by User ID who completed the task
         * @return bool Success
         */
        public function send_task_completed($task_id, $completed_by) {
            $task = get_post($task_id);
            if (!$task) {
                return false;
            }

            $project_id = get_post_meta($task_id, '_pm_project_id', true);
            $project = get_post($project_id);

            // Get project creator (manager)
            if ($project) {
                $manager = get_userdata($project->post_author);
            } else {
                return false;
            }

            if (!$manager || empty($manager->user_email)) {
                return false;
            }

            $completer = get_userdata($completed_by);
            $completer_name = $completer ? $completer->display_name : 'Unbekannt';

            $subject = 'Aufgabe abgeschlossen: ' . $task->post_title;

            $body = "Hallo " . $manager->display_name . ",\n\n";
            $body .= "Die folgende Aufgabe wurde abgeschlossen:\n\n";
            $body .= "Aufgabe: " . $task->post_title . "\n";
            $body .= "Projekt: " . ($project ? $project->post_title : 'Unbekannt') . "\n";
            $body .= "Abgeschlossen von: " . $completer_name . "\n";
            $body .= "Datum: " . date_i18n('d.m.Y H:i') . "\n\n";
            $body .= "Mit freundlichen Gruessen\n";
            $body .= get_bloginfo('name');

            return wp_mail($manager->user_email, $subject, $body);
        }

        /**
         * Get priority label in German
         *
         * @param string $priority Priority key
         * @return string Label
         */
        private function get_priority_label($priority) {
            $labels = [
                'high'   => 'Hoch',
                'medium' => 'Mittel',
                'low'    => 'Niedrig',
            ];
            return $labels[$priority] ?? 'Mittel';
        }
    }
}
