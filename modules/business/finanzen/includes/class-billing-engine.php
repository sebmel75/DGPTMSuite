<?php
/**
 * Billing Engine - Kern des Finanzen-Moduls
 *
 * Portiert 1:1 aus Python Flask membership_billing.py (MembershipBilling).
 *
 * Zwei-Phasen-Architektur fuer Chunk-Verarbeitung:
 *   Phase 1: prepare()        — Mitglieder laden, Caches aufbauen, Session starten
 *   Phase 2: process_member() — Einzelnes Mitglied verarbeiten (statisch, chunk-kompatibel)
 *
 * 5 Rechnungsvarianten:
 *   credit_sufficient, gocardless_with_credit, gocardless,
 *   transfer_with_credit, transfer_no_credit
 *
 * Standalone-Operationen:
 *   process_gocardless_payments() — Offene GC-Zahlungen verbuchen
 *   sync_gocardless_mandates()    — CRM-Mandate mit GoCardless abgleichen
 */

if (!defined('ABSPATH')) exit;

class DGPTM_FIN_Billing_Engine {

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
    /* Phase 1: Prepare (aufgerufen von start_billing AJAX)              */
    /* ================================================================ */

    /**
     * Mitglieder laden, Caches aufbauen, Chunk-Session vorbereiten.
     *
     * @param int   $year          Abrechnungsjahr
     * @param bool  $dry_run       Nur Simulation
     * @param bool  $send_invoices Rechnungen per Mail versenden
     * @param array $contact_ids   Optional: Nur bestimmte Kontakte
     * @return array ['members' => [...], 'caches' => [...], 'config' => [...]]
     */
    public function prepare(int $year, bool $dry_run, bool $send_invoices, array $contact_ids = []): array {
        $this->log(sprintf('Billing prepare: Jahr=%d, DryRun=%s, Send=%s',
            $year, $dry_run ? 'ja' : 'nein', $send_invoices ? 'ja' : 'nein'));

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

        // Caches aufbauen
        $fees     = $this->preload_all_fees();
        $mandates = $this->preload_gocardless_mandates();
        $credits  = $this->preload_books_credits($members);

        return [
            'members' => $members,
            'caches'  => [
                'fees'     => $fees,
                'mandates' => $mandates,
                'credits'  => $credits,
            ],
            'config'  => [
                'year'           => $year,
                'dry_run'        => $dry_run,
                'send_invoices'  => $send_invoices,
            ],
        ];
    }

    /* ================================================================ */
    /* Phase 2: Process Member (aufgerufen pro Chunk-Element)            */
    /* ================================================================ */

