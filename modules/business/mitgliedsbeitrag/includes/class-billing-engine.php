<?php
/**
 * Billing Engine - Kern des Mitgliedsbeitrag-Tools
 *
 * Portiert aus Python Flask membership_billing.py
 *
 * Funktionen:
 * - run_billing(): Beitragsabrechnungslauf (dry-run / live)
 * - process_gocardless_payments(): Unbezahlte Rechnungen einziehen
 * - sync_gocardless_mandates(): CRM-Mandate mit GoCardless synchronisieren
 */

if (!defined('ABSPATH')) exit;

class DGPTM_MB_Billing_Engine {

    private DGPTM_MB_Config $config;
    private DGPTM_MB_Zoho_CRM $crm;
    private DGPTM_MB_Zoho_Books $books;
    private DGPTM_MB_GoCardless $gc;
    private array $fees_cache = [];
    private array $mandate_cache = [];

    public function __construct(DGPTM_MB_Config $config) {
        $this->config = $config;
        $this->crm    = new DGPTM_MB_Zoho_CRM($config);
        $this->books  = new DGPTM_MB_Zoho_Books($config);
        $this->gc     = new DGPTM_MB_GoCardless($config);
    }

    /* ================================================================ */
    /* Beitragsabrechnungslauf                                          */
    /* ================================================================ */

    public function run_billing(int $year, bool $dry_run = true, bool $create_invoices = true, bool $send_invoices = false, array $contact_ids = []): array {
        $this->log(sprintf('Billing-Lauf gestartet: Jahr=%d, DryRun=%s', $year, $dry_run ? 'ja' : 'nein'));

        // Beitragshoehen laden
        $this->fees_cache = $this->crm->get_all_fees();
        $this->log('Beitragshoehen geladen: ' . wp_json_encode($this->fees_cache));

        // Mandate vorladen
        $this->preload_mandates();

        // Mitglieder laden
        if (!empty($contact_ids)) {
            $members = [];
            foreach ($contact_ids as $cid) {
                $m = $this->crm->get_contact($cid);
                if ($m) $members[] = $m;
            }
        } else {
            $members = $this->crm->get_members_for_billing($year);
        }

        $this->log(sprintf('%d Mitglieder geladen', count($members)));

        $results = [
            'year'    => $year,
            'dry_run' => $dry_run,
            'members' => [],
            'summary' => [
                'total'     => 0,
                'skipped'   => 0,
                'processed' => 0,
                'errors'    => 0,
                'by_variant' => [],
            ],
        ];

        foreach ($members as $member) {
            $result = $this->process_member($member, $year, $dry_run, $create_invoices, $send_invoices);
            $results['members'][] = $result;
            $results['summary']['total']++;

            if ($result['status'] === 'skipped') {
                $results['summary']['skipped']++;
            } elseif ($result['status'] === 'error') {
                $results['summary']['errors']++;
            } else {
                $results['summary']['processed']++;
                $variant = $result['variant'] ?? 'unknown';
                $results['summary']['by_variant'][$variant] = ($results['summary']['by_variant'][$variant] ?? 0) + 1;
            }
        }

        $this->log(sprintf('Billing-Lauf abgeschlossen: %d verarbeitet, %d uebersprungen, %d Fehler',
            $results['summary']['processed'], $results['summary']['skipped'], $results['summary']['errors']));

        return $results;
    }

    /* ================================================================ */
    /* Einzelnes Mitglied verarbeiten                                    */
    /* ================================================================ */

