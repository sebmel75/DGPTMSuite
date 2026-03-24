<?php
/**
 * Zoho CRM Client fuer Finanzen
 *
 * Mitglieder laden, Beitragshoehen aus CRM-Variablen,
 * Kontakte aktualisieren, Blueprint-Transitionen,
 * Mitglieder-Statistiken und Finanz-spezifische Abfragen.
 *
 * COQL-Regel: Picklist-Felder (Contact_Status, Membership_Type)
 * verwenden IMMER build_or_clause() statt IN-Operator.
 */

if (!defined('ABSPATH')) exit;

class DGPTM_FIN_Zoho_CRM {

    private DGPTM_FIN_Config $config;
    private ?string $access_token = null;
    private string $base_url;

    public function __construct(DGPTM_FIN_Config $config) {
        $this->config = $config;
        $this->base_url = $config->zoho_api_domain() . '/crm/' . $config->zoho_crm_version();
    }

    /* ============================================================ */
    /* OAuth Token                                                   */
    /* ============================================================ */

    private function get_token(): ?string {
        if ($this->access_token) return $this->access_token;

        $cached = get_transient('dgptm_fin_crm_token');
        if ($cached) {
            $this->access_token = $cached;
            return $cached;
        }

        // Versuch 1: Token vom crm-abruf Modul uebernehmen
        if (class_exists('DGPTM_Zoho_Plugin')) {
            $crm_plugin = DGPTM_Zoho_Plugin::get_instance();
            if ($crm_plugin && method_exists($crm_plugin, 'get_oauth_token')) {
                $shared_token = $crm_plugin->get_oauth_token();
                if ($shared_token) {
                    $this->access_token = $shared_token;
                    set_transient('dgptm_fin_crm_token', $this->access_token, 55 * MINUTE_IN_SECONDS);
                    return $this->access_token;
                }
            }
        }

        // Versuch 2: Eigener OAuth Refresh
        $client_id = $this->config->zoho_client_id();
        $refresh = $this->config->zoho_refresh_token();
        error_log(sprintf('[DGPTM Finanzen CRM] Token-Refresh: client_id=%s, refresh_token=%s',
            $client_id ? 'gesetzt' : 'FEHLT', $refresh ? 'gesetzt' : 'FEHLT'));

        $response = wp_remote_post($this->config->zoho_accounts_domain() . '/oauth/v2/token', [
            'timeout' => 15,
            'body' => [
                'grant_type'    => 'refresh_token',
                'client_id'     => $client_id,
                'client_secret' => $this->config->zoho_client_secret(),
                'refresh_token' => $refresh,
            ],
        ]);

        if (is_wp_error($response)) {
            error_log('[DGPTM Finanzen CRM] OAuth WP_Error: ' . $response->get_error_message());
            return null;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (empty($body['access_token'])) {
            error_log('[DGPTM Finanzen CRM] OAuth fehlgeschlagen: ' . substr(wp_remote_retrieve_body($response), 0, 300));
            return null;
        }

        $this->access_token = $body['access_token'];
        set_transient('dgptm_fin_crm_token', $this->access_token, 55 * MINUTE_IN_SECONDS);
        return $this->access_token;
    }

    /* ============================================================ */
    /* HTTP Client                                                   */
    /* ============================================================ */

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

        if (is_wp_error($response)) {
            error_log('[DGPTM Finanzen CRM] WP_Error: ' . $response->get_error_message() . ' | URL: ' . $url);
            return null;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);

        if ($code >= 400) {
            error_log(sprintf('[DGPTM Finanzen CRM] HTTP %d | URL: %s | Body: %s', $code, $url, substr($raw, 0, 500)));
        }

        return json_decode($raw, true);
    }

    private function coql_query(string $query): array {
        $token = $this->get_token();
        if (!$token) {
            error_log('[DGPTM Finanzen CRM] COQL abgebrochen: Kein Token');
            return [];
        }

        $response = wp_remote_post($this->base_url . '/coql', [
            'timeout' => 30,
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type'  => 'application/json',
            ],
            'body' => wp_json_encode(['select_query' => $query]),
        ]);

        if (is_wp_error($response)) {
            error_log('[DGPTM Finanzen CRM] COQL WP_Error: ' . $response->get_error_message());
            return [];
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);

        if ($code >= 400) {
            error_log(sprintf('[DGPTM Finanzen CRM] COQL HTTP %d | Query: %s | Body: %s', $code, substr($query, 0, 200), substr($raw, 0, 300)));
        }

