<?php
/**
 * Zoho Books API Client fuer dynamische Finanzdaten (ab 2025).
 * Credentials kommen aus wp_options (via Transfer-JSON Upload).
 */
if (!defined('ABSPATH')) exit;

class DGPTM_FB_Zoho_Books_Client {

    private string $accounts_domain;
    private string $client_id;
    private string $client_secret;
    private string $refresh_token;
    private string $organization_id;
    private ?string $access_token = null;

    const TRANSIENT_KEY = 'dgptm_fb_zoho_token';
    const API_BASE = 'https://www.zohoapis.eu/books/v3';

    public function __construct() {
        $creds = get_option(DGPTM_Finanzbericht::OPTION_CREDENTIALS);
        if (empty($creds['zoho'])) {
            throw new \RuntimeException('Zoho Books Credentials nicht konfiguriert');
        }
        $z = $creds['zoho'];
        $this->accounts_domain = $z['accounts_domain'];
        $this->client_id       = $z['client_id'];
        $this->client_secret   = $z['client_secret'];
        $this->refresh_token   = $z['refresh_token'];
        $this->organization_id = $z['organization_id'];
    }

    /* ------------------------------------------------------------ */
    /* OAuth Token                                                    */
    /* ------------------------------------------------------------ */

    private function get_token(): string {
        if ($this->access_token) {
            return $this->access_token;
        }

        $cached = get_transient(self::TRANSIENT_KEY);
        if ($cached) {
            $this->access_token = $cached;
            return $cached;
        }

        $response = wp_remote_post("{$this->accounts_domain}/oauth/v2/token", [
            'body' => [
                'refresh_token' => $this->refresh_token,
                'client_id'     => $this->client_id,
                'client_secret' => $this->client_secret,
                'grant_type'    => 'refresh_token',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            error_log('[DGPTM Finanzbericht] OAuth WP_Error: ' . $response->get_error_message());
            throw new \RuntimeException('OAuth-Fehler: ' . $response->get_error_message());
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) {
            $err = $body['error'] ?? wp_remote_retrieve_body($response);
            error_log('[DGPTM Finanzbericht] OAuth fehlgeschlagen: ' . substr(print_r($err, true), 0, 300));
            throw new \RuntimeException('Kein Access-Token erhalten: ' . (is_string($err) ? $err : 'unbekannt'));
        }

        $this->access_token = $body['access_token'];
        $ttl = ($body['expires_in'] ?? 3600) - 300;
        set_transient(self::TRANSIENT_KEY, $this->access_token, max($ttl, 60));

        return $this->access_token;
    }

    /* ------------------------------------------------------------ */
    /* API-Aufrufe                                                    */
    /* ------------------------------------------------------------ */

    private function api_get(string $endpoint, array $params = []): array {
        $params['organization_id'] = $this->organization_id;

        $url = self::API_BASE . '/' . ltrim($endpoint, '/');
        $url = add_query_arg($params, $url);

        $response = wp_remote_get($url, [
            'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $this->get_token()],
            'timeout' => 30,
        ]);

        if (is_wp_error($response)) {
            throw new \RuntimeException('API-Fehler: ' . $response->get_error_message());
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw_body = wp_remote_retrieve_body($response);

        if ($code >= 400) {
            // Detail-Logging fuer Debugging
            $parsed = json_decode($raw_body, true);
            $error_msg = $parsed['message'] ?? $raw_body;
            $error_code = $parsed['code'] ?? '';

            error_log(sprintf(
                '[DGPTM Finanzbericht] API HTTP %d | URL: %s | Error: %s | Code: %s',
                $code, $url, substr($error_msg, 0, 300), $error_code
            ));

            if (class_exists('DGPTM_Logger')) {
                \DGPTM_Logger::error(sprintf('Zoho Books API HTTP %d: %s (URL: %s)', $code, $error_msg, $endpoint), 'finanzbericht');
            }

            // Token abgelaufen? Einmal neu versuchen
            if ($code === 401) {
                delete_transient(self::TRANSIENT_KEY);
                $this->access_token = null;
                return $this->api_get($endpoint, $params);
            }
            throw new \RuntimeException("API HTTP $code: $error_msg");
        }

        return json_decode($raw_body, true) ?: [];
    }

    private function api_get_all(string $endpoint, string $list_key, array $params = []): array {
        $all = [];
        $page = 1;
        do {
            $params['page'] = $page;
            $params['per_page'] = 200;
            $data = $this->api_get($endpoint, $params);
            $items = $data[$list_key] ?? [];
            $all = array_merge($all, $items);
            $has_more = $data['page_context']['has_more_page'] ?? false;
            $page++;
        } while ($has_more);
        return $all;
    }

    /* ------------------------------------------------------------ */
    /* Jahrestagung: Einnahmen (Rechnungen)                          */
    /* ------------------------------------------------------------ */

    public function get_jt_income(string $date_start, string $date_end): array {
        // Alle Rechnungen im Zeitraum, dann nach JT-Konten filtern
        $invoices = $this->api_get_all('invoices', 'invoices', [
            'date_start' => $date_start,
            'date_end'   => $date_end,
        ]);

        $jt_accounts = ['42020', '42030', '42050', '41060'];
        $jt_items = [];
        $by_category = [];
        $total = 0.0;

        foreach ($invoices as $inv) {
            if (in_array($inv['status'], ['void', 'draft'])) continue;

            // Detail laden fuer Line-Items und Account-Code
            try {
                $detail = $this->api_get("invoices/{$inv['invoice_id']}");
                $inv_detail = $detail['invoice'] ?? [];
            } catch (\Throwable $e) {
                continue;
            }

            $inv_is_jt = false;
            foreach ($inv_detail['line_items'] ?? [] as $li) {
                $acc_name = $li['account_name'] ?? '';
                // Pruefen ob JT-Konto
                foreach ($jt_accounts as $code) {
                    if (stripos($acc_name, 'Jahrestagung') !== false ||
                        stripos($acc_name, 'Einnahmen JT') !== false) {
                        $inv_is_jt = true;
                        break;
                    }
                }
            }

            if (!$inv_is_jt) continue;

            $inv_total = floatval($inv['total']);
            $total += $inv_total;
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

    /* ------------------------------------------------------------ */
    /* Jahrestagung: Ausgaben (Bills auf Konto 59020)                */
    /* ------------------------------------------------------------ */

    public function get_jt_expenses(string $date_start, string $date_end): array {
        $bills = $this->api_get_all('bills', 'bills', [
            'date_start' => $date_start,
            'date_end'   => $date_end,
        ]);

        $results = [];
        $total = 0.0;

        foreach ($bills as $bill) {
            try {
                $detail = $this->api_get("bills/{$bill['bill_id']}");
                $bill_data = $detail['bill'] ?? [];
            } catch (\Throwable $e) {
                continue;
            }

            $jt_amount = 0.0;
            foreach ($bill_data['line_items'] ?? [] as $li) {
                $acc = $li['account_name'] ?? '';
                if (stripos($acc, 'Fremdleistungen Jahrestagung') !== false) {
                    $jt_amount += floatval($li['item_total'] ?? 0);
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
            'date_start' => $date_start,
            'date_end'   => $date_end,
        ]);
        foreach ($expenses as $exp) {
            $acc = $exp['account_name'] ?? '';
            if (stripos($acc, 'Fremdleistungen Jahrestagung') !== false) {
                $t = floatval($exp['total'] ?? 0);
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

    /* ------------------------------------------------------------ */
    /* SKK: Einnahmen (Konto 41070 / SKK-Items)                      */
    /* ------------------------------------------------------------ */

    public function get_skk_income(string $date_start, string $date_end): array {
        $invoices = $this->api_get_all('invoices', 'invoices', [
            'date_start' => $date_start,
            'date_end'   => $date_end,
        ]);

        $total = 0.0;
        $items = [];
        $skk_keywords = ['sachkunde', 'ecls', 'skk'];

        foreach ($invoices as $inv) {
            if (in_array($inv['status'], ['void', 'draft'])) continue;

            try {
                $detail = $this->api_get("invoices/{$inv['invoice_id']}");
                $inv_detail = $detail['invoice'] ?? [];
            } catch (\Throwable $e) {
                continue;
            }

            $is_skk = false;
            foreach ($inv_detail['line_items'] ?? [] as $li) {
                $name = strtolower($li['name'] ?? '');
                $acc = strtolower($li['account_name'] ?? '');
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
                $t = floatval($inv['total']);
                $total += $t;
                $items[] = [
                    'number' => $inv['invoice_number'],
                    'date' => $inv['date'],
                    'customer' => $inv['customer_name'],
                    'total' => $t,
                ];
            }
        }

        return ['total' => $total, 'count' => count($items), 'items' => $items];
    }

    /* ------------------------------------------------------------ */
    /* SKK: Ausgaben (Konto Fremdleistungen SKK)                     */
    /* ------------------------------------------------------------ */

    public function get_skk_expenses(string $date_start, string $date_end): array {
        return $this->get_expenses_by_account($date_start, $date_end, 'Fremdleistungen SKK');
    }

    /* ------------------------------------------------------------ */
    /* Zeitschrift: Einnahmen                                         */
    /* ------------------------------------------------------------ */

    public function get_zeitschrift_income(string $date_start, string $date_end): array {
        // Einfacher: alle Invoices mit Zeitschrift-Items
        $invoices = $this->api_get_all('invoices', 'invoices', [
            'search_text' => 'Zeitschrift',
            'date_start'  => $date_start,
            'date_end'    => $date_end,
        ]);

        $total = 0.0;
        $items = [];
        foreach ($invoices as $inv) {
            if (in_array($inv['status'], ['void', 'draft'])) continue;
            $t = floatval($inv['total']);
            $total += $t;
            $items[] = [
                'number' => $inv['invoice_number'],
                'date' => $inv['date'],
                'customer' => $inv['customer_name'],
                'total' => $t,
            ];
        }
        return ['total' => $total, 'count' => count($items), 'items' => $items];
    }

    /* ------------------------------------------------------------ */
    /* Zeitschrift: Ausgaben                                           */
    /* ------------------------------------------------------------ */

    public function get_zeitschrift_expenses(string $date_start, string $date_end): array {
        return $this->get_expenses_by_account($date_start, $date_end, 'Fremdleistungen Zeitschrift');
    }

    /* ------------------------------------------------------------ */
    /* Offene Forderungen                                             */
    /* ------------------------------------------------------------ */

    public function get_open_invoices(): array {
        return $this->api_get_all('invoices', 'invoices', ['status' => 'unpaid']);
    }

    /* ------------------------------------------------------------ */
    /* Helper                                                         */
    /* ------------------------------------------------------------ */

    private function get_expenses_by_account(string $start, string $end, string $account_needle): array {
        // Bills
        $bills = $this->api_get_all('bills', 'bills', [
            'date_start' => $start, 'date_end' => $end,
        ]);
        $results = [];
        $total = 0.0;

        foreach ($bills as $bill) {
            try {
                $detail = $this->api_get("bills/{$bill['bill_id']}");
                $bd = $detail['bill'] ?? [];
            } catch (\Throwable $e) {
                continue;
            }
            $amount = 0.0;
            foreach ($bd['line_items'] ?? [] as $li) {
                if (stripos($li['account_name'] ?? '', $account_needle) !== false) {
                    $amount += floatval($li['item_total'] ?? 0);
                }
            }
            if ($amount > 0) {
                $results[] = ['date' => $bd['date'] ?? '', 'vendor' => $bd['vendor_name'] ?? '', 'total' => $amount];
                $total += $amount;
            }
        }

        // Expenses
        $expenses = $this->api_get_all('expenses', 'expenses', [
            'date_start' => $start, 'date_end' => $end,
        ]);
        foreach ($expenses as $exp) {
            if (stripos($exp['account_name'] ?? '', $account_needle) !== false) {
                $t = floatval($exp['total'] ?? 0);
                $results[] = ['date' => $exp['date'] ?? '', 'vendor' => $exp['vendor_name'] ?? '', 'total' => $t];
                $total += $t;
            }
        }

        return ['total' => $total, 'count' => count($results), 'items' => $results];
    }
}
