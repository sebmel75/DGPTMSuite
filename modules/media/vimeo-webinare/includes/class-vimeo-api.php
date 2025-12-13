<?php
/**
 * Vimeo API Client
 * Handles communication with Vimeo REST API
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Vimeo_API {

    private $access_token;
    private $api_base = 'https://api.vimeo.com';

    /**
     * Constructor
     *
     * @param string $access_token Vimeo Personal Access Token
     */
    public function __construct($access_token = null) {
        $this->access_token = $access_token ?: (function_exists('dgptm_vw_get_setting') ? dgptm_vw_get_setting('vimeo_webinar_api_token', '') : get_option('vimeo_webinar_api_token', ''));
    }

    /**
     * Make API request
     *
     * @param string $endpoint API endpoint (e.g., '/me/projects/12345/videos')
     * @param string $method HTTP method
     * @param array $params Request parameters
     * @return array|WP_Error
     */
    private function request($endpoint, $method = 'GET', $params = []) {
        if (empty($this->access_token)) {
            return new WP_Error('no_token', 'Kein Vimeo Access Token konfiguriert');
        }

        $url = $this->api_base . $endpoint;

        $args = [
            'method' => $method,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->access_token,
                'Content-Type' => 'application/json',
                'Accept' => 'application/vnd.vimeo.*+json;version=3.4'
            ],
            'timeout' => 30
        ];

        if ($method === 'GET' && !empty($params)) {
            $url = add_query_arg($params, $url);
        } elseif (!empty($params)) {
            $args['body'] = json_encode($params);
        }

        $response = wp_remote_request($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code >= 400) {
            $message = $data['error'] ?? 'API Request failed';
            return new WP_Error('api_error', $message, ['code' => $code]);
        }

        return $data;
    }

    /**
     * Get all videos from a folder/project
     *
     * @param string $folder_id Vimeo Folder/Project ID
     * @return array|WP_Error Array of videos or error
     */
    public function get_folder_videos($folder_id) {
        $all_videos = [];
        $page = 1;
        $per_page = 100;

        do {
            $params = [
                'page' => $page,
                'per_page' => $per_page,
                'fields' => 'uri,name,description,duration,created_time,pictures,link,stats'
            ];

            $response = $this->request("/me/projects/{$folder_id}/videos", 'GET', $params);

            if (is_wp_error($response)) {
                return $response;
            }

            if (isset($response['data'])) {
                $all_videos = array_merge($all_videos, $response['data']);
            }

            $total = $response['total'] ?? 0;
            $has_more = ($page * $per_page) < $total;

            $page++;
        } while ($has_more);

        return $all_videos;
    }

    /**
     * Get video details
     *
     * @param string $video_id Vimeo Video ID
     * @return array|WP_Error
     */
    public function get_video($video_id) {
        return $this->request("/videos/{$video_id}");
    }

    /**
     * Get user's folders/projects
     *
     * @return array|WP_Error
     */
    public function get_folders() {
        $all_folders = [];
        $page = 1;
        $per_page = 100;

        do {
            $params = [
                'page' => $page,
                'per_page' => $per_page,
                'fields' => 'uri,name,created_time,modified_time,metadata'
            ];

            $response = $this->request('/me/projects', 'GET', $params);

            if (is_wp_error($response)) {
                return $response;
            }

            if (isset($response['data'])) {
                $all_folders = array_merge($all_folders, $response['data']);
            }

            $total = $response['total'] ?? 0;
            $has_more = ($page * $per_page) < $total;

            $page++;
        } while ($has_more);

        return $all_folders;
    }

    /**
     * Extract video ID from Vimeo URL or URI
     *
     * @param string $url_or_uri Vimeo URL or URI
     * @return string|null Video ID
     */
    public static function extract_video_id($url_or_uri) {
        // URI format: /videos/123456789
        if (strpos($url_or_uri, '/videos/') !== false) {
            $parts = explode('/videos/', $url_or_uri);
            return trim($parts[1], '/');
        }

        // URL format: https://vimeo.com/123456789
        if (preg_match('/vimeo\.com\/(\d+)/', $url_or_uri, $matches)) {
            return $matches[1];
        }

        // Player URL: https://player.vimeo.com/video/123456789
        if (preg_match('/player\.vimeo\.com\/video\/(\d+)/', $url_or_uri, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Extract folder ID from Vimeo folder URI
     *
     * @param string $uri Folder URI (e.g., /users/12345/projects/67890)
     * @return string|null Folder ID
     */
    public static function extract_folder_id($uri) {
        if (preg_match('/\/projects\/(\d+)/', $uri, $matches)) {
            return $matches[1];
        }

        // If it's already just a number
        if (is_numeric($uri)) {
            return $uri;
        }

        return null;
    }

    /**
     * Test API connection
     *
     * @return array|WP_Error
     */
    public function test_connection() {
        return $this->request('/me');
    }
}
