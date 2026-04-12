<?php
if (!defined('ABSPATH')) exit;

class DGPTM_Stipendium_Zoho {

    private $settings;
    private $api_base = 'https://www.zohoapis.eu/crm/v8/';

    public function __construct($settings) {
        $this->settings = $settings;
    }

    /* ──────────────────────────────────────────
     * Token
     * ────────────────────────────────────────── */

    private function get_token() {
        // crm-abruf stellt die Funktion global bereit
        if (function_exists('dgptm_get_zoho_token')) {
            return dgptm_get_zoho_token();
        }
        // Fallback: direkt aus Options
        $token = get_option('dgptm_zoho_access_token', '');
        $expires = get_option('dgptm_zoho_token_expires', 0);
        if (time() >= $expires) {
            // Token abgelaufen — crm-abruf refresht automatisch
            if (class_exists('DGPTM_CRM_Abruf')) {
                $crm = DGPTM_CRM_Abruf::get_instance();
                if (method_exists($crm, 'get_valid_access_token')) {
                    return $crm->get_valid_access_token();
                }
            }
        }
        return $token;
    }

    private function headers() {
        return [
            'Authorization' => 'Zoho-oauthtoken ' . $this->get_token(),
            'Content-Type'  => 'application/json',
        ];
    }

    /* ──────────────────────────────────────────
     * Stipendien CRUD
     * ────────────────────────────────────────── */

    /**
     * Neuen Stipendien-Record erstellen.
     *
     * @param array $data Feld-Werte (API-Namen als Keys)
     * @return array|WP_Error Zoho Response oder Fehler
     */
    public function create_stipendium($data) {
        $data['Eingangsdatum'] = date('Y-m-d');
        $data['Status'] = 'Eingegangen';

        return $this->api_post('Stipendien', $data);
    }

    /**
     * Stipendien-Record aktualisieren.
     */
    public function update_stipendium($record_id, $data) {
        return $this->api_put('Stipendien', $record_id, $data);
    }

