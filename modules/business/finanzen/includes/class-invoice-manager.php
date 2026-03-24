<?php
/**
 * Invoice Manager
 *
 * Offene Beitragsrechnungen verwalten: Laden, Anreichern, Sortieren,
 * GoCardless-Einzug, Ruecklastschrift-Behandlung, Guthaben-Verrechnung.
 *
 * @package DGPTM_Finanzen
 */

if (!defined('ABSPATH')) exit;

class DGPTM_FIN_Invoice_Manager {

    const CACHE_KEY = 'dgptm_fin_open_invoices';
    const CACHE_TTL = 300; // 5 Minuten
    const LOG_PREFIX = '[DGPTM Finanzen Invoices]';

    /** Aktive GC-Status, die einen Doppeleinzug verhindern. */
    const GC_ACTIVE_STATUSES = [
        'pending_submission',
        'submitted',
        'confirmed',
        'pending_customer_approval',
        'paid_out',
    ];

    /** GC-Status, die als "in Einziehung" gelten (Sortierung). */
    const GC_PENDING_STATUSES = [
        'pending_submission',
        'submitted',
        'confirmed',
        'pending_customer_approval',
    ];

    private DGPTM_FIN_Config $config;
    private DGPTM_FIN_Zoho_CRM $crm;
    private DGPTM_FIN_Zoho_Books $books;
    private DGPTM_FIN_GoCardless $gc;

    public function __construct(DGPTM_FIN_Config $config) {
        $this->config = $config;
        $this->crm    = new DGPTM_FIN_Zoho_CRM($config);
        $this->books  = new DGPTM_FIN_Zoho_Books($config);
        $this->gc     = new DGPTM_FIN_GoCardless($config);
    }

    /* ================================================================ */
    /* Offene Rechnungen laden                                           */
    /* ================================================================ */

