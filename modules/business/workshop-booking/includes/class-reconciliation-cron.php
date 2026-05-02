<?php
/**
 * Reconciliation_Cron — Drift-Erkennung Stripe + Books vs. CRM.
 *
 * Laeuft alle 15 Minuten (interval 'dgptm_wsb_15min'). Faengt verlorene
 * Webhooks ab und erkennt manuelle CRM-Aenderungen, die nicht zum
 * tatsaechlichen Zahlungs-Stand passen.
 *
 * Filtert grundsaetzlich auf Quelle = 'Modul' — Backstage-Records werden
 * konsequent uebersprungen.
 *
 * Spec Abschnitt 4a.4.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Reconciliation_Cron {

    const HOOK     = 'dgptm_wsb_reconcile';
    const INTERVAL = 'dgptm_wsb_15min';

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) self::$instance = new self();
        return self::$instance;
    }

    private function __construct() {
        add_filter('cron_schedules', [$this, 'add_schedule']);
        add_action(self::HOOK, [$this, 'run']);
        add_action('init', [$this, 'maybe_schedule']);
    }

    public function add_schedule($schedules) {
        if (!isset($schedules[self::INTERVAL])) {
            $schedules[self::INTERVAL] = [
                'interval' => 900,
                'display'  => 'Alle 15 Minuten (DGPTM Workshop)',
            ];
        }
        return $schedules;
    }

    public function maybe_schedule() {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(time() + 60, self::INTERVAL, self::HOOK);
        }
    }

    public function run() {
        $token = DGPTM_WSB_Veranstal_X_Contacts::get_token();
        if (!$token) return;

        // Nur Modul-Quelle, nur kuerzlich aktiv (7 Tage), nur relevante Status
        $coql = "select id, Anmelde_Status, Zahlungsstatus, Stripe_Session_ID, Stripe_Charge_ID, Quelle "
              . "from Veranstal_X_Contacts "
              . "where Quelle = 'Modul' "
              . "and Modified_Time >= '" . date('Y-m-d', strtotime('-7 days')) . "' "
              . "and Anmelde_Status in ('Zahlung ausstehend','Angemeldet','Storniert') "
              . "limit 200";

        $resp = wp_remote_post('https://www.zohoapis.eu/crm/v3/coql', [
            'timeout' => 25,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['select_query' => $coql]),
        ]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return;

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        $rows = isset($body['data']) && is_array($body['data']) ? $body['data'] : [];

        foreach ($rows as $row) {
            $this->check_row($row);
        }
    }

    private function check_row(array $row) {
        $contact_id     = $row['id'];
        $crm_blueprint  = isset($row['Anmelde_Status'])    ? $row['Anmelde_Status']    : null;
        $crm_payment    = isset($row['Zahlungsstatus'])    ? $row['Zahlungsstatus']    : null;
        $session_id     = isset($row['Stripe_Session_ID']) ? $row['Stripe_Session_ID'] : null;
        $charge_id      = isset($row['Stripe_Charge_ID'])  ? $row['Stripe_Charge_ID']  : null;

        $stripe_status = $this->fetch_stripe_status($session_id, $charge_id);
        if (!$stripe_status) return;

        // Auto-Korrektur: Stripe paid, CRM ausstehend → Angemeldet/Bezahlt
        if (!empty($stripe_status['paid']) && $crm_blueprint === DGPTM_WSB_State_Machine::S_ZAHLUNG_AUSSTEHEND) {
            $intent = new DGPTM_WSB_Sync_Intent(
                $contact_id,
                DGPTM_WSB_State_Machine::S_ANGEMELDET,
                'Bezahlt',
                DGPTM_WSB_Sync_Intent::SOURCE_RECONCILIATION,
                ['stripe_status' => $stripe_status],
                'reconciliation: stripe_paid_crm_pending'
            );
            DGPTM_WSB_Sync_Coordinator::apply_intent($intent);
            return;
        }

        // Auto-Korrektur: Stripe refunded, CRM Angemeldet → Storniert
        if (!empty($stripe_status['refunded']) && $crm_blueprint === DGPTM_WSB_State_Machine::S_ANGEMELDET) {
            $intent = new DGPTM_WSB_Sync_Intent(
                $contact_id,
                DGPTM_WSB_State_Machine::S_STORNIERT,
                'Erstattet',
                DGPTM_WSB_Sync_Intent::SOURCE_RECONCILIATION,
                ['stripe_status' => $stripe_status],
                'reconciliation: stripe_refunded_crm_active'
            );
            DGPTM_WSB_Sync_Coordinator::apply_intent($intent);
            return;
        }

        // Alert: CRM manuell auf Storniert, aber Stripe nicht erstattet
        if ($crm_blueprint === DGPTM_WSB_State_Machine::S_STORNIERT
            && !empty($stripe_status['paid'])
            && empty($stripe_status['refunded'])) {

            DGPTM_WSB_Drift_Alert_Store::open_alert(
                $contact_id,
                'manual_storno_without_refund',
                DGPTM_WSB_Drift_Alert_Store::SEVERITY_WARNING,
                ['blueprint' => $crm_blueprint, 'payment' => $crm_payment],
                $stripe_status,
                'Pruefen: Storno im CRM gesetzt, aber Stripe-Charge nicht erstattet. Bar-Erstattung oder fehlender Refund?'
            );
        }
    }

    /**
     * Liest Zahlungs-Stand aus Stripe (PaymentIntent oder CheckoutSession).
     */
    private function fetch_stripe_status($session_id, $charge_id) {
        $key = DGPTM_WSB_Stripe_Checkout::get_secret_key();
        if (!$key) return null;

        $headers = ['Authorization' => 'Bearer ' . $key];
        $account = DGPTM_WSB_Stripe_Checkout::get_account_id();
        if ($account) $headers['Stripe-Account'] = $account;

        // PaymentIntent ist verlaesslicher (gibt Refund-Status preis)
        if (!empty($charge_id)) {
            $url  = DGPTM_WSB_Stripe_Checkout::API_BASE . '/payment_intents/' . rawurlencode($charge_id);
            $resp = wp_remote_get($url, ['timeout' => 10, 'headers' => $headers]);
            if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return null;

            $pi = json_decode(wp_remote_retrieve_body($resp), true);
            $paid     = isset($pi['status']) && $pi['status'] === 'succeeded';
            $refunded = !empty($pi['charges']['data'][0]['refunded']);
            return [
                'paid'       => $paid,
                'refunded'   => $refunded,
                'raw_status' => isset($pi['status']) ? $pi['status'] : null,
            ];
        }

        if (!empty($session_id)) {
            $url  = DGPTM_WSB_Stripe_Checkout::API_BASE . '/checkout/sessions/' . rawurlencode($session_id);
            $resp = wp_remote_get($url, ['timeout' => 10, 'headers' => $headers]);
            if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return null;

            $sess = json_decode(wp_remote_retrieve_body($resp), true);
            $paid = isset($sess['payment_status']) && $sess['payment_status'] === 'paid';
            return [
                'paid'       => $paid,
                'refunded'   => false,
                'raw_status' => isset($sess['payment_status']) ? $sess['payment_status'] : null,
            ];
        }

        return null;
    }
}
