<?php
/**
 * Pending_Bookings_Store — Uebergangstabelle zwischen book() und Stripe-Webhook.
 *
 * Sobald die Stripe-Session offen ist, kann das Modul deterministisch
 * abgelaufene Sessions per Cron aufraeumen.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Pending_Bookings_Store {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dgptm_workshop_pending_bookings';
    }

    public static function insert($contact_id, $event_id, array $attendees) {
        global $wpdb;
        $wpdb->insert(self::table(), [
            'veranstal_x_contact_id' => $contact_id,
            'event_id'               => $event_id,
            'attendees_json'         => wp_json_encode($attendees),
            'created_at'             => current_time('mysql'),
        ]);
        return (int) $wpdb->insert_id;
    }

    public static function attach_session($contact_id, $session_id, $expires_at) {
        global $wpdb;
        $wpdb->update(
            self::table(),
            [
                'stripe_session_id'         => $session_id,
                'stripe_session_expires_at' => $expires_at,
            ],
            ['veranstal_x_contact_id' => $contact_id]
        );
    }

    public static function get_by_contact($contact_id) {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE veranstal_x_contact_id = %s",
            $contact_id
        ), ARRAY_A);
    }

    public static function delete_by_contact($contact_id) {
        global $wpdb;
        $wpdb->delete(self::table(), ['veranstal_x_contact_id' => $contact_id]);
    }

    public static function get_expired() {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::table() . "
             WHERE stripe_session_expires_at IS NOT NULL
             AND stripe_session_expires_at < %s",
            current_time('mysql')
        ), ARRAY_A);
    }
}
