<?php
/**
 * Forum Notifications - Subscription management + email dispatch.
 * Phase 5 implementation.
 */
if ( ! defined( 'ABSPATH' ) ) exit;

class DGPTM_Forum_Notifications {

    /**
     * Subscribe a user to a scope.
     */
    public static function subscribe( $user_id, $scope, $scope_id = 0 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_forum_subscriptions';
        $wpdb->replace( $table, [
            'user_id'    => $user_id,
            'scope'      => $scope,
            'scope_id'   => $scope_id,
            'is_active'  => 1,
            'created_at' => current_time( 'mysql' ),
        ] );
    }

    /**
     * Unsubscribe a user from a scope.
     */
    public static function unsubscribe( $user_id, $scope, $scope_id = 0 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_forum_subscriptions';
        $wpdb->delete( $table, [
            'user_id'  => $user_id,
            'scope'    => $scope,
            'scope_id' => $scope_id,
        ] );
    }

    /**
     * Check if user is subscribed.
     */
    public static function is_subscribed( $user_id, $scope, $scope_id = 0 ) {
        global $wpdb;
        $table = $wpdb->prefix . 'dgptm_forum_subscriptions';
        return (bool) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE user_id = %d AND scope = %s AND scope_id = %d AND is_active = 1",
            $user_id, $scope, $scope_id
        ) );
    }

    /**
     * Queue and send notifications for a new post.
     * Called after thread/reply creation.
     */
    public static function notify_new_post( $post_type, $post_id, $thread_id, $topic_id ) {
        // Phase 5: E-Mail-Versand implementieren
    }

    /**
     * Cron handler: Process notification queue.
     */
    public static function process_queue() {
        // Phase 5: Batch-Versand implementieren
    }
}
