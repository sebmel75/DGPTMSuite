<?php
/**
 * REST-Endpoint /wp-json/dgptm-workshop/v1/stripe-webhook.
 *
 * Empfaengt Stripe-Events, prueft Signatur (HMAC SHA256 mit Webhook-Secret)
 * und uebersetzt zu Sync_Intents (siehe Spec 4a.2 + 4a.4):
 *   - checkout.session.completed → Angemeldet + Bezahlt
 *   - checkout.session.expired   → Abgebrochen
 *   - charge.refunded            → Storniert + Erstattet
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Stripe_Webhook {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_action('rest_api_init', [$this, 'register_route']);
    }

    public function register_route() {
        register_rest_route('dgptm-workshop/v1', '/stripe-webhook', [
            'methods'             => 'POST',
            'callback'            => [$this, 'handle'],
            'permission_callback' => '__return_true',
        ]);
    }

    public function handle(WP_REST_Request $request) {
        $payload   = $request->get_body();
        $signature = $request->get_header('stripe_signature'); // WP normalisiert Header zu lowercase + underscore
        $secret    = DGPTM_WSB_Stripe_Checkout::get_webhook_secret();

        if (!$this->verify_signature($payload, $signature, $secret)) {
            return new WP_REST_Response(['error' => 'invalid_signature'], 401);
        }

        $event = json_decode($payload, true);
        if (!is_array($event) || empty($event['type'])) {
            return new WP_REST_Response(['error' => 'invalid_payload'], 400);
        }

        switch ($event['type']) {
            case 'checkout.session.completed':
                $this->handle_completed($event['data']['object']);
                break;
            case 'checkout.session.expired':
                $this->handle_expired($event['data']['object']);
                break;
            case 'charge.refunded':
                $this->handle_refunded($event['data']['object']);
                break;
            default:
                // Andere Events bewusst ignorieren
                break;
        }

        return new WP_REST_Response(['ok' => true], 200);
    }

    /**
     * Prueft Stripe-Signatur:
     *   t=<timestamp>,v1=<sha256(timestamp.payload, secret)>
     */
    private function verify_signature($payload, $sig_header, $secret) {
        if (empty($secret) || empty($sig_header)) return false;

        $parts = [];
        foreach (explode(',', $sig_header) as $kv) {
            $kv = explode('=', $kv, 2);
            if (count($kv) === 2) $parts[$kv[0]] = $kv[1];
        }
        if (empty($parts['t']) || empty($parts['v1'])) return false;

        $signed_payload = $parts['t'] . '.' . $payload;
        $expected = hash_hmac('sha256', $signed_payload, $secret);
        return hash_equals($expected, $parts['v1']);
    }

    private function handle_completed(array $session) {
        $contact_ids = isset($session['metadata']['contact_ids'])
                     ? explode(',', $session['metadata']['contact_ids']) : [];

        foreach ($contact_ids as $cid) {
            $cid = trim($cid);
            if (!$cid) continue;

            $intent = new DGPTM_WSB_Sync_Intent(
                $cid,
                DGPTM_WSB_State_Machine::S_ANGEMELDET,
                'Bezahlt',
                DGPTM_WSB_Sync_Intent::SOURCE_STRIPE_WEBHOOK,
                [
                    'stripe_event_id' => isset($session['id']) ? $session['id'] : '',
                    'extra_fields'    => [
                        'Stripe_Session_ID' => isset($session['id']) ? $session['id'] : '',
                        'Stripe_Charge_ID'  => isset($session['payment_intent']) ? $session['payment_intent'] : '',
                    ],
                ],
                'checkout.session.completed'
            );
            $result = DGPTM_WSB_Sync_Coordinator::apply_intent($intent);

            if ($result->success && $result->error_code !== DGPTM_WSB_Sync_Result::ERR_SOURCE_SKIPPED) {
                $event_id = isset($session['metadata']['event_id']) ? $session['metadata']['event_id'] : '';
                $event    = $event_id ? DGPTM_WSB_Event_Source::fetch_one($event_id) : null;

                // Phase 2: Ticketnummer generieren + im CRM speichern (zweiter Sync_Intent,
                // ohne Blueprint-Aenderung — nur extra_fields-Update).
                $ticket_number = DGPTM_WSB_Ticket_Number::generate_next();
                if ($ticket_number) {
                    $tn_intent = new DGPTM_WSB_Sync_Intent(
                        $cid,
                        null, // Blueprint nicht aendern
                        null, // Zahlungsstatus nicht aendern
                        DGPTM_WSB_Sync_Intent::SOURCE_STRIPE_WEBHOOK,
                        [
                            'extra_fields' => [
                                DGPTM_WSB_Ticket_Number::FIELD_NAME => $ticket_number,
                            ],
                        ],
                        'ticket_number_assigned: ' . $ticket_number
                    );
                    DGPTM_WSB_Sync_Coordinator::apply_intent($tn_intent);
                }

                DGPTM_WSB_Mail_Sender::send_confirmation($cid, $event);
                DGPTM_WSB_Pending_Bookings_Store::delete_by_contact($cid);
            }
        }
    }

    private function handle_expired(array $session) {
        $contact_ids = isset($session['metadata']['contact_ids'])
                     ? explode(',', $session['metadata']['contact_ids']) : [];

        foreach ($contact_ids as $cid) {
            $cid = trim($cid);
            if (!$cid) continue;

            $intent = new DGPTM_WSB_Sync_Intent(
                $cid,
                DGPTM_WSB_State_Machine::S_ABGEBROCHEN,
                null,
                DGPTM_WSB_Sync_Intent::SOURCE_STRIPE_WEBHOOK,
                ['stripe_event_id' => isset($session['id']) ? $session['id'] : ''],
                'checkout.session.expired'
            );
            DGPTM_WSB_Sync_Coordinator::apply_intent($intent);
            DGPTM_WSB_Pending_Bookings_Store::delete_by_contact($cid);
        }
    }

    private function handle_refunded(array $charge) {
        // Stripe charge.refunded: charge.payment_intent ist die Brueckenreferenz auf Stripe_Charge_ID
        $payment_intent_id = isset($charge['payment_intent']) ? $charge['payment_intent'] : '';
        $contact_id = $this->find_contact_by_charge($payment_intent_id);
        if (!$contact_id) return;

        $intent = new DGPTM_WSB_Sync_Intent(
            $contact_id,
            DGPTM_WSB_State_Machine::S_STORNIERT,
            'Erstattet',
            DGPTM_WSB_Sync_Intent::SOURCE_STRIPE_WEBHOOK,
            [
                'stripe_charge_id' => isset($charge['id']) ? $charge['id'] : '',
                'amount_refunded'  => isset($charge['amount_refunded']) ? $charge['amount_refunded'] : 0,
            ],
            'charge.refunded'
        );
        DGPTM_WSB_Sync_Coordinator::apply_intent($intent);
    }

    /**
     * Sucht den Veranstal_X_Contacts-Eintrag mit der gegebenen Stripe_Charge_ID
     * (= PaymentIntent-ID) via COQL.
     */
    private function find_contact_by_charge($payment_intent_id) {
        if (!$payment_intent_id) return null;

        $token = DGPTM_WSB_Veranstal_X_Contacts::get_token();
        if (!$token) return null;

        $pi   = esc_sql($payment_intent_id);
        $coql = "select id from Veranstal_X_Contacts where Stripe_Charge_ID = '$pi' limit 1";

        $resp = wp_remote_post('https://www.zohoapis.eu/crm/v3/coql', [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['select_query' => $coql]),
        ]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return null;

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return isset($body['data'][0]['id']) ? $body['data'][0]['id'] : null;
    }
}
