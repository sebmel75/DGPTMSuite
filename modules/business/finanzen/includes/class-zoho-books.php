<?php
/**
 * Konsolidierter Zoho Books Client fuer das Finanzen-Modul.
 *
 * Vereint Rechnungs-/Zahlungsmethoden (ex mitgliedsbeitrag) und
 * Finanzbericht-Methoden (ex finanzbericht) in einem Client.
 *
 * @package DGPTM_Finanzen
 */

if (!defined('ABSPATH')) exit;

class DGPTM_FIN_Zoho_Books {

    private DGPTM_FIN_Config $config;
    private ?string $access_token = null;
    private string $org_id;

    const API_BASE       = 'https://www.zohoapis.eu/books/v3';
    const TRANSIENT_KEY  = 'dgptm_fin_books_token';
    const LOG_PREFIX     = '[DGPTM Finanzen Books]';

    public function __construct(DGPTM_FIN_Config $config) {
        $this->config = $config;
        $this->org_id = $config->zoho_org_id();
    }

    /* ================================================================ */
    /* OAuth Token                                                       */
    /* ================================================================ */

    private function get_token(): string {
        if ($this->access_token) {
            return $this->access_token;
        }

        $cached = get_transient(self::TRANSIENT_KEY);
        if ($cached) {
            $this->access_token = $cached;
            return $cached;
        }

        // Versuch 1: Token vom zentralen CRM-Modul uebernehmen
        if (class_exists('DGPTM_Zoho_Plugin')) {
            try {
                $crm = DGPTM_Zoho_Plugin::get_instance();
                $token = $crm->get_oauth_token();
                if ($token) {
                    $this->access_token = $token;
                    set_transient(self::TRANSIENT_KEY, $token, 55 * MINUTE_IN_SECONDS);
                    return $token;
                }
            } catch (\Throwable $e) {
                // Fallthrough zum eigenen Refresh
            }
        }

        // Versuch 2: Eigener OAuth Refresh
        $response = wp_remote_post($this->config->zoho_accounts_domain() . '/oauth/v2/token', [
            'timeout' => 15,
            'body'    => [
                'grant_type'    => 'refresh_token',
                'client_id'     => $this->config->zoho_client_id(),
                'client_secret' => $this->config->zoho_client_secret(),
                'refresh_token' => $this->config->zoho_refresh_token(),
            ],
        ]);

        if (is_wp_error($response)) {
            error_log(self::LOG_PREFIX . ' OAuth WP_Error: ' . $response->get_error_message());
            throw new \RuntimeException('OAuth-Fehler: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) {
            $err = $body['error'] ?? wp_remote_retrieve_body($response);
            error_log(self::LOG_PREFIX . ' OAuth fehlgeschlagen: ' . substr(print_r($err, true), 0, 300));
            throw new \RuntimeException('Kein Access-Token erhalten');
        }

        $this->access_token = $body['access_token'];
        set_transient(self::TRANSIENT_KEY, $this->access_token, 55 * MINUTE_IN_SECONDS);

        return $this->access_token;
    }

    /* ================================================================ */
    /* HTTP Client                                                       */
    /* ================================================================ */

    private function api_request(string $endpoint, string $method = 'GET', ?array $body = null, array $query = []): ?array {
        $token = $this->get_token();

        $url = self::API_BASE . '/' . ltrim($endpoint, '/');
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

        if ($body && in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            error_log(self::LOG_PREFIX . ' WP_Error: ' . $response->get_error_message() . ' | URL: ' . $url);
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw  = wp_remote_retrieve_body($response);

        if ($code >= 400) {
            $parsed    = json_decode($raw, true);
            $error_msg = $parsed['message'] ?? substr($raw, 0, 300);

            error_log(sprintf(
                '%s HTTP %d | %s %s | Error: %s',
                self::LOG_PREFIX, $code, $method, $endpoint, $error_msg
            ));

            if (class_exists('DGPTM_Logger')) {
                \DGPTM_Logger::error(
                    sprintf('Zoho Books API HTTP %d: %s (%s %s)', $code, $error_msg, $method, $endpoint),
                    'finanzen'
                );
            }

            // 401 = Token abgelaufen -> einmal refreshen und retry
            if ($code === 401) {
                delete_transient(self::TRANSIENT_KEY);
                $this->access_token = null;
                return $this->api_request($endpoint, $method, $body, $query);
            }

            return $parsed ?: null;
        }

        return json_decode($raw, true) ?: [];
    }

    /**
     * Paginierter GET: Alle Seiten laden (200 pro Seite).
     */
    private function api_get_all(string $endpoint, string $list_key, array $params = []): array {
        $all  = [];
        $page = 1;
        do {
            $params['page']     = $page;
            $params['per_page'] = 200;
            $data     = $this->api_request($endpoint, 'GET', null, $params);
            $items    = $data[$list_key] ?? [];
            $all      = array_merge($all, $items);
            $has_more = $data['page_context']['has_more_page'] ?? false;
            $page++;
        } while ($has_more);

        return $all;
    }

    /* ================================================================ */
    /* Kontakte                                                          */
    /* ================================================================ */

    public function get_contact_by_crm_id(string $crm_id): ?array {
        $result   = $this->api_request('contacts', 'GET', null, [
            'zcrm_contact_id' => $crm_id,
        ]);
        $contacts = $result['contacts'] ?? [];
        return !empty($contacts) ? $contacts[0] : null;
    }

    /**
     * Kontakt als aktiv markieren (z.B. nach Reaktivierung).
     */
    public function mark_contact_active(string $contact_id): bool {
        $result = $this->api_request('contacts/' . $contact_id . '/active', 'POST');
        return !empty($result) && ($result['code'] ?? 1) === 0;
    }

    public function get_customer_credits(string $customer_id): float {
        $result  = $this->api_request('contacts/' . $customer_id);
        $contact = $result['contact'] ?? [];
        return (float) ($contact['unused_credits_receivable_amount']
            ?? $contact['unused_credits']
            ?? 0);
    }

    /* ================================================================ */
    /* Rechnungen                                                        */
    /* ================================================================ */

    public function create_invoice(array $data, bool $ignore_auto_number = true): ?array {
        $query  = $ignore_auto_number ? ['ignore_auto_number_generation' => 'true'] : [];
        $result = $this->api_request('invoices', 'POST', $data, $query);

        if (!$result) return null;

        // Code 1001 = Rechnungsnummer existiert bereits
        if (isset($result['code']) && (int) $result['code'] === 1001) {
            $inv_number = $data['invoice_number'] ?? '';
            if ($inv_number) {
                return $this->get_invoice_by_number($inv_number);
            }
        }

        return $result['invoice'] ?? $result;
    }

    public function update_invoice(string $invoice_id, array $data, string $reason = ''): ?array {
        $query = [];
        if ($reason !== '') {
            $query['reason'] = $reason;
        }
        $result = $this->api_request('invoices/' . $invoice_id, 'PUT', $data, $query);
        return $result['invoice'] ?? $result;
    }

    public function get_invoice(string $invoice_id): ?array {
        $result = $this->api_request('invoices/' . $invoice_id);
        return $result['invoice'] ?? null;
    }

    public function get_invoice_by_number(string $number): ?array {
        $result   = $this->api_request('invoices', 'GET', null, [
            'invoice_number' => $number,
        ]);
        $invoices = $result['invoices'] ?? [];
        return !empty($invoices) ? $invoices[0] : null;
    }

    /**
     * Offene Rechnungen (paginiert).
     *
     * @param string|null $cf_filter  Optionaler Custom-Field-Filter (cf_beitragsrechnung).
     */
    public function get_unpaid_invoices(?string $cf_filter = null): array {
        $params = ['status' => 'unpaid'];
        if ($cf_filter) {
            $params['cf_beitragsrechnung'] = $cf_filter;
        }
        return $this->api_get_all('invoices', 'invoices', $params);
    }

    public function send_invoice(string $invoice_id, array $to_emails, string $template_id = ''): bool {
        $data = [
            'to_mail_ids'            => $to_emails,
            'send_from_org_email_id' => '',
        ];
        if ($template_id) {
            $data['template_id'] = $template_id;
        }
        $result = $this->api_request('invoices/' . $invoice_id . '/email', 'POST', $data);
        return !empty($result) && ($result['code'] ?? 1) === 0;
    }

    /**
     * Email-Inhalt fuer eine Rechnung abrufen (Vorschau / Template-Daten).
     */
    public function get_invoice_email_content(string $invoice_id, ?string $template_id = null): ?array {
        $query = [];
        if ($template_id) {
            $query['email_template_id'] = $template_id;
        }
        $result = $this->api_request('invoices/' . $invoice_id . '/email', 'GET', null, $query);
        return $result['data'] ?? $result;
    }

    /* ================================================================ */
    /* Guthaben / Credits verrechnen                                     */
    /* ================================================================ */

    /**
     * Guthaben auf Rechnung anwenden.
     *
     * Sucht Gutschriften (Credit Notes) und ueberzahlte Zahlungen
     * (excess payments) des Kunden und wendet sie auf die Rechnung an.
     */
    public function apply_credits_to_invoice(string $invoice_id, float $amount): bool {
        // Rechnung laden, um customer_id zu ermitteln
        $invoice = $this->get_invoice($invoice_id);
        if (!$invoice) return false;

        $customer_id    = $invoice['customer_id'] ?? '';
        $remaining      = $amount;
        $apply_credits  = [];

        // 1. Credit Notes mit offenen Betraegen suchen
        if ($customer_id && $remaining > 0) {
            $creditnotes = $this->api_get_all('creditnotes', 'creditnotes', [
                'customer_id' => $customer_id,
                'status'      => 'open',
            ]);
            foreach ($creditnotes as $cn) {
                if ($remaining <= 0) break;
                $available = (float) ($cn['balance'] ?? 0);
                if ($available <= 0) continue;
                $apply = min($available, $remaining);
                $apply_credits[] = [
                    'creditnote_id' => $cn['creditnote_id'],
                    'amount_applied' => $apply,
                ];
                $remaining -= $apply;
            }
        }

        // 2. Zahlungen mit unused_amount suchen
        $apply_payments = [];
        if ($customer_id && $remaining > 0) {
            $payments = $this->api_get_all('customerpayments', 'customerpayments', [
                'customer_id' => $customer_id,
            ]);
            foreach ($payments as $pmt) {
                if ($remaining <= 0) break;
                $unused = (float) ($pmt['unused_amount'] ?? 0);
                if ($unused <= 0) continue;
                $apply = min($unused, $remaining);
                $apply_payments[] = [
                    'payment_id'     => $pmt['payment_id'],
                    'amount_applied' => $apply,
                ];
                $remaining -= $apply;
            }
        }

        if (empty($apply_credits) && empty($apply_payments)) {
            return false;
        }

        $body = [];
        if (!empty($apply_credits)) {
            $body['apply_credits'] = $apply_credits;
        }
        if (!empty($apply_payments)) {
            $body['apply_payments'] = $apply_payments;
        }

        $result = $this->api_request('invoices/' . $invoice_id . '/credits', 'POST', $body);
        return !empty($result) && ($result['code'] ?? 1) === 0;
    }

    /* ================================================================ */
    /* Zahlungen                                                         */
    /* ================================================================ */

    public function record_payment(
        string $invoice_id,
        float  $amount,
        string $date,
        string $mode,
        string $reference,
        string $account_id,
        float  $fee_amount = 0,
        string $fee_account_id = ''
    ): ?array {
        $data = [
            'payment_mode'     => $mode,
            'amount'           => $amount,
            'date'             => $date,
            'reference_number' => $reference,
            'account_id'       => $account_id,
            'invoices'         => [
                ['invoice_id' => $invoice_id, 'amount_applied' => $amount],
            ],
        ];

        if ($fee_amount > 0 && $fee_account_id) {
            $data['bank_charges']            = $fee_amount;
            $data['bank_charges_account_id'] = $fee_account_id;
        }

        $result = $this->api_request('customerpayments', 'POST', $data);
        return $result['payment'] ?? $result;
    }

    /**
     * Zahlung loeschen.
     */
    public function delete_payment(string $payment_id): bool {
        $result = $this->api_request('customerpayments/' . $payment_id, 'DELETE');
        return !empty($result) && ($result['code'] ?? 1) === 0;
    }

    /* ================================================================ */
    /* Rechnungspositionen                                               */
    /* ================================================================ */

    /**
     * Position zu einer Draft-Rechnung hinzufuegen.
     */
    public function add_charge_to_invoice(string $invoice_id, float $amount, string $description, string $account_id): bool {
        $invoice = $this->get_invoice($invoice_id);
        if (!$invoice) return false;

        $line_items   = $invoice['line_items'] ?? [];
        $line_items[] = [
            'name'        => $description,
            'description' => $description,
            'rate'        => $amount,
            'quantity'    => 1,
            'account_id'  => $account_id,
        ];

        $result = $this->api_request('invoices/' . $invoice_id, 'PUT', [
            'line_items' => $line_items,
        ]);

        return !empty($result) && ($result['code'] ?? 1) === 0;
    }

    /* ================================================================ */
    /* Steuern                                                           */
    /* ================================================================ */

    /**
     * Alle konfigurierten Steuersaetze abrufen.
     */
    public function list_taxes(): array {
        $result = $this->api_request('settings/taxes');
        return $result['taxes'] ?? [];
    }

    /* ================================================================ */
    /* Finanzbericht: Jahrestagung                                       */
    /* ================================================================ */

    /**
     * JT-Einnahmen: Rechnungen mit Jahrestagung-Konten im Zeitraum.
     */
    public function get_jt_income(string $start, string $end): array {
        $invoices = $this->api_get_all('invoices', 'invoices', [
            'date_start' => $start,
            'date_end'   => $end,
        ]);

        $jt_items = [];
        $total    = 0.0;

        foreach ($invoices as $inv) {
            if (in_array($inv['status'], ['void', 'draft'])) continue;

            try {
                $detail     = $this->api_request("invoices/{$inv['invoice_id']}");
                $inv_detail = $detail['invoice'] ?? [];
            } catch (\Throwable $e) {
                continue;
            }

            $inv_is_jt = false;
            foreach ($inv_detail['line_items'] ?? [] as $li) {
                $acc_name = $li['account_name'] ?? '';
                if (stripos($acc_name, 'Jahrestagung') !== false ||
                    stripos($acc_name, 'Einnahmen JT') !== false) {
                    $inv_is_jt = true;
                    break;
                }
            }

            if (!$inv_is_jt) continue;

            $inv_total = (float) $inv['total'];
            $total    += $inv_total;
            $jt_items[] = [
                'number'   => $inv['invoice_number'],
                'date'     => $inv['date'],
                'customer' => $inv['customer_name'],
                'total'    => $inv_total,
                'status'   => $inv['status'],
            ];
        }

        return [
            'total'    => $total,
            'count'    => count($jt_items),
            'invoices' => $jt_items,
        ];
    }

    /**
     * JT-Ausgaben: Bills + Expenses auf Konto "Fremdleistungen Jahrestagung".
     */
    public function get_jt_expenses(string $start, string $end): array {
        $bills   = $this->api_get_all('bills', 'bills', [
            'date_start' => $start,
            'date_end'   => $end,
        ]);

        $results = [];
        $total   = 0.0;

        foreach ($bills as $bill) {
            try {
                $detail    = $this->api_request("bills/{$bill['bill_id']}");
                $bill_data = $detail['bill'] ?? [];
            } catch (\Throwable $e) {
                continue;
            }

            $jt_amount = 0.0;
            foreach ($bill_data['line_items'] ?? [] as $li) {
                $acc = $li['account_name'] ?? '';
                if (stripos($acc, 'Fremdleistungen Jahrestagung') !== false) {
                    $jt_amount += (float) ($li['item_total'] ?? 0);
                }
            }

            if ($jt_amount > 0) {
                $results[] = [
                    'date'   => $bill_data['date'] ?? '',
                    'vendor' => $bill_data['vendor_name'] ?? '',
                    'total'  => $jt_amount,
                ];
                $total += $jt_amount;
            }
        }

        // Auch Expenses (nicht nur Bills)
        $expenses = $this->api_get_all('expenses', 'expenses', [
            'date_start' => $start,
            'date_end'   => $end,
        ]);
        foreach ($expenses as $exp) {
            $acc = $exp['account_name'] ?? '';
            if (stripos($acc, 'Fremdleistungen Jahrestagung') !== false) {
                $t = (float) ($exp['total'] ?? 0);
                $results[] = [
                    'date'   => $exp['date'] ?? '',
                    'vendor' => $exp['vendor_name'] ?? $exp['description'] ?? '',
                    'total'  => $t,
                ];
                $total += $t;
            }
        }

        return ['total' => $total, 'count' => count($results), 'items' => $results];
    }

    /* ================================================================ */
    /* Finanzbericht: Sachkundekurs (SKK)                                */
    /* ================================================================ */

    public function get_skk_income(string $start, string $end): array {
        $invoices = $this->api_get_all('invoices', 'invoices', [
            'date_start' => $start,
            'date_end'   => $end,
        ]);

        $total        = 0.0;
        $items        = [];
        $skk_keywords = ['sachkunde', 'ecls', 'skk'];

        foreach ($invoices as $inv) {
            if (in_array($inv['status'], ['void', 'draft'])) continue;

            try {
                $detail     = $this->api_request("invoices/{$inv['invoice_id']}");
                $inv_detail = $detail['invoice'] ?? [];
            } catch (\Throwable $e) {
                continue;
            }

            $is_skk = false;
            foreach ($inv_detail['line_items'] ?? [] as $li) {
                $name = strtolower($li['name'] ?? '');
                $acc  = strtolower($li['account_name'] ?? '');
                if (stripos($acc, 'SKK') !== false || stripos($acc, 'Tickets SKK') !== false) {
                    foreach ($skk_keywords as $kw) {
                        if (strpos($name, $kw) !== false) {
                            $is_skk = true;
                            break 2;
                        }
                    }
                }
            }

            if ($is_skk) {
                $t      = (float) $inv['total'];
                $total += $t;
                $items[] = [
                    'number'   => $inv['invoice_number'],
                    'date'     => $inv['date'],
                    'customer' => $inv['customer_name'],
                    'total'    => $t,
                ];
            }
        }

        return ['total' => $total, 'count' => count($items), 'items' => $items];
    }

    public function get_skk_expenses(string $start, string $end): array {
        return $this->get_expenses_by_account($start, $end, 'Fremdleistungen SKK');
    }

    /* ================================================================ */
    /* Finanzbericht: Zeitschrift                                        */
    /* ================================================================ */

    public function get_zeitschrift_income(string $start, string $end): array {
        $invoices = $this->api_get_all('invoices', 'invoices', [
            'search_text' => 'Zeitschrift',
            'date_start'  => $start,
            'date_end'    => $end,
        ]);

        $total = 0.0;
        $items = [];
        foreach ($invoices as $inv) {
            if (in_array($inv['status'], ['void', 'draft'])) continue;
            $t      = (float) $inv['total'];
            $total += $t;
            $items[] = [
                'number'   => $inv['invoice_number'],
                'date'     => $inv['date'],
                'customer' => $inv['customer_name'],
                'total'    => $t,
            ];
        }

        return ['total' => $total, 'count' => count($items), 'items' => $items];
    }

    public function get_zeitschrift_expenses(string $start, string $end): array {
        return $this->get_expenses_by_account($start, $end, 'Fremdleistungen Zeitschrift');
    }

    /* ================================================================ */
    /* Finanzbericht: Offene Forderungen                                 */
    /* ================================================================ */

    public function get_open_invoices(): array {
        return $this->api_get_all('invoices', 'invoices', ['status' => 'unpaid']);
    }

    /* ================================================================ */
    /* Private Helpers                                                    */
    /* ================================================================ */

    /**
     * Ausgaben nach Kontoname: Bills (Detail-Line-Items) + Expenses.
     */
    private function get_expenses_by_account(string $start, string $end, string $account_needle): array {
        $bills   = $this->api_get_all('bills', 'bills', [
            'date_start' => $start,
            'date_end'   => $end,
        ]);

        $results = [];
        $total   = 0.0;

        foreach ($bills as $bill) {
            try {
                $detail = $this->api_request("bills/{$bill['bill_id']}");
                $bd     = $detail['bill'] ?? [];
            } catch (\Throwable $e) {
                continue;
            }
            $amount = 0.0;
            foreach ($bd['line_items'] ?? [] as $li) {
                if (stripos($li['account_name'] ?? '', $account_needle) !== false) {
                    $amount += (float) ($li['item_total'] ?? 0);
                }
            }
            if ($amount > 0) {
                $results[] = [
                    'date'   => $bd['date'] ?? '',
                    'vendor' => $bd['vendor_name'] ?? '',
                    'total'  => $amount,
                ];
                $total += $amount;
            }
        }

        $expenses = $this->api_get_all('expenses', 'expenses', [
            'date_start' => $start,
            'date_end'   => $end,
        ]);
        foreach ($expenses as $exp) {
            if (stripos($exp['account_name'] ?? '', $account_needle) !== false) {
                $t = (float) ($exp['total'] ?? 0);
                $results[] = [
                    'date'   => $exp['date'] ?? '',
                    'vendor' => $exp['vendor_name'] ?? '',
                    'total'  => $t,
                ];
                $total += $t;
            }
        }

        return ['total' => $total, 'count' => count($results), 'items' => $results];
    }
}
