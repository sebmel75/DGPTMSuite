<?php
/**
 * Pending_Cleanup_Cron — raeumt abgelaufene Stripe-Sessions auf.
 *
 * Wenn eine Stripe-Checkout-Session abgelaufen ist (expires_at < jetzt) und
 * der Webhook checkout.session.expired aus irgendeinem Grund nicht eingegangen
 * ist, faengt dieser Cron es ab: Sync_Intent target=Abgebrochen + pending-Eintrag
 * loeschen → Platz wieder frei.
 *
 * Lauf alle 15 Minuten (gleicher Interval wie Reconciliation).
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Pending_Cleanup_Cron {

    const HOOK = 'dgptm_wsb_pending_cleanup';

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action(self::HOOK, [$this, 'run']);
        add_action('init', [$this, 'maybe_schedule']);
    }

    public function maybe_schedule() {
        if (!wp_next_scheduled(self::HOOK)) {
            // 90 Sekunden offset gegenueber Reconciliation, damit beide Crons nicht zur selben Sekunde feuern
            wp_schedule_event(time() + 90, DGPTM_WSB_Reconciliation_Cron::INTERVAL, self::HOOK);
        }
    }

    public function run() {
        $expired = DGPTM_WSB_Pending_Bookings_Store::get_expired();
        foreach ($expired as $row) {
            $intent = new DGPTM_WSB_Sync_Intent(
                $row['veranstal_x_contact_id'],
                DGPTM_WSB_State_Machine::S_ABGEBROCHEN,
                null,
                DGPTM_WSB_Sync_Intent::SOURCE_RECONCILIATION,
                ['expired_session' => $row['stripe_session_id']],
                'pending_cleanup: stripe_session_expired'
            );
            DGPTM_WSB_Sync_Coordinator::apply_intent($intent);
            DGPTM_WSB_Pending_Bookings_Store::delete_by_contact($row['veranstal_x_contact_id']);
        }
    }
}
