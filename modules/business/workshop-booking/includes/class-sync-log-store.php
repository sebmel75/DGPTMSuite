<?php
/**
 * Append-only Audit-Log fuer alle Sync-Operationen.
 *
 * AGB §6 Abs. 3 (Schriftform): jede Aenderung am Buchungsstatus
 * ist hier nachvollziehbar dokumentiert.
 *
 * Spec Abschnitt 4a.6.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Sync_Log_Store {

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dgptm_workshop_sync_log';
    }

    /**
     * Schreibt einen Audit-Eintrag und liefert die neue ID zurueck.
     *
     * @param DGPTM_WSB_Sync_Intent $intent
     * @param array  $previous       ['blueprint' => ..., 'payment' => ...]
     * @param bool   $success
     * @param string|null $error_code
     * @param string|null $error_message
     * @return string log_id
     */
    public static function record($intent, array $previous, $success, $error_code = null, $error_message = null) {
        global $wpdb;
        $wpdb->insert(self::table(), [
            'veranstal_x_contact_id'   => $intent->veranstal_x_contact_id,
            'source'                   => $intent->source,
            'intent_blueprint_state'   => $intent->target_blueprint_state,
            'intent_payment_status'    => $intent->target_payment_status,
            'previous_blueprint_state' => isset($previous['blueprint']) ? $previous['blueprint'] : null,
            'previous_payment_status'  => isset($previous['payment'])   ? $previous['payment']   : null,
            'success'                  => $success ? 1 : 0,
            'error_code'               => $error_code,
            'error_message'            => $error_message,
            'payload_json'             => wp_json_encode($intent->payload),
            'reason'                   => $intent->reason,
            'created_at'               => current_time('mysql'),
        ]);
        return (string) $wpdb->insert_id;
    }

    public static function recent($contact_id, $limit = 20) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE veranstal_x_contact_id = %s ORDER BY id DESC LIMIT %d",
            $contact_id, (int) $limit
        ), ARRAY_A);
    }
}