    private function process_member(array $member, int $year, bool $dry_run, bool $create_invoices, bool $send_invoices): array {
        $contact_id = $member['id'] ?? '';
        $name = $member['Full_Name'] ?? 'Unbekannt';
        $type = $member['Membership_Type'] ?? '';
        $number = $member['Membership_Number'] ?? '';
        $last_year = $member['letztesBeitragsjahr'] ?? null;
        $credit = (float) ($member['Guthaben2'] ?? 0);
        $gc_customer_id = $member['GoCardlessID'] ?? '';
        $gc_mandate_id = $member['MandatID'] ?? '';
        $finance_id = $member['Finance_ID'] ?? '';
        $email = $member['Email'] ?? '';
        $status = $member['Contact_Status'] ?? '';

        $result = [
            'contact_id' => $contact_id,
            'name'       => $name,
            'type'       => $type,
            'number'     => $number,
            'status'     => 'pending',
            'variant'    => null,
            'fee'        => 0,
            'credit'     => $credit,
            'message'    => '',
        ];

        // Pruefung 1: Mitgliedstyp
        $types = $this->config->membership_types();
        if (!isset($types[$type])) {
            $result['status'] = 'skipped';
            $result['message'] = 'Unbekannter Mitgliedstyp: ' . $type;
            return $result;
        }

        // Pruefung 2: Skip Billing
        if (!empty($types[$type]['skip_billing'])) {
            $result['status'] = 'skipped';
            $result['message'] = 'Mitgliedstyp ueberspringt Abrechnung';
            return $result;
        }

        // Pruefung 3: Bereits abgerechnet
        if ($last_year !== null && (int) $last_year >= $year) {
            $result['status'] = 'skipped';
            $result['message'] = 'Bereits fuer ' . $year . ' abgerechnet';
            return $result;
        }

        // Pruefung 4: Freistellung
        if ($status === 'Freigestellt') {
            $freigestellt_bis = $member['Freigestellt_bis'] ?? '';
            if ($freigestellt_bis && strtotime($freigestellt_bis) > time()) {
                $result['status'] = 'skipped';
                $result['message'] = 'Freigestellt bis ' . $freigestellt_bis;
                return $result;
            }
        }

        // Beitragshoehe bestimmen
        $fee = $this->fees_cache[$type] ?? 0;

        // Studentenrabatt
        $is_student = !empty($member['Student_Status']);
        $valid_through = (int) ($member['Valid_Through'] ?? 0);
        if ($is_student && $valid_through >= $year) {
            $fee = $this->config->student_fee();
        }

        $result['fee'] = $fee;

        if ($fee <= 0) {
            $result['status'] = 'skipped';
            $result['message'] = 'Beitrag = 0';
            return $result;
        }

        // Finance_ID pruefen (Books-Kontakt)
        if (!$finance_id) {
            $books_contact = $this->books->get_contact_by_crm_id($contact_id);
            if ($books_contact) {
                $finance_id = $books_contact['contact_id'] ?? '';
            }
        }

        if (!$finance_id) {
            $result['status'] = 'error';
            $result['message'] = 'Kein Books-Kontakt (Finance_ID) gefunden';
            return $result;
        }

        // Guthaben aus Books laden (aktueller Stand)
        if (!$dry_run) {
            $credit = $this->books->get_customer_credits($finance_id);
            $result['credit'] = $credit;
        }

        // Mandat pruefen
        $has_mandate = false;
        $active_mandate_id = '';

        if ($gc_mandate_id) {
            $has_mandate = $this->is_mandate_active_cached($gc_mandate_id);
            if ($has_mandate) $active_mandate_id = $gc_mandate_id;
        }

        if (!$has_mandate && $gc_customer_id) {
            $mandate = $this->gc->get_usable_mandate_for_customer($gc_customer_id);
            if ($mandate) {
                $has_mandate = true;
                $active_mandate_id = $mandate['id'] ?? '';
            }
        }

        // Variante bestimmen
        $variant = $this->determine_variant($fee, $credit, $has_mandate);
        $result['variant'] = $variant;

        if ($dry_run) {
            $result['status'] = 'dry_run';
            $result['message'] = 'Dry-Run: Variante = ' . $variant;
            return $result;
        }

        // Live: Rechnung erstellen
        if ($create_invoices) {
            $invoice = $this->create_membership_invoice($member, $year, $fee, $variant, $finance_id, $credit);
            if (!$invoice) {
                $result['status'] = 'error';
                $result['message'] = 'Rechnung konnte nicht erstellt werden';
                return $result;
            }
            $result['invoice_id'] = $invoice['invoice_id'] ?? '';
        }

        // GoCardless Zahlung erstellen
        $variant_config = $this->config->invoice_variants()[$variant] ?? [];
        if (!empty($variant_config['process_gocardless']) && $has_mandate && $active_mandate_id) {
            $remaining = ($variant_config['apply_credit'] ?? false) ? max(0, $fee - $credit) : $fee;
            if ($remaining > 0) {
                $payment = $this->gc->create_payment(
                    (int) round($remaining * 100),
                    $active_mandate_id,
                    sprintf('Mitgliedsbeitrag %d - DGPTM', $year),
                    ['crm_contact_id' => $contact_id, 'year' => (string) $year]
                );
                if ($payment) {
                    $result['gc_payment_id'] = $payment['id'] ?? '';
                }
            }
        }

        // CRM aktualisieren
        $crm_update = [
            'letztesBeitragsjahr' => $year,
            'last_fee' => $fee,
            'lastMembershipInvoicing' => date('Y-m-d'),
        ];

        if (!empty($result['invoice_id'])) {
            $crm_update['last_invoice'] = $result['invoice_id'];
        }
        if (!empty($result['gc_payment_id'])) {
            $crm_update['goCardlessPayment'] = $result['gc_payment_id'];
        }

        $this->crm->update_contact($contact_id, $crm_update);

        $result['status'] = 'success';
        $result['message'] = 'Abgerechnet: ' . $variant;
        return $result;
    }

