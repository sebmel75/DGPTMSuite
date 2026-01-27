<?php
/**
 * DGPTM Artikel-Einreichung - SharePoint Client
 * Microsoft Graph API integration for SharePoint file storage
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Artikel_SharePoint_Client {

    /**
     * @var string Tenant ID
     */
    private $tenant_id;

    /**
     * @var string Client ID
     */
    private $client_id;

    /**
     * @var string Client Secret
     */
    private $client_secret;

    /**
     * @var string SharePoint site URL
     */
    private $site_url;

    /**
     * @var string Cached access token
     */
    private $access_token;

    /**
     * @var int Token expiration timestamp
     */
    private $token_expires;

    /**
     * @var string SharePoint Site ID
     */
    private $site_id;

    /**
     * @var string SharePoint Drive ID
     */
    private $drive_id;

    /**
     * Cache key prefix
     */
    const CACHE_PREFIX = 'dgptm_artikel_sp_';

    /**
     * Large file threshold (4 MB)
     */
    const LARGE_FILE_THRESHOLD = 4194304;

    /**
     * Upload chunk size (10 MB)
     */
    const CHUNK_SIZE = 10485760;

    /**
     * Constructor
     */
    public function __construct() {
        $settings = $this->get_settings();
        $this->tenant_id = $settings['tenant_id'] ?? '';
        $this->client_id = $settings['client_id'] ?? '';
        $this->client_secret = $settings['client_secret'] ?? '';
        $this->site_url = $settings['site_url'] ?? '';
    }

    /**
     * Get SharePoint settings
     *
     * @return array
     */
    public function get_settings() {
        $defaults = array(
            'tenant_id' => '',
            'client_id' => '',
            'client_secret' => '',
            'site_url' => '',
            'base_folder' => 'Zeitschrift Perfusiologie',
        );
        $settings = get_option('dgptm_artikel_sharepoint_settings', array());
        return wp_parse_args($settings, $defaults);
    }

    /**
     * Check if SharePoint is configured
     *
     * @return bool
     */
    public function is_configured() {
        return !empty($this->tenant_id) &&
               !empty($this->client_id) &&
               !empty($this->client_secret) &&
               !empty($this->site_url);
    }

    /**
     * Get OAuth2 access token using client credentials flow
     *
     * @return string|WP_Error Access token or error
     */
    public function get_access_token() {
        // Check cached token
        $cached_token = get_transient(self::CACHE_PREFIX . 'access_token');
        if ($cached_token) {
            return $cached_token;
        }

        if (!$this->is_configured()) {
            return new WP_Error('sharepoint_not_configured', __('SharePoint ist nicht konfiguriert.', 'dgptm-artikel-einreichung'));
        }

        $token_url = "https://login.microsoftonline.com/{$this->tenant_id}/oauth2/v2.0/token";

        $response = wp_remote_post($token_url, array(
            'body' => array(
                'grant_type' => 'client_credentials',
                'client_id' => $this->client_id,
                'client_secret' => $this->client_secret,
                'scope' => 'https://graph.microsoft.com/.default',
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($body['error'])) {
            return new WP_Error(
                'sharepoint_auth_error',
                sprintf(__('SharePoint Authentifizierung fehlgeschlagen: %s', 'dgptm-artikel-einreichung'), $body['error_description'] ?? $body['error'])
            );
        }

        if (!isset($body['access_token'])) {
            return new WP_Error('sharepoint_no_token', __('Kein Access Token erhalten.', 'dgptm-artikel-einreichung'));
        }

        $this->access_token = $body['access_token'];
        $expires_in = isset($body['expires_in']) ? intval($body['expires_in']) - 300 : 3300;

        // Cache token
        set_transient(self::CACHE_PREFIX . 'access_token', $this->access_token, $expires_in);

        return $this->access_token;
    }

    /**
     * Get Site ID from site URL
     *
     * @return string|WP_Error Site ID or error
     */
    public function get_site_id() {
        $cached_site_id = get_transient(self::CACHE_PREFIX . 'site_id');
        if ($cached_site_id) {
            return $cached_site_id;
        }

        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $parsed = wp_parse_url($this->site_url);
        $hostname = $parsed['host'] ?? '';
        $path = trim($parsed['path'] ?? '', '/');

        if (empty($hostname)) {
            return new WP_Error('sharepoint_invalid_url', __('Ungültige SharePoint URL.', 'dgptm-artikel-einreichung'));
        }

        $api_url = "https://graph.microsoft.com/v1.0/sites/{$hostname}:/{$path}";

        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            return new WP_Error(
                'sharepoint_site_error',
                sprintf(__('SharePoint Site nicht gefunden: %s', 'dgptm-artikel-einreichung'), $body['error']['message'] ?? 'Unknown error')
            );
        }

        $this->site_id = $body['id'];
        set_transient(self::CACHE_PREFIX . 'site_id', $this->site_id, DAY_IN_SECONDS);

        return $this->site_id;
    }

    /**
     * Get default document library drive ID
     *
     * @return string|WP_Error Drive ID or error
     */
    public function get_drive_id() {
        $cached_drive_id = get_transient(self::CACHE_PREFIX . 'drive_id');
        if ($cached_drive_id) {
            return $cached_drive_id;
        }

        $site_id = $this->get_site_id();
        if (is_wp_error($site_id)) {
            return $site_id;
        }

        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $api_url = "https://graph.microsoft.com/v1.0/sites/{$site_id}/drive";

        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            return new WP_Error(
                'sharepoint_drive_error',
                sprintf(__('SharePoint Drive nicht gefunden: %s', 'dgptm-artikel-einreichung'), $body['error']['message'] ?? 'Unknown error')
            );
        }

        $this->drive_id = $body['id'];
        set_transient(self::CACHE_PREFIX . 'drive_id', $this->drive_id, DAY_IN_SECONDS);

        return $this->drive_id;
    }

    /**
     * Ensure a folder path exists, creating folders as needed
     *
     * @param string $folder_path Path like "Jahr/Einreichungen/Nachname"
     * @return string|WP_Error Folder item ID or error
     */
    public function ensure_folder_exists($folder_path) {
        $drive_id = $this->get_drive_id();
        if (is_wp_error($drive_id)) {
            return $drive_id;
        }

        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $folder_path = trim($folder_path, '/');
        $parts = explode('/', $folder_path);
        $current_path = '';

        foreach ($parts as $folder_name) {
            if (empty($folder_name)) {
                continue;
            }

            $parent_path = $current_path;
            $current_path = $current_path ? $current_path . '/' . $folder_name : $folder_name;

            $check_url = "https://graph.microsoft.com/v1.0/drives/{$drive_id}/root:/{$current_path}";

            $response = wp_remote_get($check_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                ),
                'timeout' => 30,
            ));

            $code = wp_remote_retrieve_response_code($response);

            if ($code === 200) {
                continue;
            }

            if ($parent_path) {
                $create_url = "https://graph.microsoft.com/v1.0/drives/{$drive_id}/root:/{$parent_path}:/children";
            } else {
                $create_url = "https://graph.microsoft.com/v1.0/drives/{$drive_id}/root/children";
            }

            $create_response = wp_remote_post($create_url, array(
                'headers' => array(
                    'Authorization' => 'Bearer ' . $token,
                    'Content-Type' => 'application/json',
                ),
                'body' => wp_json_encode(array(
                    'name' => $folder_name,
                    'folder' => new stdClass(),
                    '@microsoft.graph.conflictBehavior' => 'fail',
                )),
                'timeout' => 30,
            ));

            if (is_wp_error($create_response)) {
                return $create_response;
            }

            $create_code = wp_remote_retrieve_response_code($create_response);
            $create_body = json_decode(wp_remote_retrieve_body($create_response), true);

            if ($create_code !== 201 && $create_code !== 409) {
                return new WP_Error(
                    'sharepoint_folder_error',
                    sprintf(__('Ordner konnte nicht erstellt werden: %s', 'dgptm-artikel-einreichung'), $create_body['error']['message'] ?? 'Unknown error')
                );
            }
        }

        $final_url = "https://graph.microsoft.com/v1.0/drives/{$drive_id}/root:/{$folder_path}";
        $final_response = wp_remote_get($final_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($final_response)) {
            return $final_response;
        }

        $final_body = json_decode(wp_remote_retrieve_body($final_response), true);

        return $final_body['id'] ?? '';
    }

    /**
     * Upload a file to SharePoint
     *
     * @param string $local_path Local file path
     * @param string $remote_path Remote path (folder/filename.ext)
     * @return array|WP_Error Upload result with id, webUrl, etc. or error
     */
    public function upload_file($local_path, $remote_path) {
        if (!file_exists($local_path)) {
            return new WP_Error('file_not_found', __('Lokale Datei nicht gefunden.', 'dgptm-artikel-einreichung'));
        }

        $file_size = filesize($local_path);

        if ($file_size <= self::LARGE_FILE_THRESHOLD) {
            return $this->upload_small_file($local_path, $remote_path);
        } else {
            return $this->upload_large_file($local_path, $remote_path);
        }
    }

    /**
     * Upload small file (< 4MB) directly
     */
    private function upload_small_file($local_path, $remote_path) {
        $drive_id = $this->get_drive_id();
        if (is_wp_error($drive_id)) {
            return $drive_id;
        }

        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $folder_path = dirname($remote_path);
        if ($folder_path !== '.') {
            $folder_result = $this->ensure_folder_exists($folder_path);
            if (is_wp_error($folder_result)) {
                return $folder_result;
            }
        }

        $remote_path = trim($remote_path, '/');
        $api_url = "https://graph.microsoft.com/v1.0/drives/{$drive_id}/root:/{$remote_path}:/content";

        $file_contents = file_get_contents($local_path);
        $mime_type = mime_content_type($local_path);

        $response = wp_remote_request($api_url, array(
            'method' => 'PUT',
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => $mime_type,
            ),
            'body' => $file_contents,
            'timeout' => 120,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 && $code !== 201) {
            return new WP_Error(
                'sharepoint_upload_error',
                sprintf(__('Upload fehlgeschlagen: %s', 'dgptm-artikel-einreichung'), $body['error']['message'] ?? 'Unknown error')
            );
        }

        return array(
            'id' => $body['id'],
            'name' => $body['name'],
            'webUrl' => $body['webUrl'],
            'size' => $body['size'],
            '@microsoft.graph.downloadUrl' => $body['@microsoft.graph.downloadUrl'] ?? '',
        );
    }

    /**
     * Upload large file (>= 4MB) using upload session
     */
    private function upload_large_file($local_path, $remote_path) {
        $drive_id = $this->get_drive_id();
        if (is_wp_error($drive_id)) {
            return $drive_id;
        }

        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $folder_path = dirname($remote_path);
        if ($folder_path !== '.') {
            $folder_result = $this->ensure_folder_exists($folder_path);
            if (is_wp_error($folder_result)) {
                return $folder_result;
            }
        }

        $remote_path = trim($remote_path, '/');
        $file_size = filesize($local_path);

        $session_url = "https://graph.microsoft.com/v1.0/drives/{$drive_id}/root:/{$remote_path}:/createUploadSession";

        $session_response = wp_remote_post($session_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'item' => array(
                    '@microsoft.graph.conflictBehavior' => 'replace',
                ),
            )),
            'timeout' => 60,
        ));

        if (is_wp_error($session_response)) {
            return $session_response;
        }

        $session_body = json_decode(wp_remote_retrieve_body($session_response), true);

        if (!isset($session_body['uploadUrl'])) {
            return new WP_Error('sharepoint_session_error', __('Upload-Session konnte nicht erstellt werden.', 'dgptm-artikel-einreichung'));
        }

        $upload_url = $session_body['uploadUrl'];

        $handle = fopen($local_path, 'rb');
        $offset = 0;
        $result = null;

        while (!feof($handle)) {
            $chunk = fread($handle, self::CHUNK_SIZE);
            $chunk_size = strlen($chunk);
            $end = $offset + $chunk_size - 1;

            $chunk_response = wp_remote_request($upload_url, array(
                'method' => 'PUT',
                'headers' => array(
                    'Content-Length' => $chunk_size,
                    'Content-Range' => "bytes {$offset}-{$end}/{$file_size}",
                ),
                'body' => $chunk,
                'timeout' => 300,
            ));

            if (is_wp_error($chunk_response)) {
                fclose($handle);
                return $chunk_response;
            }

            $code = wp_remote_retrieve_response_code($chunk_response);
            $body = json_decode(wp_remote_retrieve_body($chunk_response), true);

            if ($code === 200 || $code === 201) {
                $result = array(
                    'id' => $body['id'],
                    'name' => $body['name'],
                    'webUrl' => $body['webUrl'],
                    'size' => $body['size'],
                    '@microsoft.graph.downloadUrl' => $body['@microsoft.graph.downloadUrl'] ?? '',
                );
                break;
            } elseif ($code === 202) {
                $offset += $chunk_size;
            } else {
                fclose($handle);
                return new WP_Error(
                    'sharepoint_chunk_error',
                    sprintf(__('Chunk-Upload fehlgeschlagen: %s', 'dgptm-artikel-einreichung'), $body['error']['message'] ?? 'Unknown error')
                );
            }
        }

        fclose($handle);

        if (!$result) {
            return new WP_Error('sharepoint_upload_incomplete', __('Upload wurde nicht abgeschlossen.', 'dgptm-artikel-einreichung'));
        }

        return $result;
    }

    /**
     * Delete a file from SharePoint
     */
    public function delete_file($remote_path) {
        $drive_id = $this->get_drive_id();
        if (is_wp_error($drive_id)) {
            return $drive_id;
        }

        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        if (strpos($remote_path, '/') !== false) {
            $remote_path = trim($remote_path, '/');
            $api_url = "https://graph.microsoft.com/v1.0/drives/{$drive_id}/root:/{$remote_path}";
        } else {
            $api_url = "https://graph.microsoft.com/v1.0/drives/{$drive_id}/items/{$remote_path}";
        }

        $response = wp_remote_request($api_url, array(
            'method' => 'DELETE',
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code === 204 || $code === 200) {
            return true;
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        return new WP_Error(
            'sharepoint_delete_error',
            sprintf(__('Löschen fehlgeschlagen: %s', 'dgptm-artikel-einreichung'), $body['error']['message'] ?? 'Unknown error')
        );
    }

    /**
     * Get a sharing link for file download
     */
    public function get_download_url($item_id, $type = 'view', $scope = 'organization') {
        $drive_id = $this->get_drive_id();
        if (is_wp_error($drive_id)) {
            return $drive_id;
        }

        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $item_url = "https://graph.microsoft.com/v1.0/drives/{$drive_id}/items/{$item_id}";
        $item_response = wp_remote_get($item_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
            'timeout' => 30,
        ));

        if (!is_wp_error($item_response)) {
            $item_body = json_decode(wp_remote_retrieve_body($item_response), true);
            if (isset($item_body['@microsoft.graph.downloadUrl'])) {
                return $item_body['@microsoft.graph.downloadUrl'];
            }
        }

        $api_url = "https://graph.microsoft.com/v1.0/drives/{$drive_id}/items/{$item_id}/createLink";

        $response = wp_remote_post($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json',
            ),
            'body' => wp_json_encode(array(
                'type' => $type,
                'scope' => $scope,
            )),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200 && $code !== 201) {
            return new WP_Error(
                'sharepoint_link_error',
                sprintf(__('Download-Link konnte nicht erstellt werden: %s', 'dgptm-artikel-einreichung'), $body['error']['message'] ?? 'Unknown error')
            );
        }

        return $body['link']['webUrl'] ?? '';
    }

    /**
     * Get file info by path
     */
    public function get_file_info($remote_path) {
        $drive_id = $this->get_drive_id();
        if (is_wp_error($drive_id)) {
            return $drive_id;
        }

        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $remote_path = trim($remote_path, '/');
        $api_url = "https://graph.microsoft.com/v1.0/drives/{$drive_id}/root:/{$remote_path}";

        $response = wp_remote_get($api_url, array(
            'headers' => array(
                'Authorization' => 'Bearer ' . $token,
            ),
            'timeout' => 30,
        ));

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code !== 200) {
            return new WP_Error(
                'sharepoint_file_not_found',
                sprintf(__('Datei nicht gefunden: %s', 'dgptm-artikel-einreichung'), $body['error']['message'] ?? 'Unknown error')
            );
        }

        return array(
            'id' => $body['id'],
            'name' => $body['name'],
            'webUrl' => $body['webUrl'],
            'size' => $body['size'],
            'createdDateTime' => $body['createdDateTime'],
            'lastModifiedDateTime' => $body['lastModifiedDateTime'],
            '@microsoft.graph.downloadUrl' => $body['@microsoft.graph.downloadUrl'] ?? '',
        );
    }

    /**
     * Test connection to SharePoint
     */
    public function test_connection() {
        if (!$this->is_configured()) {
            return new WP_Error('sharepoint_not_configured', __('SharePoint ist nicht konfiguriert.', 'dgptm-artikel-einreichung'));
        }

        delete_transient(self::CACHE_PREFIX . 'access_token');
        delete_transient(self::CACHE_PREFIX . 'site_id');
        delete_transient(self::CACHE_PREFIX . 'drive_id');

        $token = $this->get_access_token();
        if (is_wp_error($token)) {
            return $token;
        }

        $site_id = $this->get_site_id();
        if (is_wp_error($site_id)) {
            return $site_id;
        }

        $drive_id = $this->get_drive_id();
        if (is_wp_error($drive_id)) {
            return $drive_id;
        }

        return array(
            'success' => true,
            'message' => __('Verbindung erfolgreich!', 'dgptm-artikel-einreichung'),
            'site_id' => $site_id,
            'drive_id' => $drive_id,
        );
    }

    /**
     * Clear all cached SharePoint data
     */
    public function clear_cache() {
        delete_transient(self::CACHE_PREFIX . 'access_token');
        delete_transient(self::CACHE_PREFIX . 'site_id');
        delete_transient(self::CACHE_PREFIX . 'drive_id');
    }
}
