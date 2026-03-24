<?php
/**
 * Zoho CRM Client fuer Mitgliedsbeitrag
 *
 * Mitglieder laden, Beitragshoehen aus CRM-Variablen,
 * Kontakte aktualisieren, Blueprint-Transitionen.
 */

if (!defined('ABSPATH')) exit;

class DGPTM_MB_Zoho_CRM {

    private DGPTM_MB_Config $config;
    private ?string $access_token = null;
    private string $base_url;

    public function __construct(DGPTM_MB_Config $config) {
        $this->config = $config;
        $this->base_url = $config->zoho_api_domain() . '/crm/' . $config->zoho_crm_version();
    }

    /* ============================================================ */
    /* OAuth Token                                                   */
    /* ============================================================ */

    private function get_token(): ?string {
        if ($this->access_token) return $this->access_token;

        $cached = get_transient('dgptm_mb_crm_token');
        if ($cached) {
            $this->access_token = $cached;
            return $cached;
        }

        $response = wp_remote_post($this->config->zoho_accounts_domain() . '/oauth/v2/token', [
            'timeout' => 15,
            'body' => [
                'grant_type'    => 'refresh_token',
                'client_id'     => $this->config->zoho_client_id(),
                'client_secret' => $this->config->zoho_client_secret(),
                'refresh_token' => $this->config->zoho_refresh_token(),
            ],
        ]);

        if (is_wp_error($response)) return null;

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) return null;

