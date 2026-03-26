<?php
/**
 * DGPTM Forum – Admin Panel Renderer
 *
 * Statische Klasse zum Rendern der Admin-Panel-HTML-Ausgabe
 * für den [dgptm-forum-admin] Shortcode.
 *
 * @package DGPTM_Forum
 * @since   1.0.0
 */

if (!defined('ABSPATH')) exit;

class DGPTM_Forum_Admin_Renderer {

    // ==================================================================
    //  Tab: AGs verwalten
    // ==================================================================

    /**
     * Renders the AG management tab.
     *
     * @return string HTML output.
     */
    public static function render_tab_ags() {
        ob_start();

        $ags = DGPTM_Forum_AG_Manager::get_all_ags('active');
        ?>
        <div class="dgptm-forum-admin-section">

            <!-- Neue AG erstellen (collapsed) -->
            <div class="dgptm-forum-admin-collapsible">
                <h3>
                    <a href="#" class="dgptm-forum-admin-toggle" data-target="dgptm-forum-new-ag-form">
                        + Neue Hauptgruppe erstellen
                    </a>
                </h3>
                <div id="dgptm-forum-new-ag-form" style="display:none;">
                    <form class="dgptm-forum-admin-ag-form dgptm-forum-admin-form">
                        <input type="hidden" name="ag_id" value="0">

                        <label for="new-ag-name">Name</label>
                        <input type="text" id="new-ag-name" name="name" required placeholder="Name der Hauptgruppe">

                        <label for="new-ag-description">Beschreibung</label>
                        <textarea id="new-ag-description" name="description" rows="3" placeholder="Beschreibung der Hauptgruppe"></textarea>

                        <button type="submit" class="dgptm-forum-btn">Hauptgruppe erstellen</button>
                    </form>
                </div>
            </div>

            <hr>

            <!-- AG-Liste -->
            <?php if (empty($ags)) : ?>
                <p>Keine Hauptgruppen vorhanden.</p>
            <?php else : ?>
                <?php foreach ($ags as $ag) :
                    $members      = DGPTM_Forum_AG_Manager::get_ag_members($ag->id);
                    $member_count = count($members);
                    $leader_name  = '';

                    if (!empty($ag->leader_user_id)) {
                        $leader_user = get_userdata($ag->leader_user_id);
                        if ($leader_user) {
                            $leader_name = $leader_user->display_name;
                        }
                    }
                ?>
                <div class="dgptm-forum-admin-ag-item" data-ag-id="<?php echo esc_attr($ag->id); ?>">
                    <div class="dgptm-forum-admin-ag-header">
                        <div class="dgptm-forum-admin-ag-info">
                            <strong><?php echo esc_html($ag->name); ?></strong>
                            <span class="dgptm-forum-admin-ag-meta">
                                <?php echo esc_html($member_count); ?> Mitglied<?php echo $member_count !== 1 ? 'er' : ''; ?>
                                <?php if ($leader_name) : ?>
                                    &middot; Leiter: <?php echo esc_html($leader_name); ?>
                                <?php endif; ?>
                                &middot; Status: <?php echo esc_html($ag->status); ?>
                            </span>
                        </div>
                        <div class="dgptm-forum-admin-ag-actions">
                            <a href="#" class="dgptm-forum-btn secondary dgptm-forum-admin-toggle" data-target="dgptm-forum-edit-ag-<?php echo esc_attr($ag->id); ?>">Bearbeiten</a>
                            <a href="#" class="dgptm-forum-btn danger dgptm-forum-admin-delete-ag" data-ag-id="<?php echo esc_attr($ag->id); ?>">Löschen</a>
                        </div>
                    </div>

                    <!-- Inline Edit Form (hidden) -->
                    <div id="dgptm-forum-edit-ag-<?php echo esc_attr($ag->id); ?>" style="display:none;" class="dgptm-forum-admin-edit-form">
                        <form class="dgptm-forum-admin-ag-form dgptm-forum-admin-form">
                            <input type="hidden" name="ag_id" value="<?php echo esc_attr($ag->id); ?>">

                            <label>Name</label>
                            <input type="text" name="name" value="<?php echo esc_attr($ag->name); ?>" required>

                            <label>Beschreibung</label>
                            <textarea name="description" rows="3"><?php echo esc_html($ag->description); ?></textarea>

                            <button type="submit" class="dgptm-forum-btn">Speichern</button>
                        </form>
                    </div>

                    <!-- Member List -->
                    <div class="dgptm-forum-member-list">
                        <?php if (empty($members)) : ?>
                            <p class="dgptm-forum-admin-no-members">Keine Mitglieder.</p>
                        <?php else : ?>
                            <?php foreach ($members as $member) : ?>
                                <div class="member-item">
                                    <span>
                                        <?php echo esc_html($member->display_name); ?>
                                        <span class="member-role">(<?php echo esc_html($member->role); ?>)</span>
                                    </span>
                                    <a href="#" class="dgptm-forum-btn danger dgptm-forum-admin-remove-member"
                                       data-ag-id="<?php echo esc_attr($ag->id); ?>"
                                       data-user-id="<?php echo esc_attr($member->user_id); ?>"
                                       title="Mitglied entfernen">&times;</a>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Add Member (User Search) -->
                    <div class="dgptm-forum-user-search-wrap" data-context="ag-member" data-target-id="<?php echo esc_attr($ag->id); ?>">
                        <input type="text" class="dgptm-forum-user-search" placeholder="Mitglied suchen...">
                        <div class="dgptm-forum-user-results"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    // ==================================================================
    //  Tab: Themen verwalten
    // ==================================================================

    /**
     * Renders the topic management tab.
     *
     * @return string HTML output.
     */
    public static function render_tab_topics() {
        ob_start();
        global $wpdb;

        $ags         = DGPTM_Forum_AG_Manager::get_all_ags('active');
        $topics_tbl  = $wpdb->prefix . 'dgptm_forum_topics';
        $all_topics  = $wpdb->get_results("SELECT * FROM {$topics_tbl} ORDER BY ag_id ASC, sort_order ASC, title ASC");

        // Group topics by ag_id
        $grouped = [];
        foreach ($all_topics as $topic) {
            $key = $topic->ag_id ? (int) $topic->ag_id : 0;
            $grouped[$key][] = $topic;
        }

        // Build AG lookup
        $ag_map = [];
        foreach ($ags as $ag) {
            $ag_map[(int) $ag->id] = $ag;
        }
        ?>
        <div class="dgptm-forum-admin-section">

            <!-- Neues Thema erstellen -->
            <div class="dgptm-forum-admin-collapsible">
                <h3>
                    <a href="#" class="dgptm-forum-admin-toggle" data-target="dgptm-forum-new-topic-form">
                        + Neues Thema erstellen
                    </a>
                </h3>
                <div id="dgptm-forum-new-topic-form" style="display:none;">
                    <form class="dgptm-forum-admin-topic-form dgptm-forum-admin-form">
                        <input type="hidden" name="topic_id" value="0">

                        <label for="new-topic-title">Titel</label>
                        <input type="text" id="new-topic-title" name="title" required placeholder="Themen-Titel">

                        <label for="new-topic-description">Beschreibung</label>
                        <textarea id="new-topic-description" name="description" rows="3" placeholder="Beschreibung des Themas"></textarea>

                        <label for="new-topic-ag">Hauptgruppe</label>
                        <select id="new-topic-ag" name="ag_id">
                            <option value="0">Keine Hauptgruppe (offen)</option>
                            <?php foreach ($ags as $ag) : ?>
                                <option value="<?php echo esc_attr($ag->id); ?>"><?php echo esc_html($ag->name); ?></option>
                            <?php endforeach; ?>
                        </select>

                        <label for="new-topic-access">Zugangsmodus</label>
                        <select id="new-topic-access" name="access_mode">
                            <option value="open">Offen (open)</option>
                            <option value="ag_only">Nur Gruppenmitglieder (ag_only)</option>
                            <option value="ag_plus">Gruppe + Einzelzugriff (ag_plus)</option>
                        </select>

                        <label>Verantwortliche/r</label>
                        <div class="dgptm-forum-user-search-wrap" data-context="topic-responsible">
                            <input type="text" class="dgptm-forum-user-search" placeholder="Benutzer suchen...">
                            <input type="hidden" name="responsible_id" value="0">
                            <div class="dgptm-forum-user-results"></div>
                        </div>

                        <button type="submit" class="dgptm-forum-btn" style="margin-top:8px;">Thema erstellen</button>
                    </form>
                </div>
            </div>

            <hr>

            <!-- Offene Themen (no AG) -->
            <?php if (!empty($grouped[0])) : ?>
                <h3 class="dgptm-forum-admin-group-title">Offene Themen</h3>
                <?php self::render_topic_list($grouped[0], $ags); ?>
            <?php endif; ?>

            <!-- Topics grouped by AG -->
            <?php foreach ($ag_map as $ag_id => $ag) : ?>
                <?php if (!empty($grouped[$ag_id])) : ?>
                    <h3 class="dgptm-forum-admin-group-title"><?php echo esc_html($ag->name); ?></h3>
                    <?php self::render_topic_list($grouped[$ag_id], $ags); ?>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if (empty($all_topics)) : ?>
                <p>Keine Themen vorhanden.</p>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Renders a list of topics with edit/delete actions.
     *
     * @param array $topics Array of topic rows.
     * @param array $ags    Array of all AGs (for the select dropdown in edit forms).
     * @return void
     */
    private static function render_topic_list($topics, $ags) {
        global $wpdb;
        $access_tbl = $wpdb->prefix . 'dgptm_forum_topic_access';

        foreach ($topics as $topic) :
            $responsible_name = '';
            if (!empty($topic->responsible_id)) {
                $resp_user = get_userdata($topic->responsible_id);
                if ($resp_user) {
                    $responsible_name = $resp_user->display_name;
                }
            }

            $access_mode_labels = [
                'open'    => 'Offen',
                'ag_only' => 'Nur Gruppe',
                'ag_plus' => 'Gruppe + Einzel',
            ];
            $badge_label = isset($access_mode_labels[$topic->access_mode])
                ? $access_mode_labels[$topic->access_mode]
                : esc_html($topic->access_mode);

            $badge_class = 'open';
            if ($topic->access_mode === 'ag_only') {
                $badge_class = 'ag';
            } elseif ($topic->access_mode === 'ag_plus') {
                $badge_class = 'ag';
            }
        ?>
        <div class="dgptm-forum-admin-topic-item" data-topic-id="<?php echo esc_attr($topic->id); ?>">
            <div class="dgptm-forum-admin-topic-header">
                <div class="dgptm-forum-admin-topic-info">
                    <strong><?php echo esc_html($topic->title); ?></strong>
                    <span class="dgptm-forum-badge <?php echo esc_attr($badge_class); ?>"><?php echo esc_html($badge_label); ?></span>
                    <span class="dgptm-forum-admin-topic-meta">
                        <?php if ($responsible_name) : ?>
                            Verantwortlich: <?php echo esc_html($responsible_name); ?> &middot;
                        <?php endif; ?>
                        <?php echo esc_html((int) $topic->thread_count); ?> Thread<?php echo (int) $topic->thread_count !== 1 ? 's' : ''; ?>
                    </span>
                </div>
                <div class="dgptm-forum-admin-topic-actions">
                    <a href="#" class="dgptm-forum-btn secondary dgptm-forum-admin-toggle" data-target="dgptm-forum-edit-topic-<?php echo esc_attr($topic->id); ?>">Bearbeiten</a>
                    <a href="#" class="dgptm-forum-btn danger dgptm-forum-admin-delete-topic" data-topic-id="<?php echo esc_attr($topic->id); ?>">Löschen</a>
                </div>
            </div>

            <!-- Inline Edit Form (hidden) -->
            <div id="dgptm-forum-edit-topic-<?php echo esc_attr($topic->id); ?>" style="display:none;" class="dgptm-forum-admin-edit-form">
                <form class="dgptm-forum-admin-topic-form dgptm-forum-admin-form">
                    <input type="hidden" name="topic_id" value="<?php echo esc_attr($topic->id); ?>">

                    <label>Titel</label>
                    <input type="text" name="title" value="<?php echo esc_attr($topic->title); ?>" required>

                    <label>Beschreibung</label>
                    <textarea name="description" rows="3"><?php echo esc_html($topic->description); ?></textarea>

                    <label>Hauptgruppe</label>
                    <select name="ag_id">
                        <option value="0" <?php selected(empty($topic->ag_id)); ?>>Keine Hauptgruppe (offen)</option>
                        <?php foreach ($ags as $ag) : ?>
                            <option value="<?php echo esc_attr($ag->id); ?>" <?php selected((int) $topic->ag_id, (int) $ag->id); ?>>
                                <?php echo esc_html($ag->name); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <label>Zugangsmodus</label>
                    <select name="access_mode">
                        <option value="open" <?php selected($topic->access_mode, 'open'); ?>>Offen (open)</option>
                        <option value="ag_only" <?php selected($topic->access_mode, 'ag_only'); ?>>Nur Gruppenmitglieder (ag_only)</option>
                        <option value="ag_plus" <?php selected($topic->access_mode, 'ag_plus'); ?>>Gruppe + Einzelzugriff (ag_plus)</option>
                    </select>

                    <label>Verantwortliche/r</label>
                    <div class="dgptm-forum-user-search-wrap" data-context="topic-responsible">
                        <input type="text" class="dgptm-forum-user-search" placeholder="Benutzer suchen..." value="<?php echo esc_attr($responsible_name); ?>">
                        <input type="hidden" name="responsible_id" value="<?php echo esc_attr($topic->responsible_id); ?>">
                        <div class="dgptm-forum-user-results"></div>
                    </div>

                    <button type="submit" class="dgptm-forum-btn" style="margin-top:8px;">Speichern</button>
                </form>
            </div>

            <?php
            // For ag_plus topics: show individual access list
            if ($topic->access_mode === 'ag_plus') :
                $access_users = $wpdb->get_results($wpdb->prepare(
                    "SELECT a.user_id, u.display_name, u.user_email
                     FROM {$access_tbl} a
                     JOIN {$wpdb->users} u ON u.ID = a.user_id
                     WHERE a.topic_id = %d
                     ORDER BY u.display_name ASC",
                    $topic->id
                ));
            ?>
                <div class="dgptm-forum-admin-access-section">
                    <h4>Einzelzugriff</h4>
                    <?php if (empty($access_users)) : ?>
                        <p class="dgptm-forum-admin-no-members">Keine individuellen Zugriffsrechte vergeben.</p>
                    <?php else : ?>
                        <div class="dgptm-forum-member-list">
                            <?php foreach ($access_users as $au) : ?>
                                <div class="member-item">
                                    <span><?php echo esc_html($au->display_name); ?> (<?php echo esc_html($au->user_email); ?>)</span>
                                    <a href="#" class="dgptm-forum-btn danger dgptm-forum-admin-revoke-access"
                                       data-topic-id="<?php echo esc_attr($topic->id); ?>"
                                       data-user-id="<?php echo esc_attr($au->user_id); ?>"
                                       title="Zugriff entfernen">&times;</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Add individual access -->
                    <div class="dgptm-forum-user-search-wrap" data-context="topic-access" data-target-id="<?php echo esc_attr($topic->id); ?>">
                        <input type="text" class="dgptm-forum-user-search" placeholder="Benutzer suchen...">
                        <div class="dgptm-forum-user-results"></div>
                    </div>
                </div>
            <?php endif; ?>

        </div>
        <?php
        endforeach;
    }

    // ==================================================================
    //  Tab: Forum-Admins
    // ==================================================================

    /**
     * Renders the forum admin management tab.
     *
     * @return string HTML output.
     */
    public static function render_tab_admins() {
        ob_start();

        $admins = DGPTM_Forum_Permissions::get_forum_admins();
        ?>
        <div class="dgptm-forum-admin-section">

            <h3>Forum-Admin hinzufügen</h3>
            <div class="dgptm-forum-user-search-wrap" data-context="forum-admin">
                <input type="text" class="dgptm-forum-user-search" placeholder="Benutzer suchen...">
                <div class="dgptm-forum-user-results"></div>
            </div>

            <hr>

            <h3>Aktuelle Forum-Admins</h3>
            <?php if (empty($admins)) : ?>
                <p>Keine Forum-Admins konfiguriert. WordPress-Administratoren haben automatisch Zugriff.</p>
            <?php else : ?>
                <div class="dgptm-forum-member-list">
                    <?php foreach ($admins as $admin) : ?>
                        <div class="member-item">
                            <span>
                                <?php echo esc_html($admin->display_name); ?>
                                <span class="member-role">(<?php echo esc_html($admin->user_email); ?>)</span>
                            </span>
                            <a href="#" class="dgptm-forum-btn danger dgptm-forum-admin-remove-admin"
                               data-user-id="<?php echo esc_attr($admin->ID); ?>"
                               title="Admin-Rechte entfernen">&times;</a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }

    // ==================================================================
    //  Tab: Moderation
    // ==================================================================

    /**
     * Renders the thread moderation tab.
     *
     * @return string HTML output.
     */
    public static function render_tab_moderation() {
        ob_start();
        global $wpdb;

        $threads_tbl = $wpdb->prefix . 'dgptm_forum_threads';
        $topics_tbl  = $wpdb->prefix . 'dgptm_forum_topics';

        $threads = $wpdb->get_results(
            "SELECT t.*, tp.title AS topic_title
             FROM {$threads_tbl} t
             LEFT JOIN {$topics_tbl} tp ON tp.id = t.topic_id
             ORDER BY t.created_at DESC
             LIMIT 50"
        );

        $status_labels = [
            'open'   => 'Offen',
            'closed' => 'Geschlossen',
        ];
        ?>
        <div class="dgptm-forum-admin-section">

            <h3>Threads moderieren</h3>

            <?php if (empty($threads)) : ?>
                <p>Keine Threads vorhanden.</p>
            <?php else : ?>
                <table class="dgptm-forum-admin-table" style="width:100%;border-collapse:collapse;">
                    <thead>
                        <tr>
                            <th style="text-align:left;padding:8px;border-bottom:2px solid #ccc;">Titel</th>
                            <th style="text-align:left;padding:8px;border-bottom:2px solid #ccc;">Thema</th>
                            <th style="text-align:left;padding:8px;border-bottom:2px solid #ccc;">Autor</th>
                            <th style="text-align:left;padding:8px;border-bottom:2px solid #ccc;">Datum</th>
                            <th style="text-align:left;padding:8px;border-bottom:2px solid #ccc;">Status</th>
                            <th style="text-align:right;padding:8px;border-bottom:2px solid #ccc;">Aktionen</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($threads as $thread) :
                            $author = get_userdata($thread->author_id);
                            $author_name = $author ? $author->display_name : 'Unbekannt';

                            $is_pinned = !empty($thread->is_pinned);
                            $is_closed = ($thread->status === 'closed');

                            $status_text = isset($status_labels[$thread->status])
                                ? $status_labels[$thread->status]
                                : esc_html($thread->status);

                            if ($is_pinned) {
                                $status_text .= ' / Angepinnt';
                            }
                        ?>
                        <tr style="border-bottom:1px solid #eee;">
                            <td style="padding:8px;">
                                <?php if ($is_pinned) : ?>
                                    <span title="Angepinnt" style="margin-right:4px;">&#128204;</span>
                                <?php endif; ?>
                                <?php echo esc_html($thread->title); ?>
                            </td>
                            <td style="padding:8px;"><?php echo esc_html($thread->topic_title ?: '-'); ?></td>
                            <td style="padding:8px;"><?php echo esc_html($author_name); ?></td>
                            <td style="padding:8px;"><?php echo esc_html(date_i18n('d.m.Y H:i', strtotime($thread->created_at))); ?></td>
                            <td style="padding:8px;">
                                <span class="dgptm-forum-badge <?php echo $is_closed ? 'closed' : 'open'; ?>">
                                    <?php echo esc_html($status_text); ?>
                                </span>
                            </td>
                            <td style="padding:8px;text-align:right;white-space:nowrap;">
                                <a href="#" class="dgptm-forum-btn secondary dgptm-forum-admin-pin"
                                   data-thread-id="<?php echo esc_attr($thread->id); ?>"
                                   title="<?php echo $is_pinned ? 'Lösen' : 'Anpinnen'; ?>">
                                    <?php echo $is_pinned ? 'Lösen' : 'Anpinnen'; ?>
                                </a>
                                <a href="#" class="dgptm-forum-btn secondary dgptm-forum-admin-close"
                                   data-thread-id="<?php echo esc_attr($thread->id); ?>"
                                   title="<?php echo $is_closed ? 'Wieder öffnen' : 'Schließen'; ?>">
                                    <?php echo $is_closed ? 'Öffnen' : 'Schließen'; ?>
                                </a>
                                <a href="#" class="dgptm-forum-btn danger dgptm-forum-admin-delete-thread"
                                   data-thread-id="<?php echo esc_attr($thread->id); ?>"
                                   title="Thread löschen">Löschen</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </div>
        <?php
        return ob_get_clean();
    }
}
