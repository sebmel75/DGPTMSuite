<?php
/**
 * ICS_Builder — erzeugt VCALENDAR-Daten (RFC 5545) als String.
 *
 * Wird vom Mail_Sender als Anhang an die Bestaetigungsmail gehaengt,
 * damit Teilnehmer:innen den Termin direkt in den Kalender uebernehmen koennen.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_ICS_Builder {

    /**
     * Baut einen ICS-Inhalt fuer ein Event.
     *
     * @param array  $event         DGfK_Events-Datensatz
     * @param string $contact_email zur UID-Erzeugung (eindeutig pro TN+Event)
     * @return string ICS-Inhalt (mit CRLF-Zeilenumbruechen)
     */
    public static function build($event, $contact_email) {
        $name      = isset($event['Name']) ? $event['Name'] : 'DGPTM Workshop';
        $from      = isset($event['From_Date']) ? $event['From_Date'] : null;
        $to        = isset($event['End_Date']) ? $event['End_Date'] : $from;
        $event_id  = isset($event['id']) ? $event['id'] : '';

        $uid       = sha1($event_id . '|' . strtolower((string) $contact_email)) . '@dgptm.de';
        $stamp     = gmdate('Ymd\THis\Z');
        $start_utc = self::format_dt($from);
        $end_utc   = self::format_dt($to);

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//DGPTM//Workshop-Booking//DE',
            'METHOD:PUBLISH',
            'BEGIN:VEVENT',
            'UID:' . $uid,
            'DTSTAMP:' . $stamp,
            'DTSTART:' . $start_utc,
            'DTEND:'   . $end_utc,
            'SUMMARY:' . self::esc($name),
            'DESCRIPTION:' . self::esc('Buchung der DGPTM (Deutsche Gesellschaft für Perfusiologie und Technische Medizin e.V.)'),
            'STATUS:CONFIRMED',
            'END:VEVENT',
            'END:VCALENDAR',
        ];
        return implode("\r\n", $lines) . "\r\n";
    }

    private static function format_dt($date) {
        if (!$date) return gmdate('Ymd\THis\Z');
        $ts = strtotime($date);
        if ($ts === false) return gmdate('Ymd\THis\Z');
        return gmdate('Ymd\THis\Z', $ts);
    }

    /**
     * Escaped Sonderzeichen gemaess RFC 5545.
     */
    private static function esc($text) {
        return str_replace(
            ['\\', "\n", ',', ';'],
            ['\\\\', '\\n', '\\,', '\\;'],
            (string) $text
        );
    }
}