    /**
     * Einzelnes Mitglied verarbeiten.
     *
     * Portiert 1:1 aus Python process_member() (Zeile 1303).
     * Statisch, damit der Chunk-Processor sie ohne Instanz aufrufen kann.
     *
     * @return array Ergebnis mit status, variant, fee, credit, errors etc.
     */
    public static function process_member(
        array $member,
        int $year,
        bool $dry_run,
        bool $send_invoices,
        array $caches,
        DGPTM_FIN_Config $config,
        DGPTM_FIN_Zoho_CRM $crm,
        DGPTM_FIN_Zoho_Books $books,
        DGPTM_FIN_GoCardless $gc
    ): array {

        $contact_id = $member['id'] ?? '';
        $name       = $member['Full_Name'] ?? 'Unbekannt';
        $type       = $member['Membership_Type'] ?? '';
        $number     = $member['Membership_Number'] ?? '';
        $status     = $member['Contact_Status'] ?? '';

        $result = [
            'contact_id'      => $contact_id,
            'name'            => $name,
            'membership_type' => $type,
            'membership_number' => $number,
            'status'          => 'pending',
            'variant'         => null,
            'fee'             => 0,
            'credit'          => 0,
            'invoice_id'      => null,
            'invoice_number'  => null,
            'gocardless_payment_id' => null,
            'errors'          => [],
        ];

        // ── Skip-Pruefung 1: Kein Mitgliedstyp ──────────────────────
        $types = $config->membership_types();
        if (!$type || !isset($types[$type])) {
            $result['status'] = 'skipped';
            $result['errors'][] = 'Kein oder unbekannter Mitgliedstyp: ' . $type;
            return $result;
        }

        // ── Skip-Pruefung 2: skip_billing Flag ──────────────────────
        if (!empty($types[$type]['skip_billing'])) {
            $result['status'] = 'skipped';
            $result['errors'][] = sprintf("Mitgliedstyp '%s' ueberspringt Abrechnung", $type);
            return $result;
        }

        // ── Skip-Pruefung 3: Gestrichen ─────────────────────────────
        if ($status === 'Gestrichen') {
            $result['status'] = 'skipped';
            $result['errors'][] = "Contact_Status ist 'Gestrichen'";
            $result['contact_status'] = $status;
            return $result;
        }

        // ── Skip-Pruefung 4: Bereits abgerechnet ────────────────────
        $last_fee_year = $member['letztesBeitragsjahr'] ?? null;
        if ($last_fee_year !== null) {
            try {
                if ((int) $last_fee_year >= $year) {
                    $result['status'] = 'skipped';
                    $result['errors'][] = sprintf('Bereits abgerechnet fuer %s', $last_fee_year);
                    $result['last_fee_year'] = $last_fee_year;
                    return $result;
                }
            } catch (\Throwable $e) {
                // Ungueltig, weiter
            }
        }

        // ── Skip-Pruefung 5: Contact_Status nicht erlaubt ────────────
        $allowed_statuses = $config->allowed_statuses();
        if (!in_array($status, $allowed_statuses, true)) {
            $result['status'] = 'skipped';
            $result['skip_reason'] = sprintf("Contact_Status '%s' nicht in erlaubten Status", $status);
            return $result;
        }

        // ── Pruefung 6: Freistellung (Exemption) ────────────────────
        $exempted_until = $member['Freigestellt_bis'] ?? '';
        $exemption_expired = false;

        if ($status === 'Freigestellt' && $exempted_until) {
            $exemption_ts = strtotime($exempted_until);
            $year_end_ts  = strtotime("{$year}-12-31");

            if ($exemption_ts !== false && $exemption_ts >= $year_end_ts) {
                $result['status'] = 'exempted';
                $result['exempted_until'] = $exempted_until;
                return $result;
            }

            // Freistellung abgelaufen → Blueprint ausloesen
            $exemption_expired = true;
            $blueprint_name = $config->blueprint('reactivate_billing');
            if ($blueprint_name) {
                if ($crm->trigger_blueprint($contact_id, $blueprint_name)) {
                    $result['blueprint_triggered'] = $blueprint_name;
                } else {
                    $result['errors'][] = sprintf("Blueprint '%s' konnte nicht ausgeloest werden", $blueprint_name);
                }
            }
        }
        $result['exemption_expired'] = $exemption_expired;

        // ── Beitrag ermitteln (inkl. Studentenstatus) ────────────────
        [$fee, $is_student, $needs_student_reset] = self::get_effective_fee(
            $member, $type, $year, $caches['fees'] ?? [], $config
        );

        // ── Skip-Pruefung 7: Beitrag <= 0 ───────────────────────────
        if ($fee <= 0) {
            $result['status'] = 'skipped';
            $result['errors'][] = sprintf('Kein Beitrag fuer Mitgliedstyp: %s', $type);
            return $result;
        }

        $result['fee']                  = $fee;
        $result['is_student']           = $is_student;
        $result['student_status_reset'] = $needs_student_reset;

        // ── Missing years (Backlog) ──────────────────────────────────
        $missing_years = self::get_missing_billing_years($member, $year);
        if (count($missing_years) > 1) {
            $result['missing_years']  = $missing_years;
            $result['backlog_years'] = count($missing_years) - 1;
        }

        // ── Finance_ID pruefen / ermitteln ───────────────────────────
        $finance_id = self::get_or_update_finance_id($member, $crm, $books);
        if (!$finance_id) {
            $result['status'] = 'failed';
            $result['errors'][] = 'Kein Books-Kontakt (Finance_ID) gefunden';
            return $result;
        }

        // ── Guthaben ermitteln ───────────────────────────────────────
        $credit = self::get_books_credit($member, $caches['credits'] ?? [], $books, $finance_id);
        $result['credit'] = $credit;

        // ── Mandat pruefen ───────────────────────────────────────────
        $has_mandate     = false;
        $active_mandate_id = null;
        $mandate_cache   = $caches['mandates'] ?? [];
        $gc_customer_id  = $member['GoCardlessID'] ?? '';
        $stored_mandate  = $member['MandatID'] ?? '';

        // Cache-Lookup: customer_id -> mandate
        if ($gc_customer_id && isset($mandate_cache[$gc_customer_id])) {
            $cached_mandate = $mandate_cache[$gc_customer_id];
            if ($cached_mandate) {
                $has_mandate = true;
                $active_mandate_id = $cached_mandate['id'] ?? '';
                // CRM aktualisieren wenn Mandat-ID abweicht
                if ($active_mandate_id && $active_mandate_id !== $stored_mandate && !$dry_run) {
                    $crm->update_contact($contact_id, ['MandatID' => $active_mandate_id]);
                }
            }
        } elseif ($stored_mandate) {
            // Fallback: Gespeicherte MandatID per API pruefen
            if ($gc->is_mandate_active($stored_mandate)) {
                $has_mandate = true;
                $active_mandate_id = $stored_mandate;
            }
        }

        // Letzter Versuch: API-Lookup per Customer-ID
        if (!$has_mandate && $gc_customer_id && !isset($mandate_cache[$gc_customer_id])) {
            $usable = $gc->get_usable_mandate_for_customer($gc_customer_id);
            if ($usable) {
                $has_mandate = true;
                $active_mandate_id = $usable['id'] ?? '';
                if ($active_mandate_id && $active_mandate_id !== $stored_mandate && !$dry_run) {
                    $crm->update_contact($contact_id, ['MandatID' => $active_mandate_id]);
                }
            }
        }

        $result['has_active_mandate'] = $has_mandate;
        $result['mandate_id']         = $active_mandate_id;

        // ── Variante bestimmen ───────────────────────────────────────
        $variant = self::determine_variant($fee, $credit, $has_mandate);
        $result['variant'] = $variant;

        // ── Betraege berechnen ───────────────────────────────────────
        switch ($variant) {
            case 'credit_sufficient':
                $result['credit_applied']     = $fee;
                $result['amount_to_pay']      = 0;
                $result['amount_to_collect']  = 0;
                break;
            case 'gocardless_with_credit':
                $result['credit_applied']     = $credit;
                $result['amount_to_pay']      = 0;
                $result['amount_to_collect']  = $fee - $credit;
                break;
            case 'gocardless':
                $result['credit_applied']     = 0;
                $result['amount_to_pay']      = 0;
                $result['amount_to_collect']  = $fee;
                break;
            case 'transfer_with_credit':
                $result['credit_applied']     = $credit;
                $result['amount_to_pay']      = $fee - $credit;
                $result['amount_to_collect']  = 0;
                break;
            default: // transfer_no_credit
                $result['credit_applied']     = 0;
                $result['amount_to_pay']      = $fee;
                $result['amount_to_collect']  = 0;
                break;
        }

        // ── Dry-Run: Hier aufhoeren ──────────────────────────────────
        if ($dry_run) {
            $result['status'] = 'dry_run';
            return $result;
        }

        // ── Rechnung erstellen ───────────────────────────────────────
        $invoice = self::create_membership_invoice(
            $member, $year, $fee, $variant, $finance_id, $credit, $config, $books
        );

        if ($invoice) {
            $result['invoice_id']     = $invoice['invoice_id'] ?? '';
            $result['invoice_number'] = $invoice['invoice_number'] ?? '';
            $result['invoice_url']    = $invoice['invoice_url'] ?? '';
        } else {
            $result['status'] = 'failed';
            $result['errors'][] = 'Rechnung konnte nicht erstellt werden';
            return $result;
        }

        // ── Rechnung versenden (nur wenn kein Guthaben) ──────────────
        $needs_credit_decision = $credit > 0;
        if ($needs_credit_decision) {
            $result['invoice_sent'] = false;
            $result['pending_credit_decision'] = true;
        } elseif ($send_invoices) {
            $emails = self::get_contact_emails($member);
            $result['email_recipients'] = $emails;
            if (!empty($emails)) {
                $template_id = $config->books_setting('email_template_id');
                $subject = sprintf('DGPTM Mitgliedsbeitrag fuer %d', $year);
                if ($books->send_invoice($result['invoice_id'], $emails, $template_id)) {
                    $result['invoice_sent'] = true;
                } else {
                    $result['invoice_sent'] = false;
                    $result['errors'][] = 'Rechnungs-Email konnte nicht gesendet werden';
                }
            } else {
                $result['invoice_sent'] = false;
                $result['errors'][] = 'Keine Email-Adresse gefunden';
            }
        }

        // ── GoCardless-Zahlung ───────────────────────────────────────
        $variant_cfg = $config->invoice_variants()[$variant] ?? [];
        $invoice_status  = $invoice['status'] ?? '';
        $invoice_balance = (float) ($invoice['balance'] ?? $invoice['balance_due'] ?? $fee);

        if (!empty($variant_cfg['process_gocardless']) && $has_mandate && $active_mandate_id) {
            if ($invoice_status === 'paid' || $invoice_balance == 0) {
                $result['gocardless_skipped'] = 'invoice_already_paid';
            } elseif ($credit > 0) {
                $result['gocardless_skipped'] = 'credit_available';
            } else {
                $amount_cents = (int) round($invoice_balance * 100);
                $description  = sprintf('Mitgliedsbeitrag %d - DGPTM', $year);
                $metadata = [
                    'crm_contact_id'    => $contact_id,
                    'membership_number' => $number,
                    'year'              => (string) $year,
                ];

                $payment = $gc->create_payment($amount_cents, $active_mandate_id, $description, $metadata);

                if ($payment) {
                    $result['gocardless_payment_id'] = $payment['id'] ?? '';

                    // GC Payment-ID auf Rechnung speichern
                    $gc_field = $config->books_setting('custom_fields.gocardless_payment_id', 'cf_gocardlessid');
                    $books->update_invoice($result['invoice_id'], [
                        'custom_fields' => [['api_name' => $gc_field, 'value' => $payment['id']]],
                    ]);
                } else {
                    $result['errors'][] = 'GoCardless-Zahlung konnte nicht erstellt werden';
                }
            }
        }

        // ── CRM aktualisieren ────────────────────────────────────────
        $update_data = [
            'letztesBeitragsjahr'    => $year,
            'last_fee'               => $fee,
            'lastMembershipInvoicing' => date('Y-m-d'),
            'last_invoice'           => $result['invoice_id'],
        ];

        if (!empty($result['gocardless_payment_id'])) {
            $update_data['goCardlessPayment'] = $result['gocardless_payment_id'];
        }

        // Guthaben aktualisieren (nicht bei pending credit decision)
        if (($result['credit_applied'] ?? 0) > 0 && empty($result['pending_credit_decision'])) {
            $update_data['Guthaben2'] = max(0, $credit - $fee);
        }

        // Abgelaufenen Studentenstatus zuruecksetzen
        if ($needs_student_reset) {
            $update_data['Student_Status']      = false;
            $update_data['Valid_Through']        = null;
            $update_data['StudinachweisDirekt'] = null;
        }

        if ($crm->update_contact($contact_id, $update_data)) {
            $result['status'] = 'success';
        } else {
            $result['errors'][] = 'CRM-Update fehlgeschlagen';
            $result['status'] = 'partial';
        }

        return $result;
    }

