<?php
/**
 * Elementor Converter Class
 * Converts Elementor page data to Claude-friendly formats and back
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Elementor_Converter {

    /**
     * Export a page to Claude-friendly format
     */
    public function export_page($page_id, $format = 'markdown') {
        $post = get_post($page_id);

        if (!$post) {
            return new WP_Error('invalid_page', 'Seite nicht gefunden');
        }

        // Get Elementor data
        $elementor_data = get_post_meta($page_id, '_elementor_data', true);

        if (empty($elementor_data)) {
            return new WP_Error('no_elementor_data', 'Keine Elementor-Daten gefunden');
        }

        $data = json_decode($elementor_data, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Fehler beim Parsen der Elementor-Daten');
        }

        // Get page settings
        $page_settings = get_post_meta($page_id, '_elementor_page_settings', true);

        // Build export data
        $export_data = [
            'metadata' => [
                'page_id' => $page_id,
                'title' => $post->post_title,
                'post_type' => $post->post_type,
                'post_name' => $post->post_name,
                'exported_at' => current_time('Y-m-d H:i:s'),
                'elementor_version' => get_option('elementor_version'),
                'page_settings' => $page_settings ?: []
            ],
            'structure' => $this->parse_elementor_structure($data)
        ];

        // Generate output based on format
        switch ($format) {
            case 'markdown':
                return [
                    'format' => 'markdown',
                    'content' => $this->convert_to_markdown($export_data),
                    'filename' => sanitize_file_name($post->post_title) . '.md'
                ];

            case 'yaml':
                return [
                    'format' => 'yaml',
                    'content' => $this->convert_to_yaml($export_data),
                    'filename' => sanitize_file_name($post->post_title) . '.yaml'
                ];

            case 'json':
            default:
                return [
                    'format' => 'json',
                    'content' => json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
                    'filename' => sanitize_file_name($post->post_title) . '.json'
                ];
        }
    }

    /**
     * Parse Elementor structure into readable format
     */
    private function parse_elementor_structure($data, $level = 0) {
        $structure = [];

        foreach ($data as $element) {
            $parsed = [
                'id' => $element['id'] ?? '',
                'type' => $element['elType'] ?? 'unknown',
                'level' => $level
            ];

            // Add widget-specific info
            if (isset($element['widgetType'])) {
                $parsed['widget'] = $element['widgetType'];
            }

            // Extract settings
            if (isset($element['settings']) && !empty($element['settings'])) {
                $parsed['settings'] = $this->extract_important_settings($element['settings'], $element['elType']);
            }

            // Extract ALL original settings for re-import (preserve everything)
            if (isset($element['settings'])) {
                $parsed['_elementor_settings'] = $element['settings'];
            }

            // Parse children recursively
            if (isset($element['elements']) && !empty($element['elements'])) {
                $parsed['children'] = $this->parse_elementor_structure($element['elements'], $level + 1);
            }

            $structure[] = $parsed;
        }

        return $structure;
    }

    /**
     * Extract important settings (text, images, links, colors, etc.)
     */
    private function extract_important_settings($settings, $element_type) {
        $important = [];

        // Common important fields
        $important_fields = [
            // Content
            'text', 'title', 'description', 'content', 'editor', 'html',
            'heading', 'caption', 'button_text', 'link',

            // Media
            'image', 'background_image', 'video_link',

            // Styling (only if significant)
            'background_color', 'color', 'typography_font_family',

            // Layout
            'width', 'height', 'position',

            // Links and URLs
            'url', 'link', 'href',

            // Elementor Pro - Dynamic Visibility
            '_element_visibility', '_visibility', '_element_custom_visibility'
        ];

        // Always include Dynamic Visibility settings (Elementor Pro)
        if (isset($settings['_element_visibility'])) {
            $important['_element_visibility'] = $settings['_element_visibility'];
        }
        if (isset($settings['_element_custom_visibility'])) {
            $important['_element_custom_visibility'] = $settings['_element_custom_visibility'];
        }

        foreach ($settings as $key => $value) {
            // Skip empty values
            if (empty($value) && $value !== 0 && $value !== '0') {
                continue;
            }

            // Skip internal IDs
            if (strpos($key, '_id') !== false || strpos($key, 'element_id') !== false) {
                continue;
            }

            // Check if this is an important field
            $is_important = false;
            foreach ($important_fields as $field) {
                if (strpos($key, $field) !== false) {
                    $is_important = true;
                    break;
                }
            }

            if ($is_important) {
                // Handle complex objects (like images)
                if (is_array($value)) {
                    if (isset($value['url'])) {
                        $important[$key] = $value['url'];
                    } elseif (isset($value['id'])) {
                        $important[$key] = $value;
                    } else {
                        $important[$key] = $value;
                    }
                } else {
                    $important[$key] = $value;
                }
            }
        }

        return $important;
    }

    /**
     * Convert to Markdown format (most readable for Claude)
     */
    private function convert_to_markdown($data) {
        $output = "# Elementor Page Export: {$data['metadata']['title']}\n\n";

        // Metadata section
        $output .= "## Metadata\n\n";
        $output .= "```yaml\n";
        $output .= "page_id: {$data['metadata']['page_id']}\n";
        $output .= "title: \"{$data['metadata']['title']}\"\n";
        $output .= "post_type: {$data['metadata']['post_type']}\n";
        $output .= "post_name: {$data['metadata']['post_name']}\n";
        $output .= "exported_at: {$data['metadata']['exported_at']}\n";
        $output .= "elementor_version: {$data['metadata']['elementor_version']}\n";
        $output .= "```\n\n";

        // Page settings
        if (!empty($data['metadata']['page_settings'])) {
            $output .= "## Page Settings\n\n";
            $output .= "```json\n";
            $output .= json_encode($data['metadata']['page_settings'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $output .= "\n```\n\n";
        }

        // Structure
        $output .= "## Page Structure\n\n";
        $output .= $this->structure_to_markdown($data['structure']);

        // Instructions for Claude
        $output .= "\n\n---\n\n";
        $output .= "## WICHTIG: Anleitung für Claude AI\n\n";
        $output .= "Diese Datei ist ein Elementor-Seiten-Export zur Bearbeitung mit Claude AI.\n\n";

        $output .= "### Für den Re-Import: Sie MÜSSEN JSON zurückgeben!\n\n";
        $output .= "**KRITISCH**: Wenn Sie Änderungen vornehmen, müssen Sie das GESAMTE Dokument als **valides JSON** zurückgeben.\n\n";

        $output .= "#### Exakte Anweisung für Claude:\n\n";
        $output .= "```\n";
        $output .= "Bitte gib mir die bearbeitete Version als vollständiges JSON-Dokument zurück.\n";
        $output .= "Das JSON muss EXAKT diese Struktur haben:\n\n";
        $output .= "{\n";
        $output .= "  \"metadata\": { ... },\n";
        $output .= "  \"structure\": [ ... ]\n";
        $output .= "}\n\n";
        $output .= "Wichtig:\n";
        $output .= "- Behalte ALLE Felder bei (id, type, widget, level, settings, _elementor_settings, children)\n";
        $output .= "- Ändere NUR die Werte in 'settings', die ich angefordert habe\n";
        $output .= "- Ändere NIEMALS: IDs, types, widgets, _elementor_settings\n";
        $output .= "- Das Feld '_elementor_settings' enthält ALLE Elementor-Einstellungen (inkl. Dynamic Visibility)\n";
        $output .= "  und muss VOLLSTÄNDIG erhalten bleiben!\n";
        $output .= "- Gib mir das komplette, valide JSON ohne Markdown-Code-Blöcke\n";
        $output .= "```\n\n";

        $output .= "### Struktur-Erklärung\n\n";
        $output .= "- **settings**: Sichtbare, editierbare Felder (Text, Bilder, Links, Farben)\n";
        $output .= "- **_elementor_settings**: VOLLSTÄNDIGE Original-Einstellungen (inkl. Dynamic Visibility, Responsive, etc.)\n";
        $output .= "  → MUSS beim Re-Import vollständig erhalten bleiben!\n";
        $output .= "- **Dynamic Visibility**: In `_elementor_settings` unter Feldern wie `_element_visibility`\n\n";

        $output .= "### Beispiel-Workflow\n\n";
        $output .= "**Nutzer fragt**: \"Ändere die Überschrift im ersten Widget zu 'Willkommen'\"\n\n";
        $output .= "**Claude antwortet**:\n";
        $output .= "```json\n";
        $output .= "{\n";
        $output .= "  \"metadata\": { ... original metadata ... },\n";
        $output .= "  \"structure\": [\n";
        $output .= "    {\n";
        $output .= "      \"id\": \"abc123\",\n";
        $output .= "      \"type\": \"section\",\n";
        $output .= "      \"level\": 0,\n";
        $output .= "      \"settings\": {\n";
        $output .= "        \"heading\": \"Willkommen\"  // GEÄNDERT\n";
        $output .= "      },\n";
        $output .= "      \"_elementor_settings\": { ... ALLE original settings ... },  // UNVERÄNDERT!\n";
        $output .= "      \"children\": [ ... ]\n";
        $output .= "    }\n";
        $output .= "  ]\n";
        $output .= "}\n";
        $output .= "```\n\n";

        $output .= "### Was Sie ändern können\n\n";
        $output .= "- ✅ Text-Inhalte (title, heading, text, content, editor)\n";
        $output .= "- ✅ Bild-URLs (image, background_image)\n";
        $output .= "- ✅ Links und URLs (link, url, button_text)\n";
        $output .= "- ✅ Farben (color, background_color)\n";
        $output .= "- ✅ Sichtbare settings-Werte\n\n";

        $output .= "### Was Sie NICHT ändern dürfen\n\n";
        $output .= "- ❌ Element-IDs (id)\n";
        $output .= "- ❌ Element-Typen (type, widget)\n";
        $output .= "- ❌ Level/Hierarchie\n";
        $output .= "- ❌ Das gesamte Feld `_elementor_settings`\n";
        $output .= "- ❌ Die JSON-Struktur selbst\n\n";

        $output .= "---\n\n";
        $output .= "**Hinweis**: Wenn Sie nur diese Datei lesen möchten (ohne Änderungen), exportieren Sie\n";
        $output .= "die Seite erneut als **JSON-Format** für den direkten Re-Import.\n";

        return $output;
    }

    /**
     * Convert structure to readable Markdown
     */
    private function structure_to_markdown($structure, $depth = 0) {
        $output = '';
        $indent = str_repeat('  ', $depth);

        foreach ($structure as $element) {
            $type_label = $this->get_type_label($element['type'], $element['widget'] ?? null);

            $output .= "{$indent}- **{$type_label}** (ID: `{$element['id']}`)\n";

            // Show settings if any
            if (!empty($element['settings'])) {
                foreach ($element['settings'] as $key => $value) {
                    if (is_string($value)) {
                        $value_display = mb_strlen($value) > 100
                            ? mb_substr($value, 0, 100) . '...'
                            : $value;
                        $output .= "{$indent}  - `{$key}`: \"{$value_display}\"\n";
                    } elseif (is_array($value) && isset($value['url'])) {
                        $output .= "{$indent}  - `{$key}`: {$value['url']}\n";
                    } else {
                        $output .= "{$indent}  - `{$key}`: " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";
                    }
                }
            }

            // Recursive for children
            if (!empty($element['children'])) {
                $output .= $this->structure_to_markdown($element['children'], $depth + 1);
            }

            $output .= "\n";
        }

        return $output;
    }

    /**
     * Get human-readable type label
     */
    private function get_type_label($type, $widget = null) {
        $labels = [
            'section' => 'Section',
            'column' => 'Column',
            'widget' => $widget ? "Widget: {$widget}" : 'Widget',
            'container' => 'Container'
        ];

        return $labels[$type] ?? ucfirst($type);
    }

    /**
     * Convert to YAML format
     */
    private function convert_to_yaml($data) {
        // Simple YAML converter (for basic compatibility)
        $output = "# Elementor Page Export\n\n";
        $output .= "metadata:\n";

        foreach ($data['metadata'] as $key => $value) {
            if (is_array($value)) {
                $output .= "  {$key}:\n";
                foreach ($value as $k => $v) {
                    $output .= "    {$k}: " . json_encode($v, JSON_UNESCAPED_UNICODE) . "\n";
                }
            } else {
                $output .= "  {$key}: " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";
            }
        }

        $output .= "\nstructure:\n";
        $output .= $this->structure_to_yaml($data['structure'], 1);

        return $output;
    }

    /**
     * Convert structure to YAML
     */
    private function structure_to_yaml($structure, $level = 0) {
        $output = '';
        $indent = str_repeat('  ', $level);

        foreach ($structure as $i => $element) {
            $output .= "{$indent}- id: \"{$element['id']}\"\n";
            $output .= "{$indent}  type: {$element['type']}\n";

            if (isset($element['widget'])) {
                $output .= "{$indent}  widget: {$element['widget']}\n";
            }

            if (!empty($element['settings'])) {
                $output .= "{$indent}  settings:\n";
                foreach ($element['settings'] as $key => $value) {
                    if (is_array($value)) {
                        $output .= "{$indent}    {$key}: " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";
                    } else {
                        $output .= "{$indent}    {$key}: " . json_encode($value, JSON_UNESCAPED_UNICODE) . "\n";
                    }
                }
            }

            if (!empty($element['children'])) {
                $output .= "{$indent}  children:\n";
                $output .= $this->structure_to_yaml($element['children'], $level + 2);
            }
        }

        return $output;
    }

    /**
     * Import modified content back to Elementor
     */
    public function import_page($page_id, $content) {
        // Create backup first
        $this->create_backup($page_id);

        // Try to parse the content
        $data = null;

        // Try JSON first
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE && isset($decoded['structure'])) {
            $data = $decoded;
        } else {
            // Try to parse Markdown/YAML (simplified)
            return new WP_Error('format_error', 'Import unterstützt derzeit nur JSON-Format. Bitte exportieren Sie als JSON, bearbeiten Sie und importieren Sie zurück.');
        }

        // Validate the data structure
        $validation = $this->validate_import_data($data);
        if (is_wp_error($validation)) {
            return $validation;
        }

        // Rebuild Elementor data structure
        $elementor_data = $this->rebuild_elementor_data($data['structure']);

        // Update post meta
        update_post_meta($page_id, '_elementor_data', wp_slash(wp_json_encode($elementor_data)));

        // Clear Elementor cache
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }

        return true;
    }

    /**
     * Validate imported data structure
     */
    private function validate_import_data($data) {
        // Check required fields
        if (!isset($data['metadata'])) {
            return new WP_Error('missing_metadata', 'Fehlende Metadaten. Bitte stellen Sie sicher, dass Claude das vollständige JSON zurückgibt.');
        }

        if (!isset($data['structure']) || !is_array($data['structure'])) {
            return new WP_Error('missing_structure', 'Fehlende oder ungültige Struktur. Bitte stellen Sie sicher, dass Claude das vollständige JSON zurückgibt.');
        }

        // Validate structure elements
        $validation_result = $this->validate_structure_elements($data['structure']);
        if (is_wp_error($validation_result)) {
            return $validation_result;
        }

        return true;
    }

    /**
     * Validate structure elements recursively
     */
    private function validate_structure_elements($structure, $path = 'structure') {
        foreach ($structure as $index => $element) {
            $element_path = "{$path}[{$index}]";

            // Check required fields
            if (!isset($element['id'])) {
                return new WP_Error('missing_id', "Element bei {$element_path} hat keine ID. Bitte stellen Sie sicher, dass Claude alle IDs beibehält.");
            }

            if (!isset($element['type'])) {
                return new WP_Error('missing_type', "Element bei {$element_path} hat keinen Typ. Bitte stellen Sie sicher, dass Claude alle Types beibehält.");
            }

            // Warning if _elementor_settings is missing (but allow it)
            if (!isset($element['_elementor_settings'])) {
                dgptm_log_warning("Element {$element_path} hat keine _elementor_settings. Dynamic Visibility könnte verloren gehen.", 'elementor-ai-export');
            }

            // Validate children recursively
            if (isset($element['children']) && is_array($element['children'])) {
                $child_validation = $this->validate_structure_elements($element['children'], "{$element_path}.children");
                if (is_wp_error($child_validation)) {
                    return $child_validation;
                }
            }
        }

        return true;
    }

    /**
     * Rebuild Elementor data from our structure
     */
    private function rebuild_elementor_data($structure) {
        $elements = [];

        foreach ($structure as $element) {
            // Use full original settings if available, otherwise use edited settings
            $settings = $element['_elementor_settings'] ?? $element['settings'] ?? [];

            // If user edited 'settings', merge changes into original settings
            if (isset($element['_elementor_settings']) && isset($element['settings'])) {
                // User edited settings - merge changes into original
                $settings = $this->merge_settings($element['_elementor_settings'], $element['settings']);
            }

            $rebuilt = [
                'id' => $element['id'],
                'elType' => $element['type'],
                'settings' => $settings
            ];

            if (isset($element['widget'])) {
                $rebuilt['widgetType'] = $element['widget'];
            }

            if (isset($element['children'])) {
                $rebuilt['elements'] = $this->rebuild_elementor_data($element['children']);
            }

            $elements[] = $rebuilt;
        }

        return $elements;
    }

    /**
     * Merge edited settings into original settings (preserves all Elementor data)
     */
    private function merge_settings($original, $edited) {
        // Start with original (preserves Dynamic Visibility and all other settings)
        $merged = $original;

        // Apply user edits (only visible/editable fields)
        foreach ($edited as $key => $value) {
            $merged[$key] = $value;
        }

        return $merged;
    }

    /**
     * Create backup before import
     */
    private function create_backup($page_id) {
        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        $page_settings = get_post_meta($page_id, '_elementor_page_settings', true);

        $backup = [
            'elementor_data' => $elementor_data,
            'page_settings' => $page_settings,
            'timestamp' => current_time('mysql')
        ];

        // Store in transient (30 days)
        set_transient('elementor_ai_backup_' . $page_id, $backup, 30 * DAY_IN_SECONDS);

        return true;
    }

    /**
     * Import as staging page (creates a draft copy)
     */
    public function import_as_staging($page_id, $content) {
        $original_post = get_post($page_id);

        if (!$original_post) {
            return new WP_Error('invalid_page', 'Original-Seite nicht gefunden');
        }

        // Try to parse the content
        $decoded = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['structure'])) {
            return new WP_Error('format_error', 'Import unterstützt derzeit nur JSON-Format.');
        }

        // Create staging page
        $staging_post = [
            'post_title' => $original_post->post_title . ' (Staging)',
            'post_content' => $original_post->post_content,
            'post_status' => 'draft',
            'post_type' => $original_post->post_type,
            'post_author' => get_current_user_id()
        ];

        $staging_id = wp_insert_post($staging_post);

        if (is_wp_error($staging_id)) {
            return $staging_id;
        }

        // Rebuild Elementor data
        $elementor_data = $this->rebuild_elementor_data($decoded['structure']);

        // Set Elementor data
        update_post_meta($staging_id, '_elementor_data', wp_slash(wp_json_encode($elementor_data)));
        update_post_meta($staging_id, '_elementor_edit_mode', 'builder');
        update_post_meta($staging_id, '_elementor_template_type', get_post_meta($page_id, '_elementor_template_type', true));
        update_post_meta($staging_id, '_elementor_version', get_option('elementor_version'));

        // Copy page settings if available
        if (isset($decoded['metadata']['page_settings']) && !empty($decoded['metadata']['page_settings'])) {
            update_post_meta($staging_id, '_elementor_page_settings', $decoded['metadata']['page_settings']);
        } else {
            $original_settings = get_post_meta($page_id, '_elementor_page_settings', true);
            if ($original_settings) {
                update_post_meta($staging_id, '_elementor_page_settings', $original_settings);
            }
        }

        // Mark as staging page
        update_post_meta($staging_id, '_elementor_ai_staging', true);
        update_post_meta($staging_id, '_elementor_ai_staging_original', $page_id);
        update_post_meta($staging_id, '_elementor_ai_staging_created', current_time('mysql'));

        // Clear Elementor cache
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }

        return [
            'staging_id' => $staging_id,
            'original_id' => $page_id
        ];
    }

    /**
     * Apply staging changes to original page
     */
    public function apply_staging_to_original($staging_id) {
        // Verify it's a staging page
        $is_staging = get_post_meta($staging_id, '_elementor_ai_staging', true);
        if (!$is_staging) {
            return new WP_Error('not_staging', 'Keine Staging-Seite');
        }

        $original_id = get_post_meta($staging_id, '_elementor_ai_staging_original', true);
        if (!$original_id) {
            return new WP_Error('no_original', 'Original-Seite nicht gefunden');
        }

        $original_post = get_post($original_id);
        if (!$original_post) {
            return new WP_Error('invalid_original', 'Original-Seite existiert nicht mehr');
        }

        // Create backup of original
        $this->create_backup($original_id);

        // Copy Elementor data from staging to original
        $staging_data = get_post_meta($staging_id, '_elementor_data', true);
        $staging_settings = get_post_meta($staging_id, '_elementor_page_settings', true);

        update_post_meta($original_id, '_elementor_data', $staging_data);
        if ($staging_settings) {
            update_post_meta($original_id, '_elementor_page_settings', $staging_settings);
        }

        // Clear Elementor cache
        if (class_exists('\Elementor\Plugin')) {
            \Elementor\Plugin::$instance->files_manager->clear_cache();
        }

        // Delete staging page
        wp_delete_post($staging_id, true);

        return [
            'original_id' => $original_id,
            'staging_id' => $staging_id
        ];
    }
}
