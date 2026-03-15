<?php
/**
 * Page Analyzer - Semantische Analyse von Elementor-Seiten
 *
 * Erzeugt ein Blueprint mit:
 * - Seitenstruktur (Sections, Columns, Widgets)
 * - Dynamic Visibility Bedingungen (aufgeloest zu lesbaren Regeln)
 * - Shortcode-Inventar
 * - Berechtigungsmatrix (ACF-Felder, Rollen, User-Meta)
 * - Side-Restrict Konfiguration
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Page_Analyzer {

    private $shortcodes_found   = [];
    private $permissions_found  = [];
    private $visibility_rules   = [];
    private $acf_permission_fields = [];

    /**
     * Main entry: Analyze a page and return a structured blueprint
     */
    public function analyze($page_id) {
        $post = get_post($page_id);
        if (!$post) {
            return new WP_Error('invalid_page', 'Seite nicht gefunden');
        }

        $elementor_data = get_post_meta($page_id, '_elementor_data', true);
        if (empty($elementor_data)) {
            return new WP_Error('no_data', 'Keine Elementor-Daten fuer diese Seite');
        }

        $data = json_decode($elementor_data, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return new WP_Error('json_error', 'Fehler beim Parsen der Elementor-Daten: ' . json_last_error_msg());
        }

        // Load known ACF permission fields
        $this->load_acf_permission_fields();

        // Analyze page-level access restriction (side-restrict)
        $page_access = $this->analyze_page_access($page_id);

        // Recursively analyze elements
        $sections = $this->analyze_elements($data, 0);

        // Build the blueprint
        $blueprint = [
            '_meta' => [
                'generator'    => 'DGPTM Elementor Page Analyzer v1.0.0',
                'generated_at' => current_time('c'),
                'purpose'      => 'Semantisches Blueprint einer Elementor-Seite fuer den Neubau als modernes Dashboard',
            ],
            'page' => [
                'id'        => $page_id,
                'title'     => $post->post_title,
                'slug'      => $post->post_name,
                'url'       => get_permalink($page_id),
                'post_type' => $post->post_type,
                'status'    => $post->post_status,
                'template'  => get_page_template_slug($page_id) ?: 'default',
            ],
            'access_control' => $page_access,
            'permission_system' => [
                'description'  => 'Berechtigungen werden als ACF True/False Felder auf User-Profilen gespeichert (ACF Group: group_6792060047841 "Berechtigungen"). Side-Restrict prueft diese Felder auf Seitenebene. Dynamic Visibility prueft sie auf Element-Ebene.',
                'acf_group_key' => 'group_6792060047841',
                'fields_referenced' => array_values(array_unique($this->permissions_found)),
                'all_known_fields'  => $this->acf_permission_fields,
            ],
            'sections' => $sections,
            'shortcodes' => $this->compile_shortcode_inventory(),
            'visibility_summary' => $this->compile_visibility_summary(),
            'rebuild_notes' => $this->generate_rebuild_notes($page_access),
        ];

        return $blueprint;
    }

    /**
     * Load ACF permission fields from group_6792060047841
     */
    private function load_acf_permission_fields() {
        $this->acf_permission_fields = [];

        if (!function_exists('acf_get_fields')) {
            // Fallback: common known fields
            $this->acf_permission_fields = [
                'news_schreiben', 'news_alle', 'stellenanzeigen_anlegen',
                'testbereich', 'quizz_verwalten', 'delegate',
                'timeline', 'mitgliederversammlung', 'checkliste',
                'checkliste_erstellen', 'checkliste_template_erstellen',
                'webinar', 'umfragen', 'fortbildung',
            ];
            return;
        }

        $fields = acf_get_fields('group_6792060047841');
        if (is_array($fields)) {
            foreach ($fields as $field) {
                if ($field['type'] === 'true_false') {
                    $this->acf_permission_fields[] = $field['name'];
                }
            }
        }

        // If ACF group returned nothing, use fallback
        if (empty($this->acf_permission_fields)) {
            $this->acf_permission_fields = [
                'news_schreiben', 'news_alle', 'stellenanzeigen_anlegen',
                'testbereich', 'quizz_verwalten', 'delegate',
                'timeline', 'mitgliederversammlung', 'checkliste',
                'checkliste_erstellen', 'checkliste_template_erstellen',
                'webinar', 'umfragen', 'fortbildung',
            ];
        }
    }

    /**
     * Analyze page-level access restriction (side-restrict module)
     */
    private function analyze_page_access($page_id) {
        $restrict = get_post_meta($page_id, 'rbr_restrict_access', true);

        $access = [
            'restricted' => !empty($restrict),
            'rules'      => [],
        ];

        if (empty($restrict)) {
            $access['description'] = 'Keine Zugangsbeschraenkung - Seite ist fuer alle sichtbar';
            return $access;
        }

        // Allowed roles
        $roles = get_post_meta($page_id, 'rbr_allowed_roles', true);
        if (!empty($roles) && is_array($roles)) {
            $access['rules'][] = [
                'type'   => 'roles',
                'values' => $roles,
                'logic'  => 'Benutzer muss eine dieser Rollen haben',
            ];
        }

        // Allowed ACF fields
        $acf_fields = get_post_meta($page_id, 'rbr_allowed_acf_fields', true);
        if (!empty($acf_fields) && is_array($acf_fields)) {
            $access['rules'][] = [
                'type'   => 'acf_permissions',
                'values' => $acf_fields,
                'logic'  => 'Benutzer muss mindestens eines dieser ACF-Felder aktiviert haben (OR)',
            ];
            foreach ($acf_fields as $f) {
                $this->permissions_found[] = $f;
            }
        }

        // Allowed users
        $users = get_post_meta($page_id, 'rbr_allowed_users', true);
        if (!empty($users) && is_array($users)) {
            $user_names = [];
            foreach ($users as $uid) {
                $u = get_userdata($uid);
                $user_names[] = $u ? $u->display_name . " (ID:{$uid})" : "ID:{$uid}";
            }
            $access['rules'][] = [
                'type'   => 'specific_users',
                'values' => $user_names,
                'logic'  => 'Nur diese spezifischen Benutzer',
            ];
        }

        // Redirect
        $redirect = get_post_meta($page_id, 'rbr_redirect_page', true);
        if ($redirect) {
            $redirect_post = get_post($redirect);
            $access['redirect_on_deny'] = [
                'page_id' => $redirect,
                'title'   => $redirect_post ? $redirect_post->post_title : 'Unbekannt',
                'url'     => get_permalink($redirect),
            ];
        }

        $access['description'] = 'Zugang beschraenkt (OR-Logik: eine Bedingung genuegt)';

        return $access;
    }

    /**
     * Recursively analyze Elementor elements
     */
    private function analyze_elements($elements, $depth) {
        $result = [];

        foreach ($elements as $element) {
            $parsed = $this->analyze_single_element($element, $depth);
            if ($parsed) {
                $result[] = $parsed;
            }
        }

        return $result;
    }

    /**
     * Analyze a single element
     */
    private function analyze_single_element($element, $depth) {
        $el_type = $element['elType'] ?? 'unknown';
        $widget  = $element['widgetType'] ?? null;
        $settings = $element['settings'] ?? [];
        $el_id   = $element['id'] ?? '';

        $parsed = [
            'id'    => $el_id,
            'type'  => $el_type,
            'depth' => $depth,
        ];

        if ($widget) {
            $parsed['widget'] = $widget;
            $parsed['widget_label'] = $this->get_widget_label($widget);
        }

        // Section/Container label
        if ($el_type === 'section' || $el_type === 'container') {
            $parsed['label'] = $this->guess_section_label($element);
        }

        // Extract semantic content
        $content = $this->extract_content($settings, $widget);
        if (!empty($content)) {
            $parsed['content'] = $content;
        }

        // Analyze Dynamic Visibility
        $visibility = $this->extract_visibility($settings);
        if (!empty($visibility)) {
            $parsed['visibility'] = $visibility;
            $this->visibility_rules[] = [
                'element_id'   => $el_id,
                'element_type' => $widget ?: $el_type,
                'rules'        => $visibility,
            ];
        }

        // Find shortcodes in content
        $shortcodes = $this->find_shortcodes_in_settings($settings);
        if (!empty($shortcodes)) {
            $parsed['shortcodes'] = $shortcodes;
        }

        // CSS classes and custom IDs (useful for rebuild)
        if (!empty($settings['_css_classes'])) {
            $parsed['css_classes'] = $settings['_css_classes'];
        }
        if (!empty($settings['_element_id'])) {
            $parsed['html_id'] = $settings['_element_id'];
        }

        // Layout info for sections/containers
        if ($el_type === 'section' || $el_type === 'container') {
            $layout = $this->extract_layout_info($settings, $el_type);
            if (!empty($layout)) {
                $parsed['layout'] = $layout;
            }
        }

        // Column width
        if ($el_type === 'column') {
            if (isset($settings['_column_size'])) {
                $parsed['width_percent'] = $settings['_column_size'];
            }
            if (isset($settings['_inline_size'])) {
                $parsed['width_percent'] = $settings['_inline_size'];
            }
        }

        // Recurse into children
        if (!empty($element['elements'])) {
            $children = $this->analyze_elements($element['elements'], $depth + 1);
            if (!empty($children)) {
                $parsed['children'] = $children;
            }
        }

        return $parsed;
    }

    /**
     * Extract semantic content from widget settings
     */
    private function extract_content($settings, $widget) {
        $content = [];

        // Text content fields
        $text_fields = ['title', 'heading_title', 'editor', 'text', 'html', 'description',
                        'button_text', 'caption', 'alert_title', 'alert_description',
                        'tab_title', 'accordion_title', 'content', 'inner_text',
                        'before_text', 'after_text', 'prefix', 'suffix', 'fallback'];

        foreach ($text_fields as $field) {
            if (!empty($settings[$field])) {
                $val = $settings[$field];
                if (is_string($val) && strlen($val) > 0) {
                    // Strip HTML for readability but keep original for shortcodes
                    $content[$field] = $val;
                }
            }
        }

        // Image
        if (!empty($settings['image']['url'])) {
            $content['image_url'] = $settings['image']['url'];
        }
        if (!empty($settings['background_image']['url'])) {
            $content['background_image'] = $settings['background_image']['url'];
        }

        // Link / URL
        if (!empty($settings['link']['url'])) {
            $content['link'] = $settings['link']['url'];
        }
        if (!empty($settings['url']['url'])) {
            $content['url'] = $settings['url']['url'];
        }
        if (!empty($settings['button_link']['url'])) {
            $content['button_link'] = $settings['button_link']['url'];
        }

        // Icon
        if (!empty($settings['selected_icon']['value'])) {
            $content['icon'] = $settings['selected_icon']['value'];
        }

        // Shortcode widget
        if ($widget === 'shortcode' && !empty($settings['shortcode'])) {
            $content['shortcode'] = $settings['shortcode'];
        }

        // Menu anchor
        if ($widget === 'menu-anchor' && !empty($settings['anchor'])) {
            $content['anchor'] = $settings['anchor'];
        }

        return $content;
    }

    /**
     * Extract and resolve Dynamic Visibility conditions
     */
    private function extract_visibility($settings) {
        $rules = [];

        // Dynamic.ooo / Elementor Pro visibility
        $visibility_keys = [
            '_element_visibility',
            '_element_custom_visibility',
            'dce_visibility_triggers',
            'enabled_visibility',
            '_visibility',
        ];

        foreach ($visibility_keys as $key) {
            if (isset($settings[$key])) {
                $raw = $settings[$key];
                $resolved = $this->resolve_visibility_rule($key, $raw);
                if ($resolved) {
                    $rules[] = $resolved;
                }
            }
        }

        // JetEngine visibility conditions
        if (!empty($settings['jet_engine_listing_visibility'])) {
            $rules[] = [
                'source'      => 'jet_engine',
                'raw'         => $settings['jet_engine_listing_visibility'],
                'description' => 'JetEngine Sichtbarkeitsbedingung (manuell pruefen)',
            ];
        }

        // Dynamic.ooo visibility with conditions array
        if (!empty($settings['dce_visibility_conditions'])) {
            foreach ($settings['dce_visibility_conditions'] as $cond) {
                $resolved = $this->resolve_dce_condition($cond);
                if ($resolved) {
                    $rules[] = $resolved;
                }
            }
        }

        // Check for shortcode-based visibility (common DGPTM pattern)
        // e.g., content contains [umfrageberechtigung] or similar permission shortcodes
        $sc_visibility = $this->detect_shortcode_visibility($settings);
        if (!empty($sc_visibility)) {
            $rules = array_merge($rules, $sc_visibility);
        }

        return $rules;
    }

    /**
     * Resolve a raw visibility rule into readable format
     */
    private function resolve_visibility_rule($key, $raw) {
        if (empty($raw)) {
            return null;
        }

        $rule = [
            'source' => $key,
            'raw'    => $raw,
        ];

        // If it's an array of conditions
        if (is_array($raw)) {
            $conditions = [];
            foreach ($raw as $item) {
                if (is_array($item)) {
                    $cond = $this->resolve_single_condition($item);
                    if ($cond) {
                        $conditions[] = $cond;
                    }
                }
            }
            if (!empty($conditions)) {
                $rule['conditions'] = $conditions;
            }
        } elseif (is_string($raw)) {
            // Simple string value
            if ($raw === 'yes' || $raw === 'no') {
                $rule['description'] = $raw === 'yes' ? 'Sichtbar' : 'Versteckt';
            } else {
                $rule['description'] = 'Wert: ' . $raw;
            }
        }

        return $rule;
    }

    /**
     * Resolve a single Dynamic Visibility condition
     */
    private function resolve_single_condition($cond) {
        $result = [];

        // Common condition fields
        $type     = $cond['condition_type'] ?? $cond['type'] ?? '';
        $name     = $cond['condition_name'] ?? $cond['name'] ?? $cond['field'] ?? '';
        $operator = $cond['condition_operator'] ?? $cond['operator'] ?? 'equals';
        $value    = $cond['condition_value'] ?? $cond['value'] ?? '';

        if (empty($type) && empty($name)) {
            return $cond; // Return raw if unparseable
        }

        $result['type'] = $type;

        switch ($type) {
            case 'acf':
            case 'acf_field':
            case 'meta':
            case 'user_meta':
                $result['field'] = $name;
                $result['operator'] = $operator;
                $result['value'] = $value;
                $result['description'] = "Sichtbar wenn User-Meta/ACF '{$name}' {$operator} '{$value}'";

                // Track permission reference
                if (in_array($name, $this->acf_permission_fields, true)) {
                    $this->permissions_found[] = $name;
                    $result['is_permission_field'] = true;
                    $result['description'] = "Sichtbar wenn Berechtigung '{$name}' aktiviert ist";
                }
                break;

            case 'role':
            case 'user_role':
                $result['roles'] = is_array($value) ? $value : [$value];
                $result['description'] = 'Sichtbar fuer Rollen: ' . (is_array($value) ? implode(', ', $value) : $value);
                break;

            case 'logged_in':
            case 'user_logged_in':
                $result['description'] = $value ? 'Nur fuer eingeloggte Benutzer' : 'Nur fuer Gaeste';
                break;

            case 'shortcode':
                $result['shortcode'] = $name;
                $result['description'] = "Sichtbar wenn Shortcode '{$name}' Wert '{$value}' liefert";
                break;

            default:
                $result['raw'] = $cond;
                $result['description'] = "Bedingung: {$type} / {$name} {$operator} {$value}";
        }

        return $result;
    }

    /**
     * Resolve Dynamic.ooo condition
     */
    private function resolve_dce_condition($cond) {
        return $this->resolve_single_condition($cond);
    }

    /**
     * Detect visibility patterns in shortcode content
     */
    private function detect_shortcode_visibility($settings) {
        $rules = [];

        // Look through all text-like settings for permission-checking shortcodes
        $known_permission_shortcodes = [
            'umfrageberechtigung',
            'if_user_can',
            'if_loggedin',
            'user_has_role',
            'acf_user_field',
        ];

        foreach ($settings as $key => $value) {
            if (!is_string($value)) continue;

            foreach ($known_permission_shortcodes as $sc) {
                if (strpos($value, "[$sc") !== false) {
                    $rules[] = [
                        'source'      => 'shortcode_in_content',
                        'shortcode'   => $sc,
                        'setting_key' => $key,
                        'description' => "Inhalt wird durch Shortcode [{$sc}] bedingt angezeigt",
                    ];
                }
            }
        }

        return $rules;
    }

    /**
     * Find all shortcodes in settings
     */
    private function find_shortcodes_in_settings($settings) {
        $found = [];

        foreach ($settings as $key => $value) {
            if (!is_string($value)) continue;

            // Find shortcodes using regex
            if (preg_match_all('/\[([a-zA-Z0-9_-]+)([^\]]*)\]/', $value, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $sc_name = $match[1];
                    $sc_attrs = trim($match[2]);
                    $sc_full = $match[0];

                    // Skip common non-relevant shortcodes
                    if (in_array($sc_name, ['/if', 'else', 'endif'], true)) continue;

                    $entry = [
                        'name'        => $sc_name,
                        'full'        => $sc_full,
                        'in_setting'  => $key,
                    ];

                    if ($sc_attrs) {
                        $entry['attributes'] = $sc_attrs;
                    }

                    // Annotate known shortcodes
                    $entry['purpose'] = $this->annotate_shortcode($sc_name, $sc_attrs);

                    $found[] = $entry;

                    // Track in global shortcode list
                    $this->shortcodes_found[$sc_name] = ($this->shortcodes_found[$sc_name] ?? 0) + 1;
                }
            }
        }

        return $found;
    }

    /**
     * Annotate a known shortcode with its purpose
     */
    private function annotate_shortcode($name, $attrs) {
        $known = [
            'dgptm_umfrage'          => 'Umfrage-Formular anzeigen',
            'umfrageberechtigung'     => 'Prueft ob Benutzer Umfragen-Berechtigung hat (return 1/0)',
            'dgptm_umfrage_editor'   => 'Umfrage-Frontend-Editor',
            'dgptm_fortbildung'      => 'Fortbildungs-Widget',
            'dgptm_quiz'             => 'Quiz-Manager Widget',
            'dgptm_session_display'  => 'Session Display Widget',
            'dgptm_timeline'         => 'Timeline-Anzeige',
            'dgptm_news'             => 'News-Anzeige',
            'dgptm_stellenanzeigen'  => 'Stellenanzeigen-Liste',
            'dgptm_herzzentren'      => 'Herzzentren-Karte',
            'dgptm_mitgliedsantrag'  => 'Mitgliedsantrag-Formular',
            'elementor-template'     => 'Eingebettetes Elementor-Template',
            'formidable'             => 'Formidable Forms Formular',
            'frm_field_value'        => 'Formidable Forms Feldwert',
            'if'                     => 'Conditional Logic (if/else)',
            'get'                    => 'Custom Content Shortcode - Wert auslesen',
            'is'                     => 'Custom Content Shortcode - Bedingung pruefen',
            'user'                   => 'Benutzer-Informationen anzeigen',
            'acf'                    => 'ACF-Feldwert anzeigen',
        ];

        return $known[$name] ?? 'Unbekannter Shortcode';
    }

    /**
     * Guess a label for a section based on its content
     */
    private function guess_section_label($element) {
        $settings = $element['settings'] ?? [];

        // Check for CSS ID
        if (!empty($settings['_element_id'])) {
            return $settings['_element_id'];
        }

        // Check for CSS classes
        if (!empty($settings['_css_classes'])) {
            return $settings['_css_classes'];
        }

        // Check first heading in children
        $heading = $this->find_first_heading($element);
        if ($heading) {
            return wp_strip_all_tags($heading);
        }

        return null;
    }

    /**
     * Find first heading text in element tree
     */
    private function find_first_heading($element) {
        $settings = $element['settings'] ?? [];

        if (!empty($settings['title'])) {
            return $settings['title'];
        }
        if (!empty($settings['heading_title'])) {
            return $settings['heading_title'];
        }

        if (!empty($element['elements'])) {
            foreach ($element['elements'] as $child) {
                $result = $this->find_first_heading($child);
                if ($result) return $result;
            }
        }

        return null;
    }

    /**
     * Extract layout information
     */
    private function extract_layout_info($settings, $type) {
        $layout = [];

        if ($type === 'container') {
            if (!empty($settings['flex_direction'])) {
                $layout['direction'] = $settings['flex_direction'];
            }
            if (!empty($settings['flex_wrap'])) {
                $layout['wrap'] = $settings['flex_wrap'];
            }
            if (!empty($settings['flex_gap'])) {
                $layout['gap'] = $settings['flex_gap'];
            }
            if (!empty($settings['content_width'])) {
                $layout['content_width'] = $settings['content_width'];
            }
        }

        if ($type === 'section') {
            if (!empty($settings['layout'])) {
                $layout['type'] = $settings['layout']; // boxed, full_width, etc.
            }
            if (!empty($settings['structure'])) {
                $layout['column_structure'] = $settings['structure'];
            }
            if (!empty($settings['content_width'])) {
                $layout['content_width'] = $settings['content_width'];
            }
        }

        // Responsive visibility
        if (!empty($settings['hide_desktop'])) $layout['hide_desktop'] = true;
        if (!empty($settings['hide_tablet'])) $layout['hide_tablet'] = true;
        if (!empty($settings['hide_mobile'])) $layout['hide_mobile'] = true;

        // Background
        if (!empty($settings['background_background'])) {
            $layout['background_type'] = $settings['background_background'];
            if (!empty($settings['background_color'])) {
                $layout['background_color'] = $settings['background_color'];
            }
        }

        // Padding/Margin (top-level only)
        if (!empty($settings['padding'])) {
            $layout['padding'] = $settings['padding'];
        }
        if (!empty($settings['margin'])) {
            $layout['margin'] = $settings['margin'];
        }

        return $layout;
    }

    /**
     * Get human-readable widget label
     */
    private function get_widget_label($widget) {
        $labels = [
            'heading'              => 'Ueberschrift',
            'text-editor'          => 'Text-Editor (WYSIWYG)',
            'image'                => 'Bild',
            'button'               => 'Button',
            'icon'                 => 'Icon',
            'icon-box'             => 'Icon-Box',
            'icon-list'            => 'Icon-Liste',
            'divider'              => 'Trenner',
            'spacer'               => 'Abstand',
            'google_maps'          => 'Google Maps',
            'video'                => 'Video',
            'tabs'                 => 'Tabs',
            'accordion'            => 'Akkordeon',
            'toggle'               => 'Toggle',
            'alert'                => 'Alert/Hinweis',
            'html'                 => 'Custom HTML',
            'shortcode'            => 'Shortcode-Widget',
            'menu-anchor'          => 'Menu-Anker',
            'sidebar'              => 'Sidebar',
            'image-box'            => 'Bild-Box',
            'image-gallery'        => 'Bildergalerie',
            'image-carousel'       => 'Bild-Karussell',
            'counter'              => 'Zaehler',
            'progress'             => 'Fortschrittsbalken',
            'testimonial'          => 'Testimonial',
            'social-icons'         => 'Social Icons',
            'star-rating'          => 'Sterne-Bewertung',
            'nav-menu'             => 'Navigation',
            'template'             => 'Elementor Template',
            'wp-widget-custom_html' => 'WP Custom HTML Widget',
            'form'                 => 'Elementor Pro Formular',
            'login'                => 'Login-Formular',
            'posts'                => 'Posts/Beitraege',
            'portfolio'            => 'Portfolio',
            'slides'               => 'Slides',
            'call-to-action'       => 'Call to Action',
            'media-carousel'       => 'Media-Karussell',
            'testimonial-carousel' => 'Testimonial-Karussell',
            'animated-headline'    => 'Animierte Ueberschrift',
            'price-list'           => 'Preisliste',
            'price-table'          => 'Preistabelle',
            'flip-box'             => 'Flip-Box',
            'countdown'            => 'Countdown',
            'share-buttons'        => 'Teilen-Buttons',
            'blockquote'           => 'Zitat',
            'section'              => 'Section (Container)',
            'inner-section'        => 'Innere Section',
        ];

        return $labels[$widget] ?? $widget;
    }

    /**
     * Compile shortcode inventory across the whole page
     */
    private function compile_shortcode_inventory() {
        $inventory = [];

        foreach ($this->shortcodes_found as $name => $count) {
            $inventory[] = [
                'shortcode'  => "[$name]",
                'occurrences' => $count,
                'purpose'    => $this->annotate_shortcode($name, ''),
            ];
        }

        // Sort by occurrences
        usort($inventory, function ($a, $b) {
            return $b['occurrences'] - $a['occurrences'];
        });

        return $inventory;
    }

    /**
     * Compile visibility summary
     */
    private function compile_visibility_summary() {
        $summary = [
            'total_conditional_elements' => count($this->visibility_rules),
            'elements' => [],
        ];

        foreach ($this->visibility_rules as $rule) {
            $descriptions = [];
            foreach ($rule['rules'] as $r) {
                if (!empty($r['description'])) {
                    $descriptions[] = $r['description'];
                } elseif (!empty($r['conditions'])) {
                    foreach ($r['conditions'] as $c) {
                        if (!empty($c['description'])) {
                            $descriptions[] = $c['description'];
                        }
                    }
                }
            }

            $summary['elements'][] = [
                'element_id'   => $rule['element_id'],
                'element_type' => $rule['element_type'],
                'conditions'   => $descriptions,
            ];
        }

        return $summary;
    }

    /**
     * Generate rebuild notes for the dashboard
     */
    private function generate_rebuild_notes($page_access) {
        $notes = [];

        $notes[] = 'ZIEL: Diese Elementor-Seite soll als modernes, schnelles Dashboard ohne Elementor neu gebaut werden.';
        $notes[] = 'TECH-STACK: PHP-Modul innerhalb DGPTMSuite + Vanilla JS/CSS (kein Elementor, kein React noetig).';
        $notes[] = 'BERECHTIGUNGEN: Nutze das bestehende ACF-Berechtigungssystem (group_6792060047841). Pruefe Felder via get_field($field, "user_" . $user_id) oder get_user_meta($user_id, $field, true).';

        if (!empty($page_access['restricted'])) {
            $notes[] = 'SEITENZUGANG: Die aktuelle Seite verwendet side-restrict. Das neue Dashboard sollte dieselbe Zugangslogik implementieren.';
        }

        if (!empty($this->permissions_found)) {
            $perms = array_unique($this->permissions_found);
            $notes[] = 'VERWENDETE BERECHTIGUNGEN: ' . implode(', ', $perms);
        }

        if (!empty($this->shortcodes_found)) {
            $notes[] = 'SHORTCODES: Die folgenden Shortcodes muessen als native PHP-Funktionen/Widgets reimplementiert werden: ' . implode(', ', array_keys($this->shortcodes_found));
        }

        $notes[] = 'RESPONSIVE: Das Dashboard muss auf Desktop, Tablet und Mobile funktionieren.';
        $notes[] = 'SECTIONS: Jede Section mit Visibility-Bedingungen wird zu einem Dashboard-Widget/Karte, die serverseitig nur gerendert wird wenn der Benutzer die passende Berechtigung hat.';

        return $notes;
    }
}
