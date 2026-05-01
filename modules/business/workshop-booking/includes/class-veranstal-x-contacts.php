<?php
/**
 * Privater Lese-/Schreib-Layer fuer das Zoho-Modul Veranstal_X_Contacts.
 *
 * INTERN: Wird ausschliesslich vom DGPTM_WSB_Sync_Coordinator aufgerufen.
 * Direkter Aufruf von ausserhalb gilt als Bug — alle Status-Aenderungen
 * gehen durch den Coordinator (Audit-Log, Drift-Erkennung, State-Machine,
 * Backstage-Skip).
 */
if (!defined('ABSPATH')) exit;

class DGPTM_WSB_Veranstal_X_Contacts {

    const ZOHO_API_BASE   = 'https://www.zohoapis.eu/crm/v3';
    const ZOHO_MODULE     = 'Veranstal_X_Contacts';
    const FIELD_BLUEPRINT = 'Anmelde_Status';
    const FIELD_PAYMENT   = 'Zahlungsstatus';
    const FIELD_QUELLE    = 'Quelle';
    const FIELD_LAST_SYNC = 'Last_Sync_At';

    /**
     * Holt das Bearer-Token vom crm-abruf-Modul.
     *
     * @return string|null
     */
    public static function get_token() {
        if (!class_exists('DGPTM_Zoho_Plugin')) {
            return null;
        }
        $plugin = DGPTM_Zoho_Plugin::get_instance();
        if (!method_exists($plugin, 'get_oauth_token')) {
            return null;
        }
        $token = $plugin->get_oauth_token();
        return is_string($token) && $token !== '' ? $token : null;
    }

    /**
     * Liest einen Veranstal_X_Contacts-Eintrag aus dem CRM.
     *
     * @param string $contact_id Zoho-ID
     * @return array|null  Felder-Array oder null bei 404 / Fehler
     */
    public static function fetch($contact_id) {
        if (empty($contact_id)) return null;

        $token = self::get_token();
        if (!$token) return null;

        $url  = self::ZOHO_API_BASE . '/' . self::ZOHO_MODULE . '/' . rawurlencode($contact_id);
        $resp = wp_remote_get($url, [
            'timeout' => 15,
            'headers' => ['Authorization' => 'Zoho-oauthtoken ' . $token],
        ]);
        if (is_wp_error($resp)) return null;

        $code = wp_remote_retrieve_response_code($resp);
        if ($code === 404) return null;
        if ($code !== 200) return null;

        $body = json_decode(wp_remote_retrieve_body($resp), true);
        return isset($body['data'][0]) ? $body['data'][0] : null;
    }

    /**
     * Anlegen oder Update eines Veranstal_X_Contacts-Eintrags.
     *
     * @param array       $data       Felder, die geschrieben werden
     * @param string|null $contact_id null = neu anlegen, sonst Update
     * @return array  ['success' => bool, 'id' => ?string, 'error' => ?string]
     */
    public static function upsert(array $data, $contact_id = null) {
        $token = self::get_token();
        if (!$token) {
            return ['success' => false, 'id' => null, 'error' => 'no_oauth_token'];
        }

        $url    = self::ZOHO_API_BASE . '/' . self::ZOHO_MODULE;
        $method = 'POST';
        if (!empty($contact_id)) {
            $url   .= '/' . rawurlencode($contact_id);
            $method = 'PUT';
        }

        $resp = wp_remote_request($url, [
            'method'  => $method,
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['data' => [$data]]),
        ]);
        if (is_wp_error($resp)) {
            return ['success' => false, 'id' => null, 'error' => $resp->get_error_message()];
        }

        $code = wp_remote_retrieve_response_code($resp);
        $body = json_decode(wp_remote_retrieve_body($resp), true);

        if (!in_array($code, [200, 201], true)) {
            $err = isset($body['data'][0]['code']) ? $body['data'][0]['code'] : ('http_' . $code);
            return ['success' => false, 'id' => null, 'error' => $err];
        }

        $id = $contact_id ?: (isset($body['data'][0]['details']['id']) ? $body['data'][0]['details']['id'] : null);
        return ['success' => true, 'id' => $id, 'error' => null];
    }
}
