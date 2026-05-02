<?php
/**
 * Mail_Sender — versendet transactional Mails fuer das Workshop-Booking-Modul.
 *
 * Phase 1: send_confirmation() (Buchungsbestaetigung mit ICS-Anhang).
 * Phase 2: Bestaetigung enthaelt zusaetzlich Ticket-PDF (mit QR + Ticketnummer)
 *          und ggf. Token-Link fuer Nicht-Mitglieder zur Ticket-Verwaltung.
 *
 * Reminder, Storno, Termin-Verlegung folgen in spaeteren Phasen.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Mail_Sender {

    /**
     * Versendet die Buchungsbestaetigung mit ICS- und Ticket-PDF-Anhang.
     *
     * @param string     $veranstal_x_contact_id Zoho-ID
     * @param array|null $event                  DGfK_Events-Datensatz
     * @return bool true wenn wp_mail() erfolgreich
     */
    public static function send_confirmation($veranstal_x_contact_id, $event) {
        $contact = DGPTM_WSB_Veranstal_X_Contacts::fetch($veranstal_x_contact_id);
        if (!$contact) return false;

        $email = self::extract_email($contact);
        if (!$email) return false;

        $first_name = isset($contact['Contact_Name']['First_Name']) ? $contact['Contact_Name']['First_Name'] : '';
        $last_name  = isset($contact['Contact_Name']['Last_Name'])  ? $contact['Contact_Name']['Last_Name']  : '';
        $full_name  = trim($first_name . ' ' . $last_name);
        if ($full_name === '') {
            $full_name = isset($contact['Contact_Name']['name']) ? $contact['Contact_Name']['name'] : '';
        }

        $event_name = is_array($event) && isset($event['Name'])      ? $event['Name']      : 'Workshop';
        $event_from = is_array($event) && isset($event['From_Date']) ? date_i18n('d.m.Y', strtotime($event['From_Date'])) : '';
        $event_loc  = is_array($event) && isset($event['Location'])  ? $event['Location']  : '';
        $event_type = is_array($event) && isset($event['Event_Type']) ? $event['Event_Type'] : 'Workshop';

        $ticket_number = isset($contact[DGPTM_WSB_Ticket_Number::FIELD_NAME])
                       ? $contact[DGPTM_WSB_Ticket_Number::FIELD_NAME] : '';
        $ticket_type   = isset($contact['Ticket_Type']) ? $contact['Ticket_Type'] : '';

        // Token-Link fuer Nicht-WP-User (Phase 2)
        $token_url = self::maybe_create_token_url($veranstal_x_contact_id, $email, $event);

        $body = self::render_template($full_name, $event_name, $event_from, $ticket_number, $token_url);

        // Anhaenge sammeln
        $attachments = [];

        // ICS-Anhang
        $ics_path = self::create_temp_ics(is_array($event) ? $event : [], $email);
        if ($ics_path) $attachments[] = $ics_path;

        // Phase 2: Ticket-PDF
        $pdf_path = self::create_temp_ticket_pdf([
            'ticket_number'  => $ticket_number,
            'ticket_type'    => $ticket_type,
            'first_name'     => $first_name,
            'last_name'      => $last_name,
            'event_name'     => $event_name,
            'event_from'     => $event_from,
            'event_location' => $event_loc,
        ]);
        if ($pdf_path) $attachments[] = $pdf_path;

        $subject = 'DGPTM Buchungsbestätigung: ' . $event_name;
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $sent = wp_mail($email, $subject, $body, $headers, $attachments);

        // Aufraeumen
        foreach ($attachments as $path) {
            @unlink($path);
        }
        return (bool) $sent;
    }

    /**
     * Erzeugt fuer Nicht-WP-User einen Booking-Token (gueltig bis Workshop-Ende + 30d).
     * Bei eingeloggten DGPTM-Mitgliedern wird kein Token erzeugt — sie nutzen den
     * Mitgliederbereich (Phase 3).
     */
    private static function maybe_create_token_url($contact_id, $email, $event) {
        // Hat dieser User einen WP-Account?
        $wp_user = email_exists($email);
        if ($wp_user) {
            return null; // Mitgliederbereich-Pfad (Phase 3)
        }

        $end_date = is_array($event) && isset($event['End_Date'])
                  ? $event['End_Date']
                  : (is_array($event) && isset($event['From_Date']) ? $event['From_Date'] : date('Y-m-d'));

        $token = DGPTM_WSB_Token_Store::create_booking_token($contact_id, $email, $end_date);
        return $token ? DGPTM_WSB_Token_Store::build_booking_url($token) : null;
    }

    private static function render_template($name, $event_name, $event_from, $ticket_number, $token_url) {
        ob_start();
        $tpl = dirname(__DIR__) . '/templates/mails/booking-confirmation.php';
        if (file_exists($tpl)) {
            include $tpl;
        }
        return ob_get_clean();
    }

    private static function create_temp_ics(array $event, $email) {
        $ics = DGPTM_WSB_ICS_Builder::build($event, $email);
        if (!$ics) return null;
        $tmp = wp_tempnam('dgptm_wsb_ics');
        $path = $tmp . '.ics';
        if (!@rename($tmp, $path)) {
            $path = $tmp;
        }
        if (false === file_put_contents($path, $ics)) return null;
        return $path;
    }

    private static function create_temp_ticket_pdf(array $ticket) {
        if (empty($ticket['ticket_number'])) {
            // Kein PDF wenn keine Ticketnummer — Phase 1 fallback
            return null;
        }
        $pdf = DGPTM_WSB_Ticket_PDF::render($ticket);
        if (!$pdf) return null;

        $tmp = wp_tempnam('dgptm_wsb_ticket');
        $filename = 'DGPTM-Ticket-' . preg_replace('/[^a-zA-Z0-9_-]/', '', $ticket['ticket_number']) . '.pdf';
        $path = dirname($tmp) . '/' . $filename;
        if (!@rename($tmp, $path)) {
            $path = $tmp;
        }
        if (false === file_put_contents($path, $pdf)) return null;
        return $path;
    }

    /**
     * Versucht eine E-Mail-Adresse aus dem Veranstal_X_Contacts-Datensatz zu lesen.
     */
    private static function extract_email($contact) {
        if (!empty($contact['Contact_Name']['email'])) return $contact['Contact_Name']['email'];
        if (!empty($contact['Email']))                 return $contact['Email'];
        if (!empty($contact['email']))                 return $contact['email'];
        return null;
    }
}