    /* ================================================================ */
    /* Rechnungsvariante bestimmen                                      */
    /* ================================================================ */

    private function determine_variant(float $fee, float $credit, bool $has_mandate): string {
        if ($credit >= $fee) {
            return 'credit_sufficient';
        }
        if ($has_mandate && $credit <= 0) {
            return 'gocardless';
        }
        if ($has_mandate && $credit > 0 && $credit < $fee) {
            return 'gocardless_with_credit';
        }
        if ($credit > 0 && $credit < $fee && !$has_mandate) {
            return 'transfer_with_credit';
        }
        return 'transfer_no_credit';
    }

    /* ================================================================ */
    /* Rechnung erstellen                                                */
    /* ================================================================ */

    private function create_membership_invoice(array $member, int $year, float $fee, string $variant, string $finance_id, float $credit): ?array {
        $number = ($member['Membership_Number'] ?? '') . '-' . $year;
        $type = $member['Membership_Type'] ?? '';
        $types = $this->config->membership_types();
        $item_id = $types[$type]['item_id'] ?? $this->config->books_setting('membership_item_id');

        // Rechnungsdatum: 1. Maerz oder heute
        $march_1 = "{$year}-03-01";
        $today = date('Y-m-d');
        $invoice_date = ($today > $march_1) ? $today : $march_1;

        // Faelligkeit: +4 Wochen oder 31.12.
        $due = date('Y-m-d', strtotime($invoice_date . ' +4 weeks'));
        $dec_31 = "{$year}-12-31";
        if ($due > $dec_31) $due = $dec_31;

        // Notes aus Variante
        $variant_config = $this->config->invoice_variants()[$variant] ?? [];
        $notes_template = $variant_config['notes_template'] ?? '';
        $remaining = max(0, $fee - $credit);
        $notes = str_replace(
            ['{credit:.2f}', '{remaining:.2f}', '{year}'],
            [number_format($credit, 2, '.', ''), number_format($remaining, 2, '.', ''), $year],
            $notes_template
        );

        $invoice_data = [
            'customer_id'    => $finance_id,
            'invoice_number' => $number,
            'template_id'    => $this->config->books_setting('invoice_template_id'),
            'date'           => $invoice_date,
            'due_date'       => $due,
            'line_items'     => [[
                'item_id'     => $item_id,
                'description' => sprintf('Mitgliedsbeitrag %d - %s', $year, $type),
                'rate'        => $fee,
                'quantity'    => 1,
                'account_id'  => $this->config->books_setting('account_id'),
            ]],
            'notes'          => $notes,
            'subject'        => 'Jahresbeitrag ' . $year,
            'is_inclusive_tax' => true,
            'custom_fields'  => [
                ['api_name' => $this->config->books_setting('custom_fields.beitragsrechnung', 'cf_beitragsrechnung'), 'value' => true],
            ],
        ];

        return $this->books->create_invoice($invoice_data);
    }

    /* ================================================================ */
    /* GoCardless Zahlungen verarbeiten                                  */
    /* ================================================================ */

