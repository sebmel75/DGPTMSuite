<?php
/**
 * Plugin Name: DGPTM - Projektmanagement
 * Description: Projekt- und Aufgabenverwaltung mit Vorlagen, tokenbasiertem Zugang und taeglichen E-Mail-Benachrichtigungen
 * Version: 1.0.0
 * Author: Sebastian Melzer
 * Author URI: https://dgptm.de
 * License: GPL2
 */

if (!defined('ABSPATH')) {
    exit;
}

// Prevent class redeclaration
if (!class_exists('DGPTM_Projektmanagement')) {

    class DGPTM_Projektmanagement {

        private static $instance = null;
        private $plugin_path;
        private $plugin_url;

        // Sub-components
        private $token_manager;
        private $email_handler;
        private $cron_handler;
        private $permissions;
        private $template_manager;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            $this->plugin_path = plugin_dir_path(__FILE__);
            $this->plugin_url = plugin_dir_url(__FILE__);

            // Load includes
            $this->load_includes();

            // Initialize components
            $this->init_components();

            // Register hooks
            add_action('init', [$this, 'register_post_types']);
            add_action('init', [$this, 'handle_token_access']);

            // Shortcodes
            add_shortcode('dgptm-projektmanagement', [$this, 'shortcode_main']);
            add_shortcode('dgptm-meine-aufgaben', [$this, 'shortcode_my_tasks']);
            add_shortcode('dgptm-projekt-templates', [$this, 'shortcode_templates']);

            // Project AJAX handlers
            add_action('wp_ajax_pm_create_project', [$this, 'ajax_create_project']);
            add_action('wp_ajax_pm_update_project', [$this, 'ajax_update_project']);
            add_action('wp_ajax_pm_delete_project', [$this, 'ajax_delete_project']);
            add_action('wp_ajax_pm_get_project_tasks', [$this, 'ajax_get_project_tasks']);

            // Task AJAX handlers
            add_action('wp_ajax_pm_create_task', [$this, 'ajax_create_task']);
            add_action('wp_ajax_pm_update_task', [$this, 'ajax_update_task']);
            add_action('wp_ajax_pm_complete_task', [$this, 'ajax_complete_task']);
            add_action('wp_ajax_pm_reopen_task', [$this, 'ajax_reopen_task']);
            add_action('wp_ajax_pm_add_comment', [$this, 'ajax_add_comment']);
            add_action('wp_ajax_pm_upload_attachment', [$this, 'ajax_upload_attachment']);
            add_action('wp_ajax_pm_delete_attachment', [$this, 'ajax_delete_attachment']);
            add_action('wp_ajax_pm_get_task_details', [$this, 'ajax_get_task_details']);

            // Template AJAX handlers
            add_action('wp_ajax_pm_save_template', [$this, 'ajax_save_template']);
            add_action('wp_ajax_pm_delete_template', [$this, 'ajax_delete_template']);
            add_action('wp_ajax_pm_create_from_template', [$this, 'ajax_create_from_template']);
            add_action('wp_ajax_pm_get_template', [$this, 'ajax_get_template']);

            // Token-based AJAX (no login required)
            add_action('wp_ajax_nopriv_pm_token_complete', [$this, 'ajax_token_complete']);
            add_action('wp_ajax_nopriv_pm_token_comment', [$this, 'ajax_token_comment']);
            add_action('wp_ajax_pm_token_complete', [$this, 'ajax_token_complete']);
            add_action('wp_ajax_pm_token_comment', [$this, 'ajax_token_comment']);

            // Utility AJAX
            add_action('wp_ajax_pm_get_users', [$this, 'ajax_get_users']);
            add_action('wp_ajax_pm_trigger_daily_emails', [$this, 'ajax_trigger_daily_emails']);

            // Assets
            add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);

            // User profile fields
            add_action('show_user_profile', [$this, 'show_user_fields']);
            add_action('edit_user_profile', [$this, 'show_user_fields']);
            add_action('personal_options_update', [$this, 'save_user_fields']);
            add_action('edit_user_profile_update', [$this, 'save_user_fields']);
        }

        private function load_includes() {
            require_once $this->plugin_path . 'includes/class-pm-post-types.php';
            require_once $this->plugin_path . 'includes/class-pm-token-manager.php';
            require_once $this->plugin_path . 'includes/class-pm-email-handler.php';
            require_once $this->plugin_path . 'includes/class-pm-cron-handler.php';
            require_once $this->plugin_path . 'includes/class-pm-permissions.php';
            require_once $this->plugin_path . 'includes/class-pm-template-manager.php';
        }

        private function init_components() {
            $this->token_manager = new PM_Token_Manager();
            $this->email_handler = new PM_Email_Handler();
            $this->cron_handler = new PM_Cron_Handler($this->email_handler);
            $this->permissions = new PM_Permissions();
            $this->template_manager = new PM_Template_Manager();
        }

        public function register_post_types() {
            PM_Post_Types::register();
        }

        /**
         * Enqueue frontend assets
         */
        public function enqueue_assets() {
            global $post;

            if (!is_a($post, 'WP_Post')) {
                return;
            }

            $has_shortcode = has_shortcode($post->post_content, 'dgptm-projektmanagement')
                || has_shortcode($post->post_content, 'dgptm-meine-aufgaben')
                || has_shortcode($post->post_content, 'dgptm-projekt-templates')
                || isset($_GET['pm_token']);

            if (!$has_shortcode) {
                return;
            }

            wp_enqueue_style(
                'pm-styles',
                $this->plugin_url . 'assets/css/projektmanagement.css',
                [],
                '1.0.0'
            );

            wp_enqueue_script(
                'pm-script',
                $this->plugin_url . 'assets/js/projektmanagement.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('pm-script', 'pmData', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce'   => wp_create_nonce('pm_nonce'),
                'strings' => [
                    'confirmDelete'    => 'Wirklich loeschen?',
                    'confirmComplete'  => 'Aufgabe als erledigt markieren?',
                    'saving'           => 'Speichern...',
                    'loading'          => 'Laden...',
                    'error'            => 'Ein Fehler ist aufgetreten',
                    'success'          => 'Erfolgreich gespeichert',
                    'taskCompleted'    => 'Aufgabe abgeschlossen!',
                ],
            ]);
        }

        /**
         * Handle token-based access
         */
        public function handle_token_access() {
            if (!isset($_GET['pm_token'])) {
                return;
            }

            $token = sanitize_text_field($_GET['pm_token']);
            $task = $this->token_manager->verify_token($token);

            if (!$task) {
                return;
            }

            // Store task for later use in template
            $GLOBALS['pm_token_task'] = $task;
            $GLOBALS['pm_token'] = $token;

            // Override page content
            add_filter('the_content', [$this, 'render_token_page'], 999);
        }

        /**
         * Render token-based task page
         */
        public function render_token_page($content) {
            if (!isset($GLOBALS['pm_token_task'])) {
                return $content;
            }

            $task = $GLOBALS['pm_token_task'];
            $token = $GLOBALS['pm_token'];

            ob_start();
            include $this->plugin_path . 'templates/task-detail.php';
            return ob_get_clean();
        }

        // =====================================================
        // SHORTCODES
        // =====================================================

        /**
         * Main management shortcode [dgptm-projektmanagement]
         */
        public function shortcode_main($atts) {
            if (!is_user_logged_in()) {
                return '<div class="pm-notice pm-notice-error">Bitte melden Sie sich an, um das Projektmanagement zu nutzen.</div>';
            }

            $user_id = get_current_user_id();
            $is_manager = $this->permissions->is_projektmanager($user_id);

            ob_start();
            include $this->plugin_path . 'templates/frontend-main.php';
            return ob_get_clean();
        }

        /**
         * My tasks shortcode [dgptm-meine-aufgaben]
         */
        public function shortcode_my_tasks($atts) {
            if (!is_user_logged_in()) {
                return '<div class="pm-notice pm-notice-error">Bitte melden Sie sich an.</div>';
            }

            $user_id = get_current_user_id();
            $tasks = $this->get_user_tasks($user_id);
            $is_manager = $this->permissions->is_projektmanager($user_id);

            ob_start();
            include $this->plugin_path . 'templates/frontend-my-tasks.php';
            return ob_get_clean();
        }

        /**
         * Templates management shortcode [dgptm-projekt-templates]
         */
        public function shortcode_templates($atts) {
            if (!is_user_logged_in()) {
                return '<div class="pm-notice pm-notice-error">Bitte melden Sie sich an.</div>';
            }

            $user_id = get_current_user_id();

            if (!$this->permissions->is_projektmanager($user_id)) {
                return '<div class="pm-notice pm-notice-error">Zugriff verweigert. Sie benoetigen Projektmanager-Rechte.</div>';
            }

            $templates = $this->template_manager->get_all_templates();

            ob_start();
            include $this->plugin_path . 'templates/frontend-templates.php';
            return ob_get_clean();
        }

        // =====================================================
        // HELPER METHODS
        // =====================================================

        /**
         * Get tasks assigned to a user
         */
        public function get_user_tasks($user_id, $status = 'all') {
            $args = [
                'post_type'      => 'dgptm_task',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'meta_query'     => [
                    [
                        'key'   => '_pm_assignee',
                        'value' => $user_id,
                    ],
                ],
            ];

            if ($status !== 'all') {
                $args['meta_query'][] = [
                    'key'   => '_pm_status',
                    'value' => $status,
                ];
            }

            $tasks = get_posts($args);

            // Sort by due date
            usort($tasks, function($a, $b) {
                $date_a = get_post_meta($a->ID, '_pm_due_date', true);
                $date_b = get_post_meta($b->ID, '_pm_due_date', true);

                if (!$date_a && !$date_b) return 0;
                if (!$date_a) return 1;
                if (!$date_b) return -1;

                return strtotime($date_a) - strtotime($date_b);
            });

            return $tasks;
        }

        /**
         * Get tasks for a project
         */
        public function get_project_tasks($project_id) {
            return get_posts([
                'post_type'      => 'dgptm_task',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'meta_query'     => [
                    [
                        'key'   => '_pm_project_id',
                        'value' => $project_id,
                    ],
                ],
                'orderby'        => 'meta_value_num',
                'meta_key'       => '_pm_order',
                'order'          => 'ASC',
            ]);
        }

        /**
         * Get projects for a user (where they have tasks)
         */
        public function get_user_projects($user_id) {
            global $wpdb;

            // Get project IDs where user has tasks
            $project_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT DISTINCT pm.meta_value
                 FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->postmeta} pm2 ON pm.post_id = pm2.post_id
                 WHERE pm.meta_key = '_pm_project_id'
                 AND pm2.meta_key = '_pm_assignee'
                 AND pm2.meta_value = %d",
                $user_id
            ));

            if (empty($project_ids)) {
                return [];
            }

            return get_posts([
                'post_type'      => 'dgptm_project',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'post__in'       => $project_ids,
            ]);
        }

        /**
         * Get all projects (for managers)
         */
        public function get_all_projects($status = 'all') {
            $args = [
                'post_type'      => 'dgptm_project',
                'posts_per_page' => -1,
                'post_status'    => 'publish',
                'orderby'        => 'date',
                'order'          => 'DESC',
            ];

            if ($status !== 'all') {
                $args['meta_query'] = [
                    [
                        'key'   => '_pm_status',
                        'value' => $status,
                    ],
                ];
            }

            return get_posts($args);
        }

        // =====================================================
        // PROJECT AJAX HANDLERS
        // =====================================================

        public function ajax_create_project() {
            check_ajax_referer('pm_nonce', 'nonce');

            if (!$this->permissions->can_manage_projects()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $title = sanitize_text_field($_POST['title'] ?? '');
            $description = wp_kses_post($_POST['description'] ?? '');
            $due_date = sanitize_text_field($_POST['due_date'] ?? '');

            if (empty($title)) {
                wp_send_json_error(['message' => 'Titel erforderlich']);
            }

            $project_id = wp_insert_post([
                'post_type'    => 'dgptm_project',
                'post_title'   => $title,
                'post_content' => $description,
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
            ]);

            if (is_wp_error($project_id)) {
                wp_send_json_error(['message' => 'Fehler beim Erstellen']);
            }

            update_post_meta($project_id, '_pm_status', 'active');
            update_post_meta($project_id, '_pm_due_date', $due_date);
            update_post_meta($project_id, '_pm_created_by', get_current_user_id());

            wp_send_json_success([
                'project_id' => $project_id,
                'message'    => 'Projekt erstellt',
            ]);
        }

        public function ajax_update_project() {
            check_ajax_referer('pm_nonce', 'nonce');

            if (!$this->permissions->can_manage_projects()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $project_id = intval($_POST['project_id'] ?? 0);
            $title = sanitize_text_field($_POST['title'] ?? '');
            $description = wp_kses_post($_POST['description'] ?? '');
            $due_date = sanitize_text_field($_POST['due_date'] ?? '');
            $status = sanitize_text_field($_POST['status'] ?? 'active');

            if (!$project_id || empty($title)) {
                wp_send_json_error(['message' => 'Ungueltige Daten']);
            }

            wp_update_post([
                'ID'           => $project_id,
                'post_title'   => $title,
                'post_content' => $description,
            ]);

            update_post_meta($project_id, '_pm_due_date', $due_date);
            update_post_meta($project_id, '_pm_status', $status);

            wp_send_json_success(['message' => 'Projekt aktualisiert']);
        }

        public function ajax_delete_project() {
            check_ajax_referer('pm_nonce', 'nonce');

            if (!$this->permissions->can_manage_projects()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $project_id = intval($_POST['project_id'] ?? 0);

            if (!$project_id) {
                wp_send_json_error(['message' => 'Ungueltige Projekt-ID']);
            }

            // Delete all tasks first
            $tasks = $this->get_project_tasks($project_id);
            foreach ($tasks as $task) {
                wp_delete_post($task->ID, true);
            }

            // Delete project
            wp_delete_post($project_id, true);

            wp_send_json_success(['message' => 'Projekt geloescht']);
        }

        public function ajax_get_project_tasks() {
            check_ajax_referer('pm_nonce', 'nonce');

            $project_id = intval($_POST['project_id'] ?? 0);

            if (!$project_id) {
                wp_send_json_error(['message' => 'Ungueltige Projekt-ID']);
            }

            $tasks = $this->get_project_tasks($project_id);
            $task_data = [];

            foreach ($tasks as $task) {
                $assignee_id = get_post_meta($task->ID, '_pm_assignee', true);
                $assignee = get_userdata($assignee_id);

                $task_data[] = [
                    'id'          => $task->ID,
                    'title'       => $task->post_title,
                    'description' => $task->post_content,
                    'priority'    => get_post_meta($task->ID, '_pm_priority', true) ?: 'medium',
                    'due_date'    => get_post_meta($task->ID, '_pm_due_date', true),
                    'status'      => get_post_meta($task->ID, '_pm_status', true) ?: 'pending',
                    'assignee_id' => $assignee_id,
                    'assignee'    => $assignee ? $assignee->display_name : '',
                ];
            }

            wp_send_json_success(['tasks' => $task_data]);
        }

        // =====================================================
        // TASK AJAX HANDLERS
        // =====================================================

        public function ajax_create_task() {
            check_ajax_referer('pm_nonce', 'nonce');

            if (!$this->permissions->can_manage_projects()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $project_id = intval($_POST['project_id'] ?? 0);
            $title = sanitize_text_field($_POST['title'] ?? '');
            $description = wp_kses_post($_POST['description'] ?? '');
            $priority = sanitize_text_field($_POST['priority'] ?? 'medium');
            $due_date = sanitize_text_field($_POST['due_date'] ?? '');
            $assignee = intval($_POST['assignee'] ?? 0);

            if (!$project_id || empty($title)) {
                wp_send_json_error(['message' => 'Projekt-ID und Titel erforderlich']);
            }

            // Validate priority
            if (!in_array($priority, ['high', 'medium', 'low'])) {
                $priority = 'medium';
            }

            $task_id = wp_insert_post([
                'post_type'    => 'dgptm_task',
                'post_title'   => $title,
                'post_content' => $description,
                'post_status'  => 'publish',
                'post_author'  => get_current_user_id(),
            ]);

            if (is_wp_error($task_id)) {
                wp_send_json_error(['message' => 'Fehler beim Erstellen']);
            }

            // Set meta
            update_post_meta($task_id, '_pm_project_id', $project_id);
            update_post_meta($task_id, '_pm_assignee', $assignee);
            update_post_meta($task_id, '_pm_priority', $priority);
            update_post_meta($task_id, '_pm_due_date', $due_date);
            update_post_meta($task_id, '_pm_status', 'pending');

            // Get next order number
            $tasks = $this->get_project_tasks($project_id);
            $max_order = 0;
            foreach ($tasks as $t) {
                $order = intval(get_post_meta($t->ID, '_pm_order', true));
                if ($order > $max_order) {
                    $max_order = $order;
                }
            }
            update_post_meta($task_id, '_pm_order', $max_order + 1);

            // Generate token if assignee is set
            $token = null;
            if ($assignee) {
                $token = $this->token_manager->generate_token($task_id, $assignee);

                // Send assignment notification
                $this->email_handler->send_task_assigned($task_id, $assignee);
            }

            wp_send_json_success([
                'task_id' => $task_id,
                'token'   => $token,
                'message' => 'Aufgabe erstellt',
            ]);
        }

        public function ajax_update_task() {
            check_ajax_referer('pm_nonce', 'nonce');

            $task_id = intval($_POST['task_id'] ?? 0);

            if (!$task_id) {
                wp_send_json_error(['message' => 'Ungueltige Aufgaben-ID']);
            }

            if (!$this->permissions->can_edit_task($task_id)) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $title = sanitize_text_field($_POST['title'] ?? '');
            $description = wp_kses_post($_POST['description'] ?? '');
            $priority = sanitize_text_field($_POST['priority'] ?? 'medium');
            $due_date = sanitize_text_field($_POST['due_date'] ?? '');
            $assignee = intval($_POST['assignee'] ?? 0);

            // Check if assignee changed
            $old_assignee = intval(get_post_meta($task_id, '_pm_assignee', true));
            $assignee_changed = ($old_assignee !== $assignee && $assignee > 0);

            wp_update_post([
                'ID'           => $task_id,
                'post_title'   => $title,
                'post_content' => $description,
            ]);

            if (!in_array($priority, ['high', 'medium', 'low'])) {
                $priority = 'medium';
            }

            update_post_meta($task_id, '_pm_priority', $priority);
            update_post_meta($task_id, '_pm_due_date', $due_date);
            update_post_meta($task_id, '_pm_assignee', $assignee);

            // Generate new token and notify if assignee changed
            if ($assignee_changed) {
                $this->token_manager->regenerate_token($task_id);
                $this->email_handler->send_task_assigned($task_id, $assignee);
            }

            wp_send_json_success(['message' => 'Aufgabe aktualisiert']);
        }

        public function ajax_complete_task() {
            check_ajax_referer('pm_nonce', 'nonce');

            $task_id = intval($_POST['task_id'] ?? 0);

            if (!$task_id) {
                wp_send_json_error(['message' => 'Ungueltige Aufgaben-ID']);
            }

            $user_id = get_current_user_id();

            if (!$this->permissions->can_complete_task($task_id, $user_id)) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            update_post_meta($task_id, '_pm_status', 'completed');
            update_post_meta($task_id, '_pm_completed_date', current_time('mysql'));
            update_post_meta($task_id, '_pm_completed_by', $user_id);

            // Invalidate token
            $this->token_manager->invalidate_token($task_id);

            // Notify manager
            $this->email_handler->send_task_completed($task_id, $user_id);

            wp_send_json_success(['message' => 'Aufgabe abgeschlossen']);
        }

        public function ajax_reopen_task() {
            check_ajax_referer('pm_nonce', 'nonce');

            if (!$this->permissions->can_manage_projects()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $task_id = intval($_POST['task_id'] ?? 0);

            if (!$task_id) {
                wp_send_json_error(['message' => 'Ungueltige Aufgaben-ID']);
            }

            update_post_meta($task_id, '_pm_status', 'pending');
            delete_post_meta($task_id, '_pm_completed_date');
            delete_post_meta($task_id, '_pm_completed_by');

            // Regenerate token
            $assignee = get_post_meta($task_id, '_pm_assignee', true);
            if ($assignee) {
                $this->token_manager->regenerate_token($task_id);
            }

            wp_send_json_success(['message' => 'Aufgabe wiedereroeffnet']);
        }

        public function ajax_add_comment() {
            check_ajax_referer('pm_nonce', 'nonce');

            $task_id = intval($_POST['task_id'] ?? 0);
            $comment_text = sanitize_textarea_field($_POST['comment'] ?? '');

            if (!$task_id || empty($comment_text)) {
                wp_send_json_error(['message' => 'Aufgaben-ID und Kommentar erforderlich']);
            }

            $user_id = get_current_user_id();

            if (!$this->permissions->can_view_task($task_id, $user_id)) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $user = get_userdata($user_id);

            $comment_id = wp_insert_comment([
                'comment_post_ID' => $task_id,
                'comment_content' => $comment_text,
                'comment_author'  => $user ? $user->display_name : 'Unbekannt',
                'comment_author_email' => $user ? $user->user_email : '',
                'user_id'         => $user_id,
                'comment_approved' => 1,
            ]);

            if (!$comment_id) {
                wp_send_json_error(['message' => 'Fehler beim Speichern']);
            }

            wp_send_json_success([
                'comment_id' => $comment_id,
                'message'    => 'Kommentar hinzugefuegt',
            ]);
        }

        public function ajax_upload_attachment() {
            check_ajax_referer('pm_nonce', 'nonce');

            $task_id = intval($_POST['task_id'] ?? 0);

            if (!$task_id) {
                wp_send_json_error(['message' => 'Ungueltige Aufgaben-ID']);
            }

            if (!$this->permissions->can_view_task($task_id)) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            if (empty($_FILES['file'])) {
                wp_send_json_error(['message' => 'Keine Datei hochgeladen']);
            }

            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';

            $attachment_id = media_handle_upload('file', $task_id);

            if (is_wp_error($attachment_id)) {
                wp_send_json_error(['message' => $attachment_id->get_error_message()]);
            }

            // Add to task attachments
            $attachments = get_post_meta($task_id, '_pm_attachments', true) ?: [];
            $attachments[] = $attachment_id;
            update_post_meta($task_id, '_pm_attachments', $attachments);

            wp_send_json_success([
                'attachment_id' => $attachment_id,
                'url'           => wp_get_attachment_url($attachment_id),
                'filename'      => basename(get_attached_file($attachment_id)),
                'message'       => 'Datei hochgeladen',
            ]);
        }

        public function ajax_delete_attachment() {
            check_ajax_referer('pm_nonce', 'nonce');

            if (!$this->permissions->can_manage_projects()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $task_id = intval($_POST['task_id'] ?? 0);
            $attachment_id = intval($_POST['attachment_id'] ?? 0);

            if (!$task_id || !$attachment_id) {
                wp_send_json_error(['message' => 'Ungueltige IDs']);
            }

            // Remove from task attachments
            $attachments = get_post_meta($task_id, '_pm_attachments', true) ?: [];
            $attachments = array_diff($attachments, [$attachment_id]);
            update_post_meta($task_id, '_pm_attachments', array_values($attachments));

            // Delete attachment
            wp_delete_attachment($attachment_id, true);

            wp_send_json_success(['message' => 'Datei geloescht']);
        }

        public function ajax_get_task_details() {
            check_ajax_referer('pm_nonce', 'nonce');

            $task_id = intval($_POST['task_id'] ?? 0);

            if (!$task_id) {
                wp_send_json_error(['message' => 'Ungueltige Aufgaben-ID']);
            }

            if (!$this->permissions->can_view_task($task_id)) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $task = get_post($task_id);

            if (!$task) {
                wp_send_json_error(['message' => 'Aufgabe nicht gefunden']);
            }

            $assignee_id = get_post_meta($task_id, '_pm_assignee', true);
            $assignee = get_userdata($assignee_id);
            $project_id = get_post_meta($task_id, '_pm_project_id', true);
            $project = get_post($project_id);

            // Get comments
            $comments = get_comments([
                'post_id' => $task_id,
                'status'  => 'approve',
                'order'   => 'ASC',
            ]);

            $comments_data = array_map(function($comment) {
                return [
                    'id'      => $comment->comment_ID,
                    'author'  => $comment->comment_author,
                    'content' => $comment->comment_content,
                    'date'    => date_i18n('d.m.Y H:i', strtotime($comment->comment_date)),
                ];
            }, $comments);

            // Get attachments
            $attachment_ids = get_post_meta($task_id, '_pm_attachments', true) ?: [];
            $attachments = [];

            foreach ($attachment_ids as $att_id) {
                $attachments[] = [
                    'id'       => $att_id,
                    'url'      => wp_get_attachment_url($att_id),
                    'filename' => basename(get_attached_file($att_id)),
                ];
            }

            wp_send_json_success([
                'task' => [
                    'id'          => $task->ID,
                    'title'       => $task->post_title,
                    'description' => $task->post_content,
                    'priority'    => get_post_meta($task_id, '_pm_priority', true) ?: 'medium',
                    'due_date'    => get_post_meta($task_id, '_pm_due_date', true),
                    'status'      => get_post_meta($task_id, '_pm_status', true) ?: 'pending',
                    'assignee_id' => $assignee_id,
                    'assignee'    => $assignee ? $assignee->display_name : '',
                    'project_id'  => $project_id,
                    'project'     => $project ? $project->post_title : '',
                ],
                'comments'    => $comments_data,
                'attachments' => $attachments,
            ]);
        }

        // =====================================================
        // TOKEN-BASED AJAX HANDLERS
        // =====================================================

        public function ajax_token_complete() {
            $token = sanitize_text_field($_POST['token'] ?? '');

            if (empty($token)) {
                wp_send_json_error(['message' => 'Token erforderlich']);
            }

            $task = $this->token_manager->verify_token($token);

            if (!$task) {
                wp_send_json_error(['message' => 'Ungueltiger oder abgelaufener Token']);
            }

            $user_id = get_post_meta($task->ID, '_pm_token_user_id', true);

            update_post_meta($task->ID, '_pm_status', 'completed');
            update_post_meta($task->ID, '_pm_completed_date', current_time('mysql'));
            update_post_meta($task->ID, '_pm_completed_by', $user_id);

            // Invalidate token
            $this->token_manager->invalidate_token($task->ID);

            // Notify manager
            $this->email_handler->send_task_completed($task->ID, $user_id);

            wp_send_json_success(['message' => 'Aufgabe abgeschlossen']);
        }

        public function ajax_token_comment() {
            $token = sanitize_text_field($_POST['token'] ?? '');
            $comment_text = sanitize_textarea_field($_POST['comment'] ?? '');

            if (empty($token) || empty($comment_text)) {
                wp_send_json_error(['message' => 'Token und Kommentar erforderlich']);
            }

            $task = $this->token_manager->verify_token($token);

            if (!$task) {
                wp_send_json_error(['message' => 'Ungueltiger oder abgelaufener Token']);
            }

            $user_id = get_post_meta($task->ID, '_pm_token_user_id', true);
            $user = get_userdata($user_id);

            $comment_id = wp_insert_comment([
                'comment_post_ID' => $task->ID,
                'comment_content' => $comment_text,
                'comment_author'  => $user ? $user->display_name : 'Benutzer',
                'comment_author_email' => $user ? $user->user_email : '',
                'user_id'         => $user_id ?: 0,
                'comment_approved' => 1,
            ]);

            // Mark comment as via token
            if ($comment_id) {
                update_comment_meta($comment_id, '_pm_via_token', 1);
            }

            wp_send_json_success([
                'comment_id' => $comment_id,
                'message'    => 'Kommentar hinzugefuegt',
            ]);
        }

        // =====================================================
        // TEMPLATE AJAX HANDLERS
        // =====================================================

        public function ajax_save_template() {
            check_ajax_referer('pm_nonce', 'nonce');

            if (!$this->permissions->can_manage_templates()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $template_id = intval($_POST['template_id'] ?? 0);
            $title = sanitize_text_field($_POST['title'] ?? '');
            $description = wp_kses_post($_POST['description'] ?? '');
            $tasks = isset($_POST['tasks']) ? $_POST['tasks'] : [];

            if (empty($title)) {
                wp_send_json_error(['message' => 'Titel erforderlich']);
            }

            // Sanitize tasks
            $sanitized_tasks = [];
            foreach ($tasks as $task) {
                $sanitized_tasks[] = [
                    'title'            => sanitize_text_field($task['title'] ?? ''),
                    'description'      => wp_kses_post($task['description'] ?? ''),
                    'priority'         => sanitize_text_field($task['priority'] ?? 'medium'),
                    'relative_due_days' => intval($task['relative_due_days'] ?? 0),
                ];
            }

            if ($template_id) {
                // Update existing
                $result = $this->template_manager->update_template($template_id, $title, $description, $sanitized_tasks);
                if (!$result) {
                    wp_send_json_error(['message' => 'Fehler beim Aktualisieren']);
                }
            } else {
                // Create new
                $template_id = $this->template_manager->create_template($title, $description, $sanitized_tasks);
                if (is_wp_error($template_id)) {
                    wp_send_json_error(['message' => $template_id->get_error_message()]);
                }
            }

            wp_send_json_success([
                'template_id' => $template_id,
                'message'     => 'Vorlage gespeichert',
            ]);
        }

        public function ajax_delete_template() {
            check_ajax_referer('pm_nonce', 'nonce');

            if (!$this->permissions->can_manage_templates()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $template_id = intval($_POST['template_id'] ?? 0);

            if (!$template_id) {
                wp_send_json_error(['message' => 'Ungueltige Vorlagen-ID']);
            }

            $result = $this->template_manager->delete_template($template_id);

            if (!$result) {
                wp_send_json_error(['message' => 'Fehler beim Loeschen']);
            }

            wp_send_json_success(['message' => 'Vorlage geloescht']);
        }

        public function ajax_create_from_template() {
            check_ajax_referer('pm_nonce', 'nonce');

            if (!$this->permissions->can_manage_projects()) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $template_id = intval($_POST['template_id'] ?? 0);
            $project_title = sanitize_text_field($_POST['project_title'] ?? '');
            $project_due_date = sanitize_text_field($_POST['project_due_date'] ?? '');

            if (!$template_id || empty($project_title)) {
                wp_send_json_error(['message' => 'Vorlage und Projekttitel erforderlich']);
            }

            $project_id = $this->template_manager->create_project_from_template(
                $template_id,
                $project_title,
                $project_due_date,
                $this->token_manager
            );

            if (is_wp_error($project_id)) {
                wp_send_json_error(['message' => $project_id->get_error_message()]);
            }

            wp_send_json_success([
                'project_id' => $project_id,
                'message'    => 'Projekt aus Vorlage erstellt',
            ]);
        }

        public function ajax_get_template() {
            check_ajax_referer('pm_nonce', 'nonce');

            $template_id = intval($_POST['template_id'] ?? 0);

            if (!$template_id) {
                wp_send_json_error(['message' => 'Ungueltige Vorlagen-ID']);
            }

            $data = $this->template_manager->get_template_data($template_id);

            if (!$data) {
                wp_send_json_error(['message' => 'Vorlage nicht gefunden']);
            }

            wp_send_json_success(['template' => $data]);
        }

        // =====================================================
        // UTILITY AJAX HANDLERS
        // =====================================================

        public function ajax_get_users() {
            check_ajax_referer('pm_nonce', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Nicht angemeldet']);
            }

            $users = $this->permissions->get_assignable_users();

            wp_send_json_success(['users' => $users]);
        }

        public function ajax_trigger_daily_emails() {
            check_ajax_referer('pm_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $results = $this->cron_handler->trigger_manual();

            wp_send_json_success($results);
        }

        // =====================================================
        // USER PROFILE FIELDS
        // =====================================================

        public function show_user_fields($user) {
            if (!current_user_can('manage_options')) {
                return;
            }

            $is_manager = get_user_meta($user->ID, 'pm_is_projektmanager', true) === '1';
            ?>
            <h3>Projektmanagement</h3>
            <table class="form-table">
                <tr>
                    <th><label for="pm_is_projektmanager">Projektmanager</label></th>
                    <td>
                        <input type="checkbox" name="pm_is_projektmanager" id="pm_is_projektmanager" value="1" <?php checked($is_manager); ?>>
                        <span class="description">Benutzer kann Projekte und Aufgaben verwalten</span>
                    </td>
                </tr>
            </table>
            <?php
        }

        public function save_user_fields($user_id) {
            if (!current_user_can('manage_options')) {
                return;
            }

            $is_manager = isset($_POST['pm_is_projektmanager']) ? '1' : '0';
            update_user_meta($user_id, 'pm_is_projektmanager', $is_manager);
        }
    }
}

// Prevent double initialization
if (!isset($GLOBALS['dgptm_pm_initialized'])) {
    $GLOBALS['dgptm_pm_initialized'] = true;
    DGPTM_Projektmanagement::get_instance();
}
