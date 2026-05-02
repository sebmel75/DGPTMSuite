<?php
/**
 * 4-Felder-E-Mail-Suche im Zoho-CRM-Modul Contacts.
 *
 * Geprueft werden: Email, Secondary_Email, Third_Email, Fourth_Email.
 * Bei Treffer wird die Zoho-Contact-ID zurueckgegeben, sonst null.
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Contact_Lookup {

    const COQL_URL = 'https://www.zohoapis.eu/crm/v3/coql';

    /**
     * Sucht einen Kontakt per E-Mail in 4 Feldern.
     *
     * @param string $email
     * @return string|null  Zoho-Contact-ID oder null
     */
    public static function find_by_email($email) {
        $email = strtolower(trim((string) $email));
        if (empty($email) || !is_email($email)) {
            return null;
        }
        $token = DGPTM_WSB_Veranstal_X_Contacts::get_token();
        if (!$token) return null;

        $email_q = esc_sql($email);
        $coql = "select id from Contacts "
              . "where Email = '$email_q' "
              . "or Secondary_Email = '$email_q' "
              . "or Third_Email = '$email_q' "
              . "or Fourth_Email = '$email_q' "
              . "limit 1";

        $resp = wp_remote_post(self::COQL_URL, [
            'timeout' => 12,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['select_query' => $coql]),
        ]);
        if (is_wp_error($resp) || wp_remote_retrieve_response_code($resp) !== 200) {
            return null;
        }
        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return isset($body['data'][0]['id']) ? $body['data'][0]['id'] : null;
    }

    /**
     * Legt einen neuen Contact in Zoho an.
     *
     * @param string $first_name
     * @param string $last_name
     * @param string $email
     * @param array  $extra Weitere Felder (z.B. Mailing_City, Phone, ...)
     * @return string|null Zoho-Contact-ID oder null bei Fehler
     */
    public static function create($first_name, $last_name, $email, array $extra = []) {
        $token = DGPTM_WSB_Veranstal_X_Contacts::get_token();
        if (!$token) return null;

        $data = array_merge([
            'First_Name' => $first_name,
            'Last_Name'  => $last_name,
            'Email'      => $email,
        ], $extra);

        $resp = wp_remote_post('https://www.zohoapis.eu/crm/v3/Contacts', [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['data' => [$data]]),
        ]);
        if (is_wp_error($resp)) return null;
        if (!in_array(wp_remote_retrieve_response_code($resp), [200, 201], true)) return null;

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return isset($body['data'][0]['details']['id']) ? $body['data'][0]['details']['id'] : null;
    }
}