    public function process_gocardless_payments(bool $dry_run = true): array {
        $unpaid = $this->books->get_unpaid_invoices('true');
        $results = ['total' => count($unpaid), 'processed' => 0, 'skipped' => 0, 'errors' => 0, 'details' => []];

        foreach ($unpaid as $invoice) {
            $gc_id = '';
            // Suche GoCardless Payment ID in Custom Fields
            foreach ($invoice['custom_fields'] ?? [] as $cf) {
                if (($cf['api_name'] ?? '') === $this->config->books_setting('custom_fields.gocardless_payment_id', 'cf_gocardlessid')) {
                    $gc_id = $cf['value'] ?? '';
                }
            }

            if ($gc_id) {
                // Zahlung pruefen
                $payment = $this->gc->get_payment($gc_id);
                $status = $payment['status'] ?? 'unknown';

                if ($status === 'paid_out' && !$dry_run) {
                    $amount = ((float) ($payment['amount'] ?? 0)) / 100;
                    $gc_fee = 0.20 + ($amount * 0.01);

                    $this->books->record_payment(
                        $invoice['invoice_id'],
                        $amount,
                        date('Y-m-d'),
                        'GoCardless',
                        $gc_id,
                        $this->config->books_setting('gocardless_clearing_account_id'),
                        $gc_fee,
                        $this->config->books_setting('gocardless_fee_account_id')
                    );
                    $results['processed']++;
                } else {
                    $results['skipped']++;
                }

                $results['details'][] = [
                    'invoice' => $invoice['invoice_number'] ?? '',
                    'gc_status' => $status,
                    'action' => ($status === 'paid_out' && !$dry_run) ? 'recorded' : 'skipped',
                ];
            } else {
                $results['skipped']++;
            }
        }

        return $results;
    }

    /* ================================================================ */
    /* Mandats-Synchronisation                                          */
    /* ================================================================ */

    public function sync_gocardless_mandates(bool $dry_run = true): array {
        $customers = $this->gc->get_all_customers();
        $mandates = $this->gc->get_all_active_mandates();

        // Email -> Customer Map
        $email_map = [];
        foreach ($customers as $c) {
            $email = strtolower($c['email'] ?? '');
            if ($email) $email_map[$email] = $c['id'] ?? '';
        }

        // Customer -> Mandate Map
        $mandate_map = [];
        foreach ($mandates as $m) {
            $cust_id = $m['links']['customer'] ?? '';
            if ($cust_id && !isset($mandate_map[$cust_id])) {
                $mandate_map[$cust_id] = $m['id'] ?? '';
            }
        }

        // CRM-Mitglieder laden
        $members = $this->crm->get_members_for_billing((int) date('Y'));
        $results = ['total' => count($members), 'updated' => 0, 'matched' => 0, 'details' => []];

        foreach ($members as $member) {
            $emails = array_filter(array_map('strtolower', [
                $member['Email'] ?? '',
                $member['Secondary_Email'] ?? '',
                $member['Third_Email'] ?? '',
                $member['DGPTMMail'] ?? '',
            ]));

            $gc_customer = '';
            foreach ($emails as $email) {
                if (isset($email_map[$email])) {
                    $gc_customer = $email_map[$email];
                    break;
                }
            }

            if (!$gc_customer) continue;
            $results['matched']++;

            $gc_mandate = $mandate_map[$gc_customer] ?? '';
            $current_gc = $member['GoCardlessID'] ?? '';
            $current_mandate = $member['MandatID'] ?? '';

            if ($gc_customer !== $current_gc || $gc_mandate !== $current_mandate) {
                if (!$dry_run) {
                    $this->crm->update_contact($member['id'], [
                        'GoCardlessID' => $gc_customer,
                        'MandatID' => $gc_mandate,
                    ]);
                }
                $results['updated']++;
                $results['details'][] = [
                    'name' => $member['Full_Name'] ?? '',
                    'gc_customer' => $gc_customer,
                    'mandate' => $gc_mandate,
                ];
            }
        }

        return $results;
    }

    /* ================================================================ */
    /* Helpers                                                          */
    /* ================================================================ */

    private function preload_mandates(): void {
        $mandates = $this->gc->get_all_active_mandates();
        foreach ($mandates as $m) {
            $this->mandate_cache[$m['id'] ?? ''] = true;
        }
        $this->log(sprintf('%d aktive Mandate geladen', count($this->mandate_cache)));
    }

    private function is_mandate_active_cached(string $mandate_id): bool {
        if (isset($this->mandate_cache[$mandate_id])) {
            return true;
        }
        return $this->gc->is_mandate_active($mandate_id);
    }

    private function log(string $msg): void {
        if (class_exists('DGPTM_Logger')) {
            \DGPTM_Logger::info($msg, 'mitgliedsbeitrag');
        }
    }
}
