<?php
/**
 * Guide Manager für DGPTM Plugin Suite
 * Verwaltet Modul-Anleitungen und Dokumentation
 */

if (!defined('ABSPATH')) {
    exit;
}

class DGPTM_Guide_Manager {

    private static $instance = null;
    private $guides_dir;
    private $guides_cache = [];

    /**
     * Singleton Instance
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Konstruktor
     */
    private function __construct() {
        $this->guides_dir = DGPTM_SUITE_PATH . 'guides/';

        // Verzeichnis erstellen wenn nicht vorhanden
        if (!file_exists($this->guides_dir)) {
            wp_mkdir_p($this->guides_dir);
        }

        // AJAX Hooks - nur im Admin-Bereich
        if (is_admin()) {
            add_action('wp_ajax_dgptm_get_guide', [$this, 'ajax_get_guide']);
            add_action('wp_ajax_dgptm_search_guides', [$this, 'ajax_search_guides']);
            add_action('wp_ajax_dgptm_save_guide', [$this, 'ajax_save_guide']);
            add_action('wp_ajax_dgptm_generate_all_guides', [$this, 'ajax_generate_all_guides']);
        }
    }

    /**
     * Anleitung für Modul abrufen
     */
    public function get_guide($module_id) {
        // Cache prüfen
        if (isset($this->guides_cache[$module_id])) {
            return $this->guides_cache[$module_id];
        }

        $guide_file = $this->guides_dir . $module_id . '.json';

        if (!file_exists($guide_file)) {
            // Default-Anleitung generieren wenn nicht vorhanden
            return $this->generate_default_guide($module_id);
        }

        $guide_content = file_get_contents($guide_file);
        $guide = json_decode($guide_content, true);

        if (!$guide) {
            return $this->generate_default_guide($module_id);
        }

        // Cache speichern
        $this->guides_cache[$module_id] = $guide;

        return $guide;
    }

    /**
     * Anleitung speichern
     */
    public function save_guide($module_id, $guide_data) {
        $guide_file = $this->guides_dir . $module_id . '.json';

        // Validierung
        if (!isset($guide_data['title']) || !isset($guide_data['content'])) {
            return new WP_Error('invalid_guide', __('Ungültige Anleitungsdaten.', 'dgptm-suite'));
        }

        // Meta-Daten hinzufügen
        $guide_data['module_id'] = $module_id;
        $guide_data['last_updated'] = current_time('mysql');
        $guide_data['updated_by'] = get_current_user_id();

        // Speichern
        $result = file_put_contents($guide_file, json_encode($guide_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        if ($result === false) {
            return new WP_Error('save_failed', __('Anleitung konnte nicht gespeichert werden.', 'dgptm-suite'));
        }

        // Cache aktualisieren
        $this->guides_cache[$module_id] = $guide_data;

        return true;
    }

    /**
     * Alle Anleitungen abrufen
     */
    public function get_all_guides() {
        $guides = [];
        $module_loader = dgptm_suite()->get_module_loader();
        $available_modules = $module_loader->get_available_modules();

        foreach ($available_modules as $module_id => $module_info) {
            $guides[$module_id] = $this->get_guide($module_id);
        }

        return $guides;
    }

    /**
     * Anleitungen durchsuchen
     */
    public function search_guides($search_term) {
        $search_term = strtolower($search_term);
        $results = [];
        $all_guides = $this->get_all_guides();

        foreach ($all_guides as $module_id => $guide) {
            $score = 0;

            // Titel durchsuchen
            if (stripos($guide['title'], $search_term) !== false) {
                $score += 10;
            }

            // Beschreibung durchsuchen
            if (stripos($guide['description'], $search_term) !== false) {
                $score += 5;
            }

            // Inhalt durchsuchen
            if (stripos($guide['content'], $search_term) !== false) {
                $score += 3;
            }

            // Features durchsuchen
            if (isset($guide['features'])) {
                foreach ($guide['features'] as $feature) {
                    if (stripos($feature, $search_term) !== false) {
                        $score += 2;
                    }
                }
            }

            // Keywords durchsuchen
            if (isset($guide['keywords'])) {
                foreach ($guide['keywords'] as $keyword) {
                    if (stripos($keyword, $search_term) !== false) {
                        $score += 4;
                    }
                }
            }

            if ($score > 0) {
                $results[] = [
                    'module_id' => $module_id,
                    'guide' => $guide,
                    'score' => $score,
                ];
            }
        }

        // Nach Score sortieren
        usort($results, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        return $results;
    }

    /**
     * Default-Anleitung generieren
     */
    private function generate_default_guide($module_id) {
        $module_loader = dgptm_suite()->get_module_loader();
        $config = $module_loader->get_module_config($module_id);

        if (!$config) {
            return [
                'title' => __('Modul nicht gefunden', 'dgptm-suite'),
                'description' => '',
                'content' => '',
                'features' => [],
                'keywords' => [],
            ];
        }

        return [
            'title' => $config['name'],
            'description' => $config['description'] ?? '',
            'content' => sprintf(
                __('# %s\n\n## Beschreibung\n\n%s\n\n## Version\n\n%s\n\n## Kategorie\n\n%s\n\n_Detaillierte Anleitung wird noch erstellt._', 'dgptm-suite'),
                $config['name'],
                $config['description'] ?? '',
                $config['version'] ?? '1.0.0',
                $config['category'] ?? 'utilities'
            ),
            'features' => [],
            'keywords' => [$module_id, $config['name']],
            'category' => $config['category'] ?? 'utilities',
        ];
    }

    /**
     * AJAX: Anleitung abrufen
     */
    public function ajax_get_guide() {
        // Nonce prüfen
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dgptm_suite_nonce')) {
            wp_send_json_error(['message' => __('Nonce verification failed.', 'dgptm-suite')]);
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dgptm-suite')]);
            return;
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');

        if (empty($module_id)) {
            wp_send_json_error(['message' => __('Invalid module ID.', 'dgptm-suite')]);
            return;
        }

        $guide = $this->get_guide($module_id);

        wp_send_json_success([
            'guide' => $guide,
            'module_id' => $module_id,
        ]);
    }

    /**
     * AJAX: Anleitungen durchsuchen
     */
    public function ajax_search_guides() {
        // Nonce prüfen
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dgptm_suite_nonce')) {
            wp_send_json_error(['message' => __('Nonce verification failed.', 'dgptm-suite')]);
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dgptm-suite')]);
            return;
        }

        $search_term = sanitize_text_field($_POST['search_term'] ?? '');

        if (empty($search_term)) {
            wp_send_json_error(['message' => __('Suchbegriff fehlt.', 'dgptm-suite')]);
            return;
        }

        $results = $this->search_guides($search_term);

        wp_send_json_success([
            'results' => $results,
            'count' => count($results),
        ]);
    }

    /**
     * AJAX: Anleitung speichern
     */
    public function ajax_save_guide() {
        check_ajax_referer('dgptm_suite_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Insufficient permissions.', 'dgptm-suite')]);
        }

