<?php
/**
 * Publication Frontend Manager - File Version Management
 * Erweiterte Dateiverwaltung mit Versionierung und Vergleich
 */

if (!defined('ABSPATH')) {
    exit;
}

class PFM_File_Manager {

    /**
     * Speichere neue Dateiversion
     */
    public static function add_file_version($post_id, $attachment_id, $version_type = 'revision', $notes = '') {
        $versions = self::get_file_versions($post_id);

        $version_data = array(
            'attachment_id' => $attachment_id,
            'version_type' => $version_type, // 'initial', 'revision', 'final', 'proofread'
            'upload_date' => current_time('mysql'),
            'uploaded_by' => get_current_user_id(),
            'uploader_name' => wp_get_current_user()->display_name,
            'notes' => $notes,
            'file_size' => filesize(get_attached_file($attachment_id)),
            'file_name' => basename(get_attached_file($attachment_id)),
        );

        $versions[] = $version_data;
        update_post_meta($post_id, 'pfm_file_versions', $versions);

        // Aktuelle Version setzen
        update_post_meta($post_id, 'pfm_manuscript_attachment_id', $attachment_id);
        update_post_meta($post_id, 'pfm_current_version', count($versions) - 1);

        return count($versions) - 1;
    }

    /**
     * Hole alle Dateiversionen
     */
    public static function get_file_versions($post_id) {
        $versions = get_post_meta($post_id, 'pfm_file_versions', true);
        return is_array($versions) ? $versions : array();
    }

    /**
     * Hole aktuelle Version
     */
    public static function get_current_version($post_id) {
        $current = get_post_meta($post_id, 'pfm_current_version', true);
        $versions = self::get_file_versions($post_id);

        if ($current !== '' && isset($versions[$current])) {
            return $versions[$current];
        }

        return !empty($versions) ? end($versions) : null;
    }

