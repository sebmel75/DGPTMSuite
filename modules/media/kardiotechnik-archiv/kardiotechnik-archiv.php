<?php
/**
 * Plugin Name: Kardiotechnik Archiv
 * Plugin URI: https://www.dgptm.de
 * Description: Durchsuchbares Archiv für Kardiotechnik-Zeitschriften mit PDF-Verwaltung
 * Version: 1.0.0
 * Author: DGPTM
 * Author URI: https://www.dgptm.de
 * License: GPL2
 * Text Domain: kardiotechnik-archiv
 */

// Direkten Dateizugriff verhindern
if (!defined('ABSPATH')) {
    exit;
}

// Plugin-Konstanten definieren
define('KTA_VERSION', '1.0.0');
define('KTA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('KTA_PLUGIN_URL', plugin_dir_url(__FILE__));

// Hauptklasse des Plugins
class Kardiotechnik_Archiv {

    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct() {
        // Plugin aktivieren/deaktivieren
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));

        // Hooks initialisieren
        add_action('init', array($this, 'init'));
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_enqueue_scripts', array($this, 'enqueue_frontend_scripts'));

        // AJAX-Handler
        add_action('wp_ajax_kta_search', array($this, 'ajax_search'));
        add_action('wp_ajax_nopriv_kta_search', array($this, 'ajax_search'));
        add_action('wp_ajax_kta_import_pdfs', array($this, 'ajax_import_pdfs'));
        add_action('wp_ajax_kta_delete_article', array($this, 'ajax_delete_article'));
        add_action('wp_ajax_kta_get_article', array($this, 'ajax_get_article'));
        add_action('wp_ajax_kta_manual_add_article', array($this, 'ajax_manual_add_article'));

        // Shortcode registrieren
        add_shortcode('kardiotechnik_archiv', array($this, 'shortcode_archive'));
    }

    // Plugin-Aktivierung
    public function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'kardiotechnik_articles';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            year int(4) NOT NULL,
            issue varchar(10) NOT NULL,
            title varchar(255) NOT NULL,
            author varchar(255) DEFAULT '',
            keywords text DEFAULT '',
            abstract text DEFAULT '',
            page_start int(5) DEFAULT NULL,
            page_end int(5) DEFAULT NULL,
            pdf_path varchar(500) NOT NULL,
            pdf_url varchar(500) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY year (year),
            KEY issue (issue),
            FULLTEXT KEY search_idx (title, author, keywords, abstract)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Plugin-Version speichern
        update_option('kta_version', KTA_VERSION);
    }

    // Plugin-Deaktivierung
    public function deactivate() {
        // Cleanup-Aufgaben bei Bedarf
    }

    // Initialisierung
    public function init() {
        load_plugin_textdomain('kardiotechnik-archiv', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }

    // Admin-Menü hinzufügen
    public function add_admin_menu() {
        add_menu_page(
            'Kardiotechnik Archiv',
            'Kardiotechnik',
            'manage_options',
            'kardiotechnik-archiv',
            array($this, 'admin_page'),
            'dashicons-book-alt',
            30
        );

        add_submenu_page(
            'kardiotechnik-archiv',
            'Artikel verwalten',
            'Artikel',
            'manage_options',
            'kardiotechnik-archiv',
            array($this, 'admin_page')
        );

        add_submenu_page(
            'kardiotechnik-archiv',
            'PDFs importieren',
            'Import',
            'manage_options',
            'kardiotechnik-import',
            array($this, 'import_page')
        );
    }

    // Admin-Skripte und -Styles laden
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'kardiotechnik') === false) {
            return;
        }

        wp_enqueue_style('kta-admin-css', KTA_PLUGIN_URL . 'assets/admin.css', array(), KTA_VERSION);
        wp_enqueue_script('kta-admin-js', KTA_PLUGIN_URL . 'assets/admin.js', array('jquery'), KTA_VERSION, true);

        wp_localize_script('kta-admin-js', 'ktaAdmin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kta_admin_nonce')
        ));
    }

    // Frontend-Skripte und -Styles laden
    public function enqueue_frontend_scripts() {
        wp_enqueue_style('kta-frontend-css', KTA_PLUGIN_URL . 'assets/frontend.css', array(), KTA_VERSION);
        wp_enqueue_script('kta-frontend-js', KTA_PLUGIN_URL . 'assets/frontend.js', array('jquery'), KTA_VERSION, true);

        wp_localize_script('kta-frontend-js', 'ktaFrontend', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('kta_frontend_nonce')
        ));
    }

    // Admin-Seite: Artikel verwalten
    public function admin_page() {
        include KTA_PLUGIN_DIR . 'templates/admin-articles.php';
    }

    // Admin-Seite: Import
    public function import_page() {
        include KTA_PLUGIN_DIR . 'templates/admin-import.php';
    }

    // AJAX: Suche durchführen
    public function ajax_search() {
        check_ajax_referer('kta_frontend_nonce', 'nonce');

        global $wpdb;
        $table_name = $wpdb->prefix . 'kardiotechnik_articles';

        $search = isset($_POST['search']) ? sanitize_text_field($_POST['search']) : '';
        $year_from = isset($_POST['year_from']) ? intval($_POST['year_from']) : 1975;
        $year_to = isset($_POST['year_to']) ? intval($_POST['year_to']) : date('Y');

        $where = "WHERE year BETWEEN %d AND %d";
        $params = array($year_from, $year_to);

        if (!empty($search)) {
            $where .= " AND (title LIKE %s OR author LIKE %s OR keywords LIKE %s OR abstract LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
            $params[] = $search_term;
        }

        $sql = "SELECT * FROM $table_name $where ORDER BY year DESC, issue DESC";
        $results = $wpdb->get_results($wpdb->prepare($sql, $params));

        wp_send_json_success(array('results' => $results));
    }

    // AJAX: PDFs importieren
    public function ajax_import_pdfs() {
        check_ajax_referer('kta_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
        }

        $pdf_directory = isset($_POST['pdf_directory']) ? sanitize_text_field($_POST['pdf_directory']) : '';

        if (empty($pdf_directory) || !is_dir($pdf_directory)) {
            wp_send_json_error('Ungültiges Verzeichnis');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'kardiotechnik_articles';
        $imported = 0;

        // Verzeichnisse durchsuchen
        $directories = glob($pdf_directory . '/*', GLOB_ONLYDIR);

        foreach ($directories as $dir) {
            $dirname = basename($dir);

            // Extrahiere Jahr und Ausgabe aus dem Verzeichnisnamen (z.B. "1975-01 Kardiotechnik")
            if (preg_match('/^(\d{4})-(\d{1,2}|S\d{1,2})\s+Kardiotechnik$/i', $dirname, $matches)) {
                $year = intval($matches[1]);
                $issue = $matches[2];

                // Suche nach PDF-Dateien
                $pdf_files = glob($dir . '/*.pdf');

                foreach ($pdf_files as $pdf_file) {
                    // Prüfe, ob bereits importiert
                    $exists = $wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(*) FROM $table_name WHERE pdf_path = %s",
                        $pdf_file
                    ));

                    if ($exists > 0) {
                        continue;
                    }

                    // Upload in WordPress Media Library
                    $upload_file = $this->upload_pdf_to_media_library($pdf_file);

                    if (is_wp_error($upload_file)) {
                        continue;
                    }

                    // Datensatz erstellen
                    $wpdb->insert(
                        $table_name,
                        array(
                            'year' => $year,
                            'issue' => $issue,
                            'title' => 'Kardiotechnik ' . $year . '-' . $issue,
                            'pdf_path' => $pdf_file,
                            'pdf_url' => $upload_file['url']
                        ),
                        array('%d', '%s', '%s', '%s', '%s')
                    );

                    $imported++;
                }
            }
        }

        wp_send_json_success(array('imported' => $imported));
    }

    // PDF in Media Library hochladen
    private function upload_pdf_to_media_library($file_path) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $filename = basename($file_path);
        $upload_dir = wp_upload_dir();

        // Zielverzeichnis erstellen
        $target_dir = $upload_dir['basedir'] . '/kardiotechnik-archiv';
        if (!file_exists($target_dir)) {
            wp_mkdir_p($target_dir);
        }

        $target_file = $target_dir . '/' . $filename;

        // Datei kopieren
        if (!copy($file_path, $target_file)) {
            return new WP_Error('copy_failed', 'Datei konnte nicht kopiert werden');
        }

        // Attachment erstellen
        $wp_filetype = wp_check_filetype($filename, null);
        $attachment = array(
            'post_mime_type' => $wp_filetype['type'],
            'post_title' => sanitize_file_name($filename),
            'post_content' => '',
            'post_status' => 'inherit'
        );

        $attach_id = wp_insert_attachment($attachment, $target_file);
        $attach_data = wp_generate_attachment_metadata($attach_id, $target_file);
        wp_update_attachment_metadata($attach_id, $attach_data);

        return array(
            'id' => $attach_id,
            'url' => $upload_dir['baseurl'] . '/kardiotechnik-archiv/' . $filename
        );
    }

    // AJAX: Artikel löschen
    public function ajax_delete_article() {
        check_ajax_referer('kta_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
        }

        $article_id = isset($_POST['article_id']) ? intval($_POST['article_id']) : 0;

        global $wpdb;
        $table_name = $wpdb->prefix . 'kardiotechnik_articles';

        $deleted = $wpdb->delete($table_name, array('id' => $article_id), array('%d'));

        if ($deleted) {
            wp_send_json_success('Artikel gelöscht');
        } else {
            wp_send_json_error('Fehler beim Löschen');
        }
    }

    // AJAX: Artikel abrufen
    public function ajax_get_article() {
        check_ajax_referer('kta_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
        }

        $article_id = isset($_POST['article_id']) ? intval($_POST['article_id']) : 0;

        global $wpdb;
        $table_name = $wpdb->prefix . 'kardiotechnik_articles';

        $article = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d",
            $article_id
        ));

        if ($article) {
            wp_send_json_success($article);
        } else {
            wp_send_json_error('Artikel nicht gefunden');
        }
    }

    // AJAX: Manuell Artikel hinzufügen
    public function ajax_manual_add_article() {
        check_ajax_referer('kta_admin_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Keine Berechtigung');
        }

        // Datei-Upload verarbeiten
        if (empty($_FILES['pdf_file'])) {
            wp_send_json_error('Keine PDF-Datei hochgeladen');
        }

        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $uploadedfile = $_FILES['pdf_file'];
        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);

        if ($movefile && !isset($movefile['error'])) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'kardiotechnik_articles';

            // Datensatz erstellen
            $wpdb->insert(
                $table_name,
                array(
                    'year' => intval($_POST['year']),
                    'issue' => sanitize_text_field($_POST['issue']),
                    'title' => sanitize_text_field($_POST['title']),
                    'author' => sanitize_text_field($_POST['author']),
                    'keywords' => sanitize_textarea_field($_POST['keywords']),
                    'abstract' => sanitize_textarea_field($_POST['abstract']),
                    'page_start' => intval($_POST['page_start']),
                    'page_end' => intval($_POST['page_end']),
                    'pdf_path' => $movefile['file'],
                    'pdf_url' => $movefile['url']
                ),
                array('%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%s')
            );

            wp_send_json_success('Artikel wurde hinzugefügt');
        } else {
            wp_send_json_error($movefile['error']);
        }
    }

    // Shortcode: Archiv anzeigen
    public function shortcode_archive($atts) {
        $atts = shortcode_atts(array(
            'year_from' => 1975,
            'year_to' => date('Y')
        ), $atts);

        ob_start();
        include KTA_PLUGIN_DIR . 'templates/frontend-archive.php';
        return ob_get_clean();
    }
}

// Plugin initialisieren
function kta_init() {
    return Kardiotechnik_Archiv::get_instance();
}

// Plugin starten
add_action('plugins_loaded', 'kta_init');
