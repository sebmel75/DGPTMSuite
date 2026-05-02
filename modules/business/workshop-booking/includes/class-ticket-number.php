<?php
/**
 * Ticketnummer-Generator.
 *
 * Format: identisch zu Zoho Backstage (8-stellig), beginnend mit Praefix "99999"
 * fuer Modul-Tickets — so sind diese eindeutig von Backstage-Tickets unterscheidbar,
 * waehrend QR-Scanner beide Formate gleich behandeln koennen.
 *
 * Beispiel: 99999321 (Praefix 99999 + 3-stellige laufende Nummer)
 *
 * Spec EVL §3 Vorschlag 14 + offene Frage 7.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Ticket_Number {

    const PREFIX = '99999';
    const TOTAL_LENGTH = 8; // 5 Stellen Praefix + 3 Stellen laufende Nummer (initial)
    const FIELD_NAME = 'Ticket_Nummer';

    /**
     * Erzeugt die naechste freie Ticketnummer.
     *
     * Strategie: COQL-Abfrage des hoechsten existierenden 99999*-Tickets,
     * dann +1. Bei Race-Condition (zwei parallele Stripe-Webhooks) verlassen
     * wir uns auf die Zoho-CRM-Eindeutigkeitspruefung des Feldes
     * (Ticket_Nummer sollte unique constraint haben).
     *
     * @return string|null Ticketnummer oder null bei Fehler
     */
    public static function generate_next() {
        $highest = self::get_highest_modul_ticket();
        if ($highest === null) {
            // Erstes Modul-Ticket ueberhaupt
            return self::PREFIX . '001';
        }
        // Numerischen Anteil hochzaehlen
        $suffix_len = strlen($highest) - strlen(self::PREFIX);
        $suffix     = substr($highest, strlen(self::PREFIX));
        $next       = (int) $suffix + 1;
        $next_str   = str_pad((string) $next, max($suffix_len, 3), '0', STR_PAD_LEFT);
        return self::PREFIX . $next_str;
    }

    /**
     * Liefert die hoechste vorhandene Modul-Ticketnummer (mit Praefix 99999),
     * oder null wenn noch keine existiert.
     */
    private static function get_highest_modul_ticket() {
        $token = DGPTM_WSB_Veranstal_X_Contacts::get_token();
        if (!$token) return null;

        $coql = "select " . self::FIELD_NAME . " from Veranstal_X_Contacts "
              . "where " . self::FIELD_NAME . " like '" . self::PREFIX . "%' "
              . "order by " . self::FIELD_NAME . " desc limit 1";

        $resp = wp_remote_post('https://www.zohoapis.eu/crm/v3/coql', [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['select_query' => $coql]),
        ]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) return null;
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return isset($body['data'][0][self::FIELD_NAME]) ? (string) $body['data'][0][self::FIELD_NAME] : null;
    }

    /**
     * Prueft, ob eine Ticketnummer das Modul-Format hat.
     */
    public static function is_modul_ticket($ticket_number) {
        return is_string($ticket_number) && strpos($ticket_number, self::PREFIX) === 0;
    }
}
