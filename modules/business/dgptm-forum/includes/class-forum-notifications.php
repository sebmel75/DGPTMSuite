<?php
/**
 * Forum Notifications — Abo-Verwaltung + E-Mail-Versand.
 *
 * Scopes: forum (alles), ag (Hauptgruppe), thread (Thread)
 * Moderator wird automatisch für seine Hauptgruppen abonniert.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class DGPTM_Forum_Notifications {

    /* ============================================================
     *  Abo-Verwaltung
     * ============================================================ */

    public static function subscribe( $user_id, $scope, $scope_id = 0 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_forum_subscriptions';
        $wpdb->replace( $table, [
            'user_id'    => absint( $user_id ),
            'scope'      => sanitize_text_field( $scope ),
            'scope_id'   => absint( $scope_id ),
            'is_active'  => 1,
            'created_at' => current_time( 'mysql' ),
        ] );
    }

    public static function unsubscribe( $user_id, $scope, $scope_id = 0 ) {
        global $wpdb;
        $wpdb->delete( $wpdb->prefix . 'dgptm_forum_subscriptions', [
            'user_id'  => absint( $user_id ),
            'scope'    => $scope,
            'scope_id' => absint( $scope_id ),
        ] );
    }

    public static function is_subscribed( $user_id, $scope, $scope_id = 0 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_forum_subscriptions';
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND scope = %s AND scope_id = %d AND is_active = 1",
            $user_id, $scope, $scope_id
        ) );
    }

    /**
     * Moderator automatisch abonnieren (wird bei AG-Erstellung/-Update aufgerufen).
     */
    public static function auto_subscribe_moderator( $ag_id, $moderator_id ) {
        if ( ! $moderator_id ) return;
        self::subscribe( $moderator_id, 'ag', $ag_id );
    }

    /* ============================================================
     *  E-Mail-Versand: Neuer Thread
     * ============================================================ */

    /**
     * Benachrichtigung: Neuer Thread in einer Hauptgruppe.
     * Empfänger: Alle AG-Abonnenten + Forum-Abonnenten.
     */
    public static function notify_new_thread( $thread_id, $ag_id ) {
        global $wpdb;
        $prefix = $wpdb->prefix . 'dgptm_forum_';

        $thread = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$prefix}threads WHERE id = %d", $thread_id
        ) );
        if ( ! $thread ) return;

        $ag = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$prefix}ags WHERE id = %d", $ag_id
        ) );
        if ( ! $ag ) return;

        $author = get_userdata( $thread->author_id );
        $author_name = $author ? $author->display_name : 'Unbekannt';

        // Empfänger sammeln: AG-Abonnenten + Forum-Abonnenten
        $subscribers = self::get_subscribers_for_ag( $ag_id );

        // Autor entfernen (nicht sich selbst benachrichtigen)
        $subscribers = array_diff( $subscribers, [ (int) $thread->author_id ] );

        if ( empty( $subscribers ) ) return;

        $subject = '[DGPTM Forum] Neuer Thread in "' . $ag->name . '"';
        $forum_url = self::get_forum_url();

        foreach ( $subscribers as $uid ) {
            $user = get_userdata( $uid );
            if ( ! $user || ! $user->user_email ) continue;

            $body = self::render_email(
                $user->display_name ?: $user->user_login,
                $author_name,
                'hat einen neuen Thread erstellt',
                $thread->title,
                wp_trim_words( wp_strip_all_tags( $thread->content ), 50 ),
                $forum_url,
                'die Hauptgruppe "' . $ag->name . '"'
            );

            wp_mail( $user->user_email, $subject, $body, self::mail_headers() );
        }
    }

    /* ============================================================
     *  E-Mail-Versand: Neue Antwort
     * ============================================================ */

    /**
     * Benachrichtigung: Neue Antwort in einem Thread.
     * Empfänger: Thread-Abonnenten + AG-Abonnenten + Forum-Abonnenten.
     */
    public static function notify_new_reply( $reply_id, $thread_id ) {
        global $wpdb;
        $prefix = $wpdb->prefix . 'dgptm_forum_';

        $reply = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$prefix}replies WHERE id = %d", $reply_id
        ) );
        if ( ! $reply ) return;

        $thread = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$prefix}threads WHERE id = %d", $thread_id
        ) );
        if ( ! $thread ) return;

        // AG ermitteln
        $ag_id = (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT t.ag_id FROM {$prefix}topics t JOIN {$prefix}threads th ON th.topic_id = t.id WHERE th.id = %d",
            $thread_id
        ) );

        $ag = $ag_id ? $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$prefix}ags WHERE id = %d", $ag_id
        ) ) : null;

        $author = get_userdata( $reply->author_id );
        $author_name = $author ? $author->display_name : 'Unbekannt';

        // Empfänger: Thread-Abonnenten + AG-Abonnenten + Forum-Abonnenten
        $subscribers = self::get_subscribers_for_thread( $thread_id, $ag_id );

        // Autor entfernen
        $subscribers = array_diff( $subscribers, [ (int) $reply->author_id ] );

        if ( empty( $subscribers ) ) return;

        $subject = '[DGPTM Forum] Neue Antwort in "' . $thread->title . '"';
        $forum_url = self::get_forum_url();

        foreach ( $subscribers as $uid ) {
            $user = get_userdata( $uid );
            if ( ! $user || ! $user->user_email ) continue;

            $body = self::render_email(
                $user->display_name ?: $user->user_login,
                $author_name,
                'hat eine neue Antwort geschrieben',
                $thread->title,
                wp_trim_words( wp_strip_all_tags( $reply->content ), 50 ),
                $forum_url,
                'den Thread "' . $thread->title . '"'
            );

            wp_mail( $user->user_email, $subject, $body, self::mail_headers() );
        }
    }

    /* ============================================================
     *  E-Mail-Versand: Aufnahmeantrag
     * ============================================================ */

    /**
     * Benachrichtigung an Moderator: Aufnahmeantrag für Gruppe.
     */
    public static function notify_membership_request( $ag_id, $requesting_user_id ) {
        global $wpdb;
        $prefix = $wpdb->prefix . 'dgptm_forum_';

        $ag = $wpdb->get_row( $wpdb->prepare(
            "SELECT * FROM {$prefix}ags WHERE id = %d", $ag_id
        ) );
        if ( ! $ag || empty( $ag->moderator_id ) ) return;

        $moderator = get_userdata( $ag->moderator_id );
        if ( ! $moderator || ! $moderator->user_email ) return;

        $requester = get_userdata( $requesting_user_id );
        $requester_name = $requester ? $requester->display_name : 'Unbekannt';
        $requester_email = $requester ? $requester->user_email : '';

        $subject = '[DGPTM Forum] Aufnahmeantrag f' . "\xC3\xBC" . 'r "' . $ag->name . '"';

        $body  = "Hallo " . esc_html( $moderator->display_name ) . ",\n\n";
        $body .= esc_html( $requester_name );
        if ( $requester_email ) $body .= ' (' . $requester_email . ')';
        $body .= " m\xC3\xB6" . "chte der Hauptgruppe \"" . esc_html( $ag->name ) . "\" beitreten.\n\n";
        $body .= "Bitte pr\xC3\xBC" . "fen Sie den Antrag im Forum-Verwaltungsbereich.\n\n";
        $body .= "---\nDGPTM Forum\n";

        wp_mail( $moderator->user_email, $subject, $body, self::mail_headers() );
    }

    /* ============================================================
     *  Helfer
     * ============================================================ */

    /**
     * Sammelt alle Abonnenten für eine Hauptgruppe (AG + Forum-Scope).
     */
    private static function get_subscribers_for_ag( $ag_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_forum_subscriptions';

        $rows = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$table}
             WHERE is_active = 1 AND (
                 (scope = 'ag' AND scope_id = %d)
                 OR scope = 'forum'
             )",
            $ag_id
        ) );

        return array_map( 'intval', $rows );
    }

    /**
     * Sammelt alle Abonnenten für einen Thread (Thread + AG + Forum-Scope).
     */
    private static function get_subscribers_for_thread( $thread_id, $ag_id ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_forum_subscriptions';

        $rows = $wpdb->get_col( $wpdb->prepare(
            "SELECT DISTINCT user_id FROM {$table}
             WHERE is_active = 1 AND (
                 (scope = 'thread' AND scope_id = %d)
                 OR (scope = 'ag' AND scope_id = %d)
                 OR scope = 'forum'
             )",
            $thread_id, $ag_id
        ) );

        return array_map( 'intval', $rows );
    }

    /**
     * E-Mail-Template rendern.
     */
    private static function render_email( $recipient_name, $author_name, $action_text, $title, $excerpt, $forum_url, $subscription_reason ) {
        $body  = "Hallo {$recipient_name},\n\n";
        $body .= "{$author_name} {$action_text}:\n\n";
        $body .= "\xE2\x80\x9C{$title}\xE2\x80\x9D\n\n";
        if ( $excerpt ) {
            $body .= "---\n{$excerpt}\n---\n\n";
        }
        $body .= "Zum Forum: {$forum_url}\n\n";
        $body .= "Sie erhalten diese Benachrichtigung, weil Sie {$subscription_reason} abonniert haben.\n";
        $body .= "Abonnement verwalten: {$forum_url}\n\n";
        $body .= "---\nDGPTM Forum\n";

        return $body;
    }

    /**
     * Standard-Mail-Header.
     */
    private static function mail_headers() {
        return [ 'Content-Type: text/plain; charset=UTF-8' ];
    }

    /**
     * Forum-URL ermitteln.
     */
    private static function get_forum_url() {
        // Dashboard-Seite mit Tab-Parameter
        $page = get_option( 'dgptm_forum_page_url', '' );
        if ( $page ) return $page;

        // Fallback: Seite mit [dgptm_dashboard] suchen
        global $wpdb;
        $page_id = $wpdb->get_var(
            "SELECT ID FROM {$wpdb->posts} WHERE post_content LIKE '%dgptm_dashboard%' AND post_status = 'publish' LIMIT 1"
        );
        if ( $page_id ) {
            $url = get_permalink( $page_id ) . '?tab=forum';
            update_option( 'dgptm_forum_page_url', $url );
            return $url;
        }

        return home_url( '/mitgliedschaft/interner-bereich/?tab=forum' );
    }
}
