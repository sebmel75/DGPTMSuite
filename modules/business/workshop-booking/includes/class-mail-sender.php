<?php
/**
 * Mail_Sender — versendet transactional Mails fuer das Workshop-Booking-Modul.
 *
 * Phase 1: nur send_confirmation() (Buchungsbestaetigung mit ICS-Anhang).
 * Reminder, Storno, Termin-Verlegung folgen in spaeteren Phasen.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Mail_Sender {

    /**
     * Versendet die Buchungsbestaetigung mit ICS-Anhang.
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

        $event_name = is_array($event) && isset($event['Name']) ? $event['Name'] : 'Workshop';
        $event_from = is_array($event) && isset($event['From_Date'])
                    ? date_i18n('d.m.Y', strtotime($event['From_Date']))
                    : '';

        $body = self::render_template($full_name, $event_name, $event_from);

        // ICS-Anhang als temporaere Datei
        $tmp = wp_tempnam('dgptm_wsb_ics');
        $ics_path = $tmp . '.ics';
        @rename($tmp, $ics_path);
        if (!file_exists($ics_path)) {
            // Fallback: ohne Anhang verschicken
            $ics_path = null;
        } else {
            file_put_contents($ics_path, DGPTM_WSB_ICS_Builder::build(is_array($event) ? $event : [], $email));
        }

        $subject = 'DGPTM Buchungsbestätigung: ' . $event_name;
        $headers = ['Content-Type: text/html; charset=UTF-8'];

        $attachments = $ics_path ? [$ics_path] : [];
        $sent = wp_mail($email, $subject, $body, $headers, $attachments);

        if ($ics_path) {
            @unlink($ics_path);
        }
        return (bool) $sent;
    }

    private static function render_template($name, $event_name, $event_from) {
        ob_start();
        $tpl = dirname(__DIR__) . '/templates/mails/booking-confirmation.php';
        if (file_exists($tpl)) {
            include $tpl;
        }
        return ob_get_clean();
    }

    /**
     * Versucht eine E-Mail-Adresse aus dem Veranstal_X_Contacts-Datensatz zu lesen.
     * Reihenfolge: Contact_Name.email > Email > Direkt-Felder.
     */
    private static function extract_email($contact) {
        if (!empty($contact['Contact_Name']['email'])) return $contact['Contact_Name']['email'];
        if (!empty($contact['Email']))                 return $contact['Email'];
        if (!empty($contact['email']))                 return $contact['email'];
        return null;
    }
}
