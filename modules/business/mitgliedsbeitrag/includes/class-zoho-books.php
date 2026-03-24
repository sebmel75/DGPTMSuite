<?php
/**
 * Zoho Books Client fuer Mitgliedsbeitrag
 *
 * Rechnungen erstellen, Guthaben verrechnen, Zahlungen buchen.
 */

if (!defined('ABSPATH')) exit;

class DGPTM_MB_Zoho_Books {

    private DGPTM_MB_Config $config;
    private ?string $access_token = null;
    private string $base_url;
    private string $org_id;

    public function __construct(DGPTM_MB_Config $config) {
        $this->config = $config;
        $this->base_url = $config->zoho_api_domain() . '/books/' . $config->zoho_books_version();
        $this->org_id = $config->zoho_org_id();
    }

    private function get_token(): ?string {
        if ($this->access_token) return $this->access_token;

        $cached = get_transient('dgptm_mb_books_token');
        if ($cached) {
            $this->access_token = $cached;
            return $cached;
        }

        $response = wp_remote_post($this->config->zoho_accounts_domain() . '/oauth/v2/token', [
            'timeout' => 15,
            'body' => [
                'grant_type'    => 'refresh_token',
                'client_id'     => $this->config->zoho_client_id(),
                'client_secret' => $this->config->zoho_client_secret(),
                'refresh_token' => $this->config->zoho_refresh_token(),
            ],
        ]);

        if (is_wp_error($response)) return null;
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) return null;

        $this->access_token = $body['access_token'];
        set_transient('dgptm_mb_books_token', $this->access_token, 55 * MINUTE_IN_SECONDS);
        return $this->access_token;
    }

    private function api_request(string $endpoint, string $method = 'GET', ?array $body = null, array $query = []): ?array {
        $token = $this->get_token();
        if (!$token) return null;

        $url = $this->base_url . '/' . ltrim($endpoint, '/');
        $query['organization_id'] = $this->org_id;
        $url = add_query_arg($query, $url);

        $args = [
            'method'  => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json;charset=UTF-8',
            ],
        ];

        if ($body && in_array($method, ['POST', 'PUT'])) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) return null;

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /* ============================================================ */
    /* Kontakte                                                      */
    /* ============================================================ */

    public function get_contact_by_crm_id(string $crm_id): ?array {
        $result = $this->api_request('contacts', 'GET', null, [
            'zcrm_contact_id' => $crm_id,
        ]);
        $contacts = $result['contacts'] ?? [];
        return !empty($contacts) ? $contacts[0] : null;
    }

    public function get_customer_credits(string $customer_id): float {
        $result = $this->api_request('contacts/' . $customer_id);
        $contact = $result['contact'] ?? [];
        return (float) ($contact['unused_credits_receivable_amount']
            ?? $contact['unused_credits']
            ?? 0);
    }

    /* ============================================================ */
    /* Rechnungen                                                    */
    /* ============================================================ */

    public function create_invoice(array $invoice_data, bool $ignore_auto_number = true): ?array {
        $query = $ignore_auto_number ? ['ignore_auto_number_generation' => 'true'] : [];
        $result = $this->api_request('invoices', 'POST', $invoice_data, $query);

        if (!$result) return null;

        // Code 1001 = Rechnung existiert bereits
        if (isset($result['code']) && (int) $result['code'] === 1001) {
            $inv_number = $invoice_data['invoice_number'] ?? '';
            if ($inv_number) {
                return $this->get_invoice_by_number($inv_number);
            }
        }

        return $result['invoice'] ?? $result;
    }

    public function update_invoice(string $invoice_id, array $data): ?array {
        $result = $this->api_request('invoices/' . $invoice_id, 'PUT', $data);
        return $result['invoice'] ?? $result;
    }

    public function get_invoice_by_number(string $number): ?array {
        $result = $this->api_request('invoices', 'GET', null, [
            'invoice_number' => $number,
        ]);
        $invoices = $result['invoices'] ?? [];
        return !empty($invoices) ? $invoices[0] : null;
    }

    public function get_unpaid_invoices(?string $cf_filter = null): array {
        $query = ['status' => 'unpaid'];
        if ($cf_filter) {
            $query['cf_beitragsrechnung'] = $cf_filter;
        }
        $result = $this->api_request('invoices', 'GET', null, $query);
        return $result['invoices'] ?? [];
    }

    public function send_invoice(string $invoice_id, array $to_emails, string $template_id = ''): bool {
        $data = [
            'to_mail_ids' => $to_emails,
            'send_from_org_email_id' => '',
        ];
        if ($template_id) {
            $data['template_id'] = $template_id;
        }
        $result = $this->api_request('invoices/' . $invoice_id . '/email', 'POST', $data);
        return !empty($result) && ($result['code'] ?? 1) === 0;
    }

    /* ============================================================ */
    /* Guthaben verrechnen                                           */
    /* ============================================================ */

    public function apply_credits_to_invoice(string $invoice_id, float $amount): bool {
        $result = $this->api_request('invoices/' . $invoice_id . '/credits', 'POST', [
            'apply_credits' => [
                ['credit_note_id' => '', 'amount_applied' => $amount],
            ],
        ]);
        return !empty($result) && ($result['code'] ?? 1) === 0;
    }

    /* ============================================================ */
    /* Zahlungen                                                     */
    /* ============================================================ */

    public function record_payment(string $invoice_id, float $amount, string $date, string $mode, string $reference, string $account_id, float $fee_amount = 0, string $fee_account_id = ''): ?array {
        $data = [
            'customer_id' => '', // wird aus Invoice geholt
            'payment_mode' => $mode,
            'amount' => $amount,
            'date' => $date,
            'reference_number' => $reference,
            'account_id' => $account_id,
            'invoices' => [
                ['invoice_id' => $invoice_id, 'amount_applied' => $amount],
            ],
        ];

        if ($fee_amount > 0 && $fee_account_id) {
            $data['bank_charges'] = $fee_amount;
            $data['bank_charges_account_id'] = $fee_account_id;
        }

        $result = $this->api_request('customerpayments', 'POST', $data);
        return $result['payment'] ?? $result;
    }
}
