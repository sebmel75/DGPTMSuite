<?php
/**
 * DGPTM Forum – AJAX Dispatcher
 *
 * Central AJAX handler for all forum frontend and admin actions.
 * Simplified hierarchy: Hauptgruppe → Thread → Antworten (nested, max 3 levels).
 *
 * @package DGPTM_Forum
 * @since   2.0.0
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
        //  Helper: Default Topic Bridge
        // =============================================================

        /**
         * Get or create a default topic for an AG.
         *
         * Bridges the old schema (topics table still exists) without exposing
         * topics to the user. Each AG gets exactly one default topic.
         *
         * @param int $ag_id
         * @return int Topic ID
         */
        private function get_or_create_default_topic($ag_id) {
            global $wpdb;
            $prefix = $wpdb->prefix . 'dgptm_forum_';

            $topic = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}topics WHERE ag_id = %d LIMIT 1",
                $ag_id
            ));

            if ($topic) {
                return (int) $topic->id;
            }

            // Auto-create default topic for this AG.
            $ag = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}ags WHERE id = %d",
                $ag_id
            ));

            $wpdb->insert("{$prefix}topics", [
                'ag_id'       => $ag_id,
                'title'       => $ag ? $ag->name : 'Standard',
                'slug'        => $ag ? $ag->slug : 'standard',
                'access_mode' => 'open',
                'status'      => 'active',
                'created_at'  => current_time('mysql'),
                'created_by'  => get_current_user_id(),
            ]);

            return (int) $wpdb->insert_id;
        }

        // =============================================================
        //  Permission Helpers
        // =============================================================

        /**
         * Check if user can access (view) a Hauptgruppe.
         *
         * Open groups: everyone.
         * Closed groups: members, forum admins, moderators only.
         *
         * @param int    $user_id
         * @param object $ag  AG row object.
         * @return bool
         */
        private function can_access_group($user_id, $ag) {
            if ($ag->group_type === 'open') {
                return true;
            }

            // Closed group: admin, moderator, or member.
            if (DGPTM_Forum_Permissions::is_forum_admin($user_id)) {
                return true;
            }
            if ((int) $ag->moderator_id === (int) $user_id) {
                return true;
            }
            if (DGPTM_Forum_Permissions::is_ag_member($user_id, $ag->id)) {
                return true;
            }

            return false;
        }

        /**
         * Check if user can post in a Hauptgruppe.
         *
         * Same logic as can_access_group — if you can see it, you can post.
         *
         * @param int    $user_id
         * @param object $ag  AG row object.
         * @return bool
         */
        private function can_post_in_group($user_id, $ag) {
            return $this->can_access_group($user_id, $ag);
        }

        /**
         * Check if user is moderator of a Hauptgruppe.
         *
         * Forum admin, AG moderator_id, or AG Leiter.
         *
         * @param int    $user_id
         * @param object $ag  AG row object.
         * @return bool
         */
        private function is_group_moderator($user_id, $ag) {
            if (DGPTM_Forum_Permissions::is_forum_admin($user_id)) {
                return true;
            }
            if ((int) $ag->moderator_id === (int) $user_id) {
                return true;
            }
            if (DGPTM_Forum_Permissions::is_ag_leiter($user_id, $ag->id)) {
                return true;
            }
            return false;
        }

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
         * Resolve AG object from a thread ID.
         *
         * @param int $thread_id
         * @return object|null AG row or null.
         */
        private function get_ag_for_thread($thread_id) {
            global $wpdb;
            $prefix = $wpdb->prefix . 'dgptm_forum_';

            $row = $wpdb->get_row($wpdb->prepare(
                "SELECT t.ag_id
                 FROM {$prefix}threads th
                 JOIN {$prefix}topics t ON t.id = th.topic_id
                 WHERE th.id = %d",
                $thread_id
            ));

            if (!$row || empty($row->ag_id)) {
                return null;
            }

            return DGPTM_Forum_AG_Manager::get_ag($row->ag_id);
        }

        // =============================================================
        //  Forum View Methods
        // =============================================================

        /**
         * Central view loader.
         *
         * POST params: view (ags|threads|thread), id
         */
        private function load_view() {
            $view = isset($_POST['view']) ? sanitize_text_field($_POST['view']) : '';
            $id   = isset($_POST['id']) ? absint($_POST['id']) : 0;

            switch ($view) {
                case 'ags':
                    $html = $this->render_ags_view();
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

        // ----- AG List View (Hauptgruppen-Übersicht) --------------------

        /**
         * Render the list of all Hauptgruppen the current user can see.
         *
         * Open groups: visible to all.
         * Closed groups: visible to all (unless is_hidden).
         * Hidden closed groups: only visible to members.
         *
         * Breadcrumb: [Forum]
         *
         * @return string HTML
         */
        private function render_ags_view() {
            global $wpdb;

            $user_id  = get_current_user_id();
            $prefix   = $wpdb->prefix . 'dgptm_forum_';
            $is_admin = DGPTM_Forum_Permissions::is_forum_admin($user_id);

            $ags = DGPTM_Forum_AG_Manager::get_all_ags('active');

            ob_start();
            ?>
            <nav class="dgptm-forum-breadcrumb">
                <span class="dgptm-forum-breadcrumb-item active">Forum</span>
            </nav>

            <div class="dgptm-forum-ag-list">
                <?php if (empty($ags)) : ?>
                    <p class="dgptm-forum-empty">Keine Hauptgruppen vorhanden.</p>
                <?php else : ?>
                    <?php foreach ($ags as $ag) :
                        // Visibility logic:
                        // Open groups: visible to all
                        // Closed groups (not hidden): visible to all
                        // Hidden closed groups: only members/admins
                        if ($ag->group_type === 'closed' && $ag->is_hidden) {
                            if (!$is_admin && !DGPTM_Forum_Permissions::is_ag_member($user_id, $ag->id)) {
                                continue;
                            }
                        }

                        // Thread count: count threads from all topics belonging to this AG.
                        $thread_count = (int) $wpdb->get_var($wpdb->prepare(
                            "SELECT COUNT(*)
                             FROM {$prefix}threads th
                             JOIN {$prefix}topics t ON t.id = th.topic_id
                             WHERE t.ag_id = %d AND th.status != 'deleted'",
                            $ag->id
                        ));

                        // Last activity: latest last_reply_at or created_at from threads.
                        $last_activity = $wpdb->get_var($wpdb->prepare(
                            "SELECT GREATEST(
                                COALESCE(MAX(th.last_reply_at), '1970-01-01'),
                                COALESCE(MAX(th.created_at), '1970-01-01')
                             )
                             FROM {$prefix}threads th
                             JOIN {$prefix}topics t ON t.id = th.topic_id
                             WHERE t.ag_id = %d AND th.status != 'deleted'",
                            $ag->id
                        ));

                        $last_activity_display = ($last_activity && $last_activity !== '1970-01-01')
                            ? date_i18n('d.m.Y H:i', strtotime($last_activity))
                            : '-';

                        // Moderator name.
                        $moderator_name = '';
                        if (!empty($ag->moderator_id)) {
                            $mod_user = get_userdata($ag->moderator_id);
                            if ($mod_user) {
                                $moderator_name = $mod_user->display_name;
                            }
                        }

                        $is_member = DGPTM_Forum_Permissions::is_ag_member($user_id, $ag->id);
                        $is_closed = ($ag->group_type === 'closed');
                        $can_enter = !$is_closed || $is_member || $is_admin;

                        // 3 neueste Threads
                        $recent_threads = $wpdb->get_results($wpdb->prepare(
                            "SELECT th.id, th.title, th.author_id, th.reply_count, th.created_at
                             FROM {$prefix}threads th
                             JOIN {$prefix}topics t ON t.id = th.topic_id
                             WHERE t.ag_id = %d AND th.status != 'deleted'
                             ORDER BY COALESCE(th.last_reply_at, th.created_at) DESC
                             LIMIT 3",
                            $ag->id
                        ));
                    ?>
                        <div class="dgptm-forum-ag-card <?php echo $can_enter ? 'dgptm-forum-ag-link' : 'dgptm-forum-ag-locked'; ?>" data-ag-id="<?php echo esc_attr($ag->id); ?>" style="border:1px solid #e4e8ec;border-radius:6px;padding:14px 16px;margin-bottom:10px;background:#fff;<?php echo $can_enter ? 'cursor:pointer;' : ''; ?>transition:box-shadow .15s">
                            <div style="display:flex;justify-content:space-between;align-items:center">
                                <div style="flex:1;min-width:0">
                                    <span style="font-size:15px;font-weight:600;color:#1d2327"><?php echo esc_html($ag->name); ?></span>
                                    <?php if ($is_closed) : ?>
                                        <span style="display:inline-block;padding:1px 6px;border-radius:8px;font-size:10px;font-weight:600;background:#fce4ec;color:#c62828;margin-left:6px;vertical-align:middle">geschlossen</span>
                                    <?php else : ?>
                                        <span style="display:inline-block;padding:1px 6px;border-radius:8px;font-size:10px;font-weight:600;background:#e8f5e9;color:#2e7d32;margin-left:6px;vertical-align:middle">offen</span>
                                    <?php endif; ?>
                                    <?php if ($ag->description) : ?>
                                        <div style="font-size:12px;color:#777;margin-top:3px"><?php echo esc_html(mb_strimwidth($ag->description, 0, 120, '…')); ?></div>
                                    <?php endif; ?>
                                </div>
                                <div style="display:flex;align-items:center;gap:8px;flex-shrink:0">
                                    <span style="font-size:11px;color:#999"><?php echo $thread_count; ?> Threads</span>
                                    <?php if ($is_closed && !$is_member && !$is_admin) : ?>
                                        <button type="button" class="dgptm-forum-btn dgptm-forum-request-membership" data-ag-id="<?php echo esc_attr($ag->id); ?>" style="font-size:11px;padding:3px 10px" onclick="event.stopPropagation()">Aufnahme beantragen</button>
                                    <?php else : ?>
                                        <span style="color:#aaa;font-size:18px">&rsaquo;</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!empty($recent_threads)) : ?>
                                <div style="margin-top:8px;padding-top:8px;border-top:1px solid #f0f0f0">
                                    <?php foreach ($recent_threads as $rt) :
                                        $rt_author = get_userdata($rt->author_id);
                                        $rt_name = $rt_author ? $rt_author->display_name : 'Unbekannt';
                                        $rt_date = date_i18n('d.m.Y', strtotime($rt->created_at));
                                    ?>
                                        <div class="dgptm-forum-thread-link" data-thread-id="<?php echo esc_attr($rt->id); ?>" style="display:flex;justify-content:space-between;align-items:center;padding:3px 4px;font-size:12px;color:#666;cursor:pointer;border-radius:3px" onmouseover="this.style.background='#f0f6fc'" onmouseout="this.style.background=''" onclick="event.stopPropagation()">
                                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:60%"><?php echo esc_html($rt->title); ?></span>
                                            <span style="flex-shrink:0;color:#999"><?php echo esc_html($rt_name); ?> &middot; <?php echo $rt_date; ?> &middot; <?php echo (int)$rt->reply_count; ?> Antw.</span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php

            return ob_get_clean();
        }

        // ----- Threads View (Thread-Liste einer Hauptgruppe) ------------

        /**
         * Render threads for a given Hauptgruppe.
         *
         * For a given ag_id:
         * - Check user can access this group
         * - Get all threads from all topics of this AG
         * - Pinned first, then by last_reply_at DESC
         * - "Neuer Thread" button with compose form (or "Aufnahme beantragen" for non-members of closed groups)
         *
         * Breadcrumb: [Forum > Hauptgruppe-Name]
         *
         * @param int $ag_id
         * @return string HTML
         */
        private function render_threads_view($ag_id) {
            global $wpdb;

            $user_id = get_current_user_id();
            $prefix  = $wpdb->prefix . 'dgptm_forum_';

            $ag = DGPTM_Forum_AG_Manager::get_ag($ag_id);
            if (!$ag) {
                return '<p class="dgptm-forum-error">Hauptgruppe nicht gefunden.</p>';
            }

            // Access check.
            if (!$this->can_access_group($user_id, $ag)) {
                return '<p class="dgptm-forum-error">Kein Zugriff auf diese Hauptgruppe.</p>';
            }

            // Get all threads from all topics of this AG.
            $threads = $wpdb->get_results($wpdb->prepare(
                "SELECT th.*
                 FROM {$prefix}threads th
                 JOIN {$prefix}topics t ON t.id = th.topic_id
                 WHERE t.ag_id = %d AND th.status != 'deleted'
                 ORDER BY th.is_pinned DESC, th.last_reply_at DESC, th.created_at DESC",
                $ag_id
            ));

            $can_post    = $this->can_post_in_group($user_id, $ag);
            $is_member   = DGPTM_Forum_Permissions::is_ag_member($user_id, $ag_id);
            $is_admin    = DGPTM_Forum_Permissions::is_forum_admin($user_id);
            $is_moderator = $this->is_group_moderator($user_id, $ag);

            ob_start();
            ?>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <div style="display:flex;align-items:center;gap:8px">
                    <a href="#" class="dgptm-forum-breadcrumb a dgptm-forum-back-btn" data-view="ags" data-id="0" style="display:inline-flex;align-items:center;gap:4px;font-size:12px;color:#0073aa;text-decoration:none">&larr; Zur&uuml;ck</a>
                    <span style="color:#ccc">|</span>
                    <span style="font-size:14px;font-weight:600;color:#1d2327"><?php echo esc_html($ag->name); ?></span>
                </div>
                <div style="display:flex;gap:6px;align-items:center;flex-shrink:0">
                    <?php
                        $is_ag_subscribed = class_exists('DGPTM_Forum_Notifications')
                            ? DGPTM_Forum_Notifications::is_subscribed($user_id, 'ag', $ag_id)
                            : false;
                    ?>
                    <a href="#" class="dgptm-forum-subscribe-btn <?php echo $is_ag_subscribed ? 'subscribed' : ''; ?>" data-scope="ag" data-scope-id="<?php echo esc_attr($ag_id); ?>" style="font-size:11px;color:<?php echo $is_ag_subscribed ? '#0073aa' : '#999'; ?>;text-decoration:none" title="Neue Threads per E-Mail erhalten"><?php echo $is_ag_subscribed ? '&#128276; Abonniert' : '&#128277; Abonnieren'; ?></a>
                    <?php if ($can_post) : ?>
                        <button type="button" class="dgptm-forum-btn dgptm-forum-btn-sm dgptm-forum-new-thread-btn" data-ag-id="<?php echo esc_attr($ag_id); ?>">+ Neuer Thread</button>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (false) : // placeholder to keep structure ?>
                <div class="dgptm-forum-actions">
                </div>
                <div class="dgptm-forum-compose-area" id="dgptm-forum-compose-thread" style="display:none;">
                    <form class="dgptm-forum-thread-form" enctype="multipart/form-data">
                        <input type="hidden" name="ag_id" value="<?php echo esc_attr($ag_id); ?>">
                        <h4>Neuen Thread erstellen</h4>
                        <div style="margin-bottom:10px">
                            <input type="text" name="title" placeholder="Titel" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;font-size:14px">
                        </div>
                        <div style="margin-bottom:10px">
                            <textarea name="content" rows="5" placeholder="Ihr Beitrag…" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;font-size:13px"></textarea>
                        </div>
                        <div style="margin-bottom:10px">
                            <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.docx" style="font-size:12px">
                        </div>
                        <div>
                            <button type="submit" class="dgptm-forum-btn dgptm-forum-btn-sm">Absenden</button>
                            <a href="#" class="dgptm-forum-cancel-compose" style="margin-left:8px;font-size:11px;color:#888">Abbrechen</a>
                        </div>
                    </form>
                </div>
            <?php elseif ($ag->group_type === 'closed' && !$is_member && !$is_admin) : ?>
                <div class="dgptm-forum-actions">
                    <button type="button" class="dgptm-forum-btn dgptm-forum-btn-secondary dgptm-forum-request-membership-btn" data-ag-id="<?php echo esc_attr($ag_id); ?>">
                        Aufnahme beantragen
                    </button>
                </div>
            <?php endif; ?>

            <div class="dgptm-forum-thread-list">
                <?php if (empty($threads)) : ?>
                    <p style="color:#999;font-size:13px;padding:20px 0">Noch keine Diskussionen in dieser Gruppe.</p>
                <?php else : ?>
                    <?php foreach ($threads as $thread) :
                        $author = get_userdata($thread->author_id);
                        $author_name = $author ? $author->display_name : 'Unbekannt';
                        $last_reply = $thread->last_reply_at
                            ? date_i18n('d.m.Y H:i', strtotime($thread->last_reply_at))
                            : date_i18n('d.m.Y H:i', strtotime($thread->created_at));
                        $pinned = $thread->is_pinned;
                        $closed = ($thread->status === 'closed');
                    ?>
                        <div class="dgptm-forum-thread-link" data-thread-id="<?php echo esc_attr($thread->id); ?>"
                             style="border:1px solid <?php echo $pinned ? '#d4e6f1' : '#e4e8ec'; ?>;border-radius:6px;padding:12px 14px;margin-bottom:8px;background:<?php echo $pinned ? '#f8fbfe' : '#fff'; ?>;cursor:pointer;transition:box-shadow .15s;<?php echo $pinned ? 'border-left:3px solid #2e86c1;' : ''; ?>">
                            <div style="display:flex;justify-content:space-between;align-items:center">
                                <div style="flex:1;min-width:0">
                                    <span style="font-size:14px;font-weight:500;color:#1d2327"><?php echo esc_html($thread->title); ?></span>
                                    <?php if ($pinned) : ?>
                                        <span style="font-size:10px;color:#2e86c1;margin-left:6px" title="Angepinnt">&#128204;</span>
                                    <?php endif; ?>
                                    <?php if ($closed) : ?>
                                        <span style="display:inline-block;padding:1px 5px;border-radius:6px;font-size:9px;font-weight:600;background:#fce4ec;color:#c62828;margin-left:4px;vertical-align:middle">geschlossen</span>
                                    <?php endif; ?>
                                    <div style="font-size:11px;color:#999;margin-top:2px">
                                        <?php echo esc_html($author_name); ?> &middot; <?php echo esc_html($last_reply); ?>
                                    </div>
                                </div>
                                <div style="display:flex;align-items:center;gap:10px;flex-shrink:0">
                                    <span style="font-size:11px;color:#888"><?php echo (int)$thread->reply_count; ?> Antw.</span>
                                    <span style="color:#ccc;font-size:16px">&rsaquo;</span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php

            return ob_get_clean();
        }

        // ----- Single Thread View ----------------------------------------

        /**
         * Render a single thread with all replies (nested).
         *
         * Breadcrumb: [Forum > Hauptgruppe-Name > Thread-Titel]
         *
         * @param int $thread_id
         * @return string HTML
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

            // Resolve AG via topic.
            $topic = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}topics WHERE id = %d",
                $thread->topic_id
            ));

            $ag      = null;
            $ag_name = '';
            $ag_id   = 0;
            if ($topic && !empty($topic->ag_id)) {
                $ag = DGPTM_Forum_AG_Manager::get_ag($topic->ag_id);
                if ($ag) {
                    $ag_name = $ag->name;
                    $ag_id   = (int) $ag->id;
                }
            }

            // Access check via AG.
            if ($ag && !$this->can_access_group($user_id, $ag)) {
                return '<p class="dgptm-forum-error">Kein Zugriff auf diesen Thread.</p>';
            }

            $can_post     = $ag ? $this->can_post_in_group($user_id, $ag) : false;
            $can_moderate = $ag ? $this->is_group_moderator($user_id, $ag) : DGPTM_Forum_Permissions::is_forum_admin($user_id);

            $thread_author      = get_userdata($thread->author_id);
            $thread_author_name = $thread_author ? $thread_author->display_name : 'Unbekannt';
            $thread_date        = date_i18n('d.m.Y H:i', strtotime($thread->created_at));

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
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px">
                <div style="display:flex;align-items:center;gap:6px;flex:1;min-width:0">
                    <?php if ($ag_id > 0) : ?>
                        <a href="#" class="dgptm-forum-back-btn" data-view="threads" data-id="<?php echo esc_attr($ag_id); ?>" style="display:inline-flex;align-items:center;gap:3px;font-size:12px;color:#0073aa;text-decoration:none;flex-shrink:0">&larr; <?php echo esc_html($ag_name); ?></a>
                        <span style="color:#ccc;flex-shrink:0">|</span>
                    <?php else : ?>
                        <a href="#" class="dgptm-forum-back-btn" data-view="ags" data-id="0" style="display:inline-flex;align-items:center;gap:3px;font-size:12px;color:#0073aa;text-decoration:none;flex-shrink:0">&larr; Forum</a>
                        <span style="color:#ccc;flex-shrink:0">|</span>
                    <?php endif; ?>
                    <span style="font-size:15px;font-weight:600;color:#1d2327;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo esc_html($thread->title); ?></span>
                    <?php if ($thread->is_pinned) : ?><span style="font-size:10px;color:#2e86c1;margin-left:4px">&#128204; Angepinnt</span><?php endif; ?>
                    <?php if ($thread->status === 'closed') : ?><span style="display:inline-block;padding:1px 5px;border-radius:6px;font-size:9px;font-weight:600;background:#fce4ec;color:#c62828;margin-left:4px;vertical-align:middle">geschlossen</span><?php endif; ?>
                </h3>
                <div style="display:flex;gap:6px;align-items:center;flex-shrink:0">
                    <?php
                        $is_thread_subscribed = class_exists('DGPTM_Forum_Notifications')
                            ? DGPTM_Forum_Notifications::is_subscribed($user_id, 'thread', $thread_id)
                            : false;
                    ?>
                    <a href="#" class="dgptm-forum-subscribe-btn <?php echo $is_thread_subscribed ? 'subscribed' : ''; ?>" data-scope="thread" data-scope-id="<?php echo esc_attr($thread_id); ?>" style="font-size:11px;color:<?php echo $is_thread_subscribed ? '#0073aa' : '#999'; ?>;text-decoration:none" title="Neue Antworten per E-Mail erhalten"><?php echo $is_thread_subscribed ? '&#128276; Abonniert' : '&#128277; Abonnieren'; ?></a>
                    <?php if ($can_moderate) : ?>
                        <button type="button" class="dgptm-forum-btn dgptm-forum-btn-sm dgptm-forum-toggle-pin-btn" data-thread-id="<?php echo esc_attr($thread->id); ?>" style="background:#5b6b7a !important;border-color:#5b6b7a !important"><?php echo $thread->is_pinned ? 'Losl&ouml;sen' : 'Anpinnen'; ?></button>
                        <button type="button" class="dgptm-forum-btn dgptm-forum-btn-sm dgptm-forum-close-thread-btn" data-thread-id="<?php echo esc_attr($thread->id); ?>" style="background:#5b6b7a !important;border-color:#5b6b7a !important"><?php echo $thread->status === 'closed' ? '&Ouml;ffnen' : 'Schlie&szlig;en'; ?></button>
                    <?php endif; ?>
                </div>
            </div>

            <div class="dgptm-forum-thread-detail">
                <div style="border:1px solid #e4e8ec;border-radius:6px;padding:14px;margin-bottom:10px;background:#f8fbfe" data-post-id="<?php echo esc_attr($thread->id); ?>" data-post-type="thread">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px">
                        <div>
                            <strong style="font-size:13px;color:#1d2327"><?php echo esc_html($thread_author_name); ?></strong>
                            <span style="font-size:11px;color:#999;margin-left:8px"><?php echo esc_html($thread_date); ?></span>
                        </div>
                        <div style="display:flex;gap:4px">
                            <?php if ($can_post && $thread->status !== 'closed') : ?>
                                <button type="button" class="dgptm-forum-btn dgptm-forum-btn-sm dgptm-forum-reply-btn" data-thread-id="<?php echo esc_attr($thread->id); ?>" data-parent-id="0" data-depth="1">Antworten</button>
                            <?php endif; ?>
                            <?php if ((int)$thread->author_id === $user_id || $can_moderate) : ?>
                                <button type="button" class="dgptm-forum-btn dgptm-forum-btn-sm secondary dgptm-forum-edit-btn" data-post-id="<?php echo esc_attr($thread->id); ?>" data-post-type="thread">Bearbeiten</button>
                            <?php endif; ?>
                            <?php if ($can_moderate) : ?>
                                <button type="button" class="dgptm-forum-btn dgptm-forum-btn-sm danger dgptm-forum-delete-btn" data-post-id="<?php echo esc_attr($thread->id); ?>" data-post-type="thread">L&ouml;schen</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="post-content" style="font-size:14px;line-height:1.6;color:#333"><?php echo wp_kses_post($thread->content); ?></div>
                    <?php $this->render_attachments($thread_attachments); ?>
                </div>

                <?php // ---- Replies ---- ?>
                <div class="dgptm-forum-replies">
                    <?php $this->render_reply_tree($reply_tree, $reply_attachments, $thread, $user_id, $can_post, $can_moderate); ?>
                </div>

                <?php // ---- Compose reply form at bottom ---- ?>
                <?php if ($can_post && $thread->status !== 'closed') : ?>
                    <div style="margin-top:16px;padding:14px;background:#f8f9fa;border-radius:6px;border:1px solid #e4e8ec">
                        <form class="dgptm-forum-reply-form" enctype="multipart/form-data">
                            <input type="hidden" name="thread_id" value="<?php echo esc_attr($thread->id); ?>">
                            <input type="hidden" name="parent_id" value="0">
                            <input type="hidden" name="depth" value="1">
                            <div style="font-size:12px;font-weight:600;color:#555;margin-bottom:6px">Antwort verfassen</div>
                            <textarea name="content" rows="3" placeholder="Ihre Antwort…" required style="width:100%;padding:8px;border:1px solid #ccc;border-radius:4px;font-size:13px;margin-bottom:6px"></textarea>
                            <div style="display:flex;justify-content:space-between;align-items:center">
                                <input type="file" name="attachments[]" multiple accept=".pdf,.jpg,.jpeg,.png,.docx" style="font-size:11px">
                                <button type="submit" class="dgptm-forum-btn dgptm-forum-btn-sm">Absenden</button>
                            </div>
                        </form>
                    </div>
                <?php elseif ($thread->status === 'closed') : ?>
                    <p style="font-size:12px;color:#999;margin-top:12px;font-style:italic">Dieser Thread ist geschlossen.</p>
                <?php endif; ?>
            </div>
            <?php

            return ob_get_clean();
        }

        // =============================================================
        //  Reply Tree Helpers
        // =============================================================

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
                $date = date_i18n('d.m.Y H:i', strtotime($reply->created_at));
                $atts = isset($reply_attachments[(int) $reply->id]) ? $reply_attachments[(int) $reply->id] : [];
                $next_depth = $depth + 1;
                ?>
                <?php $indent = min($depth * 20, 60); ?>
                <div class="dgptm-forum-post" data-post-id="<?php echo esc_attr($reply->id); ?>" data-post-type="reply"
                     style="border:1px solid #eee;border-radius:5px;padding:10px 12px;margin:6px 0 6px <?php echo $indent; ?>px;background:#fff;<?php echo $depth > 1 ? 'border-left:2px solid #d4e6f1;' : ''; ?>">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:4px">
                        <div>
                            <strong style="font-size:12px;color:#1d2327"><?php echo esc_html($author_name); ?></strong>
                            <span style="font-size:10px;color:#aaa;margin-left:6px"><?php echo esc_html($date); ?></span>
                        </div>
                        <div style="display:flex;gap:3px">
                            <?php if ($can_post && $thread->status !== 'closed' && $next_depth <= 3) : ?>
                                <button type="button" class="dgptm-forum-btn dgptm-forum-btn-sm dgptm-forum-reply-btn" data-thread-id="<?php echo esc_attr($thread->id); ?>" data-parent-id="<?php echo esc_attr($reply->id); ?>" data-depth="<?php echo esc_attr($next_depth); ?>">Antworten</button>
                            <?php endif; ?>
                            <?php if ((int)$reply->author_id === $user_id || $can_moderate) : ?>
                                <button type="button" class="dgptm-forum-btn dgptm-forum-btn-sm secondary dgptm-forum-edit-btn" data-post-id="<?php echo esc_attr($reply->id); ?>" data-post-type="reply">Bearbeiten</button>
                            <?php endif; ?>
                            <?php if ($can_moderate) : ?>
                                <button type="button" class="dgptm-forum-btn dgptm-forum-btn-sm danger dgptm-forum-delete-btn" data-post-id="<?php echo esc_attr($reply->id); ?>" data-post-type="reply">L&ouml;schen</button>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="post-content" style="font-size:13px;line-height:1.5;color:#444"><?php echo wp_kses_post($reply->content); ?></div>
                    <?php $this->render_attachments($atts); ?>
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
         * POST: ag_id, title, content, files (optional)
         */
        private function create_thread() {
            global $wpdb;

            $user_id = get_current_user_id();
            $prefix  = $wpdb->prefix . 'dgptm_forum_';
            $ag_id   = isset($_POST['ag_id']) ? absint($_POST['ag_id']) : 0;
            $title   = isset($_POST['title']) ? sanitize_text_field($_POST['title']) : '';
            $content = isset($_POST['content']) ? wp_kses_post($_POST['content']) : '';

            if (empty($ag_id) || empty($title) || empty($content)) {
                wp_send_json_error(['message' => 'Hauptgruppe, Titel und Inhalt sind erforderlich.']);
            }

            // Load AG and check permission.
            $ag = DGPTM_Forum_AG_Manager::get_ag($ag_id);
            if (!$ag) {
                wp_send_json_error(['message' => 'Hauptgruppe nicht gefunden.']);
            }

            if (!$this->can_post_in_group($user_id, $ag)) {
                wp_send_json_error(['message' => 'Keine Berechtigung in dieser Hauptgruppe zu posten.']);
            }

            // Get or create default topic for this AG.
            $topic_id = $this->get_or_create_default_topic($ag_id);
            if (!$topic_id) {
                wp_send_json_error(['message' => 'Interner Fehler: Standard-Thema konnte nicht erstellt werden.']);
            }

            $now = current_time('mysql');

            $inserted = $wpdb->insert("{$prefix}threads", [
                'topic_id'      => $topic_id,
                'title'         => $title,
                'content'       => $content,
                'author_id'     => $user_id,
                'status'        => 'open',
                'is_pinned'     => 0,
                'reply_count'   => 0,
                'last_reply_at' => $now,
                'last_reply_by' => $user_id,
                'created_at'    => $now,
                'updated_at'    => $now,
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

            // E-Mail-Benachrichtigung an AG-Abonnenten
            if ( class_exists( 'DGPTM_Forum_Notifications' ) ) {
                DGPTM_Forum_Notifications::notify_new_thread( $thread_id, $ag_id );
            }

            wp_send_json_success([
                'message'   => 'Thread erstellt.',
                'thread_id' => $thread_id,
            ]);
        }

        /**
         * Create a reply.
         *
         * POST: thread_id, content, parent_id (0 for direct), depth, files (optional)
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

            // Get thread.
            $thread = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM {$prefix}threads WHERE id = %d",
                $thread_id
            ));

            if (!$thread) {
                wp_send_json_error(['message' => 'Thread nicht gefunden.']);
            }

            if ($thread->status === 'closed') {
                // Allow moderators to post in closed threads.
                $ag = $this->get_ag_for_thread($thread_id);
                if (!$ag || !$this->is_group_moderator($user_id, $ag)) {
                    wp_send_json_error(['message' => 'Dieser Thread ist geschlossen.']);
                }
            }

            // Check posting permission via AG.
            $ag = $this->get_ag_for_thread($thread_id);
            if ($ag && !$this->can_post_in_group($user_id, $ag)) {
                wp_send_json_error(['message' => 'Keine Berechtigung in dieser Hauptgruppe zu posten.']);
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

            // E-Mail-Benachrichtigung an Thread-/AG-Abonnenten
            if ( class_exists( 'DGPTM_Forum_Notifications' ) ) {
                DGPTM_Forum_Notifications::notify_new_reply( $reply_id, $thread_id );
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
                wp_send_json_error(['message' => 'Ungültiger Beitragstyp.']);
            }

            $table_suffix = $post_type === 'thread' ? 'threads' : 'replies';

            // Permission: own post OR moderator of the AG.
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
         * Only moderators can delete.
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
                wp_send_json_error(['message' => 'Ungültiger Beitragstyp.']);
            }

            $table_suffix = $post_type === 'thread' ? 'threads' : 'replies';

            // Only moderators can delete.
            if (!$this->check_delete_permission($user_id, $table_suffix, $post_id)) {
                wp_send_json_error(['message' => 'Keine Berechtigung diesen Beitrag zu löschen.']);
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
                wp_send_json_error(['message' => 'Beitrag konnte nicht gelöscht werden.']);
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

            wp_send_json_success(['message' => 'Beitrag gelöscht.']);
        }

        /**
         * Check edit permission: own post OR moderator of the AG.
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
                $thread = $wpdb->get_row($wpdb->prepare(
                    "SELECT author_id FROM {$prefix}threads WHERE id = %d",
                    $post_id
                ));
                if (!$thread) {
                    return false;
                }
                // Own post?
                if ((int) $thread->author_id === (int) $user_id) {
                    return true;
                }
                // Moderator?
                $ag = $this->get_ag_for_thread($post_id);
                return $ag ? $this->is_group_moderator($user_id, $ag) : DGPTM_Forum_Permissions::is_forum_admin($user_id);
            }

            // For replies: get author_id and resolve AG via thread.
            $reply = $wpdb->get_row($wpdb->prepare(
                "SELECT r.author_id, r.thread_id
                 FROM {$prefix}replies r
                 WHERE r.id = %d",
                $post_id
            ));

            if (!$reply) {
                return false;
            }

            if ((int) $reply->author_id === (int) $user_id) {
                return true;
            }

            $ag = $this->get_ag_for_thread($reply->thread_id);
            return $ag ? $this->is_group_moderator($user_id, $ag) : DGPTM_Forum_Permissions::is_forum_admin($user_id);
        }

        /**
         * Check delete permission: moderator only.
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
                $ag = $this->get_ag_for_thread($post_id);
                return $ag ? $this->is_group_moderator($user_id, $ag) : DGPTM_Forum_Permissions::is_forum_admin($user_id);
            }

            // For replies: resolve AG via thread.
            $reply = $wpdb->get_row($wpdb->prepare(
                "SELECT thread_id FROM {$prefix}replies WHERE id = %d",
                $post_id
            ));

            if (!$reply) {
                return false;
            }

            $ag = $this->get_ag_for_thread($reply->thread_id);
            return $ag ? $this->is_group_moderator($user_id, $ag) : DGPTM_Forum_Permissions::is_forum_admin($user_id);
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
         * POST: scope (forum|ag|thread), scope_id
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
         * POST: scope (forum|ag|thread), scope_id
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
        //  Membership Request
        // =============================================================

        /**
         * Request membership in a closed Hauptgruppe.
         *
         * POST: ag_id
         */
        private function request_membership() {
            global $wpdb;

            $user_id = get_current_user_id();
            $prefix  = $wpdb->prefix . 'dgptm_forum_';
            $ag_id   = isset($_POST['ag_id']) ? absint($_POST['ag_id']) : 0;

            if (empty($ag_id)) {
                wp_send_json_error(['message' => 'Hauptgruppen-ID ist erforderlich.']);
            }

            $ag = DGPTM_Forum_AG_Manager::get_ag($ag_id);
            if (!$ag) {
                wp_send_json_error(['message' => 'Hauptgruppe nicht gefunden.']);
            }

            // Check if already a member.
            if (DGPTM_Forum_Permissions::is_ag_member($user_id, $ag_id)) {
                wp_send_json_error(['message' => 'Sie sind bereits Mitglied dieser Hauptgruppe.']);
            }

            // Check if already pending.
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$prefix}ag_members WHERE ag_id = %d AND user_id = %d AND role = 'pending'",
                $ag_id,
                $user_id
            ));

            if ((int) $existing > 0) {
                wp_send_json_error(['message' => 'Ihre Anfrage wurde bereits gestellt und wird bearbeitet.']);
            }

            $wpdb->insert("{$prefix}ag_members", [
                'ag_id'     => $ag_id,
                'user_id'   => $user_id,
                'role'      => 'pending',
                'joined_at' => current_time('mysql'),
            ], ['%d', '%d', '%s', '%s']);

            if ($wpdb->insert_id) {
                // E-Mail an Moderator
                if ( class_exists( 'DGPTM_Forum_Notifications' ) ) {
                    DGPTM_Forum_Notifications::notify_membership_request( $ag_id, $user_id );
                }
                wp_send_json_success(['message' => 'Aufnahmeantrag gestellt. Ein Moderator wird Ihren Antrag pr\xC3\xBCfen.']);
            }

            wp_send_json_error(['message' => 'Fehler beim Erstellen des Aufnahmeantrags.']);
        }

        // =============================================================
        //  Admin Methods
        // =============================================================

        /**
         * Load admin tab content.
         *
         * POST: tab (ags|admins)
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
                    case 'admins':
                        $html = DGPTM_Forum_Admin_Renderer::render_tab_admins();
                        break;
                    default:
                        wp_send_json_error(['message' => 'Unbekannter Tab.']);
                        return;
                }
                wp_send_json_success(['html' => $html]);
            }

            // Fallback if renderer class not yet available.
            wp_send_json_error(['message' => 'Admin-Renderer nicht verfügbar.']);
        }

        /**
         * Create or update an AG.
         *
         * POST: ag_id (0=create), name, description, group_type, is_hidden, moderator_id, leader_user_id
         */
        private function admin_save_ag() {
            if (!$this->require_admin()) {
                wp_send_json_error(['message' => 'Keine Berechtigung.']);
            }

            $ag_id = isset($_POST['ag_id']) ? absint($_POST['ag_id']) : 0;
            $data  = [
                'name'           => isset($_POST['name']) ? $_POST['name'] : '',
                'description'    => isset($_POST['description']) ? $_POST['description'] : '',
                'group_type'     => isset($_POST['group_type']) ? sanitize_text_field($_POST['group_type']) : 'open',
                'is_hidden'      => !empty($_POST['is_hidden']) ? 1 : 0,
                'moderator_id'   => isset($_POST['moderator_id']) ? absint($_POST['moderator_id']) : 0,
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

            // Moderator automatisch abonnieren
            $mod_id = absint( $data['moderator_id'] ?? 0 );
            $new_ag_id = $ag_id === 0 ? $result : $ag_id;
            if ( $mod_id && class_exists( 'DGPTM_Forum_Notifications' ) ) {
                DGPTM_Forum_Notifications::auto_subscribe_moderator( $new_ag_id, $mod_id );
            }

            wp_send_json_success([
                'message' => $ag_id === 0 ? 'Hauptgruppe erstellt.' : 'Hauptgruppe aktualisiert.',
                'ag_id'   => $new_ag_id,
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

            wp_send_json_success(['message' => 'AG gelöscht.']);
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

            wp_send_json_success(['message' => 'Mitglied hinzugefügt.']);
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
         * Search users for autocomplete.
         *
         * POST: term
         * Returns: name (display_name or user_login) + email
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
            global $wpdb;
            $prefix    = $wpdb->prefix . 'dgptm_forum_';
            $thread_id = isset($_POST['thread_id']) ? absint($_POST['thread_id']) : 0;
            $user_id   = get_current_user_id();

            if (empty($thread_id)) {
                wp_send_json_error(['message' => 'Thread-ID ist erforderlich.']);
            }

            // Check moderator permission via AG.
            $ag = $this->get_ag_for_thread($thread_id);
            if (!$ag || !$this->is_group_moderator($user_id, $ag)) {
                if (!DGPTM_Forum_Permissions::is_forum_admin($user_id)) {
                    wp_send_json_error(['message' => 'Keine Berechtigung.']);
                }
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
            global $wpdb;
            $prefix    = $wpdb->prefix . 'dgptm_forum_';
            $thread_id = isset($_POST['thread_id']) ? absint($_POST['thread_id']) : 0;
            $user_id   = get_current_user_id();

            if (empty($thread_id)) {
                wp_send_json_error(['message' => 'Thread-ID ist erforderlich.']);
            }

            // Check moderator permission via AG.
            $ag = $this->get_ag_for_thread($thread_id);
            if (!$ag || !$this->is_group_moderator($user_id, $ag)) {
                if (!DGPTM_Forum_Permissions::is_forum_admin($user_id)) {
                    wp_send_json_error(['message' => 'Keine Berechtigung.']);
                }
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
                'message' => $new_status === 'closed' ? 'Thread geschlossen.' : 'Thread wieder geöffnet.',
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
