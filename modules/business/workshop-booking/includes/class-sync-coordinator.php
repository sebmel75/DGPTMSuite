<?php
/**
 * Sync_Coordinator — SINGLE ENTRY POINT fuer alle Status-Schreibzugriffe
 * auf Veranstal_X_Contacts.
 *
 * Verantwortung:
 *  - Backstage-Records (Quelle != 'Modul') werden konsequent geskippt
 *  - State-Machine prueft Blueprint-Uebergaenge
 *  - Audit-Log + Drift-Alerts werden geschrieben
 *  - Push zum Zoho CRM ueber DGPTM_WSB_Veranstal_X_Contacts
 *
 * Spec Abschnitt 4a.2 + 4a.5.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Sync_Coordinator {

    const QUELLE_MODUL     = 'Modul';
    const QUELLE_BACKSTAGE = 'Backstage';

    /**
     * Anwenden eines Sync_Intent.
     *
     * Bei Erstanlage (leerer veranstal_x_contact_id) wird die neue Zoho-ID
     * in $intent->veranstal_x_contact_id zurueckgeschrieben, damit der
     * Aufrufer sie verwenden kann.
     *
     * @param DGPTM_WSB_Sync_Intent $intent
     * @return DGPTM_WSB_Sync_Result
     */
    public static function apply_intent(DGPTM_WSB_Sync_Intent $intent) {
        $is_create = empty($intent->veranstal_x_contact_id);
        $current   = $is_create ? null : DGPTM_WSB_Veranstal_X_Contacts::fetch($intent->veranstal_x_contact_id);

        // 1) Bestandsdatensatz nicht gefunden? Bei Update-Intent ist das ein Fehler.
        if (!$is_create && $current === null) {
            $log_id = DGPTM_WSB_Sync_Log_Store::record(
                $intent, ['blueprint' => null, 'payment' => null], false,
                DGPTM_WSB_Sync_Result::ERR_CONTACT_NOT_FOUND,
                'Contact-ID nicht in Zoho gefunden'
            );
            return DGPTM_WSB_Sync_Result::fail(DGPTM_WSB_Sync_Result::ERR_CONTACT_NOT_FOUND, $log_id);
        }

        // 2) Backstage-Skip — Sync_Coordinator schreibt NIE in Eintraege mit Quelle != Modul
        if (!$is_create) {
            $quelle = self::extract_quelle($current);
            if ($quelle !== self::QUELLE_MODUL) {
                return self::handle_backstage_skip($intent, $current, $quelle);
            }
        }

        // 3) Snapshot des bisherigen Zustands (fuer Audit-Log + State-Machine)
        $previous = self::snapshot_previous($current);

        // 4) State-Machine pruefen — nur wenn Blueprint geaendert werden soll
        if ($intent->target_blueprint_state !== null) {
            $from = $previous['blueprint'];
            $to   = $intent->target_blueprint_state;
            if (!DGPTM_WSB_State_Machine::can_transition($from, $to, $intent->source)) {
                $log_id = DGPTM_WSB_Sync_Log_Store::record(
                    $intent, $previous, false,
                    DGPTM_WSB_Sync_Result::ERR_TRANSITION_FORBIDDEN,
                    sprintf('Verbotener Uebergang: %s → %s (source=%s)',
                            $from ?: '(null)', $to, $intent->source)
                );
                return DGPTM_WSB_Sync_Result::fail(DGPTM_WSB_Sync_Result::ERR_TRANSITION_FORBIDDEN, $log_id);
            }
        }

        // 5) Update-Daten zusammenstellen
        $data = self::build_update_payload($intent, $is_create);

        // 6) Push zum CRM
        $result = DGPTM_WSB_Veranstal_X_Contacts::upsert($data, $is_create ? null : $intent->veranstal_x_contact_id);
        if (!$result['success']) {
            $log_id = DGPTM_WSB_Sync_Log_Store::record(
                $intent, $previous, false,
                DGPTM_WSB_Sync_Result::ERR_ZOHO_API_ERROR,
                $result['error']
            );
            return DGPTM_WSB_Sync_Result::fail(DGPTM_WSB_Sync_Result::ERR_ZOHO_API_ERROR, $log_id);
        }

        // 7) Bei Erstanlage: ID nachtragen ins Intent (fuer Caller)
        if ($is_create && $result['id']) {
            $intent->veranstal_x_contact_id = $result['id'];
        }

        // 8) Erfolg im Audit-Log dokumentieren
        $log_id = DGPTM_WSB_Sync_Log_Store::record($intent, $previous, true);
        return DGPTM_WSB_Sync_Result::ok($log_id);
    }

    /**
     * Behandelt Skip wegen Quelle != Modul.
     *
     * Sonderfall: Stripe-Webhook auf Backstage-Record → Drift-Alert
     * (sollte normalerweise nicht passieren).
     */
    private static function handle_backstage_skip(DGPTM_WSB_Sync_Intent $intent, array $current, $quelle) {
        $log_id = DGPTM_WSB_Sync_Log_Store::record(
            $intent,
            self::snapshot_previous($current),
            true,
            DGPTM_WSB_Sync_Result::ERR_SOURCE_SKIPPED,
            'Quelle=' . ($quelle === null ? '(leer)' : $quelle)
        );

        if ($intent->source === DGPTM_WSB_Sync_Intent::SOURCE_STRIPE_WEBHOOK) {
            DGPTM_WSB_Drift_Alert_Store::open_alert(
                $intent->veranstal_x_contact_id,
                'backstage_with_stripe',
                DGPTM_WSB_Drift_Alert_Store::SEVERITY_CRITICAL,
                ['quelle' => $quelle, 'current' => self::compact_current($current)],
                ['intent' => $intent->to_array()],
                'Manuelle Pruefung: warum hat ein Backstage-Record eine Modul-Stripe-Charge?'
            );
        }
        return DGPTM_WSB_Sync_Result::skipped($log_id);
    }

    /**
     * Stellt das fuer das Update relevante Daten-Array zusammen.
     */
    private static function build_update_payload(DGPTM_WSB_Sync_Intent $intent, $is_create) {
        $data = [];

        if ($intent->target_blueprint_state !== null) {
            $data[DGPTM_WSB_Veranstal_X_Contacts::FIELD_BLUEPRINT] = $intent->target_blueprint_state;
        }
        if ($intent->target_payment_status !== null) {
            $data[DGPTM_WSB_Veranstal_X_Contacts::FIELD_PAYMENT] = $intent->target_payment_status;
        }

        // Bei Erstanlage: Quelle = Modul setzen
        if ($is_create) {
            $data[DGPTM_WSB_Veranstal_X_Contacts::FIELD_QUELLE] = self::QUELLE_MODUL;
        }

        // Last_Sync_At immer setzen
        $data[DGPTM_WSB_Veranstal_X_Contacts::FIELD_LAST_SYNC] = current_time('mysql');

        // Initial-Felder (nur Erstanlage): Contact-Lookup, Event-Lookup, Ticket-Type, Preis, ...
        if ($is_create && !empty($intent->payload['initial_fields']) && is_array($intent->payload['initial_fields'])) {
            foreach ($intent->payload['initial_fields'] as $k => $v) {
                $data[$k] = $v;
            }
        }

        // Extra-Felder (auch bei Update): Stripe_Charge_ID, Stripe_Session_ID, Books_Invoice_ID, ...
        if (!empty($intent->payload['extra_fields']) && is_array($intent->payload['extra_fields'])) {
            foreach ($intent->payload['extra_fields'] as $k => $v) {
                $data[$k] = $v;
            }
        }

        return $data;
    }

    private static function snapshot_previous($current) {
        if (!is_array($current)) {
            return ['blueprint' => null, 'payment' => null];
        }
        return [
            'blueprint' => isset($current[DGPTM_WSB_Veranstal_X_Contacts::FIELD_BLUEPRINT])
                        ? $current[DGPTM_WSB_Veranstal_X_Contacts::FIELD_BLUEPRINT] : null,
            'payment'   => isset($current[DGPTM_WSB_Veranstal_X_Contacts::FIELD_PAYMENT])
                        ? $current[DGPTM_WSB_Veranstal_X_Contacts::FIELD_PAYMENT] : null,
        ];
    }

    private static function extract_quelle($current) {
        if (!is_array($current)) return null;
        return isset($current[DGPTM_WSB_Veranstal_X_Contacts::FIELD_QUELLE])
             ? $current[DGPTM_WSB_Veranstal_X_Contacts::FIELD_QUELLE] : null;
    }

    /**
     * Reduziert den CRM-Datensatz auf relevante Felder fuer Drift-Alert-Snapshots.
     */
    private static function compact_current($current) {
        if (!is_array($current)) return [];
        $keep = [
            'id',
            DGPTM_WSB_Veranstal_X_Contacts::FIELD_QUELLE,
            DGPTM_WSB_Veranstal_X_Contacts::FIELD_BLUEPRINT,
            DGPTM_WSB_Veranstal_X_Contacts::FIELD_PAYMENT,
            DGPTM_WSB_Veranstal_X_Contacts::FIELD_LAST_SYNC,
            'Stripe_Charge_ID',
            'Stripe_Session_ID',
            'Books_Invoice_ID',
        ];
        $out = [];
        foreach ($keep as $k) {
            if (isset($current[$k])) $out[$k] = $current[$k];
        }
        return $out;
    }
}
