<?php
/**
 * Elementor Scanner Class
 * Scans Elementor pages for various errors and issues
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Elementor_Scanner {

    /**
     * Scan a single page for errors
     *
     * @param int $post_id
     * @return array Scan results with errors and warnings
     */
    public function scan_page($post_id) {
        $results = [
            'post_id' => $post_id,
            'post_title' => get_the_title($post_id),
            'post_url' => get_permalink($post_id),
            'errors' => [],
            'warnings' => [],
            'info' => [],
            'has_elementor' => false,
            'is_valid' => true
        ];

        // Check if Elementor is used
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        if (empty($elementor_data)) {
            $results['info'][] = 'Keine Elementor-Daten gefunden';
            return $results;
        }

        $results['has_elementor'] = true;

        // 1. Check JSON validity
        $this->check_json_validity($elementor_data, $results);

        // 2. Parse and check structure
        $data = json_decode($elementor_data, true);
        if (is_array($data)) {
            // 3. Check element structure
            $this->check_element_structure($data, $results);

            // 4. Check for duplicate IDs
            $this->check_duplicate_ids($data, $results);

            // 5. Check widget references
            $this->check_widget_references($data, $results);

            // 6. Check for orphaned settings
            $this->check_orphaned_settings($data, $results);
        }

        // 7. Check CSS files
        $this->check_css_files($post_id, $results);

        // 8. Check edit locks
        $this->check_edit_locks($post_id, $results);

        // 9. Check meta data consistency
        $this->check_meta_consistency($post_id, $results);

        // 10. Check revision count
        $this->check_revisions($post_id, $results);

        // Set overall validity
        $results['is_valid'] = empty($results['errors']);

        return $results;
    }

    /**
     * Scan all Elementor pages in batches
     *
     * @param int $page Current page number
     * @param int $per_page Items per page
     * @return array Batch results
     */
    public function scan_all_pages($page = 1, $per_page = 10) {
        $args = [
            'post_type' => ['page', 'post'],
            'post_status' => 'any',
            'posts_per_page' => $per_page,
            'paged' => $page,
            'meta_query' => [
                [
                    'key' => '_elementor_edit_mode',
                    'value' => 'builder',
                    'compare' => '='
                ]
            ],
            'orderby' => 'ID',
            'order' => 'ASC'
        ];

        $query = new WP_Query($args);

        $results = [
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => $query->max_num_pages,
            'total_found' => $query->found_posts,
            'items' => [],
            'has_more' => $page < $query->max_num_pages
        ];

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $post_id = get_the_ID();
                $scan_result = $this->scan_page($post_id);
                $results['items'][] = $scan_result;
            }
            wp_reset_postdata();
        }

        return $results;
    }

    /**
     * Check if JSON is valid
     */
    private function check_json_validity($json_string, &$results) {
        json_decode($json_string);
        $json_error = json_last_error();

        if ($json_error !== JSON_ERROR_NONE) {
            $error_msg = 'Ungültiges JSON-Format';
            switch ($json_error) {
                case JSON_ERROR_DEPTH:
                    $error_msg .= ': Maximale Verschachtelungstiefe überschritten';
                    break;
                case JSON_ERROR_STATE_MISMATCH:
                    $error_msg .= ': Ungültiger oder fehlerhafter JSON';
                    break;
                case JSON_ERROR_CTRL_CHAR:
                    $error_msg .= ': Unerwartetes Steuerzeichen';
                    break;
                case JSON_ERROR_SYNTAX:
                    $error_msg .= ': Syntax-Fehler';
                    break;
                case JSON_ERROR_UTF8:
                    $error_msg .= ': Fehlerhafte UTF-8 Zeichen';
                    break;
            }
            $results['errors'][] = $error_msg;
            $results['is_valid'] = false;
        }
    }

    /**
     * Check element structure (sections > columns > widgets)
     */
    private function check_element_structure($data, &$results, $parent_type = null, $depth = 0) {
        if (!is_array($data)) {
            return;
        }

        foreach ($data as $index => $element) {
            if (!is_array($element)) {
                $results['errors'][] = "Element an Position {$index} ist kein Array";
                continue;
            }

            // Check required fields
            if (!isset($element['elType'])) {
                $results['errors'][] = "Element an Position {$index} hat kein elType-Feld";
                continue;
            }

            $el_type = $element['elType'];

            // Validate hierarchy
            if ($parent_type === null && $el_type !== 'section') {
                $results['errors'][] = "Root-Element muss 'section' sein, gefunden: '{$el_type}' an Position {$index}";
            }

            if ($parent_type === 'section' && !in_array($el_type, ['section', 'column'])) {
                $results['errors'][] = "Section darf nur Columns enthalten, gefunden: '{$el_type}' an Position {$index}";
            }

            if ($parent_type === 'column' && !in_array($el_type, ['widget', 'section'])) {
                $results['errors'][] = "Column darf nur Widgets oder Inner Sections enthalten, gefunden: '{$el_type}' an Position {$index}";
            }

            // Check for required ID
            if (!isset($element['id']) || empty($element['id'])) {
                $results['errors'][] = "Element '{$el_type}' an Position {$index} hat keine ID";
            }

            // Check widget type
            if ($el_type === 'widget' && (!isset($element['widgetType']) || empty($element['widgetType']))) {
                $results['errors'][] = "Widget an Position {$index} hat keinen widgetType";
            }

            // Recursively check children
            if (isset($element['elements']) && is_array($element['elements'])) {
                $this->check_element_structure($element['elements'], $results, $el_type, $depth + 1);
            }

            // Warn about excessive nesting
            if ($depth > 5) {
                $results['warnings'][] = "Sehr tiefe Verschachtelung erkannt (Tiefe: {$depth})";
            }
        }
    }

    /**
     * Check for duplicate element IDs
     */
    private function check_duplicate_ids($data, &$results) {
        $ids = [];
        $this->collect_ids($data, $ids);

        $duplicates = array_filter(array_count_values($ids), function($count) {
            return $count > 1;
        });

        if (!empty($duplicates)) {
            foreach ($duplicates as $id => $count) {
                $results['errors'][] = "Doppelte Element-ID gefunden: '{$id}' ({$count}x)";
            }
        }
    }

    /**
     * Collect all IDs recursively
     */
    private function collect_ids($data, &$ids) {
        if (!is_array($data)) {
            return;
        }

        foreach ($data as $element) {
            if (is_array($element) && isset($element['id'])) {
                $ids[] = $element['id'];
            }

            if (is_array($element) && isset($element['elements'])) {
                $this->collect_ids($element['elements'], $ids);
            }
        }
    }

    /**
     * Check widget references
     */
    private function check_widget_references($data, &$results) {
        $widgets = [];
        $this->collect_widgets($data, $widgets);

        foreach ($widgets as $widget) {
            $widget_type = $widget['widgetType'] ?? 'unknown';

            // Check if widget type exists (basic check)
            if (empty($widget_type) || $widget_type === 'unknown') {
                $results['errors'][] = "Widget ohne Typ gefunden";
                continue;
            }

            // Check for common problematic widget types
            $deprecated_widgets = ['google-maps', 'soundcloud', 'flash'];
            if (in_array($widget_type, $deprecated_widgets)) {
                $results['warnings'][] = "Veralteter Widget-Typ gefunden: '{$widget_type}'";
            }

            // Check for missing settings
            if (!isset($widget['settings']) || !is_array($widget['settings'])) {
                $results['warnings'][] = "Widget '{$widget_type}' hat keine Settings";
            }
        }
    }

    /**
     * Collect all widgets recursively
     */
    private function collect_widgets($data, &$widgets) {
        if (!is_array($data)) {
            return;
        }

        foreach ($data as $element) {
            if (!is_array($element)) {
                continue;
            }

            if (isset($element['elType']) && $element['elType'] === 'widget') {
                $widgets[] = $element;
            }

            if (isset($element['elements'])) {
                $this->collect_widgets($element['elements'], $widgets);
            }
        }
    }

    /**
     * Check for orphaned settings
     */
    private function check_orphaned_settings($data, &$results) {
        foreach ($data as $element) {
            if (!is_array($element)) {
                continue;
            }

            // Check for settings without proper structure
            if (isset($element['settings']) && is_array($element['settings'])) {
                // Look for broken image references
                foreach ($element['settings'] as $key => $value) {
                    if (is_array($value) && isset($value['id']) && !empty($value['id'])) {
                        // Check if attachment exists
                        if (strpos($key, 'image') !== false || strpos($key, 'background_image') !== false) {
                            $attachment = get_post($value['id']);
                            if (!$attachment) {
                                $results['warnings'][] = "Bild-ID {$value['id']} existiert nicht mehr (Feld: {$key})";
                            }
                        }
                    }
                }
            }

            if (isset($element['elements'])) {
                $this->check_orphaned_settings($element['elements'], $results);
            }
        }
    }

    /**
     * Check CSS files
     */
    private function check_css_files($post_id, &$results) {
        $css_file = get_post_meta($post_id, '_elementor_css', true);

        if (empty($css_file)) {
            $results['warnings'][] = 'Keine CSS-Datei Metadaten gefunden';
            return;
        }

        // Check if CSS needs regeneration
        $elementor_edit_mode = get_post_meta($post_id, '_elementor_edit_mode', true);
        if ($elementor_edit_mode === 'builder') {
            // Check CSS file existence
            if (is_array($css_file)) {
                foreach ($css_file as $status => $data) {
                    if (isset($data['path']) && !file_exists($data['path'])) {
                        $results['warnings'][] = "CSS-Datei fehlt: {$data['path']}";
                    }
                }
            }
        }
    }

    /**
     * Check edit locks
     */
    private function check_edit_locks($post_id, &$results) {
        $locked = wp_check_post_lock($post_id);

        if ($locked) {
            $user = get_userdata($locked);
            $username = $user ? $user->display_name : 'Unbekannt';
            $results['warnings'][] = "Seite ist gesperrt von: {$username}";
        }
    }

    /**
     * Check meta data consistency
     */
    private function check_meta_consistency($post_id, &$results) {
        $edit_mode = get_post_meta($post_id, '_elementor_edit_mode', true);
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        $version = get_post_meta($post_id, '_elementor_version', true);

        if ($edit_mode === 'builder' && empty($elementor_data)) {
            $results['errors'][] = 'Edit-Mode ist "builder" aber keine Elementor-Daten vorhanden';
        }

        if (empty($version) && !empty($elementor_data)) {
            $results['warnings'][] = 'Keine Elementor-Version gespeichert';
        }

        // Check for page settings
        $page_settings = get_post_meta($post_id, '_elementor_page_settings', true);
        if (empty($page_settings) && !empty($elementor_data)) {
            $results['info'][] = 'Keine Page Settings gefunden (optional)';
        }
    }

    /**
     * Check revision count
     */
    private function check_revisions($post_id, &$results) {
        $revisions = wp_get_post_revisions($post_id);
        $revision_count = count($revisions);

        if ($revision_count > 50) {
            $results['warnings'][] = "Sehr viele Revisionen: {$revision_count} (kann Performance beeinträchtigen)";
        }

        $results['info'][] = "Revisionen: {$revision_count}";
    }
}
