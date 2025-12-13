<?php
/**
 * Elementor Repair Class
 * Repairs Elementor pages with automatic backup
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Elementor_Repair {

    private $backup_post_type = 'elementor_backup';

    public function __construct() {
        // Register post type on init hook
        add_action('init', [$this, 'register_backup_post_type']);
    }

    /**
     * Register custom post type for backups
     */
    public function register_backup_post_type() {
        if (!post_type_exists($this->backup_post_type)) {
            register_post_type($this->backup_post_type, [
                'labels' => [
                    'name' => 'Elementor Backups',
                    'singular_name' => 'Elementor Backup'
                ],
                'public' => false,
                'show_ui' => false,
                'capability_type' => 'post',
                'supports' => ['title'],
            ]);
        }
    }

    /**
     * Create backup of a page before repair
     *
     * @param int $post_id
     * @return int|WP_Error Backup post ID or error
     */
    public function create_backup($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            return new WP_Error('invalid_post', 'Post nicht gefunden');
        }

        // Get all Elementor meta data
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        $elementor_edit_mode = get_post_meta($post_id, '_elementor_edit_mode', true);
        $elementor_version = get_post_meta($post_id, '_elementor_version', true);
        $elementor_page_settings = get_post_meta($post_id, '_elementor_page_settings', true);
        $elementor_css = get_post_meta($post_id, '_elementor_css', true);

        // Create backup post
        $backup_data = [
            'post_title' => sprintf(
                'Backup: %s (%s)',
                $post->post_title,
                current_time('Y-m-d H:i:s')
            ),
            'post_type' => $this->backup_post_type,
            'post_status' => 'publish',
            'post_parent' => $post_id
        ];

        $backup_id = wp_insert_post($backup_data);

        if (is_wp_error($backup_id)) {
            return $backup_id;
        }

        // Store all metadata in backup
        $backup_meta = [
            'original_post_id' => $post_id,
            'original_post_title' => $post->post_title,
            'original_post_type' => $post->post_type,
            'backup_date' => current_time('mysql'),
            'elementor_data' => $elementor_data,
            'elementor_edit_mode' => $elementor_edit_mode,
            'elementor_version' => $elementor_version,
            'elementor_page_settings' => $elementor_page_settings,
            'elementor_css' => $elementor_css,
        ];

        foreach ($backup_meta as $key => $value) {
            update_post_meta($backup_id, '_backup_' . $key, $value);
        }

        return $backup_id;
    }

    /**
     * Restore a backup
     *
     * @param int $backup_id
     * @return bool|WP_Error
     */
    public function restore_backup($backup_id) {
        $backup_post = get_post($backup_id);
        if (!$backup_post || $backup_post->post_type !== $this->backup_post_type) {
            return new WP_Error('invalid_backup', 'Backup nicht gefunden');
        }

        $original_post_id = get_post_meta($backup_id, '_backup_original_post_id', true);
        if (!$original_post_id) {
            return new WP_Error('invalid_backup', 'Original Post-ID nicht gefunden');
        }

        // Restore all Elementor meta
        $meta_keys = [
            'elementor_data',
            'elementor_edit_mode',
            'elementor_version',
            'elementor_page_settings',
            'elementor_css'
        ];

        foreach ($meta_keys as $key) {
            $value = get_post_meta($backup_id, '_backup_' . $key, true);
            if ($value !== '') {
                update_post_meta($original_post_id, '_' . $key, $value);
            } else {
                delete_post_meta($original_post_id, '_' . $key);
            }
        }

        // Clear Elementor cache
        $this->clear_elementor_cache($original_post_id);

        return true;
    }

    /**
     * Get all backups for a post
     *
     * @param int $post_id
     * @return array
     */
    public function get_backups($post_id) {
        $backups = get_posts([
            'post_type' => $this->backup_post_type,
            'post_parent' => $post_id,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        $result = [];
        foreach ($backups as $backup) {
            $result[] = [
                'id' => $backup->ID,
                'title' => $backup->post_title,
                'date' => $backup->post_date,
                'backup_date' => get_post_meta($backup->ID, '_backup_backup_date', true)
            ];
        }

        return $result;
    }

    /**
     * Get all backups (all posts)
     *
     * @return array
     */
    public function get_all_backups() {
        $backups = get_posts([
            'post_type' => $this->backup_post_type,
            'posts_per_page' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ]);

        $result = [];
        foreach ($backups as $backup) {
            $original_post_id = get_post_meta($backup->ID, '_backup_original_post_id', true);
            $original_post_title = get_post_meta($backup->ID, '_backup_original_post_title', true);
            $backup_date = get_post_meta($backup->ID, '_backup_backup_date', true);

            // Check if original post still exists
            $original_post = get_post($original_post_id);
            $post_status = $original_post ? $original_post->post_status : 'deleted';

            $result[] = [
                'id' => $backup->ID,
                'title' => $backup->post_title,
                'date' => $backup->post_date,
                'backup_date' => $backup_date,
                'original_post_id' => $original_post_id,
                'original_post_title' => $original_post_title,
                'original_post_status' => $post_status,
                'original_post_url' => $original_post ? get_permalink($original_post_id) : '',
                'original_post_edit_url' => $original_post ? get_edit_post_link($original_post_id) : ''
            ];
        }

        return $result;
    }

    /**
     * Repair a page
     *
     * @param int $post_id
     * @return array|WP_Error Repair results or error
     */
    public function repair_page($post_id) {
        $repairs = [];

        // Get current data
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        if (empty($elementor_data)) {
            return new WP_Error('no_data', 'Keine Elementor-Daten zum Reparieren');
        }

        // Parse JSON
        $data = json_decode($elementor_data, true);
        if (!is_array($data)) {
            // Try to repair JSON
            $repaired = $this->repair_json($elementor_data);
            if ($repaired !== false) {
                update_post_meta($post_id, '_elementor_data', $repaired);
                $repairs[] = 'JSON repariert';
                $data = json_decode($repaired, true);
            } else {
                return new WP_Error('json_error', 'JSON konnte nicht repariert werden');
            }
        }

        if (is_array($data)) {
            // Repair structure
            $data = $this->repair_structure($data, $repairs);

            // Remove duplicate IDs
            $data = $this->repair_duplicate_ids($data, $repairs);

            // Clean orphaned settings
            $data = $this->clean_orphaned_settings($data, $repairs);

            // Save repaired data
            $new_json = wp_json_encode($data);
            update_post_meta($post_id, '_elementor_data', $new_json);
        }

        // Repair meta consistency
        $this->repair_meta_consistency($post_id, $repairs);

        // Clear edit locks
        $this->clear_edit_locks($post_id, $repairs);

        // Regenerate CSS
        $this->regenerate_css($post_id, $repairs);

        // Clean old revisions (keep last 10)
        $this->clean_revisions($post_id, 10, $repairs);

        return $repairs;
    }

    /**
     * Try to repair malformed JSON
     */
    private function repair_json($json_string) {
        // Try common fixes
        $json_string = trim($json_string);

        // Remove potential BOM
        $json_string = preg_replace('/^[\x{FEFF}]+/u', '', $json_string);

        // Fix common encoding issues
        $json_string = mb_convert_encoding($json_string, 'UTF-8', 'UTF-8');

        // Try to decode
        json_decode($json_string);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $json_string;
        }

        return false;
    }

    /**
     * Repair element structure
     */
    private function repair_structure($data, &$repairs) {
        if (!is_array($data)) {
            return $data;
        }

        $repaired = [];

        foreach ($data as $element) {
            if (!is_array($element)) {
                continue; // Skip invalid elements
            }

            // Ensure required fields
            if (!isset($element['id'])) {
                $element['id'] = $this->generate_element_id();
                $repairs[] = 'Fehlende Element-ID hinzugefügt';
            }

            if (!isset($element['elType'])) {
                // Try to guess type
                if (isset($element['elements'])) {
                    $element['elType'] = 'section';
                } else if (isset($element['widgetType'])) {
                    $element['elType'] = 'widget';
                } else {
                    continue; // Skip if we can't determine type
                }
                $repairs[] = 'Fehlender elType hinzugefügt';
            }

            // Ensure settings exist
            if (!isset($element['settings'])) {
                $element['settings'] = [];
            }

            // Recursively repair children
            if (isset($element['elements']) && is_array($element['elements'])) {
                $element['elements'] = $this->repair_structure($element['elements'], $repairs);
            }

            $repaired[] = $element;
        }

        return $repaired;
    }

    /**
     * Remove duplicate IDs
     */
    private function repair_duplicate_ids($data, &$repairs) {
        $used_ids = [];
        return $this->repair_duplicate_ids_recursive($data, $used_ids, $repairs);
    }

    private function repair_duplicate_ids_recursive($data, &$used_ids, &$repairs) {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as &$element) {
            if (!is_array($element)) {
                continue;
            }

            if (isset($element['id'])) {
                $original_id = $element['id'];

                // Check if ID already used
                if (in_array($original_id, $used_ids)) {
                    $element['id'] = $this->generate_element_id();
                    $repairs[] = "Doppelte ID ersetzt: {$original_id} -> {$element['id']}";
                }

                $used_ids[] = $element['id'];
            }

            // Recursively check children
            if (isset($element['elements'])) {
                $element['elements'] = $this->repair_duplicate_ids_recursive(
                    $element['elements'],
                    $used_ids,
                    $repairs
                );
            }
        }

        return $data;
    }

    /**
     * Clean orphaned settings (broken image references, etc.)
     */
    private function clean_orphaned_settings($data, &$repairs) {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as &$element) {
            if (!is_array($element)) {
                continue;
            }

            if (isset($element['settings']) && is_array($element['settings'])) {
                foreach ($element['settings'] as $key => &$value) {
                    // Check image references
                    if (is_array($value) && isset($value['id']) && !empty($value['id'])) {
                        if (strpos($key, 'image') !== false || strpos($key, 'background_image') !== false) {
                            $attachment = get_post($value['id']);
                            if (!$attachment) {
                                // Remove broken reference
                                $value = [
                                    'id' => '',
                                    'url' => ''
                                ];
                                $repairs[] = "Defekte Bild-Referenz entfernt (ID: {$value['id']})";
                            }
                        }
                    }
                }
            }

            // Recursively clean children
            if (isset($element['elements'])) {
                $element['elements'] = $this->clean_orphaned_settings($element['elements'], $repairs);
            }
        }

        return $data;
    }

    /**
     * Repair meta consistency
     */
    private function repair_meta_consistency($post_id, &$repairs) {
        $elementor_data = get_post_meta($post_id, '_elementor_data', true);
        $edit_mode = get_post_meta($post_id, '_elementor_edit_mode', true);
        $version = get_post_meta($post_id, '_elementor_version', true);

        // Ensure edit mode is set
        if (!empty($elementor_data) && empty($edit_mode)) {
            update_post_meta($post_id, '_elementor_edit_mode', 'builder');
            $repairs[] = 'Edit-Mode auf "builder" gesetzt';
        }

        // Set version if missing
        if (!empty($elementor_data) && empty($version)) {
            if (defined('ELEMENTOR_VERSION')) {
                update_post_meta($post_id, '_elementor_version', ELEMENTOR_VERSION);
                $repairs[] = 'Elementor-Version hinzugefügt';
            }
        }

        // Ensure page settings exist
        $page_settings = get_post_meta($post_id, '_elementor_page_settings', true);
        if (empty($page_settings) && !empty($elementor_data)) {
            update_post_meta($post_id, '_elementor_page_settings', []);
            $repairs[] = 'Page Settings initialisiert';
        }
    }

    /**
     * Clear edit locks
     */
    private function clear_edit_locks($post_id, &$repairs) {
        $locked = wp_check_post_lock($post_id);
        if ($locked) {
            delete_post_meta($post_id, '_edit_lock');
            $repairs[] = 'Bearbeitungssperre entfernt';
        }
    }

    /**
     * Regenerate CSS
     */
    private function regenerate_css($post_id, &$repairs) {
        // Delete CSS cache to force regeneration
        delete_post_meta($post_id, '_elementor_css');

        // Trigger regeneration if Elementor is available
        if (class_exists('\Elementor\Plugin')) {
            try {
                $css_file = \Elementor\Core\Files\CSS\Post::create($post_id);
                $css_file->update();
                $repairs[] = 'CSS regeneriert';
            } catch (Exception $e) {
                $repairs[] = 'CSS-Regenerierung vorbereitet (wird beim nächsten Laden erstellt)';
            }
        } else {
            $repairs[] = 'CSS-Cache geleert (wird beim nächsten Laden regeneriert)';
        }
    }

    /**
     * Clean old revisions
     */
    private function clean_revisions($post_id, $keep_count = 10, &$repairs) {
        $revisions = wp_get_post_revisions($post_id, ['order' => 'DESC']);

        if (count($revisions) > $keep_count) {
            $to_delete = array_slice($revisions, $keep_count);
            $deleted_count = 0;

            foreach ($to_delete as $revision) {
                if (wp_delete_post_revision($revision->ID)) {
                    $deleted_count++;
                }
            }

            if ($deleted_count > 0) {
                $repairs[] = "{$deleted_count} alte Revisionen gelöscht";
            }
        }
    }

    /**
     * Clear Elementor cache for a post
     */
    private function clear_elementor_cache($post_id) {
        delete_post_meta($post_id, '_elementor_css');

        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }
    }

    /**
     * Generate unique element ID
     */
    private function generate_element_id() {
        return dechex(time()) . substr(md5(uniqid()), 0, 6);
    }
}
