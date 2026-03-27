<?php
/**
 * Forum Notifications — Abo-Verwaltung + konfigurierbarer E-Mail-Versand.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class DGPTM_Forum_Notifications {

    const OPT_TEMPLATES = 'dgptm_forum_mail_templates';

    /* ============================================================
     *  Standard-Mail-Templates
     * ============================================================ */

    public static function default_templates() {
        return [
            'new_thread_subject' => '[DGPTM Forum] Neuer Thread in "{gruppe}"',
            'new_thread_body'    => "Hallo {empfaenger},\n\n{autor} hat einen neuen Thread erstellt:\n\n\"{titel}\"\n\n---\n{auszug}\n---\n\nZum Forum: {link}\n\nSie erhalten diese Mail, weil Sie die Hauptgruppe \"{gruppe}\" abonniert haben.\n\n{unsubscribe}\n\n-- DGPTM Forum",
            'new_reply_subject'  => '[DGPTM Forum] Neue Antwort in "{titel}"',
            'new_reply_body'     => "Hallo {empfaenger},\n\n{autor} hat eine neue Antwort geschrieben:\n\nThread: \"{titel}\"\n\n---\n{auszug}\n---\n\nZum Forum: {link}\n\nSie erhalten diese Mail, weil Sie den Thread \"{titel}\" abonniert haben.\n\n{unsubscribe}\n\n-- DGPTM Forum",
            'membership_subject' => '[DGPTM Forum] Aufnahmeantrag für "{gruppe}"',
            'membership_body'    => "Hallo {empfaenger},\n\n{autor} ({email}) möchte der Hauptgruppe \"{gruppe}\" beitreten.\n\nBitte prüfen Sie den Antrag im Forum-Verwaltungsbereich.\n\n{link}\n\n{unsubscribe}\n\n-- DGPTM Forum",
            'mention_subject'    => '[DGPTM Forum] {autor} hat Sie erwähnt',
            'mention_body'       => "Hallo {empfaenger},\n\n{autor} hat Sie in einem Beitrag erwähnt:\n\n\"{titel}\"\n\n---\n{auszug}\n---\n\nZum Forum: {link}\n\n{unsubscribe}\n\n-- DGPTM Forum",
        ];
    }

    public static function get_templates() {
        $saved = get_option( self::OPT_TEMPLATES, [] );
        return wp_parse_args( $saved, self::default_templates() );
    }

    public static function save_templates( $templates ) {
        update_option( self::OPT_TEMPLATES, $templates, false );
    }

    /* ============================================================
     *  Platzhalter ersetzen
     * ============================================================ */

    private static function replace_placeholders( $text, $vars ) {
        foreach ( $vars as $key => $val ) {
            $text = str_replace( '{' . $key . '}', $val, $text );
        }
        return $text;
    }

    /* ============================================================
     *  Abo-Verwaltung
     * ============================================================ */

    public static function subscribe( $user_id, $scope, $scope_id = 0 ) {
        global $wpdb;
        $wpdb->replace( $wpdb->prefix . 'dgptm_forum_subscriptions', [
            'user_id' => absint($user_id), 'scope' => sanitize_text_field($scope),
            'scope_id' => absint($scope_id), 'is_active' => 1, 'created_at' => current_time('mysql'),
        ] );
    }

    public static function unsubscribe( $user_id, $scope, $scope_id = 0 ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'dgptm_forum_subscriptions', [
            'user_id' => absint($user_id), 'scope' => $scope, 'scope_id' => absint($scope_id),
        ] );
    }

    public static function is_subscribed( $user_id, $scope, $scope_id = 0 ) {
        global $wpdb;
        $t = $wpdb->prefix . 'dgptm_forum_subscriptions';
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$t} WHERE user_id=%d AND scope=%s AND scope_id=%d AND is_active=1",
            $user_id, $scope, $scope_id
        ) );
    }

    public static function auto_subscribe_moderator( $ag_id, $moderator_id ) {
        if ( $moderator_id ) self::subscribe( $moderator_id, 'ag', $ag_id );
    }

    /* ============================================================
     *  E-Mail: Neuer Thread
     * ============================================================ */

    public static function notify_new_thread( $thread_id, $ag_id ) {
        global $wpdb;
        $prefix = $wpdb->prefix . 'dgptm_forum_';

        $thread = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prefix}threads WHERE id=%d", $thread_id ) );
        $ag     = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prefix}ags WHERE id=%d", $ag_id ) );
        if ( ! $thread || ! $ag ) return;

        $author = get_userdata( $thread->author_id );
        $author_name = function_exists('dgptm_forum_fullname') ? dgptm_forum_fullname($author) : ($author ? $author->display_name : 'Unbekannt');
        $tpl = self::get_templates();
        $link = self::get_forum_url();

        $vars = [
            'gruppe' => $ag->name, 'autor' => $author_name,
            'titel' => $thread->title,
            'auszug' => wp_trim_words( wp_strip_all_tags( $thread->content ), 50 ),
            'link' => $link,
        ];

        $subscribers = self::get_subscribers_for_ag( $ag_id );
        $subscribers = array_diff( $subscribers, [ (int) $thread->author_id ] );
        if ( empty( $subscribers ) ) return;

        $subject = self::replace_placeholders( $tpl['new_thread_subject'], $vars );

        foreach ( $subscribers as $uid ) {
            if ( self::is_blacklisted( $uid ) ) continue;
            $user = get_userdata( $uid );
            if ( ! $user || ! $user->user_email ) continue;
            $vars['empfaenger']  = function_exists('dgptm_forum_fullname') ? dgptm_forum_fullname($user) : $user->display_name;
            $vars['unsubscribe'] = 'Benachrichtigungen deaktivieren: ' . self::get_unsubscribe_url( $uid );
            $body = self::replace_placeholders( $tpl['new_thread_body'], $vars );
            wp_mail( $user->user_email, $subject, $body, self::mail_headers() );
        }
    }

    /* ============================================================
     *  E-Mail: Neue Antwort
     * ============================================================ */

    public static function notify_new_reply( $reply_id, $thread_id ) {
        global $wpdb;
        $prefix = $wpdb->prefix . 'dgptm_forum_';

        $reply  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prefix}replies WHERE id=%d", $reply_id ) );
        $thread = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prefix}threads WHERE id=%d", $thread_id ) );
        if ( ! $reply || ! $thread ) return;

        $ag_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT t.ag_id FROM {$prefix}topics t JOIN {$prefix}threads th ON th.topic_id=t.id WHERE th.id=%d", $thread_id
        ) );

        $author = get_userdata( $reply->author_id );
        $author_name = function_exists('dgptm_forum_fullname') ? dgptm_forum_fullname($author) : ($author ? $author->display_name : 'Unbekannt');
        $tpl = self::get_templates();
        $link = self::get_forum_url();

        $ag = $ag_id ? $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prefix}ags WHERE id=%d", $ag_id ) ) : null;

        $vars = [
            'gruppe' => $ag ? $ag->name : '', 'autor' => $author_name,
            'titel' => $thread->title,
            'auszug' => wp_trim_words( wp_strip_all_tags( $reply->content ), 50 ),
            'link' => $link,
        ];

        $subscribers = self::get_subscribers_for_thread( $thread_id, $ag_id );
        $subscribers = array_diff( $subscribers, [ (int) $reply->author_id ] );
        if ( empty( $subscribers ) ) return;

        $subject = self::replace_placeholders( $tpl['new_reply_subject'], $vars );

        foreach ( $subscribers as $uid ) {
            if ( self::is_blacklisted( $uid ) ) continue;
            $user = get_userdata( $uid );
            if ( ! $user || ! $user->user_email ) continue;
            $vars['empfaenger']  = function_exists('dgptm_forum_fullname') ? dgptm_forum_fullname($user) : $user->display_name;
            $vars['unsubscribe'] = 'Benachrichtigungen deaktivieren: ' . self::get_unsubscribe_url( $uid );
            $body = self::replace_placeholders( $tpl['new_reply_body'], $vars );
            wp_mail( $user->user_email, $subject, $body, self::mail_headers() );
        }
    }

    /* ============================================================
     *  E-Mail: Aufnahmeantrag
     * ============================================================ */

    public static function notify_membership_request( $ag_id, $requesting_user_id ) {
        global $wpdb;
        $prefix = $wpdb->prefix . 'dgptm_forum_';

        $ag = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$prefix}ags WHERE id=%d", $ag_id ) );
        if ( ! $ag || empty( $ag->moderator_id ) ) return;

        if ( self::is_blacklisted( $ag->moderator_id ) ) return;

        $moderator = get_userdata( $ag->moderator_id );
        if ( ! $moderator || ! $moderator->user_email ) return;

        $requester = get_userdata( $requesting_user_id );
        $tpl = self::get_templates();
        $link = self::get_forum_url();

        $vars = [
            'empfaenger'  => function_exists('dgptm_forum_fullname') ? dgptm_forum_fullname($moderator) : $moderator->display_name,
            'autor'       => function_exists('dgptm_forum_fullname') ? dgptm_forum_fullname($requester) : ($requester ? $requester->display_name : 'Unbekannt'),
            'email'       => $requester ? $requester->user_email : '',
            'gruppe'      => $ag->name,
            'link'        => $link,
            'unsubscribe' => 'Benachrichtigungen deaktivieren: ' . self::get_unsubscribe_url( $ag->moderator_id ),
        ];

        $subject = self::replace_placeholders( $tpl['membership_subject'], $vars );
        $body    = self::replace_placeholders( $tpl['membership_body'], $vars );
        wp_mail( $moderator->user_email, $subject, $body, self::mail_headers() );
    }

    /* ============================================================
     *  Admin-Panel: Mail-Templates rendern
     * ============================================================ */

    public static function render_mail_settings() {
        $tpl = self::get_templates();
        $placeholders = '<div style="font-size:11px;color:#888;margin-bottom:12px">Platzhalter: <code>{empfaenger}</code> <code>{autor}</code> <code>{titel}</code> <code>{gruppe}</code> <code>{auszug}</code> <code>{link}</code> <code>{email}</code> <code>{unsubscribe}</code></div>';
        ob_start();
        ?>
        <div class="dgptm-forum-admin-section">
            <h3 style="margin-top:0">E-Mail-Vorlagen</h3>
            <?php echo $placeholders; ?>

            <form class="dgptm-forum-admin-mail-form dgptm-forum-admin-form">
                <fieldset style="border:1px solid #e4e8ec;border-radius:6px;padding:12px;margin-bottom:14px">
                    <legend style="font-size:13px;font-weight:600;padding:0 6px">Neuer Thread</legend>
                    <label style="font-size:12px">Betreff</label>
                    <input type="text" name="new_thread_subject" value="<?php echo esc_attr($tpl['new_thread_subject']); ?>" style="font-size:12px">
                    <label style="font-size:12px">Text</label>
                    <textarea name="new_thread_body" rows="6" style="font-size:12px"><?php echo esc_textarea($tpl['new_thread_body']); ?></textarea>
                </fieldset>

                <fieldset style="border:1px solid #e4e8ec;border-radius:6px;padding:12px;margin-bottom:14px">
                    <legend style="font-size:13px;font-weight:600;padding:0 6px">Neue Antwort</legend>
                    <label style="font-size:12px">Betreff</label>
                    <input type="text" name="new_reply_subject" value="<?php echo esc_attr($tpl['new_reply_subject']); ?>" style="font-size:12px">
                    <label style="font-size:12px">Text</label>
                    <textarea name="new_reply_body" rows="6" style="font-size:12px"><?php echo esc_textarea($tpl['new_reply_body']); ?></textarea>
                </fieldset>

                <fieldset style="border:1px solid #e4e8ec;border-radius:6px;padding:12px;margin-bottom:14px">
                    <legend style="font-size:13px;font-weight:600;padding:0 6px">Aufnahmeantrag</legend>
                    <label style="font-size:12px">Betreff</label>
                    <input type="text" name="membership_subject" value="<?php echo esc_attr($tpl['membership_subject']); ?>" style="font-size:12px">
                    <label style="font-size:12px">Text</label>
                    <textarea name="membership_body" rows="5" style="font-size:12px"><?php echo esc_textarea($tpl['membership_body']); ?></textarea>
                </fieldset>

                <fieldset style="border:1px solid #e4e8ec;border-radius:6px;padding:12px;margin-bottom:14px">
                    <legend style="font-size:13px;font-weight:600;padding:0 6px">@Erw&auml;hnung</legend>
                    <label style="font-size:12px">Betreff</label>
                    <input type="text" name="mention_subject" value="<?php echo esc_attr($tpl['mention_subject']); ?>" style="font-size:12px">
                    <label style="font-size:12px">Text</label>
                    <textarea name="mention_body" rows="6" style="font-size:12px"><?php echo esc_textarea($tpl['mention_body']); ?></textarea>
                </fieldset>

                <button type="submit" class="dgptm-forum-btn dgptm-forum-btn-sm">Vorlagen speichern</button>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /* ============================================================
     *  Blacklist (Feature 4)
     * ============================================================ */

    /**
     * Check if a user has blacklisted forum notifications.
     *
     * @param int $user_id
     * @return bool
     */
    public static function is_blacklisted( $user_id ) {
        return (string) get_user_meta( $user_id, 'dgptm_forum_blacklisted', true ) === '1';
    }

    /**
     * Set or remove the blacklist status for a user.
     *
     * @param int  $user_id
     * @param bool $blacklisted
     */
    public static function set_blacklisted( $user_id, $blacklisted ) {
        if ( $blacklisted ) {
            update_user_meta( $user_id, 'dgptm_forum_blacklisted', 1 );
        } else {
            delete_user_meta( $user_id, 'dgptm_forum_blacklisted' );
        }
    }

    /**
     * Get all users that have blacklisted forum notifications.
     *
     * @return WP_User[]
     */
    public static function get_blacklisted_users() {
        $query = new \WP_User_Query( [
            'meta_key'   => 'dgptm_forum_blacklisted',
            'meta_value' => '1',
        ] );
        return $query->get_results();
    }

    /**
     * Generate unsubscribe URL for a user.
     *
     * @param int $user_id
     * @return string
     */
    public static function get_unsubscribe_url( $user_id ) {
        $token = wp_hash( $user_id . 'forum_unsub' );
        return site_url( '?dgptm_forum_unsubscribe=1&user=' . $user_id . '&token=' . $token );
    }

    /* ============================================================
     *  @Mentions (Feature 3)
     * ============================================================ */

    /**
     * Process @mentions in content, send notification emails.
     *
     * @param string $content   The post content.
     * @param string $post_type 'thread' or 'reply'.
     * @param int    $post_id   The thread or reply ID.
     * @param int    $thread_id The thread ID (for link context).
     * @param int    $author_id The author who wrote the post.
     * @return array Array of blacklisted user names that were mentioned but not notified.
     */
    public static function process_mentions( $content, $post_type, $post_id, $thread_id, $author_id ) {
        $blacklisted_names = [];

        // Parse @Vorname Nachname patterns
        if ( ! preg_match_all( '/@([A-Za-zÄÖÜäöüß]+\s+[A-Za-zÄÖÜäöüß]+)/', $content, $matches ) ) {
            return $blacklisted_names;
        }

        global $wpdb;
        $prefix = $wpdb->prefix . 'dgptm_forum_';

        // Get thread title for the email
        $thread = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$prefix}threads WHERE id = %d", $thread_id
        ) );
        if ( ! $thread ) return $blacklisted_names;

        $author      = get_userdata( $author_id );
        $author_name = function_exists( 'dgptm_forum_fullname' ) ? dgptm_forum_fullname( $author ) : ( $author ? $author->display_name : 'Unbekannt' );
        $tpl         = self::get_templates();
        $link        = self::get_forum_url();
        $strip       = wp_strip_all_tags( $content );
        $excerpt     = wp_trim_words( $strip, 50 );

        $already_notified = [];

        foreach ( $matches[1] as $full_name ) {
            $full_name = trim( $full_name );
            $parts     = explode( ' ', $full_name, 2 );
            if ( count( $parts ) < 2 ) continue;

            $first = $parts[0];
            $last  = $parts[1];

            // Search wp_users by first_name + last_name
            $user_query = new \WP_User_Query( [
                'meta_query' => [
                    'relation' => 'AND',
                    [
                        'key'     => 'first_name',
                        'value'   => $first,
                        'compare' => '=',
                    ],
                    [
                        'key'     => 'last_name',
                        'value'   => $last,
                        'compare' => '=',
                    ],
                ],
                'number' => 1,
            ] );

            $users = $user_query->get_results();
            if ( empty( $users ) ) continue;

            $mentioned_user = $users[0];

            // Skip self-mentions and duplicates
            if ( (int) $mentioned_user->ID === (int) $author_id ) continue;
            if ( in_array( (int) $mentioned_user->ID, $already_notified, true ) ) continue;

            // Check blacklist
            if ( self::is_blacklisted( $mentioned_user->ID ) ) {
                $blacklisted_names[] = dgptm_forum_fullname( $mentioned_user );
                continue;
            }

            $already_notified[] = (int) $mentioned_user->ID;

            $vars = [
                'empfaenger'  => function_exists( 'dgptm_forum_fullname' ) ? dgptm_forum_fullname( $mentioned_user ) : $mentioned_user->display_name,
                'autor'       => $author_name,
                'titel'       => $thread->title,
                'auszug'      => $excerpt,
                'link'        => $link,
                'unsubscribe' => 'Benachrichtigungen deaktivieren: ' . self::get_unsubscribe_url( $mentioned_user->ID ),
            ];

            $subject = self::replace_placeholders( $tpl['mention_subject'], $vars );
            $body    = self::replace_placeholders( $tpl['mention_body'], $vars );
            wp_mail( $mentioned_user->user_email, $subject, $body, self::mail_headers() );
        }

        return $blacklisted_names;
    }

    /* ============================================================
     *  Helfer
     * ============================================================ */

    private static function get_subscribers_for_ag( $ag_id ) {
        global $wpdb;
        $t = $wpdb->prefix . 'dgptm_forum_subscriptions';
        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$t} WHERE is_active=1 AND ((scope='ag' AND scope_id=%d) OR scope='forum')", $ag_id
        ) ) );
    }

    private static function get_subscribers_for_thread( $thread_id, $ag_id ) {
        global $wpdb;
        $t = $wpdb->prefix . 'dgptm_forum_subscriptions';
        return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$t} WHERE is_active=1 AND ((scope='thread' AND scope_id=%d) OR (scope='ag' AND scope_id=%d) OR scope='forum')",
            $thread_id, $ag_id
        ) ) );
    }

    private static function mail_headers() {
        return [ 'Content-Type: text/plain; charset=UTF-8' ];
    }

    private static function get_forum_url() {
        $url = get_option( 'dgptm_forum_page_url', '' );
        if ( $url ) return $url;
        global $wpdb;
        $pid = $wpdb->get_var( "SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%dgptm_dashboard%' AND post_status='publish' LIMIT 1" );
        if ( $pid ) {
            $url = get_permalink( $pid ) . '?tab=forum';
            update_option( 'dgptm_forum_page_url', $url );
            return $url;
        }
        return home_url( '/mitgliedschaft/interner-bereich/?tab=forum' );
    }
}