        $this->access_token = $body['access_token'];
        set_transient('dgptm_mb_crm_token', $this->access_token, 55 * MINUTE_IN_SECONDS);
        return $this->access_token;
    }

    private function api_request(string $endpoint, string $method = 'GET', ?array $body = null): ?array {
        $token = $this->get_token();
        if (!$token) return null;

        $args = [
            'method'  => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
        ];

        if ($body && in_array($method, ['POST', 'PUT'])) {
            $args['body'] = wp_json_encode($body);
        }

        $url = $this->base_url . '/' . ltrim($endpoint, '/');
        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) return null;
        return json_decode(wp_remote_retrieve_body($response), true);
    }

    private function coql_query(string $query): array {
        $token = $this->get_token();
        if (!$token) return [];

        $response = wp_remote_post($this->base_url . '/coql', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['select_query' => $query]),
        ]);

        if (is_wp_error($response)) return [];
        $body = json_decode(wp_remote_retrieve_body($response), true);
        return $body['data'] ?? [];
    }

    /* ============================================================ */
    /* Mitglieder laden                                              */
    /* ============================================================ */

    public function get_members_for_billing(int $year): array {
        $statuses = implode("','", $this->config->allowed_statuses());
        $types = array_keys($this->config->membership_types());
        $types_str = implode("','", $types);

        $fields = 'id,Full_Name,First_Name,Last_Name,Email,Membership_Type,Membership_Number,Member_Since,letztesBeitragsjahr,Guthaben2,GoCardlessID,MandatID,Finance_ID,Contact_Status,Student_Status,Valid_Through,Freigestellt_bis,goCardlessPayment,last_invoice';

        // Gruppe 1: Bereits abgerechnet, Beitragsjahr < aktuelles Jahr
        $q1 = "SELECT {$fields} FROM Contacts WHERE Mitglied = true AND Contact_Status IN ('{$statuses}') AND Membership_Type IN ('{$types_str}') AND letztesBeitragsjahr < {$year} LIMIT 200";
        $group1 = $this->coql_query($q1);

        // Gruppe 2: Nie abgerechnet, aber Member_Since gesetzt
        $q2 = "SELECT {$fields} FROM Contacts WHERE Mitglied = true AND Contact_Status IN ('{$statuses}') AND Membership_Type IN ('{$types_str}') AND letztesBeitragsjahr IS NULL AND Member_Since IS NOT NULL LIMIT 200";
        $group2 = $this->coql_query($q2);

        // Gruppe 3: Nie abgerechnet, Member_Since auch leer
        $q3 = "SELECT {$fields} FROM Contacts WHERE Mitglied = true AND Contact_Status IN ('{$statuses}') AND Membership_Type IN ('{$types_str}') AND letztesBeitragsjahr IS NULL AND Member_Since IS NULL LIMIT 200";
        $group3 = $this->coql_query($q3);

        return array_merge($group1, $group2, $group3);
    }

    /**
     * Einzelnen Kontakt laden
     */
    public function get_contact(string $contact_id): ?array {
        $result = $this->api_request('Contacts/' . $contact_id);
        return $result['data'][0] ?? null;
    }

    /**
     * Kontakt aktualisieren
     */
    public function update_contact(string $contact_id, array $data): bool {
        $result = $this->api_request('Contacts/' . $contact_id, 'PUT', [
            'data' => [$data],
        ]);
        return !empty($result['data'][0]['status']) && $result['data'][0]['status'] === 'success';
    }

    /* ============================================================ */
    /* CRM Variablen (Beitragshoehen)                                */
    /* ============================================================ */

    public function get_variable(string $name): ?float {
        $cached = get_transient('dgptm_mb_crm_var_' . $name);
        if (false !== $cached) return (float) $cached;

        $result = $this->api_request('settings/variables?group=beitraege');
        if (!$result || empty($result['variables'])) return null;

        foreach ($result['variables'] as $var) {
            if (($var['api_name'] ?? '') === $name) {
                $value = (float) $var['value'];
                set_transient('dgptm_mb_crm_var_' . $name, $value, DAY_IN_SECONDS);
                return $value;
            }
        }
        return null;
    }

    /**
     * Alle Beitragshoehen laden
     */
    public function get_all_fees(): array {
        $types = $this->config->membership_types();
        $fees = [];
        foreach ($types as $type => $info) {
            if (empty($info['variable'])) {
                $fees[$type] = $info['fee'] ?? 0.0;
                continue;
            }
            $fees[$type] = $this->get_variable($info['variable']) ?? 0.0;
        }
        return $fees;
    }

    /* ============================================================ */
    /* Blueprint Transition                                          */
    /* ============================================================ */

    public function trigger_blueprint(string $contact_id, string $transition_name): bool {
        $bp = $this->api_request('Contacts/' . $contact_id . '/actions/blueprint');
        if (!$bp || empty($bp['blueprint']['transitions'])) return false;

        $transition_id = null;
        foreach ($bp['blueprint']['transitions'] as $t) {
            if ($t['name'] === $transition_name) {
                $transition_id = $t['id'];
                break;
            }
        }

        if (!$transition_id) return false;

        $result = $this->api_request('Contacts/' . $contact_id . '/actions/blueprint', 'PUT', [
            'blueprint' => [
                ['transition_id' => $transition_id, 'data' => new \stdClass()],
            ],
        ]);

        return !empty($result);
    }

    /* ============================================================ */
    /* Mitglieder-Statistiken                                        */
    /* ============================================================ */

    public function get_member_stats(): array {
        $stats = [
            'total_active' => 0,
            'by_type' => [],
            'billing_status' => [],
            'timestamp' => current_time('c'),
        ];

        // Nach Typ
        $type_data = $this->coql_query("SELECT COUNT(id) as cnt, Membership_Type FROM Contacts WHERE Mitglied = true AND Contact_Status IN ('Aktiv','Freigestellt') GROUP BY Membership_Type");
        foreach ($type_data as $row) {
            $type = $row['Membership_Type'] ?? 'Unbekannt';
            $count = (int) ($row['cnt'] ?? 0);
            $stats['by_type'][$type] = $count;
            $stats['total_active'] += $count;
        }

        // Beitragslauf-Status
        $year = (int) date('Y');
        $billing_data = $this->coql_query("SELECT COUNT(id) as cnt, letztesBeitragsjahr FROM Contacts WHERE Mitglied = true AND Contact_Status IN ('Aktiv','Freigestellt') GROUP BY letztesBeitragsjahr");

        $billed_current = 0;
        $billed_previous = 0;
        $never_billed = 0;
        foreach ($billing_data as $row) {
            $by = $row['letztesBeitragsjahr'] ?? null;
            $cnt = (int) ($row['cnt'] ?? 0);
            if ($by === null || $by === '') $never_billed += $cnt;
            elseif ((int) $by >= $year) $billed_current += $cnt;
            else $billed_previous += $cnt;
        }

        $stats['billing_status'] = [
            'current_year'    => $year,
            'billed_current'  => $billed_current,
            'billed_previous' => $billed_previous,
            'never_billed'    => $never_billed,
            'pending'         => $stats['total_active'] - $billed_current,
        ];

        return $stats;
    }
}
