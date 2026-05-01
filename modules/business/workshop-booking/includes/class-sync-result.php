<?php
/**
 * Sync_Result — Ergebnis einer Sync_Intent-Anwendung.
 *
 * Spec Abschnitt 4a.2.
 */
if (!defined('ABSPATH')) exit;

final class DGPTM_WSB_Sync_Result {

    /** @var bool */
    public $success;

    /** @var string|null */
    public $error_code;

    /** @var string Foreign Key auf wp_dgptm_workshop_sync_log.id */
    public $log_id;

    /** @var string|null Foreign Key auf wp_dgptm_workshop_drift_alerts.id (falls erzeugt) */
    public $alert_id;

    const ERR_TRANSITION_FORBIDDEN = 'transition_forbidden';
    const ERR_ZOHO_API_ERROR       = 'zoho_api_error';
    const ERR_DRIFT_DETECTED       = 'drift_detected';
    const ERR_SOURCE_SKIPPED       = 'source_skipped';
    const ERR_CONTACT_NOT_FOUND    = 'contact_not_found';
    const ERR_INVALID_INTENT       = 'invalid_intent';

    private function __construct($success, $error_code, $log_id, $alert_id) {
        $this->success    = (bool) $success;
        $this->error_code = $error_code;
        $this->log_id     = (string) $log_id;
        $this->alert_id   = $alert_id;
    }

    public static function ok($log_id) {
        return new self(true, null, $log_id, null);
    }

    public static function fail($error_code, $log_id, $alert_id = null) {
        return new self(false, $error_code, $log_id, $alert_id);
    }

    /**
     * Skip ist kein Fehler — der Sync wurde bewusst nicht durchgefuehrt
     * (z.B. weil Quelle != Modul). Liefert success=true.
     */
    public static function skipped($log_id) {
        return new self(true, self::ERR_SOURCE_SKIPPED, $log_id, null);
    }
}
