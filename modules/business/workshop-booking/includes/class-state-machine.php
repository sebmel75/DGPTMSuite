<?php
/**
 * Erlaubte Blueprint-Uebergaenge zwischen den 8 Anmelde-Status.
 *
 * Manuelle Overrides (source=manual mit manage_options) duerfen jeden
 * Uebergang. Reconciliation/Stripe-Webhook sind an die State-Machine gebunden.
 *
 * Spec Abschnitt 4a.3 und 6.3.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_State_Machine {

    const S_ZAHLUNG_AUSSTEHEND = 'Zahlung ausstehend';
    const S_ANGEMELDET         = 'Angemeldet';
    const S_WARTELISTE         = 'Warteliste';
    const S_NACHRUECKER        = 'Nachrücker:in – Zahlung ausstehend';
    const S_ABGEBROCHEN        = 'Abgebrochen';
    const S_STORNIERT          = 'Storniert';
    const S_TEILGENOMMEN       = 'Teilgenommen';
    const S_NICHT_TEILGENOMMEN = 'Nicht teilgenommen';

    /**
     * Erlaubte Uebergaenge. Schluessel = von, Wert = Liste erlaubter Ziele.
     */
    private static function transitions() {
        return [
            self::S_ZAHLUNG_AUSSTEHEND => [
                self::S_ANGEMELDET,
                self::S_ABGEBROCHEN,
            ],
            self::S_ANGEMELDET => [
                self::S_STORNIERT,
                self::S_TEILGENOMMEN,
                self::S_NICHT_TEILGENOMMEN,
            ],
            self::S_WARTELISTE => [
                self::S_NACHRUECKER,
                self::S_ABGEBROCHEN,
            ],
            self::S_NACHRUECKER => [
                self::S_ANGEMELDET,
                self::S_ABGEBROCHEN,
            ],
            self::S_STORNIERT          => [],
            self::S_ABGEBROCHEN        => [],
            self::S_TEILGENOMMEN       => [],
            self::S_NICHT_TEILGENOMMEN => [],
        ];
    }

    public static function can_transition($from, $to, $source) {
        // Erstanlage: nur in Zahlung_ausstehend, Warteliste oder Angemeldet (kostenloses Ticket)
        if ($from === null || $from === '') {
            return in_array($to, [
                self::S_ZAHLUNG_AUSSTEHEND,
                self::S_WARTELISTE,
                self::S_ANGEMELDET,
            ], true);
        }
        // Idempotenz: gleicher Status ist immer ok
        if ($from === $to) {
            return true;
        }
        // Manueller Override durch Admin
        if ($source === DGPTM_WSB_Sync_Intent::SOURCE_MANUAL && current_user_can('manage_options')) {
            return true;
        }
        $transitions = self::transitions();
        $allowed = isset($transitions[$from]) ? $transitions[$from] : [];
        return in_array($to, $allowed, true);
    }

    public static function all_states() {
        return [
            self::S_ZAHLUNG_AUSSTEHEND, self::S_ANGEMELDET, self::S_WARTELISTE,
            self::S_NACHRUECKER, self::S_ABGEBROCHEN, self::S_STORNIERT,
            self::S_TEILGENOMMEN, self::S_NICHT_TEILGENOMMEN,
        ];
    }
}