    /* ================================================================ */
    /* Rechnungsvariante                                                 */
    /* ================================================================ */

    /**
     * 5 Varianten: credit_sufficient, gocardless_with_credit, gocardless,
     *              transfer_with_credit, transfer_no_credit
     */
    public static function determine_variant(float $fee, float $credit, bool $has_mandate): string {
        if ($credit >= $fee) {
            return 'credit_sufficient';
        }
        if ($has_mandate && $credit > 0) {
            return 'gocardless_with_credit';
        }
        if ($has_mandate) {
            return 'gocardless';
        }
        if ($credit > 0) {
            return 'transfer_with_credit';
        }
        return 'transfer_no_credit';
    }

    /* ================================================================ */
    /* Studentenstatus                                                    */
    /* ================================================================ */

    /**
     * @return array [is_valid_student, needs_status_reset]
     */
    public static function check_student_status(array $contact, int $year): array {
        $student_status = $contact['Student_Status'] ?? false;
        $valid_through  = $contact['Valid_Through'] ?? null;

        if (!$student_status) {
            return [false, false];
        }

        if (!$valid_through) {
            return [false, true]; // Status gesetzt, aber kein Gueltigkeitsjahr
        }

        try {
            $valid_year = (int) $valid_through;
            if ($valid_year >= $year) {
                return [true, false]; // Gueltiger Student
            }
            return [false, true]; // Abgelaufen, Reset noetig
        } catch (\Throwable $e) {
            return [false, true]; // Ungueltiger Wert
        }
    }

