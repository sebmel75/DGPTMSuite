<?php
/**
 * Enhanced CRM Cache - Longer-lived transient cache for dashboard CRM data
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Dashboard_CRM_Cache {

    const TRANSIENT_PREFIX = 'dgptm_dash_crm_';
    const DEFAULT_TTL = 900; // 15 minutes

    private $config;
    private $memory_cache = [];

    public function __construct(DGPTM_Dashboard_Config $config) {
        $this->config = $config;
    }

    /**
     * Get all CRM data for a user
     */
    public function get_user_data($user_id) {
        if (isset($this->memory_cache[$user_id])) {
            return $this->memory_cache[$user_id];
        }

        $transient_key = self::TRANSIENT_PREFIX . $user_id;
        $cached = get_transient($transient_key);

        if ($cached !== false && is_array($cached)) {
            $this->memory_cache[$user_id] = $cached;
            return $cached;
        }

        // Fetch fresh data
        $data = $this->fetch_crm_data($user_id);

        if (!empty($data)) {
            $ttl = $this->config->get_setting('cache_ttl', self::DEFAULT_TTL);
            set_transient($transient_key, $data, $ttl);
            $this->memory_cache[$user_id] = $data;
        }

        return $data;
    }

    /**
     * Get a single field value
     */
    public function get_field($user_id, $field_name) {
        $data = $this->get_user_data($user_id);
        return $data[$field_name] ?? '';
    }

    /**
     * Force-refresh CRM data
     */
    public function refresh($user_id) {
        // Delete dashboard cache
        delete_transient(self::TRANSIENT_PREFIX . $user_id);

        // Also delete the CRM module's short-lived cache
        delete_transient('dgptm_zoho_data_' . $user_id);

        unset($this->memory_cache[$user_id]);

        return $this->get_user_data($user_id);
    }

    /**
     * Get cache age in seconds (0 if not cached)
     */
    public function get_cache_age($user_id) {
        $meta_key = '_dgptm_dash_crm_time_' . $user_id;
        $cached_at = get_option($meta_key, 0);

        if (!$cached_at) {
            return 0;
        }

        return time() - $cached_at;
    }

    /**
     * Fetch CRM data from the crm-abruf module
     */
    private function fetch_crm_data($user_id) {
        $zoho_id = get_user_meta($user_id, 'zoho_id', true);

        if (empty($zoho_id)) {
            return $this->get_fallback_data($user_id);
        }

        // Try to use the CRM module's existing method
        if (class_exists('DGPTM_Zoho_CRM_Integration')) {
            $crm = DGPTM_Zoho_CRM_Integration::get_instance();

            // Check if the CRM module has cached data
            $existing = get_transient('dgptm_zoho_data_' . $user_id);
            if ($existing && is_array($existing)) {
                $this->record_cache_time($user_id);
                return $existing;
            }

            // Fetch via CRM module's internal API
            if (method_exists($crm, 'fetch_zoho_data_for_user')) {
                $data = $crm->fetch_zoho_data_for_user($user_id);
                if (!empty($data) && is_array($data)) {
                    $this->record_cache_time($user_id);
                    return $data;
                }
            }
        }

        // Direct Zoho CRM API call as fallback
        $data = $this->fetch_direct($zoho_id, $user_id);

        if (!empty($data)) {
            $this->record_cache_time($user_id);
        }

        return $data ?: $this->get_fallback_data($user_id);
    }

    /**
     * Direct Zoho API call if CRM module method unavailable
     */
    private function fetch_direct($zoho_id, $user_id) {
        $api_url = get_option('dgptm_zoho_api_url', '');
        $token   = get_option('dgptm_zoho_access_token', '');

        if (empty($api_url) || empty($token)) {
            return [];
        }

        $url = add_query_arg([
            'cid'  => $zoho_id,
            'wpid' => $user_id,
        ], $api_url);

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'Zoho-oauthtoken ' . $token,
            ],
            'timeout' => 15,
        ]);

        if (is_wp_error($response)) {
            return [];
        }

        $body = wp_remote_retrieve_body($response);
        $json = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($json)) {
            return [];
        }

        // Extract data from various response formats
        if (isset($json['details']['output'])) {
            return $json['details']['output'];
        }
        if (isset($json['data']['output'])) {
            return $json['data']['output'];
        }
        if (isset($json['data']) && is_array($json['data'])) {
            return $json['data'];
        }

        return $json;
    }

    /**
     * Fallback data from WordPress user meta
     */
    private function get_fallback_data($user_id) {
        $user = get_userdata($user_id);
        if (!$user) {
            return [];
        }

        return [
            'Vorname'  => $user->first_name,
            'Nachname' => $user->last_name,
            'Mail1'    => $user->user_email,
            '_source'  => 'wordpress_fallback',
        ];
    }

    private function record_cache_time($user_id) {
        update_option('_dgptm_dash_crm_time_' . $user_id, time(), false);
    }
}