        $module_id = sanitize_text_field($_POST['module_id'] ?? '');
        $title = sanitize_text_field($_POST['title'] ?? '');
        $description = sanitize_textarea_field($_POST['description'] ?? '');
        $content = wp_kses_post($_POST['content'] ?? '');
        $features = array_map('sanitize_text_field', $_POST['features'] ?? []);
        $keywords = array_map('sanitize_text_field', $_POST['keywords'] ?? []);

        if (empty($module_id)) {
            wp_send_json_error(['message' => __('Invalid module ID.', 'dgptm-suite')]);
        }

        $guide_data = [
            'title' => $title,
            'description' => $description,
            'content' => $content,
            'features' => $features,
            'keywords' => $keywords,
        ];

        $result = $this->save_guide($module_id, $guide_data);

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        wp_send_json_success([
            'message' => __('Anleitung erfolgreich gespeichert.', 'dgptm-suite'),
            'guide' => $this->get_guide($module_id),
        ]);
    }

    /**
     * AJAX: Alle Anleitungen generieren
     */
    public function ajax_generate_all_guides() {
        // Nonce-Validierung
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'dgptm_suite_nonce')) {
            wp_send_json_error(['message' => __('Sicherheitsprüfung fehlgeschlagen.', 'dgptm-suite')]);
            return;
        }

        // Berechtigungsprüfung
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Keine Berechtigung.', 'dgptm-suite')]);
            return;
        }

        // Import-Skript laden und ausführen
        $import_file = DGPTM_SUITE_PATH . 'import-all-guides.php';

        if (!file_exists($import_file)) {
            wp_send_json_error(['message' => __('Import-Skript nicht gefunden.', 'dgptm-suite')]);
            return;
        }

        // Output buffering starten
        ob_start();

        // Import-Skript ausführen
        try {
            require $import_file;
            $output = ob_get_clean();

            // Cache leeren
            $this->guides_cache = [];

            // Anzahl generierter Guides zählen
            $guide_files = glob($this->guides_dir . '*.json');
            $count = count($guide_files);

            wp_send_json_success([
                'message' => sprintf(
                    __('%d Anleitungen erfolgreich generiert!', 'dgptm-suite'),
                    $count
                ),
                'count' => $count,
                'output' => $output,
            ]);
        } catch (Exception $e) {
            ob_end_clean();
            wp_send_json_error([
                'message' => __('Fehler beim Generieren der Anleitungen: ', 'dgptm-suite') . $e->getMessage(),
            ]);
        }
    }

    /**
     * Markdown zu HTML konvertieren (einfache Implementierung)
     */
    public function markdown_to_html($markdown) {
        // Einfache Markdown-Konvertierung
        $html = $markdown;

        // Headers
        $html = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $html);

        // Bold
        $html = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $html);

        // Italic
        $html = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $html);

        // Code
        $html = preg_replace('/`(.*?)`/', '<code>$1</code>', $html);

        // Links
        $html = preg_replace('/\[(.*?)\]\((.*?)\)/', '<a href="$2" target="_blank">$1</a>', $html);

        // Listen
        $html = preg_replace('/^- (.*?)$/m', '<li>$1</li>', $html);
        $html = preg_replace('/(<li>.*<\/li>)/s', '<ul>$1</ul>', $html);

        // Absätze
        $html = preg_replace('/\n\n/', '</p><p>', $html);
        $html = '<p>' . $html . '</p>';

        return $html;
    }
}