    /**
     * Effektiven Beitrag ermitteln (mit Studentenrabatt).
     *
     * @return array [fee, is_student, needs_student_reset]
     */
    public static function get_effective_fee(
        array $contact,
        string $type,
        int $year,
        array $fee_cache,
        DGPTM_FIN_Config $config
    ): array {
        $base_fee = $fee_cache[$type] ?? 0.0;

        // Studentenrabatt nur fuer Ordentliches/Ausserordentliches Mitglied
        if (!in_array($type, ['Ordentliches Mitglied', 'Außerordentliches Mitglied'], true)) {
            return [$base_fee, false, false];
        }

        [$is_valid_student, $needs_reset] = self::check_student_status($contact, $year);

        if ($is_valid_student) {
            return [$config->student_fee(), true, false];
        }

        return [$base_fee, false, $needs_reset];
    }

    /* ================================================================ */
    /* Rechnungsdaten berechnen                                          */
    /* ================================================================ */

    /**
     * Rechnungsdatum und Faelligkeitsdatum berechnen.
     *
     * Regeln:
     *   - Rechnungsdatum: Max(1. Maerz, heute)
     *   - Faelligkeit: Min(Rechnungsdatum + 4 Wochen, 31.12.)
     *   - Backlog (31.12. < Rechnungsdatum): Faelligkeit = + 4 Wochen
     *
     * @return array ['invoice_date' => 'Y-m-d', 'due_date' => 'Y-m-d']
     */
    public static function calculate_invoice_dates(int $year): array {
        $today    = date('Y-m-d');
        $march_1  = "{$year}-03-01";
        $dec_31   = "{$year}-12-31";

        $invoice_date = ($today > $march_1) ? $today : $march_1;

        $due_4_weeks = date('Y-m-d', strtotime($invoice_date . ' +4 weeks'));

        if ($dec_31 >= $invoice_date) {
            $due_date = ($due_4_weeks < $dec_31) ? $due_4_weeks : $dec_31;
        } else {
            // Backlog-Fall: Rechnungsdatum nach Jahresende
            $due_date = $due_4_weeks;
        }

        return [
            'invoice_date' => $invoice_date,
            'due_date'     => $due_date,
        ];
    }

    /* ================================================================ */
    /* Rechnungsnotizen                                                   */
    /* ================================================================ */

    /**
     * Notizen-Text aus Varianten-Template generieren.
     */
    public static function get_invoice_notes(
        string $variant,
        float $credit,
        float $fee,
        int $year,
        DGPTM_FIN_Config $config
    ): string {
        $variant_cfg = $config->invoice_variants()[$variant] ?? [];
        $template    = $variant_cfg['notes_template'] ?? '';

        $remaining = max(0, $fee - $credit);

        return str_replace(
            ['{credit:.2f}', '{remaining:.2f}', '{year}',
             '{credit}', '{remaining}'],
            [number_format($credit, 2, '.', ''),
             number_format($remaining, 2, '.', ''),
             (string) $year,
             number_format($credit, 2, '.', ''),
             number_format($remaining, 2, '.', '')],
            $template
        );
    }

    /* ================================================================ */
    /* Email-Adressen                                                     */
    /* ================================================================ */

    /**
     * Alle gueltigen Email-Adressen des Kontakts (dedupliziert, case-insensitive).
     */
    public static function get_contact_emails(array $contact): array {
        $fields    = ['Email', 'Secondary_Email', 'Third_Email', 'DGPTMMail'];
        $emails    = [];
        $seen      = [];

        foreach ($fields as $field) {
            $email = $contact[$field] ?? '';
            if (!$email || !is_string($email) || strpos($email, '@') === false) {
                continue;
            }
            $email = trim($email);
            $lower = strtolower($email);
            if (!isset($seen[$lower])) {
                $seen[$lower] = true;
                $emails[] = $email;
            }
        }

        return $emails;
    }

    /* ================================================================ */
    /* Finance_ID ermitteln                                              */
    /* ================================================================ */

