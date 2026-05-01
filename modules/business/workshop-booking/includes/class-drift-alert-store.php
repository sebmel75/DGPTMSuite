<?php
/**
 * Drift-Alert-Store — kuratierter Alert-Stream fuer die Geschaeftsstelle.
 *
 * Wird genutzt, wenn der Sync_Coordinator Drift erkennt, der nicht
 * automatisch aufgeloest werden kann (z.B. CRM manuell auf Storniert,
 * aber Stripe nicht erstattet).
 *
 * Spec Abschnitt 4a.4 + 4a.6.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Drift_Alert_Store {

    const SEVERITY_INFO     = 'info';
    const SEVERITY_WARNING  = 'warning';
    const SEVERITY_CRITICAL = 'critical';

    public static function table() {
        global $wpdb;
        return $wpdb->prefix . 'dgptm_workshop_drift_alerts';
    }

    /**
     * Oeffnet einen Alert. Wenn fuer denselben Contact + Code bereits ein
     * offener Alert existiert, wird KEIN neuer angelegt (De-Dup).
     *
     * @return string alert_id
     */
    public static function open_alert($contact_id, $code, $severity, array $crm_snapshot, array $external_snapshot, $proposed_action) {
        global $wpdb;

        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM " . self::table() . "
             WHERE veranstal_x_contact_id = %s AND code = %s AND status = 'open' LIMIT 1",
            $contact_id, $code
        ));
        if ($existing) {
            return (string) $existing;
        }

        $wpdb->insert(self::table(), [
            'veranstal_x_contact_id'  => $contact_id,
            'code'                    => $code,
            'severity'                => $severity,
            'crm_state_snapshot'      => wp_json_encode($crm_snapshot),
            'external_state_snapshot' => wp_json_encode($external_snapshot),
            'proposed_action'         => $proposed_action,
            'status'                  => 'open',
            'created_at'              => current_time('mysql'),
        ]);
        return (string) $wpdb->insert_id;
    }

    public static function count_open() {
        global $wpdb;
        return (int) $wpdb->get_var("SELECT COUNT(*) FROM " . self::table() . " WHERE status = 'open'");
    }

    public static function get_open($limit = 50) {
        global $wpdb;
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE status = 'open' ORDER BY created_at DESC LIMIT %d",
            (int) $limit
        ), ARRAY_A);
    }
}
