<?php
/**
 * Publication Frontend Manager - SharePoint Uploader
 * Handles file uploads to SharePoint with versioning
 */

if (!defined('ABSPATH')) {
    exit;
}

class PFM_SharePoint_Uploader {

    /**
     * @var PFM_SharePoint_Client
     */
    private $client;

    /**
     * Version types
     */
    const TYPE_EINREICHUNG = 'einreichung';
    const TYPE_GUTACHTEN = 'gutachten';
    const TYPE_REVISION = 'revision';
    const TYPE_FINAL = 'final';

    /**
     * Constructor
     */
    public function __construct() {
        $this->client = new PFM_SharePoint_Client();
    }

    /**
     * Get SharePoint client
     *
     * @return PFM_SharePoint_Client
     */
    public function get_client() {
        return $this->client;
    }

    /**
     * Check if SharePoint is available for use
     *
     * @return bool
     */
    public function is_available() {
        return $this->client->is_configured();
    }

    /**
     * Upload a publication file to SharePoint
     *
     * @param int    $publication_id Publication post ID
     * @param array  $file           File array from $_FILES or array with 'tmp_name', 'name'
     * @param string $version_type   Type: einreichung, gutachten, revision, final
     * @param array  $options        Additional options (notes, uploaded_by, token_id, reviewer_name)
     * @return array|WP_Error        Version data or error
     */
    public function upload_publication_file($publication_id, $file, $version_type, $options = array()) {
        $publication = get_post($publication_id);
        if (!$publication || $publication->post_type !== 'publikation') {
            return new WP_Error('invalid_publication', __('Ungültige Publikation.', PFM_TD));
        }

        // Validate file
        if (!isset($file['tmp_name']) || !file_exists($file['tmp_name'])) {
            return new WP_Error('file_missing', __('Keine Datei hochgeladen.', PFM_TD));
        }

        $original_filename = sanitize_file_name($file['name']);
        $file_size = filesize($file['tmp_name']);

        // Get next version number
        $version_number = $this->get_next_version_number($publication_id, $version_type);

        // Build SharePoint path
        $remote_path = $this->build_sharepoint_path($publication_id, $original_filename, $version_number, $version_type);

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
            'publication_id' => $publication_id,
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

        // Update publication meta
        update_post_meta($publication_id, '_pfm_current_sp_path', $remote_path);
        update_post_meta($publication_id, '_pfm_current_sp_item_id', $upload_result['id']);
        update_post_meta($publication_id, '_pfm_last_upload', current_time('mysql'));

        return $version_data;
    }

    /**
     * Get the next version number for a publication and type
     *
     * @param int    $publication_id
     * @param string $version_type
     * @return int
     */
    public function get_next_version_number($publication_id, $version_type) {
        global $wpdb;

        $table = $wpdb->prefix . 'pfm_sharepoint_versions';

        $max_version = $wpdb->get_var($wpdb->prepare(
            "SELECT MAX(version_number) FROM {$table}
             WHERE publication_id = %d AND version_type = %s",
            $publication_id,
            $version_type
        ));

        return $max_version ? intval($max_version) + 1 : 1;
    }