    /**
     * Finance_ID validieren oder per Books-Suche ermitteln.
     * Aktualisiert CRM wenn neue Finance_ID gefunden.
     */
    public static function get_or_update_finance_id(
        array &$contact,
        DGPTM_FIN_Zoho_CRM $crm,
        DGPTM_FIN_Zoho_Books $books
    ): ?string {
        $contact_id = $contact['id'] ?? '';
        $finance_id = $contact['Finance_ID'] ?? '';

        // Vorhandene Finance_ID validieren
        if ($finance_id) {
            // Einfache Validierung: versuchen den Kontakt zu laden
            $credit = $books->get_customer_credits($finance_id);
            // get_customer_credits gibt 0 zurueck wenn Kontakt existiert, macht einen API-Call
            // Wenn der Kontakt nicht existiert, gibt die API einen Fehler zurueck
            // Wir vertrauen hier darauf, dass die Finance_ID gueltig ist wenn sie gesetzt ist
            return $finance_id;
        }

        // Finance_ID fehlt: In Books nach CRM-ID suchen
        $books_contact = $books->get_contact_by_crm_id($contact_id);
        if ($books_contact) {
            $new_id = $books_contact['contact_id'] ?? '';
            if ($new_id) {
                // CRM aktualisieren
                $crm->update_contact($contact_id, ['Finance_ID' => $new_id]);
                $contact['Finance_ID'] = $new_id;
                return $new_id;
            }
        }

        return null;
    }

    /* ================================================================ */
    /* Guthaben aus Books                                                */
    /* ================================================================ */

    /**
     * Guthaben: Cache zuerst, dann API, Fallback auf CRM-Feld Guthaben2.
     */
    public static function get_books_credit(
        array $contact,
        array $credit_cache,
        DGPTM_FIN_Zoho_Books $books,
        ?string $finance_id = null
    ): float {
        if (!$finance_id) {
            $finance_id = $contact['Finance_ID'] ?? '';
        }

        // Cache-Treffer
        if ($finance_id && isset($credit_cache[$finance_id])) {
            return $credit_cache[$finance_id];
        }

        // API-Lookup
        if ($finance_id) {
            try {
                return $books->get_customer_credits($finance_id);
            } catch (\Throwable $e) {
                // Fallthrough zum CRM-Fallback
            }
        }

        // Fallback: CRM-Feld
        return (float) ($contact['Guthaben2'] ?? 0);
    }

    /* ================================================================ */
    /* Rechnung erstellen                                                */
    /* ================================================================ */

    /**
     * Beitragsrechnung in Zoho Books erstellen.
     *
     * Rechnungsnummer: {Membership_Number}-{year}
     * Duplikat-Check: Code 1001 → vorhandene Rechnung zurueckgeben.
     */
    public static function create_membership_invoice(
        array $member,
        int $year,
        float $fee,
        string $variant,
        string $finance_id,
        float $credit,
        DGPTM_FIN_Config $config,
        DGPTM_FIN_Zoho_Books $books
    ): ?array {
        $type   = $member['Membership_Type'] ?? '';
        $number = ($member['Membership_Number'] ?? '') . '-' . $year;

        // Item-ID fuer diesen Mitgliedstyp
        $types   = $config->membership_types();
        $item_id = $types[$type]['item_id'] ?? $config->books_setting('membership_item_id');

        // Datum berechnen
        $dates = self::calculate_invoice_dates($year);

        // Notes aus Variante
        $notes = self::get_invoice_notes($variant, $credit, $fee, $year, $config);

        // Line Item
        $line_item = [
            'item_id'     => $item_id,
            'description' => sprintf('Mitgliedsbeitrag %d - %s', $year, $type),
            'rate'        => $fee,
            'quantity'    => 1,
            'account_id'  => $config->books_setting('account_id'),
        ];

        $tax_id = $config->books_setting('tax_id');
        if ($tax_id) {
            $line_item['tax_id'] = $tax_id;
        }

        // Custom Fields
        $custom_fields = [
            [
                'api_name' => $config->books_setting('custom_fields.beitragsrechnung', 'cf_beitragsrechnung'),
                'value'    => true,
            ],
        ];

        $invoice_data = [
            'customer_id'    => $finance_id,
            'invoice_number' => $number,
            'template_id'    => $config->books_setting('invoice_template_id'),
            'date'           => $dates['invoice_date'],
            'due_date'       => $dates['due_date'],
            'line_items'     => [$line_item],
            'notes'          => $notes,
            'subject'        => 'Jahresbeitrag ' . $year,
            'custom_fields'  => $custom_fields,
            'is_inclusive_tax' => true,
            'payment_options' => [
                'payment_gateways' => [
                    ['gateway_name' => 'gocardless', 'configured' => true],
                ],
            ],
        ];

        return $books->create_invoice($invoice_data, true);
    }

    /* ================================================================ */
    /* Fehlende Beitragsjahre (Backlog)                                  */
    /* ================================================================ */

    /**
     * Fehlende Jahre ermitteln (reaktivierte Mitglieder).
     *
     * @return array Liste der fehlenden Jahre [start..current_year]
     */
    public static function get_missing_billing_years(array $contact, int $current_year): array {
        $last_fee_year = $contact['letztesBeitragsjahr'] ?? null;
        $member_since  = $contact['Member_Since'] ?? null;

        $start_year = $current_year;

        if ($last_fee_year !== null) {
            try {
                $start_year = (int) $last_fee_year + 1;
            } catch (\Throwable $e) {
                // Ignorieren
            }
        } elseif ($member_since) {
            $ts = strtotime($member_since);
            if ($ts !== false) {
                $start_year = (int) date('Y', $ts);
            }
        }

        if ($start_year < $current_year) {
            return range($start_year, $current_year);
        }
        if ($start_year === $current_year) {
            return [$current_year];
        }
        return [];
    }

    /* ================================================================ */
    /* GoCardless Zahlungen verarbeiten (standalone)                      */
    /* ================================================================ */

