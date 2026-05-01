<?php
/**
 * Liefert kommende Workshops und Webinare aus dem Zoho-CRM-Modul DGfK_Events.
 *
 * Filter: Event_Type IN ('Workshop','Webinar') AND From_Date >= heute.
 * Cache: 5 Minuten via WP-Transient (kann via flush_cache() geleert werden).
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Event_Source {

    const TRANSIENT_KEY = 'dgptm_wsb_events_v1';
    const TTL           = 300; // 5 Minuten
    const COQL_URL      = 'https://www.zohoapis.eu/crm/v3/coql';

    public static function fetch_upcoming() {
        $cached = get_transient(self::TRANSIENT_KEY);
        if (is_array($cached)) {
            return $cached;
        }
        $events = self::query_zoho();
        if (is_array($events)) {
            set_transient(self::TRANSIENT_KEY, $events, self::TTL);
        }
        return is_array($events) ? $events : [];
    }

    public static function fetch_one($event_id) {
        if (empty($event_id)) return null;
        foreach (self::fetch_upcoming() as $ev) {
            if (isset($ev['id']) && $ev['id'] === $event_id) {
                return $ev;
            }
        }
        return null;
    }

    public static function flush_cache() {
        delete_transient(self::TRANSIENT_KEY);
    }

    private static function query_zoho() {
        $token = DGPTM_WSB_Veranstal_X_Contacts::get_token();
        if (!$token) return [];

        $select = 'id, Name, From_Date, End_Date, Event_Type, Maximum_Attendees, Tickets, '
                . 'Sprache, Storno_Frist_Tage, Anwesenheits_Schwelle_Prozent, '
                . 'EduGrant_Verfuegbar, EduGrant_Hoehe_EUR, EduGrant_Plaetze_Gesamt, '
                . 'EduGrant_Plaetze_Vergeben, Verantwortliche_Person, Ticket_Layout';
        $today = date('Y-m-d');
        $coql  = "select $select from DGfK_Events "
               . "where (Event_Type = 'Workshop' or Event_Type = 'Webinar') "
               . "and From_Date >= '$today' "
               . "limit 200";

        $resp = wp_remote_post(self::COQL_URL, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['select_query' => $coql]),
        ]);
        if (is_wp_error($resp)) return [];
        if (wp_remote_retrieve_response_code($resp) !== 200) return [];

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return isset($body['data']) && is_array($body['data']) ? $body['data'] : [];
    }
}