    /**
     * Render Datei-Versionsübersicht
     */
    public static function render_version_history($post_id) {
        $versions = self::get_file_versions($post_id);
        $current_version_index = get_post_meta($post_id, 'pfm_current_version', true);

        if (empty($versions)) {
            return '<p>' . __('Noch keine Dateien hochgeladen.', PFM_TD) . '</p>';
        }

        $output = '<div class="pfm-file-versions">';
        $output .= '<h4>' . __('Datei-Versionen', PFM_TD) . '</h4>';
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

        foreach (array_reverse($versions, true) as $index => $version) {
            $is_current = ($index == $current_version_index);
            $url = wp_get_attachment_url($version['attachment_id']);

            $output .= '<tr' . ($is_current ? ' class="current-version"' : '') . '>';
            $output .= '<td><strong>v' . ($index + 1) . '</strong>' . ($is_current ? ' <span class="badge">' . __('Aktuell', PFM_TD) . '</span>' : '') . '</td>';
            $output .= '<td>' . self::render_version_type_badge($version['version_type']) . '</td>';
            $output .= '<td>' . esc_html($version['file_name']) . '</td>';
            $output .= '<td>' . size_format($version['file_size']) . '</td>';
            $output .= '<td>' . esc_html(mysql2date('d.m.Y H:i', $version['upload_date'])) . '</td>';
            $output .= '<td>' . esc_html($version['uploader_name']) . '</td>';
            $output .= '<td>';
            $output .= '<a href="' . esc_url($url) . '" class="button button-small" target="_blank"><span class="dashicons dashicons-download"></span> ' . __('Download', PFM_TD) . '</a>';
            if (pfm_user_is_editor_in_chief() || pfm_user_is_redaktion()) {
                $output .= ' <button class="button button-small set-current" data-post-id="' . esc_attr($post_id) . '" data-version="' . esc_attr($index) . '"><span class="dashicons dashicons-yes"></span> ' . __('Als aktuell setzen', PFM_TD) . '</button>';
            }
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

    /**
     * Render Version-Type Badge
     */
    private static function render_version_type_badge($type) {
        $types = array(
            'initial' => array('label' => __('Ersteinreichung', PFM_TD), 'class' => 'initial'),
            'revision' => array('label' => __('Revision', PFM_TD), 'class' => 'revision'),
            'final' => array('label' => __('Final', PFM_TD), 'class' => 'final'),
            'proofread' => array('label' => __('Korrektur', PFM_TD), 'class' => 'proofread'),
        );

        $type_data = isset($types[$type]) ? $types[$type] : array('label' => $type, 'class' => 'default');

        return '<span class="version-type-badge ' . esc_attr($type_data['class']) . '">' . esc_html($type_data['label']) . '</span>';
    }

    /**
     * Render Datei-Upload-Formular mit Versionsauswahl
     */
    public static function render_upload_form($post_id, $version_type = 'revision') {
        ob_start();
        ?>
        <div class="pfm-file-upload-form">
            <h4><?php _e('Neue Version hochladen', PFM_TD); ?></h4>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <?php wp_nonce_field('pfm_upload_version', 'pfm_upload_version_nonce'); ?>
                <input type="hidden" name="action" value="pfm_upload_version">
                <input type="hidden" name="pfm_post_id" value="<?php echo esc_attr($post_id); ?>">

                <table class="form-table">
                    <tr>
                        <th><label for="version_type"><?php _e('Versionstyp', PFM_TD); ?></label></th>
                        <td>
                            <select name="version_type" id="version_type">
                                <option value="revision" <?php selected($version_type, 'revision'); ?>><?php _e('Revision', PFM_TD); ?></option>
                                <option value="final" <?php selected($version_type, 'final'); ?>><?php _e('Finale Version', PFM_TD); ?></option>
                                <option value="proofread" <?php selected($version_type, 'proofread'); ?>><?php _e('Korrektur', PFM_TD); ?></option>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="version_file"><?php _e('Datei (PDF)', PFM_TD); ?></label></th>
                        <td>
                            <input type="file" name="version_file" id="version_file" accept="application/pdf" required>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="version_notes"><?php _e('Änderungsnotizen', PFM_TD); ?></label></th>
                        <td>
                            <textarea name="version_notes" id="version_notes" rows="4" class="large-text"></textarea>
                            <p class="description"><?php _e('Beschreiben Sie kurz die Änderungen in dieser Version.', PFM_TD); ?></p>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <button type="submit" class="button button-primary">
                        <span class="dashicons dashicons-upload"></span> <?php _e('Version hochladen', PFM_TD); ?>
                    </button>
                </p>
            </form>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render Datei-Vergleichsansicht
     */
    public static function render_comparison_view($post_id, $version1, $version2) {
        $versions = self::get_file_versions($post_id);

        if (!isset($versions[$version1]) || !isset($versions[$version2])) {
            return '<p>' . __('Versionen nicht gefunden.', PFM_TD) . '</p>';
        }

        $v1 = $versions[$version1];
        $v2 = $versions[$version2];

        $output = '<div class="pfm-file-comparison">';
        $output .= '<h4>' . __('Versionsvergleich', PFM_TD) . '</h4>';

        $output .= '<table class="widefat">';
        $output .= '<thead><tr>';
        $output .= '<th></th>';
        $output .= '<th>' . sprintf(__('Version %d', PFM_TD), $version1 + 1) . '</th>';
        $output .= '<th>' . sprintf(__('Version %d', PFM_TD), $version2 + 1) . '</th>';
        $output .= '</tr></thead>';
        $output .= '<tbody>';

        $output .= '<tr><th>' . __('Dateiname', PFM_TD) . '</th>';
        $output .= '<td>' . esc_html($v1['file_name']) . '</td>';
        $output .= '<td>' . esc_html($v2['file_name']) . '</td></tr>';

        $output .= '<tr><th>' . __('Größe', PFM_TD) . '</th>';
        $output .= '<td>' . size_format($v1['file_size']) . '</td>';
        $output .= '<td>' . size_format($v2['file_size']) . '</td></tr>';

        $output .= '<tr><th>' . __('Hochgeladen', PFM_TD) . '</th>';
        $output .= '<td>' . esc_html(mysql2date('d.m.Y H:i', $v1['upload_date'])) . '</td>';
        $output .= '<td>' . esc_html(mysql2date('d.m.Y H:i', $v2['upload_date'])) . '</td></tr>';

        $output .= '<tr><th>' . __('Von', PFM_TD) . '</th>';
        $output .= '<td>' . esc_html($v1['uploader_name']) . '</td>';
        $output .= '<td>' . esc_html($v2['uploader_name']) . '</td></tr>';

        $output .= '<tr><th>' . __('Notizen', PFM_TD) . '</th>';
        $output .= '<td>' . esc_html($v1['notes'] ?: '—') . '</td>';
        $output .= '<td>' . esc_html($v2['notes'] ?: '—') . '</td></tr>';

        $output .= '<tr><th>' . __('Download', PFM_TD) . '</th>';
        $output .= '<td><a href="' . esc_url(wp_get_attachment_url($v1['attachment_id'])) . '" class="button" target="_blank"><span class="dashicons dashicons-download"></span> Download</a></td>';
        $output .= '<td><a href="' . esc_url(wp_get_attachment_url($v2['attachment_id'])) . '" class="button" target="_blank"><span class="dashicons dashicons-download"></span> Download</a></td></tr>';

        $output .= '</tbody></table>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Supplementary Materials Management
     */
    public static function get_supplementary_files($post_id) {
        $ids = get_post_meta($post_id, 'pfm_supplementary_ids', true);
        return is_array($ids) ? $ids : array();
    }

    /**
     * Render Supplementary Files List
     */
    public static function render_supplementary_files($post_id) {
        $file_ids = self::get_supplementary_files($post_id);

        if (empty($file_ids)) {
            return '<p>' . __('Keine zusätzlichen Dateien vorhanden.', PFM_TD) . '</p>';
        }

        $output = '<div class="pfm-supplementary-files">';
        $output .= '<h4>' . __('Zusätzliche Materialien', PFM_TD) . '</h4>';
        $output .= '<ul class="supplementary-list">';

        foreach ($file_ids as $file_id) {
            $url = wp_get_attachment_url($file_id);
            $filename = basename(get_attached_file($file_id));
            $filesize = size_format(filesize(get_attached_file($file_id)));

            $output .= '<li>';
            $output .= '<span class="dashicons dashicons-media-default"></span> ';
            $output .= '<a href="' . esc_url($url) . '" target="_blank">' . esc_html($filename) . '</a>';
            $output .= ' <small>(' . esc_html($filesize) . ')</small>';
            $output .= '</li>';
        }

        $output .= '</ul>';
        $output .= '</div>';

        return $output;
    }

    /**
     * Statistik über Dateien
     */
    public static function get_file_statistics($post_id) {
        $versions = self::get_file_versions($post_id);
        $supplementary = self::get_supplementary_files($post_id);

        $total_size = 0;
        foreach ($versions as $version) {
            $total_size += $version['file_size'];
        }

        foreach ($supplementary as $file_id) {
            $filepath = get_attached_file($file_id);
            if (file_exists($filepath)) {
                $total_size += filesize($filepath);
            }
        }

        return array(
            'version_count' => count($versions),
            'supplementary_count' => count($supplementary),
            'total_files' => count($versions) + count($supplementary),
            'total_size' => $total_size,
            'total_size_formatted' => size_format($total_size),
        );
    }
}