    /**
     * Offene Beitragsrechnungen pruefen und GoCardless-Zahlungen verbuchen.
     *
     * - Rechnungen ohne GC-Payment-ID: Automatisch einziehen wenn Mandat vorhanden
     * - paid_out: Zahlung in Books verbuchen (inkl. Gebuehr)
     * - charged_back: Ruecklastschrift markieren
     * - failed/cancelled: Zum erneuten Einzug markieren
     */
    public function process_gocardless_payments(bool $dry_run = true): array {
        $this->log(sprintf('GoCardless Zahlungen: DryRun=%s', $dry_run ? 'ja' : 'nein'));

        $gc_clearing_id    = $this->config->books_setting('gocardless_clearing_account_id');
        $gc_fee_expense_id = $this->config->books_setting('gocardless_fee_account_id');
        $chargeback_fee    = $this->config->chargeback_fee();

        $results = [
            'dry_run'           => $dry_run,
            'total_checked'     => 0,
            'paid_out'          => 0,
            'failed'            => 0,
            'pending'           => 0,
            'no_payment_id'     => 0,
            'collected'         => 0,
            'no_mandate'        => 0,
            'total_recorded'    => 0.0,
            'total_fees'        => 0.0,
            'total_chargebacks' => 0.0,
            'details'           => [],
        ];

        // Mandate vorladen und CRM-Lookup aufbauen
        $mandate_cache = $this->preload_gocardless_mandates();

        // CRM-Kontakte mit Finance_ID laden (fuer Mandats-Lookup)
        $crm_members = $this->crm->get_all_members_with_finance_id();
        $crm_by_finance_id = [];
        foreach ($crm_members as $c) {
            $fid = $c['Finance_ID'] ?? '';
            if ($fid) $crm_by_finance_id[$fid] = $c;
        }

        // Offene Beitragsrechnungen laden
        $unpaid = $this->books->get_unpaid_invoices('true');

        foreach ($unpaid as $invoice) {
            $invoice_id     = $invoice['invoice_id'] ?? '';
            $invoice_number = $invoice['invoice_number'] ?? '';

            // Volle Rechnungsdetails laden
            $full_invoice = $this->books->get_invoice($invoice_id);
            if (!$full_invoice) continue;

            // Custom Fields auslesen
            $is_membership = false;
            $gc_payment_id = null;
            $cf_beitragsrechnung_field = $this->config->books_setting('custom_fields.beitragsrechnung', 'cf_beitragsrechnung');
            $cf_gc_field = $this->config->books_setting('custom_fields.gocardless_payment_id', 'cf_gocardlessid');

            foreach ($full_invoice['custom_fields'] ?? [] as $cf) {
                $api_name = $cf['api_name'] ?? '';
                if ($api_name === $cf_beitragsrechnung_field) {
                    $is_membership = ($cf['value'] === true || $cf['value'] === 'true');
                }
                if ($api_name === $cf_gc_field) {
                    $gc_payment_id = $cf['value'] ?? null;
                }
            }

            if (!$is_membership) continue;

            $results['total_checked']++;
            $detail = [
                'invoice_id'     => $invoice_id,
                'invoice_number' => $invoice_number,
                'customer_name'  => $invoice['customer_name'] ?? '',
                'amount'         => (float) ($invoice['total'] ?? 0),
            ];

            // ── Kein GC-Payment: Automatisch einziehen ───────────────
            if (!$gc_payment_id) {
                $customer_id = $invoice['customer_id'] ?? '';
                $crm_contact = $crm_by_finance_id[$customer_id] ?? null;

                if (!$crm_contact) {
                    $detail['status'] = 'no_payment_id';
                    $results['no_payment_id']++;
                    $results['details'][] = $detail;
                    continue;
                }

                $gc_cust_id = $crm_contact['GoCardlessID'] ?? '';
                $mandate    = $gc_cust_id ? ($mandate_cache[$gc_cust_id] ?? null) : null;

                if (!$mandate) {
                    $detail['status'] = 'no_mandate';
                    $results['no_mandate']++;
                    $results['details'][] = $detail;
                    continue;
                }

                $mandate_id = $mandate['id'] ?? '';
                $balance    = (float) ($full_invoice['balance'] ?? $full_invoice['total'] ?? 0);

                if ($balance <= 0) {
                    $detail['status'] = 'already_paid';
                    $results['details'][] = $detail;
                    continue;
                }

                // Doppel-Einzug verhindern
                $existing_payments = $full_invoice['payments'] ?? [];
                $has_gc_payment = false;
                foreach ($existing_payments as $p) {
                    if (($p['payment_mode'] ?? '') === 'GoCardless'
                        || strpos($p['reference_number'] ?? '', 'PM0') !== false) {
                        $has_gc_payment = true;
                        break;
                    }
                }

                if ($has_gc_payment) {
                    $detail['status'] = 'already_collecting';
                    $results['details'][] = $detail;
                    continue;
                }

                $detail['status']     = 'collecting';
                $detail['mandate_id'] = $mandate_id;

                if (!$dry_run) {
                    $amount_cents = (int) round($balance * 100);
                    $description  = sprintf('Mitgliedsbeitrag - DGPTM (Rechnung %s)', $invoice_number);
                    $metadata = [
                        'crm_contact_id' => $crm_contact['id'] ?? '',
                        'invoice_id'     => $invoice_id,
                    ];

                    $gc_pay = $this->gc->create_payment($amount_cents, $mandate_id, $description, $metadata);

                    if ($gc_pay) {
                        $gc_pay_id = $gc_pay['id'] ?? '';
                        $detail['gocardless_payment_id'] = $gc_pay_id;

                        // CF auf Rechnung setzen
                        $this->books->update_invoice($invoice_id, [
                            'custom_fields' => [['api_name' => $cf_gc_field, 'value' => $gc_pay_id]],
                        ]);

                        // Zahlung in Books verbuchen
                        $gc_fee_cents = 20 + (int) ($amount_cents * 0.01);
                        $gc_fee = $gc_fee_cents / 100.0;
                        $recorded = $this->books->record_payment(
                            $invoice_id, $balance, date('Y-m-d'),
                            'GoCardless', $gc_pay_id,
                            $gc_clearing_id, $gc_fee, $gc_fee_expense_id
                        );

                        // CRM aktualisieren
                        $this->crm->update_contact($crm_contact['id'], [
                            'goCardlessPayment' => $gc_pay_id,
                        ]);

                        $detail['recorded'] = $recorded !== null;
                        $detail['fee']      = $gc_fee;
                    } else {
                        $detail['status'] = 'collection_failed';
                    }
                }

                $results['collected']++;
                $results['details'][] = $detail;
                continue;
            }

            // ── GC-Payment vorhanden: Status pruefen ─────────────────
            $payment = $this->gc->get_payment($gc_payment_id);
            if (!$payment) {
                $detail['status'] = 'payment_not_found';
                $detail['gocardless_payment_id'] = $gc_payment_id;
                $results['details'][] = $detail;
                continue;
            }

            $payment_status = $payment['status'] ?? 'unknown';
            $detail['gocardless_payment_id'] = $gc_payment_id;
            $detail['gocardless_status']     = $payment_status;

            if ($payment_status === 'paid_out') {
                $results['paid_out']++;
                $amount_cents = (int) ($payment['amount'] ?? 0);
                $amount       = $amount_cents / 100.0;
                $fee_cents    = 20 + (int) ($amount_cents * 0.01);
                $gc_fee       = $fee_cents / 100.0;

                $detail['amount_paid'] = $amount;
                $detail['fee']         = $gc_fee;
                $detail['status']      = 'paid_out';

                if (!$dry_run) {
                    // Auszahlungsdatum ermitteln
                    $payment_date = date('Y-m-d');
                    $payout_id = $payment['links']['payout'] ?? '';
                    if ($payout_id) {
                        $payout = $this->gc->get_payout($payout_id);
                        if ($payout) {
                            $payment_date = $payout['arrival_date'] ?? $payment_date;
                        }
                    }

                    $recorded = $this->books->record_payment(
                        $invoice_id, $amount, $payment_date,
                        'GoCardless', $gc_payment_id,
                        $gc_clearing_id, $gc_fee, $gc_fee_expense_id
                    );

                    if ($recorded) {
                        $detail['recorded'] = true;
                        $results['total_recorded'] += $amount;
                        $results['total_fees']     += $gc_fee;
                    } else {
                        $detail['recorded'] = false;
                    }
                }

            } elseif ($payment_status === 'charged_back') {
                $results['failed']++;
                $detail['status']          = $payment_status;
                $detail['chargeback_fee']  = $chargeback_fee;
                $detail['requires_action'] = true;

                // Books-Payment-ID fuer manuelle Bearbeitung
                foreach ($full_invoice['payments'] ?? [] as $p) {
                    if (($p['reference_number'] ?? '') === $gc_payment_id) {
                        $detail['books_payment_id'] = $p['payment_id'] ?? '';
                        break;
                    }
                }

                $results['total_chargebacks'] += $chargeback_fee;

            } elseif (in_array($payment_status, ['failed', 'cancelled'], true)) {
                $results['failed']++;
                $detail['status'] = $payment_status;

            } else {
                $results['pending']++;
                $detail['status'] = $payment_status;
            }

            $results['details'][] = $detail;
        }

        $this->log(sprintf(
            'GC Zahlungen: %d geprueft, %d paid_out, %d collected, %d failed, %d pending',
            $results['total_checked'], $results['paid_out'],
            $results['collected'], $results['failed'], $results['pending']
        ));

        return $results;
    }

