<?php
/**
 * Booking_Service — Orchestrator fuer Buchungen.
 *
 * Entry-Point fuer Frontend (AJAX in Shortcode-Klasse).
 * Workflow:
 *   1. Event laden + Capacity-Check
 *   2. Pro Teilnehmer:in: Contact-Lookup oder Neuanlage
 *   3. Veranstal_X_Contacts-Eintrag via Sync_Coordinator (Status 'Zahlung ausstehend')
 *   4. pending_bookings-Eintrag
 *   5a. Kostenlos? → direkt Status 'Angemeldet' + Bestaetigungsmail
 *   5b. Kostenpflichtig? → Stripe-Checkout-Session + Redirect-URL
 *
 * Spec Abschnitt 5.1.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Booking_Service {

    const RESULT_CHECKOUT = 'checkout_required';
    const RESULT_FREE     = 'free_confirmed';
    const RESULT_WAITLIST = 'waitlist';
    const RESULT_FULL     = 'full_no_waitlist';
    const RESULT_ERROR    = 'error';

    /**
     * @param string $event_id  Zoho DGfK_Events-ID
     * @param array  $attendees Liste mit
     *                          ['first_name','last_name','email','ticket_type','price_eur']
     * @return array ['result' => ..., 'checkout_url' => ?, 'contact_ids' => [], 'error' => ?]
     */
    public static function book($event_id, array $attendees) {
        $event = DGPTM_WSB_Event_Source::fetch_one($event_id);
        if (!$event) {
            return ['result' => self::RESULT_ERROR, 'error' => 'event_not_found'];
        }

        // Capacity-Check (Phase 1: einfach; Warteliste-Logik in Phase 6)
        $max    = isset($event['Maximum_Attendees']) ? (int) $event['Maximum_Attendees'] : 0;
        $taken  = self::count_active_bookings($event_id);
        $needed = count($attendees);
        if ($max > 0 && ($taken + $needed) > $max) {
            return ['result' => self::RESULT_FULL, 'error' => 'capacity_exceeded'];
        }

        $contact_ids = [];
        $total_price = 0.0;

        foreach ($attendees as $attendee) {
            // Contact-Lookup oder Neuanlage in Zoho
            $zoho_contact_id = DGPTM_WSB_Contact_Lookup::find_by_email($attendee['email']);
            if (!$zoho_contact_id) {
                $zoho_contact_id = DGPTM_WSB_Contact_Lookup::create(
                    $attendee['first_name'],
                    $attendee['last_name'],
                    $attendee['email']
                );
            }
            if (!$zoho_contact_id) {
                return ['result' => self::RESULT_ERROR, 'error' => 'contact_creation_failed'];
            }

            // Veranstal_X_Contacts-Eintrag anlegen via Sync_Coordinator
            $intent = new DGPTM_WSB_Sync_Intent(
                '', // leer = neu
                DGPTM_WSB_State_Machine::S_ZAHLUNG_AUSSTEHEND,
                'Ausstehend',
                DGPTM_WSB_Sync_Intent::SOURCE_BOOKING_INIT,
                [
                    'initial_fields' => [
                        'Contact_Name' => ['id' => $zoho_contact_id],
                        'Event_Name'   => ['id' => $event_id],
                        'Ticket_Type'  => isset($attendee['ticket_type']) ? $attendee['ticket_type'] : '',
                        'Price_EUR'    => isset($attendee['price_eur']) ? (float) $attendee['price_eur'] : 0,
                    ],
                ],
                'Booking initialisiert'
            );
            $sync = DGPTM_WSB_Sync_Coordinator::apply_intent($intent);
            if (!$sync->success) {
                return ['result' => self::RESULT_ERROR, 'error' => $sync->error_code];
            }

            $contact_ids[] = $intent->veranstal_x_contact_id;
            $total_price  += isset($attendee['price_eur']) ? (float) $attendee['price_eur'] : 0;

            DGPTM_WSB_Pending_Bookings_Store::insert(
                $intent->veranstal_x_contact_id,
                $event_id,
                [$attendee]
            );
        }

        // Kostenlos? Direkt auf Angemeldet ohne Stripe.
        if ($total_price <= 0) {
            foreach ($contact_ids as $cid) {
                $intent = new DGPTM_WSB_Sync_Intent(
                    $cid,
                    DGPTM_WSB_State_Machine::S_ANGEMELDET,
                    'Bezahlt',
                    DGPTM_WSB_Sync_Intent::SOURCE_BOOKING_INIT,
                    [],
                    'Freiticket: keine Stripe-Session noetig'
                );
                DGPTM_WSB_Sync_Coordinator::apply_intent($intent);
                DGPTM_WSB_Pending_Bookings_Store::delete_by_contact($cid);
                DGPTM_WSB_Mail_Sender::send_confirmation($cid, $event);
            }
            return ['result' => self::RESULT_FREE, 'contact_ids' => $contact_ids];
        }

        // Stripe-Checkout-Session
        $checkout = DGPTM_WSB_Stripe_Checkout::create_session($event, $attendees, $contact_ids);
        if (!$checkout['success']) {
            return ['result' => self::RESULT_ERROR, 'error' => $checkout['error']];
        }
        foreach ($contact_ids as $cid) {
            DGPTM_WSB_Pending_Bookings_Store::attach_session(
                $cid,
                $checkout['session_id'],
                $checkout['expires_at']
            );
        }
        return [
            'result'       => self::RESULT_CHECKOUT,
            'checkout_url' => $checkout['url'],
            'contact_ids'  => $contact_ids,
        ];
    }

    /**
     * Zaehlt aktive (nicht-stornierte, nicht-abgebrochene) Buchungen
     * fuer ein Event via COQL.
     */
    private static function count_active_bookings($event_id) {
        $token = DGPTM_WSB_Veranstal_X_Contacts::get_token();
        if (!$token) return 0;

        $event_id_q = esc_sql($event_id);
        $coql = "select count(id) from Veranstal_X_Contacts "
              . "where Event_Name = '$event_id_q' "
              . "and Anmelde_Status not in ('Storniert','Abgebrochen','Nicht teilgenommen')";

        $resp = wp_remote_post('https://www.zohoapis.eu/crm/v3/coql', [
            'timeout' => 12,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['select_query' => $coql]),
        ]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return 0;

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return isset($body['data'][0]['count(id)']) ? (int) $body['data'][0]['count(id)'] : 0;
    }
}