    /**
     * Build the SharePoint path for a file
     *
     * Folder structure:
     * {base_folder}/{Jahr}/Einreichungen/{Autor_Nachname}/{YYYY-MM-DD}_{Originaldateiname}_V{Version}.ext
     *
     * @param int    $publication_id
     * @param string $filename
     * @param int    $version_number
     * @param string $version_type
     * @return string
     */
    public function build_sharepoint_path($publication_id, $filename, $version_number, $version_type) {
        $settings = $this->client->get_settings();
        $base_folder = $settings['base_folder'] ?: 'Zeitschrift Perfusion';

        $publication = get_post($publication_id);
        $year = date('Y');

        // Get author name from post meta or post author
        $author_lastname = get_post_meta($publication_id, '_pfm_author_lastname', true);
        if (empty($author_lastname)) {
            $autoren = get_post_meta($publication_id, 'autoren', true);
            if ($autoren) {
                // Try to extract first author's lastname (format: "Nachname, Vorname; ...")
                $first_author = explode(';', $autoren)[0];
                $parts = explode(',', $first_author);
                $author_lastname = trim($parts[0]);
            }
            if (empty($author_lastname)) {
                $author = get_userdata($publication->post_author);
                $author_lastname = $author ? $author->last_name : 'Unbekannt';
            }
        }

        // Sanitize folder name
        $author_lastname = $this->sanitize_folder_name($author_lastname);

        // Build type folder name
        $type_folders = array(
            self::TYPE_EINREICHUNG => 'Einreichungen',
            self::TYPE_GUTACHTEN => 'Gutachten',
            self::TYPE_REVISION => 'Revisionen',
            self::TYPE_FINAL => 'Final',
        );
        $type_folder = $type_folders[$version_type] ?? 'Sonstige';

        // Build filename with version
        $pathinfo = pathinfo($filename);
        $extension = isset($pathinfo['extension']) ? '.' . $pathinfo['extension'] : '';
        $basename = $pathinfo['filename'];
        $date_prefix = date('Y-m-d');

        $new_filename = sprintf(
            '%s_%s_V%d%s',
            $date_prefix,
            $this->sanitize_folder_name($basename),
            $version_number,
            $extension
        );

        // Build full path
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
     * Sanitize a string for use as folder name
     *
     * @param string $name
     * @return string
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
     *
     * @param array $data Version data
     * @return int|WP_Error Version ID or error
     */
    public function log_version($data) {
        global $wpdb;

        $table = $wpdb->prefix . 'pfm_sharepoint_versions';

        $insert_data = array(
            'publication_id' => $data['publication_id'],
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
            return new WP_Error('db_error', __('Version konnte nicht gespeichert werden.', PFM_TD));
        }

        return $wpdb->insert_id;
    }

    /**
     * Get all versions for a publication
     *
     * @param int    $publication_id
     * @param string $version_type Optional filter by type
     * @return array
     */
    public function get_versions($publication_id, $version_type = null) {
        global $wpdb;

        $table = $wpdb->prefix . 'pfm_sharepoint_versions';

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE publication_id = %d",
            $publication_id
        );

        if ($version_type) {
            $sql .= $wpdb->prepare(" AND version_type = %s", $version_type);
        }

        $sql .= " ORDER BY version_type, version_number DESC";

        return $wpdb->get_results($sql, ARRAY_A) ?: array();
    }

    /**
     * Get the current (latest) version for a publication
     *
     * @param int    $publication_id
     * @param string $version_type Optional filter by type
     * @return array|null
     */
    public function get_current_version($publication_id, $version_type = null) {
        global $wpdb;

        $table = $wpdb->prefix . 'pfm_sharepoint_versions';

        $sql = $wpdb->prepare(
            "SELECT * FROM {$table} WHERE publication_id = %d",
            $publication_id
        );

        if ($version_type) {
            $sql .= $wpdb->prepare(" AND version_type = %s", $version_type);
        }

        $sql .= " ORDER BY uploaded_at DESC LIMIT 1";

        return $wpdb->get_row($sql, ARRAY_A);
    }