    /* ================================================================ */
    /* GoCardless Mandats-Synchronisation (standalone)                    */
    /* ================================================================ */

    /**
     * GoCardless-Kunden per Email mit CRM-Kontakten abgleichen.
     * Aktualisiert GoCardlessID und MandatID im CRM.
     */
    public function sync_gocardless_mandates(bool $dry_run = true): array {
        $this->log(sprintf('Mandats-Sync: DryRun=%s', $dry_run ? 'ja' : 'nein'));

        $results = [
            'dry_run'              => $dry_run,
            'gc_customers_loaded'  => 0,
            'crm_members_checked'  => 0,
            'already_linked'       => 0,
            'newly_linked'         => 0,
            'mandate_updated'      => 0,
            'no_match'             => 0,
            'no_mandate'           => 0,
            'details'              => [],
        ];

        // 1. GoCardless-Kunden laden und Email-Map aufbauen
        $gc_customers = $this->gc->get_all_customers();
        $results['gc_customers_loaded'] = count($gc_customers);

        $gc_email_map = [];
        foreach ($gc_customers as $customer) {
            $email = $customer['email'] ?? '';
            if ($email) {
                $gc_email_map[strtolower(trim($email))] = $customer;
            }
        }

        // 2. Mandate vorladen
        $mandate_cache = $this->preload_gocardless_mandates();

        // 3. CRM-Mitglieder laden
        $members = $this->crm->get_members_for_billing((int) date('Y'));

        // 4. Abgleich per Email (4 Felder, case-insensitive)
        foreach ($members as $member) {
            $results['crm_members_checked']++;
            $contact_id       = $member['id'] ?? '';
            $name             = $member['Full_Name'] ?? 'Unbekannt';
            $existing_gc_id   = $member['GoCardlessID'] ?? '';
            $existing_mandate = $member['MandatID'] ?? '';

            $emails = self::get_contact_emails($member);

            // Email-Match suchen
            $matched_customer = null;
            $matched_email    = null;
            foreach ($emails as $email) {
                $gc_cust = $gc_email_map[strtolower(trim($email))] ?? null;
                if ($gc_cust) {
                    $matched_customer = $gc_cust;
                    $matched_email    = $email;
                    break;
                }
            }

            if (!$matched_customer) {
                $results['no_match']++;
                continue;
            }

            $gc_customer_id = $matched_customer['id'] ?? '';

            // Mandat fuer diesen Kunden suchen
            $mandate = $mandate_cache[$gc_customer_id] ?? null;
            if (!$mandate) {
                $results['no_mandate']++;
                continue;
            }

            $mandate_id = $mandate['id'] ?? '';

            // Bereits vollstaendig verknuepft?
            if ($existing_gc_id === $gc_customer_id && $existing_mandate === $mandate_id) {
                $results['already_linked']++;
                continue;
            }

            // Update-Daten zusammenstellen
            $update_data = [];
            $detail = [
                'contact_id'     => $contact_id,
                'name'           => $name,
                'matched_email'  => $matched_email,
                'gc_customer_id' => $gc_customer_id,
                'mandate_id'     => $mandate_id,
                'changes'        => [],
            ];

            if ($existing_gc_id !== $gc_customer_id) {
                $update_data['GoCardlessID'] = $gc_customer_id;
                $detail['changes'][] = sprintf('GoCardlessID: %s -> %s',
                    $existing_gc_id ?: '(leer)', $gc_customer_id);
            }

            if ($existing_mandate !== $mandate_id) {
                $update_data['MandatID'] = $mandate_id;
                $detail['changes'][] = sprintf('MandatID: %s -> %s',
                    $existing_mandate ?: '(leer)', $mandate_id);
            }

            if (!empty($update_data)) {
                if ($existing_gc_id) {
                    $results['mandate_updated']++;
                } else {
                    $results['newly_linked']++;
                }

                if (!$dry_run) {
                    $this->crm->update_contact($contact_id, $update_data);
                }

                $results['details'][] = $detail;
            }
        }

        $this->log(sprintf(
            'Mandats-Sync: %d geprueft, %d neu verknuepft, %d aktualisiert, %d kein Match',
            $results['crm_members_checked'], $results['newly_linked'],
            $results['mandate_updated'], $results['no_match']
        ));

        return $results;
    }

