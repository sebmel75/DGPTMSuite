<?php
/**
 * Schatzmeister-Modul: Offene Zahlungen aus CRM und Books.
 *
 * Laedt Eintraege aus drei Quellen:
 * 1. CRM Expenses mit Status "An Schatzmeister uebergeben"
 * 2. CRM EduGrant (Custom Module) mit gleichem Status
 * 3. Zoho Books Bills mit cf_zahlstatus "Schatzmeister benachrichtigt"
 *
 * Portiert von treasurer.py (Python-Dashboard) nach PHP.
 *
 * @package DGPTM_Finanzen
 */

if (!defined('ABSPATH')) exit;

class DGPTM_FIN_Treasurer {

    private DGPTM_FIN_Config $config;

    const LOG_PREFIX = '[DGPTM Finanzen Treasurer]';

    public function __construct(DGPTM_FIN_Config $config) {
        $this->config = $config;
    }

    /**
     * Alle offenen Schatzmeister-Eintraege laden.
     *
     * @return array ['expenses' => [...], 'edugrants' => [...], 'bills' => [...], 'totals' => [...]]
     */
    public function get_entries(): array {
        $expenses  = $this->load_crm_expenses();
        $edugrants = $this->load_crm_edugrants();
        $bills     = $this->load_books_bills();

        $totals = [
            'expenses_total'  => array_sum(array_column($expenses, 'amount')),
            'edugrants_total' => array_sum(array_column($edugrants, 'amount')),
            'bills_total'     => array_sum(array_column($bills, 'amount')),
        ];
        $totals['grand_total'] = $totals['expenses_total'] + $totals['edugrants_total'] + $totals['bills_total'];
        $totals['count']       = count($expenses) + count($edugrants) + count($bills);

        return [
            'expenses'  => $expenses,
            'edugrants' => $edugrants,
            'bills'     => $bills,
            'totals'    => $totals,
        ];
    }

    /**
     * Eintrag als ueberwiesen markieren.
     *
     * - CRM: Blueprint-Transition ausloesen
     * - Books: Custom-Field cf_zahlstatus auf "Ist ueberwiesen" setzen
     *
     * @param string $module        'expenses', 'edugrants' oder 'bills'
     * @param string $record_id     Record-ID
     * @param string $transition_id Optional: Blueprint Transition ID fuer CRM
     * @return bool Erfolg
     */
    public function mark_transferred(string $module, string $record_id, string $transition_id = ''): bool {
        switch ($module) {
            case 'expenses':
                return $this->mark_crm_transferred('Expenses', $record_id, $transition_id);

            case 'edugrants':
                return $this->mark_crm_transferred('EduGrant', $record_id, $transition_id);

            case 'bills':
                return $this->mark_bill_transferred($record_id);

            default:
                error_log(self::LOG_PREFIX . " Unbekanntes Modul: $module");
                return false;
        }
    }

    /* ================================================================ */
    /* CRM Expenses                                                      */
    /* ================================================================ */

    private function load_crm_expenses(): array {
        $crm = new DGPTM_FIN_Zoho_CRM($this->config);
        $query = "SELECT id, Name, Amount, Date_field, Description, IBAN, BIC, Empfaenger, Status "
               . "FROM Expenses "
               . "WHERE Status = 'An Schatzmeister übergeben' "
               . "LIMIT 200";

        $records = $this->coql_query($crm, $query);
        $entries = [];

        foreach ($records as $rec) {
            $entries[] = [
                'id'          => $rec['id'] ?? '',
                'name'        => $rec['Name'] ?? '',
                'amount'      => (float) ($rec['Amount'] ?? 0),
                'date'        => $rec['Date_field'] ?? '',
                'description' => $rec['Description'] ?? '',
                'iban'        => $rec['IBAN'] ?? '',
                'bic'         => $rec['BIC'] ?? '',
                'recipient'   => $rec['Empfaenger'] ?? '',
                'type'        => 'expense',
            ];
        }

        return $entries;
    }

    /* ================================================================ */
    /* CRM EduGrant (Custom Module)                                      */
    /* ================================================================ */

    private function load_crm_edugrants(): array {
        $crm = new DGPTM_FIN_Zoho_CRM($this->config);
        $query = "SELECT id, Name, Amount, Date_field, Description, IBAN, BIC, Empfaenger, Status "
               . "FROM EduGrant "
               . "WHERE Status = 'An Schatzmeister übergeben' "
               . "LIMIT 200";

        $records = $this->coql_query($crm, $query);
        $entries = [];

        foreach ($records as $rec) {
            $entries[] = [
                'id'          => $rec['id'] ?? '',
                'name'        => $rec['Name'] ?? '',
                'amount'      => (float) ($rec['Amount'] ?? 0),
                'date'        => $rec['Date_field'] ?? '',
                'description' => $rec['Description'] ?? '',
                'iban'        => $rec['IBAN'] ?? '',
                'bic'         => $rec['BIC'] ?? '',
                'recipient'   => $rec['Empfaenger'] ?? '',
                'type'        => 'edugrant',
            ];
        }

        return $entries;
    }