    /**
     * Stipendien einer Runde und eines Typs abrufen.
     */
    public function get_stipendien_by_runde($runde, $typ = null) {
        $cache_key = 'dgptm_stip_' . md5($runde . $typ);
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $criteria = '(Runde:equals:' . $runde . ')';
        if ($typ) {
            $criteria .= ' and (Stipendientyp:equals:' . $typ . ')';
        }

        $result = $this->api_search('Stipendien', $criteria);
        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
        }
        return $result;
    }

    /**
     * Einzelnes Stipendium per ID abrufen.
     */
    public function get_stipendium($record_id) {
        return $this->api_get('Stipendien', $record_id);
    }

    /* ──────────────────────────────────────────
     * Bewertungen CRUD
     * ────────────────────────────────────────── */

    /**
     * Neue Bewertung erstellen.
     */
    public function create_bewertung($data) {
        $data['Status'] = $data['Status'] ?? 'Entwurf';
        return $this->api_post('Stipendien_Bewertungen', $data);
    }

    /**
     * Bewertung aktualisieren.
     */
    public function update_bewertung($record_id, $data) {
        return $this->api_put('Stipendien_Bewertungen', $record_id, $data);
    }

    /**
     * Bewertungen eines Gutachters fuer eine Runde abrufen.
     */
    public function get_bewertungen_by_gutachter($contact_id, $stipendium_ids = []) {
        $cache_key = 'dgptm_stip_bew_' . md5($contact_id . implode(',', $stipendium_ids));
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $criteria = '(Gutachter:equals:' . $contact_id . ')';
        $result = $this->api_search('Stipendien_Bewertungen', $criteria);

        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, 5 * MINUTE_IN_SECONDS);
        }
        return $result;
    }

    /**
     * Alle Bewertungen fuer ein Stipendium abrufen.
     */
    public function get_bewertungen_by_stipendium($stipendium_id) {
        $criteria = '(Stipendium:equals:' . $stipendium_id . ')';
        return $this->api_search('Stipendien_Bewertungen', $criteria);
    }

    /**
     * Alle Bewertungen einer Runde via COQL (ein API-Call statt n).
     */
    public function get_alle_bewertungen_runde($runde, $typ = null) {
        $cache_key = 'dgptm_stip_bew_runde_' . md5($runde . $typ);
        $cached = get_transient($cache_key);
        if ($cached !== false) return $cached;

        $query = "SELECT Stipendium, Gutachter, A_Gewichtet, B_Gewichtet, C_Gewichtet, D_Gewichtet, Gesamtscore, Status, Gesamtanmerkungen "
               . "FROM Stipendien_Bewertungen "
               . "WHERE Stipendium.Runde = '" . esc_sql($runde) . "'";
        if ($typ) {
            $query .= " AND Stipendium.Stipendientyp = '" . esc_sql($typ) . "'";
        }

        $result = $this->api_coql($query);
        if (!is_wp_error($result)) {
            set_transient($cache_key, $result, 10 * MINUTE_IN_SECONDS);
        }
        return $result;
    }

    /* ──────────────────────────────────────────
     * Cache Invalidierung
     * ────────────────────────────────────────── */

    public function invalidate_stipendien_cache($runde, $typ = null) {
        delete_transient('dgptm_stip_' . md5($runde . $typ));
        delete_transient('dgptm_stip_bew_runde_' . md5($runde . $typ));
    }

    public function invalidate_bewertung_cache($contact_id) {
        // Alle Transients mit diesem Gutachter loeschen
        global $wpdb;
        $wpdb->query($wpdb->prepare(
            "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
            '_transient_dgptm_stip_bew_' . md5($contact_id) . '%'
        ));
    }

    /* ──────────────────────────────────────────
     * Low-Level API
     * ────────────────────────────────────────── */

    private function api_post($module, $data) {
        $url = $this->api_base . $module;
        $body = json_encode(['data' => [$data]]);

        $response = $this->safe_remote('POST', $url, $body);
        return $this->parse_response($response);
    }

    private function api_put($module, $record_id, $data) {
        $url = $this->api_base . $module . '/' . $record_id;
        $body = json_encode(['data' => [$data]]);

        $response = $this->safe_remote('PUT', $url, $body);
        return $this->parse_response($response);
    }

    private function api_get($module, $record_id) {
        $url = $this->api_base . $module . '/' . $record_id;
        $response = $this->safe_remote('GET', $url);
        return $this->parse_response($response);
    }

    private function api_search($module, $criteria) {
        $url = $this->api_base . $module . '/search?criteria=' . urlencode('(' . $criteria . ')');
        $response = $this->safe_remote('GET', $url);
        $parsed = $this->parse_response($response);
        if (is_wp_error($parsed)) return $parsed;
        return $parsed['data'] ?? [];
    }

    private function api_coql($query) {
        $url = $this->api_base . 'coql';
        $body = json_encode(['select_query' => $query]);
        $response = $this->safe_remote('POST', $url, $body);
        $parsed = $this->parse_response($response);
        if (is_wp_error($parsed)) return $parsed;
        return $parsed['data'] ?? [];
    }

    /**
     * SSRF-sichere Remote-Anfrage (nutzt dgptm_safe_remote falls verfuegbar).
     */
    private function safe_remote($method, $url, $body = null) {
        $args = [
            'method'  => $method,
            'headers' => $this->headers(),
            'timeout' => 30,
        ];
        if ($body) {
            $args['body'] = $body;
        }

        if (function_exists('dgptm_safe_remote')) {
            return dgptm_safe_remote($url, $args);
        }
        return wp_remote_request($url, $args);
    }

    private function parse_response($response) {
        if (is_wp_error($response)) {
            $this->log_error('API-Fehler: ' . $response->get_error_message());
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code >= 400) {
            $msg = $body['message'] ?? ('HTTP ' . $code);
            $this->log_error('API HTTP ' . $code . ': ' . $msg);
            return new WP_Error('zoho_api_error', $msg, ['status' => $code]);
        }

        return $body;
    }

    private function log_error($message) {
        if (function_exists('dgptm_log_error')) {
            dgptm_log_error($message, 'stipendium');
        }
    }
}