        $body = json_decode($raw, true);
        return $body['data'] ?? [];
    }

    /* ============================================================ */
    /* COQL OR-Builder                                               */
    /* ============================================================ */

    /**
     * Baut eine OR-Klausel fuer Picklist-Felder.
     *
     * KRITISCH: Der COQL IN-Operator funktioniert nicht zuverlaessig
     * bei Picklist-Feldern (Contact_Status, Membership_Type).
     * Daher IMMER diese Methode verwenden.
     *
     * @param string $field  Feldname, z.B. 'Contact_Status'
     * @param array  $values Werte, z.B. ['Aktiv', 'Freigestellt']
     * @return string        z.B. "(Contact_Status = 'Aktiv' or Contact_Status = 'Freigestellt')"
     */
    private function build_or_clause(string $field, array $values): string {
        if (empty($values)) return '(1=1)';

        $parts = array_map(function ($val) use ($field) {
            $escaped = str_replace("'", "\\'", $val);
            return "{$field} = '{$escaped}'";
        }, $values);

        return '(' . implode(' or ', $parts) . ')';
    }

    /* ============================================================ */
    /* Mitglieder laden                                              */
    /* ============================================================ */

    /**
     * Mitglieder fuer Beitragslauf laden (3 COQL-Gruppen).
     */
    public function get_members_for_billing(int $year): array {
        $status_clause = $this->build_or_clause('Contact_Status', $this->config->allowed_statuses());
        $type_clause = $this->build_or_clause('Membership_Type', array_keys($this->config->membership_types()));

        $fields = 'id,Full_Name,First_Name,Last_Name,Email,Membership_Type,Membership_Number,Member_Since,letztesBeitragsjahr,Guthaben2,GoCardlessID,MandatID,Finance_ID,Contact_Status,Student_Status,Valid_Through,Freigestellt_bis,goCardlessPayment,last_invoice';

        // Gruppe 1: Bereits abgerechnet, Beitragsjahr < aktuelles Jahr
        $q1 = "SELECT {$fields} FROM Contacts WHERE Mitglied = true AND {$status_clause} AND {$type_clause} AND letztesBeitragsjahr < {$year} LIMIT 200";
        $group1 = $this->coql_query($q1);

        // Gruppe 2: Nie abgerechnet, aber Member_Since gesetzt
        $q2 = "SELECT {$fields} FROM Contacts WHERE Mitglied = true AND {$status_clause} AND {$type_clause} AND letztesBeitragsjahr is null AND Member_Since is not null LIMIT 200";
        $group2 = $this->coql_query($q2);

        // Gruppe 3: Nie abgerechnet, Member_Since auch leer
        $q3 = "SELECT {$fields} FROM Contacts WHERE Mitglied = true AND {$status_clause} AND {$type_clause} AND letztesBeitragsjahr is null AND Member_Since is null LIMIT 200";
        $group3 = $this->coql_query($q3);

        return array_merge($group1, $group2, $group3);
    }

    /* ============================================================ */
    /* Mitglieder-Statistiken                                        */
    /* ============================================================ */

    /**
     * Mitglieder-Statistiken: Anzahl nach Typ und Beitragsstatus.
     * Portiert aus finanzbericht.php mit build_or_clause().
     *
     * @return array [total_active, by_type, billing_status, timestamp]
     */
    public function get_member_stats(): array {
        $stats = [
            'total_active'   => 0,
            'by_type'        => [],
            'billing_status' => [],
            'timestamp'      => current_time('c'),
        ];

        $status_clause = $this->build_or_clause('Contact_Status', $this->config->allowed_statuses());

        // Abfrage 1: Mitglieder nach Typ
        $type_data = $this->coql_query(
            "SELECT COUNT(id) as cnt, Membership_Type FROM Contacts WHERE Mitglied = true AND {$status_clause} GROUP BY Membership_Type"
        );
        foreach ($type_data as $row) {
            $type = $row['Membership_Type'] ?? 'Unbekannt';
            $count = (int) ($row['cnt'] ?? 0);
            $stats['by_type'][$type] = $count;
            $stats['total_active'] += $count;
        }

        // Abfrage 2: Beitragslauf-Status (letztesBeitragsjahr)
        $current_year = (int) date('Y');
        $billing_data = $this->coql_query(
            "SELECT COUNT(id) as cnt, letztesBeitragsjahr FROM Contacts WHERE Mitglied = true AND {$status_clause} GROUP BY letztesBeitragsjahr"
        );

        $billed_current = 0;
        $billed_previous = 0;
        $never_billed = 0;

        foreach ($billing_data as $row) {
            $by = $row['letztesBeitragsjahr'] ?? null;
            $cnt = (int) ($row['cnt'] ?? 0);

            if ($by === null || $by === '' || $by === 'null') {
                $never_billed += $cnt;
            } elseif ((int) $by >= $current_year) {
                $billed_current += $cnt;
            } else {
                $billed_previous += $cnt;
            }
        }

        $stats['billing_status'] = [
            'current_year'    => $current_year,
            'billed_current'  => $billed_current,
            'billed_previous' => $billed_previous,
            'never_billed'    => $never_billed,
            'pending'         => $stats['total_active'] - $billed_current,
        ];

        return $stats;
    }

    /* ============================================================ */
    /* Einzelne Kontakte                                             */
    /* ============================================================ */

    /**
     * Einzelnen Kontakt laden.
     */
    public function get_contact(string $contact_id): ?array {
        $result = $this->api_request('Contacts/' . $contact_id);
        return $result['data'][0] ?? null;
    }

    /**
     * Kontakt aktualisieren.
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

    /**
     * CRM-Variable lesen (cached 1 Tag).
     */
    public function get_variable(string $name): ?float {
        $cached = get_transient('dgptm_fin_crm_var_' . $name);
        if (false !== $cached) return (float) $cached;

        $result = $this->api_request('settings/variables?group=beitraege');
        if (!$result || empty($result['variables'])) return null;

        foreach ($result['variables'] as $var) {
            if (($var['api_name'] ?? '') === $name) {
                $value = (float) $var['value'];
                set_transient('dgptm_fin_crm_var_' . $name, $value, DAY_IN_SECONDS);
                return $value;
            }
        }
        return null;
    }

    /**
     * Alle Beitragshoehen laden (Config + CRM-Variablen).
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

    /**
     * Blueprint-Transition anhand des Namens finden und ausloesen.
     */
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
    /* Mitglieder-Liste (Finanzen-Tab)                               */
    /* ============================================================ */

    /**
     * Mitglieder fuer den Mitglieder-Tab laden.
     *
     * @param array $filters Optional: type, status, billing_status
     * @return array Kontakte mit abrechnungsrelevanten Feldern
     */
    public function get_member_list(array $filters = []): array {
        $fields = 'id,Full_Name,First_Name,Last_Name,Email,Membership_Type,Membership_Number,Member_Since,letztesBeitragsjahr,Guthaben2,GoCardlessID,MandatID,Finance_ID,Contact_Status,Student_Status,goCardlessPayment';

        $where = ['Mitglied = true'];

        // Filter: Mitgliedstyp
        if (!empty($filters['type'])) {
            $types = is_array($filters['type']) ? $filters['type'] : [$filters['type']];
            $where[] = $this->build_or_clause('Membership_Type', $types);
        } else {
            $where[] = $this->build_or_clause('Membership_Type', array_keys($this->config->membership_types()));
        }

        // Filter: Status
        if (!empty($filters['status'])) {
            $statuses = is_array($filters['status']) ? $filters['status'] : [$filters['status']];
            $where[] = $this->build_or_clause('Contact_Status', $statuses);
        } else {
            $where[] = $this->build_or_clause('Contact_Status', $this->config->allowed_statuses());
        }

        // Filter: Beitragsstatus
        if (!empty($filters['billing_status'])) {
            $current_year = (int) date('Y');
            switch ($filters['billing_status']) {
                case 'billed':
                    $where[] = "letztesBeitragsjahr >= {$current_year}";
                    break;
                case 'pending':
                    $where[] = "(letztesBeitragsjahr < {$current_year} or letztesBeitragsjahr is null)";
                    break;
                case 'never':
                    $where[] = 'letztesBeitragsjahr is null';
                    break;
            }
        }

        $query = "SELECT {$fields} FROM Contacts WHERE " . implode(' AND ', $where) . " LIMIT 200";
        return $this->coql_query($query);
    }

    /* ============================================================ */
    /* Alle Mitglieder mit Finance_ID                                */
    /* ============================================================ */

    /**
     * Alle aktiven Mitglieder mit Finance_ID laden (fuer CRM-Lookup im Rechnungs-Tab).
     *
     * @return array Kontakte mit id, Full_Name, Finance_ID, Membership_Type
     */
    public function get_all_members_with_finance_id(): array {
        $status_clause = $this->build_or_clause('Contact_Status', $this->config->allowed_statuses());
        $type_clause = $this->build_or_clause('Membership_Type', array_keys($this->config->membership_types()));

        $fields = 'id,Full_Name,First_Name,Last_Name,Email,Finance_ID,Membership_Type,Membership_Number';

        $query = "SELECT {$fields} FROM Contacts WHERE Mitglied = true AND {$status_clause} AND {$type_clause} AND Finance_ID is not null LIMIT 200";
        return $this->coql_query($query);
    }
}
