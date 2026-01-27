<?php
/**
 * DGPTM Artikel-Einreichung - SharePoint Uploader
 * Handles file uploads to SharePoint with versioning
 *
 * Folder Structure:
 * Zeitschrift Perfusiologie/{Jahr}/Einreichungen/{Autor}/{YYYY-MM-DD}_{Dateiname}_V{Version}.ext
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Artikel_SharePoint_Uploader {

    /**
     * @var DGPTM_Artikel_SharePoint_Client
     */
    private $client;

    /**
     * Version types matching workflow steps
     */
    const TYPE_EINREICHUNG = 'einreichung';
    const TYPE_GUTACHTEN = 'gutachten';
    const TYPE_REVISION = 'revision';
    const TYPE_LEKTORAT = 'lektorat';
    const TYPE_GESETZT = 'gesetzt';
    const TYPE_FINAL = 'final';

    /**
     * Constructor
     */
    public function __construct() {
        $this->client = new DGPTM_Artikel_SharePoint_Client();
    }

    /**
     * Get SharePoint client
     */
    public function get_client() {
        return $this->client;
    }

    /**
     * Check if SharePoint is available for use
     */
    public function is_available() {
        return $this->client->is_configured();
    }

    /**
     * Upload an article file to SharePoint
     *
     * @param int    $artikel_id Article post ID
     * @param array  $file       File array from $_FILES or array with 'tmp_name', 'name'
     * @param string $version_type Type: einreichung, gutachten, revision, lektorat, gesetzt, final
     * @param array  $options    Additional options (notes, uploaded_by, token_id, reviewer_name)
     * @return array|WP_Error    Version data or error
     */
    public function upload_artikel_file($artikel_id, $file, $version_type, $options = array()) {
        $artikel = get_post($artikel_id);
        if (!$artikel || $artikel->post_type !== DGPTM_Artikel_Einreichung::POST_TYPE) {
            return new WP_Error('invalid_artikel', __('Ungültiger Artikel.', 'dgptm-artikel-einreichung'));
        }

        // Validate file
        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return new WP_Error('file_missing', __('Keine Datei hochgeladen.', 'dgptm-artikel-einreichung'));
        }

        $original_filename = sanitize_file_name($file['name']);
        $file_size = filesize($file['tmp_name']);

        // Get next version number
        $version_number = $this->get_next_version_number($artikel_id, $version_type);

        // Build SharePoint path
        $remote_path = $this->build_sharepoint_path($artikel_id, $original_filename, $version_number, $version_type);

        // Upload to SharePoint
        $upload_result = $this->client->upload_file($file['tmp_name'], $remote_path);

        if (is_wp_error($upload_result)) {
            return $upload_result;
        }

        // Determine uploader info
        $uploaded_by = isset($options['uploaded_by']) ? intval($options['uploaded_by']) : get_current_user_id();
        $uploaded_by_name = isset($options['reviewer_name']) ? sanitize_text_field($options['reviewer_name']) : '';

        if (!$uploaded_by_name && $uploaded_by > 0) {
            $user = get_userdata($uploaded_by);
            $uploaded_by_name = $user ? $user->display_name : '';
        }

        // Log version to database
        $version_data = array(
            'artikel_id' => $artikel_id,
            'version_number' => $version_number,
            'version_type' => $version_type,
            'original_filename' => $original_filename,
            'sharepoint_path' => $remote_path,
            'sharepoint_item_id' => $upload_result['id'],
            'sharepoint_web_url' => $upload_result['webUrl'],
            'file_size' => $file_size,
            'uploaded_by' => $uploaded_by,
            'uploaded_by_name' => $uploaded_by_name,
            'uploaded_via_token' => isset($options['token_id']) ? intval($options['token_id']) : null,
            'notes' => isset($options['notes']) ? sanitize_textarea_field($options['notes']) : '',
        );

        $version_id = $this->log_version($version_data);

        if (is_wp_error($version_id)) {
            return $version_id;
        }

        $version_data['id'] = $version_id;
        $version_data['uploaded_at'] = current_time('mysql');

        // Update article meta
        update_post_meta($artikel_id, '_dgptm_artikel_sp_path', $remote_path);
        update_post_meta($artikel_id, '_dgptm_artikel_sp_item_id', $upload_result['id']);
        update_post_meta($artikel_id, '_dgptm_artikel_last_upload', current_time('mysql'));

        return $version_data;
    }

    /**
     * Get the next version number for an article and type
     */
    public function get_next_version_number($artikel_id, $version_type) {
        global $wpdb;

        $table = $wpdb->prefix . 'dgptm_artikel_sp_versions';

        $max_version = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(version_number) FROM {$table}
             WHERE artikel_id = %d AND version_type = %s",
            $artikel_id,
            $version_type
        ));

        return $max_version ? intval($max_version) + 1 : 1;
    }

    /**
     * Build the SharePoint path for a file
     *
     * Folder structure:
     * Zeitschrift Perfusiologie/{Jahr}/Einreichungen/{Autor}/{YYYY-MM-DD}_{Originaldateiname}_V{Version}.ext
     */
    public function build_sharepoint_path($artikel_id, $filename, $version_number, $version_type) {
        $settings = $this->client->get_settings();
        $base_folder = $settings['base_folder'] ?: 'Zeitschrift Perfusiologie';

        $artikel = get_post($artikel_id);

        // Use submission date year if available, otherwise current year
        $submitted_at = get_field('submitted_at', $artikel_id);
        $year = $submitted_at ? date('Y', strtotime($submitted_at)) : date('Y');

        // Get author name - use hauptautorin field from ACF
        $author_name = get_field('hauptautorin', $artikel_id);
        if (empty($author_name)) {
            $author = get_userdata($artikel->post_author);
            $author_name = $author ? $author->display_name : 'Unbekannt';
        }

        // Extract lastname (assume format: "Vorname Nachname" or "Nachname, Vorname")
        $author_lastname = $this->extract_lastname($author_name);

        // Sanitize folder name
        $author_lastname = $this->sanitize_folder_name($author_lastname);

        // Build type folder name
        $type_folders = array(
            self::TYPE_EINREICHUNG => 'Einreichungen',
            self::TYPE_GUTACHTEN => 'Gutachten',
            self::TYPE_REVISION => 'Revisionen',
            self::TYPE_LEKTORAT => 'Lektorat',
            self::TYPE_GESETZT => 'Gesetzt',
            self::TYPE_FINAL => 'Final',
        );
        $type_folder = $type_folders[$version_type] ?? 'Sonstige';

        // Build filename with version
        $pathinfo = pathinfo($filename);
        $extension = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';
        $basename = $pathinfo['filename'];

        // Use submission date if available
        $date_prefix = $submitted_at ? date('Y-m-d', strtotime($submitted_at)) : date('Y-m-d');

        $new_filename = sprintf(
            '%s_%s_V%d%s',
            $date_prefix,
            $this->sanitize_folder_name($basename),
            $version_number,
            $extension
        );

        // Build full path: Zeitschrift Perfusiologie/{Jahr}/{Type}/{Autor}/{Dateiname}
        $path = sprintf(
            '%s/%s/%s/%s/%s',
            $base_folder,
            $year,
            $type_folder,
            $author_lastname,
            $new_filename
        );

        return $path;
    }

    /**
     * Extract lastname from full name
     */
    private function extract_lastname($full_name) {
        $full_name = trim($full_name);

        // Check if format is "Nachname, Vorname"
        if (strpos($full_name, ',') !== false) {
            $parts = explode(',', $full_name);
            return trim($parts[0]);
        }

        // Otherwise assume "Vorname Nachname"
        $parts = explode(' ', $full_name);
        return trim(end($parts));
    }

    /**
     * Sanitize a string for use as folder name
     */
    private function sanitize_folder_name($name) {
        // Replace German umlauts
        $replacements = array(
            'ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'ß' => 'ss',
            'Ä' => 'Ae', 'Ö' => 'Oe', 'Ü' => 'Ue',
        );
        $name = str_replace(array_keys($replacements), array_values($replacements), $name);

        // Remove special characters
        $name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $name);

        // Remove multiple underscores
        $name = preg_replace('/_+/', '_', $name);

        // Trim underscores
        $name = trim($name, '_');

        return $name ?: 'Unbekannt';
    }

    /**
     * Log a file version to the database
     */
    public function log_version($data) {
        global $wpdb;

        $table = $wpdb->prefix . 'dgptm_artikel_sp_versions';

        $insert_data = array(
            'artikel_id' => $data['artikel_id'],
            'version_number' => $data['version_number'],
            'version_type' => $data['version_type'],
            'original_filename' => $data['original_filename'],
            'sharepoint_path' => $data['sharepoint_path'],
            'sharepoint_item_id' => $data['sharepoint_item_id'] ?? null,
            'sharepoint_web_url' => $data['sharepoint_web_url'] ?? null,
            'file_size' => $data['file_size'] ?? null,
            'uploaded_by' => $data['uploaded_by'] ?? null,
            'uploaded_by_name' => $data['uploaded_by_name'] ?? null,
            'uploaded_via_token' => $data['uploaded_via_token'] ?? null,
            'uploaded_at' => current_time('mysql'),
            'notes' => $data['notes'] ?? null,
        );

        $result = $wpdb->insert($table, $insert_data);

        if ($result === false) {
            return new WP_Error('db_error', __('Version konnte nicht gespeichert werden.', 'dgptm-artikel-einreichung'));
        }

        return $wpdb->insert_id;
    }

    /**
     * Get all versions for an article
     */
    public function get_versions($artikel_id, $version_type = null) {
        global $wpdb;

        $table = $wpdb->prefix . 'dgptm_artikel_sp_versions';

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE artikel_id = %d",
            $artikel_id
        );

        if ($version_type) {
            $sql .= $wpdb->prepare(" AND version_type = %s", $version_type);
        }

        $sql .= " ORDER BY version_type, version_number DESC";

        return $wpdb->get_results($sql, ARRAY_A) ?: array();
    }

    /**
     * Get the current (latest) version for an article
     */
    public function get_current_version($artikel_id, $version_type = null) {
        global $wpdb;

        $table = $wpdb->prefix . 'dgptm_artikel_sp_versions';

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE artikel_id = %d",
            $artikel_id
        );

        if ($version_type) {
            $sql .= $wpdb->prepare(" AND version_type = %s", $version_type);
        }

        $sql .= " ORDER BY uploaded_at DESC LIMIT 1";

        return $wpdb->get_row($sql, ARRAY_A);
    }

    /**
     * Get a specific version by ID
     */
    public function get_version($version_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'dgptm_artikel_sp_versions';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $version_id),
            ARRAY_A
        );
    }

    /**
     * Get download URL for a version
     */
    public function get_version_download_url($version_id) {
        $version = $this->get_version($version_id);

        if (!$version) {
            return new WP_Error('version_not_found', __('Version nicht gefunden.', 'dgptm-artikel-einreichung'));
        }

        if (empty($version['sharepoint_item_id'])) {
            return new WP_Error('no_item_id', __('SharePoint Item-ID fehlt.', 'dgptm-artikel-einreichung'));
        }

        return $this->client->get_download_url($version['sharepoint_item_id']);
    }

    /**
     * Delete a version from SharePoint and database
     */
    public function delete_version($version_id) {
        global $wpdb;

        $version = $this->get_version($version_id);

        if (!$version) {
            return new WP_Error('version_not_found', __('Version nicht gefunden.', 'dgptm-artikel-einreichung'));
        }

        // Delete from SharePoint
        if (!empty($version['sharepoint_item_id'])) {
            $delete_result = $this->client->delete_file($version['sharepoint_item_id']);
            if (is_wp_error($delete_result)) {
                error_log('SharePoint delete failed: ' . $delete_result->get_error_message());
            }
        }

        // Delete from database
        $table = $wpdb->prefix . 'dgptm_artikel_sp_versions';
        $result = $wpdb->delete($table, array('id' => $version_id), array('%d'));

        if ($result === false) {
            return new WP_Error('db_error', __('Version konnte nicht gelöscht werden.', 'dgptm-artikel-einreichung'));
        }

        return true;
    }

    /**
     * Get version statistics for an article
     */
    public function get_version_stats($artikel_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'dgptm_artikel_sp_versions';

        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT version_type, COUNT(*) as count, SUM(file_size) as total_size
             FROM {$table}
             WHERE artikel_id = %d
             GROUP BY version_type",
            $artikel_id
        ), ARRAY_A);

        $result = array(
            'by_type' => array(),
            'total_count' => 0,
            'total_size' => 0,
        );

        foreach ($stats as $stat) {
            $result['by_type'][$stat['version_type']] = array(
                'count' => intval($stat['count']),
                'size' => intval($stat['total_size']),
            );
            $result['total_count'] += intval($stat['count']);
            $result['total_size'] += intval($stat['total_size']);
        }

        return $result;
    }
}
