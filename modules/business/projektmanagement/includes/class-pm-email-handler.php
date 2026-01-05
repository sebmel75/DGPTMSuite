<?php
/**
 * Email Handler for Projektmanagement
 * Handles customizable email templates and sending
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PM_Email_Handler')) {

    class PM_Email_Handler {

        private $templates_option = 'pm_email_templates';

        /**
         * Default email templates
         */
        private function get_default_templates() {
            return [
                'task_assigned' => [
                    'subject' => 'Neue Aufgabe zugewiesen: {task_title}',
                    'body' => '<h2>Hallo {assignee_name},</h2>
<p>Ihnen wurde eine neue Aufgabe zugewiesen:</p>
<div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-left: 4px solid #0073aa;">
<p><strong>Aufgabe:</strong> {task_title}</p>
<p><strong>Projekt:</strong> {project_name}</p>
<p><strong>Prioritaet:</strong> {priority}</p>
<p><strong>Faellig:</strong> {due_date}</p>
{task_description}
</div>
<p><a href="{token_url}" style="display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px;">Aufgabe anzeigen &amp; bearbeiten</a></p>
<p><small>Mit diesem Link koennen Sie die Aufgabe ansehen, kommentieren und abschliessen - ohne Anmeldung.</small></p>',
                    'enabled' => true,
                ],
                'task_completed' => [
                    'subject' => 'Aufgabe abgeschlossen: {task_title}',
                    'body' => '<h2>Hallo {manager_name},</h2>
<p>Die folgende Aufgabe wurde abgeschlossen:</p>
<div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-left: 4px solid #46b450;">
<p><strong>Aufgabe:</strong> {task_title}</p>
<p><strong>Projekt:</strong> {project_name}</p>
<p><strong>Abgeschlossen von:</strong> {completer_name}</p>
<p><strong>Datum:</strong> {completion_date}</p>
</div>',
                    'enabled' => true,
                ],
                'comment_added' => [
                    'subject' => 'Neuer Kommentar zu: {task_title}',
                    'body' => '<h2>Hallo {manager_name},</h2>
<p>Es wurde ein neuer Kommentar zu einer Aufgabe hinzugefuegt:</p>
<div style="background: #f9f9f9; padding: 20px; margin: 20px 0; border-left: 4px solid #f0ad4e;">
<p><strong>Aufgabe:</strong> {task_title}</p>
<p><strong>Projekt:</strong> {project_name}</p>
<p><strong>Kommentar von:</strong> {comment_author}</p>
<p><strong>Datum:</strong> {comment_date}</p>
<p><strong>Kommentar:</strong></p>
<blockquote style="margin: 10px 0; padding: 10px; background: #fff; border-left: 3px solid #ddd;">{comment_text}</blockquote>
</div>
<p><a href="{admin_task_url}" style="display: inline-block; padding: 10px 20px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px;">Aufgabe im System ansehen</a></p>',
                    'enabled' => true,
                ],
                'daily_summary' => [
                    'subject' => 'Ihre offenen Aufgaben - {date}',
                    'body' => '<h2>Guten Morgen, {user_name}!</h2>
<p>Sie haben <strong>{task_count} offene Aufgabe(n)</strong>:</p>
{task_list}
<div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; font-size: 12px;">
<p>Diese E-Mail wurde automatisch vom Projektmanagement-System generiert.</p>
<p>{site_name}</p>
</div>',
                    'enabled' => true,
                ],
            ];
        }

        /**
         * Get all email templates (merged with defaults)
         */
        public function get_templates() {
            $saved = get_option($this->templates_option, []);
            $defaults = $this->get_default_templates();

            // Merge saved with defaults
            foreach ($defaults as $key => $default) {
                if (!isset($saved[$key])) {
                    $saved[$key] = $default;
                } else {
                    // Ensure all keys exist
                    $saved[$key] = array_merge($default, $saved[$key]);
                }
            }

            return $saved;
        }

        /**
         * Get a single template
         */
        public function get_template($key) {
            $templates = $this->get_templates();
            return $templates[$key] ?? null;
        }

        /**
         * Save email templates
         */
        public function save_templates($templates) {
            return update_option($this->templates_option, $templates);
        }

        /**
         * Reset templates to defaults
         */
        public function reset_templates() {
            return update_option($this->templates_option, $this->get_default_templates());
        }

        /**
         * Get available placeholders with descriptions
         */
        public function get_placeholders() {
            return [
                'task_assigned' => [
                    '{task_title}' => 'Titel der Aufgabe',
                    '{task_description}' => 'Beschreibung der Aufgabe',
                    '{project_name}' => 'Name des Projekts',
                    '{assignee_name}' => 'Name des Zugewiesenen',
                    '{priority}' => 'Prioritaet (Hoch/Mittel/Niedrig)',
                    '{due_date}' => 'Faelligkeitsdatum',
                    '{token_url}' => 'Direktlink zur Aufgabe',
                    '{site_name}' => 'Name der Website',
                ],
                'task_completed' => [
                    '{task_title}' => 'Titel der Aufgabe',
                    '{project_name}' => 'Name des Projekts',
                    '{manager_name}' => 'Name des Projektmanagers',
                    '{completer_name}' => 'Name der Person, die abgeschlossen hat',
                    '{completion_date}' => 'Datum des Abschlusses',
                    '{site_name}' => 'Name der Website',
                ],
                'comment_added' => [
                    '{task_title}' => 'Titel der Aufgabe',
                    '{project_name}' => 'Name des Projekts',
                    '{manager_name}' => 'Name des Projektmanagers',
                    '{comment_author}' => 'Autor des Kommentars',
                    '{comment_text}' => 'Inhalt des Kommentars',
                    '{comment_date}' => 'Datum des Kommentars',
                    '{admin_task_url}' => 'Link zur Aufgabe im Admin',
                    '{site_name}' => 'Name der Website',
                ],
                'daily_summary' => [
                    '{user_name}' => 'Name des Empfaengers',
                    '{task_count}' => 'Anzahl offener Aufgaben',
                    '{task_list}' => 'Liste der Aufgaben (HTML)',
                    '{date}' => 'Aktuelles Datum',
                    '{site_name}' => 'Name der Website',
                ],
            ];
        }

        /**
         * Replace placeholders in template
         */
        private function replace_placeholders($text, $data) {
            foreach ($data as $key => $value) {
                $text = str_replace($key, $value, $text);
            }
            return $text;
        }

        /**
         * Wrap content in HTML email template
         */
        private function wrap_html($content) {
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8">';
            $html .= '<style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; }
                .container { max-width: 600px; margin: 0 auto; background: #fff; }
                h2 { color: #0073aa; }
                a { color: #0073aa; }
                blockquote { margin: 10px 0; padding: 10px 15px; background: #f9f9f9; border-left: 3px solid #0073aa; }
            </style>';
            $html .= '</head><body><div class="container">';
            $html .= $content;
            $html .= '</div></body></html>';
            return $html;
        }

        /**
         * Get email headers
         */
        private function get_headers() {
            return [
                'Content-Type: text/html; charset=UTF-8',
                'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
            ];
        }

        /**
         * Send task assignment notification
         */
        public function send_task_assigned($task_id, $assignee_id) {
            $template = $this->get_template('task_assigned');
            if (!$template || !$template['enabled']) {
                return false;
            }

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

            $description_html = '';
            if ($task->post_content) {
                $description_html = '<p><strong>Beschreibung:</strong><br>' . nl2br(esc_html($task->post_content)) . '</p>';
            }

            $token_url = $token ? add_query_arg('pm_token', $token, home_url('/')) : home_url('/');

            $data = [
                '{task_title}' => esc_html($task->post_title),
                '{task_description}' => $description_html,
                '{project_name}' => esc_html($project ? $project->post_title : 'Unbekannt'),
                '{assignee_name}' => esc_html($user->display_name),
                '{priority}' => $this->get_priority_label($priority),
                '{due_date}' => $due_date ? date_i18n('d.m.Y', strtotime($due_date)) : 'Nicht festgelegt',
                '{token_url}' => esc_url($token_url),
                '{site_name}' => get_bloginfo('name'),
            ];

            $subject = $this->replace_placeholders($template['subject'], $data);
            $body = $this->wrap_html($this->replace_placeholders($template['body'], $data));

            return wp_mail($user->user_email, $subject, $body, $this->get_headers());
        }

        /**
         * Send task completed notification to project manager
         */
        public function send_task_completed($task_id, $completed_by) {
            $template = $this->get_template('task_completed');
            if (!$template || !$template['enabled']) {
                return false;
            }

            $task = get_post($task_id);
            if (!$task) {
                return false;
            }

            $project_id = get_post_meta($task_id, '_pm_project_id', true);
            $project = get_post($project_id);

            // Get project creator (manager)
            if (!$project) {
                return false;
            }

            $manager = get_userdata($project->post_author);
            if (!$manager || empty($manager->user_email)) {
                return false;
            }

            $completer = get_userdata($completed_by);
            $completer_name = $completer ? $completer->display_name : 'Unbekannt';

            $data = [
                '{task_title}' => esc_html($task->post_title),
                '{project_name}' => esc_html($project->post_title),
                '{manager_name}' => esc_html($manager->display_name),
                '{completer_name}' => esc_html($completer_name),
                '{completion_date}' => date_i18n('d.m.Y H:i'),
                '{site_name}' => get_bloginfo('name'),
            ];

            $subject = $this->replace_placeholders($template['subject'], $data);
            $body = $this->wrap_html($this->replace_placeholders($template['body'], $data));

            return wp_mail($manager->user_email, $subject, $body, $this->get_headers());
        }

        /**
         * Send comment notification to project manager
         */
        public function send_comment_added($task_id, $comment_id, $commenter_id) {
            $template = $this->get_template('comment_added');
            if (!$template || !$template['enabled']) {
                return false;
            }

            $task = get_post($task_id);
            if (!$task) {
                return false;
            }

            $comment = get_comment($comment_id);
            if (!$comment) {
                return false;
            }

            $project_id = get_post_meta($task_id, '_pm_project_id', true);
            $project = get_post($project_id);

            if (!$project) {
                return false;
            }

            // Get project creator (manager)
            $manager = get_userdata($project->post_author);
            if (!$manager || empty($manager->user_email)) {
                return false;
            }

            // Don't notify manager about their own comments
            if ($commenter_id && (int)$commenter_id === (int)$project->post_author) {
                return false;
            }

            $commenter = get_userdata($commenter_id);
            $commenter_name = $commenter ? $commenter->display_name : $comment->comment_author;

            // Build admin task URL (link to page with shortcode)
            $admin_task_url = home_url('/');

            $data = [
                '{task_title}' => esc_html($task->post_title),
                '{project_name}' => esc_html($project->post_title),
                '{manager_name}' => esc_html($manager->display_name),
                '{comment_author}' => esc_html($commenter_name),
                '{comment_text}' => nl2br(esc_html($comment->comment_content)),
                '{comment_date}' => date_i18n('d.m.Y H:i', strtotime($comment->comment_date)),
                '{admin_task_url}' => esc_url($admin_task_url),
                '{site_name}' => get_bloginfo('name'),
            ];

            $subject = $this->replace_placeholders($template['subject'], $data);
            $body = $this->wrap_html($this->replace_placeholders($template['body'], $data));

            return wp_mail($manager->user_email, $subject, $body, $this->get_headers());
        }

        /**
         * Send daily task summary email to a user
         */
        public function send_daily_summary($user, $tasks) {
            if (empty($tasks)) {
                return false;
            }

            $template = $this->get_template('daily_summary');
            if (!$template || !$template['enabled']) {
                return false;
            }

            $task_list_html = $this->build_task_list_html($tasks);

            $data = [
                '{user_name}' => esc_html($user->display_name),
                '{task_count}' => count($tasks),
                '{task_list}' => $task_list_html,
                '{date}' => date_i18n('d.m.Y'),
                '{site_name}' => get_bloginfo('name'),
            ];

            $subject = $this->replace_placeholders($template['subject'], $data);
            $body = $this->wrap_html($this->replace_placeholders($template['body'], $data));

            return wp_mail($user->user_email, $subject, $body, $this->get_headers());
        }

        /**
         * Build task list HTML for daily summary
         */
        private function build_task_list_html($tasks) {
            $html = '<div class="task-list" style="margin: 20px 0;">';

            foreach ($tasks as $task) {
                $priority = get_post_meta($task->ID, '_pm_priority', true) ?: 'medium';
                $due_date = get_post_meta($task->ID, '_pm_due_date', true);
                $project_id = get_post_meta($task->ID, '_pm_project_id', true);
                $project = get_post($project_id);
                $token = get_post_meta($task->ID, '_pm_access_token', true);

                $is_overdue = $due_date && strtotime($due_date) < strtotime('today');

                $border_color = '#0073aa';
                if ($priority === 'high') $border_color = '#dc3232';
                elseif ($priority === 'low') $border_color = '#46b450';

                $html .= '<div style="background: #f9f9f9; padding: 15px; margin: 10px 0; border-left: 4px solid ' . $border_color . '; border-radius: 0 4px 4px 0;">';
                $html .= '<div style="font-weight: bold; font-size: 16px; margin-bottom: 5px;">' . esc_html($task->post_title) . '</div>';
                $html .= '<div style="color: #666; font-size: 13px;">';
                $html .= '<span style="margin-right: 15px;">Projekt: ' . esc_html($project ? $project->post_title : 'Unbekannt') . '</span>';
                $html .= '<span style="margin-right: 15px;">Prioritaet: ' . $this->get_priority_label($priority) . '</span>';

                if ($due_date) {
                    $formatted_date = date_i18n('d.m.Y', strtotime($due_date));
                    if ($is_overdue) {
                        $html .= '<span style="color: #dc3232; font-weight: bold;">Faellig: ' . $formatted_date . ' (ueberfaellig!)</span>';
                    } else {
                        $html .= '<span>Faellig: ' . $formatted_date . '</span>';
                    }
                }

                $html .= '</div>';

                if ($token) {
                    $token_url = add_query_arg('pm_token', $token, home_url('/'));
                    $html .= '<a href="' . esc_url($token_url) . '" style="display: inline-block; margin-top: 10px; padding: 8px 15px; background: #0073aa; color: white; text-decoration: none; border-radius: 3px; font-size: 14px;">Aufgabe anzeigen</a>';
                }

                $html .= '</div>';
            }

            $html .= '</div>';
            return $html;
        }

        /**
         * Get priority label in German
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