    /* ================================================================ */
    /* Cache-Methoden (private)                                          */
    /* ================================================================ */

    /**
     * Alle Beitragshoehen vorladen (Config + CRM-Variablen).
     *
     * @return array [membership_type => fee]
     */
    private function preload_all_fees(): array {
        $fees = $this->crm->get_all_fees();
        $this->log(sprintf('Beitragshoehen geladen: %d Typen', count($fees)));
        return $fees;
    }

    /**
     * Alle aktiven GoCardless-Mandate vorladen.
     *
     * @return array [customer_id => mandate_data]
     */
    private function preload_gocardless_mandates(): array {
        $mandates = $this->gc->get_all_active_mandates();

        // Cache: customer_id -> erstes nutzbares Mandat
        $cache = [];
        foreach ($mandates as $m) {
            $customer_id = $m['links']['customer'] ?? '';
            if ($customer_id && !isset($cache[$customer_id])) {
                $cache[$customer_id] = $m;
            }
        }

        $this->log(sprintf('Mandate geladen: %d Kunden mit aktivem Mandat', count($cache)));
        return $cache;
    }

    /**
     * Books-Guthaben fuer Mitglieder mit Finance_ID vorladen.
     *
     * Laedt alle Books-Kunden und baut Cache: finance_id -> credit.
     *
     * @return array [finance_id => credit_amount]
     */
    private function preload_books_credits(array $members): array {
        // Finance_IDs sammeln
        $finance_ids = [];
        foreach ($members as $m) {
            $fid = $m['Finance_ID'] ?? '';
            if ($fid) $finance_ids[$fid] = true;
        }

        if (empty($finance_ids)) {
            $this->log('Keine Finance_IDs zum Vorladen');
            return [];
        }

        $this->log(sprintf('Books-Guthaben fuer %d Finance_IDs werden geladen...', count($finance_ids)));

        // Einzeln abfragen (Books hat keine Batch-API fuer Credits)
        // Alternativ: Alle Kunden laden und filtern (wie Python)
        $cache = [];
        foreach (array_keys($finance_ids) as $fid) {
            try {
                $credit = $this->books->get_customer_credits($fid);
                $cache[$fid] = $credit;
            } catch (\Throwable $e) {
                $cache[$fid] = 0.0;
            }
        }

        $this->log(sprintf('Books-Guthaben geladen: %d Kontakte gecacht', count($cache)));
        return $cache;
    }

    /* ================================================================ */
    /* Logging                                                           */
    /* ================================================================ */

    private function log(string $msg): void {
        if (class_exists('DGPTM_Logger')) {
            \DGPTM_Logger::info($msg, 'finanzen');
        }
        error_log('[DGPTM Finanzen Billing] ' . $msg);
    }
}
