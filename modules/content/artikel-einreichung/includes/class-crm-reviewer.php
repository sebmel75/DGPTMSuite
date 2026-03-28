<?php
/**
 * Zoho CRM Integration fuer Reviewer-Management
 * Sucht/erstellt Contacts im CRM, verknuepft mit WP-Usern
 */
if (!defined('ABSPATH')) exit;

class DGPTM_CRM_Reviewer {

    public static function is_available() {
        return class_exists('DGPTM_Zoho_Plugin') && method_exists('DGPTM_Zoho_Plugin', 'get_instance');
    }

    private static function get_token() {
        if (!self::is_available()) return null;
        $plugin = DGPTM_Zoho_Plugin::get_instance();
        $token = $plugin->get_oauth_token();
        if (is_wp_error($token) || empty($token)) return null;
        return $token;
    }

    public static function search_by_email($email) {
        $token = self::get_token();
        if (!$token) return null;

        $url = 'https://www.zohoapis.eu/crm/v7/Contacts/search?email=' . urlencode($email);
        $response = dgptm_safe_remote('GET', $url, [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Accept' => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) return null;
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['data'][0])) {
            $c = $body['data'][0];
            return [
                'zoho_id' => $c['id'],
                'first_name' => $c['First_Name'] ?? '',
                'last_name' => $c['Last_Name'] ?? '',
                'email' => $c['Email'] ?? '',
                'institution' => $c['Department'] ?? '',
            ];
        }

        return null;
    }

    public static function search_by_name($name) {
        $token = self::get_token();
        if (!$token) return [];

        $url = 'https://www.zohoapis.eu/crm/v7/Contacts/search?word=' . urlencode($name);
        $response = dgptm_safe_remote('GET', $url, [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Accept' => 'application/json',
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) return [];
        $body = json_decode(wp_remote_retrieve_body($response), true);

        $results = [];
        if (!empty($body['data']) && is_array($body['data'])) {
            foreach (array_slice($body['data'], 0, 10) as $c) {
                $results[] = [
                    'zoho_id' => $c['id'],
                    'first_name' => $c['First_Name'] ?? '',
                    'last_name' => $c['Last_Name'] ?? '',
                    'email' => $c['Email'] ?? '',
                    'institution' => $c['Department'] ?? '',
                ];
            }
        }

        return $results;
    }

    public static function create_contact($first_name, $last_name, $email, $institution = '') {
        $token = self::get_token();
        if (!$token) return null;

        $data = [
            'data' => [[
                'First_Name' => $first_name,
                'Last_Name' => $last_name,
                'Email' => $email,
                'Department' => $institution,
                'Tag' => [['name' => 'Reviewer']],
            ]]
        ];

        $response = dgptm_safe_remote('POST', 'https://www.zohoapis.eu/crm/v7/Contacts', [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data),
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) return null;
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (!empty($body['data'][0]['details']['id'])) {
            return $body['data'][0]['details']['id'];
        }

        return null;
    }

    public static function link_user($user_id, $zoho_id) {
        update_user_meta($user_id, 'zoho_id', $zoho_id);
    }
}
