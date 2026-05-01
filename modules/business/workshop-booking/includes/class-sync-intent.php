<?php
/**
 * Sync_Intent — was soll am Veranstal_X_Contacts-Eintrag passieren?
 * Value Object, vom Coordinator interpretiert.
 *
 * Spec Abschnitt 4a.2.
 */
if (!defined('ABSPATH')) exit;

final class DGPTM_WSB_Sync_Intent {

    /** @var string Zoho-ID des Veranstal_X_Contacts-Eintrags. Bei Erstanlage leer. */
    public $veranstal_x_contact_id;

    /** @var string|null Ziel-Anmelde_Status (Blueprint-State). null = nicht aendern. */
    public $target_blueprint_state;

    /** @var string|null Ziel-Zahlungsstatus. null = nicht aendern. */
    public $target_payment_status;

    /** @var string Quelle des Intent (siehe Konstanten). */
    public $source;

    /** @var array Zusatzdaten (stripe_event_id, books_invoice_id, initial_fields, extra_fields, ...). */
    public $payload;

    /** @var string Menschlich lesbarer Grund (landet im sync_log). */
    public $reason;

    const SOURCE_STRIPE_WEBHOOK = 'stripe_webhook';
    const SOURCE_RECONCILIATION = 'reconciliation';
    const SOURCE_MANUAL         = 'manual';
    const SOURCE_BOOKING_INIT   = 'booking_init';

    public function __construct($contact_id, $blueprint, $payment, $source, $payload, $reason) {
        $this->veranstal_x_contact_id = (string) $contact_id;
        $this->target_blueprint_state = $blueprint;
        $this->target_payment_status  = $payment;
        $this->source                 = (string) $source;
        $this->payload                = is_array($payload) ? $payload : [];
        $this->reason                 = (string) $reason;
    }

    public function to_array() {
        return [
            'veranstal_x_contact_id' => $this->veranstal_x_contact_id,
            'target_blueprint_state' => $this->target_blueprint_state,
            'target_payment_status'  => $this->target_payment_status,
            'source'                 => $this->source,
            'payload'                => $this->payload,
            'reason'                 => $this->reason,
        ];
    }
}
