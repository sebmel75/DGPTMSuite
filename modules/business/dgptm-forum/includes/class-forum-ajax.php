<?php
/**
 * DGPTM Forum – AJAX Dispatcher
 *
 * Central AJAX handler for all forum frontend and admin actions.
 *
 * @package DGPTM_Forum
 * @since   1.0.0
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('DGPTM_Forum_Ajax')) {

    class DGPTM_Forum_Ajax {

        /** @var self|null */
        private static $instance = null;

        /** @return self */
        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {}

        // =============================================================
        //  Dispatcher
        // =============================================================

        /**
         * Central dispatch method called by DGPTM_Forum::handle_ajax().
         *
         * 1. Reads action from POST/REQUEST
         * 2. Verifies nonce
         * 3. Checks login
         * 4. Strips prefix, routes to method
         */
        public function dispatch() {
            $action = isset($_POST['action']) ? $_POST['action'] : (isset($_REQUEST['action']) ? $_REQUEST['action'] : '');

            check_ajax_referer('dgptm_forum', 'nonce');

            if (!is_user_logged_in()) {
                wp_send_json_error(['message' => 'Nicht angemeldet.']);
            }

            // Strip the 'dgptm_forum_' prefix to get the method name.
            $method = str_replace('dgptm_forum_', '', $action);

            if (method_exists($this, $method)) {
                $this->$method();
            } else {
                wp_send_json_error(['message' => 'Unbekannte Aktion.']);
            }
        }

        // =============================================================
        //  Forum View Methods
        // =============================================================

        /**
         * Central view loader.
         *
         * POST params: view (ags|topics|threads|thread), id
         */
        private function load_view() {
            $view = isset($_POST['view']) ? sanitize_text_field($_POST['view']) : '';
            $id   = isset($_POST['id']) ? absint($_POST['id']) : 0;

            switch ($view) {
                case 'ags':
                    $html = $this->render_ags_view();
                    break;
                case 'topics':
                    $html = $this->render_topics_view($id);
                    break;
                case 'threads':
                    $html = $this->render_threads_view($id);
                    break;
                case 'thread':
                    $html = $this->render_thread_view($id);
                    break;
                default:
                    wp_send_json_error(['message' => 'Ungültige Ansicht.']);
                    return;
            }

            wp_send_json_success(['html' => $html]);
        }

        // ----- AG List View ------------------------------------------

        /**
         * Render the list of all AGs the current user can see,
         * plus an "Offene Themen" section.
         *
         * Breadcrumb: [Forum]
         */
        private function render_ags_view() {
            global $wpdb;

            $user_id = get_current_user_id();
            $prefix  = $wpdb->prefix . 'dgptm_forum_';
            $is_admin = DGPTM_Forum_Permissions::is_forum_admin($user_id);

            $ags = DGPTM_Forum_AG_Manager::get_all_ags('active');

            ob_start();
            ?>
            <nav class="dgptm-forum-breadcrumb">
                <span class="dgptm-forum-breadcrumb-item active">Forum</span>
            </nav>

            <div class="dgptm-forum-ag-list">
                <?php if (empty($ags)) : ?>
                    <p class="dgptm-forum-empty">Keine Arbeitsgemeinschaften vorhanden.</p>
                <?php else : ?>
                    <?php foreach ($ags as $ag) :
                        // Only show AGs where user is a member or is admin.
                        if (!$is_admin && !DGPTM_Forum_Permissions::is_ag_member($user_id, $ag->id)) {
                            continue;
                        }

                        // Topic count for this AG.
                        $topic_count = (int) $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$prefix}topics WHERE ag_id = %d AND status = 'active'",
                            $ag->id
                        ));

                        // Member count.
                        $member_count = (int) $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*) FROM {$prefix}ag_members WHERE ag_id = %d",
                            $ag->id
                        ));
                    ?>
                        <div class="dgptm-forum-ag-card" data-ag-id="<?php echo esc_attr($ag->id); ?>">
                            <div class="dgptm-forum-ag-header">
                                <h3 class="dgptm-forum-ag-name">
                                    <a href="#" class="dgptm-forum-nav" data-view="topics" data-id="<?php echo esc_attr($ag->id); ?>">
                                        <?php echo esc_html($ag->name); ?>
                                    </a>
                                </h3>
                            </div>
                            <?php if (!empty($ag->description)) : ?>
                                <p class="dgptm-forum-ag-desc"><?php echo esc_html($ag->description); ?></p>
                            <?php endif; ?>
                            <div class="dgptm-forum-ag-meta">
                                <span class="dgptm-forum-meta-item">
                                    <strong><?php echo esc_html($topic_count); ?></strong> <?php echo $topic_count === 1 ? 'Thema' : 'Themen'; ?>
                                </span>
                                <span class="dgptm-forum-meta-item">
                                    <strong><?php echo esc_html($member_count); ?></strong> <?php echo $member_count === 1 ? 'Mitglied' : 'Mitglieder'; ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <?php
            // "Offene Themen" section: topics with ag_id=NULL and access_mode='open'.
            $open_topics = $wpdb->get_results(
                "SELECT * FROM {$prefix}topics
                 WHERE ag_id IS NULL AND access_mode = 'open' AND status = 'active'
                 ORDER BY sort_order ASC, title ASC"
            );

            if (!empty($open_topics)) :
            ?>
                <div class="dgptm-forum-open-topics">
                    <h3 class="dgptm-forum-section-title">Offene Themen</h3>
                    <div class="dgptm-forum-topic-list">
                        <?php foreach ($open_topics as $topic) :
                            $thread_count = (int) $topic->thread_count;
                        ?>
                            <div class="dgptm-forum-topic-card" data-topic-id="<?php echo esc_attr($topic->id); ?>">
                                <div class="dgptm-forum-topic-header">
                                    <h4 class="dgptm-forum-topic-name">
                                        <a href="#" class="dgptm-forum-nav" data-view="threads" data-id="<?php echo esc_attr($topic->id); ?>">
                                            <?php echo esc_html($topic->title); ?>
                                        </a>
                                    </h4>
                                </div>
                                <?php if (!empty($topic->description)) : ?>
                                    <p class="dgptm-forum-topic-desc"><?php echo esc_html($topic->description); ?></p>
                                <?php endif; ?>
                                <div class="dgptm-forum-topic-meta">
                                    <span class="dgptm-forum-meta-item">
                                        <strong><?php echo esc_html($thread_count); ?></strong> <?php echo $thread_count === 1 ? 'Thread' : 'Threads'; ?>
                                    </span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php

            return ob_get_clean();
        }

        // ----- Topics View -------------------------------------------

        /**
         * Render topics for a given AG.
         *
         * Breadcrumb: [Forum > AG-Name]
         *
         * @param int $ag_id
         * @return string
         */
        private function render_topics_view($ag_id) {
            global $wpdb;

            $user_id = get_current_user_id();
            $prefix  = $wpdb->prefix . 'dgptm_forum_';

            $ag = DGPTM_Forum_AG_Manager::get_ag($ag_id);
            if (!$ag) {
                return '<p class="dgptm-forum-error">Arbeitsgemeinschaft nicht gefunden.</p>';
            }

            $topics = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$prefix}topics WHERE ag_id = %d AND status = 'active' ORDER BY sort_order ASC, title ASC",
                $ag_id
            ));

            ob_start();
            ?>
            <nav class="dgptm-forum-breadcrumb">
                <a href="#" class="dgptm-forum-breadcrumb-item dgptm-forum-nav" data-view="ags" data-id="0">Forum</a>
                <span class="dgptm-forum-breadcrumb-sep">&rsaquo;</span>
                <span class="dgptm-forum-breadcrumb-item active"><?php echo esc_html($ag->name); ?></span>
            </nav>

            <div class="dgptm-forum-topic-list">
                <?php if (empty($topics)) : ?>
                    <p class="dgptm-forum-empty">Keine Themen in dieser Arbeitsgemeinschaft.</p>
                <?php else : ?>
                    <?php foreach ($topics as $topic) :
                        if (!DGPTM_Forum_Permissions::can_view_topic($user_id, $topic->id)) {
                            continue;
                        }

                        $thread_count = (int) $topic->thread_count;
                        $responsible_name = '';
                        if (!empty($topic->responsible_id)) {
                            $resp_user = get_userdata($topic->responsible_id);
                            if ($resp_user) {
                                $responsible_name = $resp_user->display_name;
                            }
                        }

                        $last_activity = $topic->last_activity
                            ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($topic->last_activity))
                            : '-';
                    ?>
                        <div class="dgptm-forum-topic-card" data-topic-id="<?php echo esc_attr($topic->id); ?>">
                            <div class="dgptm-forum-topic-header">
                                <h4 class="dgptm-forum-topic-name">
                                    <a href="#" class="dgptm-forum-nav" data-view="threads" data-id="<?php echo esc_attr($topic->id); ?>">
                                        <?php echo esc_html($topic->title); ?>
                                    </a>
                                </h4>
                            </div>
                            <?php if (!empty($topic->description)) : ?>
                                <p class="dgptm-forum-topic-desc"><?php echo esc_html($topic->description); ?></p>
                            <?php endif; ?>
                            <div class="dgptm-forum-topic-meta">
                                <?php if (!empty($responsible_name)) : ?>
                                    <span class="dgptm-forum-meta-item">
                                        Verantwortlich: <strong><?php echo esc_html($responsible_name); ?></strong>
                                    </span>
                                <?php endif; ?>
                                <span class="dgptm-forum-meta-item">
                                    <strong><?php echo esc_html($thread_count); ?></strong> <?php echo $thread_count === 1 ? 'Thread' : 'Threads'; ?>
                                </span>
                                <span class="dgptm-forum-meta-item">
                                    Letzte Aktivit&auml;t: <?php echo esc_html($last_activity); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php

            return ob_get_clean();
        }

        // ----- Threads View ------------------------------------------

        /**
         * Render threads for a given topic.
         *
         * Breadcrumb: [Forum > AG-Name > Topic-Name]
         *
         * @param int $topic_id
         * @return string
         */
        private function render_threads_view($topic_id) {
            global $wpdb;

            $user_id = get_current_user_id();
            $prefix  = $wpdb->prefix . 'dgptm_forum_';

            if (!DGPTM_Forum_Permissions::can_view_topic($user_id, $topic_id)) {
                return '<p class="dgptm-forum-error">Kein Zugriff auf dieses Thema.</p>';
            }

            $topic = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}topics WHERE id = %d",
                $topic_id
            ));

            if (!$topic) {
                return '<p class="dgptm-forum-error">Thema nicht gefunden.</p>';
            }

            // AG info for breadcrumb.
            $ag_name = 'Forum';
            $ag_id   = 0;
            if (!empty($topic->ag_id)) {
                $ag = DGPTM_Forum_AG_Manager::get_ag($topic->ag_id);
                if ($ag) {
                    $ag_name = $ag->name;
                    $ag_id   = $ag->id;
                }
            }

            // Threads: pinned first, then by last_reply_at DESC.
            $threads = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$prefix}threads
                 WHERE topic_id = %d AND status != 'deleted'
                 ORDER BY is_pinned DESC, last_reply_at DESC, created_at DESC",
                $topic_id
            ));

            $can_post = DGPTM_Forum_Permissions::can_post_in_topic($user_id, $topic_id);

            ob_start();
            ?>
            <nav class="dgptm-forum-breadcrumb">
                <a href="#" class="dgptm-forum-breadcrumb-item dgptm-forum-nav" data-view="ags" data-id="0">Forum</a>
                <span class="dgptm-forum-breadcrumb-sep">&rsaquo;</span>
                <?php if ($ag_id > 0) : ?>
                    <a href="#" class="dgptm-forum-breadcrumb-item dgptm-forum-nav" data-view="topics" data-id="<?php echo esc_attr($ag_id); ?>">
                        <?php echo esc_html($ag_name); ?>
                    </a>
                    <span class="dgptm-forum-breadcrumb-sep">&rsaquo;</span>
                <?php endif; ?>
                <span class="dgptm-forum-breadcrumb-item active"><?php echo esc_html($topic->title); ?></span>
            </nav>

            <?php if ($can_post) : ?>
                <div class="dgptm-forum-actions">
                    <button type="button" class="dgptm-forum-btn dgptm-forum-btn-primary dgptm-forum-new-thread-btn" data-topic-id="<?php echo esc_attr($topic_id); ?>">
                        Neuer Thread
                    </button>
                </div>
                <div class="dgptm-forum-compose-area" id="dgptm-forum-compose-thread" style="display:none;">
                    <h4>Neuen Thread erstellen</h4>
                    <div class="dgptm-forum-form-group">
                        <label for="dgptm-forum-thread-title">Titel</label>
                        <input type="text" id="dgptm-forum-thread-title" class="dgptm-forum-input" placeholder="Thread-Titel" />
                    </div>
                    <div class="dgptm-forum-form-group">
                        <label for="dgptm-forum-thread-content">Inhalt</label>
                        <textarea id="dgptm-forum-thread-content" class="dgptm-forum-textarea" rows="6" placeholder="Schreiben Sie Ihren Beitrag..."></textarea>
                    </div>
                    <div class="dgptm-forum-form-group">
                        <label for="dgptm-forum-thread-files">Dateien anh&auml;ngen</label>
                        <input type="file" id="dgptm-forum-thread-files" multiple />
                    </div>
                    <div class="dgptm-forum-form-actions">
                        <button type="button" class="dgptm-forum-btn dgptm-forum-btn-primary dgptm-forum-submit-thread" data-topic-id="<?php echo esc_attr($topic_id); ?>">
                            Absenden
                        </button>
                        <button type="button" class="dgptm-forum-btn dgptm-forum-cancel-compose">Abbrechen</button>
                    </div>
                </div>
            <?php endif; ?>

            <div class="dgptm-forum-thread-list">
                <?php if (empty($threads)) : ?>
                    <p class="dgptm-forum-empty">Noch keine Threads in diesem Thema.</p>
                <?php else : ?>
                    <?php foreach ($threads as $thread) :
                        $author = get_userdata($thread->author_id);
                        $author_name = $author ? $author->display_name : 'Unbekannt';

                        $last_reply = $thread->last_reply_at
                            ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($thread->last_reply_at))
                            : date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($thread->created_at));
                    ?>
                        <div class="dgptm-forum-thread-card <?php echo $thread->is_pinned ? 'dgptm-forum-thread-pinned' : ''; ?> <?php echo $thread->status === 'closed' ? 'dgptm-forum-thread-closed' : ''; ?>"
                             data-thread-id="<?php echo esc_attr($thread->id); ?>">
                            <div class="dgptm-forum-thread-header">
                                <h4 class="dgptm-forum-thread-title">
                                    <?php if ($thread->is_pinned) : ?>
                                        <span class="dgptm-forum-badge dgptm-forum-badge-pinned" title="Angepinnt">&#128204;</span>
                                    <?php endif; ?>
                                    <?php if ($thread->status === 'closed') : ?>
                                        <span class="dgptm-forum-badge dgptm-forum-badge-closed" title="Geschlossen">&#128274;</span>
                                    <?php endif; ?>
                                    <a href="#" class="dgptm-forum-nav" data-view="thread" data-id="<?php echo esc_attr($thread->id); ?>">
                                        <?php echo esc_html($thread->title); ?>
                                    </a>
                                </h4>
                            </div>
                            <div class="dgptm-forum-thread-meta">
                                <span class="dgptm-forum-meta-item">
                                    Von: <strong><?php echo esc_html($author_name); ?></strong>
                                </span>
                                <span class="dgptm-forum-meta-item">
                                    <strong><?php echo esc_html((int) $thread->reply_count); ?></strong> <?php echo (int) $thread->reply_count === 1 ? 'Antwort' : 'Antworten'; ?>
                                </span>
                                <span class="dgptm-forum-meta-item">
                                    Letzte Aktivit&auml;t: <?php echo esc_html($last_reply); ?>
                                </span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php

            return ob_get_clean();
        }

        // ----- Single Thread View ------------------------------------

        /**
         * Render a single thread with all replies (nested).
         *
         * Breadcrumb: [Forum > AG > Topic > Thread-Title]
         *
         * @param int $thread_id
         * @return string
         */
        private function render_thread_view($thread_id) {
            global $wpdb;

            $user_id = get_current_user_id();
            $prefix  = $wpdb->prefix . 'dgptm_forum_';

            $thread = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}threads WHERE id = %d",
                $thread_id
            ));

            if (!$thread || $thread->status === 'deleted') {
                return '<p class="dgptm-forum-error">Thread nicht gefunden.</p>';
            }

            if (!DGPTM_Forum_Permissions::can_view_topic($user_id, $thread->topic_id)) {
                return '<p class="dgptm-forum-error">Kein Zugriff auf diesen Thread.</p>';
            }

            // Topic info.
            $topic = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}topics WHERE id = %d",
                $thread->topic_id
            ));

            // AG info for breadcrumb.
            $ag_name = '';
            $ag_id   = 0;
            if ($topic && !empty($topic->ag_id)) {
                $ag = DGPTM_Forum_AG_Manager::get_ag($topic->ag_id);
                if ($ag) {
                    $ag_name = $ag->name;
                    $ag_id   = $ag->id;
                }
            }

            $can_post      = DGPTM_Forum_Permissions::can_post_in_topic($user_id, $thread->topic_id);
            $can_moderate  = DGPTM_Forum_Permissions::can_moderate_topic($user_id, $thread->topic_id);
            $thread_author = get_userdata($thread->author_id);
            $thread_author_name = $thread_author ? $thread_author->display_name : 'Unbekannt';
            $thread_date   = date_i18n(
                get_option('date_format') . ' ' . get_option('time_format'),
                strtotime($thread->created_at)
            );

            // Load all replies for this thread.
            $replies = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$prefix}replies WHERE thread_id = %d AND status != 'deleted' ORDER BY parent_id ASC, created_at ASC",
                $thread_id
            ));

            // Load attachments for thread.
            $thread_attachments = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM {$prefix}attachments WHERE post_type = 'thread' AND post_id = %d",
                $thread_id
            ));

            // Load attachments for all replies keyed by reply id.
            $reply_ids = wp_list_pluck($replies, 'id');
            $reply_attachments = [];
            if (!empty($reply_ids)) {
                $placeholders = implode(',', array_fill(0, count($reply_ids), '%d'));
                $att_query = $wpdb->prepare(
                    "SELECT * FROM {$prefix}attachments WHERE post_type = 'reply' AND post_id IN ($placeholders)",
                    ...$reply_ids
                );
                $all_reply_atts = $wpdb->get_results($att_query);
                foreach ($all_reply_atts as $att) {
                    $reply_attachments[(int) $att->post_id][] = $att;
                }
            }

            // Build nested reply tree.
            $reply_tree = $this->build_reply_tree($replies);

            ob_start();
            ?>
            <nav class="dgptm-forum-breadcrumb">
                <a href="#" class="dgptm-forum-breadcrumb-item dgptm-forum-nav" data-view="ags" data-id="0">Forum</a>
                <span class="dgptm-forum-breadcrumb-sep">&rsaquo;</span>
                <?php if ($ag_id > 0) : ?>
                    <a href="#" class="dgptm-forum-breadcrumb-item dgptm-forum-nav" data-view="topics" data-id="<?php echo esc_attr($ag_id); ?>">
                        <?php echo esc_html($ag_name); ?>
                    </a>
                    <span class="dgptm-forum-breadcrumb-sep">&rsaquo;</span>
                <?php endif; ?>
                <?php if ($topic) : ?>
                    <a href="#" class="dgptm-forum-breadcrumb-item dgptm-forum-nav" data-view="threads" data-id="<?php echo esc_attr($topic->id); ?>">
                        <?php echo esc_html($topic->title); ?>
                    </a>
                    <span class="dgptm-forum-breadcrumb-sep">&rsaquo;</span>
                <?php endif; ?>
                <span class="dgptm-forum-breadcrumb-item active"><?php echo esc_html($thread->title); ?></span>
            </nav>

            <?php // ---- Thread as first post ---- ?>
            <div class="dgptm-forum-thread-detail">
                <div class="dgptm-forum-post dgptm-forum-post-thread" data-post-id="<?php echo esc_attr($thread->id); ?>" data-post-type="thread">
                    <div class="dgptm-forum-post-header">
                        <strong class="dgptm-forum-post-author"><?php echo esc_html($thread_author_name); ?></strong>
                        <span class="dgptm-forum-post-date"><?php echo esc_html($thread_date); ?></span>
                        <?php if ($thread->is_pinned) : ?>
                            <span class="dgptm-forum-badge dgptm-forum-badge-pinned">Angepinnt</span>
                        <?php endif; ?>
                        <?php if ($thread->status === 'closed') : ?>
                            <span class="dgptm-forum-badge dgptm-forum-badge-closed">Geschlossen</span>
                        <?php endif; ?>
                    </div>
                    <div class="dgptm-forum-post-content">
                        <?php echo wp_kses_post($thread->content); ?>
                    </div>
                    <?php $this->render_attachments($thread_attachments); ?>
                    <div class="dgptm-forum-post-actions">
                        <?php if ($can_post && $thread->status !== 'closed') : ?>
                            <button type="button" class="dgptm-forum-btn dgptm-forum-btn-sm dgptm-forum-reply-btn"
                                    data-thread-id="<?php echo esc_attr($thread->id); ?>"
                                    data-parent-id="0" data-depth="1">Antworten</button>
                        <?php endif; ?>
                        <?php if ((int) $thread->author_id === $user_id || $can_moderate) : ?>
                            <button type="button" class="dgptm-forum-btn dgptm-forum-btn-sm dgptm-forum-edit-btn"
                                    data-post-id="<?php echo esc_attr($thread->id); ?>"
                                    data-post-type="thread">Bearbeiten</button>
                        <?php endif; ?>
                        <?php if ($can_moderate) : ?>
                            <button type="button" class="dgptm-forum-btn dgptm-forum-btn-sm dgptm-forum-btn-danger dgptm-forum-delete-btn"
                                    data-post-id="<?php echo esc_attr($thread->id); ?>"
                                    data-post-type="thread">L&ouml;schen</button>
                        <?php endif; ?>
                    </div>
                </div>

                <?php // ---- Replies ---- ?>
                <div class="dgptm-forum-replies">
                    <?php $this->render_reply_tree($reply_tree, $reply_attachments, $thread, $user_id, $can_post, $can_moderate); ?>
                </div>

                <?php // ---- Compose reply form at bottom ---- ?>
                <?php if ($can_post && $thread->status !== 'closed') : ?>
                    <div class="dgptm-forum-compose-area dgptm-forum-compose-reply-bottom">
                        <h4>Antwort verfassen</h4>
                        <div class="dgptm-forum-form-group">
                            <textarea id="dgptm-forum-reply-content-bottom" class="dgptm-forum-textarea" rows="4" placeholder="Ihre Antwort..."></textarea>
                        </div>
                        <div class="dgptm-forum-form-group">
                            <input type="file" id="dgptm-forum-reply-files-bottom" multiple />
                        </div>
                        <div class="dgptm-forum-form-actions">
                            <button type="button" class="dgptm-forum-btn dgptm-forum-btn-primary dgptm-forum-submit-reply"
                                    data-thread-id="<?php echo esc_attr($thread->id); ?>"
                                    data-parent-id="0" data-depth="1">Antwort absenden</button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <?php

            return ob_get_clean();
        }

        // ----- Reply Tree Helpers ------------------------------------

        /**
         * Build a nested tree structure from flat reply rows.
         *
         * @param array $replies Flat list of reply objects.
         * @return array Nested array: each element has 'reply' and 'children'.
         */
        private function build_reply_tree($replies) {
            $map  = [];
            $tree = [];

            foreach ($replies as $reply) {
                $map[(int) $reply->id] = [
                    'reply'    => $reply,
                    'children' => [],
                ];
            }

            foreach ($replies as $reply) {
                $parent = (int) $reply->parent_id;
                if ($parent > 0 && isset($map[$parent])) {
                    $map[$parent]['children'][] = &$map[(int) $reply->id];
                } else {
                    $tree[] = &$map[(int) $reply->id];
                }
            }

            return $tree;
        }

        /**
         * Render reply tree recursively using nested divs with depth classes.
         *
         * @param array  $tree              Nested reply tree.
         * @param array  $reply_attachments Attachments keyed by reply ID.
         * @param object $thread            Thread object.
         * @param int    $user_id           Current user ID.
         * @param bool   $can_post          Whether user can post.
         * @param bool   $can_moderate      Whether user can moderate.
         */
        private function render_reply_tree($tree, $reply_attachments, $thread, $user_id, $can_post, $can_moderate) {
            foreach ($tree as $node) {
                $reply = $node['reply'];
                $depth = (int) $reply->depth;
                $author = get_userdata($reply->author_id);
                $author_name = $author ? $author->display_name : 'Unbekannt';
                $date = date_i18n(
                    get_option('date_format') . ' ' . get_option('time_format'),
                    strtotime($reply->created_at)
                );
                $atts = isset($reply_attachments[(int) $reply->id]) ? $reply_attachments[(int) $reply->id] : [];
                $next_depth = $depth + 1;
                ?>
                <div class="dgptm-forum-post dgptm-forum-post-reply dgptm-forum-depth-<?php echo esc_attr($depth); ?>"
                     data-post-id="<?php echo esc_attr($reply->id); ?>" data-post-type="reply">
                    <div class="dgptm-forum-post-header">
                        <strong class="dgptm-forum-post-author"><?php echo esc_html($author_name); ?></strong>
                        <span class="dgptm-forum-post-date"><?php echo esc_html($date); ?></span>
                    </div>
                    <div class="dgptm-forum-post-content">
                        <?php echo wp_kses_post($reply->content); ?>
                    </div>
                    <?php $this->render_attachments($atts); ?>
                    <div class="dgptm-forum-post-actions">
                        <?php if ($can_post && $thread->status !== 'closed' && $next_depth <= 3) : ?>
                            <button type="button" class="dgptm-forum-btn dgptm-forum-btn-sm dgptm-forum-reply-btn"
                                    data-thread-id="<?php echo esc_attr($thread->id); ?>"
                                    data-parent-id="<?php echo esc_attr($reply->id); ?>"
                                    data-depth="<?php echo esc_attr($next_depth); ?>">Antworten</button>
                        <?php endif; ?>
                        <?php if ((int) $reply->author_id === $user_id || $can_moderate) : ?>
                            <button type="button" class="dgptm-forum-btn dgptm-forum-btn-sm dgptm-forum-edit-btn"
                                    data-post-id="<?php echo esc_attr($reply->id); ?>"
                                    data-post-type="reply">Bearbeiten</button>
                        <?php endif; ?>
                        <?php if ($can_moderate) : ?>
                            <button type="button" class="dgptm-forum-btn dgptm-forum-btn-sm dgptm-forum-btn-danger dgptm-forum-delete-btn"
                                    data-post-id="<?php echo esc_attr($reply->id); ?>"
                                    data-post-type="reply">L&ouml;schen</button>
                        <?php endif; ?>
                    </div>
                    <?php
                    // Render children.
                    if (!empty($node['children'])) {
                        echo '<div class="dgptm-forum-reply-children">';
                        $this->render_reply_tree($node['children'], $reply_attachments, $thread, $user_id, $can_post, $can_moderate);
                        echo '</div>';
                    }
                    ?>
                </div>
                <?php
            }
        }

        /**
         * Render attachment list for a post.
         *
         * @param array $attachments
         */
        private function render_attachments($attachments) {
            if (empty($attachments)) {
                return;
            }
            ?>
            <div class="dgptm-forum-attachments">
                <strong>Anh&auml;nge:</strong>
                <ul>
                    <?php foreach ($attachments as $att) :
                        $url = wp_get_attachment_url($att->attachment_id);
                        if (!$url) continue;
                    ?>
                        <li>
                            <a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">
                                <?php echo esc_html($att->filename); ?>
                            </a>
                            <?php if ($att->filesize > 0) : ?>
                                <span class="dgptm-forum-filesize">(<?php echo esc_html(size_format($att->filesize)); ?>)</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php
        }

        // =============================================================
        //  Thread / Reply CRUD
        // =============================================================

        /**
         * Create a new thread.
         *
         * POST: topic_id, title, content, files (optional)
         */
        private function create_thread() {
            global $wpdb;

            $user_id  = get_current_user_id();
            $prefix   = $wpdb->prefix . 'dgptm_forum_';
            $topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
            $title    = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
            $content  = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';

            if (empty($topic_id) || empty($title) || empty($content)) {
                wp_send_json_error(['message' => 'Titel, Inhalt und Thema sind erforderlich.']);
            }

            if (!DGPTM_Forum_Permissions::can_post_in_topic($user_id, $topic_id)) {
                wp_send_json_error(['message' => 'Keine Berechtigung in diesem Thema zu posten.']);
            }

            $now = current_time('mysql');

            $inserted = $wpdb->insert("{$prefix}threads", [
                'topic_id'     => $topic_id,
                'title'        => $title,
                'content'      => $content,
                'author_id'    => $user_id,
                'status'       => 'open',
                'is_pinned'    => 0,
                'reply_count'  => 0,
                'last_reply_at' => $now,
                'last_reply_by' => $user_id,
                'created_at'   => $now,
                'updated_at'   => $now,
            ], ['%d', '%s', '%s', '%d', '%s', '%d', '%d', '%s', '%d', '%s', '%s']);

            if (false === $inserted) {
                wp_send_json_error(['message' => 'Thread konnte nicht erstellt werden.']);
            }

            $thread_id = $wpdb->insert_id;

            // Update topic thread_count and last_activity.
            $wpdb->query($wpdb->prepare(
                "UPDATE {$prefix}topics SET thread_count = thread_count + 1, last_activity = %s WHERE id = %d",
                $now,
                $topic_id
            ));

            // Handle file attachments.
            $this->handle_attachments('thread', $thread_id);

            // Trigger notifications.
            if (class_exists('DGPTM_Forum_Notifications')) {
                DGPTM_Forum_Notifications::notify_new_post('thread', $thread_id, $thread_id, $topic_id);
            }

            wp_send_json_success([
                'message'   => 'Thread erstellt.',
                'thread_id' => $thread_id,
            ]);
        }

        /**
         * Create a reply.
         *
         * POST: thread_id, content, parent_id (0 for direct), depth
         */
        private function create_reply() {
            global $wpdb;

            $user_id   = get_current_user_id();
            $prefix    = $wpdb->prefix . 'dgptm_forum_';
            $thread_id = isset($_POST['thread_id']) ? absint($_POST['thread_id']) : 0;
            $content   = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';
            $parent_id = isset($_POST['parent_id']) ? absint($_POST['parent_id']) : 0;
            $depth     = isset($_POST['depth']) ? absint($_POST['depth']) : 1;

            if (empty($thread_id) || empty($content)) {
                wp_send_json_error(['message' => 'Thread-ID und Inhalt sind erforderlich.']);
            }

            if ($depth > 3) {
                wp_send_json_error(['message' => 'Maximale Verschachtelungstiefe erreicht.']);
            }

            // Get thread to find topic_id.
            $thread = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}threads WHERE id = %d",
                $thread_id
            ));

            if (!$thread) {
                wp_send_json_error(['message' => 'Thread nicht gefunden.']);
            }

            if ($thread->status === 'closed') {
                wp_send_json_error(['message' => 'Dieser Thread ist geschlossen.']);
            }

            if (!DGPTM_Forum_Permissions::can_post_in_topic($user_id, $thread->topic_id)) {
                wp_send_json_error(['message' => 'Keine Berechtigung in diesem Thema zu posten.']);
            }

            $now = current_time('mysql');

            $inserted = $wpdb->insert("{$prefix}replies", [
                'thread_id'  => $thread_id,
                'parent_id'  => $parent_id,
                'depth'      => $depth,
                'content'    => $content,
                'author_id'  => $user_id,
                'status'     => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ], ['%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s']);

            if (false === $inserted) {
                wp_send_json_error(['message' => 'Antwort konnte nicht erstellt werden.']);
            }

            $reply_id = $wpdb->insert_id;

            // Update thread reply_count, last_reply_at, last_reply_by.
            $wpdb->query($wpdb->prepare(
                "UPDATE {$prefix}threads SET reply_count = reply_count + 1, last_reply_at = %s, last_reply_by = %d WHERE id = %d",
                $now,
                $user_id,
                $thread_id
            ));

            // Update topic last_activity.
            $wpdb->query($wpdb->prepare(
                "UPDATE {$prefix}topics SET last_activity = %s WHERE id = %d",
                $now,
                $thread->topic_id
            ));

            // Handle file attachments.
            $this->handle_attachments('reply', $reply_id);

            // Trigger notifications.
            if (class_exists('DGPTM_Forum_Notifications')) {
                DGPTM_Forum_Notifications::notify_new_post('reply', $reply_id, $thread_id, $thread->topic_id);
            }

            wp_send_json_success([
                'message'  => 'Antwort erstellt.',
                'reply_id' => $reply_id,
            ]);
        }

        /**
         * Edit a thread or reply.
         *
         * POST: post_id, post_type (thread/reply), content
         */
        private function edit_post() {
            global $wpdb;

            $user_id   = get_current_user_id();
            $prefix    = $wpdb->prefix . 'dgptm_forum_';
            $post_id   = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            $post_type = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : '';
            $content   = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';

            if (empty($post_id) || empty($content)) {
                wp_send_json_error(['message' => 'Beitrags-ID und Inhalt sind erforderlich.']);
            }

            if (!in_array($post_type, ['thread', 'reply'], true)) {
                wp_send_json_error(['message' => 'Ung&uuml;ltiger Beitragstyp.']);
            }

            // Determine table suffix (plural).
            $table_suffix = $post_type === 'thread' ? 'threads' : 'replies';

            if (!$this->check_edit_permission($user_id, $table_suffix, $post_id)) {
                wp_send_json_error(['message' => 'Keine Berechtigung diesen Beitrag zu bearbeiten.']);
            }

            $table = $prefix . $table_suffix;
            $now   = current_time('mysql');

            $updated = $wpdb->update(
                $table,
                ['content' => $content, 'updated_at' => $now],
                ['id' => $post_id],
                ['%s', '%s'],
                ['%d']
            );

            if (false === $updated) {
                wp_send_json_error(['message' => 'Beitrag konnte nicht aktualisiert werden.']);
            }

            wp_send_json_success(['message' => 'Beitrag aktualisiert.']);
        }

        /**
         * Delete a thread or reply (soft delete).
         *
         * POST: post_id, post_type
         */
        private function delete_post() {
            global $wpdb;

            $user_id   = get_current_user_id();
            $prefix    = $wpdb->prefix . 'dgptm_forum_';
            $post_id   = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
            $post_type = isset($_POST['post_type']) ? sanitize_key($_POST['post_type']) : '';

            if (empty($post_id)) {
                wp_send_json_error(['message' => 'Beitrags-ID ist erforderlich.']);
            }

            if (!in_array($post_type, ['thread', 'reply'], true)) {
                wp_send_json_error(['message' => 'Ung&uuml;ltiger Beitragstyp.']);
            }

            $table_suffix = $post_type === 'thread' ? 'threads' : 'replies';

            if (!$this->check_delete_permission($user_id, $table_suffix, $post_id)) {
                wp_send_json_error(['message' => 'Keine Berechtigung diesen Beitrag zu l&ouml;schen.']);
            }

            $table = $prefix . $table_suffix;

            $updated = $wpdb->update(
                $table,
                ['status' => 'deleted'],
                ['id' => $post_id],
                ['%s'],
                ['%d']
            );

            if (false === $updated) {
                wp_send_json_error(['message' => 'Beitrag konnte nicht gel&ouml;scht werden.']);
            }

            // If thread deleted, decrement topic thread_count.
            if ($post_type === 'thread') {
                $thread = $wpdb->get_row($wpdb->prepare(
                    "SELECT topic_id FROM {$prefix}threads WHERE id = %d",
                    $post_id
                ));
                if ($thread) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$prefix}topics SET thread_count = GREATEST(thread_count - 1, 0) WHERE id = %d",
                        $thread->topic_id
                    ));
                }
            }

            // If reply deleted, decrement thread reply_count.
            if ($post_type === 'reply') {
                $reply = $wpdb->get_row($wpdb->prepare(
                    "SELECT thread_id FROM {$prefix}replies WHERE id = %d",
                    $post_id
                ));
                if ($reply) {
                    $wpdb->query($wpdb->prepare(
                        "UPDATE {$prefix}threads SET reply_count = GREATEST(reply_count - 1, 0) WHERE id = %d",
                        $reply->thread_id
                    ));
                }
            }

            wp_send_json_success(['message' => 'Beitrag gel&ouml;scht.']);
        }

        /**
         * Check edit permission, handling the replies-have-no-topic_id issue.
         *
         * @param int    $user_id
         * @param string $table_suffix 'threads' or 'replies'
         * @param int    $post_id
         * @return bool
         */
        private function check_edit_permission($user_id, $table_suffix, $post_id) {
            global $wpdb;
            $prefix = $wpdb->prefix . 'dgptm_forum_';

            if ($table_suffix === 'threads') {
                return DGPTM_Forum_Permissions::can_edit_post($user_id, 'threads', $post_id);
            }

            // For replies: get author_id and resolve topic_id via thread.
            $reply = $wpdb->get_row($wpdb->prepare(
                "SELECT r.author_id, t.topic_id
                 FROM {$prefix}replies r
                 JOIN {$prefix}threads t ON t.id = r.thread_id
                 WHERE r.id = %d",
                $post_id
            ));

            if (!$reply) {
                return false;
            }

            if ((int) $reply->author_id === (int) $user_id) {
                return true;
            }

            return DGPTM_Forum_Permissions::can_moderate_topic($user_id, (int) $reply->topic_id);
        }

        /**
         * Check delete permission, handling replies-have-no-topic_id issue.
         *
         * @param int    $user_id
         * @param string $table_suffix 'threads' or 'replies'
         * @param int    $post_id
         * @return bool
         */
        private function check_delete_permission($user_id, $table_suffix, $post_id) {
            global $wpdb;
            $prefix = $wpdb->prefix . 'dgptm_forum_';

            if ($table_suffix === 'threads') {
                return DGPTM_Forum_Permissions::can_delete_post($user_id, 'threads', $post_id);
            }

            // For replies: resolve topic_id via thread.
            $reply = $wpdb->get_row($wpdb->prepare(
                "SELECT t.topic_id
                 FROM {$prefix}replies r
                 JOIN {$prefix}threads t ON t.id = r.thread_id
                 WHERE r.id = %d",
                $post_id
            ));

            if (!$reply) {
                return false;
            }

            return DGPTM_Forum_Permissions::can_moderate_topic($user_id, (int) $reply->topic_id);
        }

        // =============================================================
        //  File Upload
        // =============================================================

        /**
         * Standalone file upload handler.
         *
         * Returns attachment info on success.
         */
        private function upload_file() {
            if (empty($_FILES['file'])) {
                wp_send_json_error(['message' => 'Keine Datei hochgeladen.']);
            }

            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            $attachment_id = media_handle_upload('file', 0);

            if (is_wp_error($attachment_id)) {
                wp_send_json_error(['message' => $attachment_id->get_error_message()]);
            }

            $file_path = get_attached_file($attachment_id);

            wp_send_json_success([
                'message'       => 'Datei hochgeladen.',
                'attachment_id' => $attachment_id,
                'url'           => wp_get_attachment_url($attachment_id),
                'filename'      => basename($file_path),
                'filesize'      => filesize($file_path),
            ]);
        }

        /**
         * Handle file attachments for a post (thread or reply).
         *
         * Processes all files from $_FILES and stores references in the attachments table.
         *
         * @param string $post_type 'thread' or 'reply'
         * @param int    $post_id
         */
        private function handle_attachments($post_type, $post_id) {
            if (empty($_FILES) || empty($_FILES['files'])) {
                return;
            }

            require_once ABSPATH . 'wp-admin/includes/image.php';
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';

            global $wpdb;
            $prefix  = $wpdb->prefix . 'dgptm_forum_';
            $user_id = get_current_user_id();
            $now     = current_time('mysql');

            // $_FILES['files'] may be a multi-file upload array.
            $files = $_FILES['files'];
            $count = is_array($files['name']) ? count($files['name']) : 1;

            for ($i = 0; $i < $count; $i++) {
                // Reconstruct single-file array for media_handle_upload.
                $_FILES['forum_upload'] = [
                    'name'     => is_array($files['name']) ? $files['name'][$i] : $files['name'],
                    'type'     => is_array($files['type']) ? $files['type'][$i] : $files['type'],
                    'tmp_name' => is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'],
                    'error'    => is_array($files['error']) ? $files['error'][$i] : $files['error'],
                    'size'     => is_array($files['size']) ? $files['size'][$i] : $files['size'],
                ];

                if ($_FILES['forum_upload']['error'] !== UPLOAD_ERR_OK) {
                    continue;
                }

                $attachment_id = media_handle_upload('forum_upload', 0);

                if (is_wp_error($attachment_id)) {
                    continue;
                }

                $file_path = get_attached_file($attachment_id);
                $filename  = basename($file_path);
                $filesize  = file_exists($file_path) ? filesize($file_path) : 0;
                $mime      = get_post_mime_type($attachment_id);

                $wpdb->insert("{$prefix}attachments", [
                    'post_type'     => $post_type,
                    'post_id'       => $post_id,
                    'attachment_id' => $attachment_id,
                    'filename'      => $filename,
                    'filesize'      => $filesize,
                    'mime_type'     => $mime ? $mime : '',
                    'uploaded_at'   => $now,
                    'uploaded_by'   => $user_id,
                ], ['%s', '%d', '%d', '%s', '%d', '%s', '%s', '%d']);
            }

            // Clean up temp key.
            unset($_FILES['forum_upload']);
        }

        // =============================================================
        //  Subscriptions
        // =============================================================

        /**
         * Subscribe to a scope.
         *
         * POST: scope, scope_id
         */
        private function subscribe() {
            $scope    = isset($_POST['scope']) ? sanitize_text_field($_POST['scope']) : '';
            $scope_id = isset($_POST['scope_id']) ? absint($_POST['scope_id']) : 0;

            if (empty($scope)) {
                wp_send_json_error(['message' => 'Scope ist erforderlich.']);
            }

            if (class_exists('DGPTM_Forum_Notifications')) {
                DGPTM_Forum_Notifications::subscribe(get_current_user_id(), $scope, $scope_id);
            }

            wp_send_json_success(['message' => 'Abonniert.']);
        }

        /**
         * Unsubscribe from a scope.
         *
         * POST: scope, scope_id
         */
        private function unsubscribe() {
            $scope    = isset($_POST['scope']) ? sanitize_text_field($_POST['scope']) : '';
            $scope_id = isset($_POST['scope_id']) ? absint($_POST['scope_id']) : 0;

            if (empty($scope)) {
                wp_send_json_error(['message' => 'Scope ist erforderlich.']);
            }

            if (class_exists('DGPTM_Forum_Notifications')) {
                DGPTM_Forum_Notifications::unsubscribe(get_current_user_id(), $scope, $scope_id);
            }

            wp_send_json_success(['message' => 'Abonnement entfernt.']);
        }

        // =============================================================
        //  Admin Methods
        // =============================================================

        /**
         * Verify admin access. Returns true if current user is forum admin
         * or AG leader for the relevant AG context.
         *
         * @return bool
         */
        private function require_admin() {
            $user_id = get_current_user_id();

            if (DGPTM_Forum_Permissions::is_forum_admin($user_id)) {
                return true;
            }

            // Check if user is AG leader for the AG in context.
            $ag_id = isset($_POST['ag_id']) ? absint($_POST['ag_id']) : 0;
            if ($ag_id > 0 && DGPTM_Forum_Permissions::is_ag_leiter($user_id, $ag_id)) {
                return true;
            }

            return false;
        }

        /**
         * Load admin tab content.
         *
         * POST: tab (ags|topics|admins|moderation)
         */
        private function admin_load_tab() {
            if (!DGPTM_Forum_Permissions::is_forum_admin()) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $tab = isset($_POST['tab']) ? sanitize_text_field($_POST['tab']) : '';

            if (class_exists('DGPTM_Forum_Admin_Renderer')) {
                switch ($tab) {
                    case 'ags':
                        $html = DGPTM_Forum_Admin_Renderer::render_tab_ags();
                        break;
                    case 'topics':
                    case 'themen':
                        $html = DGPTM_Forum_Admin_Renderer::render_tab_topics();
                        break;
                    case 'admins':
                        $html = DGPTM_Forum_Admin_Renderer::render_tab_admins();
                        break;
                    case 'moderation':
                        $html = DGPTM_Forum_Admin_Renderer::render_tab_moderation();
                        break;
                    default:
                        wp_send_json_error(['message' => 'Unbekannter Tab.']);
                        return;
                }
                wp_send_json_success(['html' => $html]);
            }

            // Fallback if renderer class not yet available.
            wp_send_json_error(['message' => 'Admin-Renderer nicht verf&uuml;gbar.']);
        }

        /**
         * Create or update an AG.
         *
         * POST: ag_id (0=create), name, description, leader_user_id
         */
        private function admin_save_ag() {
            if (!$this->require_admin()) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $ag_id = isset($_POST['ag_id']) ? absint($_POST['ag_id']) : 0;
            $data  = [
                'name'           => isset($_POST['name']) ? $_POST['name'] : '',
                'description'    => isset($_POST['description']) ? $_POST['description'] : '',
                'leader_user_id' => isset($_POST['leader_user_id']) ? absint($_POST['leader_user_id']) : 0,
            ];

            if ($ag_id === 0) {
                $result = DGPTM_Forum_AG_Manager::create_ag($data);
            } else {
                $result = DGPTM_Forum_AG_Manager::update_ag($ag_id, $data);
            }

            if (is_wp_error($result)) {
                wp_send_json_error(['message' => $result->get_error_message()]);
            }

            // If new AG and leader specified, add leader as member with 'leiter' role.
            if ($ag_id === 0 && !empty($data['leader_user_id']) && is_numeric($result)) {
                DGPTM_Forum_AG_Manager::add_member($result, $data['leader_user_id'], 'leiter');
            }

            wp_send_json_success([
                'message' => $ag_id === 0 ? 'Hauptgruppe erstellt.' : 'Hauptgruppe aktualisiert.',
                'ag_id'   => $ag_id === 0 ? $result : $ag_id,
            ]);
        }

        /**
         * Delete an AG.
         *
         * POST: ag_id
         */
        private function admin_delete_ag() {
            if (!DGPTM_Forum_Permissions::is_forum_admin()) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $ag_id = isset($_POST['ag_id']) ? absint($_POST['ag_id']) : 0;

            if (empty($ag_id)) {
                wp_send_json_error(['message' => 'AG-ID ist erforderlich.']);
            }

            DGPTM_Forum_AG_Manager::delete_ag($ag_id);

            wp_send_json_success(['message' => 'AG gel&ouml;scht.']);
        }

        /**
         * Add a member to an AG.
         *
         * POST: ag_id, user_id
         */
        private function admin_add_member() {
            if (!$this->require_admin()) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $ag_id   = isset($_POST['ag_id']) ? absint($_POST['ag_id']) : 0;
            $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;

            if (empty($ag_id) || empty($user_id)) {
                wp_send_json_error(['message' => 'AG-ID und Benutzer-ID sind erforderlich.']);
            }

            DGPTM_Forum_AG_Manager::add_member($ag_id, $user_id);

            wp_send_json_success(['message' => 'Mitglied hinzugef&uuml;gt.']);
        }

        /**
         * Remove a member from an AG.
         *
         * POST: ag_id, user_id
         */
        private function admin_remove_member() {
            if (!$this->require_admin()) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $ag_id   = isset($_POST['ag_id']) ? absint($_POST['ag_id']) : 0;
            $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;

            if (empty($ag_id) || empty($user_id)) {
                wp_send_json_error(['message' => 'AG-ID und Benutzer-ID sind erforderlich.']);
            }

            DGPTM_Forum_AG_Manager::remove_member($ag_id, $user_id);

            wp_send_json_success(['message' => 'Mitglied entfernt.']);
        }

        /**
         * Create or update a topic.
         *
         * POST: topic_id (0=create), ag_id, title, description, access_mode, responsible_id
         */
        private function admin_save_topic() {
            if (!$this->require_admin()) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            global $wpdb;
            $prefix = $wpdb->prefix . 'dgptm_forum_';

            $topic_id       = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
            $ag_id          = isset($_POST['ag_id']) ? absint($_POST['ag_id']) : 0;
            $title          = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
            $description    = isset($_POST['description']) ? sanitize_textarea_field($_POST['description']) : '';
            $access_mode    = isset($_POST['access_mode']) ? sanitize_text_field($_POST['access_mode']) : 'open';
            $responsible_id = isset($_POST['responsible_id']) ? absint($_POST['responsible_id']) : 0;

            if (empty($title)) {
                wp_send_json_error(['message' => 'Titel ist erforderlich.']);
            }

            $valid_modes = ['open', 'ag_only', 'ag_plus'];
            if (!in_array($access_mode, $valid_modes, true)) {
                $access_mode = 'open';
            }

            $now  = current_time('mysql');
            $slug = sanitize_title($title);

            if ($topic_id === 0) {
                // Create.
                $inserted = $wpdb->insert("{$prefix}topics", [
                    'ag_id'          => $ag_id > 0 ? $ag_id : null,
                    'title'          => $title,
                    'slug'           => $slug,
                    'description'    => $description,
                    'access_mode'    => $access_mode,
                    'responsible_id' => $responsible_id,
                    'status'         => 'active',
                    'sort_order'     => 0,
                    'thread_count'   => 0,
                    'last_activity'  => null,
                    'created_at'     => $now,
                    'created_by'     => get_current_user_id(),
                ]);

                if (false === $inserted) {
                    wp_send_json_error(['message' => 'Thema konnte nicht erstellt werden.']);
                }

                $new_id = $wpdb->insert_id;

                wp_send_json_success([
                    'message'  => 'Thema erstellt.',
                    'topic_id' => $new_id,
                ]);
            } else {
                // Update.
                $updated = $wpdb->update(
                    "{$prefix}topics",
                    [
                        'ag_id'          => $ag_id > 0 ? $ag_id : null,
                        'title'          => $title,
                        'slug'           => $slug,
                        'description'    => $description,
                        'access_mode'    => $access_mode,
                        'responsible_id' => $responsible_id,
                    ],
                    ['id' => $topic_id]
                );

                if (false === $updated) {
                    wp_send_json_error(['message' => 'Thema konnte nicht aktualisiert werden.']);
                }

                wp_send_json_success([
                    'message'  => 'Thema aktualisiert.',
                    'topic_id' => $topic_id,
                ]);
            }
        }

        /**
         * Delete a topic.
         *
         * POST: topic_id
         */
        private function admin_delete_topic() {
            if (!$this->require_admin()) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            global $wpdb;
            $prefix   = $wpdb->prefix . 'dgptm_forum_';
            $topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;

            if (empty($topic_id)) {
                wp_send_json_error(['message' => 'Themen-ID ist erforderlich.']);
            }

            // Soft-delete: set status to deleted.
            $wpdb->update(
                "{$prefix}topics",
                ['status' => 'deleted'],
                ['id' => $topic_id],
                ['%s'],
                ['%d']
            );

            // Also remove topic access entries.
            $wpdb->delete("{$prefix}topic_access", ['topic_id' => $topic_id], ['%d']);

            wp_send_json_success(['message' => 'Thema gel&ouml;scht.']);
        }

        /**
         * Grant individual topic access to a user.
         *
         * POST: topic_id, user_id
         */
        private function admin_grant_access() {
            if (!$this->require_admin()) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            global $wpdb;
            $prefix   = $wpdb->prefix . 'dgptm_forum_';
            $topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
            $user_id  = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;

            if (empty($topic_id) || empty($user_id)) {
                wp_send_json_error(['message' => 'Themen-ID und Benutzer-ID sind erforderlich.']);
            }

            $wpdb->query($wpdb->prepare(
                "INSERT IGNORE INTO {$prefix}topic_access (topic_id, user_id, granted_at, granted_by) VALUES (%d, %d, %s, %d)",
                $topic_id,
                $user_id,
                current_time('mysql'),
                get_current_user_id()
            ));

            wp_send_json_success(['message' => 'Zugriff gew&auml;hrt.']);
        }

        /**
         * Revoke individual topic access.
         *
         * POST: topic_id, user_id
         */
        private function admin_revoke_access() {
            if (!$this->require_admin()) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            global $wpdb;
            $prefix   = $wpdb->prefix . 'dgptm_forum_';
            $topic_id = isset($_POST['topic_id']) ? absint($_POST['topic_id']) : 0;
            $user_id  = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;

            if (empty($topic_id) || empty($user_id)) {
                wp_send_json_error(['message' => 'Themen-ID und Benutzer-ID sind erforderlich.']);
            }

            $wpdb->delete("{$prefix}topic_access", [
                'topic_id' => $topic_id,
                'user_id'  => $user_id,
            ], ['%d', '%d']);

            wp_send_json_success(['message' => 'Zugriff entzogen.']);
        }

        /**
         * Search users for autocomplete.
         *
         * POST: term
         */
        private function admin_search_users() {
            if (!$this->require_admin()) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $term = isset($_POST['term']) ? sanitize_text_field($_POST['term']) : '';

            if (strlen($term) < 2) {
                wp_send_json_success(['users' => []]);
                return;
            }

            $users = DGPTM_Forum_AG_Manager::search_users($term);

            $result = [];
            foreach ($users as $user) {
                $name = $user->display_name ?: $user->user_login;
                $result[] = [
                    'id'    => (int) $user->id,
                    'name'  => $name,
                    'email' => $user->user_email,
                ];
            }

            wp_send_json_success(['users' => $result]);
        }

        /**
         * Toggle thread pin status.
         *
         * POST: thread_id
         */
        private function admin_toggle_pin() {
            if (!DGPTM_Forum_Permissions::is_forum_admin()) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            global $wpdb;
            $prefix    = $wpdb->prefix . 'dgptm_forum_';
            $thread_id = isset($_POST['thread_id']) ? absint($_POST['thread_id']) : 0;

            if (empty($thread_id)) {
                wp_send_json_error(['message' => 'Thread-ID ist erforderlich.']);
            }

            $thread = $wpdb->get_row($wpdb->prepare(
                "SELECT id, is_pinned FROM {$prefix}threads WHERE id = %d",
                $thread_id
            ));

            if (!$thread) {
                wp_send_json_error(['message' => 'Thread nicht gefunden.']);
            }

            $new_pinned = $thread->is_pinned ? 0 : 1;

            $wpdb->update(
                "{$prefix}threads",
                ['is_pinned' => $new_pinned],
                ['id' => $thread_id],
                ['%d'],
                ['%d']
            );

            wp_send_json_success([
                'message'   => $new_pinned ? 'Thread angepinnt.' : 'Thread losgelöst.',
                'is_pinned' => $new_pinned,
            ]);
        }

        /**
         * Close or reopen a thread.
         *
         * POST: thread_id
         */
        private function admin_close_thread() {
            if (!DGPTM_Forum_Permissions::is_forum_admin()) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            global $wpdb;
            $prefix    = $wpdb->prefix . 'dgptm_forum_';
            $thread_id = isset($_POST['thread_id']) ? absint($_POST['thread_id']) : 0;

            if (empty($thread_id)) {
                wp_send_json_error(['message' => 'Thread-ID ist erforderlich.']);
            }

            $thread = $wpdb->get_row($wpdb->prepare(
                "SELECT id, status FROM {$prefix}threads WHERE id = %d",
                $thread_id
            ));

            if (!$thread) {
                wp_send_json_error(['message' => 'Thread nicht gefunden.']);
            }

            $new_status = ($thread->status === 'closed') ? 'open' : 'closed';

            $wpdb->update(
                "{$prefix}threads",
                ['status' => $new_status],
                ['id' => $thread_id],
                ['%s'],
                ['%d']
            );

            wp_send_json_success([
                'message' => $new_status === 'closed' ? 'Thread geschlossen.' : 'Thread wieder ge&ouml;ffnet.',
                'status'  => $new_status,
            ]);
        }

        /**
         * Set or remove forum admin status for a user.
         *
         * POST: user_id, is_admin (1/0)
         */
        private function admin_set_forum_admin() {
            if (!DGPTM_Forum_Permissions::is_forum_admin()) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $user_id  = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
            $is_admin = isset($_POST['is_admin']) ? (bool) absint($_POST['is_admin']) : false;

            if (empty($user_id)) {
                wp_send_json_error(['message' => 'Benutzer-ID ist erforderlich.']);
            }

            DGPTM_Forum_Permissions::set_forum_admin($user_id, $is_admin);

            wp_send_json_success([
                'message' => $is_admin ? 'Forum-Admin gesetzt.' : 'Forum-Admin entfernt.',
            ]);
        }
    }
}