    /* ================================================================ */
    /* Books Bills                                                        */
    /* ================================================================ */

    private function load_books_bills(): array {
        $books = new DGPTM_FIN_Zoho_Books($this->config);
        $entries = [];

        // cf_zahlstatus ist ein Custom Field in Books Bills
        // Lade offene Bills und filtere nach Custom Field
        try {
            $method = new \ReflectionMethod($books, 'api_get_all');
            $method->setAccessible(true);
            $all_bills = $method->invoke($books, 'bills', 'bills', [
                'status'         => 'open',
                'cf_zahlstatus'  => 'Schatzmeister benachrichtigt',
            ]);
        } catch (\Throwable $e) {
            error_log(self::LOG_PREFIX . ' Bills laden fehlgeschlagen: ' . $e->getMessage());
            return [];
        }

        foreach ($all_bills as $bill) {
            $entries[] = [
                'id'          => $bill['bill_id'] ?? '',
                'name'        => $bill['bill_number'] ?? '',
                'amount'      => (float) ($bill['total'] ?? 0),
                'date'        => $bill['date'] ?? '',
                'description' => $bill['reference_number'] ?? '',
                'vendor'      => $bill['vendor_name'] ?? '',
                'type'        => 'bill',
            ];
        }

        return $entries;
    }

    /* ================================================================ */
    /* Helpers                                                            */
    /* ================================================================ */

    private function coql_query(DGPTM_FIN_Zoho_CRM $crm, string $query): array {
        // Nutze Reflection um die private coql_query Methode zu erreichen,
        // oder nutze die oeffentliche API. Da coql_query privat ist,
        // verwenden wir den get_token + wp_remote_post Weg direkt.
        // Besser: Wir nutzen die api_request Methode ueber den CRM Client.
        // Da COQL nicht direkt exponiert ist, nutzen wir Reflection.
        try {
            $method = new \ReflectionMethod($crm, 'coql_query');
            $method->setAccessible(true);
            return $method->invoke($crm, $query);
        } catch (\Throwable $e) {
            error_log(self::LOG_PREFIX . ' COQL fehlgeschlagen: ' . $e->getMessage());
            return [];
        }
    }

    private function mark_crm_transferred(string $module_name, string $record_id, string $transition_id): bool {
        $crm = new DGPTM_FIN_Zoho_CRM($this->config);

        // Blueprint-Transition ausloesen ueber API
        try {
            $method = new \ReflectionMethod($crm, 'api_request');
            $method->setAccessible(true);

            if ($transition_id) {
                $result = $method->invoke($crm,
                    "{$module_name}/{$record_id}/actions/blueprint",
                    'PUT',
                    [
                        'blueprint' => [
                            ['transition_id' => $transition_id, 'data' => new \stdClass()],
                        ],
                    ]
                );
                return !empty($result);
            }

            // Ohne transition_id: Status-Feld direkt updaten
            $result = $method->invoke($crm,
                "{$module_name}/{$record_id}",
                'PUT',
                [
                    'data' => [
                        ['Status' => 'Ist überwiesen'],
                    ],
                ]
            );
            return !empty($result['data'][0]['status']) && $result['data'][0]['status'] === 'success';
        } catch (\Throwable $e) {
            error_log(self::LOG_PREFIX . " CRM Update fehlgeschlagen ($module_name/$record_id): " . $e->getMessage());
            return false;
        }
    }

    private function mark_bill_transferred(string $bill_id): bool {
        $books = new DGPTM_FIN_Zoho_Books($this->config);

        try {
            $method = new \ReflectionMethod($books, 'api_request');
            $method->setAccessible(true);

            $result = $method->invoke($books,
                'bills/' . $bill_id,
                'PUT',
                [
                    'custom_fields' => [
                        ['label' => 'cf_zahlstatus', 'value' => 'Ist überwiesen'],
                    ],
                ]
            );

            return !empty($result) && ($result['code'] ?? 1) === 0;
        } catch (\Throwable $e) {
            error_log(self::LOG_PREFIX . " Bill Update fehlgeschlagen ($bill_id): " . $e->getMessage());
            return false;
        }
    }
}
