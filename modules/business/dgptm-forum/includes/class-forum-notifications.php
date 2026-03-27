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
            'new_thread_body'    => '<p>Hallo <strong>{empfaenger}</strong>,</p><p>{autor} hat einen neuen Thread erstellt:</p><p><strong>&bdquo;{titel}&ldquo;</strong></p><blockquote style="border-left:3px solid #0073aa;padding:8px 12px;margin:12px 0;color:#555">{auszug}</blockquote><p><a href="{link}">Zum Forum</a></p><p style="font-size:12px;color:#888">Sie erhalten diese Mail, weil Sie die Hauptgruppe &bdquo;{gruppe}&ldquo; abonniert haben.<br>{unsubscribe}</p>',
            'new_reply_subject'  => '[DGPTM Forum] Neue Antwort in "{titel}"',
            'new_reply_body'     => '<p>Hallo <strong>{empfaenger}</strong>,</p><p>{autor} hat eine neue Antwort geschrieben:</p><p>Thread: <strong>&bdquo;{titel}&ldquo;</strong></p><blockquote style="border-left:3px solid #0073aa;padding:8px 12px;margin:12px 0;color:#555">{auszug}</blockquote><p><a href="{link}">Zum Forum</a></p><p style="font-size:12px;color:#888">Sie erhalten diese Mail, weil Sie den Thread &bdquo;{titel}&ldquo; abonniert haben.<br>{unsubscribe}</p>',
            'membership_subject' => '[DGPTM Forum] Aufnahmeantrag für "{gruppe}"',
            'membership_body'    => '<p>Hallo <strong>{empfaenger}</strong>,</p><p>{autor} ({email}) m&ouml;chte der Hauptgruppe &bdquo;{gruppe}&ldquo; beitreten.</p><p>Bitte pr&uuml;fen Sie den Antrag im <a href="{link}">Forum-Verwaltungsbereich</a>.</p><p style="font-size:12px;color:#888">{unsubscribe}</p>',
            'mention_subject'    => '[DGPTM Forum] {autor} hat Sie erw&auml;hnt',
            'mention_body'       => '<p>Hallo <strong>{empfaenger}</strong>,</p><p>{autor} hat Sie in einem Beitrag erw&auml;hnt:</p><p><strong>&bdquo;{titel}&ldquo;</strong></p><blockquote style="border-left:3px solid #0073aa;padding:8px 12px;margin:12px 0;color:#555">{auszug}</blockquote><p><a href="{link}">Zum Forum</a></p><p style="font-size:12px;color:#888">{unsubscribe}</p>',
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
            wp_mail( $user->user_email, $subject, self::wrap_html( $body ), self::mail_headers() );
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
            wp_mail( $user->user_email, $subject, self::wrap_html( $body ), self::mail_headers() );
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
        wp_mail( $moderator->user_email, $subject, self::wrap_html( $body ), self::mail_headers() );
    }

    /* ============================================================
     *  Admin-Panel: Mail-Templates rendern
     * ============================================================ */

    public static function render_mail_settings() {
        $tpl = self::get_templates();
        $ph = '{empfaenger} {autor} {titel} {gruppe} {auszug} {link} {email} {unsubscribe}';
        $toolbar = '<div class="dgptm-forum-rte-toolbar" style="display:flex;gap:2px;margin-bottom:4px;padding:4px;background:#f0f0f0;border-radius:3px">'
            . '<button type="button" onclick="document.execCommand(\'bold\')" style="border:none;background:none;cursor:pointer;font-weight:bold;padding:2px 6px" title="Fett">B</button>'
            . '<button type="button" onclick="document.execCommand(\'italic\')" style="border:none;background:none;cursor:pointer;font-style:italic;padding:2px 6px" title="Kursiv">I</button>'
            . '<button type="button" onclick="document.execCommand(\'underline\')" style="border:none;background:none;cursor:pointer;text-decoration:underline;padding:2px 6px" title="Unterstrichen">U</button>'
            . '<button type="button" onclick="var u=prompt(\'URL:\');if(u)document.execCommand(\'createLink\',false,u)" style="border:none;background:none;cursor:pointer;padding:2px 6px;color:#0073aa" title="Link">&#128279;</button>'
            . '<button type="button" onclick="document.execCommand(\'insertUnorderedList\')" style="border:none;background:none;cursor:pointer;padding:2px 6px" title="Liste">&#8226;</button>'
            . '</div>';
        ob_start();
        ?>
        <div class="dgptm-forum-admin-section">
            <h3 style="margin-top:0">E-Mail-Vorlagen <span style="font-size:11px;font-weight:400;color:#888">(HTML)</span></h3>
            <div style="font-size:10px;color:#999;margin-bottom:10px">Platzhalter: <?php
                foreach (explode(' ', $ph) as $p) echo '<code style="background:#f0f0f0;padding:1px 4px;border-radius:2px;margin:0 2px">' . esc_html($p) . '</code>';
            ?></div>

            <form class="dgptm-forum-admin-mail-form dgptm-forum-admin-form">
                <?php
                $fields = [
                    ['key' => 'new_thread', 'label' => 'Neuer Thread'],
                    ['key' => 'new_reply', 'label' => 'Neue Antwort'],
                    ['key' => 'membership', 'label' => 'Aufnahmeantrag'],
                    ['key' => 'mention', 'label' => '@Erw&auml;hnung'],
                ];
                foreach ($fields as $f) :
                    $subj_key = $f['key'] . '_subject';
                    $body_key = $f['key'] . '_body';
                ?>
                <fieldset style="border:1px solid #e4e8ec;border-radius:6px;padding:10px 12px;margin-bottom:10px">
                    <legend style="font-size:12px;font-weight:600;padding:0 4px"><?php echo $f['label']; ?></legend>
                    <label style="font-size:11px;color:#666">Betreff</label>
                    <input type="text" name="<?php echo $subj_key; ?>" value="<?php echo esc_attr($tpl[$subj_key]); ?>" style="font-size:12px;margin-bottom:6px">
                    <label style="font-size:11px;color:#666">Inhalt</label>
                    <?php echo $toolbar; ?>
                    <div class="dgptm-forum-rte" contenteditable="true" data-field="<?php echo $body_key; ?>"
                         style="border:1px solid #ddd;border-radius:3px;padding:8px;min-height:80px;font-size:12px;line-height:1.5;background:#fff"><?php echo wp_kses_post($tpl[$body_key]); ?></div>
                    <input type="hidden" name="<?php echo $body_key; ?>" value="">
                </fieldset>
                <?php endforeach; ?>

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

        // Parse @Vorname Nachname patterns (inkl. Bindestrich, Umlaute, diakritische Zeichen)
        if ( ! preg_match_all( '/@([\p{L}\-]+\s+[\p{L}\-]+)/u', $content, $matches ) ) {
            return $blacklisted_names;
        }

        global $wpdb;
        $prefix = $wpdb->prefix . 'dgptm_forum_';

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

            $first = trim( $parts[0] );
            $last  = trim( $parts[1] );

            // Suche per SQL LIKE (robuster als exakter Match)
            $mentioned_user = $wpdb->get_row( $wpdb->prepare(
                "SELECT u.ID FROM {$wpdb->users} u
                 JOIN {$wpdb->usermeta} um1 ON um1.user_id = u.ID AND um1.meta_key = 'first_name'
                 JOIN {$wpdb->usermeta} um2 ON um2.user_id = u.ID AND um2.meta_key = 'last_name'
                 WHERE um1.meta_value LIKE %s AND um2.meta_value LIKE %s
                 LIMIT 1",
                $wpdb->esc_like( $first ),
                $wpdb->esc_like( $last )
            ) );

            if ( ! $mentioned_user ) continue;
            $mentioned_user = get_userdata( $mentioned_user->ID );
            if ( ! $mentioned_user ) continue;

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
            wp_mail( $mentioned_user->user_email, $subject, self::wrap_html( $body ), self::mail_headers() );
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
        return [ 'Content-Type: text/html; charset=UTF-8' ];
    }

    /**
     * Wraps mail body in a minimal HTML template.
     */
    private static function wrap_html( $body ) {
        // Konvertiere Zeilenumbrüche in <br> falls kein HTML-Tag vorhanden
        if ( strip_tags( $body ) === $body ) {
            $body = nl2br( esc_html( $body ) );
        }
        return '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body style="font-family:Arial,sans-serif;font-size:14px;color:#333;line-height:1.6;max-width:600px;margin:0 auto;padding:20px">'
            . $body
            . '</body></html>';
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
