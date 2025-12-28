<?php
/**
 * Plugin Name: Crocoblock Migration Analyzer
 * Description: Analysiert die Nutzung von JetEngine, JetElements, JetSmartFilters und JetFormBuilder
 * Version: 1.0.0
 * Author: Sebastian Melzer / DGPTM
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!class_exists('Crocoblock_Analyzer')) {
    class Crocoblock_Analyzer {
        private static $instance = null;

        public static function get_instance() {
            if (null === self::$instance) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            add_action('admin_menu', [$this, 'add_admin_menu']);
            add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
            add_action('wp_ajax_cba_run_analysis', [$this, 'ajax_run_analysis']);
            add_action('wp_ajax_cba_export_report', [$this, 'ajax_export_report']);
        }

        public function add_admin_menu() {
            add_management_page(
                'Crocoblock Analyzer',
                'Crocoblock Analyzer',
                'manage_options',
                'crocoblock-analyzer',
                [$this, 'render_admin_page']
            );
        }

        public function enqueue_assets($hook) {
            if ($hook !== 'tools_page_crocoblock-analyzer') {
                return;
            }

            wp_enqueue_style(
                'cba-admin',
                plugin_dir_url(__FILE__) . 'assets/css/admin.css',
                [],
                '1.0.0'
            );

            wp_enqueue_script(
                'cba-admin',
                plugin_dir_url(__FILE__) . 'assets/js/admin.js',
                ['jquery'],
                '1.0.0',
                true
            );

            wp_localize_script('cba-admin', 'cbaAdmin', [
                'ajaxUrl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('cba_nonce')
            ]);
        }

        public function render_admin_page() {
            ?>
            <div class="wrap cba-wrap">
                <h1>Crocoblock Migration Analyzer</h1>
                <p class="description">Dieses Tool analysiert die Nutzung aller Crocoblock-Plugins für die Migration zu ACF, Elementor Pro und Formidable Forms.</p>

                <div class="cba-actions">
                    <button type="button" id="cba-run-analysis" class="button button-primary button-hero">
                        <span class="dashicons dashicons-search"></span>
                        Analyse starten
                    </button>
                    <button type="button" id="cba-export-report" class="button button-secondary" disabled>
                        <span class="dashicons dashicons-download"></span>
                        Report exportieren
                    </button>
                </div>

                <div id="cba-progress" style="display: none;">
                    <div class="cba-progress-bar">
                        <div class="cba-progress-fill"></div>
                    </div>
                    <p class="cba-progress-text">Analysiere...</p>
                </div>

                <div id="cba-results" style="display: none;">
                    <!-- JetEngine Section -->
                    <div class="cba-section" id="cba-jetengine">
                        <h2><span class="dashicons dashicons-admin-generic"></span> JetEngine</h2>
                        <div class="cba-content">
                            <div class="cba-subsection">
                                <h3>Custom Post Types</h3>
                                <div id="cba-cpts" class="cba-list"></div>
                            </div>
                            <div class="cba-subsection">
                                <h3>Custom Taxonomies</h3>
                                <div id="cba-taxonomies" class="cba-list"></div>
                            </div>
                            <div class="cba-subsection">
                                <h3>Meta Fields</h3>
                                <div id="cba-metafields" class="cba-list"></div>
                            </div>
                            <div class="cba-subsection">
                                <h3>Relations</h3>
                                <div id="cba-relations" class="cba-list"></div>
                            </div>
                        </div>
                    </div>

                    <!-- JetElements Section -->
                    <div class="cba-section" id="cba-jetelements">
                        <h2><span class="dashicons dashicons-screenoptions"></span> JetElements Widgets</h2>
                        <div class="cba-content">
                            <div id="cba-jetelements-list" class="cba-list"></div>
                        </div>
                    </div>

                    <!-- JetSmartFilters Section -->
                    <div class="cba-section" id="cba-jetsmartfilters">
                        <h2><span class="dashicons dashicons-filter"></span> JetSmartFilters</h2>
                        <div class="cba-content">
                            <div id="cba-filters-list" class="cba-list"></div>
                        </div>
                    </div>

                    <!-- JetFormBuilder Section -->
                    <div class="cba-section" id="cba-jetformbuilder">
                        <h2><span class="dashicons dashicons-feedback"></span> JetFormBuilder</h2>
                        <div class="cba-content">
                            <div id="cba-forms-list" class="cba-list"></div>
                        </div>
                    </div>

                    <!-- Migration Summary -->
                    <div class="cba-section cba-summary" id="cba-summary">
                        <h2><span class="dashicons dashicons-migrate"></span> Migrations-Zusammenfassung</h2>
                        <div class="cba-content">
                            <div id="cba-summary-content"></div>
                        </div>
                    </div>
                </div>
            </div>
            <?php
        }

        public function ajax_run_analysis() {
            check_ajax_referer('cba_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $results = [
                'jetengine' => $this->analyze_jetengine(),
                'jetelements' => $this->analyze_jetelements(),
                'jetsmartfilters' => $this->analyze_jetsmartfilters(),
                'jetformbuilder' => $this->analyze_jetformbuilder(),
                'summary' => []
            ];

            // Generate summary
            $results['summary'] = $this->generate_summary($results);

            wp_send_json_success($results);
        }

        /**
         * Analyze JetEngine: CPTs, Taxonomies, Meta Fields, Relations
         */
        private function analyze_jetengine() {
            global $wpdb;

            $result = [
                'active' => is_plugin_active('jet-engine/jet-engine.php'),
                'cpts' => [],
                'taxonomies' => [],
                'meta_fields' => [],
                'relations' => []
            ];

            // Check if JetEngine tables exist
            $tables_exist = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}jet_post_types'");

            if (!$tables_exist) {
                // Try to get CPTs from options
                $jet_cpts = get_option('jet_engine_post_types', []);
                if (!empty($jet_cpts) && is_array($jet_cpts)) {
                    foreach ($jet_cpts as $cpt) {
                        $result['cpts'][] = [
                            'slug' => $cpt['slug'] ?? $cpt['name'] ?? 'unknown',
                            'name' => $cpt['labels']['name'] ?? $cpt['name'] ?? 'Unknown',
                            'meta_fields' => $cpt['meta_fields'] ?? []
                        ];
                    }
                }

                // Get taxonomies from options
                $jet_taxes = get_option('jet_engine_taxonomies', []);
                if (!empty($jet_taxes) && is_array($jet_taxes)) {
                    foreach ($jet_taxes as $tax) {
                        $result['taxonomies'][] = [
                            'slug' => $tax['slug'] ?? $tax['name'] ?? 'unknown',
                            'name' => $tax['labels']['name'] ?? $tax['name'] ?? 'Unknown',
                            'post_types' => $tax['object_type'] ?? []
                        ];
                    }
                }

                // Get relations from options
                $jet_relations = get_option('jet_engine_relations', []);
                if (!empty($jet_relations) && is_array($jet_relations)) {
                    foreach ($jet_relations as $rel) {
                        $result['relations'][] = [
                            'name' => $rel['name'] ?? 'Unknown',
                            'parent' => $rel['parent_object'] ?? '',
                            'child' => $rel['child_object'] ?? '',
                            'type' => $rel['type'] ?? 'one_to_many'
                        ];
                    }
                }

                // Get meta boxes from options
                $jet_meta = get_option('jet_engine_meta_boxes', []);
                if (!empty($jet_meta) && is_array($jet_meta)) {
                    foreach ($jet_meta as $meta) {
                        $fields = [];
                        if (!empty($meta['meta_fields'])) {
                            foreach ($meta['meta_fields'] as $field) {
                                $fields[] = [
                                    'name' => $field['name'] ?? '',
                                    'title' => $field['title'] ?? $field['name'] ?? '',
                                    'type' => $field['type'] ?? 'text'
                                ];
                            }
                        }
                        $result['meta_fields'][] = [
                            'title' => $meta['args']['name'] ?? 'Unknown',
                            'post_types' => $meta['args']['allowed_post_type'] ?? [],
                            'fields' => $fields
                        ];
                    }
                }

                return $result;
            }

            // Query JetEngine database tables
            // CPTs
            $cpts = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}jet_post_types", ARRAY_A);
            if ($cpts) {
                foreach ($cpts as $cpt) {
                    $args = maybe_unserialize($cpt['args'] ?? '');
                    $meta = maybe_unserialize($cpt['meta_fields'] ?? '');
                    $result['cpts'][] = [
                        'id' => $cpt['id'],
                        'slug' => $cpt['slug'],
                        'name' => is_array($args) && isset($args['labels']['name']) ? $args['labels']['name'] : $cpt['slug'],
                        'meta_fields' => is_array($meta) ? $meta : []
                    ];
                }
            }

            // Taxonomies
            $taxes = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}jet_taxonomies", ARRAY_A);
            if ($taxes) {
                foreach ($taxes as $tax) {
                    $args = maybe_unserialize($tax['args'] ?? '');
                    $result['taxonomies'][] = [
                        'id' => $tax['id'],
                        'slug' => $tax['slug'],
                        'name' => is_array($args) && isset($args['labels']['name']) ? $args['labels']['name'] : $tax['slug'],
                        'post_types' => is_array($args) && isset($args['object_type']) ? $args['object_type'] : []
                    ];
                }
            }

            // Relations
            $relations_table = $wpdb->get_var("SHOW TABLES LIKE '{$wpdb->prefix}jet_rel_types'");
            if ($relations_table) {
                $relations = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}jet_rel_types", ARRAY_A);
                if ($relations) {
                    foreach ($relations as $rel) {
                        $args = maybe_unserialize($rel['args'] ?? '');
                        $result['relations'][] = [
                            'id' => $rel['id'],
                            'name' => $rel['name'] ?? 'Unknown',
                            'parent' => is_array($args) ? ($args['parent_object'] ?? '') : '',
                            'child' => is_array($args) ? ($args['child_object'] ?? '') : '',
                            'type' => is_array($args) ? ($args['type'] ?? 'one_to_many') : 'one_to_many'
                        ];
                    }
                }
            }

            return $result;
        }

        /**
         * Analyze JetElements usage in Elementor pages
         */
        private function analyze_jetelements() {
            global $wpdb;

            $result = [
                'active' => is_plugin_active('jet-elements/jet-elements.php'),
                'widgets' => [],
                'pages' => []
            ];

            // Get all Elementor pages
            $pages = $wpdb->get_results(
                "SELECT p.ID, p.post_title, p.post_type, pm.meta_value
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE pm.meta_key = '_elementor_data'
                AND pm.meta_value LIKE '%jet-%'
                AND p.post_status IN ('publish', 'draft', 'private')",
                ARRAY_A
            );

            $widget_counts = [];
            $pages_with_widgets = [];

            foreach ($pages as $page) {
                $data = json_decode($page['meta_value'], true);
                if (!$data) continue;

                $found_widgets = $this->find_jet_widgets($data);

                if (!empty($found_widgets)) {
                    $pages_with_widgets[] = [
                        'id' => $page['ID'],
                        'title' => $page['post_title'],
                        'type' => $page['post_type'],
                        'edit_url' => admin_url('post.php?post=' . $page['ID'] . '&action=elementor'),
                        'widgets' => $found_widgets
                    ];

                    foreach ($found_widgets as $widget => $count) {
                        if (!isset($widget_counts[$widget])) {
                            $widget_counts[$widget] = 0;
                        }
                        $widget_counts[$widget] += $count;
                    }
                }
            }

            // Sort widgets by usage
            arsort($widget_counts);

            $result['widgets'] = $widget_counts;
            $result['pages'] = $pages_with_widgets;

            return $result;
        }

        /**
         * Recursively find Jet widgets in Elementor data
         */
        private function find_jet_widgets($elements, $widgets = []) {
            if (!is_array($elements)) return $widgets;

            foreach ($elements as $element) {
                // Check if this is a Jet widget
                if (isset($element['widgetType']) && strpos($element['widgetType'], 'jet-') === 0) {
                    $widget_type = $element['widgetType'];
                    if (!isset($widgets[$widget_type])) {
                        $widgets[$widget_type] = 0;
                    }
                    $widgets[$widget_type]++;
                }

                // Check if this is a Jet element (section/container)
                if (isset($element['elType']) && strpos($element['elType'], 'jet-') === 0) {
                    $el_type = $element['elType'];
                    if (!isset($widgets[$el_type])) {
                        $widgets[$el_type] = 0;
                    }
                    $widgets[$el_type]++;
                }

                // Recurse into children
                if (isset($element['elements']) && is_array($element['elements'])) {
                    $widgets = $this->find_jet_widgets($element['elements'], $widgets);
                }
            }

            return $widgets;
        }

        /**
         * Analyze JetSmartFilters
         */
        private function analyze_jetsmartfilters() {
            global $wpdb;

            $result = [
                'active' => is_plugin_active('jet-smart-filters/jet-smart-filters.php'),
                'filters' => [],
                'pages_with_filters' => []
            ];

            // Get all filter posts
            $filters = get_posts([
                'post_type' => 'jet-smart-filters',
                'posts_per_page' => -1,
                'post_status' => 'any'
            ]);

            foreach ($filters as $filter) {
                $filter_type = get_post_meta($filter->ID, '_filter_type', true);
                $data_source = get_post_meta($filter->ID, '_data_source', true);
                $query_var = get_post_meta($filter->ID, '_query_var', true);

                $result['filters'][] = [
                    'id' => $filter->ID,
                    'title' => $filter->post_title,
                    'type' => $filter_type ?: 'unknown',
                    'data_source' => $data_source ?: 'unknown',
                    'query_var' => $query_var,
                    'edit_url' => admin_url('post.php?post=' . $filter->ID . '&action=edit')
                ];
            }

            // Find pages using JetSmartFilters widgets
            $pages = $wpdb->get_results(
                "SELECT p.ID, p.post_title, p.post_type, pm.meta_value
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE pm.meta_key = '_elementor_data'
                AND (pm.meta_value LIKE '%jet-smart-filters%' OR pm.meta_value LIKE '%\"provider\"%')
                AND p.post_status IN ('publish', 'draft', 'private')",
                ARRAY_A
            );

            foreach ($pages as $page) {
                $data = json_decode($page['meta_value'], true);
                if (!$data) continue;

                $filter_widgets = $this->find_filter_widgets($data);

                if (!empty($filter_widgets)) {
                    $result['pages_with_filters'][] = [
                        'id' => $page['ID'],
                        'title' => $page['post_title'],
                        'type' => $page['post_type'],
                        'edit_url' => admin_url('post.php?post=' . $page['ID'] . '&action=elementor'),
                        'filter_widgets' => $filter_widgets
                    ];
                }
            }

            return $result;
        }

        /**
         * Find filter widgets in Elementor data
         */
        private function find_filter_widgets($elements, $widgets = []) {
            if (!is_array($elements)) return $widgets;

            foreach ($elements as $element) {
                // Check for JetSmartFilters widgets
                if (isset($element['widgetType'])) {
                    $widget_type = $element['widgetType'];
                    if (strpos($widget_type, 'jet-smart-filters') !== false ||
                        strpos($widget_type, 'jsf-') === 0) {
                        if (!isset($widgets[$widget_type])) {
                            $widgets[$widget_type] = 0;
                        }
                        $widgets[$widget_type]++;
                    }
                }

                // Check for provider settings (indicates filter target)
                if (isset($element['settings']['_element_id']) &&
                    isset($element['settings']['jet_smart_filters'])) {
                    $widgets['filter_target'] = ($widgets['filter_target'] ?? 0) + 1;
                }

                // Recurse into children
                if (isset($element['elements']) && is_array($element['elements'])) {
                    $widgets = $this->find_filter_widgets($element['elements'], $widgets);
                }
            }

            return $widgets;
        }

        /**
         * Analyze JetFormBuilder
         */
        private function analyze_jetformbuilder() {
            global $wpdb;

            $result = [
                'active' => is_plugin_active('jetformbuilder/jet-form-builder.php'),
                'forms' => [],
                'pages_with_forms' => []
            ];

            // Get all JetFormBuilder forms
            $forms = get_posts([
                'post_type' => 'jet-form-builder',
                'posts_per_page' => -1,
                'post_status' => 'any'
            ]);

            foreach ($forms as $form) {
                $content = $form->post_content;
                $blocks = parse_blocks($content);
                $field_count = $this->count_form_fields($blocks);

                $result['forms'][] = [
                    'id' => $form->ID,
                    'title' => $form->post_title,
                    'status' => $form->post_status,
                    'field_count' => $field_count,
                    'edit_url' => admin_url('post.php?post=' . $form->ID . '&action=edit')
                ];
            }

            // Find pages using JetFormBuilder
            $pages = $wpdb->get_results(
                "SELECT p.ID, p.post_title, p.post_type
                FROM {$wpdb->posts} p
                WHERE (p.post_content LIKE '%jet-form-builder%' OR p.post_content LIKE '%jetformbuilder%')
                AND p.post_status IN ('publish', 'draft', 'private')
                AND p.post_type NOT IN ('jet-form-builder', 'revision')",
                ARRAY_A
            );

            // Also check Elementor data
            $elementor_pages = $wpdb->get_results(
                "SELECT p.ID, p.post_title, p.post_type
                FROM {$wpdb->posts} p
                INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                WHERE pm.meta_key = '_elementor_data'
                AND pm.meta_value LIKE '%jet-form%'
                AND p.post_status IN ('publish', 'draft', 'private')",
                ARRAY_A
            );

            $all_pages = array_merge($pages, $elementor_pages);
            $unique_pages = [];

            foreach ($all_pages as $page) {
                if (!isset($unique_pages[$page['ID']])) {
                    $unique_pages[$page['ID']] = [
                        'id' => $page['ID'],
                        'title' => $page['post_title'],
                        'type' => $page['post_type'],
                        'edit_url' => admin_url('post.php?post=' . $page['ID'] . '&action=edit')
                    ];
                }
            }

            $result['pages_with_forms'] = array_values($unique_pages);

            return $result;
        }

        /**
         * Count form fields in blocks
         */
        private function count_form_fields($blocks, $count = 0) {
            foreach ($blocks as $block) {
                if (isset($block['blockName']) && strpos($block['blockName'], 'jet-forms/') === 0) {
                    $count++;
                }
                if (!empty($block['innerBlocks'])) {
                    $count = $this->count_form_fields($block['innerBlocks'], $count);
                }
            }
            return $count;
        }

        /**
         * Generate migration summary
         */
        private function generate_summary($results) {
            $summary = [
                'total_cpts' => count($results['jetengine']['cpts']),
                'total_taxonomies' => count($results['jetengine']['taxonomies']),
                'total_relations' => count($results['jetengine']['relations']),
                'total_meta_boxes' => count($results['jetengine']['meta_fields']),
                'total_jet_widgets' => array_sum($results['jetelements']['widgets']),
                'pages_with_jet_widgets' => count($results['jetelements']['pages']),
                'total_filters' => count($results['jetsmartfilters']['filters']),
                'pages_with_filters' => count($results['jetsmartfilters']['pages_with_filters']),
                'total_jet_forms' => count($results['jetformbuilder']['forms']),
                'pages_with_jet_forms' => count($results['jetformbuilder']['pages_with_forms']),
                'migration_tasks' => []
            ];

            // Generate migration tasks
            if ($summary['total_cpts'] > 0) {
                $summary['migration_tasks'][] = [
                    'task' => 'CPTs zu PHP/ACF migrieren',
                    'count' => $summary['total_cpts'],
                    'priority' => 'high',
                    'replacement' => 'register_post_type() in Modulen'
                ];
            }

            if ($summary['total_taxonomies'] > 0) {
                $summary['migration_tasks'][] = [
                    'task' => 'Taxonomien zu PHP migrieren',
                    'count' => $summary['total_taxonomies'],
                    'priority' => 'high',
                    'replacement' => 'register_taxonomy() in Modulen'
                ];
            }

            if ($summary['total_relations'] > 0) {
                $summary['migration_tasks'][] = [
                    'task' => 'Relations zu ACF Relationship migrieren',
                    'count' => $summary['total_relations'],
                    'priority' => 'medium',
                    'replacement' => 'ACF Relationship/Post Object Fields'
                ];
            }

            if ($summary['total_jet_widgets'] > 0) {
                $summary['migration_tasks'][] = [
                    'task' => 'JetElements Widgets ersetzen',
                    'count' => $summary['total_jet_widgets'],
                    'pages' => $summary['pages_with_jet_widgets'],
                    'priority' => 'high',
                    'replacement' => 'Elementor Pro Widgets'
                ];
            }

            if ($summary['total_filters'] > 0) {
                $summary['migration_tasks'][] = [
                    'task' => 'JetSmartFilters ersetzen',
                    'count' => $summary['total_filters'],
                    'pages' => $summary['pages_with_filters'],
                    'priority' => 'medium',
                    'replacement' => 'Elementor Pro Loop + Custom Filter'
                ];
            }

            if ($summary['total_jet_forms'] > 0) {
                $summary['migration_tasks'][] = [
                    'task' => 'JetFormBuilder Formulare migrieren',
                    'count' => $summary['total_jet_forms'],
                    'pages' => $summary['pages_with_jet_forms'],
                    'priority' => 'high',
                    'replacement' => 'Formidable Forms Pro'
                ];
            }

            return $summary;
        }

        /**
         * Export report as JSON
         */
        public function ajax_export_report() {
            check_ajax_referer('cba_nonce', 'nonce');

            if (!current_user_can('manage_options')) {
                wp_send_json_error(['message' => 'Keine Berechtigung']);
            }

            $results = [
                'generated' => current_time('mysql'),
                'site_url' => home_url(),
                'jetengine' => $this->analyze_jetengine(),
                'jetelements' => $this->analyze_jetelements(),
                'jetsmartfilters' => $this->analyze_jetsmartfilters(),
                'jetformbuilder' => $this->analyze_jetformbuilder()
            ];

            $results['summary'] = $this->generate_summary($results);

            wp_send_json_success([
                'filename' => 'crocoblock-analysis-' . date('Y-m-d-His') . '.json',
                'content' => $results
            ]);
        }
    }
}

// Initialize
if (!isset($GLOBALS['crocoblock_analyzer_initialized'])) {
    $GLOBALS['crocoblock_analyzer_initialized'] = true;
    Crocoblock_Analyzer::get_instance();
}