    /**
     * Get a specific version by ID
     *
     * @param int $version_id
     * @return array|null
     */
    public function get_version($version_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'pfm_sharepoint_versions';

        return $wpdb->get_row(
            $wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $version_id),
            ARRAY_A
        );
    }

    /**
     * Get download URL for a version
     *
     * @param int $version_id
     * @return string|WP_Error
     */
    public function get_version_download_url($version_id) {
        $version = $this->get_version($version_id);

        if (!$version) {
            return new WP_Error('version_not_found', __('Version nicht gefunden.', PFM_TD));
        }

        if (empty($version['sharepoint_item_id'])) {
            return new WP_Error('no_item_id', __('SharePoint Item-ID fehlt.', PFM_TD));
        }

        return $this->client->get_download_url($version['sharepoint_item_id']);
    }

    /**
     * Delete a version from SharePoint and database
     *
     * @param int $version_id
     * @return bool|WP_Error
     */
    public function delete_version($version_id) {
        global $wpdb;

        $version = $this->get_version($version_id);

        if (!$version) {
            return new WP_Error('version_not_found', __('Version nicht gefunden.', PFM_TD));
        }

        // Delete from SharePoint
        if (!empty($version['sharepoint_item_id'])) {
            $delete_result = $this->client->delete_file($version['sharepoint_item_id']);
            if (is_wp_error($delete_result)) {
                // Log error but continue with database deletion
                error_log('SharePoint delete failed: ' . $delete_result->get_error_message());
            }
        }

        // Delete from database
        $table = $wpdb->prefix . 'pfm_sharepoint_versions';
        $result = $wpdb->delete($table, array('id' => $version_id), array('%d'));

        if ($result === false) {
            return new WP_Error('db_error', __('Version konnte nicht gelöscht werden.', PFM_TD));
        }

        return true;
    }

    /**
     * Get version statistics for a publication
     *
     * @param int $publication_id
     * @return array
     */
    public function get_version_stats($publication_id) {
        global $wpdb;

        $table = $wpdb->prefix . 'pfm_sharepoint_versions';

        $stats = $wpdb->get_results($wpdb->prepare(
            "SELECT version_type, COUNT(*) as count, SUM(file_size) as total_size
             FROM {$table}
             WHERE publication_id = %d
             GROUP BY version_type",
            $publication_id
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

    /**
     * Render version history table for admin
     *
     * @param int $publication_id
     * @return string HTML
     */
    public function render_version_history($publication_id) {
        $versions = $this->get_versions($publication_id);

        if (empty($versions)) {
            return '<p>' . __('Keine SharePoint-Versionen vorhanden.', PFM_TD) . '</p>';
        }

        $output = '<div class="pfm-sp-versions">';
        $output .= '<table class="widefat striped">';
        $output .= '<thead><tr>';
        $output .= '<th>' . __('Version', PFM_TD) . '</th>';
        $output .= '<th>' . __('Typ', PFM_TD) . '</th>';
        $output .= '<th>' . __('Dateiname', PFM_TD) . '</th>';
        $output .= '<th>' . __('Größe', PFM_TD) . '</th>';
        $output .= '<th>' . __('Hochgeladen', PFM_TD) . '</th>';
        $output .= '<th>' . __('Von', PFM_TD) . '</th>';
        $output .= '<th>' . __('Aktionen', PFM_TD) . '</th>';
        $output .= '</tr></thead>';
        $output .= '<tbody>';

        $type_labels = array(
            self::TYPE_EINREICHUNG => __('Einreichung', PFM_TD),
            self::TYPE_GUTACHTEN => __('Gutachten', PFM_TD),
            self::TYPE_REVISION => __('Revision', PFM_TD),
            self::TYPE_FINAL => __('Final', PFM_TD),
        );

        foreach ($versions as $version) {
            $type_label = $type_labels[$version['version_type']] ?? $version['version_type'];

            $output .= '<tr>';
            $output .= '<td><strong>V' . esc_html($version['version_number']) . '</strong></td>';
            $output .= '<td><span class="version-type-badge ' . esc_attr($version['version_type']) . '">' . esc_html($type_label) . '</span></td>';
            $output .= '<td>' . esc_html($version['original_filename']) . '</td>';
            $output .= '<td>' . size_format($version['file_size']) . '</td>';
            $output .= '<td>' . esc_html(mysql2date('d.m.Y H:i', $version['uploaded_at'])) . '</td>';
            $output .= '<td>' . esc_html($version['uploaded_by_name'] ?: '-') . '</td>';
            $output .= '<td>';

            if (!empty($version['sharepoint_web_url'])) {
                $output .= '<a href="' . esc_url($version['sharepoint_web_url']) . '" class="button button-small" target="_blank">';
                $output .= '<span class="dashicons dashicons-external"></span> ' . __('Öffnen', PFM_TD);
                $output .= '</a> ';
            }

            $output .= '<button class="button button-small pfm-sp-download" data-version-id="' . esc_attr($version['id']) . '">';
            $output .= '<span class="dashicons dashicons-download"></span> ' . __('Download', PFM_TD);
            $output .= '</button>';

            $output .= '</td>';
            $output .= '</tr>';

            if (!empty($version['notes'])) {
                $output .= '<tr class="version-notes"><td colspan="7">';
                $output .= '<strong>' . __('Notizen:', PFM_TD) . '</strong> ' . esc_html($version['notes']);
                $output .= '</td></tr>';
            }
        }

        $output .= '</tbody></table>';
        $output .= '</div>';

        return $output;
    }
}
