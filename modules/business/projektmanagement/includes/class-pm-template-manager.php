<?php
/**
 * Template Manager for Projektmanagement
 * Handles project and task templates
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('PM_Template_Manager')) {

    class PM_Template_Manager {

        /**
         * Get all project templates
         *
         * @return array Array of template posts
         */
        public function get_all_templates() {
            return get_posts([
                'post_type'      => 'dgptm_proj_template',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'title',
                'order'          => 'ASC',
            ]);
        }

        /**
         * Get template by ID
         *
         * @param int $template_id Template post ID
         * @return WP_Post|null
         */
        public function get_template($template_id) {
            return get_post($template_id);
        }

        /**
         * Get task templates for a project template
         *
         * @param int $template_id Project template ID
         * @return array Array of task template posts
         */
        public function get_template_tasks($template_id) {
            return get_posts([
                'post_type'      => 'dgptm_task_template',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'meta_key'       => '_pm_template_parent',
                'meta_value'     => $template_id,
                'orderby'        => 'meta_value_num',
                'meta_key'       => '_pm_order',
                'order'          => 'ASC',
            ]);
        }

        /**
         * Create a new project template
         *
         * @param string $title Template title
         * @param string $description Template description
         * @param array $tasks Array of task definitions
         * @return int|WP_Error Template ID or error
         */
        public function create_template($title, $description, $tasks = []) {
            $template_id = wp_insert_post([
                'post_type'    => 'dgptm_proj_template',
                'post_title'   => sanitize_text_field($title),
                'post_content' => wp_kses_post($description),
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
            ]);

            if (is_wp_error($template_id)) {
                return $template_id;
            }

            // Create task templates
            $order = 0;
            foreach ($tasks as $task) {
                $this->add_task_to_template($template_id, $task, $order);
                $order++;
            }

            return $template_id;
        }

        /**
         * Update a project template
         *
         * @param int $template_id Template ID
         * @param string $title Template title
         * @param string $description Template description
         * @param array $tasks Array of task definitions
         * @return bool Success
         */
        public function update_template($template_id, $title, $description, $tasks = []) {
            $result = wp_update_post([
                'ID'           => $template_id,
                'post_title'   => sanitize_text_field($title),
                'post_content' => wp_kses_post($description),
            ]);

            if (is_wp_error($result)) {
                return false;
            }

            // Delete existing task templates
            $existing_tasks = $this->get_template_tasks($template_id);
            foreach ($existing_tasks as $task) {
                wp_delete_post($task->ID, true);
            }

            // Create new task templates
            $order = 0;
            foreach ($tasks as $task) {
                $this->add_task_to_template($template_id, $task, $order);
                $order++;
            }

            return true;
        }

        /**
         * Add a task to a template
         *
         * @param int $template_id Project template ID
         * @param array $task Task definition
         * @param int $order Sort order
         * @return int|WP_Error Task template ID or error
         */
        public function add_task_to_template($template_id, $task, $order = 0) {
            $task_template_id = wp_insert_post([
                'post_type'    => 'dgptm_task_template',
                'post_title'   => sanitize_text_field($task['title'] ?? ''),
                'post_content' => wp_kses_post($task['description'] ?? ''),
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
            ]);

            if (is_wp_error($task_template_id)) {
                return $task_template_id;
            }

            // Set meta
            update_post_meta($task_template_id, '_pm_template_parent', $template_id);
            update_post_meta($task_template_id, '_pm_default_priority', sanitize_text_field($task['priority'] ?? 'medium'));
            update_post_meta($task_template_id, '_pm_relative_due_days', intval($task['relative_due_days'] ?? 0));
            update_post_meta($task_template_id, '_pm_order', $order);

            return $task_template_id;
        }

        /**
         * Delete a project template and its task templates
         *
         * @param int $template_id Template ID
         * @return bool Success
         */
        public function delete_template($template_id) {
            // Delete task templates first
            $task_templates = $this->get_template_tasks($template_id);
            foreach ($task_templates as $task) {
                wp_delete_post($task->ID, true);
            }

            // Delete project template
            $result = wp_delete_post($template_id, true);
            return $result !== false;
        }

        /**
         * Create a project from a template
         *
         * @param int $template_id Template ID
         * @param string $project_title Project title
         * @param string $project_due_date Project due date (Y-m-d)
         * @param PM_Token_Manager $token_manager Token manager instance
         * @return int|WP_Error Project ID or error
         */
        public function create_project_from_template($template_id, $project_title, $project_due_date, $token_manager = null) {
            $template = $this->get_template($template_id);

            if (!$template) {
                return new WP_Error('invalid_template', 'Template nicht gefunden');
            }

            // Create project
            $project_id = wp_insert_post([
                'post_type'    => 'dgptm_project',
                'post_title'   => sanitize_text_field($project_title),
                'post_content' => $template->post_content,
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
            ]);

            if (is_wp_error($project_id)) {
                return $project_id;
            }

            // Set project meta
            update_post_meta($project_id, '_pm_status', 'active');
            update_post_meta($project_id, '_pm_due_date', sanitize_text_field($project_due_date));
            update_post_meta($project_id, '_pm_template_id', $template_id);
            update_post_meta($project_id, '_pm_created_by', get_current_user_id());

            // Calculate base date for relative due dates
            $base_date = strtotime($project_due_date);
            if (!$base_date) {
                $base_date = time();
            }

            // Create tasks from template
            $task_templates = $this->get_template_tasks($template_id);

            foreach ($task_templates as $task_template) {
                $relative_days = intval(get_post_meta($task_template->ID, '_pm_relative_due_days', true));
                $task_due_date = date('Y-m-d', strtotime("-{$relative_days} days", $base_date));

                $task_id = wp_insert_post([
                    'post_type'    => 'dgptm_task',
                    'post_title'   => $task_template->post_title,
                    'post_content' => $task_template->post_content,
                    'post_status'  => 'publish',
                    'post_author'  => get_current_user_id(),
                ]);

                if (!is_wp_error($task_id)) {
                    update_post_meta($task_id, '_pm_project_id', $project_id);
                    update_post_meta($task_id, '_pm_priority', get_post_meta($task_template->ID, '_pm_default_priority', true) ?: 'medium');
                    update_post_meta($task_id, '_pm_due_date', $task_due_date);
                    update_post_meta($task_id, '_pm_status', 'pending');
                    update_post_meta($task_id, '_pm_order', get_post_meta($task_template->ID, '_pm_order', true));
                    // Assignee will be set later by manager
                    update_post_meta($task_id, '_pm_assignee', 0);
                }
            }

            return $project_id;
        }

        /**
         * Get template data for frontend display
         *
         * @param int $template_id Template ID
         * @return array Template data with tasks
         */
        public function get_template_data($template_id) {
            $template = $this->get_template($template_id);

            if (!$template) {
                return null;
            }

            $task_templates = $this->get_template_tasks($template_id);
            $tasks = [];

            foreach ($task_templates as $task) {
                $tasks[] = [
                    'id'               => $task->ID,
                    'title'            => $task->post_title,
                    'description'      => $task->post_content,
                    'priority'         => get_post_meta($task->ID, '_pm_default_priority', true) ?: 'medium',
                    'relative_due_days' => intval(get_post_meta($task->ID, '_pm_relative_due_days', true)),
                    'order'            => intval(get_post_meta($task->ID, '_pm_order', true)),
                ];
            }

            // Sort by order
            usort($tasks, function($a, $b) {
                return $a['order'] - $b['order'];
            });

            return [
                'id'          => $template->ID,
                'title'       => $template->post_title,
                'description' => $template->post_content,
                'tasks'       => $tasks,
                'task_count'  => count($tasks),
            ];
        }
    }
}
