<?php
/**
 * GoCardless API Client
 *
 * SEPA-Lastschrift: Mandate pruefen, Zahlungen erstellen, Gebuehren berechnen.
 */

if (!defined('ABSPATH')) exit;

class DGPTM_FIN_GoCardless {

    private string $api_url;
    private string $access_token;

    public function __construct(DGPTM_FIN_Config $config) {
        $this->api_url = rtrim($config->gc_api_url(), '/');
        $this->access_token = $config->gc_token();
    }

    private function request(string $endpoint, string $method = 'GET', ?array $body = null): ?array {
        $args = [
            'method'  => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization'      => 'Bearer ' . $this->access_token,
                'Content-Type'       => 'application/json',
                'GoCardless-Version' => '2015-07-06',
            ],
        ];

        if ($body && in_array($method, ['POST', 'PUT'])) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($this->api_url . '/' . ltrim($endpoint, '/'), $args);
        if (is_wp_error($response)) return null;

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /* ============================================================ */
    /* Mandate                                                       */
    /* ============================================================ */

    public function get_mandate(string $mandate_id): ?array {
        $result = $this->request('mandates/' . $mandate_id);
        return $result['mandates'] ?? null;
    }

    public function is_mandate_active(string $mandate_id): bool {
        $mandate = $this->get_mandate($mandate_id);
        if (!$mandate) return false;
        return in_array($mandate['status'] ?? '', ['active', 'pending_submission'], true);
    }

    public function get_usable_mandate_for_customer(string $customer_id): ?array {
        $result = $this->request('mandates', 'GET');
        $mandates = $result['mandates'] ?? [];
        foreach ($mandates as $m) {
            if (($m['links']['customer'] ?? '') === $customer_id
                && in_array($m['status'] ?? '', ['active', 'pending_submission'], true)) {
                return $m;
            }
        }
        return null;
    }

    public function get_all_active_mandates(): array {
        $all = [];

        $cursor = null;
        for ($i = 0; $i < 20; $i++) {
            $url = 'mandates?status=active&limit=500';
            if ($cursor) $url .= '&after=' . $cursor;

            $result = $this->request($url);
            $all = array_merge($all, $result['mandates'] ?? []);

            $cursor = $result['meta']['cursors']['after'] ?? null;
            if (!$cursor) break;
        }

        $cursor = null;
        for ($i = 0; $i < 20; $i++) {
            $url = 'mandates?status=pending_submission&limit=500';
            if ($cursor) $url .= '&after=' . $cursor;

            $result = $this->request($url);
            $all = array_merge($all, $result['mandates'] ?? []);

            $cursor = $result['meta']['cursors']['after'] ?? null;
            if (!$cursor) break;
        }

        return $all;
    }

    /* ============================================================ */
    /* Zahlungen                                                     */
    /* ============================================================ */

    public function create_payment(int $amount_cents, string $mandate_id, string $description, array $metadata = []): ?array {
        $data = [
            'payments' => [
                'amount'      => $amount_cents,
                'currency'    => 'EUR',
                'description' => $description,
                'links'       => ['mandate' => $mandate_id],
            ],
        ];

        if (!empty($metadata)) {
            // GoCardless erlaubt max 3 Metadata-Keys
            $data['payments']['metadata'] = array_slice($metadata, 0, 3);
        }

        $result = $this->request('payments', 'POST', $data);
        return $result['payments'] ?? null;
    }

    public function get_payment(string $payment_id): ?array {
        $result = $this->request('payments/' . $payment_id);
        return $result['payments'] ?? null;
    }

    /**
     * Zahlung mit berechneter GoCardless-Gebuehr.
     *
     * Gebuehr: 0.20 EUR + 1% vom Betrag.
     */
    public function get_payment_with_fees(string $payment_id): ?array {
        $payment = $this->get_payment($payment_id);
        if (!$payment) return null;

        $fee = 0.20 + ($payment['amount'] / 100) * 0.01;
        $payment['fee_amount'] = round($fee, 2);

        return $payment;
    }

    /* ============================================================ */
    /* Auszahlungen                                                  */
    /* ============================================================ */

    public function get_payout(string $payout_id): ?array {
        $result = $this->request('payouts/' . $payout_id);
        return $result['payouts'] ?? null;
    }

    /* ============================================================ */
    /* Kunden                                                        */
    /* ============================================================ */

    public function get_all_customers(): array {
        $all = [];
        $after = null;

        for ($i = 0; $i < 20; $i++) {
            $url = 'customers?limit=500';
            if ($after) $url .= '&after=' . $after;

            $result = $this->request($url);
            $customers = $result['customers'] ?? [];
            if (empty($customers)) break;

            $all = array_merge($all, $customers);

            $cursors = $result['meta']['cursors'] ?? [];
            $after = $cursors['after'] ?? null;
            if (!$after) break;
        }

        return $all;
    }
}