    /**
     * Offene Beitragsrechnungen mit Mandat-Info laden.
     *
     * Ablauf:
     * 1. Unbezahlte Rechnungen mit cf_beitragsrechnung=true aus Books
     * 2. CRM-Lookup: Finance_ID -> Kontakt (GoCardlessID, Telefon)
     * 3. GoCardless-Mandate vorladen
     * 4. Pro Rechnung: has_mandate Flag setzen
     * 5. Sortieren und cachen
     *
     * @return array Angereicherte Rechnungsobjekte
     */
    public function get_open_invoices(): array {
        // Cache pruefen
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached) && !empty($cached)) {
            return $cached;
        }

        try {
            // 1. Unbezahlte Beitragsrechnungen
            $invoices = $this->books->get_unpaid_invoices('true');

            // 2. CRM-Lookup: Finance_ID -> Kontaktdaten
            $crm_by_finance_id = $this->build_crm_lookup();

            // 3. Alle aktiven GoCardless-Mandate vorladen
            $mandates = $this->gc->get_all_active_mandates();
            $mandate_by_customer = [];
            foreach ($mandates as $m) {
                $gc_customer_id = $m['links']['customer'] ?? '';
                if ($gc_customer_id && in_array($m['status'] ?? '', ['active', 'pending_submission'], true)) {
                    $mandate_by_customer[$gc_customer_id] = $m;
                }
            }

            // 4. Rechnungen anreichern
            $result = [];
            foreach ($invoices as $inv) {
                $customer_id = $inv['customer_id'] ?? '';
                $crm_contact = $crm_by_finance_id[$customer_id] ?? [];

                $gc_customer_id = $crm_contact['GoCardlessID'] ?? '';
                $mandate        = $mandate_by_customer[$gc_customer_id] ?? null;

                $result[] = [
                    'invoice_id'      => $inv['invoice_id'] ?? '',
                    'invoice_number'  => $inv['invoice_number'] ?? '',
                    'customer_name'   => $inv['customer_name'] ?? '',
                    'customer_id'     => $customer_id,
                    'total'           => (float) ($inv['total'] ?? 0),
                    'balance'         => (float) ($inv['balance'] ?? 0),
                    'date'            => $inv['date'] ?? '',
                    'due_date'        => $inv['due_date'] ?? '',
                    'status'          => $inv['status'] ?? '',
                    'crm_contact_id'  => $crm_contact['id'] ?? '',
                    'phone'           => $crm_contact['Phone'] ?? '',
                    'mobile'          => $crm_contact['Mobile'] ?? '',
                    'has_mandate'     => $mandate !== null,
                    'mandate_id'      => $mandate ? ($mandate['id'] ?? '') : '',
                    'gc_status'       => '',
                    'gc_payment_id'   => '',
                    'credit'          => 0,
                ];
            }

            // 5. Sortieren
            $result = $this->sort_invoices($result);

            // Cache setzen
            set_transient(self::CACHE_KEY, $result, self::CACHE_TTL);

            return $result;
        } catch (\Throwable $e) {
            error_log(self::LOG_PREFIX . ' get_open_invoices Fehler: ' . $e->getMessage());
            return [];
        }
    }

    /* ================================================================ */
    /* Enrichment: GC-Status + Guthaben                                  */
    /* ================================================================ */

    /**
     * Rechnungen mit GC-Zahlungsstatus und Kundenguthaben anreichern.
     *
     * @param array $invoices Rechnungsliste (aus get_open_invoices)
     * @return array Angereicherte Liste mit gc_status, gc_payment_id, credit
     */
    public function enrich_invoices(array $invoices): array {
        $gc_field = $this->config->books_setting(
            'custom_fields.gocardless_payment_id',
            'cf_gocardlessid'
        );

        // Guthaben pro Kunde nur einmal abfragen
        $customer_credits = [];

        foreach ($invoices as &$entry) {
            $inv_id = $entry['invoice_id'] ?? '';
            if (!$inv_id) continue;

            // Vollstaendige Rechnung laden (fuer Custom Fields)
            $full_inv = $this->books->get_invoice($inv_id);
            if (!$full_inv) continue;

            // GoCardless-Status aus Custom Field
            foreach ($full_inv['custom_fields'] ?? [] as $cf) {
                if (($cf['api_name'] ?? '') === $gc_field && !empty($cf['value'])) {
                    $gc_payment = $this->gc->get_payment($cf['value']);
                    if ($gc_payment) {
                        $entry['gc_payment_id'] = $cf['value'];
                        $entry['gc_status']     = $gc_payment['status'] ?? '';
                    }
                    break;
                }
            }

            // Kundenguthaben
            $cid = $full_inv['customer_id'] ?? '';
            if ($cid) {
                if (!isset($customer_credits[$cid])) {
                    $customer_credits[$cid] = $this->books->get_customer_credits($cid);
                }
                $entry['credit'] = $customer_credits[$cid];
            }
        }
        unset($entry);

        return $this->sort_invoices($invoices);
    }

    /* ================================================================ */
    /* Sortierung                                                        */
    /* ================================================================ */

    /**
     * Rechnungen nach Prioritaet sortieren.
     *
     * Reihenfolge:
     * 0 = Entwurf (Draft)
     * 1 = Ruecklastschrift (charged_back)
     * 2 = Fehlgeschlagen/Storniert (failed, cancelled)
     * 3 = In Einziehung (pending GC payment)
     * 4 = Hat Mandat
     * 5 = Rest
     *
     * Innerhalb jeder Gruppe: nach Faelligkeitsdatum aufsteigend.
     *
     * @param array $invoices
     * @return array Sortierte Liste
     */
    public function sort_invoices(array $invoices): array {
        usort($invoices, function (array $a, array $b): int {
            $pa = $this->sort_priority($a);
            $pb = $this->sort_priority($b);

            if ($pa !== $pb) {
                return $pa - $pb;
            }

            // Sekundaer: Faelligkeitsdatum aufsteigend
            return strcmp($a['due_date'] ?? '', $b['due_date'] ?? '');
        });

        return $invoices;
    }

    /**
     * Sortier-Prioritaet fuer eine Rechnung bestimmen.
     */
    private function sort_priority(array $inv): int {
        $gc_status = $inv['gc_status'] ?? '';

        if (($inv['status'] ?? '') === 'draft') {
            return 0;
        }
        if ($gc_status === 'charged_back') {
            return 1;
        }
        if (in_array($gc_status, ['failed', 'cancelled'], true)) {
            return 2;
        }
        if (in_array($gc_status, self::GC_PENDING_STATUSES, true)) {
            return 3;
        }
        if (!empty($inv['has_mandate'])) {
            return 4;
        }

        return 5;
    }

    /* ================================================================ */
    /* GoCardless-Einzug                                                 */
    /* ================================================================ */

    /**
     * GoCardless-Zahlung fuer eine offene Rechnung erstellen.
     *
     * Validierung:
     * - Rechnung existiert und hat offenen Saldo
     * - Keine aktive GC-Zahlung vorhanden (Doppeleinzug-Schutz)
     * - Mandat wird aus CRM-Lookup oder uebergebener mandate_id ermittelt
     *
     * @param string $invoice_id   Books Invoice ID
     * @param string $mandate_id   Optional: GoCardless Mandate ID (sonst aus CRM)
     * @param string $contact_id   Optional: CRM Contact ID fuer Rueckschreib
     * @return array ['success', 'payment_id', 'amount', 'error']
     */
    public function collect_payment(string $invoice_id, string $mandate_id = '', string $contact_id = ''): array {
        // 1. Rechnung laden und validieren
        $invoice = $this->books->get_invoice($invoice_id);
        if (!$invoice) {
            return ['success' => false, 'error' => 'Rechnung nicht gefunden.'];
        }

        $balance = (float) ($invoice['balance'] ?? 0);
        if ($balance <= 0) {
            return ['success' => false, 'error' => 'Rechnung ist bereits bezahlt (Balance: 0).'];
        }

        // 2. Aktive GC-Zahlung pruefen (Doppeleinzug verhindern)
        $gc_field = $this->config->books_setting(
            'custom_fields.gocardless_payment_id',
            'cf_gocardlessid'
        );

        foreach ($invoice['custom_fields'] ?? [] as $cf) {
            if (($cf['api_name'] ?? '') === $gc_field && !empty($cf['value'])) {
                $existing = $this->gc->get_payment($cf['value']);
                if ($existing && in_array($existing['status'] ?? '', self::GC_ACTIVE_STATUSES, true)) {
                    return [
                        'success' => false,
                        'error'   => sprintf(
                            'Aktive GoCardless-Zahlung vorhanden (%s, Status: %s).',
                            $cf['value'],
                            $existing['status'] ?? ''
                        ),
                    ];
                }
                break;
            }
        }

        // 3. Mandat ermitteln (falls nicht uebergeben)
        if (!$mandate_id) {
            return ['success' => false, 'error' => 'Keine Mandate-ID angegeben.'];
        }

        // 4. GoCardless-Zahlung erstellen
        $invoice_number = $invoice['invoice_number'] ?? '';
        $amount_cents   = (int) round($balance * 100);
        $description    = sprintf('Mitgliedsbeitrag - DGPTM (Rechnung %s)', $invoice_number);

        $metadata = [];
        if ($contact_id) {
            $metadata['crm_contact_id'] = $contact_id;
        }
        if ($invoice_id) {
            $metadata['invoice_id'] = $invoice_id;
        }
        if ($invoice_number) {
            $metadata['invoice_number'] = $invoice_number;
        }

        $payment = $this->gc->create_payment($amount_cents, $mandate_id, $description, $metadata);
        if (!$payment) {
            return ['success' => false, 'error' => 'GoCardless-Zahlung konnte nicht erstellt werden.'];
        }

        // 5. Payment-ID auf der Rechnung speichern
        $this->books->update_invoice($invoice_id, [
            'custom_fields' => [
                ['api_name' => $gc_field, 'value' => $payment['id']],
            ],
        ]);

        // 6. CRM-Kontakt aktualisieren
        if ($contact_id) {
            $this->crm->update_contact($contact_id, [
                'goCardlessPayment' => $payment['id'],
            ]);
        }

        // Cache invalidieren
        $this->invalidate_cache();

        return [
            'success'    => true,
            'payment_id' => $payment['id'],
            'amount'     => $balance,
            'error'      => '',
        ];
    }

    /* ================================================================ */
    /* Ruecklastschrift-Behandlung                                       */
    /* ================================================================ */

    /**
     * Manuelle Ruecklastschrift-Behandlung.
     *
     * Aktionen:
     * - delete_payment: Books-Zahlung loeschen (Rechnung wird wieder offen)
     * - add_fee: Ruecklastschriftgebuehr als Position hinzufuegen
     * - both: Beides
     *
     * @param string $invoice_id      Books Invoice ID
     * @param string $action          'delete_payment', 'add_fee', 'both'
     * @param string $books_payment_id  Books Payment ID (fuer delete_payment)
     * @return array Ergebnis-Dict
     */
    public function handle_chargeback(string $invoice_id, string $action = 'both', string $books_payment_id = ''): array {
        $results = [];

        // 1. Zahlung loeschen
        if (in_array($action, ['delete_payment', 'both'], true) && $books_payment_id) {
            $deleted = $this->books->delete_payment($books_payment_id);
            $results['payment_deleted'] = $deleted;
        }

        // 2. Ruecklastschriftgebuehr hinzufuegen
        if (in_array($action, ['add_fee', 'both'], true)) {
            $chargeback_fee = $this->config->chargeback_fee();
            $fee_account_id = $this->config->books_setting('chargeback_fee_account', '');

            $added = $this->books->add_charge_to_invoice(
                $invoice_id,
                $chargeback_fee,
                'Ruecklastschriftgebuehr',
                $fee_account_id
            );

            $results['fee_added']      = $added;
            $results['chargeback_fee'] = $chargeback_fee;
        }

        // Cache invalidieren
        $this->invalidate_cache();

        return $results;
    }

    /* ================================================================ */
    /* Guthaben-Verrechnung                                              */
    /* ================================================================ */

    /**
     * Guthaben auf Rechnung anwenden, optional Restbetrag per GoCardless einziehen.
     *
     * @param string $invoice_id        Books Invoice ID
     * @param bool   $collect_remainder Restbetrag per GC einziehen?
     * @param string $mandate_id        GC Mandate ID (fuer Resteinzug)
     * @param string $contact_id        CRM Contact ID (fuer Rueckschreib)
     * @return array ['credit_applied', 'remaining', 'collected']
     */
    public function apply_credit(
        string $invoice_id,
        bool $collect_remainder = false,
        string $mandate_id = '',
        string $contact_id = ''
    ): array {
        $invoice = $this->books->get_invoice($invoice_id);
        if (!$invoice) {
            return ['credit_applied' => 0, 'remaining' => 0, 'collected' => false, 'error' => 'Rechnung nicht gefunden.'];
        }

        $customer_id = $invoice['customer_id'] ?? '';
        $balance     = (float) ($invoice['balance'] ?? 0);

        // Guthaben pruefen
        $credit = $this->books->get_customer_credits($customer_id);
        if ($credit <= 0) {
            return ['credit_applied' => 0, 'remaining' => $balance, 'collected' => false, 'error' => 'Kein Guthaben vorhanden.'];
        }

        $credit_to_apply = min($credit, $balance);
        $remaining       = $balance - $credit_to_apply;

        // Rechnung ggf. versenden (Entwurf -> gesendet, noetig fuer Credit-Anwendung)
        if (($invoice['status'] ?? '') === 'draft') {
            $email = $invoice['email'] ?? '';
            if ($email) {
                $template_id = $this->config->books_setting('email_template_id', '');
                $this->books->send_invoice($invoice_id, [$email], $template_id);
            }
        }

        // Guthaben anwenden
        $applied = $this->books->apply_credits_to_invoice($invoice_id, $credit_to_apply);

        $result = [
            'credit_applied' => $applied ? $credit_to_apply : 0,
            'remaining'      => $remaining,
            'collected'      => false,
        ];

        // Optional: Restbetrag per GoCardless einziehen
        if ($collect_remainder && $remaining > 0 && $mandate_id) {
            $gc_result = $this->collect_payment($invoice_id, $mandate_id, $contact_id);
            $result['collected'] = $gc_result['success'] ?? false;
            if (!empty($gc_result['payment_id'])) {
                $result['gc_payment_id'] = $gc_result['payment_id'];
            }
            if (!empty($gc_result['error'])) {
                $result['gc_error'] = $gc_result['error'];
            }
        }

        // Cache invalidieren
        $this->invalidate_cache();

        return $result;
    }

    /* ================================================================ */
    /* Versand ohne Guthaben                                             */
    /* ================================================================ */

    /**
     * Entwurfsrechnung ohne Guthaben-Anwendung versenden.
     *
     * @param string $invoice_id Books Invoice ID
     * @return array ['success', 'error']
     */
    public function send_without_credit(string $invoice_id): array {
        $invoice = $this->books->get_invoice($invoice_id);
        if (!$invoice) {
            return ['success' => false, 'error' => 'Rechnung nicht gefunden.'];
        }

        if (($invoice['status'] ?? '') !== 'draft') {
            return ['success' => false, 'error' => 'Rechnung ist kein Entwurf.'];
        }

        $email = $invoice['email'] ?? '';
        if (!$email) {
            return ['success' => false, 'error' => 'Keine E-Mail-Adresse vorhanden.'];
        }

        $template_id = $this->config->books_setting('email_template_id', '');
        $sent = $this->books->send_invoice($invoice_id, [$email], $template_id);

        // Cache invalidieren
        $this->invalidate_cache();

        return [
            'success' => $sent,
            'error'   => $sent ? '' : 'Versand fehlgeschlagen.',
        ];
    }

    /* ================================================================ */
    /* Cache                                                             */
    /* ================================================================ */

    /**
     * Cache invalidieren.
     */
    public function invalidate_cache(): void {
        delete_transient(self::CACHE_KEY);
    }

    /* ================================================================ */
    /* Private Helpers                                                    */
    /* ================================================================ */

    /**
     * CRM-Kontakte mit Finance_ID laden und als Dict {finance_id: contact} zurueckgeben.
     *
     * Verwendet die Zoho CRM Search API, um alle Mitglieder mit Finance_ID
     * und GoCardless-Daten zu laden.
     *
     * @return array Assoziatives Array: Finance_ID -> CRM-Kontakt
     */
    private function build_crm_lookup(): array {
        $contacts = $this->crm->get_all_members_with_finance_id();

        $lookup = [];
        foreach ($contacts as $c) {
            $finance_id = $c['Finance_ID'] ?? '';
            if ($finance_id) {
                $lookup[$finance_id] = $c;
            }
        }

        return $lookup;
    }
}
